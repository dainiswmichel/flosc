<?php
/**
 * Flow Edit Tab: Email
 * 
 * Email templates and automation configuration
 */

if (!defined('ABSPATH')) exit;

$email = $flow['email'] ?? [];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">Email Settings</h2>
    
    <div class="flosc-form-field">
        <label for="email_from_name">From Name</label>
        <input type="text" id="email_from_name" name="email_from_name" 
               value="<?php echo esc_attr($email['from_name'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('email.from_name', 'e.g., LeSAEp Team')); ?>">
        <p class="description">Name that appears in the "From" field of emails.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="email_from_email">From Email Address</label>
        <input type="email" id="email_from_email" name="email_from_email" 
               value="<?php echo esc_attr($email['from_email'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('email.from_email', 'e.g., noreply@yourdomain.com')); ?>">
        <p class="description">Email address emails are sent from. Must be a valid address on your domain.</p>
    </div>
    
    <h2 class="flosc-section-header">Quiz Results Email</h2>
    <p class="description">Sent after user completes quiz. Leave empty to use global template.</p>
    
    <div class="flosc-form-field">
        <label for="email_quiz_subject">Email Subject</label>
        <input type="text" id="email_quiz_subject" name="email_quiz_subject" 
               value="<?php echo esc_attr($email['quiz_subject'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('email.quiz_subject')); ?>">
        <p class="description">Available placeholders: <code>{score}</code>, <code>{product_name}</code>, <code>{name}</code></p>
    </div>
    
    <div class="flosc-form-field">
        <label for="email_quiz_body">Email Body</label>
        <textarea id="email_quiz_body" name="email_quiz_body" rows="15" style="font-family: monospace;"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('email.quiz_body')); ?>"><?php echo esc_textarea($email['quiz_body'] ?? ''); ?></textarea>
        <p class="description">
            Available placeholders: <code>{name}</code>, <code>{score}</code>, <code>{correct}</code>, 
            <code>{incorrect}</code>, <code>{oto_section}</code>, <code>{app_link}</code>, <code>{product_name}</code>
        </p>
    </div>
    
    <h2 class="flosc-section-header">Automation</h2>
    
    <div class="flosc-form-field">
        <label>
            <input type="checkbox" name="email_send_on_quiz_complete" value="1" 
                   <?php checked($email['send_on_quiz_complete'] ?? '', '1'); ?>>
            Send quiz results email immediately after completion
        </label>
        <?php if (empty($email['send_on_quiz_complete'])): ?>
            <span class="description" style="margin-left: 10px;">
                (Global: <?php echo flosc_get_global_setting('email.send_on_quiz_complete', '1') ? 'Enabled' : 'Disabled'; ?>)
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="email_congrats_threshold">Congratulations Threshold (%)</label>
        <input type="number" id="email_congrats_threshold" name="email_congrats_threshold" 
               value="<?php echo esc_attr($email['congrats_threshold'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('email.congrats_threshold', '80')); ?>"
               min="0" max="100" style="width: 100px; max-width: 100px;">
        <p class="description">Send congratulations email when score is at or above this percentage.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
</form>
