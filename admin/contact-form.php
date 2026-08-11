<?php
/**
 * FLOSC Contact Form Tab
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('✉', 'Contact Form');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_shortcode = '[flosc_contact_form_01]';
$flosc_contact_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-contact-form';
$flosc_contact_docs_inventory_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#inventory-communication-family';

$flosc_contact_defaults = [
    'contact_form_title' => 'Contact the Site Operator',
    'contact_form_intro' => 'Please fill out the form below to get in touch with the site operator. If you do not receive a response, please try another contact method.',
    'contact_form_submit_text' => 'Send Message',
    'contact_form_success_message' => 'We\'ve forwarded your message to the site operator, thank you!',
    'contact_form_forward_to_email' => get_option('admin_email'),
    'contact_form_email_subject' => 'FLOSC Contact Form Message',
    'contact_form_min_submit_seconds' => 4,
    'contact_form_max_submissions_per_hour' => 3,
    'contact_form_duplicate_window_minutes' => 30,
    'contact_form_button_bg_color' => '#1f6feb',
    'contact_form_button_text_color' => '#ffffff',
    'contact_form_card_background' => '#ffffff',
    'contact_form_accent_color' => '#2e8b57',
    'contact_form_message_font_family' => "'Courier New', Courier, monospace",
    'contact_form_message_font_size' => 20,
];

$flosc_contact_settings = array_merge($flosc_contact_defaults, array_intersect_key((array) $flosc_flow_settings, $flosc_contact_defaults));
?>

<div class="flosc-docs-link-wrap">
    <a href="<?php echo esc_url($flosc_contact_docs_url); ?>" class="flosc-docs-link">Docs</a>
</div>

<h2>
    Contact Form
    <a href="<?php echo esc_url($flosc_contact_docs_inventory_url); ?>" class="flosc-docs-link">Docs</a>
</h2>
<p>Configure a shortcode-driven contact form with required fields, a friendly success message, and lightweight anti-bot controls.</p>

<table class="form-table">
    <tr>
        <th scope="row">Shortcode</th>
        <td>
            <code><?php echo esc_html($flosc_shortcode); ?></code>
            <p class="description">Use this in any page or post. Alias supported: <code>[flosc-contact-form-01]</code>.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_title">Form Title</label></th>
        <td><input type="text" id="flow_contact_form_title" name="flow_contact_form_title" class="regular-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_title']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_intro">Form Intro</label></th>
        <td><input type="text" id="flow_contact_form_intro" name="flow_contact_form_intro" class="large-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_intro']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_submit_text">Submit Button Text</label></th>
        <td><input type="text" id="flow_contact_form_submit_text" name="flow_contact_form_submit_text" class="regular-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_submit_text']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_success_message">Success Message</label></th>
        <td><input type="text" id="flow_contact_form_success_message" name="flow_contact_form_success_message" class="large-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_success_message']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_forward_to_email">Forward To Email</label></th>
        <td><input type="email" id="flow_contact_form_forward_to_email" name="flow_contact_form_forward_to_email" class="regular-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_forward_to_email']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_email_subject">Email Subject</label></th>
        <td><input type="text" id="flow_contact_form_email_subject" name="flow_contact_form_email_subject" class="regular-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_email_subject']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_min_submit_seconds">Minimum Submit Delay (Seconds)</label></th>
        <td><input type="number" id="flow_contact_form_min_submit_seconds" name="flow_contact_form_min_submit_seconds" class="small-text" min="2" step="1" value="<?php echo esc_attr($flosc_contact_settings['contact_form_min_submit_seconds']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_max_submissions_per_hour">Max Submissions Per Hour (Per IP)</label></th>
        <td><input type="number" id="flow_contact_form_max_submissions_per_hour" name="flow_contact_form_max_submissions_per_hour" class="small-text" min="1" step="1" value="<?php echo esc_attr($flosc_contact_settings['contact_form_max_submissions_per_hour']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_duplicate_window_minutes">Duplicate Window (Minutes)</label></th>
        <td><input type="number" id="flow_contact_form_duplicate_window_minutes" name="flow_contact_form_duplicate_window_minutes" class="small-text" min="1" step="1" value="<?php echo esc_attr($flosc_contact_settings['contact_form_duplicate_window_minutes']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_button_bg_color">Button Background</label></th>
        <td><input type="color" id="flow_contact_form_button_bg_color" name="flow_contact_form_button_bg_color" value="<?php echo esc_attr($flosc_contact_settings['contact_form_button_bg_color']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_button_text_color">Button Text Color</label></th>
        <td><input type="color" id="flow_contact_form_button_text_color" name="flow_contact_form_button_text_color" value="<?php echo esc_attr($flosc_contact_settings['contact_form_button_text_color']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_card_background">Form Card Background</label></th>
        <td><input type="color" id="flow_contact_form_card_background" name="flow_contact_form_card_background" value="<?php echo esc_attr($flosc_contact_settings['contact_form_card_background']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_accent_color">Accent Color</label></th>
        <td><input type="color" id="flow_contact_form_accent_color" name="flow_contact_form_accent_color" value="<?php echo esc_attr($flosc_contact_settings['contact_form_accent_color']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_message_font_family">Message Font Family</label></th>
        <td><input type="text" id="flow_contact_form_message_font_family" name="flow_contact_form_message_font_family" class="large-text" value="<?php echo esc_attr($flosc_contact_settings['contact_form_message_font_family']); ?>"></td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_contact_form_message_font_size">Message Font Size (px)</label></th>
        <td><input type="number" id="flow_contact_form_message_font_size" name="flow_contact_form_message_font_size" class="small-text" min="16" step="1" value="<?php echo esc_attr($flosc_contact_settings['contact_form_message_font_size']); ?>"></td>
    </tr>
</table>
