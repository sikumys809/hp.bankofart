<?php
/**
 * セクション表示スイッチの「未設定 → '1' 補完」セーフガード。
 *
 * 背景：Meta Box の switch フィールドは std=1（デフォルトON）を「新規投稿」にしか
 * 適用しない。CSVインポート等でスイッチ値が未保存の既存投稿を管理画面で開くと、
 * これらのスイッチが OFF 表示になり、何かを編集して「更新」した瞬間に全 *_show_*
 * スイッチが '0' で保存され、該当セクションが意図せず非表示になる
 * （bankofart_should_show_section() は明示 '0' を非表示扱いにするため）。
 *
 * 対策の肝は「タイミング」。save_post 後では '0' が既に書かれており、
 * 「ユーザーが意図的にOFFにした '0'」と「Meta Box が未設定を OFF 描画して保存した '0'」を
 * 区別できない。そこで edit 画面の描画前（add_meta_boxes）に、メタが存在しない
 * スイッチだけを '1' 補完する。これでフォームは ON 表示になり、更新しても '1' が維持される。
 *
 * 併せて save_post でも「保存後になお未設定のスイッチ」を '1' 補完し、
 * クイック編集・プログラム的な wp_update_post・REST 等の経路もカバーする。
 *
 * いずれも metadata_exists() が false のときだけ補完するため、
 * 明示的に保存済みの '0'（＝ユーザーが意図的にOFF）は決して上書きしない。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT ごとのセクション表示スイッチ（*_show_* 系）一覧。
 *
 * inc/meta-box-fields.php の bankofart_section_switch() 定義と対応させること。
 * セクションスイッチを追加・削除した場合はここも更新する。
 *
 * @return array<string, string[]> post_type => スイッチfield IDの配列。
 */
function bankofart_section_display_switch_map() {
	return array(
		'artist'    => array(
			'artist_show_theme',
			'artist_show_philosophy',
			'artist_show_origin_story',
			'artist_show_goal',
			'artist_show_works',
			'artist_show_articles',
			'artist_show_matching',
			'artist_show_cta',
		),
		'art'       => array(
			'art_show_about',
			'art_show_artist',
			'art_show_more_works',
			'art_show_collected_by',
			'art_show_ownership_history',
			'art_show_cta',
		),
		'collector' => array(
			'collector_show_interview',
			'collector_show_introduced_work',
			'collector_show_same_issue',
			'collector_show_matching',
			'collector_show_cta',
		),
		'news'      => array(
			'news_show_article_ctas',
			'news_show_related_artist',
			'news_show_related_art',
			'news_show_more_news',
			'news_show_cta',
		),
		'journal'   => array(
			'journal_show_article_ctas',
			'journal_show_related_artist',
			'journal_show_related_art',
			'journal_show_more_journal',
			'journal_show_cta',
		),
	);
}

/**
 * 指定投稿のセクション表示スイッチのうち、メタが存在しないものだけ '1' を補完する。
 *
 * 既に値がある（'0' / '1' いずれも）スイッチは尊重して一切触らない。
 *
 * @param int    $post_id   対象投稿ID。
 * @param string $post_type 対象投稿タイプ。
 * @return int 補完した件数。
 */
function bankofart_fill_missing_section_switches( $post_id, $post_type ) {
	$map = bankofart_section_display_switch_map();
	if ( empty( $map[ $post_type ] ) ) {
		return 0;
	}

	$filled = 0;
	foreach ( $map[ $post_type ] as $key ) {
		// 「メタが存在しない」場合のみ '1' 補完。明示的 '0' は metadata_exists=true のため保護される。
		if ( ! metadata_exists( 'post', $post_id, $key ) ) {
			update_post_meta( $post_id, $key, '1' );
			$filled++;
		}
	}
	return $filled;
}

/**
 * 【主対策】編集画面の描画前に未設定スイッチを '1' 補完する。
 *
 * add_meta_boxes は edit 画面の組み立て時（Meta Box が各フィールド値を描画する前）に
 * 発火するため、ここで補完するとフォームが ON 表示になり、更新後も '1' が維持される。
 *
 * @param string  $post_type 現在の投稿タイプ。
 * @param WP_Post $post      現在の投稿。
 * @return void
 */
function bankofart_guard_section_switches_on_edit( $post_type, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	// 新規（auto-draft）は Meta Box の std=1 が正しく効くため対象外。既存投稿のみ補完。
	if ( 'auto-draft' === $post->post_status ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	bankofart_fill_missing_section_switches( $post->ID, $post_type );
}
add_action( 'add_meta_boxes', 'bankofart_guard_section_switches_on_edit', 10, 2 );

/**
 * 【補助対策】保存後になお未設定のスイッチを '1' 補完する。
 *
 * 編集画面以外の保存経路（クイック編集 / プログラム的更新 / REST 等）で
 * スイッチが未設定のまま残るケースを救済する。編集画面経由の通常保存では
 * Meta Box がスイッチ値を書き込むため metadata_exists=true となり、ここは no-op。
 *
 * @param int     $post_id 保存された投稿ID。
 * @param WP_Post $post    保存された投稿。
 * @return void
 */
function bankofart_guard_section_switches_on_save( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	bankofart_fill_missing_section_switches( $post_id, $post->post_type );
}
add_action( 'save_post', 'bankofart_guard_section_switches_on_save', 20, 2 );
