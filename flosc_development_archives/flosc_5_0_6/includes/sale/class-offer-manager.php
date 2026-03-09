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
     * v1.6.2: Flow-aware — reads from per-flow storage first, falls back to global
     * v1.6.5: Seeds defaults into per-flow storage on first access so admin can edit them
     */
    public function get_all_offers($flow_id = null) {
        // v1.6.2: Try per-flow storage first (where admin offers.php saves)
        if ($flow_id) {
            $flow_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_key, []);
            if (!empty($flow_settings['offers'])) {
                return $flow_settings['offers'];
            }
        }
        
        // Fallback to global option for backward compat
        $offers = get_option($this->option_key, []);
        
        // Ensure default structure
        if (empty($offers)) {
            $offers = $this->get_default_offers();
            update_option($this->option_key, $offers);
        }
        
        // v1.6.5: Seed defaults into per-flow storage so admin UI can see/edit them
        if ($flow_id) {
            $flow_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_key, []);
            $flow_settings['offers'] = $offers;
            update_option($flow_key, $flow_settings);
        }
        
        return $offers;
    }
    
    /**
     * Get active (published) offers
     * v1.6.2: Flow-aware
     */
    public function get_active_offers($flow_id = null) {
        $offers = $this->get_all_offers($flow_id);
        return array_filter($offers, function($offer) {
            return ($offer['status'] ?? 'active') === 'active';
        });
    }
    
    /**
     * Get a specific offer by ID
     * v1.6.2: Flow-aware
     */
    public function get_offer($offer_id, $flow_id = null) {
        $offers = $this->get_all_offers($flow_id);
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
                'redirect_url' => '',       // MTS-2026-02-03: External checkout URL
            ],
            
            // Display pricing (for UI, not for charging)
            'display_price' => '',          // e.g., "€144" or "500 tokens" or "Free with purchase"
            'original_price' => '',         // MTS-2026-02-03: Original price (for strikethrough)
            
            // MTS-2026-02-03: [DISPLAY-OPTIONS] Configurable display format
            'display_format' => 'card',     // pill, card, compact, banner, featured, text, inline-checkout
            'cta' => '',                    // Custom CTA button text
            'timer_seconds' => 3600,        // Countdown timer (0 = no timer)
            'guarantee' => '',              // Guarantee text (e.g., "30-day money-back guarantee")
            
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
                'level' => '',              // Member level to grant (MTS-2026-02-03)
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
                'price' => 0.00,
                'original_price' => 0.00,
                'display_price' => 'Free',
                'pricing' => [
                    'price'     => 0.00,
                    'currency'  => 'USD',
                    'processor' => 'free',
                    'stripe'    => ['price_id' => ''],
                    'tokens'    => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 0],
                ],
                'grants' => [
                    'features' => ['quiz', 'free_lesson'],
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
                'price' => 49.00,
                'original_price' => 97.00,
                'display_price' => '$49.00',
                'headline' => 'Get Full Access — 50% Off Today!',
                'cta' => 'Get Full Access Now',
                'guarantee' => 'Risk-free with our 30-day money-back guarantee',
                'pricing' => [
                    'price'     => 49.00,
                    'currency'  => 'USD',
                    'processor' => 'paypal',  // Change to 'stripe' when Stripe is configured
                    'stripe'    => ['price_id' => '', 'product_id' => ''],
                    'tokens'    => ['cost' => 1000],
                    'affiliate' => ['credit_amount' => 50],
                ],
                'grants' => [
                    'features' => ['quiz', 'all_lessons', 'ai_coach', 'certificates'],
                    'level'    => 'full_access',
                    'duration_days' => 0, // Lifetime
                    'usage_limits' => [],
                ],
                'grants_level' => 'full_access',
                'meta' => ['icon' => '⭐', 'badge' => 'Best Value', 'savings' => 'Save $48!'],
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
                    'duration_days' => 30,
                ],
                'meta' => ['icon' => '📅'],
                'sort_order' => 30,
                'created_at' => current_time('mysql'),
            ],
            
            // ============================================
            // v1.4.0: PRODUCT-SPECIFIC OFFERS
            // Each product has its own offer and member level
            // ============================================
            
            'flosc_plugin_full' => [
                'id' => 'flosc_plugin_full',
                'name' => 'FLOSC Plugin - Full Access',
                'description' => 'Complete FLOSC WordPress plugin with lifetime updates, AI-powered quiz funnels, and premium support.',
                'type' => self::TYPE_ONE_TIME,
                'status' => 'active',
                'display_price' => '$197',
                'original_price' => '$297',
                'pricing' => [
                    'stripe' => ['price_id' => '', 'product_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 50],
                    'redirect_url' => '',
                ],
                'display_format' => 'featured',
                'cta' => 'Get FLOSC Plugin',
                'timer_seconds' => 0,
                'guarantee' => '30-day money-back guarantee',
                'grants' => [
                    'features' => ['flosc_plugin', 'plugin_updates', 'premium_support', 'ai_chat', 'all_quizzes'],
                    'level' => 'flosc_plugin_member',
                    'duration_days' => 0, // Lifetime
                    'usage_limits' => [],
                ],
                'meta' => [
                    'icon' => '🔌',
                    'badge' => 'WordPress Plugin',
                    'savings' => 'Save $100',
                ],
                'product_id' => 'flosc_plugin',
                'sort_order' => 100,
                'created_at' => current_time('mysql'),
            ],
            
            'simplified_solfeggio_full' => [
                'id' => 'simplified_solfeggio_full',
                'name' => 'Simplified Solfeggio - Complete Course',
                'description' => 'Master sight-singing with the Michel Hand of Music! All lessons, exercises, and lifetime access to the course.',
                'type' => self::TYPE_ONE_TIME,
                'status' => 'active',
                'display_price' => '$47',
                'original_price' => '$97',
                'pricing' => [
                    'stripe' => ['price_id' => '', 'product_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 20],
                    'redirect_url' => '',
                ],
                'display_format' => 'featured',
                'cta' => 'Start Learning Music',
                'timer_seconds' => 0,
                'guarantee' => '30-day money-back guarantee',
                'grants' => [
                    'features' => ['solfeggio_lessons', 'solfeggio_exercises', 'ai_coach', 'all_quizzes'],
                    'level' => 'simplified_solfeggio_member',
                    'duration_days' => 0, // Lifetime
                    'usage_limits' => [],
                ],
                'meta' => [
                    'icon' => '🎵',
                    'badge' => 'Music Course',
                    'savings' => 'Save $50',
                ],
                'product_id' => 'simplified_solfeggio',
                'sort_order' => 101,
                'created_at' => current_time('mysql'),
            ],
            
            'lesaep_full' => [
                'id' => 'lesaep_full',
                'name' => 'LeSAEp - Complete Pronunciation Course',
                'description' => 'Master all 44 sounds of Standard American English! Video lessons, native speaker recordings, and IPA training.',
                'type' => self::TYPE_ONE_TIME,
                'status' => 'active',
                'display_price' => '$97',
                'original_price' => '$197',
                'pricing' => [
                    'stripe' => ['price_id' => '', 'product_id' => ''],
                    'tokens' => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 30],
                    'redirect_url' => '',
                ],
                'display_format' => 'featured',
                'cta' => 'Master Pronunciation',
                'timer_seconds' => 0,
                'guarantee' => '30-day money-back guarantee',
                'grants' => [
                    'features' => ['lesaep_lessons', 'pronunciation_exercises', 'audio_recordings', 'ipa_training', 'ai_coach'],
                    'level' => 'lesaep_member',
                    'duration_days' => 0, // Lifetime
                    'usage_limits' => [],
                ],
                'meta' => [
                    'icon' => '🎤',
                    'badge' => 'Pronunciation Course',
                    'savings' => 'Save $100',
                ],
                'product_id' => 'lesaep',
                'sort_order' => 102,
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
     * MTS-2026-02-03: [DISPLAY-FORMATS] Get available display formats for admin UI
     */
    public function get_display_formats() {
        return [
            'card' => [
                'label' => 'Card (Default)',
                'description' => 'Rich card with content, timer, and CTA button',
                'icon' => '🃏',
            ],
            'pill' => [
                'label' => 'Pill',
                'description' => 'Compact inline pill - great for PromptPanel',
                'icon' => '💊',
            ],
            'compact' => [
                'label' => 'Compact Card',
                'description' => 'Smaller card with icon, title, and price',
                'icon' => '📦',
            ],
            'banner' => [
                'label' => 'Banner',
                'description' => 'Full-width promotional banner',
                'icon' => '📢',
            ],
            'featured' => [
                'label' => 'Featured',
                'description' => 'Large prominent card with features list',
                'icon' => '⭐',
            ],
            'text' => [
                'label' => 'Text',
                'description' => 'Simple text-based with clickable link',
                'icon' => '📝',
            ],
            'inline-checkout' => [
                'label' => 'Inline Checkout',
                'description' => 'Stripe card form embedded in chat',
                'icon' => '💳',
            ],
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
    
    /**
     * v1.4.0: Get offer by product ID
     * Maps product identifiers to their offers
     */
    public function get_offer_by_product($product_id) {
        $product_offer_map = [
            'flosc_plugin' => 'flosc_plugin_full',
            'simplified_solfeggio' => 'simplified_solfeggio_full',
            'lesaep' => 'lesaep_full',
        ];
        
        $offer_id = $product_offer_map[$product_id] ?? null;
        
        if ($offer_id) {
            return $this->get_offer($offer_id);
        }
        
        return null;
    }
    
    /**
     * v1.4.0: Get member level for a product
     * Maps product identifiers to their member levels
     */
    public function get_member_level_for_product($product_id) {
        $product_level_map = [
            'flosc_plugin' => 'flosc_plugin_member',
            'simplified_solfeggio' => 'simplified_solfeggio_member',
            'lesaep' => 'lesaep_member',
        ];
        
        return $product_level_map[$product_id] ?? 'flosc_sandbox';
    }
    
    /**
     * v1.4.0: Get all product IDs
     */
    public function get_product_ids() {
        return ['flosc_plugin', 'simplified_solfeggio', 'lesaep'];
    }
    
    /**
     * v1.4.0: Get product metadata
     */
    public function get_product_metadata($product_id) {
        $products = [
            'flosc_plugin' => [
                'id' => 'flosc_plugin',
                'name' => 'FLOSC WordPress Plugin',
                'tagline' => 'AI-Powered Quiz Funnels for WordPress',
                'icon' => '🔌',
                'ivr_file' => 'flosc_default_ivr.md',
                'member_level' => 'flosc_plugin_member',
                'offer_id' => 'flosc_plugin_full',
            ],
            'simplified_solfeggio' => [
                'id' => 'simplified_solfeggio',
                'name' => 'Simplified Solfeggio',
                'tagline' => 'The Michel Hand of Music - Sight-Singing Made Simple',
                'icon' => '🎵',
                'ivr_file' => 'simplified_solfeggio_ivr.md',
                'member_level' => 'simplified_solfeggio_member',
                'offer_id' => 'simplified_solfeggio_full',
            ],
            'lesaep' => [
                'id' => 'lesaep',
                'name' => 'LeSAEp Pronunciation',
                'tagline' => 'Learn Excellent Standard American English Pronunciation',
                'icon' => '🎤',
                'ivr_file' => 'lesaep_ivr.md',
                'member_level' => 'lesaep_member',
                'offer_id' => 'lesaep_full',
            ],
        ];
        
        return $products[$product_id] ?? null;
    }
}
