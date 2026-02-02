<?php
/**
 * IVR Configuration Admin Page
 * v07.08: Markdown-based IVR configuration editor
 */

if (!defined('ABSPATH')) exit;

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flosc_ivr_content'])) {
    if (!wp_verify_nonce($_POST['flosc_ivr_nonce'] ?? '', 'flosc_save_ivr')) {
        wp_die('Security check failed');
    }
    
    $content = wp_unslash($_POST['flosc_ivr_content']);
    
    // Save and re-parse
    $parser = FLOSC_IVR_Parser::instance();
    $config = $parser->save_config($content);
    
    $message_count = count($config['messages'] ?? []);
    $style_count = count($config['styles'] ?? []);
    
    echo '<div class="notice notice-success"><p>IVR configuration saved! Found ' . $message_count . ' messages and ' . $style_count . ' styles.</p></div>';
}

// Load current content
$ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
$ivr_content = file_exists($ivr_file) ? file_get_contents($ivr_file) : '';

// Get parsed stats
$parser = FLOSC_IVR_Parser::instance();
$config = $parser->get_config();
$message_count = count($config['messages'] ?? []);
$style_count = count($config['styles'] ?? []);
?>

<div class="wrap">
    <h1>FLOSC IVR Configuration</h1>
    <p class="description">Configure chat messages using markdown format. Messages are triggered based on user state and conditions.</p>

    <div class="flosc-ivr-stats" style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin: 20px 0;">
        <strong>Current Configuration:</strong>
        <?php echo $message_count; ?> messages, <?php echo $style_count; ?> styles
        
        <div style="margin-top: 10px;">
            <strong>Phases:</strong>
            <?php foreach ($config['phases'] as $phase => $messages): ?>
                <span style="background: #fff; padding: 2px 8px; border-radius: 3px; margin-right: 8px;">
                    <?php echo ucfirst($phase); ?>: <?php echo count($messages); ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flosc-ivr-help" style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Quick Reference</h3>
        
        <p><strong>Message Types:</strong> auto, suggested_reply, offer</p>
        
        <p><strong>Message Styles:</strong> pill, button, chip, card</p>
        
        <p><strong>Variables:</strong> {name}, {score}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}</p>
        
        <p><strong>Conditions:</strong></p>
        <ul style="margin: 5px 0 0 20px;">
            <li>Boolean: logged_in, !logged_in, quiz_taken, purchased, lesson_viewed, returning_user, onboarded</li>
            <li>Scores: score > 50, score >= 70, score < 40</li>
            <li>Counters: message_count >= 3, lessons_completed >= 5</li>
            <li>Time: inactive_seconds > 120, session_minutes >= 2</li>
            <li>Events: first_show_session, first_message_after_quiz, first_message_after_purchase</li>
            <li>Logic: && (and), || (or), ! (not), () (groups)</li>
        </ul>
        
        <p><strong>Actions:</strong> open_quiz, open_registration, open_free_lesson, checkout_oto_main, open_lesson_library, resume_last_lesson, open_support</p>
    </div>

    <form method="post">
        <?php wp_nonce_field('flosc_save_ivr', 'flosc_ivr_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <td colspan="2">
                    <textarea name="flosc_ivr_content" id="flosc_ivr_content" 
                              style="width: 100%; height: 600px; font-family: monospace; font-size: 13px; line-height: 1.5;"
                    ><?php echo esc_textarea($ivr_content); ?></textarea>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary button-large">Save IVR Configuration</button>
            <a href="<?php echo admin_url('admin.php?page=flosc-ivr&action=reset'); ?>" 
               class="button" 
               onclick="return confirm('Reset to default configuration? This cannot be undone.');"
               style="margin-left: 10px;">Reset to Default</a>
        </p>
    </form>

    <div class="flosc-ivr-example" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin-top: 30px;">
        <h3>Example Message Block</h3>
        <pre style="background: #fff; padding: 10px; overflow-x: auto;">
## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. Ready to get started?
MessageConditions: first_show_session && !logged_in

## Get Started Button
MessageName: get_started_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🚀
UserInput: Get started
MessageContent: Great! The best way to start is by taking our free quiz.
MessageConditions: !quiz_taken
        </pre>
    </div>
</div>

<style>
.flosc-ivr-stats span {
    display: inline-block;
    margin-top: 5px;
}
</style>
