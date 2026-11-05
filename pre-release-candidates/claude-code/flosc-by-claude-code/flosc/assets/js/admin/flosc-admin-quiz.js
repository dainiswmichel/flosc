/**
 * Quiz tab: load demo quiz into the editor.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
document.addEventListener('click', function(e) {
	var btn = e.target.closest('.flosc-load-demo');
	if (!btn) return;

	var sourceId  = btn.dataset.source;
	var targetId  = btn.dataset.textarea;
	var detailsId = btn.dataset.details;

	var source  = document.getElementById(sourceId);
	var target  = document.getElementById(targetId);
	var details = document.getElementById(detailsId);

	if (!source || !target) return;

	target.value = source.value;

	if (details) details.open = true;
	target.scrollIntoView({ behavior: 'smooth', block: 'center' });
	target.focus();

	var original = btn.textContent;
	btn.textContent = '✓ Loaded!';
	btn.disabled = true;
	setTimeout(function() {
		btn.textContent = original;
		btn.disabled = false;
	}, 1800);
});
