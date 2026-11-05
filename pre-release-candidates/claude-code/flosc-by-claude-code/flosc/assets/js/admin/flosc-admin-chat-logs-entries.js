/**
 * Chat Logs tab, all-entries view: live polling and log management.
 *
 * Moved out of the PHP template so the page enqueues a static file; every
 * value that used to be printed into the script now arrives through
 * wp_localize_script().
 */
/* global floscAdminChatLogsEntries */
	jQuery(function($) {
		var maxId = (parseInt(floscAdminChatLogsEntries.maxId, 10) || 0);
		var selectedUserId = (parseInt(floscAdminChatLogsEntries.selectedUserId, 10) || 0);
		var pollTimer = null;
		var nonce = floscAdminChatLogsEntries.nonce;
		var floscFlowId = floscAdminChatLogsEntries.flowId; // scope the 5s live poll to the selected flow

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
