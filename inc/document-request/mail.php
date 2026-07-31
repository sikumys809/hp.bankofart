<?php
/**
 * 資料請求フォーム：メール送信（仕様§7）
 *   - 申込者向け自動返信（PDFダウンロードリンク付き）
 *   - 管理者通知（BANKOFART_DOC_REQUEST_ADMIN_EMAILS 宛）
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 申込者への自動返信メール（DLリンク付き）。
 *
 * @param int    $insert_id 申込ID。
 * @param string $token     DLトークン。
 * @return bool
 */
function bankofart_send_doc_request_user_mail( $insert_id, $token ) {
	global $wpdb;
	$table = bankofart_doc_request_table();
	$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $insert_id ) ); // phpcs:ignore WordPress.DB
	if ( ! $req ) {
		return false;
	}

	$download_url = add_query_arg( 'boa_pdf_download', rawurlencode( $token ), home_url( '/' ) );
	$briefing_url = bankofart_briefing_url(); // 説明会予約（現状は外部URL。後で自前ページへ）。
	$home         = home_url( '/' );

	$subject = '【BANK OF ART】資料請求ありがとうございます';
	$lines   = array(
		$req->contact_name . ' 様',
		'',
		'このたびはBANK OF ART のサービス資料をご請求いただき、誠にありがとうございます。',
		'下記URLより、サービス資料（PDF）をダウンロードいただけます。',
		'',
		'▼ サービス資料ダウンロード',
		$download_url,
		'',
		'────────────────────────',
		'ご請求内容',
		'────────────────────────',
		'会社名：' . $req->company_name,
		'お名前：' . $req->contact_name,
		'業種　：' . $req->industry,
		'興味度：' . $req->interest_level,
		'',
		'────────────────────────',
		'次のステップ',
		'────────────────────────',
		'資料をご覧いただき、より具体的なお話を聞きたい場合は、',
		'ぜひオンライン説明会へお越しください。',
		'30分程度で、貴社の課題に合わせた活用方法をご提案いたします。',
		'',
		'▼ オンライン説明会のご予約',
		$briefing_url,
		'',
		'ご不明な点がございましたら、本メールへご返信ください。',
		'',
		'────────────────────────',
		'BANK of ART　絵描きの明日を創出する。',
		$home,
		'株式会社シクミーズ',
		'────────────────────────',
	);
	$body    = implode( "\n", $lines );

	$headers = bankofart_mail_headers();

	// 【一時デバッグ】申込者宛（届く方）の返り値を記録。解決後に削除。
	$sent = wp_mail( $req->email, $subject, $body, $headers );
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'doc:USER  called. to=' . var_export( $req->email, true ) . ' return=' . var_export( $sent, true ) );
	}
	return $sent;
}

/**
 * 管理者通知メール。
 *
 * @param int $insert_id 申込ID。
 * @return bool
 */
function bankofart_send_doc_request_admin_mail( $insert_id ) {
	global $wpdb;
	$table = bankofart_doc_request_table();
	$req   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $insert_id ) ); // phpcs:ignore WordPress.DB
	if ( ! $req ) {
		return false;
	}

	$to = ( defined( 'BANKOFART_DOC_REQUEST_ADMIN_EMAILS' ) && ! empty( BANKOFART_DOC_REQUEST_ADMIN_EMAILS ) )
		? BANKOFART_DOC_REQUEST_ADMIN_EMAILS
		: get_option( 'admin_email' );

	$subject = sprintf( '【資料請求】%s %s 様より', $req->company_name, $req->contact_name );
	$lines   = array(
		'新規の資料請求がありました。',
		'',
		'──────────────────────',
		'申込内容',
		'──────────────────────',
		'申込日時：' . $req->created_at,
		'会社名　：' . $req->company_name,
		'担当者　：' . $req->contact_name . '（' . $req->contact_name_kana . '）',
		'役職　　：' . $req->position,
		'業種　　：' . $req->industry,
		'興味度　：' . $req->interest_level,
		'きっかけ：' . $req->referral_source,
		'',
		'ご質問・ご要望：',
		(string) $req->message,
		'',
		'──────────────────────',
		'連絡先',
		'──────────────────────',
		'メール：' . $req->email,
		'電話　：' . $req->phone,
		'',
		'──────────────────────',
		'管理画面',
		'──────────────────────',
		admin_url( 'admin.php?page=boa-document-requests' ),
	);
	$body    = implode( "\n", $lines );

	$headers = bankofart_mail_headers();

	// 【一時デバッグ】管理者宛（届かない方）の実宛先・From・返り値を記録。解決後に削除。
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'doc:ADMIN called. to=' . var_export( $to, true ) . ' | is_array=' . ( is_array( $to ) ? 'yes' : 'no' ) . ' | headers=' . wp_json_encode( $headers, JSON_UNESCAPED_UNICODE ) );
	}
	$sent = wp_mail( $to, $subject, $body, $headers );
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'doc:ADMIN wp_mail return=' . var_export( $sent, true ) );
	}
	return $sent;
}
