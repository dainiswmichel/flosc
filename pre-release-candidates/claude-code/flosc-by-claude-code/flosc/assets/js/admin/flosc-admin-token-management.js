/**
 * Token Management tab script.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
(function () {
	function setDisabled(el, off) {
		if (!el) return;
		el.classList.toggle('flosc-is-disabled', !!off);
		el.querySelectorAll('input, select, textarea').forEach(function (field) {
			// Keep fields enabled for POST; only dim UI. Cap input still posts when custom.
			field.disabled = false;
		});
	}

	document.querySelectorAll('[data-flosc-token-source]').forEach(function (sel) {
		var pid = sel.getAttribute('data-flosc-product');
		var custom = document.querySelector('[data-flosc-token-custom="' + pid + '"]');
		function sync() {
			setDisabled(custom, sel.value !== 'custom');
		}
		sel.addEventListener('change', sync);
		sync();
	});

	document.querySelectorAll('[data-flosc-token-cap-mode]').forEach(function (sel) {
		var pid = sel.getAttribute('data-flosc-product');
		var wrap = document.querySelector('[data-flosc-token-cap-input="' + pid + '"]');
		function sync() {
			setDisabled(wrap, sel.value !== 'custom');
		}
		sel.addEventListener('change', sync);
		sync();
	});
})();
