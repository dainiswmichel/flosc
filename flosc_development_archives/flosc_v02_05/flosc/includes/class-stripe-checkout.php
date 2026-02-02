<?php
/**
 * =============================================================================
 * FLOSC STRIPE CHECKOUT
 * =============================================================================
 * 
 * Direct Stripe Checkout integration without WooCommerce.
 * 
 * =============================================================================
 * HOW IT WORKS
 * =============================================================================
 * 
 * 1. User clicks "Buy Now" in chat
 * 2. JavaScript calls /flosc/v1/create-checkout
 * 3. This class creates a Stripe Checkout Session
 * 4. User is redirected to Stripe's hosted checkout page
 * 5. After payment, Stripe redirects back to success URL
 * 6. Stripe sends webhook to /flosc/v1/stripe-webhook
 * 7. Webhook handler grants access to user
 * 
 * =============================================================================
 * STRIPE API DOCUMENTATION
 * =============================================================================
 * 
 * Checkout Sessions: https://stripe.com/docs/api/checkout/sessions
 * Webhooks: https://stripe.com/docs/webhooks
 * 
 * =============================================================================
 * SECURITY NOTES
 * =============================================================================
 * 
 * - Secret key is NEVER exposed to frontend
 * - Webhook signature is ALWAYS verified
 * - User ID is passed via client_reference_id (secure)
 * - Amount is set server-side (not from client)
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Stripe_Checkout {
    
    /**
     * Reference to main FLOSC framework
     * @var FLOSC_Framework
     */
    private $flosc;
    
    
    /**
     * Constructor
     * 
     * @param FLOSC_Framework $flosc_framework Main plugin instance
     */
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }
    
    
    /* =========================================================================
     * CHECKOUT SESSION CREATION
     * =========================================================================
     * 
     * Creates a Stripe Checkout Session and returns the URL.
     * Called from REST API endpoint.
     */
    
    /**
     * Create Stripe Checkout Session
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response Checkout URL
     */
    public function create_checkout_session($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Login required', ['status' => 401]);
        }
        
        // Get Stripe configuration
        $mode = $this->flosc->get_config('stripe_mode');
        $secret_key = $mode === 'live' 
            ? $this->flosc->get_config('stripe_live_sk')
            : $this->flosc->get_config('stripe_test_sk');
        
        if (!$secret_key) {
            return new WP_Error(
                'stripe_not_configured',
                'Stripe is not configured. Please add API keys in FLOSC settings.',
                ['status' => 500]
            );
        }
        
        // Get pricing from config
        $price = (int)$this->flosc->get_config('offer_price');
        $currency = strtolower($this->flosc->get_config('offer_currency'));
        
        // Map currency symbols to codes
        $currency_map = [
            '€' => 'eur',
            '$' => 'usd',
            '£' => 'gbp',
        ];
        $currency_code = $currency_map[$currency] ?? 'eur';
        
        // Get user info
        $user = get_user_by('id', $user_id);
        $user_email = $user->user_email;
        
        // Get quiz results for metadata
        $quiz_results = get_user_meta($user_id, 'flosc_quiz_results', true);
        $needed_lessons = get_user_meta($user_id, 'flosc_needed_lessons', true);
        $referral = get_user_meta($user_id, 'flosc_referral_code', true);
        
        // Build return URLs
        $success_url = add_query_arg([
            'flosc_payment' => 'success',
            'session_id'    => '{CHECKOUT_SESSION_ID}',
        ], home_url('/'));
        
        $cancel_url = add_query_arg([
            'flosc_payment' => 'cancelled',
        ], home_url('/'));
        
        // =====================================================================
        // CREATE STRIPE CHECKOUT SESSION
        // =====================================================================
        
        $checkout_data = [
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'client_reference_id'  => (string)$user_id,  // IMPORTANT: Links payment to WordPress user
            'customer_email'       => $user_email,
            'success_url'          => $success_url,
            'cancel_url'           => $cancel_url,
            'line_items'           => [
                [
                    'price_data' => [
                        'currency'     => $currency_code,
                        'unit_amount'  => $price * 100,  // Stripe uses cents
                        'product_data' => [
                            'name'        => $this->flosc->get_config('offer_headline'),
                            'description' => $this->flosc->get_config('offer_description'),
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'wp_user_id'      => $user_id,
                'quiz_score'      => $quiz_results['score'] ?? '',
                'lessons_needed'  => is_array($needed_lessons) ? count($needed_lessons) : 0,
                'referral'        => $referral ?: '',
                'flosc_version'   => FLOSC_VERSION,
            ],
        ];
        
        // Make Stripe API call
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'body'    => http_build_query($this->flatten_array($checkout_data)),
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('FLOSC Stripe Error: ' . $response->get_error_message());
            return new WP_Error('stripe_error', 'Payment system error', ['status' => 500]);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            error_log('FLOSC Stripe API Error: ' . print_r($body['error'], true));
            return new WP_Error(
                'stripe_api_error',
                $body['error']['message'] ?? 'Payment error',
                ['status' => 400]
            );
        }
        
        if (!isset($body['url'])) {
            error_log('FLOSC Stripe: No URL in response');
            return new WP_Error('stripe_error', 'Could not create checkout', ['status' => 500]);
        }
        
        error_log("FLOSC: Created Stripe session for user {$user_id}");
        
        return new WP_REST_Response([
            'success'      => true,
            'checkout_url' => $body['url'],
            'session_id'   => $body['id'],
        ]);
    }
    
    
    /* =========================================================================
     * WEBHOOK HANDLER
     * =========================================================================
     * 
     * Receives payment confirmations from Stripe.
     * Verifies signature and grants access.
     */
    
    /**
     * Handle Stripe webhook
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        $webhook_secret = $this->flosc->get_config('stripe_webhook_secret');
        
        // =====================================================================
        // VERIFY WEBHOOK SIGNATURE
        // =====================================================================
        // This is critical for security - ensures webhook came from Stripe
        
        if ($webhook_secret) {
            $verified = $this->verify_webhook_signature($payload, $sig_header, $webhook_secret);
            
            if (!$verified) {
                error_log('FLOSC Stripe Webhook: Invalid signature');
                return new WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 400]);
            }
        } else {
            error_log('FLOSC Stripe Webhook: No webhook secret configured (signature not verified)');
        }
        
        // Parse event
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['type'])) {
            return new WP_Error('invalid_payload', 'Invalid webhook payload', ['status' => 400]);
        }
        
        error_log("FLOSC Stripe Webhook: Received event '{$event['type']}'");
        
        // =====================================================================
        // HANDLE EVENT TYPES
        // =====================================================================
        
        switch ($event['type']) {
            
            case 'checkout.session.completed':
                // =============================================================
                // PAYMENT SUCCESSFUL
                // =============================================================
                // User completed checkout - grant access!
                
                $session = $event['data']['object'];
                $user_id = (int)($session['client_reference_id'] ?? 0);
                $session_id = $session['id'];
                $amount = $session['amount_total'] ?? 0;
                
                if (!$user_id) {
                    error_log('FLOSC Stripe Webhook: No user ID in session');
                    return new WP_REST_Response(['received' => true, 'error' => 'no_user_id']);
                }
                
                // Grant access
                $method = $this->flosc->grant_paid_access($user_id, 'stripe', $session_id);
                
                error_log("FLOSC: Payment completed! User {$user_id} granted access via {$method}");
                error_log("FLOSC: Session ID: {$session_id}, Amount: {$amount}");
                
                // Store Stripe-specific metadata
                update_user_meta($user_id, 'flosc_stripe_session_id', $session_id);
                update_user_meta($user_id, 'flosc_stripe_amount', $amount);
                
                // Track referral conversion
                $referral = get_user_meta($user_id, 'flosc_referral_code', true);
                if ($referral) {
                    $this->track_referral_conversion($referral, $user_id, $amount);
                }
                
                break;
                
            case 'checkout.session.expired':
                // Session expired without payment - no action needed
                error_log('FLOSC Stripe: Checkout session expired');
                break;
                
            case 'charge.refunded':
                // =============================================================
                // REFUND
                // =============================================================
                // Optionally revoke access on refund
                
                $charge = $event['data']['object'];
                $metadata = $charge['metadata'] ?? [];
                $user_id = (int)($metadata['wp_user_id'] ?? 0);
                
                if ($user_id) {
                    // Uncomment to automatically revoke access on refund:
                    // $this->flosc->revoke_paid_access($user_id, 'stripe_refund');
                    
                    error_log("FLOSC Stripe: Refund for user {$user_id} (access NOT revoked)");
                }
                break;
                
            case 'charge.dispute.created':
                // =============================================================
                // CHARGEBACK
                // =============================================================
                // Definitely revoke access on chargeback
                
                $dispute = $event['data']['object'];
                $charge_id = $dispute['charge'] ?? '';
                
                error_log("FLOSC Stripe: Dispute/chargeback created for charge {$charge_id}");
                
                // Would need to look up user by charge ID
                // For now, just log it
                break;
                
            default:
                // Unhandled event type - just acknowledge
                error_log("FLOSC Stripe Webhook: Unhandled event type '{$event['type']}'");
        }
        
        // Always return 200 to Stripe
        return new WP_REST_Response(['received' => true]);
    }
    
    
    /* =========================================================================
     * WEBHOOK SIGNATURE VERIFICATION
     * =========================================================================
     * 
     * Verifies that the webhook actually came from Stripe.
     * Uses HMAC-SHA256 with the webhook signing secret.
     */
    
    /**
     * Verify Stripe webhook signature
     * 
     * @param string $payload Raw request body
     * @param string $sig_header Stripe-Signature header value
     * @param string $secret Webhook signing secret
     * @return bool True if valid
     */
    private function verify_webhook_signature($payload, $sig_header, $secret) {
        // Parse signature header
        // Format: t=timestamp,v1=signature,v1=signature2,...
        $parts = [];
        foreach (explode(',', $sig_header) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) === 2) {
                $parts[$kv[0]] = $kv[1];
            }
        }
        
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        
        if (!$timestamp || !$signature) {
            return false;
        }
        
        // Check timestamp (within 5 minutes)
        $tolerance = 300;  // 5 minutes
        if (abs(time() - (int)$timestamp) > $tolerance) {
            error_log('FLOSC Stripe: Webhook timestamp too old');
            return false;
        }
        
        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        
        // Compare (timing-safe)
        return hash_equals($expected, $signature);
    }
    
    
    /* =========================================================================
     * REFERRAL TRACKING
     * =========================================================================
     */
    
    /**
     * Track referral conversion (for affiliate payouts)
     */
    private function track_referral_conversion($referral_code, $user_id, $amount) {
        $conversions = get_option('flosc_referral_conversions', []);
        
        $conversions[] = [
            'referral_code' => $referral_code,
            'user_id'       => $user_id,
            'amount'        => $amount,
            'timestamp'     => current_time('timestamp'),
            'date'          => current_time('mysql'),
        ];
        
        // Keep last 1000
        if (count($conversions) > 1000) {
            $conversions = array_slice($conversions, -1000);
        }
        
        update_option('flosc_referral_conversions', $conversions);
        
        error_log("FLOSC: Referral conversion tracked - Code: {$referral_code}, Amount: {$amount}");
    }
    
    
    /* =========================================================================
     * HELPER: FLATTEN ARRAY FOR STRIPE API
     * =========================================================================
     * 
     * Stripe's API expects nested arrays in a specific format:
     * line_items[0][price_data][currency] = eur
     */
    
    /**
     * Flatten nested array for URL encoding
     * 
     * @param array $array
     * @param string $prefix
     * @return array
     */
    private function flatten_array($array, $prefix = '') {
        $result = [];
        
        foreach ($array as $key => $value) {
            $new_key = $prefix ? "{$prefix}[{$key}]" : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten_array($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
}
