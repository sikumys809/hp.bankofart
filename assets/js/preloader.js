/*
 * preloader.js
 * ファーストビューのローディング画面の制御。
 *
 * 消えるタイミングは「一番早く成立した条件」で決まる：
 *   1. ファーストビューの重要画像（ヒーロー背景など）の読み込み完了
 *   2. window load（全アセット読み込み完了）
 *   3. 安全弁のタイムアウト（読み込みが詰まっても必ず消す）
 * いずれの場合も最低表示時間（MIN_MS）は確保し、ロゴが一瞬光って消えるのを防ぐ。
 *
 * 依存なし・バニラJS。
 */
( function () {
	'use strict';

	var MIN_MS = 700;   // 最低表示時間。
	var MAX_MS = 6000;  // 安全弁：これを過ぎたら必ず消す。

	var el = document.getElementById( 'boaPreloader' );
	if ( ! el ) { return; }

	var startedAt = Date.now();
	var done = false;

	/**
	 * ローディング画面を閉じる（最低表示時間を確保してから）。
	 */
	function hide() {
		if ( done ) { return; }
		done = true;

		var wait = Math.max( 0, MIN_MS - ( Date.now() - startedAt ) );
		setTimeout( function () {
			el.classList.add( 'is-hidden' );
			document.body.classList.remove( 'boa-loading' );
			// フェード終了後に DOM から除去（スクリーンリーダー・タブ移動から外す）。
			setTimeout( function () {
				if ( el.parentNode ) { el.parentNode.removeChild( el ); }
			}, 900 );
		}, wait );
	}

	// ── 安全弁：何があっても MAX_MS で消す。
	setTimeout( hide, MAX_MS );

	// ── 全アセット読み込み完了。
	if ( 'complete' === document.readyState ) {
		hide();
	} else {
		window.addEventListener( 'load', hide );
	}

	/**
	 * ファーストビューの重要画像URLを集める。
	 *
	 * インライン style の background-image（ヒーロースライド／詳細ページのヒーロー）と、
	 * 画面1枚目に入っている <img> を対象にする。
	 *
	 * @return {string[]} 画像URLの配列。
	 */
	function collectCriticalSources() {
		var urls = [];
		var vh = window.innerHeight || 800;

		var bgNodes = document.querySelectorAll(
			'.hero-slide, .hero-bg--video, .hero-bg, .as-hero-visual-inner, .boa-preloader-logo'
		);
		Array.prototype.forEach.call( bgNodes, function ( node ) {
			if ( 'IMG' === node.tagName ) {
				if ( node.src ) { urls.push( node.src ); }
				return;
			}
			var bg = node.style.backgroundImage || '';
			var m  = bg.match( /url\(["']?(.*?)["']?\)/ );
			if ( m && m[ 1 ] ) { urls.push( m[ 1 ] ); }
		} );

		Array.prototype.forEach.call( document.images, function ( img ) {
			if ( ! img.src ) { return; }
			var rect = img.getBoundingClientRect();
			if ( rect.top < vh ) { urls.push( img.src ); }
		} );

		return urls;
	}

	/**
	 * ファーストビューの画像が出揃った時点で閉じる。
	 */
	function watchCriticalImages() {
		var urls = collectCriticalSources();
		if ( ! urls.length ) {
			// 監視対象が無いページは DOM 構築完了で閉じてよい。
			hide();
			return;
		}

		var remaining = urls.length;

		urls.forEach( function ( url ) {
			var settled = false;
			var settle  = function () {
				if ( settled ) { return; } // load と complete の二重カウントを防ぐ。
				settled = true;
				remaining -= 1;
				if ( remaining <= 0 ) { hide(); }
			};

			var img = new Image();
			img.onload  = settle;
			img.onerror = settle;
			img.src = url;
			// キャッシュ済みで onload が発火しないブラウザ向け。
			if ( img.complete ) { settle(); }
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', watchCriticalImages );
	} else {
		watchCriticalImages();
	}

	// ブラウザバック（bfcache 復帰）では即座に閉じる。
	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			el.classList.add( 'is-hidden' );
			document.body.classList.remove( 'boa-loading' );
		}
	} );
} )();
