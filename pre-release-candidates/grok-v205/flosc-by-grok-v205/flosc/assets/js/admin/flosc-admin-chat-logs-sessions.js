/**
 * Chat Logs tab, conversations view: selection, archive, delete, admin join, token assignment.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminChatLogsSessions */
	jQuery(function($) {
		var nonce = floscAdminChatLogsSessions.nonce;
		var archiveOperation = floscAdminChatLogsSessions.archiveOperation;

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
				flow_id: floscAdminChatLogsSessions.flowId,
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
