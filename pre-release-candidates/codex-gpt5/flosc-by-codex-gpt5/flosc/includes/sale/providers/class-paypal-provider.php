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
            'webhook_id' => [
                'type' => 'text',
                'label' => 'Webhook ID',
                'description' => 'Webhook URL: ' . rest_url('flosc/v1/webhooks/paypal') . ' — ID from PayPal Developer Dashboard → Webhooks.',
            ],
        ];
    }

    /**
     * Read PayPal settings.
     * 1. Per-flow setting → 2. Global wp_option → 3. Default
     */
    private function get_flow_setting($key, $default = '') {
        // 1. Per-flow setting (do not use empty() — "0" and falsey strings are valid)
        if (function_exists('flosc')) {
            $value = flosc()->get_setting('paypal_' . $key, null);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }
        // 2. Global option
        $global = get_option('flosc_paypal_' . $key, null);
        if ($global !== null && $global !== false && $global !== '') {
            return $global;
        }
        return $default;
    }

    /** @var array|null Request-cached credential packs (reset when webhook id is persisted). */
    private $credential_packs_cache = null;

    /**
     * Collect PayPal credential packs from current flow, globals, and all flosc_flow_* options.
     * Each pack is from a single source (never stitched across sources).
     *
     * @return array<int, array{webhook_id:string,client_id:string,secret:string,mode:string}>
     */
    private function collect_paypal_credential_packs() {
        if ($this->credential_packs_cache !== null) {
            return $this->credential_packs_cache;
        }

        $packs = [];
        $push = static function ($webhook_id, $client_id, $secret, $mode) use (&$packs) {
            $webhook_id = sanitize_text_field((string) $webhook_id);
            $client_id  = sanitize_text_field((string) $client_id);
            $secret     = (string) $secret;
            $mode       = sanitize_key((string) $mode);
            if ($mode !== 'live') {
                $mode = 'sandbox';
            }
            // Skip empty packs.
            if ($webhook_id === '' && $client_id === '' && $secret === '') {
                return;
            }
            $packs[] = [
                'webhook_id' => $webhook_id,
                'client_id'  => $client_id,
                'secret'     => $secret,
                'mode'       => $mode,
            ];
        };

        // 1) Active flow via flosc()->get_setting / get_flow_setting chain.
        $push(
            $this->get_flow_setting('webhook_id', ''),
            $this->get_flow_setting('client_id', ''),
            $this->get_flow_setting('secret', ''),
            $this->get_flow_setting('mode', 'sandbox')
        );

        // 2) Explicit global options (legacy / site-wide).
        $push(
            get_option('flosc_paypal_webhook_id', ''),
            get_option('flosc_paypal_client_id', ''),
            get_option('flosc_paypal_secret', ''),
            get_option('flosc_paypal_mode', 'sandbox')
        );

        // 3) All per-flow option arrays (autoload=no — cached prepared options scan).
        $rows = function_exists( 'flosc_get_flow_option_rows' ) ? flosc_get_flow_option_rows() : array();
        foreach ( $rows as $row ) {
            $data = maybe_unserialize( $row['option_value'] ?? '' );
            if ( ! is_array( $data ) ) {
                continue;
            }
            $push(
                $data['paypal_webhook_id'] ?? '',
                $data['paypal_client_id'] ?? '',
                $data['paypal_secret'] ?? '',
                $data['paypal_mode'] ?? 'sandbox'
            );
        }

        $this->credential_packs_cache = $packs;
        return $packs;
    }

    /**
     * Resolve a single coherent credential pack for webhook verification.
     *
     * Never stitch webhook_id from one source with client_id/secret from another.
     * Prefer complete packs; among complete packs prefer live (production) over sandbox.
     *
     * @return array{webhook_id:string,client_id:string,secret:string,mode:string}
     */
    private function resolve_webhook_credential_pack() {
        $packs = $this->collect_paypal_credential_packs();

        $complete = [];
        foreach ($packs as $pack) {
            if ($pack['webhook_id'] !== '' && $pack['client_id'] !== '' && $pack['secret'] !== '') {
                $complete[] = $pack;
            }
        }

        if (!empty($complete)) {
            // Prefer live packs so global sandbox credentials + live webhook_id cannot win.
            usort($complete, static function ($a, $b) {
                $a_live = (($a['mode'] ?? '') === 'live') ? 1 : 0;
                $b_live = (($b['mode'] ?? '') === 'live') ? 1 : 0;
                if ($a_live !== $b_live) {
                    return $b_live - $a_live;
                }
                // Prefer longer client ids (real apps) over placeholders — stable tie-break.
                return strlen((string) ($b['client_id'] ?? '')) - strlen((string) ($a['client_id'] ?? ''));
            });
            return $complete[0];
        }

        // Partial only when no complete pack exists — still do not cross-stitch sources.
        // Return first pack that has credentials, else first with webhook_id, else empty.
        foreach ($packs as $pack) {
            if ($pack['client_id'] !== '' && $pack['secret'] !== '') {
                return $pack;
            }
        }
        foreach ($packs as $pack) {
            if ($pack['webhook_id'] !== '') {
                return $pack;
            }
        }

        return [
            'webhook_id' => '',
            'client_id'  => '',
            'secret'     => '',
            'mode'       => 'sandbox',
        ];
    }

    /**
     * Configured PayPal Webhook ID (required for signature verification).
     *
     * @return string
     */
    private function get_webhook_id() {
        $pack = $this->resolve_webhook_credential_pack();
        return sanitize_text_field($pack['webhook_id'] ?? '');
    }

    /**
     * Public webhook listener URL for this site.
     *
     * @return string
     */
    public function get_webhook_listener_url() {
        $url = rest_url('flosc/v1/webhooks/paypal');
        if (is_ssl() || (defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN)) {
            $url = set_url_scheme($url, 'https');
        }
        // Prefer https for public PayPal callbacks when site URL is https.
        $home = home_url('/');
        if (is_string($home) && strpos($home, 'https://') === 0) {
            $url = set_url_scheme($url, 'https');
        }
        return esc_url_raw($url);
    }

    /**
     * Event types for subscription lifecycle + renewal top-ups.
     *
     * @return array<int, array{name:string}>
     */
    private function get_required_webhook_event_types() {
        $names = [
            'PAYMENT.SALE.COMPLETED',
            'PAYMENT.CAPTURE.COMPLETED',
            'BILLING.SUBSCRIPTION.ACTIVATED',
            'BILLING.SUBSCRIPTION.CANCELLED',
            'BILLING.SUBSCRIPTION.SUSPENDED',
            'BILLING.SUBSCRIPTION.EXPIRED',
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
        ];
        $out = [];
        foreach ($names as $name) {
            $out[] = ['name' => $name];
        }
        return $out;
    }

    /**
     * Ensure a PayPal webhook exists for this site and persist its ID.
     * List → reuse matching URL → else create. Required for signature verification.
     *
     * @param bool $force_refresh Prefer re-list over trusting only local storage.
     * @return array{webhook_id:string,url:string,created:bool}|WP_Error
     */
    public function ensure_webhook_registered($force_refresh = false) {
        if (!$this->is_configured()) {
            return new WP_Error(
                'paypal_not_configured',
                __('PayPal Client ID and Secret required before registering webhooks', 'flosc'),
                ['status' => 400]
            );
        }

        $listener_url = $this->get_webhook_listener_url();
        if ($this->get_mode() === 'live' && strpos($listener_url, 'https://') !== 0) {
            return new WP_Error(
                'webhook_https_required',
                __('Live PayPal webhooks require an HTTPS endpoint', 'flosc'),
                ['status' => 400]
            );
        }

        $stored_id = sanitize_text_field((string) $this->get_flow_setting('webhook_id', ''));
        if ($stored_id === '') {
            $pack = $this->resolve_webhook_credential_pack();
            $stored_id = sanitize_text_field((string) ($pack['webhook_id'] ?? ''));
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $api_base = $this->get_api_base();
        $list = wp_remote_get($api_base . '/v1/notifications/webhooks', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);
        if (is_wp_error($list)) {
            return $list;
        }

        $list_code = (int) wp_remote_retrieve_response_code($list);
        if ($list_code === 401) {
            $token = $this->get_access_token(true);
            if (is_wp_error($token)) {
                return $token;
            }
            $list = wp_remote_get($api_base . '/v1/notifications/webhooks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 30,
            ]);
            if (is_wp_error($list)) {
                return $list;
            }
            $list_code = (int) wp_remote_retrieve_response_code($list);
        }

        $list_body = json_decode(wp_remote_retrieve_body($list), true);
        if ($list_code < 200 || $list_code >= 300 || !is_array($list_body)) {
            return new WP_Error(
                'paypal_webhook_list_failed',
                __('Could not list PayPal webhooks', 'flosc'),
                ['status' => 502]
            );
        }

        $normalize = static function ($url) {
            return untrailingslashit(strtolower(esc_url_raw((string) $url)));
        };
        $target = $normalize($listener_url);

        $matched_id = '';
        $webhooks = is_array($list_body['webhooks'] ?? null) ? $list_body['webhooks'] : [];
        foreach ($webhooks as $wh) {
            if (!is_array($wh)) {
                continue;
            }
            $wh_url = $normalize($wh['url'] ?? '');
            $wh_id  = sanitize_text_field((string) ($wh['id'] ?? ''));
            if ($wh_id !== '' && $wh_url !== '' && $wh_url === $target) {
                $matched_id = $wh_id;
                break;
            }
            if (!$force_refresh && $stored_id !== '' && $wh_id === $stored_id) {
                $matched_id = $wh_id;
                break;
            }
        }

        $created = false;
        if ($matched_id === '') {
            $create = wp_remote_post($api_base . '/v1/notifications/webhooks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode([
                    'url'         => $listener_url,
                    'event_types' => $this->get_required_webhook_event_types(),
                ]),
                'timeout' => 30,
            ]);
            if (is_wp_error($create)) {
                return $create;
            }

            $create_code = (int) wp_remote_retrieve_response_code($create);
            $create_body = json_decode(wp_remote_retrieve_body($create), true);

            if ($create_code >= 200 && $create_code < 300 && !empty($create_body['id'])) {
                $matched_id = sanitize_text_field((string) $create_body['id']);
                $created = true;
            } else {
                // WEBHOOK_URL_ALREADY_EXISTS or race — re-list and match by URL.
                $retry_list = wp_remote_get($api_base . '/v1/notifications/webhooks', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 30,
                ]);
                if (!is_wp_error($retry_list)) {
                    $retry_body = json_decode(wp_remote_retrieve_body($retry_list), true);
                    $retry_hooks = is_array($retry_body['webhooks'] ?? null) ? $retry_body['webhooks'] : [];
                    foreach ($retry_hooks as $wh) {
                        if (!is_array($wh)) {
                            continue;
                        }
                        if ($normalize($wh['url'] ?? '') === $target) {
                            $matched_id = sanitize_text_field((string) ($wh['id'] ?? ''));
                            break;
                        }
                    }
                }
                if ($matched_id === '') {
                    $msg = is_array($create_body)
                        ? (string) ($create_body['message'] ?? $create_body['name'] ?? __('PayPal webhook create failed', 'flosc'))
                        : __('PayPal webhook create failed', 'flosc');
                    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                        flosc_log('[FLOSC-PAYPAL] ensure_webhook_registered create failed HTTP ' . $create_code . ' ' . wp_json_encode($create_body));
                    }
                    return new WP_Error('paypal_webhook_create_failed', $msg, ['status' => 502]);
                }
            }
        }

        $this->persist_webhook_id($matched_id);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] ensure_webhook_registered id=' . $matched_id . ' created=' . ($created ? '1' : '0') . ' url=' . $listener_url);
        }

        return [
            'webhook_id' => $matched_id,
            'url'        => $listener_url,
            'created'    => $created,
        ];
    }

    /**
     * Persist webhook ID to global + matching flow options; clear credential pack cache.
     *
     * @param string $webhook_id PayPal webhook id.
     */
    private function persist_webhook_id($webhook_id) {
        $webhook_id = sanitize_text_field((string) $webhook_id);
        if ($webhook_id === '') {
            return;
        }

        // Keep global webhook id aligned with the credential mode that registered it
        // (prevents global sandbox client + live webhook_id mismatch on verify).
        update_option('flosc_paypal_webhook_id', $webhook_id, false);
        $mode = (string) $this->get_mode();
        if ($mode === 'live' || $mode === 'sandbox') {
            update_option('flosc_paypal_mode', $mode, false);
        }
        $client = (string) $this->get_client_id();
        $secret = (string) $this->get_secret();
        if ($client !== '') {
            update_option('flosc_paypal_client_id', $client, false);
        }
        if ($secret !== '') {
            update_option('flosc_paypal_secret', $secret, false);
        }

        $stems = [];
        if (function_exists('flosc')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $stem = sanitize_key((string) ($flow['id'] ?? ''));
                if ($stem === '') {
                    $stem = sanitize_key(pathinfo((string) ($flow['ivr_file'] ?? ''), PATHINFO_FILENAME));
                }
                if ($stem !== '') {
                    $stems[] = $stem;
                }
            }
        }

        $rows = function_exists( 'flosc_get_flow_option_rows' ) ? flosc_get_flow_option_rows() : array();
        foreach ( $rows as $row ) {
            $name = (string) ( $row['option_name'] ?? '' );
            $data = maybe_unserialize( $row['option_value'] ?? '' );
            if ( ! is_array( $data ) || $name === '' ) {
                continue;
            }
            $flow_client = (string) ( $data['paypal_client_id'] ?? '' );
            $stem        = str_replace( 'flosc_flow_', '', $name );
            $same_client = ( $client !== '' && $flow_client !== '' && hash_equals( $flow_client, $client ) );
            $is_current  = in_array( $stem, $stems, true );
            if ( $same_client || $is_current ) {
                $data['paypal_webhook_id'] = $webhook_id;
                if ( $same_client && empty( $data['paypal_mode'] ) ) {
                    $data['paypal_mode'] = $mode;
                }
                update_option( $name, $data, false );
            }
        }

        foreach ($stems as $stem) {
            $key  = 'flosc_flow_' . $stem;
            $data = get_option($key, []);
            if (!is_array($data)) {
                $data = [];
            }
            $data['paypal_webhook_id'] = $webhook_id;
            update_option($key, $data, false);
        }

        if ( function_exists( 'flosc_bust_flow_option_rows_cache' ) ) {
            flosc_bust_flow_option_rows_cache();
        }
        $this->credential_packs_cache = null;
    }

    /**
     * Default mode for new installs. Credentials must be set via admin Payments tab.
     */
    public static function get_default_mode() {
        return 'sandbox';
    }

    /** @var array|null Request-scoped credential override for webhook OAuth. */
    private $runtime_credential_override = null;

    private function get_mode() {
        if (is_array($this->runtime_credential_override) && !empty($this->runtime_credential_override['mode'])) {
            return (string) $this->runtime_credential_override['mode'];
        }
        return $this->get_flow_setting('mode', 'sandbox');
    }

    private function get_client_id() {
        if (is_array($this->runtime_credential_override) && !empty($this->runtime_credential_override['client_id'])) {
            return (string) $this->runtime_credential_override['client_id'];
        }
        return $this->get_flow_setting('client_id', '');
    }

    private function get_secret() {
        if (is_array($this->runtime_credential_override) && !empty($this->runtime_credential_override['secret'])) {
            return (string) $this->runtime_credential_override['secret'];
        }
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
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
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
    public function create_order($user, $amount_dollars, $currency, $offer_id, $purchase_uuid = '') {
        $token = $this->get_access_token();
        if (is_wp_error($token)) return $token;

        $amount = number_format(floatval($amount_dollars), 2, '.', '');
        $currency = strtoupper($currency);
        $purchase_uuid = sanitize_text_field((string) $purchase_uuid);
        // Industry standard: custom_id is the server purchase intent UUID when present.
        // Fall back to JSON bind for older clients (still includes offer_id).
        $custom_id = $purchase_uuid !== ''
            ? substr($purchase_uuid, 0, 127)
            : wp_json_encode([
                'user_id'  => $user->ID ?? 0,
                'offer_id' => $offer_id,
            ]);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] create_order: user=' . ($user->ID ?? 0) . ', amount=' . $amount . ' ' . $currency . ', offer=' . $offer_id . ', intent=' . ($purchase_uuid ?: 'legacy'));
        }

        $order_body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $offer_id,
                'description' => 'FLOSC Purchase - ' . $offer_id,
                'custom_id' => $custom_id,
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
        if (!is_array($capture) || empty($capture)) {
            return new WP_Error(
                'payment_not_completed',
                __('PayPal capture is missing; payment is not completed', 'flosc'),
                ['status' => 400]
            );
        }

        // PP-01: require completed order + completed capture (HTTP 2xx alone is not settlement).
        $order_status   = strtoupper((string) ($body['status'] ?? ''));
        $capture_status = strtoupper((string) ($capture['status'] ?? ''));
        if ($order_status !== 'COMPLETED') {
            return new WP_Error(
                'payment_not_completed',
                /* translators: %s: PayPal order status string. */
                sprintf(__('PayPal order status is not COMPLETED (%s)', 'flosc'), $order_status !== '' ? $order_status : 'empty'),
                ['status' => 400]
            );
        }
        if ($capture_status !== 'COMPLETED') {
            return new WP_Error(
                'payment_not_completed',
                /* translators: %s: PayPal capture status string. */
                sprintf(__('PayPal capture status is not COMPLETED (%s)', 'flosc'), $capture_status !== '' ? $capture_status : 'empty'),
                ['status' => 400]
            );
        }

        // custom_id: server purchase_uuid (preferred) or legacy JSON {user_id, offer_id}.
        $custom_raw = sanitize_text_field((string) ($capture['custom_id'] ?? ($body['purchase_units'][0]['custom_id'] ?? '')));
        $custom_user_id  = 0;
        $custom_offer_id = '';
        $purchase_uuid   = '';

        if ($custom_raw !== '' && isset($custom_raw[0]) && $custom_raw[0] === '{') {
            $decoded_custom = json_decode($custom_raw, true, 8);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded_custom)) {
                $custom_user_id  = isset($decoded_custom['user_id']) ? absint($decoded_custom['user_id']) : 0;
                $custom_offer_id = sanitize_text_field((string) ($decoded_custom['offer_id'] ?? ''));
                $purchase_uuid   = sanitize_text_field((string) ($decoded_custom['purchase_uuid'] ?? ''));
            }
        } elseif ($custom_raw !== '') {
            $purchase_uuid = $custom_raw;
        }

        if ($purchase_uuid !== '' && function_exists('flosc_paypal_purchase_intent_get')) {
            $intent = flosc_paypal_purchase_intent_get($purchase_uuid);
            if (is_array($intent)) {
                if ($custom_offer_id === '' && !empty($intent['offer_id'])) {
                    $custom_offer_id = sanitize_text_field((string) $intent['offer_id']);
                }
                if ($custom_user_id <= 0 && !empty($intent['user_id'])) {
                    $custom_user_id = absint($intent['user_id']);
                }
            }
        }

        $payer = is_array($body['payer'] ?? null) ? $body['payer'] : [];
        $payment_source_paypal = is_array($body['payment_source']['paypal'] ?? null) ? $body['payment_source']['paypal'] : [];
        $payer_email = sanitize_email((string) ($payer['email_address'] ?? $payment_source_paypal['email_address'] ?? ''));
        $given = sanitize_text_field((string) ($payer['name']['given_name'] ?? $payment_source_paypal['name']['given_name'] ?? ''));
        $surname = sanitize_text_field((string) ($payer['name']['surname'] ?? $payment_source_paypal['name']['surname'] ?? ''));
        $payer_name = trim($given . ' ' . $surname);

        $txn_id = sanitize_text_field((string) ($capture['id'] ?? ''));
        if ($txn_id === '') {
            return new WP_Error('payment_not_completed', __('PayPal capture has no transaction id', 'flosc'), ['status' => 400]);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] capture_order SUCCESS: transaction_id=' . $txn_id . ', amount=' . ($capture['amount']['value'] ?? '?') . ', payer=' . ($payer_email ?: 'none'));
        }

        return [
            'success'         => true,
            'order_id'        => sanitize_text_field((string) ($body['id'] ?? '')),
            'status'          => 'completed',
            'transaction_id'  => $txn_id,
            'amount'          => sanitize_text_field((string) ($capture['amount']['value'] ?? '0.00')),
            'currency'        => sanitize_text_field((string) ($capture['amount']['currency_code'] ?? 'USD')),
            'user_id'         => $custom_user_id > 0 ? $custom_user_id : null,
            'offer_id'        => $custom_offer_id !== '' ? $custom_offer_id : null,
            'purchase_uuid'   => $purchase_uuid !== '' ? $purchase_uuid : null,
            'payer_email'     => $payer_email,
            'payer_name'      => $payer_name,
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

        $response = wp_remote_get($this->get_api_base() . '/v1/billing/subscriptions/' . rawurlencode($subscription_id), [
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
            // Plans already exist — still ensure webhook is registered for renewals.
            $this->ensure_webhook_registered();
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

        $this->ensure_webhook_registered();
        return $plans;
    }

    /**
     * Ensure PayPal product + plans exist for explicit monthly/yearly amounts (dollars).
     * Used when a native coupon changes recurring price (e.g. $25/mo → $10/mo).
     * Cached per credentials + amount pair so we do not recreate plans every checkout.
     *
     * @param float  $monthly_price Recurring monthly amount.
     * @param float  $yearly_price  Recurring yearly amount.
     * @param string $product_name  Catalog label.
     * @return array|WP_Error
     */
    public function ensure_plans_for_prices($monthly_price, $yearly_price, $product_name = '') {
        $monthly_price = round(max(0.0, floatval($monthly_price)), 2);
        $yearly_price  = round(max(0.0, floatval($yearly_price)), 2);
        if ($monthly_price <= 0 && $yearly_price <= 0) {
            return new WP_Error('no_price', __('Subscription amounts must be greater than zero.', 'flosc'), ['status' => 400]);
        }
        if ($monthly_price <= 0) {
            $monthly_price = $yearly_price > 0 ? round($yearly_price / 10, 2) : 1.0;
        }
        if ($yearly_price <= 0) {
            $yearly_price = round($monthly_price * 10, 2);
        }

        $amt_key = number_format($monthly_price, 2, '.', '') . '_' . number_format($yearly_price, 2, '.', '');
        $cache_key = $this->get_plans_option_key() . '_amt_' . md5($amt_key);
        $cached = get_option($cache_key, []);
        if (is_array($cached)
            && !empty($cached['monthly_plan_id'])
            && !empty($cached['yearly_plan_id'])
            && !empty($cached['credentials_fingerprint'])
            && hash_equals((string) $cached['credentials_fingerprint'], $this->get_credentials_fingerprint())
        ) {
            return $cached;
        }

        $product_name = trim((string) $product_name);
        if ($product_name === '') {
            $product_name = 'FLOSC Subscription';
        }
        $product_desc = $product_name . ' (promo ' . $amt_key . ')';

        $product = $this->create_product($product_name . ' Promo', $product_desc);
        if (is_wp_error($product)) {
            return $product;
        }
        $product_id = $product['id'];

        $monthly_label = sprintf('%s Monthly — $%s/month', $product_name, number_format($monthly_price, 2, '.', ''));
        $yearly_label  = sprintf('%s Yearly — $%s/year', $product_name, number_format($yearly_price, 2, '.', ''));

        $monthly = $this->create_plan($product_id, $monthly_label, $monthly_price, 'MONTH');
        if (is_wp_error($monthly)) {
            return $monthly;
        }
        $yearly = $this->create_plan($product_id, $yearly_label, $yearly_price, 'YEAR');
        if (is_wp_error($yearly)) {
            return $yearly;
        }

        $plans = [
            'product_id'              => $product_id,
            'monthly_plan_id'         => $monthly['id'],
            'yearly_plan_id'          => $yearly['id'],
            'monthly_price'           => $monthly_price,
            'yearly_price'            => $yearly_price,
            'mode'                    => $this->get_mode(),
            'credentials_fingerprint' => $this->get_credentials_fingerprint(),
            'created_at'              => current_time('mysql'),
            'amount_key'              => $amt_key,
        ];
        update_option($cache_key, $plans, false);

        // Index for activate-subscription plan type resolution.
        $index_key = $this->get_plans_option_key() . '_index';
        $index = get_option($index_key, []);
        if (!is_array($index)) {
            $index = [];
        }
        $index[$plans['monthly_plan_id']] = 'monthly';
        $index[$plans['yearly_plan_id']]  = 'yearly';
        update_option($index_key, $index, false);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] ensure_plans_for_prices amt=' . $amt_key
                . ' monthly=' . $plans['monthly_plan_id'] . ' yearly=' . $plans['yearly_plan_id']);
        }

        return $plans;
    }

    /**
     * Resolve plan_id to monthly|yearly including promo plan caches.
     *
     * @param string $plan_id PayPal plan id.
     * @return string '' if unknown.
     */
    public function resolve_plan_type_for_id($plan_id) {
        $plan_id = sanitize_text_field((string) $plan_id);
        if ($plan_id === '') {
            return '';
        }
        $stored = $this->get_stored_plans();
        if (!empty($stored['monthly_plan_id']) && hash_equals((string) $stored['monthly_plan_id'], $plan_id)) {
            return 'monthly';
        }
        if (!empty($stored['yearly_plan_id']) && hash_equals((string) $stored['yearly_plan_id'], $plan_id)) {
            return 'yearly';
        }
        $index = get_option($this->get_plans_option_key() . '_index', []);
        if (is_array($index) && !empty($index[$plan_id])) {
            $t = sanitize_key((string) $index[$plan_id]);
            return in_array($t, ['monthly', 'yearly'], true) ? $t : '';
        }
        return '';
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
     * Collect PayPal transmission headers from a REST request and/or PHP server vars.
     *
     * Single source of truth for the REST dispatcher and for handle_webhook() fallback
     * so verification cannot be left unwired by a dispatcher-only regression.
     *
     * @param WP_REST_Request|null $request Optional REST request.
     * @return array<string,string> Map keyed by paypal-transmission-id, etc.
     */
    public static function collect_transmission_headers_from_request($request = null) {
        $keys = [
            'paypal-transmission-id',
            'paypal-transmission-time',
            'paypal-transmission-sig',
            'paypal-cert-url',
            'paypal-auth-algo',
        ];
        $server_aliases = [
            'paypal-transmission-id'   => ['HTTP_PAYPAL_TRANSMISSION_ID', 'PAYPAL_TRANSMISSION_ID'],
            'paypal-transmission-time' => ['HTTP_PAYPAL_TRANSMISSION_TIME', 'PAYPAL_TRANSMISSION_TIME'],
            'paypal-transmission-sig'  => ['HTTP_PAYPAL_TRANSMISSION_SIG', 'PAYPAL_TRANSMISSION_SIG'],
            'paypal-cert-url'          => ['HTTP_PAYPAL_CERT_URL', 'PAYPAL_CERT_URL'],
            'paypal-auth-algo'         => ['HTTP_PAYPAL_AUTH_ALGO', 'PAYPAL_AUTH_ALGO'],
        ];

        // Also scan getallheaders() when available (Apache / some FPM setups).
        $all_headers = [];
        if (function_exists('getallheaders')) {
            $raw_all = getallheaders();
            if (is_array($raw_all)) {
                foreach ($raw_all as $hk => $hv) {
                    $all_headers[strtolower((string) $hk)] = $hv;
                }
            }
        }

        $headers = [];
        foreach ($keys as $key) {
            $value = null;
            if ($request instanceof WP_REST_Request) {
                $value = $request->get_header($key);
                // Some stacks expose original case via get_header with HTTP name.
                if (($value === null || $value === '') && strpos($key, 'paypal-') === 0) {
                    $http_name = strtoupper(str_replace('-', '-', $key));
                    $value = $request->get_header($http_name);
                }
            }
            if (($value === null || $value === '') && isset($server_aliases[$key])) {
                foreach ($server_aliases[$key] as $server_key) {
                    if (!empty($_SERVER[$server_key])) {
                        $value = sanitize_text_field(wp_unslash((string) $_SERVER[$server_key]));
                        break;
                    }
                }
            }
            if (($value === null || $value === '') && isset($all_headers[$key])) {
                $value = $all_headers[$key];
            }
            // getallheaders may use original casing (e.g. Paypal-Transmission-Id).
            if (($value === null || $value === '') && !empty($all_headers)) {
                foreach ($all_headers as $hk => $hv) {
                    if (strtolower(str_replace('_', '-', (string) $hk)) === $key) {
                        $value = $hv;
                        break;
                    }
                }
            }
            if ($value === null || $value === '') {
                continue;
            }
            $raw = is_array($value) ? (string) $value[0] : (string) $value;
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            // Never sanitize_text_field transmission-sig (base64) or cert-url.
            if ($key === 'paypal-transmission-sig') {
                $headers[$key] = preg_replace('/\s+/', '', $raw);
            } elseif ($key === 'paypal-cert-url') {
                $headers[$key] = esc_url_raw($raw);
            } else {
                $headers[$key] = sanitize_text_field($raw);
            }
        }

        return $headers;
    }

    /**
     * Handle PayPal webhooks.
     *
     * Security (mandatory, before any mutation):
     * - Require PAYPAL-TRANSMISSION-* headers (dispatcher + environment fallback)
     * - Require configured Webhook ID
     * - Verify via PayPal POST /v1/notifications/verify-webhook-signature
     * - Reject unsigned / failed verification with 401
     * - Raw body string only (never re-encoded JSON — would break signatures)
     *
     * After verification:
     * - Event-id claim (atomic) + sale/cycle idempotency on credits
     * - Subscription renewals / cancel / suspend / expire
     *
     * First activation remains activate-subscription; this covers later cycles.
     *
     * @param string $payload Raw JSON body as received from PayPal (must be string).
     * @param array  $headers Transmission headers from the REST dispatcher.
     * @return array|WP_Error
     */
    public function handle_webhook($payload, $headers = []) {
        // Signature verification requires the exact bytes PayPal signed — no re-encode.
        if (!is_string($payload) || $payload === '') {
            return new WP_Error(
                'invalid_payload',
                __('Invalid PayPal webhook payload: raw body string required', 'flosc'),
                ['status' => 400]
            );
        }
        $raw_body = $payload;

        // Prefer dispatcher-supplied headers; fill gaps from $_SERVER / getallheaders()
        // so verification cannot be left unwired if a caller forgets to forward them.
        $merged = is_array($headers) ? $headers : [];
        $from_env = self::collect_transmission_headers_from_request(null);
        foreach ($from_env as $k => $v) {
            if (!isset($merged[$k]) || $merged[$k] === '') {
                $merged[$k] = $v;
            }
        }

        // SECURITY: Cryptographic authenticity before any user/token/subscription mutation.
        $verified = $this->verify_webhook_signature($raw_body, $merged);
        if (is_wp_error($verified)) {
            return $verified;
        }

        $data = json_decode($raw_body, true);
        if (!is_array($data)) {
            return new WP_Error(
                'invalid_payload',
                __('Invalid PayPal webhook JSON', 'flosc'),
                ['status' => 400]
            );
        }

        // Event-level idempotency (after auth only). Claim lock before mutation to
        // prevent concurrent double-processing; sale/cycle keys still protect credits.
        $event_id = sanitize_text_field((string) ($data['id'] ?? ''));
        if ($event_id !== '' && $this->is_paypal_event_processed($event_id)) {
            return [
                'received'  => true,
                'processed' => false,
                'reason'    => 'already_processed',
                'event_id'  => $event_id,
            ];
        }

        $event_type = sanitize_text_field((string) ($data['event_type'] ?? $data['eventType'] ?? ''));
        $resource = is_array($data['resource'] ?? null) ? $data['resource'] : [];
        $result = [
            'received'   => true,
            'processed'  => false,
            'event_type' => $event_type,
            'event_id'   => $event_id,
        ];

        // Renewal sale against a subscription (billing agreement).
        if ($event_type === 'PAYMENT.SALE.COMPLETED' || $event_type === 'PAYMENT.CAPTURE.COMPLETED') {
            $subscription_id = sanitize_text_field((string) (
                $resource['billing_agreement_id']
                ?? ($resource['supplementary_data']['related_ids']['subscription_id'] ?? '')
            ));
            $sale_id = sanitize_text_field((string) ($resource['id'] ?? ''));

            if ($subscription_id === '') {
                $result['reason'] = 'not_subscription_sale';
                $this->mark_paypal_event_processed($event_id);
                return $result;
            }

            $user_id = self::get_user_id_for_subscription($subscription_id);
            if ($user_id <= 0) {
                $result['reason'] = 'unknown_subscription';
                $result['subscription_id'] = $subscription_id;
                // Do not mark processed: subscription may be indexed later; PayPal can retry.
                return $result;
            }

            // Claim event lock only once we know we will mutate.
            if ($event_id !== '' && !$this->claim_paypal_event($event_id)) {
                return [
                    'received'  => true,
                    'processed' => false,
                    'reason'    => 'already_processed',
                    'event_id'  => $event_id,
                ];
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

            // Prefer the offer that was sold at activate (custom token packs); else flow defaults.
            $sold_offer_id = sanitize_text_field((string) get_user_meta($user_id, '_flosc_subscription_offer_id', true));
            if ($sold_offer_id === '') {
                $sold_offer_id = sanitize_text_field((string) get_user_meta($user_id, '_flosc_purchased_offer_id', true));
            }
            $renewal_offer = null;
            if ($sold_offer_id !== '' && function_exists('flosc_sale')) {
                $om = flosc_sale()->offers();
                if ($om && method_exists($om, 'get_offer')) {
                    $renewal_offer = $om->get_offer($sold_offer_id, $flow_id ?: null);
                }
            }

            $topup = ['skipped' => true];
            if (function_exists('flosc')) {
                $mode = ($plan_type === 'yearly') ? 'recurring_yearly' : 'recurring';
                $credit_ctx = [
                    'idempotency_key' => $idem,
                    'subscription_id' => $subscription_id,
                    'offer_id' => $sold_offer_id,
                    'reason' => 'PayPal subscription renewal (' . $plan_type . ')',
                ];
                if (is_array($renewal_offer)) {
                    $credit_ctx['offer'] = $renewal_offer;
                }
                if (method_exists(flosc(), 'flosc_apply_product_token_credit_public')) {
                    $topup = flosc()->flosc_apply_product_token_credit_public($user_id, $flow_id, $mode, $credit_ctx);
                } elseif (method_exists(flosc(), 'flosc_apply_subscription_token_topup_public')) {
                    $topup = flosc()->flosc_apply_subscription_token_topup_public($user_id, $flow_id, $plan_type, $credit_ctx);
                }
            }

            update_user_meta($user_id, '_flosc_subscription_status', 'active');
            update_user_meta($user_id, '_flosc_subscription_last_sale_id', $sale_id);
            update_user_meta($user_id, '_flosc_subscription_last_payment_at', current_time('mysql'));

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] webhook renewal top-up user=' . $user_id . ' sub=' . $subscription_id . ' sale=' . $sale_id . ' result=' . wp_json_encode($topup));
            }

            $this->mark_paypal_event_processed($event_id);

            return [
                'received'        => true,
                'processed'       => true,
                'event_type'      => $event_type,
                'event_id'        => $event_id,
                'user_id'         => $user_id,
                'subscription_id' => $subscription_id,
                'token_topup'     => $topup,
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
                $user_id = self::get_user_id_for_subscription($subscription_id);
                if ($user_id > 0) {
                    if ($event_id !== '' && !$this->claim_paypal_event($event_id)) {
                        return [
                            'received'  => true,
                            'processed' => false,
                            'reason'    => 'already_processed',
                            'event_id'  => $event_id,
                        ];
                    }
                    $status = strtolower(str_replace('BILLING.SUBSCRIPTION.', '', $event_type));
                    update_user_meta($user_id, '_flosc_subscription_status', $status);
                    $result['user_id'] = $user_id;
                    $result['subscription_id'] = $subscription_id;
                    $result['status'] = $status;
                }
            }
            $result['processed'] = true;
            $this->mark_paypal_event_processed($event_id);
            return $result;
        }

        // Unknown event types: acknowledge after verify (no mutation).
        $this->mark_paypal_event_processed($event_id);
        return $result;
    }

    /**
     * Whether a PayPal event id was already claimed/processed.
     *
     * @param string $event_id PayPal event id.
     * @return bool
     */
    private function is_paypal_event_processed($event_id) {
        $event_id = sanitize_text_field((string) $event_id);
        if ($event_id === '') {
            return false;
        }
        return (bool) get_transient('flosc_paypal_event_' . md5($event_id));
    }

    /**
     * Atomically claim an event id for processing. Returns false if already claimed.
     * Uses add_option (atomic under concurrent deliveries) + transient for fast path.
     *
     * @param string $event_id PayPal event id.
     * @return bool True if this request owns the claim.
     */
    private function claim_paypal_event($event_id) {
        $event_id = sanitize_text_field((string) $event_id);
        if ($event_id === '') {
            return true;
        }
        $hash = md5($event_id);
        if (get_transient('flosc_paypal_event_' . $hash)) {
            return false;
        }
        $opt_key = 'flosc_pp_evt_' . $hash;
        // add_option is atomic in MySQL; false means another worker claimed first.
        $claimed = add_option($opt_key, (string) time(), '', 'no');
        if (!$claimed) {
            set_transient('flosc_paypal_event_' . $hash, 1, WEEK_IN_SECONDS);
            return false;
        }
        set_transient('flosc_paypal_event_' . $hash, 1, WEEK_IN_SECONDS);
        // Probabilistic cleanup of expired claim options (avoid per-event cron spam).
        if (wp_rand(1, 50) === 1) {
            $this->cleanup_expired_paypal_event_claims();
        }
        return true;
    }

    /**
     * Mark a PayPal webhook event id as processed (durable claim + transient).
     *
     * @param string $event_id PayPal event id.
     */
    private function mark_paypal_event_processed($event_id) {
        $event_id = sanitize_text_field((string) $event_id);
        if ($event_id === '') {
            return;
        }
        $hash = md5($event_id);
        $opt_key = 'flosc_pp_evt_' . $hash;
        if (get_option($opt_key) === false) {
            add_option($opt_key, (string) time(), '', 'no');
        }
        set_transient('flosc_paypal_event_' . $hash, 1, WEEK_IN_SECONDS);
    }

    /**
     * Delete claim options older than 14 days (options table hygiene).
     */
    private function cleanup_expired_paypal_event_claims() {
        global $wpdb;
        $cutoff    = time() - ( 14 * DAY_IN_SECONDS );
        $cache_key = 'pp_evt_rows_v1';
        $rows      = wp_cache_get( $cache_key, 'flosc_paypal' );
        if ( ! is_array( $rows ) ) {
            // Claims use add_option( ..., '', 'no' ) — bulk LIKE has no WP API.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk LIKE on autoload=no options; object-cached
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100",
                    $wpdb->esc_like( 'flosc_pp_evt_' ) . '%'
                ),
                ARRAY_A
            );
            if ( ! is_array( $rows ) ) {
                $rows = array();
            }
            wp_cache_set( $cache_key, $rows, 'flosc_paypal', 300 );
        }
        foreach ( $rows as $row ) {
            $ts = absint( $row['option_value'] ?? 0 );
            if ( $ts > 0 && $ts < $cutoff ) {
                delete_option( (string) $row['option_name'] );
                wp_cache_delete( $cache_key, 'flosc_paypal' );
            }
        }
    }

    /**
     * Extract PayPal transmission headers (case-insensitive).
     *
     * @param array $headers Headers from REST dispatcher or raw map.
     * @return array{transmission_id:string,transmission_time:string,transmission_sig:string,cert_url:string,auth_algo:string}|WP_Error
     */
    private function extract_paypal_transmission_headers(array $headers) {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $k = strtolower((string) $key);
            $k = str_replace('_', '-', $k);
            if (is_array($value)) {
                $value = isset($value[0]) ? $value[0] : '';
            }
            $normalized[$k] = trim((string) $value);
        }

        // Also accept short keys if the dispatcher already mapped them.
        $aliases = [
            'transmission_id'   => ['paypal-transmission-id', 'transmission-id', 'transmission_id'],
            'transmission_time' => ['paypal-transmission-time', 'transmission-time', 'transmission_time'],
            'transmission_sig'  => ['paypal-transmission-sig', 'transmission-sig', 'transmission_sig'],
            'cert_url'          => ['paypal-cert-url', 'cert-url', 'cert_url'],
            'auth_algo'         => ['paypal-auth-algo', 'auth-algo', 'auth_algo'],
        ];

        $out = [];
        foreach ($aliases as $field => $keys) {
            $out[$field] = '';
            foreach ($keys as $candidate) {
                $dash = strtolower(str_replace('_', '-', (string) $candidate));
                $underscore = str_replace('-', '_', $dash);
                if (isset($normalized[$dash]) && $normalized[$dash] !== '') {
                    $out[$field] = $normalized[$dash];
                    break;
                }
                if (isset($normalized[$underscore]) && $normalized[$underscore] !== '') {
                    $out[$field] = $normalized[$underscore];
                    break;
                }
            }
        }

        // Unsigned request = unauthenticated (401), not a generic client error.
        foreach (['transmission_id', 'transmission_time', 'transmission_sig', 'cert_url', 'auth_algo'] as $required) {
            if ($out[$required] === '') {
                return new WP_Error(
                    'missing_signature',
                    __('Missing PayPal webhook transmission headers', 'flosc'),
                    ['status' => 401, 'missing' => $required]
                );
            }
        }

        return $out;
    }

    /**
     * Allow only PayPal-owned certificate URLs (defense in depth before verify API).
     *
     * @param string $cert_url Certificate URL from PAYPAL-CERT-URL.
     * @return true|WP_Error
     */
    private function assert_paypal_cert_url($cert_url) {
        $cert_url = esc_url_raw((string) $cert_url);
        if ($cert_url === '' || strpos($cert_url, 'https://') !== 0) {
            return new WP_Error(
                'invalid_cert_url',
                __('Invalid PayPal certificate URL', 'flosc'),
                ['status' => 401]
            );
        }

        $host = strtolower((string) wp_parse_url($cert_url, PHP_URL_HOST));
        if ($host === '') {
            return new WP_Error(
                'invalid_cert_url',
                __('Invalid PayPal certificate URL host', 'flosc'),
                ['status' => 401]
            );
        }

        // Only paypal.com and its subdomains (api.paypal.com, api.sandbox.paypal.com, …).
        if (!preg_match('/(^|\.)paypal\.com$/i', $host)) {
            return new WP_Error(
                'invalid_cert_url',
                __('PayPal certificate URL host is not allowed', 'flosc'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Verify webhook authenticity via PayPal Notifications API.
     *
     * Official endpoint (sandbox/live API base):
     *   POST /v1/notifications/verify-webhook-signature
     * Required transmission headers from the inbound webhook HTTP request:
     *   PAYPAL-TRANSMISSION-ID, PAYPAL-TRANSMISSION-TIME, PAYPAL-TRANSMISSION-SIG,
     *   PAYPAL-CERT-URL, PAYPAL-AUTH-ALGO
     * Plus configured webhook_id and the exact raw webhook JSON body.
     *
     * @see https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature_post
     *
     * @param string $raw_body Exact request body as received (never re-encoded for the event).
     * @param array  $headers  Transmission headers.
     * @return true|WP_Error
     */
    private function verify_webhook_signature($raw_body, array $headers) {
        // Fail closed: no configured webhook / app credentials ⇒ reject before mutation.
        $pack = $this->resolve_webhook_credential_pack();
        $webhook_id = sanitize_text_field($pack['webhook_id'] ?? '');
        if ($webhook_id === '') {
            return new WP_Error(
                'webhook_not_configured',
                __('PayPal Webhook ID required', 'flosc'),
                ['status' => 503]
            );
        }
        if (($pack['client_id'] ?? '') === '' || ($pack['secret'] ?? '') === '') {
            return new WP_Error(
                'webhook_not_configured',
                __('PayPal Client ID and Secret required to verify webhooks', 'flosc'),
                ['status' => 503]
            );
        }

        $tx = $this->extract_paypal_transmission_headers($headers);
        if (is_wp_error($tx)) {
            return $tx;
        }

        $cert_ok = $this->assert_paypal_cert_url($tx['cert_url']);
        if (is_wp_error($cert_ok)) {
            return $cert_ok;
        }

        // Sanity-check body is JSON object; keep $raw_body for webhook_event (exact bytes).
        $event_probe = json_decode($raw_body, false);
        if (!is_object($event_probe)) {
            return new WP_Error(
                'invalid_payload',
                __('Invalid PayPal webhook JSON', 'flosc'),
                ['status' => 400]
            );
        }

        // OAuth + API base must use the same coherent pack that owns the webhook_id.
        $this->begin_webhook_credential_context($pack);
        try {
            $token = $this->get_access_token();
            if (is_wp_error($token)) {
                return new WP_Error(
                    'paypal_auth_failed',
                    __('PayPal OAuth failed; cannot verify webhook', 'flosc'),
                    ['status' => 503]
                );
            }

            // Industry standard for PHP: splice the raw event JSON into the verify
            // request so field order / unicode is not altered by a second encode.
            // PayPal signs the original body; re-encoding webhook_event can fail verify.
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            $encoded = '{'
                . '"auth_algo":' . wp_json_encode((string) $tx['auth_algo'], $flags) . ','
                . '"cert_url":' . wp_json_encode((string) $tx['cert_url'], $flags) . ','
                . '"transmission_id":' . wp_json_encode((string) $tx['transmission_id'], $flags) . ','
                . '"transmission_sig":' . wp_json_encode((string) $tx['transmission_sig'], $flags) . ','
                . '"transmission_time":' . wp_json_encode((string) $tx['transmission_time'], $flags) . ','
                . '"webhook_id":' . wp_json_encode((string) $webhook_id, $flags) . ','
                . '"webhook_event":' . $raw_body
                . '}';

            $api_base = $this->get_api_base();

            $response = wp_remote_post($api_base . '/v1/notifications/verify-webhook-signature', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => $encoded,
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    flosc_log('[FLOSC-PAYPAL] verify-webhook-signature WP_Error: ' . $response->get_error_message());
                }
                return new WP_Error(
                    'paypal_verify_unavailable',
                    __('PayPal webhook verification unavailable', 'flosc'),
                    ['status' => 503]
                );
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $body_raw    = wp_remote_retrieve_body($response);
            $body        = json_decode($body_raw, true);

            // Stale OAuth token — refresh once and retry verify.
            if ($status_code === 401) {
                $token = $this->get_access_token(true);
                if (is_wp_error($token)) {
                    return new WP_Error(
                        'paypal_auth_failed',
                        __('PayPal OAuth failed; cannot verify webhook', 'flosc'),
                        ['status' => 503]
                    );
                }
                $response = wp_remote_post($api_base . '/v1/notifications/verify-webhook-signature', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ],
                    'body'    => $encoded,
                    'timeout' => 30,
                ]);
                if (is_wp_error($response)) {
                    return new WP_Error(
                        'paypal_verify_unavailable',
                        __('PayPal webhook verification unavailable', 'flosc'),
                        ['status' => 503]
                    );
                }
                $status_code = (int) wp_remote_retrieve_response_code($response);
                $body_raw    = wp_remote_retrieve_body($response);
                $body        = json_decode($body_raw, true);
            }

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] verify-webhook-signature HTTP ' . $status_code . ' status=' . (is_array($body) ? ($body['verification_status'] ?? 'n/a') : 'n/a') . ' body=' . substr((string) $body_raw, 0, 300));
            }

            // Explicit SUCCESS only path that allows mutation.
            if (is_array($body) && isset($body['verification_status'])) {
                $verification_status = strtoupper((string) $body['verification_status']);
                if ($verification_status === 'SUCCESS') {
                    return true;
                }
                // FAILURE or any other status ⇒ unauthenticated.
                return new WP_Error(
                    'invalid_signature',
                    __('Invalid PayPal webhook signature', 'flosc'),
                    ['status' => 401]
                );
            }

            // Client errors (bad headers/cert/webhook id) are auth failures, not outages.
            if ($status_code >= 400 && $status_code < 500) {
                return new WP_Error(
                    'invalid_signature',
                    __('Invalid PayPal webhook signature', 'flosc'),
                    ['status' => 401]
                );
            }

            // 5xx / transport-level: temporary, PayPal may retry.
            if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
                return new WP_Error(
                    'paypal_verify_failed',
                    __('PayPal webhook verification request failed', 'flosc'),
                    ['status' => 503]
                );
            }

            return new WP_Error(
                'invalid_signature',
                __('Invalid PayPal webhook signature', 'flosc'),
                ['status' => 401]
            );
        } finally {
            $this->end_webhook_credential_context();
        }
    }

    /**
     * Bind request-scoped OAuth credentials to a coherent pack (same webhook app).
     *
     * @param array{webhook_id?:string,client_id?:string,secret?:string,mode?:string} $pack Credential pack.
     */
    private function begin_webhook_credential_context(array $pack) {
        $this->runtime_credential_override = [
            'client_id' => (string) ($pack['client_id'] ?? ''),
            'secret'    => (string) ($pack['secret'] ?? ''),
            'mode'      => ((string) ($pack['mode'] ?? 'sandbox') === 'live') ? 'live' : 'sandbox',
        ];
    }

    /**
     * Clear request-scoped webhook credential override.
     */
    private function end_webhook_credential_context() {
        $this->runtime_credential_override = null;
    }

    /**
     * Option key: map PayPal subscription id → WP user id (O(1) webhook lookup).
     */
    const SUBSCRIPTION_INDEX_OPTION = 'flosc_paypal_subscription_index';

    /**
     * Record subscription ownership when a subscription is created/activated.
     *
     * @param int    $user_id
     * @param string $subscription_id
     */
    public static function index_subscription($user_id, $subscription_id) {
        $user_id = absint($user_id);
        $subscription_id = sanitize_text_field((string) $subscription_id);
        if ($user_id <= 0 || $subscription_id === '') {
            return;
        }

        $index = get_option(self::SUBSCRIPTION_INDEX_OPTION, null);
        if (!is_array($index)) {
            $index = self::build_subscription_index();
        }
        $index[$subscription_id] = $user_id;
        update_option(self::SUBSCRIPTION_INDEX_OPTION, $index, false);
    }

    /**
     * Resolve WP user id for a PayPal subscription id.
     * Uses the reverse index (built at activation / on first need), not meta_key queries.
     *
     * @param string $subscription_id
     * @return int
     */
    public static function get_user_id_for_subscription($subscription_id) {
        $subscription_id = sanitize_text_field((string) $subscription_id);
        if ($subscription_id === '') {
            return 0;
        }

        $index = get_option(self::SUBSCRIPTION_INDEX_OPTION, null);
        if (!is_array($index)) {
            $index = self::build_subscription_index();
        }

        return isset($index[$subscription_id]) ? absint($index[$subscription_id]) : 0;
    }

    /**
     * Build subscription id → user id map via user meta reads (WP APIs only).
     * Runs once when the index option is missing; subsequent lookups are option reads.
     *
     * @return array<string,int>
     */
    private static function build_subscription_index() {
        $index = [];
        $page = 1;

        do {
            $batch = get_users([
                'fields' => 'ID',
                'number' => 100,
                'paged'  => $page,
            ]);
            if (empty($batch)) {
                break;
            }
            foreach ($batch as $user_id) {
                $user_id = absint($user_id);
                $sid = sanitize_text_field((string) get_user_meta($user_id, '_flosc_subscription_id', true));
                if ($sid !== '') {
                    $index[$sid] = $user_id;
                }
            }
            $page++;
        } while (count($batch) === 100);

        update_option(self::SUBSCRIPTION_INDEX_OPTION, $index, false);
        return $index;
    }
}
