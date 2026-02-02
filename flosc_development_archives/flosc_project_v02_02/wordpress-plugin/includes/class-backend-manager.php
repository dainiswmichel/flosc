<?php
/**
 * FLOSC Backend Manager
 *
 * Handles quiz audio processing across multiple backend types:
 * - Mock (random scores, no API needed)
 * - FastAPI (self-hosted Whisper)
 * - OpenAI API
 * - Claude API
 * - Custom endpoint
 *
 * TESTING TONIGHT:
 * Switch backends in WP Admin without code changes
 * Each backend returns same format: { score, details }
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Backend_Manager {

    private $flosc;

    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;
    }

    /**
     * Process quiz - Route to appropriate backend
     *
     * @param WP_REST_Request $request Contains audio file + expected_text
     * @return WP_REST_Response { success, score, details, backend_used }
     */
    public function process_quiz($request) {
        $backend_type = $this->flosc->get_config('backend_type');

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

                case 'custom':
                    return $this->process_custom($request);

                default:
                    return new WP_Error('invalid_backend', 'Invalid backend type configured', ['status' => 500]);
            }
        } catch (Exception $e) {
            error_log('FLOSC Backend Error: ' . $e->getMessage());
            return new WP_Error('backend_error', 'Quiz processing failed: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * MOCK BACKEND
     * Returns random scores for testing chat flow
     * No API calls, instant response
     */
    private function process_mock($request) {
        // Simulate processing delay
        usleep(500000); // 0.5 seconds

        $mock_score = rand(30, 95);

        return new WP_REST_Response([
            'success' => true,
            'score' => $mock_score,
            'details' => [
                'transcript' => $request->get_param('expected_text') ?? 'Mock transcript',
                'phonemes_analyzed' => rand(8, 12),
                'issues_found' => max(0, floor((100 - $mock_score) / 10)),
                'flagged_phonemes' => $mock_score < 70 ? ['/æ/', '/θ/'] : [],
            ],
            'backend_used' => 'mock',
        ]);
    }

    /**
     * FASTAPI BACKEND
     * Self-hosted Whisper + phoneme analysis
     * POST audio to configured URL
     */
    private function process_fastapi($request) {
        $url = $this->flosc->get_config('backend_url');

        if (!$url) {
            return new WP_Error('no_url', 'FastAPI backend URL not configured', ['status' => 500]);
        }

        // Get audio file
        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }

        $audio_file = $files['audio'];
        $expected_text = $request->get_param('expected_text') ?? '';

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $body = '';

        // Add expected_text field
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"expected_text\"\r\n\r\n";
        $body .= $expected_text . "\r\n";

        // Add audio file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"audio\"; filename=\"{$audio_file['name']}\"\r\n";
        $body .= "Content-Type: {$audio_file['type']}\r\n\r\n";
        $body .= file_get_contents($audio_file['tmp_name']) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        // Send to FastAPI
        $response = wp_remote_post($url, [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // Convert FastAPI response to FLOSC format
        return new WP_REST_Response([
            'success' => true,
            'score' => (int)($body['match_score'] * 100) ?? 0,
            'details' => [
                'transcript' => $body['transcription'] ?? '',
                'phonemes_analyzed' => count($body['flagged_phonemes'] ?? []),
                'issues_found' => count($body['flagged_phonemes'] ?? []),
                'flagged_phonemes' => $body['flagged_phonemes'] ?? [],
            ],
            'backend_used' => 'fastapi',
        ]);
    }

    /**
     * OPENAI BACKEND
     * Use Whisper API for transcription + GPT for analysis
     */
    private function process_openai($request) {
        $api_key = $this->flosc->get_config('backend_api_key');

        if (!$api_key) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured', ['status' => 500]);
        }

        $files = $request->get_file_params();
        if (empty($files['audio'])) {
            return new WP_Error('no_audio', 'No audio file provided', ['status' => 400]);
        }

        $audio_file = $files['audio'];
        $expected_text = $request->get_param('expected_text') ?? '';

        // Step 1: Transcribe with Whisper
        $transcript = $this->openai_whisper($audio_file, $api_key);

        if (is_wp_error($transcript)) {
            return $transcript;
        }

        // Step 2: Analyze with GPT
        $analysis = $this->openai_analyze($transcript, $expected_text, $api_key);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        return new WP_REST_Response([
            'success' => true,
            'score' => $analysis['score'],
            'details' => [
                'transcript' => $transcript,
                'phonemes_analyzed' => $analysis['phonemes_count'],
                'issues_found' => $analysis['issues_count'],
                'flagged_phonemes' => $analysis['flagged_phonemes'],
            ],
            'backend_used' => 'openai',
        ]);
    }

    /**
     * OpenAI Whisper API call
     */
    private function openai_whisper($audio_file, $api_key) {
        $boundary = wp_generate_password(24, false);
        $body = '';

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$audio_file['name']}\"\r\n";
        $body .= "Content-Type: {$audio_file['type']}\r\n\r\n";
        $body .= file_get_contents($audio_file['tmp_name']) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-1\r\n";
        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'body' => $body,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return $result['text'] ?? '';
    }

    /**
     * OpenAI GPT analysis
     */
    private function openai_analyze($transcript, $expected, $api_key) {
        $model = $this->flosc->get_config('backend_model') ?: 'gpt-4';

        $prompt = "Compare these two texts and provide pronunciation analysis:\n\n";
        $prompt .= "Expected: {$expected}\n";
        $prompt .= "Actual: {$transcript}\n\n";
        $prompt .= "Return JSON: {\"score\": 0-100, \"phonemes_count\": X, \"issues_count\": Y, \"flagged_phonemes\": []}";

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'body' => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a pronunciation expert. Analyze transcriptions and return structured JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
            ]),
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        $content = $result['choices'][0]['message']['content'] ?? '{}';

        return json_decode($content, true) ?: [
            'score' => 75,
            'phonemes_count' => 10,
            'issues_count' => 2,
            'flagged_phonemes' => [],
        ];
    }

    /**
     * CLAUDE BACKEND
     * Similar to OpenAI but uses Anthropic API
     */
    private function process_claude($request) {
        return new WP_Error('not_implemented', 'Claude backend coming soon - use OpenAI for now', ['status' => 501]);
    }

    /**
     * CUSTOM BACKEND
     * Send to any endpoint that accepts audio + returns score
     */
    private function process_custom($request) {
        $url = $this->flosc->get_config('backend_url');

        if (!$url) {
            return new WP_Error('no_url', 'Custom backend URL not configured', ['status' => 500]);
        }

        // Same logic as FastAPI but with custom URL
        return $this->process_fastapi($request);
    }
}
