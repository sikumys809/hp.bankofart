<?php
/**
 * CSVインポート：管理画面（メニュー・フォーム・結果表示）
 *
 * 管理メニュー「BOA CSV取込」。CSVをアップロードして ARTIST / ART を取り込む。
 * 実処理は inc/csv-import/importer.php。
 *
 * 安全側の設計：
 *   - 権限は manage_options のみ。nonce 必須。
 *   - 既定は「テスト実行（書き込まない）」。結果を確認してから本番実行する。
 *   - 新規作成時の既定ステータスは「下書き」。本名等の公開事故を防ぐため、
 *     公開は管理画面で内容を確認してから手動で行う運用とする。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理メニューを登録する。
 *
 * @return void
 */
function bankofart_csv_import_menu() {
	add_menu_page(
		'BOA CSV取込',
		'BOA CSV取込',
		'manage_options',
		'bankofart-csv-import',
		'bankofart_csv_import_page',
		'dashicons-upload',
		58
	);
}
add_action( 'admin_menu', 'bankofart_csv_import_menu' );

/**
 * アップロードされたCSVを検証し、一時ファイルのパスを返す。
 *
 * @return string|WP_Error 一時ファイルのパス。
 */
function bankofart_csv_import_validate_upload() {
	if ( empty( $_FILES['boa_csv_file'] ) || ! isset( $_FILES['boa_csv_file']['tmp_name'] ) ) {
		return new WP_Error( 'boa_csv_no_file', 'CSVファイルが選択されていません。' );
	}

	$error = isset( $_FILES['boa_csv_file']['error'] ) ? (int) $_FILES['boa_csv_file']['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_OK !== $error ) {
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return new WP_Error( 'boa_csv_no_file', 'CSVファイルが選択されていません。' );
		}
		if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
			return new WP_Error( 'boa_csv_too_large', 'ファイルサイズが大きすぎます。行を分割してお試しください。' );
		}
		return new WP_Error( 'boa_csv_upload', 'ファイルのアップロードに失敗しました。' );
	}

	$name = isset( $_FILES['boa_csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['boa_csv_file']['name'] ) ) : '';
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( 'csv' !== $ext && 'txt' !== $ext ) {
		return new WP_Error( 'boa_csv_ext', 'CSVファイル（.csv）を選択してください。' );
	}

	$tmp = isset( $_FILES['boa_csv_file']['tmp_name'] ) ? $_FILES['boa_csv_file']['tmp_name'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 直後に is_uploaded_file で検証。
	if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
		return new WP_Error( 'boa_csv_upload', 'アップロードファイルを取得できませんでした。' );
	}

	return $tmp;
}

/**
 * POSTを処理して結果レポートを返す。
 *
 * @return array|WP_Error|null 結果（未送信なら null）。
 */
function bankofart_csv_import_handle_post() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
		return null;
	}
	if ( ! isset( $_POST['boa_csv_import_nonce'] ) ) {
		return null;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'boa_csv_cap', '権限がありません。' );
	}
	check_admin_referer( 'boa_csv_import', 'boa_csv_import_nonce' );

	$tmp = bankofart_csv_import_validate_upload();
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$parsed = bankofart_csv_read_file( $tmp );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}

	$types = bankofart_csv_import_types();
	$type  = isset( $_POST['boa_csv_type'] ) ? sanitize_key( wp_unslash( $_POST['boa_csv_type'] ) ) : 'artist';
	if ( ! isset( $types[ $type ] ) ) {
		$type = 'artist';
	}

	$post_status = isset( $_POST['boa_csv_status'] ) ? sanitize_key( wp_unslash( $_POST['boa_csv_status'] ) ) : 'draft';
	if ( ! in_array( $post_status, array( 'draft', 'publish' ), true ) ) {
		$post_status = 'draft';
	}

	$ts_column = bankofart_csv_detect_timestamp_column( $parsed['headers'] );

	$report = bankofart_csv_import_rows(
		$parsed['rows'],
		array(
			'type'        => $type,
			'ts_column'   => $ts_column,
			'post_status' => $post_status,
			'dry_run'     => ! empty( $_POST['boa_csv_dry_run'] ),
			'update'      => ! empty( $_POST['boa_csv_update'] ),
			'with_images' => ! empty( $_POST['boa_csv_images'] ),
		)
	);

	$report['type']       = $type;
	$report['type_label'] = $types[ $type ]['label'];
	$report['dry_run']    = ! empty( $_POST['boa_csv_dry_run'] );
	$report['ts_column']  = $ts_column;
	$report['headers']    = $parsed['headers'];
	$report['row_count']  = count( $parsed['rows'] );
	$report['unmapped']   = bankofart_csv_unmapped_columns( $parsed['headers'], $type, $ts_column );

	return $report;
}

/**
 * 管理画面を描画する。
 *
 * @return void
 */
function bankofart_csv_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}

	$result = bankofart_csv_import_handle_post();
	$types  = bankofart_csv_import_types();
	?>
	<div class="wrap">
		<h1>BOA CSV取込</h1>

		<p>
			「所属画家情報」スプレッドシートなどから書き出したCSVをアップロードして、ARTIST / ART を取り込みます。<br>
			<strong>同じ行は二重に取り込まれません。</strong>
			CSVの「タイムスタンプ」列を各行の目印として記録するため、同じスプレッドシートを何度アップロードしても、
			前回までに取り込み済みの行は自動的にスキップされます。
		</p>
		<p>
			投稿タイトルは<strong>「アーティスト名」列</strong>を使います（本名の「苗字」「名前」列はタイトルに使いません）。<br>
			本名・連絡先・住所・振込先・生年月日はWordPressに取り込みません。
		</p>

		<?php if ( is_wp_error( $result ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $result->get_error_message() ); ?></p></div>
		<?php endif; ?>

		<?php if ( is_array( $result ) ) : ?>
			<?php bankofart_csv_import_render_report( $result ); ?>
		<?php endif; ?>

		<h2>CSVをアップロード</h2>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'boa_csv_import', 'boa_csv_import_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="boa_csv_type">取り込む種別</label></th>
					<td>
						<select name="boa_csv_type" id="boa_csv_type">
							<?php foreach ( $types as $key => $def ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="boa_csv_file">CSVファイル</label></th>
					<td>
						<input type="file" name="boa_csv_file" id="boa_csv_file" accept=".csv,text/csv" required>
						<p class="description">1行目を見出し行として読み込みます。UTF-8 / Shift_JIS どちらでも可。1回につき最大 <?php echo esc_html( (string) BANKOFART_CSV_MAX_ROWS ); ?> 行。</p>
					</td>
				</tr>
				<tr>
					<th scope="row">新規作成時のステータス</th>
					<td>
						<label><input type="radio" name="boa_csv_status" value="draft" checked> 下書き（推奨）</label><br>
						<label><input type="radio" name="boa_csv_status" value="publish"> 公開</label>
						<p class="description">本名など非公開情報の公開事故を防ぐため、まず下書きで取り込み、内容を確認してから公開することを推奨します。</p>
					</td>
				</tr>
				<tr>
					<th scope="row">オプション</th>
					<td>
						<label><input type="checkbox" name="boa_csv_dry_run" value="1" checked> <strong>テスト実行</strong>（書き込まずに結果だけ確認する）</label><br>
						<label><input type="checkbox" name="boa_csv_update" value="1"> 取り込み済みの行も上書き更新する</label>
						<p class="description">通常はオフのままにしてください。オンにすると、既に取り込んだ行の内容でWP側を上書きします（手動で直した内容が消えます）。</p>
						<label><input type="checkbox" name="boa_csv_images" value="1"> 画像URLの列があれば画像も取り込む</label>
						<p class="description">http(s) で直接ダウンロードできる画像URLのみ対応します。Googleドライブの共有リンクは取り込めません。</p>
					</td>
				</tr>
			</table>
			<?php submit_button( '実行する' ); ?>
		</form>

		<hr>
		<h2>読み込める列名</h2>
		<p class="description">見出し行の列名は下記のいずれかであれば自動で対応付けられます（大文字小文字・全角半角の違いは無視されます）。該当しない列は無視されます。</p>
		<?php foreach ( $types as $key => $def ) : ?>
			<h3><?php echo esc_html( $def['label'] ); ?></h3>
			<table class="widefat striped" style="max-width:960px;">
				<thead><tr><th style="width:30%;">取り込み先</th><th>CSVの列名（いずれか）</th></tr></thead>
				<tbody>
					<tr>
						<td><strong>タイトル</strong>（必須）</td>
						<td><?php echo esc_html( implode( ' / ', $def['title'] ) ); ?></td>
					</tr>
					<tr>
						<td>スラッグ</td>
						<td><?php echo esc_html( implode( ' / ', $def['slug'] ) ); ?></td>
					</tr>
					<?php foreach ( $def['meta'] as $field_id => $aliases ) : ?>
						<tr>
							<td><code><?php echo esc_html( $field_id ); ?></code></td>
							<td>
								<?php echo esc_html( implode( ' / ', $aliases ) ); ?>
								<?php if ( 'artist_catch_phrase' === $field_id ) : ?>
									<br><span class="description">専用列が無い場合は「制作テーマ（13字以内）」の内容がそのまま入ります。</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! empty( $def['derived'] ) ) : ?>
						<?php foreach ( $def['derived'] as $field_id => $unused_cb ) : ?>
							<tr>
								<td><code><?php echo esc_html( $field_id ); ?></code>（自動生成）</td>
								<td>
									<?php if ( 'artist_name_en' === $field_id ) : ?>
										専用列が無い場合、<strong>「アーティスト名」を英字大文字に変換</strong>して入れます（例：Taiki → TAIKI）。<br>
										<span class="description">アーティスト名が日本語の場合は自動生成せず、結果画面でお知らせします（編集画面で手入力してください）。本名の読みである「フリガナ」列は使いません。</span>
									<?php elseif ( 'artist_theme_keywords' === $field_id ) : ?>
										専用列が無い場合、<strong>「診断タグ」列の内容をカンマ区切りで</strong>入れます（例：<code>生命エネルギー,挑戦,格闘</code>）。<br>
										<span class="description">診断タグとテーマキーワードは同じ語彙を使う前提です。登録済みタグに無い語も、テーマキーワードにはそのまま入ります。</span>
									<?php else : ?>
										他の列から自動生成します。
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					<?php foreach ( $def['tax'] as $taxonomy => $aliases ) : ?>
						<tr>
							<td><code><?php echo esc_html( $taxonomy ); ?></code>（分類）</td>
							<td><?php echo esc_html( implode( ' / ', $aliases ) ); ?>　<span class="description">複数値は <code>;</code> <code>|</code> <code>,</code> 区切り</span></td>
						</tr>
					<?php endforeach; ?>
					<?php foreach ( $def['images'] as $field_id => $conf ) : ?>
						<tr>
							<td><code><?php echo esc_html( $field_id ); ?></code>（画像）</td>
							<td><?php echo esc_html( implode( ' / ', $conf[1] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( ! empty( $def['relation'] ) ) : ?>
						<tr>
							<td>アーティストとの関連付け</td>
							<td><?php echo esc_html( implode( ' / ', $def['relation']['aliases'] ) ); ?>　<span class="description">アーティストのスラッグまたは名前</span></td>
						</tr>
					<?php endif; ?>
					<?php if ( ! empty( $def['ignored'] ) ) : ?>
						<tr>
							<td><strong>取り込まない列</strong></td>
							<td>
								<?php echo esc_html( implode( ' / ', $def['ignored'] ) ); ?><br>
								<span class="description">個人情報・契約情報はWordPressでは管理しない方針のため、これらの列は読み飛ばします。</span>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<h3>重複判定に使う列名</h3>
		<p><?php echo esc_html( implode( ' / ', bankofart_csv_timestamp_aliases() ) ); ?></p>
		<p class="description">この列が見つからない場合は「行の内容が完全に一致するか」で重複を判定します（内容を1文字でも直すと別の行として取り込まれます）。</p>

		<?php bankofart_csv_import_render_diagnosis_helper(); ?>
	</div>
	<?php
}

/**
 * 診断タグ・共鳴文章をスプレッドシートで用意するための補助パネルを描画する。
 *
 * 申請フォームには診断タグ・共鳴文章の項目が無いため、スプレッドシートに
 * 2列足して内容を用意する運用を想定している。タグは登録済みのものしか
 * 割り当てられない（取込側で新規タームを作らない）ので、正しい語彙一覧と
 * そのまま生成AIに渡せる指示文をここに置く。
 *
 * @return void
 */
function bankofart_csv_import_render_diagnosis_helper() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'artist_diagnosis_tag',
			'hide_empty' => false,
			'orderby'    => 'term_id',
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}

	$tag_names = wp_list_pluck( $terms, 'name' );
	$tag_list  = implode( '、', $tag_names );

	$prompt = "あなたはアートギャラリーの編集者です。以下の画家プロフィールを読み、2つの項目を作ってください。\n\n"
		. "【1】診断タグ：下記の語彙リストから3〜6個だけ選び、「;」区切りで出力。リストに無い語は絶対に使わないこと。\n"
		. "語彙リスト：" . $tag_list . "\n\n"
		. "【2】共鳴文章：200〜220字の日本語。構成は「あなたが大切にする「タグA」「タグB」 — これは、{画家名}の作品世界そのものです。」で始め、"
		. "出身・学歴・転機・作風の具体を織り込み、「〜が御社の理念と響き合います。」で締める。HTMLタグは使わない。\n\n"
		. "【出力形式】以下の2行だけを出力（見出しや説明は不要）：\n"
		. "診断タグ: ○○;○○;○○\n"
		. "共鳴文章: ……\n\n"
		. "【画家プロフィール】\n"
		. "アーティスト名：\n制作テーマ：\n制作テーマ詳細：\n なぜ描くか：\n起源の物語：\n目標：\n";
	?>
	<hr>
	<h2>診断タグ・共鳴文章をスプレッドシートで用意する</h2>
	<p>
		申請フォームには「診断タグ」「共鳴文章」の項目がありません。マッチング診断に載せるには、
		スプレッドシートに <code>診断タグ</code> と <code>共鳴文章</code> の2列を追加して内容を入れてください（この2列は既に取込対応済みです）。<br>
		診断タグは <strong>登録済みの<?php echo esc_html( (string) count( $tag_names ) ); ?>個からのみ</strong>割り当てられます（表記ゆれで新しいタグは作りません）。
		リストに無い語が入っていた場合は取込結果に「未登録の値」として表示します。
	</p>

	<h3>入力形式</h3>
	<table class="widefat striped" style="max-width:960px;">
		<thead><tr><th style="width:20%;">列名</th><th>入れる内容</th></tr></thead>
		<tbody>
			<tr>
				<td><code>診断タグ</code></td>
				<td>下のリストから3〜6個。<code>;</code> <code>|</code> <code>,</code> のいずれかで区切る（例：<code>挑戦;突破;唯一無二;力強さ</code>）</td>
			</tr>
			<tr>
				<td><code>共鳴文章</code></td>
				<td>診断結果に表示される200字程度の文章。改行を含む場合はセルを引用符で囲む</td>
			</tr>
		</tbody>
	</table>

	<h3>診断タグ 全<?php echo esc_html( (string) count( $tag_names ) ); ?>語（コピーして生成AIに渡す用）</h3>
	<textarea readonly rows="4" style="width:100%;max-width:960px;font-family:monospace;" onclick="this.select();"><?php echo esc_textarea( $tag_list ); ?></textarea>

	<h3>生成AIへの指示文（そのままコピーして使えます）</h3>
	<textarea readonly rows="16" style="width:100%;max-width:960px;font-family:monospace;" onclick="this.select();"><?php echo esc_textarea( $prompt ); ?></textarea>
	<p class="description">末尾の「画家プロフィール」に、スプレッドシートの各列（制作テーマ／制作テーマ詳細／なぜ描くか／起源の物語／目標）の内容を貼り付けてから実行してください。</p>
	<?php
}

/**
 * 実行結果を描画する。
 *
 * @param array $report bankofart_csv_import_rows() の戻り値＋画面用の付加情報。
 * @return void
 */
function bankofart_csv_import_render_report( $report ) {
	$is_dry = ! empty( $report['dry_run'] );
	?>
	<div class="notice notice-<?php echo $report['failed'] ? 'warning' : 'success'; ?>">
		<p>
			<strong><?php echo $is_dry ? 'テスト実行の結果（まだ書き込んでいません）' : '取り込みが完了しました'; ?></strong><br>
			種別：<?php echo esc_html( $report['type_label'] ); ?>　/
			データ行：<?php echo esc_html( (string) $report['row_count'] ); ?> 行<br>
			新規 <?php echo esc_html( (string) $report['created'] ); ?> 件　/
			更新 <?php echo esc_html( (string) $report['updated'] ); ?> 件　/
			スキップ（取り込み済み）<?php echo esc_html( (string) $report['skipped'] ); ?> 件　/
			エラー <?php echo esc_html( (string) $report['failed'] ); ?> 件
		</p>
		<p>
			<?php if ( '' !== $report['ts_column'] ) : ?>
				重複判定に使った列：<code><?php echo esc_html( $report['ts_column'] ); ?></code>
			<?php else : ?>
				<strong>「タイムスタンプ」列が見つかりませんでした。</strong>行の内容が完全一致するかどうかで重複を判定しています。
			<?php endif; ?>
		</p>
		<?php if ( ! empty( $report['truncated'] ) ) : ?>
			<p><strong>行数が多いため、先頭 <?php echo esc_html( (string) BANKOFART_CSV_MAX_ROWS ); ?> 行のみ処理しました。</strong>残りは同じCSVをもう一度アップロードすれば続きから取り込まれます（処理済みの行はスキップされます）。</p>
		<?php endif; ?>

		<?php if ( ! empty( $report['unmapped']['ignored'] ) ) : ?>
			<p>
				取り込まなかった列（個人情報・契約情報のためWordPressでは管理しません）：<br>
				<code><?php echo esc_html( implode( '　/　', $report['unmapped']['ignored'] ) ); ?></code>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $report['unmapped']['unknown'] ) ) : ?>
			<p>
				<strong>対応先が見つからなかった列</strong>（この列の内容は取り込まれていません）：<br>
				<code><?php echo esc_html( implode( '　/　', $report['unmapped']['unknown'] ) ); ?></code><br>
				<span class="description">必要な項目がここに出ている場合は、スプレッドシート側の見出しを下の「読み込める列名」に合わせてください。</span>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $report['lines'] ) ) : ?>
		<table class="widefat striped" style="max-width:960px;margin-bottom:24px;">
			<thead><tr><th style="width:110px;">結果</th><th style="width:30%;">対象</th><th>内容</th></tr></thead>
			<tbody>
				<?php
				$labels = array(
					'created' => '新規',
					'updated' => '更新',
					'skipped' => 'スキップ',
					'failed'  => 'エラー',
				);
				foreach ( $report['lines'] as $line ) :
					?>
					<tr>
						<td><?php echo esc_html( isset( $labels[ $line['status'] ] ) ? $labels[ $line['status'] ] : $line['status'] ); ?></td>
						<td>
							<?php if ( ! empty( $line['link'] ) ) : ?>
								<a href="<?php echo esc_url( $line['link'] ); ?>"><?php echo esc_html( $line['label'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $line['label'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $line['note'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}
