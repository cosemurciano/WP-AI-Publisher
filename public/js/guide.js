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
		var html = '<h3 class="wpai-guide__related-title">' + escapeHtml( i18n.related ) + '</h3>';
		html += '<div class="wpai-guide__cards">';
		articles.forEach( function ( a ) {
			html += '<a class="wpai-guide__card" href="' + encodeURI( a.url ) + '">';
			if ( a.thumb ) {
				html += '<span class="wpai-guide__card-media"><img src="' + encodeURI( a.thumb ) + '" alt="" loading="lazy"></span>';
			} else {
				html += '<span class="wpai-guide__card-media wpai-guide__card-media--empty"></span>';
			}
			html += '<span class="wpai-guide__card-body">';
			html += '<span class="wpai-guide__card-title">' + escapeHtml( a.title ) + '</span>';
			if ( a.excerpt ) {
				html += '<span class="wpai-guide__card-excerpt">' + escapeHtml( a.excerpt ) + '</span>';
			}
			html += '</span></a>';
		} );
		html += '</div>';
		return html;
	}

	function buildPrintDoc( title, contentHtml, relatedHtml ) {
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + escapeHtml( title ) +
			'</title><style>body{font-family:Georgia,serif;line-height:1.6;color:#1a1a1a;max-width:720px;margin:40px auto;padding:0 20px}' +
			'h1,h2,h3{font-family:Arial,sans-serif;line-height:1.25}a{color:#1a0dab}' +
			'.wpai-guide__cards{display:block}.wpai-guide__card{display:block;margin:0 0 12px;text-decoration:none;color:inherit}' +
			'.wpai-guide__card-title{font-weight:bold;display:block}img{max-width:160px;height:auto}' +
			'</style></head><body>' + contentHtml + relatedHtml + '</body></html>';
	}

	// Typewriter loader: cycles through the steps, typing each one out.
	function createLoader( box, textEl, steps ) {
		var timer = null;
		var stepIndex = 0;
		var charIndex = 0;
		var typing = true;

		function tick() {
			var msg = steps[ stepIndex ] || '';
			if ( typing ) {
				charIndex++;
				textEl.textContent = msg.slice( 0, charIndex );
				if ( charIndex >= msg.length ) {
					typing = false;
					timer = setTimeout( tick, 1400 );
					return;
				}
				timer = setTimeout( tick, 35 );
			} else {
				typing = true;
				charIndex = 0;
				stepIndex = ( stepIndex + 1 ) % steps.length;
				timer = setTimeout( tick, 250 );
			}
		}

		return {
			start: function () {
				if ( ! steps || ! steps.length ) {
					return;
				}
				box.hidden = false;
				stepIndex = 0;
				charIndex = 0;
				typing = true;
				tick();
			},
			stop: function () {
				if ( timer ) {
					clearTimeout( timer );
					timer = null;
				}
				box.hidden = true;
				textEl.textContent = '';
			}
		};
	}

	function autoGrow( el ) {
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 240 ) + 'px';
	}

	function initWidget( root ) {
		var form = root.querySelector( '.wpai-guide__form' );
		var input = root.querySelector( '.wpai-guide__input' );
		var hp = root.querySelector( '.wpai-guide__hp' );
		var status = root.querySelector( '.wpai-guide__status' );
		var loaderBox = root.querySelector( '.wpai-guide__loader' );
		var loaderText = root.querySelector( '.wpai-guide__loader-text' );
		var result = root.querySelector( '.wpai-guide__result' );
		var content = root.querySelector( '.wpai-guide__content' );
		var related = root.querySelector( '.wpai-guide__related' );
		var submit = root.querySelector( '.wpai-guide__submit' );
		var printBtn = root.querySelector( '.wpai-guide__print' );
		var waBtn = root.querySelector( '.wpai-guide__whatsapp' );
		var saveBtn = root.querySelector( '.wpai-guide__save' );
		var saveNote = root.querySelector( '.wpai-guide__save-note' );
		var i18n = wpaiGuide.i18n || {};
		var loader = createLoader( loaderBox, loaderText, i18n.loadingSteps || [] );
		var lastTitle = '';

		function setStatus( msg, isError ) {
			status.hidden = ! msg;
			status.textContent = msg || '';
			status.classList.toggle( 'is-error', !! isError );
		}

		if ( input ) {
			autoGrow( input );
			input.addEventListener( 'input', function () {
				autoGrow( input );
			} );
			// Enter submits, Shift+Enter adds a newline (chat-style).
			input.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key && ! e.shiftKey ) {
					e.preventDefault();
					if ( typeof form.requestSubmit === 'function' ) {
						form.requestSubmit();
					} else {
						form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
					}
				}
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var query = ( input.value || '' ).trim();
			if ( query.length < 8 ) {
				setStatus( i18n.tooShort, true );
				return;
			}
			submit.disabled = true;
			root.classList.add( 'is-loading' );
			result.hidden = true;
			setStatus( '', false );
			loader.start();
			if ( loaderBox && loaderBox.scrollIntoView ) {
				loaderBox.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}

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
				root.classList.remove( 'is-loading' );
				loader.stop();
				if ( ! r.ok || ! r.data || ! r.data.html ) {
					var msg = r.data && r.data.message ? r.data.message : i18n.error;
					setStatus( msg, true );
					return;
				}
				setStatus( '', false );
				lastTitle = query;
				content.innerHTML = r.data.html;
				related.innerHTML = renderRelated( r.data.articles, i18n );
				if ( saveNote ) {
					saveNote.hidden = true;
				}
				result.hidden = false;
				result.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} ).catch( function () {
				submit.disabled = false;
				root.classList.remove( 'is-loading' );
				loader.stop();
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

		if ( waBtn ) {
			waBtn.addEventListener( 'click', function () {
				var text = ( i18n.waText || '' ) + ' "' + lastTitle + '"\n' + window.location.href;
				window.open( 'https://wa.me/?text=' + encodeURIComponent( text ), '_blank', 'noopener' );
			} );
		}

		if ( saveBtn && saveNote ) {
			saveBtn.addEventListener( 'click', function () {
				saveNote.textContent = i18n.saveNote || '';
				saveNote.hidden = false;
				saveNote.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var widgets = document.querySelectorAll( '.wpai-guide' );
		Array.prototype.forEach.call( widgets, initWidget );
	} );
}() );
