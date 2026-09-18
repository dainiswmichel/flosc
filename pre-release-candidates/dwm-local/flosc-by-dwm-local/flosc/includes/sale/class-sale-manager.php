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
        require_once __DIR__ . '/providers/class-paypal-provider.php'; // v1.6.9
        
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
        $this->providers['paypal'] = new FLOSC_PayPal_Provider(); // v1.6.9
        
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
     * Whether a provider result is settled payment suitable for granting access.
     *
     * Fail-closed: redirect initiation, client_secret, requires_action, processing,
     * and any result without a confirmed transaction_id never unlock access.
     *
     * @param mixed $result Provider process_payment() return value.
     * @return bool
     */
    public function is_payment_settled($result) {
        if (!is_array($result)) {
            return false;
        }

        // Explicit incomplete / initiation-only states.
        if (!empty($result['requires_action'])) {
            return false;
        }
        if (!empty($result['redirect'])) {
            return false;
        }
        if (!empty($result['pending'])) {
            return false;
        }

        $status = strtolower((string) ($result['status'] ?? ''));
        if (in_array($status, [
            'requires_action',
            'requires_payment_method',
            'requires_confirmation',
            'requires_capture',
            'processing',
            'canceled',
            'cancelled',
            'failed',
        ], true)) {
            return false;
        }

        // A client_secret alone is proof of intent creation, not payment.
        if (!empty($result['client_secret']) && empty($result['success'])) {
            return false;
        }

        $txn = isset($result['transaction_id']) ? (string) $result['transaction_id'] : '';
        if ($txn === '') {
            return false;
        }

        // trialing is not settled payment unless the provider set settled/paid explicitly
        // after an offer-level allow_trial check (see Stripe create_subscription).
        if ($status === 'trialing' && empty($result['settled']) && empty($result['paid'])) {
            return false;
        }

        if (!empty($result['success'])) {
            return true;
        }
        if (!empty($result['settled']) || !empty($result['paid'])) {
            return true;
        }
        if (in_array($status, ['succeeded', 'active', 'completed', 'captured'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Offer is free only when explicitly marked free — never by missing price.
     *
     * @param array $offer Offer row.
     * @return bool
     */
    public function offer_is_explicitly_free(array $offer) {
        if (!empty($offer['is_free'])) {
            return true;
        }
        if (isset($offer['pricing']['type']) && (string) $offer['pricing']['type'] === 'free') {
            return true;
        }
        if (isset($offer['type']) && (string) $offer['type'] === 'free') {
            return true;
        }
        // Explicit numeric zero only when the price key is present.
        if (is_array($offer['pricing'] ?? null) && array_key_exists('price', $offer['pricing'])) {
            return floatval($offer['pricing']['price']) <= 0.0;
        }
        if (array_key_exists('price', $offer)) {
            return floatval($offer['price']) <= 0.0;
        }
        return false;
    }

    /**
     * Whether an offer may be purchased or free-granted (status=active).
     *
     * @param array $offer Offer row.
     * @return bool
     */
    public function offer_is_active_for_purchase(array $offer) {
        $status = sanitize_key((string) ($offer['status'] ?? ''));
        if ($status === 'active') {
            return true;
        }
        // Legacy boolean flag when status is omitted.
        if ($status === '' && !empty($offer['active'])) {
            return true;
        }
        return false;
    }

    /**
     * Validate offer before free or paid fulfillment.
     *
     * @param array  $offer Offer row.
     * @param string $mode  free|paid
     * @return true|WP_Error
     */
    public function validate_offer_for_purchase(array $offer, $mode = 'paid') {
        $mode = sanitize_key((string) $mode);
        if (!$this->offer_is_active_for_purchase($offer)) {
            return new WP_Error(
                'offer_inactive',
                __('This offer is not active', 'flosc'),
                ['status' => 400]
            );
        }
        $is_free = $this->offer_is_explicitly_free($offer);
        if ($mode === 'free') {
            if (!$is_free) {
                return new WP_Error(
                    'not_free',
                    __('This offer requires payment', 'flosc'),
                    ['status' => 400]
                );
            }
            return true;
        }
        // Paid path: free-only offers should use free branch.
        if ($is_free) {
            return new WP_Error(
                'use_free_path',
                __('This free offer must be claimed without a payment provider', 'flosc'),
                ['status' => 400]
            );
        }
        return true;
    }

    /**
     * Claim a processor transaction for exactly one (provider, txn) → (user, offer).
     *
     * Prevents PAY-02: reusing one settled payment to unlock a different offer.
     * Same user + same offer is idempotent (webhook + browser race).
     *
     * @param string $provider        Provider id (stripe, paypal, tokens, free, …).
     * @param string $transaction_id  Processor transaction / receipt id.
     * @param int    $user_id         Buyer WordPress user id.
     * @param string $offer_id        Offer being fulfilled.
     * @param array  $extra           Optional amount/currency/mode metadata.
     * @return true|string|WP_Error true on new claim, 'already' on idempotent repeat, WP_Error on conflict.
     */
    public function claim_transaction_fulfillment($provider, $transaction_id, $user_id, $offer_id, $extra = []) {
        $provider        = sanitize_key((string) $provider);
        $transaction_id  = sanitize_text_field((string) $transaction_id);
        $offer_id        = sanitize_text_field((string) $offer_id);
        $user_id         = absint($user_id);
        $extra           = is_array($extra) ? $extra : [];

        if ($provider === '' || $transaction_id === '' || $offer_id === '' || $user_id <= 0) {
            return new WP_Error(
                'invalid_fulfillment',
                __('Missing fulfillment binding fields', 'flosc'),
                ['status' => 400]
            );
        }

        $option_key = '_flosc_fulfill_' . md5($provider . '|' . $transaction_id);
        $record     = [
            'provider'       => $provider,
            'transaction_id' => $transaction_id,
            'user_id'        => $user_id,
            'offer_id'       => $offer_id,
            'amount'         => $extra['amount'] ?? null,
            'currency'       => isset($extra['currency']) ? sanitize_text_field((string) $extra['currency']) : null,
            'mode'           => isset($extra['mode']) ? sanitize_key((string) $extra['mode']) : null,
            'claimed_at'     => time(),
        ];

        // add_option is atomic when the key is new — first writer wins.
        $added = add_option($option_key, $record, '', 'no');
        if ($added) {
            return true;
        }

        $existing = get_option($option_key);
        if (!is_array($existing)) {
            return new WP_Error(
                'transaction_conflict',
                __('This payment has already been fulfilled', 'flosc'),
                ['status' => 409]
            );
        }

        $same_user  = (int) ($existing['user_id'] ?? 0) === $user_id;
        $same_offer = sanitize_text_field((string) ($existing['offer_id'] ?? '')) === $offer_id;
        if ($same_user && $same_offer) {
            return 'already';
        }

        return new WP_Error(
            'transaction_reuse',
            __('This payment has already been used for a different purchase', 'flosc'),
            ['status' => 409]
        );
    }

    /**
     * Fulfill a settled purchase: claim transaction, grant offer, log, fire hooks.
     *
     * Call only after payment is confirmed settled (not requires_action / redirect).
     *
     * @param int    $user_id
     * @param array  $offer
     * @param string $provider_id
     * @param array  $transaction Must include transaction_id; may include amount/currency.
     * @return array|WP_Error
     */
    public function fulfill_settled_purchase($user_id, array $offer, $provider_id, array $transaction) {
        $user_id     = absint($user_id);
        $provider_id = sanitize_key((string) $provider_id);
        $offer_id    = sanitize_text_field((string) ($offer['id'] ?? ''));
        $txn_id      = sanitize_text_field((string) ($transaction['transaction_id'] ?? ''));

        if ($user_id <= 0 || $offer_id === '' || $txn_id === '' || $provider_id === '') {
            return new WP_Error(
                'invalid_fulfillment',
                __('Cannot fulfill purchase with incomplete binding', 'flosc'),
                ['status' => 400]
            );
        }

        // Ensure provider is stamped on the transaction for the access ledger.
        if (empty($transaction['provider'])) {
            $transaction['provider'] = $provider_id;
        }

        $claim = $this->claim_transaction_fulfillment(
            $provider_id,
            $txn_id,
            $user_id,
            $offer_id,
            [
                'amount'   => $transaction['amount'] ?? null,
                'currency' => $transaction['currency'] ?? null,
                'mode'     => $transaction['mode'] ?? null,
            ]
        );
        if (is_wp_error($claim)) {
            return $claim;
        }

        if ($claim === 'already') {
            return [
                'success'           => true,
                'already_fulfilled' => true,
                'offer'             => $offer,
                'provider'          => $provider_id,
                'transaction'       => $transaction,
                'access'            => $this->access_manager->get_user_access($user_id),
            ];
        }

        $this->access_manager->grant_from_offer($user_id, $offer, $transaction);
        $this->log_purchase($user_id, $offer, $provider_id, $transaction);

        $purchase_payload = [
            'offer_id'       => $offer_id,
            'grants_level'   => $offer['grants']['level'] ?? ($offer['grants_level'] ?? ''),
            'provider'       => $provider_id,
            'transaction_id' => $txn_id,
            'amount'         => $transaction['amount'] ?? ($offer['price'] ?? 0),
            'currency'       => $transaction['currency'] ?? '',
            'timestamp'      => time(),
        ];
        if (!empty($transaction['flow_id'])) {
            $purchase_payload['flow_id'] = sanitize_text_field((string) $transaction['flow_id']);
        }
        do_action('flosc_purchase_completed', $user_id, $purchase_payload);

        return [
            'success'     => true,
            'offer'       => $offer,
            'provider'    => $provider_id,
            'transaction' => $transaction,
            'access'      => $this->access_manager->get_user_access($user_id),
        ];
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
              return new WP_Error('invalid_offer', __('Offer not found', 'flosc'));
        }

        $valid = $this->validate_offer_for_purchase($offer, 'paid');
        if (is_wp_error($valid)) {
            return $valid;
        }
        
        // Get provider
        $provider = $this->get_provider($provider_id);
        if (!$provider) {
              return new WP_Error('invalid_provider', __('Payment provider not found', 'flosc'));
        }
        
        if (!$provider->is_configured()) {
              return new WP_Error('provider_not_configured', __('Payment provider not configured', 'flosc'));
        }
        
        // Process payment through provider
        $result = $provider->process_payment($user_id, $offer, $payment_data);
        
        if (is_wp_error($result)) {
            do_action('flosc_purchase_failed', $user_id, $offer_id, $provider_id, $result);
            return $result;
        }

        // PAY-01: Incomplete results (ClickBank redirect, Stripe requires_action / client_secret)
        // must never grant access. Return the initiation payload for the client to continue.
        if (!$this->is_payment_settled($result)) {
            return [
                'success'     => false,
                'pending'     => true,
                'provider'    => $provider_id,
                'offer_id'    => $offer_id,
                'transaction' => is_array($result) ? $result : [],
                'message'     => __('Payment initiated. Access is granted only after payment is confirmed.', 'flosc'),
            ];
        }

        // PAY-02 + PAY-01 settled path: bind txn→offer→user before grant.
        return $this->fulfill_settled_purchase($user_id, $offer, $provider_id, $result);
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
     * v1.6.2: Flow-aware — accepts flow_id to read from per-flow storage
     */
    public function get_available_offers($user_id = null, $flow_id = null) {
        $all_offers = $this->offer_manager->get_active_offers($flow_id);
        
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
