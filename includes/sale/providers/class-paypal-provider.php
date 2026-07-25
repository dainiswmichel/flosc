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
            flosc_log('[FLOSC-PAYPAL] is_configured() — client_id: ' . ($has_id ? 'YES' : 'NO') . ', secret: ' . ($has_secret ? 'YES' : 'NO') . ', mode: ' . $this->get_mode());
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
            flosc_log('[FLOSC-PAYPAL] Requesting OAuth token from ' . $api_base . '/v1/oauth2/token (client_id starts: ' . substr($client_id, 0, 12) . '...)');
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
                flosc_log('[FLOSC-PAYPAL] OAuth request WP_Error: ' . $response->get_error_message());
            }
            delete_transient($cache_key);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] OAuth response: HTTP ' . $status_code . ', body keys: ' . implode(',', array_keys($body ?: [])));
        }

        if (empty($body['access_token'])) {
            // Clear any stale cached token
            delete_transient($cache_key);
            $err_desc = $body['error_description'] ?? $body['error'] ?? __('HTTP', 'flosc') . ' ' . $status_code;
            return new WP_Error('paypal_auth_failed', __('PayPal OAuth failed: ', 'flosc') . $err_desc);
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
            flosc_log('[FLOSC-PAYPAL] create_order: user=' . ($user->ID ?? 0) . ', amount=' . $amount . ' ' . $currency . ', offer=' . $offer_id);
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
                flosc_log('[FLOSC-PAYPAL] create_order WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] create_order response: HTTP ' . $status_code . ', order_id=' . ($body['id'] ?? 'NONE') . ', status=' . ($body['status'] ?? 'NONE'));
        }

        // v5.0.7: If 401 Unauthorized, the cached token is stale — refresh and retry once
        if ($status_code === 401) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] create_order got 401 — clearing cached token and retrying');
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
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? __('PayPal order creation failed (HTTP ', 'flosc') . $status_code . ')';
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] create_order FAILED: ' . $error_msg . ' | full body: ' . wp_json_encode($body));
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
            flosc_log('[FLOSC-PAYPAL] capture_order: order_id=' . $order_id);
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
                flosc_log('[FLOSC-PAYPAL] capture_order WP_Error: ' . $response->get_error_message());
            }
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] capture_order response: HTTP ' . $status_code . ', status=' . ($body['status'] ?? 'NONE'));
        }

        // v5.0.7: Retry once on 401 (stale token)
        if ($status_code === 401) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order got 401 — refreshing token and retrying');
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
            $error_msg = $body['details'][0]['description'] ?? $body['message'] ?? __('PayPal capture failed (HTTP ', 'flosc') . $status_code . ')';
            $error_issue = $body['details'][0]['issue'] ?? '';

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order FAILED: ' . $error_msg . ' | issue: ' . $error_issue . ' | full body: ' . wp_json_encode($body));
            }

            return [
                'success' => false,
                'message' => $error_msg,
                'details' => $body['details'] ?? [],
                'paypal_debug_id' => $body['debug_id'] ?? '',
                'issue' => $error_issue,
            ];
        }

        // Extract transaction details + payer identity (required for visitor checkout).
        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        $custom_id = json_decode($capture['custom_id'] ?? ($body['purchase_units'][0]['custom_id'] ?? '{}'), true);
        if (!is_array($custom_id)) {
            $custom_id = [];
        }

        $payer = is_array($body['payer'] ?? null) ? $body['payer'] : [];
        $payment_source_paypal = is_array($body['payment_source']['paypal'] ?? null) ? $body['payment_source']['paypal'] : [];
        $payer_email = sanitize_email((string) ($payer['email_address'] ?? $payment_source_paypal['email_address'] ?? ''));
        $given = (string) ($payer['name']['given_name'] ?? $payment_source_paypal['name']['given_name'] ?? '');
        $surname = (string) ($payer['name']['surname'] ?? $payment_source_paypal['name']['surname'] ?? '');
        $payer_name = trim($given . ' ' . $surname);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] capture_order SUCCESS: transaction_id=' . ($capture['id'] ?? 'NONE') . ', amount=' . ($capture['amount']['value'] ?? '?') . ', payer=' . ($payer_email ?: 'none'));
        }

        return [
            'order_id' => $body['id'],
            'status' => $body['status'],
            'transaction_id' => $capture['id'] ?? '',
            'amount' => $capture['amount']['value'] ?? '0.00',
            'currency' => $capture['amount']['currency_code'] ?? 'USD',
            'user_id' => $custom_id['user_id'] ?? null,
            'offer_id' => $custom_id['offer_id'] ?? null,
            'payer_email' => $payer_email,
            'payer_name' => $payer_name,
        ];
    }

    /**
     * Process payment (generic interface — not used directly for PayPal)
     * PayPal uses create_order + capture_order instead
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        return new WP_Error('use_order_flow', __('PayPal uses the order creation flow. Use create_order() and capture_order() instead.', 'flosc'));
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
            return new WP_Error('paypal_product_failed', $body['message'] ?? __('Failed to create product (HTTP ', 'flosc') . $status . ')');
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
            return new WP_Error('paypal_plan_failed', $body['message'] ?? __('Failed to create plan (HTTP ', 'flosc') . $status . ')');
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
     * Option key for subscription plan IDs — scoped to mode + client so sandbox
     * plan IDs never get served under live credentials (or vice versa).
     */
    private function get_plans_option_key() {
        $mode = sanitize_key((string) $this->get_mode());
        if ($mode === '') {
            $mode = 'sandbox';
        }
        $client = (string) $this->get_client_id();
        $fp = $client !== '' ? substr(md5($client), 0, 12) : 'none';
        return 'flosc_paypal_plans_' . $mode . '_' . $fp;
    }

    /**
     * Fingerprint of the active credentials (mode + client id).
     */
    private function get_credentials_fingerprint() {
        return sanitize_key((string) $this->get_mode()) . ':' . md5((string) $this->get_client_id());
    }

    /**
     * Stored product/plan IDs for the active PayPal credentials only.
     * Returns [] when nothing valid is stored for this mode/client.
     */
    public function get_stored_plans() {
        $key = $this->get_plans_option_key();
        $plans = get_option($key, []);
        if (!is_array($plans)) {
            $plans = [];
        }

        // Legacy unscoped option — only accept if fingerprint matches current credentials.
        if (empty($plans['monthly_plan_id']) || empty($plans['yearly_plan_id'])) {
            $legacy = get_option('flosc_paypal_plans', []);
            if (is_array($legacy)
                && !empty($legacy['monthly_plan_id'])
                && !empty($legacy['yearly_plan_id'])
                && !empty($legacy['credentials_fingerprint'])
                && hash_equals((string) $legacy['credentials_fingerprint'], $this->get_credentials_fingerprint())
            ) {
                $plans = $legacy;
                update_option($key, $plans, false);
            }
        }

        if (empty($plans['monthly_plan_id']) || empty($plans['yearly_plan_id'])) {
            return [];
        }

        // Reject plans saved under different credentials (even if option key collides).
        if (!empty($plans['credentials_fingerprint'])
            && !hash_equals((string) $plans['credentials_fingerprint'], $this->get_credentials_fingerprint())
        ) {
            return [];
        }

        return $plans;
    }

    /**
     * Ensure PayPal product + plans exist for the *current* mode/credentials.
     * Creates them on first call for that credential set.
     * Returns [ 'product_id' => ..., 'monthly_plan_id' => ..., 'yearly_plan_id' => ... ]
     */
    public function ensure_plans_exist() {
        $plans = $this->get_stored_plans();

        if (!empty($plans['monthly_plan_id']) && !empty($plans['yearly_plan_id'])) {
            return $plans;
        }

        $plans = [];

        // Product catalog names/prices come from the active FLOSC flow — never a brand hardcode.
        $product_name = 'FLOSC Subscription';
        $product_desc = 'Recurring access subscription';
        $monthly_price = 10.00;
        $yearly_price = 100.00;
        $monthly_label = '';
        $yearly_label = '';

        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $identity_name = trim((string) ($flow['identity']['name'] ?? $flow['product']['name'] ?? ''));
                if ($identity_name !== '') {
                    $product_name = $identity_name . ' Subscription';
                    $product_desc = trim((string) ($flow['identity']['tagline'] ?? $flow['product']['tagline'] ?? ''));
                    if ($product_desc === '') {
                        $product_desc = $identity_name . ' membership';
                    }
                }
                // Prefer offer subscription.plans pricing when present on the flow.
                $offers = is_array($flow['offers'] ?? null) ? $flow['offers'] : [];
                if (empty($offers)) {
                    $stem = sanitize_key((string) ($flow['id'] ?? pathinfo((string) ($flow['ivr_file'] ?? ''), PATHINFO_FILENAME)));
                    if ($stem !== '') {
                        $fs = get_option('flosc_flow_' . $stem, []);
                        $offers = is_array($fs['offers'] ?? null) ? $fs['offers'] : [];
                    }
                }
                foreach ($offers as $offer) {
                    if (!is_array($offer)) {
                        continue;
                    }
                    $active = !empty($offer['active']) || (($offer['status'] ?? '') === 'active');
                    if (!$active) {
                        continue;
                    }
                    $sub_plans = is_array($offer['subscription']['plans'] ?? null) ? $offer['subscription']['plans'] : [];
                    if (!empty($sub_plans['monthly']['price'])) {
                        $monthly_price = floatval($sub_plans['monthly']['price']);
                        $monthly_label = (string) ($sub_plans['monthly']['label'] ?? '');
                    }
                    if (!empty($sub_plans['yearly']['price'])) {
                        $yearly_price = floatval($sub_plans['yearly']['price']);
                        $yearly_label = (string) ($sub_plans['yearly']['label'] ?? '');
                    }
                    if ($monthly_price > 0 || $yearly_price > 0) {
                        break;
                    }
                }
            }
        }

        $monthly_price = $monthly_price > 0 ? $monthly_price : 10.00;
        $yearly_price = $yearly_price > 0 ? $yearly_price : 100.00;
        if ($monthly_label === '') {
            $monthly_label = sprintf('%s Monthly — $%s/month', $product_name, number_format($monthly_price, 2, '.', ''));
        }
        if ($yearly_label === '') {
            $yearly_label = sprintf('%s Yearly — $%s/year', $product_name, number_format($yearly_price, 2, '.', ''));
        }

        $product = $this->create_product($product_name, $product_desc);
        if (is_wp_error($product)) {
            return $product;
        }
        $product_id = $product['id'];

        $monthly = $this->create_plan($product_id, $monthly_label, $monthly_price, 'MONTH');
        if (is_wp_error($monthly)) {
            return $monthly;
        }

        $yearly = $this->create_plan($product_id, $yearly_label, $yearly_price, 'YEAR');
        if (is_wp_error($yearly)) {
            return $yearly;
        }

        $plans = [
            'product_id' => $product_id,
            'monthly_plan_id' => $monthly['id'],
            'yearly_plan_id' => $yearly['id'],
            'mode' => $this->get_mode(),
            'credentials_fingerprint' => $this->get_credentials_fingerprint(),
            'created_at' => current_time('mysql'),
        ];

        update_option($this->get_plans_option_key(), $plans, false);
        // Keep legacy key in sync for older readers, but only for matching credentials.
        update_option('flosc_paypal_plans', $plans, false);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] ensure_plans_exist created plans for mode=' . $this->get_mode()
                . ' monthly=' . $plans['monthly_plan_id'] . ' yearly=' . $plans['yearly_plan_id']);
        }

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
        // Plan IDs only for the active credential set (never mix sandbox plans into live).
        $plans = $this->get_stored_plans();
        if (!empty($plans['monthly_plan_id'])) {
            $config['monthlyPlanId'] = $plans['monthly_plan_id'];
        }
        if (!empty($plans['yearly_plan_id'])) {
            $config['yearlyPlanId'] = $plans['yearly_plan_id'];
        }
        return $config;
    }

    /**
     * Handle PayPal webhooks.
     *
     * Subscription renewals: PAYMENT.SALE.COMPLETED with a billing_agreement_id
     * (subscription id) → monthly/yearly token top-up toward the flow cap.
     * First activation is handled by activate-subscription; this covers later cycles.
     */
    public function handle_webhook($payload, $headers = []) {
        $data = is_array($payload) ? $payload : json_decode((string) $payload, true);
        if (!is_array($data)) {
            return ['received' => true, 'processed' => false, 'reason' => 'invalid_payload'];
        }

        $event_type = (string) ($data['event_type'] ?? $data['eventType'] ?? '');
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];

        // Renewal sale against a subscription (billing agreement).
        if ($event_type === 'PAYMENT.SALE.COMPLETED' || $event_type === 'PAYMENT.CAPTURE.COMPLETED') {
            $subscription_id = sanitize_text_field((string) (
                $resource['billing_agreement_id']
                ?? ($resource['supplementary_data']['related_ids']['subscription_id'] ?? '')
            ));
            $sale_id = sanitize_text_field((string) ($resource['id'] ?? ''));

            if ($subscription_id === '') {
                return ['received' => true, 'processed' => false, 'reason' => 'not_subscription_sale'];
            }

            $users = get_users([
                'meta_key' => '_flosc_subscription_id',
                'meta_value' => $subscription_id,
                'number' => 1,
                'fields' => 'ID',
            ]);
            $user_id = !empty($users[0]) ? (int) $users[0] : 0;
            if ($user_id <= 0) {
                return ['received' => true, 'processed' => false, 'reason' => 'unknown_subscription', 'subscription_id' => $subscription_id];
            }

            $plan_type = sanitize_key((string) get_user_meta($user_id, '_flosc_subscription_plan', true));
            if ($plan_type === '') {
                $plan_type = 'monthly';
            }
            $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_subscription_flow_id', true));
            if ($flow_id === '') {
                $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_purchased_flow_id', true));
            }

            $idem = $sale_id !== '' ? ('sale_' . $sale_id) : ('sub_cycle_' . $subscription_id . '_' . gmdate('Y-m'));

            $topup = ['skipped' => true];
            if (function_exists('flosc')) {
                $mode = ($plan_type === 'yearly') ? 'recurring_yearly' : 'recurring';
                if (method_exists(flosc(), 'flosc_apply_product_token_credit_public')) {
                    $topup = flosc()->flosc_apply_product_token_credit_public($user_id, $flow_id, $mode, [
                        'idempotency_key' => $idem,
                        'subscription_id' => $subscription_id,
                        'reason' => 'PayPal subscription renewal (' . $plan_type . ')',
                    ]);
                } elseif (method_exists(flosc(), 'flosc_apply_subscription_token_topup_public')) {
                    $topup = flosc()->flosc_apply_subscription_token_topup_public($user_id, $flow_id, $plan_type, [
                        'idempotency_key' => $idem,
                        'subscription_id' => $subscription_id,
                        'reason' => 'PayPal subscription renewal (' . $plan_type . ')',
                    ]);
                }
            }

            update_user_meta($user_id, '_flosc_subscription_status', 'active');
            update_user_meta($user_id, '_flosc_subscription_last_sale_id', $sale_id);
            update_user_meta($user_id, '_flosc_subscription_last_payment_at', current_time('mysql'));

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] webhook renewal top-up user=' . $user_id . ' sub=' . $subscription_id . ' sale=' . $sale_id . ' result=' . wp_json_encode($topup));
            }

            return [
                'received' => true,
                'processed' => true,
                'event_type' => $event_type,
                'user_id' => $user_id,
                'subscription_id' => $subscription_id,
                'token_topup' => $topup,
            ];
        }

        // Cancel / suspend — mark status only (access expiry is separate).
        if (in_array($event_type, [
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.EXPIRED',
        ], true)) {
            $subscription_id = sanitize_text_field((string) ($resource['id'] ?? ''));
            if ($subscription_id !== '') {
                $users = get_users([
                    'meta_key' => '_flosc_subscription_id',
                    'meta_value' => $subscription_id,
                    'number' => 1,
                    'fields' => 'ID',
                ]);
                if (!empty($users[0])) {
                    $status = strtolower(str_replace('BILLING.SUBSCRIPTION.', '', $event_type));
                    update_user_meta((int) $users[0], '_flosc_subscription_status', $status);
                }
            }
            return ['received' => true, 'processed' => true, 'event_type' => $event_type];
        }

        return ['received' => true, 'processed' => false, 'event_type' => $event_type];
    }
}
