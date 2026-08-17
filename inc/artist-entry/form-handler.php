<?php
/**
 * 画家応募フォーム：送信処理（admin-post）
 *
 * 流れ：nonce → ハニーポット → reCAPTCHA → レートリミット → バリデーション
 *   → ポートフォリオPDFを Base64 化（サーバー側）→ 応募用 GAS Web App へ POST
 *   → info@ へバックアップ通知メール（GAS成否に関わらず）→ 完了画面へ PRG リダイレクト。
 *
 * GAS は POST で doPost 実行後 302 で結果URL(echo)へ返すため、redirection=0 で受けて
 * Location を GET し {"ok":true} を判定する（申請フォームと同じ作法）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 応募送信を処理する。
 *
 * @return void
 */
function bankofart_handle_artist_entry() {
	check_admin_referer( 'boa_artist_entry', 'boa_artist_entry_nonce' );

	$raw_redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
	$redirect     = wp_validate_redirect( $raw_redirect, home_url( '/' ) );

	// ハニーポット。
	if ( ! empty( $_POST['website_hp'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	// reCAPTCHA（キー未定義ならスキップ）。
	$recaptcha = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
	if ( ! bankofart_artist_entry_verify_recaptcha( $recaptcha ) ) {
		wp_safe_redirect( add_query_arg( 'entry_error', 'recaptcha', $redirect ) );
		exit;
	}

	// レートリミット。
	if ( bankofart_artist_entry_is_rate_limited( bankofart_artist_entry_get_ip() ) ) {
		wp_safe_redirect( add_query_arg( 'entry_error', 'rate_limit', $redirect ) );
		exit;
	}

	$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$text = bankofart_artist_entry_collect_text( $post );

	// バリデーション（テキスト）。
	$errors = bankofart_artist_entry_validate( $post );

	// ポートフォリオPDF（Base64化＋検証）。
	$pdf      = null;
	$pdf_errs = array();
	bankofart_artist_entry_process_pdf( $pdf, $pdf_errs );
	$errors = array_merge( $errors, $pdf_errs );

	if ( ! empty( $errors ) ) {
		$key = 'boa_ae_' . wp_generate_password( 12, false );
		set_transient(
			$key,
			array(
				'errors' => $errors,
				'values' => $text,
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'entry_error', $key, $redirect ) );
		exit;
	}

	// 応募用 GAS へ POST。
	$gas_ok = bankofart_artist_entry_post_to_gas( $text, $pdf );

	// info@ へバックアップ通知（GAS成否に関わらず必ず送る）。
	bankofart_artist_entry_send_backup_mail( $text, $pdf, $gas_ok );

	// 完了画面へ PRG。
	$thanks_key = 'boa_ae_done_' . wp_generate_password( 12, false );
	set_transient(
		$thanks_key,
		array(
			'artist_name' => $text['artist_name'],
			'gas_ok'      => $gas_ok ? 1 : 0,
		),
		30 * MINUTE_IN_SECONDS
	);
	wp_safe_redirect( add_query_arg( 'entry_thanks', $thanks_key, $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_boa_artist_entry', 'bankofart_handle_artist_entry' );
add_action( 'admin_post_boa_artist_entry', 'bankofart_handle_artist_entry' );

/**
 * テキスト項目を取得・サニタイズする（キー＝GASパラメータ名）。
 *
 * @param array $post wp_unslash 済み $_POST。
 * @return array
 */
function bankofart_artist_entry_collect_text( $post ) {
	$t = function ( $k ) use ( $post ) {
		return isset( $post[ $k ] ) ? sanitize_text_field( $post[ $k ] ) : '';
	};
	$a = function ( $k ) use ( $post ) {
		return isset( $post[ $k ] ) ? sanitize_textarea_field( $post[ $k ] ) : '';
	};

	return array(
		'name'             => $t( 'name' ),
		'name_kana'        => $t( 'name_kana' ),
		'artist_name'      => $t( 'artist_name' ),
		'age'              => $t( 'age' ),
		'email'            => isset( $post['email'] ) ? sanitize_email( $post['email'] ) : '',
		'phone'            => $t( 'phone' ),
		'base'             => $t( 'base' ),
		'career'           => $a( 'career' ),
		'exhibitions'      => $a( 'exhibitions' ),
		'awards'           => $a( 'awards' ),
		'why_paint'        => $a( 'why_paint' ),
		'origin'           => $a( 'origin' ),
		'future'           => $a( 'future' ),
		'boa_relation'     => $a( 'boa_relation' ),
		'pace'             => $t( 'pace' ),
		'income'           => $t( 'income' ),
		'sns'              => isset( $post['sns'] ) ? esc_url_raw( trim( (string) $post['sns'] ) ) : '',
		'agree_guidelines' => ! empty( $post['agree_guidelines'] ) ? '1' : '',
		'agree_privacy'    => ! empty( $post['agree_privacy'] ) ? '1' : '',
	);
}

/**
 * テキスト項目のバリデーション。
 *
 * @param array $post wp_unslash 済み $_POST。
 * @return array エラーメッセージ配列。
 */
function bankofart_artist_entry_validate( $post ) {
	$errors = array();
	$req    = array(
		'name'        => 'お名前',
		'name_kana'   => 'フリガナ',
		'artist_name' => 'アーティスト名',
		'age'         => '年齢',
		'phone'       => '電話番号',
		'base'        => '制作拠点',
		'why_paint'   => 'なぜ絵を描くか',
		'origin'      => '画家としての起源',
		'future'      => 'どんな画家になりたいか',
		'pace'        => '直近1ヶ月の制作ペース',
		'income'      => '現在の主な収入源',
	);
	foreach ( $req as $k => $label ) {
		if ( '' === trim( (string) ( isset( $post[ $k ] ) ? $post[ $k ] : '' ) ) ) {
			$errors[ $k ] = $label . 'をご入力ください。';
		}
	}

	$email = isset( $post['email'] ) ? trim( (string) $post['email'] ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		$errors['email'] = '有効なメールアドレスをご入力ください。';
	}

	$age = isset( $post['age'] ) ? trim( (string) $post['age'] ) : '';
	if ( '' !== $age && preg_match( '/[^0-9]/', $age ) ) {
		$errors['age'] = '年齢は数字でご入力ください。';
	}

	$phone = isset( $post['phone'] ) ? trim( (string) $post['phone'] ) : '';
	if ( '' !== $phone && preg_match( '/[^0-9\-]/', $phone ) ) {
		$errors['phone'] = '電話番号は数字とハイフンでご入力ください。';
	}

	if ( empty( $post['agree_guidelines'] ) ) {
		$errors['agree_guidelines'] = '募集要項の内容のご確認・同意が必要です。';
	}
	if ( empty( $post['agree_privacy'] ) ) {
		$errors['agree_privacy'] = '個人情報の取り扱いへの同意が必要です。';
	}

	return $errors;
}

/**
 * ポートフォリオPDFを検証して Base64 化する。
 *
 * @param array $pdf    出力：array( 'b64'=>..., 'name'=>... ) または null。
 * @param array $errors 出力：エラーメッセージ配列。
 * @return void
 */
function bankofart_artist_entry_process_pdf( &$pdf, &$errors ) {
	$pdf = null;

	if ( empty( $_FILES['portfolio_file'] ) || ! isset( $_FILES['portfolio_file']['error'] ) ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFを添付してください。';
		return;
	}
	$err = (int) $_FILES['portfolio_file']['error'];
	if ( UPLOAD_ERR_NO_FILE === $err ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFを添付してください。';
		return;
	}
	if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFのファイルサイズが大きすぎます。';
		return;
	}
	if ( UPLOAD_ERR_OK !== $err ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFのアップロードに失敗しました。';
		return;
	}

	$file = $_FILES['portfolio_file'];
	$tmp  = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFのアップロードを確認できませんでした。';
		return;
	}

	$size = (int) ( isset( $file['size'] ) ? $file['size'] : filesize( $tmp ) );
	if ( $size <= 0 ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFのファイルが空です。';
		return;
	}
	if ( $size > BANKOFART_ARTIST_ENTRY_MAX_PDF_BYTES ) {
		$errors['portfolio_file'] = sprintf( 'ポートフォリオPDFは %dMB 以内にしてください。', (int) ( BANKOFART_ARTIST_ENTRY_MAX_PDF_BYTES / 1024 / 1024 ) );
		return;
	}

	$name = sanitize_file_name( isset( $file['name'] ) ? $file['name'] : 'portfolio.pdf' );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, bankofart_artist_entry_allowed_ext(), true ) ) {
		$errors['portfolio_file'] = 'ポートフォリオは PDF 形式でアップロードしてください。';
		return;
	}

	// 実体MIMEチェック（拡張子偽装対策）。
	$check = wp_check_filetype_and_ext( $tmp, $name );
	$mime  = ! empty( $check['type'] ) ? $check['type'] : '';
	if ( '' === $mime ) {
		$finfo = function_exists( 'finfo_open' ) ? finfo_open( FILEINFO_MIME_TYPE ) : false;
		if ( $finfo ) {
			$mime = (string) finfo_file( $finfo, $tmp );
			finfo_close( $finfo );
		}
	}
	if ( ! in_array( $mime, bankofart_artist_entry_allowed_mime(), true ) ) {
		$errors['portfolio_file'] = 'ポートフォリオが PDF ファイルとして認識できませんでした。';
		return;
	}

	$bin = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $bin ) {
		$errors['portfolio_file'] = 'ポートフォリオPDFの読み込みに失敗しました。';
		return;
	}

	$pdf = array(
		'b64'  => base64_encode( $bin ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- GAS連携の転送用。
		'name' => $name,
	);
}

/**
 * 応募用 GAS Web App へ POST する（テキスト＋Base64 PDF）。
 *
 * @param array      $text テキスト項目（GASパラメータ名キー）。
 * @param array|null $pdf  ポートフォリオPDF。
 * @return bool 送信成功（{"ok":true}）なら true。
 */
function bankofart_artist_entry_post_to_gas( $text, $pdf ) {
	// シークレット未設定なら送らない（呼び出し側がバックアップメールで担保する）。
	if ( '' === (string) BANKOFART_ARTIST_ENTRY_GAS_SECRET ) {
		return false;
	}

	$body = array_merge(
		$text,
		array(
			'secret'             => BANKOFART_ARTIST_ENTRY_GAS_SECRET,
			'portfolio_file'     => isset( $pdf['b64'] ) ? $pdf['b64'] : '',
			'portfolio_file_name'=> isset( $pdf['name'] ) ? $pdf['name'] : '',
		)
	);

	$res = wp_remote_post(
		BANKOFART_ARTIST_ENTRY_GAS_URL,
		array(
			'timeout'     => 45,
			'redirection' => 0,
			'body'        => $body,
		)
	);
	if ( is_wp_error( $res ) ) {
		return false;
	}

	$code      = (int) wp_remote_retrieve_response_code( $res );
	$resp_body = (string) wp_remote_retrieve_body( $res );

	// 302 の場合は Location（echo URL）を GET して結果 JSON を取得する。
	if ( $code >= 300 && $code < 400 ) {
		$location = wp_remote_retrieve_header( $res, 'location' );
		if ( ! empty( $location ) ) {
			$res2 = wp_remote_get( $location, array( 'timeout' => 45 ) );
			if ( ! is_wp_error( $res2 ) ) {
				$code      = (int) wp_remote_retrieve_response_code( $res2 );
				$resp_body = (string) wp_remote_retrieve_body( $res2 );
			}
		}
	}

	if ( $code < 200 || $code >= 300 ) {
		return false;
	}

	$data = json_decode( $resp_body, true );
	return ( is_array( $data ) && ! empty( $data['ok'] ) );
}

/**
 * info@ 宛の応募通知バックアップメール（テキスト項目のみ。PDFはスプシ/Drive参照）。
 *
 * @param array      $text   テキスト項目。
 * @param array|null $pdf    ポートフォリオPDF（有無の記載用）。
 * @param bool       $gas_ok GAS送信成否。
 * @return void
 */
function bankofart_artist_entry_send_backup_mail( $text, $pdf, $gas_ok ) {
	$to      = defined( 'BANKOFART_CONTACT_EMAIL' ) ? BANKOFART_CONTACT_EMAIL : get_option( 'admin_email' );
	$headers = function_exists( 'bankofart_mail_headers' ) ? bankofart_mail_headers() : array( 'Content-Type: text/plain; charset=UTF-8' );

	$subject = sprintf( '【画家応募】%s（%s）様', $text['artist_name'], $text['name'] );

	$lines = array(
		'画家応募フォームから新しい応募がありました。',
		( $gas_ok ? '※ Googleスプレッドシート／Drive への保存：成功' : '※ Googleスプレッドシートへの保存に失敗した可能性があります。本メールの内容で対応してください。' ),
		'',
		'━━ 基本情報 ━━',
		'お名前　　：' . $text['name'],
		'フリガナ　：' . $text['name_kana'],
		'アーティスト名：' . $text['artist_name'],
		'年齢　　　：' . $text['age'],
		'メール　　：' . $text['email'],
		'電話　　　：' . $text['phone'],
		'制作拠点　：' . $text['base'],
		'経歴　　　：' . "\n" . $text['career'],
		'展示歴　　：' . "\n" . $text['exhibitions'],
		'受賞歴　　：' . "\n" . $text['awards'],
		'',
		'━━ あなたについて ━━',
		'なぜ絵を描くか：' . "\n" . $text['why_paint'],
		'画家としての起源：' . "\n" . $text['origin'],
		'どんな画家になりたいか：' . "\n" . $text['future'],
		'BOAとどう関わりたいか：' . "\n" . $text['boa_relation'],
		'',
		'━━ 制作活動 ━━',
		'直近1ヶ月の制作ペース：' . $text['pace'],
		'現在の主な収入源：' . $text['income'],
		'SNS・ウェブサイト：' . $text['sns'],
		'',
		'━━ ポートフォリオ ━━',
		( $pdf ? 'PDF：1点（ファイル名：' . $pdf['name'] . '）' : 'PDF：なし' ),
		'※ PDFの実体は Googleスプレッドシート／Drive をご参照ください。',
		'',
		'同意：募集要項=' . ( '1' === $text['agree_guidelines'] ? 'あり' : 'なし' ) . ' / 個人情報=' . ( '1' === $text['agree_privacy'] ? 'あり' : 'なし' ),
	);

	wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}
