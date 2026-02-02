<?php
/**
 * API Bridge - Connects WordPress to FastAPI Backend
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_API_Bridge {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * API URL
     */
    private $api_url;
    
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
        $this->api_url = get_option('flosc_api_url', 'http://localhost:8000');
    }
    
    /**
     * Check backend health
     */
    public function check_health() {
        $response = wp_remote_get($this->api_url . '/health', array(
            'timeout' => 5
        ));
        
        if (is_wp_error($response)) {
            return array(
                'status' => 'offline',
                'error' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        return array(
            'status' => $code === 200 ? 'online' : 'error',
            'code' => $code,
            'body' => json_decode($body, true)
        );
    }
    
    /**
     * Process audio file with Whisper
     */
    public function process_audio($audio_file_path, $quiz_id, $sentence_index, $target_sentence) {
        // Prepare multipart form data
        $boundary = wp_generate_password(24);
        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary
        );
        
        // Read audio file
        $audio_data = file_get_contents($audio_file_path);
        $filename = basename($audio_file_path);
        
        // Build multipart body
        $body = '';
        
        // Add form fields
        $fields = array(
            'quiz_id' => $quiz_id,
            'sentence_index' => $sentence_index,
            'target_sentence' => $target_sentence
        );
        
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= "{$value}\r\n";
        }
        
        // Add audio file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"audio\"; filename=\"{$filename}\"\r\n";
        $body .= "Content-Type: audio/webm\r\n\r\n";
        $body .= $audio_data . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        // Send request
        $response = wp_remote_post($this->api_url . '/api/process-audio', array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30  // Whisper processing can take time
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code !== 200) {
            return array(
                'success' => false,
                'error' => isset($data['detail']) ? $data['detail'] : 'API error: ' . $code
            );
        }
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Get analysis summary
     */
    public function get_analysis($quiz_id) {
        $response = wp_remote_get($this->api_url . '/api/analysis/' . $quiz_id, array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code !== 200) {
            return array(
                'success' => false,
                'error' => isset($data['detail']) ? $data['detail'] : 'API error: ' . $code
            );
        }
        
        return array(
            'success' => true,
            'data' => $data
        );
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        $response = wp_remote_get($this->api_url . '/health');
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message()
            );
        }
        
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code === 200) {
            return array(
                'success' => true,
                'message' => 'FastAPI backend is online and responding'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Backend returned status code: ' . $code
        );
    }
}
