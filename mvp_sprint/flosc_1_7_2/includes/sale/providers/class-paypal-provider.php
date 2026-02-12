<?php
/**
 * FLOSC PayPal Payment Provider
 * v1.7.2: Full PayPal sandbox + live integration
 *
 * Handles PayPal checkout via JS SDK (Smart Payment Buttons).
 * Flow: JS SDK → createOrder REST → PayPal popup → captureOrder REST → grant access
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
        return 'Accept payments via PayPal, Venmo, and PayPal Credit.';
    }

    public function get_icon() {
        return '🅿️';
    }

    public function is_configured() {
        return !empty($this->get_client_id()) && !empty($this->get_secret());
    }

    /**
     * v1.7.2: Override base is_enabled() to read from per-flow settings.
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

    public function get_settings_fields() {
        return [
            'mode' => [
                'type' => 'select',
                'label' => 'Mode',
                'options' => ['sandbox' => 'Sandbox', 'live' => 'Live'],
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

    // --- Settings helpers (same pattern as Stripe provider) ---

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
     * Read PayPal setting from per-flow settings, falling back to global.
     * Admin saves: paypal_client_id, paypal_secret, paypal_mode
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

    /**
     * Client-side config (passed to JavaScript via FLOSC_CONFIG)
     */
    public function get_client_config() {
        return [
            'clientId' => $this->get_client_id(),
            'mode' => $this->get_mode(),
        ];
    }

    /**
     * Get PayPal API base URL based on mode
     */
    private function get_api_base() {
        return $this->get_mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Get an OAuth 2.0 access token from PayPal
     */
    private function get_access_token() {
        $response = wp_remote_post($this->get_api_base() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->get_client_id() . ':' . $this->get_secret()),
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
            $error_msg = $body['error_description'] ?? $body['error'] ?? 'Failed to get PayPal access token';
            return new WP_Error('paypal_auth_error', $error_msg);
        }

        return $body['access_token'];
    }

    /**
     * Make an authenticated PayPal API request
     */
    private function api_request($method, $endpoint, $data = null) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($data !== null && $method !== 'GET') {
            $args['body'] = wp_json_encode($data);
        }

        $response = wp_remote_request($this->get_api_base() . $endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $error_msg = 'PayPal API error';
            if (!empty($body['details'][0]['description'])) {
                $error_msg = $body['details'][0]['description'];
            } elseif (!empty($body['message'])) {
                $error_msg = $body['message'];
            }
            return new WP_Error('paypal_api_error', $error_msg, ['status' => $code, 'body' => $body]);
        }

        return $body;
    }

    /**
     * Create a PayPal order.
     * Called from the REST endpoint when JS SDK's createOrder fires.
     *
     * @param float $amount Price in dollars (e.g. 39.00)
     * @param string $currency Currency code
     * @param array $metadata Additional metadata
     * @return array|WP_Error PayPal order with 'id' key
     */
    public function create_order($amount, $currency = 'USD', $metadata = []) {
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format((float) $amount, 2, '.', ''),
                ],
                'description' => $metadata['description'] ?? 'FLOSC Purchase',
                'custom_id' => $metadata['custom_id'] ?? '',
            ]],
        ];

        $result = $this->api_request('POST', '/v2/checkout/orders', $order_data);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'order_id' => $result['id'],
            'status' => $result['status'],
        ];
    }

    /**
     * Capture a PayPal order after buyer approval.
     * Called from the REST endpoint when JS SDK's onApprove fires.
     *
     * @param string $order_id PayPal order ID
     * @return array|WP_Error Capture result
     */
    public function capture_order($order_id) {
        $result = $this->api_request('POST', '/v2/checkout/orders/' . $order_id . '/capture', new stdClass());

        if (is_wp_error($result)) {
            return $result;
        }

        $status = $result['status'] ?? '';

        if ($status !== 'COMPLETED') {
            return new WP_Error('capture_failed', 'PayPal order capture failed. Status: ' . $status);
        }

        // Extract transaction details from the capture
        $capture = $result['purchase_units'][0]['payments']['captures'][0] ?? [];

        return [
            'success' => true,
            'transaction_id' => $capture['id'] ?? $order_id,
            'amount' => $capture['amount']['value'] ?? '0.00',
            'currency' => $capture['amount']['currency_code'] ?? 'USD',
            'status' => $status,
            'payer_email' => $result['payer']['email_address'] ?? '',
        ];
    }

    /**
     * Process payment (used by sale manager's generic flow).
     * For PayPal, the actual payment happens client-side via JS SDK,
     * so this is only used for the capture step.
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        $order_id = $payment_data['paypal_order_id'] ?? '';

        if (empty($order_id)) {
            return new WP_Error('missing_order_id', 'PayPal order ID required');
        }

        return $this->capture_order($order_id);
    }
}
