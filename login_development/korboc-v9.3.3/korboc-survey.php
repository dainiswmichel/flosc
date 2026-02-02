<?php
/**
 * Plugin Name: Korboc Survey & Profile Creator
 * Plugin URI: https://korboc.lv
 * Description: Survey with BuddyBoss social login, auto account creation, and private messaging
 * Version: 9.3.3
 * Author: Dainis W. Michel
 * Author URI: https://dainis.net
 * Text Domain: korboc-survey
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('KORBOC_SURVEY_VERSION', '9.3.3');
define('KORBOC_SURVEY_PATH', plugin_dir_path(__FILE__));
define('KORBOC_SURVEY_URL', plugin_dir_url(__FILE__));

/**
 * CHANGELOG v9.3.3 (2025-12-13)
 * - Fixed button alignment in Incomplete Sessions accordion
 * - Stacked buttons vertically with proper spacing
 * - Clean, professional button layout (no collision)
 */

/**
 * CHANGELOG v9.3.2 (2025-12-13)
 * - Increased Status column width from 100px to 160px
 * - Fixed "Incomplete Session" text collision/wrapping
 * - Clean display for all status values
 */

/**
 * CHANGELOG v9.3.1 (2025-12-13)
 * - Fixed date sorting in main admin page
 * - Made ALL columns sortable in Table View (23 columns)
 * - Fixed status column wrapping (nowrap for clean display)
 * - Fixed arrow display (inline with text, professional)
 * - Implemented table-layout: fixed for stable column widths
 * - Set explicit widths for all columns (no dancing when sorting)
 * - Added all missing sort cases to switch statement
 * - Professional, clean, readable table display
 */

/**
 * CHANGELOG v9.3.0 (2025-12-13)
 * - Added timestamp columns to Table View (Survey Started, Survey Completed)
 * - Implemented professional 3-state sorting (asc ▲ / desc ▼ / default)
 * - Fixed accordion collapse/expand behavior
 * - Format timestamps for display (YYYY-MM-DD HH:MM:SS)
 * - Professional sorting with clear visual indicators
 */

/**
 * CHANGELOG v9.2.9 (2025-12-13)
 * - Fixed Table View formatting
 * - Headers: centered + bold
 * - Data cells: top-left aligned, wrapping enabled
 * - Cells expand vertically as needed
 * - Best practices readable layout
 */

/**
 * CHANGELOG v9.2.8 (2025-12-13)
 * - Changed survey_started to timestamp format: YYYYMMDDHHmmss_XX
 * - Changed survey_completed from 'yes' to timestamp format
 * - Filter users to only show those who started survey
 * - Added sortable headers to Table View
 * - Separated sessions into accordion section below main table
 * - Three distinct states: Incomplete Session / Nepabeigta / Pabeigta
 */

// Include required files
require_once KORBOC_SURVEY_PATH . 'includes/class-survey-handler.php';
require_once KORBOC_SURVEY_PATH . 'includes/class-user-creator.php';
require_once KORBOC_SURVEY_PATH . 'includes/class-admin-page.php';
require_once KORBOC_SURVEY_PATH . 'includes/class-password-expiration.php';
require_once KORBOC_SURVEY_PATH . 'includes/shortcodes.php';

/**
 * v5.0.5: Helper function to safely get client IP address
 * Sanitizes and validates IP to prevent spoofing and injection attacks
 */
function korboc_get_client_ip() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $sanitized_ip = filter_var($ip, FILTER_VALIDATE_IP);
    return $sanitized_ip ? $sanitized_ip : '0.0.0.0';
}

// Initialize the plugin
function korboc_survey_init() {
    // Register assets
    add_action('wp_enqueue_scripts', 'korboc_survey_enqueue_assets');
    
    // Register AJAX handlers
    add_action('wp_ajax_korboc_submit_survey', 'korboc_handle_survey_submission');
    add_action('wp_ajax_nopriv_korboc_submit_survey', 'korboc_handle_survey_submission');

    add_action('wp_ajax_korboc_quick_account', 'korboc_handle_quick_account');
    add_action('wp_ajax_nopriv_korboc_quick_account', 'korboc_handle_quick_account');

    // v5.0.1: Auto-save AJAX handlers
    add_action('wp_ajax_korboc_autosave', 'korboc_handle_autosave');
    add_action('wp_ajax_nopriv_korboc_autosave', 'korboc_handle_autosave');

    add_action('wp_ajax_korboc_load_draft', 'korboc_handle_load_draft');
    add_action('wp_ajax_nopriv_korboc_load_draft', 'korboc_handle_load_draft');

    // v9.1.3: Admin can mark survey as complete
    add_action('wp_ajax_korboc_admin_mark_complete', 'korboc_admin_mark_complete');

    // v9.1.5: Magic link generation AJAX
    add_action('wp_ajax_korboc_generate_magic_link', 'korboc_generate_magic_link');

    // v5.0.6: Hook into social login to migrate session data
    // v6.0.9: Added multiple hooks to catch ALL login types (Facebook, Google, etc.)
    add_action('wp_login', 'korboc_handle_social_login_migration', 10, 2);
    add_action('nextend_social_login_register', 'korboc_handle_social_login_migration', 10, 2);
    add_action('user_register', 'korboc_handle_social_login_migration_on_register', 10, 1);

    // Register shortcodes
    korboc_register_shortcodes();
    
    // Redirect back to survey after social login
    add_filter('login_redirect', 'korboc_survey_login_redirect', 10, 3);
    
    // Show incomplete survey message on profile pages
    add_action('bp_before_member_header_meta', 'korboc_show_incomplete_survey_message');
    add_action('bp_core_general_settings_before_submit', 'korboc_show_incomplete_survey_message_settings');
    
    // PHP-based redirect check (runs on every page load)
    // v7.9.3: Removed auto-redirect - users see reminders only, no forced redirects
    // add_action('template_redirect', 'korboc_check_incomplete_survey_redirect', 1);

    // v9.1.5: Handle magic link auto-login
    add_action('template_redirect', 'korboc_handle_magic_link', 1);

    // v5.0.1: Daily cleanup of old session data
    add_action('korboc_daily_cleanup', 'korboc_cleanup_old_sessions');

    // Initialize admin page
    if (is_admin()) {
        add_action('admin_menu', 'korboc_survey_add_admin_menu');
        add_action('admin_init', 'korboc_survey_register_settings');
        new Korboc_Admin_Page();
    }
    
    // v9.2.6: Initialize password expiration system
    new Korboc_Password_Expiration();
}
add_action('init', 'korboc_survey_init');

// Add plugin settings link (for plugins page)
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'korboc_survey_plugin_links');
function korboc_survey_plugin_links($links) {
    $settings_link = '<a href="admin.php?page=korboc-survey">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

// Add admin menu
function korboc_survey_add_admin_menu() {
    // Add top-level menu (will appear after WishList Member if it exists)
    add_menu_page(
        'Korboc Survey',                    // Page title
        'Korboc Survey',                    // Menu title
        'manage_options',                   // Capability
        'korboc-survey',                    // Menu slug
        'korboc_survey_settings_page',      // Function
        'dashicons-format-chat',            // Icon
        59                                  // Position (59 = after WishList Member's typical position of 58)
    );
    
    // Add submenu (replaces default "Korboc Survey" submenu)
    add_submenu_page(
        'korboc-survey',                    // Parent slug
        'Settings',                         // Page title
        'Settings',                         // Menu title
        'manage_options',                   // Capability
        'korboc-survey',                    // Menu slug (same as parent)
        'korboc_survey_settings_page'       // Function
    );
    
    // Add "View Responses" submenu
    add_submenu_page(
        'korboc-survey',                    // Parent slug
        'Survey Responses',                 // Page title
        'Responses',                        // Menu title
        'manage_options',                   // Capability
        'korboc-survey-responses',          // Menu slug
        array('Korboc_Admin_Page', 'render_page') // Function from class
    );
}

// Register settings
function korboc_survey_register_settings() {
    register_setting('korboc_survey_settings', 'korboc_redirect_url');
    register_setting('korboc_survey_settings', 'korboc_survey_url'); // URL to survey page for incomplete message
    register_setting('korboc_survey_settings', 'korboc_wlm_level_id');
    register_setting('korboc_survey_settings', 'korboc_buddyboss_group_id');
    register_setting('korboc_survey_settings', 'korboc_admin_user_id');
    register_setting('korboc_survey_settings', 'korboc_greeting');
    
    // Profile Basics Labels
    register_setting('korboc_survey_settings', 'korboc_label_first_name');
    register_setting('korboc_survey_settings', 'korboc_label_last_name');
    register_setting('korboc_survey_settings', 'korboc_label_how_to_address');
    register_setting('korboc_survey_settings', 'korboc_label_choir_name');
    register_setting('korboc_survey_settings', 'korboc_label_rehearsal_location');
    register_setting('korboc_survey_settings', 'korboc_label_role_in_choir');
    register_setting('korboc_survey_settings', 'korboc_label_voice_part');
    register_setting('korboc_survey_settings', 'korboc_label_is_soloist');
    register_setting('korboc_survey_settings', 'korboc_label_soloist_repertoire');
    register_setting('korboc_survey_settings', 'korboc_label_email');
    register_setting('korboc_survey_settings', 'korboc_label_phone');
    register_setting('korboc_survey_settings', 'korboc_label_singer_count');
    
    // Survey Questions
    register_setting('korboc_survey_settings', 'korboc_question_1');
    register_setting('korboc_survey_settings', 'korboc_question_2');
    register_setting('korboc_survey_settings', 'korboc_question_3');
    register_setting('korboc_survey_settings', 'korboc_question_4');
    register_setting('korboc_survey_settings', 'korboc_question_5');
    
    // Sticky Footer
    register_setting('korboc_survey_settings', 'korboc_enable_sticky_footer');
    register_setting('korboc_survey_settings', 'korboc_sticky_footer_email_placeholder');
    register_setting('korboc_survey_settings', 'korboc_sticky_footer_button_text');

    // v5.0.0: Customizable Reminder Messages
    register_setting('korboc_survey_settings', 'korboc_reminder_title');
    register_setting('korboc_survey_settings', 'korboc_reminder_message');
    register_setting('korboc_survey_settings', 'korboc_reminder_button_text');
    register_setting('korboc_survey_settings', 'korboc_reminder_footer_text');
}

// Settings page
function korboc_survey_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle reset surveys
    if (isset($_POST['korboc_reset_surveys'])) {
        check_admin_referer('korboc_survey_settings');
        
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ('korboc_survey_completed', 'korboc_needs_survey')");
        
        echo '<div class="notice notice-success"><p>Reset complete! ' . $deleted . ' survey records cleared. All users can now fill out the survey again.</p></div>';
    }
    
    // Save settings
    if (isset($_POST['korboc_save_settings'])) {
        check_admin_referer('korboc_survey_settings');
        
        update_option('korboc_redirect_url', sanitize_text_field($_POST['korboc_redirect_url']));
        update_option('korboc_survey_url', sanitize_text_field($_POST['korboc_survey_url']));
        update_option('korboc_wlm_level_id', sanitize_text_field($_POST['korboc_wlm_level_id']));
        update_option('korboc_buddyboss_group_id', sanitize_text_field($_POST['korboc_buddyboss_group_id']));
        update_option('korboc_admin_user_id', absint($_POST['korboc_admin_user_id']));
        // Use stripslashes_deep to properly handle quotes without multiplication
        update_option('korboc_greeting', wp_kses_post(stripslashes_deep($_POST['korboc_greeting'])));
        
        // Profile labels
        update_option('korboc_label_first_name', sanitize_text_field($_POST['korboc_label_first_name']));
        update_option('korboc_label_last_name', sanitize_text_field($_POST['korboc_label_last_name']));
        update_option('korboc_label_how_to_address', sanitize_text_field($_POST['korboc_label_how_to_address']));
        update_option('korboc_label_choir_name', sanitize_text_field($_POST['korboc_label_choir_name']));
        update_option('korboc_label_rehearsal_location', sanitize_text_field($_POST['korboc_label_rehearsal_location']));
        update_option('korboc_label_role_in_choir', sanitize_text_field($_POST['korboc_label_role_in_choir']));
        update_option('korboc_label_voice_part', sanitize_text_field($_POST['korboc_label_voice_part']));
        update_option('korboc_label_is_soloist', sanitize_text_field($_POST['korboc_label_is_soloist']));
        update_option('korboc_label_soloist_repertoire', sanitize_textarea_field($_POST['korboc_label_soloist_repertoire']));
        update_option('korboc_label_email', sanitize_text_field($_POST['korboc_label_email']));
        update_option('korboc_label_phone', sanitize_text_field($_POST['korboc_label_phone']));
        update_option('korboc_label_singer_count', sanitize_text_field($_POST['korboc_label_singer_count']));
        
        // Survey questions
        update_option('korboc_question_1', sanitize_textarea_field($_POST['korboc_question_1']));
        update_option('korboc_question_2', sanitize_textarea_field($_POST['korboc_question_2']));
        update_option('korboc_question_3', sanitize_textarea_field($_POST['korboc_question_3']));
        update_option('korboc_question_4', sanitize_textarea_field($_POST['korboc_question_4']));
        update_option('korboc_question_5', sanitize_textarea_field($_POST['korboc_question_5']));
        
        // Sticky footer
        update_option('korboc_enable_sticky_footer', isset($_POST['korboc_enable_sticky_footer']) ? 'yes' : 'no');
        update_option('korboc_sticky_footer_email_placeholder', sanitize_text_field($_POST['korboc_sticky_footer_email_placeholder']));
        update_option('korboc_sticky_footer_button_text', sanitize_text_field($_POST['korboc_sticky_footer_button_text']));

        // v5.0.0: Reminder messages
        update_option('korboc_reminder_title', sanitize_text_field($_POST['korboc_reminder_title']));
        update_option('korboc_reminder_message', sanitize_textarea_field($_POST['korboc_reminder_message']));
        update_option('korboc_reminder_button_text', sanitize_text_field($_POST['korboc_reminder_button_text']));
        update_option('korboc_reminder_footer_text', sanitize_textarea_field($_POST['korboc_reminder_footer_text']));

        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    $redirect_url = get_option('korboc_redirect_url', home_url('/members/'));
    $wlm_level_id = get_option('korboc_wlm_level_id', '');
    $buddyboss_group_id = get_option('korboc_buddyboss_group_id', '');
    $greeting = get_option('korboc_greeting', '');
    $enable_sticky = get_option('korboc_enable_sticky_footer', 'yes');
    
    ?>
    <div class="wrap">
        <h1>Korboc Survey Settings</h1>
        <p>All survey content is editable below. Scroll down to edit each section.</p>
        
        <!-- Reset Surveys Section -->
        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <h3 style="margin-top: 0;">⚠️ Testing & Development Tools</h3>
            <p>Use this button to clear all survey completion records. This allows all users (including yourself) to fill out the survey again.</p>
            <form method="post" action="" style="display: inline;">
                <?php wp_nonce_field('korboc_survey_settings'); ?>
                <button type="submit" name="korboc_reset_surveys" class="button button-secondary" onclick="return confirm('Are you sure? This will allow ALL users to retake the survey.');">
                    🔄 Reset All Surveys
                </button>
            </form>
        </div>
        
        <form method="post" action="">
            <?php wp_nonce_field('korboc_survey_settings'); ?>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>After Survey Redirect</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="korboc_redirect_url">Redirect URL (After Completion)</label>
                    </th>
                    <td>
                        <input type="text" id="korboc_redirect_url" name="korboc_redirect_url" value="<?php echo esc_attr($redirect_url); ?>" class="regular-text">
                        <p class="description">Where users go after completing survey (default: their profile page)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="korboc_survey_url">Survey Page URL</label>
                    </th>
                    <td>
                        <input type="text" id="korboc_survey_url" name="korboc_survey_url" value="<?php echo esc_attr(get_option('korboc_survey_url', home_url('/korboc-aptauja/'))); ?>" class="regular-text">
                        <p class="description">URL to your survey page (used in incomplete survey messages)</p>
                    </td>
                </tr>
            </table>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>WishList Member & BuddyBoss</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="korboc_wlm_level_id">WLM Level ID</label>
                    </th>
                    <td>
                        <input type="text" id="korboc_wlm_level_id" name="korboc_wlm_level_id" value="<?php echo esc_attr($wlm_level_id); ?>" class="regular-text">
                        <p class="description">WishList Member level to add users to (optional)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="korboc_buddyboss_group_id">BuddyBoss Group ID</label>
                    </th>
                    <td>
                        <input type="text" id="korboc_buddyboss_group_id" name="korboc_buddyboss_group_id" value="<?php echo esc_attr($buddyboss_group_id); ?>" class="regular-text">
                        <p class="description">BuddyBoss group to add users to (e.g., "Latvijas Koristi")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="korboc_admin_user_id">Admin User ID for Messages</label>
                    </th>
                    <td>
                        <input type="number" id="korboc_admin_user_id" name="korboc_admin_user_id" value="<?php echo esc_attr(get_option('korboc_admin_user_id', 1)); ?>" class="small-text">
                        <p class="description">User ID to receive BuddyBoss private messages with survey responses (default: 1). Your user ID: <strong><?php echo get_current_user_id(); ?></strong></p>
                    </td>
                </tr>
            </table>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>Survey Greeting</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="korboc_greeting">Greeting Text</label>
                    </th>
                    <td>
                        <textarea id="korboc_greeting" name="korboc_greeting" rows="15" class="large-text"><?php echo esc_textarea($greeting); ?></textarea>
                        <p class="description">The greeting text that appears at the top of the survey</p>
                    </td>
                </tr>
            </table>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>Page 1: Profile Basics (11 Questions)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="korboc_label_first_name">1. First Name Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_first_name" name="korboc_label_first_name" value="<?php echo esc_attr(get_option('korboc_label_first_name', '1.1: Jūsu vārds (priekšvārds)? *')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_last_name">2. Last Name Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_last_name" name="korboc_label_last_name" value="<?php echo esc_attr(get_option('korboc_label_last_name', '1.2: Jūsu uzvārds? *')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_how_to_address">3. How to Address Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_how_to_address" name="korboc_label_how_to_address" value="<?php echo esc_attr(get_option('korboc_label_how_to_address', '1.3: Kā Jūs uzrunāt? *')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_choir_name">4. Choir Name Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_choir_name" name="korboc_label_choir_name" value="<?php echo esc_attr(get_option('korboc_label_choir_name', '2.1: Kā sauc Jūsu kori?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_rehearsal_location">5. Rehearsal Location Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_rehearsal_location" name="korboc_label_rehearsal_location" value="<?php echo esc_attr(get_option('korboc_label_rehearsal_location', '2.2: Kurā vietā pārsvarā notiek Jūsu mēģinājumi?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_role_in_choir">6. Role in Choir Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_role_in_choir" name="korboc_label_role_in_choir" value="<?php echo esc_attr(get_option('korboc_label_role_in_choir', '2.3: Jūsu loma korī?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_voice_part">7. Voice Part Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_voice_part" name="korboc_label_voice_part" value="<?php echo esc_attr(get_option('korboc_label_voice_part', '2.4: Kādā balsī Jūs dziedat?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_is_soloist">8. Soloist Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_is_soloist" name="korboc_label_is_soloist" value="<?php echo esc_attr(get_option('korboc_label_is_soloist', '2.5: Vai esat solists/soliste?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_soloist_repertoire">Soloist Repertoire Label</label></th>
                    <td>
                        <textarea id="korboc_label_soloist_repertoire" name="korboc_label_soloist_repertoire" rows="3" class="large-text"><?php echo esc_textarea(get_option('korboc_label_soloist_repertoire', 'Ja vēlaties tikt iekļauts/iekļauta korboc.lv solistu datubāzē, lūdzu aprakstiet, kuras solo partijas esat dziedājis/dziedājusi.')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_email">9. Email Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_email" name="korboc_label_email" value="<?php echo esc_attr(get_option('korboc_label_email', '1.4: E-pasta adrese? *')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_phone">10. Phone Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_phone" name="korboc_label_phone" value="<?php echo esc_attr(get_option('korboc_label_phone', '1.5: Telefons?')); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_label_singer_count">11. Singer Count Label</label></th>
                    <td>
                        <input type="text" id="korboc_label_singer_count" name="korboc_label_singer_count" value="<?php echo esc_attr(get_option('korboc_label_singer_count', '2.6: Apmēram cik dziedātāju dzied Jūsu korī? *')); ?>" class="large-text">
                    </td>
                </tr>
            </table>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>Page 2: Survey Questions (5 Questions)</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="korboc_question_1">Question 1.J</label></th>
                    <td>
                        <textarea id="korboc_question_1" name="korboc_question_1" rows="3" class="large-text"><?php echo esc_textarea(get_option('korboc_question_1', '3.1: Kas būtu vissvarīgākās funkcijas, lai www.korboc.lv būtu noderīgs Jūsu korim?')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_question_2">Question 2.J</label></th>
                    <td>
                        <textarea id="korboc_question_2" name="korboc_question_2" rows="3" class="large-text"><?php echo esc_textarea(get_option('korboc_question_2', '3.2: Kas vēl noteikti būtu jāiekļauj, lai www.korboc.lv būtu vērtīgs risinājums Jūsu korim?')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_question_3">Question 3.J</label></th>
                    <td>
                        <textarea id="korboc_question_3" name="korboc_question_3" rows="4" class="large-text"><?php echo esc_textarea(get_option('korboc_question_3', '3.3: Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka, kamēr dziedam un dejojam Bitīti — parādot pasaulei, ka spējam nosargāt mūsu robežas, tautu, kultūru, valsti un nākotni?')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_question_4">Question 4.J</label></th>
                    <td>
                        <textarea id="korboc_question_4" name="korboc_question_4" rows="3" class="large-text"><?php echo esc_textarea(get_option('korboc_question_4', '3.4: Vai €100/korim + €1/dziedātājam gadā šķiet pamatota cena par aprakstītajiem www.korboc.lv pakalpojumiem?')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_question_5">Question 5.J</label></th>
                    <td>
                        <textarea id="korboc_question_5" name="korboc_question_5" rows="4" class="large-text"><?php echo esc_textarea(get_option('korboc_question_5', '3.5: Lai elektroniskā komunikācija būtu bez maksas, vai tiešām ir labāk tikt galā ar WhatsApp, epastu, un citām tehnoloģijām, kas pelna ar to, ka tās mūs izseko (WhatsApp privātums | Google reklāmas), kādas ir Jūsu domas?')); ?></textarea>
                    </td>
                </tr>
            </table>
            
            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>Page 3: Sticky Footer</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="korboc_enable_sticky_footer">Enable Sticky Footer</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="korboc_enable_sticky_footer" name="korboc_enable_sticky_footer" value="yes" <?php checked($enable_sticky, 'yes'); ?>>
                            Show sticky footer with social login + email account creation
                        </label>
                        <p class="description">The sticky footer follows users as they scroll through the survey</p>
                    </td>
                </tr>
            </table>
            
            <h3>How the Sticky Footer Works:</h3>
            <div style="background: #f0f9ff; border-left: 4px solid #2196F3; padding: 15px; margin: 15px 0;">
                <p><strong>Layout:</strong></p>
                <p style="font-family: monospace; background: white; padding: 10px; border-radius: 4px;">
                    [Social Login Buttons] <span style="color: #2196F3;">vai</span> [Email Input] [Create Account Button]
                </p>
                
                <p><strong>What happens:</strong></p>
                <ol>
                    <li><strong>Social Login (left side):</strong> BuddyBoss renders Facebook/Google/Twitter buttons → User clicks → OAuth → Auto-login</li>
                    <li><strong>Email Signup (right side):</strong> User enters email → Creates account → Auto-login</li>
                </ol>
                
                <p><strong>Technical:</strong></p>
                <ul>
                    <li>BuddyBoss function: <code>BB_SSO::render_buttons_with_container()</code></li>
                    <li>Email account creation: <code>korbocQuickAccountCreation()</code> (JavaScript)</li>
                    <li>AJAX handler: <code>korboc_handle_quick_account</code> (PHP)</li>
                </ul>
            </div>
            
            <h3>Customize Text:</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="korboc_sticky_footer_email_placeholder">Email Placeholder</label></th>
                    <td>
                        <input type="text" id="korboc_sticky_footer_email_placeholder" name="korboc_sticky_footer_email_placeholder" value="<?php echo esc_attr(get_option('korboc_sticky_footer_email_placeholder', 'jūsu@epasts.lv')); ?>" class="regular-text">
                        <p class="description">Placeholder text inside the email input field</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_sticky_footer_button_text">Button Text</label></th>
                    <td>
                        <input type="text" id="korboc_sticky_footer_button_text" name="korboc_sticky_footer_button_text" value="<?php echo esc_attr(get_option('korboc_sticky_footer_button_text', 'Izveidot Profilu')); ?>" class="regular-text">
                        <p class="description">Text on the account creation button (e.g., "Create Account", "Sign Up", "Izveidot Profilu")</p>
                    </td>
                </tr>
            </table>
            
            <h3>View Code:</h3>
            <details style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <summary style="cursor: pointer; font-weight: bold; color: #2196F3;">📄 Click to View Full Sticky Footer Code</summary>
                <div style="margin-top: 15px;">
                    <p><strong>HTML Structure (from shortcodes.php):</strong></p>
                    <pre style="background: white; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px;"><code>&lt;div class="korboc-sticky-footer"&gt;
    &lt;div class="footer-content"&gt;
        &lt;div class="footer-social-section"&gt;
            &lt;?php echo korboc_render_buddyboss_social_buttons(); ?&gt;
        &lt;/div&gt;
        &lt;div class="footer-divider"&gt;vai&lt;/div&gt;
        &lt;div class="footer-email-section"&gt;
            &lt;input type="email" id="sticky-email" placeholder="&lt;?php echo esc_attr($sticky_email_placeholder); ?&gt;" class="sticky-email-input"&gt;
            &lt;button type="button" class="btn-create-account" onclick="korbocQuickAccountCreation()"&gt;
                &lt;?php echo esc_html($sticky_button_text); ?&gt;
            &lt;/button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>

                    <p><strong>JavaScript Function (from survey.js):</strong></p>
                    <pre style="background: white; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px;"><code>window.korbocQuickAccountCreation = function() {
    var email = jQuery('#sticky-email').val();
    
    if (!email || !isValidEmail(email)) {
        alert('Lūdzu, ievadiet derīgu e-pasta adresi');
        return;
    }
    
    // Create account via AJAX
    jQuery.ajax({
        url: korbocSurvey.ajaxUrl,
        type: 'POST',
        data: {
            action: 'korboc_quick_account',
            email: email,
            nonce: korbocSurvey.nonce
        },
        success: function(response) {
            if (response.success) {
                window.location.reload(); // User is now logged in
            }
        }
    });
};</code></pre>

                    <p><strong>PHP AJAX Handler (from korboc-survey.php):</strong></p>
                    <pre style="background: white; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px;"><code>function korboc_handle_quick_account() {
    $email = sanitize_email($_POST['email']);
    
    // Create minimal user account
    $user_creator = new Korboc_User_Creator();
    $user_id = $user_creator->create_quick_account($email);
    
    // Auto-login
    $user_creator->auto_login_user($user_id);
    
    wp_send_json_success();
}</code></pre>

                    <p><strong>Files Involved:</strong></p>
                    <ul>
                        <li><code>/includes/shortcodes.php</code> - Line 325-341 (HTML structure)</li>
                        <li><code>/assets/js/survey.js</code> - Line 116-155 (JavaScript function)</li>
                        <li><code>/korboc-survey.php</code> - Line 518-557 (AJAX handler)</li>
                        <li><code>/includes/class-user-creator.php</code> - create_quick_account() method</li>
                        <li><code>/assets/css/survey.css</code> - Sticky footer styles</li>
                    </ul>
                </div>
            </details>

            <h2>━━━━━━━━━━━━━━━━━━━━━━━━━</h2>
            <h2>🔔 v5.0.0: Customizable Incomplete Survey Reminders</h2>
            <p>Customize the message shown to users who haven't completed their survey on their profile/settings pages.</p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="korboc_reminder_title">Reminder Title</label></th>
                    <td>
                        <input type="text" id="korboc_reminder_title" name="korboc_reminder_title" value="<?php echo esc_attr(get_option('korboc_reminder_title', '🎵 Lūdzu, pabeidziet aptauju! 🇱🇻')); ?>" class="regular-text" style="width: 100%;">
                        <p class="description">Main heading for the reminder message</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_reminder_message">Reminder Message</label></th>
                    <td>
                        <textarea id="korboc_reminder_message" name="korboc_reminder_message" rows="3" style="width: 100%;"><?php echo esc_textarea(get_option('korboc_reminder_message', 'Korboc.lv aptauja nav vēl pabeigta.')); ?></textarea>
                        <p class="description">Main message body</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_reminder_button_text">Button Text</label></th>
                    <td>
                        <input type="text" id="korboc_reminder_button_text" name="korboc_reminder_button_text" value="<?php echo esc_attr(get_option('korboc_reminder_button_text', '🎶 Noklikšķiniet šeit, lai turpinātu → ♥️')); ?>" class="regular-text" style="width: 100%;">
                        <p class="description">Link text to return to survey</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="korboc_reminder_footer_text">Footer Text</label></th>
                    <td>
                        <textarea id="korboc_reminder_footer_text" name="korboc_reminder_footer_text" rows="2" style="width: 100%;"><?php echo esc_textarea(get_option('korboc_reminder_footer_text', '🙏 Paldies par Jūsu laiku un ieguldījumu Latvijas koru kustībā!')); ?></textarea>
                        <p class="description">Optional footer text shown at bottom of reminder</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="korboc_save_settings" class="button button-primary" value="Save Settings">
            </p>
        </form>
        
        <hr>
        
        <h2>Usage</h2>
        <p>Add the survey to any page using this shortcode:</p>
        <p><code>[korboc_survey]</code></p>
        
        <h2>View Responses</h2>
        <p><a href="<?php echo admin_url('admin.php?page=korboc-survey-responses'); ?>" class="button">View Survey Responses</a></p>
    </div>
    <?php
}

// Enqueue CSS and JS
function korboc_survey_enqueue_assets() {
    // Enqueue survey assets
    wp_enqueue_style(
        'korboc-survey-style',
        KORBOC_SURVEY_URL . 'assets/css/survey.css',
        array(),
        KORBOC_SURVEY_VERSION
    );
    
    wp_enqueue_script(
        'korboc-survey-script',
        KORBOC_SURVEY_URL . 'assets/js/survey.js',
        array('jquery'),
        KORBOC_SURVEY_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    $current_user = wp_get_current_user();
    wp_localize_script('korboc-survey-script', 'korbocSurvey', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('korboc_survey_nonce'),
        'currentUrl' => get_permalink(),
        'isLoggedIn' => is_user_logged_in(),
        'userEmail' => is_user_logged_in() ? $current_user->user_email : '',
        'debug' => defined('WP_DEBUG') && WP_DEBUG, // Bug 13 FIX: Enable debug mode based on WP_DEBUG
        'contactUrl' => 'https://dainis.net/chat', // Bug 13 FIX: Configurable contact URL
        'messages' => array(
            'processing' => __('Apstrādā...', 'korboc-survey'),
            'error' => __('Radās kļūda. Lūdzu, mēģiniet vēlreiz.', 'korboc-survey'),
            'success' => __('Paldies! Izveidojam Jūsu profilu...', 'korboc-survey')
        )
    ));
}

/**
 * v9.1.3: Admin can manually mark survey as complete
 */
function korboc_admin_mark_complete() {
    // Security checks
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }

    check_ajax_referer('korboc_admin_nonce', 'nonce');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if (!$user_id) {
        wp_send_json_error(array('message' => 'Invalid user ID'));
    }

    // v9.2.8: Mark as complete with timestamp
    $completed_timestamp = date('YmdHis') . '_' . substr(md5(uniqid(rand(), true)), 0, 2);
    update_user_meta($user_id, 'korboc_survey_completed', $completed_timestamp);

    // Also set the survey date if not already set
    $existing_date = get_user_meta($user_id, 'korboc_survey_date', true);
    if (empty($existing_date)) {
        update_user_meta($user_id, 'korboc_survey_date', current_time('mysql'));
    }

    wp_send_json_success(array('message' => 'Survey marked as complete'));
}

// Handle survey submission
function korboc_handle_survey_submission() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'korboc_survey_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // NO time-based rate limiting on survey submission!
    // Users are authenticated and can only update their own data.
    // Multiple submissions just overwrite their profile - no harm done.
    // Let users fix mistakes immediately without waiting!
    
    // Get survey handler
    $survey_handler = new Korboc_Survey_Handler();
    $user_creator = new Korboc_User_Creator();
    
    // Check if user is already logged in
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        // Create new user from survey data
        $user_id = $user_creator->create_user_from_survey($_POST);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }
        
        // Auto-login the user
        $logged_in = $user_creator->auto_login_user($user_id);
        
        if (!$logged_in) {
            wp_send_json_error(array('message' => 'User created but login failed'));
            return;
        }
    } else {
        // v7.9.2: Check if this is a repeat submission (user filling survey again)
        $previously_completed = get_user_meta($user_id, 'korboc_survey_completed', true);

        if ($previously_completed === 'yes') {
            // Store the current submission data before overwriting
            // Use the original submission timestamp, not the current time
            $original_timestamp = get_user_meta($user_id, 'korboc_survey_date', true);

            $current_submission = array(
                'timestamp' => $original_timestamp ? $original_timestamp : current_time('mysql'),
                'data' => array(
                    'first_name' => get_user_meta($user_id, 'korboc_first_name', true),
                    'last_name' => get_user_meta($user_id, 'korboc_last_name', true),
                    'how_to_address' => get_user_meta($user_id, 'korboc_how_to_address', true),
                    'choir_name' => get_user_meta($user_id, 'korboc_choir_name', true),
                    'rehearsal_location' => get_user_meta($user_id, 'korboc_rehearsal_location', true),
                    'role_in_choir' => get_user_meta($user_id, 'korboc_role_in_choir', true),
                    'voice_part' => get_user_meta($user_id, 'korboc_voice_part', true),
                    'is_soloist' => get_user_meta($user_id, 'korboc_is_soloist', true),
                    'soloist_repertoire' => get_user_meta($user_id, 'korboc_soloist_repertoire', true),
                    'phone' => get_user_meta($user_id, 'korboc_phone', true),
                    'singer_count' => get_user_meta($user_id, 'korboc_singer_count', true),
                    'song_festival_memory' => get_user_meta($user_id, 'korboc_song_festival_memory', true),
                    'important_features' => get_user_meta($user_id, 'korboc_important_features', true),
                    'important_features_other' => get_user_meta($user_id, 'korboc_important_features_other', true),
                    'additional_valuable' => get_user_meta($user_id, 'korboc_additional_valuable', true),
                    'vision_opinion' => get_user_meta($user_id, 'korboc_vision_opinion', true),
                    'vision_opinion_other' => get_user_meta($user_id, 'korboc_vision_opinion_other', true),
                    'pricing_opinion' => get_user_meta($user_id, 'korboc_pricing_opinion', true),
                    'pricing_opinion_other' => get_user_meta($user_id, 'korboc_pricing_opinion_other', true),
                    'communication_thoughts' => get_user_meta($user_id, 'korboc_communication_thoughts', true),
                )
            );

            // Get existing submissions array
            $submissions = get_user_meta($user_id, 'korboc_survey_submissions', true);
            if (!is_array($submissions)) {
                $submissions = array();
            }

            // Add current submission to history
            $submissions[] = $current_submission;
            update_user_meta($user_id, 'korboc_survey_submissions', $submissions);

            error_log('Korboc Survey v7.9.2: Archived previous submission for user ID ' . $user_id . '. Total submissions: ' . count($submissions));
        }

        // User already logged in, update their survey data (this will overwrite with new data)
        $user_creator->update_user_survey_data($user_id, $_POST);
    }

    // v9.2.8: Mark survey as completed with timestamp
    update_user_meta($user_id, 'korboc_needs_survey', 'no');
    $completed_timestamp = date('YmdHis') . '_' . substr(md5(uniqid(rand(), true)), 0, 2);
    update_user_meta($user_id, 'korboc_survey_completed', $completed_timestamp);
    update_user_meta($user_id, 'korboc_survey_date', current_time('mysql'));
    
    // Send BuddyBoss private message with survey responses
    $message_sent = $user_creator->send_buddyboss_message($user_id, $_POST);
    
    if (!$message_sent) {
        error_log('Korboc Survey: WARNING - Message not sent for user ID ' . $user_id . '. Check debug.log for details.');
    }
    
    // Send email confirmation
    $user_creator->send_welcome_email($user_id, $_POST);
    
    // Get user object for redirect
    $user = get_user_by('id', $user_id);

    // v7.9.6: Always redirect to user's profile page after submission
    $redirect_url = home_url('/members/' . $user->user_login . '/');
    
    // v7.9.1: Return success with thank you message
    wp_send_json_success(array(
        'message' => 'Aptauja nosūtīta un Jūsu profīls ir veiksmīgi izveidots. Paldies, ka piedalījāties!',
        'redirect' => $redirect_url,
        'user_id' => $user_id
    ));
}

// Handle quick account creation (email only)
function korboc_handle_quick_account() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'korboc_survey_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // ============================================
    // SMART ANTI-SPAM PROTECTION (No time limits!)
    // ============================================

    // 1. Honeypot check - Catches 90% of bots
    if (!empty($_POST['website_url'])) {
        // Bot filled in honeypot field!
        error_log('Korboc: Honeypot triggered. Possible bot. IP: ' . korboc_get_client_ip());
        wp_send_json_error(array('message' => 'Kļūda. Lūdzu, mēģiniet vēlreiz.'));
        return;
    }

    $email = sanitize_email($_POST['email']);
    
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Lūdzu, ievadiet derīgu e-pasta adresi'));
        return;
    }

    // 2. Block throwaway/temporary email services
    $email_parts = explode('@', $email);
    $domain = strtolower($email_parts[1] ?? '');

    $blocked_domains = array(
        'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'mailinator.com',
        'throwaway.email', 'temp-mail.org', 'getnada.com', 'trashmail.com'
    );

    if (in_array($domain, $blocked_domains)) {
        error_log('Korboc: Blocked temp email domain: ' . $domain . ' IP: ' . korboc_get_client_ip());
        wp_send_json_error(array('message' => 'Lūdzu, izmantojiet pastāvīgu e-pasta adresi.'));
        return;
    }

    // 3. Smart IP-based limit - Only blocks extreme abuse (30 accounts/hour from same IP)
    // This allows cafés/offices (20 people) but blocks botnets (1000 accounts)
    $ip = korboc_get_client_ip();
    $rate_key_ip = 'korboc_account_ip_' . md5($ip);
    $ip_count = get_transient($rate_key_ip) ?: 0;

    if ($ip_count >= 30) {
        error_log('Korboc: IP limit exceeded (' . $ip_count . ' accounts). Possible bot attack from IP: ' . $ip);
        wp_send_json_error(array('message' => 'Pārāk daudz kontu no šīs vietas. Lūdzu, sazinieties ar atbalstu.'));
        return;
    }

    // Increment IP counter (resets after 1 hour)
    set_transient($rate_key_ip, $ip_count + 1, 3600);

    // Check if user already exists
    if (email_exists($email)) {
        wp_send_json_error(array('message' => 'Šis e-pasts jau ir reģistrēts. Lūdzu, piesakieties.'));
        return;
    }
    
    // Create minimal user account
    $user_creator = new Korboc_User_Creator();
    $user_id = $user_creator->create_quick_account($email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
        return;
    }

    // v7.8.26 FIX: Migrate data from sessions table (not CSV)
    error_log("Korboc Survey: Quick account - Looking for session data for email: $email");

    global $wpdb;
    $table_name = $wpdb->prefix . 'korboc_survey_sessions';

    // Look up session by email
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT field_data, session_id FROM $table_name WHERE email = %s ORDER BY updated_at DESC LIMIT 1",
        strtolower($email)
    ));

    $field_count = 0;

    if ($session) {
        // Parse JSON data
        $data = json_decode($session->field_data, true);

        if (is_array($data)) {
            foreach ($data as $field => $value) {
                // Skip meta fields
                if ($field === 'current_page' || $field === 'timestamp') {
                    continue;
                }

                // Handle arrays (checkboxes) vs single values
                if (is_array($value)) {
                    $sanitized_value = array_map('sanitize_text_field', $value);
                    update_user_meta($user_id, 'korboc_draft_' . sanitize_key($field), $sanitized_value);
                } else {
                    update_user_meta($user_id, 'korboc_draft_' . sanitize_key($field), sanitize_text_field($value));
                }
                $field_count++;
            }

            if ($field_count > 0) {
                error_log("Korboc Survey: ✓ Migrated $field_count fields from session for user $user_id");

                // Delete the session record (data now in user_meta)
                $wpdb->delete($table_name, array('session_id' => $session->session_id), array('%s'));
                error_log("Korboc Survey: ✓ Deleted session record for email: $email");
            }
        } else {
            error_log("Korboc Survey: ✗ Invalid session data format");
        }
    } else {
        error_log("Korboc Survey: ✗ No session data found for email: $email");
    }

    // Mark as needing survey
    update_user_meta($user_id, 'korboc_needs_survey', 'yes');

    // Auto-login AFTER migration completes
    $user_creator->auto_login_user($user_id);

    // v5.0.0: Send BuddyBoss message for new registration
    $user_creator->send_registration_message($user_id, $email);

    wp_send_json_success(array(
        'message' => 'Profils izveidots! Lūdzu, turpiniet aptauju.',
        'user_id' => $user_id
    ));
}

/**
 * v5.0.1: Auto-save survey data (logged in or anonymous)
 * Saves to user_meta if logged in, or to sessions table if not
 */
function korboc_handle_autosave() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'korboc_survey_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $field_data = isset($_POST['field_data']) ? stripslashes($_POST['field_data']) : '';

    if (empty($field_data)) {
        wp_send_json_error(array('message' => 'No data provided'));
        return;
    }

    // Validate JSON
    $data = json_decode($field_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => 'Invalid JSON data'));
        return;
    }

    $user_id = get_current_user_id();

    if ($user_id) {
        // v9.2.8: Set survey_started timestamp on FIRST autosave
        $existing_started = get_user_meta($user_id, 'korboc_survey_started', true);
        if (empty($existing_started)) {
            $timestamp = date('YmdHis') . '_' . substr(md5(uniqid(rand(), true)), 0, 2);
            update_user_meta($user_id, 'korboc_survey_started', $timestamp);
        }
        
        // v9.1.1: Logged in users - Save to BOTH draft keys AND live keys
        // This allows incomplete surveys to show up in admin dashboard
        foreach ($data as $field => $value) {
            if ($field === 'current_page' || $field === 'timestamp') {
                continue;
            }
            // Save to draft for form restoration
            update_user_meta($user_id, 'korboc_draft_' . sanitize_key($field), $value);
            // ALSO save to live key so admin can see incomplete answers
            update_user_meta($user_id, 'korboc_' . sanitize_key($field), $value);
        }
        wp_send_json_success(array('saved_to' => 'user_meta'));
    } else {
        // v7.0.4: ULTRA-SIMPLE - If they have email, store by email. Otherwise use session ID.
        global $wpdb;
        $table_name = $wpdb->prefix . 'korboc_survey_sessions';
        
        $email = !empty($data['email']) ? sanitize_email($data['email']) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        
        if (empty($session_id)) {
            $session_id = md5(uniqid(rand(), true));
        }

        $ip_address = korboc_get_client_ip();

        // v7.8.28 FIX: MERGE data instead of replacing to prevent data loss
        // Look up existing session (by email OR session_id)
        $existing_session = null;
        if (!empty($email)) {
            $existing_session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE email = %s ORDER BY updated_at DESC LIMIT 1",
                $email
            ));
        }
        if (!$existing_session && !empty($session_id)) {
            $existing_session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s",
                $session_id
            ));
        }

        // Merge with existing data if found
        $merged_data = $data;
        if ($existing_session && !empty($existing_session->field_data)) {
            $old_data = json_decode($existing_session->field_data, true);
            if (is_array($old_data)) {
                // Merge: new data overwrites old, but old data is preserved if not in new
                $merged_data = array_merge($old_data, $data);
                error_log('Korboc v7.8.28: Merged ' . count($old_data) . ' old fields + ' . count($data) . ' new fields = ' . count($merged_data) . ' total');
            }
        }
        
        // v9.2.8: Add survey_started timestamp on FIRST save (if not already set)
        if (empty($merged_data['survey_started'])) {
            $merged_data['survey_started'] = date('YmdHis') . '_' . substr(md5(uniqid(rand(), true)), 0, 2);
        }

        $field_data_json = wp_json_encode($merged_data);

        // Save or update
        if ($existing_session) {
            // Update existing session
            $wpdb->update(
                $table_name,
                array(
                    'email' => !empty($email) ? $email : $existing_session->email,
                    'field_data' => $field_data_json,
                    'ip_address' => $ip_address,
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing_session->id),
                array('%s', '%s', '%s', '%s'),
                array('%d')
            );
            error_log('Korboc v7.8.28: Updated session ' . $existing_session->session_id);
            wp_send_json_success(array('saved_to' => 'session_updated', 'session_id' => $existing_session->session_id));
        } else {
            // Create new session
            $wpdb->insert(
                $table_name,
                array(
                    'session_id' => $session_id,
                    'email' => $email,
                    'field_data' => $field_data_json,
                    'ip_address' => $ip_address,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            error_log('Korboc v7.8.28: Created new session ' . $session_id);
            wp_send_json_success(array('saved_to' => 'session_created', 'session_id' => $session_id));
        }
    }
}

/**
 * v5.0.1: Load draft survey data
 * Loads from user_meta if logged in, or from sessions table if not
 */
function korboc_handle_load_draft() {
    $user_id = get_current_user_id();

    if ($user_id) {
        // Logged in: Load from user_meta
        global $wpdb;
        $meta_keys = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->usermeta}
             WHERE user_id = %d AND meta_key LIKE 'korboc_draft_%%'",
            $user_id
        ));

        $data = array();
        foreach ($meta_keys as $meta) {
            $field = str_replace('korboc_draft_', '', $meta->meta_key);
            // WordPress serializes arrays in user_meta, so we need to unserialize
            $value = maybe_unserialize($meta->meta_value);

            // CHECKBOX FIX: If value is array, add [] back to field name (was sanitized away during save)
            if (is_array($value)) {
                $field .= '[]';
            }

            $data[$field] = $value;
        }

        wp_send_json_success(array('data' => $data, 'source' => 'user_meta'));
    } else {
        // Not logged in: Load from sessions table
        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';

        if (empty($session_id)) {
            wp_send_json_success(array('data' => array(), 'source' => 'none'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'korboc_survey_sessions';

        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT field_data FROM $table_name WHERE session_id = %s",
            $session_id
        ));

        if ($session) {
            $data = json_decode($session->field_data, true);
            wp_send_json_success(array('data' => $data, 'source' => 'session'));
        } else {
            wp_send_json_success(array('data' => array(), 'source' => 'none'));
        }
    }
}

/**
 * v7.0.6: Handle social login - migrate session data to newly created social login user
 * This catches FB/Google login and transfers pre-filled survey data
 * 
 * FIXED: Combines email lookup (v7.0.4) with session_id fallback (v6.0.9)
 * Now works whether user filled email or not!
 */
function korboc_handle_social_login_migration($user_login, $user) {
    error_log('Korboc Survey v7.0.6: wp_login hook fired for user: ' . $user->ID . ' (' . $user_login . ')');

    global $wpdb;
    $table_name = $wpdb->prefix . 'korboc_survey_sessions';
    $session = null;
    $field_count = 0;
    
    // STRATEGY 1: Try email lookup first (works when user filled email before social login)
    if (!empty($user->user_email)) {
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT field_data, session_id FROM $table_name WHERE email = %s ORDER BY updated_at DESC LIMIT 1",
            strtolower($user->user_email)
        ));
        
        if ($session) {
            error_log('Korboc Survey v7.0.6: ✓ Found session via EMAIL lookup: ' . $user->user_email);
        }
    }
    
    // STRATEGY 2: Fallback to session_id (works when user filled name but NOT email)
    // This is THE FIX that v7.0.0-7.0.5 were missing!
    if (!$session) {
        $session_id = '';
        
        // Check cookie first (survives in some OAuth flows)
        if (isset($_COOKIE['korboc_session_id'])) {
            $session_id = sanitize_text_field($_COOKIE['korboc_session_id']);
            error_log('Korboc Survey v7.0.6: Found session_id in COOKIE: ' . $session_id);
        } 
        // Fallback to URL parameter (ALWAYS survives OAuth redirect)
        elseif (isset($_GET['korboc_session'])) {
            $session_id = sanitize_text_field($_GET['korboc_session']);
            error_log('Korboc Survey v7.0.6: Found session_id in URL parameter: ' . $session_id);
        }
        
        // Look up by session_id
        if (!empty($session_id)) {
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT field_data, session_id FROM $table_name WHERE session_id = %s",
                $session_id
            ));
            
            if ($session) {
                error_log('Korboc Survey v7.0.6: ✓ Found session via SESSION_ID lookup: ' . $session_id);
            }
        }
    }
    
    // If still no session found, nothing to migrate
    if (!$session) {
        error_log('Korboc Survey v7.0.6: No session found via email OR session_id');
        return;
    }
    
    // Parse and migrate data
    $data = json_decode($session->field_data, true);
    if (!is_array($data)) {
        error_log('Korboc Survey v7.0.6: Invalid session data format');
        return;
    }
    
    foreach ($data as $field => $value) {
        if ($field === 'current_page' || $field === 'timestamp') {
            continue;
        }
        
        if (is_array($value)) {
            update_user_meta($user->ID, 'korboc_draft_' . sanitize_key($field), array_map('sanitize_text_field', $value));
        } else {
            update_user_meta($user->ID, 'korboc_draft_' . sanitize_key($field), sanitize_text_field($value));
        }
        $field_count++;
    }
    
    // Clean up session
    $wpdb->delete($table_name, array('session_id' => $session->session_id), array('%s'));

    error_log("Korboc Survey v7.0.6: ✓ Migrated $field_count fields to user_meta");

    // v7.9.1: Store secondary email if form email differs from social login email
    if (!empty($data['email']) && strtolower($data['email']) !== strtolower($user->user_email)) {
        update_user_meta($user->ID, 'korboc_secondary_email', sanitize_email($data['email']));
        error_log("Korboc Survey v7.9.1: Stored secondary email: " . $data['email'] . " (primary: " . $user->user_email . ")");
    }

    if ($field_count > 0) {
        update_user_meta($user->ID, 'korboc_needs_survey', 'yes');
    }
}

/**
 * v6.0.9: Wrapper for user_register hook (only provides user_id, not user object)
 */
function korboc_handle_social_login_migration_on_register($user_id) {
    $user = get_user_by('id', $user_id);
    if ($user) {
        korboc_handle_social_login_migration($user->user_login, $user);
    }
}

/**
 * v5.0.1: Daily cron job to cleanup old session data
 */
function korboc_cleanup_old_sessions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'korboc_survey_sessions';

    // Delete sessions older than 7 days
    $deleted = $wpdb->query(
        "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );

    if ($deleted) {
        error_log("Korboc Survey: Cleaned up $deleted old session records");
    }
}

// Activation hook
register_activation_hook(__FILE__, 'korboc_survey_activate');
function korboc_survey_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'korboc_survey_sessions';
    $charset_collate = $wpdb->get_charset_collate();

    // v7.0.4: Create sessions table with email column for simple lookups
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        email varchar(255) DEFAULT '',
        user_id bigint(20) UNSIGNED DEFAULT NULL,
        field_data longtext NOT NULL,
        ip_address varchar(45) DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY session_id (session_id),
        KEY email (email),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // v7.0.4: Add email column to existing tables (for upgrades)
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'email'");
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN email varchar(255) DEFAULT '' AFTER session_id");
        $wpdb->query("ALTER TABLE $table_name ADD KEY email (email)");
        error_log('Korboc v7.0.4: Added email column to sessions table');
    }

    // v9.1.5: Create magic links table for passwordless authentication
    $magic_links_table = $wpdb->prefix . 'korboc_magic_links';
    $sql_magic = "CREATE TABLE IF NOT EXISTS $magic_links_table (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        token varchar(64) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        used_at datetime DEFAULT NULL,
        ip_address varchar(45) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY user_id (user_id),
        KEY expires_at (expires_at)
    ) $charset_collate;";
    dbDelta($sql_magic);

    // Create default options
    add_option('korboc_redirect_url', home_url('/members/'));
    add_option('korboc_wlm_level_id', '');
    add_option('korboc_buddyboss_group_id', '');

    // Survey greeting text
    $default_greeting = "Input your greeting message in the Survey Settings, Survey Greeting, Greeting Text field.";

    add_option('korboc_greeting', $default_greeting);

    // Schedule daily cleanup cron job
    if (!wp_next_scheduled('korboc_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'korboc_daily_cleanup');
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'korboc_survey_deactivate');
function korboc_survey_deactivate() {
    // Unschedule cron job
    $timestamp = wp_next_scheduled('korboc_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'korboc_daily_cleanup');
    }
    
    // v9.2.6: Unschedule password expiration cron
    Korboc_Password_Expiration::unschedule_cron();

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Redirect users back to survey after login (social or email)
 */
function korboc_survey_login_redirect($redirect_to, $request, $user) {
    // Mark that user just logged in and needs survey check
    if (is_a($user, 'WP_User')) {
        $survey_completed = get_user_meta($user->ID, 'korboc_survey_completed', true);
        if ($survey_completed !== 'yes') {
            // Set a transient to trigger redirect check
            set_transient('korboc_check_survey_' . $user->ID, 'yes', 60); // 60 seconds
        }
    }
    
    // v7.0.2: Check if there's a stored return URL with session ID
    if (isset($_COOKIE['korboc_return_url'])) {
        $return_url = esc_url_raw($_COOKIE['korboc_return_url']);

        // Security: Only allow redirects to our own site (prevent open redirect)
        $home_url = home_url();
        if (strpos($return_url, $home_url) !== 0) {
            $return_url = $home_url; // Fallback to home if suspicious URL
        }

        // v7.0.2: Append session ID from cookie if present
        if (isset($_COOKIE['korboc_session_id'])) {
            $session_id = sanitize_text_field($_COOKIE['korboc_session_id']);
            $return_url = add_query_arg('korboc_sid', $session_id, $return_url);
            error_log('Korboc v7.0.2: Appending session ID to return URL: ' . $session_id);
        }

        // Clear the cookie
        setcookie('korboc_return_url', '', time() - 3600, '/');
        return $return_url;
    }
    
    // Fallback: Check if coming from survey page
    if ($request && strpos($request, 'korboc-aptauja') !== false) {
        return $request;
    }
    
    // Default: use original redirect
    return $redirect_to;
}

/**
 * Check on every page load if user needs to be redirected to survey
 * This runs via template_redirect hook (priority 1)
 */
function korboc_check_incomplete_survey_redirect() {
    // Only run for logged-in users
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Check if survey is completed
    $survey_completed = get_user_meta($user_id, 'korboc_survey_completed', true);
    
    // If survey is completed, don't redirect
    if ($survey_completed === 'yes') {
        return;
    }
    
    // Get survey URL from settings
    $survey_url = get_option('korboc_survey_url', '');
    
    // If no survey URL is set, try to find it
    if (empty($survey_url)) {
        $survey_url = home_url('/korboc-aptauja/');
    }
    
    // Get current URL (escaped to prevent XSS)
    $current_url = home_url(esc_url_raw($_SERVER['REQUEST_URI']));
    
    // Don't redirect if already on survey page
    if (strpos($current_url, 'korboc-aptauja') !== false) {
        return;
    }
    
    // Don't redirect if on admin pages
    if (is_admin()) {
        return;
    }
    
    // Don't redirect if on AJAX requests
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    
    // Check if this is a fresh login (within last 60 seconds)
    $check_survey = get_transient('korboc_check_survey_' . $user_id);
    
    // Also check if user has korboc_needs_survey flag
    $needs_survey = get_user_meta($user_id, 'korboc_needs_survey', true);
    
    // Redirect if either:
    // 1. Fresh login with incomplete survey, OR
    // 2. Has korboc_needs_survey flag set to 'yes'
    if ($check_survey === 'yes' || $needs_survey === 'yes') {
        // Redirect loop protection: Don't redirect more than 3 times
        $redirect_count = get_transient('korboc_redirect_count_' . $user_id);
        if ($redirect_count && $redirect_count >= 3) {
            error_log('Korboc Survey: Redirect loop detected for user ' . $user_id . '. Stopping redirects.');
            delete_transient('korboc_redirect_count_' . $user_id);
            delete_transient('korboc_check_survey_' . $user_id);
            return;
        }

        // Increment redirect counter (expires in 5 minutes)
        set_transient('korboc_redirect_count_' . $user_id, ($redirect_count ?: 0) + 1, 300);

        // Delete the transient so we don't redirect on every page
        delete_transient('korboc_check_survey_' . $user_id);

        // Log for debugging
        error_log('Korboc Survey: Redirecting user ' . $user_id . ' to survey: ' . $survey_url);

        // Redirect to survey
        wp_redirect($survey_url);
        exit;
    }
}

/**
 * v7.9.2: Check if user has draft survey data (started but not finished)
 */
function korboc_user_has_draft_data($user_id) {
    global $wpdb;
    $draft_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE 'korboc_draft_%%'",
        $user_id
    ));
    return $draft_count > 0;
}

/**
 * v7.9.2: Show incomplete survey message on BuddyBoss profile pages
 * Only shows if user started survey AND hasn't logged in for 24+ hours
 */
function korboc_show_incomplete_survey_message() {
    // Only show to the profile owner
    if (!bp_is_my_profile()) {
        return;
    }

    $user_id = bp_displayed_user_id();
    $survey_completed = get_user_meta($user_id, 'korboc_survey_completed', true);

    // Only show if: NOT completed AND has draft data (started survey) AND 24+ hours since account creation
    $has_draft = korboc_user_has_draft_data($user_id);

    // Check account creation time
    $user = get_userdata($user_id);
    $hours_since_creation = 0;
    if ($user && !empty($user->user_registered)) {
        $account_created = strtotime($user->user_registered);
        $hours_since_creation = (time() - $account_created) / 3600;
    }

    if ($survey_completed !== 'yes' && $has_draft && $hours_since_creation >= 24) {
        // v5.0.0: Get customizable reminder settings
        $survey_url = get_option('korboc_survey_url', home_url('/korboc-aptauja/'));
        $title = get_option('korboc_reminder_title', '🎵 Lūdzu, pabeidziet aptauju! 🇱🇻');
        $message = get_option('korboc_reminder_message', 'Korboc.lv aptauja nav vēl pabeigta.');
        $button_text = get_option('korboc_reminder_button_text', '🎶 Noklikšķiniet šeit, lai turpinātu → ♥️');
        $footer_text = get_option('korboc_reminder_footer_text', '🙏 Paldies par Jūsu laiku un ieguldījumu Latvijas koru kustībā!');

        ?>
        <div class="bp-feedback info" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <span class="bp-icon" aria-hidden="true" style="font-size: 24px; margin-right: 10px;">🎼</span>
            <strong><?php echo esc_html($title); ?></strong>
            <p style="margin: 10px 0 0 0;">
                <?php echo esc_html($message); ?>
                <a href="<?php echo esc_url($survey_url); ?>" style="font-weight: bold; color: #0073aa; text-decoration: underline;">
                    <?php echo esc_html($button_text); ?>
                </a>
            </p>
            <?php if (!empty($footer_text)): ?>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #667;">
                <?php echo esc_html($footer_text); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * v7.9.2: Show incomplete survey message on settings pages
 * Only shows if user started survey AND hasn't logged in for 24+ hours
 */
function korboc_show_incomplete_survey_message_settings() {
    $user_id = get_current_user_id();
    $survey_completed = get_user_meta($user_id, 'korboc_survey_completed', true);

    // Only show if: NOT completed AND has draft data (started survey) AND 24+ hours since account creation
    $has_draft = korboc_user_has_draft_data($user_id);

    // Check account creation time
    $user = get_userdata($user_id);
    $hours_since_creation = 0;
    if ($user && !empty($user->user_registered)) {
        $account_created = strtotime($user->user_registered);
        $hours_since_creation = (time() - $account_created) / 3600;
    }

    if ($survey_completed !== 'yes' && $has_draft && $hours_since_creation >= 24) {
        // v5.0.0: Get customizable reminder settings
        $survey_url = get_option('korboc_survey_url', home_url('/korboc-aptauja/'));
        $title = get_option('korboc_reminder_title', '🎵 Lūdzu, pabeidziet aptauju! 🇱🇻');
        $message = get_option('korboc_reminder_message', 'Korboc.lv aptauja nav vēl pabeigta.');
        $button_text = get_option('korboc_reminder_button_text', '🎶 Noklikšķiniet šeit, lai turpinātu → ♥️');
        $footer_text = get_option('korboc_reminder_footer_text', '🙏 Paldies par Jūsu laiku un ieguldījumu Latvijas koru kustībā!');

        ?>
        <div class="bp-feedback info" style="margin: 0 0 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <span class="bp-icon" aria-hidden="true" style="font-size: 24px; margin-right: 10px;">🎼</span>
            <strong><?php echo esc_html($title); ?></strong>
            <p style="margin: 10px 0 0 0;">
                <?php echo esc_html($message); ?>
                <a href="<?php echo esc_url($survey_url); ?>" style="font-weight: bold; color: #0073aa; text-decoration: underline;">
                    <?php echo esc_html($button_text); ?>
                </a>
            </p>
            <?php if (!empty($footer_text)): ?>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #667;">
                <?php echo esc_html($footer_text); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

/**
 * v8.0.7: Helper function to render a single submission's data
 */
function korboc_render_submission_data($data, $labels, $submission_num, $timestamp, $is_latest = false) {
    $border_color = $is_latest ? '#0073aa' : '#998';
    $formatted_timestamp = $timestamp ? korboc_format_latvian_timestamp($timestamp) : '';
    ?>
    <div style="background: #fff; padding: 20px; margin-bottom: 20px; border-left: 4px solid <?php echo $border_color; ?>;">
        <h3 style="margin-top: 0; font-size: 18px; color: #0073aa;">
            Aptauja izpildīta <?php echo esc_html($formatted_timestamp); ?>
        </h3>

        <!-- Song Festival Memory -->
        <div style="margin-bottom: 20px;">
            <strong style="color: #0073aa;">Kāda ir Jūsu visskaistākā Dziesmu svētku atmiņa?</strong><br>
            <?php if (!empty($data['song_festival_memory'])): ?>
                <span style="color: #555; white-space: pre-line;"><?php echo esc_html($data['song_festival_memory']); ?></span>
            <?php else: ?>
                <span style="color: #998; font-style: italic;">Neatbildēts</span>
            <?php endif; ?>
        </div>

        <!-- Drone Vision -->
        <div style="margin-bottom: 20px;">
            <strong style="color: #0073aa;">Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka?</strong><br>
            <?php if (!empty($data['vision_opinion']) && is_array($data['vision_opinion'])): ?>
                <ul style="margin: 5px 0 0 20px; color: #555;">
                    <?php foreach ($data['vision_opinion'] as $opinion): ?>
                        <li><?php echo esc_html($opinion); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($data['vision_opinion_other'])): ?>
                    <div style="margin-left: 20px; font-style: italic; color: #777;">Cits: <?php echo esc_html($data['vision_opinion_other']); ?></div>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #998; font-style: italic;">Neatbildēts</span>
            <?php endif; ?>
        </div>

        <!-- Profile Fields -->
        <div style="margin-top: 20px;">
            <h4 style="color: #0073aa; margin-bottom: 10px;">Profila Informācija</h4>
            <?php foreach ($labels as $key => $label): ?>
                <?php if (!empty($data[$key])): ?>
                <div style="margin-bottom: 10px;">
                    <strong><?php echo esc_html($label); ?></strong><br>
                    <span style="color: #555;"><?php echo esc_html($data[$key]); ?></span>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Survey Questions -->
        <div style="margin-top: 20px;">
            <h4 style="color: #0073aa; margin-bottom: 10px;">Aptaujas Jautājumi</h4>

            <div style="margin-bottom: 15px;">
                <strong>1.J - Vissvarīgākās funkcijas:</strong><br>
                <?php if (!empty($data['important_features']) && is_array($data['important_features'])): ?>
                    <ul style="margin: 5px 0 0 20px; color: #555;">
                        <?php foreach ($data['important_features'] as $feature): ?>
                            <li><?php echo esc_html($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($data['important_features_other'])): ?>
                        <div style="margin-left: 20px; font-style: italic; color: #777;">Cits: <?php echo esc_html($data['important_features_other']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #998; font-style: italic;">Neatbildēts</span>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>2.J - Kas vēl jāiekļauj:</strong><br>
                <?php if (!empty($data['additional_valuable'])): ?>
                    <span style="color: #555; white-space: pre-line;"><?php echo esc_html($data['additional_valuable']); ?></span>
                <?php else: ?>
                    <span style="color: #998; font-style: italic;">Neatbildēts</span>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>4.J - Viedoklis par cenu:</strong><br>
                <?php if (!empty($data['pricing_opinion']) && is_array($data['pricing_opinion'])): ?>
                    <ul style="margin: 5px 0 0 20px; color: #555;">
                        <?php foreach ($data['pricing_opinion'] as $opinion): ?>
                            <li><?php echo esc_html($opinion); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!empty($data['pricing_opinion_other'])): ?>
                        <div style="margin-left: 20px; font-style: italic; color: #777;">Cits: <?php echo esc_html($data['pricing_opinion_other']); ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #998; font-style: italic;">Neatbildēts</span>
                <?php endif; ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>5.J - Komunikācijas domas:</strong><br>
                <?php if (!empty($data['communication_thoughts'])): ?>
                    <span style="color: #555; white-space: pre-line;"><?php echo esc_html($data['communication_thoughts']); ?></span>
                <?php else: ?>
                    <span style="color: #998; font-style: italic;">Neatbildēts</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * v7.9.2: Display completed survey responses on profile page (read-only)
 */
function korboc_display_profile_survey() {
    // Only show to the profile owner
    if (!bp_is_my_profile()) {
        return;
    }

    $user_id = bp_displayed_user_id();
    $survey_completed = get_user_meta($user_id, 'korboc_survey_completed', true);

    // Only show if survey has been completed
    if ($survey_completed !== 'yes') {
        return;
    }

    // Get all survey data
    $data = array(
        'song_festival_memory' => get_user_meta($user_id, 'korboc_song_festival_memory', true),
        'first_name' => get_user_meta($user_id, 'korboc_first_name', true),
        'last_name' => get_user_meta($user_id, 'korboc_last_name', true),
        'how_to_address' => get_user_meta($user_id, 'korboc_how_to_address', true),
        'choir_name' => get_user_meta($user_id, 'korboc_choir_name', true),
        'rehearsal_location' => get_user_meta($user_id, 'korboc_rehearsal_location', true),
        'role_in_choir' => get_user_meta($user_id, 'korboc_role_in_choir', true),
        'voice_part' => get_user_meta($user_id, 'korboc_voice_part', true),
        'is_soloist' => get_user_meta($user_id, 'korboc_is_soloist', true),
        'soloist_repertoire' => get_user_meta($user_id, 'korboc_soloist_repertoire', true),
        'phone' => get_user_meta($user_id, 'korboc_phone', true),
        'singer_count' => get_user_meta($user_id, 'korboc_singer_count', true),
        'important_features' => get_user_meta($user_id, 'korboc_important_features', true),
        'important_features_other' => get_user_meta($user_id, 'korboc_important_features_other', true),
        'additional_valuable' => get_user_meta($user_id, 'korboc_additional_valuable', true),
        'vision_opinion' => get_user_meta($user_id, 'korboc_vision_opinion', true),
        'vision_opinion_other' => get_user_meta($user_id, 'korboc_vision_opinion_other', true),
        'pricing_opinion' => get_user_meta($user_id, 'korboc_pricing_opinion', true),
        'pricing_opinion_other' => get_user_meta($user_id, 'korboc_pricing_opinion_other', true),
        'communication_thoughts' => get_user_meta($user_id, 'korboc_communication_thoughts', true),
    );

    // Get labels
    $labels = array(
        'first_name' => get_option('korboc_label_first_name', '1. Jūsu vārds (priekšvārds)? *'),
        'last_name' => get_option('korboc_label_last_name', '2. Jūsu uzvārds? *'),
        'how_to_address' => get_option('korboc_label_how_to_address', '3. Kā Jūs uzrunāt? *'),
        'choir_name' => get_option('korboc_label_choir_name', '4. Kā sauc Jūsu kori?'),
        'rehearsal_location' => get_option('korboc_label_rehearsal_location', '5. Kurā vietā pārsvarā notiek jūsu mēģinājumi?'),
        'role_in_choir' => get_option('korboc_label_role_in_choir', '6. Jūsu loma korī?'),
        'voice_part' => get_option('korboc_label_voice_part', '7. Kādā balsī Jūs dziedat?'),
        'is_soloist' => get_option('korboc_label_is_soloist', '8. Vai esi solists/soliste?'),
        'soloist_repertoire' => get_option('korboc_label_soloist_repertoire', 'Ja vēlies tikt iekļauts/iekļauta korboc.lv solistu datubāzē, lūdzu apraksti, kuras solo partijas esi dziedājis/dziedājusi.'),
        'phone' => get_option('korboc_label_phone', '10. Telefons?'),
        'singer_count' => get_option('korboc_label_singer_count', '11. Apmēram cik dziedātāju dzied Jūsu korī? *'),
    );

    // Get survey date
    $survey_date = get_user_meta($user_id, 'korboc_survey_date', true);
    $submissions = get_user_meta($user_id, 'korboc_survey_submissions', true);
    if (!is_array($submissions)) $submissions = array();

    $total_submissions = count($submissions) + 1; // Previous + current

    // Grammar: singular vs genitive plural
    $title_text = $total_submissions === 1 ? 'Jūsu Aptaujas Atbildes' : 'Jūsu Aptauju Atbildes';

    ?>
    <div class="bp-widget korboc-survey-display" style="margin: 20px 0; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
            <h2 style="margin: 0; font-size: 22px;">
                🎵 <?php echo $title_text; ?> (<?php echo $total_submissions; ?>)
            </h2>
            <?php if ($total_submissions > 1): ?>
            <div>
                <button onclick="korbocExpandAll()" class="button" style="margin-right: 5px; padding: 5px 15px; font-size: 14px;">Atvērt Visas</button>
                <button onclick="korbocCollapseAll()" class="button" style="padding: 5px 15px; font-size: 14px;">Aizvērt Visas</button>
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Display latest submission FIRST (expanded by default)
        $submission_id = 'submission-' . $user_id . '-latest';
        ?>
        <div class="korboc-submission-accordion">
            <div class="korboc-accordion-header" onclick="korbocToggle('<?php echo $submission_id; ?>')" style="cursor: pointer; padding: 15px; background: #fff; border: 2px solid #0073aa; border-radius: 4px; margin-bottom: 10px;">
                <h3 style="margin: 0; color: #0073aa;">
                    ▼ Aptauja izpildīta <?php echo esc_html(korboc_format_latvian_timestamp($survey_date)); ?>
                </h3>
            </div>
            <div id="<?php echo $submission_id; ?>" class="korboc-accordion-content" style="display: block; margin-bottom: 20px;">
                <?php
                korboc_render_submission_data(
                    $data,
                    $labels,
                    $total_submissions,
                    $survey_date,
                    true
                );
                ?>
            </div>
        </div>

        <?php
        // Display previous submissions in reverse order (newest to oldest) - all collapsed by default
        if (count($submissions) > 0):
            $reversed_submissions = array_reverse($submissions);
            $submission_num = count($submissions);
            foreach ($reversed_submissions as $prev_submission):
                $submission_id = 'submission-' . $user_id . '-' . $submission_num;
                ?>
                <div class="korboc-submission-accordion">
                    <div class="korboc-accordion-header" onclick="korbocToggle('<?php echo $submission_id; ?>')" style="cursor: pointer; padding: 15px; background: #fff; border: 2px solid #998; border-radius: 4px; margin-bottom: 10px;">
                        <h3 style="margin: 0; color: #667;">
                            ► Aptauja izpildīta <?php echo esc_html(korboc_format_latvian_timestamp($prev_submission['timestamp'])); ?>
                        </h3>
                    </div>
                    <div id="<?php echo $submission_id; ?>" class="korboc-accordion-content" style="display: none; margin-bottom: 20px;">
                        <?php
                        korboc_render_submission_data(
                            $prev_submission['data'],
                            $labels,
                            $submission_num,
                            $prev_submission['timestamp'],
                            false
                        );
                        ?>
                    </div>
                </div>
                <?php
                $submission_num--;
            endforeach;
        endif;
        ?>

        <script>
        function korbocToggle(id) {
            var content = document.getElementById(id);
            var header = content.previousElementSibling;
            var arrow = header.querySelector('h3');

            if (content.style.display === 'none') {
                content.style.display = 'block';
                arrow.innerHTML = arrow.innerHTML.replace('►', '▼');
            } else {
                content.style.display = 'none';
                arrow.innerHTML = arrow.innerHTML.replace('▼', '►');
            }
        }

        function korbocExpandAll() {
            var contents = document.querySelectorAll('.korboc-accordion-content');
            var headers = document.querySelectorAll('.korboc-accordion-header h3');
            contents.forEach(function(content) {
                content.style.display = 'block';
            });
            headers.forEach(function(header) {
                header.innerHTML = header.innerHTML.replace('►', '▼');
            });
        }

        function korbocCollapseAll() {
            var contents = document.querySelectorAll('.korboc-accordion-content');
            var headers = document.querySelectorAll('.korboc-accordion-header h3');
            contents.forEach(function(content) {
                content.style.display = 'none';
            });
            headers.forEach(function(header) {
                header.innerHTML = header.innerHTML.replace('▼', '►');
            });
        }
        </script>

        <!-- Re-fill Survey Button (only show if less than 2 submissions) -->
        <?php if ($total_submissions < 2): ?>
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <a href="<?php echo esc_url(home_url('/korboc-aptauja/')); ?>" class="button" style="background: #0073aa; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
                🎶 Aizpildīt Aptauju Vēlreiz
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * v8.0.1: Format timestamp in Latvian format
 * Example: 2025. g. 28. novembrī 14:04:26
 */
function korboc_format_latvian_timestamp($timestamp) {
    if (empty($timestamp)) return '';

    $months_locative = array(
        1 => 'janvārī', 2 => 'februārī', 3 => 'martā', 4 => 'aprīlī',
        5 => 'maijā', 6 => 'jūnijā', 7 => 'jūlijā', 8 => 'augustā',
        9 => 'septembrī', 10 => 'oktobrī', 11 => 'novembrī', 12 => 'decembrī'
    );

    $date = strtotime($timestamp);
    $year = date('Y', $date);
    $month = $months_locative[intval(date('n', $date))];
    $day = date('j', $date);
    $time = date('H:i:s', $date);

    return "{$year}. g. {$day}. {$month} {$time}";
}

/**
 * v7.9.2: Register "Aptaujas" (Surveys) tab in BuddyPress profile
 */
function korboc_add_surveys_profile_tab() {
    // Only add tab if BuddyPress is active
    if (!function_exists('bp_core_new_nav_item')) {
        return;
    }

    bp_core_new_nav_item(array(
        'name' => 'Aptaujas',
        'slug' => 'aptaujas',
        'screen_function' => 'korboc_surveys_screen',
        'position' => 95, // After Points (90)
        'default_subnav_slug' => 'view'
    ));
}
add_action('bp_setup_nav', 'korboc_add_surveys_profile_tab', 100);

/**
 * v7.9.2: Screen function for Aptaujas tab
 */
function korboc_surveys_screen() {
    add_action('bp_template_content', 'korboc_surveys_tab_content');
    bp_core_load_template('members/single/plugins');
}

/**
 * v7.9.2: Display content for Aptaujas tab
 */
function korboc_surveys_tab_content() {
    // Only show to the profile owner
    if (!bp_is_my_profile()) {
        echo '<div class="bp-feedback info"><p>Jūs nevarat skatīt citu lietotāju aptaujas.</p></div>';
        return;
    }

    $user_id = bp_displayed_user_id();

    ?>
    <div class="korboc-surveys-container" style="padding: 20px 0;">
        <h2 style="font-size: 24px; margin-bottom: 20px;">Aptaujas</h2>

        <?php
        // Display Korboc.lv Survey if completed
        $survey_completed = get_user_meta($user_id, 'korboc_survey_completed', true);

        if ($survey_completed === 'yes') {
            korboc_display_profile_survey();
        } else {
            ?>
            <div class="bp-feedback info" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <p><strong>🎵 Korboc.lv Aptauja</strong></p>
                <p>Korboc.lv aptauja nav vēl pabeigta.</p>
                <p><a href="<?php echo esc_url(home_url('/korboc-aptauja/')); ?>" class="button">Turpināt Aptauju</a></p>
            </div>
            <?php
        }

        // Placeholder for future surveys
        ?>
        <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border: 1px dashed #ddd; border-radius: 4px; text-align: center; color: #667;">
            <p><em>Šeit parādīsies citas aptaujas nākotnē...</em></p>
        </div>
    </div>
    <?php
}

/**
 * v9.1.5: Add "Create Magic Survey Link" to user row actions
 */
function korboc_add_magic_link_row_action($actions, $user) {
    if (current_user_can('manage_options')) {
        $actions['korboc_magic_link'] = sprintf(
            '<a href="javascript:void(0);" class="korboc-create-magic-link" data-user-id="%d" data-user-name="%s">%s</a>',
            $user->ID,
            esc_attr($user->display_name),
            __('Create Magic Survey Link', 'korboc-survey')
        );
    }
    return $actions;
}
add_filter('user_row_actions', 'korboc_add_magic_link_row_action', 10, 2);

/**
 * v9.1.6: Enqueue scripts for magic link modal on users page
 */
function korboc_enqueue_admin_scripts() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') {
        return;
    }
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'korboc_enqueue_admin_scripts');

/**
 * v9.1.6: Output magic link modal HTML and JavaScript
 */
function korboc_admin_footer_scripts() {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'users') {
        return;
    }
    ?>
    <style>
        .korboc-magic-modal {
            display: none;
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .korboc-magic-modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .korboc-magic-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 20px;
        }
        .korboc-magic-close:hover,
        .korboc-magic-close:focus {
            color: #000;
        }
        .korboc-magic-link-box {
            background: #f5f5f5;
            border: 2px solid #2271b1;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
        }
        .korboc-copy-btn {
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        .korboc-copy-btn:hover {
            background: #135e96;
        }
        .korboc-copy-success {
            color: #46b450;
            margin-left: 10px;
            display: none;
        }
        .korboc-magic-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 12px;
            margin: 15px 0;
        }
    </style>
    <script>
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    jQuery(document).ready(function($) {
        // Create modal HTML
        $('body').append(`
            <div id="korboc-magic-modal" class="korboc-magic-modal">
                <div class="korboc-magic-modal-content">
                    <span class="korboc-magic-close">&times;</span>
                    <h2>Magic Survey Link</h2>
                    <p>Link for: <strong><span id="korboc-user-name"></span></strong></p>
                    <div class="korboc-magic-info">
                        <p>🔒 <strong>Secure passwordless login</strong></p>
                        <p>✅ Reusable for 48 hours</p>
                        <p>🎵 Redirects to survey at dainis.net/korboc-aptauja/</p>
                    </div>
                    <div class="korboc-magic-link-box" id="korboc-magic-link-text"></div>
                    <button class="korboc-copy-btn" onclick="korbocCopyMagicLink()">📋 Copy Link</button>
                    <span class="korboc-copy-success">✓ Copied!</span>
                </div>
            </div>
        `);

        // Handle click on "Create Magic Survey Link"
        $(document).on('click', '.korboc-create-magic-link', function(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            
            $('#korboc-user-name').text(userName);
            $('#korboc-magic-link-text').html('Generating...');
            $('#korboc-magic-modal').show();
            
            // Generate link via AJAX
            $.post(ajaxurl, {
                action: 'korboc_generate_magic_link',
                user_id: userId,
                nonce: '<?php echo wp_create_nonce('korboc_magic_link'); ?>'
            }, function(response) {
                if (response.success) {
                    $('#korboc-magic-link-text').text(response.data.link);
                } else {
                    $('#korboc-magic-link-text').html('<span style="color: red;">Error: ' + response.data.message + '</span>');
                }
            });
        });

        // Close modal
        $(document).on('click', '.korboc-magic-close', function() {
            $('#korboc-magic-modal').hide();
        });

        // Close on outside click
        $(window).on('click', function(e) {
            if (e.target.id === 'korboc-magic-modal') {
                $('#korboc-magic-modal').hide();
            }
        });
    });

    function korbocCopyMagicLink() {
        const linkText = document.getElementById('korboc-magic-link-text').textContent;
        navigator.clipboard.writeText(linkText).then(function() {
            jQuery('.korboc-copy-success').show();
            setTimeout(function() {
                jQuery('.korboc-copy-success').fadeOut();
            }, 2000);
        });
    }
    </script>
    <?php
}
add_action('admin_footer', 'korboc_admin_footer_scripts');

/**
 * v9.1.5: AJAX handler to generate magic link
 */
function korboc_generate_magic_link() {
    // Security check
    check_ajax_referer('korboc_magic_link', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    $user_id = intval($_POST['user_id']);
    if (!$user_id || !get_userdata($user_id)) {
        wp_send_json_error(array('message' => 'Invalid user'));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'korboc_magic_links';
    
    // Generate cryptographically secure token
    $token = bin2hex(random_bytes(32));
    
    // 48 hours expiration
    $expires_at = date('Y-m-d H:i:s', strtotime('+48 hours'));
    
    // Insert into database
    $inserted = $wpdb->insert(
        $table,
        array(
            'token' => $token,
            'user_id' => $user_id,
            'created_at' => current_time('mysql'),
            'expires_at' => $expires_at,
            'used_at' => null,
            'ip_address' => korboc_get_client_ip()
        ),
        array('%s', '%d', '%s', '%s', '%s', '%s')
    );
    
    if ($inserted) {
        $link = home_url('/?korboc_magic=' . $token);
        wp_send_json_success(array('link' => $link, 'expires' => $expires_at));
    } else {
        wp_send_json_error(array('message' => 'Database error'));
    }
}

/**
 * v9.1.5: Handle magic link auto-login
 */
function korboc_handle_magic_link() {
    // Check if magic link parameter exists
    if (!isset($_GET['korboc_magic'])) {
        return;
    }
    
    $token = sanitize_text_field($_GET['korboc_magic']);
    
    global $wpdb;
    $table = $wpdb->prefix . 'korboc_magic_links';
    
    // Find the token
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE token = %s",
        $token
    ));
    
    // Check if token exists
    if (!$link) {
        wp_die('Invalid magic link. Please contact support.', 'Invalid Link', array('response' => 403));
    }
    
    // Check if expired
    if (strtotime($link->expires_at) < time()) {
        wp_die('This magic link has expired. Please request a new one.', 'Link Expired', array('response' => 403));
    }
    
    // Token is valid - log the user in
    wp_set_current_user($link->user_id);
    wp_set_auth_cookie($link->user_id, true);
    
    // Update used_at timestamp (but keep token reusable)
    $wpdb->update(
        $table,
        array('used_at' => current_time('mysql')),
        array('id' => $link->id),
        array('%s'),
        array('%d')
    );
    
    // Redirect to survey (before wp_login action to avoid redirect conflicts)
    wp_redirect('https://dainis.net/korboc-aptauja/');
    exit;
}

