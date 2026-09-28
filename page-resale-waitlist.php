<?php
/**
 * Template Name: リセール待機リスト登録
 *
 * リセール待機リストの登録フォーム＋完了画面（Notion「リセール待機リスト機能 実装指示書」タスク2・4）。
 * 送信処理・DB保存・メール・管理画面は inc/resale-waitlist.php。
 *
 * 読み替え：CPT artwork → art。?artwork_id=（art投稿ID）を absint で受け、投稿タイプ art を確認。
 *   作品名＝post_title、作品番号＝rwmb_meta('art_number')。不正IDは作品欄を手入力にフォールバック。
 *
 * テンプレート適用：スラッグ "resale-waitlist" の固定ページ、または本テンプレート選択ページ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_done = isset( $_GET['resale_done'] ) && '1' === $_GET['resale_done']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示分岐のみ。

// エラー時の入力値・エラー内容（送信処理がトランジェントに退避）。
$err_values = array();
$err_list   = array();
if ( isset( $_GET['resale_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- transientキー参照のみ。
	$err_key   = sanitize_text_field( wp_unslash( $_GET['resale_error'] ) );
	$stash     = get_transient( $err_key );
	if ( is_array( $stash ) ) {
		$err_values = isset( $stash['values'] ) ? $stash['values'] : array();
		$err_list   = isset( $stash['errors'] ) ? $stash['errors'] : array();
		delete_transient( $err_key );
	}
}

// 対象作品の取得（?artwork_id）。不正時は手入力フォールバック。
$artwork_id    = isset( $_GET['artwork_id'] ) ? absint( wp_unslash( $_GET['artwork_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$artwork_title = '';
$artwork_no    = '';
$has_artwork   = false;
if ( $artwork_id ) {
	$art_post = get_post( $artwork_id );
	if ( $art_post && 'art' === $art_post->post_type && 'publish' === $art_post->post_status ) {
		$has_artwork   = true;
		$artwork_title = get_the_title( $art_post );
		$artwork_no    = function_exists( 'rwmb_meta' ) ? (string) rwmb_meta( 'art_number', array(), $artwork_id ) : '';
	}
}
// エラー再表示時は退避値を優先（送信時の作品名・番号を保持）。
if ( ! empty( $err_values ) ) {
	$artwork_title = isset( $err_values['artwork_title'] ) ? $err_values['artwork_title'] : $artwork_title;
	$artwork_no    = isset( $err_values['artwork_number'] ) ? $err_values['artwork_number'] : $artwork_no;
	$artwork_id    = isset( $err_values['artwork_id'] ) ? (int) $err_values['artwork_id'] : $artwork_id;
}

$val = function ( $key ) use ( $err_values ) {
	return isset( $err_values[ $key ] ) ? $err_values[ $key ] : '';
};
$art_archive   = bankofart_collection_arts_url();
$privacy_url   = home_url( '/privacy-policy/' );
?>

<main id="main" class="site-main boa-form-page">

	<section class="boa-form-section">
	<?php if ( $is_done ) : ?>

		<!-- ━━ 完了画面（タスク4）━━ -->
		<div class="boa-complete">
			<div class="boa-form-eyebrow">Thank you</div>
			<h1 class="boa-form-title">ご登録ありがとうございました</h1>
			<p class="boa-complete-lead">
				リセール待機リストへのご登録を受け付けました。<br>
				ご登録の作品がリセールにより入荷した際は、担当者より順次ご案内いたします。
			</p>
			<p class="boa-note">
				※ 本登録は購入のお約束ではなく、入荷時のご案内を目的としたものです。<br class="br-pc">
				ご成約は対面でのご契約となります。
			</p>
			<div class="boa-form-actions">
				<a href="<?php echo esc_url( $art_archive ); ?>" class="boa-btn-outline" target="_blank" rel="noopener">作品一覧へ戻る</a>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="boa-btn-outline">トップへ</a>
			</div>
		</div>

	<?php else : ?>

		<!-- ━━ 登録フォーム（タスク2）━━ -->
		<div class="boa-form-head">
			<div class="boa-form-eyebrow">Resale Waitlist</div>
			<h1 class="boa-form-title">リセール待機リスト登録</h1>
			<p class="boa-form-lead">
				「OWNED（応援企業が所有中）」の作品が、<br class="br-pc"><br class="br-sp">リセールにより入荷した際にご案内します。<br class="br-pc"><br class="br-sp">
				下記フォームよりご登録ください。
			</p>
		</div>

		<?php if ( ! empty( $err_list ) ) : ?>
			<div class="boa-form-errors" role="alert">
				<p>入力内容をご確認ください。</p>
				<ul>
					<?php foreach ( $err_list as $msg ) : ?>
						<li><?php echo esc_html( $msg ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form class="boa-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="boa_resale_waitlist">
			<?php wp_nonce_field( 'boa_resale_waitlist', 'boa_resale_nonce' ); ?>
			<input type="hidden" name="artwork_id" value="<?php echo esc_attr( (string) $artwork_id ); ?>">
			<input type="hidden" name="artwork_number" value="<?php echo esc_attr( $artwork_no ); ?>">

			<?php if ( $has_artwork || '' !== $artwork_title ) : ?>
				<!-- 対象作品が特定できている：作品名は readonly 表示＋hidden 保持 -->
				<div class="boa-target">
					<span class="boa-target-label">希望作品</span>
					<span class="boa-target-value"><?php echo esc_html( $artwork_title ); ?><?php echo '' !== $artwork_no ? ' （' . esc_html( $artwork_no ) . '）' : ''; ?></span>
				</div>
				<input type="hidden" name="artwork_title" value="<?php echo esc_attr( $artwork_title ); ?>">
			<?php else : ?>
				<!-- 作品が特定できない：手入力フォールバック -->
				<div class="boa-field">
					<label class="boa-label" for="artwork_title">希望作品 <span class="boa-required">必須</span></label>
					<input type="text" id="artwork_title" name="artwork_title" class="boa-input" value="<?php echo esc_attr( $artwork_title ); ?>" placeholder="ご希望の作品名" required>
				</div>
			<?php endif; ?>

			<div class="boa-field">
				<label class="boa-label" for="boa_name">お名前 <span class="boa-required">必須</span></label>
				<input type="text" id="boa_name" name="boa_name" class="boa-input" value="<?php echo esc_attr( $val( 'name' ) ); ?>" required>
			</div>

			<div class="boa-field">
				<label class="boa-label" for="boa_company">会社名・屋号 <span class="boa-optional">任意</span></label>
				<input type="text" id="boa_company" name="boa_company" class="boa-input" value="<?php echo esc_attr( $val( 'company' ) ); ?>">
			</div>

			<div class="boa-field">
				<label class="boa-label" for="boa_email">メールアドレス <span class="boa-required">必須</span></label>
				<input type="email" id="boa_email" name="boa_email" class="boa-input" value="<?php echo esc_attr( $val( 'email' ) ); ?>" required>
			</div>

			<div class="boa-field">
				<label class="boa-label" for="boa_tel">電話番号 <span class="boa-optional">任意</span></label>
				<input type="tel" id="boa_tel" name="boa_tel" class="boa-input" value="<?php echo esc_attr( $val( 'tel' ) ); ?>">
			</div>

			<div class="boa-field">
				<label class="boa-label" for="boa_message">ご希望・ご質問 <span class="boa-optional">任意</span></label>
				<textarea id="boa_message" name="boa_message" class="boa-textarea" rows="5"><?php echo esc_textarea( $val( 'message' ) ); ?></textarea>
			</div>

			<div class="boa-field boa-field-check">
				<label class="boa-check">
					<input type="checkbox" name="boa_privacy" value="1">
					<span><a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">個人情報の取り扱い</a>に同意する <span class="boa-required">必須</span></span>
				</label>
			</div>

			<p class="boa-note">
				※ 本登録は購入のお約束ではなく、入荷時のご案内を目的としたものです。ご成約は対面でのご契約となります。
			</p>

			<div class="boa-form-actions">
				<button type="submit" class="boa-submit">この内容で登録する</button>
			</div>
		</form>

	<?php endif; ?>
	</section>

</main>

<?php
get_footer();
