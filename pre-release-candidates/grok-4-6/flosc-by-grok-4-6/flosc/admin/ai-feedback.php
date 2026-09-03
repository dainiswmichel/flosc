<?php
/**
 * v1.9.0: AI Feedback & Praise Editor
 * v1.9.5: Added "Rated Responses" section showing DB-rated chat log entries.
 * 
 * Admin can view, add, and delete feedback (flag bad responses) and
 * praises (reinforce good responses) that guide AI behavior.
 * Manual entries stored per-flow in flow settings.
 * Rated entries stored directly in flosc_chat_logs table (admin_rating column).
 * Both are loaded into the system prompt via build_feedback_prompt().
 * 
 * Included from settings.php within the Chat Logs tab.
 */

if (!defined('ABSPATH')) exit;

// ── v1.9.5: Rated Responses from DB (via logger data API; schema ensured there) ──
$flosc_rated_logs  = FLOSC_Chat_Logger::instance()->flosc_get_rated_logs( 50 );
$flosc_rated_count = count( $flosc_rated_logs );
?>

<?php if ($flosc_rated_count > 0): ?>
<div class="flosc-feedback-section">
    <h3 class="flosc-feedback-header">
        Rated Responses
        <span class="flosc-feedback-count flosc-feedback-count--rated"><?php echo esc_html( (string) $flosc_rated_count ); ?></span>
    </h3>
    <p class="flosc-feedback-description">
        These are chat log entries you scored in the Chat Logs tab above. 
        Negative scores tell the AI what to avoid. Positive scores reinforce good behavior. 
        All rated entries are protected from auto-expunge.
    </p>

    <table class="flosc-feedback-table widefat striped">
        <thead>
            <tr>
                <th class="flosc-feedback-col-score">Score</th>
                <th class="flosc-feedback-col-rated">Rated</th>
                <th>User Said</th>
                <th>AI Response</th>
                <th>Admin Note</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($flosc_rated_logs as $flosc_rlog): ?>
            <?php
                $flosc_score = intval($flosc_rlog['admin_rating']);
                $flosc_score_class = $flosc_score > 0 ? 'flosc-rating-positive' : ($flosc_score < 0 ? 'flosc-rating-negative' : '');
                $flosc_score_display = $flosc_score > 0 ? '+' . $flosc_score : $flosc_score;
            ?>
            <tr>
                <td>
                    <span class="flosc-rated-score <?php echo esc_attr( $flosc_score_class ); ?>"><?php echo esc_html( (string) $flosc_score_display ); ?></span>
                </td>
                <td><?php echo esc_html(substr($flosc_rlog['rated_at'] ?? '', 0, 16)); ?></td>
                <td><?php echo esc_html(mb_strimwidth($flosc_rlog['user_message'] ?? '', 0, 100, '...')); ?></td>
                <td><?php echo esc_html(mb_strimwidth($flosc_rlog['ai_response'] ?? '', 0, 150, '...')); ?></td>
                <td><?php echo esc_html($flosc_rlog['admin_note'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
// ── Feedback ──
$flosc_feedback_items = $flosc_flow_settings['ai_feedback'] ?? [];
$flosc_feedback_count = count($flosc_feedback_items);

// Handle delete feedback
if (isset($_POST['flosc_delete_feedback'])) {
    $flosc_post = wp_unslash($_POST);
    if (wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
        if (empty($flosc_selected_flow_id) || !flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
            wp_die(esc_html__('You do not have permission to manage AI feedback for this flow.', 'flosc'));
        }
        $flosc_delete_id = sanitize_text_field($flosc_post['flosc_delete_feedback'] ?? '');
        $flosc_feedback_items = array_values(array_filter($flosc_feedback_items, function($c) use ($flosc_delete_id) {
            return ($c['id'] ?? '') !== $flosc_delete_id;
        }));
        $flosc_flow_settings['ai_feedback'] = $flosc_feedback_items;
        update_option($settings_key, $flosc_flow_settings);
        $flosc_feedback_count = count($flosc_feedback_items);
        echo '<div class="notice notice-success is-dismissible"><p>Feedback deleted.</p></div>';
    }
}

// Handle add feedback
if (isset($_POST['flosc_add_feedback'])) {
    $flosc_post = wp_unslash($_POST);
    if (wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
        if (empty($flosc_selected_flow_id) || !flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
            wp_die(esc_html__('You do not have permission to manage AI feedback for this flow.', 'flosc'));
        }
        $flosc_new_feedback_item = [
            'id'                 => uniqid('corr_'),
            'timestamp'          => current_time('mysql'),
            'user_message'       => sanitize_textarea_field($flosc_post['feedback_user_message'] ?? ''),
            'bad_response'       => sanitize_textarea_field($flosc_post['feedback_bad_response'] ?? ''),
            'admin_note'         => sanitize_textarea_field($flosc_post['feedback_admin_note'] ?? ''),
            'preferred_response' => sanitize_textarea_field($flosc_post['feedback_preferred_response'] ?? ''),
            'admin_user_id'      => get_current_user_id(),
        ];

        if (!empty($flosc_new_feedback_item['user_message']) && !empty($flosc_new_feedback_item['admin_note'])) {
            $flosc_feedback_items[] = $flosc_new_feedback_item;
            $flosc_flow_settings['ai_feedback'] = $flosc_feedback_items;
            update_option($settings_key, $flosc_flow_settings);
            $flosc_feedback_count = count($flosc_feedback_items);
            echo '<div class="notice notice-success is-dismissible"><p>Feedback added. The AI will follow this guidance on next response.</p></div>';
        }
    }
}

// ── Praises ──
$flosc_praises = $flosc_flow_settings['ai_praises'] ?? [];
$flosc_praises_count = count($flosc_praises);

// Handle delete praise
if (isset($_POST['flosc_delete_praise'])) {
    $flosc_post = wp_unslash($_POST);
    if (wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
        if (empty($flosc_selected_flow_id) || !flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
            wp_die(esc_html__('You do not have permission to manage AI praise for this flow.', 'flosc'));
        }
        $flosc_delete_id = sanitize_text_field($flosc_post['flosc_delete_praise'] ?? '');
        $flosc_praises = array_values(array_filter($flosc_praises, function($p) use ($flosc_delete_id) {
            return ($p['id'] ?? '') !== $flosc_delete_id;
        }));
        $flosc_flow_settings['ai_praises'] = $flosc_praises;
        update_option($settings_key, $flosc_flow_settings);
        $flosc_praises_count = count($flosc_praises);
        echo '<div class="notice notice-success is-dismissible"><p>Praise deleted.</p></div>';
    }
}

// Handle add praise
if (isset($_POST['flosc_add_praise'])) {
    $flosc_post = wp_unslash($_POST);
    if (wp_verify_nonce(sanitize_text_field($flosc_post['_wpnonce'] ?? ''), 'flosc_save_settings')) {
        if (empty($flosc_selected_flow_id) || !flosc_flows()->can_access_flow_admin($flosc_selected_flow_id)) {
            wp_die(esc_html__('You do not have permission to manage AI praise for this flow.', 'flosc'));
        }
        $flosc_new_praise = [
            'id'            => uniqid('praise_'),
            'timestamp'     => current_time('mysql'),
            'user_message'  => sanitize_textarea_field($flosc_post['praise_user_message'] ?? ''),
            'good_response' => sanitize_textarea_field($flosc_post['praise_good_response'] ?? ''),
            'admin_note'    => sanitize_textarea_field($flosc_post['praise_admin_note'] ?? ''),
            'admin_user_id' => get_current_user_id(),
        ];

        if (!empty($flosc_new_praise['user_message']) && !empty($flosc_new_praise['admin_note'])) {
            $flosc_praises[] = $flosc_new_praise;
            $flosc_flow_settings['ai_praises'] = $flosc_praises;
            update_option($settings_key, $flosc_flow_settings);
            $flosc_praises_count = count($flosc_praises);
            echo '<div class="notice notice-success is-dismissible"><p>Praise added. The AI will reinforce this behavior.</p></div>';
        }
    }
}
?>

<div class="flosc-feedback-section">
    <h3 class="flosc-feedback-header">
        AI Feedback
        <span class="flosc-feedback-count"><?php echo esc_html( (string) $flosc_feedback_count ); ?></span>
    </h3>
    <p class="flosc-feedback-description">
        When you flag an AI response in the chat (using the ⚑ button), or add a feedback here manually, 
        the AI receives these as direct instructions in its system prompt. Use this to teach the AI 
        what NOT to do and how to respond instead.
    </p>

    <?php if ($flosc_feedback_count > 0): ?>
    <table class="flosc-feedback-table widefat striped">
        <thead>
            <tr>
                <th class="flosc-feedback-col-date">Date</th>
                <th class="flosc-feedback-col-input">User Said</th>
                <th class="flosc-feedback-col-issue">Issue</th>
                <th class="flosc-feedback-col-preferred">Preferred Response</th>
                <th class="flosc-feedback-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($flosc_feedback_items) as $flosc_feedback_item): ?>
            <tr>
                <td class="flosc-feedback-col-date">
                    <?php echo esc_html(gmdate('Y-m-d H:i', strtotime($flosc_feedback_item['timestamp'] ?? ''))); ?>
                </td>
                <td class="flosc-feedback-col-input">
                    <div class="flosc-feedback-text" title="<?php echo esc_attr($flosc_feedback_item['user_message'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($flosc_feedback_item['user_message'] ?? '', 0, 80, '...')); ?>
                    </div>
                    <?php if (!empty($flosc_feedback_item['bad_response'])): ?>
                    <div class="flosc-feedback-bad-preview" title="<?php echo esc_attr($flosc_feedback_item['bad_response']); ?>">
                        Bad: <?php echo esc_html(mb_strimwidth($flosc_feedback_item['bad_response'], 0, 60, '...')); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="flosc-feedback-col-issue">
                    <?php echo esc_html($flosc_feedback_item['admin_note'] ?? ''); ?>
                </td>
                <td class="flosc-feedback-col-preferred">
                    <?php if (!empty($flosc_feedback_item['preferred_response'])): ?>
                        <?php echo esc_html(mb_strimwidth($flosc_feedback_item['preferred_response'], 0, 100, '...')); ?>
                    <?php else: ?>
                        <em class="flosc-feedback-none">—</em>
                    <?php endif; ?>
                </td>
                <td class="flosc-feedback-col-actions">
                    <button type="submit" name="flosc_delete_feedback" value="<?php echo esc_attr($flosc_feedback_item['id']); ?>" 
                            class="button button-small flosc-feedback-delete"
                            data-confirm-message="Delete this feedback?">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="flosc-feedback-empty">
        <p>No feedback yet. Flag a bad AI response in the chat using the ⚑ button, or add one manually below.</p>
    </div>
    <?php endif; ?>

    <!-- Manual Add Feedback Form -->
    <div class="flosc-feedback-add">
        <h4>Add Feedback Manually</h4>
        <table class="form-table flosc-feedback-form-table">
            <tr>
                <th><label for="feedback_user_message">When user says <span class="required">*</span></label></th>
                <td>
                    <textarea id="feedback_user_message" name="feedback_user_message" rows="2" class="large-text"
                              placeholder="e.g., How much does it cost?"></textarea>
                    <p class="description">The type of user message this feedback applies to.</p>
                </td>
            </tr>
            <tr>
                <th><label for="feedback_bad_response">Bad response</label></th>
                <td>
                    <textarea id="feedback_bad_response" name="feedback_bad_response" rows="2" class="large-text"
                              placeholder="e.g., The AI said something incorrect or inappropriate here"></textarea>
                    <p class="description">What the AI said that was wrong (leave blank if adding a proactive rule).</p>
                </td>
            </tr>
            <tr>
                <th><label for="feedback_admin_note">What was wrong <span class="required">*</span></label></th>
                <td>
                    <textarea id="feedback_admin_note" name="feedback_admin_note" rows="2" class="large-text"
                              placeholder="e.g., Too pushy about purchasing, gave wrong price, went off-topic"></textarea>
                    <p class="description">Describe the problem so the AI knows what to avoid.</p>
                </td>
            </tr>
            <tr>
                <th><label for="feedback_preferred_response">Preferred response</label></th>
                <td>
                    <textarea id="feedback_preferred_response" name="feedback_preferred_response" rows="2" class="large-text"
                              placeholder="e.g., The course is $49. You can try a free lesson first to see if it's right for you."></textarea>
                    <p class="description">How the AI should respond instead (optional but recommended).</p>
                </td>
            </tr>
        </table>
        <button type="submit" name="flosc_add_feedback" value="1" class="button button-secondary">
            Add Feedback
        </button>
    </div>
</div>

<!-- ═══ AI Praise Section ═══ -->
<div class="flosc-feedback-section flosc-praise-section">
    <h3 class="flosc-feedback-header">
        AI Praise
        <span class="flosc-praise-count"><?php echo esc_html( (string) $flosc_praises_count ); ?></span>
    </h3>
    <p class="flosc-feedback-description">
        When you praise an AI response in the chat (using the ✓ button), or add praise here manually, 
        the AI receives these as examples of its best work. Use this to reinforce the tone, style, 
        and approach you want the AI to keep using.
    </p>

    <?php if ($flosc_praises_count > 0): ?>
    <table class="flosc-feedback-table flosc-praise-table widefat striped">
        <thead>
            <tr>
                <th class="flosc-feedback-col-date">Date</th>
                <th class="flosc-feedback-col-input">User Said</th>
                <th class="flosc-feedback-col-issue">Good Response</th>
                <th class="flosc-feedback-col-preferred">Why It Was Good</th>
                <th class="flosc-feedback-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($flosc_praises) as $flosc_praise): ?>
            <tr>
                <td class="flosc-feedback-col-date">
                    <?php echo esc_html(gmdate('Y-m-d H:i', strtotime($flosc_praise['timestamp'] ?? ''))); ?>
                </td>
                <td class="flosc-feedback-col-input">
                    <div class="flosc-feedback-text" title="<?php echo esc_attr($flosc_praise['user_message'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($flosc_praise['user_message'] ?? '', 0, 80, '...')); ?>
                    </div>
                </td>
                <td class="flosc-feedback-col-issue">
                    <div class="flosc-feedback-text flosc-feedback-good-preview" title="<?php echo esc_attr($flosc_praise['good_response'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($flosc_praise['good_response'] ?? '', 0, 100, '...')); ?>
                    </div>
                </td>
                <td class="flosc-feedback-col-preferred">
                    <?php echo esc_html($flosc_praise['admin_note'] ?? ''); ?>
                </td>
                <td class="flosc-feedback-col-actions">
                    <button type="submit" name="flosc_delete_praise" value="<?php echo esc_attr($flosc_praise['id']); ?>" 
                            class="button button-small flosc-feedback-praise-delete"
                            data-confirm-message="Delete this praise?">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="flosc-feedback-empty flosc-praise-empty">
        <p>No praises yet. Praise a good AI response in the chat using the ✓ button, or add one manually below.</p>
    </div>
    <?php endif; ?>

    <!-- Manual Add Praise Form -->
    <div class="flosc-feedback-add flosc-praise-add">
        <h4>Add Praise Manually</h4>
        <table class="form-table flosc-feedback-form-table">
            <tr>
                <th><label for="praise_user_message">When user said <span class="required">*</span></label></th>
                <td>
                    <textarea id="praise_user_message" name="praise_user_message" rows="2" class="large-text"
                              placeholder="e.g., Can you explain how the lessons work?"></textarea>
                    <p class="description">What the user asked or said.</p>
                </td>
            </tr>
            <tr>
                <th><label for="praise_good_response">Good response</label></th>
                <td>
                    <textarea id="praise_good_response" name="praise_good_response" rows="2" class="large-text"
                              placeholder="e.g., The AI's response that you liked"></textarea>
                    <p class="description">The AI response you want to reinforce.</p>
                </td>
            </tr>
            <tr>
                <th><label for="praise_admin_note">Why this was good <span class="required">*</span></label></th>
                <td>
                    <textarea id="praise_admin_note" name="praise_admin_note" rows="2" class="large-text"
                              placeholder="e.g., Perfect tone — warm but professional, gave accurate info without being pushy"></textarea>
                    <p class="description">What made this response great, so the AI replicates the approach.</p>
                </td>
            </tr>
        </table>
        <button type="submit" name="flosc_add_praise" value="1" class="button button-secondary">
            Add Praise
        </button>
    </div>
</div>
