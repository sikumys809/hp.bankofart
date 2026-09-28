<?php
/**
 * コレクションサイト同期：取得と保存
 *
 * 取得対象はサーバーサイドレンダリング済みのHTML。
 *   トップ            → /?artist=スラッグ のリンクから、アーティスト一覧を得る
 *   各アーティスト   → /?artist=スラッグ のページから、その画家の作品を得る
 *
 * 作品1点は <a href="/art/BOA-XXXX-XXXXX"> … </a> で、内側のテキストが
 *   新着（任意） / 作品名 / 画家 ／ 号数 / ¥価格（税込） / 税抜 ¥価格
 * の順に並ぶ。画像は <img alt="作品名" src="/_next/image?url=…">。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * コレクションサイトから1ページ取得する。
 *
 * @param string $path 先頭スラッシュ付きのパス（例 '/?artist=gen'）。
 * @return string|WP_Error HTML。
 */
function bankofart_collection_fetch( $path ) {
	$res = wp_remote_get(
		BANKOFART_COLLECTION_URL . $path,
		array(
			'timeout'    => 30,
			'user-agent' => 'BankOfArt-Sync/1.0 (+' . home_url( '/' ) . ')',
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		return new WP_Error( 'boa_collection_http', sprintf( 'HTTP %d （%s）', $code, $path ) );
	}
	return (string) wp_remote_retrieve_body( $res );
}

/**
 * コレクションサイトのアーティストスラッグ一覧を取得する。
 *
 * @return array|WP_Error スラッグの配列。
 */
function bankofart_collection_fetch_artists() {
	$html = bankofart_collection_fetch( '/' );
	if ( is_wp_error( $html ) ) {
		return $html;
	}
	preg_match_all( '~/\?artist=([a-z0-9-]+)~', $html, $m );
	$slugs = array_values( array_unique( array_filter( $m[1] ) ) );
	sort( $slugs );
	return $slugs;
}

/**
 * アーティスト1人ぶんのページを解析して作品配列を返す。
 *
 * @param string $html HTML。
 * @param string $slug アーティストスラッグ。
 * @return array 作品の配列。
 */
function bankofart_collection_parse_arts( $html, $slug ) {
	preg_match_all( '~<a[^>]*href="/art/(BOA-\d{4}-\d{5})"[^>]*>(.*?)</a>~s', $html, $matches, PREG_SET_ORDER );

	$arts = array();
	$i    = 0;
	foreach ( $matches as $m ) {
		$id    = $m[1];
		$inner = $m[2];
		if ( isset( $arts[ $id ] ) ) {
			continue;
		}

		// タグを区切りに変えて、表示テキストを行として取り出す。
		$text  = preg_replace( '~<[^>]+>~', "\n", $inner );
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) ), 'strlen' ) );

		// 先頭のバッジ（「新着」「SOLD OUT」）を取り除いてから本文を読む。
		$is_new  = ( isset( $lines[0] ) && '新着' === $lines[0] );
		$is_sold = in_array( 'SOLD OUT', $lines, true );
		if ( $is_new || ( isset( $lines[0] ) && 'SOLD OUT' === $lines[0] ) ) {
			array_shift( $lines );
		}

		// 作品名は img の alt が最も確実（改行や装飾の影響を受けない）。
		preg_match( '~<img[^>]*alt="([^"]*)"~', $inner, $alt );
		$title = isset( $alt[1] ) ? html_entity_decode( $alt[1], ENT_QUOTES, 'UTF-8' ) : ( $lines[0] ?? '' );

		/*
		 * 号数。「画家 ／ 6号」の形で入るが、作品名にも「／」が使われることがあるため
		 * （例「観てくれてありがとう皆んな／皆んなが…／全てを愛してるよ」）、
		 * 区切りの後ろを無条件に採らず、号数らしい表記のときだけ採用する。
		 * mini 作品のように号数表記が無いものは空のままにする。
		 */
		$size = '';
		foreach ( $lines as $line ) {
			if ( preg_match( '~／\s*(\d+\s*号|[SFPMsfpm]\s*\d+)\s*$~u', $line, $sm ) ) {
				$size = trim( $sm[1] );
				break;
			}
		}

		// 税込価格（「¥439,989」のうち（税込）が続くもの）。
		$price = '';
		if ( preg_match( '~¥([\d,]+)\s*（税込）~u', preg_replace( '~<[^>]+>~', '', $inner ), $p ) ) {
			$price = $p[1];
		} elseif ( preg_match( '~¥([\d,]+)~u', preg_replace( '~<[^>]+>~', '', $inner ), $p ) ) {
			$price = $p[1];
		}

		// 画像URL（Next.js の最適化URLは相対。絶対URLに直して保存する）。
		preg_match( '~<img[^>]*src="([^"]+)"~', $inner, $src );
		$image = isset( $src[1] ) ? html_entity_decode( $src[1], ENT_QUOTES, 'UTF-8' ) : '';
		if ( '' !== $image && 0 === strpos( $image, '/' ) ) {
			$image = BANKOFART_COLLECTION_URL . $image;
		}

		$arts[ $id ] = array(
			'collection_id' => $id,
			'artist_slug'   => $slug,
			'title'         => $title,
			'size_label'    => $size,
			'price'         => $price,
			'image_url'     => $image,
			'is_new'        => $is_new ? 1 : 0,
			'is_sold'       => $is_sold ? 1 : 0,
			'sort_order'    => $i++,
		);
	}

	return array_values( $arts );
}

/**
 * コレクションサイト全体を巡回してDBへ保存する。
 *
 * 失敗した画家があってもそこだけ飛ばし、取得できたぶんは保存する。
 * 全画家ぶん取得できたときだけ、今回見つからなかった作品を削除する
 * （途中で落ちたときに既存データを消してしまわないため）。
 *
 * @return array 結果サマリ array( 'artists', 'arts', 'errors', 'deleted' )。
 */
function bankofart_collection_sync_all() {
	global $wpdb;
	$table = bankofart_collection_table();
	$now   = current_time( 'mysql' );

	$slugs = bankofart_collection_fetch_artists();
	if ( is_wp_error( $slugs ) ) {
		return array(
			'artists' => 0,
			'arts'    => 0,
			'deleted' => 0,
			'errors'  => array( 'アーティスト一覧の取得に失敗： ' . $slugs->get_error_message() ),
		);
	}

	$errors = array();
	$seen   = array();
	$count  = 0;

	foreach ( $slugs as $slug ) {
		$html = bankofart_collection_fetch( '/?artist=' . rawurlencode( $slug ) );
		if ( is_wp_error( $html ) ) {
			$errors[] = $slug . '： ' . $html->get_error_message();
			continue;
		}

		$arts = bankofart_collection_parse_arts( $html, $slug );
		if ( ! $arts ) {
			$errors[] = $slug . '： 作品が0点（ページ構造が変わった可能性）';
			continue;
		}

		foreach ( $arts as $a ) {
			$a['synced_at'] = $now;
			$wpdb->replace( $table, $a, array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$seen[] = $a['collection_id'];
			$count++;
		}
	}

	// 全画家ぶん問題なく取れたときだけ、消えた作品を削除する。
	$deleted = 0;
	if ( ! $errors && $seen ) {
		$in      = implode( ',', array_fill( 0, count( $seen ), '%s' ) );
		$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE collection_id NOT IN ({$in})", $seen ) ); // phpcs:ignore WordPress.DB
	}

	update_option(
		'boa_collection_last_sync',
		array(
			'at'      => $now,
			'artists' => count( $slugs ) - count( $errors ),
			'arts'    => $count,
			'deleted' => $deleted,
			'errors'  => $errors,
		),
		false
	);

	return array(
		'artists' => count( $slugs ) - count( $errors ),
		'arts'    => $count,
		'deleted' => $deleted,
		'errors'  => $errors,
	);
}

/**
 * 指定アーティストの作品を取り出す（表示用の共通取得口）。
 *
 * @param string $slug  アーティストスラッグ。
 * @param int    $limit 最大件数（0で全件）。
 * @return array 行の配列。
 */
function bankofart_collection_get_arts_by_artist( $slug, $limit = 0 ) {
	global $wpdb;
	$table = bankofart_collection_table();
	$slug  = trim( (string) $slug );
	if ( '' === $slug ) {
		return array();
	}
	$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE artist_slug = %s ORDER BY sort_order ASC", $slug ); // phpcs:ignore WordPress.DB
	if ( $limit > 0 ) {
		$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
	}
	return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB
}

/**
 * 作品IDの配列から行を取り出す（指定した並び順を保つ）。
 *
 * @param array $ids collection_id の配列。
 * @return array 行の配列。
 */
function bankofart_collection_get_arts_by_ids( $ids ) {
	global $wpdb;
	$ids = array_values( array_filter( array_map( 'trim', (array) $ids ), 'strlen' ) );
	if ( ! $ids ) {
		return array();
	}
	$table = bankofart_collection_table();
	$in    = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE collection_id IN ({$in})", $ids ), ARRAY_A ); // phpcs:ignore WordPress.DB

	// 指定順に並べ直す（IN句の順序は保証されないため）。
	$by_id = array();
	foreach ( $rows as $r ) {
		$by_id[ $r['collection_id'] ] = $r;
	}
	$out = array();
	foreach ( $ids as $id ) {
		if ( isset( $by_id[ $id ] ) ) {
			$out[] = $by_id[ $id ];
		}
	}
	return $out;
}

/**
 * 作品ページのURLを返す。
 *
 * @param string $collection_id BOA-2026-00661 形式。
 * @return string
 */
function bankofart_collection_art_url( $collection_id ) {
	return BANKOFART_COLLECTION_URL . '/art/' . rawurlencode( $collection_id );
}

/**
 * アーティストの作品一覧ページ（コレクション側）のURLを返す。
 *
 * @param string $slug アーティストスラッグ。
 * @return string
 */
function bankofart_collection_artist_url( $slug ) {
	return BANKOFART_COLLECTION_URL . '/?artist=' . rawurlencode( $slug );
}
