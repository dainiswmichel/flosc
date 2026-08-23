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
    private $flosc_last_billing_meta = [];

    public function __construct() {
        $this->flosc_rag_manager = FLOSC_RAG_Manager::instance();
        // Access controller will be set when handle_with_state is called
    }

    /**
     * Handle chat with state object (v1.9.0)
     *
     * @param string $flosc_message User's message
     * @param FLOSC_User_Session $flosc_user_session User session with full context
     * @param int|null $flosc_session_id Session ID for conversation history
     * @param string|null $flosc_chatpack_prompt v1.9.2: Optional chatpack system prompt (overrides internal builder)
     * @return array Response with content and autoprompts
     */
    public function flosc_handle_with_state($flosc_message, $flosc_user_session, $flosc_session_id = null, $flosc_chatpack_prompt = null, $flosc_conv_history = null) {
        $this->flosc_last_billing_meta = [];

        // Set user session and create access controller
        $this->flosc_user_session = $flosc_user_session;
        $this->flosc_access_controller = new FLOSC_RAG_Access_Controller($flosc_user_session);

        // Load conversation history (server-side; populated for logged-in users).
        $flosc_history = $this->flosc_load_conversation_history($flosc_user_session, $flosc_session_id);

        // Visitors have no server-side history, so the caller reconstructs it from the
        // client's localStorage transcript and passes it in. Without this, the session
        // continuity prompt ("continue the conversation, don't re-greet") has no history
        // to act on and the model re-greets/repeats on every visitor follow-up.
        if (empty($flosc_history) && is_array($flosc_conv_history) && !empty($flosc_conv_history)) {
            $flosc_normalized = array_map(function ($flosc_msg) {
                return [
                    'role'    => in_array(($flosc_msg['role'] ?? ''), ['user', 'assistant'], true) ? $flosc_msg['role'] : 'user',
                    'content' => (string) ($flosc_msg['content'] ?? ''),
                ];
            }, $flosc_conv_history);
            $flosc_normalized = array_slice($flosc_normalized, -10);
            // Anthropic requires the messages array to begin with a user turn, so drop
            // any leading assistant messages (e.g. the opening greeting). Preserve text
            // in the system prompt so the model does not re-greet when history is only
            // [opening assistant, current user].
            $flosc_stripped_openings = [];
            while (!empty($flosc_normalized) && $flosc_normalized[0]['role'] !== 'user') {
                $flosc_lead = array_shift($flosc_normalized);
                if (($flosc_lead['role'] ?? '') === 'assistant') {
                    $flosc_lead_c = trim((string) ($flosc_lead['content'] ?? ''));
                    if ($flosc_lead_c !== '') {
                        $flosc_stripped_openings[] = $flosc_lead_c;
                    }
                }
            }
            $flosc_history = array_values($flosc_normalized);
            if (!empty($flosc_stripped_openings) && is_string($flosc_chatpack_prompt)
                && strpos($flosc_chatpack_prompt, 'ALREADY DELIVERED IN THIS CHAT') === false) {
                $flosc_chatpack_prompt .= "\n\n## ALREADY DELIVERED IN THIS CHAT (do not repeat)\n"
                    . "The following assistant message(s) were already shown to the user in this session. "
                    . "Do NOT re-greet, re-introduce, or re-ask language preference. Answer the current message directly.\n\n"
                    . implode("\n\n---\n\n", $flosc_stripped_openings);
            }
        }

        // v1.9.2: Use chatpack prompt if provided (includes feedback, praise, KB, WP info)
        // Falls back to internal builder for backward compatibility
        $flosc_system_prompt = $flosc_chatpack_prompt
            ? $flosc_chatpack_prompt
            : $this->flosc_build_system_prompt_from_state($flosc_user_session);

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

        // If RAG loop returned null (e.g. missing API key), signal failure so handle_chat falls through to dispatch
        if ( $flosc_response === null ) {
            return null;
        }

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
     * Get billing metadata for the most recent RAG response.
     * Shape mirrors ai-chat-dispatch get_last_billing_meta().
     */
    public function get_last_billing_meta() {
        return is_array($this->flosc_last_billing_meta) ? $this->flosc_last_billing_meta : [];
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

        $flosc_prompt .= "\n\n**CRITICAL IDENTITY RULES:**\n";
        $flosc_prompt .= "FLOSC is a white-label WordPress plugin framework. The letters stand for: Freeline, Login, Offer, Sale, Content (the 5 funnel phases).\n";
        $flosc_prompt .= "NEVER invent facts about this floscFlow. Use the attached personality and current flow context.\n";

        $flosc_prompt .= "\n**CURRENT CONTEXT:**\n";
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

        // Off-topic handling: only included if floscAdmin configured it
        $flosc_topic_scope = flosc_get_setting('ai_topic_scope', '');
        $flosc_off_topic_message = flosc_get_setting('ai_off_topic_message', '');
        $flosc_off_topic_links = flosc_get_setting('ai_off_topic_links', '');

        if ($flosc_topic_scope || $flosc_off_topic_message || $flosc_off_topic_links) {
            $flosc_prompt .= "\n**TOPIC BOUNDARIES:**\n";
            if ($flosc_topic_scope) {
                $flosc_prompt .= $flosc_topic_scope . "\n\n";
            }
            if ($flosc_off_topic_message) {
                $flosc_prompt .= "**When Users Ask Off-Topic Questions:**\n" . $flosc_off_topic_message . "\n\n";
            }
            if ($flosc_off_topic_links) {
                $flosc_prompt .= "**Recommended External Tools:**\n" . $flosc_off_topic_links . "\n\n";
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
            // Logged-in: session for this flow only (no cross-flow history).
            $flosc_flow = (string) ($flosc_state['flosc_flow_id'] ?? $flosc_state['flow_id'] ?? '');
            $flosc_session = $flosc_session_manager->get_flosc_session($flosc_session_id, $flosc_user_id, $flosc_flow);
            $flosc_messages = is_array($flosc_session) ? ($flosc_session['messages'] ?? []) : [];
        } else {
            // Visitor: no persistent chat history
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

        // RAG tool-calling is Anthropic-only, through the WordPress AI Client.
        // If the provider isn't Anthropic, return null so handle_chat() falls through to dispatch.
        $flosc_provider = flosc_get_setting('ai_provider', 'ivr');
        if ($flosc_provider !== 'anthropic') {
            return null;
        }

        $flosc_api_key = function_exists( 'flosc_get_provider_api_key' ) ? flosc_get_provider_api_key( 'anthropic' ) : flosc_get_setting( 'anthropic_api_key', '' );

        if (empty($flosc_api_key)) {
            return null; // No key — let handle_chat() fall through to dispatch
        }

        if ( ! class_exists( 'FLOSC_WP_AI_Client' ) || ! FLOSC_WP_AI_Client::is_provider_registered( 'anthropic' ) ) {
            return null;
        }

        $flosc_model = flosc_get_setting('ai_anthropic_model', 'claude-sonnet-4-5-20250929');
        $flosc_max_tokens = max(2000, (int) flosc_get_setting('ai_max_tokens', '500') * 4);

        $flosc_result = FLOSC_WP_AI_Client::generate_with_tools(
            array(
                'provider'      => 'anthropic',
                'message'       => $flosc_message,
                'system_prompt' => $flosc_system_prompt,
                'history'       => is_array( $flosc_history ) ? $flosc_history : array(),
                'model'         => (string) $flosc_model,
                'max_tokens'    => $flosc_max_tokens,
                'tools'         => is_array( $flosc_tools ) ? $flosc_tools : array(),
            ),
            function ( $flosc_name, $flosc_input, $flosc_id ) {
                unset( $flosc_id );
                $flosc_out = $this->flosc_access_controller->flosc_execute_tool(
                    $flosc_name,
                    is_array( $flosc_input ) ? $flosc_input : array()
                );
                if ( is_array( $flosc_out ) ) {
                    $flosc_out = $flosc_out['flosc_user_facing_message']
                        ?? $flosc_out['flosc_reason']
                        ?? wp_json_encode( $flosc_out );
                }
                return (string) $flosc_out;
            }
        );

        if ( is_wp_error( $flosc_result ) ) {
            if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
                flosc_log( 'FLOSC RAG: ' . $flosc_result->get_error_message() );
            }
            return "Sorry, I'm having trouble connecting. Please try again.";
        }

        $flosc_usage = isset( $flosc_result['usage'] ) && is_array( $flosc_result['usage'] ) ? $flosc_result['usage'] : array();
        $flosc_input_tokens = max( 0, intval( $flosc_usage['prompt_tokens'] ?? $flosc_usage['input_tokens'] ?? 0 ) );
        $flosc_output_tokens = max( 0, intval( $flosc_usage['completion_tokens'] ?? $flosc_usage['output_tokens'] ?? 0 ) );
        $flosc_used_model = (string) ( $flosc_result['model'] ?? $flosc_model );
        $flosc_rates = $this->flosc_resolve_anthropic_price_per_1m( $flosc_used_model );
        $flosc_input_cost = ( $flosc_input_tokens * $flosc_rates['input'] ) / 1000000;
        $flosc_output_cost = ( $flosc_output_tokens * $flosc_rates['output'] ) / 1000000;
        $flosc_total_real_millicents = max( 0, intval( ceil( $flosc_input_cost + $flosc_output_cost ) ) );

        $this->flosc_last_billing_meta = array(
            'provider' => 'anthropic',
            'model'    => $flosc_used_model,
            'usage'    => array(
                'input_tokens'  => $flosc_input_tokens,
                'output_tokens' => $flosc_output_tokens,
                'total_tokens'  => ( $flosc_input_tokens + $flosc_output_tokens ),
            ),
            'real_millicents' => $flosc_total_real_millicents,
            'source'          => ( $flosc_total_real_millicents > 0 ) ? 'token_rates' : 'none',
        );

        $flosc_text = isset( $flosc_result['text'] ) ? (string) $flosc_result['text'] : '';
        return $flosc_text !== '' ? $flosc_text : "I encountered an issue processing your request. Please try again.";
    }

    /**
     * Resolve Anthropics pricing (real millicents per 1M tokens) for billing math.
     * Flow-level overrides win when configured.
     */
    private function flosc_resolve_anthropic_price_per_1m($flosc_model) {
        $override_in = max(0, intval(flosc_get_setting('ai_billing_anthropic_input_millicents_per_1m', 0)));
        $override_out = max(0, intval(flosc_get_setting('ai_billing_anthropic_output_millicents_per_1m', 0)));
        if ($override_in > 0 || $override_out > 0) {
            return [
                'input' => $override_in,
                'output' => $override_out,
            ];
        }

        $m = strtolower((string) $flosc_model);
        $seed = ['input' => 300000, 'output' => 1500000];
        if (strpos($m, 'haiku') !== false) {
            $seed = ['input' => 100000, 'output' => 500000];
        } elseif (strpos($m, 'opus') !== false) {
            $seed = ['input' => 500000, 'output' => 2500000];
        } elseif (strpos($m, 'sonnet') !== false) {
            $seed = ['input' => 300000, 'output' => 1500000];
        }

        return [
            'input' => max(0, intval($seed['input'] ?? 0)),
            'output' => max(0, intval($seed['output'] ?? 0)),
        ];
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
            $flosc_flow = (string) ($flosc_state['flosc_flow_id'] ?? $flosc_state['flow_id'] ?? '');
            $flosc_session_manager->add_flosc_message($flosc_session_id, 'user', $flosc_message, $flosc_user_id, null, $flosc_flow);
            $flosc_session_manager->add_flosc_message($flosc_session_id, 'assistant', $flosc_response, $flosc_user_id, null, $flosc_flow);
        }
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
