<?php
/**
 * Plugin Name: FLOSC Framework
 * Description: Freeline-Login-Offer-Sale-Content conversational sales funnel
 * Version: 2.0.1
 * Author: Dainis Michel
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Framework {
    
    private $default_config = [
        // Freeline
        'welcome_message' => 'Welcome! Ready to take a quick assessment?',
        'quiz_prompt' => 'Click the microphone and read the text aloud.',
        'quiz_text' => 'One, two, three, four, five, six, seven, eight, nine, ten.',
        'processing_endpoint' => '',
        
        // Login
        'login_prompt' => 'Login to see your personalized results!',
        
        // Results - 4 tiers
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
        
        // Free content
        'free_content_message' => 'Here is your free lesson to get started:',
        'free_content_id' => '',
        
        // Offer
        'offer_headline' => 'Unlock the Full Course',
        'offer_description' => 'Get access to all lessons and personalized learning.',
        'offer_price' => 144,
        'offer_currency' => '€',
        'checkout_url' => '',
        
        // Content
        'content_source' => 'category', // 'category' or 'manual'
        'content_category' => '',
        'paid_group_id' => '',
        
        // Chat engine
        'chat_engine' => 'native', // 'native', 'voiceflow', 'grok', 'claude'
        'chat_api_key' => '',
        'chat_bot_name' => 'Coach',
        'chat_bot_avatar' => '🎯',
    ];
    
    public function __construct() {
        add_shortcode('flosc', array($this, 'render_chat'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Get config value with default fallback
     */
    private function get_config($key) {
        $value = get_option('flosc_' . $key);
        if ($value === false || $value === '') {
            return $this->default_config[$key] ?? '';
        }
        return $value;
    }
    
    /**
     * Render chat interface
     */
    public function render_chat($atts = []) {
        $atts = shortcode_atts(['campaign' => 'default'], $atts);
        
        $user = wp_get_current_user();
        $has_access = $this->user_has_paid_access($user->ID);
        
        ob_start();
        ?>
        <div id="flosc-container" data-campaign="<?php echo esc_attr($atts['campaign']); ?>">
            <div class="flosc-chat-window">
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
                
                <div class="flosc-messages" id="floscMessages"></div>
                
                <div class="flosc-input-area">
                    <div class="flosc-typing" id="floscTyping">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="flosc-replies" id="floscReplies"></div>
                    <div class="flosc-recorder" id="floscRecorder">
                        <div class="flosc-quiz-text" id="floscQuizText"></div>
                        <button class="flosc-record-btn" id="floscRecordBtn">
                            <span class="flosc-record-icon">🎤</span>
                            <span class="flosc-record-label">Click to Record</span>
                        </button>
                        <div class="flosc-waveform" id="floscWaveform">
                            <span></span><span></span><span></span><span></span><span></span>
                        </div>
                        <p class="flosc-record-status" id="floscRecordStatus"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'flosc')) {
            return;
        }
        
        wp_enqueue_style('flosc', plugins_url('assets/css/flosc.css', __FILE__), [], '2.0.1');
        wp_enqueue_script('flosc', plugins_url('assets/js/flosc.js', __FILE__), [], '2.0.1', true);
        
        $user = wp_get_current_user();
        
        // Pass all config to JavaScript
        wp_localize_script('flosc', 'FLOSC_CONFIG', [
            // URLs
            'restUrl' => rest_url('flosc/v1'),
            'loginUrl' => wp_login_url(get_permalink()),
            'checkoutUrl' => $this->get_config('checkout_url'),
            
            // User state
            'userId' => $user->ID,
            'isLoggedIn' => is_user_logged_in(),
            'hasPaidAccess' => $this->user_has_paid_access($user->ID),
            'userName' => $user->display_name,
            
            // Freeline config
            'welcomeMessage' => $this->get_config('welcome_message'),
            'quizPrompt' => $this->get_config('quiz_prompt'),
            'quizText' => $this->get_config('quiz_text'),
            'processingEndpoint' => $this->get_config('processing_endpoint'),
            
            // Login config
            'loginPrompt' => $this->get_config('login_prompt'),
            
            // Result tiers
            'tiers' => [
                ['min' => (int)$this->get_config('tier_1_min'), 'max' => (int)$this->get_config('tier_1_max'), 'message' => $this->get_config('tier_1_message')],
                ['min' => (int)$this->get_config('tier_2_min'), 'max' => (int)$this->get_config('tier_2_max'), 'message' => $this->get_config('tier_2_message')],
                ['min' => (int)$this->get_config('tier_3_min'), 'max' => (int)$this->get_config('tier_3_max'), 'message' => $this->get_config('tier_3_message')],
                ['min' => (int)$this->get_config('tier_4_min'), 'max' => (int)$this->get_config('tier_4_max'), 'message' => $this->get_config('tier_4_message')],
            ],
            
            // Free content
            'freeContentMessage' => $this->get_config('free_content_message'),
            'freeContentId' => $this->get_config('free_content_id'),
            
            // Offer config
            'offerHeadline' => $this->get_config('offer_headline'),
            'offerDescription' => $this->get_config('offer_description'),
            'offerPrice' => (int)$this->get_config('offer_price'),
            'offerCurrency' => $this->get_config('offer_currency'),
            
            // Bot config
            'botName' => $this->get_config('chat_bot_name'),
            'botAvatar' => $this->get_config('chat_bot_avatar'),
        ]);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Get user access status
        register_rest_route('flosc/v1', '/status', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_status'),
            'permission_callback' => '__return_true'
        ]);
        
        // Process quiz (mock for now, real endpoint configured separately)
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
        
        // Get paid content
        register_rest_route('flosc/v1', '/content', [
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_content'),
            'permission_callback' => function() {
                return is_user_logged_in();
            }
        ]);
        
        // Mark user as paid (admin only)
        register_rest_route('flosc/v1', '/mark-paid', [
            'methods' => 'POST',
            'callback' => array($this, 'rest_mark_paid'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * REST: Get user status
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
     * REST: Process quiz (mock response)
     * In production, this would call external API or be replaced entirely
     */
    public function rest_process_quiz($request) {
        // For framework testing: return mock score
        // Real implementation would process audio and return actual assessment
        
        $mock_score = rand(30, 95); // Random score for testing
        
        return new WP_REST_Response([
            'success' => true,
            'score' => $mock_score,
            'details' => [
                'transcript' => $request->get_param('expected_text') ?? 'Mock transcript',
                'phonemes_analyzed' => 10,
                'issues_found' => max(0, floor((100 - $mock_score) / 10)),
            ]
        ]);
    }
    
    /**
     * REST: Get free content
     */
    public function rest_get_free_content() {
        $content_id = $this->get_config('free_content_id');
        
        if (!$content_id) {
            // Return placeholder if no content configured
            return new WP_REST_Response([
                'id' => 0,
                'title' => 'Free Lesson',
                'excerpt' => 'Your free lesson content will appear here once configured.',
                'content' => '<p>Configure free content in FLOSC settings.</p>',
                'url' => '#',
            ]);
        }
        
        $post = get_post($content_id);
        if (!$post) {
            return new WP_Error('not_found', 'Free content not found', ['status' => 404]);
        }
        
        return new WP_REST_Response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'excerpt' => get_the_excerpt($post),
            'content' => apply_filters('the_content', $post->post_content),
            'url' => get_permalink($post),
        ]);
    }
    
    /**
     * REST: Get paid content
     */
    public function rest_get_content($request) {
        $user_id = get_current_user_id();
        
        if (!$this->user_has_paid_access($user_id)) {
            return new WP_Error('forbidden', 'Paid access required', ['status' => 403]);
        }
        
        $category = $this->get_config('content_category');
        
        if (!$category) {
            return new WP_REST_Response([
                'items' => [],
                'message' => 'No content category configured',
            ]);
        }
        
        $posts = get_posts([
            'post_type' => 'post',
            'category_name' => $category,
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ]);
        
        $items = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => get_the_excerpt($post),
                'url' => get_permalink($post),
            ];
        }, $posts);
        
        return new WP_REST_Response([
            'items' => $items,
            'total' => count($items),
        ]);
    }
    
    /**
     * REST: Mark user as paid
     */
    public function rest_mark_paid($request) {
        $user_id = $request->get_param('user_id');
        
        if (!$user_id) {
            return new WP_Error('invalid', 'User ID required', ['status' => 400]);
        }
        
        $group_id = $this->get_config('paid_group_id');
        
        if ($group_id && function_exists('groups_join_group')) {
            groups_join_group($group_id, $user_id);
            $method = 'buddyboss_group';
        } else {
            update_user_meta($user_id, 'flosc_paid_access', '1');
            $method = 'user_meta';
        }
        
        return new WP_REST_Response([
            'success' => true,
            'user_id' => $user_id,
            'method' => $method,
        ]);
    }
    
    /**
     * Check if user has paid access
     */
    private function user_has_paid_access($user_id) {
        if (!$user_id) return false;
        
        // Check BuddyBoss group
        $group_id = $this->get_config('paid_group_id');
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) {
                return true;
            }
        }
        
        // Check user meta
        if (get_user_meta($user_id, 'flosc_paid_access', true) === '1') {
            return true;
        }
        
        // Check capability
        $user = get_user_by('id', $user_id);
        if ($user && $user->has_cap('flosc_full_access')) {
            return true;
        }
        
        return false;
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
     * Register settings
     */
    public function register_settings() {
        foreach (array_keys($this->default_config) as $key) {
            register_setting('flosc_settings', 'flosc_' . $key);
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) return;
        
        ?>
        <div class="wrap">
            <h1>FLOSC Framework Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('flosc_settings'); ?>
                
                <h2 class="title">F - Freeline (Quiz/Hook)</h2>
                <table class="form-table">
                    <tr>
                        <th>Welcome Message</th>
                        <td>
                            <textarea name="flosc_welcome_message" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('welcome_message')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Prompt</th>
                        <td>
                            <textarea name="flosc_quiz_prompt" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('quiz_prompt')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Quiz Text (what user reads)</th>
                        <td>
                            <textarea name="flosc_quiz_text" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('quiz_text')); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>Processing Endpoint URL</th>
                        <td>
                            <input type="url" name="flosc_processing_endpoint" value="<?php echo esc_attr($this->get_config('processing_endpoint')); ?>" class="regular-text">
                            <p class="description">External API for audio processing. Leave blank for mock responses.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">L - Login Gate</h2>
                <table class="form-table">
                    <tr>
                        <th>Login Prompt</th>
                        <td>
                            <textarea name="flosc_login_prompt" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('login_prompt')); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">O - Offer (Result Tiers)</h2>
                <p>Define score ranges and messages for each tier.</p>
                
                <?php for ($i = 1; $i <= 4; $i++): ?>
                <table class="form-table" style="background: #f9f9f9; margin-bottom: 10px;">
                    <tr>
                        <th>Tier <?php echo $i; ?> Range</th>
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
                
                <h2 class="title">Free Content</h2>
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
                
                <h2 class="title">S - Sale (Offer & Checkout)</h2>
                <table class="form-table">
                    <tr>
                        <th>Offer Headline</th>
                        <td>
                            <input type="text" name="flosc_offer_headline" value="<?php echo esc_attr($this->get_config('offer_headline')); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Offer Description</th>
                        <td>
                            <textarea name="flosc_offer_description" rows="2" class="large-text"><?php echo esc_textarea($this->get_config('offer_description')); ?></textarea>
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
                        <th>Checkout URL</th>
                        <td>
                            <input type="url" name="flosc_checkout_url" value="<?php echo esc_attr($this->get_config('checkout_url')); ?>" class="large-text">
                            <p class="description">WooCommerce, Stripe, or custom checkout page</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title">C - Content (Post-Sale)</h2>
                <table class="form-table">
                    <tr>
                        <th>Content Category Slug</th>
                        <td>
                            <input type="text" name="flosc_content_category" value="<?php echo esc_attr($this->get_config('content_category')); ?>" class="regular-text">
                            <p class="description">WordPress category slug containing paid content</p>
                        </td>
                    </tr>
                    <tr>
                        <th>BuddyBoss Paid Group ID</th>
                        <td>
                            <input type="number" name="flosc_paid_group_id" value="<?php echo esc_attr($this->get_config('paid_group_id')); ?>">
                            <p class="description">Optional: Group ID for paid members</p>
                        </td>
                    </tr>
                </table>
                
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
                
                <?php submit_button('Save FLOSC Settings'); ?>
            </form>
            
            <hr>
            <h2>Usage</h2>
            <p>Add this shortcode to any page: <code>[flosc]</code></p>
        </div>
        <?php
    }
}

// Initialize
new FLOSC_Framework();
