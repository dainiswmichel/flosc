<?php
/**
 * Plugin Name: LeSAEp Chat-Only FLOSC
 * Description: Pure chat-only pronunciation quiz with BuddyBoss integration
 * Version: 1.0.0
 * Author: Dainis Michel
 */

if (!defined('ABSPATH')) exit;

class LeSAEp_Chat {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcode
        add_shortcode('lesaep_chat', array($this, 'render_chat'));
        
        // Register REST endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Admin settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Shortcode: [lesaep_chat]
     */
    public function render_chat($atts) {
        // Get current user
        $user = wp_get_current_user();
        $user_id = $user->ID;
        $is_logged_in = $user_id > 0;
        
        // Check paid access (BuddyBoss group membership)
        $has_paid_access = $this->user_has_paid_access($user_id);
        
        ob_start();
        ?>
        <div id="lesaep-chat-container">
            <!-- Chat window structure -->
            <div class="lesaep-chat-window">
                <div class="chat-header">
                    <div class="bot-avatar">🎯</div>
                    <div class="bot-info">
                        <h1>LeSAEp Pronunciation Coach</h1>
                        <p class="bot-status">
                            <span class="status-dot"></span> 
                            <?php echo $is_logged_in ? 'Logged in as ' . esc_html($user->display_name) : 'Start your free analysis'; ?>
                        </p>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <!-- Messages populated by JS -->
                </div>
                
                <div class="chat-input-area">
                    <!-- Typing indicator -->
                    <div class="typing-indicator hidden" id="typingIndicator">
                        <span></span><span></span><span></span>
                    </div>
                    
                    <!-- Quick reply buttons -->
                    <div class="quick-replies" id="quickReplies"></div>
                    
                    <!-- Audio recorder (in-chat) -->
                    <div class="recorder-area hidden" id="recorderArea">
                        <div class="sentence-display" id="sentenceDisplay"></div>
                        <button class="record-btn" id="recordBtn">
                            <span class="record-icon">🎤</span>
                            <span class="record-text">Click to Record</span>
                        </button>
                        <div class="waveform hidden" id="waveform">
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                            <div class="bar"></div>
                        </div>
                        <p class="record-status" id="recordStatus"></p>
                    </div>
                    
                    <!-- Stripe Payment Element (embedded modal) -->
                    <div class="payment-modal hidden" id="paymentModal">
                        <div class="payment-modal-content">
                            <h3>Complete Your Purchase</h3>
                            <div id="stripe-payment-element"></div>
                            <div class="payment-buttons">
                                <button id="submitPaymentBtn" class="btn-primary">Pay Now</button>
                                <button id="cancelPaymentBtn" class="btn-secondary">Cancel</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lesson viewer (in-chat) -->
                    <div class="lesson-viewer hidden" id="lessonViewer">
                        <div class="lesson-content" id="lessonContent"></div>
                        <div class="lesson-navigation">
                            <button id="prevLessonBtn" class="btn-nav">← Previous</button>
                            <button id="nextLessonBtn" class="btn-nav">Next →</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Countdown timer (overlay) -->
            <div class="countdown-overlay hidden" id="countdownOverlay">
                <div class="countdown-box">
                    <h3>⏰ Special Offer Expires</h3>
                    <div class="countdown-timer">
                        <div class="time-unit">
                            <span class="time-value" id="hours">00</span>
                            <span class="time-label">Hours</span>
                        </div>
                        <div class="time-unit">
                            <span class="time-value" id="minutes">00</span>
                            <span class="time-label">Minutes</span>
                        </div>
                        <div class="time-unit">
                            <span class="time-value" id="seconds">00</span>
                            <span class="time-label">Seconds</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue CSS and JS assets
     */
    public function enqueue_assets() {
        if (!has_shortcode(get_post()->post_content, 'lesaep_chat')) {
            return;
        }
        
        // Stripe.js
        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
        
        // Chat CSS
        wp_enqueue_style(
            'lesaep-chat-css',
            plugins_url('assets/css/chat.css', __FILE__),
            array(),
            '1.0.0'
        );
        
        // Chat JS
        wp_enqueue_script(
            'lesaep-chat-js',
            plugins_url('assets/js/chat.js', __FILE__),
            array('stripe-js'),
            '1.0.0',
            true
        );
        
        // Pass config to JS
        $user = wp_get_current_user();
        wp_localize_script('lesaep-chat-js', 'LESAEP_CONFIG', array(
            'apiUrl' => get_option('lesaep_api_url', '/lesaep-api'),
            'wpRestUrl' => rest_url('lesaep/v1'),
            'userId' => $user->ID,
            'isLoggedIn' => is_user_logged_in(),
            'hasPaidAccess' => $this->user_has_paid_access($user->ID),
            'userEmail' => $user->user_email,
            'userName' => $user->display_name,
            'loginUrl' => wp_login_url(get_permalink()),
            'stripePublicKey' => get_option('lesaep_stripe_public_key'),
            'productName' => get_option('lesaep_product_name', 'LeSAEp English Pronunciation'),
            'fullPrice' => intval(get_option('lesaep_full_price', 575)),
            'otoPrice' => intval(get_option('lesaep_oto_price', 144)),
            'discount' => intval(get_option('lesaep_discount', 75)),
            'nonce' => wp_create_nonce('lesaep_chat')
        ));
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Session token endpoint (for droplet auth)
        register_rest_route('lesaep/v1', '/session', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_session_token'),
            'permission_callback' => '__return_true'
        ));
        
        // Get lessons endpoint
        register_rest_route('lesaep/v1', '/lessons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lessons'),
            'permission_callback' => '__return_true'
        ));
        
        // Get single lesson
        register_rest_route('lesaep/v1', '/lessons/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_single_lesson'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Get session token for droplet authentication
     * This is the "glue" between WordPress and droplet
     */
    public function get_session_token($request) {
        $user = wp_get_current_user();
        
        if (!$user->ID) {
            return new WP_REST_Response(array(
                'logged_in' => false,
                'message' => 'Not logged in'
            ), 200);
        }
        
        // Generate signed token (HMAC)
        $secret = get_option('lesaep_session_secret', wp_generate_password(32, false));
        $data = array(
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'expires' => time() + 3600, // 1 hour
            'has_paid_access' => $this->user_has_paid_access($user->ID)
        );
        
        $json = json_encode($data);
        $signature = hash_hmac('sha256', $json, $secret);
        $token = base64_encode($json) . '.' . $signature;
        
        return new WP_REST_Response(array(
            'logged_in' => true,
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'has_paid_access' => $this->user_has_paid_access($user->ID),
            'lesaep_token' => $token
        ), 200);
    }
    
    /**
     * Get lessons (filtered by access level)
     */
    public function get_lessons($request) {
        $user_id = $request->get_param('user_id');
        $has_paid_access = $this->user_has_paid_access($user_id);
        
        $args = array(
            'post_type' => 'post',
            'category_name' => 'lesaep',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );
        
        // If not paid, only get free lessons
        if (!$has_paid_access) {
            $args['meta_query'] = array(
                array(
                    'key' => '_lesaep_is_free',
                    'value' => '1'
                )
            );
        }
        
        $posts = get_posts($args);
        
        $lessons = array_map(function($post) {
            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => get_the_excerpt($post),
                'content' => apply_filters('the_content', $post->post_content),
                'is_free' => get_post_meta($post->ID, '_lesaep_is_free', true) === '1',
                'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true),
                'permalink' => get_permalink($post)
            );
        }, $posts);
        
        return new WP_REST_Response($lessons, 200);
    }
    
    /**
     * Get single lesson
     */
    public function get_single_lesson($request) {
        $lesson_id = $request->get_param('id');
        $user_id = $request->get_param('user_id');
        
        $post = get_post($lesson_id);
        if (!$post) {
            return new WP_Error('not_found', 'Lesson not found', array('status' => 404));
        }
        
        $is_free = get_post_meta($post->ID, '_lesaep_is_free', true) === '1';
        $has_access = $is_free || $this->user_has_paid_access($user_id);
        
        if (!$has_access) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }
        
        return new WP_REST_Response(array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
            'is_free' => $is_free,
            'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true)
        ), 200);
    }
    
    /**
     * Check if user has paid access (BuddyBoss group membership)
     */
    private function user_has_paid_access($user_id) {
        if (!$user_id) {
            return false;
        }
        
        $paid_group_id = get_option('lesaep_paid_group_id');
        if (!$paid_group_id) {
            return false;
        }
        
        // Check BuddyBoss group membership
        if (function_exists('groups_is_user_member')) {
            return groups_is_user_member($user_id, $paid_group_id);
        }
        
        // Fallback: check user meta
        return get_user_meta($user_id, 'lesaep_paid_access', true) === '1';
    }
    
    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'LeSAEp Chat',
            'LeSAEp Chat',
            'manage_options',
            'lesaep-chat',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('lesaep_chat', 'lesaep_api_url');
        register_setting('lesaep_chat', 'lesaep_session_secret');
        register_setting('lesaep_chat', 'lesaep_stripe_public_key');
        register_setting('lesaep_chat', 'lesaep_stripe_secret_key');
        register_setting('lesaep_chat', 'lesaep_paid_group_id');
        register_setting('lesaep_chat', 'lesaep_product_name');
        register_setting('lesaep_chat', 'lesaep_full_price');
        register_setting('lesaep_chat', 'lesaep_oto_price');
        register_setting('lesaep_chat', 'lesaep_discount');
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>LeSAEp Chat Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('lesaep_chat'); ?>
                <table class="form-table">
                    <tr>
                        <th>API URL</th>
                        <td>
                            <input type="text" name="lesaep_api_url" value="<?php echo esc_attr(get_option('lesaep_api_url', '/lesaep-api')); ?>" class="regular-text">
                            <p class="description">Backend API endpoint (e.g., /lesaep-api or https://api.lesaep.com)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Session Secret</th>
                        <td>
                            <input type="text" name="lesaep_session_secret" value="<?php echo esc_attr(get_option('lesaep_session_secret')); ?>" class="regular-text">
                            <p class="description">Secret key for signing session tokens (auto-generated if empty)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Stripe Public Key</th>
                        <td><input type="text" name="lesaep_stripe_public_key" value="<?php echo esc_attr(get_option('lesaep_stripe_public_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Stripe Secret Key</th>
                        <td><input type="text" name="lesaep_stripe_secret_key" value="<?php echo esc_attr(get_option('lesaep_stripe_secret_key')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Paid Group ID (BuddyBoss)</th>
                        <td>
                            <input type="number" name="lesaep_paid_group_id" value="<?php echo esc_attr(get_option('lesaep_paid_group_id')); ?>">
                            <p class="description">BuddyBoss group ID for paid members</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Product Name</th>
                        <td><input type="text" name="lesaep_product_name" value="<?php echo esc_attr(get_option('lesaep_product_name', 'LeSAEp English Pronunciation')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Full Price ($)</th>
                        <td><input type="number" name="lesaep_full_price" value="<?php echo esc_attr(get_option('lesaep_full_price', 575)); ?>"></td>
                    </tr>
                    <tr>
                        <th>OTO Price ($)</th>
                        <td><input type="number" name="lesaep_oto_price" value="<?php echo esc_attr(get_option('lesaep_oto_price', 144)); ?>"></td>
                    </tr>
                    <tr>
                        <th>Discount (%)</th>
                        <td><input type="number" name="lesaep_discount" value="<?php echo esc_attr(get_option('lesaep_discount', 75)); ?>"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2>Quick Start</h2>
            <ol>
                <li>Create a WordPress page with shortcode: <code>[lesaep_chat]</code></li>
                <li>Configure Nginx proxy: <code>/lesaep-api/ → http://DROPLET_IP:8000/</code></li>
                <li>Deploy FastAPI backend to droplet</li>
                <li>Create BuddyBoss group for paid members</li>
                <li>Configure Stripe keys above</li>
                <li>Add lesson posts with custom fields:
                    <ul>
                        <li><code>_lesaep_is_free</code> = 1 (for free lessons)</li>
                        <li><code>_lesaep_phoneme_group</code> = /æ/ (or other phoneme)</li>
                    </ul>
                </li>
            </ol>
        </div>
        <?php
    }
}

// Initialize plugin
LeSAEp_Chat::instance();
