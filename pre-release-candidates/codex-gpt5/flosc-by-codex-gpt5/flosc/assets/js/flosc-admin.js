/* global ajaxurl, jQuery */
/**
 * FLOSC administration page behavior.
 *
 * Dynamic values are supplied with wp_localize_script() by the page that owns
 * them. Every module is safe to load on another FLOSC administration screen.
 */
(function () {
	'use strict';

	var redirectConfig = window.floscSettingsRedirectConfig || {};
	if (redirectConfig.url) {
		window.location.replace(String(redirectConfig.url));
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		var button = target && target.closest ? target.closest('button[name="flosc_save"]') : null;
		var form;
		var field;

		if (!button || button.disabled) {
			return;
		}

		form = document.getElementById('flosc-settings-form');
		if (!form) {
			return;
		}

		event.preventDefault();
		if (!form.querySelector('input[name="flosc_save"]')) {
			field = document.createElement('input');
			field.type = 'hidden';
			field.name = 'flosc_save';
			field.value = '1';
			form.appendChild(field);
		}
		form.submit();
	});

/* companion */
jQuery(document).ready(function($) {
	var $modeRadios = $('input[name="flow_companion_content_display_mode"]');

	// Toggle widget settings visibility based on display mode
	function toggleCompanionSettings() {
		var mode = $('input[name="flow_companion_content_display_mode"]:checked').val();
		var $settings = $('#companion-widget-settings');
		
		if (mode === 'in_chat') {
			$settings.addClass('flosc-companion-disabled');
		} else {
			$settings.removeClass('flosc-companion-disabled');
		}
		
		// Highlight selected radio card
		$('.flosc-companion-mode-card').removeClass('is-active');
		$('input[name="flow_companion_content_display_mode"]:checked').closest('.flosc-companion-mode-card').addClass('is-active');

		// Companion and Both modes require the widget shell; auto-enable to avoid hidden misconfiguration.
		if ((mode === 'companion' || mode === 'both') && !$('#flow_companion_enabled').is(':checked')) {
			$('#flow_companion_enabled').prop('checked', true);
		}
	}
	
	$('input[name="flow_companion_content_display_mode"]').on('change', toggleCompanionSettings);
	
	// Sync color picker with hex display + preview via CSS custom property (no per-element .css())
	$('#flow_companion_accent_color').on('input', function() {
		var color = $(this).val();
		var preview = document.querySelector('.flosc-companion-preview');
		$('#companion_accent_hex').val(color);
		if (preview) {
			preview.style.setProperty('--flosc-companion-accent', color);
		}
	});

	$('#flosc-companion-color-reset').on('click', function() {
		$('#flow_companion_accent_color').val('#6366f1').trigger('input');
	});

	function updateCompanionValidationHints() {
		var hints = [];
		var autoOpenEnabled = $('#flow_companion_auto_open_enabled').is(':checked');
		var autoOpenDelay = parseInt($('#flow_companion_auto_open_delay_ms').val(), 10) || 0;
		var autoOpenOnce = $('#flow_companion_auto_open_once_per_session').is(':checked');
		var launchOnExit = $('#flow_companion_launch_on_exit_intent').is(':checked');
		var launchOnScroll = $('#flow_companion_launch_on_scroll_threshold').is(':checked');
		var launchScrollPercent = parseInt($('#flow_companion_launch_on_scroll_percent').val(), 10) || 0;
		var cooldownMs = parseInt($('#flow_companion_trigger_cooldown_ms').val(), 10) || 0;
		var rememberOpen = $('#flow_companion_remember_open_state').is(':checked');
		var stateStorage = String($('#flow_companion_state_storage').val() || 'session');

		if (autoOpenEnabled && autoOpenDelay < 500) {
			hints.push('Auto Open Delay is below 500ms. Consider 1000ms+ to reduce intrusive launches.');
		}

		if (!autoOpenEnabled && autoOpenOnce) {
			hints.push('Timed Auto Open Once Per Session is enabled while Auto Open is off. This frequency setting has no effect until Auto Open is enabled.');
		}

		if ((launchOnExit || launchOnScroll) && cooldownMs > 0 && cooldownMs < 5000) {
			hints.push('Trigger Cooldown is below 5000ms while behavior triggers are active. Consider a longer cooldown to avoid repeated opens.');
		}

		if (launchOnScroll && launchScrollPercent === 0) {
			hints.push('Scroll Trigger threshold is set to 0%. Companion can open as soon as scrolling starts.');
		}

		if (!rememberOpen && stateStorage === 'local' && cooldownMs > 0) {
			hints.push('State Storage is set to Local while Remember Open State is off. Trigger cooldown will still persist across browser restarts.');
		} else if (!rememberOpen && stateStorage === 'local') {
			hints.push('State Storage is set to Local while Remember Open State is off. Storage selection mainly affects trigger cooldown behavior in this combination.');
		}

		if (rememberOpen && stateStorage === 'local') {
			hints.push('Remember Open State + Local storage persists companion state across browser restarts for this flow.');
		}

		if (hints.length > 0) {
			$('#flosc-companion-validation-hints-text').html(hints.join('<br>'));
			$('#flosc-companion-validation-hints').show();
		} else {
			$('#flosc-companion-validation-hints').hide();
			$('#flosc-companion-validation-hints-text').empty();
		}
	}

	$('#flow_companion_auto_open_enabled, #flow_companion_auto_open_delay_ms, #flow_companion_auto_open_once_per_session, #flow_companion_launch_on_exit_intent, #flow_companion_launch_on_scroll_threshold, #flow_companion_launch_on_scroll_percent, #flow_companion_trigger_cooldown_ms, #flow_companion_remember_open_state, #flow_companion_state_storage')
		.on('change input', updateCompanionValidationHints);

	function updateCompanionTargetMode() {
		var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
		var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');
		var disableIncludes = (mode === 'sitewide') || !advancedEnabled;
		var disableExcludes = !advancedEnabled;

		$('#flosc-companion-target-groups').toggleClass('flosc-companion-targets-disabled', !advancedEnabled);
		$('#flosc-companion-targets-disabled-note').toggle(!advancedEnabled);
		$('#flosc-companion-target-include-custom-row').toggle(advancedEnabled && mode !== 'sitewide');
		$('#flosc-companion-target-exclude-custom-row').toggle(advancedEnabled);

		$('#flow_companion_include_pages, #flow_companion_include_posts, #flow_companion_include_categories, #flow_companion_include_tags, #flow_companion_target_include_custom')
			.prop('disabled', disableIncludes);
		$('#flow_companion_exclude_pages, #flow_companion_exclude_posts, #flow_companion_exclude_categories, #flow_companion_exclude_tags, #flow_companion_target_exclude_custom')
			.prop('disabled', disableExcludes);
	}

	function updateCompanionRoutingMode() {
		var mode = String($('#flow_companion_routing_mode').val() || 'hub');
		$('.flosc-hub-routing-row').toggle(mode === 'hub');
	}

	$('input[name="flow_companion_target_mode"]').on('change', updateCompanionTargetMode);
	$('#flow_companion_targeting_customize').on('change', updateCompanionTargetMode);
	$('#flow_companion_routing_mode').on('change', updateCompanionRoutingMode);

	$('.flosc-settings-form').on('submit', function() {
		var mode = $('input[name="flow_companion_target_mode"]:checked').val() || 'sitewide';
		var advancedEnabled = $('#flow_companion_targeting_customize').is(':checked');

		if (!advancedEnabled) {
			$('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option').prop('selected', false);
			$('#flow_companion_exclude_pages option, #flow_companion_exclude_posts option, #flow_companion_exclude_categories option, #flow_companion_exclude_tags option').prop('selected', false);
			$('#flow_companion_target_include_custom').val('');
			$('#flow_companion_target_exclude_custom').val('');
			return;
		}

		if (mode !== 'sitewide') {
			return;
		}

		$('#flow_companion_include_pages option, #flow_companion_include_posts option, #flow_companion_include_categories option, #flow_companion_include_tags option')
			.prop('selected', false);
		$('#flow_companion_target_include_custom').val('');
	});

	$('#flow_companion_accent_color').trigger('input');
	$modeRadios.prop('disabled', false);
	updateCompanionValidationHints();
	updateCompanionTargetMode();
	updateCompanionRoutingMode();
});


/* email */
(function () {
	function addRow(prefix) {
		var table = document.querySelector('.flosc-fu-table[data-prefix="' + prefix + '"]');
		if (!table) { return; }
		var tbody = table.querySelector('.flosc-fu-body');
		var tr = document.createElement('tr');
		tr.className = 'flosc-fu-row';
		tr.innerHTML =
			'<td><input type="number" min="0" max="365" class="small-text" name="' + prefix + '_fu_day[]" value="0"></td>' +
			'<td><input type="text" name="' + prefix + '_fu_subject[]" value="" class="flosc-width-full"></td>' +
			'<td><textarea name="' + prefix + '_fu_body[]" rows="3" class="flosc-width-full"></textarea></td>' +
			'<td class="flosc-text-center"><button type="button" class="button flosc-fu-remove" title="Remove">&times;</button></td>';
		tbody.appendChild(tr);
	}
	document.addEventListener('click', function (e) {
		if (e.target && e.target.classList.contains('flosc-fu-add')) {
			e.preventDefault();
			addRow(e.target.getAttribute('data-prefix'));
		} else if (e.target && e.target.classList.contains('flosc-fu-remove')) {
			e.preventDefault();
			var row = e.target.closest('.flosc-fu-row');
			if (row) { row.parentNode.removeChild(row); }
		}
	});
})();


/* quiz */
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


/* chat-navigation */
(function() {
	const tbody = document.getElementById('flosc-menu-items-body');
	const addBtn = document.getElementById('flosc-add-menu-item');
	const actions = (window.floscChatNavigationConfig || {}).actions || {};

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-menu-row';
		tr.innerHTML = `
			<td class="flosc-menu-row-number"></td>
			<td><input type="text" name="visitor_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="visitor_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
			<td><button type="button" class="button flosc-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-remove-menu-item')) {
			e.target.closest('.flosc-menu-row').remove();
			renumber();
		}
	});
})();


/* chat-navigation */
(function() {
	const tbody = document.getElementById('flosc-guest-menu-items-body');
	const addBtn = document.getElementById('flosc-guest-add-menu-item');
	const actions = (window.floscChatNavigationConfig || {}).actions || {};

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-guest-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-guest-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-guest-menu-row';
		tr.innerHTML = `
			<td class="flosc-guest-menu-row-number"></td>
			<td><input type="text" name="guest_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="guest_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_quiz')}</select></td>
			<td><button type="button" class="button flosc-guest-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-guest-remove-menu-item')) {
			e.target.closest('.flosc-guest-menu-row').remove();
			renumber();
		}
	});
})();


/* chat-navigation */
(function() {
	const tbody = document.getElementById('flosc-member-menu-items-body');
	const addBtn = document.getElementById('flosc-member-add-menu-item');
	const actions = (window.floscChatNavigationConfig || {}).actions || {};

	if (!tbody || !addBtn) {
		return;
	}

	function buildActionOptions(selected) {
		let html = '';
		for (const [val, desc] of Object.entries(actions)) {
			const sel = val === selected ? ' selected' : '';
			html += `<option value="${val}"${sel}>${desc}</option>`;
		}
		return html;
	}

	function renumber() {
		tbody.querySelectorAll('.flosc-member-menu-row').forEach((row, i) => {
			row.querySelector('.flosc-member-menu-row-number').textContent = i + 1;
		});
	}

	addBtn.addEventListener('click', function() {
		const tr = document.createElement('tr');
		tr.className = 'flosc-member-menu-row';
		tr.innerHTML = `
			<td class="flosc-member-menu-row-number"></td>
			<td><input type="text" name="member_menu_label[]" class="regular-text" placeholder="Menu label" required></td>
			<td><select name="member_menu_action[]" class="flosc-ui-action-select">${buildActionOptions('open_lesson_library')}</select></td>
			<td><button type="button" class="button flosc-member-remove-menu-item" title="Remove item">&times;</button></td>
		`;
		tbody.appendChild(tr);
		renumber();
		tr.querySelector('input').focus();
	});

	tbody.addEventListener('click', function(e) {
		if (e.target.classList.contains('flosc-member-remove-menu-item')) {
			e.target.closest('.flosc-member-menu-row').remove();
			renumber();
		}
	});
})();


/* member-levels */
jQuery(document).ready(function($) {
	var config = window.floscMemberLevelsConfig || {};

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
	var categoryOptions = String(config.categoryOptions || '<option value="">— Select —</option>');

	var tagOptions = String(config.tagOptions || '<option value="">— Select —</option>');

	var levelOptions = String(config.levelOptions || '<option value="">— Any Member —</option>');

	function buildContentField(type) {
		if (type === 'post' || type === 'page') {
			return '<input type="text" name="protection_value[]" class="regular-text flosc-protection-value" placeholder="Post/Page ID">';
		} else if (type === 'tag') {
			return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + tagOptions + '</select>';
		}
		// Default: category
		return '<select name="protection_value[]" class="flosc-protection-value flosc-width-full">' + categoryOptions + '</select>';
	}

	var vgmOptions = String(config.vgmOptions || '');
	var depthOptions = String(config.depthOptions || '');

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


/* chat-logs session view */
jQuery(function($) {
		var config = window.floscChatLogsConfig || {};
		var nonce = String(config.nonce || '');
		var archiveOperation = String(config.archiveOperation || 'archive');

		function getSelectedSessions() {
			return $('.flosc-session-select:checked').map(function() {
				var $checkbox = $(this);
				return {
					by: String($checkbox.data('by') || ''),
					value: String($checkbox.data('value') || '')
				};
			}).get().filter(function(item) {
				return item.by !== '' && item.value !== '';
			});
		}

		function updateSelectionStatus() {
			var count = getSelectedSessions().length;
			$('#flosc-session-selection-status').text(count + ' selected');
			var total = $('.flosc-session-select').length;
			$('#flosc-session-select-all').prop('checked', total > 0 && count === total);
		}

		function submitDownload(sessions) {
			if (!sessions.length) {
				alert('Select at least one conversation to download.');
				return;
			}

			$('#flosc-session-download-payload').val(JSON.stringify(sessions));
			$('#flosc-session-download-form').trigger('submit');
		}

		function manageSessions(operation, sessions) {
			if (!sessions.length) {
				alert('Select at least one conversation first.');
				return;
			}

			var message = 'archive';
			if (operation === 'restore') {
				message = 'restore';
			} else if (operation === 'delete') {
				message = 'delete';
			}

			if (!confirm('Are you sure you want to ' + message + ' the selected conversation' + (sessions.length === 1 ? '' : 's') + '?')) {
				return;
			}

			$.post(ajaxurl, {
				action: 'flosc_manage_chat_sessions',
				nonce: nonce,
				flow_id: String(config.flowId || ''),
				operation: operation,
				sessions: JSON.stringify(sessions)
			}, function(res) {
				if (res && res.success) {
					location.reload();
				} else {
					alert((res && res.data && res.data.message) || 'Chat log action failed.');
				}
			}).fail(function() {
				alert('Chat log action failed.');
			});
		}

		$(document).on('click', '.flosc-session-control, .flosc-session-bulk-checkall input', function(e) {
			e.stopPropagation();
		});

		$(document).on('change', '.flosc-session-select', updateSelectionStatus);
		$('#flosc-session-select-all').on('change', function() {
			$('.flosc-session-select').prop('checked', this.checked);
			updateSelectionStatus();
		});

		$('#flosc-session-download-selected').on('click', function() {
			submitDownload(getSelectedSessions());
		});

		$('#flosc-session-archive-selected').on('click', function() {
			manageSessions(archiveOperation, getSelectedSessions());
		});

		$('#flosc-session-delete-selected').on('click', function() {
			manageSessions('delete', getSelectedSessions());
		});

		// Delete an entire conversation. stopPropagation so the click doesn't also
		// toggle the <details> it lives inside.
		$(document).on('click', '.flosc-session-delete', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			var $card = $btn.closest('.flosc-session');
			if (!confirm('Delete this entire conversation and all its messages? This cannot be undone.')) return;
			$btn.prop('disabled', true).text('Deleting…');
			$.post(ajaxurl, {
				action: 'flosc_delete_chat_session',
				nonce: nonce,
				by: $btn.data('by'),
				value: String($btn.data('value')),
				flow_id: $btn.data('flow')
			}, function(res) {
				if (res && res.success) {
					$card.slideUp(150, function() { $card.remove(); });
				} else {
					$btn.prop('disabled', false).text('Delete');
					alert('Delete failed.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Delete');
				alert('Delete failed.');
			});
		});

		$(document).on('click', '.flosc-session-archive', function(e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			manageSessions(String($btn.data('operation') || archiveOperation), [{
				by: String($btn.data('by') || ''),
				value: String($btn.data('value') || '')
			}]);
		});

		$(document).on('click', '.flosc-session-download', function(e) {
			e.stopPropagation();
		});

		$('#flosc-sessions-refresh').on('click', function() { location.reload(); });

		// Admin joins the conversation: post a pale-green "(admin)" line at the bottom.
		function floscSendAdminJoin($btn) {
			var $box = $btn.closest('.flosc-admin-join');
			var $input = $box.find('.flosc-admin-join-input');
			var as = $box.find('.flosc-admin-join-as').val() || 'admin';
			var text = ($input.val() || '').trim();
			if (text === '') return;
			$btn.prop('disabled', true).text('Sending…');
			$.post(ajaxurl, {
				action: 'flosc_admin_join',
				nonce: nonce,
				session_id: String($btn.data('session')),
				flow_id: $btn.data('flow'),
				as: as,
				text: text
			}, function(res) {
				$btn.prop('disabled', false).text('Send');
				if (res && res.success) {
					var $thread = $box.closest('.flosc-session').find('.flosc-session-thread');
					var safe = $('<div>').text(res.data.text).html();
					var who = $('<div>').text(res.data.name).html();
					if (res.data.as === 'bot') {
						// Posted as the bot — render like a normal assistant message.
						$thread.append(
							'<div class="flosc-msg flosc-msg-ai">' +
							'<div class="flosc-msg-meta"><span class="flosc-msg-who">AI</span></div>' +
							'<div class="flosc-msg-body">' + safe + '</div></div>'
						);
					} else {
						$thread.append(
							'<div class="flosc-msg flosc-msg-admin">' +
							'<div class="flosc-msg-meta"><span class="flosc-msg-who">' + who + ' <em>(admin)</em></span></div>' +
							'<div class="flosc-msg-body">' + safe + '</div></div>'
						);
					}
					$input.val('');
				} else {
					alert((res && res.data && res.data.message) || 'Could not post the message.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Send');
				alert('Could not post the message.');
			});
		}
		$(document).on('click', '.flosc-admin-join-send', function() { floscSendAdminJoin($(this)); });
		$(document).on('keydown', '.flosc-admin-join-input', function(e) {
			if (e.key === 'Enter') { e.preventDefault(); floscSendAdminJoin($(this).closest('.flosc-admin-join').find('.flosc-admin-join-send')); }
		});

		function floscAssignTokens($btn) {
			var $box = $btn.closest('.flosc-admin-assign-tokens');
			var $amountInput = $box.find('.flosc-admin-token-amount');
			var amount = parseInt(($amountInput.val() || '').trim(), 10);
			if (!Number.isFinite(amount) || amount <= 0) {
				alert('Enter a positive token amount.');
				return;
			}

			$btn.prop('disabled', true).text('Assigning...');
			$.post(ajaxurl, {
				action: 'flosc_admin_assign_tokens',
				nonce: nonce,
				session_id: String($btn.data('session')),
				flow_id: $btn.data('flow'),
				amount: amount
			}, function(res) {
				$btn.prop('disabled', false).text('Assign Tokens');
				if (res && res.success) {
					var label = res.data?.formatted || String(res.data?.balance || 0);
					alert('Assigned ' + amount + ' tokens. New balance: ' + label);
					$amountInput.val('');
				} else {
					alert((res && res.data && res.data.message) || 'Token assignment failed.');
				}
			}).fail(function() {
				$btn.prop('disabled', false).text('Assign Tokens');
				alert('Token assignment failed.');
			});
		}
		$(document).on('click', '.flosc-admin-assign-send', function() { floscAssignTokens($(this)); });
		$(document).on('keydown', '.flosc-admin-token-amount', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				floscAssignTokens($(this).closest('.flosc-admin-assign-tokens').find('.flosc-admin-assign-send'));
			}
		});

		// Clear old logs (shared with the flat view).
		$('#flosc-log-clear-btn').on('click', function() {
			if (!confirm('Clear chat logs older than 30 days?')) return;
			$.post(ajaxurl, { action: 'flosc_clear_chat_logs', nonce: nonce, days: 30 }, function(res) {
				if (res.success) {
					alert('Cleared ' + res.data.deleted + ' old log entries. ' + res.data.remaining + ' remain.');
					location.reload();
				}
			});
		});

		updateSelectionStatus();
	});


/* chat-logs flat view */
jQuery(function($) {
		var config = window.floscChatLogsConfig || {};
		var maxId = parseInt(config.maxId || 0, 10);
		var selectedUserId = parseInt(config.selectedUserId || 0, 10);
		var pollTimer = null;
		var nonce = String(config.nonce || '');
		var floscFlowId = String(config.flowId || ''); // scope the 5s live poll to the selected flow

		function startPolling() {
			if (pollTimer) return;
			pollTimer = setInterval(fetchNewLogs, 5000);
		}

		function stopPolling() {
			if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
		}

		function fetchNewLogs() {
			var phase = $('#flosc-log-filter-phase').val();
			$.post(ajaxurl, {
				action: 'flosc_get_chat_logs',
				nonce: nonce,
				since_id: maxId,
				phase: phase,
				user_id: selectedUserId,
				flow_id: floscFlowId,
				limit: 50
			}, function(res) {
				if (!res.success) return;
				$('#flosc-log-count').text(res.data.total);
				if (res.data.logs.length > 0) {
					$('#flosc-log-empty-row').remove();
					res.data.logs.forEach(function(log) {
						if (parseInt(log.id) > maxId) {
							maxId = parseInt(log.id);
						}
						var row = buildRow(log);
						$('#flosc-chat-logs-body').prepend(row);
					});
				}
			});
		}

		function buildRow(log) {
			var time = log.timestamp ? log.timestamp.substring(11, 19) : '';
			var date = log.timestamp ? log.timestamp.substring(0, 10) : '';
			// user_tier is the VGM tier the turn was answered at. Rows written
			// before the column existed have none, and are shown exactly as
			// they were before: a blank tier is unknown, not a Visitor.
			var tier = log.user_tier || '';
			var user = log.user_id > 0 ? ('User #' + log.user_id) : 'Visitor';
			if (tier) { user += ' \u00b7 ' + tier.charAt(0).toUpperCase() + tier.slice(1); }
			var source = log.response_source || 'ivr';
			var provider = log.provider || '';
			var chain = log.chain_detail || '';
			var meta = source;
			if (provider && provider !== source) meta += ' / ' + provider;
			if (chain) meta += ' (' + chain + ')';

			var msgPreview = $('<span>').text(log.user_message || '').html();
			var respPreview = $('<span>').text((log.ai_response || '').substring(0, 200)).html();
			if ((log.ai_response || '').length > 200) respPreview += '…';

			return '<tr class="flosc-log-row-new" data-id="' + log.id + '">' +
				'<td class="flosc-log-col-time"><abbr title="' + date + '">' + time + '</abbr></td>' +
				'<td class="flosc-log-col-user">' + user + '</td>' +
				'<td class="flosc-log-col-phase"><span class="flosc-log-phase-badge">' + (log.phase || '') + '</span></td>' +
				'<td class="flosc-log-col-message">' + msgPreview + '</td>' +
				'<td class="flosc-log-col-response">' + respPreview + '</td>' +
				'<td class="flosc-log-col-meta">' + meta + '</td>' +
				'<td class="flosc-log-col-ms">' + (log.response_time_ms || 0) + '</td>' +
				'<td class="flosc-log-col-rating">' +
					'<div class="flosc-log-rating" data-log-id="' + log.id + '">' +
					'<input type="number" min="-10" max="10" step="1" value="0" ' +
						'class="flosc-rating-input flosc-rating-neutral" title="-10 (worst) to +10 (best)">' +
					'<input type="text" value="" class="flosc-rating-note" placeholder="Note">' +
					'<button type="button" class="button button-small flosc-save-rating">Save</button>' +
					'</div>' +
				'</td>' +
				'</tr>';
		}

		// Auto-refresh toggle
		$('#flosc-log-auto-refresh').on('change', function() {
			this.checked ? startPolling() : stopPolling();
		});

		// Manual refresh
		$('#flosc-log-refresh-btn').on('click', fetchNewLogs);

		// Phase filter triggers immediate refresh
		$('#flosc-log-filter-phase').on('change', function() {
			// Reset maxId to reload all for the new filter
			maxId = 0;
			$('#flosc-chat-logs-body').empty();
			fetchNewLogs();
		});

		// Clear old logs
		$('#flosc-log-clear-btn').on('click', function() {
			if (!confirm('Clear chat logs older than 30 days?')) return;
			$.post(ajaxurl, {
				action: 'flosc_clear_chat_logs',
				nonce: nonce,
				days: 30
			}, function(res) {
				if (res.success) {
					alert('Cleared ' + res.data.deleted + ' old log entries. ' + res.data.remaining + ' remain.');
					location.reload();
				}
			});
		});

		// v1.9.5: Save rating — delegated click handler for dynamic rows
		$(document).on('click', '.flosc-save-rating', function() {
			var $widget = $(this).closest('.flosc-log-rating');
			var logId = $widget.data('log-id');
			var rating = parseInt($widget.find('.flosc-rating-input').val()) || 0;
			var note = $widget.find('.flosc-rating-note').val() || '';
			var $btn = $(this);

			$btn.text('Saving...').prop('disabled', true);

			$.post(ajaxurl, {
				action: 'flosc_rate_log',
				nonce: nonce,
				log_id: logId,
				rating: rating,
				note: note
			}, function(res) {
				if (res.success) {
					$btn.text('Saved!');
					// Update color class
					var $input = $widget.find('.flosc-rating-input');
					$input.removeClass('flosc-rating-positive flosc-rating-negative flosc-rating-neutral');
					if (rating > 0) $input.addClass('flosc-rating-positive');
					else if (rating < 0) $input.addClass('flosc-rating-negative');
					else $input.addClass('flosc-rating-neutral');
					setTimeout(function() { $btn.text('Save').prop('disabled', false); }, 1500);
				} else {
					$btn.text('Error').prop('disabled', false);
				}
			}).fail(function() {
				$btn.text('Error').prop('disabled', false);
			});
		});

		// v1.9.5: Live color update as admin types a score
		$(document).on('change input', '.flosc-rating-input', function() {
			var val = parseInt($(this).val()) || 0;
			$(this).removeClass('flosc-rating-positive flosc-rating-negative flosc-rating-neutral');
			if (val > 0) $(this).addClass('flosc-rating-positive');
			else if (val < 0) $(this).addClass('flosc-rating-negative');
			else $(this).addClass('flosc-rating-neutral');
		});

		// Start polling on load
		if ($('#flosc-log-auto-refresh').is(':checked')) {
			startPolling();
		}
	});


/* settings 1 */
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


/* settings 2 */
function switchIVR(ivr) {
		const form = document.getElementById('flosc-working-flow-form');
		const field = document.getElementById('flosc-working-flow-ivr');
		if (!form || !field) {
			return;
		}
		field.value = ivr;
		form.submit();
	}

	(function () {
		const ivrSelect = document.getElementById('ivr-select');
		if (ivrSelect) {
			ivrSelect.addEventListener('change', function () {
				switchIVR(this.value);
			});
		}

		const detailsToggle = document.getElementById('flosc-flow-selector-details-toggle');
		const detailsPanel = document.getElementById('flosc-flow-selector-details-panel');
		if (detailsToggle && detailsPanel) {
			detailsToggle.addEventListener('click', function () {
				const isOpen = detailsToggle.getAttribute('aria-expanded') === 'true';
				const nextOpen = !isOpen;
				detailsToggle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
				detailsToggle.classList.toggle('is-open', nextOpen);
				if (nextOpen) {
					detailsPanel.removeAttribute('hidden');
				} else {
					detailsPanel.setAttribute('hidden', '');
				}
			});
		}

		const tab = String((window.floscSettingsConfig || {}).activeTab || '');
		const tabTitles = {
			'flow': 'Flow',
			'identity': 'Identity',
			'ivr-messages': 'IVR Management',
			'autoprompts': 'AutoPrompt Panel',
			'content': 'Content',
			'member-levels': 'Content',
			'lessons': 'Content',
			'knowledge-base': 'Knowledge Base',
			'trajectories': 'Trajectories',
			'offers': 'Offers',
			'login': 'Register and Login',
			'style': 'Style and Nav',
			'ui': 'Profile Bar',
			'ai': 'AI',
			'token-management': 'Token Management',
			'concierge': 'Concierge',
			'quiz': 'Quiz',
			'email': 'Email',
			'contact-form': 'Contact Form',
			'payments': 'Payments',
			
			'sso': 'SSO',
			'engagement': 'Engagement',
			'chat-logs': 'Chat Logs',
			'administration': 'Administration',
			'documentation': 'Docs',
			'da1': 'DA1'
		};

		const section = tabTitles[tab] || 'Settings';
		document.title = section + ' < Settings < FLOSC';
	})();


/* settings 3 */
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


/* settings 4 */
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

/* payments */
(function () {
	if (!document.querySelector("#flosc-paypal-test")) { return; }
function floscSetPayPalTestResult(result, className, message) {
				var status = document.createElement('span');
				status.className = className;
				status.textContent = message;
				result.textContent = '';
				result.appendChild(status);
			}

			document.getElementById('flosc-paypal-test').addEventListener('click', function() {
				var btn = this;
				var result = document.getElementById('flosc-paypal-test-result');
				btn.disabled = true;
				btn.textContent = 'Testing...';
				result.textContent = '';
				fetch(ajaxurl + '?action=flosc_test_paypal&_wpnonce=' + String((window.floscPaymentsConfig || {}).nonce || '') + '')
					.then(r => r.json())
					.then(data => {
						btn.disabled = false;
						btn.textContent = 'Test PayPal Connection';
						if (data.success) {
							var wh = data.data.webhook;
							var whText = '';
							if (wh && typeof wh === 'object' && wh.id) {
								whText = ' · webhook ' + (wh.created ? 'created' : 'ok') + ' (' + String(wh.id).substring(0, 12) + '…)';
							} else if (typeof wh === 'string' && wh.indexOf('error:') === 0) {
								whText = ' · ' + wh;
							}
							var p0 = data.data.p0a;
							var p0Text = '';
							if (p0 && typeof p0 === 'object') {
								p0Text = p0.pass
									? ' · P0-A security OK'
									: ' · P0-A check failed (unsigned=' + (p0.unsigned || '?') + ', forged=' + (p0.forged || '?') + ')';
							}
							floscSetPayPalTestResult(result, 'flosc-paypal-result-success', '\u2705 Connected — ' + data.data.mode + ' mode, ' + data.data.app_name + whText + p0Text);
						} else {
							floscSetPayPalTestResult(result, 'flosc-paypal-result-error', '\u274c ' + (data.data || 'Connection failed'));
						}
					})
					.catch(() => {
						btn.disabled = false;
						btn.textContent = 'Test PayPal Connection';
						floscSetPayPalTestResult(result, 'flosc-paypal-result-error-lite', '\u274c Network error');
					});
			});
}());


/* da1 */
(function () {
	if (!document.querySelector("#da1-catalog-form")) { return; }
(function () {
	var COLUMNS = (window.floscDa1Config || {}).columns || [];
	var NCOLS = parseInt((window.floscDa1Config || {}).columnCount || 0, 10);
	var MULTILINE = (window.floscDa1Config || {}).multiline || [];

	function colIndex(name) {
		return COLUMNS.indexOf(name);
	}

	function nextIndex() {
		return document.querySelectorAll('#da1-tbody .da1-row').length;
	}

	function nextParentKey() {
		var iRow = colIndex('Row Key');
		var maxVal = 0;
		document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
			var input = tr.querySelector('[name*="[' + iRow + ']"]');
			if (!input) return;
			var v = (input.value || '').trim();
			if (/^[0-9]+$/.test(v)) {
				var n = parseInt(v, 10);
				if (n > maxVal) maxVal = n;
			}
		});
		return String(maxVal + 1);
	}

	function nextChildKey(parentKey) {
		var iRow = colIndex('Row Key');
		var maxSuffix = 0;
		var pattern = new RegExp('^' + parentKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\.(\\d+)$');
		document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
			var input = tr.querySelector('[name*="[' + iRow + ']"]');
			if (!input) return;
			var m = (input.value || '').trim().match(pattern);
			if (m) {
				var s = parseInt(m[1], 10);
				if (s > maxSuffix) maxSuffix = s;
			}
		});
		return parentKey + '.' + String(maxSuffix + 1);
	}

	function createInput(ci, name, value) {
		if (MULTILINE.indexOf(ci) !== -1) {
			var ta = document.createElement('textarea');
			ta.name = name;
			ta.rows = 3;
			ta.className = 'flosc-da1-input flosc-da1-textarea';
			ta.value = value || '';
			return ta;
		}
		var inp = document.createElement('input');
		inp.type = 'text';
		inp.name = name;
		inp.className = 'flosc-da1-input';
		inp.value = value || '';
		return inp;
	}

	function makeRow(seed) {
		var ri = nextIndex();
		var tr = document.createElement('tr');
		tr.className = 'da1-row flosc-da1-row';

		for (var ci = 0; ci < NCOLS; ci++) {
			var td = document.createElement('td');
			td.className = 'flosc-da1-cell';
			var name = 'da1_rows[' + ri + '][' + ci + ']';
			var col = COLUMNS[ci];
			var val = (seed && seed[col]) ? seed[col] : '';
			td.appendChild(createInput(ci, name, val));
			tr.appendChild(td);
		}

		var tdBtn = document.createElement('td');
		tdBtn.className = 'flosc-da1-cell flosc-da1-cell-actions';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'da1-open-modal button button-small flosc-da1-edit-btn';
		btn.textContent = 'Edit';
		tdBtn.appendChild(btn);
		tr.appendChild(tdBtn);

		return tr;
	}

	function readRowObj(tr) {
		var obj = {};
		COLUMNS.forEach(function (col, ci) {
			var f = tr.querySelector('[name*="[' + ci + ']"]');
			obj[col] = f ? f.value : '';
		});
		return obj;
	}

	function addEntryRow() {
		var rowKey = nextParentKey();
		var seed = {
			'Row Key': rowKey,
			'Parent Key': '',
			'Item Type': 'item',
			'Status': 'active',
			'VGM': 'Visitor Guest Member',
			'Flow Scope': 'all'
		};
		var tr = makeRow(seed);
		document.getElementById('da1-tbody').appendChild(tr);
		openModal(tr);
	}

	function addRelatedRowFrom(baseTr) {
		var data = readRowObj(baseTr);
		var parent = (data['Parent Key'] || '').trim();
		var rowKey = (data['Row Key'] || '').trim();
		if (rowKey === '') {
			rowKey = nextParentKey();
			var iRowKey = colIndex('Row Key');
			var keyInput = baseTr.querySelector('[name*="[' + iRowKey + ']"]');
			if (keyInput) {
				keyInput.value = rowKey;
			}
		}
		var parentKey = parent !== '' ? parent : rowKey;
		if (parentKey === '') {
			alert('Entry key is required before adding a child entry.');
			return;
		}
		var seed = {
			'Row Key': nextChildKey(parentKey),
			'Parent Key': parentKey,
			'Catalog Key': data['Catalog Key'] || '',
			'Item Type': data['Item Type'] || 'item',
			'Flow Scope': data['Flow Scope'] || 'all',
			'VGM': data['VGM'] || 'Visitor Guest Member',
			'Delivery Instruction': data['Delivery Instruction'] || 'intent match',
			'Delivery Rule': data['Delivery Rule'] || 'preference',
			'Fallback Order': data['Fallback Order'] || 'text',
			'Status': 'active'
		};
		var tr = makeRow(seed);
		document.getElementById('da1-tbody').appendChild(tr);
		openModal(tr);
	}

	var overlay = null;
	var body = null;
	var titleNode = null;
	var activeRow = null;

	function ensureModal() {
		if (overlay) return;
		overlay = document.createElement('div');
		overlay.className = 'flosc-da1-modal-overlay';
		overlay.id = 'da1-modal-overlay';
		overlay.innerHTML = [
			'<div class="flosc-da1-modal">',
			'<div class="flosc-da1-modal-header">',
			'<span id="da1-modal-title">Edit Row</span>',
			'<button type="button" id="da1-modal-close" class="flosc-da1-modal-close">&times;</button>',
			'</div>',
			'<div id="da1-modal-body" class="flosc-da1-modal-body"></div>',
			'<div class="flosc-da1-modal-footer">',
			'<button type="button" id="da1-modal-delete" class="flosc-da1-modal-delete">Delete this row</button>',
			'<div class="flosc-da1-modal-actions">',
			'<button type="button" id="da1-modal-cancel" class="button">Cancel</button>',
			'<button type="button" id="da1-modal-save" class="button button-primary">Save</button>',
			'</div>',
			'</div>',
			'</div>'
		].join('');
		document.body.appendChild(overlay);
		body = document.getElementById('da1-modal-body');
		titleNode = document.getElementById('da1-modal-title');

		document.getElementById('da1-modal-save').addEventListener('click', saveModal);
		document.getElementById('da1-modal-cancel').addEventListener('click', closeModal);
		document.getElementById('da1-modal-close').addEventListener('click', closeModal);
		document.getElementById('da1-modal-delete').addEventListener('click', function () {
			if (activeRow) {
				activeRow.remove();
			}
			closeModal();
		});
		overlay.addEventListener('click', function (e) {
			if (e.target === overlay) {
				closeModal();
			}
		});
	}

	function getRowFields(tr) {
		return Array.prototype.slice.call(tr.querySelectorAll('input[name*="da1_rows"], textarea[name*="da1_rows"]'));
	}

	function openModal(tr) {
		ensureModal();
		activeRow = tr;
		body.innerHTML = '';
		var fields = getRowFields(tr);
		var title = '';
		var tIdx = colIndex('Title');
		if (tIdx >= 0 && fields[tIdx]) {
			title = fields[tIdx].value.trim();
		}
		titleNode.textContent = title || 'Edit Row';

		COLUMNS.forEach(function (col, ci) {
			var wrap = document.createElement('div');
			wrap.className = 'flosc-da1-modal-field';
			var lbl = document.createElement('label');
			lbl.className = 'flosc-da1-modal-label';
			lbl.setAttribute('for', 'da1m-' + ci);
			lbl.textContent = col;
			wrap.appendChild(lbl);

			var input;
			if (MULTILINE.indexOf(ci) !== -1) {
				input = document.createElement('textarea');
				input.rows = 6;
				input.className = 'flosc-da1-modal-input flosc-da1-modal-textarea';
			} else {
				input = document.createElement('input');
				input.type = 'text';
				input.className = 'flosc-da1-modal-input';
			}
			input.id = 'da1m-' + ci;
			input.setAttribute('data-ci', String(ci));
			input.value = fields[ci] ? fields[ci].value : '';
			wrap.appendChild(input);

			if (col === 'Status') {
				var hint = document.createElement('p');
				hint.className = 'flosc-da1-modal-hint';
				hint.textContent = 'Allowed values: active, paused.';
				wrap.appendChild(hint);
			}
			body.appendChild(wrap);
		});

		overlay.style.display = 'flex';
	}

	function saveModal() {
		if (!activeRow) return;
		var fields = getRowFields(activeRow);
		body.querySelectorAll('[data-ci]').forEach(function (el) {
			var ci = parseInt(el.getAttribute('data-ci'), 10);
			if (fields[ci]) {
				fields[ci].value = el.value;
			}
		});
		closeModal();
	}

	function closeModal() {
		if (overlay) {
			overlay.style.display = 'none';
		}
		activeRow = null;
	}

	var btnAddEntry = document.getElementById('da1-add-entry-bottom');
	if (btnAddEntry) {
		btnAddEntry.addEventListener('click', addEntryRow);
	}

	document.getElementById('da1-tbody').addEventListener('click', function (e) {
		var row = e.target.closest('.da1-row');
		if (row) {
			document.querySelectorAll('#da1-tbody .da1-row.is-da1-active-row').forEach(function (r) {
				r.classList.remove('is-da1-active-row');
			});
			row.classList.add('is-da1-active-row');
		}
		var edit = e.target.closest('.da1-open-modal');
		if (edit) {
			openModal(edit.closest('.da1-row'));
			return;
		}
		var plus = e.target.closest('.da1-add-related');
		if (plus) {
			addRelatedRowFrom(plus.closest('.da1-row'));
		}
	});

	// Save: serialize the whole table into one JSON field and disable the
	// per-cell inputs, so the POST carries a few fields instead of thousands
	// (PHP max_input_vars). Works for a catalog of any size.
	Array.prototype.forEach.call(document.querySelectorAll('.da1-save-trigger'), function (btn) {
		btn.addEventListener('click', function () {
			var form = document.getElementById('da1-catalog-form');
			if (!form) return;
			var rows = [];
			document.querySelectorAll('#da1-tbody .da1-row').forEach(function (tr) {
				var cells = [];
				tr.querySelectorAll('[name^="da1_rows"]').forEach(function (f) { cells.push(f.value); });
				rows.push(cells);
			});
			var payload = form.querySelector('input[name="da1_payload"]');
			if (payload) { payload.value = JSON.stringify({ columns: COLUMNS, rows: rows }); }
			form.querySelectorAll('[name^="da1_rows"], [name^="da1_columns"]').forEach(function (el) { el.disabled = true; });
			form.submit();
		});
	});
})();
}());


/* chat-styling */
(function () {
	if (!document.querySelector("#flow_bubble_style")) { return; }
jQuery(document).ready(function($) {
	// Bubble style data
	var bubbleStyles = (window.floscChatStylingConfig || {}).bubbleStyles || {};
	
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
}());


/* login-registration */
(function () {
	if (!document.querySelector("#flosc-send-guest-link-btn")) { return; }
(function() {
	document.getElementById('flosc-send-guest-link-btn')?.addEventListener('click', function() {
		const emailEl  = document.getElementById('flosc-send-guest-link-email');
		const forceEl  = document.getElementById('flosc-send-guest-link-force');
		const resultEl = document.getElementById('flosc-send-guest-link-result');
		const btn      = this;
		const email    = emailEl.value.trim();
		const sendLabel = String((window.floscLoginRegistrationConfig || {}).sendLabel || 'Send access link');

		if (!email) { emailEl.focus(); return; }

		btn.disabled    = true;
		btn.textContent = 'Sending...';
		resultEl.style.display = 'none';

		const data = new FormData();
		data.append('action', 'flosc_send_guest_link');
		data.append('nonce',  String((window.floscLoginRegistrationConfig || {}).nonce || ''));
		data.append('email',  email);
		data.append('ivr',    String((window.floscLoginRegistrationConfig || {}).ivr || ''));
		if (forceEl && forceEl.checked) {
			data.append('force', '1');
		}

		fetch(ajaxurl, { method: 'POST', body: data })
			.then(r => r.json())
			.then(res => {
				resultEl.style.display = 'block';
				if (res.success) {
					resultEl.style.color = '#1a7f37';
					resultEl.textContent = '✓ ' + res.data.message;
					emailEl.value = '';
				} else {
					resultEl.style.color = '#d63638';
					resultEl.textContent = '✗ ' + (res.data?.message || 'Send failed.');
				}
			})
			.catch(() => {
				resultEl.style.display  = 'block';
				resultEl.style.color    = '#d63638';
				resultEl.textContent    = '✗ Request failed — check your connection.';
			})
			.finally(() => {
				btn.disabled    = false;
				btn.textContent = sendLabel;
			});
	});
})();
}());


/* sso */
(function () {
	if (!document.querySelector("#flosc-test-sso")) { return; }
(function() {
	var nonce = String((window.floscSsoConfig || {}).nonce || '');
	var ajaxUrl = String((window.floscSsoConfig || {}).ajaxUrl || '');
	// v1.5.0: Pass flow_id so test reads per-flow SSO settings
	var flowId = String((window.floscSsoConfig || {}).flowId || '');

	document.querySelectorAll('.flosc-test-connection-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var provider = this.dataset.provider;
			var statusEl = document.getElementById('flosc-test-status-' + provider);
			var resultsEl = document.getElementById('flosc-test-results-' + provider);

			// Disable button, show loading
			btn.disabled = true;
			btn.textContent = '⏳ Testing...';
			statusEl.textContent = 'Connecting to ' + provider + '...';
			statusEl.style.color = '#666';
			resultsEl.style.display = 'none';
			resultsEl.innerHTML = '';

			var formData = new FormData();
			formData.append('action', 'flosc_test_sso_connection');
			formData.append('nonce', nonce);
			formData.append('provider', provider);
			formData.append('flow_id', flowId);

			fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
				.then(function(r) { return r.json(); })
				.then(function(resp) {
					btn.disabled = false;
					btn.textContent = '🔌 Test Connection';

					if (!resp.success) {
						statusEl.textContent = '❌ Broken';
						statusEl.style.color = '#d63638';
						return;
					}

					var data = resp.data;
					var html = '';

					data.checks.forEach(function(check) {
						var cls = check.pass ? 'pass' : 'fail';
						var icon = check.pass ? '✅' : '❌';
						html += '<div class="flosc-test-check ' + cls + '">'
							+ '<span class="flosc-test-check-icon">' + icon + '</span>'
							+ '<span class="flosc-test-check-label">' + escHtml(check.label) + '</span>'
							+ '<span class="flosc-test-check-detail">' + escHtml(check.detail) + '</span>'
							+ '</div>';
					});

					if (data.all_pass) {
						html += '<div class="flosc-test-summary all-pass">✅ Live</div>';
						statusEl.textContent = '✅ Live';
						statusEl.style.color = '#00a32a';
					} else {
						html += '<div class="flosc-test-summary has-fail">❌ Broken</div>';
						statusEl.textContent = '❌ Broken';
						statusEl.style.color = '#d63638';
					}

					resultsEl.innerHTML = html;
					resultsEl.style.display = 'block';
				})
				.catch(function(err) {
					btn.disabled = false;
					btn.textContent = '🔌 Test Connection';
					statusEl.textContent = '❌ Network error: ' + err.message;
					statusEl.style.color = '#d63638';
				});
		});
	});

	function escHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}
})();
}());


/* ivr-messages API test */
(function () {
	if (!document.querySelector("#test-api-btn")) { return; }
function floscAppendAPIResultNode(parent, tagName, text) {
	const node = tagName ? document.createElement(tagName) : document.createTextNode(String(text));
	if (tagName) {
		node.textContent = String(text);
	}
	parent.appendChild(node);
	return node;
}

function floscAppendAPIResultBreaks(parent, count) {
	for (let i = 0; i < count; i++) {
		parent.appendChild(document.createElement('br'));
	}
}

function floscBeginAPIResult(parent, icon, title) {
	parent.textContent = '';
	floscAppendAPIResultNode(parent, '', icon + ' ');
	floscAppendAPIResultNode(parent, 'strong', title);
}

function floscAppendAPIResultLine(parent, label, value) {
	floscAppendAPIResultNode(parent, 'strong', label);
	floscAppendAPIResultNode(parent, '', ' ' + String(value));
	floscAppendAPIResultBreaks(parent, 1);
}

function floscAppendAPIResponseDetails(parent, data) {
	const details = document.createElement('details');
	floscAppendAPIResultNode(details, 'summary', 'Full response');
	floscAppendAPIResultNode(details, 'pre', JSON.stringify(data, null, 2));
	parent.appendChild(details);
}

function floscTestAPI() {
	const resultDiv = document.getElementById('flosc-api-test-result');
	const btn = document.getElementById('flosc-test-api');
	
	resultDiv.style.display = 'block';
	resultDiv.style.background = '#e9ecef';
	resultDiv.textContent = '⏳ Testing API endpoint...';
	btn.disabled = true;
	
	const apiUrl = String((window.floscIvrMessagesConfig || {}).apiUrl || '');
	
	fetch(apiUrl)
		.then(response => response.json())
		.then(data => {
			btn.disabled = false;
			
			if (data.success && data.messages) {
				const msgCount = data.messages.length;
				const msgNames = data.messages.map(m => m.name || 'unnamed').join(', ');
				
				if (msgCount > 0) {
					resultDiv.style.background = '#d4edda';
					floscBeginAPIResult(resultDiv, '✅', 'API Working!');
					floscAppendAPIResultBreaks(resultDiv, 2);
					floscAppendAPIResultLine(resultDiv, 'Messages returned:', msgCount);
					floscAppendAPIResultLine(resultDiv, 'Names:', msgNames);
					floscAppendAPIResultLine(resultDiv, 'User context:', JSON.stringify(data.user_context));
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResponseDetails(resultDiv, data);
				} else {
					resultDiv.style.background = '#fff3cd';
					floscBeginAPIResult(resultDiv, '⚠️', 'API responded but returned 0 messages');
					floscAppendAPIResultBreaks(resultDiv, 2);
					floscAppendAPIResultNode(resultDiv, '', 'This usually means condition evaluation is filtering everything out.');
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResultLine(resultDiv, 'User context:', JSON.stringify(data.user_context));
					floscAppendAPIResultBreaks(resultDiv, 1);
					floscAppendAPIResultNode(resultDiv, '', 'Check that conditions like "is_visitor" are being evaluated correctly.');
				}
			} else {
				resultDiv.style.background = '#f8d7da';
				floscBeginAPIResult(resultDiv, '❌', 'API Error');
				floscAppendAPIResultBreaks(resultDiv, 1);
				floscAppendAPIResultNode(resultDiv, 'pre', JSON.stringify(data, null, 2));
			}
		})
		.catch(error => {
			btn.disabled = false;
			resultDiv.style.background = '#f8d7da';
			floscBeginAPIResult(resultDiv, '❌', 'Fetch failed:');
			floscAppendAPIResultNode(resultDiv, '', ' ' + error.message);
		});
}

/*
 * There is no click listener here on purpose.
 *
 * This page used to carry its own document-level [data-flosc-action] handler
 * alongside the shared one in assets/js/flosc-admin-events.js, which is
 * enqueued on every FLOSC admin screen. Both matched the same element and both
 * called the same function, so one click ran floscToggleMsg() twice: the card
 * opened and closed in the same frame and the accordion looked dead. The same
 * double-fire hit toggle-new-editor and test-api-endpoint.
 *
 * flosc-admin-events.js handles all three, plus delete-message generically via
 * data-confirm-message and data-stop-propagation. The change listener for
 * toggle-offer-fields was doubled the same way; it never showed because
 * floscToggleOfferFields() sets the class from an explicit boolean and running
 * it twice lands in the same place. It was one refactor away from behaving
 * like the accordion.
 *
 * The submit listener went the same way, and that one was visible: a form
 * carrying data-confirm-message asked twice, because both handlers called
 * confirm(). Two dialogs for one Delete.
 *
 * Adding a second listener for an action or attribute that
 * flosc-admin-events.js already owns is how all of this returns.
 */
}());


/* ivr-messages upload */
(function () {
	if (!document.querySelector("#flosc-ivr-upload-form")) { return; }
(function () {
		var form = document.getElementById('flosc-ivr-upload-form');
		var zone = document.getElementById('flosc-ivr-dropzone');
		var input = document.getElementById('flosc-ivr-file-input');
		var nameEl = document.getElementById('flosc-ivr-dropzone-filename');
		if (!form || !zone || !input) {
			return;
		}
		function setFile(file) {
			if (!file) {
				return;
			}
			var lower = (file.name || '').toLowerCase();
			if (lower.slice(-3) !== '.md') {
				window.alert('Only .md IVR files are allowed.');
				return;
			}
			try {
				var dt = new DataTransfer();
				dt.items.add(file);
				input.files = dt.files;
			} catch (e) {
				// Older browsers: user must use the file input.
			}
			if (nameEl) {
				nameEl.hidden = false;
				nameEl.textContent = file.name;
			}
			zone.classList.add('is-has-file');
		}
		function onDrag(e) {
			e.preventDefault();
			e.stopPropagation();
		}
		['dragenter', 'dragover'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) {
				onDrag(e);
				zone.classList.add('is-dragover');
			});
		});
		['dragleave', 'drop'].forEach(function (ev) {
			zone.addEventListener(ev, function (e) {
				onDrag(e);
				zone.classList.remove('is-dragover');
			});
		});
		zone.addEventListener('drop', function (e) {
			var files = e.dataTransfer && e.dataTransfer.files;
			if (files && files.length) {
				setFile(files[0]);
			}
		});
		zone.addEventListener('click', function () {
			input.click();
		});
		zone.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' || e.key === ' ') {
				e.preventDefault();
				input.click();
			}
		});
		input.addEventListener('click', function (e) {
			e.stopPropagation();
		});
		input.addEventListener('change', function () {
			if (input.files && input.files[0]) {
				setFile(input.files[0]);
			}
		});
	})();
}());


/* ivr-messages editor */
(function () {
	if (!document.querySelector(".flosc-add-msg-btn")) { return; }
function floscToggleMsg(id) {
	const card = document.getElementById('card-' + id);
	if (!card) return;
	card.classList.toggle('is-open');
}
function floscToggleOfferFields(selectEl, msgId) {
	const panel = document.getElementById('offer-fields-' + msgId);
	if (panel) panel.classList.toggle('flosc-hidden', selectEl.value !== 'offer');
	const cpanel = document.getElementById('concierge-fields-' + msgId);
	if (cpanel) cpanel.classList.toggle('flosc-hidden', selectEl.value !== 'concierge');
}
function floscToggleNewEditor(phaseId, shouldOpen) {
	const editor = document.getElementById('editor-new-' + phaseId);
	const button = document.getElementById('add-btn-' + phaseId);
	if (editor) {
		editor.classList.toggle('flosc-hidden', !shouldOpen);
	}
	if (button) {
		button.classList.toggle('flosc-hidden', !!shouldOpen);
	}
}
// Auto-scroll to expanded message on page load
document.addEventListener('DOMContentLoaded', function() {
	const open = document.querySelector('.flosc-msg-card.is-open');
	if (open) open.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
}());


/* content */
(function () {
	if (!document.querySelector("#flosc-add-content-type")) { return; }
jQuery(document).ready(function($) {
	var config = window.floscContentConfig || {};
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

	var quizOptions = String(config.quizOptions || '');
	var catOptions = String(config.catOptions || '');
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

	var categoryOptions = String(config.categoryOptions || '');
	var tagOptions = String(config.tagOptions || '');
	var levelOptions = String(config.levelOptions || '');
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
	var vgmOptions = String(config.vgmOptions || '');
	var depthOptions = String(config.depthOptions || '');
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
}());


/* ai-configuration */
(function () {
	if (!document.querySelector("#flow_ai_provider")) { return; }
jQuery(document).ready(function($) {
	var floscAiConfig = window.floscAiAdminConfig || {};
	var floscAiLabels = floscAiConfig.labels || {};
	// --- Provider section show/hide ---
	// Which provider refuses which setting is data, declared once in
	// includes/ai/flosc-provider-profiles.php. The tab reads it rather than
	// naming a provider here, so filling in a row there is all it takes.
	// Sampling controls a provider refuses on the same request. Anthropic takes
	// temperature alone and top_p alone, and answers 400 to both together —
	// which is how visitor chat came to fail while the panel showed a request
	// that looked fine. The runtime holds one back; this is so the page says so
	// rather than displaying a request it knows will not be sent as written.
	var floscSamplingExclusive = floscAiConfig.samplingExclusive || {};

	var floscProviderRejects = floscAiConfig.providerRejects || {};

	// Model first, provider second — the same order the PHP resolver uses.
	// Sonnet 4.5 takes temperature and Sonnet 5 refuses it, and both are
	// Anthropic, so hiding the control by provider hid a control that works.
	// floscModelNoteFor already does the longest-prefix model match; this only
	// asks it the question.
	function floscModelRejectsParam(provider, model, param) {
		var note = (typeof floscModelNoteFor === 'function') ? floscModelNoteFor(provider, model) : null;

		if (note) {
			if ((note.accepts || []).indexOf(param) !== -1) { return false; }
			if ((note.refuses || []).indexOf(param) !== -1) { return true; }
		}

		// Same reason as above: this runs during setup, before the map exists.
		var wide = (floscProviderRejects && floscProviderRejects[provider]) || { params: [] };

		return (wide.params || []).indexOf(param) !== -1;
	}

	function updateProviderSections() {
		var selected = $('#flow_ai_provider').val();
		var rejects = floscProviderRejects[selected] || { params: [], note: '' };
		var selectedModelField = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
			? floscModelFieldFor[selected]
			: '';
		var selectedModel = selectedModelField ? String($('#' + selectedModelField).val() || '').trim() : '';
		var noTemperature = floscModelRejectsParam(selected, selectedModel, 'temperature');

		// A control the provider will refuse is not a control. Hide it rather
		// than leave it on screen with a paragraph explaining it does nothing.
		$('#flosc-temperature-row').prop('hidden', noTemperature);
		$('#flosc-temperature-absent').prop('hidden', !noTemperature);
		$('#flosc-temperature-absent-note').text(rejects.note || '');
		// Showing only the selected provider hides the other key fields, which
		// makes storing a second key look impossible. It never was — the keys
		// are kept separately — so this reveals them on request.
		var showAll = $('#flosc-show-all-providers').is(':checked');

		$('.flosc-ai-provider-section').each(function() {
			var provider = $(this).data('provider');
			var keep = (provider === selected) || (showAll && provider !== 'ivr');

			if (keep) {
				$(this).removeClass('is-hidden');
			} else {
				$(this).addClass('is-hidden');
			}
		});
	}
	$('#flow_ai_provider').on('change', updateProviderSections);
	$('#flosc-show-all-providers').on('change', updateProviderSections);
	updateProviderSections();

	var attachSel = $('#flow_personality_library_id');
	var attachNote = $('#flosc-personality-attach-note');
	var attachSaved = attachSel.val();
	var floscAttach = floscAiConfig.attach || { nonce: '', ivr: '', fallback: '' };
	/*
	 * The confirmation has to outlive the reload that this control triggers.
	 *
	 * It used to set the note to "Attached X. Reloading…" and call
	 * location.reload() on the next statement. The browser never painted it, so
	 * what a floscAdmin saw was "Attaching…" and then a page reload — an
	 * indefinite state with no outcome, on the one control where being sure
	 * matters most. The reload itself is needed: the designer below is rendered
	 * from the attached row.
	 *
	 * So the confirmation is stashed before reloading and shown afterwards,
	 * beside a dropdown that now reads the same name. Choose DadJokesDan, reload,
	 * see DadJokesDan attached and a line saying when it was written.
	 */
	var floscAttachStash = 'flosc_attach_confirmation';

	function floscShowStashedAttachConfirmation() {
		if (!attachNote.length) {
			return;
		}
		var raw = null;
		try {
			raw = window.sessionStorage.getItem(floscAttachStash);
			window.sessionStorage.removeItem(floscAttachStash);
		} catch (e) {
			raw = null;
		}
		if (!raw) {
			return;
		}
		try {
			var stash = JSON.parse(raw);
			if (stash && stash.text) {
				attachNote.removeClass('flosc-hidden').addClass('flosc-attach-ok').text(stash.text);
			}
		} catch (e) {
			// A confirmation we cannot read is not worth a broken page.
		}
	}
	floscShowStashedAttachConfirmation();

	attachSel.on('change', function () {
		var nextVal = attachSel.val();
		var nextLabel = attachSel.find('option:selected').text().trim();
		if (!attachNote.length || nextVal === attachSaved) {
			return;
		}
		attachSel.prop('disabled', true);
		attachNote.removeClass('flosc-hidden flosc-attach-ok flosc-attach-bad').text(String(floscAiLabels.attaching || 'Attaching…'));
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			timeout: 20000,
			dataType: 'json',
			data: {
				action: 'flosc_attach_personality',
				nonce: floscAttach.nonce,
				ivr: floscAttach.ivr,
				persona: nextVal
			}
		}).done(function (res) {
			if (res && res.success && res.data) {
				attachSaved = res.data.persona;
				var confirmed = res.data.persona
					? String(floscAiLabels.attached || 'Attached') + ' ' + (res.data.label || nextLabel || res.data.persona)
					: String(floscAiLabels.cleared || 'Attachment cleared');
				if (res.data.saved_at) {
					confirmed += ' · ' + String(floscAiLabels.saved || 'saved') + ' ' + res.data.saved_at;
				}
				try {
					window.sessionStorage.setItem(floscAttachStash, JSON.stringify({ text: confirmed }));
				} catch (e) {
					// Without storage the reload eats the confirmation, which is
					// where this started — so say it here and pause long enough
					// to be read before reloading.
					attachNote.addClass('flosc-attach-ok').text(confirmed);
					window.setTimeout(function () { window.location.reload(); }, 1200);
					return;
				}
				window.location.reload();
			} else {
				attachSel.prop('disabled', false);
				var failed = (res && res.data && res.data.message) ? res.data.message : floscAttach.fallback;
				attachNote.addClass('flosc-attach-bad').text(failed);
			}
		}).fail(function (xhr, textStatus) {
			// The row is stored before the file mirror. A lost or late body
			// still leaves that row in place, so reload onto it instead of
			// sitting on "Attaching…". A refused attach (4xx/5xx) stays here
			// and says why.
			if (textStatus === 'timeout' || (xhr && xhr.status === 200)) {
				window.location.reload();
				return;
			}
			attachSel.prop('disabled', false);
			var msg = floscAttach.fallback;
			if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
				msg = xhr.responseJSON.data.message;
			}
			attachNote.addClass('flosc-attach-bad').text(msg);
		});
	});

	// --- Chaining toggle ---
	$('#flow_ai_enable_chaining').on('change', function() {
		$('#flosc-chain-config').toggle(this.checked);
	});

	// --- The provider's model list, as something to click ---
	// Two lists matter and only one of them works here: what the API key can
	// see at the provider, and what the installed WordPress AI Provider plugin
	// carries. A model can be live at the provider and absent from the plugin's
	// catalog — which is the failure that reads as a bad key and is not one.
	//
	// A list nobody can click is not a choice. The ids go into the datalist for
	// typing, and into a visible row of buttons that fill the field on click.
	var floscModelFieldFor = {
		anthropic: 'flow_ai_anthropic_model',
		openai:    'flow_ai_openai_model',
		xai:       'flow_ai_xai_model',
		gemini:    'flow_ai_gemini_model'
	};

	function floscRenderModelPicker($picker, targetId, models) {
		var $field = $('#' + targetId);

		$picker.empty();

		if (!$field.length || !models || !models.length) {
			$picker.attr('hidden', true);
			return 0;
		}

		var current = String($field.val() || '');
		var heading = models.length + (models.length === 1 ? ' model' : ' models') + ' this key can use';

		$('<p>').addClass('flosc-model-picker__intro').text(heading + '. Click one to use it:').appendTo($picker);

		var $list = $('<div>').addClass('flosc-model-picker__list').appendTo($picker);

		models.forEach(function (m) {
			var $b = $('<button>')
				.attr('type', 'button')
				.addClass('button flosc-model-choice')
				.attr('data-id', m.id)
				.attr('data-target', targetId)
				.text(m.id);

			if (m.label && m.label !== m.id) {
				$('<span>').addClass('flosc-model-choice__label').text(m.label).appendTo($b);
			}

			if (m.id === current) {
				$b.addClass('flosc-model-choice--current');
			}

			$b.appendTo($list);
		});

		$('<p>')
			.addClass('flosc-model-picker__note')
			.text('Clicking one saves it for this flow straight away.')
			.appendTo($picker);

		$picker.removeAttr('hidden');

		// Typing still works, and the suggestions now match reality.
		var listId = $field.attr('list');

		if (listId) {
			var $dl = $('#' + listId).empty();

			models.forEach(function (m) {
				$('<option>').attr('value', m.id).text(m.label || m.id).appendTo($dl);
			});
		}

		return models.length;
	}

	// Choosing from the fetched list has to be the end of the job, so the pick
	// is saved on the spot. A choice that only fills a field, and is lost
	// unless a page-wide Save is found afterwards, is not a choice.
	$(document).on('click', '.flosc-model-choice', function () {
		var $b = $(this);
		var targetId = $b.data('target');
		var id = String($b.data('id'));
		var $field = $('#' + targetId);
		var provider = String(targetId).replace(/^flow_ai_/, '').replace(/_model$/, '');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

		if (!$field.length) { return; }

		$field.val(id).trigger('change').trigger('input');
		$('.flosc-model-choice[data-target="' + targetId + '"]').removeClass('flosc-model-choice--current');
		$b.addClass('flosc-model-choice--current');
		$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving ' + id + '…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_ai_provider_model',
				nonce: String(floscAiConfig.testNonce || ''),
				provider: provider,
				ivr: String(floscAiConfig.ivr || ''),
				model: id
			},
			success: function (response) {
				if (!response || !response.success) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not save the model.');
					return;
				}

				$status.addClass('flosc-save-key-status--ok').text('\u2713 Model saved — ' + id);

				// The test reads saved settings; this model is now one of them.
				if (typeof floscSavedAi === 'object' && floscSavedAi) {
					floscSavedAi[targetId] = id;
				}

				// Describe it now, unasked. What this model can do, and how long
				// a reply it can give, is exactly what an operator needs at the
				// moment they choose it — not after finding another button.
				floscDescribeModel(provider, targetId, id, null, false);
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			}
		});
	});

	// Save one key on its own. The full-page Save still works; this exists so
	// "did the key save?" has an answer next to the field, at the moment it is
	// pasted, instead of a banner at the top of a long tab.
	$('.flosc-save-key').on('click', function () {
		var $btn = $(this);
		var provider = $btn.data('provider');
		var targetId = $btn.data('target');
		var $status = $('.flosc-save-key-status[data-for="' + targetId + '"]');
		var key = String($('#' + targetId).val() || '').trim();

		if (!key) {
			$status.removeClass('flosc-save-key-status--ok')
				.addClass('flosc-model-fetch-status--bad').text('Paste a key first.');
			return;
		}

		$btn.prop('disabled', true);
		$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok').text('Saving…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_ai_provider_key',
				nonce: String(floscAiConfig.testNonce || ''),
				provider: provider,
				ivr: String(floscAiConfig.ivr || ''),
				api_key: key
			},
			success: function (response) {
				if (!response || !response.success) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not save the key.');
					return;
				}

				var d = response.data || {};
				$status.removeClass('flosc-model-fetch-status--bad')
					.addClass('flosc-save-key-status--ok')
					.text('✓ Saved' + (d.suffix ? ' — ends ' + d.suffix : '') + '.');

				// The test reads saved settings; this key is now one of them, so
				// the unsaved-settings guard must stop calling it unsaved.
				if (typeof floscSavedAi === 'object' && floscSavedAi) {
					floscSavedAi[targetId] = $('#' + targetId).val();
				}

				var $line = $('#' + targetId).closest('td').find('.flosc-key-state');

				if ($line.length) {
					$line.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
						.text('Saved on this flow' + (d.suffix ? ' — ends ' + d.suffix : ''));
				}
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// Ask the provider to describe the chosen model. Everything rendered comes
	// from the provider; FLOSC ranks nothing and invents nothing. The real
	// max output also raises the Max Tokens ceiling, which used to be a
	// hardcoded 4096 belonging to no model.
	function floscDescribeModel(provider, targetId, model, $btn, announce) {
		var $detail = $('.flosc-model-detail[data-for="' + targetId + '"]');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');

		if (!model) {
			$status.addClass('flosc-model-fetch-status--bad').text('Choose or type a model first.');
			return;
		}

		if ($btn) { $btn.prop('disabled', true); }

		if (announce) {
			$status.removeClass('flosc-model-fetch-status--bad flosc-save-key-status--ok')
				.text('Asking the provider about ' + model + '…');
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_describe_ai_model',
				nonce: String(floscAiConfig.testNonce || ''),
				provider: provider,
				ivr: String(floscAiConfig.ivr || ''),
				model: model,
				api_key: $('#flow_' + provider + '_api_key').val() || ''
			},
			success: function (response) {
				if (!response || !response.success) {
					$detail.attr('hidden', true).empty();
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not describe the model.');
					return;
				}

				var d = response.data || {};
				var n = function (v) { return Number(v || 0).toLocaleString(); };

				$detail.empty();
				$('<p>').addClass('flosc-model-detail__name')
					.text((d.display_name || d.id) + ' — as described by the provider').appendTo($detail);

				var $ul = $('<ul>').addClass('flosc-model-detail__list').appendTo($detail);
				$('<li>').text('Context window: ' + n(d.max_input_tokens) + ' tokens').appendTo($ul);
				$('<li>').text('Longest reply it can produce: ' + n(d.max_tokens) + ' tokens').appendTo($ul);

				if (d.features && d.features.length) {
					$('<li>').text('Can also: ' + d.features.join(', ')).appendTo($ul);
				}

				if (d.effort_levels && d.effort_levels.length) {
					$('<li>').text('Effort levels: ' + d.effort_levels.join(', ')).appendTo($ul);
				}

				// Let Max Tokens go as high as this model really allows.
				var $mt = $('#flow_ai_max_tokens');

				if ($mt.length && d.max_tokens > 0) {
					$mt.attr('max', d.max_tokens);

					$('<p>').addClass('flosc-model-detail__note')
						.text('Temperature is not listed here because Anthropic does not publish sampling support, and FLOSC does not send it to Claude at all.')
						.appendTo($detail);

					// Say it again where the number is actually typed.
					var current = parseInt($mt.val(), 10) || 0;
					var $mtNote = $('#flosc-max-tokens-live');

					if (!$mtNote.length) {
						$mtNote = $('<p>').attr('id', 'flosc-max-tokens-live').addClass('flosc-key-state');
						$mt.closest('td').append($mtNote);
					}

					if (current > d.max_tokens) {
						$mtNote.removeClass('flosc-key-state--ok').addClass('flosc-key-state--none')
							.text((d.display_name || d.id) + ' accepts at most ' + n(d.max_tokens) +
									' — ' + n(current) + ' is above that. Lower it, or the provider will reject the request.');
					} else {
						$mtNote.removeClass('flosc-key-state--none').addClass('flosc-key-state--ok')
							.text((d.display_name || d.id) + ' accepts up to ' + n(d.max_tokens) +
									' tokens per reply. Yours is ' + n(current) + '.');
					}
				}

				$detail.removeAttr('hidden');

				if (announce) {
					$status.addClass('flosc-save-key-status--ok').text('\u2713 Described by the provider.');
				}
			},
			error: function () {
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () { if ($btn) { $btn.prop('disabled', false); } }
		});
	}

	// Check what was typed before it is saved. The provider decides whether a
	// parameter is real; this only catches what could never reach a provider —
	// a line that is not name: value, or JSON that does not parse.
	// What each parameter does, so the answer is in the field rather than in a
	// provider's documentation in another tab. Never a gate: a name absent from
	// here is still sent, and says so.
	var floscParamRef = floscAiConfig.paramRef || {};

	// The same reference, split by provider, plus the whole-set recipes and the
	// measured per-model notes. All four providers are handed over at once so
	// that changing provider or model re-renders the menu from data already in
	// the page rather than from another round trip.
	var floscParamsByProvider = floscAiConfig.paramsByProvider || {};

	var floscParamRecipes = floscAiConfig.paramRecipes || {};

	var floscParamModelNotes = floscAiConfig.paramModelNotes || {};

	// Where each provider explains one parameter, in its own words. A template
	// taking the parameter name where the anchor scheme has been read off the
	// live page, '' where it has not — and the caller falls back to the
	// provider's page rather than inventing an anchor.
	var floscProviderParamDocs = floscAiConfig.providerParamDocs || {};

	var floscProviderDocs = floscAiConfig.providerDocs || {};

	var floscProviderLabels = {
		anthropic: 'Anthropic',
		openai:    'OpenAI',
		xai:       'xAI',
		gemini:    'Gemini'
	};

	// Locked, because it is a statement of what will be sent. Unlocking it is a
	// deliberate act, and from that point the operator's text is the truth.
	var floscParamsUnlocked = false;

	// Two states, and a way back from each. Editing is not a door that shuts
	// behind you: a save returns the request to rest, locked and complete,
	// with Edit alive again for whoever wants another go at it.
	function floscUnlockParams(focus) {
		if (floscParamsUnlocked) {
			if (focus) { $('#flow_ai_model_params').trigger('focus'); }
			return;
		}

		floscParamsUnlocked = true;
		$('#flow_ai_model_params').prop('readonly', false).removeClass('flosc-params-locked');
		if (focus) { $('#flow_ai_model_params').trigger('focus'); }
		$('#flosc-params-edit').prop('disabled', true).text('Editing…');
		$('#flosc-params-lock-note').text(
			'Editing. What you write is what FLOSC sends — Temperature and Max Tokens above follow it as ' +
			'you type. It saves itself when you click away.'
		);
	}

	function floscLockParams() {
		floscParamsUnlocked = false;
		$('#flow_ai_model_params').prop('readonly', true).addClass('flosc-params-locked');
		$('#flosc-params-edit').prop('disabled', false).text('Edit the request');
		$('#flosc-params-lock-note').text(
			'This is what will be sent. Built from the fields above — click Edit the request to add ' +
			'anything they cannot express.'
		);
	}

	$('#flosc-params-edit').on('click', function () { floscUnlockParams(true); });

	// Compose a first request when there is none. Never replace one that
	// exists: the text in that box is the operator's, and rebuilding it from
	// the fields is what silently dropped a temperature line on a provider
	// known to refuse temperature. Field edits reach the text through
	// floscSetParamValue, which changes one line and leaves the rest alone.
	function floscRebuildPreview() {
		var $box = $('#flow_ai_model_params');

		if (!$box.length || String($box.val() || '').trim() !== '') { return; }

		var provider = $('#flow_ai_provider').val();
		var rejects = (floscProviderRejects[provider] || { params: [] }).params;
		var lines = [];
		var t = String($('#flow_ai_temperature').val() || '').trim();
		var m = String($('#flow_ai_max_tokens').val() || '').trim();

		if (t && rejects.indexOf('temperature') === -1) { lines.push('temperature: ' + t); }
		if (m) { lines.push('max_tokens: ' + m); }

		// Anything already stored that no field represents.
		String($box.data('extra') || '').split(/\r\n|\r|\n/).forEach(function (line) {
			if (line.trim()) { lines.push(line.trim()); }
		});

		$box.val(lines.join('\n'));
		floscCheckParams();
	}

	// Guards the two-way sync between a field and the line that carries it, so
	// writing one does not bounce back and rewrite the other.
	var floscSyncing = false;

	// Set one parameter in the request and leave every other line exactly as
	// the operator wrote it — same name, same place, same ordering. An empty
	// field removes its line rather than sending an empty value.
	function floscSetParamValue(name, value) {
		var $box = $('#flow_ai_model_params');

		if (!$box.length || floscSyncing) { return; }

		floscSyncing = true;

		var current = String($box.val() || '').trim();
		value = String(value).trim();

		if (current.charAt(0) === '{') {
			var obj = null;

			try { obj = JSON.parse(current); } catch (e) { obj = null; }

			if (obj) {
				if (value === '') {
					delete obj[name];
				} else {
					var typed;

					try { typed = JSON.parse(value); } catch (e2) { typed = value; }

					obj[name] = typed;
				}

				$box.val(JSON.stringify(obj, null, 2));
				floscSyncing = false;
				floscCheckParams();
				return;
			}
		}

		var lines = current ? current.split(/\r\n|\r|\n/) : [];
		var found = false;

		lines = lines.filter(function (line) {
			var bit = floscSplitParamLine(line);

			if (!bit || bit.name !== name) { return true; }

			found = true;

			return value !== '';
		}).map(function (line) {
			var bit = floscSplitParamLine(line);

			return (bit && bit.name === name) ? name + ': ' + value : line;
		});

		if (!found && value !== '') {
			lines.push(name + ': ' + value);
		}

		$box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
		floscSyncing = false;
		floscCheckParams();
	}

	$('#flow_ai_temperature').on('input change', function () {
		var provider = floscCurrentProvider();

		// A provider that refuses this one never carries it, typed or not.
		var field = (typeof floscModelFieldFor === 'object' && floscModelFieldFor)
			? floscModelFieldFor[provider]
			: '';
		var model = field ? String($('#' + field).val() || '').trim() : '';

		if (floscModelRejectsParam(provider, model, 'temperature')) {
			return;
		}

		floscSetParamValue('temperature', $(this).val());
	});

	$('#flow_ai_max_tokens').on('input change', function () {
		floscSetParamValue('max_tokens', $(this).val());
	});

	$('#flow_ai_provider').on('change', floscRebuildPreview);

	// ---- Saving Step 2b where it is typed -----------------------------------
	//
	// The page-wide Save at the foot of Settings still writes these. This is
	// the same write, reachable from the controls themselves, and it runs on
	// its own when a value is committed — a green flash on the field that
	// changed, so the answer to "did that take?" is visible without scrolling
	// to find out.

	var floscTuningTimer = null;
	var floscTuningInFlight = false;

	// One slot for the save that has to happen next. A save already running is
	// not a reason to throw the next one away — it is a reason to make it wait.
	// Manual outranks automatic: a deliberate press is stronger intent than a
	// background write, so it takes the slot even from an autosave already in it.
	var floscTuningPending = null;

	// What the last completed save stored, so a no-op can still report a real
	// state instead of leaving the button mid-sentence.
	var floscTuningLastSavedAt = '';
	var floscTuningLastSaveSource = 'manual';

	// Raised on pointerdown, which happens before the textarea loses focus.
	// Asking afterwards whether Save has focus cannot work: pressing Save
	// disables Save, and disabling a focused element takes its focus away — so
	// the check answered "they didn't click Save" about the click that was
	// running it. Intent is captured, never inferred after the fact.
	var floscManualSaveArmed = false;

	function floscQueueTuningSave($flash, source) {
		source = (source === 'manual') ? 'manual' : 'auto';

		if (!floscTuningPending || source === 'manual') {
			floscTuningPending = { source: source, flash: $flash };
		}
	}

	// The save button is the state, rather than a button beside a state: at
	// rest it is a green statement that the tuning is stored, and it turns
	// back into something to press the moment that stops being true.
	function floscTuningState(state, detail, source) {
		var $btn = $('#flosc-save-tuning');
		var $status = $('#flosc-tuning-status');
		var auto = (source === 'auto');

		$btn.removeClass('button-primary flosc-tuning-save--saved flosc-tuning-save--bad')
			.prop('disabled', false);
		$status.attr('class', 'flosc-tuning-status').text('');

		if (state === 'saved') {
			// How the save happened, not whether a timestamp came back. Every
			// successful save returns one, so reading the stamp as the source
			// labelled a deliberate press of Save as something the page did on
			// its own — which is exactly the confidence this is meant to give.
			$btn.addClass('flosc-tuning-save--saved')
				.html(auto ? '&#10003; Autosaved' : '&#10003; Saved')
				.attr('title', auto
					? 'Saved on its own. Press to save again.'
					: 'Saved on this flow. Press to save again.');

			// The stamp sits beside the button rather than inside it: it is a
			// full Michel Time Stamp, and a button that changes width every
			// time it is pressed moves the page under whoever pressed it.
			if (detail) {
				$status.addClass('flosc-tuning-status--ok').text(detail);
			}

			return;
		}

		if (state === 'saving') {
			$btn.prop('disabled', true).text(auto ? 'Autosaving…' : 'Saving…');
			return;
		}

		if (state === 'bad') {
			$btn.addClass('flosc-tuning-save--bad').text('Save Model Tuning')
				.attr('title', 'Not saved.');
			$status.addClass('flosc-tuning-status--bad').text('\u2717 ' + (detail || 'Not saved.'));
			return;
		}

		// Dirty: something changed and has not reached the database yet.
		$btn.addClass('button-primary').text('Save Model Tuning')
			.attr('title', 'Save this flow\u2019s tuning now.');
		$status.text(detail || 'Unsaved changes.');
	}

	// The page was rendered from what is stored, so at load that is exactly
	// what the controls are showing. Nothing was autosaved to get here.
	floscTuningState('saved', '', 'manual');

	// What was last written, so an autosave that would rewrite the identical
	// values does not make a second round trip. A deliberate press always goes.
	var floscTuningLastSaved = '';

	function floscTuningFingerprintOf(provider, temperature, maxTokens, params) {
		return JSON.stringify({
			provider: String(provider || ''),
			temperature: String(temperature || ''),
			max_tokens: String(maxTokens || ''),
			params: String(params || '')
		});
	}

	function floscTuningFingerprint() {
		return floscTuningFingerprintOf(
			floscCurrentProvider(),
			$('#flow_ai_temperature').val(),
			$('#flow_ai_max_tokens').val(),
			$('#flow_ai_model_params').val()
		);
	}

	// One place that schedules an unattended save, so cancelling one is one call.
	function floscScheduleTuningAutosave($field, delay) {
		window.clearTimeout(floscTuningTimer);
		floscTuningTimer = window.setTimeout(function () {
			floscSaveTuning($field, 'auto');
		}, (typeof delay === 'number') ? delay : 1400);
	}

	floscTuningLastSaved = floscTuningFingerprint();

	function floscFlashSaved($el) {
		if (!$el || !$el.length) { return; }

		$el.removeClass('flosc-saved-flash');

		// Reading offsetWidth restarts the CSS transition on an element that
		// was flashed a moment ago; without it a second save shows nothing.
		$el[0].offsetWidth;
		$el.addClass('flosc-saved-flash');

		window.setTimeout(function () { $el.removeClass('flosc-saved-flash'); }, 1600);
	}

	function floscSaveTuning($flash, source) {
		source = (source === 'manual') ? 'manual' : 'auto';

		var provider = floscCurrentProvider();
		var $status = $('#flosc-tuning-status');

		if (!provider || provider === 'ivr') {
			$status.attr('class', 'flosc-tuning-status flosc-tuning-status--bad')
				.text('Pick an AI provider for this flow first.');
			return;
		}

		if (floscTuningInFlight) {
			floscQueueTuningSave($flash, source);
			$('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
				.text(source === 'manual' ? 'Save queued…' : 'Latest changes waiting to save…');
			return;
		}

		// A pause in typing and the blur that follows it are one edit, not two.
		// Writing the same values twice is a wasted round trip and makes the
		// indicator flicker for no reason anyone can see.
		var fingerprint = floscTuningFingerprint();

		// Already stored. Nothing to write — but the button may be sitting in
		// Saving from the call that got us here, so say so rather than
		// returning into silence. No path out of a save may leave it there.
		//
		// The label describes the action that just finished, not the write that
		// happened to be last. Reporting the stored source instead attributed a
		// recipe click — an automatic path — to whatever had saved before it,
		// so clicking Use this could read Saved when nobody had pressed
		// anything. An autosave that finds the work already stored has still
		// done an autosave's job, and says so.
		if (fingerprint === floscTuningLastSaved) {
			floscTuningLastSaveSource = source;

			floscTuningState('saved', floscTuningLastSavedAt, source);
			return;
		}

		floscTuningInFlight = true;
		floscTuningState('saving', '', source);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_save_model_tuning',
				nonce: String(floscAiConfig.testNonce || ''),
				ivr: String(floscAiConfig.ivr || ''),
				provider: provider,
				temperature: String($('#flow_ai_temperature').val() || ''),
				max_tokens: String($('#flow_ai_max_tokens').val() || ''),
				params: String($('#flow_ai_model_params').val() || '')
			},
			success: function (response) {
				if (!response || !response.success) {
					// Almost always a request that will not parse, and the
					// message names the line that broke it. Nothing was written,
					// so nothing goes green and the request stays open for
					// editing — re-locking it here would hide the mistake.
					floscTuningState('bad', (response && response.data && response.data.message) || 'Not saved.', source);
					return;
				}

				var d = response.data;

				// Stamped by the server, which is the machine that did the
				// writing. A browser clock can be wrong by hours, and "saved
				// at" is a claim only the writer can make.
				// What this request stored, taken from the answer rather than
				// from what the browser assumed before sending it.
				floscTuningLastSaved = floscTuningFingerprintOf(
					provider, d.temperature, d.max_tokens, d.params
				);

				// This answer describes the form as it was when the request
				// left. If newer work is already waiting, that answer is now
				// older than the screen, and painting it back would undo an
				// edit the operator can see. Leave the screen alone and let the
				// queued save — which carries the newer values — have the last
				// word, including the last word on the label.
				floscTuningLastSavedAt = d.saved_at || '';
				floscTuningLastSaveSource = source;

				if (floscTuningPending) {
					// This answer is older than the screen, so it must not be
					// painted back over newer work — but it must still hand the
					// button back. The queued save has the last word on the
					// label; until it lands, the page says what is true.
					$('#flosc-tuning-status').attr('class', 'flosc-tuning-status')
						.text('Latest changes waiting to save…');
					$('#flosc-save-tuning').prop('disabled', false)
						.removeClass('flosc-tuning-save--saved flosc-tuning-save--bad')
						.addClass('button-primary')
						.text('Save Model Tuning');
					return;
				}

				floscTuningState('saved', d.saved_at || '', source);

				// What came back is what is stored, after the same fold the
				// page-wide save performs. Showing it rather than what was
				// typed is the whole point: the page now displays the request
				// that will actually be sent.
				floscSyncing = true;

				if (!$('#flow_ai_temperature').is(':focus')) {
					$('#flow_ai_temperature').val(d.temperature);
				}

				if (!$('#flow_ai_max_tokens').is(':focus')) {
					$('#flow_ai_max_tokens').val(d.max_tokens);
				}

				if (!$('#flow_ai_model_params').is(':focus') && d.preview !== undefined) {
					$('#flow_ai_model_params').val(d.preview).data('extra', d.params);
				}

				floscSyncing = false;
				floscCheckParams();

				floscFlashSaved($flash && $flash.length ? $flash : $('#flow_ai_model_params'));

				// Stored and complete, so the request goes back to being a
				// statement of what will be sent — unless the operator is still
				// inside it, in which case taking the box away mid-sentence
				// would be the rudest thing this page could do.
				if (floscParamsUnlocked && !$('#flow_ai_model_params').is(':focus')) {
					floscLockParams();
				}
			},
			error: function () {
				// Through the state machine, not around it: the button was
				// disabled to say Saving, and something has to give it back.
				floscTuningState('bad', 'The save request did not complete.', source);
			},
			complete: function () {
				floscTuningInFlight = false;

				// Whatever was waiting goes now, on the next turn of the event
				// loop so this handler is off the stack first.
				if (floscTuningPending) {
					var pending = floscTuningPending;
					floscTuningPending = null;

					window.setTimeout(function () {
						floscSaveTuning(pending.flash, pending.source);
					}, 0);
				}
			}
		});
	}

	$('#flosc-save-tuning').on('pointerdown mousedown touchstart', function () {
		floscManualSaveArmed = true;
		window.clearTimeout(floscTuningTimer);
	});

	$('#flosc-save-tuning').on('click', function (event) {
		event.preventDefault();
		window.clearTimeout(floscTuningTimer);
		floscSaveTuning($('#flow_ai_model_params'), 'manual');

		// Held through the focus transition that produced this click, then
		// released so the next blur is an ordinary blur again.
		window.setTimeout(function () { floscManualSaveArmed = false; }, 0);
	});

	// Any edit makes the form dirty, including one made while a save is
	// running. An in-flight request is an older snapshot of the form, not a
	// reason to stop noticing that the form has moved on since — so newer work
	// is marked, and queued behind the request that is already going.
	$('#flow_ai_temperature, #flow_ai_max_tokens, #flow_ai_model_params').on('input', function () {
		var $field = $(this);

		if (floscSyncing) { return; }

		// Typing by hand cancels an autosave a chip or recipe had scheduled.
		// Half a handwritten line is not something to send anywhere.
		if ($field.is('#flow_ai_model_params')) {
			window.clearTimeout(floscTuningTimer);
		}

		floscTuningState('dirty');

		if (floscTuningInFlight) {
			floscQueueTuningSave($field, 'auto');
		}
	});

	// The numeric fields save after a pause in typing. The request box does
	// not: half-written YAML would be refused on every keystroke and paint the
	// status red under somebody still writing it.
	$('#flow_ai_temperature, #flow_ai_max_tokens').on('input', function () {
		if (floscSyncing) { return; }

		floscScheduleTuningAutosave($(this), 1400);
	});

	$('#flow_ai_temperature, #flow_ai_max_tokens').on('change', function () {
		window.clearTimeout(floscTuningTimer);
		floscSaveTuning($(this), 'auto');
	});

	// Leaving the request saves it — unless you left it by reaching for Save.
	//
	// This is where the label was going wrong, and it was never the label's
	// fault. Pressing Save blurs the textarea first, so the textarea's own
	// change handler started an autosave, disabled the button, and the click
	// that caused all of it arrived to find a save already running. The press
	// was discarded and the page reported Autosaved for a deliberate save.
	//
	// focusout fires before focus lands, so the answer to "where did they go?"
	// is one turn of the event loop away. Waiting for it lets the click be the
	// save it was meant to be.
	$('#flow_ai_model_params').on('focusout', function (event) {
		var $field = $(this);

		window.clearTimeout(floscTuningTimer);

		// Leaving by way of Save is a manual save, and it is already running.
		// relatedTarget names where focus is going, before it gets there.
		var next = event.relatedTarget || null;

		if (floscManualSaveArmed || (next && next.id === 'flosc-save-tuning')) {
			return;
		}

		floscTuningTimer = window.setTimeout(function () {
			if (floscManualSaveArmed) { return; }

			floscSaveTuning($field, 'auto');
		}, 150);
	});

	// ---- The parameter menu -------------------------------------------------
	//
	// Pullable, visible, explained, usable: every parameter FLOSC has a note on
	// is a button that writes itself into the request, the note is beside it,
	// the whole-set recipes are one click, and for the parameter that arrived
	// after this plugin did there is the provider's own reference and the
	// provider's own model to ask.

	function floscCurrentProvider() {
		return String($('#flow_ai_provider').val() || '');
	}

	function floscCurrentModel() {
		var field = floscModelFieldFor[floscCurrentProvider()];

		return field ? String($('#' + field).val() || '').trim() : '';
	}

	// Same longest-prefix rule the PHP side uses: providers date their model
	// ids, so a note measured on claude-sonnet-4-5 covers -20250929.
	function floscModelNoteFor(provider, model) {
		// These maps are `var`s assigned further down this block, but callers
		// run during setup — updateProviderSections() is invoked long before
		// this line is reached. Hoisting makes the function callable while its
		// data is still undefined, and reading a property off that threw, which
		// aborted setup and left every handler after it unbound. A guard here
		// rather than a rule about ordering: this must answer safely whenever
		// it is asked, including before the page has finished assembling.
		var notes = (floscParamModelNotes && floscParamModelNotes[provider]) || {};
		var best = null, bestLen = -1;

		model = String(model || '');

		Object.keys(notes).forEach(function (prefix) {
			if (model.indexOf(prefix) === 0 && prefix.length > bestLen) {
				best = notes[prefix];
				bestLen = prefix.length;
			}
		});

		return best;
	}

	// One 'name: value' line into either notation the box may be holding.
	function floscSplitParamLine(line) {
		var i = String(line).indexOf(':');

		if (i === -1) { return null; }

		return {
			name: line.slice(0, i).trim(),
			raw:  line.slice(i + 1).trim()
		};
	}

	function floscInsertParamLines(text) {
		var $box = $('#flow_ai_model_params');

		if (!$box.length) { return; }

		floscUnlockParams(false);

		var current = String($box.val() || '').trim();
		var incoming = String(text).split(/\r\n|\r|\n/).filter(function (l) { return l.trim(); });

		if (current.charAt(0) === '{') {
			// Already JSON. Put the parameters inside the object rather than
			// appending lines that would stop it parsing.
			var obj;

			try { obj = JSON.parse(current); } catch (e) { obj = null; }

			if (obj) {
				incoming.forEach(function (line) {
					var bit = floscSplitParamLine(line);

					if (!bit) { return; }

					var value;

					try { value = JSON.parse(bit.raw); } catch (e2) { value = bit.raw; }

					obj[bit.name] = value;
				});

				$box.val(JSON.stringify(obj, null, 2));
				floscCheckParams();
				floscTuningState('dirty');
				// FLOSC wrote this line, so it is known-good syntax and can
				// save itself. A short pause, so clicking three chips is one
				// save rather than three.
				floscScheduleTuningAutosave($box, 900);
				$box.trigger('focus');
				return;
			}
		}

		var lines = current ? current.split(/\r\n|\r|\n/) : [];
		var have = {};

		lines.forEach(function (line) {
			var bit = floscSplitParamLine(line);

			if (bit) { have[bit.name] = true; }
		});

		incoming.forEach(function (line) {
			var bit = floscSplitParamLine(line);

			if (bit && have[bit.name]) {
				// Replace rather than duplicate — two lines naming the same
				// parameter is not a request any provider can read.
				lines = lines.map(function (existing) {
					var e = floscSplitParamLine(existing);

					return (e && e.name === bit.name) ? line : existing;
				});
				return;
			}

			lines.push(line);

			if (bit) { have[bit.name] = true; }
		});

		$box.val(lines.filter(function (l) { return l.trim(); }).join('\n'));
		floscCheckParams();
		floscTuningState('dirty');
		floscScheduleTuningAutosave($box, 900);
		$box.trigger('focus');
	}

	function floscRenderParamMenu() {
		var provider = floscCurrentProvider();
		var model = floscCurrentModel();

		// IVR is the scripted local flow. It sends no request, so there is no
		// request to add parameters to.
		if (!provider || provider === 'ivr') {
			$('#flosc-param-menu').attr('hidden', true);
			return;
		}

		$('#flosc-param-menu').removeAttr('hidden');
		var label = floscProviderLabels[provider] || provider || 'this provider';
		var rows = floscParamsByProvider[provider] || {};
		var names = Object.keys(rows);

		$('#flosc-param-menu-where').text(
			model ? '— ' + label + ' · ' + model : '— ' + label
		);

		// What has actually been watched happen on this model, which is a
		// different and better fact than what the provider takes in general.
		var note = floscModelNoteFor(provider, model);
		var $measured = $('#flosc-param-menu-measured').empty();

		if (note && ((note.accepts && note.accepts.length) || (note.refuses && note.refuses.length))) {
			var parts = [];

			if (note.accepts && note.accepts.length) { parts.push('accepts ' + note.accepts.join(', ')); }
			if (note.refuses && note.refuses.length) { parts.push('refuses ' + note.refuses.join(', ')); }

			$measured.text('Measured on this model: ' + parts.join(' · ') + '.').removeAttr('hidden');
		} else if (model) {
			$measured.text(
				'Nothing has been measured on ' + model + ' here. The list below is what ' + label +
				' is known to take; the model decides, and its answer comes back in Step 3.'
			).removeAttr('hidden');
		} else {
			$measured.attr('hidden', true);
		}

		var refused = {};

		if (note && note.refuses) {
			note.refuses.forEach(function (n) { refused[n] = true; });
		}

		var $chips = $('#flosc-param-menu-chips').empty();

		if (!names.length) {
			$('<p>').addClass('description').text('FLOSC has no notes for this provider yet.').appendTo($chips);
		}

		names.forEach(function (name) {
			var ref = rows[name];
			var $chip = $('<button>')
				.attr('type', 'button')
				.addClass('button flosc-param-chip')
				.attr('data-param', name)
				.attr('data-insert', ref.example || (name + ': '))
				.text(name);

			if (refused[name]) {
				$chip.addClass('flosc-param-chip--refused')
					.attr('title', model + ' refuses this one.');
			}

			$chip.appendTo($chips);
		});

		var $recipes = $('#flosc-param-menu-recipes').empty();
		var recipes = floscParamRecipes[provider] || [];

		if (!recipes.length) {
			$('<p>').addClass('description')
				.text('No setups for ' + label + ' yet — none of its models have been measured here, and a plausible guess is worth less than nothing.')
				.appendTo($recipes);
		}

		recipes.forEach(function (recipe, i) {
			var $r = $('<div>').addClass('flosc-param-recipe').appendTo($recipes);
			var $head = $('<div>').addClass('flosc-param-recipe__head').appendTo($r);

			$('<strong>').text(recipe.name).appendTo($head);
			$('<button>')
				.attr('type', 'button')
				.addClass('button button-small flosc-param-recipe__use')
				.attr('data-recipe', i)
				.text('Use this')
				.appendTo($head);

			$('<p>').addClass('flosc-param-recipe__why').text(recipe.why).appendTo($r);
			$('<pre>').addClass('flosc-param-recipe__params').text(recipe.params).appendTo($r);

			if (recipe.models) {
				$('<p>').addClass('flosc-param-recipe__models').text(recipe.models).appendTo($r);
			}
		});

		var docs = floscProviderDocs[provider] || '';
		var $docs = $('#flosc-param-docs');

		if (docs) {
			$docs.attr('href', docs).text('Open ' + label + '\u2019s own request reference \u2197').removeAttr('hidden');
		} else {
			$docs.attr('hidden', true);
		}

		$('#flosc-param-menu-note').attr('hidden', true).empty();
	}

	$('#flosc-param-menu-chips').on('click', '.flosc-param-chip', function () {
		var name = $(this).data('param');
		var ref = (floscParamsByProvider[floscCurrentProvider()] || {})[name];

		floscInsertParamLines(String($(this).data('insert')));

		// The explanation lands where the click did, not only in the list of
		// what is currently typed further up.
		var $note = $('#flosc-param-menu-note').empty();

		if (ref) {
			$('<code>').text(name).appendTo($note);
			$('<span>').addClass('flosc-param-menu__what').text(' ' + ref.what).appendTo($note);

			var $meta = $('<div>').addClass('flosc-param-menu__meta')
				.text('Range: ' + ref.range + ' · ' + ref.providers +
						(ref.measured ? ' · measured against the live API' : ''))
				.appendTo($note);
			var $link = floscParamDocLink(name);

			if ($link) { $meta.append(document.createTextNode(' ')).append($link); }

			$note.removeAttr('hidden');
		}
	});

	$('#flosc-param-menu-recipes').on('click', '.flosc-param-recipe__use', function () {
		var recipes = floscParamRecipes[floscCurrentProvider()] || [];
		var recipe = recipes[parseInt($(this).data('recipe'), 10)];

		if (recipe) { floscInsertParamLines(recipe.params); }
	});

	// The parameter FLOSC has never heard of: ask the provider's own model,
	// through the key already configured on this flow. Labelled as the model's
	// answer, because a model can be wrong about its own API.
	$('#flosc-param-ask').on('click', function () {
		var $btn = $(this);
		var name = String($('#flosc-param-ask-name').val() || '').trim();
		var $out = $('#flosc-param-answer').removeClass('flosc-param-menu__answer--bad');

		if (!name) {
			$out.text('Type a parameter name first.').addClass('flosc-param-menu__answer--bad').removeAttr('hidden');
			return;
		}

		$btn.prop('disabled', true);
		$out.text('Asking ' + (floscCurrentModel() || 'the model') + '\u2026').removeAttr('hidden');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_explain_ai_parameter',
				nonce: String(floscAiConfig.testNonce || ''),
				ivr: String(floscAiConfig.ivr || ''),
				param: name
			},
			success: function (response) {
				if (!response || !response.success) {
					$out.text((response && response.data && response.data.message) || 'The provider did not answer.')
						.addClass('flosc-param-menu__answer--bad');
					return;
				}

				var d = response.data;

				$out.empty();
				$('<div>').addClass('flosc-param-menu__answersrc')
					.text('Answered by ' + (d.model || d.provider) + ' just now — this is the model\u2019s account of its own API, not FLOSC\u2019s.')
					.appendTo($out);
				$('<p>').text(d.answer).appendTo($out);

				var $row = $('<p>').appendTo($out);

				$('<button>')
					.attr('type', 'button')
					.addClass('button button-small')
					.text('Add ' + d.param + ' to the request')
					.on('click', function () { floscInsertParamLines(d.param + ': '); })
					.appendTo($row);

				// Somewhere to check the model against, which matters most for
				// exactly the parameter nobody here has a note on.
				var $link = floscParamDocLink(d.param);

				if ($link) { $row.append(document.createTextNode(' ')).append($link); }
			},
			error: function () {
				$out.text('The request did not complete.').addClass('flosc-param-menu__answer--bad');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	$('#flow_ai_provider, #flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
		.on('input change', floscRenderParamMenu);

	// Changing the model can change which controls apply — Sonnet 4.5 takes
	// temperature where Sonnet 5 refuses it — so the rows are re-decided too.
	$('#flow_ai_anthropic_model, #flow_ai_openai_model, #flow_ai_xai_model, #flow_ai_gemini_model')
		.on('input change', function () {
			if (typeof updateProviderSections === 'function') { updateProviderSections(); }
		});

	floscRenderParamMenu();

	// The field and the line that carries it are two views of one value, so
	// they are kept equal as they are typed rather than argued over at save
	// time. Nothing is struck through and nothing is warned about, because
	// there is never a moment where the two disagree.
	function floscMarkOverrides(names, values) {
		if (floscSyncing) { return; }

		floscSyncing = true;

		['temperature', 'max_tokens'].forEach(function (field) {
			var $input = $('#flow_ai_' + field);

			$('#flosc-overridden-' + field).attr('hidden', true).text('');
			$input.removeClass('flosc-input-overridden');

			// Never rewrite a field while it is being typed into: the value
			// is half-entered, and correcting "0.30" to "0.3" under the cursor
			// is the sort of help nobody asked for.
			if (!$input.length || $input.is(':focus') || names.indexOf(field) === -1) { return; }

			var value = values ? values[field] : undefined;

			if (value === undefined || value === null || typeof value === 'object') { return; }

			if (String($input.val()) !== String(value)) {
				$input.val(String(value));
			}
		});

		floscSyncing = false;
	}

	// FLOSC's note on a parameter is a paraphrase written for an operator, not
	// the authority. Every note carries the way out to the provider's own
	// entry for that exact parameter, in a new tab so nothing typed is lost.
	function floscParamDocLink(name) {
		var provider = floscCurrentProvider();
		var template = floscProviderParamDocs[provider] || '';
		var label = floscProviderLabels[provider] || provider;

		if (template) {
			return $('<a>')
				.addClass('flosc-param-doc')
				.attr('href', template.replace('%s', encodeURIComponent(name)))
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(label + ' on ' + name + ' \u2197');
		}

		var page = floscProviderDocs[provider] || '';

		if (page) {
			// No anchor scheme read off that provider's page, so the reader is
			// sent to the page and told that is what this link is.
			return $('<a>')
				.addClass('flosc-param-doc')
				.attr('href', page)
				.attr('target', '_blank')
				.attr('rel', 'noopener noreferrer')
				.text(label + '\u2019s request reference \u2197');
		}

		// Nowhere to send them. Say so in words rather than leave the note
		// trailing off: an operator who finds nothing assumes there is nothing
		// to find, and there is — it is just not somewhere FLOSC can point at.
		return $('<span>')
			.addClass('flosc-param-doc flosc-param-doc--none')
			.text('For more on this parameter, see ' + label + '\u2019s own API reference.');
	}

	// Two sampling controls the provider will not take together. Named in the
	// order they appear, because that is the order the request applies them in:
	// the first one reaches the model and the rest are held back.
	function floscExplainSamplingClash($help, names) {
		var provider = floscCurrentProvider();
		var group = floscSamplingExclusive[provider] || [];

		if (group.length < 2) { return; }

		var present = names.filter(function (n) { return group.indexOf(n) !== -1; });

		if (present.length < 2) { return; }

		var kept = present[0];
		var held = present.slice(1);
		var label = floscProviderLabels[provider] || provider;
		var $row = $('<div>').addClass('flosc-param-help__row flosc-param-clash').prependTo($help);

		$('<strong>').text(label + ' will not take ' + present.join(' and ') + ' on the same request.').appendTo($row);
		$('<div>').addClass('flosc-param-help__meta').text(
			'It answers 400 to both, so FLOSC sends ' + kept + ' and holds back ' +
			held.join(', ') + '. Remove ' + held.join(' and ') +
			' to make this request say what is actually sent.'
		).appendTo($row);
	}

	function floscExplainParams(names) {
		var $help = $('#flosc-param-help').empty();

		if (!names.length) { $help.attr('hidden', true); return; }

		names.forEach(function (name) {
			var ref = floscParamRef[name];
			var $row = $('<div>').addClass('flosc-param-help__row').appendTo($help);
			var $link = floscParamDocLink(name);

			$('<code>').addClass('flosc-param-help__name').text(name).appendTo($row);

			if (!ref) {
				$('<span>').addClass('flosc-param-help__unknown')
					.text('FLOSC has no note on this one. It will be sent as written and ' +
							'the provider decides — its answer appears in Step 3.')
					.appendTo($row);

				if ($link) {
					$('<div>').addClass('flosc-param-help__meta').append($link).appendTo($row);
				}

				return;
			}

			$('<span>').addClass('flosc-param-help__what').text(ref.what).appendTo($row);

			var $meta = $('<div>').addClass('flosc-param-help__meta').appendTo($row);
			$('<span>').text('Range: ' + ref.range).appendTo($meta);
			$('<span>').text(ref.providers).appendTo($meta);

			if (ref.measured) {
				$('<span>').addClass('flosc-param-help__measured')
					.text('measured against the live API').appendTo($meta);
			}

			if ($link) { $link.appendTo($meta); }
		});

		floscExplainSamplingClash($help, names);

		$help.removeAttr('hidden');
	}

	function floscCheckParams() {
		var $box = $('#flow_ai_model_params');
		var $out = $('#flosc-params-status');

		if (!$box.length) { return; }

		var raw = String($box.val() || '').trim();

		$out.removeClass('flosc-key-state--none flosc-key-state--ok');

		if (!raw) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

		if (raw.charAt(0) === '{') {
			try {
				var obj = JSON.parse(raw);
				var n = Object.keys(obj).length;
				$out.addClass('flosc-key-state--ok').text('\u2713 Valid JSON — ' + n + (n === 1 ? ' parameter' : ' parameters') + ' will be sent.');
				floscExplainParams(Object.keys(obj));
				floscMarkOverrides(Object.keys(obj), obj);
			} catch (e) {
				$out.addClass('flosc-key-state--none').text('\u2717 Not valid JSON: ' + e.message);
				floscExplainParams([]);
				floscMarkOverrides([], {});
			}
			return;
		}

		var lines = raw.split(/\r\n|\r|\n/), names = [], values = {}, bad = null;

		lines.forEach(function (line) {
			line = line.trim();
			if (!line || line.charAt(0) === '#' || line.indexOf('//') === 0) { return; }
			var i = line.indexOf(':');
			if (i < 1) { bad = bad || line; return; }
			var nm = line.slice(0, i).trim();
			names.push(nm);
			values[nm] = line.slice(i + 1).trim().replace(/,$/, '');
		});

		if (bad) {
			$out.addClass('flosc-key-state--none').text('\u2717 This line is not "name: value" — ' + bad);
			floscExplainParams([]);
			floscMarkOverrides([], {});
			return;
		}

		if (!names.length) { $out.text(''); floscExplainParams([]); floscMarkOverrides([], {}); return; }

		$out.addClass('flosc-key-state--ok').text('\u2713 ' + names.length + (names.length === 1 ? ' parameter' : ' parameters') + ' will be sent.');
		floscExplainParams(names);
		floscMarkOverrides(names, values);
	}

	$('#flow_ai_model_params').on('input change', floscCheckParams);
	floscCheckParams();

	$('.flosc-describe-model').on('click', function () {
		var $btn = $(this);
		floscDescribeModel($btn.data('provider'), $btn.data('target'), String($('#' + $btn.data('target')).val() || '').trim(), $btn, true);
	});

	$('.flosc-fetch-models').on('click', function () {
		var $btn = $(this);
		var provider = $btn.data('provider');
		var targetId = $btn.data('target');
		var $status = $('.flosc-model-fetch-status[data-for="' + targetId + '"]');
		var $picker = $('.flosc-model-picker[data-for="' + targetId + '"]');

		$btn.prop('disabled', true);
		$status.removeClass('flosc-model-fetch-status--bad').text('Asking the provider…');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_fetch_ai_models',
				nonce: String(floscAiConfig.testNonce || ''),
				provider: provider,
				ivr: String(floscAiConfig.ivr || ''),
				api_key: $('#flow_' + provider + '_api_key').val() || ''
			},
			success: function (response) {
				if (!response || !response.success) {
					$picker.attr('hidden', true).empty();
					$status.addClass('flosc-model-fetch-status--bad')
						.text((response && response.data && response.data.message) || 'Could not fetch the list.');
					return;
				}

				var all = response.data.models || [];

				if (floscRenderModelPicker($picker, targetId, all) === 0) {
					$status.addClass('flosc-model-fetch-status--bad')
						.text('The provider returned no models for this key.');
					return;
				}

				$status.text('Your key reached the provider. Pick a model below.');
			},
			error: function () {
				$picker.attr('hidden', true).empty();
				$status.addClass('flosc-model-fetch-status--bad').text('Could not reach the server.');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	// --- Connection test ---
	// The test runs against saved settings. Anything typed and not yet saved is
	// invisible to it, so testing then would diagnose the old value and blame
	// the provider for it. Remember what was on the page when it loaded, and
	// stop the test if the operator has changed it since.
	var floscSavedAi = {};
	$('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
		floscSavedAi[this.id] = $(this).val();
	});

	function floscUnsavedAiFields() {
		var changed = [];
		$('.flosc-ai-key-input, #flow_ai_provider, .flosc-ai-model-select').each(function () {
			if (floscSavedAi[this.id] !== undefined && floscSavedAi[this.id] !== $(this).val()) {
				var label = $('label[for="' + this.id + '"]').text().replace(/\s+/g, ' ').trim();
				changed.push(label || this.id);
			}
		});
		return changed;
	}

	// The test asks the provider for its model list before it tries to generate.
	// That call is the only step that isolates the key from everything built on
	// top of it, so its result is reported whether the test passed or failed —
	// and the ids it returns are rendered as buttons, not prose.
	function floscTestModelReport(d, lines) {
		var $picker = $('#test-model-picker').attr('hidden', true).empty();

		if (!d || !d.models_probed) { return; }

		if (d.models_error) {
			lines.push('');
			lines.push('Asking ' + (d.provider || 'the provider') + ' which models this key can use: ' + d.models_error);
			return;
		}

		var models = d.models || [];
		var targetId = floscModelFieldFor[d.provider];

		lines.push('');
		lines.push('\u2713 The key reached ' + (d.provider || 'the provider') + ', which lists ' +
			models.length + (models.length === 1 ? ' model' : ' models') + ' for it.');

		if (d.model && models.length) {
			var listed = models.some(function (m) { return m.id === d.model; });

			if (!listed) {
				lines.push('\u2717 "' + d.model + '" is not one of them.');
			}
		}

		if (targetId) {
			$picker.attr('data-for', targetId);
			floscRenderModelPicker($picker, targetId, models);
		}
	}

	$('#test-ai-connection').on('click', function() {
		var $btn = $(this);
		var $loading = $('#test-loading');
		var $results = $('#test-results');
		var $flosc_status = $('#test-status');
		var $details = $('#test-details');

		var unsaved = floscUnsavedAiFields();
		if (unsaved.length) {
			$results.show();
			$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">\u2717 Unsaved AI settings</span>');
			$details.text(
				'These were changed but not saved yet:\n  ' + unsaved.join('\n  ') +
				'\n\nThe test runs against saved settings, so it would test the old values and blame the provider for it.' +
				'\n\nSave AI Settings, then Test again.'
			);
			return;
		}

		$btn.prop('disabled', true);
		$loading.show();
		$results.hide();
		$('#test-model-picker').attr('hidden', true).empty();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'flosc_test_ai_connection',
				nonce: String(floscAiConfig.testNonce || ''),
				ivr: String(floscAiConfig.ivr || '')
			},
			success: function(response) {
				$loading.hide();
				$results.show();
				
				if (response.success) {
					var d = response.data || {};
					var lines = [];
					lines.push('Result: LIVE external API call succeeded (not IVR scripted mode)');
					if (d.provider) { lines.push('Provider: ' + d.provider); }
					if (d.model) { lines.push('Model used: ' + d.model); }
					if (d.model_configured && d.model_configured !== d.model) {
						lines.push('Model configured: ' + d.model_configured);
					}
					if (d.endpoint) { lines.push('Endpoint: ' + d.endpoint); }
					if (d.api_key_present) {
						lines.push('API key: present' + (d.api_key_suffix ? (' (…' + d.api_key_suffix + ')') : ''));
					} else {
						lines.push('API key: missing in saved settings');
					}
					if (d.flow_label || d.flow_ivr) {
						lines.push('Flow: ' + (d.flow_label || d.flow_ivr) + (d.flow_ivr && d.flow_label ? (' (' + d.flow_ivr + ')') : ''));
					}
					if (d.response_time != null) { lines.push('Latency: ' + d.response_time + ' ms'); }
					if (d.tokens_total > 0 || d.tokens_in > 0 || d.tokens_out > 0) {
						lines.push(
							'Tokens: in ' + (d.tokens_in || 0) +
							' / out ' + (d.tokens_out || 0) +
							' / total ' + (d.tokens_total || ((d.tokens_in || 0) + (d.tokens_out || 0)))
						);
					} else {
						lines.push('Tokens: (provider did not return usage on this call)');
					}
					if (d.billing_source) { lines.push('Billing source: ' + d.billing_source); }
					if (d.model_configured && d.model && d.model_configured !== d.model) {
						lines.push('');
						lines.push('Note: "' + d.model_configured + '" is not in the installed provider plugin\'s catalog,');
						lines.push('so the provider answered with ' + d.model + ' instead. Pick a model the plugin offers,');
						lines.push('or update the provider plugin, to use the one you configured.');
					}
					if (d.params_configured && d.params_configured.length) {
						lines.push('Extra parameters configured: ' + d.params_configured.join(', '));
					}
					if (d.params_applied && d.params_applied.length) {
						lines.push('\u2713 Carried by this request: ' + d.params_applied.join(', '));
					}
					if (d.params_unapplied && d.params_unapplied.length) {
						lines.push('\u2717 Left out, with the reason each was refused:');
						d.params_unapplied.forEach(function (p) { lines.push('    ' + p); });
					}
					lines.push('Model reply: ' + (d.response || '(empty)'));
					floscTestModelReport(d, lines);
					$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--pass">✓ External API OK — key + model path verified</span>');
					$details.text(lines.join('\n'));
				} else {
					var ed = response.data || {};
					var elines = [];
					$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Connection Failed</span>');
					// Report the layer that actually failed. Everything above the
					// failure is confirmed; only the last line is the diagnosis.
					elines.push('\u2713 FLOSC configuration loaded');
					if (ed.provider) { elines.push('\u2713 Provider selected: ' + ed.provider); }
					if (ed.api_key_present === false) {
						elines.push('\u2717 No API key in the saved settings');
						elines.push('');
						elines.push('Paste the key above, then Save Settings, then Test again. A key that is typed but not saved is not yet a key.');
					} else {
						if (ed.api_key_suffix) {
							elines.push('\u2713 API key saved (\u2026' + ed.api_key_suffix + ')');
						} else if (ed.api_key_present) {
							elines.push('\u2713 API key saved');
						}
						if (ed.model) { elines.push('\u2713 Model requested: ' + ed.model); }
						if (ed.endpoint) { elines.push('\u2713 Endpoint reached: ' + ed.endpoint); }
						elines.push('\u2717 The request was rejected before a reply came back');
						elines.push('');
						elines.push('What the provider integration said:');
					}
					elines.push(ed.message || 'Unknown error');
					if (ed.flow_ivr || ed.response_time != null) { elines.push(''); }
					if (ed.flow_ivr) { elines.push('Flow: ' + ed.flow_ivr); }
					if (ed.response_time != null) { elines.push('Round trip: ' + ed.response_time + ' ms'); }
					floscTestModelReport(ed, elines);
					$details.text(elines.join('\n'));
				}
			},
			error: function() {
				$loading.hide();
				$results.show();
				$flosc_status.html('<span class="flosc-pass-status flosc-pass-status--fail">✗ Request Failed</span>');
				$details.text('Could not reach the server. Please try again.');
			},
			complete: function() {
				$btn.prop('disabled', false);
			}
		});
	});
});
}());


/* ai-configuration accuracy */
(function () {
	if (!document.querySelector("#flosc-run-accuracy-test")) { return; }
jQuery(document).ready(function($) {
	var floscAiConfig = window.floscAiAdminConfig || {};
	function floscAccVarMap() {
		var raw = $('#flosc-accuracy-rows').attr('data-flosc-acc-map') || '{}';
		try { return JSON.parse(raw); } catch (e) { return {}; }
	}
	function floscAccExpand(text, map) {
		var out = String(text || '');
		$.each(map || {}, function(k, v) {
			out = out.split(k).join(v);
		});
		return out;
	}
	function floscAccRefreshPreviews() {
		var map = floscAccVarMap();
		$('#flosc-accuracy-rows .flosc-acc-row').each(function() {
			var t = $(this).find('.flosc-acc-row__input').val() || '';
			$(this).find('.flosc-acc-row__preview').text(floscAccExpand(t, map));
		});
	}
	function floscSyncAccuracyHidden() {
		var lines = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { lines.push(t); }
		});
		$('#flow_ai_accuracy_test_questions').val(lines.join('\n'));
		$('#flosc-test-msg-total').text(Math.max(1, lines.length));
		floscAccRefreshPreviews();
	}
	function floscParseAccuracyQuestions() {
		floscSyncAccuracyHidden();
		var map = floscAccVarMap();
		var msgs = [];
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			var t = $.trim($(this).val() || '');
			if (t) { msgs.push(floscAccExpand(t, map)); }
		});
		return msgs;
	}

	$('#flosc-accuracy-rows').on('input change', '.flosc-acc-row__input', floscSyncAccuracyHidden);

	$('#flosc-accuracy-rows').on('click', '.flosc-acc-reset-row', function() {
		var $row = $(this).closest('.flosc-acc-row');
		var $inp = $row.find('.flosc-acc-row__input');
		var tpl = $inp.attr('data-acc-template') || '';
		$inp.val(tpl);
		floscSyncAccuracyHidden();
	});

	$('#flosc-accuracy-reset-defaults').on('click', function() {
		$('#flosc-accuracy-rows .flosc-acc-row__input').each(function() {
			$(this).val($(this).attr('data-acc-template') || '');
		});
		floscSyncAccuracyHidden();
	});

	floscSyncAccuracyHidden();

	$('#flosc-run-accuracy-test').on('click', function() {
		var testMessages = floscParseAccuracyQuestions();
		if (!testMessages.length) {
			window.alert(String((floscAiConfig.labels || {}).addQuestion || 'Add at least one test question.'));
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);
		$('#flosc-test-msg-total').text(testMessages.length);
		$('#flosc-test-progress').show();
		$('#flosc-accuracy-results').hide();
		$('#flosc-accuracy-tbody').empty();
		$('#flosc-accuracy-summary').empty();

		var history = [];
		var totalTokensIn = 0;
		var passes = 0;
		var feedback = 0;
		function accuracyFailureMessage(payload, fallback) {
			var data = payload && payload.data ? payload.data : payload;
			if (typeof data === 'string' && data) {
				return data;
			}
			if (data && typeof data.message === 'string' && data.message) {
				return data.message;
			}
			return fallback;
		}

		function appendAccuracyFailure(idx, message) {
			$('#flosc-accuracy-tbody').append(
				'<tr>'
				+ '<td>' + (idx + 1) + '</td>'
				+ '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
				+ '<td class="flosc-text-12 flosc-request-failed">' + $('<div>').text(message).html() + '</td>'
				+ '<td>0</td>'
				+ '<td class="flosc-pass-status flosc-pass-status--fail">✗</td>'
				+ '</tr>'
			);
		}

		function runMessage(idx) {
			if (idx >= testMessages.length) {
				var summaryHtml = '<strong>Session Summary:</strong> '
					+ passes + '/' + testMessages.length + ' completed without hard fail | '
					+ 'Total input tokens: ' + totalTokensIn + ' | '
					+ 'Flags: ' + feedback;
				$('#flosc-accuracy-summary').html(summaryHtml);
				$('#flosc-accuracy-results').show();
				$('#flosc-test-progress').hide();
				$btn.prop('disabled', false);
				return;
			}
			$('#flosc-test-msg-num').text(idx + 1);

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'flosc_accuracy_test_message',
					nonce: String(floscAiConfig.accuracyNonce || ''),
					ivr: String(floscAiConfig.ivr || ''),
					message: testMessages[idx],
					message_index: idx,
					history: JSON.stringify(history)
				},
				success: function(resp) {
					if (!resp || resp.success !== true) {
						appendAccuracyFailure(idx, accuracyFailureMessage(resp, 'The AI provider request failed.'));
						runMessage(idx + 1);
						return;
					}
					var r = resp.data || {};
					var response_text = r.response || '(no response)';
					var tokens_in = r.tokens_in || 0;
					var pass = r.pass !== false;
					var corrected = r.corrected || false;

					totalTokensIn += tokens_in;
					if (pass) passes++;
					if (corrected) feedback++;

					var passCell = pass
						? '<td class="flosc-pass-status flosc-pass-status--pass">✓</td>'
						: '<td class="flosc-pass-status flosc-pass-status--fail">✗</td>';
					if (corrected) passCell = '<td class="flosc-pass-status flosc-pass-status--corrected">⚡</td>';

					var snippet = response_text.length > 200
						? response_text.substring(0, 200) + '…'
						: response_text;

					$('#flosc-accuracy-tbody').append(
						'<tr>'
						+ '<td>' + (idx + 1) + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(testMessages[idx]).html() + '</td>'
						+ '<td class="flosc-text-12">' + $('<div>').text(snippet).html() + '</td>'
						+ '<td>' + tokens_in + '</td>'
						+ passCell
						+ '</tr>'
					);

					history.push({ role: 'user', content: testMessages[idx] });
					history.push({ role: 'assistant', content: response_text });

					runMessage(idx + 1);
				},
				error: function(xhr) {
					appendAccuracyFailure(
						idx,
						accuracyFailureMessage(xhr && xhr.responseJSON ? xhr.responseJSON : null, 'The AI provider request failed.')
					);
					runMessage(idx + 1);
				}
			});
		}

		runMessage(0);
	});
});
}());


/* flow-edit color */
(function () {
	if (!document.querySelector("#floscflow_color")) { return; }
document.getElementById('floscflow_color').addEventListener('input', function() {
									document.getElementById('floscflow_color_hex').value = this.value;
								});
}());


/* flow-edit media */
(function () {
	if (!document.querySelector("#flosc_upload_app_icon")) { return; }
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
}());


/* engagement */
(function () {
	if (!document.querySelector(".flosc-eng-rule-list")) { return; }
(function () {
	var lastCond = null;

	function slugify(s) {
		return String(s || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '_')
			.replace(/^_+|_+$/g, '')
			.substring(0, 48) || 'rule';
	}

	function reindexRules() {
		var rules = document.querySelectorAll('.flosc-eng-rule-list .flosc-eng-rule');
		rules.forEach(function (rule, i) {
			rule.setAttribute('data-rule-index', String(i));
			var en = rule.querySelector('.flosc-eng-rule-enabled');
			if (en) en.name = 'engagement_rule_enabled[' + i + ']';
			var ach = rule.querySelector('.flosc-eng-rule-action-chat');
			if (ach) ach.name = 'engagement_rule_action_chat[' + i + ']';
			var aem = rule.querySelector('.flosc-eng-rule-action-email');
			if (aem) aem.name = 'engagement_rule_action_email[' + i + ']';
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			var titleInput = rule.querySelector('.flosc-eng-rule-title');
			if (idInput && (!idInput.value || idInput.value.indexOf('rule_') === 0 || idInput.value === '')) {
				var t = titleInput ? titleInput.value : '';
				idInput.value = slugify(t) + '_' + i;
			}
			var idLab = rule.querySelector('.flosc-eng-rule-id-label');
			if (idLab && idInput) idLab.textContent = idInput.value;
			var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sumTitle && titleInput) sumTitle.textContent = titleInput.value || idInput.value || ('Rule ' + (i + 1));
		});
	}

	function refreshAudienceCounts() {
		document.querySelectorAll('.flosc-eng-accordion').forEach(function (acc) {
			var list = acc.querySelector('.flosc-eng-rule-list');
			if (!list) return;
			var n = 0;
			list.querySelectorAll('.flosc-eng-rule').forEach(function (rule) {
				var en = rule.querySelector('.flosc-eng-rule-enabled');
				if (en && en.checked) n++;
			});
			var sub = acc.querySelector('.flosc-eng-accordion__sub');
			if (!sub) return;
			var base = sub.textContent.replace(/\d+\s*active rule\(s\)/, '').replace(/·\s*$/, '').trim();
			sub.textContent = base + ' · ' + n + ' active rule(s)';
		});
	}

	document.addEventListener('focusin', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-condition-input')) {
			lastCond = e.target;
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-title')) {
			var rule = e.target.closest('.flosc-eng-rule');
			if (!rule) return;
			var sum = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sum) sum.textContent = e.target.value || 'New rule';
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			if (idInput) {
				var idx = rule.getAttribute('data-rule-index') || '0';
				idInput.value = slugify(e.target.value) + '_' + idx;
				var idLab = rule.querySelector('.flosc-eng-rule-id-label');
				if (idLab) idLab.textContent = idInput.value;
			}
		}
	});

	document.addEventListener('change', function (e) {
		if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-enabled')) {
			refreshAudienceCounts();
			var rule = e.target.closest('.flosc-eng-rule');
			if (rule) {
				var prev = rule.querySelector('.flosc-eng-rule__summary-preview');
				if (prev) {
					var t = prev.textContent.replace(/\b(active|off)\b/, e.target.checked ? 'active' : 'off');
					prev.textContent = t;
				}
			}
		}
	});

	document.addEventListener('click', function (e) {
		var chip = e.target.closest('.flosc-condition-copy');
		if (chip) {
			e.preventDefault();
			var condition = chip.getAttribute('data-condition') || '';
			if (!condition) return;
			var input = lastCond;
			if (!input || !document.body.contains(input)) {
				input = document.querySelector('.flosc-eng-condition-input');
			}
			if (input) {
				var cur = (input.value || '').trim();
				input.value = cur ? (cur + ' && ' + condition) : condition;
				input.focus();
				lastCond = input;
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(condition);
			}
			return;
		}

		var addBtn = e.target.closest('.flosc-eng-add-rule');
		if (addBtn) {
			e.preventDefault();
			var audience = addBtn.getAttribute('data-audience') || 'guest';
			var list = document.querySelector('.flosc-eng-rule-list[data-audience="' + audience + '"]');
			var tpl = document.getElementById('flosc-eng-rule-template');
			if (!list || !tpl) return;
			var node = tpl.content.cloneNode(true);
			var rule = node.querySelector('.flosc-eng-rule');
			if (!rule) return;
			rule.setAttribute('data-audience', audience);
			var audInput = rule.querySelector('.flosc-eng-rule-audience');
			if (audInput) audInput.value = audience;
			var titleInput = rule.querySelector('.flosc-eng-rule-title');
			if (titleInput) {
				var label = audience.charAt(0).toUpperCase() + audience.slice(1) + ' · new rule';
				titleInput.value = label;
			}
			var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
			if (sumTitle && titleInput) sumTitle.textContent = titleInput.value;
			var idInput = rule.querySelector('.flosc-eng-rule-id');
			if (idInput) idInput.value = '';
			var cond = rule.querySelector('.flosc-eng-condition-input');
			if (cond) {
				if (audience === 'visitor') cond.value = 'is_visitor';
				else if (audience === 'guest') cond.value = 'is_guest';
				else if (audience === 'member') cond.value = 'is_member';
			}
			rule.setAttribute('open', '');
			list.appendChild(rule);
			reindexRules();
			refreshAudienceCounts();
			return;
		}

		var rem = e.target.closest('.flosc-eng-rule-remove');
		if (rem) {
			e.preventDefault();
			var ruleEl = rem.closest('.flosc-eng-rule');
			if (!ruleEl) return;
			var listEl = ruleEl.closest('.flosc-eng-rule-list');
			if (listEl && listEl.querySelectorAll('.flosc-eng-rule').length <= 1) {
				// Keep at least one shell: clear fields instead of removing last.
				var t = ruleEl.querySelector('.flosc-eng-rule-title');
				if (t) t.value = 'New rule';
				var c = ruleEl.querySelector('.flosc-eng-condition-input');
				if (c) c.value = '';
				var m = ruleEl.querySelector('.flosc-eng-rule-chat-message');
				if (m) m.value = '';
				var en = ruleEl.querySelector('.flosc-eng-rule-enabled');
				if (en) en.checked = false;
				var sum = ruleEl.querySelector('.flosc-eng-rule__summary-title');
				if (sum) sum.textContent = 'New rule';
				reindexRules();
				refreshAudienceCounts();
				return;
			}
			ruleEl.remove();
			reindexRules();
			refreshAudienceCounts();
		}
	});

	reindexRules();
})();
}());


/* knowledge-base */
(function () {
	if (!document.querySelector(".flosc-kb-dropzone")) { return; }
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
}());


/* offers */
(function () {
	if (!document.querySelector(".flosc-demo-offers")) { return; }
function floscToggleOffer(id) {
	const card = document.getElementById('offer-' + id);
	if (card) card.classList.toggle('is-open');
}
function floscToggleFmt(checkbox) {
	const card = checkbox.closest('.flosc-fmt-card');
	card.classList.toggle('is-enabled', checkbox.checked);
}
function floscToggleProcessorFields(safeId, processor) {
	const processors = ['paypal', 'stripe', 'redirect'];
	processors.forEach(function(p) {
		const el = document.getElementById('proc-' + p + '-' + safeId);
		if (el) el.style.display = (p === processor) ? 'block' : 'none';
	});
}
// Condition reference: click to copy to clipboard and nearest condition input
document.addEventListener('click', function(e) {
	const btn = e.target.closest('.flosc-condition-copy');
	if (!btn) return;
	const condition = btn.dataset.condition;
	if (!condition) return;
	
	// Copy to clipboard
	navigator.clipboard.writeText(condition).then(function() {
		const original = btn.innerHTML;
		btn.innerHTML = '<code>✓ Copied!</code>';
		btn.classList.add('copied');
		setTimeout(function() {
			btn.innerHTML = original;
			btn.classList.remove('copied');
		}, 1500);
	});
	
	// Also paste into the nearest offer's global condition input
	const offerCard = btn.closest('.flosc-offer-editor');
	if (offerCard) {
		const condInput = offerCard.querySelector('input[name="offer_condition"]');
		if (condInput) {
			// Append with && if there's already a value
			if (condInput.value.trim()) {
				condInput.value = condInput.value.trim() + ' && ' + condition;
			} else {
				condInput.value = condition;
			}
			condInput.focus();
			condInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}
});
document.addEventListener('DOMContentLoaded', function() {
	const open = document.querySelector('.flosc-offer-card.is-open');
	if (open) open.scrollIntoView({ behavior: 'smooth', block: 'start' });

	document.querySelectorAll('form').forEach(function(form) {
		var src = form.querySelector('[data-flosc-offer-token-source]');
		if (!src) return;

		var offer = src.getAttribute('data-flosc-offer');
		var custom = form.querySelector('[data-flosc-offer-token-custom="' + offer + '"]');
		var capMode = form.querySelector('[data-flosc-offer-token-cap-mode]');
		var capInput = form.querySelector('[data-flosc-offer-token-cap-input="' + offer + '"]');

		function syncSource() {
			if (custom) custom.classList.toggle('flosc-is-disabled', src.value !== 'custom');
		}

		function syncCap() {
			if (capInput) capInput.classList.toggle('flosc-is-disabled', !capMode || capMode.value !== 'custom');
		}

		src.addEventListener('change', syncSource);
		if (capMode) {
			capMode.addEventListener('change', syncCap);
		}

		syncSource();
		syncCap();
	});
});
}());


/* token-management */
(function () {
	if (!document.querySelector("[data-flosc-token-source]")) { return; }
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
}());


/* ui-navigation */
(function () {
	if (!document.querySelector("[data-flosc-profile-icon]")) { return; }
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
}());

}());
