<?php
/**
 * アセット（CSS / JS）の読み込み。
 *
 * すべての CSS / JS はこのファイルで wp_enqueue_* する。
 * テンプレート（PHP）内に <link> / <script> を直書きしない。
 * ページ別アセットは条件分岐で必要なページにだけ読み込む。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * フロント側のアセットを読み込む。
 *
 * Phase 1（土台）では共通の tokens / reset / base と Google Fonts のみを登録する。
 * header.css / footer.css / components.css / pages/*.css / 各種 JS は、
 * 対応するファイルを作成した時点で順次このファイルに追記していく。
 *
 * @return void
 */
function bankofart_enqueue_assets() {
	$theme_uri = get_theme_file_uri();
	// CSS/JS の版番号。テーマ版＋assets配下の最終更新時刻を混ぜ、ファイルを編集すると
	// URL（?ver=）が変わってブラウザキャッシュが必ず破棄されるようにする（編集が即反映）。
	$ver = bankofart_assets_version();

	/*
	 * Google Fonts
	 * - Cormorant SC : 英大文字ディスプレイ（英字専用）
	 * - Cinzel       : 英字ラベル・小見出し
	 * - Shippori Mincho B1 : 日本語全般・数字
	 * バージョンは null（Google 側で管理されるため）。
	 */
	wp_enqueue_style(
		'bankofart-fonts-preconnect',
		'https://fonts.googleapis.com',
		array(),
		null
	);
	wp_enqueue_style(
		'bankofart-fonts',
		'https://fonts.googleapis.com/css2?family=Cormorant+SC:wght@400;500;700&family=Cinzel:wght@500;700&family=Shippori+Mincho+B1:wght@400;500;700;800&display=swap',
		array(),
		null
	);

	// 共通CSS（依存関係で読み込み順を保証する）。
	wp_enqueue_style( 'bankofart-tokens', "{$theme_uri}/assets/css/tokens.css", array(), $ver );
	wp_enqueue_style( 'bankofart-reset', "{$theme_uri}/assets/css/reset.css", array(), $ver );
	wp_enqueue_style( 'bankofart-base', "{$theme_uri}/assets/css/base.css", array( 'bankofart-tokens', 'bankofart-reset' ), $ver );
	wp_enqueue_style( 'bankofart-header', "{$theme_uri}/assets/css/header.css", array( 'bankofart-base' ), $ver );
	wp_enqueue_style( 'bankofart-footer', "{$theme_uri}/assets/css/footer.css", array( 'bankofart-base' ), $ver );
	// 再利用コンポーネント（カード・CTA等）。
	wp_enqueue_style( 'bankofart-components', "{$theme_uri}/assets/css/components.css", array( 'bankofart-base' ), $ver );
	// 共通JS（フッターで読み込み）。
	wp_enqueue_script( 'bankofart-header', "{$theme_uri}/assets/js/header.js", array(), $ver, true );

	// ファーストビューのローディング画面（TOPページのみ。header.php の出力条件と揃える）。
	if ( is_front_page() ) {
		wp_enqueue_style( 'bankofart-preloader', "{$theme_uri}/assets/css/preloader.css", array( 'bankofart-base' ), $ver );
		// #boaPreloader を参照するためフッター（body解析後）で読み込む。
		wp_enqueue_script( 'bankofart-preloader', "{$theme_uri}/assets/js/preloader.js", array(), $ver, true );
	}

	// 単一ページ共通インタラクション（ヒーロー切替・リビール・ライトボックス）。
	$single_detail_needed = ( is_singular( 'artist' ) || is_singular( 'art' ) || is_singular( 'collector' ) || is_singular( 'news' ) || is_singular( 'journal' )
		|| is_post_type_archive( 'news' ) || is_post_type_archive( 'journal' ) || is_post_type_archive( 'artist' ) || is_post_type_archive( 'collector' ) || is_post_type_archive( 'art' ) ); // アーカイブは .rv リビールに使用.
	if ( $single_detail_needed ) {
		wp_enqueue_script(
			'bankofart-single-detail',
			"{$theme_uri}/assets/js/single-detail.js",
			array(),
			$ver,
			true
		);
	}

	// ページ別アセット：単一アーティスト。
	if ( is_singular( 'artist' ) ) {
		wp_enqueue_style(
			'bankofart-single-artist',
			"{$theme_uri}/assets/css/pages/single-artist.css",
			array( 'bankofart-components' ),
			$ver
		);
	}

	// ページ別アセット：単一作品。
	if ( is_singular( 'art' ) ) {
		wp_enqueue_style(
			'bankofart-single-art',
			"{$theme_uri}/assets/css/pages/single-art.css",
			array( 'bankofart-components' ),
			$ver
		);
	}

	// ページ別アセット：単一 画家応援企業。
	if ( is_singular( 'collector' ) ) {
		wp_enqueue_style(
			'bankofart-single-collector',
			"{$theme_uri}/assets/css/pages/single-collector.css",
			array( 'bankofart-components' ),
			$ver
		);
	}

	// ページ別アセット：単一 NEWS。
	if ( is_singular( 'news' ) ) {
		wp_enqueue_style(
			'bankofart-single-news',
			"{$theme_uri}/assets/css/pages/single-news.css",
			array( 'bankofart-components' ),
			$ver
		);
	}

	// ページ別アセット：単一 JOURNAL。
	if ( is_singular( 'journal' ) ) {
		wp_enqueue_style(
			'bankofart-single-journal',
			"{$theme_uri}/assets/css/pages/single-journal.css",
			array( 'bankofart-components' ),
			$ver
		);
	}

	// ページ別アセット：NEWS / JOURNAL アーカイブ（共通CSS + フィルターJS）。
	if ( is_post_type_archive( 'news' ) || is_post_type_archive( 'journal' ) ) {
		wp_enqueue_style(
			'bankofart-archive-list',
			"{$theme_uri}/assets/css/pages/archive-list.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-archive-filter',
			"{$theme_uri}/assets/js/archive-filter.js",
			array(),
			$ver,
			true
		);
	}

	// ページ別アセット：ARTIST アーカイブ（CSS + 2軸ANDフィルターJS）。
	if ( is_post_type_archive( 'artist' ) ) {
		wp_enqueue_style(
			'bankofart-archive-artist',
			"{$theme_uri}/assets/css/pages/archive-artist.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-archive-paging',
			"{$theme_uri}/assets/js/archive-paging.js",
			array(),
			$ver,
			true
		);
		wp_enqueue_script(
			'bankofart-archive-artist-filter',
			"{$theme_uri}/assets/js/archive-artist-filter.js",
			array( 'bankofart-archive-paging' ),
			$ver,
			true
		);
	}

	// ページ別アセット：COLLECTOR アーカイブ（CSS + 1軸フィルターJS）。
	if ( is_post_type_archive( 'collector' ) ) {
		wp_enqueue_style(
			'bankofart-archive-collector',
			"{$theme_uri}/assets/css/pages/archive-collector.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-archive-paging',
			"{$theme_uri}/assets/js/archive-paging.js",
			array(),
			$ver,
			true
		);
		wp_enqueue_script(
			'bankofart-archive-collector-filter',
			"{$theme_uri}/assets/js/archive-collector-filter.js",
			array( 'bankofart-archive-paging' ),
			$ver,
			true
		);
	}

	// ページ別アセット：ABOUT 固定ページ（スラッグ about または ABOUT テンプレート）。
	if ( is_page( 'about' ) || is_page_template( 'page-about.php' ) ) {
		wp_enqueue_style(
			'bankofart-page-about',
			"{$theme_uri}/assets/css/pages/page-about.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-page-about',
			"{$theme_uri}/assets/js/page-about.js",
			array(),
			$ver,
			true
		);
		// コレクトシミュレーター（タブ式：即時償却 / 減価償却）。フッターで読み込み（DOM 要素の後）。
		wp_enqueue_script(
			'bankofart-collect-simulator',
			"{$theme_uri}/assets/js/collect-simulator.js",
			array(),
			$ver,
			true
		);
	}

	// ページ別アセット：MATCHING 企業理念診断（スラッグ matching-purpose または MATCHING テンプレート）。
	if ( is_page( 'matching-purpose' ) || is_page_template( 'page-matching-purpose.php' ) ) {
		wp_enqueue_style(
			'bankofart-page-matching-purpose',
			"{$theme_uri}/assets/css/pages/page-matching-purpose.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-page-matching-purpose',
			"{$theme_uri}/assets/js/page-matching-purpose.js",
			array(),
			$ver,
			true
		);
		// 診断データ供給（Notion仕様準拠・完全動的）。
		//   - questions：diagnosis-data.php の質問マスター（仕様2章）。
		//   - artists  ：artist 投稿から動的取得（仕様7.2。タグ・共鳴文章を入力すれば自動で母集団に追加）。
		// ハードコードは一切無し。スコアリング/同点処理/タグ被り補正は JS 側で実施。
		wp_localize_script(
			'bankofart-page-matching-purpose',
			'BOA_MATCH',
			array(
				'questions'   => bankofart_get_purpose_questions(),
				'artists'     => bankofart_get_matching_artists(),
				'archiveUrl'  => get_post_type_archive_link( 'artist' ),
				'briefingUrl' => bankofart_briefing_url(),
			)
		);
	}

	// ページ別アセット：プライバシーポリシー（固定ページ slug: privacy-policy）。
	if ( is_page( 'privacy-policy' ) ) {
		wp_enqueue_style(
			'bankofart-privacy-policy',
			"{$theme_uri}/assets/css/pages/privacy-policy.css",
			array( 'bankofart-base' ),
			$ver
		);
	}

	// ページ別アセット：資料請求フォーム＋完了画面。
	if ( is_page_template( 'page-document-request.php' ) || is_page_template( 'page-document-request-complete.php' ) || is_page( 'document-request' ) ) {
		wp_enqueue_style(
			'bankofart-document-request',
			"{$theme_uri}/assets/css/pages/document-request.css",
			array( 'bankofart-components' ),
			$ver
		);
		// フォームページのみ JS（バリデーション/二重送信防止/reCAPTCHA）。
		if ( is_page_template( 'page-document-request.php' ) || is_page( 'document-request' ) ) {
			$recaptcha_site = defined( 'BANKOFART_RECAPTCHA_SITE_KEY' ) ? constant( 'BANKOFART_RECAPTCHA_SITE_KEY' ) : '';
			if ( '' !== $recaptcha_site ) {
				// reCAPTCHA v3 本体（キー設定時のみ）。
				wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
			wp_enqueue_script(
				'bankofart-document-request',
				"{$theme_uri}/assets/js/document-request.js",
				array(),
				$ver,
				true
			);
			wp_localize_script(
				'bankofart-document-request',
				'BOA_DR',
				array( 'recaptchaSiteKey' => $recaptcha_site )
			);
		}
	}

	// ページ別アセット：オンライン説明会予約（Calendly風ウィザード）。
	if ( is_page( 'online-briefing' ) || is_page_template( 'page-online-briefing.php' ) ) {
		wp_enqueue_style(
			'bankofart-online-briefing',
			"{$theme_uri}/assets/css/pages/online-briefing.css",
			array( 'bankofart-components' ),
			$ver
		);
		$recaptcha_site = defined( 'BANKOFART_RECAPTCHA_SITE_KEY' ) ? constant( 'BANKOFART_RECAPTCHA_SITE_KEY' ) : '';
		if ( '' !== $recaptcha_site ) {
			wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_script(
			'bankofart-online-briefing',
			"{$theme_uri}/assets/js/online-briefing.js",
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'bankofart-online-briefing',
			'BOA_BOOKING',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'boa_booking' ),
				'today'            => current_time( 'Y-m-d' ),
				'maxDate'          => gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +' . BANKOFART_BOOKING_DAYS_AHEAD . ' days' ) ),
				'recaptchaSiteKey' => $recaptcha_site,
			)
		);
	}

	// ページ別アセット：公認画家申請フォーム（テンプレート割り当てページ）。
	if ( is_page_template( 'page-artist-application.php' ) ) {
		wp_enqueue_style(
			'bankofart-artist-application',
			"{$theme_uri}/assets/css/pages/artist-application.css",
			array( 'bankofart-components' ),
			$ver
		);
		$recaptcha_site = defined( 'BANKOFART_RECAPTCHA_SITE_KEY' ) ? constant( 'BANKOFART_RECAPTCHA_SITE_KEY' ) : '';
		if ( '' !== $recaptcha_site ) {
			wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_script(
			'bankofart-artist-application',
			"{$theme_uri}/assets/js/artist-application.js",
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'bankofart-artist-application',
			'BOA_AA',
			array(
				'recaptchaSiteKey' => $recaptcha_site,
				'maxImageMB'       => (int) ( BANKOFART_ARTIST_APP_MAX_IMAGE_BYTES / 1024 / 1024 ),
				'maxTotalMB'       => (int) ( BANKOFART_ARTIST_APP_MAX_TOTAL_BYTES / 1024 / 1024 ),
				'maxWorkImages'    => (int) BANKOFART_ARTIST_APP_MAX_WORK_IMAGES,
			)
		);
	}

	// ページ別アセット：画家応募フォーム（artist-application.css を共用）。
	if ( is_page_template( 'page-artist-entry.php' ) ) {
		wp_enqueue_style(
			'bankofart-artist-application',
			"{$theme_uri}/assets/css/pages/artist-application.css",
			array( 'bankofart-components' ),
			$ver
		);
		$recaptcha_site = defined( 'BANKOFART_RECAPTCHA_SITE_KEY' ) ? constant( 'BANKOFART_RECAPTCHA_SITE_KEY' ) : '';
		if ( '' !== $recaptcha_site ) {
			wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_site ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_script(
			'bankofart-artist-entry',
			"{$theme_uri}/assets/js/artist-entry.js",
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'bankofart-artist-entry',
			'BOA_AE',
			array(
				'recaptchaSiteKey' => $recaptcha_site,
				'maxPdfMB'         => (int) ( BANKOFART_ARTIST_ENTRY_MAX_PDF_BYTES / 1024 / 1024 ),
			)
		);
	}

	// ページ別アセット：MATCHING ISSUE 課題逆引き診断（スラッグ matching-issue または テンプレート）。
	if ( is_page( 'matching-issue' ) || is_page_template( 'page-matching-issue.php' ) ) {
		wp_enqueue_style(
			'bankofart-page-matching-issue',
			"{$theme_uri}/assets/css/pages/page-matching-issue.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-page-matching-issue',
			"{$theme_uri}/assets/js/page-matching-issue.js",
			array(),
			$ver,
			true
		);
		// 診断データ供給（Notion仕様準拠・完全動的）。質問/効用タイプ/対応表＝diagnosis-data.php、
		// アーティスト・コレクターは投稿から動的取得。判定/推薦/記事抽出は JS。
		wp_localize_script(
			'bankofart-page-matching-issue',
			'BOA_ISSUE',
			array(
				'questions'   => bankofart_get_issue_questions(),
				'effectTypes' => bankofart_get_effect_types(),
				'effectMap'   => bankofart_get_effect_to_artist_tag_map(),
				'artists'     => bankofart_get_matching_artists(),
				'collectors'  => bankofart_get_matching_collectors(),
				'briefingUrl' => bankofart_briefing_url(),
			)
		);
	}

	// ページ別アセット：RECRUIT 固定ページ（スラッグ recruit または RECRUIT テンプレート）。
	if ( is_page( 'recruit' ) || is_page_template( 'page-recruit.php' ) ) {
		wp_enqueue_style(
			'bankofart-page-recruit',
			"{$theme_uri}/assets/css/pages/page-recruit.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-page-recruit',
			"{$theme_uri}/assets/js/page-recruit.js",
			array(),
			$ver,
			true
		);
	}

	// ページ別アセット：フロントページ（TOP）。
	if ( is_front_page() ) {
		wp_enqueue_style(
			'bankofart-front-page',
			"{$theme_uri}/assets/css/pages/front-page.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-front-page',
			"{$theme_uri}/assets/js/front-page.js",
			array(),
			$ver,
			true
		);
		// ヒーローの画像スライドショー（依存なし・slideshow時のみ実働）。
		wp_enqueue_script(
			'bankofart-hero-slideshow',
			"{$theme_uri}/assets/js/hero-slideshow.js",
			array(),
			$ver,
			true
		);
	}

	// ページ別アセット：ART アーカイブ（CSS + 7軸AND+ソートJS）。
	if ( is_post_type_archive( 'art' ) ) {
		wp_enqueue_style(
			'bankofart-archive-art',
			"{$theme_uri}/assets/css/pages/archive-art.css",
			array( 'bankofart-components' ),
			$ver
		);
		wp_enqueue_script(
			'bankofart-archive-paging',
			"{$theme_uri}/assets/js/archive-paging.js",
			array(),
			$ver,
			true
		);
		wp_enqueue_script(
			'bankofart-archive-art-filter',
			"{$theme_uri}/assets/js/archive-art-filter.js",
			array( 'bankofart-archive-paging' ),
			$ver,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'bankofart_enqueue_assets' );

/**
 * Google Fonts への接続を高速化するための preconnect / crossorigin を付与。
 *
 * wp_enqueue_style では crossorigin 属性を付けられないため、フィルターで補う。
 *
 * @param string $html   生成された link タグ。
 * @param string $handle スタイルのハンドル名。
 * @return string
 */
function bankofart_fonts_preconnect( $html, $handle ) {
	if ( 'bankofart-fonts-preconnect' === $handle ) {
		// preconnect として出力（gstatic 向け crossorigin も併記）。
		$html  = '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
		$html .= '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
	}
	return $html;
}
add_filter( 'style_loader_tag', 'bankofart_fonts_preconnect', 10, 2 );

/**
 * アセット版番号を生成する。
 *
 * テーマ版に assets/css・assets/js 配下の「最終更新時刻」を付与して返す。
 * これにより CSS/JS を編集すると enqueue の ?ver= が変化し、ブラウザ／中間キャッシュが
 * 確実に破棄されて変更が即座に反映される（開発中の「編集が反映されない」事故を防ぐ）。
 *
 * 走査対象は css/js のみ（画像は対象外）で件数が少なく軽量。リクエスト内で静的キャッシュ。
 * 本番でファイル更新が止まったら、固定版番号へ切り替えても良い。
 *
 * @return string 版番号（例: 1.0.0.1718000000）。
 */
function bankofart_assets_version() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$base   = defined( 'BANKOFART_VERSION' ) ? BANKOFART_VERSION : wp_get_theme()->get( 'Version' );
	$latest = 0;

	foreach ( array( 'assets/css', 'assets/js' ) as $dir ) {
		$path = get_theme_file_path( $dir );
		if ( ! is_dir( $path ) ) {
			continue;
		}
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
			);
			foreach ( $iterator as $file ) {
				if ( $file->isFile() ) {
					$mtime = $file->getMTime();
					if ( $mtime > $latest ) {
						$latest = $mtime;
					}
				}
			}
		} catch ( Exception $e ) {
			// 走査に失敗してもテーマ版で動作継続。
			$latest = 0;
		}
	}

	$cached = $latest ? ( $base . '.' . $latest ) : (string) $base;
	return $cached;
}
