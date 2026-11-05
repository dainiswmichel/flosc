/**
 * SSO tab: provider connection test.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminSso */
(function() {
	var nonce = floscAdminSso.nonce;
	var ajaxUrl = floscAdminSso.ajaxUrl;
	// v1.5.0: Pass flow_id so test reads per-flow SSO settings
	var flowId = floscAdminSso.flowId;

	document.querySelectorAll('.flosc-test-connection-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var provider = this.dataset.provider;
			var statusEl = document.getElementById('flosc-test-status-' + provider);
			var resultsEl = document.getElementById('flosc-test-results-' + provider);

			// Disable button, show loading
			btn.disabled = true;
			btn.textContent = '⏳ Testing...';
			statusEl.textContent = 'Connecting to ' + provider + '...';
			statusEl.style.color = '#666';
			resultsEl.style.display = 'none';
			resultsEl.innerHTML = '';

			var formData = new FormData();
			formData.append('action', 'flosc_test_sso_connection');
			formData.append('nonce', nonce);
			formData.append('provider', provider);
			formData.append('flow_id', flowId);

			fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
				.then(function(r) { return r.json(); })
				.then(function(resp) {
					btn.disabled = false;
					btn.textContent = '🔌 Test Connection';

					if (!resp.success) {
						statusEl.textContent = '❌ Broken';
						statusEl.style.color = '#d63638';
						return;
					}

					var data = resp.data;
					var html = '';

					data.checks.forEach(function(check) {
						var cls = check.pass ? 'pass' : 'fail';
						var icon = check.pass ? '✅' : '❌';
						html += '<div class="flosc-test-check ' + cls + '">'
							+ '<span class="flosc-test-check-icon">' + icon + '</span>'
							+ '<span class="flosc-test-check-label">' + escHtml(check.label) + '</span>'
							+ '<span class="flosc-test-check-detail">' + escHtml(check.detail) + '</span>'
							+ '</div>';
					});

					if (data.all_pass) {
						html += '<div class="flosc-test-summary all-pass">✅ Live</div>';
						statusEl.textContent = '✅ Live';
						statusEl.style.color = '#00a32a';
					} else {
						html += '<div class="flosc-test-summary has-fail">❌ Broken</div>';
						statusEl.textContent = '❌ Broken';
						statusEl.style.color = '#d63638';
					}

					resultsEl.innerHTML = html;
					resultsEl.style.display = 'block';
				})
				.catch(function(err) {
					btn.disabled = false;
					btn.textContent = '🔌 Test Connection';
					statusEl.textContent = '❌ Network error: ' + err.message;
					statusEl.style.color = '#d63638';
				});
		});
	});

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}
})();
