<?php

// require $_SERVER["DOCUMENT_ROOT"].'/../../../bin/vendor/autoload.php';
// require $_SERVER["DOCUMENT_ROOT"].'/../../bin/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Form {

	protected $param;
	protected $mode;
	protected $error;

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
				$v = '<br><span class="error">'.$this->h($data['error'][$k]).'</span>';
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

		$defaultslide = 0;
		if(isset($data['default-slide'])) {
			$defaultslide = $data['default-slide'];
		}
		$tpl_data = str_replace("<!-- defaultslide -->", "<script>const defaultSlide=".$defaultslide.";</script>", $tpl_data);
		
		require_once($this->param['tpl_path'].'header.tpl');
		echo $tpl_data;
		require_once($this->param['tpl_path'].'footer.tpl');

	}

	/**
	 * Form表示用成形
	 */
	public function makeForm($data) {

		$data['data'] = $this->h($data['data']);

		$keys = array('house_type', 'house_type_building', 'house_age', 'house_reform_all', 'house_reform_water', 'house_reform_out', 'house_reform_room', 'house_reform_etc', 'house_budget', 'house_construction', 'house_request');
		foreach((array)$keys as $key) {
			$tmp = "";
			// if(!isset($data['data'][$key]) || !$data['data'][$key]) { $data['data'][$key] = 1; }
			ob_start();
			$i = 0;
			foreach((array)$this->param[$key] as $k => $v) {
				$sel = "";
				$i++;
				if($this->param[$key.'_type'] == "checkbox") {
					if(isset($data['data'][$key]) && is_array($data['data'][$key])) {
						if(in_array($k, $data['data'][$key])) { $sel = 'checked="checked"'; }
					}
				} else {
					if(isset($data['data'][$key]) && $data['data'][$key] == $k) { $sel = 'checked="checked"'; }
				}
?>				
<li>
	<input
		id="<?php echo $this->h($key.sprintf('%02d', $i)); ?>"
		class="hide-check"
		type="<?php echo $this->h($this->param[$key.'_type']); ?>"
<?php if($this->param[$key.'_type'] == "checkbox") : ?>
		name="<?php echo $this->h($key); ?>[]"
<?php else : ?>
		name="<?php echo $this->h($key); ?>"
<?php endif; ?>
		value="<?php echo $this->h($k); ?>" 
		<?php echo $sel; ?>
	/>
	<?php 
		$box_class = "";
		$img = "";
		if(isset($this->param[$key.'_img']) && is_array($this->param[$key.'_img'])) {
			$box_class = "square";
			$img = $this->param[$key.'_img'][$k];
		} 
	?>
	<label class="<?php echo $box_class; ?>" for="<?php echo $this->h($key.sprintf('%02d', $i)); ?>">
		<span class="item-base">
			<i>
				<svg
					xmlns="http://www.w3.org/2000/svg"
					width="28"
					height="30"
					viewBox="0 0 28 30"
				>
					<g
						id="icon01"
						data-name="icon01"
						transform="translate(1 1)"
					>
						<g id="icon02" data-name="icon02">
							<path
								id="background"
								data-name="background02-path"
								d="M25.359,235.779c-8.01,1.5-10.218,3.839-11.577,12.542a.779.779,0,0,1-1.545,0c-1.371-8.7-3.581-11.041-11.6-12.524a.805.805,0,0,1,0-1.576c8.012-1.5,10.218-3.84,11.576-12.544a.779.779,0,0,1,1.545,0c1.372,8.7,3.584,11.043,11.6,12.526a.8.8,0,0,1,0,1.576"
								transform="translate(0 -221)"
								fill="#fff"
							/>
							<path
								id="icon03"
								data-name="icon03-path"
								d="M13.009,250a1.761,1.761,0,0,1-1.76-1.521c-.7-4.413-1.579-6.859-3.051-8.444-1.45-1.562-3.692-2.5-7.738-3.253a1.8,1.8,0,0,1,0-3.543c4.044-.755,6.285-1.7,7.733-3.266,1.47-1.588,2.35-4.036,3.039-8.45a1.779,1.779,0,0,1,3.52,0c.7,4.413,1.58,6.86,3.053,8.445,1.451,1.562,3.692,2.5,7.738,3.253a1.8,1.8,0,0,1,0,3.543c-4.044.755-6.284,1.7-7.732,3.265-1.47,1.587-2.351,4.035-3.04,8.449A1.761,1.761,0,0,1,13.009,250Zm-11.2-14.99c3.9.813,6.214,1.9,7.851,3.664,1.605,1.728,2.6,4.157,3.345,8.216.736-4.061,1.731-6.49,3.334-8.222,1.634-1.764,3.945-2.856,7.844-3.676-3.9-.814-6.213-1.9-7.85-3.665-1.606-1.729-2.6-4.158-3.347-8.218-.735,4.061-1.73,6.491-3.333,8.223C8.024,233.1,5.713,234.19,1.813,235.01Z"
								transform="translate(0 -221)"
								fill="#323232"
							/>
						</g>
					</g>
				</svg>
			</i>
<?php if($img) : ?>
	<span class="item-figure">
		<img src="<?php echo $img; ?>" alt=""/>
	</span>
<?php endif; ?>
			<span class="item-textarea"><?php echo $this->h($v); ?></span>
		</span>
	</label>
</li>
<?php
			}
			$tmp = ob_get_contents();
			ob_end_clean();
			$data['data'][$key] = $tmp;
		}

		$key = 'your-method';
		$tmp = "";
		if(!isset($data['data'][$key]) || !$data['data'][$key]) { $data['data'][$key] = 'メール'; }
		$i = 0;
		foreach((array)$this->param[$key] as $k => $v) {
			$i++;
			$sel = "";
			if(isset($data['data'][$key]) && $data['data'][$key] == $k) { $sel = 'checked="checked"'; }
			$tmp .= '<div class="form-main-radio_wrapper">
			<input
				id="'.$this->h($key.sprintf('%02d', $i)).'"
				type="radio"
				name="your-method"
				class="js-required"
				value="'.$this->h($k).'" 
				'.$sel.'
			/>
			<label for="'.$this->h($key.sprintf('%02d', $i)).'">'.$this->h($v).'</label>
		</div>';
		}
		$data['data'][$key] = $tmp;

		$key = 'your-check';
		$tmp = "";

			$sel = "";
			if(isset($data['data'][$key]) && $data['data'][$key] == 1) { $sel = 'checked="checked"'; }
			$tmp .= '<input
							type="checkbox"
							name="your-check"
							id="check-confirm"
							class="js-check-confirm" 
							value="1" 
							'.$sel.'
						/>';
		$data['data'][$key] = $tmp;


		return $data;
	}

	/**
	 * 確認画面用成形
	 */
	public function makeConfirm($data) {

		$data['data'] = $this->h($data['data']);

		$keys = array('house_type', 'house_type_building', 'house_age', 'house_reform_all', 'house_reform_water', 'house_reform_out', 'house_reform_room', 'house_reform_etc', 'house_budget', 'house_construction');
		foreach((array)$keys as $key) {
			if($this->param[$key.'_type'] == "checkbox") {
				if(isset($data['data'][$key]) && is_array($data['data'][$key])) {
					$data['data'][$key] = implode("、", $data['data'][$key]);
				}
			}
		}
		$keys = array('house_request');
		foreach((array)$keys as $key) {
			if($this->param[$key.'_type'] == "checkbox") {
				if(isset($data['data'][$key]) && is_array($data['data'][$key])) {
					$data['data'][$key] = implode("<br>", $data['data'][$key]);
				}
			}
		}

		return $data;
	}


	/**
	 * メール用成形
	 */
	public function makeMailBody($data) {

		$data['data'] = $this->h($data['data']);

		$keys = array('house_type', 'house_type_building', 'house_age', 'house_reform_all', 'house_reform_water', 'house_reform_out', 'house_reform_room', 'house_reform_etc', 'house_budget', 'house_construction');
		foreach((array)$keys as $key) {
			if($this->param[$key.'_type'] == "checkbox") {
				if(isset($data['data'][$key]) && is_array($data['data'][$key])) {
					$data['data'][$key] = implode("、", $data['data'][$key]);
				}
			}
		}
		$keys = array('house_request');
		foreach((array)$keys as $key) {
			if($this->param[$key.'_type'] == "checkbox") {
				if(isset($data['data'][$key]) && is_array($data['data'][$key])) {
					$data['data'][$key] = implode("\n", $data['data'][$key]);
				}
			}
		}

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

		$msg = mb_convert_encoding($msg, $charset, "AUTO");

		$from = array("name" => $this->param['from_name'], "mail" => $this->param['from']);

		$headers = "Mime-Version: 1.0\n";
		$headers .= "Content-Type: multipart/mixed; boundary=\"boundary\"\n";
		// $headers .= "From: ".mb_encode_mimeheader(mb_convert_encoding($from["name"], $charset, "AUTO"))." <".$from["mail"].">";
		$headers .= "From: ".mb_encode_mimeheader($from["name"])."<".$from["mail"].">";

		if($data) {
			$excelData = $this->makeXlsx($data);
			$attachment = chunk_split(base64_encode($excelData));

			$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
			$filename = $now->format('Y-m-d_H-i-s').'.xlsx';
		}

		// メールの本文と添付ファイルを作成
		$message = "--boundary\r\n";
		$message .= "Content-Type: text/plain; charset=$charset\r\n";
		$message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$message .= $msg . "\r\n";
		$message .= "--boundary\r\n";
		$message .= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"$filename\"\r\n";
		$message .= "Content-Transfer-Encoding: base64\r\n";
		$message .= "Content-Disposition: attachment; filename=\"$filename\"\r\n\r\n";
		$message .= $attachment . "\r\n";
		$message .= "--boundary--";

		mb_send_mail($to, $subject, $message, $headers);

		// ユーザー宛（自動返信）
		if ($this->param['autores']) {
			$to = $data['data']['your-mail'];
			if ($to) {
				$subject = $this->param['subject']['user'];
				$msg = file_get_contents($this->param['tpl_path'].$this->param['tpl']['mail']);

				foreach ($maildata['data'] as $k => $v) {
					if (is_array($v)) {
							continue;
					}
					$msg = str_replace("<!-- ".$k." -->", $this->d_h($this->mb_wordwrap($v, 250)), $msg);
				}

				$msg = mb_convert_encoding($msg, $charset, "AUTO");

				$headers = "Mime-Version: 1.0\n";
				$headers .= "Content-Transfer-Encoding: 7bit\n";
				$headers .= "Content-Type: text/plain;charset={$charset}\n";
				$headers .= "From: ".mb_encode_mimeheader($from["name"])."<".$from["mail"].">";

				mb_send_mail($to, $subject, $msg, $headers);
			}
		}

		// メーカー宛
		foreach((array)$data['data']['house_request'] as $req) {
			if(!$req) {
				continue;
			}

			if(!isset($this->param['house_request_mail'][$req]) || !$this->param['house_request_mail'][$req]) {
				continue;
			}

			$to = $this->param['house_request_mail'][$req];
			if ($to) {
				$subject = $this->param['subject']['admin'];
				$msg = file_get_contents($this->param['tpl_path'].$this->param['tpl']['groupmail']);
	
				foreach ($maildata['data'] as $k => $v) {
					if (is_array($v)) {
							continue;
					}
					$msg = str_replace("<!-- ".$k." -->", $this->d_h($this->mb_wordwrap($v, 250)), $msg);
				}
	
				$msg = mb_convert_encoding($msg, $charset, "AUTO");
	
				$headers = "Mime-Version: 1.0\n";
				$headers .= "Content-Transfer-Encoding: 7bit\n";
				$headers .= "Content-Type: text/plain;charset={$charset}\n";
				$headers .= "From: ".mb_encode_mimeheader($from["name"])."<".$from["mail"].">";
	
				mb_send_mail($to, $subject, $msg, $headers);
			}
		}

	}

	private function makeXlsx($data) {
		
		$temp_file = dirname(__FILE__).'/../tpl/template.xlsx';

		// テンプレートファイルを読み込み
		// $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($temp_file);
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();

		$items = array(
			'date' => "日時",
			'your-name' => "お名前",
			'your-kana' => "フリガナ",
			'your-mail' => "メールアドレス",
			'your-tel' => "電話番号",
			'your-method' => "希望連絡方法",
			'your-post' => "郵便番号",
			'your-address' => "住所",
			'house_type' => "タイプ",
			'house_type_building' => "タイプ2",
			'house_age' => "築年数",
			'house_budget' => "予算",
			'house_construction' => "着工時期",
			'house_request' => "見積もり依頼先"
		);

		// 見出し
		$cells[0] = array();
		foreach((array)$items as$key => $item) {
			$cells[0][] = $item;
		}
		$reforms = array('all', 'water', 'out', 'room', 'etc');
		foreach((array)$reforms as $reform) {
			foreach((array)$this->param['house_reform_'.$reform] as $key => $item) {
				$cells[0][] = $key;
			}
		}

		// 共通の値
		$now = new Datetime();
		$now_f = $now->format('Y-m-d H:i:s');
		$values = array();
		foreach((array)$items as $key => $item) {
			if($key == 'date') {
				$values[$key] = $now_f;
			} else if($key == 'house_request') {
				$values[$key] = "";
			} else {
				$values[$key] = $data['data'][$key];
			}
		}
		$reforms = array('all', 'water', 'out', 'room', 'etc');
		foreach((array)$reforms as $reform) {
			foreach((array)$this->param['house_reform_'.$reform] as $key => $item) {
				if($data['data']['house_reform_'.$reform] && in_array($key, $data['data']['house_reform_'.$reform])) {
					$values[$key] = "〇";
				} else {
					$values[$key] = "";
				}
			}
		}

		$i = 1;
		foreach((array)$data['data']['house_request'] as $req) {
			$values['house_request'] = $req;
			$cells[$i] = $values;
			$i++;
		}

		$sheet->fromArray($cells, null, 'A1');

		// セル書式を文字列に
		// $sheet -> getStyle("A1:Z4") -> getNumberFormat() ->setFormatCode(
		// 	\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
		// );

		$spreadsheet->setActiveSheetIndex(0); //シートを先頭に合わせる
		$spreadsheet->getSheet(0) -> unfreezePane('A1'); //アドレスA1に合わせる

		// メモリ上に保存
		$writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
		ob_start();
		$writer->save('php://output');
		$excelData = ob_get_contents();
		ob_end_clean();

		return $excelData;
	}


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

		$key = 'house_type';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key] || !in_array($data[$key], $this->param[$key])) {
			$this->error[$key] = '選択してください';
		}

		$key = 'house_type_building';
		$this->error[$key] = "";
		if($data['house_type'] == "戸建住宅") {
			if(!isset($data[$key]) || !$data[$key] || !in_array($data[$key], $this->param[$key])) {
				$this->error[$key] = '選択してください';
			}
		}

		$key = 'house_age';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key] || !in_array($data[$key], $this->param[$key])) {
			$this->error[$key] = '選択してください';
		}

		$keys = array(
			'house_reform_all',
			'house_reform_water',
			'house_reform_out',
			'house_reform_room',
			'house_reform_etc',	
		);

		$flg = false;
		foreach((array)$keys as $key) {
			$this->error[$key] = "";
			if(isset($data[$key]) && !empty($data[$key])) {
				$items = array_keys($this->param[$key]);
				foreach((array)$data[$key] as $i => $v) {
					if(!in_array($v, $items)) {
						unset($data[$key][$i]);
					}
				}
				if(!empty($data[$key])) {
					$flg = true;
				}
			}
		}
		if(!$flg) {
			$this->error[$keys[0]] = '選択してください';
		}

		$key = 'house_budget';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key] || !in_array($data[$key], $this->param[$key])) {
			$this->error[$key] = '選択してください';
		}

		$key = 'house_construction';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !$data[$key] || !in_array($data[$key], $this->param[$key])) {
			$this->error[$key] = '選択してください';
		}

		$key = 'house_request';
		$this->error[$key] = "";
		if(!isset($data[$key]) || !is_array($data[$key])) {
			$this->error[$key] = '選択してください';
		} else {
			$items = array_keys($this->param[$key]);
			foreach((array)$data[$key] as $i => $v) {
				if(!in_array($v, $items)) {
					unset($data[$key][$i]);
				}
			}
			if(empty($data[$key])) {
				$this->error[$keys] = '選択してください';
			} else if(count($data[$key]) > 3) {
				$this->error[$keys] = '最大3件までです';
			}
		}

		$key = "your-name";
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->strLength($data[$key], 0, 120)) {
			$this->error[$key] = '入力内容を確認してください';
		}

		$key = "your-kana";
		$data[$key] = $this->convertKana($data[$key]);
		$this->error[$key] = "";
		if(!$data[$key]) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isKana($data[$key])) {
			$this->error[$key] = 'カタカナで入力してください';
		} elseif(!$this->strLength($data[$key], 0, 120)) {
			$this->error[$key] = '入力内容を確認してください';
		}

		// 郵便番号
		$key = "your-post";
		$data[$key] = $this->convertAlphaNum($data[$key]);
		// $data[$key] = $this->removeHyphen($data[$key]);
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isZipNoH($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		}

		// 住所
		$key = "your-address";
		$data[$key] = $this->convertAlphaNum($data[$key]);
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->strLength($data[$key], 0, 200)) {
			$this->error[$key] = '入力内容を確認してください';
		}

		// 電話番号
		$key = "your-tel";
		$data[$key] = $this->convertAlphaNum($data[$key]);
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isTelNoH($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		}

		// メールアドレス
		$key = "your-mail";
		$data[$key] = $this->convertAlphaNum($data[$key]);
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isMail($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		}

		$key = "your-confirm";
		$data[$key] = $this->convertAlphaNum($data[$key]);
		$this->error[$key] = "";
		if(!isset($data[$key]) || !trim(mb_convert_kana($data[$key], "s", 'UTF-8'))) {
			$this->error[$key] = '入力してください';
		} elseif(!$this->isMail($data[$key])) {
			$this->error[$key] = '入力内容を確認してください';
		} elseif($data[$key] != $data['your-mail']) {
			$this->error[$key] = '入力内容を確認してください';
		}

		if($this->arrayFilterRecursive($this->error)) {
			$this->error['consecutive'] = '<p class="error consecutive_error">入力エラーがあります。</p>';
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
		$str = mb_ereg_replace("-", "", $str);
		$str = mb_ereg_replace("ー", "", $str);
		$str = mb_ereg_replace("－", "", $str);

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
		$str = mb_ereg_replace("-", "", $str);
		$str = mb_ereg_replace("ー", "", $str);
		$str = mb_ereg_replace("－", "", $str);

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