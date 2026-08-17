<?php
/**
 * オンライン説明会予約：予約確定処理（admin-post）
 *
 * フェーズ1：Google連携なし。DBへ confirmed で保存し、メール2通＋完了画面へ（PRG）。
 * ダブルブッキングは DB UNIQUE (booked_at, status) ＋ 確定前再チェックの二重で防止。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 予約確定。
 *
 * @return void
 */
function bankofart_handle_booking_reserve() {
	$page = home_url( '/online-briefing/' );

	// nonce。
	check_admin_referer( 'boa_booking_reserve', 'boa_booking_nonce' );

	// ハニーポット。
	if ( ! empty( $_POST['website_hp'] ) ) {
		wp_safe_redirect( $page );
		exit;
	}

	// reCAPTCHA（キー無ければ資料請求のヘルパーでスキップ）。
	$recaptcha = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';
	if ( function_exists( 'bankofart_doc_request_verify_recaptcha' ) && ! bankofart_doc_request_verify_recaptcha( $recaptcha ) ) {
		wp_safe_redirect( add_query_arg( 'ob_error', 'recaptcha', $page ) );
		exit;
	}

	// レートリミット（同一IP・5分3件。仕様§8）。
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rl  = 'boa_bk_rate_' . md5( $ip );
	$cnt = (int) get_transient( $rl );
	if ( $cnt >= 3 ) {
		wp_safe_redirect( add_query_arg( 'ob_error', 'rate_limit', $page ) );
		exit;
	}
	set_transient( $rl, $cnt + 1, 5 * MINUTE_IN_SECONDS );

	// 入力サニタイズ。
	$booked_at = isset( $_POST['booked_at'] ) ? sanitize_text_field( wp_unslash( $_POST['booked_at'] ) ) : '';
	$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$company   = isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '';
	$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone     = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$purpose   = isset( $_POST['purpose'] ) ? sanitize_text_field( wp_unslash( $_POST['purpose'] ) ) : '';

	// バリデーション。
	$ok = true;
	if ( '' === $name || mb_strlen( $name ) > 100 ) {
		$ok = false;
	}
	if ( '' === $company || mb_strlen( $company ) > 200 ) {
		$ok = false;
	}
	if ( '' === $email || ! is_email( $email ) ) {
		$ok = false;
	}
	$phone_digits = preg_replace( '/[^0-9]/', '', $phone );
	if ( strlen( $phone_digits ) < 10 || strlen( $phone_digits ) > 15 ) {
		$ok = false;
	}
	if ( ! in_array( $purpose, bankofart_booking_purposes(), true ) ) {
		$ok = false;
	}
	if ( ! bankofart_booking_is_valid_slot( $booked_at ) ) {
		$ok = false;
	}
	if ( ! $ok ) {
		wp_safe_redirect( add_query_arg( 'ob_error', 'validation', $page ) );
		exit;
	}

	global $wpdb;
	$table = bankofart_booking_table();

	// 同一メールから24時間以内に同じ日時の重複予約は不可（仕様§8）。
	$dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE email = %s AND booked_at = %s AND status = 'confirmed'", $email, $booked_at ) ); // phpcs:ignore WordPress.DB
	if ( $dup > 0 ) {
		wp_safe_redirect( add_query_arg( 'ob_error', 'duplicate', $page ) );
		exit;
	}

	// ダブルブッキング再チェック（確定前）。
	$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE booked_at = %s AND status = 'confirmed'", $booked_at ) ); // phpcs:ignore WordPress.DB
	if ( $taken > 0 ) {
		wp_safe_redirect( add_query_arg( 'ob_error', 'taken', $page ) );
		exit;
	}

	// INSERT（UNIQUE uk_booked_at_active がレース時の最終防壁）。
	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'booked_at'     => $booked_at,
			'name'          => $name,
			'company'       => $company,
			'email'         => $email,
			'phone'         => $phone,
			'purpose'       => $purpose,
			'gcal_event_id' => null, // フェーズ1：NULL。フェーズ2でイベントID。
			'meet_link'     => null, // フェーズ1：NULL。フェーズ2でMeet URL。
			'status'        => 'confirmed',
			'ip_address'    => substr( $ip, 0, 45 ),
			'created_at'    => current_time( 'mysql' ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	if ( ! $inserted ) {
		// UNIQUE 制約違反＝レースで同時刻が埋まった。
		wp_safe_redirect( add_query_arg( 'ob_error', 'taken', $page ) );
		exit;
	}
	$booking_id = (int) $wpdb->insert_id;

	/**
	 * ▼▼▼ フェーズ2 Google連携 差し込み口 ▼▼▼
	 * Google Calendar にイベント作成（Meet URL発行）し、結果を保存する。
	 * メール送信より前に実行することで、確認メールに Meet URL が載る
	 * （mail.php は meet_link があれば自動でURL表示する実装済み）。
	 * API障害時は false が返り gcal_event_id/meet_link は NULL のまま＝DB予約は維持
	 * （仕様§3 フォールバック）。
	 * ▲▲▲ フェーズ2 Google連携 差し込み口 ▲▲▲
	 */
	if ( function_exists( 'bankofart_booking_gcal_create_event' ) ) {
		$gc = bankofart_booking_gcal_create_event( $booking_id );
		if ( $gc ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'gcal_event_id' => $gc['event_id'],
					'meet_link'     => $gc['meet_link'],
				),
				array( 'id' => $booking_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	// メール2通（連携成功時は Meet URL 入り、失敗時はプレースホルダ）。
	bankofart_send_booking_user_mail( $booking_id );
	bankofart_send_booking_admin_mail( $booking_id );

	// 完了画面へ（PRG）。サマリをトランジェントに退避し、URLにPIIを載せない。
	$thanks_key = 'boa_bk_done_' . wp_generate_password( 16, false );
	set_transient(
		$thanks_key,
		array(
			'booked_at' => $booked_at,
			'name'      => $name,
		),
		HOUR_IN_SECONDS
	);
	wp_safe_redirect( add_query_arg( 'ob_thanks', $thanks_key, $page ) );
	exit;
}
add_action( 'admin_post_nopriv_boa_booking_reserve', 'bankofart_handle_booking_reserve' );
add_action( 'admin_post_boa_booking_reserve', 'bankofart_handle_booking_reserve' );

/**
 * "YYYY-MM-DD HH:MM" が有効な予約枠か（範囲・30分整合・スロット一致）を判定する。
 *
 * @param string $booked_at 予約日時。
 * @return bool
 */
function bankofart_booking_is_valid_slot( $booked_at ) {
	if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$/', $booked_at, $m ) ) {
		return false;
	}
	$date = $m[1];
	$time = $m[2];
	// 日付範囲（当日〜30日先）。
	$today    = current_time( 'Y-m-d' );
	$max_date = gmdate( 'Y-m-d', strtotime( $today . ' +' . BANKOFART_BOOKING_DAYS_AHEAD . ' days' ) );
	if ( $date < $today || $date > $max_date ) {
		return false;
	}
	// スロット一致（09:00〜22:30・30分）。
	if ( ! in_array( $time, bankofart_booking_all_slots(), true ) ) {
		return false;
	}
	// 当日なら過去時刻不可。
	if ( $date === $today ) {
		list( $h, $i ) = array_map( 'intval', explode( ':', $time ) );
		$now_min = (int) current_time( 'G' ) * 60 + (int) current_time( 'i' );
		if ( $h * 60 + $i <= $now_min ) {
			return false;
		}
	}
	return true;
}
