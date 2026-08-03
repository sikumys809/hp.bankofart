/*
 * single-detail.js
 * single-artist / single-art 共通のインタラクション。
 *   1) ヒーロー画像：サムネクリックでメイン画像を切り替え（as- / aw- 両対応）
 *   2) リビール（.rv）：スクロールで表示。JS有効時のみ body.reveal-ready を付与
 *   3) 画像拡大ライトボックス（.boa-zoomable）
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		/* ---- 1) ヒーロー画像切り替え ---- */
		var heroMain = document.querySelector( '#asHeroMain, #awHeroMain, #csVisualMain' );
		var thumbs   = document.querySelectorAll( '.as-hero-thumb, .aw-hero-thumb, .cs-visual-thumb' );

		if ( heroMain && thumbs.length ) {
			thumbs.forEach( function ( t ) {
				t.addEventListener( 'click', function () {
					var bg = t.dataset.bg;
					if ( bg ) {
						heroMain.style.backgroundImage = "url('" + bg + "')";
					}
					thumbs.forEach( function ( x ) {
						x.classList.remove( 'is-active' );
					} );
					t.classList.add( 'is-active' );
				} );
			} );
		}

		/* ---- 2) リビール ---- */
		var revealEls = document.querySelectorAll( '.rv' );
		if ( revealEls.length && 'IntersectionObserver' in window ) {
			document.body.classList.add( 'reveal-ready' );
			var obs = new IntersectionObserver(
				function ( entries ) {
					entries.forEach( function ( e ) {
						if ( e.isIntersecting ) {
							e.target.classList.add( 'on' );
						}
					} );
				},
				{ threshold: 0.1, rootMargin: '0px 0px -50px 0px' }
			);
			revealEls.forEach( function ( el ) {
				obs.observe( el );
			} );
		}

		/* ---- 3) 画像拡大ライトボックス ---- */
		var box      = document.getElementById( 'boaLightbox' );
		var boxImg   = document.getElementById( 'boaLightboxImg' );
		var closeBtn = document.getElementById( 'boaLightboxClose' );

		if ( box && boxImg ) {
			var openZoom = function ( el ) {
				var inner = el.querySelector( '[style*="background-image"]' );
				if ( ! inner ) {
					return;
				}
				var bg = inner.style.backgroundImage;
				if ( ! bg || 'none' === bg ) {
					return;
				}
				var rect  = el.getBoundingClientRect();
				var ratio = rect.height ? rect.width / rect.height : 1;
				var maxW  = Math.min( window.innerWidth * 0.88, 1000 );
				var maxH  = window.innerHeight * 0.84;
				var w = maxW;
				var h = maxW / ratio;
				if ( h > maxH ) {
					h = maxH;
					w = maxH * ratio;
				}
				boxImg.style.width = w + 'px';
				boxImg.style.height = h + 'px';
				boxImg.style.backgroundImage = bg;
				box.classList.add( 'is-open' );
				box.setAttribute( 'aria-hidden', 'false' );
				document.body.style.overflow = 'hidden';
			};

			var closeZoom = function () {
				box.classList.remove( 'is-open' );
				box.setAttribute( 'aria-hidden', 'true' );
				document.body.style.overflow = '';
			};

			document.querySelectorAll( '.boa-zoomable' ).forEach( function ( el ) {
				el.addEventListener( 'click', function () {
					openZoom( el );
				} );
			} );

			if ( closeBtn ) {
				closeBtn.addEventListener( 'click', closeZoom );
			}
			box.addEventListener( 'click', function ( e ) {
				if ( e.target === box ) {
					closeZoom();
				}
			} );
			document.addEventListener( 'keydown', function ( e ) {
				if ( 'Escape' === e.key ) {
					closeZoom();
				}
			} );
		}

		/* ===== 作品一覧：初期3件（モバイル2件）＋「もっと見る」で追加 =====
		 * 作品ページの MORE WORKS と、アーティストページの WORKS の両方で使う。 */
		function setupWorksLoadMore( gridId, btnId, wrapId ) {
			var grid = document.getElementById( gridId );
			var btn  = document.getElementById( btnId );
			var wrap = document.getElementById( wrapId );
			if ( ! grid || ! btn ) { return; }

			var cards = Array.prototype.slice.call( grid.querySelectorAll( '.art-card' ) );
			if ( cards.length <= 3 ) { return; }

			var mq = window.matchMedia( '(max-width: 760px)' );
			var shown = 0;

			function step() { return mq.matches ? 2 : 3; }

			function render() {
				cards.forEach( function ( el, i ) {
					el.style.display = i < shown ? '' : 'none';
				} );
				var hasMore = cards.length > shown;
				if ( wrap ) { wrap.style.display = hasMore ? '' : 'none'; }
			}

			function reset() {
				shown = Math.min( step(), cards.length );
				render();
			}

			btn.addEventListener( 'click', function () {
				shown = Math.min( shown + step(), cards.length );
				render();
			} );

			// 画面幅が変わったら初期件数を取り直す。
			if ( mq.addEventListener ) {
				mq.addEventListener( 'change', reset );
			} else if ( mq.addListener ) {
				mq.addListener( reset ); // 旧Safari互換.
			}

			reset();
		}

		setupWorksLoadMore( 'awOtherGrid', 'awOtherMoreBtn', 'awOtherMore' ); // 作品ページ：MORE WORKS
		setupWorksLoadMore( 'asWorksGrid', 'asWorksMoreBtn', 'asWorksMore' ); // アーティストページ：WORKS
	} );
} )();
