<?php
/**
 * Chat turn: user input → floscFlow response (handle_chat paths).
 *
 * Trait keeps Framework private helpers callable as $this.
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

trait FLOSC_Chat_Turn_Trait {
    /**
     * A visitor's turn must never end as WordPress fatal HTML inside a chat
     * bubble. Everything below this point may touch third-party provider code,
     * so the whole turn is wrapped: any Throwable becomes a controlled FLOSC
     * reply, with the technical reason kept for the log rather than the visitor.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_chat($request) {
        try {
            return $this->handle_chat_turn($request);
        } catch (Throwable $e) {
            if (function_exists('flosc_log')) {
                flosc_log(sprintf(
                    'Chat turn failed: %s in %s:%d — %s',
                    get_class($e),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ));
            }

            $flosc_error_code = 'flosc_chat_turn_exception';
            $flosc_friendly_message = __( 'Something went wrong on our side just then. Please try that again.', 'flosc' );

            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'message'    => $flosc_friendly_message,
                    'response'   => $flosc_friendly_message, // Backward compatibility.
                    'error'      => $flosc_error_code,        // Backward compatibility / machine code.
                    'error_code' => $flosc_error_code,
                ),
                200
            );
        }
    }

    private function handle_chat_turn($request) {
        $flosc_chat_start_time = microtime(true);
        $flosc_response_source = 'ivr'; // Track how response was generated

        $message = sanitize_text_field($request->get_param('message'));
        $session_id_raw = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
        $session_id = $this->flosc_normalize_session_id($session_id_raw);
        // Opaque per-conversation id minted in the browser and kept across login.
        // session_id changes at that moment (hashed visitor id -> numeric session
        // id), so without this the chat log splits one conversation into two.
        $journey_id = class_exists('FLOSC_Chat_Logger')
            ? FLOSC_Chat_Logger::flosc_sanitize_journey_id($request->get_param('journey_id'))
            : '';
        $context = $request->get_param('context') ?? [];
        if (is_array($context)) {
            if (isset($context['browsing_page_url'])) {
                $context['browsing_page_url'] = esc_url_raw((string) $context['browsing_page_url']);
            }
            if (isset($context['browsing_page_title'])) {
                $context['browsing_page_title'] = sanitize_text_field((string) $context['browsing_page_title']);
            }
            if (isset($context['browsing_page_path'])) {
                $context['browsing_page_path'] = sanitize_text_field((string) $context['browsing_page_path']);
            }
            if (isset($context['browsing_page_referrer'])) {
                $context['browsing_page_referrer'] = esc_url_raw((string) $context['browsing_page_referrer']);
            }
            if (isset($context['browsing_surface'])) {
                $context['browsing_surface'] = sanitize_key((string) $context['browsing_surface']);
            }
            if (isset($context['browsing_page_post_id'])) {
                $context['browsing_page_post_id'] = absint($context['browsing_page_post_id']);
            }
            if (isset($context['browsing_tab_index'])) {
                $context['browsing_tab_index'] = absint($context['browsing_tab_index']);
            }
            if (isset($context['browsing_surf_trail'])) {
                $trail = is_array($context['browsing_surf_trail']) ? $context['browsing_surf_trail'] : [];
                $safe_trail = [];
                foreach (array_slice($trail, 0, 10) as $trail_item) {
                    if (!is_array($trail_item)) {
                        continue;
                    }
                    $safe_url = esc_url_raw((string) ($trail_item['url'] ?? ''));
                    if ('' === $safe_url) {
                        continue;
                    }
                    $safe_trail[] = [
                        'url' => $safe_url,
                        'title' => sanitize_text_field((string) ($trail_item['title'] ?? '')),
                        'path' => sanitize_text_field((string) ($trail_item['path'] ?? '')),
                    ];
                }
                $context['browsing_surf_trail'] = $safe_trail;
            }
        }
        
        // v1.3.7: Get flow context from request
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        $ivr_file = sanitize_file_name($request->get_param('ivr_file') ?? '');
        // Normalize stem so visitor charge keys match V→G remaining lookup.
        if ($flow_id !== '') {
            $flow_id = $this->flosc_normalize_flow_stem($flow_id);
        }

        // Chat always belongs to a flow. Prefer request params; else current route.
        if ($flow_id === '' && $ivr_file === '' && method_exists($this, 'get_current_flow')) {
            $current_flow = $this->get_current_flow();
            if (is_array($current_flow)) {
                $ivr_file = sanitize_file_name((string) ($current_flow['ivr_file'] ?? $current_flow['ivr'] ?? ''));
                $flow_id  = $this->flosc_normalize_flow_stem(
                    (string) ($current_flow['id'] ?? $ivr_file)
                );
            }
        }
        if ($flow_id === '' && $ivr_file === '') {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'flow_id required',
                'code'    => 'flow_id_required',
            ], 400);
        }
        
        // v1.8.9 FIX: Set flow context so flosc_get_setting() can find API keys
        // REST calls from flosc.ai go to the WordPress host/wp-json — HTTP_HOST is the WordPress host,
        // not the custom domain, so get_current_flow() fails. This forces the flow.
        if (!empty($flow_id)) {
            $this->set_flow_context($flow_id);
        }
        
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }

        // v1.4.0: Admin Introspection - Let admins ask the chat about itself
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $introspection_response = $this->check_admin_introspection($message, $ivr_file);
            if ($introspection_response) {
                return new WP_REST_Response([
                    'success' => true,
                    'message' => $introspection_response,
                    'user_autoprompts' => $this->get_admin_introspection_prompts(),
                    'phaseChange' => null,
                    'isAdminIntrospection' => true,
                ]);
            }
        }

        // Flow bag first (flow_id and/or ivr_file → stem). .md only if bag empty.
        $ivr_config = [];
        $flow_key = '';
        $fs = [];
        if ($flow_id !== '' || $ivr_file !== '') {
            $resolved = flosc_resolve_flow_runtime($flow_id, $ivr_file);
            $flow_key = (string) ($resolved['flow_key'] ?? '');
            if ($flow_key !== '') {
                $fs = get_option($flow_key, []);
                if (!is_array($fs)) {
                    $fs = [];
                }
            }
            if (!empty($resolved['messages'])) {
                $ivr_config = [
                    'messages' => $resolved['messages'],
                    'phases'   => $resolved['phases'] ?? [],
                    'styles'   => $resolved['styles'] ?? [],
                ];
            }
        }

        // Fallback: try global option or default parser
        if (empty($ivr_config) || empty($ivr_config['messages'])) {
            $ivr_config = get_option('flosc_ivr_config', []);
        }

        // No generic-persona fallback: when a flow has no IVR of its own (DB empty and no
        // global config), serve its configurable fallback phrase — repeated, with no AI and
        // no persona. The .md files are portability exports and are never a runtime persona
        // fallback. The phrase appearing tells the floscAdmin the flow is not yet configured.
        if (empty($ivr_config) || empty($ivr_config['messages'])) {
            $flosc_fallback_phrase = trim((string) $this->get_setting('fallback_phrase', ''));
            if ('' === $flosc_fallback_phrase) {
                $flosc_fallback_phrase = __('Unfortunately, this chat is in fallback mode right now. Please check back later, or contact the chat host directly.', 'flosc');
            }
            return new WP_REST_Response([
                'success'     => true,
                'message'     => $flosc_fallback_phrase,
                'phaseChange' => null,
                'fallback'    => true,
            ], 200);
        }

        // Concierge: keyword-triggered messages with an optional password gate.
        // Operates on the IVR already loaded above (no extra query) and intercepts
        // before the normal IVR/AI path so a primed guest is recognised immediately.
        $concierge_session  = '';
        $concierge_seed_response = null;
        $concierge_guidance = '';
        $trajectory_guidance = '';
        if (class_exists('FLOSC_Trajectory')) {
            $trajectory_flow_settings = [];
            if (!empty($flow_key) && isset($fs) && is_array($fs)) {
                $trajectory_flow_settings = $fs;
            } elseif (!empty($ivr_file)) {
                $trajectory_key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_file, PATHINFO_FILENAME));
                $trajectory_flow_settings = get_option($trajectory_key, []);
            }
            $trajectory_guidance = FLOSC_Trajectory::active_guidance($message, $trajectory_flow_settings);
        }
        if (class_exists('FLOSC_Concierge')) {
            $concierge_session  = $this->flosc_concierge_session_key($session_id);
            $concierge_response = FLOSC_Concierge::handle($message, $concierge_session, $ivr_config);
            if (is_array($concierge_response)) {
                $concierge_state = (string) ($concierge_response['flosc_concierge'] ?? '');
                if (in_array($concierge_state, ['awaiting_password', 'retry'], true)) {
                    // A canned gate response (password prompt / retry) — short-circuit.
                    return new WP_REST_Response($concierge_response);
                }
                if ($concierge_state === 'revealing') {
                    $concierge_seed_response = [
                        'content' => (string) ($concierge_response['content'] ?? ''),
                    ];
                }
            }
            // Desk open for this guest? Inject the authorized brief so assistant hosts
            // the reveal in her own voice; otherwise this is the empty string and the
            // normal chat path is byte-for-byte unchanged. The brief speaks ONLY in
            // reply to the guest's own messages — never the auto-welcome or any other
            // "[SYSTEM:…]" generation — so the content surfaces only after the keyword.
            $concierge_guidance = (strncmp($message, '[SYSTEM:', 8) !== 0)
                ? FLOSC_Concierge::active_guidance($concierge_session)
                : '';
        }

        // v1.9.6 FIX: Use backend-authoritative phase determination
        // Previously: $phase = $context['phase'] ?? 'freeline';
        // Bug: buildIVRContext() in JS never set 'phase', so it always defaulted to 'freeline'
        // even for logged-in users. This broke IVR matching for login/offer/sale/content phases
        // and caused free lesson requests to fall through to AI (which errored out).
        // Now: Backend determines phase from user meta (is_member, funnel_completed, etc.)
        // Frontend context['phase'] is accepted only as a hint if backend can't determine.
        $phase = $this->determine_flosc_phase();
        if (is_user_logged_in() && $flow_id !== '' && $this->sale_manager && method_exists($this->sale_manager, 'access')) {
            $phase_user = get_current_user_id();
            $flow_member = (bool) $this->sale_manager->access()->is_member($phase_user, $flow_id);
            if ($flow_member) {
                $phase = 'content';
            } elseif ($phase === 'content') {
                $funnel_complete = get_user_meta($phase_user, '_flosc_funnel_completed', true);
                $free_delivered  = get_user_meta($phase_user, '_flosc_free_content_item_delivered', true);
                if ($funnel_complete) {
                    $phase = 'sale';
                } elseif ($free_delivered) {
                    $phase = 'offer';
                } else {
                    $phase = 'login';
                }
            }
        }
        
        // v1.1.0: Start with frontend context, then OVERRIDE with authoritative backend values
        // This prevents frontend from spoofing logged_in, user_id, etc.
        $eval_context = $context; // Frontend context first
        
        // Authoritative backend values (cannot be overridden by frontend)
        $eval_context['logged_in'] = is_user_logged_in();
        $eval_context['user_id'] = is_user_logged_in() ? get_current_user_id() : 0;
        $eval_context['phase'] = $phase;
        $eval_context['message_count'] = intval($context['message_count'] ?? 0);
        $eval_context['last_message'] = $message;
        $eval_context['flow_id'] = $flow_id;
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_data = get_userdata($user_id);
            $eval_context['user_name'] = $user_data->display_name ?? 'there';
            $eval_context['user_email'] = $user_data->user_email;
            $eval_context['is_admin'] = user_can($user_id, 'manage_options');
            // Per-flow userState: member only when entitled on this flow's stem.
            $simple_state = 'guest';
            $state_flow   = !empty($flow_id) ? $flow_id : '';
            if ( $this->sale_manager && method_exists( $this->sale_manager, 'access' ) ) {
                $simple_state = $this->sale_manager->access()->get_simple_state( $user_id, $state_flow );
            } elseif ( $this->member_access && method_exists( $this->member_access, 'is_member' ) ) {
                $simple_state = $this->member_access->is_member( $user_id, $state_flow ) ? 'member' : 'guest';
            }
            $eval_context['access_level'] = ( $simple_state === 'member' ) ? 'member' : 'guest';
            $eval_context['is_member']    = ( $eval_context['access_level'] === 'member' );
            // "purchased" for AI/IVR: entitlement (full access), not only _flosc_purchased meta.
            // Actual commerce flag remains on FLOSC_USER.purchased for frontend messaging.
            $eval_context['purchased']    = $eval_context['is_member'];
            $eval_context['state']        = $simple_state;
            $eval_context['hasAccess']    = $eval_context['is_member'];

            if (!empty($flow_id)) {
                $this->record_user_flow_usage($user_id, $flow_id, 'chat');
            }
        } else {
            $eval_context['access_level'] = 'visitor';
            $eval_context['is_member']    = false;
            $eval_context['purchased']    = false;
            $eval_context['state']        = 'visitor';
            $eval_context['hasAccess']    = false;
        }

        // v8.0.0: Track page/post ID for chat logs; inject body only on page-intent messages.
        $page_context_session_key = $session_id > 0
            ? (string) $session_id
            : $session_id_raw;
        // Page awareness is an optional enhancement layer: it enriches the AI prompt with
        // the post/page/product the visitor is viewing. It must never break the core chat
        // response, so each stage is guarded independently and any failure is recorded in
        // $flosc_page_ctx_note (surfaced into chain_detail / Chat Logs) instead of 500-ing
        // the whole /chat request.
        //   Stage 1 (metadata): gives every visitor/guest/member "what page am I on?" awareness.
        //   Stage 2 (body):     ingests the page content only when the message asks about it.
        $flosc_page_ctx_note = '';
        try {
            $page_context = FLOSC_Page_Context::instance();

            try {
                $page_context->normalize_browsing_post_id($eval_context);
            } catch (\Throwable $flosc_meta_error) {
                $flosc_page_ctx_note = 'meta_err:' . $flosc_meta_error->getMessage();
                flosc_log('[FLOSC] Page context metadata failed: ' . $flosc_meta_error->getMessage()
                    . ' @ ' . $flosc_meta_error->getFile() . ':' . $flosc_meta_error->getLine());
            }

            try {
                $page_context->maybe_attach_to_context(
                    $eval_context,
                    $message,
                    $page_context_session_key
                );
            } catch (\Throwable $flosc_body_error) {
                $flosc_page_ctx_note = ($flosc_page_ctx_note !== '' ? $flosc_page_ctx_note . ';' : '')
                    . 'body_err:' . $flosc_body_error->getMessage();
                flosc_log('[FLOSC] Page context body failed: ' . $flosc_body_error->getMessage()
                    . ' @ ' . $flosc_body_error->getFile() . ':' . $flosc_body_error->getLine());
            }
        } catch (\Throwable $flosc_page_context_error) {
            $flosc_page_ctx_note = 'init_err:' . $flosc_page_context_error->getMessage();
            flosc_log('[FLOSC] Page context init failed: ' . $flosc_page_context_error->getMessage()
                . ' @ ' . $flosc_page_context_error->getFile() . ':' . $flosc_page_context_error->getLine());
        }

        $session_end_contact_form_submit = !empty($request->get_param('session_end_contact_form_submit'));
        if ($session_end_contact_form_submit && !is_user_logged_in() && $session_id > 0) {
            $contact_form = $request->get_param('contact_form');
            if (!is_array($contact_form)) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Invalid guest account request payload.', 'flosc'),
                    'error_code' => 'contact_form_invalid',
                ], 400);
            }

            $contact_email = sanitize_email((string) ($contact_form['email'] ?? ''));
            if ($contact_email !== '' && is_email($contact_email) && $this->is_guest_request_email_blocked($contact_email)) {
                $thank_you_message = __('Thanks, dear guest. Your request has been recorded and an administrator will respond by email.', 'flosc');
                $redirect_url = $this->flosc_get_visitor_session_end_redirect_url($flow_id);
                $contact_log_message = implode("\n", [
                    '[GUEST ACCOUNT REQUEST BLOCKED]',
                    'First Name: ' . sanitize_text_field((string) ($contact_form['first_name'] ?? '')),
                    'Last Name: ' . sanitize_text_field((string) ($contact_form['last_name'] ?? '')),
                    'Email: ' . $contact_email,
                    'Phone: ' . sanitize_text_field((string) ($contact_form['phone'] ?? '')),
                    'Message: ' . sanitize_textarea_field((string) ($contact_form['message'] ?? '')),
                ]);

                FLOSC_Chat_Logger::instance()->flosc_log_chat([
                    'flow_id'         => $flow_id,
                    'phase'           => $phase,
                    'user_id'         => 0,
                    'session_id'      => $session_id,
                    'journey_id'      => $journey_id,
                    'user_message'    => $contact_log_message,
                    'ai_response'     => $thank_you_message,
                    'provider'        => 'ivr',
                    'chain_detail'    => [],
                    'response_source' => 'visitor_session_end_contact_form_blocked',
                    'response_time_ms'=> 0,
                    'billing_source'  => 'none',
                    'billing_model'   => '',
                    'billing_input_tokens' => 0,
                    'billing_output_tokens'=> 0,
                    'billing_total_tokens' => 0,
                    'billing_real_millicents' => 0,
                ]);

                return new WP_REST_Response([
                    'success' => true,
                    'message' => $thank_you_message,
                    'session_end' => [
                        'contact_saved' => true,
                        'input_locked' => true,
                        'redirect_url' => $redirect_url,
                    ],
                ]);
            }

            $result = $this->process_contact_form_submission([
                'first_name' => (string) ($contact_form['first_name'] ?? ''),
                'last_name' => (string) ($contact_form['last_name'] ?? ''),
                'email' => (string) ($contact_form['email'] ?? ''),
                'phone' => (string) ($contact_form['phone'] ?? ''),
                'message' => (string) ($contact_form['message'] ?? ''),
                'honeypot' => (string) ($contact_form['company'] ?? ''),
                'rendered_at' => intval($contact_form['rendered_at'] ?? 0),
            ], $flow_id, [
                'source' => 'chat_depleted',
                'enforce_timing' => true,
            ]);

            if (empty($result['success'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => __('Please check the request fields and try again.', 'flosc'),
                    'error_code' => 'contact_form_rejected',
                ], 400);
            }

            // Two intents share this path (both email the site operator via the submission above):
            // "Request Guest Account" (guest=1) also queues a guest-account request
            // for admin approval; "Submit Contact Request" (guest=0) is message-only.
            $request_guest_account = !empty($request->get_param('request_guest_account'));
            $thank_you_message = $request_guest_account
                ? __('Your guest account request has been sent — an administrator will review it and email you a link.', 'flosc')
                : __('Your message has been sent to the site operator, thank you!', 'flosc');
            $redirect_url = $this->flosc_get_visitor_session_end_redirect_url($flow_id);
            $contact_log_message = implode("\n", [
                $request_guest_account ? '[GUEST ACCOUNT REQUEST SUBMITTED]' : '[CONTACT REQUEST SUBMITTED]',
                'First Name: ' . sanitize_text_field((string) ($contact_form['first_name'] ?? '')),
                'Last Name: ' . sanitize_text_field((string) ($contact_form['last_name'] ?? '')),
                'Email: ' . sanitize_email((string) ($contact_form['email'] ?? '')),
                'Phone: ' . sanitize_text_field((string) ($contact_form['phone'] ?? '')),
                'Message: ' . sanitize_textarea_field((string) ($contact_form['message'] ?? '')),
            ]);

            FLOSC_Chat_Logger::instance()->flosc_log_chat([
                'flow_id'         => $flow_id,
                'phase'           => $phase,
                'user_id'         => 0,
                'session_id'      => $session_id,
                'journey_id'      => $journey_id,
                'user_message'    => $contact_log_message,
                'ai_response'     => $thank_you_message,
                'provider'        => 'ivr',
                'chain_detail'    => [],
                'response_source' => 'visitor_session_end_contact_form',
                'response_time_ms'=> 0,
                'billing_source'  => 'none',
                'billing_model'   => '',
                'billing_input_tokens' => 0,
                'billing_output_tokens'=> 0,
                'billing_total_tokens' => 0,
                'billing_real_millicents' => 0,
            ]);

            // Only queue a guest-account request (for the moderation panel) when the
            // visitor chose "Request Guest Account". Message-only contact submissions
            // are forwarded to the site operator but do not enter the guest-account queue.
            if ($request_guest_account) {
                $this->upsert_guest_account_request(
                    (string) ($contact_form['email'] ?? ''),
                    $flow_id,
                    (string) ($contact_form['message'] ?? '')
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => $thank_you_message,
                'session_end' => [
                    'contact_saved' => true,
                    'input_locked' => true,
                    'redirect_url' => $redirect_url,
                ],
            ]);
        }

        $session_end_contact_capture = !empty($request->get_param('session_end_contact_capture'));
        if ($session_end_contact_capture && !is_user_logged_in() && $session_id > 0) {
            $contact_details = sanitize_textarea_field((string) $message);
            $thank_you_message = __('Thank you very much!', 'flosc');
            $redirect_url = $this->flosc_get_visitor_session_end_redirect_url($flow_id);

            FLOSC_Chat_Logger::instance()->flosc_log_chat([
                'flow_id'         => $flow_id,
                'phase'           => $phase,
                'user_id'         => 0,
                'session_id'      => $session_id,
                'journey_id'      => $journey_id,
                'user_message'    => $contact_details,
                'ai_response'     => $thank_you_message,
                'provider'        => 'ivr',
                'chain_detail'    => [],
                'response_source' => 'visitor_session_end_contact',
                'response_time_ms'=> 0,
                'billing_source'  => 'none',
                'billing_model'   => '',
                'billing_input_tokens' => 0,
                'billing_output_tokens'=> 0,
                'billing_total_tokens' => 0,
                'billing_real_millicents' => 0,
            ]);

            return new WP_REST_Response([
                'success' => true,
                'message' => $thank_you_message,
                'session_end' => [
                    'contact_saved' => ($contact_details !== ''),
                    'input_locked' => true,
                    'redirect_url' => $redirect_url,
                ],
            ]);
        }

        $response_message = null;

        if (is_array($concierge_seed_response) && trim((string) ($concierge_seed_response['content'] ?? '')) !== '') {
            $response_message = [
                'content' => (string) ($concierge_seed_response['content'] ?? ''),
                'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                'phase_change' => null,
            ];
            $flosc_response_source = 'concierge';
        }

        // DA1 catalog path: content-agnostic and access-aware across all phases.
        // Rows are filtered by Status, parent Status, Flow Scope, and VGM before
        // any catalog payload can reach the conversational layer.
        $da1_catalog_reply = $this->flosc_build_da1_catalog_reply(
            $message,
            $flow_id,
            $ivr_file,
            (string) ($eval_context['access_level'] ?? 'visitor')
        );
        if ($da1_catalog_reply !== '') {
            $response_message = [
                'content' => $da1_catalog_reply,
                'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                'phase_change' => null,
            ];
            $flosc_response_source = 'da1_catalog_seed';
        }

        // v1.9.4: Chatpack — compute session tracking metadata (backend-authoritative)
        // FloscHash: permanent installation ID (generated once, stored in wp_options)
        // Session hash: per-session ID linked to parent via fingerprint prefix
        $chatpack_user_id = $eval_context['user_id'] ?? 0;
        $chatpack_flosc_hash = FLOSC_Chatpack::generate_flosc_hash();
        $chatpack_session_hash = FLOSC_Chatpack::generate_session_hash($chatpack_flosc_hash, $chatpack_user_id, $session_id);
        $chatpack_pair_number = FLOSC_Chatpack::count_message_pairs($session_id, $chatpack_user_id, $flow_id, $session_id_raw) + 1;
        $chatpack_is_first = ($chatpack_pair_number === 1);
        $chatpack_conv_history = FLOSC_Chatpack::load_conversation_history($session_id, $chatpack_user_id, 10, $flow_id, $session_id_raw);
        $flosc_prior_opening_block = '';
        
        // v2.0.7: For visitors (no session/user), use frontend-provided conversation history.
        // Visitors store chat in localStorage; JS sends last 10 messages in the payload.
        // This gives AI memory of the conversation so it doesn't repeat itself.
        if (empty($chatpack_conv_history)) {
            $visitor_history = $request->get_param('visitor_history');
            if (!empty($visitor_history) && is_array($visitor_history)) {
                $chatpack_conv_history = array_map(function($msg) {
                    return [
                        'role' => in_array($msg['role'] ?? '', ['user', 'assistant']) ? $msg['role'] : 'user',
                        'content' => sanitize_textarea_field(substr($msg['content'] ?? '', 0, 1500)), // Fix 10: raised from 500
                    ];
                }, array_slice($visitor_history, -10));
                // Update pair number based on visitor history
                $chatpack_pair_number = (int) floor(count($chatpack_conv_history) / 2) + 1;
                $chatpack_is_first = ($chatpack_pair_number <= 1);

                // The client saves the just-sent message before posting, so it arrives as the
                // LAST entry of visitor_history. Pair-number counting above needs it, but the
                // provider layer (RAG loop / dispatch) appends the current message itself — so
                // drop that trailing user turn to avoid two consecutive user turns, and strip
                // any leading assistant turns (e.g. the persisted opening greeting) so the
                // history begins with a user turn. Both are required for a valid Anthropic
                // messages array (alternating roles, user-first); otherwise the API returns 400.
                //
                // CRITICAL: do not discard stripped assistant text. On turn 2 the history is
                // often only [opening greeting, current user]. Popping the user + shifting the
                // greeting leaves an empty messages array, so the model re-greets ("Sveiks!",
                // language preference, intro) every turn. Keep those openings in the system
                // prompt as already-delivered content.
                $flosc_stripped_openings = [];
                if (!empty($chatpack_conv_history)) {
                    $flosc_tail = end($chatpack_conv_history);
                    if (is_array($flosc_tail) && ($flosc_tail['role'] ?? '') === 'user') {
                        array_pop($chatpack_conv_history);
                    }
                    while (!empty($chatpack_conv_history) && (($chatpack_conv_history[0]['role'] ?? '') !== 'user')) {
                        $flosc_lead = array_shift($chatpack_conv_history);
                        if (is_array($flosc_lead) && ($flosc_lead['role'] ?? '') === 'assistant') {
                            $flosc_lead_c = trim((string) ($flosc_lead['content'] ?? ''));
                            if ($flosc_lead_c !== '') {
                                $flosc_stripped_openings[] = $flosc_lead_c;
                            }
                        }
                    }
                    $chatpack_conv_history = array_values($chatpack_conv_history);
                }
                if (!empty($flosc_stripped_openings)) {
                    $flosc_prior_opening_block = "\n\n## ALREADY DELIVERED IN THIS CHAT (do not repeat)\n"
                        . "The following assistant message(s) were already shown to the user in this session "
                        . "(opening greeting / intro). Do NOT re-greet, do NOT re-introduce yourself, do NOT "
                        . "re-ask language preference, and do NOT restate this content unless the user asks. "
                        . "Answer the user's current message directly.\n\n"
                        . implode("\n\n---\n\n", $flosc_stripped_openings);
                }
            }
        }

        // Engagement tab: admin-configured chat inserts (shown when chat opened).
        // Appended to system prompt so subsequent AI replies know admin wrote them + content.
        // Kept out of message history to avoid role-alternation issues with providers.
        $flosc_engagement_prompt_block = '';
        $engagement_context = $request->get_param('engagement_context');
        if (!empty($engagement_context) && is_array($engagement_context)) {
            $flosc_eng_lines = [];
            foreach (array_slice($engagement_context, -5) as $flosc_eng_note) {
                if (!is_array($flosc_eng_note)) {
                    continue;
                }
                $flosc_eng_c = sanitize_textarea_field(substr((string) ($flosc_eng_note['content'] ?? ''), 0, 500));
                if ($flosc_eng_c === '') {
                    continue;
                }
                $flosc_eng_rid = sanitize_key((string) ($flosc_eng_note['rule_id'] ?? ''));
                $flosc_eng_lines[] = $flosc_eng_rid !== ''
                    ? '(' . $flosc_eng_rid . ') ' . $flosc_eng_c
                    : $flosc_eng_c;
            }
            if (!empty($flosc_eng_lines)) {
                $flosc_engagement_prompt_block = "\n\n## ADMIN ENGAGEMENT MESSAGE (already shown in chat)\n"
                    . "The site admin configured FLOSC Engagement to show the user this message when chat opened. "
                    . "It is already visible in the chat UI. Treat it as already delivered by the system (admin-written); "
                    . "do not re-deliver it unless the user asks about it. Content:\n- "
                    . implode("\n- ", $flosc_eng_lines);
            }
        }
        
        // v1.9.3: Check if frontend already found an IVR match (with client-side context).
        // Frontend has richer session context (timers, interaction state, condition evaluation)
        // so its match is authoritative. Skip redundant server-side matching when provided.
        $frontend_ivr_guidance = sanitize_textarea_field($request->get_param('ivr_guidance') ?? '');
        $frontend_ivr_name = sanitize_text_field($request->get_param('ivr_message_name') ?? '');
        
        if ($response_message === null) {
            if (!empty($frontend_ivr_guidance)) {
                // Frontend matched — use its IVR content as guidance
                $response_message = [
                    'content' => $frontend_ivr_guidance,
                    'name' => $frontend_ivr_name,
                    'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                    'phase_change' => null,
                ];
            } else {
                // v3.0.5: Check if user message matches any offer's reveal_phrase (exact match).
                // This runs server-side as a backup (client also checks) and intercepts before IVR.
                $phrase_match_offer = $this->match_offer_reveal_phrase($message, $flow_id);
                if ($phrase_match_offer) {
                    $offer_id = $phrase_match_offer['id'];
                    $offer_name = $phrase_match_offer['name'] ?? $offer_id;
                    $offer_desc = $phrase_match_offer['description'] ?? $offer_name;
                    $response_message = [
                        'content' => $offer_desc,
                        'action' => 'show_offer_' . $offer_id,
                        'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                        'phase_change' => null,
                    ];
                    $flosc_response_source = 'offer_phrase';
                } else {
                    // No frontend match, no phrase match — try server-side IVR matching
                    $response_message = $this->find_ivr_response($phase, $message, $eval_context, $ivr_config);
                }
            }
        }
        
        // v1.9.0: AI Interpreter Layer
        // IVR tells us WHAT to communicate. AI decides HOW to say it.
        // AI always manages the conversation — IVR is guidance, not a direct pipeline.
        $ai_provider = flosc_get_setting('ai_provider', 'ivr');
        $ai_available = ($ai_provider !== 'ivr' && $this->ai_chat_dispatch);
        $token_provider = $this->sale_manager->get_provider('tokens');
        $is_system_generated = (strncmp((string) $message, '[SYSTEM:', 8) === 0);
        $flow_token_enforced = $this->flosc_is_flow_chat_token_enforced($flow_id);
        // Charge only AI-backed turns. IVR/default scripted responses should not
        // debit wallets unless a separate explicit model is added for them.
        $charge_applies = ($ai_available && $token_provider && !$is_system_generated && $flow_token_enforced);
        $concierge_desk_active = (trim((string) $concierge_guidance) !== '');
        if ($concierge_desk_active) {
            $charge_applies = false;
        }
        // v8.0.13: Don't burn the guest wallet on the first post-login scripted turns.
        if ($charge_applies && is_user_logged_in()) {
            $login_user_id = get_current_user_id();
            if ($login_user_id > 0 && get_transient('flosc_just_logged_in_' . $login_user_id)) {
                $post_login_turn = intval($eval_context['message_count'] ?? 0);
                if ($post_login_turn <= 2) {
                    $charge_applies = false;
                }
            }
        }
        $visitor_balance_before = null;
        $visitor_charge_result = null;
        $visitor_reservation = null;
        $user_charge_result = null;
        $user_balance_after_charge = null;
        $billing_meta = [];

        if ($charge_applies) {
            if (is_user_logged_in()) {
                $charge_user_id = get_current_user_id();
                $user_balance_before = $this->flosc_get_user_flow_token_balance($charge_user_id, $flow_id);
                $min_cost = max(0, intval($this->flosc_get_ai_query_token_cost($flow_id, $token_provider)));
                if ($min_cost > 0 && $user_balance_before < $min_cost) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => __('Token limit reached. Add more tokens to continue.', 'flosc'),
                    ], 403);
                }
            } else {
                if ($session_id <= 0) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => $this->flosc_get_visitor_token_depleted_message($flow_id),
                        'error_code' => 'visitor_tokens_depleted',
                        'visitor_tokens_depleted' => true,
                    ], 403);
                }
                $min_cost = max(0, intval($this->flosc_get_ai_query_token_cost($flow_id, $token_provider)));
                $request_id = substr(hash('sha256', (string) $session_id . '|' . microtime(true) . '|' . wp_rand()), 0, 12);
                $visitor_reservation = $this->flosc_reserve_visitor_tokens($flow_id, $session_id, $min_cost, $request_id, $token_provider);
                if (empty($visitor_reservation['reserved'])) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => $this->flosc_get_visitor_token_depleted_message($flow_id),
                        'error_code' => 'visitor_tokens_depleted',
                        'visitor_tokens_depleted' => true,
                    ], 403);
                }
                $visitor_balance_before = intval($visitor_reservation['balance_after'] ?? 0) + intval($visitor_reservation['amount'] ?? 0);
            }
        }

        try {
        if ($response_message && $ai_available) {
            // IVR matched AND AI is configured — AI interprets the IVR guidance
            // v1.9.2: Chatpack — unified prompt with session tracking + conversation history
            $chatpack_prompt = $chatpack_is_first
                ? FLOSC_Chatpack::build_full_chatpack($phase, $eval_context, $flow_id, $chatpack_flosc_hash, $chatpack_session_hash, $chatpack_pair_number, $response_message['content'])
                : FLOSC_Chatpack::build_followup_chatpack($phase, $eval_context, $chatpack_session_hash, $chatpack_pair_number, $response_message['content']);
            if ($trajectory_guidance !== '') { $chatpack_prompt .= $trajectory_guidance; }
            if ($concierge_guidance !== '') { $chatpack_prompt .= $concierge_guidance; }
            if ($flosc_engagement_prompt_block !== '') { $chatpack_prompt .= $flosc_engagement_prompt_block; }
            if ($flosc_prior_opening_block !== '') { $chatpack_prompt .= $flosc_prior_opening_block; }
            $ai_response = $this->ai_chat_dispatch->get_response($message, $chatpack_prompt, $chatpack_conv_history);

            if ($ai_response && !is_wp_error($ai_response)) {
                // AI interpreted the IVR guidance — use AI's version
                // Keep IVR's autoprompts and phase_change (structural, not content)
                $response_message['content'] = $ai_response;
                $flosc_response_source = 'ai+ivr';
            }
            // If AI fails, fall through with original IVR content as-is
        }

        if (!$response_message) {
            // v1.9.0: No IVR match — AI responds within boundaries
            // AI is boundary-aware, IVR-aware, and FLOSC flow-aware.
            // Off-topic questions get redirected with helpful links to other AI tools.

            if ($ai_available) {
                // v1.9.1: RAG handler only supports Anthropic's tool-calling API.
                // For other providers (OpenAI, xAI, Gemini), use the dispatch class which
                // already knows how to call each provider's API correctly.
                //
                // Declared here (not only inside the RAG branch below) so the
                // billing read further down can safely test it on every path:
                // the RAG handler owns the real-cost metadata for rag turns, and
                // the decrement must read from whichever path produced the reply.
                $flosc_rag_handler = null;
                $flosc_use_rag = ($ai_provider === 'anthropic') && class_exists('FLOSC_RAG_Chat_Handler');

                if ($flosc_use_rag) {
                    // Anthropic provider — use RAG handler with tools + memory
                    // v1.9.2: Chatpack provides the system prompt (feedback, praise, KB, WP info)
                    $flosc_user_id = $eval_context['user_id'] ?? 0;
                    $flosc_user_session = new FLOSC_User_Session($flosc_user_id, $flow_id);
                    $flosc_rag_handler = new FLOSC_RAG_Chat_Handler();
                    $chatpack_prompt = $chatpack_is_first
                        ? FLOSC_Chatpack::build_full_chatpack($phase, $eval_context, $flow_id, $chatpack_flosc_hash, $chatpack_session_hash, $chatpack_pair_number)
                        : FLOSC_Chatpack::build_followup_chatpack($phase, $eval_context, $chatpack_session_hash, $chatpack_pair_number);
                    if ($trajectory_guidance !== '') { $chatpack_prompt .= $trajectory_guidance; }
                    if ($concierge_guidance !== '') { $chatpack_prompt .= $concierge_guidance; }
                    if ($flosc_engagement_prompt_block !== '') { $chatpack_prompt .= $flosc_engagement_prompt_block; }
                    if ($flosc_prior_opening_block !== '') { $chatpack_prompt .= $flosc_prior_opening_block; }
                    $flosc_rag_response = $flosc_rag_handler->flosc_handle_with_state($message, $flosc_user_session, $session_id, $chatpack_prompt, $chatpack_conv_history);

                    if ($flosc_rag_response && !is_wp_error($flosc_rag_response)) {
                        $response_message = [
                            'content' => $flosc_rag_response['content'] ?? $flosc_rag_response,
                            'user_autoprompts' => $flosc_rag_response['user_autoprompts'] ?? [],
                            'phase_change' => null,
                        ];
                        $flosc_response_source = 'rag';
                    } else {
                        // Retrieval is optional. A RAG failure must fall through to
                        // the ordinary provider before any scripted quiz fallback.
                        $flosc_use_rag = false;
                    }
                }

                if (!$flosc_use_rag && !$response_message) {
                    // All non-Anthropic providers (OpenAI, xAI, etc.) — use dispatch
                    // v1.9.2: Chatpack — unified prompt with session tracking + conversation history
                    $chatpack_prompt = $chatpack_is_first
                        ? FLOSC_Chatpack::build_full_chatpack($phase, $eval_context, $flow_id, $chatpack_flosc_hash, $chatpack_session_hash, $chatpack_pair_number)
                        : FLOSC_Chatpack::build_followup_chatpack($phase, $eval_context, $chatpack_session_hash, $chatpack_pair_number);
                    if ($trajectory_guidance !== '') { $chatpack_prompt .= $trajectory_guidance; }
                    if ($concierge_guidance !== '') { $chatpack_prompt .= $concierge_guidance; }
                    if ($flosc_engagement_prompt_block !== '') { $chatpack_prompt .= $flosc_engagement_prompt_block; }
                    if ($flosc_prior_opening_block !== '') { $chatpack_prompt .= $flosc_prior_opening_block; }
                    $dispatch_result = method_exists($this->ai_chat_dispatch, 'get_response_result')
                        ? $this->ai_chat_dispatch->get_response_result($message, $chatpack_prompt, $chatpack_conv_history)
                        : [
                            'content' => $this->ai_chat_dispatch->get_response($message, $chatpack_prompt, $chatpack_conv_history),
                            'source' => 'ai',
                            'error_code' => '',
                            'error' => '',
                        ];
                    $ai_response = $dispatch_result['content'] ?? '';
                    if (is_wp_error($ai_response)) {
                        $dispatch_result['source'] = 'fallback';
                        $dispatch_result['error'] = $ai_response->get_error_message();
                        $ai_response = '';
                    }
                    $ai_response = trim((string) $ai_response);
                    $dispatch_source = (string) ($dispatch_result['source'] ?? 'fallback');

                    if ($dispatch_source === 'fallback') {
                        do_action('flosc_ai_dispatch_failed', $dispatch_result, $phase, $flow_id);
                    }
                    $response_message = [
                        'content' => $ai_response !== '' ? $ai_response : 'I apologize, but I\'m having trouble responding right now. Please try again.',
                        'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                        'phase_change' => null,
                    ];
                    $flosc_response_source = ($dispatch_source === 'ai' && $ai_response !== '') ? 'ai' : 'fallback';
                }
            } else {
                // IVR mode or no AI - use phase default + autoprompts
                $response_message = [
                    'content' => $this->get_phase_default_response($phase, $eval_context),
                    'user_autoprompts' => $this->get_user_autoprompts_for_phase($phase, $eval_context, $ivr_config),
                    'phase_change' => null,
                ];
            }
        }
        } catch (\Throwable $flosc_provider_error) {
            if (!empty($visitor_reservation['id'])) {
                $this->flosc_settle_visitor_reservation($visitor_reservation['id'], $token_provider, []);
                $visitor_reservation = null;
            }
            throw $flosc_provider_error;
        }

        // Reputation guard: never return self-undermining hedge language.
        $response_message['content'] = $this->flosc_enforce_no_hedge_response(
            $response_message['content'] ?? '',
            $message,
            $flow_id,
            $ivr_file,
            $phase,
            $eval_context
        );

        // Store message in session if user is logged in (guarded to this flow).
        if (is_user_logged_in() && $session_id) {
            $msg_flow = $this->flosc_normalize_flow_stem((string) ($flow_id ?? ''));
            $this->session_manager->add_flosc_message($session_id, 'user', $message, get_current_user_id(), null, $msg_flow);
            $this->session_manager->add_flosc_message($session_id, 'assistant', $response_message['content'], get_current_user_id(), null, $msg_flow);
        }

        // v1.9.0: Log chat exchange for admin monitoring
        $flosc_chat_elapsed = round((microtime(true) - $flosc_chat_start_time) * 1000);
        $flosc_provider_used = $ai_available ? flosc_get_setting('ai_provider', 'ivr') : 'ivr';
        $flosc_chain_detail = ($this->ai_chat_dispatch && !empty($this->ai_chat_dispatch->last_chain_detail))
            ? $this->ai_chat_dispatch->last_chain_detail : [];
        // Persist browsing context markers in the same row so Chat Logs can
        // show where a visitor session message originated.
        $flosc_ctx_url = isset($eval_context['browsing_page_url']) ? esc_url_raw((string) $eval_context['browsing_page_url']) : '';
        $flosc_ctx_path = isset($eval_context['browsing_page_path']) ? sanitize_text_field((string) $eval_context['browsing_page_path']) : '';
        $flosc_ctx_title = isset($eval_context['browsing_page_title']) ? sanitize_text_field((string) $eval_context['browsing_page_title']) : '';
        $flosc_ctx_ref = isset($eval_context['browsing_page_referrer']) ? esc_url_raw((string) $eval_context['browsing_page_referrer']) : '';
        $flosc_ctx_surface = isset($eval_context['browsing_surface']) ? sanitize_key((string) $eval_context['browsing_surface']) : '';
        $flosc_ctx_post_id = absint($eval_context['browsing_page_post_id'] ?? 0);
        if (!is_array($flosc_chain_detail)) {
            $flosc_chain_detail = [];
        }
        if ($flosc_ctx_surface !== '') {
            $flosc_chain_detail[] = 'ctx_surface:' . $flosc_ctx_surface;
        }
        if ($flosc_ctx_post_id > 0) {
            $flosc_chain_detail[] = 'ctx_post_id:' . $flosc_ctx_post_id;
        }
        if (!empty($eval_context['page_content_injected'])) {
            $flosc_chain_detail[] = 'page_content:injected';
        }
        if (!empty($flosc_page_ctx_note)) {
            $flosc_chain_detail[] = 'page_ctx_note:' . sanitize_text_field((string) $flosc_page_ctx_note);
        }
        if ($flosc_ctx_url !== '') {
            $flosc_chain_detail[] = 'ctx_url:' . $flosc_ctx_url;
        }
        if ($flosc_ctx_path !== '') {
            $flosc_chain_detail[] = 'ctx_path:' . $flosc_ctx_path;
        }
        if ($flosc_ctx_title !== '') {
            $flosc_chain_detail[] = 'ctx_title:' . $flosc_ctx_title;
        }
        if ($flosc_ctx_ref !== '') {
            $flosc_chain_detail[] = 'ctx_ref:' . $flosc_ctx_ref;
        }
        // Read billing metadata from whichever code path actually produced this
        // reply. Anthropic replies come from the RAG handler, which accumulates
        // real provider cost during its tool loop; every other provider is
        // served by the dispatch class. Reading the wrong source leaves
        // real_millicents at 0, which silently collapses the decrement to the
        // 1-token "billing unavailable" fallback.
        if ($flosc_response_source === 'rag' && $flosc_rag_handler && method_exists($flosc_rag_handler, 'get_last_billing_meta')) {
            $billing_meta = (array) $flosc_rag_handler->get_last_billing_meta();
        } else {
            $billing_meta = method_exists($this->ai_chat_dispatch, 'get_last_billing_meta')
                ? (array) $this->ai_chat_dispatch->get_last_billing_meta()
                : [];
        }
        $billing_usage = is_array($billing_meta['usage'] ?? null) ? $billing_meta['usage'] : [];

        FLOSC_Chat_Logger::instance()->flosc_log_chat([
            'flow_id'         => $flow_id,
            'phase'           => $phase,
            'user_id'         => is_user_logged_in() ? get_current_user_id() : 0,
            'session_id'      => $session_id ?? 0,
            'journey_id'      => $journey_id,
            'user_message'    => $message,
            'ai_response'     => $response_message['content'],
            'provider'        => $flosc_provider_used,
            'chain_detail'    => $flosc_chain_detail,
            'response_source' => $flosc_response_source,
            'response_time_ms'=> $flosc_chat_elapsed,
            'billing_source'  => (string) ($billing_meta['source'] ?? ''),
            'billing_model'   => (string) ($billing_meta['model'] ?? ''),
            'billing_input_tokens' => intval($billing_usage['input_tokens'] ?? 0),
            'billing_output_tokens'=> intval($billing_usage['output_tokens'] ?? 0),
            'billing_total_tokens' => intval($billing_usage['total_tokens'] ?? 0),
            'billing_real_millicents' => intval($billing_meta['real_millicents'] ?? 0),
        ]);

        // When AI is configured, debit attempted turns even if the provider failed
        // (response_source=fallback or raw IVR text). The header token tick-down is
        // an intentional admin signal that live chat lost the AI connection.
        if ($charge_applies) {
            $charge_meta = [
                'billing' => $billing_meta,
            ];
            $real_millicents = intval($billing_meta['real_millicents'] ?? 0);
            if ($real_millicents > 0) {
                $charge_meta['real_millicents'] = $real_millicents;
            }

            if (is_user_logged_in()) {
                $charge_user_id = get_current_user_id();
                $user_charge_result = $this->flosc_charge_user_flow_tokens($charge_user_id, $flow_id, $token_provider, $billing_meta);
                if (!$user_charge_result['charged']) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => __('Token limit reached. Add more tokens to continue.', 'flosc'),
                    ], 403);
                }
                $user_balance_after_charge = intval($user_charge_result['balance_after'] ?? 0);
            } elseif (!empty($visitor_reservation['id'])) {
                $visitor_charge_result = $this->flosc_settle_visitor_reservation($visitor_reservation['id'], $token_provider, $billing_meta);
                if (!$visitor_charge_result['charged']) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => $this->flosc_get_visitor_token_depleted_message($flow_id),
                        'error_code' => 'visitor_tokens_depleted',
                        'visitor_tokens_depleted' => true,
                    ], 403);
                }
            } elseif ($session_id > 0) {
                $visitor_charge_result = $this->flosc_charge_visitor_session_tokens($flow_id, $session_id, $token_provider, $billing_meta);
                if (!$visitor_charge_result['charged']) {
                    return new WP_REST_Response([
                        'success' => false,
                        'error' => $this->flosc_get_visitor_token_depleted_message($flow_id),
                        'error_code' => 'visitor_tokens_depleted',
                        'visitor_tokens_depleted' => true,
                    ], 403);
                }
            }
        }

        $token_balance_payload = null;
        $low_token_threshold = $this->flosc_get_low_token_threshold($flow_id);
        $low_tokens_message = $this->flosc_get_visitor_low_tokens_message($flow_id);
        if (is_user_logged_in() && $user_balance_after_charge !== null) {
            $is_low_balance = ($low_token_threshold > 0 && $user_balance_after_charge <= $low_token_threshold);
            $token_balance_payload = [
                'scope' => 'user_flow',
                'value' => $user_balance_after_charge,
                'formatted' => $this->flosc_format_token_display($user_balance_after_charge),
                'low_threshold' => $low_token_threshold,
                'is_low' => $is_low_balance,
                'low_message' => $is_low_balance ? $low_tokens_message : '',
                'billing_source' => (string) ($billing_meta['source'] ?? 'none'),
                'real_millicents' => intval($billing_meta['real_millicents'] ?? 0),
            ];
        } elseif (!is_user_logged_in() && $session_id > 0 && $token_provider) {
            $visitor_value = is_array($visitor_charge_result)
                ? intval($visitor_charge_result['balance_after'] ?? 0)
                : intval($this->flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider));
            $is_low_balance = ($low_token_threshold > 0 && $visitor_value <= $low_token_threshold);
            $token_balance_payload = [
                'scope' => 'visitor_session',
                'value' => $visitor_value,
                'formatted' => $this->flosc_format_token_display($visitor_value),
                'low_threshold' => $low_token_threshold,
                'is_low' => $is_low_balance,
                'low_message' => $is_low_balance ? $low_tokens_message : '',
                'charged' => intval($visitor_charge_result['charge_tokens'] ?? 0),
                'billing_source' => (string) ($billing_meta['source'] ?? 'none'),
                'real_millicents' => intval($billing_meta['real_millicents'] ?? 0),
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $response_message['content'],
            'action' => $response_message['action'] ?? null, // v3.0.5: offer phrase actions
            'user_autoprompts' => $response_message['user_autoprompts'] ?? [],
            'phaseChange' => $response_message['phase_change'] ?? null,
            'token_balance' => $token_balance_payload,
        ]);
    }

    /**
     * Handle chat with RAG (Retrieval Augmented Generation) - v9.1.6
     * AI can search WordPress content dynamically
     */
    /**
     * A visitor's turn must never end as WordPress fatal HTML inside a chat
     * bubble. Everything below this point may touch third-party provider code,
     * so the whole turn is wrapped: any Throwable becomes a controlled FLOSC
     * reply, with the technical reason kept for the log rather than the visitor.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_chat_with_rag($request) {
        try {
            return $this->handle_chat_with_rag_turn($request);
        } catch (Throwable $e) {
            if (function_exists('flosc_log')) {
                flosc_log(sprintf(
                    'Chat turn failed: %s in %s:%d — %s',
                    get_class($e),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage()
                ));
            }

            $flosc_error_code = 'flosc_chat_turn_exception';
            $flosc_friendly_message = __( 'Something went wrong on our side just then. Please try that again.', 'flosc' );

            return new WP_REST_Response(
                array(
                    'success'    => false,
                    'message'    => $flosc_friendly_message,
                    'response'   => $flosc_friendly_message, // Backward compatibility.
                    'error'      => $flosc_error_code,        // Backward compatibility / machine code.
                    'error_code' => $flosc_error_code,
                ),
                200
            );
        }
    }

    private function handle_chat_with_rag_turn($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $context = $request->get_param('context') ?? [];
        if (is_array($context)) {
            if (isset($context['browsing_page_url'])) {
                $context['browsing_page_url'] = esc_url_raw((string) $context['browsing_page_url']);
            }
            if (isset($context['browsing_page_title'])) {
                $context['browsing_page_title'] = sanitize_text_field((string) $context['browsing_page_title']);
            }
            if (isset($context['browsing_page_path'])) {
                $context['browsing_page_path'] = sanitize_text_field((string) $context['browsing_page_path']);
            }
            if (isset($context['browsing_page_referrer'])) {
                $context['browsing_page_referrer'] = esc_url_raw((string) $context['browsing_page_referrer']);
            }
            if (isset($context['browsing_surface'])) {
                $context['browsing_surface'] = sanitize_key((string) $context['browsing_surface']);
            }
            if (isset($context['browsing_tab_index'])) {
                $context['browsing_tab_index'] = absint($context['browsing_tab_index']);
            }
            if (isset($context['browsing_surf_trail'])) {
                $trail = is_array($context['browsing_surf_trail']) ? $context['browsing_surf_trail'] : [];
                $safe_trail = [];
                foreach (array_slice($trail, 0, 10) as $trail_item) {
                    if (!is_array($trail_item)) {
                        continue;
                    }
                    $safe_url = esc_url_raw((string) ($trail_item['url'] ?? ''));
                    if ('' === $safe_url) {
                        continue;
                    }
                    $safe_trail[] = [
                        'url' => $safe_url,
                        'title' => sanitize_text_field((string) ($trail_item['title'] ?? '')),
                        'path' => sanitize_text_field((string) ($trail_item['path'] ?? '')),
                    ];
                }
                $context['browsing_surf_trail'] = $safe_trail;
            }
        }
        
        if (empty($message)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Message is required',
            ], 400);
        }

        $flosc_rag_start_time = microtime(true);
        $flow_id = sanitize_text_field((string) ($request->get_param('flow_id') ?? ''));
        $ivr_file = sanitize_file_name((string) ($request->get_param('ivr_file') ?? ''));
        $flow_stem = sanitize_key(pathinfo($ivr_file, PATHINFO_FILENAME));
        $session_id_raw = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
        $session_id = $this->flosc_normalize_session_id($session_id_raw);
        $journey_id = class_exists('FLOSC_Chat_Logger')
            ? FLOSC_Chat_Logger::flosc_sanitize_journey_id($request->get_param('journey_id'))
            : '';
        $user_context = $this->user_access_manager->get_user_context();
        $phase = sanitize_key((string) ($user_context['phase'] ?? 'content'));

        // Concierge must run on THIS route too. The frontend sends "lesson"-looking
        // queries here (/chat-rag) instead of /chat, and this handler otherwise skips
        // the IVR entirely and goes straight to the AI — so a keyword-gated concierge
        // message would never be seen. Same DB-first load and same handler as /chat,
        // and the session key is derived identically so a gate opened on one route is
        // honoured on the other.
        $concierge_session  = '';
        $concierge_guidance = '';
        $trajectory_guidance = '';
        if (class_exists('FLOSC_Concierge')) {
            if (!empty($flow_id)) {
                $this->set_flow_context($flow_id);
            }
            if ($flow_id !== '' || $ivr_file !== '') {
                $resolved = flosc_resolve_flow_runtime($flow_id, $ivr_file);
                $flow_key = (string) ($resolved['flow_key'] ?? '');
                $fs = ($flow_key !== '') ? get_option($flow_key, []) : [];
                if (!is_array($fs)) {
                    $fs = [];
                }
                if (class_exists('FLOSC_Trajectory')) {
                    $trajectory_guidance = FLOSC_Trajectory::active_guidance($message, $fs);
                }
                $flow_messages = $resolved['messages'] ?? [];
                if (!empty($flow_messages)) {
                    $concierge_session = $this->flosc_concierge_session_key($session_id);
                    $concierge_response = FLOSC_Concierge::handle($message, $concierge_session, ['messages' => $flow_messages]);
                    if (is_array($concierge_response)) {
                        $concierge_state = (string) ($concierge_response['flosc_concierge'] ?? '');
                        if (in_array($concierge_state, ['awaiting_password', 'retry'], true)) {
                            $concierge_message = '';
                            if (isset($concierge_response['message'])) {
                                $concierge_message = sanitize_textarea_field((string) $concierge_response['message']);
                            }

                            FLOSC_Chat_Logger::instance()->flosc_log_chat([
                                'flow_id'         => $flow_id ?: $flow_stem,
                                'phase'           => $phase,
                                'user_id'         => is_user_logged_in() ? get_current_user_id() : 0,
                                'session_id'      => $session_id,
                                'journey_id'      => $journey_id,
                                'user_message'    => $message,
                                'ai_response'     => $concierge_message,
                                'provider'        => 'concierge',
                                'chain_detail'    => ['concierge'],
                                'response_source' => 'concierge',
                                'response_time_ms'=> round((microtime(true) - $flosc_rag_start_time) * 1000),
                            ]);

                            // A canned gate response (password prompt / retry) — short-circuit.
                            return new WP_REST_Response($concierge_response);
                        }

                        $response_message = [
                            'content' => (string) ($concierge_response['content'] ?? ''),
                            'user_autoprompts' => $concierge_response['user_autoprompts'] ?? [],
                            'phase_change' => $concierge_response['phase_change'] ?? null,
                        ];
                        $flosc_response_source = 'concierge';
                        if (class_exists('FLOSC_Concierge')) {
                            $concierge_guidance = FLOSC_Concierge::active_guidance($concierge_session);
                        }
                    }
                    // Desk open for this guest? Carry the authorized brief into the prompt
                    // below — but ONLY in reply to the guest's own messages, never the
                    // auto-welcome or other "[SYSTEM:…]" generations.
                    $concierge_guidance = (strncmp($message, '[SYSTEM:', 8) !== 0)
                        ? FLOSC_Concierge::active_guidance($concierge_session)
                        : '';
                }
            }
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC RAG Chat: User {$user_context['user_id']} ({$user_context['access_level']}) - Message: {$message}");
        
        // Build system prompt for AI
        $system_prompt = $this->build_rag_system_prompt($user_context);
        
        // Get available lessons list (for AI to know what exists)
        $lessons_list = $this->rag_manager->get_available_lessons($user_context['access_level']);
        
        // Add lessons to system prompt
        $system_prompt .= "\n\n**AVAILABLE CONTENT:**\n{$lessons_list}";

        if ($trajectory_guidance !== '') { $system_prompt .= $trajectory_guidance; }

        // Concierge desk open for this guest → host the authorized reveal in voice.
        if ($concierge_guidance !== '') { $system_prompt .= $concierge_guidance; }

        // Get AI tools
        $tools = $this->rag_manager->get_ai_tools();
        
        // Call AI with tools (RAG enabled)
        $ai_response = $this->call_ai_with_rag($message, $system_prompt, $tools, $user_context);
        
        // CRITICAL: Validate response for access level compliance (v9.1.7)
        $validator = FLOSC_Access_Validator::instance();
        $validation_result = $validator->validate_response($ai_response, $user_context['access_level']);
        
        if (!$validation_result['valid']) {
            // Content leakage detected - use safe fallback
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC SECURITY ALERT: Content leakage prevented");
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC SECURITY: Original response: " . substr($ai_response, 0, 200));
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC SECURITY: Violations: " . wp_json_encode($validation_result['violations']));
            }
        }
        
        $safe_rag_response = $this->flosc_enforce_no_hedge_response(
            $validation_result['response'] ?? '',
            $message,
            $flow_id,
            $ivr_file,
            $user_context['phase'] ?? 'freeline',
            $user_context
        );

        FLOSC_Chat_Logger::instance()->flosc_log_chat([
            'flow_id'         => $flow_id ?: $flow_stem,
            'phase'           => $phase,
            'user_id'         => is_user_logged_in() ? get_current_user_id() : 0,
            'session_id'      => $session_id,
            'journey_id'      => $journey_id,
            'user_message'    => $message,
            'ai_response'     => $safe_rag_response,
            'provider'        => flosc_get_setting('ai_provider', 'rag'),
            'chain_detail'    => ['rag'],
            'response_source' => 'rag',
            'response_time_ms'=> round((microtime(true) - $flosc_rag_start_time) * 1000),
        ]);

        return new WP_REST_Response([
            'success' => true,
            'message' => $safe_rag_response,
            'user_context' => [
                'access_level' => $user_context['access_level'],
                'is_member' => $user_context['is_member'],
            ],
            'validated' => $validation_result['valid'], // For debugging
        ]);
    }

}
