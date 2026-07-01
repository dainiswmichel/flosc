<?php
/**
 * FLOSC Payments Configuration Tab
 * 
 * Configure payment providers for processing purchases:
 * - Stripe (credit cards, one-time and subscriptions)
 * - PayPal (alternative payment method)
 * - Manual payments (bank transfer, check, etc.)
 * - Payment webhooks and order management
 * 
 * Integrates with:
 * - Offers tab (processes purchases for configured offers)
 * - User accounts (grants access after successful payment)
 * - Email automation (sends receipts, confirmations)
 * 
 * BACKEND STATUS v8.0.0:
 * - Settings UI: COMPLETE
 * - Stripe payment intents: COMPLETE
 * - Webhook handling: COMPLETE
 * - Signature verification: IMPLEMENTED
 * - PayPal integration: AVAILABLE
 * - Manual payment instructions: AVAILABLE
 * 
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('💳', 'Payments');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_payments_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-payments';
$flosc_stripe_enabled = $flosc_flow_settings['stripe_enabled'] ?? false;
$flosc_stripe_mode = $flosc_flow_settings['stripe_mode'] ?? 'test';
$flosc_paypal_enabled = $flosc_flow_settings['paypal_enabled'] ?? false;
$flosc_manual_payments_enabled = $flosc_flow_settings['manual_payments_enabled'] ?? false;
?>

<div class="flosc-payments-docs-wrap">
    <a href="<?php echo esc_url($flosc_payments_docs_url); ?>" class="flosc-payments-docs-link">Docs</a>
</div>

<h2>Payment Processing Configuration</h2>
<p>Configure your payment providers to accept payments for offers. Start with test mode, then switch to live when ready.</p>

<div class="flosc-payments-status-banner">
    <strong>Payment status:</strong> FLOSC payment routes are active. Use test/sandbox credentials to validate your full checkout path before switching to live keys.
</div>

<!-- ============================================ -->
<!-- STRIPE PAYMENT CONFIGURATION -->
<!-- ============================================ -->
<h3>💳 Stripe Configuration</h3>
<p class="description">Stripe handles credit cards, debit cards, and subscriptions. Most popular payment provider for online courses.</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable Stripe</th>
        <td>
            <label>
                <input type="checkbox" name="flow_stripe_enabled" value="1" <?php checked($flosc_stripe_enabled); ?>>
                <strong>Enable Stripe Payments</strong>
            </label>
            <p class="description">Accept credit/debit cards via Stripe. <a href="https://stripe.com" target="_blank">Sign up for Stripe →</a></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_mode">Stripe Mode</label></th>
        <td>
            <select name="flow_stripe_mode" id="flow_stripe_mode" class="regular-text">
                <option value="test" <?php selected($flosc_stripe_mode, 'test'); ?>>🧪 Test Mode (Use test keys)</option>
                <option value="live" <?php selected($flosc_stripe_mode, 'live'); ?>>🟢 Live Mode (Real payments)</option>
            </select>
            <p class="description">Always start in test mode. Switch to live mode only when ready to accept real payments.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2" class="flosc-payments-subhead"><strong>Test Mode Credentials</strong></th>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_test_pk">Test Publishable Key</label></th>
        <td>
            <input type="text" id="flow_stripe_test_pk" name="flow_stripe_test_pk" 
                   value="<?php echo esc_attr($flosc_flow_settings['stripe_test_pk'] ?? ''); ?>" 
                   class="large-text" placeholder="pk_test_...">
            <p class="description">Starts with <code>pk_test_</code>. Find in Stripe Dashboard → Developers → API Keys</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_test_sk">Test Secret Key</label></th>
        <td>
            <input type="password" id="flow_stripe_test_sk" name="flow_stripe_test_sk" 
                   value="<?php echo esc_attr($flosc_flow_settings['stripe_test_sk'] ?? ''); ?>" 
                   class="large-text" placeholder="sk_test_...">
            <p class="description">Starts with <code>sk_test_</code>. Keep this secret! Never share publicly.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2" class="flosc-payments-subhead"><strong>Live Mode Credentials</strong></th>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_live_pk">Live Publishable Key</label></th>
        <td>
            <input type="text" id="flow_stripe_live_pk" name="flow_stripe_live_pk" 
                   value="<?php echo esc_attr($flosc_flow_settings['stripe_live_pk'] ?? ''); ?>" 
                   class="large-text" placeholder="pk_live_...">
            <p class="description">Starts with <code>pk_live_</code>. Only use when ready for real payments.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_live_sk">Live Secret Key</label></th>
        <td>
            <input type="password" id="flow_stripe_live_sk" name="flow_stripe_live_sk" 
                   value="<?php echo esc_attr($flosc_flow_settings['stripe_live_sk'] ?? ''); ?>" 
                   class="large-text" placeholder="sk_live_...">
            <p class="description">Starts with <code>sk_live_</code>. EXTREMELY SENSITIVE - never share or commit to code.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_stripe_webhook_secret">Webhook Signing Secret</label></th>
        <td>
            <input type="password" id="flow_stripe_webhook_secret" name="flow_stripe_webhook_secret" 
                   value="<?php echo esc_attr($flosc_flow_settings['stripe_webhook_secret'] ?? ''); ?>" 
                   class="large-text" placeholder="whsec_...">
            <p class="description">
                Webhook endpoint: <code><?php echo esc_html(home_url('/flosc-webhook/stripe')); ?></code><br>
                Configure in Stripe Dashboard → Developers → Webhooks. Events to listen for: <code>checkout.session.completed</code>, <code>invoice.paid</code>, <code>customer.subscription.deleted</code>
            </p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- PAYPAL CONFIGURATION -->
<!-- ============================================ -->
<hr class="flosc-payments-divider">
<h3>🅿️ PayPal Configuration</h3>
<p class="description">Accept payments via PayPal.</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable PayPal</th>
        <td>
            <label>
                <input type="checkbox" name="flow_paypal_enabled" value="1" <?php checked($flosc_paypal_enabled); ?>>
                <strong>Enable PayPal Payments</strong>
            </label>
            <p class="description">Accept payments via PayPal. <a href="https://www.paypal.com/merchantapps" target="_blank">Sign up for PayPal Business →</a></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_paypal_client_id">PayPal Client ID</label></th>
        <td>
            <input type="text" id="flow_paypal_client_id" name="flow_paypal_client_id" 
                   value="<?php echo esc_attr($flosc_flow_settings['paypal_client_id'] ?? ''); ?>" 
                   class="large-text">
            <p class="description">Find in PayPal Developer Dashboard → My Apps & Credentials</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_paypal_secret">PayPal Secret</label></th>
        <td>
            <input type="password" id="flow_paypal_secret" name="flow_paypal_secret" 
                   value="<?php echo esc_attr($flosc_flow_settings['paypal_secret'] ?? ''); ?>" 
                   class="large-text">
            <p class="description">Keep this secret. Used for server-side verification.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_paypal_mode">PayPal Mode</label></th>
        <td>
            <select name="flow_paypal_mode" id="flow_paypal_mode" class="regular-text">
                <option value="sandbox" <?php selected($flosc_flow_settings['paypal_mode'] ?? 'sandbox', 'sandbox'); ?>>Sandbox (Testing)</option>
                <option value="live" <?php selected($flosc_flow_settings['paypal_mode'] ?? 'sandbox', 'live'); ?>>Live (Production)</option>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row">Connection Test</th>
        <td>
            <button type="button" id="flosc-paypal-test" class="button button-secondary">Test PayPal Connection</button>
            <span id="flosc-paypal-test-result" class="flosc-paypal-test-result"></span>
            <?php ob_start(); ?>
            document.getElementById('flosc-paypal-test').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('flosc-paypal-test-result');
                btn.disabled = true;
                btn.textContent = 'Testing...';
                result.textContent = '';
                fetch(ajaxurl + '?action=flosc_test_paypal&_wpnonce=<?php echo esc_js(wp_create_nonce("flosc_test_paypal")); ?>')
                    .then(r => r.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.textContent = 'Test PayPal Connection';
                        if (data.success) {
                            result.innerHTML = '<span class="flosc-paypal-result-success">\u2705 Connected — ' + data.data.mode + ' mode, ' + data.data.app_name + '</span>';
                        } else {
                            result.innerHTML = '<span class="flosc-paypal-result-error">\u274c ' + (data.data || 'Connection failed') + '</span>';
                        }
                    })
                    .catch(() => {
                        btn.disabled = false;
                        btn.textContent = 'Test PayPal Connection';
                        result.innerHTML = '<span class="flosc-paypal-result-error-lite">\u274c Network error</span>';
                    });
            });
            <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- MANUAL PAYMENTS -->
<!-- ============================================ -->
<hr class="flosc-payments-divider">
<h3>💵 Manual Payments</h3>
<p class="description">Accept payments via bank transfer, check, or other offline methods. Admin manually confirms payment and grants access.</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable Manual Payments</th>
        <td>
            <label>
                <input type="checkbox" name="flow_manual_payments_enabled" value="1" <?php checked($flosc_manual_payments_enabled); ?>>
                <strong>Enable Manual Payment Option</strong>
            </label>
            <p class="description">Show "Pay by Bank Transfer" or "Contact for Payment" option on checkout.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flow_manual_payment_instructions">Payment Instructions</label></th>
        <td>
            <textarea id="flow_manual_payment_instructions" name="flow_manual_payment_instructions" 
                      rows="6" class="large-text"><?php 
                echo esc_textarea($flosc_flow_settings['manual_payment_instructions'] ?? 'Bank Transfer Instructions:

Account Name: Your Business Name
Bank: Your Bank
Account Number: 123456789
Routing Number: 987654321

Please include your name and order number in the transfer memo.
Access will be granted within 24 hours of payment confirmation.'); 
            ?></textarea>
            <p class="description">Instructions shown to users who choose manual payment.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- ORDER MANAGEMENT -->
<!-- ============================================ -->
<hr class="flosc-payments-divider">
<h3>📦 Order Management</h3>
<p class="description">View and manage customer orders, refunds, and access grants.</p>

<div class="card flosc-payments-orders-card">
    <h4>Recent Orders</h4>
    <p class="flosc-payments-empty">No orders yet. Order management will appear here once payment processing is implemented.</p>
    
    <table class="widefat flosc-payments-orders-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Offer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="7" class="flosc-payments-orders-empty-row">
                    Order history appears here as transactions are processed.
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ============================================ -->
<!-- TEST PAYMENT CARDS -->
<!-- ============================================ -->
<hr class="flosc-payments-divider">
<h3>🧪 Test Payment Cards (Stripe Test Mode)</h3>
<p class="description">Use these cards in test mode to simulate different payment scenarios.</p>

<div class="flosc-payments-test-cards-wrap">
    <table class="flosc-payments-test-cards-table">
        <tr class="flosc-payments-test-cards-row-alt">
            <td class="flosc-payments-test-cards-cell"><strong>Successful Payment</strong></td>
            <td class="flosc-payments-test-cards-cell"><code>4242 4242 4242 4242</code></td>
            <td class="flosc-payments-test-cards-cell">Any future expiry, any CVC</td>
        </tr>
        <tr>
            <td class="flosc-payments-test-cards-cell"><strong>Declined Card</strong></td>
            <td class="flosc-payments-test-cards-cell"><code>4000 0000 0000 0002</code></td>
            <td class="flosc-payments-test-cards-cell">Simulates declined payment</td>
        </tr>
        <tr class="flosc-payments-test-cards-row-alt">
            <td class="flosc-payments-test-cards-cell"><strong>Insufficient Funds</strong></td>
            <td class="flosc-payments-test-cards-cell"><code>4000 0000 0000 9995</code></td>
            <td class="flosc-payments-test-cards-cell">Simulates insufficient funds error</td>
        </tr>
        <tr>
            <td class="flosc-payments-test-cards-cell"><strong>3D Secure Required</strong></td>
            <td class="flosc-payments-test-cards-cell"><code>4000 0027 6000 3184</code></td>
            <td class="flosc-payments-test-cards-cell">Requires 3D Secure authentication</td>
        </tr>
    </table>
    <p class="flosc-payments-test-cards-link-wrap"><a href="https://stripe.com/docs/testing" target="_blank">See full list of test cards →</a></p>
</div>

