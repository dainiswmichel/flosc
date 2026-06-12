<?php if (!defined('ABSPATH')) exit; // Core reference content derived from flosc.php ?>

<h2 id="core-version-identity">Version and Identity</h2>
<p><strong>Source of truth:</strong> <code>flosc.php</code> plugin header and constants.</p>
<ul>
  <li><code>Version</code>: <strong>8.0.0</strong> (plugin header)</li>
  <li><code>FLOSC_VERSION</code>: <strong>8.0.0</strong></li>
  <li><code>FLOSC_DEBUG</code>: runtime mode from <code>flosc_debug_mode</code> option: inherit / on / off</li>
  <li><code>FLOSC_PLUGIN_DIR</code> and <code>FLOSC_PLUGIN_URL</code> define canonical plugin paths</li>
</ul>

<h2 id="core-lifecycle">Lifecycle Hooks</h2>
<ul>
  <li><strong>Activation:</strong> <code>flosc_activate()</code> registers roles, migrates legacy flow data, imports IVR, ensures chat log table, sets defaults, flushes rewrite rules.</li>
  <li><strong>Activation flush helper:</strong> <code>flosc_activation_flush()</code> sets deferred rewrite flush flag.</li>
  <li><strong>Version flush check:</strong> <code>flosc_version_flush_check()</code> performs rewrite flush on version change and backfills new flow defaults.</li>
  <li><strong>Deactivation:</strong> <code>flosc_deactivate()</code> clears FLOSC cron hooks and flushes rewrite rules.</li>
  <li><strong>Uninstall:</strong> handled in <code>uninstall.php</code> (deletion-time cleanup routine).</li>
</ul>

<h2 id="core-bootstrap">Runtime Bootstrap</h2>
<p><code>FLOSC_Framework::instance()</code> builds the singleton and then executes <code>boot()</code>, which calls:</p>
<ol>
  <li><code>load_dependencies()</code> to include core, IVR, RAG, SALE, SSO, and lesson systems</li>
  <li><code>init_hooks()</code> to register routing, admin, REST, auth token, cron, AJAX, and plugin integration hooks</li>
</ol>

<h2 id="core-auth-model">Authentication Model for API Calls</h2>
<ul>
  <li>WordPress cookie auth remains primary when available.</li>
  <li>Cross-domain fallback uses FLOSC token auth via <code>X-FLOSC-Token</code> header or <code>flosc_auth_token</code> cookie.</li>
  <li>Token validation is stateless HMAC with expiry.</li>
  <li><code>allow_flosc_token_auth()</code> bypasses WP REST nonce check only when FLOSC token auth was used.</li>
</ul>

<h2 id="core-rest-overview">REST API Overview</h2>
<p>Namespace: <code>/wp-json/flosc/v1</code>. Routes are registered in <code>register_rest_routes()</code>.</p>

<h3 id="core-rest-permission-patterns">Permission Patterns</h3>
<ul>
  <li><code>check_public_endpoint_permission()</code>: public endpoints with rate limiting.</li>
  <li><code>check_paid_endpoint_permission()</code>: stricter limits for expensive operations.</li>
  <li><code>is_user_logged_in</code>: authenticated user routes.</li>
  <li><code>__return_true</code>: open endpoints where security is implemented in handler logic (for example webhooks and payment start flows).</li>
  <li><code>current_user_can('manage_options')</code>: admin-only tools.</li>
</ul>

<h3 id="core-rest-route-groups">Route Groups</h3>
<ul>
  <li><strong>Chat and IVR:</strong> <code>/chat</code>, <code>/chat-rag</code>, <code>/ivr-messages</code>, <code>/ivr/messages</code>, <code>/ivr/track</code></li>
  <li><strong>Quiz and scoring:</strong> <code>/quiz</code> (GET/POST), <code>/quiz-result</code>, <code>/process-quiz</code>, <code>/store-score</code>, <code>/funnel-complete</code>, <code>/store-visitor-audio</code>, <code>/score-pending-audio</code>, <code>/store-quiz-data</code></li>
  <li><strong>Audio/STT:</strong> <code>/process-audio</code>, <code>/transcribe</code></li>
  <li><strong>Sessions:</strong> <code>/sessions</code> (GET/POST), <code>/sessions/{id}</code> (GET/PUT/DELETE)</li>
  <li><strong>Offers and purchase:</strong> <code>/offers</code>, <code>/offer-content</code>, <code>/purchase</code>, <code>/sandbox-purchase</code>, <code>/free-lesson</code></li>
  <li><strong>Payments:</strong> <code>/create-payment-intent</code>, <code>/complete-purchase</code>, <code>/paypal/create-order</code>, <code>/paypal/capture-order</code>, <code>/paypal/get-plans</code>, <code>/paypal/activate-subscription</code>, <code>/webhooks/{provider}</code></li>
  <li><strong>Access and intent:</strong> <code>/access</code>, <code>/tokens</code>, <code>/intents</code>, <code>/intents/{id}/offers</code>, <code>/referral</code></li>
  <li><strong>Lessons:</strong> <code>/lessons</code>, <code>/lessons/{id}</code>, <code>/lessons/free</code></li>
  <li><strong>Utility/admin:</strong> <code>/nonce</code>, <code>/test-ai</code>, <code>/register-email</code>, <code>/update-guest-profile</code>, <code>/feedback</code>, <code>/praises</code>, <code>/redeem-access-code</code></li>
</ul>

<h2 id="core-debug-endpoints">Debug and Conditional Routes</h2>
<ul>
  <li><code>/debug/funnel-state</code> is only registered when <code>FLOSC_DEBUG</code> is true.</li>
  <li>Some permissions are intentionally open for provider callbacks, with signature/handler checks done inside callback functions.</li>
</ul>

<h2 id="core-admin-hooks-summary">Admin and Operations Hooks</h2>
<ul>
  <li>Admin UI: <code>admin_menu</code>, <code>admin_init</code>, <code>admin_enqueue_scripts</code></li>
  <li>Maintenance cron: <code>flosc_cleanup_visitor_audio</code>, <code>flosc_guest_followup_cron</code></li>
  <li>Profile/media operations and chat logs have dedicated AJAX handlers.</li>
  <li>Third-party quiz integrations supported by hooks for Wp-Pro-Quiz, LearnDash, and QSM when enabled.</li>
</ul>

<h2 id="core-flow-resolution">Flow Resolution Priority</h2>
<ol>
  <li>Forced flow context via <code>set_flow_context()</code> (REST/domain-independent use cases)</li>
  <li>Rewrite query variable <code>flosc_ivr</code></li>
  <li>Custom domain mapping in flow settings</li>
  <li>Slug match from request URI</li>
</ol>

<p class="skeleton-marker">This section was generated from current code behavior and is intended as the baseline source map for future deep per-method documentation.</p>
