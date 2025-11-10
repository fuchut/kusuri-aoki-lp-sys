<section class="lp-column-center">
	<div class="lp-column-center__inner">
		<!-- 応募フォーム確認画面 -->
		<section class="lp-confirm-section bg-red">
			<div class="lp-confirm-section__inner">
				<h1 class="lp-confirm-section__title">
					<div class="lp-confirm-section__title-main">応募フォーム</div>
					<div class="lp-confirm-section__title-sub">申し込み内容確認画面</div>
				</h1>
				<div class="lp-confirm-section__instruction">
					<p>内容を確認の上、送信ボタンを押してください。</p>
				</div>
				
				<div class="lp-confirm-section__content">
					<div class="lp-confirm-section__table">
						<div class="lp-confirm-section__table-row">
							<div class="lp-confirm-section__table-label">Aoca番号</div>
							<div class="lp-confirm-section__table-value"><!-- member_id --></div>
						</div>
						<div class="lp-confirm-section__table-row">
							<div class="lp-confirm-section__table-label">応募景品</div>
							<div class="lp-confirm-section__table-value"><!-- present --></div>
						</div>
						<div class="lp-confirm-section__table-row">
							<div class="lp-confirm-section__table-label">メールアドレス</div>
							<div class="lp-confirm-section__table-value"><!-- email --></div>
						</div>
					</div>
					
					<div class="lp-confirm-section__attention">
						<p>※ドメイン拒否を設定されている場合、確認メールが届きません。当選メールも届かないため、「cp2025-kusuri-aoki.com」のドメインを受信できるように設定をお願いします。</p>
					</div>
					
					<div class="lp-confirm-section__buttons">
						<form	action=""	method="post">
							<input type="hidden" name="mode" value="send">
							<button type="submit" class="lp-confirm-section__button lp-confirm-section__button-submit"><img src="/assets/img/form-submit.png" alt="送信する" width="300" height="56"></button>
						</form>
						<form	action="#application-form"	method="post">
							<input type="hidden" name="mode" value="return">
							<button type="submit" class="lp-confirm-section__button lp-confirm-section__button-edit"><img src="/assets/img/form-return.png" alt="修正する" width="296" height="45"></button>
						</form>
					</div>
				</div>
			</div>
		</section>
		
	</div>
</section>
