/**
 * Payments tab: PayPal connection test.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminPayments */
			function floscSetPayPalTestResult(result, className, message) {
				var status = document.createElement('span');
				status.className = className;
				status.textContent = message;
				result.textContent = '';
				result.appendChild(status);
			}

			document.getElementById('flosc-paypal-test').addEventListener('click', function() {
				var btn = this;
				var result = document.getElementById('flosc-paypal-test-result');
				btn.disabled = true;
				btn.textContent = 'Testing...';
				result.textContent = '';
				fetch(ajaxurl + '?action=flosc_test_paypal&_wpnonce=' + floscAdminPayments.nonce)
					.then(r => r.json())
					.then(data => {
						btn.disabled = false;
						btn.textContent = 'Test PayPal Connection';
						if (data.success) {
							var wh = data.data.webhook;
							var whText = '';
							if (wh && typeof wh === 'object' && wh.id) {
								whText = ' · webhook ' + (wh.created ? 'created' : 'ok') + ' (' + String(wh.id).substring(0, 12) + '…)';
							} else if (typeof wh === 'string' && wh.indexOf('error:') === 0) {
								whText = ' · ' + wh;
							}
							var p0 = data.data.p0a;
							var p0Text = '';
							if (p0 && typeof p0 === 'object') {
								p0Text = p0.pass
									? ' · P0-A security OK'
									: ' · P0-A check failed (unsigned=' + (p0.unsigned || '?') + ', forged=' + (p0.forged || '?') + ')';
							}
							floscSetPayPalTestResult(result, 'flosc-paypal-result-success', '\u2705 Connected — ' + data.data.mode + ' mode, ' + data.data.app_name + whText + p0Text);
						} else {
							floscSetPayPalTestResult(result, 'flosc-paypal-result-error', '\u274c ' + (data.data || 'Connection failed'));
						}
					})
					.catch(() => {
						btn.disabled = false;
						btn.textContent = 'Test PayPal Connection';
						floscSetPayPalTestResult(result, 'flosc-paypal-result-error-lite', '\u274c Network error');
					});
			});
