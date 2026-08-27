<?php
/**
 * Plugin Name: FLOSC
 * Plugin URI: https://flosc.ai
 * Description: (F)reeline --> (L)ogin --> (O)ffer --> (S)ale --> (C)ontent: try-before-you-buy WordPress journeys.
 * Version: 8.0.0
 * Requires at least: 7.0.4
 * Requires PHP: 7.4
 * Author: Dainis W. Michel
 * Author URI: https://dainis.net
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: flosc
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('FLOSC_VERSION', '8.0.0');
// v8.0.1: Runtime debug mode override from Administration tab.
// Modes: inherit (follow WP_DEBUG), on (force), off (disable).
$flosc_debug_mode = function_exists('get_option') ? get_option('flosc_debug_mode', 'inherit') : 'inherit';
if ($flosc_debug_mode === 'on') {
    $flosc_debug_enabled = true;
} elseif ($flosc_debug_mode === 'off') {
    $flosc_debug_enabled = false;
} else {
    $flosc_debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
}
define('FLOSC_DEBUG', $flosc_debug_enabled);
define('FLOSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FLOSC_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!function_exists('flosc_log')) {
    /**
     * Debug logger: writes under uploads/flosc-logs when FLOSC_DEBUG is on (no error_log).
     *
     * @param mixed $msg Message or structure to log.
     */
    function flosc_log($msg) {
        if ( ! defined( 'FLOSC_DEBUG' ) || ! FLOSC_DEBUG ) {
            return;
        }
        $line = is_scalar( $msg ) ? (string) $msg : wp_json_encode( $msg );
        if ( ! is_string( $line ) || $line === '' ) {
            return;
        }
        if ( ! class_exists( 'FLOSC_Filesystem', false ) ) {
            $fs_file = FLOSC_PLUGIN_DIR . 'includes/filesystem/class-flosc-filesystem.php';
            if ( is_readable( $fs_file ) ) {
                require_once $fs_file;
            }
        }
        if ( ! class_exists( 'FLOSC_Filesystem' ) ) {
            return;
        }
        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return;
        }
        $dir = trailingslashit( $uploads['basedir'] ) . 'flosc-logs';
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $path  = $dir . '/debug.log';
        $entry = '[' . gmdate( 'c' ) . '] ' . $line . "\n";
        $fs    = new FLOSC_Filesystem();
        $fs->protect_uploads_dir_with_htaccess( $dir );
        $existing = $fs->read_file_safely( $path );
        if ( ! is_string( $existing ) ) {
            $existing = '';
        }
        if ( strlen( $existing ) > 524288 ) {
            $existing = substr( $existing, -262144 );
        }
        $fs->write_file_safely( $path, $existing . $entry );
    }
}


// Domain: filesystem helpers then path helpers (write gate needs FLOSC_Filesystem).
require_once FLOSC_PLUGIN_DIR . 'includes/filesystem/class-flosc-filesystem.php';
require_once FLOSC_PLUGIN_DIR . 'includes/filesystem/flosc-data-paths.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-available-providers.php';
require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-wp-ai-client.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-personality-library.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-knowledge-bases.php';

// v1.2.9: Auto-flush permalinks on activation
register_activation_hook(__FILE__, 'flosc_activation_flush');
function flosc_activation_flush() {
    // Schedule flush for next init (after rewrite rules are registered)
    update_option('flosc_needs_flush', true);
    update_option('flosc_last_permalink_flush', flosc_michel_timestamp_global());
}

// v1.3.4: Version-based auto-flush on plugin update - IMMEDIATE flush
add_action('admin_init', 'flosc_version_flush_check');
function flosc_version_flush_check() {
    $last_flushed_version = get_option('flosc_last_flushed_version', '0.0.0');

    if (version_compare(FLOSC_VERSION, $last_flushed_version, '>')) {
        flush_rewrite_rules();
        update_option('flosc_last_flushed_version', FLOSC_VERSION);
        update_option('flosc_last_permalink_flush', flosc_michel_timestamp_global());

        if (function_exists('flosc') && method_exists(flosc(), 'backfill_flow_defaults')) {
            flosc()->backfill_flow_defaults();
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: Version change detected ({$last_flushed_version} → " . FLOSC_VERSION . ") - flushed permalinks");
        }
    }
}

if (!function_exists('flosc_legacy_autoprompt_is_sandbox_pill')) {
    function flosc_legacy_autoprompt_is_sandbox_pill($pill) {
        if (!is_array($pill)) return false;

        $fields = [
            strtolower(trim((string)($pill['label'] ?? ''))),
            strtolower(trim((string)($pill['user_input'] ?? ''))),
            strtolower(trim((string)($pill['action'] ?? ''))),
            strtolower(trim((string)($pill['trigger_type'] ?? ''))),
            strtolower(trim((string)($pill['trigger_value'] ?? ''))),
        ];
        $haystack = implode(' ', array_filter($fields, static function($value) {
            return $value !== '';
        }));

        if ($haystack === '') return false;

        return (
            strpos($haystack, 'sandbox') !== false &&
            (
                strpos($haystack, 'test purchase') !== false ||
                strpos($haystack, 'sandbox purchase') !== false ||
                strpos($haystack, 'open_sandbox_purchase') !== false ||
                strpos($haystack, 'sandbox_purchase') !== false
            )
        );
    }
}

if (!function_exists('flosc_legacy_autoprompt_purge_sandbox_pills')) {
    function flosc_legacy_autoprompt_purge_sandbox_pills($autoprompts) {
        if (!is_array($autoprompts)) return [];

        $cleaned = [];
        foreach (['visitor', 'guest', 'member'] as $state) {
            $cleaned[$state] = [];
            foreach (($autoprompts[$state] ?? []) as $pill) {
                if (flosc_legacy_autoprompt_is_sandbox_pill($pill)) {
                    continue;
                }
                $cleaned[$state][] = $pill;
            }
        }

        return $cleaned;
    }
}

add_action('init', 'flosc_purge_legacy_sandbox_autoprompts', 4);
function flosc_purge_legacy_sandbox_autoprompts() {
    if (get_option('flosc_legacy_sandbox_autoprompt_purged')) return;

    $ivr_dir = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
    if (!$ivr_dir || !is_dir($ivr_dir)) return;

    $files = array_merge(
        glob($ivr_dir . '*_ivr.md') ?: [],
        glob($ivr_dir . 'ivr*.md') ?: []
    );

    $changed = false;
    foreach (array_unique($files) as $ivr_file) {
        $fname = basename($ivr_file);
        $key   = 'flosc_flow_' . sanitize_key(pathinfo($fname, PATHINFO_FILENAME));
        $fs    = get_option($key, []);
        if (empty($fs) || empty($fs['autoprompts']) || !is_array($fs['autoprompts'])) {
            continue;
        }

        $cleaned = flosc_legacy_autoprompt_purge_sandbox_pills($fs['autoprompts']);
        if ($cleaned !== $fs['autoprompts']) {
            $fs['autoprompts'] = $cleaned;
            update_option($key, $fs);
            $changed = true;
        }
    }

    if ($changed) {
        update_option('flosc_legacy_sandbox_autoprompt_purged', true);
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC: Purged legacy sandbox autoprompts from flow settings');
    } else {
        update_option('flosc_legacy_sandbox_autoprompt_purged', true);
    }
}

// v8.0.0: One-time IVR re-parse — fixes guest_upgrade action (was show_offer_full_access → checkout_flow_full_offer)
// Runs once on next page load, then sets a flag so it never runs again.
if (!get_option('flosc_ivr_reparse_800')) {
    add_action('init', function() {
        $ivr_dir = defined('FLOSC_PLUGIN_DIR') ? FLOSC_PLUGIN_DIR . 'ai_configuration_files/' : '';
        if ($ivr_dir && is_dir($ivr_dir)) {
            require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-ivr-parser.php';
            $parser = FLOSC_IVR_Parser::flosc_instance();
            $files  = array_merge(
                glob($ivr_dir . '*_ivr.md') ?: [],
                glob($ivr_dir . 'ivr*.md')  ?: []
            );
            foreach (array_unique($files) as $ivr_file) {
                $fname    = basename($ivr_file);
                $key      = 'flosc_flow_' . sanitize_key(pathinfo($fname, PATHINFO_FILENAME));
                $fs       = get_option($key, []);
                $markdown = flosc_fs_get_contents($ivr_file);
                if (!$markdown) continue;
                $config   = $parser->flosc_parse($markdown);
                $messages = $config['messages'] ?? [];
                $pills    = ['visitor' => [], 'guest' => [], 'member' => []];
                foreach ($messages as $msg) {
                    if (($msg['type'] ?? '') !== 'suggested_user_autoprompt') continue;
                    $cond = $msg['conditions'] ?? $msg['condition'] ?? '';
                    foreach (['visitor', 'guest', 'member'] as $s) {
                        if ($cond === 'always' || strpos($cond, 'is_' . $s) !== false) {
                            $pills[$s][] = [
                                'icon'          => $msg['icon']          ?? '',
                                'label'         => $msg['label']         ?? ($msg['name'] ?? ''),
                                'user_input'    => $msg['user_input']    ?? ($msg['label'] ?? ''),
                                'trigger_type'  => $msg['trigger_type']  ?? 'ai',
                                'trigger_value' => $msg['trigger_value'] ?? '',
                                'action'        => $msg['action']        ?? '',
                                'conditions'    => $cond,
                                'style'         => $msg['style']         ?? ($msg['message_style'] ?? 'pill'),
                            ];
                        }
                    }
                }
                $fs['autoprompts'] = $pills;
                // Runtime lists: flow_messages / flow_phases / flow_styles only.
                if (function_exists('flosc_flow_set_runtime')) {
                    flosc_flow_set_runtime(
                        $fs,
                        $messages,
                        $config['phases'] ?? [],
                        $config['styles'] ?? []
                    );
                } else {
                    $fs['flow_messages'] = $messages;
                    $fs['flow_phases']   = $config['phases'] ?? [];
                    $fs['flow_styles']   = $config['styles'] ?? [];
                    unset($fs['ivr_messages'], $fs['ivr_phases'], $fs['ivr_styles']);
                }
                // Fresh install: re-parse used to write messages-only options, skipping
                // admin seed (empty() false) and hiding View Flow (needs status+slug).
                $stem_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($fname, PATHINFO_FILENAME)));
                if ($stem_slug === '') {
                    $stem_slug = 'flosc';
                }
                if (empty($fs['slug']) || !is_string($fs['slug'])) {
                    $fs['slug'] = $stem_slug;
                }
                if (empty($fs['status']) || !is_string($fs['status'])) {
                    $fs['status'] = 'active';
                } elseif (!in_array($fs['status'], ['active', 'draft'], true)) {
                    $fs['status'] = 'active';
                }
                if (empty($fs['name']) || !is_string($fs['name'])) {
                    $shipped = function_exists('flosc_shipped_flow_display_name')
                        ? flosc_shipped_flow_display_name($fname)
                        : '';
                    $fs['name'] = $shipped !== ''
                        ? $shipped
                        : ucwords(str_replace(['_', '-', 'ivr', '.md'], [' ', ' ', '', ''], $fname));
                }
                if (!isset($fs['primary_color']) || $fs['primary_color'] === '') {
                    $fs['primary_color'] = '#4f46e5';
                }
                update_option($key, $fs);
            }
        }
        update_option('flosc_ivr_reparse_800', true);
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC v8.0.0: One-time IVR re-parse complete');
    }, 5);
}

// v1.2.9: Michel timestamp generator (global scope for activation hook)
function flosc_michel_timestamp_global() {
    return gmdate('Y') . 'y-' . gmdate('m') . 'm-' . gmdate('d') . 'd-UTC' . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's';
}

/**
 * Friendly display names for shipped sample IVR stems (Identity / sidebar).
 *
 * @param string $ivr_filename_or_stem e.g. flosc_default_technical_ivr.md
 * @return string Empty if not a known shipped sample (caller falls back).
 */
function flosc_shipped_flow_display_name($ivr_filename_or_stem) {
    $stem = sanitize_key(pathinfo(basename((string) $ivr_filename_or_stem), PATHINFO_FILENAME));
    if ($stem === '') {
        $stem = sanitize_key((string) $ivr_filename_or_stem);
    }
    $map = [
        'flosc_default_technical_ivr' => 'Tech Agent',
        'flosc_default_friendly_ivr'  => 'Friendly Guide',
        'flosc_default_ivr'           => 'FLOSC Starter',
    ];
    return $map[$stem] ?? '';
}

/**
 * Shipped IVR files that are personality flavors, not product journeys.
 * They belong in the Personalities library, not in Switch Flow.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return bool
 */
function flosc_is_shipped_personality_sample_ivr( $ivr_filename_or_stem ) {
    $stem = sanitize_key( pathinfo( basename( (string) $ivr_filename_or_stem ), PATHINFO_FILENAME ) );
    if ( $stem === '' ) {
        $stem = sanitize_key( (string) $ivr_filename_or_stem );
    }
    $samples = array(
        'flosc_default_ivr',
        'flosc_default_friendly_ivr',
        'flosc_default_technical_ivr',
        'flosc_default_br3nda_emotional_support_ivr',
    );
    return in_array( $stem, $samples, true );
}

/**
 * Seed library id for a shipped personality-sample IVR, if any.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return string
 */
function flosc_shipped_personality_sample_library_id( $ivr_filename_or_stem ) {
    $stem = sanitize_key( pathinfo( basename( (string) $ivr_filename_or_stem ), PATHINFO_FILENAME ) );
    $map  = array(
        'flosc_default_ivr'            => 'starter',
        'flosc_default_friendly_ivr'   => 'friendly',
        'flosc_default_technical_ivr'  => 'tech',
    );
    return $map[ $stem ] ?? '';
}

/**
 * Library personality implied by a shipped sample IVR, if any. Real flows
 * carry their attachment in the per-flow settings bag — never hardcode
 * flow→personality pairs here.
 *
 * @param string $ivr_filename_or_stem Filename or stem.
 * @return string Library id or empty.
 */
function flosc_implied_personality_library_id( $ivr_filename_or_stem ) {
    $id = flosc_shipped_personality_sample_library_id( $ivr_filename_or_stem );
    return sanitize_key( (string) apply_filters( 'flosc_implied_personality_library_id', $id, $ivr_filename_or_stem ) );
}

/**
 * Switch Flow lists journeys. Drop personality-sample IVRs when real flows exist.
 *
 * @param array<int,string> $files IVR filenames.
 * @param string            $keep  Filename to keep even if it is a sample (currently selected).
 * @return array<int,string>
 */
function flosc_filter_switch_flow_ivr_files( $files, $keep = '' ) {
    if ( ! is_array( $files ) ) {
        return array();
    }
    $keep    = basename( (string) $keep );
    $real    = array();
    $samples = array();
    foreach ( $files as $file ) {
        $file = basename( (string) $file );
        if ( $file === '' ) {
            continue;
        }
        if ( function_exists( 'flosc_is_shipped_personality_sample_ivr' ) && flosc_is_shipped_personality_sample_ivr( $file ) ) {
            $samples[] = $file;
        } else {
            $real[] = $file;
        }
    }
    if ( $real === array() ) {
        return array_values( array_unique( $files ) );
    }
    if ( $keep !== '' && in_array( $keep, $samples, true ) && ! in_array( $keep, $real, true ) ) {
        $real[] = $keep;
    }
    return array_values( array_unique( $real ) );
}

require_once FLOSC_PLUGIN_DIR . 'includes/content-item/flosc-content-item-keys.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-rest.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-admin.php';
require_once FLOSC_PLUGIN_DIR . 'admin/settings-helper.php';
require_once FLOSC_PLUGIN_DIR . 'includes/tokens/class-flosc-visitor-token-trait.php';
require_once FLOSC_PLUGIN_DIR . 'includes/magic-link/class-flosc-magic-link-trait.php';
// FLOSC_Filesystem already required above (before flosc-data-paths.php).
require_once FLOSC_PLUGIN_DIR . 'includes/request-guard/class-flosc-request-guard.php';
require_once FLOSC_PLUGIN_DIR . 'includes/da1/class-flosc-da1-compositions.php';
require_once FLOSC_PLUGIN_DIR . 'includes/chat-turn/trait-flosc-chat-turn.php';
require_once FLOSC_PLUGIN_DIR . 'includes/companion-mode/class-flosc-companion-mode.php';
require_once FLOSC_PLUGIN_DIR . 'includes/full-page-mode/class-flosc-full-page-mode.php';
require_once FLOSC_PLUGIN_DIR . 'includes/first-party-authentication/class-flosc-first-party-authentication.php';
require_once FLOSC_PLUGIN_DIR . 'includes/email/flosc-guest-followup-slots.php';
require_once FLOSC_PLUGIN_DIR . 'includes/email/class-flosc-email.php';
require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-flosc-checkout-rest.php';
require_once FLOSC_PLUGIN_DIR . 'includes/tokens/class-flosc-token-ledger.php';
require_once FLOSC_PLUGIN_DIR . 'includes/sessions/class-flosc-session-rest.php';
// WordPress.org package: define('FLOSC_ENABLE_MAGIC_ACCESS_LINKS', false) in wp-config.php
// (or filter flosc_enable_magic_access_links) to shelve guest MagicLink without deleting code.


/**
 * Main FLOSC Framework Class
 */
class FLOSC_Framework {
    use FLOSC_REST_Trait;
    use FLOSC_Admin_Trait;
    use FLOSC_Visitor_Token_Trait;
    use FLOSC_Magic_Link_Trait;
    use FLOSC_Chat_Turn_Trait;
    
    private static $instance = null;
    
    // Core components
    private $filesystem;
    private $request_guard;
    private $da1_compositions;
    private $companion_mode;
    private $full_page_mode;
    private $first_party_auth;
    private $email_service;
    private $checkout_rest;
    private $token_ledger;
    private $session_rest;
    private $ai_chat_dispatch;
    private $stt_dispatch;
    private $quiz_factory;
    private $session_manager;
    private $pronunciation_analyzer;

    // SALE system (loaded separately)
    private $sale_manager;
    
    // v1.7.5: Explicit flow context for REST API calls (domain-independent)
    private $forced_flow = null;
    
    // RAG system (v9.1.6)
    private $user_access_manager;
    private $content_filter;
    private $rag_manager;

    // v9.1.8 systems
    private $free_lesson_manager;
    private $member_access;

    // SSO system (v1.4.0)
    private $sso_manager;

    // Lesson manager
    private $lesson_manager;

    // v3.0.0: Flag set when FLOSC auth token authenticated the user
    // Used by allow_flosc_token_auth() to bypass WordPress nonce check
    private $flosc_token_auth_used = false;

    // v8.0.4: Fallback temp_id from registration request body.
    // Set by handle_email_registration() before wp_login fires, so
    // handle_user_login() can score visitor audio even when the
    // flosc_visitor_temp_id signed cookie didn't round-trip (cross-domain).
    private $_pending_audio_temp_id = '';

    public static function instance() {
        if (null === self::$instance) {
            // Assign instance BEFORE constructor work so flosc_get_setting()
            // can call instance() without infinite recursion
            self::$instance = new self();
            self::$instance->boot();
        }
        return self::$instance;
    }

    private function __construct() {
        // Intentionally empty — boot() runs after self::$instance is assigned
    }

    /** @return WP_Filesystem_Base|null */
    private function get_wp_filesystem() {
        return $this->filesystem->get_wp_filesystem();
    }

    private function move_file_safely($source, $destination) {
        return $this->filesystem->move_file_safely($source, $destination);
    }

    private function delete_file_safely($path) {
        return $this->filesystem->delete_file_safely($path);
    }

    private function delete_directory_safely($path) {
        return $this->filesystem->delete_directory_safely($path);
    }

    private function write_file_safely($path, $content) {
        return $this->filesystem->write_file_safely($path, $content);
    }

    private function write_json_atomic($path, $data) {
        return $this->filesystem->write_json_atomic($path, $data);
    }

    public function flosc_block_pending_email_login($user, $password) {
        return $this->first_party_auth->flosc_block_pending_email_login($user, $password);
    }

    public function handle_user_registration($user_id) {
        return $this->first_party_auth->handle_user_registration($user_id);
    }

    public function handle_user_login($user_login, $user) {
        return $this->first_party_auth->handle_user_login($user_login, $user);
    }

    public function handle_login_redirect($redirect_to, $requested_redirect_to, $user) {
        return $this->first_party_auth->handle_login_redirect($redirect_to, $requested_redirect_to, $user);
    }

    public function handle_woocommerce_login_redirect($redirect, $user) {
        return $this->first_party_auth->handle_woocommerce_login_redirect($redirect, $user);
    }

    public function takeover_wp_auth_url($url, $redirect = '', $force_reauth = false) {
        return $this->first_party_auth->takeover_wp_auth_url($url, $redirect, $force_reauth);
    }

    public function take_over_wp_registration_url($url) {
        return $this->first_party_auth->takeover_wp_auth_url($url);
    }

    public function takeover_wp_logout_url($logout_url, $redirect = '') {
        return $this->first_party_auth->takeover_wp_logout_url($logout_url, $redirect);
    }

    public function get_logout_redirect_url() {
        return $this->first_party_auth->get_logout_redirect_url();
    }

    public function generate_flosc_auth_token($user_id, $ttl = DAY_IN_SECONDS) {
        return $this->first_party_auth->generate_flosc_auth_token($user_id, $ttl);
    }

    public function validate_flosc_auth_token($token) {
        return $this->first_party_auth->validate_flosc_auth_token($token);
    }

    public function set_flosc_auth_cookie($token, $ttl = DAY_IN_SECONDS) {
        return $this->first_party_auth->set_flosc_auth_cookie($token, $ttl);
    }

    public function authenticate_flosc_token($user_id) {
        return $this->first_party_auth->authenticate_flosc_token($user_id);
    }

    public function ajax_logout() {
        return $this->first_party_auth->ajax_logout();
    }

    public function clear_flosc_auth_token() {
        return $this->first_party_auth->clear_flosc_auth_token();
    }

    public function set_entry_flow_cookie($flow_id) {
        return $this->first_party_auth->set_entry_flow_cookie($flow_id);
    }

    public function allow_flosc_token_auth($result) {
        return $this->first_party_auth->allow_flosc_token_auth($result);
    }

    /**
     * Collaborator API: first-party login path sends score email via email service.
     */
    public function send_score_email($user, $score_data) {
        return $this->email_service->send_score_email($user, $score_data);
    }

    private function get_guest_email_context($flow_id = '', $user_id = 0) {
        return $this->email_service->get_guest_email_context($flow_id, $user_id);
    }

    private function replace_guest_email_placeholders($text, $user, $days_remaining) {
        return $this->email_service->replace_guest_email_placeholders($text, $user, $days_remaining);
    }

    private function get_flosc_mail_identity($flow_id = '', $user_id = 0) {
        return $this->email_service->get_flosc_mail_identity($flow_id, $user_id);
    }

    private function get_flosc_mail_headers($flow_id = '', $user_id = 0, $is_html = false) {
        return $this->email_service->get_flosc_mail_headers($flow_id, $user_id, $is_html);
    }

    private function get_flosc_reply_to_header($flow_id = '', $user_id = 0) {
        return $this->email_service->get_flosc_reply_to_header($flow_id, $user_id);
    }

    public function send_sso_welcome_email($user_id, $provider_id, $user_data = []) {
        return $this->email_service->send_sso_welcome_email($user_id, $provider_id, $user_data);
    }

    private function send_due_guest_followups_for_user($user_id) {
        return $this->email_service->send_due_guest_followups_for_user($user_id);
    }

    private function send_email_throttled($to, $subject, $body, $headers) {
        return $this->email_service->send_email_throttled($to, $subject, $body, $headers);
    }

    private function flosc_email_html_card($context, $user, $body_text, $button_url = '', $button_label = '') {
        return $this->email_service->flosc_email_html_card($context, $user, $body_text, $button_url, $button_label);
    }

    public function dispatch_member_welcome_email($user_id, $purchase_data = []) {
        return $this->email_service->dispatch_member_welcome_email($user_id, $purchase_data);
    }

    public function dispatch_newsletter_welcome_email($user_id, $flow_id = '') {
        return $this->email_service->dispatch_newsletter_welcome_email($user_id, $flow_id);
    }

    public function subscribe_to_newsletter($user_id, $flow_id = '') {
        return $this->email_service->subscribe_to_newsletter($user_id, $flow_id);
    }

    public function render_newsletter_profile_field($user) {
        return $this->email_service->render_newsletter_profile_field($user);
    }

    public function save_newsletter_profile_field($user_id) {
        return $this->email_service->save_newsletter_profile_field($user_id);
    }

    private function send_due_series_followups($user, $prefix, $anchor_ts, $sent_meta_key, $flow_id) {
        return $this->email_service->send_due_series_followups($user, $prefix, $anchor_ts, $sent_meta_key, $flow_id);
    }

    public function run_guest_followup_emails() {
        return $this->email_service->run_guest_followup_emails();
    }

    private function get_default_email_template() {
        return $this->email_service->get_default_email_template();
    }

    public function get_offers($request) {
        return $this->checkout_rest->get_offers($request);
    }

    public function get_offer_content($request) {
        return $this->checkout_rest->get_offer_content($request);
    }

    public function handle_purchase($request) {
        return $this->checkout_rest->handle_purchase($request);
    }

    public function flosc_offer_list_price($offer) {
        return $this->checkout_rest->flosc_offer_list_price($offer);
    }

    public function flosc_apply_offer_price_coupon(array $offer, $code) {
        return $this->checkout_rest->flosc_apply_offer_price_coupon($offer, $code);
    }

    public function flosc_resolve_native_payable_amount(array $offer, $coupon_code = '') {
        return $this->checkout_rest->flosc_resolve_native_payable_amount($offer, $coupon_code);
    }

    public function flosc_offer_subscription_list_prices(array $offer) {
        return $this->checkout_rest->flosc_offer_subscription_list_prices($offer);
    }

    public function flosc_resolve_subscription_coupon_prices(array $offer, $coupon_code = '') {
        return $this->checkout_rest->flosc_resolve_subscription_coupon_prices($offer, $coupon_code);
    }

    public function flosc_offer_is_subscription(array $offer) {
        return $this->checkout_rest->flosc_offer_is_subscription($offer);
    }

    public function handle_apply_offer_coupon($request) {
        return $this->checkout_rest->handle_apply_offer_coupon($request);
    }

    public function handle_sandbox_purchase($request) {
        return $this->checkout_rest->handle_sandbox_purchase($request);
    }

    public function create_payment_intent($request) {
        return $this->checkout_rest->create_payment_intent($request);
    }

    public function complete_purchase($request) {
        return $this->checkout_rest->complete_purchase($request);
    }

    public function handle_checkout_binding($request) {
        return $this->checkout_rest->handle_checkout_binding($request);
    }

    /**
     * PayPal: prepare subscription purchase intent (server-side bind before JS SDK).
     *
     * Industry standard: mint offer/plan/amount/currency on the server, return UUID
     * for PayPal custom_id; activate loads that intent and ignores client offer swaps.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function paypal_prepare_subscription($request) {
        $offer_id  = sanitize_text_field((string) $request->get_param('offer_id'));
        $plan_type = sanitize_key((string) $request->get_param('plan_type'));
        $flow_id   = sanitize_key((string) ($request->get_param('flow_id') ?? ''));
        $session_id = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));

        if ($offer_id === '') {
            return new WP_Error('missing_offer', __('Offer id is required', 'flosc'), ['status' => 400]);
        }
        if ($plan_type !== 'monthly' && $plan_type !== 'yearly') {
            return new WP_Error('invalid_plan_type', __('plan_type must be monthly or yearly', 'flosc'), ['status' => 400]);
        }

        if ($flow_id !== '') {
            $this->set_flow_context($flow_id);
        }

        $paypal = $this->sale_manager->get_provider('paypal');
        if (!$paypal || !$paypal->is_configured()) {
            return new WP_Error('paypal_not_configured', __('PayPal is not configured', 'flosc'), ['status' => 503]);
        }

        $offer = $this->sale_manager->offers()->get_offer($offer_id, $flow_id ?: null);
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        if (method_exists($this->sale_manager, 'validate_offer_for_purchase')) {
            $ok = $this->sale_manager->validate_offer_for_purchase($offer, 'paid');
            if (is_wp_error($ok)) {
                return $ok;
            }
        }

        // Resolve exact PayPal plan id (site catalog + optional offer overrides).
        $plans = [];
        if (method_exists($paypal, 'get_stored_plans')) {
            $plans = $paypal->get_stored_plans();
        }
        if (!is_array($plans) || empty($plans['monthly_plan_id']) || empty($plans['yearly_plan_id'])) {
            $plans = get_option('flosc_paypal_plans', []);
        }
        if (!is_array($plans)) {
            $plans = [];
        }

        $offer_monthly = sanitize_text_field((string) ($offer['pricing']['paypal']['monthly_plan_id'] ?? $offer['paypal_monthly_plan_id'] ?? ''));
        $offer_yearly  = sanitize_text_field((string) ($offer['pricing']['paypal']['yearly_plan_id'] ?? $offer['paypal_yearly_plan_id'] ?? ''));

        if ($plan_type === 'yearly') {
            $plan_id = $offer_yearly !== '' ? $offer_yearly : sanitize_text_field((string) ($plans['yearly_plan_id'] ?? ''));
        } else {
            $plan_id = $offer_monthly !== '' ? $offer_monthly : sanitize_text_field((string) ($plans['monthly_plan_id'] ?? ''));
        }
        if ($plan_id === '') {
            return new WP_Error(
                'plan_unconfigured',
                __('PayPal plan is not configured for this offer', 'flosc'),
                ['status' => 400]
            );
        }

        $monthly_amt = floatval($offer['subscription']['plans']['monthly']['price'] ?? 0);
        $yearly_amt  = floatval($offer['subscription']['plans']['yearly']['price'] ?? 0);
        if ($monthly_amt <= 0) {
            $monthly_amt = floatval($offer['pricing']['price'] ?? $offer['price'] ?? 0);
        }
        if ($yearly_amt <= 0) {
            $yearly_amt = $monthly_amt > 0 ? ($monthly_amt * 10) : 0;
        }
        $amount = number_format($plan_type === 'yearly' ? $yearly_amt : $monthly_amt, 2, '.', '');
        if ((float) $amount <= 0) {
            return new WP_Error('invalid_amount', __('Offer amount is not configured', 'flosc'), ['status' => 400]);
        }
        $currency = strtoupper(sanitize_text_field((string) ($offer['pricing']['currency'] ?? $offer['currency'] ?? 'USD'))) ?: 'USD';

        $mode = 'live';
        if (method_exists($paypal, 'get_setting')) {
            $mode = sanitize_key((string) $paypal->get_setting('mode', 'live'));
        }

        if (!function_exists('flosc_paypal_purchase_intent_create')) {
            return new WP_Error('intent_unavailable', __('Purchase intent unavailable', 'flosc'), ['status' => 500]);
        }

        $intent = flosc_paypal_purchase_intent_create([
            'offer_id'   => sanitize_text_field((string) ($offer['id'] ?? $offer_id)),
            'plan_id'    => $plan_id,
            'plan_type'  => $plan_type,
            'amount'     => $amount,
            'currency'   => $currency,
            'flow_id'    => $flow_id,
            'user_id'    => get_current_user_id(),
            'session_id' => $session_id,
            'mode'       => $mode,
        ]);
        if (is_wp_error($intent)) {
            return $intent;
        }

        return new WP_REST_Response([
            'success'        => true,
            'purchase_uuid'  => $intent['purchase_uuid'],
            'plan_id'        => $plan_id,
            'plan_type'      => $plan_type,
            'amount'         => $amount,
            'currency'       => $currency,
            'offer_id'       => $intent['offer_id'],
        ], 200);
    }

    public function handle_webhook($request) {
        return $this->checkout_rest->handle_webhook($request);
    }

    public function check_access($request) {
        return $this->checkout_rest->check_access($request);
    }

    public function flosc_parse_utc_mts_timestamp($raw) {
        return $this->checkout_rest->flosc_parse_utc_mts_timestamp($raw);
    }


    public function flosc_ensure_guest_token_baseline($user_id, $token_provider, $flow_id = '', $reason = '') {
        return $this->token_ledger->flosc_ensure_guest_token_baseline($user_id, $token_provider, $flow_id, $reason);
    }

    public function flosc_apply_product_token_credit_public($user_id, $flow_id = '', $mode = 'onetime', $context = []) {
        return $this->token_ledger->flosc_apply_product_token_credit_public($user_id, $flow_id, $mode, $context);
    }

    public function flosc_apply_subscription_token_topup_public($user_id, $flow_id = '', $plan_type = 'monthly', $context = []) {
        return $this->token_ledger->flosc_apply_subscription_token_topup_public($user_id, $flow_id, $plan_type, $context);
    }

    public function apply_member_token_grant_on_access($user_id, $purchase_data = []) {
        return $this->token_ledger->apply_member_token_grant_on_access($user_id, $purchase_data);
    }

    public function flosc_user_should_receive_guest_tokens($user_id, $flow_id = '') {
        return $this->token_ledger->flosc_user_should_receive_guest_tokens($user_id, $flow_id);
    }

    public function flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider) {
        return $this->token_ledger->flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider);
    }

    public function flosc_set_visitor_session_token_balance($flow_id, $session_id, $balance) {
        return $this->token_ledger->flosc_set_visitor_session_token_balance($flow_id, $session_id, $balance);
    }

    public function flosc_charge_visitor_session_tokens($flow_id, $session_id, $token_provider, $billing_meta = []) {
        return $this->token_ledger->flosc_charge_visitor_session_tokens($flow_id, $session_id, $token_provider, $billing_meta);
    }

    public function flosc_reserve_visitor_tokens($flow_id, $session_id, $estimated_cost, $request_id, $token_provider) {
        return $this->token_ledger->reserve_visitor_tokens($flow_id, $session_id, $estimated_cost, $request_id, $token_provider);
    }

    public function flosc_settle_visitor_reservation($reservation_id, $token_provider, $billing_meta = []) {
        return $this->token_ledger->settle_visitor_reservation($reservation_id, $token_provider, $billing_meta);
    }

    public function flosc_format_token_display($value) {
        return $this->token_ledger->flosc_format_token_display($value);
    }

    public function handle_apply_guest_token_grant($request) {
        return $this->token_ledger->handle_apply_guest_token_grant($request);
    }

    public function handle_visitor_session_balance($request) {
        return $this->token_ledger->handle_visitor_session_balance($request);
    }


    public function get_sessions($request) {
        return $this->session_rest->get_sessions($request);
    }

    public function get_single_session($request) {
        return $this->session_rest->get_single_session($request);
    }

    public function create_session($request) {
        return $this->session_rest->create_session($request);
    }

    public function get_user_state_for_session_limits($user_id, $flow_id = '') {
        return $this->session_rest->get_user_state_for_session_limits($user_id, $flow_id);
    }

    public function flosc_guest_chat_flag_enabled($key) {
        return $this->session_rest->flosc_guest_chat_flag_enabled($key);
    }

    public function delete_session($request) {
        return $this->session_rest->delete_session($request);
    }

    public function rename_session($request) {
        return $this->session_rest->rename_session($request);
    }

    public function delete_session_from_do($session_id) {
        return $this->session_rest->delete_session_from_do($session_id);
    }

    public function flosc_normalize_session_id($session_id_raw) {
        return $this->session_rest->flosc_normalize_session_id($session_id_raw);
    }

    public function flosc_concierge_session_key($session_id) {
        return $this->session_rest->flosc_concierge_session_key($session_id);
    }


    /**
     * Collaborator API: first-party login may trigger SSO email sequence.
     */
    public function maybe_run_sso_email_sequence_for_user($user_id) {
        return $this->email_service->maybe_run_sso_email_sequence_for_user($user_id);
    }

    public function maybe_process_sso_flow_email_sequence($user_id, $provider_id, $user_data) {
        return $this->email_service->maybe_process_sso_flow_email_sequence($user_id, $provider_id, $user_data);
    }




    /**
     * Canonical FLOSC UTC MTS format.
     */
    private function get_utc_mts() {
        return gmdate('Y') . 'y-' . gmdate('m') . 'm-' . gmdate('d') . 'd-UTC' . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's';
    }

    /**
     * Build signed outbound headers for provider requests.
     */
    private function build_flosc_signed_headers($payload_json) {
        $site = wp_parse_url(home_url(), PHP_URL_HOST);
        $site = is_string($site) && $site !== '' ? strtolower($site) : 'unknown';
        $mts = $this->get_utc_mts();

        $signature_base = (string) $payload_json . "\n" . $mts . "\n" . $site;

        $signature = hash_hmac('sha256', $signature_base, flosc_token_secret());

        return [
            'X-FLOSC-Site' => $site,
            'X-FLOSC-MTS' => $mts,
            'X-FLOSC-Signature' => $signature,
        ];
    }

    /**
     * Dispatch one best-effort remote conversion request for session playback copies.
     * Non-blocking by design: failures only update metadata status.
     */
    private function dispatch_remote_playback_conversion($session_id, $targets) {
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $session_id)) {
            return ['ok' => false, 'status' => 'invalid_session'];
        }

        $provider = strtolower((string) flosc_get_setting('audio_conversion_provider', 'none'));
        if ($provider !== 'external') {
            return ['ok' => false, 'status' => 'provider_none'];
        }

        $api_base = untrailingslashit((string) flosc_get_setting('ipa_api_base_url', ''));
        if ($api_base === '') {
            return ['ok' => false, 'status' => 'missing_api_base'];
        }

        $payload = [
            'session_id' => $session_id,
            'targets' => array_values($targets),
        ];
        $payload_json = wp_json_encode($payload);
        $headers = array_merge(
            ['Content-Type' => 'application/json'],
            $this->build_flosc_signed_headers($payload_json)
        );

        $response = flosc_safe_remote_request('POST', $api_base . '/convert-session-playback', [
            'headers' => $headers,
            'body' => $payload_json,
            'timeout' => 6,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'status' => 'request_error'];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'status' => 'requested'];
        }

        return ['ok' => false, 'status' => 'http_' . $code];
    }

    private function boot() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        $this->filesystem = new FLOSC_Filesystem();
        $this->request_guard = new FLOSC_Request_Guard();
        $this->da1_compositions = new FLOSC_DA1_Compositions();
        $this->companion_mode = new FLOSC_Companion_Mode($this);
        $this->full_page_mode = new FLOSC_Full_Page_Mode($this);
        $this->first_party_auth = new FLOSC_First_Party_Authentication($this);
        $this->email_service = new FLOSC_Email($this);
        $this->checkout_rest = new FLOSC_Checkout_Rest($this);
        $this->token_ledger = new FLOSC_Token_Ledger($this);
        $this->session_rest = new FLOSC_Session_Rest($this);
        // Core components
        require_once FLOSC_PLUGIN_DIR . 'includes/class-ai-chat-dispatch.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-stt-dispatch.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-registry.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/sessions/class-session-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-pronunciation-analyzer.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-lesson-manager.php';

        // IVR system (v07.08)
        require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-ivr-parser.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';

        // RAG system (v9.1.6)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-user-access-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-content-filter.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-rag-manager.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-site-content-index.php';
        if ( function_exists( 'flosc_site_content_index' ) ) {
            flosc_site_content_index(); // Register admin-post handlers for site index.
        }
        require_once FLOSC_PLUGIN_DIR . 'includes/class-access-validator.php'; // v9.1.7
        require_once FLOSC_PLUGIN_DIR . 'includes/class-free-content-item-manager.php'; // v9.1.8
        require_once FLOSC_PLUGIN_DIR . 'includes/class-member-access.php'; // v9.1.8
        require_once FLOSC_PLUGIN_DIR . 'includes/class-content-protection.php'; // v1.0.1 - visibility tiers
        require_once FLOSC_PLUGIN_DIR . 'includes/class-bridge-data-manager.php'; // v1.0.2 - quiz state tracking
        require_once FLOSC_PLUGIN_DIR . 'includes/class-quiz-manager.php'; // v1.0.2 - external quiz integration
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flow-manager.php'; // v1.2.2 - multi-flow system
        require_once FLOSC_PLUGIN_DIR . 'includes/logging/class-flosc-chat-logger.php'; // v1.9.0 - chat logging
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-concierge.php'; // v8.0.0 - concierge primers
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-trajectory.php'; // v8.0.0 - trajectory guidance (same AI engine)
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-chatpack.php'; // v1.9.2 - unified AI context builder
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-page-context.php';

        // v1.9.0 - Unified AI architecture with enforceable structure
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-user-session.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-rag-chat-handler.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-rag-access-controller.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/class-flosc-response-validator.php';

        // SALE system
        require_once FLOSC_PLUGIN_DIR . 'includes/sale/class-sale-manager.php';

        // SSO system (v1.4.0)
        require_once FLOSC_PLUGIN_DIR . 'includes/sso/class-sso-provider-base.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/sso/class-oauth2-handler.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/sso/class-user-linker.php';
        require_once FLOSC_PLUGIN_DIR . 'includes/sso/class-sso-manager.php';

        $this->ai_chat_dispatch = new FLOSC_AI_Chat_Dispatch();
        $this->stt_dispatch = new FLOSC_STT_Dispatch();
        $this->session_manager = new FLOSC_Session_Manager();
        $this->pronunciation_analyzer = new FLOSC_Pronunciation_Analyzer();
        $this->lesson_manager = FLOSC_Lesson_Manager::instance();

        // Quiz types loaded dynamically by factory

        // Initialize SALE system
        $this->sale_manager = FLOSC_Sale_Manager::instance();
        
        // Initialize RAG system (v9.1.6)
        $this->user_access_manager = FLOSC_User_Access_Manager::instance();
        $this->content_filter = FLOSC_Content_Protection::instance();
        $this->rag_manager = FLOSC_RAG_Manager::instance();
        
        // Initialize v9.1.8 systems
        $this->free_lesson_manager = FLOSC_Free_Content_Item_Manager::instance();
        $this->member_access = FLOSC_Member_Access::instance();
        
        // Initialize SSO system (v1.4.0)
        $this->sso_manager = \FLOSC\SSO\SSO_Manager::get_instance();
        $this->sso_manager->init();
    }
    
    private function init_hooks() {
        // v3.0.0: FLOSC Auth Token — cross-domain authentication
        // Priority 20 runs AFTER WordPress's default cookie auth (priority 10).
        // If cookies already authenticated the user, this is a no-op.
        // If cookies failed (cross-domain), the FLOSC token takes over.
        add_filter('determine_current_user', [$this, 'authenticate_flosc_token'], 20);

        // v3.0.0: Bypass WordPress nonce check when FLOSC token auth was used.
        // WordPress's rest_cookie_check_errors (priority 100) checks the nonce when
        // auth_cookie_malformed fires — which happens even when NO cookie is present.
        // On cross-domain, this would undo our FLOSC token auth. Priority 99 runs
        // just before and returns true to short-circuit the nonce check.
        add_filter('rest_authentication_errors', [$this, 'allow_flosc_token_auth'], 99);

        // v3.0.0: Clear FLOSC auth token on logout
        add_action('wp_logout', [$this, 'clear_flosc_auth_token']);

        // Specialty roles (the product, etc.) are created when that product is
        // imported/configured — not on every request for a generic install.

        // v8.0.0: Instant logout via AJAX — bypasses wp-login.php confirmation screen
        add_action('wp_ajax_flosc_logout',        [$this, 'ajax_logout']);
        add_action('admin_post_flosc_contact_submit', [$this, 'handle_contact_form_submit']);
        add_action('admin_post_nopriv_flosc_contact_submit', [$this, 'handle_contact_form_submit']);

        // v1.5.2: Cross-domain SSO login token — must run before anything else
        add_action('init', [$this, 'handle_login_token'], 0);

        // v1.1.9: Custom domain mapping - check early before WP routing
        add_action('init', [$this, 'handle_custom_domain'], 1);
        
        // Virtual page routing
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_app_route']);
        
        // v1.2.9: Check if we need to flush after activation (MUST run AFTER add_rewrite_rules)
        add_action('init', [$this, 'check_activation_rewrite_flush'], 99);

        // Admin - priority 5 to ensure Settings submenu is added first
        add_action('admin_menu', [$this, 'add_admin_menu'], 5);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']); // v1.0.4: TASK-006

        // v8.0.0: Relabel WP admin footer on FLOSC pages — "WordPress X.X.X | FLOSC vX.X.X"
        // instead of bare "Version X.X.X" which confused floscAdmins into thinking it was FLOSC's version.
        add_filter('update_footer', [$this, 'relabel_admin_footer'], 20);
        add_filter('admin_footer_text', [$this, 'relabel_admin_footer_left']);

        // v8.0.5: Show user audio files on WP admin user profile page
        add_action('edit_user_profile', [$this, 'render_admin_user_audio_section']);
        add_action('show_user_profile', [$this, 'render_admin_user_audio_section']);
        // Profile reminder for email-registered users who haven't set nickname/password yet
        add_action('show_user_profile', [$this, 'render_credential_setup_reminder']);
        add_filter('manage_users_columns', [$this, 'flosc_add_users_columns']);
        add_filter('manage_users_custom_column', [$this, 'flosc_render_users_custom_column'], 10, 3);
        add_action('wp_ajax_flosc_serve_user_audio', [$this, 'ajax_serve_user_audio']);
        add_action('wp_ajax_nopriv_flosc_serve_user_audio', [$this, 'ajax_serve_user_audio']);
        
        // Auto-flush permalinks when slug changes
        add_action('update_option_flosc_app_slug', [$this, 'handle_slug_change'], 10, 2);

        // v1.2.9: New flush permalinks handler with Michel timestamp
        add_action('admin_post_flosc_flush_permalinks_v129', [$this, 'handle_flush_permalinks_v129']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Assets
        // v1.9.5: Priority 9999 ensures FLOSC dequeues AFTER all theme/plugin enqueues.
        // At default priority 10, BuddyBoss/Divi/WooCommerce styles survived the dequeue
        // because they enqueued at the same priority, running after FLOSC's dequeue loop.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 9999);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_companion']);

        // Register shortcodes (v9.2.0)
        add_shortcode('flosc_visitor_only', [$this, 'shortcode_visitor_only']);
        add_shortcode('flosc_member_only', [$this, 'shortcode_member_only']);
        add_shortcode('flosc_contact_form_01', [$this, 'shortcode_contact_form_01']);
        add_shortcode('flosc-contact-form-01', [$this, 'shortcode_contact_form_01']);

        // User registration hook (for signup bonus)
        add_action('user_register', [$this, 'handle_user_registration']);

        // User login hook (for pre-login score processing)
        add_action('wp_login', [$this, 'handle_user_login'], 10, 2);

        // v8.0.0: Cron hook to clean up expired visitor audio temp dirs (>36h)
        add_action('flosc_cleanup_visitor_audio', [$this, 'cleanup_expired_visitor_audio']);
        if (!wp_next_scheduled('flosc_cleanup_visitor_audio')) {
            wp_schedule_event(time(), 'twicedaily', 'flosc_cleanup_visitor_audio');
        }

        // v8.0.0: One-time migration — ensure My Profile + Log Out in guest/member dropdown menus
        if (!get_option('flosc_menus_v800')) {
            foreach (['flosc_guest_menu_items', 'flosc_member_menu_items'] as $_menu_key) {
                $_menu = get_option($_menu_key, []);
                $_actions = array_column($_menu, 'action');
                if (!in_array('view_profile', $_actions)) array_unshift($_menu, ['label' => 'My Profile', 'action' => 'view_profile']);
                if (!in_array('logout', $_actions))       $_menu[] = ['label' => 'Log Out', 'action' => 'logout'];
                update_option($_menu_key, $_menu);
            }
            update_option('flosc_menus_v800', true);
        }

        /*
         * v8.0.0: DA1 catalogs are files, not a "default catalog" concept.
         *
         * load_rows_for_flow() used to substitute the catalog key 'default' for any
         * flow with no assignment, which meant a site could serve DA1 rows without
         * ever recording which catalog a flow uses. That substitution is gone, so
         * this writes down what it was already resolving to: every flow that has no
         * assignment yet is pinned to the catalog files currently on disk that it
         * was effectively reading. Effective behaviour is unchanged; it is simply
         * stated instead of inferred.
         */
        if (!get_option('flosc_da1_explicit_catalogs_v800')) {
            $flosc_da1_assign = get_option('flosc_da1_flow_catalogs', []);
            if (!is_array($flosc_da1_assign)) {
                $flosc_da1_assign = [];
            }

            $flosc_da1_uploads = wp_upload_dir();
            $flosc_da1_dir = trailingslashit((string) ($flosc_da1_uploads['basedir'] ?? '')) . 'flosc-catalogs';
            $flosc_da1_legacy_file = $flosc_da1_dir . '/flosc_da1_catalog_default.tsv';

            // Only pin when the file the old fallback pointed at actually exists.
            if (file_exists($flosc_da1_legacy_file) && function_exists('flosc_data_dir')) {
                $flosc_da1_flow_files = glob(trailingslashit(flosc_data_dir()) . '*.md');
                if (is_array($flosc_da1_flow_files)) {
                    foreach ($flosc_da1_flow_files as $flosc_da1_flow_path) {
                        $flosc_da1_flow_name = basename($flosc_da1_flow_path);
                        if (!isset($flosc_da1_assign[$flosc_da1_flow_name])) {
                            $flosc_da1_assign[$flosc_da1_flow_name] = ['default'];
                        }
                    }
                    update_option('flosc_da1_flow_catalogs', $flosc_da1_assign, false);
                }
            }

            update_option('flosc_da1_explicit_catalogs_v800', true);
        }

        // v8.0.0: Upgrade is the profile-bar feature button — strip purchase rows from guest/member menus.
        if (!get_option('flosc_menus_upgrade_btn_v800')) {
            foreach (['flosc_guest_menu_items', 'flosc_member_menu_items'] as $_menu_key) {
                $_menu = get_option($_menu_key, []);
                if (!is_array($_menu)) {
                    continue;
                }
                $_menu = array_values(array_filter($_menu, static function ($item) {
                    $action = is_array($item) ? (string) ($item['action'] ?? '') : '';
                    return $action !== 'open_sandbox_purchase' && strpos($action, 'show_offer') !== 0;
                }));
                update_option($_menu_key, $_menu);
            }
            // Ensure guest Upgrade button is on when we remove the menu-row fallback.
            $_pb = get_option('flosc_profile_bar', []);
            if (!is_array($_pb)) {
                $_pb = [];
            }
            if (!isset($_pb['guest']) || !is_array($_pb['guest'])) {
                $_pb['guest'] = [];
            }
            if (!array_key_exists('show_upgrade', $_pb['guest']) || empty($_pb['guest']['show_upgrade'])) {
                $_pb['guest']['show_upgrade'] = true;
                if (empty($_pb['guest']['upgrade_label'])) {
                    $_pb['guest']['upgrade_label'] = 'Upgrade';
                }
                update_option('flosc_profile_bar', $_pb);
            }
            update_option('flosc_menus_upgrade_btn_v800', true);
        }

        // v8.0.0: SSO guest email sequence — welcome on registration, day 10/20/28 follow-ups
        add_action('flosc_sso_user_created',   [$this, 'send_sso_welcome_email'], 10, 3);
        add_action('flosc_user_registered',     [$this, 'send_sso_welcome_email'], 10, 3);
        add_action('flosc_sso_login_success',  [$this, 'maybe_process_sso_flow_email_sequence'], 10, 3);
        add_action('flosc_sso_account_auto_linked', [$this, 'maybe_process_sso_flow_email_sequence'], 10, 3);
        // Task 5: Post-purchase single-use magic link for cross-domain login
        add_action('flosc_purchase_completed', [$this, 'handle_purchase_completed'], 10, 2);
        // Member welcome — fires for every purchase path (grant_member_access → flosc_member_access_granted)
        add_action('flosc_member_access_granted', [$this, 'dispatch_member_welcome_email'], 10, 2);
        // G→M additive token grant (remaining + member_token_grant), once per flow
        add_action('flosc_member_access_granted', [$this, 'apply_member_token_grant_on_access'], 15, 2);
        // Newsletter opt-in profile checkbox (optional lead-gen)
        add_action('show_user_profile', [$this, 'render_newsletter_profile_field']);
        add_action('edit_user_profile', [$this, 'render_newsletter_profile_field']);
        add_action('personal_options_update', [$this, 'save_newsletter_profile_field']);
        add_action('edit_user_profile_update', [$this, 'save_newsletter_profile_field']);
        add_action('flosc_guest_followup_cron', [$this, 'run_guest_followup_emails']);
        if (!wp_next_scheduled('flosc_guest_followup_cron')) {
            wp_schedule_event(time(), 'daily', 'flosc_guest_followup_cron');
        }

        // Login redirect - send users to FLOSC app after login (v9.5.7)
        add_filter('login_redirect', [$this, 'handle_login_redirect'], 999, 3);
        add_filter('woocommerce_login_redirect', [$this, 'handle_woocommerce_login_redirect'], 999, 2);

        add_filter('login_url', [$this, 'takeover_wp_auth_url'], 999, 3);
        add_filter('register_url', [$this, 'take_over_wp_registration_url'], 999);
        add_filter('logout_url', [$this, 'takeover_wp_logout_url'], 999, 2);

        // Admin post handler for flush permalinks (v9.5.1)
        add_action('admin_post_flosc_flush_permalinks', [$this, 'handle_flush_permalinks']);

        // Set default flow (must run before admin HTML — not inside settings.php render)
        add_action('admin_post_flosc_set_default_flow', [$this, 'handle_set_default_flow']);

        // Settings Save / trajectory / concierge POSTs redirect — must run before admin HTML
        // or wp_safe_redirect fails and exit leaves a white content pane.
        add_action('admin_init', [$this, 'maybe_process_flosc_settings_post'], 1);

        // Sidebar shortcuts (UI & Nav, Style, AI, …) must redirect before admin chrome.
        // Late menu callbacks + headers_sent + exit = blank main content area.
        add_action('admin_init', [$this, 'maybe_redirect_flosc_admin_shortcuts'], 1);

        // Fix 6: Lesson catalog auto-regeneration on post save + manual admin-post handler
        add_action('save_post', [$this, 'maybe_regenerate_lesson_catalog'], 20, 2);
        add_action('admin_post_flosc_regenerate_lesson_catalog', [$this, 'handle_regenerate_lesson_catalog']);

        // Fix 15: KB file operation handlers (upload, delete, toggle, save edit)
        add_action('admin_post_flosc_kb_upload',    [$this, 'handle_kb_upload']);
        add_action('admin_post_flosc_kb_delete',    [$this, 'handle_kb_delete']);
        add_action('admin_post_flosc_kb_toggle',    [$this, 'handle_kb_toggle']);
        add_action('admin_post_flosc_kb_save_edit', [$this, 'handle_kb_save_edit']);
        add_action('admin_post_flosc_kb_create',    [$this, 'handle_kb_create']);

        // Fix 14: Provider Accuracy Test AJAX
        add_action('wp_ajax_flosc_accuracy_test_message', [$this, 'ajax_accuracy_test_message']);

        // Category protection AJAX (v1.0.1)
        add_action('wp_ajax_flosc_protect_category', [$this, 'ajax_protect_category']);
        add_action('wp_ajax_flosc_unprotect_category', [$this, 'ajax_unprotect_category']);

        // v1.5.0: SSO connection test AJAX (inline diagnostics — no popups)
        add_action('wp_ajax_flosc_test_sso_connection', [$this, 'ajax_test_sso_connection']);

        // v1.9.0: AI connection test AJAX
        add_action('wp_ajax_flosc_test_ai_connection', [$this, 'ajax_test_ai_connection']);

        // Admin: send Guest Access Link to any email (Register & Login tab)
        add_action('wp_ajax_flosc_send_guest_link', [$this, 'ajax_send_guest_link']);
        add_action('admin_post_flosc_guest_request_approve', [$this, 'handle_guest_request_approve']);
        add_action('admin_post_flosc_guest_request_approve_send', [$this, 'handle_guest_request_approve_send']);
        add_action('admin_post_flosc_guest_request_deny_block', [$this, 'handle_guest_request_deny_block']);
        add_action('admin_post_flosc_guest_request_delete', [$this, 'handle_guest_request_delete']);
        add_action('show_user_profile', [$this, 'render_email_account_status_profile']);
        add_action('edit_user_profile', [$this, 'render_email_account_status_profile']);
        add_action('admin_post_flosc_activate_email_account', [$this, 'handle_admin_activate_email_account']);
        add_filter('wp_authenticate_user', [$this, 'flosc_block_pending_email_login'], 10, 2);


        // v1.9.0: Chat logs AJAX (real-time polling)
        add_action('wp_ajax_flosc_get_chat_logs', [$this, 'ajax_flosc_get_chat_logs']);
        add_action('wp_ajax_flosc_clear_chat_logs', [$this, 'ajax_flosc_clear_chat_logs']);

        // v1.9.5: Rate a chat log entry (-10 to +10)
        add_action('wp_ajax_flosc_rate_log', [$this, 'ajax_flosc_rate_log']);
        add_action('wp_ajax_flosc_delete_chat_session', [$this, 'ajax_flosc_delete_chat_session']);
        add_action('wp_ajax_flosc_manage_chat_sessions', [$this, 'ajax_flosc_manage_chat_sessions']);
        // v8.0.0: Admin joins a conversation (posts a human, pale-green "(admin)" message)
        add_action('wp_ajax_flosc_admin_join', [$this, 'ajax_flosc_admin_join']);
        add_action('wp_ajax_flosc_admin_assign_tokens', [$this, 'ajax_flosc_admin_assign_tokens']);
        add_action('admin_post_flosc_download_chat_tsv', [$this, 'handle_download_chat_tsv']);

        // v8.0.0: PayPal connection test AJAX
        add_action('wp_ajax_flosc_test_paypal', [$this, 'ajax_test_paypal']);

        // v1.4.3: Post visibility meta box
        add_action('add_meta_boxes', [$this, 'flosc_add_post_visibility_meta_box']);
        add_action('save_post', [$this, 'flosc_save_post_visibility_meta'], 10, 2);

        // Third-party quiz plugin integrations (v9.3.4)
        $this->init_quiz_plugin_hooks();

        // v8.0.0: BuddyBoss/BuddyPress "Quiz Results" profile tab
        add_action('bp_setup_nav', [$this, 'setup_buddyboss_quiz_tab'], 100);
    }
    
    /**
     * Initialize third-party quiz plugin hooks (v9.3.4)
     * 
     * Captures quiz completion from external plugins and feeds them
     * into the FLOSC funnel system. Each integration is opt-in via admin.
     */
    private function init_quiz_plugin_hooks() {
        // Wp-Pro-Quiz Integration
        if (get_option('flosc_wpq_integration', 0) && class_exists('WpProQuiz_Controller_Quiz')) {
            add_action('wp_pro_quiz_completed_quiz', function($quiz_id, $score, $user_id) {
                $this->capture_external_quiz_score([
                    'source'    => 'wp_pro_quiz',
                    'quiz_id'   => $quiz_id,
                    'score'     => $score,
                    'user_id'   => $user_id,
                    'timestamp' => time()
                ]);
            }, 10, 3);
        }
        
        // LearnDash Integration
        if (get_option('flosc_ld_integration', 0) && defined('LEARNDASH_VERSION')) {
            add_action('learndash_quiz_completed', function($data, $user) {
                $this->capture_external_quiz_score([
                    'source'    => 'learndash',
                    'quiz_id'   => $data['quiz'] ?? 0,
                    'score'     => $data['percentage'] ?? 0,
                    'user_id'   => $user->ID,
                    'timestamp' => time()
                ]);
            }, 10, 2);
        }
        
        // Quiz & Survey Master Integration
        if (get_option('flosc_qsm_integration', 0) && (class_exists('QSM_Quiz') || function_exists('qsm_register_quiz_setting'))) {
            add_action('qsm_quiz_submitted', function($results, $quiz_id) {
                $total = $results['total_questions'] ?? 1;
                $correct = $results['total_correct'] ?? 0;
                $this->capture_external_quiz_score([
                    'source'    => 'qsm',
                    'quiz_id'   => $quiz_id,
                    'score'     => ($total > 0) ? round(($correct / $total) * 100) : 0,
                    'user_id'   => get_current_user_id(),
                    'timestamp' => time()
                ]);
            }, 10, 2);
        }
    }
    
    /**
     * Capture quiz scores from external plugins (v9.3.4)
     * 
     * Unified handler for scores from Wp-Pro-Quiz, LearnDash, QSM, etc.
     * Stores score for funnel progression regardless of login state.
     * 
     * v9.4.2: Uses signed cookies to prevent score forgery
     * 
     * @param array $data Score data with source, quiz_id, score, user_id, timestamp
     */
    public function capture_external_quiz_score($data) {
        $user_id = $data['user_id'] ?? get_current_user_id();
        
        // Format for FLOSC
        $score_data = [
            'score'     => intval($data['score']),
            'correct'   => [],
            'incorrect' => [],
            'quiz_type' => 'external_' . ($data['source'] ?? 'unknown'),
            'external_quiz_id' => $data['quiz_id'] ?? null,
            'timestamp' => $data['timestamp'] ?? time(),
        ];
        
        if (!$user_id) {
            // Visitor: store in signed cookie for login gate to pick up
            // v9.4.2: Cookie is now signed to prevent score forgery
            $this->set_signed_cookie('flosc_prelogin_score', $score_data, HOUR_IN_SECONDS);
            return;
        }
        
        // Logged-in user: store in user meta
        update_user_meta($user_id, '_flosc_last_quiz_score', $score_data['score']);
        update_user_meta($user_id, '_flosc_last_quiz_data', $score_data);
        update_user_meta($user_id, '_flosc_quiz_completed_at', current_time('mysql'));
        
        // Trigger FLOSC phase transition
        do_action('flosc_quiz_completed', $score_data, $user_id);
    }
    
    /**
     * Component accessors
     */
    public function ai() { return $this->ai_chat_dispatch; }
    public function stt() { return $this->stt_dispatch; }
    public function quiz() { return 'FLOSC_Quiz_Registry'; }
    public function sessions() { return $this->session_manager; }
    public function analyzer() { return $this->pronunciation_analyzer; }
    public function sale() { return $this->sale_manager; }
    public function lessons() { return $this->lesson_manager; }
    public function member_access() { return $this->member_access; }


    private function get_client_ip() {
        return $this->request_guard->get_client_ip();
    }

    private function check_rate_limit($endpoint, $limit = 20, $window = 3600) {
        return $this->request_guard->check_rate_limit($endpoint, $limit, $window);
    }

    private function sign_cookie_data($data) {
        return $this->request_guard->sign_cookie_data($data);
    }

    private function verify_signed_cookie($cookie_value) {
        return $this->request_guard->verify_signed_cookie($cookie_value);
    }

    private function set_signed_cookie($name, $data, $expiry = 0) {
        return $this->request_guard->set_signed_cookie($name, $data, $expiry);
    }

    /**
     * Collaborator API (Pass 1): signed cookie read for extracted auth services.
     * Narrow public delegate over request-guard; not a broad private→public sweep.
     *
     * @param string $name Cookie name.
     * @return mixed|null
     */
    public function get_signed_cookie($name) {
        return $this->request_guard->get_signed_cookie($name);
    }


    /**
     * Plugin Activation
     */
    // v9.1.1: Activation logic moved to global flosc_activate() function (line ~2285)
    // to avoid duplication. WordPress requires activation hook to point to a function,
    // not a class method, so the global function is the single source of truth.

    /**
     * Create default "works out of box" content
     */
    private function create_default_content() {
        // Set default messages
        $default_messages = [
            'flosc_welcome_message' => 'Default FLOSC Welcome Message: Hey, welcome to your FLOSC training! Here you\'ll discover exactly where you can improve. Ready to take a quick 30-second quiz to get started?',
            'flosc_get_started_message' => 'Default FLOSC Get Started Message: Great! The best way to begin is with our free quiz. It takes just 30 seconds and shows you exactly where you can improve. Would you like to try it?',
            'flosc_how_it_works_message' => 'Default FLOSC How It Works Message: Here\'s how FLOSC works: 1) Take a quick quiz (30 seconds), 2) Get your personalized score, 3) Receive a free lesson for your biggest challenge, 4) Upgrade to unlock full access to all content.',
            'flosc_what_you_learn_message' => 'Default FLOSC What You Learn Message: You\'ll learn practical skills tailored to your specific needs. Our quiz identifies your strengths and weaknesses, then delivers targeted lessons to help you improve exactly where you need it most.',
            'flosc_email_subject' => 'Default FLOSC Email Subject: Your Quiz Results Are Ready!',
            'flosc_email_body' => 'Default FLOSC Email Body: Hi {user_name},

You scored {score}% on the quiz!

We\'ve prepared a free lesson to help with the areas where you can improve most.

Ready for more? {oto_offer_link}

Best regards,
The Team',
        ];

        foreach ($default_messages as $key => $value) {
            if (!get_option($key)) {
                add_option($key, $value);
            }
        }

        // Create default quiz
        $quiz_config = [
            'id' => 'default-flosc-quiz',
            'name' => 'Default FLOSC Quick Assessment',
            'type' => 'flosc_sample_data_numbers_quiz',
            'items' => '1,2,3,4,5,6,7,8,9,10',
            'passing_score' => 70,
        ];
        update_option('flosc_quiz_config', $quiz_config);

        // Create "Default FLOSC Lessons" category
        $cat_id = wp_create_category('Default FLOSC Lessons');
        if ($cat_id && !is_wp_error($cat_id)) {
            update_option('flosc_content_item_category', $cat_id);

            // Auto-protect the category (hide from public by default)
            update_term_meta($cat_id, '_flosc_protected', 'yes');

            // Create 10 default lesson posts
            for ($i = 1; $i <= 10; $i++) {
                $post_id = wp_insert_post([
                    'post_title' => "Default FLOSC Lesson $i: Sample Training Topic",
                    'post_content' => "Default FLOSC Lesson Content: This is a sample lesson for quiz item $i. Replace this with your actual training content.\n\nThis lesson addresses the skills tested in item $i of the quiz.",
                    'post_status' => 'publish',
                    'post_type' => 'post',
                    'post_category' => [$cat_id],
                    'tags_input' => ["$i", "lesson-$i", "phoneme-$i"],
                ]);
            }
        }

        // Create default offer
        $offer_manager = $this->sale_manager->offers();
        $offer_manager->create_offer([
            'id' => 'default-flosc-full-access',
            'name' => 'Default FLOSC Full Access',
            'description' => 'Default FLOSC Offer: Unlock all lessons and premium features',
            'type' => 'one_time',
            'status' => 'active',
            'display_price' => '$97',
            'pricing' => [
                'stripe' => [
                    'price_id' => '', // Admin must configure
                ],
                'tokens' => [
                    'cost' => 1000,
                ],
                'affiliate' => [
                    'credit_amount' => 97.00,
                ],
            ],
            'grants' => [
                'features' => ['all_lessons', 'ai_coach', 'certificates'],
                'duration_days' => 0, // Lifetime
            ],
            'meta' => [
                'icon' => '⭐',
                'badge' => 'Best Value',
            ],
            'sort_order' => 1,
        ]);

        // Set as default OTO
        update_option('flosc_default_oto_offer', 'default-flosc-full-access');

        // Mark as created
        update_option('flosc_default_content_created', true);
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    

    








    /**
     * Check if this is the user's first known entry for the given flow.
     * Collaborator API (Pass 1): used by FLOSC_Email.
     */
    public function is_user_new_to_flow($user_id, $flow_id) {
        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        if ($flow_stem === '') {
            // Fallback path: if flow context is missing, allow one welcome send per user.
            $sent_without_flow = (int) get_user_meta((int) $user_id, '_flosc_sso_welcome_email_sent', true);
            return ($sent_without_flow <= 0);
        }

        $counts = get_user_meta((int) $user_id, '_flosc_flow_use_counts', true);
        if (!is_array($counts)) {
            $counts = [];
        }

        return empty($counts[$flow_stem]);
    }

    /**
     * Track first/latest flow attribution and per-flow usage counts for each user.
     * Collaborator API (Pass 1): used by FLOSC_Email.
     */
    public function record_user_flow_usage($user_id, $flow_id, $method = 'chat') {
        $user_id = intval($user_id);
        if ($user_id <= 0) {
            return;
        }

        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        if ($flow_stem === '') {
            return;
        }

        $flow_host = '';
        $flow_settings = $this->resolve_flow_settings_by_stem($flow_stem);
        if (is_array($flow_settings) && !empty($flow_settings['domain'])) {
            $flow_host = strtolower(trim((string) $flow_settings['domain']));
        }
        if ($flow_host === '') {
            $flow_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        }

        $now_mysql = current_time('mysql');
        $method = sanitize_key((string) $method);

        $counts = get_user_meta($user_id, '_flosc_flow_use_counts', true);
        if (!is_array($counts)) {
            $counts = [];
        }
        $counts[$flow_stem] = intval($counts[$flow_stem] ?? 0) + 1;
        update_user_meta($user_id, '_flosc_flow_use_counts', $counts);

        $first_seen = get_user_meta($user_id, '_flosc_flow_first_seen', true);
        if (!is_array($first_seen)) {
            $first_seen = [];
        }
        if (empty($first_seen[$flow_stem])) {
            $first_seen[$flow_stem] = $now_mysql;
            update_user_meta($user_id, '_flosc_flow_first_seen', $first_seen);
        }

        if (!get_user_meta($user_id, '_flosc_first_flow', true)) {
            update_user_meta($user_id, '_flosc_first_flow', $flow_stem);
            update_user_meta($user_id, '_flosc_first_flow_at', $now_mysql);
            update_user_meta($user_id, '_flosc_first_source_host', $flow_host);
            update_user_meta($user_id, '_flosc_first_source_method', $method);
        }

        update_user_meta($user_id, '_flosc_last_flow', $flow_stem);
        update_user_meta($user_id, '_flosc_last_flow_at', $now_mysql);
        update_user_meta($user_id, '_flosc_source_latest_host', $flow_host);
        update_user_meta($user_id, '_flosc_source_latest_method', $method);
    }

    /**
     * Resolve flow settings while tolerating legacy key variants.
     */
    private function resolve_flow_settings_by_stem($flow_stem) {
        $flow_stem = sanitize_key((string) $flow_stem);
        if ($flow_stem === '') {
            return [];
        }

        $candidates = [
            'flosc_flow_' . $flow_stem,
            'flosc_flow_' . $flow_stem . '_ivr',
        ];

        foreach ($candidates as $key) {
            $settings = get_option($key, []);
            if (is_array($settings) && !empty($settings)) {
                return $settings;
            }
        }

        return [];
    }

    /**
     * Users list: add FLOSC attribution columns.
     */
    public function flosc_add_users_columns($columns) {
        $with_flosc = [];
        foreach ($columns as $key => $label) {
            $with_flosc[$key] = $label;
            if ($key === 'email') {
                $with_flosc['flosc_source'] = 'FLOSC Source';
                $with_flosc['flosc_flows'] = 'Flow Use';
            }
        }

        if (!isset($with_flosc['flosc_source'])) {
            $with_flosc['flosc_source'] = 'FLOSC Source';
            $with_flosc['flosc_flows'] = 'Flow Use';
        }

        return $with_flosc;
    }

    /**
     * Users list: render FLOSC attribution cells.
     */
    public function flosc_render_users_custom_column($value, $column_name, $user_id) {
        if ($column_name === 'flosc_source') {
            $host = (string) get_user_meta($user_id, '_flosc_source_latest_host', true);
            $flow = (string) get_user_meta($user_id, '_flosc_last_flow', true);
            $method = (string) get_user_meta($user_id, '_flosc_source_latest_method', true);
            $at = (string) get_user_meta($user_id, '_flosc_last_flow_at', true);

            if ($host === '' && $flow === '' && $method === '') {
                return '—';
            }

            $parts = [];
            if ($host !== '') {
                $parts[] = esc_html($host);
            }
            if ($flow !== '') {
                $parts[] = esc_html($flow);
            }
            if ($method !== '') {
                $parts[] = esc_html($method);
            }

            $chat_logs_url = add_query_arg([
                'page' => 'flosc-settings',
                'tab' => 'chat-logs',
                'flosc_user_id' => intval($user_id),
            ], admin_url('admin.php'));

            $time_html = $at !== '' ? '<br><small class="flosc-muted-meta">' . esc_html($at) . '</small>' : '';
            return implode(' | ', $parts) . $time_html . '<br><a href="' . esc_url($chat_logs_url) . '">View chats</a>';
        }

        if ($column_name === 'flosc_flows') {
            $counts = get_user_meta($user_id, '_flosc_flow_use_counts', true);
            if (!is_array($counts) || empty($counts)) {
                return '—';
            }

            arsort($counts);
            $rows = [];
            foreach (array_slice($counts, 0, 4, true) as $flow => $count) {
                $rows[] = esc_html($flow) . ': ' . intval($count);
            }

            $more = count($counts) > 4 ? '<br><small class="flosc-muted-meta">+' . (count($counts) - 4) . ' more</small>' : '';
            return implode('<br>', $rows) . $more;
        }

        return $value;
    }


    
    /**
     * v8.0.3: Store quiz score with quiz_id tracking for multi-quiz support
     */
    public function store_quiz_score($user_id, $score_data) {
        $score = intval($score_data['score'] ?? 0);
        $quiz_id = sanitize_key($score_data['quiz_id'] ?? 'default_quiz');

        // Preserve prior payload before overwrite so profile recovery is possible
        // even if a later write fails or stores unintended data.
        $previous_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        if (is_array($previous_data) && !empty($previous_data)) {
            update_user_meta($user_id, '_flosc_last_quiz_data_backup', $previous_data);

            $history = get_user_meta($user_id, '_flosc_quiz_data_history', true);
            if (!is_array($history)) {
                $history = [];
            }

            $history[] = [
                'archived_at' => current_time('mysql'),
                'quiz_id' => sanitize_key($previous_data['quiz_id'] ?? ''),
                'session_id' => sanitize_text_field($previous_data['session_id'] ?? ''),
                'score' => intval($previous_data['score'] ?? 0),
                'data' => $previous_data,
            ];

            // Keep latest 20 archived payloads per user.
            if (count($history) > 20) {
                $history = array_slice($history, -20);
            }

            update_user_meta($user_id, '_flosc_quiz_data_history', $history);
        }
        
        // Store most recent score
        update_user_meta($user_id, '_flosc_last_quiz_score', $score);
        update_user_meta($user_id, '_flosc_last_quiz_id', $quiz_id);
        update_user_meta($user_id, '_flosc_last_quiz_data', $score_data);
        update_user_meta($user_id, '_flosc_quiz_completed_at', current_time('mysql'));
        
        // Store initial score if this is first quiz ever
        $initial_score = get_user_meta($user_id, '_flosc_initial_score', true);
        if (empty($initial_score)) {
            update_user_meta($user_id, '_flosc_initial_score', $score);
            update_user_meta($user_id, '_flosc_initial_quiz_id', $quiz_id);
        }
        
        // Track all quiz attempts
        $attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);
        if (!is_array($attempts)) {
            $attempts = [];
        }
        
        $attempts[] = [
            'quiz_id' => $quiz_id,
            'score' => $score,
            'timestamp' => current_time('mysql'),
        ];
        
        update_user_meta($user_id, '_flosc_quiz_attempts', $attempts);
    }
    
    
    // ─────────────────────────────────────────────────────────────────
    // SSO Guest Email Sequence
    // ─────────────────────────────────────────────────────────────────

    /**
     * Helper: load flow settings for a user (by stored meta, or first IVR file).
     * Collaborator API (Pass 1): used by FLOSC_Email.
     */
    public function get_flow_settings_for_user($user_id) {
        $flow_id = get_user_meta($user_id, '_flosc_registration_flow', true);
        if (empty($flow_id)) {
            $ivr_files = flosc_flows()->get_available_ivr_files();
            $flow_id   = !empty($ivr_files) ? $ivr_files[0] : '';
        }
        if (empty($flow_id)) return [];
        $key = 'flosc_flow_' . sanitize_key(pathinfo($flow_id, PATHINFO_FILENAME));
        return get_option($key, []);
    }

    /** Per-cron-run email counter for rate limiting. */
    private $flosc_email_sent_this_run = 0;

    /**
     * Rewrite Rules for Virtual Page - v1.3.4: Register ALL IVR files with defaults
     */
    public function add_rewrite_rules() {
        // v1.3.4: Register rewrite rules for ALL IVR files (even unsaved ones)
        // §2: union shipped defaults with uploaded/edited IVR files so every flow gets rewrite rules.
        $files = flosc_config_glob(['*_ivr.md', 'ivr*.md']);
        if (!empty($files)) {
            foreach ($files as $file) {
                $filename = basename($file);
                if (strpos($filename, 'backup') !== false) continue;
                
                // Get settings for this IVR file
                $settings_key = 'flosc_flow_' . sanitize_key(pathinfo($filename, PATHINFO_FILENAME));
                $flow_settings = get_option($settings_key, []);
                
                // v1.3.5: Generate default slug preserving underscores (user-friendly for IVR filenames)
                // sanitize_title converts underscores to hyphens, but we want to keep underscores
                $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', pathinfo($filename, PATHINFO_FILENAME)));
                $slug = !empty($flow_settings['slug']) 
                    ? $flow_settings['slug'] 
                    : $default_slug;
                
                $status = $flow_settings['status'] ?? 'active';
                
                if ($status === 'active') {
                    add_rewrite_rule(
                        '^' . preg_quote($slug, '/') . '/?$',
                        'index.php?flosc_app=1&flosc_ivr=' . rawurlencode($filename),
                        'top'
                    );
                }
            }
        }
        
        // Fallback: legacy slug from settings (if no IVR flows defined yet)
        $slug = get_option('flosc_app_slug', 'flosc');
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?flosc_app=1', 'top');
    }
    
    /**
     * v1.2.9: Process pending rewrite rules flush after plugin activation
     */
    public function check_activation_rewrite_flush() {
        if (get_option('flosc_needs_flush')) {
            flush_rewrite_rules();
            delete_option('flosc_needs_flush');
        }
    }
    
    /**
     * Handle slug change - auto flush permalinks
     */
    public function handle_slug_change($old_value, $new_value) {
        if ($old_value !== $new_value) {
            flush_rewrite_rules();
            update_option('flosc_last_permalink_flush', flosc_michel_timestamp_global());
        }
    }

    /**
     * v4.0.1: Backfill missing defaults for every flow option in the DB.
     * Runs on Flush and on version upgrade so admins never need to manually
     * re-save tabs after a plugin update introduces new settings keys.
     */
    public function backfill_flow_defaults() {
        // §2: union shipped defaults with uploaded/edited IVR files (uploads wins).
        $files = flosc_config_glob( ['*_ivr.md', 'ivr*.md'] );

        foreach ( $files as $file ) {
            $basename = basename( $file );
            if ( strpos( $basename, 'backup' ) !== false ) continue;
            $option_key = 'flosc_flow_' . sanitize_key( pathinfo( $basename, PATHINFO_FILENAME ) );
            $settings   = get_option( $option_key, [] );
            $changed    = false;

            // Canonical defaults — add new keys here whenever a new setting is introduced
            // Note: enabled_quizzes intentionally omitted — admin configures which quizzes
            // are active; no default should be forced on any flow.
            $stem_slug = strtolower( preg_replace( '/[^a-z0-9_-]/i', '', pathinfo( $basename, PATHINFO_FILENAME ) ) );
            if ( $stem_slug === '' ) {
                $stem_slug = 'flosc';
            }
            $defaults = [
                'paypal_mode'   => 'sandbox',
                'stripe_mode'   => 'test',
                'slug'          => $stem_slug,
                'status'        => 'active',
                'primary_color' => '#4f46e5',
            ];

            foreach ( $defaults as $key => $default ) {
                if ( ! isset( $settings[ $key ] ) || $settings[ $key ] === '' || $settings[ $key ] === null ) {
                    $settings[ $key ] = $default;
                    $changed = true;
                }
            }
            if ( isset( $settings['status'] ) && ! in_array( $settings['status'], [ 'active', 'draft' ], true ) ) {
                $settings['status'] = 'active';
                $changed = true;
            }
            if ( $changed ) {
                update_option( $option_key, $settings );
            }
        }

        // v4.0.1: Record timestamp so the admin status bar can show "✅ FLOW Settings OK"
        update_option( 'flosc_last_flow_backfill', flosc_michel_timestamp_global() );
    }

    /**
     * Handle manual permalink flush from admin (v9.5.1)
     */
    public function handle_flush_permalinks() {
        check_admin_referer('flosc_flush_permalinks');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        flush_rewrite_rules();
        $this->backfill_flow_defaults();

        wp_safe_redirect(admin_url('admin.php?page=flosc-settings&tab=product&flushed=1'));
        exit;
    }
    
    /**
     * Handle manual permalink flush v1.2.9 with Michel timestamp
     */
    public function handle_flush_permalinks_v129() {
        check_admin_referer('flosc_flush_v129');

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        flush_rewrite_rules();
        $this->backfill_flow_defaults();
        update_option('flosc_last_permalink_flush', flosc_michel_timestamp_global());

        // Get current IVR from referer or default
        $referer = wp_get_referer();
        $ivr = '';
        if (preg_match('/ivr=([^&]+)/', $referer, $matches)) {
            $ivr = '&ivr=' . $matches[1];
        }
        $tab = '';
        if (preg_match('/tab=([^&]+)/', $referer, $matches)) {
            $tab = '&tab=' . $matches[1];
        }

        wp_safe_redirect(admin_url('admin.php?page=flosc-settings' . $ivr . $tab . '&flushed=1'));
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // Fix 6: Lesson Catalog Auto-Generation
    // ─────────────────────────────────────────────────────────

    /**
     * Hook: regenerate lesson catalog when a the product post is saved.
     * Only fires for published posts in the lessons category.
     */
    public function maybe_regenerate_lesson_catalog($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_status !== 'publish') return;
        // Regenerate only when the post is in this flow's configured lessons category
        // (instance content — not a hard-coded product brand).
        $category = '';
        if (function_exists('flosc_get_setting')) {
            $category = sanitize_title((string) flosc_get_setting('content_item_category', ''));
            if ($category === '') {
                $category = sanitize_title((string) flosc_get_setting('free_content_item_pool_category', ''));
            }
        }
        if ($category === '') {
            return;
        }
        if (!has_category($category, $post_id)) {
            $terms = get_the_terms($post_id, 'category');
            if (!$terms || is_wp_error($terms)) {
                return;
            }
            $slugs = array_column($terms, 'slug');
            if (!in_array($category, $slugs, true)) {
                return;
            }
        }
        $this->generate_lesson_catalog();
    }

    /**
     * Admin-post handler: manual "Regenerate Lesson Catalog" button.
     */
    public function handle_regenerate_lesson_catalog() {
        check_admin_referer('flosc_regen_catalog');
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        $this->generate_lesson_catalog();
        $referer = wp_get_referer() ?: admin_url('admin.php?page=flosc-settings&tab=ai');
        wp_safe_redirect(add_query_arg('catalog_regenerated', '1', $referer));
        exit;
    }

    /**
     * Generate the flow lesson catalog markdown from published posts in the
     * configured lessons category (default category slug remains product BC).
     * Writes every basename from flosc_lesson_catalog_write_paths() (neutral + legacy).
     * Auto-updates on save_post hook; also callable manually.
     */
    public function generate_lesson_catalog() {
        if (!defined('FLOSC_PLUGIN_DIR')) return;
        $write_paths = function_exists('flosc_lesson_catalog_write_paths')
            ? flosc_lesson_catalog_write_paths()
            : [];
        if (empty($write_paths)) {
            $catalog_dir = flosc_data_dir();
            if ('' === $catalog_dir) {
                return; // Uploads unavailable — regenerate on a later request instead of writing elsewhere.
            }
            $write_paths = [ $catalog_dir . 'lesson_catalog.md' ];
        }

        // Category: flow setting first, then legacy product slug so existing installs keep working.
        $category = '';
        if (function_exists('flosc_get_setting')) {
            $category = sanitize_title((string) flosc_get_setting('content_item_category', ''));
            if ($category === '') {
                $category = sanitize_title((string) flosc_get_setting('free_content_item_pool_category', ''));
            }
        }
        if ($category === '') {
            return; // No lessons category configured for this flow — nothing to generate.
        }

        // Query all published posts in the lessons category
        $args = [
            'category_name' => $category,
            'post_status'   => 'publish',
            'posts_per_page'=> -1,
            'orderby'       => 'date',
            'order'         => 'ASC',
        ];
        $posts = get_posts($args);

        // Filter to actual lesson posts only: title must start with "Lesson N:" or "Lesson N.N:"
        $lessons = array_filter($posts, function($p) {
            return (bool) preg_match('/^Lesson\s+\d+[\d.]*\s*[:\-]/i', $p->post_title);
        });

        if (empty($lessons)) {
            return; // Nothing to write — don't overwrite a valid catalog with empty content
        }

        // Sort by lesson number (handles 20.1, 20.2, 20.3 correctly)
        usort($lessons, function($a, $b) {
            preg_match('/^Lesson\s+([\d.]+)/i', $a->post_title, $ma);
            preg_match('/^Lesson\s+([\d.]+)/i', $b->post_title, $mb);
            $na = isset($ma[1]) ? (float) $ma[1] : 0;
            $nb = isset($mb[1]) ? (float) $mb[1] : 0;
            return $na <=> $nb;
        });

        $lesson_count = count($lessons);
        $date = current_time('Y-m-d');
        $product_label = function_exists('flosc_get_setting')
            ? trim((string) (flosc_get_setting('identity', [])['name'] ?? ''))
            : '';
        if ($product_label === '' && function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $product_label = trim((string) ($flow['identity']['name'] ?? $flow['name'] ?? ''));
            }
        }
        if ($product_label === '') {
            $product_label = 'Lesson';
        }

        $content  = "# {$product_label} Lesson Catalog\n\n";
        $content .= "**Auto-generated from WordPress.** This catalog is everything you have been given about\n";
        $content .= "these lessons. If a lesson is not listed here, you have not been given information about\n";
        $content .= "it — say so rather than inventing titles, numbers, or content.\n\n";
        $content .= "**Total lessons: {$lesson_count}**\n\n";
        $content .= "All lessons require membership unless explicitly marked free in a learner's profile.\n\n";
        $content .= "---\n\n";
        $content .= "| Lesson | Sound | Title | Permalink |\n";
        $content .= "|--------|-------|-------|-----------|\n";

        foreach ($lessons as $post) {
            $title     = $post->post_title;
            $permalink = get_permalink($post->ID);

            // Extract lesson number from title
            preg_match('/^Lesson\s+([\d.]+)/i', $title, $m);
            $num = isset($m[1]) ? $m[1] : '';

            // Extract IPA sound: prefer custom meta, fall back to first [...] in title
            $sound = get_post_meta($post->ID, 'sound_covered', true)
                  ?: get_post_meta($post->ID, 'ipa_sound', true);
            if (!$sound) {
                // Extract from title: text between first [ ] after "Lesson N: "
                if (preg_match('/^Lesson\s+[\d.]+[:\s]+\[([^\]]+)\]/u', $title, $sm)) {
                    $sound = $sm[1];
                }
            }

            $content .= "| {$num} | {$sound} | {$title} | {$permalink} |\n";
        }

        $content .= "\n---\n\n";
        $content .= "*Generated: {$date}. Source: WordPress category \"{$category}\", published posts only.*\n";
        $content .= "*If a lesson is not in this table, you do not have information about it — say so.*\n";

        $wrote = false;
        foreach ($write_paths as $catalog_path) {
            if (flosc_write_data_file($catalog_path, $content)) {
                $wrote = true;
            }
        }
        if ($wrote) {
            update_option('flosc_lesson_catalog_generated', current_time('mysql'));
            update_option('flosc_lesson_catalog_count', $lesson_count);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Fix 15: KB File Operation Handlers
    // ─────────────────────────────────────────────────────────

    private function kb_return_url($ivr, $action, $error = '') {
        $uid = get_current_user_id();
        if ( $uid > 0 ) {
            set_transient(
                'flosc_kb_notice_' . $uid,
                array(
                    'action' => sanitize_key( (string) $action ),
                    'error'  => $error !== '' ? sanitize_text_field( (string) $error ) : '',
                ),
                MINUTE_IN_SECONDS
            );
        }
        return admin_url( 'admin.php?page=flosc-settings&ivr=' . rawurlencode( (string) $ivr ) . '&tab=knowledge-base&view=single' );
    }

    /**
     * Resolve knowledge base id from request; fall back to this flow's stem (legacy folder).
     *
     * @param string $ivr   Current IVR filename.
     * @param string $kb_id Posted/get kb id.
     * @return string
     */
    private function kb_request_id( $ivr, $kb_id ) {
        $kb_id = sanitize_key( (string) $kb_id );
        if ( $kb_id !== '' ) {
            return $kb_id;
        }
        return sanitize_key( pathinfo( (string) $ivr, PATHINFO_FILENAME ) );
    }

    public function handle_kb_upload() {
        $post = wp_unslash($_POST);
        check_admin_referer('flosc_kb_upload', 'flosc_kb_upload_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        $ivr   = sanitize_file_name($post['flosc_return_ivr'] ?? '');
        $kb_id = $this->kb_request_id($ivr, $post['kb_id'] ?? '');
        if ($kb_id === '' || !function_exists('flosc_knowledge_base_dir')) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'No knowledge base selected.'));
            exit;
        }
        if (function_exists('flosc_knowledge_base_put') && !flosc_knowledge_base_get($kb_id)) {
            flosc_knowledge_base_put(array('id' => $kb_id, 'label' => $kb_id, 'access' => array()));
        }

        $kb_dir = flosc_knowledge_base_dir($kb_id);
        if ('' === $kb_dir) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'The FLOSC uploads storage directory is unavailable. Uploads folder permissions need attention before knowledge files can be saved.'));
            exit;
        }

        $bucket = array(
            'name'     => array(),
            'type'     => array(),
            'tmp_name' => array(),
            'error'    => array(),
            'size'     => array(),
        );
        if (isset($_FILES['orientation_file']) && is_array($_FILES['orientation_file'])) {
            $bucket['name'] = isset($_FILES['orientation_file']['name'])
                ? array_map('sanitize_file_name', (array) wp_unslash($_FILES['orientation_file']['name']))
                : array();
            $bucket['type'] = isset($_FILES['orientation_file']['type'])
                ? array_map('sanitize_mime_type', (array) wp_unslash($_FILES['orientation_file']['type']))
                : array();
            $bucket['tmp_name'] = isset($_FILES['orientation_file']['tmp_name'])
                ? array_map('sanitize_text_field', (array) wp_unslash($_FILES['orientation_file']['tmp_name']))
                : array();
            $bucket['error'] = isset($_FILES['orientation_file']['error'])
                ? array_map('absint', (array) wp_unslash($_FILES['orientation_file']['error']))
                : array();
            $bucket['size'] = isset($_FILES['orientation_file']['size'])
                ? array_map('absint', (array) wp_unslash($_FILES['orientation_file']['size']))
                : array();
        }
        $names = $bucket['name'];
        if (!is_array($names)) {
            $names = array($names);
            foreach (array('type', 'tmp_name', 'error', 'size') as $flosc_file_key) {
                if (isset($bucket[$flosc_file_key]) && !is_array($bucket[$flosc_file_key])) {
                    $bucket[$flosc_file_key] = array($bucket[$flosc_file_key]);
                }
            }
        }
        if ($names === array() || (count($names) === 1 && (string) $names[0] === '')) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'No file selected.'));
            exit;
        }

        $access = in_array($post['file_access_level'] ?? '', array('visitor', 'guest', 'member'), true)
            ? $post['file_access_level']
            : 'visitor';

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $upload_overrides = array(
            'test_form' => false,
            'mimes'     => array('md' => 'text/markdown', 'txt' => 'text/plain'),
        );

        $ok = 0;
        $err = '';
        foreach ($names as $i => $raw_name) {
            $filename = sanitize_file_name((string) $raw_name);
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($filename === '' || !in_array($ext, array('md', 'txt'), true)) {
                $err = 'Only .md and .txt files are supported.';
                continue;
            }
            $size = isset($bucket['size'][$i]) ? (int) $bucket['size'][$i] : 0;
            if ($size > 500000) {
                $err = 'File too large (max 500 KB).';
                continue;
            }
            $one = array(
                'name'     => $filename,
                'type'     => isset($bucket['type'][$i]) ? (string) $bucket['type'][$i] : '',
                'tmp_name' => isset($bucket['tmp_name'][$i]) ? (string) $bucket['tmp_name'][$i] : '',
                'error'    => isset($bucket['error'][$i]) ? (int) $bucket['error'][$i] : UPLOAD_ERR_NO_FILE,
                'size'     => $size,
            );
            $handled_upload = wp_handle_upload($one, $upload_overrides);
            if (isset($handled_upload['error'])) {
                $err = 'Upload failed: ' . $handled_upload['error'];
                continue;
            }
            $target = $kb_dir . $filename;
            $uploaded_body = (!empty($handled_upload['file']) && is_readable($handled_upload['file']))
                ? flosc_fs_get_contents($handled_upload['file'])
                : false;
            if (false === $uploaded_body || !function_exists('flosc_write_data_file') || !flosc_write_data_file($target, $uploaded_body)) {
                if (!empty($handled_upload['file'])) {
                    $this->delete_file_safely($handled_upload['file']);
                }
                $err = 'Upload failed. Check directory permissions.';
                continue;
            }
            $this->delete_file_safely($handled_upload['file']);
            if (function_exists('flosc_knowledge_base_set_file_access')) {
                flosc_knowledge_base_set_file_access($kb_id, $filename, $access);
            }
            $ok++;
        }

        if ($ok === 0) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', $err !== '' ? $err : 'Upload failed.'));
            exit;
        }
        wp_safe_redirect($this->kb_return_url($ivr, 'uploaded'));
        exit;
    }

    public function handle_kb_delete() {
        $get  = wp_unslash($_GET);
        $ivr  = sanitize_file_name($get['return_ivr'] ?? '');
        $file = sanitize_file_name($get['kb_file'] ?? '');
        $kb_id = $this->kb_request_id($ivr, $get['kb_id'] ?? '');
        check_admin_referer('flosc_kb_delete_' . $kb_id . '_' . $file);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        $kb_dir = function_exists('flosc_knowledge_base_dir') ? flosc_knowledge_base_dir($kb_id) : '';
        if ('' === $kb_dir) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'The FLOSC uploads storage directory is unavailable.'));
            exit;
        }
        $target = $kb_dir . $file;
        if (file_exists($target)) {
            $this->delete_file_safely($target);
        }
        wp_safe_redirect($this->kb_return_url($ivr, 'deleted'));
        exit;
    }

    public function handle_kb_toggle() {
        $get  = wp_unslash($_GET);
        $ivr  = sanitize_file_name($get['return_ivr'] ?? '');
        $file = sanitize_file_name($get['kb_file'] ?? '');
        $kb_id = $this->kb_request_id($ivr, $get['kb_id'] ?? '');
        check_admin_referer('flosc_kb_toggle_' . $kb_id . '_' . $file);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        $current = function_exists('flosc_knowledge_base_file_access')
            ? flosc_knowledge_base_file_access($kb_id, $file)
            : 'visitor';
        $cycle = array('visitor' => 'guest', 'guest' => 'member', 'member' => 'visitor');
        $next  = $cycle[$current] ?? 'visitor';
        if (function_exists('flosc_knowledge_base_set_file_access')) {
            flosc_knowledge_base_set_file_access($kb_id, $file, $next);
        }
        wp_safe_redirect($this->kb_return_url($ivr, 'toggled'));
        exit;
    }

    public function handle_kb_save_edit() {
        $post = wp_unslash($_POST);
        check_admin_referer('flosc_kb_save_edit', 'flosc_kb_save_edit_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }

        $ivr   = sanitize_file_name($post['flosc_return_ivr'] ?? '');
        $file  = sanitize_file_name($post['editing_file'] ?? '');
        $kb_id = $this->kb_request_id($ivr, $post['kb_id'] ?? '');
        $kb_dir = function_exists('flosc_knowledge_base_dir') ? flosc_knowledge_base_dir($kb_id) : '';
        $target = $kb_dir . $file;

        if (!$file || '' === $kb_dir || !file_exists($target)) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'File not found.'));
            exit;
        }

        $content = $post['file_content'] ?? '';
        if (!flosc_write_data_file($target, $content)) {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'The file could not be written. Uploads folder permissions need attention.'));
            exit;
        }

        wp_safe_redirect($this->kb_return_url($ivr, 'saved'));
        exit;
    }

    public function handle_kb_create() {
        $post = wp_unslash($_POST);
        check_admin_referer('flosc_kb_create', 'flosc_kb_create_nonce');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage knowledge bases.', 'flosc'));
        }
        $ivr   = sanitize_file_name($post['flosc_return_ivr'] ?? '');
        $label = sanitize_text_field((string) ($post['kb_label'] ?? ''));
        if ($label === '') {
            wp_safe_redirect($this->kb_return_url($ivr, 'error', 'Name is required.'));
            exit;
        }
        $id = sanitize_key($label);
        if ($id === '') {
            $id = 'kb_' . wp_generate_password(8, false, false);
        }
        if (function_exists('flosc_knowledge_base_get') && flosc_knowledge_base_get($id)) {
            $id = $id . '_' . wp_generate_password(4, false, false);
        }
        if (function_exists('flosc_knowledge_base_put')) {
            flosc_knowledge_base_put(array('id' => $id, 'label' => $label, 'access' => array()));
        }
        wp_safe_redirect($this->kb_return_url($ivr, 'created'));
        exit;
    }

    // ─────────────────────────────────────────────────────────
    // Fix 14: Provider Accuracy Test — AJAX handler
    // ─────────────────────────────────────────────────────────

    public function ajax_accuracy_test_message() {
        $post = wp_unslash($_POST);
        check_ajax_referer('flosc_accuracy_test', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);

        $message = sanitize_textarea_field($post['message'] ?? '');
        $msg_idx = (int) ($post['message_index'] ?? 0);
        $ivr     = sanitize_file_name($post['ivr'] ?? '');
        // Pass 8: bound + field-sanitize after json_decode of request history JSON.
        $history_raw = (string) ($post['history'] ?? '[]');
        $history = [];
        if ($history_raw !== '' && strlen($history_raw) <= 200000) {
            $decoded_history = json_decode($history_raw, true, 16);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded_history)) {
                $sanitized_history = [];
                foreach ($decoded_history as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $sanitized_history[] = [
                        'role' => sanitize_key((string) ($item['role'] ?? '')),
                        'content' => sanitize_textarea_field((string) ($item['content'] ?? '')),
                    ];
                    if (count($sanitized_history) >= 100) {
                        break;
                    }
                }
                $history = $sanitized_history;
            }
        }

        // Set flow context so flosc_get_setting reads the right flow settings (same as ajax_test_ai_connection)
        if (!empty($ivr)) {
            $this->set_flow_context(pathinfo($ivr, PATHINFO_FILENAME));
        }

        // Build chatpack using real FLOSC_Chatpack with a test eval context
        if (!class_exists('FLOSC_Chatpack') || !$this->ai_chat_dispatch) {
            wp_send_json_error(['message' => 'Chatpack or AI dispatch not available.']);
        }

        // Neutral visitor-style context — do not fake IPA/lesson quiz state (that skewed every flow toward "lessons product").
        $eval_context = [
            'access_level' => 'visitor',
            'user_name'    => 'Test User',
            'is_admin'     => true,
            'user_id'      => get_current_user_id(),
        ];

        $flosc_hash   = FLOSC_Chatpack::generate_flosc_hash();
        $session_hash = FLOSC_Chatpack::generate_session_hash($flosc_hash, get_current_user_id(), 'accuracy_test');
        $pair_num     = $msg_idx + 1;

        if ($msg_idx === 0) {
            $system_prompt = FLOSC_Chatpack::build_full_chatpack('content', $eval_context, $ivr, $flosc_hash, $session_hash, $pair_num);
        } else {
            $system_prompt = FLOSC_Chatpack::build_followup_chatpack('content', $eval_context, $session_hash, $pair_num);
        }

        // Run through AI — force fresh (no cache) by using test_mode=true
        $response = $this->ai_chat_dispatch->get_response($message, $system_prompt, $history, true);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $response_text = $response ?? '(no response)';

        // Pass/fail evaluation
        $pass = true;
        $corrected = false;

        if (stripos($response_text, 'FLOSC') !== false) {
            if (preg_match('/FLOSC\s+stands?\s+for/i', $response_text)) {
                if (!preg_match('/Freeline.*Login.*Offer.*Sale.*Content/i', $response_text)) {
                    $pass = false;
                    $corrected = true; // Validation layer should have caught this
                }
            }
        }
        wp_send_json_success([
            'response'  => $response_text,
            'tokens_in' => 0, // Token tracking requires provider-specific response parsing; placeholder
            'pass'      => $pass,
            'corrected' => $corrected,
        ]);
    }

    /**
     * AJAX: Protect a category (v1.0.1)
     */
    public function ajax_protect_category() {
        $post = wp_unslash($_POST);
        check_ajax_referer('flosc_protect_category', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $cat_id = intval($post['cat_id'] ?? 0);
        $level = sanitize_text_field($post['level'] ?? '');

        if (!$cat_id) {
            wp_send_json_error('Invalid category');
        }

        // Set term meta
        update_term_meta($cat_id, '_flosc_protected', 'yes');
        if ($level) {
            update_term_meta($cat_id, '_flosc_required_level', $level);
        } else {
            delete_term_meta($cat_id, '_flosc_required_level');
        }

        wp_send_json_success(['message' => 'Category protected']);
    }

    /**
     * AJAX: Unprotect a category (v1.0.1)
     */
    public function ajax_unprotect_category() {
        $post = wp_unslash($_POST);
        check_ajax_referer('flosc_unprotect_category', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $cat_id = intval($post['cat_id'] ?? 0);

        if (!$cat_id) {
            wp_send_json_error('Invalid category');
        }

        // Remove term meta
        delete_term_meta($cat_id, '_flosc_protected');
        delete_term_meta($cat_id, '_flosc_required_level');

        wp_send_json_success(['message' => 'Protection removed']);
    }

    /**
     * v1.5.0: AJAX handler for inline SSO connection testing
     * 
     * Performs real API calls to verify provider credentials work,
     * checks callback URL reachability, and returns structured diagnostics.
     * No popups — results display inline on the SSO settings page.
     */
    public function ajax_test_sso_connection() {
        $post = wp_unslash($_POST);
        check_ajax_referer('flosc_test_sso', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $provider_id = sanitize_text_field($post['provider'] ?? '');
        if (!in_array($provider_id, ['google', 'facebook', 'apple', 'microsoft', 'linkedin'], true)) {
            wp_send_json_error('Invalid provider');
        }

        // v1.5.0: Read SSO settings from per-flow storage
        // The flow_id is passed from the admin SSO tab (knows which flow is selected)
        $flow_id = sanitize_text_field($post['flow_id'] ?? '');
        if (!empty($flow_id)) {
            $flow_settings_key = 'flosc_flow_' . sanitize_key($flow_id);
            $flow_settings = get_option($flow_settings_key, []);
            $client_id     = $flow_settings["sso_{$provider_id}_client_id"] ?? '';
            $client_secret = $flow_settings["sso_{$provider_id}_client_secret"] ?? '';
            $is_enabled    = !empty($flow_settings["sso_{$provider_id}_enabled"]);
        } else {
            // Fallback to global (shouldn't happen with per-flow architecture)
            $client_id     = get_option("flosc_sso_{$provider_id}_client_id", '');
            $client_secret = get_option("flosc_sso_{$provider_id}_client_secret", '');
            $is_enabled    = get_option("flosc_sso_{$provider_id}_enabled", false);
        }
        $callback_url  = get_site_url() . '/wp-json/flosc/v1/sso/callback/' . $provider_id;

        $checks = [];

        // ── Check 1: Enabled ──
        $checks[] = [
            'label' => 'Provider enabled',
            'pass'  => (bool) $is_enabled,
            'detail' => $is_enabled ? 'Enabled' : 'Checkbox is OFF — enable it above and save',
        ];

        // ── Check 2: Client ID present ──
        $has_id = !empty($client_id);
        $checks[] = [
            'label' => 'Client ID',
            'pass'  => $has_id,
            'detail' => $has_id ? substr($client_id, 0, 12) . '...' : 'Missing — paste your App/Client ID above',
        ];

        // ── Check 3: Client Secret present ──
        $has_secret = !empty($client_secret);
        $checks[] = [
            'label' => 'Client Secret',
            'pass'  => $has_secret,
            'detail' => $has_secret ? '••••' . substr($client_secret, -4) : 'Missing — paste your Client Secret above',
        ];

        // ── Check 4: Callback URL reachable ──
        $cb_result = flosc_safe_remote_request('GET', $callback_url, [
            'timeout'     => 10,
            'redirection' => 0,
        ]);

        if (is_wp_error($cb_result)) {
            $checks[] = [
                'label' => 'Callback URL',
                'pass'  => false,
                'detail' => 'Unreachable: ' . $cb_result->get_error_message(),
            ];
        } else {
            $cb_code = wp_remote_retrieve_response_code($cb_result);
            // REST API will return 400 (missing code/state) or 200 — both mean reachable
            $cb_ok = ($cb_code >= 200 && $cb_code < 500);
            $checks[] = [
                'label' => 'Callback URL',
                'pass'  => $cb_ok,
                'detail' => $cb_ok
                    ? "Reachable (HTTP {$cb_code})"
                    : "Server error (HTTP {$cb_code})",
            ];
        }

        // ── Check 5: Apple-specific extra fields ──
        if ($provider_id === 'apple') {
            if (!empty($flow_id) && !empty($flow_settings)) {
                $apple_team_id = $flow_settings['sso_apple_team_id'] ?? '';
                $apple_key_id = $flow_settings['sso_apple_key_id'] ?? '';
                $apple_private_key = $flow_settings['sso_apple_private_key'] ?? '';
            } else {
                $apple_team_id = get_option('flosc_sso_apple_team_id', '');
                $apple_key_id = get_option('flosc_sso_apple_key_id', '');
                $apple_private_key = get_option('flosc_sso_apple_private_key', '');
            }
            $checks[] = [
                'label' => 'Team ID',
                'pass'  => !empty($apple_team_id),
                'detail' => !empty($apple_team_id) ? $apple_team_id : 'Missing — enter your Apple Team ID above',
            ];
            $checks[] = [
                'label' => 'Key ID',
                'pass'  => !empty($apple_key_id),
                'detail' => !empty($apple_key_id) ? $apple_key_id : 'Missing — enter your Apple Key ID above',
            ];
            $checks[] = [
                'label' => 'Private Key',
                'pass'  => !empty($apple_private_key),
                'detail' => !empty($apple_private_key) ? 'Present (' . strlen($apple_private_key) . ' chars)' : 'Missing — paste your .p8 key contents above',
            ];
        }

        // ── Check 6: Provider-specific credential verification ──
        if ($has_id && $has_secret) {
            if ($provider_id === 'facebook') {
                $checks = array_merge($checks, $this->test_facebook_credentials($client_id, $client_secret));
            } elseif ($provider_id === 'google') {
                $checks = array_merge($checks, $this->test_google_credentials($client_id, $client_secret, $callback_url));
            }
        }

        // ── Summary ──
        $all_pass = true;
        foreach ($checks as $c) {
            if (!$c['pass']) { $all_pass = false; break; }
        }

        wp_send_json_success([
            'provider' => $provider_id,
            'checks'   => $checks,
            'all_pass' => $all_pass,
            'callback_url' => $callback_url,
        ]);
    }

    /**
     * v1.5.0: Test Facebook App ID + App Secret via Graph API
     * 
     * Uses the app access token (app_id|app_secret) to call /app endpoint.
     * If valid: returns app name. If invalid: returns error.
     */
    private function test_facebook_credentials($app_id, $app_secret) {
        $checks = [];
        $app_access_token = $app_id . '|' . $app_secret;

        // Call /app with the app access token
        $response = wp_remote_get(
            'https://graph.facebook.com/v19.0/app?access_token=' . rawurlencode($app_access_token),
            ['timeout' => 15, 'sslverify' => true]
        );

        if (is_wp_error($response)) {
            $checks[] = [
                'label' => 'Facebook API',
                'pass'  => false,
                'detail' => 'Could not reach Facebook: ' . $response->get_error_message(),
            ];
            return $checks;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $checks[] = [
                'label' => 'Credentials verification',
                'pass'  => false,
                'detail' => 'INVALID — ' . ($body['error']['message'] ?? 'Unknown error'),
            ];
        } else {
            $app_name = $body['name'] ?? 'Unknown';
            $app_status = isset($body['id']) ? 'verified' : 'partial';
            $checks[] = [
                'label' => 'Credentials verification',
                'pass'  => true,
                'detail' => "VALID — App: \"{$app_name}\" (ID: " . ($body['id'] ?? $app_id) . ")",
            ];

            // Check if app is in live mode (if the field is available)
            if (isset($body['status'])) {
                $is_live = ($body['status'] === 'live');
                $checks[] = [
                    'label' => 'App mode',
                    'pass'  => $is_live,
                    'detail' => $is_live ? 'Live (public)' : 'Development — only admins/developers/testers can log in',
                ];
            }
        }

        return $checks;
    }

    /**
     * v1.5.0: Test Google Client ID + Secret via token endpoint
     * 
     * Sends a dummy code exchange to Google's token endpoint.
     * - "invalid_grant" → credentials are valid (code is wrong, but creds work)
     * - "invalid_client" → credentials are wrong
     */
    private function test_google_credentials($client_id, $client_secret, $redirect_uri) {
        $checks = [];

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'sslverify' => true,
            'body' => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'code'          => 'flosc_connection_test',
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $redirect_uri,
            ],
        ]);

        if (is_wp_error($response)) {
            $checks[] = [
                'label' => 'Google API',
                'pass'  => false,
                'detail' => 'Could not reach Google: ' . $response->get_error_message(),
            ];
            return $checks;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $error = $body['error'] ?? '';
        $error_desc = $body['error_description'] ?? '';

        if ($error === 'invalid_client') {
            $checks[] = [
                'label' => 'Credentials verification',
                'pass'  => false,
                'detail' => 'INVALID — Client ID or Secret is wrong',
            ];
        } elseif ($error === 'invalid_grant' || $error === 'redirect_uri_mismatch') {
            // invalid_grant = creds work, code is fake (expected)
            // redirect_uri_mismatch = creds work, but redirect URI doesn't match
            $creds_ok = ($error !== 'redirect_uri_mismatch');
            $checks[] = [
                'label' => 'Credentials verification',
                'pass'  => true,
                'detail' => 'VALID — Client ID and Secret accepted by Google',
            ];

            if ($error === 'redirect_uri_mismatch') {
                $checks[] = [
                    'label' => 'Redirect URI match',
                    'pass'  => false,
                    'detail' => "Mismatch — add your callback URL to Google Console → Authorized redirect URIs",
                ];
            } else {
                $checks[] = [
                    'label' => 'Redirect URI match',
                    'pass'  => true,
                    'detail' => 'Redirect URI is registered in Google Console',
                ];
            }
        } else {
            // Unexpected error
            $checks[] = [
                'label' => 'Credentials verification',
                'pass'  => false,
                'detail' => "Unexpected: {$error} — {$error_desc}",
            ];
        }

        return $checks;
    }

    /**
     * v1.4.3: Add FLOSC post visibility meta box to post editor
     */
    public function flosc_add_post_visibility_meta_box() {
        // v1.4.7: Only show on posts that are in a FLOSC-protected category
        global $post;
        if (!$post || !$post->ID) return;
        
        $categories = wp_get_post_categories($post->ID);
        $in_protected = false;
        foreach ($categories as $cat_id) {
            if (get_term_meta($cat_id, '_flosc_protected', true) === 'yes') {
                $in_protected = true;
                break;
            }
        }
        
        if (!$in_protected) return;
        
        add_meta_box(
            'flosc_post_visibility',
            '🔐 FLOSC Content Access',
            [$this, 'flosc_render_post_visibility_meta_box'],
            'post',
            'side',
            'high'
        );
    }

    /**
     * v1.4.3: Render the post visibility meta box
     * v1.8.2: Added 4-tier protection override (protected, title+excerpt, title+readmore, full)
     */
    public function flosc_render_post_visibility_meta_box($post) {
        wp_nonce_field('flosc_post_visibility_nonce', 'flosc_post_visibility_nonce');
        
        // v1.8.2: Read protection mode (replaces binary _flosc_public_post)
        $protection_mode = get_post_meta($post->ID, '_flosc_protection_mode', true);
        // Backward compat: old _flosc_public_post = 'yes' → 'full'
        if (empty($protection_mode)) {
            $is_public_override = get_post_meta($post->ID, '_flosc_public_post', true) === 'yes';
            $protection_mode = $is_public_override ? 'full' : 'protected';
        }
        
        // Find the protected category name for display
        $categories = wp_get_post_categories($post->ID);
        $protected_cat_name = '';
        foreach ($categories as $cat_id) {
            if (get_term_meta($cat_id, '_flosc_protected', true) === 'yes') {
                $cat = get_category($cat_id);
                $protected_cat_name = $cat ? $cat->name : '';
                break;
            }
        }
        ?>
        <?php // §12: metabox styles enqueued via the 'flosc-metabox' handle in enqueue_admin_assets(). ?>
        <div class="flosc-post-visibility-meta-box">
            <div class="flosc-protected-notice">
                🔒 Protected by FLOSC category: <strong><?php echo esc_html($protected_cat_name); ?></strong>
            </div>
            
            <div class="flosc-protection-options flosc-protection-options--spaced">
                <label>
                    <input type="radio" name="flosc_protection_mode" value="protected" <?php checked($protection_mode, 'protected'); ?>>
                    <strong>Protected</strong>
                    <span class="option-desc">Full FLOSC protection. Non-members see nothing.</span>
                </label>
                <label>
                    <input type="radio" name="flosc_protection_mode" value="title_excerpt" <?php checked($protection_mode, 'title_excerpt'); ?>>
                    <strong>Show Title &amp; Excerpt</strong>
                    <span class="option-desc">Non-members see the title and excerpt only.</span>
                </label>
                <label>
                    <input type="radio" name="flosc_protection_mode" value="title_readmore" <?php checked($protection_mode, 'title_readmore'); ?>>
                    <strong>Show Title &amp; Content through Read More</strong>
                    <span class="option-desc">Non-members see content before the &lt;!--more--&gt; tag.</span>
                </label>
                <label>
                    <input type="radio" name="flosc_protection_mode" value="full" <?php checked($protection_mode, 'full'); ?>>
                    <strong>Full Post (Public)</strong>
                    <span class="option-desc">Disable FLOSC protection. Show per WordPress settings.</span>
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * v1.4.3: Save post visibility meta box data
     * v1.8.2: Save 4-tier protection mode instead of binary checkbox
     */
    public function flosc_save_post_visibility_meta($post_id, $post) {
        $request_post = wp_unslash($_POST);
        // Security checks
        if (!isset($request_post['flosc_post_visibility_nonce']) || 
            !wp_verify_nonce(sanitize_text_field($request_post['flosc_post_visibility_nonce']), 'flosc_post_visibility_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // v1.8.2: Save protection mode (protected, title_excerpt, title_readmore, full)
        $valid_modes = ['protected', 'title_excerpt', 'title_readmore', 'full'];
        $mode = isset($request_post['flosc_protection_mode']) ? sanitize_text_field($request_post['flosc_protection_mode']) : 'protected';
        if (!in_array($mode, $valid_modes, true)) {
            $mode = 'protected';
        }
        
        update_post_meta($post_id, '_flosc_protection_mode', $mode);
        
        // Backward compat: also update _flosc_public_post for existing code that checks it
        if ($mode === 'full') {
            update_post_meta($post_id, '_flosc_public_post', 'yes');
        } else {
            delete_post_meta($post_id, '_flosc_public_post');
        }
    }

    /**
     * v1.2.3: Handle custom domain mapping (multi-flow aware)
     * 
     * Checks ALL flows for custom domain matches, not just a global setting.
     * If a flow's custom domain matches, sets query vars for routing.
     * 
     * Server requirements:
     * - Custom domain must point to same server (A record or CNAME)
     * - Server must accept requests for the custom domain (ServerAlias in Apache/Nginx)
     */
    public function handle_custom_domain() {
        $current_host = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')));
        if (empty($current_host)) {
            return;
        }
        
        // Check all flows for custom domain match
        $flows = get_option('flosc_flows', []);
        
        foreach ($flows as $flow) {
            if ($flow['status'] !== 'active' || empty($flow['custom_domain'])) {
                continue;
            }
            
            // Normalize flow's custom domain
            $flow_domain = strtolower(trim($flow['custom_domain']));
            $flow_domain = preg_replace('#^https?://#', '', $flow_domain);
            $flow_domain = rtrim($flow_domain, '/');
            
            // Check for match (with or without www)
            if ($current_host === $flow_domain || $current_host === 'www.' . $flow_domain) {
                // Set query vars so handle_app_route() will render the correct flow
                set_query_var('flosc_app', 1);
                set_query_var('flosc_flow', $flow['id']);
                
                // Store flag so we know we're on custom domain
                if (!defined('FLOSC_CUSTOM_DOMAIN_ACTIVE')) {
                    define('FLOSC_CUSTOM_DOMAIN_ACTIVE', true);
                }
                return;
            }
        }
        
        // Fallback: Check legacy global setting for backward compatibility
        $legacy_domain = get_option('flosc_custom_domain', '');
        if (!empty($legacy_domain)) {
            $legacy_domain = strtolower(preg_replace('#^https?://#', '', trim($legacy_domain)));
            $legacy_domain = rtrim($legacy_domain, '/');
            
            if ($current_host === $legacy_domain || $current_host === 'www.' . $legacy_domain) {
                set_query_var('flosc_app', 1);
                if (!defined('FLOSC_CUSTOM_DOMAIN_ACTIVE')) {
                    define('FLOSC_CUSTOM_DOMAIN_ACTIVE', true);
                }
            }
        }
    }
    
    /**
     * v1.5.2: Handle cross-domain SSO login token
     * v1.5.3: Also handles same-domain SSO success cleanup
     *
     * Cross-domain: When SSO callback on the WordPress host redirects to flosc.ai,
     * the auth cookie doesn't travel (different domain). This handler picks
     * up the one-time token, verifies it, sets the auth cookie on the
     * current domain, and redirects to a clean URL.
     *
     * Same-domain: When SSO callback redirects back to the same domain with
     * flosc_sso_success=1, this handler fires FLOSC's login hooks and cleans
     * the URL.
     *
     * NOTE: We call handle_user_login() directly instead of do_action('wp_login')
     * because other plugins (WooCommerce, BuddyBoss) hook wp_login and call
     * wp_redirect() + exit, which would hijack the SSO flow.
     */
    private function pull_pending_session_from_do($user_id) {
        $existing = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        if (is_array($existing) && !empty($existing['phrase_results'])) {
            return;
        }

        // Cookie is set by FLOSC during SSO handoff; not a form POST.
        $pending_raw = filter_input( INPUT_COOKIE, 'flosc_pending_session', FILTER_UNSAFE_RAW );
        if ( ! is_string( $pending_raw ) || $pending_raw === '' ) {
            return;
        }

        $session_id = sanitize_text_field( urldecode( wp_unslash( $pending_raw ) ) );

        // Clear the cookie immediately (one-time use)
        setcookie('flosc_pending_session', '', time() - 3600, '/');

        if (empty($session_id)) {
            return;
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_pending_session_from_do — user={$user_id}, session={$session_id}");
        }

        $this->pull_session_from_do($user_id, $session_id);
    }

    // =========================================================================
    // v3.0.0: FLOSC AUTH TOKEN — Cross-Domain Authentication
    // =========================================================================
    //
    // WordPress sets auth cookies using COOKIE_DOMAIN (derived from site_url).
    // When a custom domain (the flow domain) points to a WordPress host (the WordPress host),
    // the browser rejects auth cookies because the flow domain cannot set cookies
    // for the WordPress host.
    //
    // The FLOSC Auth Token solves this:
    // 1. On login/registration, a stateless HMAC-signed token is generated
    // 2. The token is set as a cookie with EMPTY domain (binds to current host)
    // 3. The token is also included in FLOSC_CONFIG for JS to send as a header
    // 4. The determine_current_user filter validates the token for REST API calls
    // 5. Nonce validation is automatically skipped for non-cookie auth
    //
    // Token format: base64(user_id:expiry:hmac_signature)
    // Signature: HMAC-SHA256(user_id:expiry, flosc_token_secret())  // §5: dedicated secret
    // =========================================================================




    /**
     * Shared class-based styles for quiz/profile cards rendered from PHP.
     * Kept as an inline style handle to avoid inline style attributes in markup.
     */
    private function enqueue_flosc_quiz_ui_styles() {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        wp_register_style('flosc-quiz-ui', false, [], FLOSC_VERSION);
        wp_enqueue_style('flosc-quiz-ui');
        wp_add_inline_style('flosc-quiz-ui', '
            .flosc-score-wrap { text-align: center; margin-bottom: 24px; }
            .flosc-score-ring { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 120px; border-radius: 50%; border: 6px solid currentColor; font-size: 36px; font-weight: 700; }
            .flosc-score-date { margin-top: 8px; color: #666; font-size: 14px; }
            .flosc-score--good { color: #22c55e; }
            .flosc-score--warn { color: #eab308; }
            .flosc-score--bad { color: #ef4444; }

            .flosc-weakness-wrap { margin-bottom: 24px; }
            .flosc-quiz-section-title { font-size: 16px; margin-bottom: 8px; }
            .flosc-weakness-tags { display: flex; flex-wrap: wrap; gap: 8px; }
            .flosc-weakness-tag { display: inline-block; padding: 4px 12px; border-radius: 16px; background: #f3f4f6; border: 1px solid #d1d5db; font-family: monospace; font-size: 15px; }

            .flosc-guest-warning-card { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; }
            .flosc-guest-warning-card--wide { border-radius: 8px; padding: 16px 20px; margin: 24px 0; max-width: 640px; }
            .flosc-guest-warning-title { margin: 0 0 6px; font-size: 14px; font-weight: 600; color: #92400e; }
            .flosc-guest-warning-copy { margin: 0 0 6px; font-size: 13px; color: #78350f; }
            .flosc-guest-warning-copy--tight { margin: 0; }
            .flosc-guest-warning-link { color: #b45309; font-weight: 600; }
            .flosc-guest-warning-cta { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; }
            .flosc-guest-remaining { font-size: 13px; color: #374151; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
            .flosc-guest-remaining-link { color: #2563eb; font-weight: 600; }

            .flosc-quiz-sessions-wrap { margin-bottom: 20px; }
            .flosc-quiz-details { margin-bottom: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafafa; }
            .flosc-quiz-summary { padding: 12px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; list-style: none; }
            .flosc-quiz-summary-left { display: flex; align-items: center; gap: 6px; }
            .flosc-quiz-chevron { font-size: 12px; color: #71717a; display: inline-block; transition: transform 0.2s; }
            .flosc-quiz-summary-title { font-size: 15px; font-weight: 700; }
            .flosc-quiz-summary-text { font-size: 15px; }
            .flosc-quiz-summary-score { font-weight: 700; color: #111827; }
            .flosc-quiz-score { font-weight: 700; }
            .flosc-quiz-details-body { padding: 0 12px 12px 12px; }

            .flosc-my-files { margin-top: 20px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
            .flosc-my-files-head { padding: 12px 14px; border-bottom: 1px solid #e5e7eb; }
            .flosc-my-files-title { font-size: 16px; margin: 0; }
            .flosc-my-files-subtitle { font-size: 13px; color: #6b7280; margin: 6px 0 0; }
            .flosc-my-files-body { padding: 10px 14px 14px; }
            .flosc-my-files-session { margin: 10px 0 8px; font-size: 13px; font-weight: 700; color: #111827; }
            .flosc-my-files-list { margin: 0 0 6px 18px; padding: 0; }
            .flosc-my-files-item { margin: 5px 0; font-size: 13px; }
            .flosc-my-files-link { color: #2563eb; text-decoration: none; font-weight: 600; }
            .flosc-my-files-name { color: #6b7280; }

            .flosc-phrase-breakdown-wrap { margin-top: 12px; }
            .flosc-phrase-breakdown-title { font-size: 14px; margin: 0 0 8px; }
            .flosc-phrase-breakdown-note { font-size: 13px; color: #71717a; font-style: italic; margin-bottom: 12px; }

            .flosc-word-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; margin-bottom: 8px; }
            .flosc-word-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
            .flosc-word-title { font-weight: 600; }
            .flosc-word-score { font-weight: 700; }
            .flosc-ipa-row { display: flex; gap: 8px; font-size: 13px; margin-bottom: 2px; }
            .flosc-ipa-label { color: #71717a; min-width: 110px; }
            .flosc-ipa-value { font-family: monospace; }

            .flosc-phoneme-row { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; font-size: 13px; }
            .flosc-phoneme-ipa { font-family: monospace; width: 30px; text-align: center; }
            .flosc-phoneme-progress { flex: 1; height: 8px; }
            .flosc-phoneme-score { width: 42px; text-align: right; }

            .flosc-audio-wrap { margin-bottom: 12px; }
            .flosc-audio-stream { width: 100%; height: 36px; border-radius: 8px; }
            .flosc-playback-pending { margin-bottom: 12px; font-size: 12px; color: #6b7280; }

            .flosc-audio-list { display: flex; flex-direction: column; gap: 12px; }
            .flosc-audio-item { border: 1px solid #ddd; border-radius: 6px; padding: 10px; background: #fafafa; }
            .flosc-audio-item-title { margin-bottom: 6px; }
            .flosc-audio-player { width: 100%; max-width: 400px; }

            .flosc-links-sent { font-weight: 700; }
            .flosc-links-sent--ok { color: #1a7f37; }
            .flosc-links-sent--warn { color: #d63638; }
            .flosc-links-sent-meta { color: #646970; font-size: 12px; }

            .flosc-muted-meta { color: #666; }
            .flosc-protection-options--spaced { margin-top: 10px; }

            .flosc-bb-chevron { display: inline-block; }
            details[open] > summary .flosc-bb-chevron { transform: rotate(90deg); }
            details summary::-webkit-details-marker { display: none; }
        ');
    }

    /**
     * Render the existing single-session result card format for one quiz payload.
     */
    private function render_session_result_card($user_id, $quiz_data, $is_guest_user, $profile_completed) {
        if (empty($quiz_data) || !is_array($quiz_data)) {
            return;
        }

        $score = intval($quiz_data['score'] ?? 0);
        $ranked_phonemes = $quiz_data['ranked_phonemes'] ?? [];
        $phrase_results = $quiz_data['phrase_results'] ?? [];
        $timestamp = $quiz_data['timestamp'] ?? 0;
        $date_str = $timestamp ? wp_date('F j, Y', $timestamp) : '';

        $score_class = $score >= 80 ? 'flosc-score--good' : ($score >= 60 ? 'flosc-score--warn' : 'flosc-score--bad');
        $this->enqueue_flosc_quiz_ui_styles();

        echo '<div class="flosc-score-wrap">';
        echo '<div class="flosc-score-ring ' . esc_attr($score_class) . '">';
        echo esc_html($score) . '%';
        echo '</div>';
        if ($date_str) {
            echo '<div class="flosc-score-date">Taken ' . esc_html($date_str) . '</div>';
        }
        echo '</div>';

        if ($ranked_phonemes) {
            $top_weak = array_slice($ranked_phonemes, 0, 10);
            echo '<div class="flosc-weakness-wrap">';
            echo '<h3 class="flosc-quiz-section-title">Areas for Improvement</h3>';
            echo '<div class="flosc-weakness-tags">';
            foreach ($top_weak as $ipa) {
                echo '<span class="flosc-weakness-tag">' . esc_html($ipa) . '</span>';
            }
            echo '</div>';
            echo '</div>';
        }

        $this->render_phrase_breakdown_for_quiz_data($user_id, $quiz_data, $is_guest_user, $profile_completed);
    }





    /**
     * v1.3.6: Get the current flow based on request
     * 
     * Priority:
     * 1. flosc_ivr query var (set by rewrite rules) → read from flosc_flow_{filename} option
     * 2. Custom domain match → read from flosc_flow_{filename} options
     * 3. URL slug match → read from flosc_flow_{filename} options
     * 
     * Returns flow config array or null if no match.
     */
    public function get_current_flow() {
        // v1.7.5: If flow was explicitly set (e.g., from REST API with flow_id param,
        // or companion knowledge-hub resolution), use that instead of domain/slug detection.
        // Supports purchases from any host and companion settings on hub archives.
        if ($this->forced_flow !== null) {
            return $this->forced_flow;
        }
        
        static $current_flow = null;
        static $checked = false;
        
        // Cache result within request
        if ($checked) {
            return $current_flow;
        }
        $checked = true;

        $current_flow = $this->detect_flow_from_request_route();
        return $current_flow;
    }

    /**
     * Detect active flow from the HTTP route only (rewrite var, custom domain, slug).
     * Does not consult forced_flow — used by is_flosc_request() so companion hub
     * resolution (which sets forced_flow on normal WP pages) does not make those
     * pages look like the full-page chat SPA.
     * Collaborator API (Pass 1): used by FLOSC_Full_Page_Mode.
     *
     * @return array|null Flow config or null when this request is not a FLOSC app route.
     */
    public function detect_flow_from_request_route() {
        // A custom-domain host owns its flow even when a shared-host rewrite
        // supplies a different flosc_ivr value for paths such as /chat.
        $current_host = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')));
        if ($current_host !== '') {
            $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));
            foreach ($ivr_files as $filename) {
                if (strpos($filename, 'backup') !== false) {
                    continue;
                }

                $flow = $this->build_flow_from_ivr_file($filename);
                if (!$flow || ($flow['status'] ?? 'active') !== 'active' || empty($flow['custom_domain'])) {
                    continue;
                }

                $domain = strtolower(preg_replace('#^https?://#', '', trim($flow['custom_domain'])));
                $domain = rtrim($domain, '/');
                if ($current_host === $domain || $current_host === 'www.' . $domain) {
                    return $flow;
                }
            }
        }

        // v1.3.6: Check flosc_ivr query var FIRST (set by rewrite rules)
        // v1.8.8 FIX: $wp_query doesn't exist during plugins_loaded — guard it
        global $wp_query;
        $ivr_file = ($wp_query instanceof WP_Query) ? get_query_var('flosc_ivr') : '';
        if (!empty($ivr_file)) {
            $flow = $this->build_flow_from_ivr_file($ivr_file);
            if ($flow) {
                return $flow;
            }
        }

        // §2: union shipped defaults with uploaded/edited IVR files (uploads wins).
        $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));

        $current_host = strtolower(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? '')));
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));

        foreach ($ivr_files as $filename) {
            if (strpos($filename, 'backup') !== false) {
                continue;
            }

            $flow = $this->build_flow_from_ivr_file($filename);
            if (!$flow || ($flow['status'] ?? 'active') !== 'active') {
                continue;
            }

            // Check custom domain
            if (!empty($flow['custom_domain'])) {
                $domain = strtolower(preg_replace('#^https?://#', '', trim($flow['custom_domain'])));
                $domain = rtrim($domain, '/');
                if ($current_host === $domain || $current_host === 'www.' . $domain) {
                    return $flow;
                }
            }

            // Check slug
            if (!empty($flow['slug']) && preg_match('#^/' . preg_quote($flow['slug'], '#') . '/?#', $request_uri)) {
                return $flow;
            }
        }

        return null;
    }
    
    /**
     * v1.7.5: Explicitly set flow context for REST API calls.
     * Needed when purchase requests come from domains other than the custom domain
     * (e.g., the WordPress host, clickbank, any host embedding the FLOSC checkout).
     */
    public function set_flow_context($flow_id) {
        if (empty($flow_id)) return;
        
        // Try to find the IVR file for this flow_id
        // flow_id is the base name, e.g. "flosc_default_ivr"
        // Try common extensions
        $candidates = [
            $flow_id . '.md',
            $flow_id,
        ];

        // §2: resolve uploads-first, then the shipped default.
        foreach ($candidates as $filename) {
            $full_path = flosc_config_file(basename($filename));
            if (file_exists($full_path)) {
                $this->forced_flow = $this->build_flow_from_ivr_file(basename($filename));
                return;
            }
        }
        
        // Even without an IVR file, load settings from the flow option
        // This handles cases where the flow exists in DB but the IVR file name doesn't match
        $settings_key = 'flosc_flow_' . sanitize_key($flow_id);
        $settings = get_option($settings_key, []);
        if (!empty($settings)) {
            $this->forced_flow = array_merge($settings, [
                'id' => $flow_id,
                'status' => $settings['status'] ?? 'active',
            ]);
        }
    }
    
    /**
     * v1.3.6: Build flow config from IVR filename
     * Reads from flosc_flow_{filename} option in wp_options
     * Public so companion-mode / other modules can resolve the same shape.
     */
    public function build_flow_from_ivr_file($filename) {
        $filename = basename($filename); // Ensure just filename
        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        $settings_key = 'flosc_flow_' . sanitize_key($base_name);
        $settings = get_option($settings_key, []);
        
        // Generate defaults if no settings saved
        $default_slug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $base_name));

        // v1.7.3: Merge ALL saved settings into flow array so get_setting() can
        // find payment credentials, SSO keys, etc. — not just the core flow props.
        $flow = array_merge($settings, [
            'id' => $base_name,
            'ivr_file' => $filename,
            'slug' => $settings['slug'] ?? $default_slug,
            'custom_domain' => $settings['domain'] ?? '',
            'status' => $settings['status'] ?? 'active',
        ]);

        // Ensure identity sub-array exists with defaults
        $flow['identity'] = array_merge([
            'name' => ucwords(str_replace(['_', '-'], ' ', $base_name)),
            'title' => '',
            'tagline' => '',
            'primary_color' => '#4f46e5',
            'chatlogo_url' => '',
            'favicon_url' => '',
            'badgeUrl' => '',
            'share_text' => '',
        ], $flow['identity'] ?? []);

        return $flow;
    }
    
    /**
     * v1.2.4: Get a setting value, checking flow-specific first, then global
     * 
     * @param string $key Setting key (without 'flosc_' prefix)
     * @param mixed $default Default if neither flow nor global has value
     * @param string|null $flow_id Force specific flow (null = auto-detect current)
     * @return mixed The setting value
     */
    public function get_setting($key, $default = '', $flow_id = null) {
        if (function_exists('flosc_content_item_canonical_option_key')) {
            $key = flosc_content_item_canonical_option_key($key);
        }
        // Get flow context
        if ($flow_id !== null) {
            $flow = flosc_flows()->get_flow($flow_id);
            if (!$flow) {
                // IVR-file flows live outside the flows registry; read their
                // settings bag through the same builder the runtime uses.
                $flosc_ivr_candidates = array_unique(array(basename((string) $flow_id), basename((string) $flow_id) . '.md'));
                $flosc_ivr_files = array_map('basename', (array) flosc_config_glob(['*_ivr.md', 'ivr*.md']));
                foreach ($flosc_ivr_candidates as $flosc_ivr_candidate) {
                    if ($flosc_ivr_candidate !== '' && in_array($flosc_ivr_candidate, $flosc_ivr_files, true)) {
                        $flow = $this->build_flow_from_ivr_file($flosc_ivr_candidate);
                        break;
                    }
                }
            }
        } else {
            $flow = $this->get_current_flow();
        }
        if (is_array($flow) && function_exists('flosc_normalize_content_item_flow_settings')) {
            $flow = flosc_normalize_content_item_flow_settings($flow);
        }
        
        // Nested identity (name / title / tagline / logos) is canonical after Identity save.
        if (
            $flow
            && isset($flow['identity'])
            && is_array($flow['identity'])
            && array_key_exists($key, $flow['identity'])
            && $flow['identity'][$key] !== ''
            && $flow['identity'][$key] !== null
        ) {
            return $flow['identity'][$key];
        }

        // Flat flow key (seed / older bags that have not been nested yet).
        if ($flow && isset($flow[$key]) && $flow[$key] !== '' && $flow[$key] !== null) {
            return $flow[$key];
        }
        
        // Fallback to global wp_option (canonical then legacy)
        $val = get_option('flosc_' . $key, null);
        if ($val !== null && $val !== false && $val !== '') {
            return $val;
        }
        if (function_exists('flosc_content_item_option_key_map')) {
            $map = flosc_content_item_option_key_map();
            $old = $map[$key] ?? '';
            if ($old !== '') {
                $legacy = get_option('flosc_' . $old, null);
                if ($legacy !== null && $legacy !== false && $legacy !== '') {
                    return $legacy;
                }
            }
        }
        return $default;
    }
    
    public function is_flosc_request() {
        return $this->full_page_mode->is_flosc_request();
    }

    public function get_app_url($flow = null) {
        return $this->full_page_mode->get_app_url($flow);
    }

    public function add_query_vars($vars) {
        return $this->full_page_mode->add_query_vars($vars);
    }

    public function handle_app_route() {
        return $this->full_page_mode->handle_app_route();
    }

    private function get_requested_legal_page() {
        return $this->full_page_mode->get_requested_legal_page();
    }

    private function get_current_request_base_url() {
        return $this->full_page_mode->get_current_request_base_url();
    }

    private function render_legal_page($page) {
        return $this->full_page_mode->render_legal_page($page);
    }

    private function get_codex_charter_content() {
        return $this->full_page_mode->get_codex_charter_content();
    }

    private function render_flosc_app() {
        return $this->full_page_mode->render_flosc_app();
    }

    
    /**
     * Get FloscFlow Identity — name, chatlogo, favicon, brand color, pricing.
     * Reads from $flow['identity'] sub-array, falls back to wp_options.
     */
    public function get_floscflow_identity() {
        $flow = $this->get_current_flow();
        
        if ($flow) {
            $id = is_array($flow['identity'] ?? null) ? $flow['identity'] : [];
            // Prefer identity sub-array; fall back to flat flow keys (some saves store flat).
            $name = (string) ($id['name'] ?? $flow['name'] ?? '');
            $chatlogo = (string) ($id['chatlogo_url'] ?? $flow['chatlogo_url'] ?? '');
            $favicon = (string) ($id['favicon_url'] ?? $flow['favicon_url'] ?? '');
            $primary = (string) ($id['primary_color'] ?? $flow['primary_color'] ?? '#4f46e5');
            
            return [
                'name'            => $name !== '' ? $name : 'FLOSC App',
                'title'           => $id['title'] ?? ($flow['title'] ?? ''),
                'tagline'         => $id['tagline'] ?? ($flow['tagline'] ?? ''),
                'chatlogo_url'    => $chatlogo,
                'favicon_url'     => $favicon,
                'badgeUrl'        => $id['badgeUrl'] ?? ($flow['badgeUrl'] ?? ''),
                'primary_color'   => $primary !== '' ? $primary : '#4f46e5',
                'share_text'      => $id['share_text'] ?? ($flow['share_text'] ?? ''),
                'flow_id'         => $flow['id'] ?? 'default',
                'currency_symbol' => $id['currency_symbol'] ?? get_option('flosc_currency_symbol', '$'),
            ];
        }
        
        // No flow loaded — fall back to global settings
        return [
            'name' => get_option('flosc_product_name', 'FLOSC App'),
            'title' => get_option('flosc_product_title', ''),
            'tagline' => get_option('flosc_product_tagline', ''),
            'chatlogo_url' => get_option('flosc_product_logo', ''),
            'favicon_url' => get_option('flosc_product_app_icon', ''),
            'badgeUrl' => get_option('flosc_product_badge_url', ''),
            'primary_color' => get_option('flosc_primary_color', '#4f46e5'),
            'share_text' => get_option('flosc_share_text', 'Check out this amazing app!'),
            'flow_id' => 'default',
            'currency_symbol' => get_option('flosc_currency_symbol', '$'),
        ];
    }

    /**
     * Build AI context for phase-aware prompts (v04_04)
     */
    public function build_ai_context($frontend_context = []) {
        $context = [];

        // 1. Determine FLOSC phase
        $context['phase'] = $this->determine_flosc_phase();

        // 2. User info
        $context['logged_in'] = is_user_logged_in();

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $context['user_name'] = $user->display_name;
            $context['user_email'] = $user->user_email;
            $context['user_status'] = $this->get_user_status();
            $context['is_admin'] = user_can($user->ID, 'manage_options');

            // v1.0.3: Bridge data from manager
            $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
            $bridge_data = $bridge_mgr->get_flosc_bridge_data($user->ID);
            
            // Bridge state info
            $context['in_bridge_state'] = $bridge_mgr->is_in_flosc_bridge_state($user->ID);
            $context['has_quiz_profile'] = $bridge_mgr->flosc_has_profile($user->ID);
            
            if ($bridge_data) {
                $context['quiz_score'] = ($bridge_data['score'] ?? 0) . '%';
                $context['bridge_score'] = $bridge_data['score'] ?? 0;
                $context['bridge_correct_count'] = count($bridge_data['correct_items'] ?? []);
                $context['bridge_incorrect_count'] = count($bridge_data['incorrect_items'] ?? []);
                $context['weakest_category'] = $bridge_mgr->get_flosc_weakest_category($user->ID);
            } else {
                // Fallback to legacy user meta
                $quiz_score = get_user_meta($user->ID, '_flosc_last_quiz_score', true);
                if ($quiz_score) {
                    $context['quiz_score'] = $quiz_score . '%';
                }
            }

            // Free lesson delivered
            $free_lesson_delivered = get_user_meta($user->ID, '_flosc_free_content_item_delivered', true);
            $context['free_lesson_delivered'] = $free_lesson_delivered ? 'Yes' : 'No';

            // Membership / full access (authoritative). can_access('full') → is_member.
            $is_member = $this->sale_manager->access()->can_access( $user->ID, 'full' );
            $context['purchased']     = $is_member ? 'Yes' : 'No';
            $context['is_member']     = $is_member;
            $context['access_level']  = $is_member ? 'member' : 'guest';
            $context['user_id']       = $user->ID;
        } else {
            $context['user_status']   = 'visitor';
            $context['purchased']     = 'No';
            $context['is_member']     = false;
            $context['access_level']  = 'visitor';
        }

        // 3. Merge frontend context (message count, quiz taken, etc.)
        // Do NOT let a stale frontend purchased/access_level demote a real member.
        if (!empty($frontend_context)) {
            $protected = [
                'purchased'    => $context['purchased'] ?? 'No',
                'is_member'    => $context['is_member'] ?? false,
                'access_level' => $context['access_level'] ?? 'visitor',
                'user_id'      => $context['user_id'] ?? 0,
                'logged_in'    => $context['logged_in'] ?? is_user_logged_in(),
                'user_status'  => $context['user_status'] ?? 'visitor',
            ];
            $context = array_merge( $context, $frontend_context );
            foreach ( $protected as $pkey => $pval ) {
                // Keep backend member elevation; allow frontend to elevate only if backend guest and frontend claims member (unlikely / ignored for demote).
                if ( in_array( $pkey, [ 'purchased', 'is_member', 'access_level' ], true ) ) {
                    $backend_member = (
                        $pval === true || $pval === 'Yes' || $pval === 'member'
                        || $pval === 1 || $pval === '1'
                    );
                    if ( $backend_member ) {
                        $context[ $pkey ] = $pval;
                    }
                } else {
                    $context[ $pkey ] = $pval;
                }
            }
        }

        // 4. Flow identity info
        $identity = $this->get_floscflow_identity();
        $context['product_name'] = trim((string) ($identity['title'] ?? ''));
        $context['public_title'] = trim((string) ($identity['title'] ?? ''));
        $context['public_tagline'] = trim((string) ($identity['tagline'] ?? ''));

        return $context;
    }

    /**
     * Determine current FLOSC phase
     * v1.4.9: Aligned with frontend determinePhase() logic:
     *   purchased → content, funnelCompleted → sale,
     *   freeLessonDelivered → offer, logged_in → login, else → freeline
     */
    public function determine_flosc_phase() {
        if (!is_user_logged_in()) {
            // Visitors are always freeline — frontend determinePhase() agrees.
            // Quiz data lives in signed cookies but doesn't change the phase;
            // the IVR condition evaluator already checks quiz_taken separately.
            return 'freeline';
        }

        $user_id = get_current_user_id();

        // v8.1.0: Unified — use FLOSC_Member_Access as single source of truth
        // Previously used sale_manager->access()->is_member() which checked _flosc_access offers array.
        // Now checks _flosc_member_access meta (written by all purchase flows via flosc_purchase_completed hook).
        if ($this->member_access->is_member($user_id)) {
            return 'content';
        }

        // Frontend: if (this.user?.funnelCompleted) return 'sale';
        $funnel_complete = get_user_meta($user_id, '_flosc_funnel_completed', true);
        if ($funnel_complete) {
            return 'sale';
        }

        // Frontend: if (this.user?.freeLessonDelivered) return 'offer';
        $free_lesson_delivered = get_user_meta($user_id, '_flosc_free_content_item_delivered', true);
        if ($free_lesson_delivered) {
            return 'offer';
        }

        // Frontend: if (this.state !== 'visitor') return 'login';
        // Logged-in user who hasn't received free lesson yet
        return 'login';
    }

    /**
     * Get user status (visitor, free, paid)
     */
    private function get_user_status() {
        if (!is_user_logged_in()) {
            return 'visitor';
        }

        $user_id = get_current_user_id();
        return $this->sale_manager->access()->can_access($user_id, 'full') ? 'paid' : 'free';
    }

    /**
     * REST Handlers
     */
    


    private function flosc_build_da1_composition_reply($message, $flow_id, $ivr_file) {
        return $this->da1_compositions->build_composition_reply($message, $flow_id, $ivr_file);
    }

    private function flosc_is_composition_query($message) {
        return $this->da1_compositions->is_composition_query($message);
    }

    private function flosc_da1_is_count_request($message) {
        return $this->da1_compositions->is_count_request($message);
    }

    private function flosc_da1_is_full_list_request($message) {
        return $this->da1_compositions->is_full_list_request($message);
    }

    private function flosc_da1_detect_batch_size($message) {
        return $this->da1_compositions->detect_batch_size($message);
    }

    private function flosc_da1_get_works_list_url() {
        return $this->da1_compositions->get_works_list_url();
    }

    private function flosc_load_da1_rows_for_flow($flow_id, $ivr_file) {
        return $this->da1_compositions->load_rows_for_flow($flow_id, $ivr_file);
    }

    private function flosc_extract_da1_composition_items($rows) {
        return $this->da1_compositions->extract_composition_items($rows);
    }

    private function flosc_da1_extract_primary_media_url($text) {
        return $this->da1_compositions->extract_primary_media_url($text);
    }

    private function flosc_da1_parse_tsv_content($content) {
        return $this->da1_compositions->parse_tsv_content($content);
    }

    private function flosc_shorten_text($text, $limit) {
        return $this->da1_compositions->shorten_text($text, $limit);
    }

    private function flosc_limit_chat_response_length($text) {
        return $this->da1_compositions->limit_chat_response_length($text);
    }


    private function flosc_enforce_no_hedge_response($response_text, $user_message, $flow_id, $ivr_file, $phase, $eval_context) {
        $response_text = trim((string) $response_text);

        if ($response_text === '' || $this->flosc_contains_forbidden_hedge($response_text)) {
            return $this->flosc_build_professional_replacement($user_message, $flow_id, $ivr_file, $phase, $eval_context);
        }

        return $response_text;
    }

    private function flosc_contains_forbidden_hedge($text) {
        $text = (string) $text;
        $patterns = [
            '/\bi\s+don\'t\s+have\b[^\n]{0,160}\b(information|info|context|details|data|catalog|count|biography|bio|configured|system)\b/i',
            '/\bi\s+do\s+not\s+have\b[^\n]{0,160}\b(information|info|context|details|data|catalog|count|biography|bio|configured|system)\b/i',
            '/\bnot\s+configured\b[^\n]{0,80}\b(system|right\s+now)?\b/i',
            '/\bconfigured\s+in\s+my\s+system\b/i',
            '/\bmissing\s+(catalog|biography|bio|context|data)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function flosc_build_professional_replacement($user_message, $flow_id, $ivr_file, $phase, $eval_context) {
        $user_message = (string) $user_message;

        if ($this->flosc_is_composition_query($user_message)) {
            $catalog_reply = $this->flosc_build_da1_composition_reply($user_message, $flow_id, $ivr_file);
            if ($catalog_reply !== '') {
                return $catalog_reply;
            }
        }

        if ($this->flosc_is_bio_query($user_message)) {
            // Flow identity settings only — no hard-coded personal brand defaults (WP.org).
            $bio_summary = trim((string) flosc_get_setting('identity_bio_summary', ''));
            if ($bio_summary === '') {
                $identity_name = trim((string) flosc_get_setting('name', ''));
                if ($identity_name === '') {
                    $identity_name = 'this host';
                }
                $bio_summary = sprintf(
                    /* translators: %s: flow identity display name */
                    __('%s — ask about background, work, or how to get started with this flow.', 'flosc'),
                    $identity_name
                );
            }

            $bio_url = trim((string) flosc_get_setting('identity_bio_url', ''));
            $reply = $bio_summary;
            if ($bio_url !== '' && filter_var($bio_url, FILTER_VALIDATE_URL)) {
                $reply .= "\n" . sprintf(
                    /* translators: %s: biography URL */
                    __('More info: %s', 'flosc'),
                    $bio_url
                );
            }

            return $this->flosc_limit_chat_response_length($reply);
        }

        $default_response = $this->get_phase_default_response((string) $phase, is_array($eval_context) ? $eval_context : []);
        $default_response = trim((string) $default_response);

        if ($default_response !== '') {
            return $default_response;
        }

        return 'I can help with a direct answer. Ask for biography, resume link, catalog count, full works list, or 1 to 3 composition recommendations.';
    }

    private function flosc_is_bio_query($message) {
        $message = (string) $message;
        $message = function_exists('mb_strtolower')
            ? mb_strtolower($message, 'UTF-8')
            : strtolower($message);
        // Generic bio/identity queries only. Person-specific phrases belong in
        // flow IVR / trajectories (e.g. assistant), not engine hardcode.
        return (bool) preg_match(
            '/\b(bio|biography|background|resume|cv|who\s+are\s+you|about\s+you|about\s+the\s+(host|author|founder))\b/u',
            $message
        );
    }



    /**
     * Build transient key for visitor session token balance.
     */
    public function flosc_visitor_token_transient_key($flow_id, $session_id) {
        unset($flow_id);
        $namespace = 'v4j';
        return 'flosc_vtok_' . $namespace . '_' . absint($session_id);
    }





    
    /**
     * Build system prompt for RAG chat
     * Fix 8: Use full FLOSC_Chatpack instead of the bare minimal prompt that had no rules/grounding.
     */
    private function build_rag_system_prompt($user_context) {

        $access_level = $user_context['access_level'];

        // Fix 8: Build a proper eval_context from the user_context so FLOSC_Chatpack
        // can assemble an identity + rules + KB chatpack — the same treatment as the
        // main chat path. Without this, the RAG path had zero acronym definitions,
        // zero absolute rules, and zero factual grounding.
        if (class_exists('FLOSC_Chatpack')) {
            $eval_context = [
                'access_level'    => $access_level,
                'user_name'       => $user_context['user_name'] ?? 'User',
                'is_admin'        => $user_context['is_admin'] ?? false,
                'user_id'         => $user_context['user_id'] ?? 0,
                'quiz_taken'      => !empty($user_context['quiz_results']),
                'quiz_score'      => $user_context['quiz_score'] ?? 0,
            ];
            $phase = $user_context['phase'] ?? ($user_context['is_member'] ? 'content' : 'freeline');
            $flosc_hash    = FLOSC_Chatpack::generate_flosc_hash();
            $session_hash  = FLOSC_Chatpack::generate_session_hash($flosc_hash, $eval_context['user_id']);
            $chatpack_prompt = FLOSC_Chatpack::build_full_chatpack($phase, $eval_context, '', $flosc_hash, $session_hash, 1, null);

            // Append RAG-specific tool usage instructions after the full chatpack
            $chatpack_prompt .= "\n\n**RAG TOOL USAGE:**\n"
                . "- When you need information about specific lessons, use search_knowledge_base or search_posts\n"
                . "- When asked about available content, use search_posts\n"
                . "- When you need full lesson details, use get_lesson_content\n"
                . "- Always filter responses based on the user's access level\n"
                . "- DO NOT teach content yourself — point to the actual WordPress lessons";
            return $chatpack_prompt;
        }

        // Fallback if chatpack not available
        $personality_name = flosc_get_setting('ai_identity_name', 'AI Assistant');
        $personality_desc = flosc_get_setting('ai_identity_role', 'friendly and knowledgeable learning guide');

        // Get access level instructions
        $access_instructions = $this->get_access_level_instructions($access_level);

        $prompt = "You are {$personality_name}, a {$personality_desc}.

**YOUR ROLE:**
You are a GUIDE, not a teacher. Your job is to:
1. Guide users through the learning journey
2. Direct them to WordPress lessons and content
3. Use search tools to find relevant content when needed
4. Encourage them through the funnel (visitor → quiz → member)

**CURRENT USER:**
- Access Level: **{$access_level}**
- Logged in: " . ($user_context['is_logged_in'] ? 'Yes' : 'No') . "
- Member: " . ($user_context['is_member'] ? 'Yes' : 'No') . "
";

        // Add quiz results if available
        if (isset($user_context['quiz_results'])) {
            $quiz_score = $user_context['quiz_score'] ?? 0;
            $prompt .= "\n**QUIZ RESULTS:**\n";
            $prompt .= "Score: {$quiz_score}%\n";
            $prompt .= "Details: " . wp_json_encode($user_context['quiz_results']) . "\n";
            
            // Add pricing info if applicable
            if (isset($user_context['within_discount_window']) && $user_context['within_discount_window']) {
                $minutes_left = 30 - intval($user_context['minutes_since_quiz']);
                $discount_price = flosc_get_setting('discount_price', '');
                $discount_label = $discount_price ? "discount price of {$discount_price}" : 'special discount';
                $prompt .= "\n**SPECIAL OFFER ACTIVE:**\n";
                $prompt .= "- User took quiz recently\n";
                $prompt .= "- {$minutes_left} minutes remaining for {$discount_label}\n";
                $prompt .= "- Mention this time-limited offer!\n";
            }
        }
        
        $prompt .= "\n" . $access_instructions;
        
        $prompt .= "\n\n**HOW TO USE TOOLS:**
- When you need information about specific lessons, use search_knowledge_base or search_posts
- When asked about what content is available, use search_posts
- When you need full lesson details, use get_lesson_content
- Always filter your responses based on the user's access level

**IMPORTANT:**
- DO NOT try to teach content yourself - point to the actual WordPress lessons
- DO respect access level restrictions
- DO encourage quiz-taking for visitors
- DO mention time-limited offers when applicable
- DO be warm and encouraging";

        return $prompt;
    }
    
    /**
     * v1.4.0: Admin Introspection - Check if admin is asking about the system
     * Allows WordPress admins to ask the chat about its configuration, files, offers, etc.
     */
    private function check_admin_introspection($message, $current_ivr_file = '') {
        $message_lower = strtolower($message);
        
        // Introspection trigger patterns
        $triggers = [
            'files' => ['what files', 'which files', 'ivr files', 'configuration files', 'config files', 'available files'],
            'offers' => ['what offers', 'which offers', 'available offers', 'configured offers', 'show offers', 'list offers'],
            'system' => ['about yourself', 'tell me about you', 'who are you', 'system info', 'system status', 'debug info', 'flosc status'],
            'flows' => ['what flows', 'which flows', 'available flows', 'configured flows', 'show flows', 'list flows'],
            'providers' => ['payment providers', 'which providers', 'available providers', 'payment methods'],
            'quizzes' => ['what quizzes', 'which quizzes', 'available quizzes', 'configured quizzes', 'quiz types'],
            'current' => ['current config', 'current ivr', 'current flow', 'what am i using', 'which ivr'],
            'status' => ['user status', 'my status', 'my account', 'my role', 'am i admin', 'my access', 'my user', 'account status', 'my profile', 'what role', 'my permissions'],
            'help' => ['admin help', 'admin commands', 'what can i ask', 'introspection help'],
        ];
        
        $matched_category = null;
        foreach ($triggers as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($message_lower, $pattern) !== false) {
                    $matched_category = $category;
                    break 2;
                }
            }
        }
        
        if (!$matched_category) {
            return null; // Not an introspection query
        }
        
        return $this->get_admin_introspection_response($matched_category, $current_ivr_file);
    }
    
    /**
     * v1.4.0: Generate admin introspection response
     */
    private function get_admin_introspection_response($category, $current_ivr_file = '') {
        // v1.9.1: Michel Date Stamp timestamp in introspection header
        $now = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-T' . gmdate('H') . 'h:' . gmdate('i') . 'm:' . gmdate('s') . 's';
        $response = "🔧 **FLOSC Admin Introspection** (v" . FLOSC_VERSION . ") — {$now}\n\n";
        
        switch ($category) {
            case 'files':
                $response .= $this->get_introspection_files();
                break;
                
            case 'offers':
                $response .= $this->get_introspection_offers();
                break;
                
            case 'system':
                $response .= $this->get_introspection_system($current_ivr_file);
                break;
                
            case 'flows':
                $response .= $this->get_introspection_flows();
                break;
                
            case 'providers':
                $response .= $this->get_introspection_providers();
                break;
                
            case 'quizzes':
                $response .= $this->get_introspection_quizzes();
                break;
                
            case 'current':
                $response .= $this->get_introspection_current($current_ivr_file);
                break;

            case 'status':
                $response .= $this->get_introspection_user_status();
                break;
                
            case 'help':
            default:
                $response .= $this->get_introspection_help();
                break;
        }
        
        return $response;
    }
    
    /**
     * v1.4.0: Get available IVR configuration files
     */
    private function get_introspection_files() {
        $files = flosc_config_glob('*.md');
        
        $output = "📁 **IVR Configuration Files:**\n\n";
        
        if (empty($files)) {
            $output .= "_No IVR files found in ai_configuration_files/_\n";
        } else {
            foreach ($files as $file) {
                $basename = basename($file);
                $size = filesize($file);
                // v1.9.1: Michel Date Stamp format
                $mtime = filemtime($file);
                $modified = gmdate('Y', $mtime) . '-' . gmdate('m', $mtime) . 'm-' . gmdate('d', $mtime) . 'd-T' . gmdate('H', $mtime) . 'h:' . gmdate('i', $mtime) . 'm';
                
                // Parse to get message count
                $content = flosc_fs_get_contents($file);
                preg_match_all('/^## /m', $content, $matches);
                $message_count = count($matches[0]);
                
                $output .= "• **{$basename}**\n";
                $output .= "  - Messages: ~{$message_count}\n";
                $output .= "  - Size: " . number_format($size) . " bytes\n";
                $output .= "  - Modified: {$modified}\n\n";
            }
        }
        
        return $output;
    }
    
    /**
     * v1.4.0: Get configured offers
     */
    private function get_introspection_offers() {
        $offers = $this->sale_manager->offers()->get_all_offers();
        
        $output = "🏷️ **Configured Offers:**\n\n";
        
        if (empty($offers)) {
            $output .= "_No offers configured_\n";
        } else {
            foreach ($offers as $id => $offer) {
                $status = $offer['status'] ?? 'unknown';
                $type = $offer['type'] ?? 'one_time';
                $price = $offer['display_price'] ?? 'Not set';
                $grants_level = $offer['grants']['level'] ?? 'none';
                
                $status_icon = ($status === 'active') ? '✅' : '⏸️';
                
                $output .= "{$status_icon} **{$offer['name']}** (`{$id}`)\n";
                $output .= "  - Type: {$type}\n";
                $output .= "  - Price: {$price}\n";
                $output .= "  - Grants Level: {$grants_level}\n";
                $output .= "  - Status: {$status}\n\n";
            }
        }
        
        return $output;
    }
    
    /**
     * v1.4.0: Get system overview
     */
    private function get_introspection_system($current_ivr_file = '') {
        $output = "🖥️ **FLOSC System Overview:**\n\n";
        
        // Version info
        $output .= "**Version:** " . FLOSC_VERSION . "\n";
        $output .= "**Debug Mode:** " . (FLOSC_DEBUG ? 'Enabled' : 'Disabled') . "\n";
        $output .= "**Plugin Path:** `" . FLOSC_PLUGIN_DIR . "`\n\n";
        
        // Current IVR
        if ($current_ivr_file) {
            $output .= "**Current IVR:** `{$current_ivr_file}`\n\n";
        }
        
        // AI Provider
        $ai_provider = flosc_get_setting('ai_provider', 'ivr');
        $output .= "**AI Provider:** {$ai_provider}\n";
        
        // STT Provider
        $stt_provider = flosc_get_setting('stt_provider', 'assemblyai');
        $output .= "**STT Provider:** {$stt_provider}\n\n";
        
        // User counts via cached usermeta ID lists (no SlowDBQuery meta_query).
        $member_count = function_exists( 'flosc_get_user_ids_for_meta' )
            ? count( flosc_get_user_ids_for_meta( '_flosc_member_level' ) )
            : 0;
        $quiz_count   = function_exists( 'flosc_get_user_ids_for_meta' )
            ? count( flosc_get_user_ids_for_meta( '_flosc_last_quiz_score' ) )
            : 0;

        $output .= "**Users with Quiz Scores:** {$quiz_count}\n";
        $output .= "**Users with Member Levels:** {$member_count}\n\n";
        
        // Personality
        // v1.9.1: No hardcoded personality — admin sets this in settings
        $personality_name = get_option('flosc_personality_name', '');
        if (empty($personality_name)) {
            $personality_name = '_Not configured — set in FLOSC Settings → Personality Name_';
        }
        $output .= "**Chat Personality:** {$personality_name}\n";
        
        return $output;
    }
    
    /**
     * v1.4.0: Get configured flows
     */
    private function get_introspection_flows() {
        $flows = get_option('flosc_flows', []);
        
        $output = "🔄 **Configured Flows:**\n\n";
        
        if (empty($flows)) {
            $output .= "_No custom flows configured. Using default routing._\n\n";
        } else {
            foreach ($flows as $id => $flow) {
                $output .= "• **{$flow['name']}** (`{$id}`)\n";
                $output .= "  - Slug: `{$flow['slug']}`\n";
                $output .= "  - IVR File: `{$flow['ivr_file']}`\n";
                if (!empty($flow['domain'])) {
                    $output .= "  - Domain: {$flow['domain']}\n";
                }
                $output .= "\n";
            }
        }
        
        // Also list available IVR files that could be used
        $output .= "**Available IVR Files for Flows:**\n";
        $files = flosc_config_glob('*_ivr.md');
        foreach ($files as $file) {
            $output .= "• `" . basename($file) . "`\n";
        }
        
        return $output;
    }
    
    /**
     * v1.4.0: Get payment providers
     */
    private function get_introspection_providers() {
        $providers = $this->sale_manager->get_providers();
        
        $output = "💳 **Payment Providers:**\n\n";
        
        foreach ($providers as $id => $provider) {
            $configured = $provider->is_configured() ? '✅ Configured' : '❌ Not Configured';
            $enabled = $provider->is_enabled() ? 'Enabled' : 'Disabled';
            $subscriptions = $provider->supports_subscriptions() ? 'Yes' : 'No';
            
            $output .= "• **{$provider->get_name()}** {$provider->get_icon()} (`{$id}`)\n";
            $output .= "  - Status: {$configured}\n";
            $output .= "  - Enabled: {$enabled}\n";
            $output .= "  - Subscriptions: {$subscriptions}\n\n";
        }
        
        return $output;
    }
    
    /**
     * v1.4.0: Get available quiz types
     */
    private function get_introspection_quizzes() {
        $quiz_types = FLOSC_Quiz_Registry::get_all_quizzes();
        $enabled_quizzes = flosc_get_setting('enabled_quizzes', []);
        if (!is_array($enabled_quizzes)) {
            $enabled_quizzes = [];
        }        
        $output = "📝 **Available Quiz Types:**\n\n";
        
        foreach ($quiz_types as $id => $quiz) {
            $enabled_icon = in_array($id, $enabled_quizzes) ? '✅' : '⏸️';
            $needs_audio = $quiz->needs_audio() ? '🎤 Audio' : '📝 Text';
            
            $output .= "{$enabled_icon} **{$quiz->get_name()}** {$quiz->get_icon()}\n";
            $output .= "  - ID: `{$id}`\n";
            $output .= "  - Type: {$needs_audio}\n";
            $output .= "  - Description: {$quiz->get_description()}\n\n";
        }
        
        return $output;
    }
    
    /**
     * v1.4.0: Get current configuration context
     */
    private function get_introspection_current($current_ivr_file = '') {
        $output = "📍 **Current Context:**\n\n";
        
        // Current IVR
        $output .= "**IVR File:** " . ($current_ivr_file ?: '_default/unknown_') . "\n";
        
        // Current flow
        $flow = $this->get_current_flow();
        if ($flow) {
            $output .= "**Flow Name:** {$flow['name']}\n";
            $output .= "**Flow Slug:** {$flow['slug']}\n";
            $output .= "**Flow Domain:** " . ($flow['domain'] ?? '_any_') . "\n\n";
        }
        
        // Current user context
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user = get_userdata($user_id);
            $member_levels = ($this->member_access && method_exists($this->member_access, 'get_user_levels'))
                ? $this->member_access->get_user_levels($user_id)
                : [];
            $quiz_score = get_user_meta($user_id, '_flosc_last_quiz_score', true);
            $purchased = get_user_meta($user_id, '_flosc_purchased', true);
            
            $output .= "**Your User:**\n";
            $output .= "  - ID: {$user_id}\n";
            $output .= "  - Name: {$user->display_name}\n";
            $output .= "  - Member Levels: " . (empty($member_levels) ? '_none_' : implode(', ', $member_levels)) . "\n";
            $output .= "  - Last Quiz Score: " . ($quiz_score ?: '_no quiz taken_') . "\n";
            $output .= "  - Purchased: " . ($purchased ? 'Yes' : 'No') . "\n";
        }
        
        return $output;
    }
    
    /**
     * v1.9.2: Get admin's own user status — WordPress + FLOSC data
     */
    private function get_introspection_user_status() {
        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) {
            return "_Could not retrieve user data._\n";
        }

        $output = "👤 **Your User Status:**\n\n";

        // WordPress identity
        $output .= "**WordPress Account:**\n";
        $output .= "  - User ID: {$user_id}\n";
        $output .= "  - Display Name: {$user->display_name}\n";
        $output .= "  - Username: {$user->user_login}\n";
        $output .= "  - Email: {$user->user_email}\n";
        $output .= "  - Roles: " . implode(', ', $user->roles) . "\n";
        // v1.9.1: Michel Date Stamp format for registration
        $reg_ts = strtotime($user->user_registered);
        $registered = gmdate('Y', $reg_ts) . '-' . gmdate('m', $reg_ts) . 'm-' . gmdate('d', $reg_ts) . 'd';
        $output .= "  - Registered: {$registered}\n";
        $output .= "  - Is Admin: " . (current_user_can('manage_options') ? '✅ Yes' : '❌ No') . "\n\n";

        // FLOSC-specific data
        $output .= "**FLOSC Status:**\n";
        $phase = $this->determine_flosc_phase();
        $output .= "  - Backend Phase: " . strtoupper($phase) . "\n";
        $output .= "  - Role: ADMIN (transcends funnel — full access to all phases)\n";

        // Quiz data
        $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
        $bridge_data = $bridge_mgr->get_flosc_bridge_data($user_id);
        if ($bridge_data) {
            $score = $bridge_data['score'] ?? 'N/A';
            $correct = $bridge_data['correct_items'] ?? [];
            $incorrect = $bridge_data['incorrect_items'] ?? [];
            $output .= "  - Quiz Score: {$score}%\n";
            $output .= "  - Quiz Correct: " . count($correct) . " items\n";
            $output .= "  - Quiz Incorrect: " . count($incorrect) . " items\n";
            $weakest = $bridge_mgr->get_flosc_weakest_category($user_id);
            if ($weakest) {
                $output .= "  - Weakest Category: {$weakest}\n";
            }
        } else {
            $legacy_score = get_user_meta($user_id, '_flosc_last_quiz_score', true);
            $output .= "  - Quiz Score: " . ($legacy_score ? "{$legacy_score}%" : '_No quiz taken_') . "\n";
        }

        // Member access — report current-flow state (not global legacy flag)
        $member_levels = ($this->member_access && method_exists($this->member_access, 'get_user_levels'))
            ? $this->member_access->get_user_levels($user_id)
            : [];
        $status_flow = $this->get_current_flow();
        $status_stem = '';
        if (is_array($status_flow)) {
            $status_stem = $this->flosc_normalize_flow_stem(
                (string) ($status_flow['ivr_file'] ?? $status_flow['ivr'] ?? $status_flow['id'] ?? '')
            );
        }
        $is_member = false;
        if ( $this->sale_manager && method_exists( $this->sale_manager, 'access' ) ) {
            $is_member = ( $this->sale_manager->access()->get_simple_state( $user_id, $status_stem ) === 'member' );
        } elseif ( $this->member_access && method_exists( $this->member_access, 'is_member' ) ) {
            $is_member = (bool) $this->member_access->is_member( $user_id, $status_stem );
        }
        $output .= "  - Flow: " . ($status_stem !== '' ? $status_stem : '_unknown_') . "\n";
        $output .= "  - Member Access (this flow): " . ( $is_member ? '✅ Yes' : '❌ No (guest if logged in)' ) . "\n";
        $output .= "  - Member Levels: " . (empty($member_levels) ? '_none_' : implode(', ', $member_levels)) . "\n";

        // Free lesson
        $free_lesson_delivered = get_user_meta($user_id, '_flosc_free_content_item_delivered', true);
        $free_lesson_num = get_user_meta($user_id, '_flosc_free_content_item_number', true);
        $output .= "  - Free Lesson Delivered: " . ($free_lesson_delivered ? "Yes ({$free_lesson_delivered})" : 'No') . "\n";
        if ($free_lesson_num) {
            $output .= "  - Free Lesson Number: {$free_lesson_num}\n";
        }

        // Commerce purchase flag (may be empty for sandbox/admin grants)
        $purchased = get_user_meta($user_id, '_flosc_purchased', true);
        $output .= "  - Purchased meta: " . ($purchased ? 'Yes' : 'No') . "\n";
        $output .= "  - Full entitlement: " . ( $is_member ? 'Yes' : 'No' ) . "\n";

        // Funnel completion
        $funnel_completed = get_user_meta($user_id, '_flosc_funnel_completed', true);
        $output .= "  - Funnel Completed: " . ($funnel_completed ? 'Yes' : 'No') . "\n";

        // Profile status
        $has_profile = $bridge_mgr->flosc_has_profile($user_id);
        $output .= "  - Has Profile: " . ($has_profile ? 'Yes' : 'No') . "\n";

        // Access level label
        $access_level = $is_member ? 'member' : 'guest';
        $output .= "  - Access Level: " . strtoupper($access_level) . "\n";

        return $output;
    }

    /**
     * v1.4.0: Admin introspection help
     */
    private function get_introspection_help() {
        return "🔧 **Admin Introspection Commands**\n\n" .
            "As a WordPress admin, you can ask me about my configuration:\n\n" .
            "**📁 Files:** \"What files do you have access to?\"\n" .
            "**🏷️ Offers:** \"What offers are configured?\"\n" .
            "**🖥️ System:** \"Tell me about yourself\" or \"System status\"\n" .
            "**🔄 Flows:** \"What flows are available?\"\n" .
            "**💳 Providers:** \"What payment providers are configured?\"\n" .
            "**📝 Quizzes:** \"What quiz types are available?\"\n" .
            "**📍 Current:** \"What IVR am I using?\" or \"Current config\"\n" .
            "**👤 Status:** \"What's my user status?\" or \"My account\"\n\n" .
            "_This introspection is only available to WordPress administrators._";
    }
    
    /**
     * v1.4.0: Get admin introspection follow-up prompts
     */
    private function get_admin_introspection_prompts() {
        return [
            ['text' => '📁 Show IVR files', 'input' => 'What files do you have access to?'],
            ['text' => '🏷️ Show offers', 'input' => 'What offers are configured?'],
            ['text' => '🖥️ System status', 'input' => 'System status'],
            ['text' => '👤 My status', 'input' => 'What is my user status?'],
            ['text' => '📍 Current config', 'input' => 'What is the current config?'],
        ];
    }
    
    /**
     * Get access level specific instructions
     */
    private function get_access_level_instructions($access_level) {
        
        $instructions = [
            'visitor' => "
**🚨 CRITICAL ACCESS LEVEL: VISITOR (Not logged in) 🚨**

**YOUR ONLY JOB: GET THEM TO TAKE THE QUIZ**

What you CAN say:
- \"Take our free 2-minute quiz!\"
- \"The quiz will show you exactly where you stand\"
- \"Ready to see what you need to work on?\"
- General statements about the product (without details)

What you ABSOLUTELY CANNOT share:
- ❌ ANY lesson content or detailed material
- ❌ ANY pricing information
- ❌ ANY specific lesson titles or descriptions
- ❌ DO NOT use search tools for visitors
- ❌ DO NOT give away content that members pay for

**STRICT RULE:**
Every response to a visitor MUST redirect to taking the quiz.

Example good response:
\"Great question! Take our free quiz first - it's just 2 minutes and will show you exactly what you need. Ready to start?\"

Example BAD response:
\"Lesson 7 covers...\" ← NO! Don't mention lessons!
\"The IPA is /sɪks/\" ← NO! No IPA ever!
\"It costs \$30...\" ← NO! No pricing!",
            
            'guest' => "
**🚨 ACCESS LEVEL: GUEST (Logged in, not member) 🚨**

**YOUR ONLY JOBS:**
1. Show quiz results
2. Present offers (with urgency)
3. Get them to become members

What you CAN share:
- Their quiz score and which lessons they need
- Lesson TITLES only (not full content)
- Brief one-sentence descriptions of what lessons cover
- Pricing and offers
- Time-limited discount information
- Urgency messaging

What you ABSOLUTELY CANNOT share:
- ❌ Full lesson content
- ❌ Step-by-step guides or detailed instructions
- ❌ Member-only content

**PRICING RULES:**
- Mention the discount price if within the offer timer window
- Mention the regular price for comparison
- Create urgency with the countdown timer

Example good response:
\"Your quiz results show you'd benefit most from Lessons 6 and 7.

🔥 Special Offer available - Ready to unlock these lessons?\"

Example BAD response:
\"Here's the full lesson content...\" ← NO! Content is for members only!",
            
            'member' => "
**✅ ACCESS LEVEL: MEMBER (full access granted)**

This user is a **member**, not a guest and not a visitor.

You can and must share:
- ✅ Full lesson content
- ✅ Complete guides and instructions
- ✅ Step-by-step walkthroughs
- ✅ All member-only content
- ✅ Specific lesson numbers and topics from this flow's library

**NO-EXCUSE POLICY (CRITICAL):**
- Do NOT claim they are a guest or need to upgrade.
- Do NOT say you already answered, cannot find a lesson, or invent barriers.
- Do NOT push free-lesson funnels or purchase offers to members.
- When they ask for a topic, find the matching lesson and help.
- Prefer real WordPress lesson titles/numbers over fabricating content.

**YOUR ROLE:**
You are still a GUIDE. Don't try to teach everything yourself.
- Point them to specific lessons using search tools
- Link to WordPress posts
- Use get_lesson_content to show them what's available
- Celebrate their membership!

Example good response:
\"As a member you have full access. For that topic, start with the matching lesson — want me to open it?\"",

        ];
        
        return $instructions[$access_level] ?? $instructions['visitor'];
    }
    
    /**
     * Call AI with RAG tools (conversation loop).
     * Anthropic only, through wp_ai_client_prompt() + function declarations.
     */
    private function call_ai_with_rag($message, $system_prompt, $tools, $user_context) {
        
        // v1.9.1: Check which provider is configured — this method only supports Anthropic
        $provider = flosc_get_setting('ai_provider', 'ivr');
        if ($provider !== 'anthropic') {
            return "RAG tools require Anthropic as the AI provider. Current provider: {$provider}. Switch to Anthropic in AI Configuration, or use standard chat which works with all providers.";
        }

        // Get AI configuration
        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $api_key = function_exists( 'flosc_get_provider_api_key' ) ? flosc_get_provider_api_key( 'anthropic' ) : flosc_get_setting( 'anthropic_api_key', '' );
        
        if (empty($api_key)) {
            return "Anthropic API key not configured. Add it in FLOSC Settings → AI Configuration.";
        }

        if ( ! class_exists( 'FLOSC_WP_AI_Client' ) || ! FLOSC_WP_AI_Client::is_provider_registered( 'anthropic' ) ) {
            $plugin_name = class_exists( 'FLOSC_WP_AI_Client' ) ? FLOSC_WP_AI_Client::plugin_name( 'anthropic' ) : 'AI Provider for Anthropic';
            $plugin_url  = class_exists( 'FLOSC_WP_AI_Client' ) ? FLOSC_WP_AI_Client::plugin_directory_url( 'anthropic' ) : 'https://wordpress.org/plugins/ai-provider-for-anthropic/';
            return "RAG tools require {$plugin_name}. Install it from {$plugin_url}, then try again.";
        }
        
        $model = flosc_get_setting('ai_anthropic_model', 'claude-sonnet-4-5-20250929');
        $access_level = $user_context['access_level'] ?? 'visitor';

        $result = FLOSC_WP_AI_Client::generate_with_tools(
            array(
                'provider'      => 'anthropic',
                'message'       => $message,
                'system_prompt' => $system_prompt,
                'history'       => array(),
                'model'         => (string) $model,
                'max_tokens'    => 2000,
                'tools'         => is_array( $tools ) ? $tools : array(),
            ),
            function ( $tool_name, $tool_input, $tool_id ) use ( $access_level ) {
                unset( $tool_id );
                return $this->rag_manager->execute_tool(
                    $tool_name,
                    is_array( $tool_input ) ? $tool_input : array(),
                    $access_level
                );
            }
        );

        if ( is_wp_error( $result ) ) {
            if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
                flosc_log( 'FLOSC RAG Error: ' . $result->get_error_message() );
            }
            return "Sorry, I'm having trouble connecting. Please try again.";
        }

        $text = isset( $result['text'] ) ? (string) $result['text'] : '';
        return $text !== '' ? $text : "I encountered an issue processing your request. Please try again.";
    }

    /**
     * Handle quiz submission
     * 
     * v1.0.3: Quiz scoring is handled by quiz-type-factory.
     * Bridge data is automatically created via flosc_quiz_completed hook.
     * This endpoint returns current bridge state for frontend reference.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    // v1.0.5: This endpoint READS bridge data status (not writes)
    // Quiz storage: store_quiz_result() | Processing: handle_process_quiz()
    public function handle_quiz_submission($request) {
        $user_id = get_current_user_id();
        $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
        
        // Get bridge data if user is logged in
        $bridge_data = $user_id ? $bridge_mgr->get_flosc_bridge_data($user_id) : null;
        
        return new WP_REST_Response([
            'success' => true,
            'bridge_data_active' => $user_id ? $bridge_mgr->is_in_flosc_bridge_state($user_id) : false,
            'score' => $bridge_data['score'] ?? 0,
            'percentage' => $bridge_data['percentage'] ?? 0,
            'correct_items' => $bridge_data['correct_items'] ?? [],
            'incorrect_items' => $bridge_data['incorrect_items'] ?? [],
            'weakest_category' => $user_id ? $bridge_mgr->get_flosc_weakest_category($user_id) : null,
        ]);
    }

    /**
     * v9.3.4: Get quiz questions for in-chat quiz
     * 
     * When multiple quizzes are enabled, rotates ABAB pattern
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_quiz_questions($request) {
        $quiz_id = sanitize_text_field($request->get_param('id') ?? 'default');
        $default_text_quiz_id = flosc_get_setting('default_text_quiz_id', 'sample_assessment_quiz');
        
        // v9.3.4: If 'default', rotate through ENABLED quizzes (ABAB pattern)
        // v3.0.2: Use flosc_get_setting to check flow settings first, then global option
        // Resolve 'default' via this flow's enabled quizzes / default_text_quiz_id.
        if ($quiz_id === 'default') {
            // Set flow context from request so flosc_get_setting() finds per-flow settings.
            $req_flow_id  = sanitize_text_field($request->get_param('flow_id') ?? '');
            $req_ivr_file = sanitize_file_name($request->get_param('ivr_file') ?? '');
            if ($req_flow_id) {
                $this->set_flow_context($req_flow_id);
            }

            // No inventing: empty enabled_quizzes = this flow has no quiz.
            $enabled_quizzes = flosc_get_setting('enabled_quizzes', []);
            if (!is_array($enabled_quizzes)) {
                $enabled_quizzes = [];
            }
            $enabled_quizzes = array_values(array_unique(array_filter(array_map(static function ($id) {
                $id = sanitize_key((string) $id);
                return $id !== '' ? FLOSC_Quiz_Registry::resolve_id($id) : '';
            }, $enabled_quizzes))));
            if (empty($enabled_quizzes)) {
                return new WP_Error(
                    'flosc_no_quiz',
                    __('No quiz is enabled for this flow.', 'flosc'),
                    ['status' => 404]
                );
            }

            // Get rotation counter and increment
            $rotation_count = intval(get_option('flosc_quiz_rotation_count', 0));
            update_option('flosc_quiz_rotation_count', $rotation_count + 1);

            // Pick quiz based on rotation (ABAB pattern)
            $quiz_index = $rotation_count % count($enabled_quizzes);
            $quiz_id = $enabled_quizzes[$quiz_index];
        }
        
        // Get the quiz type handler (id must match a registered quiz).
        $quiz_type = FLOSC_Quiz_Registry::get_quiz($quiz_id);
        $resolved_id = $quiz_type ? $quiz_type->get_id() : FLOSC_Quiz_Registry::resolve_id($quiz_id);
        
        if ($quiz_type) {
            // Prefer flow settings (quiz_content_{id}), then global option, then type default.
            $content = flosc_get_setting('quiz_content_' . $resolved_id, '');
            if ($content === '' || $content === null) {
                $content = get_option('flosc_quiz_content_' . $resolved_id, $quiz_type->get_default_content());
            }
            
            // Check if this is a TEXT SEQUENCE quiz (type: 1,2,3...10)
            if ($resolved_id === 'flosc_sample_data_numbers_quiz') {
                // Parse expected values - ensure we have valid content
                $expected = array_filter(array_map('trim', explode(',', $content)), function($v) {
                    return $v !== '';
                });
                // Fallback to default if empty
                if (empty($expected)) {
                    $expected = ['1','2','3','4','5','6','7','8','9','10'];
                }
                // Return text sequence quiz format
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $resolved_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'text_sequence',
                    'prompt' => 'Type the sequence from 1 to 10 (e.g., "1, 2, 3, 4, 5, 6, 7, 8, 9, 10")',
                    'expected' => array_values($expected),
                    'instructions' => $quiz_type->get_instructions(),
                ]);
            }
            
            // Check if this is AUDIO quiz
            if ($resolved_id === 'flosc_sample_audio_quiz') {
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $resolved_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'audio',
                    'prompt' => 'Record yourself saying the sequence from 1 to 10',
                    'expected' => array_map('trim', explode(',', $content)),
                    'instructions' => $quiz_type->get_instructions(),
                ]);
            }
            
            // Check if this is MULTIPLE CHOICE (pipe format)
            if ($resolved_id === 'multiplechoice') {
                // Parse content as JSON or structured format
                $questions = $this->parse_multiplechoice_content($content);
                return new WP_REST_Response([
                    'success' => true,
                    'id' => $resolved_id,
                    'title' => $quiz_type->get_name(),
                    'type' => 'multiple_choice',
                    'questions' => $questions,
                ]);
            }

            // Sample / flow assessment quiz types with block parser + default questions
            if (method_exists($quiz_type, 'parse_content_to_questions')
                && method_exists($quiz_type, 'get_default_questions')) {
                $questions = [];
                // Content key for this quiz id only: quiz_content_{id}
                $saved_content = $content;
                if (($saved_content === '' || $saved_content === null) && $resolved_id !== '') {
                    $saved_content = flosc_get_setting('quiz_content_' . $resolved_id, '');
                }
                if (is_string($saved_content) && $saved_content !== '') {
                    $questions = $quiz_type->parse_content_to_questions($saved_content);
                }
                if (empty($questions)) {
                    $questions = $quiz_type->get_default_questions();
                }
                return new WP_REST_Response([
                    'success'   => true,
                    'id'        => $resolved_id,
                    'title'     => $quiz_type->get_name(),
                    'type'      => 'multiple_choice',
                    'questions' => $questions,
                ]);
            }
        }
        
        // Fallback: return sample assessment quiz
        $sample_questions = [
            [
                'id' => 'q1',
                'text' => 'How would you rate your current skill level?',
                'options' => [
                    ['key' => 'A', 'text' => 'Complete beginner'],
                    ['key' => 'B', 'text' => 'Some basics'],
                    ['key' => 'C', 'text' => 'Intermediate'],
                    ['key' => 'D', 'text' => 'Advanced'],
                ],
                'correct' => null,
            ],
            [
                'id' => 'q2',
                'text' => 'How much time can you dedicate to practice each week?',
                'options' => [
                    ['key' => 'A', 'text' => 'Less than 1 hour'],
                    ['key' => 'B', 'text' => '1-3 hours'],
                    ['key' => 'C', 'text' => '3-5 hours'],
                    ['key' => 'D', 'text' => 'More than 5 hours'],
                ],
                'correct' => null,
            ],
            [
                'id' => 'q3',
                'text' => 'What is your primary goal?',
                'options' => [
                    ['key' => 'A', 'text' => 'Personal improvement'],
                    ['key' => 'B', 'text' => 'Professional development'],
                    ['key' => 'C', 'text' => 'Academic requirements'],
                    ['key' => 'D', 'text' => 'Just curious to learn'],
                ],
                'correct' => null,
            ],
        ];
        
        return new WP_REST_Response([
            'success' => true,
            'id' => 'sample',
            'title' => 'Quick Assessment',
            'type' => 'multiple_choice',
            'questions' => $sample_questions,
        ]);
    }
    
    /**
     * v9.3.4: Parse multiple choice content from admin textarea
     */
    private function parse_multiplechoice_content($content) {
        // Simple format: Question?|A:Answer1|B:Answer2|C:Answer3|correct:A
        $questions = [];
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $parts = explode('|', $line);
            if (count($parts) < 3) continue;
            
            $question = [
                'id' => 'q' . (count($questions) + 1),
                'text' => trim($parts[0]),
                'options' => [],
                'correct' => null,
            ];
            
            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);
                if (strpos($part, 'correct:') === 0) {
                    $question['correct'] = substr($part, 8);
                } elseif (preg_match('/^([A-D]):(.+)$/', $part, $m)) {
                    $question['options'][] = ['key' => $m[1], 'text' => trim($m[2])];
                }
            }
            
            if (!empty($question['options'])) {
                $questions[] = $question;
            }
        }
        
        return $questions;
    }

    /**
     * v9.3.2: Store quiz result
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function store_quiz_result($request) {
        $quiz_id = sanitize_text_field($request->get_param('id') ?? 'unknown');
        $score = intval($request->get_param('score') ?? 0);
        $answers = $request->get_param('answers') ?? [];
        $completed_at = intval($request->get_param('completedAt') ?? time() * 1000);
        $duration = intval($request->get_param('duration') ?? 0);
        
        // v1.0.7 TASK-603: Store in signed cookie for visitors (not PHP session - avoids "headers sent" errors)
        if (!is_user_logged_in()) {
            $quiz_data = [
                'quiz_id' => $quiz_id,
                'score' => $score,
                'answers' => $answers,
                'completed_at' => $completed_at,
                'duration' => $duration,
            ];
            $this->set_signed_cookie('flosc_quiz_result', $quiz_data, HOUR_IN_SECONDS);
        }
        
        // If user is logged in, store in user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            
            // v1.0.4: TASK-013 - Consolidated quiz storage with underscore prefix
            update_user_meta($user_id, '_flosc_last_quiz_id', $quiz_id);
            update_user_meta($user_id, '_flosc_last_quiz_score', $score); // Canonical score location
            update_user_meta($user_id, '_flosc_quiz_completed_at', $completed_at);
            update_user_meta($user_id, '_flosc_quiz_answers_' . $quiz_id, $answers);
            
            // Add to completed quizzes array
            $completed = get_user_meta($user_id, '_flosc_completed_quizzes', true) ?: [];
            if (!in_array($quiz_id, $completed)) {
                $completed[] = $quiz_id;
                update_user_meta($user_id, '_flosc_completed_quizzes', $completed);
            }
            
            // v1.0.5 TASK-101/102: Build quiz result and fire flosc_quiz_completed action
            // This triggers both Bridge Data Manager AND Free Lesson Manager
            // v3.0.0: quiz_id is critical — Free Lesson Manager uses it to resolve
            // the correct lesson category via the flow's content_item_groups config.
            $quiz_result = [
                'quiz_id' => $quiz_id,
                'score' => $score,
                'user_answer' => is_array($answers) ? implode(',', $answers) : $answers,
                'correct_answer' => '1,2,3,4,5,6,7,8,9,10', // Default for sample quizzes; quiz types override via incorrect/missed
                'correct' => [],
                'incorrect' => [],
                'completed_at' => $completed_at,
            ];
            
            // Parse answers to determine correct/incorrect
            // NOTE: This is a generic fallback. Quiz types with their own grade() method
            // produce structured incorrect/missed arrays that the Free Lesson Manager
            // checks first (see get_missed_lessons() in class-free-content-item-manager.php).
            $user_nums = array_filter(array_map('trim', is_array($answers) ? $answers : explode(',', $answers)), 'is_numeric');
            $expected_nums = ['1','2','3','4','5','6','7','8','9','10'];
            foreach ($expected_nums as $num) {
                if (in_array($num, $user_nums)) {
                    $quiz_result['correct'][] = $num;
                } else {
                    $quiz_result['incorrect'][] = $num;
                }
            }
            
            // Fire the action — this triggers:
            // 1. FLOSC_Bridge_Data_Manager::handle_quiz_completion() — creates bridge data
            // 2. FLOSC_Free_Content_Item_Manager::handle_quiz_completion() — offers free lesson if score < 100
            //    v3.0.0: Uses quiz_id to resolve category from content_item_groups
            do_action('flosc_quiz_completed', $quiz_result, $user_id);
            
            // Set justCompletedQuiz transient for IVR
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);
            
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v1.0.7: Quiz stored for user {$user_id}, score {$score}%, fired flosc_quiz_completed");
        }
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Quiz result stored',
            'stored_for_user' => is_user_logged_in(),
            'score' => $score,
        ]);
    }
    
    /**
     * Find matching IVR response based on phase and context
     */
    private function find_ivr_response($phase, $user_message, $context, $ivr_config) {
        // v1.0.8: Get messages for the phase using correct config structure
        // Config structure: { 'messages' => {...}, 'phases' => { 'freeline' => [...], ... } }
        $all_messages = $ivr_config['messages'] ?? [];
        $phase_message_names = $ivr_config['phases'][$phase] ?? [];
        
        // Build phase messages array from names
        $phase_messages = [];
        foreach ($phase_message_names as $msg_name) {
            if (isset($all_messages[$msg_name])) {
                $phase_messages[] = $all_messages[$msg_name];
            }
        }
        
        $match = $this->search_ivr_match($phase_messages, $user_message, $context);
        if ($match) {
            return $match;
        }
        
        // v1.0.8: If not found in current phase, check freeline phase for 'always' condition messages
        // This ensures global input-output pairs (like "Are you there?") work across all phases
        if ($phase !== 'freeline') {
            $freeline_message_names = $ivr_config['phases']['freeline'] ?? [];
            $freeline_messages = [];
            foreach ($freeline_message_names as $msg_name) {
                if (isset($all_messages[$msg_name])) {
                    $freeline_messages[] = $all_messages[$msg_name];
                }
            }
            
            $match = $this->search_ivr_match($freeline_messages, $user_message, $context, true);
            if ($match) {
                return $match;
            }
        }
        
        // v1.9.0: No IVR match — return null so AI fallback path activates in handle_chat()
        // When ai_provider is 'ivr', handle_chat() will use get_phase_default_response() as last resort
        return null;
    }
    
    /**
     * v1.0.9: Substitute variables in message content
     */
    private function substitute_ivr_variables($content, $context) {
        $identity = $this->get_floscflow_identity();
        $public_title = trim((string) ($identity['title'] ?? ''));
        $public_tagline = trim((string) ($identity['tagline'] ?? ''));
        $assistant_name = function_exists('flosc_personality_name')
            ? flosc_personality_name()
            : ( function_exists('flosc_visitor_assistant_name') ? flosc_visitor_assistant_name() : '' );
        if ($assistant_name === '') {
            $assistant_name = 'our course';
        }

        $replacements = [
            '{name}' => $context['user_name'] ?? 'there',
            '{score}' => $context['quiz_score'] ?? '0',
            '{correct_items}' => $context['correct_items'] ?? '',
            '{missed_items}' => $context['missed_items'] ?? '',
            '{product_name}' => $assistant_name,
            '{title}' => $public_title !== '' ? $public_title : $assistant_name,
            '{tagline}' => $public_tagline,
            '{price}' => get_option('flosc_main_price', '$100'),
            '{discount_price}' => get_option('flosc_discount_price', '$25'),
            '{timer_remaining}' => $context['timer_remaining'] ?? '60 minutes',
            '{customer_count}' => get_option('flosc_customer_count', '1,000'),
            '{lessons_completed}' => $context['lessons_completed'] ?? '0',
        ];
        
        // v1.0.9: Special handling for {user_status_response}
        if (strpos($content, '{user_status_response}') !== false) {
            $replacements['{user_status_response}'] = $this->generate_user_status_response($context);
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * Fill a flow-configured status template.
     * Placeholders: {first_name}, {product_name}, {member_level}, {name}, {email}
     *
     * @param string $template
     * @param array  $vars
     * @return string
     */
    private function flosc_fill_status_template($template, array $vars) {
        $out = (string) $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', (string) $value, $out);
        }
        return $out;
    }

    /**
     * Build per-flow status rows for the logged-in user.
     */
    private function flosc_build_user_flow_statuses($user_id, $current_stem = '') {
        $user_id = absint($user_id);
        if ($user_id <= 0) {
            return [];
        }

        $current_stem = $this->flosc_normalize_flow_stem($current_stem);
        $registration_stem = $this->flosc_normalize_flow_stem((string) get_user_meta($user_id, '_flosc_registration_flow', true));

        $flows = [];
        if (function_exists('flosc_flows') && is_object(flosc_flows()) && method_exists(flosc_flows(), 'get_all_flows')) {
            $flows = flosc_flows()->get_all_flows();
        }
        if (!is_array($flows)) {
            $flows = [];
        }

        $rows = [];
        foreach ($flows as $flow_key => $flow) {
            if (!is_array($flow)) {
                continue;
            }

            $flow_id = sanitize_key((string) ($flow['id'] ?? (is_string($flow_key) ? $flow_key : '')));
            $flow_stem = $this->flosc_normalize_flow_stem((string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow_id));
            if ($flow_stem === '' || $flow_stem === 'default') {
                continue;
            }

            $state = 'guest';
            if ($this->sale_manager && method_exists($this->sale_manager, 'access')) {
                $state = (string) $this->sale_manager->access()->get_simple_state($user_id, $flow_stem);
            }
            if ($state !== 'member' && $state !== 'guest') {
                $state = 'guest';
            }

            $tokens = max(0, intval($this->flosc_get_user_flow_token_balance($user_id, $flow_stem)));
            $is_current = ($current_stem !== '' && $flow_stem === $current_stem);
            $include = $is_current || $state === 'member' || $tokens > 0 || ($registration_stem !== '' && $registration_stem === $flow_stem);
            if (!$include) {
                continue;
            }

            $name = trim((string) ($flow['name'] ?? ''));
            if ($name === '') {
                $identity = is_array($flow['identity'] ?? null) ? $flow['identity'] : [];
                $name = trim((string) ($identity['name'] ?? ''));
            }
            if ($name === '') {
                $bag = get_option('flosc_flow_' . $flow_stem, []);
                if (is_array($bag)) {
                    $name = trim((string) ($bag['name'] ?? $bag['product_name'] ?? ''));
                    if ($name === '' && is_array($bag['identity'] ?? null)) {
                        $name = trim((string) ($bag['identity']['name'] ?? ''));
                    }
                }
            }
            if ($name === '') {
                $name = strtoupper($flow_stem);
            }

            $rows[] = [
                'id' => $flow_id !== '' ? $flow_id : $flow_stem,
                'stem' => $flow_stem,
                'name' => $name,
                'state' => $state,
                'tokens' => $tokens,
                'current' => $is_current,
            ];
        }

        usort($rows, function ($a, $b) {
            $ac = !empty($a['current']) ? 1 : 0;
            $bc = !empty($b['current']) ? 1 : 0;
            if ($ac !== $bc) {
                return ($ac > $bc) ? -1 : 1;
            }
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * User status text from real flow state and token data.
     */
    private function generate_user_status_response($context) {
        if (!is_user_logged_in()) {
            return 'Here is your user status in this flosc ecosystem.';
        }

        $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options')) {
            return "Here is your user status in this flosc ecosystem.\n\nYou are the FLOSC Admin.";
        }

        $flow = $this->get_current_flow();
        $stem = '';
        if (is_array($flow)) {
            $stem = $this->flosc_normalize_flow_stem((string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? ''));
        }

        $rows = $this->flosc_build_user_flow_statuses($user_id, $stem);
        if (empty($rows)) {
            return "Here is your user status in this flosc ecosystem.\n\nCurrent floscFlow, you are a Guest with 0 tokens.";
        }

        $state_label = static function ($state) {
            $state = strtolower((string) $state);
            if ($state === 'member') {
                return 'Member';
            }
            if ($state === 'guest') {
                return 'Guest';
            }
            return 'Visitor';
        };

        $fmt_tokens = static function ($tokens) {
            return number_format_i18n(max(0, intval($tokens)));
        };

        $current = $rows[0];
        foreach ($rows as $row) {
            if (!empty($row['current'])) {
                $current = $row;
                break;
            }
        }

        $lines = [];
        $lines[] = 'Here is your user status in this flosc ecosystem.';
        $lines[] = '';
        $lines[] = sprintf(
            'Current floscFlow %1$s, you are a %2$s with %3$s tokens.',
            (string) ($current['name'] ?? ''),
            $state_label($current['state'] ?? ''),
            $fmt_tokens($current['tokens'] ?? 0)
        );

        foreach ($rows as $row) {
            if (!empty($row['current'])) {
                continue;
            }
            $lines[] = sprintf(
                '%1$s, you are a %2$s with %3$s tokens.',
                (string) ($row['name'] ?? ''),
                $state_label($row['state'] ?? ''),
                $fmt_tokens($row['tokens'] ?? 0)
            );
        }

        return implode("\n", $lines);
    }
    
    /**
     * v1.0.8: Search for matching user_input in a message list
     * v1.0.9: Added variable substitution for dynamic content
     * v1.6.3: Added keyword-based fuzzy fallback when exact match fails
     */
    private function search_ivr_match($messages, $user_message, $context, $only_always = false) {
        // Pass 1: Exact match (original behavior)
        foreach ($messages as $msg) {
            // v1.6.2: Match suggested_user_autoprompt AND offer-type messages with user_input
            $matchable_types = ['suggested_user_autoprompt', 'offer'];
            if (!isset($msg['type']) || !in_array($msg['type'], $matchable_types, true)) {
                continue;
            }
            
            if (!isset($msg['user_input']) || strtolower($msg['user_input']) !== strtolower($user_message)) {
                continue;
            }
            
            if ($only_always) {
                $conditions = $msg['conditions'] ?? 'always';
                if ($conditions !== 'always') {
                    continue;
                }
            }
            
            if (isset($msg['conditions']) && $msg['conditions'] !== 'always') {
                $evaluator = new FLOSC_Condition_Evaluator($context);
                if (!$evaluator->evaluate($msg['conditions'])) {
                    continue;
                }
            }
            
            // v1.0.9: Return content with variable substitution
            if (!empty($msg['content'])) {
                return [
                    'content' => $this->substitute_ivr_variables($msg['content'], $context),
                    'user_autoprompts' => $msg['user_autoprompts'] ?? [],
                    'phase_change' => $msg['phase_change'] ?? null,
                ];
            }
        }
        
        // Pass 2: Keyword fuzzy match (v1.6.3)
        // Normalize user input: lowercase, strip punctuation, split into words
        $input_normalized = strtolower(preg_replace('/[^\w\s]/', '', $user_message));
        $input_words = array_filter(preg_split('/\s+/', $input_normalized));
        
        // Skip fuzzy match for very short inputs (1 word or less)
        if (count($input_words) < 2) {
            return null;
        }
        
        // Common stop words to ignore in matching
        $stop_words = ['i', 'me', 'my', 'the', 'a', 'an', 'is', 'are', 'was', 'do', 'does', 'did', 'can', 'to', 'for', 'of', 'in', 'on', 'it', 'and', 'or', 'but', 'not', 'this', 'that', 'with', 'have', 'has', 'what', 'how', 'please', 'want', 'would', 'like', 'just', 'about'];
        $input_meaningful = array_diff($input_words, $stop_words);
        
        if (empty($input_meaningful)) {
            return null;
        }
        
        $best_match = null;
        $best_score = 0;
        
        foreach ($messages as $msg) {
            $matchable_types = ['suggested_user_autoprompt', 'offer'];
            if (!isset($msg['type']) || !in_array($msg['type'], $matchable_types, true)) {
                continue;
            }
            
            if ($only_always) {
                $conditions = $msg['conditions'] ?? 'always';
                if ($conditions !== 'always') {
                    continue;
                }
            }
            
            if (isset($msg['conditions']) && $msg['conditions'] !== 'always') {
                $evaluator = new FLOSC_Condition_Evaluator($context);
                if (!$evaluator->evaluate($msg['conditions'])) {
                    continue;
                }
            }
            
            if (empty($msg['content'])) {
                continue;
            }
            
            // Build keyword pool: explicit Keywords field + words from user_input
            $keyword_pool = [];
            
            // Explicit keywords (comma-separated in IVR config)
            if (!empty($msg['keywords'])) {
                $explicit_keywords = array_map('trim', explode(',', strtolower($msg['keywords'])));
                $keyword_pool = array_merge($keyword_pool, $explicit_keywords);
            }
            
            // Words from the user_input field itself
            if (!empty($msg['user_input'])) {
                $ui_words = array_filter(preg_split('/\s+/', strtolower(preg_replace('/[^\w\s]/', '', $msg['user_input']))));
                $ui_meaningful = array_diff($ui_words, $stop_words);
                $keyword_pool = array_merge($keyword_pool, $ui_meaningful);
            }
            
            if (empty($keyword_pool)) {
                continue;
            }
            
            $keyword_pool = array_unique($keyword_pool);
            
            // Score: count how many user words match keywords (including partial/stem matches)
            $score = 0;
            foreach ($input_meaningful as $word) {
                foreach ($keyword_pool as $keyword) {
                    // Exact word match
                    if ($word === $keyword) {
                        $score += 2;
                        break;
                    }
                    // Stem match: user word starts with keyword or keyword starts with user word (min 4 chars)
                    if (strlen($word) >= 4 && strlen($keyword) >= 4) {
                        if (strpos($word, $keyword) === 0 || strpos($keyword, $word) === 0) {
                            $score += 1;
                            break;
                        }
                    }
                }
            }
            
            // v1.9.2: Require score proportional to message length to prevent over-matching.
            // Short messages (2-3 meaningful words): need >= 3 points
            // Medium messages (4-6 words): need >= 4 points
            // Long messages (7+ words): need >= 5 points
            // This prevents open-ended questions from fuzzy-matching IVR entries.
            $input_word_count = count($input_meaningful);
            $min_score = 3; // base minimum (raised from 2)
            if ($input_word_count >= 4) {
                $min_score = 4;
            }
            if ($input_word_count >= 7) {
                $min_score = 5;
            }
            
            if ($score > $best_score && $score >= $min_score) {
                $best_score = $score;
                $best_match = $msg;
            }
        }
        
        if ($best_match) {
            return [
                'content' => $this->substitute_ivr_variables($best_match['content'], $context),
                'user_autoprompts' => $best_match['user_autoprompts'] ?? [],
                'phase_change' => $best_match['phase_change'] ?? null,
            ];
        }
        
        return null;
    }
    
    /**
     * Find a message by its name in the IVR config
     */
    private function find_message_by_name($message_name, $phase, $ivr_config) {
        if (empty($message_name)) {
            return null;
        }
        
        $phase_messages = $ivr_config[$phase] ?? [];
        
        foreach ($phase_messages as $msg) {
            if (($msg['name'] ?? '') === $message_name) {
                return [
                    'content' => $msg['content'] ?? '',
                    'user_autoprompts' => $msg['user_autoprompts'] ?? [],
                    'phase_change' => $msg['phase_change'] ?? null,
                ];
            }
        }
        
        return null;
    }

    /**
     * v5.0.2: Build a useful fallback when AI fails on quiz-related questions.
     * Returns null if the message isn't quiz-related.
     */
    private function build_quiz_fallback_response($message, $eval_context) {
        $lower = strtolower($message);
        $is_quiz_question = (
            strpos($lower, 'quiz') !== false ||
            strpos($lower, 'miss') !== false ||
            strpos($lower, 'score') !== false ||
            strpos($lower, 'topic') !== false ||
            strpos($lower, 'results') !== false
        );
        if (!$is_quiz_question) return null;

        $quiz_taken = $eval_context['quiz_taken'] ?? false;
        if (!$quiz_taken) {
            return "It looks like you haven't taken the quiz yet! Take the quiz first, and I'll be able to tell you exactly which topics to focus on.";
        }

        $score = $eval_context['score'] ?? $eval_context['quiz_score'] ?? '?';
        $response = "Based on your quiz, you scored **{$score}%**.\n\n";

        // Try bridge data for logged-in users
        $incorrect = [];
        if (!empty($eval_context['user_id']) && is_user_logged_in()) {
            $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
            $bridge_data = $bridge_mgr->get_flosc_bridge_data($eval_context['user_id']);
            if ($bridge_data) {
                $incorrect = $bridge_data['incorrect_items'] ?? [];
            }
        }

        // Fallback to frontend context
        if (empty($incorrect)) {
            $incorrect = $eval_context['incorrect_items'] ?? $eval_context['incorrectItems'] ?? [];
        }

        if (!empty($incorrect) && is_array($incorrect)) {
            // incorrect_items may be plain labels/ids or structured rows
            $labels = [];
            foreach (array_slice($incorrect, 0, 10) as $item) {
                if (is_array($item)) {
                    $labels[] = $item['topics'][0] ?? $item['question_id'] ?? $item['question'] ?? 'topic';
                } else {
                    $labels[] = (string) $item;
                }
            }
            $response .= "Here are the topics to work on: **" . implode(', ', $labels) . "**.\n\n";
            $response .= "Member content covers each of these in more depth. Would you like to try a free lesson (if one is available for your flow)?";
        } else {
            $response .= "Member content covers the topics from this assessment in more depth. Would you like to try a free lesson (if one is available for your flow)?";
        }

        return $response;
    }

    /**
     * Get default response for a phase
     */
    private function get_phase_default_response($phase, $context) {
        // Phase defaults for IVR-only mode (no AI configured / no keyword match).
        // Keep product-agnostic: no hard-coded lessons/quiz spam for every flow.
        // Guest phase is 'login' but user IS logged in — message must reflect that.
        $name = $context['user_name'] ?? $context['name'] ?? 'there';
        // Reminder when free-form hits button/keyword IVR only (no model).
        $ai_hint = ' This is just IVR-style copy — remember to configure your preferred AI API for much more intelligent responses!';
        $responses = [
            'freeline' => 'Thanks for your interest! Try one of the suggestions above.' . $ai_hint,
            'login' => "Hey {$name}! I work best with the suggestion buttons above. Try tapping one to continue." . $ai_hint,
            'offer' => 'Would you like to learn more about our offer? Try the suggestions above!' . $ai_hint,
            'sale' => 'Ready to take the next step? Check out the options above.' . $ai_hint,
            'content' => 'Welcome back! Use the suggestions above to continue.' . $ai_hint,
        ];

        return $responses[$phase] ?? ('Try one of the suggestions above.' . $ai_hint);
    }
    
    /**
     * Get user autoprompts for a phase
     */
    /**
     * v1.9.2: Build enriched AI context from eval_context + backend data.
     * Gives the AI full awareness of who the user is, where they are in the FLOSC
     * journey, their quiz results, bridge data, and product context — without
     * leaking sensitive data (no API keys, payment details, or PII beyond display name).
     *
     * Previously, ai_context was anemic: { phase, logged_in, is_admin, user_name, message_count }.
     * The AI was chatting blind about who the user is and where they are.
     */
    private function build_enriched_ai_context($phase, $eval_context, $flow_id = '', $ivr_guidance = '') {
        $user_id = $eval_context['user_id'] ?? 0;

        // FLOSC Identity — tell the AI what system it's part of
        $identity = $this->get_floscflow_identity();
        $ai_context = [
            'flosc_version' => FLOSC_VERSION,
            'flow_id' => $flow_id,
            'product_name' => trim((string) ($identity['title'] ?? '')),
            'public_title' => trim((string) ($identity['title'] ?? '')),
            'public_tagline' => trim((string) ($identity['tagline'] ?? '')),
        ];

        // User Identity
        $ai_context['logged_in'] = $eval_context['logged_in'] ?? false;
        $ai_context['is_admin'] = $eval_context['is_admin'] ?? false;
        $ai_context['user_name'] = $eval_context['user_name'] ?? 'there';
        $ai_context['access_level'] = $eval_context['access_level'] ?? 'visitor';
        $ai_context['message_count'] = $eval_context['message_count'] ?? 0;

        // v1.9.2: Enrich admin context — give AI factual data so it doesn't hallucinate
        if ($ai_context['is_admin'] && $user_id) {
            // Admin transcends the funnel — use backend phase determination, not frontend's
            $backend_phase = $this->determine_flosc_phase();
            $ai_context['phase'] = 'admin (backend: ' . $backend_phase . ', frontend sent: ' . $phase . ')';
            $ai_context['admin_note'] = 'Admin users are not regular funnel participants. They have full access to all phases, lessons, and configuration. Do not treat them as visitors or guide them through the funnel.';

            $admin_user = get_userdata($user_id);
            if ($admin_user) {
                $ai_context['wp_user_id'] = $user_id;
                $ai_context['wp_roles'] = implode(', ', $admin_user->roles);
                $ai_context['wp_email'] = $admin_user->user_email;
                $ai_context['wp_display_name'] = $admin_user->display_name;
            }
        } else {
            $ai_context['phase'] = $phase;
        }

        // Quiz & Bridge Data (for logged-in users)
        if ($user_id && is_user_logged_in()) {
            $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
            $bridge_data = $bridge_mgr->get_flosc_bridge_data($user_id);

            if ($bridge_data) {
                $ai_context['quiz_taken'] = true;
                $ai_context['quiz_score'] = ($bridge_data['score'] ?? 0) . '%';
                $correct = $bridge_data['correct_items'] ?? [];
                $incorrect = $bridge_data['incorrect_items'] ?? [];
                $ai_context['quiz_correct_count'] = count($correct);
                $ai_context['quiz_incorrect_count'] = count($incorrect);
                // Send item names (not IDs) so AI can reference them naturally
                if (!empty($incorrect)) {
                    $ai_context['quiz_missed_items'] = implode(', ', array_slice($incorrect, 0, 10));
                }
                if (!empty($correct)) {
                    $ai_context['quiz_correct_items'] = implode(', ', array_slice($correct, 0, 10));
                }
                $weakest = $bridge_mgr->get_flosc_weakest_category($user_id);
                if ($weakest) {
                    $ai_context['weakest_category'] = $weakest;
                }
            } else {
                // Fallback to legacy user meta
                $legacy_score = get_user_meta($user_id, '_flosc_last_quiz_score', true);
                $ai_context['quiz_taken'] = !empty($legacy_score);
                if ($legacy_score) {
                    $ai_context['quiz_score'] = $legacy_score . '%';
                }
            }

            // Progress & Access Data — authoritative membership (same stack as get_simple_state).
            $ai_context['has_profile'] = $bridge_mgr->flosc_has_profile($user_id);
            $ai_context['free_lesson_delivered'] = (bool) get_user_meta($user_id, '_flosc_free_content_item_delivered', true);

            $is_member = false;
            if ( isset( $eval_context['is_member'] ) ) {
                $is_member = (bool) $eval_context['is_member'];
            } elseif ( $this->sale_manager && method_exists( $this->sale_manager, 'access' ) ) {
                $ai_flow = !empty($eval_context['flow_id']) ? $eval_context['flow_id'] : '';
                $is_member = ( $this->sale_manager->access()->get_simple_state( $user_id, $ai_flow ) === 'member' );
            } elseif ( $this->member_access && method_exists( $this->member_access, 'is_member' ) ) {
                $is_member = (bool) $this->member_access->is_member( $user_id );
            }

            $ai_context['is_member']    = $is_member;
            $ai_context['access_level'] = $is_member
                ? 'member'
                : ( ( $eval_context['access_level'] ?? '' ) === 'visitor' ? 'visitor' : 'guest' );
            // For AI prompts: purchased means "has full member entitlement".
            // Commerce-only _flosc_purchased stays on FLOSC_USER for "thanks for buying" IVR.
            $ai_context['purchased'] = $is_member;
            if ( $is_member ) {
                $ai_context['member_entitlement'] = 'Member (full member access)';
            }
        } else {
            // Visitor — check eval_context for pre-login quiz data
            $ai_context['quiz_taken'] = (bool) ($eval_context['quiz_taken'] ?? false);
            if (!empty($eval_context['score'])) {
                $ai_context['quiz_score'] = $eval_context['score'] . '%';
            }
            $ai_context['is_member']    = false;
            $ai_context['access_level'] = 'visitor';
            $ai_context['purchased']    = false;
        }

        // IVR guidance (if IVR matched a scripted response)
        if (!empty($ivr_guidance)) {
            $ai_context['ivr_guidance'] = $ivr_guidance;
        }

        return $ai_context;
    }

    /**
     * v3.0.5: Match user message against offer reveal phrases (exact match only).
     * Returns the matched offer array, or null if no match.
     * AI interpretation matching is handled by injecting phrases into the AI system prompt.
     */
    private function match_offer_reveal_phrase($message, $flow_id = null) {
        $normalized = strtolower(trim($message));
        if (empty($normalized)) return null;

        $offers = $this->sale_manager->get_available_offers(
            is_user_logged_in() ? get_current_user_id() : null,
            $flow_id
        );

        foreach ($offers as $offer) {
            if (empty($offer['reveal_phrase'])) continue;
            // Only match "exact" type server-side; AI interpretation goes through AI prompt
            $match_type = $offer['match_type'] ?? 'exact';
            if ($match_type !== 'exact') continue;
            if (($offer['status'] ?? 'active') !== 'active') continue;

            $phrase = strtolower(trim($offer['reveal_phrase']));
            if ($phrase === $normalized) {
                return $offer;
            }
        }
        return null;
    }

    /**
     * v3.0.5: Get active offers that use AI interpretation matching.
     * Used by the AI chat dispatch to inject offer phrases into the system prompt.
     */
    public function get_ai_interpretation_offers($flow_id = null) {
        $offers = $this->sale_manager->get_available_offers(
            is_user_logged_in() ? get_current_user_id() : null,
            $flow_id
        );
        $ai_offers = [];
        foreach ($offers as $offer) {
            if (empty($offer['reveal_phrase'])) continue;
            if (($offer['match_type'] ?? 'exact') !== 'ai_interpretation') continue;
            if (($offer['status'] ?? 'active') !== 'active') continue;
            $ai_offers[] = $offer;
        }
        return $ai_offers;
    }

    private function get_user_autoprompts_for_phase($phase, $context, $ivr_config) {
        // v1.0.8: Use correct config structure
        $all_messages = $ivr_config['messages'] ?? [];
        $phase_message_names = $ivr_config['phases'][$phase] ?? [];
        $replies = [];
        
        foreach ($phase_message_names as $msg_name) {
            $msg = $all_messages[$msg_name] ?? null;
            if (!$msg) continue;
            
            if (isset($msg['type']) && $msg['type'] === 'suggested_user_autoprompt') {
                // Check conditions if present
                if (isset($msg['conditions']) && $msg['conditions'] !== 'always') {
                    $evaluator = new FLOSC_Condition_Evaluator($context);
                    if (!$evaluator->evaluate($msg['conditions'])) {
                        continue;
                    }
                }
                
                $replies[] = [
                    'text' => $msg['user_input'] ?? '',
                    'icon' => $msg['icon'] ?? '💬',
                ];
            }
        }
        
        return $replies;
    }

    public function handle_ai_query($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $context = $request->get_param('context') ?? [];
        $flow_id = sanitize_key((string) ($request->get_param('flow_id') ?? ($context['flow_id'] ?? '')));
        
        if (empty($message)) {
            return new WP_Error('empty_message', __('Message is required', 'flosc'), ['status' => 400]);
        }

        $flow_token_enforced = $this->flosc_is_flow_chat_token_enforced($flow_id);
        
        // Check usage limits if user is logged in
        $needs_token_charge = false;
        $token_provider = null;
        $user_id = 0;
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $usage = $this->sale_manager->usage();

            // Check if user has quota (or paid access)
            if (!$usage->has_quota($user_id, 'ai_queries')) {
                // Check if they can pay with tokens
                $token_provider = $this->sale_manager->get_provider('tokens');
                if (!$flow_token_enforced) {
                    $token_provider = null;
                    $needs_token_charge = false;
                }
                $user_balance = $this->flosc_get_user_flow_token_balance($user_id, $flow_id);
                $min_cost = max(0, intval($this->flosc_get_ai_query_token_cost($flow_id, $token_provider)));
                if (!$token_provider || ($min_cost > 0 && $user_balance < $min_cost)) {
                    return new WP_Error('limit_reached', __('AI query limit reached. Upgrade for more!', 'flosc'), ['status' => 403]);
                }
                $needs_token_charge = true;
            }

            // Track usage
            $usage->track($user_id, 'ai_queries');
        }

        // v04_04: Build context for phase-aware AI
        $ai_context = $this->build_ai_context($context);

        // v8.0.1: Fail closed — never serve unpersonified answers. An empty
        // personality profile is misconfiguration, not a content fallback;
        // admins see the error on the AI tab until it is fixed.
        $flosc_persona_profile = function_exists( 'flosc_personality_compiled_profile' )
            ? flosc_personality_compiled_profile()
            : '';
        if ( trim( (string) $flosc_persona_profile ) === '' ) {
            if ( defined( 'FLOSC_DEBUG' ) && FLOSC_DEBUG ) {
                flosc_log( 'FLOSC: flow "' . ( $flow_id ?? 'current' ) . '" refused an AI reply — no personality profile is configured.' );
            }
            return new WP_Error(
                'persona_missing',
                __( 'This assistant is unavailable.', 'flosc' ),
                array( 'status' => 503 )
            );
        }

        // v04_04: Build system prompt (base + phase-specific + context)
        $phase = $ai_context['phase'] ?? '';
        $system_prompt = $this->ai_chat_dispatch->build_system_prompt($phase, $ai_context);

        $response = $this->ai_chat_dispatch->get_response($message, $system_prompt, $context);

        if ($needs_token_charge && $token_provider) {
            $billing_meta = method_exists($this->ai_chat_dispatch, 'get_last_billing_meta')
                ? (array) $this->ai_chat_dispatch->get_last_billing_meta()
                : [];

            $charge_meta = [
                'billing' => $billing_meta,
            ];

            $real_millicents = intval($billing_meta['real_millicents'] ?? 0);
            if ($real_millicents > 0) {
                $charge_meta['real_millicents'] = $real_millicents;
            }

            $charged = $this->flosc_charge_user_flow_tokens($user_id, $flow_id, $token_provider, $billing_meta);
            if (!is_array($charged) || empty($charged['charged'])) {
                return new WP_Error('limit_reached', __('AI query limit reached. Upgrade for more!', 'flosc'), ['status' => 403]);
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'response' => $response,
        ]);
    }
    
    public function handle_process_audio($request) {
        $files = $request->get_file_params();

        if (empty($files['audio'])) {
            return new WP_Error('no_audio', __('No audio file provided', 'flosc'), ['status' => 400]);
        }

        // Track STT usage
        if (is_user_logged_in()) {
            $this->sale_manager->usage()->track(get_current_user_id(), 'stt_minutes', 1);
        }

        $transcript = $this->stt_dispatch->transcribe($files['audio']['tmp_name']);

        if (is_wp_error($transcript)) {
            return $transcript;
        }

        $quiz_id   = sanitize_text_field( $request->get_param( 'quiz_id' ) );
        $quiz_type = $quiz_id ? FLOSC_Quiz_Registry::get_quiz( $quiz_id ) : null;

        if ( ! $quiz_type ) {
            return new WP_REST_Response([
                'success'    => true,
                'transcript' => $transcript,
                'analysis'   => null,
            ]);
        }

        // Flow settings first, then global option, then type default.
        $qid = $quiz_type->get_id();
        $expected_content = flosc_get_setting('quiz_content_' . $qid, '');
        if ($expected_content === '' || $expected_content === null) {
            $expected_content = get_option('flosc_quiz_content_' . $qid, $quiz_type->get_default_content());
        }
        if (($expected_content === '' || $expected_content === null) && method_exists($quiz_type, 'get_default_content')) {
            $expected_content = $quiz_type->get_default_content();
        }

        // Analyze using quiz type
        $analysis = $quiz_type->analyze($transcript, $expected_content, [
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
        ]);

        // Track quiz completion
        if (is_user_logged_in() && !is_wp_error($analysis)) {
            $this->sale_manager->usage()->track(get_current_user_id(), 'quizzes', 1, [
                'score' => $analysis['score'],
                'quiz_type' => $quiz_type->get_id(),
            ]);
        }

        // Map to lessons
        $lessons = $quiz_type->map_to_lessons($analysis);

        // Get response templates
        $templates = [];
        foreach ($quiz_type->get_default_response_templates() as $key => $default) {
            $templates[$key] = get_option(
                'flosc_quiz_' . $quiz_type->get_id() . '_template_' . $key,
                $default
            );
        }

        // Format results
        $message = $quiz_type->format_results($analysis, $lessons, $templates);

        return new WP_REST_Response([
            'success' => true,
            'transcript' => $transcript,
            'analysis' => $analysis,
            'lessons' => $lessons,
            'message' => $message,
        ]);
    }
    
    /**
     * Handle quiz processing
     * 
     * STATUS: ✅ FULLY FUNCTIONAL (v9.1.9)
     * - Accepts quiz input (e.g., "4,7,9")
     * - Scores against expected answer (e.g., "1,2,3,4,5,6,7,8,9,10")
     * - Calculates score percentage
     * - Fires flosc_quiz_completed action ✅
     * - Triggers Free Lesson Manager ✅
     * - Sets justCompletedQuiz transient for IVR ✅
     * 
     * FLOW:
     * 1. User submits quiz → this endpoint
     * 2. Quiz scored → 30% (3 of 10 correct)
     * 3. do_action('flosc_quiz_completed') fires
     * 4. Free Lesson Manager calculates missed (1,2,3,5,6,8,10)
     * 5. Picks ONE random lesson (#8)
     * 6. Stores in user meta
     * 7. IVR/AI can deliver free lesson
     */
    public function handle_process_quiz($request) {
        $input = sanitize_textarea_field($request->get_param('input'));
        $quiz_type_id = sanitize_text_field($request->get_param('quiz_type'));

        if (empty($input)) {
            return new WP_Error('no_input', __('No input provided', 'flosc'), ['status' => 400]);
        }

        $quiz_type = FLOSC_Quiz_Registry::get_quiz( $quiz_type_id );

        if (!$quiz_type) {
            return new WP_Error('invalid_quiz_type', __('Quiz type not found', 'flosc'), ['status' => 404]);
        }

        // Validate input
        $validation = $quiz_type->validate_input($input);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Flow settings first, then global option, then type default.
        $qid = $quiz_type->get_id();
        $expected_content = flosc_get_setting('quiz_content_' . $qid, '');
        if ($expected_content === '' || $expected_content === null) {
            $expected_content = get_option('flosc_quiz_content_' . $qid, $quiz_type->get_default_content());
        }
        if (($expected_content === '' || $expected_content === null) && method_exists($quiz_type, 'get_default_content')) {
            $expected_content = $quiz_type->get_default_content();
        }

        // Analyze
        $analysis = $quiz_type->analyze($input, $expected_content, [
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
        ]);

        if (is_wp_error($analysis)) {
            return $analysis;
        }

        // Track quiz completion
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $this->sale_manager->usage()->track($user_id, 'quizzes', 1, [
                'score' => $analysis['score'],
                'quiz_type' => $quiz_type->get_id(),
            ]);

            // v07.09: Set justCompletedQuiz flag for IVR
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);
            
            // v1.0.5 TASK-103: Fire flosc_quiz_completed for ALL scores
            // Bridge data should be created regardless of score.
            // Free Lesson Manager will only offer lesson if score < 100%
            $quiz_result = [
                'quiz_id' => $quiz_type->get_id(),
                'score' => $analysis['score'],
                'user_answer' => $input,
                'correct_answer' => $expected_content,
                'correct' => $analysis['correct'] ?? [],
                'incorrect' => $analysis['incorrect'] ?? [],
                'missed' => $analysis['incorrect'] ?? []
            ];
            
            // Fire hook - triggers Bridge Data Manager and Free Lesson Manager
            do_action('flosc_quiz_completed', $quiz_result, $user_id);
            
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v1.0.7: Quiz completed for user {$user_id} with score {$analysis['score']}%");
        }

        // Map to lessons
        $lessons = $quiz_type->map_to_lessons($analysis);

        // Get response templates
        $templates = [];
        foreach ($quiz_type->get_default_response_templates() as $key => $default) {
            $templates[$key] = get_option(
                'flosc_quiz_' . $quiz_type->get_id() . '_template_' . $key,
                $default
            );
        }

        // Format results
        $message = $quiz_type->format_results($analysis, $lessons, $templates);

        return new WP_REST_Response([
            'success' => true,
            'analysis' => $analysis,
            'lessons' => $lessons,
            'message' => $message,
        ]);
    }

    /**
     * Whether this flow serves lesson content (library + complimentary free lessons).
     *
     * Source of truth: flosc_flows + flosc_flow_{stem} (Lessons tab) — not product name.
     * A flow serves lessons only if it has configured lesson groups / category / free-lesson pool.
     *
     * @param string $stem Flow stem / id / ivr basename.
     * @return bool
     */
    public function flosc_flow_serves_lessons($stem = '') {
        $stem = $this->flosc_normalize_flow_stem($stem);
        $flow = null;

        if ($stem !== '') {
            $flows = get_option('flosc_flows', []);
            if (is_array($flows)) {
                foreach ($flows as $f) {
                    if (!is_array($f)) {
                        continue;
                    }
                    $id  = sanitize_key((string) ($f['id'] ?? ''));
                    $ivr = (string) ($f['ivr_file'] ?? $f['ivr'] ?? '');
                    $fs  = $this->flosc_normalize_flow_stem($ivr !== '' ? $ivr : $id);
                    if ($fs === $stem || $id === $stem) {
                        $flow = $f;
                        if ($stem === '' || $stem === 'default') {
                            $stem = $fs !== '' ? $fs : $id;
                        }
                        break;
                    }
                }
            }
        }
        if (!is_array($flow)) {
            $flow = $this->get_current_flow();
            if (is_array($flow) && ($stem === '' || $stem === 'default')) {
                $stem = $this->flosc_normalize_flow_stem(
                    (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '')
                );
            }
        }

        $settings = [];
        if ($stem !== '' && $stem !== 'default') {
            $bag = get_option('flosc_flow_' . $stem, []);
            if (is_array($bag)) {
                $settings = $bag;
            }
        }

        // 1) content_item_groups with at least one non-empty category (Lessons tab).
        $groups = [];
        if (!empty($settings['content_item_groups']) && is_array($settings['content_item_groups'])) {
            $groups = $settings['content_item_groups'];
        } elseif (is_array($flow) && !empty($flow['content_item_groups']) && is_array($flow['content_item_groups'])) {
            $groups = $flow['content_item_groups'];
        }
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            $gcat = trim((string) ($group['category'] ?? ''));
            if ($gcat !== '' && $gcat !== '0') {
                return true;
            }
        }

        // 2) Legacy single content_item_category / wp_category_id on the flow row.
        $cat = '';
        if (!empty($settings['content_item_category'])) {
            $cat = trim((string) $settings['content_item_category']);
        } elseif (is_array($flow)) {
            $cat = trim((string) ($flow['content_item_category'] ?? ''));
            if ($cat === '' && !empty($flow['wp_category_id'])) {
                $cat = (string) (int) $flow['wp_category_id'];
            }
        }
        if ($cat !== '' && $cat !== '0') {
            return true;
        }

        // 3) Complimentary pool category (free lessons) configured for this flow.
        $pool = sanitize_title((string) ($settings['free_content_item_pool_category'] ?? ''));
        if ($pool !== '') {
            return true;
        }

        return false;
    }

    /**
     * Quiz IDs explicitly configured on this flow (flosc_flows / flosc_flow_* only).
     * Empty list = this flow does not run or surface quizzes (e.g. assistant).
     * Does not invent pronunciation_* defaults from global options.
     *
     * @param string $stem
     * @return string[]
     */
    public function flosc_flow_quiz_ids($stem = '') {
        $stem = $this->flosc_normalize_flow_stem($stem);
        $ids  = [];

        $settings = ($stem !== '' && $stem !== 'default')
            ? get_option('flosc_flow_' . $stem, [])
            : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $flow = null;
        $flows = get_option('flosc_flows', []);
        if (is_array($flows)) {
            foreach ($flows as $f) {
                if (!is_array($f)) {
                    continue;
                }
                $id  = sanitize_key((string) ($f['id'] ?? ''));
                $ivr = (string) ($f['ivr_file'] ?? $f['ivr'] ?? '');
                $fs  = $this->flosc_normalize_flow_stem($ivr !== '' ? $ivr : $id);
                if ($stem !== '' && ($fs === $stem || $id === $stem)) {
                    $flow = $f;
                    break;
                }
            }
        }
        if (!is_array($flow)) {
            $flow = $this->get_current_flow();
        }
        if (!is_array($flow)) {
            $flow = [];
        }

        // Primary: explicit enabled list (empty = no quizzes for this flow).
        $enabled = $settings['enabled_quizzes'] ?? $flow['enabled_quizzes'] ?? [];
        if (is_array($enabled)) {
            foreach ($enabled as $qid) {
                $qid = sanitize_key((string) $qid);
                if ($qid !== '') {
                    $ids[] = $qid;
                }
            }
        }

        // Secondary: single default keys only if set on this flow (never invent global product IDs).
        foreach (['default_audio_quiz_id', 'default_text_quiz_id', 'quiz_type'] as $key) {
            $v = '';
            if (!empty($settings[$key])) {
                $v = (string) $settings[$key];
            } elseif (!empty($flow[$key])) {
                $v = (string) $flow[$key];
            }
            $v = sanitize_key(pathinfo(basename(trim($v)), PATHINFO_FILENAME));
            if ($v === '') {
                $v = sanitize_key(trim((string) ($settings[$key] ?? $flow[$key] ?? '')));
            }
            if ($v !== '') {
                $ids[] = $v;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Whether stored user quiz meta should be exposed on this flow’s chat shell.
     * Rules (flow parameters only — no product-name heuristics):
     * 1) Flow must list quiz id(s) in its own config.
     * 2) If quiz_data.flow_id is set, it must match this stem.
     * 3) If quiz_id is set, it must equal one of this flow’s configured quiz ids.
     * 4) Legacy meta with no quiz_id: only if this flow has default_audio_quiz_id
     *    (explicit audio quiz key on the flow bag) and data has phrase_results.
     *
     * @param string     $stem
     * @param array|null $quiz_data
     * @param string     $quiz_id_meta
     * @return bool
     */
    public function flosc_flow_should_surface_quiz_data($stem, $quiz_data = null, $quiz_id_meta = '') {
        $configured = $this->flosc_flow_quiz_ids($stem);
        if (empty($configured)) {
            return false;
        }

        $stem = $this->flosc_normalize_flow_stem($stem);
        $qid  = sanitize_key((string) $quiz_id_meta);

        if (is_array($quiz_data)) {
            if ($qid === '' && !empty($quiz_data['quiz_id'])) {
                $qid = sanitize_key((string) $quiz_data['quiz_id']);
            }
            if ($qid === '' && !empty($quiz_data['quizId'])) {
                $qid = sanitize_key((string) $quiz_data['quizId']);
            }
            if (!empty($quiz_data['flow_id'])) {
                $data_stem = $this->flosc_normalize_flow_stem((string) $quiz_data['flow_id']);
                if ($data_stem !== '' && $stem !== '' && $data_stem !== $stem) {
                    return false;
                }
            }
        }

        if ($qid !== '') {
            return in_array($qid, $configured, true);
        }

        // Legacy untagged IPA payload: require this flow explicitly sets default_audio_quiz_id.
        if (is_array($quiz_data) && !empty($quiz_data['phrase_results'])) {
            $settings = ($stem !== '' && $stem !== 'default')
                ? get_option('flosc_flow_' . $stem, [])
                : [];
            $audio = '';
            if (is_array($settings) && !empty($settings['default_audio_quiz_id'])) {
                $audio = sanitize_key((string) $settings['default_audio_quiz_id']);
            }
            if ($audio === '') {
                $flows = get_option('flosc_flows', []);
                if (is_array($flows)) {
                    foreach ($flows as $f) {
                        if (!is_array($f)) {
                            continue;
                        }
                        $id  = sanitize_key((string) ($f['id'] ?? ''));
                        $ivr = (string) ($f['ivr_file'] ?? $f['ivr'] ?? '');
                        $fs  = $this->flosc_normalize_flow_stem($ivr !== '' ? $ivr : $id);
                        if ($fs === $stem || $id === $stem) {
                            $audio = sanitize_key((string) ($f['default_audio_quiz_id'] ?? ''));
                            break;
                        }
                    }
                }
            }
            return $audio !== '' && in_array($audio, $configured, true);
        }

        return false;
    }

    /**
     * Flow stem from REST request or current app flow (session isolation).
     *
     * @param WP_REST_Request|null $request
     * @return string
     */
    public function flosc_request_flow_stem($request = null) {
        $raw = '';
        if ($request && method_exists($request, 'get_param')) {
            $raw = (string) ($request->get_param('flow_id') ?? $request->get_param('ivr_file') ?? '');
        }
        if ($raw === '') {
            $flow = $this->get_current_flow();
            if (is_array($flow)) {
                $raw = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
            }
        }
        return $this->session_manager->normalize_flow_stem($raw);
    }

    





    
    
    
    







    /**
     * Log a client-side chat turn into Chat Logs (free-lesson UI, offer cards, etc.).
     * Does not call AI. Used so admin Chat Logs show the full conversation.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_client_chat_log($request) {
        if (!class_exists('FLOSC_Chat_Logger')) {
            return new WP_Error('no_logger', __('Chat logger unavailable', 'flosc'), ['status' => 500]);
        }

        $user_message = sanitize_textarea_field((string) $request->get_param('user_message'));
        $ai_response  = wp_kses_post((string) $request->get_param('ai_response'));
        // Cap huge lesson HTML so logs stay usable.
        if (strlen($user_message) > 10000) {
            $user_message = substr($user_message, 0, 10000);
        }
        if (strlen($ai_response) > 80000) {
            $ai_response = substr($ai_response, 0, 80000) . "\n<!-- truncated -->";
        }

        if ($user_message === '' && $ai_response === '') {
            return new WP_Error('empty', __('Nothing to log', 'flosc'), ['status' => 400]);
        }

        $flow_id = sanitize_text_field((string) ($request->get_param('flow_id') ?? ''));
        if ($flow_id === '' && method_exists($this, 'get_current_flow')) {
            $flow = $this->get_current_flow();
            if (is_array($flow)) {
                $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? '');
                $flow_id = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
            }
        }
        $flow_id = $this->flosc_normalize_flow_stem($flow_id);
        if ($flow_id === '') {
            return new WP_Error('flow_id_required', __('flow_id required', 'flosc'), ['status' => 400]);
        }

        $session_id = absint($request->get_param('session_id'));
        $phase = sanitize_text_field((string) ($request->get_param('phase') ?? 'content'));
        $provider = sanitize_text_field((string) ($request->get_param('provider') ?? 'client'));
        $source = sanitize_text_field((string) ($request->get_param('response_source') ?? 'client_ui'));

        $user_id = is_user_logged_in() ? get_current_user_id() : 0;

        // Persist into guest/member session history when possible (sidebar replay).
        // add_flosc_message already requires the session to belong to this user + flow.
        if ($user_id > 0 && $session_id > 0 && $this->session_manager) {
            $hist_flow = $flow_id;
            $hist_meta = ($source === 'engagement_admin')
                ? ['source' => 'engagement_admin', 'name' => 'Engagement']
                : null;
            if ($user_message !== '') {
                $this->session_manager->add_flosc_message($session_id, 'user', $user_message, $user_id, null, $hist_flow);
            }
            if ($ai_response !== '') {
                $this->session_manager->add_flosc_message(
                    $session_id,
                    'assistant',
                    wp_strip_all_tags($ai_response),
                    $user_id,
                    $hist_meta,
                    $hist_flow
                );
            }
        }

        $insert_id = FLOSC_Chat_Logger::instance()->flosc_log_chat([
            'flow_id'          => $flow_id,
            'phase'            => $phase !== '' ? $phase : 'content',
            'user_id'          => $user_id,
            'session_id'       => $session_id,
            'user_message'     => $user_message,
            'ai_response'      => $ai_response,
            'provider'         => $provider !== '' ? $provider : 'client',
            'chain_detail'     => ['client_ui'],
            'response_source'  => $source !== '' ? $source : 'client_ui',
            'response_time_ms' => 0,
            'billing_source'   => 'none',
        ]);

        return new WP_REST_Response([
            'success' => (bool) $insert_id,
            'log_id'  => $insert_id ? (int) $insert_id : 0,
        ]);
    }


    /**
     * Redeem Access Code — flow-level and/or per-offer access_codes.
     * Full unlock: grant member for this flow (offer grants when code is on an offer).
     */
    public function handle_redeem_access_code($request) {
        $code = $request->get_param('code');
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        $offer_id_param = sanitize_text_field($request->get_param('offer_id') ?? '');

        if (empty($code)) {
            return new WP_Error('missing_code', __('No access code provided', 'flosc'), ['status' => 400]);
        }

        $code_norm = strtoupper(trim((string) $code));
        $option_key = 'flosc_flow_' . sanitize_key($flow_id);
        $flow_option = get_option($option_key, []);
        if (!is_array($flow_option)) {
            $flow_option = [];
        }

        $matched_offer_id = '';
        $grants_level = '';

        // 1) Flow-level access code (legacy).
        $stored_code = strtoupper(trim((string) ($flow_option['access_code'] ?? '')));
        if ($stored_code !== '' && $code_norm === $stored_code) {
            $grants_level = $flow_option['access_code_role']
                ?? flosc_get_setting('default_member_level', '', $flow_id ?: null);
            $matched_offer_id = 'access_code';
        }

        // 2) Per-offer access_codes (prefer offer_id when provided).
        if ($matched_offer_id === '') {
            $offers = $flow_option['offers'] ?? [];
            if (!is_array($offers)) {
                $offers = [];
            }
            $scan = $offers;
            if ($offer_id_param !== '' && isset($offers[$offer_id_param])) {
                $scan = [$offer_id_param => $offers[$offer_id_param]];
            }
            foreach ($scan as $oid => $off) {
                if (!is_array($off)) {
                    continue;
                }
                $acs = $off['access_codes'] ?? [];
                if (!is_array($acs)) {
                    continue;
                }
                foreach ($acs as $ac) {
                    if (strtoupper(trim((string) $ac)) === $code_norm) {
                        $matched_offer_id = (string) ($off['id'] ?? $oid);
                        $grants_level = $off['grants_level']
                            ?? ($off['grants']['level'] ?? '');
                        break 2;
                    }
                }
            }
        }

        if ($matched_offer_id === '') {
            return new WP_Error('invalid_code', __('Invalid access code', 'flosc'), ['status' => 403]);
        }

        $user_id = get_current_user_id();
        $user = get_userdata($user_id);
        if (!$user) {
            return new WP_Error('no_user', __('User not found', 'flosc'), ['status' => 401]);
        }
        if (!empty($flow_id)) {
            update_user_meta($user_id, '_flosc_registration_flow', sanitize_key($flow_id));
        }

        if ($grants_level === '') {
            $grants_level = sanitize_key((string) flosc_get_setting('default_member_level', '', $flow_id ?: null));
        }
        // Product-neutral: only apply level meta when this flow configured one.
        if ($grants_level !== '') {
            update_user_meta($user_id, '_flosc_member_level', $grants_level);
        }
        update_user_meta($user_id, '_flosc_purchased', true);
        update_user_meta($user_id, '_flosc_purchased_at', current_time('mysql'));

        $member_access = FLOSC_Member_Access::instance();
        $member_access->grant_member_access($user_id, [
            'offer_id' => $matched_offer_id,
            'grants_level' => $grants_level,
            'provider' => 'access_code',
            'transaction_id' => 'access_code_' . $user_id . '_' . time(),
            'amount' => 0,
            'flow_id' => $flow_id,
        ]);

        // Also grant offer tokens/features when we matched a real offer.
        if ($matched_offer_id !== 'access_code' && $this->sale_manager) {
            $offer = $this->sale_manager->offers()->get_offer($matched_offer_id, $flow_id ?: null);
            if ($offer && method_exists($this->sale_manager->access(), 'grant_from_offer')) {
                $this->sale_manager->access()->grant_from_offer($user_id, $offer, [
                    'transaction_id' => 'access_code_' . $user_id . '_' . time(),
                    'provider' => 'access_code',
                ]);
            }
        }

        set_transient('flosc_just_purchased_' . $user_id, true, 300);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC Access Code: User {$user_id} granted {$grants_level} via access code offer={$matched_offer_id}");
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Access granted',
            'offer_id' => $matched_offer_id,
        ]);
    }

    
    /**
     * v8.0.7: Score pending audio — called by JS after ANY login method.
     * 
     * The visitor took the IPA quiz, audio was uploaded to flosc-temp/{temp_id}/.
     * After login (email, Facebook, Google, any SSO), JS sends the temp_id from
     * localStorage. This function scores the audio and returns full results.
     * 
     * This replaces the cookie-based SSO scoring path which fails cross-domain
     * (third-party cookies are blocked by modern browsers).
     */
    public function handle_score_pending_audio($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Not logged in'], 401);
        }
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] score-pending-audio called for user {$user_id}");

        // Check if user already has scored quiz data
        $existing = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        if ($existing && !empty($existing['phrase_results'])) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] User {$user_id} already has scored data, returning it");
            return new WP_REST_Response([
                'success' => true,
                'already_scored' => true,
                'score_data' => $existing,
            ]);
        }

        $temp_id = sanitize_text_field($request->get_param('temp_id') ?? '');
        if (!$temp_id || !preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $temp_id)) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] Invalid temp_id: {$temp_id}");
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid session ID'], 400);
        }
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] Scoring audio for user {$user_id}, temp_id={$temp_id}");
        $audio_score = $this->score_visitor_audio($user_id, $temp_id);

        if ($audio_score) {
            $this->store_quiz_score($user_id, $audio_score);
            do_action('flosc_quiz_completed', $audio_score, $user_id);
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);

            $user = get_user_by('id', $user_id);
            if ($user) {
                $this->send_score_email($user, $audio_score);
            }
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] Scoring complete: {$audio_score['score']}%");
            return new WP_REST_Response([
                'success' => true,
                'score_data' => $audio_score,
            ]);
        }
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("[FLOSC v8.0.7] score_visitor_audio returned false");
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Could not score audio. Files may have expired.',
        ], 500);
    }

    /**
     * Handle email-only registration / guest-link request (v1.4.0+)
     *
     * Stashes visitor quiz payload and (when MagicLink is enabled) mints access
     * for an existing WP user only. MagicLink click never creates accounts;
     * Convenience-link mint requires an existing WP user (never creates on mint).
     */
    public function handle_stash_visitor_quiz($request) {
        $quiz_data = $request->get_param('quiz_data');
        if (!is_array($quiz_data) || empty($quiz_data['phraseResults']) || !is_array($quiz_data['phraseResults'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing quiz_data.phraseResults',
            ], 400);
        }

        // Cap payload so public stash cannot fill object cache / options with megabytes.
        $encoded = wp_json_encode($quiz_data);
        if (!is_string($encoded) || strlen($encoded) > 200000) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Quiz data too large',
            ], 400);
        }

        // Keep only fields the login handoff needs; drop unexpected keys.
        $safe = array(
            'phraseResults' => array_slice(array_values($quiz_data['phraseResults']), 0, 20),
            'tempId'        => '',
            'score'         => isset($quiz_data['score']) ? floatval($quiz_data['score']) : null,
        );
        if (!empty($quiz_data['tempId']) && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', (string) $quiz_data['tempId'])) {
            $safe['tempId'] = (string) $quiz_data['tempId'];
        }

        $token = wp_generate_password(32, false, false);
        set_transient('flosc_quiz_stash_' . $token, $safe, HOUR_IN_SECONDS);

        return new WP_REST_Response([
            'success' => true,
            'token'   => $token,
        ]);
    }

    /**
     * Consume quiz data stashed via /stash-visitor-quiz before an SSO redirect.
     */
    public function consume_stashed_visitor_quiz($user_id) {
        $existing = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        if (is_array($existing) && !empty($existing['phrase_results'])) {
            return false;
        }

        if ( ! isset( $_COOKIE['flosc_quiz_stash'] ) ) {
            return false;
        }

        // Sanitize at first touch (Plugin Check ValidatedSanitizedInput).
        $token = sanitize_text_field( wp_unslash( $_COOKIE['flosc_quiz_stash'] ) );
        $token = sanitize_text_field( rawurldecode( $token ) );
        setcookie('flosc_quiz_stash', '', time() - 3600, '/');

        if ($token === '') {
            return false;
        }

        $quiz_data = get_transient('flosc_quiz_stash_' . $token);
        delete_transient('flosc_quiz_stash_' . $token);

        if (!is_array($quiz_data) || empty($quiz_data['phraseResults'])) {
            return false;
        }

        $temp_id = '';
        if (!empty($quiz_data['tempId']) && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $quiz_data['tempId'])) {
            $temp_id = sanitize_text_field($quiz_data['tempId']);
        }

        return (bool) $this->store_browser_quiz_data($user_id, $quiz_data, $temp_id);
    }


/**
     * Save guest profile nickname and optional password from the in-chat profile card.
     * Called on first (and every subsequent) guest link login.
     */
    public function handle_update_guest_profile($request) {
        // Pass 6: password/cookie only for an already authenticated WordPress user
        // (REST permission_callback = check_authenticated_user_permission).
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $display_name = sanitize_text_field($request->get_param('display_name') ?? '');
        $password     = $request->get_param('password');

        if (!empty($display_name)) {
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $display_name,
                'nickname'     => $display_name,
                'first_name'   => $display_name,   // fixes WP Admin Name column + AI context
            ]);
            // Set BuddyBoss xprofile Name field if available (field 1 = Name by default)
            if (function_exists('xprofile_set_field_data')) {
                xprofile_set_field_data(1, $user_id, $display_name);
            }
        }

        // Mark credentials as set — clears pendingCredentialSetup flag permanently
        update_user_meta($user_id, '_flosc_magic_link_user_credentials_set', true);

        if (!empty($password) && is_string($password) && strlen($password) >= 6) {
            wp_set_password($password, $user_id);
            // wp_set_password() clears all sessions — re-issue auth cookies for this user only
            wp_set_auth_cookie($user_id, true);
            $flosc_token = $this->generate_flosc_auth_token($user_id);
            $this->set_flosc_auth_cookie($flosc_token);

            // Confirmation email — never include plaintext passwords (WordPress.org / security).
            $user      = get_userdata($user_id);
            $user_email = $user->user_email ?? '';
            $guest_email_context = $this->get_guest_email_context('', $user_id);
            $chat_url   = $guest_email_context['chat_url'];
            $profile_url = function_exists('bp_core_get_user_domain') ? bp_core_get_user_domain($user_id) : '';
            $name_for_email = $display_name ?: $user->display_name;
            if ($user_email) {
                $magic_token = get_user_meta($user_id, '_flosc_magic_link_token', true);
                $magic_link_line = '';
                if ($magic_token) {
                    $magic_url = add_query_arg('flosc_magic', rawurlencode($magic_token), $guest_email_context['chat_url']);
                    $magic_link_line = "Your {$guest_email_context['link_name']} gives you instant one-click access to your\nlessons and quiz results for as long as your membership remains active.\n\nAccess Link: {$magic_url}\n\n";
                }
                $profile_line = $profile_url ? "Your profile and recordings: {$profile_url}\n" : '';
                $login_url = wp_login_url($chat_url);
                $subject = 'Your ' . $guest_email_context['link_name'] . ' is ready';
                $body    = "Hi {$name_for_email},\n\n"
                         . "Your {$guest_email_context['app_name']} account is all set.\n\n"
                         . "  Email: {$user_email}\n\n"
                         . "For security, your password is not included in this email. "
                         . "Use the password you just set, or reset it from the login screen if needed:\n"
                         . "{$login_url}\n\n"
                         . $magic_link_line
                         . "Continue here: {$chat_url}\n"
                         . $profile_line
                         . "\n— The {$guest_email_context['team_name']}";
                wp_mail(
                    $user_email,
                    $subject,
                    $body,
                    $this->get_flosc_mail_headers('', (int) $user_id, false)
                );
            }
        }

        return new WP_REST_Response([
            'success'      => true,
            'display_name' => $display_name ?: get_userdata($user_id)->display_name,
        ]);
    }

    /**
     * Generate unique username from email — consistent with WooCommerce convention on this site.
     */
    private function generate_username_from_email($email) {
        // Use email as username (consistent with WooCommerce convention on this site)
        if (!username_exists($email)) {
            return $email;
        }
        // Edge case: email already taken as user_login by a different account
        $i = 2;
        do {
            $candidate = $email . '_' . $i++;
        } while (username_exists($candidate));
        return $candidate;
    }
    
    /**
     * Process pre-login data for newly logged in user (v1.4.0)
     *
     * v3.0.7: Also falls back to the flosc_quiz_result signed cookie that the
     * in-chat multiple-choice quiz sets via POST /quiz-result. Previously only
     * flosc_prelogin_score (set by /store-score) was checked, so multiple-choice
     * quiz results were never transferred to the new user → free lesson never
     * assigned → flow broke at the "View free lesson" step.
     */
    private function process_prelogin_data_for_user($user_id) {
        // Primary: flosc_prelogin_score (set by /store-score — text-sequence + audio quiz path)
        $score_data = $this->get_signed_cookie('flosc_prelogin_score');

        // v3.0.7 Fallback: flosc_quiz_result (set by /quiz-result — in-chat MC quiz path)
        if ( ! $score_data || ! isset( $score_data['score'] ) ) {
            $raw = $this->get_signed_cookie('flosc_quiz_result');
            if ( $raw && isset( $raw['score'] ) ) {
                // Normalize flosc_quiz_result format → flosc_prelogin_score format
                // flosc_quiz_result: { quiz_id, score, answers:[{questionId,answer,correct},...], completed_at, duration }
                $answers   = is_array( $raw['answers'] ?? null ) ? $raw['answers'] : [];
                $correct   = [];
                $incorrect = [];
                foreach ( $answers as $i => $a ) {
                    $lesson = $i + 1;
                    if ( isset( $a['correct'] ) && $a['correct'] === true ) {
                        $correct[]   = $lesson;
                    } else {
                        $incorrect[] = $lesson;
                    }
                }
                $score_data = [
                    'quiz_id'   => $raw['quiz_id']      ?? flosc_get_setting('default_text_quiz_id', 'sample_assessment_quiz'),
                    'score'     => intval( $raw['score'] ),
                    'correct'   => $correct,
                    'incorrect' => $incorrect,
                    'timestamp' => isset( $raw['completed_at'] ) ? intval( $raw['completed_at'] / 1000 ) : time(),
                ];
                if ( FLOSC_DEBUG ) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log( "FLOSC v3.0.7: Using flosc_quiz_result fallback cookie for user {$user_id}" );
                }
                // Clear the fallback cookie
                setcookie( 'flosc_quiz_result', '', [ 'expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax' ] );
            }
        }

        if ($score_data && isset($score_data['score'])) {
            $score = intval($score_data['score']);
            $quiz_id = sanitize_key((string) ($score_data['quiz_id'] ?? ''));

            // v3.0.2: Store full quiz meta (mirrors store_quiz_result)
            update_user_meta($user_id, '_flosc_last_quiz_id', $quiz_id);
            update_user_meta($user_id, '_flosc_last_quiz_score', $score);
            update_user_meta($user_id, '_flosc_prelogin_score', $score);
            update_user_meta($user_id, '_flosc_quiz_completed_at', ($score_data['timestamp'] ?? time()) * 1000);

            // Add to completed quizzes array
            $completed = get_user_meta($user_id, '_flosc_completed_quizzes', true) ?: [];
            if (!in_array($quiz_id, $completed)) {
                $completed[] = $quiz_id;
                update_user_meta($user_id, '_flosc_completed_quizzes', $completed);
            }

            // v1.8.2: Fire flosc_quiz_completed so Free Lesson Manager assigns lessons
            // v3.0.2: $score_data now includes quiz_id for category resolution
            do_action('flosc_quiz_completed', $score_data, $user_id);

            // v8.0.5: Set transient so buildIVRContext() can set first_message_after_quiz
            // even when the handle_user_login() cookie path didn't fire.
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);

            // Store in bridge data if available
            $bridge_manager = FLOSC_Bridge_Data_Manager::instance();
            if ($bridge_manager) {
                // Merge with any existing bridge data
                $existing = $bridge_manager->get_flosc_bridge_data($user_id);
                if (!$existing) {
                    $bridge_manager->update_flosc_bridge_data($user_id, ['score' => $score]);
                }
            }

            // Clear the cookie after transfer
            setcookie('flosc_prelogin_score', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'samesite' => 'Lax'
            ]);

            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC Auth: Transferred pre-login score {$score}% (quiz: {$quiz_id}) for user {$user_id}");
            }
        }
    }
    
    /**
     * Get free lesson for logged-in user (v9.1.9)
     * v1.4.9: Use deliver_free_lesson() to persist _flosc_free_content_item_delivered
     */
    public function get_free_lesson($request) {
        $user_id = get_current_user_id();

        // Only flows with Lessons tab config (content_item_groups / category / pool) deliver lessons.
        $flow_raw = (string) ($request->get_param('flow_id') ?? $request->get_param('ivr_file') ?? '');
        if ($flow_raw === '') {
            $cf = $this->get_current_flow();
            if (is_array($cf)) {
                $flow_raw = (string) ($cf['ivr_file'] ?? $cf['ivr'] ?? $cf['id'] ?? '');
            }
        }
        $stem = $this->flosc_normalize_flow_stem($flow_raw);
        if (!$this->flosc_flow_serves_lessons($stem)) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'lessons_not_on_flow',
                'message' => 'Lessons are not available in this chat.',
            ], 404);
        }

        $free_lesson_mgr = FLOSC_Free_Content_Item_Manager::instance();

        // v1.4.9 FIX: Call deliver_free_lesson() instead of get_free_lesson()
        // so _flosc_free_content_item_delivered is set and phase transitions to OFFER on reload
        $result = $free_lesson_mgr->deliver_free_lesson($user_id, 'chat');

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result['message'] ?? 'No free lesson available. Please take the quiz first.',
            ], 404);
        }

        // v1.5.4: Return multiple lessons
        $lessons_data = [];
        if (!empty($result['lessons'])) {
            foreach ($result['lessons'] as $lesson) {
                $lessons_data[] = [
                    'title' => $lesson['title'],
                    'content' => $lesson['content'],
                    'url' => $lesson['url'],
                    'lesson_number' => $lesson['lesson_number'],
                ];
            }
        } else {
            // Backward compat: single lesson
            if (empty($result['title'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'No free lesson available. Please take the quiz first.',
                ], 404);
            }
            $lessons_data[] = [
                'title' => $result['title'],
                'content' => $result['content'],
                'url' => $result['url'],
                'lesson_number' => $result['lesson_number'],
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'count' => count($lessons_data),
            'lessons' => $lessons_data,
            // Backward compat
            'lesson' => $lessons_data[0],
        ]);
    }
    
    
    
    /**
     * PayPal - Test Connection (AJAX, admin only)
     * Attempts OAuth token request to verify credentials are valid.
     */
    public function ajax_test_paypal() {
        check_ajax_referer('flosc_test_paypal');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
        if (!$pp || !$pp->is_configured()) {
            wp_send_json_error('PayPal is not configured. Enter Client ID and Secret first.');
        }

        $cfg = $pp->get_client_config();
        $mode = $cfg['mode'] ?? 'sandbox';
        $client_id = $cfg['clientId'] ?? '';
        $secret = flosc()->get_setting('paypal_secret', '');
        if (empty($secret)) $secret = get_option('flosc_paypal_secret', '');
        $api_base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

        $response = wp_remote_post($api_base . '/v1/oauth2/token', [
            'headers' => [
                // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => 'grant_type=client_credentials',
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = wp_remote_retrieve_response_code($response);

        if ($status !== 200 || empty($body['access_token'])) {
            $err = $body['error_description'] ?? $body['error'] ?? 'HTTP ' . $status;
            wp_send_json_error('Auth failed: ' . $err);
        }

        // Register / refresh webhook so renewals verify (same app as Client ID/Secret).
        $webhook_info = null;
        if (method_exists($pp, 'ensure_webhook_registered')) {
            $wh = $pp->ensure_webhook_registered(true);
            if (is_wp_error($wh)) {
                wp_send_json_success([
                    'mode'     => ucfirst($mode),
                    'app_name' => $body['app_id'] ?? 'PayPal',
                    'webhook'  => 'error: ' . $wh->get_error_message(),
                ]);
            }
            $webhook_info = [
                'id'      => $wh['webhook_id'] ?? '',
                'url'     => $wh['url'] ?? '',
                'created' => !empty($wh['created']),
            ];
        }

        // P0-A security matrix (same path as production REST dispatcher).
        $p0a = ['unsigned' => null, 'forged' => null, 'headers_wired' => false];
        if (method_exists($pp, 'handle_webhook') && class_exists('WP_REST_Request')) {
            $unsigned = $pp->handle_webhook('{"id":"WH-ADMIN-UNSIGNED"}', []);
            $p0a['unsigned'] = is_wp_error($unsigned)
                ? ($unsigned->get_error_code() . ':' . (int) ($unsigned->get_error_data()['status'] ?? 0))
                : 'unexpected_ok';

            // Synthetic headers as the dispatcher would pass them after collection.
            $forged_headers = [
                'paypal-transmission-id'   => 'admin-test-tid',
                'paypal-transmission-time' => '2020-01-01T00:00:00Z',
                'paypal-transmission-sig'  => 'abc+def/ghi=',
                'paypal-cert-url'          => 'https://api.paypal.com/v1/notifications/certs/CERT-test',
                'paypal-auth-algo'         => 'SHA256withRSA',
            ];
            $forged = $pp->handle_webhook(
                '{"id":"WH-ADMIN-FORGED","event_type":"PAYMENT.SALE.COMPLETED","resource":{}}',
                $forged_headers
            );
            $p0a['forged'] = is_wp_error($forged)
                ? ($forged->get_error_code() . ':' . (int) ($forged->get_error_data()['status'] ?? 0))
                : 'unexpected_ok';

            $req = new WP_REST_Request('POST', '/flosc/v1/webhooks/paypal');
            $req->set_header('paypal-transmission-id', 'wire-check');
            $collected = FLOSC_PayPal_Provider::collect_transmission_headers_from_request($req);
            $p0a['headers_wired'] = !empty($collected['paypal-transmission-id']);
            // Unsigned → missing_signature:401; forged → invalid_signature/invalid_cert:401.
            $p0a['pass'] = (
                is_string($p0a['unsigned']) && strpos($p0a['unsigned'], 'missing_signature') === 0
                && is_string($p0a['forged']) && (
                    strpos($p0a['forged'], 'invalid_signature') === 0
                    || strpos($p0a['forged'], 'invalid_cert_url') === 0
                    || strpos($p0a['forged'], 'paypal_verify') === 0
                    || strpos($p0a['forged'], 'webhook_not_configured') === 0
                )
                && $p0a['headers_wired']
            );
        }

        wp_send_json_success([
            'mode'     => ucfirst($mode),
            'app_name' => $body['app_id'] ?? 'PayPal',
            'webhook'  => $webhook_info,
            'p0a'      => $p0a,
        ]);
    }

    /**
     * PayPal Subscriptions — Get or create plans (auto-setup)
     * Returns plan IDs for monthly ($10) and yearly ($100).
     * Creates the PayPal product + plans on first call.
     */
    public function paypal_get_plans($request) {
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if (!empty($flow_id)) {
            $this->set_flow_context($flow_id);
        }

        $paypal = $this->sale_manager->get_provider('paypal');
        if (!$paypal || !$paypal->is_configured()) {
            return new WP_Error('paypal_not_configured', __('PayPal is not configured', 'flosc'), ['status' => 500]);
        }

        $offer_id = sanitize_text_field($request->get_param('offer_id') ?? '');
        $coupon_code = sanitize_text_field($request->get_param('coupon_code') ?? '');

        // Coupon path: create/cache PayPal plans at discounted recurring amounts.
        if ($offer_id !== '' && $coupon_code !== '') {
            $offer = $this->sale_manager->offers()->get_offer($offer_id, $flow_id ?: null);
            if (!$offer) {
                return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
            }
            $sub = $this->flosc_resolve_subscription_coupon_prices($offer, $coupon_code);
            if (is_wp_error($sub)) {
                return $sub;
            }
            $product_name = trim((string) ($offer['name'] ?? $offer['headline'] ?? 'Subscription'));
            if ($product_name === '') {
                $product_name = 'Subscription';
            }
            if (!method_exists($paypal, 'ensure_plans_for_prices')) {
                return new WP_Error('paypal_plans', __('PayPal provider cannot create promo plans.', 'flosc'), ['status' => 500]);
            }
            $plans = $paypal->ensure_plans_for_prices($sub['monthly'], $sub['yearly'], $product_name);
            if (is_wp_error($plans)) {
                return $plans;
            }
            return new WP_REST_Response([
                'monthly_plan_id' => $plans['monthly_plan_id'],
                'yearly_plan_id'  => $plans['yearly_plan_id'],
                'monthly_price'   => $sub['monthly'],
                'yearly_price'    => $sub['yearly'],
                'list_monthly'    => $sub['list_monthly'],
                'list_yearly'     => $sub['list_yearly'],
                'coupon_code'     => $sub['coupon_code'],
            ]);
        }

        $plans = $paypal->ensure_plans_exist();
        if (is_wp_error($plans)) {
            return $plans;
        }

        return new WP_REST_Response([
            'monthly_plan_id' => $plans['monthly_plan_id'],
            'yearly_plan_id'  => $plans['yearly_plan_id'],
        ]);
    }

    /**
     * Resolve a verified PayPal subscription to FLOSC's local plan type.
     *
     * The browser-supplied plan hint is only used as a consistency check.
     * The PayPal subscription plan ID is the source of truth.
     *
     * @param array  $sub Verified PayPal subscription payload.
     * @param string $requested_plan_type Client-supplied plan hint.
     * @return string|WP_Error
     */
    private function resolve_paypal_subscription_plan_type(array $sub, $requested_plan_type = '') {
        $plans = [];
        $paypal = $this->sale_manager->get_provider('paypal');
        if ($paypal && method_exists($paypal, 'get_stored_plans')) {
            $plans = $paypal->get_stored_plans();
        }
        if (empty($plans)) {
            $plans = get_option('flosc_paypal_plans', []);
        }
        if (!is_array($plans)) {
            $plans = [];
        }
        $subscription_plan_id = sanitize_text_field($sub['plan_id'] ?? ($sub['plan']['id'] ?? ''));

        if (empty($subscription_plan_id)) {
            return new WP_Error('missing_plan_id', __('PayPal subscription is missing a plan_id.', 'flosc'), ['status' => 400]);
        }

        // Promo / coupon plan IDs (monthly/yearly at discounted amounts).
        if ($paypal && method_exists($paypal, 'resolve_plan_type_for_id')) {
            $promo_type = $paypal->resolve_plan_type_for_id($subscription_plan_id);
            if ($promo_type === 'monthly' || $promo_type === 'yearly') {
                return $promo_type;
            }
        }

        $resolved_plan_type = '';
        if (!empty($plans['monthly_plan_id']) && hash_equals((string) $plans['monthly_plan_id'], (string) $subscription_plan_id)) {
            $resolved_plan_type = 'monthly';
        } elseif (!empty($plans['yearly_plan_id']) && hash_equals((string) $plans['yearly_plan_id'], (string) $subscription_plan_id)) {
            $resolved_plan_type = 'yearly';
        }

        if (empty($resolved_plan_type)) {
            return new WP_Error('unknown_paypal_plan', __('PayPal subscription plan is not recognized.', 'flosc'), ['status' => 400]);
        }

        if (!empty($requested_plan_type) && $requested_plan_type !== $resolved_plan_type) {
            return new WP_Error('plan_mismatch', __('Requested plan does not match the verified PayPal subscription.', 'flosc'), ['status' => 400]);
        }

        return $resolved_plan_type;
    }


    /**
     * PayPal Subscriptions — Activate after user approves in PayPal popup.
     *
     * Order (correct):
     * 1) GET subscription from PayPal — ACTIVE only
     * 2) custom_id = server purchase_uuid — load intent
     * 3) plan/offer/amount from intent only
     * 4) then browser binding + account create + fulfill
     */
    public function paypal_activate_subscription($request) {
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] activate-subscription HIT user=' . get_current_user_id());
        }

        $subscription_id = sanitize_text_field((string) $request->get_param('subscription_id'));
        $flow_id         = sanitize_key((string) ($request->get_param('flow_id') ?? ''));
        $client_offer    = sanitize_text_field((string) ($request->get_param('offer_id') ?? ''));
        $binding_token   = sanitize_text_field((string) $request->get_param('binding_token'));
        $binding_session = sanitize_text_field((string) $request->get_param('session_id'));

        if ($subscription_id === '') {
            return new WP_Error('missing_params', __('Missing subscription_id', 'flosc'), ['status' => 400]);
        }

        $user_id         = get_current_user_id();
        $is_new_user     = false;
        $auth_token      = '';
        $binding_record  = null;

        if ($flow_id !== '') {
            $this->set_flow_context($flow_id);
        }

        $paypal = $this->sale_manager->get_provider('paypal');
        if (!$paypal || !$paypal->is_configured()) {
            return new WP_Error('paypal_not_configured', __('PayPal is not configured', 'flosc'), ['status' => 500]);
        }

        // 1) Processor truth.
        $sub = $paypal->get_subscription($subscription_id);
        if (is_wp_error($sub)) {
            return $sub;
        }
        if (!is_array($sub)) {
            return new WP_Error('paypal_error', __('Invalid PayPal subscription response', 'flosc'), ['status' => 502]);
        }

        $status = strtoupper((string) ($sub['status'] ?? ''));
        if ($status !== 'ACTIVE') {
            return new WP_Error(
                'subscription_not_active',
                sprintf(
                    /* translators: %s: PayPal subscription status */
                    __('Subscription is not ACTIVE (status: %s). Access is granted only after ACTIVE status.', 'flosc'),
                    $status !== '' ? $status : 'empty'
                ),
                ['status' => 400]
            );
        }

        // 2) Server purchase intent via custom_id (minted by prepare-subscription).
        $purchase_uuid = sanitize_text_field((string) ($sub['custom_id'] ?? ''));
        if ($purchase_uuid !== '' && isset($purchase_uuid[0]) && $purchase_uuid[0] === '{') {
            $decoded_custom = json_decode($purchase_uuid, true, 8);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded_custom)) {
                $purchase_uuid = sanitize_text_field((string) ($decoded_custom['purchase_uuid'] ?? $decoded_custom['uuid'] ?? ''));
            } else {
                $purchase_uuid = '';
            }
        }
        // Optional client echo of purchase_uuid if PayPal omitted custom_id (should not happen).
        if ($purchase_uuid === '') {
            $purchase_uuid = sanitize_text_field((string) ($request->get_param('purchase_uuid') ?? ''));
        }
        if ($purchase_uuid === '' || !function_exists('flosc_paypal_purchase_intent_get')) {
            return new WP_Error(
                'missing_purchase_intent',
                __('PayPal subscription is missing a server purchase intent (custom_id). Restart checkout.', 'flosc'),
                ['status' => 403]
            );
        }

        $intent = flosc_paypal_purchase_intent_get($purchase_uuid);
        if (!is_array($intent)) {
            return new WP_Error(
                'invalid_purchase_intent',
                __('Purchase intent expired or not found. Restart checkout.', 'flosc'),
                ['status' => 403]
            );
        }
        if (($intent['status'] ?? '') === 'fulfilled') {
            return new WP_REST_Response([
                'success'           => true,
                'already_fulfilled' => true,
                'subscription_id'   => $subscription_id,
                'offer_id'          => sanitize_text_field((string) ($intent['offer_id'] ?? '')),
            ], 200);
        }
        if (($intent['status'] ?? '') !== 'pending') {
            return new WP_Error('intent_not_pending', __('Purchase intent is not pending', 'flosc'), ['status' => 409]);
        }
        if (!empty($intent['expires_at']) && time() > (int) $intent['expires_at']) {
            return new WP_Error('intent_expired', __('Purchase intent expired. Restart checkout.', 'flosc'), ['status' => 403]);
        }

        $intent_plan_id = sanitize_text_field((string) ($intent['plan_id'] ?? ''));
        $sub_plan_id    = sanitize_text_field((string) ($sub['plan_id'] ?? ($sub['plan']['id'] ?? '')));
        if ($intent_plan_id === '' || $sub_plan_id === '' || !hash_equals($intent_plan_id, $sub_plan_id)) {
            return new WP_Error(
                'plan_mismatch',
                __('PayPal plan does not match the purchase intent', 'flosc'),
                ['status' => 403]
            );
        }

        $plan_type = sanitize_key((string) ($intent['plan_type'] ?? ''));
        if ($plan_type !== 'monthly' && $plan_type !== 'yearly') {
            $plan_type = 'monthly';
        }

        $sold_offer_id = sanitize_text_field((string) ($intent['offer_id'] ?? ''));
        if ($sold_offer_id === '') {
            return new WP_Error('missing_offer', __('Purchase intent has no offer', 'flosc'), ['status' => 400]);
        }
        if ($client_offer !== '' && !hash_equals($sold_offer_id, $client_offer)) {
            return new WP_Error(
                'offer_mismatch',
                __('Offer does not match the purchase intent', 'flosc'),
                ['status' => 403]
            );
        }

        $intent_flow = sanitize_key((string) ($intent['flow_id'] ?? ''));
        if ($intent_flow !== '') {
            $flow_id = $intent_flow;
            $this->set_flow_context($flow_id);
        }

        $amount   = sanitize_text_field((string) ($intent['amount'] ?? '0.00'));
        $currency = strtoupper(sanitize_text_field((string) ($intent['currency'] ?? 'USD'))) ?: 'USD';
        if ((float) $amount <= 0) {
            return new WP_Error('invalid_amount', __('Purchase intent has invalid amount', 'flosc'), ['status' => 400]);
        }

        $last_payment = (isset($sub['billing_info']['last_payment']['amount']) && is_array($sub['billing_info']['last_payment']['amount']))
            ? $sub['billing_info']['last_payment']['amount']
            : null;
        if (is_array($last_payment)) {
            $paid_amt   = isset($last_payment['value']) ? (float) $last_payment['value'] : -1.0;
            $paid_cur   = strtoupper(sanitize_text_field((string) ($last_payment['currency_code'] ?? '')));
            $expect_amt = (float) $amount;
            if ($paid_cur !== '' && $currency !== '' && $paid_cur !== $currency) {
                return new WP_Error(
                    'payment_mismatch',
                    __('PayPal subscription currency does not match the purchase intent', 'flosc'),
                    ['status' => 403]
                );
            }
            if ($paid_amt >= 0 && $expect_amt > 0 && abs($paid_amt - $expect_amt) > 0.05) {
                return new WP_Error(
                    'payment_mismatch',
                    __('PayPal subscription amount does not match the purchase intent', 'flosc'),
                    ['status' => 403]
                );
            }
        }

        $offer = $this->sale_manager->offers()->get_offer($sold_offer_id, $flow_id ?: null);
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        if (method_exists($this->sale_manager, 'validate_offer_for_purchase')) {
            $offer_ok = $this->sale_manager->validate_offer_for_purchase($offer, 'paid');
            if (is_wp_error($offer_ok)) {
                return $offer_ok;
            }
        }

        // 3) Browser binding (after processor+intent checks so we do not burn the token early).
        if ($user_id <= 0) {
            $binding_record = flosc_checkout_binding_verify($binding_token, $binding_session);
            if (!$binding_record) {
                return new WP_Error('invalid_checkout_binding', __('Missing or invalid checkout binding token.', 'flosc'), ['status' => 403]);
            }
            $binding_offer = sanitize_text_field((string) ($binding_record['offer_id'] ?? ''));
            if ($binding_offer !== '' && !hash_equals($sold_offer_id, $binding_offer)) {
                return new WP_Error(
                    'offer_mismatch',
                    __('Checkout binding offer does not match the purchase intent', 'flosc'),
                    ['status' => 403]
                );
            }
        } elseif ($binding_token !== '') {
            $binding_record = flosc_checkout_binding_verify($binding_token, $binding_session);
        }

        $subscriber_email = sanitize_email((string) ($sub['subscriber']['email_address'] ?? ''));
        $subscriber_name  = trim(
            sanitize_text_field((string) ($sub['subscriber']['name']['given_name'] ?? '')) . ' ' .
            sanitize_text_field((string) ($sub['subscriber']['name']['surname'] ?? ''))
        );

        if ($user_id > 0) {
            if ($subscriber_email === '') {
                return new WP_Error(
                    'buyer_proof_missing',
                    __('PayPal did not return a subscriber email; cannot verify the buyer', 'flosc'),
                    ['status' => 403]
                );
            }
            $wp_user = get_userdata($user_id);
            if ($wp_user && !empty($wp_user->user_email)
                && strcasecmp((string) $wp_user->user_email, $subscriber_email) !== 0) {
                return new WP_Error(
                    'buyer_mismatch',
                    __('PayPal subscriber email does not match the logged-in buyer', 'flosc'),
                    ['status' => 403]
                );
            }
        }

        if ($user_id <= 0) {
            if ($subscriber_email === '') {
                return new WP_Error('no_email', __('Could not retrieve email from PayPal subscription.', 'flosc'), ['status' => 400]);
            }
            $existing_user = get_user_by('email', $subscriber_email);
            if ($existing_user) {
                $user_id = (int) $existing_user->ID;
            } else {
                $username = $this->generate_username_from_email($subscriber_email);
                $password = wp_generate_password(16, true, true);
                $created  = wp_create_user($username, $password, $subscriber_email);
                if (is_wp_error($created)) {
                    return new WP_Error(
                        'user_creation_failed',
                        'Could not create account: ' . $created->get_error_message(),
                        ['status' => 500]
                    );
                }
                $user_id = (int) $created;
                $user    = get_user_by('id', $user_id);
                if ($user) {
                    $user->set_role(apply_filters('flosc_default_user_role', 'subscriber'));
                }
                if ($subscriber_name !== '') {
                    $name_parts = explode(' ', $subscriber_name, 2);
                    wp_update_user([
                        'ID'           => $user_id,
                        'first_name'   => sanitize_text_field((string) ($name_parts[0] ?? '')),
                        'last_name'    => sanitize_text_field((string) ($name_parts[1] ?? '')),
                        'display_name' => sanitize_text_field($subscriber_name),
                    ]);
                }
                update_user_meta($user_id, '_flosc_registration_method', 'paypal_purchase');
                update_user_meta($user_id, '_flosc_registered_at', current_time('mysql'));
                do_action('flosc_user_registered', $user_id, 'paypal_purchase', ['flow_id' => $flow_id]);
                $is_new_user = true;
            }
        }

        $intent_user = absint($intent['user_id'] ?? 0);
        if ($intent_user > 0 && $intent_user !== (int) $user_id) {
            return new WP_Error(
                'buyer_mismatch',
                __('Purchase intent belongs to a different user', 'flosc'),
                ['status' => 403]
            );
        }

        $default_member_level = flosc_get_setting('default_member_level', 'member', $flow_id ?: null);
        if (!isset($offer['grants']) || !is_array($offer['grants'])) {
            $offer['grants'] = [];
        }
        $offer['grants']['duration_days'] = ($plan_type === 'yearly') ? 365 : 30;
        $resolved_offer_id = sanitize_text_field((string) ($offer['id'] ?? $sold_offer_id));

        $capture_flow_id = $flow_id;
        if ($capture_flow_id === '') {
            $current_flow = $this->get_current_flow();
            $capture_flow_id = $current_flow ? sanitize_key((string) ($current_flow['id'] ?? '')) : '';
        }

        $transaction = [
            'transaction_id'  => $subscription_id,
            'subscription_id' => $subscription_id,
            'provider'        => 'paypal',
            'amount'          => $amount,
            'currency'        => $currency,
            'success'         => true,
            'status'          => 'active',
            'flow_id'         => $capture_flow_id,
            'purchase_uuid'   => $purchase_uuid,
        ];

        $fulfill_offer = $offer;
        if (empty($fulfill_offer['id']) && $resolved_offer_id !== '') {
            $fulfill_offer['id'] = $resolved_offer_id;
        }
        $fulfill = $this->sale_manager->fulfill_settled_purchase($user_id, $fulfill_offer, 'paypal', $transaction);
        if (is_wp_error($fulfill)) {
            return $fulfill;
        }

        if (function_exists('flosc_paypal_purchase_intent_mark_fulfilled')) {
            flosc_paypal_purchase_intent_mark_fulfilled($purchase_uuid, $subscription_id, $user_id);
        }

        // Store subscription metadata (incl. sold offer for renewals / token grants)
        update_user_meta($user_id, '_flosc_subscription_id', $subscription_id);
        update_user_meta($user_id, '_flosc_subscription_plan', $plan_type);
        update_user_meta($user_id, '_flosc_subscription_status', 'active');
        // Reverse index for webhook renewals (O(1) lookup — no meta_key scans).
        if (class_exists('FLOSC_PayPal_Provider') && method_exists('FLOSC_PayPal_Provider', 'index_subscription')) {
            FLOSC_PayPal_Provider::index_subscription($user_id, $subscription_id);
        }
        if ($resolved_offer_id !== '') {
            update_user_meta($user_id, '_flosc_purchased_offer_id', $resolved_offer_id);
            update_user_meta($user_id, '_flosc_subscription_offer_id', $resolved_offer_id);
        }

        set_transient('flosc_just_purchased_' . $user_id, true, 300);

        if ($capture_flow_id) {
            update_user_meta($user_id, '_flosc_purchased_flow_id', $capture_flow_id);
            update_user_meta($user_id, '_flosc_subscription_flow_id', $capture_flow_id);
        }

        // Ensure PayPal webhook is registered so renewal/cancel events can be verified.
        if ($paypal && method_exists($paypal, 'ensure_webhook_registered')) {
            $paypal->ensure_webhook_registered(false);
        }

        // Product token credit from the sold offer (custom) or flow Token Management defaults.
        // Payment always succeeds; tokens stop accruing once at the cap.
        $token_topup = $this->flosc_apply_product_token_credit(
            $user_id,
            $capture_flow_id,
            $plan_type === 'yearly' ? 'recurring_yearly' : 'recurring',
            [
                'idempotency_key' => 'activate_' . $subscription_id,
                'subscription_id' => $subscription_id,
                'offer' => $offer,
                'offer_id' => $resolved_offer_id,
                'reason' => 'PayPal subscription activate (' . $plan_type . ')',
            ]
        );

        // Post-purchase login. The subscription has been verified against PayPal's
        // live API above, so payment is real and access is granted unconditionally.
        // A login SESSION is issued only when the request also carries a valid
        // server-issued binding token (proof this is the buyer's own browser — see
        // §5b). A logged-in buyer already has a session and needs no new one.
        // Either way, handle_purchase_completed() (below, on the action) emails the
        // durable single-use link, so a buyer on another device — or one whose
        // binding token was absent — still has a one-click path in.
        // A login session is issued only for an account created in THIS
        // server-verified payment request, exactly once per subscription id, from
        // the browser holding the server-minted single-use binding token. This is
        // what makes a leaked subscription id useless for takeover: a replayed id
        // always resolves to an account that already exists ($is_new_user === false),
        // so it can never mint a session — that buyer (and every returning buyer)
        // comes in through the emailed single-use link instead. The per-subscription
        // claim transient guarantees at most one session per subscription, ever.
        $already_logged_in = $user_id > 0 && is_user_logged_in();
        $login_handoff     = 'email_link_sent';
        $claim_key         = 'flosc_sub_session_claim_' . md5($subscription_id);

        if (!$already_logged_in
            && $is_new_user
            && false === get_transient($claim_key)
            && !empty($binding_record)) {
            if (flosc_issue_post_purchase_session($user_id)) {
                set_transient($claim_key, time(), WEEK_IN_SECONDS); // Burn the one allowed claim.
                $auth_token    = $this->generate_flosc_auth_token($user_id);
                $login_handoff = 'session_issued';
            }
        } elseif ($already_logged_in) {
            $login_handoff = 'already_authenticated';
        }

        // flosc_purchase_completed already fired inside fulfill_settled_purchase.
        if (empty($fulfill['already_fulfilled'])) {
            do_action('flosc_paypal_subscription_activated', $user_id, [
                'offer_id'       => $resolved_offer_id,
                'provider'       => 'paypal',
                'transaction_id' => $subscription_id,
                'amount'         => $amount,
                'flow_id'        => $capture_flow_id,
                'subscription'   => true,
                'plan_type'      => $plan_type,
                'timestamp'      => time(),
            ]);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] === activate-subscription SUCCESS === sub=' . $subscription_id . ', plan=' . $plan_type . ', offer=' . ($resolved_offer_id ?: 'none') . ', user=' . $user_id . ', handoff=' . $login_handoff);
        }

        $user_data = get_userdata($user_id);
        $access_manager = $this->sale_manager->access();

        $product_name = '';
        $current_flow_welcome = $this->get_current_flow();
        if (is_array($current_flow_welcome)) {
            $product_name = trim((string) ($current_flow_welcome['identity']['name'] ?? $current_flow_welcome['product']['name'] ?? ''));
        }
        if ($product_name === '') {
            $product_name = 'membership';
        }

        return new WP_REST_Response([
            'success'            => true,
            'message'            => sprintf(/* translators: %s: product / flow name */ __('Welcome to %s!', 'flosc'), $product_name),
            'product_name'       => $product_name,
            'access'             => $access_manager->get_user_access($user_id),
            'member_level'       => $default_member_level,
            'plan_type'          => $plan_type,
            'amount'             => $amount,
            'currency'           => $transaction['currency'],
            'purchase_count'     => (int) get_user_meta($user_id, '_flosc_purchase_count', true),
            'user_id'            => $user_id,
            'user_email'         => $user_data->user_email ?? '',
            'user_display_name'  => $user_data->display_name ?? '',
            'is_new_user'        => $is_new_user,
            'auth_token'         => $auth_token ?: null,
            'login_handoff'      => $login_handoff,
            'token_topup'        => $token_topup,
        ]);
    }

    /**
     * PayPal - Create Order (one-time Orders API).
     * Creates a PayPal order for the given offer. Works for guests and logged-in buyers
     * (guest capture provisions the account from the PayPal payer email).
     */
    public function paypal_create_order($request) {
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] create_order ENDPOINT REACHED at ' . gmdate('Y-m-d H:i:s') . ' user=' . get_current_user_id());
        }

        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if (!empty($flow_id)) {
            $this->set_flow_context($flow_id);
        }

        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $coupon_code = sanitize_text_field($request->get_param('coupon_code') ?? '');

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] === create_order START === offer=' . $offer_id . ', flow=' . ($flow_id ?: 'none') . ', user=' . get_current_user_id());
        }

        $offer = $this->sale_manager->offers()->get_offer($offer_id, $flow_id ?: null);

        if (!$offer) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] create_order FAIL: offer "' . $offer_id . '" not found');
            return new WP_Error('invalid_offer', 'Offer not found: ' . $offer_id, ['status' => 404]);
        }

        $paypal = $this->sale_manager->get_provider('paypal');
        if (!$paypal || !$paypal->is_configured()) {
            $has_id = !empty($paypal) ? ($paypal->has_client_id() ? 'yes' : 'no') : 'no_provider';
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] create_order FAIL: not configured (client_id: ' . $has_id . ')');
            return new WP_Error('paypal_not_configured', 'PayPal is not configured (client_id: ' . $has_id . ', flow: ' . ($flow_id ?: 'none') . ')', ['status' => 500]);
        }

        // List price or native coupon (fixed final $ / percent). Server validates coupon.
        $payable = $this->flosc_resolve_native_payable_amount($offer, $coupon_code);
        if (is_wp_error($payable)) {
            return $payable;
        }
        $amount = floatval($payable['amount']);
        if ($amount <= 0) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] create_order FAIL: no price for offer "' . $offer_id . '"');
            return new WP_Error('no_price', 'No price configured for offer "' . $offer_id . '". Set the price in FLOSC Offers tab.', ['status' => 400]);
        }

        // v5.0.7: Use offer currency, fall back to provider's centralized currency.
        // This MUST match the currency the PayPal SDK was loaded with.
        $currency = strtoupper($offer['pricing']['currency'] ?? '');
        if (empty($currency)) {
            $currency = $paypal->get_currency();
        }

        $user = wp_get_current_user();

        $session_id = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
        $purchase_uuid = '';
        if (function_exists('flosc_paypal_purchase_intent_create')) {
            $intent = flosc_paypal_purchase_intent_create([
                'offer_id'   => sanitize_text_field((string) ($offer['id'] ?? $offer_id)),
                'plan_id'    => 'order', // one-time Orders API (not a billing plan)
                'plan_type'  => 'onetime',
                'amount'     => number_format((float) $amount, 2, '.', ''),
                'currency'   => $currency,
                'flow_id'    => $flow_id,
                'user_id'    => (int) ($user->ID ?? 0),
                'session_id' => $session_id,
                'mode'       => method_exists($paypal, 'get_setting') ? sanitize_key((string) $paypal->get_setting('mode', 'live')) : 'live',
            ]);
            if (is_wp_error($intent)) {
                return $intent;
            }
            $purchase_uuid = (string) ($intent['purchase_uuid'] ?? '');
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] create_order calling API: amount=' . $amount . ', currency=' . $currency . ', user_id=' . $user->ID . ', intent=' . $purchase_uuid);
        }

        $result = $paypal->create_order($user, $amount, $currency, $offer_id, $purchase_uuid);

        if (is_wp_error($result)) {
            return $result;
        }

        if (is_array($result) && $purchase_uuid !== '') {
            $result['purchase_uuid'] = $purchase_uuid;
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] === create_order SUCCESS === order_id=' . ($result['order_id'] ?? 'NONE'));
        }

        return new WP_REST_Response($result);
    }
    
    /**
     * PayPal - Capture Order (one-time Orders API).
     * After buyer approves in PayPal popup: capture funds, provision buyer account
     * if needed, grant membership from the offer. Works for logged-in buyers and
     * visitors (account created from PayPal payer email) — same model as subscriptions.
     */
    public function paypal_capture_order($request) {
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] capture_order ENDPOINT REACHED at ' . gmdate('Y-m-d H:i:s') . ' user=' . get_current_user_id());
        }

        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if (!empty($flow_id)) {
            $this->set_flow_context($flow_id);
        }

        $order_id = sanitize_text_field($request->get_param('order_id'));
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $binding_token = sanitize_text_field((string) $request->get_param('binding_token'));
        $binding_session = sanitize_text_field((string) $request->get_param('session_id'));

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] === capture_order START === order=' . $order_id . ', offer=' . $offer_id . ', flow=' . ($flow_id ?: 'none') . ', user=' . get_current_user_id());
        }

        if (empty($order_id) || empty($offer_id)) {
            return new WP_Error('missing_params', __('Missing order_id or offer_id', 'flosc'), ['status' => 400]);
        }

        $user_id = get_current_user_id();
        $is_new_user = false;
        $auth_token = '';
        $binding_record = null;

        // Visitors must present a checkout binding token before account creation / grant.
        // Logged-in buyers already have an authenticated WordPress session.
        if (!$user_id) {
            $binding_record = flosc_checkout_binding_verify($binding_token, $binding_session);
            if (!$binding_record) {
                return new WP_Error('invalid_checkout_binding', __('Missing or invalid checkout binding token.', 'flosc'), ['status' => 403]);
            }
        }

        // v8.0.1: Never block re-purchases. Always capture real PayPal payments.
        // grant_from_offer() is idempotent for features/level and additive for tokens.
        $access_manager = $this->sale_manager->access();

        $offer = $this->sale_manager->offers()->get_offer($offer_id, $flow_id ?: null);
        if (!$offer) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order FAIL: offer not found');
            }
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }

        $paypal = $this->sale_manager->get_provider('paypal');
        if (!$paypal || !$paypal->is_configured()) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order FAIL: PayPal not configured');
            }
            return new WP_Error('paypal_not_configured', __('PayPal is not configured', 'flosc'), ['status' => 500]);
        }

        $capture_result = $paypal->capture_order($order_id);
        if (is_wp_error($capture_result)) {
            return $capture_result;
        }

        // Forward PayPal error details (e.g. INSTRUMENT_DECLINED) to frontend
        if (isset($capture_result['success']) && $capture_result['success'] === false) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order: PayPal error forwarded — ' . ($capture_result['issue'] ?? $capture_result['message'] ?? 'unknown'));
            }
            return new WP_REST_Response($capture_result, 422);
        }

        // PAY-02: offer from server purchase intent (custom_id UUID) or legacy bound offer_id.
        $purchase_uuid  = sanitize_text_field((string) ($capture_result['purchase_uuid'] ?? ''));
        $bound_offer_id = sanitize_text_field((string) ($capture_result['offer_id'] ?? ''));
        $intent         = false;
        if ($purchase_uuid !== '' && function_exists('flosc_paypal_purchase_intent_get')) {
            $intent = flosc_paypal_purchase_intent_get($purchase_uuid);
            if (is_array($intent) && !empty($intent['offer_id'])) {
                $bound_offer_id = sanitize_text_field((string) $intent['offer_id']);
            }
            if (is_array($intent) && ($intent['status'] ?? '') === 'fulfilled') {
                return new WP_REST_Response([
                    'success'           => true,
                    'already_fulfilled' => true,
                    'order_id'          => $order_id,
                    'offer_id'          => $bound_offer_id,
                ], 200);
            }
        }
        if ($bound_offer_id === '') {
            return new WP_Error(
                'unbound_payment',
                __('PayPal order is not bound to an offer and cannot grant access', 'flosc'),
                ['status' => 400]
            );
        }
        if ($offer_id !== '' && !hash_equals($bound_offer_id, $offer_id)) {
            return new WP_Error(
                'offer_mismatch',
                __('Payment does not match the requested offer', 'flosc'),
                ['status' => 403]
            );
        }
        $offer_id = $bound_offer_id;
        $offer = $this->sale_manager->offers()->get_offer($offer_id, $flow_id ?: null);
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        if (method_exists($this->sale_manager, 'validate_offer_for_purchase')) {
            $offer_ok = $this->sale_manager->validate_offer_for_purchase($offer, 'paid');
            if (is_wp_error($offer_ok)) {
                return $offer_ok;
            }
        }

        // Amount/currency: intent expected when present, else offer list price.
        $expected = 0.0;
        $expected_cur = 'USD';
        if (is_array($intent) && isset($intent['amount'])) {
            $expected     = floatval($intent['amount']);
            $expected_cur = strtoupper(sanitize_text_field((string) ($intent['currency'] ?? 'USD'))) ?: 'USD';
        } elseif (is_array($offer['pricing'] ?? null) && array_key_exists('price', $offer['pricing'])) {
            $expected     = floatval($offer['pricing']['price']);
            $expected_cur = strtoupper((string) ($offer['pricing']['currency'] ?? $offer['currency'] ?? 'USD'));
        } elseif (array_key_exists('price', $offer)) {
            $expected     = floatval($offer['price']);
            $expected_cur = strtoupper((string) ($offer['pricing']['currency'] ?? $offer['currency'] ?? 'USD'));
        }
        $captured_amt = floatval($capture_result['amount'] ?? 0);
        $captured_cur = strtoupper((string) ($capture_result['currency'] ?? 'USD'));
        if ($expected > 0 && abs($captured_amt - $expected) > 0.011) {
            return new WP_Error(
                'payment_mismatch',
                __('PayPal capture amount does not match the offer price', 'flosc'),
                ['status' => 403]
            );
        }
        if ($expected > 0 && $expected_cur !== '' && $captured_cur !== '' && $expected_cur !== $captured_cur) {
            return new WP_Error(
                'payment_mismatch',
                __('PayPal capture currency does not match the offer', 'flosc'),
                ['status' => 403]
            );
        }

        // Only check user mismatch for logged-in users (user_id > 0)
        $captured_user_id = $capture_result['user_id'] ?? null;
        if ($user_id > 0 && $captured_user_id && intval($captured_user_id) > 0 && intval($captured_user_id) !== $user_id) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                flosc_log('[FLOSC-PAYPAL] capture_order FAIL: user mismatch (captured=' . $captured_user_id . ', current=' . $user_id . ')');
            }
            return new WP_Error('user_mismatch', __('Payment does not belong to this user', 'flosc'), ['status' => 403]);
        }

        // Visitor purchase: create or link WP account from PayPal payer email.
        if (!$user_id) {
            $payer_email = sanitize_email((string) ($capture_result['payer_email'] ?? ''));
            $payer_name = sanitize_text_field((string) ($capture_result['payer_name'] ?? ''));
            if ($payer_email === '') {
                return new WP_Error('no_email', __('Could not retrieve email from PayPal payment.', 'flosc'), ['status' => 400]);
            }

            $existing_user = get_user_by('email', $payer_email);
            if ($existing_user) {
                $user_id = (int) $existing_user->ID;
            } else {
                $username = $this->generate_username_from_email($payer_email);
                $password = wp_generate_password(16, true, true);
                $created_id = wp_create_user($username, $password, $payer_email);
                if (is_wp_error($created_id)) {
                    return new WP_Error('user_creation_failed', 'Could not create account: ' . $created_id->get_error_message(), ['status' => 500]);
                }
                $user_id = (int) $created_id;
                $user = get_user_by('id', $user_id);
                if ($user) {
                    $user->set_role(apply_filters('flosc_default_user_role', 'subscriber'));
                }
                if ($payer_name !== '') {
                    $name_parts = explode(' ', $payer_name, 2);
                    wp_update_user([
                        'ID' => $user_id,
                        'first_name' => sanitize_text_field((string) ($name_parts[0] ?? '')),
                        'last_name' => sanitize_text_field((string) ($name_parts[1] ?? '')),
                        'display_name' => $payer_name,
                    ]);
                }
                update_user_meta($user_id, '_flosc_registration_method', 'paypal_purchase');
                update_user_meta($user_id, '_flosc_registered_at', current_time('mysql'));
                do_action('flosc_user_registered', $user_id, 'paypal_purchase', ['flow_id' => $flow_id]);
                $is_new_user = true;
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    flosc_log('[FLOSC-PAYPAL] Created new user from one-time PayPal capture: ' . $payer_email . ' (ID: ' . $user_id . ')');
                }
            }
        }

        if ($user_id <= 0) {
            return new WP_Error('no_buyer', __('Could not resolve buyer account for this payment.', 'flosc'), ['status' => 500]);
        }

        $current_flow = $this->get_current_flow();
        $capture_flow_id = $current_flow ? ($current_flow['id'] ?? '') : '';
        if ($capture_flow_id === '' && !empty($flow_id)) {
            $capture_flow_id = sanitize_key($flow_id);
        }

        $transaction = [
            'transaction_id' => $capture_result['transaction_id'],
            'provider'       => 'paypal',
            'amount'         => $capture_result['amount'],
            'currency'       => $capture_result['currency'],
            'success'        => true,
            'status'         => 'completed',
            'flow_id'        => $capture_flow_id,
        ];

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] capture_order: granting access for user=' . $user_id . ', txn=' . $capture_result['transaction_id'] . ', offer=' . $offer_id);
        }

        // PAY-02: claim txn→offer→user before grant (blocks reuse / double-fulfill).
        $fulfill = $this->sale_manager->fulfill_settled_purchase($user_id, $offer, 'paypal', $transaction);
        if (is_wp_error($fulfill)) {
            return $fulfill;
        }

        if ($purchase_uuid !== '' && function_exists('flosc_paypal_purchase_intent_mark_fulfilled')) {
            flosc_paypal_purchase_intent_mark_fulfilled($purchase_uuid, (string) ($capture_result['transaction_id'] ?? $order_id), $user_id);
        }

        set_transient('flosc_just_purchased_' . $user_id, true, 300);
        if ($capture_flow_id) {
            update_user_meta($user_id, '_flosc_purchased_flow_id', $capture_flow_id);
        }
        if (!empty($offer_id)) {
            update_user_meta($user_id, '_flosc_purchased_offer_id', sanitize_text_field((string) $offer_id));
        } elseif (!empty($offer['id'])) {
            update_user_meta($user_id, '_flosc_purchased_offer_id', sanitize_text_field((string) $offer['id']));
        }

        // One-time product token credit (sold offer.tokens or flow Token Management defaults).
        $txn_id = (string) ($capture_result['transaction_id'] ?? $order_id);
        $token_topup = $this->flosc_apply_product_token_credit(
            $user_id,
            $capture_flow_id,
            'onetime',
            [
                'idempotency_key' => 'onetime_' . $txn_id,
                'offer' => $offer,
                'offer_id' => $offer_id ?: ($offer['id'] ?? ''),
                'reason' => 'PayPal one-time product token credit',
            ]
        );

        $member_level = $offer['grants']['level'] ?? $offer['grants_level'] ?? 'member';
        $already_logged_in = is_user_logged_in() && (int) get_current_user_id() === (int) $user_id;
        $login_handoff = 'email_link_sent';
        $claim_key = 'flosc_order_session_claim_' . md5($txn_id);

        // Instant session only for a brand-new account created in this request,
        // once per transaction, with a valid binding token (same rules as subscriptions).
        if (!$already_logged_in
            && $is_new_user
            && false === get_transient($claim_key)
            && !empty($binding_record)) {
            if (flosc_issue_post_purchase_session($user_id)) {
                set_transient($claim_key, time(), WEEK_IN_SECONDS);
                $auth_token = $this->generate_flosc_auth_token($user_id);
                $login_handoff = 'session_issued';
            }
        } elseif ($already_logged_in) {
            $login_handoff = 'already_authenticated';
        }

        // flosc_purchase_completed already fired inside fulfill_settled_purchase (skip on already_fulfilled re-fire).
        if (empty($fulfill['already_fulfilled'])) {
            // Flow id may not have been on the fulfill payload; emit a flow-enriched companion only when needed.
            // Primary purchase_completed is from fulfill_settled_purchase — do not double-grant listeners.
            do_action('flosc_paypal_capture_completed', $user_id, [
                'offer_id'       => $offer_id,
                'grants_level'   => $member_level,
                'provider'       => 'paypal',
                'transaction_id' => $txn_id,
                'amount'         => $capture_result['amount'],
                'flow_id'        => $capture_flow_id,
                'timestamp'      => time(),
            ]);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC-PAYPAL] === capture_order SUCCESS === txn=' . $txn_id . ', user=' . $user_id . ', handoff=' . $login_handoff);
        }

        $user_data = get_userdata($user_id);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Access granted',
            'access' => $access_manager->get_user_access($user_id),
            'purchase_count' => (int) get_user_meta($user_id, '_flosc_purchase_count', true),
            'member_level' => get_user_meta($user_id, '_flosc_member_level', true) ?: $member_level,
            'user_id' => $user_id,
            'user_email' => $user_data->user_email ?? '',
            'user_display_name' => $user_data->display_name ?? '',
            'is_new_user' => $is_new_user,
            'auth_token' => $auth_token ?: null,
            'login_handoff' => $login_handoff,
            'token_topup' => $token_topup,
        ]);
    }
    
    

    /**
     * Bootstrap / rehydrate logged-in session for the app shell.
     *
     * When PHP rendered as visitor (WP cookie missing on this host) but the
     * browser still has flosc_auth_token (localStorage → X-FLOSC-Token), this
     * endpoint authenticates the user, sets the auth cookie for next paint,
     * and returns per-flow state + token balances from the USER PROFILE
     * (never the visitor wallet).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_session_bootstrap($request) {
        nocache_headers();

        // Accept token from header (authFetch) or body/query for recovery paths.
        if (!is_user_logged_in()) {
            $token = '';
            if (!empty($_SERVER['HTTP_X_FLOSC_TOKEN'])) {
                $token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FLOSC_TOKEN']));
            }
            if ($token === '') {
                $token = sanitize_text_field((string) ($request->get_param('auth_token') ?? ''));
            }
            if ($token !== '') {
                $uid = $this->validate_flosc_auth_token($token);
                if ($uid) {
                    wp_set_current_user($uid);
                    $this->flosc_token_auth_used = true;
                    if ($this->first_party_auth && method_exists($this->first_party_auth, 'mark_flosc_token_auth_used')) {
                        $this->first_party_auth->mark_flosc_token_auth_used();
                    }
                }
            }
        }

        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'state'   => 'visitor',
                'user'    => null,
            ], 200);
        }

        $user_id = get_current_user_id();
        $flow_raw = (string) ($request->get_param('flow_id') ?? '');
        if ($flow_raw === '') {
            $flow = $this->get_current_flow();
            if (is_array($flow)) {
                $flow_raw = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
            }
        }
        $flow_stem = $this->flosc_normalize_flow_stem($flow_raw);
        if ($flow_stem === '' || $flow_stem === 'default') {
            $this->set_flow_context($flow_raw);
            $flow = $this->get_current_flow();
            if (is_array($flow)) {
                $flow_stem = $this->flosc_normalize_flow_stem(
                    (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? $flow_raw)
                );
            }
        }

        $payload = $this->build_app_user_payload($user_id, $flow_stem, [
            'allow_guest_grant_without_session' => true,
        ]);

        // Stick auth on this host so the next full page load is not visitor.
        $auth_token = $this->generate_flosc_auth_token($user_id);
        $this->set_flosc_auth_cookie($auth_token);

        return new WP_REST_Response([
            'success'   => true,
            'state'     => $payload['state'] ?? 'guest',
            'flow_id'   => $flow_stem,
            'user'      => $payload,
            'authToken' => $auth_token,
        ]);
    }

    /**
     * Build FLOSC_USER payload for a logged-in user on one flow.
     * Tokens always come from user meta for that flow (profile wallet), not visitor session.
     *
     * @param int    $user_id
     * @param string $flow_stem
     * @param array  $args {
     *     @type bool $allow_guest_grant_without_session Apply V→G without visitor cookie.
     *     @type bool $consume_event_transients          Clear justLoggedIn etc. (page paint only).
     * }
     * @return array
     */
    public function build_app_user_payload($user_id, $flow_stem = '', $args = []) {
        $user_id = absint($user_id);
        $args    = wp_parse_args($args, [
            'allow_guest_grant_without_session' => false,
            'consume_event_transients'          => false,
        ]);
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        $flow_stem = $this->flosc_normalize_flow_stem($flow_stem);
        if ($flow_stem === '' || $flow_stem === 'default') {
            $current = $this->get_current_flow();
            if (is_array($current)) {
                $flow_stem = $this->flosc_normalize_flow_stem(
                    (string) ($current['ivr_file'] ?? $current['ivr'] ?? $current['id'] ?? '')
                );
            }
        }

        // Authenticated payload path: guest or member only (never visitor).
        $user_state = $this->sale_manager->access()->get_simple_state($user_id, $flow_stem);
        if ($user_state !== 'member') {
            $user_state = 'guest';
        }

        $just_completed_quiz = (bool) get_transient('flosc_just_completed_quiz_' . $user_id);
        $just_logged_in      = (bool) get_transient('flosc_just_logged_in_' . $user_id);
        $just_purchased      = (bool) get_transient('flosc_just_purchased_' . $user_id);
        if (!empty($args['consume_event_transients'])) {
            if ($just_completed_quiz) {
                delete_transient('flosc_just_completed_quiz_' . $user_id);
            }
            if ($just_logged_in) {
                delete_transient('flosc_just_logged_in_' . $user_id);
            }
            if ($just_purchased) {
                delete_transient('flosc_just_purchased_' . $user_id);
            }
        }

        $actually_purchased = (bool) get_user_meta($user_id, '_flosc_purchased', true);
        $flow_id_for_tokens = $flow_stem;
        if ($flow_id_for_tokens === '' || $flow_id_for_tokens === 'default') {
            $flow_id_for_tokens = $this->flosc_normalize_flow_stem(
                (string) get_user_meta($user_id, '_flosc_registration_flow', true)
            );
        }

        $session_for_grant = $this->flosc_resolve_visitor_session_id_for_grant();
        if ($this->flosc_user_should_receive_guest_tokens($user_id, $flow_id_for_tokens)) {
            $flow_token_balance = $this->flosc_apply_guest_token_grant_once(
                $user_id,
                $flow_id_for_tokens,
                $session_for_grant,
                !empty($args['allow_guest_grant_without_session'])
            );
        } elseif ($this->sale_manager->access()->is_member($user_id, $flow_id_for_tokens)) {
            $flow_token_balance = $this->flosc_apply_member_token_grant_once($user_id, $flow_id_for_tokens);
        } else {
            $flow_token_balance = $this->flosc_get_user_flow_token_balance($user_id, $flow_id_for_tokens);
        }

        $sale_token_balance = 0;
        $token_provider_for_user = $this->sale_manager->get_provider('tokens');
        if ($token_provider_for_user && method_exists($token_provider_for_user, 'get_balance')) {
            $sale_token_balance = (int) $token_provider_for_user->get_balance($user_id);
        }
        $guest_grant_done = (bool) get_user_meta(
            $user_id,
            $this->flosc_guest_token_grant_flag_key($flow_id_for_tokens),
            true
        );
        $member_grant_done = (bool) get_user_meta(
            $user_id,
            $this->flosc_member_token_grant_flag_key($flow_id_for_tokens),
            true
        );
        $display_tokens = ($guest_grant_done || $member_grant_done || $flow_token_balance > 0)
            ? $flow_token_balance
            : $sale_token_balance;

        $payload = [
            'id'                  => $user_id,
            'name'                => $user->display_name,
            'email'               => $user->user_email,
            'avatar'              => get_avatar_url($user_id, ['size' => 40]),
            'state'               => $user_state,
            'purchased'           => $actually_purchased,
            'isAdmin'             => user_can($user_id, 'manage_options'),
            'memberLevels'        => ($this->member_access && method_exists($this->member_access, 'get_user_levels'))
                ? $this->member_access->get_user_levels($user_id)
                : [],
            'access'              => $this->sale_manager->access()->get_user_access($user_id),
            'tokens'              => $display_tokens,
            'tokenBalance'        => $display_tokens,
            'flowTokens'          => $flow_token_balance,
            'flowId'              => $flow_id_for_tokens,
            // Free lessons only when this flow’s Lessons config serves lessons.
            'freeLessonDelivered' => (bool) get_user_meta($user_id, '_flosc_free_content_item_delivered', true),
            'freeLessonsCount'    => (function () use ($user_id, $flow_stem) {
                if (!$this->flosc_flow_serves_lessons($flow_stem)) {
                    return 0;
                }
                return count(get_user_meta($user_id, '_flosc_free_content_item_numbers', true) ?: []);
            })(),
            'freeLessons'         => (function () use ($user_id, $flow_stem) {
                if (!$this->flosc_flow_serves_lessons($flow_stem)) {
                    return [];
                }
                if (!class_exists('FLOSC_Free_Content_Item_Manager')) {
                    return [];
                }
                $raw = FLOSC_Free_Content_Item_Manager::instance()->get_free_lessons($user_id);
                if (empty($raw) || !is_array($raw)) {
                    return [];
                }
                $lessons = [];
                foreach ($raw as $row) {
                    $lessons[] = [
                        'title'         => $row['title'] ?? '',
                        'content'       => $row['content'] ?? '',
                        'url'           => $row['url'] ?? '',
                        'lesson_number' => $row['lesson_number'] ?? null,
                    ];
                }
                return $lessons;
            })(),
            'lastQuizScore'       => null,
            'lastQuizId'          => null,
            'lastQuizData'        => null,
            'initialScore'        => null,
            'initialQuizId'       => null,
            'funnelCompleted'     => (bool) get_user_meta($user_id, '_flosc_funnel_completed', true),
            'justCompletedQuiz'   => $just_completed_quiz,
            'justLoggedIn'        => $just_logged_in,
            'justPurchased'       => $just_purchased,
            'loginCount'          => (int) get_user_meta($user_id, '_flosc_login_count', true),
            'completedQuizzes'    => [],
            'purchaseCount'       => (int) get_user_meta($user_id, '_flosc_purchase_count', true),
            'memberLevel'         => get_user_meta($user_id, '_flosc_member_level', true) ?: '',
            'flowStatuses'        => $this->flosc_build_user_flow_statuses($user_id, $flow_stem),
        ];

        // Quiz meta is per-user global; only attach it when THIS flow owns that quiz.
        $quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        $quiz_id   = (string) get_user_meta($user_id, '_flosc_last_quiz_id', true);
        if (!is_array($quiz_data)) {
            $quiz_data = null;
        }
        if ($this->flosc_flow_should_surface_quiz_data($flow_stem, $quiz_data, $quiz_id)) {
            $payload['lastQuizData']  = $quiz_data;
            $payload['lastQuizId']    = $quiz_id !== '' ? $quiz_id : null;
            $payload['lastQuizScore'] = get_user_meta($user_id, '_flosc_last_quiz_score', true);
            $payload['initialScore']  = get_user_meta($user_id, '_flosc_initial_score', true);
            $payload['initialQuizId'] = get_user_meta($user_id, '_flosc_initial_quiz_id', true);
            $completed = get_user_meta($user_id, '_flosc_completed_quizzes', true);
            $payload['completedQuizzes'] = is_array($completed) ? $completed : [];
        } else {
            // Do not fire justCompletedQuiz UI on flows that never ran this quiz.
            $payload['justCompletedQuiz'] = false;
        }

        return $payload;
    }
    
    public function get_token_balance($request) {
        $token_provider = $this->sale_manager->get_provider('tokens');
        $user_id = get_current_user_id();
        
        return new WP_REST_Response([
            'balance' => $token_provider->get_balance($user_id),
            'ledger' => $token_provider->get_ledger($user_id, 10),
        ]);
    }
    
    public function declare_intent($request) {
        $affiliate = $this->sale_manager->get_provider('affiliate');
        
        if (!$affiliate || !$affiliate->is_configured()) {
            return new WP_Error('not_configured', __('Affiliate system not configured', 'flosc'), ['status' => 500]);
        }
        
        $intent = $affiliate->declare_intent(get_current_user_id(), [
            'description' => sanitize_text_field($request->get_param('description')),
            'category' => sanitize_text_field($request->get_param('category') ?? 'general'),
            'expected_price' => floatval($request->get_param('expected_price') ?? 0),
            'timeframe' => sanitize_text_field($request->get_param('timeframe') ?? 'exploring'),
            'notes' => sanitize_textarea_field($request->get_param('notes') ?? ''),
        ]);
        
        return new WP_REST_Response(['intent' => $intent]);
    }
    
    public function get_intent_offers($request) {
        $intent_id = $request->get_param('id');
        $affiliate = $this->sale_manager->get_provider('affiliate');
        
        $intents = $affiliate->get_intents(get_current_user_id());
        
        if (!isset($intents[$intent_id])) {
            return new WP_Error('not_found', __('Intent not found', 'flosc'), ['status' => 404]);
        }
        
        $offers = $affiliate->find_offers_for_intent($intents[$intent_id]);
        
        return new WP_REST_Response(['offers' => $offers]);
    }
    
    public function generate_referral($request) {
        $user_id = get_current_user_id();
        $code = 'REF' . $user_id;
        $app_url = home_url('/' . get_option('flosc_app_slug', 'flosc') . '/');
        
        return new WP_REST_Response([
            'link' => add_query_arg('ref', $code, $app_url),
            'code' => $code,
        ]);
    }
    
    /**
     * v1.0.5 TASK-108: Debug endpoint for funnel state
     * Returns complete state for testing the FLOSC funnel flow
     * Only available when FLOSC_DEBUG is true
     */
    public function get_debug_funnel_state($request) {
        $user_id = get_current_user_id();
        
        // Get bridge data (class loaded at plugin init)
        $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
        
        // Get member access (class loaded at plugin init)
        $member_access = FLOSC_Member_Access::instance();
        
        // Get token balance
        $token_provider = $this->sale_manager->get_provider('tokens');
        $token_balance = $token_provider ? $token_provider->get_balance($user_id) : 0;
        
        // Get free lesson info
        $free_lesson_num = get_user_meta($user_id, '_flosc_free_content_item_number', true);
        
        return new WP_REST_Response([
            'success' => true,
            'debug' => true,
            'version' => FLOSC_VERSION,
            'user_id' => $user_id,
            'funnel_phase' => $this->determine_flosc_phase(),
            'bridge_state' => [
                'in_bridge' => $bridge_mgr->is_in_flosc_bridge_state($user_id),
                'has_profile' => $bridge_mgr->flosc_has_profile($user_id),
                'data' => $bridge_mgr->get_flosc_bridge_data($user_id),
                'weakest_category' => $bridge_mgr->get_flosc_weakest_category($user_id),
            ],
            'member_state' => [
                'is_member' => $member_access->is_member($user_id),
                'access_level' => $member_access->get_access_level($user_id),
                'member_since' => get_user_meta($user_id, '_flosc_member_since', true),
            ],
            'quiz_state' => [
                'last_score' => get_user_meta($user_id, '_flosc_last_quiz_score', true),
                'completed_at' => get_user_meta($user_id, '_flosc_quiz_completed_at', true),
                'free_lesson_number' => $free_lesson_num,
                'free_lesson_delivered' => get_user_meta($user_id, '_flosc_free_content_item_delivered', true),
            ],
            'token_state' => [
                'balance' => $token_balance,
                'signup_bonus' => $token_provider ? $token_provider->get_setting('signup_bonus', 10) : 0,
            ],
            'transients' => [
                'just_completed_quiz' => (bool) get_transient('flosc_just_completed_quiz_' . $user_id),
                'just_logged_in' => (bool) get_transient('flosc_just_logged_in_' . $user_id),
            ],
        ]);
    }
    
    /**
     * Get IVR messages for current phase and context (v9.2.6: Performance optimization)
     * v1.1.0: Return messages from related phases for members (sale+content)
     *         Also include 'always' condition messages from freeline for all phases
     * v1.2.3: Multi-flow aware - loads IVR from current flow's ivr_file
     * v1.3.8: Accept explicit flow_id/ivr_file params from frontend (REST context fix)
     */
    public function get_ivr_messages($request) {
        $phase = $this->normalize_ivr_phase($request->get_param('phase'));
        if (is_wp_error($phase)) {
            return $phase;
        }
        
        // v1.3.8: Get flow context from request (same pattern as handle_chat)
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        $ivr_file = sanitize_file_name($request->get_param('ivr_file') ?? '');
        $ivr_source = 'unknown'; // Track source for debugging
        
        // Get user context
        $user_context = $this->user_access_manager->get_user_context();
        
        // v9.2.7: Add session-based defaults (frontend handles actual session logic)
        // Backend is permissive - returns messages that COULD show
        // Frontend decides based on actual session state
        $user_context['first_show_session'] = true; // Let welcome messages through
        $user_context['first_message_after_quiz'] = $request->get_param('after_quiz') === 'true';
        $user_context['first_message_after_login'] = $request->get_param('after_login') === 'true';
        $user_context['first_message_after_purchase'] = $request->get_param('after_purchase') === 'true';

        // v1.3.8: Resolve flow runtime config (DB first, file fallback only when flow bag is empty)
        $config = flosc_resolve_flow_runtime($flow_id, $ivr_file);
        $ivr_source = $config['source'] ?? 'empty';

        $all_messages = $config['messages'] ?? [];
        $phases = $config['phases'] ?? [];
        
        // Phase access matrix (P0-E):
        // - Public content → 403
        // - Public sale → sale only (never merge content IVR)
        // - Member/admin → may merge sale+content
        $is_member = !empty($user_context['is_member'])
            || (isset($user_context['access_level']) && in_array((string) $user_context['access_level'], ['member', 'full'], true));
        if (!$is_member && is_user_logged_in() && $this->member_access) {
            $is_member = (bool) $this->member_access->is_member(get_current_user_id());
        }
        $is_admin = current_user_can('manage_options');

        if ($phase === 'content' && !$is_member && !$is_admin) {
            return new WP_Error(
                'flosc_content_phase_forbidden',
                __('Content phase requires membership.', 'flosc'),
                ['status' => 403]
            );
        }

        $phases_to_check = [$phase];
        if ($phase === 'sale' || $phase === 'content') {
            if ($is_member || $is_admin) {
                $phases_to_check = ['sale', 'content'];
            } else {
                // Public sale funnel: sale only (no content-phase leak).
                $phases_to_check = ['sale'];
            }
        } elseif ($phase === 'login' || $phase === 'offer') {
            $phases_to_check = ['login', 'offer'];
        }
        
        // Always include freeline for 'always' condition messages (not member content).
        if (!in_array('freeline', $phases_to_check, true)) {
            $phases_to_check[] = 'freeline';
        }
        
        // Collect message IDs from all relevant phases
        $phase_message_ids = [];
        foreach ($phases_to_check as $p) {
            $ids = $phases[$p] ?? [];
            $phase_message_ids = array_merge($phase_message_ids, $ids);
        }
        $phase_message_ids = array_unique($phase_message_ids);
        
        // Initialize condition evaluator (v1.0.7: class already loaded at plugin init)
        $evaluator = new FLOSC_Condition_Evaluator($user_context);
        
        // v1.6.8: Send ALL phase-matched messages to the frontend.
        // The JS has the full session context (quiz results, timers, interaction state)
        // and handles condition evaluation with accurate real-time data.
        // Server-side filtering was blocking offers because PHP lacked session context.
        $filtered_messages = [];
        foreach ($phase_message_ids as $msg_id) {
            if (!isset($all_messages[$msg_id])) continue;
            // Concierge messages are handled entirely server-side by FLOSC_Concierge
            // (keyword-gated, AI-hosted, revealed in fragments). They must NEVER reach
            // the browser: doing so leaks the private brief into client JS AND lets the
            // frontend keyword-matcher send the raw content back as ivr_guidance, which
            // bypasses the concierge flow and dumps the whole letter at the visitor.
            if (($all_messages[$msg_id]['type'] ?? '') === 'concierge') continue;
            $filtered_messages[] = $all_messages[$msg_id];
        }
        
        // v1.1.0: Substitute server-side variables in message content
        // This is needed for {user_status_response} which requires PHP context
        $eval_context = array_merge($user_context, [
            'user_name' => is_user_logged_in() ? wp_get_current_user()->display_name : 'there',
        ]);
        foreach ($filtered_messages as &$msg) {
            if (!empty($msg['content'])) {
                $msg['content'] = $this->substitute_ivr_variables($msg['content'], $eval_context);
            }
        }
        unset($msg); // Break reference
        
        return new WP_REST_Response([
            'success' => true,
            'phase' => $phase,
            'phases_checked' => $phases_to_check,
            'messages' => $filtered_messages,
            'user_context' => [
                'access_level' => $user_context['access_level'],
                'is_logged_in' => is_user_logged_in(),
            ],
            // v1.3.8: Debug info for flow context
            'flow_context' => [
                'flow_id' => $flow_id ?: null,
                'ivr_file' => $ivr_file ?: null,
                'ivr_source' => $ivr_source,
            ],
        ]);
    }
    
    /**
     * v1.0.4: Get bridge data for current user (TASK-008)
     * Returns quiz state preserved between phases for personalized offer targeting
     */
    public function get_bridge_data($request) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'Not logged in'], 401);
        }
        
        // Class loaded at plugin init
        $bridge_mgr = FLOSC_Bridge_Data_Manager::instance();
        
        $bridge_data = $bridge_mgr->get_flosc_bridge_data($user_id);
        $in_bridge = $bridge_mgr->is_in_flosc_bridge_state($user_id);
        $weakest_category = $bridge_mgr->get_flosc_weakest_category($user_id);
        $has_profile = $bridge_mgr->flosc_has_profile($user_id);
        
        return new WP_REST_Response([
            'success' => true,
            'in_bridge_state' => $in_bridge,
            'has_profile' => $has_profile,
            'bridge_data' => $bridge_data,
            'weakest_category' => $weakest_category,
            'debug' => FLOSC_DEBUG ? [
                'user_id' => $user_id,
                'phase' => $this->determine_flosc_phase(),
            ] : null,
        ]);
    }
    
    /**
     * Get lessons (metadata only).
     * v3.0.8: ?quiz_only=1  → quiz-linked category only
     *         ?search=TERM   → title/content search within configured categories
     *         (default)       → all configured categories
     *
     * Pass 4: requires flow scope; non-entitled items return locked stubs only
     * (no excerpt/url/tags/phoneme premium metadata).
     */
    public function get_lessons($request) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $flow_stem = $this->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }
        $this->set_flow_context( $flow_stem );

        $quiz_only = filter_var( $request->get_param( 'quiz_only' ), FILTER_VALIDATE_BOOLEAN );
        $search    = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );

        if ( $quiz_only ) {
            $lessons = $this->lesson_manager->get_quiz_lessons();
        } elseif ( $search !== '' ) {
            $lessons = $this->lesson_manager->search_lessons( $search );
        } else {
            $lessons = $this->lesson_manager->get_all_lessons();
        }

        if ( ! is_array( $lessons ) ) {
            $lessons = array();
        }

        $free_lesson_id = absint( get_user_meta( $user_id, '_flosc_free_content_item_id', true ) );
        $out            = array();
        foreach ( $lessons as $lesson ) {
            if ( ! is_array( $lesson ) ) {
                continue;
            }
            $lesson_id = absint( $lesson['id'] ?? 0 );
            $is_free   = ( $free_lesson_id > 0 && $free_lesson_id === $lesson_id );
            if ( $this->lesson_manager->user_can_access( $user_id, $lesson_id, $is_free ) ) {
                $out[] = $lesson;
                continue;
            }
            // Locked catalog stub — no premium metadata leakage.
            $out[] = array(
                'id'     => $lesson_id,
                'title'  => sanitize_text_field( (string) ( $lesson['title'] ?? '' ) ),
                'locked' => true,
            );
        }

        return new WP_REST_Response(
            array(
                'lessons'  => $out,
                'search'   => $search !== '' ? $search : null,
                'flow_id'  => $flow_stem,
            )
        );
    }

    /**
     * Get single lesson with content (Pass 4: flow + entitlement).
     */
    public function get_lesson($request) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $flow_stem = $this->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }
        $this->set_flow_context( $flow_stem );

        $lesson_id = absint( $request->get_param( 'id' ) );
        if ( $lesson_id <= 0 ) {
            return new WP_Error(
                'flosc_lesson_not_found',
                __( 'Lesson not found.', 'flosc' ),
                array( 'status' => 404 )
            );
        }

        $free_lesson_id = absint( get_user_meta( $user_id, '_flosc_free_content_item_id', true ) );
        $is_free_lesson = ( $free_lesson_id > 0 && $free_lesson_id === $lesson_id );

        if ( ! $this->lesson_manager->user_can_access( $user_id, $lesson_id, $is_free_lesson ) ) {
            return new WP_Error(
                'flosc_lesson_forbidden',
                __( 'Upgrade to access this lesson.', 'flosc' ),
                array( 'status' => 403 )
            );
        }

        $lesson = $this->lesson_manager->get_lesson( $lesson_id );
        if ( ! $lesson ) {
            return new WP_Error(
                'flosc_lesson_not_found',
                __( 'Lesson not found.', 'flosc' ),
                array( 'status' => 404 )
            );
        }

        if ( $is_free_lesson && ! get_user_meta( $user_id, '_flosc_free_content_item_delivered', true ) ) {
            update_user_meta( $user_id, '_flosc_free_content_item_delivered', current_time( 'mysql' ) );
        }

        return new WP_REST_Response(
            array(
                'lesson'  => $lesson,
                'flow_id' => $flow_stem,
            )
        );
    }
    
    /**
     * Store pre-login quiz score (for visitors)
     * v9.4.2: Uses signed cookies to prevent score forgery
     */
    public function store_prelogin_score($request) {
        $score_data = [
            'score' => intval($request->get_param('score')),
            'quiz_id' => sanitize_key((string) ($request->get_param('quiz_id') ?? '')),
            'correct' => $request->get_param('correct') ?? [],
            'incorrect' => $request->get_param('incorrect') ?? [],
            'quiz_type' => sanitize_text_field($request->get_param('quiz_type') ?? ''),
            'ranked_worst_lessons' => $request->get_param('ranked_worst_lessons') ?? [],
            'timestamp' => time(),
        ];
        
        // v9.4.2: Store in SIGNED cookie to prevent forgery
        // JS will also store in localStorage as backup (but server only trusts signed cookie)
        $this->set_signed_cookie('flosc_prelogin_score', $score_data, HOUR_IN_SECONDS);
        
        // v3.0.2: For LOGGED-IN users, also fire flosc_quiz_completed so bridge data
        // and free lesson assignment happen immediately (not just for pre-login visitors)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $quiz_id = $score_data['quiz_id'];
            $score = $score_data['score'];
            
            // Store quiz meta (mirrors store_quiz_result)
            update_user_meta($user_id, '_flosc_last_quiz_id', $quiz_id);
            update_user_meta($user_id, '_flosc_last_quiz_score', $score);
            update_user_meta($user_id, '_flosc_quiz_completed_at', time() * 1000);
            
            $completed = get_user_meta($user_id, '_flosc_completed_quizzes', true) ?: [];
            if (!in_array($quiz_id, $completed)) {
                $completed[] = $quiz_id;
                update_user_meta($user_id, '_flosc_completed_quizzes', $completed);
            }
            
            do_action('flosc_quiz_completed', $score_data, $user_id);
            set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);
            
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v3.0.2: /store-score fired flosc_quiz_completed for logged-in user {$user_id}, score {$score}%, quiz {$quiz_id}");
        }
        
        return new WP_REST_Response([
            'stored' => true,
            'logged_in' => is_user_logged_in(),
        ]);
    }

    /**
     * v8.0.0: Store a visitor's audio recording for deferred server-side scoring.
     *
     * Visitors record 5 phrases but their audio is NOT sent to the pronunciation API.
     * Instead it's saved to a temp directory keyed by a Michel-timestamp tempID.
     * After registration (visitor → guest), score_visitor_audio() sends the files
     * to the pronunciation API server-side and stores the results in user meta.
     *
     * Directory: wp-content/uploads/flosc-temp/{tempID}/
     * Files:     phrase-{n}.webm + metadata.json
     * Cleanup:   flosc_cleanup_visitor_audio cron deletes dirs older than 36 hours.
     */
    public function store_visitor_audio($request) {
        $files = $request->get_file_params();
        if (empty($files['audio']) || $files['audio']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('no_audio', __('No audio file received', 'flosc'), ['status' => 400]);
        }

        // 2MB per-phrase limit — 5-10 seconds of webm/opus is typically 50-150KB
        if ($files['audio']['size'] > 2 * 1024 * 1024) {
            return new WP_Error('too_large', __('Audio file exceeds 2MB limit', 'flosc'), ['status' => 400]);
        }

        $phrase_num = intval($request->get_param('phrase_num'));
        if ($phrase_num < 1 || $phrase_num > 10) {
            return new WP_Error('bad_phrase', __('Invalid phrase number', 'flosc'), ['status' => 400]);
        }

        $phrase_text = sanitize_text_field($request->get_param('phrase_text') ?? '');
        $format = sanitize_text_field($request->get_param('format') ?? 'webm');
        $target_ipa_json = $request->get_param('target_ipa') ?? '{}';

        // Get or create tempID (Michel timestamp + 5 hex chars)
        // Format: 2026-03m-08d-14h-30m-45s-a1b2c
        // v8.0.0 FIX: Accept temp_id from request body first (JS tracks it client-side
        // after first upload). Fall back to signed cookie. This eliminates the failure
        // mode where the cookie doesn't round-trip across cross-domain REST calls.
        $client_temp_id = sanitize_text_field($request->get_param('temp_id') ?? '');
        if ($client_temp_id && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $client_temp_id)) {
            $temp_id = $client_temp_id;
        } else {
            $temp_id = $this->get_signed_cookie('flosc_visitor_temp_id');
        }
        if (!$temp_id || !is_string($temp_id)) {
            $temp_id = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-'
                     . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's-'
                     . substr(bin2hex(random_bytes(3)), 0, 5);
        }
        // Always set/refresh the cookie for the registration handler
        $this->set_signed_cookie('flosc_visitor_temp_id', $temp_id, 36 * HOUR_IN_SECONDS);

        // Validate tempID format: YYYY-MMm-DDd-HHh-MMm-SSs-XXXXX
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $temp_id)) {
            return new WP_Error('bad_temp_id', __('Invalid session', 'flosc'), ['status' => 400]);
        }

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/flosc-temp/' . $temp_id;

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            // Block direct HTTP access to audio files (uploads-bound via FLOSC_Filesystem).
            if ( isset( $this->filesystem ) && is_object( $this->filesystem ) ) {
                $this->filesystem->protect_uploads_dir_with_htaccess( $temp_dir );
            }
        }

        // Whitelist extensions
        $ext = in_array($format, ['webm', 'mp4', 'ogg'], true) ? $format : 'webm';
        $filename = 'phrase-' . $phrase_num . '.' . $ext;
        $filepath = $temp_dir . '/' . $filename;

        $tmp_audio = $files['audio']['tmp_name'] ?? '';
        if (empty($tmp_audio) || !is_uploaded_file($tmp_audio)) {
            return new WP_Error('write_failed', __('Invalid uploaded audio', 'flosc'), ['status' => 400]);
        }

        $uploaded_audio = flosc_fs_get_contents($tmp_audio);
        if ($uploaded_audio === false || !$this->write_file_safely($filepath, $uploaded_audio)) {
            return new WP_Error('write_failed', __('Could not save audio', 'flosc'), ['status' => 500]);
        }

        // Update metadata.json (append phrase data)
        $meta_path = $temp_dir . '/metadata.json';
        $default_audio_quiz_id = flosc_get_setting('default_audio_quiz_id', '');
        $meta = file_exists($meta_path) ? json_decode(flosc_fs_get_contents($meta_path), true) : [
            'quiz_id' => $default_audio_quiz_id,
            'quiz_type' => 'ipa_audio',
            'created_at' => $temp_id,
            'phrases' => [],
        ];

        // Replace existing phrase entry if re-recorded, otherwise append
        $meta['phrases'] = array_values(array_filter($meta['phrases'], function($p) use ($phrase_num) {
            return ($p['num'] ?? 0) !== $phrase_num;
        }));
        $meta['phrases'][] = [
            'num' => $phrase_num,
            'text' => $phrase_text,
            'format' => $ext,
            'file' => $filename,
            'target_ipa' => json_decode($target_ipa_json, true) ?: [],
        ];

        // Sort by phrase number
        usort($meta['phrases'], function($a, $b) { return $a['num'] - $b['num']; });
        if (!$this->write_json_atomic($meta_path, $meta)) {
            return new WP_Error('write_failed', __('Could not update session metadata', 'flosc'), ['status' => 500]);
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: Stored visitor audio phrase-{$phrase_num} in {$temp_id}/ (" . filesize($filepath) . " bytes)");
        }

        return new WP_REST_Response([
            'stored' => true,
            'phrase' => $phrase_num,
            'temp_id' => $temp_id,
        ]);
    }

    /**
     * Store browser-computed quiz data in WordPress user meta.
     *
    * The browser already scored each phrase against the flow-configured pronunciation API during the quiz.
     * This method accepts those results, normalizes the data shape, stores in user meta
     * via store_quiz_score(), and moves audio files from flosc-temp/ to flosc-users/.
     *
     * This replaces the server-side re-scoring approach (score_visitor_audio) which
     * timed out on ChemiCloud shared hosting (5 phrases × 30s = 150s > 60s web server timeout).
     */
    private function store_browser_quiz_data($user_id, $quiz_data, $temp_id = '') {
        // Validate and normalize quiz data from browser
        $score = intval($quiz_data['score'] ?? 0);
        if ($score < 0 || $score > 100) return false;
        $default_audio_quiz_id = flosc_get_setting('default_audio_quiz_id', '');

        $score_data = [
            'quiz_id'              => sanitize_key($quiz_data['quizId'] ?? $quiz_data['quiz_id'] ?? $default_audio_quiz_id),
            'quiz_type'            => 'ipa_audio',
            'score'                => $score,
            'correct'              => [],
            'incorrect'            => [],
            'ranked_worst_lessons' => [],
            'timestamp'            => time(),
            'session_id'           => $temp_id,
            'ranked_phonemes'      => [],
            'phrase_results'       => [],
        ];

        // phraseResults: array of {phrase, data} — STT payloads from the browser (schema-sanitize, depth-capped).
        if (!empty($quiz_data['phraseResults']) && is_array($quiz_data['phraseResults'])) {
            $score_data['phrase_results'] = array_map(function ($pr) {
                if (!is_array($pr)) {
                    return array(
                        'phrase' => '',
                        'data'   => array(),
                    );
                }
                return array(
                    'phrase' => sanitize_text_field((string) ($pr['phrase'] ?? '')),
                    'data'   => $this->flosc_sanitize_quiz_nested_value($pr['data'] ?? array(), 0, 6),
                );
            }, array_slice(array_values($quiz_data['phraseResults']), 0, 20));
        }

        // rankedPhonemes: array of IPA strings (worst → best)
        if (!empty($quiz_data['rankedPhonemes']) && is_array($quiz_data['rankedPhonemes'])) {
            $score_data['ranked_phonemes'] = array_map('sanitize_text_field', array_slice($quiz_data['rankedPhonemes'], 0, 30));
        }

        // Rebuild incorrect lesson numbers and ranked_worst_lessons from ranked phonemes + phoneme map
        $phoneme_map = json_decode(flosc_get_setting('audio_quiz_phoneme_lesson_map', '{}'), true) ?: [];
        if ($phoneme_map && $score_data['ranked_phonemes']) {
            $incorrect = [];
            $ranked_worst = [];
            // Try to get scores from ranked_worst_lessons (JS sends them)
            $js_scores = [];
            foreach (($score_data['ranked_worst_lessons'] ?? []) as $rwl) {
                if (isset($rwl['ipa'], $rwl['score'])) {
                    $js_scores[$rwl['ipa']] = floatval($rwl['score']);
                }
            }
            foreach (array_slice($score_data['ranked_phonemes'], 0, 10) as $ipa) {
                if (isset($phoneme_map[$ipa])) {
                    $val = $phoneme_map[$ipa];
                    $lessons = is_array($val) ? array_map('intval', $val) : [intval($val)];
                    $entry = ['ipa' => $ipa, 'lessons' => $lessons];
                    if (isset($js_scores[$ipa])) $entry['score'] = $js_scores[$ipa];
                    $ranked_worst[] = $entry;
                    foreach ($lessons as $l) $incorrect[] = $l;
                }
            }
            $score_data['incorrect'] = array_values(array_unique($incorrect));
            $score_data['ranked_worst_lessons'] = $ranked_worst;
        }

        // wordIpa: reference IPA dictionary (espeak, mw, da1ni5 for each word) — sanitize keys/values.
        if (!empty($quiz_data['wordIpa']) && is_array($quiz_data['wordIpa'])) {
            $score_data['word_ipa'] = $this->flosc_sanitize_quiz_nested_value($quiz_data['wordIpa'], 0, 5);
        }

        // Store in user meta via existing store_quiz_score()
        $this->store_quiz_score($user_id, $score_data);
        do_action('flosc_quiz_completed', $score_data, $user_id);
        set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);

        // Send score email
        $user = get_userdata($user_id);
        if ($user) {
            $this->send_score_email($user, $score_data);
        }

        // Move audio files from flosc-temp/{temp_id}/ to flosc-users/{user_id}/
        if ($temp_id) {
            $this->move_visitor_audio_to_user($user_id, $temp_id);
        }
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC: store_browser_quiz_data() — user {$user_id}, score: {$score}%");
        }
        return true;
    }

    /**
     * Depth-capped sanitizer for nested quiz JSON (STT scores, IPA maps).
     * Scalars only as text/number/bool; arrays recurse; objects dropped.
     *
     * @param mixed $value Incoming browser value.
     * @param int   $depth Current depth.
     * @param int   $max   Max depth.
     * @return mixed
     */
    private function flosc_sanitize_quiz_nested_value($value, $depth = 0, $max = 6) {
        if ($depth > $max) {
            return null;
        }
        if (is_null($value) || is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value)) {
            // Cap runaway strings from STT blobs.
            if (strlen($value) > 4000) {
                $value = substr($value, 0, 4000);
            }
            return sanitize_text_field($value);
        }
        if (!is_array($value)) {
            return null;
        }
        $out = array();
        $i   = 0;
        foreach ($value as $k => $v) {
            if ($i++ > 200) {
                break;
            }
            $key = is_string($k) ? sanitize_key($k) : (is_int($k) ? $k : sanitize_key((string) $k));
            if ($key === '' && !is_int($k)) {
                continue;
            }
            $clean = $this->flosc_sanitize_quiz_nested_value($v, $depth + 1, $max);
            if (null === $clean && !is_null($v) && !is_array($v)) {
                continue;
            }
            $out[ is_int($k) ? $k : $key ] = $clean;
        }
        return $out;
    }

    /**
     * Move visitor audio files from flosc-temp/{temp_id}/ to flosc-users/{user_id}/.
     * No scoring — just file relocation for permanent storage in the user's profile.
     */
    private function move_visitor_audio_to_user($user_id, $temp_id) {
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $temp_id)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/flosc-temp/' . $temp_id;
        if (!is_dir($temp_dir)) return false;

        $user_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id;
        if (!file_exists($user_dir)) {
            wp_mkdir_p($user_dir);
            if ( isset( $this->filesystem ) && is_object( $this->filesystem ) ) {
                $this->filesystem->protect_uploads_dir_with_htaccess( $user_dir );
            }
        }

        // Per-session storage: flosc-users/{user_id}/sessions/{session_id}/
        $session_dir = $user_dir . '/sessions/' . $temp_id;
        wp_mkdir_p($session_dir);

        foreach (glob($temp_dir . '/*') as $file) {
            $dest = $session_dir . '/' . basename($file);
            $this->move_file_safely($file, $dest);
        }

        // Prefer MP4 for widest playback support, but keep original source files.
        $this->ensure_session_mp4_copies($session_dir);

        $this->delete_file_safely($temp_dir . '/.htaccess');
        $this->delete_directory_safely($temp_dir);
        return true;
    }

    /**
     * Normalize phrase metadata for playback without local shell conversion.
     *
     * Local transcoding is intentionally disabled. Playback uses existing files
     * only, preferring mp4 when present. Original source files are retained.
     */
    private function ensure_session_mp4_copies($session_dir) {
        if (!is_dir($session_dir)) {
            return false;
        }

        $updated = false;
        $meta_path = $session_dir . '/metadata.json';
        if (file_exists($meta_path)) {
            $meta = json_decode((string) flosc_fs_get_contents($meta_path), true);
            if (is_array($meta) && !empty($meta['phrases']) && is_array($meta['phrases'])) {
                $conversion_targets = [];
                foreach ($meta['phrases'] as &$phrase) {
                    $num = intval($phrase['num'] ?? 0);
                    if ($num <= 0) {
                        continue;
                    }
                    $current_file = (string) ($phrase['file'] ?? '');

                    $ready = null;
                    foreach (['mp4', 'm4a', 'wav'] as $ext) {
                        $name = 'phrase-' . $num . '.' . $ext;
                        if (file_exists($session_dir . '/' . $name)) {
                            $ready = ['file' => $name, 'format' => $ext];
                            break;
                        }
                    }

                    if ($ready) {
                        if (($phrase['file'] ?? '') !== $ready['file'] || ($phrase['format'] ?? '') !== $ready['format'] || ($phrase['playback_status'] ?? '') !== 'ready') {
                            $phrase['file'] = $ready['file'];
                            $phrase['format'] = $ready['format'];
                            $phrase['playback_file'] = $ready['file'];
                            $phrase['playback_status'] = 'ready';
                            $updated = true;
                        }
                        continue;
                    }

                    $has_source = false;
                    foreach (['webm', 'ogg'] as $ext) {
                        $name = 'phrase-' . $num . '.' . $ext;
                        if (file_exists($session_dir . '/' . $name)) {
                            $has_source = true;
                            if ($current_file === '' || !file_exists($session_dir . '/' . $current_file)) {
                                $phrase['file'] = $name;
                                $phrase['format'] = $ext;
                                $updated = true;
                            }
                            $conversion_targets[] = ['phrase_num' => $num, 'source_format' => $ext];
                            break;
                        }
                    }

                    if ($has_source && ($phrase['playback_status'] ?? '') !== 'processing') {
                        $phrase['playback_status'] = 'processing';
                        $updated = true;
                    }
                }
                unset($phrase);

                $session_id = basename($session_dir);
                $already_attempted = !empty($meta['conversion']['attempted_at']);
                if (!$already_attempted && !empty($conversion_targets)) {
                    $dispatch = $this->dispatch_remote_playback_conversion($session_id, $conversion_targets);
                    $saved_provider = strtolower((string) flosc_get_setting('audio_conversion_provider', 'none'));
                    $meta['conversion'] = [
                        'provider' => $saved_provider,
                        'attempted_at' => $this->get_utc_mts(),
                        'status' => $dispatch['status'] ?? 'unknown',
                    ];
                    $updated = true;
                }

                if ($updated) {
                    $this->write_json_atomic($meta_path, $meta);
                }
            }
        }

        return $updated;
    }

    /**
     * Find whether a session folder already exists under another user's audio tree.
     * Returns the owner user ID, or 0 when no owner is found.
     */
    private function find_session_owner_user_id($session_id) {
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $session_id)) {
            return 0;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/flosc-users';
        if (!is_dir($base_dir)) {
            return 0;
        }

        $matches = glob($base_dir . '/*/sessions/' . $session_id, GLOB_ONLYDIR);
        if (!is_array($matches) || empty($matches)) {
            return 0;
        }

        foreach ($matches as $path) {
            if (preg_match('#/flosc-users/(\d+)/sessions/#', $path, $m)) {
                $owner_id = intval($m[1]);
                if ($owner_id > 0) {
                    return $owner_id;
                }
            }
        }

        return 0;
    }

    /**
     * v8.0.0: Pull quiz session data (scores + audio) from Digital Ocean API.
     *
     * Called during registration or login when a session_id is available.
     * The DO API scored each phrase during the quiz and saved audio + results
     * in /opt/sessions/sessions/{session_id}/. This method:
     *   1. Fetches the finalized summary from GET /session/{session_id}
     *   2. Downloads each phrase audio file to flosc-users/{user_id}/
     *   3. Stores scores in user meta via store_quiz_score()
     *   4. Fires flosc_quiz_completed hook (triggers Free Lesson Manager)
     *   5. Sends score email
     *
     * @param int    $user_id     WordPress user ID
     * @param string $session_id  Michel-timestamped session ID from DO
     * @return bool  True on success, false on failure
     */
    private function pull_session_from_do($user_id, $session_id) {
        // Validate session_id format
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $session_id)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_session_from_do — invalid session_id: {$session_id}");
            return false;
        }

        // Guardrail: a session_id must not be attached to multiple different users.
        $existing_owner = $this->find_session_owner_user_id($session_id);
        if ($existing_owner && intval($existing_owner) !== intval($user_id)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_session_from_do — blocked cross-user session reuse: session {$session_id}, requested user {$user_id}, existing owner {$existing_owner}");
            }
            return false;
        }

        $api_base = untrailingslashit(flosc_get_setting('ipa_api_base_url', ''));

        // 1. Fetch session summary (scores, ranked phonemes, phrase results)
        $response = flosc_safe_remote_request('GET', $api_base . '/session/' . $session_id, [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_session_from_do — GET /session failed: " . $response->get_error_message());
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_session_from_do — GET /session returned HTTP {$status}");
            return false;
        }

        $session_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$session_data || empty($session_data['phrase_results'])) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: pull_session_from_do — empty or invalid session data");
            return false;
        }

        $score = intval($session_data['score'] ?? 0);
        $ranked_phonemes = $session_data['ranked_phonemes'] ?? [];

        // 2. Build phrase_results array in the format store_quiz_score() expects
        $phrase_results = [];
        foreach ($session_data['phrase_results'] as $pr) {
            $phrase_results[] = [
                'phrase' => $pr['phrase'] ?? '',
                'data'   => $pr['result'] ?? $pr['data'] ?? [],
            ];
        }

        // 3. Map worst phonemes to lesson numbers via admin phoneme-lesson map
        $phoneme_map = json_decode(flosc_get_setting('audio_quiz_phoneme_lesson_map', '{}'), true) ?: [];
        $incorrect = [];
        // ranked_phonemes from DO is IPA strings only (no scores).
        // phoneme_scores may be available from session_data.
        $do_phoneme_scores = [];
        foreach (($session_data['phoneme_scores'] ?? []) as $ipa => $scores) {
            if (is_array($scores) && count($scores) > 0) {
                $do_phoneme_scores[$ipa] = array_sum($scores) / count($scores);
            }
        }
        $ranked_worst_lessons = [];
        foreach (array_slice($ranked_phonemes, 0, 10) as $ipa) {
            if (isset($phoneme_map[$ipa])) {
                $val = $phoneme_map[$ipa];
                $lessons = array_map('intval', is_array($val) ? $val : [$val]);
                $entry = ['ipa' => $ipa, 'lessons' => $lessons];
                if (isset($do_phoneme_scores[$ipa])) $entry['score'] = $do_phoneme_scores[$ipa];
                $ranked_worst_lessons[] = $entry;
                foreach ($lessons as $l) $incorrect[] = $l;
            }
        }
        $incorrect = array_values(array_unique($incorrect));

        // 4. Build score_data array
        $default_audio_quiz_id = flosc_get_setting('default_audio_quiz_id', '');
        $score_data = [
            'quiz_id'              => $default_audio_quiz_id,
            'quiz_type'            => 'ipa_audio',
            'score'                => $score,
            'correct'              => [],
            'incorrect'            => $incorrect,
            'ranked_worst_lessons' => $ranked_worst_lessons,
            'timestamp'            => time(),
            'ranked_phonemes'      => array_slice($ranked_phonemes, 0, 30),
            'phrase_results'       => $phrase_results,
            'session_id'           => $session_id,
        ];

        // 5. Store in user meta
        $this->store_quiz_score($user_id, $score_data);
        do_action('flosc_quiz_completed', $score_data, $user_id);
        set_transient('flosc_just_completed_quiz_' . $user_id, true, MINUTE_IN_SECONDS * 5);

        // 6. Download audio files from DO to flosc-users/{user_id}/sessions/{session_id}/
        $upload_dir = wp_upload_dir();
        $user_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id;
        if (!file_exists($user_dir)) {
            wp_mkdir_p($user_dir);
            if ( isset( $this->filesystem ) && is_object( $this->filesystem ) ) {
                $this->filesystem->protect_uploads_dir_with_htaccess( $user_dir );
            }
        }

        $session_dir = $user_dir . '/sessions/' . $session_id;
        wp_mkdir_p($session_dir);

        $phrase_count = count($phrase_results);
        $session_phrases = [];
        for ($n = 1; $n <= $phrase_count; $n++) {
            $audio_resp = flosc_safe_remote_request('GET', $api_base . '/session/' . $session_id . '/audio/' . $n, [
                'timeout' => 15,
            ]);

            if (!is_wp_error($audio_resp) && wp_remote_retrieve_response_code($audio_resp) === 200) {
                $content_type = wp_remote_retrieve_header($audio_resp, 'content-type');
                $ext = 'webm';
                if (strpos($content_type, 'mp4') !== false) $ext = 'mp4';
                elseif (strpos($content_type, 'ogg') !== false) $ext = 'ogg';

                $filename = "phrase-{$n}.{$ext}";
                $this->write_file_safely($session_dir . '/' . $filename, wp_remote_retrieve_body($audio_resp));
                $session_phrases[] = [
                    'num' => $n,
                    'text' => $phrase_results[$n - 1]['phrase'] ?? '',
                    'file' => $filename,
                    'format' => $ext,
                ];
            }
        }

        // Write metadata.json so admin audio section can find files
        if ($session_phrases) {
            $this->write_json_atomic($session_dir . '/metadata.json', [
                'session_id' => $session_id,
                'quiz_id' => $default_audio_quiz_id,
                'phrases' => $session_phrases,
                'scored_at' => gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-'
                             . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's',
                'score' => $score,
            ]);

            // Keep source files and add mp4 copies when conversion tooling is available.
            $this->ensure_session_mp4_copies($session_dir);
        }

        // 7. Send score email
        $user = get_userdata($user_id);
        if ($user) {
            $this->send_score_email($user, $score_data);
        }
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: pull_session_from_do() — user {$user_id}, session {$session_id}, score: {$score}%");
        return true;
    }

    /**
     * REST endpoint to store browser-computed quiz data after SSO login.
     * SSO triggers a full page redirect, so JS can't send quiz_data during registration.
     * After reload, JS reads localStorage and posts quiz data to this endpoint.
     */
    public function handle_store_quiz_data($request) {
        $user_id = get_current_user_id();
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC store-quiz-data: called. user_id={$user_id}, session_id=" . ($request->get_param('session_id') ?? 'null'));
        if (!$user_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Not logged in'], 401);
        }

        // Check if this user already has scored quiz data from a PREVIOUS session.
        // If they have old data but also a new session_id, prefer pulling the new session.
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');
        $existing = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        if (is_array($existing) && !empty($existing['phrase_results']) && !$session_id) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC store-quiz-data: user {$user_id} has existing data, no new session_id — returning cached");
            return new WP_REST_Response([
                'success' => true,
                'already_scored' => true,
                'quiz_data' => $existing,
            ]);
        }

        $quiz_data = $request->get_param('quiz_data');
        $temp_id   = sanitize_text_field($request->get_param('temp_id') ?? '');
        if (!$temp_id && is_array($quiz_data) && !empty($quiz_data['tempId'])) {
            $temp_id = sanitize_text_field($quiz_data['tempId']);
        }
        if (!$temp_id) {
            $temp_id = get_user_meta($user_id, '_flosc_audio_temp_id', true) ?: '';
        }

        // Browser-computed quiz data is authoritative; DO pull is fallback.
        if (is_array($quiz_data) && !empty($quiz_data['phraseResults'])) {
            $stored = $this->store_browser_quiz_data($user_id, $quiz_data, $temp_id);
            if ($stored) {
                delete_user_meta($user_id, '_flosc_audio_temp_id');
                return new WP_REST_Response([
                    'success' => true,
                    'quiz_data' => get_user_meta($user_id, '_flosc_last_quiz_data', true),
                ]);
            }
        }

        if ($session_id && $this->pull_session_from_do($user_id, $session_id)) {
            return new WP_REST_Response([
                'success' => true,
                'quiz_data' => get_user_meta($user_id, '_flosc_last_quiz_data', true),
            ]);
        }

        if (!$quiz_data || !is_array($quiz_data)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing quiz_data'], 400);
        }

        $stored = $this->store_browser_quiz_data($user_id, $quiz_data, $temp_id);
        if (!$stored) {
            return new WP_REST_Response(['success' => false, 'message' => 'Failed to store quiz data'], 500);
        }

        // Clean up temp_id reference
        delete_user_meta($user_id, '_flosc_audio_temp_id');

        return new WP_REST_Response([
            'success' => true,
            'quiz_data' => get_user_meta($user_id, '_flosc_last_quiz_data', true),
        ]);
    }

    /**
     * v8.0.0: Score visitor audio server-side after registration.
     *
     * Reads stored audio files from flosc-temp/{tempID}/, sends each to the
     * pronunciation API via wp_remote_post (server-to-server — never exposed to browser),
     * aggregates phoneme scores, maps to lessons, and returns a score_data array
     * compatible with store_quiz_score() / flosc_quiz_completed.
     *
     * After scoring, moves the audio dir to flosc-users/{user_id}/ for retention.
     *
     * NOTE: Primary path is store_browser_quiz_data(); this method is a fallback.
     * This method is retained as a fallback for the /score-pending-audio endpoint.
     *
     * @param int    $user_id  The newly registered user's ID
     * @param string $temp_id  The Michel-timestamp tempID from the signed cookie
     * @return array|false     score_data array on success, false on failure
     */
    public function score_visitor_audio($user_id, $temp_id) {
        // Validate tempID format: YYYY-MMm-DDd-HHh-MMm-SSs-XXXXX
        // (Do not call set_time_limit — Plugin Check / WPCS discourage it.)
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $temp_id)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/flosc-temp/' . $temp_id;
        $meta_path = $temp_dir . '/metadata.json';

        if (!file_exists($meta_path)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: No metadata.json for temp_id {$temp_id}");
            return false;
        }

        $meta = json_decode(flosc_fs_get_contents($meta_path), true);
        if (!$meta || empty($meta['phrases'])) {
            return false;
        }

        // v8.0.5: Use port 443 (nginx proxy) — ChemiCloud shared hosting blocks outbound port 8000.
        $api_base = untrailingslashit(flosc_get_setting('ipa_api_base_url', ''));
        $all_results = [];

        foreach ($meta['phrases'] as $phrase_info) {
            $audio_path = $temp_dir . '/' . $phrase_info['file'];
            if (!file_exists($audio_path)) continue;

            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary/JWT token encoding, not obfuscation
            $audio_b64 = base64_encode(flosc_fs_get_contents($audio_path));
            $words = preg_split('/\s+/', trim($phrase_info['text']));
            $endpoint = count($words) === 1 ? '/analyze' : '/analyze-phrase';

            $body = [
                'audio' => $audio_b64,
                'target_text' => $phrase_info['text'],
                'format' => $phrase_info['format'],
            ];

            if ($endpoint === '/analyze-phrase' && !empty($phrase_info['target_ipa'])) {
                $body['target_ipa'] = $phrase_info['target_ipa'];
            }

            $payload_json = wp_json_encode($body);
            $response = flosc_safe_remote_request('POST', $api_base . $endpoint, [
                'headers' => array_merge(
                    ['Content-Type' => 'application/json'],
                    $this->build_flosc_signed_headers($payload_json)
                ),
                'body' => $payload_json,
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: API error for phrase {$phrase_info['num']}: " . $response->get_error_message());
                continue;
            }

            $resp_body = json_decode(wp_remote_retrieve_body($response), true);
            if ($resp_body) {
                $all_results[] = [
                    'phrase' => $phrase_info['text'],
                    'data' => $resp_body,
                ];
            }
        }

        if (empty($all_results)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: No successful API responses for temp_id {$temp_id}");
            return false;
        }

        // Aggregate phoneme scores — mirrors showIpaQuizSummary() in flosc-app.js
        $all_phonemes = [];
        foreach ($all_results as $r) {
            $words_data = $r['data']['words'] ?? [['phonemes' => $r['data']['phonemes'] ?? []]];
            foreach ($words_data as $w) {
                foreach (($w['phonemes'] ?? []) as $ph) {
                    $all_phonemes[] = $ph;
                }
            }
        }

        $total = count($all_phonemes);
        $avg = $total > 0 ? array_sum(array_column($all_phonemes, 'confidence')) / $total : 0;
        $score = (int) round($avg * 100);

        // Per-phoneme averages
        $phoneme_scores = [];
        foreach ($all_phonemes as $ph) {
            $ipa = $ph['ipa'] ?? '';
            if ($ipa === '') continue;
            $phoneme_scores[$ipa][] = $ph['confidence'];
        }

        $ranked = [];
        foreach ($phoneme_scores as $ipa => $scores) {
            $ranked[] = ['ipa' => $ipa, 'avg' => array_sum($scores) / count($scores)];
        }
        usort($ranked, function($a, $b) { return $a['avg'] <=> $b['avg']; });

        // Map worst 10 phonemes to lesson numbers
        $phoneme_map = json_decode(flosc_get_setting('audio_quiz_phoneme_lesson_map', '{}'), true) ?: [];
        $mapped_worst = array_filter(array_slice($ranked, 0, 10), function($p) use ($phoneme_map) {
            return isset($phoneme_map[$p['ipa']]);
        });

        $incorrect = [];
        foreach ($mapped_worst as $p) {
            $val = $phoneme_map[$p['ipa']];
            $lessons = is_array($val) ? $val : [$val];
            foreach ($lessons as $l) {
                $incorrect[] = (int) $l;
            }
        }
        $incorrect = array_values(array_unique($incorrect));

        // v8.0.0: Build ranked worst lessons array (ordered worst→best) for Free Lesson Manager.
        // Each entry: ['ipa' => phoneme, 'lessons' => [lesson_nums]]
        // The admin setting controls which entry becomes the free lesson.
        $ranked_worst_lessons = [];
        foreach (array_values($mapped_worst) as $p) {
            $val = $phoneme_map[$p['ipa']];
            $lessons = array_map('intval', is_array($val) ? $val : [$val]);
            $ranked_worst_lessons[] = ['ipa' => $p['ipa'], 'score' => round($p['avg'], 3), 'lessons' => $lessons];
        }

        $ranked_for_upsell = array_map(function($p) { return $p['ipa']; }, array_slice($ranked, 0, 10));

        // Move audio files to user profile directory (per-session)
        $user_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id;
        if (!file_exists($user_dir)) {
            wp_mkdir_p($user_dir);
            if ( isset( $this->filesystem ) && is_object( $this->filesystem ) ) {
                $this->filesystem->protect_uploads_dir_with_htaccess( $user_dir );
            }
        }
        $session_dir = $user_dir . '/sessions/' . $temp_id;
        wp_mkdir_p($session_dir);
        // Move entire temp dir contents
        foreach (glob($temp_dir . '/*') as $file) {
            $dest = $session_dir . '/' . basename($file);
            $this->move_file_safely($file, $dest);
        }
        // Store scoring results in the session's metadata.json
        $user_meta_path = $session_dir . '/metadata.json';
        $user_meta = file_exists($user_meta_path) ? json_decode(flosc_fs_get_contents($user_meta_path), true) : $meta;
        $user_meta['scored_at'] = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-'
                                . gmdate('H') . 'h-' . gmdate('i') . 'm-' . gmdate('s') . 's';
        $user_meta['score'] = $score;
        $user_meta['ranked_phonemes'] = $ranked_for_upsell;
        $user_meta['results'] = $all_results;
        if (!$this->write_json_atomic($user_meta_path, $user_meta)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: failed to persist scored metadata for session {$temp_id}");
        }

        // Keep source files and add mp4 copies when conversion tooling is available.
        $this->ensure_session_mp4_copies($session_dir);

        // Clean up empty temp dir
        $this->delete_file_safely($temp_dir . '/.htaccess');
        $this->delete_directory_safely($temp_dir);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: Scored visitor audio for user {$user_id}: {$score}% — " . count($incorrect) . " lesson(s) mapped");
            $now = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd-T' . gmdate('H') . 'h:' . gmdate('i') . 'm:' . gmdate('s') . 's';
                        $modified = gmdate('Y', $mtime) . '-' . gmdate('m', $mtime) . 'm-' . gmdate('d', $mtime) . 'd-T' . gmdate('H', $mtime) . 'h:' . gmdate('i', $mtime) . 'm';
            $registered = gmdate('Y', $reg_ts) . '-' . gmdate('m', $reg_ts) . 'm-' . gmdate('d', $reg_ts) . 'd';
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] activate-subscription HIT at ' . gmdate('Y-m-d H:i:s'));
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] create_order ENDPOINT REACHED at ' . gmdate('Y-m-d H:i:s') . ' user=' . get_current_user_id());
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('[FLOSC-PAYPAL] capture_order ENDPOINT REACHED at ' . gmdate('Y-m-d H:i:s') . ' user=' . get_current_user_id());
        }

        return [
            'quiz_id' => $meta['quiz_id'] ?? flosc_get_setting('default_audio_quiz_id', ''),
            'quiz_type' => 'ipa_audio',
            'score' => $score,
            'correct' => [],
            'incorrect' => $incorrect,
            'ranked_worst_lessons' => $ranked_worst_lessons,
            'timestamp' => time(),
            'session_id' => $temp_id,
            'ranked_phonemes' => $ranked_for_upsell,
            'phrase_results' => $all_results,
        ];
    }

    /**
     * v8.0.0: Cron callback — delete visitor audio temp dirs older than 36 hours.
     * Also delete guest audio dirs for users inactive >30 days with no purchase.
     *
     * Scheduled: twicedaily via flosc_cleanup_visitor_audio hook.
     * TempID format: YYYY-MMm-DDd-HHh-MMm-SSs-XXXXX — parse the timestamp to determine age.
     */
    public function cleanup_expired_visitor_audio() {
        $upload_dir = wp_upload_dir();
        $temp_base = $upload_dir['basedir'] . '/flosc-temp';

        if (!is_dir($temp_base)) return;

        $now = time();
        $max_age = 36 * HOUR_IN_SECONDS;
        $cleaned = 0;

        foreach (glob($temp_base . '/*', GLOB_ONLYDIR) as $dir) {
            $dirname = basename($dir);
            // Parse Michel timestamp: YYYY-MMm-DDd-HHh-MMm-SSs-XXXXX
            if (!preg_match('/^(\d{4})-(\d{2})m-(\d{2})d-(\d{2})h-(\d{2})m-(\d{2})s-[0-9a-f]{5}$/', $dirname, $m)) {
                continue; // Skip any non-matching dirs
            }

            $dir_time = gmmktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
            if (($now - $dir_time) > $max_age) {
                // Delete all files in the dir, then the dir itself
                $files = glob($dir . '/{,.}*', GLOB_BRACE);
                foreach ($files as $f) {
                    if (is_file($f)) {
                        $this->delete_file_safely($f);
                    }
                }
                $this->delete_directory_safely($dir);
                $cleaned++;
            }
        }

        if ($cleaned > 0 && FLOSC_DEBUG) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC v8.0.0: Cleaned up {$cleaned} expired visitor audio dirs");
        }
    }

    /**
     * Mark funnel as completed for user (v3.0.4)
     * Called after user completes the FLOSC flow (quiz → login → free lesson → upgrade prompt)
     */
    public function mark_funnel_complete($request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new WP_Error('not_logged_in', __('User must be logged in', 'flosc'), ['status' => 401]);
        }

        // Mark funnel completed
        update_user_meta($user_id, '_flosc_funnel_completed', true);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Funnel marked as completed',
        ]);
    }

    /**
     * Test AI connection (v04_09)
     * Sends a test message to verify AI provider is configured and responding
     * Returns smart error messages with next steps if connection fails
     */
    public function handle_test_ai($request) {
        $start_time = microtime(true);
        $test_message = "Hello, this is a connection test. Please respond with 'Connection successful'.";

        // v1.9.0: Use flosc_get_setting() — reads flow settings first (where admin UI saves)
        $provider = flosc_get_setting('ai_provider', 'ivr');
        if ($provider === '' || $provider === null) {
            $provider = 'ivr';
        }

        if ($provider === 'ivr') {
            return new WP_REST_Response([
                'success' => false,
                'provider' => 'ivr',
                'error_code' => 'ivr_not_external_api',
                'message' => 'Provider is IVR (scripted only). No external API call was made. Select Anthropic, OpenAI, xAI, or Gemini, Save Settings, then test again.',
            ], 200);
        }

        try {
            // Build AI context for freeline phase (simplest phase)
            $ai_context = ['phase' => 'freeline'];
            $system_prompt = $this->ai_chat_dispatch->build_system_prompt('freeline', $ai_context);

            // Get AI response with test_mode = true (no IVR fallback)
            $response = $this->ai_chat_dispatch->get_response($test_message, $system_prompt, [], true);

            // Check if response is WP_Error (connection failed)
            if (is_wp_error($response)) {
                return new WP_REST_Response([
                    'success' => false,
                    'provider' => $provider,
                    'error_code' => $response->get_error_code(),
                    'message' => $response->get_error_message()
                ], 200);
            }

            // Calculate response time
            $response_time = round((microtime(true) - $start_time) * 1000);

            return new WP_REST_Response([
                'success' => true,
                'provider' => $provider,
                'response_time' => $response_time,
                'test_message' => $test_message,
                'ai_response' => $response
            ]);
        } catch (\Throwable $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * v1.9.0: AJAX handler for AI connection test button in admin
     * Wraps handle_test_ai() for wp_ajax context
     */
    public function ajax_test_ai_connection() {
        $post = wp_unslash($_POST);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_test_ai', 'nonce');

        // v5.0.2 FIX: Set flow context from posted IVR so flosc_get_setting reads
        // the correct per-flow ai_provider. Without this, admin-ajax has no URL
        // context, get_current_flow() returns null, and provider defaults to ''.
        $ivr = sanitize_file_name($post['ivr'] ?? '');
        if ( ! empty( $ivr ) ) {
            $this->set_flow_context( pathinfo( $ivr, PATHINFO_FILENAME ) );
        }

        // v1.8.8 FIX: Run test directly — WP_REST_Request is not always loaded
        // during admin-ajax requests, causing "Class not found" fatal error.
        $start_time = microtime(true);
        $test_message = "Hello, this is a connection test. Please respond with 'Connection successful'.";
        $provider = flosc_get_setting('ai_provider', 'ivr');
        if ($provider === '' || $provider === null) {
            $provider = 'ivr';
        }

        // IVR is scripted local copy — not an external API. Do not report as API success.
        if ($provider === 'ivr') {
            wp_send_json_error([
                'provider' => 'ivr',
                'message'  => "Provider is IVR (scripted only). No external API call was made.\n\n"
                    . "1. Primary AI Provider → Anthropic, OpenAI, xAI Grok, or Gemini\n"
                    . "2. For OpenAI / Anthropic / Gemini, activate that official AI Provider plugin\n"
                    . "3. Paste the API key for that provider in FLOSC\n"
                    . "4. Click Save Settings (bottom of this page)\n"
                    . "5. Confirm the URL has saved=1, then Test again\n\n"
                    . "Expected success line: Provider: xai (or openai / anthropic / gemini), not ivr.",
            ]);
        }

        // Resolved model + key presence (for diagnostics; never return full secrets).
        $model_setting_key = [
            'openai'    => 'ai_openai_model',
            'anthropic' => 'ai_anthropic_model',
            'xai'       => 'ai_xai_model',
            'gemini'    => 'ai_gemini_model',
        ];
        $configured_model = isset($model_setting_key[$provider])
            ? (string) flosc_get_setting($model_setting_key[$provider], '')
            : '';
        $key_raw = function_exists( 'flosc_get_provider_api_key' )
            ? (string) flosc_get_provider_api_key( $provider )
            : (string) flosc_get_setting( $provider . '_api_key', '' );
        $key_present = ($key_raw !== '');
        $key_suffix  = $key_present && strlen($key_raw) >= 4
            ? substr($key_raw, -4)
            : '';

        $endpoint = [
            'openai'    => 'wp_ai_client_prompt() → AI Provider for OpenAI',
            'anthropic' => 'wp_ai_client_prompt() → AI Provider for Anthropic',
            'xai'       => 'FLOSC hop → xAI chat completions',
            'gemini'    => 'wp_ai_client_prompt() → AI Provider for Google',
        ];
        $endpoint_url = $endpoint[$provider] ?? '';

        try {
            $ai_context = ['phase' => 'freeline', 'is_admin' => true];
            $system_prompt = $this->ai_chat_dispatch->build_system_prompt('freeline', $ai_context);
            $response = $this->ai_chat_dispatch->get_response($test_message, $system_prompt, [], true);
            $response_time = round((microtime(true) - $start_time) * 1000);

            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message'           => $response->get_error_message(),
                    'provider'          => $provider,
                    'model'             => $configured_model,
                    'endpoint'          => $endpoint_url,
                    'api_key_present'   => $key_present,
                    'api_key_suffix'    => $key_suffix,
                    'response_time'     => $response_time,
                    'flow_ivr'          => $ivr,
                ]);
            }

            $billing = method_exists($this->ai_chat_dispatch, 'get_last_billing_meta')
                ? (array) $this->ai_chat_dispatch->get_last_billing_meta()
                : [];
            $usage = is_array($billing['usage'] ?? null) ? $billing['usage'] : [];
            $model_used = (string) ($billing['model'] ?? $configured_model);
            if ($model_used === '') {
                $model_used = $configured_model;
            }

            $in_tok  = intval($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
            $out_tok = intval($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);
            $tot_tok = intval($usage['total_tokens'] ?? ($in_tok + $out_tok));

            $flow = $this->get_current_flow();
            $flow_label = '';
            if (is_array($flow)) {
                $flow_label = trim((string) ($flow['identity']['name'] ?? $flow['name'] ?? ''));
            }
            if ($flow_label === '' && $ivr !== '') {
                $flow_label = pathinfo($ivr, PATHINFO_FILENAME);
            }

            wp_send_json_success([
                'provider'        => $provider,
                'model'           => $model_used,
                'model_configured'=> $configured_model,
                'endpoint'        => $endpoint_url,
                'api_key_present' => $key_present,
                'api_key_suffix'  => $key_suffix,
                'response'        => is_string($response) ? $response : wp_json_encode($response),
                'response_time'   => $response_time,
                'tokens_in'       => $in_tok,
                'tokens_out'      => $out_tok,
                'tokens_total'    => $tot_tok,
                'billing_source'  => (string) ($billing['source'] ?? ''),
                'flow_ivr'        => $ivr,
                'flow_label'      => $flow_label,
                'http_ok'         => true,
                'test_message'    => $test_message,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'message'         => $e->getMessage(),
                'provider'        => $provider,
                'model'           => $configured_model,
                'endpoint'        => $endpoint_url,
                'api_key_present' => $key_present,
                'api_key_suffix'  => $key_suffix,
                'flow_ivr'        => $ivr,
            ]);
        }
    }
    public function ajax_flosc_get_chat_logs() {
        $post = wp_unslash($_POST);
        $flow_id = sanitize_key((string) ($post['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow_id)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $logger = FLOSC_Chat_Logger::instance();
        $filters = [
            'since_id' => intval($post['since_id'] ?? 0),
            'flow_id'  => $flow_id,
            'phase'    => sanitize_text_field($post['phase'] ?? ''),
            'user_id'  => intval($post['user_id'] ?? 0),
            'limit'    => intval($post['limit'] ?? 50),
        ];

        $logs = $logger->flosc_get_logs($filters);
        $total = $logger->flosc_get_log_count($filters['flow_id']);

        wp_send_json_success([
            'logs'  => $logs,
            'total' => $total,
        ]);
    }

    /**
     * v1.9.0: AJAX handler to clear old chat logs
     */
    public function ajax_flosc_clear_chat_logs() {
        $post = wp_unslash($_POST);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $days = intval($post['days'] ?? 30);
        $logger = FLOSC_Chat_Logger::instance();
        $deleted = $logger->flosc_clear_old_logs($days);

        wp_send_json_success([
            'deleted' => $deleted,
            'remaining' => $logger->flosc_get_log_count(),
        ]);
    }

    /**
     * v1.9.5: AJAX handler — rate a chat log entry (-10 to +10).
     * Saves score + admin note directly to the chat log row.
     */
    public function ajax_flosc_rate_log() {
        $post = wp_unslash($_POST);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $log_id = intval($post['log_id'] ?? 0);
        $rating = intval($post['rating'] ?? 0);
        $note   = sanitize_textarea_field($post['note'] ?? '');

        if (!$log_id) {
            wp_send_json_error(['message' => 'Missing log_id']);
        }

        $logger = FLOSC_Chat_Logger::instance();
        $result = $logger->flosc_rate_log($log_id, $rating, $note);

        if ($result) {
            wp_send_json_success(['log_id' => $log_id, 'rating' => $rating]);
        } else {
            wp_send_json_error(['message' => 'Failed to save rating']);
        }
    }

    /**
     * v8.0.0: AJAX handler — delete one whole conversation from the chat logs.
     * Identified by (by, value) from FLOSC_Chat_Logger::flosc_session_descriptor,
     * optionally scoped to the flow currently shown.
     */
    public function ajax_flosc_delete_chat_session() {
        $post = wp_unslash($_POST);
        $flow  = sanitize_key((string) ($post['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $by    = sanitize_text_field($post['by'] ?? '');
        $value = sanitize_text_field($post['value'] ?? '');

        if ($by === '' || $value === '') {
            wp_send_json_error(['message' => 'Missing session identifier']);
        }

        $deleted = FLOSC_Chat_Logger::instance()->flosc_delete_session($by, $value, $flow);
        wp_send_json_success(['deleted' => $deleted]);
    }

    /**
     * Bulk session management for chat logs: archive, restore, or delete.
     */
    public function ajax_flosc_manage_chat_sessions() {
        $post = wp_unslash($_POST);
        $flow = sanitize_key((string) ($post['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $operation = sanitize_key((string) ($post['operation'] ?? ''));
        if (!in_array($operation, ['archive', 'restore', 'delete'], true)) {
            wp_send_json_error(['message' => 'Invalid operation']);
        }

        // Pass 8: bound + field-sanitize after json_decode of sessions list.
        $sessions_json = (string) ($post['sessions'] ?? '[]');
        $sessions = [];
        if ($sessions_json !== '' && strlen($sessions_json) <= 100000) {
            $decoded_sessions = json_decode($sessions_json, true, 16);
            if (JSON_ERROR_NONE === json_last_error() && is_array($decoded_sessions)) {
                $sessions = $decoded_sessions;
            }
        }
        if (empty($sessions)) {
            wp_send_json_error(['message' => 'No sessions selected.']);
        }

        $logger = FLOSC_Chat_Logger::instance();
        $processed = 0;
        $affected_rows = 0;

        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }

            $by = sanitize_key((string) ($session['by'] ?? ''));
            $value = sanitize_text_field((string) ($session['value'] ?? ''));
            if ($by === '' || $value === '') {
                continue;
            }

            if ($operation === 'delete') {
                $affected_rows += max(0, intval($logger->flosc_delete_session($by, $value, $flow)));
                $processed++;
                continue;
            }

            $result = $logger->flosc_set_session_archived($by, $value, $flow, $operation === 'archive');
            if ($result !== false) {
                $processed++;
            }
        }

        wp_send_json_success([
            'processed' => $processed,
            'affected_rows' => $affected_rows,
            'operation' => $operation,
        ]);
    }

    /**
     * Download one or more chat conversations as a TSV file.
     */
    public function handle_download_chat_tsv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'flosc'), 403);
        }

        $request = wp_unslash($_REQUEST);
        check_admin_referer('flosc_download_chat_tsv');

        $flow = sanitize_key((string) ($request['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow)) {
            wp_die(esc_html__('Unauthorized', 'flosc'), 403);
        }

        $sessions = [];
        if (!empty($request['sessions'])) {
            // Pass 8: bound + field-sanitize after json_decode.
            $sessions_raw = (string) $request['sessions'];
            if ($sessions_raw !== '' && strlen($sessions_raw) <= 100000) {
                $decoded = json_decode($sessions_raw, true, 16);
                if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
                    $sessions = $decoded;
                }
            }
        } elseif (!empty($request['by']) && isset($request['value'])) {
            $sessions[] = [
                'by' => sanitize_key((string) $request['by']),
                'value' => sanitize_text_field((string) $request['value']),
            ];
        }

        if (empty($sessions)) {
            wp_die(esc_html__('No chat sessions selected.', 'flosc'));
        }

        $logger = FLOSC_Chat_Logger::instance();
        $rows = [];
        foreach ($sessions as $session) {
            if (!is_array($session)) {
                continue;
            }

            $by = sanitize_key((string) ($session['by'] ?? ''));
            $value = sanitize_text_field((string) ($session['value'] ?? ''));
            if ($by === '' || $value === '') {
                continue;
            }

            $session_rows = $logger->flosc_get_session_rows($by, $value, $flow);
            if (empty($session_rows)) {
                continue;
            }

            $descriptor = FLOSC_Chat_Logger::flosc_session_key_from_descriptor($by, $value);
            foreach ($session_rows as $session_row) {
                $row_descriptor = FLOSC_Chat_Logger::flosc_session_descriptor($session_row);
                $session_row['chat_session_key'] = $descriptor;
                $session_row['chat_session_label'] = (string) ($row_descriptor['label'] ?? '');
                $rows[] = $session_row;
            }
        }

        if (empty($rows)) {
            wp_die(esc_html__('No chat rows found for the selected sessions.', 'flosc'));
        }

        $headers = array_keys($rows[0]);
        $to_tsv_line = static function (array $columns) {
            $encoded = array_map(static function ($value) {
                $value = (string) $value;
                $value = str_replace(["\r\n", "\r", "\n"], "\\n", $value);
                if (strpos($value, "\t") !== false || strpos($value, '"') !== false) {
                    $value = '"' . str_replace('"', '""', $value) . '"';
                }
                return $value;
            }, $columns);

            return implode("\t", $encoded) . "\n";
        };

        $tsv_body = $to_tsv_line($headers);
        foreach ($rows as $row) {
            $ordered_row = [];
            foreach ($headers as $header_key) {
                $value = $row[$header_key] ?? '';
                $ordered_row[] = is_scalar($value) ? (string) $value : wp_json_encode($value);
            }
            $tsv_body .= $to_tsv_line($ordered_row);
        }

        $datestamp = gmdate('Y') . '-' . gmdate('m') . 'm-' . gmdate('d') . 'd';
        $this->filesystem->stream_plain_download_and_exit(
            $tsv_body,
            'text/tab-separated-values; charset=utf-8',
            'flosc-chat-logs-' . $datestamp . '.tsv'
        );
    }

    /**
     * v8.0.0: Admin joins a conversation — post a human "(admin)" message into a
     * visitor's chat. Stored at the bottom of that session; the visitor's widget
     * picks it up on its next poll and shows it pale-green as "Name (admin)".
     */
    public function ajax_flosc_admin_join() {
        $post = wp_unslash($_POST);
        $flow = sanitize_key((string) ($post['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        check_ajax_referer('flosc_chat_logs', 'nonce');

        $session_id = intval($post['session_id'] ?? 0);
        $text       = sanitize_textarea_field($post['text'] ?? '');
        $as         = (sanitize_text_field($post['as'] ?? 'admin') === 'bot') ? 'bot' : 'admin';
        if ($session_id <= 0 || $text === '') {
            wp_send_json_error(['message' => 'A session and a message are required.']);
        }

        // "as admin" → the admin's own name, shown "(admin)"; "as bot" → the flow's
        // AI name (e.g. assistant), rendered as a normal assistant message.
        if ($flow !== '') {
            $this->set_flow_context($flow);
        }
        if ($as === 'bot') {
            $name = flosc_get_setting('ai_personality_name', flosc_get_setting('ai_identity_name', __('Site Assistant', 'flosc')));
        } else {
            $name = wp_get_current_user()->display_name;
            if ($name === '') {
                $name = 'Admin';
            }
        }

        $id = FLOSC_Chat_Logger::instance()->flosc_insert_admin_message($session_id, $flow, $name, $text, $as);
        if ($id) {
            wp_send_json_success(['id' => $id, 'name' => $name, 'text' => $text, 'as' => $as]);
        }
        wp_send_json_error(['message' => 'Could not post the message.']);
    }

    /**
     * Admin token assignment from Chat Logs session view.
     */
    public function ajax_flosc_admin_assign_tokens() {
        $post = wp_unslash($_POST);
        $flow = sanitize_key((string) ($post['flow_id'] ?? ''));
        if (!$this->can_manage_flow_chat_logs($flow)) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        check_ajax_referer('flosc_chat_logs', 'nonce');

        $session_id = intval($post['session_id'] ?? 0);
        $amount = intval($post['amount'] ?? 0);
        if ($session_id <= 0 || $amount <= 0) {
            wp_send_json_error(['message' => 'Session id and positive token amount are required.']);
        }

        $token_provider = $this->sale_manager->get_provider('tokens');
        if (!$token_provider) {
            wp_send_json_error(['message' => 'Token provider unavailable.']);
        }

        $logger = FLOSC_Chat_Logger::instance();
        $owner_user_id = $logger->flosc_get_session_owner_user_id($session_id, $flow);

        if ($owner_user_id > 0) {
            $result = $token_provider->credit($owner_user_id, $amount, 'Admin chat token assignment', [
                'flow_id' => $flow,
                'session_id' => $session_id,
                'admin_user_id' => get_current_user_id(),
            ]);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'scope' => 'user',
                'user_id' => $owner_user_id,
                'assigned' => $amount,
                'balance' => intval($result),
                'formatted' => $this->flosc_format_token_display(intval($result)),
            ]);
        }

        $balance = $this->flosc_get_visitor_session_token_balance($flow, $session_id, $token_provider);
        $updated = $this->flosc_set_visitor_session_token_balance($flow, $session_id, $balance + $amount);

        wp_send_json_success([
            'scope' => 'visitor_session',
            'session_id' => $session_id,
            'assigned' => $amount,
            'balance' => intval($updated),
            'formatted' => $this->flosc_format_token_display(intval($updated)),
        ]);
    }

    /**
     * v8.0.0: Visitor poll — return admin "(admin)" messages posted into this
     * conversation since the given cursor. Public, read-only, lightweight.
     */
    public function handle_admin_messages_token($request) {
        $session_id_raw = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
        $session_id = $this->flosc_normalize_session_id($session_id_raw);
        if ($session_id <= 0) {
            return new WP_Error('invalid_session_id', __('Invalid session id.', 'flosc'), ['status' => 400]);
        }

        if (!$this->current_request_owns_chat_session($session_id)) {
            return new WP_Error('flosc_session_ownership_failed', __('Session ownership check failed.', 'flosc'), ['status' => 403]);
        }

        $token = $this->issue_admin_poll_token($session_id);

        return new WP_REST_Response([
            'success' => true,
            'poll_token' => $token,
        ]);
    }

    /**
     * v8.0.0: Visitor poll — return admin "(admin)" messages posted into this
     * conversation since the given cursor. Public, read-only, lightweight.
     */
    public function handle_admin_messages_poll($request) {
        nocache_headers(); // belt-and-suspenders against any caching layer
        $session_id_raw = sanitize_text_field((string) ($request->get_param('session_id') ?? ''));
        $session_id = $this->flosc_normalize_session_id($session_id_raw);
        $since_id   = intval($request->get_param('since_id'));
        if ($session_id <= 0) {
            return new WP_REST_Response(['messages' => []]);
        }

        $flow_id = sanitize_key((string) ($request->get_param('flow_id') ?? ''));
        $messages = FLOSC_Chat_Logger::instance()->flosc_get_admin_messages_since($session_id, $since_id);

        $token_balance_payload = null;
        if ($flow_id !== '') {
            $token_provider = $this->sale_manager->get_provider('tokens');
            if ($token_provider) {
                $value = intval($this->flosc_get_visitor_session_token_balance($flow_id, $session_id, $token_provider));
                $flow_settings = get_option('flosc_flow_' . $flow_id, []);
                $low_token_threshold = max(0, intval($flow_settings['visitor_low_token_threshold'] ?? 0));
                $is_low_balance = ($low_token_threshold > 0 && $value <= $low_token_threshold);
                $token_balance_payload = [
                    'scope' => 'visitor_session',
                    'value' => $value,
                    'formatted' => $this->flosc_format_token_display($value),
                    'low_threshold' => $low_token_threshold,
                    'is_low' => $is_low_balance,
                    'low_message' => $is_low_balance ? $this->flosc_get_visitor_low_tokens_message($flow_id) : '',
                ];
            }
        }

        return new WP_REST_Response([
            'messages' => $messages,
            'token_balance' => $token_balance_payload,
        ]);
    }



    /**
     * v1.9.0: REST handler — save an AI feedback (admin flags a bad response)
     * Stores feedback in flow settings under 'ai_feedback' key.
     */
    public function handle_save_feedback($request) {
        $user_message    = sanitize_textarea_field($request->get_param('user_message') ?? '');
        $bad_response    = sanitize_textarea_field($request->get_param('bad_response') ?? '');
        $admin_note      = sanitize_textarea_field($request->get_param('admin_note') ?? '');
        $preferred       = sanitize_textarea_field($request->get_param('preferred_response') ?? '');
        $flow_id         = sanitize_text_field($request->get_param('flow_id') ?? '');

        if (empty($user_message) || empty($bad_response) || empty($admin_note)) {
            return new WP_Error('missing_fields', 'user_message, bad_response, and admin_note are required', ['status' => 400]);
        }

        // Resolve flow settings key
        if (empty($flow_id)) {
            $flow = $this->get_current_flow();
            $flow_id = $flow['ivr_file'] ?? '';
        }

        if (empty($flow_id)) {
            return new WP_Error('no_flow', 'Could not determine flow', ['status' => 400]);
        }

        $settings_key = 'flosc_flow_' . sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        $flow_settings = get_option($settings_key, []);
        $feedback_items = $flow_settings['ai_feedback'] ?? [];

        // Build feedback entry
        $feedback_item = [
            'id'                 => uniqid('corr_'),
            'timestamp'          => current_time('mysql'),
            'user_message'       => $user_message,
            'bad_response'       => $bad_response,
            'admin_note'         => $admin_note,
            'preferred_response' => $preferred,
            'admin_user_id'      => get_current_user_id(),
        ];

        $feedback_items[] = $feedback_item;
        $flow_settings['ai_feedback'] = $feedback_items;
        update_option($settings_key, $flow_settings);

        return new WP_REST_Response([
            'success'    => true,
            'feedback' => $feedback_item,
            'total'      => count($feedback_items),
        ]);
    }

    /**
     * v1.9.0: REST handler — list all AI feedback for current flow
     */
    public function handle_get_feedback($request) {
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');

        if (empty($flow_id)) {
            $flow = $this->get_current_flow();
            $flow_id = $flow['ivr_file'] ?? '';
        }

        if (empty($flow_id)) {
            return new WP_REST_Response(['success' => true, 'feedback' => []]);
        }

        $settings_key = 'flosc_flow_' . sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        $flow_settings = get_option($settings_key, []);
        $feedback_items = $flow_settings['ai_feedback'] ?? [];

        return new WP_REST_Response([
            'success'     => true,
            'feedback' => $feedback_items,
            'total'       => count($feedback_items),
        ]);
    }

    /**
     * v1.9.0: REST handler — delete one AI feedback by ID
     */
    public function handle_delete_feedback($request) {
        $feedback_id = sanitize_text_field($request->get_param('feedback_id'));
        $flow_id       = sanitize_text_field($request->get_param('flow_id') ?? '');

        if (empty($flow_id)) {
            $flow = $this->get_current_flow();
            $flow_id = $flow['ivr_file'] ?? '';
        }

        if (empty($flow_id)) {
            return new WP_Error('no_flow', 'Could not determine flow', ['status' => 400]);
        }

        $settings_key = 'flosc_flow_' . sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        $flow_settings = get_option($settings_key, []);
        $feedback_items = $flow_settings['ai_feedback'] ?? [];

        $original_count = count($feedback_items);
        $feedback_items = array_values(array_filter($feedback_items, function($c) use ($feedback_id) {
            return ($c['id'] ?? '') !== $feedback_id;
        }));

        if (count($feedback_items) === $original_count) {
            return new WP_Error('not_found', 'Feedback not found', ['status' => 404]);
        }

        $flow_settings['ai_feedback'] = $feedback_items;
        update_option($settings_key, $flow_settings);

        return new WP_REST_Response([
            'success'   => true,
            'deleted'   => $feedback_id,
            'remaining' => count($feedback_items),
        ]);
    }

    /**
     * v1.9.0: REST handler — save an AI praise (admin reinforces good response)
     */
    public function handle_save_praise($request) {
        $user_message = sanitize_textarea_field($request->get_param('user_message') ?? '');
        $good_response = sanitize_textarea_field($request->get_param('good_response') ?? '');
        $admin_note    = sanitize_textarea_field($request->get_param('admin_note') ?? '');
        $flow_id       = sanitize_text_field($request->get_param('flow_id') ?? '');

        if (empty($user_message) || empty($good_response) || empty($admin_note)) {
            return new WP_Error('missing_fields', 'user_message, good_response, and admin_note are required', ['status' => 400]);
        }

        if (empty($flow_id)) {
            $flow = $this->get_current_flow();
            $flow_id = $flow['ivr_file'] ?? '';
        }

        if (empty($flow_id)) {
            return new WP_Error('no_flow', 'Could not determine flow', ['status' => 400]);
        }

        $settings_key = 'flosc_flow_' . sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        $flow_settings = get_option($settings_key, []);
        $praises = $flow_settings['ai_praises'] ?? [];

        $praise = [
            'id'            => uniqid('praise_'),
            'timestamp'     => current_time('mysql'),
            'user_message'  => $user_message,
            'good_response' => $good_response,
            'admin_note'    => $admin_note,
            'admin_user_id' => get_current_user_id(),
        ];

        $praises[] = $praise;
        $flow_settings['ai_praises'] = $praises;
        update_option($settings_key, $flow_settings);

        return new WP_REST_Response([
            'success' => true,
            'praise'  => $praise,
            'total'   => count($praises),
        ]);
    }

    /**
     * v1.9.0: REST handler — delete one AI praise by ID
     */
    public function handle_delete_praise($request) {
        $praise_id = sanitize_text_field($request->get_param('praise_id'));
        $flow_id   = sanitize_text_field($request->get_param('flow_id') ?? '');

        if (empty($flow_id)) {
            $flow = $this->get_current_flow();
            $flow_id = $flow['ivr_file'] ?? '';
        }

        if (empty($flow_id)) {
            return new WP_Error('no_flow', 'Could not determine flow', ['status' => 400]);
        }

        $settings_key = 'flosc_flow_' . sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
        $flow_settings = get_option($settings_key, []);
        $praises = $flow_settings['ai_praises'] ?? [];

        $original_count = count($praises);
        $praises = array_values(array_filter($praises, function($p) use ($praise_id) {
            return ($p['id'] ?? '') !== $praise_id;
        }));

        if (count($praises) === $original_count) {
            return new WP_Error('not_found', 'Praise not found', ['status' => 404]);
        }

        $flow_settings['ai_praises'] = $praises;
        update_option($settings_key, $flow_settings);

        return new WP_REST_Response([
            'success'   => true,
            'deleted'   => $praise_id,
            'remaining' => count($praises),
        ]);
    }

    /**
     * Handle IVR message tracking (v07.09)
     * Track which messages have been shown to users
     */
    public function handle_ivr_track($request) {
        $message_name = sanitize_text_field($request->get_param('message_name'));
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $offer_state = sanitize_text_field($request->get_param('offer_state')); // shown, dismissed, purchased

        if (empty($message_name) && empty($offer_id)) {
            return new WP_Error('missing_params', 'message_name or offer_id required', ['status' => 400]);
        }

        if (!is_user_logged_in()) {
            // For visitors, track in transient by IP
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $key = 'flosc_visitor_' . md5($ip);
            $data = get_transient($key) ?: [];

            if ($message_name) {
                $data['messages'][$message_name] = time();
            }
            if ($offer_id && $offer_state) {
                $data['offers'][$offer_id][$offer_state] = time();
            }

            set_transient($key, $data, DAY_IN_SECONDS);

            return new WP_REST_Response(['success' => true, 'tracked' => 'visitor']);
        }

        // For logged-in users, track in user meta
        $user_id = get_current_user_id();

        if ($message_name) {
            $key = '_flosc_msg_shown_' . sanitize_key($message_name);
            update_user_meta($user_id, $key, current_time('mysql'));
        }

        if ($offer_id && $offer_state) {
            $key = "_flosc_offer_{$offer_state}_{$offer_id}";
            update_user_meta($user_id, $key, current_time('mysql'));
        }

        return new WP_REST_Response(['success' => true, 'tracked' => 'user']);
    }

    /**
     * Get applicable IVR messages for current user/context (v07.09)
     */
    public function handle_ivr_get_messages($request) {
        $phase = $this->normalize_ivr_phase($request->get_param('phase'));
        if (is_wp_error($phase)) {
            return $phase;
        }
        $type = sanitize_text_field($request->get_param('type')); // auto, suggested_user_autoprompt, offer

        // Build context
        require_once FLOSC_PLUGIN_DIR . 'includes/class-condition-evaluator.php';
        $context = FLOSC_Condition_Evaluator::build_context(
            is_user_logged_in() ? get_current_user_id() : null,
            [
                'message_count' => intval($request->get_param('message_count') ?? 0),
                'inactive_seconds' => intval($request->get_param('inactive_seconds') ?? 0),
                'session_seconds' => intval($request->get_param('session_seconds') ?? 0),
            ]
        );

        // P0-E: public content phase forbidden (same matrix as get_ivr_messages).
        $is_member = !empty($context['is_member'])
            || (isset($context['access_level']) && in_array((string) $context['access_level'], ['member', 'full'], true));
        if ($phase === 'content' && !$is_member && !current_user_can('manage_options')) {
            return new WP_Error(
                'flosc_content_phase_forbidden',
                __('Content phase requires membership.', 'flosc'),
                ['status' => 403]
            );
        }

        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        $ivr_file = sanitize_file_name($request->get_param('ivr_file') ?? '');
        $config = flosc_resolve_flow_runtime($flow_id, $ivr_file);
        $messages = flosc_flow_phase_messages($config, $phase);

        // Filter by type if specified
        if ($type) {
            $messages = array_filter($messages, function($m) use ($type) {
                return $m['type'] === $type;
            });
        }

        // Same non-leak rule as get_ivr_messages: concierge never reaches the browser.
        $messages = array_values(array_filter($messages, static function ($m) {
            return (($m['type'] ?? '') !== 'concierge');
        }));

        // Evaluate conditions. The Condition Evaluator is the per-message
        // authority: it resolves each message's MessageConditions expression
        // (is_visitor / is_guest / is_member, session state, quiz state)
        // against the requesting user's real context.
        $evaluator = new FLOSC_Condition_Evaluator($context);
        $applicable = $evaluator->get_applicable_messages($messages, $type);

        // Best-in-class (WPORG E3/E7): never return full evaluation context to the browser.
        // Only public-safe flags; quiz scores / user_id / bridge internals stay server-side.
        return new WP_REST_Response([
            'success'  => true,
            'messages' => array_values($applicable),
            'user_context' => [
                'access_level'  => sanitize_key((string) ($context['access_level'] ?? 'visitor')),
                'is_logged_in'  => !empty($context['logged_in']),
            ],
        ]);
    }


    /**
     * Render a profile reminder for email-registered users who have not yet set a nickname/password.
     * Shown on /wp-admin/profile.php only for the user viewing their own profile.
     */
    public function render_credential_setup_reminder($user) {
        if ($user->ID !== get_current_user_id()) return;
        if (get_user_meta($user->ID, '_flosc_registration_method', true) !== 'email') return;
        if (get_user_meta($user->ID, '_flosc_magic_link_user_credentials_set', true)) return;
        $this->enqueue_flosc_quiz_ui_styles();

        $chat_url = $this->get_app_url();
        echo '<div class="flosc-guest-warning-card flosc-guest-warning-card--wide">';
        echo '<p class="flosc-guest-warning-title">Complete your account</p>';
        echo '<p class="flosc-guest-warning-copy">You have not yet set a nickname or password. Visit the chat to complete your profile — the setup card will appear automatically.</p>';
        echo '<a href="' . esc_url($chat_url) . '" class="flosc-guest-warning-cta">Go to chat</a>';
        echo '</div>';
    }

    /**
     * v8.0.5: Render audio files section on WP admin user profile page.
     * Reads flosc-users/{user_id}/metadata.json and lists playable audio for each phrase.
     * Audio served via AJAX endpoint (files are .htaccess-protected).
     */
    public function render_admin_user_audio_section($user) {
        // Admins can view any user's audio. Users can view their own.
        $viewing_own = (get_current_user_id() === $user->ID);
        if (!current_user_can('manage_options') && !$viewing_own) {
            return;
        }

        $user_id = $user->ID;
        $this->enqueue_flosc_quiz_ui_styles();
        $upload_dir = wp_upload_dir();
        $user_audio_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id;

        // v8.0.0: Per-session storage — find audio dir via session_id in user meta
        $quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        $sess_id = is_array($quiz_data) ? ($quiz_data['session_id'] ?? '') : '';
        $meta_path = '';
        if ($sess_id && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $sess_id)) {
            $session_dir = $user_audio_dir . '/sessions/' . $sess_id;
            if (is_dir($session_dir) && file_exists($session_dir . '/metadata.json')) {
                $meta_path = $session_dir . '/metadata.json';
                $user_audio_dir = $session_dir;
            }
        }
        // Fallback: flat path (pre-session layout)
        if (!$meta_path && file_exists($user_audio_dir . '/metadata.json')) {
            $meta_path = $user_audio_dir . '/metadata.json';
        }

        // Also check flosc-temp for unscored audio (linked via user meta)
        $temp_dir = null;
        $temp_meta_path = null;
        $has_user_dir = $meta_path && file_exists($meta_path);

        if (!$has_user_dir) {
            if (empty($quiz_data)) {
                // No scored audio and no quiz data — nothing to show
                echo '<h2>FLOSC Audio Quiz</h2>';
                echo '<p>No audio quiz data for this user.</p>';
                return;
            }
        }

        echo '<h2>FLOSC Audio Quiz</h2>';
        echo '<table class="form-table" role="presentation">';

        // Show guest link send count if present
        $links_sent = (int) get_user_meta($user_id, '_flosc_links_sent', true);
        if ($links_sent > 0) {
            $log = get_option('flosc_guest_link_log', []);
            $log_entry = $log[md5(strtolower($user->user_email))] ?? null;
            $first_sent = $log_entry ? wp_date('Y-m-d', $log_entry['first_sent']) : '—';
            $last_sent  = $log_entry ? wp_date('Y-m-d', $log_entry['last_sent'])  : '—';
            $color = ($links_sent >= 6) ? '#d63638' : '#1a7f37';
            echo '<tr><th>Guest Links Sent</th><td><strong class="flosc-links-sent ' . (($links_sent >= 6) ? 'flosc-links-sent--warn' : 'flosc-links-sent--ok') . '">' . esc_html($links_sent) . '</strong>';
            echo ' <span class="flosc-links-sent-meta">(first: ' . esc_html($first_sent) . ' / last: ' . esc_html($last_sent) . ')</span></td></tr>';
        }

        // Show score summary from user meta (already loaded above)
        if ($quiz_data) {
            $score = $quiz_data['score'] ?? '—';
            $quiz_type = $quiz_data['quiz_type'] ?? $quiz_data['quiz_id'] ?? '—';
            $timestamp = $quiz_data['timestamp'] ?? '';
            $scored_date = $timestamp ? wp_date('Y-m-d H:i:s', $timestamp) : '—';
            $ranked = $quiz_data['ranked_phonemes'] ?? [];

            echo '<tr><th>Score</th><td><strong>' . esc_html($score) . '%</strong></td></tr>';
            echo '<tr><th>Quiz Type</th><td>' . esc_html($quiz_type) . '</td></tr>';
            echo '<tr><th>Scored</th><td>' . esc_html($scored_date) . '</td></tr>';
            if ($ranked) {
                echo '<tr><th>Weakest Phonemes</th><td>' . esc_html(implode(', ', $ranked)) . '</td></tr>';
            }
        }

        // Show audio files if user dir exists
        if ($has_user_dir) {
            $meta = json_decode(flosc_fs_get_contents($meta_path), true);
            $phrases = $meta['phrases'] ?? [];
            $phrases = $meta['phrases'] ?? [];

            if ($phrases) {
                echo '<tr><th>Audio Recordings</th><td>';
                echo '<div class="flosc-audio-list">';
                foreach ($phrases as $phrase) {
                    $num = $phrase['num'] ?? '?';
                    $text = $phrase['text'] ?? '';
                    $file = $phrase['file'] ?? '';
                    $format = $phrase['format'] ?? 'webm';
                    if (!$file) continue;

                    $audio_url = admin_url('admin-ajax.php') . '?' . http_build_query([
                        'action' => 'flosc_serve_user_audio',
                        'user_id' => $user_id,
                        'session_id' => $sess_id,
                        'file' => $file,
                    ]);

                    $mime = 'audio/webm';
                    if ($format === 'mp4') $mime = 'audio/mp4';
                    if ($format === 'ogg') $mime = 'audio/ogg';

                    echo '<div class="flosc-audio-item">';
                    echo '<div class="flosc-audio-item-title"><strong>Phrase ' . esc_html($num) . ':</strong> ' . esc_html($text) . '</div>';
                    echo '<audio class="flosc-audio-player" controls preload="none">';
                    echo '<source src="' . esc_url($audio_url) . '" type="' . esc_attr($mime) . '">';
                    echo 'Your browser does not support audio playback.';
                    echo '</audio>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</td></tr>';
            }

            // Show scored_at from file metadata
            if (!empty($meta['scored_at'])) {
                echo '<tr><th>Audio Scored At</th><td>' . esc_html($meta['scored_at']) . '</td></tr>';
            }
        }

        echo '</table>';
    }

    /**
     * v8.0.5: AJAX endpoint to serve user audio files to admin.
     * Required because flosc-users/ dirs have .htaccess Deny from all.
     */
    public function ajax_serve_user_audio() {
        // Signed URL endpoint (exp + HMAC). Read query via filter_input — not a
        // state-changing POST; auth is signature and/or capability below.
        $user_id     = absint( (string) filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT ) );
        $file_raw    = filter_input( INPUT_GET, 'file', FILTER_UNSAFE_RAW );
        $file        = is_string( $file_raw ) ? sanitize_file_name( wp_unslash( $file_raw ) ) : '';
        $is_download = (bool) filter_input( INPUT_GET, 'download', FILTER_UNSAFE_RAW );
        $expires     = absint( (string) filter_input( INPUT_GET, 'exp', FILTER_SANITIZE_NUMBER_INT ) );
        $sig_raw     = filter_input( INPUT_GET, 'sig', FILTER_UNSAFE_RAW );
        $sig         = is_string( $sig_raw ) ? strtolower( preg_replace( '/[^a-f0-9]/', '', wp_unslash( $sig_raw ) ) ) : '';
        $session_raw = filter_input( INPUT_GET, 'session_id', FILTER_UNSAFE_RAW );
        $session_id  = is_string( $session_raw ) ? sanitize_text_field( wp_unslash( $session_raw ) ) : '';

        if (!$user_id || !$file) {
            wp_die('Missing parameters', 400);
        }

        $has_valid_sig = $this->is_valid_audio_access_signature($user_id, $session_id, $file, $expires, $sig);

        // Allow owner/admin access, or a valid short-lived signed URL.
        if (!$has_valid_sig && !current_user_can('manage_options') && get_current_user_id() !== $user_id) {
            wp_die('Unauthorized', 403);
        }

        // Validate filename: only allow phrase-N.ext pattern
        if (!preg_match('/^phrase-\d+\.(webm|mp4|m4a|ogg|wav)$/', $file)) {
            wp_die('Invalid file', 400);
        }

        $upload_dir = wp_upload_dir();
        if ($session_id && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $session_id)) {
            $filepath = $upload_dir['basedir'] . '/flosc-users/' . $user_id . '/sessions/' . $session_id . '/' . $file;
        } else {
            $filepath = $upload_dir['basedir'] . '/flosc-users/' . $user_id . '/' . $file;
        }

        if (!file_exists($filepath)) {
            wp_die('File not found', 404);
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimes = ['webm' => 'audio/webm', 'mp4' => 'audio/mp4', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'];
        $mime = $mimes[$ext] ?? 'application/octet-stream';

        $download_name = $file;
        if ($is_download && $session_id) {
            $mts_stamp = preg_replace('/-[0-9a-f]{5}$/', '', $session_id);
            $phrase_num = preg_match('/^phrase-(\d+)\./', $file, $pm)
                ? str_pad((string) intval($pm[1]), 2, '0', STR_PAD_LEFT)
                : '01';
            $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
            $ext = in_array($ext, ['mp4', 'm4a', 'webm', 'ogg', 'wav'], true) ? $ext : 'bin';
            $download_name = 'phrase_' . $phrase_num . '_' . $mts_stamp . '.' . $ext;
        }

        // Byte range for iOS Safari/WebKit <audio> probe (Range: bytes=0-1).
        $http_range = filter_input( INPUT_SERVER, 'HTTP_RANGE', FILTER_UNSAFE_RAW );
        $http_range = is_string( $http_range ) && $http_range !== ''
            ? sanitize_text_field( wp_unslash( $http_range ) )
            : null;

        $this->filesystem->stream_uploads_binary_range_and_exit(
            $filepath,
            $mime,
            $download_name,
            $is_download,
            $http_range
        );
    }

    /**
     * v8.0.0: Register "Quiz Results" tab on BuddyBoss/BuddyPress member profiles.
     * Only appears when the viewed user has quiz data in _flosc_last_quiz_data.
     * Hooked to bp_setup_nav — runs only if BuddyPress is active.
     */
    public function setup_buddyboss_quiz_tab() {
        if (!function_exists('bp_core_new_nav_item') || !function_exists('bp_displayed_user_id')) {
            return;
        }

        $displayed_user_id = bp_displayed_user_id();
        if (!$displayed_user_id) return;

        bp_core_new_nav_item([
            'name'                => __('Quiz Results', 'flosc'),
            'slug'                => 'flosc_quiz_tab',
            'position'            => 80,
            'screen_function'     => [$this, 'buddyboss_quiz_tab_screen'],
            'default_subnav_slug' => 'flosc_quiz_tab',
            'show_for_displayed_user' => true,
        ]);
    }

    /**
     * v8.0.0: Screen function for the BuddyBoss Quiz Results tab.
     * Sets the page title and hooks the content render into bp_template_content.
     */
    public function buddyboss_quiz_tab_screen() {
        add_action('bp_template_title', function() {
            echo esc_html__('Quiz Results', 'flosc');
        });
        add_action('bp_template_content', [$this, 'render_buddyboss_quiz_tab']);
        // BuddyPress members plugin template (core BP path; no third-party filter needed).
        bp_core_load_template( 'members/single/plugins' );
    }

    /**
     * v8.0.0: Render the Quiz Results tab content on BuddyBoss member profiles.
     * Shows score circle, phoneme breakdown, and phrase-level results.
     * All data comes from WordPress user meta (_flosc_last_quiz_data).
     * No audio playback here — that is a premium/admin feature.
     */
    public function render_buddyboss_quiz_tab() {
        $user_id = bp_displayed_user_id();
        $quiz_data = get_user_meta($user_id, '_flosc_last_quiz_data', true);
        $quiz_attempts = get_user_meta($user_id, '_flosc_quiz_attempts', true);

        if (empty($quiz_data) || !is_array($quiz_data)) {
            echo '<div class="flosc-quiz-results-empty"><p>No quiz results available.</p></div>';
            return;
        }

        if (!is_array($quiz_attempts)) {
            $quiz_attempts = [];
        }

        $score = intval($quiz_data['score'] ?? 0);
        $ranked_phonemes = $quiz_data['ranked_phonemes'] ?? [];
        $phrase_results = $quiz_data['phrase_results'] ?? [];
        $timestamp = $quiz_data['timestamp'] ?? 0;
        $date_str = $timestamp ? wp_date('F j, Y', $timestamp) : '';

        // Score tier: good ≥80, warn ≥60, bad <60
        $score_class = $score >= 80 ? 'flosc-score--good' : ($score >= 60 ? 'flosc-score--warn' : 'flosc-score--bad');
        $this->enqueue_flosc_quiz_ui_styles();

        echo '<div class="flosc-quiz-results-tab">';

        // Legacy-only fallback: render top-level score + areas when there are no per-session attempts.
        // With attempts, render_session_result_card() inside each session accordion already renders these.
        if (empty($quiz_attempts)) {
            // Score circle
            echo '<div class="flosc-score-wrap">';
            echo '<div class="flosc-score-ring ' . esc_attr($score_class) . '">';
            echo esc_html($score) . '%';
            echo '</div>';
            if ($date_str) {
                echo '<div class="flosc-score-date">Taken ' . esc_html($date_str) . '</div>';
            }
            echo '</div>';

            // Weakest phonemes
            if ($ranked_phonemes) {
                $top_weak = array_slice($ranked_phonemes, 0, 10);
                echo '<div class="flosc-weakness-wrap">';
                echo '<h3 class="flosc-quiz-section-title">Areas for Improvement</h3>';
                echo '<div class="flosc-weakness-tags">';
                foreach ($top_weak as $ipa) {
                    echo '<span class="flosc-weakness-tag">' . esc_html($ipa) . '</span>';
                }
                echo '</div>';
                echo '</div>';
            }
        }

        // Determine profile completion + guest status for the profile owner.
        // Guest role + access window come from flow settings — never hardcode product roles or 30 days.
        $bb_user          = get_userdata($user_id);
        $guest_level      = sanitize_key((string) flosc_get_setting('default_guest_level', ''));
        $profile_completed = (bool) get_user_meta($user_id, '_flosc_magic_link_user_credentials_set', true)
            || !empty(get_user_meta($user_id, '_flosc_sso_linked_providers', true));
        $is_guest_user    = $bb_user && (
            ($guest_level !== '' && in_array($guest_level, (array) $bb_user->roles, true)) ||
            !empty(get_user_meta($user_id, '_flosc_sso_linked_providers', true))
        );

        $days_remaining = null;
        $guest_window   = max(0, intval(flosc_get_setting('guest_access_days', 0)));
        if ($is_guest_user && $bb_user && $guest_window > 0) {
            $days_elapsed   = floor((time() - strtotime($bb_user->user_registered)) / DAY_IN_SECONDS);
            $days_remaining = max(0, $guest_window - $days_elapsed);
        }

        $upgrade_url = flosc_get_setting('guest_link_upgrade_url', '');

        if (!$profile_completed && $is_guest_user) {
            // Anonymous public page notice — shown until guest completes profile
            $upgrade_link = $upgrade_url ? ' <a href="' . esc_url($upgrade_url) . '" class="flosc-guest-warning-link">Upgrade for full access.</a>' : '';
            $days_note    = ($days_remaining !== null)
                ? 'This page and all associated data will be removed from our servers in <strong>' . esc_html($days_remaining) . '</strong> day' . ($days_remaining !== 1 ? 's' : '') . ' if you don\'t upgrade.' . $upgrade_link
                : '';
            echo '<div class="flosc-guest-warning-card">';
            echo '<p class="flosc-guest-warning-title">This is your anonymous, public quiz score page.</p>';
            echo '<p class="flosc-guest-warning-copy">It becomes <strong>private</strong> — and you can listen to your recordings — once you complete your guest learner profile.</p>';
            if ($days_note) echo '<p class="flosc-guest-warning-copy flosc-guest-warning-copy--tight">' . wp_kses_post( $days_note ) . '</p>';
            echo '</div>';
        } elseif ($profile_completed && $is_guest_user && $days_remaining !== null) {
            // Profile completed — show simple days remaining banner
            $upgrade_link = $upgrade_url ? ' <a href="' . esc_url($upgrade_url) . '" class="flosc-guest-remaining-link">Upgrade for full access here.</a>' : '';
            echo '<p class="flosc-guest-remaining">You have <strong>' . esc_html($days_remaining) . '</strong> day' . ($days_remaining !== 1 ? 's' : '') . ' of guest access remaining — we hope you are enjoying your complimentary guest access!' . wp_kses_post( $upgrade_link ) . '</p>';
        }

        // Sessions: 1 session → display the result card directly; 2+ → wrap each in an accordion.
        if (!empty($quiz_attempts)) {
            $upload_dir = wp_upload_dir();
            $multi = count($quiz_attempts) > 1;

            if ($multi) {
                echo '<div class="flosc-quiz-sessions-wrap">';
                echo '<h3 class="flosc-quiz-section-title">Quiz Sessions</h3>';
            }

            foreach ($quiz_attempts as $idx => $attempt) {
                $session_num = $idx + 1;
                $attempt_score = intval($attempt['score'] ?? 0);
                $attempt_sid = $attempt['session_id'] ?? '';

                if ($multi) {
                    echo '<details class="flosc-quiz-details">';
                    echo '<summary class="flosc-quiz-summary">';
                    echo '<span class="flosc-quiz-summary-left">';
                    echo '<span class="flosc-bb-chevron flosc-quiz-chevron">&#9654;</span>';
                    echo '<span class="flosc-quiz-summary-title">Quiz Session ' . esc_html(str_pad((string) $session_num, 2, '0', STR_PAD_LEFT)) . '</span>';
                    echo '</span>';
                    echo '<span class="flosc-quiz-summary-score">' . esc_html($attempt_score) . '%</span>';
                    echo '</summary>';
                    echo '<div class="flosc-quiz-details-body">';
                }

                $attempt_quiz_data = $this->find_quiz_data_by_session($user_id, $attempt_sid, $quiz_data);
                if (empty($attempt_quiz_data) || !is_array($attempt_quiz_data)) {
                    // Fallback for users whose per-session lookup fails (e.g. older attempts whose session_id
                    // doesn't match _flosc_last_quiz_data / backup / history). Show their latest quiz data
                    // instead of rendering nothing.
                    $attempt_quiz_data = is_array($quiz_data) ? $quiz_data : [];
                }
                // Pin session_id to this attempt so per-phrase audio paths resolve to the correct session folder.
                if (is_array($attempt_quiz_data) && $attempt_sid) {
                    $attempt_quiz_data['session_id'] = $attempt_sid;
                }
                $this->render_session_result_card($user_id, $attempt_quiz_data, $is_guest_user, $profile_completed);

                if ($multi) {
                    echo '</div>';
                    echo '</details>';
                }
            }

            if ($multi) {
                echo '</div>';
            }
        }

        $can_view_my_files = current_user_can('manage_options') || (get_current_user_id() === (int) $user_id);
        if ($can_view_my_files) {
            $upload_dir = wp_upload_dir();
            $recording_items = [];
            $seen_files = [];

            $session_ids = [];
            if (!empty($quiz_attempts) && is_array($quiz_attempts)) {
                foreach ($quiz_attempts as $attempt) {
                    $sid = sanitize_text_field($attempt['session_id'] ?? '');
                    if ($sid && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $sid)) {
                        $session_ids[$sid] = true;
                    }
                }
            }

            $latest_sid = sanitize_text_field($quiz_data['session_id'] ?? '');
            if ($latest_sid && preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $latest_sid)) {
                $session_ids[$latest_sid] = true;
            }

            foreach (array_keys($session_ids) as $sid) {
                $session_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id . '/sessions/' . $sid;
                if (!is_dir($session_dir)) {
                    continue;
                }

                $files = glob($session_dir . '/phrase-*.*');
                if (!is_array($files) || empty($files)) {
                    continue;
                }

                natsort($files);
                foreach ($files as $filepath) {
                    $basename = basename($filepath);
                    if (!preg_match('/^phrase-(\d+)\.(mp4|webm|ogg|wav)$/i', $basename, $m)) {
                        continue;
                    }

                    $phrase_num = str_pad((string) intval($m[1]), 2, '0', STR_PAD_LEFT);
                    $ext = strtolower($m[2]);
                    $key = $sid . '|' . $basename;
                    if (isset($seen_files[$key])) {
                        continue;
                    }
                    $seen_files[$key] = true;

                    $mts_stamp = preg_replace('/-[0-9a-f]{5}$/', '', $sid);
                    $display_name = 'phrase_' . $phrase_num . '_' . $mts_stamp . '.' . $ext;
                    $download_url = admin_url('admin-ajax.php') . '?' . http_build_query([
                        'action' => 'flosc_serve_user_audio',
                        'user_id' => $user_id,
                        'session_id' => $sid,
                        'file' => $basename,
                        'download' => 1,
                    ]);

                    $recording_items[] = [
                        'sid' => $sid,
                        'sid_display' => $mts_stamp,
                        'url' => $download_url,
                        'name' => $display_name,
                        'ext' => $ext,
                    ];
                }
            }

            if (!empty($recording_items)) {
                usort($recording_items, function($a, $b) {
                    if ($a['sid'] === $b['sid']) {
                        return strcmp($a['name'], $b['name']);
                    }
                    return strcmp($b['sid'], $a['sid']);
                });

                echo '<div class="flosc-my-files">';
                echo '<div class="flosc-my-files-head">';
                echo '<h3 class="flosc-my-files-title">My Files</h3>';
                echo '<p class="flosc-my-files-subtitle">Download your recording files (MP4 and WebM) by session.</p>';
                echo '</div>';
                echo '<div class="flosc-my-files-body">';

                $current_sid = '';
                foreach ($recording_items as $item) {
                    if ($item['sid'] !== $current_sid) {
                        if ($current_sid !== '') {
                            echo '</ul>';
                        }
                        $current_sid = $item['sid'];
                        echo '<p class="flosc-my-files-session">Session: ' . esc_html($item['sid_display']) . '</p>';
                        echo '<ul class="flosc-my-files-list">';
                    }
                    echo '<li class="flosc-my-files-item">';
                    echo '<a class="flosc-my-files-link" href="' . esc_url($item['url']) . '" download="' . esc_attr($item['name']) . '">Download ' . esc_html(strtoupper($item['ext'])) . '</a>';
                    echo ' <span class="flosc-my-files-name">' . esc_html($item['name']) . '</span>';
                    echo '</li>';
                }
                if ($current_sid !== '') {
                    echo '</ul>';
                }

                echo '</div>';
                echo '</div>';
            }
        }

        // Phrase-level results — clickable accordions with word-level IPA + audio
        if ($phrase_results && empty($quiz_attempts)) {
            $word_ipa = $quiz_data['word_ipa'] ?? [];
            $session_id = $quiz_data['session_id'] ?? '';
            $upload_dir = wp_upload_dir();

            echo '<div>';
            echo '<h3 class="flosc-quiz-section-title">Phrase Breakdown</h3>';
            echo '<p class="flosc-phrase-breakdown-note">Click each phrase to expand the detailed analysis</p>';
            foreach ($phrase_results as $i => $pr) {
                $phrase_text = $pr['phrase'] ?? '';
                $data = $pr['data'] ?? [];
                $phrase_score = 0;
                $phoneme_count = 0;

                $words_data = $data['words'] ?? [['word' => $data['target_text'] ?? '', 'expected_ipa' => $data['expected_ipa'] ?? '', 'phonemes' => $data['phonemes'] ?? []]];
                foreach ($words_data as $w) {
                    foreach (($w['phonemes'] ?? []) as $ph) {
                        $phrase_score += floatval($ph['confidence'] ?? 0);
                        $phoneme_count++;
                    }
                }
                $pct = $phoneme_count > 0 ? intval(round(($phrase_score / $phoneme_count) * 100)) : 0;
                $pct_color = $pct >= 80 ? '#22c55e' : ($pct >= 60 ? '#eab308' : '#ef4444');

                echo '<details class="flosc-quiz-details">';
                echo '<summary class="flosc-quiz-summary">';
                echo '<span class="flosc-quiz-summary-left">';
                echo '<span class="flosc-bb-chevron flosc-quiz-chevron">&#9654;</span>';
                echo '<span class="flosc-quiz-summary-text"><strong>Phrase ' . esc_html($i + 1) . ':</strong> ' . esc_html($phrase_text) . '</span>';
                echo '</span>';
                echo '<span class="flosc-quiz-score ' . esc_attr($pct >= 80 ? 'flosc-score--good' : ($pct >= 60 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($pct) . '%</span>';
                echo '</summary>';
                echo '<div class="flosc-quiz-details-body">';

                // Audio playback — members always get audio; guests only once profile is completed
                if ((!$is_guest_user || $profile_completed) && $session_id) {
                    $phrase_num = $i + 1;
                    $user_audio_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id . '/sessions/' . $session_id;
                    $this->render_phrase_audio_player_and_download($user_id, $session_id, $phrase_num, $user_audio_dir);
                }

                // Word-level breakdown
                foreach ($words_data as $w) {
                    $word_text = $w['word'] ?? '';
                    $w_phonemes = $w['phonemes'] ?? [];
                    $w_avg = count($w_phonemes) > 0 ? array_sum(array_column($w_phonemes, 'confidence')) / count($w_phonemes) : 0;
                    $w_pct = round($w_avg * 100);
                    $w_color = $w_avg >= 0.5 ? '#22c55e' : ($w_avg >= 0.1 ? '#eab308' : '#ef4444');
                    $key = strtolower($word_text);
                    $ipa_data = $word_ipa[$key] ?? [];

                    echo '<div class="flosc-word-card">';
                    echo '<div class="flosc-word-head">';
                    echo '<span class="flosc-word-title">' . esc_html($word_text) . '</span>';
                    echo '<span class="flosc-word-score ' . esc_attr($w_avg >= 0.5 ? 'flosc-score--good' : ($w_avg >= 0.1 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($w_pct) . '%</span>';
                    echo '</div>';

                    // IPA reference rows (when word_ipa data is available)
                    if (!empty($ipa_data)) {
                        $ipa_rows = [];
                        if (!empty($ipa_data['mw'])) $ipa_rows['merriam-webster'] = $ipa_data['mw'];
                        $da1ni5_val = $ipa_data['da1ni5'] ?? '';
                        if (is_array($da1ni5_val)) $da1ni5_val = implode(' | ', $da1ni5_val);
                        if ($da1ni5_val) $ipa_rows['da1ni5'] = $da1ni5_val;
                        if (!empty($w['expected_ipa'])) $ipa_rows['scored as'] = $w['expected_ipa'];
                        foreach ($ipa_rows as $label => $val) {
                            echo '<div class="flosc-ipa-row">';
                            echo '<span class="flosc-ipa-label">' . esc_html($label) . '</span>';
                            echo '<span class="flosc-ipa-value">[' . esc_html($val) . ']</span>';
                            echo '</div>';
                        }
                    }

                    // Phoneme confidence bars
                    foreach ($w_phonemes as $ph) {
                        $conf = floatval($ph['confidence'] ?? 0);
                        $ph_pct = round($conf * 100, 1);
                        $bar_w = max(1, $conf * 100);
                        $ph_color = $conf >= 0.5 ? '#22c55e' : ($conf >= 0.1 ? '#eab308' : '#ef4444');
                        echo '<div class="flosc-phoneme-row">';
                        echo '<span class="flosc-phoneme-ipa">' . esc_html($ph['ipa'] ?? '') . '</span>';
                        echo '<progress class="flosc-phoneme-progress" max="100" value="' . esc_attr($bar_w) . '"></progress>';
                        echo '<span class="flosc-phoneme-score ' . esc_attr($conf >= 0.5 ? 'flosc-score--good' : ($conf >= 0.1 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($ph_pct) . '%</span>';
                        echo '</div>';
                    }

                    echo '</div>';
                }

                echo '</div>';
                echo '</details>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Resolve a full quiz payload for a specific session id.
     */
    private function find_quiz_data_by_session($user_id, $session_id, $current_quiz_data = []) {
        if (empty($session_id)) {
            return [];
        }

        if (is_array($current_quiz_data) && (($current_quiz_data['session_id'] ?? '') === $session_id)) {
            return $current_quiz_data;
        }

        $backup_data = get_user_meta($user_id, '_flosc_last_quiz_data_backup', true);
        if (is_array($backup_data) && (($backup_data['session_id'] ?? '') === $session_id)) {
            return $backup_data;
        }

        $history = get_user_meta($user_id, '_flosc_quiz_data_history', true);
        if (is_array($history)) {
            foreach (array_reverse($history) as $entry) {
                $entry_data = $entry['data'] ?? [];
                if (is_array($entry_data) && (($entry_data['session_id'] ?? '') === $session_id)) {
                    return $entry_data;
                }
            }
        }

        // Fallback: usermeta LIKE candidates (cached prepared query).
        $candidate_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_last_quiz_data', $session_id, 'LIKE', 10 )
            : array();
        foreach ( $candidate_ids as $candidate_id ) {
            $data = get_user_meta( (int) $candidate_id, '_flosc_last_quiz_data', true );
            if ( is_array( $data ) && ( ( $data['session_id'] ?? '' ) === $session_id ) ) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Render phrase audio player and MP4 download link for one phrase.
     */
    private function render_phrase_audio_player_and_download($user_id, $session_id, $phrase_num, $user_audio_dir) {
        $audio_file = '';
        foreach (['mp4', 'm4a', 'wav'] as $ext) {
            if (file_exists($user_audio_dir . '/phrase-' . $phrase_num . '.' . $ext)) {
                $audio_file = 'phrase-' . $phrase_num . '.' . $ext;
                break;
            }
        }

        if (!$audio_file) {
            if (file_exists($user_audio_dir . '/phrase-' . $phrase_num . '.webm') || file_exists($user_audio_dir . '/phrase-' . $phrase_num . '.ogg')) {
                echo '<div class="flosc-playback-pending">Playback copy is processing. Please refresh shortly.</div>';
            }
            return;
        }

        $expires = time() + 3600;
        $sig = $this->build_audio_access_signature($user_id, $session_id, $audio_file, $expires);

        $audio_url = admin_url('admin-ajax.php') . '?' . http_build_query([
            'action' => 'flosc_serve_user_audio',
            'user_id' => $user_id,
            'session_id' => $session_id,
            'file' => $audio_file,
            'exp' => $expires,
            'sig' => $sig,
        ]);

        echo '<div class="flosc-audio-wrap">';
        echo '<audio class="flosc-audio-stream" controls controlsList="nodownload" src="' . esc_url($audio_url) . '"></audio>';

        echo '</div>';
    }

    /**
     * Build short-lived signature for protected audio URLs.
     */
    private function build_audio_access_signature($user_id, $session_id, $file, $expires) {
        $payload = implode('|', [
            (int) $user_id,
            (string) $session_id,
            (string) $file,
            (int) $expires,
        ]);

        return hash_hmac('sha256', $payload, flosc_token_secret());
    }

    /**
     * Validate signature for protected audio URL access.
     */
    private function is_valid_audio_access_signature($user_id, $session_id, $file, $expires, $sig) {
        if (!$expires || !$sig) {
            return false;
        }

        $now = time();
        if ($expires < $now || $expires > ($now + DAY_IN_SECONDS)) {
            return false;
        }

        $expected = $this->build_audio_access_signature($user_id, $session_id, $file, $expires);
        return hash_equals($expected, $sig);
    }

    /**
     * Render the formatted phrase breakdown block for a specific quiz payload.
     */
    private function render_phrase_breakdown_for_quiz_data($user_id, $quiz_data, $is_guest_user, $profile_completed) {
        if (empty($quiz_data) || !is_array($quiz_data)) {
            return;
        }

        $phrase_results = $quiz_data['phrase_results'] ?? [];
        if (empty($phrase_results) || !is_array($phrase_results)) {
            return;
        }

        $word_ipa = $quiz_data['word_ipa'] ?? [];
        $session_id = $quiz_data['session_id'] ?? '';
        $upload_dir = wp_upload_dir();

        echo '<div class="flosc-phrase-breakdown-wrap">';
        echo '<h4 class="flosc-phrase-breakdown-title">Phrase Breakdown</h4>';
        echo '<p class="flosc-phrase-breakdown-note">Click each phrase to expand the detailed analysis</p>';

        foreach ($phrase_results as $i => $pr) {
            $phrase_text = $pr['phrase'] ?? '';
            $data = $pr['data'] ?? [];
            $phrase_score = 0;
            $phoneme_count = 0;

            $words_data = $data['words'] ?? [['word' => $data['target_text'] ?? '', 'expected_ipa' => $data['expected_ipa'] ?? '', 'phonemes' => $data['phonemes'] ?? []]];
            foreach ($words_data as $w) {
                foreach (($w['phonemes'] ?? []) as $ph) {
                    $phrase_score += floatval($ph['confidence'] ?? 0);
                    $phoneme_count++;
                }
            }
            $pct = $phoneme_count > 0 ? intval(round(($phrase_score / $phoneme_count) * 100)) : 0;
            $pct_color = $pct >= 80 ? '#22c55e' : ($pct >= 60 ? '#eab308' : '#ef4444');

            echo '<details class="flosc-quiz-details">';
            echo '<summary class="flosc-quiz-summary">';
            echo '<span class="flosc-quiz-summary-left">';
            echo '<span class="flosc-bb-chevron flosc-quiz-chevron">&#9654;</span>';
            echo '<span class="flosc-quiz-summary-text"><strong>Phrase ' . esc_html($i + 1) . ':</strong> ' . esc_html($phrase_text) . '</span>';
            echo '</span>';
            echo '<span class="flosc-quiz-score ' . esc_attr($pct >= 80 ? 'flosc-score--good' : ($pct >= 60 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($pct) . '%</span>';
            echo '</summary>';
            echo '<div class="flosc-quiz-details-body">';

            if ((!$is_guest_user || $profile_completed) && $session_id) {
                $phrase_num = $i + 1;
                $user_audio_dir = $upload_dir['basedir'] . '/flosc-users/' . $user_id . '/sessions/' . $session_id;
                $this->render_phrase_audio_player_and_download($user_id, $session_id, $phrase_num, $user_audio_dir);
            }

            foreach ($words_data as $w) {
                $word_text = $w['word'] ?? '';
                $w_phonemes = $w['phonemes'] ?? [];
                $w_avg = count($w_phonemes) > 0 ? array_sum(array_column($w_phonemes, 'confidence')) / count($w_phonemes) : 0;
                $w_pct = round($w_avg * 100);
                $w_color = $w_avg >= 0.5 ? '#22c55e' : ($w_avg >= 0.1 ? '#eab308' : '#ef4444');
                $key = strtolower($word_text);
                $ipa_data = $word_ipa[$key] ?? [];

                echo '<div class="flosc-word-card">';
                echo '<div class="flosc-word-head">';
                echo '<span class="flosc-word-title">' . esc_html($word_text) . '</span>';
                echo '<span class="flosc-word-score ' . esc_attr($w_avg >= 0.5 ? 'flosc-score--good' : ($w_avg >= 0.1 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($w_pct) . '%</span>';
                echo '</div>';

                if (!empty($ipa_data)) {
                    $ipa_rows = [];
                    if (!empty($ipa_data['mw'])) $ipa_rows['merriam-webster'] = $ipa_data['mw'];
                    $da1ni5_val = $ipa_data['da1ni5'] ?? '';
                    if (is_array($da1ni5_val)) $da1ni5_val = implode(' | ', $da1ni5_val);
                    if ($da1ni5_val) $ipa_rows['da1ni5'] = $da1ni5_val;
                    if (!empty($w['expected_ipa'])) $ipa_rows['scored as'] = $w['expected_ipa'];
                    foreach ($ipa_rows as $label => $val) {
                        echo '<div class="flosc-ipa-row">';
                        echo '<span class="flosc-ipa-label">' . esc_html($label) . '</span>';
                        echo '<span class="flosc-ipa-value">[' . esc_html($val) . ']</span>';
                        echo '</div>';
                    }
                }

                foreach ($w_phonemes as $ph) {
                    $conf = floatval($ph['confidence'] ?? 0);
                    $ph_pct = round($conf * 100, 1);
                    $bar_w = max(1, $conf * 100);
                    $ph_color = $conf >= 0.5 ? '#22c55e' : ($conf >= 0.1 ? '#eab308' : '#ef4444');
                    echo '<div class="flosc-phoneme-row">';
                    echo '<span class="flosc-phoneme-ipa">' . esc_html($ph['ipa'] ?? '') . '</span>';
                    echo '<progress class="flosc-phoneme-progress" max="100" value="' . esc_attr($bar_w) . '"></progress>';
                    echo '<span class="flosc-phoneme-score ' . esc_attr($conf >= 0.5 ? 'flosc-score--good' : ($conf >= 0.1 ? 'flosc-score--warn' : 'flosc-score--bad')) . '">' . esc_html($ph_pct) . '%</span>';
                    echo '</div>';
                }

                echo '</div>';
            }

            echo '</div>';
            echo '</details>';
        }

        echo '</div>';
    }

    /**
     * Enqueue Assets
     * v1.2.1: Uses is_flosc_request() to check both slug and custom domain
     * v1.9.5: Nuclear dequeue - removes ALL theme/plugin CSS and JS.
     *   The FLOSC app page is a standalone SPA; it needs zero theme assets.
     *   Previously ran at priority 10 which let 22 theme CSS files and 93 scripts
     *   survive because BuddyBoss/Divi/WooCommerce enqueued at the same priority.
     *   Now runs at priority 9999 so everything is already in the queue when we clean it.
     */
    public function enqueue_assets() {
        if (!$this->is_flosc_request()) {
            return;
        }

        // -- NUCLEAR DEQUEUE: Remove ALL non-FLOSC styles --
        // At priority 9999, every theme/plugin has already enqueued.
        // We iterate the full queue and remove everything not ours.
        global $wp_styles, $wp_scripts;

        // Companion widget must survive if both surfaces ever share a request.
        $flosc_style_whitelist = ['flosc-layout', 'flosc-theme', 'flosc-offers', 'flosc-preset', 'flosc-companion'];
        if (isset($wp_styles->queue) && is_array($wp_styles->queue)) {
            foreach ($wp_styles->queue as $handle) {
                if (in_array($handle, $flosc_style_whitelist, true)) {
                    continue;
                }
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }

        // -- NUCLEAR DEQUEUE: Remove ALL non-FLOSC scripts --
        // Keep only flosc-app.js, companion, and payment SDKs (PayPal, Stripe).
        $flosc_script_whitelist = ['flosc-app', 'flosc-companion', 'paypal-js', 'stripe-js'];
        if (isset($wp_scripts->queue) && is_array($wp_scripts->queue)) {
            foreach ($wp_scripts->queue as $handle) {
                if (in_array($handle, $flosc_script_whitelist, true)) {
                    continue;
                }
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }

        // Our assets - v9.3.7 Clean CSS Architecture
        // 1. Layout CSS (structure only, no colors)
        wp_enqueue_style(
            'flosc-layout',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-layout.css',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-layout.css')
        );

        // 2. Theme CSS (connects variables to selectors)
        wp_enqueue_style(
            'flosc-theme',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-theme.css',
            ['flosc-layout'],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-theme.css')
        );

        // v1.6.2: Offer/checkout/autoprompt CSS (extracted from inline JS)
        wp_enqueue_style(
            'flosc-offers',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-offers.css',
            ['flosc-theme'],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-offers.css')
        );

        // 3. Preset CSS (variable definitions only)
        $this->enqueue_chat_style();

        // Payment SDKs first. Explicit version satisfies Plugin Check; CDN URLs strip
        // ?ver= via script_loader_src (PayPal rejects unknown query params).
        $flosc_app_deps = [];

        // Stripe.js — first-class when publishable key is set on Payments (WPDB).
        $stripe = $this->sale_manager->get_provider('stripe');
        if ($stripe && method_exists($stripe, 'get_client_config')) {
            $stripe_cfg = $stripe->get_client_config();
            $stripe_pk = (string) ($stripe_cfg['publishableKey'] ?? '');
            if ($stripe_pk !== '') {
                wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', [], defined('FLOSC_VERSION') ? FLOSC_VERSION : '1', true);
                $flosc_app_deps[] = 'stripe-js';
            }
        }

        // PayPal JS SDK — currency from provider; intent from active offer *type*
        // (capture for one_time; subscription only when type is subscription or
        // subscription.plans is set — not when a leftover subscription{} bag exists).
        $paypal = $this->sale_manager->get_provider('paypal');
        if ($paypal && $paypal->has_client_id()) {
            $pp_config = $paypal->get_client_config();
            $pp_client_id = $pp_config['clientId'] ?? '';
            if ($pp_client_id) {
                $pp_currency = $pp_config['currency'] ?? 'USD';
                $pp_intent = 'capture';
                $flow = $this->get_current_flow();
                $stem = '';
                if (is_array($flow)) {
                    $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
                    $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                    if ($stem === '' && !empty($flow['id'])) {
                        $stem = sanitize_key((string) $flow['id']);
                    }
                }
                if ($stem !== '') {
                    $fs = get_option('flosc_flow_' . $stem, []);
                    $flow_offers = is_array($fs['offers'] ?? null) ? $fs['offers'] : [];
                    foreach ($flow_offers as $o) {
                        if (!is_array($o)) {
                            continue;
                        }
                        $active = !empty($o['active']) || (($o['status'] ?? '') === 'active');
                        if (!$active) {
                            continue;
                        }
                        $type = strtolower((string) ($o['type'] ?? 'one_time'));
                        $has_plans = !empty($o['subscription']['plans']) && is_array($o['subscription']['plans']);
                        if ($type === 'subscription' || $has_plans) {
                            $pp_intent = 'subscription';
                            break;
                        }
                    }
                }
                $pp_sdk = 'https://www.paypal.com/sdk/js?client-id=' . rawurlencode($pp_client_id)
                    . '&currency=' . rawurlencode($pp_currency)
                    . '&intent=' . rawurlencode($pp_intent);
                if ($pp_intent === 'subscription') {
                    $pp_sdk .= '&vault=true';
                }
                // Explicit version for Plugin Check; ver is stripped for this handle below.
                wp_enqueue_script('paypal-js', $pp_sdk, [], defined('FLOSC_VERSION') ? FLOSC_VERSION : '1', true);
                $flosc_app_deps[] = 'paypal-js';
            }
        }

        // Strip WP ?ver= from third-party payment CDN scripts (PayPal SDK query contract).
        if (!empty($flosc_app_deps)) {
            add_filter('script_loader_src', static function ($src, $handle) {
                if (in_array($handle, ['paypal-js', 'stripe-js'], true) && is_string($src) && $src !== '') {
                    return remove_query_arg('ver', $src);
                }
                return $src;
            }, 10, 2);
        }

        $flosc_app_js = FLOSC_PLUGIN_DIR . 'assets/js/flosc-app.js';
        wp_enqueue_script(
            'flosc-app',
            FLOSC_PLUGIN_URL . 'assets/js/flosc-app.js',
            $flosc_app_deps,
            file_exists($flosc_app_js) ? filemtime($flosc_app_js) : (defined('FLOSC_VERSION') ? FLOSC_VERSION : '8.0.0'),
            true
        );
    }

    public function enqueue_companion() {
        return $this->companion_mode->enqueue_companion();
    }

    private function resolve_companion_flow_context($handoff_request = false) {
        return $this->companion_mode->resolve_companion_flow_context($handoff_request);
    }

    private function find_companion_flows_for_request($req_path, array $category_slugs) {
        return $this->companion_mode->find_companion_flows_for_request($req_path, $category_slugs);
    }

    private function get_companion_request_category_slugs() {
        return $this->companion_mode->get_companion_request_category_slugs();
    }

    private function get_companion_chat_app_url() {
        return $this->companion_mode->get_companion_chat_app_url();
    }

    private function get_flow_by_slug_for_companion($slug) {
        return $this->companion_mode->get_flow_by_slug_for_companion($slug);
    }

    private function get_current_frontend_url() {
        return $this->companion_mode->get_current_frontend_url();
    }

    private function get_companion_defaults() {
        return $this->companion_mode->get_companion_defaults();
    }

    private function get_companion_numeric_limits() {
        return $this->companion_mode->get_companion_numeric_limits();
    }

    private function get_companion_allowed_modes() {
        return $this->companion_mode->get_companion_allowed_modes();
    }

    private function get_companion_widget_modes() {
        return $this->companion_mode->get_companion_widget_modes();
    }

    private function get_companion_allowed_positions() {
        return $this->companion_mode->get_companion_allowed_positions();
    }

    private function get_companion_mobile_behaviors() {
        return $this->companion_mode->get_companion_mobile_behaviors();
    }

    private function get_companion_context_scopes() {
        return $this->companion_mode->get_companion_context_scopes();
    }

    private function get_companion_motion_modes() {
        return $this->companion_mode->get_companion_motion_modes();
    }

    private function get_companion_state_storages() {
        return $this->companion_mode->get_companion_state_storages();
    }

    private function get_companion_launcher_svg_paths() {
        return $this->companion_mode->get_companion_launcher_svg_paths();
    }

    private function build_companion_context_params($scope) {
        return $this->companion_mode->build_companion_context_params($scope);
    }

    private function parse_companion_path_patterns($raw_patterns) {
        return $this->companion_mode->parse_companion_path_patterns($raw_patterns);
    }

    private function get_companion_target_types() {
        return $this->companion_mode->get_companion_target_types();
    }

    private function should_show_companion_for_current_request() {
        return $this->companion_mode->should_show_companion_for_current_request();
    }

    private function parse_companion_target_rules($raw_rules) {
        return $this->companion_mode->parse_companion_target_rules($raw_rules);
    }

    private function companion_target_matches_any_rule($rules) {
        return $this->companion_mode->companion_target_matches_any_rule($rules);
    }

    private function get_companion_request_path() {
        return $this->companion_mode->get_companion_request_path();
    }


    /**
     * Enqueue chat styling (v9.3.9 - Bulletproof Architecture)
     *
     * Architecture:
     * 1. flosc-layout.css - Structure only (already enqueued)
     * 2. flosc-theme.css - Variable consumption (already enqueued)
     * 3. This method - Variable definitions via inline CSS
     *
    * Presets: auto (system preference), light, dark
    * Customization: bubble style, accent color, font, scale
     */
    private function enqueue_chat_style() {
        // v1.6.1: Per-flow settings via FLOSC_Flow_Manager::get_setting()
        $fm = FLOSC_Flow_Manager::instance();
        $preset     = $fm->get_setting('flosc_chat_style_preset', 'style', 'preset', 'light');
        $bubble     = $fm->get_setting('flosc_chat_style_bubble', 'style', 'bubble', 'subtle-notch');
        $accent     = $fm->get_setting('flosc_chat_style_accent', 'style', 'accent', '');
        $font       = $fm->get_setting('flosc_chat_style_font', 'style', 'font', 'system');
        $scale      = intval($fm->get_setting('flosc_chat_style_scale', 'style', 'scale', 100));

        // Bubble style presets (border-radius values per FLOSC_STYLE_GUIDE.md)
        $bubble_styles = [
            'subtle-notch' => ['user' => '18px 18px 4px 18px', 'assistant' => '4px 18px 18px 18px'],
            'classic'      => ['user' => '18px 18px 0 18px',   'assistant' => '0 18px 18px 18px'],
            'modern'       => ['user' => '20px 20px 6px 20px', 'assistant' => '6px 20px 20px 20px'],
            'minimal'      => ['user' => '16px',               'assistant' => '16px'],
            'sharp'        => ['user' => '12px 12px 2px 12px', 'assistant' => '2px 12px 12px 12px'],
        ];

        // Font family map
        $font_families = [
            'system'        => '',
            'inter'         => '"Inter", -apple-system, sans-serif',
            'ibm-plex-sans' => '"IBM Plex Sans", -apple-system, sans-serif',
            'ibm-plex-mono' => '"IBM Plex Mono", "SF Mono", Monaco, monospace',
            'roboto'        => '"Roboto", -apple-system, sans-serif',
            'roboto-mono'   => '"Roboto Mono", "SF Mono", Monaco, monospace',
            'fira-code'     => '"Fira Code", "SF Mono", Monaco, monospace',
        ];

        // File paths
        $light_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-light.css';
        $dark_path  = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-dark.css';

        $inline_css = '';

        // ===========================================
        // PRESET LOADING
        // ===========================================
        if ($preset === 'auto') {
            // Auto mode: Light by default, dark via prefers-color-scheme
            if (file_exists($light_path) && file_exists($dark_path)) {
                $light_content = flosc_fs_get_contents($light_path);
                $dark_content  = flosc_fs_get_contents($dark_path);

                if ($light_content) {
                    $light_vars = $this->extract_css_variables($light_content);
                    if ($light_vars) {
                        $inline_css .= "/* Light Theme (Default) */\n:root {\n{$light_vars}}\n\n";
                    }
                }

                if ($dark_content) {
                    $dark_vars = $this->extract_css_variables($dark_content);
                    if ($dark_vars) {
                        $inline_css .= "/* Dark Theme (System Preference) */\n@media (prefers-color-scheme: dark) {\n  :root {\n{$dark_vars}  }\n}\n\n";
                    }
                }
            }
        } else {
            // Named preset (light, dark, chatgpt, claude, grok): load as external stylesheet
            $safe_preset = preg_replace('/[^a-z0-9-]/', '', $preset);
            $preset_path = FLOSC_PLUGIN_DIR . 'assets/css/chat-style-' . $safe_preset . '.css';
            if (file_exists($preset_path)) {
                wp_enqueue_style(
                    'flosc-preset',
                    FLOSC_PLUGIN_URL . 'assets/css/chat-style-' . $safe_preset . '.css',
                    ['flosc-theme'],
                    filemtime($preset_path)
                );
            }
        }

        // ===========================================
        // DYNAMIC OVERRIDES
        // ===========================================
        $bubble_config = $bubble_styles[$bubble] ?? $bubble_styles['subtle-notch'];

        $overrides = [];
        $overrides[] = "--flosc-user-message-radius: {$bubble_config['user']}";
        $overrides[] = "--flosc-assistant-message-radius: {$bubble_config['assistant']}";

        // v1.6.1: Full accent color cascade (5->15 derived variables)
        if (!empty($accent) && $accent !== '#2563eb') {
            // Compute derived colors from hex accent
            $hover   = $this->adjust_color_brightness($accent, -15);
            $subtle  = $this->hex_to_rgba($accent, 0.06);
            $subtle4 = $this->hex_to_rgba($accent, 0.04);
            $light   = $this->adjust_color_brightness($accent, 40);

            // Core accent
            $overrides[] = "--flosc-accent: {$accent}";
            $overrides[] = "--flosc-accent-hover: {$hover}";
            $overrides[] = "--flosc-accent-subtle: {$subtle}";

            // Components that derive from accent
            $overrides[] = "--flosc-user-message-bg: {$accent}";
            $overrides[] = "--flosc-user-avatar-bg: {$accent}";
            $overrides[] = "--flosc-send-btn-bg: {$accent}";
            $overrides[] = "--flosc-pill-hover-text: {$accent}";
            $overrides[] = "--flosc-pill-hover-border: {$light}";
            $overrides[] = "--flosc-card-hover-text: {$accent}";
            $overrides[] = "--flosc-card-hover-border: {$light}";
            $overrides[] = "--flosc-content-link: {$accent}";
            $overrides[] = "--flosc-content-link-hover: {$hover}";
            $overrides[] = "--flosc-content-blockquote-border: {$accent}";
            $overrides[] = "--flosc-content-blockquote-bg: {$subtle4}";
            $overrides[] = "--flosc-quiz-tab-active-bg: {$accent}";
            $overrides[] = "--flosc-quiz-input-focus-border: {$accent}";
        }

        // Scale factor
        if ($scale !== 100 && $scale > 0) {
            $scale_factor = $scale / 100;
            $overrides[] = "--flosc-scale: {$scale_factor}";
        }

        // Font family
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $overrides[] = "--flosc-font-family: {$font_families[$font]}";
        }

        if (!empty($overrides)) {
            $inline_css .= "/* Dynamic Overrides */\n:root {\n    " . implode(";\n    ", $overrides) . ";\n}\n\n";
        }

        // Font application
        if ($font !== 'system' && isset($font_families[$font]) && !empty($font_families[$font])) {
            $inline_css .= "/* Font Application */\n";
            $inline_css .= ".flosc-app,\n.flosc-app .messages,\n.flosc-app .message-text {\n";
            $inline_css .= "    font-family: var(--flosc-font-family) !important;\n}\n\n";
        }

        // Attach inline styles to flosc-theme handle (always exists)
        if (!empty(trim($inline_css))) {
            wp_add_inline_style('flosc-theme', $inline_css);
        }
    }

    /**
     * Extract CSS variables from stylesheet content
     * Returns the inner content of :root { } block
     *
     * @param string $css_content Raw CSS file content
     * @return string Variable declarations or empty string
     */
    private function extract_css_variables($css_content) {
        if (empty($css_content)) {
            return '';
        }

        // Remove CSS comments
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css_content);

        // Extract content inside :root { }
        if (preg_match('/:root\s*\{([^}]+)\}/s', $css, $matches)) {
            return trim($matches[1]) . "\n";
        }

        return '';
    }

    /**
     * Adjust hex color brightness by a percentage (-100 to +100).
     * Negative = darker, positive = lighter.
     * v1.6.1: Used for accent color cascade.
     */
    private function adjust_color_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + round($r * $percent / 100)));
        $g = max(0, min(255, $g + round($g * $percent / 100)));
        $b = max(0, min(255, $b + round($b * $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Convert hex color to rgba string.
     * v1.6.1: Used for accent-subtle generation.
     */
    private function hex_to_rgba($hex, $alpha) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }
}

// Initialize
function flosc() {
    return FLOSC_Framework::instance();
}

/**
 * Helper: Adjust hex color brightness
 */
function flosc_adjust_brightness($hex, $percent) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    
    return sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
}

/**
 * IVR import/export/sync hooks were extracted to a dedicated include to keep
 * this bootstrap file smaller and easier to maintain.
 * Flow runtime (bag-first load + flow_* dual-read helpers) must load before
 * sync/admin/shell callers that use flosc_resolve_flow_runtime / flosc_flow_*.
 */
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-flow-runtime.php';
require_once FLOSC_PLUGIN_DIR . 'includes/portability/flosc-ivr-sync.php';
require_once FLOSC_PLUGIN_DIR . 'includes/flosc-lifecycle.php';

/**
 * Lifecycle hooks are loaded from a dedicated include to keep this bootstrap
 * file focused on framework bootstrapping.
 */

// Register activation hook
register_activation_hook(__FILE__, 'flosc_activate');
register_deactivation_hook(__FILE__, 'flosc_deactivate');

// Translations load automatically on WordPress.org-hosted plugins (WP 4.6+);
// no load_plugin_textdomain() call is needed.

// Start the plugin
add_action('plugins_loaded', 'flosc');

/**
 * v1.2.4: Global helper function for flow-aware settings
 * 
 * Usage: flosc_get_setting('ai_provider', 'ivr')
 * Checks: flow[$key] → get_option('flosc_' . $key) → $default
 * 
 * @param string $key Setting key (without 'flosc_' prefix)
 * @param mixed $default Default if neither flow nor global has value
 * @param string|null $flow_id Force specific flow (null = auto-detect)
 * @return mixed The setting value
 */
function flosc_get_setting($key, $default = '', $flow_id = null) {
    return FLOSC_Framework::instance()->get_setting($key, $default, $flow_id);
}

/**
 * Get the flow's favicon URL (browser tab icon).
 * Reads favicon_url from flow identity. Falls back to bundled FLOSC default icon.
 */
function flosc_get_favicon_url($size = '') {
    $identity = FLOSC_Framework::instance()->get_floscflow_identity();
    if (!empty($identity['favicon_url'])) return $identity['favicon_url'];
    $suffix = $size ? "-{$size}" : '';
    return FLOSC_PLUGIN_URL . "assets/img/flosc-icon{$suffix}.png";
}

/**
 * Resolve Chat Logo URL from a flow settings array (admin or runtime).
 * Prefer identity.chatlogo_url, then flat chatlogo_url. Does not invent a brand logo.
 *
 * @param array|null $flow_settings Flow option / current settings. Null = current runtime identity.
 * @param bool       $use_plugin_default When true and nothing set, return bundled FLOSC icon.
 * @return string URL or empty string when $use_plugin_default is false and no logo is configured.
 */
function flosc_resolve_chatlogo_url( $flow_settings = null, $use_plugin_default = true ) {
    $url = '';

    if ( is_array( $flow_settings ) ) {
        if ( ! empty( $flow_settings['identity'] ) && is_array( $flow_settings['identity'] ) ) {
            $url = (string) ( $flow_settings['identity']['chatlogo_url'] ?? '' );
        }
        if ( $url === '' && ! empty( $flow_settings['chatlogo_url'] ) ) {
            $url = (string) $flow_settings['chatlogo_url'];
        }
    }

    if ( $url === '' && function_exists( 'flosc' ) && is_object( flosc() ) && method_exists( flosc(), 'get_floscflow_identity' ) ) {
        $identity = flosc()->get_floscflow_identity();
        if ( is_array( $identity ) && ! empty( $identity['chatlogo_url'] ) ) {
            $url = (string) $identity['chatlogo_url'];
        }
    }

    $url = esc_url_raw( trim( $url ) );
    if ( $url !== '' ) {
        return $url;
    }

    if ( $use_plugin_default ) {
        return FLOSC_PLUGIN_URL . 'assets/img/flosc-icon.png';
    }

    return '';
}

/**
 * Get the flow's chatLogo URL (landing state header image, sidebar logo).
 * Reads chatlogo_url from flow identity. Falls back to bundled FLOSC default icon.
 */
function flosc_get_chatlogo_url() {
    return flosc_resolve_chatlogo_url( null, true );
}


