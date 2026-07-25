<?php if (!defined('ABSPATH')) exit; // Admin docs pass order and navigation map ?>

<?php
$flosc_ref_ivr = isset($selected_ivr) ? sanitize_file_name((string) $selected_ivr) : '';
$flosc_feature_links = [
  'flow' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'flow'], admin_url('admin.php')),
  'identity' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'identity'], admin_url('admin.php')),
  'ivr-messages' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ivr-messages'], admin_url('admin.php')),
  'autoprompts' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'autoprompts'], admin_url('admin.php')),
  'member-levels' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'member-levels'], admin_url('admin.php')),
  'trajectories' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'trajectories'], admin_url('admin.php')),
  'offers' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'offers'], admin_url('admin.php')),
  'login' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'login'], admin_url('admin.php')),
  'style' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'style'], admin_url('admin.php')),
  'ui' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ui'], admin_url('admin.php')),
  'ai' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ai'], admin_url('admin.php')),
  'concierge' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'concierge'], admin_url('admin.php')),
  'quiz' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'quiz'], admin_url('admin.php')),
  'email' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'email'], admin_url('admin.php')),
  'lessons' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'lessons'], admin_url('admin.php')),
  'payments' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'payments'], admin_url('admin.php')),
  'sso' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'sso'], admin_url('admin.php')),
  'chat-logs' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'chat-logs'], admin_url('admin.php')),
  'administration' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'administration'], admin_url('admin.php')),
  'da1' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'da1'], admin_url('admin.php')),
];
?>

<h1 id="ref-admin">Part 3: Reference — Admin Pages</h1>
<p>This section defines the documentation update workflow for FLOSC admin features.</p>

<h2 id="admin-doc-pass-order">Documentation Pass Order (Authoritative)</h2>
<p>Update documentation in the exact admin tab order: <strong>left to right, top to bottom</strong>.</p>

<ol>
  <li><code>flow</code> — Flow setup, routing, active/inactive lifecycle</li>
  <li><code>identity</code> — Product identity, branding fields, compliance text blocks</li>
  <li><code>ivr-messages</code> — IVR parser model, conditions/actions, message lifecycle</li>
  <li><code>autoprompts</code> — PromptPanel behavior by state (visitor/guest/member)</li>
  <li><code>member-levels</code> — Level model and unlock logic</li>
  <li><code>trajectories</code> — Outcome trajectories and progression configuration</li>
  <li><code>offers</code> — Offer definitions, display modes, trigger mapping</li>
  <li><code>login</code> — Registration/login flows and guest-link behavior</li>
  <li><code>style</code> — Structured theme and chat style controls</li>
  <li><code>ui</code> — Navigation and presentation rules</li>
  <li><code>ai</code> — Provider configuration, knowledge files, feedback/praise</li>
  <li><code>concierge</code> — Concierge prompt routing and assist behavior controls</li>
  <li><code>quiz</code> — Quiz architecture, scoring model, lesson mapping</li>
  <li><code>email</code> — Email sequence, sender identity, placeholders</li>
  <li><code>lessons</code> — Lesson source model and access filtering</li>
  <li><code>payments</code> — Stripe/PayPal/token and webhook boundaries</li>
  <li><code>sso</code> — OAuth provider setup, callback behavior, flow resolution</li>
  <li><code>chat-logs</code> — Logging model, ratings, operational usage</li>
  <li><code>administration</code> — Runtime controls, debug mode, operational tasks</li>
  <li><code>da1</code> — Catalog datasets, flow assignment, upload/export operations</li>
  <li><code>documentation</code> — Final reconciliation and link validation pass</li>
</ol>

<h2 id="admin-doc-jit-links">Just-in-Time Linking Rule</h2>
<p>Each feature doc section must include a direct admin destination link format:</p>
<pre><code>?page=flosc-settings&amp;tab={tab_id}</code></pre>
<p>Each tab-level doc should also include reverse links back to key feature sections in this documentation.</p>

<h2 id="admin-doc-pass1-links">Pass 1 Bidirectional Links</h2>
<p>These are the first tab-level framework links: documentation to feature, and feature back to documentation anchors.</p>

<h3 id="tab-flow">Flow Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['flow']); ?>">Open Feature: Flow tab</a></p>
<p><strong>One further step:</strong> After reviewing the phase snapshot, open one phase editor and verify that the count shown in Flow matches what is configured in that destination tab.</p>
<p><strong>Procedure Level:</strong> Open one phase card, compare it against the destination tab, and confirm the count is still aligned.</p>
<p><strong>Tech Ref Level:</strong> The Flow tab reads live counts from the current flow settings and presents the Freeline-to-Content path in one summary view.</p>
<p><strong>Code Level:</strong> admin/flow.php renders the overview cards, and each edit button links to the matching tab for the same flow data.</p>

<h3 id="tab-identity">Identity Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['identity']); ?>">Open Feature: Identity tab</a></p>
<p><strong>One further step:</strong> Confirm the flow name, title, and slug together, then open the generated flow URL to verify public-facing identity output.</p>
<p><strong>Procedure Level:</strong> Check the name, title, and slug together, then open the public URL to confirm the branding is correct.</p>
<p><strong>Tech Ref Level:</strong> Identity settings supply the public-facing name, title, tagline, and slug used throughout the flow.</p>
<p><strong>Code Level:</strong> The identity editor lives in admin/settings.php, and the frontend shell reads those fields when building labels and links.</p>

<h3 id="tab-ivr-messages">IVR Management Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['ivr-messages']); ?>">Open Feature: IVR Management tab</a></p>
<p><strong>One further step:</strong> Run a compare/sync check and confirm the selected IVR file and per-flow database messages are aligned before editing individual messages.</p>
<p><strong>Procedure Level:</strong> Compare the active IVR file with the stored messages, then edit only after the sync looks correct.</p>
<p><strong>Tech Ref Level:</strong> IVR management coordinates message files, per-flow overrides, and parser rules that control message behavior.</p>
<p><strong>Code Level:</strong> admin/ivr-messages.php renders the editor, and the backend loader reads the selected IVR plus saved overrides.</p>

<h4 id="tab-ivr-messages-all-flows-fields">All Flows File Management field guide</h4>
<p><strong>Flow Label:</strong> The human-readable flow name pulled from the flow settings bundle, usually from the identity name field, and used only for display.</p>
<p><strong>Flow Key (internal):</strong> The WordPress option key that stores the per-flow settings bundle, typically the <code>flosc_flow_*</code> record used by the admin and runtime loaders.</p>
<p><strong>AI Provider:</strong> The provider slug stored in the flow settings, resolved from the flow option first and then from the global AI default if the flow has no explicit provider value.</p>
<p><strong>Messages:</strong> The count of parsed message entries currently available for that IVR file, taken from the parser output after the markdown file is read.</p>
<p><strong>Phases:</strong> The per-phase message count snapshot for Freeline, Login, Offer, Sale, and Content, built from the parser's phase arrays.</p>
<p><strong>Modified:</strong> The file modification timestamp from the resolved IVR file path on disk, not a database timestamp.</p>
<p><strong>Size:</strong> The byte size of the resolved IVR file as reported by the filesystem.</p>
<p><strong>Parse Status:</strong> The parser result for the row; the code checks that the file exists, can be read, and returns at least one message entry after passing the markdown through <code>FLOSC_IVR_Parser::flosc_instance()-&gt;flosc_parse()</code>.</p>
<p><strong>Selected IVR File in Settings:</strong> The comparison between the row file name and the flow's stored IVR pointer, using <code>active_ivr_file</code> first and then <code>ivr_file</code> from the flow settings array.</p>

<h3 id="tab-da1-dataset-template">DA1 Dataset Template (Submission Reference)</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['da1']); ?>">Open Feature: DA1 tab</a></p>
<p><strong>Required control columns:</strong> <code>Row Key</code>, <code>Parent Key</code>, <code>Catalog Key</code>, <code>Record Type</code>, <code>Flow Scope</code>, <code>VGM</code>, <code>Delivery Instruction</code>, <code>Delivery Rule</code>, <code>Fallback Order</code>, <code>Status</code>.</p>
<p><strong>Status values:</strong> Use <code>active</code> or <code>paused</code>. Parent <code>paused</code> means child rows should be treated as paused for serving.</p>
<p><strong>Row model:</strong> Parent rows use integer keys like <code>85</code>. Child rows use decimal keys like <code>85.1</code>, <code>85.2</code>, and set <code>Parent Key</code> to <code>85</code>.</p>
<p><strong>Flow Scope:</strong> Required. Use one or more flow keys comma-separated (for example <code>lesaep_ivr,dainis_net_ivr</code>) or <code>all</code>.</p>
<p><strong>VGM values:</strong> Use <code>visitor</code>, <code>guest</code>, <code>member</code>, or <code>all</code>.</p>
<p><strong>Payload fields:</strong> Keep catalog content flexible. Recommended payload columns are <code>Date</code>, <code>Title</code>, <code>Description</code>, <code>Lyrics</code>, <code>Media</code>, <code>Media Type</code>, and <code>Notes</code>. Additional columns are allowed and preserved.</p>
<p><strong>Catalog operations:</strong> DA1 supports create catalog, assign catalogs per flow, upload TSV, and export TSV. One flow can use multiple catalogs ordered by priority.</p>
<pre><code>Row Key	Parent Key	Catalog Key	Record Type	Flow Scope	VGM	Delivery Instruction	Delivery Rule	Fallback Order	Status	Date	Title	Description	Lyrics	Media	Media Type	Notes
85		music_core	work	lesaep_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active	1996	Time Flies	Rock collaboration				Primary work row
85.1	85	music_core	media	lesaep_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active		Time Flies	Live version		https://example.com/youtube-link	youtube	Live performance
85.2	85	music_core	media	lesaep_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active		Time Flies	Short form clip		https://example.com/tiktok-link	tiktok	Social clip</code></pre>
<h3 id="visitor-autoprompts-intropanelshow-panel">⚪ Visitor AutoPrompts — IntroPanelShow panel</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['autoprompts']); ?>">Open Feature: AutoPrompts tab (Visitor section)</a></p>
<p><strong>One further step:</strong> Toggle the Visitor panel visibility once and save, then reload the tab to confirm the state persisted for the active flow.</p>
<p><strong>Procedure Level:</strong> Toggle the visitor panel, save it, and confirm the same state stays selected after reload.</p>
<p><strong>Tech Ref Level:</strong> Visitor autoprompts control the first-state prompt flow before a user signs in or reaches member access.</p>
<p><strong>Code Level:</strong> admin/autoprompts.php renders the visitor rows, and the frontend prompt engine reads those saved visitor prompts.</p>

<h3 id="guest-autoprompts-promptpanelshow-panel">🟢 Guest AutoPrompts — PromptPanelShow panel</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['autoprompts']); ?>">Open Feature: AutoPrompts tab (Guest section)</a></p>
<p><strong>One further step:</strong> Add one guest autoprompt and validate that the row appears under the correct panel and state label.</p>
<p><strong>Procedure Level:</strong> Add one guest prompt row, save it, and check that it appears under the correct guest section.</p>
<p><strong>Tech Ref Level:</strong> Guest prompts define the intermediate prompt set used after visitor interactions and before member-only content.</p>
<p><strong>Code Level:</strong> admin/autoprompts.php renders the guest panel rows, and the prompt display logic reads them from the same flow settings.</p>

<h3 id="member-autoprompts-memberpromptpanelshow-panel">🔵 LeSAEp Learner AutoPrompts — MemberPromptPanelShow panel</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['autoprompts']); ?>">Open Feature: AutoPrompts tab (Member section)</a></p>
<p><strong>One further step:</strong> Validate one learner autoprompt trigger phrase against your IVR messaging intent so member prompts remain behaviorally consistent.</p>
<p><strong>Procedure Level:</strong> Check one learner trigger phrase, then verify it still matches the intended member-facing prompt behavior.</p>
<p><strong>Tech Ref Level:</strong> Member prompts are the learned-state prompts shown after the user reaches member access in the flow.</p>
<p><strong>Code Level:</strong> admin/autoprompts.php renders the member panel, and the frontend prompt engine uses the saved member-state definitions.</p>

<h2 id="admin-doc-pass1-shell">Pass 1 Shell</h2>
<p>Use this shell pattern for each pass-1 section: feature destination link, one behavioral checkpoint, and one persistence or data-alignment check.</p>

<h2 id="admin-doc-pass2-links">Pass 2 Bidirectional Links</h2>
<p>These additions extend documented coverage to 6 tabs total.</p>

<h3 id="tab-member-levels">Member Levels Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['member-levels']); ?>">Open Feature: Member Levels tab</a></p>
<p><strong>One further step:</strong> Create or confirm one level slug and then verify that slug appears as a selectable target in dependent tabs that grant or require levels.</p>
<p><strong>Procedure Level:</strong> Confirm one member level slug, then check that the same slug is available wherever access levels are referenced.</p>
<p><strong>Tech Ref Level:</strong> Member levels are used as access targets and grant values across offers, lessons, and gating logic.</p>
<p><strong>Code Level:</strong> admin/member-levels.php edits the level data, and the access-control routines read the same slug values for permissions.</p>

<h3 id="tab-offers">Offers Tab</h3>
<p><strong>Payment processors (JIT):</strong> Each offer picks <code>paypal</code> or <code>stripe</code> (native — keys on Payments tab), <code>free</code>, or <code>redirect</code> (WooCommerce, Shopify, member sites, any checkout URL). FLOSC is not a full PSP; external carts redirect out, then you grant access via Access Code or member level.</p>
<p><a href="<?php echo esc_url($flosc_feature_links['offers']); ?>">Open Feature: Offers tab</a></p>
<p><strong>One further step:</strong> Save one offer with a single enabled display format and confirm status plus pricing fields persist after reload.</p>
<p><strong>Procedure Level:</strong> Save one offer, keep one display format active, and verify the price and status stay intact after reload.</p>
<p><strong>Tech Ref Level:</strong> Offer records carry display formats, triggers, pricing, and level grants used by the checkout and content gates.</p>
<p><strong>Code Level:</strong> admin/offers.php manages the offer editor, while the sale backend reads the stored offer definitions for rendering and payment flow.</p>

<h2 id="admin-doc-pass2-shell">Pass 2 Shell</h2>
<p>For each added tab: link to the feature, record one runtime behavior check, and record one saved-state verification check.</p>

<h2 id="admin-doc-pass3-links">Pass 3 Bidirectional Links</h2>
<p>These additions extend documented coverage to 9 tabs total.</p>

<h3 id="tab-login">Register &amp; Login Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['login']); ?>">Open Feature: Register &amp; Login tab</a></p>
<p><strong>One further step:</strong> Change one auth modal text field and one header button action, save, then reload to confirm both text and action selections persisted for the active flow.</p>
<p><strong>Procedure Level:</strong> Edit one login prompt and one button action, save, and confirm both remain on the active flow after reload.</p>
<p><strong>Tech Ref Level:</strong> Login settings store modal copy, header actions, and guest-link behavior for the registration path.</p>
<p><strong>Code Level:</strong> admin/login-registration.php renders the controls, and the frontend auth flow reads the saved action and text values.</p>

<h3 id="tab-style">Style Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['style']); ?>">Open Feature: Style tab</a></p>
<p><strong>One further step:</strong> Update one structured style control (for example accent color), save, then verify the saved value appears in the tab and in live chat output.</p>
<p><strong>Procedure Level:</strong> Change one visual setting, save it, and check the new style in the tab and on the live chat surface.</p>
<p><strong>Tech Ref Level:</strong> Style controls write theme values that feed the chat renderer and frontend CSS variables.</p>
<p><strong>Code Level:</strong> admin/chat-styling.php renders the style UI, and the frontend theme files consume the saved design tokens.</p>

<h3 id="tab-ui">UI &amp; Nav Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['ui']); ?>">Open Feature: UI &amp; Nav tab</a></p>
<p><strong>One further step:</strong> Change one profile/menu action mapping, save, then verify the selected state behavior and label appear correctly in the interface.</p>
<p><strong>Procedure Level:</strong> Adjust one navigation mapping, save it, and confirm the displayed label and state still match the selected action.</p>
<p><strong>Tech Ref Level:</strong> UI settings govern menu labels, navigation actions, and which profile or state views are shown to the user.</p>
<p><strong>Code Level:</strong> admin/ui-navigation.php renders the mappings, and the frontend navigation logic uses the saved UI state.</p>

<h2 id="admin-doc-pass3-shell">Pass 3 Shell</h2>
<p>For each added tab: link to the feature, record one UI-behavior checkpoint, and record one saved-setting verification checkpoint.</p>

<h2 id="admin-doc-pass4-links">Pass 4 Bidirectional Links</h2>
<p>These additions extend documented coverage to 12 tabs total.</p>

<h3 id="tab-ai">AI Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['ai']); ?>">Open Feature: AI tab</a></p>
<p><strong>One further step:</strong> Select one AI provider and adjust one model or behavior setting, save, then verify the provider-specific section remains consistent after reload.</p>
<p><strong>Procedure Level:</strong> Pick one provider, save one setting, and reload to confirm the same provider section stays visible.</p>
<p><strong>Tech Ref Level:</strong> The AI tab stores provider selection, model text, and feedback or praise content in the flow settings bundle.</p>
<p><strong>Code Level:</strong> admin/ai-configuration.php controls the form, and the prompt-building backend reads the saved provider and feedback settings.</p>

<h3 id="tab-quiz">Quiz Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['quiz']); ?>">Open Feature: Quiz tab</a></p>
<p><strong>One further step:</strong> Enable one quiz or load one demo block, save, then verify the selected quiz state and content are retained on reload.</p>
<p><strong>Procedure Level:</strong> Enable one quiz block, save it, and check that the same quiz remains selected after reload.</p>
<p><strong>Tech Ref Level:</strong> Quiz settings drive scoring, active quiz selection, lesson mapping, and the post-quiz flow sequence.</p>
<p><strong>Code Level:</strong> admin/quiz.php renders the quiz deck and quiz content UI, while the quiz engine reads the stored quiz configuration.</p>

<h3 id="tab-email">Email Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['email']); ?>">Open Feature: Email tab</a></p>
<p><strong>One further step:</strong> Edit one subject/body placeholder value, save, then confirm the updated template is preserved and visible in the tab form.</p>
<p><strong>Procedure Level:</strong> Change one email template field, save it, and verify the updated placeholder text is still present after reload.</p>
<p><strong>Tech Ref Level:</strong> Email settings control subject, body, sender identity, and placeholder values used by automation and notifications.</p>
<p><strong>Code Level:</strong> admin/email.php renders the template editor, and the mail-sending code reads those saved placeholders at send time.</p>

<h2 id="admin-doc-pass4-shell">Pass 4 Shell</h2>
<p>For each added tab: link to the feature, record one provider or content behavior check, and record one save/reload verification check.</p>

<h2 id="admin-doc-pass5-links">Pass 5 Bidirectional Links</h2>
<p>These additions extend documented coverage to 15 tabs total.</p>

<h3 id="tab-lessons">Lessons Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['lessons']); ?>">Open Feature: Lessons tab</a></p>
<p><strong>One further step:</strong> Review the lesson group/category mappings for the active flow and confirm the visible lesson structure matches the current content set.</p>
<p><strong>Procedure Level:</strong> Review one lesson group, confirm the mapped category, and check that the visible lesson structure matches the current flow.</p>
<p><strong>Tech Ref Level:</strong> Lesson visibility is driven by per-flow lesson groups, category protection metadata, and offer-granted access levels.</p>
<p><strong>Code Level:</strong> admin/lessons.php builds the repeater UI and protection controls, while flow.php and post-access logic consume the saved lesson group settings.</p>

<h3 id="tab-payments">Payments Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['payments']); ?>">Open Feature: Payments tab</a></p>
<p><strong>One further step:</strong> Enable PayPal and/or Stripe in test/sandbox, save keys, then open an offer with that processor and confirm the checkout script loads (PayPal buttons or Stripe card field).</p>
<p><strong>Procedure Level:</strong> Add Client ID + Secret (PayPal) and/or publishable + secret keys (Stripe). Leave External carts to Offers → Redirect URL — they do not need keys on this tab.</p>
<p><strong>Tech Ref Level:</strong> Per-flow WPDB stores Stripe and PayPal credentials. Frontend gets <code>stripeKey</code>, <code>paypalClientId</code>, and <code>paypalSdkIntent</code> (<code>capture</code> vs <code>subscription</code> from active offers). External Woo/Shopify paths use offer <code>redirect_url</code> only.</p>
<p><strong>Code Level:</strong> admin/payments.php is the form; providers in includes/sale/providers/; enqueue in flosc.php; checkout multipath in flosc-app.js <code>openCheckout</code>.</p>

<h3 id="tab-sso">SSO Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['sso']); ?>">Open Feature: SSO tab</a></p>
<p><strong>One further step:</strong> Check one provider row and the fallback redirect context, then confirm the active flow identity and redirect source are obvious at a glance.</p>
<p><strong>Procedure Level:</strong> Inspect one provider configuration, confirm the callback URL, and verify the redirect target matches the active flow.</p>
<p><strong>Tech Ref Level:</strong> SSO uses per-flow provider credentials plus callback-state data to resolve the correct app URL after login.</p>
<p><strong>Code Level:</strong> admin/sso.php renders the provider cards, and the callback handler in the SSO backend resolves redirects from stored flow state.</p>

<h2 id="admin-doc-pass5-shell">Pass 5 Shell</h2>
<p>For each added tab: link to the feature, record one configuration-behavior checkpoint, and record one active-flow verification checkpoint.</p>

<h2 id="admin-doc-pass6-links">Pass 6 Bidirectional Links</h2>
<p>These additions complete documented coverage to 17 tabs total.</p>

<h3 id="tab-chat-logs">Chat Logs Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['chat-logs']); ?>">Open Feature: Chat Logs tab</a></p>
<p><strong>One further step:</strong> Filter one live conversation by phase or user, then confirm the newest row order and rating controls match the active log stream.</p>
<p><strong>Procedure Level:</strong> Filter one live chat stream, then confirm the newest row and rating controls match what is shown.</p>
<p><strong>Tech Ref Level:</strong> Chat logs are read from the log table through AJAX polling, with ratings stored alongside each entry.</p>
<p><strong>Code Level:</strong> admin/chat-logs.php renders the log table, and the AJAX handlers plus log helper manage polling and rating writes.</p>

<h3 id="tab-administration">Administration Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['administration']); ?>">Open Feature: Administration tab</a></p>
<p><strong>One further step:</strong> Switch one debug setting or account-plan control, save, then confirm the runtime status table reflects the updated effective access state.</p>
<p><strong>Procedure Level:</strong> Change one admin control, save it, and recheck the runtime summary table for the updated state.</p>
<p><strong>Tech Ref Level:</strong> This tab reports access, plan, and debug values from WordPress options plus current user capability data.</p>
<p><strong>Code Level:</strong> The form and status table are rendered in admin/administration.php, and the values are read directly from options and user/session helpers.</p>

<h2 id="admin-doc-pass6-shell">Pass 6 Shell</h2>
<p>For each added tab: link to the feature, record one operational behavior checkpoint, and record one saved-state verification checkpoint.</p>

<h2 id="admin-doc-completion-rule">Completion Rule per Tab</h2>
<ul>
  <li>Describe what the tab controls in runtime behavior.</li>
  <li>List the primary options stored and where they are read in code.</li>
  <li>Document external-service behavior, if any, with triggers and data flow.</li>
  <li>Add at least one direct help link to and from the relevant in-app feature.</li>
  <li>Mark done only after code match and link validation.</li>
</ul>

<p class="skeleton-marker">This ordering is now the required workflow for documentation passes in FLOSC 8.0.1 planning and forward releases.</p>
