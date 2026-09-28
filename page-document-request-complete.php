<?php
/**
 * Template Name: 資料請求 完了
 *
 * 資料請求の完了画面（Notion「資料請求フォーム 実装指示書」§5）。
 * ?token=… を受け、PDF即DLボタン（?boa_pdf_download=token）と説明会予約CTAを表示。
 *
 * テンプレート適用：/document-request/complete/（document-request の子ページ）に割り当て。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- トークンは認可キー。
$valid = false;
if ( '' !== $token ) {
	global $wpdb;
	$table = bankofart_doc_request_table();
	$valid = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE pdf_download_token = %s", $token ) ); // phpcs:ignore WordPress.DB
}
$download_url = $valid ? add_query_arg( 'boa_pdf_download', rawurlencode( $token ), home_url( '/' ) ) : '';
$briefing_url = bankofart_briefing_url(); // 説明会予約（現状は外部URL。後で自前ページに差し替え予定）。
$art_archive  = bankofart_collection_arts_url();
?>

<main id="main" class="site-main boa-form-page document-request-complete">

	<section class="boa-form-section">
		<div class="boa-complete">
			<div class="boa-complete-check" aria-hidden="true">✓</div>
			<div class="boa-form-eyebrow">Thank you</div>
			<h1 class="boa-form-title">資料請求ありがとうございました</h1>
			<p class="boa-complete-lead">
				ご登録のメールアドレスに、資料ダウンロードリンクをお送りしました。<br class="br-pc">
				数分以内に届かない場合は、迷惑メールフォルダもご確認ください。
			</p>

			<?php if ( $download_url ) : ?>
				<div class="boa-form-actions">
					<a href="<?php echo esc_url( $download_url ); ?>" class="boa-submit">資料を今すぐダウンロード</a>
				</div>
			<?php endif; ?>

			<hr class="boa-section-divider">

			<p class="boa-complete-lead">資料を見てさらに知りたい方は、オンライン説明会へ。<br class="br-pc">30分程度で、貴社の課題に合わせた活用方法をご提案いたします。</p>
			<div class="boa-form-actions">
				<!-- 説明会予約ページは後で自前実装。現状は /online-briefing/（ヘルパー）への導線 -->
				<a href="<?php echo esc_url( $briefing_url ); ?>" target="_blank" rel="noopener" class="boa-btn-outline">オンライン説明会を予約する</a>
				<a href="<?php echo esc_url( $art_archive ); ?>" class="boa-btn-outline" target="_blank" rel="noopener">作品一覧を見る</a>
			</div>

			<p class="boa-note">ご不明点は、メールへのご返信またはお問い合わせフォームよりお気軽にどうぞ。</p>
		</div>
	</section>

</main>

<?php
get_footer();
