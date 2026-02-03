<?php
class DB {
  private PDO $pdo;

  public function __construct($host, $port, $dbname, $user, $password) {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $this->pdo = new PDO($dsn, $user, $password);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  public function getPdo(): PDO {
    return $this->pdo;
  }

  // A案：成功送信回数だけをカウント（失敗は数えない）
  public function recordAttempt(string $token): void {
    $th = hash('sha256', $token);

    $sql = "CREATE TABLE IF NOT EXISTS token_audit (
              token_hash CHAR(64) NOT NULL,
              token VARCHAR(255) NOT NULL,
              first_sent_at DATETIME NULL,
              last_sent_at DATETIME NULL,
              send_count INT UNSIGNED NOT NULL DEFAULT 0,
              last_status VARCHAR(32) NULL,
              last_error VARCHAR(255) NULL,
              PRIMARY KEY (token_hash),
              KEY idx_last_sent_at (last_sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $this->pdo->exec($sql);

    $sql2 = "INSERT INTO token_audit
              (token_hash, token, first_sent_at, last_sent_at, send_count, last_status)
            VALUES
              (:th, :token, NOW(), NOW(), 1, 'success')
            ON DUPLICATE KEY UPDATE
              token = VALUES(token),
              first_sent_at = COALESCE(first_sent_at, NOW()),
              last_sent_at = NOW(),
              send_count = send_count + 1,
              last_status = 'success',
              last_error = NULL";
    $st = $this->pdo->prepare($sql2);
    $st->execute([':th'=>$th, ':token'=>$token]);
  }
}
