
<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.io
 * Description: Freeline-Login-Offer-Sale-Content - Quiz-based learning and conversational sales funnel framework
 * Version: 9.7.1
 * Author: Dainis Michel
 * Author URI: https://dainismichel.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flosc
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '9.7.1');
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main FLOSC Framework Class
 */
class FLOSC_Framework {
	private static $instance = null;
	private $ai_factory;
	private $stt_factory;
	private $quiz_factory;
	private $session_manager;
	private $pronunciation_analyzer;
	private $sale_manager;
	private $user_access_manager;
	private $content_filter;
	private $rag_manager;
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
	private $lesson_manager;
	private function load_dependencies() {
		// Core components
		require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-provider-factory.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-provider-factory.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-type-factory.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-session-manager.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-manager.php';
		// IVR system (v07.08)
		require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
		// RAG system (v9.1.6)
		require_once FLOSC_PLUGIN_DIR . 'includes/class-user-access-manager.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-content-filter.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-rag-manager.php';
		require_once FLOSC_PLUGIN_DIR . 'includes/class-access-validator.php'; // v9.1.7
		require_once FLOSC_PLUGIN_DIR . 'includes/class-free-lesson-manager.php'; // v9.1.8
		require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php'; // v9.1.8
		// SALE system
		require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-sale-manager.php';
		// ...existing code...
	}
	// ...existing code...
}

// Register activation hook
register_activation_hook(__FILE__, 'flosc_activate');

// Start the plugin
add_action('plugins_loaded', 'flosc');
