<?php
/**
 * コレクションサイト同期：作品の切り抜き画像を生成する
 *
 * collection 側の切り抜きは手作業で、しかも自由変形（四隅を別々に動かす）で指定されている。
 * 壁に掛けた作品を斜めから撮った写真を、正面から見た長方形に起こすための指定なので、
 * 縦横まっすぐの長方形では代用できない（台形のまま余白が残る／作品が欠ける）。
 *
 * その4点は collection のページに数値で埋まっているため、同期時に取り込んで、
 * こちらで同じ変形（射影変換）をかけた画像を作り、uploads に置いて配信する。
 * 一覧カードは出来上がった画像をそのまま並べるだけになる。
 *
 * ※ collection 自身の一覧カードは4点の外接長方形しか使っておらず、作品のまわりに
 *   壁や額縁が残る。ここで作るのは作品ページと同じ「起こしたあと」の見え方。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 生成する画像の長辺（px）。カードの実寸は400px前後なので、高精細表示でも足りる。 */
define( 'BANKOFART_COLLECTION_RECT_LONG', 1000 );

/** 生成物の世代。変換の中身を変えたときに上げると、全点が作り直しになる。 */
define( 'BANKOFART_COLLECTION_RECT_GEN', '1' );

/**
 * 生成画像を置くディレクトリを返す（無ければ作る）。
 *
 * @return array|WP_Error array( 'dir' => 絶対パス, 'url' => URL )。
 */
function bankofart_collection_rect_dir() {
	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) {
		return new WP_Error( 'boa_upload_dir', $up['error'] );
	}
	$dir = trailingslashit( $up['basedir'] ) . 'boa-collection';
	if ( ! file_exists( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'boa_upload_dir', 'uploads/boa-collection を作成できませんでした。' );
	}
	return array(
		'dir' => $dir,
		'url' => trailingslashit( $up['baseurl'] ) . 'boa-collection',
	);
}

/**
 * 1点ぶんの生成画像のファイル名を返す。
 *
 * @param string $collection_id BOA-2026-00661 形式。
 * @return string
 */
function bankofart_collection_rect_filename( $collection_id ) {
	return sanitize_file_name( $collection_id ) . '.jpg';
}

/**
 * その行の切り抜き指定に対する識別子を返す。
 *
 * 元写真・4点・比率のいずれかが変われば値が変わるので、これを保存しておけば
 * 「作り直しが要るか」を毎回の同期で判定できる。
 *
 * @param array $row 作品1行。
 * @return string 空文字なら生成対象外（4点が無い）。
 */
function bankofart_collection_rect_key( array $row ) {
	if ( empty( $row['crop_quad'] ) || empty( $row['source_url'] ) ) {
		return '';
	}
	return md5(
		BANKOFART_COLLECTION_RECT_GEN . '|' . BANKOFART_COLLECTION_RECT_LONG . '|'
		. $row['source_url'] . '|' . $row['crop_quad'] . '|' . (string) ( $row['crop_ratio'] ?? '' )
	);
}

/**
 * 生成済み画像のURLを返す。まだ無ければ空文字。
 *
 * @param array $row 作品1行。
 * @return string
 */
function bankofart_collection_rect_url( array $row ) {
	if ( empty( $row['rect_key'] ) || empty( $row['collection_id'] ) ) {
		return '';
	}
	$paths = bankofart_collection_rect_dir();
	if ( is_wp_error( $paths ) ) {
		return '';
	}
	$name = bankofart_collection_rect_filename( $row['collection_id'] );
	if ( ! file_exists( $paths['dir'] . '/' . $name ) ) {
		return '';
	}
	// 作り直したときにブラウザ側の古い画像が残らないよう、識別子を付ける。
	return $paths['url'] . '/' . $name . '?v=' . substr( $row['rect_key'], 0, 8 );
}

/**
 * GDで画像を開く（JPEG／PNG／WebPに対応）。
 *
 * @param string $file ファイルパス。
 * @return resource|GdImage|WP_Error
 */
function bankofart_collection_open_image( $file ) {
	$info = @getimagesize( $file );
	if ( ! $info ) {
		return new WP_Error( 'boa_image', '画像として読めませんでした。' );
	}
	switch ( $info[2] ) {
		case IMAGETYPE_JPEG:
			$img = @imagecreatefromjpeg( $file );
			break;
		case IMAGETYPE_PNG:
			$img = @imagecreatefrompng( $file );
			break;
		case IMAGETYPE_WEBP:
			$img = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false;
			break;
		default:
			$img = false;
	}
	if ( ! $img ) {
		return new WP_Error( 'boa_image', '対応していない画像形式です。' );
	}
	return $img;
}

/**
 * 4点で囲まれた領域を、指定の縦横比の長方形へ起こす。
 *
 * 単位正方形 (0,0)(1,0)(1,1)(0,1) を4点へ写す射影変換を求め、出力側の各画素から
 * 元写真の位置を逆算して色を拾う（双一次補間）。台形のゆがみもこれで直る。
 *
 * @param resource|GdImage $src   元画像。
 * @param array            $quad  [[x,y],…] 4点。0〜1の割合で、左上→右上→右下→左下の順。
 * @param float            $ratio 出来上がりの縦横比（幅÷高さ）。作品の実寸から来る。
 * @return resource|GdImage|WP_Error
 */
function bankofart_collection_rectify( $src, array $quad, $ratio ) {
	if ( 4 !== count( $quad ) ) {
		return new WP_Error( 'boa_crop', '4点そろっていません。' );
	}
	$ratio = (float) $ratio;
	if ( $ratio <= 0 ) {
		return new WP_Error( 'boa_crop', '縦横比が不正です。' );
	}

	$sw   = imagesx( $src );
	$sh   = imagesy( $src );
	$long = BANKOFART_COLLECTION_RECT_LONG;
	if ( $ratio >= 1 ) {
		$ow = $long;
		$oh = max( 1, (int) round( $long / $ratio ) );
	} else {
		$oh = $long;
		$ow = max( 1, (int) round( $long * $ratio ) );
	}

	// 4点を画素座標へ。
	$x0 = $quad[0][0] * $sw;
	$y0 = $quad[0][1] * $sh;
	$x1 = $quad[1][0] * $sw;
	$y1 = $quad[1][1] * $sh;
	$x2 = $quad[2][0] * $sw;
	$y2 = $quad[2][1] * $sh;
	$x3 = $quad[3][0] * $sw;
	$y3 = $quad[3][1] * $sh;

	// 射影変換の係数（Heckbert の「単位正方形 → 任意の四角形」）。
	$dx1 = $x1 - $x2;
	$dx2 = $x3 - $x2;
	$dx3 = $x0 - $x1 + $x2 - $x3;
	$dy1 = $y1 - $y2;
	$dy2 = $y3 - $y2;
	$dy3 = $y0 - $y1 + $y2 - $y3;

	if ( abs( $dx3 ) < 1e-9 && abs( $dy3 ) < 1e-9 ) {
		// 平行四辺形のときは遠近成分が無く、ただのアフィン変換になる。
		$a11 = $x1 - $x0;
		$a21 = $x3 - $x0;
		$a31 = $x0;
		$a12 = $y1 - $y0;
		$a22 = $y3 - $y0;
		$a32 = $y0;
		$a13 = 0.0;
		$a23 = 0.0;
	} else {
		$den = $dx1 * $dy2 - $dy1 * $dx2;
		if ( abs( $den ) < 1e-9 ) {
			return new WP_Error( 'boa_crop', '4点が一直線に並んでいます。' );
		}
		$a13 = ( $dx3 * $dy2 - $dy3 * $dx2 ) / $den;
		$a23 = ( $dx1 * $dy3 - $dy1 * $dx3 ) / $den;
		$a11 = $x1 - $x0 + $a13 * $x1;
		$a21 = $x3 - $x0 + $a23 * $x3;
		$a31 = $x0;
		$a12 = $y1 - $y0 + $a13 * $y1;
		$a22 = $y3 - $y0 + $a23 * $y3;
		$a32 = $y0;
	}

	$out  = imagecreatetruecolor( $ow, $oh );
	$maxx = $sw - 1;
	$maxy = $sh - 1;

	for ( $py = 0; $py < $oh; $py++ ) {
		$t = ( $py + 0.5 ) / $oh;
		// 1行ぶんは s について線形なので、行頭の値と1画素ぶんの増分だけ出して足していく。
		$du = $a11 / $ow;
		$dv = $a12 / $ow;
		$dw = $a13 / $ow;
		$hu = $a21 * $t + $a31 + $du * 0.5;
		$hv = $a22 * $t + $a32 + $dv * 0.5;
		$hw = $a23 * $t + 1.0 + $dw * 0.5;

		for ( $px = 0; $px < $ow; $px++ ) {
			$u = $hw ? $hu / $hw : 0;
			$v = $hw ? $hv / $hw : 0;
			$hu += $du;
			$hv += $dv;
			$hw += $dw;

			if ( $u < 0 ) {
				$u = 0;
			} elseif ( $u > $maxx ) {
				$u = $maxx;
			}
			if ( $v < 0 ) {
				$v = 0;
			} elseif ( $v > $maxy ) {
				$v = $maxy;
			}

			$iu  = (int) $u;
			$iv  = (int) $v;
			$fu  = $u - $iu;
			$fv  = $v - $iv;
			$iu1 = $iu < $maxx ? $iu + 1 : $iu;
			$iv1 = $iv < $maxy ? $iv + 1 : $iv;

			$c00 = imagecolorat( $src, $iu, $iv );
			$c10 = imagecolorat( $src, $iu1, $iv );
			$c01 = imagecolorat( $src, $iu, $iv1 );
			$c11 = imagecolorat( $src, $iu1, $iv1 );

			$w00 = ( 1 - $fu ) * ( 1 - $fv );
			$w10 = $fu * ( 1 - $fv );
			$w01 = ( 1 - $fu ) * $fv;
			$w11 = $fu * $fv;

			$r = (int) ( ( ( $c00 >> 16 ) & 255 ) * $w00 + ( ( $c10 >> 16 ) & 255 ) * $w10 + ( ( $c01 >> 16 ) & 255 ) * $w01 + ( ( $c11 >> 16 ) & 255 ) * $w11 );
			$g = (int) ( ( ( $c00 >> 8 ) & 255 ) * $w00 + ( ( $c10 >> 8 ) & 255 ) * $w10 + ( ( $c01 >> 8 ) & 255 ) * $w01 + ( ( $c11 >> 8 ) & 255 ) * $w11 );
			$b = (int) ( ( $c00 & 255 ) * $w00 + ( $c10 & 255 ) * $w10 + ( $c01 & 255 ) * $w01 + ( $c11 & 255 ) * $w11 );

			imagesetpixel( $out, $px, $py, ( $r << 16 ) | ( $g << 8 ) | $b );
		}
	}

	return $out;
}

/**
 * 1点ぶんの切り抜き画像を作って uploads に保存する。
 *
 * @param array $row 作品1行（source_url・crop_quad・crop_ratio が要る）。
 * @return true|WP_Error
 */
function bankofart_collection_build_rect( array $row ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return new WP_Error( 'boa_gd', 'GD拡張が有効ではありません。' );
	}
	$key = bankofart_collection_rect_key( $row );
	if ( '' === $key ) {
		return new WP_Error( 'boa_crop', '切り抜きの4点がありません。' );
	}
	$quad = json_decode( (string) $row['crop_quad'], true );
	if ( ! is_array( $quad ) || 4 !== count( $quad ) ) {
		return new WP_Error( 'boa_crop', '切り抜きの4点を読めませんでした。' );
	}
	$paths = bankofart_collection_rect_dir();
	if ( is_wp_error( $paths ) ) {
		return $paths;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = download_url( $row['source_url'], 60 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$src = bankofart_collection_open_image( $tmp );
	if ( is_wp_error( $src ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $src;
	}

	$out = bankofart_collection_rectify( $src, $quad, $row['crop_ratio'] );
	imagedestroy( $src );
	@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( is_wp_error( $out ) ) {
		return $out;
	}

	$file = $paths['dir'] . '/' . bankofart_collection_rect_filename( $row['collection_id'] );
	$ok   = imagejpeg( $out, $file, 88 );
	imagedestroy( $out );
	if ( ! $ok ) {
		return new WP_Error( 'boa_image', '画像を保存できませんでした： ' . $file );
	}

	global $wpdb;
	$wpdb->update( bankofart_collection_table(), array( 'rect_key' => $key ), array( 'collection_id' => $row['collection_id'] ), array( '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return true;
}

/**
 * 生成済みかどうかを判定する。
 *
 * @param array  $row 作品1行。
 * @param string $dir 生成先ディレクトリ（空なら未生成扱い）。
 * @return bool
 */
function bankofart_collection_rect_is_built( array $row, $dir ) {
	$key = bankofart_collection_rect_key( $row );
	if ( '' === $key || '' === $dir ) {
		return false;
	}
	return ( $key === (string) $row['rect_key'] )
		&& file_exists( $dir . '/' . bankofart_collection_rect_filename( $row['collection_id'] ) );
}

/**
 * 生成状況を数える。
 *
 * @return array array( 'total' => 対象総数, 'pending' => 未生成数 )。
 */
function bankofart_collection_rect_status() {
	global $wpdb;
	$table = bankofart_collection_table();
	$rows  = $wpdb->get_results( "SELECT collection_id, source_url, crop_quad, crop_ratio, rect_key FROM {$table} WHERE crop_quad IS NOT NULL AND crop_quad <> ''", ARRAY_A ); // phpcs:ignore WordPress.DB

	$paths   = bankofart_collection_rect_dir();
	$dir     = is_wp_error( $paths ) ? '' : $paths['dir'];
	$pending = 0;
	foreach ( $rows as $r ) {
		if ( ! bankofart_collection_rect_is_built( $r, $dir ) ) {
			$pending++;
		}
	}
	return array(
		'total'   => count( $rows ),
		'pending' => $pending,
	);
}

/**
 * 未生成ぶんを、与えられた時間の範囲で作る。
 *
 * 1点あたり「ダウンロード＋変換」で1〜2秒かかるため、実行時間の上限に当たらないよう
 * 時間を区切って進め、残りは次回に回す（5分後の単発イベントを予約する）。
 *
 * @param int $budget 使ってよい秒数。
 * @return array array( 'built', 'failed', 'pending', 'errors' )。
 */
function bankofart_collection_build_pending( $budget = 40 ) {
	global $wpdb;
	$table = bankofart_collection_table();
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE crop_quad IS NOT NULL AND crop_quad <> '' ORDER BY artist_slug ASC, sort_order ASC", ARRAY_A ); // phpcs:ignore WordPress.DB

	$paths  = bankofart_collection_rect_dir();
	$dir    = is_wp_error( $paths ) ? '' : $paths['dir'];
	$start  = microtime( true );
	$built  = 0;
	$failed = 0;
	$errors = array();
	$left   = 0;

	foreach ( $rows as $row ) {
		if ( bankofart_collection_rect_is_built( $row, $dir ) ) {
			continue;
		}
		if ( '' === bankofart_collection_rect_key( $row ) ) {
			continue;
		}
		if ( ( microtime( true ) - $start ) > $budget ) {
			$left++;
			continue;
		}
		$res = bankofart_collection_build_rect( $row );
		if ( is_wp_error( $res ) ) {
			$failed++;
			if ( count( $errors ) < 10 ) {
				$errors[] = $row['collection_id'] . '： ' . $res->get_error_message();
			}
			continue;
		}
		$built++;
	}

	// 残っていれば、続きを後で自動的に進める。
	if ( $left > 0 && ! wp_next_scheduled( 'boa_collection_build_rects' ) ) {
		wp_schedule_single_event( time() + 300, 'boa_collection_build_rects' );
	}

	return array(
		'built'   => $built,
		'failed'  => $failed,
		'pending' => $left,
		'errors'  => $errors,
	);
}

add_action( 'boa_collection_build_rects', 'bankofart_collection_build_pending' );
