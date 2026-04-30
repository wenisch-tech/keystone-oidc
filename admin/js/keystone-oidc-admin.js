/* global keystoneOidc */
(function () {
	// Build scopes string from checkboxes.
	function updateScopes() {
		var scopes = [ 'openid' ];
		[ 'profile', 'email' ].forEach( function ( s ) {
			var cb = document.querySelector( '[name="scope_' + s + '"]' );
			if ( cb && cb.checked ) {
				scopes.push( s );
			}
		} );
		var hidden = document.getElementById( 'allowed_scopes_hidden' );
		if ( hidden ) {
			hidden.value = scopes.join( ' ' );
		}
	}
	document.querySelectorAll( '[name^="scope_"]' ).forEach( function ( el ) {
		el.addEventListener( 'change', updateScopes );
	} );

	// Copy-to-clipboard buttons.
	document.querySelectorAll( '.wp-oidc-copy-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = document.getElementById( btn.dataset.target );
			if ( ! target ) {
				return;
			}
			navigator.clipboard.writeText( target.textContent ).then( function () {
				var orig = btn.textContent;
				btn.textContent = keystoneOidc.copied;
				setTimeout( function () {
					btn.textContent = orig;
				}, 2000 );
			} );
		} );
	} );
}());
