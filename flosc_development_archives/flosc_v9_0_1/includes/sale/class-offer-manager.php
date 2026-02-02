<?php
/**
 * FLOSC Offer Manager
 * 
 * Manages purchasable offers:
 * - One-time purchases (lifetime access, lesson packs)
 * - Subscriptions (monthly, yearly)
 * - Token/Credit packs
 * - Hybrid offers
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Offer_Manager {
    
    private $option_key = 'flosc_offers';
    
    /**
     * Offer types
     */
    const TYPE_ONE_TIME = 'one_time';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_TOKENS = 'tokens';
    const TYPE_HYBRID = 'hybrid';
    
    /**
     * Subscription intervals
     */
    const INTERVAL_MONTHLY = 'month';
    const INTERVAL_YEARLY = 'year';
    const INTERVAL_WEEKLY = 'week';
    
    /**
     * Get all offers
     */
    public function get_all_offers() {
        $offers = get_option($this->option_key, []);
        
        // Ensure default structure
        if (empty($offers)) {
            $offers = $this->get_default_offers();
            update_option($this->option_key, $offers);
        }
        
        return $offers;
    }
    
    /**
     * Get active (published) offers
     */
    public function get_active_offers() {
        $offers = $this->get_all_offers();
        return array_filter($offers, function($offer) {
            return ($offer['status'] ?? 'active') === 'active';
        });
    }
    
    /**
     * Get a specific offer by ID
     */
    public function get_offer($offer_id) {
        $offers = $this->get_all_offers();
        return $offers[$offer_id] ?? null;
    }
    
    /**
     * Get offers by type
     */
    public function get_offers_by_type($type) {
        $offers = $this->get_active_offers();
        return array_filter($offers, function($offer) use ($type) {
            return $offer['type'] === $type;
        });
    }
    
    /**
     * Create a new offer
     */
    public function create_offer($data) {
        $offers = $this->get_all_offers();
        
        $offer_id = $data['id'] ?? 'offer_' . uniqid();
        
        $offer = $this->validate_offer_data(array_merge([
            'id' => $offer_id,
            'created_at' => current_time('mysql'),
        ], $data));
        
        $offers[$offer_id] = $offer;
        update_option($this->option_key, $offers);
        
        do_action('flosc_offer_created', $offer_id, $offer);
        
        return $offer;
    }
    
    /**
     * Update an offer
     */
    public function update_offer($offer_id, $data) {
        $offers = $this->get_all_offers();
        
        if (!isset($offers[$offer_id])) {
            return new WP_Error('not_found', 'Offer not found');
        }
        
        $offer = $this->validate_offer_data(array_merge(
            $offers[$offer_id],
            $data,
            ['updated_at' => current_time('mysql')]
        ));
        
        $offers[$offer_id] = $offer;
        update_option($this->option_key, $offers);
        
        do_action('flosc_offer_updated', $offer_id, $offer);
        
        return $offer;
    }
    
    /**
     * Delete an offer
     */
    public function delete_offer($offer_id) {
        $offers = $this->get_all_offers();
        
        if (!isset($offers[$offer_id])) {
            return new WP_Error('not_found', 'Offer not found');
        }
        
        $offer = $offers[$offer_id];
        unset($offers[$offer_id]);
        update_option($this->option_key, $offers);
        
        do_action('flosc_offer_deleted', $offer_id, $offer);
        
        return true;
    }
    
    /**
     * Validate and normalize offer data
     */
    private function validate_offer_data($data) {
        $defaults = [
            'id' => '',
            'name' => '',
            'description' => '',
            'type' => self::TYPE_ONE_TIME,
            'status' => 'active',
            
            // Pricing (provider-specific)
            'pricing' => [
                'stripe' => [
                    'price_id' => '',       // Stripe Price ID
                    'product_id' => '',     // Stripe Product ID
                ],
                'tokens' => [
                    'cost' => 0,            // Cost in tokens
                ],
                'affiliate' => [
                    'credit_amount' => 0,   // How much affiliate credit unlocks this
                ],
            ],
            
            // Display pricing (for UI, not for charging)
            'display_price' => '',          // e.g., "€144" or "500 tokens" or "Free with purchase"
            
            // For subscriptions
            'subscription' => [
                'interval' => self::INTERVAL_MONTHLY,
                'interval_count' => 1,
                'trial_days' => 0,
            ],
            
            // For token packs
            'tokens' => [
                'amount' => 0,              // How many tokens this grants
                'bonus' => 0,               // Bonus tokens
            ],
            
            // Access grants
            'grants' => [
                'features' => [],           // Feature flags to enable
                'access_level' => '',       // Access level (e.g., 'pro', 'premium')
                'duration_days' => 0,       // 0 = lifetime
                'usage_limits' => [],       // e.g., ['ai_queries' => 1000]
            ],
            
            // Metadata
            'meta' => [
                'badge' => '',              // Badge text (e.g., "Most Popular")
                'savings' => '',            // Savings text (e.g., "Save 20%")
                'icon' => '',               // Emoji or icon
            ],
            
            'sort_order' => 0,
            'created_at' => '',
            'updated_at' => '',
        ];
        
        return wp_parse_args($data, $defaults);
    }
    
    /**
     * Get default offers (starter configuration)
     */
    private function get_default_offers() {
        return [
            'free_trial' => [
                'id' => 'free_trial',
                'name' => 'Free Trial',
                'description' => 'Try the quiz and one free lesson',
                'type' => self::TYPE_ONE_TIME,
                'status' => 'active',
                'display_price' => 'Free',
                'pricing' => [
                    'stripe' => ['price_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 0],
                ],
                'grants' => [
                    'features' => ['quiz', 'free_lesson'],
                    'access_level' => 'free',
                    'duration_days' => 0,
                    'usage_limits' => ['quizzes' => 3, 'lessons' => 1],
                ],
                'meta' => ['icon' => '🎁'],
                'sort_order' => 0,
                'created_at' => current_time('mysql'),
            ],
            
            'full_access' => [
                'id' => 'full_access',
                'name' => 'Full Access',
                'description' => 'Lifetime access to all lessons',
                'type' => self::TYPE_ONE_TIME,
                'status' => 'active',
                'display_price' => 'Configure in Stripe',
                'pricing' => [
                    'stripe' => ['price_id' => '', 'product_id' => ''],
                    'tokens' => ['cost' => 1000],
                    'affiliate' => ['credit_amount' => 50],
                ],
                'grants' => [
                    'features' => ['quiz', 'all_lessons', 'ai_coach', 'certificates'],
                    'access_level' => 'pro',
                    'duration_days' => 0, // Lifetime
                    'usage_limits' => [],
                ],
                'meta' => ['icon' => '⭐', 'badge' => 'Best Value'],
                'sort_order' => 10,
                'created_at' => current_time('mysql'),
            ],
            
            'token_pack_small' => [
                'id' => 'token_pack_small',
                'name' => '100 Tokens',
                'description' => 'Pay-per-use credits',
                'type' => self::TYPE_TOKENS,
                'status' => 'draft', // Not active by default
                'display_price' => 'Configure in Stripe',
                'pricing' => [
                    'stripe' => ['price_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 5],
                ],
                'tokens' => [
                    'amount' => 100,
                    'bonus' => 0,
                ],
                'grants' => [
                    'features' => [],
                    'access_level' => '',
                    'duration_days' => 0,
                ],
                'meta' => ['icon' => '🪙'],
                'sort_order' => 20,
                'created_at' => current_time('mysql'),
            ],
            
            'monthly_sub' => [
                'id' => 'monthly_sub',
                'name' => 'Monthly Access',
                'description' => 'Full access, billed monthly',
                'type' => self::TYPE_SUBSCRIPTION,
                'status' => 'draft', // Not active by default
                'display_price' => 'Configure in Stripe',
                'pricing' => [
                    'stripe' => ['price_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 10],
                ],
                'subscription' => [
                    'interval' => self::INTERVAL_MONTHLY,
                    'interval_count' => 1,
                    'trial_days' => 7,
                ],
                'grants' => [
                    'features' => ['quiz', 'all_lessons', 'ai_coach'],
                    'access_level' => 'pro',
                    'duration_days' => 30,
                ],
                'meta' => ['icon' => '📅'],
                'sort_order' => 30,
                'created_at' => current_time('mysql'),
            ],
        ];
    }
    
    /**
     * Get offer types for admin UI
     */
    public function get_offer_types() {
        return [
            self::TYPE_ONE_TIME => [
                'label' => 'One-Time Purchase',
                'description' => 'Single payment for lifetime or fixed-period access',
            ],
            self::TYPE_SUBSCRIPTION => [
                'label' => 'Subscription',
                'description' => 'Recurring payment for ongoing access',
            ],
            self::TYPE_TOKENS => [
                'label' => 'Token Pack',
                'description' => 'Purchase credits for pay-per-use features',
            ],
            self::TYPE_HYBRID => [
                'label' => 'Hybrid',
                'description' => 'Subscription with included tokens',
            ],
        ];
    }
    
    /**
     * Get subscription intervals for admin UI
     */
    public function get_intervals() {
        return [
            self::INTERVAL_WEEKLY => 'Weekly',
            self::INTERVAL_MONTHLY => 'Monthly',
            self::INTERVAL_YEARLY => 'Yearly',
        ];
    }
    
    /**
     * Calculate effective price from an offer for a provider
     */
    public function get_offer_price($offer_id, $provider_id) {
        $offer = $this->get_offer($offer_id);
        
        if (!$offer) {
            return null;
        }
        
        return $offer['pricing'][$provider_id] ?? null;
    }
}
