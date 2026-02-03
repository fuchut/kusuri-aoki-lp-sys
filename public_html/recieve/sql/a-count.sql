CREATE TABLE token_audit (
  token VARCHAR(200) NOT NULL,
  token_hash CHAR(64) NOT NULL,

  first_sent_at DATETIME NULL,
  last_sent_at DATETIME NULL,
  send_count INT UNSIGNED NOT NULL DEFAULT 0,

  last_status ENUM('success','fail') NULL,
  last_error VARCHAR(255) NULL,

  PRIMARY KEY (token_hash),
  KEY idx_last (last_sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
