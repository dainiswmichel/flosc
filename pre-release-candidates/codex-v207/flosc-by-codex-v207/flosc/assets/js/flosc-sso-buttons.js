/**
 * FLOSC SSO login buttons.
 *
 * Sends a click on any .flosc-sso-button to FLOSC's REST authorize route for
 * that provider, carrying the page the visitor should return to.
 *
 * The authorize route's base URL comes from PHP through wp_localize_script()
 * as window.floscSsoButtons.authorizeBase (rest_url( 'flosc/v1/sso/authorize/' )),
 * so this file holds no PHP value and no site address.
 *
 * redirect_to: when the login page was itself reached with ?redirect_to=...
 * (for example wp-login.php?redirect_to=https://example.com/), that target is
 * kept; otherwise the current page is used.
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var config = window.floscSsoButtons || {};
		var authorizeBase = typeof config.authorizeBase === 'string' ? config.authorizeBase : '';
		if ( '' === authorizeBase ) {
			return;
		}

		document.querySelectorAll( '.flosc-sso-button' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var provider = this.dataset.provider;
				var urlParams = new URLSearchParams( window.location.search );
				var redirectTo = urlParams.get( 'redirect_to' ) || window.location.href;
				var baseUrl = authorizeBase + provider;
				// Non-pretty permalinks put the route in ?rest_route=, so the
				// next parameter joins with & rather than ?.
				var separator = baseUrl.indexOf( '?' ) !== -1 ? '&' : '?';
				window.location.href = baseUrl + separator + 'redirect_to=' + encodeURIComponent( redirectTo );
			} );
		} );
	} );
}() );
