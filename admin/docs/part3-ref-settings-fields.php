<?php
if (!defined('ABSPATH')) exit;
/**
 * Field-level techref: portable flow settings (Settings YAML).
 * Generated 2026-08-11 from inventory + code-derived descriptions.
 * Secrets are listed separately and are never portable.
 */

$flosc_ref_ivr = isset($selected_ivr) ? sanitize_file_name((string) $selected_ivr) : (isset($flosc_selected_ivr) ? sanitize_file_name((string) $flosc_selected_ivr) : '');
if (!isset($flosc_feature_links) || !is_array($flosc_feature_links)) { $flosc_feature_links = []; }
?>
<h1 id="ref-settings-fields">Part 3: Reference — Flow settings fields</h1>
<p>Each entry is a flow option key. <strong>Portable: yes</strong> means the value may travel in the flow IVR file under <code>## Settings (YAML)</code>. Secrets never appear in the pack.</p>
<p><strong>Admin link format:</strong> <code>?page=flosc-settings&amp;tab={tab_id}</code></p>
<h2 id="settings-secrets">Secrets (not portable)</h2>
<ul>
<li><code>anthropic_api_key</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>assemblyai_api_key</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>flosc_cncrg_password</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>message_individual_password</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>openai_api_key</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>paypal_secret</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>stripe_live_sk</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>stripe_test_sk</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>stripe_webhook_secret</code> — configure on the target site only; never export into Settings YAML.</li>
<li><code>xai_api_key</code> — configure on the target site only; never export into Settings YAML.</li>
</ul>
<h2 id="settings-portable-index">Portable fields by tab</h2>
<h3 id="settings-tab-flow">Flow <code>flow</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'flow' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-delete_ivr_file"><code>delete_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>delete_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-duplicate_ivr_file"><code>duplicate_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>duplicate_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_delete_ivr_file"><code>flosc_delete_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_delete_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_duplicate_ivr_file"><code>flosc_duplicate_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_duplicate_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_import_selected_ivr_file"><code>flosc_import_selected_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_import_selected_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_upload_ivr_file"><code>flosc_upload_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_upload_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_upload_redirect_tab"><code>flosc_upload_redirect_tab</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_upload_redirect_tab</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-flosc_upload_redirect_view"><code>flosc_upload_redirect_view</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>flosc_upload_redirect_view</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-import_ivr_file"><code>import_ivr_file</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>import_ivr_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h4 id="field-ivr_file_upload"><code>ivr_file_upload</code></h4>
<p>Flow option used on the <strong>Flow</strong> admin tab. Stored as <code>ivr_file_upload</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Flow</li>
</ul>
<h3 id="settings-tab-identity">Identity <code>identity</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'identity' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-badgeUrl"><code>badgeUrl</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>badgeUrl</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-chatlogo_url"><code>chatlogo_url</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>chatlogo_url</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-data_deletion_content"><code>data_deletion_content</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>data_deletion_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-domain"><code>domain</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>domain</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-favicon_url"><code>favicon_url</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>favicon_url</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-flosc_save"><code>flosc_save</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>flosc_save</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-flosc_save_all_flows"><code>flosc_save_all_flows</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>flosc_save_all_flows</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-flosc_save_flow"><code>flosc_save_flow</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>flosc_save_flow</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-name"><code>name</code></h4>
<p>Display name for the flow.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-platform_compliance_content"><code>platform_compliance_content</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>platform_compliance_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-primary_color"><code>primary_color</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>primary_color</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-privacy_policy_content"><code>privacy_policy_content</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>privacy_policy_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-slug"><code>slug</code></h4>
<p>Public URL slug for the full-page flow app route.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-status"><code>status</code></h4>
<p>Whether the flow is treated as active.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-tagline"><code>tagline</code></h4>
<p>Short tagline for the flow identity.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-terms_of_service_content"><code>terms_of_service_content</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>terms_of_service_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-title"><code>title</code></h4>
<p>Title used in UI / identity contexts.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h4 id="field-view"><code>view</code></h4>
<p>Flow option used on the <strong>Identity</strong> admin tab. Stored as <code>view</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Identity</li>
</ul>
<h3 id="settings-tab-ivr-messages">IVR Management <code>ivr-messages</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ivr-messages' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_clear_ivr_db"><code>flosc_clear_ivr_db</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_clear_ivr_db</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_confirm_import"><code>flosc_confirm_import</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_confirm_import</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_export_ivr"><code>flosc_export_ivr</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_export_ivr</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_force_resync"><code>flosc_force_resync</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_force_resync</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_import_mode"><code>flosc_import_mode</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_import_mode</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_preview_import"><code>flosc_preview_import</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_preview_import</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-flosc_save_full_ivr"><code>flosc_save_full_ivr</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>flosc_save_full_ivr</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-ivr_full_text"><code>ivr_full_text</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>ivr_full_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_action"><code>message_action</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_action</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_conditions"><code>message_conditions</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_conditions</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_content"><code>message_content</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_discount_price"><code>message_discount_price</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_discount_price</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_display_format"><code>message_display_format</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_display_format</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_html_file"><code>message_html_file</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_html_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_icon"><code>message_icon</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_icon</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_id"><code>message_id</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_keywords"><code>message_keywords</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_keywords</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_name"><code>message_name</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_name</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_offer_id"><code>message_offer_id</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_offer_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_password_max_tries"><code>message_password_max_tries</code></h4>
<p>IVR password-gate copy or try-limit for gated messages. Stored as <code>message_password_max_tries</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_password_prompt"><code>message_password_prompt</code></h4>
<p>IVR password-gate copy or try-limit for gated messages. Stored as <code>message_password_prompt</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_password_retry"><code>message_password_retry</code></h4>
<p>IVR password-gate copy or try-limit for gated messages. Stored as <code>message_password_retry</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_password_success"><code>message_password_success</code></h4>
<p>IVR password-gate copy or try-limit for gated messages. Stored as <code>message_password_success</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_phase"><code>message_phase</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_phase</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_post_id"><code>message_post_id</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_post_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_price"><code>message_price</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_price</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_style"><code>message_style</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_style</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_timer"><code>message_timer</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_timer</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_type"><code>message_type</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_type</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_user_input"><code>message_user_input</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_user_input</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-message_woo_product"><code>message_woo_product</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>message_woo_product</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h4 id="field-save_ivr_message"><code>save_ivr_message</code></h4>
<p>Flow option used on the <strong>IVR Management</strong> admin tab. Stored as <code>save_ivr_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> IVR Management</li>
</ul>
<h3 id="settings-tab-autoprompts">AutoPrompts <code>autoprompts</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'autoprompts' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_flow_key"><code>flosc_flow_key</code></h4>
<p>Flow option used on the <strong>AutoPrompts</strong> admin tab. Stored as <code>flosc_flow_key</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AutoPrompts</li>
</ul>
<h4 id="field-flosc_ivr"><code>flosc_ivr</code></h4>
<p>Flow option used on the <strong>AutoPrompts</strong> admin tab. Stored as <code>flosc_ivr</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AutoPrompts</li>
</ul>
<h4 id="field-panel_enabled_companion"><code>panel_enabled_companion</code></h4>
<p>Flow option used on the <strong>AutoPrompts</strong> admin tab. Stored as <code>panel_enabled_companion</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AutoPrompts</li>
</ul>
<h3 id="settings-tab-content">Content <code>content</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'content' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-content_item_group_category"><code>content_item_group_category</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>content_item_group_category</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-content_item_group_quiz"><code>content_item_group_quiz</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>content_item_group_quiz</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-content_type_plural"><code>content_type_plural</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>content_type_plural</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-content_type_singular"><code>content_type_singular</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>content_type_singular</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-exclude_items_from_freeline"><code>exclude_items_from_freeline</code></h4>
<p>Content-item numbers the freeline grant algorithm must never pick, even if in the pool category.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-free_content_item_count"><code>free_content_item_count</code></h4>
<p>How many freeline (complimentary) content items the guest-selection path may grant when mode is fixed.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-free_content_item_guaranteed"><code>free_content_item_guaranteed</code></h4>
<p>Optional bonus content-item number always considered for freeline (0 = off).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-free_content_item_mode"><code>free_content_item_mode</code></h4>
<p>Freeline selection mode: fixed count or proportion of missed (quiz path).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-free_content_item_pool_category"><code>free_content_item_pool_category</code></h4>
<p>WordPress category slug whose posts may be chosen as freeline samples.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-free_content_item_proportion"><code>free_content_item_proportion</code></h4>
<p>When mode is proportion: fraction of missed items used for freeline selection.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-guest_access_days"><code>guest_access_days</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_access_days</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-guest_can_delete_chats"><code>guest_can_delete_chats</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_can_delete_chats</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-guest_can_rename_chats"><code>guest_can_rename_chats</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_can_rename_chats</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-guest_max_chats"><code>guest_max_chats</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_max_chats</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-guest_new_chat_limit_message"><code>guest_new_chat_limit_message</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_new_chat_limit_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-level_description"><code>level_description</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>level_description</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-level_name"><code>level_name</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>level_name</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-level_slug"><code>level_slug</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>level_slug</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-protection_level"><code>protection_level</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>protection_level</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-protection_type"><code>protection_type</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>protection_type</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h4 id="field-protection_value"><code>protection_value</code></h4>
<p>Flow option used on the <strong>Content</strong> admin tab. Stored as <code>protection_value</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Content</li>
</ul>
<h3 id="settings-tab-trajectories">Trajectories <code>trajectories</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'trajectories' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_create_trajectory_post"><code>flosc_create_trajectory_post</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_create_trajectory_post</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_toggle_trajectory_post"><code>flosc_toggle_trajectory_post</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_toggle_trajectory_post</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trajectory_set"><code>flosc_trajectory_set</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trajectory_set</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_flow"><code>flosc_trj_flow</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_flow</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_instructions"><code>flosc_trj_instructions</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_instructions</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_keywords"><code>flosc_trj_keywords</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_keywords</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_off_ramp_exactness"><code>flosc_trj_off_ramp_exactness</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_off_ramp_exactness</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_off_ramp_phrases"><code>flosc_trj_off_ramp_phrases</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_off_ramp_phrases</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_priority"><code>flosc_trj_priority</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_priority</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h4 id="field-flosc_trj_title"><code>flosc_trj_title</code></h4>
<p>Flow option used on the <strong>Trajectories</strong> admin tab. Stored as <code>flosc_trj_title</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Trajectories</li>
</ul>
<h3 id="settings-tab-offers">Offers <code>offers</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'offers' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_offer_token_amount"><code>flosc_offer_token_amount</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>flosc_offer_token_amount</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-flosc_offer_token_cap"><code>flosc_offer_token_cap</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>flosc_offer_token_cap</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-flosc_offer_token_cap_mode"><code>flosc_offer_token_cap_mode</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>flosc_offer_token_cap_mode</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-flosc_offer_token_mode"><code>flosc_offer_token_mode</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>flosc_offer_token_mode</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-flosc_offer_token_source"><code>flosc_offer_token_source</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>flosc_offer_token_source</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_card_enabled"><code>fmt_card_enabled</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_card_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_featured_enabled"><code>fmt_featured_enabled</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_featured_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_pill_enabled"><code>fmt_pill_enabled</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_pill_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_pill_icon"><code>fmt_pill_icon</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_pill_icon</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_pill_label"><code>fmt_pill_label</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_pill_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_pill_phrase"><code>fmt_pill_phrase</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_pill_phrase</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-fmt_pill_target_panel"><code>fmt_pill_target_panel</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>fmt_pill_target_panel</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_access_codes"><code>offer_access_codes</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_access_codes</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_active"><code>offer_active</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_active</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_after_offer_id"><code>offer_after_offer_id</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_after_offer_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_badge"><code>offer_badge</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_badge</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_condition"><code>offer_condition</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_condition</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_coupon_code"><code>offer_coupon_code</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_coupon_code</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_coupon_type"><code>offer_coupon_type</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_coupon_type</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_coupon_valid_from"><code>offer_coupon_valid_from</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_coupon_valid_from</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_coupon_valid_until"><code>offer_coupon_valid_until</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_coupon_valid_until</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_coupon_value"><code>offer_coupon_value</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_coupon_value</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_cta"><code>offer_cta</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_cta</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_currency"><code>offer_currency</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_currency</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_description"><code>offer_description</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_description</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_display_price"><code>offer_display_price</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_display_price</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_external_sale_note"><code>offer_external_sale_note</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_external_sale_note</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_features"><code>offer_features</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_features</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_frequency_max_shows"><code>offer_frequency_max_shows</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_frequency_max_shows</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_frequency_scope"><code>offer_frequency_scope</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_frequency_scope</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_grants_level"><code>offer_grants_level</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_grants_level</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_guarantee"><code>offer_guarantee</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_guarantee</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_headline"><code>offer_headline</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_headline</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_icon"><code>offer_icon</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_icon</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_id"><code>offer_id</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_match_type"><code>offer_match_type</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_match_type</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_name"><code>offer_name</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_name</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_original_price"><code>offer_original_price</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_original_price</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_presentation_priority"><code>offer_presentation_priority</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_presentation_priority</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_price"><code>offer_price</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_price</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_processor"><code>offer_processor</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_processor</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_redirect_url"><code>offer_redirect_url</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_redirect_url</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_reveal_delay_seconds"><code>offer_reveal_delay_seconds</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_reveal_delay_seconds</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_reveal_event"><code>offer_reveal_event</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_reveal_event</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_reveal_phrase"><code>offer_reveal_phrase</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_reveal_phrase</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_savings"><code>offer_savings</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_savings</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_show_coupon_field"><code>offer_show_coupon_field</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_show_coupon_field</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_stripe_price_id"><code>offer_stripe_price_id</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_stripe_price_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_stripe_product_id"><code>offer_stripe_product_id</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_stripe_product_id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_timer"><code>offer_timer</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_timer</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_trigger"><code>offer_trigger</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_trigger</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-offer_type"><code>offer_type</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>offer_type</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h4 id="field-save_offer"><code>save_offer</code></h4>
<p>Flow option used on the <strong>Offers</strong> admin tab. Stored as <code>save_offer</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Offers</li>
</ul>
<h3 id="settings-tab-login">Register &amp; Login <code>login</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'login' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-auth_login_modal_button_text"><code>auth_login_modal_button_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_button_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_dismiss_message"><code>auth_login_modal_dismiss_message</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_dismiss_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_email_label"><code>auth_login_modal_email_label</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_email_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_email_placeholder"><code>auth_login_modal_email_placeholder</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_email_placeholder</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_loading_text"><code>auth_login_modal_loading_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_loading_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_sso_divider"><code>auth_login_modal_sso_divider</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_sso_divider</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_subtitle"><code>auth_login_modal_subtitle</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_subtitle</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_success_message"><code>auth_login_modal_success_message</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_success_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_terms_text"><code>auth_login_modal_terms_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_terms_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_login_modal_title"><code>auth_login_modal_title</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_login_modal_title</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_button_text"><code>auth_modal_button_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_button_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_dismiss_message"><code>auth_modal_dismiss_message</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_dismiss_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_email_label"><code>auth_modal_email_label</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_email_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_email_placeholder"><code>auth_modal_email_placeholder</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_email_placeholder</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_loading_text"><code>auth_modal_loading_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_loading_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_sso_divider"><code>auth_modal_sso_divider</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_sso_divider</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_subtitle"><code>auth_modal_subtitle</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_subtitle</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_success_message"><code>auth_modal_success_message</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_success_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_terms_text"><code>auth_modal_terms_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_terms_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-auth_modal_title"><code>auth_modal_title</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>auth_modal_title</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-email"><code>email</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>email</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_check_email_message"><code>guest_link_check_email_message</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_check_email_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_email_subject"><code>guest_link_email_subject</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_email_subject</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_expired_offer_url"><code>guest_link_expired_offer_url</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_expired_offer_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_max_uses"><code>guest_link_max_uses</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_max_uses</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_name"><code>guest_link_name</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_profile_confirm_message"><code>guest_link_profile_confirm_message</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_profile_confirm_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_upgrade_url"><code>guest_link_upgrade_url</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_upgrade_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_welcome_message"><code>guest_link_welcome_message</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_welcome_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-guest_link_window_days"><code>guest_link_window_days</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_link_window_days</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-header_login_action"><code>header_login_action</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>header_login_action</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-header_login_text"><code>header_login_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>header_login_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-header_signup_action"><code>header_signup_action</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>header_signup_action</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-header_signup_text"><code>header_signup_text</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>header_signup_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-id"><code>id</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>id</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-magic_access_links_enabled"><code>magic_access_links_enabled</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>magic_access_links_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-magic_link_admin_send_condition"><code>magic_link_admin_send_condition</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>magic_link_admin_send_condition</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-member_link_welcome_message"><code>member_link_welcome_message</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>member_link_welcome_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-user_status_admin"><code>user_status_admin</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>user_status_admin</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-user_status_guest"><code>user_status_guest</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>user_status_guest</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-user_status_member"><code>user_status_member</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>user_status_member</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-user_status_member_level"><code>user_status_member_level</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>user_status_member_level</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h4 id="field-user_status_visitor"><code>user_status_visitor</code></h4>
<p>Flow option used on the <strong>Register &amp; Login</strong> admin tab. Stored as <code>user_status_visitor</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Register &amp; Login</li>
</ul>
<h3 id="settings-tab-style">Style &amp; Nav <code>style</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'style' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-chat_style_accent"><code>chat_style_accent</code></h4>
<p>Chat appearance setting (preset, bubble, accent, font, or scale). Stored as <code>chat_style_accent</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-chat_style_bubble"><code>chat_style_bubble</code></h4>
<p>Chat appearance setting (preset, bubble, accent, font, or scale). Stored as <code>chat_style_bubble</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-chat_style_font"><code>chat_style_font</code></h4>
<p>Chat appearance setting (preset, bubble, accent, font, or scale). Stored as <code>chat_style_font</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-chat_style_preset"><code>chat_style_preset</code></h4>
<p>Chat appearance setting (preset, bubble, accent, font, or scale). Stored as <code>chat_style_preset</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-chat_style_scale"><code>chat_style_scale</code></h4>
<p>Chat appearance setting (preset, bubble, accent, font, or scale). Stored as <code>chat_style_scale</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_accent_color"><code>companion_accent_color</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_accent_color</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_allow_escape_close"><code>companion_allow_escape_close</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_allow_escape_close</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_auto_open_delay_ms"><code>companion_auto_open_delay_ms</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_auto_open_delay_ms</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_auto_open_enabled"><code>companion_auto_open_enabled</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_auto_open_enabled</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_auto_open_once_per_session"><code>companion_auto_open_once_per_session</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_auto_open_once_per_session</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_chat_app_url"><code>companion_chat_app_url</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_chat_app_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_close_aria_label"><code>companion_close_aria_label</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_close_aria_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_content_display_mode"><code>companion_content_display_mode</code></h4>
<p>How the chat is shown: full-page only (<code>in_chat</code>), site bubble only (<code>companion</code>), or both / Hybrid (<code>both</code>).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_context_scope"><code>companion_context_scope</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_context_scope</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_contextual_prompt"><code>companion_contextual_prompt</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_contextual_prompt</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_enable_keyboard_shortcut"><code>companion_enable_keyboard_shortcut</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_enable_keyboard_shortcut</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_enabled"><code>companion_enabled</code></h4>
<p>Turns the companion bubble/widget on for this flow when display mode includes companion.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_exclude_categories"><code>companion_exclude_categories</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_exclude_categories</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_exclude_pages"><code>companion_exclude_pages</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_exclude_pages</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_exclude_posts"><code>companion_exclude_posts</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_exclude_posts</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_exclude_tags"><code>companion_exclude_tags</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_exclude_tags</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_flow_slug"><code>companion_flow_slug</code></h4>
<p>Which flow the companion opens (slug / route).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_focus_on_open"><code>companion_focus_on_open</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_focus_on_open</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_greeting"><code>companion_greeting</code></h4>
<p>Title text on the companion header.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_header_icon_url"><code>companion_header_icon_url</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_header_icon_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_hub_companion_url"><code>companion_hub_companion_url</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_hub_companion_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_hub_fullscreen_url"><code>companion_hub_fullscreen_url</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_hub_fullscreen_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_include_categories"><code>companion_include_categories</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_include_categories</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_include_pages"><code>companion_include_pages</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_include_pages</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_include_posts"><code>companion_include_posts</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_include_posts</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_include_tags"><code>companion_include_tags</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_include_tags</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_keyboard_shortcut_key"><code>companion_keyboard_shortcut_key</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_keyboard_shortcut_key</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launch_on_exit_intent"><code>companion_launch_on_exit_intent</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launch_on_exit_intent</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launch_on_scroll_percent"><code>companion_launch_on_scroll_percent</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launch_on_scroll_percent</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launch_on_scroll_threshold"><code>companion_launch_on_scroll_threshold</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launch_on_scroll_threshold</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launcher_aria_label"><code>companion_launcher_aria_label</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launcher_aria_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launcher_icon"><code>companion_launcher_icon</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launcher_icon</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_launcher_size"><code>companion_launcher_size</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_launcher_size</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_mobile_behavior"><code>companion_mobile_behavior</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_mobile_behavior</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_motion_mode"><code>companion_motion_mode</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_motion_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_page_intent_phrases"><code>companion_page_intent_phrases</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_page_intent_phrases</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_panel_height"><code>companion_panel_height</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_panel_height</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_panel_width"><code>companion_panel_width</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_panel_width</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_pass_page_context"><code>companion_pass_page_context</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_pass_page_context</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_position"><code>companion_position</code></h4>
<p>Screen corner for the companion launcher (e.g. bottom-right).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_guest"><code>companion_profile_tier_guest</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_guest</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_guest_label"><code>companion_profile_tier_guest_label</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_guest_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_member"><code>companion_profile_tier_member</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_member</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_member_label"><code>companion_profile_tier_member_label</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_member_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_visitor"><code>companion_profile_tier_visitor</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_visitor</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_profile_tier_visitor_label"><code>companion_profile_tier_visitor_label</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_profile_tier_visitor_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_remember_open_state"><code>companion_remember_open_state</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_remember_open_state</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_routing_mode"><code>companion_routing_mode</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_routing_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_show_for_visitors"><code>companion_show_for_visitors</code></h4>
<p>Whether logged-out visitors see the companion bubble.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_show_header_subtitle"><code>companion_show_header_subtitle</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_show_header_subtitle</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_show_header_title"><code>companion_show_header_title</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_show_header_title</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_show_open_fullpage"><code>companion_show_open_fullpage</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_show_open_fullpage</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_state_storage"><code>companion_state_storage</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_state_storage</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_subtitle"><code>companion_subtitle</code></h4>
<p>Subtitle under the companion greeting.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_target_exclude_custom"><code>companion_target_exclude_custom</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_target_exclude_custom</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_target_include_custom"><code>companion_target_include_custom</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_target_include_custom</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_target_mode"><code>companion_target_mode</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_target_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_trigger_cooldown_ms"><code>companion_trigger_cooldown_ms</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_trigger_cooldown_ms</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_trigger_desktop_only"><code>companion_trigger_desktop_only</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_trigger_desktop_only</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_trigger_min_page_time_ms"><code>companion_trigger_min_page_time_ms</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_trigger_min_page_time_ms</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_trigger_suppress_on_auth_checkout"><code>companion_trigger_suppress_on_auth_checkout</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_trigger_suppress_on_auth_checkout</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-companion_trigger_suppress_path_patterns"><code>companion_trigger_suppress_path_patterns</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_trigger_suppress_path_patterns</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h4 id="field-flosc_reset_chat_style"><code>flosc_reset_chat_style</code></h4>
<p>Flow option used on the <strong>Style &amp; Nav</strong> admin tab. Stored as <code>flosc_reset_chat_style</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Style &amp; Nav</li>
</ul>
<h3 id="settings-tab-ui">Profile Bar <code>ui</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ui' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-empty_chat_list_message"><code>empty_chat_list_message</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>empty_chat_list_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-flosc_login_destination"><code>flosc_login_destination</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>flosc_login_destination</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-guest_menu_action"><code>guest_menu_action</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_menu_action</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-guest_menu_label"><code>guest_menu_label</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_menu_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-guest_new_chat_welcome_message"><code>guest_new_chat_welcome_message</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_new_chat_welcome_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-member_menu_action"><code>member_menu_action</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>member_menu_action</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-member_menu_label"><code>member_menu_label</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>member_menu_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-member_new_chat_welcome_message"><code>member_new_chat_welcome_message</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>member_new_chat_welcome_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-new_chat_button_label"><code>new_chat_button_label</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>new_chat_button_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_avatar_radius"><code>profile_bar_guest_avatar_radius</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_avatar_radius</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_badge"><code>profile_bar_guest_badge</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_badge</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_icon"><code>profile_bar_guest_icon</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_icon</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_icon_url"><code>profile_bar_guest_icon_url</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_icon_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_name"><code>profile_bar_guest_name</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_show_upgrade"><code>profile_bar_guest_show_upgrade</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_show_upgrade</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_guest_upgrade"><code>profile_bar_guest_upgrade</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_guest_upgrade</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_avatar_radius"><code>profile_bar_member_avatar_radius</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_avatar_radius</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_badge"><code>profile_bar_member_badge</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_badge</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_icon"><code>profile_bar_member_icon</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_icon</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_icon_url"><code>profile_bar_member_icon_url</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_icon_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_name"><code>profile_bar_member_name</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_show_upgrade"><code>profile_bar_member_show_upgrade</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_show_upgrade</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_member_upgrade_label"><code>profile_bar_member_upgrade_label</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_member_upgrade_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_avatar_radius"><code>profile_bar_visitor_avatar_radius</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_avatar_radius</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_badge"><code>profile_bar_visitor_badge</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_badge</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_icon"><code>profile_bar_visitor_icon</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_icon</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_icon_url"><code>profile_bar_visitor_icon_url</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_icon_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_name"><code>profile_bar_visitor_name</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_show_upgrade"><code>profile_bar_visitor_show_upgrade</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_show_upgrade</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-profile_bar_visitor_upgrade_label"><code>profile_bar_visitor_upgrade_label</code></h4>
<p>Profile bar presentation setting (including avatar radius per tier when nested). Stored as <code>profile_bar_visitor_upgrade_label</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-visitor_menu_action"><code>visitor_menu_action</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>visitor_menu_action</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h4 id="field-visitor_menu_label"><code>visitor_menu_label</code></h4>
<p>Flow option used on the <strong>Profile Bar</strong> admin tab. Stored as <code>visitor_menu_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Profile Bar</li>
</ul>
<h3 id="settings-tab-ai">AI <code>ai</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'ai' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-ai_anthropic_model"><code>ai_anthropic_model</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_anthropic_model</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_base_prompt"><code>ai_base_prompt</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_base_prompt</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_boundaries"><code>ai_boundaries</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_boundaries</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_chain_provider_1"><code>ai_chain_provider_1</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_chain_provider_1</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_chain_provider_2"><code>ai_chain_provider_2</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_chain_provider_2</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_chain_provider_3"><code>ai_chain_provider_3</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_chain_provider_3</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_context_awareness"><code>ai_context_awareness</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_context_awareness</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_enable_chaining"><code>ai_enable_chaining</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_enable_chaining</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_enable_content_access"><code>ai_enable_content_access</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_enable_content_access</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_enable_ivr_context"><code>ai_enable_ivr_context</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_enable_ivr_context</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_freeline_restrictions"><code>ai_freeline_restrictions</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_freeline_restrictions</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_max_tokens"><code>ai_max_tokens</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_max_tokens</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_member_access"><code>ai_member_access</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_member_access</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_mission"><code>ai_mission</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_mission</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_off_topic_links"><code>ai_off_topic_links</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_off_topic_links</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_off_topic_message"><code>ai_off_topic_message</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_off_topic_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_openai_model"><code>ai_openai_model</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_openai_model</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_personality_name"><code>ai_personality_name</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_personality_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_personality_role"><code>ai_personality_role</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_personality_role</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_personality_traits"><code>ai_personality_traits</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_personality_traits</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_prompt_content"><code>ai_prompt_content</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_prompt_content</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_prompt_freeline"><code>ai_prompt_freeline</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_prompt_freeline</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_prompt_login"><code>ai_prompt_login</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_prompt_login</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_prompt_offer"><code>ai_prompt_offer</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_prompt_offer</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_prompt_sale"><code>ai_prompt_sale</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_prompt_sale</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_provider"><code>ai_provider</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_provider</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_response_mode"><code>ai_response_mode</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_response_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_temperature"><code>ai_temperature</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_temperature</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_topic_scope"><code>ai_topic_scope</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_topic_scope</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-ai_xai_model"><code>ai_xai_model</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>ai_xai_model</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-custom_stt_endpoint"><code>custom_stt_endpoint</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>custom_stt_endpoint</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-editing_file"><code>editing_file</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>editing_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-fallback_phrase"><code>fallback_phrase</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>fallback_phrase</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-file_access_level"><code>file_access_level</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>file_access_level</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-file_content"><code>file_content</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>file_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-flosc_return_ivr"><code>flosc_return_ivr</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>flosc_return_ivr</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-orientation_file"><code>orientation_file</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>orientation_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-phase_outcomes_content"><code>phase_outcomes_content</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>phase_outcomes_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-phase_outcomes_freeline"><code>phase_outcomes_freeline</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>phase_outcomes_freeline</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-phase_outcomes_login"><code>phase_outcomes_login</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>phase_outcomes_login</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-phase_outcomes_offer"><code>phase_outcomes_offer</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>phase_outcomes_offer</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-phase_outcomes_sale"><code>phase_outcomes_sale</code></h4>
<p>Flow option used on the <strong>AI</strong> admin tab. Stored as <code>phase_outcomes_sale</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h4 id="field-stt_provider"><code>stt_provider</code></h4>
<p>AI / STT configuration for this flow (non-secret fields only in portable packs). Stored as <code>stt_provider</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> AI</li>
</ul>
<h3 id="settings-tab-token-management">Token Management <code>token-management</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'token-management' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-chat_token_enforcement"><code>chat_token_enforcement</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>chat_token_enforcement</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-guest_token_grant"><code>guest_token_grant</code></h4>
<p>Guest-session or guest chat capability setting. Stored as <code>guest_token_grant</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-member_token_grant"><code>member_token_grant</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>member_token_grant</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-product_token_cap"><code>product_token_cap</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>product_token_cap</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-product_token_grant_onetime"><code>product_token_grant_onetime</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>product_token_grant_onetime</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-product_token_grant_recurring"><code>product_token_grant_recurring</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>product_token_grant_recurring</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-product_token_grant_recurring_yearly"><code>product_token_grant_recurring_yearly</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>product_token_grant_recurring_yearly</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-tokens_communication_tokens_per_message"><code>tokens_communication_tokens_per_message</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_communication_tokens_per_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-tokens_nominal_millicents_per_token_decimal"><code>tokens_nominal_millicents_per_token_decimal</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_nominal_millicents_per_token_decimal</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-tokens_real_millicents_per_token_decimal"><code>tokens_real_millicents_per_token_decimal</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_real_millicents_per_token_decimal</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-visitor_depleted_contact_mode"><code>visitor_depleted_contact_mode</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>visitor_depleted_contact_mode</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-visitor_low_token_threshold"><code>visitor_low_token_threshold</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>visitor_low_token_threshold</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-visitor_low_tokens_message"><code>visitor_low_tokens_message</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>visitor_low_tokens_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-visitor_session_end_redirect_url"><code>visitor_session_end_redirect_url</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>visitor_session_end_redirect_url</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h4 id="field-visitor_tokens_depleted_message"><code>visitor_tokens_depleted_message</code></h4>
<p>Flow option used on the <strong>Token Management</strong> admin tab. Stored as <code>visitor_tokens_depleted_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Token Management</li>
</ul>
<h3 id="settings-tab-concierge">Concierge <code>concierge</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'concierge' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_cncrg_content"><code>flosc_cncrg_content</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_content</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_delivery"><code>flosc_cncrg_delivery</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_delivery</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_flow"><code>flosc_cncrg_flow</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_flow</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_keyword"><code>flosc_cncrg_keyword</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_keyword</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_max_tries"><code>flosc_cncrg_max_tries</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_max_tries</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_off_ramp_exactness"><code>flosc_cncrg_off_ramp_exactness</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_off_ramp_exactness</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_off_ramp_phrases"><code>flosc_cncrg_off_ramp_phrases</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_off_ramp_phrases</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_retry"><code>flosc_cncrg_retry</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_retry</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_success"><code>flosc_cncrg_success</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_success</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_cncrg_title"><code>flosc_cncrg_title</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_cncrg_title</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h4 id="field-flosc_create_concierge_post"><code>flosc_create_concierge_post</code></h4>
<p>Flow option used on the <strong>Concierge</strong> admin tab. Stored as <code>flosc_create_concierge_post</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Concierge</li>
</ul>
<h3 id="settings-tab-quiz">Quiz <code>quiz</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'quiz' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-audio_conversion_provider"><code>audio_conversion_provider</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_conversion_provider</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_complete_message"><code>audio_quiz_complete_message</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_complete_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_escape_after_phrase"><code>audio_quiz_escape_after_phrase</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_escape_after_phrase</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_escape_enabled"><code>audio_quiz_escape_enabled</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_escape_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_escape_once"><code>audio_quiz_escape_once</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_escape_once</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_phoneme_lesson_map"><code>audio_quiz_phoneme_lesson_map</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_phoneme_lesson_map</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_phrase_complete_message"><code>audio_quiz_phrase_complete_message</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_phrase_complete_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_results_message"><code>audio_quiz_results_message</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_results_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-audio_quiz_upsell_message"><code>audio_quiz_upsell_message</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>audio_quiz_upsell_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h4 id="field-enabled_quizzes"><code>enabled_quizzes</code></h4>
<p>Flow option used on the <strong>Quiz</strong> admin tab. Stored as <code>enabled_quizzes</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Quiz</li>
</ul>
<h3 id="settings-tab-email">Email <code>email</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'email' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-email_body"><code>email_body</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_body</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_congrats_threshold"><code>email_congrats_threshold</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_congrats_threshold</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_encouragement_threshold"><code>email_encouragement_threshold</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_encouragement_threshold</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_from_address"><code>email_from_address</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_from_address</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_from_name"><code>email_from_name</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_from_name</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_on_quiz_complete"><code>email_on_quiz_complete</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_on_quiz_complete</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_provider"><code>email_provider</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_provider</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_reengagement_days"><code>email_reengagement_days</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_reengagement_days</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_reengagement_enabled"><code>email_reengagement_enabled</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_reengagement_enabled</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_subject"><code>email_subject</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_subject</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-email_weekly_summary"><code>email_weekly_summary</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>email_weekly_summary</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-newsletter_optin_text"><code>newsletter_optin_text</code></h4>
<p>Flow option used on the <strong>Email</strong> admin tab. Stored as <code>newsletter_optin_text</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h4 id="field-support_email"><code>support_email</code></h4>
<p>Flow option used on the <strong>Email</strong> admin tab. Stored as <code>support_email</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Email</li>
</ul>
<h3 id="settings-tab-contact-form">Contact Form <code>contact-form</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'contact-form' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-contact_form_accent_color"><code>contact_form_accent_color</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_accent_color</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_button_bg_color"><code>contact_form_button_bg_color</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_button_bg_color</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_button_text_color"><code>contact_form_button_text_color</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_button_text_color</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_card_background"><code>contact_form_card_background</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_card_background</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_duplicate_window_minutes"><code>contact_form_duplicate_window_minutes</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_duplicate_window_minutes</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_email_subject"><code>contact_form_email_subject</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_email_subject</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_forward_to_email"><code>contact_form_forward_to_email</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_forward_to_email</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_intro"><code>contact_form_intro</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_intro</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_max_submissions_per_hour"><code>contact_form_max_submissions_per_hour</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_max_submissions_per_hour</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_message_font_family"><code>contact_form_message_font_family</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_message_font_family</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_message_font_size"><code>contact_form_message_font_size</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_message_font_size</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_min_submit_seconds"><code>contact_form_min_submit_seconds</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_min_submit_seconds</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_submit_text"><code>contact_form_submit_text</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_submit_text</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_success_message"><code>contact_form_success_message</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_success_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h4 id="field-contact_form_title"><code>contact_form_title</code></h4>
<p>Email or contact-form copy/routing setting for this flow. Stored as <code>contact_form_title</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Contact Form</li>
</ul>
<h3 id="settings-tab-payments">Payments <code>payments</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'payments' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-manual_payment_instructions"><code>manual_payment_instructions</code></h4>
<p>Flow option used on the <strong>Payments</strong> admin tab. Stored as <code>manual_payment_instructions</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-manual_payments_enabled"><code>manual_payments_enabled</code></h4>
<p>Flow option used on the <strong>Payments</strong> admin tab. Stored as <code>manual_payments_enabled</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-paypal_client_id"><code>paypal_client_id</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>paypal_client_id</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-paypal_enabled"><code>paypal_enabled</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>paypal_enabled</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-paypal_mode"><code>paypal_mode</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>paypal_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-paypal_webhook_id"><code>paypal_webhook_id</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>paypal_webhook_id</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-stripe_enabled"><code>stripe_enabled</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>stripe_enabled</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-stripe_live_pk"><code>stripe_live_pk</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>stripe_live_pk</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-stripe_mode"><code>stripe_mode</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>stripe_mode</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h4 id="field-stripe_test_pk"><code>stripe_test_pk</code></h4>
<p>Payments-related flow setting. Secrets are not portable; this key is documented for admin configuration. Stored as <code>stripe_test_pk</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Payments</li>
</ul>
<h3 id="settings-tab-sso">SSO <code>sso</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'sso' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-sso_post_login_redirect_url"><code>sso_post_login_redirect_url</code></h4>
<p>SSO / OAuth flow setting. Client secrets are never portable. Stored as <code>sso_post_login_redirect_url</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> SSO</li>
</ul>
<h3 id="settings-tab-engagement">Engagement <code>engagement</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'engagement' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-engagement_rule_audience"><code>engagement_rule_audience</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_audience</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_chat_message"><code>engagement_rule_chat_message</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_chat_message</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_condition"><code>engagement_rule_condition</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_condition</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_days"><code>engagement_rule_days</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_days</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_email_template"><code>engagement_rule_email_template</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_email_template</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_id"><code>engagement_rule_id</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_id</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_title"><code>engagement_rule_title</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_title</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h4 id="field-engagement_rule_trigger"><code>engagement_rule_trigger</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rule_trigger</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Engagement</li>
</ul>
<h3 id="settings-tab-chat-logs">Chat Logs <code>chat-logs</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'chat-logs' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-feedback_admin_note"><code>feedback_admin_note</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>feedback_admin_note</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-feedback_bad_response"><code>feedback_bad_response</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>feedback_bad_response</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-feedback_preferred_response"><code>feedback_preferred_response</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>feedback_preferred_response</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-feedback_user_message"><code>feedback_user_message</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>feedback_user_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-flosc_add_feedback"><code>flosc_add_feedback</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>flosc_add_feedback</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-flosc_add_praise"><code>flosc_add_praise</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>flosc_add_praise</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-flosc_delete_feedback"><code>flosc_delete_feedback</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>flosc_delete_feedback</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-flosc_delete_praise"><code>flosc_delete_praise</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>flosc_delete_praise</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-praise_admin_note"><code>praise_admin_note</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>praise_admin_note</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-praise_good_response"><code>praise_good_response</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>praise_good_response</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-praise_user_message"><code>praise_user_message</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>praise_user_message</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h4 id="field-sessions"><code>sessions</code></h4>
<p>Flow option used on the <strong>Chat Logs</strong> admin tab. Stored as <code>sessions</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Chat Logs</li>
</ul>
<h3 id="settings-tab-administration">Administration <code>administration</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'administration' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-flosc_account_plan"><code>flosc_account_plan</code></h4>
<p>Flow option used on the <strong>Administration</strong> admin tab. Stored as <code>flosc_account_plan</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Administration</li>
</ul>
<h4 id="field-flosc_account_purchases_manual"><code>flosc_account_purchases_manual</code></h4>
<p>Flow option used on the <strong>Administration</strong> admin tab. Stored as <code>flosc_account_purchases_manual</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Administration</li>
</ul>
<h4 id="field-flosc_debug_mode"><code>flosc_debug_mode</code></h4>
<p>Flow option used on the <strong>Administration</strong> admin tab. Stored as <code>flosc_debug_mode</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Administration</li>
</ul>
<h4 id="field-flosc_flow_editors"><code>flosc_flow_editors</code></h4>
<p>Flow option used on the <strong>Administration</strong> admin tab. Stored as <code>flosc_flow_editors</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Administration</li>
</ul>
<h4 id="field-flosc_update_flosc_editors"><code>flosc_update_flosc_editors</code></h4>
<p>Flow option used on the <strong>Administration</strong> admin tab. Stored as <code>flosc_update_flosc_editors</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Administration</li>
</ul>
<h3 id="settings-tab-da1">DA1 <code>da1</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'da1' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-catalog"><code>catalog</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>catalog</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-da1_payload"><code>da1_payload</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>da1_payload</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-da1_save_catalog"><code>da1_save_catalog</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>da1_save_catalog</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_da1_assign_catalogs"><code>flosc_da1_assign_catalogs</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_da1_assign_catalogs</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_da1_create_catalog"><code>flosc_da1_create_catalog</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_da1_create_catalog</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_da1_upload_catalog"><code>flosc_da1_upload_catalog</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_da1_upload_catalog</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_da1_upload_file"><code>flosc_da1_upload_file</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_da1_upload_file</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_flow_catalogs"><code>flosc_flow_catalogs</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_flow_catalogs</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_new_catalog_key"><code>flosc_new_catalog_key</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_new_catalog_key</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h4 id="field-flosc_new_catalog_label"><code>flosc_new_catalog_label</code></h4>
<p>Flow option used on the <strong>DA1</strong> admin tab. Stored as <code>flosc_new_catalog_label</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> DA1</li>
</ul>
<h3 id="settings-tab-settings-save">Settings save <code>settings-save</code></h3>
<p><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'flosc-settings', 'ivr' => $flosc_ref_ivr, 'tab' => 'content' ), admin_url( 'admin.php' ) ) ); ?>">Open admin tab</a></p>
<h4 id="field-avatar_radius"><code>avatar_radius</code></h4>
<p>Legacy avatar corner radius for profile UI.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-companion_target_exclude"><code>companion_target_exclude</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_target_exclude</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-companion_target_include"><code>companion_target_include</code></h4>
<p>Companion (bubble/hybrid) setting for this flow. Stored as <code>companion_target_include</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-content_item_category"><code>content_item_category</code></h4>
<p>Primary content library category slug for this flow.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-content_item_groups"><code>content_item_groups</code></h4>
<p>Quiz-to-category mappings (and standalone category libraries).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-content_item_label_plural"><code>content_item_label_plural</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>content_item_label_plural</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-content_item_label_singular"><code>content_item_label_singular</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>content_item_label_singular</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-content_types"><code>content_types</code></h4>
<p>Product nouns (singular/plural) for this flow — e.g. Lesson/Lessons or Recipe/Recipes.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-engagement_rules"><code>engagement_rules</code></h4>
<p>Engagement rule or threshold for visitor/guest/member transitions. Stored as <code>engagement_rules</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-identity"><code>identity</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>identity</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-member_levels"><code>member_levels</code></h4>
<p>Named access levels for this flow (e.g. guest, member).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-offers"><code>offers</code></h4>
<p>Offer registry for this flow (price, processor, codes, grants, display).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-protected_content"><code>protected_content</code></h4>
<p>Content protection rules (what is gated and at which level).</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-subscription_monthly_token_grant"><code>subscription_monthly_token_grant</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>subscription_monthly_token_grant</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-subscription_token_cap"><code>subscription_token_cap</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>subscription_token_cap</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-subscription_yearly_token_grant"><code>subscription_yearly_token_grant</code></h4>
<p>Flow option used on the <strong>Settings save</strong> admin tab. Stored as <code>subscription_yearly_token_grant</code> on the flow settings array.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-tokens_nominal_millicents_per_token_denominator"><code>tokens_nominal_millicents_per_token_denominator</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_nominal_millicents_per_token_denominator</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-tokens_nominal_millicents_per_token_numerator"><code>tokens_nominal_millicents_per_token_numerator</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_nominal_millicents_per_token_numerator</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-tokens_real_millicents_per_token_denominator"><code>tokens_real_millicents_per_token_denominator</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_real_millicents_per_token_denominator</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
<h4 id="field-tokens_real_millicents_per_token_numerator"><code>tokens_real_millicents_per_token_numerator</code></h4>
<p>Token economy / ledger-facing setting. Stored as <code>tokens_real_millicents_per_token_numerator</code>.</p>
<ul>
<li><strong>Portable:</strong> yes (Settings YAML)</li>
<li><strong>Admin tab:</strong> Settings save</li>
</ul>
