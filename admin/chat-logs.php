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
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin filter parameter
$flosc_get = wp_unslash($_GET);
$flosc_selected_user_id = isset($flosc_get['flosc_user_id']) ? absint($flosc_get['flosc_user_id']) : 0;
// Scope chat logs to the selected flow. Stored flow_id has no file extension
// (e.g. "dainis_net_ivr"), while $current_ivr is the filename ("dainis_net_ivr.md").
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
?>

<div class="flosc-chat-logs-wrap">
    <h2 class="flosc-chat-logs-title-row">
        <span>Chat Logs</span>
        <a href="<?php echo esc_url($flosc_chat_logs_docs_url); ?>" class="flosc-chat-logs-docs-link">Docs</a>
    </h2>
    <p class="description">All chat exchanges for this flow.<?php echo $flosc_logview === 'sessions' ? ' Grouped by conversation, newest first — click a session to read the thread.' : ' Flat view, newest first. Auto-refreshes every 5 seconds.'; ?></p>
    <?php if ($flosc_selected_user_id > 0): ?>
        <p class="description"><strong>User filter:</strong> Showing only User #<?php echo intval($flosc_selected_user_id); ?>. <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&tab=chat-logs')); ?>">Clear filter</a></p>
    <?php endif; ?>

    <div class="flosc-chat-logs-toolbar">
        <span class="flosc-chat-logs-count">Total: <strong id="flosc-log-count"><?php echo intval($flosc_total_logs); ?></strong> entries</span>

        <span class="flosc-log-viewswitch flosc-log-viewswitch-spaced">
            View:
            <a href="<?php echo esc_url($flosc_sessions_url); ?>" class="<?php echo $flosc_logview === 'sessions' ? 'button button-primary button-small' : 'button button-small'; ?>">Sessions</a>
            <a href="<?php echo esc_url($flosc_flat_url); ?>" class="<?php echo $flosc_logview === 'flat' ? 'button button-primary button-small' : 'button button-small'; ?>">All entries</a>
        </span>

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
    <?php $flosc_sessions = $flosc_logger->flosc_get_sessions($flosc_current_flow_id, 800); ?>
    <?php // Chat Logs styles (.flosc-session*, .flosc-msg*) live in assets/css/flosc-admin.css, enqueued on FLOSC admin pages. ?>
    <div id="flosc-sessions">
        <?php if (empty($flosc_sessions)): ?>
            <p class="description">No conversations yet. They'll appear here as people chat.</p>
        <?php else: ?>
            <?php foreach ($flosc_sessions as $flosc_session): ?>
                <?php echo wp_kses(flosc_render_chat_session($flosc_session), flosc_chat_session_allowed_html()); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php ob_start(); ?>
    jQuery(function($) {
        var nonce = '<?php echo esc_js($flosc_chat_logs_nonce); ?>';

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
                        // Posted as the bot — render like a normal AI (Brenda) message.
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
        'span'    => ['class' => true],
        'div'     => ['class' => true, 'data-msg-id' => true, 'title' => true],
        'em'      => [],
        'strong'  => [],
        'br'      => [],
        'p'       => ['class' => true],
        'button'  => ['type' => true, 'class' => true, 'data-by' => true, 'data-value' => true, 'data-flow' => true, 'data-session' => true],
        'input'   => ['type' => true, 'class' => true, 'placeholder' => true],
        'select'  => ['class' => true, 'title' => true],
        'option'  => ['value' => true, 'selected' => true],
    ];
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
 * @return string
 */
function flosc_render_msg_bubbles($code, $letter, $n, $content, $who, $time, $rid, $css) {
    // One stored field = ONE message = ONE bubble, shown verbatim. We do NOT split
    // it into sub-bubbles — the chat shows each response as a single message (internal
    // line breaks and all), so the log must mirror that, not fragment it.
    $id   = $code . '-' . $letter . '-' . $n;
    $meta = '<div class="flosc-msg-meta">';
    if ($time !== '') {
        $meta .= '<span class="flosc-msg-t">' . esc_html($time) . '</span> ';
    }
    $meta .= '<span class="flosc-msg-id">' . esc_html($id) . '</span> '
        . '<span class="flosc-msg-who">' . esc_html($who) . '</span></div>';
    return '<div class="flosc-msg ' . esc_attr($css) . '" data-msg-id="' . esc_attr($id) . '" title="row ' . intval($rid) . '">'
        . $meta
        . '<div class="flosc-msg-body">' . esc_html(trim((string) $content)) . '</div>'
        . '</div>';
}

/**
 * Render one conversation as a collapsible <details> block (session view).
 *
 * The auto-welcome "[SYSTEM: …]" rows are skipped so only the real back-and-forth
 * shows. The header carries a Delete control that removes the whole conversation.
 */
function flosc_render_chat_session($s) {
    $when  = esc_html(substr((string) ($s['last_ts'] ?? ''), 0, 16)); // Y-m-d H:i
    $turns = intval($s['turns'] ?? 0);
    $label = esc_html($s['label'] ?? '');

    // First real visitor message → preview line in the header.
    $preview = '';
    foreach (($s['rows'] ?? []) as $r) {
        $um = (string) ($r['user_message'] ?? '');
        if ($um !== '' && strncmp($um, '[SYSTEM:', 8) !== 0) {
            $preview = mb_substr($um, 0, 70);
            break;
        }
    }

    $by   = esc_attr($s['by'] ?? '');
    $val  = esc_attr($s['value'] ?? '');
    $flow = esc_attr($s['flow_id'] ?? '');

    $head = '<summary class="flosc-session-head">'
        . '<span class="flosc-session-when">' . $when . '</span>'
        . '<span class="flosc-session-label">' . $label . '</span>'
        . '<span class="flosc-session-turns">' . $turns . ' msg</span>'
        . '<span class="flosc-session-preview">' . ($preview !== '' ? esc_html($preview) : '<em>opened — no messages</em>') . '</span>'
        . '<button type="button" class="button button-small flosc-session-delete" data-by="' . $by . '" data-value="' . $val . '" data-flow="' . $flow . '">Delete</button>'
        . '</summary>';

    $code      = (string) ($s['code'] ?? '');
    $label_raw = (string) ($s['label'] ?? '');
    $thread = '<div class="flosc-session-thread">';
    $shown = 0;
    // Per-speaker counters: bot welcome b-001, visitor reply u-001, bot reply b-002…
    // Admin-joined human lines get their own 'a' counter (a-001…), pale green.
    $u_seq = 0;
    $b_seq = 0;
    $a_seq = 0;
    foreach (($s['rows'] ?? []) as $r) {
        $um  = (string) ($r['user_message'] ?? '');
        $ar  = (string) ($r['ai_response'] ?? '');
        $rid = intval($r['id'] ?? 0);
        $t   = substr((string) ($r['timestamp'] ?? ''), 11, 5);
        $src = (string) ($r['response_source'] ?? '');

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

        // Admin posted AS the bot — renders like a normal AI (Brenda) message.
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
            $thread .= flosc_render_msg_bubbles(
                $code, 'u', str_pad((string) $u_seq, 3, '0', STR_PAD_LEFT),
                $um, $label_raw, $t, $rid, 'flosc-msg-user'
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
    $composer = '';
    if (($s['by'] ?? '') === 'session' && intval($s['value'] ?? 0) > 0) {
        $admin_name = wp_get_current_user()->display_name;
        if ($admin_name === '') {
            $admin_name = 'Admin';
        }
        $bot_name = flosc_get_setting('ai_personality_name', flosc_get_setting('ai_identity_name', 'Brenda'));
        $composer = '<div class="flosc-admin-join">'
            . '<select class="flosc-admin-join-as" title="Send as">'
                . '<option value="admin">' . esc_html($admin_name) . ' (admin)</option>'
                . '<option value="bot">' . esc_html($bot_name) . '</option>'
            . '</select>'
            . '<input type="text" class="flosc-admin-join-input" placeholder="' . esc_attr('Type a message to join the chat…') . '">'
            . '<button type="button" class="button button-small flosc-admin-join-send" data-session="' . esc_attr($s['value']) . '" data-flow="' . esc_attr($s['flow_id'] ?? '') . '">Send</button>'
            . '</div>';
    }

    return '<details class="flosc-session" data-key="' . esc_attr($s['key'] ?? '') . '">' . $head . $thread . $composer . '</details>';
}
?>
