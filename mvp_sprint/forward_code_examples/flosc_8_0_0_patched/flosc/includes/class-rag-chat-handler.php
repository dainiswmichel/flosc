    /**
     * Handle chat with RAG (Retrieval Augmented Generation) - v9.1.6
     * AI can search WordPress content dynamically
     */
    public function handle_chat_with_rag($request) {
        $message = sanitize_text_field($request->get_param('message'));
        
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }
        
        // Get user context
        $user_context = $this->user_access_manager->get_user_context();
        
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG Chat: User {$user_context['user_id']} ({$user_context['access_level']}) - Message: {$message}");
        
        // Build system prompt for AI
        $system_prompt = $this->build_rag_system_prompt($user_context);
        
        // Get available lessons list (for AI to know what exists)
        $lessons_list = $this->rag_manager->get_available_lessons($user_context['access_level']);
        
        // Add lessons to system prompt
        $system_prompt .= "\n\n**AVAILABLE CONTENT:**\n{$lessons_list}";
        
        // Get AI tools
        $tools = $this->rag_manager->get_ai_tools();
        
        // Call AI with tools (RAG enabled)
        $ai_response = $this->call_ai_with_rag($message, $system_prompt, $tools, $user_context);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => $ai_response,
            'user_context' => [
                'access_level' => $user_context['access_level'],
                'is_member' => $user_context['is_member'],
            ],
        ]);
    }
    
    /**
     * Build system prompt for RAG chat
     */
    private function build_rag_system_prompt($user_context) {
        
        $access_level = $user_context['access_level'];
        // v1.9.0: Read identity from admin-configured flow settings — NO hardcoded names or roles
        $personality_name = flosc_get_setting('ai_personality_name', '');
        $personality_desc = flosc_get_setting('ai_personality_description', '');
        $product_name = flosc_get_setting('product_name', '');
        
        // Get access level instructions
        $access_instructions = $this->get_access_level_instructions($access_level);
        
        // Build identity line from admin config (skip if not configured)
        $identity = '';
        if ($personality_name && $personality_desc) {
            $identity = "You are {$personality_name}, a {$personality_desc}.";
        } elseif ($personality_name) {
            $identity = "You are {$personality_name}.";
        } elseif ($product_name) {
            $identity = "You are the AI assistant for {$product_name}.";
        } else {
            $identity = "You are a helpful AI assistant.";
        }
        
        $prompt = "{$identity}

**YOUR ROLE:**
You are a GUIDE. Your job is to:
1. Guide users through the learning journey
2. Direct them to available lessons and content
3. Use search tools to find relevant content when needed
4. Encourage them through the funnel (visitor → quiz → member)

**CURRENT USER:**
- Access Level: **{$access_level}**
- Logged in: " . ($user_context['is_logged_in'] ? 'Yes' : 'No') . "
- Member: " . ($user_context['is_member'] ? 'Yes' : 'No') . "
";

        // Add quiz results if available
        if (isset($user_context['quiz_results'])) {
            $quiz_score = $user_context['quiz_score'] ?? 0;
            $prompt .= "\n**QUIZ RESULTS:**\n";
            $prompt .= "Score: {$quiz_score}%\n";
            $prompt .= "Details: " . json_encode($user_context['quiz_results']) . "\n";
            
            // Add pricing info if applicable
            if (isset($user_context['within_discount_window']) && $user_context['within_discount_window']) {
                $minutes_left = 30 - intval($user_context['minutes_since_quiz']);
                $prompt .= "\n**SPECIAL OFFER ACTIVE:**\n";
                $prompt .= "- User took quiz recently\n";
                $prompt .= "- {$minutes_left} minutes remaining for \$30 discount price\n";
                $prompt .= "- Mention this time-limited offer!\n";
            }
        }
        
        $prompt .= "\n" . $access_instructions;
        
        $prompt .= "\n\n**HOW TO USE TOOLS:**
- When you need information about specific lessons, use search_knowledge_base or search_posts
- When asked about what content is available, use search_posts
- When you need full lesson details, use get_lesson_content
- Always filter your responses based on the user's access level

**IMPORTANT:**
- DO NOT try to teach content yourself - point to the actual lessons
- DO respect access level restrictions
- DO encourage quiz-taking for visitors
- DO mention time-limited offers when applicable
- DO be warm and encouraging";

        return $prompt;
    }
    
    /**
     * Get access level specific instructions
     */
    private function get_access_level_instructions($access_level) {
        
        $instructions = [
            'visitor' => "
**ACCESS LEVEL: VISITOR (Not logged in)**

What you can share:
- General information about what's available
- Encourage them to take the free quiz
- Create curiosity about the content

What you MUST NOT share:
- Specific lesson content
- Member-only information

Your goal:
- Get them to take the quiz
- Show value without giving everything away",
            
            'guest' => "
**ACCESS LEVEL: GUEST (Logged in, not member)**

What you can share:
- What you shared with visitors
- Brief descriptions of lessons
- Quiz results and personalized recommendations
- Time-limited offer details

What you MUST NOT share:
- Full lesson content
- Detailed member-only guides

Your goal:
- Show them exactly what lessons they need based on quiz
- Encourage membership with time-limited offer
- Build value and urgency",
            
            'member' => "
**ACCESS LEVEL: MEMBER (Full access)**

You can share:
- ALL content
- Full lesson details
- Complete guides

Your goal:
- Help them access the right lessons
- Guide them through content
- Celebrate their membership",
        ];
        
        return $instructions[$access_level] ?? $instructions['visitor'];
    }
    
    /**
     * Call AI with RAG tools (conversation loop)
     * PSEUDOCODE: Full Anthropic API implementation with tool calling
     */
    private function call_ai_with_rag($message, $system_prompt, $tools, $user_context) {
        
        // Get AI configuration — v1.8.7: per-flow via flosc_get_setting()
        $api_key = flosc_get_setting('anthropic_api_key', '');

        if (empty($api_key)) {
            return "AI not configured. Please add your Anthropic API key in settings.";
        }

        $model = flosc_get_setting('ai_anthropic_model', 'claude-sonnet-4-5-20250929');
        
        // PSEUDOCODE: Conversation loop for tool calling
        // This allows AI to make multiple tool calls
        
        $messages = [
            [
                'role' => 'user',
                'content' => $message
            ]
        ];
        
        $max_iterations = 5; // Prevent infinite loops
        
        for ($i = 0; $i < $max_iterations; $i++) {
            
            // Call Anthropic API
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $model,
                    'max_tokens' => 2000,
                    'system' => $system_prompt,
                    'tools' => $tools,
                    'messages' => $messages,
                ]),
                'timeout' => 30,
            ]);
            
            if (is_wp_error($response)) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG Error: " . $response->get_error_message());
                return "Sorry, I'm having trouble connecting. Please try again.";
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (!isset($body['content'])) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG: Invalid API response - " . json_encode($body));
                return "Sorry, I encountered an error. Please try again.";
            }
            
            $stop_reason = $body['stop_reason'] ?? 'end_turn';
            
            // Check if AI is done or wants to use tools
            if ($stop_reason === 'end_turn') {
                // AI is done - extract and return text response
                return $this->extract_text_from_response($body['content']);
            }
            
            if ($stop_reason === 'tool_use') {
                // AI wants to use tools!
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG: AI requested tool use");
                
                // Add AI's response to conversation
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $body['content']
                ];
                
                // Execute tools and add results
                $tool_results = $this->execute_tools_from_response(
                    $body['content'],
                    $user_context['access_level']
                );
                
                $messages[] = [
                    'role' => 'user',
                    'content' => $tool_results
                ];
                
                // Continue loop - AI will process tool results
                continue;
            }
            
            // Unexpected stop reason
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG: Unexpected stop reason: {$stop_reason}");
            break;
        }
        
        // If we hit max iterations
        return "I encountered an issue processing your request. Please try again.";
    }
    
    /**
     * Extract text response from AI content blocks
     */
    private function extract_text_from_response($content_blocks) {
        $text = '';
        
        foreach ($content_blocks as $block) {
            if ($block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
        
        return $text;
    }
    
    /**
     * Execute tools requested by AI
     */
    private function execute_tools_from_response($content_blocks, $access_level) {
        
        $tool_results = [];
        
        foreach ($content_blocks as $block) {
            if ($block['type'] === 'tool_use') {
                
                $tool_name = $block['name'];
                $tool_input = $block['input'];
                $tool_use_id = $block['id'];
                
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG: Executing tool '{$tool_name}' with input: " . json_encode($tool_input));
                
                // Execute the tool
                $result = $this->rag_manager->execute_tool($tool_name, $tool_input, $access_level);
                
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log("FLOSC RAG: Tool result length: " . strlen($result) . " chars");
                
                // Format result for AI
                $tool_results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $tool_use_id,
                    'content' => $result
                ];
            }
        }
        
        return $tool_results;
    }
