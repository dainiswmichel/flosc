<?php
/**
 * =============================================================================
 * FLOSC FRAMEWORK v2.0.4
 * =============================================================================
 * 
 * Plugin Name: FLOSC Framework
 * Description: Freeline-Login-Offer-Sale-Content conversational sales funnel
 * Version: 2.0.5
 * Author: Dainis W. Michel
 * 
 * =============================================================================
 * WHAT IS FLOSC?
 * =============================================================================
 * 
 * FLOSC is a 5-stage conversational sales funnel framework:
 * 
 *   F = FREELINE   → Hook the user with a free quiz/assessment
 *   L = LOGIN      → Gate results behind login (BuddyBoss social login)
 *   O = OFFER      → Show results + one FREE lesson they NEED
 *   S = SALE       → Present paid offer with countdown timer
 *   C = CONTENT    → Deliver purchased content in-chat
 * 
 * =============================================================================
 * v2.0.4 NEW FEATURES
 * =============================================================================
 * 
 * 1. DOMAIN LANDING MODE
 *    - lesaep.com renders ONLY the chatbot (no WordPress page needed)
 *    - Configure which domains trigger landing mode
 *    - Bot appears at root path (/) without page-as-domain plugins
 * 
 * 2. TEXT QUIZ MODE (for testing)
 *    - Quiz items: "1 2 3 4 5 6 7 8 9 10" (configurable)
 *    - User types: "1 2 3" → Score: 3/10
 *    - Identifies WHICH items are missing → needed lessons
 *    - Perfect precursor for SAE 44-47 phoneme model
 * 
 * 3. ITEM-LEVEL SCORING
 *    - Tracks which quiz items were correct vs missed
 *    - Maps missed items to specific lessons
 *    - Example: missed "4 5 6 7 8 9 10" → needs lessons 4-10
 * 
 * 4. SMART FREE LESSON
 *    - Gives user ONE free lesson from their MISSING set
 *    - Not random - personalized to their needs
 *    - Stores which free lesson was delivered
 * 
 * 5. STRIPE CHECKOUT (No WooCommerce!)
 *    - Direct Stripe Checkout Session integration
 *    - Webhook receives payment confirmation
 *    - Unlocks user via user_meta (no WooCommerce dependency)
 *    - PayPal and ClickBank placeholders included
 * 
 * 6. BUDDYBOSS GROUP ASSIGNMENT
 *    - After quiz: assign to "Quiz Taken" group
 *    - After purchase: assign to "Paid Students" group
 * 
 * =============================================================================
 * ARCHITECTURE NOTES
 * =============================================================================
 * 
 * Standard American English (SAE) has 44-47 phonemes.
 * The 1-10 quiz model is a PERFECT testing precursor:
 * 
 *   Quiz Items (now):     1, 2, 3, 4, 5, 6, 7, 8, 9, 10
 *   Quiz Items (future):  /æ/, /ɛ/, /ɪ/, /ɑ/, /ʌ/, /ʊ/, /u/, /oʊ/, /aɪ/, /aʊ/
 * 
 * Each quiz item maps to exactly one lesson.
 * The framework doesn't care what the items ARE - just that:
 *   - There's a canonical list of items
 *   - User response identifies which items are correct
 *   - Missing items determine which lessons are needed
 * 
 * =============================================================================
 * FILE STRUCTURE
 * =============================================================================
 * 
 * flosc/
 * ├── flosc.php                          ← Main plugin (this file)
 * ├── includes/
 * │   ├── class-quiz-processor.php       ← Text/audio quiz scoring
 * │   ├── class-lesson-mapper.php        ← Quiz item → lesson mapping
 * │   ├── class-stripe-checkout.php      ← Stripe payment integration
 * │   ├── class-payment-gateway.php      ← Abstract payment gateway
 * │   ├── class-content-renderer.php     ← In-chat content display
 * │   └── class-session-manager.php      ← State persistence
 * ├── assets/
 * │   ├── js/flosc.js                    ← Frontend state machine
 * │   └── css/flosc.css                  ← Chat UI styles
 * └── templates/
 *     └── landing.php                    ← Fullpage landing template
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

/* =============================================================================
 * SECTION 1: PLUGIN CONSTANTS AND INCLUDES
 * =============================================================================
 * 
 * Define version, paths, and load all component classes.
 * Each class handles ONE responsibility (Single Responsibility Principle).
 */

define('FLOSC_VERSION', '2.0.5');
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load component classes
require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-processor.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-mapper.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-stripe-checkout.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-payment-gateway.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-content-renderer.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';


/* =============================================================================
 * SECTION 2: MAIN PLUGIN CLASS
 * =============================================================================
 */

class FLOSC_Framework {

    /* =========================================================================
     * SECTION 2.1: DEFAULT CONFIGURATION
     * =========================================================================
     * 
     * All settings with sensible defaults.
     * These are used if nothing is configured in WP Admin.
     * Organized by FLOSC stage for clarity.
     */
    
    private $default_config = [
        
        // =====================================================================
        // DOMAIN LANDING MODE (v2.0.4)
        // =====================================================================
        // When enabled, specified domains show ONLY the chatbot at root path
        // Example: lesaep.com → renders chat without WordPress page
        
        'enable_landing_mode'    => 'no',
        'landing_domains'        => '',  // Comma-separated: lesaep.com,www.lesaep.com
        
        // =====================================================================
        // F - FREELINE (Quiz/Hook Stage)
        // =====================================================================
        // The initial interaction that hooks users and collects assessment data.
        // This is where we capture their attention and gather diagnostic info.
        
        'welcome_message'        => 'Hi! 👋 Ready for a quick pronunciation check?',
        'quiz_prompt'            => 'Type the numbers you hear clearly:',
        
        // Quiz Configuration
        // For testing: "1 2 3 4 5 6 7 8 9 10"
        // For SAE: "/æ/ /ɛ/ /ɪ/ /ɑ/ /ʌ/ /ʊ/ /u/ /oʊ/ /aɪ/ /aʊ/"
        'quiz_type'              => 'text',  // 'text' or 'audio'
        'quiz_items'             => "1\n2\n3\n4\n5\n6\n7\n8\n9\n10",  // One per line
        'quiz_display_text'      => '1, 2, 3, 4, 5, 6, 7, 8, 9, 10',  // What user sees
        
        // Referral Tracking
        'enable_referral_tracking' => 'yes',
        'referral_cookie_days'     => 30,
        'referral_greeting'        => 'I see {ref} sent you! Welcome! 🎉',
        
        // Cooldown After 3×No
        'enable_cooldown'          => 'yes',
        'cooldown_threshold'       => 3,
        'cooldown_duration_hours'  => 24,
        'cooldown_message'         => 'Take some time to think it over. Come back in {hours} hours!',
        
        // =====================================================================
        // L - LOGIN GATE (Authentication Stage)
        // =====================================================================
        // User must log in to see their results.
        // Uses BuddyBoss social login (Google, Facebook, etc.)
        
        'login_prompt'           => 'Create your free account to see your personalized results!',
        'enable_social_login'    => 'yes',

        // =====================================================================
        // IVR vs AI MODE (v2.0.5)
        // =====================================================================
        // OFF (default): deterministic scripted responses ("simple IVR mode")
        // ON: chat copy is generated via an AI backend, proxied server-side.
        //
        // IMPORTANT:
        // - API keys should NEVER be exposed to the browser.
        // - This plugin therefore calls an AI backend URL from PHP (server-side).
        // - For zero ongoing cost, point this at YOUR OWN droplet endpoint.
        //
        // Minimal expected backend contract (your choice of stack/model):
        // POST {prompt, state, context, sessionId} -> {message}

        'enable_ai_mode'         => 'no',
        'ai_backend_url'         => '',   // Example: https://api.lesaep.com/flosc-ai
        'ai_backend_bearer'      => '',   // Optional Authorization: Bearer ...
        'ai_system_prompt'       => 'You are a concise, sales-funnel chatbot. Keep responses under 60 words. Stay within the FLOSC flow.',
        'ai_timeout_seconds'     => 15,
        'ai_max_chars'           => 800,
        
        // BuddyBoss Group Assignment (after quiz)
        'quiz_taken_group_id'    => '',  // Add user to this group after completing quiz
        
        // =====================================================================
        // O - OFFER (Results + Free Content Stage)
        // =====================================================================
        // Show user their score, which items they missed, and ONE free lesson.
        // The free lesson is chosen from their MISSING items (personalized).
        
        'results_message'        => 'You got {score} out of {total} correct!',
        'items_correct_message'  => '✅ You nailed: {correct_items}',
        'items_missed_message'   => '📚 You need practice with: {missed_items}',
        
        // Free Content Selection
        // The system picks ONE lesson from the user's missed items
        'free_content_message'   => 'Here\'s a FREE lesson to get you started:',
        'free_lesson_selection'  => 'first_missed',  // 'first_missed' or 'random_missed'
        
        // Offer Expiry Countdown
        'enable_offer_expiry'    => 'yes',
        'offer_expiry_minutes'   => 15,
        'offer_expired_message'  => 'This special offer has expired. Refresh to start again.',
        
        // =====================================================================
        // S - SALE (Payment Stage)
        // =====================================================================
        // Present the paid offer and handle checkout.
        // Supports Stripe (primary), PayPal, and ClickBank.
        
        'offer_headline'         => '🎓 Unlock Your Complete Course',
        'offer_description'      => 'Get personalized lessons for every sound you need to master.',
        'offer_price'            => 144,
        'offer_currency'         => '€',
        'offer_currency_symbol'  => '€',
        
        // Payment Gateway Selection
        'payment_gateway'        => 'stripe',  // 'stripe', 'paypal', 'clickbank', 'manual'
        
        // Stripe Configuration
        'stripe_mode'            => 'test',  // 'test' or 'live'
        'stripe_test_pk'         => '',      // Publishable key (test)
        'stripe_test_sk'         => '',      // Secret key (test)
        'stripe_live_pk'         => '',      // Publishable key (live)
        'stripe_live_sk'         => '',      // Secret key (live)
        'stripe_webhook_secret'  => '',      // Webhook signing secret
        
        // PayPal Configuration (placeholder for future)
        'paypal_mode'            => 'sandbox',
        'paypal_client_id'       => '',
        'paypal_client_secret'   => '',
        
        // ClickBank Configuration (placeholder for future)
        // See SECTION: CLICKBANK INTEGRATION PLACEHOLDER
        'clickbank_vendor'       => '',
        'clickbank_secret_key'   => '',
        'clickbank_product_id'   => '',
        
        // Manual/Testing Mode
        'manual_checkout_url'    => '',  // Fallback URL if no gateway configured
        
        // =====================================================================
        // C - CONTENT (Post-Purchase Delivery Stage)
        // =====================================================================
        // Deliver purchased content in-chat.
        // Shows ONLY the lessons the user needs (based on quiz results).
        
        'content_category'       => 'flosc-lessons',  // WordPress category slug
        'content_display_mode'   => 'list',           // 'inline' or 'list'
        
        // BuddyBoss Group Assignment (after purchase)
        'paid_group_id'          => '',  // Add user to this group after purchase
        
        // =====================================================================
        // CHAT BOT APPEARANCE
        // =====================================================================
        
        'chat_bot_name'          => 'Coach',
        'chat_bot_avatar'        => '🎯',
    ];


    /* =========================================================================
     * SECTION 2.2: CONSTRUCTOR AND HOOKS
     * =========================================================================
     * 
     * Initialize the plugin and register all WordPress hooks.
     */
    
    public function __construct() {
        
        // ---------------------------------------------------------------------
        // DOMAIN LANDING MODE HOOK (runs early, before WordPress loads theme)
        // ---------------------------------------------------------------------
        // This intercepts requests to configured domains and renders
        // the chat-only landing page without loading a WordPress page.
        
        add_action('template_redirect', [$this, 'maybe_render_landing_mode'], 1);
        
        // ---------------------------------------------------------------------
        // SHORTCODE REGISTRATION
        // ---------------------------------------------------------------------
        // [flosc] - Renders the chat interface on any page
        // [flosc fullpage="yes"] - Renders fullpage chat (no header/footer)
        
        add_shortcode('flosc', [$this, 'render_chat']);
        
        // ---------------------------------------------------------------------
        // REST API ROUTES
        // ---------------------------------------------------------------------
        // All chat-backend communication uses these endpoints
        
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        
        // ---------------------------------------------------------------------
        // FRONTEND ASSETS
        // ---------------------------------------------------------------------
        // CSS and JavaScript only load on pages with [flosc] shortcode
        
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // ---------------------------------------------------------------------
        // ADMIN INTERFACE
        // ---------------------------------------------------------------------
        // Settings page organized by FLOSC stages
        
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // ---------------------------------------------------------------------
        // COMPONENT INITIALIZATION
        // ---------------------------------------------------------------------
        // Each component class handles one responsibility
        
        new FLOSC_Session_Manager($this);
        new FLOSC_Stripe_Checkout($this);
    }


    /* =========================================================================
     * SECTION 2.3: CONFIGURATION GETTER
     * =========================================================================
     * 
     * Central method to retrieve configuration values.
     * Falls back to defaults if not configured in WP Admin.
     */
    
    public function get_config($key) {
        $value = get_option('flosc_' . $key);
        
        // Return saved value if it exists and is not empty
        if ($value !== false && $value !== '') {
            return $value;
        }
        
        // Fall back to default
        return $this->default_config[$key] ?? '';
    }


    /* =========================================================================
     * SECTION 2.4: DOMAIN LANDING MODE
     * =========================================================================
     * 
     * v2.0.4 NEW FEATURE
     * 
     * When enabled, specified domains (e.g., lesaep.com) render ONLY the
     * chatbot at the root path (/). No WordPress page required.
     * 
     * This solves the problem of needing a "page as domain" plugin.
     * 
     * How it works:
     * 1. Check if landing mode is enabled
     * 2. Check if current domain matches configured domains
     * 3. Check if request is for root path (/)
     * 4. If all true, render landing template and exit
     */
    
    public function maybe_render_landing_mode() {
        
        // Check if landing mode is enabled
        if ($this->get_config('enable_landing_mode') !== 'yes') {
            return;
        }
        
        // Get configured landing domains
        $domains_raw = $this->get_config('landing_domains');
        if (empty($domains_raw)) {
            return;
        }
        
        // Parse domain list (comma-separated)
        $domains = array_map('trim', explode(',', $domains_raw));
        $domains = array_filter($domains);
        
        if (empty($domains)) {
            return;
        }
        
        // Get current domain
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        $current_domain = strtolower(preg_replace('/:\d+$/', '', $current_domain)); // Remove port
        
        // Check if current domain matches
        $domain_matches = false;
        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            if ($current_domain === $domain) {
                $domain_matches = true;
                break;
            }
        }
        
        if (!$domain_matches) {
            return;
        }
        
        // Check if request is for root path
        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($request_uri, PHP_URL_PATH);
        
        // Only trigger for root path or /index.php
        if ($path !== '/' && $path !== '/index.php' && $path !== '') {
            return;
        }
        
        // =====================================================================
        // RENDER LANDING PAGE
        // =====================================================================
        // Load the minimal landing template that contains only the chat UI
        
        // Set up WordPress query vars so plugins work correctly
        global $wp_query;
        $wp_query->is_home = false;
        $wp_query->is_page = true;
        
        // Load the landing template
        $template = FLOSC_PLUGIN_DIR . 'templates/landing.php';
        
        if (file_exists($template)) {
            include $template;
            exit;
        }
        
        // Fallback: render inline if template missing
        $this->render_landing_inline();
        exit;
    }
    
    
    /**
     * Fallback inline landing page renderer
     * Used if templates/landing.php is missing
     */
    private function render_landing_inline() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($this->get_config('chat_bot_name')); ?></title>
            <?php wp_head(); ?>
            <style>
                /* Fullpage chat styles */
                html, body {
                    margin: 0;
                    padding: 0;
                    height: 100%;
                    overflow: hidden;
                }
                #flosc-container {
                    max-width: 100% !important;
                    margin: 0 !important;
                    height: 100vh;
                }
                .flosc-chat-window {
                    height: 100vh !important;
                    border-radius: 0 !important;
                    border: none !important;
                }
                .flosc-messages {
                    height: calc(100vh - 200px) !important;
                }
            </style>
        </head>
        <body class="flosc-landing-page">
            <?php echo $this->render_chat(['fullpage' => 'yes']); ?>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }


    /* =========================================================================
     * SECTION 2.5: CHAT INTERFACE RENDERER
     * =========================================================================
     * 
     * Renders the chat UI HTML.
     * Used by both shortcode and landing mode.
     * 
     * Shortcode attributes:
     *   [flosc]                 - Standard chat embed
     *   [flosc fullpage="yes"]  - Fullpage chat (no WordPress chrome)
     *   [flosc campaign="xyz"]  - Named campaign (future multi-funnel support)
     */
    
    public function render_chat($atts = []) {
        $atts = shortcode_atts([
            'fullpage' => 'no',
            'campaign' => 'default',
        ], $atts);
        
        $user = wp_get_current_user();
        $has_access = $this->user_has_paid_access($user->ID);
        
        // Add fullpage class if needed
        $container_class = 'flosc-container';
        if ($atts['fullpage'] === 'yes') {
            $container_class .= ' flosc-fullpage';
        }
        
        ob_start();
        ?>
        <div id="flosc-container" 
             class="<?php echo esc_attr($container_class); ?>"
             data-campaign="<?php echo esc_attr($atts['campaign']); ?>">
            
            <div class="flosc-chat-window">
                
                <!-- =========================================================
                     CHAT HEADER
                     Shows bot avatar, name, and status indicator
                     ========================================================= -->
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
                
                <!-- =========================================================
                     MESSAGE AREA
                     All chat messages appear here (bot and user)
                     Content is also rendered here (inline mode)
                     ========================================================= -->
                <div class="flosc-messages" id="floscMessages"></div>
                
                <!-- =========================================================
                     INPUT AREA
                     Contains all interactive elements:
                     - Typing indicator
                     - Quick reply buttons
                     - Text input (for text quiz mode)
                     - Audio recorder (for audio quiz mode)
                     - Social login buttons
                     ========================================================= -->
                <div class="flosc-input-area">
                    
                    <!-- Typing Indicator (animated dots) -->
                    <div class="flosc-typing hidden" id="floscTyping">
                        <span></span><span></span><span></span>
                    </div>
                    
                    <!-- Quick Reply Buttons -->
                    <div class="flosc-replies" id="floscReplies"></div>
                    
                    <!-- Text Input (v2.0.4: for text quiz mode) -->
                    <div class="flosc-text-input hidden" id="floscTextInput">
                        <div class="flosc-quiz-display" id="floscQuizDisplay"></div>
                        <input type="text" 
                               id="floscQuizInput" 
                               class="flosc-quiz-field"
                               placeholder="Type your answer..."
                               autocomplete="off">
                        <button type="button" id="floscSubmitBtn" class="flosc-submit-btn">
                            Submit ✓
                        </button>
                    </div>
                    
                    <!-- Audio Recorder (for audio quiz mode) -->
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
                    
                    <!-- Countdown Timer (v2.0.3) -->
                    <div class="flosc-countdown hidden" id="floscCountdown">
                        <span class="flosc-countdown-label">Offer expires in:</span>
                        <span class="flosc-countdown-time" id="floscCountdownTime">15:00</span>
                    </div>
                    
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }


    /* =========================================================================
     * SECTION 2.6: ASSET ENQUEUING
     * =========================================================================
     * 
     * Load CSS and JavaScript only on pages that need them.
     * Pass all configuration to JavaScript via wp_localize_script.
     */
    
    public function enqueue_assets() {
        global $post;
        
        // Only load on pages with [flosc] shortcode
        // OR if we're in landing mode (checked separately)
        if (!$post || !has_shortcode($post->post_content, 'flosc')) {
            // Check if this is a landing mode request
            if ($this->get_config('enable_landing_mode') !== 'yes') {
                return;
            }
        }
        
        // ---------------------------------------------------------------------
        // STYLES
        // ---------------------------------------------------------------------
        wp_enqueue_style(
            'flosc',
            FLOSC_PLUGIN_URL . 'assets/css/flosc.css',
            [],
            FLOSC_VERSION
        );
        
        // ---------------------------------------------------------------------
        // JAVASCRIPT
        // ---------------------------------------------------------------------
        wp_enqueue_script(
            'flosc',
            FLOSC_PLUGIN_URL . 'assets/js/flosc.js',
            [],
            FLOSC_VERSION,
            true  // Load in footer
        );
        
        // ---------------------------------------------------------------------
        // PASS CONFIGURATION TO JAVASCRIPT
        // ---------------------------------------------------------------------
        // All settings needed by the frontend state machine
        
        $user = wp_get_current_user();
        $quiz_items = $this->get_quiz_items_array();
        
        wp_localize_script('flosc', 'FLOSC_CONFIG', [
            
            // -----------------------------------------------------------------
            // URLs
            // -----------------------------------------------------------------
            'restUrl'       => rest_url('flosc/v1'),
            'loginUrl'      => wp_login_url(get_permalink()),
            'siteUrl'       => home_url('/'),
            
            // -----------------------------------------------------------------
            // User State
            // -----------------------------------------------------------------
            'userId'        => $user->ID,
            'isLoggedIn'    => is_user_logged_in(),
            'hasPaidAccess' => $this->user_has_paid_access($user->ID),
            'userName'      => $user->display_name,
            'userEmail'     => $user->user_email,
            
            // -----------------------------------------------------------------
            // F - FREELINE Configuration
            // -----------------------------------------------------------------
            'welcomeMessage'    => $this->get_config('welcome_message'),
            'quizPrompt'        => $this->get_config('quiz_prompt'),
            'quizType'          => $this->get_config('quiz_type'),
            'quizItems'         => $quiz_items,
            'quizDisplayText'   => $this->get_config('quiz_display_text'),
            'quizItemCount'     => count($quiz_items),
            
            // Referral
            'enableReferralTracking' => $this->get_config('enable_referral_tracking') === 'yes',
            'referralCode'           => $this->get_referral_code(),
            'referralGreeting'       => $this->get_config('referral_greeting'),
            
            // Cooldown
            'enableCooldown'         => $this->get_config('enable_cooldown') === 'yes',
            'cooldownThreshold'      => (int)$this->get_config('cooldown_threshold'),
            'cooldownDurationHours'  => (int)$this->get_config('cooldown_duration_hours'),
            'cooldownMessage'        => $this->get_config('cooldown_message'),
            'isCooledDown'           => $this->check_cooldown(),
            'cooldownEndsAt'         => $this->get_cooldown_end_time(),
            
            // -----------------------------------------------------------------
            // L - LOGIN Configuration
            // -----------------------------------------------------------------
            'loginPrompt'        => $this->get_config('login_prompt'),
            'enableSocialLogin'  => $this->get_config('enable_social_login') === 'yes',

            // -----------------------------------------------------------------
            // IVR vs AI Mode (v2.0.5)
            // -----------------------------------------------------------------
            'enableAIMode'       => $this->get_config('enable_ai_mode') === 'yes',
            // NOTE: AI backend URL is NOT exposed to the browser by default.
            // Frontend calls our WP REST endpoint (/ai) which proxies server-side.
            
            // -----------------------------------------------------------------
            // O - OFFER Configuration
            // -----------------------------------------------------------------
            'resultsMessage'       => $this->get_config('results_message'),
            'itemsCorrectMessage'  => $this->get_config('items_correct_message'),
            'itemsMissedMessage'   => $this->get_config('items_missed_message'),
            'freeContentMessage'   => $this->get_config('free_content_message'),
            
            'enableOfferExpiry'    => $this->get_config('enable_offer_expiry') === 'yes',
            'offerExpiryMinutes'   => (int)$this->get_config('offer_expiry_minutes'),
            'offerExpiredMessage'  => $this->get_config('offer_expired_message'),
            
            // -----------------------------------------------------------------
            // S - SALE Configuration
            // -----------------------------------------------------------------
            'offerHeadline'     => $this->get_config('offer_headline'),
            'offerDescription'  => $this->get_config('offer_description'),
            'offerPrice'        => (int)$this->get_config('offer_price'),
            'offerCurrency'     => $this->get_config('offer_currency'),
            'offerCurrencySymbol' => $this->get_config('offer_currency_symbol'),
            
            'paymentGateway'    => $this->get_config('payment_gateway'),
            
            // -----------------------------------------------------------------
            // C - CONTENT Configuration
            // -----------------------------------------------------------------
            'contentDisplayMode' => $this->get_config('content_display_mode'),
            
            // -----------------------------------------------------------------
            // Bot Appearance
            // -----------------------------------------------------------------
            'botName'   => $this->get_config('chat_bot_name'),
            'botAvatar' => $this->get_config('chat_bot_avatar'),
            
            // -----------------------------------------------------------------
            // Security
            // -----------------------------------------------------------------
            'nonce' => wp_create_nonce('flosc_rest'),
        ]);
    }
    
    
    /**
     * Parse quiz items from config (one per line)
     * 
     * @return array List of quiz items
     */
    private function get_quiz_items_array() {
        $raw = $this->get_config('quiz_items');
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = array_values(array_filter(array_map('trim', $lines)));
        
        // Default to 1-10 if empty
        if (empty($items)) {
            $items = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        }
        
        return $items;
    }


    /* =========================================================================
     * SECTION 2.7: REST API ROUTES
     * =========================================================================
     * 
     * All communication between chat UI and backend happens via REST API.
     * Each endpoint handles one specific action.
     */
    
    public function register_rest_routes() {
        
        // -----------------------------------------------------------------
        // STATUS ENDPOINT
        // Returns current user login/payment status
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/status', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_get_status'],
            'permission_callback' => '__return_true',
        ]);

        // -----------------------------------------------------------------
        // AI CHAT COPY ENDPOINT (v2.0.5)
        // Frontend calls this when AI mode is enabled.
        // WordPress proxies the request to your configured AI backend.
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/ai', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_ai_chat'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // QUIZ PROCESSING ENDPOINT (v2.0.4: handles text and audio)
        // Scores the quiz and returns which items were correct/missed
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/process-quiz', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_process_quiz'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // FREE CONTENT ENDPOINT (v2.0.4: smart selection)
        // Returns ONE lesson from the user's missed items
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/free-content', [
            'methods'  => 'POST',  // POST because we need quiz results
            'callback' => [$this, 'rest_get_free_content'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // PAID CONTENT ENDPOINT
        // Returns all lessons the user needs (requires paid access)
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/content', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_get_content'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
        
        // -----------------------------------------------------------------
        // SOCIAL LOGIN BUTTONS ENDPOINT
        // Returns BuddyBoss social login HTML
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/social-login', [
            'methods'  => 'GET',
            'callback' => [$this, 'rest_get_social_login'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // COOLDOWN TRACKING ENDPOINT
        // Tracks "no" responses and applies cooldown
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/track-no', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_track_no'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // REFERRAL SAVE ENDPOINT
        // Saves referral code to user account
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/save-referral', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_save_referral'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
        
        // -----------------------------------------------------------------
        // STRIPE CHECKOUT SESSION ENDPOINT (v2.0.4)
        // Creates Stripe Checkout Session and returns URL
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/create-checkout', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_create_checkout'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
        
        // -----------------------------------------------------------------
        // STRIPE WEBHOOK ENDPOINT (v2.0.4)
        // Receives payment confirmations from Stripe
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/stripe-webhook', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_stripe_webhook'],
            'permission_callback' => '__return_true',  // Stripe can't send auth
        ]);
        
        // -----------------------------------------------------------------
        // PAYPAL WEBHOOK ENDPOINT (placeholder)
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/paypal-webhook', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_paypal_webhook'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // CLICKBANK IPN ENDPOINT (placeholder)
        // See SECTION: CLICKBANK INTEGRATION PLACEHOLDER
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/clickbank-ipn', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_clickbank_ipn'],
            'permission_callback' => '__return_true',
        ]);
        
        // -----------------------------------------------------------------
        // SAVE QUIZ RESULTS ENDPOINT
        // Stores quiz results to user meta after login
        // -----------------------------------------------------------------
        register_rest_route('flosc/v1', '/save-results', [
            'methods'  => 'POST',
            'callback' => [$this, 'rest_save_results'],
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ]);
    }


    /* =========================================================================
     * SECTION 2.8: REST API HANDLERS
     * =========================================================================
     */
    
    /**
     * GET /flosc/v1/status
     * Returns current user status
     */
    public function rest_get_status() {
        $user = wp_get_current_user();
        
        // Get stored quiz results if user is logged in
        $quiz_results = null;
        $needed_lessons = [];
        
        if ($user->ID) {
            $quiz_results = get_user_meta($user->ID, 'flosc_quiz_results', true);
            $needed_lessons = get_user_meta($user->ID, 'flosc_needed_lessons', true) ?: [];
        }
        
        return new WP_REST_Response([
            'logged_in'       => is_user_logged_in(),
            'user_id'         => $user->ID,
            'display_name'    => $user->display_name,
            'has_paid_access' => $this->user_has_paid_access($user->ID),
            'quiz_results'    => $quiz_results,
            'needed_lessons'  => $needed_lessons,
        ]);
    }


    /**
     * POST /flosc/v1/ai
     *
     * v2.0.5: Optional AI chat-copy generation.
     *
     * Design goals:
     * - Keep the funnel functional with AI OFF (simple IVR).
     * - When AI is enabled, NEVER expose keys to the browser.
     * - WordPress proxies to a backend URL you control.
     * - Lightweight rate limiting so anonymous traffic can't burn your quota.
     *
     * Expected request JSON:
     *   { prompt: string, state: string, context: object, sessionId: string }
     * Expected backend response JSON:
     *   { message: string }
     */
    public function rest_ai_chat($request) {
        // Hard OFF: deterministic IVR only
        if ($this->get_config('enable_ai_mode') !== 'yes') {
            return new WP_REST_Response([
                'mode'    => 'ivr',
                'message' => '',
            ]);
        }

        $backend_url = trim((string)$this->get_config('ai_backend_url'));
        if (empty($backend_url)) {
            return new WP_REST_Response([
                'error'   => 'AI backend URL not configured',
                'message' => '',
            ], 503);
        }

        // Basic rate limit (anonymous safe guard)
        if (!$this->ai_rate_limit_allow()) {
            return new WP_REST_Response([
                'error'   => 'Rate limited',
                'message' => 'One moment — please try again.',
            ], 429);
        }

        $prompt = (string)($request->get_param('prompt') ?? '');
        $state  = (string)($request->get_param('state') ?? 'INIT');
        $ctx    = $request->get_param('context') ?? [];
        $session_id = (string)($request->get_param('sessionId') ?? '');

        // Sanitize aggressively (do not allow HTML)
        $prompt = wp_strip_all_tags($prompt);
        $state  = sanitize_text_field($state);
        if (!is_array($ctx)) $ctx = [];

        $payload = [
            'system'    => (string)$this->get_config('ai_system_prompt'),
            'prompt'    => $prompt,
            'state'     => $state,
            'context'   => $ctx,
            'sessionId' => $session_id,
            'site'      => [
                'name' => get_bloginfo('name'),
                'url'  => home_url('/'),
            ],
        ];

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $bearer = trim((string)$this->get_config('ai_backend_bearer'));
        if (!empty($bearer)) {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }

        $timeout = (int)$this->get_config('ai_timeout_seconds');
        if ($timeout < 3) $timeout = 3;
        if ($timeout > 60) $timeout = 60;

        $resp = wp_remote_post($backend_url, [
            'headers' => $headers,
            'body'    => wp_json_encode($payload),
            'timeout' => $timeout,
        ]);

        if (is_wp_error($resp)) {
            return new WP_REST_Response([
                'error'   => 'AI backend request failed',
                'message' => '',
            ], 502);
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($json) || empty($json['message'])) {
            return new WP_REST_Response([
                'error'   => 'AI backend invalid response',
                'message' => '',
            ], 502);
        }

        $message = wp_strip_all_tags((string)$json['message']);

        // Enforce length cap
        $max_chars = (int)$this->get_config('ai_max_chars');
        if ($max_chars < 200) $max_chars = 200;
        if ($max_chars > 5000) $max_chars = 5000;
        if (mb_strlen($message, 'UTF-8') > $max_chars) {
            $message = mb_substr($message, 0, $max_chars, 'UTF-8');
        }

        return new WP_REST_Response([
            'mode'    => 'ai',
            'message' => $message,
        ]);
    }
    
    
    /**
     * POST /flosc/v1/process-quiz
     * 
     * v2.0.4: Handles both text and audio quiz modes
     * 
     * Text mode:
     *   - Receives user's typed answer
     *   - Compares to expected quiz items
     *   - Returns which items were correct vs missed
     * 
     * Audio mode:
     *   - Delegates to FLOSC_Quiz_Processor
     *   - Uses Whisper API or FastAPI backend
     */
    public function rest_process_quiz($request) {
        $quiz_type = $this->get_config('quiz_type');
        $quiz_items = $this->get_quiz_items_array();
        
        if ($quiz_type === 'text') {
            // =====================================================================
            // TEXT QUIZ MODE (v2.0.4)
            // =====================================================================
            // User typed their answer, we compare to expected items
            
            $user_answer = $request->get_param('answer') ?? '';
            $user_answer = sanitize_text_field($user_answer);
            
            // Parse user's answer into items
            // Handles various formats: "1 2 3", "1, 2, 3", "1,2,3"
            $user_items = preg_split('/[\s,]+/', trim($user_answer));
            $user_items = array_values(array_filter(array_map('trim', $user_items)));
            
            // Compare to expected items
            $correct_items = [];
            $missed_items = [];
            
            foreach ($quiz_items as $item) {
                if (in_array($item, $user_items, true)) {
                    $correct_items[] = $item;
                } else {
                    $missed_items[] = $item;
                }
            }
            
            // Calculate score
            $score = count($correct_items);
            $total = count($quiz_items);
            $percentage = $total > 0 ? round(($score / $total) * 100) : 0;
            
            // Log for debugging
            error_log("FLOSC Quiz: User answered '{$user_answer}', score: {$score}/{$total}");
            error_log("FLOSC Quiz: Correct: " . implode(', ', $correct_items));
            error_log("FLOSC Quiz: Missed: " . implode(', ', $missed_items));
            
            return new WP_REST_Response([
                'success'       => true,
                'quiz_type'     => 'text',
                'score'         => $score,
                'total'         => $total,
                'percentage'    => $percentage,
                'correct_items' => $correct_items,
                'missed_items'  => $missed_items,
                'user_answer'   => $user_answer,
            ]);
            
        } else {
            // =====================================================================
            // AUDIO QUIZ MODE
            // =====================================================================
            // Delegate to quiz processor class
            
            $processor = new FLOSC_Quiz_Processor($this);
            return $processor->process_audio($request);
        }
    }
    
    
    /**
     * POST /flosc/v1/free-content
     * 
     * v2.0.4: Smart free lesson selection
     * 
     * Returns ONE lesson from the user's MISSED items, not random.
     * This ensures the free lesson is relevant to what they need.
     */
    public function rest_get_free_content($request) {
        $missed_items = $request->get_param('missed_items') ?? [];
        
        if (!is_array($missed_items)) {
            $missed_items = [];
        }
        
        // Use lesson mapper to find appropriate free lesson
        $mapper = new FLOSC_Lesson_Mapper($this);
        $lesson = $mapper->get_free_lesson_for_items($missed_items);
        
        if (!$lesson) {
            return new WP_REST_Response([
                'id'      => 0,
                'title'   => 'Free Lesson',
                'excerpt' => 'No matching lesson found. Check back soon!',
                'content' => '<p>Configure lessons in FLOSC settings.</p>',
            ]);
        }
        
        $renderer = new FLOSC_Content_Renderer($this);
        return $renderer->get_single_post($lesson->ID);
    }
    
    
    /**
     * GET /flosc/v1/content
     * 
     * Returns all lessons the user needs (based on their quiz results)
     * Requires paid access
     */
    public function rest_get_content($request) {
        $user_id = get_current_user_id();
        
        if (!$this->user_has_paid_access($user_id)) {
            return new WP_Error('forbidden', 'Paid access required', ['status' => 403]);
        }
        
        // Get user's needed lessons
        $needed_lessons = get_user_meta($user_id, 'flosc_needed_lessons', true) ?: [];
        
        $renderer = new FLOSC_Content_Renderer($this);
        return $renderer->get_lessons_for_items($needed_lessons);
    }
    
    
    /**
     * GET /flosc/v1/social-login
     * Returns BuddyBoss social login buttons HTML
     */
    public function rest_get_social_login() {
        $html = $this->render_buddyboss_social_buttons();
        
        return new WP_REST_Response([
            'html'      => $html,
            'available' => class_exists('BB_SSO'),
        ]);
    }
    
    
    /**
     * Render BuddyBoss social login buttons
     * COPIED FROM: korboc-survey plugin (keep in sync)
     */
    private function render_buddyboss_social_buttons() {
        if (!class_exists('BB_SSO') || !method_exists('BB_SSO', 'render_buttons_with_container')) {
            return '<p class="flosc-social-note">Social login requires BuddyBoss. <a href="' . esc_url(wp_login_url(get_permalink())) . '">Login with email</a></p>';
        }
        
        return BB_SSO::render_buttons_with_container([
            'label_type' => 'register',
            'style'      => 'icon',
        ]);
    }
    
    
    /**
     * POST /flosc/v1/track-no
     * Tracks "no" responses for cooldown feature
     */
    public function rest_track_no($request) {
        if ($this->get_config('enable_cooldown') !== 'yes') {
            return new WP_REST_Response(['cooldown_enabled' => false]);
        }
        
        $session_id = $this->get_or_create_session_id();
        $counter_key = 'flosc_no_count_' . $session_id;
        
        $count = (int)get_transient($counter_key);
        $count++;
        
        set_transient($counter_key, $count, DAY_IN_SECONDS);
        
        $threshold = (int)$this->get_config('cooldown_threshold');
        $cooldown_applied = false;
        $cooldown_until = null;
        
        if ($count >= $threshold) {
            $hours = (int)$this->get_config('cooldown_duration_hours');
            $cooldown_until = current_time('timestamp') + ($hours * HOUR_IN_SECONDS);
            
            set_transient('flosc_cooldown_' . $session_id, $cooldown_until, $hours * HOUR_IN_SECONDS);
            delete_transient($counter_key);
            $cooldown_applied = true;
            
            error_log("FLOSC: Cooldown applied for session {$session_id}");
        }
        
        return new WP_REST_Response([
            'no_count'         => $count,
            'threshold'        => $threshold,
            'cooldown_applied' => $cooldown_applied,
            'cooldown_until'   => $cooldown_until,
        ]);
    }
    
    
    /**
     * POST /flosc/v1/save-referral
     * Saves referral code to user meta
     */
    public function rest_save_referral($request) {
        if ($this->get_config('enable_referral_tracking') !== 'yes') {
            return new WP_REST_Response(['tracking_enabled' => false]);
        }
        
        $user_id = get_current_user_id();
        $referral_code = sanitize_text_field($request->get_param('referral_code') ?? '');
        
        if (!$referral_code) {
            return new WP_Error('missing_code', 'Referral code required', ['status' => 400]);
        }
        
        // Don't overwrite existing referral
        $existing = get_user_meta($user_id, 'flosc_referral_code', true);
        if ($existing) {
            return new WP_REST_Response([
                'already_set'   => true,
                'referral_code' => $existing,
            ]);
        }
        
        update_user_meta($user_id, 'flosc_referral_code', $referral_code);
        update_user_meta($user_id, 'flosc_referral_timestamp', current_time('timestamp'));
        
        error_log("FLOSC: Referral '{$referral_code}' saved for user {$user_id}");
        
        return new WP_REST_Response([
            'success'       => true,
            'referral_code' => $referral_code,
        ]);
    }
    
    
    /**
     * POST /flosc/v1/save-results
     * Saves quiz results to user meta after login
     */
    public function rest_save_results($request) {
        $user_id = get_current_user_id();
        
        $results = [
            'score'         => (int)$request->get_param('score'),
            'total'         => (int)$request->get_param('total'),
            'correct_items' => $request->get_param('correct_items') ?? [],
            'missed_items'  => $request->get_param('missed_items') ?? [],
            'timestamp'     => current_time('timestamp'),
        ];
        
        update_user_meta($user_id, 'flosc_quiz_results', $results);
        update_user_meta($user_id, 'flosc_needed_lessons', $results['missed_items']);
        
        // Assign to "quiz taken" group if configured
        $group_id = $this->get_config('quiz_taken_group_id');
        if ($group_id && function_exists('groups_join_group')) {
            groups_join_group($group_id, $user_id);
            error_log("FLOSC: User {$user_id} added to quiz-taken group {$group_id}");
        }
        
        return new WP_REST_Response([
            'success' => true,
            'results' => $results,
        ]);
    }
    
    
    /**
     * POST /flosc/v1/create-checkout
     * 
     * v2.0.4: Creates payment checkout session
     * Delegates to appropriate payment gateway
     */
    public function rest_create_checkout($request) {
        $gateway = $this->get_config('payment_gateway');
        
        switch ($gateway) {
            case 'stripe':
                $stripe = new FLOSC_Stripe_Checkout($this);
                return $stripe->create_checkout_session($request);
                
            case 'paypal':
                // PLACEHOLDER: PayPal checkout
                return new WP_Error('not_implemented', 'PayPal checkout coming soon', ['status' => 501]);
                
            case 'clickbank':
                // PLACEHOLDER: ClickBank hoplink
                // See SECTION: CLICKBANK INTEGRATION PLACEHOLDER
                return new WP_Error('not_implemented', 'ClickBank checkout coming soon', ['status' => 501]);
                
            case 'manual':
                $url = $this->get_config('manual_checkout_url');
                return new WP_REST_Response(['checkout_url' => $url]);
                
            default:
                return new WP_Error('no_gateway', 'No payment gateway configured', ['status' => 500]);
        }
    }
    
    
    /**
     * POST /flosc/v1/stripe-webhook
     * Receives payment confirmations from Stripe
     */
    public function rest_stripe_webhook($request) {
        $stripe = new FLOSC_Stripe_Checkout($this);
        return $stripe->handle_webhook($request);
    }
    
    
    /**
     * POST /flosc/v1/paypal-webhook
     * PLACEHOLDER: PayPal IPN handler
     */
    public function rest_paypal_webhook($request) {
        // =====================================================================
        // PAYPAL INTEGRATION PLACEHOLDER
        // =====================================================================
        // 
        // When implementing PayPal:
        // 
        // 1. Verify IPN signature
        // 2. Extract transaction details
        // 3. Find WordPress user by email or custom field
        // 4. Grant access via $this->grant_paid_access($user_id)
        // 5. Log transaction
        // 
        // Reference: https://developer.paypal.com/docs/ipn/
        // =====================================================================
        
        error_log('FLOSC: PayPal webhook received (not implemented)');
        error_log('FLOSC: PayPal payload: ' . print_r($request->get_body(), true));
        
        return new WP_REST_Response(['received' => true, 'implemented' => false]);
    }
    
    
    /**
     * POST /flosc/v1/clickbank-ipn
     * 
     * =========================================================================
     * CLICKBANK INTEGRATION PLACEHOLDER
     * =========================================================================
     * 
     * ClickBank sends Instant Payment Notifications (IPN) when:
     * - Sale occurs
     * - Refund occurs
     * - Subscription rebills
     * - Chargeback occurs
     * 
     * WHEN IMPLEMENTING:
     * 
     * 1. VERIFY THE IPN
     *    - ClickBank sends a secret key in the request
     *    - Verify: sha256(secretKey + ipnFields) === ipnVerify
     *    
     *    $secret_key = $this->get_config('clickbank_secret_key');
     *    $ipn_verify = $_POST['ipn_verify'] ?? '';
     *    // ... compute hash and compare
     * 
     * 2. EXTRACT USER INFO
     *    - Customer email: $_POST['cemail']
     *    - Customer name: $_POST['cfname'], $_POST['clname']
     *    - Transaction ID: $_POST['receipt']
     *    - Product ID: $_POST['cproditem']
     *    - Affiliate: $_POST['affiliate']
     * 
     * 3. FIND OR CREATE WORDPRESS USER
     *    $user = get_user_by('email', $customer_email);
     *    if (!$user) {
     *        // Create user with email, send password reset
     *    }
     * 
     * 4. GRANT ACCESS
     *    $this->grant_paid_access($user->ID, 'clickbank', $receipt);
     * 
     * 5. TRACK AFFILIATE
     *    if ($affiliate) {
     *        update_user_meta($user->ID, 'flosc_clickbank_affiliate', $affiliate);
     *    }
     * 
     * 6. HANDLE REFUNDS
     *    if ($ctransaction === 'RFND' || $ctransaction === 'CGBK') {
     *        $this->revoke_paid_access($user->ID, 'clickbank_refund');
     *    }
     * 
     * CLICKBANK FIELD REFERENCE:
     * - ctransaction: SALE, RFND, CGBK, REBILL, etc.
     * - cproditem: Your product item number
     * - cemail: Customer email
     * - receipt: Transaction receipt number
     * - affiliate: Affiliate nickname (if any)
     * - ipn_verify: Verification hash
     * 
     * =========================================================================
     */
    public function rest_clickbank_ipn($request) {
        error_log('FLOSC: ClickBank IPN received');
        
        // Get raw POST data
        $body = $request->get_body();
        $params = [];
        parse_str($body, $params);
        
        error_log('FLOSC: ClickBank params: ' . print_r($params, true));
        
        // =====================================================================
        // TODO: Implement ClickBank verification and processing
        // =====================================================================
        // 
        // $secret_key = $this->get_config('clickbank_secret_key');
        // $vendor = $this->get_config('clickbank_vendor');
        // 
        // // Verify signature
        // $ipn_fields = [...]; // Specific fields in specific order
        // $expected_hash = strtoupper(sha256($secret_key . '|' . implode('|', $ipn_fields)));
        // 
        // if ($expected_hash !== ($params['ipn_verify'] ?? '')) {
        //     error_log('FLOSC: ClickBank IPN verification failed');
        //     return new WP_Error('invalid_signature', 'IPN verification failed', ['status' => 403]);
        // }
        // 
        // // Process based on transaction type
        // $transaction_type = $params['ctransaction'] ?? '';
        // 
        // switch ($transaction_type) {
        //     case 'SALE':
        //     case 'TEST_SALE':
        //         // Find or create user, grant access
        //         break;
        //     case 'RFND':
        //     case 'CGBK':
        //         // Revoke access
        //         break;
        // }
        // =====================================================================
        
        return new WP_REST_Response([
            'received'    => true,
            'implemented' => false,
            'message'     => 'ClickBank IPN endpoint ready for implementation',
        ]);
    }


    /* =========================================================================
     * SECTION 2.9: ACCESS CONTROL
     * =========================================================================
     * 
     * Methods for checking and granting user access to paid content.
     * Supports multiple access methods for flexibility.
     */
    
    /**
     * Check if user has paid access
     * 
     * Checks multiple methods in order:
     * 1. BuddyBoss group membership (if configured)
     * 2. User meta flag (flosc_paid_access)
     * 3. User capability (flosc_full_access)
     * 
     * @param int $user_id WordPress user ID
     * @return bool True if user has paid access
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
     * Grant paid access to user
     * 
     * Called by payment webhooks after successful payment.
     * Uses multiple methods for redundancy.
     * 
     * @param int    $user_id        WordPress user ID
     * @param string $gateway        Payment gateway used
     * @param string $transaction_id Transaction reference
     * @return string Method used to grant access
     */
    public function grant_paid_access($user_id, $gateway = 'manual', $transaction_id = '') {
        
        // Method 1: Add to BuddyBoss group (if configured)
        $group_id = $this->get_config('paid_group_id');
        $method = 'user_meta';
        
        if ($group_id && function_exists('groups_join_group')) {
            $result = groups_join_group($group_id, $user_id);
            if ($result) {
                $method = 'buddyboss_group';
                error_log("FLOSC: User {$user_id} added to paid group {$group_id}");
            }
        }
        
        // Method 2: Set user meta (always do this as backup)
        update_user_meta($user_id, 'flosc_paid_access', '1');
        update_user_meta($user_id, 'flosc_purchase_gateway', $gateway);
        update_user_meta($user_id, 'flosc_purchase_transaction_id', $transaction_id);
        update_user_meta($user_id, 'flosc_purchase_date', current_time('mysql'));
        
        // Track referral on purchase
        $referral = get_user_meta($user_id, 'flosc_referral_code', true);
        if ($referral) {
            error_log("FLOSC: Purchase by user {$user_id} attributed to referral '{$referral}'");
        }
        
        // Log purchase
        $this->log_purchase($user_id, $gateway, $transaction_id, $method);
        
        // Fire action for extensions
        do_action('flosc_access_granted', $user_id, $gateway, $transaction_id, $method);
        
        return $method;
    }
    
    
    /**
     * Revoke paid access from user
     * 
     * Called by refund/chargeback webhooks.
     * 
     * @param int    $user_id WordPress user ID
     * @param string $reason  Reason for revocation
     */
    public function revoke_paid_access($user_id, $reason = 'manual') {
        
        // Remove from BuddyBoss group
        $group_id = $this->get_config('paid_group_id');
        if ($group_id && function_exists('groups_leave_group')) {
            groups_leave_group($group_id, $user_id);
        }
        
        // Clear user meta
        update_user_meta($user_id, 'flosc_paid_access', '0');
        update_user_meta($user_id, 'flosc_access_revoked', current_time('mysql'));
        update_user_meta($user_id, 'flosc_revocation_reason', $reason);
        
        error_log("FLOSC: Access revoked for user {$user_id}, reason: {$reason}");
        
        do_action('flosc_access_revoked', $user_id, $reason);
    }
    
    
    /**
     * Log purchase for analytics
     */
    private function log_purchase($user_id, $gateway, $transaction_id, $method) {
        $purchases = get_option('flosc_purchases', []);
        
        $purchases[] = [
            'user_id'        => $user_id,
            'gateway'        => $gateway,
            'transaction_id' => $transaction_id,
            'method'         => $method,
            'referral'       => get_user_meta($user_id, 'flosc_referral_code', true),
            'timestamp'      => current_time('timestamp'),
            'date'           => current_time('mysql'),
        ];
        
        // Keep last 1000 purchases
        if (count($purchases) > 1000) {
            $purchases = array_slice($purchases, -1000);
        }
        
        update_option('flosc_purchases', $purchases);
    }


    /* =========================================================================
     * SECTION 2.10: HELPER METHODS
     * =========================================================================
     */
    
    /**
     * Get referral code from URL, cookie, or user meta
     */
    private function get_referral_code() {
        // Check URL parameter
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            $ref = sanitize_text_field($_GET['ref']);
            $days = (int)$this->get_config('referral_cookie_days');
            setcookie('flosc_ref', $ref, time() + ($days * DAY_IN_SECONDS), '/');
            return $ref;
        }
        
        // Check cookie
        if (isset($_COOKIE['flosc_ref']) && !empty($_COOKIE['flosc_ref'])) {
            return sanitize_text_field($_COOKIE['flosc_ref']);
        }
        
        // Check user meta
        if (is_user_logged_in()) {
            $ref = get_user_meta(get_current_user_id(), 'flosc_referral_code', true);
            if ($ref) return $ref;
        }
        
        return null;
    }
    
    
    /**
     * Check if user is in cooldown
     */
    private function check_cooldown() {
        $session_id = $this->get_or_create_session_id();
        $cooldown_until = get_transient('flosc_cooldown_' . $session_id);
        
        return $cooldown_until && $cooldown_until > current_time('timestamp');
    }
    
    
    /**
     * Get cooldown end time
     */
    private function get_cooldown_end_time() {
        $session_id = $this->get_or_create_session_id();
        $cooldown_until = get_transient('flosc_cooldown_' . $session_id);
        
        if ($cooldown_until && $cooldown_until > current_time('timestamp')) {
            return $cooldown_until;
        }
        
        return null;
    }
    
    
    /**
     * Get or create session ID for anonymous users
     */
    private function get_or_create_session_id() {
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return 'ip_' . md5($ip);
    }


    /**
     * Minimal AI rate limiter.
     *
     * Purpose: prevent anonymous traffic from hammering your AI backend.
     * This is intentionally simple and cheap (transients).
     */
    private function ai_rate_limit_allow() {
        $sid = $this->get_or_create_session_id();
        $ip  = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Anonymous: 30 req / hour. Logged-in: 120 req / hour.
        $limit = is_user_logged_in() ? 120 : 30;
        $window_seconds = HOUR_IN_SECONDS;

        $key = 'flosc_ai_rl_' . md5($sid . '|' . $ip);
        $count = (int)get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        set_transient($key, $count + 1, $window_seconds);
        return true;
    }


    /* =========================================================================
     * SECTION 2.11: ADMIN INTERFACE
     * =========================================================================
     * 
     * Settings page organized by FLOSC stages.
     * Each stage has its own section with clear labels.
     */
    
    public function add_admin_menu() {
        add_menu_page(
            'FLOSC Framework',
            'FLOSC',
            'manage_options',
            'flosc-settings',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            30
        );
    }
    
    
    public function register_settings() {
        foreach (array_keys($this->default_config) as $key) {
            register_setting('flosc_settings', 'flosc_' . $key);
        }
    }
    
    
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error('flosc_messages', 'flosc_message', 'Settings Saved ✓', 'updated');
        }
        settings_errors('flosc_messages');
        
        ?>
        <div class="wrap">
            <h1>🎯 FLOSC Framework v<?php echo FLOSC_VERSION; ?></h1>
            <p>Freeline → Login → Offer → Sale → Content</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('flosc_settings'); ?>
                
                <!-- =========================================================
                     DOMAIN LANDING MODE (v2.0.4)
                     ========================================================= -->
                <h2 class="flosc-section-title">🌐 Domain Landing Mode</h2>
                <p class="flosc-section-desc">Make lesaep.com show ONLY the chatbot at root path.</p>
                <table class="form-table">
                    <tr>
                        <th>Enable Landing Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_landing_mode" value="yes" 
                                    <?php checked($this->get_config('enable_landing_mode'), 'yes'); ?>>
                                Render chat-only page for specified domains
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Landing Domains</th>
                        <td>
                            <input type="text" name="flosc_landing_domains" 
                                   value="<?php echo esc_attr($this->get_config('landing_domains')); ?>" 
                                   class="large-text"
                                   placeholder="lesaep.com, www.lesaep.com">
                            <p class="description">Comma-separated list of domains. Root path (/) on these domains will show only the chatbot.</p>
                        </td>
                    </tr>
                </table>

                <!-- =========================================================
                     AI MODE (v2.0.5)
                     ========================================================= -->
                <h2 class="flosc-section-title">🤖 Chat Mode: Simple IVR vs AI</h2>
                <p class="flosc-section-desc">
                    Default is <strong>Simple IVR</strong> (deterministic scripted copy). Enable <strong>AI Mode</strong> only if you have an AI backend endpoint you control.
                    AI calls are proxied by WordPress (keys never exposed to the browser).
                </p>
                <table class="form-table">
                    <tr>
                        <th>Enable AI Mode</th>
                        <td>
                            <label>
                                <input type="hidden" name="flosc_enable_ai_mode" value="no">
                                <input type="checkbox" name="flosc_enable_ai_mode" value="yes" 
                                    <?php checked($this->get_config('enable_ai_mode'), 'yes'); ?>>
                                Use AI-generated chat responses (proxy via WP)
                            </label>
                            <p class="description">Off = Simple IVR mode (recommended for early testing). On = calls <code>/wp-json/flosc/v1/ai</code> internally.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>AI Backend URL</th>
                        <td>
                            <input type="text" name="flosc_ai_backend_url" 
                                   value="<?php echo esc_attr($this->get_config('ai_backend_url')); ?>" 
                                   class="large-text"
                                   placeholder="https://YOUR-DROPLET-DOMAIN/flosc-ai">
                            <p class="description">Your server endpoint that returns JSON: <code>{"message":"..."}</code>. Leave empty to disable AI.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>AI Backend Bearer</th>
                        <td>
                            <input type="password" name="flosc_ai_backend_bearer" 
                                   value="<?php echo esc_attr($this->get_config('ai_backend_bearer')); ?>" 
                                   class="large-text"
                                   placeholder="optional">
                            <p class="description">Optional Authorization token sent as <code>Bearer ...</code> to your backend.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>AI System Prompt</th>
                        <td>
                            <textarea name="flosc_ai_system_prompt" rows="3" class="large-text"><?php 
                                echo esc_textarea($this->get_config('ai_system_prompt')); 
                            ?></textarea>
                            <p class="description">Keep it strict and short. This governs tone and limits. (We cap response length server-side.)</p>
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     F - FREELINE
                     ========================================================= -->
                <h2 class="flosc-section-title">F - Freeline (Quiz/Hook)</h2>
                <p class="flosc-section-desc">The initial interaction that hooks users and collects assessment data.</p>
                <table class="form-table">
                    <tr>
                        <th>Welcome Message</th>
                        <td>
                            <textarea name="flosc_welcome_message" rows="2" class="large-text"><?php 
                                echo esc_textarea($this->get_config('welcome_message')); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Type</th>
                        <td>
                            <select name="flosc_quiz_type">
                                <option value="text" <?php selected($this->get_config('quiz_type'), 'text'); ?>>
                                    Text Input (for testing)
                                </option>
                                <option value="audio" <?php selected($this->get_config('quiz_type'), 'audio'); ?>>
                                    Audio Recording (pronunciation)
                                </option>
                            </select>
                            <p class="description">Text mode is perfect for testing the funnel. Audio mode uses speech recognition.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Items (one per line)</th>
                        <td>
                            <textarea name="flosc_quiz_items" rows="5" class="large-text code"><?php 
                                echo esc_textarea($this->get_config('quiz_items')); 
                            ?></textarea>
                            <p class="description">
                                Testing: 1, 2, 3... 10<br>
                                SAE Phonemes: /æ/, /ɛ/, /ɪ/... (44-47 sounds)<br>
                                Each item maps to one lesson.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Display Text</th>
                        <td>
                            <input type="text" name="flosc_quiz_display_text" 
                                   value="<?php echo esc_attr($this->get_config('quiz_display_text')); ?>" 
                                   class="large-text">
                            <p class="description">What the user sees (e.g., "1, 2, 3, 4, 5, 6, 7, 8, 9, 10")</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Prompt</th>
                        <td>
                            <textarea name="flosc_quiz_prompt" rows="2" class="large-text"><?php 
                                echo esc_textarea($this->get_config('quiz_prompt')); 
                            ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <h3>Referral Tracking</h3>
                <table class="form-table">
                    <tr>
                        <th>Enable Referral Tracking</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_referral_tracking" value="yes" 
                                    <?php checked($this->get_config('enable_referral_tracking'), 'yes'); ?>>
                                Track ?ref=XYZ parameter
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Referral Greeting</th>
                        <td>
                            <input type="text" name="flosc_referral_greeting" 
                                   value="<?php echo esc_attr($this->get_config('referral_greeting')); ?>" 
                                   class="large-text">
                            <p class="description">Use {ref} placeholder. Example: "I see {ref} sent you!"</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cookie Duration</th>
                        <td>
                            <input type="number" name="flosc_referral_cookie_days" 
                                   value="<?php echo esc_attr($this->get_config('referral_cookie_days')); ?>" 
                                   style="width:80px"> days
                        </td>
                    </tr>
                </table>
                
                <h3>Cooldown</h3>
                <table class="form-table">
                    <tr>
                        <th>Enable Cooldown</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_cooldown" value="yes" 
                                    <?php checked($this->get_config('enable_cooldown'), 'yes'); ?>>
                                Apply cooldown after multiple "no" responses
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Threshold</th>
                        <td>
                            <input type="number" name="flosc_cooldown_threshold" 
                                   value="<?php echo esc_attr($this->get_config('cooldown_threshold')); ?>" 
                                   style="width:80px"> "no" responses
                        </td>
                    </tr>
                    <tr>
                        <th>Duration</th>
                        <td>
                            <input type="number" name="flosc_cooldown_duration_hours" 
                                   value="<?php echo esc_attr($this->get_config('cooldown_duration_hours')); ?>" 
                                   style="width:80px"> hours
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     L - LOGIN
                     ========================================================= -->
                <h2 class="flosc-section-title">L - Login Gate</h2>
                <p class="flosc-section-desc">User must log in to see their results.</p>
                <table class="form-table">
                    <tr>
                        <th>Login Prompt</th>
                        <td>
                            <textarea name="flosc_login_prompt" rows="2" class="large-text"><?php 
                                echo esc_textarea($this->get_config('login_prompt')); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Social Login</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_social_login" value="yes" 
                                    <?php checked($this->get_config('enable_social_login'), 'yes'); ?>>
                                Show BuddyBoss social login buttons
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Taken Group ID</th>
                        <td>
                            <input type="number" name="flosc_quiz_taken_group_id" 
                                   value="<?php echo esc_attr($this->get_config('quiz_taken_group_id')); ?>">
                            <p class="description">BuddyBoss group to add users after completing quiz</p>
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     O - OFFER
                     ========================================================= -->
                <h2 class="flosc-section-title">O - Offer (Results + Free Content)</h2>
                <p class="flosc-section-desc">Show results and one FREE lesson from their missing items.</p>
                <table class="form-table">
                    <tr>
                        <th>Results Message</th>
                        <td>
                            <input type="text" name="flosc_results_message" 
                                   value="<?php echo esc_attr($this->get_config('results_message')); ?>" 
                                   class="large-text">
                            <p class="description">Use {score} and {total} placeholders</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Correct Items Message</th>
                        <td>
                            <input type="text" name="flosc_items_correct_message" 
                                   value="<?php echo esc_attr($this->get_config('items_correct_message')); ?>" 
                                   class="large-text">
                            <p class="description">Use {correct_items} placeholder</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Missed Items Message</th>
                        <td>
                            <input type="text" name="flosc_items_missed_message" 
                                   value="<?php echo esc_attr($this->get_config('items_missed_message')); ?>" 
                                   class="large-text">
                            <p class="description">Use {missed_items} placeholder</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Free Content Message</th>
                        <td>
                            <textarea name="flosc_free_content_message" rows="2" class="large-text"><?php 
                                echo esc_textarea($this->get_config('free_content_message')); 
                            ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Free Lesson Selection</th>
                        <td>
                            <select name="flosc_free_lesson_selection">
                                <option value="first_missed" <?php selected($this->get_config('free_lesson_selection'), 'first_missed'); ?>>
                                    First Missed Item
                                </option>
                                <option value="random_missed" <?php selected($this->get_config('free_lesson_selection'), 'random_missed'); ?>>
                                    Random Missed Item
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h3>Offer Expiry</h3>
                <table class="form-table">
                    <tr>
                        <th>Enable Countdown</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_enable_offer_expiry" value="yes" 
                                    <?php checked($this->get_config('enable_offer_expiry'), 'yes'); ?>>
                                Show countdown timer
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Duration</th>
                        <td>
                            <input type="number" name="flosc_offer_expiry_minutes" 
                                   value="<?php echo esc_attr($this->get_config('offer_expiry_minutes')); ?>" 
                                   style="width:80px"> minutes
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     S - SALE
                     ========================================================= -->
                <h2 class="flosc-section-title">S - Sale (Payment)</h2>
                <p class="flosc-section-desc">Present the paid offer and handle checkout.</p>
                <table class="form-table">
                    <tr>
                        <th>Offer Headline</th>
                        <td>
                            <input type="text" name="flosc_offer_headline" 
                                   value="<?php echo esc_attr($this->get_config('offer_headline')); ?>" 
                                   class="large-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Offer Description</th>
                        <td>
                            <textarea name="flosc_offer_description" rows="3" class="large-text"><?php 
                                echo esc_textarea($this->get_config('offer_description')); 
                            ?></textarea>
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
                            <input type="number" name="flosc_offer_price" 
                                   value="<?php echo esc_attr($this->get_config('offer_price')); ?>" 
                                   style="width:100px">
                        </td>
                    </tr>
                    <tr>
                        <th>Payment Gateway</th>
                        <td>
                            <select name="flosc_payment_gateway" id="flosc_payment_gateway">
                                <option value="stripe" <?php selected($this->get_config('payment_gateway'), 'stripe'); ?>>
                                    Stripe Checkout
                                </option>
                                <option value="paypal" <?php selected($this->get_config('payment_gateway'), 'paypal'); ?>>
                                    PayPal (coming soon)
                                </option>
                                <option value="clickbank" <?php selected($this->get_config('payment_gateway'), 'clickbank'); ?>>
                                    ClickBank (coming soon)
                                </option>
                                <option value="manual" <?php selected($this->get_config('payment_gateway'), 'manual'); ?>>
                                    Manual/Custom URL
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h3>💳 Stripe Configuration</h3>
                <table class="form-table">
                    <tr>
                        <th>Mode</th>
                        <td>
                            <select name="flosc_stripe_mode">
                                <option value="test" <?php selected($this->get_config('stripe_mode'), 'test'); ?>>Test</option>
                                <option value="live" <?php selected($this->get_config('stripe_mode'), 'live'); ?>>Live</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Test Publishable Key</th>
                        <td>
                            <input type="text" name="flosc_stripe_test_pk" 
                                   value="<?php echo esc_attr($this->get_config('stripe_test_pk')); ?>" 
                                   class="large-text" placeholder="pk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th>Test Secret Key</th>
                        <td>
                            <input type="password" name="flosc_stripe_test_sk" 
                                   value="<?php echo esc_attr($this->get_config('stripe_test_sk')); ?>" 
                                   class="large-text" placeholder="sk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th>Live Publishable Key</th>
                        <td>
                            <input type="text" name="flosc_stripe_live_pk" 
                                   value="<?php echo esc_attr($this->get_config('stripe_live_pk')); ?>" 
                                   class="large-text" placeholder="pk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th>Live Secret Key</th>
                        <td>
                            <input type="password" name="flosc_stripe_live_sk" 
                                   value="<?php echo esc_attr($this->get_config('stripe_live_sk')); ?>" 
                                   class="large-text" placeholder="sk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th>Webhook Secret</th>
                        <td>
                            <input type="password" name="flosc_stripe_webhook_secret" 
                                   value="<?php echo esc_attr($this->get_config('stripe_webhook_secret')); ?>" 
                                   class="large-text" placeholder="whsec_...">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html(rest_url('flosc/v1/stripe-webhook')); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3>🔗 ClickBank Configuration (Placeholder)</h3>
                <p class="description">ClickBank integration is ready for implementation. Contact developer to enable.</p>
                <table class="form-table">
                    <tr>
                        <th>Vendor ID</th>
                        <td>
                            <input type="text" name="flosc_clickbank_vendor" 
                                   value="<?php echo esc_attr($this->get_config('clickbank_vendor')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Secret Key</th>
                        <td>
                            <input type="password" name="flosc_clickbank_secret_key" 
                                   value="<?php echo esc_attr($this->get_config('clickbank_secret_key')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Product ID</th>
                        <td>
                            <input type="text" name="flosc_clickbank_product_id" 
                                   value="<?php echo esc_attr($this->get_config('clickbank_product_id')); ?>" 
                                   class="regular-text">
                            <p class="description">
                                IPN URL: <code><?php echo esc_html(rest_url('flosc/v1/clickbank-ipn')); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h3>Manual Checkout</h3>
                <table class="form-table">
                    <tr>
                        <th>Custom Checkout URL</th>
                        <td>
                            <input type="url" name="flosc_manual_checkout_url" 
                                   value="<?php echo esc_attr($this->get_config('manual_checkout_url')); ?>" 
                                   class="large-text" placeholder="https://...">
                            <p class="description">Fallback URL if no payment gateway configured</p>
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     C - CONTENT
                     ========================================================= -->
                <h2 class="flosc-section-title">C - Content (Post-Purchase)</h2>
                <p class="flosc-section-desc">Deliver purchased content in-chat.</p>
                <table class="form-table">
                    <tr>
                        <th>Content Category</th>
                        <td>
                            <input type="text" name="flosc_content_category" 
                                   value="<?php echo esc_attr($this->get_config('content_category')); ?>" 
                                   class="regular-text" placeholder="flosc-lessons">
                            <p class="description">WordPress category slug containing lessons</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Display Mode</th>
                        <td>
                            <select name="flosc_content_display_mode">
                                <option value="list" <?php selected($this->get_config('content_display_mode'), 'list'); ?>>
                                    List (titles, expand on click)
                                </option>
                                <option value="inline" <?php selected($this->get_config('content_display_mode'), 'inline'); ?>>
                                    Inline (full content in chat)
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Paid Group ID</th>
                        <td>
                            <input type="number" name="flosc_paid_group_id" 
                                   value="<?php echo esc_attr($this->get_config('paid_group_id')); ?>">
                            <p class="description">BuddyBoss group to add users after purchase</p>
                        </td>
                    </tr>
                </table>
                
                <!-- =========================================================
                     BOT APPEARANCE
                     ========================================================= -->
                <h2 class="flosc-section-title">🤖 Chat Bot Appearance</h2>
                <table class="form-table">
                    <tr>
                        <th>Bot Name</th>
                        <td>
                            <input type="text" name="flosc_chat_bot_name" 
                                   value="<?php echo esc_attr($this->get_config('chat_bot_name')); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Bot Avatar (emoji)</th>
                        <td>
                            <input type="text" name="flosc_chat_bot_avatar" 
                                   value="<?php echo esc_attr($this->get_config('chat_bot_avatar')); ?>" 
                                   style="width:60px; font-size:24px;">
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('💾 Save FLOSC Settings'); ?>
            </form>
            
            <!-- =========================================================
                 USAGE INSTRUCTIONS
                 ========================================================= -->
            <hr style="margin: 40px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
                <h2>📖 Quick Start</h2>
                
                <h3>1. Add Shortcode</h3>
                <p><code>[flosc]</code> on any page, or enable Domain Landing Mode above.</p>
                
                <h3>2. Configure Quiz Items</h3>
                <p>For testing, use: <code>1, 2, 3, 4, 5, 6, 7, 8, 9, 10</code></p>
                <p>Later, use SAE phonemes: <code>/æ/, /ɛ/, /ɪ/...</code> (44-47 sounds)</p>
                
                <h3>3. Create Lessons</h3>
                <p>Create WordPress posts in category <code><?php echo esc_html($this->get_config('content_category') ?: 'flosc-lessons'); ?></code></p>
                <p>Add post meta <code>_flosc_quiz_item</code> = quiz item (e.g., "4" or "/æ/")</p>
                
                <h3>4. Test the Flow</h3>
                <ol>
                    <li>Open page in incognito</li>
                    <li>Type "1 2 3" → Score 3/10</li>
                    <li>Login → See results + free lesson for item 4</li>
                    <li>Click Buy → Stripe checkout</li>
                    <li>Return → See all needed lessons</li>
                </ol>
            </div>
        </div>
        
        <style>
        .flosc-section-title {
            border-bottom: 3px solid #0073aa;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        .flosc-section-desc {
            color: #666;
            font-style: italic;
        }
        .form-table th {
            width: 200px;
            font-weight: 600;
        }
        </style>
        <?php
    }
}


/* =============================================================================
 * SECTION 3: PLUGIN INITIALIZATION
 * =============================================================================
 */

// Initialize the plugin
new FLOSC_Framework();
