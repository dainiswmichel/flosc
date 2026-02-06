<!DOCTYPE html>
<!--
================================================================================
FLOSC APP - MAIN CHAT APPLICATION TEMPLATE
================================================================================
Version: 1.1.0
Updated: 2026-02m-02d

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
│  flosc-layout.css = STRUCTURE (flexbox, grid, positioning, dimensions)      │
│  flosc-theme.css  = COLORS (reads CSS variables, applies to elements)       │
│  chat-style-*.css = PRESETS (defines CSS variable values)                   │
└─────────────────────────────────────────────────────────────────────────────┘

⚠️  HARD-LEARNED LESSON (2026-01m-25d):
    AI tools repeatedly broke icon visibility by adding incorrect CSS.
    After 6+ failed attempts and hours of debugging, the fix was simple:
    SVG icons MUST have stroke="currentColor" AND the parent button
    MUST have a defined color value (not just "inherit").
    
    The SVG icon pattern that WORKS:
    <svg ... fill="none" stroke="currentColor" stroke-width="2">
    
    The CSS pattern that WORKS:
    .button { color: #667; }  /* Explicit color, not inherit */
    .button svg { display: block; }  /* Prevents inline whitespace issues */
    .button:hover { color: #333; }  /* Hover state changes icon color too */

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
-->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($product['name'] ?: 'FLOSC App'); ?> - <?php echo esc_html($product['tagline']); ?></title>
    
    <!-- Dynamic Primary Color -->
    <style>
        :root {
            --flosc-primary: <?php echo esc_attr($product['primary_color']); ?>;
            --flosc-primary-hover: <?php echo esc_attr(flosc_adjust_brightness($product['primary_color'], -20)); ?>;
            --flosc-primary-light: <?php echo esc_attr($product['primary_color']); ?>15;
        }
    </style>
    
    <?php 
    // Google Analytics
    $ga4_id = get_option('flosc_ga4_id', '');
    if ($ga4_id): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_id); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo esc_js($ga4_id); ?>');
    </script>
    <?php endif; ?>

    <!-- Markdown parser for IVR messages (v04_03) -->
    <script src="https://cdn.jsdelivr.net/npm/marked@11.1.1/marked.min.js"></script>

    <?php wp_head(); ?>
</head>
<?php
// Determine if funnel is completed (for conditional rendering)
$funnel_completed = is_user_logged_in() && get_user_meta(get_current_user_id(), '_flosc_funnel_completed', true);

// v9.0.8: Chat styling data attributes (font, theme, preset, scale)
$chat_font   = get_option('flosc_chat_style_font', 'system');
$chat_theme  = get_option('flosc_chat_style_theme', 'default');
$chat_preset = get_option('flosc_chat_style_preset', 'flosc');
$chat_scale  = intval(get_option('flosc_chat_style_scale', 112)); // percent
?>
<body class="flosc-app"
      data-user-state="<?php echo esc_attr($user_state); ?>"
      data-flosc-font="<?php echo esc_attr($chat_font); ?>"
    data-flosc-theme="<?php echo esc_attr($chat_theme); ?>"
    data-flosc-style-preset="<?php echo esc_attr($chat_preset); ?>"
    style="--flosc-scale: <?php echo esc_attr($chat_scale); ?>%;">

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
    
    2. BUTTON CSS (in flosc-layout.css + flosc-theme.css):
       - Parent button MUST have explicit color (not just inherit)
       - SVG must have display: block (prevents inline spacing bugs)
       - Hover states must change color (which SVG inherits)
    
    3. ANIMATIONS (in flosc-layout.css):
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
                <?php if (!empty($product['logo_url'])): ?>
                    <img src="<?php echo esc_url($product['logo_url']); ?>" alt="<?php echo esc_attr($product['name']); ?>" class="logo-img">
                <?php else: ?>
                    <span class="logo-emoji"><?php echo esc_html($product['emoji']); ?></span>
                <?php endif; ?>
                <span class="logo-text"><?php echo esc_html($product['name'] ?: 'FLOSC'); ?></span>
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
                <button class="flosc_app_sidebar_toggle" id="flosc_app_sidebar_toggle" aria-label="Close sidebar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <?php if ($funnel_completed): ?>
        <button class="new-chat-btn" id="flosc_app_new_session_button">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>New chat</span>
        </button>

        <!-- Session history -->
        <div class="flosc_app_session_list" id="flosc_app_session_list"></div>
        <?php endif; ?>

        <!-- User profile card -->
        <div class="user-profile-card" id="flosc_app_profile_card">
            <button class="profile-button" id="flosc_app_profile_button">
                <img src="" alt="" class="profile-avatar" id="flosc_app_profile_avatar">
                <div class="profile-info">
                    <div class="profile-name" id="flosc_app_profile_name"></div>
                    <div class="profile-badge" id="flosc_app_profile_badge"></div>
                </div>
                <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div class="profile-dropdown" id="flosc_app_profile_dropdown">
                <div class="profile-dropdown-header">
                    <div class="dropdown-name" id="flosc_app_dropdown_name"></div>
                    <div class="dropdown-email" id="flosc_app_dropdown_email"></div>
                </div>
                <div class="upgrade-container" id="flosc_app_upgrade_container">
                    <button class="upgrade-btn" id="flosc_app_upgrade_button">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        Upgrade to Pro
                    </button>
                </div>
                <?php if (is_user_logged_in()): ?>
                    <?php
                    // Add WordPress/BuddyBoss profile menu items
                    $current_user = wp_get_current_user();
                    $profile_url = function_exists('bp_core_get_user_domain')
                        ? bp_core_get_user_domain($current_user->ID)
                        : admin_url('profile.php');
                    ?>
                    <a href="<?php echo esc_url($profile_url); ?>" class="profile-dropdown-item">My Profile</a>
                    <?php if (current_user_can('read')): ?>
                        <a href="<?php echo admin_url(); ?>" class="profile-dropdown-item">Dashboard</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="#" class="profile-dropdown-item" id="flosc_app_settings_link">Settings</a>
                <a href="#" class="profile-dropdown-item" id="flosc_app_help_link">Help</a>
                <a href="<?php echo wp_logout_url(home_url(get_option('flosc_app_slug', 'flosc'))); ?>" class="profile-dropdown-item">Log out</a>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flosc-main">
        <!-- Header -->
        <header class="flosc-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="flosc_app_mobile_menu_button" aria-label="Open menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="logo-mobile">
                    <?php echo esc_html($product['emoji']); ?> <?php echo esc_html($product['name'] ?: 'FLOSC'); ?>
                </div>
            </div>

            <div class="header-right" id="flosc_app_header_right">
                <!-- Visitor: auth buttons -->
                <div class="auth-buttons" id="flosc_app_auth_buttons">
                    <a href="<?php echo esc_url(wp_login_url(home_url(get_option('flosc_app_slug', 'flosc')))); ?>" class="btn-secondary">Log in</a>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary">Sign up</a>
                </div>

                <!-- Logged in: share button -->
                <button class="share-btn" id="flosc_app_share_button">
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
            <!-- Landing state -->
            <div class="landing-state" id="landingState">
                <h1 class="landing-title">Welcome to <?php echo esc_html($product['name'] ?: 'FLOSC'); ?></h1>
                <p class="landing-subtitle"><?php echo esc_html($product['tagline'] ?: 'Your AI-powered assistant'); ?></p>
            </div>

            <!-- v07.09: User autoprompts are now dynamically rendered by flosc-app.js based on ivr.md configuration -->
            <!-- No static HTML needed - the JavaScript IVR engine handles all reply rendering -->
            <div class="messages" id="flosc_output_chat_responses">
                <?php if (is_user_logged_in()): ?>
                <!-- Greeting for logged in users only -->
                <div class="greeting" id="greeting" style="display: none;">
                    <h2 class="greeting-title" id="greetingTitle"></h2>
                </div>
                <?php endif; ?>
            </div>

            <!-- User autoprompts container (dynamically populated by flosc-app.js) -->
            <div class="flosc-user-autoprompts-container" id="flosc_input_user_autoprompts_panel"></div>

            <!-- TODO v04_02: Upgrade banner commented out for redesign
                 Consider Grok-style pill button at bottom instead of full-width bar
                 See /flosc_v04_01/testing_screenshots/Screenshot 2026-01-09 at 6.18.46 PM.png
            <div class="upgrade-banner" id="upgradeBanner">
                <div class="upgrade-banner-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <span>Upgrade to unlock all content</span>
                    <button class="upgrade-banner-btn" id="upgradeBannerBtn">Upgrade</button>
                </div>
                <button class="upgrade-banner-close" id="upgradeBannerClose" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            -->

            <!-- Typing indicator -->
            <div class="typing-indicator" id="flosc_output_chat_typing_indicator">
                <div class="typing-avatar"><?php echo esc_html($product['emoji']); ?></div>
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!--
        ============================================================================
        INPUT COMPOSER - VOICE & SEND BUTTONS (2026-01m-30d)
        ============================================================================
        These buttons contain SVG icons that MUST remain visible.
        
        VOICE BUTTON (.flosc_input_chat_voice_button):
        - Default state: muted gray icon
        - Hover state: darker gray
        - Recording state: red icon (.recording class added by JS)
        - The mic SVG uses explicit stroke colors (#6b7280) as a fallback
          because this button needs to work even if CSS fails to load
        
        SEND BUTTON (.flosc_input_chat_send_button):
        - Uses stroke="currentColor" to inherit from button color
        - Button has white icon on accent-colored background
        - Hover darkens background, icon stays white
        - Disabled state reduces opacity
        
        TEACHABLE PATTERN:
        For critical UI elements, consider explicit color fallbacks in SVG
        so the interface remains usable even if CSS partially fails.
        ============================================================================
        -->
        <!-- Composer -->
        <div class="flosc_input_composer" id="flosc_input_composer">
            <div class="flosc_input_composer_inner">
                <button class="flosc_input_chat_voice_button" id="flosc_input_chat_voice_button" aria-label="Record audio" title="Record audio">
                    <svg width="20" height="20" viewBox="0 0 24 24">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" fill="none" stroke="#6b7280" stroke-width="2"></path>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" fill="none" stroke="#6b7280" stroke-width="2"></path>
                        <line x1="12" y1="19" x2="12" y2="23" stroke="#6b7280" stroke-width="2"></line>
                        <line x1="8" y1="23" x2="16" y2="23" stroke="#6b7280" stroke-width="2"></line>
                    </svg>
                </button>
                <textarea
                    id="flosc_input_chat_field"
                    placeholder="Message <?php echo esc_attr($product['name'] ?: 'FLOSC'); ?>..."
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
    </main>

    <!-- Quiz Modal (v9.3.3 - supports text AND audio input) -->
    <div class="modal-overlay" id="flosc_modal_recording">
        <div class="modal recording-modal">
            <div class="modal-header">
                <h3>🎯 Quick Quiz</h3>
                <button class="modal-close" id="floscQuizModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <?php
                // v9.3.3: Get quiz content for display
                $quiz_type = get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz');
                $quiz_content = get_option('flosc_quiz_content_' . $quiz_type, '1,2,3,4,5,6,7,8,9,10');
                $items = array_map('trim', explode(',', $quiz_content));
                $items_display = implode(', ', $items);
                ?>
                
                <!-- Quiz Prompt -->
                <div class="quiz-prompt">
                    <p class="quiz-prompt-label">Repeat this sequence:</p>
                    <p class="quiz-sequence" id="floscQuizSequence">
                        <?php echo esc_html($items_display); ?>
                    </p>
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
                <div class="quiz-panel quiz-audio-panel" id="floscQuizAudioPanel" style="display: none;">
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
                        <button class="quiz-stop-btn" id="floscQuizStopButton" style="display: none;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                            </svg>
                            <span>Stop</span>
                        </button>
                    </div>
                    
                    <div class="quiz-recording-status" id="floscQuizRecordingStatus"></div>
                    
                    <div class="quiz-playback" id="floscQuizRecordingPlayback" style="display: none;">
                        <audio id="floscQuizRecordingAudio" controls style="width: 100%;"></audio>
                        <div class="quiz-playback-actions">
                            <button class="btn-secondary" id="floscQuizRerecordButton">Re-record</button>
                            <button class="quiz-submit-btn" id="floscQuizSubmitRecordingButton">Submit →</button>
                        </div>
                    </div>
                    
                    <div class="quiz-error" id="floscQuizRecordingError" style="display: none;">
                        <p>Microphone access denied. Please enable it in your browser settings.</p>
                    </div>
                </div>
                
                <!-- Quiz Result Panel (hidden by default) -->
                <div class="quiz-result-panel" id="floscQuizResultPanel" style="display: none;">
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
    <div class="modal-overlay" id="flosc_modal_share">
        <div class="modal">
            <div class="modal-header">
                <h3>Share <?php echo esc_html($product['name'] ?: 'FLOSC'); ?></h3>
                <button class="modal-close" id="shareModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="share-text"><?php echo esc_html($product['share_text'] ?? 'Check out this amazing app!'); ?></p>
                <div class="share-link-container">
                    <input type="text" id="shareLink" readonly class="share-link-input" value="">
                    <button class="copy-btn" id="copyBtn">
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

    <!-- Payment Modal (In-Chat Stripe) -->
    <div class="modal-overlay" id="flosc_modal_payment">
        <div class="modal payment-modal">
            <div class="modal-header">
                <h3>Complete Your Purchase</h3>
                <button class="modal-close" id="paymentModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="payment-summary">
                    <div class="payment-product">
                        <span class="product-icon"><?php echo esc_html($product['emoji']); ?></span>
                        <div class="product-info">
                            <div class="product-name"><?php echo esc_html($product['name'] ?: 'FLOSC'); ?> Full Access</div>
                            <div class="product-desc">Lifetime access to all content</div>
                        </div>
                    </div>
                    <div class="payment-price" id="paymentPrice"><?php echo esc_html($product['currency_symbol'] . $product['price']); ?></div>
                </div>
                
                <div class="payment-form">
                    <label for="card-element">Card details</label>
                    <div id="card-element" class="stripe-card-element">
                        <!-- Stripe Elements will mount here -->
                    </div>
                    <div id="card-errors" class="stripe-errors" role="alert"></div>
                </div>
                
                <button class="pay-btn" id="payBtn" disabled>
                    <span class="pay-btn-text">Pay <?php echo esc_html($product['currency_symbol'] . $product['price']); ?></span>
                    <span class="pay-btn-spinner"></span>
                </button>
                
                <div class="payment-footer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Secure payment powered by Stripe</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Gate Modal (for visitors after 2 interactions) -->
    <div class="modal-overlay" id="flosc_modal_login_gate">
        <div class="modal">
            <div class="modal-header">
                <h3>Continue with <?php echo esc_html($product['name'] ?: 'FLOSC'); ?></h3>
                <button class="modal-close" id="loginGateModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body login-gate-body">
                <p>Create a free account to save your progress and continue the conversation.</p>
                <div class="login-gate-buttons">
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary btn-large">Create Free Account</a>
                    <a href="<?php echo esc_url(wp_login_url(home_url(get_option('flosc_app_slug', 'flosc')))); ?>" class="btn-secondary btn-large">Log In</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay" id="flosc_app_sidebar_overlay"></div>

    <?php wp_footer(); ?>
    
    <!-- Pass config and user data to JS -->
    <script>
        <?php
        // v07.08: Load IVR parser config
        $ivr_parser = FLOSC_IVR_Parser::flosc_instance();
        $ivr_config = $ivr_parser->get_flosc_config();
        $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
        $ivr_version = file_exists($ivr_file) ? filemtime($ivr_file) : time();

            // v1.0.7: DEBUG - Check if messages loaded (only when FLOSC_DEBUG enabled)
            if (empty($ivr_config['messages'])) {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    error_log('FLOSC v1.0.7: WARNING - No IVR messages loaded!');
                    error_log('FLOSC v1.0.7: Config keys: ' . implode(', ', array_keys($ivr_config)));
                }
            
                // Force re-parse if empty
                $ivr_file = FLOSC_PLUGIN_DIR . 'ai_configuration_files/ivr.md';
                if (file_exists($ivr_file)) {
                    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                        error_log('FLOSC v1.0.7: ivr.md exists, forcing re-parse...');
                    }
                    $markdown = file_get_contents($ivr_file);
                    $ivr_config = $ivr_parser->flosc_parse($markdown);
                    update_option('flosc_ivr_config', $ivr_config);
                    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                        error_log('FLOSC v1.0.7: Re-parse complete. Messages: ' . count($ivr_config['messages'] ?? []));
                    }
                } else {
                    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                        error_log('FLOSC v1.0.7: ERROR - ivr.md file not found at: ' . $ivr_file);
                    }
                }
            } else {
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
                    error_log('FLOSC v1.0.7: IVR config loaded successfully. Messages: ' . count($ivr_config['messages']));
                }
            }
        ?>
        window.FLOSC_CONFIG = <?php echo wp_json_encode([
            'restUrl' => rest_url('flosc/v1/'),
            'apiUrl' => rest_url('flosc/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'stripeKey' => $providers['stripe']['config']['publishableKey'] ?? '',
            'quizType' => get_option('flosc_quiz_type', 'pronunciation'),
            'product' => $product,
            'offers' => array_values($offers),
            'loginUrl' => wp_login_url(home_url(get_option('flosc_app_slug', 'flosc'))),
            'registerUrl' => wp_registration_url(),
            'registrationUrl' => wp_registration_url(),
            'lessonsUrl' => home_url('/lessons/'),
            'checkoutUrl' => home_url('/checkout/'),
            'lessonsCategory' => get_option('flosc_lessons_category', ''),
            'otoOfferId' => get_option('flosc_oto_offer_id', ''),
            'tokenName' => get_option('flosc_token_name', 'tokens'),
            // v07.09: IVR Configuration
            'ivrMessages' => $ivr_config['messages'] ?? [],
            'ivrStyles' => $ivr_config['styles'] ?? [],
            'ivrStylesCss' => $ivr_parser->get_flosc_styles_css(),
            'ivrVersion' => $ivr_version,
        ]); ?>;
        window.FLOSC_USER = <?php echo wp_json_encode($user_data); ?>;
    </script>
</body>
</html>
