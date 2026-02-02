<?php
/**
 * Plugin Name: FLOSC Framework v2.0.2
 * Description: Freeline-Login-Offer-Sale-Content conversational sales funnel with pluggable backends
 * Version: 2.0.2
 * Author: Dainis W. Michel
 *
 * ============================================================================
 * FLOSC FRAMEWORK - PRODUCTION READY
 * ============================================================================
 *
 * Architecture:
 * - Modular backend system (mock/FastAPI/OpenAI/Claude/custom)
 * - All-in-chat content rendering (no external links)
 * - BuddyBoss social login integration
 * - WooCommerce auto-access on purchase
 * - Session persistence across page refresh
 * - Comprehensive error handling
 *
 * Testing Tonight:
 * - Configure backend in WP Admin → FLOSC → Backend Settings
 * - Switch between mock/real backends without code changes
 * - Test purchase flow with placeholder product ID
 * - Test social login (requires BuddyBoss plugin active)
 *
 * ============================================================================
 */

if (!defined('ABSPATH')) exit;

/**
 * Load modular components
 * Each component handles one responsibility
 */
require_once plugin_dir_path(__FILE__) . 'includes/class-backend-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-woocommerce-hooks.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-content-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-session-manager.php';

class FLOSC_Framework {

    /**
     * Default configuration
     * These are fallback values if nothing is configured in WP admin
     */
    private $default_config = [
        // === F - FREELINE (Hook/Quiz) ===
        'welcome_message' => 'Welcome! Ready to take a quick assessment?',
        'quiz_prompt' => 'Click the microphone and read the text aloud.',
        'quiz_text' => 'The quick brown fox jumps over the lazy dog.',

        // === BACKEND CONFIGURATION ===
        // Options: 'mock', 'fastapi', 'openai', 'claude', 'custom'
        'backend_type' => 'mock',
        'backend_url' => '', // For FastAPI or custom
        'backend_api_key' => '', // For OpenAI/Claude
        'backend_model' => '', // e.g., 'gpt-4', 'claude-3-sonnet'

        // === L - LOGIN GATE ===
        'login_prompt' => 'Login to see your personalized results!',
        'enable_social_login' => 'yes', // BuddyBoss social buttons

        // === O - OFFER (Results Tiers) ===
        'tier_1_min' => 0,
        'tier_1_max' => 25,
        'tier_1_message' => 'Hmm, something may have gone wrong. Would you like to try again?',
        'tier_2_min' => 26,
        'tier_2_max' => 50,
        'tier_2_message' => 'You have some areas that need attention. The good news? We can fix them!',
        'tier_3_min' => 51,
        'tier_3_max' => 75,
        'tier_3_message' => 'Nice foundation! With some polish, you\'ll sound even better.',
        'tier_4_min' => 76,
        'tier_4_max' => 100,
        'tier_4_message' => 'Excellent work! Just a few fine-tuning opportunities.',

        // === Free Content ===
        'free_content_message' => 'Here is your free lesson to get started:',
        'free_content_id' => '', // WordPress post ID

        // === S - SALE (Offer & Checkout) ===
        'offer_headline' => 'Unlock the Full Course',
        'offer_description' => 'Get access to all lessons and personalized learning.',
        'offer_price' => 144,
        'offer_currency' => '€',
        'woocommerce_product_id' => '', // Placeholder - configure in admin
        'checkout_url' => '', // Falls back to product URL if empty

        // === C - CONTENT (Post-Sale) ===
        'content_source' => 'category', // 'category' or 'manual'
        'content_category' => '', // WordPress category slug
        'paid_group_id' => '', // BuddyBoss group ID (optional)
        'content_display_mode' => 'inline', // 'inline' (in chat) or 'list' (clickable list)

        // === Chat Bot Settings ===
        'chat_bot_name' => 'Coach',
        'chat_bot_avatar' => '🎯',
    ];

    public function __construct() {
        // Shortcode: [flosc] or [flosc campaign="lesaep"]
        add_shortcode('flosc', array($this, 'render_chat'));

        // REST API routes
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Enqueue assets (CSS + JS)
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Initialize modular components
        new FLOSC_WooCommerce_Hooks($this);
        new FLOSC_Session_Manager();
    }

    /**
     * Get config value with default fallback
     * Centralized config retrieval for consistency
     */
    public function get_config($key) {
        $value = get_option('flosc_' . $key);
        if ($value === false || $value === '') {
            return $this->default_config[$key] ?? '';
        }
        return $value;
    }

    /**
     * Render chat interface
     * Shortcode: [flosc] or [flosc campaign="custom_name"]
     */
    public function render_chat($atts = []) {
        $atts = shortcode_atts(['campaign' => 'default'], $atts);

        $user = wp_get_current_user();
        $has_access = $this->user_has_paid_access($user->ID);

        ob_start();
        ?>
        <div id="flosc-container" data-campaign="<?php echo esc_attr($atts['campaign']); ?>">
            <div class="flosc-chat-window">
                <!-- Chat Header -->
                <div class="flosc-header">
                    <div class="flosc-avatar"><?php echo esc_html($this->get_config('chat_bot_avatar')); ?></div>
                    <div class="flosc-info">
                        <h2 class="flosc-title"><?php echo esc_html($this->get_config('chat_bot_name')); ?></h2>
                        <p class="flosc-status">
                            <span class="flosc-status-dot"></span>
                            <span id="floscStatusText">Online</span>
                        </p>
                    </div>
                </div>

                <!-- Message Area -->
                <div class="flosc-messages" id="floscMessages"></div>

                <!-- Input Area (typing indicator, replies, recorder, social login) -->
                <div class="flosc-input-area">
                    <!-- Typing Indicator -->
                    <div class="flosc-typing hidden" id="floscTyping">
                        <span></span><span></span><span></span>
                    </div>

                    <!-- Quick Replies (Yes/No buttons, etc) -->
                    <div class="flosc-replies" id="floscReplies"></div>

                    <!-- Audio Recorder -->
                    <div class="flosc-recorder hidden" id="floscRecorder">
                        <div class="flosc-quiz-text" id="floscQuizText"></div>
                        <button class="flosc-record-btn" id="floscRecordBtn" type="button">
                            <span class="flosc-record-icon">🎤</span>
                            <span class="flosc-record-label">Click to Record</span>
                        </button>
                        <div class="flosc-waveform hidden" id="floscWaveform">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <p class="flosc-record-status" id="floscRecordStatus"></p>
                    </div>

                    <!-- Social Login Buttons (BuddyBoss) -->
                    <div class="flosc-social-login hidden" id="floscSocialLogin"></div>

                    <!-- Content Display Area (for post-purchase lessons) -->
                    <div class="flosc-content-area hidden" id="floscContentArea"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue CSS and JavaScript
     * Only loads on pages with [flosc] shortcode
     */
    public function enqueue_assets() {
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'flosc')) {
            return;
        }

        // Styles
        wp_enqueue_style('flosc', plugins_url('assets/css/flosc.css', __FILE__), [], '2.0.2');

        // JavaScript
        wp_enqueue_script('flosc', plugins_url('assets/js/flosc.js', __FILE__), [], '2.0.2', true);

        $user = wp_get_current_user();

        // Pass configuration to JavaScript
        wp_localize_script('flosc', 'FLOSC_CONFIG', [
            // URLs
            'restUrl' => rest_url('flosc/v1'),
            'loginUrl' => wp_login_url(get_permalink()),
            'checkoutUrl' => $this->get_checkout_url(),

            // User state
            'userId' => $user->ID,
            'isLoggedIn' => is_user_logged_in(),
            'hasPaidAccess' => $this->user_has_paid_access($user->ID),
            'userName' => $user->display_name,
            'userEmail' => $user->user_email,

            // Freeline config
            'welcomeMessage' => $this->get_config('welcome_message'),
            'quizPrompt' => $this->get_config('quiz_prompt'),
            'quizText' => $this->get_config('quiz_text'),

            // Backend config (type determines how quiz is processed)
            'backendType' => $this->get_config('backend_type'),

            // Login config
            'loginPrompt' => $this->get_config('login_prompt'),
            'enableSocialLogin' => $this->get_config('enable_social_login') === 'yes',

            // Result tiers
            'tiers' => [
                ['min' => (int)$this->get_config('tier_1_min'), 'max' => (int)$this->get_config('tier_1_max'), 'message' => $this->get_config('tier_1_message')],
                ['min' => (int)$this->get_config('tier_2_min'), 'max' => (int)$this->get_config('tier_2_max'), 'message' => $this->get_config('tier_2_message')],
                ['min' => (int)$this->get_config('tier_3_min'), 'max' => (int)$this->get_config('tier_3_max'), 'message' => $this->get_config('tier_3_message')],
                ['min' => (int)$this->get_config('tier_4_min'), 'max' => (int)$this->get_config('tier_4_max'), 'message' => $this->get_config('tier_4_message')],
            ],

            // Free content
            'freeContentMessage' => $this->get_config('free_content_message'),

            // Offer config
            'offerHeadline' => $this->get_config('offer_headline'),
            'offerDescription' => $this->get_config('offer_description'),
            'offerPrice' => (int)$this->get_config('offer_price'),
            'offerCurrency' => $this->get_config('offer_currency'),

            // Content display
            'contentDisplayMode' => $this->get_config('content_display_mode'),

            // Bot config
            'botName' => $this->get_config('chat_bot_name'),
            'botAvatar' => $this->get_config('chat_bot_avatar'),

            // Nonce for security
            'nonce' => wp_create_nonce('flosc_rest'),
        ]);
    }

    /**
     * Register REST API routes
     * All chat-backend communication happens through these endpoints
     */
    public function register_rest_routes() {
        // Get user access status
        register_rest_route('flosc/v1', '/status', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_status'),
            'permission_callback' => '__return_true'
        ]);

        // Process quiz (delegates to backend manager)
        register_rest_route('flosc/v1', '/process-quiz', [
            'methods' => 'POST',
            'callback' => array($this, 'rest_process_quiz'),
            'permission_callback' => '__return_true'
        ]);

        // Get free content
        register_rest_route('flosc/v1', '/free-content', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_free_content'),
            'permission_callback' => '__return_true'
        ]);

        // Get paid content (requires login + paid access)
        register_rest_route('flosc/v1', '/content', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_content'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);

        // Get social login buttons HTML
        register_rest_route('flosc/v1', '/social-login', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_social_login'),
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * REST: Get user status
     * Returns current login/payment status
     */
    public function rest_get_status() {
        $user = wp_get_current_user();

        return new WP_REST_Response([
            'logged_in' => is_user_logged_in(),
            'user_id' => $user->ID,
            'display_name' => $user->display_name,
            'has_paid_access' => $this->user_has_paid_access($user->ID),
        ]);
    }

    /**
     * REST: Process quiz audio
     * Delegates to backend manager based on configuration
     *
     * Backend Manager handles:
     * - Mock responses (for testing)
     * - FastAPI calls (self-hosted Whisper)
     * - OpenAI API calls
     * - Claude API calls
     * - Custom endpoint calls
     */
    public function rest_process_quiz($request) {
        $backend_manager = new FLOSC_Backend_Manager($this);
        return $backend_manager->process_quiz($request);
    }

    /**
     * REST: Get free content
     * Returns one free lesson/post
     */
    public function rest_get_free_content() {
        $content_id = $this->get_config('free_content_id');

        if (!$content_id) {
            return new WP_REST_Response([
                'id' => 0,
                'title' => 'Free Lesson',
                'excerpt' => 'Your free lesson content will appear here once configured.',
                'content' => '<p>Configure free content in FLOSC settings.</p>',
            ]);
        }

        $renderer = new FLOSC_Content_Renderer($this);
        return $renderer->get_single_post($content_id);
    }

    /**
     * REST: Get paid content
     * Returns all lessons for paid users
     * Content is rendered inline in chat (no external links)
     */
    public function rest_get_content($request) {
        $user_id = get_current_user_id();

        if (!$this->user_has_paid_access($user_id)) {
            return new WP_Error('forbidden', 'Paid access required', ['status' => 403]);
        }

        $renderer = new FLOSC_Content_Renderer($this);
        return $renderer->get_content_list();
    }

    /**
     * REST: Get social login buttons HTML
     * Copied from korboc-survey plugin (read-only)
     * Requires BuddyBoss plugin with social login enabled
     */
    public function rest_get_social_login() {
        $html = $this->render_buddyboss_social_buttons();

        return new WP_REST_Response([
            'html' => $html,
            'available' => class_exists('BB_SSO'),
        ]);
    }

    /**
     * Render BuddyBoss social login buttons
     * COPIED FROM: /latvijas_kori/code/functional_code/korboc-v9.3.3/includes/shortcodes.php
     * DO NOT MODIFY - Keep in sync with korboc implementation
     */
    private function render_buddyboss_social_buttons() {
        if (!class_exists('BB_SSO') || !method_exists('BB_SSO', 'render_buttons_with_container')) {
            return '<p class="social-note">Social login not available. Please enable BuddyBoss social login.</p>';
        }

        return BB_SSO::render_buttons_with_container(array(
            'label_type' => 'register',
            'style' => 'icon',
        ));
    }

    /**
     * Check if user has paid access
     * Checks multiple methods in order:
     * 1. BuddyBoss group membership (if configured)
     * 2. User meta flag
     * 3. User capability
     */
    public function user_has_paid_access($user_id) {
        if (!$user_id) return false;

        // Method 1: BuddyBoss group
        $group_id = $this->get_config('paid_group_id');
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) {
                return true;
            }
        }

        // Method 2: User meta
        if (get_user_meta($user_id, 'flosc_paid_access', true) === '1') {
            return true;
        }

        // Method 3: Capability
        $user = get_user_by('id', $user_id);
        if ($user && $user->has_cap('flosc_full_access')) {
            return true;
        }

        return false;
    }

    /**
     * Get checkout URL
     * Falls back to WooCommerce product URL if no custom checkout URL set
     */
    private function get_checkout_url() {
        $url = $this->get_config('checkout_url');

        if (!$url) {
            $product_id = $this->get_config('woocommerce_product_id');
            if ($product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $url = $product->add_to_cart_url();
                }
            }
        }

        return $url ?: '#';
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'FLOSC Framework',
            'FLOSC',
            'manage_options',
            'flosc-settings',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            30
        );
    }

    /**
     * Register all settings
     */
    public function register_settings() {
        foreach (array_keys($this->default_config) as $key) {
            register_setting('flosc_settings', 'flosc_' . $key);
        }
    }

    /**
     * Render admin settings page
     * Organized by FLOSC stages: F → L → O → S → C
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        // Show success message if settings saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error('flosc_messages', 'flosc_message', 'Settings Saved', 'updated');
        }
        settings_errors('flosc_messages');

        ?>
        <div class="wrap">
            <h1>🎯 FLOSC Framework Settings v2.0.2</h1>
            <p>Configure your Freeline → Login → Offer → Sale → Content funnel</p>

            <form method="post" action="options.php">
                <?php settings_fields('flosc_settings'); ?>

                <!-- F - FREELINE -->
                <h2 class="title">F - Freeline (Quiz/Hook)</h2>
                <p>The initial interaction that hooks users and collects assessment data.</p>
                <table class="form-table">
                    <tr>
                        <th>Welcome Message</th>
                        <td>
                            <textarea name="flosc_welcome_message" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('welcome_message')); ?></textarea>
                            <p class="description">First message users see when chat loads</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Prompt</th>
                        <td>
                            <textarea name="flosc_quiz_prompt" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('quiz_prompt')); ?></textarea>
                            <p class="description">Instructions before recording starts</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Text (what user reads)</th>
                        <td>
                            <textarea name="flosc_quiz_text" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('quiz_text')); ?></textarea>
                            <p class="description">Text displayed during recording</p>
                        </td>
                    </tr>
                </table>

                <!-- BACKEND CONFIGURATION -->
                <h2 class="title">Backend Configuration (Quiz Processing)</h2>
                <p><strong>🔧 TEST MULTIPLE OPTIONS:</strong> Switch between backends without code changes</p>
                <table class="form-table" style="background: #f0f8ff; padding: 15px;">
                    <tr>
                        <th>Backend Type</th>
                        <td>
                            <select name="flosc_backend_type" id="flosc_backend_type">
                                <option value="mock" <?php selected($this->get_config('backend_type'), 'mock'); ?>>Mock (random scores, no API)</option>
                                <option value="fastapi" <?php selected($this->get_config('backend_type'), 'fastapi'); ?>>FastAPI (self-hosted Whisper)</option>
                                <option value="openai" <?php selected($this->get_config('backend_type'), 'openai'); ?>>OpenAI API</option>
                                <option value="claude" <?php selected($this->get_config('backend_type'), 'claude'); ?>>Claude API</option>
                                <option value="custom" <?php selected($this->get_config('backend_type'), 'custom'); ?>>Custom Endpoint</option>
                            </select>
                            <p class="description">
                                <strong>Mock:</strong> Test chat flow without real processing<br>
                                <strong>FastAPI:</strong> Your self-hosted Whisper backend<br>
                                <strong>OpenAI/Claude:</strong> Cloud APIs (requires API key)<br>
                                <strong>Custom:</strong> Any endpoint that accepts audio + returns score
                            </p>
                        </td>
                    </tr>
                    <tr class="backend-field" data-backend="fastapi custom">
                        <th>Backend URL</th>
                        <td>
                            <input type="url" name="flosc_backend_url" value="<?php echo esc_attr($this->get_config('backend_url')); ?>" class="large-text" placeholder="https://your-server.com/process-audio">
                            <p class="description">For FastAPI or custom endpoints</p>
                        </td>
                    </tr>
                    <tr class="backend-field" data-backend="openai claude">
                        <th>API Key</th>
                        <td>
                            <input type="password" name="flosc_backend_api_key" value="<?php echo esc_attr($this->get_config('backend_api_key')); ?>" class="large-text">
                            <p class="description">OpenAI or Claude API key</p>
                        </td>
                    </tr>
                    <tr class="backend-field" data-backend="openai claude">
                        <th>Model</th>
                        <td>
                            <input type="text" name="flosc_backend_model" value="<?php echo esc_attr($this->get_config('backend_model')); ?>" placeholder="gpt-4 or claude-3-sonnet-20240229">
                            <p class="description">Specific model name</p>
                        </td>
                    </tr>
                </table>

                <!-- L - LOGIN GATE -->
                <h2 class="title">L - Login Gate</h2>
                <p>Force login to see quiz results (captures user data).</p>
                <table class="form-table">
                    <tr>
                        <th>Login Prompt</th>
                        <td>
                            <textarea name="flosc_login_prompt" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('login_prompt')); ?></textarea>
                            <p class="description">Message shown when forcing login</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Enable Social Login</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_social_login" value="yes" <?php checked($this->get_config('enable_social_login'), 'yes'); ?>>
                                Show BuddyBoss social login buttons (Google, Facebook, etc)
                            </label>
                            <p class="description">Requires BuddyBoss plugin with social login configured</p>
                        </td>
                    </tr>
                </table>

                <!-- O - OFFER (Results + Free Content) -->
                <h2 class="title">O - Offer (Results & Tiers)</h2>
                <p>Define score ranges and corresponding messages. After results, show free content + upsell offer.</p>

                <?php for ($i = 1; $i <= 4; $i++): ?>
                <table class="form-table" style="background: #f9f9f9; margin-bottom: 10px; padding: 10px;">
                    <tr>
                        <th>Tier <?php echo $i; ?> Score Range</th>
                        <td>
                            <input type="number" name="flosc_tier_<?php echo $i; ?>_min" value="<?php echo esc_attr($this->get_config("tier_{$i}_min")); ?>" style="width:60px" min="0" max="100"> to
                            <input type="number" name="flosc_tier_<?php echo $i; ?>_max" value="<?php echo esc_attr($this->get_config("tier_{$i}_max")); ?>" style="width:60px" min="0" max="100">
                        </td>
                    </tr>
                    <tr>
                        <th>Tier <?php echo $i; ?> Message</th>
                        <td>
                            <textarea name="flosc_tier_<?php echo $i; ?>_message" rows="2" class="large-text"><?php echo esc_textarea($this->get_config("tier_{$i}_message")); ?></textarea>
                        </td>
                    </tr>
                </table>
                <?php endfor; ?>

                <h3>Free Content (Give Value Before Sale)</h3>
                <table class="form-table">
                    <tr>
                        <th>Free Content Message</th>
                        <td>
                            <textarea name="flosc_free_content_message" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('free_content_message')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Free Content Post ID</th>
                        <td>
                            <input type="number" name="flosc_free_content_id" value="<?php echo esc_attr($this->get_config('free_content_id')); ?>">
                            <p class="description">WordPress post ID to show as free lesson</p>
                        </td>
                    </tr>
                </table>

                <!-- S - SALE -->
                <h2 class="title">S - Sale (Offer & Checkout)</h2>
                <p>Present the paid offer and handle checkout.</p>
                <table class="form-table">
                    <tr>
                        <th>Offer Headline</th>
                        <td>
                            <input type="text" name="flosc_offer_headline" value="<?php echo esc_attr($this->get_config('offer_headline')); ?>" class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Offer Description</th>
                        <td>
                            <textarea name="flosc_offer_description" rows="3" class="large-text"><?php echo esc_textarea($this->get_config('offer_description')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Price</th>
                        <td>
                            <select name="flosc_offer_currency" style="width:60px">
                                <option value="€" <?php selected($this->get_config('offer_currency'), '€'); ?>>€</option>
                                <option value="$" <?php selected($this->get_config('offer_currency'), '$'); ?>>$</option>
                                <option value="£" <?php selected($this->get_config('offer_currency'), '£'); ?>>£</option>
                            </select>
                            <input type="number" name="flosc_offer_price" value="<?php echo esc_attr($this->get_config('offer_price')); ?>" style="width:80px">
                        </td>
                    </tr>
                    <tr>
                        <th>WooCommerce Product ID</th>
                        <td>
                            <input type="number" name="flosc_woocommerce_product_id" value="<?php echo esc_attr($this->get_config('woocommerce_product_id')); ?>">
                            <p class="description">
                                <strong>⚠️ PLACEHOLDER FOR TESTING:</strong> Use any product ID (e.g., 999)<br>
                                On purchase, user automatically gets access to content.<br>
                                Leave empty to test without WooCommerce.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom Checkout URL</th>
                        <td>
                            <input type="url" name="flosc_checkout_url" value="<?php echo esc_attr($this->get_config('checkout_url')); ?>" class="large-text" placeholder="Optional - falls back to WooCommerce product URL">
                            <p class="description">Leave empty to use WooCommerce product checkout</p>
                        </td>
                    </tr>
                </table>

                <!-- C - CONTENT -->
                <h2 class="title">C - Content (Post-Sale Delivery)</h2>
                <p><strong>ALL CONTENT DISPLAYS IN CHAT</strong> - no external links, everything inline.</p>
                <table class="form-table">
                    <tr>
                        <th>Content Source</th>
                        <td>
                            <select name="flosc_content_source">
                                <option value="category" <?php selected($this->get_config('content_source'), 'category'); ?>>WordPress Category</option>
                                <option value="manual" <?php selected($this->get_config('content_source'), 'manual'); ?>>Manual Selection (future)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Content Category Slug</th>
                        <td>
                            <input type="text" name="flosc_content_category" value="<?php echo esc_attr($this->get_config('content_category')); ?>" class="regular-text" placeholder="lesaep-lessons">
                            <p class="description">WordPress category slug containing paid content</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Content Display Mode</th>
                        <td>
                            <select name="flosc_content_display_mode">
                                <option value="inline" <?php selected($this->get_config('content_display_mode'), 'inline'); ?>>Inline (render full content in chat)</option>
                                <option value="list" <?php selected($this->get_config('content_display_mode'), 'list'); ?>>List (show titles, expand on click)</option>
                            </select>
                            <p class="description">How content appears after purchase</p>
                        </td>
                    </tr>
                    <tr>
                        <th>BuddyBoss Paid Group ID</th>
                        <td>
                            <input type="number" name="flosc_paid_group_id" value="<?php echo esc_attr($this->get_config('paid_group_id')); ?>">
                            <p class="description">Optional: Auto-add purchasers to this BuddyBoss group</p>
                        </td>
                    </tr>
                </table>

                <!-- Chat Bot Settings -->
                <h2 class="title">Chat Bot Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Bot Name</th>
                        <td>
                            <input type="text" name="flosc_chat_bot_name" value="<?php echo esc_attr($this->get_config('chat_bot_name')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Bot Avatar (emoji)</th>
                        <td>
                            <input type="text" name="flosc_chat_bot_avatar" value="<?php echo esc_attr($this->get_config('chat_bot_avatar')); ?>" style="width:60px">
                        </td>
                    </tr>
                </table>

                <?php submit_button('💾 Save FLOSC Settings'); ?>
            </form>

            <hr style="margin: 40px 0;">

            <!-- Usage Instructions -->
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                <h2>📖 Usage</h2>
                <h3>1. Add Shortcode to Page</h3>
                <p><code>[flosc]</code> or <code>[flosc campaign="lesaep"]</code></p>

                <h3>2. Test Backend Options Tonight</h3>
                <ul>
                    <li><strong>Mock Mode:</strong> Set Backend Type = "Mock", save, test chat flow</li>
                    <li><strong>FastAPI:</strong> Deploy your backend, add URL, save, test</li>
                    <li><strong>OpenAI:</strong> Add API key, set model to "gpt-4", save, test</li>
                </ul>

                <h3>3. Test Purchase Flow</h3>
                <ol>
                    <li>Create WooCommerce product (any price)</li>
                    <li>Copy product ID to "WooCommerce Product ID" above</li>
                    <li>Save settings</li>
                    <li>Complete quiz → login → click "Buy" → purchase</li>
                    <li>Return to chat → should show paid content automatically</li>
                </ol>

                <h3>4. Documentation</h3>
                <p>See <code>docs/TESTING.md</code> for step-by-step test scenarios</p>
                <p>See <code>docs/CONFIGURATION.md</code> for backend setup guides</p>
            </div>

        </div>

        <script>
        // Show/hide backend fields based on selected type
        document.getElementById('flosc_backend_type').addEventListener('change', function() {
            const selected = this.value;
            document.querySelectorAll('.backend-field').forEach(field => {
                const backends = field.dataset.backend.split(' ');
                field.style.display = backends.includes(selected) ? 'table-row' : 'none';
            });
        });
        // Trigger on load
        document.getElementById('flosc_backend_type').dispatchEvent(new Event('change'));
        </script>

        <style>
        .form-table th { width: 200px; font-weight: 600; }
        .form-table td { padding: 15px 10px; }
        .form-table p.description { margin-top: 5px; color: #666; font-size: 13px; }
        h2.title { border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-top: 30px; }
        </style>
        <?php
    }
}

// Initialize plugin
new FLOSC_Framework();
