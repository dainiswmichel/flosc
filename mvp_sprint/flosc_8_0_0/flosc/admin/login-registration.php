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

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
?>

<?php
$header_actions = [
    'open_login_modal'     => 'Open General Login Modal',
    'open_registration'    => 'Open Post-Quiz Auth Modal',
    'open_quiz'            => 'Open Quiz',
    'open_free_lesson'     => 'Open Free Lesson',
    'open_sandbox_purchase'=> 'Open Purchase Flow',
];
$login_action = $flow_settings['header_login_action'] ?? 'open_login_modal';
$signup_action = $flow_settings['header_signup_action'] ?? 'open_login_modal';
?>

<h2>Header Auth Buttons</h2>
<p class="description">The two buttons in the top-right corner of the chat. Configure the label and what each button does.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_header_login_text">Login Button Text</label></th>
        <td>
            <input type="text" id="flow_header_login_text" name="flow_header_login_text"
                   value="<?php echo esc_attr($flow_settings['header_login_text'] ?? 'Log in'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_login_action">Login Button Action</label></th>
        <td>
            <select id="flow_header_login_action" name="flow_header_login_action">
                <?php foreach ($header_actions as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($login_action, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_signup_text">Sign Up Button Text</label></th>
        <td>
            <input type="text" id="flow_header_signup_text" name="flow_header_signup_text"
                   value="<?php echo esc_attr($flow_settings['header_signup_text'] ?? 'Sign up'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_header_signup_action">Sign Up Button Action</label></th>
        <td>
            <select id="flow_header_signup_action" name="flow_header_signup_action">
                <?php foreach ($header_actions as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($signup_action, $key); ?>><?php echo esc_html($label); ?></option>
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
                   value="<?php echo esc_attr($flow_settings['auth_modal_title'] ?? 'Register Or Log In To See Your Quiz Results'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_subtitle">Subtitle</label></th>
        <td>
            <input type="text" id="flow_auth_modal_subtitle" name="flow_auth_modal_subtitle"
                   value="<?php echo esc_attr($flow_settings['auth_modal_subtitle'] ?? 'Account creation is necessary for LeSAEp to be able to process your quiz!'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_button_text">Button Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_button_text" name="flow_auth_modal_button_text"
                   value="<?php echo esc_attr($flow_settings['auth_modal_button_text'] ?? 'Continue with Email'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_dismiss_message">Dismiss Message</label></th>
        <td>
            <input type="text" id="flow_auth_modal_dismiss_message" name="flow_auth_modal_dismiss_message"
                   value="<?php echo esc_attr($flow_settings['auth_modal_dismiss_message'] ?? 'Your quiz results are temporarily saved. Sign up or log in to see your results before they expire.'); ?>"
                   class="large-text">
            <p class="description">Shown in chat when visitor closes without registering. Leave empty to show nothing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_sso_divider">SSO Divider Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_sso_divider" name="flow_auth_modal_sso_divider"
                   value="<?php echo esc_attr($flow_settings['auth_modal_sso_divider'] ?? 'or continue with'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_email_label">Email Label</label></th>
        <td>
            <input type="text" id="flow_auth_modal_email_label" name="flow_auth_modal_email_label"
                   value="<?php echo esc_attr($flow_settings['auth_modal_email_label'] ?? 'Email Address'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_email_placeholder">Email Placeholder</label></th>
        <td>
            <input type="text" id="flow_auth_modal_email_placeholder" name="flow_auth_modal_email_placeholder"
                   value="<?php echo esc_attr($flow_settings['auth_modal_email_placeholder'] ?? 'you@example.com'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_loading_text">Loading Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_loading_text" name="flow_auth_modal_loading_text"
                   value="<?php echo esc_attr($flow_settings['auth_modal_loading_text'] ?? 'Creating account...'); ?>"
                   class="large-text">
            <p class="description">Shown on the button while registration is processing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_terms_text">Terms Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_terms_text" name="flow_auth_modal_terms_text"
                   value="<?php echo esc_attr($flow_settings['auth_modal_terms_text'] ?? 'By continuing, you agree to our Terms of Service and Privacy Policy.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_success_message">Success Message</label></th>
        <td>
            <input type="text" id="flow_auth_modal_success_message" name="flow_auth_modal_success_message"
                   value="<?php echo esc_attr($flow_settings['auth_modal_success_message'] ?? 'Welcome! You\'re now logged in as {email}. Let\'s continue!'); ?>"
                   class="large-text">
            <p class="description">Shown in chat after successful registration. Use <code>{email}</code> for the user's email address.</p>
        </td>
    </tr>
</table>

<hr>

<h2>General Login Modal</h2>
<p class="description">Shown when a visitor clicks "Log In" or "Sign Up" from the header or menu.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_title">Title</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_title" name="flow_auth_login_modal_title"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_title'] ?? 'Log In or Create an Account'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_subtitle">Subtitle</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_subtitle" name="flow_auth_login_modal_subtitle"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_subtitle'] ?? 'Sign in to access your lessons and progress.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_button_text">Button Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_button_text" name="flow_auth_login_modal_button_text"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_button_text'] ?? 'Continue with Email'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_dismiss_message">Dismiss Message</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_dismiss_message" name="flow_auth_login_modal_dismiss_message"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_dismiss_message'] ?? ''); ?>"
                   class="large-text">
            <p class="description">Shown in chat when visitor closes without registering. Leave empty to show nothing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_sso_divider">SSO Divider Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_sso_divider" name="flow_auth_login_modal_sso_divider"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_sso_divider'] ?? 'or continue with'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_email_label">Email Label</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_email_label" name="flow_auth_login_modal_email_label"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_email_label'] ?? 'Email Address'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_email_placeholder">Email Placeholder</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_email_placeholder" name="flow_auth_login_modal_email_placeholder"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_email_placeholder'] ?? 'you@example.com'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_loading_text">Loading Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_loading_text" name="flow_auth_login_modal_loading_text"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_loading_text'] ?? 'Creating account...'); ?>"
                   class="large-text">
            <p class="description">Shown on the button while registration is processing.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_terms_text">Terms Text</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_terms_text" name="flow_auth_login_modal_terms_text"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_terms_text'] ?? 'By continuing, you agree to our Terms of Service and Privacy Policy.'); ?>"
                   class="large-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_login_modal_success_message">Success Message</label></th>
        <td>
            <input type="text" id="flow_auth_login_modal_success_message" name="flow_auth_login_modal_success_message"
                   value="<?php echo esc_attr($flow_settings['auth_login_modal_success_message'] ?? 'Welcome! You\'re now logged in as {email}. Let\'s continue!'); ?>"
                   class="large-text">
            <p class="description">Shown in chat after successful registration. Use <code>{email}</code> for the user's email address.</p>
        </td>
    </tr>
</table>
