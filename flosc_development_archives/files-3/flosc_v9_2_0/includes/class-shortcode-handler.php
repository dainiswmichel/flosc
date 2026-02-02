<?php
/**
 * FLOSC Shortcode Handler
 * 
 * Provides access-control shortcodes for content protection:
 * - [flosc_visitor_only]...[/flosc_visitor_only]
 * - [flosc_member_only]...[/flosc_member_only]
 * 
 * @since 9.2.0
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Shortcode_Handler {
    
    private $user_access_manager;
    
    public function __construct() {
        $this->user_access_manager = new FLOSC_User_Access_Manager();
        $this->register_shortcodes();
    }
    
    /**
     * Register all FLOSC shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('flosc_visitor_only', [$this, 'visitor_only_shortcode']);
        add_shortcode('flosc_member_only', [$this, 'member_only_shortcode']);
    }
    
    /**
     * [flosc_visitor_only] shortcode
     * Shows content ONLY to visitors (not logged in)
     * 
     * Usage:
     * [flosc_visitor_only]
     *   Take our free quiz to get started!
     * [/flosc_visitor_only]
     */
    public function visitor_only_shortcode($atts, $content = null) {
        if (empty($content)) {
            return '';
        }
        
        // Get current user access level
        $access_level = $this->user_access_manager->get_access_level();
        
        // Show content ONLY if visitor
        if ($access_level === 'visitor') {
            return do_shortcode($content);
        }
        
        return '';
    }
    
    /**
     * [flosc_member_only] shortcode
     * Shows content ONLY to members (quiz complete OR paid)
     * 
     * Usage:
     * [flosc_member_only]
     *   <h2>Welcome, Member!</h2>
     *   <p>Here's your exclusive content...</p>
     * [/flosc_member_only]
     * 
     * Options:
     * - fallback: Text to show non-members
     * 
     * [flosc_member_only fallback="Upgrade to see this content"]
     *   Member-only content here
     * [/flosc_member_only]
     */
    public function member_only_shortcode($atts, $content = null) {
        if (empty($content)) {
            return '';
        }
        
        // Parse attributes
        $atts = shortcode_atts([
            'fallback' => '', // Optional fallback message
        ], $atts, 'flosc_member_only');
        
        // Get current user access level
        $access_level = $this->user_access_manager->get_access_level();
        
        // Show content ONLY if member
        if ($access_level === 'member') {
            return do_shortcode($content);
        }
        
        // Show fallback message if provided
        if (!empty($atts['fallback'])) {
            return '<div class="flosc-member-only-fallback">' . 
                   esc_html($atts['fallback']) . 
                   '</div>';
        }
        
        return '';
    }
    
    /**
     * Helper: Check if user is logged in
     */
    private function is_logged_in() {
        return is_user_logged_in();
    }
    
    /**
     * Helper: Check if user is member
     */
    private function is_member() {
        return $this->user_access_manager->is_member();
    }
}

// Initialize shortcodes
add_action('init', function() {
    new FLOSC_Shortcode_Handler();
});
