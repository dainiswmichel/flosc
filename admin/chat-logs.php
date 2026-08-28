<?php
/**
 * FLOSC Chat Logs Admin Page
 * v1.9.0: Real-time chat log viewer with AJAX polling.
 * v8.0.0: Session view — conversations grouped, click to expand the thread, the
 *         auto-welcome "[SYSTEM: …]" greetings filtered out, and a per-session
 *         delete. The original flat table lives on under the "All entries" view.
 *
 * Included by admin/settings.php when tab === 'chat-logs'
 * Front-end CSS for the flat table is in assets/css/flosc-admin.css; the small,
 * self-contained session-view styling is scoped inline below so the feature is
 * one file.
 */

if (!defined('ABSPATH')) exit;

$flosc_logger = FLOSC_Chat_Logger::instance();
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_chat_logs_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-chat-logs';
// $flosc_get prepared by admin/settings.php (filters are display-only).
if ( ! isset( $flosc_get ) || ! is_array( $flosc_get ) ) {
	$flosc_get = array();
}
$flosc_selected_user_id = isset( $flosc_get['flosc_user_id'] ) ? absint( $flosc_get['flosc_user_id'] ) : 0;
// Scope chat logs to the selected flow. Stored flow_id has no file extension
// (e.g. "flow_ivr"), while $current_ivr is the filename ("flow_ivr.md").
$flosc_current_flow_id = $flosc_current_ivr !== '' ? pathinfo($flosc_current_ivr, PATHINFO_FILENAME) : '';
$flosc_total_logs = $flosc_logger->flosc_get_log_count($flosc_current_flow_id);
$flosc_chat_logs_nonce = wp_create_nonce('flosc_chat_logs');

// Two ways to read the same logs: grouped by conversation (default) or the flat
// chronological table. The flat view keeps the live 5s poll + rating widgets.
$flosc_logview = (isset($flosc_get['logview']) && $flosc_get['logview'] === 'flat') ? 'flat' : 'sessions';
$flosc_view_base = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'chat-logs',
], admin_url('admin.php'));
$flosc_sessions_url = add_query_arg('logview', 'sessions', $flosc_view_base);
$flosc_flat_url     = add_query_arg('logview', 'flat', $flosc_view_base);
$flosc_session_scope = (isset($flosc_get['session_scope']) && $flosc_get['session_scope'] === 'archived') ? 'archived' : 'active';
$flosc_sessions_active_url = add_query_arg([
    'logview' => 'sessions',
    'session_scope' => 'active',
], $flosc_view_base);
$flosc_sessions_archived_url = add_query_arg([
    'logview' => 'sessions',
    'session_scope' => 'archived',
], $flosc_view_base);
?>

<div class="flosc-chat-logs-wrap">
    <h2 class="flosc-chat-logs-title-row">
        <span>Chat Logs</span>
        <a href="<?php echo esc_url($flosc_chat_logs_docs_url); ?>" class="flosc-chat-logs-docs-link">Docs</a>
    </h2>
    <p class="description">All chat exchanges for this flow.<?php echo esc_html( $flosc_logview === 'sessions' ? ' Grouped by conversation, newest first — click a session to read the thread.' : ' Flat view, newest first. Auto-refreshes every 5 seconds.' ); ?></p>
    <?php if ($flosc_selected_user_id > 0): ?>
        <p class="description"><strong>User filter:</strong> Showing only User #<?php echo intval($flosc_selected_user_id); ?>. <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=chat-logs')); ?>">Clear filter</a></p>
    <?php endif; ?>

    <div class="flosc-chat-logs-toolbar">
        <span class="flosc-chat-logs-count">Total: <strong id="flosc-log-count"><?php echo intval($flosc_total_logs); ?></strong> entries</span>

        <span class="flosc-log-viewswitch flosc-log-viewswitch-spaced">
            View:
            <a href="<?php echo esc_url($flosc_sessions_url); ?>" class="<?php echo esc_attr( $flosc_logview === 'sessions' ? 'button button-primary button-small' : 'button button-small' ); ?>">Sessions</a>
            <a href="<?php echo esc_url($flosc_flat_url); ?>" class="<?php echo esc_attr( $flosc_logview === 'flat' ? 'button button-primary button-small' : 'button button-small' ); ?>">All entries</a>
        </span>

        <?php if ($flosc_logview === 'sessions'): ?>
            <span class="flosc-log-viewswitch flosc-log-viewswitch-spaced">
                Scope:
                <a href="<?php echo esc_url($flosc_sessions_active_url); ?>" class="<?php echo esc_attr( $flosc_session_scope === 'active' ? 'button button-primary button-small' : 'button button-small' ); ?>">Active</a>
                <a href="<?php echo esc_url($flosc_sessions_archived_url); ?>" class="<?php echo esc_attr( $flosc_session_scope === 'archived' ? 'button button-primary button-small' : 'button button-small' ); ?>">Archived</a>
            </span>
        <?php endif; ?>

        <?php if ($flosc_logview === 'flat'): ?>
            <label for="flosc-log-filter-phase">Phase:</label>
            <select id="flosc-log-filter-phase" class="flosc-ai-model-select">
                <option value="">All</option>
                <option value="freeline">Freeline</option>
                <option value="login">Login</option>
                <option value="offer">Offer</option>
                <option value="sale">Sale</option>
                <option value="content">Content</option>
            </select>

            <label>
                <input type="checkbox" id="flosc-log-auto-refresh" checked> Auto-refresh
            </label>

            <button type="button" id="flosc-log-refresh-btn" class="button">Refresh Now</button>
        <?php else: ?>
            <button type="button" id="flosc-sessions-refresh" class="button">Refresh</button>
        <?php endif; ?>

        <button type="button" id="flosc-log-clear-btn" class="button" title="Clear logs older than 30 days">Clear Old Logs</button>
    </div>

<?php if ($flosc_logview === 'sessions'): ?>
    <?php $flosc_sessions = $flosc_logger->flosc_get_sessions($flosc_current_flow_id, 800, $flosc_session_scope); ?>
    <?php // Chat Logs styles (.flosc-session*, .flosc-msg*) live in assets/css/flosc-admin.css, enqueued on FLOSC admin pages. ?>
    <div class="flosc-session-bulk-toolbar">
        <label class="flosc-session-bulk-checkall">
            <input type="checkbox" id="flosc-session-select-all">
            <span>Select all</span>
        </label>
        <button type="button" class="button" id="flosc-session-download-selected">Download Selected TSV</button>
        <button type="button" class="button" id="flosc-session-archive-selected"><?php echo esc_html( $flosc_session_scope === 'archived' ? 'Restore Selected' : 'Archive Selected' ); ?></button>
        <button type="button" class="button button-link-delete" id="flosc-session-delete-selected">Delete Selected</button>
        <span class="flosc-session-selection-status" id="flosc-session-selection-status">0 selected</span>
    </div>

    <form id="flosc-session-download-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="flosc_download_chat_tsv">
        <input type="hidden" name="flow_id" value="<?php echo esc_attr($flosc_current_flow_id); ?>">
        <input type="hidden" name="sessions" id="flosc-session-download-payload" value="">
        <?php wp_nonce_field('flosc_download_chat_tsv'); ?>
    </form>

    <div id="flosc-sessions">
        <?php if (empty($flosc_sessions)): ?>
            <p class="description"><?php echo esc_html( $flosc_session_scope === 'archived' ? 'No archived conversations for this flow yet.' : 'No conversations yet. They\'ll appear here as people chat.' ); ?></p>
        <?php else: ?>
            <?php foreach ($flosc_sessions as $flosc_session): ?>
                <?php echo wp_kses(flosc_render_chat_session($flosc_session), flosc_chat_session_allowed_html()); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php ob_start(); ?>
    jQuery(function($) {
        var nonce = '<?php echo esc_js($flosc_chat_logs_nonce); ?>';
        var archiveOperation = '<?php echo esc_js($flosc_session_scope === 'archived' ? 'restore' : 'archive'); ?>';

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
                flow_id: '<?php echo esc_js($flosc_current_flow_id); ?>',
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
    <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<?php else: ?>
    <?php
    $flosc_recent_logs = $flosc_logger->flosc_get_logs([
        'limit'   => 50,
        'user_id' => $flosc_selected_user_id,
        'flow_id' => $flosc_current_flow_id,
    ]);
    ?>
    <table class="widefat striped flosc-chat-logs-table">
        <thead>
            <tr>
                <th class="flosc-log-col-time">Time</th>
                <th class="flosc-log-col-user">User</th>
                <th class="flosc-log-col-phase">Phase</th>
                <th class="flosc-log-col-message">User Message</th>
                <th class="flosc-log-col-response">AI Response</th>
                <th class="flosc-log-col-meta">Source / Provider</th>
                <th class="flosc-log-col-ms">ms</th>
                <th class="flosc-log-col-rating">Rating</th>
            </tr>
        </thead>
        <tbody id="flosc-chat-logs-body">
            <?php if (empty($flosc_recent_logs)): ?>
                <tr id="flosc-log-empty-row"><td colspan="8">No chat logs yet. Logs will appear here as users chat.</td></tr>
            <?php else: ?>
                <?php foreach ($flosc_recent_logs as $flosc_log): ?>
                    <?php echo wp_kses_post( flosc_render_chat_log_row($flosc_log) ); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php ob_start(); ?>
    jQuery(function($) {
        var maxId = <?php echo intval($flosc_recent_logs[0]['id'] ?? 0); ?>;
        var selectedUserId = <?php echo intval($flosc_selected_user_id); ?>;
        var pollTimer = null;
        var nonce = '<?php echo esc_js($flosc_chat_logs_nonce); ?>';
        var floscFlowId = '<?php echo esc_js($flosc_current_flow_id); ?>'; // scope the 5s live poll to the selected flow

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
            var user = log.user_id > 0 ? ('User #' + log.user_id) : ('Visitor');
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
    <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
<?php endif; ?>
</div>

<?php
/**
 * Render a single chat log table row (flat "All entries" view).
 */
function flosc_render_chat_log_row($log) {
    $time = substr($log['timestamp'] ?? '', 11, 8);
    $date = substr($log['timestamp'] ?? '', 0, 10);
    $user = $log['user_id'] > 0 ? ('User #' . intval($log['user_id'])) : 'Visitor';
    $phase = esc_html($log['phase'] ?? '');
    $msg = esc_html($log['user_message'] ?? '');
    $resp = esc_html(mb_substr($log['ai_response'] ?? '', 0, 200));
    if (mb_strlen($log['ai_response'] ?? '') > 200) $resp .= '…';

    $source = esc_html($log['response_source'] ?? 'ivr');
    $provider = esc_html($log['provider'] ?? '');
    $chain = esc_html($log['chain_detail'] ?? '');
    $meta = $source;
    if ($provider && $provider !== $source) $meta .= ' / ' . $provider;
    if ($chain) $meta .= ' (' . $chain . ')';
    $ms = intval($log['response_time_ms'] ?? 0);

    // v1.9.5: Rating widget
    $rating = intval($log['admin_rating'] ?? 0);
    $note = esc_attr($log['admin_note'] ?? '');
    $log_id = intval($log['id']);
    $rating_class = $rating > 0 ? 'flosc-rating-positive' : ($rating < 0 ? 'flosc-rating-negative' : 'flosc-rating-neutral');

    $rating_cell = '<td class="flosc-log-col-rating">'
        . '<div class="flosc-log-rating" data-log-id="' . $log_id . '">'
        . '<input type="number" min="-10" max="10" step="1" value="' . $rating . '" '
        . 'class="flosc-rating-input ' . $rating_class . '" title="-10 (worst) to +10 (best)">'
        . '<input type="text" value="' . $note . '" class="flosc-rating-note" placeholder="Note">'
        . '<button type="button" class="button button-small flosc-save-rating">Save</button>'
        . '</div>'
        . '</td>';

    return '<tr data-id="' . $log_id . '">'
        . '<td class="flosc-log-col-time"><abbr title="' . esc_attr($date) . '">' . esc_html($time) . '</abbr></td>'
        . '<td class="flosc-log-col-user">' . $user . '</td>'
        . '<td class="flosc-log-col-phase"><span class="flosc-log-phase-badge">' . $phase . '</span></td>'
        . '<td class="flosc-log-col-message">' . $msg . '</td>'
        . '<td class="flosc-log-col-response">' . $resp . '</td>'
        . '<td class="flosc-log-col-meta">' . $meta . '</td>'
        . '<td class="flosc-log-col-ms">' . $ms . '</td>'
        . $rating_cell
        . '</tr>';
}

/**
 * Allowed HTML for a rendered session block (passed to wp_kses on output).
 */
function flosc_chat_session_allowed_html() {
    return [
        'details' => ['class' => true, 'data-key' => true],
        'summary' => ['class' => true],
        'span'    => ['class' => true, 'aria-hidden' => true],
        'div'     => ['class' => true, 'data-msg-id' => true, 'title' => true],
        'em'      => [],
        'strong'  => [],
        'br'      => [],
        'p'       => ['class' => true],
        'button'  => ['type' => true, 'class' => true, 'data-by' => true, 'data-value' => true, 'data-flow' => true, 'data-session' => true, 'data-operation' => true],
        'input'   => ['type' => true, 'class' => true, 'placeholder' => true, 'value' => true, 'min' => true, 'step' => true, 'data-by' => true, 'data-value' => true, 'checked' => true],
        'select'  => ['class' => true, 'title' => true],
        'option'  => ['value' => true, 'selected' => true],
        'a'       => ['href' => true, 'class' => true],
    ];
}

/**
 * Format a MySQL timestamp into Michel Date Stamp (UTC).
 * Example: 2026-07m-10d-UTC08h:26m13s
 */
function flosc_format_mts_utc($timestamp) {
    $raw = trim((string) $timestamp);
    if ($raw === '') {
        return '';
    }

    try {
        $site_tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        $dt_local = new DateTimeImmutable($raw, $site_tz);
        $dt_utc = $dt_local->setTimezone(new DateTimeZone('UTC'));
        return $dt_utc->format('Y-m-d\\d-\\U\\T\\CH\\h:i\\m') . $dt_utc->format('s') . 's';
    } catch (Exception $e) {
        return esc_html($raw);
    }
}

/**
 * Extract one context token from chain_detail.
 */
function flosc_get_chain_context_value($chain_detail, $key) {
    $chain_detail = (string) $chain_detail;
    $needle = $key . ':';
    if ($chain_detail === '' || strpos($chain_detail, $needle) === false) {
        return '';
    }

    $parts = array_map('trim', explode('→', $chain_detail));
    foreach ($parts as $part) {
        if (strpos($part, $needle) === 0) {
            return trim(substr($part, strlen($needle)));
        }
    }

    return '';
}

/**
 * Render one speaker's turn as one or more message bubbles with stable ids.
 *
 * The turn is split on blank lines into the bubbles the chat would show. A single
 * bubble is "{code}-{u|b}-{NNN}"; several become "…-NNN.1", "…-NNN.2", … — reading
 * like a nested list. The source row id rides along in the title attribute so the
 * true chronological order (the database sort) is always one hover away.
 *
 * @param string $code    Conversation code (6-char).
 * @param string $letter  'u' (visitor) or 'b' (bot).
 * @param string $n       Zero-padded turn number (001…).
 * @param string $content The turn's text.
 * @param string $who     Speaker label.
 * @param string $time    HH:MM, shown on the first bubble only ('' to omit).
 * @param int    $rid     Source row id (hover title).
 * @param string $css     Bubble CSS class.
 * @param string $context_url Optional visitor page URL context.
 * @return string
 */
function flosc_render_msg_bubbles($code, $letter, $n, $content, $who, $time, $rid, $css, $context_url = '') {
    // One stored field = ONE message = ONE bubble, shown verbatim. We do NOT split
    // it into sub-bubbles — the chat shows each response as a single message (internal
    // line breaks and all), so the log must mirror that, not fragment it.
    $flosc_id   = $code . '-' . $letter . '-' . $n;
    $meta = '<div class="flosc-msg-meta">';
    if ($time !== '') {
        $meta .= '<span class="flosc-msg-t">' . esc_html($time) . '</span> ';
    }
    $meta .= '<span class="flosc-msg-id">' . esc_html($flosc_id) . '</span> '
        . '<span class="flosc-msg-who">' . esc_html($who) . '</span></div>';

    $hint = '';
    $trimmed_content = trim((string) $content);
    if (
        strncmp($trimmed_content, '[GUEST ACCOUNT REQUEST SUBMITTED]', 33) === 0
        || strncmp($trimmed_content, '[CONTACT FORM SUBMITTED]', 24) === 0
    ) {
        $current_ivr = sanitize_file_name((string) ($GLOBALS['flosc_current_ivr'] ?? ''));
        $register_login_url = add_query_arg([
            'page' => 'flosc-settings',
            'tab'  => 'login',
            'ivr'  => $current_ivr,
        ], admin_url('admin.php'));
        $hint = '<p class="description">Moderation actions are in <a href="' . esc_url($register_login_url) . '">Register &amp; Login</a>: Approve, Approve + Send MagicLink, Deny + Block, or Delete.</p>';
    }

    if ($letter === 'u') {
        $safe_context_url = esc_url((string) $context_url);
        if ($safe_context_url !== '') {
            $hint .= '<p class="description">Visitor URL: <a href="' . $safe_context_url . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $context_url) . '</a></p>';
        }
    }

    return '<div class="flosc-msg ' . esc_attr($css) . '" data-msg-id="' . esc_attr($flosc_id) . '" title="row ' . intval($rid) . '">'
        . $meta
        . '<div class="flosc-msg-body">' . esc_html(trim((string) $content)) . '</div>'
        . $hint
        . '</div>';
}

/**
 * Render one conversation as a collapsible <details> block (session view).
 *
 * The auto-welcome "[SYSTEM: …]" rows are skipped so only the real back-and-forth
 * shows. The header carries a Delete control that removes the whole conversation.
 */
function flosc_render_chat_session($flosc_s) {
    $when  = esc_html(flosc_format_mts_utc((string) ($flosc_s['last_ts'] ?? '')));
    $turns = intval($flosc_s['turns'] ?? 0);
    $label = esc_html($flosc_s['label'] ?? '');
    $is_archived = !empty($flosc_s['is_archived']);

    // First real visitor message → preview line in the header.
    $flosc_preview = '';
    foreach (($flosc_s['rows'] ?? []) as $r) {
        $um = (string) ($r['user_message'] ?? '');
        if ($um !== '' && strncmp($um, '[SYSTEM:', 8) !== 0) {
            $flosc_preview = mb_substr($um, 0, 70);
            break;
        }
    }

    $by   = esc_attr($flosc_s['by'] ?? '');
    $val  = esc_attr($flosc_s['value'] ?? '');
    $flow = esc_attr($flosc_s['flow_id'] ?? '');
    $download_url = wp_nonce_url(
        add_query_arg([
            'action' => 'flosc_download_chat_tsv',
            'flow_id' => $flow,
            'by' => $by,
            'value' => $val,
        ], admin_url('admin-post.php')),
        'flosc_download_chat_tsv'
    );

    $head = '<summary class="flosc-session-head">'
        . '<span class="flosc-session-caret" aria-hidden="true">&rsaquo;</span>'
        . '<input type="checkbox" class="flosc-session-select flosc-session-control" data-by="' . $by . '" data-value="' . $val . '">'
        . '<span class="flosc-session-when">' . $when . '</span>'
        . '<span class="flosc-session-label">' . $label . '</span>'
        . '<span class="flosc-session-turns">' . $turns . ' msg</span>'
        . '<span class="flosc-session-preview">' . ($flosc_preview !== '' ? esc_html($flosc_preview) : '<em>opened — no messages</em>') . '</span>'
        . '<a href="' . esc_url($download_url) . '" class="button button-small flosc-session-download flosc-session-control">Download TSV</a>'
        . '<button type="button" class="button button-small flosc-session-archive flosc-session-control" data-operation="' . ($is_archived ? 'restore' : 'archive') . '" data-by="' . $by . '" data-value="' . $val . '" data-flow="' . $flow . '">' . ($is_archived ? 'Restore' : 'Archive') . '</button>'
        . '<button type="button" class="button button-small flosc-session-delete" data-by="' . $by . '" data-value="' . $val . '" data-flow="' . $flow . '">Delete</button>'
        . '</summary>';

    $code      = (string) ($flosc_s['code'] ?? '');
    $label_raw = (string) ($flosc_s['label'] ?? '');
    $thread = '<div class="flosc-session-thread">';
    $shown = 0;
    // Per-speaker counters: bot welcome b-001, visitor reply u-001, bot reply b-002…
    // Admin-joined human lines get their own 'a' counter (a-001…), pale green.
    $u_seq = 0;
    $b_seq = 0;
    $a_seq = 0;
    foreach (($flosc_s['rows'] ?? []) as $r) {
        $um  = (string) ($r['user_message'] ?? '');
        $ar  = (string) ($r['ai_response'] ?? '');
        $rid = intval($r['id'] ?? 0);
        $t   = flosc_format_mts_utc((string) ($r['timestamp'] ?? ''));
        $src = (string) ($r['response_source'] ?? '');

        // VGM state change — a divider, not a message. It has no speaker, so it
        // gets no u-/b-/a- sequence number and does not count as a turn.
        //   +G  account created just now      G  signed in, account already existed
        //   +M  became a member just now      M  signed in, already a member here
        if ($src === 'state_change') {
            $thread .= '<div class="flosc-msg flosc-msg-state" title="row ' . $rid . '">'
                . '<span class="flosc-msg-state-rule" aria-hidden="true"></span>'
                . '<span class="flosc-msg-state-label">' . esc_html(trim($ar)) . '</span>'
                . '<span class="flosc-msg-state-t">' . esc_html($t) . '</span>'
                . '<span class="flosc-msg-state-rule" aria-hidden="true"></span>'
                . '</div>';
            $shown++;
            continue;
        }

        // Admin-joined human message — pale green, "Name (admin)" (italic), letter 'a'.
        if ($src === 'admin') {
            $a_seq++;
            $aid   = $code . '-a-' . str_pad((string) $a_seq, 3, '0', STR_PAD_LEFT);
            $aname = esc_html(($r['provider'] ?? '') !== '' ? $r['provider'] : 'Admin');
            $thread .= '<div class="flosc-msg flosc-msg-admin" data-msg-id="' . esc_attr($aid) . '" title="row ' . $rid . '">'
                . '<div class="flosc-msg-meta"><span class="flosc-msg-t">' . esc_html($t) . '</span> '
                . '<span class="flosc-msg-id">' . esc_html($aid) . '</span> '
                . '<span class="flosc-msg-who">' . $aname . ' <em>(admin)</em></span></div>'
                . '<div class="flosc-msg-body">' . esc_html(trim($ar)) . '</div>'
                . '</div>';
            $shown++;
            continue;
        }

        // Admin posted AS the bot — renders like a normal assistant message.
        if ($src === 'admin_bot') {
            $b_seq++;
            $thread .= flosc_render_msg_bubbles(
                $code, 'b', str_pad((string) $b_seq, 3, '0', STR_PAD_LEFT),
                $ar, 'AI', $t, $rid, 'flosc-msg-ai'
            );
            $shown++;
            continue;
        }

        $is_system = (strncmp($um, '[SYSTEM:', 8) === 0); // the auto-welcome row

        // The visitor's message — hidden only for the auto-welcome's "[SYSTEM:…]" prompt.
        if (!$is_system) {
            $u_seq++;
            $visitor_context_url = flosc_get_chain_context_value((string) ($r['chain_detail'] ?? ''), 'ctx_url');
            $thread .= flosc_render_msg_bubbles(
                $code, 'u', str_pad((string) $u_seq, 3, '0', STR_PAD_LEFT),
                $um, $label_raw, $t, $rid, 'flosc-msg-user', $visitor_context_url
            );
        }

        // The bot's message — shown for EVERY row, including the opening welcome the
        // visitor actually saw (we just don't echo the internal "[SYSTEM:…]" prompt).
        $b_seq++;
        $thread .= flosc_render_msg_bubbles(
            $code, 'b', str_pad((string) $b_seq, 3, '0', STR_PAD_LEFT),
            $ar, 'AI', ($is_system ? $t : ''), $rid, 'flosc-msg-ai'
        );
        $shown++;
    }
    if ($shown === 0) {
        $thread .= '<p class="description">No messages in this conversation yet.</p>';
    }
    $thread .= '</div>';

    // Admin-join composer — shown when the conversation has a deliverable session id
    // (visitors now carry one). Posting drops a pale-green "(admin)" line at the
    // bottom; the visitor's widget shows it on its next poll.
    //
    // A journey-grouped conversation is keyed by its journey id, but delivery
    // still needs the numeric session id, so read it from deliver_session_id
    // (which flosc_get_sessions() fills from the newest row that carries one).
    // For a session-grouped conversation the two are the same value.
    $flosc_deliver_session = intval($flosc_s['deliver_session_id'] ?? 0);
    if ($flosc_deliver_session <= 0 && ($flosc_s['by'] ?? '') === 'session') {
        $flosc_deliver_session = intval($flosc_s['value'] ?? 0);
    }

    $composer = '';
    if ($flosc_deliver_session > 0) {
        $admin_name = wp_get_current_user()->display_name;
        if ($admin_name === '') {
            $admin_name = 'Admin';
        }
        $bot_name = flosc_get_setting('ai_personality_name', flosc_get_setting('ai_identity_name', 'Site Assistant'));

        $latest_usage_row = null;
        $latest_row = null;
        $latest_context_row = null;
        $session_rows = is_array($flosc_s['rows'] ?? null) ? $flosc_s['rows'] : [];
        for ($i = count($session_rows) - 1; $i >= 0; $i--) {
            $row = $session_rows[$i];
            if (!is_array($latest_row)) {
                $latest_row = $row;
            }
            $has_usage = intval($row['billing_total_tokens'] ?? 0) > 0
                || intval($row['billing_real_millicents'] ?? 0) > 0
                || ((string) ($row['billing_source'] ?? '') !== '' && (string) ($row['billing_source'] ?? '') !== 'none');
            if ($has_usage) {
                $latest_usage_row = $row;
            }

            $chain_detail = (string) ($row['chain_detail'] ?? '');
            if (!is_array($latest_context_row) && strpos($chain_detail, 'ctx_') !== false) {
                $latest_context_row = $row;
            }

            if (is_array($latest_usage_row) && is_array($latest_context_row) && is_array($latest_row)) {
                break;
            }
        }

        if (is_array($latest_context_row)) {
            $chain_detail = (string) ($latest_context_row['chain_detail'] ?? '');
            $ctx_parts = array_map('trim', explode('→', $chain_detail));
            $ctx = [
                'surface' => '',
                'url' => '',
                'path' => '',
                'title' => '',
                'ref' => '',
            ];
            foreach ($ctx_parts as $part) {
                if (strpos($part, 'ctx_surface:') === 0) {
                    $ctx['surface'] = trim(substr($part, strlen('ctx_surface:')));
                } elseif (strpos($part, 'ctx_url:') === 0) {
                    $ctx['url'] = trim(substr($part, strlen('ctx_url:')));
                } elseif (strpos($part, 'ctx_path:') === 0) {
                    $ctx['path'] = trim(substr($part, strlen('ctx_path:')));
                } elseif (strpos($part, 'ctx_title:') === 0) {
                    $ctx['title'] = trim(substr($part, strlen('ctx_title:')));
                } elseif (strpos($part, 'ctx_ref:') === 0) {
                    $ctx['ref'] = trim(substr($part, strlen('ctx_ref:')));
                }
            }

            $ctx_lines = [];
            if ($ctx['surface'] !== '') {
                $ctx_lines[] = 'Surface: ' . esc_html($ctx['surface']);
            }
            if ($ctx['url'] !== '') {
                $ctx_lines[] = 'Page URL: ' . esc_html($ctx['url']);
            }
            if ($ctx['path'] !== '') {
                $ctx_lines[] = 'Path: ' . esc_html($ctx['path']);
            }
            if ($ctx['title'] !== '') {
                $ctx_lines[] = 'Title: ' . esc_html($ctx['title']);
            }
            if ($ctx['ref'] !== '') {
                $ctx_lines[] = 'Referrer: ' . esc_html($ctx['ref']);
            }

            if (!empty($ctx_lines)) {
                $composer .= '<div class="flosc-admin-session-context">'
                    . '<p class="description"><strong>Session Source Context</strong><br>'
                    . implode('<br>', $ctx_lines)
                    . '</p>'
                    . '</div>';
            }
        }

        if (is_array($latest_usage_row)) {
            $usage_provider = esc_html((string) ($latest_usage_row['provider'] ?? 'unknown'));
            $usage_model = esc_html((string) ($latest_usage_row['billing_model'] ?? 'not reported'));
            $usage_source = esc_html((string) ($latest_usage_row['billing_source'] ?? 'none'));
            $usage_input = intval($latest_usage_row['billing_input_tokens'] ?? 0);
            $usage_output = intval($latest_usage_row['billing_output_tokens'] ?? 0);
            $usage_total = intval($latest_usage_row['billing_total_tokens'] ?? 0);
            $usage_real_millicents = intval($latest_usage_row['billing_real_millicents'] ?? 0);
            $usage_real_cents = number_format_i18n($usage_real_millicents / 1000, 4);

            $composer .= '<div class="flosc-admin-usage-report">'
                . '<p class="description"><strong>Latest AI API Usage Report</strong><br>'
                . 'Provider: ' . $usage_provider . '<br>'
                . 'Model: ' . $usage_model . '<br>'
                . 'API reported tokens (input/output/total): ' . esc_html(number_format_i18n($usage_input)) . ' / ' . esc_html(number_format_i18n($usage_output)) . ' / ' . esc_html(number_format_i18n($usage_total)) . '<br>'
                . 'API reported actual usage cost: ' . esc_html(number_format_i18n($usage_real_millicents)) . ' millicents (' . esc_html($usage_real_cents) . ' cents)<br>'
                . 'Cost source: ' . $usage_source
                . '</p>'
                . '</div>';
        } else {
            $latest_provider = is_array($latest_row) ? esc_html((string) ($latest_row['provider'] ?? 'ivr')) : 'ivr';
            $latest_response_source = is_array($latest_row) ? esc_html((string) ($latest_row['response_source'] ?? 'ivr')) : 'ivr';
            $latest_billing_source = is_array($latest_row) ? esc_html((string) ($latest_row['billing_source'] ?? 'none')) : 'none';
            $composer .= '<div class="flosc-admin-usage-report">'
                . '<p class="description"><strong>Latest AI API Usage Report</strong><br>'
                . 'No provider usage metrics are logged for this session yet.<br>'
                . 'Latest row state: response source = ' . $latest_response_source
                . ', provider = ' . $latest_provider
                . ', billing source = ' . $latest_billing_source
                . '</p>'
                . '</div>';
        }

        $composer .= '<div class="flosc-admin-join">'
            . '<select class="flosc-admin-join-as" title="Send as">'
                . '<option value="admin">' . esc_html($admin_name) . ' (admin)</option>'
                . '<option value="bot">' . esc_html($bot_name) . '</option>'
            . '</select>'
            . '<input type="text" class="flosc-admin-join-input" placeholder="' . esc_attr('Type a message to join the chat…') . '">'
            . '<button type="button" class="button button-small flosc-admin-join-send" data-session="' . esc_attr((string) $flosc_deliver_session) . '" data-flow="' . esc_attr($flosc_s['flow_id'] ?? '') . '">Send</button>'
            . '</div>';

        $composer .= '<div class="flosc-admin-assign-tokens">'
            . '<input type="number" class="flosc-admin-token-amount" min="1" step="1" value="" placeholder="Token amount">'
            . '<button type="button" class="button button-small flosc-admin-assign-send" data-session="' . esc_attr((string) $flosc_deliver_session) . '" data-flow="' . esc_attr($flosc_s['flow_id'] ?? '') . '">Assign Tokens</button>'
            . '</div>';
    }

    return '<details class="flosc-session" data-key="' . esc_attr($flosc_s['key'] ?? '') . '">' . $head . $thread . $composer . '</details>';
}
?>
