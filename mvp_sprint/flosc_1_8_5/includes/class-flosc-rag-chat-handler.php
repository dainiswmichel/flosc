<?php
/**
 * FLOSC RAG Chat Handler
 * Handles AI chat with Retrieval Augmented Generation (tools + memory)
 *
 * @package FLOSC
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_RAG_Chat_Handler {

    private $flosc_rag_manager;
    private $flosc_access_controller;
    private $flosc_user_session;

    public function __construct() {
        $this->flosc_rag_manager = new FLOSC_RAG_Manager();
        // Access controller will be set when handle_with_state is called
    }

    /**
     * Handle chat with state object (v1.9.0)
     *
     * @param string $flosc_message User's message
     * @param FLOSC_User_Session $flosc_user_session User session with full context
     * @param int|null $flosc_session_id Session ID for conversation history
     * @return array Response with content and autoprompts
     */
    public function flosc_handle_with_state($flosc_message, $flosc_user_session, $flosc_session_id = null) {

        // Set user session and create access controller
        $this->flosc_user_session = $flosc_user_session;
        $this->flosc_access_controller = new FLOSC_RAG_Access_Controller($flosc_user_session);

        // Load conversation history
        $flosc_history = $this->flosc_load_conversation_history($flosc_user_session, $flosc_session_id);

        // Build system prompt using state (not hardcoded)
        $flosc_system_prompt = $this->flosc_build_system_prompt_from_state($flosc_user_session);

        // Get AI tools
        $flosc_tools = $this->flosc_rag_manager->get_ai_tools();

        // Execute RAG loop with tools
        $flosc_response = $this->flosc_execute_rag_loop(
            $flosc_message,
            $flosc_system_prompt,
            $flosc_history,
            $flosc_tools,
            $flosc_user_session
        );

        // Store conversation
        $this->flosc_store_conversation($flosc_user_session, $flosc_session_id, $flosc_message, $flosc_response);

        // Get contextual autoprompts
        $flosc_autoprompts = $this->flosc_get_contextual_autoprompts($flosc_user_session, $flosc_response);

        return [
            'content' => $flosc_response,
            'user_autoprompts' => $flosc_autoprompts,
            'phase_change' => null,
        ];
    }

    /**
     * Build system prompt from FLOSC User Session
     *
     * @param FLOSC_User_Session $flosc_user_session
     * @return string System prompt
     */
    private function flosc_build_system_prompt_from_state($flosc_user_session) {
        $flosc_state = $flosc_user_session->flosc_get();
        $flosc_user_type = $flosc_state['flosc_user_type'];
        $flosc_flow = $flosc_state['flosc_flow'];
        $flosc_quiz = $flosc_state['flosc_quiz'];
        $flosc_boundaries = $flosc_state['flosc_ivr']['flosc_boundary_rules'];

        // Persona-aware prompt based on user type
        $flosc_persona_prompts = [
            'flosc_admin' => "🎯 ADMIN MODE: You're speaking with a floscAdmin. Be technical and direct. Discuss configuration, debugging, backend operations.",

            'flosc_visitor' => "👋 NEW VISITOR: Primary goal is warmly encouraging the 2-minute quiz for their personalized free lesson. Be inviting, not pushy. NO lesson content (titles only). NO pricing (until after quiz).",

            'flosc_guest' => "🎓 QUIZ COMPLETER (score: {$flosc_quiz['flosc_score']}): CELEBRATE completion! Deliver their ONE free lesson (#{$flosc_quiz['flosc_free_lesson_number']}). Encourage full access purchase. Show catalog titles only (except free lesson).",

            'flosc_member' => "✨ FULL ACCESS MEMBER: Be their supportive learning coach. Guide to lessons based on improvement areas. Full content access. Celebrate progress!",
        ];

        $flosc_prompt = $flosc_persona_prompts[$flosc_user_type] ?? $flosc_persona_prompts['flosc_visitor'];

        $flosc_prompt .= "\n\n**CURRENT CONTEXT:**\n";
        $flosc_prompt .= "Flow: {$flosc_flow['flosc_name']}\n";
        $flosc_prompt .= "Phase: {$flosc_state['flosc_phase']}\n";
        $flosc_prompt .= "User Type: {$flosc_user_type}\n";

        // Add boundary rules
        $flosc_prompt .= "\n**YOUR BOUNDARIES:**\n";
        foreach ($flosc_boundaries as $flosc_rule => $flosc_value) {
            if (is_bool($flosc_value)) {
                $flosc_status = $flosc_value ? '✅' : '❌';
                $flosc_prompt .= "{$flosc_status} " . str_replace('flosc_', '', $flosc_rule) . "\n";
            }
        }

        return $flosc_prompt;
    }

    /**
     * Load conversation history
     *
     * @param FLOSC_User_Session $flosc_user_session
     * @param int|null $flosc_session_id
     * @return array Message history
     */
    private function flosc_load_conversation_history($flosc_user_session, $flosc_session_id) {
        $flosc_state = $flosc_user_session->flosc_get();
        $flosc_user_id = $flosc_state['flosc_user_id'];

        $flosc_session_manager = new FLOSC_Session_Manager();

        if ($flosc_user_id > 0) {
            // Logged-in: use existing Session Manager
            $flosc_session = $flosc_session_manager->get_flosc_session($flosc_session_id, $flosc_user_id);
            $flosc_messages = $flosc_session['messages'] ?? [];
        } else {
            // Visitor: use transient storage (TODO: implement in Step 4)
            $flosc_messages = [];
        }

        // Convert to API format and return last 10 messages
        return array_slice(array_map(function($flosc_msg) {
            return [
                'role' => $flosc_msg['role'],
                'content' => $flosc_msg['content'],
            ];
        }, $flosc_messages), -10);
    }

    /**
     * Execute RAG loop with tools
     *
     * @param string $flosc_message User message
     * @param string $flosc_system_prompt System prompt
     * @param array $flosc_history Conversation history
     * @param array $flosc_tools Available tools
     * @param FLOSC_User_Session $flosc_user_session
     * @return string AI response
     */
    private function flosc_execute_rag_loop($flosc_message, $flosc_system_prompt, $flosc_history, $flosc_tools, $flosc_user_session) {

        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $flosc_api_key = flosc_get_setting('anthropic_api_key', '');

        if (empty($flosc_api_key)) {
            return "AI not configured. Please add your Anthropic API key in settings.";
        }

        $flosc_model = flosc_get_setting('ai_model', 'claude-sonnet-4-5-20250929');

        // Build messages array with history
        $flosc_messages = $flosc_history;
        $flosc_messages[] = [
            'role' => 'user',
            'content' => $flosc_message
        ];

        $flosc_max_iterations = 5;

        for ($i = 0; $i < $flosc_max_iterations; $i++) {

            $flosc_response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $flosc_api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $flosc_model,
                    'max_tokens' => 2000,
                    'system' => $flosc_system_prompt,
                    'tools' => $flosc_tools,
                    'messages' => $flosc_messages,
                ]),
                'timeout' => 30,
            ]);

            if (is_wp_error($flosc_response)) {
                return "Sorry, I'm having trouble connecting. Please try again.";
            }

            $flosc_body = json_decode(wp_remote_retrieve_body($flosc_response), true);

            if (!isset($flosc_body['content'])) {
                return "Sorry, I encountered an error. Please try again.";
            }

            $flosc_stop_reason = $flosc_body['stop_reason'] ?? 'end_turn';

            if ($flosc_stop_reason === 'end_turn') {
                // AI is done
                return $this->flosc_extract_text_from_response($flosc_body['content']);
            }

            if ($flosc_stop_reason === 'tool_use') {
                // AI wants to use tools
                $flosc_messages[] = [
                    'role' => 'assistant',
                    'content' => $flosc_body['content']
                ];

                // Execute tools
                $flosc_tool_results = $this->flosc_execute_tools_from_response(
                    $flosc_body['content'],
                    $flosc_user_session
                );

                $flosc_messages[] = [
                    'role' => 'user',
                    'content' => $flosc_tool_results
                ];

                continue;
            }

            break;
        }

        return "I encountered an issue processing your request. Please try again.";
    }

    /**
     * Extract text from AI response
     *
     * @param array $flosc_content_blocks
     * @return string
     */
    private function flosc_extract_text_from_response($flosc_content_blocks) {
        $flosc_text = '';

        foreach ($flosc_content_blocks as $flosc_block) {
            if ($flosc_block['type'] === 'text') {
                $flosc_text .= $flosc_block['text'];
            }
        }

        return $flosc_text;
    }

    /**
     * Execute tools from AI response
     *
     * @param array $flosc_content_blocks
     * @param FLOSC_User_Session $flosc_user_session
     * @return array Tool results
     */
    private function flosc_execute_tools_from_response($flosc_content_blocks, $flosc_user_session) {
        $flosc_tool_results = [];

        foreach ($flosc_content_blocks as $flosc_block) {
            if ($flosc_block['type'] === 'tool_use') {
                $flosc_tool_name = $flosc_block['name'];
                $flosc_tool_input = $flosc_block['input'];
                $flosc_tool_use_id = $flosc_block['id'];

                // CRITICAL: Execute via Access Controller (not RAG manager directly!)
                // This enforces deny-by-default security
                $flosc_result = $this->flosc_access_controller->flosc_execute_tool(
                    $flosc_tool_name,
                    $flosc_tool_input
                );

                $flosc_tool_results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $flosc_tool_use_id,
                    'content' => $flosc_result
                ];
            }
        }

        return $flosc_tool_results;
    }

    /**
     * Store conversation
     *
     * @param FLOSC_User_Session $flosc_user_session
     * @param int|null $flosc_session_id
     * @param string $flosc_message
     * @param string $flosc_response
     */
    private function flosc_store_conversation($flosc_user_session, $flosc_session_id, $flosc_message, $flosc_response) {
        $flosc_state = $flosc_user_session->flosc_get();
        $flosc_user_id = $flosc_state['flosc_user_id'];

        if ($flosc_user_id > 0 && $flosc_session_id) {
            $flosc_session_manager = new FLOSC_Session_Manager();
            $flosc_session_manager->add_flosc_message($flosc_session_id, 'user', $flosc_message, $flosc_user_id);
            $flosc_session_manager->add_flosc_message($flosc_session_id, 'assistant', $flosc_response, $flosc_user_id);
        }
        // TODO: Step 4 will add visitor storage
    }

    /**
     * Get contextual autoprompts
     *
     * @param FLOSC_User_Session $flosc_user_session
     * @param string $flosc_response
     * @return array Autoprompt options
     */
    private function flosc_get_contextual_autoprompts($flosc_user_session, $flosc_response) {
        $flosc_state = $flosc_user_session->flosc_get();
        $flosc_user_type = $flosc_state['flosc_user_type'];
        $flosc_ivr_prompts = $flosc_state['flosc_ivr']['flosc_visible_autoprompts'];

        // Start with IVR's suggestions (max 3)
        $flosc_prompts = array_slice($flosc_ivr_prompts, 0, 3);

        // Add funnel-advancing prompts
        $flosc_funnel_prompts = [
            'flosc_visitor' => [
                ['user_input' => 'Take the quiz', 'action' => 'start_quiz'],
                ['user_input' => 'Tell me more', 'action' => 'continue'],
            ],
            'flosc_guest' => [
                ['user_input' => 'Show my free lesson', 'action' => 'deliver_free_lesson'],
                ['user_input' => 'Unlock full access', 'action' => 'show_pricing'],
            ],
            'flosc_member' => [
                ['user_input' => 'Continue learning', 'action' => 'next_lesson'],
                ['user_input' => 'Review weak areas', 'action' => 'review_quiz'],
            ],
        ];

        $flosc_prompts = array_merge($flosc_prompts, array_slice($flosc_funnel_prompts[$flosc_user_type] ?? [], 0, 2));
        return array_slice($flosc_prompts, 0, 4); // Max 4 pills
    }
}
