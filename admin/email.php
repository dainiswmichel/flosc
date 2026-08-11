<?php
/**
 * FLOSC Email Configuration Tab
 * 
 * Email templates and automation triggers for user engagement:
 * - Quiz result emails (customizable templates)
 * - Congratulations/encouragement based on score
 * - Welcome emails for new users
 * - Re-engagement for inactive users
 * - Upgrade offers for free users
 * - Email trigger configuration (when to send)
 * 
 * PLACEHOLDERS AVAILABLE:
 * {name} - User's name
 * {score} - Quiz score percentage
 * {correct} - Number of correct answers
 * {incorrect} - Number of incorrect answers
 * {product_name} - Product name from Product tab
 * {app_link} - Link to app
 * {oto_section} - One-time offer content (if applicable)
 * 
 * BACKEND STATUS: Email templates functional. Guest/member automation sequences are live.
 * 
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('📧', 'Email');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_email_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-email';
$flosc_email_docs_inventory_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#inventory-communication-family';
$flosc_product_name = $flosc_flow_settings['name'] ?? 'FLOSC App';
$flosc_default_subject = "Your {$flosc_product_name} Quiz Results: {score}%";
$flosc_default_body = "Hi {name},

Thanks for taking the {$flosc_product_name} quiz!

YOUR SCORE: {score}%

✅ Correct: {correct}
❌ Needs Practice: {incorrect}
{oto_section}
Your personalized learning path is ready. Log in to get your FREE lesson and start improving today!

{app_link}

Best,
The {$flosc_product_name} Team";
?>

<div class="flosc-docs-link-wrap">
    <a href="<?php echo esc_url($flosc_email_docs_url); ?>" class="flosc-docs-link">Docs</a>
</div>

<h2>
    Email Templates & Automation
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h2>
<p>Customize emails sent to users and configure when they're triggered.</p>

<?php
/**
 * Renderer for a configurable per-flow email series (used by Newsletter + Member levels).
 * Welcome = single email. Follow-ups = admin-managed repeater rows ("after N days" + subject + body).
 * The number of follow-ups and their day offsets are per-flow parameters (stored in <prefix>_followups),
 * never hardcoded. Mirrors the Member Levels repeater pattern.
 */
$flosc_render_email_series = function ($prefix, $flow_settings, $welcome_default_subject, $welcome_default_body) {
    $w_subject = $flow_settings[$prefix . '_welcome_subject'] ?? $welcome_default_subject;
    $w_body    = $flow_settings[$prefix . '_welcome_body']    ?? $welcome_default_body;
    $followups = $flow_settings[$prefix . '_followups'] ?? [];
    if (!is_array($followups)) { $followups = []; }
?>
<table class="form-table flosc-form-table-reset">
    <tr>
        <th scope="row"><label>Welcome Subject</label></th>
        <td><input type="text" name="flow_<?php echo esc_attr($prefix); ?>_welcome_subject"
                   value="<?php echo esc_attr($w_subject); ?>" class="large-text"></td>
    </tr>
    <tr>
        <th scope="row"><label>Welcome Body</label></th>
        <td>
            <textarea name="flow_<?php echo esc_attr($prefix); ?>_welcome_body" rows="6" class="large-text code"><?php
                echo esc_textarea($w_body); ?></textarea>
            <p class="description">Sent immediately on entry. The access-link button is added automatically.</p>
        </td>
    </tr>
</table>
<p class="flosc-email-followup-title"><strong>Follow-up Emails</strong></p>
<p class="description flosc-email-followup-desc">Add as many as you like. Each sends once, the given number of days after entry. The number of days is configurable per row.</p>
<table class="widefat flosc-fu-table flosc-email-fu-table" data-prefix="<?php echo esc_attr($prefix); ?>">
    <thead><tr>
        <th class="flosc-email-col-days">After (days)</th>
        <th class="flosc-email-col-subject">Subject</th>
        <th>Body</th>
        <th class="flosc-email-col-remove">Remove</th>
    </tr></thead>
    <tbody class="flosc-fu-body">
    <?php foreach ($followups as $fu): ?>
        <tr class="flosc-fu-row">
            <td><input type="number" min="0" max="365" class="small-text" name="<?php echo esc_attr($prefix); ?>_fu_day[]" value="<?php echo esc_attr((int) ($fu['day'] ?? 0)); ?>"></td>
            <td><input type="text" name="<?php echo esc_attr($prefix); ?>_fu_subject[]" value="<?php echo esc_attr($fu['subject'] ?? ''); ?>" class="flosc-width-full"></td>
            <td><textarea name="<?php echo esc_attr($prefix); ?>_fu_body[]" rows="3" class="flosc-width-full"><?php echo esc_textarea($fu['body'] ?? ''); ?></textarea></td>
            <td class="flosc-text-center"><button type="button" class="button flosc-fu-remove" title="Remove">&times;</button></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="flosc-margin-top-8"><button type="button" class="button flosc-fu-add" data-prefix="<?php echo esc_attr($prefix); ?>">+ Add Follow-up</button></p>
<?php
};
?>

<!-- ============================================ -->
<!-- NEWSLETTER EMAIL SEQUENCE (optional / lead-gen) -->
<!-- ============================================ -->
<hr class="flosc-email-hr-top">
<h3>
    Newsletter Email Sequence <span class="flosc-email-status-badge flosc-email-status-badge--optional">OPTIONAL</span>
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">
    Optional sequence for users who opt into the newsletter (a profile checkbox; the chatbot can also offer sign-up).
    Placeholders: <code>{name}</code>, <code>{chat_url}</code>, <code>{profile_url}</code>, <code>{app_name}</code>, <code>{team_name}</code>.
</p>
<table class="form-table flosc-form-table-reset">
    <tr>
        <th scope="row"><label for="flow_newsletter_optin_text">Opt-in Prompt Text</label></th>
        <td><input type="text" id="flow_newsletter_optin_text" name="flow_newsletter_optin_text"
                   value="<?php echo esc_attr($flosc_flow_settings['newsletter_optin_text'] ?? 'Enter your email to subscribe to our newsletter:'); ?>"
                   class="large-text">
            <p class="description">Shown by the chatbot when offering the newsletter sign-up.</p></td>
    </tr>
</table>
<?php
$flosc_render_email_series(
    'newsletter',
    $flosc_flow_settings,
    'Thanks for subscribing to {app_name}',
    "Hi {name}!\n\nThanks for subscribing to the {app_name} newsletter. We'll keep you posted.\n\n— The {team_name}"
);
?>

<!-- ============================================ -->
<!-- PRIMARY QUIZ RESULTS EMAIL -->
<!-- ============================================ -->
<h3>
    Quiz Results Email <span class="flosc-email-status-badge flosc-email-status-badge--ready">READY</span>
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">Sent after user completes quiz. Customize subject and body with placeholders.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_email_subject">Email Subject</label></th>
        <td>
            <input type="text" id="flow_email_subject" name="flow_email_subject" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_subject'] ?? $flosc_default_subject); ?>" 
                   class="large-text">
            <p class="description">Available placeholders: <code>{score}</code>, <code>{product_name}</code>, <code>{name}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_email_body">Email Body</label></th>
        <td>
            <textarea id="flow_email_body" name="flow_email_body" rows="15" class="large-text code"><?php 
                echo esc_textarea($flosc_flow_settings['email_body'] ?? $flosc_default_body); 
            ?></textarea>
            <p class="description">
                Available placeholders: <code>{name}</code>, <code>{score}</code>, <code>{correct}</code>, 
                <code>{incorrect}</code>, <code>{oto_section}</code>, <code>{app_link}</code>, <code>{product_name}</code>
            </p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- EMAIL AUTOMATION TRIGGERS [PLANNED UI] -->
<!-- ============================================ -->
<hr class="flosc-email-hr">
<h3>
    Email Automation Triggers <span class="flosc-email-status-badge flosc-email-status-badge--backend">PLANNED UI</span>
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">Configuration controls reserved for a future automation module. These fields are not used by current runtime dispatch.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_email_on_quiz_complete">Send on Quiz Completion</label></th>
        <td>
            <label>
                <input type="checkbox" id="flow_email_on_quiz_complete" name="flow_email_on_quiz_complete" 
                      value="1" <?php checked($flosc_flow_settings['email_on_quiz_complete'] ?? false); ?>>
                Send quiz results email immediately after completion
            </label>
            <p class="description">Uses template above. Triggered when user completes quiz and enters email.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_congrats_threshold">Congratulations Email Threshold</label></th>
        <td>
            <input type="number" id="flow_email_congrats_threshold" name="flow_email_congrats_threshold" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_congrats_threshold'] ?? 80); ?>" 
                   min="0" max="100" class="small-text"> %
            <p class="description">Send congratulations variant when score is at or above this percentage.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_encouragement_threshold">Encouragement Email Threshold</label></th>
        <td>
            <input type="number" id="flow_email_encouragement_threshold" name="flow_email_encouragement_threshold" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_encouragement_threshold'] ?? 60); ?>" 
                   min="0" max="100" class="small-text"> %
            <p class="description">Send encouragement variant when score is below this percentage.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_reengagement_days">Re-engagement Email</label></th>
        <td>
            <input type="number" id="flow_email_reengagement_days" name="flow_email_reengagement_days" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_reengagement_days'] ?? 7); ?>" 
                   min="1" class="small-text"> days of inactivity
            <label class="flosc-email-inline-label-left">
                <input type="checkbox" name="flow_email_reengagement_enabled" value="1" 
                       <?php checked($flosc_flow_settings['email_reengagement_enabled'] ?? false); ?>>
                Enable
            </label>
            <p class="description">Send "we miss you" email after user hasn't logged in for specified days.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_weekly_summary">Weekly Progress Summary</label></th>
        <td>
            <label>
                <input type="checkbox" id="flow_email_weekly_summary" name="flow_email_weekly_summary" 
                       value="1" <?php checked($flosc_flow_settings['email_weekly_summary'] ?? false); ?>>
                Send weekly progress summary to active users
            </label>
            <p class="description">Summary includes: lessons completed this week, quiz attempts, upcoming content.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- GUEST ACCESS EMAIL SEQUENCE [LIVE] -->
<!-- ============================================ -->
<hr class="flosc-email-hr">
<h3>
    Guest Access Email Sequence <span class="flosc-email-status-badge flosc-email-status-badge--live">LIVE</span>
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">
    Emails sent automatically to guests who have not purchased.
    Placeholders: <code>{name}</code>, <code>{days_remaining}</code>, <code>{chat_url}</code>, <code>{profile_url}</code>, <code>{upgrade_url}</code>.
</p>

<?php
// Product-neutral defaults. Use {app_name} / {team_name} from Identity — never hardcode a brand for all flows.
$flosc_guest_emails = [
    'guest_welcome' => [
        'label'           => 'Welcome Email',
        'timing'          => 'Sent immediately on first login for this flow',
        'default_subject' => 'Welcome to {app_name} — your guest access is ready',
        'default_body'    => "Hi {name}!\n\nWelcome to {app_name}!\n\nYou've been given complimentary guest access. During this time you can take the quiz, review your results, and explore free content.\n\nContinue here: {chat_url}\n\n— The {team_name}",
    ],
    'guest_day10' => [
        'label'           => 'Day 10 Check-In',
        'timing'          => 'Sent in day window if not yet upgraded',
        'default_subject' => 'How is your {app_name} experience going?',
        'default_body'    => "Hi {name}!\n\nYou're into your complimentary {app_name} guest access — we hope you're finding it useful.\n\nYou still have {days_remaining} days remaining. Continue here: {chat_url}\n\nReady to unlock everything? Upgrade: {upgrade_url}\n\n— The {team_name}",
        'default_min_day' => 10,
        'default_max_day' => 12,
    ],
    'guest_day20' => [
        'label'           => 'Day 20 — Progress Check',
        'timing'          => 'Sent in day window if not yet upgraded',
        'default_subject' => 'You have {days_remaining} days of {app_name} access remaining',
        'default_body'    => "Hi {name}!\n\nYou're well into your complimentary {app_name} guest access.\n\nVisit your profile: {profile_url}\n\nYou have {days_remaining} days remaining. Upgrade for full access: {upgrade_url}\n\n— The {team_name}",
        'default_min_day' => 20,
        'default_max_day' => 22,
    ],
    'guest_day28' => [
        'label'           => 'Day 28 — Final Notice',
        'timing'          => 'Sent in day window if not yet upgraded',
        'default_subject' => '{days_remaining} days left for your guest access',
        'default_body'    => "Hi {name}!\n\nWe would love to welcome you as a full member of {app_name}.\n\nYour guest access expires in {days_remaining} days. If you do not upgrade, guest account information, recordings, and quiz scores may be removed from our servers.\n\nUpgrade to keep your data: {upgrade_url}\n\n— The {team_name}",
        'default_min_day' => 28,
        'default_max_day' => 30,
    ],
];
foreach ($flosc_guest_emails as $flosc_key => $flosc_cfg):
?>
<hr class="flosc-email-hr-divider">
<h4 class="flosc-email-section-subtitle"><?php echo esc_html($flosc_cfg['label']); ?></h4>
<p class="description flosc-email-subdesc"><?php echo esc_html($flosc_cfg['timing']); ?></p>
<table class="form-table flosc-form-table-reset">
    <tr>
        <th scope="row"><label>Subject</label></th>
        <td>
            <input type="text" name="flow_<?php echo esc_attr($flosc_key); ?>_subject"
                   value="<?php echo esc_attr($flosc_flow_settings[$flosc_key . '_subject'] ?? $flosc_cfg['default_subject']); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label>Body</label></th>
        <td>
            <textarea name="flow_<?php echo esc_attr($flosc_key); ?>_body" rows="8" class="large-text code"><?php
                echo esc_textarea($flosc_flow_settings[$flosc_key . '_body'] ?? $flosc_cfg['default_body']);
            ?></textarea>
        </td>
    </tr>
    <?php if ($flosc_key !== 'guest_welcome'): ?>
    <tr>
        <th scope="row"><label>Send Window (days)</label></th>
        <td>
            <?php
            $flosc_min_key = $flosc_key . '_min_day';
            $flosc_max_key = $flosc_key . '_max_day';
            $flosc_min_default = (int) ($flosc_cfg['default_min_day'] ?? 0);
            $flosc_max_default = (int) ($flosc_cfg['default_max_day'] ?? $flosc_min_default);
            ?>
            <label class="flosc-email-inline-label-right">From day
                <input type="number" min="0" max="365" class="small-text" name="flow_<?php echo esc_attr($flosc_min_key); ?>"
                       value="<?php echo esc_attr((int) ($flosc_flow_settings[$flosc_min_key] ?? $flosc_min_default)); ?>">
            </label>
            <label>to day
                <input type="number" min="0" max="365" class="small-text" name="flow_<?php echo esc_attr($flosc_max_key); ?>"
                       value="<?php echo esc_attr((int) ($flosc_flow_settings[$flosc_max_key] ?? $flosc_max_default)); ?>">
            </label>
            <p class="description flosc-margin-top-6">Per-flow control: set Day10 to Day9 by changing this window (for example 9 to 11).</p>
        </td>
    </tr>
    <?php endif; ?>
</table>
<?php endforeach; ?>

<!-- ============================================ -->
<!-- MEMBER EMAIL SEQUENCE (per level) -->
<!-- ============================================ -->
<hr class="flosc-email-hr">
<h3>
    Member Email Sequence
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">
    Per-level emails sent to members. Levels come from the <strong>Member Levels</strong> tab.
    Placeholders: <code>{name}</code>, <code>{chat_url}</code>, <code>{profile_url}</code>, <code>{upgrade_url}</code>, <code>{app_name}</code>, <code>{team_name}</code>.
</p>
<?php
$flosc_member_levels = $flosc_flow_settings['member_levels'] ?? [];
$flosc_has_levels = false;
foreach ((array) $flosc_member_levels as $flosc_lvl_key => $flosc_lvl) {
    $flosc_slug = sanitize_key($flosc_lvl['slug'] ?? $flosc_lvl_key);
    if ($flosc_slug === '') { continue; }
    $flosc_has_levels = true;
    $flosc_lname = trim((string) ($flosc_lvl['name'] ?? '')) ?: $flosc_slug;
    echo '<h4 class="flosc-email-level-title">Level: ' . esc_html($flosc_lname) . ' <code>' . esc_html($flosc_slug) . '</code></h4>';
    $flosc_render_email_series(
        'member_' . $flosc_slug,
        $flosc_flow_settings,
        'Welcome to {app_name} — your membership is active',
        "Hi {name}!\n\nYour {app_name} membership is now active. Your access link gives you one-click access any time.\n\nContinue here: {chat_url}\n\n— The {team_name}"
    );
}
if (!$flosc_has_levels):
?>
<p class="description"><em>No member levels defined yet. Add levels in the <strong>Member Levels</strong> tab, then their email series will appear here.</em></p>
<?php endif; ?>

<?php // §12: enqueue page JS via the registered admin handle instead of a raw <script> tag. ?>
<?php ob_start(); ?>
(function () {
    function addRow(prefix) {
        var table = document.querySelector('.flosc-fu-table[data-prefix="' + prefix + '"]');
        if (!table) { return; }
        var tbody = table.querySelector('.flosc-fu-body');
        var tr = document.createElement('tr');
        tr.className = 'flosc-fu-row';
        tr.innerHTML =
            '<td><input type="number" min="0" max="365" class="small-text" name="' + prefix + '_fu_day[]" value="0"></td>' +
            '<td><input type="text" name="' + prefix + '_fu_subject[]" value="" class="flosc-width-full"></td>' +
            '<td><textarea name="' + prefix + '_fu_body[]" rows="3" class="flosc-width-full"></textarea></td>' +
            '<td class="flosc-text-center"><button type="button" class="button flosc-fu-remove" title="Remove">&times;</button></td>';
        tbody.appendChild(tr);
    }
    document.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('flosc-fu-add')) {
            e.preventDefault();
            addRow(e.target.getAttribute('data-prefix'));
        } else if (e.target && e.target.classList.contains('flosc-fu-remove')) {
            e.preventDefault();
            var row = e.target.closest('.flosc-fu-row');
            if (row) { row.parentNode.removeChild(row); }
        }
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<!-- ============================================ -->
<!-- EMAIL PROVIDER SETTINGS [BACKEND NEEDED] -->
<!-- ============================================ -->
<hr class="flosc-email-hr">
<h3>
    Email Provider Settings <span class="flosc-email-status-badge flosc-email-status-badge--backend">BACKEND NEEDED</span>
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">Configure email delivery provider. Currently uses WordPress default (wp_mail).</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_email_provider">Email Provider</label></th>
        <td>
            <select id="flow_email_provider" name="flow_email_provider" disabled>
                <option value="wordpress" selected>WordPress Mail (wp_mail)</option>
                <option value="buddyboss">BuddyBoss Mailer</option>
                <option value="mailjet">Mailjet</option>
                <option value="sendgrid">SendGrid</option>
                <option value="smtp">Custom SMTP</option>
            </select>
            <p class="description">⚠️ Provider switching not yet implemented. Currently uses WordPress default.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_from_name">From Name</label></th>
        <td>
            <input type="text" id="flow_email_from_name" name="flow_email_from_name" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_from_name'] ?? ($flosc_flow_settings['name'] ?? 'FLOSC App')); ?>" 
                   class="regular-text">
            <p class="description">Name that appears in "From" field (e.g., "FLOSC Support Team")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_from_address">From Email Address</label></th>
        <td>
            <input type="email" id="flow_email_from_address" name="flow_email_from_address" 
                   value="<?php echo esc_attr($flosc_flow_settings['email_from_address'] ?? get_option('admin_email')); ?>" 
                   class="regular-text">
            <p class="description">Email address that appears in "From" field</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_support_email">Reply-To Support Email</label></th>
        <td>
            <input type="email" id="flow_support_email" name="flow_support_email"
                   value="<?php echo esc_attr($flosc_flow_settings['support_email'] ?? get_option('admin_email')); ?>"
                   class="regular-text">
            <p class="description">Replies from FLOSC emails will go to this address. Default: your site admin email.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- TEMPLATE EXAMPLES & INSPIRATION -->
<!-- ============================================ -->
<hr class="flosc-email-hr">
<h3>
    Email Template Examples (Copy-Paste Reference)
    <a href="<?php echo esc_url($flosc_email_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h3>
<p class="description">Best practice email templates for different scenarios. Copy and customize as needed.</p>

<div class="flosc-email-template-card flosc-email-template-card--congrats">
    <h4 class="flosc-email-template-title">🎉 Congratulations Email (High Score: 80%+)</h4>
    <p><strong>Subject:</strong> <code>Amazing! You scored {score}% on the {product_name} quiz 🎉</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

WOW! You scored {score}% on the {product_name} quick assessment! 🎯

That's an impressive score - you clearly have a strong foundation.

🏆 Your Results:
• {correct} correct answers
• {incorrect} areas for fine-tuning

Even with your strong performance, we've identified a few areas where you can reach true mastery.

👉 Get Your Free Advanced Lesson: {app_link}

This personalized lesson will:
✓ Build on your existing knowledge
✓ Address the {incorrect} concepts you missed
✓ Take you from good to exceptional

{oto_section}

Keep up the outstanding work!

Best regards,
The {product_name} Team

P.S. High performers like you often benefit most from targeted practice. Your free lesson is designed specifically for your level.</pre>
</div>

<div class="flosc-email-template-card flosc-email-template-card--encouragement">
    <h4 class="flosc-email-template-title">💪 Encouragement Email (Low Score: Below 60%)</h4>
    <p><strong>Subject:</strong> <code>Your {product_name} results + FREE personalized lesson inside</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

Thanks for taking the {product_name} quick assessment! You scored {score}%.

Here's the truth: Every expert was once a beginner. The difference? They didn't give up.

📊 Your Results:
• {correct} correct answers
• {incorrect} concepts to master

The good news? You've just identified EXACTLY what to focus on to improve fastest.

👉 Start Your Free Personalized Lesson: {app_link}

This lesson is specifically designed for your level and includes:
✓ Step-by-step explanations of the concepts you found challenging
✓ Practice exercises with instant feedback
✓ Real-world examples that make it click
✓ Progress tracking to see your improvement

{oto_section}

Most of our successful students started exactly where you are right now.

The only difference? They took the next step.

Best regards,
The {product_name} Team

P.S. This free lesson takes just 10 minutes and targets your exact weak points. What do you have to lose?</pre>
</div>

<div class="flosc-email-template-card flosc-email-template-card--welcome">
    <h4 class="flosc-email-template-title">🚀 Welcome Email (New User)</h4>
    <p><strong>Subject:</strong> <code>Welcome to {product_name} - Your learning journey starts now! 🚀</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

Welcome to {product_name}! We're thrilled to have you here.

Here's what happens next:

1️⃣ Take the 2-Minute Assessment
   Discover your current skill level and knowledge gaps
   
2️⃣ Get Your Personalized Learning Path
   We'll create a custom lesson plan based on your results
   
3️⃣ Start Learning Immediately
   Access your first FREE lesson right away - no credit card required

👉 Start Your Assessment Now: {app_link}

Questions? Just reply to this email - we're here to help!

Best regards,
The {product_name} Team

P.S. The assessment takes less time than making coffee, but the insights you'll gain are priceless.</pre>
</div>

<div class="flosc-email-template-card flosc-email-template-card--reengagement">
    <h4 class="flosc-email-template-title">⏰ Re-engagement Email (Inactive User)</h4>
    <p><strong>Subject:</strong> <code>We miss you, {name}! Your personalized lesson is still waiting</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

We noticed you haven't visited {product_name} in a while.

Your personalized lesson (based on your {score}% quiz score) is still waiting for you.

Life gets busy - we get it. But here's the thing:

The students who succeed aren't the ones with the most time.
They're the ones who show up consistently, even for just 10 minutes.

👉 Pick Up Where You Left Off: {app_link}

Your progress is saved. Your next lesson is queued up. All you need to do is click.

What are you waiting for?

Best regards,
The {product_name} Team

P.S. This personalized lesson expires in 7 days. Don't let your progress go to waste!</pre>
</div>

<div class="flosc-email-template-card flosc-email-template-card--upgrade">
    <h4 class="flosc-email-template-title">💎 Upgrade Offer Email (Free User)</h4>
    <p><strong>Subject:</strong> <code>Ready to unlock your full potential? Premium is 50% off</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

You've completed your free lesson and scored {score}% on the assessment.

That's real progress. But imagine what you could achieve with the full {product_name} experience.

{product_name} Premium includes:
✓ Complete access to all lessons and modules
✓ Advanced practice exercises with AI feedback
✓ Personalized coaching based on your learning style
✓ Progress tracking and achievement badges
✓ Certificate of completion
✓ Lifetime updates and new content

{oto_section}

Students who upgrade see 3x faster improvement on average.

Why? Because they get the complete system, not just a taste.

👉 Upgrade to Premium Now: {app_link}

Best regards,
The {product_name} Team

P.S. Have questions about whether Premium is right for you? Reply to this email and let's chat!</pre>
</div>

<div class="flosc-email-template-card flosc-email-template-card--weekly">
    <h4 class="flosc-email-template-title">📈 Weekly Progress Summary</h4>
    <p><strong>Subject:</strong> <code>Your {product_name} weekly progress - Keep it up!</code></p>
    <p><strong>Body:</strong></p>
    <pre class="flosc-email-template-pre">Hi {name},

Here's your weekly progress summary for {product_name}:

📊 This Week's Stats:
• Lessons completed: {lessons_completed}
• Quiz attempts: {quiz_attempts}
• Time spent learning: {time_spent}
• Current streak: {streak_days} days

🎯 Your Next Goals:
• Complete lesson: {next_lesson}
• Practice area: {weak_area}
• Challenge: {weekly_challenge}

👉 Continue Your Journey: {app_link}

You're making real progress. Keep showing up!

Best regards,
The {product_name} Team

P.S. Consistency beats intensity. Even 10 minutes a day adds up to massive results over time.</pre>
</div>
