<?php
/**
 * FLOSC AI Chat Dispatch
 * Supports: IVR (scripted), Anthropic, OpenAI, xAI, Gemini.
 */

if (!defined('ABSPATH')) exit;

class FLOSC_AI_Chat_Dispatch {

    private $provider;
    public $last_chain_detail = [];
    private $last_billing_meta = [];

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

        // when a member asks to find/browse lessons on a specific sound or topic.
        // The JS pattern matcher handles common phrasings client-side; this catches creative variants.
        $lesson_search_section = '';
        if ( function_exists( 'flosc' ) ) {
            $flow = flosc()->get_current_flow();
            if ( ! empty( $flow['content_item_groups'] ) ) {
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

        // v8.0.0: Quiz action tag — teach the AI how to launch quizzes via action tags
        // instead of fabricating quiz content. The AI is an IVR humanizer, NOT a content creator.
        $quiz_action_section = '';
        if ( function_exists( 'flosc_get_setting' ) && class_exists( 'FLOSC_Quiz_Registry' ) ) {
            $enabled_quizzes = flosc_get_setting( 'enabled_quizzes', [] );
            if ( ! is_array( $enabled_quizzes ) ) {
                $enabled_quizzes = [];
            }
            if ( ! empty( $enabled_quizzes ) ) {
                $quiz_action_section  = "## Quiz Action Tags\n";
                $quiz_action_section .= "When a user asks to take a quiz or test their pronunciation:\n";
                $quiz_action_section .= "- Just allow the quiz code to do its thing and present the quiz to the user.\n";
                $quiz_action_section .= "- DO NOT fabricate a quiz. You are NOT the content creator here. ";
                $quiz_action_section .= "You are an IVR message humanizer and content facilitator for this flow. ";
                $quiz_action_section .= "Lesson content comes from this flow's WordPress posts / knowledge base. ";
                $quiz_action_section .= "You do not need to fabricate quizzes — please DO NOT FABRICATE QUIZZES.\n";
                $quiz_action_section .= "- Should you be unable to restrain yourself, and simply MUST interject yourself ";
                $quiz_action_section .= "between the user's quiz request and the system's presentation of the quiz to the user, ";
                $quiz_action_section .= "you may respond with a brief encouraging message like 'Coming right up...' ";
                $quiz_action_section .= "or 'Your quiz is coming right up...' and include the action tag at the END of your response. ";
                $quiz_action_section .= "NEVER fabricate quiz questions yourself.\n\n";
                $quiz_action_section .= "Format: [ACTION:open_quiz:QUIZ_ID]\n";
                $quiz_action_section .= "Available quizzes:\n";
                foreach ( $enabled_quizzes as $qid ) {
                    $qt   = FLOSC_Quiz_Registry::get_quiz( $qid );
                    $name = $qt ? $qt->get_name() : ucwords( str_replace( '_', ' ', $qid ) );
                    $quiz_action_section .= "- {$name} → [ACTION:open_quiz:{$qid}]\n";
                }
            }
        }

        // Sample / flow assessment results — available to Guests and members.
        // Visitors NEVER see quiz results or learn which questions they missed.
        // They must create an account first. This is a STRICT business rule.
        $quiz_results_section = '';
        if ( is_user_logged_in() && class_exists( 'FLOSC_Sample_Assessment_Quiz' ) ) {
            $user_id     = get_current_user_id();
            $raw_answers = get_user_meta( $user_id, '_flosc_quiz_answers_sample_assessment_quiz', true );
            if ( ! empty( $raw_answers ) ) {
                $quiz    = new FLOSC_Sample_Assessment_Quiz();
                $content = function_exists( 'flosc_get_setting' )
                    ? flosc_get_setting( 'quiz_content_sample_assessment_quiz', '' )
                    : '';
                $result  = $quiz->analyze( $raw_answers, $content, array( 'user_id' => $user_id ) );

                $score  = (int) ( $result['score'] ?? 0 );
                $missed = is_array( $result['incorrect'] ?? null ) ? $result['incorrect'] : array();
                $got    = is_array( $result['correct'] ?? null ) ? $result['correct'] : array();
                $total  = count( $got ) + count( $missed );

                $quiz_results_section  = "## Assessment Quiz Results\n";
                $quiz_results_section .= "Score: {$score}% (" . count( $got ) . "/{$total} correct)\n\n";

                if ( ! empty( $missed ) ) {
                    $quiz_results_section .= "**Topics this learner needs to work on:**\n";
                    foreach ( $missed as $item ) {
                        $item_num = is_array( $item ) ? (int) ( $item['question_index'] ?? 0 ) : (int) $item;
                        $topic    = $item_num > 0 ? "Topic {$item_num}" : 'Topic';
                        if ( is_array( $item ) && ! empty( $item['topics'][0] ) ) {
                            $topic = (string) $item['topics'][0];
                        }
                        $label = $item_num > 0 ? "Item {$item_num}" : 'Item';
                        $quiz_results_section .= "- {$label}: {$topic}\n";
                    }
                }

                if ( ! empty( $got ) ) {
                    $quiz_results_section .= "\n**Topics already solid:**\n";
                    foreach ( $got as $item ) {
                        $item_num = is_array( $item ) ? (int) ( $item['question_index'] ?? 0 ) : (int) $item;
                        $topic    = $item_num > 0 ? "Topic {$item_num}" : 'Topic';
                        if ( is_array( $item ) && ! empty( $item['topics'][0] ) ) {
                            $topic = (string) $item['topics'][0];
                        }
                        $label = $item_num > 0 ? "Item {$item_num}" : 'Item';
                        $quiz_results_section .= "- {$label}: {$topic}\n";
                    }
                }

                // Membership for AI: trust access_level / is_member first, then purchased (bool or Yes).
                $is_member = $this->flosc_context_user_is_member( $context );
                if ( $is_member ) {
                    $quiz_results_section .= "\n**Context:** This user is a **member** (full member access). "
                        . "You CAN and SHOULD discuss their missed topics in detail, reference matching content "
                        . "from CorrectContent / RelatedContent and flow topics, guide them to real WordPress lessons, "
                        . "and help them practice. "
                        . "Do NOT claim they are a guest. Do NOT push upgrades or free-lesson funnels. "
                        . "Do NOT say you already answered, refuse, or make excuses — find the content and help.\n";
                } else {
                    // Guest — logged in, not a full member
                    $quiz_results_section .= "\n**Context:** This user is a **Guest** (logged in, not a full member yet). "
                        . "You MAY share their quiz score and which topics they missed. "
                        . "Do NOT deliver full member content (videos, exercises, detailed how-to). "
                        . "You may invite them to unlock full member access.\n";
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
                $all_offers = flosc()->sale()->offers()->get_all_offers( $flow_id );
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
                $enabled_quizzes = flosc_get_setting( 'enabled_quizzes', [] );
                if ( ! is_array( $enabled_quizzes ) ) {
                    $enabled_quizzes = [];
                }
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
            $quiz_action_section,
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
        // Personality: attached library entry (one per flow) wins when set; else flow bag.
        $name = function_exists( 'flosc_personality_name' )
            ? flosc_personality_name()
            : ( function_exists( 'flosc_personality_library_resolve_field' )
                ? flosc_personality_library_resolve_field( 'ai_personality_name', 'FLOSC' )
                : flosc_get_setting( 'ai_personality_name', 'FLOSC' ) );
        $role = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_personality_role', 'AI assistant' )
            : flosc_get_setting( 'ai_personality_role', 'AI assistant' );
        $traits = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_personality_traits', 'Helpful, friendly, and professional.' )
            : flosc_get_setting( 'ai_personality_traits', 'Helpful, friendly, and professional.' );
        $mission = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_mission', 'Help users achieve their goals.' )
            : flosc_get_setting( 'ai_mission', 'Help users achieve their goals.' );
        $boundaries = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_boundaries', '' )
            : flosc_get_setting( 'ai_boundaries', '' );
        $product_name = function_exists( 'flosc_flow_public_title' )
            ? flosc_flow_public_title()
            : flosc_get_setting( 'title', '' );
        $product_tagline = function_exists( 'flosc_flow_public_tagline' )
            ? flosc_flow_public_tagline()
            : flosc_get_setting( 'tagline', '' );

        $prompt = "# Your Identity\n\n";

        // v8.0.1: Content-agnostic. Nothing about the product is hardcoded
        // here. Admins write optional Product facts on the AI tab; those are
        // injected verbatim. Empty means none — never invent substitutes.
        $brand_facts = trim( (string) ( function_exists( 'flosc_get_setting' )
            ? flosc_get_setting( 'ai_brand_facts', '' )
            : '' ) );
        if ( $brand_facts !== '' ) {
            $prompt .= "## Product facts\n";
            $prompt .= "Configured facts about this product. These override any guess you could make. Never invent or substitute expansions, categories, or claims.\n\n";
            $prompt .= $brand_facts . "\n\n";
        }
        $prompt .= "**NEVER invent facts about this flow.** Use only configured flow settings and the attached personality profile.\n\n";

        if ($product_name) {
            $prompt .= "## Configured Flow Title\n";
            $prompt .= "{$product_name}\n";
            if ($product_tagline) {
                $prompt .= "Configured Flow Tagline: {$product_tagline}\n";
            }
            $prompt .= "Use these configured flow values only when relevant. Do not announce configuration status or direct visitors to the administrator.\n\n";
        }

        $compiled_profile = function_exists( 'flosc_personality_compiled_profile' )
            ? flosc_personality_compiled_profile()
            : '';
        if ( $compiled_profile !== '' ) {
            $prompt .= "## Personality\n";
            $prompt .= "The following profile is who you are. Speak as this person. Do not describe how you were made.\n\n";
            $prompt .= $compiled_profile . "\n";
            if ( $boundaries ) {
                $prompt .= "\n## Boundaries\n{$boundaries}";
            }
            return $prompt;
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
     * Negative ratings = feedback. Positive ratings = praise. Magnitude = weight.
     *
     * Also includes legacy ai_feedback/ai_praises from flow settings
     * for backward compatibility with manually-added entries.
     */
    private function build_feedback_prompt() {
        $sections = [];

        // ── DB-rated entries via Chat Logger (object-cached, no direct $wpdb here) ──
        if ( class_exists( 'FLOSC_Chat_Logger' ) ) {
            $rated = FLOSC_Chat_Logger::instance()->flosc_get_rated_logs( 100 );
            $negatives = array();
            $positives = array();
            foreach ( (array) $rated as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }
                $score = isset( $row['admin_rating'] ) ? (int) $row['admin_rating'] : 0;
                if ( $score < 0 && count( $negatives ) < 20 ) {
                    $negatives[] = $row;
                } elseif ( $score > 0 && count( $positives ) < 20 ) {
                    $positives[] = $row;
                }
            }
            // Strongest first for each polarity.
            usort(
                $negatives,
                static function ( $a, $b ) {
                    return (int) ( $a['admin_rating'] ?? 0 ) <=> (int) ( $b['admin_rating'] ?? 0 );
                }
            );
            usort(
                $positives,
                static function ( $a, $b ) {
                    return (int) ( $b['admin_rating'] ?? 0 ) <=> (int) ( $a['admin_rating'] ?? 0 );
                }
            );

            if ( ! empty( $negatives ) ) {
                $prompt = "## Admin Feedback (Rated Responses)\n";
                $prompt .= "The administrator scored these responses negatively. Avoid this behavior.\n\n";
                foreach ( $negatives as $i => $row ) {
                    $num   = $i + 1;
                    $score = $row['admin_rating'];
                    $prompt .= "### Feedback {$num} (score: {$score}/10)\n";
                    $prompt .= '**User said:** "' . mb_substr( (string) ( $row['user_message'] ?? '' ), 0, 200 ) . "\"\n";
                    $prompt .= '**Your bad response:** "' . mb_substr( (string) ( $row['ai_response'] ?? '' ), 0, 300 ) . "\"\n";
                    if ( ! empty( $row['admin_note'] ) ) {
                        $prompt .= '**Admin note:** ' . $row['admin_note'] . "\n";
                    }
                    $prompt .= "\n";
                }
                $sections[] = $prompt;
            }

            if ( ! empty( $positives ) ) {
                $prompt = "## Admin Praise (Rated Responses)\n";
                $prompt .= "The administrator scored these responses positively. Replicate this quality.\n\n";
                foreach ( $positives as $i => $row ) {
                    $num   = $i + 1;
                    $score = $row['admin_rating'];
                    $prompt .= "### Example {$num} (score: +{$score}/10)\n";
                    $prompt .= '**User said:** "' . mb_substr( (string) ( $row['user_message'] ?? '' ), 0, 200 ) . "\"\n";
                    $prompt .= '**Your excellent response:** "' . mb_substr( (string) ( $row['ai_response'] ?? '' ), 0, 300 ) . "\"\n";
                    if ( ! empty( $row['admin_note'] ) ) {
                        $prompt .= '**Why this was good:** ' . $row['admin_note'] . "\n";
                    }
                    $prompt .= "\n";
                }
                $sections[] = $prompt;
            }
        }

        // ── Legacy manual feedback/praises (backward compat) ──
        $feedback_items = flosc_get_setting('ai_feedback', []);
        if (!empty($feedback_items) && is_array($feedback_items)) {
            $prompt = "## Manual Feedback\n";
            $prompt .= "The following are specific feedback from the administrator.\n\n";
            foreach ($feedback_items as $i => $feedback_item) {
                $num = $i + 1;
                $prompt .= "### Feedback {$num}\n";
                $prompt .= "**When the user says:** \"{$feedback_item['user_message']}\"\n";
                $prompt .= "**Do NOT respond like:** \"{$feedback_item['bad_response']}\"\n";
                $prompt .= "**Issue:** {$feedback_item['admin_note']}\n";
                if (!empty($feedback_item['preferred_response'])) {
                    $prompt .= "**Instead, respond like:** \"{$feedback_item['preferred_response']}\"\n";
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
        $topic_scope = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_topic_scope', '' )
            : flosc_get_setting( 'ai_topic_scope', '' );
        $off_topic_message = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_off_topic_message', '' )
            : flosc_get_setting( 'ai_off_topic_message', '' );
        $off_topic_links = function_exists( 'flosc_personality_library_resolve_field' )
            ? flosc_personality_library_resolve_field( 'ai_off_topic_links', '' )
            : flosc_get_setting( 'ai_off_topic_links', '' );

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
        // Per-flow basket only — mirrors FLOSC_Chatpack::load_knowledge_files so both
        // prompt-builders draw on the same physically-separate, tier-gated files and
        // neither one ever bleeds another flow's content into the prompt.
        $flow_stem = '';
        if (!empty($context['flow_id']) && is_string($context['flow_id'])) {
            $flow_stem = sanitize_key(pathinfo(basename($context['flow_id']), PATHINFO_FILENAME));
        }
        if ($flow_stem === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow) && !empty($flow['id'])) {
                $flow_stem = sanitize_key((string) $flow['id']);
            }
        }
        if ($flow_stem === '' || !function_exists('flosc_knowledge_bases_prompt_text')) {
            return '';
        }
        $user_level = $context['access_level'] ?? (!empty($context['logged_in']) ? 'guest' : 'visitor');
        return flosc_knowledge_bases_prompt_text($flow_stem, $user_level);
    }

    /**
     * Whether AI context represents a full member (member), not a guest.
     *
     * Prefer access_level / is_member. Treat purchased as truthy for bool and
     * common string forms ('Yes', 'true', '1') — never require purchased === 'Yes'
     * alone (that mis-labeled sandbox / meta-granted members as guests).
     *
     * @param array $context AI / session context
     * @return bool
     */
    private function flosc_context_user_is_member( $context ) {
        if ( ! is_array( $context ) ) {
            $context = [];
        }

        $level = strtolower( (string) ( $context['access_level'] ?? '' ) );
        if ( $level === 'member' ) {
            return true;
        }

        if ( ! empty( $context['is_member'] ) ) {
            return true;
        }

        // purchased may be bool true, 1, or strings Yes/true/1 from various builders.
        if ( array_key_exists( 'purchased', $context ) ) {
            $purchased = $context['purchased'];
            if ( $purchased === true || $purchased === 1 || $purchased === '1' ) {
                return true;
            }
            if ( is_string( $purchased ) ) {
                $norm = strtolower( trim( $purchased ) );
                if ( in_array( $norm, [ 'yes', 'true', '1', 'member' ], true ) ) {
                    return true;
                }
            }
        }

        // Backend authority when session is authenticated (covers role/meta grants).
        $user_id = (int) ( $context['user_id'] ?? $context['wp_user_id'] ?? 0 );
        if ( ! $user_id && function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
            $user_id = (int) get_current_user_id();
        }
        if ( $user_id > 0 ) {
            $flow_for_member = (string) ( $context['flow_id'] ?? '' );
            if ( function_exists( 'flosc' ) && is_object( flosc() ) && method_exists( flosc(), 'sale' ) ) {
                $sale = flosc()->sale();
                if ( $sale && method_exists( $sale, 'access' ) ) {
                    $access = $sale->access();
                    if ( $access && method_exists( $access, 'is_member' ) && $access->is_member( $user_id, $flow_for_member ) ) {
                        return true;
                    }
                }
            }
            if ( class_exists( 'FLOSC_Member_Access' ) ) {
                $ma = FLOSC_Member_Access::instance();
                if ( $ma && method_exists( $ma, 'is_member' ) && $ma->is_member( $user_id, $flow_for_member ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build context string from context array
     * v1.9.2: Handle arrays and nested values gracefully
     */
    private function build_context_string($context) {
        if (empty($context)) {
            return '';
        }

        // Re-assert membership so the model never trusts a stale guest label.
        $is_member = $this->flosc_context_user_is_member( $context );
        if ( $is_member ) {
            $context['access_level'] = 'member';
            $context['is_member']    = true;
            // Display-friendly; keep separate from actual-purchase meta when present.
            if ( ! array_key_exists( 'purchased', $context )
                || $context['purchased'] === false
                || $context['purchased'] === 'No'
                || $context['purchased'] === ''
            ) {
                // Has full access even if _flosc_purchased is empty (admin/sandbox grant).
                $context['member_entitlement'] = 'Member (full member access)';
            }
        }

        $lines = [];
        // v1.9.0: Skip keys handled separately in build_system_prompt()
        $skip_keys = ['ivr_guidance'];

        // Put identity fields first so the model sees them before long KB/quiz blocks.
        $priority_keys = [ 'access_level', 'is_member', 'purchased', 'member_entitlement', 'user_name', 'phase', 'logged_in' ];
        foreach ( $priority_keys as $pkey ) {
            if ( ! array_key_exists( $pkey, $context ) ) {
                continue;
            }
            $formatted_value = $this->format_context_value( $context[ $pkey ] );
            if ( $formatted_value === null || $formatted_value === '' ) {
                continue;
            }
            $label   = ucwords( str_replace( '_', ' ', $pkey ) );
            $lines[] = "- **{$label}:** {$formatted_value}";
        }
        if ( $is_member ) {
            $lines[] = '- **User tier for content:** Member — full lesson access. Do NOT treat as guest. Do NOT refuse lesson requests or invent upgrade funnels.';
        }

        foreach ($context as $key => $value) {
            if (in_array($key, $skip_keys, true) || in_array($key, $priority_keys, true)) {
                continue;
            }
            $formatted_value = $this->format_context_value($value);
            if ($formatted_value === null || $formatted_value === '') {
                continue; // Skip null values
            }
            // Format key: flosc_version → Flosc Version, quiz_score → Quiz Score
            $label = ucwords(str_replace('_', ' ', $key));
            $lines[] = "- **{$label}:** {$formatted_value}";
        }

        return implode("\n", $lines);
    }

    /**
     * Format nested context values into readable strings for prompts.
     * Prevents nested arrays from collapsing into literal "Array" strings.
     *
     * @param mixed $value
     * @param int $depth
     * @return string|null
     */
    private function format_context_value($value, $depth = 0) {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_object($value)) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return trim((string) $value);
        }

        if ($depth >= 2) {
            return wp_json_encode($value);
        }

        $parts = [];
        foreach (array_slice($value, 0, 10, true) as $item_key => $item_value) {
            $item = $this->format_context_value($item_value, $depth + 1);
            if ($item === null || $item === '') {
                continue;
            }

            if (is_string($item_key)) {
                $parts[] = $item_key . ': ' . $item;
            } else {
                $parts[] = $item;
            }
        }

        return implode(' | ', $parts);
    }

    /**
     * Get default base system prompt
     */
    private function get_default_base_prompt() {
        $identity = $this->get_floscflow_identity();
        $assistant = function_exists( 'flosc_personality_name' )
            ? flosc_personality_name()
            : 'FLOSC';
        $public_title = function_exists( 'flosc_flow_public_title' )
            ? flosc_flow_public_title()
            : trim( (string) ( $identity['title'] ?? '' ) );
        $line = "You are {$assistant}, the AI assistant";
        if ( $public_title !== '' ) {
            $line .= " for {$public_title}";
        }
        $line .= ". Your mission is to help users learn and improve through personalized guidance and encouragement. Be helpful, friendly, specific, and action-oriented. Always reference the user's quiz results and progress when available.";
        return $line;
    }

    /**
     * Get AI Response
     * @param bool $test_mode If true, return WP_Error on failure instead of falling back to IVR
     */
    public function get_response($message, $system_prompt = '', $context = [], $test_mode = false, $return_errors = false) {
        $this->last_billing_meta = [];

        // v5.0.2 FIX: Read provider fresh at call time so flow context (set by handle_chat
        // or ajax_test_ai_connection) is respected. Constructor runs at plugin init before
        // any flow context exists, so $this->provider is always stale/empty.
        $provider = flosc_get_setting('ai_provider', 'ivr');
        if ( empty( $provider ) ) $provider = 'ivr';

        // v1.9.2: Never cache for admin users — admin is testing/debugging and needs fresh responses.
        // Also skip cache in test mode.
        // v8.0.0 token integrity: disable cache for visitors so each visitor turn
        // captures fresh billing metadata; shared visitor cache hits (user_id=0)
        // can otherwise bypass billing capture and force 1-token fallback charges.
        $is_admin = is_user_logged_in() && current_user_can('manage_options');
        $is_visitor = !is_user_logged_in();
        $use_cache = !$test_mode && !$is_admin && !$is_visitor;

        if ($use_cache) {
            // Include user_id in cache key so different users never share cached responses
            $user_id = get_current_user_id();
            $context_hash = !empty($context) ? md5(wp_json_encode($context)) : '';
            $cache_key = 'flosc_ai_' . md5($provider . $message . $system_prompt . $context_hash . $user_id);
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
            $response = $this->call_provider($provider, $message, $system_prompt, $context, $test_mode || $return_errors);
        }

        // v1.9.2: Reduced cache TTL from 1 hour to 5 minutes.
        // AI responses are dynamic and context-dependent — long caches cause stale/wrong responses.
        // Fix 1: Response validation — correct wrong acronym expansions before caching/returning
        if ($response && !is_wp_error($response)) {
            $response = $this->validate_ai_response($response);
        }

        if ($use_cache && $response && !is_wp_error($response)) {
            set_transient($cache_key, $response, 5 * MINUTE_IN_SECONDS);
        }

        // Fix 3: Full debug logging — every interaction logged when FLOSC_DEBUG is true
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            $log = get_option('flosc_debug_log', []);
            $log[] = [
                'ts'       => current_time('mysql'),
                'provider' => $provider,
                'prompt'   => $system_prompt,
                'message'  => $message,
                'response' => is_wp_error($response) ? 'WP_Error: ' . $response->get_error_message() : $response,
            ];
            // Keep last 200 entries
            if (count($log) > 200) {
                $log = array_slice($log, -200);
            }
            update_option('flosc_debug_log', $log);
        }

        return $response;
    }

    /**
     * Production dispatch with an explicit, inspectable outcome.
     *
     * Provider details remain internal; callers decide visitor copy, logging, and
     * administrator diagnostics separately.
     *
     * @return array{content:string,source:string,provider:string,error_code:string,error:string}
     */
    public function get_response_result($message, $system_prompt = '', $context = []) {
        $provider = (string) flosc_get_setting('ai_provider', 'ivr');
        $response = $this->get_response($message, $system_prompt, $context, false, true);

        if (is_wp_error($response)) {
            return [
                'content'    => '',
                'source'     => 'fallback',
                'provider'   => sanitize_key($provider),
                'error_code' => sanitize_key((string) $response->get_error_code()),
                'error'      => sanitize_text_field($response->get_error_message()),
            ];
        }

        $content = is_string($response) ? trim($response) : '';
        return [
            'content'    => $content,
            'source'     => $content !== '' ? 'ai' : 'fallback',
            'provider'   => sanitize_key($provider),
            'error_code' => $content !== '' ? '' : 'flosc_empty_ai_response',
            'error'      => $content !== '' ? '' : __('The provider returned an empty response.', 'flosc'),
        ];
    }

    /**
     * Get billing metadata for the most recent provider call.
     */
    public function get_last_billing_meta() {
        return is_array($this->last_billing_meta) ? $this->last_billing_meta : [];
    }

    /**
     * Capture normalized billing metrics from provider responses.
     */
    private function capture_billing_meta($provider, $model, $usage = [], $raw = []) {
        $usage = is_array($usage) ? $usage : [];
        $raw = is_array($raw) ? $raw : [];

        $input_tokens = intval($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
        $output_tokens = intval($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
        $total_tokens = intval($usage['total_tokens'] ?? ($input_tokens + $output_tokens));

        $real_millicents = 0;
        $source = 'none';

        // Preferred: provider-reported cost fields when available.
        $usd_cost = null;
        if (isset($usage['cost_usd'])) {
            $usd_cost = floatval($usage['cost_usd']);
        } elseif (isset($usage['total_cost_usd'])) {
            $usd_cost = floatval($usage['total_cost_usd']);
        } elseif (isset($raw['cost_usd'])) {
            $usd_cost = floatval($raw['cost_usd']);
        } elseif (isset($raw['total_cost_usd'])) {
            $usd_cost = floatval($raw['total_cost_usd']);
        }

        if ($usd_cost !== null && $usd_cost > 0) {
            $real_millicents = max(1, intval(round($usd_cost * 100000)));
            $source = 'provider_cost';
        } else {
            // Fallback: provider-reported token COUNTS × real price-per-1M. Providers
            // (Anthropic/OpenAI/xAI) report token usage, not cost — that is the hook.
            // A per-provider setting override wins; otherwise a seeded per-model real
            // price is used so real cost is never zero (which was silently forcing flat).
            $price = $this->resolve_model_price_per_1m($provider, (string) $model);
            $input_rate = $price['input'];
            $output_rate = $price['output'];

            if (($input_tokens > 0 || $output_tokens > 0) && ($input_rate > 0 || $output_rate > 0)) {
                $input_cost = ($input_tokens * $input_rate) / 1000000;
                $output_cost = ($output_tokens * $output_rate) / 1000000;
                $real_millicents = max(1, intval(ceil($input_cost + $output_cost)));
                $source = 'token_rates';
            }
        }

        $this->last_billing_meta = [
            'provider' => (string) $provider,
            'model' => (string) $model,
            'usage' => [
                'input_tokens' => $input_tokens,
                'output_tokens' => $output_tokens,
                'total_tokens' => $total_tokens,
            ],
            'real_millicents' => $real_millicents,
            'source' => $source,
        ];
    }

    /**
     * Real price per 1,000,000 tokens, in millicents ($1 = 100,000 millicents), by model.
     *
     * A per-provider setting override (ai_billing_{provider}_input/output_millicents_per_1m)
     * wins when set; otherwise a seeded per-model real price is used so cost never resolves
     * to zero. Matched by keyword on the model id so id variants (dates/suffixes) resolve.
     * Overridable via the 'flosc_model_price_millicents_per_1m' filter.
     *
     * @param string $provider Provider slug.
     * @param string $model    Model id.
     * @return array {input:int, output:int} millicents per 1M tokens.
     */
    private function resolve_model_price_per_1m($provider, $model) {
        $override_in  = max(0, intval(flosc_get_setting('ai_billing_' . $provider . '_input_millicents_per_1m', 0)));
        $override_out = max(0, intval(flosc_get_setting('ai_billing_' . $provider . '_output_millicents_per_1m', 0)));
        if ($override_in > 0 || $override_out > 0) {
            return array('input' => $override_in, 'output' => $override_out);
        }

        $m = strtolower((string) $model);
        $seed = array('input' => 300000, 'output' => 1500000); // conservative default (Sonnet-tier)
        if (strpos($m, 'haiku') !== false) {
            $seed = array('input' => 100000, 'output' => 500000);    // ~$1 / $5 per 1M
        } elseif (strpos($m, 'opus') !== false) {
            $seed = array('input' => 500000, 'output' => 2500000);   // ~$5 / $25 per 1M
        } elseif (strpos($m, 'sonnet') !== false) {
            $seed = array('input' => 300000, 'output' => 1500000);   // ~$3 / $15 per 1M
        } elseif (strpos($m, '4o-mini') !== false) {
            $seed = array('input' => 15000, 'output' => 60000);      // ~$0.15 / $0.60 per 1M
        } elseif (strpos($m, 'gpt') !== false || strpos($m, '4o') !== false) {
            $seed = array('input' => 250000, 'output' => 1000000);   // ~$2.50 / $10 per 1M
        } elseif (strpos($m, 'grok') !== false) {
            $seed = array('input' => 300000, 'output' => 1500000);   // ~$3 / $15 per 1M (override in settings)
        }

        $seed = apply_filters('flosc_model_price_millicents_per_1m', $seed, $model, $provider);
        return array(
            'input'  => max(0, intval($seed['input'] ?? 0)),
            'output' => max(0, intval($seed['output'] ?? 0)),
        );
    }

    /**
     * One hop: IVR locally; OpenAI/Anthropic/Gemini via WordPress AI Client; xAI via FLOSC HTTP.
     */
    private function call_provider($provider, $message, $system_prompt, $context, $test_mode) {
        switch ($provider) {
            case 'openai':    return $this->openai_request($message, $system_prompt, $context, $test_mode);
            case 'anthropic': return $this->anthropic_request($message, $system_prompt, $context, $test_mode);
            case 'xai':       return $this->xai_request($message, $system_prompt, $context, $test_mode);
            case 'gemini':    return $this->gemini_request($message, $system_prompt, $context, $test_mode);
            case 'ivr':       return $this->ivr_response($message); // Explicit IVR mode — correct
            default:
                // v1.9.3: Unknown/misconfigured provider — return null, not silent IVR
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC: Unknown AI provider: ' . $provider);
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
        $name = function_exists( 'flosc_visitor_assistant_name' )
            ? flosc_visitor_assistant_name()
            : (string) ( $identity['name'] ?? '' );
        if ( $name === '' ) {
            $name = 'this app';
        }

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
     * OpenAI, Anthropic, and Gemini chat: WordPress 7.0 AI Client.
     * Official provider plugins own the vendor HTTP. FLOSC binds this flow's key.
     */
    private function wp_ai_chat_request($provider, $message, $system_prompt, $context = [], $test_mode = false) {
        $model_keys = array(
            'openai'    => array('ai_openai_model', 'gpt-4o-mini'),
            'anthropic' => array('ai_anthropic_model', 'claude-sonnet-4-5-20250929'),
            'gemini'    => array('ai_gemini_model', 'gemini-2.5-flash'),
        );
        $model_pair = isset($model_keys[$provider]) ? $model_keys[$provider] : array('ai_openai_model', 'gpt-4o-mini');
        $model = (string) flosc_get_setting($model_pair[0], $model_pair[1]);
        if ($model === '') {
            $model = $model_pair[1];
        }
        $temperature = (float) flosc_get_setting('ai_temperature', '0.3');
        $max_tokens = (int) flosc_get_setting('ai_max_tokens', '500');

        if (!class_exists('FLOSC_WP_AI_Client')) {
            if ($test_mode) {
                return new WP_Error('flosc_wp_ai_missing_core', 'FLOSC WordPress AI Client wrapper is not loaded.');
            }
            return null;
        }

        $result = FLOSC_WP_AI_Client::generate(array(
            'provider'      => $provider,
            'message'       => $message,
            'system_prompt' => $system_prompt,
            'history'       => is_array($context) ? $context : array(),
            'model'         => $model,
            'temperature'   => $temperature,
            'max_tokens'    => $max_tokens,
            'test_mode'     => $test_mode,
        ));

        if (is_wp_error($result)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('FLOSC WP AI Client (' . $provider . '): ' . $result->get_error_message());
            }
            if ($test_mode) {
                return $result;
            }
            return null;
        }

        $used_model = (string) ($result['model'] ?? $model);
        $usage = isset($result['usage']) && is_array($result['usage']) ? $result['usage'] : array();
        $this->capture_billing_meta($provider, $used_model, $usage, array());

        $text = isset($result['text']) ? (string) $result['text'] : '';
        return $text !== '' ? $text : null;
    }

    /**
     * OpenAI chat via WordPress AI Client + AI Provider for OpenAI.
     */
    private function openai_request($message, $system_prompt, $context = [], $test_mode = false) {
        return $this->wp_ai_chat_request('openai', $message, $system_prompt, $context, $test_mode);
    }
    
    /**
     * Anthropic Claude via WordPress AI Client + AI Provider for Anthropic.
     */
    private function anthropic_request($message, $system_prompt, $context = [], $test_mode = false) {
        return $this->wp_ai_chat_request('anthropic', $message, $system_prompt, $context, $test_mode);
    }
    
    /**
     * xAI Grok
     */
    private function xai_request($message, $system_prompt, $context = [], $test_mode = false) {
        // v1.9.0: Use flosc_get_setting() — reads flow settings first
        $api_key = function_exists( 'flosc_get_provider_api_key' ) ? flosc_get_provider_api_key( 'xai' ) : flosc_get_setting( 'xai_api_key', '' );

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
        // Default tracks current xAI chat flagship (see docs.x.ai/developers/models).
        $model = (string) flosc_get_setting('ai_xai_model', 'grok-4.5');
        if ($model === '') {
            $model = 'grok-4.5';
        }
        // Retired slugs still stored on older installs — remap so chat works without re-save.
        $flosc_xai_legacy = [
            'grok-2-latest'    => 'grok-4.5',
            'grok-2'           => 'grok-4.5',
            'grok-2-1212'      => 'grok-4.5',
            'grok-beta'        => 'grok-4.5',
            'grok-vision-beta' => 'grok-4.5',
        ];
        if (isset($flosc_xai_legacy[$model])) {
            $model = $flosc_xai_legacy[$model];
        }
        $temperature = (float) flosc_get_setting('ai_temperature', '0.3');
        $max_tokens = (int) flosc_get_setting('ai_max_tokens', '500');

        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration -- WordPress has no official xAI provider plugin yet, so Grok chat is FLOSC-owned. Service declared in readme.txt External Services.
        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $max_tokens,
                'temperature' => $temperature,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC xAI Error: ' . $response->get_error_message());
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
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC xAI API Error: ' . wp_json_encode($body['error']));
            if ($test_mode) {
                $error_msg = is_string($body['error']) ? $body['error'] : ($body['error']['message'] ?? 'Unknown error');

                $help_text = "\n\n📝 Next steps:\n";

                if (strpos($error_msg, 'authentication') !== false || strpos($error_msg, 'invalid') !== false || strpos($error_msg, 'Unauthorized') !== false) {
                    $help_text .= "1. Your API key appears to be invalid\n";
                    $help_text .= "2. Go to https://console.x.ai\n";
                    $help_text .= "3. Create a new API key\n";
                    $help_text .= "4. Replace the old key with the new one above\n";
                    $help_text .= "5. Make sure you copied the entire key (starts with xai-...)";
                } elseif (stripos($error_msg, 'model not found') !== false || stripos($error_msg, 'does not exist') !== false) {
                    $help_text .= "1. The model ID is retired or not enabled on your xAI account (requested: {$model})\n";
                    $help_text .= "2. AI tab → xAI Model → choose Grok 4.5 (or another current ID)\n";
                    $help_text .= "3. Click Save Settings, then Test again\n";
                    $help_text .= "4. Model catalog: https://docs.x.ai/developers/models";
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

        $this->capture_billing_meta('xai', $model, $body['usage'] ?? [], $body);

        return $body['choices'][0]['message']['content'] ?? null;
    }

    /**
     * Gemini chat via WordPress AI Client + AI Provider for Google.
     */
    private function gemini_request($message, $system_prompt, $context = [], $test_mode = false) {
        return $this->wp_ai_chat_request('gemini', $message, $system_prompt, $context, $test_mode);
    }

    private function validate_ai_response($response) {
        if (empty($response)) return $response;
        $feedback_log = [];

        // Correct wrong FLOSC expansions
        if (preg_match('/FLOSC\s+stands?\s+for\b/i', $response)) {
            if (!preg_match('/Freeline.*Login.*Offer.*Sale.*Content/i', $response)) {
                $original = $response;
                $response = preg_replace(
                    '/FLOSC\s+stands?\s+for[^.!?\n]*/i',
                    'FLOSC stands for Freeline, Login, Offer, Sale, Content',
                    $response
                );
                if ($response !== $original) {
                    $feedback_log[] = 'Corrected wrong FLOSC expansion';
                }
            }
        }

        // Log feedback
        if (!empty($feedback_log)) {
            $log = get_option('flosc_validation_feedback', []);
            $log[] = [
                'timestamp'   => current_time('mysql'),
                'feedback' => $feedback_log,
            ];
            update_option('flosc_validation_feedback', $log);
        }

        return $response;
    }

    /**
     * Get FloscFlow Identity (helper)
     */
    private function get_floscflow_identity() {
        $currency = flosc_get_setting('currency', 'EUR');
        $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        $fw_id = [];
        if ( function_exists( 'flosc' ) && is_object( flosc() ) && method_exists( flosc(), 'get_floscflow_identity' ) ) {
            $fw_id = flosc()->get_floscflow_identity();
        }
        if ( ! is_array( $fw_id ) ) {
            $fw_id = [];
        }
        $assistant = function_exists( 'flosc_personality_name' )
            ? flosc_personality_name()
            : '';

        return [
            'name' => $assistant !== '' ? $assistant : 'FLOSC App',
            'title' => trim( (string) ( $fw_id['title'] ?? flosc_get_setting( 'title', '' ) ) ),
            'tagline' => trim( (string) ( $fw_id['tagline'] ?? flosc_get_setting( 'tagline', '' ) ) ),
            'price' => flosc_get_setting('product_price', ''),
            'currency_symbol' => $symbols[$currency] ?? $currency,
        ];
    }
}
