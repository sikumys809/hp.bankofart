# テーマ構造ドキュメント

`wp-content/themes/bankofart/` 配下のオリジナルWordPressテーマの構造を定義する。

このドキュメントは、テンプレート階層、Meta Boxフィールド、関連付け、データフローを網羅する。Claude Code で実装する際の設計書。

---

## 目次

1. [テーマ全体図](#1-テーマ全体図)
2. [テンプレート対応表](#2-テンプレート対応表)
3. [カスタム投稿タイプ（CPT）](#3-カスタム投稿タイプcpt)
4. [カスタムタクソノミー](#4-カスタムタクソノミー)
5. [Meta Boxフィールド一覧](#5-meta-boxフィールド一覧)
6. [MB Relationships](#6-mb-relationships)
7. [テンプレートパーツ](#7-テンプレートパーツ)
8. [アセット読み込み戦略](#8-アセット読み込み戦略)
9. [URLとパーマリンク設計](#9-urlとパーマリンク設計)
10. [データフロー図](#10-データフロー図)

---

## 1. テーマ全体図

```
bankofart/
├── style.css                   # テーマヘッダー＋メイン読み込み
├── functions.php               # 全 inc/* を require
├── header.php                  # 共通ヘッダー
├── footer.php                  # 共通フッター
├── sidebar.php                 # 使わない（テーマ的に不要）
├── front-page.php              # TOP（index.html ベース）
├── page-about.php              # ABOUT（about.html ベース）
├── page-recruit.php            # JOIN US（recruit.html ベース）
├── page-matching-purpose.php   # パーパス診断
├── page-matching-issue.php     # 課題逆引き診断
├── page-privacy.php            # プライバシーポリシー
├── archive-artist.php          # アーティスト一覧
├── single-artist.php           # アーティスト詳細
├── archive-art.php             # 作品一覧
├── single-art.php              # 作品詳細
├── archive-collector.php       # 画家応援企業一覧
├── single-collector.php        # 画家応援企業詳細
├── archive-news.php            # NEWS一覧
├── single-news.php             # NEWS詳細
├── archive-journal.php         # JOURNAL一覧
├── single-journal.php          # JOURNAL詳細
├── 404.php                     # 404ページ
├── searchform.php              # 検索フォーム（必要なら）
├── inc/                        # PHP分割
│   ├── post-types.php          # CPT登録
│   ├── taxonomies.php          # タクソノミー登録
│   ├── meta-box-fields.php     # Meta Boxフィールド定義
│   ├── relationships.php       # MB Relationships定義
│   ├── enqueue.php             # CSS/JS読み込み
│   ├── customizer.php          # WP Customizer設定
│   ├── shortcodes.php          # ショートコード
│   ├── ajax-handlers.php       # AJAX エンドポイント
│   ├── helpers.php             # ヘルパー関数
│   └── theme-setup.php         # テーマサポート設定
├── template-parts/             # パーシャル
│   ├── header-main.php
│   ├── footer-main.php
│   ├── card-artist.php
│   ├── card-art.php
│   ├── card-collector.php
│   ├── card-news.php
│   ├── card-journal.php
│   ├── filter-artist.php
│   ├── filter-art.php
│   ├── matching-banner-purpose.php
│   ├── matching-banner-issue.php
│   ├── for-artists-banner.php
│   ├── cta-contact.php
│   └── simulator.php
└── assets/
    ├── css/
    │   ├── tokens.css          # デザイントークン（CSS変数定義）
    │   ├── reset.css           # CSSリセット
    │   ├── base.css            # ベーススタイル
    │   ├── header.css
    │   ├── footer.css
    │   ├── components.css      # 共通コンポーネント
    │   ├── style.css           # 統合エントリ（@import）
    │   └── pages/
    │       ├── front.css
    │       ├── about.css
    │       ├── simulator.css
    │       ├── artist.css
    │       ├── art.css
    │       ├── collector.css
    │       ├── news.css
    │       ├── journal.css
    │       ├── recruit.css
    │       └── matching.css
    ├── js/
    │   ├── main.js             # 全ページ共通
    │   ├── header.js           # ハンバーガーメニュー
    │   ├── reveal.js           # スクロールリビール
    │   ├── simulator.js        # 税制シミュレーター
    │   ├── filter.js           # ART/ARTIST/COLLECTORフィルター
    │   ├── matching-purpose.js
    │   └── matching-issue.js
    ├── img/                    # 固定画像
    │   ├── logo/
    │   ├── collector-logos/
    │   ├── icons/
    │   └── og-image.png
    └── fonts/                  # Google Fonts ローカル化（任意）
```

---

## 2. テンプレート対応表

`mockups/` の各HTMLが、テーマ内のどのテンプレートに対応するか。

| モックアップHTML | テンプレート | URL | ページ生成方法 |
|---|---|---|---|
| `index.html` | `front-page.php` | `/` | 静的ページとして固定ページ「TOP」を作成、設定 > 表示設定で固定ページに |
| `about.html` | `page-about.php` | `/about/` | 固定ページ「ABOUT」作成、スラッグ `about` |
| `recruit.html` | `page-recruit.php` | `/recruit/` | 固定ページ「JOIN US」作成、スラッグ `recruit` |
| `matching-purpose.html` | `page-matching-purpose.php` | `/matching-purpose/` | 固定ページ作成 |
| `matching-issue.html` | `page-matching-issue.php` | `/matching-issue/` | 固定ページ作成 |
| `artist.html` | `archive-artist.php` | `/artist/` | CPT `artist` の自動アーカイブ |
| `artist-single.html` | `single-artist.php` | `/artist/{slug}/` | CPT `artist` の自動シングル |
| `art.html` | `archive-art.php` | `/art/` | CPT `art` の自動アーカイブ |
| `art-single.html` | `single-art.php` | `/art/{slug}/` | CPT `art` の自動シングル |
| `collector.html` | `archive-collector.php` | `/collector/` | CPT `collector` の自動アーカイブ |
| `collector-single.html` | `single-collector.php` | `/collector/{slug}/` | CPT `collector` の自動シングル |
| `news.html` | `archive-news.php` | `/news/` | CPT `news` の自動アーカイブ |
| `journal.html` | `archive-journal.php` | `/journal/` | CPT `journal` の自動アーカイブ |
| - | `single-news.php` | `/news/{slug}/` | 個別記事 |
| - | `single-journal.php` | `/journal/{slug}/` | 個別記事 |

### 共通領域

| 領域 | パーツファイル |
|---|---|
| ヘッダー | `header.php` → `template-parts/header-main.php` |
| フッター | `footer.php` → `template-parts/footer-main.php` |
| カード（アーティスト） | `template-parts/card-artist.php` |
| カード（作品） | `template-parts/card-art.php` |
| カード（企業） | `template-parts/card-collector.php` |
| 診断バナー（パーパス） | `template-parts/matching-banner-purpose.php` |
| 診断バナー（課題） | `template-parts/matching-banner-issue.php` |
| 画家募集バナー | `template-parts/for-artists-banner.php` |
| CTA（資料請求＋説明会） | `template-parts/cta-contact.php` |
| ローディング画面（プリローダー） | `header.php`（`.boa-preloader`）＋ `assets/css/preloader.css` / `assets/js/preloader.js` |
| JOURNAL インタビュー本文 | `template-parts/journal/body-interview.php` |

### ローディング画面（プリローダー）

**TOPページ（`is_front_page()`）のみ**。下層ページにも出すと遷移のたびに挟まってくどいため、
`header.php` の出力条件と `inc/enqueue.php` の読み込み条件を両方 `is_front_page()` に揃えている。
`<body>` 直後に `.boa-preloader` を出力し、`body` に `boa-loading` クラスを付けてスクロールを止める。
解除は `assets/js/preloader.js` が行い、次のうち最も早く成立した条件で消える。

1. ファーストビューの重要画像（`.hero-slide` / `.hero-bg` / `.as-hero-visual-inner` と1画面目の `<img>`）の読み込み完了
2. `window` の `load` イベント
3. 安全弁のタイムアウト（6秒）

JS 無効環境では `header.php` 内の `<noscript>` で `.boa-preloader` ごと非表示にするため、
画面が固まることはない。

### 管理画面（CSV取込）

| 画面 | ファイル |
|---|---|
| 管理メニュー「BOA CSV取込」 | `inc/csv-import/admin.php` |
| 読み込み・マッピング・取り込み処理 | `inc/csv-import/importer.php` |

`functions.php` の `is_admin()` ブロックから読み込む（フロントでは読み込まない）。
ARTIST / ART のCSVを取り込む。行の一意キーは「タイムスタンプ」列で、投稿メタ
`_bankofart_import_key` に記録するため、同じスプレッドシートを何度アップロードしても
取り込み済みの行はスキップされる。既定は「テスト実行」＋「下書きで作成」。

- 投稿タイトルは必ず**「アーティスト名」列**を使う（本名の「苗字」「名前」は使わない）
- 本名・フリガナ・連絡先・住所・性別・生年月日・振込先は `'ignored'` として**取り込まない**
  （`inc/meta-box-fields.php` の「個人情報はWPで管理しない」方針に合わせる）
- 対応先が見つからなかった列は取り込み結果に列挙し、入れ漏れに気付けるようにする
- Googleドライブの共有リンクはダウンロードURLに変換して取得し、
  中身が画像かを検証する（非公開ファイルはHTMLが返るためエラーとして報告）

### 学歴・展示歴の表記統一

`bankofart_parse_record_lines()`（`inc/helpers.php`）が、入力の書き方の揺れを表示時に
「年 ／ 月 ／ 内容」へ正規化する（DBの値は書き換えない）。吸収できる書き方は同関数の
docblock を参照。`single-artist.php` の `.as-record-list.has-year` はCSSグリッドで、
`2024` でも `2017〜2021` でも年カラムの幅が自動で揃う。同じ年が連続する行は年を空欄にする。

---

## 3. カスタム投稿タイプ（CPT）

Meta Box の MB Custom Post Type で登録。設定UIで作成しつつ、エクスポートした `inc/post-types.php` でコード管理する。

### `artist`（アーティスト）

| 設定項目 | 値 |
|---|---|
| ラベル | アーティスト |
| スラッグ | `artist` |
| アーカイブ | ✅ 有効 |
| 階層 | なし |
| サポート | title, editor, thumbnail, excerpt |
| メニュー位置 | 5 |
| メニューアイコン | `dashicons-art` |
| REST API | 有効 |

### `art`（作品）

| 設定項目 | 値 |
|---|---|
| ラベル | 作品 |
| スラッグ | `art` |
| アーカイブ | ✅ 有効 |
| サポート | title, thumbnail |
| メニュー位置 | 6 |
| メニューアイコン | `dashicons-format-image` |

### `collector`（画家応援企業）

| 設定項目 | 値 |
|---|---|
| ラベル | 画家応援企業 |
| スラッグ | `collector` |
| アーカイブ | ✅ 有効 |
| サポート | title, editor, thumbnail |
| メニュー位置 | 7 |
| メニューアイコン | `dashicons-building` |

### `news`（NEWS）

| 設定項目 | 値 |
|---|---|
| ラベル | NEWS |
| スラッグ | `news` |
| アーカイブ | ✅ 有効 |
| サポート | title, editor, thumbnail |
| メニュー位置 | 8 |

### `journal`（JOURNAL）

| 設定項目 | 値 |
|---|---|
| ラベル | JOURNAL |
| スラッグ | `journal` |
| アーカイブ | ✅ 有効 |
| サポート | title, editor, thumbnail |
| メニュー位置 | 9 |

---

## 4. カスタムタクソノミー

すべて Meta Box の MB Taxonomy で作成。

### `artist` 用

| タクソノミー | 階層 | 値 |
|---|---|---|
| `artist_status` | フラット | 公認画家 / 登録画家 |
| `artist_genre` | フラット | 具象 / 抽象 / ストリートアート / ポップアート / 日本画 / 油彩 / アクリル / ミクストメディア |

### `art` 用

| タクソノミー | 階層 | 値 |
|---|---|---|
| `art_status` | フラット | AVAILABLE / OWNED |
| `art_form` | フラット | 平面 / 立体 / 半立体 |
| `art_genre` | フラット | 抽象 / 具象 / ポップアート / ストリートアート |
| `art_technique` | フラット | 油彩 / アクリル / 水彩 / 墨 / 日本画材 / ミクストメディア |
| `art_size` | フラット | 10号 / 20号 / 30号 / その他 |
| `art_color` | フラット | 赤 / 橙 / 黄 / 緑 / 青 / 紫 / 茶 / 白 / 黒 / 金 / 銀 / その他 |

### `collector` 用

| タクソノミー | 階層 | 値 |
|---|---|---|
| `collector_issue` | フラット | モチベーション / 他社との差別化 / 取引先への印象 / 企業理念浸透 / 空間の活性化 |
| `collector_industry` | フラット | IT・ソフトウェア開発 / 金融 / 保険 / 広告代理店 / 製造業 / 小売 / コンサルティング / ... |

### `news` 用

| タクソノミー | 階層 | 値 |
|---|---|---|
| `news_category` | フラット | 受賞 / 展示 / メディア掲載 / お知らせ |

### `journal` 用

| タクソノミー | 階層 | 値 |
|---|---|---|
| `journal_category` | フラット | コラム / インタビュー |

---

## 5. Meta Boxフィールド一覧

Meta Box の MB Builder で UI から作成し、エクスポートした PHP を `inc/meta-box-fields.php` で管理する。

### artist（アーティスト）

| フィールドID | ラベル | タイプ | 公開 | 備考 |
|---|---|---|---|---|
| `artist_name_en` | 活動名（英字） | text | 🌐 | 大文字推奨 |
| `artist_catch_phrase` | キャッチフレーズ | text | 🌐 | 例：「ADRENALINE ARTIST」 |
| `artist_theme_short` | 制作テーマ（13字以内） | text | 🌐 | カード表示用 |
| `artist_theme_long` | 制作テーマ詳細 | textarea | 🌐 | 詳細ページ |
| `artist_reason` | なぜ描くか | textarea | 🌐 | |
| `artist_origin_story` | 起源の物語 | wysiwyg | 🌐 | |
| `artist_birthday` | 生年月日 | date | 🔒 | 非公開（管理用） |
| `artist_birthplace` | 出身地 | text | 🌐 | |
| `artist_education` | 学歴・経歴 | wysiwyg | 🌐 | ヒーロー右の経歴リストに「学歴・経歴 / Education」として表示 |
| `artist_solo_exhibitions` | 個展 | textarea | 🌐 | 改行区切り |
| `artist_group_exhibitions` | グループ展 | textarea | 🌐 | 改行区切り |
| `artist_media_awards` | メディア・受賞 | textarea | 🌐 | 改行区切り |
| `artist_other_activities` | その他活動 | textarea | 🌐 | 改行区切り |
| `artist_tags` | キーワードタグ | text | 🌐 | カンマ区切り例：生命エネルギー,挑戦,格闘 |
| `artist_sns_instagram` | Instagram URL | url | 🌐 | |
| `artist_sns_x` | X (Twitter) URL | url | 🌐 | |
| `artist_sns_facebook` | Facebook URL | url | 🌐 | |
| `artist_sns_youtube` | YouTube URL | url | 🌐 | |
| `artist_sns_other` | その他URL | url | 🌐 | |
| `artist_video_url` | プロフィール動画URL | url | 🌐 | YouTube埋込 |
| `artist_working_images` | 制作風景画像 | image_advanced | 🌐 | 複数可 |
| `artist_legal_name` | 本名 | text | 🔒 | 管理用 |
| `artist_legal_name_kana` | 本名フリガナ | text | 🔒 | |
| `artist_phone` | 電話番号 | text | 🔒 | |
| `artist_email` | 連絡先メール | email | 🔒 | |
| `artist_address` | 住所 | text | 🔒 | |
| `artist_bank_info` | 振込先 | group | 🔒 | サブフィールド |

🌐 = 公開項目、🔒 = 管理画面のみ閲覧（権限制限）

### art（作品）

| フィールドID | ラベル | タイプ | 備考 |
|---|---|---|---|
| `art_title_en` | 作品英題 | text | 任意 |
| `art_year` | 制作年 | number | 西暦4桁 |
| `art_medium` | 素材・支持体 | text | 例：「アクリル / キャンバス」 |
| `art_size_detail` | サイズ詳細 | text | 例：「F10（530×455mm）」 |
| `art_size_label` | 号数表記 | text | 例：「F10」「P20」 |
| `art_main_color` | メインカラー（HEX） | color | 表示用 |
| `art_price` | 価格 | number | 非公開、管理用 |
| `art_description` | 作品説明 | wysiwyg | 詳細ページ |
| `art_concept` | コンセプト | wysiwyg | 詳細ページ |
| `art_owned_by` | 所有企業 | post（select） | OWNED時のみ。collector投稿を選択 |
| `art_owned_since` | 所有開始日 | date | OWNED時 |
| `art_is_first_owner` | 初代所有か | checkbox | リセール履歴判定 |
| `art_resale_count` | リセール回数 | number | 0=新品、1=2代目所有 |
| `art_gallery` | 詳細画像 | image_advanced | 複数可 |

### collector（画家応援企業）

| フィールドID | ラベル | タイプ | 備考 |
|---|---|---|---|
| `collector_logo` | 企業ロゴ | image | |
| `collector_company_name` | 正式企業名 | text | post_titleと使い分け |
| `collector_industry_text` | 業界（表示用） | text | |
| `collector_office_location` | 設置場所 | text | 例：「執務スペース」 |
| `collector_implementation_date` | 導入時期 | date | |
| `collector_change_summary` | アートを置いた変化 | textarea | 一文 |
| `collector_interview` | インタビュー | wysiwyg | 5問1答形式 |
| `collector_image_office` | オフィス写真 | image_advanced | 複数可 |
| `collector_url` | 企業URL | url | |

### news（NEWS）

| フィールドID | ラベル | タイプ | 備考 |
|---|---|---|---|
| `news_summary` | 要約 | textarea | カード表示用 |
| `news_external_url` | 外部リンク | url | メディア掲載時 |
| `news_related_artists` | 関連アーティスト | post（multiple） | |

### journal（JOURNAL）

| フィールドID | ラベル | タイプ | 備考 |
|---|---|---|---|
| `journal_summary` | 要約 | textarea | カード表示用 |
| `journal_author` | 著者 | text | |
| `journal_reading_time` | 読了時間（分） | number | |
| `journal_main_image` | メイン写真 | single_image | カード・詳細冒頭 |
| `journal_layout` | 記事デザイン | select | `auto`(既定) / `column` / `interview` |
| `journal_sections` | 本文セクション | group（clone） | コラム形式の本文 |
| `journal_interview_intro` | 導入文（リード） | wysiwyg | インタビュー形式 |
| `journal_speaker_name` | 話し手の名前 | text | 空なら関連アーティスト1人目から補完 |
| `journal_speaker_role` | 話し手の肩書き | text | |
| `journal_speaker_icon` | 話し手のアイコン | single_image | 空なら関連アーティストの `artist_main_photo` |
| `journal_interviewer_name` | 聞き手の名前 | text | 既定 `BOA` |
| `journal_interviewer_icon` | 聞き手のアイコン | single_image | 空ならテーマ同梱の `boa-11.png` |
| `journal_interview_qa` | Q&A | group（clone） | `qa_chapter` / `qa_question` / `qa_answer` / `qa_images` |

**記事デザインの切り替え**：`journal_layout` が `auto`（既定）のとき、タクソノミー
`journal_category` が「インタビュー」なら吹き出し形式、それ以外はコラム形式で表示する。
判定は `bankofart_journal_layout()`（`inc/helpers.php`）、
描画は `template-parts/journal/body-interview.php` と `single-journal.php`。

詳細は `docs/meta-box-fields.md` を参照（別途生成）。

---

## 6. MB Relationships

`inc/relationships.php` で定義。

### 関係1: アーティスト ⇔ 作品

```php
MB_Relationships_API::register([
    'id'   => 'artist_to_art',
    'from' => [
        'object_type' => 'post',
        'post_type'   => 'artist',
        'meta_box'    => [ 'title' => 'このアーティストの作品' ],
    ],
    'to' => [
        'object_type' => 'post',
        'post_type'   => 'art',
        'meta_box'    => [ 'title' => 'このアートを制作したアーティスト' ],
    ],
]);
```

- artist 1 ⟷ 多 art
- 取得: `MB_Relationships_API::get_connected(['id' => 'artist_to_art', 'from' => $artist_id])`

### 関係2: 作品 ⇔ 所有企業（OWNED時）

```php
MB_Relationships_API::register([
    'id'   => 'art_to_owner',
    'from' => [
        'object_type' => 'post',
        'post_type'   => 'art',
        'meta_box'    => [ 'title' => '所有企業' ],
    ],
    'to' => [
        'object_type' => 'post',
        'post_type'   => 'collector',
        'meta_box'    => [ 'title' => '所有作品' ],
    ],
]);
```

- art 1 ⟷ 1 collector（現在の所有者）
- 取得: `MB_Relationships_API::get_connected(['id' => 'art_to_owner', 'from' => $art_id])`

### 関係3: NEWS ⇔ 関連アーティスト

```php
MB_Relationships_API::register([
    'id'   => 'news_to_artist',
    'from' => [
        'object_type' => 'post',
        'post_type'   => 'news',
        'meta_box'    => [ 'title' => '関連アーティスト' ],
    ],
    'to' => [
        'object_type' => 'post',
        'post_type'   => 'artist',
        'meta_box'    => [ 'title' => '関連NEWS' ],
    ],
]);
```

詳細は `docs/implementation/data-model.md` を参照（別途作成）。

---

## 7. テンプレートパーツ

### `template-parts/card-artist.php`

ARTIST一覧・single-art・診断結果などで使い回す。

```php
<?php
/**
 * Artist Card
 *
 * @var WP_Post $artist
 */
$artist_id        = $artist->ID;
$thumb            = get_the_post_thumbnail_url($artist_id, 'large');
$theme_short      = get_post_meta($artist_id, 'artist_theme_short', true);
$catch_phrase     = get_post_meta($artist_id, 'artist_catch_phrase', true);
$status_terms     = get_the_terms($artist_id, 'artist_status');
$status           = $status_terms ? $status_terms[0]->name : '';
?>
<a href="<?php echo esc_url(get_permalink($artist_id)); ?>" class="artist-card">
    <div class="artist-photo" style="background-image:url(<?php echo esc_url($thumb); ?>);"></div>
    <span class="artist-status"><?php echo esc_html($status); ?></span>
    <h3 class="artist-name"><?php echo esc_html(get_the_title($artist_id)); ?></h3>
    <p class="artist-catch"><?php echo esc_html($catch_phrase); ?></p>
    <p class="artist-theme"><?php echo esc_html($theme_short); ?></p>
</a>
```

呼び出し例:
```php
<?php
$args = [ 'post_type' => 'artist', 'posts_per_page' => 20 ];
$artists = get_posts($args);
foreach ($artists as $artist) {
    include get_theme_file_path('template-parts/card-artist.php');
}
?>
```

---

## 8. アセット読み込み戦略

### 原則

- すべての CSS / JS は `inc/enqueue.php` で `wp_enqueue_*` する
- ページ別 CSS は条件分岐で必要なページにだけ読み込む
- HTMLには `<link>` `<script>` を直書きしない

### サンプル

```php
function bankofart_enqueue_assets() {
    $theme_uri = get_stylesheet_directory_uri();
    $ver = wp_get_theme()->get('Version');

    // Google Fonts
    wp_enqueue_style(
        'bankofart-fonts',
        'https://fonts.googleapis.com/css2?family=Cormorant+SC:wght@400;500;700&family=Cinzel:wght@500;700&family=Shippori+Mincho+B1:wght@400;500;700;800&display=swap',
        [], null
    );

    // 全ページ共通CSS
    wp_enqueue_style('bankofart-tokens',     "{$theme_uri}/assets/css/tokens.css",     [], $ver);
    wp_enqueue_style('bankofart-reset',      "{$theme_uri}/assets/css/reset.css",      [], $ver);
    wp_enqueue_style('bankofart-base',       "{$theme_uri}/assets/css/base.css",       ['bankofart-tokens'], $ver);
    wp_enqueue_style('bankofart-header',     "{$theme_uri}/assets/css/header.css",     ['bankofart-base'], $ver);
    wp_enqueue_style('bankofart-footer',     "{$theme_uri}/assets/css/footer.css",     ['bankofart-base'], $ver);
    wp_enqueue_style('bankofart-components', "{$theme_uri}/assets/css/components.css", ['bankofart-base'], $ver);

    // 全ページ共通JS
    wp_enqueue_script('bankofart-main',   "{$theme_uri}/assets/js/main.js",   [], $ver, true);
    wp_enqueue_script('bankofart-header', "{$theme_uri}/assets/js/header.js", [], $ver, true);
    wp_enqueue_script('bankofart-reveal', "{$theme_uri}/assets/js/reveal.js", [], $ver, true);

    // === ページ別 ===
    if (is_front_page()) {
        wp_enqueue_style('bankofart-page-front', "{$theme_uri}/assets/css/pages/front.css", [], $ver);
    }

    if (is_page('about')) {
        wp_enqueue_style('bankofart-page-about',     "{$theme_uri}/assets/css/pages/about.css",     [], $ver);
        wp_enqueue_style('bankofart-page-simulator', "{$theme_uri}/assets/css/pages/simulator.css", [], $ver);
        wp_enqueue_script('bankofart-simulator',     "{$theme_uri}/assets/js/simulator.js",         [], $ver, true);
    }

    if (is_post_type_archive('artist') || is_singular('artist')) {
        wp_enqueue_style('bankofart-page-artist', "{$theme_uri}/assets/css/pages/artist.css", [], $ver);
        wp_enqueue_script('bankofart-filter',     "{$theme_uri}/assets/js/filter.js",         [], $ver, true);
    }

    if (is_post_type_archive('art') || is_singular('art')) {
        wp_enqueue_style('bankofart-page-art', "{$theme_uri}/assets/css/pages/art.css", [], $ver);
        wp_enqueue_script('bankofart-filter',  "{$theme_uri}/assets/js/filter.js",     [], $ver, true);
    }

    if (is_post_type_archive('collector') || is_singular('collector')) {
        wp_enqueue_style('bankofart-page-collector', "{$theme_uri}/assets/css/pages/collector.css", [], $ver);
    }

    if (is_post_type_archive('news') || is_singular('news')) {
        wp_enqueue_style('bankofart-page-news', "{$theme_uri}/assets/css/pages/news.css", [], $ver);
    }

    if (is_post_type_archive('journal') || is_singular('journal')) {
        wp_enqueue_style('bankofart-page-journal', "{$theme_uri}/assets/css/pages/journal.css", [], $ver);
    }

    if (is_page('recruit')) {
        wp_enqueue_style('bankofart-page-recruit', "{$theme_uri}/assets/css/pages/recruit.css", [], $ver);
    }

    if (is_page('matching-purpose')) {
        wp_enqueue_style('bankofart-page-matching',    "{$theme_uri}/assets/css/pages/matching.css", [], $ver);
        wp_enqueue_script('bankofart-matching-purpose', "{$theme_uri}/assets/js/matching-purpose.js", [], $ver, true);
        wp_localize_script('bankofart-matching-purpose', 'BOA_MATCHING_DATA', bankofart_get_matching_data('purpose'));
    }

    if (is_page('matching-issue')) {
        wp_enqueue_style('bankofart-page-matching',   "{$theme_uri}/assets/css/pages/matching.css", [], $ver);
        wp_enqueue_script('bankofart-matching-issue', "{$theme_uri}/assets/js/matching-issue.js",   [], $ver, true);
        wp_localize_script('bankofart-matching-issue', 'BOA_MATCHING_DATA', bankofart_get_matching_data('issue'));
    }
}
add_action('wp_enqueue_scripts', 'bankofart_enqueue_assets');
```

---

## 9. URLとパーマリンク設計

WordPress管理画面の「設定 > パーマリンク設定」で **「投稿名」** を選択。CPTスラッグはCPT登録時のもの。

### 完成形

| URL | ページ | 備考 |
|---|---|---|
| `/` | TOP | `front-page.php` |
| `/about/` | ABOUT | 固定ページ、スラッグ `about` |
| `/recruit/` | JOIN US | 固定ページ、スラッグ `recruit` |
| `/matching-purpose/` | パーパス診断 | 固定ページ |
| `/matching-issue/` | 課題逆引き診断 | 固定ページ |
| `/privacy/` | プライバシーポリシー | 固定ページ |
| `/artist/` | アーティスト一覧 | CPTアーカイブ |
| `/artist/{slug}/` | アーティスト詳細 | CPTシングル |
| `/art/` | 作品一覧 | CPTアーカイブ |
| `/art/{slug}/` | 作品詳細 | CPTシングル |
| `/collector/` | 画家応援企業一覧 | CPTアーカイブ |
| `/collector/{slug}/` | 画家応援企業詳細 | CPTシングル |
| `/news/` | NEWS一覧 | CPTアーカイブ |
| `/news/{slug}/` | NEWS詳細 | CPTシングル |
| `/journal/` | JOURNAL一覧 | CPTアーカイブ |
| `/journal/{slug}/` | JOURNAL詳細 | CPTシングル |
| `/document-request/` | 資料請求フォーム | 固定ページ |
| `/online-briefing/` | オンライン説明会予約 | 固定ページ |
| `/resale-waitlist/` | リセール待機リスト登録 | 固定ページ |

### 旧サイトからのリダイレクト

`bankof-art.com` を引き続き使うため、リダイレクトは原則不要。ただし旧サイト時代のURLパターン（例：旧ブログ等）があれば、ConoHa WING の `.htaccess` で301リダイレクトを設定。

---

## 10. データフロー図

### TOPページでの「最新NEWS 4件」表示

```
front-page.php
  └→ WP_Query( ['post_type' => 'news', 'posts_per_page' => 4] )
       └→ ループ内で template-parts/card-news.php を include
            └→ 表示
```

### ART一覧フィルター

```
archive-art.php
  ├→ ページ初回ロード時：全件表示（WP_Query）
  ├→ ユーザーがフィルタータブをクリック
  │    └→ JS（filter.js）が AJAX で /wp-admin/admin-ajax.php に POST
  │         └→ inc/ajax-handlers.php の bankofart_ajax_filter_art() が実行
  │              └→ tax_query 付きの WP_Query を実行
  │                   └→ JSON で結果を返却
  │                        └→ JS が DOM を再構築
```

### artist-single.php での作品一覧

```
single-artist.php
  └→ get_the_ID() で artist 投稿IDを取得
       └→ MB_Relationships_API::get_connected([
              'id'   => 'artist_to_art',
              'from' => $artist_id,
              'items_per_page' => -1,
          ])
          └→ 取得した art 投稿群を card-art.php でループ表示
```

### art-single.php での所有企業表示（OWNED時）

```
single-art.php
  ├→ get_the_ID() で art 投稿IDを取得
  ├→ art_status タクソノミーをチェック
  │    └→ OWNED の場合のみ:
  │         └→ MB_Relationships_API::get_connected([
  │                'id'   => 'art_to_owner',
  │                'from' => $art_id,
  │            ])
  │            └→ collector 投稿を取得して表示
  │                 └→ 「この企業の他作品」リンクを表示
```

### マッチング診断（パーパス）

```
page-matching-purpose.php
  ├→ アーティスト一覧を WP_Query で取得
  │    └→ wp_localize_script で BOA_MATCHING_DATA としてJSに渡す
  │         （タグ・テーマ・写真URL・共鳴ポイント文章）
  ├→ matching-purpose.js が読み込まれる
  ├→ ユーザーが5問に回答
  │    └→ JS内でスコアリング計算
  │         └→ 結果画面でメイン1名＋サブ3名を表示
```

---

## 11. テンプレート実装の優先順位

Claude Code で着手するときの推奨順序：

### Phase 1: 土台（最重要・最初に作る）

1. **テーマ基本ファイル**
   - `style.css`（テーマヘッダー）
   - `functions.php`（inc/* のrequire）
   - `inc/theme-setup.php`（add_theme_support等）

2. **アセット読み込み**
   - `inc/enqueue.php`
   - `assets/css/tokens.css`（CSS変数）
   - `assets/css/reset.css` / `base.css`

3. **共通ヘッダー・フッター**
   - `header.php`
   - `footer.php`
   - `template-parts/header-main.php`
   - `template-parts/footer-main.php`
   - `assets/css/header.css` / `footer.css`

4. **CPT・タクソノミー・Meta Box**
   - `inc/post-types.php`
   - `inc/taxonomies.php`
   - `inc/meta-box-fields.php`
   - `inc/relationships.php`

### Phase 2: 静的ページ（順番自由）

5. `front-page.php`（TOP）
6. `page-about.php`（ABOUT + シミュレーター）
7. `page-recruit.php`（JOIN US）
8. `archive-news.php` / `single-news.php`
9. `archive-journal.php` / `single-journal.php`

### Phase 3: 動的ページ（CPT絡み）

10. `archive-artist.php` / `single-artist.php`
11. `archive-art.php` / `single-art.php`（フィルター含む）
12. `archive-collector.php` / `single-collector.php`

### Phase 4: 診断機能

13. `page-matching-purpose.php`
14. `page-matching-issue.php`

### Phase 5: 追加システム

15. 資料請求フォーム
16. オンライン説明会予約システム
17. リセール待機リスト

---

## 12. 開発時の Tips

### モックアップとの差異対応

`mockups/` 配下のHTMLは、CSS・JSが全てインラインで書かれている。これをWPテーマ化する際の方針：

- **CSSはページ別ファイルに分離**（`assets/css/pages/*.css`）
- **JSもページ別ファイルに分離**（`assets/js/*.js`）
- **動的データ（CPT絡み）は WP_Query や Meta Box API で取得**
- **静的なロゴ・キャラクター画像は `assets/img/` に配置**

### Claude Code への指示例

```
mockups/about.html を参考に、page-about.php と関連CSSを作成してください。
ABOUT本文のセクション構造を維持しつつ、3 STEPS と Resale Service のテキストは現状のHTMLどおりで実装してください。
税制シミュレーターは `assets/js/simulator.js` として外部ファイル化し、`page-about.php` 内では `<script>` の直書きをしないでください。
```

### 動作確認スクリプト

```bash
# Local の Site Shell で実行
cd ~/Local\ Sites/bankofart-local/app/public

# WordPress のヘルスチェック
wp core verify-checksums

# 投稿数確認
wp post list --post_type=artist --format=count
wp post list --post_type=art --format=count

# キャッシュクリア
wp cache flush
```

---

## 13. 今後拡張するならここ

- 多言語化（英語版サイト）→ Polylang or WPML
- AI画像検索 / 類似作品レコメンド
- LINEログイン連携
- Stripe 連携での仮押さえ機能（リセール待機リスト発展形）
- 月次レポートPDF自動生成（Cron）

ただし**現段階ではスコープ外**。本ドキュメントに記載した機能を確実に作りきることが最優先。
