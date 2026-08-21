<?php
if (!defined('ABSPATH')) {
    exit;
}

$flosc_visitor_name = function_exists( 'flosc_visitor_assistant_name' )
    ? flosc_visitor_assistant_name()
    : (string) ( $identity['name'] ?? 'FLOSC' );
if ( $flosc_visitor_name === '' ) {
    $flosc_visitor_name = 'FLOSC';
}

// v9.0.8: Chat styling data attributes (font, theme, preset, scale)
$flosc_chat_font   = get_option('flosc_chat_style_font', 'system');
$flosc_chat_theme  = get_option('flosc_chat_style_theme', 'default');
$flosc_chat_preset = get_option('flosc_chat_style_preset', 'flosc');
$flosc_chat_scale  = intval(get_option('flosc_chat_style_scale', 112)); // percent
?>
<!DOCTYPE html>
<?php /* ====  Source documentation — kept in the file for maintainers, NOT emitted to the browser. ====
================================================================================
FLOSC APP - MAIN CHAT APPLICATION TEMPLATE
================================================================================
Version: 1.7.7
Updated: 2026-02m-12d

TEACHABLE CODE PRINCIPLES:
This file demonstrates proper separation of concerns for a WordPress chat UI.
It is designed to be readable by both humans and AI assistants.

ARCHITECTURE OVERVIEW:
┌─────────────────────────────────────────────────────────────────────────────┐
│  flosc-app.php (this file) = HTML STRUCTURE ONLY                            │
│  ├── Semantic HTML elements with meaningful class names                     │
│  ├── Accessibility attributes (aria-label, title, role)                     │
│  ├── Data attributes for JavaScript hooks (id="flosc_*")                    │
│  └── NO inline styles except CSS custom properties (variables)              │
├─────────────────────────────────────────────────────────────────────────────┤
│  flosc-chat.css     = CHAT INTERFACE (messages, prompts, chat layout)       │
│  flosc-offers.css   = OFFER PRESENTATION (in-chat and optional frontend)    │
│  flosc-frontend.css = NON-CHAT SERVED CONTENT (legal, landing, content)     │
│  flosc-access.css   = ACCESS/UNLOCK GATE UI                                  │
│  chat-style-*.css   = CHAT PRESET VARIABLE PACKS                             │
└─────────────────────────────────────────────────────────────────────────────┘

⚠️  HARD-LEARNED LESSON (2026-01m-25d):
    AI tools repeatedly broke icon visibility by adding incorrect CSS.
    After 6+ failed attempts and hours of debugging, the fix was simple:
    SVG icons MUST have stroke="currentColor" AND the parent button
    MUST have a defined color value (not just "inherit").
    
    The SVG icon pattern that WORKS:
    <svg ... fill="none" stroke="currentColor" stroke-width="2">
    
    The CSS pattern that WORKS:
    .button { color: #667; }            -- Explicit color, not inherit
    .button svg { display: block; }     -- Prevents inline whitespace issues
    .button:hover { color: #333; }      -- Hover state changes icon color too

ICON & BUTTON CHECKLIST (verify all work before deployment):
□ Restart chat button - icon visible, spins on click
□ Sidebar toggle (X) - icon visible, hover state works
□ New chat button (+) - icon visible, hover darkens
□ Voice record button (mic) - icon visible, turns red when recording
□ Send message button (arrow) - icon visible, hover lightens
□ Share button - icon visible
□ Close modal buttons (X) - all modals
□ Recording controls (circle, square) - quiz audio panel

================================================================================
==== end source documentation ==== */ ?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($flosc_visitor_name); ?><?php echo !empty($identity['title']) ? ' - ' . esc_html($identity['title']) : ''; ?></title>
    
    <!-- v8.0.0: Favicon — per-flow favicon_url, falls back to bundled default.
         Generic unsized <link rel="icon"> listed FIRST for Safari compatibility
         (Safari ignores sized icon links in some versions). -->
    <?php
    remove_action('wp_head', 'wp_site_icon', 99);
    $flosc_favicon_generic = function_exists('flosc_get_favicon_url') ? flosc_get_favicon_url()     : '';
    $flosc_favicon_32      = function_exists('flosc_get_favicon_url') ? flosc_get_favicon_url('32')  : '';
    $flosc_favicon_180     = function_exists('flosc_get_favicon_url') ? flosc_get_favicon_url('180') : '';
    if (!$flosc_favicon_generic) $flosc_favicon_generic = FLOSC_PLUGIN_URL . 'assets/img/flosc-icon.png';
    if (!$flosc_favicon_32)      $flosc_favicon_32      = FLOSC_PLUGIN_URL . 'assets/img/flosc-icon-32.png';
    if (!$flosc_favicon_180)     $flosc_favicon_180     = FLOSC_PLUGIN_URL . 'assets/img/flosc-icon-180.png';
    ?>
    <link rel="icon" href="<?php echo esc_url($flosc_favicon_generic); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url($flosc_favicon_32); ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url($flosc_favicon_180); ?>">

    <?php
    // Companion embed: FOUC guard via wp_add_inline_style on flosc-layout (same handle as
    // theme vars below). Hides chrome and caps logos when ?flosc_companion is present.
    $flosc_is_companion_embed = ( null !== filter_input( INPUT_GET, 'flosc_companion' ) );
    if ( $flosc_is_companion_embed ) {
        $flosc_companion_critical_css = '
body.flosc-companion-embed .flosc-sidebar,
body.flosc-companion-embed .flosc-sidebar-overlay,
body.flosc-companion-embed #flosc_app_sidebar_toggle,
body.flosc-companion-embed .flosc-header,
body.flosc-companion-embed .mobile-menu-btn,
body.flosc-companion-embed .logo-mobile,
body.flosc-companion-embed .landing-state {
	display: none !important;
}
body.flosc-companion-embed img.logo-img,
body.flosc-companion-embed img.logo-mobile__img,
body.flosc-companion-embed img.landing-icon {
	width: 32px !important;
	height: 32px !important;
	max-width: 32px !important;
	max-height: 32px !important;
	object-fit: contain !important;
}
';
        wp_add_inline_style( 'flosc-layout', $flosc_companion_critical_css );
    }
    ?>
    
    <!-- Dynamic Primary Color -->
    <?php // §12: dynamic CSS vars attached to the enqueued flosc-chat handle (prints in <head> via wp_head) instead of an inline <style> tag. ?>
    <?php ob_start(); ?>
        :root {
            --flosc-primary: <?php echo esc_attr($identity['primary_color']); ?>;
            --flosc-primary-hover: <?php echo esc_attr(flosc_adjust_brightness($identity['primary_color'], -20)); ?>;
            --flosc-primary-light: <?php echo esc_attr($identity['primary_color']); ?>15;
            --flosc-scale: <?php echo esc_attr($flosc_chat_scale); ?>%;
            // v1.8.3+: Avatar shape defaults from profile-bar visitor state.
            <?php
            $flosc_profile_bar_for_avatar = get_option('flosc_profile_bar', []);
            $flosc_avatar_radius = sanitize_text_field((string) ($flosc_profile_bar_for_avatar['visitor']['avatar_radius'] ?? '8px'));
            if (!in_array($flosc_avatar_radius, ['8px', '50%', '4px', '0'], true)) {
                $flosc_avatar_radius = '8px';
            }
            ?>
            --flosc-avatar-radius: <?php echo esc_attr($flosc_avatar_radius); ?>;
        }
    <?php // App enqueues flosc-layout (not flosc-chat); attach vars to the live handle. ?>
    <?php wp_add_inline_style('flosc-layout', ob_get_clean()); ?>

    <!-- Markdown parser fallback for IVR rendering when a parser is not already loaded. -->
    <?php // §12: attached to flosc-app as a 'before' inline script so it still defines window.marked prior to flosc-app.js. ?>
    <?php ob_start(); ?>
        window.marked = window.marked || {
            parse: function(text) {
                return String(text || '');
            }
        };
    <?php wp_add_inline_script('flosc-app', ob_get_clean(), 'before'); ?>

    <?php wp_head(); ?>
</head>
<?php
// Determine if funnel is completed (for conditional rendering)
$flosc_flow_completed = is_user_logged_in() && get_user_meta(get_current_user_id(), '_flosc_funnel_completed', true);
?>
<?php
// $flosc_is_companion_embed set in <head> (critical CSS). Presence-only flag.
$flosc_body_classes = 'flosc-app';
if ( is_admin_bar_showing() ) {
	$flosc_body_classes .= ' admin-bar';
}
if ( ! empty( $flosc_is_companion_embed ) ) {
	$flosc_body_classes .= ' flosc-companion-embed';
}
?>
<body class="<?php echo esc_attr( $flosc_body_classes ); ?>"
      data-user-state="<?php echo esc_attr($user_state); ?>"
      data-flosc-font="<?php echo esc_attr($flosc_chat_font); ?>"
    data-flosc-theme="<?php echo esc_attr($flosc_chat_theme); ?>"
        data-flosc-style-preset="<?php echo esc_attr($flosc_chat_preset); ?>">

    <!-- Sidebar -->
    <!--
    ============================================================================
    SIDEBAR ICONS - CRITICAL IMPLEMENTATION NOTES (2026-01m-30d)
    ============================================================================
    AI previously broke these icons multiple times. Here's what MUST be true:
    
    1. SVG ATTRIBUTES (in HTML):
       - fill="none" (icons are stroked, not filled)
       - stroke="currentColor" (inherits color from parent)
       - stroke-width="2" (consistent line weight)
       - Explicit width/height attributes
    
    2. BUTTON CSS (in active frontend/chat stylesheets):
       - Parent button MUST have explicit color (not just inherit)
       - SVG must have display: block (prevents inline spacing bugs)
       - Hover states must change color (which SVG inherits)
    
    3. ANIMATIONS (in active frontend/chat stylesheets):
       - .spinning class triggers rotation animation
       - @keyframes spin defined once, used via class toggle
       - JavaScript adds/removes .spinning class
    
    DO NOT:
    ✗ Add stroke colors directly to SVG paths
    ✗ Use fill for line-art icons
    ✗ Rely on color: inherit without an ancestor defining color
    ✗ Add inline styles to fix visibility (extract to CSS instead)
    ============================================================================
    -->
    <aside class="flosc-sidebar" id="flosc_app_sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="<?php echo esc_url(flosc_get_chatlogo_url()); ?>" alt="<?php echo esc_attr($flosc_visitor_name); ?>" class="logo-img" width="32" height="32" decoding="async">
                <span class="logo-text"><?php echo esc_html($flosc_visitor_name); ?></span>
            </div>
            <div class="sidebar-header-actions">
                <button class="sidebar-action-btn" id="flosc_app_restart_chat" title="Restart chat" aria-label="Restart chat">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                        <path d="M21 3v5h-5"></path>
                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                        <path d="M3 21v-5h5"></path>
                    </svg>
                </button>
                <button class="flosc_app_sidebar_toggle" id="flosc_app_sidebar_toggle" aria-label="Toggle sidebar">
                    <!-- v1.8.1: Sidebar panel collapse icon (like ChatGPT/Claude) instead of X -->
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                </button>
            </div>
        </div>

        <?php if (is_user_logged_in()): ?>
        <?php
        $flosc_new_chat_btn_label = function_exists('flosc_get_setting')
            ? (string) flosc_get_setting('new_chat_button_label', 'New chat')
            : 'New chat';
        if ($flosc_new_chat_btn_label === '') {
            $flosc_new_chat_btn_label = 'New chat';
        }
        ?>
        <button class="new-chat-btn" id="flosc_app_new_session_button">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span><?php echo esc_html($flosc_new_chat_btn_label); ?></span>
        </button>

        <!-- Session history -->
        <div class="flosc_app_session_list" id="flosc_app_session_list"></div>
        <?php endif; ?>

        <!-- User Profile Bar — one component, 3 states: visitor | guest | member -->
        <?php
        $flosc_user = is_user_logged_in() ? wp_get_current_user() : null;
        $flosc_profile_bar = get_option('flosc_profile_bar', []);
        $flosc_pb_visitor = wp_parse_args($flosc_profile_bar['visitor'] ?? [], [
            'name'  => 'Visitor',
            'badge' => 'Hope you enjoy our chat :-)',
            'icon'  => '👋',
            'icon_url' => '',
            'show_upgrade' => true,
            'upgrade_label' => 'Upgrade',
        ]);
        $flosc_pb_guest = wp_parse_args($flosc_profile_bar['guest'] ?? [], [
            'name'          => '',
            'badge'         => 'Guest',
            'icon'          => '',
            'icon_url'      => '',
            'avatar_radius' => '8px',
            'show_upgrade'  => true,
            'upgrade_label' => 'Upgrade to Pro',
        ]);
        $flosc_pb_member = wp_parse_args($flosc_profile_bar['member'] ?? [], [
            'name'          => '',
            'badge'         => 'Member',
            'icon'          => '',
            'icon_url'      => '',
            'avatar_radius' => '8px',
            'show_upgrade'  => false,
            'upgrade_label' => 'Upgrade',
        ]);

        foreach (['visitor', 'guest', 'member'] as $flosc_pb_state) {
            if (!isset($flosc_profile_bar[$flosc_pb_state]['avatar_radius']) || !in_array((string) $flosc_profile_bar[$flosc_pb_state]['avatar_radius'], ['8px', '50%', '4px', '0'], true)) {
                if ($flosc_pb_state === 'visitor') {
                    $flosc_pb_visitor['avatar_radius'] = '8px';
                }
                if ($flosc_pb_state === 'guest') {
                    $flosc_pb_guest['avatar_radius'] = '8px';
                }
                if ($flosc_pb_state === 'member') {
                    $flosc_pb_member['avatar_radius'] = '8px';
                }
            }
        }

        // Render visitor label with token count only at first paint.
        // JS also normalizes this on init, but server-side output guarantees
        // correctness on refresh even before any client logic runs.
        $flosc_tokens_per_message = max(1, intval(get_option('flosc_tokens_communication_tokens_per_message', 5000)));
        $flosc_economics = [];
        if (class_exists('FLOSC_Sale_Manager')) {
            $flosc_token_provider = FLOSC_Sale_Manager::instance()->get_provider('tokens');
            if ($flosc_token_provider && method_exists($flosc_token_provider, 'get_communication_economics')) {
                $flosc_economics = (array) $flosc_token_provider->get_communication_economics();
                $flosc_tokens_per_message = max(1, intval($flosc_economics['tokens_per_message'] ?? $flosc_tokens_per_message));
            }
        }
        $flosc_visitor_label_base = trim((string) ($flosc_pb_visitor['name'] ?? 'Visitor'));
        $flosc_visitor_label_base = preg_replace('/\s*\(?\d+[kmb]?\)?$/i', '', $flosc_visitor_label_base);
        $flosc_visitor_label_base = trim($flosc_visitor_label_base);
        if ($flosc_visitor_label_base === '') {
            $flosc_visitor_label_base = 'Visitor';
        }

        $flosc_tokens_per_message_display = number_format_i18n($flosc_tokens_per_message);
        $flosc_visitor_wallet_initial = isset($flosc_current_flow['tokens_communication_tokens_per_message'])
            ? max(1, intval($flosc_current_flow['tokens_communication_tokens_per_message']))
            : $flosc_tokens_per_message;
        $flosc_visitor_wallet_initial_display = number_format_i18n($flosc_visitor_wallet_initial);
        // Defined here for FLOSC_CONFIG visitorTokenDisplay (was previously undefined).
        $flosc_real_millicents_per_message = max(0, intval($flosc_economics['real_millicents_per_message'] ?? 0));

        // v1.8.2: Dynamic visitor menu — indexed array of [label, action] pairs
        $flosc_visitor_menu_raw = get_option('flosc_visitor_menu_items', []);
        $flosc_visitor_menu = [];
        if (!empty($flosc_visitor_menu_raw)) {
            // Backward-compat: convert old associative format
            if (isset($flosc_visitor_menu_raw['signup']) || isset($flosc_visitor_menu_raw['login']) || isset($flosc_visitor_menu_raw['quiz'])) {
                $flosc_action_map = ['signup' => 'open_registration', 'login' => 'login', 'quiz' => 'open_quiz'];
                foreach ($flosc_visitor_menu_raw as $flosc_key => $flosc_item) {
                    if (!empty($flosc_item['enabled'])) {
                        $flosc_visitor_menu[] = ['label' => $flosc_item['label'] ?? ucfirst($flosc_key), 'action' => $flosc_action_map[$flosc_key] ?? $flosc_key];
                    }
                }
            } else {
                $flosc_visitor_menu = $flosc_visitor_menu_raw;
            }
        }
        if (empty($flosc_visitor_menu)) {
            $flosc_visitor_menu = [
                ['label' => 'Sign Up',   'action' => 'open_registration'],
                ['label' => 'Log In',    'action' => 'login'],
                ['label' => 'Take Quiz', 'action' => 'open_quiz'],
            ];
        }
        $flosc_profile_url = ($flosc_user && function_exists('bp_core_get_user_domain'))
            ? bp_core_get_user_domain($flosc_user->ID)
            : admin_url('profile.php');
        ?>
        <div class="user-profile-bar" id="flosc_user_profile_bar"
             data-visitor-avatar-radius="<?php echo esc_attr($flosc_pb_visitor['avatar_radius'] ?? '8px'); ?>"
             data-visitor-icon-url="<?php echo esc_attr($flosc_pb_visitor['icon_url'] ?? ''); ?>"
             data-visitor-upgrade-show="<?php echo !empty($flosc_pb_visitor['show_upgrade']) ? '1' : '0'; ?>"
             data-visitor-upgrade-label="<?php echo esc_attr($flosc_pb_visitor['upgrade_label'] ?? 'Upgrade'); ?>"
             data-guest-badge="<?php echo esc_attr($flosc_pb_guest['badge']); ?>"
             data-guest-name="<?php echo esc_attr($flosc_pb_guest['name']); ?>"
             data-guest-icon="<?php echo esc_attr($flosc_pb_guest['icon']); ?>"
             data-guest-icon-url="<?php echo esc_attr($flosc_pb_guest['icon_url']); ?>"
             data-guest-avatar-radius="<?php echo esc_attr($flosc_pb_guest['avatar_radius'] ?? '8px'); ?>"
             data-guest-upgrade-show="<?php echo !empty($flosc_pb_guest['show_upgrade']) ? '1' : '0'; ?>"
             data-guest-upgrade-label="<?php echo esc_attr($flosc_pb_guest['upgrade_label'] ?? 'Upgrade to Pro'); ?>"
             data-member-badge="<?php echo esc_attr($flosc_pb_member['badge']); ?>"
             data-member-name="<?php echo esc_attr($flosc_pb_member['name']); ?>"
             data-member-icon="<?php echo esc_attr($flosc_pb_member['icon']); ?>"
             data-member-icon-url="<?php echo esc_attr($flosc_pb_member['icon_url']); ?>"
             data-member-avatar-radius="<?php echo esc_attr($flosc_pb_member['avatar_radius'] ?? '8px'); ?>"
             data-member-upgrade-show="<?php echo !empty($flosc_pb_member['show_upgrade']) ? '1' : '0'; ?>"
             data-member-upgrade-label="<?php echo esc_attr($flosc_pb_member['upgrade_label'] ?? 'Upgrade'); ?>"
             data-upgrade-label="<?php echo esc_attr($flosc_pb_guest['upgrade_label']); ?>">
            <button class="profile-button" id="flosc_profile_button">
                <?php
                // Server-side flosc-hidden so wrong-branch nodes never paint.
                // CSS alone failed: .flosc-app .user-profile-bar img.flosc-profile-avatar
                // {display:block} beat [data-show="logged-in"]{display:none} → 👋 + blue square.
                $flosc_is_visitor          = ($user_state === 'visitor');
                $flosc_pb_visitor_hidden   = $flosc_is_visitor ? '' : ' flosc-hidden';
                $flosc_pb_logged_in_hidden = $flosc_is_visitor ? ' flosc-hidden' : '';
                // Icon XOR image: both stay hidden until setupUI setDisplayState picks one.
                $flosc_pb_avatar_pending   = ' flosc-hidden';
                ?>
                <!-- Visitor state: emoji fallback always present; optional image overlays when URL loads -->
                <div class="flosc-profile-avatar profile-avatar visitor-avatar<?php echo esc_attr($flosc_pb_visitor_hidden); ?>" data-show="visitor">
                    <span class="flosc-visitor-avatar-fallback" aria-hidden="true"><?php
                        echo esc_html(($flosc_pb_visitor['icon'] !== '' && $flosc_pb_visitor['icon'] !== null)
                            ? $flosc_pb_visitor['icon']
                            : '👋');
                    ?></span>
                    <?php if (!empty($flosc_pb_visitor['icon_url'])): ?>
                        <img src="<?php echo esc_url($flosc_pb_visitor['icon_url']); ?>"
                             alt=""
                             class="flosc-visitor-avatar-img flosc-profile-avatar"
                             data-flosc-visitor-avatar-img="1">
                    <?php endif; ?>
                </div>
                <div class="flosc-profile-avatar profile-avatar flosc-profile-avatar-icon<?php echo esc_attr($flosc_pb_avatar_pending); ?>" id="flosc_profile_avatar_icon" data-show="logged-in"></div>
                <!-- Guest/Member avatar: no empty src (empty src + accent bg = solid blue tile). -->
                <img alt="" class="flosc-profile-avatar profile-avatar<?php echo esc_attr($flosc_pb_avatar_pending); ?>" id="flosc_profile_avatar" data-show="logged-in">
                <div class="profile-info">
                    <div class="profile-name<?php echo esc_attr($flosc_pb_visitor_hidden); ?>" data-show="visitor">
                        <span class="flosc-visitor-label-text"><?php echo esc_html($flosc_visitor_label_base); ?></span>
                        <span class="flosc-visitor-token-count" id="flosc_visitor_token_count">(<?php echo esc_html($flosc_visitor_wallet_initial_display); ?>)</span>
                    </div>
                    <div class="profile-name<?php echo esc_attr($flosc_pb_logged_in_hidden); ?>" id="flosc_profile_name" data-show="logged-in"></div>
                    <div class="profile-badge<?php echo esc_attr($flosc_pb_visitor_hidden); ?>" data-show="visitor"><?php echo esc_html($flosc_pb_visitor['badge']); ?></div>
                    <div class="profile-badge<?php echo esc_attr($flosc_pb_logged_in_hidden); ?>" id="flosc_profile_badge" data-show="logged-in"></div>
                </div>
                <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div class="profile-dropdown" id="flosc_profile_dropdown">
                <!-- Visitor dropdown items — v1.8.2 dynamic menu -->
                <div class="profile-dropdown-group" data-show="visitor">
                    <?php foreach ($flosc_visitor_menu as $flosc_item):
                        $flosc_is_offer = (
                            strpos( (string) $flosc_item['action'], 'show_offer' ) === 0
                            || (string) $flosc_item['action'] === 'show_upgrade'
                            || (string) $flosc_item['action'] === 'open_sandbox_purchase'
                        );
                    ?>
                    <?php if ($flosc_is_offer): ?>
                    <?php if (!empty($flosc_pb_visitor['show_upgrade'])): ?>
                    <div class="upgrade-container">
                        <button class="upgrade-btn" data-action="<?php echo esc_attr($flosc_item['action']); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                            <?php echo esc_html($flosc_pb_visitor['upgrade_label'] ?: $flosc_item['label']); ?>
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="#" class="profile-dropdown-item" data-action="<?php echo esc_attr($flosc_item['action']); ?>">
                        <?php echo esc_html($flosc_item['label']); ?>
                    </a>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Guest/Member dropdown items -->
                <div class="profile-dropdown-group" data-show="logged-in">
                    <div class="profile-dropdown-header">
                        <div class="dropdown-name" id="flosc_dropdown_name"></div>
                        <div class="dropdown-email" id="flosc_dropdown_email"></div>
                    </div>
                    <!-- Upgrade: flosc feature button (guest; member only if profile-bar show_upgrade). Not a menu row. -->
                    <div class="upgrade-container" id="flosc_upgrade_container">
                        <button type="button" class="upgrade-btn" id="flosc_upgrade_button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                            <span id="flosc_upgrade_button_label"><?php echo esc_html($flosc_pb_guest['upgrade_label']); ?></span>
                        </button>
                    </div>
                    <?php
                    // Read admin-configured menu for the current user state
                    $flosc_li_menu = ($user_state === 'member')
                        ? get_option('flosc_member_menu_items', [])
                        : get_option('flosc_guest_menu_items', []);
                    if (empty($flosc_li_menu)) {
                        $flosc_li_menu = ($user_state === 'member')
                            ? [['label' => 'Log Out', 'action' => 'logout']]
                            : [
                                ['label' => 'My Profile', 'action' => 'view_profile'],
                                ['label' => 'Log Out', 'action' => 'logout'],
                            ];
                    }
                    foreach ($flosc_li_menu as $flosc_li_item):
                        $flosc_li_action = (string) ($flosc_li_item['action'] ?? '');
                        // Purchase/offer lives on the Upgrade feature button, not as a plain menu link.
                        if (
                            $flosc_li_action === 'open_sandbox_purchase'
                            || strpos($flosc_li_action, 'show_offer') === 0
                        ) {
                            continue;
                        }
                    ?>
                    <a href="#" class="profile-dropdown-item" data-action="<?php echo esc_attr($flosc_li_action); ?>">
                        <?php echo esc_html($flosc_li_item['label'] ?? ''); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flosc-main">
        <!-- Header -->
        <header class="flosc-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="flosc_app_mobile_menu_button" aria-label="Open sidebar">
                    <!-- v1.8.1: Sidebar panel icon matches the collapse button in sidebar header -->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="9" y1="3" x2="9" y2="21"></line>
                    </svg>
                </button>
                <div class="logo-mobile">
                    <img src="<?php echo esc_url(flosc_get_chatlogo_url()); ?>" alt="" class="logo-mobile__img" width="28" height="28" decoding="async">
                    <?php echo esc_html($flosc_visitor_name); ?>
                </div>
            </div>

            <div class="header-right" id="flosc_app_header_right">
                <?php if ( ! $flosc_is_companion_embed ) : ?>
                <button class="flosc-dock-btn flosc-dock-btn--icon" id="flosc_app_dock_companion_button" aria-label="<?php echo esc_attr__('Minimize to companion', 'flosc'); ?>" title="<?php echo esc_attr__('Minimize to companion', 'flosc'); ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="7" y1="7" x2="17" y2="17"></line>
                        <polyline points="11 17 17 17 17 11"></polyline>
                    </svg>
                </button>
                <?php endif; ?>

                <!-- Visitor: auth buttons -->
                <div class="auth-buttons" id="flosc_app_auth_buttons">
                    <?php
                    $flosc_login_action = flosc_get_setting('header_login_action', 'open_login_modal');
                    $flosc_signup_action = flosc_get_setting('header_signup_action', 'open_login_modal');
                    ?>
                    <a href="#" class="btn-secondary" data-flosc-action="perform-ivr-action" data-ivr-action="<?php echo esc_attr($flosc_login_action); ?>"><?php echo esc_html(flosc_get_setting('header_login_text', 'Log in')); ?></a>
                    <a href="#" class="btn-primary" data-flosc-action="perform-ivr-action" data-ivr-action="<?php echo esc_attr($flosc_signup_action); ?>"><?php echo esc_html(flosc_get_setting('header_signup_text', 'Sign up')); ?></a>
                </div>

                <!-- Logged in: share button -->
                <button class="flosc-share-btn" id="flosc_app_share_button">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                    <span>Share</span>
                </button>
            </div>
        </header>

        <!-- Chat container -->
        <div class="chat-container" id="flosc_chat_container">
            <?php if ( empty( $flosc_is_companion_embed ) ) : ?>
            <!-- Landing state: compact Grok-style centered identity (full-page only; not in companion iframe). -->
            <div class="landing-state" id="landingState">
                <div class="landing-header">
                    <?php
                    // v1.9.7: Landing state uses logo. Falls back to FLOSC default icon.
                    $flosc_landing_logo = function_exists('flosc_get_chatlogo_url') ? flosc_get_chatlogo_url() : (FLOSC_PLUGIN_URL . 'assets/img/flosc-icon.png');
                    ?>
                        <img src="<?php echo esc_url($flosc_landing_logo); ?>" alt="" class="landing-icon" width="36" height="36" decoding="async">
                    <span class="landing-title"><?php echo esc_html($flosc_visitor_name); ?></span>
                    <?php if (!empty($identity['title'])): ?>
                        <span class="landing-subtitle"><?php echo esc_html($identity['title']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($identity['tagline'])): ?>
                    <div class="landing-tagline"><?php echo esc_html($identity['tagline']); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- v1.8.0: Old visitor engagement bar removed. Profile bar (sidebar bottom) now handles
                 all visitor/guest/member states. Configured via FLOSC → UI & Navigation. -->

            <!-- v07.09: User autoprompts are now dynamically rendered by flosc-app.js based on ivr.md configuration -->
            <!-- No static HTML needed - the JavaScript IVR engine handles all reply rendering -->
            <div class="messages" id="flosc_output_chat_responses">
                <?php if (is_user_logged_in()): ?>
                <!-- Greeting for logged in users only -->
                <div class="greeting flosc-hidden" id="greeting">
                    <h2 class="greeting-title" id="greetingTitle"></h2>
                </div>
                <?php endif; ?>
            </div>

            <!-- User autoprompts container (dynamically populated by flosc-app.js) -->
            <div class="flosc-user-autoprompts-container" id="flosc_input_user_autoprompts_panel"></div>

            <!-- Typing indicator -->
            <div class="typing-indicator" id="flosc_output_chat_typing_indicator">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Composer -->
        <div class="flosc_input_composer" id="flosc_input_composer">
            <div class="flosc_input_composer_inner">
                <textarea
                    id="flosc_input_chat_field"
                    placeholder="Message <?php echo esc_attr($flosc_visitor_name); ?>..."
                    rows="1"
                    aria-label="Message input"
                ></textarea>
                <button class="flosc_input_chat_send_button" id="flosc_input_chat_send_button" aria-label="Send message">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
        <?php // Companion-embed only: Name (V|G|M) · TokenCount · ExpandSubMenuCaret under the input. ?>
        <div id="flosc_companion_session_status"
             class="flosc-companion-session-status flosc-companion-profile-row"
             hidden>
            <div class="flosc-companion-profile-menu" id="flosc_companion_profile_menu" hidden role="menu"></div>
            <button type="button"
                    class="flosc-companion-profile-toggle"
                    id="flosc_companion_profile_toggle"
                    aria-expanded="false"
                    aria-haspopup="menu"
                    aria-controls="flosc_companion_profile_menu">
                <span class="flosc-companion-session-identity">
                    <span class="flosc-companion-session-user" id="flosc_companion_session_user"></span>
                    <span class="flosc-companion-session-tier" id="flosc_companion_session_tier" aria-hidden="true"></span>
                </span>
                <span class="flosc-companion-session-tokens" id="flosc_companion_session_tokens" data-flosc-token-balance="1" aria-live="polite"></span>
                <svg class="flosc-companion-profile-caret" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
        </div>
    </main>

    <!-- Quiz Modal (v9.3.3 - supports text AND audio input) -->
    <div class="flosc-modal-overlay" id="flosc_modal_recording">
        <div class="flosc-modal flosc-recording-modal">
            <div class="flosc-modal-header">
                <h3>🎯 Quick Quiz</h3>
                <button class="flosc-modal-close" id="floscQuizModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="flosc-modal-body">
                <?php
                // First enabled quiz only — empty list = no quiz UI content (never invent sample IDs).
                $flosc_enabled_quizzes = flosc_get_setting( 'enabled_quizzes', [] );
                if ( ! is_array( $flosc_enabled_quizzes ) ) {
                    $flosc_enabled_quizzes = [];
                }
                $flosc_quiz_id = ! empty( $flosc_enabled_quizzes ) ? sanitize_key( (string) $flosc_enabled_quizzes[0] ) : '';
                $flosc_quiz_content = $flosc_quiz_id !== ''
                    ? (string) flosc_get_setting( 'quiz_content_' . $flosc_quiz_id, '' )
                    : '';
                $flosc_items = $flosc_quiz_content !== ''
                    ? array_map( 'trim', explode( ',', $flosc_quiz_content ) )
                    : [];
                $flosc_items_display = implode( ', ', $flosc_items );
                ?>
                
                <!-- Quiz Prompt -->
                <div class="quiz-prompt">
                    <?php if ( $flosc_quiz_id === '' ) : ?>
                    <p class="quiz-prompt-label"><?php echo esc_html__( 'No quiz is enabled for this flow.', 'flosc' ); ?></p>
                    <p class="quiz-sequence" id="floscQuizSequence"></p>
                    <?php else : ?>
                    <p class="quiz-prompt-label">Repeat this sequence:</p>
                    <p class="quiz-sequence" id="floscQuizSequence">
                        <?php echo esc_html($flosc_items_display); ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Quiz Tabs Zone -->
                <div class="quiz-tabs">
                    <button type="button" class="quiz-tab-btn active" data-tab="text">
                        ⌨️ Type
                    </button>
                    <button type="button" class="quiz-tab-btn" data-tab="audio">
                        🎤 Speak
                    </button>
                </div>
                
                <!-- Quiz Text Input Panel -->
                <div class="quiz-panel" id="floscQuizTextPanel">
                    <input type="text" 
                           id="floscQuizTextInput"
                           class="quiz-text-input"
                           placeholder="Type the numbers (e.g., 1, 2, 3...)" 
                           autocomplete="off">
                    <button type="button" 
                            id="floscQuizSubmitTextButton" 
                            class="quiz-submit-btn">
                        Submit Answer →
                    </button>
                </div>
                
                <!-- Quiz Audio Panel (hidden by default) -->
                <div class="quiz-panel quiz-audio-panel flosc-hidden" id="floscQuizAudioPanel">
                    <div class="quiz-waveform" id="floscQuizWaveformContainer">
                        <canvas id="waveformCanvas" width="280" height="60"></canvas>
                    </div>
                    
                    <div class="quiz-timer" id="floscQuizRecordingTimer">0:00</div>
                    
                    <div class="quiz-recording-controls">
                        <button class="quiz-record-btn" id="floscQuizRecordButton">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <circle cx="12" cy="12" r="8"></circle>
                            </svg>
                            <span>Start Recording</span>
                        </button>
                        <button class="quiz-stop-btn flosc-hidden" id="floscQuizStopButton">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                            </svg>
                            <span>Stop</span>
                        </button>
                    </div>
                    
                    <div class="quiz-recording-status" id="floscQuizRecordingStatus"></div>
                    
                    <div class="quiz-playback flosc-hidden" id="floscQuizRecordingPlayback">
                        <audio id="floscQuizRecordingAudio" class="quiz-playback-audio" controls></audio>
                        <div class="quiz-playback-actions">
                            <button class="btn-secondary" id="floscQuizRerecordButton">Re-record</button>
                            <button class="quiz-submit-btn" id="floscQuizSubmitRecordingButton">Submit →</button>
                        </div>
                    </div>
                    
                    <div class="quiz-error flosc-hidden" id="floscQuizRecordingError">
                        <p>Microphone access denied. Please enable it in your browser settings.</p>
                    </div>
                </div>
                
                <!-- Quiz Result Panel (hidden by default) -->
                <div class="quiz-result-panel flosc-hidden" id="floscQuizResultPanel">
                    <div class="quiz-score-display" id="floscQuizScoreDisplay">0%</div>
                    <p class="quiz-result-message" id="floscQuizResultMessage">Calculating your score...</p>
                    <button type="button" id="floscQuizContinueButton" class="quiz-continue-btn">
                        Continue →
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="flosc-modal-overlay" id="flosc_modal_share">
        <div class="flosc-modal">
            <div class="flosc-modal-header">
                <h3>Share <?php echo esc_html($flosc_visitor_name); ?></h3>
                <button class="flosc-modal-close" id="shareModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="flosc-modal-body">
                <p class="flosc-share-text"><?php echo esc_html($identity['share_text'] ?? 'Check out this amazing app!'); ?></p>
                <div class="flosc-share-link-container">
                    <input type="text" id="shareLink" readonly class="flosc-share-link-input" value="">
                    <button class="flosc-copy-btn" id="copyBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span id="copyBtnText">Copy</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal (In-Chat Stripe/PayPal) -->
    <div class="flosc-modal-overlay" id="flosc_modal_payment">
        <div class="flosc-modal flosc-payment-modal">
            <div class="flosc-modal-header">
                <h3>Complete Your Purchase</h3>
                <button class="flosc-modal-close" id="paymentModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="flosc-modal-body">
                <!-- Pay UI: never destroyed by Access Code (toggle with #flosc-access-code-panel) -->
                <div id="flosc-payment-main">
                    <div class="flosc-payment-summary">
                        <div class="flosc-payment-product">
                            <?php if (!empty($identity['chatlogo_url'])): ?>
                                <img src="<?php echo esc_url($identity['chatlogo_url']); ?>" alt="" class="flosc-product-icon flosc-product-icon--image">
                            <?php endif; ?>
                            <div class="flosc-product-info">
                                <div class="flosc-product-name">Full Access</div>
                                <div class="flosc-product-desc">Unlimited access to all content</div>
                            </div>
                        </div>
                        <div class="flosc-payment-price" id="paymentPrice"></div>
                    </div>

                    <!-- v1.6.9: PayPal Button Container (rendered by PayPal JS SDK) -->
                    <div id="paypal-button-container" class="flosc-paypal-buttons flosc-paypal-buttons--spaced flosc-hidden"></div>

                    <!-- Separator between PayPal and Card (shown when both are available) -->
                    <div id="payment-separator" class="flosc-payment-separator flosc-hidden">
                        <span>or pay with card</span>
                    </div>

                    <div class="flosc-payment-form" id="stripe-payment-form">
                        <label for="card-element">Card details</label>
                        <div id="card-element" class="flosc-stripe-card-element">
                            <!-- Stripe Elements will mount here -->
                        </div>
                        <div id="card-errors" class="flosc-stripe-errors" role="alert"></div>
                    </div>

                    <button class="flosc-pay-btn" id="payBtn" disabled>
                        <span class="flosc-pay-btn-text">Pay</span>
                        <span class="flosc-pay-btn-spinner"></span>
                    </button>

                    <div class="flosc-payment-footer">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Secure payment</span>
                    </div>
                    <div id="flosc-coupon-row" class="flosc-coupon-row">
                        <label class="flosc-coupon-label" for="flosc-coupon-input">Coupon code</label>
                        <div class="flosc-coupon-fields">
                            <input type="text" id="flosc-coupon-input" class="flosc-coupon-input" maxlength="40" autocomplete="off" spellcheck="false" placeholder="e.g. 75SEP">
                            <button type="button" id="flosc-coupon-apply" class="flosc-coupon-apply">Apply</button>
                        </div>
                        <div id="flosc-coupon-status" class="flosc-coupon-status" aria-live="polite"></div>
                    </div>
                    <div id="flosc-access-code-trigger" class="flosc-access-code-trigger">
                        <a href="#" class="flosc-access-code-link" data-flosc-action="open-access-code-payment">Access Code</a>
                    </div>
                </div>
                <!-- Access Code step: same modal, does not replace pay DOM -->
                <div id="flosc-access-code-panel" class="flosc-access-code-panel flosc-hidden">
                    <button type="button" id="flosc-access-code-back" class="flosc-access-code-back">&larr; Back to payment</button>
                    <div class="flosc-access-code-title">Enter Access Code</div>
                    <input type="text" id="flosc-access-code-input" maxlength="20" autocomplete="off" spellcheck="false"
                           class="flosc-access-code-input" placeholder="">
                    <div class="flosc-access-code-actions">
                        <button type="button" id="flosc-access-code-submit" class="flosc-access-code-submit">Submit</button>
                    </div>
                    <div id="flosc-access-code-error" class="flosc-access-code-error"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Gate Modal (for visitors after 2 interactions) -->
    <div class="flosc-modal-overlay" id="flosc_modal_login_gate">
        <div class="flosc-modal">
            <div class="flosc-modal-header">
                <h3>Continue with <?php echo esc_html($flosc_visitor_name); ?></h3>
                <button class="flosc-modal-close" id="loginGateModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="flosc-modal-body flosc-login-gate-body">
                <p>Create a free account to save your progress and continue the conversation.</p>
                <div class="flosc-login-gate-buttons">
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary btn-large">Create Free Account</a>
                    <a href="<?php echo esc_url(wp_login_url(flosc()->get_app_url())); ?>" class="btn-secondary btn-large">Log In</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar overlay for mobile -->
    <div class="flosc-sidebar-overlay" id="flosc_app_sidebar_overlay"></div>

    <!-- Pass config and user data to JS -->
    <?php // §12: config built here and attached to flosc-app as a 'before' inline script, then wp_footer() runs below so flosc-app.js prints right after this config (it reads FLOSC_CONFIG on DOMContentLoaded). ?>
    <?php ob_start(); ?>
        <?php
        // v1.2.3: Get flow's IVR file for version tracking
        $flosc_current_flow = flosc()->get_current_flow();
        $flosc_ivr_filename = ($flosc_current_flow && !empty($flosc_current_flow['ivr_file'])) 
            ? $flosc_current_flow['ivr_file'] 
            : 'flosc_default_technical_ivr.md';
        $flosc_flow_id = $flosc_current_flow['id'] ?? '';

        // Flow bag runtime first, IVR file only as empty-bag fallback.
        $flosc_ivr_config = flosc_resolve_flow_runtime($flosc_flow_id, $flosc_ivr_filename);

        $flosc_ivr_file = flosc_config_file($flosc_ivr_filename);
        $flosc_ivr_version = file_exists($flosc_ivr_file) ? filemtime($flosc_ivr_file) : time();

            // v1.0.7: DEBUG - Check if messages loaded (only when FLOSC_DEBUG enabled)
            if (empty($flosc_ivr_config['messages'])) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC v1.5.0: WARNING - No IVR messages loaded from ' . $flosc_ivr_filename);
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC v1.5.0: Config keys: ' . implode(', ', array_keys($flosc_ivr_config)));
                }
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC v1.5.0: Runtime source was ' . ($flosc_ivr_config['source'] ?? 'unknown'));
                }
            } else {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC v1.5.0: IVR config loaded successfully. Messages: ' . count($flosc_ivr_config['messages']) . ' source=' . ($flosc_ivr_config['source'] ?? 'unknown'));
                }
            }
        ?>
        window.FLOSC_CONFIG = <?php 
            // v1.4.9: Get SSO providers from per-flow settings (not global options)
            $flosc_sso_providers = [];
            if (class_exists('\FLOSC\SSO\SSO_Manager')) {
                $flosc_sso_manager = \FLOSC\SSO\SSO_Manager::get_instance();
                $flosc_flow_id_for_sso = $flosc_current_flow ? ($flosc_current_flow['id'] ?? '') : '';
                
                // Load per-flow SSO settings
                $flosc_sso_flow_settings = [];
                if (!empty($flosc_flow_id_for_sso)) {
                    $flosc_sso_settings_key = 'flosc_flow_' . sanitize_key($flosc_flow_id_for_sso);
                    $flosc_sso_flow_settings = get_option($flosc_sso_settings_key, []);
                }
                
                // Check each registered provider against flow settings
                $flosc_sso_provider_ids = ['google', 'facebook', 'apple', 'microsoft', 'linkedin'];
                foreach ($flosc_sso_provider_ids as $flosc_pid) {
                    $flosc_flow_enabled = !empty($flosc_sso_flow_settings["sso_{$flosc_pid}_enabled"]);
                    $flosc_flow_client_id = $flosc_sso_flow_settings["sso_{$flosc_pid}_client_id"] ?? '';
                    $flosc_flow_client_secret = $flosc_sso_flow_settings["sso_{$flosc_pid}_client_secret"] ?? '';
                    
                    if ($flosc_flow_enabled && !empty($flosc_flow_client_id) && !empty($flosc_flow_client_secret)) {
                        $flosc_provider = $flosc_sso_manager->get_provider($flosc_pid);
                        if ($flosc_provider) {
                            // v1.7.5: SSO auth URL must use host domain for cookie consistency
                            if (defined('FLOSC_CUSTOM_DOMAIN_ACTIVE') && FLOSC_CUSTOM_DOMAIN_ACTIVE) {
                                $flosc_scheme = is_ssl() ? 'https://' : 'http://';
                                $flosc_current_host = '';
                                if (isset($_SERVER['HTTP_HOST'])) {
                                    $flosc_current_host = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']));
                                }
                                $flosc_rest_prefix = rest_get_url_prefix();
                                $flosc_auth_url = $flosc_scheme . $flosc_current_host . '/' . $flosc_rest_prefix . "/flosc/v1/sso/authorize/{$flosc_pid}";
                            } else {
                                $flosc_auth_url = rest_url("flosc/v1/sso/authorize/{$flosc_pid}");
                            }
                            // v1.4.9: Include flow_id so OAuth handler loads per-flow credentials
                            if (!empty($flosc_flow_id_for_sso)) {
                                $flosc_auth_url = add_query_arg('flow_id', rawurlencode($flosc_flow_id_for_sso), $flosc_auth_url);
                            }
                            $flosc_sso_providers[] = [
                                'id' => $flosc_pid,
                                'name' => $flosc_provider->get_name(),
                                'icon' => $flosc_provider->get_icon(),
                                'colors' => $flosc_provider->get_button_colors(),
                                'authUrl' => $flosc_auth_url,
                            ];
                        }
                    }
                }
            }
            
            // v1.4.9: Use flow-aware app URL for custom domain support
            $flosc_app_url = flosc()->get_app_url();
            
            // v1.7.5: REST API URL must use the SAME origin as the page
            // so cookies/nonce travel with the request. When on a custom domain
            // (flosc.ai), rest_url() returns the WordPress host which is cross-origin.
            $flosc_rest_base = rest_url('flosc/v1');
            if (defined('FLOSC_CUSTOM_DOMAIN_ACTIVE') && FLOSC_CUSTOM_DOMAIN_ACTIVE) {
                // Build REST URL on current domain so it's same-origin
                $flosc_scheme = is_ssl() ? 'https://' : 'http://';
                $flosc_current_host = '';
                if (isset($_SERVER['HTTP_HOST'])) {
                    $flosc_current_host = sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_HOST']));
                }
                $flosc_rest_prefix = rest_get_url_prefix(); // usually "wp-json"
                $flosc_rest_base = $flosc_scheme . $flosc_current_host . '/' . $flosc_rest_prefix . '/flosc/v1';
            }

            // Companion params: prefer $flow_settings option, fall back to current flow merge.
            $flosc_companion_source = is_array($flow_settings) ? $flow_settings : [];
            if (empty($flosc_companion_source) && !empty($flosc_current_flow) && is_array($flosc_current_flow)) {
                $flosc_companion_source = $flosc_current_flow;
            } elseif (!empty($flosc_current_flow) && is_array($flosc_current_flow)) {
                // Fill missing companion_* keys from live flow object.
                foreach ($flosc_current_flow as $flosc_ck => $flosc_cv) {
                    if (strpos((string) $flosc_ck, 'companion_') === 0 && !isset($flosc_companion_source[$flosc_ck])) {
                        $flosc_companion_source[$flosc_ck] = $flosc_cv;
                    }
                }
                foreach (['content_item_category', 'content_item_groups', 'slug', 'domain'] as $flosc_ck) {
                    if (!isset($flosc_companion_source[$flosc_ck]) && isset($flosc_current_flow[$flosc_ck])) {
                        $flosc_companion_source[$flosc_ck] = $flosc_current_flow[$flosc_ck];
                    }
                }
            }

            $flosc_companion_mode = sanitize_text_field((string) ($flosc_companion_source['companion_content_display_mode'] ?? 'in_chat'));
            $flosc_companion_enabled = !empty($flosc_companion_source['companion_enabled']);
            $flosc_companion_handoff_enabled = $flosc_companion_enabled && in_array($flosc_companion_mode, ['companion', 'both'], true);
            $flosc_companion_show_for_visitors = filter_var(
                $flosc_companion_source['companion_show_for_visitors'] ?? true,
                FILTER_VALIDATE_BOOLEAN
            );
            // Full-page chat always exposes companion collapse/return action (hidden when already embedded).
            $flosc_companion_return_available = ! $flosc_is_companion_embed;
            $flosc_companion_state_storage = sanitize_text_field((string) ($flosc_companion_source['companion_state_storage'] ?? 'session'));
            if (!in_array($flosc_companion_state_storage, ['session', 'local'], true)) {
                $flosc_companion_state_storage = 'session';
            }
            $flosc_companion_state_key = 'flosc_companion_state_' . md5((string) $flosc_app_url);
            $flosc_companion_routing_mode = sanitize_key((string) ($flosc_companion_source['companion_routing_mode'] ?? 'hub'));
            if (!in_array($flosc_companion_routing_mode, ['hub', 'domain_persistence'], true)) {
                $flosc_companion_routing_mode = 'hub';
            }
            // Hub URLs from floscAdmin Companion settings (defaults from flow params only).
            $flosc_hub_defaults_rt = function_exists('flosc_companion_hub_defaults_from_flow')
                ? flosc_companion_hub_defaults_from_flow($flosc_companion_source)
                : ['fullscreen' => (string) $flosc_app_url, 'companion' => home_url('/')];
            $flosc_hub_fullscreen_url = esc_url_raw((string) ($flosc_companion_source['companion_hub_fullscreen_url'] ?? ($flosc_hub_defaults_rt['fullscreen'] ?? $flosc_app_url)));
            if ($flosc_hub_fullscreen_url === '') {
                $flosc_hub_fullscreen_url = esc_url_raw((string) ($flosc_hub_defaults_rt['fullscreen'] ?? $flosc_app_url));
            }
            $flosc_hub_companion_url = esc_url_raw((string) ($flosc_companion_source['companion_hub_companion_url'] ?? ($flosc_hub_defaults_rt['companion'] ?? home_url('/'))));
            if ($flosc_hub_companion_url === '') {
                $flosc_hub_companion_url = esc_url_raw((string) ($flosc_hub_defaults_rt['companion'] ?? home_url('/')));
            }
            $flosc_companion_collapse_url = ($flosc_companion_routing_mode === 'hub')
                ? $flosc_hub_companion_url
                : esc_url_raw(home_url('/'));
            $flosc_companion_collapse_target_policy = ($flosc_companion_routing_mode === 'domain_persistence')
                ? 'origin'
                : 'fallback';
            $flosc_companion_contextual_prompt = sanitize_text_field((string) ($flosc_companion_source['companion_contextual_prompt'] ?? 'What do you want to explore together?'));
            // Profile-row tier codes (FLOSC defaults V/G/M) — per-flow overrideable.
            $flosc_tier_code = static function ( $raw, $fallback ) {
                $code = strtoupper( sanitize_text_field( (string) $raw ) );
                $code = preg_replace( '/[^A-Z0-9]/', '', $code );
                $code = substr( (string) $code, 0, 3 );
                return $code !== '' ? $code : $fallback;
            };
            $flosc_companion_tier_visitor = $flosc_tier_code( $flosc_companion_source['companion_profile_tier_visitor'] ?? 'V', 'V' );
            $flosc_companion_tier_guest   = $flosc_tier_code( $flosc_companion_source['companion_profile_tier_guest'] ?? 'G', 'G' );
            $flosc_companion_tier_member  = $flosc_tier_code( $flosc_companion_source['companion_profile_tier_member'] ?? 'M', 'M' );
            $flosc_companion_tier_visitor_label = sanitize_text_field( (string) ( $flosc_companion_source['companion_profile_tier_visitor_label'] ?? 'Visitor' ) );
            $flosc_companion_tier_guest_label   = sanitize_text_field( (string) ( $flosc_companion_source['companion_profile_tier_guest_label'] ?? 'Guest' ) );
            $flosc_companion_tier_member_label  = sanitize_text_field( (string) ( $flosc_companion_source['companion_profile_tier_member_label'] ?? 'Member' ) );
            if ( $flosc_companion_tier_visitor_label === '' ) {
                $flosc_companion_tier_visitor_label = 'Visitor';
            }
            if ( $flosc_companion_tier_guest_label === '' ) {
                $flosc_companion_tier_guest_label = 'Guest';
            }
            if ( $flosc_companion_tier_member_label === '' ) {
                $flosc_companion_tier_member_label = 'Member';
            }
            
            echo wp_json_encode([
            'restUrl' => $flosc_rest_base . '/',
            'apiUrl' => $flosc_rest_base,
            'nonce' => wp_create_nonce('wp_rest'),
            // Stripe publishable key from Payments tab (WPDB per-flow) — first-class, not disabled.
            'stripeKey' => (static function () {
                $stripe = FLOSC_Sale_Manager::instance()->get_provider('stripe');
                if (!$stripe || !method_exists($stripe, 'get_client_config')) {
                    return '';
                }
                // Only expose when Stripe is enabled for this flow (or legacy configured).
                if (method_exists($stripe, 'is_enabled') && !$stripe->is_enabled()) {
                    // Still allow if publishable key present (admin may enable per-offer only).
                }
                $cfg = $stripe->get_client_config();
                return (string) ($cfg['publishableKey'] ?? '');
            })(),
            // PayPal from Payments tab (WPDB) — includes currency for SDK/order alignment.
            'paypalClientId' => (function() {
                $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if ($pp && $pp->has_client_id()) {
                    $cfg = $pp->get_client_config();
                    return $cfg['clientId'] ?? '';
                }
                return '';
            })(),
            'paypalMode' => (function() {
                $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if ($pp) {
                    $cfg = $pp->get_client_config();
                    return $cfg['mode'] ?? 'sandbox';
                }
                return 'sandbox';
            })(),
            'paypalCurrency' => (function() {
                $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if ($pp) {
                    $cfg = $pp->get_client_config();
                    return $cfg['currency'] ?? 'USD';
                }
                return 'USD';
            })(),
            // Which PayPal JS SDK intent was enqueued (capture = one-time; subscription = plans).
            'paypalSdkIntent' => (static function () {
                $paypal = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if (!$paypal || !$paypal->has_client_id()) {
                    return '';
                }
                $flow = function_exists('flosc') ? flosc()->get_current_flow() : null;
                $stem = '';
                if (is_array($flow)) {
                    $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
                    $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                    if ($stem === '' && !empty($flow['id'])) {
                        $stem = sanitize_key((string) $flow['id']);
                    }
                }
                $offers = [];
                if ($stem !== '') {
                    $flosc_flow_settings = get_option('flosc_flow_' . $stem, []);
                    $offers = is_array($flosc_flow_settings['offers'] ?? null) ? $flosc_flow_settings['offers'] : [];
                }
                foreach ($offers as $o) {
                    if (!is_array($o)) {
                        continue;
                    }
                    $active = !empty($o['active']) || (($o['status'] ?? '') === 'active');
                    if (!$active) {
                        continue;
                    }
                    $flosc_type = strtolower((string) ($o['type'] ?? 'one_time'));
                    if ($flosc_type === 'subscription' || !empty($o['subscription']['plans'])) {
                        return 'subscription';
                    }
                }
                return 'capture';
            })(),
            'identity' => $identity,
            // Convenience alias for JS (logout goodbye, companion labels) — from Identity params.
            'productName' => $flosc_visitor_name,
            'personalityName' => $flosc_visitor_name,
            'flowDisplayName' => is_array($identity) ? (string) ($identity['name'] ?? '') : '',
            'offers' => array_values($offers),
            'appUrl' => $flosc_app_url,
            // Dock/collapse handoff when companion mode is companion|both and enabled.
            'companionHandoffEnabled' => ($flosc_companion_handoff_enabled && $flosc_companion_return_available),
            'companionCollapseUrl' => $flosc_companion_collapse_url,
            'companionCollapseTargetPolicy' => $flosc_companion_collapse_target_policy,
            'companionRoutingMode' => $flosc_companion_routing_mode,
            'companionHubFullScreenUrl' => $flosc_hub_fullscreen_url,
            'companionHubCompanionUrl' => $flosc_hub_companion_url,
            // Owning flow id for cross-domain knowledge hub handoff (e.g. flow_com_ivr).
            'companionFlowId' => $flosc_current_flow ? sanitize_key((string) ($flosc_current_flow['id'] ?? pathinfo((string) ($flosc_current_flow['ivr_file'] ?? ''), PATHINFO_FILENAME))) : '',
            'companionContextualPrompt' => $flosc_companion_contextual_prompt,
            'companionProfileTier' => [
                'visitor' => $flosc_companion_tier_visitor,
                'guest'   => $flosc_companion_tier_guest,
                'member'  => $flosc_companion_tier_member,
            ],
            'companionProfileTierLabels' => [
                'visitor' => $flosc_companion_tier_visitor_label,
                'guest'   => $flosc_companion_tier_guest_label,
                'member'  => $flosc_companion_tier_member_label,
            ],
            'companionStateKey' => $flosc_companion_state_key,
            'companionStateStorage' => $flosc_companion_state_storage,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'logoutNonce' => wp_create_nonce('flosc_logout'),
            'profileUrl' => ($flosc_user && function_exists('bp_core_get_user_domain')) ? bp_core_get_user_domain($flosc_user->ID) : admin_url('profile.php'),
            'dashboardUrl' => admin_url(),
            'loginUrl' => wp_login_url($flosc_app_url),
            'logoutUrl' => wp_logout_url(
                flosc_get_setting('logout_redirect_url', $flosc_app_url)
            ),
            'registerUrl' => wp_registration_url(),
            'registrationUrl' => wp_registration_url(),
            'lessonsUrl' => home_url('/lessons/'),
            'checkoutUrl' => home_url('/checkout/'),
            // Per-flow Lessons tab only — never fall back to global flosc_content_item_category.
            'lessonsCategory' => (function () use ($flosc_current_flow, $flow_settings) {
                $flosc_cat = trim((string) ($flow_settings['content_item_category'] ?? ''));
                if ($flosc_cat === '' && is_array($flosc_current_flow)) {
                    $flosc_cat = trim((string) ($flosc_current_flow['content_item_category'] ?? ''));
                }
                return $flosc_cat;
            })(),
            // Authoritative: flosc_flows / flosc_flow_* Lessons configuration.
            'servesLessons' => (function () use ($flosc_current_flow) {
                $stem = '';
                if (is_array($flosc_current_flow)) {
                    $ivr = (string) ($flosc_current_flow['ivr_file'] ?? $flosc_current_flow['ivr'] ?? $flosc_current_flow['id'] ?? '');
                    $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                    if ($stem === '' || $stem === 'default') {
                        $stem = sanitize_key((string) ($flosc_current_flow['id'] ?? ''));
                    }
                }
                if (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'flosc_flow_serves_lessons')) {
                    return (bool) flosc()->flosc_flow_serves_lessons($stem);
                }
                return false;
            })(),
            'otoOfferId' => $flosc_current_flow['oto_offer_id'] ?? get_option('flosc_oto_offer_id', ''),
            'tokenName' => get_option('flosc_token_name', 'tokens'),
            'communicationTokenEconomics' => (function() {
                $token_provider = FLOSC_Sale_Manager::instance()->get_provider('tokens');
                if ($token_provider && method_exists($token_provider, 'get_communication_economics')) {
                    return $token_provider->get_communication_economics();
                }
                return [
                    'tokens_per_message' => 5000,
                    'nominal_millicents_per_message' => 5000,
                    'real_millicents_per_message' => 2500,
                ];
            })(),
            'visitorTokenDisplay' => [
                'value' => $flosc_visitor_wallet_initial,
                'formatted' => $flosc_visitor_wallet_initial_display,
                'realMillicents' => $flosc_real_millicents_per_message,
                'lowThreshold' => max(0, intval($flosc_current_flow['visitor_low_token_threshold'] ?? 0)),
                // Included from FLOSC_Full_Page_Mode — use flosc() not $this.
                'depletedContactMode' => (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'flosc_get_visitor_depleted_contact_mode'))
                    ? flosc()->flosc_get_visitor_depleted_contact_mode((string) ($flow_id ?? ''))
                    : 'message',
                'depletedContactLabels' => [
                    'title' => trim((string) ($flow_settings['contact_form_title'] ?? 'Request Guest Account')),
                    'intro' => trim((string) ($flow_settings['contact_form_intro'] ?? 'Dear visitor, your chat tokens are used up for now. You can request a Guest account and an administrator will email you a link to keep chatting. Share your details below and let the site operator know what you are interested in.')),
                    'submitText' => trim((string) ($flow_settings['contact_form_submit_text'] ?? 'Request Guest Account')),
                ],
                'label' => $flosc_visitor_label_base,
            ],
            // v1.3.7: Flow context for API calls
            'flowId' => $flosc_current_flow ? ($flosc_current_flow['id'] ?? null) : null,
            'ivrFile' => $flosc_ivr_filename,
            // v07.09: IVR Configuration
            'ivrMessages' => array_filter( $flosc_ivr_config['messages'] ?? [], static function ( $m ) {
                // Concierge messages are server-only (keyword-gated, AI-hosted, revealed
                // in fragments). Shipping them here would leak the note into the browser
                // and let the client matcher serve it raw — exactly the dump we forbid.
                return ( $m['type'] ?? '' ) !== 'concierge';
            } ),
            'ivrStyles' => $flosc_ivr_config['styles'] ?? [],
            'ivrStylesCss' => flosc_flow_styles_css($flosc_ivr_config),
            'ivrVersion' => $flosc_ivr_version,
            // v8.0.0: AI provider is PER-FLOW (the global flosc_ai_provider is
            // intentionally empty in the per-flow model). Read it from THIS flow's
            // settings so the browser correctly knows AI is active and routes every
            // message through the server (where concierge / RAG / hosting live)
            // instead of resolving matches client-side.
            'aiProvider' => ( static function () use ( $flosc_current_flow ) {
                $fid = $flosc_current_flow ? ( $flosc_current_flow['id'] ?? '' ) : '';
                $flosc_flow_settings  = $fid ? get_option( 'flosc_flow_' . sanitize_key( $fid ), [] ) : [];
                $p   = $flosc_flow_settings['ai']['provider'] ?? ( $flosc_flow_settings['ai_provider'] ?? '' );
                return ( $p !== '' ) ? $p : flosc_get_setting( 'ai_provider', 'ivr' );
            } )(),
            // v1.4.0: SSO Providers
            'ssoProviders' => $flosc_sso_providers,
            // v3.0.0: FLOSC auth token for cross-domain authentication
            // Cookie-based auth (flosc_auth_token cookie set at login by
            // set_flosc_auth_cookie) handles this. The cookie is on the current
            // domain (the flow domain) and travels with same-origin REST requests.
            // DO NOT generate a token here for the header — authenticate_flosc_token()
            // checks the header BEFORE the cookie, and if the header token is
            // present but fails validation, it blocks the valid cookie fallback.
            'authToken' => '',
            // Per-flow autoprompt pills — written to WP DB on IVR import, served here
            'autoprompts' => (function() use ($flow_settings) {
                $raw = $flow_settings['autoprompts'] ?? [];
                // Strip all accumulated backslash layers from corrupted data
                $prev = null;
                while ($prev !== $raw) { $prev = $raw; $raw = stripslashes_deep($raw); }
                $states = ['visitor', 'guest', 'member'];
                $out    = [];
                $panel_map = ['visitor' => 'intro', 'guest' => 'prompt', 'member' => 'prompt'];
                $phase_map = ['visitor' => 'freeline', 'guest' => 'offer', 'member' => 'content'];
                foreach ($states as $flosc_s) {
                    foreach (($raw[$flosc_s] ?? []) as $i => $p) {
                        $flosc_name = 'for_' . $flosc_s . 's_' . $i;
                        // v8.0.0: If pill has an Action but no explicit trigger_type,
                        // route it as 'action' type so the JS click handler calls
                        // performIVRAction() instead of sending text to AI.
                        $action = $p['action'] ?? '';
                        $explicit_trigger = $p['trigger_type'] ?? '';
                        $trigger_type  = $explicit_trigger ?: ($action ? 'action' : 'ai');
                        $trigger_value = $p['trigger_value'] ?? ($action ?: '');
                        $out[$flosc_name] = [
                            'name'          => $flosc_name,
                            'type'          => 'suggested_user_autoprompt',
                            'icon'          => $p['icon']          ?? '',
                            'user_input'    => $p['user_input']    ?? ($p['label'] ?? ''),
                            '_pill_label'   => $p['user_input']    ?? ($p['label'] ?? ''),
                            'conditions'    => $p['conditions']    ?? ('is_' . $flosc_s),
                            'message_style' => $p['style']         ?? 'pill',
                            'panel'         => $panel_map[$flosc_s],
                            'phase'         => $phase_map[$flosc_s],
                            'action'        => $action,
                            '_trigger_type' => $trigger_type,
                            '_trigger_value'=> $trigger_value,
                        ];
                    }
                }
                return $out;
            })(),
            'autopromptHeaders' => [
                'visitor' => $flow_settings['autoprompt_headers']['visitor'] ?? 'Try these AutoPrompts!',
                'guest'   => $flow_settings['autoprompt_headers']['guest']   ?? 'Try these AutoPrompts!',
                'member'  => $flow_settings['autoprompt_headers']['member']  ?? 'You\'re a member! Here\'s what\'s available:',
            ],
            'autopromptPanelEnabled' => [
                'visitor' => (bool)($flow_settings['autoprompt_panel_enabled']['visitor'] ?? true),
                'guest'   => (bool)($flow_settings['autoprompt_panel_enabled']['guest']   ?? true),
                'member'  => (bool)($flow_settings['autoprompt_panel_enabled']['member']  ?? true),
            ],
            // Companion widget panel should be parameterized separately from
            // full-page behavior. Default is disabled for ship readiness.
            'autopromptCompanionEnabled' => (bool)($flow_settings['autoprompt_companion_enabled'] ?? false),
            // v4.0.0: Admin test mode — all offers (incl. drafts) for in-chat testing
            'adminTestOffers' => $admin_test_offers ?? [],
            // Audio quiz configurable messages
            'audioQuizPhraseCompleteMessage' => flosc_get_setting('audio_quiz_phrase_complete_message', 'Thank you. {current} of {total} recorded.'),
            'audioQuizCompleteMessage' => flosc_get_setting('audio_quiz_complete_message', 'Pronunciation assessment complete! All {total} phrases recorded and analyzed. Sign up to see your results.'),
            'audioQuizResultsMessage' => flosc_get_setting('audio_quiz_results_message', 'Welcome! Here are your assessment results.'),
            'audioQuizUpsellMessage' => flosc_get_setting('audio_quiz_upsell_message', 'Our accent analysis shows you would benefit from lessons on {1st}, {2nd}, and {4th}. Upgrade today for full access to all lessons.'),
            'audioQuizPhonemeLessonMap' => json_decode(flosc_get_setting('audio_quiz_phoneme_lesson_map', '{}'), true) ?: (object)[],
            // Between-phrase escape hatch (upgrade / softer tier) — per-flow admin params.
            'audioQuizEscapeEnabled' => (function () {
                $v = flosc_get_setting( 'audio_quiz_escape_enabled', '1' );
                return $v === '' || $v === null ? true : (bool) $v;
            })(),
            'audioQuizEscapeOnce' => (function () {
                $v = flosc_get_setting( 'audio_quiz_escape_once', '1' );
                return $v === '' || $v === null ? true : (bool) $v;
            })(),
            'audioQuizEscapeAfterPhrase' => max( 0, min( 99, (int) flosc_get_setting( 'audio_quiz_escape_after_phrase', 3 ) ) ),
            'ipaApiBaseUrl' => untrailingslashit(flosc_get_setting('ipa_api_base_url', '')),
            // Quiz IDs: this flow’s bag only — empty means no quiz surface on this flow.
            // Do not invent sample/pronunciation defaults when none are enabled.
            'enabledQuizzes' => (function () use ($flow_settings, $flosc_current_flow) {
                $raw = $flow_settings['enabled_quizzes']
                    ?? $flosc_current_flow['enabled_quizzes']
                    ?? [];
                if (!is_array($raw)) {
                    return [];
                }
                $out = [];
                foreach ($raw as $qid) {
                    $qid = sanitize_key((string) $qid);
                    if ($qid === '') {
                        continue;
                    }
                    if (class_exists('FLOSC_Quiz_Registry')) {
                        $qid = FLOSC_Quiz_Registry::resolve_id($qid);
                    }
                    $out[] = $qid;
                }
                return array_values(array_unique($out));
            })(),
            'defaultAudioQuizId' => (string) ($flow_settings['default_audio_quiz_id']
                ?? $flosc_current_flow['default_audio_quiz_id']
                ?? ''),
            'defaultTextQuizId' => (function () use ($flow_settings, $flosc_current_flow) {
                $id = (string) ($flow_settings['default_text_quiz_id']
                    ?? $flosc_current_flow['default_text_quiz_id']
                    ?? '');
                if ($id !== '' && class_exists('FLOSC_Quiz_Registry')) {
                    $id = FLOSC_Quiz_Registry::resolve_id($id);
                }
                return $id;
            })(),
            'quizType' => (string) ($flow_settings['quiz_type']
                ?? $flosc_current_flow['quiz_type']
                ?? ''),
            'defaultOfferId' => flosc_get_setting('default_offer_id', ''),
            'defaultQuizAction' => flosc_get_setting('default_quiz_action', ''),
            'defaultMemberLevel' => flosc_get_setting('default_member_level', ''),
            'defaultGuestLevel' => flosc_get_setting('default_guest_level', ''),
            // Per-flow user-status templates (Login & Registration → User status messages).
            'userStatus' => [
                'visitor' => (function () {
                    $v = flosc_get_setting('user_status_visitor', 'Hey, thanks for asking about your user status! You are a **Visitor**. You are chatting in "{product_name}".');
                    $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
                })(),
                'guest' => (function () {
                    $v = flosc_get_setting('user_status_guest', 'Hey, thanks for asking about your user status! You are a **Guest**. You like to be called **{first_name}**. You are chatting in "{product_name}".');
                    $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
                })(),
                'member' => (function () {
                    $v = flosc_get_setting('user_status_member', 'Hey, thanks for asking about your user status! You are a **Member**. You like to be called **{first_name}**. You are chatting in "{product_name}".');
                    $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
                })(),
                'memberLevel' => (function () {
                    $v = flosc_get_setting('user_status_member_level', 'Hey, thanks for asking about your user status! You are a **Member**. You like to be called **{first_name}** and have access to **{member_level}** within "{product_name}".');
                    $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
                })(),
                'admin' => (function () {
                    $v = flosc_get_setting('user_status_admin', 'Hey, thanks for asking about your user status! You are the **FLOSC Admin**. You are chatting in "{product_name}".');
                    $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
                })(),
            ],
            'authModalTitle' => flosc_get_setting('auth_modal_title', 'Register Or Log In'),
            'authModalSubtitle' => flosc_get_setting('auth_modal_subtitle', ''),
            'authModalButtonText' => flosc_get_setting('auth_modal_button_text', 'Continue with Email'),
            'authModalDismissMessage' => flosc_get_setting('auth_modal_dismiss_message', 'Your quiz results are temporarily saved. Sign up or log in to see your results before they expire.'),
            'authModalSsoDivider' => flosc_get_setting('auth_modal_sso_divider', 'or continue with'),
            'authModalEmailLabel' => flosc_get_setting('auth_modal_email_label', 'Email Address'),
            'authModalEmailPlaceholder' => flosc_get_setting('auth_modal_email_placeholder', 'you@example.com'),
            'authModalLoadingText' => flosc_get_setting('auth_modal_loading_text', 'Creating account...'),
            'authModalTermsText' => flosc_get_setting('auth_modal_terms_text', 'By continuing, you agree to our Terms of Service and Privacy Policy.'),
            'authModalSuccessMessage' => flosc_get_setting('auth_modal_success_message', 'Welcome! You\'re now logged in as {email}. Let\'s continue!'),
            'authLoginModalTitle' => flosc_get_setting('auth_login_modal_title', 'Log In or Create an Account'),
            'authLoginModalSubtitle' => flosc_get_setting('auth_login_modal_subtitle', 'Sign in to access your lessons and progress.'),
            'authLoginModalButtonText' => flosc_get_setting('auth_login_modal_button_text', 'Continue with Email'),
            'authLoginModalDismissMessage' => flosc_get_setting('auth_login_modal_dismiss_message', ''),
            'authLoginModalSsoDivider' => flosc_get_setting('auth_login_modal_sso_divider', 'or continue with'),
            'authLoginModalEmailLabel' => flosc_get_setting('auth_login_modal_email_label', 'Email Address'),
            'authLoginModalEmailPlaceholder' => flosc_get_setting('auth_login_modal_email_placeholder', 'you@example.com'),
            'authLoginModalLoadingText' => flosc_get_setting('auth_login_modal_loading_text', 'Creating account...'),
            'authLoginModalTermsText' => flosc_get_setting('auth_login_modal_terms_text', 'By continuing, you agree to our Terms of Service and Privacy Policy.'),
            'authLoginModalSuccessMessage' => flosc_get_setting('auth_login_modal_success_message', 'Welcome! You\'re now logged in as {email}. Let\'s continue!'),
            'consentButtonText' => flosc_get_setting('consent_button_text', 'I Agree — Let\'s Go!'),
            // Plan IDs from active flow credentials only (never mix sandbox plans into live).
            'paypalMonthlyPlanId' => (static function () {
                $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if ($pp && method_exists($pp, 'get_client_config')) {
                    $cfg = $pp->get_client_config();
                    return (string) ($cfg['monthlyPlanId'] ?? '');
                }
                return '';
            })(),
            'paypalYearlyPlanId' => (static function () {
                $pp = FLOSC_Sale_Manager::instance()->get_provider('paypal');
                if ($pp && method_exists($pp, 'get_client_config')) {
                    $cfg = $pp->get_client_config();
                    return (string) ($cfg['yearlyPlanId'] ?? '');
                }
                return '';
            })(),
            // Product token economics (Token Management defaults for checkout UI copy).
            'subscriptionMonthlyTokenGrant' => max(0, intval(
                $flosc_current_flow['product_token_grant_recurring']
                ?? $flosc_current_flow['subscription_monthly_token_grant']
                ?? 10000
            )),
            'subscriptionYearlyTokenGrant' => max(0, intval(
                $flosc_current_flow['product_token_grant_recurring_yearly']
                ?? $flosc_current_flow['subscription_yearly_token_grant']
                ?? 35000
            )),
            'subscriptionTokenCap' => max(0, intval(
                $flosc_current_flow['product_token_cap']
                ?? $flosc_current_flow['subscription_token_cap']
                ?? 35000
            )),
            'productTokenGrantOnetime' => max(0, intval(
                $flosc_current_flow['product_token_grant_onetime']
                ?? $flosc_current_flow['product_token_grant_recurring']
                ?? $flosc_current_flow['subscription_monthly_token_grant']
                ?? 10000
            )),
            // Guest link config — product-neutral defaults (never hardcode a brand for all flows)
            // Strip all accumulated backslash layers from stored strings (same idiom as autoprompts)
            'guestLinkName'              => (function() { $v = flosc_get_setting('guest_link_name', 'Guest Access Link'); $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v; })(),
            'guestLinkCheckEmailMessage' => (function() { $v = flosc_get_setting('guest_link_check_email_message', "We've sent you a {link_name} to your email — click it to access this chat as a guest and view your quiz score, free lessons, and upgrade options."); $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v; })(),
            'guestLinkWelcomeMessage'    => (function() { $v = flosc_get_setting('guest_link_welcome_message', 'Hi, welcome back! Just to confirm: your email address is {email} and you can use your {link_name} {n} more times to access this chat. <a href="{upgrade_url}">Upgrade</a> for complete access.'); $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v; })(),
            'guestLinkUpgradeUrl'        => flosc_get_setting('guest_link_upgrade_url', ''),
            // One-time injection after redirect-back login (via short-lived transient)
            'guestLinkRemaining' => (function() use ($user_state) {
                if (!is_user_logged_in() || $user_state !== 'guest') return null;
                $key       = 'flosc_just_guest_login_' . get_current_user_id();
                $remaining = get_transient($key);
                if ($remaining === false || !is_numeric($remaining)) return null;
                delete_transient($key);
                return (int) $remaining;
            })(),
            'memberLinkLogin' => (function() use ($user_state) {
                if (!is_user_logged_in() || $user_state !== 'member') return null;
                $key = 'flosc_just_guest_login_' . get_current_user_id();
                if (get_transient($key) === false) return null;
                delete_transient($key);
                $v = flosc_get_setting('member_link_welcome_message',
                    'Hi, welcome back {name}! Just to confirm: your email address is {email} and you can use your access link unlimited times to access this chat. Enjoy your session!');
                $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v;
            })(),
            // True if the current user has any SSO provider linked (FB, Google, etc.)
            'hasSsoProvider' => (is_user_logged_in()
                && !empty(get_user_meta(get_current_user_id(), '_flosc_sso_linked_providers', true))),
            // Shows credential setup card to magic-link users who have not yet set their password and display name.
            // Scoped by registration method — not applicable to SSO users or admin/other user types.
            'pendingCredentialSetup' => (is_user_logged_in()
                && get_user_meta(get_current_user_id(), '_flosc_registration_method', true) === 'email'
                && !get_user_meta(get_current_user_id(), '_flosc_magic_link_user_credentials_set', true)
                && empty(get_user_meta(get_current_user_id(), '_flosc_sso_linked_providers', true))),
            'guestLinkProfileConfirmMessage' => (function() { $v = flosc_get_setting('guest_link_profile_confirm_message', 'Perfect, {name}! You can always log in directly at {login_url}, update your profile, and upgrade to full access.'); $p = null; while ($p !== $v) { $p = $v; $v = stripslashes_deep($v); } return $v; })(),
            // Engagement tab: profile completion nudge (product-neutral default; per-flow override)
            'engagementProfileNudgeMessage' => (function() {
                $v = flosc_get_setting(
                    'engagement_profile_nudge_message',
                    'Complete your profile to keep your results private and unlock recordings.'
                );
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v;
            })(),
            // Engagement rules (when → condition → chat/email). Offers not included.
            'engagementRules' => (function () use ($flow_settings, $flosc_current_flow) {
                $raw = $flow_settings['engagement_rules']
                    ?? $flosc_current_flow['engagement_rules']
                    ?? [];
                if (!is_array($raw)) {
                    return [];
                }
                $out = [];
                foreach ($raw as $row) {
                    if (!is_array($row) || empty($row['enabled'])) {
                        continue;
                    }
                    $out[] = [
                        'id'            => sanitize_key((string) ($row['id'] ?? '')),
                        'audience'      => sanitize_key((string) ($row['audience'] ?? 'guest')),
                        'trigger'       => sanitize_key((string) ($row['trigger'] ?? '')),
                        'triggerDays'   => max(0, min(365, intval($row['trigger_days'] ?? 0))),
                        'condition'     => sanitize_text_field((string) ($row['condition'] ?? '')),
                        'actionChat'    => !empty($row['action_chat']),
                        'actionEmail'   => !empty($row['action_email']),
                        'emailTemplate' => sanitize_key((string) ($row['email_template'] ?? '')),
                        'chatMessage'   => sanitize_textarea_field((string) ($row['chat_message'] ?? '')),
                    ];
                }
                return $out;
            })(),
            'loginCount' => is_user_logged_in()
                ? max(0, intval(get_user_meta(get_current_user_id(), '_flosc_login_count', true)))
                : 0,
            'daysSinceRegistration' => (is_user_logged_in())
                ? (function () {
                    $u = wp_get_current_user();
                    if (!$u || empty($u->user_registered)) {
                        return 0;
                    }
                    return max(0, (int) floor((time() - strtotime($u->user_registered)) / DAY_IN_SECONDS));
                })()
                : 0,
            // Days of guest access remaining — only for THIS flow when it defines a guest window.
            'guestDaysRemaining' => ($user_state === 'guest' && is_user_logged_in())
                ? (function() {
                    $window = intval(flosc_get_setting('guest_access_days', 0));
                    if ($window <= 0) {
                        return null;
                    }
                    $user = get_userdata(get_current_user_id());
                    if (!$user) return null;
                    $days_elapsed = floor((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS);
                    return max(0, $window - $days_elapsed);
                })()
                : null,
            // Guest/member chat list (flow params — not brand hardcodes)
            'guestMaxChats' => max(0, intval(flosc_get_setting('guest_max_chats', 0))),
            // Default true when never saved; explicit '' / '0' = off (must not treat '' as true).
            'guestCanDeleteChats' => (function () {
                $flow = function_exists('flosc') ? flosc()->get_current_flow() : null;
                $stem = '';
                if (is_array($flow)) {
                    $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
                    $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                }
                $flosc_flow_settings = $stem !== '' ? get_option('flosc_flow_' . $stem, []) : [];
                if (!is_array($flosc_flow_settings) || !array_key_exists('guest_can_delete_chats', $flosc_flow_settings)) {
                    return true;
                }
                $v = $flosc_flow_settings['guest_can_delete_chats'];
                return !($v === '' || $v === '0' || $v === 0 || $v === false || $v === null);
            })(),
            'guestCanRenameChats' => (function () {
                $flow = function_exists('flosc') ? flosc()->get_current_flow() : null;
                $stem = '';
                if (is_array($flow)) {
                    $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
                    $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
                }
                $flosc_flow_settings = $stem !== '' ? get_option('flosc_flow_' . $stem, []) : [];
                if (!is_array($flosc_flow_settings) || !array_key_exists('guest_can_rename_chats', $flosc_flow_settings)) {
                    return true;
                }
                $v = $flosc_flow_settings['guest_can_rename_chats'];
                return !($v === '' || $v === '0' || $v === 0 || $v === false || $v === null);
            })(),
            'guestNewChatLimitMessage' => (function () {
                $v = flosc_get_setting(
                    'guest_new_chat_limit_message',
                    'Your guest account allows {max} chats listed below. If you would like to start a new chat, you can delete one below.'
                );
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v;
            })(),
            'newChatButtonLabel' => (function () {
                $v = flosc_get_setting('new_chat_button_label', 'New chat');
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v !== '' ? $v : 'New chat';
            })(),
            'emptyChatListMessage' => (function () {
                $v = flosc_get_setting('empty_chat_list_message', 'No chats yet');
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v !== '' ? $v : 'No chats yet';
            })(),
            'guestNewChatWelcomeMessage' => (function () {
                $v = flosc_get_setting('guest_new_chat_welcome_message', 'Welcome back, what would you like to work on?');
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v;
            })(),
            'memberNewChatWelcomeMessage' => (function () {
                $v = flosc_get_setting(
                    'member_new_chat_welcome_message',
                    'Hi {NickName}, glad to be chatting with you! What would you like to work on in this session?'
                );
                $p = null;
                while ($p !== $v) {
                    $p = $v;
                    $v = stripslashes_deep($v);
                }
                return $v;
            })(),
        ]); ?>;
        window.FLOSC_USER = <?php echo wp_json_encode($user_data); ?>;
    <?php wp_add_inline_script('flosc-app', ob_get_clean(), 'before'); ?>

    <?php wp_footer(); ?>
</body>
</html>
