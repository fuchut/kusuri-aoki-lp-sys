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
// 応募URL
// =====================================
define('APPLY_URL', 'https://cp2025-kusuri-aoki.com/apply/');



