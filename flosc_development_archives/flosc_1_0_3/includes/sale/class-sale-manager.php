<?php
/**
 * FLOSC SALE Manager
 * 
 * Orchestrates the entire SALE system:
 * - Offers (what can be purchased)
 * - Payment Providers (how they pay)
 * - Usage Tracking (metered billing)
 * - Access Grants (what they get)
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Sale_Manager {
    
    private static $instance = null;
    
    private $offer_manager;
    private $usage_tracker;
    private $access_manager;
    private $providers = [];
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_components();
        $this->register_providers();
    }
    
    private function load_components() {
        require_once __DIR__ . '/class-offer-manager.php';
        require_once __DIR__ . '/class-usage-tracker.php';
        require_once __DIR__ . '/class-access-manager.php';
        require_once __DIR__ . '/class-payment-provider.php';
        require_once __DIR__ . '/providers/class-stripe-provider.php';
        require_once __DIR__ . '/providers/class-token-provider.php';
        require_once __DIR__ . '/providers/class-affiliate-provider.php';
        require_once __DIR__ . '/providers/class-clickbank-provider.php'; // v07.07
        
        $this->offer_manager = new FLOSC_Offer_Manager();
        $this->usage_tracker = new FLOSC_Usage_Tracker();
        $this->access_manager = new FLOSC_Access_Manager();
    }
    
    private function register_providers() {
        // Register built-in payment providers
        $this->providers['stripe'] = new FLOSC_Stripe_Provider();
        $this->providers['tokens'] = new FLOSC_Token_Provider();
        $this->providers['affiliate'] = new FLOSC_Affiliate_Provider();
        $this->providers['clickbank'] = new FLOSC_ClickBank_Provider(); // v07.07
        
        // Allow plugins to register additional providers
        $this->providers = apply_filters('flosc_payment_providers', $this->providers);
    }
    
    /**
     * Get a payment provider by ID
     */
    public function get_provider($provider_id) {
        return $this->providers[$provider_id] ?? null;
    }
    
    /**
     * Get all registered providers
     */
    public function get_providers() {
        return $this->providers;
    }
    
    /**
     * Get active providers (configured and enabled)
     */
    public function get_active_providers() {
        return array_filter($this->providers, function($provider) {
            return $provider->is_configured() && $provider->is_enabled();
        });
    }
    
    /**
     * Access component getters
     */
    public function offers() {
        return $this->offer_manager;
    }
    
    public function usage() {
        return $this->usage_tracker;
    }
    
    public function access() {
        return $this->access_manager;
    }
    
    /**
     * Process a purchase
     * 
     * @param int $user_id
     * @param string $offer_id
     * @param string $provider_id
     * @param array $payment_data Provider-specific data
     * @return array|WP_Error
     */
    public function process_purchase($user_id, $offer_id, $provider_id, $payment_data = []) {
        // Get offer
        $offer = $this->offer_manager->get_offer($offer_id);
        if (!$offer) {
            return new WP_Error('invalid_offer', 'Offer not found');
        }
        
        // Get provider
        $provider = $this->get_provider($provider_id);
        if (!$provider) {
            return new WP_Error('invalid_provider', 'Payment provider not found');
        }
        
        if (!$provider->is_configured()) {
            return new WP_Error('provider_not_configured', 'Payment provider not configured');
        }
        
        // Process payment through provider
        $result = $provider->process_payment($user_id, $offer, $payment_data);
        
        if (is_wp_error($result)) {
            do_action('flosc_purchase_failed', $user_id, $offer_id, $provider_id, $result);
            return $result;
        }
        
        // Grant access based on offer
        $this->access_manager->grant_from_offer($user_id, $offer, $result);
        
        // Log the purchase
        $this->log_purchase($user_id, $offer, $provider_id, $result);
        
        // Fire purchase completed action with standardized 2-arg signature
        // Args: $user_id, $purchase_data (array with offer details)
        do_action('flosc_purchase_completed', $user_id, [
            'offer_id' => $offer_id,
            'grants_level' => $offer['grants_level'] ?? '',
            'provider' => $provider_id,
            'transaction_id' => $result['transaction_id'] ?? null,
            'amount' => $offer['price'] ?? 0,
            'timestamp' => time(),
        ]);
        
        return [
            'success' => true,
            'offer' => $offer,
            'provider' => $provider_id,
            'transaction' => $result,
            'access' => $this->access_manager->get_user_access($user_id),
        ];
    }
    
    /**
     * Check if user can access a feature
     */
    public function can_access($user_id, $feature) {
        return $this->access_manager->can_access($user_id, $feature);
    }
    
    /**
     * Track usage of a feature
     */
    public function track_usage($user_id, $event, $quantity = 1, $meta = []) {
        return $this->usage_tracker->track($user_id, $event, $quantity, $meta);
    }
    
    /**
     * Check if user has enough tokens/credits for an action
     */
    public function has_credits($user_id, $amount) {
        return $this->providers['tokens']->get_balance($user_id) >= $amount;
    }
    
    /**
     * Deduct credits for an action
     */
    public function deduct_credits($user_id, $amount, $reason = '') {
        return $this->providers['tokens']->deduct($user_id, $amount, $reason);
    }
    
    /**
     * Log purchase for records
     */
    private function log_purchase($user_id, $offer, $provider_id, $transaction) {
        $purchases = get_user_meta($user_id, '_flosc_purchases', true) ?: [];
        
        $purchases[] = [
            'offer_id' => $offer['id'],
            'offer_name' => $offer['name'],
            'provider' => $provider_id,
            'transaction_id' => $transaction['transaction_id'] ?? null,
            'amount' => $transaction['amount'] ?? null,
            'currency' => $transaction['currency'] ?? null,
            'timestamp' => current_time('mysql'),
        ];
        
        update_user_meta($user_id, '_flosc_purchases', $purchases);
    }
    
    /**
     * Get available offers for a user (considering their current access)
     */
    public function get_available_offers($user_id = null) {
        $all_offers = $this->offer_manager->get_active_offers();
        
        if (!$user_id) {
            return $all_offers;
        }
        
        $user_access = $this->access_manager->get_user_access($user_id);
        
        // Filter out offers user already has
        return array_filter($all_offers, function($offer) use ($user_access) {
            // Don't show one-time offers they already purchased
            if ($offer['type'] === 'one_time' && isset($user_access['offers'][$offer['id']])) {
                return false;
            }
            return true;
        });
    }
    
    /**
     * Get recommended offer based on user state and funnel position
     */
    public function get_recommended_offer($user_id, $context = []) {
        $offers = $this->get_available_offers($user_id);
        $usage = $this->usage_tracker->get_user_summary($user_id);
        $access = $this->access_manager->get_user_access($user_id);
        
        // Logic to recommend best offer based on:
        // - User's current access level
        // - Usage patterns
        // - Funnel context (quiz score, engagement, etc.)
        
        // Default: return first available offer
        // Override with flosc_recommended_offer filter for custom logic
        $recommended = !empty($offers) ? reset($offers) : null;
        
        return apply_filters('flosc_recommended_offer', $recommended, $user_id, $context, $offers);
    }
}

/**
 * Global accessor
 */
function flosc_sale() {
    return FLOSC_Sale_Manager::instance();
}
