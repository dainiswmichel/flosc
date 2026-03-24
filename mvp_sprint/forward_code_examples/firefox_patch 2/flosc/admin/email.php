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
 * BACKEND STATUS: Email templates functional, automation triggers pseudocoded
 * 
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('📧', 'Email');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$product_name = $flow_settings['name'] ?? 'FLOSC App';
$default_subject = "Your {$product_name} Quiz Results: {score}%";
$default_body = "Hi {name},

Thanks for taking the {product_name} quiz!

YOUR SCORE: {score}%

✅ Correct: {correct}
❌ Needs Practice: {incorrect}
{oto_section}
Your personalized learning path is ready. Log in to get your FREE lesson and start improving today!

{app_link}

Best,
The {product_name} Team";
?>

<h2>Email Templates & Automation</h2>
<p>Customize emails sent to users and configure when they're triggered.</p>

<!-- ============================================ -->
<!-- PRIMARY QUIZ RESULTS EMAIL -->
<!-- ============================================ -->
<h3>Quiz Results Email <span style="background: #10b981; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">READY</span></h3>
<p class="description">Sent after user completes quiz. Customize subject and body with placeholders.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_email_subject">Email Subject</label></th>
        <td>
            <input type="text" id="flow_email_subject" name="flow_email_subject" 
                   value="<?php echo esc_attr($flow_settings['email_subject'] ?? $default_subject); ?>" 
                   class="large-text">
            <p class="description">Available placeholders: <code>{score}</code>, <code>{product_name}</code>, <code>{name}</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_email_body">Email Body</label></th>
        <td>
            <textarea id="flow_email_body" name="flow_email_body" rows="15" class="large-text code"><?php 
                echo esc_textarea($flow_settings['email_body'] ?? $default_body); 
            ?></textarea>
            <p class="description">
                Available placeholders: <code>{name}</code>, <code>{score}</code>, <code>{correct}</code>, 
                <code>{incorrect}</code>, <code>{oto_section}</code>, <code>{app_link}</code>, <code>{product_name}</code>
            </p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- EMAIL AUTOMATION TRIGGERS [BACKEND NEEDED] -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Email Automation Triggers <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
<p class="description">Configure when emails are automatically sent. Uses condition system similar to IVR messages.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_email_on_quiz_complete">Send on Quiz Completion</label></th>
        <td>
            <label>
                <input type="checkbox" id="flow_email_on_quiz_complete" name="flow_email_on_quiz_complete" 
                       value="1" <?php checked($flow_settings['email_on_quiz_complete'] ?? true); ?>>
                Send quiz results email immediately after completion
            </label>
            <p class="description">Uses template above. Triggered when user completes quiz and enters email.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_congrats_threshold">Congratulations Email Threshold</label></th>
        <td>
            <input type="number" id="flow_email_congrats_threshold" name="flow_email_congrats_threshold" 
                   value="<?php echo esc_attr($flow_settings['email_congrats_threshold'] ?? 80); ?>" 
                   min="0" max="100" class="small-text"> %
            <p class="description">Send congratulations variant when score is at or above this percentage.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_encouragement_threshold">Encouragement Email Threshold</label></th>
        <td>
            <input type="number" id="flow_email_encouragement_threshold" name="flow_email_encouragement_threshold" 
                   value="<?php echo esc_attr($flow_settings['email_encouragement_threshold'] ?? 60); ?>" 
                   min="0" max="100" class="small-text"> %
            <p class="description">Send encouragement variant when score is below this percentage.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_reengagement_days">Re-engagement Email</label></th>
        <td>
            <input type="number" id="flow_email_reengagement_days" name="flow_email_reengagement_days" 
                   value="<?php echo esc_attr($flow_settings['email_reengagement_days'] ?? 7); ?>" 
                   min="1" class="small-text"> days of inactivity
            <label style="margin-left: 15px;">
                <input type="checkbox" name="flow_email_reengagement_enabled" value="1" 
                       <?php checked($flow_settings['email_reengagement_enabled'] ?? false); ?>>
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
                       value="1" <?php checked($flow_settings['email_weekly_summary'] ?? false); ?>>
                Send weekly progress summary to active users
            </label>
            <p class="description">Summary includes: lessons completed this week, quiz attempts, upcoming content.</p>
        </td>
    </tr>
</table>

<!--
BACKEND IMPLEMENTATION NOTES:
=============================

Hook into these WordPress actions:
1. flosc_quiz_completed - Send quiz results email
2. flosc_user_registered - Send welcome email
3. flosc_lesson_completed - Send lesson completion congrats
4. Daily cron job - Check for re-engagement candidates

Pseudocode for quiz results:

add_action('flosc_quiz_completed', 'flosc_send_quiz_results_email', 10, 2);
function flosc_send_quiz_results_email($user_email, $quiz_data) {
    if (!($flow_settings['email_on_quiz_complete'] ?? true)) return;
    
    $score = $quiz_data['score'];
    $congrats_threshold = $flow_settings['email_congrats_threshold'] ?? 80;
    $encouragement_threshold = $flow_settings['email_encouragement_threshold'] ?? 60;
    
    // Choose template variant based on score
    if ($score >= $congrats_threshold) {
        $template = 'congrats'; // High score variant
    } elseif ($score < $encouragement_threshold) {
        $template = 'encouragement'; // Needs improvement variant
    } else {
        $template = 'standard'; // Mid-range variant
    }
    
    $subject = $flow_settings['email_subject'] ?? '';
    $body = $flow_settings['email_body'] ?? '';
    
    // Replace placeholders
    $placeholders = [
        '{name}' => $quiz_data['name'],
        '{score}' => $score,
        '{correct}' => $quiz_data['correct'],
        '{incorrect}' => $quiz_data['incorrect'],
        '{product_name}' => $flow_settings['product_name'] ?? '',
        '{app_link}' => home_url('/' . ($flow_settings['app_slug'] ?? '') . '/'),
        '{oto_section}' => flosc_get_oto_content($quiz_data),
    ];
    
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $body = str_replace(array_keys($placeholders), array_values($placeholders), $body);
    
    wp_mail($user_email, $subject, $body);
}

Pseudocode for re-engagement:

add_action('flosc_daily_cron', 'flosc_send_reengagement_emails');
function flosc_send_reengagement_emails() {
    if (!($flow_settings['email_reengagement_enabled'] ?? false)) return;
    
    $days = $flow_settings['email_reengagement_days'] ?? 7;
    $inactive_users = flosc_get_inactive_users($days);
    
    foreach ($inactive_users as $user) {
        // Send re-engagement email
        $subject = "We miss you! Your personalized lesson is waiting";
        $body = flosc_get_reengagement_email_template($user);
        wp_mail($user->email, $subject, $body);
        
        // Mark as sent to avoid spam
        update_user_meta($user->ID, 'flosc_last_reengagement_email', current_time('timestamp'));
    }
}
-->

<!-- ============================================ -->
<!-- GUEST ACCESS EMAIL SEQUENCE [LIVE] -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Guest Access Email Sequence <span style="background: #10b981; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">LIVE</span></h3>
<p class="description">
    Emails sent automatically to <strong>SSO guests</strong> (Facebook / Google logins who have not purchased).
    Placeholders: <code>{name}</code>, <code>{days_remaining}</code>, <code>{chat_url}</code>, <code>{profile_url}</code>, <code>{upgrade_url}</code>.
</p>

<?php
$guest_emails = [
    'guest_welcome' => [
        'label'           => 'Welcome Email',
        'timing'          => 'Sent immediately on first SSO login',
        'default_subject' => 'Welcome to LeSAEp — your 30-day guest access is ready',
        'default_body'    => "Hi {name}!\n\nWelcome to LeSAEp (Learn Excellent Standard American English Pronunciation)!\n\nYou've been given complimentary guest access for 30 days. During this time you can take the pronunciation quiz, hear your recordings, and explore lessons.\n\nContinue your LeSAEp experience: {chat_url}\n\nWe hope you enjoy every moment of it!\n\n— The LeSAEp Team",
    ],
    'guest_day10' => [
        'label'           => 'Day 10 Check-In',
        'timing'          => 'Sent on day 10 if not yet upgraded',
        'default_subject' => 'How is your LeSAEp experience going? 🎉',
        'default_body'    => "Hi {name}!\n\nYou're 10 days into your complimentary LeSAEp guest access — we hope you're enjoying it!\n\nYou still have {days_remaining} days remaining. Did you know you can take the quiz, get personalized feedback, and explore lessons right from the chat?\n\nContinue here: {chat_url}\n\nReady to unlock everything? Upgrade for full access: {upgrade_url}\n\n— The LeSAEp Team",
    ],
    'guest_day20' => [
        'label'           => 'Day 20 — Recordings & Scores',
        'timing'          => 'Sent on day 20 if not yet upgraded',
        'default_subject' => 'Your LeSAEp recordings & scores are waiting for you 🎧',
        'default_body'    => "Hi {name}!\n\nWe hope you're enjoying your LeSAEp experience! Did you know you can listen to your pronunciation recordings and review your quiz scores any time?\n\nVisit your profile here: {profile_url}\n\nYou have {days_remaining} days of guest access remaining. We'd love to welcome you as a full member — upgrade here: {upgrade_url}\n\n— The LeSAEp Team",
    ],
    'guest_day28' => [
        'label'           => 'Day 28 — Final Notice',
        'timing'          => 'Sent on day 28 if not yet upgraded',
        'default_subject' => '{days_remaining} days left — your LeSAEp guest access & recordings',
        'default_body'    => "Hi {name}!\n\nWe would love to welcome you as a full LeSAEp member!\n\nYour guest access expires in {days_remaining} days. We want to be transparent: if you do not upgrade, all guest access information — including your account, pronunciation recordings, and quiz scores — will be removed from our servers.\n\nWe wish you the very very best in your learning journey, whatever you decide.\n\nTo continue your progress and keep your data, upgrade here: {upgrade_url}\n\n— The LeSAEp Team",
    ],
];
foreach ($guest_emails as $key => $cfg):
?>
<hr style="margin: 24px 0 16px; border: none; border-top: 1px solid #e0e0e0;">
<h4 style="margin: 0 0 4px;"><?php echo esc_html($cfg['label']); ?></h4>
<p class="description" style="margin-bottom: 12px;"><?php echo esc_html($cfg['timing']); ?></p>
<table class="form-table" style="margin-bottom: 0;">
    <tr>
        <th scope="row"><label>Subject</label></th>
        <td>
            <input type="text" name="flow_<?php echo $key; ?>_subject"
                   value="<?php echo esc_attr($flow_settings[$key . '_subject'] ?? $cfg['default_subject']); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label>Body</label></th>
        <td>
            <textarea name="flow_<?php echo $key; ?>_body" rows="8" class="large-text code"><?php
                echo esc_textarea($flow_settings[$key . '_body'] ?? $cfg['default_body']);
            ?></textarea>
        </td>
    </tr>
</table>
<?php endforeach; ?>

<!-- ============================================ -->
<!-- EMAIL PROVIDER SETTINGS [BACKEND NEEDED] -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Email Provider Settings <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
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
                   value="<?php echo esc_attr($flow_settings['email_from_name'] ?? ($flow_settings['name'] ?? 'FLOSC App')); ?>" 
                   class="regular-text">
            <p class="description">Name that appears in "From" field (e.g., "LeSAEp Team")</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_email_from_address">From Email Address</label></th>
        <td>
            <input type="email" id="flow_email_from_address" name="flow_email_from_address" 
                   value="<?php echo esc_attr($flow_settings['email_from_address'] ?? get_option('admin_email')); ?>" 
                   class="regular-text">
            <p class="description">Email address that appears in "From" field</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- TEMPLATE EXAMPLES & INSPIRATION -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>Email Template Examples (Copy-Paste Reference)</h3>
<p class="description">Best practice email templates for different scenarios. Copy and customize as needed.</p>

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #4f46e5;">
    <h4 style="margin-top: 0;">🎉 Congratulations Email (High Score: 80%+)</h4>
    <p><strong>Subject:</strong> <code>Amazing! You scored {score}% on the {product_name} quiz 🎉</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <h4 style="margin-top: 0;">💪 Encouragement Email (Low Score: Below 60%)</h4>
    <p><strong>Subject:</strong> <code>Your {product_name} results + FREE personalized lesson inside</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #10b981;">
    <h4 style="margin-top: 0;">🚀 Welcome Email (New User)</h4>
    <p><strong>Subject:</strong> <code>Welcome to {product_name} - Your learning journey starts now! 🚀</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #ec4899;">
    <h4 style="margin-top: 0;">⏰ Re-engagement Email (Inactive User)</h4>
    <p><strong>Subject:</strong> <code>We miss you, {name}! Your personalized lesson is still waiting</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #8b5cf6;">
    <h4 style="margin-top: 0;">💎 Upgrade Offer Email (Free User)</h4>
    <p><strong>Subject:</strong> <code>Ready to unlock your full potential? Premium is 50% off</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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

<div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #0ea5e9;">
    <h4 style="margin-top: 0;">📈 Weekly Progress Summary</h4>
    <p><strong>Subject:</strong> <code>Your {product_name} weekly progress - Keep it up!</code></p>
    <p><strong>Body:</strong></p>
    <pre style="background: white; padding: 10px; overflow-x: auto; font-size: 13px;">Hi {name},

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
