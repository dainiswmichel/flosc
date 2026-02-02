<?php
/**
 * AJAX Handler - All AJAX endpoints
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Ajax_Handler {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Quiz actions
        add_action('wp_ajax_flosc_start_quiz', array($this, 'start_quiz'));
        add_action('wp_ajax_nopriv_flosc_start_quiz', array($this, 'start_quiz'));
        add_action('wp_ajax_flosc_record_no', array($this, 'record_no'));
        add_action('wp_ajax_nopriv_flosc_record_no', array($this, 'record_no'));
        add_action('wp_ajax_flosc_submit_audio', array($this, 'submit_audio'));
        add_action('wp_ajax_nopriv_flosc_submit_audio', array($this, 'submit_audio'));
        add_action('wp_ajax_flosc_complete_quiz', array($this, 'complete_quiz'));
        add_action('wp_ajax_nopriv_flosc_complete_quiz', array($this, 'complete_quiz'));
        
        // Offer actions
        add_action('wp_ajax_flosc_get_analysis', array($this, 'get_analysis'));
        add_action('wp_ajax_flosc_send_offer', array($this, 'send_offer'));
        add_action('wp_ajax_flosc_create_checkout', array($this, 'create_checkout'));
    }
    
    /**
     * Start quiz
     */
    public function start_quiz() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : null;
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $quiz_id = $quiz_handler->create_session($user_id, $email);
        
        wp_send_json_success(array('quiz_id' => $quiz_id));
    }
    
    /**
     * Record "No" response
     */
    public function record_no() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        $quiz_id = isset($_POST['quiz_id']) ? sanitize_text_field($_POST['quiz_id']) : '';
        
        if (empty($quiz_id)) {
            wp_send_json_error(array('message' => 'Quiz ID required'));
        }
        
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $result = $quiz_handler->record_no($quiz_id);
        
        if (isset($result['error'])) {
            wp_send_json_error($result);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Submit audio
     */
    public function submit_audio() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        $quiz_id = isset($_POST['quiz_id']) ? sanitize_text_field($_POST['quiz_id']) : '';
        $sentence_index = isset($_POST['sentence_index']) ? intval($_POST['sentence_index']) : 0;
        $target_sentence = isset($_POST['target_sentence']) ? sanitize_text_field($_POST['target_sentence']) : '';
        
        if (empty($quiz_id) || !isset($_FILES['audio'])) {
            wp_send_json_error(array('message' => 'Missing required data'));
        }
        
        // Save uploaded audio
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/flosc-audio/' . $quiz_id;
        
        if (!file_exists($audio_dir)) {
            wp_mkdir_p($audio_dir);
        }
        
        $audio_file = $_FILES['audio'];
        $audio_path = $audio_dir . '/sentence_' . $sentence_index . '.webm';
        
        if (!move_uploaded_file($audio_file['tmp_name'], $audio_path)) {
            wp_send_json_error(array('message' => 'Failed to save audio'));
        }
        
        // Process with FastAPI backend
        $api_bridge = FLOSC_API_Bridge::instance();
        $result = $api_bridge->process_audio($audio_path, $quiz_id, $sentence_index, $target_sentence);
        
        if (!$result['success']) {
            wp_send_json_error(array('message' => $result['error']));
        }
        
        // Save result to database
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $quiz_handler->save_result($quiz_id, $sentence_index, $audio_path, $result['data']);
        
        wp_send_json_success($result['data']);
    }
    
    /**
     * Complete quiz
     */
    public function complete_quiz() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        $quiz_id = isset($_POST['quiz_id']) ? sanitize_text_field($_POST['quiz_id']) : '';
        
        if (empty($quiz_id)) {
            wp_send_json_error(array('message' => 'Quiz ID required'));
        }
        
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $result = $quiz_handler->complete_quiz($quiz_id);
        
        wp_send_json_success($result);
    }
    
    /**
     * Get analysis
     */
    public function get_analysis() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }
        
        $quiz_id = isset($_GET['quiz_id']) ? sanitize_text_field($_GET['quiz_id']) : '';
        
        if (empty($quiz_id)) {
            wp_send_json_error(array('message' => 'Quiz ID required'));
        }
        
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $session = $quiz_handler->get_session($quiz_id);
        
        if (!$session) {
            wp_send_json_error(array('message' => 'Quiz not found'));
        }
        
        $flagged_phonemes = json_decode($session['flagged_phonemes'], true);
        $free_lessons = $quiz_handler->get_lessons_for_phonemes($flagged_phonemes, true);
        
        wp_send_json_success(array(
            'flagged_phonemes' => $flagged_phonemes,
            'phoneme_count' => count($flagged_phonemes),
            'free_lessons' => array_slice($free_lessons, 0, 2),
            'oto_expires_at' => $session['oto_expires_at']
        ));
    }
    
    /**
     * Send offer email
     */
    public function send_offer() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }
        
        $quiz_id = isset($_POST['quiz_id']) ? sanitize_text_field($_POST['quiz_id']) : '';
        $user = wp_get_current_user();
        
        $quiz_handler = FLOSC_Quiz_Handler::instance();
        $session = $quiz_handler->get_session($quiz_id);
        
        if (!$session) {
            wp_send_json_error(array('message' => 'Quiz not found'));
        }
        
        // Set OTO expiry
        $oto_duration = get_option('flosc_oto_duration_hours', 24);
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $oto_duration . ' hours'));
        
        $quiz_handler->update_session($quiz_id, array(
            'oto_sent_at' => current_time('mysql'),
            'oto_expires_at' => $expires_at
        ));
        
        // Send email
        $flagged_phonemes = json_decode($session['flagged_phonemes'], true);
        $free_lessons = $quiz_handler->get_lessons_for_phonemes($flagged_phonemes, true);
        
        $email_manager = FLOSC_Email_Manager::instance();
        $email_manager->send_offer_email($user->user_email, $quiz_id, $flagged_phonemes, array_slice($free_lessons, 0, 2));
        
        wp_send_json_success(array('expires_at' => $expires_at));
    }
    
    /**
     * Create Stripe checkout
     */
    public function create_checkout() {
        check_ajax_referer('flosc_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Not logged in'));
        }
        
        $quiz_id = isset($_POST['quiz_id']) ? sanitize_text_field($_POST['quiz_id']) : '';
        $user_id = get_current_user_id();
        
        $payment_handler = FLOSC_Payment_Handler::instance();
        $result = $payment_handler->create_checkout_session($quiz_id, $user_id);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
