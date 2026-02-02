<?php
/**
 * STT Provider Factory
 *
 * Manages multiple Speech-to-Text providers with easy switching
 * Supports: AssemblyAI, OpenAI Whisper, Deepgram, Custom endpoints
 *
 * @package LeSAEp_FLOSC
 * @version 2.0.7
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base class for all STT providers
 */
abstract class LeSAEp_STT_Provider_Base {
    protected $api_key;
    protected $config;

    public function __construct($api_key = '', $config = []) {
        $this->api_key = $api_key;
        $this->config = $config;
    }

    /**
     * Transcribe audio file
     *
     * @param string $audio_file_path Path to audio file
     * @param array $options Additional options (language, etc.)
     * @return array ['transcript' => string, 'confidence' => float, 'words' => array]
     */
    abstract public function transcribe($audio_file_path, $options = []);

    /**
     * Check if provider is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
}

/**
 * AssemblyAI Provider
 * Best for: Speaker diarization, accuracy, accent handling
 * Cost: $0.00025/second (~$0.0025 per 10-sec recording)
 */
class LeSAEp_STT_AssemblyAI extends LeSAEp_STT_Provider_Base {

    public function transcribe($audio_file_path, $options = []) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'AssemblyAI API key not configured');
        }

        // Step 1: Upload audio file
        $upload_url = $this->upload_file($audio_file_path);
        if (is_wp_error($upload_url)) {
            return $upload_url;
        }

        // Step 2: Request transcription
        $transcript_id = $this->request_transcription($upload_url, $options);
        if (is_wp_error($transcript_id)) {
            return $transcript_id;
        }

        // Step 3: Poll for completion
        $result = $this->wait_for_completion($transcript_id);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'transcript' => $result['text'],
            'confidence' => $result['confidence'],
            'words' => $result['words'] ?? [],
            'speaker_count' => $result['speaker_count'] ?? 1,
            'raw' => $result,
        ];
    }

    private function upload_file($audio_file_path) {
        $response = wp_remote_post('https://api.assemblyai.com/v2/upload', [
            'headers' => [
                'authorization' => $this->api_key,
                'content-type' => 'application/octet-stream',
            ],
            'body' => file_get_contents($audio_file_path),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['upload_url'])) {
            return new WP_Error('upload_failed', 'Failed to upload audio to AssemblyAI');
        }

        return $body['upload_url'];
    }

    private function request_transcription($audio_url, $options) {
        $payload = [
            'audio_url' => $audio_url,
            'language_code' => $options['language'] ?? 'en',
            'speaker_labels' => $options['speaker_diarization'] ?? true,
        ];

        $response = wp_remote_post('https://api.assemblyai.com/v2/transcript', [
            'headers' => [
                'authorization' => $this->api_key,
                'content-type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['id'])) {
            return new WP_Error('transcription_failed', 'Failed to request transcription');
        }

        return $body['id'];
    }

    private function wait_for_completion($transcript_id, $max_attempts = 60) {
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $response = wp_remote_get("https://api.assemblyai.com/v2/transcript/{$transcript_id}", [
                'headers' => [
                    'authorization' => $this->api_key,
                ],
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($body['status'] === 'completed') {
                return $body;
            }

            if ($body['status'] === 'error') {
                return new WP_Error('transcription_error', $body['error'] ?? 'Transcription failed');
            }

            sleep(2); // Wait 2 seconds before polling again
            $attempt++;
        }

        return new WP_Error('timeout', 'Transcription timeout');
    }
}

/**
 * OpenAI Whisper Provider
 * Best for: General purpose, fast, no speaker diarization
 * Cost: $0.006/minute (~$0.001 per 10-sec recording)
 */
class LeSAEp_STT_OpenAI_Whisper extends LeSAEp_STT_Provider_Base {

    public function transcribe($audio_file_path, $options = []) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        // Prepare multipart form data
        $boundary = wp_generate_password(24, false);
        $body = '';

        // Add file
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"audio.webm\"\r\n";
        $body .= "Content-Type: audio/webm\r\n\r\n";
        $body .= file_get_contents($audio_file_path) . "\r\n";

        // Add model
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
        $body .= "whisper-1\r\n";

        // Add language
        if (!empty($options['language'])) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= $options['language'] . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body' => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($result['text'])) {
            return new WP_Error('transcription_failed', 'No transcript returned');
        }

        return [
            'transcript' => $result['text'],
            'confidence' => 0.9, // Whisper doesn't return confidence
            'words' => [],
            'raw' => $result,
        ];
    }
}

/**
 * Deepgram Provider
 * Best for: Real-time, streaming, multilingual
 * Cost: $0.0043/minute (~$0.00072 per 10-sec recording)
 */
class LeSAEp_STT_Deepgram extends LeSAEp_STT_Provider_Base {

    public function transcribe($audio_file_path, $options = []) {
        if (!$this->is_configured()) {
            return new WP_Error('no_api_key', 'Deepgram API key not configured');
        }

        $params = http_build_query([
            'language' => $options['language'] ?? 'en',
            'model' => $options['model'] ?? 'nova-2',
            'smart_format' => true,
            'diarize' => $options['speaker_diarization'] ?? false,
        ]);

        $response = wp_remote_post("https://api.deepgram.com/v1/listen?{$params}", [
            'headers' => [
                'Authorization' => 'Token ' . $this->api_key,
                'Content-Type' => 'audio/webm',
            ],
            'body' => file_get_contents($audio_file_path),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($result['results']['channels'][0]['alternatives'][0]['transcript'])) {
            return new WP_Error('transcription_failed', 'No transcript returned');
        }

        $channel = $result['results']['channels'][0];
        $alternative = $channel['alternatives'][0];

        return [
            'transcript' => $alternative['transcript'],
            'confidence' => $alternative['confidence'] ?? 0.9,
            'words' => $alternative['words'] ?? [],
            'raw' => $result,
        ];
    }
}

/**
 * Custom Endpoint Provider
 * For self-hosted solutions (faster-whisper, local models)
 */
class LeSAEp_STT_Custom extends LeSAEp_STT_Provider_Base {

    public function transcribe($audio_file_path, $options = []) {
        $endpoint = $this->config['endpoint'] ?? '';

        if (empty($endpoint)) {
            return new WP_Error('no_endpoint', 'Custom STT endpoint not configured');
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'audio/webm',
            ],
            'body' => file_get_contents($audio_file_path),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        // Expect format: {transcript: string, confidence: float, words: []}
        if (empty($result['transcript'])) {
            return new WP_Error('transcription_failed', 'No transcript returned');
        }

        return [
            'transcript' => $result['transcript'],
            'confidence' => $result['confidence'] ?? 0.9,
            'words' => $result['words'] ?? [],
            'raw' => $result,
        ];
    }
}

/**
 * Factory class to get the configured STT provider
 */
class LeSAEp_STT_Provider_Factory {

    public static function get_provider() {
        $provider_type = get_option('lesaep_stt_provider', 'assemblyai');
        $api_key = get_option('lesaep_stt_api_key', '');
        $config = [];

        switch ($provider_type) {
            case 'assemblyai':
                return new LeSAEp_STT_AssemblyAI($api_key, $config);

            case 'openai_whisper':
                return new LeSAEp_STT_OpenAI_Whisper($api_key, $config);

            case 'deepgram':
                return new LeSAEp_STT_Deepgram($api_key, $config);

            case 'custom':
                $config['endpoint'] = get_option('lesaep_stt_custom_endpoint', '');
                return new LeSAEp_STT_Custom($api_key, $config);

            default:
                return new LeSAEp_STT_AssemblyAI($api_key, $config);
        }
    }

    public static function get_available_providers() {
        return [
            'assemblyai' => [
                'name' => 'AssemblyAI',
                'description' => 'Best accuracy, speaker diarization included',
                'cost' => '$0.0025 per 10-second recording',
                'features' => ['Speaker diarization', 'High accuracy', 'Accent handling'],
            ],
            'openai_whisper' => [
                'name' => 'OpenAI Whisper',
                'description' => 'Fast, general purpose',
                'cost' => '$0.001 per 10-second recording',
                'features' => ['Fast processing', 'Multilingual', 'No speaker labels'],
            ],
            'deepgram' => [
                'name' => 'Deepgram',
                'description' => 'Real-time capable, streaming support',
                'cost' => '$0.00072 per 10-second recording',
                'features' => ['Real-time', 'Streaming', 'Multilingual'],
            ],
            'custom' => [
                'name' => 'Custom Endpoint',
                'description' => 'Self-hosted or third-party API',
                'cost' => 'Your own infrastructure',
                'features' => ['Full control', 'Privacy', 'Custom models'],
            ],
        ];
    }
}
