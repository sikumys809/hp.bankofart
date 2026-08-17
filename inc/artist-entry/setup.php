<?php
/**
 * 画家応募フォーム（選考用）：定数・選択肢・共通ヘルパー
 *
 * 「公認画家申請フォーム（page-artist-application）」の仕組みを流用した“応募（選考）”用フォーム。
 * 申請フォームとの違い：口座・本名管理情報は集めない／公開ページ（/artist-entry/）／連携GASは別エンドポイント。
 * 応募データは WordPress DB には保存せず、Google スプレッドシート＋Drive（GAS Web App）で受け取り、
 * 取りこぼし防止に info@（BANKOFART_CONTACT_EMAIL）へ応募通知のバックアップメールも送る。
 *
 * セキュリティ：GAS secret はサーバー側（本PHP）のみで保持し公開JSには出さない。
 * PDF の Base64 化と GAS への POST は必ずサーバー側から行う（クライアントから直接 GAS を叩かない）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 応募用 GAS Web App 連携（サーバー側のみで使用。フロントには出力しない）。
 * 申請フォームとは別エンドポイント。wp-config.php 等で同名定数を先に define すれば上書き可能。
 *
 * シークレットはリポジトリに置かない（理由は inc/artist-application/setup.php を参照）。
 *
 *   define( 'BANKOFART_ARTIST_ENTRY_GAS_SECRET', '……' );
 *
 * 未定義／空のときは GAS へは送らず、バックアップメールのみで受け付ける。
 */
if ( ! defined( 'BANKOFART_ARTIST_ENTRY_GAS_URL' ) ) {
	define( 'BANKOFART_ARTIST_ENTRY_GAS_URL', 'https://script.google.com/macros/s/AKfycbxnwV40z8A-GSLIzQZpb2Z-d7SceOoeF6cvjGQWGVOh8_gjbHdpUTeRipyS_-31uu3v/exec' );
}
if ( ! defined( 'BANKOFART_ARTIST_ENTRY_GAS_SECRET' ) ) {
	define( 'BANKOFART_ARTIST_ENTRY_GAS_SECRET', '' );
}

/* ポートフォリオPDFの制限。 */
if ( ! defined( 'BANKOFART_ARTIST_ENTRY_MAX_PDF_BYTES' ) ) {
	define( 'BANKOFART_ARTIST_ENTRY_MAX_PDF_BYTES', 10 * 1024 * 1024 ); // 10MB。
}

/** 許可する拡張子（ポートフォリオ）。 */
function bankofart_artist_entry_allowed_ext() {
	return array( 'pdf' );
}

/** 許可するMIME（ポートフォリオ）。 */
function bankofart_artist_entry_allowed_mime() {
	return array( 'application/pdf', 'application/x-pdf' );
}

/**
 * 直近1ヶ月の制作ペース（select 4択）。Tally（tally.so/r/MeMEV0）準拠。
 *
 * @return string[]
 */
function bankofart_artist_entry_pace_options() {
	return array(
		'1点以下',
		'～3点',
		'3～7点',
		'10点以上',
	);
}

/**
 * 現在の主な収入源（select 4択）。Tally（tally.so/r/MeMEV0）準拠。
 *
 * @return string[]
 */
function bankofart_artist_entry_income_options() {
	return array(
		'絵で生計を立てている',
		'別の職業がある',
		'学生',
		'その他',
	);
}

/**
 * 応募者IPを取得する。
 *
 * @return string
 */
function bankofart_artist_entry_get_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( $ip, 0, 45 );
}

/**
 * IPレートリミット（同一IP・10分・3回まで）。
 *
 * @param string $ip IP。
 * @return bool ブロックすべきなら true。
 */
function bankofart_artist_entry_is_rate_limited( $ip ) {
	$key   = 'boa_ae_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 3 ) {
		return true;
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * reCAPTCHA v3 検証。申請フォームと同じ作り（キー未定義/空ならスキップ）。
 *
 * @param string $token g-recaptcha-response。
 * @return bool 合格なら true。
 */
function bankofart_artist_entry_verify_recaptcha( $token ) {
	if ( function_exists( 'bankofart_doc_request_verify_recaptcha' ) ) {
		return bankofart_doc_request_verify_recaptcha( $token );
	}
	return true;
}
