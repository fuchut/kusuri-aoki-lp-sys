<?php
declare(strict_types=1);

require_once __DIR__ . '/setting.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/**
 * B受信API（最終版）
 * - Aからの暗号文(sealed box)を受け取って復号
 * - token_hash(sha256(token)) をPKとして UPSERT（更新あり対応）
 * - HMAC署名 + timestamp/nonceで改ざん＆リプレイ対策
 * - ingest_audit は idem を UNIQUE で保持するが、重複でも処理は続行する
 */

// ===== ユーティリティ =====
function jexit(int $code, array $payload): void {
  // ここで必ずログ化（個人情報は含まれない）
  rlog(($code >= 500) ? 'ERROR' : 'WARN', 'jexit', [
    'code'  => $code,
    'error' => $payload['error'] ?? '',
    'status'=> $payload['status'] ?? '',
  ]);

  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function rlog(string $level, string $msg, array $ctx = []): void {
  $line = date('Y-m-d H:i:s') . " [$level] " . $msg;
  if ($ctx) $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE);
  $line .= "\n";

  $file = defined('RECEIVE_LOG_FILE') ? (string)RECEIVE_LOG_FILE : '';
  if ($file) {
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
      error_log("receive log mkdir failed: $dir");
      error_log($line);
      return;
    }
    if (file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
      error_log("receive log write failed: $file");
      error_log($line);
      return;
    }
    return;
  }
  error_log($line);
}

rlog('INFO', 'boot', [
  'ip'  => $_SERVER['REMOTE_ADDR'] ?? '',
  'uri' => $_SERVER['REQUEST_URI'] ?? '',
]);

rlog('INFO', 'request', [
  'method' => $_SERVER['REQUEST_METHOD'] ?? '',
  'ct'     => $_SERVER['CONTENT_TYPE'] ?? '',
  'len'    => $_SERVER['CONTENT_LENGTH'] ?? '',
  'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
  'xff'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
  'ua'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? ''));
if ($method !== 'POST') {
  jexit(405, ['ok'=>false,'error'=>'method_not_allowed']);
}

// ===== 署名ヘッダ =====
$raw   = file_get_contents('php://input') ?: '';
$ts    = (string)($_SERVER['HTTP_X_TS'] ?? '');
$nonce = (string)($_SERVER['HTTP_X_NONCE'] ?? '');
$sig   = (string)($_SERVER['HTTP_X_SIG'] ?? '');

if ($raw === '' || $ts === '' || $nonce === '' || $sig === '') jexit(400, ['ok'=>false,'error'=>'missing_headers']);
if (!ctype_digit($ts)) jexit(400, ['ok'=>false,'error'=>'bad_ts']);
if (abs(time() - (int)$ts) > (int)MAX_SKEW_SEC) jexit(401, ['ok'=>false,'error'=>'ts_out_of_range']);

$base = $raw . "\n" . $ts . "\n" . $nonce;
$calc = hash_hmac('sha256', $base, (string)SHARED_SECRET);
if (!hash_equals($calc, $sig)) {
  rlog('WARN', 'bad_signature', [
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'ts' => $ts,
    'nonce' => substr($nonce, 0, 12),
    'calc' => substr($calc, 0, 12),
    'sig'  => substr($sig, 0, 12),
  ]);
  jexit(401, ['ok'=>false,'error'=>'bad_signature']);
}

rlog('INFO', 'recv start', [
  'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
  'len'   => strlen($raw),
  'ts'    => $ts,
  'nonce' => substr($nonce, 0, 12),
]);

// ===== JSON =====
$body = json_decode($raw, true);
if (!is_array($body)) jexit(400, ['ok'=>false,'error'=>'invalid_json']);

$idempotency = (string)($body['idempotency_key'] ?? '');
$cipherB64   = (string)($body['ciphertext'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/', $idempotency) || $cipherB64 === '') jexit(422, ['ok'=>false,'error'=>'invalid_payload']);

$cipher = base64_decode($cipherB64, true);
if ($cipher === false) jexit(422, ['ok'=>false,'error'=>'bad_cipher']);

// ===== DB（ingest_auditは「重複でも続行」）=====
try {
  $dsn = 'mysql:host='.(string)DB_HOST.';port='.(string)DB_PORT.';dbname='.(string)DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, (string)DB_USER, (string)DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS ingest_audit (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      idempotency_key CHAR(64) NOT NULL,
      status ENUM('ok','reject','error') NOT NULL,
      message VARCHAR(255) NULL,
      UNIQUE KEY uq_idem (idempotency_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // ★ここが修正点：duplicateでも終了しない
  $st = $pdo->prepare("
    INSERT INTO ingest_audit (idempotency_key, status, message)
    VALUES (:idem,'ok','accepted')
    ON DUPLICATE KEY UPDATE
      received_at = CURRENT_TIMESTAMP,
      status = 'ok',
      message = 'accepted'
  ");
  $st->execute([':idem' => $idempotency]);

} catch (Throwable $e) {
  error_log('db init failed: '.$e->getMessage());
  rlog('ERROR', 'db_init_failed', ['msg' => $e->getMessage()]);
  jexit(500, ['ok'=>false,'error'=>'db_init_failed']);
}

// ===== 復号 =====
$secB64 = @file_get_contents((string)SECRET_KEY_PATH);
$sec = $secB64 ? base64_decode(trim($secB64), true) : false;
if ($sec === false || strlen($sec) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) jexit(500, ['ok'=>false,'error'=>'secret_key_invalid']);

$pub = sodium_crypto_box_publickey_from_secretkey($sec);
$keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($sec, $pub);

$plain = sodium_crypto_box_seal_open($cipher, $keypair);
if ($plain === false) jexit(422, ['ok'=>false,'error'=>'decrypt_failed']);

$payload = json_decode($plain, true);
if (!is_array($payload)) jexit(422, ['ok'=>false,'error'=>'payload_json_invalid']);

// ===== 必須 =====
$token = (string)($payload['token'] ?? '');
if ($token === '' || strlen($token) > 200) jexit(422, ['ok'=>false,'error'=>'invalid_token']);
$tokenHash = hash('sha256', $token);

$group = trim((string)($payload['group'] ?? ''));
if ($group === '' || strlen($group) > 255) jexit(422, ['ok'=>false,'error'=>'invalid_group']);

$memberId = trim((string)($payload['member_id'] ?? ''));
$email    = trim((string)($payload['email'] ?? ''));
if ($memberId === '' || $email === '') jexit(422, ['ok'=>false,'error'=>'missing_fields']);

rlog('INFO', 'before_entries', [
  'idem' => substr($idempotency, 0, 12),
  'th'   => substr($tokenHash, 0, 12),
  'group'=> $group,
]);

// ===== entries =====
try {
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS entries (
      token_hash CHAR(64) NOT NULL,
      token      VARCHAR(255) NOT NULL,
      group_name VARCHAR(255) NOT NULL,

      member_id  VARCHAR(64)  NULL,
      email      VARCHAR(255) NULL,
      last_name  VARCHAR(255) NULL,
      first_name VARCHAR(255) NULL,
      tel        VARCHAR(64)  NULL,
      zip        VARCHAR(64)  NULL,
      address    VARCHAR(255) NULL,
      present    VARCHAR(255) NULL,

      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

      PRIMARY KEY (token_hash),
      KEY idx_group_updated (group_name, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $sql = "INSERT INTO entries
    (token_hash, token, group_name, member_id, email, last_name, first_name, tel, zip, address, present)
    VALUES
    (:th, :token, :g, :m, :e, :ln, :fn, :t, :z, :a, :p)
    ON DUPLICATE KEY UPDATE
      token      = VALUES(token),
      group_name = VALUES(group_name),
      member_id  = VALUES(member_id),
      email      = VALUES(email),
      last_name  = VALUES(last_name),
      first_name = VALUES(first_name),
      tel        = VALUES(tel),
      zip        = VALUES(zip),
      address    = VALUES(address),
      present    = VALUES(present)";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([
    ':th'    => $tokenHash,
    ':token' => $token,
    ':g'     => $group,
    ':m'     => $memberId,
    ':e'     => $email,
    ':ln'    => (string)($payload['last_name'] ?? ''),
    ':fn'    => (string)($payload['first_name'] ?? ''),
    ':t'     => (string)($payload['tel'] ?? ''),
    ':z'     => (string)($payload['zip'] ?? ''),
    ':a'     => (string)($payload['address'] ?? ''),
    ':p'     => (string)($payload['present'] ?? ''),
  ]);

  rlog('INFO', 'saved', [
    'th' => substr($tokenHash, 0, 12),
    'group' => $group,
  ]);

  jexit(200, ['ok'=>true, 'status'=>'saved']);

} catch (Throwable $e) {
  error_log('upsert failed: '.$e->getMessage());
  rlog('ERROR', 'upsert failed', ['msg' => $e->getMessage()]);
  jexit(500, ['ok'=>false,'error'=>'db_error']);
}
