<?php
/**
 * 公認画家申請フォーム：定数・選択肢・共通ヘルパー
 *
 * 現行の form-mailer フォームを BOAデザインの自前WPフォームに置き換え、
 * Google スプレッドシート＋Drive（GAS Web App 経由）で受け取る。
 * 申請データは WordPress DB には保存しない（個人情報・契約情報は WP で管理しない方針）。
 * 取りこぼし防止として info@（BANKOFART_CONTACT_EMAIL）へテキスト項目のバックアップ通知も送る。
 *
 * セキュリティ：GAS の secret はサーバー側（本PHP）でのみ保持し、公開JSには一切出さない。
 * 画像の Base64 化と GAS への POST は必ずサーバー側から行う（クライアントから直接 GAS を叩かない）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * GAS Web App 連携（サーバー側のみで使用。フロントには出力しない）。
 * 値の差し替えは wp-config.php 等で同名定数を先に define すれば上書き可能。
 *
 * シークレットはリポジトリに置かない。以前はここに直書きしていたが、
 * リポジトリを見られると誰でも正規のリクエストを GAS へ投げられるため、
 * wp-config.php（git 管理外）で定義する方式へ移した。
 *
 *   define( 'BANKOFART_ARTIST_APP_GAS_SECRET', '……' );
 *
 * 未定義／空のときは GAS へは送らず、バックアップメールのみで受け付ける
 * （bankofart_artist_app_post_to_gas() が false を返す）。フォーム自体は動く。
 */
if ( ! defined( 'BANKOFART_ARTIST_APP_GAS_URL' ) ) {
	define( 'BANKOFART_ARTIST_APP_GAS_URL', 'https://script.google.com/macros/s/AKfycbzaBVzgYmX2c5N83jiztvge0iTjnz_3ChCWp2Ya7FjYm9xu9JY1q2WK8VTNGovwQdDX/exec' );
}
if ( ! defined( 'BANKOFART_ARTIST_APP_GAS_SECRET' ) ) {
	define( 'BANKOFART_ARTIST_APP_GAS_SECRET', '' );
}

/* 画像制限（GASへ Base64 で送るため過大にならないよう制御）。 */
if ( ! defined( 'BANKOFART_ARTIST_APP_MAX_IMAGE_BYTES' ) ) {
	define( 'BANKOFART_ARTIST_APP_MAX_IMAGE_BYTES', 5 * 1024 * 1024 ); // 1枚あたり 5MB。
}
if ( ! defined( 'BANKOFART_ARTIST_APP_MAX_TOTAL_BYTES' ) ) {
	define( 'BANKOFART_ARTIST_APP_MAX_TOTAL_BYTES', 20 * 1024 * 1024 ); // 全画像合計 20MB。
}
if ( ! defined( 'BANKOFART_ARTIST_APP_MAX_WORK_IMAGES' ) ) {
	define( 'BANKOFART_ARTIST_APP_MAX_WORK_IMAGES', 10 ); // 制作風景の上限枚数。
}

/** 許可する画像拡張子。 */
function bankofart_artist_app_allowed_ext() {
	return array( 'jpg', 'jpeg', 'png' );
}

/** 許可する画像MIME。 */
function bankofart_artist_app_allowed_mime() {
	return array( 'image/jpeg', 'image/png' );
}

/**
 * 都道府県（住所select用）。
 *
 * @return string[]
 */
function bankofart_artist_app_prefectures() {
	return array(
		'北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
		'茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
		'新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
		'岐阜県', '静岡県', '愛知県', '三重県',
		'滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
		'鳥取県', '島根県', '岡山県', '広島県', '山口県',
		'徳島県', '香川県', '愛媛県', '高知県',
		'福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
		'海外',
	);
}

/**
 * 性別（select用）。
 *
 * @return string[]
 */
function bankofart_artist_app_genders() {
	return array( '男性', '女性', 'その他', '回答しない' );
}

/**
 * 口座種別（select用）。
 *
 * @return string[]
 */
function bankofart_artist_app_account_types() {
	return array( '普通', '当座' );
}

/**
 * 申請者IPを取得する。
 *
 * @return string
 */
function bankofart_artist_app_get_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return substr( $ip, 0, 45 );
}

/**
 * IPレートリミット（同一IP・10分・3回まで）。
 *
 * @param string $ip IP。
 * @return bool ブロックすべきなら true。
 */
function bankofart_artist_app_is_rate_limited( $ip ) {
	$key   = 'boa_aa_rate_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= 3 ) {
		return true;
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}

/**
 * reCAPTCHA v3 検証。資料請求と同じ作り（キー未定義/空ならスキップ）。
 * 既存の bankofart_doc_request_verify_recaptcha を流用し、無ければ素通り。
 *
 * @param string $token g-recaptcha-response。
 * @return bool 合格なら true。
 */
function bankofart_artist_app_verify_recaptcha( $token ) {
	if ( function_exists( 'bankofart_doc_request_verify_recaptcha' ) ) {
		return bankofart_doc_request_verify_recaptcha( $token );
	}
	return true;
}
