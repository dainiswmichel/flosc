<?php
/**
 * FLOSC Chatpack — Unified AI Context Builder
 * 
 * v1.9.2: First-contact vs subsequent-message prompt strategy.
 * 
 * On the FIRST message of a session, sends a comprehensive "chatpack" containing:
 *   - FLOSC identity, WordPress environment, user identity, flow context,
 *     knowledge base, corrections/praise, conversation rules.
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
     * Example: flosc_dainis_net_26_02m_21d_T14h30m12s_a3f7e21
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
        $mts = date('y', $now) . '_'
             . date('m', $now) . 'm_'
             . date('d', $now) . 'd_T'
             . date('H', $now) . 'h'
             . date('i', $now) . 'm'
             . date('s', $now) . 's';

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
        $mts = date('y', $now) . '_'
             . date('m', $now) . 'm_'
             . date('d', $now) . 'd_T'
             . date('H', $now) . 'h'
             . date('i', $now) . 'm'
             . date('s', $now) . 's';

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
    public static function count_message_pairs($session_id, $user_id) {
        if (!$session_id || !$user_id) {
            return 0;
        }

        $session_manager = new FLOSC_Session_Manager();
        $session = $session_manager->get_flosc_session($session_id, $user_id);

        if (!$session || empty($session['messages'])) {
            return 0;
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
    public static function load_conversation_history($session_id, $user_id, $max_messages = 10) {
        if (!$session_id || !$user_id) {
            return [];
        }

        $session_manager = new FLOSC_Session_Manager();
        $session = $session_manager->get_flosc_session($session_id, $user_id);

        if (!$session || empty($session['messages'])) {
            return [];
        }

        return array_slice(array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }, $session['messages']), -$max_messages);
    }

    /**
     * Detect if this is the first message in a session.
     *
     * @param int $session_id FLOSC session ID
     * @param int $user_id WordPress user ID
     * @return bool True if no prior messages exist
     */
    public static function is_first_message($session_id, $user_id) {
        return self::count_message_pairs($session_id, $user_id) === 0;
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
        $sections[] = self::build_identity_section();

        // ── 2. WORDPRESS ENVIRONMENT ────────────────────────
        $sections[] = self::build_wordpress_section();

        // ── 3. USER IDENTITY ────────────────────────────────
        $sections[] = self::build_user_section($eval_context);

        // ── 4. FLOW CONTEXT ─────────────────────────────────
        $sections[] = self::build_flow_section($phase, $eval_context);

        // ── 5. KNOWLEDGE BASE ───────────────────────────────
        $sections[] = self::build_knowledge_section($eval_context);

        // ── 6. IVR GUIDANCE ─────────────────────────────────
        if ($ivr_guidance) {
            $sections[] = self::build_ivr_section($ivr_guidance);
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
        $sections[] = "## FLOSC SESSION CONTINUE\n"
            . "FLOSC-HASH: {$flosc_hash}\n"
            . "FLOSC-SESSION: {$session_hash}\n"
            . "Message Pair: #{$pair_number}\n"
            . "Phase: {$phase}";

        // ── PHASE CHANGE NOTICE ─────────────────────────────
        if ($previous_phase && $previous_phase !== $phase) {
            $sections[] = "**PHASE CHANGED:** {$previous_phase} → {$phase}\n"
                . "Adjust your behavior to the new phase rules. "
                . self::get_phase_one_liner($phase);
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
            $sections[] = self::build_ivr_section($ivr_guidance);
        }

        return implode("\n\n", array_filter($sections));
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
    private static function build_identity_section() {
        $identity = FLOSC_Framework::instance()->get_floscflow_identity();
        $product_name = $identity['name'] ?? '';
        $product_tagline = $identity['tagline'] ?? '';
        $ai_name = flosc_get_setting('ai_name', 'AI Assistant');
        $ai_role = flosc_get_setting('ai_role', 'friendly learning assistant');
        $ai_traits = flosc_get_setting('ai_traits', 'helpful, encouraging, knowledgeable');
        $ai_mission = flosc_get_setting('ai_mission', '');
        $ai_boundaries = flosc_get_setting('ai_boundaries', '');

        $section = "## 1. IDENTITY — EXACT DEFINITIONS (do not paraphrase or invent)\n\n";

        // FLOSC definition — spelled out unambiguously
        $section .= "**FLOSC** = Freeline, Login, Offer, Sale, Content. Those are the 5 phases. "
            . "FLOSC is a white-label WordPress plugin framework. "
            . "That is ALL it stands for. Do not expand it any other way.\n\n";

        // Product info (floscAdmin-configured)
        if ($product_name) {
            $section .= "**This Site's Product:** {$product_name}";
            if ($product_tagline) {
                $section .= " = {$product_tagline}";
            }
            $section .= "\n";
            // Spell out the acronym rule explicitly for the product name
            if ($product_tagline) {
                $section .= "When asked what {$product_name} stands for, the ONLY correct answer is: \"{$product_tagline}\". "
                    . "Do not invent any other expansion.\n";
            } else {
                $section .= "If you do not know what {$product_name} stands for, say \"I'm not sure of the exact expansion — "
                    . "the site administrator hasn't configured that yet.\" Do NOT guess.\n";
            }
        } else {
            $section .= "**This Site's Product:** Not yet configured by the floscAdmin.\n";
        }

        // AI persona (floscAdmin-configured)
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

        // Hard rules (non-configurable safety rails)
        // Written as both positive (DO) and negative (NEVER) for maximum compliance.
        $section .= "\n**ABSOLUTE RULES — violation of any rule is a critical failure:**\n\n";

        $section .= "1. ACRONYMS: The ONLY acronym expansions you may state are the ones defined above. "
            . "FLOSC = Freeline, Login, Offer, Sale, Content. ";
        if ($product_name && $product_tagline) {
            $section .= "{$product_name} = {$product_tagline}. ";
        }
        $section .= "If someone asks what ANY word or name stands for and you don't have the answer "
            . "IN THIS PROMPT, say you don't know. NEVER guess or invent an acronym expansion.\n\n";

        $section .= "2. NO FABRICATION: Never invent, fabricate, or guess ANY of the following:\n";
        $section .= "   - Quiz questions, words, phrases, or pronunciation exercises\n";
        $section .= "   - Statistics, student counts, success rates, or social proof\n";
        $section .= "   - Lesson content, lesson titles, or course structure\n";
        $section .= "   - Features, capabilities, or tools that aren't described in this prompt\n";
        $section .= "   - Prices, discounts, or offers not defined in your context\n";
        $section .= "   If you don't have specific information, say so: \"I don't have that information right now.\"\n\n";

        $section .= "3. STAY IN YOUR LANE: You are a chat assistant. You do NOT administer quizzes. "
            . "The quiz is a separate audio-recording system on the page. If the user is taking a quiz, "
            . "do not pretend to be part of it, do not suggest words to say, and do not simulate quiz behavior.\n\n";

        $section .= "4. CONTENT ACCESS: Never share lesson content, member materials, or paid content "
            . "with visitors who haven't purchased.\n\n";

        $section .= "5. HONESTY OVER HELPFULNESS: If you don't know something, say so. "
            . "A wrong answer is worse than no answer. Never fill gaps in your knowledge with plausible-sounding inventions.\n";

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

        // Progress & access data
        if (!empty($eval_context['user_id']) && is_user_logged_in()) {
            $user_id = $eval_context['user_id'];
            $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
            $has_profile = $bridge_mgr->flosc_has_profile($user_id);
            $free_delivered = (bool) get_user_meta($user_id, '_flosc_free_lesson_delivered', true);
            $purchased = (bool) get_user_meta($user_id, '_flosc_member_access', true);

            $section .= "\n**Progress:**\n";
            $section .= "- Has Profile: " . ($has_profile ? 'Yes' : 'No') . "\n";
            $section .= "- Free Lesson Delivered: " . ($free_delivered ? 'Yes' : 'No') . "\n";
            $section .= "- Purchased: " . ($purchased ? 'Yes' : 'No') . "\n";
        }

        return $section;
    }

    /**
     * Section 4: Flow Context — current phase and phase-specific instructions.
     */
    private static function build_flow_section($phase, $eval_context) {
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
        $section .= "\n**FLOSC Funnel Phases:**\n";
        $section .= "1. **Freeline** — Visitor (not logged in). Goal: get them to take the quiz.\n";
        $section .= "2. **Login** — Guest (logged in, quiz done). Goal: show score, deliver free lesson.\n";
        $section .= "3. **Offer** — Guest (free lesson viewed). Goal: present upgrade offer.\n";
        $section .= "4. **Sale** — Member (purchased). Full access to all content.\n";
        $section .= "5. **Content** — Ongoing member engagement, support, encouragement.\n";

        // Phase-specific instructions
        $section .= "\n" . self::get_phase_instructions($phase, $eval_context);

        // Access-level instructions (floscAdmin-configurable via ai_prompt_{phase})
        $phase_prompt = flosc_get_setting("ai_prompt_{$phase}", '');
        if ($phase_prompt) {
            $section .= "\n**FloscAdmin Phase Instructions:**\n" . $phase_prompt . "\n";
        }

        return $section;
    }

    /**
     * Section 5: Knowledge Base — corrections, praise, KB files.
     * Corrections and praise are floscAdmin-managed training data.
     */
    private static function build_knowledge_section($eval_context) {
        $section = '';

        // Corrections (floscAdmin-flagged bad responses)
        $corrections = flosc_get_setting('ai_corrections', []);
        if (!empty($corrections) && is_array($corrections)) {
            $section .= "## 5a. ADMIN CORRECTIONS — Avoid These Mistakes\n\n";
            $section .= "The site administrator has flagged these as BAD responses. Learn from them:\n\n";
            foreach ($corrections as $i => $correction) {
                $num = $i + 1;
                $section .= "### Correction #{$num}\n";
                $section .= "**User said:** " . ($correction['user_message'] ?? '') . "\n";
                $section .= "**Bad response:** " . ($correction['bad_response'] ?? '') . "\n";
                $section .= "**Issue:** " . ($correction['admin_note'] ?? '') . "\n";
                if (!empty($correction['preferred_response'])) {
                    $section .= "**Better response:** " . $correction['preferred_response'] . "\n";
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
            $section .= "## 5c. KNOWLEDGE BASE FILES\n\n" . $kb_content;
        }

        return $section;
    }

    /**
     * Section 6: IVR Guidance — scripted response the AI should rewrite.
     */
    private static function build_ivr_section($ivr_guidance) {
        return "## IVR RESPONSE GUIDANCE\n\n"
            . "The scripted system matched the following reference material for the user's input. "
            . "This is AUTHORITATIVE product information written by the site administrator. "
            . "Use it as the factual basis for your reply — do not contradict it or add information "
            . "that isn't supported by this text or your other context sections.\n\n"
            . "Respond to what the user ACTUALLY asked. Do not simply copy-paste the guidance — "
            . "rewrite it naturally in your voice. But STAY WITHIN the facts provided. "
            . "If the guidance doesn't cover what the user asked, say you don't have that information.\n\n"
            . "CRITICAL: Check the conversation history. If you have already shared this information, "
            . "do NOT repeat it. Acknowledge what you already told them and address the new angle.\n\n"
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
        $section .= "- If the user asks about something you already covered, acknowledge it briefly (e.g., 'As I mentioned...') and then address the NEW aspect of their question\n";
        $section .= "- Each response must add NEW value — if you have nothing new to add, say so honestly\n";
        $section .= "- Vary your language and structure across responses — do not use the same phrasing twice\n";

        // v8.0.10: Anti-hallucination anchor — reinforced at end of prompt for recency bias
        $section .= "\n**FACTUAL GROUNDING (final reminder):**\n";
        $section .= "- Your ONLY source of truth about this product is this system prompt and the knowledge base files above\n";
        $section .= "- Do NOT use your general training knowledge to fill in product details, features, prices, or course content\n";
        $section .= "- If this prompt doesn't tell you something, you don't know it. Say so.\n";
        $section .= "- NEVER invent acronym expansions. FLOSC = Freeline, Login, Offer, Sale, Content. That's it.\n";
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
     * Get phase-specific behavioral instructions.
     */
    private static function get_phase_instructions($phase, $eval_context) {
        $access_level = $eval_context['access_level'] ?? 'visitor';

        switch ($phase) {
            case 'freeline':
                $quiz_in_progress = !empty($eval_context['quiz_in_progress']);
                $freeline_intro = $quiz_in_progress
                    ? "This visitor is CURRENTLY TAKING the pronunciation quiz (in progress right now).\n"
                      . "- The quiz is managed by a SEPARATE recording/analysis system — NOT by you.\n"
                      . "- NEVER fabricate quiz questions, words, phrases, or pronunciation exercises.\n"
                      . "- NEVER pretend you are administering the quiz or suggest words to say.\n"
                      . "- If asked about status: tell them they're a Visitor taking the quiz, and encourage them to finish it.\n"
                      . "- Keep answers brief — the user should get back to their quiz.\n"
                    : "This visitor has NOT taken the quiz yet. Your goal: spark curiosity and nudge toward the quiz.\n"
                      . "- Guide the visitor toward the quiz\n"
                      . "- If they ask a question, give an honest answer, then mention the quiz\n";
                return "**CURRENT PHASE INSTRUCTIONS (Freeline):**\n"
                    . $freeline_intro
                    . "- DON'T share lesson content, titles, or pricing\n"
                    . "\n**VISITOR RESOURCE POLICY:**\n"
                    . "Visitors are NOT logged-in members. They have LIMITED conversation access.\n"
                    . "- For the FIRST 1-2 off-topic or general questions: answer briefly and naturally, then steer toward the quiz.\n"
                    . "- After 2+ off-topic questions (or if the visitor is clearly using you as a general AI chatbot): \n"
                    . "  Politely but firmly redirect. Use language like: 'Because you're currently only logged in as a visitor, "
                    . "I'm not really authorized to engage on in-depth or off-topic subjects. I am happy to guide you through "
                    . "a pronunciation quiz that tests your Standard American English pronunciation. When you're a member, you "
                    . "have more access to system resources, but as a guest, the site is actually paying to host your conversation "
                    . "here. Even though it pains me to do so, I do sometimes need to wrap up conversations when a user asks "
                    . "off-topic questions. Would you like to take our quiz now?'\n"
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
                return "**CURRENT PHASE INSTRUCTIONS ({$phase}):**\n"
                    . "User is a paying member with full access.\n"
                    . "- DO: Be their supportive learning coach\n"
                    . "- DO: Reference their quiz results to suggest lessons\n"
                    . "- DO: Celebrate progress and encourage continued learning\n"
                    . "- DO: Answer detailed content questions\n"
                    . "- Full access to all lessons and materials\n";

            default:
                return '';
        }
    }

    /**
     * Get a one-liner description for phase change notifications.
     */
    private static function get_phase_one_liner($phase) {
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
     * Load knowledge base .md files from ai_configuration_files/ directory.
     * Access-filtered: visitors get public files, members get everything.
     */
    private static function load_knowledge_files($eval_context) {
        $kb_dir = defined('FLOSC_PLUGIN_DIR')
            ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/'
            : '';

        if (!$kb_dir || !is_dir($kb_dir)) {
            return '';
        }

        $access_level = $eval_context['access_level'] ?? 'visitor';
        $content = '';

        $files = glob($kb_dir . '*.md');
        if (!$files) {
            return '';
        }

        foreach ($files as $file) {
            $filename = basename($file);

            // Skip IVR files, backups, and system files
            if (strpos($filename, 'ivr') !== false
                || strpos($filename, 'backup') !== false
                || $filename === 'README.md') {
                continue;
            }

            // Access filter: files starting with "members_" require member access
            if (strpos($filename, 'members_') === 0 && $access_level === 'visitor') {
                continue;
            }

            $file_content = file_get_contents($file);
            if ($file_content) {
                // Limit individual file size to prevent token bloat
                if (strlen($file_content) > 3000) {
                    $file_content = substr($file_content, 0, 3000) . "\n[...truncated]";
                }
                $content .= "### {$filename}\n{$file_content}\n\n";
            }
        }

        return $content;
    }
}
