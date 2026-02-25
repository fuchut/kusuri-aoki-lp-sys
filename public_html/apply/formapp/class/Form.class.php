<?php

// require $_SERVER["DOCUMENT_ROOT"].'/../../bin/vendor/autoload.php';
require $_SERVER["DOCUMENT_ROOT"].'/../bin/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Form {

	protected $param;
	protected $mode;
	protected $error;
	protected $db;

	protected $total_price = 0;

	/********************************************
	 * セッション  
	 ********************************************/

	public function sessionStart() {
		session_start();
		// session_regenerate_id();
	}

	public function sessionEnd() {
		$_SESSION = array();

		// セッションを切断するにはセッションクッキーも削除する。
		// Note: セッション情報だけでなくセッションを破壊する。
		if (isset($_COOKIE[session_name()])) {
			setcookie(session_name(), '', time()-42000, '/');
		}

		// 最終的に、セッションを破壊する
		session_destroy();
	}
	
	/**
	 * 連投チェック
	 */ 
	public function checkSendTimer($time) {
		if(isset($_SESSION['form_send_time']) && $_SESSION['form_send_time']) {
			$check_time = new Datetime();
			if($_SESSION['form_send_time'] > $check_time->format('U')-$time*60) {
				return false;
			}
		}
		$_SESSION['form_send_time'] = "";
		return true;
	}
	public function setSendTimer() {
		$this->sessionStart();
		$now = new Datetime();
		$_SESSION['form_send_time'] = $now->format('U');
	}


	/********************************************
	 * 初期化など
	 ********************************************/
	public function setParam($param, $mode) {
		$this->param = $param;
		$this->mode = $mode;
	}
	public function init() {
		foreach($this->param['forms'] as $v) {
			$data['data'][$v] = "";
			$data['error'][$v] = "";
			if(isset($this->param['forms_array'][$v])) {
				foreach($this->param['forms_array'][$v] as $w) {
					$data['data'][$w] = "";
					$data['error'][$w] = "";
				}
			}
		}

		return $data;
	}

	/**
	 * モードの再セット
	 */
	public function setMode($mode) {
		$this->mode = $mode;
	}
	
	public function setDB($db) {
		$this->db = $db;
	}

	/********************************************
	 * 出力
	 ********************************************/
	/**
	 * 出力処理
	 * @param Array $param	共通パラメータ
	 * @param Array $tpl	本文部分のテンプレートファイル
	 * @param Array $tpl	置換文字
	 * @return string
	 */
	public function outputTpl($tpl, $data) {

		$tpl_data = file_get_contents($this->param['tpl_path'].$tpl);

		foreach($this->param['forms'] as $k) {
			$v = "";
			if(isset($data['data'][$k])) {
				$v = $data['data'][$k];
				if($this->mode == "confirm") {
					switch($k) {
						case "text" : 
							$v = nl2br($v);
							break;
					}
				}
			}
			$tpl_data = str_replace("<!-- ".$k." -->", $v, $tpl_data);

			if(isset($this->param['forms_array'][$k])) {
				foreach($this->param['forms_array'][$k] as $k2) {
					$tmp = "";
					if(isset($data['data'][$k2])) { $tmp = $data['data'][$k2]; }
					$tpl_data = str_replace("<!-- ".$k2." -->", $this->h($tmp), $tpl_data);
				}
			}
		}

		foreach($this->param['forms'] as $k) {
			$v = "";
			$class = "";
			if(isset($data['error'][$k]) && $data['error'][$k]) { 
				$v = '<span class="error red-error">'.$this->h($data['error'][$k]).'</span>';
				$class = "error_bg";
			}
			$tpl_data = str_replace("<!-- error_".$k." -->", $v, $tpl_data);
			$tpl_data = str_replace("<!-- error_bg_".$k." -->", $class, $tpl_data);
		}

		// 総合エラー
		$key = 'consecutive';
		if (isset($data['error'][$key]) && $data['error'][$key]) {
			$tpl_data = str_replace("<!-- error_".$key." -->", $data['error'][$key], $tpl_data);
		}
	
		require_once($this->param['tpl_path'].'header.tpl');
		echo $tpl_data;
		require_once($this->param['tpl_path'].'footer.tpl');

	}

	/**
	 * Form表示用成形
	 */
	public function makeForm($data) {

		$data['data'] = $this->h($data['data']);

		$key = 'member_id';
		if(isset($data['data'][$key]) && $data['data'][$key]) {
			$value = $this->convertAlphaNum(str_replace(" ", "", $data['data'][$key]));
			// $data['data'][$key] = sprintf('%016d', $value);
			$data['data'][$key] = $value;
		}

		return $data;
	}

	public function getTokenFromRequest(): string|false
	{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $parts = array_values(array_filter(explode('/', $path), 'strlen'));

    if (count($parts) < 2) {
        return false;
    }

    // 最後の要素を token とみなす
    $token = $parts[count($parts) - 1];

    // --- Excel 対策形式 ="token" を除去する ---
    // ="abcdef..." → abcdef...
    if (preg_match('/^="([0-9a-fA-F]{32})"$/', $token, $m)) {
        $token = $m[1];
    }

    // --- 引用符に囲まれている場合 ("token") を除外 ---
    if (preg_match('/^"([0-9a-fA-F]{32})"$/', $token, $m)) {
        $token = $m[1];
    }

    // --- 最終チェック：32桁の hex 以外は無効 ---
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $token)) {
        return false;
    }

    return strtolower($token); // 小文字に統一（DB も小文字前提）
	}

	public function getMemberId(string $token)
	{
    // XLSX ファイルのパス
    $xlsxFile = $this->param['xlsx']; // 例: "exports/entry_all.xlsx"

    if (!file_exists($xlsxFile)) {
        return null;
    }

		try {
			$spreadsheet = IOFactory::load($xlsxFile);
		} catch (\Throwable $e) {
			var_dump('[XLSX load error]', $xlsxFile, $e->getMessage());
			exit;
		}

    // 最初のシート取得（entry_all.xlsx は1シート構成）
    $sheet = $spreadsheet->getActiveSheet();

    // 行ループ開始
    $rowIterator = $sheet->getRowIterator();

    // 1行目（ヘッダ）をスキップ
    if ($rowIterator->valid()) {
        $rowIterator->next();
    }

    foreach ($rowIterator as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        $rowValues = [];
        foreach ($cellIterator as $cell) {
            $rowValues[] = (string)$cell->getValue();
        }

        // カラム：0=member_id / 1=present / 2=email / 3=token / 4=url / 5=updated_at
        if (!isset($rowValues[3])) {
            continue;
        }

        $xlsxToken = trim($rowValues[3]);

        // Token が一致したら結果返す
        if ($xlsxToken === $token) {
            // member_id の正規化（数字以外除去）
            $memberIdRaw = $rowValues[0] ?? '';
            $memberId    = preg_replace('/\D/', '', $memberIdRaw);

            return [
								'token'			=> $token,
                'member_id' => $memberId,
                'present'   => $rowValues[1] ?? '',
                'email'     => $rowValues[2] ?? '',
            ];
        }
    }

    return null; // 見つからなかった場合
	}



	public function getMemberIdCSV(string $token)
	{
    // CSV ファイルのパス（クラス内で適宜変更）
    $csvFile = $this->param['csv'];

    if (!file_exists($csvFile)) {
        return null;
    }

    // SJIS-win → UTF-8 に変換しながら読み込み
    $fp = fopen($csvFile, 'r');
    if (!$fp) {
        return null;
    }

    // 1行目はヘッダー
    $header = fgetcsv($fp);

    while (($row = fgetcsv($fp)) !== false) {

        // 各要素を UTF-8 に変換
        $row = array_map(function ($v) {
            return mb_convert_encoding($v, 'UTF-8', 'SJIS-win');
        }, $row);

        // CSV カラム順:
        // 0:member_id, 1:present, 2:email, 3:token, 4:url, 5:updated_at
        $csvToken = trim($row[3]);

        if ($csvToken === $token) {

            // member_id の正規化: ="0000012345678900" → 0000012345678900
            $memberId = preg_replace('/[^0-9]/', '', $row[0]);

            fclose($fp);
            return array(
							'token' => $token,
							'member_id' => $memberId, 
							'present' => $row[1],
							'email' => $row[2],
						);
        }
    }

    fclose($fp);
    return null; // 見つからない
	}


	/**
	 * 確認画面用成形
	 */
	public function makeConfirm($data) {

		$data['data'] = $this->h($data['data']);

		$key = 'member_id';
		if(isset($data['data'][$key]) && $data['data'][$key]) {
			$value = $this->convertAlphaNum(str_replace(" ", "", $data['data'][$key]));
			$value = sprintf('%016d', $value);
			$data['data'][$key] = trim(chunk_split($value, 4, ' '));
		}

		return $data;
	}

	/**
	 * メール用成形
	 */
	public function makeMailBody($data) {

		$data['data'] = $this->h($data['data']);

		$key = 'member_id';
		if(isset($data['data'][$key]) && $data['data'][$key]) {
			$value = $this->convertAlphaNum($data['data'][$key]);
			$value = str_replace(" ", "", $value);
			$value = sprintf('%016d', $value);
			$data['data'][$key] = trim(chunk_split($value, 4, ' '));
		}

		$key = 'present';
		$data['data'][$key] = empty($_SESSION['memberData'][$key]) ? "" : $_SESSION['memberData'][$key];

		return $data;
	}


	/********************************************
	 * Mail
	 ********************************************/
	/**
	 * メール送信
	 */
	public function sendMail($maildata, $data = null) {

		$charset = "iso-2022-JP";

		mb_language("ja");
		mb_internal_encoding("utf-8");

		$from = $this->param['from'];
		//$header = "From: {$from}\nReply-To: {$from}\nContent-Type: text/plain;";

		// 管理者宛
		$to = $this->param['to'];
		$subject = $this->param['subject']['admin'];
		$msg = file_get_contents($this->param['tpl_path'].$this->param['tpl']['adminmail']);

		foreach($maildata['data'] as $k => $v) {
      $msg = str_replace("<!-- ".$k." -->", $this->d_h($this->mb_wordwrap($v, 250)), $msg);
		}

		//$present = empty($_SESSION['memberData']['present']) ? "" : $_SESSION['memberData']['present'];
		//$msg = str_replace("<!-- present -->", $this->d_h($this->mb_wordwrap($present, 250)), $msg);

		$msg = mb_convert_encoding($msg, $charset, "AUTO");

		$from = array("name" => $this->param['from_name'], "mail" => $this->param['from']);

		$headers = "Mime-Version: 1.0\n";
		$headers .= "Content-Transfer-Encoding: 7bit\n";
		$headers .= "Content-Type: text/plain;charset={$charset}\n";
		// $headers .= "From: ".mb_encode_mimeheader(mb_convert_encoding($from["name"], $charset, "AUTO"))." <".$from["mail"].">";
		$headers .= "From: ".mb_encode_mimeheader($from["name"])."<".$from["mail"].">";


		// メールの本文と添付ファイルを作成
		mb_send_mail($to, $subject, $msg, $headers);

		// ユーザー宛（自動返信）
		if ($this->param['autores']) {
			$to = $data['data']['email'];
			if ($to) {
				$subject = $this->param['subject']['user'];
				$msg = file_get_contents($this->param['tpl_path'].$this->param['tpl']['mail']);

				foreach ($maildata['data'] as $k => $v) {
					if (is_array($v)) {
							continue;
					}
					$msg = str_replace("<!-- ".$k." -->", $this->d_h($this->mb_wordwrap($v, 250)), $msg);
				}

				// $msg = str_replace("<!-- present -->", $this->d_h($this->mb_wordwrap($present, 250)), $msg);
				
				$msg = mb_convert_encoding($msg, $charset, "AUTO");

				$headers = "Mime-Version: 1.0\n";
				$headers .= "Content-Transfer-Encoding: 7bit\n";
				$headers .= "Content-Type: text/plain;charset={$charset}\n";
				$headers .= "From: ".mb_encode_mimeheader($from["name"])."<".$from["mail"].">";

				mb_send_mail($to, $subject, $msg, $headers);
			}
		}

	}

	// private function makeXlsx($data) {
		
	// 	$temp_file = dirname(__FILE__).'/../tpl/template.xlsx';

	// 	// テンプレートファイルを読み込み
	// 	// $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp_file);
	// 	$spreadsheet = new Spreadsheet();
	// 	$sheet = $spreadsheet->getActiveSheet();

	// 	$items = array(
	// 		'date' => "日時",
	// 		'your-name' => "お名前",
	// 		'your-kana' => "フリガナ",
	// 		'your-mail' => "メールアドレス",
	// 	);

	// 	// 見出し
	// 	$cells[0] = array();
	// 	foreach((array)$items as$key => $item) {
	// 		$cells[0][] = $item;
	// 	}
	// 	$reforms = array('all', 'water', 'out', 'room', 'etc');
	// 	foreach((array)$reforms as $reform) {
	// 		foreach((array)$this->param['house_reform_'.$reform] as $key => $item) {
	// 			$cells[0][] = $key;
	// 		}
	// 	}

	// 	// 共通の値
	// 	$now = new Datetime();
	// 	$now_f = $now->format('Y-m-d H:i:s');
	// 	$values = array();
	// 	foreach((array)$items as $key => $item) {
	// 		if($key == 'date') {
	// 			$values[$key] = $now_f;
	// 		} else if($key == 'house_request') {
	// 			$values[$key] = "";
	// 		} else {
	// 			$values[$key] = $data['data'][$key];
	// 		}
	// 	}

	// 	$sheet->fromArray($cells, null, 'A1');

	// 	// セル書式を文字列に
	// 	// $sheet -> getStyle("A1:Z4") -> getNumberFormat() ->setFormatCode(
	// 	// 	\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
	// 	// );

	// 	$spreadsheet->setActiveSheetIndex(0); //シートを先頭に合わせる
	// 	$spreadsheet->getSheet(0) -> unfreezePane('A1'); //アドレスA1に合わせる

	// 	// メモリ上に保存
	// 	$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
	// 	ob_start();
	// 	$writer->save('php://output');
	// 	$excelData = ob_get_contents();
	// 	ob_end_clean();

	// 	return $excelData;
	// }

	/********************************************
	 * Validate
	 ********************************************/
	/**
	 * Validate
	 */
	public function validate($data) {

		// スペースのみのデータを削除
		$data = $this->doTrim($data);

		$tmp = $this->init();
		$data = array_merge($tmp['data'], $data);
		$this->error = $tmp['error'];
		unset($this->error['consecutive']);

		$key = 'token';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = 'トークンをが不正です';
		}

		$key = 'member_id';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '会員No.を入力してください';
		} else {
			$data[$key] = $this->convertAlphaNum($data[$key]);
			$noHyphen = str_replace(" ", "", $data[$key]);
			$data[$key] = $noHyphen;
		 	if(!$this->isInt($noHyphen) || !$this->strLength($noHyphen, 16, 16)) {
				$this->error[$key] = '会員No.を確認してください';
			}
		}

		$key = 'last_name';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		}
		$key = 'first_name';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		}		

		$key = 'email';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isMail($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		}

		$key = 'tel';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isTelNoH($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		}

		$key = 'zip';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		}	

		$key = 'address';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key]) {
			$this->error[$key] = '入力してください';
		}	

		if(empty($_SESSION['memberData'])) {
			$this->error['consecutive'] = '<p class="error red-error consecutive_error">入力エラーがあります。</p>';
		} elseif(empty($_SESSION['memberData']['member_id']) || empty($_SESSION['memberData']['email']) || empty($data['member_id']) || empty($data['email'])) {
			$this->error['consecutive'] = '<p class="error red-error consecutive_error">トークンエラー</p>';
		} elseif(($_SESSION['memberData']['member_id'] != $data['member_id']) || ($_SESSION['memberData']['email'] != $data['email'])) {
			$this->error['consecutive'] = '<p class="error red-error consecutive_error">トークンエラー</p>';
		}

		if($this->arrayFilterRecursive($this->error)) {
			$this->error['consecutive'] = '<p class="error red-error consecutive_error">入力エラーがあります。</p>';
		}

		$_SESSION['edit'] = $data;

		return $data;
	}


	/**
	 * Validate後のエラーを返す
	 */
	public function rtnError() {
		return $this->error;
	}

	/********************************************
	 * 変換・成形処理 共通
	 ********************************************/
	/**
	 * htmlspecialcharsを実行(コード用)
	 * @param unknown $str
	 * @param unknown $enc	変換文字コード
	 * @return string
	 */
	public function h($str, $enc = null) {

		if(!$enc) {
			$enc = mb_internal_encoding();
		}

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->h($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = str_replace('<br>', "\n",  $str);
			$str = nl2br(htmlspecialchars($str, ENT_QUOTES, $enc));
		}
		return $str;
	}

	/**
	 * エンコードして出力する（配列は除外）
	 * @param String $str 
	 */
	public function v($str) {
		echo $this->h($str);
	}


	/**
	 * nl2br, htmlspecialcharsを実行(画面表示用)
	 * @param unknown $str
	 * @param unknown $enc	変換文字コード
	 * @return string
	 */
	public function hBr($str, $enc = null) {

		if(!$enc) {
			$enc = mb_internal_encoding();
		}

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->hBr($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = nl2br(htmlspecialchars($str, ENT_QUOTES, $enc));
		}
		return $str;
	}	 

	/**
	 * htmlspecialcharsをDecode(コード用)
	 * @param unknown $str
	 * @param unknown $enc	変換文字コード
	 * @return string
	 */
	public function d_h($str, $enc = null) {

		if(!$enc) {
			$enc = mb_internal_encoding();
		}

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->h($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = html_entity_decode($str, ENT_QUOTES, $enc);

			// タグだけは動作しないための保険
			$str = str_replace('<', '＜', $str);
			$str = str_replace('>', '＞', $str);
			$str = str_replace("'", '’', $str);
			$str = str_replace('"', '”', $str);
			$str = str_replace('&', '＆', $str);

		}
		return $str;
	}

	/**
	 * 全角英数字を半角英数に変換
	 * @param unknown $str
	 */
	public function convertAlphaNum($str) {

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] =  $this->convertAlphaNum($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = mb_convert_kana($str, "a", mb_internal_encoding());
		}

		return $str;

	}

	/**
	 * 半ｶﾅを全角カナに変換
	 */
	public function convertSkana($str) {

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->convertHira($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = mb_convert_kana($str, "KA" ,mb_internal_encoding());
		}
		return $str;

	}

	/**
	 * カナをひらがなに変換
	 */
	public function convertHira($str) {

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->convertHira($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = mb_convert_kana($str, "HVc" ,mb_internal_encoding());
		}
		return $str;

	}

	/**
	 * 半ｶﾅ・全かなを全カナに変換
	 */
	public function convertKana($str) {

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->convertKana($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = mb_convert_kana($str, "KVC" ,mb_internal_encoding());
		}
		return $str;

	}

	/**
	 * ハイフンを削除
	 * @param Array $array
	 * @return Array
	 */
	public function removeHyphen($str) {
		if (is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach ($str as $k => $v) {
					$str[$k] = $this->removeHyphen($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = mb_ereg_replace("-", "", $str);
			$str = mb_ereg_replace("ー", "", $str);
			$str = mb_ereg_replace("－", "", $str);
		}
		return $str;
  }

	/**
	 * 空の文字を排除する
	 * @param Array $array
	 * @return Array
	 */
	public function doTrim($str) {

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->doTrim($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$str = trim(mb_convert_kana($str, "s" ,mb_internal_encoding()));
		}
		return $str;
	}

	/**
	 * 空の配列を排除する
	 * @param Array $array
	 * @return Array
	 */
	public function arrayFilterRecursive($array) {

		foreach ($array as &$value) {
			if (is_array($value)) {
				$value = $this->arrayFilterRecursive($value);
			}
		}
		return array_filter($array);
	}

  // メール改行を追加
  public function mb_wordwrap( $str, $width=35, $break=PHP_EOL ) {

		mb_language("ja");
		mb_internal_encoding("utf-8");

		if(is_array($str)) {//配列かどうか
			//配列の場合、foreachで再帰処理
			foreach($str as $k => $v){
				$str[$k] = $this->mb_wordwrap($v);
			}
		} else {
			//配列じゃない場合、処理を行う
			$c = mb_strlen($str, "utf-8");
			$arr = array();
			for ($i=0; $i<=$c; $i+=$width) {
					$arr[] = mb_substr($str, $i, $width, "utf-8");
			}
			$str = implode($break, $arr);
		}
		return $str;

  }


	/********************************************
	 * チェック 共通 
	 ********************************************/
	/**
	 * 空チェック
	 * 配列のチェックも対象
	 * @param Array|String $str
	 * @return boolean
	 */
	public function notEmpty($str) {

		// 配列の場合は、空を除去しチェック
		if(is_array($str)) {
			$str = $this->arrayFilterRecursive($str);
			if(empty($str)) {
				return FALSE;
			}
		} elseif(mb_strlen($str, mb_internal_encoding()) < 1) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 文字数チェック
	 * それぞれ0の場合は判定なしとする
	 * @param String $str
	 * @param Integer $min	//最少文字数
	 * @param Integer $max	//最大文字数
	 * @return boolean
	 */
	public function strLength($str, $min = 0, $max = 0) {

		$c = mb_strlen($str, mb_internal_encoding());

		if($min) {
			if($c < $min) {
				return FALSE;
			}
		}
		if($max) {
			if($c > $max) {
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * 整数のみ
	 * @param String $str
	 * @return boolean
	 */
	public function isInt($str) {

		if (!preg_match("/^[0-9]+$/", $str)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 英数のみ
	 * @param String $str
	 * @return boolean
	 */
	public function isAlpha($str) {

		if (!preg_match("/^[0-9a-zA-Z]+$/", $str)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * メールアドレス形式チェック
	 * @param String $str
	 * @return boolean
	 */
	public function isMail($str) {

		if(!preg_match('/^[-+.\\w]+@[-a-z0-9]+(\\.[-a-z0-9]+)*\\.[a-z]{2,6}$/i', $str)){
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 電話番号（ハイフン有）
	 * @param String $str
	 * @return boolean
	 */
	public function isTel($str) {

		//形式チェック
		if(!preg_match('/^0\d{1,4}-\d{1,4}-\d{4}$/', $str)) {
			return FALSE;
		}

		//半角または全角のハイフンは取り除く
		$str = $this->removeHyphen($str);

		//数字であり、かつ10桁もしくは11桁かチェック
		if(!ctype_digit($str) || !(strlen($str) == 10 OR strlen($str)== 11)){
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 電話番号（ハイフン有無兼用）
	 * @param String $str
	 * @return boolean
	 */
	public function isTelNoH($str) {

		//半角または全角のハイフンは取り除く
		$str = $this->removeHyphen($str);

		//数字であり、かつ10桁もしくは11桁かチェック
		if(!ctype_digit($str) || !(strlen($str) == 10 OR strlen($str)== 11)){
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 郵便番号チェック（ハイフン有）
	 * @param String $str
	 * @return boolean
	 */
	public function isZip($str) {

		if (!preg_match("/^\d{3}\-\d{4}$/",$str)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * 郵便番号チェック（ハイフン有無兼用）
	 * @param String $str
	 * @return boolean
	 */
	public function isZipNoH($str) {

		//半角または全角のハイフンは取り除く
		$str = mb_ereg_replace("-", "", $str);
		$str = mb_ereg_replace("ー", "", $str);
		$str = mb_ereg_replace("－", "", $str);

		//数字であり、かつ7桁かチェック
		if(!ctype_digit($str) || !(strlen($str) == 7)){
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * かなチェック
	 * @param String $str
	 * @return boolean
	 */
	public function isHira($str) {

		mb_regex_encoding("UTF-8");
		$str = $this->convertHira($str);
		if (!preg_match("/^[ぁ-ん　 ]+$/u", $str)) {
			return FALSE;
		}
		return TRUE;
	}

	/**
	 * カナチェック
	 * @param String $str
	 * @return boolean
	 */
	public function isKana($str) {

		mb_regex_encoding("UTF-8");
		$str = $this->convertSKana($str);
		if (!preg_match("/^[ァ-ヶー　 ]+$/u", $str)) {
			return FALSE;
		}
		return TRUE;
	}


}