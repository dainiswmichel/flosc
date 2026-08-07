<?php if (!defined('ABSPATH')) exit; // Part 5: Glossary — FLOSC Documentation ?>

<h1 id="glossary">Part 5: Glossary</h1>
<p>Every FLOSC-specific term defined once. When a term appears in the codebase or documentation, its definition is here.</p>

<h2 id="glossary-a">A</h2>

<h3 id="term-access-controller">Access Controller</h3>
<p><code>class-flosc-rag-access-controller.php</code>. The class that decides what content the AI is permitted to deliver to the current user. Enforces lesson access rules: visitors get nothing, guests get the free lesson, members get all lessons.</p>

<h3 id="term-access-level">Access Level</h3>
<p>A numeric tier stored as user meta that controls which FLOSC content a user may access. Level 0 = Visitor, Level 1 = Guest, Level 2 = Member (purchased). Also used for token-based systems with higher levels for premium tiers.</p>

<h3 id="term-access-manager">Access Manager (Sale)</h3>
<p><code>includes/sale/class-access-manager.php</code>. Grants and revokes member access after a successful purchase. Updates user meta, logs the transaction, triggers post-purchase hooks.</p>

<h3 id="term-admin-introspection">Admin Introspection</h3>
<p>A verification mechanism that checks whether the AI "knows" what FLOSC product it is operating for. The <code>check_admin_introspection()</code> method builds an <code>adminVerification</code> object (IVR file name, app slug, product name, tagline, domain) included in the FLOSC_USER config object sent to the JavaScript client. Used as a self-consistency check.</p>

<h3 id="term-ai-provider">AI Provider</h3>
<p>One of: <code>anthropic</code>, <code>openai</code>, <code>xai</code>, or <code>ivr</code>. Configurable per flow from the admin AI Configuration tab. When set to <code>ivr</code>, no external AI API is called — all responses come from the IVR script. When set to an AI provider, IVR still runs first and AI only handles unmatched messages.</p>

<h3 id="term-anthropic">Anthropic (Claude)</h3>
<p>The default AI provider for FLOSC. Uses the Claude API. Model is configurable (claude-sonnet-4-6, claude-opus-4-6, etc.). API key stored in WordPress options, never exposed to the client. All prompts are assembled server-side before the API call.</p>

<h3 id="term-app-slug">App Slug</h3>
<p>The URL segment at which the FLOSC app lives, e.g. <code>learn</code> makes the app accessible at <code>yoursite.com/learn</code>. Configurable per flow. WordPress rewrite rules redirect this slug to the virtual page rendered by <code>render_flosc_app()</code>. Changing the slug triggers an automatic permalink flush.</p>

<h3 id="term-audio-quiz">Audio Quiz</h3>
<p><code>class-flosc-sample-audio-quiz.php</code>. A quiz type that requires microphone access and speech-to-text transcription. Currently in the Quiz Deck but non-functional (cannot be enabled) pending STT pipeline implementation. Shows an amber warning in the admin: "Requires microphone + speech-to-text — not yet functional."</p>

<h3 id="term-autoprompt">Autoprompt / Autoprompt Pill</h3>
<p>A pre-configured button rendered in the chat interface that sends a message when clicked. Configured per user state (visitor, guest, member). Each pill has: a label, a user_input message to send, an optional icon, and optionally a trigger (show an offer, fire an action). The admin configures pills in the AutoPrompts tab. In Admin Test Mode, all three state pill sets are shown simultaneously.</p>

<h2 id="glossary-b">B</h2>

<h3 id="term-bridge-data">Bridge Data</h3>
<p>Client-side storage of quiz results before login. When a visitor completes the quiz, their score and answers are stored in the browser (localStorage/sessionStorage). When they create an account, this data is "bridged" to the new user account via the <code>/flosc/v1/funnel-complete</code> REST endpoint. Prevents losing quiz context during the login flow.</p>

<h3 id="term-bridge-data-manager">Bridge Data Manager</h3>
<p><code>class-bridge-data-manager.php</code>. Server-side component that receives, stores, and retrieves bridge data. Manages the handoff of quiz scores from anonymous sessions to registered users.</p>

<h3 id="term-build-context">build_context()</h3>
<p>A method in the main FLOSC class that assembles the complete context array passed to AI chat dispatch and IVR evaluation. Includes: user ID, user state, quiz results, flow ID, phase, purchased status, and other variables used by condition evaluation.</p>

<h2 id="glossary-c">C</h2>

<h3 id="term-carousel">Carousel (Autoprompt)</h3>
<p>A display mode for autoprompt pills where they scroll horizontally in a row rather than wrapping to multiple lines. Configured as <code>display_style: carousel</code> on a pill set.</p>

<h3 id="term-chat-bubble">Chat Bubble</h3>
<p>An individual message in the chat interface, rendered as a styled HTML element. User messages appear right-aligned; assistant messages appear left-aligned. The chat style system controls bubble geometry, colors, font, and scale through structured admin settings and CSS variables.</p>

<h3 id="term-chat-handler">Chat Handler</h3>
<p>The server-side class that processes a chat message through the full pipeline: IVR evaluation → AI fallback → response validation → tool execution → response formatting. <code>class-flosc-rag-chat-handler.php</code> is the primary implementation.</p>

<h3 id="term-chat-log">Chat Log</h3>
<p>A record of a single user message + assistant response stored in the custom <code>flosc_chat_logs</code> database table. Includes: message text, response text, user phase, timestamp, and a rating field (-10 to +10) that admins can set from the Chat Logs admin page.</p>

<h3 id="term-chat-style-preset">Chat Style Preset</h3>
<p>A named structured theme for the chat interface. Presets such as Auto, Light, and Dark set the baseline surface, while the admin's structured controls refine bubble geometry, accent color, font, and scale. The result is the FLOSC Signature Template: consistent, guided, and not a freeform style override.</p>

<h3 id="term-chatpack">ChatPack</h3>
<p>The complete structured context payload sent to the AI on the first message of a session. Built by <code>class-flosc-chatpack.php</code>. Contains sections: Identity, WordPress context, User state, Flow configuration, Knowledge base, IVR messages, and Behavioral rules. Subsequent messages send a slimmer "follow-up pack" instead of the full chatpack, to minimize token usage.</p>

<h3 id="term-clickbank">ClickBank</h3>
<p>One of the supported payment providers in FLOSC's sale system. Handles affiliate-tracked sales via ClickBank's IPN (Instant Payment Notification) webhook. Configured via <code>class-clickbank-provider.php</code>.</p>

<h3 id="term-companion-widget">Companion Widget</h3>
<p>A persistent chat button and panel rendered outside the main FLOSC app, available on any page of the WordPress site via the <code>[flosc_companion]</code> shortcode or the Companion Widget admin settings. The companion renders in an iframe pointing to the main FLOSC app URL, so it shares the same session and state.</p>

<h3 id="term-condition">Condition (IVR)</h3>
<p>A boolean expression in the IVR markdown file that determines whether a message fires. Conditions reference variables from the user context: <code>user.state</code>, <code>score</code>, <code>purchased</code>, <code>flow_id</code>, and ~35 other whitelisted variables. Conditions can use AND/OR operators, comparison operators (<code>==</code>, <code>!=</code>, <code>&gt;</code>, <code>&lt;</code>, <code>&gt;=</code>, <code>&lt;=</code>), and array membership checks (<code>in</code>).</p>

<h3 id="term-condition-evaluator">Condition Evaluator</h3>
<p><code>class-condition-evaluator.php</code>. Safely evaluates IVR condition expressions against the user context. Uses a whitelist of allowed variables (preventing arbitrary PHP execution) and a custom expression parser rather than <code>eval()</code>.</p>

<h3 id="term-content-filter">Content Filter</h3>
<p><code>class-content-filter.php</code>. Filters WordPress query results based on FLOSC access rules. Intercepts <code>pre_get_posts</code> and <code>the_posts</code> filters to hide protected lessons from users without the appropriate access level.</p>

<h3 id="term-content-protection">Content Protection</h3>
<p><code>class-content-protection.php</code>. Manages post-level visibility tiers. WordPress posts can be marked with a <code>_flosc_visibility</code> meta field that FLOSC respects when deciding whether to show the post to the current user. Categories can be marked as FLOSC-protected via the admin UI.</p>

<h3 id="term-feedback">Feedback (AI Feedback)</h3>
<p>Admin-written examples of AI responses that were wrong, with the correct response. Stored in WordPress options and injected into the AI system prompt as negative examples. The AI is instructed to avoid repeating the corrected mistakes. Configured from the AI Feedback admin page.</p>

<h3 id="term-custom-domain">Custom Domain</h3>
<p>A domain mapping that points to a specific FLOSC flow. Configured in the Flow editor. When a request arrives at a custom domain, <code>handle_custom_domain()</code> identifies the corresponding flow and sets it as the active flow for that request, even if the WordPress install lives at a different domain.</p>

<h2 id="glossary-d">D</h2>

<h3 id="term-dispatch">Dispatch (AI Chat)</h3>
<p>The process of deciding how to respond to a chat message. FLOSC dispatch order: (1) Check IVR messages for a matching condition → return if found; (2) Call AI provider with full context → return AI response. The dispatch class is <code>class-ai-chat-dispatch.php</code>.</p>

<h2 id="glossary-e">E</h2>

<h3 id="term-email-automation">Email Automation</h3>
<p>Scheduled or event-triggered emails sent to users via WordPress's mail system. Configured from the Email admin page. Triggers include: quiz completed, post-purchase congratulations, encouragement (for users who haven't returned), re-engagement (for lapsed users), and weekly summary.</p>

<h3 id="term-email-template">Email Template</h3>
<p>An admin-configurable email body with template variables (e.g., <code>{user_name}</code>, <code>{score}</code>, <code>{oto_offer_link}</code>) that are replaced at send time. Separate templates exist for each automation trigger.</p>

<h2 id="glossary-f">F</h2>

<h3 id="term-flosc">FLOSC</h3>
<p>Acronym: Freeline → Login → Offer → Sale → Content. A WordPress plugin that turns a quiz-based learning experience into a full conversational sales and education flow. See The Five Phases in Part 1.</p>

<h3 id="term-flosc-admin">FloscAdmin</h3>
<p>The WordPress user with <code>manage_options</code> capability who configures FLOSC. All admin pages check this capability. FloscAdmin is not a custom role — it uses the standard WordPress Administrator role.</p>

<h3 id="term-flosc-config">FLOSC_CONFIG</h3>
<p>The JavaScript configuration object passed to the frontend via <code>wp_localize_script()</code>. Contains: REST URL, nonce, Stripe publishable key, PayPal client ID, IVR messages, offers, SSO providers, admin autoprompts, quiz settings, chat style, product branding, and (for admins) the full offer list for test mode. Everything the JS app needs to render.</p>

<h3 id="term-flosc-user">FLOSC_USER</h3>
<p>The JavaScript user object passed to the frontend alongside FLOSC_CONFIG. Contains: user ID, display name, email, state (visitor/guest/member), purchased flag, access level, token balance, free lesson delivered flag, quiz answers, and the adminVerification object for admin users.</p>

<h3 id="term-flow">Flow</h3>
<p>One independent FLOSC product instance. A flow has its own IVR file, AI configuration, quiz settings, offer set, branding (name, tagline, emoji, colors, logo), domain mapping, and app slug. Multiple flows can run from one WordPress installation. Stored in the <code>flosc_flows</code> WordPress option.</p>

<h3 id="term-flow-manager">Flow Manager</h3>
<p><code>class-flow-manager.php</code>. Manages CRUD operations for flows. Provides <code>get_all_flows()</code>, <code>get_flow()</code>, <code>get_flow_by_slug()</code>, <code>get_flow_by_domain()</code>, and the user-flow access control logic.</p>

<h3 id="term-flow-settings">Flow Settings</h3>
<p>Per-flow overrides stored in a WordPress option keyed as <code>flosc_flow_{ivr_slug}</code>. When a setting is read via <code>flosc_get_setting()</code>, flow-level settings take precedence over global settings. This is how two flows can have different AI models, quiz content, or brand colors without conflicts.</p>

<h3 id="term-free-lesson">Free Lesson</h3>
<p>One lesson delivered to a Guest (logged-in, non-purchased) user based on their quiz results. The free lesson is the most powerful conversion tool in the flow — it shows the quality of the content before asking for payment. Configured as a lesson group tied to quiz performance. <code>class-free-lesson-manager.php</code> handles delivery.</p>

<h3 id="term-freeline">FREELINE Phase</h3>
<p>The phase a visitor is in before logging in. The "freeline" gives the user something of value (the quiz) before asking for anything. IVR messages in the freeline phase are designed to generate interest and drive toward the Login phase.</p>

<h2 id="glossary-g">G</h2>

<h3 id="term-guest">Guest</h3>
<p>A user who has created an account but has not purchased. In code: <code>is_user_logged_in() && !$purchased</code>. Guests have access to their quiz results, the free lesson, and upgrade offers. They do not have access to the full lesson library.</p>

<h3 id="term-guest-access">Guest Access</h3>
<p>The limited content access level granted to logged-in non-purchased users. Grants: quiz result details, one free lesson, AI conversation with purchase prompts. Does not grant: full lesson library, member-phase IVR messages.</p>

<h3 id="term-guest-session">Guest Session</h3>
<p>A server-side session record for a logged-in guest. Stores conversation history, quiz state, and free lesson delivery flag. Separate from WordPress's own session — managed by <code>class-session-manager.php</code>.</p>

<h2 id="glossary-h">H</h2>

<h3 id="term-hmac">HMAC (Hash-Based Message Authentication Code)</h3>
<p>The cryptographic primitive used to sign FLOSC cookies. A cookie containing user state data is accompanied by an HMAC signature computed using the WordPress secret key. On every request, the signature is recomputed and compared — if it doesn't match, the cookie is rejected. This prevents cookie tampering without a database lookup.</p>

<h2 id="glossary-i">I</h2>

<h3 id="term-intent">Intent (Payment)</h3>
<p>A server-side record of a user's intention to purchase a specific offer before payment is captured. Used by Stripe's Payment Intents API. The intent is created server-side, returned to the client with a client secret, and confirmed by Stripe after the user completes payment. Prevents price manipulation — the price is set server-side.</p>

<h3 id="term-ipa">IPA (International Phonetic Alphabet)</h3>
<p>The standard phonetic notation system used in FLOSC's pronunciation content. Lesson content may include IPA symbols (e.g., /æ/, /r/, /θ/, /ð/) to describe sounds precisely. The pronunciation analyzer uses IPA as a common representation for comparing expected and actual pronunciations.</p>

<h3 id="term-ivr">IVR (Interactive Voice Response)</h3>
<p>In FLOSC: a library of scripted responses that the chatbot delivers based on conditions, without calling the AI API. Named after telecom IVR (phone tree) systems. The IVR-first dispatch model means scripted responses are always preferred over AI-generated ones — they are faster, cheaper, and fully under admin control. See Part 1: The IVR Inspiration.</p>

<h3 id="term-ivr-config-file">IVR Config File</h3>
<p>A Markdown file (e.g., <code>{flowname}_ivr.md</code>) in the <code>ai_configuration_files/</code> directory that defines all scripted responses for a flow. Each flow has one IVR file. The file is parsed by <code>class-ivr-parser.php</code> and the resulting message tree is stored in the database for fast lookups.</p>

<h3 id="term-ivr-message">IVR Message</h3>
<p>One scripted response in the IVR config file. A message has: a phase, a name, a trigger condition, optional autoprompt pills, optional offer triggers, a style, and the message content (Markdown with template variables). The condition is evaluated against the current user context at dispatch time.</p>

<h3 id="term-ivr-phase">IVR Phase</h3>
<p>The section of the IVR file a message belongs to: <code>freeline</code>, <code>login</code>, <code>offer</code>, <code>sale</code>, or <code>content</code>. Mapped from section headers in the Markdown file (<code># Freeline Messages</code>, <code># Guest Messages</code> → login phase, <code># Member Messages</code> → content phase).</p>

<h3 id="term-ivr-style">IVR Style</h3>
<p>A named CSS class applied to an IVR message's chat bubble. Styles are defined in the IVR file's style block and applied to messages that reference them. Allows some messages (e.g., offers, special announcements) to render with distinct visual treatment.</p>

<h2 id="glossary-k">K</h2>

<h3 id="term-knowledge-base">Knowledge Base</h3>
<p>The collection of files and WordPress content that the AI draws on when answering questions. Includes: the orientation file (general product knowledge), uploaded knowledge files, and the current lesson catalog. Loaded and indexed by <code>class-rag-manager.php</code>.</p>

<h3 id="term-knowledge-file">Knowledge File</h3>
<p>A text or Markdown file uploaded through the AI Knowledge admin page that is included in the AI system prompt. Can contain product information, FAQs, lesson catalog details, or any background information the AI should know. Multiple files are supported; they are concatenated into the knowledge section of the chatpack.</p>

<h2 id="glossary-l">L</h2>

<h3 id="term-lesaep">LeSAEp</h3>
<p><strong>Learn Excellent Standard American English Pronunciation.</strong> A real-world FLOSC deployment used as a worked example in this documentation — not a hard requirement of the framework. Assesses 10 core American English pronunciation challenges (short-a vowel, rhotic R, voiceless/voiced TH, short-i/long-e, schwa, flap-T, word stress, connected speech, dark L). Lesson posts typically live in a WordPress category the site admin chooses (e.g. category slug <code>lesaep</code>). Members who purchase full access are often labeled with a site-defined member level (e.g. “LeSAEp Learners”) in flow settings — those names are configuration, not FLOSC core.</p>

<h3 id="term-lesson">Lesson</h3>
<p>A WordPress post (standard <code>post</code> type) assigned to a FLOSC-configured lesson category. Lessons are the primary content delivered to members. The post title, content, and tags are indexed by FLOSC for search and recommendation. Lessons are delivered via AI tool calls, not via direct links — the AI delivers the lesson text inline in the conversation.</p>

<h3 id="term-lesson-number">Lesson Number</h3>
<p>A numeric post meta field (<code>_flosc_lesson_number</code>) that maps a WordPress post to a quiz item. Lesson 1 maps to quiz item 1. The pronunciation quiz returns incorrect item numbers; FLOSC looks up the corresponding lesson by number. This field is set when sample data is created, or manually by the admin.</p>

<h3 id="term-login-token">Login Token</h3>
<p>A one-time URL token that authenticates a user without a password, used for cross-domain login scenarios. Generated server-side, stored as a transient with a short TTL, consumed on first use. Handled by <code>handle_login_token()</code>.</p>

<h2 id="glossary-m">M</h2>

<h3 id="term-member">Member</h3>
<p>A user who has purchased and has full content access. In code: <code>is_user_logged_in() && $purchased</code>. Members receive the MEMBER phase IVR messages, have access to all lessons, and get the full AI tutor experience with their quiz history in context.</p>

<h3 id="term-member-level">Member Level</h3>
<p>A numeric value stored as user meta that can distinguish between different tiers of membership (e.g., monthly vs. annual). Level 1 = basic member, Level 2+ = premium tiers. Used for multi-tier access control where different offers unlock different content sets.</p>

<h3 id="term-member-phase">MEMBER Phase</h3>
<p>The FLOSC flow phase for logged-in, purchased users. Corresponds to the "Content" (C) phase of F→L→O→S→C. IVR messages in this phase focus on lesson delivery, progress encouragement, and upsell to higher tiers.</p>

<h3 id="term-michel-date-stamp">Michel Date Stamp Innovation</h3>
<p>A date format used throughout FLOSC documentation and file naming: <code>YYYY-MMm-DDd</code>, e.g., <code>2026-03m-02d</code>. The redundant letter suffixes (m for month, d for day) make the format unambiguous and human-readable without a key. Also exists in timestamp form with time: <code>2026-03m-02d-T14h30m00s</code>. Used in devnotes file names, session status reports, and code comments.</p>

<h2 id="glossary-n">N</h2>

<h3 id="term-nonce">Nonce</h3>
<p>A WordPress-generated token that prevents CSRF attacks. FLOSC REST endpoints validate the nonce from the <code>X-WP-Nonce</code> header. Nonces expire after 24 hours by default. The FLOSC JavaScript includes retry logic: if a request fails with a nonce error, it refreshes the nonce via <code>GET /flosc/v1/nonce</code> and retries the original request once.</p>

<h2 id="glossary-o">O</h2>

<h3 id="term-oauth2">OAuth2</h3>
<p>The authorization protocol used by FLOSC's SSO system for social login (Google, Apple, Facebook, Microsoft, LinkedIn). The flow: FLOSC redirects to the provider's authorization URL with a state parameter → provider authenticates the user → provider redirects back to FLOSC with a code → FLOSC exchanges the code for tokens → FLOSC looks up or creates the WordPress user.</p>

<h3 id="term-offer">Offer</h3>
<p>A purchasable product configured in the FLOSC admin. An offer has: an ID (e.g., <code>full_access_001</code>), a name, type (one_time/subscription/tokens/hybrid), status (active/draft), display format, price, headline, description, feature list, CTA text, and payment provider pricing details (Stripe price_id, PayPal plan, tokens cost). Offers are shown in the chat interface as cards or pills when triggered.</p>

<h3 id="term-offer-display-format">Offer Display Format</h3>
<p>How an offer renders in the chat. Options: <code>card</code> (full featured card with headline, description, features, price, CTA), <code>pill</code> (compact single button), <code>compact</code> (small card, less detail), <code>banner</code> (full-width announcement), <code>featured</code> (highlighted card), <code>text</code> (inline text link). Configurable per offer.</p>

<h3 id="term-offer-id">Offer ID</h3>
<p>A slug-format identifier for an offer (e.g., <code>full_access_001</code>, <code>monthly_plan</code>). Used in IVR trigger conditions (<code>data-offer-id</code> on pills), in AI action tags (<code>[ACTION:show_offer_full_access_001]</code>), and in payment processing. Must be unique within a flow.</p>

<h3 id="term-offer-pill">Offer Pill</h3>
<p>An autoprompt pill configured to trigger an offer display. When the user clicks it, the corresponding offer card renders in the chat interface. Offer pills are distinct from plain message pills — they carry a <code>data-offer-id</code> attribute that the JavaScript uses to show the offer rather than send a message.</p>

<h3 id="term-one-time-offer">One-Time Offer (OTO)</h3>
<p>An offer type where the user pays once and receives permanent access. As opposed to subscription (recurring) or token (per-use) offers. The default offer in sample data is a one-time offer for full lesson access.</p>

<h3 id="term-orientation-file">Orientation File</h3>
<p>A Markdown file uploaded through the AI Knowledge admin page that provides background information about the product, the teaching methodology, the target audience, and any other context the AI needs. Conceptually the "product brief" the AI reads before every conversation.</p>

<h2 id="glossary-p">P</h2>

<h3 id="term-paypal-sandbox">PayPal Sandbox</h3>
<p>PayPal's testing environment for payment development. FLOSC supports sandbox mode via a toggle in the Payments admin page. Sandbox mode uses separate PayPal credentials and processes no real money. The admin can test the full purchase flow without actual payment.</p>

<h3 id="term-payment-provider">Payment Provider</h3>
<p>An implementation of <code>class-payment-provider.php</code> that handles a specific payment gateway. Current providers: Stripe (<code>class-stripe-provider.php</code>), PayPal (<code>class-paypal-provider.php</code>), ClickBank (<code>class-clickbank-provider.php</code>), Token (<code>class-token-provider.php</code>), and Affiliate (<code>class-affiliate-provider.php</code>).</p>

<h3 id="term-phase">Phase (Visitor / Freeline / Member)</h3>
<p>One of three runtime states that determine which IVR messages, AI context, and content access apply to the current user. Visitor = not logged in. Guest/Freeline = logged in, no purchase. Member = logged in, purchased. Determined by <code>determine_flosc_phase()</code>.</p>

<h3 id="term-phase-prompt">Phase Prompt</h3>
<p>An admin-configurable text block that is added to the AI system prompt only when the user is in a specific phase. Configured in the AI Knowledge tab. Allows the AI to receive different instructions for visitors vs. guests vs. members.</p>

<h3 id="term-post-visibility">Post Visibility</h3>
<p>A WordPress post meta field (<code>_flosc_visibility</code>) that marks a post as requiring a specific FLOSC access level. Values: <code>public</code> (all users), <code>guest</code> (logged in), <code>member</code> (purchased). Posts without this meta follow the default FLOSC rules for their category.</p>

<h3 id="term-praise">Praise (AI Feedback)</h3>
<p>Admin-written examples of AI responses that were correct and should be reinforced. The counterpart to Feedback. Injected into the AI system prompt as positive examples. Configured from the AI Feedback/Praise admin page.</p>

<h3 id="term-product-config">Product Config</h3>
<p>The array returned by <code>get_product_config()</code> in the main FLOSC class. Contains all product-level settings (name, tagline, colors, logo, quiz config, etc.) assembled for a specific flow. Used when building the FLOSC_CONFIG JavaScript object.</p>

<h3 id="term-profile-bar">Profile Bar</h3>
<p>The navigation bar rendered at the top of the FLOSC app for logged-in users. Shows the user's name, avatar, and links to their profile or logout. Different configurations for Guest vs. Member. Configurable from the Navigation admin page.</p>

<h3 id="term-pronunciation-analyzer">Pronunciation Analyzer</h3>
<p><code>class-pronunciation-analyzer.php</code>. Handles the comparison between expected pronunciation (from quiz questions) and actual pronunciation (from STT transcription). Normalizes text for comparison, handles IPA symbols, generates learner-friendly feedback on errors.</p>

<h3 id="term-provider-pattern">Provider Pattern</h3>
<p>An architectural pattern used throughout FLOSC where interchangeable implementations share a common interface. Payment providers, SSO providers, STT providers, and AI providers all follow this pattern — swap out the provider by changing a setting, not by changing code.</p>

<h2 id="glossary-q">Q</h2>

<h3 id="term-quiz">Quiz</h3>
<p>A structured assessment that a user completes to receive a score and lesson recommendations. In FLOSC, a quiz is an instance of a quiz type class. The quiz produces an analysis result (score, correct items, incorrect items with topic slugs) that drives the entire subsequent experience.</p>

<h3 id="term-quiz-factory">Quiz Factory</h3>
<p><code>class-quiz-type-factory.php</code>. Creates quiz type instances. Loads all registered quiz type classes, provides <code>get_quiz_type()</code> by ID, and <code>get_all_quiz_types()</code> for the admin UI. Quiz type files are discovered by scanning the <code>includes/quiz-types/</code> directory.</p>

<h3 id="term-quiz-type">Quiz Type</h3>
<p>A PHP class extending <code>FLOSC_Abstract_Quiz_Type</code> that implements a specific question format. Each quiz type defines: its ID, name, description, icon, whether it needs audio/STT, question format instructions, default content, input validation, and the <code>analyze()</code> method that scores the user's answers and returns the result structure including incorrect items with topic slugs. Current types: pronunciation, Multiple Choice, True/False, Text Sequence (numbers), Audio, Word Matching.</p>

<h2 id="glossary-r">R</h2>

<h3 id="term-rag">RAG (Retrieval-Augmented Generation)</h3>
<p>The architecture pattern where an AI's responses are augmented with retrieved content from a knowledge base. In FLOSC, this means lessons, product knowledge files, and quiz results are retrieved from WordPress and injected into the AI context before the API call. The AI's answers are grounded in the specific content the admin has configured, not just the AI's general training.</p>

<h3 id="term-rate-limiting">Rate Limiting</h3>
<p>Per-IP request throttling on FLOSC REST endpoints. Authenticated users get higher limits than visitors. Rate limit state is stored in WordPress transients keyed to the client IP (detected from CF-Connecting-IP or X-Forwarded-For headers for CDN compatibility). Exceeding the limit returns HTTP 429.</p>

<h3 id="term-rest-endpoint">REST Endpoint</h3>
<p>A WordPress REST API route registered by FLOSC under the <code>flosc/v1</code> namespace. The main endpoints include <code>/chat</code>, <code>/quiz</code>, <code>/lessons</code>, <code>/offers</code>, <code>/sessions</code>, <code>/paypal/*</code>, <code>/create-payment-intent</code>, <code>/purchase</code>, <code>/sso/*</code>, and others. All require nonce authentication except public endpoints.</p>

<h3 id="term-rewrite-rules">Rewrite Rules</h3>
<p>WordPress URL routing rules added by FLOSC to make the app slug resolve to the FLOSC virtual page. Added via <code>add_rewrite_rules()</code> and flushed on plugin activation, version update, or when the slug setting changes. The "Permalinks OK" and "FLOW Settings OK" badges in the admin confirm the rules are active.</p>

<h2 id="glossary-s">S</h2>

<h3 id="term-sale-lifecycle">Sale Lifecycle</h3>
<p>The sequence of events in a FLOSC purchase: offer displayed → user clicks buy → payment intent created server-side → payment provider processes → webhook or callback confirms → access granted → user meta updated → email sent → AI context updated with purchase record.</p>

<h3 id="term-sample-data">Sample Data</h3>
<p>The demo content that ships with FLOSC: a pronunciation quiz, 10 lesson posts in a "Default FLOSC Lessons" category, a default offer, sample IVR messages, and sample autoprompt pills. Can be created via the Sample Data admin page or via WXR import. Provides a working product out of the box.</p>

<h3 id="term-sandbox-purchase">Sandbox Purchase</h3>
<p>A test purchase that grants access without processing real payment. Used by admins to test the post-purchase flow. The <code>/flosc/v1/sandbox-purchase</code> endpoint requires admin capability and immediately grants member access to the requesting user.</p>

<h3 id="term-session">Session</h3>
<p>A server-side record of a user's current conversation. For logged-in users, stored as user meta. For visitors, stored as a WordPress transient keyed to a session token in the browser. Contains: conversation history (message pairs), current phase, quiz state, and any carried context.</p>

<h3 id="term-signed-cookie">Signed Cookie</h3>
<p>A browser cookie whose value is an HMAC-signed payload. FLOSC uses signed cookies to store user state information that the server needs to read on every request without a database query. The cookie value is <code>base64(data) + '.' + base64(hmac_sha256(data, wp_secret_key))</code>. Tampering with the data invalidates the signature.</p>

<h3 id="term-singleton">Singleton Pattern</h3>
<p>A class pattern where only one instance exists per process. FLOSC uses singletons for all major component classes: <code>FLOSC_Framework::instance()</code>, <code>FLOSC_Flow_Manager::instance()</code>, <code>FLOSC_Sale_Manager::instance()</code>, etc. The global <code>flosc()</code> helper function returns the FLOSC_Framework singleton.</p>

<h3 id="term-sso">SSO (Single Sign-On)</h3>
<p>Social login via OAuth2 providers. FLOSC supports Google, Apple, Facebook, Microsoft, and LinkedIn. When a user authenticates via SSO, FLOSC looks up their linked WordPress account (by email or linked provider ID) or creates a new one. Handled by the SSO system in <code>includes/sso/</code>.</p>

<h3 id="term-stt">STT (Speech-to-Text)</h3>
<p>Audio transcription service that converts recorded pronunciation to text for comparison with expected pronunciation. FLOSC supports AssemblyAI, OpenAI Whisper, and a custom provider slot. Required for the Audio Quiz type. STT is not yet integrated in the MVP — the Audio Quiz type exists in the deck but cannot be enabled until the STT pipeline is wired.</p>

<h3 id="term-stripe">Stripe</h3>
<p>The primary payment processor for FLOSC. Supports one-time payments via Payment Intents, subscriptions via Stripe Subscriptions, and webhook-based purchase confirmation. Requires a Stripe publishable key (sent to frontend) and secret key (server-side only). Test mode and live mode use different key pairs.</p>

<h3 id="term-system-prompt">System Prompt</h3>
<p>The instruction set sent to the AI API along with the conversation history. In FLOSC, the system prompt is assembled from multiple sections: AI identity, FLOSC process instructions, phase-specific instructions, orientation files, user context, IVR guidance (when applicable), feedback (feedback/praise), and offer phrase triggers. Built by <code>build_system_prompt()</code> in <code>class-ai-chat-dispatch.php</code>.</p>

<h2 id="glossary-t">T</h2>

<h3 id="term-text-sequence-quiz">Text Sequence Quiz</h3>
<p>A quiz type (<code>class-flosc-sample-text-based-quiz.php</code>, now named "FLOSC Sample 1-10 Numbers Quiz") where the expected answer is a comma-separated sequence of numbers. Used as a simple pipeline test and demo quiz. The user types "1,2,3,4,5,6,7,8,9,10" and FLOSC scores which items they got right in sequence order.</p>

<h3 id="term-token-virtual">Token (Virtual Currency)</h3>
<p>A virtual currency system for metered access. Users can purchase token bundles; specific actions (requesting a lesson, starting a session) cost tokens. Implemented by <code>class-token-provider.php</code>. An alternative monetization model to one-time purchase or subscription.</p>

<h3 id="term-tool-use">Tool Use (Anthropic)</h3>
<p>An Anthropic API feature where the AI can request that specific functions be called on the server. FLOSC defines tools like <code>deliver_lesson</code> and <code>show_offer</code> that the AI can call when it determines a lesson should be delivered or an offer shown. Tool results are returned to the AI as a follow-up message in the same API call chain.</p>

<h3 id="term-template-variable">Template Variable (IVR)</h3>
<p>A placeholder in an IVR message content that is replaced at render time with a dynamic value. Format: <code>{variable_name}</code>. Available variables include: <code>{user_name}</code>, <code>{score}</code>, <code>{product_name}</code>, <code>{free_lesson_title}</code>, and others from the user context. Template variables make scripted messages feel personalized.</p>

<h3 id="term-true-false-quiz">True/False Quiz</h3>
<p><code>class-truefalse-quiz.php</code>. A quiz type where each question is a statement and the answer is True or False. Question format: <code>Statement.|True|Topic: slug</code>. Accepts <code>T/F/True/False/Yes/No</code> answers. Supports <code>|Topic:</code> pipe segment for lesson recommendations on incorrect answers.</p>

<h2 id="glossary-u">U</h2>

<h3 id="term-usage-tracker">Usage Tracker</h3>
<p><code>class-usage-tracker.php</code>. Tracks how many times a user has used specific FLOSC features (AI requests, lesson deliveries, quiz attempts). Used for rate limiting metered features and for generating usage analytics.</p>

<h3 id="term-user-linker">User Linker</h3>
<p><code>includes/sso/class-user-linker.php</code>. Handles the mapping between OAuth provider identities and WordPress user accounts. When a user logs in via Google for the first time, the User Linker either finds their existing WordPress account by email or creates a new one, then stores the provider-user association for future logins.</p>

<h2 id="glossary-v">V</h2>

<h3 id="term-visitor">Visitor</h3>
<p>A user who is not logged in. Visitors can take the quiz and chat with the AI, but their results are stored only in the browser. They cannot see which specific questions they missed — that detail requires account creation. IVR messages and AI in the visitor phase are designed to generate interest without over-delivering.</p>

<h3 id="term-visitor-bar">Visitor Bar</h3>
<p>The navigation bar shown to visitors (non-logged-in users) at the top of the FLOSC app. Contains configurable links and a login/signup button. The visitor bar configuration is separate from the profile bar shown to logged-in users.</p>

<h3 id="term-visitor-id">Visitor ID</h3>
<p>A unique anonymous identifier assigned to each visitor session and stored as a browser cookie. Used to track quiz attempts and conversation history before account creation. Not linked to any user account until the visitor registers, at which point the bridge data tied to this ID transfers to the new account.</p>

<h3 id="term-visitor-menu">Visitor Menu / Dropdown</h3>
<p>A configurable dropdown menu shown when a visitor clicks their visitor bar button. Contains links configured by the admin from the Navigation admin page. Typically includes links like "How It Works," "Pricing," "About," and a prominent CTA for starting the quiz or creating an account.</p>

<h3 id="term-visitor-phase">VISITOR Phase</h3>
<p>The runtime phase for non-logged-in users. FLOSC serves the visitor IVR messages, shows visitor-phase autoprompt pills, and prevents quiz result details from being revealed. The VISITOR phase is the top of the flow.</p>

<h2 id="glossary-w">W</h2>

<h3 id="term-webhook">Webhook</h3>
<p>An HTTP callback sent by a payment provider to FLOSC when a payment event occurs (purchase completed, subscription renewed, refund issued). FLOSC registers webhook endpoints under <code>/flosc/v1/webhooks/{provider}</code>. Webhooks are the authoritative source of payment confirmation — they are used alongside (not instead of) client-side callbacks for reliability.</p>

<h3 id="term-whitelisted-variable">Whitelisted Variable</h3>
<p>One of ~35 approved variable names that may be referenced in IVR conditions. The whitelist prevents arbitrary PHP variable access or code injection via condition expressions. Examples: <code>user.state</code>, <code>score</code>, <code>purchased</code>, <code>flow_id</code>, <code>quiz_type</code>, <code>lesson_count</code>, <code>free_lesson_delivered</code>. Managed in <code>class-condition-evaluator.php</code>.</p>

<h3 id="term-word-matching-quiz">Word Matching Quiz</h3>
<p><code>class-wordmatching-quiz.php</code>. A quiz type where the user matches words or phrases to their corresponding items. Used for vocabulary association, definition matching, or pairing exercises.</p>

<h3 id="term-wxr">WXR (WordPress eXtended RSS)</h3>
<p>The XML export format WordPress uses for content import/export. FLOSC ships sample data as a WXR file that admins can import via WordPress's built-in Import tool. The WXR contains: 10 sample lesson posts with correct <code>_flosc_lesson_number</code> meta, the "Default FLOSC Lessons" category, and associated tags. This gives a new FLOSC install working content in one import step.</p>
