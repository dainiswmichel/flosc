<?php
/**
 * FLOSC Chat Logs Admin Page
 * v1.9.0: Real-time chat log viewer with AJAX polling.
 *
 * Included by admin/settings.php when tab === 'chat-logs'
 * All CSS in assets/css/flosc-admin.css — no inline styles here.
 */

if (!defined('ABSPATH')) exit;

$logger = FLOSC_Chat_Logger::instance();
$total_logs = $logger->flosc_get_log_count();
$recent_logs = $logger->flosc_get_logs(['limit' => 50]);
$flosc_chat_logs_nonce = wp_create_nonce('flosc_chat_logs');
?>

<div class="flosc-chat-logs-wrap">
    <h2>Chat Logs</h2>
    <p class="description">Real-time view of all chat exchanges. Newest first. Auto-refreshes every 5 seconds.</p>

    <div class="flosc-chat-logs-toolbar">
        <span class="flosc-chat-logs-count">Total: <strong id="flosc-log-count"><?php echo intval($total_logs); ?></strong> entries</span>

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

        <button type="button" id="flosc-log-clear-btn" class="button" title="Clear logs older than 30 days">Clear Old Logs</button>
    </div>

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
            </tr>
        </thead>
        <tbody id="flosc-chat-logs-body">
            <?php if (empty($recent_logs)): ?>
                <tr id="flosc-log-empty-row"><td colspan="7">No chat logs yet. Logs will appear here as users chat.</td></tr>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                    <?php echo flosc_render_chat_log_row($log); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
jQuery(function($) {
    var maxId = <?php echo intval($recent_logs[0]['id'] ?? 0); ?>;
    var pollTimer = null;
    var nonce = '<?php echo esc_js($flosc_chat_logs_nonce); ?>';

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

    // Start polling on load
    if ($('#flosc-log-auto-refresh').is(':checked')) {
        startPolling();
    }
});
</script>

<?php
/**
 * Render a single chat log table row.
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

    return '<tr data-id="' . intval($log['id']) . '">'
        . '<td class="flosc-log-col-time"><abbr title="' . esc_attr($date) . '">' . esc_html($time) . '</abbr></td>'
        . '<td class="flosc-log-col-user">' . $user . '</td>'
        . '<td class="flosc-log-col-phase"><span class="flosc-log-phase-badge">' . $phase . '</span></td>'
        . '<td class="flosc-log-col-message">' . $msg . '</td>'
        . '<td class="flosc-log-col-response">' . $resp . '</td>'
        . '<td class="flosc-log-col-meta">' . $meta . '</td>'
        . '<td class="flosc-log-col-ms">' . $ms . '</td>'
        . '</tr>';
}
?>
