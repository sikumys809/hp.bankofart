<?php
/**
 * TOPページのART欄に出す作品の選択
 *
 * コレクションサイトの作品から6点を選ぶ（3×2のカード表示）。
 * 選択結果は boa_collection_top_picks（collection_id の配列）に保存する。
 * 作品データ自体は月1同期で更新されるので、ここでは「どれを出すか」だけ持つ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** TOPに並べる点数（3列×2段）。 */
define( 'BANKOFART_TOP_PICK_COUNT', 6 );

/**
 * TOPに出す作品IDの配列を返す（選択順）。
 *
 * @return array collection_id の配列。
 */
function bankofart_collection_get_top_picks() {
	$picks = get_option( 'boa_collection_top_picks', array() );
	if ( ! is_array( $picks ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'strval', $picks ), 'strlen' ) );
}

/**
 * TOPに出す作品を取得する。
 *
 * 選択が無い／足りない場合は、新着の作品で補う。
 * 管理画面で設定する前でもTOPが空にならないようにするため。
 *
 * @return array 行の配列（最大 BANKOFART_TOP_PICK_COUNT 件）。
 */
function bankofart_collection_get_top_arts() {
	$picks = bankofart_collection_get_top_picks();
	$arts  = $picks ? bankofart_collection_get_arts_by_ids( $picks ) : array();

	if ( count( $arts ) >= BANKOFART_TOP_PICK_COUNT ) {
		return array_slice( $arts, 0, BANKOFART_TOP_PICK_COUNT );
	}

	// 不足分を新着で補う（すでに入っているものは除く）。
	global $wpdb;
	$table = bankofart_collection_table();
	$have  = wp_list_pluck( $arts, 'collection_id' );
	$need  = BANKOFART_TOP_PICK_COUNT - count( $arts );

	if ( $have ) {
		$in   = implode( ',', array_fill( 0, count( $have ), '%s' ) );
		$args = array_merge( $have, array( $need ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE is_sold = 0 AND collection_id NOT IN ({$in}) ORDER BY is_new DESC, collection_id DESC LIMIT %d", $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE is_sold = 0 ORDER BY is_new DESC, collection_id DESC LIMIT %d", $need ), ARRAY_A ); // phpcs:ignore WordPress.DB
	}

	return array_merge( $arts, (array) $rows );
}

/**
 * 選択用の作品一覧を、アーティストごとにまとめて返す。
 *
 * @return array array( 表示名 => array( collection_id => ラベル ) )。
 */
function bankofart_collection_pick_options() {
	global $wpdb;
	$table = bankofart_collection_table();
	$rows  = $wpdb->get_results( "SELECT collection_id, artist_slug, title, size_label, is_sold FROM {$table} ORDER BY artist_slug, sort_order", ARRAY_A ); // phpcs:ignore WordPress.DB

	$grouped = array();
	foreach ( $rows as $r ) {
		$artist_post = get_page_by_path( $r['artist_slug'], OBJECT, 'artist' );
		$artist_name = $artist_post ? get_the_title( $artist_post ) : $r['artist_slug'];

		$label = $r['title'];
		if ( '' !== (string) $r['size_label'] ) {
			$label .= '（' . $r['size_label'] . '）';
		}
		if ( ! empty( $r['is_sold'] ) ) {
			$label .= ' ※SOLD OUT';
		}
		$grouped[ $artist_name ][ $r['collection_id'] ] = $label;
	}
	return $grouped;
}

/**
 * 選択内容の保存を受け取る。
 *
 * @return void
 */
function bankofart_collection_handle_top_picks() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}
	check_admin_referer( 'boa_collection_top_picks' );

	$picks = array();
	for ( $i = 0; $i < BANKOFART_TOP_PICK_COUNT; $i++ ) {
		$key = 'pick_' . $i;
		if ( ! empty( $_POST[ $key ] ) ) {
			$picks[] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}
	}
	// 同じ作品を2箇所に入れても意味がないので重複は落とす。
	$picks = array_values( array_unique( $picks ) );

	update_option( 'boa_collection_top_picks', $picks, false );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'   => 'boa-collection-sync',
				'picked' => count( $picks ),
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_boa_collection_top_picks', 'bankofart_collection_handle_top_picks' );
