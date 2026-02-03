<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function assertValidToken(string $token): void {
  if ($token === '' || strlen($token) > TOKEN_MAX_LEN || !preg_match(TOKEN_REGEX, $token)) {
    throw new RuntimeException('invalid token');
  }
}
function tokenHash(string $token): string {
  return hash('sha256', $token);
}

function loadBPublicKey(): string {
  $b64 = trim((string)file_get_contents(B_PUBLICKEY_B64_PATH));
  $pub = base64_decode($b64, true);
  if ($pub === false || strlen($pub) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
    throw new RuntimeException('B public key invalid');
  }
  return $pub;
}

function encryptPayload(array $payload): string {
  $pub = loadBPublicKey();
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
  if ($json === false) throw new RuntimeException('payload json encode failed');
  $cipher = sodium_crypto_box_seal($json, $pub);
  return base64_encode($cipher);
}

/**
 * Outboxの重複防止用キー
 * - 同一tokenで同内容送信が短時間に多重実行されるのを抑える
 * - “更新で内容が変わる”のはOK（別idemになる）
 */
function idempotencyKeyForOutbox(array $payload): string {
  // 暗号化前の安定化JSONでhash（PIIはAに保存しないが、ここはメモリ上のみ）
  $p = $payload;
  ksort($p);
  $json = json_encode($p, JSON_UNESCAPED_UNICODE);
  return hash('sha256', $json ?: '');
}

function enqueueCipher(PDO $pdo, array $payload): void {
  $idem = idempotencyKeyForOutbox($payload);
  $cipherB64 = encryptPayload($payload);

  $sql = "INSERT INTO outbox_cipher
          (created_at, next_try_at, idempotency_key, ciphertext_b64)
          VALUES (NOW(), NOW(), :idem, :c)
          ON DUPLICATE KEY UPDATE ciphertext_b64 = ciphertext_b64";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':idem'=>$idem, ':c'=>$cipherB64]);
}

function signRequest(string $json, string $ts, string $nonce): string {
  return hash_hmac('sha256', $json . "\n" . $ts . "\n" . $nonce, SHARED_SECRET);
}

function postToB(string $json): array {
  $ts = (string)time();
  $nonce = bin2hex(random_bytes(16));
  $sig = signRequest($json, $ts, $nonce);

  $ch = curl_init(B_RECEIVE_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $json,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json; charset=utf-8',
      'X-TS: ' . $ts,
      'X-NONCE: ' . $nonce,
      'X-SIG: ' . $sig,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ]);

  $res  = curl_exec($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  return [$code, $res, $err];
}

function auditSuccess(PDO $pdo, string $token): void {
  $th = tokenHash($token);
  $sql = "INSERT INTO token_audit
            (token, token_hash, first_sent_at, last_sent_at, send_count, last_status)
          VALUES
            (:token, :th, NOW(), NOW(), 1, 'success')
          ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            last_sent_at = NOW(),
            send_count = send_count + 1,
            last_status = 'success',
            last_error = NULL";
  $pdo->prepare($sql)->execute([':token'=>$token, ':th'=>$th]);
}

function auditFail(PDO $pdo, string $token, string $err): void {
  $th = tokenHash($token);
  $sql = "INSERT INTO token_audit
            (token, token_hash, last_sent_at, send_count, last_status, last_error)
          VALUES
            (:token, :th, NOW(), 1, 'fail', :err)
          ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            last_sent_at = NOW(),
            send_count = send_count + 1,
            last_status = 'fail',
            last_error = :err";
  $pdo->prepare($sql)->execute([':token'=>$token, ':th'=>$th, ':err'=>mb_substr($err,0,255)]);
}

function trySendPending(PDO $pdo, int $limit = 100): void {
  // 多重起動ロック
  $pdo->exec("SELECT GET_LOCK('outbox_cipher_send_lock', 5)");

  $stmt = $pdo->prepare("
    SELECT * FROM outbox_cipher
    WHERE status='pending' AND next_try_at <= NOW()
    ORDER BY next_try_at ASC
    LIMIT :lim
    FOR UPDATE
  ");

  $pdo->beginTransaction();
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();
  $pdo->commit();

  foreach ($rows as $r) {
    $id = (int)$r['id'];
    $idem = (string)$r['idempotency_key'];
    $cipherB64 = (string)$r['ciphertext_b64'];

    // Outboxからはtokenを取り出せない（暗号化のため）
    // → 監査(token_audit)は enqueue時/フォーム処理時に更新する（推奨）
    $body = json_encode([
      'idempotency_key' => $idem,
      'ciphertext'      => $cipherB64,
    ], JSON_UNESCAPED_UNICODE);

    [$code, $res, $err] = postToB($body);

    if ($code >= 200 && $code < 300) {
      $pdo->prepare("UPDATE outbox_cipher SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=:id")
          ->execute([':id'=>$id]);
      continue;
    }

    $try = (int)$r['try_count'] + 1;
    $delay = match (true) {
      $try <= 1 => 60,
      $try == 2 => 300,
      $try == 3 => 1800,
      $try == 4 => 7200,
      default   => 21600,
    };
    $newStatus = ($try >= 20) ? 'dead' : 'pending';
    $msg = $err ?: ("HTTP " . $code);

    $pdo->prepare("
      UPDATE outbox_cipher
      SET try_count=:try,
          status=:st,
          next_try_at=DATE_ADD(NOW(), INTERVAL :d SECOND),
          last_error=:err
      WHERE id=:id
    ")->execute([
      ':try'=>$try,
      ':st'=>$newStatus,
      ':d'=>$delay,
      ':err'=>mb_substr($msg,0,255),
      ':id'=>$id,
    ]);
  }

  $pdo->exec("DO RELEASE_LOCK('outbox_cipher_send_lock')");
}
