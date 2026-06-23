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
	} );
}());
