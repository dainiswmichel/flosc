<?php if (!defined('ABSPATH')) exit; // Admin docs pass order and navigation map ?>

<?php
$flosc_ref_ivr = isset($selected_ivr) ? sanitize_file_name((string) $selected_ivr) : '';
$flosc_ref_admin_doc_url = add_query_arg([
  'page' => 'flosc-settings',
  'ivr' => $flosc_ref_ivr,
  'tab' => 'documentation',
  'doc' => 'ref-admin',
], admin_url('admin.php'));
$flosc_feature_links = [
  'flow' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'flow'], admin_url('admin.php')),
  'identity' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'identity'], admin_url('admin.php')),
  'ivr-messages' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ivr-messages'], admin_url('admin.php')),
  'autoprompts' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'autoprompts'], admin_url('admin.php')),
  'content' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'content'], admin_url('admin.php')),
  'trajectories' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'trajectories'], admin_url('admin.php')),
  'offers' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'offers'], admin_url('admin.php')),
  'login' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'login'], admin_url('admin.php')),
  'style' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'style'], admin_url('admin.php')),
  'ui' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ui'], admin_url('admin.php')),
  'ai' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ai'], admin_url('admin.php')),
  'token-management' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'token-management'], admin_url('admin.php')),
  'concierge' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'concierge'], admin_url('admin.php')),
  'quiz' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'quiz'], admin_url('admin.php')),
  'email' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'email'], admin_url('admin.php')),
  'contact-form' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'contact-form'], admin_url('admin.php')),
  'payments' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'payments'], admin_url('admin.php')),
  'sso' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'sso'], admin_url('admin.php')),
  'engagement' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'engagement'], admin_url('admin.php')),
  'chat-logs' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'chat-logs'], admin_url('admin.php')),
  'administration' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'administration'], admin_url('admin.php')),
  'da1' => add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'da1'], admin_url('admin.php')),
];

$flosc_tab_doc_anchors = [
  'flow' => 'tab-flow',
  'identity' => 'tab-identity',
  'ivr-messages' => 'tab-ivr-messages',
  'autoprompts' => 'visitor-autoprompts-intropanelshow-panel',
  'content' => 'tab-content',
  'trajectories' => 'admin-doc-pass2-links',
  'offers' => 'tab-offers',
  'login' => 'tab-login',
  'style' => 'tab-style',
  'ui' => 'tab-ui',
  'ai' => 'tab-ai',
  'token-management' => 'tab-token-management',
  'concierge' => 'inventory-ai-family',
  'quiz' => 'tab-quiz',
  'email' => 'tab-email',
  'contact-form' => 'tab-contact-form',
  'payments' => 'tab-payments',
  'sso' => 'tab-sso',
  'engagement' => 'tab-engagement',
  'chat-logs' => 'tab-chat-logs',
  'administration' => 'tab-administration',
  'documentation' => 'admin-doc-pass-order',
  'da1' => 'tab-da1-dataset-template',
];

$flosc_tab_labels = [
  'flow' => 'Flow',
  'identity' => 'Identity',
  'ivr-messages' => 'IVR Management',
  'autoprompts' => 'AutoPrompts',
  'content' => 'Content',
  'trajectories' => 'Trajectories',
  'offers' => 'Offers',
  'login' => 'Register & Login',
  'style' => 'Style & Nav',
  'ui' => 'Profile Bar',
  'ai' => 'AI',
  'token-management' => 'Token Management',
  'concierge' => 'Concierge',
  'quiz' => 'Quiz',
  'email' => 'Email',
  'contact-form' => 'Contact Form',
  'payments' => 'Payments',
  'sso' => 'SSO',
  'engagement' => 'Engagement',
  'chat-logs' => 'Chat Logs',
  'administration' => 'Administration',
  'documentation' => 'Documentation',
  'da1' => 'DA1',
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
  <li><code>content</code> — Member levels, content groups, protection rules, and access structure</li>
  <li><code>trajectories</code> — Outcome trajectories and progression configuration</li>
  <li><code>offers</code> — Offer definitions, display modes, trigger mapping</li>
  <li><code>login</code> — Registration/login flows and guest-link behavior</li>
  <li><code>style</code> — Structured theme and chat style controls</li>
  <li><code>ui</code> — Navigation and presentation rules</li>
  <li><code>ai</code> — Dual view: This flow (attach personality, primary provider, chain, accuracy tests) + All Flows AI API Management (install keys + personality library)</li>
  <li><code>token-management</code> — Visitor/member token grants, costs, refill, and ledger-facing controls</li>
  <li><code>concierge</code> — Concierge prompt routing and assist behavior controls</li>
  <li><code>quiz</code> — Quiz architecture, scoring model, and content recommendation mapping</li>
  <li><code>email</code> — Email sequence, sender identity, placeholders</li>
  <li><code>contact-form</code> — Contact form routing, labels, and follow-up settings</li>
  <li><code>payments</code> — Stripe/PayPal/token and webhook boundaries</li>
  <li><code>sso</code> — OAuth provider setup, callback behavior, flow resolution</li>
  <li><code>engagement</code> — Visitor/guest/member engagement thresholds, triggers, and state transitions</li>
  <li><code>chat-logs</code> — Logging model, ratings, operational usage</li>
  <li><code>administration</code> — Runtime controls, debug mode, operational tasks</li>
  <li><code>da1</code> — Catalog datasets, flow assignment, upload/export operations</li>
  <li><code>documentation</code> — Final reconciliation and link validation pass</li>
</ol>

<h2 id="admin-doc-jit-links">Just-in-Time Linking Rule</h2>
<p>Each feature doc section must include a direct admin destination link format:</p>
<pre><code>?page=flosc-settings&amp;tab={tab_id}</code></pre>
<p>Each tab-level doc should also include reverse links back to key feature sections in this documentation.</p>

<h3 id="admin-doc-jit-link-matrix">Tab Destination + Docs Anchor Matrix</h3>
<p>Use these links in dashboard help text so admins can jump to the correct tab and the matching documentation anchor.</p>
<table>
  <tr><th>Tab</th><th>Feature Destination</th><th>Documentation Anchor</th></tr>
  <?php foreach ($flosc_tab_doc_anchors as $flosc_tab_key => $flosc_anchor): ?>
    <?php
    $flosc_feature_url = isset($flosc_feature_links[$flosc_tab_key])
      ? $flosc_feature_links[$flosc_tab_key]
      : add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => $flosc_tab_key], admin_url('admin.php'));
    $flosc_doc_url = $flosc_ref_admin_doc_url . '#' . sanitize_key((string) $flosc_anchor);
    $flosc_label = $flosc_tab_labels[$flosc_tab_key] ?? $flosc_tab_key;
    ?>
    <tr>
      <td><code><?php echo esc_html($flosc_tab_key); ?></code> (<?php echo esc_html($flosc_label); ?>)</td>
      <td><a href="<?php echo esc_url($flosc_feature_url); ?>">Open tab</a></td>
      <td><a href="<?php echo esc_url($flosc_doc_url); ?>">Open docs section</a></td>
    </tr>
  <?php endforeach; ?>
</table>

<h2 id="admin-doc-pass1-links">Pass 1 Bidirectional Links</h2>
<p>These are the first tab-level framework links: documentation to feature, and feature back to documentation anchors.</p>

<h3 id="tab-flow">Flow Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['flow']); ?>">Open Feature: Flow tab</a></p>
<p><strong>One further step:</strong> After reviewing the phase snapshot, open one phase editor and verify that the count shown in Flow matches what is configured in that destination tab.</p>
<p><strong>Procedure Level:</strong> Open one phase card, compare it against the destination tab, and confirm the count is still aligned.</p>
<p><strong>Tech Ref Level:</strong> The Flow tab reads live counts from the current flow settings and presents the Freeline-to-Content path in one summary view. Portability (below) moves personality + data packs without shipping secrets.</p>
<p><strong>Code Level:</strong> admin/flow.php renders the overview cards, and each edit button links to the matching tab for the same flow data.</p>

<h4 id="tab-flow-portability">Flow Portability (Create / Apply packs)</h4>
<p>Portability separates <strong>personality</strong> (how the assistant speaks — typically an IVR <code>.md</code> with optional Settings YAML) from <strong>data</strong> (DA1 TSV, WXR posts, media). API key strings never travel in packs; they stay in <code>floscAvailableProviders</code> on the install (or a flow-local override).</p>
<ul>
  <li><strong>Create</strong> — drop or select a personality <code>.md</code> (plus optional data files) to create a <em>new</em> floscFlow; data in the same drop attaches as that flow’s data set.</li>
  <li><strong>Apply</strong> — merge personality and/or attach data into the <em>current</em> floscFlow (same merge rules as the drop zone).</li>
  <li><strong>Settings YAML</strong> (inside the IVR <code>.md</code>) may carry journey wiring and AI policy <em>ids</em> (provider slug, chain order, <code>personality_library_id</code>) — never secret key material.</li>
</ul>
<p><strong>Procedure Level:</strong> Prefer Create for a green-field product; use Apply when updating the selected flow. After import, open <strong>AI → All Flows AI API Management</strong> on the target install to ensure keys exist, then <strong>This flow: AI settings</strong> to attach primary provider and personality.</p>
<p><strong>Code Level:</strong> admin/flow.php portability UI; portable field list in Documentation → Flow Settings Fields.</p>

<h3 id="tab-identity">Identity Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['identity']); ?>">Open Feature: Identity tab</a></p>
<p><strong>One further step:</strong> Confirm the operator floscFlow name (dropdown only), Title (public offering), Tagline (one-line expansion of Title), and slug together, then open the generated flow URL. Visitors should see the attached personality, then Title, then Tagline — not the operator name and not the FLOSC acronym as the tagline.</p>
<p><strong>Procedure Level:</strong> Check floscFlow name, Title, Tagline, and slug together, then open the public URL to confirm the branding is correct.</p>
<p><strong>Tech Ref Level:</strong> Identity splits four layers: <code>identity.name</code> is the operator floscFlow name; the attached personality is the visitor-facing speaker; <code>identity.title</code> is the public offering; <code>identity.tagline</code> is the offering one-liner. Chatpack sends Title + Tagline as offering facts. FLOSC (the software) remains Freeline, Login, Offer, Sale, Content.</p>
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
<p><strong>AI Provider:</strong> The primary provider slug stored on this flow (<code>ai_provider</code>). API credentials resolve flow key first, then floscAvailableProviders (AI → All Flows AI API Management). Not the same as “which model” — model fields are separate per provider.</p>
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
<p><strong>Flow Scope:</strong> Required. Use one or more flow keys comma-separated (for example <code>flow_a_ivr,flow_b_ivr</code>) or <code>all</code>.</p>
<p><strong>VGM values:</strong> Use <code>visitor</code>, <code>guest</code>, <code>member</code>, or <code>all</code>.</p>
<p><strong>Payload fields:</strong> Keep catalog content flexible. Recommended payload columns are <code>Date</code>, <code>Title</code>, <code>Description</code>, <code>Lyrics</code>, <code>Media</code>, <code>Media Type</code>, and <code>Notes</code>. Additional columns are allowed and preserved.</p>
<p><strong>Catalog operations:</strong> DA1 supports create catalog, assign catalogs per flow, upload TSV, and export TSV. One flow can use multiple catalogs ordered by priority.</p>
<pre><code>Row Key	Parent Key	Catalog Key	Record Type	Flow Scope	VGM	Delivery Instruction	Delivery Rule	Fallback Order	Status	Date	Title	Description	Lyrics	Media	Media Type	Notes
85		music_core	work	flow_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active	1996	Time Flies	Rock collaboration				Primary work row
85.1	85	music_core	media	flow_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active		Time Flies	Live version		https://example.com/youtube-link	youtube	Live performance
85.2	85	music_core	media	flow_ivr	visitor	contextual match	preference	chatplayer &gt; media-link &gt; text	active		Time Flies	Short form clip		https://example.com/tiktok-link	tiktok	Social clip</code></pre>
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

<h3 id="member-autoprompts-memberpromptpanelshow-panel">🔵 member AutoPrompts — MemberPromptPanelShow panel</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['autoprompts']); ?>">Open Feature: AutoPrompts tab (Member section)</a></p>
<p><strong>One further step:</strong> Validate one learner autoprompt trigger phrase against your IVR messaging intent so member prompts remain behaviorally consistent.</p>
<p><strong>Procedure Level:</strong> Check one learner trigger phrase, then verify it still matches the intended member-facing prompt behavior.</p>
<p><strong>Tech Ref Level:</strong> Member prompts are the learned-state prompts shown after the user reaches member access in the flow.</p>
<p><strong>Code Level:</strong> admin/autoprompts.php renders the member panel, and the frontend prompt engine uses the saved member-state definitions.</p>

<h2 id="admin-doc-pass1-shell">Pass 1 Shell</h2>
<p>Use this shell pattern for each pass-1 section: feature destination link, one behavioral checkpoint, and one persistence or data-alignment check.</p>

<h2 id="admin-doc-pass2-links">Pass 2 Bidirectional Links</h2>
<p>This pass extends coverage across Content, Offers, and Token Management.</p>

<h3 id="tab-content">Content Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['content']); ?>">Open Feature: Content tab</a></p>
<p><strong>One further step:</strong> Create or confirm one member level plus one content group, then verify both appear in the active flow summary and in dependent grant or gating controls.</p>
<p><strong>Procedure Level:</strong> Confirm one member level slug, one content-group mapping, and one protection setting, then check that the same values appear in dependent tabs.</p>
<p><strong>Tech Ref Level:</strong> The Content tab stores member levels, content-group mappings (internally keyed as <code>content_item_groups</code>), access gates, and protection rules used across protected content delivery and offer grants.</p>
<p><strong>Code Level:</strong> admin/content.php edits the content structures, and access-control plus content-serving routines read the same stored arrays for permissions and delivery.</p>

<h3 id="tab-offers">Offers Tab</h3>
<p><strong>Payment processors (JIT):</strong> Each offer picks <code>paypal</code> or <code>stripe</code> (native — keys on Payments tab), <code>free</code>, or <code>redirect</code> (WooCommerce, Shopify, member sites, any checkout URL). FLOSC is not a full PSP; external carts redirect out, then you grant access via Access Code or member level.</p>
<p><a href="<?php echo esc_url($flosc_feature_links['offers']); ?>">Open Feature: Offers tab</a></p>
<p><strong>One further step:</strong> Save one offer with a single enabled display format and confirm status plus pricing fields persist after reload.</p>
<p><strong>Procedure Level:</strong> Save one offer, keep one display format active, and verify the price and status stay intact after reload.</p>
<p><strong>Tech Ref Level:</strong> Offer records carry display formats, triggers, pricing, and level grants used by the checkout and content gates.</p>
<p><strong>Code Level:</strong> admin/offers.php manages the offer editor, while the sale backend reads the stored offer definitions for rendering and payment flow.</p>

<h3 id="tab-token-management">Token Management Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['token-management']); ?>">Open Feature: Token Management tab</a></p>
<p><strong>One further step:</strong> Change one token cost or grant setting, save, then verify the same effective value appears in token-facing runtime summaries or ledger-related UI for the active flow.</p>
<p><strong>Procedure Level:</strong> Adjust one token setting, save it, and confirm the same value persists after reload for the selected flow.</p>
<p><strong>Tech Ref Level:</strong> Token Management controls visitor and member token costs, grants, and flow-scoped balance behavior used by chat consumption and refill logic.</p>
<p><strong>Code Level:</strong> admin/token-management.php renders the token controls, and the token ledger plus visitor-token traits read those stored values during runtime charging.</p>

<h2 id="admin-doc-pass2-shell">Pass 2 Shell</h2>
<p>For each added tab: link to the feature, record one runtime behavior check, and record one saved-state verification check.</p>

<h2 id="admin-doc-pass3-links">Pass 3 Bidirectional Links</h2>
<p>This pass extends coverage across Register &amp; Login, Style, and UI navigation.</p>

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
<p>This pass extends coverage across AI, Quiz, Email, and Contact Form.</p>

<h3 id="tab-ai">AI Tab</h3>
<?php
$flosc_ai_all_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_ref_ivr,
		'tab'  => 'ai',
		'view' => 'all',
	),
	admin_url( 'admin.php' )
);
$flosc_ai_single_url = add_query_arg(
	array(
		'page' => 'flosc-settings',
		'ivr'  => $flosc_ref_ivr,
		'tab'  => 'ai',
		'view' => 'single',
	),
	admin_url( 'admin.php' )
);
?>
<p><a href="<?php echo esc_url( $flosc_ai_single_url ); ?>">Open Feature: This flow — AI settings</a>
· <a href="<?php echo esc_url( $flosc_ai_all_url ); ?>">Open Feature: All Flows AI API Management</a></p>

<p>The AI tab is a <strong>dual view</strong> (same pattern as IVR Management single vs all-flows). Top buttons switch between them.</p>

<h4 id="tab-ai-all-flows">All Flows AI API Management (<code>view=all</code>)</h4>
<p><strong>Install-wide — not per-flow secrets.</strong></p>
<ol>
  <li><strong>Available Providers (floscAvailableProviders)</strong> — paste Anthropic / OpenAI / xAI / Gemini (chat) and AssemblyAI (STT) keys once. Status shows which keys are available to any current or future floscFlow. Leave a password field blank to keep the key on file.</li>
  <li><strong>Personalities library</strong> — define reusable personalities (label, name, role, traits, base prompt fields). Each floscFlow attaches <em>exactly one</em>. Personalities are never chained; only APIs chain.</li>
</ol>
<p><strong>Procedure Level:</strong> Keys first on a new install, then library entries, then switch to This flow to attach. Saving pool or library uses dedicated forms (not the main flow settings Save).</p>
<p><strong>Code Level:</strong> admin/ai-all-flows.php; options via flosc-available-providers.php and flosc-personality-library.php.</p>

<h4 id="tab-ai-this-flow">This flow: AI settings (<code>view=single</code>, default)</h4>
<p><strong>Per-flow attachment and tuning.</strong></p>
<ol>
  <li><strong>Attached personality</strong> — select a library id, or “Custom on this flow only” and edit fields on this screen. One personality only.</li>
  <li><strong>Primary AI Provider</strong> — <code>ivr</code> (scripted only) or <code>anthropic</code> / <code>openai</code> / <code>xai</code> / <code>gemini</code>. Key resolution: flow-local key if set, else floscAvailableProviders. STT is separate: AssemblyAI, OpenAI Whisper, or custom.</li>
  <li><strong>API chain</strong> (optional) — ordered provider hops for one visitor turn. Intermediate hops are internal; the visitor still sees one userMessage → one assistantMessage. Each hop needs a key (flow or available pool). Personalities do not chain.</li>
  <li><strong>Models, temperature, phase prompts, knowledge files, STT</strong> — remaining per-flow AI policy on the same screen.</li>
  <li><strong>Accuracy test</strong> — up to ten template rows (placeholders <code>{flow_name}</code> operator name, <code>{title}</code> offering, <code>{tagline}</code> one-liner, <code>{topic_scope}</code>, <code>{site_name}</code>). Editor stores templates; “User input (sent to AI)” preview shows the expanded userMessage. Results pair userMessage with assistantMessage (AI response). Ten completed rows = ten turns. See Glossary → Chat turns and messages.</li>
</ol>
<p><strong>One further step:</strong> Confirm Install keys available (✓) on This flow; if a needed provider shows —, open All Flows AI API Management and set the key. Attach one personality, pick primary provider, save, then run one accuracy-test row and confirm user input / AI response columns.</p>
<p><strong>Procedure Level:</strong> Never expect portable YAML to install secrets. After Create/Apply on Flow, re-check available keys and personality attach on the target site.</p>
<p><strong>Tech Ref Level:</strong> Flow bag stores <code>personality_library_id</code>, <code>ai_provider</code>, chain fields, models, prompts, <code>ai_accuracy_test_questions</code> (newline templates). Install options: <code>flosc_available_providers</code>, personality library option. Prompt assembly and dispatch read resolved personality + key.</p>
<p><strong>Code Level:</strong> admin/ai-configuration.php (single + view switch); admin/ai-all-flows.php (all); dispatch/chatpack resolve keys and personality at runtime.</p>

<p><strong>Related docs:</strong>
<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'documentation', 'doc' => 'ref-ai-config' ), admin_url( 'admin.php' ) ) ); ?>">AI Configuration Guide</a>
· <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'documentation', 'doc' => 'glossary' ), admin_url( 'admin.php' ) ) ); ?>#glossary-turns">Glossary: turns &amp; messages</a>
· <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'documentation', 'doc' => 'glossary' ), admin_url( 'admin.php' ) ) ); ?>#term-available-providers">Available Providers</a>
</p>

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

<h3 id="tab-contact-form">Contact Form Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['contact-form']); ?>">Open Feature: Contact Form tab</a></p>
<p><strong>One further step:</strong> Edit one label or destination field, save, then verify the same contact configuration remains visible and is still tied to the active flow after reload.</p>
<p><strong>Procedure Level:</strong> Change one contact form field, save it, and confirm the same contact behavior settings persist for the selected flow.</p>
<p><strong>Tech Ref Level:</strong> Contact Form settings control the contact capture copy, destinations, and follow-up behavior used when the flow collects direct outreach details.</p>
<p><strong>Code Level:</strong> admin/contact-form.php renders the contact controls, and the submission handling code reads the same stored settings when processing outreach forms.</p>

<h2 id="admin-doc-pass4-shell">Pass 4 Shell</h2>
<p>For each added tab: link to the feature, record one provider or content behavior check, and record one save/reload verification check.</p>

<h2 id="admin-doc-pass5-links">Pass 5 Bidirectional Links</h2>
<p>This pass extends coverage across Payments, SSO, and Engagement.</p>

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

<h3 id="tab-engagement">Engagement Tab</h3>
<p><a href="<?php echo esc_url($flosc_feature_links['engagement']); ?>">Open Feature: Engagement tab</a></p>
<p><strong>One further step:</strong> Adjust one engagement threshold or action, save, then verify the same threshold remains stored and visible for the active flow after reload.</p>
<p><strong>Procedure Level:</strong> Change one engagement rule, save it, and confirm the same rule remains associated with the selected flow.</p>
<p><strong>Tech Ref Level:</strong> Engagement settings define when user behavior triggers prompts, access changes, or progression actions for visitor, guest, and member states.</p>
<p><strong>Code Level:</strong> admin/engagement.php renders the controls, and the session or state-transition logic reads the stored thresholds and actions during runtime evaluation.</p>

<h2 id="admin-doc-pass5-shell">Pass 5 Shell</h2>
<p>For each added tab: link to the feature, record one configuration-behavior checkpoint, and record one active-flow verification checkpoint.</p>

<h2 id="admin-doc-pass6-links">Pass 6 Bidirectional Links</h2>
<p>This pass completes the remaining operational tabs and final reconciliation steps.</p>

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

<h2 id="admin-parameter-inventory">Tab Parameter Inventory (Docs-First Coverage)</h2>
<p>This inventory is derived from the current save pipeline in <code>admin/settings.php</code>. It keeps every tab and parameter family in one operator view before portability testing.</p>

<h3 id="inventory-parameter-link-pattern">Parameter Link Pattern</h3>
<p>For parameter-level help links, use this pair: feature destination to the tab plus docs destination to the exact parameter family anchor.</p>
<ul>
  <li><strong>Feature:</strong> <code>?page=flosc-settings&amp;ivr={flow_file}&amp;tab={tab_id}</code></li>
  <li><strong>Docs:</strong> <code>?page=flosc-settings&amp;ivr={flow_file}&amp;tab=documentation&amp;doc=ref-admin#{parameter_anchor}</code></li>
</ul>

<h3 id="inventory-flow-family">Flow/Identity/IVR Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['flow']); ?>">Flow tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-flow-family'); ?>">Flow parameter docs</a></p>
<ul>
  <li><strong>flow:</strong> flow routing and lifecycle keys stored via generic <code>flow_*</code> post mapping.</li>
  <li><strong>identity:</strong> identity bundle and public-policy content keys (operator <code>name</code>, public offering <code>title</code>, one-line <code>tagline</code>, domain/slug plus policy text fields).</li>
  <li><strong>ivr-messages:</strong> IVR message arrays and file sync-managed message payload fields.</li>
</ul>

<h3 id="inventory-content-family">Content and Access Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['content']); ?>">Content tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-content-family'); ?>">Content parameter docs</a></p>
<ul>
  <li><strong>content:</strong> <code>member_levels</code>, <code>protected_content</code>, <code>content_item_groups</code>, <code>content_item_category</code>, <code>content_types</code>, free-lesson and guest access constraints.</li>
  <li><strong>trajectories:</strong> trajectory and progression structures for content journey mapping.</li>
  <li><strong>offers:</strong> offer records, pricing, processor mapping, and access grant metadata.</li>
</ul>

<h3 id="inventory-auth-family">Authentication and Access Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['login']); ?>">Login tab</a> · <a href="<?php echo esc_url($flosc_feature_links['sso']); ?>">SSO tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-auth-family'); ?>">Auth parameter docs</a></p>
<ul>
  <li><strong>login:</strong> guest-link and magic-link controls including <code>magic_access_links_enabled</code>, <code>guest_link_max_uses</code>, and <code>guest_link_window_days</code>.</li>
  <li><strong>sso:</strong> provider enable toggles and provider credential fields (<code>sso_google_*</code>, <code>sso_apple_*</code>, <code>sso_facebook_*</code>, <code>sso_microsoft_*</code>, <code>sso_linkedin_*</code>).</li>
  <li><strong>administration:</strong> operational toggles and runtime/admin control keys.</li>
</ul>

<h3 id="inventory-ai-family">AI and Prompt Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['ai']); ?>">AI tab</a> · <a href="<?php echo esc_url($flosc_feature_links['autoprompts']); ?>">AutoPrompts tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-ai-family'); ?>">AI/Prompt parameter docs</a> · <a href="<?php echo esc_url( $flosc_ref_admin_doc_url . '#tab-ai' ); ?>">AI dual-view procedures</a></p>
<ul>
  <li><strong>ai (this flow):</strong> <code>personality_library_id</code>, <code>ai_provider</code>, model/temperature/max tokens, phase prompts, <code>ai_enable_ivr_context</code>, <code>ai_enable_content_access</code>, <code>ai_enable_chaining</code> + <code>ai_chain_provider_*</code>, STT keys policy, <code>ai_accuracy_test_questions</code> (template lines), custom personality fields when not attached to library.</li>
  <li><strong>ai (install, All Flows view):</strong> <code>flosc_available_providers</code> (secret keys — not portable); personality library entries (install option — attach via id on each flow).</li>
  <li><strong>Key resolve order:</strong> flow bag key first, then floscAvailableProviders for that provider slug.</li>
  <li><strong>autoprompts:</strong> visitor/guest/member prompt panel rows and action/condition mappings (each pill’s sent text is a userMessage / userInput).</li>
  <li><strong>concierge:</strong> companion/concierge route targets, launcher behavior, contextual trigger rules, and display controls.</li>
</ul>

<h3 id="inventory-quiz-family">Quiz Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['quiz']); ?>">Quiz tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-quiz-family'); ?>">Quiz parameter docs</a></p>
<ul>
  <li><strong>quiz:</strong> quiz enablement and content fields including <code>enabled_quizzes</code>, quiz content blocks, and audio controls such as <code>audio_quiz_escape_after_phrase</code>.</li>
  <li><strong>Optional by design:</strong> empty <code>enabled_quizzes</code> is valid. A flow can intentionally run with no quiz, and frontend guards must respect that state.</li>
  <li><strong>email coupling:</strong> optional quiz-completion email behavior via <code>email_on_quiz_complete</code> and template followups.</li>
</ul>

<h3 id="inventory-token-family">Token and Payments Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['token-management']); ?>">Token Management tab</a> · <a href="<?php echo esc_url($flosc_feature_links['payments']); ?>">Payments tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-token-family'); ?>">Token/Payments parameter docs</a></p>
<ul>
  <li><strong>token-management:</strong> token economy fields (<code>tokens_*_numerator/denominator</code>, per-message cost, grants, caps, enforcement, visitor threshold messaging).</li>
  <li><strong>product token grants:</strong> per-offer token settings synchronized into offer payload under <code>offers[*].tokens</code>.</li>
  <li><strong>payments:</strong> processor enablement (<code>stripe_enabled</code>, <code>paypal_enabled</code>, <code>manual_payments_enabled</code>) and related credential fields.</li>
</ul>

<h3 id="inventory-communication-family">Communication and Engagement Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['email']); ?>">Email tab</a> · <a href="<?php echo esc_url($flosc_feature_links['contact-form']); ?>">Contact Form tab</a> · <a href="<?php echo esc_url($flosc_feature_links['engagement']); ?>">Engagement tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-communication-family'); ?>">Communication parameter docs</a></p>
<ul>
  <li><strong>email:</strong> sender/template fields, recurring follow-up arrays, and re-engagement toggles.</li>
  <li><strong>contact-form:</strong> title/intro/submit/success/destination plus anti-abuse limits and form styling keys.</li>
  <li><strong>engagement:</strong> <code>engagement_rules</code> array (audience, trigger, condition, action_chat, action_email, template, days) and legacy sync fields <code>email_reengagement_enabled</code> / <code>email_reengagement_days</code>.</li>
</ul>

<h3 id="inventory-ui-family">UI and Operations Family</h3>
<p><strong>Parameter links:</strong> <a href="<?php echo esc_url($flosc_feature_links['style']); ?>">Style tab</a> · <a href="<?php echo esc_url($flosc_feature_links['ui']); ?>">Profile Bar tab</a> · <a href="<?php echo esc_url($flosc_feature_links['chat-logs']); ?>">Chat Logs tab</a> · <a href="<?php echo esc_url($flosc_feature_links['administration']); ?>">Administration tab</a> · <a href="<?php echo esc_url($flosc_feature_links['da1']); ?>">DA1 tab</a> · <a href="<?php echo esc_url($flosc_ref_admin_doc_url . '#inventory-ui-family'); ?>">UI/Ops parameter docs</a></p>
<ul>
  <li><strong>style:</strong> theme and presentation values consumed by frontend CSS/token rendering.</li>
  <li><strong>ui:</strong> chat navigation labels, welcome strings, avatar/profile radius, and state presentation controls.</li>
  <li><strong>chat-logs:</strong> log viewing/rating operational controls and log query behavior.</li>
  <li><strong>documentation:</strong> docs topic selection and reference navigation state.</li>
  <li><strong>da1:</strong> dataset catalog assignment, upload, export, and flow scope mappings.</li>
</ul>

<p class="skeleton-marker">This ordering is now the required workflow for documentation passes in FLOSC 8.0.1 planning and forward releases.</p>
