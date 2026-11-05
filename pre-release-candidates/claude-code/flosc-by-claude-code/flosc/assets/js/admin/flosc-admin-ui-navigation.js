/**
 * Profile Bar tab: icon palettes and avatar media picker.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
(function() {
	function normalizeUnicodeIconInput(rawValue) {
		var value = String(rawValue || '').trim();
		if (!value) return '';

		var codePattern = /^U\+[0-9A-Fa-f]{4,6}(?:\s+U\+[0-9A-Fa-f]{4,6})*$/;
		if (!codePattern.test(value)) {
			return value;
		}

		var parts = value.split(/\s+/);
		var chars = [];
		for (var i = 0; i < parts.length; i++) {
			var hex = parts[i].slice(2);
			var codePoint = parseInt(hex, 16);
			if (!Number.isFinite(codePoint) || codePoint < 0) {
				return value;
			}
			chars.push(String.fromCodePoint(codePoint));
		}

		return chars.join('');
	}

	function hideAllIconPalettes() {
		var palettes = document.querySelectorAll('.flosc-avatar-icon-palette');
		palettes.forEach(function(palette) {
			palette.hidden = true;
		});
	}

	function showIconPalette(inputId) {
		hideAllIconPalettes();
		var palette = document.querySelector('.flosc-avatar-icon-palette[data-target-input="' + inputId + '"]');
		if (!palette) return;
		palette.hidden = false;
	}

	function openAvatarMediaPicker(targetInputId) {
		var input = document.getElementById(targetInputId);
		if (!input || typeof wp === 'undefined' || !wp.media) return;

		var frame = wp.media({
			title: 'Select Avatar Graphic',
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function() {
			var selection = frame.state().get('selection').first();
			if (!selection) return;
			var media = selection.toJSON();
			input.value = media.url || '';
			input.dispatchEvent(new Event('change', { bubbles: true }));
		});

		frame.open();
	}

	var fields = document.querySelectorAll('.flosc-avatar-media-input');
	fields.forEach(function(field) {
		field.addEventListener('click', function(e) {
			e.preventDefault();
			openAvatarMediaPicker(field.id);
		});
	});

	var iconInputs = document.querySelectorAll('.flosc-avatar-icon-input');
	iconInputs.forEach(function(input) {
		input.addEventListener('click', function(e) {
			e.stopPropagation();
		});
		input.addEventListener('blur', function() {
			var normalized = normalizeUnicodeIconInput(input.value);
			if (normalized !== input.value) {
				input.value = normalized;
			}
		});
	});

	var iconPickerButtons = document.querySelectorAll('.flosc-avatar-icon-picker-button');
	iconPickerButtons.forEach(function(button) {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var targetInputId = button.getAttribute('data-target-input');
			if (!targetInputId) return;
			showIconPalette(targetInputId);
		});
	});

	var iconOptions = document.querySelectorAll('.flosc-avatar-icon-option');
	iconOptions.forEach(function(option) {
		option.addEventListener('click', function(e) {
			e.preventDefault();
			var targetInputId = option.getAttribute('data-target-input');
			var icon = option.getAttribute('data-icon') || '';
			var targetInput = targetInputId ? document.getElementById(targetInputId) : null;
			if (!targetInput) return;
			targetInput.value = icon;
			targetInput.dispatchEvent(new Event('change', { bubbles: true }));
			hideAllIconPalettes();
		});
	});

	var buttons = document.querySelectorAll('.flosc-avatar-media-button');
	buttons.forEach(function(button) {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			var targetInputId = button.getAttribute('data-target-input');
			if (!targetInputId) return;
			openAvatarMediaPicker(targetInputId);
		});
	});

	document.addEventListener('click', function() {
		hideAllIconPalettes();
	});
})();
