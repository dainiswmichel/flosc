<?php
/**
 * FLOSC Chatpack — Unified AI Context Builder
 * 
 * v1.9.2: First-contact vs subsequent-message prompt strategy.
 * 
 * On the FIRST message of a session, sends a comprehensive "chatpack" containing:
 *   - FLOSC identity, WordPress environment, user identity, flow context,
 *     knowledge base, feedback/praise, conversation rules.
 * 
 * On SUBSEQUENT messages, sends a slim follow-up with only:
 *   - Session reference, message pair number, phase changes, new IVR guidance,
 *     updated user state (if changed).
 *
 * All sections are configurable by floscAdmins via the WordPress dashboard.
 * The chatpack also provides backend-authoritative session hashing and
 * input-response pair counting.
 *
 * @package FLOSC
 * @since 1.9.2
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Chatpack {

    /**
     * Generate or retrieve the permanent FLOSC installation hash.
     *
     * Format: flosc_{domain}_{MTS}_{7-char hex}
     * Example: flosc_host_flow_26_02m_21d_T14h30m12s_a3f7e21
     *
     * Generated once on first call, then stored permanently in wp_options.
     * The MTS (Michel Time Stamp) records when FLOSC first generated the hash.
     * The 7-char hex suffix provides global uniqueness across all FLOSC installs.
     *
     * @return string Permanent installation hash
     * @since 1.9.4
     */
    public static function generate_flosc_hash() {
        $stored = get_option('flosc_hash');
        if ($stored) {
            return $stored;
        }

        // Domain: strip scheme, replace dots with underscores
        $raw_url = get_bloginfo('url');
        $domain = preg_replace('#^https?://#', '', $raw_url);
        $domain = rtrim($domain, '/');
        $domain = str_replace('.', '_', $domain);

        // MTS: Michel Time Stamp with seconds
        $now = time();
           $mts = gmdate('y', $now) . '_'
               . gmdate('m', $now) . 'm_'
               . gmdate('d', $now) . 'd_T'
               . gmdate('H', $now) . 'h'
               . gmdate('i', $now) . 'm'
               . gmdate('s', $now) . 's';

        // 7-char hex suffix: seeded from domain + exact timestamp for global uniqueness
        $seed = $domain . ':' . $now . ':' . wp_generate_password(16, false);
        $suffix = substr(md5($seed), 0, 7);

        $flosc_hash = 'flosc_' . $domain . '_' . $mts . '_' . $suffix;

        update_option('flosc_hash', $flosc_hash, true);
        return $flosc_hash;
    }

    /**
     * Generate a session hash linked to the parent FLOSC installation hash.
     *
     * Format: {parent_fingerprint}_flosc_session_{MTS}_{5-char hex}
     * Example: a3f7e21_flosc_session_26_02m_21d_T16h05m33s_c2ae3
     *
     * The parent fingerprint (first 7 chars) is extracted from the flosc_hash suffix,
     * linking every session back to its parent install without repeating the domain.
     * The 5-char hex suffix disambiguates sessions starting in the same second.
     *
     * Generated once per session. The flosc_hash must be passed in.
     *
     * @param string $flosc_hash The parent FLOSC installation hash
     * @param int $user_id WordPress user ID (0 for visitors)
     * @param int|null $session_id FLOSC session ID
     * @return string Session hash
     * @since 1.9.4
     */
    public static function generate_session_hash($flosc_hash, $user_id, $session_id = null) {
        // Extract parent fingerprint: last 7 chars of flosc_hash
        $parent_fingerprint = substr($flosc_hash, -7);

        // MTS: Michel Time Stamp with seconds (session birth time)
        $now = time();
           $mts = gmdate('y', $now) . '_'
               . gmdate('m', $now) . 'm_'
               . gmdate('d', $now) . 'd_T'
               . gmdate('H', $now) . 'h'
               . gmdate('i', $now) . 'm'
               . gmdate('s', $now) . 's';

        // 5-char hex suffix: seeded from user + session + microtime for same-second disambiguation
        $seed = $user_id . ':' . ($session_id ?? 0) . ':' . microtime(true);
        $suffix = substr(md5($seed), 0, 5);

        return $parent_fingerprint . '_flosc_session_' . $mts . '_' . $suffix;
    }

    /**
     * Count message pairs from stored session data (backend-authoritative).
     * One pair = user message + assistant response.
     *
     * @param int $session_id FLOSC session ID
     * @param int $user_id WordPress user ID
     * @return int Number of completed pairs before this message
     */
    public static function count_message_pairs($session_id, $user_id, $flow_id = '', $session_id_raw = '') {
        if (!$session_id) {
            return 0;
        }

        if ($user_id) {
            $session_manager = new FLOSC_Session_Manager();
            $session = $session_manager->get_flosc_session($session_id, $user_id, $flow_id);
            if (!$session || empty($session['messages'])) {
                $session = $session_manager->get_flosc_session_by_id($session_id, $user_id);
            }

            if (!$session || empty($session['messages'])) {
                return 0;
            }
        } else {
            if (!class_exists('FLOSC_Chat_Logger')) {
                return 0;
            }
            $turns = FLOSC_Chat_Logger::instance()->flosc_get_session_turns((int) $session_id, 50, (string) $session_id_raw);
            $user_messages = array_filter($turns, function ($msg) {
                return ($msg['role'] ?? '') === 'user';
            });
            return count($user_messages);
        }

        // Count user messages (each has a paired assistant response)
        $user_messages = array_filter($session['messages'], function($msg) {
            return ($msg['role'] ?? '') === 'user';
        });

        return count($user_messages);
    }

    /**
     * Load conversation history from stored session (for dispatch path).
     * RAG handler has its own loader; this gives dispatch parity.
     *
     * @param int $session_id FLOSC session ID
     * @param int $user_id WordPress user ID
     * @param int $max_messages Maximum messages to return (default 10)
     * @return array Messages in [role, content] format for AI API
     */
    public static function load_conversation_history($session_id, $user_id, $max_messages = 10, $flow_id = '', $session_id_raw = '') {
        $session_id = absint($session_id);
        $max_messages = max(1, intval($max_messages));
        if ($session_id <= 0) {
            return [];
        }

        $map_messages = static function ($messages, $max_messages) {
            if (!is_array($messages) || $messages === []) {
                return [];
            }
            return array_slice(array_map(function ($msg) {
                $content = (string) ($msg['content'] ?? '');
                $src = is_array($msg['meta'] ?? null)
                    ? sanitize_key((string) ($msg['meta']['source'] ?? ''))
                    : '';
                if ($src === 'engagement_admin' && $content !== ''
                    && strpos($content, '[Admin engagement message]') === false) {
                    $content = '[Admin engagement message] ' . $content;
                }
                return [
                    'role'    => $msg['role'] ?? 'user',
                    'content' => $content,
                ];
            }, $messages), -$max_messages);
        };

        if ($user_id) {
            $session_manager = new FLOSC_Session_Manager();
            $session = $session_manager->get_flosc_session($session_id, $user_id, $flow_id);
            if (!$session || empty($session['messages'])) {
                $session = $session_manager->get_flosc_session_by_id($session_id, $user_id);
            }
            if ($session && !empty($session['messages'])) {
                return $map_messages($session['messages'], $max_messages);
            }
            return [];
        }

        if (!class_exists('FLOSC_Chat_Logger')) {
            return [];
        }
        $turns = FLOSC_Chat_Logger::instance()->flosc_get_session_turns(
            $session_id,
            (int) ceil($max_messages / 2),
            (string) $session_id_raw
        );
        return $map_messages($turns, $max_messages);
    }

    /**
     * Detect if this is the first message in a session.
     *
     * @param int $session_id FLOSC session ID
     * @param int $user_id WordPress user ID
     * @return bool True if no prior messages exist
     */
    public static function is_first_message($session_id, $user_id, $flow_id = '') {
        return self::count_message_pairs($session_id, $user_id, $flow_id) === 0;
    }

    /**
     * Build the FULL chatpack for first-contact messages.
     * This is the comprehensive system prompt sent on message #1.
     *
     * @param string $phase Current FLOSC phase
     * @param array $eval_context Backend-authoritative evaluation context
     * @param string $flow_id Current flow ID
     * @param string $flosc_hash Permanent installation hash (FLOSC-HASH)
     * @param string $session_hash Generated session hash (FLOSC-SESSION)
     * @param int $pair_number Current message pair number (1-based)
     * @param string|null $ivr_guidance IVR scripted response (if matched)
     * @return string Complete system prompt
     * @since 1.9.4: Added flosc_hash parameter, session_hash now second
     */
    public static function build_full_chatpack($phase, $eval_context, $flow_id, $flosc_hash, $session_hash, $pair_number, $ivr_guidance = null) {
        $sections = [];

        // ── HEADER ──────────────────────────────────────────
        $sections[] = self::build_header($flosc_hash, $session_hash, $pair_number, $flow_id);

        // ── 1. FLOSC IDENTITY ───────────────────────────────
        $sections[] = self::build_identity_section((string) $flow_id);

        // ── 2. WORDPRESS ENVIRONMENT ────────────────────────
        $sections[] = self::build_wordpress_section();

        // ── 3. USER IDENTITY ────────────────────────────────
        $sections[] = self::build_user_section($eval_context);

        // ── 4. FLOW CONTEXT ─────────────────────────────────
        $sections[] = self::build_flow_section($phase, $eval_context, $flow_id);

        // ── 5. KNOWLEDGE BASE ───────────────────────────────
        $sections[] = self::build_knowledge_section($eval_context);

        // ── 6. IVR GUIDANCE ─────────────────────────────────
        if ($ivr_guidance) {
            $sections[] = self::build_ivr_section($ivr_guidance, $flow_id, $eval_context);
        }

        // ── 7. CONVERSATION RULES ───────────────────────────
        $sections[] = self::build_rules_section($pair_number, $eval_context);

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Build the SLIM follow-up prompt for subsequent messages.
     * Only includes changed state + session reference.
     *
     * @param string $phase Current FLOSC phase
     * @param array $eval_context Backend-authoritative evaluation context
     * @param string $session_hash Same session hash as first message
     * @param int $pair_number Current message pair number
     * @param string|null $ivr_guidance IVR scripted response (if matched)
     * @param string|null $previous_phase Phase from previous message (for change detection)
     * @return string Slim follow-up system prompt
     */
    public static function build_followup_chatpack($phase, $eval_context, $session_hash, $pair_number, $ivr_guidance = null, $previous_phase = null) {
        $sections = [];

        // ── SESSION REFERENCE ───────────────────────────────
        $flosc_hash = self::generate_flosc_hash();

        // Fix 4: Anchor definitions — always present regardless of message number.
        // These live only in message 1's full chatpack. Conversation history carries
        // what was *said*, not what the system prompt *instructed*. By message 2 the
        // definitions are gone from authoritative context unless we anchor them here.
        $anchor_line      = "FLOSC = Freeline, Login, Offer, Sale, Content. (Fixed — never changes.)";

        $sections[] = "## FLOSC SESSION CONTINUE\n"
            . "FLOSC-HASH: {$flosc_hash}\n"
            . "FLOSC-SESSION: {$session_hash}\n"
            . "Message Pair: #{$pair_number}\n"
            . "Phase: {$phase}\n\n"
            . $anchor_line;

        // ── FLOW IDENTITY (re-anchored EVERY turn) ──────────
        // The first message sends the full identity, but follow-ups previously
        // dropped it and leaned on conversation history to carry the persona. That
        // holds for logged-in users (server-side memory) but NOT for visitors, whose
        // browser only replays message TEXT — so by message 2 the persona thinned to
        // a generic FLOSC voice, i.e. one flow bleeding into another. The identity
        // section is flow-scoped, so re-sending it on every turn keeps each chatbot
        // firmly inside its own flow. (Cheap insurance; flow isolation is the point.)
        $sections[] = self::build_identity_section((string) ($eval_context['flow_id'] ?? ''));
        $followup_flow = (string) ($eval_context['flow_id'] ?? '');
        $sections[] = self::build_user_section($eval_context);
        $sections[] = self::build_flow_section($phase, $eval_context, $followup_flow);
        $kb_section = self::build_knowledge_section($eval_context);
        if ($kb_section !== '') {
            $sections[] = $kb_section;
        }

        // ── SESSION CONTINUITY (mandatory) ──────────────────
        // The session ID owns the conversation; the display surface (full page vs
        // companion) and any page navigation are just views onto it. Follow-up turns
        // previously carried no continuity discipline, so the model re-greeted,
        // re-introduced itself, and re-asked language every turn. Anchor it here.
        $sections[] = "**SESSION CONTINUITY (mandatory):**\n"
            . "- This is a CONTINUING conversation (message pair #{$pair_number}), NOT a new one.\n"
            . "- You have ALREADY greeted and introduced yourself earlier in this session. Do NOT greet again, do NOT re-introduce yourself, and do NOT re-ask the user's language preference.\n"
            . "- If the user is writing in a given language, simply continue in that language without asking.\n"
            . "- Review the conversation history above before replying; NEVER repeat information you have already given — add new value or advance the thread.\n"
            . "- Continue naturally from where the conversation left off, regardless of which page or surface the user is on. The session is one continuous flow.";

        // ── PHASE CHANGE NOTICE ─────────────────────────────
        if ($previous_phase && $previous_phase !== $phase) {
            $sections[] = "**PHASE CHANGED:** {$previous_phase} → {$phase}\n"
                . "Adjust your behavior to the new phase rules. "
                . self::get_phase_one_liner($phase, $eval_context);
        }

        // ── UPDATED USER STATE ──────────────────────────────
        // Only include state that could have changed mid-conversation
        $state_updates = [];

        // Quiz just taken?
        if (!empty($eval_context['first_message_after_quiz'])) {
            $score = $eval_context['score'] ?? $eval_context['quiz_score'] ?? '?';
            $state_updates[] = "User just completed quiz (score: {$score}%)";
        }

        // Just logged in?
        if (!empty($eval_context['first_message_after_login'])) {
            $name = $eval_context['user_name'] ?? 'User';
            $state_updates[] = "User just logged in as {$name}";
        }

        // Just purchased?
        if (!empty($eval_context['first_message_after_purchase'])) {
            $state_updates[] = "User just purchased — now a member with full access";
        }

        if (!empty($state_updates)) {
            $sections[] = "**STATE UPDATES:**\n- " . implode("\n- ", $state_updates);
        }

        // ── IVR GUIDANCE ────────────────────────────────────
        if ($ivr_guidance) {
            $sections[] = self::build_ivr_section($ivr_guidance, null, $eval_context);
        }

        // ── PAGE CONTEXT (must ride EVERY turn) ─────────────
        // Page awareness + on-demand page body previously lived only in the rules
        // section, which build_full_chatpack() sends on message #1 only. Visitors chat
        // first and ask "what is this?" several turns in, so follow-ups lost the page
        // entirely. Re-anchoring it here keeps the bot aware of the current page on
        // every turn, for visitors, guests, and members alike.
        $page_context_section = self::build_page_context_section($eval_context);
        if ($page_context_section !== '') {
            $sections[] = $page_context_section;
        }

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Page-context block shared by first-contact and follow-up chatpacks.
     *
     * Two things ride here, and they are deliberately separate:
     *   - PAGE AWARENESS: lightweight title/URL/ID, present on EVERY turn so the bot
     *     can answer "what page am I on?" — cheap enough for idle browsing.
     *   - PAGE CONTENT: the full post body, injected ONLY on a turn where the user
     *     asked about the page (server sets browsing_page_content for that turn only).
     * COMPANION MODE POLICY is appended when the surface is the docked companion.
     *
     * @param array $eval_context
     * @return string Section text, or '' when there is no page context to add.
     */
    private static function build_page_context_section($eval_context) {
        $surface          = sanitize_key((string) ($eval_context['browsing_surface'] ?? ''));
        $browsing_url     = trim((string) ($eval_context['browsing_page_url'] ?? ''));
        $browsing_title   = trim((string) ($eval_context['browsing_page_title'] ?? ''));
        $page_content     = trim((string) ($eval_context['browsing_page_content'] ?? ''));
        $browsing_post_id = absint($eval_context['browsing_page_post_id'] ?? 0);

        $has_page = ($browsing_url !== '' || $browsing_title !== '' || $browsing_post_id > 0);
        // Companion turns always carry the on-site policy, even with no resolved page.
        if (!$has_page && $page_content === '' && $surface !== 'companion') {
            return '';
        }

        $section = '';

        if ($page_content !== '') {
            $section .= "**PAGE CONTENT (source of truth for this turn):**\n";
            $section .= "- The user is asking about a specific page/post. Use ONLY the content below for page-specific facts.\n";
            $section .= "- Reply in 2-4 short, conversational sentences. Do NOT paste or summarize the whole page.\n";
            $section .= "- You may quote one short phrase if it helps. Stay human and guided.\n";
            if ($browsing_title !== '') {
                $section .= "- Page title: {$browsing_title}\n";
            }
            $section .= "\n--- PAGE BODY (background context, not for display) ---\n";
            $section .= $page_content . "\n";
            $section .= "--- END PAGE BODY ---\n";
        } elseif ($has_page) {
            $section .= "**PAGE AWARENESS (mandatory):**\n";
            $section .= "- The chat is open on the page the visitor is browsing — use title/URL/ID below for \"what page am I on?\" style questions\n";
            if ($browsing_post_id > 0) {
                $section .= "- Current WordPress post/page ID: {$browsing_post_id}\n";
            }
            if ($browsing_title !== '') {
                $section .= "- Current page title in context: {$browsing_title}\n";
            }
            if ($browsing_url !== '') {
                $section .= "- Current page URL in context: {$browsing_url}\n";
            }
            $section .= "- For page-specific facts beyond title/URL, rely on PAGE BODY when it appears in this prompt\n";
            $section .= "- After confirming page awareness, continue with a concrete help question relevant to that page\n";
        }

        if ($surface === 'companion') {
            $section .= "\n**COMPANION MODE POLICY (strict):**\n";
            $section .= "- The visitor opened chat from the page they are browsing right now\n";
            $section .= "- Page title/URL are always in context; full PAGE BODY appears only when injected for this turn\n";
            $section .= "- When PAGE BODY is present, use it as the authoritative source and answer in 2-3 short sentences\n";
            $section .= "- Treat this as on-site guidance only for the current page/site context\n";
            $section .= "- Do NOT answer off-topic questions; politely refuse and redirect back to current on-site context\n";
            $section .= "- Do NOT provide external links, external tools, or external resource recommendations\n";
            $section .= "- Keep navigation guidance inside this site only\n";
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (!is_string($site_host) || $site_host === '') {
                $site_host = 'this site';
            }
            $section .= "- Use the exact off-topic reply ONLY when the user is treating this as a general AI resource (for example: \"what is the capital of France\") and the question is not about {$site_host} content\n";
            $section .= "- Exact off-topic reply: \"That's outside what I cover here on {$site_host}. Ask me something about this site or what I can help you with, and I'll stay on that.\"\n";
        }

        return $section;
    }

    // ─────────────────────────────────────────────────────────
    // SECTION BUILDERS — each builds one piece of the chatpack
    // ─────────────────────────────────────────────────────────

    /**
     * Chatpack header with installation + session tracking metadata.
     * @since 1.9.4: Added flosc_hash (installation ID)
     */
    private static function build_header($flosc_hash, $session_hash, $pair_number, $flow_id) {
        $flow_name = $flow_id ?: 'default';

        return "## FLOSC CHATPACK v1\n"
            . "FLOSC-HASH: {$flosc_hash}\n"
            . "FLOSC-SESSION: {$session_hash}\n"
            . "Message Pair: #{$pair_number}\n"
            . "Flow: {$flow_name}\n"
            . "Protocol: First-contact — full context included. Subsequent messages will be slimmer.";
    }

    /**
     * Section 1: FLOSC Identity — what FLOSC is, product info, AI persona.
     * Reads from floscAdmin-configurable settings.
     */
    private static function build_identity_section($flow_id = '') {
        $flow_id = ($flow_id !== null && $flow_id !== '') ? $flow_id : null;
        // Fix 12: Library attach (one personality) or flow bag / legacy keys.
        $res = function_exists( 'flosc_personality_library_resolve_field' ) ? 'flosc_personality_library_resolve_field' : null;
        $ai_name    = function_exists( 'flosc_personality_name' )
            ? flosc_personality_name( $flow_id )
            : ( $res
                ? call_user_func( $res, 'ai_personality_name', 'AI Assistant', $flow_id )
                : flosc_get_setting( 'ai_personality_name', 'AI Assistant', $flow_id ) );
        $ai_role    = $res
            ? call_user_func( $res, 'ai_personality_role', flosc_get_setting( 'ai_role', 'friendly learning assistant', $flow_id ), $flow_id )
            : flosc_get_setting( 'ai_personality_role', flosc_get_setting( 'ai_role', 'friendly learning assistant', $flow_id ), $flow_id );
        $ai_traits  = $res
            ? call_user_func( $res, 'ai_personality_traits', flosc_get_setting( 'ai_traits', 'helpful, encouraging, knowledgeable', $flow_id ), $flow_id )
            : flosc_get_setting( 'ai_personality_traits', flosc_get_setting( 'ai_traits', 'helpful, encouraging, knowledgeable', $flow_id ), $flow_id );
        $ai_mission = $res ? call_user_func( $res, 'ai_mission', '', $flow_id ) : flosc_get_setting( 'ai_mission', '', $flow_id );
        $ai_boundaries = $res ? call_user_func( $res, 'ai_boundaries', '', $flow_id ) : flosc_get_setting( 'ai_boundaries', '', $flow_id );
        $ai_topic_scope    = $res ? call_user_func( $res, 'ai_topic_scope', '', $flow_id ) : flosc_get_setting( 'ai_topic_scope', '', $flow_id );
        $ai_off_topic      = $res ? call_user_func( $res, 'ai_off_topic_message', '', $flow_id ) : flosc_get_setting( 'ai_off_topic_message', '', $flow_id );
        $ai_referral_links = $res ? call_user_func( $res, 'ai_off_topic_links', '', $flow_id ) : flosc_get_setting( 'ai_off_topic_links', '', $flow_id );
        $ai_base_prompt    = $res ? call_user_func( $res, 'ai_base_prompt', '', $flow_id ) : flosc_get_setting( 'ai_base_prompt', '', $flow_id );
        $site_url = function_exists('get_bloginfo') ? get_bloginfo('url') : '';
        $compiled_profile = function_exists( 'flosc_personality_compiled_profile' )
            ? flosc_personality_compiled_profile( $flow_id )
            : trim( (string) $ai_base_prompt );

        $section = "## 1. IDENTITY\n\n";

        if ( $compiled_profile !== '' ) {
            $section .= "This chat is on a FLOSC flow";
            if ( $site_url ) {
                $section .= " at {$site_url}";
            }
            $section .= ".\n";
            $section .= "FLOSC = Freeline, Login, Offer, Sale, Content (the software). ";
            $section .= "Do not invent another expansion.\n";
            $section .= "\n## Personality\n";
            $section .= "The following profile is who you are. Speak as this person. Do not describe how you were made.\n\n";
            $section .= $compiled_profile . "\n";
        } else {
            $section .= "You are {$ai_name}, the AI facilitator";
            if ($site_url) {
                $section .= " on {$site_url}";
            }
            $section .= ".\n\n";

            $section .= "This is a FLOSC installation. FLOSC = Freeline, Login, Offer, Sale, Content — "
                . "a 5-phase journey from first visit to long-term membership.";
            $section .= "\n";
            if ($ai_mission) {
                $section .= $ai_mission . "\n";
            }

            $section .= "\n**YOUR ENERGY IS ENCOURAGEMENT.** This is the through-line of every interaction:\n";
            $section .= "- A visitor showing up → spark curiosity, make them want to find out where they stand\n";
            $section .= "- A visitor taking the quiz → they took action, honor that\n";
            $section .= "- Seeing quiz results → celebrate what they discovered about themselves\n";
            $section .= "- Considering the offer → \"you're this close — here's the path\"\n";
            $section .= "- Making the purchase → celebrate the decision to invest in themselves\n";
            $section .= "- Every lesson completed → acknowledge the win, no matter how small\n";
            $section .= "- Continued learning → track progress, make it visible, keep momentum alive\n\n";
            $section .= "Your job is to move each user forward — one phase, one step, one win at a time.\n\n";
        }

        $section .= "--- EXACT DEFINITIONS (do not paraphrase or invent) ---\n\n";

        // FLOSC definition — spelled out unambiguously
        $section .= "**FLOSC** = Freeline, Login, Offer, Sale, Content. Those are the 5 phases. "
            . "FLOSC is a white-label WordPress plugin framework. "
            . "That is ALL it stands for. Do not expand it any other way.\n\n";

        $public_title = function_exists( 'flosc_flow_public_title' )
            ? flosc_flow_public_title( $flow_id )
            : '';
        $public_tagline = function_exists( 'flosc_flow_public_tagline' )
            ? flosc_flow_public_tagline( $flow_id )
            : '';
        if ( $public_title !== '' ) {
            $section .= "**Public offering title:** {$public_title}\n";
        } else {
            $section .= "**Public offering title:** none. This flow is not a course catalog and has no product or lesson brand. Do not invent one.\n";
        }
        if ( $public_tagline !== '' ) {
            $section .= "**Tagline:** {$public_tagline}\n";
        }
        $section .= "\n";

        if ( $compiled_profile === '' ) {
            $section .= "\n**Your Persona:**\n";
            $section .= "- Name: {$ai_name}\n";
            $section .= "- Role: {$ai_role}\n";
            $section .= "- Traits: {$ai_traits}\n";
            if ($ai_mission) {
                $section .= "- Mission: {$ai_mission}\n";
            }
            if ($ai_boundaries) {
                $section .= "- Boundaries: {$ai_boundaries}\n";
            }
        } elseif ( $ai_boundaries ) {
            $section .= "\n**Boundaries (this flow):** " . $ai_boundaries . "\n";
        }

        // Hard rules (non-configurable safety rails)
        // Written as both positive (DO) and negative (NEVER) for maximum compliance.
        $section .= "\n**ABSOLUTE RULES — violation of any rule is a critical failure:**\n\n";

        $section .= "1. NAMES & ACRONYMS: Only expand or define names/acronyms when this prompt (or verified site context) gives the expansion. ";
        $section .= "FLOSC (the software framework) = Freeline, Login, Offer, Sale, Content — only when relevant to the software, not as this flow's forced topic. ";
        $section .= "If you do not have a verified expansion for a name, say so briefly and offer a useful next step — NEVER invent an expansion.\n\n";

        $section .= "2. NO FABRICATION: Never invent facts not present in this prompt or verified tools/context:\n";
        $section .= "   - Lesson/course inventories, lesson numbers, or curricula (unless this flow actually provides them)\n";
        $section .= "   - Statistics, social proof, or unverified claims\n";
        $section .= "   - Features, prices, offers, or contact details not in context\n";
        $section .= "   If you lack a detail, stay in character, answer what you can from verified context, and offer a concrete next step (e.g. how to reach the host, what to ask next). Do NOT default to timid hedging phrases.\n";
        $section .= "   NEVER open with stock lines like \"I'm still learning, and I could be wrong, but...\" or \"I'm still being trained...\" — that is not this assistant's voice.\n";
        $section .= "   Keep tone human, confident where context allows, clear when declining to invent.\n\n";

        $section .= "3. STAY IN YOUR LANE: Follow this flow's identity and IVR/mission. You are not a generic lessons bot unless this flow is configured for lessons. "
            . "You do NOT administer quizzes as the quiz engine; do not simulate quiz administration.\n\n";

        $section .= "4. CONTENT ACCESS: Never share paid or member-only materials with visitors who are not entitled to them.\n\n";

        $section .= "5. HONESTY OVER INVENTION: Prefer verified context over speculation. "
            . "A scoped, in-character answer is better than a plausible fabrication.\n";

        // Fix 12: Topic scope + referral links (admin-configured, were collected but never injected)
        if ($ai_topic_scope) {
            $section .= "\n**Topic Scope:** " . $ai_topic_scope . "\n";
        }
        if ($ai_off_topic) {
            $section .= "**Off-Topic Handling:** " . $ai_off_topic . "\n";
        }
        if ($ai_referral_links) {
            $section .= "**Recommended External Resources:** " . $ai_referral_links . "\n";
        }

        if ( $compiled_profile === '' && $ai_base_prompt ) {
            $section .= "\n**FloscAdmin Advanced Override:**\n" . $ai_base_prompt . "\n";
        }

        return $section;
    }

    /**
     * Section 2: WordPress Environment — site info the AI should know.
     * All from WordPress core functions — not configurable (it's factual).
     */
    private static function build_wordpress_section() {
        // These are WordPress functions — they'll be available at runtime
        // but we need to guard against CLI/test environments
        if (!function_exists('get_bloginfo')) {
            return '';
        }

        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        $site_description = get_bloginfo('description');
        $timezone = wp_timezone_string();
        $locale = get_locale();
        $flosc_version = defined('FLOSC_VERSION') ? FLOSC_VERSION : 'unknown';

        $section = "## 2. WORDPRESS ENVIRONMENT\n\n";
        $section .= "- Site Name: {$site_name}\n";
        $section .= "- Site URL: {$site_url}\n";
        if ($site_description) {
            $section .= "- Site Description: {$site_description}\n";
        }
        $section .= "- Timezone: {$timezone}\n";
        $section .= "- Locale: {$locale}\n";
        $section .= "- FLOSC Version: {$flosc_version}\n";

        return $section;
    }

    /**
     * Section 3: User Identity — who is talking to the AI.
     * Backend-authoritative, not spoofable.
     */
    private static function build_user_section($eval_context) {
        $section = "## 3. USER IDENTITY\n\n";

        $access_level = $eval_context['access_level'] ?? 'visitor';
        $user_name = $eval_context['user_name'] ?? 'Anonymous';
        $is_admin = $eval_context['is_admin'] ?? false;

        $section .= "- Access Level: **{$access_level}**" . ($is_admin ? ' (floscAdmin)' : '') . "\n";
        $section .= "- Name: {$user_name}\n";

        // Admin-specific details
        if ($is_admin && !empty($eval_context['user_id']) && is_user_logged_in()) {
            $user_id = $eval_context['user_id'];
            $admin_user = get_userdata($user_id);
            if ($admin_user) {
                $section .= "- WP User ID: {$user_id}\n";
                $section .= "- WP Roles: " . implode(', ', $admin_user->roles) . "\n";
                $section .= "- Email: {$admin_user->user_email}\n";
            }
            $section .= "\n**Admin Note:** Admin users are not funnel participants. They have full access to all phases, lessons, and configuration. Do not treat them as visitors or guide them through the funnel.\n";
        }

        // Quiz data
        $quiz_taken = $eval_context['quiz_taken'] ?? false;
        if ($quiz_taken) {
            $score = $eval_context['score'] ?? $eval_context['quiz_score'] ?? '?';
            $section .= "\n**Quiz Results:**\n";
            $section .= "- Score: {$score}%\n";

            // v5.0.2: Try bridge data first (logged-in users with DB profile),
            // then fall back to frontend context (visitors who took quiz client-side).
            $got_bridge_data = false;
            if (!empty($eval_context['user_id']) && is_user_logged_in()) {
                $user_id = $eval_context['user_id'];
                $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
                $bridge_data = $bridge_mgr->get_flosc_bridge_data($user_id);

                if ($bridge_data) {
                    $correct = $bridge_data['correct_items'] ?? [];
                    $incorrect = $bridge_data['incorrect_items'] ?? [];
                    if (!empty($correct)) {
                        $section .= "- Correct (" . count($correct) . "): " . implode(', ', array_slice($correct, 0, 10)) . "\n";
                        $got_bridge_data = true;
                    }
                    if (!empty($incorrect)) {
                        $section .= "- Missed (" . count($incorrect) . "): " . implode(', ', array_slice($incorrect, 0, 10)) . "\n";
                        $got_bridge_data = true;
                    }

                    $weakest = $bridge_mgr->get_flosc_weakest_category($user_id);
                    if ($weakest) {
                        $section .= "- Weakest Area: {$weakest}\n";
                    }
                }
            }

            // Fallback: use quiz data from frontend context (visitors/guests without bridge data)
            if (!$got_bridge_data) {
                $fc_correct = $eval_context['correct_items'] ?? $eval_context['correctItems'] ?? [];
                $fc_incorrect = $eval_context['incorrect_items'] ?? $eval_context['incorrectItems'] ?? [];
                if (!empty($fc_correct) && is_array($fc_correct)) {
                    $section .= "- Correct (" . count($fc_correct) . "): " . implode(', ', array_slice($fc_correct, 0, 10)) . "\n";
                }
                if (!empty($fc_incorrect) && is_array($fc_incorrect)) {
                    $section .= "- Missed (" . count($fc_incorrect) . "): " . implode(', ', array_slice($fc_incorrect, 0, 10)) . "\n";
                }
            }

            // v8.0.11: IPA pronunciation quiz results from frontend context
            $ipa_score = $eval_context['ipa_quiz_score'] ?? 0;
            $ipa_tier = $eval_context['ipa_quiz_tier'] ?? '';
            $ipa_weakest = $eval_context['ipa_weakest_sounds'] ?? [];
            if ($ipa_score > 0) {
                $section .= "\n**IPA Pronunciation Quiz:**\n";
                $section .= "- Score: {$ipa_score}%\n";
                if ($ipa_tier) {
                    $section .= "- Tier: {$ipa_tier}\n";
                }
                if (!empty($ipa_weakest) && is_array($ipa_weakest)) {
                    $section .= "- Weakest Sounds: " . implode(', ', array_slice($ipa_weakest, 0, 5)) . "\n";
                }
                $section .= "- When the user asks about their quiz results or pronunciation, reference these specific sounds and scores.\n";
            }
        }

        // Fix 11b: Server-generated lesson recommendations (only when quiz taken)
        if ($quiz_taken) {
            $recs = self::build_personalized_recommendations($eval_context);
            if ($recs) {
                $section .= $recs;
            }
        }

        // Progress & access data
        if (!empty($eval_context['user_id']) && is_user_logged_in()) {
            $user_id = $eval_context['user_id'];
            $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
            $has_profile = $bridge_mgr->flosc_has_profile($user_id);
            $free_delivered = (bool) get_user_meta($user_id, '_flosc_free_content_item_delivered', true);
            // Per-flow paid entitlement for AI context (not global _flosc_member_access).
            $flow_for_purchase = (string) ($eval_context['flow_id'] ?? '');
            $purchased = !empty($eval_context['is_member']) || !empty($eval_context['purchased']);
            if (!$purchased && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'sale')) {
                $sale = flosc()->sale();
                if ($sale && method_exists($sale, 'access')) {
                    $purchased = $sale->access()->is_member($user_id, $flow_for_purchase);
                }
            }

            $section .= "\n**Progress:**\n";
            $section .= "- Has Profile: " . ($has_profile ? 'Yes' : 'No') . "\n";
            $section .= "- Free Lesson Delivered: " . ($free_delivered ? 'Yes' : 'No') . "\n";
            $section .= "- Member (this flow): " . ($purchased ? 'Yes' : 'No') . "\n";
        }

        return $section;
    }

    /**
     * Section 4: Flow Context — current phase and phase-specific instructions.
     */
    private static function build_flow_section($phase, $eval_context, $flow_id = null) {
        // build_followup_chatpack() hands us $eval_context['flow_id'] cast to a
        // string, which is '' when the turn carries no flow. Settings lookups
        // treat '' as "a flow named empty string" and skip the flow bag entirely,
        // so normalize it back to null and keep the get_current_flow() fallback.
        if ($flow_id === '') {
            $flow_id = null;
        }

        $section = "## 4. FLOW CONTEXT\n\n";

        $is_admin = $eval_context['is_admin'] ?? false;

        if ($is_admin) {
            // Admin uses backend-determined phase
            if (function_exists('flosc')) {
                $backend_phase = flosc()->determine_flosc_phase();
                $section .= "- Phase: admin (backend: {$backend_phase}, frontend sent: {$phase})\n";
            } else {
                $section .= "- Phase: admin\n";
            }
        } else {
            $section .= "- Phase: **{$phase}**\n";
        }

        // Phase descriptions (the 5 FLOSC phases)
        $section .= "\n**FLOSC Phases:**\n";
        $section .= "1. **Freeline** — Visitor (not logged in). Goal: get them to take the quiz.\n";
        $section .= "2. **Login** — Guest (logged in, quiz done). Goal: show score, deliver free lesson.\n";
        $section .= "3. **Offer** — Guest (free lesson viewed). Goal: present upgrade offer.\n";
        $section .= "4. **Sale** — Member (purchased). Full access to all content.\n";
        $section .= "5. **Content** — Ongoing member engagement, support, encouragement.\n";

        $phase_outcomes = self::get_phase_outcomes($phase, $eval_context, $flow_id);
        if (!empty($phase_outcomes)) {
            $section .= "\n**Phase Outcomes:**\n";
            foreach ($phase_outcomes as $outcome) {
                $section .= "- {$outcome}\n";
            }
            $section .= "Choose one or more outcomes that fit the user's current intent and readiness. Do not force outcomes that do not match the conversation.\n";
        }

        // Phase-specific instructions
        $section .= "\n" . self::get_phase_instructions($phase, $eval_context, $flow_id);

        // Access-level instructions (floscAdmin-configurable via ai_prompt_{phase}).
        // Pass $flow_id: without it flosc_get_setting() falls back to
        // get_current_flow(), so a turn on one flow could be handed another
        // flow's phase instructions.
        $phase_prompt = flosc_get_setting("ai_prompt_{$phase}", '', $flow_id);
        if ($phase_prompt) {
            $section .= "\n**FloscAdmin Phase Instructions:**\n" . $phase_prompt . "\n";
        }

        return $section;
    }

    /**
     * Section 5: Knowledge Base — feedback, praise, KB files.
     * Feedback and praise are floscAdmin-managed training data.
     */
    private static function build_knowledge_section($eval_context) {
        $section = '';

        // Feedback (floscAdmin-flagged bad responses)
        $feedback_items = flosc_get_setting('ai_feedback', []);
        if (!empty($feedback_items) && is_array($feedback_items)) {
            $section .= "## 5a. ADMIN FEEDBACK — Avoid These Mistakes\n\n";
            $section .= "The site administrator has flagged these as BAD responses. Learn from them:\n\n";
            foreach ($feedback_items as $i => $feedback_item) {
                $num = $i + 1;
                $section .= "### Feedback #{$num}\n";
                $section .= "**User said:** " . ($feedback_item['user_message'] ?? '') . "\n";
                $section .= "**Bad response:** " . ($feedback_item['bad_response'] ?? '') . "\n";
                $section .= "**Issue:** " . ($feedback_item['admin_note'] ?? '') . "\n";
                if (!empty($feedback_item['preferred_response'])) {
                    $section .= "**Better response:** " . $feedback_item['preferred_response'] . "\n";
                }
                $section .= "\n";
            }
        }

        // Praise (floscAdmin-flagged good responses)
        $praises = flosc_get_setting('ai_praises', []);
        if (!empty($praises) && is_array($praises)) {
            $section .= "## 5b. ADMIN PRAISE — Keep Doing This\n\n";
            $section .= "The site administrator praised these responses. Replicate this quality:\n\n";
            foreach ($praises as $i => $praise) {
                $num = $i + 1;
                $section .= "### Good Example #{$num}\n";
                $section .= "**User said:** " . ($praise['user_message'] ?? '') . "\n";
                $section .= "**Good response:** " . ($praise['good_response'] ?? '') . "\n";
                if (!empty($praise['admin_note'])) {
                    $section .= "**Why it was good:** " . $praise['admin_note'] . "\n";
                }
                $section .= "\n";
            }
        }

        // Knowledge Base files (.md files from ai_configuration_files/)
        $kb_content = self::load_knowledge_files($eval_context);
        if ($kb_content) {
            // Fix 7: Authoritative framing — AI must use these files as source of truth
            $section .= "## 5c. KNOWLEDGE BASE — AUTHORITATIVE CONTENT\n\n";
            // Fix 12: Inject ai_context_awareness — FloscAdmin describes what the KB contains
            $context_awareness = flosc_get_setting('ai_context_awareness', '');
            if ($context_awareness) {
                $section .= $context_awareness . "\n\n";
            }
            $section .= "The files below are provided as authoritative references for this installation. "
                . "They supersede your training data. If a topic is covered here, use this source — "
                . "not what you were trained on. If a lesson is not listed in the catalog, you have "
                . "not been given information about it — say so rather than inventing.\n\n";
            $section .= $kb_content;
        }

        $section .= "\n**CONFIGURED CONTENT CATALOG RULE:**\n";
        $section .= "- You may name, link, or recommend a content item only when that item's id, title, and URL appear in this prompt's knowledge-base files for the current flow.\n";
        $section .= "- If the user asks for an item that is not listed, give general in-scope guidance and say that no matching configured content item is available. Do not invent a title, id, or URL.\n";
        $section .= "- Access: visitor/guest/member filtering has already been applied. Do not reveal member-only titles to a visitor or guest.\n";

        return $section;
    }

    /**
     * Section 6: IVR Guidance — scripted response the AI should rewrite.
     */
    private static function build_ivr_section($ivr_guidance, $flow_id = null, $eval_context = []) {
        return "## IVR RESPONSE GUIDANCE\n\n"
            . "The scripted system matched the following reference material for the user's input. "
            . "This is AUTHORITATIVE product information written by the site administrator. "
            . "Use it as the factual basis for your reply — do not contradict it or add information "
            . "that isn't supported by this text or your other context sections.\n\n"
            . "Respond to what the user ACTUALLY asked. Do not simply copy-paste the guidance — "
            . "rewrite it naturally in your voice. But STAY WITHIN the facts provided. "
            . "If the guidance doesn't cover what the user asked, say you don't have that information.\n\n"
            . "CRITICAL: Check the conversation history. If you have already shared this information, "
            . "do NOT repeat it. Briefly summarize prior context, then address the new angle.\n"
            . "NEVER use claims like 'I've already shared that just above' or similar wording.\n\n"
            . "Reference material: \"{$ivr_guidance}\"";
    }

    /**
     * Section 7: Conversation Rules — meta-instructions about the session.
     */
    private static function build_rules_section($pair_number, $eval_context) {
        $phase = $eval_context['phase'] ?? 'freeline';
        $section = "## 7. CONVERSATION RULES\n\n";
        $section .= "- This is message pair **#{$pair_number}** in this session\n";

        if ($pair_number === 1) {
            $section .= "- This is the **opening message** — greet the user appropriately\n";
        }

        // v2.0.7: Conversation-awareness — prevent AI from repeating itself
        $section .= "\n**CONVERSATION AWARENESS (mandatory):**\n";
        $section .= "- ALWAYS review the conversation history before responding\n";
        $section .= "- NEVER repeat information you have already told the user in this conversation\n";
        $section .= "- If the user asks about something you already covered, give a short factual recap and then address the NEW aspect of their question\n";
        $section .= "- Each response must add NEW value — if you have nothing new to add, say so honestly\n";
        $section .= "- Vary your language and structure across responses — do not use the same phrasing twice\n";
        $section .= "- NEVER claim prior output with phrases like 'already shared above', 'as noted above', or similar if that exact text is not verifiably present\n";

        // Page awareness + on-demand page body live in build_page_context_section() so the
        // follow-up chatpack reuses the identical block on EVERY turn (see build_followup_chatpack),
        // not only on message #1. $page_content is still read here for the grounding branch below.
        $page_content = trim((string)($eval_context['browsing_page_content'] ?? ''));
        $page_context = self::build_page_context_section($eval_context);
        if ($page_context !== '') {
            $section .= "\n" . $page_context;
        }

        // v8.0.10: Anti-hallucination anchor — reinforced at end of prompt for recency bias
        $section .= "\n**FACTUAL GROUNDING (final reminder):**\n";
        if ($page_content !== '') {
            $section .= "- For questions about the current page, the PAGE BODY section above is authoritative\n";
            $section .= "- For broader product/platform questions, use this system prompt and the knowledge base files above\n";
        } else {
            $section .= "- Your ONLY source of truth about this product is this system prompt and the knowledge base files above\n";
        }
        $section .= "- Do NOT use your general training knowledge to fill in product details, features, prices, or course content\n";
        $section .= "- If this prompt doesn't tell you something, you don't know it. Say so.\n";
        $section .= "- NEVER invent acronym expansions. FLOSC = Freeline, Login, Offer, Sale, Content. That's it.\n";
        // COMPANION MODE POLICY now lives in build_page_context_section() (shared with follow-ups).
        if ($phase === 'freeline') {
            $section .= "- You are NOT the quiz. The quiz is a separate audio-recording widget. Do not simulate it.\n";
        }

        // Response format preferences (floscAdmin-configurable)
        $response_style = flosc_get_setting('ai_response_style', '');
        if ($response_style) {
            $section .= "- Response style: {$response_style}\n";
        }

        $max_length = flosc_get_setting('ai_max_response_length', '');
        if ($max_length) {
            $section .= "- Maximum response length: {$max_length}\n";
        }

        // Topic boundaries (floscAdmin-configurable)
        $topic_scope = flosc_get_setting('ai_topic_scope', '');
        $off_topic_message = flosc_get_setting('ai_off_topic_message', '');
        $off_topic_links = flosc_get_setting('ai_off_topic_links', '');

        if ($topic_scope || $off_topic_message || $off_topic_links) {
            $section .= "\n**Topic Boundaries:**\n";
            if ($topic_scope) {
                $section .= $topic_scope . "\n";
            }
            if ($off_topic_message) {
                $section .= "When users ask off-topic questions: {$off_topic_message}\n";
            }
            if ($off_topic_links) {
                $section .= "Recommended external tools: {$off_topic_links}\n";
            }
        }

        return $section;
    }

    // ─────────────────────────────────────────────────────────
    // HELPER METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Fix 11b: Build server-generated personalized lesson recommendations.
     * PHP makes the connection between quiz weak sounds and specific lessons.
     * The AI receives a generated recommendation — no inference required.
     *
     * @param array $eval_context
     * @return string Recommendation block, or empty string if not applicable.
     */
    private static function build_personalized_recommendations($eval_context) {
        $weakest_sounds = $eval_context['ipa_weakest_sounds'] ?? [];
        if (empty($weakest_sounds)) return '';

        $catalog = self::load_knowledge_files($eval_context);
        if ($catalog === '') {
            return '';
        }

        $recommendations = [];
        foreach (array_slice($weakest_sounds, 0, 5) as $sound) {
            $sound_escaped = preg_quote($sound, '/');
            // Match table rows: | LessonNum | Title | Sound | ...
            if (preg_match('/\|\s*(\d+)\s*\|\s*([^|]+?)\s*\|\s*' . $sound_escaped . '\s*\|/i', $catalog, $m)) {
                $recommendations[] = "Lesson {$m[1]}: " . trim($m[2]) . " (covers {$sound})";
            }
        }

        if (empty($recommendations)) return '';

        $section  = "\n**Personalized Lesson Recommendations (server-generated from quiz data):**\n";
        $section .= "Based on this user's quiz results, their priority lessons are:\n";
        foreach ($recommendations as $rec) {
            $section .= "- {$rec}\n";
        }
        $section .= "Reference these lessons by name and number. Do not invent additional recommendations.\n";
        return $section;
    }

    /**
     * Get phase-specific behavioral instructions.
     */
        private static function get_phase_instructions($phase, $eval_context, $flow_id = null) {
        $access_level = $eval_context['access_level'] ?? 'visitor';

        switch ($phase) {
            case 'freeline':
                $quiz_in_progress = !empty($eval_context['quiz_in_progress']);
                $phase_outcomes = self::get_phase_outcomes('freeline', $eval_context, $flow_id);
                                $quiz_outcome_enabled = self::outcomes_include_quiz($phase_outcomes);
                $freeline_intro = $quiz_in_progress
                    ? "This visitor is CURRENTLY TAKING the pronunciation quiz (in progress right now).\n"
                      . "- The quiz is managed by a SEPARATE recording/analysis system — NOT by you.\n"
                      . "- NEVER fabricate quiz questions, words, phrases, or pronunciation exercises.\n"
                      . "- NEVER pretend you are administering the quiz or suggest words to say.\n"
                      . "- If asked about status: tell them they're a Visitor taking the quiz, and encourage them to finish it.\n"
                      . "- Keep answers brief — the user should get back to their quiz.\n"
                                        : ($quiz_outcome_enabled
                                                ? "This visitor has NOT taken the quiz yet. Your goal: spark curiosity and nudge toward the quiz.\n"
                                                    . "- Guide the visitor toward the quiz\n"
                                                    . "- If they ask a question, give an honest answer, then mention the quiz\n"
                                                : "This visitor is in Freeline (visitor context). Your goal: understand what they need and encourage one or more configured phase outcomes.\n"
                                                    . "- Lead with inquiry-first responses\n"
                                                    . "- Give honest, direct answers before nudging to an outcome\n");
                                $visitor_redirect = $quiz_outcome_enabled
                                        ? "a pronunciation quiz that tests your Standard American English pronunciation"
                                        : "the next best step in this flow";
                return "**CURRENT PHASE INSTRUCTIONS (Freeline):**\n"
                    . $freeline_intro
                    . "- DON'T share lesson content, titles, or pricing\n"
                    . "\n**VISITOR RESOURCE POLICY:**\n"
                    . "Visitors are NOT logged-in members. They have LIMITED conversation access.\n"
                    . "- For the FIRST 1-2 off-topic or general questions: answer briefly and naturally, then steer toward the quiz.\n"
                    . "- After 2+ off-topic questions (or if the visitor is clearly using you as a general AI chatbot): \n"
                    . "  Politely but firmly redirect. Use language like: 'Because you're currently only logged in as a visitor, "
                    . "I'm not really authorized to engage on in-depth or off-topic subjects. I am happy to guide you through "
                    . "{$visitor_redirect}. When you're a member, you "
                    . "have more access to system resources, but as a guest, the site is actually paying to host your conversation "
                    . "here. Even though it pains me to do so, I do sometimes need to wrap up conversations when a user asks "
                    . "off-topic questions. Would you like to take the next step now?'\n"
                    . "- Adapt this message to fit your personality and the flow's product context.\n"
                    . "- After delivering this message, if the visitor STILL goes off-topic, give a brief final redirect and stop engaging on the topic.\n";

            case 'login':
                return "**CURRENT PHASE INSTRUCTIONS (Login):**\n"
                    . "User has taken the quiz and logged in.\n"
                    . "- DO: Reference their quiz score and results\n"
                    . "- DO: Guide them to their free lesson\n"
                    . "- DON'T: Share content beyond the free lesson\n"
                    . "- DON'T: Pressure them to buy yet\n";

            case 'offer':
                return "**CURRENT PHASE INSTRUCTIONS (Offer):**\n"
                    . "User has seen their free lesson.\n"
                    . "- DO: Present the upgrade offer naturally\n"
                    . "- DO: Reference what they learned in the free lesson\n"
                    . "- DO: Mention their quiz weak areas as motivation\n"
                    . "- DON'T: Be pushy or salesy\n"
                    . "- DON'T: Share content they haven't paid for\n";

            case 'sale':
            case 'content':
                $recs_block = self::build_personalized_recommendations($eval_context);
                $is_member = !empty($eval_context['is_member']);
                $member_line = $is_member
                    ? "User is a member of this flow with the access granted by that entitlement.\n"
                    : "User is not a member of this flow. Do not share member-only content or claim full access.\n";
                return "**CURRENT PHASE INSTRUCTIONS ({$phase}):**\n"
                    . $member_line
                    . "- DO: Be their supportive learning coach\n"
                    . "- DO: When asked what to work on, use the Personalized Lesson Recommendations below as your starting point\n"
                    . "- DO: If the user describes a pronunciation difficulty, cross-reference it with their known weak sounds and the recommended lessons\n"
                    . "- DO: Celebrate progress and milestones — every lesson completed is a win worth acknowledging\n"
                    . "- DO: Answer detailed content questions that this access level is allowed to receive\n"
                    . ($is_member ? "- Full access to this flow's permitted materials\n" : "- Do not invent or unlock member-only materials\n")
                    . $recs_block;

            default:
                return '';
        }
    }

    /**
     * Get a one-liner description for phase change notifications.
     */
    private static function get_phase_one_liner($phase, $eval_context = []) {
        $liners = [
            'freeline' => 'Goal: encourage quiz.',
            'login'    => 'Goal: celebrate score, deliver free lesson.',
            'offer'    => 'Goal: present upgrade offer naturally.',
            'sale'     => 'Full access member — be their coach.',
            'content'  => 'Ongoing engagement and support.',
        ];
        return $liners[$phase] ?? '';
    }

    /**
     * Resolve phase outcomes from per-flow/global settings with safe defaults.
     * Accepts either a map (phase_outcomes[phase]) or per-phase keys.
     */
    private static function get_phase_outcomes($phase, $eval_context = [], $flow_id = null) {
        $raw_map = flosc_get_setting('phase_outcomes', [], $flow_id);
        if (is_array($raw_map) && isset($raw_map[$phase])) {
            $parsed = self::normalize_outcomes($raw_map[$phase]);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        $candidate_keys = [
            "phase_outcomes_{$phase}",
            "outcome_cues_{$phase}",
            "goalposts_{$phase}",
            "behaviors_{$phase}",
        ];
        foreach ($candidate_keys as $key) {
            $raw = flosc_get_setting($key, '', $flow_id);
            $parsed = self::normalize_outcomes($raw);
            if (!empty($parsed)) {
                return $parsed;
            }
        }

        return self::get_default_phase_outcomes($phase, $eval_context, $flow_id);
    }

    /**
     * Normalize outcomes from string/array formats into a clean string list.
     */
    private static function normalize_outcomes($raw) {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }

            // Pass 8: settings may store JSON; sanitize leaves after decode.
            $decoded = (strlen($trimmed) <= 100000) ? json_decode($trimmed, true, 16) : null;
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/[\r\n,|]+/', $trimmed);
            }
        } else {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (!is_scalar($item)) {
                continue;
            }
            $v = sanitize_text_field(trim((string) $item));
            if ($v !== '') {
                $normalized[] = $v;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Detect if an outcome list includes quiz-oriented intent.
     */
    private static function outcomes_include_quiz($outcomes) {
        if (!is_array($outcomes) || empty($outcomes)) {
            return false;
        }
        foreach ($outcomes as $outcome) {
            $needle = strtolower((string) $outcome);
            if (strpos($needle, 'quiz') !== false || strpos($needle, 'assessment') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Backward-compatible defaults when no explicit outcomes are configured.
     */
    private static function get_default_phase_outcomes($phase, $eval_context = [], $flow_id = null) {
        $quiz_in_progress = !empty($eval_context['quiz_in_progress']);

        switch ($phase) {
            case 'freeline':
                return $quiz_in_progress
                    ? ['Finish the in-progress quiz']
                    : ['Quiz entry', 'Inquiry clarification'];
            case 'login':
                return ['Quiz result clarity', 'Free lesson delivery'];
            case 'offer':
                return ['Offer readiness', 'Objection handling'];
            case 'sale':
                return ['Confident purchase confirmation', 'Member onboarding'];
            case 'content':
                return ['Learning momentum', 'Progress reinforcement'];
            default:
                return [];
        }
    }

    /**
     * Load knowledge base .md files from ai_configuration_files/ directory.
     * Access-filtered: visitors get public files, members get everything.
     */
    private static function load_knowledge_files($eval_context) {
        $flow_stem = sanitize_key((string) ($eval_context['flow_id'] ?? ''));
        if ($flow_stem === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow) && !empty($flow['id'])) {
                $flow_stem = sanitize_key((string) $flow['id']);
            }
        }
        if ($flow_stem === '' || !function_exists('flosc_knowledge_bases_prompt_text')) {
            return '';
        }
        $user_level = $eval_context['access_level'] ?? 'visitor';
        if (!in_array($user_level, ['visitor', 'guest', 'member'], true)) {
            $user_level = 'visitor';
        }
        return flosc_knowledge_bases_prompt_text($flow_stem, $user_level);
    }
}
