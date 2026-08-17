<?php
/**
 * 単一アーティスト（single-artist）テンプレート
 *
 * mockups/artist-single.html の構造を移植し、ハードコード値を Meta Box /
 * タクソノミー / Relationships から動的取得する。各セクションは
 * bankofart_should_show_section() による二段階チェックで表示制御する。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$artist_id = get_the_ID();

	// ---- 取得（基本） ----
	$name        = get_the_title();
	$name_en     = rwmb_meta( 'artist_name_en' );
	$catch       = rwmb_meta( 'artist_catch_phrase' );
	$hero_image  = bankofart_get_image( 'artist_main_photo', $artist_id, 'large' );
	$gallery     = (array) rwmb_meta( 'artist_gallery_photos', array( 'size' => 'large' ) );
	$video_url   = rwmb_meta( 'artist_video_url' );

	// ---- 取得（プロフィール） ----
	$theme_long  = rwmb_meta( 'artist_theme_long' );
	$theme_kw    = rwmb_meta( 'artist_theme_keywords' );
	$reason      = rwmb_meta( 'artist_reason' );
	$origin      = rwmb_meta( 'artist_origin_story' );
	$goal        = rwmb_meta( 'artist_goal' );
	$resonance   = rwmb_meta( 'artist_resonance_message' );
	$work_imgs   = (array) rwmb_meta( 'artist_working_photos', array( 'size' => 'large' ) ); // image_advanced（配列）.
	$symbol_img  = bankofart_get_image( 'artist_symbol_image', $artist_id, 'large' );

	// ---- 取得（経歴・テキスト） ----
	// 学歴は wysiwyg、その他は textarea。bankofart_value_to_lines() で同じ行配列に揃え、
	// 展示（個展・グループ展）と同一の .as-record-list 形式で表示する。
	$education = rwmb_meta( 'artist_education' );
	$solo      = rwmb_meta( 'artist_solo_exhibitions' );
	$group     = rwmb_meta( 'artist_group_exhibitions' );
	$awards    = rwmb_meta( 'artist_media_awards' );
	$others    = rwmb_meta( 'artist_other_activities' );

	// ---- 取得（タクソノミー） ----
	$diag_terms = get_the_terms( $artist_id, 'artist_diagnosis_tag' );
	$diag_terms = ( ! is_wp_error( $diag_terms ) && $diag_terms ) ? $diag_terms : array();

	// ---- 取得（リレーション） ----
	$works = bankofart_get_connected( 'artist_to_art', 'from', $artist_id );

	$rel_news    = bankofart_get_connected( 'news_to_artist', 'to', $artist_id );
	$rel_journal = bankofart_get_connected( 'journal_to_artist', 'to', $artist_id );
	$articles    = array_merge( $rel_news, $rel_journal );
	usort(
		$articles,
		static function ( $a, $b ) {
			return strtotime( $b->post_date ) <=> strtotime( $a->post_date );
		}
	);
	$articles = array_slice( $articles, 0, 6 );

	// ---- SNS（未設定は出力しない） ----
	$sns = array(
		'instagram' => array(
			'url'  => rwmb_meta( 'artist_sns_instagram' ),
			'name' => 'Instagram',
			'path' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 01-1.38-.9 3.7 3.7 0 01-.9-1.38c-.16-.42-.36-1.06-.41-2.23-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16zM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.38C1.36 2.67.94 3.34.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.31.8.72 1.47 1.38 2.13.66.66 1.33 1.07 2.12 1.38.76.3 1.64.5 2.91.56C8.33 23.99 8.74 24 12 24s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.8-.31 1.47-.72 2.13-1.38.66-.66 1.07-1.33 1.38-2.13.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91a5.9 5.9 0 00-1.38-2.12A5.9 5.9 0 0019.86.63c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0zm0 5.84A6.16 6.16 0 105.84 12 6.16 6.16 0 0012 5.84zM12 16a4 4 0 114-4 4 4 0 01-4 4zm6.4-10.4a1.44 1.44 0 11-1.44-1.44 1.44 1.44 0 011.44 1.44z',
		),
		'x'         => array(
			'url'  => rwmb_meta( 'artist_sns_x' ),
			'name' => 'X',
			'path' => 'M18.9 1.5h3.68l-8.04 9.19L24 22.5h-7.4l-5.8-7.58-6.64 7.58H.48l8.6-9.83L0 1.5h7.59l5.24 6.93zM17.6 20.3h2.04L6.49 3.6H4.3z',
		),
		'facebook'  => array(
			'url'  => rwmb_meta( 'artist_sns_facebook' ),
			'name' => 'Facebook',
			'path' => 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07c0 6.02 4.39 11.01 10.13 11.93v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.69.24 2.69.24v2.97h-1.52c-1.49 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8v8.44C19.61 23.08 24 18.09 24 12.07z',
		),
		'youtube'   => array(
			'url'  => rwmb_meta( 'artist_sns_youtube' ),
			'name' => 'YouTube',
			'path' => 'M23.5 6.2a3 3 0 00-2.11-2.12C19.5 3.56 12 3.56 12 3.56s-7.5 0-9.39.52A3 3 0 00.5 6.2 31.3 31.3 0 000 12a31.3 31.3 0 00.5 5.8 3 3 0 002.11 2.12c1.89.52 9.39.52 9.39.52s7.5 0 9.39-.52a3 3 0 002.11-2.12A31.3 31.3 0 0024 12a31.3 31.3 0 00-.5-5.8zM9.6 15.6V8.4l6.2 3.6z',
		),
		'other'     => array(
			'url'  => rwmb_meta( 'artist_sns_other' ),
			'name' => 'Website',
			'path' => 'M12 0a12 12 0 100 24 12 12 0 000-24zm6.93 6h-2.95a15.7 15.7 0 00-1.38-3.56A8.03 8.03 0 0118.93 6zM12 2.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM2.26 14a7.96 7.96 0 010-4h3.38a16.6 16.6 0 000 4zm.81 2h2.95c.3 1.26.76 2.46 1.38 3.56A8.03 8.03 0 013.07 16zm2.95-8H3.07a8.03 8.03 0 014.33-3.56A15.7 15.7 0 005.64 8zM12 21.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82A13.7 13.7 0 0112 21.96zM14.34 16H9.66a14.8 14.8 0 010-4h4.68a14.8 14.8 0 010 4zm.32 3.56c.62-1.1 1.08-2.3 1.38-3.56h2.95a8.03 8.03 0 01-4.33 3.56zM18.36 14a16.6 16.6 0 000-4h3.38a7.96 7.96 0 010 4z',
		),
	);

	// ---- セクション可視性（二段階チェック） ----
	$show_theme   = bankofart_should_show_section( 'artist_show_theme', $theme_long, $artist_id );
	$show_phil    = bankofart_should_show_section( 'artist_show_philosophy', $reason, $artist_id );
	$show_history = bankofart_should_show_section( 'artist_show_origin_story', $origin, $artist_id );
	$show_goal    = bankofart_should_show_section( 'artist_show_goal', $goal, $artist_id );
	$show_works   = bankofart_should_show_section( 'artist_show_works', $works, $artist_id );
	$show_article = bankofart_should_show_section( 'artist_show_articles', $articles, $artist_id );
	// 共鳴文はバナーに表示しない設計のため、switch のみで判定（データ有無は問わない）。
	$show_match   = bankofart_should_show_section( 'artist_show_matching', true, $artist_id );
	$show_cta     = bankofart_should_show_section( 'artist_show_cta', true, $artist_id );

	// ---- YouTube 埋め込みURL ----
	$video_embed = '';
	if ( ! empty( $video_url ) ) {
		if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/))([\w-]{11})~', $video_url, $m ) ) {
			$video_embed = 'https://www.youtube.com/embed/' . $m[1];
		} else {
			$video_embed = $video_url;
		}
	}
	?>

<main id="main" class="single-artist">

	<nav class="breadcrumb" aria-label="breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">HOME</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'artist' ) ); ?>">ARTIST</a>
		<span class="sep">/</span>
		<span><?php echo esc_html( $name_en ? $name_en : $name ); ?></span>
	</nav>

	<!-- ════════ HERO ════════ -->
	<section class="as-hero">
		<div class="as-hero-gallery rv">
			<div class="as-hero-visual boa-zoomable">
				<span
					class="as-hero-visual-inner"
					id="asHeroMain"
					<?php if ( ! empty( $hero_image['url'] ) ) : ?>
						style="background-image:url('<?php echo esc_url( $hero_image['url'] ); ?>');"
						role="img" aria-label="<?php echo esc_attr( $hero_image['alt'] ? $hero_image['alt'] : $name ); ?>"
					<?php endif; ?>
				></span>
			</div>

			<?php if ( ! empty( $gallery ) ) : ?>
				<div class="as-hero-thumbs">
					<?php
					$thumb_index = 0;
					foreach ( $gallery as $g ) :
						if ( empty( $g['url'] ) ) {
							continue;
						}
						$is_active = ( 0 === $thumb_index ) ? ' is-active' : '';
						?>
						<button type="button" class="as-hero-thumb<?php echo esc_attr( $is_active ); ?>" data-bg="<?php echo esc_url( $g['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( '画像%d', $thumb_index + 1 ) ); ?>">
							<span class="as-hero-thumb-inner" style="background-image:url('<?php echo esc_url( $g['url'] ); ?>');"></span>
						</button>
						<?php
						++$thumb_index;
					endforeach;
					?>
				</div>
			<?php endif; ?>
		</div>

		<div class="as-hero-text rv d1">
			<?php if ( ! empty( $name_en ) ) : ?>
				<p class="as-hero-en"><?php echo esc_html( $name_en ); ?></p>
			<?php endif; ?>

			<h1 class="as-hero-name-ja"><?php echo esc_html( $name ); ?></h1>

			<?php if ( ! empty( $catch ) ) : ?>
				<p class="as-hero-catch"><?php echo esc_html( $catch ); ?></p>
			<?php endif; ?>

			<?php
			$sns_links = array_filter(
				$sns,
				static function ( $s ) {
					return ! empty( $s['url'] );
				}
			);
			if ( ! empty( $sns_links ) ) :
				?>
				<div class="as-hero-sns">
					<?php foreach ( $sns_links as $s ) : ?>
						<a href="<?php echo esc_url( $s['url'] ); ?>" class="as-sns-a" target="_blank" rel="noopener" title="<?php echo esc_attr( $s['name'] ); ?>" aria-label="<?php echo esc_attr( $s['name'] ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?php echo esc_attr( $s['path'] ); ?>"/></svg>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $diag_terms ) ) : ?>
				<div class="as-hero-tags">
					<?php foreach ( $diag_terms as $term ) : ?>
						<span class="as-tag"><?php echo esc_html( $term->name ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php
			// 学歴 → 個展 → グループ展 → メディア・受賞 → その他活動 の順に、
			// bankofart_parse_record_lines() で「年 / 月 / 内容」へ分解して統一表記で描画する。
			// 入力側の書き方（年が見出し行・箇条書き記号・年月の区切り）の揺れはここで吸収する。
			$record_groups = array();
			foreach (
				array(
					array( '学歴・経歴', 'Education', $education ),
					array( '個展', 'Solo Exhibitions', $solo ),
					array( 'グループ展', 'Group Exhibitions', $group ),
					array( 'メディア・受賞', 'Awards & Media', $awards ),
					array( 'その他活動', 'Other Activities', $others ),
				) as $rg_def
			) {
				$rg_entries = bankofart_parse_record_lines( $rg_def[2] );
				if ( ! empty( $rg_entries ) ) {
					$record_groups[] = array(
						'ja'      => $rg_def[0],
						'en'      => $rg_def[1],
						'entries'  => $rg_entries,
						// 1件でも年／月があるグループだけ列を確保する（無い場合に無駄な余白を作らない）。
						'has_year'  => (bool) array_filter( wp_list_pluck( $rg_entries, 'year_raw' ) ),
						'has_month' => (bool) array_filter( wp_list_pluck( $rg_entries, 'month' ) ),
					);
				}
			}

			if ( ! empty( $record_groups ) ) :
				?>
				<div class="as-record">
					<?php foreach ( $record_groups as $rg ) : ?>
						<div class="as-record-group">
							<div class="as-record-label">
								<span class="ja"><?php echo esc_html( $rg['ja'] ); ?></span>
								<span class="en"><?php echo esc_html( $rg['en'] ); ?></span>
							</div>
							<?php
							// has-year / has-month の有無でグリッドの列数を合わせる（CSS側と対応）。
							$list_class = 'as-record-list';
							$list_class .= $rg['has_year'] ? ' has-year' : '';
							$list_class .= ( $rg['has_year'] && $rg['has_month'] ) ? ' has-month' : '';
						?>
						<ul class="<?php echo esc_attr( $list_class ); ?>">
								<?php foreach ( $rg['entries'] as $entry ) : ?>
									<li>
										<?php if ( $rg['has_year'] ) : ?>
											<span class="yr boa-num"><?php echo esc_html( $entry['year'] ); ?></span>
										<?php endif; ?>
										<?php if ( $rg['has_year'] && $rg['has_month'] ) : ?>
											<span class="mo boa-num"><?php echo esc_html( $entry['month'] ); ?></span>
										<?php endif; ?>
										<span class="tx"><?php echo esc_html( $entry['text'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- ════════ MOVIE ════════ -->
	<?php if ( ! empty( $video_embed ) ) : ?>
		<section class="as-video-sec">
			<div class="as-video-inner">
				<div class="as-video-head">
					<div class="as-video-en rv">MOVIE</div>
					<div class="as-video-ja rv d1"><?php echo esc_html( sprintf( '%s を動画で知る', $name ) ); ?></div>
				</div>
				<div class="as-video-frame rv">
					<iframe src="<?php echo esc_url( $video_embed ); ?>" title="<?php echo esc_attr( sprintf( '%s — Artist Movie', $name ) ); ?>" loading="lazy" allowfullscreen></iframe>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ PROFILE（Theme / Philosophy / History）+ GOAL ════════ -->
	<?php if ( $show_theme || $show_phil || $show_history || $show_goal ) : ?>
		<section class="as-section">
			<?php if ( $show_theme || $show_phil || $show_history ) : ?>
				<div class="as-profile">

					<?php if ( $show_theme ) : ?>
						<div class="as-block rv">
							<div class="as-block-label">Theme</div>
							<h2 class="as-block-title">制作テーマ</h2>
							<p class="as-block-lead"><?php echo nl2br( esc_html( $theme_long ) ); ?></p>

							<?php
							$kw_list = array_filter( array_map( 'trim', explode( ',', (string) $theme_kw ) ) );
							if ( ! empty( $kw_list ) ) :
								?>
								<div class="as-kw-list">
									<?php foreach ( $kw_list as $kw ) : ?>
										<span class="as-kw"># <?php echo esc_html( $kw ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php
							$work_img = ( ! empty( $work_imgs ) && ! empty( $work_imgs[0]['url'] ) ) ? $work_imgs[0] : null;
							if ( $work_img ) :
								?>
								<div class="as-process-photo boa-zoomable">
									<span class="as-process-photo-inner" style="background-image:url('<?php echo esc_url( $work_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( '制作風景' ); ?>"></span>
								</div>
								<p class="as-process-cap">制作風景 — Working Process</p>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_phil ) : ?>
						<div class="as-block rv" style="margin-top:72px;">
							<div class="as-block-label">Philosophy</div>
							<h2 class="as-block-title">なぜ描くか</h2>
							<div class="as-block-text"><?php echo wp_kses_post( $reason ); ?></div>

							<?php if ( ! empty( $symbol_img['url'] ) ) : ?>
								<div class="as-process-photo boa-zoomable">
									<span class="as-process-photo-inner" style="background-image:url('<?php echo esc_url( $symbol_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( $symbol_img['alt'] ? $symbol_img['alt'] : $name ); ?>"></span>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $show_history ) : ?>
						<div class="as-block rv" style="margin-top:72px;">
							<div class="as-block-label">History</div>
							<h2 class="as-block-title">起源の物語</h2>
							<div class="as-block-text"><?php echo wp_kses_post( $origin ); ?></div>
						</div>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<?php
			/*
			 * 制作風景写真の横スクロール（マーキー）。
			 * 「制作テーマ」ブロックでは1枚目しか使っていなかったため、ここで
			 * 2枚目以降も含めて全部流す。2枚以上あるときだけ動かす意味があるので、
			 * 1枚のときは静止表示（CSS側でアニメーションを止める）。
			 */
			$marquee_shots = array_values(
				array_filter(
					$work_imgs,
					static function ( $img ) {
						return ! empty( $img['url'] );
					}
				)
			);
			if ( ! empty( $marquee_shots ) ) :
				?>
				<div class="as-process-marquee rv" aria-label="制作風景">
					<div class="as-process-marquee-row<?php echo count( $marquee_shots ) < 2 ? ' is-static' : ''; ?>">
						<?php
						// 途切れないよう2周分出力する（CSSで -50% までスクロールさせる）。
						$loop = count( $marquee_shots ) < 2 ? $marquee_shots : array_merge( $marquee_shots, $marquee_shots );
						foreach ( $loop as $shot ) :
							?>
							<div class="as-process-shot boa-zoomable">
								<span class="as-process-shot-inner" style="background-image:url('<?php echo esc_url( $shot['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( sprintf( '%s の制作風景', $name ) ); ?>"></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $show_goal ) : ?>
				<div class="as-goal">
					<div class="as-goal-label">Goal</div>
					<p class="as-goal-text"><?php echo nl2br( esc_html( $goal ) ); ?></p>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<!-- ════════ WORKS ════════ -->
	<?php if ( $show_works ) : ?>
		<section class="as-works-sec">
			<div class="as-works">
				<div class="as-works-head">
					<div class="as-works-en rv">WORKS</div>
					<div class="as-works-ja rv d1"><?php echo esc_html( sprintf( '%s の作品', $name ) ); ?></div>
				</div>
				<div class="as-works-grid" id="asWorksGrid">
					<?php
					foreach ( $works as $work ) :
						get_template_part(
							'template-parts/cards/card-art',
							null,
							array(
								'art_id'  => $work->ID,
								'context' => 'related',
							)
						);
					endforeach;
					?>
				</div>

				<?php
				/*
				 * 作品数が多い作家（KATHMIは25点）だと全件表示ではスクロールが長すぎるため、
				 * 初期は3件（モバイル2件）だけ出し、残りは「もっと見る」で追加する。
				 * 制御は assets/js/single-detail.js。
				 */
				if ( count( $works ) > 3 ) :
					?>
					<div class="as-works-more" id="asWorksMore">
						<button type="button" class="archive-loadmore-btn" id="asWorksMoreBtn"><?php echo esc_html__( 'もっと見る', 'bankofart' ); ?></button>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ ARTICLE ════════ -->
	<?php if ( $show_article ) : ?>
		<section class="as-article-sec">
			<div class="as-article-inner">
				<div class="as-article-head">
					<div class="as-article-en rv">ARTICLE</div>
					<div class="as-article-ja rv d1"><?php echo esc_html( sprintf( '%s の記事', $name ) ); ?></div>
				</div>
				<div class="as-article-grid">
					<?php
					foreach ( $articles as $article ) :
						$a_id    = $article->ID;
						$a_type  = $article->post_type;
						$a_img   = bankofart_get_image( ( 'journal' === $a_type ) ? 'journal_main_image' : 'news_main_image', $a_id, 'large' );
						$a_cat   = bankofart_get_first_term_name( $a_id, ( 'journal' === $a_type ) ? 'journal_category' : 'news_category' );
						$a_date  = get_the_date( 'Y.m.d', $a_id );
						?>
						<a href="<?php echo esc_url( get_permalink( $a_id ) ); ?>" class="as-article-card rv">
							<div class="as-article-thumb">
								<span class="as-article-thumb-inner"<?php if ( ! empty( $a_img['url'] ) ) : ?> style="background-image:url('<?php echo esc_url( $a_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( get_the_title( $a_id ) ); ?>"<?php endif; ?>><?php
								if ( empty( $a_img['url'] ) && ! empty( $a_cat ) ) {
									echo esc_html( $a_cat );
								}
								?></span>
							</div>
							<?php if ( ! empty( $a_date ) ) : ?>
								<div class="as-article-date boa-num"><?php echo esc_html( $a_date ); ?></div>
							<?php endif; ?>
							<h3 class="as-article-title"><?php echo esc_html( get_the_title( $a_id ) ); ?></h3>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ MATCHING BANNER ════════ -->
	<?php if ( $show_match ) : ?>
		<section class="match-banner-sec">
			<div class="match-banner rv">
				<div class="match-banner-left">
					<span class="match-banner-label">Artist Matching</span>
					<h2 class="match-banner-title">企業理念 × アーティスト</h2>
					<p class="match-banner-sub">価値観で繋がる、アートとの出会い。</p>
					<p class="match-banner-body"><span class="boa-num">5</span>つの質問にお答えいただくだけで、御社のパーパスに最も共鳴するアーティストをご提案します。</p>
				</div>
				<div class="match-banner-right">
					<a href="<?php echo esc_url( home_url( '/matching-purpose/' ) ); ?>" class="match-banner-btn">診断スタート</a>
					<span class="match-banner-meta"><span class="boa-num">5</span>QUESTIONS &nbsp;/&nbsp; <span class="boa-num">3</span>MIN</span>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ BACK LINK ════════ -->
	<div class="as-back">
		<div class="as-back-inner">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'artist' ) ); ?>">アーティスト一覧へ戻る</a>
		</div>
	</div>

	<!-- ════════ CTA ════════ -->
	<?php
	if ( $show_cta ) {
		get_template_part( 'template-parts/sections/section-cta' );
	}
	?>

</main>

<!-- 画像拡大ライトボックス -->
<div class="boa-lightbox" id="boaLightbox" aria-hidden="true">
	<button class="boa-lightbox-close" id="boaLightboxClose" aria-label="閉じる">&times;</button>
	<div class="boa-lightbox-img" id="boaLightboxImg"></div>
</div>

	<?php
endwhile;

get_footer();
