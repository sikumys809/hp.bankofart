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
 * アーティスト一覧ページ（/artist）から、画家ごとの点数を取り出す。
 *
 * 一覧ページのカードは次の形で、スラッグ・表示名・公開点数がそろっている。
 *   <a href="/artist/{スラッグ}"> … <img alt="{表示名}"> … 公開中の作品 N点 … </a>
 * この N点は SOLD OUT も含めた総数で、各画家ページの実数と一致することを確認済み。
 *
 * これを使うと「前回から点数が変わった画家」だけを回れるので、
 * 毎回20ページ以上巡回する必要がなくなる。
 *
 * @return array|WP_Error スラッグ => array( 'name' => 表示名, 'count' => 点数 )。
 */
function bankofart_collection_fetch_artist_index() {
	$html = bankofart_collection_fetch( '/artist' );
	if ( is_wp_error( $html ) ) {
		return $html;
	}

	preg_match_all( '~<a[^>]*href="/artist/([A-Za-z0-9-]+)"[^>]*>(.*?)</a>~s', $html, $matches, PREG_SET_ORDER );
	if ( ! $matches ) {
		return new WP_Error( 'boa_collection_index', 'アーティスト一覧の構造が変わった可能性があります。' );
	}

	$index = array();
	foreach ( $matches as $m ) {
		$slug = $m[1];
		if ( isset( $index[ $slug ] ) ) {
			continue;
		}
		preg_match( '~<img[^>]*alt="([^"]*)"~', $m[2], $alt );
		$count = preg_match( '~公開中の作品\s*(\d+)\s*点~u', $m[2], $c ) ? (int) $c[1] : 0;

		$index[ $slug ] = array(
			'name'  => isset( $alt[1] ) ? html_entity_decode( $alt[1], ENT_QUOTES, 'UTF-8' ) : '',
			'count' => $count,
		);
	}
	ksort( $index );
	return $index;
}

/**
 * コレクションサイトのアーティストスラッグ一覧を取得する。
 *
 * @return array|WP_Error スラッグの配列。
 */
function bankofart_collection_fetch_artists() {
	$index = bankofart_collection_fetch_artist_index();
	if ( is_wp_error( $index ) ) {
		return $index;
	}
	return array_keys( $index );
}

/**
 * ページに埋まっている切り抜き指定（4点）を、元写真のURLをキーにして取り出す。
 *
 * Next.js が書き出す受け渡しデータ（画面用のJSON）に、作品ごとに次の3つが入っている。
 *   aspectRatio … 作品の実寸から来る縦横比。切り抜き後の長方形の形になる。
 *   url         … 元写真そのもののURL（画面に出ている最適化済みURLとは別）。
 *   crop.q      … 手作業で指定された4点。左上→右上→右下→左下の順、0〜1の割合。
 *
 * 見た目のHTMLに出ている left/top/width/height は、この4点の外接長方形でしかない。
 * 作品を正面から見た形に起こすには4点のほうが要る。
 *
 * 作品によっては q が無く、長方形（x0/x1/y0/y1）だけのこともある。
 * その場合はその長方形の四隅を4点として扱い、以降の処理を一本化する。
 *
 * @param string $html HTML。
 * @return array 元写真URL => array( 'ratio' => float, 'quad' => array )。
 */
function bankofart_collection_parse_crops( $html ) {
	$re = '~\\\\"aspectRatio\\\\":\\\\"([0-9.]+)\\\\".{0,600}?\\\\"url\\\\":\\\\"(https://[^\\\\"]+)\\\\".{0,300}?\\\\"crop\\\\":\{(.{0,400}?)\}~s';
	if ( ! preg_match_all( $re, $html, $matches, PREG_SET_ORDER ) ) {
		return array();
	}

	$crops = array();
	foreach ( $matches as $m ) {
		// 受け渡しデータはJS文字列の中にあるので、まず \" を " に戻してから読む。
		$crop = json_decode( '{' . str_replace( '\\"', '"', $m[3] ) . '}', true );
		if ( ! is_array( $crop ) ) {
			continue;
		}
		$quad = bankofart_collection_crop_to_quad( $crop );
		if ( ! $quad ) {
			continue;
		}
		$crops[ $m[2] ] = array(
			'ratio' => (float) $m[1],
			'quad'  => $quad,
		);
	}
	return $crops;
}

/**
 * 切り抜き指定を、かならず4点の形にして返す。
 *
 * @param array $crop 受け渡しデータの crop オブジェクト。
 * @return array|null 左上→右上→右下→左下の4点。読めなければ null。
 */
function bankofart_collection_crop_to_quad( array $crop ) {
	// 自由変形（四隅を別々に指定）があればそれが本命。
	if ( isset( $crop['q'] ) && is_array( $crop['q'] ) && 4 === count( $crop['q'] ) ) {
		$quad = array();
		foreach ( $crop['q'] as $point ) {
			if ( ! is_array( $point ) || 2 !== count( $point ) || ! is_numeric( $point[0] ) || ! is_numeric( $point[1] ) ) {
				return null;
			}
			$quad[] = array( (float) $point[0], (float) $point[1] );
		}
		return $quad;
	}

	// 長方形だけの作品は、その四隅を4点として扱う。
	$keys = array( 'x0', 'x1', 'y0', 'y1' );
	foreach ( $keys as $k ) {
		if ( ! isset( $crop[ $k ] ) || ! is_numeric( $crop[ $k ] ) ) {
			return null;
		}
	}
	$x0 = (float) $crop['x0'];
	$x1 = (float) $crop['x1'];
	$y0 = (float) $crop['y0'];
	$y1 = (float) $crop['y1'];
	return array(
		array( $x0, $y0 ),
		array( $x1, $y0 ),
		array( $x1, $y1 ),
		array( $x0, $y1 ),
	);
}

/**
 * 画面用の画像URLから、元写真のURLを取り出す。
 *
 * 一覧の img は /_next/image?url=<元写真URL>&w=…&q=… という中継URLになっている。
 *
 * @param string $image_url 画面用のURL。
 * @return string 取り出せなければ空文字。
 */
function bankofart_collection_source_url( $image_url ) {
	$query = (string) wp_parse_url( $image_url, PHP_URL_QUERY );
	if ( '' === $query ) {
		return '';
	}
	parse_str( $query, $args );
	$url = isset( $args['url'] ) ? (string) $args['url'] : '';
	return ( 0 === strpos( $url, 'http' ) ) ? $url : '';
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

	// 切り抜きの4点はページ末尾の受け渡しデータ側にあるので、先に全部拾っておく。
	$crops = bankofart_collection_parse_crops( $html );

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

		/*
		 * トリミング情報。collection は作品を手作業で切り抜いており、その結果を
		 * 「枠の縦横比」と「元写真を何%拡大してどれだけずらすか」という数値で
		 * マークアップに埋め込んでいる。切り抜き済み画像は配信されないため、
		 * この数値を取り込んで同じ指定を再現するのが、そのまま持ってくる唯一の方法。
		 */
		$crop = array(
			'ratio'  => null,
			'left'   => null,
			'top'    => null,
			'width'  => null,
			'height' => null,
		);
		/*
		 * 枠の指定は作品の形で2通りある。
		 *   縦長 … aspect-ratio:0.85…;height:100%
		 *   横長・正方形 … aspect-ratio:1;width:100%
		 * 比率は「1」のように小数点が付かないこともあるので、どちらも拾う。
		 */
		if ( preg_match( '~aspect-ratio:([0-9]+(?:\.[0-9]+)?);(?:height|width):100%~', $inner, $ar )
			&& preg_match( '~position:absolute;left:(-?[0-9.]+)%;top:(-?[0-9.]+)%;width:([0-9.]+)%;height:([0-9.]+)%~', $inner, $cp ) ) {
			$crop = array(
				'ratio'  => (float) $ar[1],
				'left'   => (float) $cp[1],
				'top'    => (float) $cp[2],
				'width'  => (float) $cp[3],
				'height' => (float) $cp[4],
			);
		}

		/*
		 * 手作業の4点。元写真のURLで突き合わせる（作品名は重複しうるため）。
		 * 比率もこちらの値を採る。実寸から計算された正確な値で、
		 * 見た目のHTMLに出ている丸めた値より確か。
		 */
		$source = bankofart_collection_source_url( $image );
		$quad   = null;
		if ( '' !== $source && isset( $crops[ $source ] ) ) {
			$quad = wp_json_encode( $crops[ $source ]['quad'] );
			if ( $crops[ $source ]['ratio'] > 0 ) {
				$crop['ratio'] = $crops[ $source ]['ratio'];
			}
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
			'crop_ratio'    => $crop['ratio'],
			'crop_left'     => $crop['left'],
			'crop_top'      => $crop['top'],
			'crop_width'    => $crop['width'],
			'crop_height'   => $crop['height'],
			'source_url'    => $source,
			'crop_quad'     => $quad,
			'sort_order'    => $i++,
		);
	}

	return array_values( $arts );
}

/**
 * コレクションサイトを巡回してDBへ保存する。
 *
 * まずアーティスト一覧ページを1枚だけ取り、画家ごとの点数を見る。
 * 前回保存した点数と変わっている画家だけを実際に回るので、
 * 作品の増減が無い月は「一覧ページ1枚」で終わる。
 *
 * ※ 点数が変わらない変化（SOLD OUT になった・価格が変わった）は、この方式では
 *    拾えない。そのため3か月に1回は全画家を回す（下の $force 判定）。
 *    管理画面の「全件を取り直す」からも全巡回できる。
 *
 * 失敗した画家があってもそこだけ飛ばし、取得できたぶんは保存する。
 * 削除は「今回実際に回った画家」の作品だけを対象にする
 * （回っていない画家の作品を消してしまわないため）。
 *
 * @param bool $force true なら点数に関係なく全画家を回す。
 * @return array 結果サマリ。
 */
function bankofart_collection_sync_all( $force = false ) {
	global $wpdb;
	$table = bankofart_collection_table();
	$now   = current_time( 'mysql' );

	$index = bankofart_collection_fetch_artist_index();
	if ( is_wp_error( $index ) ) {
		return array(
			'artists' => 0,
			'arts'    => 0,
			'deleted' => 0,
			'errors'  => array( 'アーティスト一覧の取得に失敗： ' . $index->get_error_message() ),
		);
	}
	$slugs = array_keys( $index );

	// 3か月に1回は、点数が変わらない変化（SOLD化・価格改定）を拾うために全部回す。
	$runs  = (int) get_option( 'boa_collection_sync_runs', 0 );
	$force = $force || ( 0 === $runs % 3 );
	update_option( 'boa_collection_sync_runs', $runs + 1, false );

	// 保存済みの画家別点数。これと一覧ページの点数を突き合わせる。
	$stored = array();
	foreach ( $wpdb->get_results( "SELECT artist_slug, COUNT(*) c FROM {$table} GROUP BY artist_slug", ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB
		$stored[ $r['artist_slug'] ] = (int) $r['c'];
	}

	$errors  = array();
	$seen    = array();
	$count   = 0;
	$empty   = array();
	$visited = array();
	$skipped = array();

	foreach ( $slugs as $slug ) {
		$want = (int) $index[ $slug ]['count'];
		$have = isset( $stored[ $slug ] ) ? $stored[ $slug ] : 0;

		// 点数が同じ画家は回らない。既存の行はそのまま残す。
		if ( ! $force && $want === $have ) {
			$skipped[] = $slug;
			if ( 0 === $want ) {
				$empty[] = $slug;
			}
			continue;
		}

		$visited[] = $slug;
		$html      = bankofart_collection_fetch( '/?artist=' . rawurlencode( $slug ) );
		if ( is_wp_error( $html ) ) {
			$errors[] = $slug . '： ' . $html->get_error_message();
			continue;
		}

		$arts = bankofart_collection_parse_arts( $html, $slug );

		/*
		 * 作品一覧の絞り込み（/?artist=）が効かない作家がいる。
		 * （スラッグがUUIDのまま等、向こうのデータの都合。森田さんがこれ）
		 * そのときはプロフィールページから拾う。こちらは全員ぶん揃っている。
		 * ただし「新着」バッジが出ない作りなので、絞り込みが効くならそちらを優先する。
		 */
		if ( ! $arts ) {
			$profile = bankofart_collection_fetch( '/artist/' . rawurlencode( $slug ) );
			if ( ! is_wp_error( $profile ) ) {
				$arts = bankofart_collection_parse_arts( $profile, $slug );
			}
		}

		/*
		 * 作品を1点も出していない作家は普通にいる（登録だけの状態）。
		 * これを失敗として数えると、消えた作品の削除が毎回止まってしまうので
		 * エラーではなく「作品なし」として記録するにとどめる。
		 */
		if ( ! $arts ) {
			$empty[] = $slug;
			continue;
		}

		foreach ( $arts as $a ) {
			$a['synced_at'] = $now;
			/*
			 * REPLACE は行を消して入れ直すため、そのままだと生成済み画像の目印
			 * （rect_key）が毎回消えて、全点を作り直すことになる。持ち越しておく。
			 * 切り抜き指定が変わっていれば目印と合わなくなるので、そのときだけ作り直る。
			 */
			$a['rect_key'] = $wpdb->get_var( $wpdb->prepare( "SELECT rect_key FROM {$table} WHERE collection_id = %s", $a['collection_id'] ) ); // phpcs:ignore WordPress.DB

			$wpdb->replace(
				$table,
				$a,
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%s', '%s' )
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$seen[] = $a['collection_id'];
			$count++;
		}
	}

	/*
	 * 消えた作品を削除する。
	 *
	 * 対象は「今回実際に回った画家」の作品だけ。回らなかった画家の作品まで
	 * 消さないようにするため、画家で絞ってから比較する。
	 * あわせて、一覧ページから居なくなった画家の作品もまとめて消す
	 * （向こうでスラッグが変わった場合など）。
	 */
	$deleted = 0;
	if ( ! $errors ) {
		if ( $visited && $seen ) {
			$in_v    = implode( ',', array_fill( 0, count( $visited ), '%s' ) );
			$in_s    = implode( ',', array_fill( 0, count( $seen ), '%s' ) );
			$deleted = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE artist_slug IN ({$in_v}) AND collection_id NOT IN ({$in_s})", array_merge( $visited, $seen ) ) ); // phpcs:ignore WordPress.DB
		}
		if ( $slugs ) {
			$in_a     = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );
			$deleted += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE artist_slug NOT IN ({$in_a})", $slugs ) ); // phpcs:ignore WordPress.DB
		}
	}

	// 消えた作品の生成画像も片付ける（DBに残っているIDを正とする）。
	if ( $deleted > 0 ) {
		$keep = $wpdb->get_col( "SELECT collection_id FROM {$table}" ); // phpcs:ignore WordPress.DB
		bankofart_collection_purge_rects( (array) $keep );
	}

	/*
	 * 切り抜き画像を作る。1点あたり1〜2秒かかるので時間を区切り、
	 * 残りは5分後の単発イベントで続きを進める（build_pending が予約する）。
	 */
	$rects = bankofart_collection_build_pending( 40 );

	$summary = array(
		'at'      => $now,
		'artists' => count( $slugs ) - count( $errors ) - count( $empty ),
		'arts'    => $count,
		'deleted' => $deleted,
		'errors'  => $errors,
		'empty'   => $empty,
		'rects'   => $rects,
		// 巡回の内訳。管理画面で「何ページ見たか」を出すために持つ。
		'visited' => $visited,
		'skipped' => $skipped,
		'pages'   => 1 + count( $visited ),
		'full'    => (bool) $force,
		'total'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ), // phpcs:ignore WordPress.DB
	);

	$stored_summary           = $summary;
	$stored_summary['errors'] = array_merge( $errors, $rects['errors'] );
	update_option( 'boa_collection_last_sync', $stored_summary, false );

	return $summary;
}

/**
 * 残っていない作品の生成画像を削除する。
 *
 * @param array $keep 残す collection_id の配列。
 * @return int 削除した枚数。
 */
function bankofart_collection_purge_rects( array $keep ) {
	$paths = bankofart_collection_rect_dir();
	if ( is_wp_error( $paths ) ) {
		return 0;
	}
	$files = glob( $paths['dir'] . '/*.jpg' );
	if ( ! $files ) {
		return 0;
	}
	$keep_files = array();
	foreach ( $keep as $id ) {
		$keep_files[ bankofart_collection_rect_filename( $id ) ] = true;
	}
	$removed = 0;
	foreach ( $files as $file ) {
		if ( ! isset( $keep_files[ basename( $file ) ] ) && unlink( $file ) ) {
			$removed++;
		}
	}
	return $removed;
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

/**
 * アーティストのスラッグ → WPでの表示名 の対応表を返す。
 *
 * 作品1点ずつ get_page_by_path() を呼ぶと件数ぶんクエリが出るので、1回でまとめて引く。
 * WPに投稿が無い画家はスラッグをそのまま名前として扱う。
 *
 * @return array スラッグ => 表示名。
 */
function bankofart_collection_artist_names() {
	static $names = null;
	if ( null !== $names ) {
		return $names;
	}

	$names   = array();
	$artists = get_posts(
		array(
			'post_type'        => 'artist',
			'post_status'      => array( 'publish', 'draft', 'private' ),
			'numberposts'      => -1,
			'suppress_filters' => false,
		)
	);
	foreach ( $artists as $a ) {
		$names[ $a->post_name ] = $a->post_title;
	}
	return $names;
}

/**
 * いま開いているのが指定の投稿タイプの編集画面かを判定する。
 *
 * Meta Box のフィールド定義は init（全リクエスト）で組み立てられるため、
 * 作品135点ぶんの選択肢をそこで毎回作ると、フロントにも管理画面の他の画面にも
 * 無駄なクエリが出る。必要な編集画面のときだけ作るために使う。
 *
 * @param string $post_type 投稿タイプ。
 * @return bool
 */
function bankofart_collection_is_editing( $post_type ) {
	global $pagenow;
	if ( ! is_admin() ) {
		return false;
	}
	if ( 'post-new.php' === $pagenow ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		return isset( $_GET['post_type'] ) && $post_type === sanitize_key( wp_unslash( $_GET['post_type'] ) );
	}
	if ( 'post.php' === $pagenow ) {
		// 表示時は GET の post、保存時は POST の post_ID に入る。
		// phpcs:ignore WordPress.Security.NonceVerification
		$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : (int) ( $_POST['post_ID'] ?? 0 );
		return $id > 0 && $post_type === get_post_type( $id );
	}
	return false;
}

/**
 * 管理画面で作品を選ぶための「ID => ラベル」一覧を返す。
 *
 * ラベルは「画家 ／ 作品名（号数）」。SOLD OUT は末尾に付ける。
 * select2 の検索にかかるよう、画家名も作品名も1行に入れてある。
 *
 * @return array collection_id => ラベル。
 */
function bankofart_collection_art_labels() {
	global $wpdb;
	$table = bankofart_collection_table();
	$rows  = $wpdb->get_results( "SELECT collection_id, artist_slug, title, size_label, is_sold FROM {$table} ORDER BY artist_slug, sort_order", ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows ) {
		return array();
	}

	$names  = bankofart_collection_artist_names();
	$labels = array();
	foreach ( $rows as $r ) {
		$label = ( $names[ $r['artist_slug'] ] ?? $r['artist_slug'] ) . ' ／ ' . $r['title'];
		if ( '' !== (string) $r['size_label'] ) {
			$label .= '（' . $r['size_label'] . '）';
		}
		if ( ! empty( $r['is_sold'] ) ) {
			$label .= ' ※SOLD OUT';
		}
		$labels[ $r['collection_id'] ] = $label;
	}
	return $labels;
}

/**
 * 作品一覧のURLを返す。
 *
 * WPの /art/ アーカイブに代わる「作品を探す」の行き先。
 * テンプレートからは必ずこれを呼ぶ（リンク先を1か所で変えられるようにするため）。
 *
 * @return string
 */
function bankofart_collection_arts_url() {
	return BANKOFART_COLLECTION_URL . '/';
}
