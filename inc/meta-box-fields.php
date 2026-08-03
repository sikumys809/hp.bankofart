<?php
/**
 * Meta Box フィールド定義（タブ式UI 決定版）
 *
 * docs/phase1-finalize.md を反映。全CPTを art と同じタブ式UIに統一する。
 * Meta Box AIO（MB Tabs 拡張を含む）が有効な場合に動作する（rwmb_meta_boxes フィルター）。
 *
 * 運用性の3原則:
 *   1. 管理画面から表示制御可能 … 各CPTに「セクション表示設定」タブ + switch フィールド
 *   2. 未入力項目の自動非表示  … テンプレート側で値の存在チェック（inc/helpers.php）
 *   3. 再利用可能なコンポーネント … template-parts/ で部品化（Phase 2）
 *
 * 非公開フィールド（本名・連絡先・価格等）は別メタボックスに分離し、
 * current_user_can('manage_options') の場合のみ登録する＝管理者のみ閲覧可。
 *
 * セクション表示の switch は全て std => 1（デフォルトON）。
 * フィールドIDは仕様書通り。テンプレートから rwmb_meta() で参照する。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 「表示する」switch フィールドの共通定義を生成する。
 *
 * @param string $id   フィールドID（例：artist_show_works）。
 * @param string $name ラベル。
 * @param string $tab  所属タブキー。
 * @return array
 */
function bankofart_section_switch( $id, $name, $tab = 'section_display' ) {
	return array(
		'id'        => $id,
		'name'      => $name,
		'type'      => 'switch',
		'tab'       => $tab,
		'std'       => 1,
		'style'     => 'rounded',
		'on_label'  => '表示',
		'off_label' => '非表示',
	);
}

/**
 * タクソノミー選択ピッカー（select2）の共通定義を生成する。
 *
 * type は 'taxonomy'（実際のタームひも付けを保存）を使用する。
 * これにより get_the_terms() / tax_query が従来どおり機能する
 * （taxonomy_advanced はメタ値保存のため関連付けが作られず不可）。
 * field_type:
 *   - multiple = true  → 'select_advanced'（select2・複数選択・検索可。診断タグ等）
 *   - multiple = false → 'select'（ネイティブのプルダウン。単一選択を素直に表現）
 *
 * @param string $id       フィールドID（例：'artist_status_picker'）。
 * @param string $name     ラベル。
 * @param string $taxonomy タクソノミー名。
 * @param string $tab      所属タブキー。
 * @param bool   $multiple 複数選択可か。
 * @param string $desc     補足説明。
 * @return array
 */
function bankofart_taxonomy_picker( $id, $name, $taxonomy, $tab, $multiple = false, $desc = '' ) {
	return array(
		'id'          => $id,
		'name'        => $name,
		'type'        => 'taxonomy',
		'taxonomy'    => $taxonomy,
		'field_type'  => $multiple ? 'select_advanced' : 'select',
		'multiple'    => $multiple,
		'add_new'     => false,
		// select_advanced は既定で ajax=true（候補をAJAX取得）。本環境では候補が
		// 出ない（選択済みのみ表示）ため ajax=false にし、全タームをクライアント
		// 側に描画して select2 でローカル検索させる（最大55件で十分軽い）。
		'ajax'        => false,
		'tab'         => $tab,
		'placeholder' => $multiple ? '選択してください（複数可）' : '選択してください',
		'desc'        => $desc,
		// 投稿0件のタームも選択肢に出す（既定の hide_empty=true だと空に見えるため）。
		'query_args'  => array(
			'hide_empty' => false,
		),
	);
}

/**
 * 学歴・展示歴系フィールドの入力書式についての説明文。
 *
 * 表示側は bankofart_parse_record_lines() が「年 / 月 / 内容」に自動整形するため、
 * 書き方が多少ばらついても表示は揃う。その旨を編集画面にも書いておく。
 *
 * @return string
 */
function bankofart_record_format_desc() {
	return '表示は自動で「年 ／ 月 ／ 内容」に整形されます。'
		. '「2024 個展○○」のように1行にまとめても、年だけの行の下に「・個展○○」と並べても構いません。'
		. '「2017〜2021」のような期間表記、行頭の「・」「-」も自動で処理されます。';
}

/**
 * Meta Box のフィールドグループを登録する。
 *
 * @param array $meta_boxes 既存のメタボックス定義。
 * @return array
 */
function bankofart_register_meta_boxes( $meta_boxes ) {

	$can_manage = current_user_can( 'manage_options' );

	/* =========================================================
	 * ARTIST（公開フィールド）— 7タブ
	 * 基本 / テーマ / 展示 / 画像 / SNS / 診断 / セクション表示
	 * ======================================================= */
	$meta_boxes[] = array(
		'title'      => 'アーティスト情報',
		'id'         => 'bankofart_artist_public',
		'post_types' => array( 'artist' ),
		'context'    => 'normal',
		'priority'   => 'high',
		'tab_style'  => 'left',
		'tabs'       => array(
			'basic'           => array(
				'label' => '基本情報',
				'icon'  => 'dashicons-id',
			),
			'theme'           => array(
				'label' => 'テーマ・物語',
				'icon'  => 'dashicons-book',
			),
			'exhibition'      => array(
				'label' => '展示・経歴',
				'icon'  => 'dashicons-awards',
			),
			'media'           => array(
				'label' => '画像・動画',
				'icon'  => 'dashicons-format-image',
			),
			'sns'             => array(
				'label' => 'SNS',
				'icon'  => 'dashicons-share',
			),
			'diagnosis'       => array(
				'label' => '診断タグ・共鳴',
				'icon'  => 'dashicons-search',
			),
			'section_display' => array(
				'label' => 'セクション表示設定',
				'icon'  => 'dashicons-visibility',
			),
			'top_display'     => array(
				'label' => 'TOP表示設定',
				'icon'  => 'dashicons-admin-home',
			),
		),
		'fields'     => array(
			// --- 基本情報 ---
			array(
				'id'   => 'artist_name_en',
				'name' => '活動名（英字大文字）',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：AZUMA JUSEI',
			),
			array(
				'id'   => 'artist_catch_phrase',
				'name' => 'キャッチフレーズ',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：ADRENALINE ARTIST',
			),
			bankofart_taxonomy_picker( 'artist_status_picker', 'アーティストステータス', 'artist_status', 'basic', false, '公認画家 / 登録画家' ),
			bankofart_taxonomy_picker( 'artist_genre_picker', 'ジャンル', 'artist_genre', 'basic', true, '複数選択可・検索可' ),
			array(
				'id'   => 'artist_birthplace',
				'name' => '出身地',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：兵庫県神戸市',
			),
			// --- テーマ・物語 ---
			array(
				'id'         => 'artist_theme_short',
				'name'       => '制作テーマ（13字以内）',
				'type'       => 'text',
				'tab'        => 'theme',
				'desc'       => 'カード表示用・文字数注意',
				'attributes' => array( 'maxlength' => 13 ),
			),
			array(
				'id'   => 'artist_theme_long',
				'name' => '制作テーマ詳細',
				'type' => 'textarea',
				'tab'  => 'theme',
				'desc' => 'プロフィールページ用',
			),
			array(
				'id'   => 'artist_theme_keywords',
				'name' => 'テーマキーワード',
				'type' => 'text',
				'tab'  => 'theme',
				'desc' => 'カンマ区切り（例：生命エネルギー,挑戦,格闘）',
			),
			array(
				'id'   => 'artist_reason',
				'name' => 'なぜ描くか（Philosophy）',
				'type' => 'wysiwyg',
				'tab'  => 'theme',
			),
			array(
				'id'   => 'artist_origin_story',
				'name' => '起源の物語（History）',
				'type' => 'wysiwyg',
				'tab'  => 'theme',
				'desc' => '長文・段落構成可',
			),
			array(
				'id'   => 'artist_goal',
				'name' => '目標（Goal）',
				'type' => 'textarea',
				'tab'  => 'theme',
			),
			// --- 展示・経歴 ---
			array(
				'id'   => 'artist_education',
				'name' => '学歴・経歴',
				'type' => 'wysiwyg',
				'tab'  => 'exhibition',
				'desc' => '公開プロフィール用（出身校・主な経歴など）。' . bankofart_record_format_desc(),
			),
			array(
				'id'   => 'artist_solo_exhibitions',
				'name' => '個展',
				'type' => 'textarea',
				'tab'  => 'exhibition',
				'desc' => '改行区切りで複数。' . bankofart_record_format_desc(),
			),
			array(
				'id'   => 'artist_group_exhibitions',
				'name' => 'グループ展',
				'type' => 'textarea',
				'tab'  => 'exhibition',
				'desc' => '改行区切り。' . bankofart_record_format_desc(),
			),
			array(
				'id'   => 'artist_media_awards',
				'name' => 'メディア・受賞',
				'type' => 'textarea',
				'tab'  => 'exhibition',
				'desc' => '改行区切り。' . bankofart_record_format_desc(),
			),
			array(
				'id'   => 'artist_other_activities',
				'name' => 'その他活動',
				'type' => 'textarea',
				'tab'  => 'exhibition',
				'desc' => '改行区切り。' . bankofart_record_format_desc(),
			),
			// --- 画像・動画 ---
			array(
				'id'   => 'artist_main_photo',
				'name' => 'アーティストメイン写真',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => '一覧カード用',
			),
			array(
				'id'               => 'artist_gallery_photos',
				'name'             => 'アーティスト写真ギャラリー',
				'type'             => 'image_advanced',
				'tab'              => 'media',
				'desc'             => '1〜4枚（詳細ページ）',
				'max_file_uploads' => 4,
			),
			array(
				'id'   => 'artist_symbol_image',
				'name' => '自己を象徴する写真',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'Philosophyセクション用',
			),
			array(
				'id'               => 'artist_working_photos',
				'name'             => '制作風景写真',
				'type'             => 'image_advanced',
				'tab'              => 'media',
				'desc'             => '複数可',
				'max_file_uploads' => 20,
			),
			array(
				'id'   => 'artist_video_url',
				'name' => 'プロフィール動画URL',
				'type' => 'url',
				'tab'  => 'media',
				'desc' => 'YouTube埋込用',
			),
			// --- SNS ---
			array(
				'id'   => 'artist_sns_instagram',
				'name' => 'Instagram URL',
				'type' => 'url',
				'tab'  => 'sns',
			),
			array(
				'id'   => 'artist_sns_x',
				'name' => 'X (Twitter) URL',
				'type' => 'url',
				'tab'  => 'sns',
			),
			array(
				'id'   => 'artist_sns_facebook',
				'name' => 'Facebook URL',
				'type' => 'url',
				'tab'  => 'sns',
			),
			array(
				'id'   => 'artist_sns_youtube',
				'name' => 'YouTube URL',
				'type' => 'url',
				'tab'  => 'sns',
			),
			array(
				'id'   => 'artist_sns_other',
				'name' => 'その他URL（公式サイト等）',
				'type' => 'url',
				'tab'  => 'sns',
			),
			// --- 診断タグ・共鳴 ---
			bankofart_taxonomy_picker( 'artist_diagnosis_tag_picker', '診断タグ', 'artist_diagnosis_tag', 'diagnosis', true, '推奨：3〜6個。検索しながら選択できます（全55タグ）' ),
			array(
				'id'   => 'artist_resonance_message',
				'name' => '共鳴ポイント文章',
				'type' => 'wysiwyg',
				'tab'  => 'diagnosis',
				'desc' => 'パーパス診断結果で表示。200字程度。診断タグ自体は右サイドバーの「診断タグ」タクソノミーで管理。',
			),
			// --- セクション表示設定（8 switch）---
			bankofart_section_switch( 'artist_show_theme', '制作テーマ（Theme）セクション' ),
			bankofart_section_switch( 'artist_show_philosophy', 'なぜ描くのか（Philosophy）セクション' ),
			bankofart_section_switch( 'artist_show_origin_story', '起源の物語（History）セクション' ),
			bankofart_section_switch( 'artist_show_goal', 'Goal セクション' ),
			bankofart_section_switch( 'artist_show_works', 'WORKS（このアーティストの作品）セクション' ),
			bankofart_section_switch( 'artist_show_articles', 'ARTICLE（このアーティストの記事）セクション' ),
			bankofart_section_switch( 'artist_show_matching', 'Artist Matching バナー' ),
			bankofart_section_switch( 'artist_show_cta', 'CTA（資料請求・説明会）セクション' ),
			// --- TOP表示設定（front-page の ARTIST レール流し込み用）---
			array(
				'id'        => 'artist_top_featured',
				'name'      => 'TOPページに表示する',
				'type'      => 'switch',
				'tab'       => 'top_display',
				'std'       => 0,
				'style'     => 'rounded',
				'on_label'  => '表示',
				'off_label' => '非表示',
				'desc'      => 'ONにしたアーティストが TOP の ARTIST レールに表示される（最大5名）。未設定/OFFは表示されない。',
			),
			array(
				'id'   => 'artist_top_order',
				'name' => 'TOP表示順',
				'type' => 'number',
				'tab'  => 'top_display',
				'min'  => 0,
				'step' => 1,
				'std'  => 0,
				'desc' => '小さい順に左から並ぶ（1, 2, 3…）。未設定は末尾。',
			),
		),
	);

	/*
	 * ARTIST 非公開ボックス（本名・連絡先・住所・振込先・生年月日）は撤去。
	 * 個人情報・契約情報はWPで管理せず、フォーム経由で別途収集する運用に変更。
	 * 学歴・経歴は公開プロフィール（展示・経歴タブ）へ移設、出身地は基本情報タブに残置。
	 */

	/* =========================================================
	 * ART（作品情報）— 5タブ
	 * 基本 / 説明・コンセプト / 画像 / 所有歴 / セクション表示
	 * ======================================================= */
	$meta_boxes[] = array(
		'title'       => '作品情報',
		'id'          => 'bankofart_art_public',
		'post_types'  => array( 'art' ),
		'context'     => 'normal',
		'priority'    => 'high',
		'tab_style'   => 'left',
		'tabs'        => array(
			'basic'           => array(
				'label' => '基本情報',
				'icon'  => 'dashicons-id',
			),
			'concept'         => array(
				'label' => '説明・コンセプト',
				'icon'  => 'dashicons-editor-paragraph',
			),
			'images'          => array(
				'label' => '画像',
				'icon'  => 'dashicons-format-image',
			),
			'ownership'       => array(
				'label' => '所有歴',
				'icon'  => 'dashicons-groups',
			),
			'section_display' => array(
				'label' => 'セクション表示設定',
				'icon'  => 'dashicons-visibility',
			),
			'top_display'     => array(
				'label' => 'TOP表示設定',
				'icon'  => 'dashicons-admin-home',
			),
		),
		'fields'      => array(
			// --- 基本情報 ---
			/*
			 * シリーズを選ぶと、そのシリーズに登録した共通項目（作品名ベース／英題／
			 * 素材・支持体／ジャンル／技法／形態／作品説明／コンセプト）が空欄に自動入力される。
			 * 差し込み処理は inc/art-series/admin.php ＋ assets/js/admin-art-series.js。
			 */
			bankofart_taxonomy_picker( 'art_series_picker', '作品シリーズ', 'art_series', 'basic', false, '選ぶとシリーズ共通の項目が自動入力されます（空欄のみ）。シリーズの内容は「作品 > 作品シリーズ」で編集します。' ),
			array(
				'id'   => 'art_title_en',
				'name' => '作品英題',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：ADRENALINE ART',
			),
			array(
				'id'   => 'art_number',
				'name' => '作品NO.',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：No.01、VOL.5',
			),
			array(
				'id'   => 'art_year',
				'name' => '制作年',
				'type' => 'number',
				'tab'  => 'basic',
				'desc' => '西暦4桁',
			),
			array(
				'id'   => 'art_medium',
				'name' => '素材・支持体',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：アクリル / キャンバス',
			),
			array(
				'id'   => 'art_size_detail',
				'name' => 'サイズ詳細',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：F10（530×455mm）',
			),
			array(
				'id'   => 'art_size_label',
				'name' => '号数表記',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：F10、P20',
			),
			// --- タクソノミー（select2 ピッカー）---
			bankofart_taxonomy_picker( 'art_status_picker', 'ステータス', 'art_status', 'basic', false, 'AVAILABLE / OWNED' ),
			bankofart_taxonomy_picker( 'art_form_picker', '形態', 'art_form', 'basic', false, '平面 / 立体 / 半立体' ),
			bankofart_taxonomy_picker( 'art_genre_picker', 'ジャンル', 'art_genre', 'basic', true, '複数選択可' ),
			bankofart_taxonomy_picker( 'art_technique_picker', '技法', 'art_technique', 'basic', true, '複数選択可' ),
			bankofart_taxonomy_picker( 'art_size_picker', 'サイズ（号数区分）', 'art_size', 'basic', false ),
			bankofart_taxonomy_picker( 'art_main_color_picker', 'メインカラー', 'art_main_color', 'basic', false, '登録済みの12色から選択' ),
			// --- 説明・コンセプト ---
			array(
				'id'   => 'art_description',
				'name' => '作品説明',
				'type' => 'wysiwyg',
				'tab'  => 'concept',
				'desc' => '「この作品について」',
			),
			array(
				'id'   => 'art_concept',
				'name' => '作品コンセプト',
				'type' => 'wysiwyg',
				'tab'  => 'concept',
				'desc' => '補足説明',
			),
			array(
				'id'   => 'art_series_name',
				'name' => 'シリーズ名',
				'type' => 'text',
				'tab'  => 'concept',
				'desc' => '例：ADRENALINE ART（将来CPT化候補）',
			),
			// --- 画像 ---
			array(
				'id'   => 'art_main_image',
				'name' => 'メイン作品画像',
				'type' => 'single_image',
				'tab'  => 'images',
				'desc' => 'カード・一覧用',
			),
			array(
				'id'               => 'art_gallery',
				'name'             => '作品ギャラリー画像',
				'type'             => 'image_advanced',
				'tab'              => 'images',
				'desc'             => '3〜4枚（詳細ページ）',
				'max_file_uploads' => 20,
			),
			// --- 所有歴（Repeater）---
			array(
				'id'          => 'ownership_history',
				'name'        => '所有歴',
				'type'        => 'group',
				'tab'         => 'ownership',
				'clone'       => true,
				'sort_clone'  => true,
				'collapsible' => true,
				'add_button'  => '所有歴を追加',
				'group_title' => array( 'field' => 'collector_ref' ),
				'fields'      => array(
					array(
						'id'         => 'collector_ref',
						'name'       => '所有企業',
						'type'       => 'post',
						'post_type'  => 'collector',
						'field_type' => 'select_advanced',
						'ajax'       => false, // 候補をクライアント側に描画（select_advanced の ajax 既定対策）.
					),
					array(
						'id'         => 'from_date',
						'name'       => 'コレクト開始日',
						'type'       => 'date',
						'js_options' => array( 'dateFormat' => 'yy-mm-dd' ),
					),
					array(
						'id'         => 'to_date',
						'name'       => 'リセール日',
						'type'       => 'date',
						'js_options' => array( 'dateFormat' => 'yy-mm-dd' ),
					),
					array(
						'id'   => 'is_current',
						'name' => '現所有',
						'type' => 'switch',
					),
					array(
						'id'   => 'is_first',
						'name' => '初代所有',
						'type' => 'switch',
					),
					array(
						'id'   => 'resale_rate',
						'name' => 'リセール査定率（%）',
						'type' => 'number',
						'min'  => 0,
						'max'  => 100,
					),
					array(
						'id'   => 'comment',
						'name' => 'コメント',
						'type' => 'text',
					),
				),
			),
			// --- セクション表示設定（6 switch）---
			bankofart_section_switch( 'art_show_about', 'この作品について セクション' ),
			bankofart_section_switch( 'art_show_artist', 'ARTIST（描いたアーティスト）セクション' ),
			bankofart_section_switch( 'art_show_more_works', 'MORE WORKS（他の作品）セクション' ),
			bankofart_section_switch( 'art_show_collected_by', 'Collected by（所有企業）セクション ※OWNED時のみ' ),
			bankofart_section_switch( 'art_show_ownership_history', 'Ownership History（所有歴）セクション' ),
			bankofart_section_switch( 'art_show_cta', 'CTA（資料請求・説明会）セクション' ),
			// --- TOP表示設定（front-page の ART コラージュ流し込み用）---
			array(
				'id'        => 'art_top_featured',
				'name'      => 'TOPページに表示する',
				'type'      => 'switch',
				'tab'       => 'top_display',
				'std'       => 0,
				'style'     => 'rounded',
				'on_label'  => '表示',
				'off_label' => '非表示',
				'desc'      => 'ONにした作品が TOP の ART コラージュに表示される（最大7点）。',
			),
			array(
				'id'   => 'art_top_order',
				'name' => 'TOP表示順',
				'type' => 'number',
				'tab'  => 'top_display',
				'min'  => 0,
				'step' => 1,
				'std'  => 0,
				'desc' => '小さい順に並ぶ（1, 2, 3…）。未設定は末尾。コラージュは上段3点→下段4点の順に埋まる。',
			),
			array(
				'id'          => 'art_top_size',
				'name'        => 'TOP表示サイズ',
				'type'        => 'select',
				'tab'         => 'top_display',
				'options'     => array(
					'wide'   => '横長（wide）',
					'tall'   => '縦長（tall）',
					'square' => '正方形（square）',
				),
				'std'         => 'square',
				'placeholder' => false,
				'desc'        => 'コラージュ内での枠の形。未設定はモック既定の並び（横長→縦長→正方形…）にフォールバック。',
			),
			array(
				'id'   => 'art_top_image',
				'name' => 'TOP用画像（任意）',
				'type' => 'single_image',
				'tab'  => 'top_display',
				'desc' => 'TOPのコラージュは枠いっぱいに絵を出すため、保存時に自動で「絵の部分だけ」を切り出した画像を作ります。'
					. '自動トリミングの結果が気に入らないときだけ、ここに画像を入れてください（指定があれば自動処理より優先されます）。',
			),
		),
	);

	/* =========================================================
	 * ART（価格・非公開／管理者のみ）
	 * ======================================================= */
	if ( $can_manage ) {
		$meta_boxes[] = array(
			'title'      => '作品 管理情報（非公開）',
			'id'         => 'bankofart_art_private',
			'post_types' => array( 'art' ),
			'context'    => 'side',
			'priority'   => 'low',
			'fields'     => array(
				array(
					'id'   => 'art_price',
					'name' => '価格（非公開）',
					'type' => 'number',
					'desc' => '管理用',
				),
			),
		);
	}

	/* =========================================================
	 * COLLECTOR（画家応援企業）— 4タブ
	 * 基本 / 画像 / インタビュー / セクション表示
	 * ======================================================= */
	$meta_boxes[] = array(
		'title'       => '画家応援企業情報',
		'id'          => 'bankofart_collector',
		'post_types'  => array( 'collector' ),
		'context'     => 'normal',
		'priority'    => 'high',
		'tab_style'   => 'left',
		'tabs'        => array(
			'basic'           => array(
				'label' => '基本情報',
				'icon'  => 'dashicons-building',
			),
			'media'           => array(
				'label' => '画像',
				'icon'  => 'dashicons-format-image',
			),
			'interview'       => array(
				'label' => 'インタビュー',
				'icon'  => 'dashicons-format-chat',
			),
			'section_display' => array(
				'label' => 'セクション表示設定',
				'icon'  => 'dashicons-visibility',
			),
		),
		'fields'      => array(
			// --- 基本情報 ---
			array(
				'id'   => 'collector_company_name',
				'name' => '企業正式名称',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => 'post_titleと使い分け',
			),
			array(
				'id'   => 'collector_url',
				'name' => '企業URL',
				'type' => 'url',
				'tab'  => 'basic',
				'desc' => '公式サイト',
			),
			array(
				'id'   => 'collector_external_url',
				'name' => '旧サイト記事URL（参照用）',
				'type' => 'url',
				'tab'  => 'basic',
				'desc' => '旧サイトの該当記事URL。参照用（表示は任意）。',
			),
			array(
				'id'   => 'collector_video_url',
				'name' => '動画URL',
				'type' => 'url',
				'tab'  => 'basic',
				'desc' => 'YouTube等（任意）。',
			),
			array(
				'id'   => 'collector_introduced_artwork_text',
				'name' => 'コレクト作品名（テキスト）',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => 'art リレーション未投入時の暫定テキスト。',
			),
			// 「業界（表示用テキスト）」「設置場所（自由記述）」は廃止。
			// 業界は下の「業種」タクソノミー（13種＋その他）に一本化、設置場所も
			// 「設置場所」タクソノミー（複数選択）に一本化した。既存の自由テキスト値は
			// DB上に残るが（後日CSVで業種へ移行）、編集UI・表示参照からは外している。
			array(
				'id'         => 'collector_implementation_date',
				'name'       => '導入時期',
				'type'       => 'date',
				'tab'        => 'basic',
				'desc'       => '「2024年4月」表示',
				'js_options' => array( 'dateFormat' => 'yy-mm-dd' ),
			),
			array(
				'id'   => 'collector_change_summary',
				'name' => 'アートを置いた変化（一文）',
				'type' => 'textarea',
				'tab'  => 'basic',
				'desc' => 'カード表示用',
			),
			bankofart_taxonomy_picker( 'collector_industry_picker', '業種', 'collector_industry', 'basic', false, '14業種から選択（13種＋その他）' ),
			bankofart_taxonomy_picker( 'collector_issue_picker', '課題', 'collector_issue', 'basic', true, '複数選択可・診断と連動' ),
			bankofart_taxonomy_picker( 'collector_placement_picker', '設置場所', 'collector_placement', 'basic', true, '複数選択可' ),
			// --- 画像 ---
			array(
				'id'   => 'collector_logo',
				'name' => '企業ロゴ',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'TOP・CLIENT COMPANIES用',
			),
			array(
				'id'   => 'collector_main_office_image',
				'name' => 'オフィスメイン写真',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'カード一覧用',
			),
			array(
				'id'               => 'collector_office_images',
				'name'             => 'オフィス追加写真',
				'type'             => 'image_advanced',
				'tab'              => 'media',
				'desc'             => '3〜5枚（詳細ページ）',
				'max_file_uploads' => 10,
			),
			array(
				'id'   => 'collector_entrance_image',
				'name' => 'エントランス写真',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'インタビューQ1付近で使用',
			),
			array(
				'id'   => 'collector_interview_image_1',
				'name' => 'インタビュー写真1',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'Q2付近',
			),
			array(
				'id'   => 'collector_interview_image_2',
				'name' => 'インタビュー写真2',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'Q4付近',
			),
			array(
				'id'   => 'collector_interview_image_3',
				'name' => 'インタビュー写真3',
				'type' => 'single_image',
				'tab'  => 'media',
				'desc' => 'Q5付近',
			),
			/*
			 * --- インタビュー（自由記述・件数無制限）---
			 * 下の Q1〜Q5 は定番の質問セット。取材内容がそれに収まらないことも多いため、
			 * 質問文ごと自由に足せるリピーターを用意した。
			 * 1行でも入っていればこちらが本文として使われ、Q1〜Q5 は表示されない
			 * （single-collector.php で切り替え）。
			 */
			array(
				'id'          => 'collector_interview_qa',
				'name'        => 'インタビュー（自由記述）',
				'type'        => 'group',
				'tab'         => 'interview',
				'clone'       => true,
				'sort_clone'  => true,
				'collapsible' => true,
				'add_button'  => '質問を追加',
				'group_title' => array( 'field' => 'qa_question' ),
				'desc'        => 'ここに1件でも入力すると、下の「定番の質問（Q1〜Q5）」ではなくこちらが記事本文になります。',
				'fields'      => array(
					array(
						'id'   => 'qa_question',
						'name' => '質問',
						'type' => 'textarea',
						'rows' => 2,
					),
					array(
						'id'   => 'qa_answer',
						'name' => '回答',
						'type' => 'wysiwyg',
					),
					array(
						'id'   => 'qa_image',
						'name' => 'この回答に添える写真',
						'type' => 'single_image',
						'desc' => '任意。未設定なら写真なしで表示します。',
					),
				),
			),
			// --- インタビュー（定番の質問 Q&A 5問。上のリピーターが空のときに使われる）---
			array(
				'id'   => 'collector_q1_values',
				'name' => 'Q1：理念や価値観について',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => 'エントランス写真と並列表示',
			),
			array(
				'id'   => 'collector_q2_motivation',
				'name' => 'Q2：アート導入のきっかけ',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => 'インタビュー写真1と並列表示',
			),
			array(
				'id'   => 'collector_q3_choice',
				'name' => 'Q3：作品・作家を選んだ決め手',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => '該当アートの写真と並列表示',
			),
			array(
				'id'   => 'collector_q4_changes',
				'name' => 'Q4：飾ってからの変化',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => 'インタビュー写真2と並列表示',
			),
			array(
				'id'   => 'collector_q5_message',
				'name' => 'Q5：検討中企業へのメッセージ',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => 'インタビュー写真3と並列表示',
			),
			// --- セクション表示設定（5 switch）---
			bankofart_section_switch( 'collector_show_interview', 'INTERVIEW（導入企業の声）セクション' ),
			bankofart_section_switch( 'collector_show_introduced_work', 'INTRODUCED WORK（迎えた作品）セクション' ),
			bankofart_section_switch( 'collector_show_same_issue', 'SAME ISSUE（同じ課題に取り組む企業）セクション' ),
			bankofart_section_switch( 'collector_show_matching', 'Issue Matching バナー' ),
			bankofart_section_switch( 'collector_show_cta', 'CTA（資料請求・説明会）セクション' ),
		),
	);

	/* =========================================================
	 * COLLECTOR（移行管理／非公開・管理者のみ）
	 * legacy_post_id / identify_status は公開ページに出力しない管理用メモ。
	 * ======================================================= */
	if ( $can_manage ) {
		$meta_boxes[] = array(
			'title'      => 'コレクター 管理情報（非公開）',
			'id'         => 'bankofart_collector_private',
			'post_types' => array( 'collector' ),
			'context'    => 'side',
			'priority'   => 'low',
			'fields'     => array(
				array(
					'id'   => 'collector_legacy_post_id',
					'name' => '旧サイト投稿ID',
					'type' => 'text',
					'desc' => '移行管理用（重複投入防止のキー）',
				),
				array(
					'id'   => 'collector_article_published_date',
					'name' => '旧サイト記事公開日',
					'type' => 'text',
					'desc' => 'YYYY-MM-DD。旧サイト記事の公開日（移行時に自動取得）。'
						. '※「記事の公開日」であり「実際のアート導入日」ではない。導入時期は別フィールド（collector_implementation_date）で管理する。',
				),
				array(
					'id'   => 'collector_identify_status',
					'name' => '同定ステータス（メモ）',
					'type' => 'text',
					'desc' => '管理用メモ。公開ページには出力しない。',
				),
			),
		);
	}

	/* =========================================================
	 * NEWS — 3タブ
	 * 基本 / 本文セクション / セクション表示
	 * 関連アーティスト/作品は MB Relationships で管理。
	 * ======================================================= */
	$meta_boxes[] = array(
		'title'       => 'NEWS情報',
		'id'          => 'bankofart_news',
		'post_types'  => array( 'news' ),
		'context'     => 'normal',
		'priority'    => 'high',
		'tab_style'   => 'left',
		'tabs'        => array(
			'basic'           => array(
				'label' => '基本情報',
				'icon'  => 'dashicons-megaphone',
			),
			'body'            => array(
				'label' => '本文セクション',
				'icon'  => 'dashicons-edit',
			),
			'section_display' => array(
				'label' => 'セクション表示設定',
				'icon'  => 'dashicons-visibility',
			),
		),
		'fields'      => array(
			// --- 基本情報 ---
			array(
				'id'   => 'news_summary',
				'name' => '要約（カード表示用）',
				'type' => 'textarea',
				'tab'  => 'basic',
				'desc' => '1〜2文',
			),
			array(
				'id'   => 'news_main_image',
				'name' => 'メイン写真',
				'type' => 'single_image',
				'tab'  => 'basic',
				'desc' => 'カード・詳細冒頭',
			),
			array(
				'id'   => 'news_external_url',
				'name' => '外部リンクURL',
				'type' => 'url',
				'tab'  => 'basic',
				'desc' => 'メディア掲載時の元記事',
			),
			array(
				'id'   => 'news_external_label',
				'name' => '外部リンクラベル',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：PR TIMESで読む',
			),
			bankofart_taxonomy_picker( 'news_category_picker', 'カテゴリー', 'news_category', 'basic', false, '受賞 / 展示 / メディア掲載 / お知らせ' ),
			// --- 本文セクション（Repeater）---
			array(
				'id'          => 'news_sections',
				'name'        => '本文セクション',
				'type'        => 'group',
				'tab'         => 'body',
				'clone'       => true,
				'sort_clone'  => true,
				'collapsible' => true,
				'add_button'  => 'セクションを追加',
				'group_title' => array( 'field' => 'section_heading' ),
				'fields'      => array(
					array(
						'id'   => 'section_heading',
						'name' => '見出し',
						'type' => 'text',
					),
					array(
						'id'   => 'section_body',
						'name' => '本文',
						'type' => 'wysiwyg',
					),
					array(
						'id'               => 'section_images',
						'name'             => 'サブ写真',
						'type'             => 'image_advanced',
						'max_file_uploads' => 10,
					),
				),
			),
			// --- セクション表示設定（4 switch）---
			bankofart_section_switch( 'news_show_related_artist', 'Related Artist（関連アーティスト）セクション' ),
			bankofart_section_switch( 'news_show_related_art', 'Related Art（関連作品）セクション' ),
			bankofart_section_switch( 'news_show_more_news', 'MORE NEWS セクション' ),
			bankofart_section_switch( 'news_show_cta', 'CTA（資料請求・説明会）セクション' ),
		),
	);

	/* =========================================================
	 * JOURNAL — 4タブ
	 * 基本 / 本文セクション（コラム）/ インタビュー / セクション表示
	 *
	 * 表示デザインはカテゴリー（コラム / インタビュー）で自動的に切り替わる。
	 * 「コラム」＝本文セクション（従来の記事レイアウト）
	 * 「インタビュー」＝アイコン＋吹き出しのQ&Aレイアウト
	 * ======================================================= */
	$meta_boxes[] = array(
		'title'       => 'JOURNAL情報',
		'id'          => 'bankofart_journal',
		'post_types'  => array( 'journal' ),
		'context'     => 'normal',
		'priority'    => 'high',
		'tab_style'   => 'left',
		'tabs'        => array(
			'basic'           => array(
				'label' => '基本情報',
				'icon'  => 'dashicons-book-alt',
			),
			'body'            => array(
				'label' => '本文セクション（コラム）',
				'icon'  => 'dashicons-edit',
			),
			'interview'       => array(
				'label' => 'インタビュー',
				'icon'  => 'dashicons-format-chat',
			),
			'section_display' => array(
				'label' => 'セクション表示設定',
				'icon'  => 'dashicons-visibility',
			),
		),
		'fields'      => array(
			// --- 基本情報 ---
			array(
				'id'   => 'journal_summary',
				'name' => '要約（カード表示用）',
				'type' => 'textarea',
				'tab'  => 'basic',
				'desc' => '1〜2文',
			),
			array(
				'id'   => 'journal_author',
				'name' => '著者名',
				'type' => 'text',
				'tab'  => 'basic',
				'desc' => '例：水野永吉',
			),
			array(
				'id'   => 'journal_reading_time',
				'name' => '読了時間（分）',
				'type' => 'number',
				'tab'  => 'basic',
				'desc' => '例：5',
			),
			array(
				'id'   => 'journal_main_image',
				'name' => 'メイン写真',
				'type' => 'single_image',
				'tab'  => 'basic',
				'desc' => 'カード・詳細冒頭',
			),
			bankofart_taxonomy_picker( 'journal_category_picker', 'カテゴリー', 'journal_category', 'basic', false, 'コラム / インタビュー（記事デザインが自動で切り替わります）' ),
			array(
				'id'      => 'journal_layout',
				'name'    => '記事デザイン',
				'type'    => 'select',
				'tab'     => 'basic',
				'std'     => 'auto',
				'options' => array(
					'auto'      => 'カテゴリーに従う（推奨）',
					'column'    => 'コラム（本文セクション形式）',
					'interview' => 'インタビュー（アイコン＋吹き出し形式）',
				),
				'desc'    => '通常は「カテゴリーに従う」でOK。カテゴリーが「インタビュー」なら吹き出し形式、それ以外はコラム形式で表示されます。',
			),
			// --- 本文セクション（Repeater）---
			array(
				'id'          => 'journal_sections',
				'name'        => '本文セクション',
				'type'        => 'group',
				'tab'         => 'body',
				'clone'       => true,
				'sort_clone'  => true,
				'collapsible' => true,
				'add_button'  => 'セクションを追加',
				'group_title' => array( 'field' => 'section_heading' ),
				'fields'      => array(
					array(
						'id'   => 'section_heading',
						'name' => '見出し',
						'type' => 'text',
					),
					array(
						'id'   => 'section_body',
						'name' => '本文',
						'type' => 'wysiwyg',
					),
					array(
						'id'               => 'section_images',
						'name'             => 'サブ写真',
						'type'             => 'image_advanced',
						'max_file_uploads' => 10,
					),
				),
			),
			// --- インタビュー（話し手・聞き手 ＋ Q&A リピーター）---
			array(
				'id'   => 'journal_interview_intro',
				'name' => '導入文（リード）',
				'type' => 'wysiwyg',
				'tab'  => 'interview',
				'desc' => 'Q&Aの前に置く前書き。未入力なら表示されません。',
			),
			array(
				'id'   => 'journal_speaker_name',
				'name' => '話し手の名前',
				'type' => 'text',
				'tab'  => 'interview',
				'desc' => '未入力の場合、「関連アーティスト」に設定した1人目の名前を自動で使います。',
			),
			array(
				'id'   => 'journal_speaker_role',
				'name' => '話し手の肩書き',
				'type' => 'text',
				'tab'  => 'interview',
				'desc' => '例：画家 / 代表取締役。任意。',
			),
			array(
				'id'   => 'journal_speaker_icon',
				'name' => '話し手のアイコン',
				'type' => 'single_image',
				'tab'  => 'interview',
				'desc' => '未設定の場合、「関連アーティスト」1人目のメイン写真を自動で使います。正方形推奨。',
			),
			array(
				'id'   => 'journal_interviewer_name',
				'name' => '聞き手の名前',
				'type' => 'text',
				'tab'  => 'interview',
				'std'  => 'BOA',
				'desc' => '未入力なら「BOA」。',
			),
			array(
				'id'   => 'journal_interviewer_icon',
				'name' => '聞き手のアイコン',
				'type' => 'single_image',
				'tab'  => 'interview',
				'desc' => '未設定ならBOAのロゴマークを使います。正方形推奨。',
			),
			array(
				'id'          => 'journal_interview_qa',
				'name'        => 'Q&A（質問と回答）',
				'type'        => 'group',
				'tab'         => 'interview',
				'clone'       => true,
				'sort_clone'  => true,
				'collapsible' => true,
				'add_button'  => 'Q&Aを追加',
				'group_title' => array( 'field' => 'qa_question' ),
				'fields'      => array(
					array(
						'id'   => 'qa_chapter',
						'name' => '章見出し（任意）',
						'type' => 'text',
						'desc' => 'このQ&Aの手前に大見出しを入れたいときだけ入力。',
					),
					array(
						'id'   => 'qa_question',
						'name' => '質問（聞き手）',
						'type' => 'textarea',
						'rows' => 3,
					),
					array(
						'id'   => 'qa_answer',
						'name' => '回答（話し手）',
						'type' => 'wysiwyg',
					),
					array(
						'id'               => 'qa_images',
						'name'             => '回答に添える写真',
						'type'             => 'image_advanced',
						'max_file_uploads' => 10,
					),
				),
			),
			// --- セクション表示設定（4 switch）---
			bankofart_section_switch( 'journal_show_related_artist', 'Related Artist（関連アーティスト）セクション' ),
			bankofart_section_switch( 'journal_show_related_art', 'Related Art（関連作品）セクション' ),
			bankofart_section_switch( 'journal_show_more_journal', 'MORE JOURNAL セクション' ),
			bankofart_section_switch( 'journal_show_cta', 'CTA（資料請求・説明会）セクション' ),
		),
	);

	return $meta_boxes;
}
add_filter( 'rwmb_meta_boxes', 'bankofart_register_meta_boxes' );
