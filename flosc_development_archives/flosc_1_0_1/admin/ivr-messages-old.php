<?php
/**
 * FLOSC IVR Messages Tab
 * 
 * Displays configured IVR messages organized by FLOSC phase.
 * Messages are stored in ivr.md and parsed by FLOSC_IVR_Parser.
 * 
 * Links to the dedicated IVR settings editor (admin.php?page=flosc-ivr)
 * for full message management including condition builder.
 */

if (!defined('ABSPATH')) exit;

$ivr_config = FLOSC_IVR_Parser::flosc_instance()->get_flosc_config();
?>

</form>
<h2>IVR Messages Configuration</h2>
<p><strong>IVR (Interactive Voice Response)</strong> is our framework for delivering contextual messages based on user behavior, FLOSC phase, quiz scores, and conditions.</p>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>Understanding FLOSC Phases:</strong>
    <ul style="margin: 10px 0 0 20px;">
        <li><strong>Freeline:</strong> Visitor (not logged in) - Goal: Encourage them to take the quiz</li>
        <li><strong>Login:</strong> Post-quiz visitors + Logged-in users - Goal: Deliver free lesson, present offer</li>
        <li><strong>Offer:</strong> Sales pitch - Goal: Encourage purchase</li>
        <li><strong>Sale:</strong> Post-purchase - Goal: Onboard to content</li>
        <li><strong>Content:</strong> Ongoing access - Goal: Support and encourage</li>
    </ul>
</div>


<!-- IVR Messages Display -->
<h3>IVR Messages <a href="<?php echo admin_url('admin.php?page=flosc-ivr'); ?>" class="button button-secondary" style="margin-left: 10px;">Edit Messages</a></h3>
<p class="description">All messages are configured in ivr.md markdown format. Click "Edit Messages" to modify.</p>

<div id="flosc-ivr-display">
    <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $phase):
        $phase_messages = $ivr_config['phases'][$phase] ?? [];
    ?>
    <div class="ivr-phase-section" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
        <h4 style="margin-top: 0; text-transform: capitalize;"><?php echo esc_html($phase); ?> Phase
            <span style="font-weight: normal; font-size: 14px;">(<?php echo count($phase_messages); ?> messages)</span>
        </h4>

        <?php if (empty($phase_messages)): ?>
            <p style="color: #999;">No messages configured for this phase.</p>
        <?php else: ?>
            <?php foreach ($phase_messages as $msg_id):
                $message = $ivr_config['messages'][$msg_id] ?? null;
                if (!$message) continue;
            ?>
            <div style="background: #fff; padding: 10px; margin-top: 10px; border: 1px solid #eee; border-left: 3px solid #0073aa;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong><?php echo esc_html($message['name']); ?></strong>
                    <span style="color: #667; font-size: 12px;"><?php echo esc_html($message['type'] ?? 'auto'); ?></span>
                </div>
                <?php if (!empty($message['condition'])): ?>
                    <div style="background: #f0f0f1; padding: 5px; border-radius: 3px; font-size: 12px; margin-bottom: 8px; font-family: monospace;">
                        Condition: <?php echo esc_html($message['condition']); ?>
                    </div>
                <?php endif; ?>
                <div style="color: #333;">
                    <?php echo esc_html(strlen($message['content']) > 150 ? substr($message['content'], 0, 150) . '...' : $message['content']); ?>
                </div>
                <?php if (!empty($message['action'])): ?>
                    <div style="margin-top: 5px; color: #0073aa; font-size: 12px;">
                        ➜ Action: <?php echo esc_html($message['action']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
