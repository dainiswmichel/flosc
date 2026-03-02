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

        // v1.9.5: Unified admin feedback — reads rated chat logs from DB
        $feedback_prompt = $this->build_feedback_prompt();

        // v3.0.5: AI-interpretation offer phrases — when the user's message
        // semantically matches one of these phrases, AI should include the action tag.
        $offer_phrase_section = '';
        if (function_exists('flosc') && method_exists(flosc(), 'get_ai_interpretation_offers')) {
            $flow_id = $context['flow_id'] ?? null;
            $ai_offers = flosc()->get_ai_interpretation_offers($flow_id);
            if (!empty($ai_offers)) {
                $offer_phrase_section = "## Offer Phrase Triggers\n";
                $offer_phrase_section .= "When the user's message matches the INTENT of any phrase below, ";
                $offer_phrase_section .= "include the corresponding action tag at the END of your response on its own line. ";
                $offer_phrase_section .= "The tag format is: [ACTION:show_offer_OFFERID]\n";
                $offer_phrase_section .= "Only trigger when the user's intent clearly matches. Do not force a match.\n\n";
                foreach ($ai_offers as $ao) {
                    $offer_phrase_section .= "- Phrase: \"{$ao['reveal_phrase']}\" → Tag: [ACTION:show_offer_{$ao['id']}] (Offer: {$ao['name']})\n";
                }
            }
        }

        // v3.0.8: Lesson search action tags — teach AI to emit [ACTION:open_filtered_lessons:topic]
        // when a member asks to find/browse lessons on a specific sound or topic.
        // The JS pattern matcher handles common phrasings client-side; this catches creative variants.
        $lesson_search_section = '';
        if ( function_exists( 'flosc' ) ) {
            $flow = flosc()->get_current_flow();
            if ( ! empty( $flow['lesson_groups'] ) ) {
                $lesson_search_section  = "## Lesson Search Action Tags\n";
                $lesson_search_section .= "When a **member** asks to find, browse, or see lessons about a specific sound, topic, or keyword, ";
                $lesson_search_section .= "include an action tag at the END of your response on its own line.\n";
                $lesson_search_section .= "Format: [ACTION:open_filtered_lessons:TOPIC]\n";
                $lesson_search_section .= "Use the exact topic word(s) the user mentioned as the TOPIC value. Examples:\n";
                $lesson_search_section .= "- \"show me lessons on vowel sounds\" → [ACTION:open_filtered_lessons:vowel sounds]\n";
                $lesson_search_section .= "- \"find TH lessons\" → [ACTION:open_filtered_lessons:TH]\n";
                $lesson_search_section .= "- \"what covers the R sound\" → [ACTION:open_filtered_lessons:R sound]\n";
                $lesson_search_section .= "Note: lesson titles are descriptive and serve as accurate metadata — the topic search matches against titles.\n";
                $lesson_search_section .= "Only emit this tag when the user clearly wants to browse or find topic-specific lessons.\n";
            }
        }

        // v4.0.0: LeSAEp quiz results — available to Guests and LeSAEp Learners (members).
        // Visitors NEVER see quiz results or learn which questions they missed.
        // They must create an account first. This is a STRICT business rule.
        $quiz_results_section = '';
        if ( is_user_logged_in() && class_exists( 'FLOSC_LeSAEp_Pronunciation_Quiz' ) ) {
            $user_id     = get_current_user_id();
            $raw_answers = get_user_meta( $user_id, '_flosc_quiz_answers_lesaep_text_based_pronunciation_quiz', true );
            if ( ! empty( $raw_answers ) ) {
                $quiz    = new FLOSC_LeSAEp_Pronunciation_Quiz();
                $content = function_exists( 'flosc_get_setting' ) ? flosc_get_setting( 'quiz_content_lesaep_text_based_pronunciation_quiz', '' ) : '';
                $result  = $quiz->analyze( $raw_answers, $content );

                $sound_map = [
                    1  => 'The /æ/ short-a vowel (cat, map, back)',
                    2  => 'The American rhotic R (car, bird, butter)',
                    3  => 'Voiceless TH /θ/ (think, three, bath)',
                    4  => 'Voiced TH /ð/ (this, that, the)',
                    5  => '/ɪ/ vs /iː/ — ship vs sheep',
                    6  => 'Schwa /ə/ and unstressed vowels',
                    7  => 'Flap T (butter = "budder")',
                    8  => 'Word stress patterns (DES-ert vs de-SERT)',
                    9  => 'Connected speech / linking (turn-it-off)',
                    10 => 'Dark L vs. light L (full, ball, feel)',
                ];

                $score  = $result['score'];
                $missed = $result['incorrect'];
                $got    = $result['correct'];
                $total  = count( $got ) + count( $missed );

                $quiz_results_section  = "## LeSAEp Pronunciation Quiz Results\n";
                $quiz_results_section .= "Score: {$score}% (" . count( $got ) . "/{$total} correct)\n\n";

                if ( ! empty( $missed ) ) {
                    $quiz_results_section .= "**Sounds this learner needs to work on:**\n";
                    foreach ( $missed as $lesson_num ) {
                        $sound = $sound_map[ $lesson_num ] ?? "Lesson {$lesson_num}";
                        $quiz_results_section .= "- Lesson {$lesson_num}: {$sound}\n";
                    }
                }

                if ( ! empty( $got ) ) {
                    $quiz_results_section .= "\n**Sounds already mastered:**\n";
                    foreach ( $got as $lesson_num ) {
                        $sound = $sound_map[ $lesson_num ] ?? "Lesson {$lesson_num}";
                        $quiz_results_section .= "- Lesson {$lesson_num}: {$sound}\n";
                    }
                }

                $is_member = ( ( $context['purchased'] ?? 'No' ) === 'Yes' );
                if ( $is_member ) {
                    // LeSAEp Learner — full access: discuss specific lessons, sounds, personalized path
                    $quiz_results_section .= "\n**Context:** This user is a LeSAEp Learner (paid member). You CAN discuss their missed sounds in detail, reference specific lesson numbers, and guide them through their personalized learning path.\n";
                } else {
                    // Guest — has an account and quiz results, but hasn't purchased yet
                    // Share their score and missed sounds to motivate purchase; do NOT deliver lesson content
                    $quiz_results_section .= "\n**Context:** This user has an account but has not yet purchased full access. You MAY share their quiz score and which sounds they missed — this helps motivate the purchase. Do NOT deliver lesson content (videos, exercises, detailed how-to). Encourage them to unlock their full personalized learning path.\n";
                }
            }
        }

        // v4.0.0: Admin context report — full config summary when speaking with an admin
        $admin_context_section = '';
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            $flow_id = $context['flow_id'] ?? null;
            $lines   = [];

            // Offers
            if ( function_exists( 'flosc' ) ) {
                $all_offers = flosc()->sale_manager->offers()->get_all_offers( $flow_id );
                if ( $all_offers ) {
                    $lines[] = "**Configured Offers:**";
                    foreach ( $all_offers as $o ) {
                        $status = ( ( $o['status'] ?? 'draft' ) === 'active' ) ? '✅ ACTIVE' : '📝 DRAFT';
                        $price  = $o['display_price'] ?? ( isset( $o['price'] ) ? '$' . $o['price'] : '' );
                        $fmt    = $o['display_format'] ?? 'card';
                        $lines[] = "- [{$status}] {$o['name']} (id: {$o['id']}, format: {$fmt}" . ($price ? ", price: {$price}" : '') . ")";
                    }
                }
            }

            // Quizzes
            if ( function_exists( 'flosc_get_setting' ) && class_exists( 'FLOSC_Quiz_Registry' ) ) {
                $enabled_quizzes = flosc_get_setting( 'enabled_quizzes', ['flosc_sample_data_numbers_quiz'] );
                if ( ! is_array( $enabled_quizzes ) ) $enabled_quizzes = ['flosc_sample_data_numbers_quiz'];
                if ( $enabled_quizzes ) {
                    $lines[] = "**Configured Quizzes:**";
                    foreach ( $enabled_quizzes as $qid ) {
                        $qt     = FLOSC_Quiz_Registry::get_quiz( $qid );
                        $name   = $qt ? $qt->get_name() : ucwords( str_replace( '_', ' ', $qid ) );
                        $lines[] = "- {$name} (id: {$qid})";
                    }
                }
            }

            // AutoPrompt pills per state
            if ( function_exists( 'flosc_get_setting' ) ) {
                $raw_pills = flosc_get_setting( 'autoprompts', [] );
                foreach ( ['visitor', 'guest', 'member'] as $state ) {
                    $pills = $raw_pills[$state] ?? [];
                    if ( $pills ) {
                        $label_list = implode( ', ', array_column( $pills, 'label' ) );
                        $lines[] = "**" . ucfirst($state) . " AutoPrompt Pills:** {$label_list}";
                    }
                }
            }

            if ( $lines ) {
                $admin_context_section  = "## Admin Configuration Report\n";
                $admin_context_section .= "You are speaking with the SITE ADMIN. Full config data is available.\n";
                $admin_context_section .= "The admin can ask: 'What does a visitor see?', 'List all offers', 'Walk me through the flow.'\n\n";
                $admin_context_section .= implode( "\n", $lines ) . "\n";
            }
        }

        // 6. Merge all sections
        $sections = array_filter([
            $identity_prompt,
            $flosc_process,
            $phase_prompt,
            $orientation_content ? "## Knowledge Base\n" . $orientation_content : '',
            $feedback_prompt,
            $offer_phrase_section,
            $lesson_search_section,
            $quiz_results_section,
            $admin_context_section,
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
     * v1.9.2: Added FLOSC framework description to prevent identity hallucination
     */
    private function build_identity_prompt($context = []) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $name = flosc_get_setting('ai_personality_name', flosc_get_setting('product_name', 'FLOSC'));
        $role = flosc_get_setting('ai_personality_role', 'AI assistant');
        $traits = flosc_get_setting('ai_personality_traits', 'Helpful, friendly, and professional.');
        $mission = flosc_get_setting('ai_mission', 'Help users achieve their goals.');
        $boundaries = flosc_get_setting('ai_boundaries', '');
        $product_name = flosc_get_setting('product_name', '');
        $product_tagline = flosc_get_setting('product_tagline', '');

        $prompt = "# Your Identity\n\n";

        // v1.9.2: FLOSC Framework Identity — prevents AI from hallucinating
        // what FLOSC is or what it teaches. The product is configured by the site admin.
        $prompt .= "## CRITICAL: What FLOSC Is\n";
        $prompt .= "FLOSC is a **white-label WordPress plugin framework** for selling knowledge-based products online. ";
        $prompt .= "The letters F-L-O-S-C stand for the 5 sales funnel phases: **Freeline, Login, Offer, Sale, Content**. ";
        $prompt .= "FLOSC is NOT a school, course, or educational institution itself — it is the SOFTWARE that powers the site.\n\n";
        $prompt .= "**NEVER invent or guess what FLOSC stands for.** It is always: Freeline, Login, Offer, Sale, Content.\n";
        $prompt .= "**NEVER invent what this site teaches.** Only describe the product using the information below.\n\n";

        // v1.9.2: Product identity (what the admin's site actually sells)
        if ($product_name) {
            $prompt .= "## This Site's Product\n";
            $prompt .= "**Product Name:** {$product_name}\n";
            if ($product_tagline) {
                $prompt .= "**Tagline:** {$product_tagline}\n";
            }
            $prompt .= "You are the AI assistant for **{$product_name}**. ";
            $prompt .= "Refer to this product by name when users ask what this site offers.\n\n";
        } else {
            $prompt .= "## This Site's Product\n";
            $prompt .= "The site administrator has not yet configured a product name. ";
            $prompt .= "If users ask what this site teaches, say: 'The site is still being set up. ";
            $prompt .= "Please check back soon or ask the administrator for details.'\n\n";
        }

        $prompt .= "## Your Persona\n";
        $prompt .= "You are **{$name}**, a {$role}.\n\n";
        $prompt .= "## Personality\n{$traits}\n\n";
        $prompt .= "## Mission\n{$mission}\n";

        if ($boundaries) {
            $prompt .= "\n## Boundaries\n{$boundaries}";
        }

        return $prompt;
    }

    /**
     * v1.9.5: Build unified feedback prompt from rated chat log entries.
     * Reads directly from the flosc_chat_logs table (admin_rating != 0).
     * Negative ratings = corrections. Positive ratings = praise. Magnitude = weight.
     *
     * Also includes legacy ai_corrections/ai_praises from flow settings
     * for backward compatibility with manually-added entries.
     */
    private function build_feedback_prompt() {
        global $wpdb;

        $sections = [];

        // ── DB-rated entries (new system) ──
        $table = $wpdb->prefix . 'flosc_chat_logs';

        // Check if admin_rating column exists before querying
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'admin_rating'");
        if (!empty($col)) {
            // Get negative-rated logs (corrections), strongest first, limit 20
            $negatives = $wpdb->get_results(
                "SELECT user_message, ai_response, admin_rating, admin_note FROM {$table} 
                 WHERE admin_rating < 0 ORDER BY admin_rating ASC, rated_at DESC LIMIT 20",
                ARRAY_A
            );

            if (!empty($negatives)) {
                $prompt = "## Admin Corrections (Rated Responses)\n";
                $prompt .= "The administrator scored these responses negatively. Avoid this behavior.\n\n";
                foreach ($negatives as $i => $row) {
                    $num = $i + 1;
                    $score = $row['admin_rating'];
                    $prompt .= "### Correction {$num} (score: {$score}/10)\n";
                    $prompt .= "**User said:** \"" . mb_substr($row['user_message'], 0, 200) . "\"\n";
                    $prompt .= "**Your bad response:** \"" . mb_substr($row['ai_response'], 0, 300) . "\"\n";
                    if (!empty($row['admin_note'])) {
                        $prompt .= "**Admin note:** {$row['admin_note']}\n";
                    }
                    $prompt .= "\n";
                }
                $sections[] = $prompt;
            }

            // Get positive-rated logs (praise), strongest first, limit 20
            $positives = $wpdb->get_results(
                "SELECT user_message, ai_response, admin_rating, admin_note FROM {$table} 
                 WHERE admin_rating > 0 ORDER BY admin_rating DESC, rated_at DESC LIMIT 20",
                ARRAY_A
            );

            if (!empty($positives)) {
                $prompt = "## Admin Praise (Rated Responses)\n";
                $prompt .= "The administrator scored these responses positively. Replicate this quality.\n\n";
                foreach ($positives as $i => $row) {
                    $num = $i + 1;
                    $score = $row['admin_rating'];
                    $prompt .= "### Example {$num} (score: +{$score}/10)\n";
                    $prompt .= "**User said:** \"" . mb_substr($row['user_message'], 0, 200) . "\"\n";
                    $prompt .= "**Your excellent response:** \"" . mb_substr($row['ai_response'], 0, 300) . "\"\n";
                    if (!empty($row['admin_note'])) {
                        $prompt .= "**Why this was good:** {$row['admin_note']}\n";
                    }
                    $prompt .= "\n";
                }
                $sections[] = $prompt;
            }
        }

        // ── Legacy manual corrections/praises (backward compat) ──
        $corrections = flosc_get_setting('ai_corrections', []);
        if (!empty($corrections) && is_array($corrections)) {
            $prompt = "## Manual Corrections\n";
            $prompt .= "The following are specific corrections from the administrator.\n\n";
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
            $sections[] = $prompt;
        }

        $praises = flosc_get_setting('ai_praises', []);
        if (!empty($praises) && is_array($praises)) {
            $prompt = "## Manual Praise\n";
            $prompt .= "The administrator praised the following responses.\n\n";
            foreach ($praises as $i => $praise) {
                $num = $i + 1;
                $prompt .= "### Example {$num}\n";
                $prompt .= "**When the user said:** \"{$praise['user_message']}\"\n";
                $prompt .= "**Your excellent response was:** \"{$praise['good_response']}\"\n";
                $prompt .= "**Why this was good:** {$praise['admin_note']}\n\n";
            }
            $sections[] = $prompt;
        }

        return implode("\n", $sections);
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

        // v1.9.2: Admin gets full context PLUS admin-specific guidance (no early return)
        if ($is_admin) {
            $prompt .= "## Current User: ADMIN\n";
            $prompt .= "This user is a site administrator, not a customer. Adjust your behavior:\n";
            $prompt .= "- Be direct and technical — skip sales guidance and marketing talk\n";
            $prompt .= "- They may be testing you, configuring flows, or debugging\n";
            $prompt .= "- Report your current configuration when asked (provider, phase logic, etc.)\n";
            $prompt .= "- If they ask about the FLOSC process, explain how it works rather than performing it\n";
            $prompt .= "- Answer admin questions with factual data from the context provided, not generic statements\n";
            $prompt .= "- When asked about user status, use the session context data below — do not make things up\n\n";
            // Don't return early — admin still needs phase/topic context below
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
     * v1.9.2: Handle arrays and nested values gracefully
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
            } elseif (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            } elseif (is_null($value)) {
                continue; // Skip null values
            }
            // Format key: flosc_version → Flosc Version, quiz_score → Quiz Score
            $label = ucwords(str_replace('_', ' ', $key));
            $lines[] = "- **{$label}:** {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Get default base system prompt
     */
    private function get_default_base_prompt() {
        $identity = $this->get_floscflow_identity();
        return "You are the {$identity['name']} AI assistant. Your mission is to help users learn and improve through personalized guidance and encouragement. Be helpful, friendly, specific, and action-oriented. Always reference the user's quiz results and progress when available.";
    }

    /**
     * Get AI Response
     * @param bool $test_mode If true, return WP_Error on failure instead of falling back to IVR
     */
    public function get_response($message, $system_prompt = '', $context = [], $test_mode = false) {
        // v1.9.2: Never cache for admin users — admin is testing/debugging and needs fresh responses.
        // Also skip cache in test mode.
        $is_admin = is_user_logged_in() && current_user_can('manage_options');
        $use_cache = !$test_mode && !$is_admin;

        if ($use_cache) {
            // Include user_id in cache key so different users never share cached responses
            $user_id = get_current_user_id();
            $context_hash = !empty($context) ? md5(json_encode($context)) : '';
            $cache_key = 'flosc_ai_' . md5($this->provider . $message . $system_prompt . $context_hash . $user_id);
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

        // v1.9.2: Reduced cache TTL from 1 hour to 5 minutes.
        // AI responses are dynamic and context-dependent — long caches cause stale/wrong responses.
        if ($use_cache && $response && !is_wp_error($response)) {
            set_transient($cache_key, $response, 5 * MINUTE_IN_SECONDS);
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
            case 'ivr':       return $this->ivr_response($message); // Explicit IVR mode — correct
            default:
                // v1.9.3: Unknown/misconfigured provider — return null, not silent IVR
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) error_log('FLOSC: Unknown AI provider: ' . $provider);
                return null;
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

        // v1.9.3: Return null on total chain failure — caller decides fallback, not dispatch
        return $response ?: null;
    }
    
    /**
     * IVR - Scripted Responses (Free)
     */
    private function ivr_response($message) {
        $message_lower = strtolower($message);
        $identity = $this->get_floscflow_identity();
        $name = $identity['name'] ?: 'this app';

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

        // Pricing — read from offers, not identity
        if (preg_match('/\b(price|cost|pay|money|expensive|cheap|how much)\b/', $message_lower)) {
            $offers = flosc_sale()->offers()->get_active_offers();
            $main_offer = reset($offers);
            $price_val = $main_offer['price'] ?? $main_offer['pricing']['price'] ?? '';
            $price_display = $price_val ? ('$' . number_format(floatval($price_val), 2)) : 'available at a great price';
            return "Full lifetime access to {$name} is {$price_display} — that's a one-time payment with no subscriptions or hidden fees.\n\nBut first, take the free quiz to see exactly what you'll get!";
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
            // v1.9.3: Return null — caller decides fallback, not dispatch
            return null;
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
            return null; // v1.9.3: No silent IVR substitution
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
            return null; // v1.9.3: No silent IVR substitution
        }

        return $body['choices'][0]['message']['content'] ?? null;
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
            return null; // v1.9.3: No silent IVR substitution
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
            return null; // v1.9.3: No silent IVR substitution
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
            return null; // v1.9.3: No silent IVR substitution
        }

        return $data['content'][0]['text'] ?? null;
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
            return null; // v1.9.3: No silent IVR substitution
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
            return null; // v1.9.3: No silent IVR substitution
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
            return null; // v1.9.3: No silent IVR substitution
        }

        return $body['choices'][0]['message']['content'] ?? null;
    }
    
    /**
     * Get FloscFlow Identity (helper)
     */
    private function get_floscflow_identity() {
        $currency = flosc_get_setting('currency', 'EUR');
        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        
        return [
            'name' => flosc_get_setting('product_name', 'FLOSC App'),
            'price' => flosc_get_setting('product_price', ''),
            'currency_symbol' => $symbols[$currency] ?? $currency,
        ];
    }
}
