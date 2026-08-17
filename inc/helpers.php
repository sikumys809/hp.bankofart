<?php
/**
 * テンプレート用ヘルパー関数
 *
 * 運用性の3原則のうち「2. 未入力項目の自動非表示」を支える共通関数。
 * Phase 2 のテンプレート移植で全 single ページが本関数を使う前提。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * 連絡先メール（全フォーム共通・一元管理）
 * 資料請求／オンライン説明会予約／リセール待機リストの
 * 管理者通知宛先・送信元(From)・Reply-To はすべてこの定数を参照する。
 * 変更は BANKOFART_CONTACT_EMAIL の1箇所のみでよい。
 * ======================================================= */
if ( ! defined( 'BANKOFART_CONTACT_EMAIL' ) ) {
	define( 'BANKOFART_CONTACT_EMAIL', 'info@bankof-art.com' );
}
if ( ! defined( 'BANKOFART_CONTACT_FROM_NAME' ) ) {
	define( 'BANKOFART_CONTACT_FROM_NAME', 'BANK of ART' );
}
/*
 * 送信元(From)アドレスは、送信サーバー(ConoHa: mail*.conoha.ne.jp)が送出する
 * ドメインと一致する固定アドレスにする。SPF/DMARC を通すため必須。
 * 宛先や Reply-To（＝BANKOFART_CONTACT_EMAIL）とは独立させ、From は常にこの値。
 * ※ WP Mail SMTP の「送信元メール」設定とも一致させること（不一致だと強制上書きされる）。
 */
if ( ! defined( 'BANKOFART_MAIL_FROM_EMAIL' ) ) {
	define( 'BANKOFART_MAIL_FROM_EMAIL', 'info@bankof-art.com' );
}

/**
 * フォーム送信メール共通ヘッダー（Content-Type / From / Reply-To）。
 *
 * @return string[] wp_mail() 用ヘッダー配列。
 */
function bankofart_mail_headers() {
	// From は SPF が通る固定アドレス（送信サーバーのドメインと一致）。宛先とは独立。
	$from_email = ( defined( 'BANKOFART_MAIL_FROM_EMAIL' ) && BANKOFART_MAIL_FROM_EMAIL )
		? BANKOFART_MAIL_FROM_EMAIL
		: 'info@bankof-art.com';
	// Reply-To は連絡先（返信はこちらへ）。From≠宛先でも Reply-To で受け側の返信先を担保。
	$reply_to = ( defined( 'BANKOFART_CONTACT_EMAIL' ) && BANKOFART_CONTACT_EMAIL )
		? BANKOFART_CONTACT_EMAIL
		: $from_email;
	return array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: ' . BANKOFART_CONTACT_FROM_NAME . ' <' . $from_email . '>',
		'Reply-To: ' . $reply_to,
	);
}

/* =========================================================
 * 【一時デバッグ】管理者通知メール不達の切り分けログ
 * uploads/boa-mail-debug.log に全 wp_mail 呼び出しの 宛先/From/件名/返り値 を記録。
 * 原因特定後に、このブロックと各 mail.php / resale の bankofart_maildbg_log 呼び出しを削除する。
 * ======================================================= */
function bankofart_maildbg_log( $msg ) {
	$line = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $msg . "\n";
	error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	if ( function_exists( 'wp_upload_dir' ) ) {
		$up = wp_upload_dir();
		if ( ! empty( $up['basedir'] ) && is_writable( $up['basedir'] ) ) {
			error_log( $line, 3, $up['basedir'] . '/boa-mail-debug.log' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}

// 全 wp_mail 呼び出しの 宛先・件名・From を記録（From==To の自己宛パターンを可視化）。
add_filter(
	'wp_mail',
	function ( $atts ) {
		$to   = isset( $atts['to'] ) ? $atts['to'] : '';
		$from = '';
		foreach ( (array) ( isset( $atts['headers'] ) ? $atts['headers'] : array() ) as $hl ) {
			if ( is_string( $hl ) && 0 === stripos( $hl, 'From:' ) ) {
				$from = $hl;
			}
		}
		bankofart_maildbg_log( 'wp_mail() to=' . var_export( $to, true ) . ' | subject=' . ( isset( $atts['subject'] ) ? $atts['subject'] : '' ) . ' | ' . $from );
		return $atts;
	},
	1
);

// 送信失敗（wp_mail_failed）を記録。
add_action(
	'wp_mail_failed',
	function ( $e ) {
		if ( is_wp_error( $e ) ) {
			bankofart_maildbg_log( 'wp_mail_FAILED: ' . $e->get_error_message() . ' | data=' . wp_json_encode( $e->get_error_data(), JSON_UNESCAPED_UNICODE ) );
		}
	}
);

/**
 * セクション可視性の二段階チェック。
 *
 * 段階1: 管理画面の switch（例：artist_show_works）が ON か。
 * 段階2: 表示すべきデータが実際に存在するか。
 *
 * 両方を満たした場合のみ true を返す。テンプレート側はこの戻り値で
 * セクションの描画可否を判定する。
 *
 * @param string $switch_field_id Meta Box の switch フィールドID（例：'artist_show_works'）。
 *                                空文字を渡すと段階1チェックを省略しデータ有無のみで判定。
 * @param mixed  $data_to_check   存在チェック対象（array / string / 数値 / オブジェクト等）。
 * @param int    $post_id         投稿ID。省略時は現在の投稿。
 * @return bool 描画すべきなら true。
 */
function bankofart_should_show_section( $switch_field_id, $data_to_check, $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();

	// 段階1: 管理画面の switch（switch_field_id が指定された場合のみ）。
	if ( ! empty( $switch_field_id ) ) {
		$switch_value = function_exists( 'rwmb_meta' )
			? rwmb_meta( $switch_field_id, '', $post_id )
			: get_post_meta( $post_id, $switch_field_id, true );

		// 明示的に OFF（'0' / 0）の場合のみ非表示にする。
		// 未設定（'' / null / false）は switch の std => 1（デフォルトON）扱い。
		// ※CSVインポートや未保存投稿で switch メタが無くても、データがあれば表示する。
		if ( '0' === (string) $switch_value ) {
			return false;
		}
	}

	// 段階2: データの有無。
	return bankofart_has_value( $data_to_check );
}

/**
 * フィールド値が「実質的に空でない」かを型に応じて判定する。
 *
 * - 配列        : 要素が1つ以上ある
 * - 文字列      : タグ除去・トリム後に文字が残る（wysiwyg の空 <p></p> 対策）
 * - 数値        : null でなければ true（0 も値として扱う）
 * - それ以外    : !empty()
 *
 * @param mixed $value 判定対象。
 * @return bool 値があれば true。
 */
function bankofart_has_value( $value ) {
	if ( is_array( $value ) ) {
		return count( $value ) > 0;
	}

	if ( is_string( $value ) ) {
		return '' !== trim( wp_strip_all_tags( $value ) );
	}

	if ( is_int( $value ) || is_float( $value ) ) {
		// 0 も「入力済み」とみなす（号数・査定率0% 等の要件に対応）。
		return true;
	}

	return ! empty( $value );
}

/**
 * 指定 Relationship で接続された投稿群を取得する薄いラッパー。
 *
 * MB Relationships 未導入時も致命的エラーにならないよう存在チェックする。
 *
 * @param string $relationship_id Relationship ID（例：'artist_to_art'）。
 * @param string $direction       'from' または 'to'。接続元としてのIDなら 'from'。
 * @param int    $post_id         基準投稿ID。省略時は現在の投稿。
 * @return array 接続された WP_Post 配列（無い場合は空配列）。
 */
function bankofart_get_connected( $relationship_id, $direction = 'from', $post_id = null ) {
	if ( ! class_exists( 'MB_Relationships_API' ) ) {
		return array();
	}

	$post_id = $post_id ? $post_id : get_the_ID();

	$connected = MB_Relationships_API::get_connected(
		array(
			'id'        => $relationship_id,
			$direction  => $post_id,
		)
	);

	return is_array( $connected ) ? $connected : array();
}

/**
 * Meta Box の single_image フィールドから url / alt を取得する。
 *
 * カードコンポーネント等での画像出力を共通化する。値が無い場合は
 * url が空文字の配列を返すので、呼び出し側は !empty($img['url']) で判定する。
 *
 * @param string $field_id single_image フィールドID（例：'artist_main_photo'）。
 * @param int    $post_id  投稿ID。省略時は現在の投稿。
 * @param string $size     画像サイズ。既定 'medium'。
 * @return array array( 'url' => string, 'alt' => string, 'id' => int )
 */
function bankofart_get_image( $field_id, $post_id = null, $size = 'medium' ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$result  = array(
		'url' => '',
		'alt' => '',
		'id'  => 0,
	);

	if ( ! function_exists( 'rwmb_meta' ) ) {
		return $result;
	}

	$img = rwmb_meta( $field_id, array( 'size' => $size ), $post_id );

	if ( ! empty( $img['url'] ) ) {
		$result['url'] = $img['url'];
		$result['alt'] = ! empty( $img['alt'] ) ? $img['alt'] : '';
		$result['id']  = ! empty( $img['ID'] ) ? (int) $img['ID'] : 0;
	}

	return $result;
}

/**
 * タクソノミーの最初のターム名を返す（単一値ラベル用）。
 *
 * @param int    $post_id  投稿ID。
 * @param string $taxonomy タクソノミー名。
 * @return string ターム名（無ければ空文字）。
 */
function bankofart_get_first_term_name( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}
	return $terms[0]->name;
}

/**
 * 画家応募フォームの URL を返す（一元管理）。
 *
 * recruit ページの「応募フォームへ」ボタンと、archive 等の FOR ARTISTS バナーの
 * 「応募する」ボタンが共通でこれを参照する。差し替えはこの1関数のみ。
 * 旧：外部 Tally（https://tally.so/r/MeMEV0）→ 自前の画家応募フォーム /artist-entry/ に置き換え。
 *
 * @return string 応募フォーム URL（自前ページ /artist-entry/）。
 */
function bankofart_apply_url() {
	return home_url( '/artist-entry/' );
}

/**
 * 募集要項PDF の URL を返す（一元管理）。
 *
 * recruit ページの「詳しい募集要項はこちら」と、FOR ARTISTS バナーの
 * 「募集要項を見る」ボタンが共通でこれを参照する。差し替えはこの1関数のみ。
 *
 * カスタマイザー（外観 › カスタマイズ › 募集（RECRUIT））でアップロードした
 * PDF を優先。未設定ならテーマ同梱の assets/docs/ のPDFにフォールバック。
 *
 * @return string 募集要項PDF URL。
 */
function bankofart_recruit_guidelines_pdf_url() {
	$attachment_id = absint( get_theme_mod( 'bankofart_guidelines_pdf', 0 ) );
	if ( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			return $url;
		}
	}
	return get_theme_file_uri( 'assets/docs/boa-artist-application-guidelines.pdf' );
}

/**
 * 資料請求の URL を返す（一元管理）。
 *
 * CONTACT 系ボタン（CTA / ヘッダー / フッター / about MOVIE 等）が共通で参照する。
 * 暫定は現行運用の外部フォーム（form-mailer）。将来 内部の資料請求フォームを実装したら、
 * このフィルター既定値を差し替える（apply_filters は header/footer と共有）。
 *
 * @return string 資料請求フォーム URL。
 */
function bankofart_document_request_url() {
	// 自前の資料請求フォームページ（/document-request/）へ。確定後の差し替えはフィルターで。
	return apply_filters( 'bankofart_document_request_url', home_url( '/document-request/' ) );
}

/**
 * オンライン説明会予約の URL を返す（一元管理）。
 *
 * 暫定は現行運用の外部予約（receptionist）。将来 内部の説明会予約システムを実装したら、
 * このフィルター既定値を差し替える（apply_filters は header/footer と共有）。
 *
 * @return string オンライン説明会予約 URL。
 */
function bankofart_briefing_url() {
	// 自前のオンライン説明会予約ページ（/online-briefing/）へ。差し替えはフィルターで。
	return apply_filters( 'bankofart_briefing_url', home_url( '/online-briefing/' ) );
}

/**
 * JOURNAL 記事のデザイン形式を判定する。
 *
 * 管理画面の「記事デザイン」が auto（既定）なら、カテゴリー（journal_category）が
 * 「インタビュー」のとき interview、それ以外は column を返す。
 * auto 以外が選ばれていればその指定を優先する。
 *
 * @param int|null $post_id 投稿ID。省略時は現在の投稿。
 * @return string 'interview' または 'column'。
 */
function bankofart_journal_layout( $post_id = null ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	if ( ! $post_id ) {
		return 'column';
	}

	$layout = function_exists( 'rwmb_meta' ) ? rwmb_meta( 'journal_layout', array(), $post_id ) : '';
	if ( 'interview' === $layout || 'column' === $layout ) {
		return $layout;
	}

	// auto：カテゴリー名で判定する。
	$terms = get_the_terms( $post_id, 'journal_category' );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( 'インタビュー' === $term->name || 'interview' === $term->slug ) {
				return 'interview';
			}
		}
	}

	return 'column';
}

/**
 * テキスト／wysiwyg の値を「1行1項目」の配列に正規化する。
 *
 * 個展・グループ展（textarea・改行区切り）と学歴（wysiwyg・HTML）を
 * 同じリスト形式（.as-record-list）で描画するために使う。
 *   - HTML の場合：</p> </li> <br> 等の区切りを改行に変換してからタグを除去
 *   - プレーンテキストの場合：改行でそのまま分割
 * 先頭の箇条書き記号（・- —）と空行は取り除く。
 *
 * @param string $value textarea または wysiwyg の値。
 * @return string[] 行の配列（空なら空配列）。
 */
function bankofart_value_to_lines( $value ) {
	$value = (string) $value;
	if ( '' === trim( $value ) ) {
		return array();
	}

	// ブロック／改行タグを改行へ。タグを含まない場合は素通りする。
	if ( false !== strpos( $value, '<' ) ) {
		$value = preg_replace( '~<(br|BR)\s*/?>~', "\n", $value );
		$value = preg_replace( '~</(p|div|li|h[1-6]|tr)\s*>~i', "\n", $value );
		$value = wp_strip_all_tags( $value );
		$value = html_entity_decode( $value, ENT_QUOTES, 'UTF-8' );
	}

	$lines = preg_split( '/\r\n|\r|\n/', $value );
	$out   = array();

	foreach ( (array) $lines as $line ) {
		// 全角スペース・ノーブレークスペースも空白として扱う。
		$line = trim( preg_replace( '/^[\x{30FB}\x{FF65}\-\x{2014}\x{2013}\*\x{25CF}\x{25CB}]+\s*/u', '', trim( $line ) ) );
		$line = trim( str_replace( array( "\xc2\xa0", "\xe3\x80\x80" ), ' ', $line ) );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return $out;
}

/**
 * 学歴・経歴・展示歴などの「年月＋内容」を統一表記に整形する。
 *
 * 入力側（画家本人の申請フォーム／CSV）は書き方がバラバラになるため、表示時に
 * 「年 / 月 / 内容」の3要素へ分解して揃える。DBの値は書き換えない（非破壊）。
 *
 * 吸収できる書き方：
 *   1. 年が見出し行、内容が次行以降（年は次の年行まで引き継ぐ）
 *        2024
 *        ・IndependentTokyo2024
 *        ・GINZA ART FESTA in 松屋銀座
 *   2. 1行に年と内容
 *        2022 個展「東樹生展」
 *        2024年3月 グループ展
 *        2024/3 グループ展
 *   3. 年の範囲
 *        2017〜2021
 *        2021〜
 *   4. 行頭の箇条書き記号（・ ー - — * ● ○）は除去
 *
 * 同じ年に複数行が続く場合、年は先頭行にだけ出し、続く行は空欄にして列を揃える
 * （'year' が空文字で 'year_raw' に元の年が入る）。
 *
 * @param string $value textarea または wysiwyg の値。
 * @return array[] array( array( 'year' => '2024', 'year_raw' => '2024', 'month' => '3月', 'text' => '…' ), … )
 */
function bankofart_parse_record_lines( $value ) {
	$lines = bankofart_value_to_lines( $value );
	if ( empty( $lines ) ) {
		return array();
	}

	$entries      = array();
	$current_year = '';
	$last_printed = null; // 直前に年を出力した行の年（重複表示を避けるため）。

	foreach ( $lines as $line ) {
		$year  = '';
		$month = '';
		$text  = $line;

		// 行頭の西暦4桁を取り出す。
		if ( preg_match( '/^(\d{4})\s*年?\s*(.*)$/u', $line, $m ) ) {
			$year = $m[1];
			$rest = trim( $m[2] );

			// 「2017〜2021」「2021〜」形式の範囲表記。
			if ( preg_match( '/^[〜~ー－\-–—]\s*(\d{4})?\s*年?$/u', $rest, $rm ) ) {
				$current_year = $year . '〜' . ( isset( $rm[1] ) ? $rm[1] : '' );
				$last_printed = null; // 次の行で年を出力させる。
				continue;
			}

			// 年だけの見出し行 → 以降の行に引き継ぐ。
			if ( '' === $rest ) {
				$current_year = $year;
				$last_printed = null;
				continue;
			}

			// 「3月」または「/3」「.3」「-3」形式の月。
			if ( preg_match( '/^(\d{1,2})\s*月\s*(.*)$/u', $rest, $mm ) ) {
				$month = $mm[1] . '月';
				$rest  = trim( $mm[2] );
			} elseif ( preg_match( '/^[\/.\-]\s*(\d{1,2})(?!\d)\s*(.*)$/u', $rest, $mm ) ) {
				$month = (int) $mm[1] . '月';
				$rest  = trim( $mm[2] );
			}

			// 年月と内容の間に残った区切り記号を落とす。
			$text         = ltrim( $rest, " \t.,、:：/／-–—" );
			$current_year = $year;
		} elseif ( '' !== $current_year ) {
			// 年行の配下にある内容行。
			$year = $current_year;
		}

		if ( '' === trim( $text ) ) {
			continue;
		}

		// 同じ年が連続する場合、2行目以降は年を空欄にして列を揃える。
		$show_year    = ( '' !== $year && $year !== $last_printed ) ? $year : '';
		$last_printed = ( '' !== $year ) ? $year : $last_printed;

		$entries[] = array(
			'year'     => $show_year,
			'year_raw' => $year,
			'month'    => $month,
			'text'     => $text,
		);
	}

	return $entries;
}

/**
 * Meta Box の wysiwyg フィールドを本文として出力する共通ルール。
 *
 * wysiwyg の保存値は WordPress の post_content と同じ「素のテキスト＋改行」で、
 * <p> は入っていない。post_content なら the_content フィルタが wpautop を
 * かけてくれるが、カスタムフィールドは自前で通す必要がある。通していないと
 * 段落の空行（\r\n\r\n）が単なる空白に潰れ、長い回答が一続きの塊になって読めない。
 *
 * 適用順は the_content と同じ：
 *   1. wpautop        … 空行→段落 <p> ／ 単独の改行→<br>
 *   2. shortcode_unautop … ショートコードが <p> に包まれるのを戻す
 *   3. do_shortcode
 *   4. 画像の拡大（bankofart_enlarge_content_images）
 *
 * wpautop は既に <p> や <ul> 等のブロック要素で組まれた HTML には
 * 二重に段落を付けないので、JSON取込などで整形済みの値を渡しても安全。
 *
 * @param string $html wysiwyg の保存値。
 * @return string 出力用HTML。
 */
function bankofart_richtext( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return '';
	}

	$html = wpautop( $html );
	$html = shortcode_unautop( $html );
	$html = do_shortcode( $html );

	return bankofart_enlarge_content_images( $html );
}

/**
 * 本文（wysiwyg）に挿入された画像を「大きく・鮮明に」表示できるよう整える。
 *
 * クラシックエディタで「中サイズ」等を選んで挿入すると、src が縮小版（例 -300x216）に
 * 固定され width/height 属性も付くため小さく表示される。本関数は：
 *   - class の wp-image-{ID} から添付IDを取得し、src を large（無ければ full）に差し替え
 *   - 古い srcset / sizes / width / height 属性を除去（表示幅は CSS 側に委ねる）
 * これにより本文のメイン画像が本文幅いっぱいに大きく、かつ縮小版でない鮮明な画像で出る。
 * 添付IDが取れない場合は src のサイズ接尾辞（-WxH）を除去して原寸に寄せる。
 *
 * @param string $html 本文HTML。
 * @return string 変換後HTML。
 */
function bankofart_enlarge_content_images( $html ) {
	if ( '' === (string) $html || false === strpos( $html, '<img' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'~<img\b[^>]*>~i',
		static function ( $m ) {
			$tag     = $m[0];
			$new_src = '';
			if ( preg_match( '~wp-image-(\d+)~', $tag, $idm ) ) {
				$id      = (int) $idm[1];
				$new_src = wp_get_attachment_image_url( $id, 'large' );
				if ( ! $new_src ) {
					$new_src = wp_get_attachment_image_url( $id, 'full' );
				}
			}
			if ( $new_src ) {
				$tag = preg_replace( '~\ssrc=("|\')[^"\']*\1~i', ' src="' . esc_url( $new_src ) . '"', $tag );
			} else {
				// 添付ID不明：サイズ接尾辞を除去して原寸URLに寄せる（-scaled 等はそのまま）。
				$tag = preg_replace( '~(src=("|\')[^"\']*?)-\d+x\d+(\.(?:jpe?g|png|webp|gif)\2)~i', '$1$3', $tag );
			}
			// 縮小前提の属性を除去（表示サイズは CSS に委ねる）。
			$tag = preg_replace( '~\ssrcset=("|\')[^"\']*\1~i', '', $tag );
			$tag = preg_replace( '~\ssizes=("|\')[^"\']*\1~i', '', $tag );
			$tag = preg_replace( '~\swidth=("|\')\d+\1~i', '', $tag );
			$tag = preg_replace( '~\sheight=("|\')\d+\1~i', '', $tag );
			return $tag;
		},
		$html
	);
}
