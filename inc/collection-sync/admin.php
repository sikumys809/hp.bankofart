<?php
/**
 * コレクションサイト同期：管理画面と定期実行
 *
 * 月1回の自動取得（WP-Cron）と、管理画面からの手動取得。
 * 取得結果は wp_options の boa_collection_last_sync に残す。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 定期実行のフック名。 */
define( 'BANKOFART_COLLECTION_CRON_HOOK', 'bankofart_collection_sync_cron' );

/**
 * 月1回のスケジュールを登録する（WP標準に monthly が無いため追加）。
 *
 * @param array $schedules 既存スケジュール。
 * @return array
 */
function bankofart_collection_cron_schedule( $schedules ) {
	if ( ! isset( $schedules['boa_monthly'] ) ) {
		$schedules['boa_monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS,
			'display'  => __( '月1回（BOA）', 'bankofart' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'bankofart_collection_cron_schedule' ); // phpcs:ignore WordPress.WP.CronInterval

/**
 * 定期実行を予約する（未予約のときだけ）。
 *
 * @return void
 */
function bankofart_collection_schedule_cron() {
	if ( ! wp_next_scheduled( BANKOFART_COLLECTION_CRON_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'boa_monthly', BANKOFART_COLLECTION_CRON_HOOK );
	}
}
add_action( 'init', 'bankofart_collection_schedule_cron' );
add_action( BANKOFART_COLLECTION_CRON_HOOK, 'bankofart_collection_sync_all' );

/**
 * 管理メニューを追加する。
 *
 * @return void
 */
function bankofart_collection_admin_menu() {
	add_menu_page(
		__( 'コレクション同期', 'bankofart' ),
		__( 'コレクション同期', 'bankofart' ),
		'manage_options',
		'boa-collection-sync',
		'bankofart_collection_admin_page',
		'dashicons-images-alt2',
		59
	);
}
add_action( 'admin_menu', 'bankofart_collection_admin_menu' );

/**
 * 手動取得の受け口。
 *
 * @return void
 */
function bankofart_collection_handle_sync() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}
	check_admin_referer( 'boa_collection_sync' );

	$r = bankofart_collection_sync_all();

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'boa-collection-sync',
				'synced'  => 1,
				'arts'    => (int) $r['arts'],
				'artists' => (int) $r['artists'],
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_boa_collection_sync', 'bankofart_collection_handle_sync' );

/**
 * 切り抜き画像の生成を手で進める受け口。
 *
 * @return void
 */
function bankofart_collection_handle_rects() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}
	check_admin_referer( 'boa_collection_rects' );

	$r = bankofart_collection_build_pending( 40 );

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'   => 'boa-collection-sync',
				'built'  => (int) $r['built'],
				'failed' => (int) $r['failed'],
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_boa_collection_rects', 'bankofart_collection_handle_rects' );

/**
 * 管理画面を描画する。
 *
 * @return void
 */
function bankofart_collection_admin_page() {
	global $wpdb;
	$table = bankofart_collection_table();

	$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB
	$by_art  = $wpdb->get_results( "SELECT artist_slug, COUNT(*) c FROM {$table} GROUP BY artist_slug ORDER BY c DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
	$last    = get_option( 'boa_collection_last_sync', array() );
	$next_ts = wp_next_scheduled( BANKOFART_COLLECTION_CRON_HOOK );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'コレクション同期', 'bankofart' ); ?></h1>

		<?php if ( isset( $_GET["picked"] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( "TOPに出す作品を保存しました。", "bankofart" ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET["synced"] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: artists, 2: artworks */
						esc_html__( '取得しました：アーティスト %1$d名 / 作品 %2$d点', 'bankofart' ),
						(int) ( $_GET['artists'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
						(int) ( $_GET['arts'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['built'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					printf(
						/* translators: 1: built, 2: failed */
						esc_html__( '切り抜き画像を %1$d 点 生成しました（失敗 %2$d 点）。', 'bankofart' ),
						(int) ( $_GET['built'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
						(int) ( $_GET['failed'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<p>
			<?php
			printf(
				/* translators: %s: collection site URL */
				esc_html__( '%s の作品データをWPに取り込みます。作品名や価格は向こうが正で、ここでは編集できません。', 'bankofart' ),
				'<code>' . esc_html( BANKOFART_COLLECTION_URL ) . '</code>'
			);
			?>
		</p>

		<table class="widefat" style="max-width:720px;margin:16px 0;">
			<tbody>
				<tr>
					<th style="width:180px;"><?php esc_html_e( '保存済みの作品', 'bankofart' ); ?></th>
					<td><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> 点</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '前回の取得', 'bankofart' ); ?></th>
					<td>
						<?php if ( ! empty( $last['at'] ) ) : ?>
							<?php echo esc_html( $last['at'] ); ?>
							（アーティスト <?php echo esc_html( (int) $last['artists'] ); ?>名 /
							作品 <?php echo esc_html( (int) $last['arts'] ); ?>点<?php
							if ( ! empty( $last['deleted'] ) ) {
								echo ' / 削除 ' . esc_html( (int) $last['deleted'] ) . '点';
							}
							?>）
						<?php else : ?>
							<em><?php esc_html_e( 'まだ取得していません', 'bankofart' ); ?></em>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '切り抜き画像', 'bankofart' ); ?></th>
					<td>
						<?php
						$rect = bankofart_collection_rect_status();
						printf(
							/* translators: 1: built, 2: total */
							esc_html__( '%1$d / %2$d 点 生成済み', 'bankofart' ),
							(int) ( $rect['total'] - $rect['pending'] ),
							(int) $rect['total']
						);
						?>
						<?php if ( $rect['pending'] > 0 ) : ?>
							<span style="color:#b32d2e;margin-left:8px;">
								<?php
								printf(
									/* translators: %d: pending count */
									esc_html__( '未生成 %d 点', 'bankofart' ),
									(int) $rect['pending']
								);
								?>
							</span>
						<?php endif; ?>
						<p class="description" style="margin:4px 0 0;">
							<?php esc_html_e( 'collection 側の手作業の切り抜き（四隅の指定）をもとに、作品だけを正面から見た形に起こした画像です。', 'bankofart' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '次回の自動取得', 'bankofart' ); ?></th>
					<td>
						<?php
						echo $next_ts
							? esc_html( wp_date( 'Y-m-d H:i', $next_ts ) ) . '（月1回）'
							: esc_html__( '未予約', 'bankofart' );
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $last['errors'] ) ) : ?>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( '前回の取得で問題がありました', 'bankofart' ); ?></strong></p>
				<ul style="list-style:disc;padding-left:20px;">
					<?php foreach ( (array) $last['errors'] as $e ) : ?>
						<li><?php echo esc_html( $e ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><?php esc_html_e( '※ 問題があった回は、既存データを消さずに残しています。', 'bankofart' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px;">
			<?php wp_nonce_field( 'boa_collection_sync' ); ?>
			<input type="hidden" name="action" value="boa_collection_sync">
			<?php submit_button( __( '今すぐ取得する', 'bankofart' ), 'primary', 'submit', false ); ?>
			<span class="description" style="margin-left:10px;">
				<?php esc_html_e( '20ページほど巡回するので、30秒前後かかります。切り抜き画像も続けて生成します。', 'bankofart' ); ?>
			</span>
		</form>

		<?php if ( $rect['pending'] > 0 ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'boa_collection_rects' ); ?>
				<input type="hidden" name="action" value="boa_collection_rects">
				<?php submit_button( __( '切り抜き画像の続きを作る', 'bankofart' ), 'secondary', 'submit', false ); ?>
				<span class="description" style="margin-left:10px;">
					<?php esc_html_e( '1回で40秒ぶんだけ進みます。残りは5分おきに自動で進みます。', 'bankofart' ); ?>
				</span>
			</form>
		<?php endif; ?>

		<?php
		/* ── TOPに出す作品の選択（3×2の6点）── */
		$picks   = bankofart_collection_get_top_picks();
		$options = function_exists( 'bankofart_collection_pick_options' ) ? bankofart_collection_pick_options() : array();
		if ( $options ) :
			?>
			<h2 style="margin-top:40px;"><?php esc_html_e( 'TOPページに出す作品', 'bankofart' ); ?></h2>
			<p class="description" style="margin-bottom:12px;">
				<?php esc_html_e( 'TOPページのART欄に3列×2段で並びます。上の枠から順に配置されます。選ばなかった枠は新着作品で埋まります。', 'bankofart' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'boa_collection_top_picks' ); ?>
				<input type="hidden" name="action" value="boa_collection_top_picks">

				<table class="widefat striped" style="max-width:820px;">
					<tbody>
						<?php for ( $i = 0; $i < BANKOFART_TOP_PICK_COUNT; $i++ ) : ?>
							<?php $current = $picks[ $i ] ?? ''; ?>
							<tr>
								<th style="width:110px;">
									<?php
									printf(
										/* translators: %d: slot number */
										esc_html__( '%d枠目', 'bankofart' ),
										(int) $i + 1
									);
									?>
								</th>
								<td>
									<select name="pick_<?php echo esc_attr( $i ); ?>" style="width:100%;max-width:620px;">
										<option value=""><?php esc_html_e( '— 選択しない（新着で埋める）—', 'bankofart' ); ?></option>
										<?php foreach ( $options as $artist_name => $items ) : ?>
											<optgroup label="<?php echo esc_attr( $artist_name ); ?>">
												<?php foreach ( $items as $cid => $label ) : ?>
													<option value="<?php echo esc_attr( $cid ); ?>"<?php selected( $current, $cid ); ?>>
														<?php echo esc_html( $label ); ?>
													</option>
												<?php endforeach; ?>
											</optgroup>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endfor; ?>
					</tbody>
				</table>

				<?php submit_button( __( 'TOPの作品を保存', 'bankofart' ) ); ?>
			</form>
		<?php endif; ?>

		<?php if ( $by_art ) : ?>
			<h2 style="margin-top:32px;"><?php esc_html_e( 'アーティスト別の作品数', 'bankofart' ); ?></h2>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'スラッグ', 'bankofart' ); ?></th>
						<th><?php esc_html_e( 'WPのアーティスト', 'bankofart' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( '作品数', 'bankofart' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $by_art as $row ) :
						$wp_post = get_page_by_path( $row['artist_slug'], OBJECT, 'artist' );
						?>
						<tr>
							<td><code><?php echo esc_html( $row['artist_slug'] ); ?></code></td>
							<td>
								<?php if ( $wp_post ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $wp_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $wp_post ) ); ?></a>
								<?php else : ?>
									<span style="color:#b32d2e;">— 対応するアーティストがWPにありません</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (int) $row['c'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
