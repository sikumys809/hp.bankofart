<?php
/**
 * JOURNAL 本文：インタビュー形式（アイコン＋吹き出し）
 *
 * journal_interview_qa（リピーター）を Q（聞き手・左）／ A（話し手・右）の
 * 吹き出しとして描画する。アイコンは以下の優先順で解決する。
 *   話し手 … journal_speaker_icon → 関連アーティスト1人目の artist_main_photo → 頭文字
 *   聞き手 … journal_interviewer_icon → テーマ同梱のBOAロゴ
 *
 * @param array $args {
 *     @type int   $journal_id  投稿ID。
 *     @type array $rel_artists 関連アーティストの投稿配列（アイコン自動取得用）。
 * }
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jid         = isset( $args['journal_id'] ) ? (int) $args['journal_id'] : get_the_ID();
$rel_artists = isset( $args['rel_artists'] ) ? (array) $args['rel_artists'] : array();

$qa_rows = array_filter( (array) rwmb_meta( 'journal_interview_qa', array(), $jid ) );
$intro   = rwmb_meta( 'journal_interview_intro', array(), $jid );

// ---- 話し手（未設定なら関連アーティスト1人目から補完）----
$lead_artist = ! empty( $rel_artists ) ? reset( $rel_artists ) : null;

$speaker_name = trim( (string) rwmb_meta( 'journal_speaker_name', array(), $jid ) );
if ( '' === $speaker_name && $lead_artist ) {
	$speaker_name = get_the_title( $lead_artist->ID );
}

$speaker_role = trim( (string) rwmb_meta( 'journal_speaker_role', array(), $jid ) );

$speaker_icon = bankofart_get_image( 'journal_speaker_icon', $jid, 'thumbnail' );
if ( empty( $speaker_icon['url'] ) && $lead_artist ) {
	$speaker_icon = bankofart_get_image( 'artist_main_photo', $lead_artist->ID, 'thumbnail' );
	if ( empty( $speaker_icon['url'] ) && has_post_thumbnail( $lead_artist->ID ) ) {
		$speaker_icon = array(
			'url' => get_the_post_thumbnail_url( $lead_artist->ID, 'thumbnail' ),
			'alt' => $speaker_name,
		);
	}
}

$speaker_link = $lead_artist ? get_permalink( $lead_artist->ID ) : '';

// プロフィール枠（200px角）は thumbnail だと荒れるので、同じ絵柄を large で取り直す。
$speaker_photo = bankofart_get_image( 'journal_speaker_icon', $jid, 'large' );
if ( empty( $speaker_photo['url'] ) && $lead_artist ) {
	$speaker_photo = bankofart_get_image( 'artist_main_photo', $lead_artist->ID, 'large' );
	if ( empty( $speaker_photo['url'] ) && has_post_thumbnail( $lead_artist->ID ) ) {
		$speaker_photo = array(
			'url' => get_the_post_thumbnail_url( $lead_artist->ID, 'large' ),
			'alt' => $speaker_name,
		);
	}
}
if ( empty( $speaker_photo['url'] ) ) {
	$speaker_photo = $speaker_icon;
}

// ---- 聞き手 ----
$interviewer_name = trim( (string) rwmb_meta( 'journal_interviewer_name', array(), $jid ) );
if ( '' === $interviewer_name ) {
	$interviewer_name = 'BOA';
}

$interviewer_icon = bankofart_get_image( 'journal_interviewer_icon', $jid, 'thumbnail' );
if ( empty( $interviewer_icon['url'] ) ) {
	$interviewer_icon = array(
		'url' => get_theme_file_uri( 'assets/img/logo/boa-11.png' ),
		'alt' => $interviewer_name,
	);
}

/**
 * 吹き出しのアイコンを出力する。画像が無ければ頭文字の円で代替する。
 *
 * @param array  $img  bankofart_get_image() 形式の配列。
 * @param string $name 話者名。
 * @param string $link リンク先URL（空ならリンクなし）。
 * @return void
 */
$bankofart_render_avatar = static function ( $img, $name, $link = '' ) {
	$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
	$tag     = $link ? 'a' : 'span';

	printf( '<%1$s class="sj-iv-avatar"%2$s>', esc_attr( $tag ), $link ? ' href="' . esc_url( $link ) . '"' : '' );
	if ( ! empty( $img['url'] ) ) {
		printf(
			'<span class="sj-iv-avatar-img" style="background-image:url(\'%1$s\');" role="img" aria-label="%2$s"></span>',
			esc_url( $img['url'] ),
			esc_attr( $name )
		);
	} else {
		printf( '<span class="sj-iv-avatar-initial" aria-hidden="true">%s</span>', esc_html( $initial ) );
	}
	printf( '<span class="sj-iv-avatar-name">%s</span>', esc_html( $name ) );
	printf( '</%s>', esc_attr( $tag ) );
};
?>

<?php if ( ! empty( $intro ) ) : ?>
	<div class="sj-iv-intro rv"><?php echo wp_kses_post( bankofart_richtext( $intro ) ); ?></div>
<?php endif; ?>

<?php
/*
 * 話し手のプロフィール（導入文の直後）。
 * 記事の冒頭で「誰の話なのか」が分かるよう、関連アーティストの写真・名前・
 * 制作テーマを添える。single-art.php の「この作品を手がけたアーティスト」と
 * 同じ作りで、リンク先はアーティストの詳細ページ。
 * 関連アーティストが未設定でも、話し手名だけで最低限の紹介として出す。
 */
if ( '' !== $speaker_name ) :
	$profile_theme = $lead_artist ? (string) rwmb_meta( 'artist_theme_long', '', $lead_artist->ID ) : '';
	$profile_catch = $lead_artist ? (string) rwmb_meta( 'artist_catch_phrase', '', $lead_artist->ID ) : '';
	$profile_en    = $lead_artist ? (string) rwmb_meta( 'artist_name_en', '', $lead_artist->ID ) : '';
	$profile_tag   = $speaker_link ? 'a' : 'div';

	// 英字名が表示名と同じ（TAIKI 等）なら二重表示になるので出さない。
	if ( 0 === strcasecmp( trim( $profile_en ), trim( $speaker_name ) ) ) {
		$profile_en = '';
	}
	?>
	<div class="sj-iv-profile rv">
		<div class="sj-iv-profile-head">
			<div class="sj-iv-profile-en">RELATED ARTIST</div>
			<div class="sj-iv-profile-ja">お話を伺ったアーティスト</div>
		</div>

		<<?php echo esc_attr( $profile_tag ); ?> class="sj-iv-profile-card"<?php echo $speaker_link ? ' href="' . esc_url( $speaker_link ) . '"' : ''; ?>>
			<div class="sj-iv-profile-photo">
				<span class="sj-iv-profile-photo-inner"<?php if ( ! empty( $speaker_photo['url'] ) ) : ?> style="background-image:url('<?php echo esc_url( $speaker_photo['url'] ); ?>');" role="img" aria-label="<?php echo esc_attr( $speaker_name ); ?>"<?php endif; ?>></span>
			</div>

			<div class="sj-iv-profile-info">
				<?php if ( '' !== $profile_en ) : ?>
					<p class="sj-iv-profile-name-en"><?php echo esc_html( $profile_en ); ?></p>
				<?php endif; ?>

				<h3 class="sj-iv-profile-name"><?php echo esc_html( $speaker_name ); ?></h3>

				<?php if ( '' !== $speaker_role ) : ?>
					<p class="sj-iv-profile-role"><?php echo esc_html( $speaker_role ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== trim( $profile_catch ) ) : ?>
					<p class="sj-iv-profile-catch"><?php echo esc_html( $profile_catch ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== trim( wp_strip_all_tags( $profile_theme ) ) ) : ?>
					<p class="sj-iv-profile-bio"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $profile_theme ), 90, '…' ) ); ?></p>
				<?php endif; ?>

				<?php if ( $speaker_link ) : ?>
					<span class="sj-iv-profile-link">プロフィールを見る</span>
				<?php endif; ?>
			</div>
		</<?php echo esc_attr( $profile_tag ); ?>>
	</div>
<?php endif; ?>

<div class="sj-iv-thread">
	<?php
	foreach ( $qa_rows as $row ) :
		$question = isset( $row['qa_question'] ) ? (string) $row['qa_question'] : '';
		$answer   = isset( $row['qa_answer'] ) ? (string) $row['qa_answer'] : '';
		$chapter  = isset( $row['qa_chapter'] ) ? (string) $row['qa_chapter'] : '';
		$img_ids  = ! empty( $row['qa_images'] ) ? (array) $row['qa_images'] : array();

		$has_q = '' !== trim( $question );
		$has_a = '' !== trim( wp_strip_all_tags( $answer ) );
		if ( ! $has_q && ! $has_a && empty( $img_ids ) && '' === trim( $chapter ) ) {
			continue;
		}
		?>

		<?php if ( '' !== trim( $chapter ) ) : ?>
			<h2 class="sj-iv-chapter rv"><?php echo esc_html( $chapter ); ?></h2>
		<?php endif; ?>

		<?php if ( $has_q ) : ?>
			<div class="sj-iv-turn sj-iv-turn--q rv">
				<?php $bankofart_render_avatar( $interviewer_icon, $interviewer_name ); ?>
				<div class="sj-iv-bubble">
					<p class="sj-iv-q-text"><?php echo nl2br( esc_html( trim( $question ) ) ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $has_a || ! empty( $img_ids ) ) : ?>
			<div class="sj-iv-turn sj-iv-turn--a rv">
				<?php $bankofart_render_avatar( $speaker_icon, $speaker_name, $speaker_link ); ?>
				<div class="sj-iv-bubble">
					<?php if ( $has_a ) : ?>
						<div class="sj-iv-a-text"><?php echo wp_kses_post( bankofart_richtext( $answer ) ); ?></div>
					<?php endif; ?>

					<?php if ( ! empty( $img_ids ) ) : ?>
						<div class="sj-iv-figs">
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
										'class'   => 'sj-iv-fig',
										'loading' => 'lazy',
									)
								);
							endforeach;
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

	<?php endforeach; ?>
</div>
