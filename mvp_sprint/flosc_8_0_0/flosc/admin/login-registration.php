<?php
/**
 * FLOSC Login & Registration Tab
 * 
 * Configurable text for the auth modal that appears when visitors
 * need to register or log in (e.g., to see quiz results).
 * 
 * v8.0.4: Initial implementation — admin-configurable auth modal copy.
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('🔐', 'Login & Registration');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
?>

<h2>Auth Modal Text</h2>
<p class="description">These fields control the title, subtitle, button, and dismiss message shown when a visitor is prompted to register or log in.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_auth_modal_title">Modal Title</label></th>
        <td>
            <input type="text" id="flow_auth_modal_title" name="flow_auth_modal_title"
                   value="<?php echo esc_attr($flow_settings['auth_modal_title'] ?? 'Register Or Log In To See Your Quiz Results'); ?>"
                   class="large-text">
            <p class="description">The heading shown in the auth modal. Default: <code>Register Or Log In To See Your Quiz Results</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_subtitle">Modal Subtitle</label></th>
        <td>
            <input type="text" id="flow_auth_modal_subtitle" name="flow_auth_modal_subtitle"
                   value="<?php echo esc_attr($flow_settings['auth_modal_subtitle'] ?? 'Account creation is necessary for LeSAEp to be able to process your quiz!'); ?>"
                   class="large-text">
            <p class="description">The paragraph shown below the title. Default: <code>Account creation is necessary for LeSAEp to be able to process your quiz!</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_button_text">Continue Button Text</label></th>
        <td>
            <input type="text" id="flow_auth_modal_button_text" name="flow_auth_modal_button_text"
                   value="<?php echo esc_attr($flow_settings['auth_modal_button_text'] ?? 'Continue with Email'); ?>"
                   class="large-text">
            <p class="description">Text on the submit button. Default: <code>Continue with Email</code></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_auth_modal_dismiss_message">Dismiss Message</label></th>
        <td>
            <input type="text" id="flow_auth_modal_dismiss_message" name="flow_auth_modal_dismiss_message"
                   value="<?php echo esc_attr($flow_settings['auth_modal_dismiss_message'] ?? 'Your quiz results are temporarily saved. Sign up or log in to see your results before they expire.'); ?>"
                   class="large-text">
            <p class="description">Message shown in chat when a visitor closes the auth modal without registering. Default: <code>Your quiz results are temporarily saved. Sign up or log in to see your results before they expire.</code></p>
        </td>
    </tr>
</table>
