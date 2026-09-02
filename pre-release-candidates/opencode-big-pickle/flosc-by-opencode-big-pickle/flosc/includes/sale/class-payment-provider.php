<?php
/**
 * FLOSC Payment Provider Interface
 * 
 * Abstract base class for all payment providers.
 * Implement this to add new payment methods.
 */
if (!defined('ABSPATH')) exit;

abstract class FLOSC_Payment_Provider {
    
    /**
     * Unique provider ID
     */
    abstract public function get_id();
    
    /**
     * Provider display name
     */
    abstract public function get_name();
    
    /**
     * Description for admin
     */
    abstract public function get_description();
    
    /**
     * Optional icon for admin UI
     */
    public function get_icon() {
        return '💳';
    }
    
    /**
     * Check if provider is properly configured
     */
    abstract public function is_configured();
    
    /**
     * Check if provider is enabled
     */
    public function is_enabled() {
        return get_option('flosc_provider_' . $this->get_id() . '_enabled', true);
    }
    
    /**
     * Enable/disable the provider
     */
    public function set_enabled($enabled) {
        update_option('flosc_provider_' . $this->get_id() . '_enabled', (bool) $enabled);
    }
    
    /**
     * Get provider settings fields for admin
     */
    abstract public function get_settings_fields();
    
    /**
     * Process a payment
     * 
     * @param int $user_id
     * @param array $offer The offer being purchased
     * @param array $payment_data Provider-specific data
     * @return array|WP_Error Transaction result or error
     */
    abstract public function process_payment($user_id, $offer, $payment_data = []);
    
    /**
     * Handle refund
     */
    public function process_refund($user_id, $transaction_id, $amount = null) {
        return new WP_Error('not_supported', __('Refunds not supported by this provider', 'flosc'));
    }
    
    /**
     * Get transaction status
     */
    public function get_transaction_status($transaction_id) {
        return null;
    }
    
    /**
     * Handle webhooks (if applicable)
     */
    public function handle_webhook($payload, $headers = []) {
        return new WP_Error('not_supported', __('Webhooks not supported by this provider', 'flosc'));
    }
    
    /**
     * Get client-side config for JS
     */
    public function get_client_config() {
        return [];
    }
    
    /**
     * Render payment form/UI (if needed)
     */
    public function render_payment_ui($offer) {
        return '';
    }
    
    /**
     * Check if provider supports subscriptions
     */
    public function supports_subscriptions() {
        return false;
    }
    
    /**
     * Cancel a subscription
     */
    public function cancel_subscription($subscription_id) {
        return new WP_Error('not_supported', __('Subscriptions not supported by this provider', 'flosc'));
    }
    
    /**
     * Get user's active subscriptions
     */
    public function get_user_subscriptions($user_id) {
        return [];
    }
    
    /**
     * Helper: Save a setting
     */
    protected function save_setting($key, $value) {
        update_option('flosc_' . $this->get_id() . '_' . $key, $value);
    }
    
    /**
     * Helper: Get a setting
     */
    protected function get_setting($key, $default = '') {
        return get_option('flosc_' . $this->get_id() . '_' . $key, $default);
    }
}
