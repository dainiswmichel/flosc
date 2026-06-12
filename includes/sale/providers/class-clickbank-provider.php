<?php
/**
 * FLOSC ClickBank Payment Provider
 * 
 * Handles payments via ClickBank marketplace.
 * Uses ClickBank IPN (Instant Payment Notification) for payment verification.
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
                'label' => 'Product SKU',
                'placeholder' => 'e.g., YOURPRODUCT',
                'description' => 'The ClickBank product item number to match for access grants',
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
                'description' => 'What access level to grant when a ClickBank purchase is verified',
            ],
        ];
    }
    
    /**
     * Process payment - ClickBank uses redirect-based checkout
     * Required by abstract parent class
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        // ClickBank uses redirect to their checkout page, not direct processing
        // User is redirected to ClickBank, then IPN webhook handles fulfillment
        return [
            'success' => true,
            'redirect' => true,
            'checkout_url' => $this->get_checkout_url($payment_data['affiliate_id'] ?? null),
            'message' => 'Redirecting to ClickBank checkout...',
        ];
    }
    
    /**
     * Get ClickBank checkout URL
     */
    public function get_checkout_url($affiliate_id = null) {
        $vendor = $this->get_setting('vendor', '');
        $product = $this->get_setting('product', '');
        $mode = $this->get_setting('mode', 'sandbox');
        
        if ($mode === 'sandbox') {
            return "https://sandbox.clickbank.net/checkout/order/hop.php?vendor={$vendor}&product={$product}";
        }
        
        $aff = $affiliate_id ?: 'AFFILIATE';
        return "http://{$aff}.{$vendor}.hop.clickbank.net/?tid={$product}";
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
     * Handle ClickBank IPN webhook
     */
    public function handle_webhook($payload, $headers = []) {
        $params = [];
        parse_str($payload, $params);
        
        // Validate required fields
        $required = ['ctransaction', 'ctransreceipt', 'cvendor', 'ccustname', 'ccustemail'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                /* translators: %s: name of the missing ClickBank webhook field. */
                return new WP_Error('missing_field', sprintf(__('Missing required field: %s', 'flosc'), $field), ['status' => 400]);
            }
        }
        
        // Verify vendor matches
        $our_vendor = $this->get_setting('vendor', '');
        if (strtolower($params['cvendor']) !== strtolower($our_vendor)) {
            return new WP_Error('vendor_mismatch', __('Vendor does not match', 'flosc'), ['status' => 400]);
        }
        
        // Mandatory signature verification before any account or access side effects.
        $secret_key = $this->get_setting('secret', '');
        if (empty($secret_key)) {
            return new WP_Error('clickbank_not_configured', __('ClickBank secret is not configured', 'flosc'), ['status' => 500]);
        }

        if (empty($params['cverify'])) {
            return new WP_Error('missing_signature', __('Missing IPN signature', 'flosc'), ['status' => 401]);
        }

        if (!$this->verify_ipn_signature($params, $secret_key)) {
            return new WP_Error('invalid_signature', __('Invalid IPN signature', 'flosc'), ['status' => 401]);
        }
        
        // Idempotency check - prevent duplicate processing
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $idempotency_key = 'flosc_cb_' . md5($receipt . ($params['ctransaction'] ?? ''));
        
        if (get_transient($idempotency_key)) {
            return new WP_REST_Response([
                'success' => true,
                'already_processed' => true,
                'receipt' => $receipt,
            ], 200);
        }
        
        // Process transaction based on type
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
                return new WP_REST_Response(['received' => true, 'action' => 'ignored'], 200);
        }
        
        // Mark as processed (24 hour idempotency window)
        if (!is_wp_error($result)) {
            set_transient($idempotency_key, time(), DAY_IN_SECONDS);
        }
        
        return $result;
    }
    
    /**
     * Verify ClickBank IPN signature
     * Uses hash('sha256', ...) - NOT sha256() which doesn't exist in PHP
     */
    private function verify_ipn_signature($params, $secret_key) {
        if (empty($params['cverify'])) {
            return false;
        }
        
        $received_hash = $params['cverify'];
        
        // Build string to hash (all params except cverify, in alphabetical order)
        $hash_params = $params;
        unset($hash_params['cverify']);
        ksort($hash_params);
        
        $string_to_hash = '';
        foreach ($hash_params as $key => $value) {
            $string_to_hash .= $value . '|';
        }
        $string_to_hash .= $secret_key;
        
        $expected = strtoupper(hash('sha256', $string_to_hash));
        
        return hash_equals($expected, strtoupper($received_hash));
    }
    
    /**
     * Handle initial sale
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
        
        // Grant access
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
     * Send welcome email with login credentials
     */
    private function send_welcome_email($user_id, $email, $name, $username, $password) {
        $product_name = get_option('flosc_product_name', 'Our Product');
        $site_name = get_bloginfo('name');
        $login_url = wp_login_url();
        
        /* translators: %s: product name. */
        $subject = sprintf(__('Welcome to %s - Your Account Details', 'flosc'), $product_name);
        
        /* translators: 1: customer name, 2: username, 3: password, 4: login URL, 5: site name. */
        $message = sprintf(
            __("Hi %1\$s,\n\nThank you for your purchase!\n\nYour account has been created:\n\nUsername: %2\$s\nPassword: %3\$s\nLogin: %4\$s\n\nPlease change your password after first login.\n\nBest regards,\n%5\$s", 'flosc'),
            $name,
            $username,
            $password,
            $login_url,
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
