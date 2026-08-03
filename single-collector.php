<?php
/**
 * 単一 画家応援企業（single-collector）テンプレート
 *
 * mockups/collector-single.html の構造を移植し、値を Meta Box /
 * タクソノミー / Relationships から動的取得する。各セクションは
 * bankofart_should_show_section() で二段階チェックする。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$cid = get_the_ID();

	// ---- 基本 ----
	$title         = get_the_title();
	$impl_raw      = rwmb_meta( 'collector_implementation_date' );
	$change        = rwmb_meta( 'collector_change_summary' );

	// ---- 画像 ----
	$main_office = bankofart_get_image( 'collector_main_office_image', $cid, 'large' );
	$office_imgs = array_filter(
		(array) rwmb_meta( 'collector_office_images', array( 'size' => 'large' ) ),
		static function ( $g ) {
			return ! empty( $g['url'] );
		}
	);
	$entrance = bankofart_get_image( 'collector_entrance_image', $cid, 'large' );
	$iv1      = bankofart_get_image( 'collector_interview_image_1', $cid, 'large' );

	// インタビューの質問アバター（BOAキャラ）。モック準拠でブランド円に重ねる。
	$interview_avatar = get_theme_file_uri( 'assets/img/logo/char-01.png' );
	$iv2      = bankofart_get_image( 'collector_interview_image_2', $cid, 'large' );
	$iv3      = bankofart_get_image( 'collector_interview_image_3', $cid, 'large' );

	// ---- インタビュー回答 ----
	$q1 = rwmb_meta( 'collector_q1_values' );
	$q2 = rwmb_meta( 'collector_q2_motivation' );
	$q3 = rwmb_meta( 'collector_q3_choice' );
	$q4 = rwmb_meta( 'collector_q4_changes' );
	$q5 = rwmb_meta( 'collector_q5_message' );

	// ---- タクソノミー ----
	$tax_join = static function ( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return implode( ' / ', wp_list_pluck( $terms, 'name' ) );
	};
	$issue_first   = bankofart_get_first_term_name( $cid, 'collector_issue' );
	$issue_label   = $tax_join( $cid, 'collector_issue' );
	$industry_term = bankofart_get_first_term_name( $cid, 'collector_industry' );
	$place_label   = $tax_join( $cid, 'collector_placement' );

	// ---- 所有作品（art_to_owner 逆引き） ----
	$owned_arts = bankofart_get_connected( 'art_to_owner', 'to', $cid );

	// Q3 用の作品画像（先頭の所有作品メイン画像）。
	$q3_img = array(
		'url' => '',
		'alt' => '',
	);
	if ( ! empty( $owned_arts ) ) {
		$q3_img = bankofart_get_image( 'art_main_image', $owned_arts[0]->ID, 'large' );
	}

	// ---- 同じ課題の他企業 ----
	$same_collectors = array();
	$issue_terms     = get_the_terms( $cid, 'collector_issue' );
	if ( ! is_wp_error( $issue_terms ) && ! empty( $issue_terms ) ) {
		$issue_ids = wp_list_pluck( $issue_terms, 'term_id' );
		$same_q    = new WP_Query(
			array(
				'post_type'      => 'collector',
				'post_status'    => 'publish',
				'post__not_in'   => array( $cid ),
				'posts_per_page' => 3,
				'no_found_rows'  => true,
				'tax_query'      => array(
					array(
						'taxonomy' => 'collector_issue',
						'field'    => 'term_id',
						'terms'    => $issue_ids,
						'operator' => 'IN',
					),
				),
			)
		);
		$same_collectors = $same_q->posts;
		wp_reset_postdata();
	}

	/*
	 * ---- インタビューQ&A（回答あり = 表示。写真は未入力なら枠を出さない）----
	 * 「インタビュー（自由記述）」リピーターに1行でも入っていればそちらを本文に使う。
	 * 空のときだけ、従来の定番5問（Q1〜Q5）にフォールバックする。
	 */
	$qa_free   = array_filter( (array) rwmb_meta( 'collector_interview_qa' ) );
	$qa_blocks = array();

	foreach ( $qa_free as $row ) {
		$answer = isset( $row['qa_answer'] ) ? (string) $row['qa_answer'] : '';
		if ( '' === trim( wp_strip_all_tags( $answer ) ) ) {
			continue; // 回答が空の行は出さない。
		}

		$img = array(
			'url' => '',
			'alt' => '',
		);
		if ( ! empty( $row['qa_image'] ) ) {
			$att = wp_get_attachment_image_src( (int) $row['qa_image'], 'large' );
			if ( $att ) {
				$img['url'] = $att[0];
				$img['alt'] = get_post_meta( (int) $row['qa_image'], '_wp_attachment_image_alt', true );
			}
		}

		$qa_blocks[] = array(
			'q'   => isset( $row['qa_question'] ) ? (string) $row['qa_question'] : '',
			'a'   => $answer,
			'img' => $img,
		);
	}

	// 自由記述が空なら従来の定番5問。
	if ( empty( $qa_blocks ) ) :
	$qa_blocks = array(
		array(
			'q'   => '御社が大切にされている理念や価値観について教えてください。',
			'a'   => $q1,
			'img' => $entrance,
		),
		array(
			'q'   => 'アートを導入したきっかけを教えてください。',
			'a'   => $q2,
			'img' => $iv1,
		),
		array(
			'q'   => '作品・作家を選んだ決め手は何でしたか？',
			'a'   => $q3,
			'img' => $q3_img,
		),
		array(
			'q'   => '作品を飾ってから、どんな変化がありましたか？',
			'a'   => $q4,
			'img' => $iv2,
		),
		array(
			'q'   => '導入を検討している企業へメッセージをお願いします。',
			'a'   => $q5,
			'img' => $iv3,
		),
	);
	endif;

	$has_any_answer = false;
	foreach ( $qa_blocks as $blk ) {
		if ( '' !== trim( wp_strip_all_tags( (string) $blk['a'] ) ) ) {
			$has_any_answer = true;
			break;
		}
	}

	// ---- プロフィール表の行（データのある行のみ）----
	$impl_label = '';
	if ( ! empty( $impl_raw ) ) {
		$ts = strtotime( $impl_raw );
		if ( $ts ) {
			$impl_label = date_i18n( 'Y年n月', $ts );
		}
	}
	$profile_rows = array();
	$industry_disp = $industry_term; // 業界は「業種」タクソノミーに一本化。
	if ( ! empty( $industry_disp ) ) {
		$profile_rows[] = array( '業界', $industry_disp );
	}
	if ( ! empty( $issue_label ) ) {
		$profile_rows[] = array( '企業課題', $issue_label );
	}
	$place_disp = $place_label; // 設置場所は「設置場所」タクソノミーに一本化。
	if ( ! empty( $place_disp ) ) {
		$profile_rows[] = array( '設置場所', $place_disp );
	}
	if ( ! empty( $impl_label ) ) {
		$profile_rows[] = array( '導入時期', $impl_label );
	}

	// ---- セクション可視性 ----
	$show_interview = bankofart_should_show_section( 'collector_show_interview', $has_any_answer, $cid );
	$show_work      = bankofart_should_show_section( 'collector_show_introduced_work', $owned_arts, $cid );
	$show_same      = bankofart_should_show_section( 'collector_show_same_issue', $same_collectors, $cid );
	$show_matching  = bankofart_should_show_section( 'collector_show_matching', true, $cid );
	$show_cta       = bankofart_should_show_section( 'collector_show_cta', true, $cid );
	?>

<main id="main" class="single-collector">

	<nav class="breadcrumb" aria-label="breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">HOME</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'collector' ) ); ?>">COLLECTOR</a>
		<span class="sep">/</span>
		<span><?php echo esc_html( $title ); ?></span>
	</nav>

	<!-- ════════ HERO ════════ -->
	<section class="cs-hero">
		<?php if ( ! empty( $issue_first ) ) : ?>
			<span class="cs-hero-tag rv"><?php echo esc_html( $issue_first ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $industry_disp ) ) : ?>
			<p class="cs-hero-industry rv d1"><?php echo esc_html( $industry_disp ); ?></p>
		<?php endif; ?>
		<h1 class="cs-hero-name rv d1"><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $change ) ) : ?>
			<p class="cs-hero-lead rv d2"><?php echo esc_html( $change ); ?></p>
		<?php endif; ?>
	</section>

	<!-- ════════ HERO VISUAL ════════ -->
	<?php if ( ! empty( $main_office['url'] ) || ! empty( $office_imgs ) ) : ?>
		<div class="cs-visual rv">
			<div class="cs-visual-inner">
				<span
					class="cs-visual-img"
					id="csVisualMain"
					<?php if ( ! empty( $main_office['url'] ) ) : ?>
						style="background-image:url('<?php echo esc_url( $main_office['url'] ); ?>');"
						role="img" aria-label="<?php echo esc_attr( $main_office['alt'] ? $main_office['alt'] : $title ); ?>"
					<?php endif; ?>
				></span>
			</div>
			<?php if ( ! empty( $office_imgs ) ) : ?>
				<div class="cs-visual-thumbs">
					<?php
					$vi = 0;
					foreach ( $office_imgs as $g ) :
						$active = ( 0 === $vi ) ? ' is-active' : '';
						?>
						<button type="button" class="cs-visual-thumb<?php echo esc_attr( $active ); ?>" data-bg="<?php echo esc_url( $g['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( '画像%d', $vi + 1 ) ); ?>">
							<span class="cs-visual-thumb-inner" style="background-image:url('<?php echo esc_url( $g['url'] ); ?>');"></span>
						</button>
						<?php
						++$vi;
					endforeach;
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- ════════ INTERVIEW ════════ -->
	<?php if ( $show_interview ) : ?>
		<section class="cs-interview-sec">
			<div class="cs-interview">
				<div class="cs-iv-head">
					<div class="cs-iv-head-en rv">INTERVIEW</div>
					<div class="cs-iv-head-ja rv d1">導入企業の声</div>
				</div>

				<?php
				foreach ( $qa_blocks as $blk ) :
					if ( '' === trim( wp_strip_all_tags( (string) $blk['a'] ) ) ) {
						continue; // 回答が空のQはブロックごとスキップ.
					}
					?>
					<div class="cs-iv-block rv">
						<div class="cs-iv-q">
							<span class="cs-iv-q-avatar" aria-hidden="true"><img src="<?php echo esc_url( $interview_avatar ); ?>" alt=""></span>
							<span class="cs-iv-q-bubble"><?php echo esc_html( $blk['q'] ); ?></span>
						</div>
						<div class="cs-iv-a"><?php echo wp_kses_post( $blk['a'] ); ?></div>
						<?php if ( ! empty( $blk['img']['url'] ) ) : ?>
							<div class="cs-iv-photo">
								<span class="cs-iv-photo-inner" style="background-image:url('<?php echo esc_url( $blk['img']['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( $blk['img']['alt'] ? $blk['img']['alt'] : $title ); ?>"></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<?php if ( ! empty( $profile_rows ) ) : ?>
					<div class="cs-profile">
						<?php foreach ( $profile_rows as $row ) : ?>
							<div class="cs-profile-row">
								<div class="cs-profile-key"><?php echo esc_html( $row[0] ); ?></div>
								<div class="cs-profile-val"><?php echo esc_html( $row[1] ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ INTRODUCED WORK ════════ -->
	<?php if ( $show_work ) : ?>
		<section class="cs-work-sec">
			<div class="cs-work-head">
				<div class="cs-work-en rv">INTRODUCED WORK</div>
				<div class="cs-work-ja rv d1">この企業が迎えた作品</div>
			</div>
			<?php
			foreach ( $owned_arts as $art ) :
				$art_id      = $art->ID;
				$art_img     = bankofart_get_image( 'art_main_image', $art_id, 'large' );
				$art_year    = rwmb_meta( 'art_year', '', $art_id );
				$art_medium  = rwmb_meta( 'art_medium', '', $art_id );
				$art_size    = rwmb_meta( 'art_size_label', '', $art_id );
				$art_artists = bankofart_get_connected( 'artist_to_art', 'to', $art_id );
				$art_artist  = ! empty( $art_artists ) ? get_the_title( $art_artists[0]->ID ) : '';
				$meta_parts  = array_filter( array( $art_size, $art_medium, $art_year ? $art_year . '年' : '' ) );
				?>
				<div class="cs-work-card rv">
					<div class="cs-work-image">
						<span class="cs-work-image-inner"<?php if ( ! empty( $art_img['url'] ) ) : ?> style="background-image:url('<?php echo esc_url( $art_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( get_the_title( $art_id ) ); ?>"<?php endif; ?>></span>
					</div>
					<div class="cs-work-info">
						<?php if ( ! empty( $art_artist ) ) : ?>
							<p class="cs-work-artist"><?php echo esc_html( $art_artist ); ?></p>
						<?php endif; ?>
						<h3 class="cs-work-title"><?php echo esc_html( get_the_title( $art_id ) ); ?></h3>
						<?php if ( ! empty( $meta_parts ) ) : ?>
							<p class="cs-work-meta"><?php echo esc_html( implode( ' / ', $meta_parts ) ); ?></p>
						<?php endif; ?>
						<a href="<?php echo esc_url( get_permalink( $art_id ) ); ?>" class="cs-work-link">作品を見る</a>
					</div>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>

	<!-- ════════ SAME ISSUE ════════ -->
	<?php if ( $show_same ) : ?>
		<section class="cs-related">
			<div class="cs-related-head">
				<div class="cs-related-en rv">SAME ISSUE</div>
				<div class="cs-related-ja rv d1">同じ課題に取り組む企業</div>
			</div>
			<div class="cs-related-grid">
				<?php
				foreach ( $same_collectors as $rc ) :
					$rc_id       = $rc->ID;
					$rc_img      = bankofart_get_image( 'collector_main_office_image', $rc_id, 'large' );
					$rc_issue    = bankofart_get_first_term_name( $rc_id, 'collector_issue' );
					$rc_ind      = bankofart_get_first_term_name( $rc_id, 'collector_industry' );
					$rc_effect   = rwmb_meta( 'collector_change_summary', '', $rc_id );
					?>
					<a href="<?php echo esc_url( get_permalink( $rc_id ) ); ?>" class="cs-related-card rv">
						<div class="cs-related-image">
							<?php if ( ! empty( $rc_issue ) ) : ?>
								<span class="cs-related-tag"><?php echo esc_html( $rc_issue ); ?></span>
							<?php endif; ?>
							<span class="cs-related-image-inner"<?php if ( ! empty( $rc_img['url'] ) ) : ?> style="background-image:url('<?php echo esc_url( $rc_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( get_the_title( $rc_id ) ); ?>"<?php endif; ?>></span>
						</div>
						<?php if ( ! empty( $rc_ind ) ) : ?>
							<div class="cs-related-industry"><?php echo esc_html( $rc_ind ); ?></div>
						<?php endif; ?>
						<h3 class="cs-related-name"><?php echo esc_html( get_the_title( $rc_id ) ); ?></h3>
						<?php if ( ! empty( $rc_effect ) ) : ?>
							<p class="cs-related-effect"><?php echo esc_html( $rc_effect ); ?></p>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ MATCHING BANNER（Issue Matching）════════ -->
	<?php if ( $show_matching ) : ?>
		<section class="match-banner-sec">
			<div class="match-banner rv">
				<div class="match-banner-left">
					<span class="match-banner-label">Issue Matching</span>
					<h2 class="match-banner-title">企業課題 <span class="accent">×</span> アート</h2>
					<p class="match-banner-sub">その課題、アートで解決するかも。</p>
					<p class="match-banner-body"><span class="boa-num">3</span>つの質問にお答えいただくだけで、御社の課題に合ったアートをご提案します。</p>
				</div>
				<div class="match-banner-right">
					<a href="<?php echo esc_url( home_url( '/matching-issue/' ) ); ?>" class="match-banner-btn">課題から探す</a>
					<span class="match-banner-meta"><span class="boa-num">3</span>QUESTIONS &nbsp;/&nbsp; <span class="boa-num">2</span>MIN</span>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ BACK LINK ════════ -->
	<div class="cs-back">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'collector' ) ); ?>">画家応援企業一覧へ戻る</a>
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
