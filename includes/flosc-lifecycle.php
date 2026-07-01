<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation (v3.0.9 - Resolved: moved outside class so hook fires correctly)
 */
function flosc_activate() {
    // v8.0.0: Register LeSAEp Learner roles (same capabilities as subscriber)
    $member_level = flosc_get_setting('default_member_level', 'pronunciation_learners', 'lesaep');
    $guest_level = flosc_get_setting('default_guest_level', 'guest_pronunciation_learner', 'lesaep');
    if (!get_role($member_level)) {
        add_role($member_level, 'LeSAEp Learner', ['read' => true]);
    }
    if (!get_role($guest_level)) {
        add_role($guest_level, 'Guest LeSAEp Learner', ['read' => true]);
    }
    if ($member_level !== 'lesaep_learners' && !get_role('lesaep_learners')) {
        add_role('lesaep_learners', 'LeSAEp Learner', ['read' => true]);
    }
    if ($guest_level !== 'guest_lesaep_learner' && !get_role('guest_lesaep_learner')) {
        add_role('guest_lesaep_learner', 'Guest LeSAEp Learner', ['read' => true]);
    }

    // v1.2.2: Migrate legacy settings to flows system
    require_once FLOSC_PLUGIN_DIR . 'includes/class-flow-manager.php';
    flosc_flows()->maybe_migrate_from_legacy();

    // Flush rewrite rules to register REST API routes
    flush_rewrite_rules();

    // Set defaults (only if they don't exist)
    $defaults = [
        'flosc_app_slug' => 'flosc', // v1.1.9: Changed default from 'app' to 'flosc'
        'flosc_custom_domain' => '', // v1.1.9: Optional custom domain mapping
        'flosc_product_name' => '',
        'flosc_product_title' => '',
        'flosc_product_tagline' => '',
        'flosc_primary_color' => '#4f46e5',
        'flosc_ai_provider' => 'ivr',
        'flosc_stt_provider' => 'assemblyai',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Force critical "out of box" defaults
    $force_defaults = [
        'flosc_quiz_content_flosc_sample_audio_quiz' => '1,2,3,4,5,6,7,8,9,10',
        'flosc_token_name' => 'tokens',
    ];

    foreach ($force_defaults as $key => $value) {
        update_option($key, $value);
    }

    // Set PayPal mode to sandbox on fresh install (credentials set via admin Payments tab)
    if (get_option('flosc_paypal_mode') === false) {
        update_option('flosc_paypal_mode', 'sandbox');
    }

    // v1.2.3: Ensure default flosc_default_ivr.md exists in the uploads data
    // directory. When uploads are unavailable the seed is skipped — readers
    // fall back to the shipped read-only default via flosc_config_file().
    $seed_dir = flosc_data_dir();
    $ivr_file = '' !== $seed_dir ? $seed_dir . 'flosc_default_ivr.md' : '';
    if ('' !== $ivr_file && !file_exists($ivr_file)) {

        // Copy default from includes/defaults/ if it exists, otherwise create minimal version
        $default_ivr = FLOSC_PLUGIN_DIR . 'includes/defaults/ivr.md';
        if (file_exists($default_ivr)) {
            copy($default_ivr, $ivr_file);
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
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- activation seeding of uploads-rooted IVR default file
            file_put_contents($ivr_file, $minimal_ivr);
        }
    }

    // v9.2.3: Import IVR messages to database on first activation
    flosc_import_ivr_to_database(false); // Execute import (not preview)

    // v1.9.0: Create chat logs table
    // Must require the file here — activation hook fires before plugins_loaded,
    // so the FLOSC_Framework constructor hasn't loaded class files yet.
    require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-chat-logger.php';
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
