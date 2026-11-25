/**
 * Member Levels tab: level and content-protection repeaters.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminMemberLevels */
function floscAdminMemberLevelsEsc(value) {
	return String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

jQuery(document).ready(function($) {

	// ─── Level repeater ─────────────────────────────────────────────

	$('#flosc-add-level').on('click', function() {
		var row = '<tr class="flosc-level-row">'
			+ '<td><input type="text" name="level_slug[]" class="regular-text flosc-level-slug" placeholder="full_access" pattern="[a-z0-9_]+" title="Lowercase letters, numbers, underscores only"></td>'
			+ '<td><input type="text" name="level_name[]" class="regular-text" placeholder="Full Access"></td>'
			+ '<td><input type="text" name="level_description[]" class="regular-text" placeholder="Complete access to all course content"></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-level" title="Remove this level">&times;</button></td>'
			+ '</tr>';
		$('#flosc-levels-body').append(row);
	});

	$(document).on('click', '.flosc-remove-level', function() {
		if ($('.flosc-level-row').length > 1) {
			$(this).closest('tr').remove();
		} else {
			// Clear the fields instead of removing last row
			var $row = $(this).closest('tr');
			$row.find('input').val('');
		}
	});

	// Auto-generate slug from display name
	$(document).on('input', '.flosc-level-row input[name="level_name[]"]', function() {
		var $row = $(this).closest('tr');
		var $flosc_slug = $row.find('.flosc-level-slug');
		// Only auto-fill if slug is currently empty
		if ($flosc_slug.val() === '') {
			var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
			$flosc_slug.val(slug);
		}
	});

	// Enforce slug format on blur
	$(document).on('blur', '.flosc-level-slug', function() {
		var v = $(this).val().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, '');
		$(this).val(v);
	});

	// ─── Content Protection repeater ────────────────────────────────

	// Build option strings for JS-generated rows
	var categoryOptions = '<option value="">— Select —</option>' + floscAdminMemberLevels.categories.map(function (item) {
		return '<option value="' + floscAdminMemberLevelsEsc(item.id) + '">' + floscAdminMemberLevelsEsc(item.name) + ' (' + item.count + ' posts)' + '</option>';
	}).join('');

	var tagOptions = '<option value="">— Select —</option>' + floscAdminMemberLevels.tags.map(function (item) {
		return '<option value="' + floscAdminMemberLevelsEsc(item.id) + '">' + floscAdminMemberLevelsEsc(item.name) + ' (' + item.count + ' posts)' + '</option>';
	}).join('');

	var levelOptions = '<option value="">— Any Member —</option>' + floscAdminMemberLevels.levels.map(function (item) {
		return '<option value="' + floscAdminMemberLevelsEsc(item.slug) + '">' + floscAdminMemberLevelsEsc(item.label) + '</option>';
	}).join('');

	function buildContentField(type) {
		if (type === 'post' || type === 'page') {
			return '<input type="text" name="protection_value[]" class="regular-text flosc-protection-value" placeholder="Post/Page ID">';
		} else if (type === 'tag') {
			return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + tagOptions + '</select>';
		}
		// Default: category
		return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + categoryOptions + '</select>';
	}

	var vgmOptions = floscAdminMemberLevels.vgmTiers.map(function (pair) {
		return '<option value="' + floscAdminMemberLevelsEsc(pair[0]) + '"' + ( String(pair[0]) === 'member' ? " selected='selected'" : '' ) + '>' + floscAdminMemberLevelsEsc(pair[1]) + '</option>';
	}).join('');
	var depthOptions = floscAdminMemberLevels.vgmDepths.map(function (pair) {
		return '<option value="' + floscAdminMemberLevelsEsc(pair[0]) + '"' + ( String(pair[0]) === 'full' ? " selected='selected'" : '' ) + '>' + floscAdminMemberLevelsEsc(pair[1]) + '</option>';
	}).join('');

	$('#flosc-add-protection').on('click', function() {
		var row = '<tr class="flosc-protection-row">'
			+ '<td><select name="protection_type[]" class="flosc-protection-type flosc-width-full"><option value="category">Category</option><option value="tag">Tag</option><option value="post">Post (ID)</option><option value="page">Page (ID)</option></select></td>'
			+ '<td>' + buildContentField('category') + '</td>'
			+ '<td><select name="protection_vgm[]" class="flosc-protection-vgm flosc-width-full">' + vgmOptions + '</select></td>'
			+ '<td><select name="protection_depth[]" class="flosc-protection-depth flosc-width-full">' + depthOptions + '</select></td>'
			+ '<td><select name="protection_level[]" class="flosc-protection-level-select flosc-width-full">' + levelOptions + '</select></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-protection-row" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-protection-body').append(row);
	});

	// When type changes, swap the content field
	$(document).on('change', '.flosc-protection-type', function() {
		var type = $(this).val();
		var $td = $(this).closest('tr').find('td:eq(1)');
		$td.html(buildContentField(type));
	});

	$(document).on('click', '.flosc-remove-protection-row', function() {
		$(this).closest('tr').remove();
	});

	// ─── Free lesson mode toggle ────────────────────────────────────

	function toggleFreeLessonFields() {
		var mode = $('#flow_free_content_item_mode').val();
		if (mode === 'fixed') {
			$('#flow_free_content_item_count_row').show();
			$('#flow_free_content_item_proportion_row').hide();
		} else {
			$('#flow_free_content_item_count_row').hide();
			$('#flow_free_content_item_proportion_row').show();
		}
	}
	toggleFreeLessonFields();
	$('#flow_free_content_item_mode').on('change', toggleFreeLessonFields);
});
