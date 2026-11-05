/**
 * Expose a verified SSO callback error to the FLOSC app UI.
 *
 * @package FLOSC
 */

(function () {
	'use strict';

	var config = window.floscSsoErrorConfig || {};
	window.flosc_sso_error = String(config.message || '');
}());
