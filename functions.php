<?php
/**
 * Bank of Art テーマ functions.php
 *
 * このファイルは最小構成に留め、機能は inc/ 配下に分割して require する。
 * 各 inc ファイルは bankofart_ プレフィックスの関数とフックで完結させる。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセス禁止
}

/**
 * テーマのバージョン・パス定数。
 */
define( 'BANKOFART_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'BANKOFART_DIR', get_theme_file_path() );
define( 'BANKOFART_URI', get_theme_file_uri() );

/**
 * inc/ 配下の分割ファイルを読み込む。
 *
 * 現時点（Phase 1 土台）で存在するファイルのみを列挙する。
 * 後続フェーズで post-types.php / taxonomies.php / meta-box-fields.php /
 * relationships.php / customizer.php / shortcodes.php / ajax-handlers.php /
 * helpers.php を追加していく。
 *
 * @var string[] $bankofart_includes
 */
$bankofart_includes = array(
	'inc/theme-setup.php',      // add_theme_support 等の基本設定
	'inc/enqueue.php',          // CSS / JS の読み込み
	'inc/post-types.php',       // カスタム投稿タイプ登録
	'inc/taxonomies.php',       // カスタムタクソノミー登録
	'inc/meta-box-fields.php',  // Meta Box フィールド定義（要 Meta Box AIO）
	'inc/section-display-guard.php', // セクション表示スイッチの未設定→'1'補完（再発防止）
	'inc/auto-featured-image.php',   // メイン画像→アイキャッチ自動設定（保存時／OGP個別化）
	'inc/meta-description.php',       // artist/art/collector の meta/og description 自動生成（SEO SIMPLE PACK フィルタ）
	'inc/structured-data.php',       // JSON-LD（schema.org）自動出力（wp_head / @graph）
	'inc/term-meta-fields.php', // MB Term Meta（art_main_color のカラー情報）
	'inc/relationships.php',    // MB Relationships 定義（要 Meta Box AIO）
	'inc/customizer.php',       // WP カスタマイザー（サイト数値等）
	'inc/resale-waitlist.php',  // リセール待機リスト（テーブル/フォーム送信/管理画面）
	'inc/diagnosis-data.php',   // マッチング診断データ（PHP配列）
	'inc/helpers.php',          // テンプレート用ヘルパー（セクション可視性判定等）
	'inc/document-request/setup.php',        // 資料請求：テーブル/定数
	'inc/document-request/helpers.php',      // 資料請求：トークン/レート/検証/挿入
	'inc/document-request/form-handler.php', // 資料請求：送信処理・PDF配信
	'inc/document-request/mail.php',         // 資料請求：メール送信
	'inc/online-booking/setup.php',          // 説明会予約：テーブル/定数/スロット
	'inc/online-booking/availability.php',   // 説明会予約：空きスロット（admin-ajax）
	'inc/online-booking/form-handler.php',   // 説明会予約：予約確定（admin-post）
	'inc/online-booking/mail.php',           // 説明会予約：メール送信
	'inc/online-booking/gcal.php',           // 説明会予約：Google Calendar/Meet連携（フェーズ2）
	'inc/artist-application/setup.php',      // 公認画家申請：定数/選択肢/共通ヘルパー
	'inc/artist-application/form-handler.php', // 公認画家申請：送信処理（GAS連携＋バックアップメール）
	'inc/artist-entry/setup.php',            // 画家応募（選考用）：定数/選択肢/共通ヘルパー
	'inc/artist-entry/form-handler.php',     // 画家応募（選考用）：送信処理（GAS連携＋バックアップメール）
);

foreach ( $bankofart_includes as $bankofart_file ) {
	$bankofart_path = get_theme_file_path( $bankofart_file );

	if ( is_readable( $bankofart_path ) ) {
		require_once $bankofart_path;
	} else {
		// 開発中の読み込み漏れに気付けるよう、管理者にのみ通知する。
		add_action(
			'admin_notices',
			function () use ( $bankofart_file ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: 読み込めなかったファイルのパス */
							__( 'Bank of Art テーマ: 必要なファイルを読み込めませんでした: %s', 'bankofart' ),
							$bankofart_file
						)
					)
				);
			}
		);
	}
}

unset( $bankofart_includes, $bankofart_file, $bankofart_path );

// 管理画面でのみ読み込むファイル（資料請求の管理画面／CSV出力・CSVインポート）。
if ( is_admin() ) {
	$bankofart_admin_only = array(
		'inc/document-request/admin.php',
		'inc/document-request/csv-export.php',
		'inc/csv-import/importer.php', // CSV取込：読み込み・マッピング・取り込み処理。
		'inc/csv-import/admin.php',    // CSV取込：管理メニュー（importer.php の後に読む）。
		// JOURNAL/NEWS のJSON取込。重複キー定数を csv-import/importer.php の const から
		// 流用するため、必ず importer.php より「後」に読み込む（定数の二重定義を避ける）。
		'inc/journal-import/admin.php',
		'inc/art-series/admin.php',    // 作品シリーズ：編集画面での共通項目の自動入力。
	);
	foreach ( $bankofart_admin_only as $bankofart_admin_file ) {
		$bankofart_admin_path = get_theme_file_path( $bankofart_admin_file );
		if ( is_readable( $bankofart_admin_path ) ) {
			require_once $bankofart_admin_path;
		}
	}
	unset( $bankofart_admin_only, $bankofart_admin_file, $bankofart_admin_path );
}
