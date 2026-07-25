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
$flosc_member_token_grant = max(0, intval($flosc_flow_settings['member_token_grant'] ?? $flosc_guest_token_grant));
// Product token parameters (flow defaults). Legacy subscription_* keys still read.
$flosc_product_token_grant_recurring = max(0, intval(
    $flosc_flow_settings['product_token_grant_recurring']
    ?? $flosc_flow_settings['subscription_monthly_token_grant']
    ?? 10000
));
$flosc_product_token_cap = max(0, intval(
    $flosc_flow_settings['product_token_cap']
    ?? $flosc_flow_settings['subscription_token_cap']
    ?? 35000
));
$flosc_product_token_grant_recurring_yearly = max(0, intval(
    $flosc_flow_settings['product_token_grant_recurring_yearly']
    ?? $flosc_flow_settings['subscription_yearly_token_grant']
    ?? $flosc_product_token_cap
));
$flosc_product_token_grant_onetime = max(0, intval(
    $flosc_flow_settings['product_token_grant_onetime']
    ?? $flosc_product_token_grant_recurring
));
$flosc_low_token_threshold = max(0, intval($flosc_flow_settings['visitor_low_token_threshold'] ?? 0));
$flosc_default_low_message = 'You\'re running low on chat tokens. Pretty soon, you\'ll be invited to register or log in to receive {token_grant} more tokens.';
$flosc_low_message = trim((string)($flosc_flow_settings['visitor_low_tokens_message'] ?? $flosc_default_low_message));
if ($flosc_low_message === '') {
    $flosc_low_message = $flosc_default_low_message;
}
$flosc_default_depleted_message = 'Dear guest, your chat tokens are used up for now. Please get in touch with Dainis personally, communicate with him in one of your shared groups, or make sure your purchases have been registered to your account so you have more chat tokens. I\'ll be shutting down this chat for now. Thanks for stopping by! Sincerely, Br3nda, Dainis\' virtual AI assistant.';
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
<p>Configure wallet size, how real API cost converts to floscTokens, low-token warnings, and depleted-token behavior for this flow.</p>
<p class="description">Debit rule: each chargeable AI chat turn debits the <strong>real API cost</strong> for that turn, converted to floscTokens via the Real Billing Factor below. If the AI API reports no cost (broken or misconfigured), the turn debits <strong>1 floscToken</strong> as a visible signal. Chat Logs record the real per-turn millicents so you can tune the factor from actual numbers.</p>

<div class="flosc-payments-status-banner">
    <strong>Flow:</strong> floscTokens are the runtime currency. A balance starts at the Wallet Initial Amount (or Guest/Member grant), then debits the real API cost of each chargeable turn, converted to floscTokens. The Real Billing Factor is how you set that conversion from your actual expenses.
</div>

<div class="flosc-payments-status-banner">
    <strong>Allocation rules (predict / avoid “tokens not working”):</strong>
    <ul class="flosc-token-predict-list">
        <li><strong>V→G once:</strong> guest_balance = visitor_remaining + Guest grant (idempotent per flow).</li>
        <li><strong>G→M once:</strong> member_balance = guest_remaining + Member grant (Access Code / first membership grant).</li>
        <li><strong>Product one-time:</strong> paid one-time products add One-time Product Token Grant (offer may override with more/less).</li>
        <li><strong>Product recurring:</strong> each paid cycle adds Recurring grant (monthly) or Recurring yearly grant — consistently, every cycle.</li>
        <li><strong>Product token cap:</strong> optional ceiling for product credits. <code>0</code> = no cap. At cap, payment still processes; credit is 0 until balance is spent down.</li>
        <li><strong>Admin accounts skip guest grant</strong> — test V→G with a normal guest user.</li>
        <li><strong>Guest grant = 0</strong> locks new guests at visitor remaining only. Keep a positive Guest grant for demos.</li>
    </ul>
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
            <p class="description">Visitor starting balance, in floscTokens. Turns per visitor = this amount / floscTokens debited per turn (see Real Billing Factor below).</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Nominal Display Factor (Millicents per Token)</th>
        <td>
            <input type="number" name="flow_tokens_nominal_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_nominal_rate_display); ?>" min="0.001" step="0.001" class="small-text">
            <p class="description">Token valuation factor for the nominal side of your business model. Use it to reflect your intended token economics in the FLOSC ecosystem. Supports thousandths (for example 1.375).</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Real Billing Factor (Millicents per Token)</th>
        <td>
            <input type="number" name="flow_tokens_real_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_real_rate_display); ?>" min="0.001" step="0.001" class="small-text">
            <p class="description">How many <strong>real API millicents equal 1 floscToken</strong> ($1 = 100,000 millicents). This converts the provider's real per-turn cost into the floscTokens debited from the wallet. Example: at <code>10</code>, a turn costing ~1,265 real millicents debits ~127 floscTokens, so a 1,000 wallet lasts ~8 turns. Raise this for fewer floscTokens per turn (more turns); lower it for more. Check Chat Logs for your actual per-turn millicents.</p>
        </td>
    </tr>

    <tr>
        <th scope="row">Conversion Summary</th>
        <td>
            <p class="description">
                Nominal display reference (using Nominal Display Factor): <strong><?php echo esc_html(number_format_i18n($flosc_nominal_millicents_per_message / 1000, 4)); ?> cents</strong>
                (<?php echo esc_html(number_format_i18n($flosc_nominal_millicents_per_message)); ?> millicents)<br>
                Real-cost reference (using Real Billing Factor): <strong><?php echo esc_html(number_format_i18n($flosc_real_millicents_per_message / 1000, 4)); ?> cents</strong>
                (<?php echo esc_html(number_format_i18n($flosc_real_millicents_per_message)); ?> millicents)<br>
                Runtime debit rule: chargeable AI turn -> real API millicents / Real Billing Factor -> floscTokens debited from wallet (or 1 floscToken if the API reports no cost).<br>
                Reporting rule: chat logs still record provider token usage and actual real millicent cost for each exchange.
            </p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_guest_token_grant">Guest Wallet Additional Token Grant Amount</label></th>
        <td>
            <input type="number" id="flow_guest_token_grant" name="flow_guest_token_grant" value="<?php echo esc_attr($flosc_guest_token_grant); ?>" min="0" step="1" class="regular-text">
            <p class="description">Per-flow amount <strong>added</strong> when a Visitor becomes a Guest: guest_balance = visitor_remaining + this grant (once per flow). Example: visitor has 2,000 left and this is 5,000 → Guest starts at 7,000.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_member_token_grant">Member Wallet Additional Token Grant Amount</label></th>
        <td>
            <input type="number" id="flow_member_token_grant" name="flow_member_token_grant" value="<?php echo esc_attr($flosc_member_token_grant); ?>" min="0" step="1" class="regular-text">
            <p class="description">Per-flow amount <strong>added</strong> when a Guest becomes a Member (Access Code / first membership): member_balance = guest_remaining + this grant (once per flow).</p>
        </td>
    </tr>
</table>

<h2>Product token grants</h2>
<p class="description">
    Flow defaults for paid products. Different products can grant more or less: set defaults here,
    and override per offer with <code>tokens.amount</code> / <code>tokens.cap</code> when needed.
    Works for one-time purchases and recurring cycles, with or without a cap.
</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_product_token_grant_onetime">One-time Product Token Grant</label></th>
        <td>
            <input type="number" id="flow_product_token_grant_onetime" name="flow_product_token_grant_onetime" value="<?php echo esc_attr($flosc_product_token_grant_onetime); ?>" min="0" step="1" class="regular-text">
            <p class="description">Tokens added on a <strong>one-time</strong> paid purchase (PayPal capture, Stripe one-time, token pack, etc.). A cheaper product can use a smaller offer override; a premium pack can use a larger one. Default matches recurring if unset.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_product_token_grant_recurring">Recurring Product Token Grant</label></th>
        <td>
            <input type="number" id="flow_product_token_grant_recurring" name="flow_product_token_grant_recurring" value="<?php echo esc_attr($flosc_product_token_grant_recurring); ?>" min="0" step="1" class="regular-text">
            <p class="description">Tokens added on <strong>each paid recurring cycle</strong> (e.g. monthly subscription). Applied consistently every cycle — first activation and each renewal. Default 10,000.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_product_token_grant_recurring_yearly">Recurring Yearly Product Token Grant</label></th>
        <td>
            <input type="number" id="flow_product_token_grant_recurring_yearly" name="flow_product_token_grant_recurring_yearly" value="<?php echo esc_attr($flosc_product_token_grant_recurring_yearly); ?>" min="0" step="1" class="regular-text">
            <p class="description">Tokens added on each paid <strong>yearly</strong> cycle. Default 35,000. Use a different number if a yearly product should fill more or less than monthly × 12.</p>
        </td>
    </tr>

    <tr>
        <th scope="row"><label for="flow_product_token_cap">Product Token Cap</label></th>
        <td>
            <input type="number" id="flow_product_token_cap" name="flow_product_token_cap" value="<?php echo esc_attr($flosc_product_token_cap); ?>" min="0" step="1" class="regular-text">
            <p class="description">
                Optional ceiling for product credits on this flow. Default 35,000.
                <strong>0 = no cap</strong> (always add the full grant).
                When at or above the cap, payment still processes and credits <strong>0</strong> until the wallet is spent below the cap.
                Partial room under the cap is filled only up to the cap.
            </p>
        </td>
    </tr>
</table>

<table class="form-table">
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
            <p class="description">Optional URL. After visitor tokens are depleted, one Guest Account Request is captured, a confirmation appears, input is disabled, and the chat redirects to this URL.</p>
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
