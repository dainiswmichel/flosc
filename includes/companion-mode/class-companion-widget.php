<?php
/**
 * FLOSC Companion Widget
 * 
 * Renders a floating chat companion on WordPress pages.
 * This is the "Companion Mode" — a lightweight chatbot widget that
 * accompanies members as they browse lesson content on the WordPress site.
 * 
 * Separation of Concerns:
 * - This class handles ONLY widget registration, asset enqueueing, and HTML injection
 * - flosc-companion.js handles ONLY the widget UI and REST API communication
 * - flosc-companion.css handles ONLY the widget layout and styling
 * - The full FLOSC app (flosc-app.js) is NOT loaded — these are independent experiences
 * 
 * Architecture:
 * - Hooks wp_footer to inject widget container on non-app WP pages
 * - Enqueues companion-specific JS/CSS (NOT the full app assets)
 * - Generates FLOSC_COMPANION object with minimal config (REST, nonce, user, context)
 * - Detects current page context (lesson post, category, general) for contextual awareness
 * - Respects per-flow settings via companion override group
 * 
 * Display modes (admin-configured per-flow; labels: Full-page / Companion / Hybrid):
 * - 'in_chat'    — Full-page only (default)
 * - 'companion'  — Companion bubble on WP pages
 * - 'both'       — Hybrid: full-page + companion, expand/collapse
 * 
 * @package FLOSC
 * @since   1.6.0
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Companion_Widget {

    /**
     * Singleton instance
     * @var self|null
     */
    private static $instance = null;

    /**
     * Cached flow settings to avoid repeated lookups
     * @var array|null
     */
    private $cached_settings = null;

    /**
     * Get singleton instance
     * @return self
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor — register hooks
     */
    private function __construct() {
        // Only hook on frontend, not admin
        if (!is_admin()) {
            add_action('template_redirect', [$this, 'apply_companion_cache_policy'], 0);
            add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
            add_action('wp_footer',          [$this, 'maybe_render_widget']);
        }
    }

    /**
     * Apply no-cache policy for companion pages.
     *
     * Why: page/edge caches can hold stale HTML that references old asset
     * versions, forcing users to append query parameters to get fresh layout
     * behavior. For companion-enabled pages, always emit no-cache signals and
     * common cache-plugin bypass constants so normal URLs stay current.
     */
    public function apply_companion_cache_policy() {
        if (!$this->should_load()) {
            return;
        }

        // Cache plugins (WP Super Cache, W3TC, LiteSpeed, etc.) look for this
        // exact unprefixed constant — cannot use a flosc_ prefix.
        if (!defined('DONOTCACHEPAGE')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- industry-standard cache-bypass flag; name is not under our control
            define('DONOTCACHEPAGE', true);
        }

        nocache_headers();
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
            header('Pragma: no-cache');
            header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Settings
    // ──────────────────────────────────────────────────────────────

    /**
     * Get companion settings for a flow (with caching)
     * 
     * Falls back to global defaults when no flow or when use_global is true.
     * 
     * @param  string|null $flow_id  Optional flow ID
     * @return array {
     *     @type string $content_display_mode  'in_chat'|'companion'|'both'
     *     @type bool   $enabled               Whether companion is active
     *     @type string $position              'bottom-right'|'bottom-left'
     *     @type string $greeting              Greeting message in widget
     *     @type string $accent_color          Hex color or empty for theme default
     *     @type bool   $show_for_visitors     Whether non-logged-in users see the widget
     * }
     */
    public function get_settings($flow_id = null) {
        if ($this->cached_settings !== null && $flow_id === null) {
            return $this->cached_settings;
        }

        $defaults = [
            'content_display_mode' => 'in_chat',
            'enabled'              => false,
            'position'             => 'bottom-right',
            'greeting'             => 'Chat with us',
            'accent_color'         => '',
            'show_for_visitors'    => true,
        ];

        // Attempt per-flow resolution
        $flow_manager = $this->get_flow_manager();
        if ($flow_manager) {
            foreach ($defaults as $key => $default) {
                $option_name = 'flosc_companion_' . $key;
                $defaults[$key] = $flow_manager->get_setting(
                    $option_name,
                    'companion',
                    $key,
                    $default,
                    $flow_id
                );
            }
        }

        // Type coercion for booleans
        $defaults['enabled']            = filter_var($defaults['enabled'], FILTER_VALIDATE_BOOLEAN);
        $defaults['show_for_visitors']  = filter_var($defaults['show_for_visitors'], FILTER_VALIDATE_BOOLEAN);

        if ($flow_id === null) {
            $this->cached_settings = $defaults;
        }

        return $defaults;
    }

    /**
     * Check if companion mode is active for the current request
     * 
     * Companion should load when:
     * 1. Companion mode is enabled in admin settings
     * 2. We are NOT on the full FLOSC app route (that has its own UI)
     * 3. We are on a frontend page (not admin)
     * 4. User meets visibility requirements (logged in, or show_for_visitors is on)
     * 
     * @return bool
     */
    public function should_load() {
        // Never load on admin pages
        if (is_admin()) {
            return false;
        }

        // Outer chrome never on FLOSC app routes (iframe targets only app routes).
        if (function_exists('flosc') && flosc()->is_flosc_request()) {
            return false;
        }

        $settings = $this->get_settings();

        // Must be enabled
        if (!$settings['enabled']) {
            return false;
        }

        // Content display mode must include companion
        $mode = $settings['content_display_mode'];
        if ($mode !== 'companion' && $mode !== 'both') {
            return false;
        }

        // Visibility check
        if (!$settings['show_for_visitors'] && !is_user_logged_in()) {
            return false;
        }

        return true;
    }

    // ──────────────────────────────────────────────────────────────
    // Asset Enqueueing
    // ──────────────────────────────────────────────────────────────

    /**
     * Conditionally enqueue companion assets
     * 
     * Hooked to wp_enqueue_scripts. Only loads companion JS/CSS when
     * should_load() returns true. Does NOT touch theme styles — the
     * companion lives alongside the WP theme, not instead of it.
     */
    public function maybe_enqueue_assets() {
        if (!$this->should_load()) {
            return;
        }

        $css_path = FLOSC_PLUGIN_DIR . 'assets/css/flosc-companion.css';
        $js_path  = FLOSC_PLUGIN_DIR . 'assets/js/flosc-companion.js';

        // Companion CSS — standalone, no dependencies on FLOSC layout/theme
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'flosc-companion',
                FLOSC_PLUGIN_URL . 'assets/css/flosc-companion.css',
                [],
                defined('FLOSC_VERSION') ? FLOSC_VERSION : '8.0.0'
            );
        }

        // Companion JS — standalone, no dependencies on flosc-app.js
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'flosc-companion',
                FLOSC_PLUGIN_URL . 'assets/js/flosc-companion.js',
                [],
                defined('FLOSC_VERSION') ? FLOSC_VERSION : '8.0.0',
                true // footer
            );
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Widget Rendering
    // ──────────────────────────────────────────────────────────────

    /**
     * Conditionally render the widget HTML + inline config
     * 
     * Hooked to wp_footer. Injects:
     * 1. FLOSC_COMPANION JS object (REST URL, nonce, user data, page context)
     * 2. Widget container HTML (the JS will populate it)
     * 
     * Outputs minimal HTML — the JS module builds the UI.
     */
    public function maybe_render_widget() {
        if (!$this->should_load()) {
            return;
        }

        $settings     = $this->get_settings();
        $page_context = $this->detect_page_context();
        $user_data    = $this->get_companion_user_data();

        // §12: Attach the accent override + config to the enqueued flosc-companion handles
        // (wp_add_inline_style/script) rather than echoing raw <style>/<script> tags. This runs
        // on wp_footer before wp_print_footer_scripts, so the data prints with — and just before —
        // flosc-companion.js. WordPress prints the late inline style via print_late_styles().
        if (!empty($settings['accent_color'])) {
            $accent = sanitize_hex_color($settings['accent_color']);
            if ($accent) {
                wp_add_inline_style('flosc-companion', sprintf(
                    '.flosc-companion-widget{--companion-accent:%s;--companion-accent-hover:%s;}',
                    esc_attr($accent),
                    esc_attr($this->adjust_brightness($accent, -12))
                ));
            }
        }

        wp_add_inline_script('flosc-companion', 'window.FLOSC_COMPANION = ' . wp_json_encode([
            'restUrl'  => rest_url('flosc/v1/'),
            'nonce'    => wp_create_nonce('wp_rest'),
            'user'     => $user_data,
            'page'     => $page_context,
            'settings' => [
                'position'  => $settings['position'],
                'greeting'  => $settings['greeting'],
                'mode'      => $settings['content_display_mode'],
            ],
        ]) . ';', 'before');

        ?>
        <!-- FLOSC Companion Widget v1.6.0 -->
        <div id="flosc-companion-root"
             class="flosc-companion-widget flosc-companion-<?php echo esc_attr($settings['position']); ?>"
             aria-label="Learning Companion"
             role="complementary">
            <!-- flosc-companion.js builds the UI here -->
        </div>
        <?php
    }

    // ──────────────────────────────────────────────────────────────
    // Page Context Detection
    // ──────────────────────────────────────────────────────────────

    /**
     * Detect what the user is currently viewing
     * 
     * This is the "context-aware" piece — the companion knows what
     * lesson/page the member is reading and can offer relevant help.
     * 
     * @return array {
     *     @type string      $type       'lesson'|'lesson_archive'|'page'|'home'|'other'
     *     @type int|null    $post_id    Current post ID if applicable
     *     @type string|null $title      Current post/page title
     *     @type string|null $category   Lesson category slug if on a lesson
     *     @type array       $tags       Post tags (for quiz topic matching)
     *     @type string|null $url        Current page URL
     * }
     */
    public function detect_page_context() {
        $context = [
            'type'     => 'other',
            'postId'   => null,
            'title'    => null,
            'category' => null,
            'tags'     => [],
            'url'      => $this->get_current_url(),
        ];

        if (is_front_page() || is_home()) {
            $context['type'] = 'home';
            return $context;
        }

        // Check if we're on a lesson post
        $lesson_category = get_option('flosc_lessons_category', '');

        if (is_singular('post') || is_singular('page')) {
            $post = get_queried_object();
            if (!$post) {
                return $context;
            }

            $context['postId'] = $post->ID;
            $context['title']  = $post->post_title;
            $context['tags']   = wp_get_post_tags($post->ID, ['fields' => 'slugs']);

            // Determine if this post is in the lessons category
            if (!empty($lesson_category) && $this->post_is_lesson($post->ID, $lesson_category)) {
                $context['type']     = 'lesson';
                $context['category'] = $lesson_category;
            } else {
                $context['type'] = 'page';
            }

            return $context;
        }

        // Check if on a lesson category archive
        if (is_category() && !empty($lesson_category)) {
            $cat = get_queried_object();
            if ($cat) {
                $cat_match = is_numeric($lesson_category)
                    ? ($cat->term_id === intval($lesson_category))
                    : ($cat->slug === sanitize_title($lesson_category));

                if ($cat_match) {
                    $context['type']     = 'lesson_archive';
                    $context['category'] = $lesson_category;
                    $context['title']    = $cat->name;
                }
            }
        }

        return $context;
    }

    /**
     * Check if a post belongs to the configured lessons category
     * 
     * @param  int    $post_id
     * @param  string $lesson_category  Category slug or ID
     * @return bool
     */
    private function post_is_lesson($post_id, $lesson_category) {
        if (is_numeric($lesson_category)) {
            return has_category(intval($lesson_category), $post_id);
        }
        return has_category(sanitize_title($lesson_category), $post_id);
    }

    // ──────────────────────────────────────────────────────────────
    // User Data
    // ──────────────────────────────────────────────────────────────

    /**
     * Get minimal user data for the companion
     * 
     * Lighter than FLOSC_USER — only what the companion needs.
     * No IVR state, no quiz data, no offer history.
     * 
     * @return array
     */
    private function get_companion_user_data() {
        if (!is_user_logged_in()) {
            return [
                'loggedIn'    => false,
                'displayName' => 'Visitor',
                'isMember'    => false,
                'hasAccess'   => false,
            ];
        }

        $user    = wp_get_current_user();
        $user_id = $user->ID;

        // Check member status via the existing member access system
        $is_member  = false;
        $has_access = false;

        if (class_exists('FLOSC_Member_Access')) {
            $member_access = FLOSC_Member_Access::instance();
            $is_member     = $member_access->is_member($user_id);
        }

        // Check lesson access via lesson manager
        if (class_exists('FLOSC_Lesson_Manager') && is_singular()) {
            $post = get_queried_object();
            if ($post) {
                $lesson_manager = FLOSC_Lesson_Manager::instance();
                $has_access     = $lesson_manager->user_can_access($user_id, $post->ID);
            }
        }

        return [
            'loggedIn'    => true,
            'userId'      => $user_id,
            'displayName' => $user->display_name ?: $user->user_login,
            'avatar'      => get_avatar_url($user_id, ['size' => 48]),
            'isMember'    => $is_member,
            'hasAccess'   => $has_access,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Utilities
    // ──────────────────────────────────────────────────────────────

    /**
     * Get FlowManager instance safely
     * @return FLOSC_Flow_Manager|null
     */
    private function get_flow_manager() {
        if (!class_exists('FLOSC_Flow_Manager')) {
            return null;
        }
        return FLOSC_Flow_Manager::instance();
    }

    /**
     * Get current URL cleanly
     * @return string
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        $host = 'localhost';
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']));
        }
        $request_uri = '/';
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI']));
        }
        return $protocol . $host . $request_uri;
    }

    /**
     * Adjust hex color brightness
     * 
     * @param  string $hex     Hex color (#RRGGBB)
     * @param  int    $percent Negative = darker, positive = lighter
     * @return string          Adjusted hex color
     */
    private function adjust_brightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = max(0, min(255, hexdec(substr($hex, 0, 2)) + (255 * $percent / 100)));
        $g = max(0, min(255, hexdec(substr($hex, 2, 2)) + (255 * $percent / 100)));
        $b = max(0, min(255, hexdec(substr($hex, 4, 2)) + (255 * $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
