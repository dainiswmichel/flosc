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
     * Build system prompt by merging identity + phase + knowledge + FLOSC process (v1.4.1)
     */
    public function build_system_prompt($phase = '', $context = []) {
        // 1. Build AI Identity section from Knowledge tab settings
        $identity_prompt = $this->build_identity_prompt($context);

        // 2. Load FLOSC process instructions (what the AI should DO)
        $flosc_process = $this->get_flosc_process_prompt($phase, $context);

        // 3. Load phase-specific prompt from admin settings
        $phase_prompt = '';
        if ($phase) {
            $phase_prompt = $this->load_phase_prompt($phase);
        }

        // 4. Load AI knowledge files (product info, lesson catalog, etc.)
        $orientation_content = $this->load_orientation_files($context);

        // 5. Build context variables string
        $context_string = $this->build_context_string($context);

        // 6. Merge all sections
        $sections = array_filter([
            $identity_prompt,
            $flosc_process,
            $phase_prompt,
            $orientation_content ? "## Knowledge Base\n" . $orientation_content : '',
            $context_string ? "## Current Session Context\n" . $context_string : '',
        ]);

        return implode("\n\n", $sections);
    }

    /**
     * v1.4.1: Build AI identity from Knowledge tab settings
     */
    private function build_identity_prompt($context = []) {
        $name = get_option('flosc_ai_personality_name', get_option('flosc_product_name', 'FLOSC'));
        $role = get_option('flosc_ai_personality_role', 'AI assistant');
        $traits = get_option('flosc_ai_personality_traits', 'Helpful, friendly, and professional.');
        $mission = get_option('flosc_ai_mission', 'Help users achieve their goals.');
        $boundaries = get_option('flosc_ai_boundaries', '');

        $prompt = "# Your Identity\n\n";
        $prompt .= "You are **{$name}**, a {$role}.\n\n";
        $prompt .= "## Personality\n{$traits}\n\n";
        $prompt .= "## Mission\n{$mission}\n";

        if ($boundaries) {
            $prompt .= "\n## Boundaries\n{$boundaries}";
        }

        return $prompt;
    }

    /**
     * v1.4.1: Get FLOSC process instructions based on user phase
     */
    private function get_flosc_process_prompt($phase, $context = []) {
        $logged_in = $context['logged_in'] ?? false;

        $prompt = "# The FLOSC Process\n\n";
        $prompt .= "You are guiding users through a **try-before-you-buy** experience. The process has phases:\n\n";
        $prompt .= "1. **FREELINE** - Visitors can chat, ask questions, and take a free quiz\n";
        $prompt .= "2. **LOGIN** - After the quiz, guide them to create an account to save progress\n";
        $prompt .= "3. **OFFER** - Present the value of full access based on their quiz results\n";
        $prompt .= "4. **SALE** - Help them purchase when ready (don't be pushy)\n";
        $prompt .= "5. **CONTENT** - Members get full access to lessons and personalized coaching\n\n";

        // Access-level specific instructions
        if (!$logged_in) {
            $freeline_rules = get_option('flosc_ai_freeline_restrictions', '');
            $prompt .= "## Current Phase: FREELINE (Visitor)\n";
            $prompt .= "The user is NOT logged in. Your primary goals:\n";
            $prompt .= "- Build rapport and answer their questions\n";
            $prompt .= "- Encourage them to take the free quiz to see what they'll learn\n";
            $prompt .= "- Give them a taste of value without revealing premium content\n";
            if ($freeline_rules) {
                $prompt .= "\n### Access Rules\n" . $freeline_rules;
            }
        } else {
            $member_rules = get_option('flosc_ai_member_access', '');
            $prompt .= "## Current Phase: MEMBER (Logged In)\n";
            $prompt .= "The user is logged in. Your primary goals:\n";
            $prompt .= "- Provide personalized guidance based on their progress\n";
            $prompt .= "- Help them get maximum value from lessons\n";
            $prompt .= "- Answer specific questions about content they have access to\n";
            if ($member_rules) {
                $prompt .= "\n### Access Rules\n" . $member_rules;
            }
        }

        return $prompt;
    }

    /**
     * Load phase-specific prompt from admin settings
     */
    private function load_phase_prompt($phase) {
        // Load from WordPress admin options (AI Configuration tab)
        $option_key = "flosc_ai_prompt_{$phase}";
        $prompt = get_option($option_key, '');
        
        return $prompt;
    }

    /**
     * Load AI knowledge files with access-level filtering (v1.4.1)
     * Public files: available to all users
     * Members files: only loaded for logged-in users
     */
    private function load_orientation_files($context = []) {
        $orientation_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';

        if (!is_dir($orientation_dir)) {
            return '';
        }

        $logged_in = $context['logged_in'] ?? false;
        $content = '';
        $files = scandir($orientation_dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || pathinfo($file, PATHINFO_EXTENSION) !== 'md') {
                continue;
            }

            // Check file access level
            $file_access = get_option('flosc_knowledge_access_' . md5($file), 'public');
            
            // Skip members-only files for visitors
            if ($file_access === 'members' && !$logged_in) {
                continue;
            }

            $filepath = $orientation_dir . $file;
            $file_content = file_get_contents($filepath);

            if ($file_content) {
                $access_label = $file_access === 'members' ? ' (Members Only)' : '';
                $content .= "\n\n### {$file}{$access_label}\n" . $file_content;
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
     * @param bool $test_mode If true, return WP_Error on failure instead of falling back to IVR
     */
    public function get_response($message, $system_prompt = '', $context = [], $test_mode = false) {
        // Skip cache in test mode
        if (!$test_mode) {
            // Check cache first
            $cache_key = 'flosc_ai_' . md5($this->provider . $message . $system_prompt);
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return $cached;
            }
        }

        switch ($this->provider) {
            case 'openai':
                $response = $this->openai_request($message, $system_prompt, $context, $test_mode);
                break;
            case 'anthropic':
                $response = $this->anthropic_request($message, $system_prompt, $context, $test_mode);
                break;
            case 'xai':
                $response = $this->xai_request($message, $system_prompt, $context, $test_mode);
                break;
            case 'ivr':
            default:
                $response = $this->ivr_response($message);
                break;
        }

        // Cache for 1 hour (IVR responses are static anyway), but not in test mode
        if (!$test_mode && $response && !is_wp_error($response)) {
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
    private function openai_request($message, $system_prompt, $context = [], $test_mode = false) {
        $api_key = get_option('flosc_openai_api_key', '');

        if (empty($api_key)) {
            if ($test_mode) {
                return new WP_Error(
                    'openai_no_api_key',
                    "No OpenAI API key configured.\n\n📝 Next steps:\n1. Go to https://platform.openai.com/api-keys\n2. Sign up or log in to your OpenAI account\n3. Click 'Create new secret key'\n4. Copy the key (starts with sk-proj-...)\n5. Paste it in the 'OpenAI API Key' field above\n6. Click 'Save AI Configuration'\n7. Try testing again!"
                );
            }
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
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC OpenAI Error: ' . $response->get_error_message());
            if ($test_mode) {
                return new WP_Error(
                    'openai_connection_error',
                    "Could not connect to OpenAI API.\n\n❌ Error: " . $response->get_error_message() . "\n\n📝 Next steps:\n1. Check your internet connection\n2. Verify OpenAI services are operational: https://status.openai.com\n3. Try again in a few moments"
                );
            }
            return $this->ivr_response($message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC OpenAI API Error: ' . $body['error']['message']);
            if ($test_mode) {
                $error_msg = $body['error']['message'] ?? 'Unknown error';
                $error_type = $body['error']['type'] ?? 'unknown';

                $help_text = "\n\n📝 Next steps:\n";

                if (strpos($error_msg, 'invalid') !== false || strpos($error_msg, 'Incorrect') !== false) {
                    $help_text .= "1. Your API key appears to be invalid\n";
                    $help_text .= "2. Go to https://platform.openai.com/api-keys\n";
                    $help_text .= "3. Create a new API key\n";
                    $help_text .= "4. Replace the old key with the new one above\n";
                    $help_text .= "5. Make sure you copied the entire key (starts with sk-proj-...)";
                } elseif (strpos($error_msg, 'quota') !== false || strpos($error_msg, 'insufficient') !== false) {
                    $help_text .= "1. You've exceeded your OpenAI usage quota\n";
                    $help_text .= "2. Go to https://platform.openai.com/settings/organization/billing\n";
                    $help_text .= "3. Add a payment method or increase your quota\n";
                    $help_text .= "4. Wait for quota to reset or upgrade your plan";
                } else {
                    $help_text .= "1. Check the error message above for details\n";
                    $help_text .= "2. Verify your API key at https://platform.openai.com/api-keys\n";
                    $help_text .= "3. Check OpenAI status: https://status.openai.com";
                }

                return new WP_Error(
                    'openai_api_error',
                    "OpenAI API Error: " . $error_msg . $help_text
                );
            }
            return $this->ivr_response($message);
        }

        return $body['choices'][0]['message']['content'] ?? $this->ivr_response($message);
    }
    
    /**
     * Anthropic Claude
     */
    private function anthropic_request($message, $system_prompt, $context = [], $test_mode = false) {
        $api_key = get_option('flosc_anthropic_api_key', '');

        if (empty($api_key)) {
            if ($test_mode) {
                return new WP_Error(
                    'anthropic_no_api_key',
                    "No Anthropic API key configured.\n\n📝 Next steps:\n1. Go to https://console.anthropic.com/settings/keys\n2. Sign up or log in to your Anthropic account\n3. Click 'Create Key'\n4. Copy the key (starts with sk-ant-...)\n5. Paste it in the 'Anthropic API Key' field above\n6. Click 'Save AI Configuration'\n7. Try testing again!\n\n💡 Anthropic offers $5 free credit to start."
                );
            }
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
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC Anthropic Error: ' . $response->get_error_message());
            if ($test_mode) {
                return new WP_Error(
                    'anthropic_connection_error',
                    "Could not connect to Anthropic API.\n\n❌ Error: " . $response->get_error_message() . "\n\n📝 Next steps:\n1. Check your internet connection\n2. Verify Anthropic services are operational\n3. Try again in a few moments"
                );
            }
            return $this->ivr_response($message);
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['error'])) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC Anthropic API Error: ' . $data['error']['message']);
            if ($test_mode) {
                $error_msg = $data['error']['message'] ?? 'Unknown error';
                $error_type = $data['error']['type'] ?? 'unknown';

                $help_text = "\n\n📝 Next steps:\n";

                if (strpos($error_msg, 'authentication') !== false || strpos($error_msg, 'invalid') !== false) {
                    $help_text .= "1. Your API key appears to be invalid\n";
                    $help_text .= "2. Go to https://console.anthropic.com/settings/keys\n";
                    $help_text .= "3. Create a new API key\n";
                    $help_text .= "4. Replace the old key with the new one above\n";
                    $help_text .= "5. Make sure you copied the entire key (starts with sk-ant-...)";
                } elseif (strpos($error_msg, 'credit') !== false || strpos($error_msg, 'quota') !== false) {
                    $help_text .= "1. You've run out of credits or exceeded your quota\n";
                    $help_text .= "2. Go to https://console.anthropic.com/settings/billing\n";
                    $help_text .= "3. Add a payment method or purchase more credits\n";
                    $help_text .= "4. Anthropic provides $5 free credit for new accounts";
                } else {
                    $help_text .= "1. Check the error message above for details\n";
                    $help_text .= "2. Verify your API key at https://console.anthropic.com/settings/keys\n";
                    $help_text .= "3. Check your account status and billing";
                }

                return new WP_Error(
                    'anthropic_api_error',
                    "Anthropic API Error: " . $error_msg . $help_text
                );
            }
            return $this->ivr_response($message);
        }

        return $data['content'][0]['text'] ?? $this->ivr_response($message);
    }
    
    /**
     * xAI Grok
     */
    private function xai_request($message, $system_prompt, $context = [], $test_mode = false) {
        $api_key = get_option('flosc_xai_api_key', '');

        if (empty($api_key)) {
            if ($test_mode) {
                return new WP_Error(
                    'xai_no_api_key',
                    "No xAI API key configured.\n\n📝 Next steps:\n1. Go to https://console.x.ai\n2. Sign up or log in to your xAI account\n3. Navigate to API keys section\n4. Create a new API key\n5. Copy the key (starts with xai-...)\n6. Paste it in the 'xAI API Key' field above\n7. Click 'Save AI Configuration'\n8. Try testing again!"
                );
            }
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
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC xAI Error: ' . $response->get_error_message());
            if ($test_mode) {
                return new WP_Error(
                    'xai_connection_error',
                    "Could not connect to xAI API.\n\n❌ Error: " . $response->get_error_message() . "\n\n📝 Next steps:\n1. Check your internet connection\n2. Verify xAI services are operational\n3. Try again in a few moments"
                );
            }
            return $this->ivr_response($message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC xAI API Error: ' . json_encode($body['error']));
            if ($test_mode) {
                $error_msg = is_string($body['error']) ? $body['error'] : ($body['error']['message'] ?? 'Unknown error');

                $help_text = "\n\n📝 Next steps:\n";

                if (strpos($error_msg, 'authentication') !== false || strpos($error_msg, 'invalid') !== false || strpos($error_msg, 'Unauthorized') !== false) {
                    $help_text .= "1. Your API key appears to be invalid\n";
                    $help_text .= "2. Go to https://console.x.ai\n";
                    $help_text .= "3. Create a new API key\n";
                    $help_text .= "4. Replace the old key with the new one above\n";
                    $help_text .= "5. Make sure you copied the entire key (starts with xai-...)";
                } else {
                    $help_text .= "1. Check the error message above for details\n";
                    $help_text .= "2. Verify your API key at https://console.x.ai\n";
                    $help_text .= "3. Check your xAI account status";
                }

                return new WP_Error(
                    'xai_api_error',
                    "xAI API Error: " . $error_msg . $help_text
                );
            }
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
