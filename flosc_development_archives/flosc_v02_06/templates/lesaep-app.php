<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LeSAEp - AI Pronunciation Coach</title>
    <?php wp_head(); ?>
</head>
<body class="lesaep-app" data-user-state="<?php echo esc_attr($user_state); ?>">

    <!-- Sidebar -->
    <aside class="lesaep-sidebar" id="lesaepSidebar">
        <div class="sidebar-header">
            <div class="logo">
                🎯 <span>LeSAEp</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
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

        <!-- Session history (only for logged-in users) -->
        <div class="session-history" id="sessionHistory" style="display: none;">
            <!-- Will be populated by JavaScript -->
        </div>

        <!-- User profile card (bottom of sidebar, only for logged-in users) -->
        <div class="user-profile-card" id="userProfileCard" style="display: none;">
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

            <div class="profile-dropdown" id="profileDropdown" style="display: none;">
                <div class="profile-dropdown-header">
                    <div class="profile-name" id="profileDropdownName"></div>
                    <div class="profile-email" id="profileDropdownEmail"></div>
                </div>
                <div class="upgrade-btn-container" id="upgradeContainer" style="display: none;">
                    <a href="#" class="upgrade-btn" id="upgradeBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                        Upgrade to Pro
                    </a>
                </div>
                <a href="#" class="profile-dropdown-item">Settings</a>
                <a href="#" class="profile-dropdown-item">Help</a>
                <a href="<?php echo wp_logout_url(home_url(get_option('lesaep_app_slug', 'lesaep'))); ?>" class="profile-dropdown-item">Log out</a>
            </div>
        </div>
    </aside>

    <!-- Main content area -->
    <main class="lesaep-main">
        <!-- Header -->
        <header class="lesaep-header">
            <div class="header-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="logo-mobile">🎯 LeSAEp</div>
            </div>

            <div class="header-right" id="headerRight">
                <!-- Visitor state -->
                <div class="auth-buttons" id="authButtons">
                    <a href="<?php echo esc_url(wp_login_url(home_url(get_option('lesaep_app_slug', 'lesaep')))); ?>" class="btn-secondary">Log in</a>
                    <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary">Sign up</a>
                </div>

                <!-- Logged-in state -->
                <button class="share-btn" id="shareBtn" style="display: none;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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

        <!-- Chat messages area -->
        <div class="chat-container">
            <!-- Landing state (visitor, centered) -->
            <div class="landing-state" id="landingState">
                <h1 class="landing-title">Welcome to LeSAEp</h1>
                <p class="landing-subtitle">Your AI pronunciation coach for Standard American English</p>

                <div class="suggested-prompts">
                    <button class="prompt-card" data-prompt="I want to analyze my English pronunciation">
                        <span class="prompt-text">Analyze my pronunciation</span>
                    </button>
                    <button class="prompt-card" data-prompt="Start the free quiz">
                        <span class="prompt-text">Start free quiz</span>
                    </button>
                    <button class="prompt-card" data-prompt="How does LeSAEp work?">
                        <span class="prompt-text">How does it work?</span>
                    </button>
                    <button class="prompt-card" data-prompt="What will I learn?">
                        <span class="prompt-text">What will I learn?</span>
                    </button>
                </div>
            </div>

            <!-- Chat messages (logged-in state) -->
            <div class="messages" id="messages">
                <!-- Personalized greeting -->
                <div class="greeting" id="greeting" style="display: none;">
                    <h2 class="greeting-title" id="greetingTitle">Welcome back</h2>
                </div>

                <!-- Messages will be populated here -->
            </div>

            <!-- Upgrade banner (free users only) -->
            <div class="upgrade-banner" id="upgradeBanner" style="display: none;">
                <div class="upgrade-banner-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <span>Upgrade to unlock all lessons and features</span>
                    <a href="#" class="upgrade-banner-btn" id="upgradeBannerBtn">Upgrade</a>
                </div>
                <button class="upgrade-banner-close" id="upgradeBannerClose" aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Typing indicator -->
            <div class="typing-indicator" id="typingIndicator" style="display: none;">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>

        <!-- Composer bar (input area) -->
        <div class="composer">
            <div class="composer-inner">
                <textarea
                    id="messageInput"
                    placeholder="Message LeSAEp..."
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

    <!-- Share modal -->
    <div class="modal-overlay" id="shareModal" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3>Share LeSAEp</h3>
                <button class="modal-close" id="shareModalClose" aria-label="Close">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="share-text" id="shareText">Free Standard American English Accent Evaluation!</p>
                <div class="share-link-container">
                    <input type="text" id="shareLink" readonly class="share-link-input" value="">
                    <button class="copy-btn" id="copyBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                        </svg>
                        <span id="copyBtnText">Copy link</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
