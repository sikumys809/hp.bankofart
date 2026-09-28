<?php
/**
 * コレクションサイトのアーティストを WP に取り込む
 *
 * collection 側の /artist/{slug} には、WPのアーティスト項目とほぼ同じ情報が載っている。
 * 既に WP にいる画家は触らず、いない画家だけを下書きで作る（公開は人が判断する）。
 *
 * 取れる項目：
 *   活動名 / 英字名 / キャッチフレーズ / 出身地 / 制作テーマ / 描く理由 /
 *   目標 / 経歴 / 個展 / グループ展 / 受賞・メディア / Instagram / メイン写真
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * collection のアーティスト一覧（スラッグ）を取得する。
 *
 * @return array|WP_Error
 */
function bankofart_collection_fetch_artist_slugs() {
	$html = bankofart_collection_fetch( '/artist' );
	if ( is_wp_error( $html ) ) {
		return $html;
	}
	preg_match_all( '~/artist/([A-Za-z0-9-]+)~', $html, $m );
	return array_values( array_unique( array_filter( $m[1] ) ) );
}

/**
 * アーティストページのHTMLを、表示テキストの行配列に均す。
 *
 * @param string $html HTML。
 * @return array 行の配列。
 */
function bankofart_collection_artist_lines( $html ) {
	$t = preg_replace( '~<(script|style)[^>]*>.*?</\1>~s', ' ', $html );
	$t = preg_replace( '~<[^>]+>~', "\n", $t );
	$t = html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
	return array_values( array_filter( array_map( 'trim', explode( "\n", $t ) ), 'strlen' ) );
}

/**
 * 見出しの次の行から、次の見出しの手前までを取り出す。
 *
 * @param array  $lines 行配列。
 * @param string $head  見出し文字列。
 * @param array  $stops 次の見出しの候補。
 * @return string 改行区切りの本文。
 */
function bankofart_collection_section( $lines, $head, $stops ) {
	$idx = array_search( $head, $lines, true );
	if ( false === $idx ) {
		return '';
	}
	$out = array();
	for ( $i = $idx + 1, $n = count( $lines ); $i < $n; $i++ ) {
		if ( in_array( $lines[ $i ], $stops, true ) ) {
			break;
		}
		$out[] = $lines[ $i ];
	}
	return implode( "\n", $out );
}

/**
 * アーティスト1人ぶんのプロフィールを取得・解析する。
 *
 * @param string $slug スラッグ。
 * @return array|WP_Error
 */
function bankofart_collection_fetch_artist( $slug ) {
	$html = bankofart_collection_fetch( '/artist/' . rawurlencode( $slug ) );
	if ( is_wp_error( $html ) ) {
		return $html;
	}

	$lines = bankofart_collection_artist_lines( $html );

	// <title> は「表示名（英字名） | BANK OF ART」。
	preg_match( '~<title>(.*?)</title>~s', $html, $tm );
	$title = isset( $tm[1] ) ? trim( html_entity_decode( $tm[1], ENT_QUOTES, 'UTF-8' ) ) : '';
	$title = trim( preg_replace( '~\s*\|\s*BANK OF ART\s*$~u', '', $title ) );

	$name    = $title;
	$name_en = '';
	if ( preg_match( '~^(.*?)（(.*?)）$~u', $title, $nm ) ) {
		$name    = trim( $nm[1] );
		$name_en = trim( $nm[2] );
	}
	if ( '' === $name || 'BANK OF ART' === $name ) {
		return new WP_Error( 'boa_artist_empty', $slug . '： 名前が取得できません（中身が空のページ）' );
	}

	// 見出しの一覧。区切りの判定に使う。
	$stops = array( '制作のテーマ', '描く理由', '目標', '経歴', '個展', 'グループ展', '受賞', 'メディア', 'WORKS', '作品', 'この作家の作品を見る →', 'Instagram' );

	// キャッチフレーズ＝名前の直後の1行（見出しでも定型語でもないもの）。
	$catch = '';
	$ni    = array_search( $name, $lines, true );
	if ( false !== $ni && isset( $lines[ $ni + 1 ] ) ) {
		$cand = $lines[ $ni + 1 ];
		if ( ! in_array( $cand, $stops, true ) && '出身　' !== $cand && '出身' !== $cand ) {
			$catch = $cand;
		}
	}

	// 出身地（「出身」の次の行）。
	$birth = '';
	foreach ( array( '出身　', '出身' ) as $h ) {
		$bi = array_search( $h, $lines, true );
		if ( false !== $bi && isset( $lines[ $bi + 1 ] ) ) {
			$birth = $lines[ $bi + 1 ];
			break;
		}
	}

	preg_match( '~https://(?:www\.)?instagram\.com/[A-Za-z0-9_.]+~', $html, $ig );

	// メイン写真（Next.js の画像最適化URLを絶対URLに直す）。
	$photo = '';
	if ( preg_match( '~<img[^>]*src="(/_next/image\?url=[^"]+)"~', $html, $im ) ) {
		$photo = BANKOFART_COLLECTION_URL . html_entity_decode( $im[1], ENT_QUOTES, 'UTF-8' );
	}

	return array(
		'slug'        => $slug,
		'name'        => $name,
		'name_en'     => $name_en,
		'catch'       => $catch,
		'birthplace'  => $birth,
		'theme_short' => bankofart_collection_section( $lines, '制作のテーマ', $stops ),
		'reason'      => bankofart_collection_section( $lines, '描く理由', $stops ),
		'goal'        => bankofart_collection_section( $lines, '目標', $stops ),
		'education'   => bankofart_collection_section( $lines, '経歴', $stops ),
		'solo_exh'    => bankofart_collection_section( $lines, '個展', $stops ),
		'group_exh'   => bankofart_collection_section( $lines, 'グループ展', $stops ),
		'awards'      => bankofart_collection_section( $lines, '受賞', $stops ),
		'instagram'   => $ig[0] ?? '',
		'photo_url'   => $photo,
	);
}

/**
 * 解析済みのプロフィールから WP のアーティスト投稿を作る。
 *
 * 既に同じスラッグの投稿があれば何もしない（人が編集した内容を上書きしないため）。
 * 作成するのは常に下書き。公開は中身を見てから人が判断する。
 *
 * @param array $a bankofart_collection_fetch_artist() の戻り値。
 * @return int|WP_Error 作成した投稿ID。
 */
function bankofart_collection_create_artist( $a ) {
	if ( get_page_by_path( $a['slug'], OBJECT, 'artist' ) ) {
		return new WP_Error( 'boa_artist_exists', $a['slug'] . '： すでにWPにあります' );
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'artist',
			'post_status' => 'draft',
			'post_title'  => $a['name'],
			'post_name'   => $a['slug'],
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$map = array(
		'artist_name_en'           => $a['name_en'],
		'artist_catch_phrase'      => $a['catch'],
		'artist_birthplace'        => $a['birthplace'],
		'artist_theme_short'       => $a['theme_short'],
		'artist_reason'            => $a['reason'],
		'artist_goal'              => $a['goal'],
		'artist_education'         => $a['education'],
		'artist_solo_exhibitions'  => $a['solo_exh'],
		'artist_group_exhibitions' => $a['group_exh'],
		'artist_media_awards'      => $a['awards'],
		'artist_sns_instagram'     => $a['instagram'],
	);
	foreach ( $map as $key => $value ) {
		if ( '' !== trim( (string) $value ) ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	// どこから作られたか分かるようにしておく（後から辿れるように）。
	update_post_meta( $post_id, '_bankofart_collection_slug', $a['slug'] );

	return $post_id;
}

/**
 * WPに無いアーティストだけを取り込む。
 *
 * @return array 結果 array( 'created' => array, 'skipped' => array, 'errors' => array )。
 */
function bankofart_collection_import_missing_artists() {
	$slugs = bankofart_collection_fetch_artist_slugs();
	if ( is_wp_error( $slugs ) ) {
		return array(
			'created' => array(),
			'skipped' => array(),
			'errors'  => array( 'アーティスト一覧の取得に失敗： ' . $slugs->get_error_message() ),
		);
	}

	$created = array();
	$skipped = array();
	$errors  = array();

	foreach ( $slugs as $slug ) {
		if ( get_page_by_path( $slug, OBJECT, 'artist' ) ) {
			$skipped[] = $slug;
			continue;
		}

		$a = bankofart_collection_fetch_artist( $slug );
		if ( is_wp_error( $a ) ) {
			$errors[] = $a->get_error_message();
			continue;
		}

		$id = bankofart_collection_create_artist( $a );
		if ( is_wp_error( $id ) ) {
			$errors[] = $id->get_error_message();
			continue;
		}
		$created[] = array(
			'id'   => $id,
			'slug' => $slug,
			'name' => $a['name'],
		);
	}

	return array(
		'created' => $created,
		'skipped' => $skipped,
		'errors'  => $errors,
	);
}
