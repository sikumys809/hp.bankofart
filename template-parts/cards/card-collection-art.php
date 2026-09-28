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
		 * 作品の切り抜きは collection 側で手作業されており、切り抜き済み画像は配信されない。
		 * 代わりに「枠の縦横比」と「元写真の拡大率・ずらし量」が数値で提供されるので、
		 * それを同期時に取り込んである。ここで同じ指定を組み立てて、向こうと同じ絵を出す。
		 * 数値が無い作品（構造変更などで拾えなかった場合）は、写真全体を収めて表示する。
		 */
		$has_crop = ( null !== ( $art['crop_ratio'] ?? null ) && (float) $art['crop_ratio'] > 0 );
		?>
		<?php if ( $has_crop && '' !== $image ) : ?>
			<span class="art-crop-frame" style="aspect-ratio:<?php echo esc_attr( (float) $art['crop_ratio'] ); ?>;">
				<span class="art-crop-img" role="img" aria-label="<?php echo esc_attr( $title ); ?>"
					style="background-image:url('<?php echo esc_url( $image ); ?>');
						left:<?php echo esc_attr( (float) $art['crop_left'] ); ?>%;
						top:<?php echo esc_attr( (float) $art['crop_top'] ); ?>%;
						width:<?php echo esc_attr( (float) $art['crop_width'] ); ?>%;
						height:<?php echo esc_attr( (float) $art['crop_height'] ); ?>%;"></span>
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
