/**
 * FLOSC flow editor: brand-colour hex mirror and the app-icon media picker.
 *
 * Enqueued by admin/flow-edit.php (wp_enqueue_media() is called there).
 *
 * @package FLOSC
 */
/* global wp */
document.getElementById('floscflow_color').addEventListener('input', function() {
	document.getElementById('floscflow_color_hex').value = this.value;
});

document.getElementById('flosc_upload_app_icon')?.addEventListener('click', function(e) {
	e.preventDefault();
	var frame = wp.media({ title: 'Choose App Icon', multiple: false, library: { type: 'image' } });
	frame.on('select', function() {
		var url = frame.state().get('selection').first().toJSON().url;
		document.getElementById('floscflow_favicon').value = url;
		var preview = document.getElementById('flosc_app_icon_preview');
		preview.src = url;
		preview.style.display = '';
	});
	frame.open();
});
