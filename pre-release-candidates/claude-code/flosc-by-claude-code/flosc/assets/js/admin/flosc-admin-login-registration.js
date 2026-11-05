/**
 * Register and Login tab: send guest link.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminLoginRegistration */
(function() {
	document.getElementById('flosc-send-guest-link-btn')?.addEventListener('click', function() {
		const emailEl  = document.getElementById('flosc-send-guest-link-email');
		const forceEl  = document.getElementById('flosc-send-guest-link-force');
		const resultEl = document.getElementById('flosc-send-guest-link-result');
		const btn      = this;
		const email    = emailEl.value.trim();
		const sendLabel = floscAdminLoginRegistration.labels.sendGuestLink;

		if (!email) { emailEl.focus(); return; }

		btn.disabled    = true;
		btn.textContent = 'Sending...';
		resultEl.style.display = 'none';

		const data = new FormData();
		data.append('action', 'flosc_send_guest_link');
		data.append('nonce',  floscAdminLoginRegistration.nonce);
		data.append('email',  email);
		data.append('ivr',    floscAdminLoginRegistration.ivr);
		if (forceEl && forceEl.checked) {
			data.append('force', '1');
		}

		fetch(ajaxurl, { method: 'POST', body: data })
			.then(r => r.json())
			.then(res => {
				resultEl.style.display = 'block';
				if (res.success) {
					resultEl.style.color = '#1a7f37';
					resultEl.textContent = '✓ ' + res.data.message;
					emailEl.value = '';
				} else {
					resultEl.style.color = '#d63638';
					resultEl.textContent = '✗ ' + (res.data?.message || 'Send failed.');
				}
			})
			.catch(() => {
				resultEl.style.display  = 'block';
				resultEl.style.color    = '#d63638';
				resultEl.textContent    = '✗ Request failed — check your connection.';
			})
			.finally(() => {
				btn.disabled    = false;
				btn.textContent = sendLabel;
			});
	});
})();
