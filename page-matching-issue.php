<?php
/**
 * Template Name: MATCHING ISSUE（課題逆引き診断）
 *
 * 企業課題 × アートの課題逆引き診断（collector向け3問）。
 * Notion「課題逆引き診断 構成指示書」を正として移植。
 *
 * データ・ロジックはすべて assets/js/page-matching-issue.js（仕様3〜5章）で完結。
 * 本テンプレートは画面の器と、診断データ供給（wp_localize_script）の受け皿のみ。
 *   - 質問/効用タイプ/効用→画家タグ対応表：diagnosis-data.php（仕様準拠）
 *   - アーティスト・コレクター：WP投稿から動的取得（inc/enqueue.php で localize）
 *
 * テンプレート適用：スラッグ "matching-issue" の固定ページ、または本テンプレート選択ページ。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 課題診断はいったん非公開（bankofart_issue_matching_enabled）。
 * 入口のバナーは消してあるが、URLを直接開かれることはある。
 * 「消した」のではなく「今は出していない」状態なので、404 ではなく
 * 画家応援企業の一覧へ 302（一時的な転送）で送る。
 * 復活させたら転送も自動的に止まり、元のURLがそのまま使える。
 */
if ( ! bankofart_issue_matching_enabled() ) {
	wp_safe_redirect( get_post_type_archive_link( 'collector' ), 302 );
	exit;
}

get_header();

$briefing_url = bankofart_briefing_url(); // 結果画面「お問い合わせ」。
?>

<main id="main" class="site-main matching-issue-page">

	<!-- ━━━━━━ Screen 1: スタート ━━━━━━ -->
	<section class="match-screen is-active" id="screen-start">
		<div class="match-hero">
			<div class="match-hero-eyebrow">Matching</div>
			<h1 class="match-hero-title">MATCHING</h1>
			<p class="match-hero-ja">企業課題×アート</p>
			<p class="match-hero-lead">
				3つの質問に答えるだけで、<br>
				貴社の課題に効くアートのタイプと、<br>
				相性の良い画家・導入企業の事例をご提案します。
			</p>
			<button class="match-start-btn" id="start-btn">診断スタート</button>
		</div>
	</section>

	<!-- ━━━━━━ Screen 2: 質問 ━━━━━━ -->
	<section class="match-screen" id="screen-question">
		<div class="match-q-section">
			<div class="match-progress" id="q-progress">QUESTION 1 / 3</div>
			<div class="match-progress-bar"><div class="match-progress-fill" id="q-progress-fill" style="width:33%;"></div></div>
			<h2 class="match-q-title" id="q-title">質問テキスト</h2>
			<div class="match-options" id="q-options"></div>
		</div>
	</section>

	<!-- ━━━━━━ Screen 3: ローディング ━━━━━━ -->
	<section class="match-screen" id="screen-loading">
		<div class="match-loading">
			<div class="match-loading-spinner"></div>
			<p class="match-loading-text">貴社の課題に効くアートを探しています...</p>
		</div>
	</section>

	<!-- ━━━━━━ Screen 4: 結果 ━━━━━━ -->
	<section class="match-screen" id="screen-result">
		<div class="match-result">
			<div class="result-eyebrow">Your Match</div>
			<h2 class="result-title">貴社の課題に効くのは</h2>

			<div class="result-type" id="result-type"></div>

			<div id="art-block">
				<div class="result-art-h">Recommended Works</div>
				<div class="result-art-sub">おすすめ画家の最新作</div>
				<div class="result-art-list" id="recommended-works"></div>
			</div>

			<div class="result-sub-h">おすすめの画家</div>
			<div class="result-sub-list" id="recommended-artists"></div>

			<div id="collector-block">
				<div class="result-collector-h">Case Studies</div>
				<div class="result-collector-sub">同じ課題で導入した企業の声</div>
				<div class="result-collector-list" id="related-collectors"></div>
			</div>

			<div class="result-actions">
				<button class="is-secondary" onclick="restart()">もう一度診断する</button>
				<a href="<?php echo esc_url( $briefing_url ); ?>" target="_blank" rel="noopener" class="is-primary">お問い合わせ</a>
			</div>
		</div>
	</section>

</main>

<?php
get_footer();
