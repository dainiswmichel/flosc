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
            'github'    => 'GitHub_Provider',
            'twitter'   => 'Twitter_Provider',
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
        
        // Login form integration
        add_action('login_form', array($this, 'add_sso_buttons_to_login'));
        add_action('register_form', array($this, 'add_sso_buttons_to_login'));
        
        // Handle SSO errors on frontend
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
            echo $provider->get_login_button_html();
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
        
        echo '<style>
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
        </style>';
        
        // Add click handler script
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".flosc-sso-button").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    var provider = this.dataset.provider;
                    var redirectTo = window.location.href;
                    window.location.href = "' . rest_url('flosc/v1/sso/authorize/') . '" + provider + "?redirect_to=" + encodeURIComponent(redirectTo);
                });
            });
        });
        </script>';
    }
    
    /**
     * Handle SSO error display on frontend
     */
    public function handle_sso_error_display() {
        if (isset($_GET['flosc_sso_error'])) {
            $error_key = sanitize_text_field($_GET['flosc_sso_error']);
            $error_message = get_transient($error_key);
            
            if ($error_message) {
                delete_transient($error_key);
                
                // Store for display
                add_action('wp_footer', function() use ($error_message) {
                    echo '<script>alert("Login Error: ' . esc_js($error_message) . '");</script>';
                });
            }
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
                register_setting('flosc_sso_settings', $field['id']);
                
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
     * Render settings section description
     */
    public function render_settings_section() {
        echo '<p>' . __('Configure social login providers. You will need to create OAuth applications with each provider.', 'flosc') . '</p>';
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
                
            case 'password':
                printf(
                    '<input type="password" id="%s" name="%s" value="%s" class="regular-text" />',
                    esc_attr($field['id']),
                    esc_attr($field['id']),
                    esc_attr($value)
                );
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
        
        $provider_id = sanitize_text_field($_POST['provider'] ?? '');
        
        if (!$this->has_provider($provider_id)) {
            wp_send_json_error('Invalid provider');
        }
        
        $user_id = get_current_user_id();
        
        // Check if this is their only login method
        $linked = $this->user_linker->get_linked_providers($user_id);
        $user = get_userdata($user_id);
        $has_password = !empty($user->user_pass) && $user->user_pass !== wp_hash_password('');
        
        if (count($linked) <= 1 && !$has_password) {
            wp_send_json_error('Cannot unlink your only login method. Please set a password first.');
        }
        
        $this->user_linker->unlink_account($user_id, $provider_id);
        
        wp_send_json_success(array(
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
    
    /**
     * Get SSO buttons HTML for FLOSC chat widget
     * 
     * @return string HTML
     */
    public function get_chat_sso_buttons_html() {
        $providers = $this->get_enabled_providers();
        
        if (empty($providers)) {
            return '';
        }
        
        $html = '<div class="flosc-chat-sso-buttons">';
        $html .= '<div class="flosc-sso-divider"><span>or continue with</span></div>';
        
        foreach ($providers as $provider) {
            $html .= sprintf(
                '<button type="button" class="flosc-chat-sso-btn flosc-chat-sso-%s" data-provider="%s" data-auth-url="%s">
                    <span class="sso-icon">%s</span>
                    <span class="sso-label">%s</span>
                </button>',
                esc_attr($provider->get_id()),
                esc_attr($provider->get_id()),
                esc_url(rest_url("flosc/v1/sso/authorize/{$provider->get_id()}")),
                $provider->get_icon(),
                esc_html($provider->get_name())
            );
        }
        
        $html .= '</div>';
        
        return $html;
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
