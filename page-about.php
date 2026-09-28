<?php
/**
 * Template Name: ABOUT（バンク・オブ・アートとは）
 *
 * ABOUT 固定ページ。mockups/about__17_.html を正として移植。
 * STEP 1：静的セクション全部 + 5 REASONS + CTA。
 *   セクション順：HERO → 数字/ロゴ → 3 STEPS → MOVIE → 償却ルール → RESALE → 5 REASONS → CTA
 * STEP 2 予定：コレクト（税制）シミュレーター .sim-section（タブ式：即時償却 / 減価償却）。
 *   今回は <!-- STEP2: コレクトシミュレーター --> プレースホルダのみ。計算ロジックは未移植。
 *
 * テンプレート適用：スラッグ "about" の固定ページ、または「ABOUT」テンプレート選択ページ。
 * アセット（画像/動画/ロゴ）はすべてテーマ同梱の実ファイルを参照（fallback 不要）。
 *
 * @package bankofart
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// アイコン・キャラクター・ロゴの URI（すべてテーマ同梱・実在確認済み）。
$icon = function ( $file ) {
	return esc_url( get_theme_file_uri( 'assets/img/icons/' . $file ) );
};
$char = function ( $file ) {
	return esc_url( get_theme_file_uri( 'assets/img/logo/' . $file ) );
};

// CLIENT COMPANIES ロゴ（マーキー）。collector-logos 配下に実在。表示順はモック準拠。
$client_logos = array(
	'AMASSY.png'   => 'Amassy',
	'B_C.png'      => 'B&middot;C Investors',
	'DES.png'      => 'DES International',
	'KOUEI.png'    => 'KOUEI',
	'MEDIAAID.png' => 'MEDIA AID',
	'MOOOVE.png'   => 'MOOOVE',
	'SBS.png'      => 'SBS GROUP',
	'STBELIEF.png' => 'ST-BELIEF',
);
?>

<main id="main" class="about-page">

	<!-- ════════ 1. ABOUT HERO（FV） ════════ -->
	<section class="about-hero">
		<div class="about-hero-inner">
			<h1 class="about-hero-title rv">ABOUT</h1>
			<p class="about-hero-ja rv d1">バンク・オブ・アートとは</p>
			<div class="about-hero-rule"></div>

			<h2 class="about-hero-sub rv d2">
				<span>節税対策</span>
				<span class="x">×</span>
				<span>画家支援</span>
			</h2>
			<div class="about-hero-lead rv d3">
				<p>バンク・オブ・アートは、<br class="br-pc">アート作品を<span class="hl">「資産」</span>として活用する、<br class="br-pc">法人・事業者向けサービス。</p>
				<p style="margin-top:18px;">減価償却・即時償却など税務上の仕組みを活用して、<br class="br-pc">本物のアートを<span class="hl">無料に近い費用</span>でコレクト可能。</p>
			</div>
		</div>
	</section>

	<!-- ════════ 1-2. 数字＆クライアントロゴ ════════ -->
	<section class="about-second">
		<div class="about-hero-inner">
			<!-- 統計（アイコン付き） ※数字は「外観 > カスタマイズ > サイト数値（実績）」で更新可能。 -->
			<div class="stats rv">
				<div class="stat-cell">
					<div class="stat-icon"><img src="<?php echo $icon( 'BOAicon-18.png' ); ?>" alt=""></div>
					<div class="stat-num"><span class="count-up" data-target="<?php echo esc_attr( bankofart_stat( 'clients' ) ); ?>">0</span><span class="unit">ヶ所</span></div>
					<div class="stat-label">導入先</div>
					<div class="stat-eng">CLIENTS</div>
				</div>
				<div class="stat-cell">
					<div class="stat-icon"><img src="<?php echo $icon( 'BOAicon-17.png' ); ?>" alt=""></div>
					<div class="stat-num"><span class="count-up" data-target="<?php echo esc_attr( bankofart_stat( 'artists' ) ); ?>">0</span><span class="unit">名</span></div>
					<div class="stat-label">所属画家</div>
					<div class="stat-eng">ARTISTS</div>
				</div>
				<div class="stat-cell">
					<div class="stat-icon"><img src="<?php echo $icon( 'BOAicon-16.png' ); ?>" alt=""></div>
					<div class="stat-num"><span class="count-up" data-target="<?php echo esc_attr( bankofart_stat( 'artworks' ) ); ?>">0</span><span class="unit">枚</span></div>
					<div class="stat-label">取扱作品数</div>
					<div class="stat-eng">ARTWORKS</div>
				</div>
			</div>

			<!-- 導入会社ロゴ（マーキー）。前半8社 + ループ用に同一8社を複製。 -->
			<div class="logo-marquee rv">
				<div class="logo-marquee-label">CLIENT COMPANIES</div>
				<div class="logo-marquee-track">
					<div class="logo-marquee-row">
						<?php
						// 2 周ぶん（モック同様、シームレスループ用に複製）。
						for ( $pass = 0; $pass < 2; $pass++ ) :
							foreach ( $client_logos as $file => $label ) :
								$alt = ( 0 === $pass ) ? $label : '';
								?>
								<div class="logo-item"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/collector-logos/' . $file ) ); ?>" alt="<?php echo esc_attr( $alt ); ?>"></div>
								<?php
							endforeach;
						endfor;
						?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<div class="section-rule"><hr></div>

	<!-- ════════ 2. 3 STEPS（サイクル型） ════════ -->
	<section class="section" id="steps">
		<div class="section-inner">
			<div class="head-block">
				<h2 class="h-en rv">3 STEPS</h2>
				<p class="h-ja rv d1">3つのしくみ</p>
				<div class="h-rule"></div>
			</div>

			<div class="cycle rv">
				<div class="cycle-stage">
					<!-- 01 コレクト（緑） -->
					<div class="cycle-node node-left">
						<div class="cycle-node-char"><img src="<?php echo $char( 'char-02.png' ); ?>" alt=""></div>
						<div class="cycle-node-num">01</div>
						<div class="cycle-node-title">コレクト</div>
						<div class="cycle-node-en">COLLECT</div>
						<p class="cycle-node-text">専属コレクターが<br><span class="hl">全国各地の若手画家</span>と直接契約。<br>独自の評価基準を満たした<br>原画を厳選してご提供します。</p>
					</div>

					<!-- 02 減価償却（黒） -->
					<div class="cycle-node node-top">
						<div class="cycle-node-char"><img src="<?php echo $char( 'char-03.png' ); ?>" alt=""></div>
						<div class="cycle-node-num">02</div>
						<div class="cycle-node-title">減価償却</div>
						<div class="cycle-node-en">DEPRECIATION</div>
						<p class="cycle-node-text">取り扱う絵画は原則として<br><span class="hl">減価償却資産の美術工芸品</span>。<br>1年〜8年で経費計上を<br>行うことが可能です。</p>
					</div>

					<!-- 03 リセール（緑） -->
					<div class="cycle-node node-right">
						<div class="cycle-node-char"><img src="<?php echo $char( 'char-01.png' ); ?>" alt=""></div>
						<div class="cycle-node-num">03</div>
						<div class="cycle-node-title">リセール</div>
						<div class="cycle-node-en">RESALE</div>
						<p class="cycle-node-text">当方より販売した作品は<br><span class="hl">販売価格の30〜70%</span>の<br>査定評価にて買取いたします。</p>
					</div>
				</div>
			</div>
		</div>
	</section>

	<div class="section-rule"><hr></div>

	<!-- ════════ 3. MOVIE ════════ -->
	<section class="section section-dark" id="movie">
		<div class="section-inner-narrow">
			<div class="head-block">
				<h2 class="h-en rv">WATCH THE STORY</h2>
				<p class="h-ja rv d1">バンク・オブ・アートを動画で知る</p>
				<div class="h-rule"></div>
			</div>

			<div class="movie-frame rv">
				<iframe src="https://www.youtube.com/embed/wTWIQXGHlfQ" title="What is BANK OF ART?" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
			</div>
			<div class="movie-caption rv d1">BANK OF ART — OFFICIAL MOVIE</div>

			<div class="movie-cta rv d2">
				<a href="<?php echo esc_url( bankofart_document_request_url() ); ?>" class="btn-w" target="_blank" rel="noopener">資料請求</a>
				<a href="<?php echo esc_url( bankofart_briefing_url() ); ?>" class="btn-o" target="_blank" rel="noopener">オンライン説明会</a>
			</div>
		</div>
	</section>

	<div class="section-rule"><hr></div>

	<!-- ════════ 4. 絵画償却制度のルール（棒グラフ風） ════════ -->
	<section class="section" id="dep-rules">
		<div class="section-inner">
			<div class="head-block">
				<h2 class="h-en rv">DEPRECIATION RULES</h2>
				<p class="h-ja rv d1">絵画償却制度のルール</p>
				<div class="h-rule"></div>
			</div>

			<p class="dep-intro rv d1">
				取得価額が1点100万円未満の美術品等は<br class="br-sp">原則として減価償却資産に該当します。<br class="br-pc"><br class="br-sp">税制改正により少額減価償却資産の特例上限が30万円→40万円に拡大されました。
			</p>

			<div class="dep-bar rv">
				<!-- 棒グラフ -->
				<div class="dep-bar-graph">
					<div class="dep-zone-over">100万円以上は対象外</div>

					<div class="dep-line-100"></div>

					<!-- 100万〜40万：減価償却ゾーン -->
					<div class="dep-zone-shoukyaku">
						<div class="dep-zone-tag">RULE 01</div>
						<div class="dep-zone-title">減価償却</div>
						<div class="dep-zone-cond">絵画1点につき<br>100万円未満が対象</div>
					</div>

					<div class="dep-line-30"></div>

					<!-- 40万以下：即時償却ゾーン -->
					<div class="dep-zone-sokuji">
						<div class="dep-zone-tag">RULE 02</div>
						<div class="dep-zone-title">即時償却</div>
						<div class="dep-zone-cond">中小企業・個人事業主は<br>40万円未満で即時償却</div>
					</div>

					<!-- Y軸ラベル -->
					<div class="dep-axis-label top">100<span class="yen">万円</span></div>
					<div class="dep-axis-label mid">40<span class="yen">万円</span></div>
					<div class="dep-axis-label bot">0<span class="yen">円</span></div>
				</div>

				<!-- 右サイド説明 -->
				<div class="dep-side">
					<div class="dep-side-card">
						<div class="dep-side-tag">RULE 01</div>
						<div class="dep-side-headline">減価償却</div>
						<p class="dep-side-text">絵画1点につき100万円未満であれば、<br>減価償却資産として1年〜8年で<br>経費計上が可能。</p>
					</div>
					<div class="dep-side-card primary">
						<div class="dep-side-tag">RULE 02</div>
						<div class="dep-side-headline">即時償却</div>
						<p class="dep-side-text">中小企業・個人事業主は<br>40万円未満で取得価額の全額を、<br>その年の経費にできる特例があります。</p>
					</div>
				</div>
			</div>

			<p class="dep-source rv d1">
				引用：<a href="https://www.nta.go.jp/law/joho-zeikaishaku/hojin/bijutsuhin_FAQ/index.htm" target="_blank" rel="noopener">国税庁HP明記事項</a><br>
				※100％保証するものではありません。使用目的によっては否認されるケースもあります。<br class="br-pc">顧問税理士等にご相談ください。
			</p>
		</div>
	</section>

	<div class="section-rule"><hr></div>

	<!-- ════════ 5. RESALE SERVICE ════════ -->
	<section class="section" id="resale">
		<div class="section-inner">
			<div class="head-block">
				<h2 class="h-en rv">RESALE SERVICE</h2>
				<p class="h-ja rv d1">リセールサービス</p>
				<div class="h-rule"></div>
			</div>

			<p class="lead rv">
				バンク・オブ・アートより販売した作品は<span class="hl">販売価格30〜70%</span>の査定評価にて買取を行います。
			</p>

			<div class="resale-bars rv">
				<!-- 斜め破線：高比率の右上 ⇄ 標準比率の左上 を結ぶ（JS で座標計算） -->
				<svg class="resale-bars-diff" aria-hidden="true">
					<line id="resaleDiffLine" stroke="#01ae84" stroke-width="2" stroke-dasharray="6 6"/>
				</svg>

				<!-- 左：組換有（高比率＝高いバー） -->
				<div class="resale-bar-col with">
					<div class="resale-bar-label">
						<div class="resale-bar-tag">RECOMMENDED</div>
						<div class="resale-bar-title">売却と同時に<br>新たな絵画を<br>組み替える場合</div>
					</div>
					<div class="resale-bar-wrap">
						<div class="resale-bar bar-high">
							<div class="resale-bar-rate">高比率</div>
							<div class="resale-bar-rate-sub">で買取</div>
						</div>
					</div>
				</div>

				<!-- 右：組換無（標準比率＝低いバー） -->
				<div class="resale-bar-col without">
					<div class="resale-bar-label">
						<div class="resale-bar-tag">STANDARD</div>
						<div class="resale-bar-title">新たな絵画を<br>組み替えない場合</div>
					</div>
					<div class="resale-bar-wrap">
						<div class="resale-bar bar-low">
							<div class="resale-bar-rate">標準比率</div>
							<div class="resale-bar-rate-sub">で買取</div>
						</div>
					</div>
				</div>
			</div>

			<p class="dep-source rv d2">
				※バンク・オブ・アートブランドの絵画のみリセールサービス対象。<br class="br-pc">当方の定める保管展示方法を守り、額装管理している事がリセールサービスの条件です。
			</p>
		</div>
	</section>

	<div class="section-rule"><hr></div>

	<!-- ════════ コレクトシミュレーター（タブ式：即時償却 / 減価償却）※計算ロジックは mockup から verbatim 移植 ════════ -->
	<section class="sim-section" id="collect-simulator">
		<div class="sim-section-inner">
			<div class="sim-eyebrow rv">Simulator</div>
			<h2 class="sim-title rv d1">コレクトシミュレーター</h2>
			<p class="sim-lead rv d2">
				バンク・オブ・アートでアートを資産として持つことで<br class="br-sp">生まれる節税効果と回収を、<br class="br-pc"><br class="br-sp">ご自身の条件で試算いただけます。<br class="br-pc"><br class="br-sp"><br class="br-sp">
				即時償却タイプは「実質コスト」を、<br class="br-pc"><br class="br-sp">減価償却タイプは「年ごとの経費計上額・期末帳簿価額」を確認いただけます。
			</p>

			<div class="sim-tabs rv d3">
				<button class="sim-tab is-active" data-tab="immediate">即時償却シミュレーター</button>
				<button class="sim-tab" data-tab="depreciation">減価償却シミュレーター</button>
			</div>

			<!-- ━━━━━━ パネル1：即時償却シミュレーター ━━━━━━ -->
			<div class="sim-panel is-active" data-panel="immediate">
				<div class="sim-grid">
					<div class="sim-form">
						<div class="sim-form-h">条件を入力</div>

						<div class="sim-field">
							<label class="sim-field-label">事業形態</label>
							<select class="sim-select" id="cs-entity">
								<option value="corp">法人</option>
								<option value="sole">個人事業主</option>
							</select>
						</div>

						<div class="sim-field" id="cs-corp-region">
							<label class="sim-field-label">地域・規模</label>
							<select class="sim-select" id="cs-corpRate">
								<option value="0.3459">東京都・中小法人（所得年800万円超）</option>
								<option value="0.3358">標準税率地域・中小法人（所得年800万円超）</option>
								<option value="0.2559">中小法人（所得年800万円以下が中心）</option>
							</select>
							<div class="sim-field-hint">所在自治体・資本金・所得規模により実効税率は変動します。プリセットは参考値です。</div>
						</div>

						<div class="sim-field" id="cs-sole-region" style="display:none;">
							<label class="sim-field-label">地域</label>
							<select class="sim-select" id="cs-soleRate">
								<option value="0.10">標準地域（住民税10%）</option>
								<option value="0.10025">超過課税地域（10.025%）</option>
							</select>
						</div>

						<div class="sim-field" id="cs-sole-income" style="display:none;">
							<label class="sim-field-label">年間の課税所得（アート取得前）</label>
							<input type="number" class="sim-input" id="cs-income" value="9000000" min="1000000" max="50000000" step="100000">
							<div class="sim-field-hint">売上から経費・各種控除を引いた後の金額です。アート取得による経費はこの金額から差し引いて試算します。</div>
						</div>

						<div class="sim-field" id="cs-sole-tax-toggle" style="display:none;">
							<div class="sim-toggle-wrap">
								<div class="sim-toggle" id="cs-bizTaxToggle"></div>
								<span class="sim-toggle-label">個人事業税を計算に含める</span>
							</div>
							<div class="sim-field-hint">ONにすると個人事業税の節税分も加算します。業種により非課税の場合もあります。正確な扱いは顧問税理士にご確認ください。</div>
						</div>

						<div class="sim-field" id="cs-sole-biztype" style="display:none;">
							<label class="sim-field-label">業種（事業税率）</label>
							<select class="sim-select" id="cs-bizRate">
								<option value="0.05">第3種事業（デザイン業・コンサル 等）5%</option>
								<option value="0.05">第1種事業（物品販売・不動産業 等）5%</option>
								<option value="0.03">一部の第3種事業（あんま・マッサージ 等）3%</option>
							</select>
						</div>

						<div class="sim-field">
							<label class="sim-field-label">リセールサービス比率</label>
							<div class="sim-slider-wrap">
								<input type="range" class="sim-slider" id="cs-resale" min="30" max="70" step="5" value="70">
								<span class="sim-slider-value" id="cs-resaleVal">70%</span>
							</div>
						</div>

						<div class="sim-field">
							<label class="sim-field-label">コレクト点数</label>
							<div class="sim-slider-wrap">
								<input type="range" class="sim-slider" id="cs-qty" min="1" max="20" step="1" value="7">
								<span class="sim-slider-value" id="cs-qtyVal">7点</span>
							</div>
						</div>

						<button class="sim-btn" id="cs-calc">計算する</button>
					</div>

					<div class="sim-result" id="cs-result">
						<div class="sim-result-h">試算結果</div>
						<div class="sim-result-empty">条件を入力して<br>「計算する」を押してください</div>
					</div>
				</div>
			</div>

			<!-- ━━━━━━ パネル2：減価償却シミュレーター ━━━━━━ -->
			<div class="sim-panel" data-panel="depreciation">
				<div class="sim-grid">
					<div class="sim-form">
						<div class="sim-form-h">条件を入力</div>

						<div class="sim-field">
							<label class="sim-field-label">作品タイプ</label>
							<select class="sim-select" id="ds-artType">
								<option value="390000">即時償却タイプ（10号）</option>
								<option value="700000">減価償却タイプ（20号）</option>
								<option value="990000">減価償却タイプ（30号）</option>
							</select>
							<div class="sim-field-hint">即時償却タイプは取得年に全額を経費計上できます（中小企業者等・青色申告が対象）。減価償却タイプは法定耐用年数8年で分割して経費計上します。</div>
						</div>

						<div class="sim-field">
							<label class="sim-field-label">事業形態</label>
							<select class="sim-select" id="ds-entity">
								<option value="corp">法人</option>
								<option value="sole">個人事業主</option>
							</select>
						</div>

						<div class="sim-field" id="ds-corp-region">
							<label class="sim-field-label">地域・規模</label>
							<select class="sim-select" id="ds-corpRate">
								<option value="0.3459">東京都・中小法人（所得年800万円超）</option>
								<option value="0.3358">標準税率地域・中小法人（所得年800万円超）</option>
								<option value="0.2559">中小法人（所得年800万円以下が中心）</option>
							</select>
						</div>

						<div class="sim-field" id="ds-sole-region" style="display:none;">
							<label class="sim-field-label">地域</label>
							<select class="sim-select" id="ds-soleRate">
								<option value="0.10">標準地域（住民税10%）</option>
								<option value="0.10025">超過課税地域（10.025%）</option>
							</select>
						</div>

						<div class="sim-field" id="ds-sole-income" style="display:none;">
							<label class="sim-field-label">年間の課税所得（アート取得前）</label>
							<input type="number" class="sim-input" id="ds-income" value="9000000" min="1000000" max="50000000" step="100000">
							<div class="sim-field-hint">各年とも同一の課税所得を前提とした簡易計算です。</div>
						</div>

						<div class="sim-field">
							<label class="sim-field-label">コレクト点数</label>
							<div class="sim-slider-wrap">
								<input type="range" class="sim-slider" id="ds-qty" min="1" max="20" step="1" value="1">
								<span class="sim-slider-value" id="ds-qtyVal">1点</span>
							</div>
						</div>

						<button class="sim-btn" id="ds-calc">計算する</button>
					</div>

					<div class="sim-result" id="ds-result">
						<div class="sim-result-h">試算結果</div>
						<div class="sim-result-empty">条件を入力して<br>「計算する」を押してください</div>
					</div>
				</div>
			</div>

			<!-- ARTを探すCTAボタン -->
			<div class="sim-cta-wrap rv">
				<a href="<?php echo esc_url( bankofart_collection_arts_url() ); ?>" class="sim-cta-btn" target="_blank" rel="noopener">ARTを探す</a>
			</div>

			<!-- 免責 -->
			<div class="sim-disclaimer">
				<div class="sim-disclaimer-h">免責・注記</div>
				<ul>
					<li>本シミュレーションは概算であり、節税効果・リセール額を100%保証するものではありません。使用目的等により税務上否認されるケースもあります。実際の適用は必ず顧問税理士にご相談ください。</li>
					<li>即時償却（少額減価償却資産の特例）は、資本金1億円以下・青色申告・常時使用従業員数400人以下の中小企業者等が対象です。1点40万円未満（2026年4月1日以降取得分。それ以前は30万円未満）・年間合計300万円までが上限です。本制度は2029年3月31日まで適用されます。</li>
					<li>法人実効税率は所在自治体・資本金・所得規模により変動します。表示のプリセットは参考値です。</li>
					<li>個人事業主の試算は所得税・復興特別所得税・住民税所得割を対象とします。個人事業税は即時償却シミュレーターで「計算に含める」を選択した場合のみ加算します。減価償却シミュレーターでは個人事業税は試算対象に含みません。</li>
					<li>リセールサービスによる売却収益は将来の売却時点で発生するものであり、税金圧縮（取得年）とは発生時期が異なります。本試算は累計の損益を示すものです。</li>
					<li>出典：国税庁「美術品等についての減価償却資産の判定に関するFAQ」</li>
				</ul>
			</div>
		</div>
	</section>

	<!-- ════════ 6. 5 REASONS ════════ -->
	<section class="section section-dark" id="reasons">
		<div class="section-inner">
			<div class="head-block">
				<h2 class="h-en rv">5 REASONS</h2>
				<p class="h-ja rv d1">バンク・オブ・アートが選ばれる、5つの理由。</p>
				<div class="h-rule"></div>
			</div>

			<p class="reasons-tagline rv">
				節税<span class="x">×</span>画家支援<br class="br-sp"><span class="x">×</span>空間演出
			</p>

			<div class="reasons-cards rv d1">
				<!-- 01 -->
				<div class="reason-card">
					<div class="reason-icon"><img src="<?php echo $icon( 'BOAicon-07.png' ); ?>" alt=""></div>
					<h3 class="reason-title">即時償却 ＆<br>減価償却</h3>
					<p class="reason-text">税負担の<br>軽減につながる</p>
				</div>
				<!-- 02 -->
				<div class="reason-card">
					<div class="reason-icon"><img src="<?php echo $icon( 'BOAicon-08.png' ); ?>" alt=""></div>
					<h3 class="reason-title">最大リセール70%</h3>
					<p class="reason-text">販売価格の<br>30〜70%で<br>買取保証</p>
				</div>
				<!-- 03 -->
				<div class="reason-card">
					<div class="reason-icon"><img src="<?php echo $icon( 'BOAicon-09.png' ); ?>" alt=""></div>
					<h3 class="reason-title">価値上昇の可能性</h3>
					<p class="reason-text">作家価値の上昇で<br>取得額を超える<br>買取の可能性</p>
				</div>
				<!-- 04 -->
				<div class="reason-card">
					<div class="reason-icon"><img src="<?php echo $icon( 'BOAicon-16.png' ); ?>" alt=""></div>
					<h3 class="reason-title">空間演出 ＆<br>ブランディング</h3>
					<p class="reason-text">企業イメージ向上と<br>空間価値の演出</p>
				</div>
				<!-- 05 -->
				<div class="reason-card">
					<div class="reason-icon"><img src="<?php echo $icon( 'BOAicon-15.png' ); ?>" alt=""></div>
					<h3 class="reason-title">委託から買取へ</h3>
					<p class="reason-text">買取で画家を<br>確実に支援</p>
				</div>
			</div>
		</div>
	</section>

	<!-- ════════ 7. CTA（CONTACT）※共通コンポーネント ════════ -->
	<?php get_template_part( 'template-parts/sections/section-cta' ); ?>

</main>

<?php
get_footer();
