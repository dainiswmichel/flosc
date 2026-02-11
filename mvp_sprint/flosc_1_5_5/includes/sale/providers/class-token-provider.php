<?php
/**
 * FLOSC Token Payment Provider
 * 
 * Internal credit/token system for pay-per-use features.
 * Tokens can be earned through affiliate purchases or bought with real money.
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Token_Provider extends FLOSC_Payment_Provider {
    
    private $balance_meta_key = '_flosc_token_balance';
    private $ledger_meta_key = '_flosc_token_ledger';
    
    public function get_id() {
        return 'tokens';
    }
    
    public function get_name() {
        return 'Tokens';
    }
    
    public function get_description() {
        return 'Internal credit system for pay-per-use features. Users earn or purchase tokens.';
    }
    
    public function get_icon() {
        return '🪙';
    }
    
    public function is_configured() {
        // Tokens are always "configured" - it's an internal system
        return true;
    }
    
    /**
     * Settings for admin
     */
    public function get_settings_fields() {
        return [
            'token_name' => [
                'type' => 'text',
                'label' => 'Token Name',
                'default' => 'Credits',
                'description' => 'What to call your tokens (e.g., Credits, Coins, Points)',
            ],
            'token_symbol' => [
                'type' => 'text',
                'label' => 'Token Symbol',
                'default' => '🪙',
                'description' => 'Emoji or symbol for tokens',
            ],
            'signup_bonus' => [
                'type' => 'number',
                'label' => 'Signup Bonus',
                'default' => 10,
                'description' => 'Free tokens given on registration',
            ],
            'referral_bonus' => [
                'type' => 'number',
                'label' => 'Referral Bonus',
                'default' => 25,
                'description' => 'Tokens given when a referral signs up',
            ],
        ];
    }
    
    /**
     * Get client config
     */
    public function get_client_config() {
        return [
            'name' => $this->get_setting('token_name', 'Credits'),
            'symbol' => $this->get_setting('token_symbol', '🪙'),
        ];
    }
    
    /**
     * Process payment (spend tokens)
     */
    public function process_payment($user_id, $offer, $payment_data = []) {
        $cost = $offer['pricing']['tokens']['cost'] ?? 0;
        
        if ($cost <= 0) {
            // Free with tokens
            return [
                'success' => true,
                'transaction_id' => 'token_free_' . uniqid(),
                'amount' => 0,
                'currency' => 'tokens',
            ];
        }
        
        $balance = $this->get_balance($user_id);
        
        if ($balance < $cost) {
            return new WP_Error(
                'insufficient_tokens',
                sprintf(
                    'Insufficient tokens. Required: %d, Available: %d',
                    $cost,
                    $balance
                ),
                ['required' => $cost, 'available' => $balance]
            );
        }
        
        // Deduct tokens
        $this->deduct($user_id, $cost, 'Purchase: ' . $offer['name']);
        
        return [
            'success' => true,
            'transaction_id' => 'token_purchase_' . uniqid(),
            'amount' => $cost,
            'currency' => 'tokens',
            'new_balance' => $this->get_balance($user_id),
        ];
    }
    
    /**
     * Get user's token balance
     */
    public function get_balance($user_id) {
        $balance = get_user_meta($user_id, $this->balance_meta_key, true);
        return intval($balance) ?: 0;
    }
    
    /**
     * Add tokens to user's balance
     */
    public function credit($user_id, $amount, $reason = '', $meta = []) {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive');
        }
        
        $current = $this->get_balance($user_id);
        $new_balance = $current + $amount;
        
        update_user_meta($user_id, $this->balance_meta_key, $new_balance);
        
        $this->log_transaction($user_id, [
            'type' => 'credit',
            'amount' => $amount,
            'reason' => $reason,
            'balance_before' => $current,
            'balance_after' => $new_balance,
            'meta' => $meta,
            'timestamp' => current_time('mysql'),
        ]);
        
        do_action('flosc_tokens_credited', $user_id, $amount, $reason);
        
        return $new_balance;
    }
    
    /**
     * Deduct tokens from user's balance
     */
    public function deduct($user_id, $amount, $reason = '', $meta = []) {
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive');
        }
        
        $current = $this->get_balance($user_id);
        
        if ($current < $amount) {
            return new WP_Error('insufficient_balance', 'Insufficient token balance');
        }
        
        $new_balance = $current - $amount;
        
        update_user_meta($user_id, $this->balance_meta_key, $new_balance);
        
        $this->log_transaction($user_id, [
            'type' => 'debit',
            'amount' => $amount,
            'reason' => $reason,
            'balance_before' => $current,
            'balance_after' => $new_balance,
            'meta' => $meta,
            'timestamp' => current_time('mysql'),
        ]);
        
        do_action('flosc_tokens_debited', $user_id, $amount, $reason);
        
        return $new_balance;
    }
    
    /**
     * Set balance directly (admin function)
     */
    public function set_balance($user_id, $amount, $reason = 'Admin adjustment') {
        $current = $this->get_balance($user_id);
        
        update_user_meta($user_id, $this->balance_meta_key, max(0, intval($amount)));
        
        $this->log_transaction($user_id, [
            'type' => 'adjustment',
            'amount' => $amount - $current,
            'reason' => $reason,
            'balance_before' => $current,
            'balance_after' => $amount,
            'timestamp' => current_time('mysql'),
        ]);
        
        return $amount;
    }
    
    /**
     * Log transaction to ledger
     */
    private function log_transaction($user_id, $transaction) {
        $ledger = get_user_meta($user_id, $this->ledger_meta_key, true) ?: [];
        
        // Keep last 100 transactions
        $ledger = array_slice($ledger, -99);
        $ledger[] = $transaction;
        
        update_user_meta($user_id, $this->ledger_meta_key, $ledger);
    }
    
    /**
     * Get user's transaction ledger
     */
    public function get_ledger($user_id, $limit = 50) {
        $ledger = get_user_meta($user_id, $this->ledger_meta_key, true) ?: [];
        return array_slice(array_reverse($ledger), 0, $limit);
    }
    
    /**
     * Grant signup bonus
     */
    public function grant_signup_bonus($user_id) {
        $bonus = intval($this->get_setting('signup_bonus', 10));
        
        if ($bonus > 0) {
            $this->credit($user_id, $bonus, 'Welcome bonus');
        }
        
        return $bonus;
    }
    
    /**
     * Grant referral bonus
     */
    public function grant_referral_bonus($referrer_id, $referred_id) {
        $bonus = intval($this->get_setting('referral_bonus', 25));
        
        if ($bonus > 0) {
            $referred_user = get_user_by('ID', $referred_id);
            $this->credit(
                $referrer_id, 
                $bonus, 
                'Referral: ' . ($referred_user ? $referred_user->display_name : 'User #' . $referred_id)
            );
        }
        
        return $bonus;
    }
    
    /**
     * Convert tokens from affiliate earnings
     */
    public function credit_from_affiliate($user_id, $affiliate_amount, $affiliate_meta = []) {
        // Conversion rate: $1 affiliate commission = X tokens
        $rate = intval($this->get_setting('affiliate_conversion_rate', 10)); // Default: $1 = 10 tokens
        $tokens = round($affiliate_amount * $rate);
        
        if ($tokens > 0) {
            return $this->credit($user_id, $tokens, 'Affiliate commission', array_merge($affiliate_meta, [
                'affiliate_amount' => $affiliate_amount,
                'conversion_rate' => $rate,
            ]));
        }
        
        return 0;
    }
    
    /**
     * Token costs for actions (configurable)
     */
    public function get_action_costs() {
        return apply_filters('flosc_token_costs', [
            'ai_query' => intval($this->get_setting('cost_ai_query', 1)),
            'stt_minute' => intval($this->get_setting('cost_stt_minute', 2)),
            'quiz_attempt' => intval($this->get_setting('cost_quiz', 0)),
            'lesson_view' => intval($this->get_setting('cost_lesson', 5)),
            'certificate' => intval($this->get_setting('cost_certificate', 10)),
        ]);
    }
    
    /**
     * Check if user can afford an action
     */
    public function can_afford($user_id, $action) {
        $costs = $this->get_action_costs();
        $cost = $costs[$action] ?? 0;
        
        if ($cost === 0) {
            return true;
        }
        
        return $this->get_balance($user_id) >= $cost;
    }
    
    /**
     * Charge for an action
     */
    public function charge_for_action($user_id, $action, $meta = []) {
        $costs = $this->get_action_costs();
        $cost = $costs[$action] ?? 0;
        
        if ($cost === 0) {
            return true;
        }
        
        $result = $this->deduct($user_id, $cost, 'Action: ' . $action, $meta);
        
        return !is_wp_error($result);
    }
}
