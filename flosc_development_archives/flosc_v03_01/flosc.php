<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - Quiz-based learning and conversational sales funnel framework
 * Version: 3.0.1
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '3.0.1');
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
    
    private function load_dependencies() {
        // Core components
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-type-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';

        // SALE system
        require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-sale-manager.php';

        $this->ai_factory = new FLOSC_AI_Provider_Factory();
        $this->stt_factory = new FLOSC_STT_Provider_Factory();
        $this->session_manager = new FLOSC_Session_Manager();
        $this->pronunciation_analyzer = new FLOSC_Pronunciation_Analyzer();

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
        
        // Activation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
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
            'permission_callback' => '__return_true',
        ]);
        
        // Process Audio (for audio-based quiz types)
        register_rest_route('flosc/v1', '/process-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => '__return_true',
        ]);

        // Process Quiz (for text-based quiz types)
        register_rest_route('flosc/v1', '/process-quiz', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_quiz'],
            'permission_callback' => '__return_true',
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
        
        $product = $this->get_product_config();
        $system_prompt = "You are {$product['name']}, an AI assistant. {$product['tagline']}. Be helpful, friendly, and concise.";
        
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
        
        $result = $provider->handle_webhook(
            $request->get_body(),
            $request->get_headers()
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

// Start the plugin
add_action('plugins_loaded', 'flosc');
