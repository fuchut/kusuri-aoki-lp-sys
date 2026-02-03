CREATE TABLE outbox_cipher (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,

  status ENUM('pending','sent','dead') NOT NULL DEFAULT 'pending',
  try_count INT UNSIGNED NOT NULL DEFAULT 0,
  next_try_at DATETIME NOT NULL,
  last_error VARCHAR(255) NULL,

  -- 追跡用（再送・照合）
  token_hash CHAR(64) NULL,
  token      VARCHAR(255) NULL,

  -- 同一イベントの重複登録防止（仕様に合わせたidem）
  idempotency_key CHAR(64) NOT NULL,

  -- 暗号文（sealed box）
  ciphertext_b64 MEDIUMTEXT NOT NULL,

  UNIQUE KEY uq_idem (idempotency_key),
  KEY idx_status_next (status, next_try_at),

  -- よく使う検索（tokenで追える）
  KEY idx_tokenhash_status (token_hash, status),
  KEY idx_token_status (token(32), status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
