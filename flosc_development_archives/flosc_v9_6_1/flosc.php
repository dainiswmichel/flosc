<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - Quiz-based learning and conversational sales funnel framework
 * Version: 9.6.1
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '9.6.1');
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main FLOSC Framework Class
 */
class FLOSC_Framework {
    
    private static $instance = null;
    
    // Core components
    private $ai_factory;
    private $stt_factory;
    private $quiz_factory;
    private $session_manager;
    private $pronunciation_analyzer;

    // SALE system (loaded separately)
    private $sale_manager;
    
    // RAG system (v9.1.6)
    private $user_access_manager;
    private $content_filter;
    private $rag_manager;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    // Lesson manager
    private $lesson_manager;
    
    private function load_dependencies() {
        // Core components
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-type-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-manager.php';

        // IVR system (v07.08)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';

        // RAG system (v9.1.6)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-user-access-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-content-filter.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-rag-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-access-validator.php'; // v9.1.7
        require_once FLOSC_PLUGIN_DIR . 'includes/class-free-lesson-manager.php'; // v9.1.8
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php'; // v9.1.8

        // SALE system
        require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-sale-manager.php';

        $this->ai_factory = new FLOSC_AI_Provider_Factory();
        $this->stt_factory = new FLOSC_STT_Provider_Factory();
        $this->session_manager = new FLOSC_Session_Manager();
        $this->pronunciation_analyzer = new FLOSC_Pronunciation_Analyzer();
        $this->lesson_manager = FLOSC_Lesson_Manager::instance();

        // Quiz types loaded dynamically by factory

        // Initialize SALE system
        $this->sale_manager = FLOSC_Sale_Manager::instance();
        
        // Initialize RAG system (v9.1.6)
        $this->user_access_manager = FLOSC_User_Access_Manager::instance();
        $this->content_filter = flosc_content_filter::instance();
        $this->rag_manager = FLOSC_RAG_Manager::instance();
        
        // Initialize v9.1.8 systems
        $this->free_lesson_manager = FLOSC_Free_Lesson_Manager::instance();
        $this->member_access = FLOSC_Member_Access::instance();
    }
    
    private function init_hooks() {
        // Virtual page routing
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_app_route']);

        // Admin - priority 5 to ensure Settings submenu is added first
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Auto-flush permalinks when slug changes
        add_action('update_option_flosc_app_slug', [$this, 'handle_slug_change'], 10, 2);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Register shortcodes (v9.2.0)
        add_shortcode('flosc_visitor_only', [$this, 'shortcode_visitor_only']);
        add_shortcode('flosc_member_only', [$this, 'shortcode_member_only']);

        // User registration hook (for signup bonus)
        add_action('user_register', [$this, 'handle_user_registration']);

        // User login hook (for pre-login score processing)
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);

        // Login redirect - send users to FLOSC app after login (v9.5.7)
        add_filter('login_redirect', [$this, 'handle_login_redirect'], 999, 3);
        add_filter('woocommerce_login_redirect', [$this, 'handle_woocommerce_login_redirect'], 999, 2);

        // Admin post handler for flush permalinks (v9.5.1)
        add_action('admin_post_flosc_flush_permalinks', [$this, 'handle_flush_permalinks']);

        // Third-party quiz plugin integrations (v9.3.4)
        $this->init_quiz_plugin_hooks();
    }
    
    /**
     * Initialize third-party quiz plugin hooks (v9.3.4)
     * 
     * Captures quiz completion from external plugins and feeds them
     * into the FLOSC funnel system. Each integration is opt-in via admin.
     */
    private function init_quiz_plugin_hooks() {
        // Wp-Pro-Quiz Integration
        if (get_option('flosc_wpq_integration', 0) && class_exists('WpProQuiz_Controller_Quiz')) {
            add_action('wp_pro_quiz_completed_quiz', function($quiz_id, $score, $user_id) {
                $this->capture_external_quiz_score([
                    'source'    => 'wp_pro_quiz',
                    'quiz_id'   => $quiz_id,
                    'score'     => $score,
                    'user_id'   => $user_id,
                    'timestamp' => time()
                ]);
            }, 10, 3);
        }
        
        // LearnDash Integration
        if (get_option('flosc_ld_integration', 0) && defined('LEARNDASH_VERSION')) {
            add_action('learndash_quiz_completed', function($data, $user) {
                $this->capture_external_quiz_score([
                    'source'    => 'learndash',
                    'quiz_id'   => $data['quiz'] ?? 0,
                    'score'     => $data['percentage'] ?? 0,
                    'user_id'   => $user->ID,
                    'timestamp' => time()
                ]);
            }, 10, 2);
        }
        
        // Quiz & Survey Master Integration
        if (get_option('flosc_qsm_integration', 0) && (class_exists('QSM_Quiz') || function_exists('qsm_register_quiz_setting'))) {
            add_action('qsm_quiz_submitted', function($results, $quiz_id) {
                $total = $results['total_questions'] ?? 1;
                $correct = $results['total_correct'] ?? 0;
                $this->capture_external_quiz_score([
                    'source'    => 'qsm',
                    'quiz_id'   => $quiz_id,
                    'score'     => ($total > 0) ? round(($correct / $total) * 100) : 0,
                    'user_id'   => get_current_user_id(),
                    'timestamp' => time()
                ]);
            }, 10, 2);
        }
    }
    
    /**
     * Capture quiz scores from external plugins (v9.3.4)
     * 
     * Unified handler for scores from Wp-Pro-Quiz, LearnDash, QSM, etc.
     * Stores score for funnel progression regardless of login state.
     * 
     * v9.4.2: Uses signed cookies to prevent score forgery
     * 
     * @param array $data Score data with source, quiz_id, score, user_id, timestamp
     */
    public function capture_external_quiz_score($data) {
        $user_id = $data['user_id'] ?? get_current_user_id();
        
        // Format for FLOSC
        $score_data = [
            'score'     => intval($data['score']),
            'correct'   => [],
            'incorrect' => [],
            'quiz_type' => 'external_' . ($data['source'] ?? 'unknown'),
            'external_quiz_id' => $data['quiz_id'] ?? null,
            'timestamp' => $data['timestamp'] ?? time(),
        ];
        
        if (!$user_id) {
            // Visitor: store in signed cookie for login gate to pick up
            // v9.4.2: Cookie is now signed to prevent score forgery
            $this->set_signed_cookie('flosc_prelogin_score', $score_data, HOUR_IN_SECONDS);
            return;
        }
        
        // Logged-in user: store in user meta
        update_user_meta($user_id, '_flosc_last_quiz_score', $score_data['score']);
        update_user_meta($user_id, '_flosc_last_quiz_data', $score_data);
        update_user_meta($user_id, '_flosc_quiz_completed_at', current_time('mysql'));
        
        // Trigger FLOSC phase transition
        do_action('flosc_quiz_completed', $score_data, $user_id);
    }
    
    /**
     * Component accessors
     */
    public function ai() { return $this->ai_factory; }
    public function stt() { return $this->stt_factory; }
    public function quiz() { return 'FLOSC_Quiz_Type_Factory'; } // Returns class name (static factory)
    public function sessions() { return $this->session_manager; }
    public function analyzer() { return $this->pronunciation_analyzer; }
    public function sale() { return $this->sale_manager; }
    public function lessons() { return $this->lesson_manager; }

    /**
     * Rate Limiting Helper
     * Prevents API abuse on public endpoints
     */
    private function check_rate_limit($endpoint, $limit = 20, $window = 3600) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'flosc_rate_' . md5($endpoint . $ip);
        $count = get_transient($key) ?: 0;

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Signed Cookie Helpers (v9.4.2 Security Hardening)
     * 
     * Prevents cookie forgery by adding HMAC signature.
     * Uses WordPress AUTH_SALT for signing.
     */
    
    /**
     * Create a signed cookie value
     * Format: base64(data)|signature
     * 
     * @param array $data Data to store in cookie
     * @return string Signed cookie value
     */
    private function sign_cookie_data($data) {
        $json = wp_json_encode($data);
        $encoded = base64_encode($json);
        $signature = hash_hmac('sha256', $encoded, wp_salt('auth'));
        return $encoded . '|' . $signature;
    }
    
    /**
     * Verify and decode a signed cookie
     * 
     * @param string $cookie_value Raw cookie value
     * @return array|false Decoded data or false if invalid
     */
    private function verify_signed_cookie($cookie_value) {
        if (empty($cookie_value) || strpos($cookie_value, '|') === false) {
            return false;
        }
        
        $parts = explode('|', $cookie_value, 2);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($encoded, $signature) = $parts;
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $encoded, wp_salt('auth'));
        if (!hash_equals($expected_signature, $signature)) {
            // Invalid signature - possible tampering
            return false;
        }
        
        // Decode and return data
        $json = base64_decode($encoded);
        if ($json === false) {
            return false;
        }
        
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Set a signed cookie
     * 
     * @param string $name Cookie name
     * @param array $data Data to store
     * @param int $expiry Expiry time (timestamp or seconds from now)
     */
    private function set_signed_cookie($name, $data, $expiry = 0) {
        $value = $this->sign_cookie_data($data);
        
        // If expiry is small number, treat as seconds from now
        if ($expiry > 0 && $expiry < time() - 86400) {
            $expiry = time() + $expiry;
        }
        
        setcookie($name, $value, $expiry, '/', '', is_ssl(), true);
    }
    
    /**
     * Get data from a signed cookie
     * 
     * @param string $name Cookie name
     * @return array|false Decoded data or false if invalid/missing
     */
    private function get_signed_cookie($name) {
        $value = $_COOKIE[$name] ?? null;
        if (empty($value)) {
            return false;
        }
        return $this->verify_signed_cookie($value);
    }

    /**
     * Permission Callbacks for REST API
     */
    public function check_paid_endpoint_permission($request) {
        // Check rate limit first
        if (!$this->check_rate_limit('paid_endpoint', 20, 3600)) {
            return new WP_Error('rate_limit', 'Too many requests. Please try again later.', ['status' => 429]);
        }

        // Allow logged-in users with usage tracking
        if (is_user_logged_in()) {
            return true;
        }

        // For visitors: strict rate limit
        if (!$this->check_rate_limit('visitor_paid', 5, 3600)) {
            return new WP_Error('rate_limit', 'Free tier limit reached. Please log in.', ['status' => 429]);
        }

        return true;
    }
    
    /**
     * v9.4.2: Permission callback for public endpoints that need rate limiting
     * 
     * Unlike check_paid_endpoint_permission(), this is for truly public endpoints
     * like IVR chat that don't consume expensive AI credits but should still
     * be protected from abuse.
     * 
     * Limits: 60 requests/hour for logged-in users, 30/hour for visitors
     */
    public function check_public_endpoint_permission($request) {
        $endpoint = $request->get_route();
        
        if (is_user_logged_in()) {
            if (!$this->check_rate_limit('public_auth_' . $endpoint, 60, 3600)) {
                return new WP_Error('rate_limit', 'Too many requests. Please slow down.', ['status' => 429]);
            }
            return true;
        }
        
        // Visitors get stricter limits
        if (!$this->check_rate_limit('public_visitor_' . $endpoint, 30, 3600)) {
            return new WP_Error('rate_limit', 'Rate limit reached. Please try again later.', ['status' => 429]);
        }
        
        return true;
    }

    /**
     * Plugin Activation
     */
    // v9.1.1: Activation logic moved to global flosc_activate() function (line ~2285)
    // to avoid duplication. WordPress requires activation hook to point to a function,
    // not a class method, so the global function is the single source of truth.

    /**
     * Create default "works out of box" content
     */
    private function create_default_content() {
        // Set default messages
        $default_messages = [
            'flosc_welcome_message' => 'Default FLOSC Welcome Message: Hey, welcome to your FLOSC training! Here you\'ll discover exactly where you can improve. Ready to take a quick 30-second quiz to get started?',
            'flosc_get_started_message' => 'Default FLOSC Get Started Message: Great! The best way to begin is with our free quiz. It takes just 30 seconds and shows you exactly where you can improve. Would you like to try it?',
            'flosc_how_it_works_message' => 'Default FLOSC How It Works Message: Here\'s how FLOSC works: 1) Take a quick quiz (30 seconds), 2) Get your personalized score, 3) Receive a free lesson for your biggest challenge, 4) Upgrade to unlock full access to all content.',
            'flosc_what_you_learn_message' => 'Default FLOSC What You Learn Message: You\'ll learn practical skills tailored to your specific needs. Our quiz identifies your strengths and weaknesses, then delivers targeted lessons to help you improve exactly where you need it most.',
            'flosc_email_subject' => 'Default FLOSC Email Subject: Your Quiz Results Are Ready!',
            'flosc_email_body' => 'Default FLOSC Email Body: Hi {user_name},

You scored {score}% on the quiz!

We\'ve prepared a free lesson to help with the areas where you can improve most.

Ready for more? {oto_offer_link}

Best regards,
The Team',
        ];

        foreach ($default_messages as $key => $value) {
            if (!get_option($key)) {
                add_option($key, $value);
            }
        }

        // Create default quiz
        $quiz_config = [
            'id' => 'default-flosc-quiz',
            'name' => 'Default FLOSC Quick Assessment',
            'type' => 'flosc_sample_text_based_quiz',
            'items' => '1,2,3,4,5,6,7,8,9,10',
            'passing_score' => 70,
        ];
        update_option('flosc_quiz_config', $quiz_config);

        // Create "Default FLOSC Lessons" category
        $cat_id = wp_create_category('Default FLOSC Lessons');
        if ($cat_id && !is_wp_error($cat_id)) {
            update_option('flosc_lessons_category', $cat_id);

            // Create 10 default lesson posts
            for ($i = 1; $i <= 10; $i++) {
                $post_id = wp_insert_post([
                    'post_title' => "Default FLOSC Lesson $i: Sample Training Topic",
                    'post_content' => "Default FLOSC Lesson Content: This is a sample lesson for quiz item $i. Replace this with your actual training content.\n\nThis lesson addresses the skills tested in item $i of the quiz.",
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    'post_category' => [$cat_id],
                    'tags_input' => ["$i", "lesson-$i", "phoneme-$i"],
                ]);
            }
        }

        // Create default offer
        $offer_manager = $this->sale_manager->offers();
        $offer_manager->create_offer([
            'id' => 'default-flosc-full-access',
            'name' => 'Default FLOSC Full Access',
            'description' => 'Default FLOSC Offer: Unlock all lessons and premium features',
            'type' => 'one_time',
            'status' => 'active',
            'display_price' => '$97',
            'pricing' => [
                'stripe' => [
                    'price_id' => '', // Admin must configure
                ],
                'tokens' => [
                    'cost' => 1000,
                ],
                'affiliate' => [
                    'credit_amount' => 97.00,
                ],
            ],
            'grants' => [
                'features' => ['all_lessons', 'ai_coach', 'certificates'],
                'duration_days' => 0, // Lifetime
            ],
            'meta' => [
                'icon' => '⭐',
                'badge' => 'Best Value',
            ],
            'sort_order' => 1,
        ]);

        // Set as default OTO
        update_option('flosc_default_oto_offer', 'default-flosc-full-access');

        // Mark as created
        update_option('flosc_default_content_created', true);
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Handle new user registration
     */
    public function handle_user_registration($user_id) {
        // Grant signup bonus tokens
        $token_provider = $this->sale_manager->get_provider('tokens');
        if ($token_provider) {
            $token_provider->grant_signup_bonus($user_id);
        }
        
        // Check for referrer
        $referrer = $_COOKIE['flosc_referrer'] ?? null;
        if ($referrer && preg_match('/^REF(\d+)$/', $referrer, $matches)) {
            $referrer_id = intval($matches[1]);
            if ($referrer_id && $referrer_id !== $user_id) {
                $token_provider->grant_referral_bonus($referrer_id, $user_id);
            }
        }
    }
    
    /**
     * Handle user login - process pre-login quiz scores
     */
    public function handle_user_login($user_login, $user) {
        // v07.09: Set justLoggedIn flag for IVR
        set_transient('flosc_just_logged_in_' . $user->ID, true, MINUTE_IN_SECONDS * 5);

        // v9.4.2: Check for pre-login score in SIGNED cookie
        $score_data = $this->get_signed_cookie('flosc_prelogin_score');

        if ($score_data && isset($score_data['score'])) {
            // v8.0.3: Store score with quiz_id tracking
            $this->store_quiz_score($user->ID, $score_data);

            // v07.09: Set justCompletedQuiz flag for IVR
            set_transient('flosc_just_completed_quiz_' . $user->ID, true, MINUTE_IN_SECONDS * 5);

            // Send email with score and OTO
            $this->send_score_email($user, $score_data);

            // Clear the cookie (set expiry in past)
            setcookie('flosc_prelogin_score', '', time() - 3600, '/');
        }
    }

    /**
     * v9.5.7: Redirect users to FLOSC app after login
     * Only redirect if the intended destination is my-account or similar
     */
    public function handle_login_redirect($redirect_to, $requested_redirect_to, $user) {
        // If user explicitly requested a specific page, respect it
        if (!empty($requested_redirect_to) && strpos($requested_redirect_to, 'my-account') === false) {
            // Check if it's already pointing to our app
            $app_slug = get_option('flosc_app_slug', 'app');
            if (strpos($requested_redirect_to, '/' . $app_slug) !== false) {
                return $requested_redirect_to;
            }
        }
        
        // Get FLOSC app URL
        $app_url = home_url('/' . get_option('flosc_app_slug', 'app') . '/');
        
        // Redirect to FLOSC app
        return $app_url;
    }

    /**
     * v9.5.7: Handle WooCommerce-specific login redirect
     */
    public function handle_woocommerce_login_redirect($redirect, $user) {
        // Always redirect to FLOSC app from WooCommerce login
        return home_url('/' . get_option('flosc_app_slug', 'app') . '/');
    }
    
    /**
     * v8.0.3: Store quiz score with quiz_id tracking for multi-quiz support
     */
    private function store_quiz_score($user_id, $score_data) {
        $score = intval($score_data['score'] ?? 0);
        $quiz_id = sanitize_key($score_data['quiz_id'] ?? 'default_quiz');
        
        // Store most recent score
        update_user_meta($user_id, '_flosc_last_quiz_score', $score);
        update_user_meta($user_id, '_flosc_last_quiz_id', $quiz_id);
        update_user_meta($user_id, '_flosc_last_quiz_data', $score_data);
        update_user_meta($user_id, '_flosc_quiz_completed_at', current_time('mysql'));
        
        // Store initial score if this is first quiz ever
        $initial_score = get_user_meta($user_id, '_flosc_initial_score', true);
        if (empty($initial_score)) {
            update_user_meta($user_id, '_flosc_initial_score', $score);
            update_user_meta($user_id, '_flosc_initial_quiz_id', $quiz_id);
        }
        
        // Track all quiz attempts
        $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
        if (!is_array($attempts)) {
            $attempts = [];
        }
        
        $attempts[] = [
            'quiz_id' => $quiz_id,
            'score' => $score,
            'timestamp' => current_time('mysql'),
        ];
        
        update_user_meta($user_id, '_flosc_quiz_attempts', $attempts);
    }
    
    /**
     * Send quiz score email with OTO
     */
    private function send_score_email($user, $score_data) {
        $product_name = get_option('flosc_product_name', 'FLOSC App');
        $score = $score_data['score'];
        $correct = $score_data['correct'] ?? [];
        $incorrect = $score_data['incorrect'] ?? [];
        
        // Get OTO offer
        $oto_offer_id = get_option('flosc_oto_offer_id', '');
        $oto_offer = null;
        $oto_link = home_url('/' . get_option('flosc_app_slug', 'app') . '/');
        
        if ($oto_offer_id) {
            $oto_offer = $this->sale_manager->offers()->get_offer($oto_offer_id);
        }
        
        // Build email
        $subject = get_option('flosc_email_subject', "Your {$product_name} Quiz Results: {$score}%");
        $subject = str_replace(['{score}', '{product_name}'], [$score, $product_name], $subject);
        
        // Email body
        $body_template = get_option('flosc_email_body', $this->get_default_email_template());
        
        $correct_list = !empty($correct) ? implode(', ', $correct) : 'None';
        $incorrect_list = !empty($incorrect) ? implode(', ', $incorrect) : 'None';
        
        $oto_section = '';
        if ($oto_offer) {
            $oto_section = "\n\n🎁 SPECIAL OFFER FOR YOU:\n";
            $oto_section .= "{$oto_offer['name']} - {$oto_offer['display_price']}\n";
            $oto_section .= "{$oto_offer['description']}\n";
            $oto_section .= "Claim your offer: {$oto_link}\n";
        }
        
        $body = str_replace(
            ['{name}', '{score}', '{correct}', '{incorrect}', '{oto_section}', '{app_link}', '{product_name}'],
            [$user->display_name, $score, $correct_list, $incorrect_list, $oto_section, $oto_link, $product_name],
            $body_template
        );
        
        // Send
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $body, $headers);
        
        // Track
        do_action('flosc_score_email_sent', $user->ID, $score_data);
    }
    
    /**
     * Default email template
     */
    private function get_default_email_template() {
        return "Hi {name},

Thanks for taking the {product_name} quiz!

YOUR SCORE: {score}%

✅ Correct: {correct}
❌ Needs Practice: {incorrect}
{oto_section}
Your personalized learning path is ready. Log in to get your FREE lesson and start improving today!

{app_link}

Best,
The {product_name} Team";
    }
    
    /**
     * Rewrite Rules for Virtual Page
     */
    public function add_rewrite_rules() {
        $slug = get_option('flosc_app_slug', 'app');
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?flosc_app=1', 'top');
    }
    
    /**
     * Handle slug change - auto flush permalinks
     */
    public function handle_slug_change($old_value, $new_value) {
        if ($old_value !== $new_value) {
            flush_rewrite_rules();
        }
    }

    /**
     * Handle manual permalink flush from admin (v9.5.1)
     */
    public function handle_flush_permalinks() {
        check_admin_referer('flosc_flush_permalinks');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        flush_rewrite_rules();

        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=product&flushed=1'));
        exit;
    }

    public function add_query_vars($vars) {
        $vars[] = 'flosc_app';
        $vars[] = 'ref';
        return $vars;
    }
    
    public function handle_app_route() {
        if (!get_query_var('flosc_app')) return;
        
        // Track referral
        $ref = get_query_var('ref');
        if ($ref && !is_user_logged_in()) {
            setcookie('flosc_referrer', sanitize_text_field($ref), time() + (30 * DAY_IN_SECONDS), '/');
        }
        
        // Determine user state
        $user_state = 'visitor';
        $user_data = [];
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_state = $this->sale_manager->access()->get_simple_state($user->ID);

            // v07.09: Check for event flags (transients set by specific actions)
            $just_completed_quiz = (bool) get_transient('flosc_just_completed_quiz_' . $user->ID);
            $just_logged_in = (bool) get_transient('flosc_just_logged_in_' . $user->ID);
            $just_purchased = (bool) get_transient('flosc_just_purchased_' . $user->ID);

            // Clear transients after reading
            if ($just_completed_quiz) delete_transient('flosc_just_completed_quiz_' . $user->ID);
            if ($just_logged_in) delete_transient('flosc_just_logged_in_' . $user->ID);
            if ($just_purchased) delete_transient('flosc_just_purchased_' . $user->ID);

            $user_data = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 40]),
                'state' => $user_state,
                'purchased' => ($user_state === 'member'),  // v9.0.6: true if user is a member
                'access' => $this->sale_manager->access()->get_user_access($user->ID),
                'tokens' => $this->sale_manager->get_provider('tokens')->get_balance($user->ID),
                'freeLessonDelivered' => (bool) get_user_meta($user->ID, '_flosc_free_lesson_delivered', true),
                'lastQuizScore' => get_user_meta($user->ID, '_flosc_last_quiz_score', true),
                'lastQuizId' => get_user_meta($user->ID, '_flosc_last_quiz_id', true),
                'initialScore' => get_user_meta($user->ID, '_flosc_initial_score', true),
                'initialQuizId' => get_user_meta($user->ID, '_flosc_initial_quiz_id', true),
                'funnelCompleted' => (bool) get_user_meta($user->ID, '_flosc_funnel_completed', true),
                // v07.09: Event flags for IVR first_message_after_* conditions
                'justCompletedQuiz' => $just_completed_quiz,
                'justLoggedIn' => $just_logged_in,
                'justPurchased' => $just_purchased,
            ];
        }
        
        // Get product config
        $product = $this->get_product_config();
        
        // Get available offers
        $offers = $this->sale_manager->get_available_offers(
            is_user_logged_in() ? get_current_user_id() : null
        );
        
        // Get payment providers config for frontend
        $providers = [];
        foreach ($this->sale_manager->get_active_providers() as $id => $provider) {
            $providers[$id] = [
                'id' => $id,
                'name' => $provider->get_name(),
                'icon' => $provider->get_icon(),
                'config' => $provider->get_client_config(),
            ];
        }
        
        // Load template
        include FLOSC_PLUGIN_DIR . 'admin/flosc-app.php';
        exit;
    }
    
    /**
     * Get Product Configuration
     */
    public function get_product_config() {
        return [
            'name' => get_option('flosc_product_name', 'FLOSC App'),
            'tagline' => get_option('flosc_product_tagline', 'Your AI-powered assistant'),
            'emoji' => get_option('flosc_product_emoji', '🎯'),
            'logo_url' => get_option('flosc_product_logo', ''),
            'primary_color' => get_option('flosc_primary_color', '#4f46e5'),
            'share_text' => get_option('flosc_share_text', 'Check out this amazing app!'),
        ];
    }

    /**
     * Build AI context for phase-aware prompts (v04_04)
     */
    public function build_ai_context($frontend_context = []) {
        $context = [];

        // 1. Determine FLOSC phase
        $context['phase'] = $this->determine_flosc_phase();

        // 2. User info
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context['user_name'] = $user->display_name;
            $context['user_email'] = $user->user_email;
            $context['user_status'] = $this->get_user_status();

            // Quiz score
            $quiz_score = get_user_meta($user->ID, '_flosc_last_quiz_score', true);
            if ($quiz_score) {
                $context['quiz_score'] = $quiz_score . '%';
            }

            // Free lesson delivered
            $free_lesson_delivered = get_user_meta($user->ID, '_flosc_free_lesson_delivered', true);
            $context['free_lesson_delivered'] = $free_lesson_delivered ? 'Yes' : 'No';

            // Purchase status
            $context['purchased'] = $this->sale_manager->access()->can_access($user->ID, 'full') ? 'Yes' : 'No';
        } else {
            $context['user_status'] = 'visitor';
            $context['purchased'] = 'No';
        }

        // 3. Merge frontend context (message count, quiz taken, etc.)
        if (!empty($frontend_context)) {
            $context = array_merge($context, $frontend_context);
        }

        // 4. Product info
        $product = $this->get_product_config();
        $context['product_name'] = $product['name'];

        return $context;
    }

    /**
     * Determine current FLOSC phase
     */
    private function determine_flosc_phase() {
        if (!is_user_logged_in()) {
            // Check if quiz was taken (via cookie or transient)
            $quiz_taken = isset($_COOKIE['flosc_quiz_taken']) && $_COOKIE['flosc_quiz_taken'] === '1';
            return $quiz_taken ? 'login-prompt' : 'freeline';
        }

        $user_id = get_current_user_id();

        // Check if user has purchased
        if ($this->sale_manager->access()->can_access($user_id, 'full')) {
            // Check if onboarded
            $funnel_complete = get_user_meta($user_id, '_flosc_funnel_completed', true);
            if ($funnel_complete) {
                return 'content';
            }
            return 'sale';
        }

        // Check if offer was shown
        $offer_shown = get_user_meta($user_id, '_flosc_offer_shown', true);
        if ($offer_shown) {
            return 'offer';
        }

        // Default to login phase (logged in, pre-offer)
        return 'login';
    }

    /**
     * Get user status (visitor, free, paid)
     */
    private function get_user_status() {
        if (!is_user_logged_in()) {
            return 'visitor';
        }

        $user_id = get_current_user_id();
        return $this->sale_manager->access()->can_access($user_id, 'full') ? 'paid' : 'free';
    }

    /**
     * Admin Menu
     * v05_02: Menu shortcuts to Settings tabs in logical order
     */
    public function add_admin_menu() {
        // Main FLOSC menu - clicking goes to Settings (Product tab)
        add_menu_page(
            'FLOSC Settings',
            'FLOSC',
            'manage_options',
            'flosc-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            30
        );

        // All submenu items redirect to Settings page with appropriate tab
        // Order: Settings → Product → IVR Messages → Chat Styling → AI Configuration → Quiz → Email → AI Knowledge → Offers → Payments → Lessons

        // 1. Settings (Product tab)
        add_submenu_page(
            'flosc-settings',
            'FLOSC Settings',
            'Settings',
            'manage_options',
            'flosc-settings', // Same slug = first item replaces parent
            [$this, 'render_admin_page']
        );

        // 2. Product
        add_submenu_page(
            'flosc-settings',
            'Product Settings',
            'Product',
            'manage_options',
            'flosc-product',
            [$this, 'redirect_to_product_tab']
        );

        // 3. IVR Messages
        add_submenu_page(
            'flosc-settings',
            'IVR Messages',
            'IVR Messages',
            'manage_options',
            'flosc-ivr-messages',
            [$this, 'redirect_to_ivr_tab']
        );

        // 4. Chat Styling
        add_submenu_page(
            'flosc-settings',
            'Chat Styling',
            'Chat Styling',
            'manage_options',
            'flosc-chat-style',
            [$this, 'redirect_to_style_tab']
        );

        // 5. AI Configuration
        add_submenu_page(
            'flosc-settings',
            'AI Configuration',
            'AI Configuration',
            'manage_options',
            'flosc-ai-config',
            [$this, 'redirect_to_ai_tab']
        );

        // 5. Quiz
        add_submenu_page(
            'flosc-settings',
            'Quiz Settings',
            'Quiz',
            'manage_options',
            'flosc-quiz',
            [$this, 'redirect_to_quiz_tab']
        );

        // 5. Email
        add_submenu_page(
            'flosc-settings',
            'Email Settings',
            'Email',
            'manage_options',
            'flosc-email',
            [$this, 'redirect_to_email_tab']
        );

        // 6. AI Knowledge
        add_submenu_page(
            'flosc-settings',
            'AI Knowledge',
            'AI Knowledge',
            'manage_options',
            'flosc-ai-knowledge',
            [$this, 'redirect_to_ai_knowledge_tab']
        );

        // 7. Offers
        add_submenu_page(
            'flosc-settings',
            'Offers',
            'Offers',
            'manage_options',
            'flosc-offers',
            [$this, 'redirect_to_offers_tab']
        );

        // 8. Payments
        add_submenu_page(
            'flosc-settings',
            'Payments',
            'Payments',
            'manage_options',
            'flosc-payments',
            [$this, 'redirect_to_payments_tab']
        );

        // 9. Lessons
        add_submenu_page(
            'flosc-settings',
            'Lessons',
            'Lessons',
            'manage_options',
            'flosc-lessons',
            [$this, 'redirect_to_lessons_tab']
        );
        
        // 10. IVR Editor (dedicated page - hidden from menu)
        add_submenu_page(
            null, // null = hidden from menu, but accessible via URL
            'IVR Message Editor',
            'IVR Editor',
            'manage_options',
            'flosc-ivr',
            [$this, 'render_ivr_editor']
        );
    }
    
    /**
     * Register Settings
     */
    public function register_settings() {
        // Product Settings
        register_setting('flosc_settings', 'flosc_app_slug');
        register_setting('flosc_settings', 'flosc_product_name');
        register_setting('flosc_settings', 'flosc_product_tagline');
        register_setting('flosc_settings', 'flosc_product_emoji');
        register_setting('flosc_settings', 'flosc_product_logo');
        register_setting('flosc_settings', 'flosc_primary_color');
        register_setting('flosc_settings', 'flosc_share_text');
        
        // AI Provider
        register_setting('flosc_settings', 'flosc_ai_provider');
        register_setting('flosc_settings', 'flosc_openai_api_key');
        register_setting('flosc_settings', 'flosc_anthropic_api_key');
        register_setting('flosc_settings', 'flosc_xai_api_key');

        // v04_04: AI Configuration
        register_setting('flosc_ai_settings', 'flosc_ai_provider');
        register_setting('flosc_ai_settings', 'flosc_openai_api_key');
        register_setting('flosc_ai_settings', 'flosc_anthropic_api_key');
        register_setting('flosc_ai_settings', 'flosc_xai_api_key');
        register_setting('flosc_ai_settings', 'flosc_ai_base_prompt');
        
        // STT Provider
        register_setting('flosc_settings', 'flosc_stt_provider');
        register_setting('flosc_settings', 'flosc_assemblyai_api_key');
        register_setting('flosc_settings', 'flosc_deepgram_api_key');
        register_setting('flosc_settings', 'flosc_custom_stt_endpoint');

        // Quiz Type System
        register_setting('flosc_settings', 'flosc_quiz_type');
        register_setting('flosc_settings', 'flosc_enabled_quizzes'); // v9.3.4: Multi-quiz activation
        
        // Third-party quiz plugin integrations (v9.3.4)
        register_setting('flosc_settings', 'flosc_wpq_integration');  // Wp-Pro-Quiz
        register_setting('flosc_settings', 'flosc_ld_integration');   // LearnDash
        register_setting('flosc_settings', 'flosc_qsm_integration');  // Quiz & Survey Master

        // Register quiz content settings for each quiz type dynamically
        $quiz_types = FLOSC_Quiz_Type_Factory::get_all_quiz_types();
        foreach ($quiz_types as $quiz_id => $quiz_type) {
            register_setting('flosc_settings', 'flosc_quiz_content_' . $quiz_id);

            // Register quiz-specific settings
            $settings_fields = $quiz_type->get_settings_fields();
            foreach ($settings_fields as $field_key => $field_config) {
                register_setting('flosc_settings', 'flosc_quiz_' . $quiz_id . '_' . $field_key);
            }

            // Register response templates
            $templates = $quiz_type->get_default_response_templates();
            foreach (array_keys($templates) as $template_key) {
                register_setting('flosc_settings', 'flosc_quiz_' . $quiz_id . '_template_' . $template_key);
            }
        }
        
        // Analytics
        register_setting('flosc_settings', 'flosc_ga4_id');
        
        // BuddyBoss
        register_setting('flosc_settings', 'flosc_buddyboss_group_id');
        
        // Lessons
        register_setting('flosc_settings', 'flosc_lessons_category');
        register_setting('flosc_settings', 'flosc_oto_offer_id');
        
        // Email
        register_setting('flosc_settings', 'flosc_email_subject');
        register_setting('flosc_settings', 'flosc_email_body');

        // Payments (v5.0.6)
        register_setting('flosc_settings', 'flosc_stripe_enabled');
        register_setting('flosc_settings', 'flosc_stripe_mode');
        register_setting('flosc_settings', 'flosc_stripe_test_pk');
        register_setting('flosc_settings', 'flosc_stripe_test_sk');
        register_setting('flosc_settings', 'flosc_stripe_live_pk');
        register_setting('flosc_settings', 'flosc_stripe_live_sk');

        // ClickBank (v7.0.7)
        register_setting('flosc_settings', 'flosc_clickbank_enabled');
        register_setting('flosc_settings', 'flosc_clickbank_mode');
        register_setting('flosc_settings', 'flosc_clickbank_vendor');
        register_setting('flosc_settings', 'flosc_clickbank_secret');
        register_setting('flosc_settings', 'flosc_clickbank_product');
        register_setting('flosc_settings', 'flosc_clickbank_access_level');

        // AI Base Prompt (v5.0.6 - added to flosc_settings group)
        register_setting('flosc_settings', 'flosc_ai_base_prompt');

        // Chat Style Settings (v9.3.7 - Clean Architecture)
        register_setting('flosc_settings', 'flosc_chat_style_preset');      // day, night
        register_setting('flosc_settings', 'flosc_chat_style_bubble');      // subtle-notch, classic, modern, minimal, sharp
        register_setting('flosc_settings', 'flosc_chat_style_accent');      // hex color
        register_setting('flosc_settings', 'flosc_chat_style_font');        // system, inter, ibm-plex-*, roboto-*, fira-code
        register_setting('flosc_settings', 'flosc_chat_style_scale');       // 100-150
        register_setting('flosc_settings', 'flosc_chat_style_custom_css');  // raw CSS
    }
    
    /**
     * Render Admin Pages
     */
    public function render_admin_page() {
        include FLOSC_PLUGIN_DIR . 'admin/settings.php';
    }
    
    // Offers now integrated into main settings page
    
    // Payments now integrated into main settings page

    // AI Config now integrated into main settings page

    // AI Knowledge now integrated into main settings page

    // Chat Style now integrated into main settings page

    /**
     * Redirect handlers for tab shortcuts - ALL menu items go to Settings tabs
     */
    public function redirect_to_product_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=product'));
        exit;
    }

    public function redirect_to_ivr_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ivr-messages'));
        exit;
    }

    public function redirect_to_style_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=style'));
        exit;
    }

    public function redirect_to_ai_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ai'));
        exit;
    }

    public function redirect_to_quiz_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=quiz'));
        exit;
    }

    public function redirect_to_email_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=email'));
        exit;
    }

    public function redirect_to_ai_knowledge_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ai-knowledge'));
        exit;
    }

    public function redirect_to_offers_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=offers'));
        exit;
    }

    public function redirect_to_payments_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=payments'));
        exit;
    }

    public function redirect_to_lessons_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=lessons'));
        exit;
    }
    
    /**
     * Shortcode: [flosc_visitor_only]
     * Shows content only to non-logged-in visitors
     */
    public function shortcode_visitor_only($atts, $content = '') {
        if (!is_user_logged_in()) {
            return do_shortcode($content);
        }
        return '';
    }
    
    /**
     * Shortcode: [flosc_member_only]
     * Shows content only to members (users with _flosc_member_access = true)
     * 
     * Usage: [flosc_member_only fallback="Upgrade to unlock"]Content here[/flosc_member_only]
     * 
     * @param array $atts Shortcode attributes (fallback message)
     * @param string $content Shortcode content
     * @return string
     */
    public function shortcode_member_only($atts, $content = '') {
        // Parse attributes
        $atts = shortcode_atts([
            'fallback' => '', // Optional fallback message for non-members
        ], $atts);
        
        if (!is_user_logged_in()) {
            return $atts['fallback'] ? '<div class="flosc-member-only-fallback">' . esc_html($atts['fallback']) . '</div>' : '';
        }
        
        $user_id = get_current_user_id();
        $is_member = get_user_meta($user_id, '_flosc_member_access', true);
        
        if ($is_member === 'true' || $is_member === true) {
            return do_shortcode($content);
        }
        
        // Not a member - show fallback if provided
        return $atts['fallback'] ? '<div class="flosc-member-only-fallback">' . esc_html($atts['fallback']) . '</div>' : '';
    }
    
    /**
     * Render IVR Message Editor (dedicated page)
     */
    public function render_ivr_editor() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        include FLOSC_PLUGIN_DIR . 'admin/ivr-settings.php';
    }

    /**
     * Enqueue Assets
     */
    public function enqueue_assets() {
        if (!get_query_var('flosc_app')) return;
        
        // Dequeue theme styles
        global $wp_styles;
        foreach ($wp_styles->queue as $handle) {
            wp_dequeue_style($handle);
        }
        
        // Our assets - v9.3.7 Clean CSS Architecture
        // 1. Layout CSS (structure only, no colors)
        wp_enqueue_style(
            'flosc-layout', 
            FLOSC_PLUGIN_URL . 'assets/css/flosc-layout.css', 
            [], 
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-layout.css')
        );
        
        // 2. Theme CSS (connects variables to selectors)
        wp_enqueue_style(
            'flosc-theme', 
            FLOSC_PLUGIN_URL . 'assets/css/flosc-theme.css', 
            ['flosc-layout'], 
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-theme.css')
        );
        
        // 3. Preset CSS (variable definitions only)
        $this->enqueue_chat_style();

        wp_enqueue_script('flosc-app', FLOSC_PLUGIN_URL . 'assets/js/flosc-app.js', [], filemtime(FLOSC_PLUGIN_DIR . 'assets/js/flosc-app.js'), true);
        
        // Stripe.js if configured
        $stripe = $this->sale_manager->get_provider('stripe');
        if ($stripe && $stripe->is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, false);
        }
    }

    /**
     * Enqueue chat styling (v9.3.9 - Bulletproof Architecture)
     * 
     * Architecture:
     * 1. flosc-layout.css - Structure only (already enqueued)
     * 2. flosc-theme.css - Variable consumption (already enqueued)
     * 3. This method - Variable definitions via inline CSS
     * 
     * Presets: auto (system preference), light, dark
     * Customization: bubble style, accent color, font, scale, custom CSS
     */
    private function enqueue_chat_style() {
        // Get settings with safe defaults
        $preset     = get_option('flosc_chat_style_preset', 'light');
        $bubble     = get_option('flosc_chat_style_bubble', 'subtle-notch');
        $accent     = get_option('flosc_chat_style_accent', '');
        $font       = get_option('flosc_chat_style_font', 'system');
        $scale      = intval(get_option('flosc_chat_style_scale', 100));
        $custom_css = get_option('flosc_chat_style_custom_css', '');

        // Bubble style presets (border-radius values per FLOSC_STYLE_GUIDE.md)
        $bubble_styles = [
            'subtle-notch' => ['user' => '18px 18px 4px 18px', 'assistant' => '4px 18px 18px 18px'],
            'classic'      => ['user' => '18px 18px 0 18px',   'assistant' => '0 18px 18px 18px'],
            'modern'       => ['user' => '20px 20px 6px 20px', 'assistant' => '6px 20px 20px 20px'],
            'minimal'      => ['user' => '16px',               'assistant' => '16px'],
            'sharp'        => ['user' => '12px 12px 2px 12px', 'assistant' => '2px 12px 12px 12px'],
        ];

        // Font family map
        $font_families = [
            'system'        => '',
            'inter'         => '"Inter", -apple-system, sans-serif',
            'ibm-plex-sans' => '"IBM Plex Sans", -apple-system, sans-serif',
            'ibm-plex-mono' => '"IBM Plex Mono", "SF Mono", Monaco, monospace',
            'roboto'        => '"Roboto", -apple-system, sans-serif',
            'roboto-mono'   => '"Roboto Mono", "SF Mono", Monaco, monospace',
            'fira-code'     => '"Fira Code", "SF Mono", Monaco, monospace',
        ];

        // File paths
        $light_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-light.css';
        $dark_path  = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-dark.css';
        
        $inline_css = "";

        // ===========================================
        // PRESET LOADING
        // ===========================================
        if ($preset === 'auto') {
            // Auto mode: Light by default, dark via prefers-color-scheme
            if (file_exists($light_path) && file_exists($dark_path)) {
                $light_content = @file_get_contents($light_path);
                $dark_content  = @file_get_contents($dark_path);
                
                if ($light_content) {
                    $light_vars = $this->extract_css_variables($light_content);
                    if ($light_vars) {
                        $inline_css .= "/* Light Theme (Default) */\n:root {\n{$light_vars}}\n\n";
                    }
                }
                
                if ($dark_content) {
                    $dark_vars = $this->extract_css_variables($dark_content);
                    if ($dark_vars) {
                        $inline_css .= "/* Dark Theme (System Preference) */\n@media (prefers-color-scheme: dark) {\n  :root {\n{$dark_vars}  }\n}\n\n";
                    }
                }
            }
        } elseif ($preset === 'light' || $preset === 'dark') {
            // Specific preset: load as external stylesheet
            $preset_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-' . $preset . '.css';
            if (file_exists($preset_path)) {
                wp_enqueue_style(
                    'flosc-preset',
                    FLOSC_PLUGIN_URL . 'assets/css/chat-style-' . $preset . '.css',
                    ['flosc-theme'],
                    filemtime($preset_path)
                );
            }
        }

        // ===========================================
        // DYNAMIC OVERRIDES
        // ===========================================
        $bubble_config = $bubble_styles[$bubble] ?? $bubble_styles['subtle-notch'];
        
        $overrides = [];
        $overrides[] = "--flosc-user-message-radius: {$bubble_config['user']}";
        $overrides[] = "--flosc-assistant-message-radius: {$bubble_config['assistant']}";
        
        // Accent color
        if (!empty($accent) && $accent !== '#2563eb') {
            $overrides[] = "--flosc-accent: {$accent}";
            $overrides[] = "--flosc-accent-hover: {$accent}";
            $overrides[] = "--flosc-user-message-bg: {$accent}";
            $overrides[] = "--flosc-user-avatar-bg: {$accent}";
            $overrides[] = "--flosc-send-btn-bg: {$accent}";
        }
        
        // Scale factor
        if ($scale !== 100 && $scale > 0) {
            $scale_factor = $scale / 100;
            $overrides[] = "--flosc-scale: {$scale_factor}";
        }
        
        // Font family
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $overrides[] = "--flosc-font-family: {$font_families[$font]}";
        }
        
        if (!empty($overrides)) {
            $inline_css .= "/* Dynamic Overrides */\n:root {\n    " . implode(";\n    ", $overrides) . ";\n}\n\n";
        }
        
        // Font application
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $inline_css .= "/* Font Application */\n";
            $inline_css .= ".flosc-app,\n.flosc-app .messages,\n.flosc-app .message-text {\n";
            $inline_css .= "    font-family: var(--flosc-font-family) !important;\n}\n\n";
        }

        // Custom CSS
        if (!empty(trim($custom_css))) {
            $inline_css .= "/* Custom CSS */\n" . trim($custom_css) . "\n";
        }

        // Attach inline styles to flosc-theme handle (always exists)
        if (!empty(trim($inline_css))) {
            wp_add_inline_style('flosc-theme', $inline_css);
        }
    }

    /**
     * Extract CSS variables from stylesheet content
     * Returns the inner content of :root { } block
     * 
     * @param string $css_content Raw CSS file content
     * @return string Variable declarations or empty string
     */
    private function extract_css_variables($css_content) {
        if (empty($css_content)) {
            return '';
        }
        
        // Remove CSS comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css_content);
        
        // Extract content inside :root { }
        if (preg_match('/:root\s*\{([^}]+)\}/s', $css, $matches)) {
            return trim($matches[1]) . "\n";
        }
        
        return '';
    }

    /**
     * REST API Routes
     * v9.4.2: Added rate limiting to public endpoints
     */
    public function register_rest_routes() {
        // IVR Chat (primary endpoint)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/chat', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
        
        // RAG Chat (v9.1.6 - AI with search capabilities)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/chat-rag', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_chat_with_rag'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Quiz Submission (NEW: for collecting quiz answers)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_quiz_submission'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.3.2: GET quiz questions for in-chat quiz
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/quiz', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quiz_questions'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.3.2: Store quiz results
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/quiz-result', [
            'methods' => 'POST',
            'callback' => [$this, 'store_quiz_result'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // AI Query
        register_rest_route('flosc/v1', '/ai-query', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_query'],
            'permission_callback' => [$this, 'check_paid_endpoint_permission'],
        ]);

        // Process Audio (for audio-based quiz types)
        register_rest_route('flosc/v1', '/process-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => [$this, 'check_paid_endpoint_permission'],
        ]);

        // Process Quiz (for text-based quiz types)
        register_rest_route('flosc/v1', '/process-quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_quiz'],
            'permission_callback' => [$this, 'check_paid_endpoint_permission'],
        ]);
        
        // Sessions
        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sessions'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Offers
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_offers'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
        
        // Purchase
        register_rest_route('flosc/v1', '/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_purchase'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Free Lesson (v9.1.9)
        register_rest_route('flosc/v1', '/free-lesson', [
            'methods' => 'GET',
            'callback' => [$this, 'get_free_lesson'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Create Payment Intent (for Stripe)
        register_rest_route('flosc/v1', '/create-payment-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'create_payment_intent'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Webhooks (from payment providers like Stripe)
        // NOTE: Must remain __return_true - payment providers can't pass WP auth
        // Security is handled via webhook signature verification in handle_webhook()
        register_rest_route('flosc/v1', '/webhooks/(?P<provider>[a-z]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
        ]);
        
        // Access check
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$this, 'check_access'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
        
        // Token balance
        register_rest_route('flosc/v1', '/tokens', [
            'methods' => 'GET',
            'callback' => [$this, 'get_token_balance'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Purchase intents (affiliate system)
        register_rest_route('flosc/v1', '/intents', [
            'methods' => 'POST',
            'callback' => [$this, 'declare_intent'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        register_rest_route('flosc/v1', '/intents/(?P<id>[a-z0-9_]+)/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_intent_offers'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Referral
        register_rest_route('flosc/v1', '/referral', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_referral'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Lessons
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/lessons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lessons'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
        
        register_rest_route('flosc/v1', '/lessons/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lesson'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        register_rest_route('flosc/v1', '/lessons/free', [
            'methods' => 'GET',
            'callback' => [$this, 'get_free_lesson'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // IVR Messages (v9.2.2)
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/ivr-messages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_ivr_messages'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
        
        // Store pre-login score
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/store-score', [
            'methods' => 'POST',
            'callback' => [$this, 'store_prelogin_score'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // Mark funnel completed (v3.0.4)
        register_rest_route('flosc/v1', '/funnel-complete', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_funnel_complete'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Test AI connection (v04_05)
        register_rest_route('flosc/v1', '/test-ai', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test_ai'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        // v07.09: IVR message tracking
        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/ivr/track', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ivr_track'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);

        // v9.4.2: Now rate-limited via check_public_endpoint_permission
        register_rest_route('flosc/v1', '/ivr/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_ivr_get_messages'],
            'permission_callback' => [$this, 'check_public_endpoint_permission'],
        ]);
    }
    
    /**
     * REST Handlers
     */
    
    /**
     * Handle IVR Chat - Process user messages through IVR system
     * Returns next IVR message/response based on current phase and conditions
     */
    public function handle_chat($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $session_id = intval($request->get_param('session_id')) ?? null;
        $context = $request->get_param('context') ?? [];
        
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }
        
        // Get IVR configuration
        $ivr_config = get_option('flosc_ivr_config', []);
        
        // If not set in options, try loading via parser (fresh install fallback)
        if (empty($ivr_config)) {
            $ivr_config = FLOSC_IVR_Parser::flosc_instance()->get_flosc_config();
        }
        
        if (empty($ivr_config)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'IVR not configured',
            ], 500);
        }
        
        // Determine phase from context
        $phase = $context['phase'] ?? 'freeline';
        
        // Build evaluation context
        $eval_context = [
            'logged_in' => is_user_logged_in(),
            'user_id' => is_user_logged_in() ? get_current_user_id() : 0,
            'phase' => $phase,
            'message_count' => intval($context['message_count'] ?? 0),
            'last_message' => $message,
        ];
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_data = get_userdata($user_id);
            $eval_context['user_name'] = $user_data->display_name ?? 'there';
            $eval_context['user_email'] = $user_data->user_email;
        }
        
        // Merge with passed context
        $eval_context = array_merge($eval_context, $context);
        
        // Find matching IVR message for this phase
        $response_message = $this->find_ivr_response($phase, $message, $eval_context, $ivr_config);
        
        if (!$response_message) {
            // Fallback response
            $response_message = [
                'content' => 'Thanks for your message! How can I help further?',
                'suggested_replies' => [],
                'phase_change' => null,
            ];
        }
        
        // Store message in session if user is logged in
        if (is_user_logged_in() && $session_id) {
            $this->session_manager->add_flosc_message($session_id, 'user', $message, get_current_user_id());
            $this->session_manager->add_flosc_message($session_id, 'assistant', $response_message['content'], get_current_user_id());
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => $response_message['content'],
            'suggested_replies' => $response_message['suggested_replies'] ?? [],
            'phaseChange' => $response_message['phase_change'] ?? null,
        ]);
    }

    /**
     * Handle chat with RAG (Retrieval Augmented Generation) - v9.1.6
     * AI can search WordPress content dynamically
     */
    public function handle_chat_with_rag($request) {
        $message = sanitize_text_field($request->get_param('message'));
        
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }
        
        // Get user context
        $user_context = $this->user_access_manager->get_user_context();
        
        error_log("FLOSC RAG Chat: User {$user_context['user_id']} ({$user_context['access_level']}) - Message: {$message}");
        
        // Build system prompt for AI
        $system_prompt = $this->build_rag_system_prompt($user_context);
        
        // Get available lessons list (for AI to know what exists)
        $lessons_list = $this->rag_manager->get_available_lessons($user_context['access_level']);
        
        // Add lessons to system prompt
        $system_prompt .= "\n\n**AVAILABLE CONTENT:**\n{$lessons_list}";
        
        // Get AI tools
        $tools = $this->rag_manager->get_ai_tools();
        
        // Call AI with tools (RAG enabled)
        $ai_response = $this->call_ai_with_rag($message, $system_prompt, $tools, $user_context);
        
        // CRITICAL: Validate response for access level compliance (v9.1.7)
        $validator = FLOSC_Access_Validator::instance();
        $validation_result = $validator->validate_response($ai_response, $user_context['access_level']);
        
        if (!$validation_result['valid']) {
            // Content leakage detected - use safe fallback
            error_log("FLOSC SECURITY ALERT: Content leakage prevented");
            error_log("FLOSC SECURITY: Original response: " . substr($ai_response, 0, 200));
            error_log("FLOSC SECURITY: Violations: " . json_encode($validation_result['violations']));
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => $validation_result['response'], // Use validated response
            'user_context' => [
                'access_level' => $user_context['access_level'],
                'is_member' => $user_context['is_member'],
            ],
            'validated' => $validation_result['valid'], // For debugging
        ]);
    }
    
    /**
     * Build system prompt for RAG chat
     */
    private function build_rag_system_prompt($user_context) {
        
        $access_level = $user_context['access_level'];
        $personality_name = get_option('flosc_personality_name', 'Brenda');
        $personality_desc = get_option('flosc_personality_description', 'friendly pronunciation coach');
        
        // Get access level instructions
        $access_instructions = $this->get_access_level_instructions($access_level);
        
        $prompt = "You are {$personality_name}, a {$personality_desc}.

**YOUR ROLE:**
You are a GUIDE, not a teacher. Your job is to:
1. Guide users through the learning journey
2. Direct them to WordPress lessons and content
3. Use search tools to find relevant content when needed
4. Encourage them through the funnel (visitor → quiz → member)

**CURRENT USER:**
- Access Level: **{$access_level}**
- Logged in: " . ($user_context['is_logged_in'] ? 'Yes' : 'No') . "
- Member: " . ($user_context['is_member'] ? 'Yes' : 'No') . "
";

        // Add quiz results if available
        if (isset($user_context['quiz_results'])) {
            $quiz_score = $user_context['quiz_score'] ?? 0;
            $prompt .= "\n**QUIZ RESULTS:**\n";
            $prompt .= "Score: {$quiz_score}%\n";
            $prompt .= "Details: " . json_encode($user_context['quiz_results']) . "\n";
            
            // Add pricing info if applicable
            if (isset($user_context['within_discount_window']) && $user_context['within_discount_window']) {
                $minutes_left = 30 - intval($user_context['minutes_since_quiz']);
                $prompt .= "\n**SPECIAL OFFER ACTIVE:**\n";
                $prompt .= "- User took quiz recently\n";
                $prompt .= "- {$minutes_left} minutes remaining for \$30 discount price\n";
                $prompt .= "- Mention this time-limited offer!\n";
            }
        }
        
        $prompt .= "\n" . $access_instructions;
        
        $prompt .= "\n\n**HOW TO USE TOOLS:**
- When you need information about specific lessons, use search_knowledge_base or search_posts
- When asked about what content is available, use search_posts
- When you need full lesson details, use get_lesson_content
- Always filter your responses based on the user's access level

**IMPORTANT:**
- DO NOT try to teach content yourself - point to the actual WordPress lessons
- DO respect access level restrictions
- DO encourage quiz-taking for visitors
- DO mention time-limited offers when applicable
- DO be warm and encouraging";

        return $prompt;
    }
    
    /**
     * Get access level specific instructions
     */
    private function get_access_level_instructions($access_level) {
        
        $instructions = [
            'visitor' => "
**🚨 CRITICAL ACCESS LEVEL: VISITOR (Not logged in) 🚨**

**YOUR ONLY JOB: GET THEM TO TAKE THE QUIZ**

What you CAN say:
- \"Take our free 2-minute quiz!\"
- \"The quiz will show you exactly where you stand\"
- \"Ready to see what you need to work on?\"
- General statements about the product (without details)

What you ABSOLUTELY CANNOT share:
- ❌ ANY lesson content
- ❌ ANY IPA transcriptions (no /w/, /ʌ/, etc)
- ❌ ANY pronunciation guides
- ❌ ANY pricing information
- ❌ ANY specific lesson titles or descriptions
- ❌ DO NOT use search tools for visitors
- ❌ DO NOT try to answer pronunciation questions

**STRICT RULE:**
Every response to a visitor MUST redirect to taking the quiz.

Example good response:
\"Great question! Take our free quiz first - it's just 2 minutes and will show you exactly what you need. Ready to start?\"

Example BAD response:
\"Lesson 7 covers...\" ← NO! Don't mention lessons!
\"The IPA is /sɪks/\" ← NO! No IPA ever!
\"It costs \$30...\" ← NO! No pricing!",
            
            'guest' => "
**🚨 ACCESS LEVEL: GUEST (Logged in, not member) 🚨**

**YOUR ONLY JOBS:**
1. Show quiz results
2. Present offers (with urgency)
3. Get them to become members

What you CAN share:
- Their quiz score and which lessons they need
- Lesson TITLES only (e.g., \"Lesson 7\")
- Brief one-sentence descriptions (\"covers pronunciation of 'seven'\")
- Pricing and offers
- Time-limited discount information
- Urgency messaging

What you ABSOLUTELY CANNOT share:
- ❌ Full lesson content
- ❌ IPA transcriptions
- ❌ Step-by-step guides
- ❌ Detailed pronunciation instructions
- ❌ Member-only content

**PRICING RULES:**
- Mention \$30 if within 30 min of quiz
- Mention \$100 regular price
- Create urgency with timer

Example good response:
\"You scored 30% on the quiz. You need Lessons 6 and 7. 

🔥 Special Offer: \$30/year (70% off) - Only 25 minutes left!

Ready to unlock these lessons?\"

Example BAD response:
\"The IPA for 'seven' is...\" ← NO! No IPA for guests!
\"Here's how to pronounce it step by step...\" ← NO! No detailed content!",
            
            'member' => "
**✅ ACCESS LEVEL: MEMBER (Full access granted)**

You can now share:
- ✅ Full lesson content
- ✅ IPA transcriptions
- ✅ Complete pronunciation guides
- ✅ Step-by-step instructions
- ✅ All member-only content

**YOUR ROLE:**
You are still a GUIDE. Don't try to teach yourself.
- Point them to specific lessons using search tools
- Link to WordPress posts
- Use get_lesson_content to show them what's available
- Celebrate their membership!

Example good response:
\"Great! As a member, you have full access. Based on your quiz, I recommend starting with Lesson 7: [link]. It has the complete IPA guide and video walkthrough. Ready to dive in?\"",

        ];
        
        return $instructions[$access_level] ?? $instructions['visitor'];
    }
    
    /**
     * Call AI with RAG tools (conversation loop)
     * PSEUDOCODE: Full Anthropic API implementation with tool calling
     */
    private function call_ai_with_rag($message, $system_prompt, $tools, $user_context) {
        
        // Get AI configuration
        $api_key = get_option('flosc_anthropic_api_key', '');
        
        if (empty($api_key)) {
            return "AI not configured. Please add your Anthropic API key in settings.";
        }
        
        $model = get_option('flosc_ai_model', 'claude-sonnet-4-5-20250929');
        
        // PSEUDOCODE: Conversation loop for tool calling
        // This allows AI to make multiple tool calls
        
        $messages = [
            [
                'role' => 'user',
                'content' => $message
            ]
        ];
        
        $max_iterations = 5; // Prevent infinite loops
        
        for ($i = 0; $i < $max_iterations; $i++) {
            
            // Call Anthropic API
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $model,
                    'max_tokens' => 2000,
                    'system' => $system_prompt,
                    'tools' => $tools,
                    'messages' => $messages,
                ]),
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                error_log("FLOSC RAG Error: " . $response->get_error_message());
                return "Sorry, I'm having trouble connecting. Please try again.";
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!isset($body['content'])) {
                error_log("FLOSC RAG: Invalid API response - " . json_encode($body));
                return "Sorry, I encountered an error. Please try again.";
            }
            
            $stop_reason = $body['stop_reason'] ?? 'end_turn';
            
            // Check if AI is done or wants to use tools
            if ($stop_reason === 'end_turn') {
                // AI is done - extract and return text response
                return $this->extract_text_from_response($body['content']);
            }
            
            if ($stop_reason === 'tool_use') {
                // AI wants to use tools!
                error_log("FLOSC RAG: AI requested tool use");
                
                // Add AI's response to conversation
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $body['content']
                ];
                
                // Execute tools and add results
                $tool_results = $this->execute_tools_from_response(
                    $body['content'],
                    $user_context['access_level']
                );
                
                $messages[] = [
                    'role' => 'user',
                    'content' => $tool_results
                ];
                
                // Continue loop - AI will process tool results
                continue;
            }
            
            // Unexpected stop reason
            error_log("FLOSC RAG: Unexpected stop reason: {$stop_reason}");
            break;
        }
        
        // If we hit max iterations
        return "I encountered an issue processing your request. Please try again.";
    }
    
    /**
     * Extract text response from AI content blocks
     */
    private function extract_text_from_response($content_blocks) {
        $text = '';
        
        foreach ($content_blocks as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
        
        return $text;
    }
    
    /**
     * Execute tools requested by AI
     */
    private function execute_tools_from_response($content_blocks, $access_level) {
        
        $tool_results = [];
        
        foreach ($content_blocks as $block) {
            if ($block['type'] === 'tool_use') {
                
                $tool_name = $block['name'];
                $tool_input = $block['input'];
                $tool_use_id = $block['id'];
                
                error_log("FLOSC RAG: Executing tool '{$tool_name}' with input: " . json_encode($tool_input));
                
                // Execute the tool
                $result = $this->rag_manager->execute_tool($tool_name, $tool_input, $access_level);
                
                error_log("FLOSC RAG: Tool result length: " . strlen($result) . " chars");
                
                // Format result for AI
                $tool_results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $tool_use_id,
                    'content' => $result
                ];
            }
        }
        
        return $tool_results;
    }
    /**
     * Handle quiz submission (NEW)
     * 
     * Receives quiz answers, scores them, and triggers bridge data state
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_quiz_submission($request) {
        // TODO: Implement quiz submission handling
        // Pseudocode:
        // 1. Get quiz_id and answers from request
        // 2. Load quiz manager
        // 3. Score the quiz
        // 4. Create/update user profile if needed
        // 5. Store quiz results via bridge data manager
        // 6. Return results summary + bridge data prompt
        //
        // Example implementation:
        // $quiz_id = sanitize_text_field($request->get_param('quiz_id'));
        // $answers = $request->get_param('answers') ?? [];
        //
        // if (empty($quiz_id) || empty($answers)) {
        //     return new WP_REST_Response([
        //         'success' => false,
        //         'error' => 'quiz_id and answers required',
        //     ], 400);
        // }
        //
        // require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-manager.php';
        // $quiz_mgr = new FLOSC_Quiz_Manager();
        //
        // // Score the quiz
        // $results = $quiz_mgr->score_flosc_quiz($quiz_id, $answers);
        //
        // // Get or create user (can be anonymous at this point)
        // $user_id = is_user_logged_in() ? get_current_user_id() : 0;
        //
        // // If user is logged in, store bridge data
        // if ($user_id) {
        //     require_once FLOSC_PLUGIN_DIR . 'includes/class-bridge-data-manager.php';
        //     $bridge_mgr = new FLOSC_Bridge_Data_Manager();
        //     $bridge_mgr->flosc_create_bridge_data($user_id, $quiz_id, $results);
        // }
        //
        // // Get preview item for bridge data
        // $preview = $quiz_mgr->get_flosc_bridge_preview_item($quiz_id, $results['incorrect_items']);
        //
        // return new WP_REST_Response([
        //     'success' => true,
        //     'score' => $results['score'],
        //     'percentage' => $results['percentage'],
        //     'total_questions' => $results['total_questions'],
        //     'correct_items' => $results['correct_items'],
        //     'incorrect_items' => $results['incorrect_items'],
        //     'preview_item' => $preview,
        //     'bridge_data_active' => (bool) $user_id,
        // ]);

        // PLACEHOLDER: Return mock response for testing
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Quiz submission endpoint is under development',
            'score' => 0,
            'percentage' => 0,
            'total_questions' => 0,
            'correct_items' => [],
            'incorrect_items' => [],
            'preview_item' => null,
            'bridge_data_active' => is_user_logged_in(),
        ]);
    }

    /**
     * v9.3.4: Get quiz questions for in-chat quiz
     * 
     * When multiple quizzes are enabled, rotates ABAB pattern
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_quiz_questions($request) {
        $quiz_id = sanitize_text_field($request->get_param('id') ?? 'default');
        
        // v9.3.4: If 'default', rotate through ENABLED quizzes (ABAB pattern)
        if ($quiz_id === 'default') {
            $enabled_quizzes = get_option('flosc_enabled_quizzes', ['flosc_sample_text_based_quiz']);
            if (!is_array($enabled_quizzes) || empty($enabled_quizzes)) {
                $enabled_quizzes = ['flosc_sample_text_based_quiz'];
            }
            
            // Get rotation counter and increment
            $rotation_count = intval(get_option('flosc_quiz_rotation_count', 0));
            update_option('flosc_quiz_rotation_count', $rotation_count + 1);
            
            // Pick quiz based on rotation (ABAB pattern)
            $quiz_index = $rotation_count % count($enabled_quizzes);
            $quiz_id = $enabled_quizzes[$quiz_index];
        }
        
        // Get the quiz type handler
        $quiz_type = FLOSC_Quiz_Type_Factory::get_quiz_type($quiz_id);
        
        if ($quiz_type) {
            // Get content from admin settings
            $content = get_option('flosc_quiz_content_' . $quiz_id, $quiz_type->get_default_content());
            
            // Check if this is a TEXT SEQUENCE quiz (type: 1,2,3...10)
            if ($quiz_id === 'flosc_sample_text_based_quiz') {
                // Parse expected values - ensure we have valid content
                $expected = array_filter(array_map('trim', explode(',', $content)), function($v) {
                    return $v !== '';
                });
                // Fallback to default if empty
                if (empty($expected)) {
                    $expected = ['1','2','3','4','5','6','7','8','9','10'];
                }
                // Return text sequence quiz format
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $quiz_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'text_sequence',
                    'prompt' => 'Type the sequence from 1 to 10 (e.g., "1, 2, 3, 4, 5, 6, 7, 8, 9, 10")',
                    'expected' => array_values($expected),
                    'instructions' => $quiz_type->get_instructions(),
                ]);
            }
            
            // Check if this is AUDIO quiz
            if ($quiz_id === 'flosc_sample_audio_quiz') {
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $quiz_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'audio',
                    'prompt' => 'Record yourself saying the sequence from 1 to 10',
                    'expected' => array_map('trim', explode(',', $content)),
                    'instructions' => $quiz_type->get_instructions(),
                ]);
            }
            
            // Check if this is MULTIPLE CHOICE
            if ($quiz_id === 'multiplechoice') {
                // Parse content as JSON or structured format
                $questions = $this->parse_multiplechoice_content($content);
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $quiz_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'multiple_choice',
                    'questions' => $questions,
                ]);
            }
        }
        
        // Fallback: return sample assessment quiz
        $sample_questions = [
            [
                'id' => 'q1',
                'text' => 'How would you rate your current skill level?',
                'options' => [
                    ['key' => 'A', 'text' => 'Complete beginner'],
                    ['key' => 'B', 'text' => 'Some basics'],
                    ['key' => 'C', 'text' => 'Intermediate'],
                    ['key' => 'D', 'text' => 'Advanced'],
                ],
                'correct' => null,
            ],
            [
                'id' => 'q2',
                'text' => 'How much time can you dedicate to practice each week?',
                'options' => [
                    ['key' => 'A', 'text' => 'Less than 1 hour'],
                    ['key' => 'B', 'text' => '1-3 hours'],
                    ['key' => 'C', 'text' => '3-5 hours'],
                    ['key' => 'D', 'text' => 'More than 5 hours'],
                ],
                'correct' => null,
            ],
            [
                'id' => 'q3',
                'text' => 'What is your primary goal?',
                'options' => [
                    ['key' => 'A', 'text' => 'Personal improvement'],
                    ['key' => 'B', 'text' => 'Professional development'],
                    ['key' => 'C', 'text' => 'Academic requirements'],
                    ['key' => 'D', 'text' => 'Just curious to learn'],
                ],
                'correct' => null,
            ],
        ];
        
        return new WP_REST_Response([
            'success' => true,
            'id' => 'sample',
            'title' => 'Quick Assessment',
            'type' => 'multiple_choice',
            'questions' => $sample_questions,
        ]);
    }
    
    /**
     * v9.3.4: Parse multiple choice content from admin textarea
     */
    private function parse_multiplechoice_content($content) {
        // Simple format: Question?|A:Answer1|B:Answer2|C:Answer3|correct:A
        $questions = [];
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) < 3) continue;
            
            $question = [
                'id' => 'q' . (count($questions) + 1),
                'text' => trim($parts[0]),
                'options' => [],
                'correct' => null,
            ];
            
            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);
                if (strpos($part, 'correct:') === 0) {
                    $question['correct'] = substr($part, 8);
                } elseif (preg_match('/^([A-D]):(.+)$/', $part, $m)) {
                    $question['options'][] = ['key' => $m[1], 'text' => trim($m[2])];
                }
            }
            
            if (!empty($question['options'])) {
                $questions[] = $question;
            }
        }
        
        return $questions;
    }

    /**
     * v9.3.2: Store quiz result
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function store_quiz_result($request) {
        $quiz_id = sanitize_text_field($request->get_param('id') ?? 'unknown');
        $score = intval($request->get_param('score') ?? 0);
        $answers = $request->get_param('answers') ?? [];
        $completed_at = intval($request->get_param('completedAt') ?? time() * 1000);
        $duration = intval($request->get_param('duration') ?? 0);
        
        // Store in session for visitors
        if (!session_id()) {
            session_start();
        }
        
        $_SESSION['flosc_quiz_result'] = [
            'quiz_id' => $quiz_id,
            'score' => $score,
            'answers' => $answers,
            'completed_at' => $completed_at,
            'duration' => $duration,
        ];
        
        // If user is logged in, store in user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            update_user_meta($user_id, 'flosc_last_quiz_id', $quiz_id);
            update_user_meta($user_id, 'flosc_last_quiz_score', $score);
            update_user_meta($user_id, 'flosc_last_quiz_completed', $completed_at);
            update_user_meta($user_id, 'flosc_quiz_answers_' . $quiz_id, $answers);
            
            // Add to completed quizzes array
            $completed = get_user_meta($user_id, 'flosc_completed_quizzes', true) ?: [];
            if (!in_array($quiz_id, $completed)) {
                $completed[] = $quiz_id;
                update_user_meta($user_id, 'flosc_completed_quizzes', $completed);
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Quiz result stored',
            'stored_for_user' => is_user_logged_in(),
        ]);
    }
    
    /**
     * Find matching IVR response based on phase and context
     */
    private function find_ivr_response($phase, $user_message, $context, $ivr_config) {
        // Look for suggested reply matches first
        $phase_messages = $ivr_config[$phase] ?? [];
        
        foreach ($phase_messages as $msg) {
            // Check if this is an AutoPrompt that matches
            if (isset($msg['type']) && $msg['type'] === 'suggested_user_autoprompt') {
                $msg_name = $msg['name'] ?? '';
                
                // If message has conditions, evaluate them
                if (isset($msg['conditions'])) {
                    $evaluator = new FLOSC_Condition_Evaluator($context);
                    if (!$evaluator->evaluate($msg['conditions'])) {
                        continue;
                    }
                }
                
                // Check if user clicked this suggested reply
                if (isset($msg['user_input']) && strtolower($msg['user_input']) === strtolower($user_message)) {
                    // Find the linked message response
                    $next_message = $this->find_message_by_name($msg['linked_message'] ?? '', $phase, $ivr_config);
                    if ($next_message) {
                        return $next_message;
                    }
                }
            }
        }
        
        // If no match, return a default response for the phase
        return [
            'content' => $this->get_phase_default_response($phase, $context),
            'suggested_replies' => $this->get_suggested_replies_for_phase($phase, $context, $ivr_config),
            'phase_change' => null,
        ];
    }
    
    /**
     * Find a message by its name in the IVR config
     */
    private function find_message_by_name($message_name, $phase, $ivr_config) {
        if (empty($message_name)) {
            return null;
        }
        
        $phase_messages = $ivr_config[$phase] ?? [];
        
        foreach ($phase_messages as $msg) {
            if (($msg['name'] ?? '') === $message_name) {
                return [
                    'content' => $msg['content'] ?? '',
                    'suggested_replies' => $msg['suggested_replies'] ?? [],
                    'phase_change' => $msg['phase_change'] ?? null,
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get default response for a phase
     */
    private function get_phase_default_response($phase, $context) {
        $responses = [
            'freeline' => 'Thanks for your interest! How can I help you today?',
            'login' => 'Please log in to continue.',
            'offer' => 'Would you like to learn more about our offer?',
            'sale' => 'Ready to take the next step?',
            'content' => 'Enjoy your course!',
        ];
        
        return $responses[$phase] ?? 'How can I help you?';
    }
    
    /**
     * Get suggested replies for a phase
     */
    private function get_suggested_replies_for_phase($phase, $context, $ivr_config) {
        $phase_messages = $ivr_config[$phase] ?? [];
        $replies = [];
        
        foreach ($phase_messages as $msg) {
            if (isset($msg['type']) && $msg['type'] === 'suggested_user_autoprompt') {
                // Check conditions if present
                if (isset($msg['conditions'])) {
                    $evaluator = new FLOSC_Condition_Evaluator($context);
                    if (!$evaluator->evaluate($msg['conditions'])) {
                        continue;
                    }
                }
                
                $replies[] = [
                    'text' => $msg['user_input'] ?? '',
                    'icon' => $msg['icon'] ?? '💬',
                ];
            }
        }
        
        return $replies;
    }

    public function handle_ai_query($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $context = $request->get_param('context') ?? [];
        
        if (empty($message)) {
            return new WP_Error('empty_message', 'Message is required', ['status' => 400]);
        }
        
        // Check usage limits if user is logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $usage = $this->sale_manager->usage();

            // Check if user has quota (or paid access)
            if (!$usage->has_quota($user_id, 'ai_queries')) {
                // Check if they can pay with tokens
                $token_provider = $this->sale_manager->get_provider('tokens');
                if (!$token_provider->can_afford($user_id, 'ai_query')) {
                    return new WP_Error('limit_reached', 'AI query limit reached. Upgrade for more!', ['status' => 403]);
                }
                // Charge tokens
                $token_provider->charge_for_action($user_id, 'ai_query');
            }

            // Track usage
            $usage->track($user_id, 'ai_queries');
        }

        // v04_04: Build context for phase-aware AI
        $ai_context = $this->build_ai_context($context);

        // v04_04: Build system prompt (base + phase-specific + context)
        $phase = $ai_context['phase'] ?? '';
        $system_prompt = $this->ai_factory->build_system_prompt($phase, $ai_context);

        $response = $this->ai_factory->get_response($message, $system_prompt, $context);
        
        return new WP_REST_Response([
            'success' => true,
            'response' => $response,
        ]);
    }
    
    public function handle_process_audio($request) {
        $files = $request->get_file_params();

        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }

        // Track STT usage
        if (is_user_logged_in()) {
            $this->sale_manager->usage()->track(get_current_user_id(), 'stt_minutes', 1);
        }

        $transcript = $this->stt_factory->transcribe($files['audio']['tmp_name']);

        if (is_wp_error($transcript)) {
            return $transcript;
        }

        // Get active quiz type
        $quiz_type = FLOSC_Quiz_Type_Factory::get_active_quiz_type();

        if (!$quiz_type) {
            return new WP_REST_Response([
                'success' => true,
                'transcript' => $transcript,
                'analysis' => null,
            ]);
        }

        // Get expected content for this quiz type
        $expected_content = get_option('flosc_quiz_content_' . $quiz_type->get_id(), $quiz_type->get_default_content());

        // Analyze using quiz type
        $analysis = $quiz_type->analyze($transcript, $expected_content, [
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
        ]);

        // Track quiz completion
        if (is_user_logged_in() && !is_wp_error($analysis)) {
            $this->sale_manager->usage()->track(get_current_user_id(), 'quizzes', 1, [
                'score' => $analysis['score'],
                'quiz_type' => $quiz_type->get_id(),
            ]);
        }

        // Map to lessons
        $lessons = $quiz_type->map_to_lessons($analysis);

        // Get response templates
        $templates = [];
        foreach ($quiz_type->get_default_response_templates() as $key => $default) {
            $templates[$key] = get_option(
                'flosc_quiz_' . $quiz_type->get_id() . '_template_' . $key,
                $default
            );
        }

        // Format results
        $message = $quiz_type->format_results($analysis, $lessons, $templates);

        return new WP_REST_Response([
            'success' => true,
            'transcript' => $transcript,
            'analysis' => $analysis,
            'lessons' => $lessons,
            'message' => $message,
        ]);
    }
    
    /**
     * Handle quiz processing
     * 
     * STATUS: ✅ FULLY FUNCTIONAL (v9.1.9)
     * - Accepts quiz input (e.g., "4,7,9")
     * - Scores against expected answer (e.g., "1,2,3,4,5,6,7,8,9,10")
     * - Calculates score percentage
     * - Fires flosc_quiz_completed action ✅
     * - Triggers Free Lesson Manager ✅
     * - Sets justCompletedQuiz transient for IVR ✅
     * 
     * FLOW:
     * 1. User submits quiz → this endpoint
     * 2. Quiz scored → 30% (3 of 10 correct)
     * 3. do_action('flosc_quiz_completed') fires
     * 4. Free Lesson Manager calculates missed (1,2,3,5,6,8,10)
     * 5. Picks ONE random lesson (#8)
     * 6. Stores in user meta
     * 7. IVR/AI can deliver free lesson
     */
    public function handle_process_quiz($request) {
        $input = sanitize_textarea_field($request->get_param('input'));
        $quiz_type_id = sanitize_text_field($request->get_param('quiz_type'));

        if (empty($input)) {
            return new WP_Error('no_input', 'No input provided', ['status' => 400]);
        }

        // Get quiz type (use provided or active)
        $quiz_type = $quiz_type_id
            ? FLOSC_Quiz_Type_Factory::get_quiz_type($quiz_type_id)
            : FLOSC_Quiz_Type_Factory::get_active_quiz_type();

        if (!$quiz_type) {
            return new WP_Error('invalid_quiz_type', 'Quiz type not found', ['status' => 404]);
        }

        // Validate input
        $validation = $quiz_type->validate_input($input);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Get expected content
        $expected_content = get_option('flosc_quiz_content_' . $quiz_type->get_id(), $quiz_type->get_default_content());

        // Analyze
        $analysis = $quiz_type->analyze($input, $expected_content, [
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
        ]);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        // Track quiz completion
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->sale_manager->usage()->track($user_id, 'quizzes', 1, [
                'score' => $analysis['score'],
                'quiz_type' => $quiz_type->get_id(),
            ]);

            // v07.09: Set justCompletedQuiz flag for IVR
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);
            
            // v9.1.9: Trigger free lesson selection if score < 100%
            if ($analysis['score'] < 100) {
                $quiz_result = [
                    'score' => $analysis['score'],
                    'user_answer' => $input,
                    'correct_answer' => $expected_content,
                    'correct' => $analysis['correct'] ?? [],
                    'incorrect' => $analysis['incorrect'] ?? [],
                    'missed' => $analysis['incorrect'] ?? []
                ];
                
                // Fire hook for free lesson manager
                do_action('flosc_quiz_completed', $quiz_result, $user_id);
                
                error_log("FLOSC: Quiz completed for user {$user_id} with score {$analysis['score']}%");
            }
        }

        // Map to lessons
        $lessons = $quiz_type->map_to_lessons($analysis);

        // Get response templates
        $templates = [];
        foreach ($quiz_type->get_default_response_templates() as $key => $default) {
            $templates[$key] = get_option(
                'flosc_quiz_' . $quiz_type->get_id() . '_template_' . $key,
                $default
            );
        }

        // Format results
        $message = $quiz_type->format_results($analysis, $lessons, $templates);

        return new WP_REST_Response([
            'success' => true,
            'analysis' => $analysis,
            'lessons' => $lessons,
            'message' => $message,
        ]);
    }

    public function get_sessions($request) {
        $sessions = $this->session_manager->get_flosc_user_sessions(get_current_user_id());
        return new WP_REST_Response(['sessions' => $sessions]);
    }
    
    public function create_session($request) {
        $title = sanitize_text_field($request->get_param('title') ?? 'New Chat');
        $session = $this->session_manager->flosc_create_session(get_current_user_id(), $title);
        return new WP_REST_Response(['session' => $session]);
    }
    
    public function get_offers($request) {
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $offers = $this->sale_manager->get_available_offers($user_id);
        return new WP_REST_Response(['offers' => array_values($offers)]);
    }
    
    /**
     * Handle purchase
     * 
     * STATUS: ✅ FULLY FUNCTIONAL (v9.1.9)
     * - Processes purchase via payment provider
     * - Fires flosc_purchase_completed action ✅
     * - Grants member access automatically ✅
     * - Sets _flosc_member_access = 'true' ✅
     * - User can now access ALL 10 posts ✅
     * 
     * TESTING: Use 'tokens' provider for sandbox testing
     */
    public function handle_purchase($request) {
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $provider_id = sanitize_text_field($request->get_param('provider'));
        $payment_data = $request->get_param('payment_data') ?? [];
        
        $user_id = get_current_user_id();
        
        $result = $this->sale_manager->process_purchase(
            $user_id,
            $offer_id,
            $provider_id,
            $payment_data
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // v9.1.9: Grant member access on successful purchase
        if ($result['success'] ?? false) {
            do_action('flosc_purchase_completed', $user_id, [
                'offer_id' => $offer_id,
                'provider' => $provider_id,
                'transaction_id' => $result['transaction_id'] ?? null,
                'timestamp' => time()
            ]);
            
            error_log("FLOSC: Purchase completed for user {$user_id}, offer {$offer_id}");
        }
        
        return new WP_REST_Response($result);
    }
    
    /**
     * Get free lesson for logged-in user (v9.1.9)
     */
    public function get_free_lesson($request) {
        $user_id = get_current_user_id();
        
        $free_lesson_mgr = FLOSC_Free_Lesson_Manager::instance();
        $lesson_data = $free_lesson_mgr->get_free_lesson($user_id);
        
        if (!$lesson_data) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No free lesson available. Please take the quiz first.'
            ], 404);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'lesson' => $lesson_data
        ]);
    }
    
    public function create_payment_intent($request) {
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $offer = $this->sale_manager->offers()->get_offer($offer_id);
        
        if (!$offer) {
            return new WP_Error('invalid_offer', 'Offer not found', ['status' => 404]);
        }
        
        $stripe = $this->sale_manager->get_provider('stripe');
        if (!$stripe || !$stripe->is_configured()) {
            return new WP_Error('stripe_not_configured', 'Stripe is not configured', ['status' => 500]);
        }
        
        $price_id = $offer['pricing']['stripe']['price_id'] ?? '';
        if (!$price_id) {
            return new WP_Error('no_price', 'No Stripe price configured for this offer', ['status' => 400]);
        }
        
        $user = wp_get_current_user();
        $result = $stripe->create_payment_intent($user, $price_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result);
    }
    
    public function handle_webhook($request) {
        $provider_id = $request->get_param('provider');
        $provider = $this->sale_manager->get_provider($provider_id);

        if (!$provider) {
            return new WP_Error('invalid_provider', 'Unknown provider', ['status' => 400]);
        }

        // Get Stripe signature header (single string, not array)
        $headers = [];
        if ($provider_id === 'stripe') {
            $stripe_sig = $request->get_header('stripe-signature');
            if ($stripe_sig) {
                $headers['stripe-signature'] = is_array($stripe_sig) ? $stripe_sig[0] : $stripe_sig;
            }
        }

        $result = $provider->handle_webhook(
            $request->get_body(),
            $headers
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result);
    }
    
    public function check_access($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['state' => 'visitor', 'level' => 'visitor']);
        }
        
        $user_id = get_current_user_id();
        $access = $this->sale_manager->access();
        
        return new WP_REST_Response([
            'state' => $access->get_simple_state($user_id),
            'level' => $access->get_level($user_id),
            'access' => $access->get_user_access($user_id),
        ]);
    }
    
    public function get_token_balance($request) {
        $token_provider = $this->sale_manager->get_provider('tokens');
        $user_id = get_current_user_id();
        
        return new WP_REST_Response([
            'balance' => $token_provider->get_balance($user_id),
            'ledger' => $token_provider->get_ledger($user_id, 10),
        ]);
    }
    
    public function declare_intent($request) {
        $affiliate = $this->sale_manager->get_provider('affiliate');
        
        if (!$affiliate || !$affiliate->is_configured()) {
            return new WP_Error('not_configured', 'Affiliate system not configured', ['status' => 500]);
        }
        
        $intent = $affiliate->declare_intent(get_current_user_id(), [
            'description' => sanitize_text_field($request->get_param('description')),
            'category' => sanitize_text_field($request->get_param('category') ?? 'general'),
            'expected_price' => floatval($request->get_param('expected_price') ?? 0),
            'timeframe' => sanitize_text_field($request->get_param('timeframe') ?? 'exploring'),
            'notes' => sanitize_textarea_field($request->get_param('notes') ?? ''),
        ]);
        
        return new WP_REST_Response(['intent' => $intent]);
    }
    
    public function get_intent_offers($request) {
        $intent_id = $request->get_param('id');
        $affiliate = $this->sale_manager->get_provider('affiliate');
        
        $intents = $affiliate->get_intents(get_current_user_id());
        
        if (!isset($intents[$intent_id])) {
            return new WP_Error('not_found', 'Intent not found', ['status' => 404]);
        }
        
        $offers = $affiliate->find_offers_for_intent($intents[$intent_id]);
        
        return new WP_REST_Response(['offers' => $offers]);
    }
    
    public function generate_referral($request) {
        $user_id = get_current_user_id();
        $code = 'REF' . $user_id;
        $app_url = home_url('/' . get_option('flosc_app_slug', 'app') . '/');
        
        return new WP_REST_Response([
            'link' => add_query_arg('ref', $code, $app_url),
            'code' => $code,
        ]);
    }
    
    /**
     * Get IVR messages for current phase and context (v9.2.6: Performance optimization)
     */
    public function get_ivr_messages($request) {
        $phase = sanitize_text_field($request->get_param('phase') ?: 'freeline');
        
        // Get user context
        $user_context = $this->user_access_manager->get_user_context();
        
        // v9.2.7: Add session-based defaults (frontend handles actual session logic)
        // Backend is permissive - returns messages that COULD show
        // Frontend decides based on actual session state
        $user_context['first_show_session'] = true; // Let welcome messages through
        $user_context['first_message_after_quiz'] = $request->get_param('after_quiz') === 'true';
        $user_context['first_message_after_login'] = $request->get_param('after_login') === 'true';
        $user_context['first_message_after_purchase'] = $request->get_param('after_purchase') === 'true';
        
        // Get all messages from database
        $all_messages = get_option('flosc_ivr_messages', []);
        $phases = get_option('flosc_ivr_phases', []);
        
        // Get message IDs for this phase
        $phase_message_ids = $phases[$phase] ?? [];
        
        // Initialize condition evaluator once (v9.2.6: singleton pattern)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
        $evaluator = new FLOSC_Condition_Evaluator($user_context);
        
        // Filter and evaluate conditions
        $filtered_messages = [];
        foreach ($phase_message_ids as $msg_id) {
            if (!isset($all_messages[$msg_id])) continue;
            
            $message = $all_messages[$msg_id];
            
            // Evaluate conditions if present
            if (!empty($message['conditions'])) {
                if (!$evaluator->evaluate($message['conditions'])) {
                    continue; // Skip this message
                }
            }
            
            $filtered_messages[] = $message;
        }
        
        return new WP_REST_Response([
            'success' => true,
            'phase' => $phase,
            'messages' => $filtered_messages,
            'user_context' => [
                'access_level' => $user_context['access_level'],
                'is_logged_in' => is_user_logged_in(),
            ],
        ]);
    }
    
    /**
     * Get all lessons (metadata only)
     */
    public function get_lessons($request) {
        $lessons = $this->lesson_manager->get_all_lessons();
        
        return new WP_REST_Response([
            'lessons' => $lessons,
        ]);
    }
    
    /**
     * Get single lesson with content
     */
    public function get_lesson($request) {
        $lesson_id = intval($request->get_param('id'));
        $user_id = get_current_user_id();
        
        // Check if this is the user's free lesson
        $free_lesson_id = get_user_meta($user_id, '_flosc_free_lesson_id', true);
        $is_free_lesson = ($free_lesson_id == $lesson_id);
        
        // Check access
        if (!$this->lesson_manager->user_can_access($user_id, $lesson_id, $is_free_lesson)) {
            return new WP_Error('no_access', 'Upgrade to access this lesson', ['status' => 403]);
        }
        
        $lesson = $this->lesson_manager->get_lesson($lesson_id);
        
        if (!$lesson) {
            return new WP_Error('not_found', 'Lesson not found', ['status' => 404]);
        }
        
        // Mark free lesson as delivered
        if ($is_free_lesson && !get_user_meta($user_id, '_flosc_free_lesson_delivered', true)) {
            update_user_meta($user_id, '_flosc_free_lesson_delivered', current_time('mysql'));
        }
        
        return new WP_REST_Response([
            'lesson' => $lesson,
        ]);
    }
    
    /**
     * Store pre-login quiz score (for visitors)
     * v9.4.2: Uses signed cookies to prevent score forgery
     */
    public function store_prelogin_score($request) {
        $score_data = [
            'score' => intval($request->get_param('score')),
            'correct' => $request->get_param('correct') ?? [],
            'incorrect' => $request->get_param('incorrect') ?? [],
            'quiz_type' => sanitize_text_field($request->get_param('quiz_type') ?? ''),
            'timestamp' => time(),
        ];
        
        // v9.4.2: Store in SIGNED cookie to prevent forgery
        // JS will also store in localStorage as backup (but server only trusts signed cookie)
        $this->set_signed_cookie('flosc_prelogin_score', $score_data, HOUR_IN_SECONDS);
        
        return new WP_REST_Response([
            'stored' => true,
        ]);
    }

    /**
     * Mark funnel as completed for user (v3.0.4)
     * Called after user completes the FLOSC flow (quiz → login → free lesson → upgrade prompt)
     */
    public function mark_funnel_complete($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_Error('not_logged_in', 'User must be logged in', ['status' => 401]);
        }

        // Mark funnel completed
        update_user_meta($user_id, '_flosc_funnel_completed', true);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Funnel marked as completed',
        ]);
    }

    /**
     * Test AI connection (v04_09)
     * Sends a test message to verify AI provider is configured and responding
     * Returns smart error messages with next steps if connection fails
     */
    public function handle_test_ai($request) {
        $start_time = microtime(true);
        $test_message = "Hello, this is a connection test. Please respond with 'Connection successful'.";

        $provider = get_option('flosc_ai_provider', 'ivr');

        try {
            // Build AI context for freeline phase (simplest phase)
            $ai_context = ['phase' => 'freeline'];
            $system_prompt = $this->ai_factory->build_system_prompt('freeline', $ai_context);

            // Get AI response with test_mode = true (no IVR fallback)
            $response = $this->ai_factory->get_response($test_message, $system_prompt, [], true);

            // Check if response is WP_Error (connection failed)
            if (is_wp_error($response)) {
                return new WP_REST_Response([
                    'success' => false,
                    'provider' => $provider,
                    'error_code' => $response->get_error_code(),
                    'message' => $response->get_error_message()
                ], 200);
            }

            // Calculate response time
            $response_time = round((microtime(true) - $start_time) * 1000);

            return new WP_REST_Response([
                'success' => true,
                'provider' => $provider,
                'response_time' => $response_time,
                'test_message' => $test_message,
                'ai_response' => $response
            ]);
        } catch (Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle IVR message tracking (v07.09)
     * Track which messages have been shown to users
     */
    public function handle_ivr_track($request) {
        $message_name = sanitize_text_field($request->get_param('message_name'));
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $offer_state = sanitize_text_field($request->get_param('offer_state')); // shown, dismissed, purchased

        if (empty($message_name) && empty($offer_id)) {
            return new WP_Error('missing_params', 'message_name or offer_id required', ['status' => 400]);
        }

        if (!is_user_logged_in()) {
            // For visitors, track in transient by IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $key = 'flosc_visitor_' . md5($ip);
            $data = get_transient($key) ?: [];

            if ($message_name) {
                $data['messages'][$message_name] = time();
            }
            if ($offer_id && $offer_state) {
                $data['offers'][$offer_id][$offer_state] = time();
            }

            set_transient($key, $data, DAY_IN_SECONDS);

            return new WP_REST_Response(['success' => true, 'tracked' => 'visitor']);
        }

        // For logged-in users, track in user meta
        $user_id = get_current_user_id();

        if ($message_name) {
            $key = '_flosc_msg_shown_' . sanitize_key($message_name);
            update_user_meta($user_id, $key, current_time('mysql'));
        }

        if ($offer_id && $offer_state) {
            $key = "_flosc_offer_{$offer_state}_{$offer_id}";
            update_user_meta($user_id, $key, current_time('mysql'));
        }

        return new WP_REST_Response(['success' => true, 'tracked' => 'user']);
    }

    /**
     * Get applicable IVR messages for current user/context (v07.09)
     */
    public function handle_ivr_get_messages($request) {
        $phase = sanitize_text_field($request->get_param('phase'));
        $type = sanitize_text_field($request->get_param('type')); // auto, suggested_user_autoprompt, offer

        // Build context
        require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
        $context = FLOSC_Condition_Evaluator::build_context(
            is_user_logged_in() ? get_current_user_id() : null,
            [
                'message_count' => intval($request->get_param('message_count') ?? 0),
                'inactive_seconds' => intval($request->get_param('inactive_seconds') ?? 0),
                'session_seconds' => intval($request->get_param('session_seconds') ?? 0),
            ]
        );

        // Get parser
        $parser = FLOSC_IVR_Parser::flosc_instance();
        $messages = $phase ? $parser->get_flosc_phase_messages($phase) : array_values($parser->get_flosc_config()['messages']);

        // Filter by type if specified
        if ($type) {
            $messages = array_filter($messages, function($m) use ($type) {
                return $m['type'] === $type;
            });
        }

        // Evaluate conditions
        $evaluator = new FLOSC_Condition_Evaluator($context);
        $applicable = $evaluator->get_applicable_messages($messages, $type);

        return new WP_REST_Response([
            'success' => true,
            'messages' => array_values($applicable),
            'context' => $context,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FUTURE ENHANCEMENTS (v07.07)
    |--------------------------------------------------------------------------
    | The following features are planned but not yet implemented.
    | Pseudocode and best practices documented here for future development.
    |--------------------------------------------------------------------------
    
    6.1 LOGGER
    ----------
    Purpose: Structured logging for debugging, security events, payment transactions.
    
    Implementation approach:
    - Create class-logger.php in includes/
    - Use singleton pattern: FLOSC_Logger::instance()
    - Log levels: DEBUG, INFO, WARNING, ERROR, CRITICAL
    - Methods: debug(), info(), warning(), error(), critical(), security()
    - Store logs in wp-content/flosc-logs/ or use WP error_log
    - Include correlation IDs for request tracking
    - Sanitize sensitive data (passwords, API keys, card numbers)
    
    Pseudocode:
    ```php
    class FLOSC_Logger {
        public static function info($message, $context = []) {
            if (!self::should_log('INFO')) return;
            $entry = self::format_entry('INFO', $message, $context);
            self::write($entry);
        }
        
        private static function format_entry($level, $message, $context) {
            return [
                'timestamp' => current_time('mysql'),
                'level' => $level,
                'correlation_id' => self::get_correlation_id(),
                'message' => $message,
                'context' => self::sanitize_context($context),
            ];
        }
    }
    ```
    
    6.2 VALIDATOR
    -------------
    Purpose: Centralized input validation and sanitization for security.
    
    Implementation approach:
    - Create class-validator.php in includes/
    - Static methods for common validations
    - Return sanitized value or WP_Error
    - Use for all user inputs, API payloads, webhook data
    
    Pseudocode:
    ```php
    class FLOSC_Validator {
        public static function email($input, $required = true) {
            $input = trim($input);
            if (empty($input) && !$required) return '';
            if (!is_email($input)) return new WP_Error('invalid_email', 'Invalid email');
            return sanitize_email($input);
        }
        
        public static function score($input, $min = 0, $max = 100) {
            $score = intval($input);
            return max($min, min($max, $score));
        }
    }
    ```
    
    6.3 ENHANCED RATE LIMITING
    --------------------------
    Purpose: Better rate limiting using cookies for visitor tracking.
    
    Implementation approach:
    - Track by user ID for logged-in users
    - Track by IP + visitor cookie for guests
    - Set visitor cookie in wp_loaded, NOT in permission callbacks
    - Use transients for rate limit counting
    
    Pseudocode:
    ```php
    // In constructor or init:
    add_action('wp_loaded', [$this, 'set_visitor_cookie']);
    
    public function set_visitor_cookie() {
        if (is_user_logged_in() || isset($_COOKIE['flosc_visitor_id'])) return;
        $visitor_id = wp_generate_uuid4();
        setcookie('flosc_visitor_id', $visitor_id, [
            'expires' => time() + DAY_IN_SECONDS,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    
    private function get_rate_limit_key($endpoint) {
        if (is_user_logged_in()) {
            return 'flosc_rate_u' . get_current_user_id() . '_' . md5($endpoint);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $vid = $_COOKIE['flosc_visitor_id'] ?? '';
        return 'flosc_rate_v' . md5($endpoint . $ip . $vid);
    }
    ```
    
    6.4 CSRF/NONCE ENFORCEMENT
    --------------------------
    Purpose: Protect REST API endpoints from cross-site request forgery.
    
    Implementation approach:
    - Verify nonce on POST/PUT/DELETE for authenticated users
    - Skip nonce for webhooks (they use signature verification)
    - Skip nonce for public GET endpoints
    - Return 403 with helpful error message if invalid
    
    Pseudocode:
    ```php
    public function check_authenticated_with_nonce($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', 'Authentication required', ['status' => 401]);
        }
        
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('invalid_nonce', 'Security token invalid. Please refresh.', ['status' => 403]);
        }
        
        return true;
    }
    ```
    
    |--------------------------------------------------------------------------
    | END FUTURE ENHANCEMENTS
    |--------------------------------------------------------------------------
    */
}

// Initialize
function flosc() {
    return FLOSC_Framework::instance();
}

/**
 * Helper: Adjust hex color brightness
 */
function flosc_adjust_brightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
}

/**
 * Import IVR messages from ivr.md into database
 * v9.2.2: IVR Database Integration
 */
/**
 * Import IVR from ivr.md to database (REPLACE MODE - ivr.md is source of truth)
 * 
 * @param bool $preview_only If true, returns preview without making changes
 * @return array Result with success, stats, message, and preview data
 */
function flosc_import_ivr_to_database($preview_only = false) {
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    
    if (!file_exists($ivr_file)) {
        return ['success' => false, 'message' => 'ivr.md file not found'];
    }
    
    require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
    $parser = FLOSC_IVR_Parser::flosc_instance();
    $markdown = file_get_contents($ivr_file);
    $config = $parser->flosc_parse($markdown);
    
    if (empty($config)) {
        return ['success' => false, 'message' => 'Failed to parse ivr.md'];
    }
    
    // Get current database state
    $current_messages = get_option('flosc_ivr_messages', []);
    $incoming_messages = $config['messages'] ?? [];
    
    // Calculate changes (ivr.md is source of truth - database will match it)
    $current_ids = array_keys($current_messages);
    $incoming_ids = array_keys($incoming_messages);
    
    $to_add = array_diff($incoming_ids, $current_ids);
    $to_update = array_intersect($incoming_ids, $current_ids);
    $to_delete = array_diff($current_ids, $incoming_ids); // Database-only messages will be DELETED
    
    $stats = [
        'added' => array_values($to_add),
        'updated' => array_values($to_update),
        'deleted' => array_values($to_delete),
        'current_count' => count($current_messages),
        'incoming_count' => count($incoming_messages),
        'has_deletions' => !empty($to_delete)
    ];
    
    // PREVIEW MODE: Return analysis without making changes
    if ($preview_only) {
        return ['success' => true, 'preview' => true, 'stats' => $stats];
    }
    
    // EXECUTE IMPORT: Auto-backup first, then replace database
    $backup_file = '';
    if (!empty($current_messages)) {
        $backup_file = flosc_export_ivr_backup();
    }
    
    // REPLACE database with ivr.md contents (source of truth)
    update_option('flosc_ivr_messages', $incoming_messages);
    update_option('flosc_ivr_phases', $config['phases'] ?? []);
    update_option('flosc_ivr_styles', $config['styles'] ?? []);
    update_option('flosc_ivr_last_import', current_time('mysql'));
    
    // Generate success message
    $message = sprintf(
        'Database replaced with ivr.md contents. Added: %d, Updated: %d, Deleted: %d',
        count($stats['added']),
        count($stats['updated']),
        count($stats['deleted'])
    );
    
    if ($backup_file) {
        $message .= sprintf('. Backup saved: %s', basename($backup_file));
    }
    
    return ['success' => true, 'stats' => $stats, 'message' => $message, 'backup_file' => $backup_file];
}

/**
 * Create timestamped backup of current IVR database state
 * 
 * @return string|false Backup filename on success, false on failure
 */
function flosc_export_ivr_backup() {
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    $styles = get_option('flosc_ivr_styles', []);
    
    if (empty($messages)) {
        return false; // No data to backup
    }
    
    // Generate markdown (same format as export)
    $markdown = "# FLOSC IVR Configuration (AUTO-BACKUP)\n\n";
    $markdown .= "Backup created: " . current_time('mysql') . "\n\n";
    
    // Add styles
    foreach ($styles as $style_name => $style_css) {
        $markdown .= "## MessageStyle: $style_name\n";
        $markdown .= $style_css . "\n\n";
    }
    
    $markdown .= "## Available Variables\n";
    $markdown .= "{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}\n\n";
    
    $markdown .= "---\n\n";
    
    // Add messages by phase
    foreach ($phases as $phase_name => $message_ids) {
        $markdown .= "# " . ucfirst($phase_name) . " Messages\n\n";
        
        foreach ($message_ids as $msg_id) {
            if (!isset($messages[$msg_id])) continue;
            $msg = $messages[$msg_id];
            
            $markdown .= "## " . ($msg['name'] ?? $msg_id) . "\n";
            $markdown .= "MessageName: " . $msg_id . "\n";
            $markdown .= "MessageType: " . ($msg['type'] ?? 'auto') . "\n";
            
            if (!empty($msg['style'])) {
                $markdown .= "MessageStyle: " . $msg['style'] . "\n";
            }
            if (!empty($msg['icon'])) {
                $markdown .= "Icon: " . $msg['icon'] . "\n";
            }
            if (!empty($msg['user_input'])) {
                $markdown .= "UserInput: " . $msg['user_input'] . "\n";
            }
            
            $markdown .= "MessageContent: " . $msg['content'] . "\n";
            
            if (!empty($msg['conditions'])) {
                $markdown .= "MessageConditions: " . $msg['conditions'] . "\n";
            }
            if (!empty($msg['action'])) {
                $markdown .= "MessageAction: " . $msg['action'] . "\n";
            }
            
            $markdown .= "\n";
        }
    }
    
    // Save to timestamped backup file
    $timestamp = current_time('Y-m-d_H-i-s');
    $backup_file = FLOSC_PLUGIN_DIR . "ai_configuration_files/ivr-backup-{$timestamp}.md";
    
    if (file_put_contents($backup_file, $markdown)) {
        return basename($backup_file);
    }
    
    return false;
}

/**
 * Auto-export IVR database to ivr.md file (write-through)
 * v9.2.8: Called after every save/delete to keep DB and file in sync
 * 
 * @return bool Success
 */
function flosc_auto_export_ivr_to_file() {
    $messages = get_option('flosc_ivr_messages', []);
    $phases = get_option('flosc_ivr_phases', []);
    $styles = get_option('flosc_ivr_styles', []);
    
    if (empty($messages)) {
        return false;
    }
    
    // Build markdown in proper ivr.md format
    $markdown = "# FLOSC IVR Configuration\n\n";
    
    // Add styles
    foreach ($styles as $style_name => $style_data) {
        $markdown .= "## MessageStyle: $style_name\n";
        if (is_array($style_data)) {
            if (!empty($style_data['description'])) {
                $markdown .= "Description: " . $style_data['description'] . "\n";
            }
            if (!empty($style_data['css'])) {
                $markdown .= $style_data['css'] . "\n";
            }
        } else {
            $markdown .= $style_data . "\n";
        }
        $markdown .= "\n";
    }
    
    $markdown .= "## Available Variables\n";
    $markdown .= "{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}\n\n";
    
    $markdown .= "## Available Conditions\n";
    $markdown .= "- Scores: score > X, score < X, score >= X, score <= X, score == X, initial_score > X\n";
    $markdown .= "- Boolean: quiz_taken, !quiz_taken, logged_in, !logged_in, purchased, !purchased, lesson_viewed, returning_user, onboarded, has_incomplete_lesson, has_profile, !has_profile\n";
    $markdown .= "- Access: is_visitor, is_guest, is_member\n";
    $markdown .= "- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_lesson, first_show_session\n";
    $markdown .= "- Logic: &&, ||, !, ()\n\n";
    
    $markdown .= "---\n\n";
    
    // Phase descriptions
    $phase_descriptions = [
        'freeline' => 'Visitor (not logged in) → Take quiz → MUST login to see score.',
        'login' => 'Guest (logged in, not purchased) → See score → 1 free lesson → Offers → No quiz retake.',
        'offer' => 'Guests (completed quiz, not purchased) → See quiz results → Free preview lesson → Upgrade offer',
        'sale' => 'Member (purchased) → Full access → Retake quiz with timestamps → All lessons/quizzes.',
        'content' => 'Ongoing users - Support, encouragement, engagement',
    ];
    
    // Add messages by phase
    foreach ($phases as $phase_name => $message_ids) {
        if (empty($message_ids)) continue;
        
        $markdown .= "# " . ucfirst($phase_name) . " Messages\n";
        if (isset($phase_descriptions[$phase_name])) {
            $markdown .= $phase_descriptions[$phase_name] . "\n";
        }
        $markdown .= "\n";
        
        foreach ($message_ids as $msg_id) {
            if (!isset($messages[$msg_id])) continue;
            $msg = $messages[$msg_id];
            
            // Use title/display name if available
            $title = $msg['title'] ?? $msg['name'] ?? $msg_id;
            $markdown .= "## " . $title . "\n";
            $markdown .= "MessageName: " . $msg_id . "\n";
            $markdown .= "MessageType: " . ($msg['type'] ?? 'auto') . "\n";
            
            if (!empty($msg['style']) && $msg['style'] !== 'default') {
                $markdown .= "MessageStyle: " . $msg['style'] . "\n";
            }
            if (!empty($msg['icon'])) {
                $markdown .= "Icon: " . $msg['icon'] . "\n";
            }
            if (!empty($msg['user_input'])) {
                $markdown .= "UserInput: " . $msg['user_input'] . "\n";
            }
            if (!empty($msg['action'])) {
                $markdown .= "Action: " . $msg['action'] . "\n";
            }
            
            $markdown .= "MessageContent: " . ($msg['content'] ?? '') . "\n";
            
            if (!empty($msg['conditions']) && $msg['conditions'] !== 'always') {
                $markdown .= "MessageConditions: " . $msg['conditions'] . "\n";
            }
            
            $markdown .= "\n";
        }
        
        $markdown .= "---\n\n";
    }
    
    // Write to ivr.md
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    $result = file_put_contents($ivr_file, $markdown);
    
    if ($result !== false) {
        // Update last export timestamp
        update_option('flosc_ivr_last_export', current_time('mysql'));
        return true;
    }
    
    return false;
}

/**
 * Plugin activation (v3.0.9 - Resolved: moved outside class so hook fires correctly)
 */
function flosc_activate() {
    // Flush rewrite rules to register REST API routes
    flush_rewrite_rules();

    // Set defaults (only if they don't exist)
    $defaults = [
        'flosc_app_slug' => 'app',
        'flosc_product_name' => '',
        'flosc_product_tagline' => '',
        'flosc_product_emoji' => '🎯',
        'flosc_primary_color' => '#4f46e5',
        'flosc_ai_provider' => 'ivr',
        'flosc_stt_provider' => 'assemblyai',
        'flosc_quiz_type' => 'flosc_sample_audio_quiz',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Force critical "out of box" defaults
    $force_defaults = [
        'flosc_quiz_content_flosc_sample_audio_quiz' => '1,2,3,4,5,6,7,8,9,10',
        'flosc_token_name' => 'tokens',
    ];

    foreach ($force_defaults as $key => $value) {
        update_option($key, $value);
    }

    // v07.09: Ensure default ivr.md exists
    $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
    if (!file_exists($ivr_file)) {
        // Create directory if needed
        $dir = dirname($ivr_file);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        // Copy default from includes/defaults/ if it exists, otherwise create minimal version
        $default_ivr = FLOSC_PLUGIN_DIR . 'includes/defaults/ivr.md';
        if (file_exists($default_ivr)) {
            copy($default_ivr, $ivr_file);
        } else {
            // Create minimal working ivr.md
            $minimal_ivr = <<<'MD'
# FLOSC IVR Configuration

## MessageStyle: pill
Description: Superlight chat bubble style
.flosc-style-pill {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}
.flosc-style-pill:hover {
  background: rgba(255, 255, 255, 0.95);
  border-color: rgba(0, 0, 0, 0.12);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

---

# Freeline Messages

## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. Ready to get started?
MessageConditions: first_show_session && !logged_in

## Get Started
MessageName: get_started_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🚀
UserInput: Get started
MessageContent: Great! Let's begin with a quick quiz to see where you stand.
MessageConditions: !quiz_taken
MD;
            file_put_contents($ivr_file, $minimal_ivr);
        }
    }

    // v9.2.3: Import IVR messages to database on first activation
    flosc_import_ivr_to_database(false); // Execute import (not preview)

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register activation hook
register_activation_hook(__FILE__, 'flosc_activate');

// Start the plugin
add_action('plugins_loaded', 'flosc');
