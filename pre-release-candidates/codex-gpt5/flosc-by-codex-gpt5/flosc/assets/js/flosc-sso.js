/**
 * FLOSC SSO login-button behavior.
 *
 * @package FLOSC
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var config = window.floscSsoConfig || {};
		var baseUrl = String(config.authorizeBaseUrl || '');
		if (!baseUrl) {
			return;
		}

		document.querySelectorAll('.flosc-sso-button').forEach(function (button) {
			button.addEventListener('click', function () {
				var provider = String(this.dataset.provider || '').replace(/[^a-z0-9_-]/gi, '');
				if (!provider) {
					return;
				}

				var urlParams = new URLSearchParams(window.location.search);
				var redirectTo = urlParams.get('redirect_to') || window.location.href;
				var target = baseUrl + provider;
				var separator = target.indexOf('?') !== -1 ? '&' : '?';
				window.location.href = target + separator + 'redirect_to=' + encodeURIComponent(redirectTo);
			});
		});
	});
}());
