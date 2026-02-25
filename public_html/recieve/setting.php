<?php
// =====================================
// DB設定
// =====================================
define('DB_NAME', 'mydatabase');
define('DB_USER', 'myuser');
define('DB_PASSWORD', 'mypassword');
define('DB_HOST', 'db');
define('DB_PORT', '3306');

// =====================================
// B受信API設定
// =====================================
define('SHARED_SECRET', 'db1c3a6411eaff31461b22a5b26ae1cabd3974048c54a4b6e66115ae6bfad838');
define('SECRET_KEY_PATH', '/home/USER/secure/box_secret.b64'); // public_html外
define('MAX_SKEW_SEC', 300); // 5分

define('RECEIVE_LOG_FILE', __DIR__ . '/logs/receive.log');
define('CSV_RUN_KEY', '9eA7f2KpXQm4ZcD8JYbW0VnS1LrH6M5aE3TUFo');


// XLSX 出力先（public_html外推奨。書き込み権限必要）
define('XLSX_DIR', __DIR__ . '/xlsx');   // 例：/home/USER/secure/xlsx でもOK
define('XLSX_RUN_KEY', '7f3c9e8a4b1d2c6f9a0e5b8d3c1f7a2e9d4c6b8f1a3e5d7c9b2a4f6e8c0d1a3');

// composer autoload
define('COMPOSER_AUTOLOAD', __DIR__ . '/../../vendor/autoload.php');