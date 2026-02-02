<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - A conversational sales funnel framework for WordPress
 * Version: 0.2.8
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '0.2.8');
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main FLOSC Framework Class
 */
class FLOSC_Framework {
    
    private static $instance = null;
    private $ai_factory;
    private $stt_factory;
    private $session_manager;
    private $access_control;
    private $pronunciation_analyzer;
    
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
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-access-control.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        
        $this->ai_factory = new FLOSC_AI_Provider_Factory();
        $this->stt_factory = new FLOSC_STT_Provider_Factory();
        $this->session_manager = new FLOSC_Session_Manager();
        $this->access_control = new FLOSC_Access_Control();
        $this->pronunciation_analyzer = new FLOSC_Pronunciation_Analyzer();
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
        
        // Activation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
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
            'flosc_quiz_mode' => 'counting',
            'flosc_quiz_expected' => '1 2 3 4 5 6 7 8 9 10',
            'flosc_product_price' => '144.00',
            'flosc_currency' => 'EUR',
            'flosc_stripe_mode' => 'test',
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
            $has_paid = $this->access_control->has_paid_access($user->ID);
            $user_state = $has_paid ? 'paid' : 'free';
            
            $user_data = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 40]),
                'state' => $user_state,
            ];
        }
        
        // Get product config
        $product = $this->get_product_config();
        
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
            'price' => get_option('flosc_product_price', '144.00'),
            'currency' => get_option('flosc_currency', 'EUR'),
            'currency_symbol' => $this->get_currency_symbol(get_option('flosc_currency', 'EUR')),
            'share_text' => get_option('flosc_share_text', 'Check out this amazing app!'),
        ];
    }
    
    private function get_currency_symbol($currency) {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'JPY' => '¥',
        ];
        return $symbols[$currency] ?? $currency;
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
    }
    
    /**
     * Register All Settings
     */
    public function register_settings() {
        // Product Settings
        register_setting('flosc_settings', 'flosc_app_slug', ['sanitize_callback' => 'sanitize_title']);
        register_setting('flosc_settings', 'flosc_product_name', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_product_tagline', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_product_emoji', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_product_logo', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('flosc_settings', 'flosc_primary_color', ['sanitize_callback' => 'sanitize_hex_color']);
        register_setting('flosc_settings', 'flosc_share_text', ['sanitize_callback' => 'sanitize_text_field']);
        
        // Pricing Settings
        register_setting('flosc_settings', 'flosc_product_price', ['sanitize_callback' => 'floatval']);
        register_setting('flosc_settings', 'flosc_currency', ['sanitize_callback' => 'sanitize_text_field']);
        
        // Access Control
        register_setting('flosc_settings', 'flosc_buddyboss_group_id', ['sanitize_callback' => 'absint']);
        
        // AI Provider Settings
        register_setting('flosc_settings', 'flosc_ai_provider', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_openai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_anthropic_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_xai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        
        // STT Provider Settings
        register_setting('flosc_settings', 'flosc_stt_provider', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_assemblyai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_deepgram_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_custom_stt_endpoint', ['sanitize_callback' => 'esc_url_raw']);
        
        // Quiz Settings
        register_setting('flosc_settings', 'flosc_quiz_mode', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_quiz_expected', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('flosc_settings', 'flosc_quiz_instructions', ['sanitize_callback' => 'sanitize_textarea_field']);
        
        // Stripe Settings
        register_setting('flosc_settings', 'flosc_stripe_mode', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_stripe_test_pk', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_stripe_test_sk', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_stripe_live_pk', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_stripe_live_sk', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('flosc_settings', 'flosc_stripe_webhook_secret', ['sanitize_callback' => 'sanitize_text_field']);
        
        // Analytics
        register_setting('flosc_settings', 'flosc_ga4_id', ['sanitize_callback' => 'sanitize_text_field']);
    }
    
    /**
     * Admin Page Render
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';
        $slug = get_option('flosc_app_slug', 'app');
        $app_url = home_url('/' . $slug . '/');
        ?>
        <div class="wrap flosc-admin">
            <h1>FLOSC Settings</h1>
            
            <div class="flosc-admin-header">
                <p>Your app is live at: <a href="<?php echo esc_url($app_url); ?>" target="_blank"><?php echo esc_html($app_url); ?></a></p>
            </div>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=flosc-settings&tab=product" class="nav-tab <?php echo $active_tab === 'product' ? 'nav-tab-active' : ''; ?>">Product</a>
                <a href="?page=flosc-settings&tab=stripe" class="nav-tab <?php echo $active_tab === 'stripe' ? 'nav-tab-active' : ''; ?>">Stripe</a>
                <a href="?page=flosc-settings&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Provider</a>
                <a href="?page=flosc-settings&tab=stt" class="nav-tab <?php echo $active_tab === 'stt' ? 'nav-tab-active' : ''; ?>">Speech-to-Text</a>
                <a href="?page=flosc-settings&tab=quiz" class="nav-tab <?php echo $active_tab === 'quiz' ? 'nav-tab-active' : ''; ?>">Quiz</a>
                <a href="?page=flosc-settings&tab=access" class="nav-tab <?php echo $active_tab === 'access' ? 'nav-tab-active' : ''; ?>">Access Control</a>
            </nav>
            
            <form method="post" action="options.php">
                <?php settings_fields('flosc_settings'); ?>
                
                <?php if ($active_tab === 'product'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flosc_app_slug">App URL Slug</label></th>
                        <td>
                            <input type="text" id="flosc_app_slug" name="flosc_app_slug" value="<?php echo esc_attr(get_option('flosc_app_slug', 'app')); ?>" class="regular-text">
                            <p class="description">Your app will be at: <?php echo esc_html(home_url('/')); ?><strong>[slug]</strong>/</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_product_name">Product Name</label></th>
                        <td>
                            <input type="text" id="flosc_product_name" name="flosc_product_name" value="<?php echo esc_attr(get_option('flosc_product_name', '')); ?>" class="regular-text" placeholder="e.g., LeSAEp, Solfeggio Coach">
                            <p class="description">The name of your product/course</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_product_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="flosc_product_tagline" name="flosc_product_tagline" value="<?php echo esc_attr(get_option('flosc_product_tagline', '')); ?>" class="regular-text" placeholder="e.g., Your AI pronunciation coach">
                            <p class="description">Short description shown on landing page</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_product_emoji">Logo Emoji</label></th>
                        <td>
                            <input type="text" id="flosc_product_emoji" name="flosc_product_emoji" value="<?php echo esc_attr(get_option('flosc_product_emoji', '🎯')); ?>" class="small-text">
                            <p class="description">Emoji shown in sidebar and chat (used if no logo image)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_product_logo">Logo URL</label></th>
                        <td>
                            <input type="url" id="flosc_product_logo" name="flosc_product_logo" value="<?php echo esc_attr(get_option('flosc_product_logo', '')); ?>" class="regular-text" placeholder="https://...">
                            <p class="description">Optional: URL to logo image (replaces emoji)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_primary_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="flosc_primary_color" name="flosc_primary_color" value="<?php echo esc_attr(get_option('flosc_primary_color', '#4f46e5')); ?>">
                            <p class="description">Main brand color for buttons and accents</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_product_price">Price</label></th>
                        <td>
                            <input type="number" id="flosc_product_price" name="flosc_product_price" value="<?php echo esc_attr(get_option('flosc_product_price', '144.00')); ?>" class="small-text" step="0.01" min="0">
                            <select name="flosc_currency" id="flosc_currency">
                                <option value="EUR" <?php selected(get_option('flosc_currency'), 'EUR'); ?>>EUR (€)</option>
                                <option value="USD" <?php selected(get_option('flosc_currency'), 'USD'); ?>>USD ($)</option>
                                <option value="GBP" <?php selected(get_option('flosc_currency'), 'GBP'); ?>>GBP (£)</option>
                            </select>
                            <p class="description">Course price for checkout</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_share_text">Share Text</label></th>
                        <td>
                            <input type="text" id="flosc_share_text" name="flosc_share_text" value="<?php echo esc_attr(get_option('flosc_share_text', 'Check out this amazing app!')); ?>" class="regular-text">
                            <p class="description">Default text when users share referral links</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_ga4_id">Google Analytics ID</label></th>
                        <td>
                            <input type="text" id="flosc_ga4_id" name="flosc_ga4_id" value="<?php echo esc_attr(get_option('flosc_ga4_id', '')); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                            <p class="description">Optional: For funnel tracking and conversion analytics</p>
                        </td>
                    </tr>
                </table>
                
                <?php elseif ($active_tab === 'stripe'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Stripe Mode</th>
                        <td>
                            <label><input type="radio" name="flosc_stripe_mode" value="test" <?php checked(get_option('flosc_stripe_mode', 'test'), 'test'); ?>> Test Mode</label><br>
                            <label><input type="radio" name="flosc_stripe_mode" value="live" <?php checked(get_option('flosc_stripe_mode'), 'live'); ?>> Live Mode</label>
                            <p class="description">Use Test Mode while developing, switch to Live for real payments</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2"><h3>Test Keys</h3></th>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_stripe_test_pk">Test Publishable Key</label></th>
                        <td>
                            <input type="text" id="flosc_stripe_test_pk" name="flosc_stripe_test_pk" value="<?php echo esc_attr(get_option('flosc_stripe_test_pk', '')); ?>" class="regular-text" placeholder="pk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_stripe_test_sk">Test Secret Key</label></th>
                        <td>
                            <input type="password" id="flosc_stripe_test_sk" name="flosc_stripe_test_sk" value="<?php echo esc_attr(get_option('flosc_stripe_test_sk', '')); ?>" class="regular-text" placeholder="sk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2"><h3>Live Keys</h3></th>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_stripe_live_pk">Live Publishable Key</label></th>
                        <td>
                            <input type="text" id="flosc_stripe_live_pk" name="flosc_stripe_live_pk" value="<?php echo esc_attr(get_option('flosc_stripe_live_pk', '')); ?>" class="regular-text" placeholder="pk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_stripe_live_sk">Live Secret Key</label></th>
                        <td>
                            <input type="password" id="flosc_stripe_live_sk" name="flosc_stripe_live_sk" value="<?php echo esc_attr(get_option('flosc_stripe_live_sk', '')); ?>" class="regular-text" placeholder="sk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" colspan="2"><h3>Webhook</h3></th>
                    </tr>
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <code><?php echo esc_html(rest_url('flosc/v1/stripe-webhook')); ?></code>
                            <p class="description">Add this URL in your Stripe Dashboard → Webhooks</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_stripe_webhook_secret">Webhook Signing Secret</label></th>
                        <td>
                            <input type="password" id="flosc_stripe_webhook_secret" name="flosc_stripe_webhook_secret" value="<?php echo esc_attr(get_option('flosc_stripe_webhook_secret', '')); ?>" class="regular-text" placeholder="whsec_...">
                        </td>
                    </tr>
                </table>
                
                <?php elseif ($active_tab === 'ai'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flosc_ai_provider">AI Provider</label></th>
                        <td>
                            <select name="flosc_ai_provider" id="flosc_ai_provider">
                                <option value="ivr" <?php selected(get_option('flosc_ai_provider'), 'ivr'); ?>>IVR (Scripted - Free)</option>
                                <option value="openai" <?php selected(get_option('flosc_ai_provider'), 'openai'); ?>>OpenAI (GPT-4o-mini)</option>
                                <option value="anthropic" <?php selected(get_option('flosc_ai_provider'), 'anthropic'); ?>>Anthropic (Claude)</option>
                                <option value="xai" <?php selected(get_option('flosc_ai_provider'), 'xai'); ?>>xAI (Grok)</option>
                            </select>
                            <p class="description">IVR = Scripted responses (no API cost). Other providers require API keys.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" id="flosc_openai_api_key" name="flosc_openai_api_key" value="<?php echo esc_attr(get_option('flosc_openai_api_key', '')); ?>" class="regular-text" placeholder="sk-...">
                            <p class="description">~$0.005 per interaction</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_anthropic_api_key">Anthropic API Key</label></th>
                        <td>
                            <input type="password" id="flosc_anthropic_api_key" name="flosc_anthropic_api_key" value="<?php echo esc_attr(get_option('flosc_anthropic_api_key', '')); ?>" class="regular-text" placeholder="sk-ant-...">
                            <p class="description">~$0.003 per interaction</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_xai_api_key">xAI API Key</label></th>
                        <td>
                            <input type="password" id="flosc_xai_api_key" name="flosc_xai_api_key" value="<?php echo esc_attr(get_option('flosc_xai_api_key', '')); ?>" class="regular-text">
                            <p class="description">For Grok integration</p>
                        </td>
                    </tr>
                </table>
                
                <?php elseif ($active_tab === 'stt'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flosc_stt_provider">STT Provider</label></th>
                        <td>
                            <select name="flosc_stt_provider" id="flosc_stt_provider">
                                <option value="assemblyai" <?php selected(get_option('flosc_stt_provider'), 'assemblyai'); ?>>AssemblyAI (Recommended)</option>
                                <option value="openai" <?php selected(get_option('flosc_stt_provider'), 'openai'); ?>>OpenAI Whisper</option>
                                <option value="deepgram" <?php selected(get_option('flosc_stt_provider'), 'deepgram'); ?>>Deepgram</option>
                                <option value="custom" <?php selected(get_option('flosc_stt_provider'), 'custom'); ?>>Custom Endpoint</option>
                            </select>
                            <p class="description">AssemblyAI recommended for best accent handling (~$0.0025 per 10s recording)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_assemblyai_api_key">AssemblyAI API Key</label></th>
                        <td>
                            <input type="password" id="flosc_assemblyai_api_key" name="flosc_assemblyai_api_key" value="<?php echo esc_attr(get_option('flosc_assemblyai_api_key', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_deepgram_api_key">Deepgram API Key</label></th>
                        <td>
                            <input type="password" id="flosc_deepgram_api_key" name="flosc_deepgram_api_key" value="<?php echo esc_attr(get_option('flosc_deepgram_api_key', '')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_custom_stt_endpoint">Custom STT Endpoint</label></th>
                        <td>
                            <input type="url" id="flosc_custom_stt_endpoint" name="flosc_custom_stt_endpoint" value="<?php echo esc_attr(get_option('flosc_custom_stt_endpoint', '')); ?>" class="regular-text" placeholder="https://your-server.com/transcribe">
                            <p class="description">For self-hosted faster-whisper or other STT service</p>
                        </td>
                    </tr>
                </table>
                
                <?php elseif ($active_tab === 'quiz'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flosc_quiz_mode">Quiz Mode</label></th>
                        <td>
                            <select name="flosc_quiz_mode" id="flosc_quiz_mode">
                                <option value="counting" <?php selected(get_option('flosc_quiz_mode'), 'counting'); ?>>Counting (1-10)</option>
                                <option value="sentence" <?php selected(get_option('flosc_quiz_mode'), 'sentence'); ?>>Custom Sentence</option>
                                <option value="none" <?php selected(get_option('flosc_quiz_mode'), 'none'); ?>>No Quiz (Chat Only)</option>
                            </select>
                            <p class="description">Counting mode for pronunciation (LeSAEp), or custom sentence, or no quiz</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_quiz_expected">Expected Response</label></th>
                        <td>
                            <textarea id="flosc_quiz_expected" name="flosc_quiz_expected" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_quiz_expected', '1 2 3 4 5 6 7 8 9 10')); ?></textarea>
                            <p class="description">What the user should say (for pronunciation comparison)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="flosc_quiz_instructions">Quiz Instructions</label></th>
                        <td>
                            <textarea id="flosc_quiz_instructions" name="flosc_quiz_instructions" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_quiz_instructions', 'Please count from 1 to 10 clearly and at a normal pace.')); ?></textarea>
                            <p class="description">Instructions shown to user before recording</p>
                        </td>
                    </tr>
                </table>
                
                <?php elseif ($active_tab === 'access'): ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flosc_buddyboss_group_id">BuddyBoss Paid Group ID</label></th>
                        <td>
                            <input type="number" id="flosc_buddyboss_group_id" name="flosc_buddyboss_group_id" value="<?php echo esc_attr(get_option('flosc_buddyboss_group_id', '')); ?>" class="small-text" min="0">
                            <p class="description">Users in this group get paid access. Leave empty to use user meta only.</p>
                        </td>
                    </tr>
                </table>
                
                <?php endif; ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <style>
        .flosc-admin { max-width: 900px; }
        .flosc-admin-header { background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .flosc-admin .nav-tab-wrapper { margin-bottom: 20px; }
        .flosc-admin h3 { margin: 30px 0 10px; padding-top: 20px; border-top: 1px solid #ddd; }
        </style>
        <?php
    }
    
    /**
     * Enqueue Assets
     */
    public function enqueue_assets() {
        if (!get_query_var('flosc_app')) return;
        
        // Dequeue all theme styles
        global $wp_styles;
        foreach ($wp_styles->queue as $handle) {
            wp_dequeue_style($handle);
        }
        
        // Our assets
        wp_enqueue_style('flosc-app', FLOSC_PLUGIN_URL . 'assets/css/flosc-app.css', [], FLOSC_VERSION);
        wp_enqueue_script('flosc-app', FLOSC_PLUGIN_URL . 'assets/js/flosc-app.js', [], FLOSC_VERSION, true);
        
        // Stripe.js if configured
        $stripe_pk = $this->get_stripe_publishable_key();
        if ($stripe_pk) {
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], null, false);
        }
        
        // Pass config to JS
        wp_localize_script('flosc-app', 'FLOSC_CONFIG', [
            'restUrl' => rest_url('flosc/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'appUrl' => home_url('/' . get_option('flosc_app_slug', 'app') . '/'),
            'stripeKey' => $stripe_pk,
            'product' => $this->get_product_config(),
            'quizMode' => get_option('flosc_quiz_mode', 'counting'),
            'quizInstructions' => get_option('flosc_quiz_instructions', 'Please count from 1 to 10 clearly.'),
            'ga4Id' => get_option('flosc_ga4_id', ''),
        ]);
    }
    
    private function get_stripe_publishable_key() {
        $mode = get_option('flosc_stripe_mode', 'test');
        return $mode === 'live' 
            ? get_option('flosc_stripe_live_pk', '')
            : get_option('flosc_stripe_test_pk', '');
    }
    
    private function get_stripe_secret_key() {
        $mode = get_option('flosc_stripe_mode', 'test');
        return $mode === 'live'
            ? get_option('flosc_stripe_live_sk', '')
            : get_option('flosc_stripe_test_sk', '');
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
        
        // Process Audio (STT + Analysis)
        register_rest_route('flosc/v1', '/process-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => '__return_true',
        ]);
        
        // Sessions
        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sessions'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'POST',
            'callback' => [$this, 'create_session'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        register_rest_route('flosc/v1', '/sessions/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_session'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        register_rest_route('flosc/v1', '/sessions/(?P<id>\d+)/messages', [
            'methods' => 'POST',
            'callback' => [$this, 'add_message'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        // Access check
        register_rest_route('flosc/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$this, 'check_access'],
            'permission_callback' => '__return_true',
        ]);
        
        // Referral link
        register_rest_route('flosc/v1', '/referral', [
            'methods' => 'GET',
            'callback' => [$this, 'generate_referral'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        // Stripe: Create Payment Intent
        register_rest_route('flosc/v1', '/create-payment-intent', [
            'methods' => 'POST',
            'callback' => [$this, 'create_payment_intent'],
            'permission_callback' => [$this, 'check_logged_in'],
        ]);
        
        // Stripe: Webhook
        register_rest_route('flosc/v1', '/stripe-webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_stripe_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    public function check_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * AI Query Handler
     */
    public function handle_ai_query($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $context = $request->get_param('context') ?? [];
        
        if (empty($message)) {
            return new WP_Error('empty_message', 'Message is required', ['status' => 400]);
        }
        
        // Build system prompt with product context
        $product = $this->get_product_config();
        $system_prompt = "You are {$product['name']}, an AI assistant. {$product['tagline']}. Be helpful, friendly, and concise.";
        
        $response = $this->ai_factory->get_response($message, $system_prompt, $context);
        
        return new WP_REST_Response([
            'success' => true,
            'response' => $response,
        ]);
    }
    
    /**
     * Process Audio (STT + Optional Analysis)
     */
    public function handle_process_audio($request) {
        $files = $request->get_file_params();
        
        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }
        
        $audio_file = $files['audio'];
        
        // Validate file
        if ($audio_file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload failed', ['status' => 400]);
        }
        
        // Transcribe
        $transcript = $this->stt_factory->transcribe($audio_file['tmp_name']);
        
        if (is_wp_error($transcript)) {
            return $transcript;
        }
        
        // Analyze if quiz mode is active
        $analysis = null;
        $quiz_mode = get_option('flosc_quiz_mode', 'counting');
        
        if ($quiz_mode !== 'none') {
            $expected = get_option('flosc_quiz_expected', '1 2 3 4 5 6 7 8 9 10');
            $analysis = $this->pronunciation_analyzer->analyze($transcript, $expected);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'transcript' => $transcript,
            'analysis' => $analysis,
        ]);
    }
    
    /**
     * Session Handlers (delegated to Session Manager)
     */
    public function get_sessions($request) {
        $user_id = get_current_user_id();
        $sessions = $this->session_manager->get_user_sessions($user_id);
        return new WP_REST_Response(['sessions' => $sessions]);
    }
    
    public function create_session($request) {
        $user_id = get_current_user_id();
        $title = sanitize_text_field($request->get_param('title') ?? 'New Chat');
        $session = $this->session_manager->create_session($user_id, $title);
        return new WP_REST_Response(['session' => $session]);
    }
    
    public function get_session($request) {
        $session_id = absint($request->get_param('id'));
        $session = $this->session_manager->get_session($session_id);
        
        if (!$session) {
            return new WP_Error('not_found', 'Session not found', ['status' => 404]);
        }
        
        return new WP_REST_Response(['session' => $session]);
    }
    
    public function add_message($request) {
        $session_id = absint($request->get_param('id'));
        $role = sanitize_text_field($request->get_param('role'));
        $content = sanitize_textarea_field($request->get_param('content'));
        
        $result = $this->session_manager->add_message($session_id, $role, $content);
        return new WP_REST_Response(['success' => $result]);
    }
    
    /**
     * Access Check
     */
    public function check_access($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['access' => 'visitor']);
        }
        
        $user_id = get_current_user_id();
        $has_paid = $this->access_control->has_paid_access($user_id);
        
        return new WP_REST_Response([
            'access' => $has_paid ? 'paid' : 'free',
        ]);
    }
    
    /**
     * Generate Referral Link
     */
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
     * Create Stripe Payment Intent
     */
    public function create_payment_intent($request) {
        $secret_key = $this->get_stripe_secret_key();
        
        if (empty($secret_key)) {
            return new WP_Error('stripe_not_configured', 'Stripe is not configured', ['status' => 500]);
        }
        
        $price = floatval(get_option('flosc_product_price', '144.00'));
        $currency = strtolower(get_option('flosc_currency', 'eur'));
        $amount = intval($price * 100); // Convert to cents
        
        $user = wp_get_current_user();
        $product = $this->get_product_config();
        
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $product['name'] . ' Full Course Access',
                'metadata[user_id]' => $user->ID,
                'metadata[user_email]' => $user->user_email,
                'metadata[product]' => $product['name'],
                'receipt_email' => $user->user_email,
            ],
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('stripe_error', $response->get_error_message(), ['status' => 500]);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            return new WP_Error('stripe_error', $body['error']['message'], ['status' => 400]);
        }
        
        return new WP_REST_Response([
            'clientSecret' => $body['client_secret'],
        ]);
    }
    
    /**
     * Handle Stripe Webhook
     */
    public function handle_stripe_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhook_secret = get_option('flosc_stripe_webhook_secret', '');
        
        // Verify signature if secret is configured
        if ($webhook_secret) {
            $timestamp = null;
            $signature = null;
            
            foreach (explode(',', $sig_header) as $part) {
                [$key, $value] = explode('=', $part, 2);
                if ($key === 't') $timestamp = $value;
                if ($key === 'v1') $signature = $value;
            }
            
            $signed_payload = $timestamp . '.' . $payload;
            $expected_sig = hash_hmac('sha256', $signed_payload, $webhook_secret);
            
            if (!hash_equals($expected_sig, $signature)) {
                return new WP_Error('invalid_signature', 'Invalid webhook signature', ['status' => 400]);
            }
        }
        
        $event = json_decode($payload, true);
        
        if ($event['type'] === 'payment_intent.succeeded') {
            $payment_intent = $event['data']['object'];
            $user_id = $payment_intent['metadata']['user_id'] ?? null;
            
            if ($user_id) {
                // Grant access
                $this->access_control->grant_paid_access(intval($user_id));
                
                // Log the purchase
                update_user_meta($user_id, '_flosc_purchase_date', current_time('mysql'));
                update_user_meta($user_id, '_flosc_payment_intent', $payment_intent['id']);
            }
        }
        
        return new WP_REST_Response(['received' => true]);
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
