<?php
/**
 * AI Provider Factory
 * Routes requests to appropriate AI provider (OpenAI, Anthropic, xAI, or IVR)
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeSAEp_AI_Provider_Factory {

    /**
     * Get provider instance based on settings
     */
    public static function get_provider() {
        $provider_type = get_option('lesaep_ai_provider', 'ivr');

        switch ($provider_type) {
            case 'openai':
                return new LeSAEp_OpenAI_Provider();
            case 'anthropic':
                return new LeSAEp_Anthropic_Provider();
            case 'xai':
                return new LeSAEp_XAI_Provider();
            case 'ivr':
            default:
                return new LeSAEp_IVR_Provider();
        }
    }
}

/**
 * Base Provider Interface
 */
abstract class LeSAEp_AI_Provider_Base {
    abstract public function get_response($prompt);

    protected function make_request($url, $headers, $body) {
        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => json_encode($body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }
}

/**
 * OpenAI Provider (ChatGPT)
 */
class LeSAEp_OpenAI_Provider extends LeSAEp_AI_Provider_Base {

    public function get_response($prompt) {
        $api_key = get_option('lesaep_openai_api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'OpenAI API key not configured');
        }

        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => 'gpt-4o-mini',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are LeSAEp, a helpful pronunciation coach for Standard American English. Keep responses concise and encouraging.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 500,
            'temperature' => 0.7,
        );

        $data = $this->make_request($url, $headers, $body);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return new WP_Error('api_error', 'Invalid response from OpenAI');
    }
}

/**
 * Anthropic Provider (Claude)
 */
class LeSAEp_Anthropic_Provider extends LeSAEp_AI_Provider_Base {

    public function get_response($prompt) {
        $api_key = get_option('lesaep_anthropic_api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'Anthropic API key not configured');
        }

        $url = 'https://api.anthropic.com/v1/messages';
        $headers = array(
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 500,
            'system' => 'You are LeSAEp, a helpful pronunciation coach for Standard American English. Keep responses concise and encouraging.',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
        );

        $data = $this->make_request($url, $headers, $body);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['content'][0]['text'])) {
            return $data['content'][0]['text'];
        }

        return new WP_Error('api_error', 'Invalid response from Anthropic');
    }
}

/**
 * xAI Provider (Grok)
 */
class LeSAEp_XAI_Provider extends LeSAEp_AI_Provider_Base {

    public function get_response($prompt) {
        $api_key = get_option('lesaep_xai_api_key');

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'xAI API key not configured');
        }

        $url = 'https://api.x.ai/v1/chat/completions';
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        );

        $body = array(
            'model' => 'grok-beta',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are LeSAEp, a helpful pronunciation coach for Standard American English. Keep responses concise and encouraging.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 500,
            'temperature' => 0.7,
        );

        $data = $this->make_request($url, $headers, $body);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        return new WP_Error('api_error', 'Invalid response from xAI');
    }
}

/**
 * IVR Provider (Scripted Responses)
 */
class LeSAEp_IVR_Provider extends LeSAEp_AI_Provider_Base {

    public function get_response($prompt) {
        $prompt_lower = strtolower($prompt);

        // Quiz responses
        if (preg_match('/\d+/', $prompt_lower)) {
            return "Great! I've received your pronunciations. Let me analyze them...\n\nYou scored 2 out of 3. Let's work on improving your pronunciation!";
        }

        // Greeting
        if (preg_match('/(hello|hi|hey)/i', $prompt_lower)) {
            return "Welcome to LeSAEp! I'm your pronunciation coach. Ready to start your free pronunciation evaluation?";
        }

        // Help request
        if (preg_match('/(help|how|what)/i', $prompt_lower)) {
            return "I'll help you improve your Standard American English pronunciation. Just say the sentences I give you, and I'll provide feedback.";
        }

        // Default response
        return "I'm LeSAEp, your pronunciation coach. Type 'start' to begin your free evaluation, or ask me how I can help!";
    }
}
