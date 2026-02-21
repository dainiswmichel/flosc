<?php
/**
 * FLOSC AI Chat Dispatch
 * Supports: IVR (Scripted), OpenAI, Anthropic, xAI
 */

if (!defined('ABSPATH')) exit;

class FLOSC_AI_Chat_Dispatch {

    private $provider;
    public $last_chain_detail = [];

    public function __construct() {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves),
        // then falls back to global options
        $this->provider = flosc_get_setting('ai_provider', 'ivr');
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

        // v1.9.0: IVR Interpreter — when IVR matched, AI uses it as response guidance
        $ivr_guidance = $context['ivr_guidance'] ?? '';

        // v1.9.0: Admin corrections + praise — load flagged/praised response guidance
        $corrections_prompt = $this->build_corrections_prompt();
        $praise_prompt = $this->build_praise_prompt();

        // 6. Merge all sections
        $sections = array_filter([
            $identity_prompt,
            $flosc_process,
            $phase_prompt,
            $orientation_content ? "## Knowledge Base\n" . $orientation_content : '',
            $corrections_prompt,
            $praise_prompt,
            $context_string ? "## Current Session Context\n" . $context_string : '',
            $ivr_guidance ? "## Response Guidance\nThe scripted system matched the following response for the user's input. "
                . "Use this as the BASIS for your reply — convey the same meaning and intent, "
                . "but rewrite it naturally in your own voice. Do not repeat it word-for-word.\n\n"
                . "Scripted guidance: \"" . $ivr_guidance . "\"" : '',
        ]);

        return implode("\n\n", $sections);
    }

    /**
     * v1.4.1: Build AI identity from Knowledge tab settings
     */
    private function build_identity_prompt($context = []) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $name = flosc_get_setting('ai_personality_name', flosc_get_setting('product_name', 'FLOSC'));
        $role = flosc_get_setting('ai_personality_role', 'AI assistant');
        $traits = flosc_get_setting('ai_personality_traits', 'Helpful, friendly, and professional.');
        $mission = flosc_get_setting('ai_mission', 'Help users achieve their goals.');
        $boundaries = flosc_get_setting('ai_boundaries', '');

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
     * v1.9.0: Build corrections prompt from admin-flagged bad responses.
     * Returns formatted guidance for the AI to avoid repeating past mistakes.
     */
    private function build_corrections_prompt() {
        $corrections = flosc_get_setting('ai_corrections', []);

        if (empty($corrections) || !is_array($corrections)) {
            return '';
        }

        $prompt = "## Admin Corrections\n";
        $prompt .= "The following are specific corrections from the administrator. Follow these exactly.\n\n";

        foreach ($corrections as $i => $correction) {
            $num = $i + 1;
            $prompt .= "### Correction {$num}\n";
            $prompt .= "**When the user says:** \"{$correction['user_message']}\"\n";
            $prompt .= "**Do NOT respond like:** \"{$correction['bad_response']}\"\n";
            $prompt .= "**Issue:** {$correction['admin_note']}\n";

            if (!empty($correction['preferred_response'])) {
                $prompt .= "**Instead, respond like:** \"{$correction['preferred_response']}\"\n";
            }

            $prompt .= "\n";
        }

        return $prompt;
    }

    /**
     * v1.9.0: Build praise prompt from admin-praised good responses.
     * Reinforces behaviors the admin wants the AI to keep doing.
     */
    private function build_praise_prompt() {
        $praises = flosc_get_setting('ai_praises', []);

        if (empty($praises) || !is_array($praises)) {
            return '';
        }

        $prompt = "## Admin Praise — Keep Doing This\n";
        $prompt .= "The administrator praised the following responses. Use them as examples of your best work.\n\n";

        foreach ($praises as $i => $praise) {
            $num = $i + 1;
            $prompt .= "### Example {$num}\n";
            $prompt .= "**When the user said:** \"{$praise['user_message']}\"\n";
            $prompt .= "**Your excellent response was:** \"{$praise['good_response']}\"\n";
            $prompt .= "**Why this was good:** {$praise['admin_note']}\n\n";
        }

        return $prompt;
    }

    /**
     * Get FLOSC process instructions based on user phase and role
     */
    private function get_flosc_process_prompt($phase, $context = []) {
        $is_admin = $context['is_admin'] ?? false;

        $prompt = "# The FLOSC Process\n\n";
        $prompt .= "You are guiding users through a **try-before-you-buy** experience. The process has phases:\n\n";
        $prompt .= "1. **FREELINE** - Visitors can chat, ask questions, and take a free quiz\n";
        $prompt .= "2. **LOGIN** - After the quiz, guide them to create an account to save progress\n";
        $prompt .= "3. **OFFER** - Present the value of full access based on their quiz results\n";
        $prompt .= "4. **SALE** - Help them purchase when ready (don't be pushy)\n";
        $prompt .= "5. **CONTENT** - Members get full access to lessons and personalized coaching\n\n";

        // Admin gets a distinct context — they're testing/configuring, not a customer
        if ($is_admin) {
            $prompt .= "## Current User: ADMIN\n";
            $prompt .= "This user is a site administrator. Your behavior:\n";
            $prompt .= "- Be direct and technical — skip sales guidance\n";
            $prompt .= "- They may be testing you, configuring flows, or debugging\n";
            $prompt .= "- Report your current configuration when asked (provider, phase logic, etc.)\n";
            $prompt .= "- If they ask about the FLOSC process, explain how it works rather than performing it\n";
            return $prompt;
        }

        // Off-topic handling: only included if floscAdmin configured it
        $topic_scope = flosc_get_setting('ai_topic_scope', '');
        $off_topic_message = flosc_get_setting('ai_off_topic_message', '');
        $off_topic_links = flosc_get_setting('ai_off_topic_links', '');

        if ($topic_scope || $off_topic_message || $off_topic_links) {
            $prompt .= "## Topic Boundaries\n";
            if ($topic_scope) {
                $prompt .= $topic_scope . "\n\n";
            }
            if ($off_topic_message) {
                $prompt .= "### When Users Ask Off-Topic Questions\n" . $off_topic_message . "\n\n";
            }
            if ($off_topic_links) {
                $prompt .= "### Recommended External Tools\n" . $off_topic_links . "\n\n";
            }
        }

        // Phase-specific instructions for regular users
        switch ($phase) {
            case 'freeline':
                $rules = flosc_get_setting('ai_freeline_restrictions', '');
                $prompt .= "## Current Phase: FREELINE (Visitor)\n";
                $prompt .= "The user is NOT logged in. Your primary goals:\n";
                $prompt .= "- Build rapport and answer their questions\n";
                $prompt .= "- Encourage them to take the free quiz to see what they'll learn\n";
                $prompt .= "- Give them a taste of value without revealing premium content\n";
                if ($rules) {
                    $prompt .= "\n### Access Rules\n" . $rules;
                }
                break;

            case 'login':
                $prompt .= "## Current Phase: LOGIN (New Account)\n";
                $prompt .= "The user just logged in or created an account. Your primary goals:\n";
                $prompt .= "- Welcome them and acknowledge their quiz results if available\n";
                $prompt .= "- Deliver or guide them to the free lesson\n";
                $prompt .= "- Build trust before presenting any offer\n";
                break;

            case 'offer':
                $prompt .= "## Current Phase: OFFER (Post Free Lesson)\n";
                $prompt .= "The user received their free lesson. Your primary goals:\n";
                $prompt .= "- Present personalized value based on their quiz results\n";
                $prompt .= "- Address objections naturally — don't be pushy\n";
                $prompt .= "- Show what full access unlocks for their specific weak areas\n";
                break;

            case 'sale':
                $prompt .= "## Current Phase: SALE (Ready to Purchase)\n";
                $prompt .= "The user has seen the offer and is considering purchase. Your primary goals:\n";
                $prompt .= "- Help them purchase when ready — answer any final questions\n";
                $prompt .= "- Reinforce the value specific to their needs\n";
                $prompt .= "- Don't pressure — let them decide at their own pace\n";
                break;

            case 'content':
                $rules = flosc_get_setting('ai_member_access', '');
                $prompt .= "## Current Phase: CONTENT (Member)\n";
                $prompt .= "The user is a paying member with full access. Your primary goals:\n";
                $prompt .= "- Provide personalized guidance based on their progress\n";
                $prompt .= "- Help them get maximum value from lessons\n";
                $prompt .= "- Answer specific questions about content they have access to\n";
                if ($rules) {
                    $prompt .= "\n### Access Rules\n" . $rules;
                }
                break;

            default:
                $prompt .= "## Current Phase: " . strtoupper($phase) . "\n";
                break;
        }

        return $prompt;
    }

    /**
     * Load phase-specific prompt from admin settings
     */
    private function load_phase_prompt($phase) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        return flosc_get_setting("ai_prompt_{$phase}", '');
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
        // v1.9.0: Skip keys handled separately in build_system_prompt()
        $skip_keys = ['ivr_guidance'];

        foreach ($context as $key => $value) {
            if (in_array($key, $skip_keys)) continue;
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
        return "You are the {$product['name']} AI assistant. Your mission is to help users learn and improve through personalized guidance and encouragement. Be helpful, friendly, specific, and action-oriented. Always reference the user's quiz results and progress when available.";
    }

    /**
     * Get AI Response
     * @param bool $test_mode If true, return WP_Error on failure instead of falling back to IVR
     */
    public function get_response($message, $system_prompt = '', $context = [], $test_mode = false) {
        // Skip cache in test mode
        if (!$test_mode) {
            // Check cache first — include context in key so different users/sessions don't share cached responses
            $context_hash = !empty($context) ? md5(json_encode($context)) : '';
            $cache_key = 'flosc_ai_' . md5($this->provider . $message . $system_prompt . $context_hash);
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                return $cached;
            }
        }

        // v1.9.0: Provider chaining — send user message through multiple AI providers sequentially.
        // AI1 responds first, then AI2 sees AI1's response as context and refines/reviews it.
        $chaining_enabled = !$test_mode && flosc_get_setting('ai_enable_chaining', false);

        if ($chaining_enabled) {
            $response = $this->get_chained_response($message, $system_prompt, $context);
        } else {
            $response = $this->call_provider($this->provider, $message, $system_prompt, $context, $test_mode);
        }

        // Cache for 1 hour (IVR responses are static anyway), but not in test mode
        if (!$test_mode && $response && !is_wp_error($response)) {
            set_transient($cache_key, $response, HOUR_IN_SECONDS);
        }

        return $response;
    }

    /**
     * v1.9.0: Route to a single provider by name
     */
    private function call_provider($provider, $message, $system_prompt, $context, $test_mode) {
        switch ($provider) {
            case 'openai':    return $this->openai_request($message, $system_prompt, $context, $test_mode);
            case 'anthropic': return $this->anthropic_request($message, $system_prompt, $context, $test_mode);
            case 'xai':       return $this->xai_request($message, $system_prompt, $context, $test_mode);
            case 'ivr':
            default:          return $this->ivr_response($message);
        }
    }

    /**
     * v1.9.0: Provider Chaining
     * Sends the user message through multiple AI providers sequentially.
     * Each provider sees the previous provider's response as context.
     * Example: OpenAI drafts → Anthropic refines → final response.
     */
    private function get_chained_response($message, $system_prompt, $context) {
        $chain = [];
        for ($i = 1; $i <= 3; $i++) {
            $p = flosc_get_setting("ai_chain_provider_{$i}", '');
            if (!empty($p) && $p !== 'none') {
                $chain[] = $p;
            }
        }

        // Need at least 2 providers for chaining — otherwise just use single provider
        if (count($chain) < 2) {
            return $this->call_provider($chain[0] ?? $this->provider, $message, $system_prompt, $context, false);
        }

        $response = '';
        $chain_context = $context;
        $chain_log = [];

        foreach ($chain as $provider_name) {
            $result = $this->call_provider($provider_name, $message, $system_prompt, $chain_context, false);

            if (!is_wp_error($result) && !empty($result)) {
                $response = $result;
                $chain_log[] = $provider_name;
                // Add this provider's response as assistant context for the next provider
                $chain_context[] = ['role' => 'assistant', 'content' => $response];
            } else {
                // Chain link failed — return whatever we have so far
                break;
            }
        }

        // Store chain detail for logging
        $this->last_chain_detail = $chain_log;

        return $response ?: $this->ivr_response($message);
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
            return "Hello! Welcome to {$name}. I'm your AI assistant, ready to help you.\n\nWould you like to start with a free assessment?";
        }

        // Quiz-related triggers - more specific to avoid false positives
        if (preg_match('/\b(take.*quiz|start.*quiz|begin.*quiz|quiz.*me|assessment)\b/', $message_lower)) {
            return "Great! Let's get you started with the quiz. I'll walk you through it and show you exactly where you can improve.";
        }

        // How it works
        if (preg_match('/\bhow.*(work|does)\b/', $message_lower)) {
            return "Here's how {$name} works:\n\n1. **Take the free quiz** - See where you stand\n2. **Get instant analysis** - I'll identify areas for improvement\n3. **Try a free lesson** - Experience the teaching method\n4. **Unlock all lessons** - Full access to everything\n\nWant to start with the free quiz?";
        }

        // What will I learn
        if (preg_match('/\bwhat.*(learn|teach|will i)\b/', $message_lower)) {
            return "With {$name}, you'll develop skills tailored to your needs based on your quiz results.\n\nThe best way to see what you'll learn is to take the free quiz. Want to try it?";
        }

        // Pricing
        if (preg_match('/\b(price|cost|pay|money|expensive|cheap|how much)\b/', $message_lower)) {
            $price = $product['currency_symbol'] . $product['price'];
            return "Full lifetime access to {$name} is {$price} - that's a one-time payment with no subscriptions or hidden fees.\n\nBut first, take the free quiz to see exactly what you'll get!";
        }

        // Help
        if (preg_match('/\b(help|support|question|assist)\b/', $message_lower)) {
            return "I'm here to help! You can:\n\n• **Take the free quiz** to assess your current level\n• **Ask questions** about {$name}\n• **Start a lesson** if you have access\n\nWhat would you like to do?";
        }

        // Thank you
        if (preg_match('/\b(thank|thanks)\b/', $message_lower)) {
            return "You're welcome! Is there anything else I can help you with?";
        }

        // Default - more conversational
        return "I'm here to help! I can answer questions about {$name}, help you get started, or guide you through the free quiz. What would you like to know?";
    }
    
    /**
     * OpenAI Chat Completions
     */
    private function openai_request($message, $system_prompt, $context = [], $test_mode = false) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first
        $api_key = flosc_get_setting('openai_api_key', '');

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

        // v1.8.7: Per-flow model, temperature, max_tokens
        $model = flosc_get_setting('ai_openai_model', 'gpt-4o-mini');
        $temperature = (float) flosc_get_setting('ai_temperature', '0.7');
        $max_tokens = (int) flosc_get_setting('ai_max_tokens', '500');

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
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
        // v1.9.0: Use flosc_get_setting() — reads flow settings first
        $api_key = flosc_get_setting('anthropic_api_key', '');

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

        // v1.8.7: Per-flow model + max_tokens
        $model = flosc_get_setting('ai_anthropic_model', 'claude-sonnet-4-5-20250929');
        $max_tokens = (int) flosc_get_setting('ai_max_tokens', '500');

        $body = [
            'model' => $model,
            'max_tokens' => $max_tokens,
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
        // v1.9.0: Use flosc_get_setting() — reads flow settings first
        $api_key = flosc_get_setting('xai_api_key', '');

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

        // v1.8.7: Per-flow model, temperature, max_tokens
        $model = flosc_get_setting('ai_xai_model', 'grok-2-latest');
        $temperature = (float) flosc_get_setting('ai_temperature', '0.7');
        $max_tokens = (int) flosc_get_setting('ai_max_tokens', '500');

        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
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
        $currency = flosc_get_setting('currency', 'EUR');
        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        
        return [
            'name' => flosc_get_setting('product_name', 'FLOSC App'),
            'price' => flosc_get_setting('product_price', ''),
            'currency_symbol' => $symbols[$currency] ?? $currency,
        ];
    }
}
