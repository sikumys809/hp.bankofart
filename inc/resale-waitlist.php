<?php
/**
 * リセール待機リスト機能（Notion「リセール待機リスト機能 実装指示書」準拠）
 *
 * 指示書は ACF/CPT「artwork」前提だが、本テーマは Meta Box / CPT「art」/
 * タクソノミー art_status（AVAILABLE/OWNED）で実装している。読み替え対応：
 *   - get_field('stock_status') → art_status タクソノミー（has_term 'OWNED'）
 *   - get_field('work_number')  → rwmb_meta('art_number')（作品NO.）
 *   - CPT artwork / single-artwork.php / artwork アーカイブ → art / single-art.php / archive-art.php
 *
 * 内容：カスタムテーブル作成 / フォーム送信処理（admin-post）/ 管理画面（一覧・ステータス・CSV）。
 * フォーム表示は page-resale-waitlist.php、作品ページのボタンは single-art.php。
 * プラグイン不使用・WordPress 標準機能のみ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** DBスキーマのバージョン（変更時に上げると dbDelta 再実行）。 */
define( 'BANKOFART_RESALE_DB_VERSION', '1.0.0' );

/**
 * 待機リストテーブル名（$wpdb->prefix 前置）。
 *
 * @return string
 */
function bankofart_resale_table() {
	global $wpdb;
	return $wpdb->prefix . 'boa_resale_waitlist';
}

/**
 * カスタムテーブルを作成/更新する（dbDelta）。
 *
 * @return void
 */
function bankofart_resale_create_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = bankofart_resale_table();
	$charset = $wpdb->get_charset_collate();

	// dbDelta はフォーマットに厳格（2スペースインデント・型表記・PRIMARY KEY 記法）。
	$sql = "CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  artwork_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  artwork_title VARCHAR(255) NOT NULL DEFAULT '',
  artwork_number VARCHAR(50) NOT NULL DEFAULT '',
  name VARCHAR(100) NOT NULL DEFAULT '',
  company VARCHAR(150) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL DEFAULT '',
  tel VARCHAR(50) NOT NULL DEFAULT '',
  message TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY status (status),
  KEY created_at (created_at)
) {$charset};";

	dbDelta( $sql );
	update_option( 'boa_resale_db_version', BANKOFART_RESALE_DB_VERSION );
}

/**
 * 必要に応じてテーブルを作成する（テーマ有効化時＋管理画面アクセス時のバージョン差分検知）。
 *
 * @return void
 */
function bankofart_resale_maybe_upgrade() {
	if ( get_option( 'boa_resale_db_version' ) !== BANKOFART_RESALE_DB_VERSION ) {
		bankofart_resale_create_table();
	}
}
add_action( 'after_switch_theme', 'bankofart_resale_create_table' );
add_action( 'admin_init', 'bankofart_resale_maybe_upgrade' );

/* =========================================================
 * フォーム送信処理（admin-post.php）
 * ======================================================= */

/**
 * リセール待機リスト登録の送信を処理する。
 *
 * @return void
 */
function bankofart_handle_resale_waitlist() {
	// 1) nonce 検証。
	check_admin_referer( 'boa_resale_waitlist', 'boa_resale_nonce' );

	// 2) 取得・サニタイズ。
	$data = array(
		'artwork_id'     => isset( $_POST['artwork_id'] ) ? absint( wp_unslash( $_POST['artwork_id'] ) ) : 0,
		'artwork_title'  => isset( $_POST['artwork_title'] ) ? sanitize_text_field( wp_unslash( $_POST['artwork_title'] ) ) : '',
		'artwork_number' => isset( $_POST['artwork_number'] ) ? sanitize_text_field( wp_unslash( $_POST['artwork_number'] ) ) : '',
		'name'           => isset( $_POST['boa_name'] ) ? sanitize_text_field( wp_unslash( $_POST['boa_name'] ) ) : '',
		'company'        => isset( $_POST['boa_company'] ) ? sanitize_text_field( wp_unslash( $_POST['boa_company'] ) ) : '',
		'email'          => isset( $_POST['boa_email'] ) ? sanitize_email( wp_unslash( $_POST['boa_email'] ) ) : '',
		'tel'            => isset( $_POST['boa_tel'] ) ? sanitize_text_field( wp_unslash( $_POST['boa_tel'] ) ) : '',
		'message'        => isset( $_POST['boa_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['boa_message'] ) ) : '',
		'privacy'        => isset( $_POST['boa_privacy'] ) ? 1 : 0,
	);

	// 3) バリデーション。
	$errors = array();
	if ( '' === $data['name'] ) {
		$errors['boa_name'] = 'お名前をご入力ください。';
	}
	if ( '' === $data['email'] || ! is_email( $data['email'] ) ) {
		$errors['boa_email'] = '有効なメールアドレスをご入力ください。';
	}
	if ( '' === $data['artwork_title'] ) {
		$errors['artwork_title'] = '希望作品をご入力ください。';
	}
	if ( ! $data['privacy'] ) {
		$errors['boa_privacy'] = '個人情報の取り扱いへの同意が必要です。';
	}

	$redirect_back = wp_get_referer();
	if ( ! $redirect_back ) {
		$redirect_back = home_url( '/resale-waitlist/' );
	}

	if ( ! empty( $errors ) ) {
		// エラー：入力値とエラーをトランジェントへ退避（5分）→ フォームへ戻す。
		$key = 'boa_resale_' . wp_generate_password( 12, false );
		set_transient(
			$key,
			array(
				'errors' => $errors,
				'values' => $data,
			),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'resale_error', $key, $redirect_back ) );
		exit;
	}

	// 4) DB保存。
	global $wpdb;
	$table = bankofart_resale_table();
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'artwork_id'     => $data['artwork_id'],
			'artwork_title'  => $data['artwork_title'],
			'artwork_number' => $data['artwork_number'],
			'name'           => $data['name'],
			'company'        => $data['company'],
			'email'          => $data['email'],
			'tel'            => $data['tel'],
			'message'        => $data['message'],
			'status'         => 'new',
			'created_at'     => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	// 5) メール2通。
	bankofart_resale_send_mails( $data );

	// 6) 完了画面へ（PRG）。
	wp_safe_redirect( add_query_arg( 'resale_done', '1', $redirect_back ) );
	exit;
}
add_action( 'admin_post_nopriv_boa_resale_waitlist', 'bankofart_handle_resale_waitlist' );
add_action( 'admin_post_boa_resale_waitlist', 'bankofart_handle_resale_waitlist' );

/**
 * 管理者通知＋登録者自動返信メールを送る。
 *
 * @param array $data 検証済み入力値。
 * @return void
 */
function bankofart_resale_send_mails( $data ) {
	$default_admin = defined( 'BANKOFART_CONTACT_EMAIL' ) ? BANKOFART_CONTACT_EMAIL : get_option( 'admin_email' );
	$admin_email   = apply_filters( 'bankofart_resale_admin_email', $default_admin );
	$headers       = function_exists( 'bankofart_mail_headers' ) ? bankofart_mail_headers() : array( 'Content-Type: text/plain; charset=UTF-8' );
	$site_name     = get_bloginfo( 'name' );
	$now           = current_time( 'Y-m-d H:i' );

	// (A) 管理者宛。
	$admin_subject = sprintf( '【リセール待機登録】%s %s / %s様', $data['artwork_number'], $data['artwork_title'], $data['name'] );
	$admin_body    = implode(
		"\n",
		array(
			'リセール待機リストに新しい登録がありました。',
			'',
			'■ 希望作品：' . $data['artwork_title'],
			'■ 作品番号：' . $data['artwork_number'],
			'■ 作品ID　：' . $data['artwork_id'],
			'■ お名前　：' . $data['name'],
			'■ 会社名　：' . $data['company'],
			'■ メール　：' . $data['email'],
			'■ 電話　　：' . $data['tel'],
			'■ ご希望　：' . $data['message'],
			'■ 登録日時：' . $now,
			'',
			'※ WP管理画面「リセール待機リスト」からも確認できます。',
		)
	);
	// 【一時デバッグ】管理者宛（届かない方）の実宛先・From・返り値を記録。解決後に削除。
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'resale:ADMIN called. to=' . var_export( $admin_email, true ) . ' | is_array=' . ( is_array( $admin_email ) ? 'yes' : 'no' ) . ' | headers=' . wp_json_encode( $headers, JSON_UNESCAPED_UNICODE ) );
	}
	$admin_sent = wp_mail( $admin_email, $admin_subject, $admin_body, $headers );
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'resale:ADMIN wp_mail return=' . var_export( $admin_sent, true ) );
	}

	// (B) 登録者宛 自動返信（※「購入確約ではない・対面契約」の注意書きを必ず含む）。
	$reply_subject = '【バンク・オブ・アート】リセール待機リストご登録ありがとうございます';
	$reply_body    = implode(
		"\n",
		array(
			$data['name'] . ' 様',
			'',
			'この度は、リセール待機リストにご登録いただきありがとうございます。',
			'下記の作品について、ご登録を受け付けいたしました。',
			'',
			'■ 希望作品：' . $data['artwork_title'],
			( '' !== $data['artwork_number'] ? '■ 作品番号：' . $data['artwork_number'] : '' ),
			'',
			'当該作品がリセールにより入荷した際は、担当者より順次ご案内いたします。',
			'',
			'――――――――――――――――――――',
			'※ 本登録は購入のお約束ではなく、入荷時のご案内を目的としたものです。',
			'　 ご成約は対面でのご契約となります。あらかじめご了承ください。',
			'――――――――――――――――――――',
			'',
			$site_name,
		)
	);
	// 空行（artwork_number 無し時の空要素）を除去。
	$reply_body = preg_replace( "/\n{3,}/", "\n\n", $reply_body );
	// 【一時デバッグ】登録者宛（届く方）の返り値を記録。解決後に削除。
	$reply_sent = wp_mail( $data['email'], $reply_subject, $reply_body, $headers );
	if ( function_exists( 'bankofart_maildbg_log' ) ) {
		bankofart_maildbg_log( 'resale:USER(reply) to=' . var_export( $data['email'], true ) . ' return=' . var_export( $reply_sent, true ) );
	}
}

/* =========================================================
 * 管理画面（一覧・ステータス変更・CSVエクスポート）
 * ======================================================= */

/**
 * 管理メニューを追加する。
 *
 * @return void
 */
function bankofart_resale_admin_menu() {
	add_menu_page(
		'リセール待機リスト',
		'リセール待機リスト',
		'manage_options',
		'boa-resale-waitlist',
		'bankofart_resale_admin_page',
		'dashicons-list-view',
		26
	);
}
add_action( 'admin_menu', 'bankofart_resale_admin_menu' );

/**
 * 管理一覧ページを描画する（created_at DESC・ページネーション・ステータス変更）。
 *
 * @return void
 */
function bankofart_resale_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}

	global $wpdb;
	$table = bankofart_resale_table();

	// ステータス更新（POST）。
	$updated = false;
	if ( isset( $_POST['boa_resale_status_update'] ) ) {
		check_admin_referer( 'boa_resale_status', 'boa_resale_status_nonce' );
		$row_id     = isset( $_POST['row_id'] ) ? absint( $_POST['row_id'] ) : 0;
		$new_status = isset( $_POST['new_status'] ) ? sanitize_text_field( wp_unslash( $_POST['new_status'] ) ) : '';
		if ( $row_id && in_array( $new_status, array( 'new', 'contacted', 'closed' ), true ) ) {
			$wpdb->update( $table, array( 'status' => $new_status ), array( 'id' => $row_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$updated = true;
		}
	}

	// ページネーション。
	$per_page = 30;
	$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$offset   = ( $paged - 1 ) * $per_page;
	$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) ); // phpcs:ignore WordPress.DB
	$pages    = max( 1, (int) ceil( $total / $per_page ) );

	$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=boa_resale_export' ), 'boa_resale_export', 'boa_resale_export_nonce' );
	$status_labels = array(
		'new'       => '新規',
		'contacted' => '連絡済み',
		'closed'    => '完了',
	);
	?>
	<div class="wrap">
		<h1>リセール待機リスト <span class="title-count theme-count"><?php echo esc_html( (string) $total ); ?></span></h1>
		<?php if ( $updated ) : ?>
			<div class="notice notice-success is-dismissible"><p>ステータスを更新しました。</p></div>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $export_url ); ?>" class="button button-primary">CSVダウンロード</a></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>登録日時</th><th>作品名</th><th>作品番号</th><th>氏名</th><th>会社名</th>
					<th>メール</th><th>電話</th><th>ご希望</th><th>ステータス</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $rows ) ) : ?>
				<tr><td colspan="9">登録はまだありません。</td></tr>
			<?php else : ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td><?php echo esc_html( $row->artwork_title ); ?></td>
						<td><?php echo esc_html( $row->artwork_number ); ?></td>
						<td><?php echo esc_html( $row->name ); ?></td>
						<td><?php echo esc_html( $row->company ); ?></td>
						<td><?php echo esc_html( $row->email ); ?></td>
						<td><?php echo esc_html( $row->tel ); ?></td>
						<td><?php echo esc_html( $row->message ); ?></td>
						<td>
							<form method="post" style="display:flex;gap:4px;align-items:center;">
								<?php wp_nonce_field( 'boa_resale_status', 'boa_resale_status_nonce' ); ?>
								<input type="hidden" name="row_id" value="<?php echo esc_attr( (string) $row->id ); ?>">
								<select name="new_status">
									<?php foreach ( $status_labels as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $row->status, $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="submit" name="boa_resale_status_update" value="1" class="button button-small">更新</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $pages,
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * CSVエクスポート（UTF-8 BOM付き・nonce保護・権限チェック）。
 *
 * @return void
 */
function bankofart_resale_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}
	check_admin_referer( 'boa_resale_export', 'boa_resale_export_nonce' );

	global $wpdb;
	$table = bankofart_resale_table();
	$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB

	$filename = 'resale-waitlist-' . gmdate( 'Ymd-His' ) . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$out = fopen( 'php://output', 'w' );
	echo "\xEF\xBB\xBF"; // UTF-8 BOM（Excel 文字化け防止）。
	fputcsv( $out, array( '登録日時', '作品名', '作品番号', '作品ID', '氏名', '会社名', 'メール', '電話', 'ご希望', 'ステータス' ) );
	if ( $rows ) {
		foreach ( $rows as $r ) {
			fputcsv(
				$out,
				array(
					$r['created_at'], $r['artwork_title'], $r['artwork_number'], $r['artwork_id'],
					$r['name'], $r['company'], $r['email'], $r['tel'], $r['message'], $r['status'],
				)
			);
		}
	}
	fclose( $out );
	exit;
}
add_action( 'admin_post_boa_resale_export', 'bankofart_resale_export_csv' );
