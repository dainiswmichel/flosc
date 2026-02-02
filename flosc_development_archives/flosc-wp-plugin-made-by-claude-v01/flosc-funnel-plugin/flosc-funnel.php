<?php
/**
 * Plugin Name: FLOSC Funnel - AI-Powered Course Sales System
 * Plugin URI: https://yoursite.com/flosc-funnel
 * Description: Complete sales funnel with AI speech analysis. Freeline chatbot, personalized offers, automated email sequences, and content delivery.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Your Name
 * Author URI: https://yoursite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc-funnel
 * Domain Path: /languages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FLOSC_VERSION', '1.0.0');
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FLOSC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main FLOSC Funnel Class
 */
final class FLOSC_Funnel {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once FLOSC_PLUGIN_DIR . 'includes/class-installer.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-api-bridge.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-handler.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-email-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-payment-handler.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-content-guard.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-cpt.php';
        
        // Admin
        if (is_admin()) {
            require_once FLOSC_PLUGIN_DIR . 'admin/class-admin.php';
            require_once FLOSC_PLUGIN_DIR . 'admin/class-settings.php';
            require_once FLOSC_PLUGIN_DIR . 'admin/class-analytics.php';
        }
        
        // Public
        require_once FLOSC_PLUGIN_DIR . 'public/class-shortcodes.php';
        require_once FLOSC_PLUGIN_DIR . 'public/class-ajax-handler.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array('FLOSC_Installer', 'activate'));
        register_deactivation_hook(__FILE__, array('FLOSC_Installer', 'deactivate'));
        
        // Init
        add_action('init', array($this, 'init'), 0);
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        FLOSC_Lesson_CPT::instance();
        FLOSC_Shortcodes::instance();
        FLOSC_Ajax_Handler::instance();
        
        if (is_admin()) {
            FLOSC_Admin::instance();
            FLOSC_Settings::instance();
            FLOSC_Analytics::instance();
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('flosc-funnel', false, dirname(FLOSC_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue public assets
     */
    public function enqueue_public_assets() {
        // Only load on pages with FLOSC shortcodes
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'flosc_chatbot') && 
            !has_shortcode($post->post_content, 'flosc_offer') && 
            !has_shortcode($post->post_content, 'flosc_dashboard')) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'flosc-public',
            FLOSC_PLUGIN_URL . 'assets/css/public.css',
            array(),
            FLOSC_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'flosc-app',
            FLOSC_PLUGIN_URL . 'assets/js/app.js',
            array('jquery'),
            FLOSC_VERSION,
            true
        );
        
        wp_enqueue_script(
            'flosc-chatbot',
            FLOSC_PLUGIN_URL . 'assets/js/chatbot.js',
            array('jquery', 'flosc-app'),
            FLOSC_VERSION,
            true
        );
        
        wp_enqueue_script(
            'flosc-recorder',
            FLOSC_PLUGIN_URL . 'assets/js/recorder.js',
            array('jquery'),
            FLOSC_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('flosc-app', 'floscData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flosc_nonce'),
            'apiUrl' => get_option('flosc_api_url', 'http://localhost:8000'),
            'maxNoAttempts' => get_option('flosc_max_no_attempts', 3),
            'productName' => get_option('flosc_product_name', ''),
            'magicSentences' => $this->get_magic_sentences(),
            'currentUserId' => get_current_user_id(),
            'isLoggedIn' => is_user_logged_in()
        ));
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on FLOSC admin pages
        if (strpos($hook, 'flosc') === false && $hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_style(
            'flosc-admin',
            FLOSC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            FLOSC_VERSION
        );
        
        wp_enqueue_script(
            'flosc-admin',
            FLOSC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            FLOSC_VERSION,
            true
        );
        
        wp_localize_script('flosc-admin', 'floscAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('flosc_admin_nonce')
        ));
    }
    
    /**
     * Get magic sentences
     */
    private function get_magic_sentences() {
        return array(
            get_option('flosc_magic_sentence_1', 'The cat sat on the mat'),
            get_option('flosc_magic_sentence_2', 'She sells seashells by the seashore'),
            get_option('flosc_magic_sentence_3', 'How now brown cow')
        );
    }
}

/**
 * Initialize plugin
 */
function flosc_funnel() {
    return FLOSC_Funnel::instance();
}

// Start the plugin
flosc_funnel();
