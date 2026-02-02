<?php
/**
 * Plugin Name: LeSAEp FLOSC
 * Plugin URI: https://lesaep.com
 * Description: AI-powered pronunciation coach with multi-provider support (AI + STT). Quiz funnel with audio recording, pronunciation analysis, and lesson recommendations.
 * Version: 2.0.7
 * Author: Dainis W. Michel
 * Author URI: https://dainis.net
 * License: GPL v2 or later
 * Text Domain: lesaep-flosc
 *
 * @package LeSAEp_FLOSC
 * @version 2.0.7
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('LESAEP_FLOSC_VERSION', '2.0.7');
define('LESAEP_FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LESAEP_FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class LeSAEp_FLOSC {

    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Hooks
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_virtual_page']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Activation/Deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    private function load_dependencies() {
        require_once LESAEP_FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once LESAEP_FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
        require_once LESAEP_FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        require_once LESAEP_FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once LESAEP_FLOSC_PLUGIN_DIR . 'includes/class-access-control.php';
    }

    public function activate() {
        $this->register_rewrite_rules();
        flush_rewrite_rules();

        // Set default options
        if (!get_option('lesaep_app_slug')) {
            add_option('lesaep_app_slug', 'lesaep');
        }
        if (!get_option('lesaep_ai_provider')) {
            add_option('lesaep_ai_provider', 'ivr');
        }
        if (!get_option('lesaep_stt_provider')) {
            add_option('lesaep_stt_provider', 'assemblyai');
        }
        if (!get_option('lesaep_quiz_sentence')) {
            add_option('lesaep_quiz_sentence', 'one two three four five six seven eight nine ten');
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function register_rewrite_rules() {
        $slug = get_option('lesaep_app_slug', 'lesaep');
        add_rewrite_rule("^{$slug}/?$", 'index.php?lesaep_app=1', 'top');
        add_rewrite_tag('%lesaep_app%', '([^&]+)');
    }

    public function handle_virtual_page() {
        if (get_query_var('lesaep_app')) {
            $this->render_app_template();
            exit;
        }
    }

    private function render_app_template() {
        // Get user state
        $user_state = 'visitor';
        $user_id = get_current_user_id();

        if ($user_id) {
            $has_paid_access = LeSAEp_Access_Control::has_paid_access($user_id);
            $user_state = $has_paid_access ? 'paid' : 'free';
        }

        // Load template
        include LESAEP_FLOSC_PLUGIN_DIR . 'templates/lesaep-app.php';
    }

    public function enqueue_assets() {
        if (!get_query_var('lesaep_app')) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'lesaep-app',
            LESAEP_FLOSC_PLUGIN_URL . 'assets/css/lesaep-app.css',
            [],
            LESAEP_FLOSC_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'lesaep-app',
            LESAEP_FLOSC_PLUGIN_URL . 'assets/js/lesaep-app.js',
            [],
            LESAEP_FLOSC_VERSION,
            true
        );

        // Localize script with config
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        wp_localize_script('lesaep-app', 'LESAEP', [
            'restUrl' => rest_url('flosc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => $user_id > 0,
            'userState' => $user_id ? (LeSAEp_Access_Control::has_paid_access($user_id) ? 'paid' : 'free') : 'visitor',
            'userName' => $user ? $user->display_name : '',
            'userEmail' => $user ? $user->user_email : '',
            'userAvatar' => $user ? get_avatar_url($user_id) : '',
            'checkoutUrl' => get_option('lesaep_checkout_url', '#'),
            'loginUrl' => wp_login_url(home_url(get_option('lesaep_app_slug', 'lesaep'))),
            'signupUrl' => wp_registration_url(),
            'quizSentence' => get_option('lesaep_quiz_sentence', 'one two three four five six seven eight nine ten'),
        ]);
    }

    /**
     * REST API Routes
     */
    public function register_rest_routes() {
        // Existing chat routes
        register_rest_route('flosc/v1', '/ai-query', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_ai_query'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_sessions'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/sessions', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_create_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/sessions/(?P<id>[a-zA-Z0-9_]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/sessions/(?P<id>[a-zA-Z0-9_]+)/messages', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_add_message'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/access', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_check_access'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('flosc/v1', '/referral', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_referral'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // NEW: Audio processing route
        register_rest_route('flosc/v1', '/process-audio', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_process_audio'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Handle AI query (chat)
     */
    public function handle_ai_query($request) {
        $prompt = sanitize_textarea_field($request->get_param('prompt'));
        $session_id = sanitize_text_field($request->get_param('session_id'));

        if (empty($prompt)) {
            return new WP_Error('missing_prompt', 'Prompt is required', ['status' => 400]);
        }

        $provider = LeSAEp_AI_Provider_Factory::get_provider();

        if (!$provider->is_configured()) {
            return new WP_REST_Response([
                'message' => 'AI provider not configured. Please set up an AI provider in WordPress admin.',
            ]);
        }

        $response = $provider->get_response($prompt);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'message' => 'Sorry, I encountered an error. Please try again.',
            ]);
        }

        return new WP_REST_Response([
            'message' => $response,
        ]);
    }

    /**
     * Handle audio processing (NEW)
     */
    public function handle_process_audio($request) {
        $files = $request->get_file_params();
        $expected_text = sanitize_text_field($request->get_param('expected_text'));

        if (empty($files['audio'])) {
            return new WP_Error('missing_audio', 'Audio file is required', ['status' => 400]);
        }

        $audio_file = $files['audio'];

        // Move uploaded file to temp location
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/lesaep_temp_' . uniqid() . '.webm';

        if (!move_uploaded_file($audio_file['tmp_name'], $temp_path)) {
            return new WP_Error('upload_failed', 'Failed to process audio file', ['status' => 500]);
        }

        // Get STT provider
        $stt_provider = LeSAEp_STT_Provider_Factory::get_provider();

        if (!$stt_provider->is_configured()) {
            @unlink($temp_path);
            return new WP_Error('stt_not_configured', 'Speech-to-text provider not configured', ['status' => 500]);
        }

        // Transcribe audio
        $stt_result = $stt_provider->transcribe($temp_path);

        // Clean up temp file
        @unlink($temp_path);

        if (is_wp_error($stt_result)) {
            return $stt_result;
        }

        // Analyze pronunciation
        $analysis = LeSAEp_Pronunciation_Analyzer::analyze(
            $stt_result['transcript'],
            $expected_text,
            $stt_result
        );

        // Format for chat display
        $chat_message = LeSAEp_Pronunciation_Analyzer::format_for_chat($analysis);

        return new WP_REST_Response([
            'success' => true,
            'transcript' => $stt_result['transcript'],
            'analysis' => $analysis,
            'chat_message' => $chat_message,
            'free_lesson' => LeSAEp_Pronunciation_Analyzer::get_free_lesson($analysis['lesson_recommendations']),
            'paid_lessons' => LeSAEp_Pronunciation_Analyzer::get_paid_lessons($analysis['lesson_recommendations']),
        ]);
    }

    /**
     * Session management routes (from v02_06)
     */
    public function handle_get_sessions($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['today' => [], 'yesterday' => [], 'last_7_days' => [], 'older' => []]);
        }

        $sessions = LeSAEp_Session_Manager::get_user_sessions($user_id);
        $grouped = LeSAEp_Session_Manager::group_sessions_by_date($sessions);

        return new WP_REST_Response($grouped);
    }

    public function handle_create_session($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            $session_id = 'visitor_' . uniqid();
        } else {
            $session_id = 'session_' . $user_id . '_' . time();
        }

        if ($user_id) {
            LeSAEp_Session_Manager::create_session($user_id, $session_id);
        }

        return new WP_REST_Response(['session_id' => $session_id]);
    }

    public function handle_get_session($request) {
        $session_id = $request->get_param('id');
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['messages' => []]);
        }

        $session = LeSAEp_Session_Manager::get_session($user_id, $session_id);

        return new WP_REST_Response($session ?? ['messages' => []]);
    }

    public function handle_add_message($request) {
        $session_id = $request->get_param('id');
        $role = sanitize_text_field($request->get_param('role'));
        $content = sanitize_textarea_field($request->get_param('content'));
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['success' => false]);
        }

        LeSAEp_Session_Manager::add_message($user_id, $session_id, $role, $content);

        return new WP_REST_Response(['success' => true]);
    }

    public function handle_check_access($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['state' => 'visitor']);
        }

        $has_access = LeSAEp_Access_Control::has_paid_access($user_id);

        return new WP_REST_Response([
            'state' => $has_access ? 'paid' : 'free',
            'has_paid_access' => $has_access,
        ]);
    }

    public function handle_generate_referral($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_Error('not_logged_in', 'Must be logged in', ['status' => 401]);
        }

        $referral_code = get_user_meta($user_id, '_lesaep_referral_code', true);

        if (!$referral_code) {
            $referral_code = 'ref_' . $user_id . '_' . substr(md5($user_id . time()), 0, 8);
            update_user_meta($user_id, '_lesaep_referral_code', $referral_code);
        }

        $app_url = home_url(get_option('lesaep_app_slug', 'lesaep'));
        $referral_url = add_query_arg('ref', $referral_code, $app_url);

        return new WP_REST_Response([
            'referral_code' => $referral_code,
            'referral_url' => $referral_url,
            'share_text' => 'Free Standard American English Accent Evaluation!',
        ]);
    }

    /**
     * Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'LeSAEp',
            'LeSAEp',
            'manage_options',
            'lesaep-flosc',
            [$this, 'render_admin_page'],
            'dashicons-microphone',
            30
        );
    }

    public function register_settings() {
        // General settings
        register_setting('lesaep_general', 'lesaep_app_slug');
        register_setting('lesaep_general', 'lesaep_buddyboss_group_id');
        register_setting('lesaep_general', 'lesaep_checkout_url');

        // AI Provider settings
        register_setting('lesaep_ai', 'lesaep_ai_provider');
        register_setting('lesaep_ai', 'lesaep_openai_api_key');
        register_setting('lesaep_ai', 'lesaep_anthropic_api_key');
        register_setting('lesaep_ai', 'lesaep_xai_api_key');

        // STT Provider settings
        register_setting('lesaep_stt', 'lesaep_stt_provider');
        register_setting('lesaep_stt', 'lesaep_stt_api_key');
        register_setting('lesaep_stt', 'lesaep_stt_custom_endpoint');

        // Quiz settings
        register_setting('lesaep_quiz', 'lesaep_quiz_sentence');
        register_setting('lesaep_quiz', 'lesaep_quiz_mode'); // 'counting' or 'magic_sentence'
    }

    public function render_admin_page() {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap">
            <h1>LeSAEp FLOSC Settings</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=lesaep-flosc&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">General</a>
                <a href="?page=lesaep-flosc&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Provider</a>
                <a href="?page=lesaep-flosc&tab=stt" class="nav-tab <?php echo $active_tab === 'stt' ? 'nav-tab-active' : ''; ?>">Speech-to-Text</a>
                <a href="?page=lesaep-flosc&tab=quiz" class="nav-tab <?php echo $active_tab === 'quiz' ? 'nav-tab-active' : ''; ?>">Quiz Settings</a>
            </h2>

            <?php
            switch ($active_tab) {
                case 'ai':
                    $this->render_ai_settings();
                    break;
                case 'stt':
                    $this->render_stt_settings();
                    break;
                case 'quiz':
                    $this->render_quiz_settings();
                    break;
                default:
                    $this->render_general_settings();
            }
            ?>
        </div>
        <?php
    }

    private function render_general_settings() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('lesaep_general'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="lesaep_app_slug">App URL Slug</label></th>
                    <td>
                        <input type="text" id="lesaep_app_slug" name="lesaep_app_slug" value="<?php echo esc_attr(get_option('lesaep_app_slug', 'lesaep')); ?>" class="regular-text">
                        <p class="description">Your app will be at: <?php echo home_url(); ?>/<strong><?php echo esc_html(get_option('lesaep_app_slug', 'lesaep')); ?></strong>/</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_buddyboss_group_id">BuddyBoss Paid Group ID</label></th>
                    <td>
                        <input type="number" id="lesaep_buddyboss_group_id" name="lesaep_buddyboss_group_id" value="<?php echo esc_attr(get_option('lesaep_buddyboss_group_id', '')); ?>" class="regular-text">
                        <p class="description">Users in this group get automatic paid access</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_checkout_url">Checkout URL</label></th>
                    <td>
                        <input type="url" id="lesaep_checkout_url" name="lesaep_checkout_url" value="<?php echo esc_attr(get_option('lesaep_checkout_url', '')); ?>" class="regular-text">
                        <p class="description">Where users go to purchase full access</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_ai_settings() {
        $current_provider = get_option('lesaep_ai_provider', 'ivr');
        $providers = LeSAEp_AI_Provider_Factory::get_available_providers();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('lesaep_ai'); ?>

            <h2>Select AI Provider</h2>
            <table class="form-table">
                <tr>
                    <th><label for="lesaep_ai_provider">Active Provider</label></th>
                    <td>
                        <select id="lesaep_ai_provider" name="lesaep_ai_provider">
                            <?php foreach ($providers as $key => $provider): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                    <?php echo esc_html($provider['name']); ?> - <?php echo esc_html($provider['description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h3>Provider API Keys</h3>
            <table class="form-table">
                <tr>
                    <th><label for="lesaep_openai_api_key">OpenAI API Key</label></th>
                    <td>
                        <input type="password" id="lesaep_openai_api_key" name="lesaep_openai_api_key" value="<?php echo esc_attr(get_option('lesaep_openai_api_key', '')); ?>" class="regular-text">
                        <p class="description">Get from: <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_anthropic_api_key">Anthropic API Key</label></th>
                    <td>
                        <input type="password" id="lesaep_anthropic_api_key" name="lesaep_anthropic_api_key" value="<?php echo esc_attr(get_option('lesaep_anthropic_api_key', '')); ?>" class="regular-text">
                        <p class="description">Get from: <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_xai_api_key">xAI (Grok) API Key</label></th>
                    <td>
                        <input type="password" id="lesaep_xai_api_key" name="lesaep_xai_api_key" value="<?php echo esc_attr(get_option('lesaep_xai_api_key', '')); ?>" class="regular-text">
                        <p class="description">Get from: <a href="https://x.ai/api" target="_blank">x.ai/api</a></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_stt_settings() {
        $current_provider = get_option('lesaep_stt_provider', 'assemblyai');
        $providers = LeSAEp_STT_Provider_Factory::get_available_providers();
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('lesaep_stt'); ?>

            <h2>Select Speech-to-Text Provider</h2>
            <table class="form-table">
                <tr>
                    <th><label for="lesaep_stt_provider">Active Provider</label></th>
                    <td>
                        <select id="lesaep_stt_provider" name="lesaep_stt_provider">
                            <?php foreach ($providers as $key => $provider): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                    <?php echo esc_html($provider['name']); ?> - <?php echo esc_html($provider['cost']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Choose based on your needs: accuracy, cost, or self-hosting</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_stt_api_key">API Key</label></th>
                    <td>
                        <input type="password" id="lesaep_stt_api_key" name="lesaep_stt_api_key" value="<?php echo esc_attr(get_option('lesaep_stt_api_key', '')); ?>" class="regular-text">
                        <p class="description">
                            <strong>AssemblyAI:</strong> <a href="https://www.assemblyai.com/" target="_blank">assemblyai.com</a> (Recommended)<br>
                            <strong>OpenAI:</strong> Same key as AI provider<br>
                            <strong>Deepgram:</strong> <a href="https://deepgram.com/" target="_blank">deepgram.com</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_stt_custom_endpoint">Custom Endpoint (optional)</label></th>
                    <td>
                        <input type="url" id="lesaep_stt_custom_endpoint" name="lesaep_stt_custom_endpoint" value="<?php echo esc_attr(get_option('lesaep_stt_custom_endpoint', '')); ?>" class="regular-text">
                        <p class="description">For self-hosted or third-party STT services</p>
                    </td>
                </tr>
            </table>

            <h3>Provider Comparison</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Cost</th>
                        <th>Features</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $key => $provider): ?>
                        <tr <?php echo $current_provider === $key ? 'style="background:#e0f7e0;"' : ''; ?>>
                            <td><strong><?php echo esc_html($provider['name']); ?></strong></td>
                            <td><?php echo esc_html($provider['cost']); ?></td>
                            <td><?php echo implode(', ', $provider['features']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_quiz_settings() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('lesaep_quiz'); ?>

            <h2>Quiz Configuration</h2>
            <table class="form-table">
                <tr>
                    <th><label for="lesaep_quiz_mode">Quiz Mode</label></th>
                    <td>
                        <select id="lesaep_quiz_mode" name="lesaep_quiz_mode">
                            <option value="counting" <?php selected(get_option('lesaep_quiz_mode', 'counting'), 'counting'); ?>>Counting (1-10) - For development/testing</option>
                            <option value="magic_sentence" <?php selected(get_option('lesaep_quiz_mode'), 'magic_sentence'); ?>>Magic Sentence - For production</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="lesaep_quiz_sentence">Quiz Sentence</label></th>
                    <td>
                        <textarea id="lesaep_quiz_sentence" name="lesaep_quiz_sentence" rows="3" class="large-text"><?php echo esc_textarea(get_option('lesaep_quiz_sentence', 'one two three four five six seven eight nine ten')); ?></textarea>
                        <p class="description">
                            <strong>Counting mode:</strong> Use "one two three four five six seven eight nine ten"<br>
                            <strong>Magic sentence mode:</strong> Use your phonetically complete sentence
                        </p>
                    </td>
                </tr>
            </table>

            <h3>How It Works</h3>
            <p>The quiz asks users to pronounce the sentence above. Our system:</p>
            <ol>
                <li>Records their audio (browser MediaRecorder)</li>
                <li>Transcribes using your selected STT provider</li>
                <li>Analyzes pronunciation errors</li>
                <li>Maps errors to lesson recommendations</li>
                <li>Gives 1 lesson FREE, upsells the rest</li>
            </ol>

            <?php submit_button(); ?>
        </form>
        <?php
    }
}

// Initialize plugin
function lesaep_flosc_init() {
    return LeSAEp_FLOSC::get_instance();
}
add_action('plugins_loaded', 'lesaep_flosc_init');
