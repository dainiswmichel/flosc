<?php
/**
 * v1.9.0: AI Corrections & Praise Editor
 * 
 * Admin can view, add, and delete corrections (flag bad responses) and
 * praises (reinforce good responses) that guide AI behavior.
 * Both are stored per-flow in flow settings and loaded into the system prompt.
 * 
 * Included from settings.php within the Chat Logs tab.
 */

if (!defined('ABSPATH')) exit;

// ── Corrections ──
$corrections = $flow_settings['ai_corrections'] ?? [];
$corrections_count = count($corrections);

// Handle delete correction
if (isset($_POST['flosc_delete_correction']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
    $delete_id = sanitize_text_field($_POST['flosc_delete_correction']);
    $corrections = array_values(array_filter($corrections, function($c) use ($delete_id) {
        return ($c['id'] ?? '') !== $delete_id;
    }));
    $flow_settings['ai_corrections'] = $corrections;
    update_option($settings_key, $flow_settings);
    $corrections_count = count($corrections);
    echo '<div class="notice notice-success is-dismissible"><p>Correction deleted.</p></div>';
}

// Handle add correction
if (isset($_POST['flosc_add_correction']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
    $new_correction = [
        'id'                 => uniqid('corr_'),
        'timestamp'          => current_time('mysql'),
        'user_message'       => sanitize_textarea_field($_POST['correction_user_message'] ?? ''),
        'bad_response'       => sanitize_textarea_field($_POST['correction_bad_response'] ?? ''),
        'admin_note'         => sanitize_textarea_field($_POST['correction_admin_note'] ?? ''),
        'preferred_response' => sanitize_textarea_field($_POST['correction_preferred_response'] ?? ''),
        'admin_user_id'      => get_current_user_id(),
    ];

    if (!empty($new_correction['user_message']) && !empty($new_correction['admin_note'])) {
        $corrections[] = $new_correction;
        $flow_settings['ai_corrections'] = $corrections;
        update_option($settings_key, $flow_settings);
        $corrections_count = count($corrections);
        echo '<div class="notice notice-success is-dismissible"><p>Correction added. The AI will follow this guidance on next response.</p></div>';
    }
}

// ── Praises ──
$praises = $flow_settings['ai_praises'] ?? [];
$praises_count = count($praises);

// Handle delete praise
if (isset($_POST['flosc_delete_praise']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
    $delete_id = sanitize_text_field($_POST['flosc_delete_praise']);
    $praises = array_values(array_filter($praises, function($p) use ($delete_id) {
        return ($p['id'] ?? '') !== $delete_id;
    }));
    $flow_settings['ai_praises'] = $praises;
    update_option($settings_key, $flow_settings);
    $praises_count = count($praises);
    echo '<div class="notice notice-success is-dismissible"><p>Praise deleted.</p></div>';
}

// Handle add praise
if (isset($_POST['flosc_add_praise']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_settings')) {
    $new_praise = [
        'id'            => uniqid('praise_'),
        'timestamp'     => current_time('mysql'),
        'user_message'  => sanitize_textarea_field($_POST['praise_user_message'] ?? ''),
        'good_response' => sanitize_textarea_field($_POST['praise_good_response'] ?? ''),
        'admin_note'    => sanitize_textarea_field($_POST['praise_admin_note'] ?? ''),
        'admin_user_id' => get_current_user_id(),
    ];

    if (!empty($new_praise['user_message']) && !empty($new_praise['admin_note'])) {
        $praises[] = $new_praise;
        $flow_settings['ai_praises'] = $praises;
        update_option($settings_key, $flow_settings);
        $praises_count = count($praises);
        echo '<div class="notice notice-success is-dismissible"><p>Praise added. The AI will reinforce this behavior.</p></div>';
    }
}
?>

<div class="flosc-corrections-section">
    <h3 class="flosc-corrections-header">
        AI Corrections
        <span class="flosc-corrections-count"><?php echo $corrections_count; ?></span>
    </h3>
    <p class="flosc-corrections-description">
        When you flag an AI response in the chat (using the ⚑ button), or add a correction here manually, 
        the AI receives these as direct instructions in its system prompt. Use this to teach the AI 
        what NOT to do and how to respond instead.
    </p>

    <?php if ($corrections_count > 0): ?>
    <table class="flosc-corrections-table widefat striped">
        <thead>
            <tr>
                <th class="flosc-corr-col-date">Date</th>
                <th class="flosc-corr-col-input">User Said</th>
                <th class="flosc-corr-col-issue">Issue</th>
                <th class="flosc-corr-col-preferred">Preferred Response</th>
                <th class="flosc-corr-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($corrections) as $correction): ?>
            <tr>
                <td class="flosc-corr-col-date">
                    <?php echo esc_html(date('Y-m-d H:i', strtotime($correction['timestamp'] ?? ''))); ?>
                </td>
                <td class="flosc-corr-col-input">
                    <div class="flosc-corr-text" title="<?php echo esc_attr($correction['user_message'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($correction['user_message'] ?? '', 0, 80, '...')); ?>
                    </div>
                    <?php if (!empty($correction['bad_response'])): ?>
                    <div class="flosc-corr-bad-preview" title="<?php echo esc_attr($correction['bad_response']); ?>">
                        Bad: <?php echo esc_html(mb_strimwidth($correction['bad_response'], 0, 60, '...')); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td class="flosc-corr-col-issue">
                    <?php echo esc_html($correction['admin_note'] ?? ''); ?>
                </td>
                <td class="flosc-corr-col-preferred">
                    <?php if (!empty($correction['preferred_response'])): ?>
                        <?php echo esc_html(mb_strimwidth($correction['preferred_response'], 0, 100, '...')); ?>
                    <?php else: ?>
                        <em class="flosc-corr-none">—</em>
                    <?php endif; ?>
                </td>
                <td class="flosc-corr-col-actions">
                    <button type="submit" name="flosc_delete_correction" value="<?php echo esc_attr($correction['id']); ?>" 
                            class="button button-small flosc-corr-delete"
                            onclick="return confirm('Delete this correction?');">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="flosc-corrections-empty">
        <p>No corrections yet. Flag a bad AI response in the chat using the ⚑ button, or add one manually below.</p>
    </div>
    <?php endif; ?>

    <!-- Manual Add Correction Form -->
    <div class="flosc-corrections-add">
        <h4>Add Correction Manually</h4>
        <table class="form-table flosc-corrections-form-table">
            <tr>
                <th><label for="correction_user_message">When user says <span class="required">*</span></label></th>
                <td>
                    <textarea id="correction_user_message" name="correction_user_message" rows="2" class="large-text"
                              placeholder="e.g., How much does it cost?"></textarea>
                    <p class="description">The type of user message this correction applies to.</p>
                </td>
            </tr>
            <tr>
                <th><label for="correction_bad_response">Bad response</label></th>
                <td>
                    <textarea id="correction_bad_response" name="correction_bad_response" rows="2" class="large-text"
                              placeholder="e.g., The AI said something incorrect or inappropriate here"></textarea>
                    <p class="description">What the AI said that was wrong (leave blank if adding a proactive rule).</p>
                </td>
            </tr>
            <tr>
                <th><label for="correction_admin_note">What was wrong <span class="required">*</span></label></th>
                <td>
                    <textarea id="correction_admin_note" name="correction_admin_note" rows="2" class="large-text"
                              placeholder="e.g., Too pushy about purchasing, gave wrong price, went off-topic"></textarea>
                    <p class="description">Describe the problem so the AI knows what to avoid.</p>
                </td>
            </tr>
            <tr>
                <th><label for="correction_preferred_response">Preferred response</label></th>
                <td>
                    <textarea id="correction_preferred_response" name="correction_preferred_response" rows="2" class="large-text"
                              placeholder="e.g., The course is $49. You can try a free lesson first to see if it's right for you."></textarea>
                    <p class="description">How the AI should respond instead (optional but recommended).</p>
                </td>
            </tr>
        </table>
        <button type="submit" name="flosc_add_correction" value="1" class="button button-secondary">
            Add Correction
        </button>
    </div>
</div>

<!-- ═══ AI Praise Section ═══ -->
<div class="flosc-corrections-section flosc-praise-section">
    <h3 class="flosc-corrections-header">
        AI Praise
        <span class="flosc-praise-count"><?php echo $praises_count; ?></span>
    </h3>
    <p class="flosc-corrections-description">
        When you praise an AI response in the chat (using the ✓ button), or add praise here manually, 
        the AI receives these as examples of its best work. Use this to reinforce the tone, style, 
        and approach you want the AI to keep using.
    </p>

    <?php if ($praises_count > 0): ?>
    <table class="flosc-corrections-table flosc-praise-table widefat striped">
        <thead>
            <tr>
                <th class="flosc-corr-col-date">Date</th>
                <th class="flosc-corr-col-input">User Said</th>
                <th class="flosc-corr-col-issue">Good Response</th>
                <th class="flosc-corr-col-preferred">Why It Was Good</th>
                <th class="flosc-corr-col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_reverse($praises) as $praise): ?>
            <tr>
                <td class="flosc-corr-col-date">
                    <?php echo esc_html(date('Y-m-d H:i', strtotime($praise['timestamp'] ?? ''))); ?>
                </td>
                <td class="flosc-corr-col-input">
                    <div class="flosc-corr-text" title="<?php echo esc_attr($praise['user_message'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($praise['user_message'] ?? '', 0, 80, '...')); ?>
                    </div>
                </td>
                <td class="flosc-corr-col-issue">
                    <div class="flosc-corr-text flosc-corr-good-preview" title="<?php echo esc_attr($praise['good_response'] ?? ''); ?>">
                        <?php echo esc_html(mb_strimwidth($praise['good_response'] ?? '', 0, 100, '...')); ?>
                    </div>
                </td>
                <td class="flosc-corr-col-preferred">
                    <?php echo esc_html($praise['admin_note'] ?? ''); ?>
                </td>
                <td class="flosc-corr-col-actions">
                    <button type="submit" name="flosc_delete_praise" value="<?php echo esc_attr($praise['id']); ?>" 
                            class="button button-small flosc-corr-praise-delete"
                            onclick="return confirm('Delete this praise?');">
                        Delete
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="flosc-corrections-empty flosc-praise-empty">
        <p>No praises yet. Praise a good AI response in the chat using the ✓ button, or add one manually below.</p>
    </div>
    <?php endif; ?>

    <!-- Manual Add Praise Form -->
    <div class="flosc-corrections-add flosc-praise-add">
        <h4>Add Praise Manually</h4>
        <table class="form-table flosc-corrections-form-table">
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
