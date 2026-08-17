<?php
/**
 * BOA JOURNAL / NEWS  JSON取込
 * -------------------------------------------------------------
 * 生成AIが出力したJSONを貼り付け → 下書き記事を一括生成する。
 *
 * 運用方針（確定事項）:
 *   - 生成された投稿は常に draft
 *   - 画像は取り込まない（人が後で手動挿入）
 *   - 関連アーティスト/作品は取り込まない（人が手動で紐付け）
 *   - 新規ONLY（同一 timestamp+type は再取込しない）
 *
 * 依存:
 *   - CPT journal / news、タクソノミー journal_category / news_category
 *   - Meta Box フィールド（group は clone:true / clone_as_multiple 未指定
 *     ＝ PHP配列をそのまま update_post_meta すれば serialize 1行で保存される）
 *   - 重複キー定数は inc/csv-import/importer.php の const を流用する。
 *     functions.php では csv-import/importer.php の「後」に読み込むこと。
 *
 * 既存の inc/csv-import/ には一切手を入れない（読み取り専用で流用）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 重複防止キーの meta_key。
 * inc/csv-import/importer.php が `const` で定義済み（defined() は const にも効く）。
 * 単独で読み込まれた場合の保険として、未定義のときだけ同じ値で定義する。
 */
if ( ! defined( 'BANKOFART_CSV_IMPORT_KEY_META' ) ) {
	define( 'BANKOFART_CSV_IMPORT_KEY_META', '_bankofart_import_key' );
}
if ( ! defined( 'BANKOFART_CSV_IMPORT_LOG_META' ) ) {
	define( 'BANKOFART_CSV_IMPORT_LOG_META', '_bankofart_import_source' );
}
if ( ! defined( 'BANKOFART_JSON_IMPORT_MAX' ) ) {
	define( 'BANKOFART_JSON_IMPORT_MAX', 300 );
}

/* =========================================================
 *  管理メニュー
 * ========================================================= */

/**
 * 管理メニュー「JOURNAL取込」を登録する。
 *
 * menu_position は 59（「BOA CSV取込」= 58 の直下）。
 * CPT JOURNAL が 9 を使っているため 9 は使わない。
 *
 * @return void
 */
function bankofart_journal_import_menu() {
	add_menu_page(
		'記事取込',
		'記事取込',
		'manage_options',
		'bankofart-journal-import',
		'bankofart_journal_import_page',
		'dashicons-download',
		59
	);
}
add_action( 'admin_menu', 'bankofart_journal_import_menu' );

/* =========================================================
 *  画面描画 ＋ POST処理
 * ========================================================= */

/**
 * 取込画面を描画する。
 *
 * @return void
 */
function bankofart_journal_import_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '権限がありません。', 'bankofart' ) );
	}

	$result  = null;
	$payload = '';

	if ( isset( $_POST['bankofart_json_import_nonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_POST['bankofart_json_import_nonce'] ) );
		if ( wp_verify_nonce( $nonce, 'bankofart_json_import' ) ) {
			// JSON本文はスラッシュだけ外す（内容は後段で項目ごとにサニタイズする）。
			$payload = isset( $_POST['json_payload'] ) ? wp_unslash( $_POST['json_payload'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSONとして解析し、値ごとに個別サニタイズする。
			$result  = bankofart_journal_import_run( $payload );
		} else {
			$result = array(
				'created'       => 0,
				'skipped'       => 0,
				'errors'        => 1,
				'has_error'     => true,
				'messages'      => array( 'セキュリティ検証に失敗しました。画面を再読み込みしてやり直してください。' ),
				'created_links' => array(),
			);
		}
	}
	?>
	<div class="wrap">
		<h1>記事取込（JOURNAL / NEWS / 画家応援企業）</h1>
		<p>
			生成AIが出力したJSONを貼り付けて実行します。すべて<strong>下書き</strong>で作成されます。<br>
			画像と関連アーティスト・関連作品は取り込みません（取込後に編集画面で設定してください）。<br>
			同じ <code>timestamp</code>＋<code>type</code> の記事は再取込されません（新規のみ）。
		</p>

		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo $result['has_error'] ? 'warning' : 'success'; ?>">
				<p><strong>取込結果：</strong>
					作成 <?php echo (int) $result['created']; ?> 件 ／
					スキップ <?php echo (int) $result['skipped']; ?> 件 ／
					エラー <?php echo (int) $result['errors']; ?> 件
				</p>
				<?php if ( ! empty( $result['messages'] ) ) : ?>
					<ul style="margin-left:1.5em;list-style:disc;">
						<?php foreach ( $result['messages'] as $m ) : ?>
							<li><?php echo esc_html( $m ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( ! empty( $result['created_links'] ) ) : ?>
					<p><strong>作成した下書き：</strong></p>
					<ul style="margin-left:1.5em;list-style:disc;">
						<?php foreach ( $result['created_links'] as $link ) : ?>
							<li><a href="<?php echo esc_url( $link['edit'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['title'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'bankofart_json_import', 'bankofart_json_import_nonce' ); ?>
			<textarea name="json_payload" rows="20" style="width:100%;font-family:monospace;" placeholder='[ { "type": "interview", "timestamp": "...", "title": "...", ... } ]'><?php echo esc_textarea( $payload ); ?></textarea>
			<p><button type="submit" class="button button-primary">取り込む（下書き作成）</button></p>
		</form>

		<hr>
		<h2>JSONの書式</h2>
		<p><code>type</code> は <code>interview</code> / <code>column</code> / <code>news</code> / <code>collector</code> のいずれか。単体オブジェクトでも配列でも可。1回の上限は <?php echo esc_html( (string) BANKOFART_JSON_IMPORT_MAX ); ?> 件。</p>

		<h3>共通（必須）</h3>
		<table class="widefat striped" style="max-width:960px;">
			<thead><tr><th style="width:24%;">キー</th><th>内容</th></tr></thead>
			<tbody>
				<tr><td><code>type</code></td><td><strong>必須</strong>。interview / column / news / collector</td></tr>
				<tr><td><code>timestamp</code></td><td><strong>必須</strong>。重複判定に使う一意の文字列（例：<code>2026-07-31T10:00:00+09:00</code>）</td></tr>
				<tr><td><code>title</code></td><td><strong>必須</strong>。記事タイトル</td></tr>
				<tr><td><code>slug</code></td><td>任意。URLスラッグ（新規作成時のみ）</td></tr>
				<tr><td><code>summary</code></td><td>要約（カード表示用）</td></tr>
				<tr>
					<td><code>target</code></td>
					<td>
						任意。<strong>既にある投稿に本文を追記したいとき</strong>に、そのタイトルを完全一致で書く。<br>
						<span class="description">
							指定すると<strong>新規作成しません</strong>。見つからない／複数見つかる場合はエラーで中止します（同じ会社を二重に作る事故を防ぐため）。<br>
							既存の写真・URL・移行情報などには触れず、本文とJSONで指定した項目だけを書き込みます。<br>
							すでに本文が入っている投稿はスキップします。上書きしたいときだけ <code>"overwrite": true</code> を付けてください。
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<h4>既存の投稿に本文だけ入れる例</h4>
		<textarea readonly rows="10" style="width:100%;max-width:960px;font-family:monospace;" onclick="this.select();"><?php
		echo esc_textarea(
			"{\n"
			. "  \"type\": \"collector\",\n"
			. "  \"target\": \"株式会社B・Cインベスターズ\",\n"
			. "  \"timestamp\": \"2026-08-03T12:00:00+09:00\",\n"
			. "  \"title\": \"株式会社B・Cインベスターズ\",\n"
			. "  \"body\": {\n"
			. "    \"qa\": [\n"
			. "      { \"question\": \"……\", \"answer\": \"<p>……</p>\" }\n"
			. "    ]\n"
			. "  }\n"
			. "}"
		);
		?></textarea>
		<p class="description"><code>target</code> を書かなければ、これまで通り新しい投稿が作られます（新規の企業を追加する場合はそちら）。</p>

		<h3>interview / column（JOURNAL）</h3>
		<table class="widefat striped" style="max-width:960px;">
			<thead><tr><th style="width:24%;">キー</th><th>取り込み先</th></tr></thead>
			<tbody>
				<tr><td><code>author</code></td><td><code>journal_author</code></td></tr>
				<tr><td><code>reading_time</code></td><td><code>journal_reading_time</code></td></tr>
				<tr><td><code>body.intro</code></td><td><code>journal_interview_intro</code>（interviewのみ）</td></tr>
				<tr><td><code>body.speaker_name</code></td><td><code>journal_speaker_name</code>（interviewのみ）</td></tr>
				<tr><td><code>body.speaker_role</code></td><td><code>journal_speaker_role</code>（interviewのみ）</td></tr>
				<tr><td><code>body.interviewer_name</code></td><td><code>journal_interviewer_name</code>（未指定なら <code>BOA</code>）</td></tr>
				<tr><td><code>body.qa[]</code></td><td><code>journal_interview_qa</code>（<code>chapter</code>/<code>question</code>/<code>answer</code>）</td></tr>
				<tr><td><code>body.sections[]</code></td><td><code>journal_sections</code>（<code>heading</code>/<code>body</code>）※columnのみ</td></tr>
			</tbody>
		</table>

		<h3>news（NEWS）</h3>
		<table class="widefat striped" style="max-width:960px;">
			<thead><tr><th style="width:24%;">キー</th><th>取り込み先</th></tr></thead>
			<tbody>
				<tr><td><code>external_url</code></td><td><code>news_external_url</code></td></tr>
				<tr><td><code>external_label</code></td><td><code>news_external_label</code></td></tr>
				<tr><td><code>news_category</code></td><td>受賞 / 展示 / メディア掲載 / お知らせ のいずれか</td></tr>
				<tr><td><code>body.sections[]</code></td><td><code>news_sections</code>（<code>heading</code>/<code>body</code>）</td></tr>
			</tbody>
		</table>

		<h3>collector（画家応援企業インタビュー）</h3>
		<table class="widefat striped" style="max-width:960px;">
			<thead><tr><th style="width:24%;">キー</th><th>取り込み先</th></tr></thead>
			<tbody>
				<tr>
					<td><code>body.qa[]</code></td>
					<td>
						<code>collector_interview_qa</code>（<code>question</code>/<code>answer</code>）<br>
						<span class="description"><strong>質問数は自由</strong>です。何問でも並べられます。定番のQ1〜Q5には触れません（1件でも入れば自由記述側が本文になります）。</span>
					</td>
				</tr>
				<tr><td><code>company_name</code></td><td><code>collector_company_name</code></td></tr>
				<tr><td><code>url</code> / <code>external_url</code> / <code>video_url</code></td><td>企業サイト／外部記事／動画のURL</td></tr>
				<tr><td><code>introduced_work</code></td><td><code>collector_introduced_artwork_text</code></td></tr>
				<tr><td><code>change_summary</code></td><td><code>collector_change_summary</code></td></tr>
				<tr><td><code>implementation_date</code></td><td>導入時期（例：<code>2025-04-01</code>）</td></tr>
				<tr><td><code>industry</code> / <code>issue</code> / <code>placement</code></td><td>業種 / 課題 / 設置場所。文字列または配列。登録済みターム名と<strong>完全一致</strong>するものだけ紐付け、一致しない値は取込結果に表示します</td></tr>
			</tbody>
		</table>

		<h4>指定できる分類の値</h4>
		<table class="widefat striped" style="max-width:960px;">
			<tbody>
			<?php
			foreach ( array(
				'industry'  => array( 'collector_industry', '業種' ),
				'issue'     => array( 'collector_issue', '課題' ),
				'placement' => array( 'collector_placement', '設置場所' ),
			) as $key => $conf ) :
				$terms = get_terms(
					array(
						'taxonomy'   => $conf[0],
						'hide_empty' => false,
					)
				);
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				?>
				<tr>
					<td style="width:24%;"><code><?php echo esc_html( $key ); ?></code>（<?php echo esc_html( $conf[1] ); ?>）</td>
					<td><?php echo esc_html( implode( ' / ', wp_list_pluck( $terms, 'name' ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h4>collector の記入例</h4>
		<textarea readonly rows="18" style="width:100%;max-width:960px;font-family:monospace;" onclick="this.select();"><?php
		echo esc_textarea(
			"{\n"
			. "  \"type\": \"collector\",\n"
			. "  \"timestamp\": \"2026-08-01T10:00:00+09:00\",\n"
			. "  \"title\": \"株式会社サンプル\",\n"
			. "  \"slug\": \"sample-inc\",\n"
			. "  \"company_name\": \"株式会社サンプル\",\n"
			. "  \"url\": \"https://example.com/\",\n"
			. "  \"implementation_date\": \"2025-04-01\",\n"
			. "  \"industry\": \"IT・通信\",\n"
			. "  \"issue\": [\"モチベーション\", \"企業理念浸透\"],\n"
			. "  \"placement\": \"エントランス\",\n"
			. "  \"change_summary\": \"社員同士の会話が増えた\",\n"
			. "  \"body\": {\n"
			. "    \"qa\": [\n"
			. "      { \"question\": \"御社が大切にされている理念を教えてください。\",\n"
			. "        \"answer\": \"<p>……</p>\" },\n"
			. "      { \"question\": \"アートを導入したきっかけは？\",\n"
			. "        \"answer\": \"<p>……</p>\" }\n"
			. "    ]\n"
			. "  }\n"
			. "}"
		);
		?></textarea>
	</div>
	<?php
}

/* =========================================================
 *  取込本体
 * ========================================================= */

/**
 * JSON文字列を解析して取り込む。
 *
 * @param string $raw 貼り付けられたJSON。
 * @return array 結果レポート。
 */
function bankofart_journal_import_run( $raw ) {
	$out = array(
		'created'       => 0,
		'skipped'       => 0,
		'errors'        => 0,
		'has_error'     => false,
		'messages'      => array(),
		'created_links' => array(),
	);

	$raw = trim( (string) $raw );
	if ( '' === $raw ) {
		$out['messages'][] = 'JSONが空です。';
		$out['has_error']  = true;
		return $out;
	}

	$data = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		$out['messages'][] = 'JSONの構文エラー：' . json_last_error_msg();
		$out['has_error']  = true;
		return $out;
	}

	if ( ! is_array( $data ) ) {
		$out['messages'][] = 'JSONのトップは配列またはオブジェクトにしてください。';
		$out['has_error']  = true;
		return $out;
	}

	// 単体オブジェクトを配列に揃える。
	if ( isset( $data['type'] ) ) {
		$data = array( $data );
	}

	if ( count( $data ) > BANKOFART_JSON_IMPORT_MAX ) {
		$out['messages'][] = '1回の上限（' . BANKOFART_JSON_IMPORT_MAX . '件）を超えています。';
		$out['has_error']  = true;
		return $out;
	}

	$index = 0;
	foreach ( $data as $item ) {
		++$index;
		$res = bankofart_journal_import_one( $item );

		if ( 'created' === $res['status'] ) {
			++$out['created'];
			$out['created_links'][] = $res['link'];
		} elseif ( 'skipped' === $res['status'] ) {
			++$out['skipped'];
		} else {
			++$out['errors'];
			$out['has_error'] = true;
		}

		if ( ! empty( $res['message'] ) ) {
			$out['messages'][] = '[' . $index . '] ' . $res['message'];
		}
	}

	return $out;
}

/**
 * 1件分を取り込む。
 *
 * @param mixed $item JSONの1要素。
 * @return array array( 'status' => created|skipped|error, 'message' => string, 'link' => array )
 */
function bankofart_journal_import_one( $item ) {
	$fail = static function ( $msg ) {
		return array(
			'status'  => 'error',
			'message' => $msg,
		);
	};

	if ( ! is_array( $item ) ) {
		return $fail( 'オブジェクトではありません。' );
	}

	$type = isset( $item['type'] ) ? (string) $item['type'] : '';
	if ( ! in_array( $type, array( 'interview', 'column', 'news', 'collector' ), true ) ) {
		// 表示側で esc_html するため、ここではエスケープしない（二重エスケープ防止）。
		return $fail( 'type が不正（' . $type . '）。interview / column / news / collector のいずれかにしてください。' );
	}

	$title = isset( $item['title'] ) ? trim( (string) $item['title'] ) : '';
	if ( '' === $title ) {
		return $fail( 'title が空です。' );
	}

	$timestamp = isset( $item['timestamp'] ) ? trim( (string) $item['timestamp'] ) : '';
	if ( '' === $timestamp ) {
		return $fail( 'timestamp が空です（重複判定に必須）。' );
	}

	$post_type_map = array(
		'news'      => 'news',
		'collector' => 'collector',
	);
	$post_type     = isset( $post_type_map[ $type ] ) ? $post_type_map[ $type ] : 'journal';

	/*
	 * 重複チェック（新規ONLY）。
	 * 'any' はゴミ箱を含まないため、trash まで明示して再作成を防ぐ
	 * （inc/csv-import/importer.php の判定と同じ考え方）。
	 */
	$import_key = sha1( $type . '|ts|' . $timestamp );
	$existing   = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => BANKOFART_CSV_IMPORT_KEY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- 取込時のみの管理画面処理。
			'meta_value'     => $import_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- 同上。
		)
	);
	if ( ! empty( $existing ) ) {
		return array(
			'status'  => 'skipped',
			'message' => '「' . $title . '」は取込済みのためスキップ（投稿ID ' . (int) $existing[0] . '）。',
		);
	}

	/*
	 * target が指定されていれば、既存投稿に中身を追記する（＝新規作成しない）。
	 * 既に写真やURLが入っている投稿を空の新規で二重に作ってしまう事故を防ぐため、
	 * 見つからない・複数見つかる場合は作らずにエラーを返す。
	 */
	$target   = isset( $item['target'] ) ? trim( (string) $item['target'] ) : '';
	$is_update = false;

	if ( '' !== $target ) {
		$found = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 2, // 2件取って重複を検出する。
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'title'          => $target,
			)
		);

		if ( empty( $found ) ) {
			return $fail( '「' . $target . '」に一致する' . $post_type . '投稿が見つかりません（target 指定時は新規作成しません）。タイトルの完全一致で探しています。' );
		}
		if ( count( $found ) > 1 ) {
			return $fail( '「' . $target . '」に一致する投稿が複数あります（ID ' . implode( ', ', $found ) . '）。取り違えを避けるため中止しました。' );
		}

		$post_id   = (int) $found[0];
		$is_update = true;
	} else {
		// 投稿本体（本文はメタに入れるので post_content は空のまま）。
		$postarr = array(
			'post_type'   => $post_type,
			'post_title'  => $title,
			'post_status' => 'draft',
		);
		if ( ! empty( $item['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $item['slug'] );
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $fail( 'wp_insert_post に失敗：' . $post_id->get_error_message() );
		}
		$post_id = (int) $post_id;
	}

	/*
	 * 既存投稿に追記するとき、すでに本文が入っていれば黙って上書きしない。
	 * 上書きしたいときだけ JSON に "overwrite": true を書く。
	 */
	if ( $is_update && empty( $item['overwrite'] ) ) {
		$body_keys = array(
			'collector' => 'collector_interview_qa',
			'interview' => 'journal_interview_qa',
			'column'    => 'journal_sections',
			'news'      => 'news_sections',
		);
		$key      = isset( $body_keys[ $type ] ) ? $body_keys[ $type ] : '';
		$existing_body = $key ? array_filter( (array) get_post_meta( $post_id, $key, true ) ) : array();
		if ( ! empty( $existing_body ) ) {
			return array(
				'status'  => 'skipped',
				'message' => '「' . $target . '」には既に本文が入っているためスキップ（投稿ID ' . $post_id . '）。上書きするには "overwrite": true を付けてください。',
			);
		}
	}

	// type 別の中身挿入。$notes には「取り込めなかった値」等の注意書きが入る。
	$notes = array();
	if ( 'news' === $type ) {
		bankofart_journal_import_fill_news( $post_id, $item );
	} elseif ( 'collector' === $type ) {
		$notes = (array) bankofart_journal_import_fill_collector( $post_id, $item );
	} else {
		bankofart_journal_import_fill_journal( $post_id, $type, $item );
	}

	// 重複キー／監査ログ。
	update_post_meta( $post_id, BANKOFART_CSV_IMPORT_KEY_META, $import_key );
	update_post_meta(
		$post_id,
		BANKOFART_CSV_IMPORT_LOG_META,
		wp_json_encode(
			array(
				'source'      => 'journal-json-import',
				'type'        => $type,
				'timestamp'   => $timestamp,
				'imported_at' => current_time( 'mysql' ),
			),
			JSON_UNESCAPED_UNICODE
		)
	);

	$label = $is_update
		? '「' . $target . '」に本文を追記（投稿ID ' . $post_id . '）。既存の写真・URL等はそのままです。'
		: '「' . $title . '」を下書き作成（投稿ID ' . $post_id . '）。';

	return array(
		'status'  => 'created',
		'message' => $label . ( $notes ? ' ／ ' . implode( ' ／ ', $notes ) : '' ),
		'link'    => array(
			'title' => $is_update ? $target : $title,
			'edit'  => get_edit_post_link( $post_id, 'raw' ),
		),
	);
}

/**
 * wysiwyg 相当の本文を整える。
 *
 * テンプレート側（single-journal.php / body-interview.php）は本文を wpautop せず
 * そのまま出力するため、ブロック要素の無い素のテキストが来ると改行が失われる。
 * ブロックタグを含まない場合だけ wpautop() を通し、手入力（TinyMCE）と同じ形にする。
 * 既に <p> 等を含む場合は何もしない。
 *
 * @param string $html 本文。
 * @return string
 */
function bankofart_journal_import_prepare_richtext( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return '';
	}

	if ( preg_match( '~<(p|div|h[1-6]|ul|ol|li|blockquote|figure|table|pre)\b~i', $html ) ) {
		return $html;
	}

	return wpautop( $html );
}

/* ---- journal (interview / column) ------------------------- */

/**
 * JOURNAL の各フィールドを埋める。
 *
 * @param int    $post_id 投稿ID。
 * @param string $type    'interview' または 'column'。
 * @param array  $item    JSONの1要素。
 * @return void
 */
function bankofart_journal_import_fill_journal( $post_id, $type, $item ) {
	$body = ( isset( $item['body'] ) && is_array( $item['body'] ) ) ? $item['body'] : array();

	// 共通メタ。
	if ( isset( $item['summary'] ) ) {
		update_post_meta( $post_id, 'journal_summary', sanitize_textarea_field( $item['summary'] ) );
	}
	if ( isset( $item['author'] ) ) {
		update_post_meta( $post_id, 'journal_author', sanitize_text_field( $item['author'] ) );
	}
	if ( isset( $item['reading_time'] ) ) {
		update_post_meta( $post_id, 'journal_reading_time', (int) $item['reading_time'] );
	}

	// layout を明示（term名の完全一致に依存しないため最も確実）。
	$layout = ( 'interview' === $type ) ? 'interview' : 'column';
	update_post_meta( $post_id, 'journal_layout', $layout );

	if ( 'interview' === $type ) {
		if ( isset( $body['intro'] ) ) {
			update_post_meta( $post_id, 'journal_interview_intro', wp_kses_post( bankofart_journal_import_prepare_richtext( $body['intro'] ) ) );
		}
		if ( isset( $body['speaker_name'] ) ) {
			update_post_meta( $post_id, 'journal_speaker_name', sanitize_text_field( $body['speaker_name'] ) );
		}
		if ( isset( $body['speaker_role'] ) ) {
			update_post_meta( $post_id, 'journal_speaker_role', sanitize_text_field( $body['speaker_role'] ) );
		}

		$interviewer = ( isset( $body['interviewer_name'] ) && '' !== trim( (string) $body['interviewer_name'] ) )
			? sanitize_text_field( $body['interviewer_name'] )
			: 'BOA';
		update_post_meta( $post_id, 'journal_interviewer_name', $interviewer );

		/*
		 * Q&A（group / clone:true・clone_as_multiple 未指定）。
		 * PHP配列をそのまま渡せば WordPress が serialize して1行で保存する。
		 * 空のサブフィールドはキーごと入れない（手入力時のDB実態に合わせる）。
		 */
		$qa_rows = array();
		if ( ! empty( $body['qa'] ) && is_array( $body['qa'] ) ) {
			foreach ( $body['qa'] as $qa ) {
				if ( ! is_array( $qa ) ) {
					continue;
				}
				$question = isset( $qa['question'] ) ? sanitize_textarea_field( $qa['question'] ) : '';
				$answer   = isset( $qa['answer'] ) ? wp_kses_post( bankofart_journal_import_prepare_richtext( $qa['answer'] ) ) : '';
				$chapter  = isset( $qa['chapter'] ) ? sanitize_text_field( $qa['chapter'] ) : '';

				if ( '' === $question && '' === trim( wp_strip_all_tags( $answer ) ) && '' === $chapter ) {
					continue;
				}

				$row = array();
				if ( '' !== $chapter ) {
					$row['qa_chapter'] = $chapter;
				}
				if ( '' !== $question ) {
					$row['qa_question'] = $question;
				}
				if ( '' !== $answer ) {
					$row['qa_answer'] = $answer;
				}
				$qa_rows[] = $row;
			}
		}
		// 空配列は保存しない（Meta Box は空値の行を作らないため実態に合わせる）。
		if ( ! empty( $qa_rows ) ) {
			update_post_meta( $post_id, 'journal_interview_qa', $qa_rows );
		}

		bankofart_journal_import_set_term( $post_id, 'journal_category', 'インタビュー' );

	} else {
		$sections = bankofart_journal_import_build_sections( $body );
		if ( ! empty( $sections ) ) {
			update_post_meta( $post_id, 'journal_sections', $sections );
		}

		bankofart_journal_import_set_term( $post_id, 'journal_category', 'コラム' );
	}

	// 表示スイッチ（section-display-guard が補完するが明示する）。
	foreach ( array( 'journal_show_related_artist', 'journal_show_related_art', 'journal_show_more_journal', 'journal_show_cta' ) as $sw ) {
		update_post_meta( $post_id, $sw, '1' );
	}
}

/* ---- news ------------------------------------------------- */

/**
 * NEWS の各フィールドを埋める。
 *
 * @param int   $post_id 投稿ID。
 * @param array $item    JSONの1要素。
 * @return void
 */
function bankofart_journal_import_fill_news( $post_id, $item ) {
	$body = ( isset( $item['body'] ) && is_array( $item['body'] ) ) ? $item['body'] : array();

	if ( isset( $item['summary'] ) ) {
		update_post_meta( $post_id, 'news_summary', sanitize_textarea_field( $item['summary'] ) );
	}
	if ( isset( $item['external_url'] ) ) {
		update_post_meta( $post_id, 'news_external_url', esc_url_raw( $item['external_url'] ) );
	}
	if ( isset( $item['external_label'] ) ) {
		update_post_meta( $post_id, 'news_external_label', sanitize_text_field( $item['external_label'] ) );
	}

	$sections = bankofart_journal_import_build_sections( $body );
	if ( ! empty( $sections ) ) {
		update_post_meta( $post_id, 'news_sections', $sections );
	}

	// カテゴリー（4種。登録済みターム名と完全一致するときだけ紐付け）。
	$cat     = isset( $item['news_category'] ) ? trim( (string) $item['news_category'] ) : '';
	$allowed = array( '受賞', '展示', 'メディア掲載', 'お知らせ' );
	if ( in_array( $cat, $allowed, true ) ) {
		bankofart_journal_import_set_term( $post_id, 'news_category', $cat );
	}

	foreach ( array( 'news_show_related_artist', 'news_show_related_art', 'news_show_more_news', 'news_show_cta' ) as $sw ) {
		update_post_meta( $post_id, $sw, '1' );
	}
}

/* ---- collector（画家応援企業インタビュー） ---------------- */

/**
 * COLLECTOR の各フィールドを埋める。
 *
 * インタビューは質問文ごと自由に持てる collector_interview_qa（group/clone）に入れる。
 * 固定のQ1〜Q5には触れない（自由記述が1件でもあればテンプレート側がそちらを使う）。
 *
 * @param int   $post_id 投稿ID。
 * @param array $item    JSONの1要素。
 * @return void
 */
function bankofart_journal_import_fill_collector( $post_id, $item ) {
	$body = ( isset( $item['body'] ) && is_array( $item['body'] ) ) ? $item['body'] : array();

	// 基本情報。
	$text_map = array(
		'company_name'    => 'collector_company_name',
		'url'             => 'collector_url',
		'external_url'    => 'collector_external_url',
		'video_url'       => 'collector_video_url',
		'introduced_work' => 'collector_introduced_artwork_text',
		'change_summary'  => 'collector_change_summary',
	);
	foreach ( $text_map as $key => $field ) {
		if ( ! isset( $item[ $key ] ) || '' === trim( (string) $item[ $key ] ) ) {
			continue;
		}
		$value = ( false !== strpos( $field, '_url' ) )
			? esc_url_raw( $item[ $key ] )
			: sanitize_text_field( $item[ $key ] );
		update_post_meta( $post_id, $field, $value );
	}

	// 導入時期（YYYY-MM-DD 等で受ける）。
	if ( ! empty( $item['implementation_date'] ) ) {
		$ts = strtotime( (string) $item['implementation_date'] );
		if ( $ts ) {
			update_post_meta( $post_id, 'collector_implementation_date', gmdate( 'Y-m-d', $ts ) );
		}
	}

	/*
	 * インタビュー本文（質問数は自由）。
	 * group/clone なので PHP配列をそのまま渡せば serialize されて1行で保存される。
	 */
	$qa_rows = array();
	if ( ! empty( $body['qa'] ) && is_array( $body['qa'] ) ) {
		foreach ( $body['qa'] as $qa ) {
			if ( ! is_array( $qa ) ) {
				continue;
			}
			$question = isset( $qa['question'] ) ? sanitize_textarea_field( $qa['question'] ) : '';
			$answer   = isset( $qa['answer'] ) ? wp_kses_post( bankofart_journal_import_prepare_richtext( $qa['answer'] ) ) : '';

			if ( '' === $question && '' === trim( wp_strip_all_tags( $answer ) ) ) {
				continue;
			}

			$row = array();
			if ( '' !== $question ) {
				$row['qa_question'] = $question;
			}
			if ( '' !== $answer ) {
				$row['qa_answer'] = $answer;
			}
			$qa_rows[] = $row;
		}
	}
	if ( ! empty( $qa_rows ) ) {
		update_post_meta( $post_id, 'collector_interview_qa', $qa_rows );
	}

	// タクソノミー（登録済みターム名と完全一致するものだけ。表記ゆれで新タームは作らない）。
	$notes = array();
	foreach ( array(
		'industry'  => 'collector_industry',
		'issue'     => 'collector_issue',
		'placement' => 'collector_placement',
	) as $key => $taxonomy ) {
		if ( empty( $item[ $key ] ) ) {
			continue;
		}
		$names   = is_array( $item[ $key ] ) ? $item[ $key ] : array( $item[ $key ] );
		$ids     = array();
		$missing = array();
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			} else {
				$missing[] = $name;
			}
		}
		if ( $ids ) {
			wp_set_object_terms( $post_id, $ids, $taxonomy, false );
		}
		if ( $missing ) {
			// 黙って捨てると気付けないため、未登録の値は必ず報告する。
			$notes[] = sprintf( '%s に未登録の値：%s', $taxonomy, implode( '・', $missing ) );
		}
	}

	foreach ( array( 'collector_show_interview', 'collector_show_introduced_work', 'collector_show_same_issue', 'collector_show_matching', 'collector_show_cta' ) as $sw ) {
		update_post_meta( $post_id, $sw, '1' );
	}

	return $notes;
}

/* ---- sections配列の組み立て（column / news 共通） ---------- */

/**
 * 本文セクションの配列を組み立てる。
 *
 * journal_sections / news_sections はサブフィールドのキーが同名
 * （section_heading / section_body）なので共通化できる。
 * 空のサブフィールドはキーごと入れない（手入力時のDB実態に合わせる）。
 *
 * @param array $body JSONの body 部分。
 * @return array セクション行の配列。
 */
function bankofart_journal_import_build_sections( $body ) {
	$sections = array();

	if ( empty( $body['sections'] ) || ! is_array( $body['sections'] ) ) {
		return $sections;
	}

	foreach ( $body['sections'] as $sec ) {
		if ( ! is_array( $sec ) ) {
			continue;
		}

		$heading = isset( $sec['heading'] ) ? sanitize_text_field( $sec['heading'] ) : '';
		$content = isset( $sec['body'] ) ? wp_kses_post( bankofart_journal_import_prepare_richtext( $sec['body'] ) ) : '';

		if ( '' === $heading && '' === trim( wp_strip_all_tags( $content ) ) ) {
			continue;
		}

		$row = array();
		if ( '' !== $heading ) {
			$row['section_heading'] = $heading;
		}
		if ( '' !== $content ) {
			$row['section_body'] = $content;
		}
		$sections[] = $row;
	}

	return $sections;
}

/* ---- ターム紐付け（名前・完全一致。新規タームは作らない） -- */

/**
 * 登録済みのタームだけを紐付ける。
 *
 * 表記ゆれで不要なタームが増えるのを防ぐため、存在しない名前は無視する。
 *
 * @param int    $post_id   投稿ID。
 * @param string $taxonomy  タクソノミー。
 * @param string $term_name ターム名。
 * @return void
 */
function bankofart_journal_import_set_term( $post_id, $taxonomy, $term_name ) {
	$term = get_term_by( 'name', $term_name, $taxonomy );
	if ( $term && ! is_wp_error( $term ) ) {
		wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false );
	}
}
