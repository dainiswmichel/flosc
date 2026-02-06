<?php
/**
 * Flow Edit Tab: Payments
 * 
 * Stripe and PayPal configuration
 */

if (!defined('ABSPATH')) exit;

$payments = $flow['payments'] ?? [];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <strong>💰 Flow-Specific Payment Accounts</strong>
        <p style="margin: 8px 0 0 0;">
            Each flow can have its own payment provider accounts. This allows different flows to 
            receive payments to different Stripe/PayPal accounts - useful for separate business entities 
            or revenue tracking.
        </p>
    </div>
    
    <h2 class="flosc-section-header">💳 Stripe Configuration</h2>
    
    <div class="flosc-form-field">
        <label>
            <input type="checkbox" name="payments_stripe_enabled" value="1" 
                   <?php checked($payments['stripe_enabled'] ?? '', '1'); ?>>
            <strong>Enable Stripe for this flow</strong>
        </label>
        <?php if (empty($payments['stripe_enabled'])): ?>
            <span class="description" style="margin-left: 10px;">
                (Uses global: <?php echo flosc_get_global_setting('payments.stripe_enabled', false) ? 'Enabled' : 'Disabled'; ?>)
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_mode">Stripe Mode</label>
        <select id="payments_stripe_mode" name="payments_stripe_mode" style="max-width: 300px;">
            <option value="">— Use Global —</option>
            <option value="test" <?php selected($payments['stripe_mode'] ?? '', 'test'); ?>>🧪 Test Mode</option>
            <option value="live" <?php selected($payments['stripe_mode'] ?? '', 'live'); ?>>🟢 Live Mode</option>
        </select>
        <?php if (empty($payments['stripe_mode'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('payments.stripe_mode', 'test')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <h3 style="margin-top: 30px; padding: 10px; background: #f0f0f1;">Test Mode Credentials</h3>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_test_pk">Test Publishable Key</label>
        <input type="text" id="payments_stripe_test_pk" name="payments_stripe_test_pk" 
               value="<?php echo esc_attr($payments['stripe_test_pk'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['stripe_test_pk']) ? 'pk_test_... (using global)' : ''; ?>">
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_test_sk">Test Secret Key</label>
        <input type="password" id="payments_stripe_test_sk" name="payments_stripe_test_sk" 
               value="<?php echo esc_attr($payments['stripe_test_sk'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['stripe_test_sk']) ? 'sk_test_... (using global)' : ''; ?>">
    </div>
    
    <h3 style="margin-top: 30px; padding: 10px; background: #f0f0f1;">Live Mode Credentials</h3>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_live_pk">Live Publishable Key</label>
        <input type="text" id="payments_stripe_live_pk" name="payments_stripe_live_pk" 
               value="<?php echo esc_attr($payments['stripe_live_pk'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['stripe_live_pk']) ? 'pk_live_... (using global)' : ''; ?>">
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_live_sk">Live Secret Key</label>
        <input type="password" id="payments_stripe_live_sk" name="payments_stripe_live_sk" 
               value="<?php echo esc_attr($payments['stripe_live_sk'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['stripe_live_sk']) ? 'sk_live_... (using global)' : ''; ?>">
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_stripe_webhook_secret">Webhook Signing Secret</label>
        <input type="password" id="payments_stripe_webhook_secret" name="payments_stripe_webhook_secret" 
               value="<?php echo esc_attr($payments['stripe_webhook_secret'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['stripe_webhook_secret']) ? 'whsec_... (using global)' : ''; ?>">
        <p class="description">
            Webhook endpoint: <code><?php echo home_url('/flosc-webhook/stripe?flow=' . $flow_id); ?></code>
        </p>
    </div>
    
    <h2 class="flosc-section-header">🅿️ PayPal Configuration</h2>
    
    <div class="flosc-form-field">
        <label>
            <input type="checkbox" name="payments_paypal_enabled" value="1" 
                   <?php checked($payments['paypal_enabled'] ?? '', '1'); ?>>
            <strong>Enable PayPal for this flow</strong>
        </label>
        <?php if (empty($payments['paypal_enabled'])): ?>
            <span class="description" style="margin-left: 10px;">
                (Uses global: <?php echo flosc_get_global_setting('payments.paypal_enabled', false) ? 'Enabled' : 'Disabled'; ?>)
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_paypal_mode">PayPal Mode</label>
        <select id="payments_paypal_mode" name="payments_paypal_mode" style="max-width: 300px;">
            <option value="">— Use Global —</option>
            <option value="sandbox" <?php selected($payments['paypal_mode'] ?? '', 'sandbox'); ?>>🧪 Sandbox (Testing)</option>
            <option value="live" <?php selected($payments['paypal_mode'] ?? '', 'live'); ?>>🟢 Live (Production)</option>
        </select>
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_paypal_client_id">PayPal Client ID</label>
        <input type="text" id="payments_paypal_client_id" name="payments_paypal_client_id" 
               value="<?php echo esc_attr($payments['paypal_client_id'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['paypal_client_id']) ? 'Using global' : ''; ?>">
    </div>
    
    <div class="flosc-form-field">
        <label for="payments_paypal_secret">PayPal Secret</label>
        <input type="password" id="payments_paypal_secret" name="payments_paypal_secret" 
               value="<?php echo esc_attr($payments['paypal_secret'] ?? ''); ?>"
               placeholder="<?php echo empty($payments['paypal_secret']) ? 'Using global' : ''; ?>">
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
</form>
