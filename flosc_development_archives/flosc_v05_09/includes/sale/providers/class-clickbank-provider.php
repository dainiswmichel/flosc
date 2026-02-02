<?php
/**
 * FLOSC ClickBank Payment Provider
 * 
 * Handles payments via ClickBank marketplace.
 * Uses ClickBank IPN (Instant Payment Notification) for payment verification.
 * 
 * @since 5.0.9
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
        return true; // ClickBank supports recurring products
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
                'default' => 'SANDBOXVENDOR', // v05_09: Sandbox default
            ],
            'secret_key' => [
                'type' => 'password',
                'label' => 'Secret Key',
                'placeholder' => 'Your ClickBank secret key',
                'description' => 'Found in Account Settings → My Site → Advanced Tools → Secret Key',
                'default' => 'SANDBOX123456', // v05_09: Sandbox default
            ],
            'product_sku' => [
                'type' => 'text',
                'label' => 'Product SKU',
                'placeholder' => 'e.g., LESAEPPRO',
                'description' => 'The ClickBank product item number to match for access grants',
                'default' => 'SANDBOXPRODUCT', // v05_09: Sandbox default
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
                'default' => 'full', // v05_09: Grants access to content phase
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
        
        // ClickBank hop link format
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
            // Sandbox URL for testing
            return "https://sandbox.clickbank.net/checkout/order/hop.php?vendor={$vendor}&product={$product}";
        }
        
        // Live hoplink - affiliate ID optional
        $aff = $affiliate_id ?: 'AFFILIATE';
        return "http://{$aff}.{$vendor}.hop.clickbank.net/?tid={$product}";
    }
    
    /**
     * Process ClickBank IPN (Instant Payment Notification)
     * 
     * ClickBank sends POST data to this endpoint when a sale occurs.
     * We verify the signature and grant access.
     */
    public function handle_webhook($payload, $headers = []) {
        $params = [];
        parse_str($payload, $params);
        
        // Log for debugging
        error_log('FLOSC ClickBank IPN received: ' . print_r($params, true));
        
        // Required fields from ClickBank
        $required = ['ctransaction', 'ctransreceipt', 'cvendor', 'ccustname', 'ccustemail'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                error_log("FLOSC ClickBank IPN missing field: {$field}");
                return new WP_Error('missing_field', "Missing required field: {$field}", ['status' => 400]);
            }
        }
        
        // Verify this is for our vendor
        $our_vendor = $this->get_setting('vendor_nickname', 'SANDBOXVENDOR');
        if (strtolower($params['cvendor']) !== strtolower($our_vendor)) {
            error_log("FLOSC ClickBank IPN vendor mismatch: {$params['cvendor']} vs {$our_vendor}");
            return new WP_Error('vendor_mismatch', 'Vendor does not match', ['status' => 400]);
        }
        
        // Verify signature (if secret key configured)
        $secret_key = $this->get_setting('secret_key', '');
        if (!empty($secret_key)) {
            if (!$this->verify_ipn_signature($params, $secret_key)) {
                error_log('FLOSC ClickBank IPN signature verification failed');
                return new WP_Error('invalid_signature', 'Invalid IPN signature', ['status' => 401]);
            }
        }
        
        // Determine transaction type
        $transaction_type = strtoupper($params['ctransaction'] ?? '');
        
        switch ($transaction_type) {
            case 'SALE':
            case 'TEST_SALE':
            case 'TEST':
                return $this->handle_sale($params);
                
            case 'RFND':
            case 'CGBK':
            case 'INSF':
                return $this->handle_refund($params);
                
            case 'REBILL':
            case 'TEST_REBILL':
                return $this->handle_rebill($params);
                
            case 'CANCEL-REBILL':
            case 'UNCANCEL-REBILL':
                return $this->handle_subscription_change($params);
                
            default:
                error_log("FLOSC ClickBank IPN unknown transaction type: {$transaction_type}");
                return new WP_REST_Response(['received' => true, 'action' => 'ignored'], 200);
        }
    }
    
    /**
     * Verify ClickBank IPN signature
     * 
     * ClickBank signs IPNs using: SHA-256(secretKey + ipnFields)
     * The signature is sent in the 'cverify' field
     */
    private function verify_ipn_signature($params, $secret_key) {
        if (empty($params['cverify'])) {
            // No signature provided - in sandbox mode this is OK
            $mode = $this->get_setting('mode', 'sandbox');
            return $mode === 'sandbox';
        }
        
        // Build the string to hash (all params except cverify, in alphabetical order)
        $fields = $params;
        unset($fields['cverify']);
        ksort($fields);
        
        $string_to_hash = '';
        foreach ($fields as $key => $value) {
            $string_to_hash .= $value . '|';
        }
        $string_to_hash .= $secret_key;
        
        // ClickBank uses uppercase SHA-256
        $expected = strtoupper(sha256($string_to_hash));
        $received = strtoupper($params['cverify']);
        
        return hash_equals($expected, $received);
    }
    
    /**
     * Handle successful sale
     */
    private function handle_sale($params) {
        $email = sanitize_email($params['ccustemail']);
        $name = sanitize_text_field($params['ccustname']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        $product = sanitize_text_field($params['cproditem'] ?? $params['cproduct'] ?? '');
        $amount = floatval($params['ctransamount'] ?? 0);
        $affiliate = sanitize_text_field($params['caffitid'] ?? '');
        
        // Find or create WordPress user
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Create new user
            $username = sanitize_user(strtolower(explode('@', $email)[0]));
            $username = $this->get_unique_username($username);
            
            $user_id = wp_create_user($username, wp_generate_password(), $email);
            
            if (is_wp_error($user_id)) {
                error_log('FLOSC ClickBank failed to create user: ' . $user_id->get_error_message());
                return new WP_Error('user_creation_failed', 'Failed to create user', ['status' => 500]);
            }
            
            // Set display name
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $name,
                'first_name' => explode(' ', $name)[0],
            ]);
            
            $user = get_user_by('ID', $user_id);
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
        
        // Track affiliate if present
        if (!empty($affiliate)) {
            update_user_meta($user_id, '_flosc_affiliate_id', $affiliate);
        }
        
        // Mark as purchased (for FLOSC phase logic)
        update_user_meta($user_id, '_flosc_purchased', true);
        
        error_log("FLOSC ClickBank sale processed: user={$user_id}, receipt={$receipt}, access={$access_level}");
        
        // Fire action for other plugins to hook into
        do_action('flosc_clickbank_sale', $user_id, $params);
        
        return new WP_REST_Response([
            'success' => true,
            'user_id' => $user_id,
            'access_level' => $access_level,
            'receipt' => $receipt,
        ], 200);
    }
    
    /**
     * Handle refund/chargeback
     */
    private function handle_refund($params) {
        $email = sanitize_email($params['ccustemail']);
        $receipt = sanitize_text_field($params['ctransreceipt']);
        
        $user = get_user_by('email', $email);
        
        if ($user) {
            // Revoke access
            update_user_meta($user->ID, '_flosc_access_level', 'free');
            update_user_meta($user->ID, '_flosc_refunded', true);
            update_user_meta($user->ID, '_flosc_refund_date', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_purchased', false);
            
            error_log("FLOSC ClickBank refund processed: user={$user->ID}, receipt={$receipt}");
            
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
            // Update rebill date
            update_user_meta($user->ID, '_flosc_last_rebill', current_time('mysql'));
            update_user_meta($user->ID, '_flosc_clickbank_receipt', $receipt);
            
            error_log("FLOSC ClickBank rebill processed: user={$user->ID}");
            
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
                // Note: Access typically continues until end of billing period
            } else {
                // UNCANCEL-REBILL
                update_user_meta($user->ID, '_flosc_subscription_status', 'active');
                delete_user_meta($user->ID, '_flosc_cancel_date');
            }
            
            error_log("FLOSC ClickBank subscription {$transaction_type}: user={$user->ID}");
            
            do_action('flosc_clickbank_subscription_change', $user->ID, $transaction_type, $params);
        }
        
        return new WP_REST_Response(['success' => true, 'action' => $transaction_type], 200);
    }
    
    /**
     * Get unique username
     */
    private function get_unique_username($base) {
        $username = $base;
        $counter = 1;
        
        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    /**
     * Create purchase intent (for frontend)
     */
    public function create_intent($user_id, $offer, $params = []) {
        // ClickBank doesn't use intents like Stripe
        // We just return the checkout URL
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
        // ClickBank handles this via IPN, not direct completion
        // This method is for manual verification if needed
        
        $receipt = $params['cbreceipt'] ?? '';
        
        if (empty($receipt)) {
            return new WP_Error('no_receipt', 'No ClickBank receipt provided');
        }
        
        // Check if we already processed this receipt
        $existing = get_users([
            'meta_key' => '_flosc_clickbank_receipt',
            'meta_value' => $receipt,
            'number' => 1,
        ]);
        
        if (!empty($existing)) {
            // Already processed
            return [
                'success' => true,
                'already_processed' => true,
                'user_id' => $existing[0]->ID,
            ];
        }
        
        // Manual grant (if IPN hasn't arrived yet)
        $access_level = $this->get_setting('access_level', 'full');
        update_user_meta($user_id, '_flosc_access_level', $access_level);
        update_user_meta($user_id, '_flosc_clickbank_receipt', $receipt);
        update_user_meta($user_id, '_flosc_purchased', true);
        
        return [
            'success' => true,
            'access_level' => $access_level,
        ];
    }
}
