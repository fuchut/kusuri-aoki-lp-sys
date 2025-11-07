<?php

/*
 * DB class for handling database operations.
 * 
 * | 要素          | 処理                      | 備考 |
 * | ----------- | --------------------------------- | -- |
 * | **新規登録**    | `send_flg=0` → 即時配信対象             | OK |
 * | **上書き更新**   | `send_flg` を0に戻す → 再送対象           | OK |
 * | **エラー再送**   | `retry_at` により自動再試行               | OK |
 * | **恒久エラー隔離** | `quarantine_flg=1` → スキップ         | OK |
 * | **同時実行防止**  | `.lock` により排他制御済み                 | OK |
 * | **レート制限**   | `$PACE_USLEEP` によりBlastengine制限回避 | OK |

*/

class DB {
  private $pdo;

  public function __construct($host, $port, $dbname, $user, $password) {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $this->pdo = new PDO($dsn, $user, $password);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function insertEntry($data) {
    $memberId = trim((string)$data['data']['member_id']);
    $email    = strtolower(trim((string)$data['data']['email']));
    $present  = trim((string)$data['data']['present']);

    $sql = "
      INSERT INTO entry (member_id, present, email, send_flg, quarantine_flg, retry_at, updated_at, created_at)
      VALUES (:member_id, :present, :email, 0, 0, NULL, NOW(), NOW())
      ON DUPLICATE KEY UPDATE
        present         = VALUES(present),
        email           = VALUES(email),
        send_flg        = 0,
        quarantine_flg  = 0,
        retry_at        = NULL,
        updated_at      = NOW()
    ";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
      ':member_id' => $memberId,
      ':present'   => $present,
      ':email'     => $email,
    ]);
  }

}