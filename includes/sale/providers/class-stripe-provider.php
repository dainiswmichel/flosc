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
     * v1.6.3: Fixed to read from per-flow settings via flosc()->get_setting()
     * Key mapping: provider asks for 'test_publishable_key' → admin stores 'stripe_test_pk'
     */
    private function get_mode() {
        return $this->get_flow_setting('mode', 'test');
    }
    
    private function get_publishable_key() {
        $mode = $this->get_mode();
        return $this->get_flow_setting($mode . '_pk', '');
    }
    
    private function get_secret_key() {
        $mode = $this->get_mode();
        return $this->get_flow_setting($mode . '_sk', '');
    }
    
    /**
     * v1.6.3: Read Stripe setting from per-flow settings, falling back to global
     * Admin saves: stripe_test_pk, stripe_test_sk, stripe_live_pk, stripe_live_sk, stripe_mode, stripe_webhook_secret
     * All under the per-flow array option (flosc_flow_{name})
     */
    private function get_flow_setting($key, $default = '') {
        // Try per-flow via flosc()->get_setting() (checks flow array first, then global)
        if (function_exists('flosc')) {
            $value = flosc()->get_setting('stripe_' . $key, '');
            if (!empty($value)) {
                return $value;
            }
        }
        // Fallback to legacy global option (flosc_stripe_*)
        return get_option('flosc_stripe_' . $key, $default);
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
            return new WP_Error('no_price', __('No Stripe Price ID configured for this offer', 'flosc'));
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('User not found', 'flosc'));
        }

        $offer_id = sanitize_text_field((string) ($offer['id'] ?? ($payment_data['offer_id'] ?? '')));
        
        // Handle based on offer type
        if (($offer['type'] ?? '') === 'subscription') {
            return $this->create_subscription($user, $price_id, $payment_data, $offer_id);
        }

        return $this->create_payment($user, $price_id, $payment_data, $offer_id);
    }
    
    /**
     * Create one-time payment
     *
     * @param WP_User $user
     * @param string  $price_id
     * @param array   $payment_data
     * @param string  $offer_id Bound offer for metadata (PAY-02).
     */
    private function create_payment($user, $price_id, $payment_data, $offer_id = '') {
        // If we have a payment_method_id, create PaymentIntent and confirm
        if (!empty($payment_data['payment_method_id'])) {
            return $this->confirm_payment($user, $price_id, $payment_data['payment_method_id'], $offer_id);
        }
        
        // Client-side confirmation path: returns client_secret only (not settled).
        return $this->create_payment_intent($user, $price_id, 'usd', $offer_id);
    }
    
    /**
     * Create PaymentIntent for client-side confirmation
     * v1.4.1: Added offer_id parameter to track which offer is being purchased
     */
    public function create_payment_intent($user, $price_id_or_amount, $currency = 'usd', $offer_id = '') {
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
        
        $metadata = [
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
        ];
        
        // v1.4.1: Include offer_id for webhook to grant correct access
        if ($offer_id) {
            $metadata['offer_id'] = $offer_id;
        }
        
        $response = $this->api_request('POST', '/payment_intents', [
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => $metadata,
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
     *
     * @param WP_User $user
     * @param string  $price_id
     * @param string  $payment_method_id
     * @param string  $offer_id
     */
    private function confirm_payment($user, $price_id, $payment_method_id, $offer_id = '') {
        // Get price details
        $price = $this->api_request('GET', '/prices/' . $price_id);
        if (is_wp_error($price)) {
            return $price;
        }

        $metadata = [
            'user_id'    => $user->ID,
            'user_email' => $user->user_email,
        ];
        if ($offer_id !== '') {
            $metadata['offer_id'] = $offer_id;
        }
        
        // Create and confirm PaymentIntent
        $response = $this->api_request('POST', '/payment_intents', [
            'amount' => $price['unit_amount'],
            'currency' => $price['currency'],
            'payment_method' => $payment_method_id,
            'confirm' => true,
            'metadata' => $metadata,
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
                'status' => 'succeeded',
            ];
        }
        
        if ($response['status'] === 'requires_action') {
            // Incomplete — sale manager must not grant on this payload.
            return [
                'success' => false,
                'requires_action' => true,
                'client_secret' => $response['client_secret'],
                'payment_intent_id' => $response['id'],
                'status' => 'requires_action',
            ];
        }
        
        return new WP_Error('payment_failed', __('Payment failed: ', 'flosc') . ($response['last_payment_error']['message'] ?? __('Unknown error', 'flosc')));
    }
    
    /**
     * Create subscription
     *
     * @param WP_User $user
     * @param string  $price_id
     * @param array   $payment_data
     * @param string  $offer_id
     */
    private function create_subscription($user, $price_id, $payment_data, $offer_id = '') {
        // Get or create Stripe customer
        $customer_id = $this->get_or_create_customer($user);
        
        if (is_wp_error($customer_id)) {
            return $customer_id;
        }

        $metadata = [
            'user_id' => $user->ID,
        ];
        if ($offer_id !== '') {
            $metadata['offer_id'] = $offer_id;
        }
        
        $sub_data = [
            'customer' => $customer_id,
            'items' => [['price' => $price_id]],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => $metadata,
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
                'success' => false,
                'requires_action' => true,
                'client_secret' => $payment_intent['client_secret'],
                'subscription_id' => $response['id'],
                'status' => 'requires_action',
            ];
        }
        
        if ($response['status'] === 'active') {
            // Save subscription ID to user
            update_user_meta($user->ID, '_flosc_stripe_subscription', $response['id']);

            return [
                'success' => true,
                'transaction_id' => $response['id'],
                'subscription_id' => $response['id'],
                'status' => 'active',
            ];
        }

        // PAY-01C: trialing is not settled payment. Fulfill only after active (or explicit later path).
        if ($response['status'] === 'trialing') {
            update_user_meta($user->ID, '_flosc_stripe_subscription', $response['id']);
            update_user_meta($user->ID, '_flosc_subscription_status', 'trialing');
            return [
                'success' => false,
                'pending' => true,
                'subscription_id' => $response['id'],
                'status' => 'trialing',
                'message' => __('Subscription is trialing; access is granted only after an active paid status.', 'flosc'),
            ];
        }

        return new WP_Error('subscription_failed', __('Failed to create subscription', 'flosc'));
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
        $webhook_secret = $this->get_flow_setting('webhook_secret', '');

        // SECURITY: Require webhook secret in production
        if (empty($webhook_secret)) {
            return new WP_Error('webhook_not_configured', __('Stripe webhook secret required', 'flosc'), ['status' => 400]);
        }

        // SECURITY: Verify signature (mandatory)
        if (!isset($headers['stripe-signature'])) {
            return new WP_Error('missing_signature', __('Missing Stripe signature header', 'flosc'), ['status' => 400]);
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
            return new WP_Error('expired_webhook', __('Webhook timestamp too old or invalid', 'flosc'), ['status' => 400]);
        }

        // Verify signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);

        if (!hash_equals($expected, $signature)) {
            return new WP_Error('invalid_signature', __('Invalid webhook signature', 'flosc'), ['status' => 401]);
        }

        // Pass 8: json_decode does not sanitize — field-sanitize every value we use.
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return new WP_Error('invalid_payload', __('Invalid Stripe webhook JSON', 'flosc'), ['status' => 400]);
        }

        // SECURITY: Idempotency - check if already processed
        $event_id = sanitize_text_field((string) ($event['id'] ?? ''));
        if ($event_id !== '') {
            $processed_key = 'flosc_stripe_event_' . $event_id;
            if (get_transient($processed_key)) {
                return ['success' => true, 'message' => 'Event already processed'];
            }
            // Mark as processed (store for 24 hours)
            set_transient($processed_key, true, DAY_IN_SECONDS);
        }
        $type = sanitize_text_field((string) ($event['type'] ?? ''));
        $object = (isset($event['data']['object']) && is_array($event['data']['object']))
            ? $event['data']['object']
            : [];

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
    
    /**
     * v1.4.1: Handle successful payment - grant access based on offer
     * PAY-01/PAY-02: only metadata-bound offer; claim txn before grant (idempotent with complete_purchase).
     */
    private function handle_payment_succeeded($payment_intent) {
        $meta = (isset($payment_intent['metadata']) && is_array($payment_intent['metadata']))
            ? $payment_intent['metadata']
            : [];
        $user_id = absint($meta['user_id'] ?? 0);
        $offer_id = sanitize_text_field((string) ($meta['offer_id'] ?? ''));
        $transaction_id = sanitize_text_field((string) ($payment_intent['id'] ?? ''));
        $amount = absint($payment_intent['amount'] ?? 0);
        $currency = sanitize_text_field((string) ($payment_intent['currency'] ?? ''));

        // Unbound payments must not grant — offer_id was set at PaymentIntent creation.
        if ($user_id > 0 && $offer_id !== '' && $transaction_id !== '' && function_exists('flosc_sale')) {
            $sale_manager = flosc_sale();
            $offer = $sale_manager->offers()->get_offer($offer_id);

            if ($offer) {
                $transaction = [
                    'transaction_id' => $transaction_id,
                    'provider'       => 'stripe',
                    'amount'         => $amount,
                    'currency'       => $currency,
                    'success'        => true,
                    'status'         => 'succeeded',
                ];

                $fulfill = $sale_manager->fulfill_settled_purchase($user_id, $offer, 'stripe', $transaction);
                if (!is_wp_error($fulfill)) {
                    set_transient('flosc_just_purchased_' . $user_id, true, 300);
                    update_user_meta($user_id, '_flosc_purchased_offer_id', $offer_id);
                    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                        flosc_log("FLOSC: Access granted to user {$user_id} for offer {$offer_id} via Stripe webhook");
                    }
                } elseif (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    flosc_log('FLOSC: Stripe webhook fulfill rejected: ' . $fulfill->get_error_message());
                }
            }
        }

        if ($user_id > 0) {
            do_action('flosc_stripe_payment_succeeded', $user_id, $payment_intent);
        }

        return ['success' => true];
    }
    
    private function handle_subscription_updated($subscription) {
        $meta = (isset($subscription['metadata']) && is_array($subscription['metadata']))
            ? $subscription['metadata']
            : [];
        $user_id = absint($meta['user_id'] ?? 0);
        $status = sanitize_key((string) ($subscription['status'] ?? ''));

        if ($user_id > 0) {
            update_user_meta($user_id, '_flosc_subscription_status', $status);
            do_action('flosc_stripe_subscription_updated', $user_id, $subscription);
        }

        return ['success' => true];
    }
    
    private function handle_subscription_deleted($subscription) {
        $meta = (isset($subscription['metadata']) && is_array($subscription['metadata']))
            ? $subscription['metadata']
            : [];
        $user_id = absint($meta['user_id'] ?? 0);

        if ($user_id > 0) {
            delete_user_meta($user_id, '_flosc_stripe_subscription');
            update_user_meta($user_id, '_flosc_subscription_status', 'canceled');
            do_action('flosc_stripe_subscription_canceled', $user_id, $subscription);
        }

        return ['success' => true];
    }
    
    private function handle_payment_failed($invoice) {
        $customer_id = sanitize_text_field((string) ($invoice['customer'] ?? ''));

        // Find user by customer ID (cached usermeta lookup — no SlowDBQuery meta_query).
        $ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_stripe_customer', $customer_id, '=', 1 )
            : array();

        if ( ! empty( $ids ) ) {
            do_action( 'flosc_stripe_payment_failed', (int) $ids[0], $invoice );
        }
        
        return ['success' => true];
    }
    
    /**
     * v1.4.1: Retrieve a PaymentIntent to verify payment status
     */
    public function retrieve_payment_intent($payment_intent_id) {
        return $this->api_request('GET', '/payment_intents/' . $payment_intent_id);
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
