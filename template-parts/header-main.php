<?php
/**
 * 共通ヘッダー本体
 *
 * sticky（fixed）ヘッダー。左：メニューボタン / 中央：ロゴ / 右：CONTACTドロップダウン。
 * メニュー本体は下に展開するドロワー（全幅）。スマホではMENUラベルを隠しアイコンのみ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ロゴ画像（assets/img/logo/ に配置予定）。base が通常、hover がホバー時。
$bankofart_logo_base  = get_theme_file_uri( 'assets/img/logo/boa-07.png' );
$bankofart_logo_hover = get_theme_file_uri( 'assets/img/logo/boa-06.png' );

// CONTACT 系は一元管理ヘルパー経由（資料請求＝/document-request/、説明会予約＝/online-briefing/）。
$bankofart_url_document  = bankofart_document_request_url(); // 自前フォーム /document-request/.
$bankofart_url_briefing  = bankofart_briefing_url();         // 自前予約 /online-briefing/.

/*
 * グローバルナビ項目。英字（大文字）＋日本語の2段表示。
 * CPT/固定ページの正式URLが決まるまで home_url() ベースで出力する。
 */
$bankofart_nav_items = array(
	array(
		'en'  => 'ABOUT',
		'ja'  => 'バンクオブアートとは',
		'url' => home_url( '/about/' ),
	),
	array(
		'en'  => 'ARTIST',
		'ja'  => 'アーティスト一覧',
		'url' => home_url( '/artist/' ),
	),
	array(
		'en'       => 'ART',
		'ja'       => '作品一覧',
		// 作品はコレクションサイトが正。別サイトなので新しいタブで開く。
		'url'      => bankofart_collection_arts_url(),
		'external' => true,
	),
	array(
		'en'  => 'COLLECTOR',
		'ja'  => '画家応援企業',
		'url' => home_url( '/collector/' ),
	),
	array(
		'en'  => 'NEWS',
		'ja'  => '最新記事',
		'url' => home_url( '/news/' ),
	),
	array(
		'en'  => 'JOURNAL',
		'ja'  => '読み物',
		'url' => home_url( '/journal/' ),
	),
);
?>
<header id="site-header" class="site-header">

	<button class="h-menu-btn" id="menuToggle" aria-label="<?php esc_attr_e( 'メニューを開く', 'bankofart' ); ?>" aria-controls="drawer" aria-expanded="false">
		<span class="h-menu-icon" aria-hidden="true"></span>
		<span class="h-menu-label">MENU</span>
	</button>

	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="h-logo" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<img class="logo-base" src="<?php echo esc_url( $bankofart_logo_base ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="240" height="56">
		<img class="logo-hover" src="<?php echo esc_url( $bankofart_logo_hover ); ?>" alt="" aria-hidden="true" width="240" height="56">
	</a>

	<div class="h-actions">
		<div class="contact-dropdown" id="contactDropdown">
			<button class="btn-contact" id="contactToggle" aria-haspopup="true" aria-expanded="false">CONTACT</button>
			<div class="contact-menu">
				<a href="<?php echo esc_url( $bankofart_url_document ); ?>" class="contact-menu-item">資料請求</a>
				<a href="<?php echo esc_url( $bankofart_url_briefing ); ?>" class="contact-menu-item">オンライン説明会</a>
			</div>
		</div>
	</div>

</header>

<nav class="h-drawer" id="drawer" aria-label="<?php esc_attr_e( 'グローバルナビゲーション', 'bankofart' ); ?>">
	<div class="h-drawer-inner">
		<?php foreach ( $bankofart_nav_items as $bankofart_item ) : ?>
			<a href="<?php echo esc_url( $bankofart_item['url'] ); ?>"<?php echo empty( $bankofart_item['external'] ) ? '' : ' target="_blank" rel="noopener"'; ?>>
				<span class="en"><?php echo esc_html( $bankofart_item['en'] ); ?></span>
				<span class="ja"><?php echo esc_html( $bankofart_item['ja'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</nav>
