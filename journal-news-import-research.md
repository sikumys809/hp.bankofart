# JOURNAL / NEWS 外部データ取込 調査レポート

**目的**: journal・news CPT への記事データ外部取込（自動化）を実装するための、既存構造の全量調査。
**調査日**: 2026-07-31
**調査対象**: `wp-content/themes/bankofart/`（テーマ）＋ ローカルDB `local`（`wp_` プレフィックス）
**DB接続**: Local by Flywheel / MySQL 8.4 / `127.0.0.1:10005` / user `root`

---

## 0. 結論サマリー（実装前に押さえるべき5点）

| # | 結論 | 影響 |
|---|---|---|
| 1 | 繰り返しフィールドは **PHP serialize された1行**（`_qa_0_question` のようなフラット保存ではない） | `update_post_meta($id, 'journal_interview_qa', $rows)` に**PHP配列をそのまま渡せばよい**（WPが自動でserialize） |
| 2 | グループ内の画像（`section_images` / `qa_images`）は**serialize配列の中のネスト配列**（添付IDの文字列） | グループ行の配列にそのまま `array('213','215')` を入れる |
| 3 | **トップレベル**の `image_advanced` だけは**同一meta_keyの複数行**（Meta Box `multiple:true`） | journal/news には該当なし。artist/art の取込時のみ注意 |
| 4 | カテゴリー（コラム/インタビュー等）は **postmeta に保存されない**。`wp_term_relationships` のみ | `wp_set_object_terms()` が必須。`*_picker` という meta_key は**存在しない**（実測0件） |
| 5 | 関連アーティスト/作品は postmeta ではなく **`wp_mb_relationships` 専用テーブル** | `MB_Relationships_API::add()` を使う |

---

## 1. CPT定義

**登録ファイル**: [`inc/post-types.php`](inc/post-types.php)
**関数**: `bankofart_register_post_types()` / **フック**: `add_action('init', ..., 0)`
Meta Box の MB Custom Post Type ではなく、**WP標準の `register_post_type`** で登録している。

`inc/post-types.php:22-58` の配列で5CPTを一括定義し、`register_post_type()`（`:79-95`）に流している。

### journal

| 項目 | 値 |
|---|---|
| post_type スラッグ | `journal` |
| 表示名 | JOURNAL |
| rewrite slug | `journal`（`with_front => false`）→ `/journal/{post_name}/` |
| `has_archive` | `true` → `/journal/` |
| `public` | `true` |
| `hierarchical` | `false` |
| `show_in_rest` | `true` |
| **`supports`** | **`array('title', 'thumbnail')` のみ** |
| menu_position / icon | 9 / `dashicons-book-alt` |
| taxonomy | `journal_category` |

### news

| 項目 | 値 |
|---|---|
| post_type スラッグ | `news` |
| 表示名 | NEWS |
| rewrite slug | `news`（`with_front => false`）→ `/news/{post_name}/` |
| `has_archive` | `true` → `/news/` |
| `public` | `true` |
| `hierarchical` | `false` |
| `show_in_rest` | `true` |
| **`supports`** | **`array('title', 'thumbnail')` のみ** |
| menu_position / icon | 8 / `dashicons-megaphone` |
| taxonomy | `news_category` |

> ⚠️ **`editor` / `excerpt` を supports していない**。本文は `post_content` ではなく
> **`journal_sections` / `news_sections`（Meta Boxリピーター）** に入る。
> 取込時に `post_content` へ本文を入れても**フロントには一切表示されない**。

### タクソノミー

**登録ファイル**: [`inc/taxonomies.php`](inc/taxonomies.php) / 定義は `bankofart_get_taxonomy_config()`（`:103-114`）
両方とも `hierarchical => false`（フラット/タグ型）、`meta_box_cb => false`（標準UIは無効、入力はMeta Boxピッカーに一本化）。

| taxonomy | 対象CPT | rewrite slug | ターム（**DB実測の term_id**） |
|---|---|---|---|
| `journal_category` | journal | `journal-category` | コラム=**59** / インタビュー=**60** |
| `news_category` | news | `news-category` | 受賞=**55** / 展示=**56** / メディア掲載=**57** / お知らせ=**58** |

### その他のCPT挙動

- `bankofart_disable_block_editor()`（`inc/post-types.php:112`）— 5CPTすべて**クラシックエディター**固定
- `bankofart_force_show_meta_boxes()`（`:135`）— `bankofart_journal` / `bankofart_news` を画面オプションで常に表示

---

## 2. Meta Box フィールド全量

**定義ファイル**: [`inc/meta-box-fields.php`](inc/meta-box-fields.php)
**関数**: `bankofart_register_meta_boxes()` / **フック**: `add_filter('rwmb_meta_boxes', ...)`

### 2-1. JOURNAL（メタボックスID: `bankofart_journal`／4タブ）

| フィールドID | 日本語ラベル | type | 繰り返し | 親グループ | タブ | 備考 |
|---|---|---|---|---|---|---|
| `journal_summary` | 要約（カード表示用） | textarea | – | – | basic | 1〜2文 |
| `journal_author` | 著者名 | text | – | – | basic | |
| `journal_reading_time` | 読了時間（分） | number | – | – | basic | |
| `journal_main_image` | メイン写真 | single_image | – | – | basic | 添付ID |
| `journal_category_picker` | カテゴリー | **taxonomy**(select) | – | – | basic | **postmetaに保存されない** |
| `journal_layout` | 記事デザイン | select | – | – | basic | `auto`(既定)/`column`/`interview` |
| **`journal_sections`** | **本文セクション** | **group** | **clone: true**<br>sort_clone: true | – | body | コラム用本文 |
| &emsp;`section_heading` | 見出し | text | – | **`journal_sections`** | body | |
| &emsp;`section_body` | 本文 | wysiwyg | – | **`journal_sections`** | body | |
| &emsp;`section_images` | サブ写真 | image_advanced | 複数値 | **`journal_sections`** | body | max 10 |
| `journal_interview_intro` | 導入文（リード） | wysiwyg | – | – | interview | |
| `journal_speaker_name` | 話し手の名前 | text | – | – | interview | 空なら関連アーティストから補完 |
| `journal_speaker_role` | 話し手の肩書き | text | – | – | interview | |
| `journal_speaker_icon` | 話し手のアイコン | single_image | – | – | interview | 空なら関連アーティストの写真 |
| `journal_interviewer_name` | 聞き手の名前 | text | – | – | interview | std `BOA` |
| `journal_interviewer_icon` | 聞き手のアイコン | single_image | – | – | interview | 空ならBOAロゴ |
| **`journal_interview_qa`** | **Q&A（質問と回答）** | **group** | **clone: true**<br>sort_clone: true | – | interview | インタビュー用本文 |
| &emsp;`qa_chapter` | 章見出し（任意） | text | – | **`journal_interview_qa`** | interview | |
| &emsp;`qa_question` | 質問（聞き手） | textarea | – | **`journal_interview_qa`** | interview | rows 3 |
| &emsp;`qa_answer` | 回答（話し手） | wysiwyg | – | **`journal_interview_qa`** | interview | |
| &emsp;`qa_images` | 回答に添える写真 | image_advanced | 複数値 | **`journal_interview_qa`** | interview | max 10 |
| `journal_show_related_artist` | Related Artist セクション | switch | – | – | section_display | std `1` |
| `journal_show_related_art` | Related Art セクション | switch | – | – | section_display | std `1` |
| `journal_show_more_journal` | MORE JOURNAL セクション | switch | – | – | section_display | std `1` |
| `journal_show_cta` | CTA セクション | switch | – | – | section_display | std `1` |

### 2-2. NEWS（メタボックスID: `bankofart_news`／3タブ）

| フィールドID | 日本語ラベル | type | 繰り返し | 親グループ | タブ | 備考 |
|---|---|---|---|---|---|---|
| `news_summary` | 要約（カード表示用） | textarea | – | – | basic | 1〜2文 |
| `news_main_image` | メイン写真 | single_image | – | – | basic | 添付ID |
| `news_external_url` | 外部リンクURL | url | – | – | basic | メディア掲載の元記事 |
| `news_external_label` | 外部リンクラベル | text | – | – | basic | 例：PR TIMESで読む |
| `news_category_picker` | カテゴリー | **taxonomy**(select) | – | – | basic | **postmetaに保存されない** |
| **`news_sections`** | **本文セクション** | **group** | **clone: true**<br>sort_clone: true | – | body | |
| &emsp;`section_heading` | 見出し | text | – | **`news_sections`** | body | |
| &emsp;`section_body` | 本文 | wysiwyg | – | **`news_sections`** | body | |
| &emsp;`section_images` | サブ写真 | image_advanced | 複数値 | **`news_sections`** | body | max 10 |
| `news_show_related_artist` | Related Artist セクション | switch | – | – | section_display | std `1` |
| `news_show_related_art` | Related Art セクション | switch | – | – | section_display | std `1` |
| `news_show_more_news` | MORE NEWS セクション | switch | – | – | section_display | std `1` |
| `news_show_cta` | CTA セクション | switch | – | – | section_display | std `1` |

> journal と news の **サブフィールドキーは完全に同名**（`section_heading` / `section_body` / `section_images`）。
> 区別は親グループのキー（`journal_sections` / `news_sections`）だけ。

### 2-3. 表示スイッチの補完

[`inc/section-display-guard.php`](inc/section-display-guard.php) が、上表の `*_show_*` キーが**未保存の投稿に対して `'1'` を補完**する。
CSV/API取込でスイッチを入れ忘れてもセクションが消えないための保険。取込側で明示的に `'1'` を入れてもよい。

---

## 3. 関連付けフィールド（関連アーティスト / 関連作品）

### ⚠️ postmeta ではなく専用テーブル

`journal` / `news` の Meta Box 定義には **post型フィールドは1つも無い**。
関連付けは **MB Relationships** で、[`inc/relationships.php`](inc/relationships.php) の
`bankofart_register_relationships()`（フック: `add_action('mb_relationships_init', ...)`）で登録している。

| relationship ID | from | to | 意味 |
|---|---|---|---|
| `journal_to_artist` | **journal** | artist | 関連アーティスト |
| `journal_to_art` | **journal** | art | 関連作品 |
| `news_to_artist` | **news** | artist | 関連アーティスト |
| `news_to_art` | **news** | art | 関連作品 |
| `artist_to_art` | artist | art | （参考）アーティストの作品 |
| `art_to_owner` | art | collector | （参考）所有企業 |

### 保存先テーブル `wp_mb_relationships`（実測）

```
Field       Type              Null  Key  Extra
ID          bigint unsigned   NO    PRI  auto_increment
from        bigint unsigned   NO    MUL
to          bigint unsigned   NO    MUL
type        varchar(44)       NO    MUL
order_from  bigint unsigned   NO
order_to    bigint unsigned   NO
```

実データ（NEWS→アーティスト）：

```
ID   from   to    type              order_from  order_to
58   184    162   news_to_artist    1           1
60   182    99    news_to_artist    1           1
64   183    151   news_to_artist    1           1
```

| 質問 | 回答 |
|---|---|
| フィールドtype | post型フィールドではない。**MB Relationships**（専用テーブル） |
| フィールドID | 無し。`type` 列の値（`journal_to_artist` 等）で識別 |
| 単数/複数 | **複数可**（1記事につき複数行を追加できる） |
| 保存される値 | **投稿ID**（`from` = journal/news の post ID、`to` = artist/art の post ID） |
| 並び順 | `order_from` / `order_to`（1始まり） |

### 取込時のAPI

```php
// journal(123) に artist(99) を関連付ける
MB_Relationships_API::add( 123, 99, 'journal_to_artist' );
// 削除
MB_Relationships_API::delete( 123, 99, 'journal_to_artist' );
```

読み出しはテーマの `bankofart_get_connected()`（[`inc/helpers.php:175`](inc/helpers.php)）でラップ済み。

---

## 4. タイプ分岐（interview / column / news）

### news と journal は「別CPT」で分岐

`news` は **post_type そのものが別**。テンプレートも `single-news.php` / `single-journal.php` に完全分離。
判定コードは存在せず、WPのテンプレート階層で自動的に分かれる。

### journal 内の interview / column の分岐

**判定関数**: `bankofart_journal_layout( $post_id )` — [`inc/helpers.php`](inc/helpers.php)
**呼び出し**: [`single-journal.php`](single-journal.php)（`$layout = bankofart_journal_layout( $jid );`）

判定は **メタフィールド優先 → タクソノミーにフォールバック** の2段。

| 優先 | 判定材料 | 値 | 結果 |
|---|---|---|---|
| 1 | メタ `journal_layout` | `'interview'` | interview |
| 1 | メタ `journal_layout` | `'column'` | column |
| 2 | 上記が `'auto'` / 空 のとき<br>タクソノミー `journal_category` | term名 `'インタビュー'`<br>または slug `'interview'` | interview |
| 2 | 同上（それ以外のターム／未設定） | — | **column（既定）** |

```php
// inc/helpers.php（抜粋）
$layout = rwmb_meta( 'journal_layout', array(), $post_id );
if ( 'interview' === $layout || 'column' === $layout ) { return $layout; }
$terms = get_the_terms( $post_id, 'journal_category' );
foreach ( $terms as $term ) {
    if ( 'インタビュー' === $term->name || 'interview' === $term->slug ) { return 'interview'; }
}
return 'column';
```

> ⚠️ **タームのslugはURLエンコードされた日本語**（実測: `%e3%82%a4%e3%83%b3%e3%82%bf%e3%83%93%e3%83%a5%e3%83%bc`）。
> slug一致は事実上機能せず、**term名 `'インタビュー'` での一致が実質の判定**。
> 取込では **`journal_layout` を明示的に入れるのが最も確実**。

### 分岐の帰結

| layout | 使う本文フィールド | 描画 |
|---|---|---|
| `column` | `journal_sections` | `single-journal.php` 内でインライン描画 |
| `interview` | `journal_interview_qa` ＋ 話し手/聞き手フィールド | `template-parts/journal/body-interview.php` |

`<main>` に `single-journal--column` / `single-journal--interview` クラスが付く。
著者表記も `column`＝「文 ／」、`interview`＝「取材・文 ／」に変わる。

---

## 5. 既存「BOA CSV取込」の実装

### プラグインではなく **自作PHP**

`CLAUDE.md` には「WP All Import Pro（必須）」と書かれているが、**インストールされていない**。
現在有効なプラグインは `meta-box` / `meta-box-aio` / `seo-simple-pack` / `all-in-one-wp-migration` のみ。
「BOA CSV取込」は**テーマ内の自作実装**。

### ファイル構成

| ファイル | 役割 |
|---|---|
| [`inc/csv-import/admin.php`](inc/csv-import/admin.php) | 管理メニュー・フォーム・結果表示 |
| [`inc/csv-import/importer.php`](inc/csv-import/importer.php) | CSV読込・列マッピング・取込処理 |

読み込みは [`functions.php`](functions.php) の `is_admin()` ブロック（フロントでは読まれない）:

```php
if ( is_admin() ) {
    $bankofart_admin_only = array(
        'inc/document-request/admin.php',
        'inc/document-request/csv-export.php',
        'inc/csv-import/importer.php', // ← 先に読む
        'inc/csv-import/admin.php',
    );
```

### 関数とフック

| 関数 | フック / 呼び出し元 | 役割 |
|---|---|---|
| `bankofart_csv_import_menu()` | **`add_action('admin_menu', ...)`** | 管理メニュー「BOA CSV取込」登録（`add_menu_page`, slug `bankofart-csv-import`, cap `manage_options`） |
| `bankofart_csv_import_page()` | メニューのページコールバック | 画面描画 |
| `bankofart_csv_import_handle_post()` | `bankofart_csv_import_page()` 内から呼ぶ | POST受け・nonce検証・実行 |
| `bankofart_csv_import_validate_upload()` | 同上 | アップロード検証 |
| **`bankofart_csv_import_types()`** | — | **★列マッピング定義。journal/news 追加はここ** |
| **`bankofart_csv_import_rows()`** | — | **★取込ループ本体（作成/更新/スキップ判定）** |
| `bankofart_csv_read_file()` | — | CSV解析（UTF-8/SJIS自動判別、BOM除去、見出し一意化） |
| `bankofart_csv_detect_timestamp_column()` | — | 重複判定用の列を検出 |
| `bankofart_csv_pick()` / `bankofart_csv_split_multi()` | — | 列名ゆれ吸収 / 複数値分割 |
| `bankofart_csv_resolve_terms()` | — | ターム名→term_id（**新規タームは作らない**） |
| `bankofart_csv_resolve_image()` | — | 画像URL→添付ID（Googleドライブリンク変換込み） |
| `bankofart_csv_unmapped_columns()` | — | 未対応列の報告 |

### 重複防止の仕組み

| 項目 | 内容 |
|---|---|
| 一意キー | CSVの「タイムスタンプ」列 → `sha1( type . '|ts|' . 値 )` |
| 保存先 | 投稿メタ `_bankofart_import_key`（定数 `BANKOFART_CSV_IMPORT_KEY_META`） |
| 監査ログ | 投稿メタ `_bankofart_import_source`（JSON、`BANKOFART_CSV_IMPORT_LOG_META`） |
| 照合 | `bankofart_csv_find_by_import_key()` — ゴミ箱も含めて検索 |
| 1回の上限 | `BANKOFART_CSV_MAX_ROWS = 300` 行 |

### journal / news を追加するときの手順

1. `bankofart_csv_import_types()` に `'journal' => array(...)` / `'news' => array(...)` を追加
   （`label` / `post_type` / `title` / `slug` / `meta` / `tax` / `images` / `relation` / `ignored`）
2. **繰り返しフィールドは既存の平坦なマッピングでは扱えない**ため、
   `bankofart_csv_import_rows()` に**グループ組み立て処理の追加が必要**（下記6章の形式で配列を作って `update_post_meta`）
3. `relation` は現在1つしか持てない構造（`$def['relation']` が単数）。
   journal は artist と art の**2系統**あるため、`relation` を配列化する改修が要る

---

## 6. 保存形式サンプル（★実際のSELECT結果）

### 6-1. 結論：**PHP serialize された1行**。フラット保存（`_qa_0_question`）ではない

Meta Box 本体のソースで確定（`plugins/meta-box/inc/field.php:256-270`）：

```php
// If field is cloneable AND not force to save as multiple rows, value is saved as a single row in the database.
if ( $field['clone'] && ! $field['clone_as_multiple'] ) {
    $storage->update( $post_id, $name, $new );   // ← serialize された1行
    return;
}
// Save cloned fields as multiple values instead serialized array.
if ( ( $field['clone'] && $field['clone_as_multiple'] ) || $field['multiple'] ) {
    $storage->delete( $post_id, $name );
    foreach ( (array) $new as $new_value ) {
        $storage->add( $post_id, $name, $new_value, false );  // ← 複数行
    }
    return;
}
```

テーマの group フィールドは `clone_as_multiple` を指定していない（＝既定 `false`）ので、**すべて前者＝serialize 1行**。
`image_advanced` は Meta Box が内部で `multiple = true` を強制する（`inc/fields/media.php:111`）ため、
**トップレベルに置いた場合のみ**後者＝複数行になる（journal/news には該当なし）。

### 6-2. journal 実データ（post_id=427 / インタビュー / 下書き）

```sql
SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = 427 ORDER BY meta_id;
```

| meta_key | meta_value |
|---|---|
| `_edit_last` | `1` |
| `_edit_lock` | `1785465969:1` |
| `journal_summary` | `インタビュー記事のテストです。` |
| `journal_author` | `岡田` |
| `journal_reading_time` | `6` |
| `journal_main_image` | `0` ← **未設定は "0"（空文字ではない）** |
| `journal_layout` | `interview` |
| `journal_interview_intro` | `<p>インタビューしました</p>\n` |
| `journal_speaker_name` | `アーティスト名` |
| `journal_speaker_role` | `画家` |
| `journal_speaker_icon` | `0` |
| `journal_interviewer_name` | `BOA` |
| `journal_interviewer_icon` | `0` |
| **`journal_interview_qa`** | **下記の serialize 値（1行のみ）** |
| `journal_show_related_artist` | `1` |
| `journal_show_related_art` | `1` |
| `journal_show_more_journal` | `1` |
| `journal_show_cta` | `1` |

**`journal_interview_qa` の生の値：**

```
a:1:{i:0;a:3:{s:10:"qa_chapter";s:9:"質問１";s:11:"qa_question";s:21:"ーーーですか？";s:9:"qa_answer";s:18:"ーーーでした";}}
```

対応するPHP配列：

```php
array(
    0 => array(
        'qa_chapter'  => '質問１',
        'qa_question' => 'ーーーですか？',
        'qa_answer'   => 'ーーーでした',
        // 'qa_images' は未入力 → キー自体が存在しない（a:3 であって a:4 ではない）
    ),
)
```

> ⚠️ **空のサブフィールドはキーごと省かれる**。読み出し側は `isset()` 必須
> （テーマ側は `isset( $row['qa_answer'] )` で対応済み）。

### 6-3. グループ内の画像（★ネスト配列）— news post_id=184 の実データ

```
a:1:{i:0;a:2:{s:12:"section_body";s:1731:"<p …本文HTML… </p>";s:14:"section_images";a:2:{i:0;s:3:"213";i:1;s:3:"215";}}}
```

該当部分だけ抜き出すと：

```
s:14:"section_images";a:2:{i:0;s:3:"213";i:1;s:3:"215";}
```

対応するPHP配列：

```php
array(
    0 => array(
        'section_body'   => '<p>…本文HTML…</p>',
        'section_images' => array( '213', '215' ),   // ← 添付IDの「文字列」配列
    ),
)
```

### 6-4. journal コラム記事（post_id=381）— 見出しなしセクションの例

```
a:7:{i:0;a:1:{s:12:"section_body";s:1008:"<p …>…</p>";} … }
```

- 7セクション、うち `section_heading` を持つのは6件（`a:1` の行は本文のみ）
- **`journal_layout` の行が存在しない**（フィールド追加前の投稿）→ `bankofart_journal_layout()` が
  タクソノミー（コラム）にフォールバックして `column` と判定される

### 6-5. その他フィールドの保存形式（実測）

| type | 保存形式 | 実例 |
|---|---|---|
| `text` / `textarea` / `url` / `number` | 文字列1行 | `journal_author` = `岡田` |
| `wysiwyg` | HTML文字列1行 | `journal_interview_intro` = `<p>…</p>\n` |
| `select` | 選択値の文字列1行 | `journal_layout` = `interview` |
| `switch` | `1` / `0` の文字列1行 | `journal_show_cta` = `1` |
| `single_image` | 添付ID1行。**未設定は `0`** | `journal_main_image` = `383` |
| `group`(clone) | **serialize配列1行** | 6-2 参照 |
| `image_advanced`（グループ内） | serialize配列内の**ネスト配列** | 6-3 参照 |
| `image_advanced`（トップレベル） | **同一meta_keyの複数行** | journal/newsには無し |
| **`taxonomy`ピッカー** | **postmetaに行を作らない** | 実測 `meta_key LIKE '%_picker'` = **0件** |

### 6-6. アイキャッチの自動設定

[`inc/auto-featured-image.php`](inc/auto-featured-image.php) が
`rwmb_after_save_post` と `save_post`（優先度99）で、
`journal_main_image` / `news_main_image` の添付IDを `_thumbnail_id` にコピーする。

- 既にアイキャッチがある投稿は**上書きしない**
- 実測: post 381 は `journal_main_image` = `383`、`_thumbnail_id` = `383`
- `wp_insert_post()` 経由の取込でも `save_post` で発火するため、**取込側で `_thumbnail_id` を設定する必要はない**

### 6-7. カテゴリーの保存先（postmetaではない）

```sql
SELECT p.ID, tt.taxonomy, t.name, t.term_id FROM wp_posts p
JOIN wp_term_relationships tr ON tr.object_id = p.ID
JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
JOIN wp_terms t ON t.term_id = tt.term_id
WHERE p.post_type IN ('journal','news');
```

```
ID   post_type  taxonomy          name          term_id
182  news       news_category     メディア掲載    57
183  news       news_category     展示           56
184  news       news_category     受賞           55
381  journal    journal_category  コラム         59
427  journal    journal_category  インタビュー    60
```

---

## 7. 取込実装のひな形

上記をすべて満たす最小の取込コード：

```php
/**
 * JOURNAL（インタビュー）を1件取り込む例。
 */
function bankofart_import_journal_example( array $data ) {

    // 1. 投稿本体（本文は post_content ではなくメタに入れるので post_content は空でよい）
    $post_id = wp_insert_post( array(
        'post_type'   => 'journal',
        'post_title'  => $data['title'],
        'post_status' => 'draft',            // 公開は人が確認してから
        'post_name'   => sanitize_title( $data['slug'] ),
    ), true );
    if ( is_wp_error( $post_id ) ) { return $post_id; }

    // 2. 単純メタ
    update_post_meta( $post_id, 'journal_summary',          $data['summary'] );
    update_post_meta( $post_id, 'journal_author',           $data['author'] );
    update_post_meta( $post_id, 'journal_reading_time',     (int) $data['reading_time'] );
    update_post_meta( $post_id, 'journal_layout',           'interview' ); // ★明示が最確実
    update_post_meta( $post_id, 'journal_interviewer_name', 'BOA' );
    update_post_meta( $post_id, 'journal_speaker_name',     $data['speaker'] );

    // 3. 画像は「添付ID」を入れる（URLではない）
    update_post_meta( $post_id, 'journal_main_image', (int) $data['main_image_id'] );

    // 4. 繰り返し（Q&A）— PHP配列をそのまま渡せばWPが serialize する
    $qa_rows = array();
    foreach ( $data['qa'] as $qa ) {
        $row = array(
            'qa_question' => $qa['q'],
            'qa_answer'   => $qa['a'],       // wysiwyg なのでHTML可
        );
        if ( ! empty( $qa['chapter'] ) ) { $row['qa_chapter'] = $qa['chapter']; }
        if ( ! empty( $qa['image_ids'] ) ) {
            $row['qa_images'] = array_map( 'strval', $qa['image_ids'] ); // ネスト配列（文字列ID）
        }
        $qa_rows[] = $row;
    }
    update_post_meta( $post_id, 'journal_interview_qa', $qa_rows );

    // 5. 表示スイッチ（未設定でも guard が補完するが明示推奨）
    foreach ( array( 'journal_show_related_artist', 'journal_show_related_art',
                     'journal_show_more_journal', 'journal_show_cta' ) as $sw ) {
        update_post_meta( $post_id, $sw, '1' );
    }

    // 6. カテゴリー（★postmetaではなくターム）
    $term = get_term_by( 'name', 'インタビュー', 'journal_category' );
    if ( $term ) {
        wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'journal_category', false );
    }

    // 7. 関連付け（★専用テーブル）
    if ( class_exists( 'MB_Relationships_API' ) ) {
        foreach ( (array) $data['artist_ids'] as $aid ) {
            MB_Relationships_API::add( $post_id, (int) $aid, 'journal_to_artist' );
        }
        foreach ( (array) $data['art_ids'] as $art_id ) {
            MB_Relationships_API::add( $post_id, (int) $art_id, 'journal_to_art' );
        }
    }

    // 8. 重複防止キー（既存CSV取込と同じ運用に乗せる）
    update_post_meta( $post_id, BANKOFART_CSV_IMPORT_KEY_META, sha1( 'journal|ts|' . $data['timestamp'] ) );

    return $post_id;
}
```

### 落とし穴チェックリスト

- [ ] `post_content` に本文を入れていないか（**supports に editor が無く、表示されない**）
- [ ] 画像フィールドに**URLではなく添付ID**を入れているか
- [ ] `qa_images` / `section_images` を**文字列IDの配列**にしているか
- [ ] カテゴリーを `*_picker` というmeta_keyに入れようとしていないか（**そんなキーは無い**）
- [ ] 関連アーティストを postmeta に入れようとしていないか（**`wp_mb_relationships`**）
- [ ] `journal_layout` を入れずにタクソノミー任せにしていないか（term名の完全一致に依存する）
- [ ] 既存の `bankofart_csv_import_rows()` はグループ非対応 → **journal/news用に拡張が必要**
- [ ] `$def['relation']` が単数構造 → journal は artist/art の**2系統必要**

---

## 8. 調査に使ったコマンド（再現用）

```bash
# 認証情報は wp-config.php の DB_USER / DB_PASSWORD を参照（ここには書かない）
MYSQL="/c/Users/mizun/AppData/Roaming/Local/lightning-services/mysql-8.4.0/bin/win64/bin/mysql.exe"

# ★ --default-character-set=utf8mb4 を付けないと日本語が文字化けする
"$MYSQL" --default-character-set=utf8mb4 -h 127.0.0.1 -P 10005 -u "$DB_USER" -p"$DB_PASS" local --batch -e "
  SELECT p.ID, p.post_type, pm.meta_key, LEFT(pm.meta_value,120), LENGTH(pm.meta_value)
  FROM wp_posts p JOIN wp_postmeta pm ON pm.post_id = p.ID
  WHERE p.post_type IN ('journal','news') AND p.post_status <> 'auto-draft'
  ORDER BY p.ID, pm.meta_id;"

# serialize 値を生で見る（--raw でエスケープを抑止）
"$MYSQL" --default-character-set=utf8mb4 -h 127.0.0.1 -P 10005 -u "$DB_USER" -p"$DB_PASS" local --batch --raw -e "
  SELECT meta_value FROM wp_postmeta WHERE post_id=427 AND meta_key='journal_interview_qa';"
```

MySQLのポート番号は `%APPDATA%\Local\sites.json` の該当サイトの
`services.mysql.ports.MYSQL` で確認できる（本サイトは **10005**）。
