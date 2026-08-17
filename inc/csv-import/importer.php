<?php
/**
 * CSVインポート：読み込み・マッピング・取り込み処理
 *
 * 画家申請フォーム（GAS）が貯めているスプレッドシートから書き出したCSVを、
 * ARTIST / ART の投稿として取り込む。同じ行を二重に取り込まないよう、
 * 各行の「タイムスタンプ列」を一意キーにして投稿メタ（_bankofart_import_key）に
 * 記録し、既に同じキーを持つ投稿があればスキップする。
 *
 * 画面（管理メニュー）は inc/csv-import/admin.php。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 取り込み済みキーを記録する投稿メタのキー名。 */
const BANKOFART_CSV_IMPORT_KEY_META = '_bankofart_import_key';

/** 取り込み元CSVの列名等を記録する投稿メタのキー名（監査用）。 */
const BANKOFART_CSV_IMPORT_LOG_META = '_bankofart_import_source';

/** 1回の実行で処理する最大行数（タイムアウト回避）。 */
const BANKOFART_CSV_MAX_ROWS = 300;

/**
 * 一意キー（重複判定）に使う列名の候補。
 *
 * Googleフォーム／スプレッドシートの既定名と、手書きCSVの一般的な名前を並べる。
 *
 * @return string[]
 */
function bankofart_csv_timestamp_aliases() {
	return array(
		'タイムスタンプ',
		'タイム スタンプ',
		'timestamp',
		'time stamp',
		'送信日時',
		'受付日時',
		'登録日時',
		'申請日時',
		'日時',
		'submitted_at',
		'created_at',
	);
}

/**
 * ヘッダー名・キーを比較用に正規化する。
 *
 * 前後空白・全角空白・BOM・大文字小文字・括弧の全半角差を吸収する。
 *
 * @param string $key 列名。
 * @return string 正規化済みの列名。
 */
function bankofart_csv_normalize_key( $key ) {
	$key = (string) $key;
	$key = str_replace( array( "\xEF\xBB\xBF", "\xc2\xa0" ), '', $key );
	$key = str_replace( array( '　', '（', '）', '・' ), array( ' ', '(', ')', '' ), $key );
	$key = trim( $key );

	if ( function_exists( 'mb_strtolower' ) ) {
		$key = mb_strtolower( $key, 'UTF-8' );
	} else {
		$key = strtolower( $key );
	}

	return preg_replace( '/\s+/u', ' ', $key );
}

/**
 * CSVファイルを読み込み、ヘッダー付きの連想配列にする。
 *
 * 文字コードは UTF-8（BOM有無問わず）と SJIS-win を自動判別する。
 *
 * @param string $path アップロードされた一時ファイルのパス。
 * @return array|WP_Error array( 'headers' => string[], 'rows' => array[] )。
 */
function bankofart_csv_read_file( $path ) {
	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'boa_csv_unreadable', 'CSVファイルを読み込めませんでした。' );
	}

	$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- ローカル一時ファイル。
	if ( false === $raw || '' === $raw ) {
		return new WP_Error( 'boa_csv_empty', 'CSVファイルが空です。' );
	}

	// BOM を除去。
	if ( 0 === strncmp( $raw, "\xEF\xBB\xBF", 3 ) ) {
		$raw = substr( $raw, 3 );
	}

	// Excel 保存の SJIS を UTF-8 に変換する（UTF-8 として妥当ならそのまま）。
	if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $raw, 'UTF-8' ) ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'SJIS-win, eucJP-win, UTF-8' );
	}

	// 改行コードを LF に統一（セル内改行も含めて fgetcsv が正しく扱えるようにする）。
	$raw = str_replace( array( "\r\n", "\r" ), "\n", $raw );

	$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- メモリストリーム。
	if ( ! $handle ) {
		return new WP_Error( 'boa_csv_stream', 'CSVの解析に失敗しました。' );
	}
	fwrite( $handle, $raw ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- メモリストリーム。
	rewind( $handle );

	$headers = fgetcsv( $handle );
	if ( ! $headers ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- メモリストリーム。
		return new WP_Error( 'boa_csv_no_header', 'CSVの1行目（見出し行）が読み取れませんでした。' );
	}

	/*
	 * 見出しの空欄・重複を一意化する。
	 * スプレッドシートの結合セル（例：「住所」の右に空欄が4つ続く）をそのまま
	 * array_combine すると列が潰れて値が失われるため、内部用の名前を割り当てる。
	 */
	$headers = array_map( 'trim', (array) $headers );
	$seen    = array();
	foreach ( $headers as $i => $header ) {
		if ( '' === $header ) {
			$headers[ $i ] = sprintf( '__col%d', $i + 1 );
			continue;
		}
		$norm = bankofart_csv_normalize_key( $header );
		if ( isset( $seen[ $norm ] ) ) {
			++$seen[ $norm ];
			$headers[ $i ] = $header . '__' . $seen[ $norm ];
		} else {
			$seen[ $norm ] = 1;
		}
	}

	$rows  = array();
	$count = count( $headers );
	while ( false !== ( $line = fgetcsv( $handle ) ) ) {
		// 完全な空行は無視する。
		if ( 1 === count( $line ) && ( null === $line[0] || '' === trim( (string) $line[0] ) ) ) {
			continue;
		}
		// 列数の過不足を吸収する。
		$line = array_slice( array_pad( (array) $line, $count, '' ), 0, $count );
		$rows[] = array_combine( $headers, $line );
	}
	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- メモリストリーム。

	if ( empty( $rows ) ) {
		return new WP_Error( 'boa_csv_no_rows', 'データ行がありません（見出し行のみのCSVです）。' );
	}

	return array(
		'headers' => $headers,
		'rows'    => $rows,
	);
}

/**
 * 重複判定に使う列名を見つける。
 *
 * @param string[] $headers ヘッダー行。
 * @return string 見つかった列名。見つからなければ空文字。
 */
function bankofart_csv_detect_timestamp_column( $headers ) {
	$aliases = array_map( 'bankofart_csv_normalize_key', bankofart_csv_timestamp_aliases() );

	foreach ( (array) $headers as $header ) {
		if ( in_array( bankofart_csv_normalize_key( $header ), $aliases, true ) ) {
			return $header;
		}
	}

	return '';
}

/**
 * 行から、列名候補のいずれかに一致する値を取り出す。
 *
 * @param array    $row     1行分の連想配列。
 * @param string[] $aliases 列名候補。
 * @return string 値（見つからなければ空文字）。
 */
function bankofart_csv_pick( $row, $aliases ) {
	static $normalized_cache = array();

	$cache_key = md5( wp_json_encode( array_keys( $row ) ) );
	if ( ! isset( $normalized_cache[ $cache_key ] ) ) {
		$map = array();
		foreach ( $row as $key => $unused ) {
			$map[ bankofart_csv_normalize_key( $key ) ] = $key;
		}
		$normalized_cache[ $cache_key ] = $map;
	}
	$map = $normalized_cache[ $cache_key ];

	foreach ( (array) $aliases as $alias ) {
		$norm = bankofart_csv_normalize_key( $alias );
		if ( isset( $map[ $norm ] ) ) {
			$value = trim( (string) $row[ $map[ $norm ] ] );
			if ( '' !== $value ) {
				return $value;
			}
		}
	}

	return '';
}

/**
 * 複数値（ジャンル・タグ等）を配列に分割する。
 *
 * 区切りは ; | , 、 / と改行に対応。
 *
 * @param string $value セルの値。
 * @return string[]
 */
function bankofart_csv_split_multi( $value ) {
	$value = (string) $value;

	/*
	 * ハッシュタグ形式（例：継ぎ接ぎ#再生#偶然）で書かれることがあるため # も区切りに含める。
	 * ただしURL（画像列）にはフラグメントとして # が入りうるので、URLらしき値では使わない。
	 */
	$pattern = ( false !== strpos( $value, '://' ) )
		? '~[;|,、／/\r\n]+~u'
		: '~[;|,、／/#＃\r\n]+~u';

	$parts = preg_split( $pattern, $value );
	$parts = array_map( 'trim', (array) $parts );

	return array_values( array_filter( $parts, static function ( $v ) {
		return '' !== $v;
	} ) );
}

/* =========================================================
 * 導出フィールド（CSVに専用列が無いものを他の列から生成する）
 * ======================================================= */

/**
 * 活動名（英字）を「アーティスト名」列から導出する。
 *
 * サイト表記ルール（CLAUDE.md）に従い英字はすべて大文字にする。
 * 全角英数字は半角に正規化してから大文字化する。
 *
 * ⚠️ アーティスト名が日本語の場合は変換せず空を返す（人が手入力する）。
 *    フリガナ列は「本名の読み」であり、そこから英字名を作ると本名が
 *    公開フィールドに出てしまうため、絶対に使わない。
 *
 * @param array $row 1行分の連想配列。
 * @param array $def 種別定義。
 * @return array array( 'value' => string, 'note' => string )
 */
function bankofart_csv_derive_artist_name_en( $row, $def ) {
	$name = bankofart_csv_pick( $row, $def['title'] );
	if ( '' === $name ) {
		return array(
			'value' => '',
			'note'  => '',
		);
	}

	// 全角英数字・記号を半角へ（例：ＴＡＩＫＩ → TAIKI）。
	if ( function_exists( 'mb_convert_kana' ) ) {
		$name = mb_convert_kana( $name, 'as', 'UTF-8' );
	}
	$name = trim( $name );

	// ラテン文字・数字と一般的な区切りだけで構成されているか。
	if ( ! preg_match( "/^[A-Za-z0-9 .'&_-]+$/", $name ) ) {
		return array(
			'value' => '',
			'note'  => sprintf( '活動名（英字）は自動生成できませんでした（アーティスト名「%s」が英字ではないため）。編集画面で入力してください。', $name ),
		);
	}

	$upper = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $name, 'UTF-8' ) : strtoupper( $name );

	return array(
		'value' => $upper,
		'note'  => '',
	);
}

/**
 * テーマキーワードの値をカンマ区切りに整形する。
 *
 * 表示側（single-artist.php）は explode(',') で分解するため、カンマ以外の区切りで
 * 書かれていると1個のチップにまとまってしまう。ハッシュタグ形式
 * （継ぎ接ぎ#再生#偶然）や読点区切りも受け付けてカンマに揃える。
 *
 * @param string $value 元の値。
 * @return string カンマ区切りの文字列。
 */
function bankofart_csv_normalize_keywords( $value ) {
	$parts = array_map( 'bankofart_csv_normalize_term_name', bankofart_csv_split_multi( $value ) );
	$parts = array_values( array_unique( array_filter( $parts, 'strlen' ) ) );
	return implode( ',', $parts );
}

/**
 * テーマキーワードを「診断タグ」列から導出する。
 *
 * 診断タグとテーマキーワードは同じ語彙を使う運用のため、専用列が無ければ
 * 診断タグの内容をそのままカンマ区切りで入れる（例：生命エネルギー,挑戦,格闘）。
 * 表示側（single-artist.php）は explode(',') で分解して「# 挑戦」のように出す。
 *
 * @param array $row 1行分の連想配列。
 * @param array $def 種別定義。
 * @return array array( 'value' => string, 'note' => string )
 */
function bankofart_csv_derive_artist_theme_keywords( $row, $def ) {
	$raw = bankofart_csv_pick( $row, $def['tax']['artist_diagnosis_tag'] );
	if ( '' === $raw ) {
		return array(
			'value' => '',
			'note'  => '',
		);
	}

	return array(
		'value' => bankofart_csv_normalize_keywords( $raw ),
		'note'  => '',
	);
}

/* =========================================================
 * 列マッピング定義
 * ======================================================= */

/**
 * 取り込み種別の定義を返す。
 *
 * @return array 種別キー => 定義。
 */
function bankofart_csv_import_types() {
	return array(
		'artist' => array(
			'label'     => 'ARTIST（アーティスト）',
			'post_type' => 'artist',
			// 投稿タイトルは必ず「アーティスト名（活動名）」列を使う。
			// 苗字・名前（本名）は絶対にタイトルに使わない（本名公開事故の防止）。
			'title'     => array( 'アーティスト名', '活動名', '活動名(アーティスト名)', 'artist_name', 'post_title', 'タイトル', '作家名' ),
			'slug'      => array( 'post_name', 'slug', 'スラッグ' ),
			'meta'      => array(
				'artist_name_en'           => array( 'artist_name_en', 'name_en', '英語名', 'アーティスト名英語', 'アーティスト名(英語)', 'アーティスト名英字' ),
				/*
				 * キャッチフレーズは専用列があればそれを使い、無ければ
				 * 「制作テーマ（13字以内）」を流用する（申請フォームに専用項目が無いため）。
				 * bankofart_csv_pick() は先に一致した列を採るので、専用列を前に置く。
				 */
				'artist_catch_phrase'      => array( 'artist_catch_phrase', 'catch_phrase', 'catch', 'キャッチコピー', 'キャッチフレーズ', '制作テーマ（13字以内）', '制作テーマ' ),
				'artist_birthplace'        => array( 'artist_birthplace', 'birthplace', '出身地' ),
				'artist_theme_short'       => array( 'artist_theme_short', 'theme_short', '制作テーマ', '制作テーマ(13字以内)' ),
				'artist_theme_long'        => array( 'artist_theme_long', 'theme_long', '制作テーマ詳細' ),
				'artist_theme_keywords'    => array( 'artist_theme_keywords', 'theme_keywords', 'キーワード', 'テーマキーワード' ),
				'artist_reason'            => array( 'artist_reason', 'reason', 'なぜ描くか', 'なぜ描くのか' ),
				'artist_origin_story'      => array( 'artist_origin_story', 'origin', 'origin_story', '起源の物語' ),
				'artist_goal'              => array( 'artist_goal', 'goal', '画家としての目標・ゴール', '画家としての目標', '目標', 'ゴール' ),
				'artist_education'         => array( 'artist_education', 'education', 'career', '経歴', '学歴', '学歴 経歴' ),
				'artist_solo_exhibitions'  => array( 'artist_solo_exhibitions', 'solo_exh', 'solo', '個展歴', '個展' ),
				'artist_group_exhibitions' => array( 'artist_group_exhibitions', 'group_exh', 'group', 'グループ展歴', 'グループ展' ),
				'artist_media_awards'      => array( 'artist_media_awards', 'awards', '受賞・メディア歴', 'メディア・受賞', '受賞歴', '受賞', 'メディア掲載' ),
				'artist_other_activities'  => array( 'artist_other_activities', 'others', 'その他活動' ),
				'artist_resonance_message' => array( 'artist_resonance_message', 'resonance', '共鳴文章', '共鳴文', '共鳴メッセージ', '共鳴文章（診断結果用）' ),
				'artist_video_url'         => array( 'artist_video_url', 'video', '動画url', 'プロフィール動画url' ),
				'artist_sns_instagram'     => array( 'artist_sns_instagram', 'sns_instagram', 'instagram' ),
				'artist_sns_x'             => array( 'artist_sns_x', 'sns_x', 'x', 'twitter' ),
				'artist_sns_facebook'      => array( 'artist_sns_facebook', 'sns_facebook', 'facebook' ),
				'artist_sns_youtube'       => array( 'artist_sns_youtube', 'sns_youtube', 'youtube' ),
				'artist_sns_other'         => array( 'artist_sns_other', 'sns_other', 'その他url', 'website' ),
			),
			'tax'       => array(
				'artist_status'        => array( 'artist_status', 'status', 'ステータス', 'アーティストステータス' ),
				'artist_genre'         => array( 'artist_genre', 'genre', 'ジャンル' ),
				'artist_diagnosis_tag' => array( 'artist_diagnosis_tag', 'diagnosis_tags', '診断タグ', '診断タグ（3〜6個）', 'マッチングタグ' ),
			),
			'images'    => array(
				'artist_main_photo'     => array( 'single', array( 'artist_main_photo', 'thumbnail', 'main_image', 'メイン画像(1枚)', 'メイン画像', 'メイン写真' ) ),
				'artist_symbol_image'   => array( 'single', array( 'artist_symbol_image', 'symbol_image', '自己を象徴する画像(1枚)', '自己を象徴する画像', '象徴写真' ) ),
				'artist_gallery_photos' => array( 'multi', array( 'artist_gallery_photos', 'gallery', 'ギャラリー写真' ) ),
				'artist_working_photos' => array( 'multi', array( 'artist_working_photos', 'work_images', '制作風景・画材・制作環境の写真（複数可）', '制作風景・画材・制作環境の写真', '制作風景写真', '制作風景' ) ),
			),
			/*
			 * 導出フィールド：CSVに専用列が無いとき、他の列から自動生成する。
			 * 該当する列がCSVにあればそちらが優先される。
			 */
			'derived'   => array(
				'artist_name_en'        => 'bankofart_csv_derive_artist_name_en',
				// テーマキーワードは診断タグと同じ語彙。専用列が無ければ診断タグから流用する。
				'artist_theme_keywords' => 'bankofart_csv_derive_artist_theme_keywords',
			),
			/*
			 * 専用列に値があっても書式を揃える項目。
			 * テーマキーワードは表示側が explode(',') で分解するため、
			 * ハッシュタグ区切り等で来てもカンマ区切りに直す。
			 */
			'normalize' => array(
				'artist_theme_keywords' => 'bankofart_csv_normalize_keywords',
			),
			/*
			 * 意図的に取り込まない列（個人情報・契約情報）。
			 * inc/meta-box-fields.php の方針どおり、本名・連絡先・住所・振込先・生年月日は
			 * WordPress では管理しない。取り込み画面に「取り込まない列」として明示する。
			 */
			'ignored'   => array(
				'苗字', '名前', 'フリガナ', 'メールアドレス', '電話番号', '住所', '性別', '生年月日',
				'銀行名', '支店名', '口座種別', '口座番号', '口座名義（カナ）', '口座名義',
				'個人情報の取り扱い・規約に同意する', '個人情報の取り扱い',
			),
		),
		'art'    => array(
			'label'     => 'ART（作品）',
			'post_type' => 'art',
			'title'     => array( 'post_title', 'タイトル', '作品名', 'art_title' ),
			'slug'      => array( 'post_name', 'slug', 'スラッグ' ),
			'meta'      => array(
				'art_title_en'    => array( 'art_title_en', 'title_en', '作品名英語', '作品名(英語)' ),
				'art_number'      => array( 'art_number', '作品番号' ),
				'art_year'        => array( 'art_year', 'year', '制作年' ),
				'art_medium'      => array( 'art_medium', 'medium', '技法 素材', '素材' ),
				'art_size_detail' => array( 'art_size_detail', 'size_detail', 'サイズ詳細' ),
				'art_size_label'  => array( 'art_size_label', 'size_label', 'サイズ表記', '号数' ),
				'art_description' => array( 'art_description', 'description', '作品説明' ),
				'art_concept'     => array( 'art_concept', 'concept', 'コンセプト' ),
				'art_series_name' => array( 'art_series_name', 'series', 'シリーズ名' ),
			),
			'tax'       => array(
				'art_status'     => array( 'art_status', 'status', 'ステータス' ),
				'art_form'       => array( 'art_form', 'form', '形態' ),
				'art_genre'      => array( 'art_genre', 'genre', 'ジャンル' ),
				'art_technique'  => array( 'art_technique', 'technique', '技法' ),
				'art_size'       => array( 'art_size', 'size', 'サイズ', '号数区分' ),
				'art_main_color' => array( 'art_main_color', 'main_color', 'color', 'メインカラー' ),
			),
			'images'    => array(
				'art_main_image' => array( 'single', array( 'art_main_image', 'thumbnail', 'main_image', 'メイン画像', '作品画像' ) ),
				'art_gallery'    => array( 'multi', array( 'art_gallery', 'gallery', 'ギャラリー画像' ) ),
			),
			// 作品 → アーティストの関連付け（artist_to_art リレーション）。
			'relation'  => array(
				'id'      => 'artist_to_art',
				'aliases' => array( 'artist_slug', 'artist', 'アーティスト', 'アーティストスラッグ', '画家' ),
			),
		),
	);
}

/**
 * CSVの見出しのうち、どこにも対応付かなかった列名を返す。
 *
 * 「入れたつもりの項目が入っていない」事故に気付けるよう、取り込み結果に表示する。
 *
 * @param string[] $headers   CSVの見出し行。
 * @param string   $type      取り込み種別。
 * @param string   $ts_column 重複判定に使った列名。
 * @return array array( 'ignored' => string[], 'unknown' => string[] )。
 */
function bankofart_csv_unmapped_columns( $headers, $type, $ts_column ) {
	$types = bankofart_csv_import_types();
	if ( ! isset( $types[ $type ] ) ) {
		return array(
			'ignored' => array(),
			'unknown' => array(),
		);
	}
	$def = $types[ $type ];

	// 対応付けに使う全エイリアスを集める。
	$mapped = array_merge( $def['title'], $def['slug'] );
	foreach ( $def['meta'] as $aliases ) {
		$mapped = array_merge( $mapped, $aliases );
	}
	foreach ( $def['tax'] as $aliases ) {
		$mapped = array_merge( $mapped, $aliases );
	}
	foreach ( $def['images'] as $conf ) {
		$mapped = array_merge( $mapped, $conf[1] );
	}
	if ( ! empty( $def['relation'] ) ) {
		$mapped = array_merge( $mapped, $def['relation']['aliases'] );
	}
	$mapped = array_map( 'bankofart_csv_normalize_key', $mapped );

	$ignored_defs = isset( $def['ignored'] ) ? array_map( 'bankofart_csv_normalize_key', $def['ignored'] ) : array();

	$ignored = array();
	$unknown = array();

	foreach ( (array) $headers as $header ) {
		// 見出し空欄に割り当てた内部名と、重複判定に使った列は対象外。
		if ( 0 === strpos( $header, '__col' ) || $header === $ts_column ) {
			continue;
		}
		$norm = bankofart_csv_normalize_key( $header );
		if ( in_array( $norm, $mapped, true ) ) {
			continue;
		}
		if ( in_array( $norm, $ignored_defs, true ) ) {
			$ignored[] = $header;
		} else {
			$unknown[] = $header;
		}
	}

	return array(
		'ignored' => $ignored,
		'unknown' => $unknown,
	);
}

/* =========================================================
 * 取り込み実行
 * ======================================================= */

/**
 * 一意キーから既存投稿を探す（ゴミ箱も含む）。
 *
 * @param string $post_type 投稿タイプ。
 * @param string $key       一意キー。
 * @return int 投稿ID（無ければ 0）。
 */
function bankofart_csv_find_by_import_key( $post_type, $key ) {
	$found = get_posts(
		array(
			'post_type'        => $post_type,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			'meta_key'         => BANKOFART_CSV_IMPORT_KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- 取り込み時のみの管理画面処理。
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- 同上。
		)
	);

	return ! empty( $found ) ? (int) $found[0] : 0;
}

/**
 * 既存タームを名前／スラッグで解決する（新規タームは作らない）。
 *
 * 表記ゆれで不要なタームが増えるのを防ぐため、登録済みのタームだけを割り当てる。
 *
 * @param string   $taxonomy タクソノミー。
 * @param string[] $names    ターム名の配列。
 * @return array array( 'ids' => int[], 'missing' => string[] )。
 */
function bankofart_csv_resolve_terms( $taxonomy, $names ) {
	$ids     = array();
	$missing = array();

	/*
	 * 登録済みターム名を「照合用に正規化した形」で引けるようにしておく。
	 * 診断タグのように語彙が決まっている分類では、全角空白・カギ括弧・引用符・
	 * 中黒・ハッシュなどの装飾が付いているだけで一致せず、まるごと落ちてしまう。
	 */
	static $index = array();
	if ( ! isset( $index[ $taxonomy ] ) ) {
		$index[ $taxonomy ] = array();
		$all = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $all ) ) {
			foreach ( $all as $t ) {
				$index[ $taxonomy ][ bankofart_csv_normalize_term_name( $t->name ) ] = (int) $t->term_id;
			}
		}
	}

	foreach ( (array) $names as $name ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$ids[] = (int) $term->term_id;
			continue;
		}

		// 装飾を落として再照合（「挑戦」→ 挑戦、#挑戦 → 挑戦 など）。
		$norm = bankofart_csv_normalize_term_name( $name );
		if ( '' !== $norm && isset( $index[ $taxonomy ][ $norm ] ) ) {
			$ids[] = $index[ $taxonomy ][ $norm ];
			continue;
		}

		$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			$ids[] = (int) $term->term_id;
			continue;
		}

		$missing[] = $name;
	}

	return array(
		'ids'     => array_values( array_unique( $ids ) ),
		'missing' => $missing,
	);
}

/**
 * ターム名を照合用に正規化する。
 *
 * 前後の装飾（カギ括弧・引用符・#・中黒）と空白を落とし、全角英数字を半角へ。
 *
 * @param string $name ターム名。
 * @return string
 */
function bankofart_csv_normalize_term_name( $name ) {
	$name = (string) $name;
	if ( function_exists( 'mb_convert_kana' ) ) {
		$name = mb_convert_kana( $name, 'as', 'UTF-8' );
	}
	$name = str_replace( array( '　', "\xc2\xa0" ), ' ', $name );
	$name = trim( $name );
	// 前後の装飾記号を除去。
	$name = preg_replace( '/^[\s#＃"\'「『（(【\[]+|[\s"\'」』）)】\]]+$/u', '', $name );
	$name = preg_replace( '/\s+/u', '', $name );
	return $name;
}

/**
 * Google ドライブの共有リンクを直接ダウンロードURLに変換する。
 *
 * 申請フォーム（GAS）が書き込むのは閲覧用リンクのため、そのままでは画像として
 * 取得できない。ファイルIDを抜き出してダウンロード用エンドポイントに組み替える。
 * ※ ファイルが「リンクを知っている全員が閲覧可」になっていない場合は、
 *    変換してもログインページのHTMLが返るため、呼び出し側で中身を検証すること。
 *
 * @param string $url 元のURL。
 * @return string 変換後URL（Googleドライブでなければ元のまま）。
 */
function bankofart_csv_normalize_drive_url( $url ) {
	if ( false === strpos( $url, 'drive.google.com' ) && false === strpos( $url, 'docs.google.com' ) ) {
		return $url;
	}

	$file_id = '';
	if ( preg_match( '~/file/d/([A-Za-z0-9_-]+)~', $url, $m ) ) {
		$file_id = $m[1];
	} elseif ( preg_match( '~[?&]id=([A-Za-z0-9_-]+)~', $url, $m ) ) {
		$file_id = $m[1];
	}

	if ( '' === $file_id ) {
		return $url;
	}

	return 'https://drive.google.com/uc?export=download&id=' . $file_id;
}

/**
 * 画像URLを添付ファイルIDに解決する。
 *
 * 既にメディアライブラリにあるURLはそのIDを使い、外部URLはダウンロードして取り込む。
 * Googleドライブのリンクはダウンロード用URLに変換したうえで、取得したファイルが
 * 本当に画像かどうかを検証する（非公開ファイルだとHTMLが返るため）。
 *
 * @param string $url     画像URL。
 * @param int    $post_id 添付先の投稿ID。
 * @return int|WP_Error 添付ファイルID。
 */
function bankofart_csv_resolve_image( $url, $post_id ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return new WP_Error( 'boa_csv_img_empty', '画像URLが空です。' );
	}

	// サイト相対パス（/wp-content/uploads/...）は絶対URL化する。
	if ( 0 === strpos( $url, '/' ) ) {
		$url = home_url( $url );
	}

	// 既にメディアライブラリにある画像はダウンロードしない。
	$existing = attachment_url_to_postid( $url );
	if ( $existing ) {
		return (int) $existing;
	}

	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	$download_url = bankofart_csv_normalize_drive_url( $url );

	$tmp = download_url( $download_url, 60 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	// 実体が画像かを確認する（Googleドライブの非公開ファイルはHTMLが返る）。
	$size = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- 画像以外なら false を受けて判定する。
	if ( ! $size || empty( $size['mime'] ) ) {
		wp_delete_file( $tmp );
		return new WP_Error(
			'boa_csv_img_not_image',
			'画像として取得できませんでした（Googleドライブの場合、ファイルの共有設定を「リンクを知っている全員が閲覧可」にしてください）。'
		);
	}

	$ext = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);
	if ( ! isset( $ext[ $size['mime'] ] ) ) {
		wp_delete_file( $tmp );
		return new WP_Error( 'boa_csv_img_mime', sprintf( '対応していない画像形式です（%s）。', $size['mime'] ) );
	}

	// ダウンロードURLには拡張子が無いため、MIMEから決めたファイル名を付け直す。
	$filename = sprintf( 'boa-import-%d-%s.%s', $post_id, substr( md5( $url ), 0, 8 ), $ext[ $size['mime'] ] );

	$attachment_id = media_handle_sideload(
		array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		),
		$post_id
	);

	if ( is_wp_error( $attachment_id ) ) {
		wp_delete_file( $tmp );
		return $attachment_id;
	}

	return (int) $attachment_id;
}

/**
 * CSVの行を投稿として取り込む。
 *
 * @param array $rows 行の配列（bankofart_csv_read_file の 'rows'）。
 * @param array $args {
 *     @type string $type        'artist' | 'art'。
 *     @type string $ts_column   重複判定に使う列名（空なら行全体のハッシュ）。
 *     @type string $post_status 新規作成時の投稿ステータス。
 *     @type bool   $dry_run     true なら書き込まず結果だけ返す。
 *     @type bool   $update      true なら既存行を上書き更新、false ならスキップ。
 *     @type bool   $with_images true なら画像URLを取り込む。
 * }
 * @return array 結果レポート。
 */
function bankofart_csv_import_rows( $rows, $args ) {
	$defaults = array(
		'type'        => 'artist',
		'ts_column'   => '',
		'post_status' => 'draft',
		'dry_run'     => true,
		'update'      => false,
		'with_images' => false,
	);
	$args = wp_parse_args( $args, $defaults );

	$types = bankofart_csv_import_types();
	$def   = isset( $types[ $args['type'] ] ) ? $types[ $args['type'] ] : null;

	$report = array(
		'created'  => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'failed'   => 0,
		'truncated' => false,
		'lines'    => array(), // 1行ごとの結果メッセージ。
		'notes'    => array(),
	);

	if ( ! $def ) {
		$report['notes'][] = '取り込み種別が不正です。';
		return $report;
	}

	if ( count( $rows ) > BANKOFART_CSV_MAX_ROWS ) {
		$rows               = array_slice( $rows, 0, BANKOFART_CSV_MAX_ROWS );
		$report['truncated'] = true;
	}

	$row_number = 1; // 見出し行の次から。

	foreach ( $rows as $row ) {
		++$row_number;

		// ---- 一意キー（重複判定）----
		$ts = '' !== $args['ts_column'] && isset( $row[ $args['ts_column'] ] ) ? trim( (string) $row[ $args['ts_column'] ] ) : '';
		if ( '' !== $ts ) {
			$import_key = sha1( $args['type'] . '|ts|' . $ts );
		} else {
			// タイムスタンプが無い行は、行の内容そのものをキーにする。
			$import_key = sha1( $args['type'] . '|row|' . wp_json_encode( $row ) );
		}

		$title = bankofart_csv_pick( $row, $def['title'] );
		$label = '' !== $title ? $title : sprintf( '%d行目', $row_number );

		if ( '' === $title ) {
			++$report['failed'];
			$report['lines'][] = array(
				'status' => 'failed',
				'label'  => $label,
				'note'   => 'タイトル（氏名・作品名）が空のため取り込めません。',
			);
			continue;
		}

		// ---- 既存チェック ----
		$existing_id = bankofart_csv_find_by_import_key( $def['post_type'], $import_key );
		if ( $existing_id && ! $args['update'] ) {
			++$report['skipped'];
			$report['lines'][] = array(
				'status' => 'skipped',
				'label'  => $label,
				'note'   => sprintf( '取り込み済みのためスキップ（投稿ID %d）', $existing_id ),
			);
			continue;
		}

		if ( $args['dry_run'] ) {
			if ( $existing_id ) {
				++$report['updated'];
				$report['lines'][] = array(
					'status' => 'updated',
					'label'  => $label,
					'note'   => sprintf( '既存を更新します（投稿ID %d）', $existing_id ),
				);
			} else {
				++$report['created'];
				$report['lines'][] = array(
					'status' => 'created',
					'label'  => $label,
					'note'   => '新規作成します。',
				);
			}
			continue;
		}

		// ---- 投稿の作成／更新 ----
		$postarr = array(
			'post_type'  => $def['post_type'],
			'post_title' => $title,
		);

		$slug = bankofart_csv_pick( $row, $def['slug'] );
		if ( '' !== $slug ) {
			$postarr['post_name'] = sanitize_title( $slug );
		}

		if ( $existing_id ) {
			$postarr['ID'] = $existing_id;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$postarr['post_status'] = $args['post_status'];
			$post_id                = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			++$report['failed'];
			$report['lines'][] = array(
				'status' => 'failed',
				'label'  => $label,
				'note'   => $post_id->get_error_message(),
			);
			continue;
		}

		$post_id = (int) $post_id;
		$notes   = array();

		// ---- メタフィールド ----
		$set_from_csv = array();
		$normalizers  = isset( $def['normalize'] ) ? $def['normalize'] : array();
		foreach ( $def['meta'] as $field_id => $aliases ) {
			$value = bankofart_csv_pick( $row, $aliases );
			if ( '' === $value ) {
				continue;
			}
			// 書式を揃える必要がある項目（テーマキーワード等）はここで整形する。
			if ( isset( $normalizers[ $field_id ] ) && is_callable( $normalizers[ $field_id ] ) ) {
				$value = call_user_func( $normalizers[ $field_id ], $value );
				if ( '' === $value ) {
					continue;
				}
			}
			update_post_meta( $post_id, $field_id, wp_kses_post( $value ) );
			$set_from_csv[ $field_id ] = true;
		}

		// ---- 導出フィールド（CSVに専用列が無いとき、他の列から自動生成する） ----
		if ( ! empty( $def['derived'] ) ) {
			foreach ( $def['derived'] as $field_id => $callback ) {
				// CSVに該当列があってそこから入った場合は、そちらを優先して上書きしない。
				if ( isset( $set_from_csv[ $field_id ] ) || ! is_callable( $callback ) ) {
					continue;
				}
				$derived = call_user_func( $callback, $row, $def );
				if ( '' !== (string) $derived['value'] ) {
					update_post_meta( $post_id, $field_id, $derived['value'] );
				} elseif ( ! empty( $derived['note'] ) ) {
					$notes[] = $derived['note'];
				}
			}
		}

		// ---- タクソノミー ----
		foreach ( $def['tax'] as $taxonomy => $aliases ) {
			$value = bankofart_csv_pick( $row, $aliases );
			if ( '' === $value ) {
				continue;
			}
			$resolved = bankofart_csv_resolve_terms( $taxonomy, bankofart_csv_split_multi( $value ) );
			if ( ! empty( $resolved['ids'] ) ) {
				wp_set_object_terms( $post_id, $resolved['ids'], $taxonomy, false );
			}
			if ( ! empty( $resolved['missing'] ) ) {
				$notes[] = sprintf(
					'%s に未登録の値：%s',
					$taxonomy,
					implode( '・', $resolved['missing'] )
				);
			}
		}

		// ---- 画像 ----
		if ( $args['with_images'] && ! empty( $def['images'] ) ) {
			foreach ( $def['images'] as $field_id => $conf ) {
				list( $mode, $aliases ) = $conf;

				$value = bankofart_csv_pick( $row, $aliases );
				if ( '' === $value ) {
					continue;
				}

				$urls = ( 'multi' === $mode ) ? bankofart_csv_split_multi( $value ) : array( $value );
				$ids  = array();
				foreach ( $urls as $url ) {
					$att = bankofart_csv_resolve_image( $url, $post_id );
					if ( is_wp_error( $att ) ) {
						$notes[] = sprintf( '画像を取り込めませんでした（%s）', $url );
						continue;
					}
					$ids[] = $att;
				}

				if ( empty( $ids ) ) {
					continue;
				}

				if ( 'multi' === $mode ) {
					delete_post_meta( $post_id, $field_id );
					foreach ( $ids as $att_id ) {
						add_post_meta( $post_id, $field_id, $att_id );
					}
				} else {
					update_post_meta( $post_id, $field_id, $ids[0] );
					if ( ! has_post_thumbnail( $post_id ) ) {
						set_post_thumbnail( $post_id, $ids[0] );
					}
				}
			}
		}

		// ---- リレーション（作品 → アーティスト）----
		if ( ! empty( $def['relation'] ) ) {
			$ref = bankofart_csv_pick( $row, $def['relation']['aliases'] );
			if ( '' !== $ref ) {
				$artist = get_page_by_path( sanitize_title( $ref ), OBJECT, 'artist' );
				if ( ! $artist ) {
					$hit = get_posts(
						array(
							'post_type'      => 'artist',
							'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
							'posts_per_page' => 1,
							'title'          => $ref,
							'no_found_rows'  => true,
						)
					);
					$artist = ! empty( $hit ) ? $hit[0] : null;
				}

				if ( $artist && class_exists( 'MB_Relationships_API' ) ) {
					MB_Relationships_API::add( $artist->ID, $post_id, $def['relation']['id'] );
				} elseif ( ! $artist ) {
					$notes[] = sprintf( 'アーティスト「%s」が見つからず、関連付けできませんでした。', $ref );
				}
			}
		}

		// ---- 取り込み記録（次回以降の重複判定に使う）----
		update_post_meta( $post_id, BANKOFART_CSV_IMPORT_KEY_META, $import_key );
		update_post_meta(
			$post_id,
			BANKOFART_CSV_IMPORT_LOG_META,
			wp_json_encode(
				array(
					'timestamp'  => $ts,
					'ts_column'  => $args['ts_column'],
					'imported_at' => current_time( 'mysql' ),
				)
			)
		);

		if ( $existing_id ) {
			++$report['updated'];
			$status = 'updated';
		} else {
			++$report['created'];
			$status = 'created';
		}

		$report['lines'][] = array(
			'status' => $status,
			'label'  => $label,
			'note'   => sprintf(
				'%s（投稿ID %d）%s',
				'updated' === $status ? '更新' : '新規作成',
				$post_id,
				$notes ? ' ／ ' . implode( ' ／ ', $notes ) : ''
			),
			'link'   => get_edit_post_link( $post_id, 'raw' ),
		);
	}

	return $report;
}
