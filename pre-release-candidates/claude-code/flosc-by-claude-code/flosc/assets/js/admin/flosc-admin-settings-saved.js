/**
 * FLOSC Settings: bring the "Settings saved" notice into view after the
 * post-redirect-get reload, then drop ?saved=1 so a refresh does not flash it
 * again.
 *
 * Enqueued by admin/settings.php only when the notice is printed.
 *
 * @package FLOSC
 */
(function () {
	var notice = document.getElementById('flosc-save-feedback');
	if (notice) {
		try {
			notice.focus({ preventScroll: true });
		} catch (e) {
			try { notice.focus(); } catch (e2) { /* ignore */ }
		}
		if (typeof notice.scrollIntoView === 'function') {
			notice.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}
	try {
		var url = new URL(window.location.href);
		if (url.searchParams.has('saved')) {
			url.searchParams.delete('saved');
			window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
		}
	} catch (e3) { /* ignore */ }
})();
