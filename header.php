<?php
/**
 * テーマヘッダー
 *
 * ドキュメントの <head> を出力し、<body> を開いて共通ヘッダーパーツを読み込む。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class( is_front_page() ? 'boa-loading' : '' ); ?>>
<?php wp_body_open(); ?>

<?php
/*
 * ファーストビューのローディング画面（TOPページのみ）。
 * ヒーロー画像が読み込まれる前に生の状態が見えないよう、最前面で覆う。
 * 下層ページにも出すとページ遷移のたびに挟まってくどいため、is_front_page() に限定する。
 * 解除は assets/js/preloader.js（画像読込完了／window load／安全弁タイマー）。
 * JS 無効環境では <noscript> で丸ごと無効化し、画面が固まらないようにする。
 */
if ( is_front_page() ) :
	?>
	<div class="boa-preloader" id="boaPreloader" role="status" aria-live="polite" aria-label="<?php esc_attr_e( '読み込み中', 'bankofart' ); ?>">
		<img class="boa-preloader-logo" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logo/boa-11.png' ) ); ?>" alt="" width="130" height="130" fetchpriority="high" decoding="async">
		<div class="boa-preloader-text">now loading</div>
	</div>
	<noscript>
		<style>.boa-preloader { display: none; } body.boa-loading { overflow: auto; }</style>
	</noscript>
<?php endif; ?>

<a class="skip-link visually-hidden" href="#main"><?php esc_html_e( 'コンテンツへスキップ', 'bankofart' ); ?></a>

<?php get_template_part( 'template-parts/header', 'main' ); ?>
