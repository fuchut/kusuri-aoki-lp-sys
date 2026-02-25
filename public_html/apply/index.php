<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ini_set( 'display_errors', 1 );
// ini_set('error_reporting', E_ALL);

require_once(dirname(__FILE__).'/formapp/setting.php');
require_once(dirname(__FILE__).'/formapp/class/Form.class.php');
require_once(dirname(__FILE__).'/formapp/class/DB.class.php');
require_once(dirname(__FILE__).'/formapp/class/OutboxSender.class.php');

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

		$_POST['email'] = $_SESSION['memberData']['email'];
		$_POST['token'] = $_SESSION['memberData']['token'];
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

			$outsender = new OutboxSender($db->getPdo());

			// token（validate済みの想定だが、ガードだけ）
			$token = (string)($data['data']['token'] ?? '');
			if ($token === '') {
				error_log('token missing after validate dir=' . __DIR__);
				http_response_code(500);
				exit('internal error');
			}

			// Group取得（設定ファイルから）
			if (!defined('APP_GROUP') || APP_GROUP === '') {
				// 設定漏れは運用事故なので即停止
				error_log('APP_GROUP not defined or empty dir=' . __DIR__);
				http_response_code(500);
				exit('config error');
			}
			$group = (string)APP_GROUP;

			// メール用成形
			$maildata = $org->makeMailBody($data);

			// 送信ログの記録
			try {
				$db->recordAttempt($token);
			} catch (Throwable $e) {
				error_log('recordAttempt failed: ' . $e->getMessage());
				$data['error']['db'] = '<p class="error">ただいま混み合っています。時間を置いてお試しください。</p>';
				$tpl = $param['tpl']['form'];
				$data = $org->makeForm($data);
				break;
			}

			// member_idのスペースをお落とす
			$trim_member_id = str_replace(" ", "", $maildata['data']['member_id']);

			// ===== 1.5) A Outboxへ暗号文保存（復号不可）=====
			try {
				$payload = [
					'token'      => $token,
					'group'      => $group,

					// 必須（B側がチェックしている）
					'member_id'  => (string)($trim_member_id ?? ''),  // ←ここ重要
					'email'      => (string)($maildata['data']['email'] ?? ''),

					// 氏名（分割済み）
					'last_name'  => (string)($maildata['data']['last_name'] ?? ''),
					'first_name' => (string)($maildata['data']['first_name'] ?? ''),

					'tel'        => (string)($maildata['data']['tel'] ?? ''),
					'zip'        => (string)($maildata['data']['zip'] ?? ''),
					'address'    => (string)($maildata['data']['address'] ?? ''),

					// prize → present
					'present'    => (string)($maildata['data']['present'] ?? ''),
				];

				if ($payload['member_id'] === '' || $payload['email'] === '') {
						error_log(
								'payload missing required: token_hash=' .
								hash('sha256', $token)
						);

						// 運用方針A：ここで止める（おすすめ）
						$data['error']['required'] =
								'<p class="error">必須情報が取得できませんでした。お手数ですが再度お試しください。</p>';
						$tpl = $param['tpl']['form'];
						$data = $org->makeForm($data);
						break;

						// 運用方針B：Outboxに積まないが画面は完了扱い
						// return;
				}

				$outsender->enqueue($payload);

			} catch (Throwable $e) {
				echo "Error: " . $e->getMessage();
				exit;
				error_log('outsender->enqueue failed token=' . $token . ' ' . $e->getMessage());
				$data['error']['sync'] = '<p class="error">送信準備に失敗しました。時間を置いてお試しください。</p>';
				$tpl = $param['tpl']['form'];
				$data = $org->makeForm($data);
				break;
			}

			// ===== 2) メール送信（失敗しても止めない）=====
			try {
				$org->sendMail($maildata, $data);
			} catch (Throwable $e) {
				error_log('sendMail failed token=' . $token . ' ' . $e->getMessage());
			}

			// ===== 3) B連携（即時送信を試す）=====
			try {
				$outsender->trySend(20);
			} catch (Throwable $e) {
				error_log('outsender->trySend failed token=' . $token . ' ' . $e->getMessage());
			}

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

		$token = $org->getTokenFromRequest();
		if($token) {
			$_SESSION['memberData'] = $org->getMemberId($token);
			$_SESSION['memberData']['token'] = $token;
			$data['data']['token'] = $token;
		}
		if(isset($_SESSION['memberData']['member_id'])) {
			$data['data']['member_id'] = $_SESSION['memberData']['member_id'];
		}

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
