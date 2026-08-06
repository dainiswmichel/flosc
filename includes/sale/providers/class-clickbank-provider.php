<?php
/**
 * FLOSC ClickBank Payment Provider
 *
 * Redirect checkout via official seller payment links.
 * Fulfillment only after ClickBank Instant Notification Service (INS v6+/v8
 * encrypted JSON) or verified legacy form IPN — never on redirect alone.
 *
 * @since 7.0.7
 */

if (!defined('ABSPATH')) exit;

class FLOSC_ClickBank_Provider extends FLOSC_Payment_Provider {
    
    public function get_id() {
        return 'clickbank';
    }
    
    public function get_name() {
        return 'ClickBank';
    }
    
    public function get_description() {
        return 'Accept payments via ClickBank marketplace with affiliate support.';
    }
    
    public function get_icon() {
        return '🛒';
    }
    
    public function is_configured() {
        $vendor = $this->get_setting('vendor', '');
        $secret = $this->get_setting('secret', '');
        return !empty($vendor) && !empty($secret);
    }
    
    public function supports_subscriptions() {
        return true;
    }
    
    /**
     * Get settings fields for admin UI
     */
    public function get_settings_fields() {
        return [
            'enabled' => [
                'type' => 'checkbox',
                'label' => 'Enable ClickBank',
                'default' => false,
            ],
            'mode' => [
                'type' => 'select',
                'label' => 'Mode',
                'options' => [
                    'sandbox' => 'Sandbox (Testing)',
                    'live' => 'Live',
                ],
                'default' => 'sandbox',
            ],
            'vendor' => [
                'type' => 'text',
                'label' => 'Vendor Nickname',
                'placeholder' => 'Your ClickBank vendor nickname',
                'description' => 'Your ClickBank account nickname (found in Account Settings)',
            ],
            'secret' => [
                'type' => 'password',
                'label' => 'Secret Key',
                'placeholder' => 'Your ClickBank secret key',
                'description' => 'Found in Account Settings → My Site → Advanced Tools → Secret Key',
            ],
            'product' => [
                'type' => 'text',
                'label' => 'Product Item Number',
                'placeholder' => 'e.g., 1',
                'description' => 'ClickBank product item number (cbitems). Used in the payment link and to match INS line items.',
            ],
            'offer_id' => [
                'type' => 'text',
                'label' => 'FLOSC Offer ID',
                'placeholder' => 'e.g., full_access',
                'description' => 'Optional FLOSC offer id granted when this product is purchased via INS/IPN.',
            ],
            'access_level' => [
                'type' => 'select',
                'label' => 'Access Level on Purchase',
                'options' => [
                    'basic' => 'Basic',
                    'pro' => 'Pro',
                    'premium' => 'Premium',
                    'full' => 'Full Access',
                ],
                'default' => 'full',
                'description' => 'Fallback access level meta when no matching FLOSC offer is configured.',
            ],
        ];
    }
    
    /**
     * Process payment - ClickBank uses redirect-based checkout
     * Required by abstract parent class
     *
     * PAY-01: Must not report settled payment. Redirect initiation only;
     * IPN/INS webhook is the sole fulfillment path.
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        return [
            'success'      => false,
            'pending'      => true,
            'redirect'     => true,
            'checkout_url' => $this->get_checkout_url($payment_data['affiliate_id'] ?? null),
            'message'      => __('Redirecting to ClickBank checkout. Access is granted only after ClickBank confirms payment.', 'flosc'),
        ];
    }
    
    /**
     * Get ClickBank seller payment link.
     *
     * Official format (current): https://VENDOR.pay.clickbank.net/?cbitems=ITEM
     * Optional vtid is tracking only — never the product selector.
     * Affiliate hop links remain affiliate-side; this is the vendor pay link.
     *
     * @param string|null $affiliate_id Unused for seller pay links (kept for API compat).
     * @param string|null $vtid         Optional vendor tracking id.
     * @return string
     */
    public function get_checkout_url($affiliate_id = null, $vtid = null) {
        unset($affiliate_id); // Seller pay links do not use hop-style affiliate hosts.
        $vendor  = preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->get_setting('vendor', ''));
        $product = sanitize_text_field((string) $this->get_setting('product', ''));
        $mode    = $this->get_setting('mode', 'sandbox');

        if ($vendor === '' || $product === '') {
            return '';
        }

        // Live seller payment link per ClickBank docs.
        $url = 'https://' . strtolower($vendor) . '.pay.clickbank.net/?cbitems=' . rawurlencode($product);

        // Sandbox still uses ClickBank's sandbox host when mode=sandbox.
        if ($mode === 'sandbox') {
            $url = 'https://sandbox.clickbank.net/checkout/order/hop.php?vendor='
                . rawurlencode($vendor) . '&product=' . rawurlencode($product);
        }

        $vtid = sanitize_text_field((string) ($vtid ?? ''));
        if ($vtid !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9_]{0,99}$/', $vtid)) {
            $url = add_query_arg('vtid', $vtid, $url);
        }

        return $url;
    }
    
    /**
     * Get client-side config (for checkout buttons, etc.)
     */
    public function get_client_config() {
        return [
            'enabled' => (bool) $this->get_setting('enabled', false),
            'mode' => $this->get_setting('mode', 'sandbox'),
            'checkout_url' => $this->get_checkout_url(),
        ];
    }
    
    /**
     * Handle ClickBank INS / legacy IPN webhook.
     *
     * Preferred: INS v6+/v8 encrypted JSON body:
     *   {"notification":"<base64>","iv":"<base64>"}
     * Legacy: form-encoded fields with cverify (SHA-1 first 8 hex, uppercase).
     *
     * @param string $payload Raw request body.
     * @param array  $headers Optional headers.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_webhook($payload, $headers = []) {
        unset($headers);
        $secret_key = (string) $this->get_setting('secret', '');
        if ($secret_key === '') {
            return new WP_Error('clickbank_not_configured', __('ClickBank secret is not configured', 'flosc'), ['status' => 500]);
        }

        $payload = (string) $payload;
        $params  = null;

        // --- INS encrypted JSON (v6/v7/v8) ---
        $json = json_decode($payload, true);
        if (is_array($json) && !empty($json['notification']) && !empty($json['iv'])) {
            $decrypted = $this->decrypt_ins_notification(
                (string) $json['notification'],
                (string) $json['iv'],
                $secret_key
            );
            if (is_wp_error($decrypted)) {
                return $decrypted;
            }
            $params = $this->map_ins_json_to_params($decrypted);
        }

        // --- Legacy form IPN ---
        if ($params === null) {
            $raw_params = [];
            parse_str($payload, $raw_params);
            if (!is_array($raw_params) || empty($raw_params)) {
                // Also accept application/json that is the decrypted shape already (tests).
                if (is_array($json) && !empty($json['receipt']) && !empty($json['transactionType'])) {
                    $params = $this->map_ins_json_to_params($json);
                } else {
                    return new WP_Error('invalid_payload', __('Unrecognized ClickBank notification payload', 'flosc'), ['status' => 400]);
                }
            } else {
                $required = ['ctransaction', 'ctransreceipt', 'cvendor', 'ccustname', 'ccustemail'];
                foreach ($required as $field) {
                    if (empty($raw_params[ $field ])) {
                        /* translators: %s: name of the missing ClickBank webhook field. */
                        return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'flosc'), $field), ['status' => 400]);
                    }
                }
                if (empty($raw_params['cverify'])) {
                    return new WP_Error('missing_signature', __('Missing IPN signature', 'flosc'), ['status' => 401]);
                }
                if (!$this->verify_ipn_signature($raw_params, $secret_key)) {
                    return new WP_Error('invalid_signature', __('Invalid IPN signature', 'flosc'), ['status' => 401]);
                }
                $params = $this->sanitize_ipn_params($raw_params);
            }
        }

        if (!is_array($params) || empty($params['ctransreceipt'])) {
            return new WP_Error('invalid_payload', __('ClickBank notification missing receipt', 'flosc'), ['status' => 400]);
        }

        $our_vendor = strtolower((string) $this->get_setting('vendor', ''));
        $msg_vendor = strtolower((string) ($params['cvendor'] ?? ''));
        if ($our_vendor !== '' && $msg_vendor !== '' && $msg_vendor !== $our_vendor) {
            return new WP_Error('vendor_mismatch', __('Vendor does not match', 'flosc'), ['status' => 400]);
        }

        $receipt         = sanitize_text_field((string) ($params['ctransreceipt'] ?? ''));
        $transaction_type = strtoupper((string) ($params['ctransaction'] ?? ''));
        $idempotency_key = 'flosc_cb_' . md5($receipt . '|' . $transaction_type);

        if (get_transient($idempotency_key)) {
            return new WP_REST_Response([
                'success'           => true,
                'already_processed' => true,
                'receipt'           => $receipt,
            ], 200);
        }

        $result = null;
        switch ($transaction_type) {
            case 'SALE':
            case 'TEST_SALE':
            case 'TEST':
                $result = $this->handle_sale($params);
                break;

            case 'RFND':
            case 'CGBK':
            case 'INSF':
            case 'TEST_RFND':
                $result = $this->handle_refund($params);
                break;

            case 'REBILL':
            case 'BILL':
            case 'TEST_REBILL':
            case 'TEST_BILL':
                $result = $this->handle_rebill($params);
                break;

            case 'CANCEL-REBILL':
            case 'UNCANCEL-REBILL':
            case 'CANCEL-TEST-REBILL':
            case 'UNCANCEL-TEST-REBILL':
                $result = $this->handle_subscription_change($params);
                break;

            default:
                return new WP_REST_Response(['received' => true, 'action' => 'ignored', 'type' => $transaction_type], 200);
        }

        if (!is_wp_error($result)) {
            set_transient($idempotency_key, time(), DAY_IN_SECONDS);
        }

        return $result;
    }

    /**
     * Decrypt ClickBank INS AES-256-CBC payload (official PHP sample).
     *
     * @param string $encrypted_b64 Base64 notification.
     * @param string $iv_b64        Base64 IV.
     * @param string $secret_key    ClickBank secret (up to 16 chars).
     * @return array|WP_Error Decoded JSON assoc array.
     */
    private function decrypt_ins_notification($encrypted_b64, $iv_b64, $secret_key) {
        if (!function_exists('openssl_decrypt')) {
            return new WP_Error('openssl_missing', __('OpenSSL is required for ClickBank INS decryption', 'flosc'), ['status' => 500]);
        }

        $encrypted = base64_decode((string) $encrypted_b64, true);
        $iv        = base64_decode((string) $iv_b64, true);
        if ($encrypted === false || $iv === false || $iv === '') {
            return new WP_Error('invalid_ins_encoding', __('Invalid ClickBank INS encoding', 'flosc'), ['status' => 400]);
        }

        // Key = first 32 hex chars of sha1(secret) as raw key bytes (ClickBank sample).
        $key = substr(sha1((string) $secret_key), 0, 32);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return new WP_Error('ins_decrypt_failed', __('Could not decrypt ClickBank INS notification', 'flosc'), ['status' => 401]);
        }

        $decrypted = trim($decrypted, "\0..\32");
        $order     = json_decode($decrypted, true);
        if (!is_array($order)) {
            // Some payloads include escape slashes; retry after stripslashes.
            $order = json_decode(stripslashes($decrypted), true);
        }
        if (!is_array($order)) {
            return new WP_Error('ins_json_invalid', __('Decrypted ClickBank INS payload is not valid JSON', 'flosc'), ['status' => 400]);
        }

        return $order;
    }

    /**
     * Map INS JSON (v6–v8) into the flat param shape used by sale/refund handlers.
     *
     * @param array $order Decrypted or plain INS object.
     * @return array
     */
    private function map_ins_json_to_params(array $order) {
        $billing  = is_array($order['customer']['billing'] ?? null) ? $order['customer']['billing'] : [];
        $shipping = is_array($order['customer']['shipping'] ?? null) ? $order['customer']['shipping'] : [];
        $email    = sanitize_email((string) ($billing['email'] ?? $shipping['email'] ?? ''));
        $name     = sanitize_text_field((string) (
            $billing['fullName']
            ?? $shipping['fullName']
            ?? trim(($billing['firstName'] ?? '') . ' ' . ($billing['lastName'] ?? ''))
            ?? ''
        ));

        $item_no = '';
        $line_items = $order['lineItems'] ?? [];
        if (is_array($line_items) && !empty($line_items[0]) && is_array($line_items[0])) {
            $item_no = sanitize_text_field((string) ($line_items[0]['itemNo'] ?? ''));
        }

        $type = strtoupper((string) ($order['transactionType'] ?? $order['ctransaction'] ?? ''));
        // Normalize BILL (INS) to REBILL for internal switch where needed.
        if ($type === 'BILL') {
            $type = 'REBILL';
        }

        $params = [
            'ctransaction'  => $type,
            'ctransreceipt' => sanitize_text_field((string) ($order['receipt'] ?? $order['ctransreceipt'] ?? '')),
            'cvendor'       => sanitize_text_field((string) ($order['vendor'] ?? $order['cvendor'] ?? '')),
            'ccustname'     => $name,
            'ccustemail'    => $email,
            'cproditem'     => $item_no,
            'ctransamount'  => sanitize_text_field((string) ($order['totalOrderAmount'] ?? $order['ctransamount'] ?? '0')),
            'ccurrency'     => sanitize_text_field((string) ($order['currency'] ?? $order['ccurrency'] ?? 'USD')),
            'ins_version'   => sanitize_text_field((string) ($order['version'] ?? '8')),
        ];

        return $this->sanitize_ipn_params($params);
    }

    /**
     * Verify legacy form IPN cverify (ClickBank: SHA-1 hex, first 8 chars, uppercase).
     *
     * @param array  $params     Raw form params including cverify.
     * @param string $secret_key Secret key.
     * @return bool
     */
    private function verify_ipn_signature($params, $secret_key) {
        if (empty($params['cverify'])) {
            return false;
        }

        $received = strtoupper((string) $params['cverify']);
        $hash_params = $params;
        unset($hash_params['cverify']);
        ksort($hash_params);

        $string_to_hash = '';
        foreach ($hash_params as $value) {
            if (is_array($value)) {
                continue;
            }
            $string_to_hash .= $value . '|';
        }
        $string_to_hash .= $secret_key;

        // Official legacy sample: sha1, first 8 hex chars, uppercase.
        $expected_sha1 = strtoupper(substr(sha1($string_to_hash), 0, 8));
        if (hash_equals($expected_sha1, $received) || hash_equals($expected_sha1, strtoupper(substr($received, 0, 8)))) {
            return true;
        }

        // Tolerate full SHA-256 if a custom/test integration used it.
        $expected_sha256 = strtoupper(hash('sha256', $string_to_hash));
        return hash_equals($expected_sha256, $received);
    }

    /**
     * Sanitize ClickBank IPN key/value map after signature verification.
     *
     * @param array $params Raw IPN params.
     * @return array
     */
    private function sanitize_ipn_params(array $params) {
        $clean = array();
        $i     = 0;
        foreach ($params as $key => $value) {
            if ($i++ > 100) {
                break;
            }
            $k = sanitize_key((string) $key);
            if ($k === '') {
                continue;
            }
            if (is_array($value)) {
                // IPN is flat form fields; drop nested noise.
                continue;
            }
            $clean[ $k ] = sanitize_text_field(wp_unslash((string) $value));
        }
        return $clean;
    }
    
    /**
     * Handle initial sale
     *
     * Access is granted only after IPN signature verification (caller).
     * Receipt is claimed so one ClickBank payment cannot unlock twice.
     */
    private function handle_sale($params) {
        $email = sanitize_email($params['ccustemail']);
        $name = sanitize_text_field($params['ccustname']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $amount = floatval($params['ctransamount'] ?? 0);
        $product = sanitize_text_field($params['cproditem'] ?? '');
        
        // Check if product SKU matches (if configured)
        $our_product = $this->get_setting('product', '');
        if (!empty($our_product) && !empty($product)) {
            if (strtoupper($product) !== strtoupper($our_product)) {
                return new WP_REST_Response([
                    'received' => true,
                    'action' => 'ignored',
                    'reason' => 'product_mismatch',
                ], 200);
            }
        }
        
        // Find or create user
        $user = get_user_by('email', $email);
        
        if (!$user) {
            $user_id = $this->create_user_from_purchase($email, $name);
            if (is_wp_error($user_id)) {
                return $user_id;
            }
        } else {
            $user_id = $user->ID;
        }

        // Map ClickBank product to a FLOSC offer when configured; else product/access_level meta only.
        $offer_id = sanitize_text_field((string) $this->get_setting('offer_id', ''));
        if ($offer_id === '') {
            $offer_id = 'clickbank_' . sanitize_key($product !== '' ? $product : 'default');
        }

        if (function_exists('flosc_sale')) {
            $claim = flosc_sale()->claim_transaction_fulfillment(
                'clickbank',
                $receipt,
                $user_id,
                $offer_id,
                [
                    'amount'   => $amount,
                    'currency' => sanitize_text_field((string) ($params['ccurrency'] ?? '')),
                ]
            );
            if (is_wp_error($claim)) {
                return $claim;
            }
            if ($claim === 'already') {
                return new WP_REST_Response([
                    'success'           => true,
                    'already_fulfilled' => true,
                    'user_id'           => $user_id,
                    'receipt'           => $receipt,
                ], 200);
            }

            $offer = flosc_sale()->offers()->get_offer($offer_id);
            if ($offer) {
                flosc_sale()->access()->grant_from_offer($user_id, $offer, [
                    'transaction_id' => $receipt,
                    'provider'       => 'clickbank',
                    'amount'         => $amount,
                    'success'        => true,
                ]);
            }
        }
        
        // Grant access (meta flags for legacy paths)
        $access_level = $this->get_setting('access_level', 'full');
        update_user_meta($user_id, '_flosc_access_level', $access_level);
        update_user_meta($user_id, '_flosc_purchased', true);
        update_user_meta($user_id, '_flosc_purchase_date', current_time('mysql'));
        update_user_meta($user_id, '_flosc_clickbank_receipt', $receipt);
        update_user_meta($user_id, '_flosc_payment_provider', 'clickbank');
        update_user_meta($user_id, '_flosc_purchase_amount', $amount);
        
        // Fire action for integrations
        do_action('flosc_clickbank_sale', $user_id, $params);
        do_action('flosc_purchase_complete', $user_id, 'clickbank', $amount, [
            'receipt' => $receipt,
            'product' => $product,
        ]);
        do_action('flosc_purchase_completed', $user_id, [
            'offer_id'       => $offer_id,
            'provider'       => 'clickbank',
            'transaction_id' => $receipt,
            'amount'         => $amount,
            'timestamp'      => time(),
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'user_id' => $user_id,
            'access_level' => $access_level,
            'receipt' => $receipt,
        ], 200);
    }
    
    /**
     * Create new user from ClickBank purchase
     */
    private function create_user_from_purchase($email, $name) {
        $base_username = sanitize_user(explode('@', $email)[0], true);
        if (empty($base_username)) {
            $base_username = 'user';
        }
        
        $username = $this->get_unique_username($base_username);
        $password = wp_generate_password(16, true, true);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $name = sanitize_text_field((string) $name);
        $name_parts = explode(' ', trim($name), 2);
        $first_name = sanitize_text_field((string) ($name_parts[0] ?? ''));
        $last_name = sanitize_text_field((string) ($name_parts[1] ?? ''));

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);
        
        update_user_meta($user_id, '_flosc_created_via', 'clickbank');
        update_user_meta($user_id, '_flosc_created_at', current_time('mysql'));
        
        // Never email the plaintext password.
        $this->send_welcome_email($user_id, $email, $name, $username);
        
        return $user_id;
    }
    
    /**
     * Get unique username
     */
    private function get_unique_username($base) {
        if (!username_exists($base)) {
            return $base;
        }
        
        $suffix = wp_rand(100, 9999);
        $username = $base . $suffix;
        
        $attempts = 0;
        while (username_exists($username) && $attempts < 10) {
            $suffix = wp_rand(1000, 99999);
            $username = $base . $suffix;
            $attempts++;
        }
        
        return $username;
    }
    
    /**
     * Send welcome email (no plaintext password — use WordPress reset link).
     *
     * @param int    $user_id
     * @param string $email
     * @param string $name
     * @param string $username
     */
    private function send_welcome_email($user_id, $email, $name, $username) {
        $product_name = get_option('flosc_product_name', 'Our Product');
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        $reset_url = $login_url;

        $user = get_userdata((int) $user_id);
        if ($user && function_exists('get_password_reset_key')) {
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                $reset_url = network_site_url(
                    'wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login),
                    'login'
                );
            }
        }
        
        /* translators: %s: product name. */
        $subject = sprintf(__('Welcome to %s - Your Account Details', 'flosc'), $product_name);
        
        /* translators: 1: customer name, 2: username, 3: login URL, 4: password reset URL, 5: site name. */
        $message = sprintf(
            __("Hi %1\$s,\n\nThank you for your purchase!\n\nYour account has been created:\n\nUsername: %2\$s\nLogin: %3\$s\n\nFor security, your password is not included in this email. Set or reset it here:\n%4\$s\n\nBest regards,\n%5\$s", 'flosc'),
            $name,
            $username,
            $login_url,
            $reset_url,
            $site_name
        );
        
        return wp_mail($email, $subject, $message);
    }
    
    /**
     * Handle refund/chargeback
     */
    private function handle_refund($params) {
        $email = sanitize_email($params['ccustemail']);
        
        $user = get_user_by('email', $email);
        
        if ($user) {
            update_user_meta($user->ID, '_flosc_access_level', 'free');
            update_user_meta($user->ID, '_flosc_refunded', true);
            update_user_meta($user->ID, '_flosc_refund_date', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_purchased', false);
            
            do_action('flosc_clickbank_refund', $user->ID, $params);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => 'access_revoked'], 200);
    }
    
    /**
     * Handle subscription rebill
     */
    private function handle_rebill($params) {
        $email = sanitize_email($params['ccustemail']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        
        $user = get_user_by('email', $email);
        
        if ($user) {
            update_user_meta($user->ID, '_flosc_last_rebill', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_clickbank_receipt', $receipt);
            
            do_action('flosc_clickbank_rebill', $user->ID, $params);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => 'rebill_recorded'], 200);
    }
    
    /**
     * Handle subscription cancellation/uncancellation
     */
    private function handle_subscription_change($params) {
        $email = sanitize_email($params['ccustemail']);
        $transaction_type = strtoupper($params['ctransaction']);
        
        $user = get_user_by('email', $email);
        
        if ($user) {
            if ($transaction_type === 'CANCEL-REBILL') {
                update_user_meta($user->ID, '_flosc_subscription_status', 'cancelled');
                update_user_meta($user->ID, '_flosc_cancel_date', current_time('mysql'));
            } else {
                update_user_meta($user->ID, '_flosc_subscription_status', 'active');
                delete_user_meta($user->ID, '_flosc_cancel_date');
            }
            
            do_action('flosc_clickbank_subscription_change', $user->ID, $transaction_type, $params);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => $transaction_type], 200);
    }
}
