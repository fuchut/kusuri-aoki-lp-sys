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

    // ユニークURL発行
    $publicToken = random_bytes(16);

    $sql = "
      INSERT INTO entry (
        member_id,
        present,
        email,
        public_token,
        updated_at,
        created_at
      ) VALUES (
        :member_id,
        :present,
        :email,
        :public_token,
        NOW(),
        NOW()
      )
      ON DUPLICATE KEY UPDATE
        present        = VALUES(present),
        email          = VALUES(email),
        send_flg       = 0,
        quarantine_flg = 0,
        retry_at       = NULL,
        updated_at     = NOW()
    ";

    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([
      ':member_id' => $memberId,
      ':present'   => $present,
      ':email'     => $email,
      ':public_token'  => $publicToken,
    ]);
  }

  private function issuePublicTokenAndUrl(string $baseUrl, int $maxTries = 5): array
  {
      for ($i = 0; $i < $maxTries; $i++) {
          $bin  = random_bytes(16);        // 16B = 128bit
          $hex  = bin2hex($bin);           // URL用表示
          $url  = rtrim($baseUrl, '/') . '/' . $hex;
          // DB保存は VARBINARY(16) = $bin、表示や差し込みは $hex/$url を使う
          return ['bin' => $bin, 'hex' => $hex, 'url' => $url];
      }
      throw new RuntimeException('public_token の発行に失敗しました');
  }

  /**
   * トークンHEXから行を取得（/r/<hex> の受け側などで利用）
   * @param string $hex 32桁の16進
   * @return array|null
   */
  public function getEntryByPublicTokenHex(string $hex): ?array
  {
      if (!preg_match('/^[a-f0-9]{32}$/', $hex)) {
          throw new InvalidArgumentException('invalid token hex');
      }
      $bin = hex2bin($hex);
      $st = $this->pdo->prepare("SELECT id, member_id, present, email, public_token FROM entry WHERE public_token = :t LIMIT 1");
      $st->execute([':t' => $bin]);
      $row = $st->fetch();
      return $row ?: null;
  }


  /**
   * token(32桁hex) から entry を1件取得する
   *
   * @param PDO    $pdo
   * @param string $token 32桁の16進数文字列
   * @return array|null   見つかれば連想配列、なければ null
   */
  public function findEntryByToken(string $token): ?array
  {
      $sql = "
          SELECT
              member_id,
              present,
              email,
              updated_at,
              created_at
          FROM entry
          WHERE public_token = UNHEX(:token)
          LIMIT 1
      ";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([':token' => $token]);

      $row = $stmt->fetch();
      if ($row === false) {
          return null;
      }

      return $row;
  }

}