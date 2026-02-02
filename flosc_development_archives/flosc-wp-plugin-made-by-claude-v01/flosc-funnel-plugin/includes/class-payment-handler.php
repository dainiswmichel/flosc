<?php
/**
 * Payment Handler - Stripe Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Payment_Handler {
    
    private static $instance = null;
    private $stripe_key;
    private $publishable_key;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_stripe();
        add_action('init', array($this, 'handle_webhook'));
    }
    
    /**
     * Initialize Stripe
     */
    private function init_stripe() {
        $mode = get_option('flosc_stripe_mode', 'test');
        
        if ($mode === 'live') {
            $this->stripe_key = get_option('flosc_stripe_live_secret_key');
            $this->publishable_key = get_option('flosc_stripe_live_publishable_key');
        } else {
            $this->stripe_key = get_option('flosc_stripe_test_secret_key');
            $this->publishable_key = get_option('flosc_stripe_test_publishable_key');
        }
    }
    
    /**
     * Create checkout session
     */
    public function create_checkout_session($quiz_id, $user_id = null) {
        $product_name = get_option('flosc_product_name');
        $oto_price = get_option('flosc_oto_price');
        $discount_percent = get_option('flosc_oto_discount_percent');
        
        $success_url = add_query_arg(array(
            'quiz_id' => $quiz_id,
            'session_id' => '{CHECKOUT_SESSION_ID}'
        ), get_permalink(get_option('flosc_success_page')));
        
        $cancel_url = add_query_arg('quiz_id', $quiz_id, get_permalink(get_option('flosc_offer_page')));
        
        $data = array(
            'payment_method_types' => array('card'),
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => 'usd',
                        'unit_amount' => $oto_price * 100,
                        'product_data' => array(
                            'name' => $product_name,
                            'description' => sprintf('%d%% discount - One time offer', $discount_percent)
                        )
                    ),
                    'quantity' => 1
                )
            ),
            'mode' => 'payment',
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => array(
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            )
        );
        
        $response = $this->stripe_api_call('POST', 'checkout/sessions', $data);
        
        if ($response && isset($response['url'])) {
            return array(
                'success' => true,
                'url' => $response['url'],
                'session_id' => $response['id']
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Failed to create checkout session'
        );
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook() {
        if (!isset($_GET['flosc_stripe_webhook'])) {
            return;
        }
        
        $payload = file_get_contents('php://input');
        $sig_header = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
        
        // For now, just parse the JSON (in production, verify signature)
        $event = json_decode($payload, true);
        
        if (!$event) {
            status_header(400);
            exit;
        }
        
        if ($event['type'] === 'checkout.session.completed') {
            $session = $event['data']['object'];
            $quiz_id = isset($session['metadata']['quiz_id']) ? $session['metadata']['quiz_id'] : '';
            $user_id = isset($session['metadata']['user_id']) ? $session['metadata']['user_id'] : null;
            
            if ($quiz_id) {
                $quiz_handler = FLOSC_Quiz_Handler::instance();
                $quiz_handler->mark_paid($quiz_id, $session['id']);
                
                // Update user meta if user ID exists
                if ($user_id) {
                    update_user_meta($user_id, 'flosc_has_paid', true);
                    update_user_meta($user_id, 'flosc_paid_at', current_time('mysql'));
                }
            }
        }
        
        status_header(200);
        exit;
    }
    
    /**
     * Make Stripe API call
     */
    private function stripe_api_call($method, $endpoint, $data = array()) {
        $url = 'https://api.stripe.com/v1/' . $endpoint;
        
        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->stripe_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30
        );
        
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = http_build_query($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
    
    /**
     * Get publishable key
     */
    public function get_publishable_key() {
        return $this->publishable_key;
    }
}
