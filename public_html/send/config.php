<?php
declare(strict_types=1);

const B_RECEIVE_URL = 'https://b.example.com/api/receive.php';
const SHARED_SECRET = 'CHANGE_ME_LONG_RANDOM_SECRET';

// B公開鍵（Bで生成したbox_public.b64をAにコピー）
const B_PUBLICKEY_B64_PATH = __DIR__ . '/box_public.b64';

// token制約（URL用。必要なら調整）
const TOKEN_MAX_LEN = 200;
const TOKEN_REGEX   = '/^[A-Za-z0-9_-]+$/';

function pdoA(): PDO {
  return new PDO(
    'mysql:host=localhost;dbname=YOURDB;charset=utf8mb4',
    'USER',
    'PASS',
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
}
