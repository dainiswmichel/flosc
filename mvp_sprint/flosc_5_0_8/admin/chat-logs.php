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
                <th class="flosc-log-col-rating">Rating</th>
            </tr>
        </thead>
        <tbody id="flosc-chat-logs-body">
            <?php if (empty($recent_logs)): ?>
                <tr id="flosc-log-empty-row"><td colspan="8">No chat logs yet. Logs will appear here as users chat.</td></tr>
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
?>
