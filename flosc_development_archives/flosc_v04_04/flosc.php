<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - Quiz-based learning and conversational sales funnel framework
 * Version: 4.0.4
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '4.0.4');
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

        // IVR system (v04_03)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-manager.php';

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
    }
    
    private function init_hooks() {
        // Virtual page routing
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_app_route']);
        
        // Admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
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
                
                // Clear the cookie (set expiry in past)
                setcookie('flosc_prelogin_score', '', time() - 3600, '/');
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
            setcookie('flosc_referrer', sanitize_text_field($ref), time() + (30 * DAY_IN_SECONDS), '/');
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
            $quiz_score = get_user_meta($user->ID, 'flosc_last_quiz_score', true);
            if ($quiz_score) {
                $context['quiz_score'] = $quiz_score . '%';
            }

            // Free lesson delivered
            $free_lesson_delivered = get_user_meta($user->ID, 'flosc_free_lesson_delivered', true);
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
            $funnel_complete = get_user_meta($user_id, 'flosc_funnel_completed', true);
            if ($funnel_complete) {
                return 'content';
            }
            return 'sale';
        }

        // Check if offer was shown
        $offer_shown = get_user_meta($user_id, 'flosc_offer_shown', true);
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
     */
    public function add_admin_menu() {
        add_menu_page(
            'FLOSC Settings',
            'FLOSC',
            'manage_options',
            'flosc-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'flosc-settings',
            'Offers',
            'Offers',
            'manage_options',
            'flosc-offers',
            [$this, 'render_offers_page']
        );
        
        add_submenu_page(
            'flosc-settings',
            'Payment Providers',
            'Payments',
            'manage_options',
            'flosc-payments',
            [$this, 'render_payments_page']
        );

        // v04_04: AI Configuration
        add_submenu_page(
            'flosc-settings',
            'AI Configuration',
            'AI Config',
            'manage_options',
            'flosc-ai-config',
            [$this, 'render_ai_config_page']
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
        
        // Webhooks
        register_rest_route('flosc/v1', '/webhooks/(?P<provider>[a-z]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => '__return_true',
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
    }
    
    /**
     * REST Handlers
     */
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
        setcookie(
            'flosc_prelogin_score',
            wp_json_encode($score_data),
            time() + HOUR_IN_SECONDS,
            '/'
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
