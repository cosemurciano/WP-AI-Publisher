(function () {
	'use strict';

	window.wpaiPublisherAdmin = window.wpaiPublisherAdmin || {
		phase: '1',
		remoteCallsEnabled: false
	};

	// Initialise the WordPress tag-box UI for our category fields (idea
	// create/edit). The native tagBox renders removable token chips and wires
	// the ajax category autocomplete.
	document.addEventListener( 'DOMContentLoaded', function () {
		if ( window.tagBox && document.querySelector( '.wpai-tagsdiv' ) ) {
			try {
				window.tagBox.init();
			} catch ( e ) {}
		}

		initTabs();
	} );

	/**
	 * Generic tabbed-section controller shared by the admin pages.
	 *
	 * Markup contract (progressive enhancement — without JS all panels show
	 * stacked):
	 *   <div class="wpai-tabs" data-wpai-initial="" data-wpai-store="myKey">
	 *     <h2 class="nav-tab-wrapper wpai-nav">
	 *       <a class="nav-tab" data-wpai-tab="one">One</a> ...
	 *     </h2>
	 *     <div class="wpai-tab-panel" data-wpai-panel="one"> ... </div> ...
	 *   </div>
	 *
	 * Priority for the initially active tab: data-wpai-initial (server hint,
	 * e.g. after a redirect) > last used (localStorage) > first tab.
	 */
	function initTabs() {
		var wraps = document.querySelectorAll( '.wpai-tabs' );

		wraps.forEach( function ( wrap ) {
			var tabs   = wrap.querySelectorAll( '.wpai-nav .nav-tab' );
			var panels = wrap.querySelectorAll( '.wpai-tab-panel' );
			if ( ! tabs.length || ! panels.length ) {
				return;
			}

			var storeKey = 'wpaiPublisherTab:' + ( wrap.getAttribute( 'data-wpai-store' ) || 'default' );

			function activate( id, persist ) {
				var matched = false;
				panels.forEach( function ( panel ) {
					var isMatch = panel.getAttribute( 'data-wpai-panel' ) === id;
					panel.classList.toggle( 'is-active', isMatch );
					if ( isMatch ) {
						matched = true;
					}
				} );
				tabs.forEach( function ( tab ) {
					var isMatch = tab.getAttribute( 'data-wpai-tab' ) === id;
					tab.classList.toggle( 'nav-tab-active', isMatch );
					tab.setAttribute( 'aria-selected', isMatch ? 'true' : 'false' );
				} );
				if ( matched && persist ) {
					try {
						window.localStorage.setItem( storeKey, id );
					} catch ( e ) {}
				}
				return matched;
			}

			wrap.classList.add( 'wpai-tabs-ready' );

			tabs.forEach( function ( tab ) {
				tab.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					activate( tab.getAttribute( 'data-wpai-tab' ), true );
				} );
			} );

			// Priority: URL hash (deep link / cross-link) > server hint >
			// last used > first tab.
			var initial = '';
			var hash = ( window.location.hash || '' ).replace( /^#/, '' );
			if ( hash ) {
				var hashPanel = wrap.querySelector( '.wpai-tab-panel#' + ( window.CSS && CSS.escape ? CSS.escape( hash ) : hash ) );
				if ( hashPanel ) {
					initial = hashPanel.getAttribute( 'data-wpai-panel' ) || '';
				}
			}
			if ( ! initial ) {
				initial = wrap.getAttribute( 'data-wpai-initial' ) || '';
			}
			if ( ! initial ) {
				try {
					initial = window.localStorage.getItem( storeKey ) || '';
				} catch ( e ) {}
			}
			if ( ! initial || ! activate( initial, false ) ) {
				activate( tabs[0].getAttribute( 'data-wpai-tab' ), false );
			}
		} );
	}
}());
