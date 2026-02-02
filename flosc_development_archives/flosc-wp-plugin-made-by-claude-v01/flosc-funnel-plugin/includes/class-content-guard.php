<?php
/**
 * Content Guard - Protect lesson content
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Content_Guard {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_filter('the_content', array($this, 'protect_lesson_content'));
    }
    
    /**
     * Protect lesson content
     */
    public function protect_lesson_content($content) {
        if (!is_singular('flosc_lesson')) {
            return $content;
        }
        
        global $post;
        
        $is_free = get_post_meta($post->ID, '_flosc_is_free', true) === '1';
        
        // If free lesson, show it
        if ($is_free) {
            return $content;
        }
        
        // Check if user has paid
        if (!is_user_logged_in()) {
            return $this->get_login_message();
        }
        
        $user_id = get_current_user_id();
        $has_paid = $this->user_has_access($user_id);
        
        if (!$has_paid) {
            return $this->get_upgrade_message();
        }
        
        return $content;
    }
    
    /**
     * Check if user has access
     */
    public function user_has_access($user_id) {
        // Check user meta
        $has_paid_meta = get_user_meta($user_id, 'flosc_has_paid', true);
        if ($has_paid_meta) {
            return true;
        }
        
        // Check quiz sessions
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        return $quiz_handler->user_has_paid($user_id);
    }
    
    /**
     * Get login message
     */
    private function get_login_message() {
        $login_url = wp_login_url(get_permalink());
        
        ob_start();
        ?>
        <div class="flosc-content-locked">
            <div class="flosc-lock-icon">🔒</div>
            <h3><?php _e('Login Required', 'flosc-funnel'); ?></h3>
            <p><?php _e('Please log in to access this lesson.', 'flosc-funnel'); ?></p>
            <a href="<?php echo esc_url($login_url); ?>" class="button"><?php _e('Log In', 'flosc-funnel'); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get upgrade message
     */
    private function get_upgrade_message() {
        $offer_page_id = get_option('flosc_offer_page');
        $offer_url = $offer_page_id ? get_permalink($offer_page_id) : '#';
        
        ob_start();
        ?>
        <div class="flosc-content-locked">
            <div class="flosc-lock-icon">🎁</div>
            <h3><?php _e('Premium Content', 'flosc-funnel'); ?></h3>
            <p><?php _e('Upgrade to access all lessons and master your pronunciation!', 'flosc-funnel'); ?></p>
            <a href="<?php echo esc_url($offer_url); ?>" class="button button-primary"><?php _e('Upgrade Now', 'flosc-funnel'); ?></a>
        </div>
        <?php
        return ob_get_clean();
    }
}
