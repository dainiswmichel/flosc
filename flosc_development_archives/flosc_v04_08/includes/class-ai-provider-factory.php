<?php
/**
 * FLOSC AI Provider Factory
 * Supports: IVR (Scripted), OpenAI, Anthropic, xAI
 */

if (!defined('ABSPATH')) exit;

class FLOSC_AI_Provider_Factory {

    private $provider;

    public function __construct() {
        $this->provider = get_option('flosc_ai_provider', 'ivr');
    }

    /**
     * Build system prompt by merging base + phase-specific prompts + orientation files (v04_04/v04_05)
     */
    public function build_system_prompt($phase = '', $context = []) {
        // 1. Get base system prompt from database
        $base_prompt = get_option('flosc_ai_base_prompt', $this->get_default_base_prompt());

        // 2. Load phase-specific prompt from MD file
        $phase_prompt = '';
        if ($phase) {
            $phase_prompt = $this->load_phase_prompt($phase);
        }

        // 3. Load AI orientation files (v04_05)
        $orientation_content = $this->load_orientation_files();

        // 4. Build context variables string
        $context_string = $this->build_context_string($context);

        // 5. Merge all tiers
        $full_prompt = trim($base_prompt);

        if ($phase_prompt) {
            $full_prompt .= "\n\n" . trim($phase_prompt);
        }

        if ($orientation_content) {
            $full_prompt .= "\n\n## Knowledge Base\n" . trim($orientation_content);
        }

        if ($context_string) {
            $full_prompt .= "\n\n## Current Context\n" . trim($context_string);
        }

        return $full_prompt;
    }

    /**
     * Load phase-specific prompt from MD file
     */
    private function load_phase_prompt($phase) {
        $prompt_file = FLOSC_PLUGIN_DIR . "prompts/{$phase}-prompt.md";

        if (!file_exists($prompt_file)) {
            return '';
        }

        $content = file_get_contents($prompt_file);
        return $content ?: '';
    }

    /**
     * Load AI orientation files (v04_05)
     */
    private function load_orientation_files() {
        $orientation_dir = FLOSC_PLUGIN_DIR . 'ai_orientation_files/';

        if (!is_dir($orientation_dir)) {
            return '';
        }

        $content = '';
        $files = scandir($orientation_dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }

            $filepath = $orientation_dir . $file;
            $file_content = file_get_contents($filepath);

            if ($file_content) {
                $content .= "\n\n### File: {$file}\n" . $file_content;
            }
        }

        return $content;
    }

    /**
     * Build context string from context array
     */
    private function build_context_string($context) {
        if (empty($context)) {
            return '';
        }

        $lines = [];

        foreach ($context as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            }
            $lines[] = "- **{$key}:** {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Get default base system prompt
     */
    private function get_default_base_prompt() {
        $product = $this->get_product_config();
        return "You are {$product['name']}, an AI pronunciation coach. Your mission is to help users improve their English pronunciation through personalized practice and encouragement. Be helpful, friendly, specific, and action-oriented. Always reference the user's quiz results and progress when available.";
    }

    /**
     * Get AI Response
     */
    public function get_response($message, $system_prompt = '', $context = []) {
        // Check cache first
        $cache_key = 'flosc_ai_' . md5($this->provider . $message . $system_prompt);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        switch ($this->provider) {
            case 'openai':
                $response = $this->openai_request($message, $system_prompt, $context);
                break;
            case 'anthropic':
                $response = $this->anthropic_request($message, $system_prompt, $context);
                break;
            case 'xai':
                $response = $this->xai_request($message, $system_prompt, $context);
                break;
            case 'ivr':
            default:
                $response = $this->ivr_response($message);
                break;
        }
        
        // Cache for 1 hour (IVR responses are static anyway)
        if ($response && !is_wp_error($response)) {
            set_transient($cache_key, $response, HOUR_IN_SECONDS);
        }
        
        return $response;
    }
    
    /**
     * IVR - Scripted Responses (Free)
     */
    private function ivr_response($message) {
        $message_lower = strtolower($message);
        $product = $this->get_product_config();
        $name = $product['name'] ?: 'this app';

        // Connection test pattern
        if (preg_match('/connection.*test/i', $message_lower)) {
            return "Connection successful! I'm ready to help you.";
        }

        // Presence/confirmation patterns (are you there, are you listening, etc.)
        if (preg_match('/\b(are you (there|here|listening|available|online|active|responding)|can you hear me|anyone there)\b/', $message_lower)) {
            return "Yes, I'm here and ready to help! What would you like to know?";
        }

        // Identity patterns (who are you, who is this, what are you)
        if (preg_match('/\b(who (are you|is this)|what are you|introduce yourself)\b/', $message_lower)) {
            return "I'm your {$name} AI assistant! I'm here to help you learn and answer your questions. What can I help you with?";
        }

        // Greeting
        if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening)\b/', $message_lower)) {
            return "Hello! Welcome to {$name}. I'm your AI coach, ready to help you improve.\n\nWould you like to start with a free pronunciation assessment?";
        }

        // Quiz-related triggers - more specific to avoid false positives
        if (preg_match('/\b(take.*quiz|start.*quiz|begin.*quiz|quiz.*me|pronunciation quiz|assessment|evaluate.*pronunciation)\b/', $message_lower)) {
            return "Great! I'll help you assess your pronunciation. Click the microphone button below to start recording. When you're ready, I'll analyze your speech and show you exactly where you can improve.";
        }

        // How it works
        if (preg_match('/\bhow.*(work|does)\b/', $message_lower)) {
            return "Here's how {$name} works:\n\n1. **Take the free quiz** - Record yourself speaking\n2. **Get instant analysis** - I'll identify areas for improvement\n3. **Try a free lesson** - Experience the teaching method\n4. **Unlock all lessons** - Full access to master every sound\n\nWant to start with the free quiz?";
        }

        // What will I learn
        if (preg_match('/\bwhat.*(learn|teach|will i)\b/', $message_lower)) {
            return "With {$name}, you'll master:\n\n• Clear, confident pronunciation\n• Problem sounds specific to your accent\n• Natural rhythm and intonation\n• Professional speaking skills\n\nThe best way to see what you'll learn is to take the free quiz. Want to try it?";
        }

        // Pricing
        if (preg_match('/\b(price|cost|pay|money|expensive|cheap|how much)\b/', $message_lower)) {
            $price = $product['currency_symbol'] . $product['price'];
            return "Full lifetime access to {$name} is {$price} - that's a one-time payment with no subscriptions or hidden fees.\n\nBut first, take the free quiz to see exactly what you'll get!";
        }

        // Help
        if (preg_match('/\b(help|support|question|assist)\b/', $message_lower)) {
            return "I'm here to help! You can:\n\n• **Take the free quiz** to assess your current level\n• **Ask questions** about the course or methodology\n• **Start a lesson** if you have access\n\nWhat would you like to do?";
        }

        // Thank you
        if (preg_match('/\b(thank|thanks)\b/', $message_lower)) {
            return "You're welcome! Is there anything else I can help you with?";
        }

        // Default - more conversational
        return "I'm here to help! I can answer questions about {$name}, help you get started, or guide you through the free quiz. What would you like to know?";
    }
    
    /**
     * OpenAI GPT-4o-mini
     */
    private function openai_request($message, $system_prompt, $context = []) {
        $api_key = get_option('flosc_openai_api_key', '');
        
        if (empty($api_key)) {
            return $this->ivr_response($message); // Fallback
        }
        
        $messages = [];
        
        if ($system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        
        // Add context messages
        foreach ($context as $ctx) {
            $messages[] = [
                'role' => $ctx['role'],
                'content' => $ctx['content'],
            ];
        }
        
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('FLOSC OpenAI Error: ' . $response->get_error_message());
            return $this->ivr_response($message);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            error_log('FLOSC OpenAI API Error: ' . $body['error']['message']);
            return $this->ivr_response($message);
        }
        
        return $body['choices'][0]['message']['content'] ?? $this->ivr_response($message);
    }
    
    /**
     * Anthropic Claude
     */
    private function anthropic_request($message, $system_prompt, $context = []) {
        $api_key = get_option('flosc_anthropic_api_key', '');
        
        if (empty($api_key)) {
            return $this->ivr_response($message);
        }
        
        $messages = [];
        
        foreach ($context as $ctx) {
            $messages[] = [
                'role' => $ctx['role'],
                'content' => $ctx['content'],
            ];
        }
        
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $body = [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 500,
            'messages' => $messages,
        ];
        
        if ($system_prompt) {
            $body['system'] = $system_prompt;
        }
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('FLOSC Anthropic Error: ' . $response->get_error_message());
            return $this->ivr_response($message);
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['error'])) {
            error_log('FLOSC Anthropic API Error: ' . $data['error']['message']);
            return $this->ivr_response($message);
        }
        
        return $data['content'][0]['text'] ?? $this->ivr_response($message);
    }
    
    /**
     * xAI Grok
     */
    private function xai_request($message, $system_prompt, $context = []) {
        $api_key = get_option('flosc_xai_api_key', '');
        
        if (empty($api_key)) {
            return $this->ivr_response($message);
        }
        
        $messages = [];
        
        if ($system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }
        
        foreach ($context as $ctx) {
            $messages[] = [
                'role' => $ctx['role'],
                'content' => $ctx['content'],
            ];
        }
        
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'grok-beta',
                'messages' => $messages,
                'max_tokens' => 500,
                'temperature' => 0.7,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            error_log('FLOSC xAI Error: ' . $response->get_error_message());
            return $this->ivr_response($message);
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            error_log('FLOSC xAI API Error: ' . json_encode($body['error']));
            return $this->ivr_response($message);
        }
        
        return $body['choices'][0]['message']['content'] ?? $this->ivr_response($message);
    }
    
    /**
     * Get Product Config (helper)
     */
    private function get_product_config() {
        $currency = get_option('flosc_currency', 'EUR');
        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        
        return [
            'name' => get_option('flosc_product_name', 'FLOSC App'),
            'price' => get_option('flosc_product_price', '144.00'),
            'currency_symbol' => $symbols[$currency] ?? $currency,
        ];
    }
}
