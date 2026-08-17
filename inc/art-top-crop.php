<?php
/**
 * TOPページ用「絵だけ」の自動トリミング
 *
 * 作品写真は壁に掛けた状態で撮られているため、そのまま TOP のコラージュに
 * 入れると枠の中に壁が大きく写り込む。ここでは写真から絵の部分だけを検出して
 * 切り出した画像を作り、TOP のフレームに使う。
 *
 * 優先順位（front-page.php 側で解決）:
 *   1. art_top_image     … 管理画面で明示指定した画像（自動処理より優先）
 *   2. art_top_crop_auto … 本ファイルが自動生成した切り出し画像
 *   3. art_main_image    … 生成できなかった場合のフォールバック
 *
 * 生成タイミングは保存時（TOP表示ONの作品のみ）。フロントでは生成しない。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 自動生成した切り出し画像の添付IDを入れるメタキー。 */
const BANKOFART_TOP_CROP_META = 'art_top_crop_auto';

/** 生成元を記録して、元画像が変わったときだけ作り直すためのメタキー。 */
const BANKOFART_TOP_CROP_SRC_META = '_art_top_crop_src';

/**
 * 壁に掛かった作品写真から「絵の部分」の矩形を検出する。
 *
 * 壁は「明るく低彩度」、絵は「彩度が高い or 暗い」という性質を使う。
 * 壁の明るさは写真ごとに違うため、四隅から基準値を取って相対的に判定する。
 *
 * @param string $file 画像ファイルのパス。
 * @return array|null array( x, y, w, h, W, H )。検出できなければ null。
 */
function bankofart_detect_artwork_box( $file ) {
	if ( ! function_exists( 'imagecreatefromjpeg' ) || ! file_exists( $file ) ) {
		return null;
	}

	$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 非画像は null を返す。
	if ( ! $size ) {
		return null;
	}

	$im = ( IMAGETYPE_PNG === $size[2] ) ? @imagecreatefrompng( $file ) : @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 同上。
	if ( ! $im ) {
		return null;
	}

	$w_src = imagesx( $im );
	$h_src = imagesy( $im );

	// 解析は縮小版で行う（速度とノイズ対策）。
	$sw = 240;
	$sh = max( 1, (int) ( $h_src * $sw / $w_src ) );
	$small = imagecreatetruecolor( $sw, $sh );
	imagecopyresampled( $small, $im, 0, 0, 0, 0, $sw, $sh, $w_src, $h_src );
	imagedestroy( $im );

	$val = array();
	$sat = array();
	for ( $y = 0; $y < $sh; $y++ ) {
		for ( $x = 0; $x < $sw; $x++ ) {
			$c  = imagecolorat( $small, $x, $y );
			$r  = ( $c >> 16 ) & 255;
			$g  = ( $c >> 8 ) & 255;
			$b  = $c & 255;
			$mx = max( $r, $g, $b );
			$mn = min( $r, $g, $b );

			$val[ $y ][ $x ] = $mx / 255;
			$sat[ $y ][ $x ] = $mx ? ( $mx - $mn ) / $mx : 0;
		}
	}
	imagedestroy( $small );

	// 壁の基準値：四隅（各10%四方）の中央値。
	$corner_v = array();
	$corner_s = array();
	$bw = max( 2, (int) ( $sw * 0.10 ) );
	$bh = max( 2, (int) ( $sh * 0.10 ) );
	foreach ( array( array( 0, 0 ), array( $sw - $bw, 0 ), array( 0, $sh - $bh ), array( $sw - $bw, $sh - $bh ) ) as $o ) {
		for ( $y = $o[1]; $y < $o[1] + $bh; $y++ ) {
			for ( $x = $o[0]; $x < $o[0] + $bw; $x++ ) {
				$corner_v[] = $val[ $y ][ $x ];
				$corner_s[] = $sat[ $y ][ $x ];
			}
		}
	}
	sort( $corner_v );
	sort( $corner_s );
	$wall_v = $corner_v[ (int) ( count( $corner_v ) / 2 ) ];
	$wall_s = $corner_s[ (int) ( count( $corner_s ) / 2 ) ];

	$sat_th = $wall_s + 0.13;
	$val_th = $wall_v - 0.16;

	$col_hit = array_fill( 0, $sw, 0 );
	$row_hit = array_fill( 0, $sh, 0 );
	for ( $y = 0; $y < $sh; $y++ ) {
		for ( $x = 0; $x < $sw; $x++ ) {
			if ( $sat[ $y ][ $x ] > $sat_th || $val[ $y ][ $x ] < $val_th ) {
				++$col_hit[ $x ];
				++$row_hit[ $y ];
			}
		}
	}

	// 影や埃を拾わないよう、行・列の22%以上が該当する範囲だけを採用する。
	$min_col = max( 3, (int) ( $sh * 0.22 ) );
	$min_row = max( 3, (int) ( $sw * 0.22 ) );

	$x0 = 0;
	while ( $x0 < $sw && $col_hit[ $x0 ] < $min_col ) {
		++$x0;
	}
	$x1 = $sw - 1;
	while ( $x1 > $x0 && $col_hit[ $x1 ] < $min_col ) {
		--$x1;
	}
	$y0 = 0;
	while ( $y0 < $sh && $row_hit[ $y0 ] < $min_row ) {
		++$y0;
	}
	$y1 = $sh - 1;
	while ( $y1 > $y0 && $row_hit[ $y1 ] < $min_row ) {
		--$y1;
	}

	// 極端に小さい／検出できていない場合は諦める（元画像をそのまま使う）。
	if ( ( $x1 - $x0 ) < $sw * 0.15 || ( $y1 - $y0 ) < $sh * 0.15 ) {
		return null;
	}

	// 淡い作品は輪郭が内側に寄りやすいため、少しだけ外に広げる。
	$pad_x = ( $x1 - $x0 ) * 0.02;
	$pad_y = ( $y1 - $y0 ) * 0.02;
	$x0    = max( 0, $x0 - $pad_x );
	$y0    = max( 0, $y0 - $pad_y );
	$x1    = min( $sw - 1, $x1 + $pad_x );
	$y1    = min( $sh - 1, $y1 + $pad_y );

	$sx = $w_src / $sw;
	$sy = $h_src / $sh;

	return array(
		'x' => (int) round( $x0 * $sx ),
		'y' => (int) round( $y0 * $sy ),
		'w' => (int) round( ( $x1 - $x0 + 1 ) * $sx ),
		'h' => (int) round( ( $y1 - $y0 + 1 ) * $sy ),
		'W' => $w_src,
		'H' => $h_src,
	);
}

/**
 * 作品のメイン画像から「絵だけ」の画像を生成し、メディアに登録する。
 *
 * @param int $post_id 作品（art）の投稿ID。
 * @return int|WP_Error 生成した添付ファイルID。
 */
function bankofart_generate_top_crop( $post_id ) {
	$main_id = (int) get_post_meta( $post_id, 'art_main_image', true );
	if ( ! $main_id ) {
		return new WP_Error( 'boa_crop_no_main', 'メイン画像が設定されていません。' );
	}

	$src = get_attached_file( $main_id );
	if ( ! $src || ! file_exists( $src ) ) {
		return new WP_Error( 'boa_crop_no_file', '元画像のファイルが見つかりません。' );
	}

	$box = bankofart_detect_artwork_box( $src );
	if ( ! $box ) {
		return new WP_Error( 'boa_crop_undetected', '絵の範囲を検出できませんでした。' );
	}

	$editor = wp_get_image_editor( $src );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}

	$editor->crop( $box['x'], $box['y'], $box['w'], $box['h'] );
	$editor->resize( 1400, 1400, false ); // TOPのタイル用。長辺1400pxで十分。

	/*
	 * いったん一時ファイルに書き出し、media_handle_sideload で登録する。
	 * wp_insert_attachment に絶対パスを渡すと、Windows 環境では
	 * _wp_attached_file が壊れた相対パス（ヌルバイト混じり）になることがあるため。
	 */
	$tmp   = wp_tempnam( 'boa-top-crop.jpg' );
	$saved = $editor->save( $tmp, 'image/jpeg' );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}

	/*
	 * 既存の自動生成分があれば片付ける。
	 * 添付の登録が壊れている（_wp_attached_file が無い等）と wp_delete_attachment が
	 * 途中で致命的エラーになるため、ファイルパスが取れるものだけ削除する。
	 */
	$old = (int) get_post_meta( $post_id, BANKOFART_TOP_CROP_META, true );
	if ( $old && $old !== (int) $main_id ) {
		$old_file = get_attached_file( $old );
		if ( $old_file && is_string( $old_file ) && false === strpos( $old_file, "\0" ) && file_exists( $old_file ) ) {
			wp_delete_attachment( $old, true );
		} else {
			wp_delete_post( $old, true ); // ファイルが辿れない壊れた添付は投稿だけ消す。
		}
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$filename = sanitize_file_name( pathinfo( $src, PATHINFO_FILENAME ) . '-top.jpg' );

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $filename,
			'tmp_name' => $saved['path'],
		),
		$post_id,
		get_the_title( $post_id ) . '（TOP用トリミング）'
	);
	if ( is_wp_error( $attachment_id ) ) {
		if ( file_exists( $saved['path'] ) ) {
			wp_delete_file( $saved['path'] );
		}
		return $attachment_id;
	}

	update_post_meta( $post_id, BANKOFART_TOP_CROP_META, (int) $attachment_id );
	update_post_meta( $post_id, BANKOFART_TOP_CROP_SRC_META, $main_id );

	return (int) $attachment_id;
}

/**
 * TOPのフレームに使う画像を返す。
 *
 * @param int    $post_id 作品の投稿ID。
 * @param string $size    画像サイズ。
 * @return array bankofart_get_image() と同じ形式。
 */
function bankofart_get_art_top_image( $post_id, $size = 'large' ) {
	// 1. 管理画面で明示指定された画像を最優先。
	$manual = bankofart_get_image( 'art_top_image', $post_id, $size );
	if ( ! empty( $manual['url'] ) ) {
		return $manual;
	}

	// 2. 自動生成した切り出し画像。
	$crop_id = (int) get_post_meta( $post_id, BANKOFART_TOP_CROP_META, true );
	if ( $crop_id ) {
		$url = wp_get_attachment_image_url( $crop_id, $size );
		if ( $url ) {
			return array(
				'url' => $url,
				'alt' => get_the_title( $post_id ),
				'id'  => $crop_id,
			);
		}
	}

	// 3. フォールバック：メイン画像そのまま。
	return bankofart_get_image( 'art_main_image', $post_id, $size );
}

/**
 * 保存時に、TOP表示ONの作品だけ切り出し画像を用意する。
 *
 * メイン画像が変わっていない場合は作り直さない。
 *
 * @param int $post_id 投稿ID。
 * @return void
 */
function bankofart_maybe_generate_top_crop( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'art' !== get_post_type( $post_id ) ) {
		return;
	}
	if ( '1' !== (string) get_post_meta( $post_id, 'art_top_featured', true ) ) {
		return;
	}

	$main_id = (int) get_post_meta( $post_id, 'art_main_image', true );
	if ( ! $main_id ) {
		return;
	}

	// 明示指定があるなら自動生成は不要。
	$manual = (int) get_post_meta( $post_id, 'art_top_image', true );
	if ( $manual ) {
		return;
	}

	$done_for = (int) get_post_meta( $post_id, BANKOFART_TOP_CROP_SRC_META, true );
	$existing = (int) get_post_meta( $post_id, BANKOFART_TOP_CROP_META, true );
	if ( $existing && $done_for === $main_id ) {
		return; // 生成済みで元画像も同じ。
	}

	bankofart_generate_top_crop( $post_id );
}
add_action( 'rwmb_after_save_post', 'bankofart_maybe_generate_top_crop', 30 );
add_action( 'save_post_art', 'bankofart_maybe_generate_top_crop', 99 );
