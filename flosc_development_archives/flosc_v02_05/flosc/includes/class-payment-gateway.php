<?php
/**
 * =============================================================================
 * FLOSC PAYMENT GATEWAY (Abstract Base Class)
 * =============================================================================
 * 
 * Abstract base class for payment gateway implementations.
 * 
 * CURRENT IMPLEMENTATIONS:
 *   - FLOSC_Stripe_Checkout (class-stripe-checkout.php)
 * 
 * PLANNED IMPLEMENTATIONS:
 *   - FLOSC_PayPal_Checkout
 *   - FLOSC_ClickBank_Gateway
 * 
 * =============================================================================
 * WHY THIS EXISTS
 * =============================================================================
 * 
 * This provides a consistent interface for all payment gateways.
 * Each gateway must implement these methods:
 * 
 *   - create_checkout_session() - Create payment and return redirect URL
 *   - handle_webhook()          - Process payment notifications
 *   - verify_payment()          - Verify a specific payment
 * 
 * =============================================================================
 * FUTURE: MULTI-GATEWAY SUPPORT
 * =============================================================================
 * 
 * When multiple gateways are fully implemented:
 * 
 *   $gateway = FLOSC_Payment_Gateway::get_gateway($gateway_type);
 *   $checkout_url = $gateway->create_checkout_session($user_id, $amount);
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

/**
 * Abstract Payment Gateway
 * 
 * All payment gateway implementations should extend this class.
 */
abstract class FLOSC_Payment_Gateway {
    
    /**
     * Reference to main FLOSC framework
     * @var FLOSC_Framework
     */
    protected $flosc;
    
    
    /**
     * Constructor
     * 
     * @param FLOSC_Framework $flosc_framework
     */
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }
    
    
    /* =========================================================================
     * ABSTRACT METHODS (must be implemented by subclasses)
     * =========================================================================
     */
    
    /**
     * Create checkout session and return redirect URL
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response Contains checkout_url
     */
    abstract public function create_checkout_session($request);
    
    
    /**
     * Handle webhook/IPN from payment provider
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    abstract public function handle_webhook($request);
    
    
    /* =========================================================================
     * SHARED METHODS (available to all gateways)
     * =========================================================================
     */
    
    /**
     * Grant access to user after successful payment
     * 
     * @param int    $user_id        WordPress user ID
     * @param string $transaction_id Payment reference
     * @return string Method used to grant access
     */
    protected function grant_access($user_id, $transaction_id) {
        return $this->flosc->grant_paid_access(
            $user_id,
            $this->get_gateway_name(),
            $transaction_id
        );
    }
    
    
    /**
     * Revoke access from user (e.g., refund)
     * 
     * @param int    $user_id WordPress user ID
     * @param string $reason  Reason for revocation
     */
    protected function revoke_access($user_id, $reason) {
        $this->flosc->revoke_paid_access($user_id, $reason);
    }
    
    
    /**
     * Get gateway name for logging
     * 
     * @return string
     */
    abstract protected function get_gateway_name();
    
    
    /**
     * Check if gateway is configured
     * 
     * @return bool
     */
    abstract public function is_configured();
    
    
    /* =========================================================================
     * FACTORY METHOD
     * =========================================================================
     */
    
    /**
     * Get payment gateway instance by type
     * 
     * @param string          $type           Gateway type (stripe, paypal, clickbank)
     * @param FLOSC_Framework $flosc_framework
     * @return FLOSC_Payment_Gateway|null
     */
    public static function get_gateway($type, $flosc_framework) {
        switch ($type) {
            case 'stripe':
                return new FLOSC_Stripe_Checkout($flosc_framework);
                
            case 'paypal':
                // TODO: Implement PayPal gateway
                // return new FLOSC_PayPal_Checkout($flosc_framework);
                return null;
                
            case 'clickbank':
                // TODO: Implement ClickBank gateway
                // return new FLOSC_ClickBank_Gateway($flosc_framework);
                return null;
                
            default:
                return null;
        }
    }
}


/* =============================================================================
 * PAYPAL GATEWAY PLACEHOLDER
 * =============================================================================
 * 
 * When implementing PayPal:
 * 
 * class FLOSC_PayPal_Checkout extends FLOSC_Payment_Gateway {
 *     
 *     public function create_checkout_session($request) {
 *         // Create PayPal order using Orders API
 *         // https://developer.paypal.com/docs/api/orders/v2/
 *         
 *         $client_id = $this->flosc->get_config('paypal_client_id');
 *         $secret = $this->flosc->get_config('paypal_client_secret');
 *         
 *         // 1. Get access token
 *         // 2. Create order
 *         // 3. Return approval URL
 *     }
 *     
 *     public function handle_webhook($request) {
 *         // Verify webhook signature
 *         // Process PAYMENT.CAPTURE.COMPLETED event
 *         // Grant access
 *     }
 *     
 *     protected function get_gateway_name() {
 *         return 'paypal';
 *     }
 *     
 *     public function is_configured() {
 *         return !empty($this->flosc->get_config('paypal_client_id'));
 *     }
 * }
 * 
 * =============================================================================
 */


/* =============================================================================
 * CLICKBANK GATEWAY PLACEHOLDER
 * =============================================================================
 * 
 * When implementing ClickBank:
 * 
 * class FLOSC_ClickBank_Gateway extends FLOSC_Payment_Gateway {
 *     
 *     public function create_checkout_session($request) {
 *         // Generate ClickBank hoplink
 *         // Format: https://AFFILIATE.VENDOR.hop.clickbank.net/?tid=TRACKING
 *         
 *         $vendor = $this->flosc->get_config('clickbank_vendor');
 *         $product_id = $this->flosc->get_config('clickbank_product_id');
 *         $user_id = get_current_user_id();
 *         
 *         // The TID (tracking ID) contains our user ID for matching
 *         $hoplink = "https://{$vendor}.pay.clickbank.net/?cbitems={$product_id}&tid={$user_id}";
 *         
 *         return new WP_REST_Response(['checkout_url' => $hoplink]);
 *     }
 *     
 *     public function handle_webhook($request) {
 *         // This is the IPN handler
 *         // See flosc.php rest_clickbank_ipn() for full implementation notes
 *         
 *         // 1. Verify IPN signature
 *         // 2. Extract transaction type (SALE, RFND, etc.)
 *         // 3. Find user by TID or email
 *         // 4. Grant or revoke access
 *     }
 *     
 *     protected function get_gateway_name() {
 *         return 'clickbank';
 *     }
 *     
 *     public function is_configured() {
 *         return !empty($this->flosc->get_config('clickbank_vendor'))
 *             && !empty($this->flosc->get_config('clickbank_secret_key'));
 *     }
 * }
 * 
 * =============================================================================
 */
