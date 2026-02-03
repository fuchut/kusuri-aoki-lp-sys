<?php

date_default_timezone_set('Asia/Tokyo');

// 送信先
$param['from'] = "noreply@cp2025-kusuri-aoki.com";
$param['from_name'] = "クスリのアオキ「40周年大感謝キャンペーン」事務局";
$param['to'] = "fuchu@works-kanazawa.com"; // 複数はカンマ( , )でつなげる

// メールタイトル
$param['subject'] = array(
        'admin' 	=> '当選者情報の登録がありました',
        'user'		=> 'クスリのアオキ40周年大感謝キャンペーン 当選者情報受付',
);

$param['csv'] = dirname(__FILE__).'/../../../job/tousen/tousen.csv';
$param['xlsx'] = dirname(__FILE__).'/../../../job/tousen/tousen.xlsx';
        
$param['autores'] = true;

// 処理する項目のname
// 処理する項目のname
$param['forms'] = array(
        'member_id',
        'last_name',
        'first_name',
        'email',
        'tel',        
        'zip',
        'address',
        'token',
);

// type, autocomplete メモ
// 'name'  => array('text', 'name'),
// 'name01'  => array('text', 'family-name'),
// 'name02'  => array('text', 'given-name'),
// 'tel'   => array('tel', 'tel'),
// 'email' => array('email', 'email'),
// 'url'   => array('url', 'off'),
// 'zip'   => array('text', 'postal-code'),
// 'pref'  => array('text', 'address-level1'),
// 'city'  => array('text', 'address-level2'),
// 'address'  => array('text', 'address-line1'),
// 'company'  => array('text', 'organization'),

// テンプレートの指定
$param['tpl_path'] = dirname(__FILE__).'/tpl/';
$param['tpl'] = array(
        'form' 		=> "form.tpl",
        'confirm' 	=> "confirm.tpl",
        'complete' 	=> "complete.tpl",
        'mail'		=> "mail.tpl",
        'adminmail'	=> "adminmail.tpl",
);

/*****************************************
// radio
$param['xxxxxxxxxxxxxxxxxx'] = array(

);
$param['xxxxxxxxxxxxxxxxxx_type'] = 'radio';

// checkbox
$param['xxxxxxxxxxxxxxxxxx'] = array(
);
$param['xxxxxxxxxxxxxxxxxx_type'] = 'checkbox';
$param['xxxxxxxxxxxxxxxxxx_max'] = -1;

*****************************************/

// =====================================
// Group設定
// =====================================
define('APP_GROUP', 'G2');

// =====================================
// DB設定
// =====================================
define('DB_NAME', 'mydatabase');
define('DB_USER', 'myuser');
define('DB_PASSWORD', 'mypassword');
define('DB_HOST', 'db');
define('DB_PORT', '3306');

// =====================================
// サーバー連携設定
// =====================================
// ===== B連携先 =====
define('B_RECEIVE_URL', 'https://aoki-cp2025.wk-demo.com/recieve/recieve.php'); // Bの受信API
define('SHARED_SECRET', 'db1c3a6411eaff31461b22a5b26ae1cabd3974048c54a4b6e66115ae6bfad838');         // A/B同一にする

// B公開鍵（base64ファイル）
// Aサーバー上の配置パス（public_html外推奨でもOK。読める場所ならOK）
define('B_PUBLICKEY_B64_PATH', dirname(__FILE__).'/keys/box_public.b64');

// ベーシック認証
define('B_BASIC_USER', 'works');
define('B_BASIC_PASS', 'pwworks');

// ===== Outbox動作 =====
define('OUTBOX_LOCK_NAME', 'outbox_cipher_send_lock');
define('OUTBOX_SEND_LIMIT_DEFAULT', 100);
define('OUTBOX_HTTP_TIMEOUT', 10); // 秒

// 送信リトライ最大回数（超えたらdead）
define('OUTBOX_MAX_TRY', 5);

// ログ
define('OUTBOX_LOG_DIR', __DIR__ . '/logs');
define('OUTBOX_LOG_FILE', OUTBOX_LOG_DIR . '/outbox_sender.log');

// =====================================
// 応募URL
// =====================================
define('APPLY_URL', 'https://cp2025-kusuri-aoki.com/apply/');



