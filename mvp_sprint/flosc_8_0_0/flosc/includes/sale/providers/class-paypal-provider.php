<?php
/**
 * FLOSC PayPal Payment Provider
 * v5.0.7: Definitive PayPal Orders API v2 integration (sandbox + live)
 *
 * v5.0.7 fixes:
 * - Stale OAuth token cache: clear transient on auth failure, retry once
 * - Better credential resolution: explicit fallback chain with logging
 * - Currency consistency: return resolved currency for SDK/order alignment
 * - Debug logging: every API call logged when FLOSC_DEBUG is on
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
        $has_id = !empty($this->get_client_id());
        $has_secret = !empty($this->get_secret());
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] is_configured() — client_id: ' . ($has_id ? 'YES' : 'NO') . ', secret: ' . ($has_secret ? 'YES' : 'NO') . ', mode: ' . $this->get_mode());
        }
        return $has_id && $has_secret;
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
     * Read PayPal settings.
     * 1. Per-flow setting → 2. Global wp_option → 3. Default
     */
    private function get_flow_setting($key, $default = '') {
        // 1. Per-flow setting
        if (function_exists('flosc')) {
            $value = flosc()->get_setting('paypal_' . $key, '');
            if (!empty($value)) {
                return $value;
            }
        }
        // 2. Global option
        $global = get_option('flosc_paypal_' . $key, '');
        if (!empty($global)) {
            return $global;
        }
        return $default;
    }

    /**
     * Default mode for new installs. Credentials must be set via admin Payments tab.
     */
    public static function get_default_mode() {
        return 'sandbox';
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
     * v5.0.7: Get the currency for PayPal orders.
     * Centralised so SDK loading and order creation use the same value.
     */
    public function get_currency() {
        // Offer-level currency is set by the caller; this is the global fallback.
        if (function_exists('flosc')) {
            $cur = flosc()->get_setting('product_currency', '');
            if (!empty($cur)) return strtoupper($cur);
        }
        return 'USD';
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
     * v5.0.7: Get OAuth2 access token from PayPal.
     *
     * Fixes vs prior versions:
     * - Clears cached token on ANY auth failure (prevents stale token loops)
     * - Retries once after clearing cache (handles token-expired edge case)
     * - Logs full error details when FLOSC_DEBUG is on
     */
    private function get_access_token($force_refresh = false) {
        $client_id = $this->get_client_id();
        $secret = $this->get_secret();

        $cache_key = 'flosc_paypal_token_' . md5($client_id . $secret);

        // Return cached token unless force-refreshing
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if ($cached) return $cached;
        } else {
            delete_transient($cache_key);
        }

        $api_base = $this->get_api_base();

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] Requesting OAuth token from ' . $api_base . '/v1/oauth2/token (client_id starts: ' . substr($client_id, 0, 12) . '...)');
        }

        $response = wp_remote_post($api_base . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] OAuth request WP_Error: ' . $response->get_error_message());
            }
            delete_transient($cache_key);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] OAuth response: HTTP ' . $status_code . ', body keys: ' . implode(',', array_keys($body ?: [])));
        }

        if (empty($body['access_token'])) {
            // Clear any stale cached token
            delete_transient($cache_key);
            $err_desc = $body['error_description'] ?? $body['error'] ?? 'HTTP ' . $status_code;
            return new WP_Error('paypal_auth_failed', 'PayPal OAuth failed: ' . $err_desc);
        }

        // Cache for 30 minutes (not 1 hour — safer margin against expiry races)
        set_transient($cache_key, $body['access_token'], 1800);
        return $body['access_token'];
    }

    /**
     * Create a PayPal order (called from REST endpoint)
     * v5.0.7: Retries once on 401 (stale token), logs all steps
     */
    public function create_order($user, $amount_dollars, $currency, $offer_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $amount = number_format(floatval($amount_dollars), 2, '.', '');
        $currency = strtoupper($currency);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] create_order: user=' . ($user->ID ?? 0) . ', amount=' . $amount . ' ' . $currency . ', offer=' . $offer_id);
        }

        $order_body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $offer_id,
                'description' => 'FLOSC Purchase - ' . $offer_id,
                'custom_id' => json_encode([
                    'user_id' => $user->ID ?? 0,
                    'offer_id' => $offer_id,
                ]),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $amount,
                ],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => 'FLOSC',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                ],
            ],
        ];

        $response = wp_remote_post($this->get_api_base() . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => wp_json_encode($order_body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] create_order WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] create_order response: HTTP ' . $status_code . ', order_id=' . ($body['id'] ?? 'NONE') . ', status=' . ($body['status'] ?? 'NONE'));
        }

        // v5.0.7: If 401 Unauthorized, the cached token is stale — refresh and retry once
        if ($status_code === 401) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] create_order got 401 — clearing cached token and retrying');
            }
            $token = $this->get_access_token(true);
            if (is_wp_error($token)) return $token;

            $response = wp_remote_post($this->get_api_base() . '/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation',
                ],
                'body' => wp_json_encode($order_body),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) return $response;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
        }

        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? 'PayPal order creation failed (HTTP ' . $status_code . ')';
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] create_order FAILED: ' . $error_msg . ' | full body: ' . wp_json_encode($body));
            }
            return new WP_Error('paypal_order_failed', $error_msg);
        }

        return [
            'order_id' => $body['id'],
            'status' => $body['status'],
        ];
    }

    /**
     * Capture a PayPal order after buyer approves
     * v5.0.7: Retries once on 401, logs all steps
     */
    public function capture_order($order_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] capture_order: order_id=' . $order_id);
        }

        $capture_url = $this->get_api_base() . '/v2/checkout/orders/' . $order_id . '/capture';

        $response = wp_remote_post($capture_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => '{}',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] capture_order WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] capture_order response: HTTP ' . $status_code . ', status=' . ($body['status'] ?? 'NONE'));
        }

        // v5.0.7: Retry once on 401 (stale token)
        if ($status_code === 401) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] capture_order got 401 — refreshing token and retrying');
            }
            $token = $this->get_access_token(true);
            if (is_wp_error($token)) return $token;

            $response = wp_remote_post($capture_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Prefer' => 'return=representation',
                ],
                'body' => '{}',
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) return $response;
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);
        }

        // Forward PayPal's structured error details (especially INSTRUMENT_DECLINED)
        if ($status_code < 200 || $status_code >= 300) {
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? 'PayPal capture failed (HTTP ' . $status_code . ')';
            $error_issue = $body['details'][0]['issue'] ?? '';

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                error_log('[FLOSC-PAYPAL] capture_order FAILED: ' . $error_msg . ' | issue: ' . $error_issue . ' | full body: ' . wp_json_encode($body));
            }

            return [
                'success' => false,
                'message' => $error_msg,
                'details' => $body['details'] ?? [],
                'paypal_debug_id' => $body['debug_id'] ?? '',
                'issue' => $error_issue,
            ];
        }

        // Extract transaction details
        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        $custom_id = json_decode($capture['custom_id'] ?? '{}', true);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            error_log('[FLOSC-PAYPAL] capture_order SUCCESS: transaction_id=' . ($capture['id'] ?? 'NONE') . ', amount=' . ($capture['amount']['value'] ?? '?'));
        }

        return [
            'order_id' => $body['id'],
            'status' => $body['status'],
            'transaction_id' => $capture['id'] ?? '',
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

    // ================================================================
    // PayPal Subscriptions API — Products, Plans, Subscriptions
    // ================================================================

    /**
     * Create a catalog product in PayPal (one-time setup)
     */
    public function create_product($name, $description) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $response = wp_remote_post($this->get_api_base() . '/v1/catalogs/products', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'name'        => $name,
                'description' => $description,
                'type'        => 'DIGITAL',
                'category'    => 'EDUCATIONAL_AND_TEXTBOOKS',
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;
        $body   = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('paypal_product_failed', $body['message'] ?? 'Failed to create product (HTTP ' . $status . ')');
        }
        return $body;
    }

    /**
     * Create a billing plan for a product
     */
    public function create_plan($product_id, $name, $amount, $interval_unit, $interval_count = 1) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $currency = $this->get_currency();

        $response = wp_remote_post($this->get_api_base() . '/v1/billing/plans', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'product_id'     => $product_id,
                'name'           => $name,
                'status'         => 'ACTIVE',
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit'  => strtoupper($interval_unit),
                            'interval_count' => $interval_count,
                        ],
                        'tenure_type'    => 'REGULAR',
                        'sequence'       => 1,
                        'total_cycles'   => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value'         => number_format((float) $amount, 2, '.', ''),
                                'currency_code' => $currency,
                            ],
                        ],
                    ],
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding'      => true,
                    'payment_failure_threshold'   => 3,
                ],
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;
        $body   = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status < 200 || $status >= 300) {
            return new WP_Error('paypal_plan_failed', $body['message'] ?? 'Failed to create plan (HTTP ' . $status . ')');
        }
        return $body;
    }

    /**
     * Get subscription details from PayPal
     */
    public function get_subscription($subscription_id) {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $response = wp_remote_get($this->get_api_base() . '/v1/billing/subscriptions/' . urlencode($subscription_id), [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Ensure PayPal product + plans exist. Creates them on first call.
     * Returns [ 'product_id' => ..., 'monthly_plan_id' => ..., 'yearly_plan_id' => ... ]
     */
    public function ensure_plans_exist() {
        $plans = get_option('flosc_paypal_plans', []);

        if (!empty($plans['monthly_plan_id']) && !empty($plans['yearly_plan_id'])) {
            return $plans;
        }

        // v8.0.0: Seed known sandbox plans (created via PayPal API 2026-03).
        // These plans already exist on PayPal's side but the WP option was never populated.
        if ($this->get_mode() === 'sandbox' && (empty($plans['monthly_plan_id']) || empty($plans['yearly_plan_id']))) {
            $plans = [
                'product_id'      => 'PROD-9B5722127Y095851P',
                'monthly_plan_id' => 'P-5K352631T93015240NGW5YWQ',
                'yearly_plan_id'  => 'P-4P651307R15744312NGW5YWQ',
            ];
            update_option('flosc_paypal_plans', $plans);
            return $plans;
        }

        // Create product if needed
        $product_id = $plans['product_id'] ?? '';
        if (empty($product_id)) {
            $product = $this->create_product(
                'LeSAEp Pronunciation Course',
                'Learn Excellent Standard American English Pronunciation'
            );
            if (is_wp_error($product)) return $product;
            $product_id = $product['id'];
        }

        // Create monthly plan ($10/month)
        if (empty($plans['monthly_plan_id'])) {
            $monthly = $this->create_plan($product_id, 'LeSAEp Monthly — $10/month', 10.00, 'MONTH');
            if (is_wp_error($monthly)) return $monthly;
            $plans['monthly_plan_id'] = $monthly['id'];
        }

        // Create yearly plan ($100/year)
        if (empty($plans['yearly_plan_id'])) {
            $yearly = $this->create_plan($product_id, 'LeSAEp Yearly — $100/year', 100.00, 'YEAR');
            if (is_wp_error($yearly)) return $yearly;
            $plans['yearly_plan_id'] = $yearly['id'];
        }

        $plans['product_id'] = $product_id;
        update_option('flosc_paypal_plans', $plans);
        return $plans;
    }

    /**
     * Client-side config passed to JS
     */
    public function get_client_config() {
        $config = [
            'clientId' => $this->get_client_id(),
            'mode'     => $this->get_mode(),
            'currency' => $this->get_currency(),
        ];
        // Include plan IDs if they exist (for subscription buttons)
        $plans = get_option('flosc_paypal_plans', []);
        if (!empty($plans['monthly_plan_id'])) {
            $config['monthlyPlanId'] = $plans['monthly_plan_id'];
        }
        if (!empty($plans['yearly_plan_id'])) {
            $config['yearlyPlanId'] = $plans['yearly_plan_id'];
        }
        return $config;
    }

    /**
     * Handle PayPal webhooks (optional — capture already confirms payment)
     */
    public function handle_webhook($payload, $headers = []) {
        return ['received' => true];
    }
}
