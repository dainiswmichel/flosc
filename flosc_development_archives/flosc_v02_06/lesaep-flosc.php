<?php
/**
 * Plugin Name: LeSAEp FLOSC v02.06
 * Plugin URI: https://lesaep.com
 * Description: Professional AI-powered pronunciation coach with Claude/ChatGPT-style interface
 * Version: 2.0.6
 * Author: Dainis W. Michel
 * Author URI: https://dainis.net
 * License: GPL v2 or later
 * Text Domain: lesaep-flosc
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LESAEP_VERSION', '2.0.6');
define('LESAEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LESAEP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main LeSAEp FLOSC Plugin Class
 */
class LeSAEp_FLOSC_Plugin {

    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize hooks
        add_action('init', array($this, 'register_rewrite_rules'));
        add_action('template_redirect', array($this, 'handle_virtual_page'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('query_vars', array($this, 'add_query_vars'));

        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once LESAEP_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
        require_once LESAEP_PLUGIN_DIR . 'includes/class-session-manager.php';
        require_once LESAEP_PLUGIN_DIR . 'includes/class-access-control.php';
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Register rewrite rules
        $this->register_rewrite_rules();
        flush_rewrite_rules();

        // Set default options
        add_option('lesaep_app_slug', 'lesaep');
        add_option('lesaep_ai_provider', 'ivr');
        add_option('lesaep_openai_api_key', '');
        add_option('lesaep_anthropic_api_key', '');
        add_option('lesaep_xai_api_key', '');
    }

    /**
     * Plugin deactivation
     */
    public function deactivation() {
        flush_rewrite_rules();
    }

    /**
     * Register rewrite rules for virtual page
     */
    public function register_rewrite_rules() {
        $slug = get_option('lesaep_app_slug', 'lesaep');
        add_rewrite_rule(
            '^' . $slug . '/?$',
            'index.php?lesaep_app=1',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'lesaep_app';
        return $vars;
    }

    /**
     * Handle virtual page rendering
     */
    public function handle_virtual_page() {
        if (get_query_var('lesaep_app')) {
            $this->render_app_template();
            exit;
        }
    }

    /**
     * Render app template (NO WordPress theme)
     */
    private function render_app_template() {
        // Get user state
        $user = wp_get_current_user();
        $is_logged_in = is_user_logged_in();
        $has_paid_access = $this->check_paid_access($user->ID);

        // Determine user state
        if (!$is_logged_in) {
            $user_state = 'visitor';
        } elseif ($has_paid_access) {
            $user_state = 'paid';
        } else {
            $user_state = 'free';
        }

        // Enqueue assets
        wp_enqueue_style('lesaep-app', LESAEP_PLUGIN_URL . 'assets/css/lesaep-app.css', array(), LESAEP_VERSION);
        wp_enqueue_script('lesaep-app', LESAEP_PLUGIN_URL . 'assets/js/lesaep-app.js', array(), LESAEP_VERSION, true);

        // Localize script
        wp_localize_script('lesaep-app', 'LESAEP', array(
            'userState' => $user_state,
            'userId' => $user->ID,
            'userName' => $user->display_name,
            'userEmail' => $user->user_email,
            'userAvatar' => get_avatar_url($user->ID),
            'isLoggedIn' => $is_logged_in,
            'hasPaidAccess' => $has_paid_access,
            'restUrl' => rest_url('flosc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'aiProvider' => get_option('lesaep_ai_provider', 'ivr'),
            'loginUrl' => wp_login_url(home_url(get_option('lesaep_app_slug', 'lesaep'))),
            'signupUrl' => wp_registration_url(),
            'checkoutUrl' => get_option('lesaep_checkout_url', ''),
            'siteUrl' => home_url(),
        ));

        // Include template
        include LESAEP_PLUGIN_DIR . 'templates/lesaep-app.php';
    }

    /**
     * Check if user has paid access
     */
    private function check_paid_access($user_id) {
        if (!$user_id) {
            return false;
        }

        // Check BuddyBoss group membership
        $group_id = get_option('lesaep_paid_group_id', 0);
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) {
                return true;
            }
        }

        // Check user meta flag
        if (get_user_meta($user_id, '_lesaep_paid_access', true)) {
            return true;
        }

        return false;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // AI Query endpoint
        register_rest_route('flosc/v1', '/ai-query', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_ai_query'),
            'permission_callback' => '__return_true',
        ));

        // Session endpoints
        register_rest_route('flosc/v1', '/sessions', array(
            'methods' => 'GET',
            'callback' => array($this, 'list_sessions'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('flosc/v1', '/sessions/(?P<id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'load_session'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('flosc/v1', '/sessions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_session'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route('flosc/v1', '/sessions/(?P<id>[a-zA-Z0-9-]+)/messages', array(
            'methods' => 'POST',
            'callback' => array($this, 'append_message'),
            'permission_callback' => '__return_true',
        ));

        // User access check
        register_rest_route('flosc/v1', '/access', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_access'),
            'permission_callback' => '__return_true',
        ));

        // Referral endpoint
        register_rest_route('flosc/v1', '/referral', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_referral_link'),
            'permission_callback' => function() {
                return is_user_logged_in();
            },
        ));
    }

    /**
     * Handle AI query
     */
    public function handle_ai_query($request) {
        $prompt = sanitize_textarea_field($request->get_param('prompt'));
        $session_id = sanitize_text_field($request->get_param('session_id'));

        if (empty($prompt)) {
            return new WP_REST_Response(array('error' => 'Prompt is required'), 400);
        }

        // Get AI provider
        $provider = LeSAEp_AI_Provider_Factory::get_provider();

        // Get response from AI
        $response = $provider->get_response($prompt);

        if (is_wp_error($response)) {
            return new WP_REST_Response(array('error' => $response->get_error_message()), 500);
        }

        return new WP_REST_Response(array(
            'message' => $response,
            'provider' => get_option('lesaep_ai_provider', 'ivr'),
        ));
    }

    /**
     * List sessions
     */
    public function list_sessions($request) {
        $session_manager = new LeSAEp_Session_Manager();
        $sessions = $session_manager->list_sessions();

        return new WP_REST_Response($sessions);
    }

    /**
     * Load session
     */
    public function load_session($request) {
        $session_id = $request->get_param('id');
        $session_manager = new LeSAEp_Session_Manager();
        $session = $session_manager->load_session($session_id);

        if (!$session) {
            return new WP_REST_Response(array('error' => 'Session not found'), 404);
        }

        return new WP_REST_Response($session);
    }

    /**
     * Create session
     */
    public function create_session($request) {
        $session_manager = new LeSAEp_Session_Manager();
        $session_id = $session_manager->create_session();

        return new WP_REST_Response(array(
            'session_id' => $session_id,
        ));
    }

    /**
     * Append message to session
     */
    public function append_message($request) {
        $session_id = $request->get_param('id');
        $role = sanitize_text_field($request->get_param('role'));
        $content = sanitize_textarea_field($request->get_param('content'));

        $session_manager = new LeSAEp_Session_Manager();
        $result = $session_manager->append_message($session_id, $role, $content);

        if (!$result) {
            return new WP_REST_Response(array('error' => 'Failed to append message'), 500);
        }

        return new WP_REST_Response(array('success' => true));
    }

    /**
     * Check user access
     */
    public function check_access($request) {
        $user = wp_get_current_user();

        return new WP_REST_Response(array(
            'logged_in' => is_user_logged_in(),
            'user_id' => $user->ID,
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'has_paid_access' => $this->check_paid_access($user->ID),
        ));
    }

    /**
     * Generate referral link
     */
    public function generate_referral_link($request) {
        $user = wp_get_current_user();

        if (!$user->ID) {
            return new WP_REST_Response(array('error' => 'User not logged in'), 401);
        }

        // Get or create referral code
        $referral_code = get_user_meta($user->ID, '_lesaep_referral_code', true);

        if (empty($referral_code)) {
            $referral_code = 'lesaep_' . $user->ID . '_' . substr(md5($user->user_email), 0, 6);
            update_user_meta($user->ID, '_lesaep_referral_code', $referral_code);
        }

        $referral_url = add_query_arg('ref', $referral_code, home_url(get_option('lesaep_app_slug', 'lesaep')));

        return new WP_REST_Response(array(
            'referral_code' => $referral_code,
            'referral_url' => $referral_url,
            'share_text' => 'Free Standard American English Accent Evaluation!',
        ));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'LeSAEp Settings',
            'LeSAEp',
            'manage_options',
            'lesaep-settings',
            array($this, 'render_admin_page'),
            'dashicons-microphone',
            30
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting('lesaep_settings', 'lesaep_app_slug');
        register_setting('lesaep_settings', 'lesaep_paid_group_id');
        register_setting('lesaep_settings', 'lesaep_checkout_url');

        // AI settings
        register_setting('lesaep_settings', 'lesaep_ai_provider');
        register_setting('lesaep_settings', 'lesaep_openai_api_key');
        register_setting('lesaep_settings', 'lesaep_anthropic_api_key');
        register_setting('lesaep_settings', 'lesaep_xai_api_key');
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings
        if (isset($_POST['lesaep_save_settings'])) {
            check_admin_referer('lesaep_settings');

            update_option('lesaep_app_slug', sanitize_text_field($_POST['lesaep_app_slug']));
            update_option('lesaep_paid_group_id', absint($_POST['lesaep_paid_group_id']));
            update_option('lesaep_checkout_url', esc_url_raw($_POST['lesaep_checkout_url']));
            update_option('lesaep_ai_provider', sanitize_text_field($_POST['lesaep_ai_provider']));
            update_option('lesaep_openai_api_key', sanitize_text_field($_POST['lesaep_openai_api_key']));
            update_option('lesaep_anthropic_api_key', sanitize_text_field($_POST['lesaep_anthropic_api_key']));
            update_option('lesaep_xai_api_key', sanitize_text_field($_POST['lesaep_xai_api_key']));

            // Flush rewrite rules
            flush_rewrite_rules();

            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        // Get current values
        $app_slug = get_option('lesaep_app_slug', 'lesaep');
        $paid_group_id = get_option('lesaep_paid_group_id', '');
        $checkout_url = get_option('lesaep_checkout_url', '');
        $ai_provider = get_option('lesaep_ai_provider', 'ivr');
        $openai_key = get_option('lesaep_openai_api_key', '');
        $anthropic_key = get_option('lesaep_anthropic_api_key', '');
        $xai_key = get_option('lesaep_xai_api_key', '');

        ?>
        <div class="wrap">
            <h1>LeSAEp FLOSC Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('lesaep_settings'); ?>

                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lesaep_app_slug">App URL Slug</label></th>
                        <td>
                            <input type="text" id="lesaep_app_slug" name="lesaep_app_slug" value="<?php echo esc_attr($app_slug); ?>" class="regular-text">
                            <p class="description">Your app will be accessible at: <?php echo home_url(); ?>/<strong><?php echo esc_html($app_slug); ?></strong>/</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesaep_paid_group_id">BuddyBoss Paid Group ID</label></th>
                        <td>
                            <input type="number" id="lesaep_paid_group_id" name="lesaep_paid_group_id" value="<?php echo esc_attr($paid_group_id); ?>" class="regular-text">
                            <p class="description">Users in this group will have paid access</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesaep_checkout_url">Checkout URL</label></th>
                        <td>
                            <input type="url" id="lesaep_checkout_url" name="lesaep_checkout_url" value="<?php echo esc_attr($checkout_url); ?>" class="regular-text">
                            <p class="description">URL where users can purchase full access</p>
                        </td>
                    </tr>
                </table>

                <h2>AI Provider Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lesaep_ai_provider">AI Provider</label></th>
                        <td>
                            <select id="lesaep_ai_provider" name="lesaep_ai_provider">
                                <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR (Scripted Responses)</option>
                                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (ChatGPT)</option>
                                <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude)</option>
                                <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI (Grok)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesaep_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" id="lesaep_openai_api_key" name="lesaep_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesaep_anthropic_api_key">Anthropic API Key</label></th>
                        <td>
                            <input type="password" id="lesaep_anthropic_api_key" name="lesaep_anthropic_api_key" value="<?php echo esc_attr($anthropic_key); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lesaep_xai_api_key">xAI API Key</label></th>
                        <td>
                            <input type="password" id="lesaep_xai_api_key" name="lesaep_xai_api_key" value="<?php echo esc_attr($xai_key); ?>" class="regular-text">
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings', 'primary', 'lesaep_save_settings'); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize plugin
function lesaep_flosc_init() {
    return LeSAEp_FLOSC_Plugin::get_instance();
}

// Start the plugin
lesaep_flosc_init();
