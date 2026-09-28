<?php
/**
 * WP側のARTページを撤廃し、コレクションサイトへ寄せる
 *
 * 作品データの正は collection.bankof-art.com になったため、WPの art 投稿を
 * 公開ページとして見せるのはやめる。ただし投稿そのものは消さない。
 *   - 企業ページ（collector）との関連付けが残っている
 *   - JOURNAL / NEWS から参照できる余地を残しておきたい
 *   - 過去の入稿データを失いたくない
 * ため、「消さずに、行き先だけ collection に向ける」形にする。
 *
 * 作品単位のURLは両サイトで対応が取れない（WPの作品名は正式名称ではなく、
 * collection の作品IDとも紐づいていない）。そのかわり画家のスラッグは一致するので、
 * 個別ページはその画家の作品一覧へ送る。それも分からなければ作品一覧のトップへ送る。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ARTページの撤廃が有効かどうか。
 *
 * 戻したくなったときに1か所で切り替えられるようにしておく。
 *
 * @return bool
 */
function bankofart_art_pages_retired() {
	return (bool) apply_filters( 'bankofart_art_pages_retired', true );
}

/**
 * art 投稿の行き先（collection側のURL）を返す。
 *
 * @param int $art_id 作品の投稿ID。
 * @return string
 */
function bankofart_art_redirect_target( $art_id ) {
	$artists = bankofart_get_connected( 'artist_to_art', 'to', $art_id );
	if ( $artists ) {
		$slug = get_post_field( 'post_name', $artists[0]->ID );
		if ( $slug ) {
			return bankofart_collection_artist_url( $slug );
		}
	}
	return bankofart_collection_arts_url();
}

/**
 * ARTの一覧・個別ページをコレクションサイトへ転送する。
 *
 * 恒久的な移転なので 301。ログイン中かどうかに関わらず転送する
 * （管理者だけ古いページが見えると、撤廃できているのか確認できないため）。
 * 編集画面の「プレビュー」だけは、中身を見られるように素通しする。
 *
 * @return void
 */
function bankofart_art_redirect_to_collection() {
	if ( ! bankofart_art_pages_retired() ) {
		return;
	}
	if ( is_preview() ) {
		return;
	}

	if ( is_post_type_archive( 'art' ) || is_tax( array( 'art_status', 'art_genre', 'art_technique', 'art_form', 'art_size', 'art_color' ) ) ) {
		wp_redirect( bankofart_collection_arts_url(), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}

	if ( is_singular( 'art' ) ) {
		wp_redirect( bankofart_art_redirect_target( get_queried_object_id() ), 301 ); // phpcs:ignore WordPress.Security.SafeRedirect
		exit;
	}
}
add_action( 'template_redirect', 'bankofart_art_redirect_to_collection' );

/**
 * art 投稿をサイト内検索の対象から外す。
 *
 * 転送されるだけのページが検索結果に並ぶのを防ぐ。
 *
 * @param array  $args      register_post_type の引数。
 * @param string $post_type 投稿タイプ。
 * @return array
 */
function bankofart_art_exclude_from_search( $args, $post_type ) {
	if ( 'art' === $post_type && bankofart_art_pages_retired() ) {
		$args['exclude_from_search'] = true;
	}
	return $args;
}
add_filter( 'register_post_type_args', 'bankofart_art_exclude_from_search', 10, 2 );

/**
 * art をサイトマップから外す。
 *
 * @param array $post_types 投稿タイプの配列（キーがスラッグ）。
 * @return array
 */
function bankofart_art_remove_from_sitemap( $post_types ) {
	if ( bankofart_art_pages_retired() ) {
		unset( $post_types['art'] );
	}
	return $post_types;
}
add_filter( 'wp_sitemaps_post_types', 'bankofart_art_remove_from_sitemap' );

/**
 * 管理画面の作品一覧に、撤廃済みであることを出しておく。
 *
 * 編集しても公開ページには出ないので、気づかずに入稿し続けるのを防ぐ。
 *
 * @return void
 */
function bankofart_art_admin_notice() {
	if ( ! bankofart_art_pages_retired() ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'art' !== $screen->post_type ) {
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong><?php esc_html_e( 'この「作品」は公開ページを持ちません。', 'bankofart' ); ?></strong><br>
			<?php
			printf(
				/* translators: %s: collection site URL */
				esc_html__( '作品の公開先は %s に移りました。こちらのデータは企業ページとの関連付け・過去データの保存用に残しています。', 'bankofart' ),
				'<code>' . esc_html( BANKOFART_COLLECTION_URL ) . '</code>'
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'bankofart_art_admin_notice' );
