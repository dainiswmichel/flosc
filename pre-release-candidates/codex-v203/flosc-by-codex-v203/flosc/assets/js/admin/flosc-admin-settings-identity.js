/**
 * FLOSC Settings, Identity tab: brand-colour preview and the media-library
 * pickers for the chat logo, favicon and badge.
 *
 * Enqueued by admin/settings.php on the Identity tab.
 *
 * @package FLOSC
 */
/* global wp */
(function () {
	var input = document.getElementById('flow_primary_color');
	var preview = document.getElementById('color-preview');
	if (!input || !preview) return;
	function syncPreview(hex) {
		preview.setAttribute('data-color', hex);
		preview.style.setProperty('--flosc-preview-bg', hex);
	}
	syncPreview(preview.getAttribute('data-color') || input.value || '#4f46e5');
	input.addEventListener('input', function (e) {
		syncPreview(e.target.value);
	});
})();

(function() {
	function setupMediaButton(buttonId, inputId, previewId) {
		var btn = document.getElementById(buttonId);
		if (!btn) return;
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			var frame = wp.media({ title: 'Choose Image', multiple: false, library: { type: 'image' } });
			frame.on('select', function() {
				var url = frame.state().get('selection').first().toJSON().url;
				document.getElementById(inputId).value = url;
				var preview = document.getElementById(previewId);
				preview.src = url;
				preview.style.display = '';
			});
			frame.open();
		});
	}
	setupMediaButton('flosc_upload_chatlogo', 'flow_chatlogo_url', 'flosc_chatlogo_preview');
	setupMediaButton('flosc_upload_favicon', 'flow_favicon_url', 'flosc_favicon_preview');
	setupMediaButton('flosc_upload_badge', 'flow_badgeUrl', 'flosc_badge_preview');
})();
