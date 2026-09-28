<?php
/**
 * カードコンポーネント：コレクションサイトの作品
 *
 * card-art.php（WPの作品投稿用）と同じDOM・クラスにして、既存のCSSをそのまま使う。
 * 違いは以下の3点だけ。
 *   - データ元が wp_boa_collection_arts（月1同期）
 *   - リンク先が collection.bankof-art.com の作品ページ（別タブで開く）
 *   - ステータスは SOLD OUT / AVAILABLE（WPの OWNED とは別系統）
 *
 * 引数（$args 経由）:
 *   - art         array  wp_boa_collection_arts の1行（必須）
 *   - context     string 'archive' | 'related' | 'top'（既定 'related'）
 *   - show_artist bool   画家名を表示するか（既定 true）
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$art = isset( $args['art'] ) ? (array) $args['art'] : array();
if ( empty( $art['collection_id'] ) ) {
	return;
}

$context     = isset( $args['context'] ) ? $args['context'] : 'related';
$show_artist = isset( $args['show_artist'] ) ? (bool) $args['show_artist'] : true;

$title   = (string) ( $art['title'] ?? '' );
$size    = (string) ( $art['size_label'] ?? '' );
$price   = (string) ( $art['price'] ?? '' );
$image   = (string) ( $art['image_url'] ?? '' );
$is_sold = ! empty( $art['is_sold'] );
$is_new  = ! empty( $art['is_new'] );
$url     = bankofart_collection_art_url( $art['collection_id'] );

// 画家名は WP のアーティスト投稿から引く（collection 側の表記ゆれに左右されないため）。
$artist_name = '';
if ( $show_artist && ! empty( $art['artist_slug'] ) ) {
	$artist_post = get_page_by_path( $art['artist_slug'], OBJECT, 'artist' );
	if ( $artist_post ) {
		$artist_name = get_the_title( $artist_post );
	}
}

// 号数・価格。どちらも入っていない作品があるので、あるものだけ出す。
$specs = array();
if ( '' !== $size ) {
	$specs[] = array(
		'text' => $size,
		'num'  => true,
	);
}
if ( '' !== $price ) {
	$specs[] = array(
		'text' => '¥' . $price,
		'num'  => true,
	);
}
?>
<a class="art-card art-card--collection art-card--<?php echo esc_attr( $context ); ?>" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
	<div class="art-image">
		<span class="art-status <?php echo $is_sold ? 'is-owned' : 'is-available'; ?>"><?php echo esc_html( $is_sold ? 'SOLD OUT' : 'AVAILABLE' ); ?></span>

		<?php if ( $is_new && ! $is_sold ) : ?>
			<span class="art-number"><?php esc_html_e( '新着', 'bankofart' ); ?></span>
		<?php endif; ?>

		<?php
		/*
		 * 作品の切り抜きは collection 側で手作業されており、しかも自由変形
		 * （四隅を別々に動かす）で指定されている。切り抜き済みの画像は配信されないので、
		 * その4点を同期時に取り込み、こちらで作品を起こした画像を作って uploads に置いてある。
		 * ここではその出来上がりを、作品本来の縦横比で並べるだけ。
		 *
		 * まだ生成できていない作品（同期直後・GDが無い等）は、写真全体を収めて表示する。
		 * 切り抜き位置がずれた絵を出すより、そのままの写真を出したほうがましなため。
		 */
		$rect  = bankofart_collection_rect_url( $art );
		$ratio = (float) ( $art['crop_ratio'] ?? 0 );
		?>
		<?php if ( '' !== $rect && $ratio > 0 ) : ?>
			<?php
			// 横長は幅を、縦長は高さを基準にする。基準を1つに決めれば比率は崩れない。
			$crop_wide = ( $ratio >= 1 );
			?>
			<span class="art-crop-frame<?php echo $crop_wide ? ' is-wide' : ''; ?>" style="aspect-ratio:<?php echo esc_attr( $ratio ); ?>;">
				<img class="art-crop-img" src="<?php echo esc_url( $rect ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" decoding="async">
			</span>
		<?php else : ?>
			<span class="art-image-inner"<?php if ( '' !== $image ) : ?> style="background-image:url('<?php echo esc_url( $image ); ?>');" role="img" aria-label="<?php echo esc_attr( $title ); ?>"<?php endif; ?>></span>
		<?php endif; ?>
	</div>

	<div class="art-info">
		<?php if ( '' !== $artist_name ) : ?>
			<div class="art-artist"><?php echo esc_html( $artist_name ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== $title ) : ?>
			<h3 class="art-title"><?php echo esc_html( $title ); ?></h3>
		<?php endif; ?>

		<?php if ( $specs ) : ?>
			<div class="art-meta">
				<?php foreach ( $specs as $spec ) : ?>
					<span<?php echo $spec['num'] ? ' class="boa-num"' : ''; ?>><?php echo esc_html( $spec['text'] ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</a>
