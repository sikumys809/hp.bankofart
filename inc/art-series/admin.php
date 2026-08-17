<?php
/**
 * 作品シリーズ：編集画面での共通項目の自動入力
 *
 * 同じシリーズの作品は、素材・支持体／ジャンル／技法／作品説明／コンセプトなどが
 * ほぼ同じになる。そこで art_series タクソノミーのターム メタに「シリーズ共通の初期値」を
 * 持たせ（inc/term-meta-fields.php）、作品編集画面でシリーズを選んだ時点で
 * 空欄のフィールドへ流し込む。
 *
 * 差し込み対象:
 *   post_title（作品名のベース）/ art_title_en / art_medium / art_series_name /
 *   art_genre_picker / art_technique_picker / art_form_picker /
 *   art_description / art_concept
 *
 * 作品ごとに必ず変わる項目（作品NO.・制作年・サイズ・メインカラー・画像）は対象外。
 *
 * 既定は「空欄のみ埋める」。入力済みの内容は勝手に消さない。
 * まとめて置き換えたいときだけ、画面のボタンから明示的に上書きする。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * シリーズのタームから、作品編集画面へ流し込む値を組み立てる。
 *
 * @param int $term_id art_series のターム ID。
 * @return array|WP_Error 差し込み用の連想配列。
 */
function bankofart_art_series_get_template( $term_id ) {
	$term = get_term( (int) $term_id, 'art_series' );
	if ( ! $term || is_wp_error( $term ) ) {
		return new WP_Error( 'boa_series_not_found', 'シリーズが見つかりませんでした。' );
	}

	/**
	 * ターム メタの複数値を配列で取り出す。
	 *
	 * select_advanced（multiple）は複数行で保存されるため get_term_meta( , , false ) を使う。
	 * 単一行に配列で入っている場合にも備えて平坦化する。
	 *
	 * @param int    $tid ターム ID。
	 * @param string $key メタキー。
	 * @return string[]
	 */
	$multi = static function ( $tid, $key ) {
		$raw  = get_term_meta( $tid, $key, false );
		$out  = array();
		foreach ( (array) $raw as $v ) {
			if ( is_array( $v ) ) {
				$out = array_merge( $out, $v );
			} elseif ( '' !== (string) $v ) {
				$out[] = (string) $v;
			}
		}
		return array_values( array_unique( array_filter( $out, 'strlen' ) ) );
	};

	/**
	 * ターム名の配列を、そのタクソノミーの term_id 配列に変換する。
	 *
	 * @param string[] $names    ターム名。
	 * @param string   $taxonomy タクソノミー。
	 * @return int[]
	 */
	$to_ids = static function ( $names, $taxonomy ) {
		$ids = array();
		foreach ( (array) $names as $name ) {
			$t = get_term_by( 'name', $name, $taxonomy );
			if ( ! $t ) {
				$t = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			}
			if ( $t && ! is_wp_error( $t ) ) {
				$ids[] = (int) $t->term_id;
			}
		}
		return $ids;
	};

	return array(
		// キーは編集画面のフィールドID（JS側でそのまま参照する）。
		'post_title'           => (string) get_term_meta( $term_id, 'series_title_base', true ),
		'art_title_en'         => (string) get_term_meta( $term_id, 'series_title_en', true ),
		'art_medium'           => (string) get_term_meta( $term_id, 'series_medium', true ),
		'art_series_name'      => $term->name,
		'art_description'      => (string) get_term_meta( $term_id, 'series_description', true ),
		'art_concept'          => (string) get_term_meta( $term_id, 'series_concept', true ),
		'art_genre_picker'     => $to_ids( $multi( $term_id, 'series_genre' ), 'art_genre' ),
		'art_technique_picker' => $to_ids( $multi( $term_id, 'series_technique' ), 'art_technique' ),
		'art_form_picker'      => $to_ids( $multi( $term_id, 'series_form' ), 'art_form' ),
	);
}

/**
 * admin-ajax：シリーズの共通項目を返す。
 *
 * @return void
 */
function bankofart_art_series_ajax() {
	check_ajax_referer( 'boa_art_series', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
	if ( ! $term_id ) {
		wp_send_json_error( array( 'message' => 'シリーズが指定されていません。' ), 400 );
	}

	$template = bankofart_art_series_get_template( $term_id );
	if ( is_wp_error( $template ) ) {
		wp_send_json_error( array( 'message' => $template->get_error_message() ), 404 );
	}

	wp_send_json_success( $template );
}
add_action( 'wp_ajax_bankofart_art_series', 'bankofart_art_series_ajax' );

/**
 * 作品の編集画面にだけ、シリーズ自動入力のスクリプトを読み込む。
 *
 * @param string $hook 現在の管理画面。
 * @return void
 */
function bankofart_art_series_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'art' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_script(
		'bankofart-admin-art-series',
		get_theme_file_uri( 'assets/js/admin-art-series.js' ),
		// select2（Meta Box のジャンル・技法ピッカー）へ変更を伝えるため jQuery に依存する。
		// 管理画面限定であり、フロント側は従来どおりバニラJSのみ。
		array( 'jquery' ),
		bankofart_assets_version(),
		true
	);

	wp_localize_script(
		'bankofart-admin-art-series',
		'BOA_ART_SERIES',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'boa_art_series' ),
			'labels'  => array(
				'filled'    => 'シリーズ「%s」の内容を空欄に入力しました：',
				'nothing'   => 'シリーズ「%s」に共通項目が登録されていません。「作品シリーズ」の編集画面で入力してください。',
				'allFilled' => 'すべての項目が入力済みのため、変更していません。',
				'overwrite' => 'シリーズの内容で上書きする',
				'confirm'   => '入力済みの項目もシリーズの内容で上書きします。よろしいですか？',
				'error'     => 'シリーズの内容を取得できませんでした。',
			),
			'fields'  => array(
				'post_title'           => '作品名（TITLE）',
				'art_title_en'         => '作品英題',
				'art_medium'           => '素材・支持体',
				'art_series_name'      => 'シリーズ名',
				'art_genre_picker'     => 'ジャンル',
				'art_technique_picker' => '技法',
				'art_form_picker'      => '形態',
				'art_description'      => '作品説明',
				'art_concept'          => '作品コンセプト',
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'bankofart_art_series_enqueue' );
