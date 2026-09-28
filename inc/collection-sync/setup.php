<?php
/**
 * コレクションサイト同期：テーブル・定数
 *
 * collection.bankof-art.com（Next.js）を作品データの正とし、その写しをWPに保存する。
 * WP側の art 投稿とは作品名で突き合わせない（WPの作品名は正式名称ではないため）。
 * 両者をつなぐ鍵は「アーティストのスラッグ」だけで、これは両サイトで一致している。
 *
 * 取得はサーバーサイドレンダリング済みHTMLの解析で行う（wp_remote_get のみ。JS実行不要）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** コレクションサイトのURL（末尾スラッシュなし）。 */
if ( ! defined( 'BANKOFART_COLLECTION_URL' ) ) {
	define( 'BANKOFART_COLLECTION_URL', 'https://collection.bankof-art.com' );
}

/** DBスキーマのバージョン（変更時に上げると dbDelta が再実行される）。 */
define( 'BANKOFART_COLLECTION_DB_VERSION', '1.3.0' );

/**
 * 同期テーブル名を返す。
 *
 * @return string
 */
function bankofart_collection_table() {
	global $wpdb;
	return $wpdb->prefix . 'boa_collection_arts';
}

/**
 * テーブルを作成／更新する。
 *
 * collection_id（BOA-2026-00661）を主キーにして、再取得時は同じ行を上書きする。
 * artist_slug に索引を張るのは、アーティストページ・診断で「この画家の作品」を引くため。
 *
 * 切り抜き関連の列は2系統ある。
 *   crop_quad            … 手作業で指定された4点（自由変形）。これが本命で、
 *                           これを使って作品を起こした画像を自前で生成する。
 *   crop_left〜crop_height … 上記4点の外接長方形。collection の一覧カードが
 *                           使っている簡易版で、GDが無い環境での控えとして残す。
 *
 * @return void
 */
function bankofart_collection_create_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = bankofart_collection_table();
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
  collection_id VARCHAR(32) NOT NULL,
  artist_slug VARCHAR(100) NOT NULL DEFAULT '',
  title TEXT NULL,
  size_label VARCHAR(50) NULL,
  price VARCHAR(50) NULL,
  image_url TEXT NULL,
  is_new TINYINT(1) NOT NULL DEFAULT 0,
  is_sold TINYINT(1) NOT NULL DEFAULT 0,
  crop_ratio DECIMAL(10,6) NULL,
  crop_left DECIMAL(10,4) NULL,
  crop_top DECIMAL(10,4) NULL,
  crop_width DECIMAL(10,4) NULL,
  crop_height DECIMAL(10,4) NULL,
  source_url TEXT NULL,
  crop_quad VARCHAR(200) NULL,
  rect_key VARCHAR(32) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  synced_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (collection_id),
  KEY idx_artist (artist_slug),
  KEY idx_sort (artist_slug, sort_order)
) {$charset};";

	dbDelta( $sql );
	update_option( 'boa_collection_db_version', BANKOFART_COLLECTION_DB_VERSION );
}
add_action( 'after_switch_theme', 'bankofart_collection_create_table' );

/**
 * スキーマ版が古ければ作成／更新を流す（テーマ更新時の取りこぼし対策）。
 *
 * @return void
 */
function bankofart_collection_maybe_upgrade() {
	if ( get_option( 'boa_collection_db_version' ) !== BANKOFART_COLLECTION_DB_VERSION ) {
		bankofart_collection_create_table();
	}
}
add_action( 'admin_init', 'bankofart_collection_maybe_upgrade' );
