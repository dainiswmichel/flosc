<?php
/**
 * FLOSC Session Manager
 *
 * Persists chat state across page refresh using WordPress transients
 * JavaScript handles localStorage, this class provides server-side backup
 *
 * Stored data:
 * - Current state (FREELINE, LOGIN_GATE, RESULTS, etc)
 * - Quiz score
 * - Quiz details
 * - Timestamps
 *
 * Session expires after 24 hours
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Session_Manager {

    const SESSION_EXPIRY = DAY_IN_SECONDS; // 24 hours

    public function __construct() {
        // REST endpoint to save/restore session
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST routes for session management
     */
    public function register_routes() {
        // Save session
        register_rest_route('flosc/v1', '/session/save', [
            'methods' => 'POST',
            'callback' => array($this, 'save_session'),
            'permission_callback' => '__return_true',
        ]);

        // Get session
        register_rest_route('flosc/v1', '/session/get', [
            'methods' => 'GET',
            'callback' => array($this, 'get_session'),
            'permission_callback' => '__return_true',
        ]);

        // Clear session
        register_rest_route('flosc/v1', '/session/clear', [
            'methods' => 'POST',
            'callback' => array($this, 'clear_session'),
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Save session data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function save_session($request) {
        $session_id = $this->get_session_id($request);
        $data = $request->get_json_params();

        // Add metadata
        $data['timestamp'] = current_time('timestamp');
        $data['user_id'] = get_current_user_id();

        // Save as transient (24 hour expiry)
        set_transient('flosc_session_' . $session_id, $data, self::SESSION_EXPIRY);

        return new WP_REST_Response([
            'success' => true,
            'session_id' => $session_id,
            'expires_in' => self::SESSION_EXPIRY,
        ]);
    }

    /**
     * Get session data
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_session($request) {
        $session_id = $this->get_session_id($request);
        $data = get_transient('flosc_session_' . $session_id);

        if (!$data) {
            return new WP_REST_Response([
                'found' => false,
                'message' => 'No session found or session expired',
            ]);
        }

        // Check if expired (shouldn't happen with transients, but belt-and-suspenders)
        $age = current_time('timestamp') - ($data['timestamp'] ?? 0);
        if ($age > self::SESSION_EXPIRY) {
            $this->clear_session_by_id($session_id);
            return new WP_REST_Response([
                'found' => false,
                'message' => 'Session expired',
            ]);
        }

        return new WP_REST_Response([
            'found' => true,
            'data' => $data,
            'age_seconds' => $age,
        ]);
    }

    /**
     * Clear session
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function clear_session($request) {
        $session_id = $this->get_session_id($request);
        $this->clear_session_by_id($session_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Session cleared',
        ]);
    }

    /**
     * Clear session by ID
     *
     * @param string $session_id
     */
    private function clear_session_by_id($session_id) {
        delete_transient('flosc_session_' . $session_id);
    }

    /**
     * Get session ID from request
     * Generates new ID if none provided
     *
     * @param WP_REST_Request $request
     * @return string Session ID
     */
    private function get_session_id($request) {
        // Try to get from request parameter
        $session_id = $request->get_param('session_id');

        if ($session_id) {
            return sanitize_key($session_id);
        }

        // Generate new session ID
        return $this->generate_session_id();
    }

    /**
     * Generate unique session ID
     *
     * @return string
     */
    private function generate_session_id() {
        return wp_generate_password(32, false);
    }
}
