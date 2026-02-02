<?php
/**
 * IVR Message Edit Form
 * Included in ivr-settings.php for inline editing
 */

if (!defined('ABSPATH')) exit;

// $editing_message, $variables, and $actions should be set by parent
$is_new = empty($editing_message['name']);
$msg = $editing_message;
?>

<form method="post" class="ivr-message-form">
    <?php wp_nonce_field('flosc_save_ivr_message', 'flosc_ivr_message_nonce'); ?>
    <input type="hidden" name="action" value="save_message">
    <input type="hidden" name="original_name" value="<?php echo esc_attr($msg['name'] ?? ''); ?>">
    
    <table class="form-table">
        <tr>
            <th scope="row"><label for="phase">Phase</label></th>
            <td>
                <select name="phase" id="phase" required>
                    <option value="freeline" <?php selected($msg['phase'] ?? 'freeline', 'freeline'); ?>>Freeline</option>
                    <option value="login" <?php selected($msg['phase'] ?? '', 'login'); ?>>Login</option>
                    <option value="offer" <?php selected($msg['phase'] ?? '', 'offer'); ?>>Offer</option>
                    <option value="sale" <?php selected($msg['phase'] ?? '', 'sale'); ?>>Sale</option>
                    <option value="content" <?php selected($msg['phase'] ?? '', 'content'); ?>>Content</option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="message_name">Message Name</label></th>
            <td>
                <input type="text" name="message_name" id="message_name" 
                       value="<?php echo esc_attr($msg['name'] ?? ''); ?>" 
                       class="regular-text" 
                       pattern="[a-zA-Z0-9_]+" 
                       title="Alphanumeric and underscore only"
                       required>
                <p class="description">Unique identifier (alphanumeric + underscore only)</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="title">Display Title</label></th>
            <td>
                <input type="text" name="title" id="title" 
                       value="<?php echo esc_attr($msg['title'] ?? $msg['name'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">Human-readable title for documentation</p>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="message_type">Message Type</label></th>
            <td>
                <select name="message_type" id="message_type" required onchange="toggleMessageTypeFields(this.value)">
                    <option value="auto" <?php selected($msg['type'] ?? 'auto', 'auto'); ?>>Auto (appears automatically)</option>
                    <option value="suggested_reply" <?php selected($msg['type'] ?? '', 'suggested_reply'); ?>>Suggested Reply (clickable button)</option>
                    <option value="offer" <?php selected($msg['type'] ?? '', 'offer'); ?>>Offer (special offer card)</option>
                </select>
            </td>
        </tr>
        
        <tr>
            <th scope="row"><label for="message_content">Message Content</label></th>
            <td>
                <textarea name="message_content" id="message_content" 
                          rows="4" class="large-text" required><?php echo esc_textarea($msg['content'] ?? ''); ?></textarea>
                <p class="description">
                    <button type="button" class="button button-small" onclick="insertVariable()">Insert Variable ▼</button>
                    <select id="variable-selector" style="margin-left: 10px;">
                        <option value="">-- Select Variable --</option>
                        <?php foreach ($variables as $var): ?>
                            <option value="<?php echo esc_attr($var); ?>"><?php echo esc_html($var); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
            </td>
        </tr>
        
        <!-- Suggested Reply Fields -->
        <tr class="suggested-reply-field" style="display: <?php echo ($msg['type'] ?? '') === 'suggested_reply' ? 'table-row' : 'none'; ?>;">
            <th scope="row"><label for="message_style">Message Style</label></th>
            <td>
                <select name="message_style" id="message_style">
                    <option value="pill" <?php selected($msg['style'] ?? 'pill', 'pill'); ?>>Pill</option>
                    <option value="button" <?php selected($msg['style'] ?? '', 'button'); ?>>Button</option>
                    <option value="chip" <?php selected($msg['style'] ?? '', 'chip'); ?>>Chip</option>
                    <option value="card" <?php selected($msg['style'] ?? '', 'card'); ?>>Card</option>
                </select>
            </td>
        </tr>
        
        <tr class="suggested-reply-field" style="display: <?php echo ($msg['type'] ?? '') === 'suggested_reply' ? 'table-row' : 'none'; ?>;">
            <th scope="row"><label for="icon">Icon</label></th>
            <td>
                <input type="text" name="icon" id="icon" 
                       value="<?php echo esc_attr($msg['icon'] ?? ''); ?>" 
                       class="small-text">
                <p class="description">Emoji or icon (e.g., 🚀)</p>
            </td>
        </tr>
        
        <tr class="suggested-reply-field" style="display: <?php echo ($msg['type'] ?? '') === 'suggested_reply' ? 'table-row' : 'none'; ?>;">
            <th scope="row"><label for="user_input">User Input Text</label></th>
            <td>
                <input type="text" name="user_input" id="user_input" 
                       value="<?php echo esc_attr($msg['user_input'] ?? ''); ?>" 
                       class="regular-text">
                <p class="description">What the button says (what user sees)</p>
            </td>
        </tr>
        
        <!-- Action Field -->
        <tr>
            <th scope="row"><label for="action_value">Action (Optional)</label></th>
            <td>
                <select name="action_value" id="action_value">
                    <option value="">-- No Action --</option>
                    <?php foreach ($actions as $act): ?>
                        <option value="<?php echo esc_attr($act); ?>" <?php selected($msg['action'] ?? '', $act); ?>>
                            <?php echo esc_html($act); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        
        <!-- Conditions Section -->
        <tr>
            <th scope="row"><label>Conditions</label></th>
            <td>
                <div style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                    <p style="margin-top: 0;">
                        <label>
                            <input type="radio" name="condition_mode" value="builder" 
                                   <?php checked(!isset($msg['conditions']) || $msg['conditions'] === 'always' || empty($msg['conditions'])); ?> 
                                   onchange="toggleConditionMode(this.value)"> 
                            Condition Builder
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            <input type="radio" name="condition_mode" value="expression" 
                                   <?php checked(isset($msg['conditions']) && $msg['conditions'] !== 'always' && !empty($msg['conditions'])); ?> 
                                   onchange="toggleConditionMode(this.value)"> 
                            Condition Expression
                        </label>
                    </p>
                    
                    <!-- Condition Builder UI -->
                    <div id="condition-builder" style="display: <?php echo (!isset($msg['conditions']) || $msg['conditions'] === 'always' || empty($msg['conditions'])) ? 'block' : 'none'; ?>;">
                        <h4>User State</h4>
                        <label><input type="checkbox" class="condition-check" data-condition="logged_in"> Logged In</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="!logged_in"> NOT Logged In</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="quiz_taken"> Quiz Taken</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="!quiz_taken"> Quiz NOT Taken</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="purchased"> Has Purchased</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="returning_user"> Returning User</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="onboarded"> Onboarded</label><br>
                        
                        <h4 style="margin-top: 15px;">Score</h4>
                        <select id="score-operator" onchange="buildConditionExpression()">
                            <option value="">No score requirement</option>
                            <option value=">">Greater than</option>
                            <option value="<">Less than</option>
                            <option value=">=">Greater than or equal</option>
                            <option value="<=">Less than or equal</option>
                            <option value="==">Equal to</option>
                        </select>
                        <input type="number" id="score-value" min="0" max="100" style="width: 80px;" onchange="buildConditionExpression()">
                        
                        <h4 style="margin-top: 15px;">Timing/Events</h4>
                        <label><input type="checkbox" class="condition-check" data-condition="first_show_session"> First show in session</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="first_message_after_quiz"> First message after quiz</label><br>
                        <label><input type="checkbox" class="condition-check" data-condition="first_message_after_purchase"> First message after purchase</label><br>
                        
                        <p style="margin-top: 15px;">
                            <label>Inactive for 
                                <input type="number" id="inactive-seconds" min="0" style="width: 80px;" onchange="buildConditionExpression()"> 
                                seconds
                            </label>
                        </p>
                        
                        <h4 style="margin-top: 15px;">Generated Expression:</h4>
                        <input type="text" id="built-expression" readonly style="width: 100%; background: #fff;">
                    </div>
                    
                    <!-- Condition Expression Input -->
                    <div id="condition-expression" style="display: <?php echo (isset($msg['conditions']) && $msg['conditions'] !== 'always' && !empty($msg['conditions'])) ? 'block' : 'none'; ?>;">
                        <input type="text" name="message_conditions" id="message_conditions_input" 
                               value="<?php echo esc_attr($msg['conditions'] ?? 'always'); ?>" 
                               class="large-text" 
                               placeholder="e.g., logged_in && score > 50">
                        
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; color: #0073aa;"><strong>Available Conditions Reference</strong></summary>
                            <div style="background: #fff; padding: 10px; margin-top: 5px; border: 1px solid #ddd;">
                                <p><strong>Boolean:</strong> logged_in, !logged_in, quiz_taken, purchased, lesson_viewed, returning_user, onboarded</p>
                                <p><strong>Scores:</strong> score > 50, score >= 70, score < 40</p>
                                <p><strong>Counters:</strong> message_count >= 3, lessons_completed >= 5</p>
                                <p><strong>Time:</strong> inactive_seconds > 120, session_minutes >= 2</p>
                                <p><strong>Events:</strong> first_show_session, first_message_after_quiz, first_message_after_purchase</p>
                                <p><strong>Logic:</strong> && (and), || (or), ! (not), () (groups)</p>
                                <p><strong>Example:</strong> logged_in && score > 50 || returning_user</p>
                            </div>
                        </details>
                    </div>
                    
                    <!-- Hidden field that gets submitted -->
                    <input type="hidden" name="message_conditions" id="final_conditions" value="<?php echo esc_attr($msg['conditions'] ?? 'always'); ?>">
                </div>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <button type="submit" class="button button-primary button-large">Save Message</button>
        <a href="?page=flosc-ivr" class="button">Cancel</a>
    </p>
</form>

<script>
function toggleMessageTypeFields(type) {
    const suggestedReplyFields = document.querySelectorAll('.suggested-reply-field');
    suggestedReplyFields.forEach(field => {
        field.style.display = (type === 'suggested_reply') ? 'table-row' : 'none';
    });
}

function toggleConditionMode(mode) {
    const builder = document.getElementById('condition-builder');
    const expression = document.getElementById('condition-expression');
    
    if (mode === 'builder') {
        builder.style.display = 'block';
        expression.style.display = 'none';
        buildConditionExpression();
    } else {
        builder.style.display = 'none';
        expression.style.display = 'block';
        // Copy expression input to final field
        const exprInput = document.getElementById('message_conditions_input');
        document.getElementById('final_conditions').value = exprInput.value;
    }
}

function buildConditionExpression() {
    const conditions = [];
    
    // Get checked conditions
    document.querySelectorAll('.condition-check:checked').forEach(checkbox => {
        conditions.push(checkbox.dataset.condition);
    });
    
    // Get score condition
    const scoreOp = document.getElementById('score-operator').value;
    const scoreVal = document.getElementById('score-value').value;
    if (scoreOp && scoreVal) {
        conditions.push(`score ${scoreOp} ${scoreVal}`);
    }
    
    // Get inactive seconds
    const inactiveSeconds = document.getElementById('inactive-seconds').value;
    if (inactiveSeconds && parseInt(inactiveSeconds) > 0) {
        conditions.push(`inactive_seconds > ${inactiveSeconds}`);
    }
    
    // Build expression
    const expression = conditions.length > 0 ? conditions.join(' && ') : 'always';
    
    // Update display and hidden field
    document.getElementById('built-expression').value = expression;
    document.getElementById('final_conditions').value = expression;
}

function insertVariable() {
    const selector = document.getElementById('variable-selector');
    const variable = selector.value;
    if (!variable) return;
    
    const textarea = document.getElementById('message_content');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    
    textarea.value = text.substring(0, start) + variable + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    
    selector.value = '';
}

// Update final conditions when expression input changes
document.addEventListener('DOMContentLoaded', function() {
    const exprInput = document.getElementById('message_conditions_input');
    if (exprInput) {
        exprInput.addEventListener('input', function() {
            document.getElementById('final_conditions').value = this.value;
        });
    }
    
    // Add listeners to condition checkboxes
    document.querySelectorAll('.condition-check').forEach(checkbox => {
        checkbox.addEventListener('change', buildConditionExpression);
    });
    
    // Initialize
    const currentMode = document.querySelector('input[name="condition_mode"]:checked');
    if (currentMode) {
        toggleConditionMode(currentMode.value);
    }
});
</script>
