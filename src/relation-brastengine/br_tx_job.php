<?php declare(strict_types=1);

/**
 * Blastengine トランザクション配信ジョブ
 * - CLI 専用。ただし index.php からは ALLOW_WEB_RUN = true の場合に限り実行可能
 * - セキュリティ硬化：log制御文字除去 / 作業dir 0775 / PDO emulate off
 */

if (PHP_SAPI !== 'cli' && !defined('ALLOW_WEB_RUN')) {
  http_response_code(403);
  exit('Forbidden');
}

$BASE_DIR = __DIR__;
require_once $BASE_DIR . '/../formapp/setting.php';
require_once $BASE_DIR . '/../formapp/class/DB.class.php';

/* ========================
   基本設定
   ======================== */
$TABLE            = 'entry';
$FAIL_TABLE       = 'delivery_failures';
$BE_BASE          = 'https://app.engn.jp/api/v1';

$BE_TOKEN         = defined('BE_BEARER_TOKEN') ? BE_BEARER_TOKEN : '';
$FROM_EMAIL       = defined('BE_FROM_EMAIL')   ? BE_FROM_EMAIL   : 'no-reply@example.com';
$FROM_NAME        = defined('BE_FROM_NAME')    ? BE_FROM_NAME    : '送信者名';

$SUBJECT_USER     = defined('BE_FROM_SUBJECT')  ? BE_FROM_SUBJECT  : '【お知らせ】プレゼントのご案内';
$SUBJECT_ADMIN    = defined('BE_ADMIN_SUBJECT') ? BE_ADMIN_SUBJECT : '応募がありました';
$ADMIN_TO         = defined('BE_ADMIN_TO')      ? BE_ADMIN_TO      : '';

$BATCH_LIMIT      = 250;
$MAX_RUN_SECONDS  = 240;
$PACE_USLEEP      = 400000;
$MAX_RETRIES      = 5;
$FAIL_THRESHOLD   = 3;
$RETRY_DELAY_SEC  = 3600;

$WORKDIR          = $BASE_DIR . '/tx_job_work';
$LOG_PATH         = $WORKDIR . '/job.log';

/* ========================
   テンプレート設定（パス）
   ======================== */
// ▼ユーザー宛テンプレ（必須: text / 任意: html）
$TPL_TEXT_PATH = $BASE_DIR . '/tpl/text_part.tpl';
$TPL_HTML_PATH = $BASE_DIR . '/tpl/html_part.tpl';

// ▼管理者宛テンプレ（任意: 両方なくてもOK）
$TPL_ADMIN_TEXT_PATH = $BASE_DIR . '/tpl/admin_notify.tpl';
$TPL_ADMIN_HTML_PATH = $BASE_DIR . '/tpl/admin_notify_html.tpl';

/* ========================
   作業DIR & トークン確認
   ======================== */
if (!is_dir($WORKDIR)) {
  $old = umask(0002); mkdir($WORKDIR, 0775, true); umask($old);
}

if (!$BE_TOKEN) {
  fwrite(STDERR, "[ERROR] BE_BEARER_TOKEN が未設定です。\n");
  exit(1);
}

/* ========================
   排他制御
   ======================== */
$lock = fopen($WORKDIR . '/.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
  echo "[WARN] Another instance is running.\n";
  exit(0);
}

/* ========================
   ログ
   ======================== */
function logf(string $msg): void {
  global $LOG_PATH;
  $msg = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $msg); // 制御文字除去
  $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg);
  file_put_contents($LOG_PATH, $line, FILE_APPEND);
  echo $line;
}

/* ========================
   テンプレート読み込み
   ======================== */
// --- ユーザー宛 ---
$TPL_TEXT_RAW = @file_get_contents($TPL_TEXT_PATH);
$TPL_HTML_RAW = @file_get_contents($TPL_HTML_PATH);

if ($TPL_TEXT_RAW === false) {
  fwrite(STDERR, "[ERROR] text template not found: {$TPL_TEXT_PATH}\n");
  exit(1);
}
if ($TPL_HTML_RAW === false) { $TPL_HTML_RAW = ''; }

// --- 管理者宛（あれば使用 / なくてもOK）---
$TPL_ADMIN_TXT  = is_readable($TPL_ADMIN_TEXT_PATH)
                ? file_get_contents($TPL_ADMIN_TEXT_PATH)
                : null;

$TPL_ADMIN_HTML = is_readable($TPL_ADMIN_HTML_PATH)
                ? file_get_contents($TPL_ADMIN_HTML_PATH)
                : null;

/* ========================
   整形
   ======================== */
function format_member4($raw): string {
  $digits = preg_replace('/\D/', '', (string)$raw);
  return trim(chunk_split($digits, 4, ' '));
}

/* ========================
   差し込み
   ======================== */
function render_tpl(string $tpl, array $row, bool $isHtml = false): string {
  $rawMember       = (string)($row['member_id'] ?? '');
  $memberFormatted = format_member4($rawMember);
  $present         = (string)($row['present'] ?? '');
  $email           = (string)($row['email'] ?? '');

  if ($isHtml) {
    $memberFormatted = htmlspecialchars($memberFormatted, ENT_QUOTES, 'UTF-8');
    $present         = htmlspecialchars($present,         ENT_QUOTES, 'UTF-8');
    $email           = htmlspecialchars($email,           ENT_QUOTES, 'UTF-8');
  }
  return strtr($tpl, [
    '__member_id__' => $memberFormatted,
    '__present__'   => $present,
    '__email__'     => $email,
  ]);
}

/* ========================
   HTTPユーティリティ
   ======================== */
function http_with_headers(string $method, string $url, array $headers = [], $body = null): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_HEADER         => true,
  ]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

  $raw   = curl_exec($ch);
  $errno = curl_errno($ch);
  $err   = curl_error($ch);
  $code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $hsize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  curl_close($ch);

  if ($raw === false) return [0, [], null, $err];

  $hraw = substr($raw, 0, $hsize);
  $body = substr($raw, $hsize);

  $hs = [];
  foreach (explode("\r\n", $hraw) as $line) {
    $p = strpos($line, ':');
    if ($p !== false) {
      $k = strtolower(trim(substr($line, 0, $p)));
      $v = trim(substr($line, $p+1));
      if ($k !== '') $hs[$k] = $v;
    }
  }
  $json = json_decode($body, true);
  return [$code, $hs, $json ?? $body, $errno ? $err : null];
}

/* ========================
   送信
   ======================== */
function be_tx_send(array $row): array {
  global $BE_BASE, $BE_TOKEN, $FROM_EMAIL, $FROM_NAME, $MAX_RETRIES;
  global $TPL_TEXT_RAW, $TPL_HTML_RAW, $TPL_ADMIN_TXT, $TPL_ADMIN_HTML;
  global $SUBJECT_USER, $SUBJECT_ADMIN, $ADMIN_TO;

  $to = strtolower(trim((string)$row['email']));

  $unsubscribeMail = defined('BE_UNSUBSCRIBE_MAIL') ? BE_UNSUBSCRIBE_MAIL : 'unsubscribe@example.com';
  $unsubscribeUrl  = defined('BE_UNSUBSCRIBE_URL')  ? BE_UNSUBSCRIBE_URL  : 'https://example.com/unsubscribe';
  $unsubscribeFullUrl = $unsubscribeUrl . '?email=' . urlencode($to);

  // ユーザー宛（mo.png回避のため text のみ。HTML送るなら html_part を有効化）
  $payloadUser = [
    'from'      => ['email' => $FROM_EMAIL, 'name' => $FROM_NAME],
    'to'        => $to,
    'subject'   => $SUBJECT_USER,
    'text_part' => render_tpl($TPL_TEXT_RAW, $row, false),
    // 'html_part' => render_tpl($TPL_HTML_RAW, $row, true),
    // 'headers'   => ['List-Unsubscribe' => "<mailto:{$unsubscribeMail}>, <{$unsubscribeFullUrl}>"],
  ];

  // 管理者通知（テンプレなしでも送る & member_idは4桁区切り）
  $payloadAdmin = null;
  if (!empty($ADMIN_TO)) {
    if (is_string($TPL_ADMIN_TXT)) {
      $adminText = render_tpl($TPL_ADMIN_TXT, $row, false);
    } else {
      $adminText = "応募がありました。\n"
                 . "会員ID: " . format_member4($row['member_id'] ?? '') . "\n"
                 . "メール: "   . ($row['email'] ?? '') . "\n"
                 . "内容: "     . ($row['present'] ?? '') . "\n";
    }
    if (is_string($TPL_ADMIN_HTML)) {
      $adminHtml = render_tpl($TPL_ADMIN_HTML, $row, true);
    } else {
      $adminHtml = nl2br(htmlspecialchars($adminText, ENT_QUOTES, 'UTF-8'));
    }
    $payloadAdmin = [
      'from'      => ['email' => $FROM_EMAIL, 'name' => $FROM_NAME],
      'to'        => $ADMIN_TO,
      'subject'   => $SUBJECT_ADMIN,
      'text_part' => $adminText,
      // 'html_part' => $adminHtml,
    ];
  }

  // 共通送信
  $sendFunc = function($payload) use ($BE_BASE, $BE_TOKEN, $MAX_RETRIES) {
    $attempt = 0;
    while (true) {
      $attempt++;
      [$code, $hdrs, $res, $err] = http_with_headers(
        'POST',
        $BE_BASE.'/deliveries/transaction',
        [
          'Authorization: Bearer ' . $BE_TOKEN,
          'Accept-Language: ja-JP',
          'Content-Type: application/json'
        ],
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
      );

      if ($code === 201) return [true, $code, $res];

      if ($code === 0) {
        logf("TX CURL error: {$err}");
      } elseif ($code === 429) {
        $sleep = (int)($hdrs['retry-after'] ?? 1);
        logf("TX 429 retry {$sleep}s"); sleep($sleep);
      } elseif ($code >= 500) {
        $sleep = min(60, 2 ** $attempt);
        logf("TX {$code} server error sleep {$sleep}s"); sleep($sleep);
      } elseif ($code == 408) {
        logf("TX 408 retry 60s"); sleep(60);
      } else {
        $bodyShow = is_array($res) ? json_encode($res, JSON_UNESCAPED_UNICODE) : (string)$res;
        logf("TX FAIL ({$code}): {$bodyShow}");
        return [false, $code, $res];
      }

      if ($attempt >= $MAX_RETRIES) {
        logf("TX give up after {$attempt} attempts.");
        return [false, $code, $res];
      }
    }
  };

  // ユーザー宛送信
  [$okUser, $codeUser, $resUser] = $sendFunc($payloadUser);

  // ユーザー宛成功時のみ、管理者通知
  if ($okUser) {
    if ($payloadAdmin) {
      [$okAdmin, $codeAdmin, $resAdmin] = $sendFunc($payloadAdmin);
      if ($okAdmin) { logf("ADMIN_NOTICE sent to {$ADMIN_TO}"); }
      else {
        $body = is_array($resAdmin) ? json_encode($resAdmin, JSON_UNESCAPED_UNICODE) : (string)$resAdmin;
        logf("ADMIN_NOTICE FAIL to {$ADMIN_TO} code={$codeAdmin} body={$body}");
      }
    } else {
      if (empty($ADMIN_TO)) logf("ADMIN_NOTICE skipped (BE_ADMIN_TO not set)");
      else logf("ADMIN_NOTICE skipped (payloadAdmin not built)");
    }
  }

  return [$okUser, $codeUser, $resUser];
}

/* ========================
   実行本体（index.phpからも呼べる）
   ======================== */
function run_br_tx_job(): int {
  global $TABLE, $FAIL_TABLE;
  global $BATCH_LIMIT, $FAIL_THRESHOLD, $RETRY_DELAY_SEC, $MAX_RUN_SECONDS, $PACE_USLEEP;

  $start = time(); $sent = 0; $failed = 0;

  try {
    $pdo = new PDO(
      sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
      DB_USER, DB_PASSWORD,
      [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
      ]
    );
    $pdo->exec("SET NAMES utf8mb4");

    // 失敗カウントテーブル
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS {$FAIL_TABLE} (
        email VARCHAR(320) PRIMARY KEY,
        fail_count INT NOT NULL DEFAULT 0,
        last_error_at DATETIME NULL,
        last_code INT NULL,
        last_body TEXT NULL
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $stmtMarkSent  = $pdo->prepare("UPDATE {$TABLE} SET send_flg=1, retry_at=NULL WHERE id=?");
    $stmtResetFail = $pdo->prepare("UPDATE {$FAIL_TABLE} SET fail_count=0,last_code=NULL,last_body=NULL WHERE email=?");
    $stmtUpFail    = $pdo->prepare("
      INSERT INTO {$FAIL_TABLE} (email, fail_count, last_error_at, last_code, last_body)
      VALUES (?, 1, NOW(), ?, ?)
      ON DUPLICATE KEY UPDATE
        fail_count = fail_count + 1,
        last_error_at = NOW(),
        last_code = VALUES(last_code),
        last_body = VALUES(last_body)
    ");
    $stmtGetFail   = $pdo->prepare("SELECT fail_count FROM {$FAIL_TABLE} WHERE email=?");
    $stmtQuaran    = $pdo->prepare("UPDATE {$TABLE} SET quarantine_flg=1 WHERE send_flg=0 AND LOWER(TRIM(email))=?");
    $stmtScheduleRt= $pdo->prepare("UPDATE {$TABLE} SET retry_at = DATE_ADD(NOW(), INTERVAL :sec SECOND) WHERE id=:id");

    do {
      $sql = "
        SELECT e.id, e.member_id, e.present, e.email
        FROM {$TABLE} e
        LEFT JOIN {$FAIL_TABLE} f ON f.email = LOWER(TRIM(e.email))
        WHERE e.send_flg = 0
          AND COALESCE(e.quarantine_flg,0) = 0
          AND (e.retry_at IS NULL OR e.retry_at <= NOW())
          AND e.email <> ''
          AND COALESCE(f.fail_count,0) < :th
        ORDER BY COALESCE(e.retry_at,'1970-01-01'), e.id
        LIMIT :lim
      ";
      $st=$pdo->prepare($sql);
      $st->bindValue(':th',$FAIL_THRESHOLD,PDO::PARAM_INT);
      $st->bindValue(':lim',$BATCH_LIMIT,PDO::PARAM_INT);
      $st->execute();
      $rows=$st->fetchAll();

      if (!$rows) { logf("no pending rows. sent={$sent} failed={$failed} exit."); break; }

      logf("batch rows=".count($rows));

      foreach ($rows as $r) {
        $email = strtolower(trim((string)$r['email']));
        if ($email === '') { logf("skip id={$r['id']} (empty email)"); continue; }

        [$ok, $code, $res] = be_tx_send($r);

        if ($ok) {
          $stmtMarkSent->execute([(int)$r['id']]);
          $stmtResetFail->execute([$email]);
          logf("SUCCESS id={$r['id']} email={$email}");
          $sent++; usleep($PACE_USLEEP);

        } else {
          $failed++;
          $bodyShow = is_array($res) ? json_encode($res, JSON_UNESCAPED_UNICODE) : (string)$res;
          $isTemporary = ($code === 0) || ($code == 408) || ($code == 429) || ($code >= 500);

          if ($isTemporary) {
            $stmtScheduleRt->execute([':sec' => $RETRY_DELAY_SEC, ':id' => (int)$r['id']]);
            logf("TEMP_FAIL id={$r['id']} code={$code} → retry_at +{$RETRY_DELAY_SEC}s");
          } else {
            $stmtUpFail->execute([$email, $code ?: null, mb_strimwidth($bodyShow, 0, 2000, '...', 'UTF-8')]);
            $stmtGetFail->execute([$email]);
            $failCount = (int)($stmtGetFail->fetchColumn() ?: 0);

            logf("PERM_FAIL id={$r['id']} email={$email} code={$code} fail_count={$failCount}");

            if ($failCount >= $FAIL_THRESHOLD) {
              $stmtQuaran->execute([$email]);
              logf("QUARANTINED id={$r['id']} email={$email} (fail_count={$failCount})");
            }
          }
        }

        if (time() - $start > $MAX_RUN_SECONDS) {
          logf("time limit reached. sent={$sent} failed={$failed}");
          break 2;
        }
      }

    } while (true);

    logf("job finished. total sent={$sent} failed={$failed}");
    return 0;

  } catch (Throwable $e) {
    logf("ERROR: ".$e->getMessage());
    return 1;
  } finally {
    global $lock;
    if (isset($lock) && is_resource($lock)) {
      flock($lock, LOCK_UN); fclose($lock);
    }
  }
}

/* ========================
   CLIなら自動実行
   ======================== */
if (PHP_SAPI === 'cli') {
  exit(run_br_tx_job());
}
