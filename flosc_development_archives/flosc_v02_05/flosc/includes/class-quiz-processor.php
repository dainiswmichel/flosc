<?php
/**
 * =============================================================================
 * FLOSC QUIZ PROCESSOR
 * =============================================================================
 * 
 * Handles quiz processing for both text and audio modes.
 * 
 * TEXT MODE (v2.0.4):
 *   - Compares user input to expected quiz items
 *   - Returns which items were correct vs missed
 *   - Perfect for testing the funnel before adding audio
 * 
 * AUDIO MODE:
 *   - Delegates to external backend (FastAPI/OpenAI/Claude)
 *   - Processes speech-to-text
 *   - Analyzes pronunciation
 * 
 * =============================================================================
 * ARCHITECTURE NOTE
 * =============================================================================
 * 
 * The 1-10 text quiz maps directly to the SAE phoneme model:
 * 
 *   Now:    1,  2,  3,  4,  5,  6,  7,  8,  9,  10
 *   Later: /æ/, /ɛ/, /ɪ/, /ɑ/, /ʌ/, /ʊ/, /u/, /oʊ/, /aɪ/, /aʊ/
 * 
 * The framework doesn't care what the items ARE.
 * It only cares about:
 *   - Canonical list of items
 *   - Which items user got correct
 *   - Which items user missed → lessons needed
 * 
 * =============================================================================
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Quiz_Processor {
    
    /**
     * Reference to main FLOSC framework
     * @var FLOSC_Framework
     */
    private $flosc;
    
    
    /**
     * Constructor
     * 
     * @param FLOSC_Framework $flosc_framework Main plugin instance
     */
    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }
    
    
    /* =========================================================================
     * AUDIO QUIZ PROCESSING
     * =========================================================================
     * 
     * Routes audio to appropriate backend based on configuration.
     * All backends return the same format for consistency.
     */
    
    /**
     * Process audio quiz submission
     * 
     * @param WP_REST_Request $request Contains audio file
     * @return WP_REST_Response Standardized quiz results
     */
    public function process_audio($request) {
        $backend_type = $this->flosc->get_config('backend_type');
        
        error_log("FLOSC Quiz: Processing audio with backend '{$backend_type}'");
        
        try {
            switch ($backend_type) {
                case 'mock':
                    return $this->process_mock($request);
                    
                case 'fastapi':
                    return $this->process_fastapi($request);
                    
                case 'openai':
                    return $this->process_openai($request);
                    
                case 'claude':
                    return $this->process_claude($request);
                    
                default:
                    return $this->process_mock($request);
            }
        } catch (Exception $e) {
            error_log("FLOSC Quiz Error: " . $e->getMessage());
            return new WP_Error(
                'quiz_error',
                'Quiz processing failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }
    
    
    /* =========================================================================
     * MOCK BACKEND
     * =========================================================================
     * 
     * Returns simulated results for testing the funnel.
     * No external API calls needed.
     * 
     * Simulates item-level scoring by randomly selecting which items
     * were "heard" correctly.
     */
    
    private function process_mock($request) {
        // Simulate processing delay (feels more realistic)
        usleep(800000);  // 0.8 seconds
        
        // Get quiz items
        $quiz_items = $this->get_quiz_items();
        $total = count($quiz_items);
        
        // Randomly select how many items were "correct" (30-90% range)
        $correct_count = rand(
            (int)($total * 0.3),
            (int)($total * 0.9)
        );
        
        // Randomly select which items were correct
        $shuffled = $quiz_items;
        shuffle($shuffled);
        $correct_items = array_slice($shuffled, 0, $correct_count);
        $missed_items = array_diff($quiz_items, $correct_items);
        
        // Sort for consistency
        sort($correct_items);
        $missed_items = array_values($missed_items);
        sort($missed_items);
        
        $score = count($correct_items);
        $percentage = $total > 0 ? round(($score / $total) * 100) : 0;
        
        error_log("FLOSC Mock Quiz: Score {$score}/{$total}");
        
        return new WP_REST_Response([
            'success'       => true,
            'quiz_type'     => 'audio',
            'backend'       => 'mock',
            'score'         => $score,
            'total'         => $total,
            'percentage'    => $percentage,
            'correct_items' => $correct_items,
            'missed_items'  => $missed_items,
            'transcript'    => 'Mock transcript for testing',
        ]);
    }
    
    
    /* =========================================================================
     * FASTAPI BACKEND
     * =========================================================================
     * 
     * Self-hosted Whisper + phoneme analysis.
     * POST audio to configured URL.
     * 
     * Expected FastAPI response:
     * {
     *     "transcription": "one two three",
     *     "match_score": 0.85,
     *     "flagged_phonemes": ["/æ/", "/θ/"],
     *     "detected_items": ["1", "2", "3"]
     * }
     */
    
    private function process_fastapi($request) {
        $url = $this->flosc->get_config('backend_url');
        
        if (!$url) {
            error_log("FLOSC: FastAPI URL not configured, falling back to mock");
            return $this->process_mock($request);
        }
        
        // Get audio file from request
        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }
        
        $audio_file = $files['audio'];
        $quiz_items = $this->get_quiz_items();
        
        // Build multipart form data
        $boundary = wp_generate_password(24, false);
        $body = '';
        
        // Add quiz items
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"quiz_items\"\r\n\r\n";
        $body .= implode(',', $quiz_items) . "\r\n";
        
        // Add audio file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"audio\"; filename=\"{$audio_file['name']}\"\r\n";
        $body .= "Content-Type: {$audio_file['type']}\r\n\r\n";
        $body .= file_get_contents($audio_file['tmp_name']) . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        // Send to FastAPI
        $response = wp_remote_post($url . '/process-audio', [
            'body'    => $body,
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'timeout' => 60,  // Audio processing can be slow
        ]);
        
        if (is_wp_error($response)) {
            error_log("FLOSC FastAPI Error: " . $response->get_error_message());
            return $this->process_mock($request);  // Fallback
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!$data) {
            error_log("FLOSC: Invalid FastAPI response");
            return $this->process_mock($request);  // Fallback
        }
        
        // Parse FastAPI response into standard format
        $detected = $data['detected_items'] ?? [];
        $correct_items = array_intersect($quiz_items, $detected);
        $missed_items = array_diff($quiz_items, $detected);
        
        $score = count($correct_items);
        $total = count($quiz_items);
        $percentage = $total > 0 ? round(($score / $total) * 100) : 0;
        
        return new WP_REST_Response([
            'success'       => true,
            'quiz_type'     => 'audio',
            'backend'       => 'fastapi',
            'score'         => $score,
            'total'         => $total,
            'percentage'    => $percentage,
            'correct_items' => array_values($correct_items),
            'missed_items'  => array_values($missed_items),
            'transcript'    => $data['transcription'] ?? '',
        ]);
    }
    
    
    /* =========================================================================
     * OPENAI BACKEND
     * =========================================================================
     * 
     * Uses OpenAI Whisper for transcription + GPT for analysis.
     * Requires API key in settings.
     */
    
    private function process_openai($request) {
        $api_key = $this->flosc->get_config('backend_api_key');
        
        if (!$api_key) {
            error_log("FLOSC: OpenAI API key not configured, falling back to mock");
            return $this->process_mock($request);
        }
        
        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }
        
        $audio_file = $files['audio'];
        $quiz_items = $this->get_quiz_items();
        
        // Step 1: Transcribe with Whisper
        $transcript = $this->openai_transcribe($audio_file, $api_key);
        
        if (is_wp_error($transcript)) {
            return $transcript;
        }
        
        // Step 2: Analyze with GPT to identify which items were pronounced
        $analysis = $this->openai_analyze($transcript, $quiz_items, $api_key);
        
        if (is_wp_error($analysis)) {
            return $analysis;
        }
        
        return new WP_REST_Response([
            'success'       => true,
            'quiz_type'     => 'audio',
            'backend'       => 'openai',
            'score'         => $analysis['score'],
            'total'         => $analysis['total'],
            'percentage'    => $analysis['percentage'],
            'correct_items' => $analysis['correct_items'],
            'missed_items'  => $analysis['missed_items'],
            'transcript'    => $transcript,
        ]);
    }
    
    
    /**
     * Transcribe audio using OpenAI Whisper API
     */
    private function openai_transcribe($audio_file, $api_key) {
        $boundary = wp_generate_password(24, false);
        $body = '';
        
        // File
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$audio_file['name']}\"\r\n";
        $body .= "Content-Type: {$audio_file['type']}\r\n\r\n";
        $body .= file_get_contents($audio_file['tmp_name']) . "\r\n";
        
        // Model
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-1\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'body'    => $body,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['text'] ?? '';
    }
    
    
    /**
     * Analyze transcript using GPT to identify pronounced items
     */
    private function openai_analyze($transcript, $quiz_items, $api_key) {
        $model = $this->flosc->get_config('backend_model') ?: 'gpt-4o-mini';
        
        $prompt = "Analyze this speech transcript and identify which items from the list were pronounced correctly.\n\n";
        $prompt .= "Quiz Items: " . implode(', ', $quiz_items) . "\n";
        $prompt .= "Transcript: {$transcript}\n\n";
        $prompt .= "Return JSON only: {\"detected_items\": [\"1\", \"2\", ...]}";
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'body'    => json_encode([
                'model'       => $model,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a speech analysis expert. Identify which items from a list were pronounced in a transcript. Return only JSON.'
                    ],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $content = $data['choices'][0]['message']['content'] ?? '{}';
        
        // Parse GPT response
        $analysis = json_decode($content, true);
        $detected = $analysis['detected_items'] ?? [];
        
        $correct_items = array_intersect($quiz_items, $detected);
        $missed_items = array_diff($quiz_items, $detected);
        
        $score = count($correct_items);
        $total = count($quiz_items);
        
        return [
            'score'         => $score,
            'total'         => $total,
            'percentage'    => $total > 0 ? round(($score / $total) * 100) : 0,
            'correct_items' => array_values($correct_items),
            'missed_items'  => array_values($missed_items),
        ];
    }
    
    
    /* =========================================================================
     * CLAUDE BACKEND (Placeholder)
     * =========================================================================
     */
    
    private function process_claude($request) {
        // =================================================================
        // CLAUDE INTEGRATION PLACEHOLDER
        // =================================================================
        // 
        // When implementing Claude:
        // 
        // 1. Use Anthropic API for audio analysis
        //    - Claude can analyze audio descriptions
        //    - Or use Claude for post-Whisper analysis
        // 
        // 2. API endpoint: https://api.anthropic.com/v1/messages
        // 
        // 3. Headers:
        //    - x-api-key: Your Anthropic API key
        //    - anthropic-version: 2023-06-01
        //    - content-type: application/json
        // 
        // 4. For now, fall back to mock
        // =================================================================
        
        error_log("FLOSC: Claude backend not yet implemented, using mock");
        return $this->process_mock($request);
    }
    
    
    /* =========================================================================
     * HELPER METHODS
     * =========================================================================
     */
    
    /**
     * Get quiz items from configuration
     * 
     * @return array List of quiz items
     */
    private function get_quiz_items() {
        $raw = $this->flosc->get_config('quiz_items');
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $items = array_values(array_filter(array_map('trim', $lines)));
        
        if (empty($items)) {
            // Default 1-10
            $items = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
        }
        
        return $items;
    }
}
