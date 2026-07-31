<?php
/**
 * オンライン説明会予約：Google Calendar / Google Meet 連携（フェーズ2）
 *
 * サービスアカウント（ドメイン全体の委任）で mizuno@sikumys.co.jp を
 * なりすまし（impersonate）し、以下を行う：
 *   1. 予約確定時にカレンダーへイベント作成＋Google Meet URL自動発行
 *   2. Freebusy でカレンダーのbusy時間帯を空きスロット判定に反映
 *
 * 認証は Composer / google-api-client を使わず、JWT(RS256)→アクセストークン
 * を自前実装（wp_remote_post + openssl_sign）で完結させる。
 *
 * 設計方針：予約処理は絶対に失敗させない。Google API のエラー・遅延は
 * すべて握りつぶして false を返し、呼び出し側はフォールバックする。
 *
 * 有効化条件（wp-config.php で定義）：
 *   - BANKOFART_GCAL_ENABLED     … false ならこのモジュールは即なにもしない
 *   - BANKOFART_GCAL_KEY_PATH    … サービスアカウントJSON鍵の絶対パス（公開領域外）
 *   - BANKOFART_GCAL_IMPERSONATE … なりすまし対象＝対象カレンダーID
 *   - BANKOFART_GCAL_TIMEZONE    … 既定 Asia/Tokyo
 *   - BANKOFART_GCAL_SLOT_MINUTES… 既定 30
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** アクセストークンのトランジェントキー。 */
define( 'BANKOFART_GCAL_TOKEN_TRANSIENT', 'bankofart_gcal_access_token' );

/**
 * 【一時デバッグ】切り分け用ログ。
 *
 * wp-config.php に `define( 'BANKOFART_GCAL_DEBUG', true );` を追記した時だけ出力する。
 * 出力先：WPの debug.log（WP_DEBUG_LOG有効時）＋ 保護ディレクトリ内 gcal-debug.log。
 * 秘密（access_token 本文・秘密鍵・JWT assertion）は絶対に渡さないこと。
 * 切り分け完了後は本関数と各呼び出しを削除する。
 *
 * @param string $msg ログ本文。
 * @return void
 */
function bankofart_gcal_log( $msg ) {
	if ( ! defined( 'BANKOFART_GCAL_DEBUG' ) || ! BANKOFART_GCAL_DEBUG ) {
		return;
	}
	$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] [boa-gcal] ' . $msg;

	// 1) WP標準（WP_DEBUG_LOG 有効時は wp-content/debug.log へ）。
	error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions

	// 2) uploads/ 内の専用ログに直書き＝確実に書ける場所（WP_DEBUG非依存）。
	//    ※切り分け用の一時ログ。秘密は書かない。完了後は削除すること。
	if ( function_exists( 'wp_upload_dir' ) ) {
		$up = wp_upload_dir();
		if ( ! empty( $up['basedir'] ) && is_writable( $up['basedir'] ) ) {
			error_log( $line . "\n", 3, $up['basedir'] . '/boa-gcal-debug.log' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}

	// 3) 保護ディレクトリ（鍵と同じ boa-private/）が書けるならそこにも。
	if ( defined( 'BANKOFART_GCAL_KEY_PATH' ) && BANKOFART_GCAL_KEY_PATH ) {
		$dir = dirname( BANKOFART_GCAL_KEY_PATH );
		if ( is_dir( $dir ) && is_writable( $dir ) ) {
			error_log( $line . "\n", 3, $dir . '/gcal-debug.log' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}

/**
 * 【一時デバッグ】連携が無効な理由（なぜ is_enabled が false か）を返す。切り分け完了後は削除。
 *
 * @return string 空文字なら有効。
 */
function bankofart_gcal_disabled_reason() {
	if ( ! defined( 'BANKOFART_GCAL_ENABLED' ) || ! BANKOFART_GCAL_ENABLED ) {
		return 'BANKOFART_GCAL_ENABLED が false または未定義';
	}
	if ( ! function_exists( 'openssl_sign' ) ) {
		return 'openssl 拡張が無効（openssl_sign 不在）';
	}
	if ( ! defined( 'BANKOFART_GCAL_KEY_PATH' ) || '' === BANKOFART_GCAL_KEY_PATH ) {
		return 'BANKOFART_GCAL_KEY_PATH が未定義';
	}
	if ( ! defined( 'BANKOFART_GCAL_IMPERSONATE' ) || '' === BANKOFART_GCAL_IMPERSONATE ) {
		return 'BANKOFART_GCAL_IMPERSONATE が未定義';
	}
	return '';
}

/**
 * 連携が有効か（定数・拡張・鍵の存在まで含めて）を判定する。
 *
 * @return bool
 */
function bankofart_gcal_is_enabled() {
	if ( ! defined( 'BANKOFART_GCAL_ENABLED' ) || ! BANKOFART_GCAL_ENABLED ) {
		return false;
	}
	if ( ! function_exists( 'openssl_sign' ) ) {
		return false;
	}
	if ( ! defined( 'BANKOFART_GCAL_KEY_PATH' ) || '' === BANKOFART_GCAL_KEY_PATH ) {
		return false;
	}
	if ( ! defined( 'BANKOFART_GCAL_IMPERSONATE' ) || '' === BANKOFART_GCAL_IMPERSONATE ) {
		return false;
	}
	return true;
}

/**
 * タイムゾーン（既定 Asia/Tokyo）。
 *
 * @return string
 */
function bankofart_gcal_timezone() {
	return ( defined( 'BANKOFART_GCAL_TIMEZONE' ) && BANKOFART_GCAL_TIMEZONE ) ? BANKOFART_GCAL_TIMEZONE : 'Asia/Tokyo';
}

/**
 * 1枠の長さ（分）。既定は予約グリッドと同じ30分。
 *
 * @return int
 */
function bankofart_gcal_slot_minutes() {
	if ( defined( 'BANKOFART_GCAL_SLOT_MINUTES' ) && (int) BANKOFART_GCAL_SLOT_MINUTES > 0 ) {
		return (int) BANKOFART_GCAL_SLOT_MINUTES;
	}
	if ( defined( 'BANKOFART_BOOKING_INTERVAL' ) ) {
		return (int) BANKOFART_BOOKING_INTERVAL;
	}
	return 30;
}

/**
 * base64url エンコード（JWT用・パディング除去）。
 *
 * @param string $data 生データ。
 * @return string
 */
function bankofart_gcal_base64url( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}

/**
 * C-1. サービスアカウント→（委任）→アクセストークンを取得する。
 *
 * JWT(RS256) を生成し oauth2.googleapis.com/token で access_token に交換。
 * 取得したトークンは WP トランジェントに約50分キャッシュし、毎回発行しない。
 * 失敗時は false（呼び出し側はフォールバック）。
 *
 * @return string|false
 */
function bankofart_gcal_access_token() {
	if ( ! bankofart_gcal_is_enabled() ) {
		bankofart_gcal_log( 'access_token: 連携無効のため中断。理由=' . bankofart_gcal_disabled_reason() );
		return false;
	}

	// キャッシュ命中なら再発行しない（レート制限・遅延対策）。
	$cached = get_transient( BANKOFART_GCAL_TOKEN_TRANSIENT );
	if ( is_string( $cached ) && '' !== $cached ) {
		bankofart_gcal_log( 'access_token: transientキャッシュ命中（新規発行せず）' );
		return $cached;
	}

	try {
		bankofart_gcal_log( 'access_token: 鍵読込 KEY_PATH=' . BANKOFART_GCAL_KEY_PATH . ' / IMPERSONATE=' . BANKOFART_GCAL_IMPERSONATE );
		if ( ! is_readable( BANKOFART_GCAL_KEY_PATH ) ) {
			bankofart_gcal_log( 'access_token: 鍵ファイルが読めない（is_readable=false）。パス・権限を確認' );
			return false;
		}
		$raw = file_get_contents( BANKOFART_GCAL_KEY_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $raw ) {
			bankofart_gcal_log( 'access_token: file_get_contents 失敗' );
			return false;
		}
		$key = json_decode( $raw, true );
		if ( empty( $key['client_email'] ) || empty( $key['private_key'] ) ) {
			bankofart_gcal_log( 'access_token: JSON鍵に client_email / private_key が無い（json_decode失敗 or 鍵ファイル不正）。json_last_error=' . json_last_error_msg() );
			return false;
		}
		bankofart_gcal_log( 'access_token: 鍵OK client_email=' . $key['client_email'] );

		$now    = time();
		$header = array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		);
		$claims = array(
			'iss'   => $key['client_email'],
			'sub'   => BANKOFART_GCAL_IMPERSONATE, // ←委任のなりすまし。これでMeet発行が可能になる。
			'scope' => 'https://www.googleapis.com/auth/calendar',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		);

		$segments = array(
			bankofart_gcal_base64url( wp_json_encode( $header ) ),
			bankofart_gcal_base64url( wp_json_encode( $claims ) ),
		);
		$signing_input = implode( '.', $segments );

		$signature = '';
		$signed    = openssl_sign( $signing_input, $signature, $key['private_key'], 'sha256WithRSAEncryption' );
		if ( ! $signed ) {
			bankofart_gcal_log( 'access_token: openssl_sign 失敗（private_key不正の可能性）。openssl_error=' . openssl_error_string() );
			return false;
		}
		$segments[] = bankofart_gcal_base64url( $signature );
		$jwt        = implode( '.', $segments );

		bankofart_gcal_log( 'access_token: token エンドポイントへPOST（JWT bearer）' );
		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			bankofart_gcal_log( 'access_token: token POST が WP_Error＝' . $response->get_error_message() . '（外向き通信不可の可能性）' );
			return false;
		}
		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );
		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			// ★失敗時のみ本文（＝Googleのエラー error/error_description）を出す。成功時は本文にtokenが入るので出さない。
			bankofart_gcal_log( 'access_token: token取得失敗 HTTP=' . $code . ' body=' . $raw_body );
			return false;
		}

		$token = (string) $body['access_token'];
		// expires_in（通常3600）から余裕を引いてキャッシュ。既定は約50分。
		$ttl = isset( $body['expires_in'] ) ? ( (int) $body['expires_in'] - 300 ) : 3000;
		set_transient( BANKOFART_GCAL_TOKEN_TRANSIENT, $token, max( 60, $ttl ) );

		bankofart_gcal_log( 'access_token: token取得成功 HTTP=' . $code . ' expires_in=' . ( isset( $body['expires_in'] ) ? $body['expires_in'] : '不明' ) );
		return $token;
	} catch ( Exception $e ) {
		bankofart_gcal_log( 'access_token: 例外＝' . $e->getMessage() );
		return false;
	}
}

/**
 * C-2. 予約確定時にカレンダーへイベント作成＋Google Meet URL発行。
 *
 * @param int $booking_id 予約ID。
 * @return array|false array( 'event_id' => ..., 'meet_link' => ... )／失敗時 false。
 */
function bankofart_booking_gcal_create_event( $booking_id ) {
	bankofart_gcal_log( 'create_event: 開始 booking_id=' . $booking_id );
	if ( ! bankofart_gcal_is_enabled() ) {
		bankofart_gcal_log( 'create_event: 連携無効のため中断。理由=' . bankofart_gcal_disabled_reason() );
		return false;
	}

	try {
		$token = bankofart_gcal_access_token();
		if ( ! $token ) {
			bankofart_gcal_log( 'create_event: アクセストークン取得失敗のため中断（上のtokenログ参照）' );
			return false;
		}

		global $wpdb;
		$table = bankofart_booking_table();
		$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $booking_id ) ); // phpcs:ignore WordPress.DB
		if ( ! $req ) {
			bankofart_gcal_log( 'create_event: 予約行が見つからない booking_id=' . $booking_id );
			return false;
		}

		$tz       = new DateTimeZone( bankofart_gcal_timezone() );
		$slot_min = bankofart_gcal_slot_minutes();

		// booked_at は WP ローカル（JST, UTC+9）保存の "YYYY-MM-DD HH:MM"。
		$start = new DateTime( $req->booked_at, $tz );
		$end   = clone $start;
		$end->modify( '+' . $slot_min . ' minutes' );

		// タイトル：【BOA説明会】{name}様（{company}）。
		$summary = sprintf( '【BOA説明会】%s様', $req->name );
		if ( ! empty( $req->company ) ) {
			$summary .= sprintf( '（%s）', $req->company );
		}

		$description = implode(
			"\n",
			array(
				'オンライン説明会のご予約',
				'',
				'お名前　：' . $req->name,
				'会社名　：' . $req->company,
				'ご連絡先：' . $req->phone,
				'メール　：' . $req->email,
				'ご目的　：' . $req->purpose,
				'予約ID　：' . $req->id,
			)
		);

		$event = array(
			'summary'        => $summary,
			'description'    => $description,
			'start'          => array(
				'dateTime' => $start->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => bankofart_gcal_timezone(),
			),
			'end'            => array(
				'dateTime' => $end->format( 'Y-m-d\TH:i:s' ),
				'timeZone' => bankofart_gcal_timezone(),
			),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => 'boa-' . $booking_id . '-' . wp_generate_password( 12, false ),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			),
		);

		/**
		 * 予約者を attendees に含めるか。既定 false。
		 * 既存の確認メールと二重になるのを避けるため既定では付けない。
		 * 招待を送りたい場合は true を返すフィルタを登録する。
		 *
		 * @param bool   $add 既定 false。
		 * @param object $req 予約行。
		 */
		if ( apply_filters( 'bankofart_booking_gcal_add_attendee', false, $req ) && ! empty( $req->email ) ) {
			$event['attendees'] = array( array( 'email' => $req->email ) );
		}

		$url = sprintf(
			'https://www.googleapis.com/calendar/v3/calendars/%s/events?conferenceDataVersion=1&sendUpdates=none',
			rawurlencode( BANKOFART_GCAL_IMPERSONATE )
		);

		bankofart_gcal_log( 'create_event: events API へPOST cal=' . BANKOFART_GCAL_IMPERSONATE . ' start=' . $start->format( 'Y-m-d\TH:i:s' ) );
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event ),
			)
		);
		if ( is_wp_error( $response ) ) {
			bankofart_gcal_log( 'create_event: events POST が WP_Error＝' . $response->get_error_message() );
			return false;
		}
		$code     = (int) wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );
		$body     = json_decode( $raw_body, true );
		if ( $code < 200 || $code >= 300 || empty( $body['id'] ) ) {
			// 失敗時はGoogleのエラー本文をそのまま出す（error.message / errors[].reason が原因の核心）。
			bankofart_gcal_log( 'create_event: イベント作成失敗 HTTP=' . $code . ' body=' . $raw_body );
			return false;
		}

		$event_id = (string) $body['id'];

		// Meet URL：hangoutLink 優先、無ければ conferenceData の video entryPoint。
		$meet_link = '';
		if ( ! empty( $body['hangoutLink'] ) ) {
			$meet_link = (string) $body['hangoutLink'];
		} elseif ( ! empty( $body['conferenceData']['entryPoints'] ) && is_array( $body['conferenceData']['entryPoints'] ) ) {
			foreach ( $body['conferenceData']['entryPoints'] as $ep ) {
				if ( isset( $ep['entryPointType'] ) && 'video' === $ep['entryPointType'] && ! empty( $ep['uri'] ) ) {
					$meet_link = (string) $ep['uri'];
					break;
				}
			}
		}

		bankofart_gcal_log( 'create_event: 成功 event_id=' . $event_id . ' meet_link=' . ( '' !== $meet_link ? $meet_link : '（空＝Meet未発行。conferenceDataVersion/委任設定を確認）' ) );
		return array(
			'event_id'  => $event_id,
			'meet_link' => $meet_link,
		);
	} catch ( Exception $e ) {
		bankofart_gcal_log( 'create_event: 例外＝' . $e->getMessage() );
		return false;
	}
}

/**
 * C-3. Freebusy でカレンダーのbusyを取得し、$booked（"HH:MM"配列）にマージする。
 *
 * bankofart_booking_busy_slots フィルタに登録して空きスロット判定へ反映。
 * 連携無効・トークン取得失敗・API失敗時は $booked をそのまま返す（DB既存予約のみ＝安全側）。
 *
 * @param string[] $booked 既存のbusyスロット（"HH:MM"）。
 * @param string   $date   対象日 "YYYY-MM-DD"。
 * @return string[]
 */
function bankofart_booking_gcal_busy_slots( $booked, $date ) {
	$booked = is_array( $booked ) ? $booked : array();

	if ( ! bankofart_gcal_is_enabled() ) {
		return $booked;
	}

	try {
		$token = bankofart_gcal_access_token();
		if ( ! $token ) {
			return $booked;
		}

		$tz        = new DateTimeZone( bankofart_gcal_timezone() );
		$day_start = new DateTime( $date . ' 00:00:00', $tz );
		$day_end   = clone $day_start;
		$day_end->modify( '+1 day' );

		$response = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/freeBusy',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'timeMin'  => $day_start->format( 'c' ),
						'timeMax'  => $day_end->format( 'c' ),
						'timeZone' => bankofart_gcal_timezone(),
						'items'    => array( array( 'id' => BANKOFART_GCAL_IMPERSONATE ) ),
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $booked;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code ) {
			return $booked;
		}

		$busy = array();
		if ( isset( $body['calendars'][ BANKOFART_GCAL_IMPERSONATE ]['busy'] ) && is_array( $body['calendars'][ BANKOFART_GCAL_IMPERSONATE ]['busy'] ) ) {
			$busy = $body['calendars'][ BANKOFART_GCAL_IMPERSONATE ]['busy'];
		}
		if ( empty( $busy ) ) {
			return $booked;
		}

		// busy 区間を DateTime（対象TZ）に正規化。
		$intervals = array();
		foreach ( $busy as $b ) {
			if ( empty( $b['start'] ) || empty( $b['end'] ) ) {
				continue;
			}
			$bs = new DateTime( $b['start'] );
			$bs->setTimezone( $tz );
			$be = new DateTime( $b['end'] );
			$be->setTimezone( $tz );
			$intervals[] = array( $bs, $be );
		}

		// 各スロット [slot, slot+interval) が busy と少しでも重なれば埋まり扱い。
		$slot_min = bankofart_gcal_slot_minutes();
		foreach ( bankofart_booking_all_slots() as $slot ) {
			if ( in_array( $slot, $booked, true ) ) {
				continue;
			}
			$slot_start = new DateTime( $date . ' ' . $slot . ':00', $tz );
			$slot_end   = clone $slot_start;
			$slot_end->modify( '+' . $slot_min . ' minutes' );

			foreach ( $intervals as $iv ) {
				// 重なり条件：slot_start < busy_end && slot_end > busy_start。
				if ( $slot_start < $iv[1] && $slot_end > $iv[0] ) {
					$booked[] = $slot;
					break;
				}
			}
		}

		return $booked;
	} catch ( Exception $e ) {
		return $booked;
	}
}
add_filter( 'bankofart_booking_busy_slots', 'bankofart_booking_gcal_busy_slots', 10, 2 );
