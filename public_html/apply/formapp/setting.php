<?php

date_default_timezone_set('Asia/Tokyo');

// 送信先
$param['from'] = "info@xxx.com";
$param['from_name'] = "";
$param['to'] = "fuchu@works-kanazawa.com"; // 複数はカンマ( , )でつなげる

// メールタイトル
$param['subject'] = array(
        'admin' 	=> '',
        'user'		=> '',
);
        
$param['autores'] = true;

// 処理する項目のname
// 処理する項目のname
$param['forms'] = array(
        'member_id',
        'last_name',
        'first_name',
        'email',
        'tel',        
        'address',
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
        'groupmail'	=> "groupmail.tpl",
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
// DB設定
// =====================================
define('DB_NAME', 'mydatabase');
define('DB_USER', 'myuser');
define('DB_PASSWORD', 'mypassword');
define('DB_HOST', 'db');
define('DB_PORT', '3306');

// =====================================
// 応募URL
// =====================================
define('APPLY_URL', 'https://cp2025-kusuri-aoki.com/apply/');

// =====================================
// Blastengine設定
// =====================================
define('BE_BEARER_TOKEN', 'NTEwYWFmMjAzNTY0ZDQyZWJmNDczNWEzYjAyMDRkMzRmMGM1ZjQwNGFhNWU4ZWJhMGZkMjljNzI1MjhlNzU4Mg==');

// Userメール設定
define('BE_FROM_EMAIL', 'noreply@cp2025-kusuri-aoki.com');
define('BE_FROM_NAME', 'クスリのアオキ「40周年大感謝キャンペーン」事務局');
define('BE_FROM_SUBJECT', 'クスリのアオキ40周年大感謝キャンペーン 当選者情報受付');

// Adminメール設定
define('BE_ADMIN_TO', 'fuchu@works-kanazawa.com');
// define('BE_ADMIN_TO', 'jimukyoku@cp2025-kusuri-aoki.com'); //本番
define('BE_ADMIN_SUBJECT', '当選者情報の登録がありました');

define('BE_TEST_TOKEN', 'pwworks');

// =====================================
// List-Unsubscribe（迷惑メール対策）
// =====================================
// メールでキャンセルを受け付ける宛先
define('BE_UNSUBSCRIBE_MAIL', 'unsubscribe@cp2025-kusuri-aoki.com');

// Webでキャンセルを受け付けるURL
define('BE_UNSUBSCRIBE_URL', 'https://cp2025-kusuri-aoki.com/unsubscribe/');
