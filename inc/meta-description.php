<?php
/**
 * meta description / og:description の自動生成（artist / art / collector）
 *
 * SEO SIMPLE PACK の標準 snippet タグは Meta Box フィールドを参照できないため、
 * テーマ側で各CPTの single ページの description を組み立てる。
 *
 * 【二重化回避：方法A】
 * SEO SIMPLE PACK が用意するフィルタ `ssp_output_description` /
 * `ssp_output_og_description` に乗って値を差し替える。プラグインの description
 * 生成パイプラインの内部で置換するため、<meta name="description"> と
 * <meta property="og:description"> が二重出力されることはない。
 *
 * 【個別手入力の優先】
 * 投稿ごとに SEO SIMPLE PACK の「Description of this page」
 * （post_meta: ssp_meta_description）が入力されている場合は、それを尊重して
 * テーマ生成では上書きしない。
 *
 * 【対象】
 * artist / art / collector の single ページのみ。archive / taxonomy は対象外。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 自動生成の対象CPTスラッグ。
 *
 * @var string[]
 */
const BANKOFART_META_DESC_POST_TYPES = array( 'artist', 'art', 'collector', 'journal', 'news' );

/**
 * description の最大文字数（全角換算）。超過分は切り詰めて「…」を付す。
 */
const BANKOFART_META_DESC_MAX = 120;

/**
 * SEO SIMPLE PACK の「個別 description」を保存する post_meta キー。
 * （プラグイン側 SSP_MetaBox::POST_META_KEYS['description'] と同値）
 */
const BANKOFART_SSP_DESC_META_KEY = 'ssp_meta_description';

/**
 * description フィルタの共通コールバック。
 *
 * 対象CPTの single 以外、または個別手入力 description がある場合は、
 * 受け取った値（プラグイン既定）をそのまま返す。
 *
 * @param string $description プラグインが生成した description。
 * @return string
 */
function bankofart_filter_meta_description( $description ) {
	if ( ! is_singular( BANKOFART_META_DESC_POST_TYPES ) ) {
		return $description;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return $description;
	}

	// 個別手入力 description があれば優先（テーマ生成で上書きしない）。
	$manual = get_post_meta( $post_id, BANKOFART_SSP_DESC_META_KEY, true );
	if ( '' !== trim( (string) $manual ) ) {
		return $description;
	}

	$generated = bankofart_generate_meta_description( $post_id );

	// 生成できなかった場合はプラグイン既定にフォールバック。
	return '' !== $generated ? $generated : $description;
}
add_filter( 'ssp_output_description', 'bankofart_filter_meta_description' );
add_filter( 'ssp_output_og_description', 'bankofart_filter_meta_description' );

/**
 * 投稿IDから description を組み立てる（CPT別ルーティング）。
 *
 * @param int $post_id 投稿ID。
 * @return string 整形済みプレーンテキスト（生成不可なら空文字）。
 */
function bankofart_generate_meta_description( $post_id ) {
	switch ( get_post_type( $post_id ) ) {
		case 'artist':
			$raw = bankofart_build_artist_description( $post_id );
			break;
		case 'art':
			$raw = bankofart_build_art_description( $post_id );
			break;
		case 'collector':
			$raw = bankofart_build_collector_description( $post_id );
			break;
		case 'journal':
			$raw = bankofart_build_journal_description( $post_id );
			break;
		case 'news':
			$raw = bankofart_build_news_description( $post_id );
			break;
		default:
			$raw = '';
	}

	return bankofart_normalize_description( $raw );
}

/**
 * ARTIST：
 *   {活動名}　BANK of ARTの公認アーティスト ― {制作テーマ13字}｜{制作テーマ詳細}
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bankofart_build_artist_description( $post_id ) {
	$name = get_the_title( $post_id );
	if ( '' === trim( $name ) ) {
		return '';
	}

	$base = sprintf( '%s　BANK of ARTの公認アーティスト', $name );

	$short = trim( (string) get_post_meta( $post_id, 'artist_theme_short', true ) );
	$long  = trim( (string) get_post_meta( $post_id, 'artist_theme_long', true ) );

	$theme_parts = array_filter( array( $short, $long ), 'strlen' );
	if ( ! empty( $theme_parts ) ) {
		$base .= ' ― ' . implode( '｜', $theme_parts );
	}

	return $base;
}

/**
 * ART：
 *   {作品名}｜{制作者}による{技法・ジャンル}作品。BANK of ARTの公認作品です。
 *   制作者・技法/ジャンルが取れない部分は自然に省略する。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bankofart_build_art_description( $post_id ) {
	$title = get_the_title( $post_id );
	if ( '' === trim( $title ) ) {
		return '';
	}

	// 制作者（artist_to_art の逆引き：先頭1名）。
	$artist_name = '';
	if ( function_exists( 'bankofart_get_connected' ) ) {
		$artists = bankofart_get_connected( 'artist_to_art', 'to', $post_id );
		if ( ! empty( $artists ) ) {
			$artist_name = trim( get_the_title( $artists[0]->ID ) );
		}
	}

	// 技法・ジャンル（タクソノミー term 名を結合）。
	$labels = array();
	foreach ( array( 'art_technique', 'art_genre' ) as $tax ) {
		$terms = get_the_terms( $post_id, $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$labels[] = $term->name;
			}
		}
	}
	$labels = array_unique( array_filter( $labels, 'strlen' ) );
	$style  = implode( '・', $labels );

	// 中間句「{制作者}による{技法・ジャンル}作品」を条件分岐で構築。
	if ( '' !== $artist_name && '' !== $style ) {
		$middle = sprintf( '%sによる%s作品。', $artist_name, $style );
	} elseif ( '' !== $artist_name ) {
		$middle = sprintf( '%sによる作品。', $artist_name );
	} elseif ( '' !== $style ) {
		$middle = sprintf( '%s作品。', $style );
	} else {
		$middle = '';
	}

	if ( '' !== $middle ) {
		return sprintf( '%s｜%sBANK of ARTの公認作品です。', $title, $middle );
	}

	return sprintf( '%s｜BANK of ARTの公認作品です。', $title );
}

/**
 * COLLECTOR：
 *   {name}様のアート導入事例。{アートを置いた変化（一文）}節税対策×画家支援のBANK of ART。
 *   変化（collector_change_summary）が空なら当該部を省略。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bankofart_build_collector_description( $post_id ) {
	$name = trim( get_the_title( $post_id ) );
	if ( '' === $name ) {
		return '';
	}

	// 「様」重複ガード：個人コレクター等でタイトル末尾が敬称（様/さん）の場合は
	// 除去してから「様」を付す（例：「青木様」→「青木様…」／「様様」にしない）。
	$name = preg_replace( '/(様|さん)$/u', '', $name );
	$name = trim( $name );
	if ( '' === $name ) {
		return '';
	}

	$effect = trim( (string) get_post_meta( $post_id, 'collector_change_summary', true ) );

	if ( '' !== $effect ) {
		// 変化文の末尾が句点でなければ補い、読点的な連結を避ける。
		if ( ! preg_match( '/[。．.！!？?]$/u', $effect ) ) {
			$effect .= '。';
		}
		return sprintf( '%s様のアート導入事例。%s節税対策×画家支援のBANK of ART。', $name, $effect );
	}

	return sprintf( '%s様のアート導入事例。節税対策×画家支援のBANK of ART。', $name );
}

/**
 * JOURNAL：
 *   第一優先＝要約（journal_summary）。空なら本文セクション（journal_sections）の冒頭。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bankofart_build_journal_description( $post_id ) {
	$summary = trim( (string) get_post_meta( $post_id, 'journal_summary', true ) );
	if ( '' !== $summary ) {
		return $summary;
	}

	return bankofart_first_section_body( $post_id, 'journal_sections' );
}

/**
 * NEWS：
 *   第一優先＝要約（news_summary）。空なら本文セクション（news_sections）の冒頭。
 *
 * @param int $post_id 投稿ID。
 * @return string
 */
function bankofart_build_news_description( $post_id ) {
	$summary = trim( (string) get_post_meta( $post_id, 'news_summary', true ) );
	if ( '' !== $summary ) {
		return $summary;
	}

	return bankofart_first_section_body( $post_id, 'news_sections' );
}

/**
 * 本文セクション（Meta Box clone group）の最初の本文（section_body）を取得する。
 *
 * news_sections / journal_sections は section_heading / section_body /
 * section_images を持つグループの繰り返し。最初に本文が入っているセクションの
 * section_body を返す（タグ除去・切り詰めは呼び出し側の normalize で行う）。
 *
 * @param int    $post_id   投稿ID。
 * @param string $group_key グループフィールドID（'news_sections' 等）。
 * @return string 見つからなければ空文字。
 */
function bankofart_first_section_body( $post_id, $group_key ) {
	$sections = function_exists( 'rwmb_meta' )
		? rwmb_meta( $group_key, '', $post_id )
		: get_post_meta( $post_id, $group_key, true );

	if ( empty( $sections ) || ! is_array( $sections ) ) {
		return '';
	}

	foreach ( $sections as $section ) {
		if ( ! is_array( $section ) || ! isset( $section['section_body'] ) ) {
			continue;
		}
		$body = (string) $section['section_body'];
		// タグ・空白を除いて中身があるセクションを採用。
		if ( ! bankofart_is_blank_html( $body ) ) {
			return $body;
		}
	}

	return '';
}

/**
 * description をプレーンテキスト化し、長さ制限を適用する。
 *
 * - HTMLタグ除去、改行・連続空白の単一化、トリム
 * - 全角120字を超えたら切り詰めて「…」を付与
 *
 * @param string $text 元テキスト。
 * @return string
 */
function bankofart_normalize_description( $text ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return '';
	}

	// ショートコード・HTMLタグを除去。
	$text = strip_shortcodes( $text );
	$text = wp_strip_all_tags( $text );

	// HTMLエンティティを実体へ（&amp; 等が description に残らないように）。
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

	// 改行・タブ・連続空白を半角スペース1個へ畳み、前後をトリム。
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = trim( $text );

	// 長さ制限（全角換算）。
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > BANKOFART_META_DESC_MAX ) {
		$text = mb_substr( $text, 0, BANKOFART_META_DESC_MAX - 1, 'UTF-8' ) . '…';
	}

	return $text;
}
