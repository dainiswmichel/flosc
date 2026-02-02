<?php
/**
 * FLOSC ClickBank Payment Provider
 * 
 * Handles payments via ClickBank marketplace.
 * Uses ClickBank IPN (Instant Payment Notification) for payment verification.
 * 
 * @since 5.0.9
 * @updated 6.0.1 - Fixed sha256 bug, added idempotency, improved security
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
        $vendor = $this->get_setting('vendor_nickname', 'SANDBOXVENDOR');
        $secret = $this->get_setting('secret_key', 'SANDBOX123456');
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
            'vendor_nickname' => [
                'type' => 'text',
                'label' => 'Vendor Nickname',
                'placeholder' => 'Your ClickBank vendor nickname',
                'description' => 'Your ClickBank account nickname (found in Account Settings)',
                'default' => 'SANDBOXVENDOR',
            ],
            'secret_key' => [
                'type' => 'password',
                'label' => 'Secret Key',
                'placeholder' => 'Your ClickBank secret key',
                'description' => 'Found in Account Settings → My Site → Advanced Tools → Secret Key',
                'default' => 'SANDBOX123456',
            ],
            'product_sku' => [
                'type' => 'text',
                'label' => 'Product SKU',
                'placeholder' => 'e.g., LESAEPPRO',
                'description' => 'The ClickBank product item number to match for access grants',
                'default' => 'SANDBOXPRODUCT',
            ],
            'access_level' => [
                'type' => 'select',
                'label' => 'Access Level on Purchase',
                'options' => [
                    'basic' => 'Basic',
                    'pro' => 'Pro',
                    'premium' => 'Premium',
                    'full' => 'Full Access (Content Phase)',
                ],
                'default' => 'full',
                'description' => 'What access level to grant when a ClickBank purchase is verified',
            ],
        ];
    }
    
    /**
     * Get client-side config (for checkout buttons, etc.)
     */
    public function get_client_config() {
        $mode = $this->get_setting('mode', 'sandbox');
        $vendor = $this->get_setting('vendor_nickname', 'SANDBOXVENDOR');
        $product = $this->get_setting('product_sku', 'SANDBOXPRODUCT');
        
        $hop_link = $mode === 'sandbox' 
            ? "https://sandbox.clickbank.net/checkout/order/hop.php?vendor={$vendor}&product={$product}"
            : "http://{AFF_ID}.{$vendor}.hop.clickbank.net/?tid={$product}";
        
        return [
            'enabled' => (bool) $this->get_setting('enabled', false),
            'mode' => $mode,
            'vendor' => $vendor,
            'product' => $product,
            'hop_link_template' => $hop_link,
            'checkout_url' => $this->get_checkout_url(),
        ];
    }
    
    /**
     * Get ClickBank checkout URL
     */
    public function get_checkout_url($affiliate_id = null) {
        $vendor = $this->get_setting('vendor_nickname', 'SANDBOXVENDOR');
        $product = $this->get_setting('product_sku', 'SANDBOXPRODUCT');
        $mode = $this->get_setting('mode', 'sandbox');
        
        if ($mode === 'sandbox') {
            return "https://sandbox.clickbank.net/checkout/order/hop.php?vendor={$vendor}&product={$product}";
        }
        
        $aff = $affiliate_id ?: 'AFFILIATE';
        return "http://{$aff}.{$vendor}.hop.clickbank.net/?tid={$product}";
    }
    
    /**
     * Handle ClickBank IPN webhook
     * 
     * v6.0.1: Added idempotency, fixed signature verification, improved logging
     */
    public function handle_webhook($payload, $headers = []) {
        $params = [];
        parse_str($payload, $params);
        
        // Log IPN receipt
        FLOSC_Logger::info('ClickBank IPN received', [
            'transaction_type' => $params['ctransaction'] ?? 'unknown',
            'vendor' => $params['cvendor'] ?? 'unknown',
            'receipt' => $params['ctransreceipt'] ?? 'unknown',
        ]);
        
        // Validate required fields
        $required = ['ctransaction', 'ctransreceipt', 'cvendor', 'ccustname', 'ccustemail'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                FLOSC_Logger::warning('ClickBank IPN missing required field', ['field' => $field]);
                return new WP_Error('missing_field', "Missing required field: {$field}", ['status' => 400]);
            }
        }
        
        // Verify vendor matches
        $our_vendor = $this->get_setting('vendor_nickname', 'SANDBOXVENDOR');
        if (strtolower($params['cvendor']) !== strtolower($our_vendor)) {
            FLOSC_Logger::security('ClickBank IPN vendor mismatch', [
                'received' => $params['cvendor'],
                'expected' => $our_vendor,
            ]);
            return new WP_Error('vendor_mismatch', 'Vendor does not match', ['status' => 400]);
        }
        
        // Verify signature
        $secret_key = $this->get_setting('secret_key', 'SANDBOX123456');
        if (!empty($secret_key)) {
            if (!$this->verify_ipn_signature($params, $secret_key)) {
                FLOSC_Logger::security('ClickBank IPN signature verification failed');
                return new WP_Error('invalid_signature', 'Invalid IPN signature', ['status' => 401]);
            }
        }
        
        // v6.0.1: Idempotency check - prevent replay attacks
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $idempotency_key = 'flosc_cb_' . md5($receipt . ($params['ctransaction'] ?? ''));
        
        if (get_transient($idempotency_key)) {
            FLOSC_Logger::info('ClickBank IPN already processed (idempotency)', ['receipt' => $receipt]);
            return new WP_REST_Response([
                'success' => true,
                'already_processed' => true,
                'receipt' => $receipt,
            ], 200);
        }
        
        // Process transaction
        $transaction_type = strtoupper($params['ctransaction'] ?? '');
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
                $result = $this->handle_refund($params);
                break;
                
            case 'REBILL':
            case 'TEST_REBILL':
                $result = $this->handle_rebill($params);
                break;
                
            case 'CANCEL-REBILL':
            case 'UNCANCEL-REBILL':
                $result = $this->handle_subscription_change($params);
                break;
                
            default:
                FLOSC_Logger::info('ClickBank IPN ignored transaction type', ['type' => $transaction_type]);
                return new WP_REST_Response(['received' => true, 'action' => 'ignored'], 200);
        }
        
        // Mark as processed (24 hour TTL)
        if (!is_wp_error($result)) {
            set_transient($idempotency_key, time(), DAY_IN_SECONDS);
        }
        
        return $result;
    }
    
    /**
     * Verify ClickBank IPN signature
     * 
     * v6.0.1: CRITICAL FIX - sha256() is NOT a valid PHP function
     * Must use hash('sha256', ...) instead
     */
    private function verify_ipn_signature($params, $secret_key) {
        if (empty($params['cverify'])) {
            $mode = $this->get_setting('mode', 'sandbox');
            if ($mode === 'sandbox') {
                FLOSC_Logger::debug('ClickBank IPN signature skipped (sandbox mode)');
                return true;
            }
            FLOSC_Logger::warning('ClickBank IPN missing signature in live mode');
            return false;
        }
        
        // Build the string to hash
        $fields = $params;
        unset($fields['cverify']);
        ksort($fields);
        
        $string_to_hash = '';
        foreach ($fields as $key => $value) {
            $string_to_hash .= $value . '|';
        }
        $string_to_hash .= $secret_key;
        
        // v6.0.1: CRITICAL FIX - Use hash() function, NOT sha256()
        $expected = strtoupper(hash('sha256', $string_to_hash));
        $received = strtoupper($params['cverify']);
        
        $valid = hash_equals($expected, $received);
        
        if (!$valid) {
            FLOSC_Logger::debug('ClickBank signature mismatch', [
                'expected_prefix' => substr($expected, 0, 8) . '...',
                'received_prefix' => substr($received, 0, 8) . '...',
            ]);
        }
        
        return $valid;
    }
    
    /**
     * Handle successful sale
     * 
     * v6.0.1: Improved email validation, case-insensitive check, user notification
     */
    private function handle_sale($params) {
        $email = sanitize_email($params['ccustemail']);
        $name = sanitize_text_field($params['ccustname']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $product = sanitize_text_field($params['cproditem'] ?? $params['cproduct'] ?? '');
        $amount = floatval($params['ctransamount'] ?? 0);
        $affiliate = sanitize_text_field($params['caffitid'] ?? '');
        
        // v6.0.1: Validate email before proceeding
        if (!is_email($email)) {
            FLOSC_Logger::error('ClickBank sale invalid email', ['email' => $email, 'receipt' => $receipt]);
            return new WP_Error('invalid_email', 'Invalid customer email', ['status' => 400]);
        }
        
        // v6.0.1: Case-insensitive email lookup
        $user = $this->find_user_by_email_insensitive($email);
        $is_new_user = false;
        
        if (!$user) {
            $user_id = $this->create_user_from_purchase($email, $name);
            
            if (is_wp_error($user_id)) {
                FLOSC_Logger::error('ClickBank user creation failed', [
                    'email' => $email,
                    'error' => $user_id->get_error_message(),
                ]);
                return $user_id;
            }
            
            $user = get_user_by('ID', $user_id);
            $is_new_user = true;
        }
        
        $user_id = $user->ID;
        
        // Grant access
        $access_level = $this->get_setting('access_level', 'full');
        update_user_meta($user_id, '_flosc_access_level', $access_level);
        update_user_meta($user_id, '_flosc_clickbank_receipt', $receipt);
        update_user_meta($user_id, '_flosc_clickbank_product', $product);
        update_user_meta($user_id, '_flosc_purchase_date', current_time('mysql'));
        update_user_meta($user_id, '_flosc_purchase_amount', $amount);
        update_user_meta($user_id, '_flosc_payment_provider', 'clickbank');
        update_user_meta($user_id, '_flosc_purchased', true);
        
        if (!empty($affiliate)) {
            update_user_meta($user_id, '_flosc_affiliate_id', $affiliate);
        }
        
        FLOSC_Logger::payment('sale_completed', 'clickbank', $user_id, $amount, [
            'receipt' => $receipt,
            'product' => $product,
            'affiliate' => $affiliate,
            'is_new_user' => $is_new_user,
            'access_level' => $access_level,
        ]);
        
        do_action('flosc_clickbank_sale', $user_id, $params);
        do_action('flosc_purchase_completed', $user_id, null, 'clickbank', [
            'receipt' => $receipt,
            'amount' => $amount,
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'user_id' => $user_id,
            'access_level' => $access_level,
            'receipt' => $receipt,
            'is_new_user' => $is_new_user,
        ], 200);
    }
    
    /**
     * Find user by email (case-insensitive)
     */
    private function find_user_by_email_insensitive($email) {
        $user = get_user_by('email', $email);
        if ($user) {
            return $user;
        }
        
        global $wpdb;
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->users} WHERE LOWER(user_email) = LOWER(%s) LIMIT 1",
            $email
        ));
        
        return $user_id ? get_user_by('ID', $user_id) : null;
    }
    
    /**
     * Create user from purchase with notification
     */
    private function create_user_from_purchase($email, $name) {
        $base_username = sanitize_user(strtolower(explode('@', $email)[0]));
        $username = $this->get_unique_username($base_username);
        $password = wp_generate_password(16, true, true);
        
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        $name_parts = explode(' ', trim($name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
        
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $name,
            'first_name' => $first_name,
            'last_name' => $last_name,
        ]);
        
        update_user_meta($user_id, '_flosc_created_via', 'clickbank');
        update_user_meta($user_id, '_flosc_created_at', current_time('mysql'));
        
        $this->send_welcome_email($user_id, $email, $name, $username, $password);
        
        FLOSC_Logger::info('ClickBank user created', [
            'user_id' => $user_id,
            'username' => $username,
        ]);
        
        return $user_id;
    }
    
    /**
     * Send welcome email with login credentials
     */
    private function send_welcome_email($user_id, $email, $name, $username, $password) {
        $product_name = get_option('flosc_product_name', 'Our Product');
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        
        $subject = sprintf(__('Welcome to %s - Your Account Details', 'flosc'), $product_name);
        
        $message = sprintf(
            __("Hi %s,\n\nThank you for your purchase!\n\nYour account has been created:\n\nUsername: %s\nPassword: %s\nLogin: %s\n\nPlease change your password after first login.\n\nBest regards,\n%s", 'flosc'),
            $name,
            $username,
            $password,
            $login_url,
            $site_name
        );
        
        $sent = wp_mail($email, $subject, $message);
        
        if (!$sent) {
            FLOSC_Logger::warning('ClickBank welcome email failed', [
                'user_id' => $user_id,
            ]);
        }
        
        return $sent;
    }
    
    /**
     * Get unique username (random suffix, not sequential)
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
        
        if (username_exists($username)) {
            $username = $base . '_' . substr(flosc_generate_uuid4(), 0, 8);
        }
        
        return $username;
    }
    
    /**
     * Handle refund/chargeback
     */
    private function handle_refund($params) {
        $email = sanitize_email($params['ccustemail']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        
        $user = $this->find_user_by_email_insensitive($email);
        
        if ($user) {
            update_user_meta($user->ID, '_flosc_access_level', 'free');
            update_user_meta($user->ID, '_flosc_refunded', true);
            update_user_meta($user->ID, '_flosc_refund_date', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_purchased', false);
            
            FLOSC_Logger::payment('refund_processed', 'clickbank', $user->ID, null, [
                'receipt' => $receipt,
                'reason' => $params['ctransaction'] ?? 'unknown',
            ]);
            
            do_action('flosc_clickbank_refund', $user->ID, $params);
        } else {
            FLOSC_Logger::warning('ClickBank refund user not found', ['email' => $email]);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => 'access_revoked'], 200);
    }
    
    /**
     * Handle subscription rebill
     */
    private function handle_rebill($params) {
        $email = sanitize_email($params['ccustemail']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $amount = floatval($params['ctransamount'] ?? 0);
        
        $user = $this->find_user_by_email_insensitive($email);
        
        if ($user) {
            update_user_meta($user->ID, '_flosc_last_rebill', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_clickbank_receipt', $receipt);
            
            FLOSC_Logger::payment('rebill_processed', 'clickbank', $user->ID, $amount, [
                'receipt' => $receipt,
            ]);
            
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
        
        $user = $this->find_user_by_email_insensitive($email);
        
        if ($user) {
            if ($transaction_type === 'CANCEL-REBILL') {
                update_user_meta($user->ID, '_flosc_subscription_status', 'cancelled');
                update_user_meta($user->ID, '_flosc_cancel_date', current_time('mysql'));
            } else {
                update_user_meta($user->ID, '_flosc_subscription_status', 'active');
                delete_user_meta($user->ID, '_flosc_cancel_date');
            }
            
            FLOSC_Logger::info('ClickBank subscription change', [
                'user_id' => $user->ID,
                'action' => $transaction_type,
            ]);
            
            do_action('flosc_clickbank_subscription_change', $user->ID, $transaction_type, $params);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => $transaction_type], 200);
    }
    
    /**
     * Create purchase intent (for frontend)
     */
    public function create_intent($user_id, $offer, $params = []) {
        $affiliate_id = $params['affiliate_id'] ?? null;
        
        return [
            'checkout_url' => $this->get_checkout_url($affiliate_id),
            'vendor' => $this->get_setting('vendor_nickname'),
            'product' => $this->get_setting('product_sku'),
        ];
    }
    
    /**
     * Complete purchase (called after redirect back from ClickBank)
     */
    public function complete_purchase($user_id, $intent_id, $params = []) {
        $receipt = $params['cbreceipt'] ?? '';
        
        if (empty($receipt)) {
            return new WP_Error('no_receipt', 'No ClickBank receipt provided');
        }
        
        $existing = get_users([
            'meta_key' => '_flosc_clickbank_receipt',
            'meta_value' => $receipt,
            'number' => 1,
        ]);
        
        if (!empty($existing)) {
            return [
                'success' => true,
                'already_processed' => true,
                'user_id' => $existing[0]->ID,
            ];
        }
        
        $access_level = $this->get_setting('access_level', 'full');
        update_user_meta($user_id, '_flosc_access_level', $access_level);
        update_user_meta($user_id, '_flosc_clickbank_receipt', $receipt);
        update_user_meta($user_id, '_flosc_purchased', true);
        
        FLOSC_Logger::info('ClickBank manual purchase completion', [
            'user_id' => $user_id,
            'receipt' => $receipt,
        ]);
        
        return [
            'success' => true,
            'access_level' => $access_level,
        ];
    }
}
