<?php
/**
 * 単一作品（single-art）テンプレート
 *
 * mockups/art-single.html の構造を移植し、ハードコード値を Meta Box /
 * タクソノミー / Relationships から動的取得する。Main Color / Collected by /
 * Ownership History はモックに無いセクションだが、Phase 1 仕様に基づき追加し、
 * 各セクションは bankofart_should_show_section() で二段階チェックする。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$art_id = get_the_ID();

	// ---- 基本 ----
	$title       = get_the_title();
	$title_en    = rwmb_meta( 'art_title_en' );
	$number      = rwmb_meta( 'art_number' );
	$year        = rwmb_meta( 'art_year' );
	$medium      = rwmb_meta( 'art_medium' );
	$size_detail = rwmb_meta( 'art_size_detail' );
	$size_label  = rwmb_meta( 'art_size_label' );
	$description = rwmb_meta( 'art_description' );
	$concept     = rwmb_meta( 'art_concept' );

	$main_image  = bankofart_get_image( 'art_main_image', $art_id, 'large' );
	$gallery     = array_filter(
		(array) rwmb_meta( 'art_gallery', array( 'size' => 'large' ) ),
		static function ( $g ) {
			return ! empty( $g['url'] );
		}
	);

	// ---- ステータス ----
	$status_term = bankofart_get_first_term_name( $art_id, 'art_status' );
	$status_uc   = strtoupper( (string) $status_term );
	$is_owned    = ( 'OWNED' === $status_uc );
	$status_jp   = $is_owned ? 'コレクト済み（OWNED）' : '在庫あり（AVAILABLE）';

	// ---- タクソノミー（表示用に名前を連結） ----
	$tax_join = static function ( $post_id, $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return implode( ' / ', wp_list_pluck( $terms, 'name' ) );
	};
	$form_label  = $tax_join( $art_id, 'art_form' );
	$genre_label = $tax_join( $art_id, 'art_genre' );
	$tech_label  = $tax_join( $art_id, 'art_technique' );

	// ---- アーティスト（artist_to_art 逆引き） ----
	$artists   = bankofart_get_connected( 'artist_to_art', 'to', $art_id );
	$artist    = ! empty( $artists ) ? $artists[0] : null;
	$artist_id = $artist ? $artist->ID : 0;

	// 同アーティストの他作品（自身を除外）。
	$other_works = array();
	if ( $artist_id ) {
		$siblings = bankofart_get_connected( 'artist_to_art', 'from', $artist_id );
		foreach ( $siblings as $w ) {
			if ( (int) $w->ID !== (int) $art_id ) {
				$other_works[] = $w;
			}
		}
	}

	// ---- 所有企業・所有歴 ----
	$owners            = bankofart_get_connected( 'art_to_owner', 'from', $art_id );
	$ownership_history = array_filter( (array) rwmb_meta( 'ownership_history' ) );

	// ---- セクション可視性 ----
	$show_about     = bankofart_should_show_section( 'art_show_about', $description, $art_id );
	$show_artist    = bankofart_should_show_section( 'art_show_artist', $artist, $art_id );
	$show_more      = bankofart_should_show_section( 'art_show_more_works', $other_works, $art_id );
	$show_collected = $is_owned && bankofart_should_show_section( 'art_show_collected_by', $owners, $art_id );
	$show_ownership = bankofart_should_show_section( 'art_show_ownership_history', $ownership_history, $art_id );
	$show_cta       = bankofart_should_show_section( 'art_show_cta', true, $art_id );

	// ---- スペック行（データのある行のみ） ----
	$spec_rows = array();
	if ( ! empty( $status_term ) ) {
		$spec_rows[] = array(
			'key'   => 'Status',
			'val'   => $status_jp,
			'class' => $is_owned ? 'status-owned' : 'status-available',
		);
	}
	$size_val = $size_detail ? $size_detail : $size_label;
	if ( ! empty( $size_val ) ) {
		$spec_rows[] = array(
			'key' => 'Size',
			'val' => $size_val,
		);
	}
	if ( ! empty( $form_label ) ) {
		$spec_rows[] = array(
			'key' => 'Form',
			'val' => $form_label,
		);
	}
	if ( ! empty( $tech_label ) ) {
		$spec_rows[] = array(
			'key' => 'Technique',
			'val' => $tech_label,
		);
	}
	if ( ! empty( $medium ) ) {
		$spec_rows[] = array(
			'key' => 'Medium',
			'val' => $medium,
		);
	}
	if ( ! empty( $year ) ) {
		$spec_rows[] = array(
			'key' => 'Year',
			'val' => $year . ' 年',
			'num' => true,
		);
	}
	if ( ! empty( $genre_label ) ) {
		$spec_rows[] = array(
			'key' => 'Genre',
			'val' => $genre_label,
		);
	}

	// CONTACT 系は一元管理ヘルパー（現状は外部URL：form-mailer / receptionist。
	// 後で自前ページ実装時にヘルパー側を差し替えれば全箇所反映）。
	$document_url = bankofart_document_request_url();
	$briefing_url = bankofart_briefing_url();
	// リセール待機リスト登録ページURL（OWNED作品のボタン用）。
	$resale_page  = get_page_by_path( 'resale-waitlist' );
	$resale_url   = $resale_page ? add_query_arg( 'artwork_id', $art_id, get_permalink( $resale_page ) ) : '';
	?>

<main id="main" class="single-art">

	<nav class="breadcrumb" aria-label="breadcrumb">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">HOME</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'art' ) ); ?>">ART</a>
		<span class="sep">/</span>
		<span><?php echo esc_html( $title ); ?></span>
	</nav>

	<!-- ════════ HERO ════════ -->
	<section class="aw-hero">
		<div class="aw-hero-gallery rv">
			<div class="aw-hero-image boa-zoomable">
				<?php if ( ! empty( $status_term ) ) : ?>
					<span class="aw-hero-status <?php echo $is_owned ? 'is-owned' : 'is-available'; ?>"><?php echo esc_html( $status_uc ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $number ) ) : ?>
					<span class="aw-hero-no"><?php echo esc_html( $number ); ?></span>
				<?php endif; ?>
				<span
					class="aw-hero-image-inner"
					id="awHeroMain"
					<?php if ( ! empty( $main_image['url'] ) ) : ?>
						style="background-image:url('<?php echo esc_url( $main_image['url'] ); ?>');"
						role="img" aria-label="<?php echo esc_attr( $main_image['alt'] ? $main_image['alt'] : $title ); ?>"
					<?php endif; ?>
				></span>
			</div>

			<?php if ( ! empty( $gallery ) ) : ?>
				<div class="aw-hero-thumbs">
					<?php
					$ti = 0;
					foreach ( $gallery as $g ) :
						$active = ( 0 === $ti ) ? ' is-active' : '';
						?>
						<button type="button" class="aw-hero-thumb<?php echo esc_attr( $active ); ?>" data-bg="<?php echo esc_url( $g['url'] ); ?>" aria-label="<?php echo esc_attr( sprintf( '画像%d', $ti + 1 ) ); ?>">
							<span class="aw-hero-thumb-inner" style="background-image:url('<?php echo esc_url( $g['url'] ); ?>');"></span>
						</button>
						<?php
						++$ti;
					endforeach;
					?>
				</div>
			<?php endif; ?>
		</div>

		<div class="aw-info rv d1">
			<?php if ( $artist ) : ?>
				<a href="<?php echo esc_url( get_permalink( $artist_id ) ); ?>" class="aw-info-artist font-deco"><?php echo esc_html( rwmb_meta( 'artist_name_en', '', $artist_id ) ? rwmb_meta( 'artist_name_en', '', $artist_id ) : get_the_title( $artist_id ) ); ?></a>
			<?php endif; ?>

			<h1 class="aw-info-title"><?php echo esc_html( $title ); ?></h1>

			<?php if ( ! empty( $spec_rows ) ) : ?>
				<div class="aw-spec">
					<?php foreach ( $spec_rows as $row ) : ?>
						<div class="aw-spec-row">
							<div class="aw-spec-key"><?php echo esc_html( $row['key'] ); ?></div>
							<div class="aw-spec-val <?php echo isset( $row['class'] ) ? esc_attr( $row['class'] ) : ''; ?> <?php echo ! empty( $row['num'] ) ? 'boa-num' : ''; ?>"><?php echo esc_html( $row['val'] ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $is_owned ) : ?>
				<!-- OWNED：リセール待機リスト導線（同一サイト内のため target=_blank なし） -->
				<p class="aw-resale-note"><?php echo esc_html__( 'この作品は現在、応援企業が所有しています。リセール待機リストにご登録いただくと、作品が入荷した際に担当者よりご案内いたします。', 'bankofart' ); ?></p>
				<div class="aw-cta-btns">
					<?php if ( $resale_url ) : ?>
						<a href="<?php echo esc_url( $resale_url ); ?>" class="aw-cta-primary"><?php echo esc_html__( 'リセール待機リストに登録', 'bankofart' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $briefing_url ); ?>" class="aw-cta-outline"><?php echo esc_html__( 'オンライン説明会', 'bankofart' ); ?></a>
				</div>
			<?php else : ?>
				<!-- AVAILABLE：通常の問い合わせ導線（自前ページ・同一サイト内のため target=_blank なし） -->
				<p class="aw-note"><?php echo esc_html__( '作品の販売は対面契約のみとなります。価格・在庫状況・即時償却の詳細は、資料請求またはオンライン説明会にてご案内いたします。', 'bankofart' ); ?></p>
				<div class="aw-cta-btns">
					<a href="<?php echo esc_url( $briefing_url ); ?>" class="aw-cta-primary"><?php echo esc_html__( 'この作品を購入したい方はこちら', 'bankofart' ); ?></a>
					<a href="<?php echo esc_url( $document_url ); ?>" class="aw-cta-outline"><?php echo esc_html__( '資料請求', 'bankofart' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
	</section>

	<!-- MAIN COLOR セクションは撤廃（カラー解説はアーカイブページに集約） -->

	<!-- ════════ ABOUT THE WORK ════════ -->
	<?php if ( $show_about ) : ?>
		<section class="aw-story-sec">
			<div class="aw-story">
				<div class="aw-story-label rv">About the Work</div>
				<h2 class="aw-story-title rv d1">この作品について</h2>
				<div class="aw-story-text rv d2">
					<?php echo wp_kses_post( bankofart_richtext( $description ) ); ?>
					<?php if ( ! empty( trim( wp_strip_all_tags( (string) $concept ) ) ) ) : ?>
						<?php echo wp_kses_post( bankofart_richtext( $concept ) ); ?>
					<?php endif; ?>
				</div>
			</div>

			<?php
			/*
			 * 以前ここにギャラリー画像の横スクロール（マーキー）を置いていたが、
			 * 同じ作品の写真が繰り返し流れるだけで見応えが無いため廃止した。
			 * 横スクロール演出は single-artist.php の「起源の物語」下に、
			 * 制作風景写真を流す形で移設している。
			 */
			?>
		</section>
	<?php endif; ?>

	<!-- ════════ ARTIST ════════ -->
	<?php
	if ( $show_artist ) :
		$a_img    = bankofart_get_image( 'artist_main_photo', $artist_id, 'large' );
		$a_nameen = rwmb_meta( 'artist_name_en', '', $artist_id );
		$a_bio    = rwmb_meta( 'artist_theme_long', '', $artist_id );
		?>
		<section class="aw-artist-sec">
			<div class="aw-artist-head">
				<div class="aw-artist-head-en rv">ARTIST</div>
				<div class="aw-artist-head-ja rv d1">この作品を手がけたアーティスト</div>
			</div>
			<a href="<?php echo esc_url( get_permalink( $artist_id ) ); ?>" class="aw-artist-card rv">
				<div class="aw-artist-photo">
					<span class="aw-artist-photo-inner"<?php if ( ! empty( $a_img['url'] ) ) : ?> style="background-image:url('<?php echo esc_url( $a_img['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( get_the_title( $artist_id ) ); ?>"<?php endif; ?>></span>
				</div>
				<div class="aw-artist-info">
					<?php if ( ! empty( $a_nameen ) ) : ?>
						<p class="aw-artist-name-en"><?php echo esc_html( $a_nameen ); ?></p>
					<?php endif; ?>
					<h3 class="aw-artist-name"><?php echo esc_html( get_the_title( $artist_id ) ); ?></h3>
					<?php if ( ! empty( $a_bio ) ) : ?>
						<p class="aw-artist-bio"><?php echo esc_html( $a_bio ); ?></p>
					<?php endif; ?>
					<span class="aw-artist-link">プロフィールを見る</span>
				</div>
			</a>
		</section>
	<?php endif; ?>

	<!-- ════════ COLLECTED BY（OWNED時のみ）════════ -->
	<?php if ( $show_collected ) : ?>
		<section class="aw-owner-sec">
			<div class="aw-owner-inner">
				<div class="aw-owner-head">
					<div class="aw-owner-en rv">COLLECTED BY</div>
					<div class="aw-owner-ja rv d1">この作品を迎えた企業</div>
				</div>
				<div class="aw-owner-grid">
					<?php
					foreach ( $owners as $owner ) :
						$o_id   = $owner->ID;
						$o_logo = bankofart_get_image( 'collector_logo', $o_id, 'medium' );
						?>
						<a href="<?php echo esc_url( get_permalink( $o_id ) ); ?>" class="aw-owner-card rv">
							<div class="aw-owner-logo">
								<?php if ( ! empty( $o_logo['url'] ) ) : ?>
									<img src="<?php echo esc_url( $o_logo['url'] ); ?>" alt="<?php echo esc_attr( get_the_title( $o_id ) ); ?>" loading="lazy">
								<?php else : ?>
									<span class="aw-owner-logo-text"><?php echo esc_html( get_the_title( $o_id ) ); ?></span>
								<?php endif; ?>
							</div>
							<div class="aw-owner-name"><?php echo esc_html( get_the_title( $o_id ) ); ?></div>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ OWNERSHIP HISTORY ════════ -->
	<?php if ( $show_ownership ) : ?>
		<section class="aw-history-sec">
			<div class="aw-history-inner">
				<div class="aw-history-head">
					<div class="aw-history-en rv">OWNERSHIP HISTORY</div>
					<div class="aw-history-ja rv d1">所有の歩み</div>
				</div>
				<ul class="aw-history-list rv">
					<?php
					foreach ( $ownership_history as $row ) :
						$h_owner   = ! empty( $row['collector_ref'] ) ? get_the_title( (int) $row['collector_ref'] ) : '';
						$h_from    = ! empty( $row['from_date'] ) ? date_i18n( 'Y年n月', strtotime( $row['from_date'] ) ) : '';
						$h_current = ! empty( $row['is_current'] );
						$h_to      = ! empty( $row['to_date'] ) ? date_i18n( 'Y年n月', strtotime( $row['to_date'] ) ) : ( $h_current ? '現在' : '' );
						$h_rate    = ( isset( $row['resale_rate'] ) && '' !== $row['resale_rate'] ) ? $row['resale_rate'] : '';
						$h_comment = ! empty( $row['comment'] ) ? $row['comment'] : '';
						$h_period  = trim( $h_from . ( ( $h_from && $h_to ) ? ' 〜 ' : '' ) . $h_to );
						?>
						<li class="aw-history-item">
							<?php if ( '' !== $h_period ) : ?>
								<span class="aw-history-period boa-num"><?php echo esc_html( $h_period ); ?></span>
							<?php endif; ?>
							<span class="aw-history-owner">
								<?php echo esc_html( $h_owner ); ?>
								<?php if ( ! empty( $row['is_first'] ) ) : ?><span class="aw-history-badge">初代</span><?php endif; ?>
								<?php if ( $h_current ) : ?><span class="aw-history-badge is-current">現所有</span><?php endif; ?>
							</span>
							<?php if ( '' !== $h_rate ) : ?>
								<span class="aw-history-rate">リセール査定率 <span class="boa-num"><?php echo esc_html( $h_rate ); ?></span>%</span>
							<?php endif; ?>
							<?php if ( '' !== $h_comment ) : ?>
								<span class="aw-history-note"><?php echo esc_html( $h_comment ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ MORE WORKS ════════ -->
	<?php if ( $show_more ) : ?>
		<section class="aw-other-sec">
			<div class="aw-other">
				<div class="aw-other-head">
					<div class="aw-other-en rv">MORE WORKS</div>
					<div class="aw-other-ja rv d1"><?php echo esc_html( sprintf( '%s の他の作品', get_the_title( $artist_id ) ) ); ?></div>
				</div>
				<div class="aw-other-grid" id="awOtherGrid">
					<?php
					foreach ( $other_works as $work ) :
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
				 * 作品数が多いアーティスト（KATHMIは25点）だと全件表示ではスクロールが長すぎるため、
				 * 初期は3件（モバイル2件）だけ出し、残りは「もっと見る」で追加する。
				 * 制御は assets/js/single-detail.js。JS無効時はCSSが効かず全件表示のままになる。
				 */
				if ( count( $other_works ) > 3 ) :
					?>
					<div class="aw-other-more" id="awOtherMore">
						<button type="button" class="archive-loadmore-btn" id="awOtherMoreBtn"><?php echo esc_html__( 'もっと見る', 'bankofart' ); ?></button>
					</div>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- ════════ BACK LINK ════════ -->
	<div class="aw-back">
		<div class="aw-back-inner">
			<a href="<?php echo esc_url( get_post_type_archive_link( 'art' ) ); ?>">作品一覧へ戻る</a>
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
