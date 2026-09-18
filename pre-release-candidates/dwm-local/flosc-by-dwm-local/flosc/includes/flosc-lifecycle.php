<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation (v3.0.9 - Resolved: moved outside class so hook fires correctly)
 */
function flosc_activate() {
    // Specialty product roles are created when that flow/product
    // is deliberately imported or configured — not on every generic activate.

    // v1.2.2: Migrate legacy settings to flows system
    require_once FLOSC_PLUGIN_DIR . 'includes/class-flow-manager.php';
    flosc_flows()->maybe_migrate_from_legacy();

    // Flush rewrite rules to register REST API routes
    flush_rewrite_rules();

    // First-install defaults only — never clobber floscAdmin choices on reactivate.
    $defaults = [
        'flosc_app_slug' => 'flosc', // v1.1.9: Changed default from 'app' to 'flosc'
        'flosc_custom_domain' => '', // v1.1.9: Optional custom domain mapping
        'flosc_product_name' => '',
        'flosc_product_title' => '',
        'flosc_product_tagline' => '',
        'flosc_primary_color' => '#4f46e5',
        'flosc_ai_provider' => 'ivr',
        'flosc_stt_provider' => 'assemblyai',
        // Communication economics model (defaults: 5000 tokens = 5 nominal cents = 2.5 real cents)
        // 1 token is priced in millicents via rational numerators/denominators.
        'flosc_tokens_communication_tokens_per_message' => 5000,
        'flosc_tokens_nominal_millicents_per_token_numerator' => 1,
        'flosc_tokens_nominal_millicents_per_token_denominator' => 1,
        'flosc_tokens_real_millicents_per_token_numerator' => 1,
        'flosc_tokens_real_millicents_per_token_denominator' => 2,
        'flosc_quiz_content_flosc_sample_audio_quiz' => '1,2,3,4,5,6,7,8,9,10',
        'flosc_token_name' => 'tokens',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Set PayPal mode to sandbox on fresh install (credentials set via admin Payments tab)
    if (get_option('flosc_paypal_mode') === false) {
        update_option('flosc_paypal_mode', 'sandbox');
    }

    // v1.2.3: Ensure default flosc_default_technical_ivr.md exists in the uploads data
    // directory. When uploads are unavailable the seed is skipped — readers
    // fall back to the shipped read-only default via flosc_config_file().
    $seed_dir = flosc_data_dir();
    $ivr_file = '' !== $seed_dir ? $seed_dir . 'flosc_default_technical_ivr.md' : '';
    if ('' !== $ivr_file && !file_exists($ivr_file)) {

        // Copy the shipped canonical default if present, otherwise create minimal version
        $default_ivr = FLOSC_PLUGIN_DIR . 'ai_configuration_files/flosc_default_technical_ivr.md';
        if (file_exists($default_ivr)) {
            // Pass 5: read shipped default (plugin dir is read-only source); write only under uploads.
            $default_body = flosc_fs_get_contents($default_ivr);
            if (false !== $default_body && function_exists('flosc_write_data_file')) {
                flosc_write_data_file($ivr_file, $default_body);
            }
        } else {
            // Create minimal working ivr.md
            $minimal_ivr = <<<'MD'
# FLOSC IVR Configuration

## MessageStyle: pill
Description: Superlight chat bubble style
.flosc-style-pill {
  background: rgba(255, 255, 255, 0.7);
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: all 0.2s;
  backdrop-filter: blur(4px);
}
.flosc-style-pill:hover {
  background: rgba(255, 255, 255, 0.95);
  border-color: rgba(0, 0, 0, 0.12);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

---

# Freeline Messages

## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. Ready to get started?
MessageConditions: first_show_session && !logged_in

## Get Started
MessageName: get_started_001
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🚀
UserInput: Get started
MessageContent: Great! Let's begin with a quick quiz to see where you stand.
MessageConditions: !quiz_taken
MD;
            // Pass 5: seed only under uploads via flosc_write_data_file.
            if (function_exists('flosc_write_data_file')) {
                flosc_write_data_file($ivr_file, $minimal_ivr);
            }
        }
    }

    // v9.2.3: Import IVR messages to database on first activation
    flosc_import_ivr_to_database(false); // Execute import (not preview)

    // v1.9.0: Create chat logs table
    // Must require the file here — activation hook fires before plugins_loaded,
    // so the FLOSC_Framework constructor hasn't loaded class files yet.
    require_once FLOSC_PLUGIN_DIR . 'includes/logging/class-flosc-chat-logger.php';
    FLOSC_Chat_Logger::instance()->flosc_ensure_table();

    // v1.4.7: Auto-protect flosc_sample_data category
    $sample_cat = get_category_by_slug('flosc_sample_data');
    if ($sample_cat) {
        update_term_meta($sample_cat->term_id, '_flosc_protected', 'yes');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Deactivation cleanup.
 *
 * Keep stored data intact on deactivation, but unschedule FLOSC cron jobs
 * and flush rewrite rules to avoid stale routes.
 */
function flosc_deactivate() {
    wp_clear_scheduled_hook('flosc_cleanup_visitor_audio');
    wp_clear_scheduled_hook('flosc_guest_followup_cron');
    flush_rewrite_rules();
}

/**
 * Suggested privacy policy text for Settings → Privacy.
 */
function flosc_add_privacy_policy_content() {
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }

    $content = '<p class="privacy-policy-tutorial">'
        . esc_html__('FLOSC (this plugin) may process personal data when visitors use chat, quizzes, login, offers, and payments.', 'flosc')
        . '</p>'
        . '<p><strong>' . esc_html__('What FLOSC may collect', 'flosc') . '</strong></p>'
        . '<ul>'
        . '<li>' . esc_html__('Chat and quiz messages, scores, and session identifiers.', 'flosc') . '</li>'
        . '<li>' . esc_html__('Account and authentication data (email, profile fields, SSO provider identifiers).', 'flosc') . '</li>'
        . '<li>' . esc_html__('Optional audio for pronunciation / speech features.', 'flosc') . '</li>'
        . '<li>' . esc_html__('Purchase and membership records needed to unlock content.', 'flosc') . '</li>'
        . '</ul>'
        . '<p><strong>' . esc_html__('Where data is stored', 'flosc') . '</strong></p>'
        . '<p>' . esc_html__('On this WordPress site (database options/meta/tables and files under the uploads directory). FLOSC does not write visitor data into the plugin install folder.', 'flosc') . '</p>'
        . '<p><strong>' . esc_html__('Third parties', 'flosc') . '</strong></p>'
        . '<p>' . esc_html__('When you enable integrations, data may be sent to AI providers, speech-to-text providers, payment processors, OAuth providers, or a scoring API you configure. See the plugin readme “External Services” section for purpose, data, and policy links for each provider you enable.', 'flosc') . '</p>'
        . '<p><strong>' . esc_html__('Retention and deletion', 'flosc') . '</strong></p>'
        . '<p>' . esc_html__('Retention follows your site policies and WordPress tools. Deactivating the plugin keeps data so reactivation can continue. Deleting the plugin runs uninstall cleanup for FLOSC options, FLOSC meta, FLOSC tables, scheduled events, and FLOSC upload folders when present. Some host-level backups may retain copies outside WordPress.', 'flosc') . '</p>';

    wp_add_privacy_policy_content(
        'FLOSC',
        wp_kses_post($content)
    );
}
add_action('admin_init', 'flosc_add_privacy_policy_content');
