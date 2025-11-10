<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ini_set( 'display_errors', 1 );
// ini_set('error_reporting', E_ALL);

require_once(dirname(__FILE__).'/formapp/setting.php');
require_once(dirname(__FILE__).'/formapp/class/Form.class.php');
require_once(dirname(__FILE__).'/formapp/class/DB.class.php');

// Form Class
$org = new Form();
$db = new DB(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD);

// セッションスタート
$org->sessionStart();

// Modeを取得
$mode = "";
if($_REQUEST && isset($_REQUEST['mode'])) {
	$mode = $_REQUEST['mode'];
}
if($mode == "send") {
	if (!isset($_POST['mode']) || !$_POST['mode']) {
		$mode = "";
	}
}
$timer_flg = $org->checkSendTimer(0); // set - minutes
if(!$timer_flg) {
	$mode = "";
}

// パラメータをClass変数にセット
$org->setParam($param, $mode);

// データ初期化
$data = $org->init();

// Mode別に処理
switch($mode) {
	case "confirm":

		// Validate と データの格納
		$data['data'] = $org->validate($_POST);

		// エラーを取得
		$data['error'] = $org->rtnError();		

		// テンプレートを設定
		if($org->arrayFilterRecursive($data['error'])) {
			$tpl = $param['tpl']['form'];

			// フォーム用成形
			$org->setMode('return');
			$data = $org->makeForm($data);

		} else {
			$tpl = $param['tpl']['confirm'];

			// 確認画面用成形
			$data = $org->makeConfirm($data);				
			
		}

		break;

	case "send":

		// Validate と データの格納
		$data['data'] = $org->validate($_SESSION['edit']);

		// エラーを取得
		$data['error'] = $org->rtnError();	

		// テンプレートを設定
		if($org->arrayFilterRecursive($data['error'])) {
			$tpl = $param['tpl']['form'];
			
			// フォーム用成形
			$data = $org->makeForm($data);
			
		} else {
		
			// メール用成形
			$maildata = $org->makeMailBody($data);

			$db->insertEntry($maildata);
			// $org->sendMail($maildata, $data);

			$data['data'] = array();

			// テンプレートを設定
			// $tpl = $param['tpl']['complete'];
			
			// セッションを終了
			$org->sessionEnd();	
	
			$org->setSendTimer();
	
			header('Location:thanks.html');

			exit;
		}
		break;

	case "return":
		// データ格納
		$data['data'] = $_SESSION['edit'];	

		// テンプレートを設定	
		$tpl = $param['tpl']['form'];

		// フォーム用成形
		$data = $org->makeForm($data);
		
		break;

	default:

		// テンプレートを設定
		$tpl = $param['tpl']['form'];

		// フォーム用成形
		$data = $org->makeForm($data);	

		if(!$timer_flg) {
			$data['error']['consecutive'] = '<p class="error consecutive_error">連続しての送信はできません。時間を置いてお試しください。</p>';
		}

		// セッションの初期化
		// $_SESSION['edit'] = array();
}

// 出力
$org->outputTpl($tpl, $data);
