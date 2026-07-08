<?php
/**
 * FLOSC Token Management Tab
 *
 * Per-flow controls for communication token economics.
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('🪙', 'Token Management');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_token_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-payments';

$flosc_global_tokens_per_message = max(1, intval(get_option('flosc_tokens_communication_tokens_per_message', 5000)));
$flosc_global_nom_num = max(1, intval(get_option('flosc_tokens_nominal_millicents_per_token_numerator', 1)));
$flosc_global_nom_den = max(1, intval(get_option('flosc_tokens_nominal_millicents_per_token_denominator', 1)));
$flosc_global_real_num = max(1, intval(get_option('flosc_tokens_real_millicents_per_token_numerator', 1)));
$flosc_global_real_den = max(1, intval(get_option('flosc_tokens_real_millicents_per_token_denominator', 2)));

$flosc_tokens_per_message = max(1, intval($flosc_flow_settings['tokens_communication_tokens_per_message'] ?? $flosc_global_tokens_per_message));
$flosc_nom_num = max(1, intval($flosc_flow_settings['tokens_nominal_millicents_per_token_numerator'] ?? $flosc_global_nom_num));
$flosc_nom_den = max(1, intval($flosc_flow_settings['tokens_nominal_millicents_per_token_denominator'] ?? $flosc_global_nom_den));
$flosc_real_num = max(1, intval($flosc_flow_settings['tokens_real_millicents_per_token_numerator'] ?? $flosc_global_real_num));
$flosc_real_den = max(1, intval($flosc_flow_settings['tokens_real_millicents_per_token_denominator'] ?? $flosc_global_real_den));
$flosc_nominal_rate = $flosc_nom_den > 0 ? ($flosc_nom_num / $flosc_nom_den) : 1.0;
$flosc_real_rate = $flosc_real_den > 0 ? ($flosc_real_num / $flosc_real_den) : 1.0;
$flosc_nominal_rate_display = rtrim(rtrim(number_format($flosc_nominal_rate, 3, '.', ''), '0'), '.');
$flosc_real_rate_display = rtrim(rtrim(number_format($flosc_real_rate, 3, '.', ''), '0'), '.');
if ($flosc_nominal_rate_display === '') {
    $flosc_nominal_rate_display = '1';
}
if ($flosc_real_rate_display === '') {
    $flosc_real_rate_display = '1';
}

$flosc_nominal_millicents_per_message = intval(round(($flosc_tokens_per_message * $flosc_nom_num) / $flosc_nom_den));
$flosc_real_millicents_per_message = intval(round(($flosc_tokens_per_message * $flosc_real_num) / $flosc_real_den));
$flosc_chat_token_enforcement = array_key_exists('chat_token_enforcement', $flosc_flow_settings)
    ? !empty($flosc_flow_settings['chat_token_enforcement'])
    : true;
$flosc_guest_token_grant = max(0, intval($flosc_flow_settings['guest_token_grant'] ?? $flosc_tokens_per_message));
$flosc_low_token_threshold = max(0, intval($flosc_flow_settings['visitor_low_token_threshold'] ?? 0));
$flosc_default_low_message = 'You\'re running low on chat tokens. Pretty soon, you\'ll be invited to register or log in to receive {token_grant} more tokens.';
$flosc_low_message = trim((string)($flosc_flow_settings['visitor_low_tokens_message'] ?? $flosc_default_low_message));
if ($flosc_low_message === '') {
    $flosc_low_message = $flosc_default_low_message;
}
$flosc_default_depleted_message = 'This session has run out of chat tokens. You can log in, and Dainis will give you {token_grant} tokens to use this chat. You can also contact Dainis personally or input your phone number or email address and preferred contact method and time for Dainis to get back to you.';
$flosc_depleted_message = trim((string)($flosc_flow_settings['visitor_tokens_depleted_message'] ?? $flosc_default_depleted_message));
if ($flosc_depleted_message === '') {
    $flosc_depleted_message = $flosc_default_depleted_message;
}
$flosc_session_end_redirect_url = trim((string)($flosc_flow_settings['visitor_session_end_redirect_url'] ?? ''));
$flosc_depleted_contact_mode = sanitize_key((string)($flosc_flow_settings['visitor_depleted_contact_mode'] ?? 'message'));
if (!in_array($flosc_depleted_contact_mode, ['message', 'in_chat_form'], true)) {
    $flosc_depleted_contact_mode = 'message';
}
?>

<div class="flosc-settings-docs-row">
    <a href="<?php echo esc_url($flosc_token_docs_url); ?>" class="flosc-settings-docs-link">Docs</a>
</div>

<h2>Token Management</h2>
<p>Configure the visitor wallet amount, the conversion factors, low token message behavior, and depleted token mode for this flow.</p>
<p class="description">Model: Actual AI API usage (provider-reported real cost) -> conversion factor -> FLOSC tokens spent and displayed.</p>

<div class="flosc-payments-status-banner">
    <strong>Flow:</strong> Provider reports actual AI API usage -> FLOSC converts that usage with the Real Billing Factor -> FLOSC token balance updates in the interface.
</div>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_chat_token_enforcement">Enforce Chat Token Charging</label></th>
        <td>
            <label>
                <input type="checkbox" id="flow_chat_token_enforcement" name="flow_chat_token_enforcement" value="1" <?php checked($flosc_chat_token_enforcement); ?>>
                Charge chat interactions against this flow's token wallets.
            </label>
            <p class="description">Per-flow switch. Turn off to allow chat without token debits for this flow.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_tokens_communication_tokens_per_message">Visitor Wallet Initial Amount</label></th>
        <td>
            <input type="number" id="flow_tokens_communication_tokens_per_message" name="flow_tokens_communication_tokens_per_message" value="<?php echo esc_attr($flosc_tokens_per_message); ?>" min="1" step="1" class="regular-text">
            <p class="description">Starting FLOSC token balance shown to visitors for this flow.</p>
        </td>
    </tr>

    <tr>
        <th scope="row">FLOSC Display Factor (Millicents per Token)</th>
        <td>
            <input type="number" name="flow_tokens_nominal_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_nominal_rate_display); ?>" min="0.001" step="0.001" class="small-text">
            <p class="description">Display/reference factor for FLOSC token valuation in the interface. Supports thousandths (for example 1.375).</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Real Billing Factor (Millicents per Token)</th>
        <td>
            <input type="number" name="flow_tokens_real_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_real_rate_display); ?>" min="0.001" step="0.001" class="small-text">
            <p class="description">Primary conversion factor: converts provider-reported actual AI API usage into FLOSC tokens spent. Supports thousandths (for example 1.375).</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Conversion Summary</th>
        <td>
            <p class="description">
                FLOSC display reference value (using FLOSC Display Factor): <strong><?php echo esc_html(number_format_i18n($flosc_nominal_millicents_per_message / 1000, 4)); ?> cents</strong>
                (<?php echo esc_html(number_format_i18n($flosc_nominal_millicents_per_message)); ?> millicents)<br>
                Real usage reference value (using Real Billing Factor): <strong><?php echo esc_html(number_format_i18n($flosc_real_millicents_per_message / 1000, 4)); ?> cents</strong>
                (<?php echo esc_html(number_format_i18n($flosc_real_millicents_per_message)); ?> millicents)<br>
                Runtime spend rule: provider-reported actual usage -> Real Billing Factor conversion -> FLOSC tokens debited from wallet balance.
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_guest_token_grant">Become A Guest Token Grant</label></th>
        <td>
            <input type="number" id="flow_guest_token_grant" name="flow_guest_token_grant" value="<?php echo esc_attr($flosc_guest_token_grant); ?>" min="0" step="1" class="regular-text">
            <p class="description">Per-flow token grant for guests on registration/login.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_visitor_low_token_threshold">Low Tokens Threshold</label></th>
        <td>
            <input type="number" id="flow_visitor_low_token_threshold" name="flow_visitor_low_token_threshold" value="<?php echo esc_attr($flosc_low_token_threshold); ?>" min="0" step="1" class="regular-text">
            <p class="description">Defines what balance counts as low tokens for this flow. Use 0 to disable low-token state.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_visitor_low_tokens_message">Low Tokens Message</label></th>
        <td>
            <input type="text" id="flow_visitor_low_tokens_message" name="flow_visitor_low_tokens_message" value="<?php echo esc_attr($flosc_low_message); ?>" class="large-text">
            <p class="description">Per-flow phrase shown when a visitor reaches low-token state. Placeholder: <code>{token_grant}</code>.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_visitor_tokens_depleted_message">Depleted Token Message</label></th>
        <td>
            <input type="text" id="flow_visitor_tokens_depleted_message" name="flow_visitor_tokens_depleted_message" value="<?php echo esc_attr($flosc_depleted_message); ?>" class="large-text">
            <p class="description">Per-flow phrase shown when visitor session tokens are depleted. Placeholder: <code>{token_grant}</code>.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_visitor_session_end_redirect_url">Redirect After Session End</label></th>
        <td>
            <input type="url" id="flow_visitor_session_end_redirect_url" name="flow_visitor_session_end_redirect_url" value="<?php echo esc_attr($flosc_session_end_redirect_url); ?>" class="large-text" placeholder="https://www.yoursite.tld/your-contact-form/">
            <p class="description">Optional URL. After visitor tokens are depleted, one contact message is captured, a thank-you appears, input is disabled, and the chat redirects to this URL.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_visitor_depleted_contact_mode">Depleted Token Mode</label></th>
        <td>
            <select id="flow_visitor_depleted_contact_mode" name="flow_visitor_depleted_contact_mode" class="regular-text">
                <option value="message" <?php selected($flosc_depleted_contact_mode, 'message'); ?>>One Message Capture</option>
                <option value="in_chat_form" <?php selected($flosc_depleted_contact_mode, 'in_chat_form'); ?>>In-Chat Contact Form</option>
            </select>
            <p class="description">floscAdmin choice for depleted token contact collection behavior.</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Global Fallback</th>
        <td>
            <p class="description">
                If this flow leaves token fields empty in future migrations, global defaults are used:
                <?php echo esc_html($flosc_global_tokens_per_message); ?> tokens,
                nominal <?php echo esc_html($flosc_global_nom_num); ?>/<?php echo esc_html($flosc_global_nom_den); ?>,
                real <?php echo esc_html($flosc_global_real_num); ?>/<?php echo esc_html($flosc_global_real_den); ?>.
            </p>
        </td>
    </tr>
</table>
