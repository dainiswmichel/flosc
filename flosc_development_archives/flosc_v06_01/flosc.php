<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - Quiz-based learning and conversational sales funnel framework
 * Version: 6.0.2
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
// v06.02: Repository cleanup, AI system renamed to "Knowledge Base", consolidated documentation
define('FLOSC_VERSION', '6.0.2');
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
    private $ivr_manager;

    // SALE system (loaded separately)
    private $sale_manager;
    
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
        // v6.0.1: Core infrastructure
        require_once FLOSC_PLUGIN_DIR . 'includes/class-logger.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-validator.php';
        
        // Initialize logger
        FLOSC_Logger::init();
        
        // Core components
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-type-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-manager.php';

        // IVR system (v04_03)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-manager.php';

        // SALE system
        require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-sale-manager.php';

        $this->ai_factory = new FLOSC_AI_Provider_Factory();
        $this->stt_factory = new FLOSC_STT_Provider_Factory();
        $this->session_manager = new FLOSC_Session_Manager();
        $this->pronunciation_analyzer = new FLOSC_Pronunciation_Analyzer();
        $this->lesson_manager = FLOSC_Lesson_Manager::instance();
        $this->ivr_manager = FLOSC_IVR_Manager::get_instance();

        // Quiz types loaded dynamically by factory

        // Initialize SALE system
        $this->sale_manager = FLOSC_Sale_Manager::instance();
    }
    
    private function init_hooks() {
        // Virtual page routing
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_app_route']);

        // Admin - priority 5 to ensure Settings submenu is added first
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        add_action('admin_init', [$this, 'register_settings']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // User registration hook (for signup bonus)
        add_action('user_register', [$this, 'handle_user_registration']);

        // User login hook (for pre-login score processing)
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);
    }
    
    /**
     * Component accessors
     */
    public function ai() { return $this->ai_factory; }
    public function stt() { return $this->stt_factory; }
    public function quiz() { return 'FLOSC_Quiz_Type_Factory'; } // Returns class name (static factory)
    public function sessions() { return $this->session_manager; }
    public function analyzer() { return $this->pronunciation_analyzer; }
    public function ivr() { return $this->ivr_manager; }
    public function sale() { return $this->sale_manager; }
    public function lessons() { return $this->lesson_manager; }

    /**
     * Rate Limiting Helper
     * v6.0.1: Improved with user ID-based limiting for logged-in users
     */
    private function check_rate_limit($endpoint, $limit = 20, $window = 3600) {
        // For logged-in users, use user ID (more reliable than IP)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $key = 'flosc_rate_u' . $user_id . '_' . md5($endpoint);
        } else {
            // For visitors, use IP + a visitor cookie for better tracking
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $visitor_id = $_COOKIE['flosc_visitor_id'] ?? '';
            
            // Create visitor ID if not exists
            if (empty($visitor_id)) {
                $visitor_id = wp_generate_uuid4();
                setcookie('flosc_visitor_id', $visitor_id, [
                    'expires' => time() + DAY_IN_SECONDS,
                    'path' => '/',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            
            $key = 'flosc_rate_v' . md5($endpoint . $ip . $visitor_id);
        }
        
        $count = get_transient($key) ?: 0;

        if ($count >= $limit) {
            FLOSC_Logger::security('Rate limit exceeded', [
                'endpoint' => $endpoint,
                'limit' => $limit,
                'count' => $count,
            ]);
            return false;
        }

        set_transient($key, $count + 1, $window);
        return true;
    }

    /**
     * Verify nonce for REST API requests
     * v6.0.1: Added CSRF protection
     */
    private function verify_rest_nonce($request) {
        // Get nonce from header or parameter
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce');
        }
        
        // Verify nonce
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_rest')) {
            FLOSC_Logger::security('Invalid REST nonce', [
                'endpoint' => $request->get_route(),
                'method' => $request->get_method(),
            ]);
            return false;
        }
        
        return true;
    }

    /**
     * Permission Callbacks for REST API
     * v6.0.1: Added CSRF/nonce verification for authenticated requests
     */
    public function check_paid_endpoint_permission($request) {
        // Check rate limit first
        if (!$this->check_rate_limit('paid_endpoint', 20, 3600)) {
            return new WP_Error('rate_limit', 'Too many requests. Please try again later.', ['status' => 429]);
        }

        // For logged-in users: verify nonce (CSRF protection)
        if (is_user_logged_in()) {
            if (!$this->verify_rest_nonce($request)) {
                return new WP_Error('invalid_nonce', 'Invalid security token. Please refresh the page.', ['status' => 403]);
            }
            return true;
        }

        // For visitors: strict rate limit only (nonce not available pre-login)
        if (!$this->check_rate_limit('visitor_paid', 5, 3600)) {
            return new WP_Error('rate_limit', 'Free tier limit reached. Please log in.', ['status' => 429]);
        }

        return true;
    }
    
    /**
     * Permission check for authenticated-only endpoints
     * v6.0.1: With CSRF protection
     */
    public function check_authenticated_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('not_logged_in', 'You must be logged in.', ['status' => 401]);
        }
        
        if (!$this->verify_rest_nonce($request)) {
            return new WP_Error('invalid_nonce', 'Invalid security token. Please refresh the page.', ['status' => 403]);
        }
        
        return true;
    }
    
    /**
     * Permission check for webhook endpoints (no CSRF, signature verified separately)
     */
    public function check_webhook_permission($request) {
        // Webhooks don't use CSRF - they use signature verification
        // Rate limit to prevent abuse
        if (!$this->check_rate_limit('webhook', 100, 3600)) {
            return new WP_Error('rate_limit', 'Too many webhook requests.', ['status' => 429]);
        }
        return true;
    }

    /**
     * Plugin Activation
     */
    public function activate() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();

        // Set defaults
        $defaults = [
            'flosc_app_slug' => 'app',
            'flosc_product_name' => '',
            'flosc_product_tagline' => '',
            'flosc_product_emoji' => '🎯',
            'flosc_primary_color' => '#4f46e5',
            'flosc_ai_provider' => 'ivr',
            'flosc_stt_provider' => 'assemblyai',
            'flosc_quiz_type' => 'simple_scoring', // Default to simple scoring quiz
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        // Force critical "out of box" defaults (v3.0.8 - added FLOSC Default prefix)
        // These are set every activation to ensure plugin works without configuration
        $force_defaults = [
            'flosc_quiz_content_simple_scoring' => '1,2,3,4,5,6,7,8,9,10',
            'flosc_token_name' => 'tokens',
            'flosc_get_started_message' => 'FLOSC Default Get started text: Welcome! I\'m your FLOSC learning assistant. I\'m here to help you master new skills through interactive lessons and quizzes. Ready to get started?',
            'flosc_how_it_works_message' => 'FLOSC Default How does it work? text: Here\'s how it works: First, you\'ll take a quick quiz to assess your current level. Then, based on your results, I\'ll unlock a free lesson personalized to your needs. After that, you can upgrade for full access to all lessons and ongoing support.',
            'flosc_what_you_learn_message' => 'FLOSC Default What will I learn? text: You\'ll master practical skills through interactive lessons, get personalized feedback on your progress, and access a complete learning path designed to take you from beginner to advanced. Each lesson includes exercises, quizzes, and real-world applications.',
        ];

        foreach ($force_defaults as $key => $value) {
            update_option($key, $value);
        }

        // Create default content (only on first activation)
        if (!get_option('flosc_default_content_created')) {
            $this->create_default_content();
        }
    }

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
            'type' => 'simple_scoring',
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
                'access_level' => 'premium',
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
        // Check for pre-login score in cookie
        $score_data = $_COOKIE['flosc_prelogin_score'] ?? null;
        
        if ($score_data) {
            $score_data = json_decode(stripslashes($score_data), true);
            
            if ($score_data && isset($score_data['score'])) {
                // Store the score for this user
                update_user_meta($user->ID, '_flosc_last_quiz_score', $score_data['score']);
                update_user_meta($user->ID, '_flosc_last_quiz_data', $score_data);
                update_user_meta($user->ID, '_flosc_quiz_completed_at', current_time('mysql'));
                
                // Send email with score and OTO
                $this->send_score_email($user, $score_data);
                
                // v05_09: Fix cookie deletion - must use same options as when setting
                setcookie('flosc_prelogin_score', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }
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
            // v05_08: Added secure cookie options
            setcookie('flosc_referrer', sanitize_text_field($ref), [
                'expires' => time() + (30 * DAY_IN_SECONDS),
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        // Determine user state
        $user_state = 'visitor';
        $user_data = [];
        
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $user_state = $this->sale_manager->access()->get_simple_state($user->ID);
            
            $user_data = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 40]),
                'state' => $user_state,
                'access' => $this->sale_manager->access()->get_user_access($user->ID),
                'tokens' => $this->sale_manager->get_provider('tokens')->get_balance($user->ID),
                'freeLessonDelivered' => (bool) get_user_meta($user->ID, '_flosc_free_lesson_delivered', true),
                'lastQuizScore' => get_user_meta($user->ID, '_flosc_last_quiz_score', true),
                'funnelCompleted' => (bool) get_user_meta($user->ID, '_flosc_funnel_completed', true),
                // v05_07: Add missing flags for phase logic
                'quizScore' => get_user_meta($user->ID, '_flosc_last_quiz_score', true),
                'offerShown' => (bool) get_user_meta($user->ID, '_flosc_offer_shown', true),
                'purchased' => ($user_state === 'paid' || $this->sale_manager->access()->has_access($user->ID, 'content')),
                'onboarded' => (bool) get_user_meta($user->ID, '_flosc_onboarded', true),
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
        include FLOSC_PLUGIN_DIR . 'templates/flosc-app.php';
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
            $context['purchased'] = $this->sale_manager->access()->has_access($user->ID, 'full') ? 'Yes' : 'No';
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
        if ($this->sale_manager->access()->has_access($user_id, 'full')) {
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
        return $this->sale_manager->access()->has_access($user_id, 'full') ? 'paid' : 'free';
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
        // Order: Product → IVR Messages → AI Configuration → Quiz → Email → AI Knowledge → Offers → Payments → Lessons

        // 1. Product
        add_submenu_page(
            'flosc-settings',
            'Product Settings',
            'Product',
            'manage_options',
            'flosc-settings', // Same slug = first item replaces parent
            [$this, 'render_admin_page']
        );

        // 2. IVR Messages
        add_submenu_page(
            'flosc-settings',
            'IVR Messages',
            'IVR Messages',
            'manage_options',
            'flosc-ivr-messages',
            [$this, 'redirect_to_ivr_tab']
        );

        // 3. AI Configuration
        add_submenu_page(
            'flosc-settings',
            'AI Configuration',
            'AI Configuration',
            'manage_options',
            'flosc-ai-config',
            [$this, 'redirect_to_ai_tab']
        );

        // 4. Quiz
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

        // Messages (v3.0.4)
        register_setting('flosc_settings', 'flosc_welcome_message');
        register_setting('flosc_settings', 'flosc_get_started_message');
        register_setting('flosc_settings', 'flosc_how_it_works_message');
        register_setting('flosc_settings', 'flosc_what_you_learn_message');

        // Payments (v5.0.6)
        register_setting('flosc_settings', 'flosc_stripe_enabled');
        register_setting('flosc_settings', 'flosc_stripe_mode');
        register_setting('flosc_settings', 'flosc_stripe_test_pk');
        register_setting('flosc_settings', 'flosc_stripe_test_sk');
        register_setting('flosc_settings', 'flosc_stripe_live_pk');
        register_setting('flosc_settings', 'flosc_stripe_live_sk');

        // v05_09: ClickBank settings
        register_setting('flosc_settings', 'flosc_clickbank_enabled');
        register_setting('flosc_settings', 'flosc_clickbank_mode');
        register_setting('flosc_settings', 'flosc_clickbank_vendor');
        register_setting('flosc_settings', 'flosc_clickbank_secret');
        register_setting('flosc_settings', 'flosc_clickbank_product');
        register_setting('flosc_settings', 'flosc_clickbank_access_level');

        // AI Base Prompt (v5.0.6 - added to flosc_settings group)
        register_setting('flosc_settings', 'flosc_ai_base_prompt');
    }
    
    /**
     * Render Admin Pages
     */
    public function render_admin_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/settings.php';
    }
    
    public function render_offers_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/offers.php';
    }
    
    public function render_payments_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/payments.php';
    }

    public function render_ai_config_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/ai-config.php';
    }

    /**
     * Render AI Knowledge Base admin page
     * v06.02: Renamed from ai-orientation.php to ai-knowledge-base.php for clarity
     */
    public function render_ai_configuration_page() {
        include FLOSC_PLUGIN_DIR . 'templates/admin/ai-knowledge-base.php';
    }

    /**
     * Redirect handlers for tab shortcuts - ALL menu items go to Settings tabs
     */
    public function redirect_to_ivr_tab() {
        wp_redirect(admin_url('admin.php?page=flosc-settings&tab=ivr-messages'));
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
     * Enqueue Assets
     */
    public function enqueue_assets() {
        if (!get_query_var('flosc_app')) return;
        
        // Dequeue theme styles
        global $wp_styles;
        foreach ($wp_styles->queue as $handle) {
            wp_dequeue_style($handle);
        }
        
        // Our assets
        wp_enqueue_style('flosc-app', FLOSC_PLUGIN_URL . 'assets/css/flosc-app.css', [], FLOSC_VERSION);
        wp_enqueue_script('flosc-app', FLOSC_PLUGIN_URL . 'assets/js/flosc-app.js', [], FLOSC_VERSION, true);
        
        // Stripe.js if configured
        $stripe = $this->sale_manager->get_provider('stripe');
        if ($stripe && $stripe->is_configured()) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, false);
        }
    }
    
    /**
     * REST API Routes
     */
    public function register_rest_routes() {
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
        register_rest_route('flosc/v1', '/offers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_offers'],
            'permission_callback' => '__return_true',
        ]);
        
        // Purchase
        register_rest_route('flosc/v1', '/purchase', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_purchase'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Create Payment Intent (for Stripe)
        register_rest_route('flosc/v1', '/create-payment-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'create_payment_intent'],
            'permission_callback' => 'is_user_logged_in',
        ]);
        
        // Webhooks (v6.0.1: rate limited, signature verified in handler)
        register_rest_route('flosc/v1', '/webhooks/(?P<provider>[a-z]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'check_webhook_permission'],
        ]);
        
        // Access check
        register_rest_route('flosc/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$this, 'check_access'],
            'permission_callback' => '__return_true',
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
        register_rest_route('flosc/v1', '/lessons', [
            'methods' => 'GET',
            'callback' => [$this, 'get_lessons'],
            'permission_callback' => '__return_true',
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
        
        // Store pre-login score
        register_rest_route('flosc/v1', '/store-score', [
            'methods' => 'POST',
            'callback' => [$this, 'store_prelogin_score'],
            'permission_callback' => '__return_true',
        ]);

        // Mark funnel completed (v3.0.4)
        register_rest_route('flosc/v1', '/funnel-complete', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_funnel_complete'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // v05_07: Mark offer shown (for phase logic)
        register_rest_route('flosc/v1', '/mark-offer-shown', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_mark_offer_shown'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // v05_07: Mark onboarded (for phase logic)
        register_rest_route('flosc/v1', '/mark-onboarded', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_mark_onboarded'],
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
    }
    
    /**
     * REST Handlers
     */
    public function handle_ai_query($request) {
        $message = $request->get_param('message');
        $context = $request->get_param('context') ?? [];
        
        // v6.0.1: Input validation with schema
        if (!FLOSC_Validator::validate_message($message)) {
            FLOSC_Logger::warning('AI query invalid message', [
                'error' => FLOSC_Validator::get_error(),
                'length' => is_string($message) ? strlen($message) : 'not_string',
            ]);
            return new WP_Error('invalid_message', FLOSC_Validator::get_error() ?? 'Invalid message', ['status' => 400]);
        }
        
        // Sanitize after validation
        $message = sanitize_text_field($message);
        
        // v6.0.1: Validate and sanitize context
        if (!empty($context) && is_array($context)) {
            $context = FLOSC_Validator::sanitize($context, [
                'type' => 'array',
                'maxItems' => 20,
            ]);
        } else {
            $context = [];
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
                    FLOSC_Logger::info('AI query limit reached', ['user_id' => $user_id]);
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
            'analysis' => $analysis,
            'lessons' => $lessons,
            'message' => $message,
        ]);
    }

    public function get_sessions($request) {
        $sessions = $this->session_manager->get_user_sessions(get_current_user_id());
        return new WP_REST_Response(['sessions' => $sessions]);
    }
    
    public function create_session($request) {
        $title = sanitize_text_field($request->get_param('title') ?? 'New Chat');
        $session = $this->session_manager->create_session(get_current_user_id(), $title);
        return new WP_REST_Response(['session' => $session]);
    }
    
    public function get_offers($request) {
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        $offers = $this->sale_manager->get_available_offers($user_id);
        return new WP_REST_Response(['offers' => array_values($offers)]);
    }
    
    public function handle_purchase($request) {
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $provider_id = sanitize_text_field($request->get_param('provider'));
        $payment_data = $request->get_param('payment_data') ?? [];
        
        $result = $this->sale_manager->process_purchase(
            get_current_user_id(),
            $offer_id,
            $provider_id,
            $payment_data
        );
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result);
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
     * Get the user's free lesson based on their quiz results
     */
    public function get_free_lesson($request) {
        $user_id = get_current_user_id();
        
        // Check if already delivered
        if (get_user_meta($user_id, '_flosc_free_lesson_delivered', true)) {
            $free_lesson_id = get_user_meta($user_id, '_flosc_free_lesson_id', true);
            if ($free_lesson_id) {
                $lesson = $this->lesson_manager->get_lesson($free_lesson_id);
                return new WP_REST_Response([
                    'lesson' => $lesson,
                    'already_delivered' => true,
                ]);
            }
        }
        
        // Get missed items from last quiz
        $quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        $missed_items = $quiz_data['incorrect'] ?? [];
        
        // Get matching lesson
        $lesson = $this->lesson_manager->get_free_lesson($missed_items);
        
        if (!$lesson) {
            return new WP_Error('no_lesson', 'No lessons available', ['status' => 404]);
        }
        
        // Store which lesson is their free one
        update_user_meta($user_id, '_flosc_free_lesson_id', $lesson['id']);
        
        // Get full content
        $lesson = $this->lesson_manager->get_lesson($lesson['id']);
        
        // Mark as delivered
        update_user_meta($user_id, '_flosc_free_lesson_delivered', current_time('mysql'));
        
        return new WP_REST_Response([
            'lesson' => $lesson,
            'already_delivered' => false,
        ]);
    }
    
    /**
     * Store pre-login quiz score (for visitors)
     */
    public function store_prelogin_score($request) {
        $score_data = [
            'score' => intval($request->get_param('score')),
            'correct' => $request->get_param('correct') ?? [],
            'incorrect' => $request->get_param('incorrect') ?? [],
            'quiz_type' => sanitize_text_field($request->get_param('quiz_type') ?? ''),
            'timestamp' => time(),
        ];
        
        // Store in cookie (JS will also store in localStorage as backup)
        // v05_08: Added secure cookie options
        setcookie(
            'flosc_prelogin_score',
            wp_json_encode($score_data),
            [
                'expires' => time() + HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
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
     * v05_07: Mark offer shown (for phase logic)
     */
    public function handle_mark_offer_shown($request) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, '_flosc_offer_shown', true);
        return new WP_REST_Response(['success' => true]);
    }

    /**
     * v05_07: Mark onboarded (for phase logic)
     */
    public function handle_mark_onboarded($request) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, '_flosc_onboarded', true);
        return new WP_REST_Response(['success' => true]);
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
 * Plugin activation (v3.0.9 - Resolved: moved outside class so hook fires correctly)
 */
function flosc_activate() {
    // Set defaults (only if they don't exist)
    $defaults = [
        'flosc_app_slug' => 'app',
        'flosc_product_name' => '',
        'flosc_product_tagline' => '',
        'flosc_product_emoji' => '🎯',
        'flosc_primary_color' => '#4f46e5',
        'flosc_ai_provider' => 'ivr',
        'flosc_stt_provider' => 'assemblyai',
        'flosc_quiz_type' => 'simple_scoring',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Force critical "out of box" defaults (v3.0.8 - added FLOSC Default prefix)
    // These are set every activation to ensure plugin works without configuration
    $force_defaults = [
        'flosc_quiz_content_simple_scoring' => '1,2,3,4,5,6,7,8,9,10',
        'flosc_token_name' => 'tokens',
        'flosc_get_started_message' => 'FLOSC Default Get started text: Welcome! I\'m your FLOSC learning assistant. I\'m here to help you master new skills through interactive lessons and quizzes. Ready to get started?',
        'flosc_how_it_works_message' => 'FLOSC Default How does it work? text: Here\'s how it works: First, you\'ll take a quick quiz to assess your current level. Then, based on your results, I\'ll unlock a free lesson personalized to your needs. After that, you can upgrade for full access to all lessons and ongoing support.',
        'flosc_what_you_learn_message' => 'FLOSC Default What will I learn? text: You\'ll master practical skills through interactive lessons, get personalized feedback on your progress, and access a complete learning path designed to take you from beginner to advanced. Each lesson includes exercises, quizzes, and real-world applications.',
    ];

    foreach ($force_defaults as $key => $value) {
        update_option($key, $value);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Register activation hook
register_activation_hook(__FILE__, 'flosc_activate');

// Start the plugin
add_action('plugins_loaded', 'flosc');
