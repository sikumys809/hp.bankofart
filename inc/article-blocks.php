<?php
/**
 * 記事（JOURNAL / NEWS）に後から足せる部品：動画とCTA
 *
 * どちらも本文セクション（journal_sections / news_sections）の外に置いている。
 * JSON取り込みは本文セクションを update_post_meta で丸ごと入れ替えるため、
 * リピーターの中に足すと取り込みのたびに消えてしまうため。
 * ここで定義するフィールドは取り込みが触らないので、手で設定した内容が残る。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CTAの種類と、その既定のラベル・リンク先。
 *
 * 'url' は呼び出し時に解決する（home_url がこの時点では使えないため）。
 *
 * @return array 種類キー => array( 'label' => 既定ラベル, 'admin' => 管理画面の表示名 )。
 */
function bankofart_cta_types() {
	return array(
		'collection' => array(
			'admin' => '作品を見る（コレクションサイトへ）',
			'label' => '作品を見る',
		),
		'art_cards'  => array(
			'admin' => '作品カードを並べる（コレクションの作品を選ぶ）',
			'label' => '',
		),
		'briefing'   => array(
			'admin' => 'オンライン説明会を予約',
			'label' => 'オンライン説明会を予約する',
		),
		'document'   => array(
			'admin' => '資料請求',
			'label' => '資料を請求する',
		),
		'artist'     => array(
			'admin' => 'この記事のアーティストページへ',
			'label' => 'アーティストを見る',
		),
		'artist_all' => array(
			'admin' => 'アーティスト一覧へ',
			'label' => 'アーティストを探す',
		),
		'collector'  => array(
			'admin' => '画家応援企業の事例へ',
			'label' => '導入事例を見る',
		),
		'entry'      => array(
			'admin' => '画家応募フォームへ',
			'label' => '画家として応募する',
		),
		'custom'     => array(
			'admin' => '自由リンク（URLとラベルを自分で入れる）',
			'label' => '',
		),
	);
}

/**
 * CTAリピーターのフィールド定義を返す。
 *
 * JOURNAL と NEWS で同じものを使う。
 *
 * @param string $prefix 'journal' | 'news'。
 * @param string $tab    所属タブキー。
 * @return array Meta Box のフィールド定義（2件）。
 */
function bankofart_cta_fields( $prefix, $tab ) {
	$choices = array();
	foreach ( bankofart_cta_types() as $key => $t ) {
		$choices[ $key ] = $t['admin'];
	}

	return array(
		array(
			'id'   => $prefix . '_cta_heading',
			'name' => 'CTAの見出し',
			'type' => 'text',
			'tab'  => $tab,
			'desc' => '任意。未入力ならボタンだけを出します。',
		),
		array(
			'id'          => $prefix . '_ctas',
			'name'        => 'CTAボタン',
			'type'        => 'group',
			'tab'         => $tab,
			'clone'       => true,
			'sort_clone'  => true,
			'collapsible' => true,
			'add_button'  => 'CTAを追加',
			'group_title' => array( 'field' => 'cta_label' ),
			'desc'        => '本文の下に、並べた順で表示します。JSON取り込みでは消えません。',
			'fields'      => array(
				array(
					'id'      => 'cta_type',
					'name'    => '種類',
					'type'    => 'select',
					'options' => $choices,
					'std'     => 'collection',
				),
				array(
					'id'   => 'cta_label',
					'name' => 'ボタンの文字',
					'type' => 'text',
					'desc' => '未入力なら種類ごとの既定文言を使います。',
				),
				array(
					'id'   => 'cta_note',
					'name' => '補足テキスト',
					'type' => 'text',
					'desc' => 'ボタンの下に小さく出ます（任意）。',
				),
				array(
					'id'   => 'cta_url',
					'name' => 'リンク先URL',
					'type' => 'url',
					'desc' => '「自由リンク」のときだけ使います。',
				),
				array(
					'id'          => 'cta_arts',
					'name'        => '並べる作品',
					'type'        => 'select_advanced',
					'multiple'    => true,
					'ajax'        => false,
					'placeholder' => '画家名・作品名で検索できます',
					'options'     => ( function_exists( 'bankofart_collection_is_editing' ) && bankofart_collection_is_editing( $prefix ) )
						? bankofart_collection_art_labels()
						: array(),
					'desc'        => '「作品カードを並べる」のときだけ使います。選んだ順に並びます。',
				),
			),
		),
	);
}

/**
 * 動画フィールドの定義を返す。
 *
 * @param string $prefix 'journal' | 'news'。
 * @param string $tab    所属タブキー。
 * @return array Meta Box のフィールド定義（3件）。
 */
function bankofart_article_video_fields( $prefix, $tab ) {
	return array(
		array(
			'id'   => $prefix . '_video_url',
			'name' => '動画URL（YouTube）',
			'type' => 'url',
			'tab'  => $tab,
			'desc' => '通常URL・短縮URL（youtu.be）・ショートのどれでもOK。未入力なら表示されません。',
		),
		array(
			'id'   => $prefix . '_video_caption',
			'name' => '動画の見出し',
			'type' => 'text',
			'tab'  => $tab,
			'desc' => '任意。動画の上に小さく出ます。',
		),
		array(
			'id'      => $prefix . '_video_position',
			'name'    => '動画の位置',
			'type'    => 'select',
			'tab'     => $tab,
			'options' => array(
				'lead' => '導入文のすぐ下',
				'body' => '本文の下',
			),
			'std'     => 'lead',
		),
	);
}

/**
 * 記事の動画ブロックを出力する。
 *
 * @param string $prefix   'journal' | 'news'。
 * @param string $position 'lead' | 'body'。この位置のときだけ出す。
 * @param int    $post_id  投稿ID。
 * @return void
 */
function bankofart_render_article_video( $prefix, $position, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$embed = bankofart_video_embed_url( (string) rwmb_meta( $prefix . '_video_url', '', $post_id ) );
	if ( '' === $embed ) {
		return;
	}

	$at = (string) rwmb_meta( $prefix . '_video_position', '', $post_id );
	if ( '' === $at ) {
		$at = 'lead';
	}
	if ( $at !== $position ) {
		return;
	}

	$caption = (string) rwmb_meta( $prefix . '_video_caption', '', $post_id );
	?>
	<div class="article-video rv">
		<?php if ( '' !== trim( $caption ) ) : ?>
			<div class="article-video-head"><?php echo esc_html( $caption ); ?></div>
		<?php endif; ?>
		<div class="article-video-frame">
			<iframe src="<?php echo esc_url( $embed ); ?>" title="<?php echo esc_attr( get_the_title( $post_id ) ); ?>" loading="lazy" allowfullscreen></iframe>
		</div>
	</div>
	<?php
}

/**
 * CTA1件ぶんのリンク先とラベルを決める。
 *
 * @param array $row     リピーターの1行。
 * @param int   $post_id 投稿ID。
 * @return array|null array( 'url', 'label', 'external' )。出さないときは null。
 */
function bankofart_cta_resolve( array $row, $post_id ) {
	$types = bankofart_cta_types();
	$type  = isset( $row['cta_type'] ) ? (string) $row['cta_type'] : 'collection';
	if ( ! isset( $types[ $type ] ) ) {
		return null;
	}

	$label    = isset( $row['cta_label'] ) ? trim( (string) $row['cta_label'] ) : '';
	$external = false;

	switch ( $type ) {
		case 'collection':
			$url      = function_exists( 'bankofart_collection_arts_url' ) ? bankofart_collection_arts_url() : home_url( '/' );
			$external = true;
			break;

		case 'briefing':
			$url = bankofart_briefing_url();
			break;

		case 'document':
			$url = bankofart_document_request_url();
			break;

		case 'entry':
			$url = bankofart_apply_url();
			break;

		case 'artist_all':
			$url = (string) get_post_type_archive_link( 'artist' );
			break;

		case 'collector':
			$url = (string) get_post_type_archive_link( 'collector' );
			break;

		case 'artist':
			// 記事に紐づいた関連アーティストの先頭へ。無ければ一覧へ。
			$rel = bankofart_get_connected( ( 'news' === get_post_type( $post_id ) ? 'news_to_artist' : 'journal_to_artist' ), 'from', $post_id );
			$url = $rel ? (string) get_permalink( $rel[0]->ID ) : (string) get_post_type_archive_link( 'artist' );
			if ( $rel && '' === $label ) {
				$label = get_the_title( $rel[0]->ID ) . ' を見る';
			}
			break;

		case 'custom':
			$url = isset( $row['cta_url'] ) ? trim( (string) $row['cta_url'] ) : '';
			if ( '' === $url ) {
				return null;
			}
			// 外部サイトなら別タブで開く。
			$host     = (string) wp_parse_url( $url, PHP_URL_HOST );
			$external = ( '' !== $host && $host !== (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
			break;

		default:
			return null;
	}

	if ( '' === $url ) {
		return null;
	}
	if ( '' === $label ) {
		$label = $types[ $type ]['label'];
	}
	if ( '' === $label ) {
		return null;
	}

	return array(
		'url'      => $url,
		'label'    => $label,
		'external' => $external,
	);
}

/**
 * 記事のCTAブロックを出力する。
 *
 * ボタンと「作品カードを並べる」が混在できる。並べた順に出す。
 *
 * @param string $prefix  'journal' | 'news'。
 * @param int    $post_id 投稿ID。
 * @return void
 */
function bankofart_render_article_ctas( $prefix, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	$rows = array_filter( (array) rwmb_meta( $prefix . '_ctas', '', $post_id ) );
	if ( ! $rows ) {
		return;
	}

	// 先に中身を組み立てて、1つも出ないときはブロックごと出さない。
	$blocks = array();
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$type = isset( $row['cta_type'] ) ? (string) $row['cta_type'] : '';

		if ( 'art_cards' === $type ) {
			$ids = array_filter( array_map( 'strval', (array) ( $row['cta_arts'] ?? array() ) ), 'strlen' );
			if ( ! $ids || ! function_exists( 'bankofart_collection_get_arts_by_ids' ) ) {
				continue;
			}
			$arts = bankofart_collection_get_arts_by_ids( $ids );
			if ( ! $arts ) {
				continue;
			}
			$blocks[] = array(
				'kind' => 'arts',
				'arts' => $arts,
				'note' => isset( $row['cta_note'] ) ? trim( (string) $row['cta_note'] ) : '',
			);
			continue;
		}

		$cta = bankofart_cta_resolve( $row, $post_id );
		if ( ! $cta ) {
			continue;
		}
		$cta['kind'] = 'button';
		$cta['note'] = isset( $row['cta_note'] ) ? trim( (string) $row['cta_note'] ) : '';
		$blocks[]    = $cta;
	}

	if ( ! $blocks ) {
		return;
	}

	$heading = (string) rwmb_meta( $prefix . '_cta_heading', '', $post_id );
	?>
	<div class="article-cta rv">
		<?php if ( '' !== trim( $heading ) ) : ?>
			<h2 class="article-cta-head"><?php echo esc_html( $heading ); ?></h2>
		<?php endif; ?>

		<?php foreach ( $blocks as $b ) : ?>
			<?php if ( 'arts' === $b['kind'] ) : ?>
				<div class="article-cta-arts">
					<?php
					foreach ( $b['arts'] as $art ) {
						get_template_part(
							'template-parts/cards/card-collection-art',
							null,
							array(
								'art'     => $art,
								'context' => 'related',
							)
						);
					}
					?>
				</div>
				<?php if ( '' !== $b['note'] ) : ?>
					<p class="article-cta-note"><?php echo esc_html( $b['note'] ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<div class="article-cta-row">
					<a class="article-cta-btn" href="<?php echo esc_url( $b['url'] ); ?>"<?php echo $b['external'] ? ' target="_blank" rel="noopener"' : ''; ?>>
						<?php echo esc_html( $b['label'] ); ?>
					</a>
					<?php if ( '' !== $b['note'] ) : ?>
						<p class="article-cta-note"><?php echo esc_html( $b['note'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php
}
