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
        
        // Quiz-related triggers
        if (preg_match('/\b(quiz|test|analyze|pronunciation|start|begin)\b/', $message_lower)) {
            return "Great! I'll help you assess your pronunciation. Click the microphone button below to start recording. When you're ready, I'll analyze your speech and show you exactly where you can improve.";
        }
        
        // How it works
        if (preg_match('/\bhow.*(work|does)\b/', $message_lower)) {
            return "Here's how {$name} works:\n\n1. **Take the free quiz** - Record yourself speaking\n2. **Get instant analysis** - I'll identify areas for improvement\n3. **Try a free lesson** - Experience the teaching method\n4. **Unlock all lessons** - Full access to master every sound\n\nWant to start with the free quiz?";
        }
        
        // What will I learn
        if (preg_match('/\bwhat.*(learn|teach)\b/', $message_lower)) {
            return "With {$name}, you'll master:\n\n• Clear, confident pronunciation\n• Problem sounds specific to your accent\n• Natural rhythm and intonation\n• Professional speaking skills\n\nThe best way to see what you'll learn is to take the free quiz. Want to try it?";
        }
        
        // Pricing
        if (preg_match('/\b(price|cost|pay|money|expensive|cheap)\b/', $message_lower)) {
            $price = $product['currency_symbol'] . $product['price'];
            return "Full lifetime access to {$name} is {$price} - that's a one-time payment with no subscriptions or hidden fees.\n\nBut first, take the free quiz to see exactly what you'll get!";
        }
        
        // Help
        if (preg_match('/\b(help|support|question)\b/', $message_lower)) {
            return "I'm here to help! You can:\n\n• **Take the free quiz** to assess your current level\n• **Ask questions** about the course or methodology\n• **Start a lesson** if you have access\n\nWhat would you like to do?";
        }
        
        // Greeting
        if (preg_match('/\b(hi|hello|hey|good morning|good afternoon)\b/', $message_lower)) {
            return "Hello! Welcome to {$name}. I'm your AI coach, ready to help you improve.\n\nWould you like to start with a free pronunciation assessment?";
        }
        
        // Thank you
        if (preg_match('/\b(thank|thanks)\b/', $message_lower)) {
            return "You're welcome! Is there anything else I can help you with?";
        }
        
        // Default
        return "I understand you're interested in {$name}. The best way to get started is with our free quiz - it takes just 30 seconds and shows you exactly where you can improve.\n\nWould you like to try it?";
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
