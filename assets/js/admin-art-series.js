/*
 * admin-art-series.js
 * 作品編集画面：シリーズを選ぶと共通項目を自動入力する（管理画面専用）。
 *
 *   - 既定は「空欄のみ」埋める。入力済みの内容は勝手に消さない。
 *   - まとめて置き換えたいときは、表示されるボタンから明示的に上書きする。
 *   - ジャンル／技法／形態は Meta Box の select2 のため、値を入れたあと
 *     jQuery の change を発火させて表示を更新する（管理画面限定でjQuery使用）。
 *
 * サーバー側は inc/art-series/admin.php。
 */
( function ( $ ) {
	'use strict';

	if ( 'undefined' === typeof BOA_ART_SERIES ) { return; }

	var CFG    = BOA_ART_SERIES;
	var TEXT   = [ 'art_title_en', 'art_medium', 'art_series_name' ];
	var SELECT = [ 'art_genre_picker', 'art_technique_picker', 'art_form_picker' ];
	var RICH   = [ 'art_description', 'art_concept' ];

	var seriesSelect = document.getElementById( 'art_series_picker' );
	if ( ! seriesSelect ) { return; }

	var lastTemplate = null;
	var notice       = null;

	/**
	 * 通知エリアを取得（無ければシリーズ選択欄の直後に作る）。
	 *
	 * @return {HTMLElement}
	 */
	function getNotice() {
		if ( notice && notice.parentNode ) { return notice; }
		notice = document.createElement( 'div' );
		notice.className = 'boa-series-notice';
		notice.style.cssText = 'margin:8px 0 0;padding:10px 12px;border-left:4px solid #01ae84;background:#f2faf8;font-size:13px;line-height:1.7;';
		seriesSelect.parentNode.insertBefore( notice, seriesSelect.nextSibling );
		return notice;
	}

	/**
	 * 通知を表示する。
	 *
	 * @param {string}  html      表示内容（HTML）。
	 * @param {boolean} showBtn   上書きボタンを出すか。
	 */
	function showNotice( html, showBtn ) {
		var box = getNotice();
		box.innerHTML = html;

		if ( ! showBtn ) { return; }

		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'button';
		btn.style.marginTop = '8px';
		btn.textContent = CFG.labels.overwrite;
		btn.addEventListener( 'click', function () {
			if ( ! window.confirm( CFG.labels.confirm ) ) { return; }
			apply( lastTemplate, true );
		} );
		box.appendChild( document.createElement( 'br' ) );
		box.appendChild( btn );
	}

	/**
	 * 入力欄が空かどうか。
	 *
	 * @param {string} id フィールドID。
	 * @return {boolean}
	 */
	function isEmpty( id ) {
		if ( 'post_title' === id ) {
			var t = document.getElementById( 'title' );
			return ! t || '' === t.value.trim();
		}

		if ( RICH.indexOf( id ) !== -1 ) {
			return '' === getRich( id ).trim();
		}

		if ( SELECT.indexOf( id ) !== -1 ) {
			var sel = document.getElementById( id );
			if ( ! sel ) { return false; }
			return 0 === getSelected( sel ).length;
		}

		var el = document.getElementById( id );
		return ! el || '' === el.value.trim();
	}

	/**
	 * select の選択値を配列で返す。
	 *
	 * @param {HTMLSelectElement} sel セレクト要素。
	 * @return {string[]}
	 */
	function getSelected( sel ) {
		var out = [];
		Array.prototype.forEach.call( sel.options, function ( o ) {
			if ( o.selected && '' !== o.value ) { out.push( o.value ); }
		} );
		return out;
	}

	/**
	 * wysiwyg の現在値を取得（TinyMCE 起動中はそちらを優先）。
	 *
	 * @param {string} id フィールドID。
	 * @return {string}
	 */
	function getRich( id ) {
		if ( window.tinymce ) {
			var ed = window.tinymce.get( id );
			if ( ed && ! ed.isHidden() ) { return ed.getContent(); }
		}
		var ta = document.getElementById( id );
		return ta ? ta.value : '';
	}

	/**
	 * wysiwyg に値を入れる（TinyMCE とテキストエリアの両方を更新）。
	 *
	 * @param {string} id    フィールドID。
	 * @param {string} value 値。
	 */
	function setRich( id, value ) {
		var ta = document.getElementById( id );
		if ( ta ) { ta.value = value; }

		if ( window.tinymce ) {
			var ed = window.tinymce.get( id );
			if ( ed ) { ed.setContent( value ); }
		}
	}

	/**
	 * 取得したシリーズ内容をフォームへ反映する。
	 *
	 * @param {Object}  tpl       サーバーから受け取った値。
	 * @param {boolean} overwrite 入力済みも上書きするか。
	 */
	function apply( tpl, overwrite ) {
		if ( ! tpl ) { return; }

		var filled = [];

		// テキスト系。
		TEXT.concat( [ 'post_title' ] ).forEach( function ( id ) {
			var value = tpl[ id ];
			if ( ! value ) { return; }
			if ( ! overwrite && ! isEmpty( id ) ) { return; }

			var el = ( 'post_title' === id ) ? document.getElementById( 'title' ) : document.getElementById( id );
			if ( ! el ) { return; }
			el.value = value;
			// タイトルは placeholder（「タイトルを追加」）の表示制御があるため通知する。
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			filled.push( CFG.fields[ id ] || id );
		} );

		// wysiwyg。
		RICH.forEach( function ( id ) {
			var value = tpl[ id ];
			if ( ! value ) { return; }
			if ( ! overwrite && ! isEmpty( id ) ) { return; }
			setRich( id, value );
			filled.push( CFG.fields[ id ] || id );
		} );

		// select2（ジャンル・技法・形態）。
		SELECT.forEach( function ( id ) {
			var ids = tpl[ id ];
			if ( ! ids || ! ids.length ) { return; }
			if ( ! overwrite && ! isEmpty( id ) ) { return; }

			var sel = document.getElementById( id );
			if ( ! sel ) { return; }

			var wanted = ids.map( String );
			var hit    = false;
			Array.prototype.forEach.call( sel.options, function ( o ) {
				var on = wanted.indexOf( String( o.value ) ) !== -1;
				if ( sel.multiple ) {
					o.selected = on;
				} else if ( on ) {
					o.selected = true;
				}
				if ( on ) { hit = true; }
			} );

			if ( ! hit ) { return; }
			// select2 は DOM を直接書き換えても追随しないため change を発火させる。
			$( sel ).trigger( 'change' );
			filled.push( CFG.fields[ id ] || id );
		} );

		var name = seriesSelect.options[ seriesSelect.selectedIndex ]
			? seriesSelect.options[ seriesSelect.selectedIndex ].text
			: '';

		if ( ! filled.length ) {
			showNotice( CFG.labels.allFilled, true );
			return;
		}

		showNotice(
			CFG.labels.filled.replace( '%s', name ) + '<br><strong>' + filled.join( '、' ) + '</strong>',
			! overwrite
		);
	}

	/**
	 * シリーズが選ばれたら内容を取りに行く。
	 */
	seriesSelect.addEventListener( 'change', function () {
		var termId = seriesSelect.value;
		if ( ! termId ) {
			if ( notice && notice.parentNode ) { notice.parentNode.removeChild( notice ); notice = null; }
			return;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'bankofart_art_series' );
		body.append( 'nonce', CFG.nonce );
		body.append( 'term_id', termId );

		fetch( CFG.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( ! res || ! res.success ) {
					showNotice( ( res && res.data && res.data.message ) ? res.data.message : CFG.labels.error, false );
					return;
				}

				lastTemplate = res.data;

				// 共通項目が1つも登録されていないシリーズ。
				var hasAny = Object.keys( res.data ).some( function ( k ) {
					if ( 'art_series_name' === k ) { return false; } // ターム名は常に入るため判定から除く。
					var v = res.data[ k ];
					return Array.isArray( v ) ? v.length > 0 : '' !== String( v || '' );
				} );

				var name = seriesSelect.options[ seriesSelect.selectedIndex ].text;
				if ( ! hasAny ) {
					showNotice( CFG.labels.nothing.replace( '%s', name ), false );
					return;
				}

				apply( res.data, false );
			} )
			.catch( function () {
				showNotice( CFG.labels.error, false );
			} );
	} );
} )( window.jQuery );
