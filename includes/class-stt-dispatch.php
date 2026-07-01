<?php
/**
 * FLOSC STT Dispatch
 * Supports: AssemblyAI, OpenAI Whisper, Custom Endpoint
 */

if (!defined('ABSPATH')) exit;

class FLOSC_STT_Dispatch {
    
    private $provider;
    
    public function __construct() {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $this->provider = flosc_get_setting('stt_provider', 'assemblyai');
    }

    /**
     * Resolve multipart filename and MIME from the real source file.
     *
     * @param string $audio_path
     * @return array{filename:string,mime:string}
     */
    private function resolve_audio_upload_meta($audio_path) {
        $ext = strtolower((string) pathinfo((string) $audio_path, PATHINFO_EXTENSION));
        $allowed = [
            'webm' => 'audio/webm',
            'mp4'  => 'audio/mp4',
            'm4a'  => 'audio/mp4',
            'ogg'  => 'audio/ogg',
            'wav'  => 'audio/wav',
        ];

        if (!isset($allowed[$ext])) {
            $ext = 'webm';
        }

        return [
            'filename' => 'audio.' . $ext,
            'mime' => $allowed[$ext] ?? 'application/octet-stream',
        ];
    }
    
    /**
     * Transcribe Audio File
     */
    public function transcribe($audio_path, $options = []) {
        // Check cache (useful for repeated test recordings)
        $cache_key = 'flosc_stt_' . md5_file($audio_path);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        switch ($this->provider) {
            case 'assemblyai':
                $result = $this->assemblyai_transcribe($audio_path, $options);
                break;
            case 'openai':
                $result = $this->openai_whisper_transcribe($audio_path, $options);
                break;
            case 'custom':
                $result = $this->custom_transcribe($audio_path, $options);
                break;
            default:
                return new WP_Error('invalid_provider', __('Invalid STT provider', 'flosc'));
        }
        
        // Cache successful transcriptions for 24 hours
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, DAY_IN_SECONDS);
        }
        
        return $result;
    }
    
    /**
     * AssemblyAI - Recommended for accent handling
     * Cost: ~$0.00025/second = $0.0025 per 10s recording
     */
    private function assemblyai_transcribe($audio_path, $options = []) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $api_key = flosc_get_setting('assemblyai_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('AssemblyAI API key not configured', 'flosc'));
        }
        
        // Step 1: Upload audio file
        $audio_data = file_get_contents($audio_path);
        
        $upload_response = wp_remote_post('https://api.assemblyai.com/v2/upload', [
            'headers' => [
                'Authorization' => $api_key,
                'Content-Type' => 'application/octet-stream',
            ],
            'body' => $audio_data,
            'timeout' => 60,
        ]);
        
        if (is_wp_error($upload_response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC AssemblyAI Upload Error: ' . $upload_response->get_error_message());
            return $upload_response;
        }
        
        $upload_body = json_decode(wp_remote_retrieve_body($upload_response), true);
        
        if (!isset($upload_body['upload_url'])) {
            return new WP_Error('upload_failed', __('Failed to upload audio to AssemblyAI', 'flosc'));
        }
        
        // Step 2: Request transcription
        $transcribe_response = wp_remote_post('https://api.assemblyai.com/v2/transcript', [
            'headers' => [
                'Authorization' => $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'audio_url' => $upload_body['upload_url'],
                'language_code' => $options['language'] ?? 'en_us',
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($transcribe_response)) {
            return $transcribe_response;
        }
        
        $transcribe_body = json_decode(wp_remote_retrieve_body($transcribe_response), true);
        $transcript_id = $transcribe_body['id'] ?? null;
        
        if (!$transcript_id) {
            return new WP_Error('transcription_failed', __('Failed to start transcription', 'flosc'));
        }
        
        // Step 3: Poll for result without explicit sleep loops.
        // The request timeout provides bounded pacing while avoiding blocking pauses.
        $max_attempts = 12;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $attempt++;
            
            $poll_response = wp_remote_get("https://api.assemblyai.com/v2/transcript/{$transcript_id}", [
                'headers' => ['Authorization' => $api_key],
                'timeout' => 2,
            ]);
            
            if (is_wp_error($poll_response)) {
                continue;
            }
            
            $poll_body = json_decode(wp_remote_retrieve_body($poll_response), true);
            $status = $poll_body['status'] ?? '';
            
            if ($status === 'completed') {
                return $poll_body['text'] ?? '';
            }
            
            if ($status === 'error') {
                return new WP_Error('transcription_error', $poll_body['error'] ?? __('Transcription failed', 'flosc'));
            }
        }
        
        return new WP_Error('timeout', __('Transcription timed out', 'flosc'));
    }
    
    /**
     * OpenAI Whisper
     * Cost: ~$0.006/minute = $0.001 per 10s recording
     */
    private function openai_whisper_transcribe($audio_path, $options = []) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $api_key = flosc_get_setting('openai_api_key', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'flosc'));
        }
        
        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $body = '';
        $upload_meta = $this->resolve_audio_upload_meta($audio_path);
        
        // Add file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$upload_meta['filename']}\"\r\n";
        $body .= "Content-Type: {$upload_meta['mime']}\r\n\r\n";
        $body .= file_get_contents($audio_path) . "\r\n";
        
        // Add model
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-1\r\n";
        
        // Add language hint
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
        $body .= "en\r\n";
        
        $body .= "--{$boundary}--\r\n";
        
        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC Whisper Error: ' . $response->get_error_message());
            return $response;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($result['error'])) {
            return new WP_Error('whisper_error', $result['error']['message']);
        }
        
        return $result['text'] ?? '';
    }
    
    /**
     * Custom Endpoint (Self-hosted faster-whisper, etc.)
     */
    private function custom_transcribe($audio_path, $options = []) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $endpoint = flosc_get_setting('custom_stt_endpoint', '');
        
        if (empty($endpoint)) {
            return new WP_Error('no_endpoint', __('Custom STT endpoint not configured', 'flosc'));
        }
        
        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $body = '';
        $upload_meta = $this->resolve_audio_upload_meta($audio_path);
        
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"audio\"; filename=\"{$upload_meta['filename']}\"\r\n";
        $body .= "Content-Type: {$upload_meta['mime']}\r\n\r\n";
        $body .= file_get_contents($audio_path) . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        // Support common response formats
        return $result['text'] ?? $result['transcript'] ?? $result['transcription'] ?? '';
    }
    
    /**
     * Get Provider Info
     */
    public function get_provider_info() {
        $providers = [
            'assemblyai' => [
                'name' => 'AssemblyAI',
                'cost_per_10s' => '$0.0025',
                'pros' => 'Best accent handling, speaker diarization',
            ],
            'openai' => [
                'name' => 'OpenAI Whisper',
                'cost_per_10s' => '$0.001',
                'pros' => 'Fast, reliable, good quality',
            ],
            'custom' => [
                'name' => 'Custom Endpoint',
                'cost_per_10s' => 'Variable',
                'pros' => 'Self-hosted, no per-use cost',
            ],
        ];
        
        return $providers[$this->provider] ?? $providers['assemblyai'];
    }
}
