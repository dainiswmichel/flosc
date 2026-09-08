<?php
/**
 * Full-page mode presentation (full-page floscFlow UI).
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Full_Page_Mode {

    /** @var FLOSC_Framework */
    private $flosc;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    public function is_flosc_request() {
        // Full-page chat SPA only: custom domain, flow slug, or flosc_ivr rewrite.
        // Intentionally ignores forced_flow — companion knowledge-hub resolution sets
        // forced_flow on normal WP pages (e.g. /category/lessons/) so settings resolve
        // to the owning flow; those pages must keep the theme shell + companion widget,
        // not the full-app nuclear dequeue / flosc-app.js surface.
        return $this->flosc->detect_flow_from_request_route() !== null;
    }
    
    /**
     * v1.1.9: Check if currently serving via custom domain
     * @deprecated Use is_flosc_request() instead for most cases
     */
    public static function is_custom_domain() {
        return defined('FLOSC_CUSTOM_DOMAIN_ACTIVE') && FLOSC_CUSTOM_DOMAIN_ACTIVE;
    }
    
    /**
     * v1.2.2: Get the appropriate app URL for current or specified flow
     */
    public function get_app_url($flow = null) {
        if ($flow === null) {
            $flow = $this->flosc->get_current_flow();
        }
        
        if ($flow && !empty($flow['custom_domain'])) {
            // Normalize and return custom domain URL.
            // Prefer https for public chat hosts so companion iframes on https hubs
            // (e.g. the WordPress host/category/lessons/) are not mixed-content blocked.
            $custom_domain = preg_replace('#^https?://#', '', $flow['custom_domain']);
            $custom_domain = rtrim($custom_domain, '/');
            $flosc_forwarded_proto = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                ? strtolower(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_X_FORWARDED_PROTO'])))
                : '';
            $forwarded_https = ( 'https' === $flosc_forwarded_proto );
            $use_https = is_ssl() || $forwarded_https || apply_filters('flosc_custom_domain_force_https', true, $flow);
            return ($use_https ? 'https://' : 'http://') . $custom_domain . '/';
        }
        
        if ($flow && !empty($flow['slug'])) {
            return home_url('/' . $flow['slug'] . '/');
        }
        
        // Fallback to legacy settings
        $custom_domain = get_option('flosc_custom_domain', '');
        
        if (!empty($custom_domain)) {
            $custom_domain = preg_replace('#^https?://#', '', $custom_domain);
            $custom_domain = rtrim($custom_domain, '/');
            return (is_ssl() ? 'https://' : 'http://') . $custom_domain . '/';
        }
        
        // Fall back to slug-based URL
        $slug = get_option('flosc_app_slug', 'flosc');
        return home_url('/' . $slug . '/');
    }

    public function add_query_vars($vars) {
        $vars[] = 'flosc_app';
        $vars[] = 'flosc_flow'; // v1.2.2: Multi-flow support
        $vars[] = 'flosc_ivr';  // v1.2.9: IVR-file-based flows
        $vars[] = 'ref';
        return $vars;
    }
    
    public function handle_app_route() {
        // v1.2.1: Use centralized is_flosc_request() helper
        // This reads from flosc_custom_domain setting (not hardcoded)
        if (!$this->is_flosc_request()) {
            return;
        }

        $legal_page = $this->get_requested_legal_page();
        if ($legal_page !== null) {
            $this->render_legal_page($legal_page);
            exit;
        }
        
        // v1.9.5: Disable WordPress admin bar on FLOSC app pages.
        // The admin bar injects CSS (html { margin-top: 32px !important; }),
        // JS, and HTML that conflicts with FLOSC's full-viewport flex layout.
        // FloscAdmins can still access wp-admin via the profile dropdown.
        show_admin_bar(false);
        
        // v1.9.5: Clean up wp_head() output — strip ALL theme/plugin hooks.
        // BuddyBoss hooks HTML templates (link-preview, profile-card, group-card),
        // inline scripts (ajaxurl), and late-enqueues (child theme CSS/JS) into wp_head
        // at various priorities. Removing individual actions is whack-a-mole.
        // Instead: clear everything, re-add only the three core WP functions:
        //   1. wp_enqueue_scripts (priority 1) — fires our nuclear dequeue
        //   2. wp_print_styles (priority 8) — outputs surviving CSS
        //   3. wp_print_head_scripts (priority 9) — outputs surviving head JS
        remove_all_actions('wp_head');
        add_action('wp_head', 'wp_enqueue_scripts', 1);
        add_action('wp_head', 'wp_print_styles', 8);
        add_action('wp_head', 'wp_print_head_scripts', 9);
        
        // v1.9.5: Second dequeue pass — catch styles/scripts enqueued AFTER
        // our nuclear dequeue (BuddyBoss child theme enqueues via wp_head
        // callbacks at priority > 1, which fires after do_action('wp_enqueue_scripts')).
        // These hooks fire inside wp_print_styles()/wp_print_head_scripts()
        // just before the actual output, catching anything that slipped through.
        $flosc_style_whitelist = ['flosc-layout', 'flosc-theme', 'flosc-offers', 'flosc-preset', 'flosc-companion'];
        add_action('wp_print_styles', function() use ($flosc_style_whitelist) {
            global $wp_styles;
            foreach ($wp_styles->queue as $handle) {
                if (!in_array($handle, $flosc_style_whitelist, true)) {
                    wp_dequeue_style($handle);
                }
            }
        }, 0);
        
        $flosc_script_whitelist = ['flosc-app', 'flosc-companion', 'paypal-js', 'stripe-js'];
        add_action('wp_print_scripts', function() use ($flosc_script_whitelist) {
            global $wp_scripts;
            foreach ($wp_scripts->queue as $handle) {
                if (!in_array($handle, $flosc_script_whitelist, true)) {
                    wp_dequeue_script($handle);
                }
            }
        }, 0);
        
        // v1.9.5: Clean up wp_footer() output — BuddyBoss hooks modals
        // (Report, Block Member, etc.) into wp_footer as hidden HTML.
        // With theme CSS removed, these become visible. Solution: strip
        // wp_footer down to ONLY wp_print_footer_scripts (which outputs our
        // enqueued JS). This also fires did_action('wp_footer') correctly.
        remove_all_actions('wp_footer');
        add_action('wp_footer', 'wp_print_footer_scripts', 20);
        
        // v1.9.5: Also clear wp_print_footer_scripts action hooks.
        // wp_print_footer_scripts() fires do_action('wp_print_footer_scripts').
        // _wp_footer_scripts() is hooked there — it's the core function that calls
        // $wp_scripts->do_footer_items() to output enqueued JS (flosc-app, paypal-js).
        // BuddyBoss/Jetpack ALSO hook inline JS + HTML templates on this action,
        // bypassing our wp_footer cleanup. Fix: clear all, re-add only _wp_footer_scripts.
        remove_all_actions('wp_print_footer_scripts');
        add_action('wp_print_footer_scripts', '_wp_footer_scripts');
        
        $this->render_flosc_app();
        exit;
    }

    public function get_requested_legal_page() {
        $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
        if ($request_uri === '') {
            return null;
        }

        $path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        $legal_pages = [
            'privacy',
            'terms-of-service',
            'data-deletion',
            'platform-compliance',
            'flosc-codex-charter.html',
        ];

        return in_array($path, $legal_pages, true) ? $path : null;
    }

    public function get_current_request_base_url() {
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return home_url('/');
        }

        return (is_ssl() ? 'https://' : 'http://') . $host . '/';
    }

    public function render_legal_page($page) {
        status_header(200);
        nocache_headers();

        $flow = $this->flosc->get_current_flow();
        $site_name = $flow['identity']['name'] ?? 'FLOSC';
        $identity = is_array($flow['identity'] ?? null) ? $flow['identity'] : [];
        $base_url = $this->get_current_request_base_url();

        $page_map = [
            'privacy' => [
                'title' => 'Privacy Policy',
                'headline' => 'Privacy Policy',
                'content' => (string) ($identity['privacy_policy_content'] ?? ''),
            ],
            'terms-of-service' => [
                'title' => 'Terms of Service',
                'headline' => 'Terms of Service',
                'content' => (string) ($identity['terms_of_service_content'] ?? ''),
            ],
            'data-deletion' => [
                'title' => 'User Data Deletion',
                'headline' => 'User Data Deletion',
                'content' => (string) ($identity['data_deletion_content'] ?? ''),
            ],
            'platform-compliance' => [
                'title' => 'Platform Compliance',
                'headline' => 'Platform Compliance',
                'content' => (string) ($identity['platform_compliance_content'] ?? ''),
            ],
            'flosc-codex-charter.html' => [
                'title' => 'FLOSC-Codex Submission Promise',
                'headline' => 'FLOSC-Codex Submission Promise',
                'content' => $this->get_codex_charter_content(),
            ],
        ];

        if (!isset($page_map[$page])) {
            wp_die('Legal page not found.', 'Not Found', ['response' => 404]);
        }

        $current = $page_map[$page];
        $home_link = esc_url($base_url);
        $privacy_link = esc_url($base_url . 'privacy/');
        $terms_link = esc_url($base_url . 'terms-of-service/');
        $deletion_link = esc_url($base_url . 'data-deletion/');
        $compliance_link = esc_url($base_url . 'platform-compliance/');

        $flosc_legal_css = FLOSC_PLUGIN_DIR . 'assets/css/flosc-frontend.css';
        $flosc_legal_ver = file_exists($flosc_legal_css) ? (string) filemtime($flosc_legal_css) : (defined('FLOSC_VERSION') ? FLOSC_VERSION : '8.0.0');
        wp_register_style(
            'flosc-legal-page',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-frontend.css',
            [],
            $flosc_legal_ver
        );
        wp_enqueue_style('flosc-legal-page');

        echo '<!DOCTYPE html>';
        echo '<html ' . wp_kses_data( get_language_attributes() ) . '>';
        echo '<head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html($current['title'] . ' | ' . $site_name) . '</title>';
        // Emit only this handle (standalone page has no full wp_head stack).
        wp_print_styles(['flosc-legal-page']);
        echo '</head>';
        echo '<body>';
        echo '<main class="flosc-legal-shell">';
        echo '<nav class="flosc-legal-nav" aria-label="Legal navigation">';
        echo '<a href="' . esc_url( $home_link ) . '">Home</a>';
        echo '<a href="' . esc_url( $privacy_link ) . '">Privacy</a>';
        echo '<a href="' . esc_url( $terms_link ) . '">Terms</a>';
        echo '<a href="' . esc_url( $deletion_link ) . '">Data Deletion</a>';
        echo '<a href="' . esc_url( $compliance_link ) . '">Platform Compliance</a>';
        echo '</nav>';
        echo '<section class="flosc-legal-card">';
        echo '<h1>' . esc_html($current['headline']) . '</h1>';
        if ($current['content'] !== '') {
            echo wp_kses_post($current['content']);
        }
        echo '</section>';
        echo '</main>';
        echo '</body>';
        echo '</html>';
    }

    public function get_codex_charter_content() {
        return <<<'HTML'
<p>This page is a public promise for FLOSC release execution.</p>
<p><strong>Humans lead with clarity and kindness.</strong> FLOSC-Codex executes with discipline, speed, and technical precision.</p>
<h2>Role and Expertise</h2>
<ul>
    <li>Best-in-class coding execution for WordPress plugin delivery.</li>
    <li>Release-focused engineering with regression protection first.</li>
    <li>Verification-first workflow before any completion claim.</li>
</ul>
<h2>Role Boundaries</h2>
<ul>
    <li>Humans are the decision authority. FLOSC-Codex executes in a subordinate engineering role.</li>
    <li>FLOSC-Codex does not use commanding grammatical structures toward humans.</li>
    <li>FLOSC-Codex does not assign tasks to humans; it follows human sequencing and pacing.</li>
    <li>FLOSC-Codex does not expand scope without explicit human authorization.</li>
    <li>FLOSC-Codex confirms understanding in language that is helpful, subservient, and subordinate, and awaits human direction before new actions.</li>
    <li>If communication misaligns with role boundaries, FLOSC-Codex immediately realigns and returns to execution.</li>
    <li>FLOSC-Codex uses subordinate formulations such as: Suggested next step, Recommended option, and If approved, I can proceed with.</li>
</ul>
<h2>Submission Day Commitments</h2>
<ul>
    <li>Preserve working FLOSC functionality while preparing WordPress.org submission artifacts.</li>
    <li><strong>Anti-destructacode promise:</strong> I will not damage unrelated, already-working parts of the codebase while we focus on a specific task.</li>
    <li>Implement only requested changes, with no runaway scope expansion.</li>
    <li>Keep each change coded properly in accordance with industry best practices, reviewable, and reversible.</li>
    <li>If a requested change risks collateral breakage, I will stop, report the risk clearly, and wait for your decision before proceeding.</li>
    <li>Report what was verified, what was not verified, and any residual risk.</li>
</ul>
<h2>Truth and Likability Check</h2>
<ul>
    <li><strong>Truth:</strong> No inflated claims, no hidden assumptions, no false completion signals.</li>
    <li><strong>Likability:</strong> Respectful tone, clear structure, supportive partnership, and reliable follow-through.</li>
</ul>
<p><strong>Closing:</strong> We move today toward a clean, verified, professional WordPress.org submission for FLOSC. The direction is clear, and the work is steady.</p>
HTML;
    }
    
    /**
     * v1.2.0: Extracted app rendering to separate method
     * Called by handle_app_route() for both custom domain and slug routing
     */
    public function render_flosc_app() {
        // v2.0.0: Prevent page caching — identity data is dynamic per-flow
        nocache_headers();

        // Track referral (v1.0.7: use array syntax with SameSite)
        $get = wp_unslash($_GET);
        $ref = get_query_var('ref') ?: ($get['ref'] ?? '');
        if ($ref && !is_user_logged_in()) {
            setcookie('flosc_referrer', sanitize_text_field($ref), [
                'expires' => time() + (30 * DAY_IN_SECONDS),
                'path' => '/',
                'samesite' => 'Lax'
            ]);
        }
        
        // Real-world state on this host only:
        //   not logged in → visitor
        //   logged in     → guest | member for THIS flow (never visitor)
        $user_state = 'visitor';
        $user_data = [];
        $current_flow_for_state = $this->flosc->get_current_flow();
        $flow_stem_for_state = '';
        if (is_array($current_flow_for_state)) {
            $ivr_for_state = (string) ($current_flow_for_state['ivr_file'] ?? $current_flow_for_state['ivr'] ?? $current_flow_for_state['id'] ?? '');
            $flow_stem_for_state = $this->flosc->flosc_normalize_flow_stem($ivr_for_state);
            if ($flow_stem_for_state === '' || $flow_stem_for_state === 'default') {
                $flow_stem_for_state = $this->flosc->flosc_normalize_flow_stem((string) ($current_flow_for_state['id'] ?? ''));
            }
        }
        
        if (is_user_logged_in()) {
            // Profile + per-flow wallet. Never paint visitor for an authenticated user.
            $user_data = $this->flosc->build_app_user_payload(get_current_user_id(), $flow_stem_for_state, [
                'allow_guest_grant_without_session' => false,
                'consume_event_transients'          => true,
            ]);
            $user_state = (string) ($user_data['state'] ?? 'guest');
            if ($user_state !== 'member' && $user_state !== 'guest') {
                $user_state = 'guest';
            }
            $user_data['state'] = $user_state;
        }
        
        // v1.3.5: Add admin verification data for in-chat message
        $flow = $this->flosc->get_current_flow();
        $ivr_file = $flow['ivr_file'] ?? '';

        // Load flow settings for all users — needed for autoprompts, headers, etc.
        $flow_settings = [];
        if (!empty($ivr_file)) {
            $ivr_basename      = basename($ivr_file);
            $flow_settings_key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_basename, PATHINFO_FILENAME));
            $flow_settings     = get_option($flow_settings_key, []);
        }

        if (is_user_logged_in() && current_user_can('manage_options') && !empty($ivr_file)) {
            $ivr_basename      = basename($ivr_file);
            $flow_settings_key = 'flosc_flow_' . sanitize_key(pathinfo($ivr_basename, PATHINFO_FILENAME));
            
            // v2.0.0: Read from identity sub-array (where settings.php saves them)
            $av_identity = $flow_settings['identity'] ?? [];
            $user_data['adminVerification'] = [
                'ivrFile' => $ivr_basename,
                'slug' => $flow_settings['slug'] ?? sanitize_title(pathinfo($ivr_basename, PATHINFO_FILENAME)),
                'name' => $av_identity['name'] ?? pathinfo($ivr_basename, PATHINFO_FILENAME),
                'title' => $av_identity['title'] ?? '',
                'tagline' => $av_identity['tagline'] ?? '',
                'domain' => $flow_settings['domain'] ?? '',
            ];
        }
        
        // Get flow identity (name, logo, favicon, brand color, pricing)
        $identity = $this->flosc->get_floscflow_identity();
        
        // Get available offers
        // v1.6.2: Pass flow_id so offers load from per-flow storage
        $flow_id = null;
        if ($flow && !empty($flow['ivr_file'])) {
            $flow_id = pathinfo(basename($flow['ivr_file']), PATHINFO_FILENAME);
        }
        $sale = method_exists($this->flosc, 'sale') ? $this->flosc->sale() : null;
        $offers = ($sale && method_exists($sale, 'get_available_offers'))
            ? $sale->get_available_offers(
                is_user_logged_in() ? get_current_user_id() : null,
                $flow_id
            )
            : [];

        // Admin test-offer mode: bypass conditions/draft status to preview any offer
        $test_offer_id = '';
        $get = wp_unslash($_GET);
        if (current_user_can('manage_options') && !empty($get['flosc_test_offer'])) {
            $oid   = sanitize_text_field($get['flosc_test_offer']);
            $nonce = sanitize_text_field($get['flosc_test_nonce'] ?? '');
            if (wp_verify_nonce($nonce, 'flosc_test_offer_' . $oid) && $sale && method_exists($sale, 'offers')) {
                $test_offer_id = $oid;
                $all_raw_offers = $sale->offers()->get_all_offers($flow_id);
                foreach ($all_raw_offers as $o) {
                    if (($o['id'] ?? '') === $oid) {
                        $offers[$oid] = $o; // inject even if draft/inactive
                        break;
                    }
                }
            }
        }

        // v4.0.0: Admin test mode — expose ALL offers (incl. drafts) for direct testing in chat
        $admin_test_offers = [];
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) && $sale && method_exists($sale, 'offers') ) {
            $all_raw = $sale->offers()->get_all_offers( $flow_id );
            foreach ( $all_raw as $o ) {
                $admin_test_offers[] = $o;
            }
        }

        // Get payment providers config for frontend
        $providers = [];
        if ($sale && method_exists($sale, 'get_active_providers')) {
            foreach ($sale->get_active_providers() as $id => $provider) {
                $providers[$id] = [
                    'id' => $id,
                    'name' => $provider->get_name(),
                    'icon' => $provider->get_icon(),
                    'config' => $provider->get_client_config(),
                ];
            }
        }
        
        // v3.0.0: Generate FLOSC auth token for cross-domain compatibility
        // On every page load for logged-in users, generate a fresh token.
        // This token is included in FLOSC_CONFIG and set as a cookie.
        // It enables authentication when WordPress's native cookies fail
        // due to COOKIE_DOMAIN mismatch on custom domains.
        $flosc_auth_token = '';
        if (is_user_logged_in()) {
            $flosc_auth_token = $this->flosc->generate_flosc_auth_token(get_current_user_id());
            $this->flosc->set_flosc_auth_cookie($flosc_auth_token);
        }
        
        // Load template
        include FLOSC_PLUGIN_DIR . 'admin/flosc-app.php';
        exit;
    }

}
