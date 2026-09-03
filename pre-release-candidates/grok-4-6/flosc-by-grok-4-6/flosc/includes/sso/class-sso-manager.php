<?php
/**
 * SSO Manager Class
 * 
 * Main entry point for FLOSC Social Login functionality.
 * Registers providers, initializes OAuth2 handler, and manages the SSO system.
 * 
 * @package FLOSC
 * @subpackage SSO
 * @since 1.4.0
 */

namespace FLOSC\SSO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSO Manager Class
 */
class SSO_Manager {
    
    /**
     * Singleton instance
     * @var SSO_Manager
     */
    private static $instance = null;
    
    /**
     * Registered providers
     * @var array
     */
    private $providers = array();
    
    /**
     * OAuth2 Handler
     * @var OAuth2_Handler
     */
    private $oauth2_handler;
    
    /**
     * User Linker
     * @var User_Linker
     */
    private $user_linker;
    
    /**
     * Get singleton instance
     * 
     * @return SSO_Manager
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
        $this->user_linker = new User_Linker();
        $this->oauth2_handler = new OAuth2_Handler($this);
    }
    
    /**
     * Initialize the SSO system
     */
    public function init() {
        // Load provider classes
        $this->load_providers();
        
        // Initialize OAuth2 handler
        $this->oauth2_handler->init();
        
        // Register hooks
        $this->register_hooks();
        
        do_action('flosc_sso_initialized', $this);
    }
    
    /**
     * Load all provider classes
     */
    private function load_providers() {
        $providers_dir = FLOSC_PLUGIN_DIR . 'includes/sso/providers/';
        
        // Define available providers
        $provider_classes = array(
            'google'    => 'Google_Provider',
            'apple'     => 'Apple_Provider',
            'facebook'  => 'Facebook_Provider',
            'microsoft' => 'Microsoft_Provider',
            'linkedin'  => 'LinkedIn_Provider',
        );
        
        // Allow filtering of available providers
        $provider_classes = apply_filters('flosc_sso_providers', $provider_classes);
        
        foreach ($provider_classes as $provider_id => $class_name) {
            $file = $providers_dir . 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                
                $full_class = __NAMESPACE__ . '\\Providers\\' . $class_name;
                
                if (class_exists($full_class)) {
                    $this->register_provider(new $full_class());
                }
            }
        }
        
        // Allow manual provider registration
        do_action('flosc_sso_register_providers', $this);
    }
    
    /**
     * Register hooks
     */
    private function register_hooks() {
        // Avatar filter
        add_filter('get_avatar_url', array($this, 'filter_avatar_url'), 10, 3);
        
        // v1.4.8: FLOSC SSO buttons only appear inside FLOSC flows (chat widget auth modal).
        // Removed login_form and register_form hooks to prevent interference with
        // BuddyBoss or other site-wide login systems.
        
        // Handle SSO errors on frontend (only on FLOSC pages)
        add_action('wp_loaded', array($this, 'handle_sso_error_display'));
        
        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // AJAX endpoints for frontend
        add_action('wp_ajax_flosc_unlink_sso', array($this, 'ajax_unlink_provider'));
        add_action('wp_ajax_flosc_get_linked_accounts', array($this, 'ajax_get_linked_accounts'));
    }
    
    /**
     * Register a provider
     * 
     * @param SSO_Provider_Base $provider Provider instance
     */
    public function register_provider($provider) {
        if ($provider instanceof SSO_Provider_Base) {
            $this->providers[$provider->get_id()] = $provider;
        }
    }
    
    /**
     * Check if a provider exists
     * 
     * @param string $provider_id Provider ID
     * @return bool
     */
    public function has_provider($provider_id) {
        return isset($this->providers[$provider_id]);
    }
    
    /**
     * Get a provider instance
     * 
     * @param string $provider_id Provider ID
     * @return SSO_Provider_Base|null
     */
    public function get_provider($provider_id) {
        return isset($this->providers[$provider_id]) ? $this->providers[$provider_id] : null;
    }
    
    /**
     * Get all registered providers
     * 
     * @return array
     */
    public function get_providers() {
        return $this->providers;
    }
    
    /**
     * Get all enabled providers
     * 
     * @return array
     */
    public function get_enabled_providers() {
        return array_filter($this->providers, function($provider) {
            return $provider->is_enabled();
        });
    }
    
    /**
     * Get the User Linker instance
     * 
     * @return User_Linker
     */
    public function get_user_linker() {
        return $this->user_linker;
    }
    
    /**
     * Get the OAuth2 Handler instance
     * 
     * @return OAuth2_Handler
     */
    public function get_oauth2_handler() {
        return $this->oauth2_handler;
    }
    
    /**
     * Filter avatar URL to use SSO avatar
     * 
     * @param string $url Current avatar URL
     * @param mixed $id_or_email User ID or email
     * @param array $args Avatar arguments
     * @return string
     */
    public function filter_avatar_url($url, $id_or_email, $args) {
        // Get user ID
        $user_id = null;
        
        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
            }
        } else {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        }
        
        if (!$user_id) {
            return $url;
        }
        
        // Check for SSO avatar
        $sso_avatar = $this->user_linker->get_sso_avatar($user_id);
        
        if ($sso_avatar) {
            return $sso_avatar;
        }
        
        return $url;
    }
    
    /**
     * Add SSO buttons to WordPress login form
     */
    public function add_sso_buttons_to_login() {
        $providers = $this->get_enabled_providers();
        
        if (empty($providers)) {
            return;
        }
        
        echo '<div class="flosc-sso-login-buttons">';
        echo '<p class="flosc-sso-separator"><span>or continue with</span></p>';
        
        foreach ($providers as $provider) {
            echo wp_kses_post($provider->get_login_button_html());
        }
        
        echo '</div>';
        
        // Add inline styles
        $this->output_login_button_styles();
    }
    
    /**
     * Output login button styles
     */
    private function output_login_button_styles() {
        static $styles_output = false;
        if ($styles_output) return;
        $styles_output = true;
        
        // §12: SSO button styles via an inline-only style handle instead of a raw <style> tag.
        wp_register_style('flosc-sso', false, [], FLOSC_VERSION);
        wp_enqueue_style('flosc-sso');
        wp_add_inline_style('flosc-sso', '
        .flosc-sso-login-buttons {
            margin: 20px 0;
            text-align: center;
        }
        .flosc-sso-separator {
            position: relative;
            margin: 20px 0;
            text-align: center;
            color: #666;
        }
        .flosc-sso-separator::before,
        .flosc-sso-separator::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: #ddd;
        }
        .flosc-sso-separator::before { left: 0; }
        .flosc-sso-separator::after { right: 0; }
        .flosc-sso-separator span {
            background: #fff;
            padding: 0 10px;
        }
        .flosc-sso-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 10px 16px;
            margin: 8px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        .flosc-sso-button:hover {
            background: #f5f5f5;
            border-color: #999;
        }
        .flosc-sso-icon {
            margin-right: 10px;
            font-size: 18px;
        }
        .flosc-sso-google { background: #fff; color: #757575; }
        .flosc-sso-google:hover { background: #f5f5f5; }
        .flosc-sso-facebook { background: #1877f2; color: #fff; border-color: #1877f2; }
        .flosc-sso-facebook:hover { background: #166fe5; }
        .flosc-sso-apple { background: #000; color: #fff; border-color: #000; }
        .flosc-sso-apple:hover { background: #333; }
        .flosc-sso-microsoft { background: #2f2f2f; color: #fff; border-color: #2f2f2f; }
        .flosc-sso-microsoft:hover { background: #444; }
        .flosc-sso-linkedin { background: #0a66c2; color: #fff; border-color: #0a66c2; }
        .flosc-sso-linkedin:hover { background: #004182; }
        ');

        // Add click handler script
        // v1.4.6: Use URL-safe separator (handles non-pretty permalinks)
        // §12: attached via an inline-only script handle instead of a raw <script> tag.
        wp_register_script('flosc-sso', false, [], FLOSC_VERSION, true);
        wp_enqueue_script('flosc-sso');
        wp_add_inline_script('flosc-sso', '
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".flosc-sso-button").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var provider = this.dataset.provider;
                    // v1.5.0: Extract redirect_to from URL params (e.g. wp-login.php?redirect_to=flosc.ai)
                    // instead of capturing the login page URL itself
                    var urlParams = new URLSearchParams(window.location.search);
                    var redirectTo = urlParams.get("redirect_to") || window.location.href;
                    var baseUrl = ' . wp_json_encode(rest_url('flosc/v1/sso/authorize/')) . ' + provider;
                    var separator = baseUrl.indexOf("?") !== -1 ? "&" : "?";
                    window.location.href = baseUrl + separator + "redirect_to=" + encodeURIComponent(redirectTo);
                });
            });
        });
        ');
    }
    
    /**
     * Handle SSO error display on frontend
     * 
     * v8.0.1: Instead of a browser alert(), output a JS variable that flosc-app.js
     * picks up on init. This lets the app show the error in-chat and re-show the
     * auth modal so the user can try a different login method.
     */
    public function handle_sso_error_display() {
        $err_raw = filter_input( INPUT_GET, 'flosc_sso_error', FILTER_UNSAFE_RAW );
        if ( is_string( $err_raw ) && $err_raw !== '' ) {
            $error_token = sanitize_key( wp_unslash( $err_raw ) );
            if ($error_token === '') {
                return;
            }

            $error_key = 'flosc_sso_error_' . $error_token;
            $error_message = get_transient($error_key);
            
            if ($error_message) {
                delete_transient($error_key);
            } else {
                // v8.0.2: Transient expired (TTL elapsed) or was never found.
                // Show a generic message so the user isn't left with zero feedback.
                $error_message = 'Login didn\'t complete. Please try again — if the issue persists, try a different login method or contact support.';
            }
            
            // v8.0.1: Set a JS variable instead of alert() so flosc-app.js can
            // show the error in-chat and re-present the auth modal
            add_action('wp_footer', function() use ($error_message) {
                // §12: emit via an inline-only script handle instead of a raw <script> tag.
                wp_register_script('flosc-sso-error', false, [], FLOSC_VERSION, true);
                wp_enqueue_script('flosc-sso-error');
                wp_add_inline_script('flosc-sso-error', 'window.flosc_sso_error = ' . wp_json_encode($error_message) . ';');
            });
        }
    }
    
    /**
     * Register admin settings
     */
    public function register_settings() {
        // Register setting section
        add_settings_section(
            'flosc_sso_settings',
            __('Social Login Settings', 'flosc'),
            array($this, 'render_settings_section'),
            'flosc-sso'
        );
        
        // Register settings for each provider
        foreach ($this->providers as $provider) {
            $fields = $provider->get_settings_fields();
            
            foreach ($fields as $field) {
                $field_type = $field['type'] ?? '';

                $field_id = (string) ( $field['id'] ?? '' );
                // PEM / private keys: always secret pass-through (even when UI type is textarea).
                // Must be checked before the generic textarea branch (Pass 2 audit).
                $is_pem_field = ( false !== strpos( $field_id, 'private_key' ) );
                $is_secret_field = ( $field_type === 'password' || $field_type === 'secret' || $is_pem_field );

                if ($field_type === 'checkbox') {
                    $setting_args = array(
                        'type'              => 'integer',
                        'sanitize_callback' => array($this, 'sanitize_checkbox_setting'),
                        'default'           => $field['default'] ?? 0,
                    );
                } elseif ( $is_secret_field ) {
                    // Client secrets / PEM: empty-preserve + no sanitize_text_field (Jul email ✨).
                    $setting_args = array(
                        'type'              => 'string',
                        'sanitize_callback' => array( $this, 'sanitize_secret_setting' ),
                        'default'           => $field['default'] ?? '',
                    );
                } elseif ($field_type === 'textarea') {
                    $setting_args = array(
                        'type'              => 'string',
                        'sanitize_callback' => array($this, 'sanitize_textarea_setting'),
                        'default'           => $field['default'] ?? '',
                    );
                } else {
                    $setting_args = array(
                        'type'              => 'string',
                        'sanitize_callback' => array($this, 'sanitize_text_setting'),
                        'default'           => $field['default'] ?? '',
                    );
                }

                register_setting(
                    'flosc_sso_settings',
                    $field['id'],
                    array(
                        'type'              => $setting_args['type'],
                        'sanitize_callback' => $setting_args['sanitize_callback'],
                        'default'           => $setting_args['default'],
                    )
                );
                
                add_settings_field(
                    $field['id'],
                    $field['title'],
                    array($this, 'render_setting_field'),
                    'flosc-sso',
                    'flosc_sso_settings',
                    $field
                );
            }
        }
    }

    /**
     * Sanitize text-like SSO settings.
     *
     * @param mixed $value Raw submitted value.
     * @return string
     */
    public function sanitize_text_setting($value) {
        return sanitize_text_field(wp_unslash((string) $value));
    }

    /**
     * Sanitize textarea SSO settings (preserves PEM newlines).
     *
     * @param mixed $value Raw submitted value.
     * @return string
     */
    public function sanitize_textarea_setting($value) {
        return sanitize_textarea_field(wp_unslash((string) $value));
    }

    /**
     * Sanitize checkbox SSO settings to 0/1.
     *
     * @param mixed $value Raw submitted value.
     * @return int
     */
    public function sanitize_checkbox_setting($value) {
        return !empty($value) ? 1 : 0;
    }

    /**
     * Pass-through sanitizer for OAuth client secrets and similar credentials.
     *
     * Empty form fields keep the stored secret (password inputs submit empty when
     * left unchanged). Option name is resolved from sanitize_option_{$option}.
     *
     * @param mixed $value Raw submitted value.
     * @return string
     */
    public function sanitize_secret_setting($value) {
        $option_name = '';
        $filter      = current_filter();
        if (is_string($filter) && 0 === strpos($filter, 'sanitize_option_')) {
            $option_name = substr($filter, strlen('sanitize_option_'));
        }

        if (!is_string($value)) {
            return $option_name !== '' ? (string) get_option($option_name, '') : '';
        }

        $value = wp_unslash($value);
        if ('' === $value) {
            return $option_name !== '' ? (string) get_option($option_name, '') : '';
        }

        return $value;
    }
    
    /**
     * Render settings section description
     */
    public function render_settings_section() {
        echo '<p>' . esc_html__('Configure social login providers. You will need to create OAuth applications with each provider.', 'flosc') . '</p>';
    }
    
    /**
     * Render a setting field
     * 
     * @param array $field Field configuration
     */
    public function render_setting_field($field) {
        $value = get_option($field['id'], $field['default'] ?? '');
        
        switch ($field['type']) {
            case 'checkbox':
                printf(
                    '<input type="checkbox" id="%s" name="%s" value="1" %s />',
                    esc_attr($field['id']),
                    esc_attr($field['id']),
                    checked($value, 1, false)
                );
                break;

            case 'textarea':
                $flosc_ta_value = (string) $value;
                $flosc_is_pem = (false !== strpos((string) ($field['id'] ?? ''), 'private_key'));
                if ($flosc_is_pem && !flosc_admin_may_view_secrets()) {
                    $flosc_ta_value = '';
                }
                printf(
                    '<textarea id="%s" name="%s" rows="6" class="large-text code" autocomplete="off">%s</textarea>',
                    esc_attr($field['id']),
                    esc_attr($field['id']),
                    esc_textarea($flosc_ta_value)
                );
                if ($flosc_is_pem && !flosc_admin_may_view_secrets() && (string) $value !== '') {
                    echo '<p class="description">' . esc_html__('Key is saved. Leave blank to keep the current value.', 'flosc') . '</p>';
                }
                break;
                
            case 'password':
                // Editors with flow access must not read secrets from page source.
                $flosc_show_secret = function_exists('flosc_admin_secret_input_value')
                    ? flosc_admin_secret_input_value($value)
                    : (current_user_can('manage_options') ? (string) $value : '');
                $flosc_has_saved = ((string) $value !== '' && !flosc_admin_may_view_secrets());
                printf(
                    '<input type="password" id="%s" name="%s" value="%s" class="regular-text" autocomplete="new-password" placeholder="%s" />',
                    esc_attr($field['id']),
                    esc_attr($field['id']),
                    esc_attr($flosc_show_secret),
                    esc_attr($flosc_has_saved ? '••••••••' : '')
                );
                if ($flosc_has_saved) {
                    echo '<p class="description">' . esc_html__('Secret is saved. Leave blank to keep the current value.', 'flosc') . '</p>';
                }
                break;
                
            default:
                printf(
                    '<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
                    esc_attr($field['id']),
                    esc_attr($field['id']),
                    esc_attr($value)
                );
        }
        
        if (!empty($field['description'])) {
            printf('<p class="description">%s</p>', esc_html($field['description']));
        }
    }
    
    /**
     * AJAX handler: Unlink a provider from current user
     */
    public function ajax_unlink_provider() {
        check_ajax_referer('flosc_sso_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $provider_id = sanitize_text_field(wp_unslash($_POST['provider'] ?? ''));
        
        if (!$this->has_provider($provider_id)) {
            wp_send_json_error('Invalid provider');
        }
        
        $user_id = get_current_user_id();
        
        // Check if this is their only login method
        $linked = $this->user_linker->get_linked_providers($user_id);
        $user = get_userdata($user_id);
        // v1.4.9: wp_hash_password() generates random salt each call, so direct comparison never works.
        // Instead, use wp_check_password() against empty string to detect if user has a real password.
        $has_password = !empty($user->user_pass) && !wp_check_password('', $user->user_pass, $user_id);
        
        if (count($linked) <= 1 && !$has_password) {
            wp_send_json_error('Cannot unlink your only login method. Please set a password first.');
        }
        
        $this->user_linker->unlink_account($user_id, $provider_id);
        
        wp_send_json_success(array(
            /* translators: %s: provider name. */
            'message' => sprintf(__('%s account unlinked successfully.', 'flosc'), ucfirst($provider_id)),
            'linked_providers' => $this->user_linker->get_linked_providers($user_id),
        ));
    }
    
    /**
     * AJAX handler: Get linked accounts for current user
     */
    public function ajax_get_linked_accounts() {
        check_ajax_referer('flosc_sso_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        $user_id = get_current_user_id();
        $linked = $this->user_linker->get_linked_providers($user_id);
        
        $accounts = array();
        foreach ($linked as $provider_id) {
            $info = $this->user_linker->get_linked_account_info($user_id, $provider_id);
            if ($info) {
                $provider = $this->get_provider($provider_id);
                $info['provider_name'] = $provider ? $provider->get_name() : ucfirst($provider_id);
                $info['provider_icon'] = $provider ? $provider->get_icon() : '';
                $accounts[] = $info;
            }
        }
        
        wp_send_json_success(array(
            'linked_accounts' => $accounts,
            'available_providers' => array_keys(array_diff_key($this->get_enabled_providers(), array_flip($linked))),
        ));
    }
}

/**
 * Get SSO Manager instance
 * 
 * @return SSO_Manager
 */
function flosc_sso() {
    return SSO_Manager::get_instance();
}
