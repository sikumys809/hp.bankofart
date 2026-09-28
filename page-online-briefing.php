<?php
/**
 * Template Name: オンライン説明会予約
 *
 * オンライン説明会予約ページ（Notion「オンライン説明会予約システム 実装指示書」§4）。
 * フェーズ1：Google連携なし。Calendly風3ステップ（日付→時間→フォーム→確認→確定）。
 * 空きスロットは admin-ajax（availability.php・DB既存予約のみ）、確定は admin-post（form-handler.php）。
 *
 * テンプレート適用：スラッグ "online-briefing" の固定ページ、または本テンプレート選択ページ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// 完了画面（?ob_thanks=KEY）。
$thanks = false;
$thanks_dt = '';
if ( isset( $_GET['ob_thanks'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tk    = sanitize_text_field( wp_unslash( $_GET['ob_thanks'] ) );
	$stash = get_transient( $tk );
	if ( is_array( $stash ) ) {
		$thanks    = true;
		$thanks_dt = bankofart_booking_format_datetime( $stash['booked_at'] );
		delete_transient( $tk );
	}
}

// エラーメッセージ（?ob_error=CODE）。
$err = '';
if ( isset( $_GET['ob_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$code = sanitize_text_field( wp_unslash( $_GET['ob_error'] ) );
	$map  = array(
		'taken'      => '申し訳ございません。その時間枠は埋まりました。別の時間をお選びください。',
		'duplicate'  => '同じ日時のご予約が既に承られています。',
		'validation' => '入力内容をご確認ください。',
		'rate_limit' => '短時間に複数回の操作がありました。しばらくおいて再度お試しください。',
		'recaptcha'  => '送信を確認できませんでした。再度お試しください。',
	);
	$err = isset( $map[ $code ] ) ? $map[ $code ] : '';
}

$recaptcha_key = defined( 'BANKOFART_RECAPTCHA_SITE_KEY' ) ? constant( 'BANKOFART_RECAPTCHA_SITE_KEY' ) : '';
?>

<main id="main" class="site-main boa-form-page online-briefing-page">

<?php if ( $thanks ) : ?>

	<section class="boa-form-section">
		<div class="boa-complete">
			<div class="boa-complete-check" aria-hidden="true">✓</div>
			<div class="boa-form-eyebrow">Reserved</div>
			<h1 class="boa-form-title">ご予約を承りました</h1>
			<p class="boa-complete-lead">
				下記日時でオンライン説明会のご予約を承りました。<br class="br-pc">
				確認メールをお送りしましたのでご確認ください。
			</p>
			<div class="boa-target" style="justify-content:center;">
				<span class="boa-target-label">ご予約日時</span>
				<span class="boa-target-value"><?php echo esc_html( $thanks_dt ); ?></span>
			</div>
			<p class="boa-note">
				※ オンライン会議URLは、追って担当者よりご案内いたします。<br class="br-pc">
				（カレンダー追加（.ics）・Meet URL の自動発行は今後対応予定です）
			</p>
			<div class="boa-form-actions">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="boa-btn-outline">トップへ</a>
				<a href="<?php echo esc_url( bankofart_collection_arts_url() ); ?>" class="boa-btn-outline" target="_blank" rel="noopener">作品一覧を見る</a>
			</div>
		</div>
	</section>

<?php else : ?>

	<section class="ob-section">
		<div class="ob-layout">

			<!-- 上：説明（横長コンパクト） -->
			<div class="ob-intro">
				<div class="ob-intro-text">
					<div class="boa-form-eyebrow">ONLINE BRIEFING</div>
					<h1 class="ob-title">オンライン説明会<span class="ob-duration"><span class="ob-duration-num boa-num">30</span> 分</span></h1>
					<p class="ob-lead">Bank of Art のサービスを、貴社の課題に合わせてご説明します。<br class="br-pc">即時償却・画家支援・アート活用について、オンラインでお気軽にご相談ください。</p>
				</div>
				<dl class="ob-meta">
					<div class="ob-meta-item"><dt>所要時間</dt><dd>約30分</dd></div>
					<div class="ob-meta-item"><dt>形式</dt><dd>オンライン（ビデオ会議）</dd></div>
					<div class="ob-meta-item"><dt>担当</dt><dd>株式会社シクミーズ / BANK OF ART</dd></div>
				</dl>
			</div>

			<!-- 下：カレンダー全幅ウィザード -->
			<div class="ob-wizard">
				<ol class="ob-steps" aria-hidden="true">
					<li class="is-current" data-step="1">日時を選ぶ</li>
					<li data-step="2">情報入力</li>
					<li data-step="3">確認</li>
				</ol>

				<?php if ( '' !== $err ) : ?>
					<div class="boa-form-errors" role="alert"><p><?php echo esc_html( $err ); ?></p></div>
				<?php endif; ?>

				<form class="boa-form ob-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ob-form">
					<input type="hidden" name="action" value="boa_booking_reserve">
					<?php wp_nonce_field( 'boa_booking_reserve', 'boa_booking_nonce' ); ?>
					<div class="boa-honeypot" aria-hidden="true"><label>Website<input type="text" name="website_hp" tabindex="-1" autocomplete="off"></label></div>
					<?php if ( $recaptcha_key ) : ?><input type="hidden" name="g-recaptcha-response" id="ob-recaptcha-response" value=""><?php endif; ?>
					<input type="hidden" name="booked_at" id="ob-booked-at" value="">

					<!-- Step1 日時（週表示グリッド：横=日付7日 × 縦=時間） -->
					<div class="ob-step is-active" data-panel="1">
						<h2 class="ob-step-h">日時を選ぶ</h2>
						<div class="ob-week">
							<div class="ob-week-head">
								<button type="button" class="ob-week-nav" id="ob-week-prev">‹ 前の週</button>
								<span class="ob-week-range" id="ob-week-range"></span>
								<button type="button" class="ob-week-nav" id="ob-week-next">次の週 ›</button>
							</div>
							<div class="ob-week-scroll">
								<div class="ob-week-cols" id="ob-week-cols"><p class="ob-slots-loading">読み込み中…</p></div>
							</div>
							<p class="ob-week-hint">空いている時間枠をタップすると、お客様情報の入力に進みます。</p>
						</div>

						<!-- モバイル用：月カレンダー（〇△✕）。PCでは CSS で非表示（週グリッドのまま） -->
						<div class="ob-month" id="ob-month">
							<div class="ob-month-head">
								<button type="button" class="ob-week-nav" id="ob-month-prev">‹ 前月</button>
								<span class="ob-week-range" id="ob-month-label"></span>
								<button type="button" class="ob-week-nav" id="ob-month-next">翌月 ›</button>
							</div>
							<div class="ob-month-grid" id="ob-month-grid"><p class="ob-slots-loading">読み込み中…</p></div>
							<p class="ob-month-legend"><span class="ob-cal-mark is-o">〇</span> 空きあり　<span class="ob-cal-mark is-tri">△</span> 残りわずか　<span class="ob-cal-mark is-x">✕</span> 満席</p>
							<div class="ob-month-day" id="ob-month-day" hidden>
								<div class="ob-month-day-label" id="ob-month-day-label"></div>
								<div class="ob-day-slots" id="ob-month-slots"></div>
							</div>
						</div>
					</div>

					<!-- Step2 フォーム -->
					<div class="ob-step" data-panel="2">
						<button type="button" class="ob-back" data-back="1">‹ 日時に戻る</button>
						<h2 class="ob-step-h">お客様情報</h2>
						<div class="boa-target"><span class="boa-target-label">ご予約日時</span><span class="boa-target-value" id="ob-dt-label"></span></div>
						<div class="boa-field">
							<label class="boa-label" for="ob-name">お名前 <span class="boa-required">必須</span></label>
							<input type="text" id="ob-name" name="name" class="boa-input" maxlength="100" required>
						</div>
						<div class="boa-field">
							<label class="boa-label" for="ob-company">会社名 <span class="boa-required">必須</span></label>
							<input type="text" id="ob-company" name="company" class="boa-input" maxlength="200" placeholder="個人の場合は「個人」とご記入ください" required>
						</div>
						<div class="boa-field">
							<label class="boa-label" for="ob-email">メールアドレス <span class="boa-required">必須</span></label>
							<input type="email" id="ob-email" name="email" class="boa-input" maxlength="255" required>
						</div>
						<div class="boa-field">
							<label class="boa-label" for="ob-phone">電話番号 <span class="boa-required">必須</span></label>
							<input type="tel" id="ob-phone" name="phone" class="boa-input" maxlength="20" placeholder="ハイフンなし可" required>
						</div>
						<div class="boa-field">
							<label class="boa-label" for="ob-purpose">ご目的 <span class="boa-required">必須</span></label>
							<select id="ob-purpose" name="purpose" class="boa-select" required>
								<option value="">選択してください</option>
								<?php foreach ( bankofart_booking_purposes() as $p ) : ?>
									<option value="<?php echo esc_attr( $p ); ?>"><?php echo esc_html( $p ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="ob-form-msg" id="ob-form-msg" role="alert"></div>
						<div class="boa-form-actions"><button type="button" class="boa-submit" id="ob-to-confirm">確認画面へ</button></div>
					</div>

					<!-- Step3 確認 -->
					<div class="ob-step" data-panel="3">
						<button type="button" class="ob-back" data-back="2">‹ 入力に戻る</button>
						<h2 class="ob-step-h">ご予約内容の確認</h2>
						<dl class="ob-confirm" id="ob-confirm"></dl>
						<p class="boa-note">この内容で予約を確定します。オンライン会議URLは追ってご案内いたします。</p>
						<div class="boa-form-actions"><button type="submit" class="boa-submit" id="ob-submit">予約を確定する</button></div>
					</div>
				</form>
			</div>
		</div>
	</section>

<?php endif; ?>

</main>

<?php
get_footer();
