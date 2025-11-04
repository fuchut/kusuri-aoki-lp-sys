<div class="">
	<!-- 確認画面-->
	<section id="js-form-confirm"	class="page-inner common-inner">
		<h2 class="section-title">
			<span> お見積もりフォーム </span>
		</h2>
		<div class="form-contents">
			<h3 class="form-question">確認画面</h3>
			<span class="form-question-sub">入力内容に間違いがないかご確認ください。</span>
		</div>

		<div class="form-cofirm-question">
			<div class="form-cofirm-question_wrapper">
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q1.まずはお住まいのタイプをお選びください
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_type -->
					</div>
				</div>
				<div class="form-cofirm-question-box js-house-building-question">
					<div class="form-confirm-question_title">
						Q1-1.「戸建住宅」を選択した方は、お住まいの建築材料を選択ください
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_type_building -->
					</div>
				</div>
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q2.お住まいの築年数をお選びください
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_age -->
					</div>
				</div>
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q3.リフォーム箇所をお選びください（複数選択可）
					</div>
					<div class="form-confirm-question_chekclist">
						<strong>◎全体</strong><br>
						<!-- house_reform_all --><br>
						<br>

						<strong>◎水まわり</strong><br>
						<!-- house_reform_water --><br>
						<br>

						<strong>◎外まわり</strong><br>
						<!-- house_reform_out --><br>
						<br>

						<strong>◎居間</strong><br>
						<!-- house_reform_room --><br>
						<br>

						<strong>◎その他</strong><br>
						<!-- house_reform_etc --><br>
						<br>
					</div>
				</div>
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q4.予算をお選びください
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_budget -->
					</div>
				</div>
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q5.着工時期をお選びください
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_construction -->
					</div>
				</div>
				<div class="form-cofirm-question-box">
					<div class="form-confirm-question_title">
						Q6.見積もり依頼先をお選びください（3社まで）
					</div>
					<div class="form-confirm-question_chekclist">
						<!-- house_request -->
					</div>
				</div>
			</div>
			<div class="form-main-container">
				<div class="form-main-box">
					<div class="form-main-title">
						お名前<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-name"><!-- your-name --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						フリガナ<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-kana"><!-- your-kana --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						メールアドレス<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-mail"><!-- your-mail --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						電話場号<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-tel"><!-- your-tel --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						希望連絡方法<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-method"><!-- your-method --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						郵便番号<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-post"><!-- your-post --></div>
				</div>
				<div class="form-main-box">
					<div class="form-main-title">
						住所<span class="form-required">*</span>
					</div>
					<div class="form-main-confirm" id="confirm-address"><!-- your-address --></div>
				</div>
			</div>
		</div>

		<div class="form-button-wrapper">
			<form	action=""	method="post"	class="form-wrapper">
				<input type="hidden" name="mode" value="send">
				<button class="form-button" type="submit">送信</button>
			</form>
			<form	action=""	method="post"	class="form-wrapper">
				<input type="hidden" name="mode" value="return">
				<button class="form-return">
					<span>戻る</span>
				</button>
			</form>
		</div>
	</section>
</div>