<?php
declare(strict_types=1);

final class OutboxSender
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS outbox_cipher (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at    DATETIME NULL,

            status ENUM('pending','sent','fail') NOT NULL DEFAULT 'pending',
            try_count INT UNSIGNED NOT NULL DEFAULT 0,
            revision_count INT UNSIGNED NOT NULL DEFAULT 0,

            next_try_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_error  VARCHAR(255) NULL,

            token_hash CHAR(64) NOT NULL,
            token      VARCHAR(255) NULL,

            idempotency_key CHAR(64) NOT NULL,
            ciphertext_b64  MEDIUMTEXT NOT NULL,

            UNIQUE KEY uq_tokenhash (token_hash),
            KEY idx_status_next (status, next_try_at),
            KEY idx_token_status (token(32), status),
            KEY idx_idem (idempotency_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }


    /** payloadを暗号化してOutboxに積む（復号不可） */
    public function enqueue(array $payload): void
    {
        $cipherB64 = $this->sealPayload($payload);

        $token    = (string)($payload['token'] ?? '');
        $group    = (string)($payload['group'] ?? '');
        $memberId = (string)($payload['member_id'] ?? '');
        $email    = (string)($payload['email'] ?? '');

        // ガード（運用事故防止）
        if ($token === '' || $group === '' || $memberId === '' || $email === '') {
            throw new RuntimeException('enqueue: missing required fields');
        }

        $tokenHash = hash('sha256', $token);

        // 毎回ユニーク（B側 audit / リプレイ追跡用途）
        $idem = bin2hex(random_bytes(32));

        $sql = "
            INSERT INTO outbox_cipher (
                created_at,
                sent_at,
                status,
                try_count,
                revision_count,
                next_try_at,
                last_error,
                token_hash,
                token,
                idempotency_key,
                ciphertext_b64
            ) VALUES (
                NOW(),
                NULL,
                'pending',
                0,
                0,
                NOW(),
                NULL,
                :th,
                :token,
                :idem,
                :c
            )
            ON DUPLICATE KEY UPDATE
                -- 最新内容で上書き
                token           = VALUES(token),
                idempotency_key = VALUES(idempotency_key),
                ciphertext_b64  = VALUES(ciphertext_b64),

                -- 送信再開（毎回Bに送る）
                status      = 'pending',
                next_try_at = NOW(),
                sent_at     = NULL,
                last_error  = NULL,

                -- 内容更新回数（try_countとは別）
                revision_count = revision_count + 1
        ";

        $this->pdo->prepare($sql)->execute([
            ':th'    => $tokenHash,
            ':token' => $token,
            ':idem'  => $idem,
            ':c'     => $cipherB64,
        ]);
    }



    /** pendingをBへ送る（フォーム送信後 or cronで実行） */
    public function trySend(int $limit = 50): void
    {
        $lock = defined('OUTBOX_LOCK_NAME') ? (string)OUTBOX_LOCK_NAME : 'outbox_send_lock';
        $got  = $this->pdo->query("SELECT GET_LOCK(".$this->pdo->quote($lock).", 1) AS got")->fetch();
        if (empty($got) || (int)$got['got'] !== 1) return;

        try {
            // ① 送信対象を拾う
            $st = $this->pdo->prepare("
                SELECT id, idempotency_key, ciphertext_b64, try_count
                FROM outbox_cipher
                WHERE status='pending' AND next_try_at <= NOW()
                ORDER BY id ASC
                LIMIT :lim
            ");
            $st->bindValue(':lim', $limit, PDO::PARAM_INT);
            $st->execute();
            $list = $st->fetchAll();

            foreach ($list as $r) {
                $id = (int)$r['id'];

                // ② ここで“掴む” (他プロセスが拾い直さないように next_try_at を未来へ)
                //    例：2分ロック（送信が詰まっても次のプロセスがすぐ拾わない）
                $grab = $this->pdo->prepare("
                    UPDATE outbox_cipher
                    SET next_try_at = DATE_ADD(NOW(), INTERVAL 2 MINUTE)
                    WHERE id = :id AND status='pending' AND next_try_at <= NOW()
                ");
                $grab->execute([':id' => $id]);

                if ($grab->rowCount() !== 1) {
                    // すでに他が掴んだ or 状態が変わった
                    continue;
                }

                // ③ 送信
                $this->sendOne(
                    $id,
                    (string)$r['idempotency_key'],
                    (string)$r['ciphertext_b64'],
                    (int)$r['try_count']
                );
            }
        } finally {
            $this->pdo->query("DO RELEASE_LOCK(".$this->pdo->quote($lock).")");
        }
    }

    private function sendOne(int $id, string $idem, string $cipherB64, int $tryCount): void
    {
        try {
        $body = json_encode([
            'idempotency_key' => $idem,
            'ciphertext'      => $cipherB64,
        ], JSON_UNESCAPED_UNICODE);

        if ($body === false) {
            $this->markFail($id, $tryCount, 'json_encode_failed');
            return;
        }

        // ===== ① 先に作る =====
        $ts    = (string)time();
        $nonce = bin2hex(random_bytes(16));

        // ===== ② 送信開始ログ =====
        $this->log('INFO', 'send start', [
            'outbox_id'  => $id,
            'idem'       => substr($idem, 0, 12),
            'url'        => (string)B_RECEIVE_URL,
            'try'        => $tryCount + 1,
            'ts'         => $ts,
            'nonce'      => substr($nonce, 0, 12),
            'body_len'   => strlen($body),
            'cipher_len' => strlen($cipherB64),
        ]);

        // ===== ③ 署名 =====
        $sig = hash_hmac(
            'sha256',
            $body . "\n" . $ts . "\n" . $nonce,
            (string)SHARED_SECRET
        );

        // ===== ④ HTTP送信 =====
        $respCode = 0;
        $respBody = null;
        $ok = $this->httpPost(
            (string)B_RECEIVE_URL,
            $body,
            [
                'Content-Type: application/json',
                'X-TS: ' . $ts,
                'X-NONCE: ' . $nonce,
                'X-SIG: ' . $sig,
                'Authorization: Basic ' . base64_encode((string)B_BASIC_USER . ':' . (string)B_BASIC_PASS),
            ],
            $respCode,
            $respBody
        );

        // ===== ⑤ 結果ログ =====
        $this->log($ok ? 'INFO' : 'ERROR', 'send done', [
            'outbox_id' => $id,
            'idem'      => substr($idem, 0, 12),
            'http_ok'   => $ok ? '1' : '0',
            'code'      => $respCode,
            'resp'      => is_string($respBody) ? mb_substr($respBody, 0, 200) : '',
        ]);

        // ===== ⑥ 成否反映 =====
        if ($ok && $respCode >= 200 && $respCode < 300) {
            $this->pdo
                ->prepare("UPDATE outbox_cipher SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=:id")
                ->execute([':id' => $id]);
            return;
        }

        $msg = 'http_fail code=' . $respCode;
        if (is_string($respBody) && $respBody !== '') {
            $msg .= ' body=' . mb_substr($respBody, 0, 120);
        }

        $this->markFail($id, $tryCount, $msg);

        } catch (Throwable $e) {
            $this->log('ERROR', 'send fatal', [
                'outbox_id' => $id,
                'idem'      => substr($idem, 0, 12),
                'err'       => $e->getMessage(),
                'where'     => $e->getFile().':'.$e->getLine(),
            ]);

            // 失敗としてOutbox更新（漏れ防止）
            $this->markFail($id, $tryCount, 'fatal: '.$e->getMessage());
        }

    }

    private function markFail(int $id, int $tryCount, string $error): void
    {
        $nextTry = min($tryCount + 1, 10);
        $delayMin = [1,2,5,10,30,60,120,240,480,720][$nextTry - 1] ?? 1440;

        $maxTry = defined('OUTBOX_MAX_TRY') ? (int)OUTBOX_MAX_TRY : 20;
        $newCount = $tryCount + 1;
        $newStatus = ($newCount >= $maxTry) ? 'fail' : 'pending';

        $sql = "UPDATE outbox_cipher
                SET try_count=:tc,
                    status=:st,
                    next_try_at=DATE_ADD(NOW(), INTERVAL :mins MINUTE),
                    last_error=:err
                WHERE id=:id";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':tc'   => $newCount,
            ':st'   => $newStatus,
            ':mins' => $delayMin,
            ':err'  => mb_substr($error, 0, 255),
            ':id'   => $id,
        ]);
    }

    private function httpPost(string $url, string $body, array $headers, int &$code, ?string &$respBody): bool
    {
        $ch = curl_init($url);
        if ($ch === false) {
            $code = 0; $respBody = null;
            $this->log('ERROR', 'curl_init failed', ['url'=>$url]);
            return false;
        }

        $timeout = defined('OUTBOX_HTTP_TIMEOUT') ? (int)OUTBOX_HTTP_TIMEOUT : 10;

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);

        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $respBody = is_string($resp) ? $resp : null;

        if ($resp === false) {
            $this->log('ERROR', 'curl_exec failed', [
            'url'=>$url, 'errno'=>$errno, 'err'=>$err, 'code'=>$code
            ]);
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return true;
    }

    private function sealPayload(array $payload): string
    {
        if (!function_exists('sodium_crypto_box_seal')) {
            throw new RuntimeException('sodium not available');
        }
        $pk = $this->loadBPublicKey();

        $plain = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($plain === false) {
            throw new RuntimeException('payload json encode failed');
        }

        $cipher = sodium_crypto_box_seal($plain, $pk);
        return base64_encode($cipher);
    }

    private function loadBPublicKey(): string
    {
        if (!defined('B_PUBLICKEY_B64_PATH') || (string)B_PUBLICKEY_B64_PATH === '') {
            throw new RuntimeException('B_PUBLICKEY_B64_PATH not defined');
        }
        $b64 = @file_get_contents((string)B_PUBLICKEY_B64_PATH);
        if ($b64 === false) {
            throw new RuntimeException('public key not readable: ' . (string)B_PUBLICKEY_B64_PATH);
        }
        $pk = base64_decode(trim($b64), true);
        if ($pk === false || strlen($pk) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new RuntimeException('public key invalid');
        }
        return $pk;
    }

    private function log(string $level, string $msg, array $ctx = []): void
    {
        $dir = defined('OUTBOX_LOG_DIR') ? (string)OUTBOX_LOG_DIR : '';
        $file = defined('OUTBOX_LOG_FILE') ? (string)OUTBOX_LOG_FILE : '';

        $line = date('Y-m-d H:i:s') . " [$level] " . $msg;

        if ($ctx) {
            // 個人情報を入れない（tokenは出さない）
            $safe = [];
            foreach ($ctx as $k => $v) {
                $safe[$k] = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $line .= ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE);
        }
        $line .= "\n";

        if ($dir && !is_dir($dir)) @mkdir($dir, 0755, true);
        if ($file) {
            @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }

    private function shortHash(string $s): string
    {
        return substr(hash('sha256', $s), 0, 12);
    }

}
