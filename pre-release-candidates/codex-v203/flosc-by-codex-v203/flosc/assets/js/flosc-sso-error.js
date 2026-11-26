/**
 * FLOSC SSO error hand-off.
 *
 * When a provider sends the visitor back with a failure, PHP passes the
 * message through wp_localize_script() as window.floscSsoErrorData.message.
 * This copies it to window.flosc_sso_error, the name flosc-app.js reads on
 * init: the app shows the message in the chat and offers the login choices
 * again, instead of a browser alert().
 */
( function () {
	'use strict';

	var data = window.floscSsoErrorData;
	if ( data && typeof data.message === 'string' && '' !== data.message ) {
		window.flosc_sso_error = data.message;
	}
}() );
