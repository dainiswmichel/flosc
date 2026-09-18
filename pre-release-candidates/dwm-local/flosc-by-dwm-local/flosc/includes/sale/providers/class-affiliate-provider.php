<?php
/**
 * FLOSC Affiliate Payment Provider
 * 
 * Permission-Based Purchase Intent System
 * 
 * How it works:
 * 1. User declares what they're planning to buy ("I need a new laptop")
 * 2. System searches affiliate networks for matching offers
 * 3. User clicks through and purchases (they were going to buy anyway)
 * 4. Commission flows back to the ecosystem
 * 5. User gets free/discounted access to FLOSC products
 * 
 * Win-win-win:
 * - User gets free access
 * - Ecosystem gets commission
 * - Affiliate networks get sales they wouldn't have tracked
 * 
 * This is the spiritual successor to ADZ.world - a permission-based
 * advertising model that doesn't track users without consent.
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Affiliate_Provider extends FLOSC_Payment_Provider {
    
    private $intents_meta_key = '_flosc_purchase_intents';
    private $credits_meta_key = '_flosc_affiliate_credits';
    
    public function get_id() {
        return 'affiliate';
    }
    
    public function get_name() {
        return 'Purchase Intent';
    }
    
    public function get_description() {
        return 'Users earn access by declaring purchase intent. When they buy through affiliate links, the commission funds their access.';
    }
    
    public function get_icon() {
        return '🎁';
    }
    
    public function is_configured() {
        // Check if any affiliate network is configured
        return !empty($this->get_setting('amazon_tag', '')) ||
               !empty($this->get_setting('cj_id', '')) ||
               !empty($this->get_setting('shareasale_id', '')) ||
               !empty($this->get_setting('custom_endpoint', ''));
    }
    
    /**
     * Settings for admin
     */
    public function get_settings_fields() {
        return [
            'description_header' => [
                'type' => 'heading',
                'label' => 'Permission-Based Affiliate System',
                'description' => 'Users declare what they plan to buy. When they purchase through your affiliate links, they earn credits toward free access.',
            ],
            
            // Amazon Associates
            'amazon_tag' => [
                'type' => 'text',
                'label' => 'Amazon Associates Tag',
                'placeholder' => 'your-tag-20',
                'description' => 'Your Amazon affiliate tracking tag',
            ],
            'amazon_regions' => [
                'type' => 'text',
                'label' => 'Amazon Regions',
                'default' => 'US,UK,DE',
                'description' => 'Comma-separated: US, UK, DE, FR, IT, ES, CA, etc.',
            ],
            
            // Impact/CJ (Commission Junction)
            'cj_id' => [
                'type' => 'text',
                'label' => 'CJ (Commission Junction) ID',
                'placeholder' => 'Your CJ publisher ID',
            ],
            'cj_api_key' => [
                'type' => 'password',
                'label' => 'CJ API Key',
            ],
            
            // ShareASale
            'shareasale_id' => [
                'type' => 'text',
                'label' => 'ShareASale Affiliate ID',
            ],
            'shareasale_api_token' => [
                'type' => 'password',
                'label' => 'ShareASale API Token',
            ],
            
            // Custom affiliate endpoint (for aggregators or your own system)
            'custom_endpoint' => [
                'type' => 'url',
                'label' => 'Custom Affiliate API Endpoint',
                'description' => 'Your own affiliate offer aggregation API',
            ],
            'custom_api_key' => [
                'type' => 'password',
                'label' => 'Custom API Key',
            ],
            
            // Conversion settings
            'credit_rate' => [
                'type' => 'number',
                'label' => 'Credit Rate',
                'default' => 100,
                'description' => 'Percent of commission to credit (100 = full, 50 = half)',
            ],
            'min_intent_value' => [
                'type' => 'number',
                'label' => 'Minimum Intent Value',
                'default' => 20,
                'description' => 'Minimum expected purchase value ($) to show offers',
            ],
            
            // Categories
            'enabled_categories' => [
                'type' => 'text',
                'label' => 'Enabled Categories',
                'default' => 'electronics,software,books,courses,services',
                'description' => 'Comma-separated list of allowed purchase categories',
            ],
        ];
    }
    
    /**
     * Process payment via affiliate credits
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        if (!is_array($offer['pricing']['affiliate'] ?? null)
            || !array_key_exists('credit_amount', $offer['pricing']['affiliate'])) {
            return new WP_Error(
                'invalid_affiliate_price',
                __('Affiliate credit amount is not configured for this offer', 'flosc'),
                ['status' => 400]
            );
        }
        $required = floatval($offer['pricing']['affiliate']['credit_amount']);

        if ($required <= 0) {
            return new WP_Error(
                'invalid_affiliate_price',
                __('Affiliate purchases require a positive credit amount', 'flosc'),
                ['status' => 400]
            );
        }
        
        $credits = $this->get_credits($user_id);
        
        if ($credits < $required) {
            return new WP_Error(
                'insufficient_credits',
                sprintf(
                    'Insufficient affiliate credits. Required: $%.2f, Available: $%.2f. ' .
                    'Declare a purchase intent to earn credits!',
                    $required,
                    $credits
                ),
                [
                    'required' => $required,
                    'available' => $credits,
                    'shortfall' => $required - $credits,
                ]
            );
        }
        
        // Deduct credits (atomic; fail closed if concurrent debit wins).
        $deducted = $this->deduct_credits($user_id, $required, 'Purchase: ' . ($offer['name'] ?? $offer['id'] ?? 'offer'));
        if (is_wp_error($deducted)) {
            return $deducted;
        }

        return [
            'success'            => true,
            'transaction_id'     => 'affiliate_purchase_' . uniqid(),
            'amount'             => $required,
            'currency'           => 'affiliate_credits',
            'remaining_credits'  => $this->get_credits($user_id),
            'status'             => 'completed',
        ];
    }
    
    /**
     * Get client config
     */
    public function get_client_config() {
        return [
            'enabled' => $this->is_configured(),
            'categories' => explode(',', $this->get_setting('enabled_categories', 'electronics,software,books')),
            'minValue' => floatval($this->get_setting('min_intent_value', 20)),
        ];
    }
    
    // =========================================================================
    // PURCHASE INTENT SYSTEM
    // =========================================================================
    
    /**
     * User declares a purchase intent
     * 
     * @param int $user_id
     * @param array $intent [
     *   'description' => 'MacBook Pro 14"',
     *   'category' => 'electronics',
     *   'expected_price' => 2000,
     *   'timeframe' => 'this_week', // this_week, this_month, exploring
     *   'notes' => 'Need for music production',
     * ]
     */
    public function declare_intent($user_id, $intent) {
        $intents = $this->get_intents($user_id);
        
        $intent_id = 'intent_' . uniqid();
        
        $intent = wp_parse_args($intent, [
            'id' => $intent_id,
            'description' => '',
            'category' => 'general',
            'expected_price' => 0,
            'timeframe' => 'exploring',
            'notes' => '',
            'status' => 'active', // active, fulfilled, expired, canceled
            'created_at' => current_time('mysql'),
            'offers_shown' => [],
            'clicks' => [],
            'conversions' => [],
        ]);
        
        $intents[$intent_id] = $intent;
        update_user_meta($user_id, $this->intents_meta_key, $intents);
        
        do_action('flosc_intent_declared', $user_id, $intent);
        
        return $intent;
    }
    
    /**
     * Get user's purchase intents
     */
    public function get_intents($user_id, $status = null) {
        $intents = get_user_meta($user_id, $this->intents_meta_key, true) ?: [];
        
        if ($status) {
            $intents = array_filter($intents, function($i) use ($status) {
                return $i['status'] === $status;
            });
        }
        
        return $intents;
    }
    
    /**
     * Update intent status
     */
    public function update_intent($user_id, $intent_id, $updates) {
        $intents = $this->get_intents($user_id);
        
        if (!isset($intents[$intent_id])) {
            return new WP_Error('not_found', __('Intent not found', 'flosc'));
        }
        
        $intents[$intent_id] = array_merge($intents[$intent_id], $updates);
        update_user_meta($user_id, $this->intents_meta_key, $intents);
        
        return $intents[$intent_id];
    }
    
    // =========================================================================
    // AFFILIATE OFFER MATCHING
    // =========================================================================
    
    /**
     * Find affiliate offers for an intent
     */
    public function find_offers_for_intent($intent) {
        $offers = [];
        
        // Amazon search
        if ($this->get_setting('amazon_tag')) {
            $amazon_offers = $this->search_amazon($intent['description'], $intent['category']);
            $offers = array_merge($offers, $amazon_offers);
        }
        
        // CJ search
        if ($this->get_setting('cj_id') && $this->get_setting('cj_api_key')) {
            $cj_offers = $this->search_cj($intent['description'], $intent['category']);
            $offers = array_merge($offers, $cj_offers);
        }
        
        // ShareASale search
        if ($this->get_setting('shareasale_id')) {
            $sas_offers = $this->search_shareasale($intent['description'], $intent['category']);
            $offers = array_merge($offers, $sas_offers);
        }
        
        // Custom endpoint
        if ($this->get_setting('custom_endpoint')) {
            $custom_offers = $this->search_custom($intent);
            $offers = array_merge($offers, $custom_offers);
        }
        
        // Sort by potential commission
        usort($offers, function($a, $b) {
            return ($b['estimated_commission'] ?? 0) - ($a['estimated_commission'] ?? 0);
        });
        
        return apply_filters('flosc_affiliate_offers', $offers, $intent);
    }
    
    /**
     * Search Amazon for products
     */
    private function search_amazon($query, $category = null) {
        $tag = $this->get_setting('amazon_tag');
        if (!$tag) return [];
        
        // Amazon Product Advertising API would go here
        // For now, return affiliate link format
        $offers = [];
        
        // Basic Amazon search link (users can search themselves)
        $search_url = 'https://www.amazon.com/s?' . http_build_query([
            'k' => $query,
            'tag' => $tag,
        ]);
        
        $offers[] = [
            'source' => 'amazon',
            'title' => 'Search Amazon for: ' . $query,
            'url' => $search_url,
            'estimated_commission' => 0, // Unknown until purchase
            'commission_rate' => '1-10%',
            'note' => 'Earn affiliate credit when you purchase through this link',
        ];
        
        return $offers;
    }
    
    /**
     * Search CJ (Commission Junction)
     */
    private function search_cj($query, $category = null) {
        $cj_id = $this->get_setting('cj_id');
        $api_key = $this->get_setting('cj_api_key');
        
        if (!$cj_id || !$api_key) return [];
        
        // CJ API integration would go here
        // Returns merchant offers matching the query
        
        return [];
    }
    
    /**
     * Search ShareASale
     */
    private function search_shareasale($query, $category = null) {
        $sas_id = $this->get_setting('shareasale_id');
        
        if (!$sas_id) return [];
        
        // ShareASale API integration would go here
        
        return [];
    }
    
    /**
     * Search custom endpoint (your own aggregation service)
     */
    private function search_custom($intent) {
        $endpoint = $this->get_setting('custom_endpoint');
        $api_key = $this->get_setting('custom_api_key');
        
        if (!$endpoint) return [];
        
        $response = flosc_safe_remote_request('POST', $endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'query' => $intent['description'],
                'category' => $intent['category'],
                'price_range' => $intent['expected_price'],
            ]),
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['offers'] ?? [];
    }
    
    // =========================================================================
    // TRACKING & CONVERSIONS
    // =========================================================================
    
    /**
     * Track a click on an affiliate offer
     */
    public function track_click($user_id, $intent_id, $offer) {
        $user_id   = absint($user_id);
        $intent_id = sanitize_key((string) $intent_id);
        $offer     = is_array($offer) ? $offer : array();
        $safe_offer = array(
            'source' => sanitize_key((string) ($offer['source'] ?? '')),
            'url'    => esc_url_raw((string) ($offer['url'] ?? '')),
            'title'  => sanitize_text_field((string) ($offer['title'] ?? '')),
            'id'     => sanitize_text_field((string) ($offer['id'] ?? '')),
        );
        $intents = $this->get_intents($user_id);

        if ($user_id > 0 && $intent_id !== '' && isset($intents[ $intent_id ])) {
            $intents[ $intent_id ]['clicks'][] = array(
                'offer_source' => $safe_offer['source'],
                'offer_url'    => $safe_offer['url'],
                'timestamp'    => current_time('mysql'),
            );
            update_user_meta($user_id, $this->intents_meta_key, $intents);
        }

        do_action('flosc_affiliate_click', $user_id, $intent_id, $safe_offer);
    }

    /**
     * Record a conversion (called by webhook or postback)
     */
    public function record_conversion($tracking_data) {
        // Tracking data comes from affiliate network postback.
        $tracking_data = is_array($tracking_data) ? $tracking_data : array();
        $safe          = array(
            'user_id'    => absint($tracking_data['user_id'] ?? 0),
            'intent_id'  => sanitize_key((string) ($tracking_data['intent_id'] ?? '')),
            'commission' => floatval($tracking_data['commission'] ?? 0),
            'order_id'   => sanitize_text_field((string) ($tracking_data['order_id'] ?? '')),
            'source'     => sanitize_key((string) ($tracking_data['source'] ?? 'unknown')),
        );

        $user_id    = $safe['user_id'];
        $commission = $safe['commission'];

        if (!$user_id || $commission <= 0) {
            return new WP_Error('invalid_data', __('Invalid conversion data', 'flosc'));
        }

        // Apply credit rate
        $rate = floatval($this->get_setting('credit_rate', 100)) / 100;
        $credit = $commission * $rate;

        // Add credits
        $this->add_credits($user_id, $credit, array(
            'source'     => $safe['source'],
            'order_id'   => $safe['order_id'],
            'commission' => $commission,
            'intent_id'  => $safe['intent_id'],
        ));

        // Update intent if provided
        if ($safe['intent_id'] !== '') {
            $this->update_intent($user_id, $safe['intent_id'], array(
                'status'      => 'fulfilled',
                'conversions' => array_merge(
                    $this->get_intents($user_id)[ $safe['intent_id'] ]['conversions'] ?? array(),
                    array( $safe )
                ),
            ));
        }

        // Also credit tokens if token provider is active
        $token_provider = flosc_sale()->get_provider('tokens');
        if ($token_provider) {
            $token_provider->credit_from_affiliate($user_id, $credit, $safe);
        }

        do_action('flosc_affiliate_conversion', $user_id, $credit, $safe);

        return array(
            'success'       => true,
            'credits_added' => $credit,
            'new_balance'   => $this->get_credits($user_id),
        );
    }
    
    // =========================================================================
    // CREDITS MANAGEMENT
    // =========================================================================
    
    /**
     * Get user's affiliate credits (in dollars)
     */
    public function get_credits($user_id) {
        $credits = get_user_meta($user_id, $this->credits_meta_key, true);
        return floatval($credits) ?: 0;
    }
    
    /**
     * Add affiliate credits
     */
    public function add_credits($user_id, $amount, $meta = []) {
        $current = $this->get_credits($user_id);
        $new_balance = $current + floatval($amount);
        
        update_user_meta($user_id, $this->credits_meta_key, $new_balance);
        
        // Log
        $this->log_credit_change($user_id, 'credit', $amount, $meta);
        
        return $new_balance;
    }
    
    /**
     * Deduct affiliate credits (atomic conditional debit — PAY-ACC-01).
     */
    public function deduct_credits($user_id, $amount, $reason = '') {
        $user_id = absint($user_id);
        $amount  = floatval($amount);
        if ($user_id <= 0) {
            return new WP_Error('invalid_user', __('Invalid user', 'flosc'));
        }
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', __('Amount must be positive', 'flosc'));
        }

        global $wpdb;
        $meta_key = $this->credits_meta_key;
        $locked   = false;
        $current  = 0.0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic debit under row lock
        $wpdb->query('START TRANSACTION');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1 FOR UPDATE",
                $user_id,
                $meta_key
            )
        );
        if ($row) {
            $locked  = true;
            $current = floatval($row->meta_value);
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- atomic usermeta balance row under transaction
            $wpdb->insert(
                $wpdb->usermeta,
                [
                    'user_id'    => $user_id,
                    'meta_key'   => $meta_key,
                    'meta_value' => '0',
                ],
                ['%d', '%s', '%s']
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- end of atomic ledger block
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1 FOR UPDATE",
                    $user_id,
                    $meta_key
                )
            );
            if ($row) {
                $locked  = true;
                $current = floatval($row->meta_value);
            }
        }

        if (!$locked) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
            $wpdb->query('ROLLBACK');
            return new WP_Error('debit_failed', __('Could not lock affiliate credit balance', 'flosc'));
        }

        if ($current < $amount) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
            $wpdb->query('ROLLBACK');
            return new WP_Error('insufficient', __('Insufficient credits', 'flosc'));
        }

        $new_balance = $current - $amount;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- atomic debit by umeta_id under row lock
        $updated = $wpdb->update(
            $wpdb->usermeta,
            ['meta_value' => (string) $new_balance],
            ['umeta_id' => absint($row->umeta_id)],
            ['%s'],
            ['%d']
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- end of atomic ledger block
        if (false === $updated) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
            $wpdb->query('ROLLBACK');
            return new WP_Error('debit_failed', __('Affiliate credit debit failed', 'flosc'));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic token/affiliate ledger under transaction; no WP API for row lock
        $wpdb->query('COMMIT');
        wp_cache_delete($user_id, 'user_meta');

        $this->log_credit_change($user_id, 'debit', $amount, ['reason' => $reason]);

        return $new_balance;
    }
    
    /**
     * Log credit changes
     */
    private function log_credit_change($user_id, $type, $amount, $meta = []) {
        $log = get_user_meta($user_id, '_flosc_affiliate_credit_log', true) ?: [];
        
        $log[] = array_merge([
            'type' => $type,
            'amount' => $amount,
            'timestamp' => current_time('mysql'),
        ], $meta);
        
        // Keep last 100
        $log = array_slice($log, -100);
        
        update_user_meta($user_id, '_flosc_affiliate_credit_log', $log);
    }
    
    /**
     * Handle webhook from affiliate networks
     */
    public function handle_webhook($payload, $headers = []) {
        // Determine source from headers or payload
        $source = $this->detect_webhook_source($headers, $payload);
        
        // Parse payload based on source
        $tracking_data = $this->parse_webhook_payload($source, $payload);
        
        if (is_wp_error($tracking_data)) {
            return $tracking_data;
        }
        
        return $this->record_conversion($tracking_data);
    }
    
    private function detect_webhook_source($headers, $payload) {
        // Logic to detect Amazon, CJ, ShareASale, etc. from webhook
        return 'custom';
    }
    
    private function parse_webhook_payload($source, $payload) {
        // Pass 8: field-sanitize after json_decode of untrusted webhook body.
        $data = [];
        if (is_string($payload)) {
            if ($payload !== '' && strlen($payload) <= 65536) {
                $decoded = json_decode($payload, true, 16);
                if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                    $data = $decoded;
                }
            }
        } elseif (is_array($payload)) {
            $data = $payload;
        }

        $user_raw = $data['user_id'] ?? $data['subid'] ?? null;
        $user_id = is_numeric($user_raw) ? absint($user_raw) : 0;
        $commission = isset($data['commission']) ? floatval($data['commission']) : floatval($data['amount'] ?? 0);

        return [
            'source' => sanitize_key((string) $source),
            'user_id' => $user_id > 0 ? $user_id : null,
            'commission' => $commission,
            'order_id' => sanitize_text_field((string) ($data['order_id'] ?? $data['transaction_id'] ?? '')),
            'intent_id' => sanitize_text_field((string) ($data['intent_id'] ?? '')),
        ];
    }
}
