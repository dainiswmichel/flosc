<?php
/**
 * FLOSC Stripe Payment Provider
 * 
 * Handles traditional card payments and subscriptions via Stripe.
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Stripe_Provider extends FLOSC_Payment_Provider {
    
    public function get_id() {
        return 'stripe';
    }
    
    public function get_name() {
        return 'Stripe';
    }
    
    public function get_description() {
        return 'Accept credit cards, Apple Pay, Google Pay, and subscriptions via Stripe.';
    }
    
    public function get_icon() {
        return '💳';
    }
    
    public function is_configured() {
        return !empty($this->get_secret_key()) && !empty($this->get_publishable_key());
    }
    
    public function supports_subscriptions() {
        return true;
    }
    
    /**
     * Get settings fields for admin UI
     */
    public function get_settings_fields() {
        return [
            'mode' => [
                'type' => 'select',
                'label' => 'Mode',
                'options' => [
                    'test' => 'Test Mode',
                    'live' => 'Live Mode',
                ],
                'default' => 'test',
            ],
            'test_publishable_key' => [
                'type' => 'text',
                'label' => 'Test Publishable Key',
                'placeholder' => 'pk_test_...',
            ],
            'test_secret_key' => [
                'type' => 'password',
                'label' => 'Test Secret Key',
                'placeholder' => 'sk_test_...',
            ],
            'live_publishable_key' => [
                'type' => 'text',
                'label' => 'Live Publishable Key',
                'placeholder' => 'pk_live_...',
            ],
            'live_secret_key' => [
                'type' => 'password',
                'label' => 'Live Secret Key',
                'placeholder' => 'sk_live_...',
            ],
            'webhook_secret' => [
                'type' => 'password',
                'label' => 'Webhook Signing Secret',
                'placeholder' => 'whsec_...',
                'description' => 'Webhook URL: ' . rest_url('flosc/v1/webhooks/stripe'),
            ],
        ];
    }
    
    /**
     * Get appropriate keys based on mode
     */
    private function get_mode() {
        return $this->get_setting('mode', 'test');
    }
    
    private function get_publishable_key() {
        $mode = $this->get_mode();
        return $this->get_setting($mode . '_publishable_key', '');
    }
    
    private function get_secret_key() {
        $mode = $this->get_mode();
        return $this->get_setting($mode . '_secret_key', '');
    }
    
    /**
     * Client-side config (passed to JavaScript)
     */
    public function get_client_config() {
        return [
            'publishableKey' => $this->get_publishable_key(),
            'mode' => $this->get_mode(),
        ];
    }
    
    /**
     * Process payment
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        $pricing = $offer['pricing']['stripe'] ?? [];
        $price_id = $pricing['price_id'] ?? '';
        
        if (empty($price_id)) {
            return new WP_Error('no_price', 'No Stripe Price ID configured for this offer');
        }
        
        $user = get_user_by('ID', $user_id);
        
        // Handle based on offer type
        if ($offer['type'] === 'subscription') {
            return $this->create_subscription($user, $price_id, $payment_data);
        } else {
            return $this->create_payment($user, $price_id, $payment_data);
        }
    }
    
    /**
     * Create one-time payment
     */
    private function create_payment($user, $price_id, $payment_data) {
        // If we have a payment_method_id, create PaymentIntent and confirm
        if (!empty($payment_data['payment_method_id'])) {
            return $this->confirm_payment($user, $price_id, $payment_data['payment_method_id']);
        }
        
        // Otherwise, create a PaymentIntent for client-side confirmation
        return $this->create_payment_intent($user, $price_id);
    }
    
    /**
     * Create PaymentIntent for client-side confirmation
     */
    public function create_payment_intent($user, $price_id_or_amount, $currency = 'usd') {
        // First, get the price details from Stripe
        if (strpos($price_id_or_amount, 'price_') === 0) {
            $price = $this->api_request('GET', '/prices/' . $price_id_or_amount);
            if (is_wp_error($price)) {
                return $price;
            }
            $amount = $price['unit_amount'];
            $currency = $price['currency'];
        } else {
            $amount = intval($price_id_or_amount);
        }
        
        $response = $this->api_request('POST', '/payment_intents', [
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
            ],
            'receipt_email' => $user->user_email,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'client_secret' => $response['client_secret'],
            'payment_intent_id' => $response['id'],
            'requires_action' => $response['status'] === 'requires_action',
        ];
    }
    
    /**
     * Confirm a payment (server-side)
     */
    private function confirm_payment($user, $price_id, $payment_method_id) {
        // Get price details
        $price = $this->api_request('GET', '/prices/' . $price_id);
        if (is_wp_error($price)) {
            return $price;
        }
        
        // Create and confirm PaymentIntent
        $response = $this->api_request('POST', '/payment_intents', [
            'amount' => $price['unit_amount'],
            'currency' => $price['currency'],
            'payment_method' => $payment_method_id,
            'confirm' => true,
            'metadata' => [
                'user_id' => $user->ID,
                'user_email' => $user->user_email,
            ],
            'receipt_email' => $user->user_email,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if ($response['status'] === 'succeeded') {
            return [
                'success' => true,
                'transaction_id' => $response['id'],
                'amount' => $response['amount'],
                'currency' => $response['currency'],
            ];
        }
        
        if ($response['status'] === 'requires_action') {
            return [
                'requires_action' => true,
                'client_secret' => $response['client_secret'],
                'payment_intent_id' => $response['id'],
            ];
        }
        
        return new WP_Error('payment_failed', 'Payment failed: ' . ($response['last_payment_error']['message'] ?? 'Unknown error'));
    }
    
    /**
     * Create subscription
     */
    private function create_subscription($user, $price_id, $payment_data) {
        // Get or create Stripe customer
        $customer_id = $this->get_or_create_customer($user);
        
        if (is_wp_error($customer_id)) {
            return $customer_id;
        }
        
        $sub_data = [
            'customer' => $customer_id,
            'items' => [['price' => $price_id]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'user_id' => $user->ID,
            ],
        ];
        
        // Add payment method if provided
        if (!empty($payment_data['payment_method_id'])) {
            $sub_data['default_payment_method'] = $payment_data['payment_method_id'];
        }
        
        $response = $this->api_request('POST', '/subscriptions', $sub_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Check if needs payment confirmation
        $invoice = $response['latest_invoice'];
        $payment_intent = $invoice['payment_intent'] ?? null;
        
        if ($payment_intent && $payment_intent['status'] === 'requires_action') {
            return [
                'requires_action' => true,
                'client_secret' => $payment_intent['client_secret'],
                'subscription_id' => $response['id'],
            ];
        }
        
        if ($response['status'] === 'active' || $response['status'] === 'trialing') {
            // Save subscription ID to user
            update_user_meta($user->ID, '_flosc_stripe_subscription', $response['id']);
            
            return [
                'success' => true,
                'transaction_id' => $response['id'],
                'subscription_id' => $response['id'],
                'status' => $response['status'],
            ];
        }
        
        return new WP_Error('subscription_failed', 'Failed to create subscription');
    }
    
    /**
     * Get or create Stripe Customer
     */
    private function get_or_create_customer($user) {
        $customer_id = get_user_meta($user->ID, '_flosc_stripe_customer', true);
        
        if ($customer_id) {
            // Verify customer still exists
            $customer = $this->api_request('GET', '/customers/' . $customer_id);
            if (!is_wp_error($customer) && empty($customer['deleted'])) {
                return $customer_id;
            }
        }
        
        // Create new customer
        $response = $this->api_request('POST', '/customers', [
            'email' => $user->user_email,
            'name' => $user->display_name,
            'metadata' => [
                'user_id' => $user->ID,
                'wp_site' => home_url(),
            ],
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        update_user_meta($user->ID, '_flosc_stripe_customer', $response['id']);
        
        return $response['id'];
    }
    
    /**
     * Cancel subscription
     */
    public function cancel_subscription($subscription_id) {
        $response = $this->api_request('DELETE', '/subscriptions/' . $subscription_id);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return [
            'success' => true,
            'subscription_id' => $subscription_id,
            'status' => $response['status'],
        ];
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook($payload, $headers = []) {
        $webhook_secret = $this->get_setting('webhook_secret', '');

        // SECURITY: Require webhook secret in production
        if (empty($webhook_secret)) {
            return new WP_Error('webhook_not_configured', 'Stripe webhook secret required', ['status' => 400]);
        }

        // SECURITY: Verify signature (mandatory)
        if (!isset($headers['stripe-signature'])) {
            return new WP_Error('missing_signature', 'Missing Stripe signature header', ['status' => 400]);
        }

        $sig = $headers['stripe-signature'];

        // Parse signature
        $timestamp = null;
        $signature = null;
        foreach (explode(',', $sig) as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') $timestamp = $value;
            if ($key === 'v1') $signature = $value;
        }

        // SECURITY: Validate timestamp (prevent replay attacks)
        $tolerance = 300; // 5 minutes
        if (empty($timestamp) || abs(time() - intval($timestamp)) > $tolerance) {
            return new WP_Error('expired_webhook', 'Webhook timestamp too old or invalid', ['status' => 400]);
        }

        // Verify signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);

        if (!hash_equals($expected, $signature)) {
            return new WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 401]);
        }

        $event = json_decode($payload, true);

        // SECURITY: Idempotency - check if already processed
        $event_id = $event['id'] ?? '';
        if ($event_id) {
            $processed_key = 'flosc_stripe_event_' . $event_id;
            if (get_transient($processed_key)) {
                return ['success' => true, 'message' => 'Event already processed'];
            }
            // Mark as processed (store for 24 hours)
            set_transient($processed_key, true, DAY_IN_SECONDS);
        }
        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];
        
        switch ($type) {
            case 'payment_intent.succeeded':
                return $this->handle_payment_succeeded($object);
                
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                return $this->handle_subscription_updated($object);
                
            case 'customer.subscription.deleted':
                return $this->handle_subscription_deleted($object);
                
            case 'invoice.payment_failed':
                return $this->handle_payment_failed($object);
        }
        
        return ['received' => true];
    }
    
    private function handle_payment_succeeded($payment_intent) {
        $user_id = $payment_intent['metadata']['user_id'] ?? null;
        
        if ($user_id) {
            do_action('flosc_stripe_payment_succeeded', intval($user_id), $payment_intent);
        }
        
        return ['success' => true];
    }
    
    private function handle_subscription_updated($subscription) {
        $user_id = $subscription['metadata']['user_id'] ?? null;
        
        if ($user_id) {
            update_user_meta($user_id, '_flosc_subscription_status', $subscription['status']);
            do_action('flosc_stripe_subscription_updated', intval($user_id), $subscription);
        }
        
        return ['success' => true];
    }
    
    private function handle_subscription_deleted($subscription) {
        $user_id = $subscription['metadata']['user_id'] ?? null;
        
        if ($user_id) {
            delete_user_meta($user_id, '_flosc_stripe_subscription');
            update_user_meta($user_id, '_flosc_subscription_status', 'canceled');
            do_action('flosc_stripe_subscription_canceled', intval($user_id), $subscription);
        }
        
        return ['success' => true];
    }
    
    private function handle_payment_failed($invoice) {
        $customer_id = $invoice['customer'] ?? '';
        
        // Find user by customer ID
        $users = get_users([
            'meta_key' => '_flosc_stripe_customer',
            'meta_value' => $customer_id,
            'number' => 1,
        ]);
        
        if (!empty($users)) {
            do_action('flosc_stripe_payment_failed', $users[0]->ID, $invoice);
        }
        
        return ['success' => true];
    }
    
    /**
     * Make Stripe API request
     */
    private function api_request($method, $endpoint, $data = []) {
        $url = 'https://api.stripe.com/v1' . $endpoint;
        
        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_secret_key(),
            ],
            'timeout' => 30,
        ];
        
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        } elseif (!empty($data)) {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = $data;
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error(
                'stripe_error',
                $body['error']['message'] ?? 'Stripe API error',
                $body['error']
            );
        }
        
        return $body;
    }
}
