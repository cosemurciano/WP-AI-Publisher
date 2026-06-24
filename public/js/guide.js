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

	// Typewriter loader: cycles through the steps, typing each one out and
	// pushing the current text through the supplied apply() callback (used to
	// animate the input placeholder during generation).
	function createLoader( apply, steps ) {
		var timer = null;
		var stepIndex = 0;
		var charIndex = 0;
		var typing = true;

		function tick() {
			var msg = steps[ stepIndex ] || '';
			if ( typing ) {
				charIndex++;
				apply( msg.slice( 0, charIndex ) );
				if ( charIndex >= msg.length ) {
					typing = false;
					timer = setTimeout( tick, 1400 );
					return;
				}
				timer = setTimeout( tick, 38 );
			} else {
				typing = true;
				charIndex = 0;
				stepIndex = ( stepIndex + 1 ) % steps.length;
				timer = setTimeout( tick, 250 );
			}
		}

		return {
			running: false,
			start: function () {
				if ( ! steps || ! steps.length ) {
					return;
				}
				this.running = true;
				stepIndex = 0;
				charIndex = 0;
				typing = true;
				tick();
			},
			stop: function () {
				this.running = false;
				if ( timer ) {
					clearTimeout( timer );
					timer = null;
				}
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
		var result = root.querySelector( '.wpai-guide__result' );
		var content = root.querySelector( '.wpai-guide__content' );
		var related = root.querySelector( '.wpai-guide__related' );
		var submit = root.querySelector( '.wpai-guide__submit' );
		var printBtn = root.querySelector( '.wpai-guide__print' );
		var waBtn = root.querySelector( '.wpai-guide__whatsapp' );
		var saveBtn = root.querySelector( '.wpai-guide__save' );
		var saveNote = root.querySelector( '.wpai-guide__save-note' );
		var i18n = wpaiGuide.i18n || {};
		var basePlaceholder = input ? input.getAttribute( 'placeholder' ) || '' : '';
		// During generation the typing animation is shown inside the input field
		// (as its placeholder), so it only ever appears after a request is sent.
		var loader = createLoader( function ( text ) {
			if ( input ) {
				input.setAttribute( 'placeholder', text );
			}
		}, i18n.loadingSteps || [] );
		var lastTitle = '';
		var lastGuideUrl = '';
		var lastRequestId = 0;
		var save = wpaiGuide.save || {};

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
			if ( input ) {
				input.value = '';
				input.style.height = 'auto';
				input.readOnly = true;
			}
			loader.start();

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
				if ( input ) {
					input.readOnly = false;
					input.setAttribute( 'placeholder', basePlaceholder );
				}
				if ( ! r.ok || ! r.data || ! r.data.html ) {
					var msg = r.data && r.data.message ? r.data.message : i18n.error;
					setStatus( msg, true );
					return;
				}
				setStatus( '', false );
				lastTitle = query;
				lastGuideUrl = r.data.guide_url || '';
				lastRequestId = r.data.request_id || 0;
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
				if ( input ) {
					input.readOnly = false;
					input.setAttribute( 'placeholder', basePlaceholder );
				}
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
				var url = lastGuideUrl || window.location.href;
				var text = ( i18n.waText || '' ) + ' "' + lastTitle + '"\n' + url;
				window.open( 'https://wa.me/?text=' + encodeURIComponent( text ), '_blank', 'noopener' );
			} );
		}

		function showNote( html ) {
			saveNote.innerHTML = html;
			saveNote.hidden = false;
			saveNote.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}

		// Quick-reply chips: fill the input with the saved request and submit.
		var chips = root.querySelectorAll( '.wpai-guide__chip' );
		Array.prototype.forEach.call( chips, function ( chip ) {
			chip.addEventListener( 'click', function () {
				if ( ! input ) {
					return;
				}
				input.value = chip.getAttribute( 'data-query' ) || chip.textContent.trim();
				autoGrow( input );
				if ( typeof form.requestSubmit === 'function' ) {
					form.requestSubmit();
				} else {
					form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
				}
			} );
		} );

		if ( saveBtn && saveNote ) {
			saveBtn.addEventListener( 'click', function () {
				// Membership disabled: keep the legacy informational note.
				if ( ! save.enabled ) {
					saveNote.textContent = i18n.saveNote || '';
					saveNote.hidden = false;
					return;
				}
				// Not logged in: invite to register/login.
				if ( ! save.isLoggedIn ) {
					var link = save.loginUrl ? ' <a href="' + encodeURI( save.loginUrl ) + '">' + escapeHtml( i18n.saveLoginLink ) + '</a>' : '';
					showNote( escapeHtml( i18n.saveLoginText ) + link );
					return;
				}
				if ( ! lastRequestId ) {
					return;
				}
				saveBtn.disabled = true;
				var headers = { 'Content-Type': 'application/json' };
				if ( wpaiGuide.restNonce ) {
					headers['X-WP-Nonce'] = wpaiGuide.restNonce;
				}
				fetch( save.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: headers,
					body: JSON.stringify( { request_id: lastRequestId } )
				} ).then( function ( res ) {
					return res.json().then( function ( data ) {
						return { ok: res.ok, data: data };
					} );
				} ).then( function ( r ) {
					saveBtn.disabled = false;
					if ( ! r.ok || ! r.data || ! r.data.saved ) {
						showNote( escapeHtml( i18n.saveError ) );
						return;
					}
					var url = r.data.accountUrl || save.accountUrl || '';
					var link = url ? ' <a href="' + encodeURI( url ) + '">' + escapeHtml( i18n.saveOkLink ) + '</a>' : '';
					showNote( escapeHtml( i18n.saveOk ) + link );
				} ).catch( function () {
					saveBtn.disabled = false;
					showNote( escapeHtml( i18n.saveError ) );
				} );
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var widgets = document.querySelectorAll( '.wpai-guide' );
		Array.prototype.forEach.call( widgets, initWidget );
	} );
}() );
