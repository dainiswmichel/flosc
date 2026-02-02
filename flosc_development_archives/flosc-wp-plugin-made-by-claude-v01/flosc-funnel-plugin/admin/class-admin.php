<?php
if (!defined('ABSPATH')) exit;

class FLOSC_Admin {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
    }
    
    public function add_menu() {
        add_menu_page('FLOSC Funnel', 'FLOSC Funnel', 'manage_options', 'flosc-funnel', array($this, 'dashboard'), 'dashicons-chart-line', 30);
    }
    
    public function dashboard() {
        echo '<div class="wrap"><h1>FLOSC Funnel</h1><p>Your AI-powered course funnel is active!</p></div>';
    }
}
