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
            // any leading assistant messages (e.g. the opening greeting).
            while (!empty($flosc_normalized) && $flosc_normalized[0]['role'] !== 'user') {
                array_shift($flosc_normalized);
            }
            $flosc_history = $flosc_normalized;
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

        // v1.9.2: FLOSC Framework Identity — prevents AI from hallucinating
        $flosc_product_name = flosc_get_setting('product_name', '');
        $flosc_product_tagline = flosc_get_setting('product_tagline', '');

        $flosc_prompt .= "\n\n**CRITICAL IDENTITY RULES:**\n";
        $flosc_prompt .= "FLOSC is a white-label WordPress plugin framework. The letters stand for: Freeline, Login, Offer, Sale, Content (the 5 funnel phases).\n";
        $flosc_prompt .= "NEVER invent what FLOSC stands for. NEVER guess what this site teaches.\n";
        if ($flosc_product_name) {
            $flosc_prompt .= "This site's product: **{$flosc_product_name}**";
            if ($flosc_product_tagline) {
                $flosc_prompt .= " — {$flosc_product_tagline}";
            }
            $flosc_prompt .= "\n";
        } else {
            $flosc_prompt .= "The product name has not been configured yet. If asked what this site teaches, say 'The site is being set up — please check back soon.'\n";
        }

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

        // v1.9.1: This RAG handler only supports Anthropic's tool-calling API.
        // If the provider isn't Anthropic, return null so handle_chat() falls through to dispatch.
        $flosc_provider = flosc_get_setting('ai_provider', 'ivr');
        if ($flosc_provider !== 'anthropic') {
            return null;
        }

        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $flosc_api_key = flosc_get_setting('anthropic_api_key', '');

        if (empty($flosc_api_key)) {
            return null; // No key — let handle_chat() fall through to dispatch
        }

        // v1.8.7: Per-flow model selection (consistent with ai-chat-dispatch)
        $flosc_model = flosc_get_setting('ai_anthropic_model', 'claude-sonnet-4-5-20250929');
        // RAG needs more tokens for tool-use loops — use 4x the configured chat max, floor 2000
        $flosc_max_tokens = max(2000, (int) flosc_get_setting('ai_max_tokens', '500') * 4);

        // Build messages array with history
        $flosc_messages = $flosc_history;
        $flosc_messages[] = [
            'role' => 'user',
            'content' => $flosc_message
        ];

        $flosc_max_iterations = 5;
        $flosc_total_input_tokens = 0;
        $flosc_total_output_tokens = 0;
        $flosc_total_real_millicents = 0;

        for ($i = 0; $i < $flosc_max_iterations; $i++) {

            $flosc_response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $flosc_api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model' => $flosc_model,
                    'max_tokens' => $flosc_max_tokens,
                    'system' => $flosc_system_prompt,
                    'tools' => $flosc_tools,
                    'messages' => $flosc_messages,
                ]),
                'timeout' => 30,
            ]);

            if (is_wp_error($flosc_response)) {
                return "Sorry, I'm having trouble connecting. Please try again.";
            }

            $flosc_raw_body = wp_remote_retrieve_body($flosc_response);
            $flosc_body = json_decode($flosc_raw_body, true);

            // Capture billing on every Anthropic round-trip (tool-use loops can
            // involve multiple calls). This keeps debit math aligned with real usage.
            $flosc_usage = is_array($flosc_body['usage'] ?? null) ? $flosc_body['usage'] : [];
            $flosc_input_tokens = max(0, intval($flosc_usage['input_tokens'] ?? 0));
            $flosc_output_tokens = max(0, intval($flosc_usage['output_tokens'] ?? 0));
            $flosc_total_input_tokens += $flosc_input_tokens;
            $flosc_total_output_tokens += $flosc_output_tokens;

            $flosc_rates = $this->flosc_resolve_anthropic_price_per_1m((string) $flosc_model);
            $flosc_input_cost = ($flosc_input_tokens * $flosc_rates['input']) / 1000000;
            $flosc_output_cost = ($flosc_output_tokens * $flosc_rates['output']) / 1000000;
            $flosc_round_real_millicents = max(0, intval(ceil($flosc_input_cost + $flosc_output_cost)));
            $flosc_total_real_millicents += $flosc_round_real_millicents;

            $this->flosc_last_billing_meta = [
                'provider' => 'anthropic',
                'model' => (string) $flosc_model,
                'usage' => [
                    'input_tokens' => $flosc_total_input_tokens,
                    'output_tokens' => $flosc_total_output_tokens,
                    'total_tokens' => ($flosc_total_input_tokens + $flosc_total_output_tokens),
                ],
                'real_millicents' => max(0, intval($flosc_total_real_millicents)),
                'source' => ($flosc_total_real_millicents > 0) ? 'token_rates' : 'none',
            ];

            if (!isset($flosc_body['content'])) {
                // v1.9.6: Log the actual API error instead of silently swallowing it
                $flosc_http_code = wp_remote_retrieve_response_code($flosc_response);
                $flosc_error_msg = $flosc_body['error']['message'] ?? 'No content in response';
                $flosc_error_type = $flosc_body['error']['type'] ?? 'unknown';
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG: API error on iteration {$i} — HTTP {$flosc_http_code}, type: {$flosc_error_type}, message: {$flosc_error_msg}");
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG: Response body (first 500 chars): " . substr($flosc_raw_body, 0, 500));
                }
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

                // v1.9.6: Anthropic requires tool_result content to be a string.
                // Access controller denial returns an array — stringify it.
                if (is_array($flosc_result)) {
                    $flosc_result = $flosc_result['flosc_user_facing_message']
                        ?? $flosc_result['flosc_reason']
                        ?? wp_json_encode($flosc_result);
                }

                $flosc_tool_results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $flosc_tool_use_id,
                    'content' => (string) $flosc_result
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
