<?php declare(strict_types=1);

/**
 * relation-brastengine/index.php
 * - テスト用ダッシュボード（トークン認証）
 * - 実行 / 隔離解除 / 送信済み解除（ID指定 or 全件）
 * - ログ閲覧（左 span-8）/ ログクリア（右 span-4, 既定はバックアップ保存しない）
 * - セキュリティ強化：POST限定・CSRFトークン・堅牢Cookie・CSP/Clickjacking対策・PDO硬化
 */

/////////////////////////////
// 早期セキュアヘッダ
/////////////////////////////
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline';");
header('Referrer-Policy: no-referrer');

/////////////////////////////
// セッション & CSRF
/////////////////////////////
session_start();
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf'],ENT_QUOTES,'UTF-8').'">';
}
function csrf_check(): void {
  if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
    http_response_code(400); exit('Bad Request (CSRF)');
  }
}
function require_post(): void {
  if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405); header('Allow: POST'); exit('Method Not Allowed');
  }
}

/////////////////////////////
// 設定
/////////////////////////////
$JOB_SCRIPT   = __DIR__ . '/br_tx_job.php';
$WORKDIR      = __DIR__ . '/tx_job_work';
$LOG_PATH     = $WORKDIR . '/job.log';
$LOG_TAIL_N   = 80;
$PAGE_TITLE   = 'Blastengine 配信ジョブ（テスト用ダッシュボード）';
$COOKIE_NAME  = 'run_token';
$RUN_TIMEOUT  = 240;
$TABLE        = 'entry';
$FAIL_TABLE   = 'delivery_failures';

/////////////////////////////
// setting.php
/////////////////////////////
require_once __DIR__ . '/../formapp/setting.php';

/////////////////////////////
// トークン（環境変数優先）
/////////////////////////////
$serverToken = (string)(getenv('BE_TEST_TOKEN') ?: '');
if ($serverToken === '' && defined('BE_TEST_TOKEN')) {
  $serverToken = (string)BE_TEST_TOKEN;
}
// 任意：テストトークン未設定なら停止
if ($serverToken === '') { http_response_code(503); exit('TEST TOKEN not configured'); }

/////////////////////////////
// ユーティリティ
/////////////////////////////
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function tail_lines(string $file, int $lines = 100): string {
  if (!is_file($file)) return '';
  $f = fopen($file, 'rb'); if (!$f) return '';
  $pos = -1; $buffer = ''; $linecnt = 0;
  $stat = fstat($f); $filesize = $stat['size'] ?? 0;
  if ($filesize === 0) { fclose($f); return ''; }
  while ($linecnt <= $lines && -$pos < $filesize) {
    fseek($f, $pos, SEEK_END);
    $c = fgetc($f);
    $buffer = $c . $buffer;
    if ($c === "\n") $linecnt++;
    $pos--;
  }
  fclose($f);
  return rtrim($buffer);
}

function db_connect(): ?PDO {
  try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4");
    return $pdo;
  } catch (Throwable $e) {
    return null;
  }
}

function get_stats(PDO $pdo, string $table, string $failTable): array {
  $stats = ['pending'=>0,'retry_wait'=>0,'quarantined'=>0,'failed_top'=>[]];
  $sql1 = "SELECT COUNT(*) FROM {$table}
           WHERE send_flg=0 AND COALESCE(quarantine_flg,0)=0
             AND (retry_at IS NULL OR retry_at<=NOW()) AND email<>''";
  $sql2 = "SELECT COUNT(*) FROM {$table}
           WHERE send_flg=0 AND COLESCE(quarantine_flg,0)=0
             AND retry_at IS NOT NULL AND retry_at>NOW()";
  $sql2 = str_replace('COLESCE', 'COALESCE', $sql2); // typo protection
  $sql3 = "SELECT COUNT(*) FROM {$table}
           WHERE send_flg=0 AND quarantine_flg=1";
  $sql4 = "SELECT email, fail_count, last_error_at, last_code
           FROM {$failTable} ORDER BY last_error_at DESC LIMIT 10";
  try {
    $stats['pending']     = (int)$pdo->query($sql1)->fetchColumn();
    $stats['retry_wait']  = (int)$pdo->query($sql2)->fetchColumn();
    $stats['quarantined'] = (int)$pdo->query($sql3)->fetchColumn();
    $stats['failed_top']  = $pdo->query($sql4)->fetchAll();
  } catch (Throwable $e) {}
  return $stats;
}

// 文字列から ID 群を抽出（改行 / カンマ / 空白区切り）→ 正の整数のみ
function parse_ids(string $text): array {
  $text  = str_replace(["\r\n", "\r"], "\n", $text);
  $parts = preg_split('/[,\s]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
  $ids   = [];
  foreach ($parts as $p) {
    if (preg_match('/^\d+$/', $p)) {
      $val = (int)$p; if ($val > 0) $ids[$val] = true;
    }
  }
  $out = array_keys($ids);
  sort($out, SORT_NUMERIC);
  return $out;
}

/////////////////////////////
// 認証
/////////////////////////////
$cookieToken   = $_COOKIE[$COOKIE_NAME] ?? '';
$hasValidToken = ($serverToken!=='' && $cookieToken!=='' && hash_equals($serverToken,$cookieToken));

/////////////////////////////
// アクション（POST限定）
/////////////////////////////
$action = $_POST['action'] ?? '';
$msg = $errMsg = '';

if ($action==='login') {
  require_post(); csrf_check();
  $input = (string)($_POST['token'] ?? '');
  if ($serverToken!=='' && $input!=='' && hash_equals($serverToken,$input)) {
    setcookie($COOKIE_NAME, $input, [
      'expires'  => time()+86400*7,
      'path'     => dirname($_SERVER['SCRIPT_NAME']) ?: '/',
      'secure'   => true,      // HTTPS必須
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
    header('Location: '.$_SERVER['PHP_SELF']); exit;
  }
  $errMsg = 'トークンが不正です。';
}

if ($action==='logout') {
  require_post(); csrf_check();
  setcookie($COOKIE_NAME, '', [
    'expires'  => time()-3600,
    'path'     => dirname($_SERVER['SCRIPT_NAME']) ?: '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  header('Location: '.$_SERVER['PHP_SELF']); exit;
}

/* ---------- 今すぐ実行 ---------- */
if ($action==='run' && $hasValidToken) {
  require_post(); csrf_check();
  @set_time_limit($RUN_TIMEOUT);
  header('Content-Type: text/html; charset=UTF-8'); ?>
<!doctype html><meta charset="utf-8">
<title><?=h($PAGE_TITLE)?> - 実行中</title>
<pre style="background:#000;color:#0f0;padding:12px;">
<?php
  @ini_set('output_buffering','off');
  while(ob_get_level()>0) ob_end_flush();
  @ob_implicit_flush(true);
  echo "[".date('Y-m-d H:i:s')."] running...\n";

  define('ALLOW_WEB_RUN', true);
  require_once $JOB_SCRIPT;

  if (function_exists('run_br_tx_job')) {
    $exit = run_br_tx_job();
    echo "[".date('Y-m-d H:i:s')."] finished exit={$exit}\n";
  } else {
    echo "ERROR: run_br_tx_job がありません\n";
  }

  if (is_file($LOG_PATH)) {
    echo "\n---job.log tail---\n";
    echo tail_lines($LOG_PATH, 50);
  }
?></pre>
<a href="<?=h($_SERVER['PHP_SELF'])?>">戻る</a>
<?php exit; }

/* ---------- 隔離解除 / 送信済み解除 / ログクリア ---------- */
if ($hasValidToken && in_array($action,['unquarantine','reset_sent','clear_log'],true)) {
  require_post(); csrf_check();
  if (!is_dir($WORKDIR)) { $old = umask(0002); mkdir($WORKDIR, 0775, true); umask($old); }

  if ($action==='clear_log') {
    $backup = ($_POST['backup'] ?? '0') === '1'; // 既定：保存しない
    if (is_file($LOG_PATH) && filesize($LOG_PATH)>0) {
      if ($backup) { $bak = $WORKDIR.'/job-'.date('Ymd-His').'.log'; @copy($LOG_PATH,$bak); }
      @file_put_contents($LOG_PATH,'');
      $msg = 'ログをクリアしました'.($backup?'（バックアップあり）':'');
    } else {
      @file_put_contents($LOG_PATH,'');
      $msg = 'ログは空でした（新規作成）';
    }
  } else {
    $pdo = db_connect();
    if (!$pdo) { $errMsg='DB接続に失敗'; }
    else {
      try {
        if ($action==='unquarantine') {
          $mode = $_POST['mode'] ?? 'all';
          if ($mode==='all') {
            $cnt=$pdo->exec("UPDATE {$TABLE} SET quarantine_flg=0 WHERE COALESCE(quarantine_flg,0)=1");
            $msg="隔離解除(全件)：{$cnt}件";
          } else {
            $ids = parse_ids((string)($_POST['ids'] ?? ''));
            if (!$ids) { $errMsg='IDを入力してください。'; }
            elseif (count($ids) > 1000) { $errMsg = 'IDは最大1000件までです。'; }
            else {
              foreach ($ids as $v) { if ($v < 1 || $v > 2147483647) { $errMsg='IDの範囲が不正です。'; break; } }
              if (!$errMsg) {
                $in = implode(',', array_fill(0,count($ids),'?'));
                $st = $pdo->prepare("UPDATE {$TABLE} SET quarantine_flg=0 WHERE id IN ({$in})");
                foreach ($ids as $k=>$v) $st->bindValue($k+1,(int)$v,PDO::PARAM_INT);
                $st->execute();
                $msg="隔離解除：".$st->rowCount()."件";
              }
            }
          }
        }
        if (!$errMsg && $action==='reset_sent') {
          $mode = $_POST['mode'] ?? 'all';
          if ($mode==='all') {
            $cnt=$pdo->exec("UPDATE {$TABLE} SET send_flg=0, retry_at=NULL WHERE send_flg=1");
            $msg="送信済み解除(全件)：{$cnt}件";
          } else {
            $ids = parse_ids((string)($_POST['ids'] ?? ''));
            if (!$ids) { $errMsg='IDを入力してください。'; }
            elseif (count($ids) > 1000) { $errMsg = 'IDは最大1000件までです。'; }
            else {
              foreach ($ids as $v) { if ($v < 1 || $v > 2147483647) { $errMsg='IDの範囲が不正です。'; break; } }
              if (!$errMsg) {
                $in = implode(',', array_fill(0,count($ids),'?'));
                $st = $pdo->prepare("UPDATE {$TABLE} SET send_flg=0, retry_at=NULL WHERE send_flg=1 AND id IN ({$in})");
                foreach ($ids as $k=>$v) $st->bindValue($k+1,(int)$v,PDO::PARAM_INT);
                $st->execute();
                $msg="送信済み解除：".$st->rowCount()."件";
              }
            }
          }
        }
      } catch (Throwable $e) {
        $errMsg='処理失敗：'.$e->getMessage();
      }
    }
  }
}

/////////////////////////////
// ダッシュボード HTML
/////////////////////////////
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html><meta charset="utf-8">
<title><?=h($PAGE_TITLE)?></title>
<style>
:root { --bg:#0b1021; --fg:#e6e6e6; --card:#111735; --muted:#9aa4b2; --accent:#0b63ff; --warn:#d99100; --danger:#d94141; }
*{box-sizing:border-box}
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif; background:var(--bg); color:var(--fg); margin:0; padding:24px;}
.wrap{max-width:1100px;margin:0 auto;}
.card{background:var(--card);padding:16px;border-radius:16px;margin-top:16px; box-shadow:0 1px 0 rgba(255,255,255,.06) inset;}
.btn{padding:10px 16px;border-radius:10px;background:var(--accent);color:#fff;border:none;cursor:pointer;font-weight:600;}
.btn.warn{background:var(--warn);} .btn.danger{background:var(--danger);} .btn.secondary{background:#2a3359;}
textarea,input,select{width:100%;padding:10px;border-radius:10px;background:#0b1021;color:#fff;border:1px solid rgba(255,255,255,.15);}
.row{display:grid;grid-template-columns:repeat(12,1fr);gap:14px;}
.span-6{grid-column:span 6;} .span-4{grid-column:span 4;} .span-8{grid-column:span 8;}
.muted{color:var(--muted);} pre{background:#000;color:#0f0;padding:10px;border-radius:12px;max-height:300px;overflow:auto;}
h1{margin:0 0 16px;font-size:22px;}
</style>

<div class="wrap">
<h1><?=h($PAGE_TITLE)?></h1>

<?php if(!$hasValidToken): ?>
  <div class="card" style="max-width:420px;margin:80px auto;">
    <h2 class="muted">トークン入力</h2>
    <?php if($errMsg): ?><p style="color:#f66;"><?=$errMsg?></p><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <input type="password" name="token" placeholder="Token" style="margin-top:10px;">
      <button class="btn" style="margin-top:12px;">ログイン</button>
    </form>
  </div>
<?php exit; endif; ?>

<?php
$pdo=db_connect();
$stats=$pdo?get_stats($pdo,$TABLE,$FAIL_TABLE):['pending'=>0,'retry_wait'=>0,'quarantined'=>0,'failed_top'=>[]];
$logTail = is_file($LOG_PATH)?tail_lines($LOG_PATH,$LOG_TAIL_N):'(なし)';
?>

<?php if($msg): ?><p style="background:#103;color:#6f6;padding:10px;border-radius:8px;"><?=$msg?></p><?php endif; ?>
<?php if($errMsg): ?><p style="background:#301;color:#f66;padding:10px;border-radius:8px;"><?=$errMsg?></p><?php endif; ?>

<div class="row">
  <div class="card span-4">
    <h3 class="muted">未送信</h3>
    <strong><?= number_format($stats['pending']) ?></strong>
    <p class="muted">send_flg=0 / 非隔離 / retry到来済み</p>
  </div>
  <div class="card span-4">
    <h3 class="muted">retry待ち</h3>
    <strong><?= number_format($stats['retry_wait']) ?></strong>
    <p class="muted">次回実行で再送予定</p>
  </div>
  <div class="card span-4">
    <h3 class="muted">隔離</h3>
    <strong><?= number_format($stats['quarantined']) ?></strong>
    <p class="muted">fail_count しきい値到達</p>
  </div>
</div>

<!-- 実行 -->
<div class="card">
  <form method="post" onsubmit="return confirm('実行しますか？');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="run">
    <button class="btn">今すぐ実行</button>
  </form>
  <form method="post" style="margin-top:8px;" onsubmit="return confirm('ログアウトしますか？');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="logout">
    <button class="btn secondary">ログアウト</button>
  </form>
</div>

<!-- 隔離解除 + 送信済み解除（横並び・ID指定対応） -->
<div class="row">
  <!-- 隔離解除 -->
  <div class="card span-6">
    <h3 class="muted">隔離解除</h3>
    <form method="post"
          onsubmit="return confirm(this.mode.value==='all'?'全件の隔離を解除します。よろしいですか？':'入力したIDの隔離を解除します。');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="unquarantine">
      <select id="mode-uq" name="mode" style="margin-top:8px"
              onchange="document.getElementById('uq-ids').style.display=(this.value==='ids'?'block':'none');">
        <option value="all" selected>全件（隔離解除）</option>
        <option value="ids">ID 指定</option>
      </select>
      <div id="uq-ids" style="margin-top:12px; display:none;">
        <label class="muted">ID（複数可：改行・空白・カンマ区切り / 数字のみ）</label>
        <textarea name="ids" placeholder="101&#10;102&#10;103"></textarea>
      </div>
      <button class="btn warn" style="margin-top:12px;">隔離解除を実行</button>
    </form>
    <p class="muted" style="margin-top:10px;">※ <code>entry.quarantine_flg</code> を 0 に戻します。</p>
  </div>

  <!-- 送信済み解除 -->
  <div class="card span-6">
    <h3 class="muted">送信済み解除</h3>
    <form method="post"
          onsubmit="return confirm(this.mode.value==='all'?'全件の送信フラグを解除します。よろしいですか？':'入力したIDの送信フラグを解除します。');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_sent">
      <select id="mode-rs" name="mode" style="margin-top:8px"
              onchange="document.getElementById('rs-ids').style.display=(this.value==='ids'?'block':'none');">
        <option value="all" selected>全件（送信済み解除）</option>
        <option value="ids">ID 指定</option>
      </select>
      <div id="rs-ids" style="margin-top:12px; display:none;">
        <label class="muted">ID（複数可：改行・空白・カンマ区切り / 数字のみ）</label>
        <textarea name="ids" placeholder="201, 202 203"></textarea>
      </div>
      <button class="btn danger" style="margin-top:12px;">送信済み解除を実行</button>
    </form>
    <p class="muted" style="margin-top:10px;">※ <code>entry.send_flg=0, retry_at=NULL</code> に戻します。</p>
  </div>
</div>

<!-- ログ表示（左 span-8） + ログクリア（右 span-4, 既定は保存しない） -->
<div class="row">
  <div class="card span-8">
    <h3 class="muted">job.log（末尾 <?= (int)$LOG_TAIL_N ?> 行）</h3>
    <pre><?=h($logTail)?></pre>
  </div>
  <div class="card span-4">
    <h3 class="muted">ログクリア</h3>
    <form method="post" onsubmit="return confirm('ログをクリアしますか？');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="clear_log">
      <label class="muted">バックアップを保存</label>
      <select name="backup" style="margin-top:8px;">
        <option value="0" selected>保存しない（即時クリア）</option>
        <option value="1">保存する（推奨）</option>
      </select>
      <button class="btn secondary" style="margin-top:10px;">ログをクリア</button>
      <p class="muted" style="margin-top:10px;">保存する場合、<code>tx_job_work/job-YYYYmmdd-His.log</code> にバックアップします。</p>
    </form>
  </div>
</div>

</div>

<!-- 初期化JS：default=all で ID欄を非表示にする -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  function toggle(selId, boxId) {
    var sel = document.getElementById(selId);
    var box = document.getElementById(boxId);
    if (!sel || !box) return;
    box.style.display = (sel.value === 'ids') ? 'block' : 'none';
  }
  toggle('mode-uq', 'uq-ids');
  toggle('mode-rs', 'rs-ids');

  var uq = document.getElementById('mode-uq');
  var rs = document.getElementById('mode-rs');
  if (uq) uq.addEventListener('change', function(){ toggle('mode-uq', 'uq-ids'); });
  if (rs) rs.addEventListener('change', function(){ toggle('mode-rs', 'rs-ids'); });
});
</script>
