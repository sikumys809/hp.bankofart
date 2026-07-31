<?php
/**
 * フロントページ（front-page）
 *
 * mockups/index.html を正として移植。
 * STEP 1：骨組み + 静的セクション（HERO / GALLERY / HOW / FOUNDER / CTA）。
 * データ連動セクション（ARTIST / ART / COLLECTOR / NEWS）は STEP 2 で実装するため、
 * 見出しのみ出し、カード一覧部分はプレースホルダ（コメント）にしている。
 *
 * セクション順：HERO → ARTIST → ART → COLLECTOR → GALLERY → HOW → FOUNDER → NEWS → CTA
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// ---- HERO 背景：カスタマイザー（外観→カスタマイズ→ヒーロー）で管理。
// 表示タイプ（画像スライドショー / 動画）と各メディアは theme_mod から取得。
// 未設定時は最下層 .hero-bg のCSSグラデにフォールバック。上層（ブラー/ロゴ/キャッチ）は不変。
$hero_type     = bankofart_hero_type();
$hero_images   = bankofart_hero_images();
$hero_interval = bankofart_hero_interval();
$hero_video    = bankofart_hero_video();
$hero_poster   = bankofart_hero_poster();

// ロゴ（hero）。boa-12=回転する円マーク / boa-17=白ワードマーク。
$logo_circle = get_theme_file_uri( 'assets/img/logo/boa-12.png' );
$logo_text   = get_theme_file_uri( 'assets/img/logo/boa-17.png' );

// MESSAGE（FOUNDER）ポートレート。ファイルがあれば背景に cover 表示、無ければCSSのグラデ。
// 画像更新時に確実に反映されるよう URL へ filemtime ベースの ?ver= を付与（キャッシュ破棄）。
$founder_mizuno_path = get_theme_file_path( 'assets/img/founder/mizuno.png' );
$founder_mizuno_bg   = file_exists( $founder_mizuno_path )
	? ' style="background-image:url(\'' . esc_url( get_theme_file_uri( 'assets/img/founder/mizuno.png' ) . '?ver=' . filemtime( $founder_mizuno_path ) ) . '\');background-size:cover;background-position:center 20%;"'
	: '';
$founder_okada_path = get_theme_file_path( 'assets/img/founder/okada.jpeg' );
$founder_okada_bg   = file_exists( $founder_okada_path )
	? ' style="background-image:url(\'' . esc_url( get_theme_file_uri( 'assets/img/founder/okada.jpeg' ) . '?ver=' . filemtime( $founder_okada_path ) ) . '\');background-size:cover;background-position:center 20%;"'
	: '';

// アーカイブリンク。
$artist_archive    = get_post_type_archive_link( 'artist' );
$art_archive       = get_post_type_archive_link( 'art' );
$collector_archive = get_post_type_archive_link( 'collector' );
$news_archive      = get_post_type_archive_link( 'news' );

/*
 * STEP2 データ取得（各セクションは「最新N件抜粋」。アーカイブ全件とは別物）。
 * 出力には get_posts()（配列）を使い、メインクエリのグローバルを汚さない。
 * 各フィールドは post_id を明示して rwmb_meta / ヘルパーで取得する。
 */
/*
 * ARTIST：TOP表示ONのアーティストをレール（最大5名）に流し込む。
 * 「TOPページに表示する（artist_top_featured）」が ON の投稿を取得し、
 * 「TOP表示順（artist_top_order）」昇順で並べ、先頭5名だけ使う。
 *
 * ART と同様、順序未設定が orderby の INNER JOIN で除外されないよう、絞り込みのみ
 * クエリで行い、ソート＆5名打ち切りは usort + array_slice で安全に処理する。
 * featured は未設定/OFF（'0'）なら対象外（= meta value '1' の一致のみ取得）。
 */
$front_artists = get_posts(
	array(
		'post_type'      => 'artist',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- TOP抜粋。featured のみで件数僅少。
			array(
				'key'     => 'artist_top_featured',
				'value'   => '1',
				'compare' => '=',
			),
		),
	)
);

// artist_top_order 昇順で並べ替え（未設定は末尾、同値は公開日降順）。
usort(
	$front_artists,
	static function ( $a, $b ) {
		$oa = rwmb_meta( 'artist_top_order', '', $a->ID );
		$ob = rwmb_meta( 'artist_top_order', '', $b->ID );
		$oa = is_numeric( $oa ) ? (float) $oa : INF;
		$ob = is_numeric( $ob ) ? (float) $ob : INF;
		if ( $oa === $ob ) {
			return strcmp( $b->post_date, $a->post_date ); // 同順位は新しい順。
		}
		return ( $oa < $ob ) ? -1 : 1;
	}
);

// レールは最大5名（モック：5カラム）。超過分は先頭5名で打ち切り。少なければあるだけ。
$front_artists = array_slice( $front_artists, 0, 5 );

/*
 * ART：TOP表示ONの作品をコラージュ（上段3＋下段4＝最大7点）に流し込む。
 * 「TOPページに表示する（art_top_featured）」が ON の作品を取得し、
 * 「TOP表示順（art_top_order）」昇順で並べ、先頭7点だけ使う。
 *
 * 並び替えは PHP 側で行う。art_top_order を orderby の meta_key に使うと
 * 順序未設定の作品が INNER JOIN で除外されてしまうため、ここでは絞り込みだけ
 * クエリで行い、ソート＆7点打ち切りは usort + array_slice で安全に処理する。
 * featured 件数は実運用でも数点程度のため -1 取得でも軽い。
 */
$front_arts = get_posts(
	array(
		'post_type'      => 'art',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- TOP抜粋。featured のみで件数僅少。
			array(
				'key'     => 'art_top_featured',
				'value'   => '1',
				'compare' => '=',
			),
		),
	)
);

// art_top_order 昇順で並べ替え（未設定は末尾、同値は公開日降順）。
usort(
	$front_arts,
	static function ( $a, $b ) {
		$oa = rwmb_meta( 'art_top_order', '', $a->ID );
		$ob = rwmb_meta( 'art_top_order', '', $b->ID );
		$oa = is_numeric( $oa ) ? (float) $oa : INF;
		$ob = is_numeric( $ob ) ? (float) $ob : INF;
		if ( $oa === $ob ) {
			return strcmp( $b->post_date, $a->post_date ); // 同順位は新しい順。
		}
		return ( $oa < $ob ) ? -1 : 1;
	}
);

// コラージュは最大7点（上段3＋下段4）。超過分は打ち切り（将来：2段以上に拡張余地）。
$front_arts = array_slice( $front_arts, 0, 7 );

// COLLECTOR：画家応援企業の最新12社（ロゴグリッド）。
$front_collectors = get_posts(
	array(
		'post_type'      => 'collector',
		'post_status'    => 'publish',
		'posts_per_page' => 12,
		'orderby'        => array(
			'menu_order' => 'ASC',
			'date'       => 'DESC',
		),
	)
);

// NEWS：最新4件（公開日降順）。card-news を context='top'（縦カード）で出力。
$front_news = get_posts(
	array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => 4,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

// ART コラージュのフレーム比率。各作品の art_top_size を最優先で反映し、
// 未設定の作品はこのモック既定の並び（上段 wide/tall/square、下段 tall/square/tall/wide）に
// 位置でフォールバックする。
$front_frame_types = array( 'type-wide', 'type-tall', 'type-square', 'type-tall', 'type-square', 'type-tall', 'type-wide' );
// art_top_size の値 → frame の type-クラス対応。
$front_top_size_map = array(
	'wide'   => 'type-wide',
	'tall'   => 'type-tall',
	'square' => 'type-square',
);
?>

<main id="main" class="front-page">

	<!-- ════════ HERO（sticky 2レイヤー）════════ -->
	<div class="hero-stack-wrap">

		<!-- レイヤー1：背景 + ロゴ + キャッチ（スクロールで blur） -->
		<section class="hero-stack-layer hero-layer-logo">
			<div class="hero-blur-target">
				<?php if ( 'video' === $hero_type ) : ?>
					<?php if ( $hero_video || $hero_poster ) : ?>
						<div class="hero-bg hero-bg--video"<?php echo $hero_poster ? ' style="background-image:url(\'' . esc_url( $hero_poster ) . '\');"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>>
							<?php if ( $hero_video ) : ?>
								<video class="hero-video" autoplay muted loop playsinline<?php echo $hero_poster ? ' poster="' . esc_url( $hero_poster ) . '"' : ''; ?>>
									<source src="<?php echo esc_url( $hero_video ); ?>" type="video/mp4">
								</video>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="hero-bg"></div>
					<?php endif; ?>
				<?php elseif ( ! empty( $hero_images ) ) : ?>
					<div class="hero-bg hero-bg--slideshow" data-hero-interval="<?php echo esc_attr( $hero_interval ); ?>">
						<?php foreach ( $hero_images as $bankofart_si => $bankofart_simg ) : ?>
							<div class="hero-slide<?php echo 0 === $bankofart_si ? ' is-active' : ''; ?>" style="background-image:url('<?php echo esc_url( $bankofart_simg ); ?>');"></div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="hero-bg"></div>
				<?php endif; ?>
				<div class="hero-content">
					<div class="hero-logo-stack">
						<img class="logo-circle" src="<?php echo esc_url( $logo_circle ); ?>" alt="">
						<img class="logo-text" src="<?php echo esc_url( $logo_text ); ?>" alt="BANK of ART">
					</div>
					<div class="hero-catch">
						<div class="hero-catch-ja">絵描きの明日を創出する。</div>
					</div>
				</div>
			</div>
			<div class="hero-scroll-hint">
				<div class="scroll-v"></div>
				<div class="scroll-v-anim"></div>
				<span class="scroll-word">Scroll</span>
			</div>
		</section>

		<!-- レイヤー2：ARTIST（ヒーロー上に重なる） ※STEP2でカード連動 -->
		<section class="hero-stack-layer hero-layer-artist artist-section" id="artist">
			<div class="artist-stack-inner">
				<div class="dark-section-head">
					<h2 class="dark-section-head-en"><span class="tr" data-tr>Artist</span></h2>
					<p class="dark-section-head-ja rv d1">公認アーティスト一覧</p>
					<div class="head-rule"></div>
				</div>

				<div class="artist-inner">
					<div class="artist-rail">
						<?php if ( $front_artists ) : ?>
							<?php
							$artist_i = 0;
							foreach ( $front_artists as $artist ) :
								$artist_i++;
								$a_id    = $artist->ID;
								$a_name  = get_the_title( $a_id );
								$a_desc  = (string) rwmb_meta( 'artist_theme_short', '', $a_id );
								if ( '' === trim( $a_desc ) ) {
									$a_desc = (string) rwmb_meta( 'artist_catch_phrase', '', $a_id );
								}
								$a_photo = bankofart_get_image( 'artist_main_photo', $a_id, 'large' );
								$ap      = 'ap' . ( ( ( $artist_i - 1 ) % 5 ) + 1 ); // 写真未設定時のフォールバック背景。
								$a_delay = $artist_i > 1 ? ' d' . min( $artist_i - 1, 4 ) : '';
								$a_bg    = ! empty( $a_photo['url'] ) ? ' style="background-image:url(\'' . esc_url( $a_photo['url'] ) . '\');"' : '';
								?>
								<a href="<?php echo esc_url( get_permalink( $a_id ) ); ?>" class="artist-card rv<?php echo esc_attr( $a_delay ); ?>">
									<div class="artist-portrait">
										<div class="artist-port-img <?php echo esc_attr( $ap ); ?>"<?php echo $a_bg; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>></div>
										<div class="artist-history">HISTORY →</div>
									</div>
									<div class="artist-info">
										<div class="artist-name-ja"><?php echo esc_html( $a_name ); ?></div>
										<?php if ( '' !== trim( (string) $a_desc ) ) : ?>
											<div class="artist-desc"><?php echo esc_html( $a_desc ); ?></div>
										<?php endif; ?>
									</div>
								</a>
							<?php endforeach; ?>
						<?php else : ?>
							<p class="front-empty">アーティストを準備中です。</p>
						<?php endif; ?>
					</div>
					<div class="guest-more rv d2">
						<a href="<?php echo esc_url( $artist_archive ); ?>" class="guest-more-btn">View All Artists →</a>
					</div>
				</div>
			</div>
		</section>
	</div>

	<div class="rule-section"><hr class="rule-h rule-double"></div>

	<!-- ════════ ART ※STEP2でカード連動 ════════ -->
	<section class="art-section" id="art">
		<div class="dark-section-head">
			<h2 class="dark-section-head-en"><span class="tr" data-tr>Art</span></h2>
			<p class="dark-section-head-ja rv d1">作品一覧</p>
			<div class="head-rule"></div>
		</div>
		<?php if ( $front_arts ) : ?>
			<?php
			$rows  = array( array_slice( $front_arts, 0, 3 ), array_slice( $front_arts, 3, 4 ) );
			$order = 0;
			?>
			<div class="art-collage" id="artCollage">
				<?php
				foreach ( $rows as $row ) :
					if ( empty( $row ) ) {
						continue;
					}
					?>
					<div class="art-row">
						<?php
						foreach ( $row as $art ) :
							$art_id2     = $art->ID;
							// 表示サイズは作品の art_top_size を優先。未設定は位置でモック既定にフォールバック。
							$art_top_size = (string) rwmb_meta( 'art_top_size', '', $art_id2 );
							if ( isset( $front_top_size_map[ $art_top_size ] ) ) {
								$type = $front_top_size_map[ $art_top_size ];
							} elseif ( isset( $front_frame_types[ $order ] ) ) {
								$type = $front_frame_types[ $order ];
							} else {
								$type = 'type-square';
							}
							$art_num     = (string) rwmb_meta( 'art_number', '', $art_id2 );
							$art_img     = bankofart_get_art_top_image( $art_id2, 'large' );
							$art_en      = (string) rwmb_meta( 'art_title_en', '', $art_id2 );
							$art_disp    = '' !== trim( $art_en ) ? $art_en : get_the_title( $art_id2 );
							$art_makers  = bankofart_get_connected( 'artist_to_art', 'to', $art_id2 );
							$art_artist  = ! empty( $art_makers ) ? get_the_title( $art_makers[0]->ID ) : '';
							$art_bg      = ! empty( $art_img['url'] ) ? ' style="background-image:url(\'' . esc_url( $art_img['url'] ) . '\');"' : '';
							$art_num_dig = preg_replace( '/[^0-9]/', '', $art_num );
							?>
							<a href="<?php echo esc_url( get_permalink( $art_id2 ) ); ?>" class="frame <?php echo esc_attr( $type ); ?>" data-order="<?php echo esc_attr( $order ); ?>">
								<?php if ( '' !== trim( $art_num ) ) : ?>
									<div class="frame-num">#<?php echo esc_html( $art_num ); ?></div>
								<?php endif; ?>
								<div class="frame-inner">
									<div class="frame-art"<?php echo $art_bg; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>></div>
									<div class="frame-label">
										<?php if ( '' !== $art_num_dig ) : ?>
											<div class="frame-label-no">No. <?php echo esc_html( str_pad( $art_num_dig, 4, '0', STR_PAD_LEFT ) ); ?></div>
										<?php endif; ?>
										<div class="frame-label-name"><?php echo esc_html( $art_disp ); ?></div>
										<?php if ( '' !== trim( (string) $art_artist ) ) : ?>
											<div class="frame-label-artist"><?php echo esc_html( $art_artist ); ?></div>
										<?php endif; ?>
									</div>
								</div>
							</a>
							<?php
							$order++;
						endforeach;
						?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="front-empty-wrap"><p class="front-empty">作品を準備中です。</p></div>
		<?php endif; ?>
		<div class="art-cta rv">
			<a href="<?php echo esc_url( $art_archive ); ?>" class="art-see-all">View All Works →</a>
		</div>
	</section>

	<!-- ════════ COLLECTOR ※STEP2でカード連動 ════════ -->
	<section class="collector" id="collector">
		<div class="collector-inner">
			<div class="collector-head rv">
				<h2 class="collector-head-en"><span class="tr" data-tr>Collector</span></h2>
				<p class="collector-head-ja">画家応援企業</p>
				<div class="head-rule"></div>
			</div>
			<?php if ( $front_collectors ) : ?>
				<div class="collector-grid">
					<?php
					$collector_i = 0;
					foreach ( $front_collectors as $collector ) :
						$collector_i++;
						$c_id    = $collector->ID;
						$c_name  = (string) rwmb_meta( 'collector_company_name', '', $c_id );
						if ( '' === trim( $c_name ) ) {
							$c_name = get_the_title( $c_id );
						}
						// メイン画像（オフィスメイン写真）を優先。未設定時のみ企業ロゴにフォールバック。
						$c_img   = bankofart_get_image( 'collector_main_office_image', $c_id, 'medium' );
						if ( empty( $c_img['url'] ) ) {
							$c_img = bankofart_get_image( 'collector_logo', $c_id, 'medium' );
						}
						$c_bg    = ! empty( $c_img['url'] ) ? ' style="background-image:url(\'' . esc_url( $c_img['url'] ) . '\');"' : '';
						?>
						<a href="<?php echo esc_url( get_permalink( $c_id ) ); ?>" class="collector-card rv">
							<div class="collector-card-img"<?php echo $c_bg; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>></div>
							<div class="collector-card-body">
								<div class="collector-card-num">No. <?php echo esc_html( str_pad( (string) $collector_i, 2, '0', STR_PAD_LEFT ) ); ?></div>
								<div class="collector-card-name"><?php echo esc_html( $c_name ); ?></div>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="front-empty-wrap"><p class="front-empty">画家応援企業を準備中です。</p></div>
			<?php endif; ?>
			<div class="collector-cta rv">
				<a href="<?php echo esc_url( $collector_archive ); ?>" class="collector-see-all">View All Collectors →</a>
			</div>
		</div>
	</section>

	<!-- ════════ GALLERY（静的）════════ -->
	<section class="gallery-section" id="gallery">
		<div class="dark-section-head">
			<h2 class="dark-section-head-en"><span class="tr" data-tr>Gallery</span></h2>
			<p class="dark-section-head-ja rv d1">展示・ギャラリー風景</p>
			<div class="head-rule"></div>
		</div>

		<div class="gallery-stage">
			<div class="carousel-wrap">
				<div class="carousel-track" id="carTrack">
					<?php
					// ギャラリー写真（assets/images/gallery/）。
					$gallery_images = array( 'gallery1.jpeg', 'gallery2.jpeg', 'gallery3.jpeg', 'gallery4.jpeg' );
					foreach ( $gallery_images as $bankofart_gallery_img ) :
						$bankofart_gallery_url = get_theme_file_uri( 'assets/images/gallery/' . $bankofart_gallery_img );
						?>
						<div class="car-item">
							<div class="car-item-img"><div class="car-item-img-bg" style="background-image:url('<?php echo esc_url( $bankofart_gallery_url ); ?>');"></div></div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="car-controls">
				<button class="car-btn" id="carPrev" aria-label="Previous">&larr;</button>
				<div class="car-indicator" id="carIndicator"></div>
				<button class="car-btn" id="carNext" aria-label="Next">&rarr;</button>
			</div>

			<div class="gallery-cta rv">
				<a href="<?php echo esc_url( bankofart_briefing_url() ); ?>" class="gallery-reservation" target="_blank" rel="noopener">RESERVATION →</a>
			</div>
		</div>
	</section>

	<!-- ════════ HOW（静的・4 STEPS）════════ -->
	<section class="how" id="how">
		<div class="how-inner">
			<div class="how-head">
				<h2 class="how-head-title"><span class="tr" data-tr>4 STEPS OF BANK OF ART</span></h2>
				<p class="how-head-ja rv d1">ご利用の流れ</p>
				<div class="head-rule"></div>
			</div>

			<div class="how-stack" id="howStack">
				<div class="how-line"><div class="how-line-fill" id="howLineFill"></div></div>

				<div class="how-row right rv">
					<div class="how-num-wrap"><span class="how-num">01</span></div>
					<div class="how-txt-wrap">
						<div class="how-step-label">Deposited</div>
						<div class="how-step-ja">作品を預ける</div>
						<div class="how-step-desc">アーティストから作品を全買取形式で預託。<br class="br-pc"><br class="br-sp">画家のキャッシュフローを改善します。</div>
					</div>
				</div>
				<div class="how-row left rv d1">
					<div class="how-num-wrap"><span class="how-num brand">02</span></div>
					<div class="how-txt-wrap">
						<div class="how-step-label">Purchased</div>
						<div class="how-step-ja">企業が購入</div>
						<div class="how-step-desc">即時償却・少額減価償却特例を活用して購入。<br class="br-pc"><br class="br-sp">節税と芸術支援を同時に実現します。</div>
					</div>
				</div>
				<div class="how-row right rv d2">
					<div class="how-num-wrap"><span class="how-num">03</span></div>
					<div class="how-txt-wrap">
						<div class="how-step-label">Exhibited</div>
						<div class="how-step-ja">オフィスに飾る</div>
						<div class="how-step-desc">作品をオフィスや公共施設に展示。<br class="br-pc"><br class="br-sp">作家の認知向上にも直接つながります。</div>
					</div>
				</div>
				<div class="how-row left rv d3">
					<div class="how-num-wrap"><span class="how-num brand">04</span></div>
					<div class="how-txt-wrap">
						<div class="how-step-label">Recovered</div>
						<div class="how-step-ja">リセールで回収</div>
						<div class="how-step-desc">最大70%のリセールで資産を回収。<br class="br-pc"><br class="br-sp">売却益も期待できます。</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- ════════ FOUNDER（静的・MESSAGE）════════ -->
	<section class="founder" id="founder">
		<div class="founder-inner">
			<h2 class="founder-head-en"><span class="tr" data-tr>MESSAGE</span></h2>
			<div class="head-rule"></div>

			<div class="founder-layout">
				<div class="founder-message rv d1">
					<p class="founder-quote">「画家という<br class="br-sp">生き方を、当たり前に。」</p>
					<p class="founder-quote-sub">誰にも見つかっていない<br class="br-sp">才能に最初のスポンサーを。</p>
				</div>
				<div class="founder-portraits rv d2">
					<div class="founder-card">
						<div class="founder-portrait fp1"<?php echo $founder_mizuno_bg; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>></div>
						<div class="founder-info">
							<div class="founder-name-ja">水野 永吉</div>
							<div class="founder-univ">慶應義塾大学 卒</div>
						</div>
					</div>
					<div class="founder-card">
						<div class="founder-portrait fp2"<?php echo $founder_okada_bg; // phpcs:ignore WordPress.Security.EscapeOutput -- URLは esc_url 済み ?>></div>
						<div class="founder-info">
							<div class="founder-name-ja">岡田 美波</div>
							<div class="founder-univ">成蹊大学 卒</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<div class="rule-section"><hr class="rule-h rule-double"></div>

	<!-- ════════ NEWS ※STEP2でカード連動 ════════ -->
	<section class="news" id="news">
		<div class="news-inner">
			<div class="news-head">
				<h2 class="news-head-en"><span class="tr" data-tr>News</span></h2>
				<p class="news-head-ja rv d1">最新記事</p>
				<div class="head-rule"></div>
			</div>
			<?php if ( $front_news ) : ?>
				<div class="news-grid">
					<?php
					foreach ( $front_news as $news_item ) :
						get_template_part(
							'template-parts/cards/card-news',
							null,
							array(
								'news_id' => $news_item->ID,
								'context' => 'top',
							)
						);
					endforeach;
					?>
				</div>
			<?php else : ?>
				<div class="front-empty-wrap"><p class="front-empty">NEWS を準備中です。</p></div>
			<?php endif; ?>
			<div class="news-all-wrap">
				<a href="<?php echo esc_url( $news_archive ); ?>" class="news-all">All News →</a>
			</div>
		</div>
	</section>

	<!-- ════════ CONTACT ════════ -->
	<?php get_template_part( 'template-parts/sections/section-cta' ); ?>

</main>

<?php
get_footer();
