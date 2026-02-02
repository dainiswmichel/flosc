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
 * BACKEND STATUS v1.0.4: 
 * - Settings UI: COMPLETE
 * - Stripe payment intents: COMPLETE (see create_payment_intent in flosc.php)
 * - Webhook handling: COMPLETE (see handle_webhook in flosc.php)
 * - Signature verification: IMPLEMENTED (TASK-015)
 * - PayPal integration: DEFERRED (not in MVP scope)
 * - Manual payments: DEFERRED (not in MVP scope)
 */

if (!defined('ABSPATH')) exit;

$stripe_enabled = get_option('flosc_stripe_enabled', false);
$stripe_mode = get_option('flosc_stripe_mode', 'test');
$paypal_enabled = get_option('flosc_paypal_enabled', false);
$manual_payments_enabled = get_option('flosc_manual_payments_enabled', false);
?>

<h2>Payment Processing Configuration</h2>
<p>Configure your payment providers to accept payments for offers. Start with test mode, then switch to live when ready.</p>

<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
    <strong>⚠️ Development Status:</strong> Payment provider configuration is ready. Actual payment processing, webhooks, and order management require backend implementation (pseudocode provided below).
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
                <input type="checkbox" name="flosc_stripe_enabled" value="1" <?php checked($stripe_enabled); ?>>
                <strong>Enable Stripe Payments</strong>
            </label>
            <p class="description">Accept credit/debit cards via Stripe. <a href="https://stripe.com" target="_blank">Sign up for Stripe →</a></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_mode">Stripe Mode</label></th>
        <td>
            <select name="flosc_stripe_mode" id="flosc_stripe_mode" class="regular-text">
                <option value="test" <?php selected($stripe_mode, 'test'); ?>>🧪 Test Mode (Use test keys)</option>
                <option value="live" <?php selected($stripe_mode, 'live'); ?>>🟢 Live Mode (Real payments)</option>
            </select>
            <p class="description">Always start in test mode. Switch to live mode only when ready to accept real payments.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;"><strong>Test Mode Credentials</strong></th>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_test_pk">Test Publishable Key</label></th>
        <td>
            <input type="text" id="flosc_stripe_test_pk" name="flosc_stripe_test_pk" 
                   value="<?php echo esc_attr(get_option('flosc_stripe_test_pk', '')); ?>" 
                   class="large-text" placeholder="pk_test_...">
            <p class="description">Starts with <code>pk_test_</code>. Find in Stripe Dashboard → Developers → API Keys</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_test_sk">Test Secret Key</label></th>
        <td>
            <input type="password" id="flosc_stripe_test_sk" name="flosc_stripe_test_sk" 
                   value="<?php echo esc_attr(get_option('flosc_stripe_test_sk', '')); ?>" 
                   class="large-text" placeholder="sk_test_...">
            <p class="description">Starts with <code>sk_test_</code>. Keep this secret! Never share publicly.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row" colspan="2" style="background: #f0f0f1; padding: 10px;"><strong>Live Mode Credentials</strong></th>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_live_pk">Live Publishable Key</label></th>
        <td>
            <input type="text" id="flosc_stripe_live_pk" name="flosc_stripe_live_pk" 
                   value="<?php echo esc_attr(get_option('flosc_stripe_live_pk', '')); ?>" 
                   class="large-text" placeholder="pk_live_...">
            <p class="description">Starts with <code>pk_live_</code>. Only use when ready for real payments.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_live_sk">Live Secret Key</label></th>
        <td>
            <input type="password" id="flosc_stripe_live_sk" name="flosc_stripe_live_sk" 
                   value="<?php echo esc_attr(get_option('flosc_stripe_live_sk', '')); ?>" 
                   class="large-text" placeholder="sk_live_...">
            <p class="description">Starts with <code>sk_live_</code>. EXTREMELY SENSITIVE - never share or commit to code.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_stripe_webhook_secret">Webhook Signing Secret</label></th>
        <td>
            <input type="password" id="flosc_stripe_webhook_secret" name="flosc_stripe_webhook_secret" 
                   value="<?php echo esc_attr(get_option('flosc_stripe_webhook_secret', '')); ?>" 
                   class="large-text" placeholder="whsec_...">
            <p class="description">
                Webhook endpoint: <code><?php echo home_url('/flosc-webhook/stripe'); ?></code><br>
                Configure in Stripe Dashboard → Developers → Webhooks. Events to listen for: <code>checkout.session.completed</code>, <code>invoice.paid</code>, <code>customer.subscription.deleted</code>
            </p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- PAYPAL CONFIGURATION -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>🅿️ PayPal Configuration <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
<p class="description">Alternative payment method for users who prefer PayPal over credit cards.</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable PayPal</th>
        <td>
            <label>
                <input type="checkbox" name="flosc_paypal_enabled" value="1" <?php checked($paypal_enabled); ?>>
                <strong>Enable PayPal Payments</strong>
            </label>
            <p class="description">Accept payments via PayPal. <a href="https://www.paypal.com/merchantapps" target="_blank">Sign up for PayPal Business →</a></p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_paypal_client_id">PayPal Client ID</label></th>
        <td>
            <input type="text" id="flosc_paypal_client_id" name="flosc_paypal_client_id" 
                   value="<?php echo esc_attr(get_option('flosc_paypal_client_id', '')); ?>" 
                   class="large-text">
            <p class="description">Find in PayPal Developer Dashboard → My Apps & Credentials</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_paypal_secret">PayPal Secret</label></th>
        <td>
            <input type="password" id="flosc_paypal_secret" name="flosc_paypal_secret" 
                   value="<?php echo esc_attr(get_option('flosc_paypal_secret', '')); ?>" 
                   class="large-text">
            <p class="description">Keep this secret. Used for server-side verification.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_paypal_mode">PayPal Mode</label></th>
        <td>
            <select name="flosc_paypal_mode" id="flosc_paypal_mode" class="regular-text">
                <option value="sandbox" <?php selected(get_option('flosc_paypal_mode', 'sandbox'), 'sandbox'); ?>>Sandbox (Testing)</option>
                <option value="live" <?php selected(get_option('flosc_paypal_mode', 'sandbox'), 'live'); ?>>Live (Production)</option>
            </select>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- MANUAL PAYMENTS -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>💵 Manual Payments <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
<p class="description">Accept payments via bank transfer, check, or other offline methods. Admin manually confirms payment and grants access.</p>

<table class="form-table">
    <tr>
        <th scope="row">Enable Manual Payments</th>
        <td>
            <label>
                <input type="checkbox" name="flosc_manual_payments_enabled" value="1" <?php checked($manual_payments_enabled); ?>>
                <strong>Enable Manual Payment Option</strong>
            </label>
            <p class="description">Show "Pay by Bank Transfer" or "Contact for Payment" option on checkout.</p>
        </td>
    </tr>
    
    <tr>
        <th scope="row"><label for="flosc_manual_payment_instructions">Payment Instructions</label></th>
        <td>
            <textarea id="flosc_manual_payment_instructions" name="flosc_manual_payment_instructions" 
                      rows="6" class="large-text"><?php 
                echo esc_textarea(get_option('flosc_manual_payment_instructions', 'Bank Transfer Instructions:

Account Name: Your Business Name
Bank: Your Bank
Account Number: 123456789
Routing Number: 987654321

Please include your name and order number in the transfer memo.
Access will be granted within 24 hours of payment confirmation.')); 
            ?></textarea>
            <p class="description">Instructions shown to users who choose manual payment.</p>
        </td>
    </tr>
</table>

<!-- ============================================ -->
<!-- ORDER MANAGEMENT -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>📦 Order Management <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
<p class="description">View and manage customer orders, refunds, and access grants.</p>

<div class="card" style="max-width: 100%;">
    <h4>Recent Orders</h4>
    <p style="color: #667; font-style: italic;">No orders yet. Order management will appear here once payment processing is implemented.</p>
    
    <table class="widefat" style="margin-top: 15px; opacity: 0.5;">
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
                <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
                    Order data will appear here after payment integration
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- ============================================ -->
<!-- TEST PAYMENT CARDS -->
<!-- ============================================ -->
<hr style="margin: 40px 0;">
<h3>🧪 Test Payment Cards (Stripe Test Mode)</h3>
<p class="description">Use these cards in test mode to simulate different payment scenarios.</p>

<div style="background: #f9f9f9; padding: 15px; border-radius: 4px;">
    <table style="width: 100%;">
        <tr style="background: #fff;">
            <td style="padding: 10px;"><strong>Successful Payment</strong></td>
            <td style="padding: 10px;"><code>4242 4242 4242 4242</code></td>
            <td style="padding: 10px;">Any future expiry, any CVC</td>
        </tr>
        <tr>
            <td style="padding: 10px;"><strong>Declined Card</strong></td>
            <td style="padding: 10px;"><code>4000 0000 0000 0002</code></td>
            <td style="padding: 10px;">Simulates declined payment</td>
        </tr>
        <tr style="background: #fff;">
            <td style="padding: 10px;"><strong>Insufficient Funds</strong></td>
            <td style="padding: 10px;"><code>4000 0000 0000 9995</code></td>
            <td style="padding: 10px;">Simulates insufficient funds error</td>
        </tr>
        <tr>
            <td style="padding: 10px;"><strong>3D Secure Required</strong></td>
            <td style="padding: 10px;"><code>4000 0027 6000 3184</code></td>
            <td style="padding: 10px;">Requires 3D Secure authentication</td>
        </tr>
    </table>
    <p style="margin: 10px 0 0 0;"><a href="https://stripe.com/docs/testing" target="_blank">See full list of test cards →</a></p>
</div>

<!--
BACKEND IMPLEMENTATION NOTES:
=============================

Payment processing flow integrates Stripe, PayPal, and manual payments.

STRIPE PAYMENT PROCESSING:

function flosc_process_stripe_payment($offer_id, $user_email, $user_name) {
    require_once 'vendor/autoload.php'; // Stripe PHP library
    
    $mode = get_option('flosc_stripe_mode', 'test');
    $secret_key = $mode === 'live' 
        ? get_option('flosc_stripe_live_sk') 
        : get_option('flosc_stripe_test_sk');
    
    \Stripe\Stripe::setApiKey($secret_key);
    
    $offer = flosc()->sale()->offers()->get_offer($offer_id);
    
    try {
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $offer['name'],
                        'description' => $offer['description'],
                    ],
                    'unit_amount' => $offer['price'] * 100, // Cents
                ],
                'quantity' => 1,
            ]],
            'mode' => $offer['type'] === 'subscription' ? 'subscription' : 'payment',
            'success_url' => home_url('/success?session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/checkout?cancelled=1'),
            'customer_email' => $user_email,
            'client_reference_id' => $user_email,
            'metadata' => [
                'offer_id' => $offer_id,
                'user_name' => $user_name,
            ],
        ]);
        
        return ['success' => true, 'checkout_url' => $session->url];
        
    } catch (\Exception $e) {
        error_log('Stripe payment error: ' . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

STRIPE WEBHOOK HANDLER:

add_action('rest_api_init', function() {
    register_rest_route('flosc/v1', '/webhook/stripe', [
        'methods' => 'POST',
        'callback' => 'flosc_handle_stripe_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function flosc_handle_stripe_webhook($request) {
    $payload = $request->get_body();
    $sig_header = $request->get_header('stripe_signature');
    $webhook_secret = get_option('flosc_stripe_webhook_secret');
    
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
        
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                
                // Grant access to user
                $user_email = $session->customer_email;
                $offer_id = $session->metadata->offer_id;
                
                flosc_grant_offer_access($user_email, $offer_id);
                flosc_send_purchase_confirmation_email($user_email, $offer_id);
                flosc_track_offer_conversion($offer_id, $user_email, $session->amount_total / 100);
                
                break;
                
            case 'invoice.paid':
                // Handle subscription renewal
                break;
                
            case 'customer.subscription.deleted':
                // Revoke access when subscription cancelled
                break;
        }
        
        return new WP_REST_Response(['received' => true], 200);
        
    } catch (\Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 400);
    }
}

GRANT ACCESS AFTER PAYMENT:

function flosc_grant_offer_access($user_email, $offer_id) {
    $user = get_user_by('email', $user_email);
    if (!$user) {
        // Create user account if doesn't exist
        $user_id = wp_create_user($user_email, wp_generate_password(), $user_email);
        $user = get_user_by('id', $user_id);
    }
    
    // Grant access to offer content
    $purchased_offers = get_user_meta($user->ID, 'flosc_purchased_offers', true) ?: [];
    $purchased_offers[] = [
        'offer_id' => $offer_id,
        'purchased_at' => current_time('mysql'),
        'status' => 'active',
    ];
    update_user_meta($user->ID, 'flosc_purchased_offers', $purchased_offers);
    
    // Update user phase to 'sale' or 'content'
    update_user_meta($user->ID, 'flosc_user_phase', 'sale');
    
    // Log transaction
    flosc_log_transaction([
        'user_id' => $user->ID,
        'offer_id' => $offer_id,
        'amount' => flosc()->sale()->offers()->get_offer($offer_id)['price'],
        'payment_method' => 'stripe',
        'status' => 'completed',
        'timestamp' => current_time('mysql'),
    ]);
}

PAYPAL INTEGRATION (similar pattern):

function flosc_process_paypal_payment($offer_id, $user_email) {
    // Use PayPal PHP SDK
    // Create order, redirect to PayPal, handle return webhook
    // Similar to Stripe but using PayPal's API
}

MANUAL PAYMENT WORKFLOW:

function flosc_create_manual_payment_order($offer_id, $user_email, $user_name) {
    $order_id = 'manual_' . wp_generate_password(8, false);
    
    $order = [
        'id' => $order_id,
        'offer_id' => $offer_id,
        'user_email' => $user_email,
        'user_name' => $user_name,
        'status' => 'pending_payment',
        'payment_method' => 'manual',
        'created_at' => current_time('mysql'),
    ];
    
    // Store order
    $orders = get_option('flosc_manual_orders', []);
    $orders[$order_id] = $order;
    update_option('flosc_manual_orders', $orders);
    
    // Send email with payment instructions
    flosc_send_manual_payment_instructions($user_email, $order_id);
    
    return $order;
}

// Admin manually confirms payment and grants access
function flosc_confirm_manual_payment($order_id) {
    $orders = get_option('flosc_manual_orders', []);
    if (isset($orders[$order_id])) {
        $order = $orders[$order_id];
        $order['status'] = 'completed';
        $order['confirmed_at'] = current_time('mysql');
        $orders[$order_id] = $order;
        update_option('flosc_manual_orders', $orders);
        
        flosc_grant_offer_access($order['user_email'], $order['offer_id']);
        flosc_send_purchase_confirmation_email($order['user_email'], $order['offer_id']);
    }
}
-->
