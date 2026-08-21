<?php
/**
 * Companion mode presentation (widget on normal WP pages).
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Companion_Mode {

    /** @var FLOSC_Framework */
    private $flosc;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    /**
     * v1.6.1: Enqueue companion widget on non-app WordPress pages.
     * Only loads if companion mode is enabled for the current flow.
     * v1.6.3: Fixed to read from flat per-flow settings (matching admin save pattern)
     * v8.0.0: Knowledge hubs — resolve flow by handoff param, hub companion URL, or lessons category.
     */
    public function enqueue_companion() {
        // Outer chrome is for normal WP host pages only.
        // FLOSC app routes (is_flosc_request) never load outer chrome — by construction
        // the companion iframe may only target an app route, so nesting cannot occur.
        if ($this->flosc->is_flosc_request()) {
            return;
        }

        // Public handoff flag from full-page dock (not a form POST — no nonce applies).
        $handoff_request = ( '1' === sanitize_text_field( (string) filter_input( INPUT_GET, 'flosc_companion_handoff' ) ) );

        // Cross-domain knowledge hub: pick the owning flow before reading settings.
        $this->resolve_companion_flow_context($handoff_request);

        $defaults = $this->get_companion_defaults();
        $numeric_limits = $this->get_companion_numeric_limits();

        // Read from per-flow settings (flat keys, not overrides)
        $enabled = filter_var($this->flosc->get_setting('companion_enabled', $defaults['enabled']), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }

        $mode = (string) $this->flosc->get_setting('companion_content_display_mode', $defaults['mode']);
        if (!in_array($mode, $this->get_companion_allowed_modes(), true)) {
            $mode = $defaults['mode'];
        }

        // Mode gate: widget only loads in modes explicitly mapped for widget rendering.
        if (!in_array($mode, $this->get_companion_widget_modes(), true)) {
            return;
        }

        // Visitor gate: when disabled, only logged-in users should see companion.
        // Handoff from full-page chat always allowed — dock may land cross-domain
        // (e.g. the flow domain → the WordPress host knowledge hub) without a shared login cookie.
        // Sales default is on (see get_companion_defaults). Stored 0/1 after Style save;
        // legacy empty string falls through get_setting to the default (on).
        $show_for_visitors = filter_var(
            $this->flosc->get_setting('companion_show_for_visitors', $defaults['show_for_visitors']),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$handoff_request && !$show_for_visitors && !is_user_logged_in()) {
            return;
        }

        // Optional route targeting: exclude rules override include rules.
        // Handoff always shows the widget so dock navigation is not blocked by include/exclude.
        if (!$handoff_request && !$this->should_show_companion_for_current_request()) {
            return;
        }

        // Iframe target must be a FLOSC app route (slug or custom domain of an active flow).
        // Non-app URLs (hub, home, posts) are invalid — no widget, no nest path.
        $app_url = $this->get_companion_chat_app_url();
        if (empty($app_url) || !$this->is_flosc_app_route_url($app_url)) {
            return;
        }

        $current_url = $this->get_current_frontend_url();

        // Keep defaults aligned with companion admin UI and filterable for extensions.
        $accent = sanitize_hex_color((string) $this->flosc->get_setting('companion_accent_color', $defaults['accent_color']));
        $title  = sanitize_text_field((string) $this->flosc->get_setting('companion_greeting', $defaults['greeting']));
        $subtitle = sanitize_text_field((string) $this->flosc->get_setting('companion_subtitle', $defaults['subtitle']));
        $show_header_title = filter_var($this->flosc->get_setting('companion_show_header_title', $defaults['show_header_title']), FILTER_VALIDATE_BOOLEAN);
        $show_header_subtitle = filter_var($this->flosc->get_setting('companion_show_header_subtitle', $defaults['show_header_subtitle']), FILTER_VALIDATE_BOOLEAN);
        $show_open_fullpage = filter_var($this->flosc->get_setting('companion_show_open_fullpage', $defaults['show_open_fullpage']), FILTER_VALIDATE_BOOLEAN);
        $position = (string) $this->flosc->get_setting('companion_position', $defaults['position']);
        if (!in_array($position, $this->get_companion_allowed_positions(), true)) {
            $position = $defaults['position'];
        }

        // Brand / product identity — header icon + assistant name (not hard-coded FLOSC/emoji).
        $identity = $this->flosc->get_floscflow_identity();
        $product_name = function_exists( 'flosc_visitor_assistant_name' )
            ? sanitize_text_field( flosc_visitor_assistant_name() )
            : sanitize_text_field((string) ($identity['name'] ?? ''));
        if ($product_name === '') {
            $product_name = sanitize_text_field((string) flosc_get_setting('product_name', 'FLOSC'));
        }
        if ($title === '') {
            $title = $product_name !== ''
                ? sprintf(
                    /* translators: %s: product / flow name */
                    __('%s Companion', 'flosc'),
                    $product_name
                )
                : (string) $defaults['greeting'];
        }
        // Header icon: optional companion override → this flow’s Chat Logo → bundled default only if none.
        $header_icon_url = esc_url_raw( (string) $this->flosc->get_setting( 'companion_header_icon_url', '' ) );
        if ( $header_icon_url === '' ) {
            // Prefer identity from resolved current flow (hub + app).
            if ( ! empty( $identity['chatlogo_url'] ) ) {
                $header_icon_url = esc_url_raw( (string) $identity['chatlogo_url'] );
            }
        }
        if ( $header_icon_url === '' && function_exists( 'flosc_resolve_chatlogo_url' ) ) {
            // Pass full flow array so flat/nested chatlogo_url both work.
            $flow_for_logo = $this->flosc->get_current_flow();
            $header_icon_url = flosc_resolve_chatlogo_url( is_array( $flow_for_logo ) ? $flow_for_logo : null, true );
        } elseif ( $header_icon_url === '' && function_exists( 'flosc_get_chatlogo_url' ) ) {
            $header_icon_url = esc_url_raw( (string) flosc_get_chatlogo_url() );
        }

        $panel_width = absint($this->flosc->get_setting('companion_panel_width', $defaults['panel_width']));
        $panel_height = absint($this->flosc->get_setting('companion_panel_height', $defaults['panel_height']));
        $panel_width = max($numeric_limits['panel_width_min'], min($numeric_limits['panel_width_max'], $panel_width));
        $panel_height = max($numeric_limits['panel_height_min'], min($numeric_limits['panel_height_max'], $panel_height));
        $launcher_size = absint($this->flosc->get_setting('companion_launcher_size', $defaults['launcher_size']));
        $launcher_size = max($numeric_limits['launcher_size_min'], min($numeric_limits['launcher_size_max'], $launcher_size));
        $launcher_icon = sanitize_key((string) $this->flosc->get_setting('companion_launcher_icon', $defaults['launcher_icon']));
        $launcher_svgs = $this->get_companion_launcher_svg_paths();
        // product_logo = use Chat Logo / header icon image for FAB (not a path SVG).
        $launcher_uses_product_logo = ($launcher_icon === 'product_logo');
        if (!$launcher_uses_product_logo && !isset($launcher_svgs[$launcher_icon])) {
            $launcher_icon = $defaults['launcher_icon'];
        }
        $launcher_svg_path = $launcher_uses_product_logo
            ? ''
            : (string) ($launcher_svgs[$launcher_icon] ?? $launcher_svgs['chat']);
        $launcher_icon_url = $launcher_uses_product_logo ? $header_icon_url : '';

        $mobile_behavior = (string) $this->flosc->get_setting('companion_mobile_behavior', $defaults['mobile_behavior']);
        if (!in_array($mobile_behavior, $this->get_companion_mobile_behaviors(), true)) {
            $mobile_behavior = $defaults['mobile_behavior'];
        }

        $pass_page_context = filter_var($this->flosc->get_setting('companion_pass_page_context', $defaults['pass_page_context']), FILTER_VALIDATE_BOOLEAN);
        $context_scope = (string) $this->flosc->get_setting('companion_context_scope', $defaults['context_scope']);
        if (!in_array($context_scope, $this->get_companion_context_scopes(), true)) {
            $context_scope = $defaults['context_scope'];
        }

        $auto_open_enabled = filter_var($this->flosc->get_setting('companion_auto_open_enabled', $defaults['auto_open_enabled']), FILTER_VALIDATE_BOOLEAN);
        $auto_open_delay_ms = absint($this->flosc->get_setting('companion_auto_open_delay_ms', $defaults['auto_open_delay_ms']));
        $auto_open_delay_ms = max($numeric_limits['auto_open_delay_min_ms'], min($numeric_limits['auto_open_delay_max_ms'], $auto_open_delay_ms));
        $auto_open_once_per_session = filter_var(
            $this->flosc->get_setting('companion_auto_open_once_per_session', $defaults['auto_open_once_per_session']),
            FILTER_VALIDATE_BOOLEAN
        );
        $launch_on_exit_intent = filter_var(
            $this->flosc->get_setting('companion_launch_on_exit_intent', $defaults['launch_on_exit_intent']),
            FILTER_VALIDATE_BOOLEAN
        );
        $launch_on_scroll_threshold = filter_var(
            $this->flosc->get_setting('companion_launch_on_scroll_threshold', $defaults['launch_on_scroll_threshold']),
            FILTER_VALIDATE_BOOLEAN
        );
        $launch_on_scroll_percent = absint($this->flosc->get_setting('companion_launch_on_scroll_percent', $defaults['launch_on_scroll_percent']));
        $launch_on_scroll_percent = max($numeric_limits['scroll_percent_min'], min($numeric_limits['scroll_percent_max'], $launch_on_scroll_percent));
        $trigger_desktop_only = filter_var(
            $this->flosc->get_setting('companion_trigger_desktop_only', $defaults['trigger_desktop_only']),
            FILTER_VALIDATE_BOOLEAN
        );
        $trigger_min_page_time_ms = absint($this->flosc->get_setting('companion_trigger_min_page_time_ms', $defaults['trigger_min_page_time_ms']));
        $trigger_min_page_time_ms = max($numeric_limits['trigger_min_page_time_min_ms'], min($numeric_limits['trigger_min_page_time_max_ms'], $trigger_min_page_time_ms));
        $trigger_suppress_on_auth_checkout = filter_var(
            $this->flosc->get_setting('companion_trigger_suppress_on_auth_checkout', $defaults['trigger_suppress_on_auth_checkout']),
            FILTER_VALIDATE_BOOLEAN
        );
        $trigger_suppress_path_patterns = $this->parse_companion_path_patterns(
            (string) $this->flosc->get_setting('companion_trigger_suppress_path_patterns', $defaults['trigger_suppress_path_patterns'])
        );

        $motion_mode = (string) $this->flosc->get_setting('companion_motion_mode', $defaults['motion_mode']);
        if (!in_array($motion_mode, $this->get_companion_motion_modes(), true)) {
            $motion_mode = $defaults['motion_mode'];
        }
        $focus_on_open = filter_var(
            $this->flosc->get_setting('companion_focus_on_open', $defaults['focus_on_open']),
            FILTER_VALIDATE_BOOLEAN
        );
        $allow_escape_close = filter_var(
            $this->flosc->get_setting('companion_allow_escape_close', $defaults['allow_escape_close']),
            FILTER_VALIDATE_BOOLEAN
        );
        $enable_keyboard_shortcut = filter_var(
            $this->flosc->get_setting('companion_enable_keyboard_shortcut', $defaults['enable_keyboard_shortcut']),
            FILTER_VALIDATE_BOOLEAN
        );
        $keyboard_shortcut_key = sanitize_key((string) $this->flosc->get_setting('companion_keyboard_shortcut_key', $defaults['keyboard_shortcut_key']));
        $keyboard_shortcut_key = substr($keyboard_shortcut_key, 0, 1);
        if (!preg_match('/^[a-z0-9]$/', $keyboard_shortcut_key)) {
            $keyboard_shortcut_key = 'k';
        }
        $launcher_aria_label = sanitize_text_field((string) $this->flosc->get_setting('companion_launcher_aria_label', $defaults['launcher_aria_label']));
        if ($launcher_aria_label === '') {
            $launcher_aria_label = esc_html__('Open Chat', 'flosc');
        }
        $close_aria_label = sanitize_text_field((string) $this->flosc->get_setting('companion_close_aria_label', $defaults['close_aria_label']));
        if ($close_aria_label === '') {
            $close_aria_label = esc_html__('Collapse Chat', 'flosc');
        }
        $remember_open_state = filter_var(
            $this->flosc->get_setting('companion_remember_open_state', $defaults['remember_open_state']),
            FILTER_VALIDATE_BOOLEAN
        );
        $state_storage = (string) $this->flosc->get_setting('companion_state_storage', $defaults['state_storage']);
        if (!in_array($state_storage, $this->get_companion_state_storages(), true)) {
            $state_storage = $defaults['state_storage'];
        }
        $trigger_cooldown_ms = absint($this->flosc->get_setting('companion_trigger_cooldown_ms', $defaults['trigger_cooldown_ms']));
        $trigger_cooldown_ms = max($numeric_limits['trigger_cooldown_min_ms'], min($numeric_limits['trigger_cooldown_max_ms'], $trigger_cooldown_ms));

        $context_params = $pass_page_context ? $this->build_companion_context_params($context_scope) : [];

        $flow = $this->flosc->get_current_flow();
        $flow_id = is_array($flow) ? sanitize_text_field((string) ($flow['id'] ?? '')) : '';
        $sale = method_exists($this->flosc, 'sale') ? $this->flosc->sale() : null;
        $token_provider = ($sale && method_exists($sale, 'get_provider')) ? $sale->get_provider('tokens') : null;
        // Mirror FLOSC_Framework visitor wallet baseline (trait method is private).
        $visitor_wallet_initial = 0;
        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        if ($flow_stem !== '') {
            $flow_settings = get_option('flosc_flow_' . $flow_stem, []);
            if (is_array($flow_settings) && isset($flow_settings['tokens_communication_tokens_per_message'])) {
                $visitor_wallet_initial = max(0, intval($flow_settings['tokens_communication_tokens_per_message']));
            }
        }
        if ($visitor_wallet_initial <= 0) {
            $visitor_wallet_initial = max(0, intval(get_option('flosc_tokens_communication_tokens_per_message', 5000)));
        }
        if ($visitor_wallet_initial <= 0) {
            $visitor_wallet_initial = 5000;
        }

        wp_enqueue_style(
            'flosc-companion',
            FLOSC_PLUGIN_URL . 'assets/css/flosc-companion.css',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/css/flosc-companion.css')
        );

        // Companion accent is emitted as a CSS custom property on the style handle.
        if (!empty($accent)) {
            wp_add_inline_style('flosc-companion', sprintf(
                '.flosc-companion{--flosc-accent:%s;--flosc-companion-width:%dpx;--flosc-companion-height:%dpx;--flosc-companion-launcher-size:%dpx;}',
                esc_attr($accent)
                ,
                $panel_width,
                $panel_height,
                $launcher_size
            ));
        } else {
            wp_add_inline_style('flosc-companion', sprintf(
                '.flosc-companion{--flosc-companion-width:%dpx;--flosc-companion-height:%dpx;--flosc-companion-launcher-size:%dpx;}',
                $panel_width,
                $panel_height,
                $launcher_size
            ));
        }

        wp_enqueue_script(
            'flosc-companion',
            FLOSC_PLUGIN_URL . 'assets/js/flosc-companion.js',
            [],
            filemtime(FLOSC_PLUGIN_DIR . 'assets/js/flosc-companion.js'),
            true
        );

        wp_localize_script('flosc-companion', 'floscCompanionL10n', [
            'openChat' => esc_html__('Open Chat', 'flosc'),
            'collapseChat' => esc_html__('Collapse Chat', 'flosc'),
            'floscAssistant' => esc_html__('FLOSC Assistant', 'flosc'),
            'processing' => esc_html__('Processing...', 'flosc'),
            'chatWithUs' => esc_html__('Chat with us', 'flosc'),
            'weReplyInstantly' => esc_html__('We reply instantly', 'flosc'),
            'toggleFullscreen' => esc_html__('Toggle fullscreen', 'flosc'),
        ]);

        // Expand destination: must also be a FLOSC app route (same invariant as iframe).
        $full_page_url = esc_url_raw((string) $this->flosc->get_setting('companion_hub_fullscreen_url', ''), ['http', 'https']);
        if ($full_page_url === '' || !$this->is_flosc_app_route_url($full_page_url)) {
            $full_page_url = $app_url;
        }

        $assistant_title = $product_name !== ''
            ? $product_name
            : sanitize_text_field((string) __('Assistant', 'flosc'));

        $companion_config = [
            'appUrl' => $app_url,
            'fullPageUrl' => $full_page_url,
            'returnUrl' => $current_url,
            'flowId' => $flow_id,
            'visitorTokenGrant' => $visitor_wallet_initial,
            'productName' => $product_name,
            'title' => $title,
            'subtitle' => $subtitle,
            'showHeaderTitle' => $show_header_title,
            'showHeaderSubtitle' => $show_header_subtitle,
            'showOpenFullpage' => $show_open_fullpage,
            // Wallet/tokens render in the iframe profile row — never in the purple header.
            'headerTokenText' => '',
            'showHeaderTokens' => false,
            // Parameterized brand icon (Chat Logo / companion_header_icon_url). No emoji default.
            'headerIconUrl' => $header_icon_url,
            'avatar' => '',
            'accentColor' => $accent ?: $defaults['accent_color'],
            'position' => $position,
            'mode' => $mode,
            'width' => $panel_width . 'px',
            'height' => $panel_height . 'px',
            'mobileBehavior' => $mobile_behavior,
            'contextParams' => $context_params,
            'currentPagePostId' => (int) get_queried_object_id(),
            'autoOpenEnabled' => $auto_open_enabled,
            'autoOpenDelayMs' => $auto_open_delay_ms,
            'autoOpenOncePerSession' => $auto_open_once_per_session,
            'sessionKey' => 'flosc_companion_auto_open_' . md5((string) $app_url),
            'launchOnExitIntent' => $launch_on_exit_intent,
            'launchOnScrollThreshold' => $launch_on_scroll_threshold,
            'launchOnScrollPercent' => $launch_on_scroll_percent,
            'launcherSvgPath' => $launcher_svg_path,
            'launcherIconUrl' => $launcher_icon_url,
            'launcherUsesProductLogo' => $launcher_uses_product_logo,
            'triggerDesktopOnly' => $trigger_desktop_only,
            'triggerMinPageTimeMs' => $trigger_min_page_time_ms,
            'triggerSuppressOnAuthCheckout' => $trigger_suppress_on_auth_checkout,
            'triggerSuppressPathPatterns' => $trigger_suppress_path_patterns,
            'currentPath' => $this->get_companion_request_path(),
            'motionMode' => $motion_mode,
            'focusOnOpen' => $focus_on_open,
            'allowEscapeClose' => $allow_escape_close,
            'enableKeyboardShortcut' => $enable_keyboard_shortcut,
            'keyboardShortcutKey' => $keyboard_shortcut_key,
            'launcherAriaLabel' => $launcher_aria_label,
            'closeAriaLabel' => $close_aria_label,
            'assistantTitle' => $assistant_title,
            'launcherOpenAriaLabel' => $launcher_aria_label,
            'launcherCollapseAriaLabel' => esc_html__('Collapse Chat', 'flosc'),
            'rememberOpenState' => $remember_open_state,
            'stateStorage' => $state_storage,
            'triggerCooldownMs' => $trigger_cooldown_ms,
            'stateKey' => 'flosc_companion_state_' . md5((string) $app_url),
            'cooldownKey' => 'flosc_companion_cooldown_' . md5((string) $app_url),
            'skipBehaviorTriggers' => $handoff_request,
        ];

        $filtered_companion_config = apply_filters('flosc_companion_frontend_config', $companion_config, $this);
        if (is_array($filtered_companion_config)) {
            $companion_config = wp_parse_args($filtered_companion_config, $companion_config);
        }

        // Keep runtime payload stable even when third-party filters alter value types.
        // Filters cannot smuggle a non-app URL into the iframe.
        $companion_config['appUrl'] = esc_url_raw((string) ($companion_config['appUrl'] ?? $app_url), ['http', 'https']);
        $filtered_app = (string) $companion_config['appUrl'];
        if ($filtered_app === '' || !$this->is_flosc_app_route_url($filtered_app)) {
            return;
        }
        $companion_config['appUrl'] = $filtered_app;
        $companion_config['title'] = sanitize_text_field((string) ($companion_config['title'] ?? $title));
        $companion_config['subtitle'] = sanitize_text_field((string) ($companion_config['subtitle'] ?? $subtitle));
        $companion_config['productName'] = sanitize_text_field((string) ($companion_config['productName'] ?? $product_name));
        $companion_config['headerIconUrl'] = esc_url_raw((string) ($companion_config['headerIconUrl'] ?? $header_icon_url));
        $companion_config['launcherIconUrl'] = esc_url_raw((string) ($companion_config['launcherIconUrl'] ?? $launcher_icon_url));
        $companion_config['headerTokenText'] = '';
        $companion_config['showHeaderTokens'] = false;
        $companion_config['launcherAriaLabel'] = sanitize_text_field((string) ($companion_config['launcherAriaLabel'] ?? $launcher_aria_label));
        $companion_config['closeAriaLabel'] = sanitize_text_field((string) ($companion_config['closeAriaLabel'] ?? $close_aria_label));
        $companion_config['assistantTitle'] = sanitize_text_field((string) ($companion_config['assistantTitle'] ?? $assistant_title));
        $companion_config['launcherOpenAriaLabel'] = sanitize_text_field((string) ($companion_config['launcherOpenAriaLabel'] ?? $launcher_aria_label));
        $companion_config['launcherCollapseAriaLabel'] = sanitize_text_field((string) ($companion_config['launcherCollapseAriaLabel'] ?? esc_html__('Collapse Chat', 'flosc')));
        if (!is_array($companion_config['contextParams'] ?? null)) {
            $companion_config['contextParams'] = [];
        }
        if (!is_array($companion_config['triggerSuppressPathPatterns'] ?? null)) {
            $companion_config['triggerSuppressPathPatterns'] = [];
        }

        $queried_post_id = (int) get_queried_object_id();
        if ($queried_post_id > 0) {
            wp_add_inline_script(
                'flosc-companion',
                sprintf(
                    '(function(){var id=%1$d;if(id>0&&document.body){document.body.setAttribute("data-flosc-post-id",String(id));}})();',
                    $queried_post_id
                ),
                'before'
            );
        }

        wp_add_inline_script('flosc-companion', sprintf(
            'FloscCompanion.init(%s);',
            wp_json_encode($companion_config)
        ));
    }

    /**
     * Resolve which flow owns companion on this request (knowledge hub support).
     *
     * Priority:
     * 1) flosc_flow_id / flosc_ivr query (from full-chat dock handoff)
     * 2) Current URL matches a flow's companion_hub_companion_url
     * 3) Current WP category matches a flow's content_item_groups / content_item_category
     * 4) Leave get_current_flow() as-is (domain/slug detection)
     *
     * @param bool $handoff_request Whether this is a companion handoff navigation.
     */
    private function resolve_companion_flow_context($handoff_request = false) {
        $request_path = $this->get_companion_request_path();
        $category_slugs = $this->get_companion_request_category_slugs();
        // Normalize so site-root "/" is not collapsed to "" (WP untrailingslashit('/')).
        $req_path = $this->companion_normalize_url_path($request_path);

        // Optional dock hint from full-page chat (public query string, not a form).
        $hint = sanitize_text_field((string) filter_input(INPUT_GET, 'flosc_flow_id'));
        if ($hint === '') {
            $hint = sanitize_text_field((string) filter_input(INPUT_GET, 'flosc_ivr'));
        }
        $hint = sanitize_key(preg_replace('/\.md$/i', '', (string) $hint));

        $matches = $this->find_companion_flows_for_request($req_path, $category_slugs);
        $hub_match = $matches['hub'] ?? null;
        $category_match = $matches['category'] ?? null;
        $page_owner = $hub_match ?: $category_match;

        // Hint may only select a companion-enabled flow that either owns this page
        // or is an explicit handoff to a real flow (dock from full-page chat).
        if ($hint !== '') {
            $hint_flow = $this->flosc->build_flow_from_ivr_file($hint . '.md');
            if (!is_array($hint_flow)) {
                $hint_flow = $this->flosc->build_flow_from_ivr_file($hint);
            }
            $hint_ok = is_array($hint_flow)
                && filter_var($hint_flow['companion_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && in_array((string) ($hint_flow['companion_content_display_mode'] ?? 'in_chat'), ['companion', 'both'], true);

            if ($hint_ok) {
                $hint_id = sanitize_key((string) ($hint_flow['id'] ?? $hint));
                if ($page_owner && $hint_id === $page_owner) {
                    $this->flosc->set_flow_context($hint_id);
                    return;
                }
                if ($handoff_request) {
                    // Cross-domain dock: hub host may not yet match path heuristics;
                    // only accept a real companion-enabled flow id, never free-form text.
                    $this->flosc->set_flow_context($hint_id);
                    return;
                }
            }
        }

        if ($page_owner) {
            $this->flosc->set_flow_context($page_owner);
        }
    }

    /**
     * Find companion-enabled flows that own the current request path or category.
     *
     * @param string   $req_path        Normalized request path.
     * @param string[] $category_slugs  Category slugs for this request.
     * @return array{hub:?string,category:?string}
     */
    private function find_companion_flows_for_request($req_path, array $category_slugs) {
        $hub_match = null;
        $hub_match_len = -1;
        $category_match = null;

        $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));
        foreach ($ivr_files as $filename) {
            if (strpos($filename, 'backup') !== false) {
                continue;
            }
            $flow = $this->flosc->build_flow_from_ivr_file($filename);
            if (!is_array($flow)) {
                continue;
            }
            $enabled = filter_var($flow['companion_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $mode = (string) ($flow['companion_content_display_mode'] ?? 'in_chat');
            if (!$enabled || !in_array($mode, ['companion', 'both'], true)) {
                continue;
            }

            $flow_id = pathinfo($filename, PATHINFO_FILENAME);

            // Hub companion URL path match — prefer longest (most specific) path.
            // Site-root hubs (https://dainis.net/) must match path "/" — never drop to "".
            $hub = esc_url_raw((string) ($flow['companion_hub_companion_url'] ?? ''));
            if ($hub !== '' && $req_path !== '') {
                $hub_path_raw = wp_parse_url($hub, PHP_URL_PATH);
                $hub_path = is_string($hub_path_raw) ? $this->companion_normalize_url_path($hub_path_raw) : '';
                if ($hub_path !== '') {
                    // Site-root hub ("/") is a low-priority sitewide owner (e.g. dainis.net).
                    // Longer hub paths always win (e.g. /category/lesaep/).
                    $matches_hub = ($hub_path === '/')
                        || $req_path === $hub_path
                        || strpos($req_path . '/', $hub_path . '/') === 0;
                    if ($matches_hub) {
                        $len = ($hub_path === '/') ? 1 : strlen($hub_path);
                        if ($len > $hub_match_len) {
                            $hub_match_len = $len;
                            $hub_match = $flow_id;
                        }
                    }
                }
            }

            // Lessons category / content group → knowledge hub archive or lesson posts.
            if ($category_match === null && !empty($category_slugs)) {
                $flow_cats = [];
                if (!empty($flow['content_item_category'])) {
                    $flow_cats[] = sanitize_title((string) $flow['content_item_category']);
                }
                if (!empty($flow['content_item_groups']) && is_array($flow['content_item_groups'])) {
                    foreach ($flow['content_item_groups'] as $group) {
                        if (!empty($group['category'])) {
                            $flow_cats[] = sanitize_title((string) $group['category']);
                        }
                    }
                }
                $flow_cats = array_filter(array_unique($flow_cats));
                if (array_intersect($category_slugs, $flow_cats)) {
                    $category_match = $flow_id;
                }
            }
        }

        return [
            'hub'      => $hub_match,
            'category' => $category_match,
        ];
    }

    /**
     * Category slugs for the current frontend request (archive or single post).
     *
     * @return string[]
     */
    private function get_companion_request_category_slugs() {
        $slugs = [];
        if (function_exists('is_category') && is_category()) {
            $term = get_queried_object();
            if ($term && !empty($term->slug)) {
                $slugs[] = sanitize_title((string) $term->slug);
            }
        }
        if (function_exists('is_singular') && is_singular()) {
            $post_id = get_queried_object_id();
            if ($post_id) {
                $terms = get_the_terms($post_id, 'category');
                if (is_array($terms)) {
                    foreach ($terms as $term) {
                        if (!empty($term->slug)) {
                            $slugs[] = sanitize_title((string) $term->slug);
                        }
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * Normalize URL path for companion comparisons.
     * Empty / missing path and bare "/" all mean site root.
     *
     * @param string $url Absolute or relative URL.
     * @return string Path with leading slash, no trailing slash except root "/".
     */
    private function companion_normalize_url_path($url) {
        $path = (string) wp_parse_url((string) $url, PHP_URL_PATH);
        $path = untrailingslashit($path);
        return ($path === '' || $path === false) ? '/' : $path;
    }

    /**
     * True when $url is a FLOSC full-page app route (active flow slug or custom domain).
     * Hub pages, posts, home, and arbitrary WP paths are not app routes.
     *
     * This is the structural invariant: companion iframe may only load such URLs.
     * App routes never enqueue outer companion chrome (is_flosc_request), so nesting
     * cannot occur.
     *
     * @param string $url Absolute http(s) URL.
     * @return bool
     */
    private function is_flosc_app_route_url($url) {
        $url = esc_url_raw((string) $url, ['http', 'https']);
        if ($url === '') {
            return false;
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        $path = $this->companion_normalize_url_path($url);
        if ($host === '') {
            return false;
        }

        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));

        if (function_exists('flosc_config_glob')) {
            $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));
            foreach ($ivr_files as $filename) {
                if (strpos((string) $filename, 'backup') !== false) {
                    continue;
                }
                $flow = $this->flosc->build_flow_from_ivr_file($filename);
                if (!$flow || (($flow['status'] ?? 'active') !== 'active')) {
                    continue;
                }

                if (!empty($flow['custom_domain'])) {
                    $domain = strtolower(preg_replace('#^https?://#', '', trim((string) $flow['custom_domain'])));
                    $domain = rtrim($domain, '/');
                    if ($domain !== '' && $this->companion_hosts_match($host, $domain)) {
                        // Custom-domain flows serve the SPA for that host.
                        return true;
                    }
                }

                if (!empty($flow['slug']) && $home_host !== '') {
                    $slug = sanitize_title((string) $flow['slug']);
                    if (
                        $slug !== ''
                        && $this->companion_hosts_match($host, $home_host)
                        && $this->companion_path_is_flow_slug($path, $slug)
                    ) {
                        return true;
                    }
                }
            }
        }

        // Legacy global app slug / custom domain (pre multi-flow fallbacks).
        $global_slug = sanitize_title((string) get_option('flosc_app_slug', 'flosc'));
        if (
            $global_slug !== ''
            && $home_host !== ''
            && $this->companion_hosts_match($host, $home_host)
            && $this->companion_path_is_flow_slug($path, $global_slug)
        ) {
            return true;
        }

        $global_domain = strtolower(preg_replace('#^https?://#', '', trim((string) get_option('flosc_custom_domain', ''))));
        $global_domain = rtrim($global_domain, '/');
        if ($global_domain !== '' && $this->companion_hosts_match($host, $global_domain)) {
            return true;
        }

        // Resolved flow after hub/handoff context (forced_flow). Session dock handoff
        // sets this before enqueue; must still accept that flow's app surface so
        // flosc_session_id / visitor continuity can load into the iframe.
        $flow = $this->flosc->get_current_flow();
        if (is_array($flow)) {
            if (!empty($flow['custom_domain'])) {
                $domain = strtolower(preg_replace('#^https?://#', '', trim((string) $flow['custom_domain'])));
                $domain = rtrim($domain, '/');
                if ($domain !== '' && $this->companion_hosts_match($host, $domain)) {
                    return true;
                }
            }
            if (!empty($flow['slug']) && $home_host !== '') {
                $slug = sanitize_title((string) $flow['slug']);
                if (
                    $slug !== ''
                    && $this->companion_hosts_match($host, $home_host)
                    && $this->companion_path_is_flow_slug($path, $slug)
                ) {
                    return true;
                }
            }
        }

        $resolved_app = esc_url_raw((string) $this->flosc->get_app_url(), ['http', 'https']);
        if ($resolved_app !== '' && $this->companion_app_routes_match($url, $resolved_app)) {
            return true;
        }

        return false;
    }

    /**
     * Same app document (host + path); query/fragment ignored.
     * Used so session handoff targets still match get_app_url() exactly.
     *
     * @param string $url_a First URL.
     * @param string $url_b Second URL.
     * @return bool
     */
    private function companion_app_routes_match($url_a, $url_b) {
        $url_a = (string) $url_a;
        $url_b = (string) $url_b;
        if ($url_a === '' || $url_b === '') {
            return false;
        }
        $host_a = strtolower((string) wp_parse_url($url_a, PHP_URL_HOST));
        $host_b = strtolower((string) wp_parse_url($url_b, PHP_URL_HOST));
        if ($host_a === '' || $host_b === '' || !$this->companion_hosts_match($host_a, $host_b)) {
            return false;
        }
        return $this->companion_normalize_url_path($url_a) === $this->companion_normalize_url_path($url_b);
    }

    /**
     * @param string $host_a Host A.
     * @param string $host_b Host B.
     * @return bool
     */
    private function companion_hosts_match($host_a, $host_b) {
        $host_a = strtolower((string) $host_a);
        $host_b = strtolower((string) $host_b);
        if ($host_a === '' || $host_b === '') {
            return false;
        }
        if ($host_a === $host_b) {
            return true;
        }
        return $host_a === 'www.' . $host_b || $host_b === 'www.' . $host_a;
    }

    /**
     * Path is exactly /{slug} or under /{slug}/...
     *
     * @param string $path Normalized path (/ or /foo).
     * @param string $slug Flow slug.
     * @return bool
     */
    private function companion_path_is_flow_slug($path, $slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return false;
        }
        $path = (string) $path;
        if ($path === '' || $path[0] !== '/') {
            $path = $this->companion_normalize_url_path($path);
        } else {
            $path = untrailingslashit($path);
            if ($path === '') {
                $path = '/';
            }
        }
        $prefix = '/' . $slug;
        return $path === $prefix || strpos($path . '/', $prefix . '/') === 0;
    }

    /**
     * Resolve companion iframe app URL from floscAdmin parameters only.
     *
     * Priority matches the pre-nest-fix order (session integrity / same chat surface):
     * 1) companion_chat_app_url (explicit Companion chat URL field)
     * 2) hub defaults chat_app (same-origin flow slug when available)
     * 3) companion_flow_slug / flow slug on this WordPress site
     * 4) Flow get_app_url()
     *
     * Fullscreen / expand URL is separate (full_page_url) — not an iframe candidate.
     * Only URLs that pass is_flosc_app_route_url() are returned (no hub/home iframe).
     */
    private function get_companion_chat_app_url() {
        $candidates = [];

        $configured = esc_url_raw((string) $this->flosc->get_setting('companion_chat_app_url', ''), ['http', 'https']);
        if ($configured !== '') {
            $candidates[] = $configured;
        }

        $flow = $this->flosc->get_current_flow();
        $flow_settings = is_array($flow) ? $flow : [];
        if (function_exists('flosc_companion_hub_defaults_from_flow')) {
            $defaults = flosc_companion_hub_defaults_from_flow($flow_settings);
            // chat_app only — same as HEAD. Do not prefer fullscreen for the iframe
            // (wrong surface can drop guest/member continuity onto another route).
            $from_defaults = esc_url_raw((string) ($defaults['chat_app'] ?? ''), ['http', 'https']);
            if ($from_defaults !== '') {
                $candidates[] = $from_defaults;
            }
        }

        $slug = sanitize_title((string) $this->flosc->get_setting('companion_flow_slug', ''));
        if ($slug === '' && is_array($flow) && !empty($flow['slug'])) {
            $slug = sanitize_title((string) $flow['slug']);
        }
        if ($slug !== '') {
            $candidates[] = esc_url_raw(home_url('/' . $slug . '/'), ['http', 'https']);
        }

        $app = $this->flosc->get_app_url();
        if (!empty($app)) {
            $candidates[] = esc_url_raw((string) $app, ['http', 'https']);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && $this->is_flosc_app_route_url($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Find active flow by slug for companion URL hardening.
     */
    private function get_flow_by_slug_for_companion($slug) {
        $slug = sanitize_title((string) $slug);
        if ($slug === '') {
            return null;
        }

        $ivr_files = array_unique(array_map('basename', flosc_config_glob(['*_ivr.md', 'ivr*.md'])));
        foreach ($ivr_files as $filename) {
            if (strpos($filename, 'backup') !== false) {
                continue;
            }

            $flow = $this->flosc->build_flow_from_ivr_file($filename);
            if (!is_array($flow)) {
                continue;
            }

            if (($flow['status'] ?? 'active') !== 'active') {
                continue;
            }

            if (sanitize_title((string) ($flow['slug'] ?? '')) === $slug) {
                return $flow;
            }
        }

        return null;
    }

    /**
     * Build current frontend URL for companion handoff context.
     */
    private function get_current_frontend_url() {
        $request_uri = sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $query = (string) wp_parse_url($request_uri, PHP_URL_QUERY);

        if ($path === '') {
            $path = '/';
        }

        $url = home_url($path);
        if ($query !== '') {
            $url = $url . '?' . $query;
        }

        return esc_url_raw($url);
    }

    /**
     * Filterable companion defaults for stable parameterization.
     */
    private function get_companion_defaults() {
        $defaults = [
            'enabled' => false,
            'mode' => 'in_chat',
            'position' => 'bottom-right',
            'greeting' => 'Chat with us',
            'subtitle' => 'We reply instantly',
            'show_header_title' => true,
            'show_header_subtitle' => true,
            'show_open_fullpage' => true,
            'contextual_prompt' => 'What do you want to explore together?',
            // Companion profile row tier codes: Name (V|G|M) — brand-neutral FLOSC defaults.
            'profile_tier_visitor' => 'V',
            'profile_tier_guest' => 'G',
            'profile_tier_member' => 'M',
            'profile_tier_visitor_label' => 'Visitor',
            'profile_tier_guest_label' => 'Guest',
            'profile_tier_member_label' => 'Member',
            'accent_color' => '#6366f1',
            // Sales freeline default: site visitors see the companion bubble.
            // Operators may still turn this off per flow (stored 0/1).
            'show_for_visitors' => true,
            'panel_width' => 380,
            'panel_height' => 560,
            'launcher_size' => 60,
            'launcher_icon' => 'product_logo',
            'mobile_behavior' => 'fullscreen',
            'pass_page_context' => true,
            'context_scope' => 'basic',
            'auto_open_enabled' => false,
            'auto_open_delay_ms' => 1500,
            'auto_open_once_per_session' => true,
            'launch_on_exit_intent' => false,
            'launch_on_scroll_threshold' => false,
            'launch_on_scroll_percent' => 0,
            'trigger_desktop_only' => true,
            'trigger_min_page_time_ms' => 0,
            'trigger_suppress_on_auth_checkout' => true,
            'trigger_suppress_path_patterns' => '',
            'motion_mode' => 'system',
            'focus_on_open' => true,
            'allow_escape_close' => true,
            'enable_keyboard_shortcut' => false,
            'keyboard_shortcut_key' => 'k',
            'launcher_aria_label' => 'Open Chat',
            'close_aria_label' => 'Collapse Chat',
            'remember_open_state' => false,
            'state_storage' => 'session',
            'trigger_cooldown_ms' => 0,
        ];

        $filtered = apply_filters('flosc_companion_defaults', $defaults, $this);
        if (!is_array($filtered)) {
            return $defaults;
        }

        return wp_parse_args($filtered, $defaults);
    }

    /**
     * Filterable numeric bounds for companion setting sanitization.
     */
    private function get_companion_numeric_limits() {
        $limits = [
            'panel_width_min' => 280,
            'panel_width_max' => 900,
            'panel_height_min' => 320,
            'panel_height_max' => 1200,
            'launcher_size_min' => 44,
            'launcher_size_max' => 96,
            'auto_open_delay_min_ms' => 0,
            'auto_open_delay_max_ms' => 60000,
            'scroll_percent_min' => 0,
            'scroll_percent_max' => 100,
            'trigger_min_page_time_min_ms' => 0,
            'trigger_min_page_time_max_ms' => 120000,
            'trigger_cooldown_min_ms' => 0,
            'trigger_cooldown_max_ms' => 86400000,
        ];

        $filtered = apply_filters('flosc_companion_numeric_limits', $limits, $this);
        if (is_array($filtered)) {
            $limits = wp_parse_args($filtered, $limits);
        }

        foreach ($limits as $key => $value) {
            $limits[$key] = absint($value);
        }

        if ($limits['panel_width_max'] < $limits['panel_width_min']) {
            $limits['panel_width_max'] = $limits['panel_width_min'];
        }
        if ($limits['panel_height_max'] < $limits['panel_height_min']) {
            $limits['panel_height_max'] = $limits['panel_height_min'];
        }
        if ($limits['launcher_size_max'] < $limits['launcher_size_min']) {
            $limits['launcher_size_max'] = $limits['launcher_size_min'];
        }
        if ($limits['auto_open_delay_max_ms'] < $limits['auto_open_delay_min_ms']) {
            $limits['auto_open_delay_max_ms'] = $limits['auto_open_delay_min_ms'];
        }
        if ($limits['scroll_percent_max'] < $limits['scroll_percent_min']) {
            $limits['scroll_percent_max'] = $limits['scroll_percent_min'];
        }
        if ($limits['trigger_min_page_time_max_ms'] < $limits['trigger_min_page_time_min_ms']) {
            $limits['trigger_min_page_time_max_ms'] = $limits['trigger_min_page_time_min_ms'];
        }
        if ($limits['trigger_cooldown_max_ms'] < $limits['trigger_cooldown_min_ms']) {
            $limits['trigger_cooldown_max_ms'] = $limits['trigger_cooldown_min_ms'];
        }

        return $limits;
    }

    /**
     * Filterable full mode list for validation/sanitization.
     */
    private function get_companion_allowed_modes() {
        $modes = apply_filters('flosc_companion_allowed_modes', ['in_chat', 'companion', 'both'], $this);
        return is_array($modes) ? array_values(array_unique(array_map('strval', $modes))) : ['in_chat', 'companion', 'both'];
    }

    /**
     * Filterable mode list that should render the widget shell.
     */
    private function get_companion_widget_modes() {
        $modes = apply_filters('flosc_companion_widget_modes', ['companion', 'both'], $this);
        return is_array($modes) ? array_values(array_unique(array_map('strval', $modes))) : ['companion', 'both'];
    }

    /**
     * Filterable launcher position list.
     */
    private function get_companion_allowed_positions() {
        $positions = apply_filters('flosc_companion_allowed_positions', ['bottom-right', 'bottom-left'], $this);
        return is_array($positions) ? array_values(array_unique(array_map('strval', $positions))) : ['bottom-right', 'bottom-left'];
    }

    /**
     * Filterable mobile behavior list for companion panel.
     */
    private function get_companion_mobile_behaviors() {
        $behaviors = apply_filters('flosc_companion_mobile_behaviors', ['fullscreen', 'panel'], $this);
        return is_array($behaviors) ? array_values(array_unique(array_map('strval', $behaviors))) : ['fullscreen', 'panel'];
    }

    /**
     * Filterable companion context scope list.
     */
    private function get_companion_context_scopes() {
        $scopes = apply_filters('flosc_companion_context_scopes', ['basic', 'extended'], $this);
        return is_array($scopes) ? array_values(array_unique(array_map('strval', $scopes))) : ['basic', 'extended'];
    }

    /**
     * Filterable motion mode list for companion interactions.
     */
    private function get_companion_motion_modes() {
        $modes = apply_filters('flosc_companion_motion_modes', ['system', 'reduce', 'full'], $this);
        return is_array($modes) ? array_values(array_unique(array_map('strval', $modes))) : ['system', 'reduce', 'full'];
    }

    /**
     * Filterable storage backends for companion browser state.
     */
    private function get_companion_state_storages() {
        $storages = apply_filters('flosc_companion_state_storages', ['session', 'local'], $this);
        return is_array($storages) ? array_values(array_unique(array_map('strval', $storages))) : ['session', 'local'];
    }

    /**
     * Filterable launcher SVG path map keyed by launcher icon id.
     */
    private function get_companion_launcher_svg_paths() {
        $paths = [
            'chat' => 'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z',
            'help' => 'M12 2a10 10 0 100 20 10 10 0 000-20zm0 17a1.25 1.25 0 110-2.5A1.25 1.25 0 0112 19zm1.15-5.55-.52.33A1.74 1.74 0 0011.8 15h-1.6v-.4c0-1 .5-1.92 1.34-2.44l.72-.45c.5-.31.8-.85.8-1.44 0-.93-.77-1.7-1.7-1.7s-1.7.77-1.7 1.7H8.1a3.3 3.3 0 116.6 0c0 1.15-.58 2.2-1.55 2.83z',
            'spark' => 'M12 2l2.6 5.4L20 10l-5.4 2.6L12 18l-2.6-5.4L4 10l5.4-2.6L12 2zm8 14l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2zM4 14l1 2 2 1-2 1-1 2-1-2-2-1 2-1 1-2z',
        ];

        $filtered = apply_filters('flosc_companion_launcher_svg_paths', $paths, $this);
        return is_array($filtered) ? $filtered : $paths;
    }

    /**
     * Build context parameters passed into companion iframe URL.
     */
    private function build_companion_context_params($scope) {
        global $wp;

        $object_id = (int) get_queried_object_id();
        $page_url  = '';
        if ($object_id > 0 && is_numeric($object_id)) {
            $permalink = get_permalink($object_id);
            if (is_string($permalink) && $permalink !== '') {
                $page_url = $permalink;
            }
        }
        if ($page_url === '') {
            $request_path = is_object($wp) ? trim((string) ($wp->request ?? ''), '/') : '';
            $page_url     = $request_path !== '' ? home_url('/' . $request_path . '/') : home_url('/');
        }

        $params = [
            'flosc_context_url'     => esc_url_raw($page_url),
            'flosc_context_surface' => 'companion',
        ];

        if ($object_id > 0) {
            $params['flosc_context_post_id'] = $object_id;
            $params['flosc_context_title']   = sanitize_text_field((string) get_the_title($object_id));
        }

        if ($scope === 'extended' && is_singular()) {
            $post = get_post($object_id);
            if ($post) {
                $params['flosc_context_post_type'] = sanitize_key((string) $post->post_type);
            }
        }

        return apply_filters('flosc_companion_context_params', $params, $scope, $this);
    }

    /**
     * Parse path-pattern textarea into normalized prefix list.
     */
    private function parse_companion_path_patterns($raw_patterns) {
        $patterns = [];
        $chunks = preg_split('/[\r\n,]+/', (string) $raw_patterns);
        if (!is_array($chunks)) {
            return $patterns;
        }

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $patterns[] = '/' . ltrim($chunk, '/');
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Filterable target rule type list.
     */
    private function get_companion_target_types() {
        $types = apply_filters('flosc_companion_target_rule_types', ['path', 'page', 'post', 'category', 'tag'], $this);
        return is_array($types) ? array_values(array_unique(array_map('strval', $types))) : ['path', 'page', 'post', 'category', 'tag'];
    }

    /**
     * Companion targeting evaluator for the current frontend request.
     * Rules are newline-delimited and support path/page/post/category/tag formats.
     */
    private function should_show_companion_for_current_request() {
        $include_rules = $this->parse_companion_target_rules(
            (string) $this->flosc->get_setting('companion_target_include', '')
        );
        $exclude_rules = $this->parse_companion_target_rules(
            (string) $this->flosc->get_setting('companion_target_exclude', '')
        );

        // Exclusions take precedence to make policy intent deterministic.
        if (!empty($exclude_rules) && $this->companion_target_matches_any_rule($exclude_rules)) {
            return false;
        }

        // Empty include means global include (subject to other gates).
        if (empty($include_rules)) {
            return true;
        }

        return $this->companion_target_matches_any_rule($include_rules);
    }

    /**
     * Parse multiline/csv companion targeting input into normalized rule objects.
     */
    private function parse_companion_target_rules($raw_rules) {
        $rules = [];
        $chunks = preg_split('/[\r\n,]+/', (string) $raw_rules);
        if (!is_array($chunks)) {
            return $rules;
        }

        foreach ($chunks as $raw_rule) {
            $raw_rule = trim((string) $raw_rule);
            if ($raw_rule === '') {
                continue;
            }

            if (strpos($raw_rule, ':') === false) {
                $rules[] = [
                    'type' => 'path',
                    'value' => '/' . ltrim($raw_rule, '/'),
                ];
                continue;
            }

            list($type, $value) = array_map('trim', explode(':', $raw_rule, 2));
            $type = strtolower($type);
            $value = (string) $value;
            if ($value === '') {
                continue;
            }

            if (!in_array($type, $this->get_companion_target_types(), true)) {
                continue;
            }

            if ($type === 'path') {
                $value = '/' . ltrim($value, '/');
            }

            $rules[] = [
                'type' => $type,
                'value' => $value,
            ];
        }

        return $rules;
    }

    /**
     * Determine whether any targeting rule matches the current request context.
     */
    private function companion_target_matches_any_rule($rules) {
        if (empty($rules)) {
            return false;
        }

        $request_path = $this->get_companion_request_path();

        foreach ($rules as $rule) {
            $type = (string) ($rule['type'] ?? '');
            $value = (string) ($rule['value'] ?? '');
            if ($type === '' || $value === '') {
                continue;
            }

            // Extension point: custom rule types can provide match logic here.
            $custom_match = apply_filters(
                'flosc_companion_target_rule_match',
                null,
                $type,
                $value,
                $request_path,
                $this
            );
            if (is_bool($custom_match)) {
                if ($custom_match) {
                    return true;
                }
                continue;
            }

            if ($type === 'path') {
                $normalized_path = untrailingslashit('/' . ltrim($request_path, '/'));
                $normalized_rule = untrailingslashit('/' . ltrim($value, '/'));
                if ($normalized_rule === '') {
                    $normalized_rule = '/';
                }
                if ($normalized_path === $normalized_rule || strpos($normalized_path . '/', $normalized_rule . '/') === 0) {
                    return true;
                }
                continue;
            }

            if (is_singular()) {
                $post_id = (int) get_queried_object_id();
                if ($type === 'page' && is_page() && ((int) $value === $post_id)) {
                    return true;
                }
                if ($type === 'post' && is_single() && ((int) $value === $post_id)) {
                    return true;
                }
                if ($type === 'category' && has_category($value, $post_id)) {
                    return true;
                }
                if ($type === 'tag' && has_tag($value, $post_id)) {
                    return true;
                }
            }

            if ($type === 'category' && is_category($value)) {
                return true;
            }
            if ($type === 'tag' && is_tag($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve current request path in a frontend-safe way.
     */
    private function get_companion_request_path() {
        $raw = sanitize_text_field((string) wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        $path = (string) wp_parse_url($raw, PHP_URL_PATH);
        if ($path === '') {
            $path = '/';
        }
        return '/' . ltrim($path, '/');
    }

}
