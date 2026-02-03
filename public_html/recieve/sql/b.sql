CREATE TABLE entries (
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