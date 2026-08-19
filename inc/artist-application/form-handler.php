<?php
/**
 * 公認画家申請フォーム：送信処理（admin-post）
 *
 * 流れ：nonce → ハニーポット → reCAPTCHA → レートリミット → バリデーション
 *   → 画像を Base64 化（サーバー側）→ GAS Web App へ POST（テキスト＋Base64）
 *   → info@ へバックアップ通知メール（GAS成否に関わらず）→ 完了画面へ PRG リダイレクト。
 *
 * 画像の Base64 化・GAS への POST は必ず本サーバー側で行う（クライアントから直接 GAS を叩かない）。
 * GAS secret はサーバー側定数のみで保持し、フロントには出さない。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 申請送信を処理する。
 *
 * @return void
 */
function bankofart_handle_artist_application() {
	// 1) nonce。
	check_admin_referer( 'boa_artist_app', 'boa_artist_app_nonce' );

	// リダイレクト先（フォーム固定ページのURL。改ざん対策に wp_validate_redirect）。
	$raw_redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
	$redirect     = wp_validate_redirect( $raw_redirect, home_url( '/' ) );

	// 2) ハニーポット（埋まっていればスパム扱いで完了風に流す）。
	if ( ! empty( $_POST['website_hp'] ) ) {
		wp_safe_redirect( $redirect );
		exit;
	}

	// 3) reCAPTCHA（キー未定義ならスキップ）。
	$recaptcha = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
	if ( ! bankofart_artist_app_verify_recaptcha( $recaptcha ) ) {
		wp_safe_redirect( add_query_arg( 'app_error', 'recaptcha', $redirect ) );
		exit;
	}

	// 4) レートリミット。
	if ( bankofart_artist_app_is_rate_limited( bankofart_artist_app_get_ip() ) ) {
		wp_safe_redirect( add_query_arg( 'app_error', 'rate_limit', $redirect ) );
		exit;
	}

	// 5) テキスト項目の取得・サニタイズ。
	$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$text = bankofart_artist_app_collect_text( $post );

	// 6) バリデーション（テキスト）。
	$errors = bankofart_artist_app_validate( $post );

	// 7) 画像処理（Base64化＋検証）。
	$images   = array();
	$img_errs = array();
	bankofart_artist_app_process_images( $images, $img_errs );
	$errors = array_merge( $errors, $img_errs );

	// 8) エラーがあれば再入力へ（値を一時保存。画像は再選択のため保存しない）。
	if ( ! empty( $errors ) ) {
		$key = 'boa_aa_' . wp_generate_password( 12, false );
		set_transient(
			$key,
			array(
				'errors' => $errors,
				'values' => $text,
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'app_error', $key, $redirect ) );
		exit;
	}

	// 9) GAS Web App へ POST（テキスト＋Base64画像）。成否は記録のみ（取りこぼしはメールで担保）。
	$gas_ok = bankofart_artist_app_post_to_gas( $text, $images );

	// 10) 申請者本人へ受付の自動返信（失敗しても申請は成立させる）。
	bankofart_artist_app_send_user_mail( $text, $images );

	// 11) info@ へバックアップ通知メール（GAS成否に関わらず必ず送る）。
	bankofart_artist_app_send_backup_mail( $text, $images, $gas_ok );

	// 12) 完了画面へ PRG リダイレクト（二重送信防止）。
	$thanks_key = 'boa_aa_done_' . wp_generate_password( 12, false );
	set_transient(
		$thanks_key,
		array(
			'artist_name' => $text['artist_name'],
			'gas_ok'      => $gas_ok ? 1 : 0,
		),
		30 * MINUTE_IN_SECONDS
	);
	wp_safe_redirect( add_query_arg( 'app_thanks', $thanks_key, $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_boa_artist_application', 'bankofart_handle_artist_application' );
add_action( 'admin_post_boa_artist_application', 'bankofart_handle_artist_application' );

/**
 * テキスト項目を取得・サニタイズして連想配列で返す（キー＝GASパラメータ名）。
 *
 * @param array $post wp_unslash 済み $_POST。
 * @return array
 */
function bankofart_artist_app_collect_text( $post ) {
	$t = function ( $k ) use ( $post ) {
		return isset( $post[ $k ] ) ? sanitize_text_field( $post[ $k ] ) : '';
	};
	$a = function ( $k ) use ( $post ) {
		return isset( $post[ $k ] ) ? sanitize_textarea_field( $post[ $k ] ) : '';
	};
	$u = function ( $k ) use ( $post ) {
		return isset( $post[ $k ] ) ? esc_url_raw( trim( (string) $post[ $k ] ) ) : '';
	};

	return array(
		// 基本情報。
		'name_sei'            => $t( 'name_sei' ),
		'name_mei'            => $t( 'name_mei' ),
		'name_kana'           => $t( 'name_kana' ),
		'artist_name'         => $t( 'artist_name' ),
		'email'               => isset( $post['email'] ) ? sanitize_email( $post['email'] ) : '',
		'phone'               => $t( 'phone' ),
		'postal_code'         => $t( 'postal_code' ),
		'pref'                => $t( 'pref' ),
		'city'                => $t( 'city' ),
		'address1'            => $t( 'address1' ),
		'building'            => $t( 'building' ),
		'gender'              => $t( 'gender' ),
		'birthday'            => $t( 'birthday' ),
		'career'              => $a( 'career' ),
		// 公開プロフィール。
		'theme_short'         => $t( 'theme_short' ),
		'theme_long'          => $a( 'theme_long' ),
		'reason'              => $a( 'reason' ),
		'origin'              => $a( 'origin' ),
		'goal'                => $a( 'goal' ),
		'solo_exh'            => $a( 'solo_exh' ),
		'group_exh'           => $a( 'group_exh' ),
		'awards'              => $a( 'awards' ),
		'sns_instagram'       => $u( 'sns_instagram' ),
		'sns_x'               => $u( 'sns_x' ),
		'sns_facebook'        => $u( 'sns_facebook' ),
		'sns_youtube'         => $u( 'sns_youtube' ),
		'sns_other'           => $u( 'sns_other' ),
		// 契約情報（非公開）。
		'bank_name'           => $t( 'bank_name' ),
		'bank_branch'         => $t( 'bank_branch' ),
		'bank_account_type'   => $t( 'bank_account_type' ),
		'bank_account_number' => $t( 'bank_account_number' ),
		'bank_account_holder' => $t( 'bank_account_holder' ),
		// 同意。
		'agreed'              => ! empty( $post['agreed'] ) ? '1' : '',
	);
}

/**
 * テキスト項目のバリデーション。
 *
 * @param array $post wp_unslash 済み $_POST。
 * @return array エラーメッセージ配列。
 */
function bankofart_artist_app_validate( $post ) {
	$errors = array();
	$req    = array(
		'name_sei'    => '本名（姓）',
		'name_mei'    => '本名（名）',
		'name_kana'   => '本名フリガナ',
		'artist_name' => '活動名（アーティスト名）',
		'phone'       => '電話番号',
		'career'      => '経歴',
		'theme_short' => '制作テーマ',
		'theme_long'  => '制作テーマ詳細',
		'reason'      => 'なぜ描くか',
		'origin'      => '起源の物語',
	);
	foreach ( $req as $k => $label ) {
		if ( '' === trim( (string) ( isset( $post[ $k ] ) ? $post[ $k ] : '' ) ) ) {
			$errors[ $k ] = $label . 'をご入力ください。';
		}
	}

	// メール（必須＋確認一致）。
	$email   = isset( $post['email'] ) ? trim( (string) $post['email'] ) : '';
	$email_c = isset( $post['email_confirm'] ) ? trim( (string) $post['email_confirm'] ) : '';
	if ( '' === $email || ! is_email( $email ) ) {
		$errors['email'] = '有効なメールアドレスをご入力ください。';
	} elseif ( $email !== $email_c ) {
		$errors['email_confirm'] = '確認用メールアドレスが一致しません。';
	}

	// 電話番号（数字・ハイフンのみ）。
	$phone = isset( $post['phone'] ) ? trim( (string) $post['phone'] ) : '';
	if ( '' !== $phone && preg_match( '/[^0-9\-]/', $phone ) ) {
		$errors['phone'] = '電話番号は数字とハイフンでご入力ください。';
	}

	// 制作テーマ（13字以内）。
	$theme_short = isset( $post['theme_short'] ) ? trim( (string) $post['theme_short'] ) : '';
	if ( '' !== $theme_short && mb_strlen( $theme_short ) > 13 ) {
		$errors['theme_short'] = '制作テーマは13字以内でご入力ください。';
	}

	// 口座番号（入力時は数字のみ）。
	$acct = isset( $post['bank_account_number'] ) ? trim( (string) $post['bank_account_number'] ) : '';
	if ( '' !== $acct && preg_match( '/[^0-9]/', $acct ) ) {
		$errors['bank_account_number'] = '口座番号は数字のみでご入力ください。';
	}

	// 同意（必須）。
	if ( empty( $post['agreed'] ) ) {
		$errors['agreed'] = '個人情報の取り扱い・規約への同意が必要です。';
	}

	return $errors;
}

/**
 * アップロード画像を検証して Base64 化する。
 *
 * @param array $images 出力：array( 'main'=>{b64,name}, 'symbol'=>{...}, 'work'=>array({b64,name},...) )。
 * @param array $errors 出力：エラーメッセージ配列。
 * @return void
 */
function bankofart_artist_app_process_images( &$images, &$errors ) {
	$images = array(
		'main'   => null,
		'symbol' => null,
		'work'   => array(),
	);
	$total = 0;

	// 単一画像（メイン・象徴）。
	$singles = array(
		'main_image'   => array( 'key' => 'main', 'label' => 'メイン画像' ),
		'symbol_image' => array( 'key' => 'symbol', 'label' => '自己を象徴する画像' ),
	);
	foreach ( $singles as $field => $info ) {
		if ( empty( $_FILES[ $field ] ) || ! isset( $_FILES[ $field ]['error'] ) ) {
			continue;
		}
		$err = (int) $_FILES[ $field ]['error'];
		if ( UPLOAD_ERR_NO_FILE === $err ) {
			continue; // 任意。
		}
		$res = bankofart_artist_app_handle_single_file( $_FILES[ $field ], $info['label'] );
		if ( is_wp_error( $res ) ) {
			$errors[ $field ] = $res->get_error_message();
			continue;
		}
		$total += $res['bytes'];
		$images[ $info['key'] ] = array(
			'b64'  => $res['b64'],
			'name' => $res['name'],
		);
	}

	// 複数画像（制作風景）。name="work_images[]"。
	if ( ! empty( $_FILES['work_images'] ) && isset( $_FILES['work_images']['name'] ) && is_array( $_FILES['work_images']['name'] ) ) {
		$count = count( $_FILES['work_images']['name'] );
		$kept  = 0;
		for ( $i = 0; $i < $count; $i++ ) {
			$err = isset( $_FILES['work_images']['error'][ $i ] ) ? (int) $_FILES['work_images']['error'][ $i ] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_NO_FILE === $err ) {
				continue;
			}
			if ( $kept >= BANKOFART_ARTIST_APP_MAX_WORK_IMAGES ) {
				$errors['work_images'] = sprintf( '制作風景の画像は最大%d枚までです。', BANKOFART_ARTIST_APP_MAX_WORK_IMAGES );
				break;
			}
			$file = array(
				'name'     => $_FILES['work_images']['name'][ $i ],
				'type'     => isset( $_FILES['work_images']['type'][ $i ] ) ? $_FILES['work_images']['type'][ $i ] : '',
				'tmp_name' => isset( $_FILES['work_images']['tmp_name'][ $i ] ) ? $_FILES['work_images']['tmp_name'][ $i ] : '',
				'error'    => $err,
				'size'     => isset( $_FILES['work_images']['size'][ $i ] ) ? $_FILES['work_images']['size'][ $i ] : 0,
			);
			$res = bankofart_artist_app_handle_single_file( $file, '制作風景の画像' );
			if ( is_wp_error( $res ) ) {
				$errors['work_images'] = $res->get_error_message();
				continue;
			}
			$total           += $res['bytes'];
			$images['work'][] = array(
				'b64'  => $res['b64'],
				'name' => $res['name'],
			);
			$kept++;
		}
	}

	// 合計サイズ上限。
	if ( $total > BANKOFART_ARTIST_APP_MAX_TOTAL_BYTES ) {
		$errors['images_total'] = sprintf( '画像の合計サイズが大きすぎます（上限 %dMB）。枚数やサイズを調整してください。', (int) ( BANKOFART_ARTIST_APP_MAX_TOTAL_BYTES / 1024 / 1024 ) );
	}
}

/**
 * 単一アップロードファイルを検証し Base64 化する。
 *
 * @param array  $file  $_FILES の1要素。
 * @param string $label エラー表示用ラベル。
 * @return array|WP_Error array( 'b64'=>..., 'name'=>..., 'bytes'=>... )。
 */
function bankofart_artist_app_handle_single_file( $file, $label ) {
	$err = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_INI_SIZE === $err || UPLOAD_ERR_FORM_SIZE === $err ) {
		return new WP_Error( 'too_large', $label . 'のファイルサイズが大きすぎます。' );
	}
	if ( UPLOAD_ERR_OK !== $err ) {
		return new WP_Error( 'upload', $label . 'のアップロードに失敗しました。' );
	}

	$tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		return new WP_Error( 'invalid', $label . 'のアップロードを確認できませんでした。' );
	}

	$size = (int) ( isset( $file['size'] ) ? $file['size'] : filesize( $tmp ) );
	if ( $size <= 0 ) {
		return new WP_Error( 'empty', $label . 'のファイルが空です。' );
	}
	if ( $size > BANKOFART_ARTIST_APP_MAX_IMAGE_BYTES ) {
		return new WP_Error( 'too_large', sprintf( '%sは1枚あたり %dMB 以内にしてください。', $label, (int) ( BANKOFART_ARTIST_APP_MAX_IMAGE_BYTES / 1024 / 1024 ) ) );
	}

	// 拡張子チェック。
	$name = sanitize_file_name( isset( $file['name'] ) ? $file['name'] : 'image' );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, bankofart_artist_app_allowed_ext(), true ) ) {
		return new WP_Error( 'ext', $label . 'は JPG または PNG 形式でアップロードしてください。' );
	}

	// 実体MIMEチェック（拡張子偽装対策）。
	$check = wp_check_filetype_and_ext( $tmp, $name );
	$mime  = ! empty( $check['type'] ) ? $check['type'] : '';
	if ( '' === $mime && function_exists( 'getimagesize' ) ) {
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$mime = ( $info && ! empty( $info['mime'] ) ) ? $info['mime'] : '';
	}
	if ( ! in_array( $mime, bankofart_artist_app_allowed_mime(), true ) ) {
		return new WP_Error( 'mime', $label . 'が画像ファイル（JPG/PNG）として認識できませんでした。' );
	}

	$bin = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( false === $bin ) {
		return new WP_Error( 'read', $label . 'の読み込みに失敗しました。' );
	}

	return array(
		'b64'   => base64_encode( $bin ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- GAS連携の画像転送用。
		'name'  => $name,
		'bytes' => $size,
	);
}

/**
 * GAS Web App へ POST する（テキスト＋Base64画像）。
 *
 * @param array $text   テキスト項目（GASパラメータ名キー）。
 * @param array $images 画像（main/symbol/work）。
 * @return bool 送信成功なら true。
 */
function bankofart_artist_app_post_to_gas( $text, $images ) {
	// シークレット未設定（wp-config.php で define されていない）なら送らない。
	// 呼び出し側は false を受けてもバックアップメールを必ず送るので、申請は取りこぼさない。
	if ( '' === (string) BANKOFART_ARTIST_APP_GAS_SECRET ) {
		return false;
	}

	$work_b64   = array();
	$work_names = array();
	foreach ( $images['work'] as $w ) {
		$work_b64[]   = $w['b64'];
		$work_names[] = $w['name'];
	}

	$body = array_merge(
		$text,
		array(
			'secret'           => BANKOFART_ARTIST_APP_GAS_SECRET,
			'main_image'       => isset( $images['main']['b64'] ) ? $images['main']['b64'] : '',
			'main_image_name'  => isset( $images['main']['name'] ) ? $images['main']['name'] : '',
			'symbol_image'     => isset( $images['symbol']['b64'] ) ? $images['symbol']['b64'] : '',
			'symbol_image_name'=> isset( $images['symbol']['name'] ) ? $images['symbol']['name'] : '',
			'work_images'      => implode( '|', $work_b64 ),
			'work_image_names' => implode( '|', $work_names ),
		)
	);

	/*
	 * GAS Web App は POST を受けて doPost を実行後、結果を script.googleusercontent.com の
	 * echo へ 302 で返す。WP_Http が POST のまま自動追従すると Google が 400/411 を返すため、
	 * redirection=0 で追従を止め（doPost はこの POST で実行済み）、Location を GET で取得して
	 * 結果 JSON（{"ok":true}）を読む。
	 */
	$res = wp_remote_post(
		BANKOFART_ARTIST_APP_GAS_URL,
		array(
			'timeout'     => 45,
			'redirection' => 0,
			'body'        => $body,
		)
	);
	if ( is_wp_error( $res ) ) {
		return false;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
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

	// 成功判定は厳密に {"ok":true}。GAS のエラーHTML（HTTP 200）は失敗扱い。
	$data = json_decode( $resp_body, true );
	return ( is_array( $data ) && ! empty( $data['ok'] ) );
}

/**
 * 申請者本人への自動返信メール。
 *
 * こちらは選考（画家応募）ではなく、公開プロフィールの登録申請なので、
 * 「審査の合否」ではなく「内容確認のうえ担当者から連絡」という案内にする。
 * 本名・住所・口座情報を預かるフォームなので、それらが公開されない点だけ明記する。
 * 「掲載前に本人確認する」といった運用の約束は書かない（運用が縛られるため）。
 *
 * @param array $text   テキスト項目。
 * @param array $images 画像（受領枚数の記載用）。
 * @return bool 送信成功なら true。
 */
function bankofart_artist_app_send_user_mail( $text, $images ) {
	$to = isset( $text['email'] ) ? trim( (string) $text['email'] ) : '';
	if ( ! is_email( $to ) ) {
		return false;
	}

	$headers = function_exists( 'bankofart_mail_headers' ) ? bankofart_mail_headers() : array( 'Content-Type: text/plain; charset=UTF-8' );
	$subject = '【BANK OF ART】ご申請ありがとうございます';

	$work_n = isset( $images['work'] ) ? count( (array) $images['work'] ) : 0;
	$main_n = ! empty( $images['main'] ) ? 1 : 0;
	$sym_n  = ! empty( $images['symbol'] ) ? 1 : 0;

	$lines = array(
		$text['name_sei'] . ' ' . $text['name_mei'] . ' 様',
		'',
		'このたびは BANK OF ART へ公認画家のご申請をいただき、誠にありがとうございます。',
		'以下の内容で申請を受け付けいたしました。',
		'',
		'────────────────────────',
		' 受付内容',
		'────────────────────────',
		'お名前　　：' . $text['name_sei'] . ' ' . $text['name_mei'],
		'活動名　　：' . $text['artist_name'],
		'メール　　：' . $text['email'],
		'制作テーマ：' . $text['theme_short'],
		'ご提出画像：メイン ' . $main_n . '点 ／ シンボル ' . $sym_n . '点 ／ 制作風景 ' . $work_n . '点',
		'',
		'────────────────────────',
		' 今後の流れ',
		'────────────────────────',
		'いただいた内容を確認のうえ、担当者より2〜4週間程度を目安にご連絡いたします。',
		'内容の確認や追加のご相談で、こちらからお伺いすることがあります。',
		'',
		'※ 本名・ご住所・口座情報などが公開されることはありません。',
		'',
		'※ 期間を過ぎても連絡が確認できない場合は、迷惑メールフォルダをご確認のうえ、',
		'　 本メールへご返信ください。',
		'',
		'ご不明な点がございましたら、本メールへご返信ください。',
		'',
		'────────────────────────',
		'BANK of ART　絵描きの明日を創出する。',
		home_url( '/' ),
		'株式会社シクミーズ',
		'────────────────────────',
	);

	return wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}

/**
 * info@ 宛のバックアップ通知メールを送る（テキスト項目のみ。画像はスプシ/Drive参照）。
 *
 * @param array $text   テキスト項目。
 * @param array $images 画像（枚数の記載用）。
 * @param bool  $gas_ok GAS送信成否。
 * @return void
 */
function bankofart_artist_app_send_backup_mail( $text, $images, $gas_ok ) {
	$to      = defined( 'BANKOFART_CONTACT_EMAIL' ) ? BANKOFART_CONTACT_EMAIL : get_option( 'admin_email' );
	$headers = function_exists( 'bankofart_mail_headers' ) ? bankofart_mail_headers() : array( 'Content-Type: text/plain; charset=UTF-8' );

	$work_n = count( $images['work'] );
	$main_n = isset( $images['main'] ) && $images['main'] ? 1 : 0;
	$sym_n  = isset( $images['symbol'] ) && $images['symbol'] ? 1 : 0;

	$subject = sprintf( '【公認画家申請】%s（%s %s）様', $text['artist_name'], $text['name_sei'], $text['name_mei'] );

	$lines = array(
		'公認画家申請フォームから新しい申請がありました。',
		( $gas_ok ? '※ Googleスプレッドシート／Drive への保存：成功' : '※ Googleスプレッドシートへの保存に失敗した可能性があります。本メールの内容で対応してください。' ),
		'',
		'━━ 基本情報 ━━',
		'本名　　　：' . $text['name_sei'] . ' ' . $text['name_mei'],
		'フリガナ　：' . $text['name_kana'],
		'活動名　　：' . $text['artist_name'],
		'メール　　：' . $text['email'],
		'電話　　　：' . $text['phone'],
		'住所　　　：〒' . $text['postal_code'] . ' ' . $text['pref'] . $text['city'] . $text['address1'] . ' ' . $text['building'],
		'性別　　　：' . $text['gender'],
		'生年月日　：' . $text['birthday'],
		'経歴　　　：' . "\n" . $text['career'],
		'',
		'━━ 公開プロフィール ━━',
		'制作テーマ：' . $text['theme_short'],
		'テーマ詳細：' . "\n" . $text['theme_long'],
		'なぜ描くか：' . "\n" . $text['reason'],
		'起源の物語：' . "\n" . $text['origin'],
		'目標・ゴール：' . "\n" . $text['goal'],
		'個展歴　　：' . "\n" . $text['solo_exh'],
		'グループ展：' . "\n" . $text['group_exh'],
		'受賞・メディア：' . "\n" . $text['awards'],
		'Instagram：' . $text['sns_instagram'],
		'X　　　　 ：' . $text['sns_x'],
		'Facebook　：' . $text['sns_facebook'],
		'YouTube　 ：' . $text['sns_youtube'],
		'その他URL ：' . $text['sns_other'],
		'',
		'━━ 契約情報（非公開・振込用）━━',
		'銀行名　　：' . $text['bank_name'],
		'支店名　　：' . $text['bank_branch'],
		'口座種別　：' . $text['bank_account_type'],
		'口座番号　：' . $text['bank_account_number'],
		'口座名義　：' . $text['bank_account_holder'],
		'',
		'━━ 画像 ━━',
		sprintf( 'メイン画像：%d枚 / 自己象徴：%d枚 / 制作風景：%d枚', $main_n, $sym_n, $work_n ),
		'※ 画像の実体は Googleスプレッドシート／Drive をご参照ください。',
		'',
		'同意：' . ( '1' === $text['agreed'] ? 'あり' : 'なし' ),
	);

	wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
}
