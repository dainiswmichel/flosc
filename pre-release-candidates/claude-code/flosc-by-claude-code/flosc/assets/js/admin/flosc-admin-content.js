/**
 * Content tab: content-item, group and protection repeaters.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminContent */
function floscAdminContentEsc(value) {
	return String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

jQuery(document).ready(function($) {
	$('#flosc-add-content-type').on('click', function() {
		var row = '<tr class="flosc-content-type-row">'
			+ '<td><input type="text" name="content_type_singular[]" class="regular-text" placeholder="Lesson"></td>'
			+ '<td><input type="text" name="content_type_plural[]" class="regular-text" placeholder="Lessons"></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-content-type" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-content-types-body').append(row);
	});
	$(document).on('click', '.flosc-remove-content-type', function() {
		if ($('.flosc-content-type-row').length > 1) {
			$(this).closest('tr').remove();
		} else {
			$(this).closest('tr').find('input').val('');
		}
	});

	$('#flosc-add-level').on('click', function() {
		var row = '<tr class="flosc-level-row">'
			+ '<td><input type="text" name="level_slug[]" class="regular-text flosc-level-slug" placeholder="full_access" pattern="[a-z0-9_]+"></td>'
			+ '<td><input type="text" name="level_name[]" class="regular-text" placeholder="Full Access"></td>'
			+ '<td><input type="text" name="level_description[]" class="regular-text" placeholder=""></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-level" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-levels-body').append(row);
	});
	$(document).on('click', '.flosc-remove-level', function() {
		if ($('.flosc-level-row').length > 1) {
			$(this).closest('tr').remove();
		} else {
			$(this).closest('tr').find('input').val('');
		}
	});
	$(document).on('input', '.flosc-level-row input[name="level_name[]"]', function() {
		var $slug = $(this).closest('tr').find('.flosc-level-slug');
		if ($slug.val() === '') {
			$slug.val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, ''));
		}
	});
	$(document).on('blur', '.flosc-level-slug', function() {
		$(this).val($(this).val().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, ''));
	});

	var quizOptions = '<option value="">— No Quiz (Standalone) —</option>' + floscAdminContent.quizzes.map(function (item) {
		return '<option value="' + floscAdminContentEsc(item.value) + '">' + floscAdminContentEsc(item.label) + '</option>';
	}).join('');
	var catOptions = '<option value="">— Select Category —</option>' + floscAdminContent.categories.map(function (item) {
		return '<option value="' + floscAdminContentEsc(item.slug) + '">' + floscAdminContentEsc(item.name) + ' (' + floscAdminContentEsc(String(item.count)) + ' posts)' + '</option>';
	}).join('');
	$('#flosc-add-lesson-group').on('click', function() {
		var row = '<tr class="flosc-lesson-group-row">'
			+ '<td><select name="content_item_group_quiz[]" class="regular-text flosc-width-full">' + quizOptions + '</select></td>'
			+ '<td><select name="content_item_group_category[]" class="regular-text flosc-width-full">' + catOptions + '</select></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-lesson-group" title="Remove">&times;</button></td>'
			+ '</tr>';
		$('#flosc-lesson-groups-body').append(row);
	});
	$(document).on('click', '.flosc-remove-lesson-group', function() {
		if ($('.flosc-lesson-group-row').length > 1) {
			$(this).closest('tr').remove();
		}
	});

	var categoryOptions = '<option value="">— Select —</option>' + floscAdminContent.categories.map(function (item) {
		return '<option value="' + floscAdminContentEsc(item.id) + '">' + floscAdminContentEsc(item.name) + '</option>';
	}).join('');
	var tagOptions = '<option value="">— Select —</option>' + floscAdminContent.tags.map(function (item) {
		return '<option value="' + floscAdminContentEsc(item.id) + '">' + floscAdminContentEsc(item.name) + '</option>';
	}).join('');
	var levelOptions = '<option value="">— Any Member —</option>' + floscAdminContent.levels.map(function (item) {
		return '<option value="' + floscAdminContentEsc(item.slug) + '">' + floscAdminContentEsc(item.label) + '</option>';
	}).join('');
	function buildContentField(type) {
		if (type === 'post' || type === 'page') {
			return '<input type="text" name="protection_value[]" class="regular-text flosc-protection-value" placeholder="Post/Page ID">';
		}
		if (type === 'tag') {
			return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + tagOptions + '</select>';
		}
		return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + categoryOptions + '</select>';
	}
	/* A new rule starts where every rule stored before these columns existed
		already sits: members only, whole body. */
	var vgmOptions = floscAdminContent.vgmTiers.map(function (pair) {
		return '<option value="' + floscAdminContentEsc(pair[0]) + '"' + ( String(pair[0]) === 'member' ? " selected='selected'" : '' ) + '>' + floscAdminContentEsc(pair[1]) + '</option>';
	}).join('');
	var depthOptions = floscAdminContent.vgmDepths.map(function (pair) {
		return '<option value="' + floscAdminContentEsc(pair[0]) + '"' + ( String(pair[0]) === 'full' ? " selected='selected'" : '' ) + '>' + floscAdminContentEsc(pair[1]) + '</option>';
	}).join('');
	$('#flosc-add-protection').on('click', function() {
		var row = '<tr class="flosc-protection-row">'
			+ '<td><select name="protection_type[]" class="flosc-protection-type flosc-width-full"><option value="category">Category</option><option value="tag">Tag</option><option value="post">Post (ID)</option><option value="page">Page (ID)</option></select></td>'
			+ '<td>' + buildContentField('category') + '</td>'
			+ '<td><select name="protection_vgm[]" class="flosc-protection-vgm flosc-width-full">' + vgmOptions + '</select></td>'
			+ '<td><select name="protection_depth[]" class="flosc-protection-depth flosc-width-full">' + depthOptions + '</select></td>'
			+ '<td><select name="protection_level[]" class="flosc-protection-level-select flosc-width-full">' + levelOptions + '</select></td>'
			+ '<td class="flosc-text-center"><button type="button" class="button flosc-remove-protection-row">&times;</button></td>'
			+ '</tr>';
		$('#flosc-protection-body').append(row);
	});
	$(document).on('change', '.flosc-protection-type', function() {
		$(this).closest('tr').find('td:eq(1)').html(buildContentField($(this).val()));
	});
	$(document).on('click', '.flosc-remove-protection-row', function() {
		$(this).closest('tr').remove();
	});

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
