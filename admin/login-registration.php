<?php
/**
 * FLOSC Register & Login Tab
 *
 * Three product concerns on one tab:
 * 1. Registration — post-quiz / create-account modal copy and header Sign Up
 * 2. Login — general auth modal copy and header Log In
 * 3. Guest Access Link (MagicLink) — convenience login for existing users only
 *
 * Defaults are product-neutral. Never hardcode a single product brand for all flows.
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
// Prefer one-shot transient; fall back to sanitized query flag from parent $flosc_get.
$flosc_guest_request_notice = '';
$flosc_guest_notice_t = get_transient( 'flosc_guest_request_notice_' . get_current_user_id() );
if ( is_string( $flosc_guest_notice_t ) && $flosc_guest_notice_t !== '' ) {
	delete_transient( 'flosc_guest_request_notice_' . get_current_user_id() );
	$flosc_guest_request_notice = sanitize_key( $flosc_guest_notice_t );
} elseif ( isset( $flosc_get['flosc_guest_request_notice'] ) ) {
	$flosc_guest_request_notice = sanitize_key( (string) $flosc_get['flosc_guest_request_notice'] );
}
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

<h2>User status messages</h2>
<p class="description">
    Shown for “What is my user status?” / <code>{user_status_response}</code> on <strong>this floscFlow only</strong>.
    Placeholders: <code>{first_name}</code>, <code>{product_name}</code>, <code>{member_level}</code>, <code>{name}</code>, <code>{email}</code>.
    Do not name other flows. Customize copy per product.
</p>
<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_user_status_visitor">Visitor</label></th>
        <td>
            <textarea id="flow_user_status_visitor" name="flow_user_status_visitor" class="large-text" rows="2"><?php
                echo esc_textarea($flosc_flow_settings['user_status_visitor'] ?? 'Hey, thanks for asking about your user status! You are a **Visitor**. You are chatting in "{product_name}".');
            ?></textarea>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_user_status_guest">Guest</label></th>
        <td>
            <textarea id="flow_user_status_guest" name="flow_user_status_guest" class="large-text" rows="2"><?php
                echo esc_textarea($flosc_flow_settings['user_status_guest'] ?? 'Hey, thanks for asking about your user status! You are a **Guest**. You like to be called **{first_name}**. You are chatting in "{product_name}".');
            ?></textarea>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_user_status_member">Member (no level slug)</label></th>
        <td>
            <textarea id="flow_user_status_member" name="flow_user_status_member" class="large-text" rows="2"><?php
                echo esc_textarea($flosc_flow_settings['user_status_member'] ?? 'Hey, thanks for asking about your user status! You are a **Member**. You like to be called **{first_name}**. You are chatting in "{product_name}".');
            ?></textarea>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_user_status_member_level">Member (with {member_level})</label></th>
        <td>
            <textarea id="flow_user_status_member_level" name="flow_user_status_member_level" class="large-text" rows="2"><?php
                echo esc_textarea($flosc_flow_settings['user_status_member_level'] ?? 'Hey, thanks for asking about your user status! You are a **Member**. You like to be called **{first_name}** and have access to **{member_level}** within "{product_name}".');
            ?></textarea>
            <p class="description">Used when this flow has <code>default_member_level</code> set and the user is a member of this flow.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_user_status_admin">Admin</label></th>
        <td>
            <textarea id="flow_user_status_admin" name="flow_user_status_admin" class="large-text" rows="2"><?php
                echo esc_textarea($flosc_flow_settings['user_status_admin'] ?? 'Hey, thanks for asking about your user status! You are the **FLOSC Admin**. You are chatting in "{product_name}".');
            ?></textarea>
        </td>
    </tr>
</table>

<hr>

<h2>Registration — Post-Quiz Auth Modal</h2>
<p class="description">Shown when a visitor completes a quiz and needs to register or log in to see results. Product-neutral defaults; customize per flow.</p>

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
                   value="<?php echo esc_attr($flosc_flow_settings['auth_modal_subtitle'] ?? 'Create an account or log in to process and view your quiz results.'); ?>"
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

<h2>Login — General Auth Modal</h2>
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

<hr>

<?php
// ─── Guest Access Link (MagicLink) ─────────────────────────────────────────
// Neutral product defaults. Never hardcode a product brand (e.g. the product) here —
// each flow sets its own labels. MagicLink logs in an EXISTING WP user only;
// it never creates accounts on click.
$flosc_guest_link_name_default = 'Guest Access Link';
$flosc_guest_link_name = $flosc_flow_settings['guest_link_name'] ?? $flosc_guest_link_name_default;
$flosc_magic_enabled = !empty($flosc_flow_settings['magic_access_links_enabled']);
$flosc_magic_max_uses = isset($flosc_flow_settings['guest_link_max_uses'])
    ? max(1, min(100, intval($flosc_flow_settings['guest_link_max_uses'])))
    : 10;
$flosc_magic_window_days = isset($flosc_flow_settings['guest_link_window_days'])
    ? max(1, min(365, intval($flosc_flow_settings['guest_link_window_days'])))
    : 30;
$flosc_package_magic_on = (defined('FLOSC_ENABLE_MAGIC_ACCESS_LINKS') && FLOSC_ENABLE_MAGIC_ACCESS_LINKS)
    || (!defined('FLOSC_ENABLE_MAGIC_ACCESS_LINKS') && (bool) apply_filters('flosc_enable_magic_access_links', false));
?>

<h2>Guest Access Link (MagicLink)</h2>
<p class="description">
    One-click login for an <strong>existing</strong> WordPress user. Never creates an account from the link.
    Directory package default is off (package switch + this flow checkbox both required).
    Letter content for guest sequences lives on the <strong>Email</strong> tab; journey timing parameters live on <strong>Engagement</strong>.
</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable for this flow</th>
        <td>
            <label>
                <input type="checkbox" name="flow_magic_access_links_enabled" value="1"
                    <?php checked($flosc_magic_enabled); ?>
                    <?php disabled(!$flosc_package_magic_on); ?>>
                Allow MagicLink mint and consume for this flow
            </label>
            <?php if (!$flosc_package_magic_on): ?>
                <p class="description">
                    Package-level MagicLink is off. Set <code>define('FLOSC_ENABLE_MAGIC_ACCESS_LINKS', true);</code>
                    in <code>wp-config.php</code> (or filter <code>flosc_enable_magic_access_links</code>) for private deploys, then enable this checkbox.
                </p>
            <?php else: ?>
                <p class="description">Package allows MagicLink. This checkbox is still off by default per flow.</p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_max_uses">Max uses (non-members)</label></th>
        <td>
            <input type="number" id="flow_guest_link_max_uses" name="flow_guest_link_max_uses"
                   value="<?php echo esc_attr((string) $flosc_magic_max_uses); ?>"
                   min="1" max="100" class="small-text">
            <p class="description">How many times a non-member may use the same link. Members are unlimited. Default 10.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_window_days">Active window (days)</label></th>
        <td>
            <input type="number" id="flow_guest_link_window_days" name="flow_guest_link_window_days"
                   value="<?php echo esc_attr((string) $flosc_magic_window_days); ?>"
                   min="1" max="365" class="small-text">
            <p class="description">Days after first click that the link stays valid. Default 30.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_name">Link Name</label></th>
        <td>
            <input type="text" id="flow_guest_link_name" name="flow_guest_link_name"
                   value="<?php echo esc_attr($flosc_guest_link_name); ?>"
                   class="large-text">
            <p class="description">Human-readable name for the access link. Used as <code>{link_name}</code> in templates below. Product-neutral default; set per flow.</p>
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
                   value="<?php echo esc_attr($flosc_flow_settings['guest_link_check_email_message'] ?? "We've sent you a {link_name} to your email — click it to access this chat as a guest and view your quiz score, free lessons, and upgrade options."); ?>"
                   class="large-text">
            <p class="description">Shown in chat after user submits email. Use <code>{link_name}</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_link_welcome_message">Welcome Chat Message</label></th>
        <td>
            <textarea id="flow_guest_link_welcome_message" name="flow_guest_link_welcome_message"
                      class="large-text" rows="4"><?php echo esc_textarea($flosc_flow_settings['guest_link_welcome_message'] ?? 'Hi, welcome back! Just to confirm: your email address is {email} and you can use your {link_name} {n} more times to access this chat. <a href="{upgrade_url}">Upgrade</a> for complete access.'); ?></textarea>
            <p class="description">Shown in chat on every guest link login. Placeholders: <code>{email}</code>, <code>{n}</code> (remaining uses), <code>{link_name}</code>, <code>{upgrade_url}</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_member_link_welcome_message">Member Welcome (unlimited link)</label></th>
        <td>
            <textarea id="flow_member_link_welcome_message" name="flow_member_link_welcome_message"
                      class="large-text" rows="3"><?php echo esc_textarea($flosc_flow_settings['member_link_welcome_message'] ?? 'Hi, welcome back {name}! Just to confirm: your email address is {email} and you can use your access link unlimited times to access this chat. Enjoy your session!'); ?></textarea>
            <p class="description">Shown when a member logs in via MagicLink. Placeholders: <code>{name}</code>, <code>{email}</code>.</p>
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
            <p class="description">Where to redirect users whose link has exhausted max uses or the active window. Leave empty for an in-chat expired message.</p>
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
    <tr>
        <th scope="row"><label for="flow_magic_link_admin_send_condition">Admin send condition</label></th>
        <td>
            <input type="text" id="flow_magic_link_admin_send_condition" name="flow_magic_link_admin_send_condition"
                   value="<?php echo esc_attr($flosc_flow_settings['magic_link_admin_send_condition'] ?? ''); ?>"
                   class="large-text"
                   placeholder="leave empty = always allow">
            <p class="description">
                Optional. Same condition language as Offers. Empty or <code>always</code> = no gate (ship default).
                When set, the email must already be an existing account and the expression must pass.
                Works for SSO and email registrants. Example:
                <code>is_guest &amp;&amp; login_count &gt;= 3 &amp;&amp; days_since_registration &gt;= 10 &amp;&amp; (has_sso || registration_method == "sso")</code>
            </p>
            <p class="description">
                Keys: <code>login_count</code>, <code>days_since_registration</code>, <code>registration_method</code>,
                <code>has_sso</code>, <code>is_guest</code>, <code>is_member</code>, <code>quiz_taken</code>, <code>purchased</code>, etc.
                Guest Account Request “Approve + Send” is moderated and does not use this gate.
            </p>
        </td>
    </tr>
</table>

<hr class="flosc-login-divider">
<h2>Send <?php echo esc_html($flosc_guest_link_name); ?></h2>
<p>Send a working guest access link to any email. Existing users (including SSO) get a mint only; missing users may be provisioned when the send condition is empty. Click never creates accounts.</p>
<div class="flosc-login-send-row">
    <div>
        <label for="flosc-send-guest-link-email" class="flosc-login-email-label">Email address</label>
        <input type="email" id="flosc-send-guest-link-email" placeholder="recipient@example.com"
               class="flosc-login-email-input">
    </div>
    <button type="button" id="flosc-send-guest-link-btn" class="button button-primary"
        <?php disabled(!$flosc_package_magic_on || !$flosc_magic_enabled); ?>>
        Send <?php echo esc_html($flosc_guest_link_name); ?>
    </button>
</div>
<p class="description flosc-login-desc-mt">
    <label>
        <input type="checkbox" id="flosc-send-guest-link-force" value="1">
        Force send (bypass admin send condition)
    </label>
</p>
<p id="flosc-send-guest-link-result" class="flosc-login-send-result"></p>

<?php ob_start(); ?>
(function() {
    document.getElementById('flosc-send-guest-link-btn')?.addEventListener('click', function() {
        const emailEl  = document.getElementById('flosc-send-guest-link-email');
        const forceEl  = document.getElementById('flosc-send-guest-link-force');
        const resultEl = document.getElementById('flosc-send-guest-link-result');
        const btn      = this;
        const email    = emailEl.value.trim();
        const sendLabel = <?php echo wp_json_encode('Send ' . $flosc_guest_link_name); ?>;

        if (!email) { emailEl.focus(); return; }

        btn.disabled    = true;
        btn.textContent = 'Sending...';
        resultEl.style.display = 'none';

        const data = new FormData();
        data.append('action', 'flosc_send_guest_link');
        data.append('nonce',  '<?php echo esc_js(wp_create_nonce('flosc_send_guest_link')); ?>');
        data.append('email',  email);
        data.append('ivr',    '<?php echo esc_js($GLOBALS['flosc_current_ivr'] ?? ''); ?>');
        if (forceEl && forceEl.checked) {
            data.append('force', '1');
        }

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
                btn.textContent = sendLabel;
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
