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

//** 設定ここまで ******************************************//

// 処理する項目のname
$param['forms'] = array(
        'house_type',
        'house_type_building', 
        'house_age', 
        'house_reform_all',
        'house_reform_water',
        'house_reform_out',
        'house_reform_room',
        'house_reform_etc',
        'house_budget',
        'house_construction',
        'house_request',
        'your-name', 'your-kana', 
        'your-tel', 'your-mail', 'your-confirm',
        'your-post', 'your-address',
        'your-method',
        'your-check'
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

$param['slideno'] = array(
        'house_type' => 0,
        'house_type_building' => 0,
        'house_age' => 1, 
        'house_reform_all' => 2,
        'house_reform_water' => 2,
        'house_reform_out' => 2,
        'house_reform_room' => 2,
        'house_reform_etc' => 2,
        'house_budget' => 3,
        'house_construction' => 4,
        'house_request' => 5,
        'your-name' => 6,
        'your-kana' => 6,
        'your-tel' => 6,
        'your-mail' => 6,
        'your-confirm' => 6,
        'your-post' => 6,
        'your-address' => 6,
        'your-method' => 6,
        'your-check' => 6,
);


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

$param['house_type'] = array(
        '戸建住宅' => '戸建住宅',
        'マンション/アパート' => 'マンション/アパート',
        '店舗/事務所' => '店舗/事務所',
);
$param['house_type_type'] = 'radio';
$param['house_type_img'] = array(
        '戸建住宅' => '/assets/img/svg/icon-detached.svg',
        'マンション/アパート' => '/assets/img/svg/icon-apartment.svg',
        '店舗/事務所' => '/assets/img/svg/icon-commercial.svg',
);

$param['house_type_building'] = array(
        '木造' => '木造',
        '鉄筋' => '鉄筋',
        'ツーバイフォー' => 'ツーバイフォー',
        'コンクリート' => 'コンクリート',
);
$param['house_type_building_type'] = 'radio';

$param['house_age'] = array(
        '～10年' => '～10年',
        '10年〜20年' => '10年〜20年',     
        '20年〜30年' => '20年〜30年',
        '30年〜40年' => '30年〜40年',
        '50年以上' => '50年以上',
);
$param['house_age_type'] = 'radio';

$param['house_reform_all'] = array(
        '建物全体' => '建物全体',
        '1階のみ全体' => '1階のみ全体',
);
$param['house_reform_all_type'] = 'checkbox';

$param['house_reform_water'] = array(
        'キッチン' => 'キッチン',
        '浴室・バス' => '浴室・バス',
        'トイレ' => 'トイレ',
        '洗面' => '洗面',
);
$param['house_reform_water_type'] = 'checkbox';
$param['house_reform_water_max'] = -1;

$param['house_reform_out'] = array(
        '外壁' => '外壁',
        '屋根' => '屋根',
        '外構・エクステリア' => '外構・<br>エクステリア',
        'バルコニー・ベランダ' => 'バルコニー・<br>ベランダ',
);
$param['house_reform_out_type'] = 'checkbox';
$param['house_reform_out_max'] = -1;

$param['house_reform_room'] = array(
        'リビング' => 'リビング',
        'ダイニング' => 'ダイニング',
        '洋室' => '洋室',
        '和室' => '和室',
);
$param['house_reform_room_type'] = 'checkbox';
$param['hhouse_reform_room_max'] = -1;

$param['house_reform_etc'] = array(
        '玄関' => '玄関',
        '廊下' => '廊下',
        '階段' => '階段',
        '窓・サッシ' => '窓・サッシ',
        '断熱' => '断熱',
        'その他' => 'その他',
);
$param['house_reform_etc_type'] = 'checkbox';
$param['house_reform_etc_max'] = -1;

$param['house_budget'] = array(
        '～500万円' => '～500万円',
        '500〜1000万円' => '500〜1000万円',
        '1000万円以上' => '1000万円以上',
        '未定' => '未定',
);
$param['house_budget_type'] = 'radio';

$param['house_construction'] = array(
        '3ヶ月以内' => '3ヶ月以内',
        '半年以内' => '半年以内',
        '1年以内' => '1年以内',
        '2年以内' => '2年以内',
        '未定' => '未定',        
);
$param['house_construction_type'] = 'radio';

$param['house_request'] = array(
        '会社1' => '会社1',
        '会社2' => '会社2',
        '会社3' => '会社3',
        '会社4' => '会社4',
        '会社5' => '会社5',        
        '会社6' => '会社6',
        '会社7' => '会社7',
        '会社8' => '会社8',
        '会社9' => '会社9',
        '会社10' => '会社10',     
);
$param['house_request_type'] = 'checkbox';
$param['house_request_max'] = 3;
$param['house_request_img'] = array(
        '会社1' => '/assets/img/svg/icon-detached.svg',
        '会社2' => '/assets/img/svg/icon-apartment.svg',
        '会社3' => '/assets/img/svg/icon-commercial.svg',
        '会社4' => '/assets/img/svg/icon-commercial.svg',
        '会社5' => '/assets/img/svg/icon-commercial.svg',
        '会社6' => '/assets/img/svg/icon-detached.svg',
        '会社7' => '/assets/img/svg/icon-apartment.svg',
        '会社8' => '/assets/img/svg/icon-commercial.svg',
        '会社9' => '/assets/img/svg/icon-commercial.svg',
        '会社10' => '/assets/img/svg/icon-commercial.svg',
);
$param['house_request_mail'] = array(
        '会社1' => 'fuchu@works-kanazawa.com',
        '会社2' => 'fuchu@works-kanazawa.com',
        '会社3' => 'fuchu@works-kanazawa.com',
        '会社4' => 'fuchu@works-kanazawa.com',
        '会社5' => 'fuchu@works-kanazawa.com',       
        '会社6' => 'fuchu@works-kanazawa.com',
        '会社7' => 'fuchu@works-kanazawa.com',
        '会社8' => 'fuchu@works-kanazawa.com',
        '会社9' => 'fuchu@works-kanazawa.com',
        '会社10' => 'fuchu@works-kanazawa.com',
);

$param['your-method'] = array(
        'メール' => 'メール',
        '電話' => '電話'
);