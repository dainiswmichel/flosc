/**
 * Knowledge Base tab script.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
(function () {
	document.querySelectorAll('.flosc-kb-dropzone').forEach(function (zone) {
		var input = zone.querySelector('input[type="file"]');
		if (!input) return;
		zone.addEventListener('dragover', function (e) {
			e.preventDefault();
			zone.classList.add('is-drag');
		});
		zone.addEventListener('dragleave', function () {
			zone.classList.remove('is-drag');
		});
		zone.addEventListener('drop', function (e) {
			e.preventDefault();
			zone.classList.remove('is-drag');
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				input.files = e.dataTransfer.files;
				var form = zone.closest('form');
				if (form) form.submit();
			}
		});
	});
})();
