/**
 * Chat Styling tab: bubble style presets.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminChatStyling */
jQuery(document).ready(function($) {
	// Bubble style data
	var bubbleStyles = floscAdminChatStyling.bubbleStyles;
	
	// Update bubble preview via CSS custom properties (no element.style / jQuery .css).
	function updateBubblePreview() {
		var style = bubbleStyles[$('#flosc_chat_style_bubble').val()] || {};
		var accent = $('#flosc_chat_style_accent').val() || '';
		var root = document.getElementById('flosc-chat-style-preview');
		if (!root) {
			return;
		}
		root.style.setProperty('--flosc-preview-user-radius', style.user || '');
		root.style.setProperty('--flosc-preview-user-bg', accent);
		root.style.setProperty('--flosc-preview-assistant-radius', style.assistant || '');
		root.style.setProperty('--flosc-preview-assistant-border', style.border || '');
	}
	
	// Update scale display
	$('#flosc_chat_style_scale').on('input', function() {
		$('#scale_value').text(this.value + '%');
	});
	
	// Bubble style change
	$('#flosc_chat_style_bubble').on('change', updateBubblePreview);
	
	// Accent color sync
	$('#flosc_chat_style_accent').on('input', function() {
		$('#flosc_chat_style_accent_hex').val(this.value);
		updateBubblePreview();
		// Swatch selection via class only (no jQuery .css)
		$('.color-swatch').removeClass('is-active');
		$('.color-swatch[data-color="' + this.value + '"]').addClass('is-active');
	});
	
	// Quick swatches
	$('.color-swatch').on('click', function() {
		var color = $(this).data('color');
		$('#flosc_chat_style_accent').val(color).trigger('input');
	});
	
	// Initialize
	updateBubblePreview();
});
