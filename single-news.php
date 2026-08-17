<?php
/**
 * 単一 NEWS（single-news）テンプレート
 *
 * news-single.html のモックは存在しないため、single-art / single-artist の
 * デザイン作法（breadcrumb / hero / セクション / .rv リビール / tokens）に準拠して
 * 記事ページとして新規構成する。本文は news_sections（リピーター）を使用。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$nid = get_the_ID();

	// ---- 基本 ----
	$title    = get_the_title();
	$date     = get_the_date( 'Y.m.d' );
	$category = bankofart_get_first_term_name( $nid, 'news_category' );
	$summary  = rwmb_meta( 'news_summary' );
	$ext_url  = rwmb_meta( 'news_external_url' );
	$ext_lbl  = rwmb_meta( 'news_external_label' );

	// 外部リンクが YouTube の場合は埋め込み再生用URLを生成（ABOUT/Artist の動画と同じ作法）。
	// 通常の動画URL・短縮URL・shorts に対応。YouTube 以外は通常の外部リンク扱い。
	$video_embed = '';
	if ( ! empty( $ext_url ) && preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([\w-]{11})~', $ext_url, $m ) ) {
		$video_embed = 'https://www.youtube.com/embed/' . $m[1];
	}

	// アイキャッチ：news_main_image（Meta Box）優先、無ければ投稿サムネイル。
	$eyecatch = bankofart_get_image( 'news_main_image', $nid, 'large' );
	if ( empty( $eyecatch['url'] ) && has_post_thumbnail( $nid ) ) {
		$eyecatch['url'] = get_the_post_thumbnail_url( $nid, 'large' );
		$eyecatch['alt'] = $title;
	}

	// ---- 本文セクション（リピーター）----
	$sections = array_filter( (array) rwmb_meta( 'news_sections' ) );

	// ---- リレーション ----
	$rel_artists = bankofart_get_connected( 'news_to_artist', 'from', $nid );
	$rel_arts    = bankofart_get_connected( 'news_to_art', 'from', $nid );

	// ---- 関連NEWS（同カテゴリ・自分除外・3件）----
	$more_news   = array();
	$cat_terms   = get_the_terms( $nid, 'news_category' );
	if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) {
		$more_q = new WP_Query(
			array(
				'post_type'      => 'news',
				'post_status'    => 'publish',
				'post__not_in'   => array( $nid ),
				'posts_per_page' => 3,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'news_category',
						'field'    => 'term_id',
						'terms'    => wp_list_pluck( $cat_terms, 'term_id' ),
						'operator' => 'IN',
					),
				),
			)
		);
		$more_news = $more_q->posts;
		wp_reset_postdata();
	}

	// ---- セクション可視性 ----
	$show_body           = bankofart_should_show_section( '', $sections, $nid );
	$show_related_artist = bankofart_should_show_section( 'news_show_related_artist', $rel_artists, $nid );
	$show_related_art    = bankofart_should_show_section( 'news_show_related_art', $rel_arts, $nid );
	$show_more           = bankofart_should_show_section( 'news_show_more_news', $more_news, $nid );
	$show_cta            = bankofart_should_show_section( 'news_show_cta', true, $nid );
	?>

<main id="main" class="single-news">

	<nav class="breadcrumb" aria-label="breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">HOME</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'news' ) ); ?>">NEWS</a>
		<span class="sep">/</span>
		<span><?php echo esc_html( $title ); ?></span>
	</nav>

	<!-- ════════ HERO ════════ -->
	<header class="sn-hero">
		<div class="sn-hero-meta rv">
			<?php if ( ! empty( $category ) ) : ?>
				<span class="sn-hero-cat"><?php echo esc_html( $category ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $date ) ) : ?>
				<time class="sn-hero-date boa-num" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( $date ); ?></time>
			<?php endif; ?>
		</div>
		<h1 class="sn-hero-title rv d1"><?php echo esc_html( $title ); ?></h1>
	</header>

	<?php if ( ! empty( $video_embed ) ) : ?>
		<!-- 外部リンクが YouTube の場合：single ページ上で直接再生（ABOUT の動画と同様の表示）。 -->
		<div class="sn-video rv">
			<div class="sn-video-frame">
				<iframe src="<?php echo esc_url( $video_embed ); ?>" title="<?php echo esc_attr( $title ); ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
			</div>
		</div>
	<?php elseif ( ! empty( $eyecatch['url'] ) ) : ?>
		<div class="sn-eyecatch rv">
			<img src="<?php echo esc_url( $eyecatch['url'] ); ?>" alt="<?php echo esc_attr( ! empty( $eyecatch['alt'] ) ? $eyecatch['alt'] : $title ); ?>" loading="lazy">
		</div>
	<?php endif; ?>

	<!-- ════════ 本文 ════════ -->
	<article class="sn-article">

		<?php if ( ! empty( $summary ) ) : ?>
			<p class="sn-lead rv"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>

		<?php if ( $show_body ) : ?>
			<?php
			foreach ( $sections as $sec ) :
				$heading = isset( $sec['section_heading'] ) ? $sec['section_heading'] : '';
				$body    = isset( $sec['section_body'] ) ? $sec['section_body'] : '';
				$img_ids = ! empty( $sec['section_images'] ) ? (array) $sec['section_images'] : array();
				// 空セクション（見出し・本文・画像すべて無し）はスキップ。
				if ( '' === trim( (string) $heading ) && '' === trim( wp_strip_all_tags( (string) $body ) ) && empty( $img_ids ) ) {
					continue;
				}
				?>
				<section class="sn-section rv">
					<?php if ( '' !== trim( (string) $heading ) ) : ?>
						<h2 class="sn-section-title"><?php echo esc_html( $heading ); ?></h2>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( (string) $body ) ) ) : ?>
						<div class="sn-section-body"><?php echo wp_kses_post( bankofart_richtext( $body ) ); ?></div>
					<?php endif; ?>

					<?php if ( ! empty( $img_ids ) ) : ?>
						<div class="sn-section-figs">
							<?php
							foreach ( $img_ids as $img_id ) :
								$img_id = (int) $img_id;
								if ( ! $img_id ) {
									continue;
								}
								echo wp_get_attachment_image(
									$img_id,
									'large',
									false,
									array(
										'class'   => 'sn-section-fig',
										'loading' => 'lazy',
									)
								);
							endforeach;
							?>
						</div>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( empty( $video_embed ) && ! empty( $ext_url ) ) : ?>
			<div class="sn-external rv">
				<a href="<?php echo esc_url( $ext_url ); ?>" class="sn-external-btn" target="_blank" rel="noopener">
					<?php echo esc_html( ! empty( $ext_lbl ) ? $ext_lbl : '元記事を読む' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</article>

	<!-- ════════ 関連アーティスト ════════ -->
	<?php if ( $show_related_artist ) : ?>
		<section class="sn-related">
			<div class="sn-related-head">
				<div class="sn-related-en rv">RELATED ARTIST</div>
				<div class="sn-related-ja rv d1">関連アーティスト</div>
			</div>
			<div class="artist-grid">
				<?php
				foreach ( $rel_artists as $artist ) :
					get_template_part(
						'template-parts/cards/card-artist',
						null,
						array(
							'artist_id' => $artist->ID,
							'context'   => 'related',
						)
					);
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ 関連作品 ════════ -->
	<?php if ( $show_related_art ) : ?>
		<section class="sn-related">
			<div class="sn-related-head">
				<div class="sn-related-en rv">RELATED ART</div>
				<div class="sn-related-ja rv d1">関連作品</div>
			</div>
			<div class="art-grid">
				<?php
				foreach ( $rel_arts as $art ) :
					get_template_part(
						'template-parts/cards/card-art',
						null,
						array(
							'art_id'  => $art->ID,
							'context' => 'related',
						)
					);
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ MORE NEWS ════════ -->
	<?php if ( $show_more ) : ?>
		<section class="sn-more">
			<div class="sn-related-head">
				<div class="sn-related-en rv">MORE NEWS</div>
				<div class="sn-related-ja rv d1">関連記事</div>
			</div>
			<div class="news-list">
				<?php
				foreach ( $more_news as $mn ) :
					get_template_part(
						'template-parts/cards/card-news',
						null,
						array(
							'news_id' => $mn->ID,
						)
					);
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ BACK LINK ════════ -->
	<div class="sn-back">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'news' ) ); ?>">NEWS一覧へ戻る</a>
	</div>

	<!-- ════════ CTA ════════ -->
	<?php
	if ( $show_cta ) {
		get_template_part( 'template-parts/sections/section-cta' );
	}
	?>

</main>

	<?php
endwhile;

get_footer();
