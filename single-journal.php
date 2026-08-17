<?php
/**
 * 単一 JOURNAL（single-journal）テンプレート
 *
 * single-news.php をベースに journal 用へ展開。読み物（コラム/インタビュー）の
 * 記事ページとして、著者・読了時間を加えた構成。本文は journal_sections（リピーター）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$jid = get_the_ID();

	// ---- 基本 ----
	$title    = get_the_title();
	$date     = get_the_date( 'Y.m.d' );
	$category = bankofart_get_first_term_name( $jid, 'journal_category' );
	$summary  = rwmb_meta( 'journal_summary' );
	$author   = rwmb_meta( 'journal_author' );
	$reading  = rwmb_meta( 'journal_reading_time' );

	// アイキャッチ：journal_main_image 優先、無ければ投稿サムネイル。
	$eyecatch = bankofart_get_image( 'journal_main_image', $jid, 'large' );
	if ( empty( $eyecatch['url'] ) && has_post_thumbnail( $jid ) ) {
		$eyecatch['url'] = get_the_post_thumbnail_url( $jid, 'large' );
		$eyecatch['alt'] = $title;
	}

	// ---- 記事デザイン（コラム / インタビュー）----
	// カテゴリー「インタビュー」＝アイコン＋吹き出し形式、それ以外＝従来のコラム形式。
	// 管理画面の「記事デザイン」で個別に上書きもできる。
	$layout = bankofart_journal_layout( $jid );

	// ---- 本文セクション（リピーター）----
	$sections = array_filter( (array) rwmb_meta( 'journal_sections' ) );
	$qa_rows  = array_filter( (array) rwmb_meta( 'journal_interview_qa' ) );

	// ---- リレーション ----
	$rel_artists = bankofart_get_connected( 'journal_to_artist', 'from', $jid );
	$rel_arts    = bankofart_get_connected( 'journal_to_art', 'from', $jid );

	// ---- 関連JOURNAL（同カテゴリ・自分除外・3件）----
	$more_journal = array();
	$cat_terms    = get_the_terms( $jid, 'journal_category' );
	if ( ! is_wp_error( $cat_terms ) && ! empty( $cat_terms ) ) {
		$more_q = new WP_Query(
			array(
				'post_type'      => 'journal',
				'post_status'    => 'publish',
				'post__not_in'   => array( $jid ),
				'posts_per_page' => 3,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'journal_category',
						'field'    => 'term_id',
						'terms'    => wp_list_pluck( $cat_terms, 'term_id' ),
						'operator' => 'IN',
					),
				),
			)
		);
		$more_journal = $more_q->posts;
		wp_reset_postdata();
	}

	// ---- セクション可視性 ----
	$show_body           = ( 'interview' === $layout )
		? bankofart_should_show_section( '', $qa_rows, $jid )
		: bankofart_should_show_section( '', $sections, $jid );
	// インタビューでは1人目を導入文の直下にプロフィール付きで出すので、
	// ページ下部の RELATED ARTIST は2人目以降だけにする（同じ人が2回出るのを防ぐ）。
	$is_interview_body = ( $show_body && 'interview' === $layout );
	$bottom_artists    = ( $is_interview_body && ! empty( $rel_artists ) ) ? array_slice( $rel_artists, 1 ) : $rel_artists;

	$show_related_artist = bankofart_should_show_section( 'journal_show_related_artist', $bottom_artists, $jid );
	$show_related_art    = bankofart_should_show_section( 'journal_show_related_art', $rel_arts, $jid );
	$show_more           = bankofart_should_show_section( 'journal_show_more_journal', $more_journal, $jid );
	$show_cta            = bankofart_should_show_section( 'journal_show_cta', true, $jid );
	?>

<main id="main" class="single-journal single-journal--<?php echo esc_attr( $layout ); ?>">

	<nav class="breadcrumb" aria-label="breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">HOME</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'journal' ) ); ?>">JOURNAL</a>
		<span class="sep">/</span>
		<span><?php echo esc_html( $title ); ?></span>
	</nav>

	<!-- ════════ HERO ════════ -->
	<header class="sj-hero">
		<div class="sj-hero-meta rv">
			<?php if ( ! empty( $category ) ) : ?>
				<span class="sj-hero-cat"><?php echo esc_html( $category ); ?></span>
			<?php endif; ?>
			<?php if ( ! empty( $date ) ) : ?>
				<time class="sj-hero-date boa-num" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( $date ); ?></time>
			<?php endif; ?>
			<?php if ( ! empty( $reading ) ) : ?>
				<span class="sj-hero-read"><span class="boa-num"><?php echo esc_html( $reading ); ?></span>分で読めます</span>
			<?php endif; ?>
		</div>
		<h1 class="sj-hero-title rv d1"><?php echo esc_html( $title ); ?></h1>
		<?php
		// インタビューは「取材・文」、コラムは「文」。
		$byline_label = ( 'interview' === $layout ) ? '取材・文' : '文';
		if ( ! empty( $author ) ) :
			?>
			<p class="sj-hero-byline rv d1"><?php echo esc_html( $byline_label ); ?> ／ <?php echo esc_html( $author ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( ! empty( $eyecatch['url'] ) ) : ?>
		<div class="sj-eyecatch rv">
			<img src="<?php echo esc_url( $eyecatch['url'] ); ?>" alt="<?php echo esc_attr( ! empty( $eyecatch['alt'] ) ? $eyecatch['alt'] : $title ); ?>" loading="lazy">
		</div>
	<?php endif; ?>

	<!-- ════════ 本文 ════════ -->
	<article class="sj-article">

		<?php if ( ! empty( $summary ) ) : ?>
			<p class="sj-lead rv"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>

		<?php if ( $show_body && 'interview' === $layout ) : ?>
			<?php
			// インタビュー：アイコン＋吹き出しのQ&A。
			get_template_part(
				'template-parts/journal/body',
				'interview',
				array(
					'journal_id'  => $jid,
					'rel_artists' => $rel_artists,
				)
			);
			?>
		<?php elseif ( $show_body ) : ?>
			<?php
			foreach ( $sections as $sec ) :
				$heading = isset( $sec['section_heading'] ) ? $sec['section_heading'] : '';
				$body    = isset( $sec['section_body'] ) ? $sec['section_body'] : '';
				$img_ids = ! empty( $sec['section_images'] ) ? (array) $sec['section_images'] : array();
				if ( '' === trim( (string) $heading ) && '' === trim( wp_strip_all_tags( (string) $body ) ) && empty( $img_ids ) ) {
					continue;
				}
				?>
				<section class="sj-section rv">
					<?php if ( '' !== trim( (string) $heading ) ) : ?>
						<h2 class="sj-section-title"><?php echo esc_html( $heading ); ?></h2>
					<?php endif; ?>

					<?php if ( '' !== trim( wp_strip_all_tags( (string) $body ) ) ) : ?>
						<div class="sj-section-body"><?php echo wp_kses_post( bankofart_enlarge_content_images( $body ) ); ?></div>
					<?php endif; ?>

					<?php if ( ! empty( $img_ids ) ) : ?>
						<div class="sj-section-figs">
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
										'class'   => 'sj-section-fig',
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

		<?php if ( ! empty( $author ) ) : ?>
			<p class="sj-author-foot"><?php echo esc_html( $byline_label ); ?> ／ <?php echo esc_html( $author ); ?></p>
		<?php endif; ?>
	</article>

	<!-- ════════ 関連アーティスト ════════ -->
	<?php if ( $show_related_artist ) : ?>
		<section class="sj-related">
			<div class="sj-related-head">
				<div class="sj-related-en rv">RELATED ARTIST</div>
				<div class="sj-related-ja rv d1">関連アーティスト</div>
			</div>
			<div class="artist-grid">
				<?php
				foreach ( $bottom_artists as $artist ) :
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
		<section class="sj-related">
			<div class="sj-related-head">
				<div class="sj-related-en rv">RELATED ART</div>
				<div class="sj-related-ja rv d1">関連作品</div>
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

	<!-- ════════ MORE JOURNAL ════════ -->
	<?php if ( $show_more ) : ?>
		<section class="sj-more">
			<div class="sj-related-head">
				<div class="sj-related-en rv">MORE JOURNAL</div>
				<div class="sj-related-ja rv d1">関連記事</div>
			</div>
			<div class="news-list">
				<?php
				foreach ( $more_journal as $mj ) :
					get_template_part(
						'template-parts/cards/card-journal',
						null,
						array(
							'journal_id' => $mj->ID,
						)
					);
				endforeach;
				?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ BACK LINK ════════ -->
	<div class="sj-back">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'journal' ) ); ?>">JOURNAL一覧へ戻る</a>
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
