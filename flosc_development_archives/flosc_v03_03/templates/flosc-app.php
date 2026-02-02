<!DOCTYPE html>
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
    
    <?php wp_head(); ?>
</head>
<body class="flosc-app" data-user-state="<?php echo esc_attr($user_state); ?>">

    <!-- Sidebar -->
    <aside class="flosc-sidebar" id="floscSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <?php if (!empty($product['logo_url'])): ?>
                    <img src="<?php echo esc_url($product['logo_url']); ?>" alt="<?php echo esc_attr($product['name']); ?>" class="logo-img">
                <?php else: ?>
                    <span class="logo-emoji"><?php echo esc_html($product['emoji']); ?></span>
                <?php endif; ?>
                <span class="logo-text"><?php echo esc_html($product['name'] ?: 'FLOSC'); ?></span>
            </div>
            <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <button class="new-chat-btn" id="newChatBtn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>New chat</span>
        </button>

        <!-- Session history -->
        <div class="session-history" id="sessionHistory"></div>

        <!-- User profile card -->
        <div class="user-profile-card" id="userProfileCard">
            <button class="profile-button" id="profileButton">
                <img src="" alt="" class="profile-avatar" id="profileAvatar">
                <div class="profile-info">
                    <div class="profile-name" id="profileName"></div>
                    <div class="profile-badge" id="profileBadge"></div>
                </div>
                <svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>

            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-dropdown-header">
                    <div class="dropdown-name" id="dropdownName"></div>
                    <div class="dropdown-email" id="dropdownEmail"></div>
                </div>
                <div class="upgrade-container" id="upgradeContainer">
                    <button class="upgrade-btn" id="upgradeBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        Upgrade to Pro
                    </button>
                </div>
                <a href="#" class="profile-dropdown-item" id="settingsLink">Settings</a>
                <a href="#" class="profile-dropdown-item" id="helpLink">Help</a>
                <a href="<?php echo wp_logout_url(home_url(get_option('flosc_app_slug', 'app'))); ?>" class="profile-dropdown-item">Log out</a>
            </div>
        </div>
    </aside>

    <!-- Main content -->
    <main class="flosc-main">
        <!-- Header -->
        <header class="flosc-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
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

            <div class="header-right" id="headerRight">
                <!-- Visitor: auth buttons -->
                <div class="auth-buttons" id="authButtons">
                    <a href="<?php echo esc_url(wp_login_url(home_url(get_option('flosc_app_slug', 'app')))); ?>" class="btn-secondary">Log in</a>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary">Sign up</a>
                </div>

                <!-- Logged in: share button -->
                <button class="share-btn" id="shareBtn">
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
        <div class="chat-container" id="chatContainer">
            <!-- Landing state -->
            <div class="landing-state" id="landingState">
                <h1 class="landing-title">Welcome to <?php echo esc_html($product['name'] ?: 'FLOSC'); ?></h1>
                <p class="landing-subtitle"><?php echo esc_html($product['tagline'] ?: 'Your AI-powered assistant'); ?></p>
                
                <div class="suggested-prompts">
                    <button class="prompt-card" data-prompt="I want to get started">
                        <span class="prompt-icon">🎤</span>
                        <span class="prompt-text">Get started</span>
                    </button>
                    <button class="prompt-card" data-prompt="Start the free quiz">
                        <span class="prompt-icon">📝</span>
                        <span class="prompt-text">Start free quiz</span>
                    </button>
                    <button class="prompt-card" data-prompt="How does this work?">
                        <span class="prompt-icon">❓</span>
                        <span class="prompt-text">How does it work?</span>
                    </button>
                    <button class="prompt-card" data-prompt="What will I learn?">
                        <span class="prompt-icon">📚</span>
                        <span class="prompt-text">What will I learn?</span>
                    </button>
                </div>
            </div>

            <!-- Messages area -->
            <div class="messages" id="messages">
                <!-- Greeting for logged in users -->
                <div class="greeting" id="greeting">
                    <h2 class="greeting-title" id="greetingTitle">Welcome back</h2>
                </div>
            </div>

            <!-- Upgrade banner -->
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

            <!-- Typing indicator -->
            <div class="typing-indicator" id="typingIndicator">
                <div class="typing-avatar"><?php echo esc_html($product['emoji']); ?></div>
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Composer -->
        <div class="composer" id="composer">
            <div class="composer-inner">
                <button class="mic-btn" id="micBtn" aria-label="Record audio">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                        <line x1="12" y1="19" x2="12" y2="23"></line>
                        <line x1="8" y1="23" x2="16" y2="23"></line>
                    </svg>
                </button>
                <textarea
                    id="messageInput"
                    placeholder="Message <?php echo esc_attr($product['name'] ?: 'FLOSC'); ?>..."
                    rows="1"
                    aria-label="Message input"
                ></textarea>
                <button class="send-btn" id="sendBtn" aria-label="Send message">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        </div>
    </main>

    <!-- Recording Modal -->
    <div class="modal-overlay" id="recordingModal">
        <div class="modal recording-modal">
            <div class="modal-header">
                <h3>Record Your Response</h3>
                <button class="modal-close" id="recordingModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="recording-instructions" id="recordingInstructions">
                    <?php echo esc_html(get_option('flosc_quiz_instructions', 'Please speak clearly when ready.')); ?>
                </p>
                
                <div class="waveform-container" id="waveformContainer">
                    <canvas id="waveformCanvas" width="280" height="60"></canvas>
                </div>
                
                <div class="recording-timer" id="recordingTimer">0:00</div>
                
                <div class="recording-controls">
                    <button class="record-btn" id="recordBtn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                        </svg>
                        <span>Start Recording</span>
                    </button>
                    <button class="stop-btn" id="stopBtn" style="display: none;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <rect x="6" y="6" width="12" height="12" rx="2"></rect>
                        </svg>
                        <span>Stop</span>
                    </button>
                </div>
                
                <div class="recording-playback" id="recordingPlayback" style="display: none;">
                    <audio id="recordingAudio" controls></audio>
                    <div class="playback-actions">
                        <button class="btn-secondary" id="rerecordBtn">Re-record</button>
                        <button class="btn-primary" id="submitRecordingBtn">Submit</button>
                    </div>
                </div>
                
                <div class="recording-error" id="recordingError" style="display: none;">
                    <p>Microphone access denied. Please enable it in your browser settings and try again.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Modal -->
    <div class="modal-overlay" id="shareModal">
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
    <div class="modal-overlay" id="paymentModal">
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
    <div class="modal-overlay" id="loginGateModal">
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
                    <a href="<?php echo esc_url(wp_login_url(home_url(get_option('flosc_app_slug', 'app')))); ?>" class="btn-secondary btn-large">Log In</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <?php wp_footer(); ?>
    
    <!-- Pass config and user data to JS -->
    <script>
        window.FLOSC_CONFIG = <?php echo wp_json_encode([
            'restUrl' => rest_url('flosc/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'stripeKey' => $providers['stripe']['config']['publishableKey'] ?? '',
            'quizType' => get_option('flosc_quiz_type', 'pronunciation'),
            'product' => $product,
            'offers' => array_values($offers),
            'loginUrl' => wp_login_url(home_url(get_option('flosc_app_slug', 'app'))),
            'registerUrl' => wp_registration_url(),
            'lessonsCategory' => get_option('flosc_lessons_category', ''),
            'otoOfferId' => get_option('flosc_oto_offer_id', ''),
        ]); ?>;
        window.FLOSC_USER = <?php echo wp_json_encode($user_data); ?>;
    </script>
</body>
</html>
