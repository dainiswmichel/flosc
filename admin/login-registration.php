<?php
/**
 * FLOSC Register & Login Tab
 * 
 * Configurable text for auth modals (popups). Each setup has 10 fields.
 * Two setups: Post-Quiz and General Login.
 * 
 * v8.0.4: Initial implementation — 4 fields for post-quiz modal.
 * v8.1.0: Expanded to 10 fields per setup, added General Login setup.
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('🔐', 'Register & Login');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_login_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-login';
?>

<div class="flosc-login-docs-wrap">
    <a href="<?php echo esc_url($flosc_login_docs_url); ?>" class="flosc-login-docs-link">Docs</a>
</div>

<?php
$flosc_guest_request_notice = sanitize_key((string) ($_GET['flosc_guest_request_notice'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin notice rendering only
$flosc_guest_request_messages = [
    'approved' => ['type' => 'success', 'text' => 'Guest account request approved.'],
    'approve_sent' => ['type' => 'success', 'text' => 'Guest account request approved and MagicLink sent.'],
    'approve_send_failed' => ['type' => 'error', 'text' => 'Approved, but MagicLink email send failed.'],
    'denied_blocked' => ['type' => 'success', 'text' => 'Guest account request denied and email blocked.'],
    'deleted' => ['type' => 'success', 'text' => 'Guest account request deleted.'],
    'invalid_email' => ['type' => 'error', 'text' => 'Invalid email for guest account request action.'],
];
if (isset($flosc_guest_request_messages[$flosc_guest_request_notice])) {
    $flosc_notice = $flosc_guest_request_messages[$flosc_guest_request_notice];
    $flosc_notice_class = ($flosc_notice['type'] === 'error') ? 'notice notice-error' : 'notice notice-success';
    echo '<div class="' . esc_attr($flosc_notice_class) . '"><p>' . esc_html($flosc_notice['text']) . '</p></div>';
}
?>

<?php
$flosc_header_actions = [
    'open_login_modal'     => 'Open General Auth Modal',
    'open_registration'    => 'Open Post-Quiz Auth Modal',
    'open_quiz'            => 'Open Quiz',
    'open_free_lesson'     => 'Open Free Lesson',
    'open_sandbox_purchase'=> 'Open Purchase Flow',
];
$flosc_login_action = $flosc_flow_settings['header_login_action'] ?? 'open_login_modal';
$flosc_signup_action = $flosc_flow_settings['header_signup_action'] ?? 'open_login_modal';
?>

<h2>Header Auth Buttons</h2>
<p class="description">The two buttons in the top-right corner of the chat. Configure the label and what each button does.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_header_login_text">Login Button Text</label></th>
        <td>
            <input type="text" id="flow_header_login_text" name="flow_header_login_text"
                   value="<?php echo esc_attr($flosc_flow_settings['header_login_text'] ?? 'Log in'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_login_action">Login Button Action</label></th>
        <td>
            <select id="flow_header_login_action" name="flow_header_login_action">
                <?php foreach ($flosc_header_actions as $flosc_key => $flosc_label): ?>
                    <option value="<?php echo esc_attr($flosc_key); ?>" <?php selected($flosc_login_action, $flosc_key); ?>><?php echo esc_html($flosc_label); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_signup_text">Sign Up Button Text</label></th>
        <td>
            <input type="text" id="flow_header_signup_text" name="flow_header_signup_text"
                   value="<?php echo esc_attr($flosc_flow_settings['header_signup_text'] ?? 'Sign up'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_signup_action">Sign Up Button Action</label></th>
        <td>
            <select id="flow_header_signup_action" name="flow_header_signup_action">
                <?php foreach ($flosc_header_actions as $flosc_key => $flosc_label): ?>
                    <option value="<?php echo esc_attr($flosc_key); ?>" <?php selected($flosc_signup_action, $flosc_key); ?>><?php echo esc_html($flosc_label); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
</table>

<hr>

<h2>Post-Quiz Auth Modal</h2>
<p class="description">Shown when a visitor completes a quiz and needs to register or log in to see results.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_auth_modal_title">Title</label></th>
        <td>
            <input type="text" id="flow_auth_modal_title" name="flow_auth_modal_title"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_title'] ?? 'Register Or Log In To See Your Quiz Results'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_subtitle">Subtitle</label></th>
        <td>
            <input type="text" id="flow_auth_modal_subtitle" name="flow_auth_modal_subtitle"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_subtitle'] ?? 'Account creation is necessary for LeSAEp to be able to process your quiz!'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_button_text">Button Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_button_text" name="flow_auth_modal_button_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_button_text'] ?? 'Continue with Email'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_dismiss_message">Dismiss Message</label></th>
        <td>
            <input type="text" id="flow_auth_modal_dismiss_message" name="flow_auth_modal_dismiss_message"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_dismiss_message'] ?? 'Your quiz results are temporarily saved. Sign up or log in to see your results before they expire.'); ?>"
                   class="large-text">
            <p class="description">Shown in chat when visitor closes without registering. Leave empty to show nothing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_sso_divider">SSO Divider Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_sso_divider" name="flow_auth_modal_sso_divider"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_sso_divider'] ?? 'or continue with'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_email_label">Email Label</label></th>
        <td>
            <input type="text" id="flow_auth_modal_email_label" name="flow_auth_modal_email_label"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_email_label'] ?? 'Email Address'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_email_placeholder">Email Placeholder</label></th>
        <td>
            <input type="text" id="flow_auth_modal_email_placeholder" name="flow_auth_modal_email_placeholder"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_email_placeholder'] ?? 'you@example.com'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_loading_text">Loading Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_loading_text" name="flow_auth_modal_loading_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_loading_text'] ?? 'Creating account...'); ?>"
                   class="large-text">
            <p class="description">Shown on the button while registration is processing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_terms_text">Terms Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_terms_text" name="flow_auth_modal_terms_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_terms_text'] ?? 'By continuing, you agree to our Terms of Service and Privacy Policy.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_success_message">Success Message</label></th>
        <td>
            <input type="text" id="flow_auth_modal_success_message" name="flow_auth_modal_success_message"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_success_message'] ?? 'Welcome! You\'re now logged in as {email}. Let\'s continue!'); ?>"
                   class="large-text">
            <p class="description">Shown in chat after successful registration. Use <code>{email}</code> for the user's email address.</p>
        </td>
    </tr>
</table>

<hr>

<h2>General Auth Modal</h2>
<p class="description">Shown when a visitor clicks "Log In" or "Sign Up" from the header or menu.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_title">Title</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_title" name="flow_auth_login_modal_title"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_title'] ?? 'Log In or Create an Account'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_subtitle">Subtitle</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_subtitle" name="flow_auth_login_modal_subtitle"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_subtitle'] ?? 'Sign in to access your lessons and progress.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_button_text">Button Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_button_text" name="flow_auth_login_modal_button_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_button_text'] ?? 'Continue with Email'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_dismiss_message">Dismiss Message</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_dismiss_message" name="flow_auth_login_modal_dismiss_message"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_dismiss_message'] ?? ''); ?>"
                   class="large-text">
            <p class="description">Shown in chat when visitor closes without registering. Leave empty to show nothing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_sso_divider">SSO Divider Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_sso_divider" name="flow_auth_login_modal_sso_divider"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_sso_divider'] ?? 'or continue with'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_email_label">Email Label</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_email_label" name="flow_auth_login_modal_email_label"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_email_label'] ?? 'Email Address'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_email_placeholder">Email Placeholder</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_email_placeholder" name="flow_auth_login_modal_email_placeholder"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_email_placeholder'] ?? 'you@example.com'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_loading_text">Loading Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_loading_text" name="flow_auth_login_modal_loading_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_loading_text'] ?? 'Creating account...'); ?>"
                   class="large-text">
            <p class="description">Shown on the button while registration is processing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_terms_text">Terms Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_terms_text" name="flow_auth_login_modal_terms_text"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_terms_text'] ?? 'By continuing, you agree to our Terms of Service and Privacy Policy.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_success_message">Success Message</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_success_message" name="flow_auth_login_modal_success_message"
                   value="<?php echo esc_attr($flosc_flow_settings['auth_login_modal_success_message'] ?? 'Welcome! You\'re now logged in as {email}. Let\'s continue!'); ?>"
                   class="large-text">
            <p class="description">Shown in chat after successful registration. Use <code>{email}</code> for the user's email address.</p>
        </td>
    </tr>
</table>

<h2>Complimentary LeSAEp Learners Guest Access Link Settings</h2>
<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_guest_link_name">Link Name</label></th>
        <td>
            <input type="text" id="flow_guest_link_name" name="flow_guest_link_name"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_name'] ?? 'Complimentary LeSAEp Learners Guest Access Link'); ?>"
                   class="large-text">
            <p class="description">The human-readable name for the guest access link. Used as <code>{link_name}</code> in all templates below.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_email_subject">Email Subject</label></th>
        <td>
            <input type="text" id="flow_guest_link_email_subject" name="flow_guest_link_email_subject"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_email_subject'] ?? 'Your {link_name}'); ?>"
                   class="large-text">
            <p class="description">Subject line for the guest link email. Use <code>{link_name}</code> for the link name.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_check_email_message">"Check Email" Chat Message</label></th>
        <td>
            <input type="text" id="flow_guest_link_check_email_message" name="flow_guest_link_check_email_message"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_check_email_message'] ?? "We've sent you a {link_name} to your email — click it to access this chat as a guest and view your quiz score, free lessons, and a special upgrade offer."); ?>"
                   class="large-text">
            <p class="description">Shown in chat after user submits email. Use <code>{link_name}</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_welcome_message">Welcome Chat Message</label></th>
        <td>
            <textarea id="flow_guest_link_welcome_message" name="flow_guest_link_welcome_message"
                      class="large-text" rows="4"><?php echo esc_textarea($flosc_flow_settings['guest_link_welcome_message'] ?? 'Hi, welcome back! Just to confirm: your email address is {email} and you can use your {link_name} {n} more times to access this chat. <a href="{upgrade_url}">Upgrade</a> for complete access to all lessons, quiz audios, and our LeSAEp Learners network...'); ?></textarea>
            <p class="description">Shown in chat on every guest link login. Placeholders: <code>{email}</code>, <code>{n}</code> (remaining uses), <code>{link_name}</code>, <code>{upgrade_url}</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_upgrade_url">Upgrade URL</label></th>
        <td>
            <input type="url" id="flow_guest_link_upgrade_url" name="flow_guest_link_upgrade_url"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_upgrade_url'] ?? ''); ?>"
                   class="large-text">
            <p class="description">URL for the Upgrade link inside the welcome message (<code>{upgrade_url}</code>).</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_expired_offer_url">Expired Link Offer URL</label></th>
        <td>
            <input type="url" id="flow_guest_link_expired_offer_url" name="flow_guest_link_expired_offer_url"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_expired_offer_url'] ?? ''); ?>"
                   class="large-text">
            <p class="description">Where to redirect users whose link has been used 10 times or expired after 30 days. Leave empty to show an in-chat expired message instead.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_profile_confirm_message">Profile Card Confirmation Message</label></th>
        <td>
            <input type="text" id="flow_guest_link_profile_confirm_message" name="flow_guest_link_profile_confirm_message"
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_profile_confirm_message'] ?? 'Perfect, {name}! You can always log in directly at {login_url}, update your profile, and upgrade to full access.'); ?>"
                   class="large-text">
            <p class="description">Shown in chat after guest saves their profile card. Placeholders: <code>{name}</code>, <code>{login_url}</code>.</p>
        </td>
    </tr>
</table>

<hr class="flosc-login-divider">
<h2>Send <?php echo esc_html($flosc_flow_settings['guest_link_name'] ?? 'Complimentary LeSAEp Learners Guest Access Link'); ?></h2>
<p>Send a working guest access link directly to any email address — same flow as if the user entered their own email in the chat.</p>
<div class="flosc-login-send-row">
    <div>
        <label for="flosc-send-guest-link-email" class="flosc-login-email-label">Email address</label>
        <input type="email" id="flosc-send-guest-link-email" placeholder="recipient@example.com"
               class="flosc-login-email-input">
    </div>
    <button type="button" id="flosc-send-guest-link-btn" class="button button-primary">
        Send <?php echo esc_html($flosc_flow_settings['guest_link_name'] ?? 'Complimentary LeSAEp Learners Guest Access Link'); ?>
    </button>
</div>
<p id="flosc-send-guest-link-result" class="flosc-login-send-result"></p>

<?php ob_start(); ?>
(function() {
    document.getElementById('flosc-send-guest-link-btn')?.addEventListener('click', function() {
        const emailEl  = document.getElementById('flosc-send-guest-link-email');
        const resultEl = document.getElementById('flosc-send-guest-link-result');
        const btn      = this;
        const email    = emailEl.value.trim();

        if (!email) { emailEl.focus(); return; }

        btn.disabled    = true;
        btn.textContent = 'Sending...';
        resultEl.style.display = 'none';

        const data = new FormData();
        data.append('action', 'flosc_send_guest_link');
        data.append('nonce',  '<?php echo esc_js(wp_create_nonce('flosc_send_guest_link')); ?>');
        data.append('email',  email);
        data.append('ivr',    '<?php echo esc_js($GLOBALS['flosc_current_ivr'] ?? ''); ?>');

        fetch(ajaxurl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                resultEl.style.display = 'block';
                if (res.success) {
                    resultEl.style.color = '#1a7f37';
                    resultEl.textContent = '✓ ' + res.data.message;
                    emailEl.value = '';
                } else {
                    resultEl.style.color = '#d63638';
                    resultEl.textContent = '✗ ' + (res.data?.message || 'Send failed.');
                }
            })
            .catch(() => {
                resultEl.style.display  = 'block';
                resultEl.style.color    = '#d63638';
                resultEl.textContent    = '✗ Request failed — check your connection.';
            })
            .finally(() => {
                btn.disabled    = false;
                btn.textContent = btn.textContent.replace('Sending...', 'Send <?php echo esc_js($flosc_flow_settings['guest_link_name'] ?? 'Complimentary LeSAEp Learners Guest Access Link'); ?>');
            });
    });
})();
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<hr class="flosc-login-divider">
<h2>Guest Account Requests</h2>
<p>Review requests captured in depleted chat sessions. Actions are performed here after reviewing context in Chat Logs.</p>
<?php
$flosc_request_queue = get_option('flosc_guest_account_request_queue', []);
$flosc_request_queue = is_array($flosc_request_queue) ? $flosc_request_queue : [];
$flosc_request_denylist = get_option('flosc_guest_account_request_denylist', []);
$flosc_request_denylist = is_array($flosc_request_denylist) ? $flosc_request_denylist : [];
$flosc_action_url = admin_url('admin-post.php');
$flosc_queue_rows = array_values($flosc_request_queue);

usort($flosc_queue_rows, static function ($a, $b) {
    $a_time = intval($a['last_requested_at'] ?? ($a['requested_at'] ?? 0));
    $b_time = intval($b['last_requested_at'] ?? ($b['requested_at'] ?? 0));
    return $b_time <=> $a_time;
});

if (empty($flosc_queue_rows)) {
    echo '<p class="flosc-login-empty-log">No guest account requests queued yet.</p>';
} else {
    echo '<table class="widefat striped flosc-login-activity-table">';
    echo '<thead><tr><th>Email</th><th>Flow</th><th>Requested</th><th>Status</th><th>Request Note</th><th>Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ($flosc_queue_rows as $flosc_request_row) {
        $flosc_email = sanitize_email((string) ($flosc_request_row['email'] ?? ''));
        if ($flosc_email === '') {
            continue;
        }
        $flosc_status = sanitize_key((string) ($flosc_request_row['status'] ?? 'pending'));
        if (!in_array($flosc_status, ['pending', 'approved', 'denied'], true)) {
            $flosc_status = 'pending';
        }
        $flosc_flow_id = sanitize_key((string) ($flosc_request_row['flow_id'] ?? ''));
        $flosc_requested_at = intval($flosc_request_row['last_requested_at'] ?? ($flosc_request_row['requested_at'] ?? 0));
        $flosc_requested_text = $flosc_requested_at > 0 ? wp_date('Y-m-d H:i', $flosc_requested_at) : '—';
        $flosc_note = sanitize_textarea_field((string) ($flosc_request_row['last_message_excerpt'] ?? ''));
        $flosc_note = $flosc_note !== '' ? wp_html_excerpt($flosc_note, 120, '...') : '—';
        $flosc_blocked = isset($flosc_request_denylist[md5(strtolower($flosc_email))]);
        $flosc_status_display = ucfirst($flosc_status) . ($flosc_blocked ? ' (blocked)' : '');

        echo '<tr>';
        echo '<td>' . esc_html($flosc_email) . '</td>';
        echo '<td>' . esc_html($flosc_flow_id !== '' ? $flosc_flow_id : '—') . '</td>';
        echo '<td>' . esc_html($flosc_requested_text) . '</td>';
        echo '<td>' . esc_html($flosc_status_display) . '</td>';
        echo '<td>' . esc_html($flosc_note) . '</td>';
        echo '<td>';

        $flosc_actions = [
            'flosc_guest_request_approve' => 'Approve',
            'flosc_guest_request_approve_send' => 'Approve + Send MagicLink',
            'flosc_guest_request_deny_block' => 'Deny + Block',
            'flosc_guest_request_delete' => 'Delete',
        ];

        foreach ($flosc_actions as $flosc_action => $flosc_label) {
            echo '<form method="post" action="' . esc_url($flosc_action_url) . '" class="flosc-admin-inline-action-form">';
            echo '<input type="hidden" name="action" value="' . esc_attr($flosc_action) . '">';
            echo '<input type="hidden" name="email" value="' . esc_attr($flosc_email) . '">';
            echo '<input type="hidden" name="flow_id" value="' . esc_attr($flosc_flow_id) . '">';
            echo '<input type="hidden" name="ivr" value="' . esc_attr($flosc_current_ivr) . '">';
            wp_nonce_field('flosc_guest_request_action', 'flosc_guest_request_nonce');
            echo '<button type="submit" class="button button-small">' . esc_html($flosc_label) . '</button>';
            echo '</form>';
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}
?>

<hr class="flosc-login-divider">
<h2>Guest Link Activity Log</h2>
<p>Emails that have requested guest access links, sorted by request count. Counts reset after 90 days of inactivity.</p>
<?php
$flosc_guest_log = get_option('flosc_guest_link_log', []);
if (empty($flosc_guest_log)) {
    echo '<p class="flosc-login-empty-log">No guest link requests recorded yet.</p>';
} else {
    // Sort by count descending
    uasort($flosc_guest_log, fn($a, $b) => $b['count'] <=> $a['count']);
    echo '<table class="widefat striped flosc-login-activity-table">';
    echo '<thead><tr><th>Email</th><th>Links Sent</th><th>First Request</th><th>Last Request</th></tr></thead>';
    echo '<tbody>';
    foreach ($flosc_guest_log as $flosc_entry) {
        $flosc_count      = (int) ($flosc_entry['count'] ?? 0);
        $flosc_first_sent = isset($flosc_entry['first_sent']) ? wp_date('Y-m-d H:i', $flosc_entry['first_sent']) : '—';
        $flosc_last_sent  = isset($flosc_entry['last_sent'])  ? wp_date('Y-m-d H:i', $flosc_entry['last_sent'])  : '—';
        $flosc_count_class = $flosc_count >= 6 ? 'flosc-login-count-cell flosc-login-count-cell--warn' : 'flosc-login-count-cell';
        // Link to WP user profile if user exists
        $flosc_wp_user = get_user_by('email', $flosc_entry['email']);
        $flosc_email_display = $flosc_wp_user
            ? '<a href="' . esc_url(get_edit_user_link($flosc_wp_user->ID)) . '">' . esc_html($flosc_entry['email']) . '</a>'
            : esc_html($flosc_entry['email']);
        echo '<tr>';
        echo '<td>' . wp_kses_post( $flosc_email_display ) . '</td>';
        echo '<td class="' . esc_attr($flosc_count_class) . '">' . esc_html($flosc_count) . ($flosc_count >= 6 ? ' ⚠️' : '') . '</td>';
        echo '<td>' . esc_html($flosc_first_sent) . '</td>';
        echo '<td>' . esc_html($flosc_last_sent) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
?>
