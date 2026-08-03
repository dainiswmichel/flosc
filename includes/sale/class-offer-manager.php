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
    private $offer_aliases = [
        'lesaep_full' => 'pronunciation_full',
        'pronunciation_full' => 'lesaep_full',
    ];
    
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
                $offers = $flow_settings['offers'];
                $synced = $this->sync_ivr_offers_into_offers($offers, $flow_id);
                if ($synced !== $offers) {
                    $flow_settings['offers'] = $synced;
                    update_option($flow_key, $flow_settings);
                    return $synced;
                }
                return $offers;
            }
        }
        
        // Fallback to global option for backward compat
        $offers = get_option($this->option_key, []);
        
        // Ensure default structure
        if (empty($offers)) {
            $offers = $this->get_default_offers();
            update_option($this->option_key, $offers);
        }

        $offers = $this->sync_ivr_offers_into_offers($offers, $flow_id);
        
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
        if (isset($offers[$offer_id])) {
            return $offers[$offer_id];
        }
        $alias = $this->offer_aliases[$offer_id] ?? null;
        return $alias && isset($offers[$alias]) ? $offers[$alias] : null;
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
            return new WP_Error('not_found', __('Offer not found', 'flosc'));
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
            return new WP_Error('not_found', __('Offer not found', 'flosc'));
        }
        
        $offer = $offers[$offer_id];
        unset($offers[$offer_id]);
        update_option($this->option_key, $offers);
        
        do_action('flosc_offer_deleted', $offer_id, $offer);
        
        return true;
    }

    /**
     * Sync IVR-defined offer messages into the editable offer registry.
     * Keeps the IVR file as the source of truth for visible offer copy/format.
     */
    private function sync_ivr_offers_into_offers($offers, $flow_id = null) {
        if (empty($flow_id)) {
            return $offers;
        }

        $messages = [];

        // Primary source: per-flow DB messages (what IVR Messages tab is editing).
        $flow_key = 'flosc_flow_' . sanitize_key($flow_id);
        $flow_settings = get_option($flow_key, []);
        $db_messages = flosc_flow_get_messages($flow_settings);
        if (is_array($db_messages) && !empty($db_messages)) {
            $messages = $db_messages;
        }

        // Fallback source: active IVR file if DB messages are not available.
        if (empty($messages)) {
            $ivr_file = $this->get_flow_ivr_file($flow_id);
            if (!$ivr_file || !file_exists($ivr_file)) {
                return $offers;
            }

            if (!class_exists('FLOSC_IVR_Parser')) {
                require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
            }

            if (!class_exists('FLOSC_IVR_Parser')) {
                return $offers;
            }

            $parser = FLOSC_IVR_Parser::flosc_instance();
            $parsed = $parser->flosc_parse(file_get_contents($ivr_file));
            $messages = $parsed['messages'] ?? [];
        }

        if (empty($messages) || !is_array($messages)) {
            return $offers;
        }

        $updated = $offers;
        foreach ($messages as $msg_id => $msg) {
            $msg_type = strtolower(trim((string)($msg['type'] ?? '')));
            $msg_phase = strtolower(trim((string)($msg['phase'] ?? '')));
            $msg_action = strtolower(trim((string)($msg['action'] ?? '')));

            $is_offer_scope = ($msg_type === 'offer')
                || ($msg_phase === 'offer' && $msg_type === 'suggested_user_autoprompt')
                || strpos($msg_action, 'show_offer') !== false
                || strpos($msg_action, 'checkout') !== false
                || strpos($msg_action, 'sandbox_purchase') !== false;

            if (!$is_offer_scope) {
                continue;
            }

            $offer_id = sanitize_key($msg['offer_id'] ?? ($msg['name'] ?? $msg_id));
            if (!$offer_id) {
                continue;
            }

            $existing = $updated[$offer_id] ?? [];
            $updated[$offer_id] = $this->normalize_ivr_offer_message($existing, $msg, $offer_id);
        }

        return $updated;
    }

    /**
     * Build a normalized offer record from an IVR offer message.
     */
    private function normalize_ivr_offer_message($existing, $msg, $offer_id) {
        $name = trim((string)($msg['title'] ?? ''));
        if ($name === '') {
            $name = trim((string)($msg['name'] ?? ''));
        }
        if ($name === '') {
            $name = $offer_id;
        }

        $description = trim((string)($msg['content'] ?? ''));
        if ($description === '' && !empty($existing['description'])) {
            $description = $existing['description'];
        }

        $display_format = trim((string)($msg['display_format'] ?? ''));
        if ($display_format === '') {
            if (($msg['type'] ?? '') === 'suggested_user_autoprompt') {
                $display_format = 'pill';
            } else {
                $display_format = $existing['display_format'] ?? 'featured';
            }
        }

        // Preserve admin status/active. Previously this always forced status=active,
        // which re-enabled sandbox offers after floscAdmin turned them off.
        if (!empty($existing) && is_array($existing)) {
            $status = strtolower((string) ($existing['status'] ?? 'active'));
            if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
                $status = 'active';
            }
            if (array_key_exists('active', $existing)) {
                $is_active = !empty($existing['active']);
            } else {
                $is_active = ($status === 'active');
            }
            // Keep status/active consistent with each other.
            if ($status === 'active' && !$is_active) {
                $status = 'inactive';
            }
            if ($is_active && $status !== 'active') {
                $is_active = false;
            }
        } else {
            $status = 'active';
            $is_active = true;
        }

        $normalized = array_merge($existing, [
            'id'             => $offer_id,
            'name'           => $name,
            'description'    => $description,
            'headline'       => $name,
            'type'           => $existing['type'] ?? self::TYPE_ONE_TIME,
            'status'         => $status,
            'active'         => $is_active,
            'display_format' => $display_format,
            'display_price'  => $existing['display_price'] ?? '',
            'cta'            => $existing['cta'] ?? '',
            'reveal_phrase'  => trim((string)($msg['user_input'] ?? ($existing['reveal_phrase'] ?? ''))),
            'condition'      => trim((string)($msg['conditions'] ?? ($existing['condition'] ?? 'always'))),
            'timer_seconds'  => isset($msg['timer']) ? intval($msg['timer']) * 60 : ($existing['timer_seconds'] ?? 0),
            'meta'           => array_merge($existing['meta'] ?? [], [
                'icon' => $msg['icon'] ?? ($existing['meta']['icon'] ?? '⭐'),
            ]),
        ]);

        if (empty($normalized['display_formats']) || !is_array($normalized['display_formats'])) {
            $normalized['display_formats'] = [];
        }
        if (!isset($normalized['display_formats'][$display_format])) {
            $normalized['display_formats'][$display_format] = ['enabled' => true];
        }

        return $this->validate_offer_data($normalized);
    }

    /**
     * Resolve the active IVR file for a flow.
     */
    private function get_flow_ivr_file($flow_id) {
        $flow_key = 'flosc_flow_' . sanitize_key($flow_id);
        $flow_settings = get_option($flow_key, []);
        $candidate_files = [];

        // Primary source: per-flow selected IVR file in settings UI.
        $active_ivr_file = sanitize_file_name((string)($flow_settings['active_ivr_file'] ?? ''));
        if ($active_ivr_file !== '') {
            $candidate_files[] = $active_ivr_file;
        }

        // Flow registry fallback: flosc_flows[flow_id].ivr_file.
        $all_flows = get_option('flosc_flows', []);
        if (is_array($all_flows) && !empty($all_flows[$flow_id]['ivr_file'])) {
            $registry_ivr_file = sanitize_file_name((string)$all_flows[$flow_id]['ivr_file']);
            if ($registry_ivr_file !== '') {
                $candidate_files[] = $registry_ivr_file;
            }
        }

        // If flow_id itself includes .md, try it directly.
        $flow_id_file = sanitize_file_name((string)$flow_id);
        if (substr($flow_id_file, -3) === '.md') {
            $candidate_files[] = $flow_id_file;
        }

        // Default inference from flow_id.
        $candidate_files[] = sanitize_file_name((string)$flow_id) . '.md';

        $candidate_files = array_values(array_unique(array_filter($candidate_files)));

        foreach ($candidate_files as $candidate) {
            $path = flosc_config_file($candidate);
            if (file_exists($path)) {
                return $path;
            }
        }

        // Return the first candidate path for diagnostics/logging if no file exists.
        $fallback = $candidate_files[0] ?? ('flosc_default_technical_ivr.md');
        return flosc_config_file($fallback);
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
            
            'pronunciation_full' => [
                'id' => 'pronunciation_full',
                'name' => 'Pronunciation — Full Access',
                'description' => 'Full access to all American English pronunciation lessons, IPA training, audio recordings, and AI coaching.',
                'type' => self::TYPE_SUBSCRIPTION,
                'status' => 'active',
                'price' => 10.00,
                'display_price' => '$10/month or $100/year',
                'original_price' => '',
                'pricing' => [
                    'price'     => 10.00,
                    'currency'  => 'USD',
                    'processor' => 'paypal',
                    'stripe'    => ['price_id' => '', 'product_id' => ''],
                    'tokens'    => ['cost' => 0],
                    'affiliate' => ['credit_amount' => 30],
                    'redirect_url' => '',
                ],
                'subscription' => [
                    'interval' => self::INTERVAL_MONTHLY,
                    'interval_count' => 1,
                    'trial_days' => 0,
                    'plans' => [
                        'monthly' => ['price' => 10.00, 'label' => '$10/month'],
                        'yearly'  => ['price' => 100.00, 'label' => '$100/year — save $20!'],
                    ],
                ],
                'display_format' => 'featured',
                'cta' => 'Start Learning Now',
                'timer_seconds' => 0,
                'guarantee' => '',
                'grants' => [
                    'features' => ['lesaep_lessons', 'pronunciation_exercises', 'audio_recordings', 'ipa_training', 'ai_coach'],
                    'level' => 'pronunciation_learners',
                    'duration_days' => 30,
                    'usage_limits' => [],
                ],
                'grants_level' => 'pronunciation_learners',
                'meta' => [
                    'icon' => '🎤',
                    'badge' => 'Pre-Launch Price',
                    'savings' => 'Save $20 with yearly!',
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
            'lesaep' => flosc_get_setting('default_offer_id', 'pronunciation_full', 'lesaep'),
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
            'lesaep' => flosc_get_setting('default_member_level', 'pronunciation_learners', 'lesaep'),
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
        $lesaep_flow = function_exists('flosc_flows') ? flosc_flows()->get_flow('lesaep') : null;
        $lesaep_identity = $lesaep_flow['identity'] ?? [];
        $products = [
            'flosc_plugin' => [
                'id' => 'flosc_plugin',
                'name' => 'FLOSC WordPress Plugin',
                'tagline' => 'AI-Powered Quiz Funnels for WordPress',
                'icon' => '🔌',
                'ivr_file' => 'flosc_default_technical_ivr.md',
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
                'name' => $lesaep_identity['name'] ?? 'LeSAEp Pronunciation',
                'tagline' => $lesaep_identity['tagline'] ?? 'Learn Excellent Standard American English Pronunciation',
                'icon' => '🎤',
                'ivr_file' => 'lesaep_ivr.md',
                'member_level' => flosc_get_setting('default_member_level', 'pronunciation_learners', 'lesaep'),
                'offer_id' => flosc_get_setting('default_offer_id', 'pronunciation_full', 'lesaep'),
            ],
        ];
        
        return $products[$product_id] ?? null;
    }
}
