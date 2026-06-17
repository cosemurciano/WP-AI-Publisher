/* global wpaiGuide */
( function () {
	'use strict';

	if ( typeof wpaiGuide === 'undefined' ) {
		return;
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.textContent = String( str == null ? '' : str );
		return div.innerHTML;
	}

	function renderRelated( articles, i18n ) {
		if ( ! articles || ! articles.length ) {
			return '';
		}
		var html = '<h3 class="wpai-guide__related-title">' + escapeHtml( i18n.related ) + '</h3><ul class="wpai-guide__related-list">';
		articles.forEach( function ( a ) {
			html += '<li class="wpai-guide__related-item">';
			if ( a.thumb ) {
				html += '<a href="' + encodeURI( a.url ) + '" class="wpai-guide__related-thumb"><img src="' + encodeURI( a.thumb ) + '" alt="" loading="lazy"></a>';
			}
			html += '<div class="wpai-guide__related-text"><a href="' + encodeURI( a.url ) + '">' + escapeHtml( a.title ) + '</a>';
			if ( a.excerpt ) {
				html += '<p>' + escapeHtml( a.excerpt ) + '</p>';
			}
			html += '</div></li>';
		} );
		html += '</ul>';
		return html;
	}

	function buildPrintDoc( title, contentHtml, relatedHtml ) {
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + escapeHtml( title ) +
			'</title><style>body{font-family:Georgia,serif;line-height:1.6;color:#1a1a1a;max-width:720px;margin:40px auto;padding:0 20px}' +
			'h1,h2,h3{font-family:Arial,sans-serif;line-height:1.25}a{color:#1a0dab}' +
			'.wpai-guide__related-list{list-style:none;padding:0}.wpai-guide__related-item{margin:0 0 12px}img{max-width:120px;height:auto}' +
			'</style></head><body>' + contentHtml + relatedHtml + '</body></html>';
	}

	function initWidget( root ) {
		var form = root.querySelector( '.wpai-guide__form' );
		var input = root.querySelector( '.wpai-guide__input' );
		var hp = root.querySelector( '.wpai-guide__hp' );
		var status = root.querySelector( '.wpai-guide__status' );
		var result = root.querySelector( '.wpai-guide__result' );
		var content = root.querySelector( '.wpai-guide__content' );
		var related = root.querySelector( '.wpai-guide__related' );
		var submit = root.querySelector( '.wpai-guide__submit' );
		var printBtn = root.querySelector( '.wpai-guide__print' );
		var i18n = wpaiGuide.i18n || {};
		var lastTitle = '';

		function setStatus( msg, isError ) {
			status.hidden = ! msg;
			status.textContent = msg || '';
			status.classList.toggle( 'is-error', !! isError );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var query = ( input.value || '' ).trim();
			if ( query.length < 8 ) {
				setStatus( i18n.tooShort, true );
				return;
			}
			submit.disabled = true;
			result.hidden = true;
			setStatus( i18n.loading, false );

			var headers = { 'Content-Type': 'application/json' };
			if ( wpaiGuide.restNonce ) {
				headers['X-WP-Nonce'] = wpaiGuide.restNonce;
			}

			fetch( wpaiGuide.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				headers: headers,
				body: JSON.stringify( {
					query: query,
					nonce: wpaiGuide.nonce,
					website: hp ? hp.value : ''
				} )
			} ).then( function ( res ) {
				return res.json().then( function ( data ) {
					return { ok: res.ok, data: data };
				} );
			} ).then( function ( r ) {
				submit.disabled = false;
				if ( ! r.ok || ! r.data || ! r.data.html ) {
					var msg = r.data && r.data.message ? r.data.message : i18n.error;
					setStatus( msg, true );
					return;
				}
				setStatus( '', false );
				lastTitle = query;
				content.innerHTML = r.data.html;
				related.innerHTML = renderRelated( r.data.articles, i18n );
				result.hidden = false;
				result.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} ).catch( function () {
				submit.disabled = false;
				setStatus( i18n.error, true );
			} );
		} );

		if ( printBtn ) {
			printBtn.addEventListener( 'click', function () {
				var doc = buildPrintDoc( ( i18n.printTitle || 'Guide' ) + ' — ' + lastTitle, content.innerHTML, related.innerHTML );
				var win = window.open( '', '_blank' );
				if ( ! win ) {
					return;
				}
				win.document.open();
				win.document.write( doc );
				win.document.close();
				win.focus();
				setTimeout( function () {
					win.print();
				}, 300 );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var widgets = document.querySelectorAll( '.wpai-guide' );
		Array.prototype.forEach.call( widgets, initWidget );
	} );
}() );
