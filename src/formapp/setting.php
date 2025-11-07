<?php

date_default_timezone_set('Asia/Tokyo');

// 送信先
$param['from'] = "info@xxx.com";
$param['from_name'] = "リフォーム・アイミッツ";
$param['to'] = "fuchu@works-kanazawa.com"; // 複数はカンマ( , )でつなげる

// メールタイトル
$param['subject'] = array(
        'admin' 	=> '【リフォーム・アイミッツ】HPからのお問い合わせ',
        'user'		=> '【リフォーム・アイミッツ】お見積もりのご依頼ありがとうございます。',
);
        
$param['autores'] = true;

// 処理する項目のname
$param['forms'] = array(
        'member_id',
        'present',
        'email',
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

$param['present'] = array(
        1 => 'ノベルティカード',
        2 => 'プレート4枚セット（各21cm）',
        3 => 'ブランケット in クッション',
        4 => 'マグカップ（フタ付）2個セット',
        5 => '水筒（350ml） 白',
        6 => '水筒（350ml） 黒',
        7 => 'バスタオル',
        8 => 'ランチBOXセット'
);
$param['present_type'] = 'radio';
$param['present_img'] = array(
        1 => '/assets/img/present_01.png',
        2 => '/assets/img/present_02.png',
        3 => '/assets/img/present_03.png',
        4 => '/assets/img/present_04.png',
        5 => '/assets/img/present_05.png',
        6 => '/assets/img/present_06.png',
        7 => '/assets/img/present_07.png',
        8 => '/assets/img/present_08.png',
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
// Blastengine設定
// =====================================
define('BE_BEARER_TOKEN', 'NTEwYWFmMjAzNTY0ZDQyZWJmNDczNWEzYjAyMDRkMzRmMGM1ZjQwNGFhNWU4ZWJhMGZkMjljNzI1MjhlNzU4Mg==');

// Userメール設定
define('BE_FROM_EMAIL', 'noreply@cp2025-kusuri-aoki.com');
define('BE_FROM_NAME', 'クスリのアオキ');
define('BE_FROM_SUBJECT', 'キャンペーンのご応募ありがとうございます');

// Adminメール設定
define('BE_ADMIN_TO', 'fuchu@works-kanazawa.com');
define('BE_ADMIN_SUBJECT', '応募がありました');

define('BE_TEST_TOKEN', 'pwworks');

// =====================================
// List-Unsubscribe（迷惑メール対策）
// =====================================
// メールでキャンセルを受け付ける宛先
define('BE_UNSUBSCRIBE_MAIL', 'unsubscribe@cp2025-kusuri-aoki.com');

// Webでキャンセルを受け付けるURL
define('BE_UNSUBSCRIBE_URL', 'https://cp2025-kusuri-aoki.com/unsubscribe/');