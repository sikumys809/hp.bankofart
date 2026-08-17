<?php
/**
 * カスタムタクソノミー登録
 *
 * theme-structure.md「4. カスタムタクソノミー」の定義に準拠。
 * すべてフラット（タグ型）。WordPress標準の register_taxonomy で登録する。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * タクソノミー定義一覧を返す。
 *
 * 形式: スラッグ => array( ラベル, 対象CPT, 既定ターム配列 )
 *
 * @return array
 */
function bankofart_get_taxonomy_config() {
	return array(
		// artist 用.
		'artist_status' => array(
			'label'     => 'アーティストステータス',
			'post_type' => 'artist',
			'terms'     => array( '公認画家', '登録画家' ),
		),
		'artist_genre' => array(
			'label'     => 'アーティストジャンル',
			'post_type' => 'artist',
			'terms'     => array( '具象', '抽象', 'ストリートアート', 'ポップアート', '日本画', '油彩', 'アクリル', 'ミクストメディア' ),
		),
		// 診断タグ：パーパス診断・課題逆引き診断の双方で使用する主要タグ。
		// アーティスト1人につき3〜6個付与する。初期値はシード（管理画面で編集可）。
		'artist_diagnosis_tag' => array(
			'label'     => '診断タグ',
			'post_type' => 'artist',
			'terms'     => array(
				// 価値観系（15）.
				'つながり', 'コミュニティ', '地域', '挑戦', '突破', '唯一無二', '社会貢献', '貧困', 'SDGs', '伝統', '継承', '工芸', '祈り', '心の豊かさ', '愛',
				// 物語系（13）.
				'再生', 'オルタナリー', '第二のキャリア', '家族', '国際', '越境', '多文化', '偶然', '転機', '実験', '社会批評', '不条理', 'メッセージ',
				// 未来系（11）.
				'子供', '希望', '未来', '多様性', 'POP', '自然', 'サステナビリティ', '植物', '自立', 'エンパワー', '普遍性',
				// 社風系（10）.
				'勉強会', '格闘', '生命エネルギー', '錬磨', 'DIY', 'ストリート', 'サブカルチャー', '神性', '構造', '探究',
				// その他（6）.
				'ポジティブ', '物語', '都市', '現代性', '童心', '若者',
				// マッチング仕様（Notion「アーティストマッチング機能 構成指示書」）の語彙を補完（9）.
				'癒し', '救い', 'リカバリー', '文化', '力強さ', '静謐', 'バンドカルチャー', '知性', '若手',
			),
		),
		// art 用.
		'art_status' => array(
			'label'     => '作品ステータス',
			'post_type' => 'art',
			'terms'     => array( 'AVAILABLE', 'OWNED' ),
		),
		'art_form' => array(
			'label'     => '形態',
			'post_type' => 'art',
			'terms'     => array( '平面', '立体', '半立体' ),
		),
		'art_genre' => array(
			'label'     => '作品ジャンル',
			'post_type' => 'art',
			'terms'     => array( '抽象', '具象', 'ポップアート', 'ストリートアート' ),
		),
		'art_technique' => array(
			'label'     => '技法',
			'post_type' => 'art',
			'terms'     => array( '油彩', 'アクリル', '水彩', '墨', '日本画材', 'ミクストメディア' ),
		),
		'art_size' => array(
			'label'     => 'サイズ',
			'post_type' => 'art',
			'terms'     => array( '10号', '20号', '30号', 'その他' ),
		),
		'art_main_color' => array(
			'label'     => 'メインカラー',
			'post_type' => 'art',
			'terms'     => array( '赤', '橙', '黄', '緑', '青', '紫', '茶', '白', '黒', '金', '銀', 'その他' ),
		),
		/*
		 * 作品シリーズ。
		 * 同じシリーズで共通する項目（素材・支持体／ジャンル／技法／作品説明／
		 * コンセプト等）をタームのメタに持たせ、作品編集画面でシリーズを選ぶと
		 * 自動入力する（inc/art-series/admin.php）。
		 * タームは運用しながら追加するため、初期タームは持たない。
		 */
		'art_series' => array(
			'label'     => '作品シリーズ',
			'post_type' => 'art',
			'terms'     => array(),
		),
		// collector 用.
		// 課題：診断仕様（課題逆引き診断）と値を統一。旧値（モチベーション 等）は廃止。
		'collector_issue' => array(
			'label'     => '課題',
			'post_type' => 'collector',
			'terms'     => array( '離職・モチベーション', '採用・差別化', '取引先への印象', '理念浸透', '空間の活性化' ),
		),
		'collector_industry' => array(
			'label'     => '業界',
			'post_type' => 'collector',
			'terms'     => array( '士業', '不動産・建設', '金融・保険', '医療・福祉', 'IT・通信', '製造・メーカー', '卸・商社', '小売・サービス', '教育・人材', '広告・メディア', 'エンタメ・文化', '物流・運輸', '公共・その他', 'その他' ),
		),
		'collector_placement' => array(
			'label'     => '設置場所',
			'post_type' => 'collector',
			'terms'     => array( 'エントランス', '執務スペース', '会議室・応接室', '共用スペース', '店舗・接客スペース' ),
		),
		// news 用.
		'news_category' => array(
			'label'     => 'NEWSカテゴリー',
			'post_type' => 'news',
			'terms'     => array( '受賞', '展示', 'メディア掲載', 'お知らせ' ),
		),
		// journal 用.
		'journal_category' => array(
			'label'     => 'JOURNALカテゴリー',
			'post_type' => 'journal',
			'terms'     => array( 'コラム', 'インタビュー' ),
		),
	);
}

/**
 * art_main_color の各ターム（色名）に投入するカラー情報メタの初期値。
 *
 * docs/phase1-finalize.md §3「Main Color Filter」の表に基づく。
 * 金/銀の表現は色彩の一般的連想に合わせて割当（Notion最終稿で要確認）。
 * 文言は管理画面で編集可（term meta）。Phase 2-C のフィルターで参照する。
 *
 * 形式: 色名 => array( hex, effect_title, effect_desc, place_title, place_desc )
 * hex は各色の実表示色の初期値（管理画面の term meta で1色ずつ変更可）。
 *
 * @return array
 */
function bankofart_get_color_meta_seed() {
	return array(
		'赤' => array(
			'hex'          => '#D32F2F',
			'effect_title' => '情熱・行動力・活気',
			'effect_desc'  => '注意を引き、情熱的な高揚をもたらす色。交感神経を刺激し、空間に活気とエネルギーを生み出します。',
			'place_title'  => '会議室・ブレインストーミングスペース',
			'place_desc'   => '議論を活性化させ、積極的な発言を促したい場所に。',
		),
		'橙' => array(
			'hex'          => '#F57C00',
			'effect_title' => '陽気・コミュニケーション促進',
			'effect_desc'  => '楽天的で親しみやすい印象を与える色。気持ちを開放的にし、人と人との会話を後押しします。',
			'place_title'  => '休憩室・カフェスペース・ラウンジ',
			'place_desc'   => '社員同士の自然な交流を生みたい場所に。',
		),
		'黄' => array(
			'hex'          => '#FBC02D',
			'effect_title' => '明るさ・希望・集中力',
			'effect_desc'  => '明るさと希望を与え、頭の回転を活発にする色。集中力を高め、ひらめきをもたらすとされています。',
			'place_title'  => '執務スペース・デスクエリア',
			'place_desc'   => '日々の業務に集中力と前向きさを添えたい場所に。',
		),
		'緑' => array(
			'hex'          => '#388E3C',
			'effect_title' => '安心・リラックス・癒し',
			'effect_desc'  => '目線を安定させ、緊張をほぐす色。心身を癒し、空間に落ち着きと安らぎをもたらします。',
			'place_title'  => '受付・廊下・休憩室',
			'place_desc'   => '来訪者や社員の緊張を和らげ、疲労を回復したい場所に。',
		),
		'青' => array(
			'hex'          => '#1976D2',
			'effect_title' => '冷静・信頼・誠実',
			'effect_desc'  => '精神を落ち着かせる鎮静作用を持つ色。冷静さと信頼感、誠実な印象を空間に与えます。',
			'place_title'  => '役員室・応接室・集中作業エリア',
			'place_desc'   => '落ち着いた判断や信頼感を求められる場所に。',
		),
		'紫' => array(
			'hex'          => '#7B1FA2',
			'effect_title' => '高貴・優雅・想像力',
			'effect_desc'  => '高貴さと優雅さを表す色。集中力を高め、鎮静効果と創造的な感性の両方を刺激します。',
			'place_title'  => '役員室・応接室・クリエイティブ部署',
			'place_desc'   => '品格と発想力を演出したい場所に。',
		),
		'茶' => array(
			'hex'          => '#6D4C41',
			'effect_title' => '安定・ぬくもり・自然',
			'effect_desc'  => '木や大地を思わせるナチュラルな色。安定感とぬくもりを与え、空間を落ち着いた雰囲気にまとめます。',
			'place_title'  => '応接室・役員室・打ち合わせスペース',
			'place_desc'   => '重厚感と安心感を両立させたい場所に。',
		),
		'白' => array(
			'hex'          => '#FAFAFA',
			'effect_title' => '清潔感・誠実・開放感',
			'effect_desc'  => '空間に清潔感と軽さを与える色。圧迫感を減らし、開放的で整然とした印象を演出します。',
			'place_title'  => '受付・エントランス・会議室',
			'place_desc'   => '来訪者に清潔感と信頼感を与えたい場所、空間を広く見せたい場所に。',
		),
		'黒' => array(
			'hex'          => '#212121',
			'effect_title' => '高級感・威厳・洗練',
			'effect_desc'  => '重厚感と力強さを持つ色。空間を引き締め、洗練された印象と高級感を演出します。',
			'place_title'  => '役員室・応接室・エントランス',
			'place_desc'   => 'ブランドイメージや品格を強調したい場所に。',
		),
		'金' => array(
			'hex'          => '#C9A227',
			'effect_title' => '高級感・上品・華やかさ',
			'effect_desc'  => '高級感や上品さ、華やかさを象徴する色。空間に華やぎと特別感を与え、ブランド価値や存在感を引き立てます。',
			'place_title'  => 'エントランス・応接室・ラウンジ',
			'place_desc'   => '企業のブランド性や格式を印象づけたい場所に。',
		),
		'銀' => array(
			'hex'          => '#9E9E9E',
			'effect_title' => '先進性・神性・スタイリッシュ',
			'effect_desc'  => '金属的な輝きを持ち、都会的で洗練された印象を与える色。冷静さや神性を感じさせ、テクノロジーや未来性とも相性が良い色です。',
			'place_title'  => '応接室・ミーティングルーム・受付',
			'place_desc'   => 'モダンで先進的な雰囲気を演出したい場所に。',
		),
		'その他' => array(
			'hex'          => '#607D8B',
			'effect_title' => '活気・多様性・個性',
			'effect_desc'  => '複数の色が響き合う多彩な作品や、独自の色彩を持つ作品。エネルギーと多様性を空間に与え、個性的でポジティブな印象を生みます。',
			'place_title'  => 'エントランス・オープンスペース・社員食堂',
			'place_desc'   => '活気や創造性、多様な社風を表現したい場所に。',
		),
	);
}

/**
 * タクソノミーを一括登録する。
 *
 * @return void
 */
function bankofart_register_taxonomies() {
	foreach ( bankofart_get_taxonomy_config() as $slug => $config ) {
		$label = $config['label'];

		$labels = array(
			'name'          => $label,
			'singular_name' => $label,
			'menu_name'     => $label,
			'all_items'     => $label . '一覧',
			'edit_item'     => $label . 'を編集',
			'update_item'   => $label . 'を更新',
			'add_new_item'  => $label . 'を追加',
			'new_item_name' => '新しい' . $label,
			'search_items'  => $label . 'を検索',
		);

		register_taxonomy(
			$slug,
			$config['post_type'],
			array(
				'labels'            => $labels,
				'hierarchical'      => false, // フラット（タグ型）.
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				// 標準のタグ入力UI（右サイドバー）を無効化。入力は Meta Box の
				// taxonomy（select_advanced）ピッカーに一本化する。
				'meta_box_cb'       => false,
				'rewrite'           => array(
					'slug'       => str_replace( '_', '-', $slug ),
					'with_front' => false,
				),
			)
		);
	}
}
add_action( 'init', 'bankofart_register_taxonomies', 0 );

/**
 * art_main_color のターム名 → 英語スラッグ対応表。
 *
 * 運用方針：name＝日本語表示（赤/橙/…）、slug＝英語（red/orange/…）。
 * これにより mockup の data-color="red" 等の英語キーがそのまま使え、
 * 変換テーブル不要でフィルター照合できる。
 *
 * @return array
 */
function bankofart_get_color_slug_map() {
	return array(
		'赤'     => 'red',
		'橙'     => 'orange',
		'黄'     => 'yellow',
		'緑'     => 'green',
		'青'     => 'blue',
		'紫'     => 'purple',
		'茶'     => 'brown',
		'白'     => 'white',
		'黒'     => 'black',
		'金'     => 'gold',
		'銀'     => 'silver',
		'その他' => 'other',
	);
}

/**
 * 既定タームを一度だけ投入する。
 *
 * theme-structure.md に列挙された値を初期データとして登録する。
 * オプションフラグで多重実行を防ぐ（管理者が削除したタームは復活しない）。
 *
 * @return void
 */
function bankofart_seed_default_terms() {
	// バージョンを上げると再シードされる。
	//   v3: artist_diagnosis_tag 追加・collector_issue 統一
	//   v4: 診断タグを正式55個へ（オルタナリー / 物語 追加）・art_main_color のカラー情報メタ投入
	//   v5: art_main_color に色（color_hex）初期値を投入
	//   v6: art_main_color のスラッグを英語化（赤→red 等。name は日本語のまま）
	if ( get_option( 'bankofart_terms_seeded_v6' ) ) {
		return;
	}

	$color_slug_map = bankofart_get_color_slug_map();

	foreach ( bankofart_get_taxonomy_config() as $slug => $config ) {
		if ( ! taxonomy_exists( $slug ) ) {
			continue;
		}
		foreach ( $config['terms'] as $term ) {
			if ( term_exists( $term, $slug ) ) {
				continue;
			}
			// art_main_color は新規作成時から英語スラッグを付与（新規インストール用）。
			if ( 'art_main_color' === $slug && isset( $color_slug_map[ $term ] ) ) {
				wp_insert_term( $term, $slug, array( 'slug' => $color_slug_map[ $term ] ) );
			} else {
				wp_insert_term( $term, $slug );
			}
		}
	}

	// 既存の art_main_color ターム（日本語スラッグ）を英語スラッグへ統一。
	// term_id は変わらないため、作品とのひも付け（リレーション）は維持される。
	foreach ( $color_slug_map as $color_name => $en_slug ) {
		$term = get_term_by( 'name', $color_name, 'art_main_color' );
		if ( $term && $term->slug !== $en_slug ) {
			wp_update_term( $term->term_id, 'art_main_color', array( 'slug' => $en_slug ) );
		}
	}

	// 廃止タームを整理。投稿に未使用（count === 0）の場合のみ削除し、データ破壊を避ける。
	$obsolete_terms = array(
		'collector_issue'      => array( 'モチベーション', '他社との差別化', '企業理念浸透' ),
		'artist_diagnosis_tag' => array( 'オルタナティブ' ), // v3 で誤って入れた旧表記。
	);
	foreach ( $obsolete_terms as $taxonomy => $names ) {
		foreach ( $names as $old_term ) {
			$existing = get_term_by( 'name', $old_term, $taxonomy );
			if ( $existing && 0 === (int) $existing->count ) {
				wp_delete_term( $existing->term_id, $taxonomy );
			}
		}
	}

	// art_main_color のカラー情報メタを投入（MB Term Meta）。
	// 既に値があるタームは上書きしない（管理者の編集を尊重）。
	foreach ( bankofart_get_color_meta_seed() as $color_name => $meta ) {
		$term = get_term_by( 'name', $color_name, 'art_main_color' );
		if ( ! $term ) {
			continue;
		}
		$map = array(
			'color_hex'                     => $meta['hex'],
			'color_effect_title'            => $meta['effect_title'],
			'color_effect_description'      => $meta['effect_desc'],
			'recommended_place_title'       => $meta['place_title'],
			'recommended_place_description' => $meta['place_desc'],
		);
		foreach ( $map as $key => $value ) {
			if ( '' === (string) get_term_meta( $term->term_id, $key, true ) ) {
				update_term_meta( $term->term_id, $key, $value );
			}
		}
	}

	update_option( 'bankofart_terms_seeded_v6', 1 );
}
add_action( 'init', 'bankofart_seed_default_terms', 20 );
