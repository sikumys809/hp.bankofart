<?php
/**
 * MB Term Meta フィールド定義
 *
 * docs/phase1-finalize.md §3「Main Color Filter」を反映。
 * art_main_color タクソノミーの各ターム（赤・橙…）に「カラー効果」と
 * 「推奨設置場所」のメタ情報を持たせ、ARTフィルターで色クリック時に表示する。
 *
 * Meta Box AIO の MB Term Meta 拡張が有効な場合に動作する。
 * テンプレートからは get_term_meta( $term_id, $key, true ) で参照する。
 *
 * 初期値は inc/taxonomies.php の seed 関数で投入する（管理画面で編集可）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * art_main_color のターム編集画面にカラー情報フィールドを追加する。
 *
 * @param array $meta_boxes 既存のメタボックス定義。
 * @return array
 */
function bankofart_register_term_meta_boxes( $meta_boxes ) {
	$meta_boxes[] = array(
		'title'      => 'カラー情報（効果・推奨設置場所）',
		'id'         => 'art_main_color_meta',
		'taxonomies' => array( 'art_main_color' ),
		'fields'     => array(
			array(
				'id'   => 'color_hex',
				'name' => '色（カラーコード）',
				'type' => 'color',
				'desc' => 'この色の実際の表示色。一覧・フィルターのスウォッチに使用（例：#D32F2F）',
			),
			array(
				'id'   => 'color_effect_title',
				'name' => 'カラー効果（タイトル）',
				'type' => 'text',
				'desc' => '例：情熱・行動力・活気',
			),
			array(
				'id'   => 'color_effect_description',
				'name' => 'カラー効果（説明）',
				'type' => 'textarea',
				'rows' => 3,
			),
			array(
				'id'   => 'recommended_place_title',
				'name' => '推奨設置場所（タイトル）',
				'type' => 'text',
				'desc' => '例：会議室・ブレインストーミングスペース',
			),
			array(
				'id'   => 'recommended_place_description',
				'name' => '推奨設置場所（説明）',
				'type' => 'textarea',
				'rows' => 3,
			),
		),
	);

	/*
	 * 作品シリーズのテンプレート。
	 * ここに入れた内容が、作品編集画面で同じシリーズを選んだときに自動入力される
	 * （実際の差し込みは inc/art-series/admin.php ＋ assets/js/admin-art-series.js）。
	 * 作品ごとに変わる項目（作品NO.／制作年／サイズ／メインカラー／画像）は持たせない。
	 */
	$meta_boxes[] = array(
		'title'      => 'シリーズ共通の初期値（作品編集画面で自動入力されます）',
		'id'         => 'art_series_template',
		'taxonomies' => array( 'art_series' ),
		'fields'     => array(
			array(
				'id'   => 'series_title_base',
				'name' => '作品名（TITLE）のベース',
				'type' => 'text',
				'desc' => '例：ADRENALINE ART 昇華 — 作品NO.等は作品ごとに追記してください。',
			),
			array(
				'id'   => 'series_title_en',
				'name' => '作品英題',
				'type' => 'text',
				'desc' => '例：ADRENALINE ART',
			),
			array(
				'id'   => 'series_medium',
				'name' => '素材・支持体',
				'type' => 'text',
				'desc' => '例：アクリル / キャンバス',
			),
			bankofart_series_term_picker( 'series_genre', 'ジャンル', 'art_genre', '複数選択可' ),
			bankofart_series_term_picker( 'series_technique', '技法', 'art_technique', '複数選択可' ),
			bankofart_series_term_picker( 'series_form', '形態', 'art_form', '平面 / 立体 / 半立体' ),
			array(
				'id'   => 'series_description',
				'name' => '作品説明',
				'type' => 'wysiwyg',
				'desc' => '「この作品について」。シリーズ共通の説明文。',
			),
			array(
				'id'   => 'series_concept',
				'name' => '作品コンセプト',
				'type' => 'wysiwyg',
				'desc' => 'シリーズ共通のコンセプト。',
			),
		),
	);

	return $meta_boxes;
}

/**
 * シリーズのターム編集画面で、作品用タクソノミーのタームを選ばせるフィールドを作る。
 *
 * ターム編集画面では type='taxonomy' が使えない（そのターム自身への割当になってしまう）ため、
 * select_advanced で「ターム名の配列」をターム メタに保存する。
 * 名前で保存しておくと、作品側へ流し込むときに ID を引き直せて移行にも強い。
 *
 * @param string $id       フィールドID。
 * @param string $name     ラベル。
 * @param string $taxonomy 選択肢を取るタクソノミー。
 * @param string $desc     補足説明。
 * @return array
 */
function bankofart_series_term_picker( $id, $name, $taxonomy, $desc = '' ) {
	$options = array();
	$terms   = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$options[ $term->name ] = $term->name;
		}
	}

	return array(
		'id'          => $id,
		'name'        => $name,
		'type'        => 'select_advanced',
		'options'     => $options,
		'multiple'    => true,
		'placeholder' => '選択してください',
		'desc'        => $desc,
	);
}
add_filter( 'rwmb_meta_boxes', 'bankofart_register_term_meta_boxes' );
