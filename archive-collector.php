<?php
/**
 * COLLECTOR アーカイブ（archive-collector）
 *
 * mockups/collector.html を正として移植。Issue（課題）1軸の絞り込み（JS）。
 * 全件取得（posts_per_page = -1）してクライアント側で絞り込む。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Issue（課題）のターム（フィルターボタン用）。
$issue_terms = get_terms(
	array(
		'taxonomy'   => 'collector_issue',
		'hide_empty' => false,
		'orderby'    => 'term_id',
	)
);
$issue_terms = is_wp_error( $issue_terms ) ? array() : $issue_terms;

// 業種（collector_industry）のターム（プルダウン用）。投稿0件のタームは除外して
// 実在する業種だけを選択肢に出す。
$industry_terms = get_terms(
	array(
		'taxonomy'   => 'collector_industry',
		'hide_empty' => true,
		'orderby'    => 'name',
	)
);
$industry_terms = is_wp_error( $industry_terms ) ? array() : $industry_terms;

// 企業を全件取得。
$collectors_q = new WP_Query(
	array(
		'post_type'      => 'collector',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
		'no_found_rows'  => true,
	)
);

// 課題逆引き診断ページ（Phase 2-E 実装済み・page-matching-issue.php / スラッグ matching-issue）。
$matching_url = home_url( '/matching-issue/' );
?>

<main id="main" class="archive-collector">

	<section class="page-hero">
		<h1 class="page-hero-title rv">COLLECTOR</h1>
		<p class="page-hero-ja rv d1">画家応援企業</p>
		<div class="page-statement rv d2">
			<p><?php echo esc_html__( '「アートを迎える」という選択には、それぞれの想いや物語がある。', 'bankofart' ); ?></p>
			<p class="mid"><?php echo esc_html__( 'なぜ若手画家を応援しようと思ったのか。', 'bankofart' ); ?><br><?php echo esc_html__( 'なぜ“アートを飾る”という選択をしたのか。', 'bankofart' ); ?></p>
			<p><?php echo esc_html__( '作品との出会いは空間だけでなく、', 'bankofart' ); ?><br><?php echo esc_html__( '人や会話、働く空気までも少しずつ変えていく。', 'bankofart' ); ?></p>
		</div>
	</section>

	<!-- Issue Matching バナー（課題診断。いったん非公開＝bankofart_issue_matching_enabled）-->
	<?php if ( bankofart_issue_matching_enabled() ) : ?>
	<section class="match-banner-sec">
		<div class="match-banner rv">
			<div class="match-banner-left">
				<span class="match-banner-label">Issue Matching</span>
				<h2 class="match-banner-title">企業課題 <span class="accent">×</span> アート</h2>
				<p class="match-banner-sub">その課題、<br class="br-sp">アートで解決するかも。</p>
				<p class="match-banner-body"><span class="boa-num">3</span>つの質問にお答えいただくだけで、御社の課題に合ったアートをご提案します。</p>
			</div>
			<div class="match-banner-right">
				<a href="<?php echo esc_url( $matching_url ); ?>" class="match-banner-btn">課題から探す</a>
				<span class="match-banner-meta"><span class="boa-num">3</span>QUESTIONS &nbsp;/&nbsp; <span class="boa-num">2</span>MIN</span>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="collector-section">
		<div class="section-head">
			<div class="section-head-en rv">COLLECTORS</div>
			<div class="section-head-ja rv d1">画家応援企業の声</div>
		</div>

		<div class="filter-section rv">
			<!-- モバイル用フィルタートグル（PCでは CSS で非表示） -->
			<button type="button" class="filter-toggle" id="filterToggle" aria-expanded="false" aria-controls="collectorFilterInner">
				<span>詳細から探す</span>
				<span class="filter-toggle-icon" aria-hidden="true"></span>
			</button>
			<div class="filter-inner" id="collectorFilterInner">
				<div class="filter-row">
					<span class="filter-label">Issue</span>
					<div class="filter-tags">
						<button type="button" class="filter-tag is-active" data-axis="issue" data-filter="all">すべて</button>
						<?php foreach ( $issue_terms as $term ) : ?>
							<button type="button" class="filter-tag" data-axis="issue" data-filter="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>

				<?php if ( ! empty( $industry_terms ) ) : ?>
					<div class="filter-row">
						<span class="filter-label">Industry</span>
						<div class="filter-tags">
							<button type="button" class="filter-tag is-active" data-axis="industry" data-filter="all">すべて</button>
							<?php foreach ( $industry_terms as $term ) : ?>
								<button type="button" class="filter-tag" data-axis="industry" data-filter="<?php echo esc_attr( $term->term_id ); ?>"><?php echo esc_html( $term->name ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="filter-result">
					<span class="filter-count"><span class="boa-num"><?php echo (int) $collectors_q->post_count; ?></span>COLLECTORS</span>
				</div>
			</div>
		</div>

		<?php if ( $collectors_q->have_posts() ) : ?>
			<div class="collector-grid" id="collectorGrid">
				<?php
				while ( $collectors_q->have_posts() ) :
					$collectors_q->the_post();
					get_template_part(
						'template-parts/cards/card-collector',
						null,
						array( 'collector_id' => get_the_ID() )
					);
				endwhile;
				wp_reset_postdata();
				?>
			</div>

			<!-- ページ番号式ページャー（JSが matched に応じて生成） -->
			<nav class="archive-pager" id="collectorPager" aria-label="<?php echo esc_attr__( 'ページ送り', 'bankofart' ); ?>"></nav>
		<?php else : ?>
			<div class="news-empty">
				<p><?php echo esc_html__( '画家応援企業がまだ登録されていません。', 'bankofart' ); ?></p>
			</div>
		<?php endif; ?>
	</section>

	<!-- CONTACT -->
	<?php get_template_part( 'template-parts/sections/section-cta' ); ?>

</main>

<?php
get_footer();
