<?php
/**
 * =============================================================================
 * FLOSC SESSION MANAGER
 * =============================================================================
 * 
 * Handles session persistence across page refresh and login.
 * 
 * =============================================================================
 * DUAL STORAGE STRATEGY
 * =============================================================================
 * 
 * 1. localStorage (JavaScript) - Fast, client-side
 *    - Quiz progress
 *    - Score and results
 *    - Current state
 * 
 * 2. WordPress Transients (Server) - Backup, survives browser close
 *    - Keyed by session_id
 *    - 24-hour expiry
 *    - Restored when user returns
 * 
 * This ensures users don't lose progress if they:
 *   - Refresh the page
 *   - Close and reopen browser
 *   - Log in mid-funnel
 * 
 * =============================================================================
 * SESSION FLOW
 * =============================================================================
 * 
 * 1. Anonymous user arrives
 *    - Generate session_id
 *    - Store in localStorage + cookie
 * 
 * 2. User takes quiz
 *    - Save results to localStorage
 *    - Backup to server transient
 * 
 * 3. User logs in
 *    - Restore session by session_id
 *    - Copy session data to user_meta
 *    - Continue from where they left off
 * 
 * 4. User makes purchase
 *    - Session data already in user_meta
 *    - Clear temporary session
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Session_Manager {
    
    const SESSION_EXPIRY = DAY_IN_SECONDS;  // 24 hours
    const COOKIE_NAME = 'flosc_session_id';
    
    /**
     * @var FLOSC_Framework
     */
    private $flosc;
    
    
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
        
        // Register REST routes for session management
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // Restore session on login
        add_action('wp_login', [$this, 'restore_session_on_login'], 10, 2);
    }
    
    
    /* =========================================================================
     * REST API ROUTES
     * =========================================================================
     */
    
    public function register_routes() {
        
        // Save session state
        register_rest_route('flosc/v1', '/session/save', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_save_session'],
            'permission_callback' => '__return_true',
        ]);
        
        // Get session state
        register_rest_route('flosc/v1', '/session/get', [
            'methods'             => 'GET',
            'callback'            => [$this, 'rest_get_session'],
            'permission_callback' => '__return_true',
        ]);
        
        // Clear session
        register_rest_route('flosc/v1', '/session/clear', [
            'methods'             => 'POST',
            'callback'            => [$this, 'rest_clear_session'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    
    /* =========================================================================
     * SAVE SESSION
     * =========================================================================
     */
    
    /**
     * Save session data to server
     * Called periodically by JavaScript
     */
    public function rest_save_session($request) {
        $session_id = $this->get_session_id($request);
        $data = $request->get_json_params();
        
        // Add metadata
        $data['_saved_at'] = current_time('timestamp');
        $data['_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $data['_user_id'] = get_current_user_id();
        
        // Save to transient
        $key = 'flosc_session_' . $session_id;
        set_transient($key, $data, self::SESSION_EXPIRY);
        
        // If user is logged in, also save to user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'flosc_session_data', $data);
        }
        
        return new WP_REST_Response([
            'success'    => true,
            'session_id' => $session_id,
            'expires_in' => self::SESSION_EXPIRY,
        ]);
    }
    
    
    /* =========================================================================
     * GET SESSION
     * =========================================================================
     */
    
    /**
     * Retrieve session data from server
     */
    public function rest_get_session($request) {
        $session_id = $this->get_session_id($request);
        
        // Try user meta first if logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $data = get_user_meta($user_id, 'flosc_session_data', true);
            
            if ($data && is_array($data)) {
                return new WP_REST_Response([
                    'found'  => true,
                    'source' => 'user_meta',
                    'data'   => $data,
                ]);
            }
        }
        
        // Try transient
        $key = 'flosc_session_' . $session_id;
        $data = get_transient($key);
        
        if ($data && is_array($data)) {
            return new WP_REST_Response([
                'found'  => true,
                'source' => 'transient',
                'data'   => $data,
            ]);
        }
        
        return new WP_REST_Response([
            'found'   => false,
            'message' => 'No session found',
        ]);
    }
    
    
    /* =========================================================================
     * CLEAR SESSION
     * =========================================================================
     */
    
    /**
     * Clear session data (e.g., after purchase or reset)
     */
    public function rest_clear_session($request) {
        $session_id = $this->get_session_id($request);
        
        // Delete transient
        $key = 'flosc_session_' . $session_id;
        delete_transient($key);
        
        // Clear user meta if logged in
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            delete_user_meta($user_id, 'flosc_session_data');
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Session cleared',
        ]);
    }
    
    
    /* =========================================================================
     * SESSION RESTORE ON LOGIN
     * =========================================================================
     * 
     * When user logs in, restore their anonymous session data
     * and copy it to their user account.
     */
    
    /**
     * Hook: wp_login
     * Restores session when user logs in
     */
    public function restore_session_on_login($user_login, $user) {
        // Get session ID from cookie
        $session_id = $_COOKIE[self::COOKIE_NAME] ?? '';
        
        if (!$session_id) {
            return;
        }
        
        // Get anonymous session data
        $key = 'flosc_session_' . $session_id;
        $data = get_transient($key);
        
        if (!$data || !is_array($data)) {
            return;
        }
        
        // Copy to user meta
        update_user_meta($user->ID, 'flosc_session_data', $data);
        
        // If session has quiz results, also save those separately
        if (isset($data['quizResults'])) {
            update_user_meta($user->ID, 'flosc_quiz_results', $data['quizResults']);
        }
        
        if (isset($data['missedItems'])) {
            update_user_meta($user->ID, 'flosc_needed_lessons', $data['missedItems']);
        }
        
        error_log("FLOSC: Restored session for user {$user->ID} from session {$session_id}");
        
        // Clean up anonymous transient
        delete_transient($key);
    }
    
    
    /* =========================================================================
     * HELPER METHODS
     * =========================================================================
     */
    
    /**
     * Get session ID from request or generate new one
     */
    private function get_session_id($request) {
        // Try from request parameter
        $session_id = $request->get_param('session_id');
        
        if ($session_id) {
            return sanitize_key($session_id);
        }
        
        // Try from cookie
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            return sanitize_key($_COOKIE[self::COOKIE_NAME]);
        }
        
        // Generate new
        return $this->generate_session_id();
    }
    
    
    /**
     * Generate unique session ID
     */
    private function generate_session_id() {
        return 'flosc_' . wp_generate_password(24, false);
    }
}
