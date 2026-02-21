<?php
/**
 * FLOSC PayPal Payment Provider
 * v1.6.9: PayPal Orders API v2 integration (sandbox + live)
 * 
 * Flow: Create Order → Approve (PayPal JS SDK) → Capture → Grant Access
 */

if (!defined('ABSPATH')) exit;

class FLOSC_PayPal_Provider extends FLOSC_Payment_Provider {
    
    public function get_id() {
        return 'paypal';
    }
    
    public function get_name() {
        return 'PayPal';
    }
    
    public function get_description() {
        return 'Accept payments via PayPal checkout.';
    }
    
    public function get_icon() {
        return '🅿️';
    }
    
    public function is_configured() {
        return !empty($this->get_client_id()) && !empty($this->get_secret());
    }

    /**
     * v1.7.3: Override base is_enabled() to read from per-flow settings.
     * Base class checks get_option('flosc_provider_paypal_enabled') which the
     * admin UI never writes to — admin saves 'paypal_enabled' to the flow option.
     */
    public function is_enabled() {
        if (function_exists('flosc')) {
            $value = flosc()->get_setting('paypal_enabled', '');
            if ($value !== '') {
                return !empty($value);
            }
        }
        return parent::is_enabled();
    }

    /**
     * v1.7.3: Check if client ID is set (enough for SDK loading + button rendering)
     * Full is_configured() also requires the secret (for server-side API calls)
     */
    public function has_client_id() {
        return !empty($this->get_client_id());
    }
    
    public function get_settings_fields() {
        return [
            'mode' => [
                'type' => 'select',
                'label' => 'Mode',
                'options' => [
                    'sandbox' => 'Sandbox (Testing)',
                    'live' => 'Live',
                ],
                'default' => 'sandbox',
            ],
            'client_id' => [
                'type' => 'text',
                'label' => 'Client ID',
            ],
            'secret' => [
                'type' => 'password',
                'label' => 'Secret Key',
            ],
        ];
    }
    
    /**
     * Read PayPal settings from per-flow config, fallback to global
     */
    private function get_flow_setting($key, $default = '') {
        if (function_exists('flosc')) {
            $value = flosc()->get_setting('paypal_' . $key, '');
            if (!empty($value)) {
                return $value;
            }
        }
        return get_option('flosc_paypal_' . $key, $default);
    }
    
    private function get_mode() {
        return $this->get_flow_setting('mode', 'sandbox');
    }
    
    private function get_client_id() {
        return $this->get_flow_setting('client_id', '');
    }
    
    private function get_secret() {
        return $this->get_flow_setting('secret', '');
    }
    
    /**
     * API base URL based on mode
     */
    private function get_api_base() {
        return $this->get_mode() === 'live' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';
    }
    
    /**
     * Get OAuth2 access token from PayPal
     */
    private function get_access_token() {
        $client_id = $this->get_client_id();
        $secret = $this->get_secret();
        
        // Cache token in transient (valid ~9 hours, we cache for 1 hour)
        $cache_key = 'flosc_paypal_token_' . md5($client_id);
        $cached = get_transient($cache_key);
        if ($cached) return $cached;
        
        $response = wp_remote_post($this->get_api_base() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['access_token'])) {
            return new WP_Error('paypal_auth_failed', 'Could not get PayPal access token: ' . ($body['error_description'] ?? 'Unknown error'));
        }
        
        set_transient($cache_key, $body['access_token'], 3600);
        return $body['access_token'];
    }
    
    /**
     * Create a PayPal order (called from REST endpoint)
     */
    public function create_order($user, $amount_dollars, $currency, $offer_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;
        
        // v1.7.3: Amount is in dollars (e.g. 39.00), not cents
        $amount = number_format(floatval($amount_dollars), 2, '.', '');
        $currency = strtoupper($currency);
        
        $response = wp_remote_post($this->get_api_base() . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => wp_json_encode([
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $offer_id,
                    'description' => 'FLOSC Purchase - ' . $offer_id,
                    'custom_id' => json_encode([
                        'user_id' => $user->ID,
                        'offer_id' => $offer_id,
                    ]),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                ]],
                'application_context' => [
                    'brand_name' => 'FLOSC',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                ],
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? 'PayPal order creation failed';
            return new WP_Error('paypal_order_failed', $error_msg);
        }
        
        return [
            'order_id' => $body['id'],
            'status' => $body['status'],
        ];
    }
    
    /**
     * Capture a PayPal order after buyer approves
     */
    public function capture_order($order_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;
        
        $response = wp_remote_post($this->get_api_base() . '/v2/checkout/orders/' . $order_id . '/capture', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => '{}',
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? 'PayPal capture failed';
            return new WP_Error('paypal_capture_failed', $error_msg);
        }
        
        // Extract transaction details
        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        // v1.7.5: custom_id lives inside the capture object, not at purchase_unit level
        $custom_id = json_decode($capture['custom_id'] ?? '{}', true);
        
        return [
            'order_id' => $body['id'],
            'status' => $body['status'],
            'transaction_id' => $capture['id'] ?? '', // v1.7.3: renamed from capture_id for consistency
            'amount' => $capture['amount']['value'] ?? '0.00',
            'currency' => $capture['amount']['currency_code'] ?? 'USD',
            'user_id' => $custom_id['user_id'] ?? null,
            'offer_id' => $custom_id['offer_id'] ?? null,
        ];
    }
    
    /**
     * Process payment (generic interface — not used directly for PayPal)
     * PayPal uses create_order + capture_order instead
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        return new WP_Error('use_order_flow', 'PayPal uses the order creation flow. Use create_order() and capture_order() instead.');
    }
    
    /**
     * Client-side config passed to JS
     */
    public function get_client_config() {
        return [
            'clientId' => $this->get_client_id(),
            'mode' => $this->get_mode(),
        ];
    }
    
    /**
     * Handle PayPal webhooks (optional — capture already confirms payment)
     */
    public function handle_webhook($payload, $headers = []) {
        // PayPal webhook verification could go here
        // For now, the capture_order flow handles everything synchronously
        return ['received' => true];
    }
}
