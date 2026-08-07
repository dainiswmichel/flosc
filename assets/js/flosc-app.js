/**
 * FLOSC App JavaScript
 * Main application controller
 * v1.4.2: Flow-aware IVR messages - loadIVRMessages() sends flowId/ivrFile
 */

// Clear FLOSC-specific localStorage on version change. Tracks the plugin version
// (FLOSC_VERSION) so a release bump cleanly retires stale per-visitor state.
(function() {
    const FLOSC_JS_VERSION = '8.0.0';
    try {
        const stored = localStorage.getItem('flosc_js_version');
        if (stored !== FLOSC_JS_VERSION) {
            // Only clear FLOSC keys - don't nuke everything
            Object.keys(localStorage).forEach(key => {
                if (key.startsWith('flosc_')) {
                    localStorage.removeItem(key);
                }
            });
            localStorage.setItem('flosc_js_version', FLOSC_JS_VERSION);
        }
    } catch(e) {
        // Storage access may fail in private browsing — safe to ignore
    }
})();

class floscApp {
    constructor() {
        this.config = window.FLOSC_CONFIG || {};
        this.user = window.FLOSC_USER || {};
        // Real-world (this host only): no user → visitor; user id → guest|member never visitor.
        this.state = this.resolveAppUserState(
            this.user,
            document.body?.dataset?.userState || 'visitor'
        );
        if (document.body) {
            document.body.dataset.userState = this.state;
            document.body.setAttribute('data-user-state', this.state);
        }
        
        // v3.0.0: FLOSC Auth Token — host session continuity
        // Priority: config (freshest, from server) > localStorage (persisted from prior session)
        this.authToken = this.config.authToken || '';
        try {
            if (this.authToken) {
                localStorage.setItem('flosc_auth_token', this.authToken);
            } else {
                this.authToken = localStorage.getItem('flosc_auth_token') || '';
            }
        } catch (e) {
            // localStorage may fail in private browsing — auth token still works for this session
        }
        
        // v1.7.7: Debug logging — only outputs when FLOSC_CONFIG.debug is true
        // Prevents information disclosure in production while keeping logs available for dev
        this._debug = Boolean(this.config.debug);
        
        // State tracking
        this.currentSession = null;
        this.visitorInteractions = 0;
        this.isRecording = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.recordingStream = null;
        
        // Stripe
        this.stripe = null;
        this.cardElement = null;

        // v9.1.1: Security whitelist for condition parser
        this.allowedConditionVars = new Set([
            // State variables
            'is_visitor', 'is_guest', 'is_member', 'logged_in', 'has_profile',
            // Session state
            'first_show_session', 'first_message_after_quiz', 'first_message_after_login',
            'first_message_after_purchase', 'first_message_after_free_lesson', 'returning_user', 'command',
            // User info
            'user_id', 'name', 'email',
            // Quiz info
            'score', 'quiz_id', 'initial_score', 'initial_quiz_id', 'quiz_taken', 'quiz_results_shown',
            // Purchase/access
            'purchased', 'lesson_viewed', 'free_lessons_count', 'onboarded', 'access_level',
            'lessons_completed', 'has_incomplete_lesson', 'completed_quizzes',
            // v2.0.2: Login count for member tier conditions
            'login_count',
            // Session tracking
            'message_count', 'inactive_seconds', 'session_seconds', 'session_minutes',
            // Product info
            'product_name', 'price', 'discount_price', 'customer_count', 'timer_remaining'
        ]);

        // v9.1.1: Memory leak prevention - track event listeners for cleanup
        this.activeEventListeners = new Map();

        // v07.08: New IVR Engine
        this.ivr = {
            messages: {}, // Will be loaded from API
            styles: this.config.ivrStyles || {},
            phase: this.determinePhase(),
            messageCount: 0,
            sessionStart: Date.now(),
            lastInteraction: Date.now(),
            shownThisSession: this._loadOfferStates(), // v1.6.2: Restore from localStorage
            inactivityTimer: null,
            context: {}
        };

        // v9.3.2: In-Chat Quiz Engine
        this.quiz = {
            active: false,
            id: null,
            title: '',
            questions: [],
            currentIndex: 0,
            answers: [],
            startedAt: null,
            completedAt: null
        };

        this.visitorDepletedState = {
            awaitingContactDetails: false,
            inputLocked: false,
            formRenderedAt: 0,
            formSubmitted: false,
        };
        this.browsingContext = this.parseBrowsingContextFromUrl();

        // Offer timers — v1.7.7: Use Map to prevent memory leaks with multiple offers
        this.offerTimers = new Map();
        this.offerStartTime = null;

        // MTS-2026-02-03: [OFFER-ENGINE] Comprehensive offer display system
        this.offers = {
            loaded: {},           // Cached offer data by ID
            activeOffer: null,    // Currently displayed offer
            displayFormat: 'card', // Default format: pill, card, compact, banner, text
            shownOffers: new Set(), // Track which offers shown this session
            dismissedOffers: new Set(), // Track dismissed offers
            purchasedOffers: new Set(), // Track purchased offers
            checkoutInProgress: false,
            paymentMethod: null   // 'stripe', 'redirect', etc.
        };

        // Initialize
        this.init();
    }

    /**
     * v1.6.2: Persist offer_shown / offer_dismissed states to localStorage
     * so IVR conditions like offer_shown_full_access survive page refresh.
     */
    _loadOfferStates() {
        try {
            const raw = localStorage.getItem(this.flowStorageKey('flosc_offer_states'));
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    _saveOfferStates() {
        try {
            localStorage.setItem(this.flowStorageKey('flosc_offer_states'), JSON.stringify(this.ivr.shownThisSession));
        } catch (e) {
            this.logWarn('[FLOSC] Could not persist offer states', e);
        }
    }
    
        // v9.2.7: Minimal fallback - only if DB completely fails
        getFallbackMessages() {
            this.logWarn('[FLOSC] Using emergency fallback - DB messages not loaded!');
            return {
                'emergency_fallback': {
                    name: 'emergency_fallback',
                    type: 'auto',
                    phase: 'freeline',
                    content: "Hi! How can I help you today?",
                    conditions: 'always',
                    style: 'default'
                }
            };
        }
    
    /**
     * Load IVR messages from database via REST API (v9.2.7)
     * v1.3.8: Pass flow context (flow_id, ivr_file) for multi-flow support
     */
    async loadIVRMessages() {
        try {
            // v1.3.8: Build URL with flow context params
            const params = new URLSearchParams({
                phase: this.ivr.phase
            });
            
            // Add flow context if available (from FLOSC_CONFIG)
            if (this.config.flowId) {
                params.append('flow_id', this.config.flowId);
            }
            if (this.config.ivrFile) {
                params.append('ivr_file', this.config.ivrFile);
            }
            
            const url = `${this.config.apiUrl}/ivr-messages?${params.toString()}`;
            this.log('[FLOSC] Fetching IVR messages from:', url);
            
            const response = await this.authFetch(url, { credentials: 'same-origin' });
            
            if (!response.ok) {
                this.logError('[FLOSC] API returned error:', response.status);
                this.ivr.messages = this.getFallbackMessages();
                return;
            }
            
            const data = await response.json();
            this.log('[FLOSC] API response:', data);
            
            // v1.3.8: Log flow context for debugging
            if (data.flow_context) {
                this.log('[FLOSC] Flow context:', data.flow_context);
            }
            
            if (data.success && data.messages && data.messages.length > 0) {
                // Convert array to object keyed by message name
                const messagesObj = {};
                data.messages.forEach(msg => {
                    const key = msg.name || msg.id || `msg_${Date.now()}`;
                    messagesObj[key] = msg;
                });
                
                this.ivr.messages = messagesObj;
                this.log('[FLOSC] ✓ Loaded', data.messages.length, 'messages from DB for phase:', this.ivr.phase);
            } else {
                this.logWarn('[FLOSC] API returned empty messages array - check condition evaluator');
                this.log('[FLOSC] User context:', data.user_context);
                this.ivr.messages = this.getFallbackMessages();
            }
            
        } catch (error) {
            this.logError('[FLOSC] Failed to fetch IVR messages:', error);
            this.ivr.messages = this.getFallbackMessages();
        }
    }
    
    async init() {
        this.log('[FLOSC] Initializing app...');

        // Continuity before restore: visitors (client handoff) and guest/member (server session id).
        this.applyVisitorSessionHandoffFromUrl();

        // If PHP painted visitor but this browser has a FLOSC auth token, rehydrate
        // the WP user + per-flow profile wallet before any visitor-token path runs.
        await this.rehydrateSessionFromAuthToken();

        // Keep visitor runtime wallet aligned with flow-level grant changes.
        // When floscAdmin changes the visitor grant, start a fresh visitor session
        // so server-side transient balance is re-seeded from the new baseline.
        this.ensureVisitorSessionForConfiguredGrant();
        
        try {
            this.log('[FLOSC] Loading IVR messages from API...');
            await this.loadIVRMessages();
            this.log('[FLOSC] IVR messages loaded:', Object.keys(this.ivr.messages).length, 'messages');
            
            this.log('[FLOSC] Binding elements...');
            this.bindElements();
            this.log('[FLOSC] Elements bound:', {
                chatInput: !!this.chatInput,
                sendBtn: !!this.sendBtn,
                chatMessages: !!this.chatMessages
            });
            
            this.log('[FLOSC] Binding events...');
            this.bindEvents();
            this.log('[FLOSC] Events bound successfully');
            
            this.log('[FLOSC] Setting up UI...');
            this.setupUI();
            this.log('[FLOSC] UI setup complete');

            if (this.state === 'visitor') {
                await this.fetchVisitorSessionBalanceOnInit();
            }

            // V→G additive grant (visitor remaining + guest grant), once per flow.
            // Safe after SSO when wp_login ran without the visitor session cookie.
            if (this.state === 'guest') {
                await this.applyGuestTokenGrantOnInit();
            }

            // v1.8.0: Old visitor engagement bar removed. The unified profile bar
            // (always visible, 3 states) replaces it. No initVisitorBar() needed.

            this.log('[FLOSC] Injecting IVR styles...');
            this.injectIVRStyles();
            this.log('[FLOSC] IVR styles injected');

            if (this.config.stripeKey) {
                this.log('[FLOSC] Initializing Stripe...');
                this.initStripe();
            }

            if (this.state !== 'visitor') {
                // Logged-in: server sessions are the source of truth. Prefer explicit
                // collapse/expand session id, then remembered active id, then last session.
                this.log('[FLOSC] Loading sessions...');
                await this.loadSessions();

                const restoredHandoff = await this.applyUserSessionHandoffFromUrl();
                if (!restoredHandoff && this.currentSession === null) {
                    await this.restoreLastSession();
                }
                if (this.currentSession?.id) {
                    this.rememberActiveChatSessionId(this.currentSession.id);
                }
            }

            this.freeLessonDelivered = this.user?.freeLessonDelivered || false;

            // v9.0.6: Defer visitor restore until context is built; only restore on returning sessions

            this.trackEvent('page_view');

            // v07.08: Build context and start IVR
            this.log('[FLOSC] Building IVR context...');
            this.buildIVRContext();
            this.requestCompanionBrowsingContext();

            // v8.0.5: checkPendingQuizResults MUST run AFTER buildIVRContext so the
            // context flags it sets (quiz_results_shown, first_message_after_quiz)
            // aren't wiped by buildIVRContext's fresh context object.
            if (this.state !== 'visitor') {
                this.log('[FLOSC] Checking pending quiz results...');
                await this.checkPendingQuizResults();
            }
            this.log('[FLOSC] IVR context built:', this.ivr.context);

            if (this.state === 'visitor') {
                // Keep the session intact: an existing conversation is NEVER discarded.
                // The session id + history persist in localStorage across every page and
                // the companion iframe (same origin), so navigating around is one continuous
                // session. Only a genuine first-show with NO stored history starts fresh;
                // any stored messages mean this is a continuing session, so we restore them
                // (which also suppresses the opening greeting, since the chat won't be empty).
                let hasStoredVisitorHistory = false;
                try {
                    const stored = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
                    hasStoredVisitorHistory = Array.isArray(stored) && stored.length > 0;
                } catch (e) {
                    hasStoredVisitorHistory = false;
                }

                if (this.ivr.context.first_show_session && !hasStoredVisitorHistory) {
                    this.log('[FLOSC] First session - no stored history, starting fresh');
                    try { localStorage.removeItem(this.flowStorageKey('flosc_visitor_messages')); } catch(e) { this.logWarn('FLOSC: Could not clear visitor messages', e); }
                    this._restoredVisitorMessages = false;
                } else {
                    this.log('[FLOSC] Continuing session - restoring visitor messages');
                    this.restoreVisitorMessages();
                }
            }
            
            // Member magic-link login: show confirmation as first message, with fresh chat
            if (this.config.memberLinkLogin) {
                this.currentSession = null;          // ensure new session on first message
                if (this.chatMessages) this.chatMessages.innerHTML = '';  // fresh chat
                const memberMsg = this.config.memberLinkLogin
                    .replace('{name}', this.user?.name || '')
                    .replace('{email}', this.user?.email || '');
                this.addMessage('assistant', memberMsg, true);
            }

            this.log('[FLOSC] Starting IVR...');
            // v1.6.2: initOfferMessages() REMOVED — offers ARE IVR entries, no bridge needed
            this.startIVR();
            // Post-login quiz display fallback — startIVR only auto-renders when chat is empty.
            const shouldShowPostLoginQuiz = !this.ivr.context.quiz_results_shown
                && this.shouldSurfaceQuizResults(this.user?.lastQuizData)
                && this._hasIpaPhraseResults(this.user?.lastQuizData)
                && (this._pendingQuizResultsWelcome || this.user?.justLoggedIn);
            if (shouldShowPostLoginQuiz) {
                this.log('[FLOSC] Post-login quiz results welcome (fallback)');
                setTimeout(() => {
                    if (!this.ivr.context.quiz_results_shown
                        && this.shouldSurfaceQuizResults(this.user?.lastQuizData)
                        && this._hasIpaPhraseResults(this.user?.lastQuizData)) {
                        this.openQuizResults();
                    }
                }, 1200);
                this._pendingQuizResultsWelcome = false;
            }
            // v8.0.0: Poll for admin-joined messages (admin dropping into this chat).
            this.startAdminPoll();
            this.log('[FLOSC] Initialization complete!');
            
            // v8.0.1: After everything is initialized and visitor messages restored,
            // check if the user just returned from a failed SSO attempt. If so, show
            // the error in chat and re-present the auth modal for another try.
            if (window.flosc_sso_error) {
                this.handleSSOError(window.flosc_sso_error);
                delete window.flosc_sso_error;
            }

            // v8.0.0: Auto-open login modal when arriving via email link (?flosc_open_login=1)
            if (new URLSearchParams(window.location.search).get('flosc_open_login')) {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('flosc_open_login');
                window.history.replaceState({}, '', cleanUrl.toString());
                setTimeout(() => this.showLoginModal(), 400);
            }

            // v8.0.0: Auto-open upgrade offer when arriving via upgrade link (?flosc_open_upgrade=OFFER_ID)
            const _upgradeOfferId = new URLSearchParams(window.location.search).get('flosc_open_upgrade');
            if (_upgradeOfferId) {
                const cleanUrl = new URL(window.location.href);
                cleanUrl.searchParams.delete('flosc_open_upgrade');
                window.history.replaceState({}, '', cleanUrl.toString());
                setTimeout(() => this.showOffer(_upgradeOfferId, { source: 'user' }), 600);
            }

            // Guest link: show welcome message after redirect-back login, with days remaining appended
            if (!this._restoredVisitorMessages && this.config.guestLinkRemaining !== null && this.config.guestLinkRemaining !== undefined) {
                const days = this.config.guestDaysRemaining;
                const upgradeUrl = this.config.guestLinkUpgradeUrl || '#';
                const productLabel = String(this.config?.productName || this.config?.identity?.name || '').trim() || 'this program';
                const defaultGuestLinkName = `Complimentary ${productLabel} Guest Access Link`;
                const daysStr = (days !== null && days !== undefined)
                    ? ` You have <strong>${days}</strong> day${days !== 1 ? 's' : ''} of guest access remaining — we hope you are enjoying your complimentary guest access! <a href="${upgradeUrl}">Upgrade for full access here.</a>`
                    : '';
                const welcomeMsg = (this.config.guestLinkWelcomeMessage || '')
                    .replace('{email}', this.user?.email || '')
                    .replace('{n}', this.config.guestLinkRemaining)
                    .replace('{link_name}', this.config.guestLinkName || defaultGuestLinkName)
                    .replace('{upgrade_url}', this.config.guestLinkUpgradeUrl || '#')
                    + daysStr;
                this.addMessage('assistant', welcomeMsg, true);
            }

            // SSO guest: show days remaining once per browser session
            // (magic link guests get days via guestLinkRemaining block above)
            if (this.config.hasSsoProvider
                && !this._restoredVisitorMessages
                && this.config.guestDaysRemaining !== null
                && this.config.guestDaysRemaining !== undefined
                && !sessionStorage.getItem(this.flowStorageKey('flosc_sso_guest_days_shown'))) {
                const days = this.config.guestDaysRemaining;
                const upgradeUrl = this.config.guestLinkUpgradeUrl || '#';
                const msg = `Welcome back! You have <strong>${days}</strong> day${days !== 1 ? 's' : ''} of guest access remaining — we hope you are enjoying your complimentary guest access! <a href="${upgradeUrl}">Upgrade for full access here.</a>`;
                this.addMessage('assistant', msg, true);
                sessionStorage.setItem(this.flowStorageKey('flosc_sso_guest_days_shown'), 'true');
                try {
                    sessionStorage.setItem(this.flowStorageKey('flosc_eng_welcome_shown'), '1');
                } catch (e) { /* ignore */ }
            }

            // Profile completion prompt — independent of welcome message, persistent across redirects
            if (this.config.pendingCredentialSetup
                && !sessionStorage.getItem(this.flowStorageKey('flosc_credential_setup_dismissed'))) {
                this.showCredentialSetupCard();
            }

            // Engagement tab: return_login / profile_incomplete chat floscResponses
            this.applyEngagementChatRules();

            // Guest link: handle expired status param (no offer URL configured)
            const _guestParams = new URLSearchParams(window.location.search);
            if (_guestParams.get('flosc_guest_status') === 'expired') {
                window.history.replaceState({}, '', window.location.pathname);
            }

            // Verify window.FLOSC is set for debugging
            window.FLOSC = this;
            this.log('[FLOSC] App instance available at window.FLOSC');
            
        } catch (error) {
            this.logError('[FLOSC] INITIALIZATION FAILED:', error);
            this.logError('[FLOSC] Error stack:', error.stack);
            throw error;
        }
    }

    /**
     * Engagement rules from FLOSC_CONFIG (Engagement tab) — parameters only.
     * When user sees chat: once per browser session, when chat UI finishes loading
     * after login (not mid-conversation). Email is server-side. Offers not here.
     * AI: subsequent turns receive engagement_context so the model knows an admin
     * inserted the message and its content.
     */
    /**
     * Normalize assistant text for duplicate welcome detection.
     */
    _normalizeAssistantPlain(text) {
        return String(text || '')
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    /**
     * Strip internal AI-history label if it ever leaked into stored content.
     */
    _stripEngagementAdminLabel(text) {
        return String(text || '').replace(/^\[Admin engagement message\]\s*/i, '').trim();
    }

    /**
     * True if chat already shows an assistant bubble that is the same welcome
     * (or another "Welcome back..." line) so engagement must not double-insert.
     */
    _assistantWelcomeAlreadyShown(candidatePlain) {
        const cand = this._normalizeAssistantPlain(
            this._stripEngagementAdminLabel(candidatePlain)
        );
        if (!cand) {
            return false;
        }
        try {
            if (sessionStorage.getItem(this.flowStorageKey('flosc_eng_welcome_shown'))) {
                return true;
            }
        } catch (e) { /* ignore */ }
        const root = this.chatMessages || document.getElementById('flosc_app_messages');
        if (!root) {
            return false;
        }
        const nodes = root.querySelectorAll('.message-content, .flosc-message-content, .message.assistant, [data-role="assistant"]');
        for (const el of nodes) {
            const existing = this._normalizeAssistantPlain(
                this._stripEngagementAdminLabel(el.textContent || el.innerText || '')
            );
            if (!existing) {
                continue;
            }
            if (existing === cand) {
                return true;
            }
            // Both are return-login style welcomes (SSO days msg + engagement "Welcome back!").
            if (existing.startsWith('welcome back') && cand.startsWith('welcome back')) {
                return true;
            }
        }
        return false;
    }

    applyEngagementChatRules() {
        // Restore any notes from earlier this browser session (reload / new tab same origin).
        this._loadEngagementAiNotes();

        const rules = Array.isArray(this.config?.engagementRules) ? this.config.engagementRules : [];
        if (!rules.length) {
            return;
        }
        const loginCount = parseInt(this.config?.loginCount, 10) || 0;
        const daysReg = parseInt(this.config?.daysSinceRegistration, 10) || 0;
        const state = this.state; // visitor | guest | member | admin
        const ctx = {
            is_guest: state === 'guest',
            is_member: state === 'member' || state === 'admin',
            is_visitor: state === 'visitor',
            logged_in: state !== 'visitor',
            purchased: state === 'member' || state === 'admin' || !!this.user?.purchased,
            login_count: loginCount,
            days_since_registration: daysReg,
            quiz_taken: !!(this.user?.lastQuizData || this.user?.lastQuizScore),
            returning_user: loginCount >= 2,
            has_sso: !!this.config?.hasSsoProvider,
        };

        for (const rule of rules) {
            if (!rule || !rule.actionChat || !rule.chatMessage) {
                continue;
            }
            const aud = String(rule.audience || 'guest');
            if (aud === 'visitor' && state !== 'visitor') {
                continue;
            }
            if (aud === 'guest' && state !== 'guest') {
                continue;
            }
            if (aud === 'member' && state !== 'member' && state !== 'admin') {
                continue;
            }
            const trigger = String(rule.trigger || '');
            const n = parseInt(rule.triggerDays, 10) || 0;
            if (trigger === 'chat_open') {
                // Once per browser session when chat UI finishes loading (any audience).
            } else if (trigger === 'return_login') {
                if (state === 'visitor' || loginCount < 2) {
                    continue;
                }
            } else if (trigger === 'days_since_registration') {
                if (state === 'visitor' || daysReg < n) {
                    continue;
                }
            } else if (trigger === 'profile_incomplete') {
                if (!this.config?.pendingCredentialSetup) {
                    continue;
                }
            } else if (trigger === 'inactive_days') {
                // Email-side primarily; skip chat on every page load without last-seen meta.
                continue;
            } else {
                continue;
            }
            if (rule.condition && !this._evalEngagementCondition(rule.condition, ctx)) {
                continue;
            }
            const onceKey = this.flowStorageKey('flosc_eng_chat_' + (rule.id || trigger));
            if (sessionStorage.getItem(onceKey)) {
                continue;
            }
            const plain = String(rule.chatMessage).trim();
            if (!plain) {
                continue;
            }
            // Avoid double "Welcome back..." (SSO days bubble + engagement, or two rules).
            if (this._assistantWelcomeAlreadyShown(plain)) {
                sessionStorage.setItem(onceKey, '1');
                continue;
            }
            // UI: plain admin text only — never show "[Admin engagement message]" in the chat pane.
            // (That label is for the AI history assembler only, via meta.source.)
            this.addMessage('assistant', plain, false);
            sessionStorage.setItem(onceKey, '1');
            // Mark all welcome-style engagement slots for this session so a second
            // rule (or SSO "Welcome back" + engagement) cannot stack another line.
            if (this._normalizeAssistantPlain(plain).startsWith('welcome back')) {
                try {
                    sessionStorage.setItem(this.flowStorageKey('flosc_eng_welcome_shown'), '1');
                } catch (e) { /* ignore */ }
            }
            // AI: remember admin insert + content for subsequent turns this session.
            this._recordEngagementAiNote(rule.id || trigger, plain);
            if (state === 'visitor') {
                this.saveVisitorMessage('assistant', plain, { source: 'engagement_admin', name: 'Engagement' });
            } else if (this.currentSession?.id) {
                // Store plain text only. Server meta source=engagement_admin; chatpack
                // prefixes for the model — not for the user-visible bubble.
                this.logClientChatTurn('', plain, {
                    source: 'engagement_admin',
                    provider: 'engagement',
                });
            }
            // One engagement chat insert per open is enough; more rules would stack welcomes.
            break;
        }
    }

    _engagementAiNotesKey() {
        return this.flowStorageKey('flosc_eng_ai_notes');
    }

    _loadEngagementAiNotes() {
        try {
            const raw = sessionStorage.getItem(this._engagementAiNotesKey());
            const arr = raw ? JSON.parse(raw) : [];
            this._engagementAiNotes = Array.isArray(arr) ? arr : [];
        } catch (e) {
            this._engagementAiNotes = [];
        }
    }

    _recordEngagementAiNote(ruleId, content) {
        if (!Array.isArray(this._engagementAiNotes)) {
            this._engagementAiNotes = [];
        }
        const note = {
            rule_id: String(ruleId || ''),
            content: String(content || '').substring(0, 500),
        };
        // De-dupe by rule_id + content
        const exists = this._engagementAiNotes.some(
            (n) => n && n.rule_id === note.rule_id && n.content === note.content
        );
        if (!exists) {
            this._engagementAiNotes.push(note);
            if (this._engagementAiNotes.length > 10) {
                this._engagementAiNotes = this._engagementAiNotes.slice(-10);
            }
            try {
                sessionStorage.setItem(
                    this._engagementAiNotesKey(),
                    JSON.stringify(this._engagementAiNotes)
                );
            } catch (e) { /* ignore quota */ }
        }
    }

    /**
     * Minimal safe evaluator for Engagement freeform conditions (subset of FLOSC_Condition_Evaluator).
     * Supports && || ! () and tokens used on the Engagement condition chips.
     */
    _evalEngagementCondition(expr, ctx) {
        if (!expr || !String(expr).trim()) {
            return true;
        }
        let s = String(expr).trim();
        // Replace known atoms with true/false
        const atoms = {
            'is_guest': !!ctx.is_guest,
            'is_member': !!ctx.is_member,
            'is_visitor': !!ctx.is_visitor,
            'logged_in': !!ctx.logged_in,
            'purchased': !!ctx.purchased,
            '!purchased': !ctx.purchased,
            'quiz_taken': !!ctx.quiz_taken,
            '!quiz_taken': !ctx.quiz_taken,
            'returning_user': !!ctx.returning_user,
            'has_sso': !!ctx.has_sso,
        };
        // Numeric comparisons
        s = s.replace(/login_count\s*(>=|<=|>|<|==)\s*(\d+)/g, (_, op, n) => {
            const a = ctx.login_count || 0;
            const b = parseInt(n, 10);
            return this._cmp(a, op, b) ? ' TRUE ' : ' FALSE ';
        });
        s = s.replace(/days_since_registration\s*(>=|<=|>|<|==)\s*(\d+)/g, (_, op, n) => {
            const a = ctx.days_since_registration || 0;
            const b = parseInt(n, 10);
            return this._cmp(a, op, b) ? ' TRUE ' : ' FALSE ';
        });
        for (const [k, v] of Object.entries(atoms)) {
            const re = new RegExp('\\b' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'g');
            // !purchased already in atoms as key with !
            if (k.startsWith('!')) {
                s = s.split(k).join(v ? ' TRUE ' : ' FALSE ');
            } else {
                s = s.replace(re, v ? ' TRUE ' : ' FALSE ');
            }
        }
        // Boolean reduce with parentheses (simple recursive)
        const evalBool = (e) => {
            e = e.trim();
            while (e.includes('(')) {
                e = e.replace(/\(([^()]+)\)/g, (_, inner) => (evalBool(inner) ? 'TRUE' : 'FALSE'));
            }
            if (e.includes('||')) {
                return e.split('||').some((p) => evalBool(p));
            }
            if (e.includes('&&')) {
                return e.split('&&').every((p) => evalBool(p));
            }
            e = e.trim();
            if (e.startsWith('!')) {
                return !evalBool(e.slice(1));
            }
            return e === 'TRUE' || e === 'true';
        };
        try {
            return evalBool(s);
        } catch (err) {
            this.log('[FLOSC Engagement] condition eval failed', expr, err);
            return false;
        }
    }

    _cmp(a, op, b) {
        if (op === '>=') return a >= b;
        if (op === '<=') return a <= b;
        if (op === '>') return a > b;
        if (op === '<') return a < b;
        if (op === '==') return a === b;
        return false;
    }

    ensureVisitorSessionForConfiguredGrant() {
        if (this.state !== 'visitor') {
            return;
        }

        try {
            const flowId = String(this.config?.flowId || 'default');
            const grantValue = parseInt(this.config?.visitorTokenDisplay?.value, 10);
            if (!Number.isFinite(grantValue) || grantValue < 0) {
                return;
            }

            const grantKey = `flosc_visitor_grant_${flowId}`;
            const previousGrant = parseInt(localStorage.getItem(grantKey), 10);
            // Session transient on the server owns the runtime balance. Keep this
            // check limited to grant metadata tracking; never rewrite token count
            // from client storage during init.
            this.log('[FLOSC] Visitor grant tracked for current flow.', {
                flowId,
                previousGrant,
                grantValue,
            });

            localStorage.setItem(grantKey, String(grantValue));
        } catch (e) {
            this.logWarn('[FLOSC] Could not evaluate visitor grant/session alignment', e);
        }
    }

    async fetchVisitorSessionBalanceOnInit() {
        if (this.state !== 'visitor') {
            return;
        }

        const sessionId = String(this.getVisitorSessionId() || '').trim();
        const flowId = String(this.config?.flowId || '').trim();
        if (!sessionId || !flowId) {
            return;
        }

        try {
            const response = await this.authFetch(this.config.apiUrl + '/visitor-session-balance', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    flow_id: flowId
                })
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            this.syncVisitorTokenBalanceFromPayload(data);
        } catch (e) {
            this.logWarn('[FLOSC] Could not fetch visitor session balance on init:', e);
        }
    }

    /**
     * V→G: remaining visitor tokens + guest_token_grant (once per flow).
     * Called for guests on app init so SSO return still carries visitor remaining.
     * Retries once — silent REST failure was the main "tokens not allocated" symptom.
     */
    async applyGuestTokenGrantOnInit() {
        if (this.state !== 'guest' && this.state !== 'member') {
            return;
        }

        const sessionId = String(this.getVisitorSessionId() || '').trim();
        const flowId = String(this.config?.flowId || this.user?.flowId || '').trim();
        if (!flowId) {
            this.logWarn('[FLOSC] Token grant skipped: missing flowId');
            return;
        }

        this._persistVisitorSessionCookie(sessionId);
        this.user = this.user || {};

        const attempt = async () => {
            const response = await this.authFetch(this.config.apiUrl + '/apply-guest-token-grant', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    flow_id: flowId,
                    visitor_session_id: sessionId
                })
            });
            if (!response.ok) {
                const errText = await response.text().catch(() => '');
                throw new Error(`grant HTTP ${response.status} ${errText}`.slice(0, 200));
            }
            return response.json();
        };

        try {
            let data;
            try {
                data = await attempt();
            } catch (firstErr) {
                this.logWarn('[FLOSC] Guest token grant attempt 1 failed, retrying:', firstErr);
                await new Promise((r) => setTimeout(r, 450));
                data = await attempt();
            }

            if (data?.success && typeof data.token_balance === 'number') {
                this.user.tokenBalance = data.token_balance;
                this.user.tokens = data.token_balance;
                this.user.flowTokens = data.token_balance;
                this.user.tokensFormatted = data.formatted || this.formatProfileTokenDisplay(data.token_balance);
                this.updateLoggedInTokenLabel(data.token_balance);
                this.log('[FLOSC] Token grant ok:', {
                    balance: data.token_balance,
                    applied_new: data.applied_new,
                    remaining: data.visitor_remaining,
                    grant: data.grant,
                    had_session: data.had_session,
                    skipped: data.skipped,
                    reason: data.reason,
                });
                if (data.token_balance === 0 && !data.skipped) {
                    this.logWarn('[FLOSC] Token balance is 0 after grant — check Token Management guest/member grant for this flow.');
                }
            } else {
                this.logWarn('[FLOSC] Token grant response missing balance:', data);
                this.updateLoggedInTokenLabel(this.resolveLoggedInTokenBalance());
            }
        } catch (e) {
            this.logWarn('[FLOSC] Could not apply guest token grant on init:', e);
            // Still paint whatever we have so the profile bar is not blank.
            this.updateLoggedInTokenLabel(this.resolveLoggedInTokenBalance());
        }
    }

    // ==========================================
    // v07.08: IVR System
    // ==========================================

    determinePhase() {
        // v8.0.0 FIX: Use isMember for content access (admins see content),
        // but purchased reflects actual purchase (for IVR messaging).
        // An admin who hasn't purchased still gets 'content' phase for access.
        if (this.state === 'member') return 'content';
        if (this.user?.funnelCompleted) return 'sale';
        if (this.user?.freeLessonDelivered) return 'offer';
        if (this.state !== 'visitor') return 'login';
        return 'freeline';
    }

    // v8.0.5: Helper — checks if localStorage has a pending quiz result (< 1 hour old).
    // Used by buildIVRContext() to set first_message_after_quiz when the PHP transient
    // didn't survive (cross-domain REST call, cookie issues).
    _hasPendingQuizResult() {
        try {
            const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
            if (!stored) return false;
            const result = JSON.parse(stored);
            const age = Date.now() - (result.timestamp || 0);
            return age < 3600000;
        } catch (e) { return false; }
    }

    parseBrowsingContextFromUrl() {
        try {
            const params = new URLSearchParams(window.location.search || '');
            const toText = (value, maxLen = 300) => String(value || '').trim().slice(0, maxLen);
            const toUrl = (value) => {
                const raw = String(value || '').trim();
                if (!raw) {
                    return '';
                }
                try {
                    const parsed = new URL(raw, window.location.origin);
                    if (!/^https?:$/.test(parsed.protocol)) {
                        return '';
                    }
                    return parsed.toString();
                } catch (e) {
                    return '';
                }
            };
            const toTrail = (value) => {
                try {
                    const parsed = JSON.parse(String(value || '[]'));
                    if (!Array.isArray(parsed)) {
                        return [];
                    }
                    return parsed.slice(0, 10).map((item) => {
                        if (!item || typeof item !== 'object') {
                            return null;
                        }
                        const url = toUrl(item.url || item.page_url || '');
                        if (!url) {
                            return null;
                        }
                        return {
                            url,
                            title: toText(item.title || item.page_title || '', 180),
                            path: toText(item.path || item.page_path || '', 240)
                        };
                    }).filter(Boolean);
                } catch (e) {
                    return [];
                }
            };
            const toPostId = (value) => {
                const parsed = parseInt(String(value || '').trim(), 10);
                return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
            };

            return {
                page_url: toUrl(params.get('flosc_context_url') || ''),
                page_title: toText(params.get('flosc_context_title') || '', 180),
                page_path: toText(params.get('flosc_context_path') || '', 240),
                page_referrer: toUrl(params.get('flosc_context_referrer') || ''),
                page_post_id: toPostId(params.get('flosc_context_post_id') || ''),
                page_post_type: toText(params.get('flosc_context_post_type') || '', 40),
                surface: toText(params.get('flosc_context_surface') || '', 40),
                trail: toTrail(params.get('flosc_context_trail') || '')
            };
        } catch (e) {
            return {
                page_url: '',
                page_title: '',
                page_path: '',
                page_referrer: '',
                page_post_id: 0,
                page_post_type: '',
                surface: '',
                trail: []
            };
        }
    }

    consumeBrowsingContextQueryParams() {
        try {
            const url = new URL(window.location.href);
            const keys = [
                'flosc_surface',
                'flosc_context_url',
                'flosc_context_title',
                'flosc_context_path',
                'flosc_context_referrer',
                'flosc_context_post_id',
                'flosc_context_post_type',
                'flosc_context_post_title',
                'flosc_context_surface',
                'flosc_context_trail',
                'flosc_companion_expand_target'
            ];

            let changed = false;
            keys.forEach((key) => {
                if (url.searchParams.has(key)) {
                    url.searchParams.delete(key);
                    changed = true;
                }
            });

            if (!changed) {
                return;
            }

            const search = url.searchParams.toString();
            const cleaned = url.pathname + (search ? ('?' + search) : '') + (url.hash || '');
            window.history.replaceState({}, '', cleaned);
        } catch (e) {
            // Ignore URL cleanup failures.
        }
    }

    /**
     * Storage key scoped to the current flow so multi-tab / multi-flow
     * Different flows never share visitor history, offers, or quiz state.
     * Auth token stays host-global (one login per origin).
     */
    flowStorageKey(base) {
        const raw = String(
            this.config?.flowId || this.config?.ivrFile || this.config?.companionFlowId || 'default'
        );
        const flow = raw.replace(/[^a-zA-Z0-9._-]/g, '_').slice(0, 80) || 'default';
        return String(base) + '__' + flow;
    }

    /** localStorage key: active guest/member chat session on this origin (chat host). */
    getActiveChatSessionStorageKey() {
        return this.flowStorageKey('flosc_active_chat_session');
    }

    rememberActiveChatSessionId(sessionId) {
        const id = String(sessionId || '').trim();
        if (!id || this.state === 'visitor') {
            return;
        }
        try {
            localStorage.setItem(this.getActiveChatSessionStorageKey(), id.slice(0, 80));
        } catch (e) {
            // Ignore storage failures.
        }
    }

    readRememberedActiveChatSessionId() {
        try {
            return String(localStorage.getItem(this.getActiveChatSessionStorageKey()) || '').trim().slice(0, 80);
        } catch (e) {
            return '';
        }
    }

    forgetRememberedActiveChatSessionId() {
        try {
            localStorage.removeItem(this.getActiveChatSessionStorageKey());
        } catch (e) {
            // Ignore storage failures.
        }
    }

    encodeSessionHandoffPayload(payload) {
        try {
            return window.btoa(unescape(encodeURIComponent(JSON.stringify(payload || {}))));
        } catch (e) {
            return '';
        }
    }

    decodeSessionHandoffPayload(encoded) {
        try {
            if (!encoded) {
                return null;
            }
            const decoded = decodeURIComponent(escape(window.atob(String(encoded))));
            const parsed = JSON.parse(decoded);
            return (parsed && typeof parsed === 'object') ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    /**
     * Build continuity payload for surface switches.
     * - Visitors: client session id + local message history (cautious continuity).
     * - Guest/member: server chat session id (authoritative WP-user persistence).
     */
    buildSessionHandoffPayload() {
        if (this.state === 'visitor') {
            const payload = {
                kind: 'visitor',
                sessionId: String(this.getVisitorSessionId() || '').slice(0, 80),
                messages: []
            };
            try {
                const messages = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
                if (Array.isArray(messages) && messages.length > 0) {
                    payload.messages = messages.slice(-50).map((msg) => {
                        if (!msg || typeof msg !== 'object') {
                            return null;
                        }
                        const role = String(msg.role || '').trim();
                        if (role !== 'assistant' && role !== 'user') {
                            return null;
                        }
                        return {
                            role,
                            content: String(msg.content || '').slice(0, 1200),
                            timestamp: Math.max(0, parseInt(msg.timestamp, 10) || Date.now())
                        };
                    }).filter(Boolean);
                }
            } catch (e) {
                payload.messages = [];
            }
            return payload;
        }

        // Logged-in: server session is source of truth (chat logs on the user).
        const serverId = String(this.currentSession?.id || this.readRememberedActiveChatSessionId() || '').trim();
        if (!serverId) {
            return { kind: 'user', sessionId: '', messages: [] };
        }
        this.rememberActiveChatSessionId(serverId);
        return {
            kind: 'user',
            sessionId: serverId.slice(0, 80),
            messages: []
        };
    }

    /** @deprecated Use buildSessionHandoffPayload — kept for older call sites. */
    buildVisitorSessionHandoffPayload() {
        if (this.state !== 'visitor') {
            return null;
        }
        return this.buildSessionHandoffPayload();
    }

    postVisitorSessionHandoffToParent() {
        if (window.self === window.top) {
            return;
        }

        const payload = this.buildSessionHandoffPayload() || {};

        let targetOrigin = '*';
        try {
            if (document.referrer) {
                const ref = new URL(document.referrer, window.location.origin);
                if (/^https?:$/.test(ref.protocol)) {
                    targetOrigin = ref.origin;
                }
            }
        } catch (e) {
            targetOrigin = '*';
        }

        window.parent.postMessage({
            type: 'flosc_companion_session_handoff',
            payload
        }, targetOrigin);
    }

    consumeSessionHandoffQueryParams() {
        try {
            const url = new URL(window.location.href);
            let changed = false;
            ['flosc_visitor_session', 'flosc_handoff', 'flosc_session_id'].forEach((key) => {
                if (url.searchParams.has(key)) {
                    url.searchParams.delete(key);
                    changed = true;
                }
            });
            if (!changed) {
                return;
            }
            const search = url.searchParams.toString();
            const cleaned = url.pathname + (search ? ('?' + search) : '') + (url.hash || '');
            window.history.replaceState({}, '', cleaned);
        } catch (e) {
            // Ignore URL cleanup failures.
        }
    }

    /**
     * Apply visitor continuity params from the URL (anonymous diligence path).
     */
    applyVisitorSessionHandoffFromUrl() {
        if (this.state !== 'visitor') {
            return;
        }

        try {
            const params = new URLSearchParams(window.location.search || '');
            const sid = String(params.get('flosc_visitor_session') || '').trim();
            const encoded = String(params.get('flosc_handoff') || '').trim();

            if (encoded) {
                const payload = this.decodeSessionHandoffPayload(encoded);
                if (payload && typeof payload === 'object') {
                    const payloadSid = String(payload.sessionId || '').trim();
                    const effectiveSid = payloadSid || sid;
                    if (effectiveSid) {
                        this._visitorSessionId = effectiveSid.slice(0, 80);
                        try {
                            localStorage.setItem(this.flowStorageKey('flosc_visitor_session'), this._visitorSessionId);
                        } catch (e) {
                            // Ignore storage failures.
                        }
                    }

                    const msgs = Array.isArray(payload.messages) ? payload.messages : [];
                    if (msgs.length > 0) {
                        const compact = msgs.slice(-50).map((msg) => {
                            if (!msg || typeof msg !== 'object') {
                                return null;
                            }
                            const role = String(msg.role || '').trim();
                            if (role !== 'assistant' && role !== 'user') {
                                return null;
                            }
                            return {
                                role,
                                content: String(msg.content || '').slice(0, 1200),
                                timestamp: Math.max(0, parseInt(msg.timestamp, 10) || Date.now())
                            };
                        }).filter(Boolean);
                        if (compact.length > 0) {
                            try {
                                localStorage.setItem(this.flowStorageKey('flosc_visitor_messages'), JSON.stringify(compact));
                            } catch (e) {
                                // Ignore storage failures.
                            }
                        }
                    }

                }
            } else if (sid) {
                this._visitorSessionId = sid.slice(0, 80);
                try {
                    localStorage.setItem(this.flowStorageKey('flosc_visitor_session'), this._visitorSessionId);
                } catch (e) {
                    // Ignore storage failures.
                }
            }

            this.consumeSessionHandoffQueryParams();
        } catch (e) {
            this.logWarn('[FLOSC] Could not apply visitor session handoff:', e);
        }
    }

    /**
     * Guest/member: prefer explicit flosc_session_id (collapse/expand) or remembered active session.
     * Server chat logs are the source of truth — load that session, don't mint a new one.
     *
     * @return {Promise<boolean>} true when a specific session was restored
     */
    async applyUserSessionHandoffFromUrl() {
        if (this.state === 'visitor') {
            return false;
        }

        try {
            const params = new URLSearchParams(window.location.search || '');
            let sessionId = String(params.get('flosc_session_id') || '').trim();

            if (!sessionId) {
                const encoded = String(params.get('flosc_handoff') || '').trim();
                if (encoded) {
                    const payload = this.decodeSessionHandoffPayload(encoded);
                    if (payload && typeof payload === 'object') {
                        const kind = String(payload.kind || '').toLowerCase();
                        if (kind !== 'visitor') {
                            sessionId = String(payload.sessionId || '').trim();
                        }
                    }
                }
            }

            if (!sessionId) {
                sessionId = this.readRememberedActiveChatSessionId();
            }

            this.consumeSessionHandoffQueryParams();

            if (!sessionId) {
                return false;
            }

            this.log('[FLOSC] Restoring logged-in session from handoff/memory:', sessionId);
            const ok = await this.loadSession(sessionId);
            if (ok && this.currentSession?.id) {
                this.rememberActiveChatSessionId(this.currentSession.id);
                return true;
            }
        } catch (e) {
            this.logWarn('[FLOSC] Could not apply user session handoff:', e);
        }
        return false;
    }

    /**
     * Attach continuity query params to a navigation URL (full ↔ companion).
     * @param {URL} url
     */
    appendSessionContinuityParams(url) {
        if (!url || typeof url.searchParams?.set !== 'function') {
            return;
        }
        const payload = this.buildSessionHandoffPayload();
        if (!payload) {
            return;
        }
        const sid = String(payload.sessionId || '').trim();
        if (this.state === 'visitor') {
            if (sid) {
                url.searchParams.set('flosc_visitor_session', sid.slice(0, 80));
            }
            const packed = this.encodeSessionHandoffPayload(payload);
            if (packed && packed.length <= 6000) {
                url.searchParams.set('flosc_handoff', packed);
            }
            return;
        }
        // Guest/member: server session id only (messages live on the user profile).
        if (sid) {
            url.searchParams.set('flosc_session_id', sid.slice(0, 80));
            this.rememberActiveChatSessionId(sid);
        }
    }

    applyBrowsingContext(nextContext = {}) {
        if (!nextContext || typeof nextContext !== 'object') {
            return;
        }

        const normalizeUrl = (value) => {
            const raw = String(value || '').trim();
            if (!raw) {
                return '';
            }
            try {
                const parsed = new URL(raw, window.location.origin);
                if (!/^https?:$/.test(parsed.protocol)) {
                    return '';
                }
                return parsed.toString();
            } catch (e) {
                return '';
            }
        };

        const normalizeTrail = (value) => {
            if (!Array.isArray(value)) {
                return [];
            }
            return value.slice(0, 10).map((item) => {
                if (!item || typeof item !== 'object') {
                    return null;
                }
                const url = normalizeUrl(item.url || item.page_url || '');
                if (!url) {
                    return null;
                }
                return {
                    url,
                    title: String(item.title || item.page_title || '').trim().slice(0, 180),
                    path: String(item.path || item.page_path || '').trim().slice(0, 240)
                };
            }).filter(Boolean);
        };

        const current = this.browsingContext || {};
        const nextPostId = parseInt(nextContext.page_post_id || 0, 10) || 0;
        const currentPostId = parseInt(current.page_post_id || 0, 10) || 0;
        const merged = {
            page_url: normalizeUrl(nextContext.page_url || current.page_url || ''),
            page_title: String(nextContext.page_title || current.page_title || '').trim().slice(0, 180),
            page_path: String(nextContext.page_path || current.page_path || '').trim().slice(0, 240),
            page_referrer: normalizeUrl(nextContext.page_referrer || current.page_referrer || ''),
            page_post_id: nextPostId > 0 ? nextPostId : currentPostId,
            page_post_type: String(nextContext.page_post_type || current.page_post_type || '').trim().slice(0, 40),
            surface: String(nextContext.surface || current.surface || '').trim().slice(0, 40),
            trail: normalizeTrail(Array.isArray(nextContext.trail) ? nextContext.trail : current.trail)
        };

        this.browsingContext = merged;
    }

    getContextualGreetingLine() {
        // Contextual "continue from page" greeting is only for truly fresh chats.
        // If a visitor conversation already exists, sizing/navigation changes must
        // preserve continuity and avoid restart-like greetings.
        if (this.state === 'visitor') {
            try {
                const messages = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
                if (Array.isArray(messages) && messages.length > 0) {
                    return '';
                }
            } catch (e) {
                // Ignore storage parse errors; fallback to existing behavior.
            }
        }

        const isCompanionEmbed = document.body.classList.contains('flosc-companion-embed');
        if (isCompanionEmbed) {
            const companionPrompt = String(this.config?.companionContextualPrompt || '').trim();
            return companionPrompt || 'What do you want to explore together?';
        }

        // Full-page chat uses its standard welcome. Page context still reaches the
        // AI through the prompt, so there is no forced "continue from the page" opening line.
        return '';
    }

    buildIVRContext() {
        // v8.0.9: Use positive state checking instead of negative logic
        const isVisitor = (this.state === 'visitor');
        const isGuest = (this.state === 'guest');
        const isMember = (this.state === 'member');
        
        // v9.0.6: Check if first session (unified session key format)
        const sessionKey = 'flosc_session_' + this.getSessionKey();
        const hasSession = !!localStorage.getItem(sessionKey);
        
        this.ivr.context = {
            // Positive state assertions (clear and explicit)
            is_visitor: isVisitor,
            is_guest: isGuest,
            is_member: isMember,
            logged_in: !isVisitor,  // Keep for backward compatibility with ivr.md
            has_profile: !isVisitor && !!this.user?.id,
            // v1.6.2: access_level string for condition evaluator (is_guest/is_visitor/is_member)
            access_level: isMember ? 'member' : (isGuest ? 'guest' : 'visitor'),
            
            // Session state
            first_show_session: !hasSession,
            // v8.0.0: Set from PHP transients (one-shot flags, survive buildIVRContext rebuild)
            // v8.0.5: Also check localStorage for pending quiz results as fallback when
            // the server transient didn't survive (cross-domain, cookie issues).
            first_message_after_quiz: !!this.user?.justCompletedQuiz || this._hasPendingQuizResult(),
            first_message_after_login: !!this.user?.justLoggedIn,
            first_message_after_purchase: !!this.user?.justPurchased,
            returning_user: hasSession,
            command: '',
            
            // User info
            user_id: this.user?.id || 0,
            name: this.user?.name?.split(' ')[0] || 'there',
            email: this.user?.email || '',
            
            // Quiz info — v5.0.3 FIX: Also check this.quiz (in-session data)
            // so buildIVRContext() doesn't wipe quiz_taken for visitors whose
            // this.user object was never populated with quiz results.
            score: parseInt(this.user?.lastQuizScore) || this.quiz?.score || 0,
            quiz_id: this.user?.lastQuizId || this.quiz?.id || 'unknown',
            initial_score: parseInt(this.user?.initialScore) || 0,
            initial_quiz_id: this.user?.initialQuizId || 'unknown',
            quiz_taken: this._userHasQuizTaken(),
            // v5.0.3 FIX: Include actual quiz items so AI knows what was missed/correct
            correct_items: this.quiz?.correctItems || [],
            incorrect_items: this.quiz?.missedItems || [],
            // v8.0.0: Track quiz completion and results display state
            quiz_completed: this._userHasQuizTaken(),
            quiz_results_shown: false,  // Set true by checkPendingQuizResults after display
            // v8.0.10: Signal that user is currently in the middle of taking the IPA quiz
            quiz_in_progress: !!(this.ipaQuiz && this.ipaQuiz.phrases && this.ipaQuiz.currentIndex < this.ipaQuiz.phrases.length),
            // v8.0.11: IPA quiz detailed results for AI context
            ipa_quiz_score: this.ipaQuiz?.score || this.quiz?.score || 0,
            ipa_quiz_tier: this.ipaQuiz?.tier || '',
            ipa_ranked_phonemes: this.ipaQuiz?.rankedPhonemes || [],
            ipa_weakest_sounds: this.ipaQuiz?.weakestSounds || [],
            
            // Purchase/access — v8.0.0 FIX: "purchased" must reflect actual purchase,
            // not admin-granted member access. Admins are is_member=true for content
            // gating, but purchased=false until they actually buy something.
            // This prevents IVR from showing "Congratulations on your purchase!" to admins.
            purchased: !!this.user?.purchased,
            lesson_viewed: !!this.user?.freeLessonDelivered,
            free_lessons_count: parseInt(this.user?.freeLessonsCount) || 0,
            // v2.0.2: Login count for member tier pill conditions
            login_count: parseInt(this.user?.loginCount) || 0,
            onboarded: !!this.user?.funnelCompleted,
            lessons_completed: parseInt(this.user?.lessonsCompleted) || 0,
            has_incomplete_lesson: !!this.user?.hasIncompleteLesson,
            completed_quizzes: Array.isArray(this.user?.completedQuizzes) ? this.user.completedQuizzes : [],
            
            // Session tracking
            message_count: this.ivr.messageCount,
            inactive_seconds: 0,
            session_seconds: 0,
            session_minutes: 0,
            
            // Identity info (from FLOSC_CONFIG, set by floscAdmin)
            product_name: this.config.identity?.name || 'this program',
            price: this.config.identity?.price || '',
            discount_price: this.config.identity?.discount_price || '',
            customer_count: this.config.identity?.customer_count || '',
            timer_remaining: '60:00'
        };

        const bc = this.browsingContext || {};
        let browsingPostId = parseInt(bc.page_post_id || 0, 10) || 0;
        if (!browsingPostId) {
            try {
                const params = new URLSearchParams(window.location.search || '');
                browsingPostId = parseInt(params.get('flosc_context_post_id') || 0, 10) || 0;
            } catch (e) {
                browsingPostId = 0;
            }
        }
        this.ivr.context.browsing_page_url = String(bc.page_url || '').slice(0, 1000);
        this.ivr.context.browsing_page_title = String(bc.page_title || '').slice(0, 180);
        this.ivr.context.browsing_page_path = String(bc.page_path || '').slice(0, 300);
        this.ivr.context.browsing_page_referrer = String(bc.page_referrer || '').slice(0, 1000);
        this.ivr.context.browsing_page_post_id = browsingPostId;
        this.ivr.context.browsing_surface = String(bc.surface || bc.page_surface || '').slice(0, 40) || (
            document.body.classList.contains('flosc-companion-embed') ? 'companion' : ''
        );
        this.ivr.context.browsing_surf_trail = Array.isArray(bc.trail) ? bc.trail.slice(0, 10) : [];
        
        // Mark session as started
        if (!hasSession) {
            try {
                localStorage.setItem(sessionKey, Date.now().toString());
            } catch(e) {
                this.logWarn('FLOSC: Could not set session key', e);
            }
        }
    }

    getSessionKey() {
        const today = new Date().toISOString().split('T')[0];
        return today + '_' + (this.user?.id || 'visitor');
    }

    /**
     * v8.0.0: Stable per-visitor session id, persisted in localStorage.
     *
     * Visitors have no server-side session, so the concierge desk (and log grouping)
     * key off this id. Persisting it gives a returning visitor a continuous,
     * progressively-improving experience; restartChat() mints a fresh one to begin
     * a brand-new conversation with its own desk — no backend change needed, since
     * the server already keys the desk by session_id when one is sent.
     */
    getVisitorSessionId() {
        if (this._visitorSessionId) {
            this._persistVisitorSessionCookie(this._visitorSessionId);
            return this._visitorSessionId;
        }

        try {
            let id = localStorage.getItem(this.flowStorageKey('flosc_visitor_session'));
            if (!id) {
                id = this._mintOpaqueVisitorSessionId();
                localStorage.setItem(this.flowStorageKey('flosc_visitor_session'), id);
            }
            this._visitorSessionId = id;
            this._persistVisitorSessionCookie(id);
            return id;
        } catch (e) {
            // Storage may be unavailable. Keep a stable in-memory id for this
            // page lifetime so chat/logging does not split mid-conversation.
            this._visitorSessionId = this._mintOpaqueVisitorSessionId();
            this._persistVisitorSessionCookie(this._visitorSessionId);
            return this._visitorSessionId;
        }
    }

    /**
     * Opaque visitor session id (not Date.now / sequential).
     * Prefer crypto.randomUUID; fall back to getRandomValues; last resort: random string.
     */
    _mintOpaqueVisitorSessionId() {
        try {
            if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
                return crypto.randomUUID();
            }
        } catch (e) { /* ignore */ }
        try {
            if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
                const bytes = new Uint8Array(16);
                crypto.getRandomValues(bytes);
                bytes[6] = (bytes[6] & 0x0f) | 0x40;
                bytes[8] = (bytes[8] & 0x3f) | 0x80;
                const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
                return (
                    hex.slice(0, 8) + '-' +
                    hex.slice(8, 12) + '-' +
                    hex.slice(12, 16) + '-' +
                    hex.slice(16, 20) + '-' +
                    hex.slice(20)
                );
            }
        } catch (e2) { /* ignore */ }
        return 'v-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    }

    /**
     * Cookie so PHP can read visitor remaining tokens on V→G (additive grant).
     * Same id as localStorage flosc_visitor_session.
     */
    _persistVisitorSessionCookie(sessionId) {
        const id = String(sessionId || '').trim();
        if (!id) return;
        try {
            document.cookie = `flosc_visitor_session=${encodeURIComponent(id)};path=/;max-age=2592000;SameSite=Lax`;
        } catch (e) {
            /* ignore */
        }
    }

    /**
     * Request a server-issued checkout binding token, bound to this browser
     * session. Presented back at payment completion as proof the completing
     * request is this same browser (see §5b in flosc.php). Returns '' on any
     * failure; the caller then proceeds without instant login and the buyer
     * receives the emailed sign-in link instead.
     */
    async _mintCheckoutBinding(sessionId, provider, offerId = '') {
        try {
            const res = await this.authFetch(this.config.apiUrl + '/checkout/binding', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                body: JSON.stringify({
                    session_id: sessionId || '',
                    flow_id: this.config.flowId || '',
                    provider: provider || '',
                    offer_id: offerId || '',
                }),
            });
            const data = await res.json();
            return (data && data.binding_token) ? data.binding_token : '';
        } catch (e) {
            this.logWarn('[FLOSC-CHECKOUT] binding token mint failed; falling back to email link', e);
            return '';
        }
    }

    /**
     * v8.0.0: Admin join — poll for human messages an admin posted into this chat.
     *
     * An admin can drop into a live conversation from the Chat Logs screen; their
     * line lands at the bottom of this session. We poll a public read-only endpoint
     * for any such messages newer than what we've shown, and render them in place:
     * "(admin)" lines as a pale-green admin bubble; "bot" injections as a normal
     * assistant message. Lightweight, runs while the chat is open.
     */
    startAdminPoll() {
        if (this._adminPollTimer) return;
        this._adminSince = this._adminSince || 0;

        const poll = async () => {
            const sid = (this.state === 'visitor')
                ? this.getVisitorSessionId()
                : (this.currentSession && this.currentSession.id);
            if (!sid) return;

            // Mint session-bound poll_token (required by /admin-messages permission).
            // Ownership requires at least one chat-log row for this session; fail soft until then.
            try {
                if (!this._adminPollToken || String(this._adminPollTokenSession) !== String(sid)) {
                    const tres = await fetch(this.config.apiUrl + '/admin-messages-token', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ session_id: sid }),
                    });
                    if (!tres || !tres.ok) return;
                    const tdata = await tres.json().catch(() => null);
                    if (!tdata || !tdata.poll_token) return;
                    this._adminPollToken = tdata.poll_token;
                    this._adminPollTokenSession = String(sid);
                }

                const r = await fetch(this.config.apiUrl + '/admin-messages', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        session_id: sid,
                        since_id: this._adminSince || 0,
                        flow_id: this.config?.flowId || '',
                        poll_token: this._adminPollToken,
                    }),
                });
                if (!r) return;
                if (r.status === 403) {
                    // Token invalidated (restart/ownership) — re-mint next tick.
                    this._adminPollToken = '';
                    this._adminPollTokenSession = null;
                    return;
                }
                const d = await r.json().catch(() => null);
                if (!d) return;
                this.syncVisitorTokenBalanceFromPayload(d);
                const msgs = (d && d.messages) || [];
                msgs.forEach(m => {
                    if (parseInt(m.id) > (this._adminSince || 0)) this._adminSince = parseInt(m.id);
                    if (m.source === 'bot') {
                        this.addMessage('assistant', m.text);
                        if (this.state === 'visitor') {
                            this.saveVisitorMessage('assistant', m.text);
                        }
                    } else {
                        this.renderAdminMessage(m.name, m.text);
                        if (this.state === 'visitor') {
                            this.saveVisitorMessage('assistant', m.text, {
                                source: 'admin',
                                name: m.name || 'Admin'
                            });
                        }
                    }
                });
            } catch (e) {
                /* soft-fail poll */
            }
        };
        poll();
        this._adminPollTimer = setInterval(poll, 8000);
    }

    /**
     * Render a human admin message: bot-side, pale-green, "Name (admin)".
        * Styled via sanctioned frontend stylesheet classes.
     */
    renderAdminMessage(name, text) {
        if (!this.chatMessages) return;
        const div = document.createElement('div');
        div.className = 'message assistant flosc-admin-message';
        const safeName = this.escapeHtml(name || 'Admin');
        const body = this.formatMarkdown(text);
        div.innerHTML =
            '<div class="message-content">'
                + '<div class="message-text flosc-admin-message-text">'
                    + '<div class="flosc-admin-message-meta">'
                        + safeName + ' <em>(admin)</em></div>'
                    + body
                + '</div>'
            + '</div>';
        this.chatMessages.appendChild(div);
        requestAnimationFrame(() => requestAnimationFrame(() => {
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
        }));
    }

    injectIVRStyles() {
        const stylesCss = String(this.config.ivrStylesCss || '').trim();
        if (stylesCss) {
            this.logWarn('[FLOSC] ivrStylesCss injection is disabled in release build. Use sanctioned CSS files and variables.');
        }

        // v1.6.2: Offer/checkout/autoprompt CSS moved to flosc-offers.css (enqueued via PHP)
    }

    startIVR() {
        this.log('FLOSC v9.0.6: Starting IVR for phase:', this.ivr.phase);
        this.log('FLOSC: Total messages available:', Object.keys(this.ivr.messages).length);
        
        // v1.3.5: Show admin verification message in chat (not banner)
        if (this.user?.isAdmin && this.user?.adminVerification) {
            const av = this.user.adminVerification;
            const adminMsg = `🔧 **Admin View**\n\n` +
                `**IVR File:** \`${av.ivrFile}\`\n` +
                `**Slug:** /${av.slug}/\n` +
                (av.name ? `**Name:** ${av.name}\n` : '') +
                (av.title ? `**Title:** "${av.title}"\n` : '') +
                (av.tagline ? `**Tagline:** "${av.tagline}"\n` : '') +
                (av.domain ? `**Custom Domain:** ${av.domain}\n` : '') +
                `\n_This message is only visible to admins._`;
            this.addMessage('assistant', adminMsg);
        }
        
        // v8.0.9: RULE #1 - If chat is empty, ALWAYS show welcome. Period.
        // This is idempotent and defensive - check DOM, not localStorage
        const existingMessages = this.chatMessages?.querySelectorAll('.message, .flosc-message') || [];
        
        if (existingMessages.length === 0) {
            this.log('FLOSC: Chat is empty - showing welcome message');
            
            // v1.4.7: Check URL params for contextual greeting (e.g., ?from=lesson&title=...)
            const urlParams = new URLSearchParams(window.location.search);
            const fromParam = urlParams.get('from');
            const titleParam = urlParams.get('title');
            
            if (fromParam === 'lesson' && titleParam) {
                this.log('[FLOSC] URL param greeting for lesson:', titleParam);
                const decodedTitle = decodeURIComponent(titleParam);
                this.addMessage('assistant', `Hey, you're trying to find out more about ${decodedTitle}, right? Ask me anything!`);
                this.hideTyping();
                this.floscShowUserAutoPrompts();
                this.startInactivityTimer();

                // Clean URL params without reload
                const cleanUrl = window.location.pathname + window.location.hash;
                window.history.replaceState({}, '', cleanUrl);

                return;
            }

            // v1.7.8: Handle referral from public post (via CTA on WordPress post)
            if (fromParam === 'public-post') {
                const postId = urlParams.get('post_id');
                const slug = urlParams.get('slug');
                this.log('[FLOSC] Visitor from public post:', postId, slug);

                if (slug) {
                    const decodedSlug = decodeURIComponent(slug).replace(/-/g, ' ');
                    this.addMessage('assistant', `I see you're interested in "${decodedSlug}"! Want to unlock the full lesson or ask me questions?`);
                } else {
                    this.addMessage('assistant', `Welcome! I see you came from one of our lessons. How can I help you dive deeper?`);
                }

                this.hideTyping();
                this.floscShowUserAutoPrompts();
                this.startInactivityTimer();

                // Clean URL params without reload
                const cleanUrl = window.location.pathname + window.location.hash;
                window.history.replaceState({}, '', cleanUrl);

                return;
            }

            const contextualGreeting = this.getContextualGreetingLine();
            if (contextualGreeting) {
                this.addMessage('assistant', contextualGreeting);
                // Persist the opening greeting so the session, once started, carries across
                // surfaces (companion <-> full page) and navigation instead of re-greeting.
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', contextualGreeting);
                }
                this.hideTyping();
                this.floscShowUserAutoPrompts();
                this.startInactivityTimer();
                this.consumeBrowsingContextQueryParams();
                return;
            }

            // Try to find a welcome message from IVR config
            let welcomeShown = false;
            // The AI welcome is fetched asynchronously; track it so we don't hide
            // the "…" typing indicator before the greeting actually arrives.
            let asyncWelcomeInFlight = false;
            
            // v1.9.5: When AI is active, route the welcome through AI.
            // AI uses the IVR welcome as guidance and crafts a natural greeting.
            // Token integrity guard: visitor startup welcome must not hit the
            // chat API implicitly. Visitors should only consume tokens after a
            // deliberate user message, never during first-paint greeting.
            const aiActive = this.state !== 'visitor' && this.config.aiProvider && this.config.aiProvider !== 'ivr';
            
            const messages = Object.values(this.ivr.messages);
            const welcomeMessages = messages.filter(m => 
                m.type === 'auto' && 
                m.name && 
                m.name.includes('welcome')
            );
            
            // Find applicable welcome message
            let welcomeMsg = null;
            for (const msg of welcomeMessages) {
                if (!msg.phase || msg.phase === this.ivr.phase) {
                    welcomeMsg = msg;
                    break;
                }
            }
            
            // Quiz data only as welcome when THIS flow owns that quiz.
            if (this.shouldSurfaceQuizResults(this.user?.lastQuizData)
                && this._hasIpaPhraseResults(this.user?.lastQuizData)) {
                this.log('FLOSC: Quiz data present for this flow — showing results as welcome');
                this.openQuizResults();
                welcomeShown = true;
            } else if (aiActive) {
                // AI generates the greeting using IVR welcome as inspiration
                this.log('FLOSC: AI active — routing welcome through AI');
                this.showTyping();
                this._generateAIWelcome(welcomeMsg);
                welcomeShown = true;
                asyncWelcomeInFlight = true; // _generateAIWelcome hides the "…" when it returns
            } else if (welcomeMsg) {
                // No AI — display IVR welcome directly
                this.log('FLOSC: Using IVR welcome:', welcomeMsg.name);
                this.showIVRMessage(welcomeMsg);
                welcomeShown = true;
            }

            // If no IVR welcome found and no AI, show hardcoded fallback
            if (!welcomeShown) {
                this.log('FLOSC: Using fallback welcome');
                const productName = this.config.identity?.name || 'FLOSC';
                const fallbackWelcome = this.state === 'visitor'
                    ? `Hi! Welcome to ${productName}. How can I help you today?`
                    : `Welcome back, ${this.ivr.context.name}! How can I help you today?`;
                this.addMessage('assistant', fallbackWelcome);
                // Persist the opening so the session, once started, carries across surfaces.
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', fallbackWelcome);
                }
            }

            // Hide the typing indicator now ONLY for the synchronous welcome paths
            // (IVR welcome / fallback). The AI welcome is async and hides the "…"
            // itself when the greeting arrives — hiding here would blank it instantly,
            // leaving the visitor staring at an empty screen during the wait.
            if (!asyncWelcomeInFlight) {
                this.hideTyping();
            }

            // Show user autoprompts
            this.floscShowUserAutoPrompts();

            this._scheduleOffersForEvent('chat_start');
            if (this.state === 'guest' || this.state === 'member') {
                this._scheduleOffersForEvent('login');
            }
            
            // Start inactivity timer
            this.startInactivityTimer();
            
            return; // Done - chat is now responsive
        }
        
        // v8.0.9: RULE #2 - If chat has messages, try IVR matching (but don't fail silently)
        this.log('FLOSC: Chat has', existingMessages.length, 'messages - checking for auto messages');
        this.checkAutoMessages();
        
        // Always show user autoprompts (helps user know what to do)
        setTimeout(() => this.floscShowUserAutoPrompts(), 500);
        
        // Start inactivity timer
        this.startInactivityTimer();
    }

    updateIVRContext() {
        const elapsed = (Date.now() - this.ivr.sessionStart) / 1000;
        this.ivr.context.message_count = this.ivr.messageCount;
        this.ivr.context.session_seconds = Math.floor(elapsed);
        this.ivr.context.session_minutes = Math.floor(elapsed / 60);
        this.ivr.context.inactive_seconds = Math.floor((Date.now() - this.ivr.lastInteraction) / 1000);
    }

    // Offers product data: FLOSC_CONFIG.offers (WPDB Offers registry).
    // Script rows may reference offer_id for when to show; display name/price/badge
    // come from the Offers tab via getOfferData() (registry wins over message slug).

    evaluateCondition(conditionString) {
        this.log('FLOSC: Evaluating condition:', conditionString);

        if (!conditionString || conditionString === 'always') {
            this.log('FLOSC: → TRUE (always)');
            return true;
        }
        if (conditionString === 'never') {
            this.log('FLOSC: → FALSE (never)');
            return false;
        }

        this.updateIVRContext();
        const ctx = this.ivr.context;
        this.log('FLOSC: Context:', ctx);

        try {
            const result = this.parseCondition(conditionString, ctx);
            this.log('FLOSC: → Result:', result);
            return result;
        } catch (e) {
            this.logError('FLOSC: Condition parse error:', conditionString, e);
            // v8.0.9: On error, default to FALSE (safe) but log it clearly
            this.logError('FLOSC: Returning FALSE due to parse error');
            return false;
        }
    }

    parseCondition(expr, ctx) {
        expr = expr.trim();

        // Handle parentheses
        while (expr.includes('(')) {
            expr = expr.replace(/\(([^()]+)\)/g, (match, inner) => {
                return this.parseCondition(inner, ctx) ? 'TRUE' : 'FALSE';
            });
        }

        // Handle OR
        if (expr.includes('||')) {
            const parts = expr.split('||').map(p => p.trim());
            return parts.some(p => this.parseCondition(p, ctx));
        }

        // Handle AND
        if (expr.includes('&&')) {
            const parts = expr.split('&&').map(p => p.trim());
            return parts.every(p => this.parseCondition(p, ctx));
        }

        // Handle NOT
        if (expr.startsWith('!')) {
            return !this.parseCondition(expr.substring(1), ctx);
        }

        // Handle TRUE/FALSE
        if (expr === 'TRUE') return true;
        if (expr === 'FALSE') return false;

        // Handle numeric and string comparisons (===, ==, !==, !=, >=, <=, >, <)
        const compMatch = expr.match(/^(\w+)\s*(===|==|!==|!=|>=|<=|>|<)\s*(.+)$/);
        if (compMatch) {
            const [, varName, op, raw] = compMatch;
            
            // v9.1.1: Security - only allow whitelisted variables
            if (!this.allowedConditionVars.has(varName)) {
                this.logError('[FLOSC Security] Blocked invalid condition variable:', varName);
                return false;
            }
            
            const left = ctx[varName];
            let right;
            // quoted string
            const strMatch = raw.match(/^"([^"]*)"$/) || raw.match(/^'([^']*)'$/);
            if (strMatch) {
                right = strMatch[1];
            } else if (/^empty$/i.test(raw)) {
                right = '';
            } else if (/^\d+(\.\d+)?$/.test(raw)) {
                right = parseFloat(raw);
            } else {
                // identifier from context
                right = ctx[raw];
            }
            switch (op) {
                case '===': return left === right;
                case '==': return left == right;
                case '!==': return left !== right;
                case '!=': return left != right;
                case '>': return Number(left) > Number(right);
                case '<': return Number(left) < Number(right);
                case '>=': return Number(left) >= Number(right);
                case '<=': return Number(left) <= Number(right);
            }
        }

        // Handle offer states
        if (expr.startsWith('offer_shown_')) {
            const offerId = expr.replace('offer_shown_', '');
            return this._wasOfferShown(offerId);
        }
        if (expr.startsWith('offer_dismissed_')) {
            const offerId = expr.replace('offer_dismissed_', '');
            return !!this.ivr.shownThisSession['offer_dismissed_' + offerId];
        }
        if (expr.startsWith('offer_purchased_')) {
            return ctx.purchased;
        }

        // Handle completed quiz flags: completed_quiz_{id}
        if (expr.startsWith('completed_quiz_')) {
            const qid = expr.replace('completed_quiz_', '');
            return Array.isArray(ctx.completed_quizzes) && ctx.completed_quizzes.includes(qid);
        }

        // Handle boolean variables
        // v9.1.1: Security - only allow whitelisted variables
        if (this.allowedConditionVars.has(expr) && ctx.hasOwnProperty(expr)) {
            return !!ctx[expr];
        }

        // Log attempt to access invalid variable
        if (ctx.hasOwnProperty(expr)) {
            this.logWarn('[FLOSC Security] Blocked invalid condition variable:', expr);
        }
        
        return false;
    }

    // v1.6.2: Debounced wrapper — multiple rapid callers get coalesced into one check
    checkAutoMessages() {
        if (this._autoMsgTimer) clearTimeout(this._autoMsgTimer);
        this._autoMsgTimer = setTimeout(() => this._checkAutoMessagesNow(), 50);
    }

    _checkAutoMessagesNow() {
        this.log('FLOSC: Checking auto messages for phase:', this.ivr.phase);

        // v1.9.5: When AI is active, suppress auto IVR messages.
        // IVR auto-messages are scripted nudges ("Ready to take the quiz?") that
        // bypass the AI pipeline. With AI enabled, the AI handles all engagement.
        // Offers (type === 'offer') are still shown — they're structural, not conversational.
        const aiActive = this.config.aiProvider && this.config.aiProvider !== 'ivr';

        const messages = Object.values(this.ivr.messages);
        this.log('FLOSC: Total messages loaded:', messages.length);

        const autoMessages = messages.filter(m => {
            if (m.type === 'offer') return true; // Offers always eligible
            if (m.type === 'auto' && aiActive) {
                this.log('FLOSC: Suppressing auto IVR (AI active):', m.name);
                return false;
            }
            return m.type === 'auto';
        });
        this.log('FLOSC: Auto/offer messages found:', autoMessages.length);

        for (const msg of autoMessages) {
            // v8.0.9: Skip if already shown (DOM check - idempotent)
            const alreadyShown = document.querySelector(`[data-message-name="${msg.name}"]`);
            if (alreadyShown) {
                this.log('FLOSC: Message already in DOM, skipping:', msg.name);
                continue;
            }
            
            // Skip if marked as shown this session
            if (this.ivr.shownThisSession[msg.name]) continue;

            // Check phase match — allow cross-phase for merged guest/member phases
            if (msg.phase && msg.phase !== this.ivr.phase) {
                const guestPhases = ['login', 'offer'];
                const memberPhases = ['sale', 'content'];
                const isGuestCross = guestPhases.includes(msg.phase) && guestPhases.includes(this.ivr.phase);
                const isMemberCross = memberPhases.includes(msg.phase) && memberPhases.includes(this.ivr.phase);
                if (!isGuestCross && !isMemberCross) {
                    continue;
                }
            }

            this.log('FLOSC: Testing message:', msg.name, 'condition:', msg.conditions);

            // v8.0.9: Simplify conditions - 'always' or 'never' bypass evaluation
            if (msg.conditions === 'always' || !msg.conditions) {
                this.log('FLOSC: Condition is "always" - showing message');
                this.showIVRMessage(msg);
                this.ivr.shownThisSession[msg.name] = true;
                // v8.0.0: Cascade — check for next auto-message after delay
                setTimeout(() => this.checkAutoMessages(), 1500);
                break;
            }
            
            if (msg.conditions === 'never') {
                this.log('FLOSC: Condition is "never" - skipping');
                continue;
            }

            if (this.evaluateCondition(msg.conditions)) {
                this.log('FLOSC: Condition matched! Showing:', msg.name);
                this.showIVRMessage(msg);
                this.ivr.shownThisSession[msg.name] = true;
                // v8.0.0: Cascade — show next matching message after delay
                // (e.g., login_success → offer card sequence)
                setTimeout(() => this.checkAutoMessages(), 1500);
                break; // Only show one auto message at a time
            }
        }
    }

    floscShowUserAutoPrompts() {
        // Companion mode has an explicit panel toggle separate from full-page.
        const isCompanionEmbed = document.body.classList.contains('flosc-companion-embed');
        if (isCompanionEmbed && !this.config?.autopromptCompanionEnabled) {
            this.log('FLOSC: AutoPromptPanel disabled for companion mode');
            return;
        }

        // v1.9.1: Don't re-show panel if user dismissed it this session
        if (this._panelDismissed) {
            this.log('FLOSC: Panel dismissed by user this session, skipping render');
            return;
        }

        // v4.0.0: Admin test mode — show all pills + all offers grouped by state
        if (this.user?.isAdmin) {
            this._renderAdminTestPanel();
            return;
        }

        // Check if admin has enabled the panel for this user state
        const panelEnabled = this.config.autopromptPanelEnabled || {};
        if (panelEnabled[this.state] === false) {
            this.log('FLOSC: AutoPromptPanel disabled for state:', this.state);
            return;
        }

        // Pills come from WP DB (populated from IVR file) — single source
        const autoPrompts = Object.values(this.config.autoprompts || {});

        if (autoPrompts.length === 0) {
            this.log('FLOSC: No autoprompts configured for this flow');
            return;
        }

        // Filter by panel: visitor → 'intro', guest/member → 'prompt'
        // v8.0.1: ALSO evaluate each pill's conditions so member-only pills
        // (e.g. "Show me all lessons", conditioned on is_member) don't leak to guests.
        // Previously only panel was checked — both guest and member share panel 'prompt'.
        const targetPanel = this.state === 'visitor' ? 'intro' : 'prompt';
        const applicable = [];

        for (const pill of autoPrompts) {
            if (pill.panel !== targetPanel) continue;
            // Evaluate the pill's IVR conditions (is_guest, is_member, quiz_taken, etc.)
            if (pill.conditions && pill.conditions !== 'always') {
                if (!this.evaluateCondition(pill.conditions)) continue;
            }
            applicable.push(pill);
        }

        if (applicable.length === 0) {
            this.log('FLOSC: No autoprompts match current user state:', this.state);
            return;
        }

        this.log('FLOSC: Rendering', applicable.length, 'autoprompts for state:', this.state);
        this.floscRenderUserAutoPrompts(applicable);
    }

    // v4.0.0: Admin test panel — renders all pills (by state) + all offers for in-chat testing
    // v8.0.1: Fixed container lookup (was referencing non-existent selectors) + added click handlers
    _renderAdminTestPanel() {
        // If panel already exists AND has been built (has the admin toggle), just unhide it.
        // The PHP template pre-renders an empty placeholder div with this ID, so we must
        // check for built content — otherwise we'd return early on an empty div.
        const existingPanel = document.getElementById('flosc_input_user_autoprompts_panel');
        if (existingPanel && existingPanel.querySelector('#flosc-admin-panel-toggle')) {
            existingPanel.classList.remove('flosc-hidden');
            return;
        }

        // Clean up previous panel and tracked listeners
        this.floscCleanupUserAutoPrompts();

        const adminPrompts = this.config.autoprompts || {};
        const testOffers   = this.config.adminTestOffers  || [];

        // Group pills by state (pill names follow pattern: for_visitors_N, for_guests_N, for_members_N)
        const groups = { visitor: [], guest: [], member: [] };
        Object.values(adminPrompts).forEach(p => {
            const m = (p.name || '').match(/^for_(visitors|guests|members)_/);
            if (m) groups[m[1].replace(/s$/, '')].push(p);
        });

        const stateConfig = {
            visitor: { label: 'Visitor Pills',        bg: '#f0f0f1', border: '#c3c4c7', color: '#3c434a', emoji: '⚪' },
            guest:   { label: 'Guest Pills',           bg: '#fff4e6', border: '#f59e0b', color: '#92400e', emoji: '🟠' },
            member:  { label: 'Member Pills',           bg: '#f0fdf4', border: '#86efac', color: '#166534', emoji: '🟢' },
        };

        // Build state pill sections
        let sectionsHtml = '';
        for (const [state, cfg] of Object.entries(stateConfig)) {
            const pills = groups[state];
            if (!pills.length) continue;
            const pillsHtml = pills.map(p => {
                const label     = p._pill_label || p.user_input || p.name;
                const icon      = p.icon ? `<span class="flosc-autoprompt-icon">${p.icon}</span>` : '';
                const offerAttr = p._trigger_type === 'offer'  ? ` data-offer-id="${this.escapeHtml(p._trigger_value)}"` : '';
                const actAttr   = p._trigger_type === 'action' ? ` data-action="${this.escapeHtml(p._trigger_value)}"` : '';
                return `<button class="flosc-style-pill flosc-admin-pill" data-message="${this.escapeHtml(p.user_input)}"${offerAttr}${actAttr}>${icon}<span class="flosc-autoprompt-label">${this.escapeHtml(label)}</span></button>`;
            }).join('');
            sectionsHtml += `
            <div class="flosc-admin-pill-group flosc-admin-pill-group-${state}">
                <div class="flosc-admin-pill-group-title">${cfg.emoji} ${cfg.label} (${pills.length})</div>
                <div class="flosc-admin-pill-row">${pillsHtml}</div>
            </div>`;
        }

        // Offers section — all offers, including drafts
        let offersHtml = '';
        if (testOffers.length) {
            const btns = testOffers.map(o => {
                const active = (o.status === 'active') ? '✅' : '📝';
                const price  = o.display_price || (o.price ? `$${o.price}` : '');
                return `<button class="flosc-style-pill flosc-admin-pill flosc-admin-pill-offer" data-offer-id="${this.escapeHtml(String(o.id))}">
                            💰 ${active} ${this.escapeHtml(o.name || o.id)}${price ? ' · ' + this.escapeHtml(price) : ''}
                        </button>`;
            }).join('');
            offersHtml = `
            <div class="flosc-admin-pill-group flosc-admin-pill-group-offer">
                <div class="flosc-admin-pill-group-title">🧪 ALL OFFERS — click to test</div>
                <div class="flosc-admin-pill-row">${btns}</div>
            </div>`;
        }

        const panel = document.createElement('div');
        panel.id = 'flosc_input_user_autoprompts_panel';
        panel.className = 'prompt-panel prompt-panel-inline flosc-admin-test-panel';
        panel.innerHTML = `
            <div class="prompt-panel-header flosc-admin-panel-header" id="flosc-admin-panel-toggle">
                <div>
                    <div class="prompt-panel-eyebrow flosc-admin-panel-eyebrow">🧪 Admin Test Mode — all states visible</div>
                    <div class="flosc-admin-panel-stats">🛒 Purchases: ${this.user?.purchaseCount ?? 0} | 🏷️ Level: ${this.user?.memberLevel || 'none'} | 📊 State: ${this.state || '?'}</div>
                </div>
                <span id="flosc-admin-panel-chevron" class="flosc-admin-panel-chevron">▼</span>
            </div>
            <div class="prompt-panel-body flosc-admin-panel-body" id="flosc-admin-panel-body">
                ${sectionsHtml}${offersHtml}
                <div class="flosc-admin-pill-group flosc-admin-pill-group-quiz">
                    <div class="flosc-admin-pill-group-title">🎤 Quiz Cycle</div>
                    <div class="flosc-admin-pill-row">
                        ${this.flowHasQuizConfigured() ? `<button class="flosc-style-pill flosc-admin-pill flosc-admin-pill-quiz" data-action="${this.config.defaultQuizAction || 'open_quiz'}">🎤 Start Quiz</button>` : ''}
                    </div>
                </div>
            </div>`;

        // Wire chevron toggle to collapse/expand the panel body
        const toggleHeader = panel.querySelector('#flosc-admin-panel-toggle');
        if (toggleHeader) {
            const toggleHandler = () => {
                const body = document.getElementById('flosc-admin-panel-body');
                const chevron = document.getElementById('flosc-admin-panel-chevron');
                if (body && chevron) {
                    const collapsed = body.classList.contains('flosc-hidden');
                    body.classList.toggle('flosc-hidden', !collapsed);
                    chevron.textContent = collapsed ? '▼' : '▶';
                }
            };
            toggleHeader.addEventListener('click', toggleHandler);
            this.activeEventListeners.set(toggleHeader, toggleHandler);
        }

        // Wire click handlers for ALL pills (admin pills + offer pills)
        panel.querySelectorAll('button.flosc-style-pill').forEach(btn => {
            const handler = () => {
                const label = btn.querySelector('.flosc-autoprompt-label')?.textContent || btn.textContent.trim();

                // Offer pill — show the offer
                const offerId = btn.dataset.offerId;
                if (offerId) {
                    this.addMessage('user', label);
                    this.ivr.messageCount++;
                    this.ivr.lastInteraction = Date.now();
                    this.log('[FLOSC-ADMIN] Offer pill clicked:', offerId);
                    setTimeout(() => this.showOffer(offerId, { source: 'user' }), 300);
                    return;
                }

                // Action pill — fire the IVR action
                const action = btn.dataset.action;
                if (action) {
                    this.log('[FLOSC-ADMIN] Action pill clicked:', action);
                    this.performIVRAction(action);
                    return;
                }

                // Regular pill — send as user message
                const userInput = btn.dataset.message;
                if (userInput && this.chatInput) {
                    this.chatInput.value = userInput;
                    this.sendMessage();
                }
            };
            btn.addEventListener('click', handler);
            this.activeEventListeners.set(btn, handler);
        });

        // Insert before the composer (same pattern as floscRenderUserAutoPrompts)
        const composer = document.getElementById('flosc_input_composer');
        if (composer && composer.parentElement) {
            composer.parentElement.insertBefore(panel, composer);
        }

        this.log('[FLOSC-ADMIN] Test panel rendered:', Object.keys(groups).map(s => `${s}:${groups[s].length}`).join(', '), '+ offers:', testOffers.length);
    }

    // v9.1.1: Memory leak prevention - cleanup event listeners
    floscCleanupUserAutoPrompts() {
        // Remove all tracked event listeners
        this.activeEventListeners.forEach((handler, element) => {
            if (element && element.parentNode) {
                element.removeEventListener('click', handler);
            }
        });
        this.activeEventListeners.clear();
        
        // Remove DOM element
        const existing = document.getElementById('flosc_input_user_autoprompts_panel');
        if (existing) {
            existing.remove();
        }
    }

    floscRenderUserAutoPrompts(replies) {
        // v9.1.1: Clean up old listeners first
        this.floscCleanupUserAutoPrompts();

        if (replies.length === 0) return;

        // v1.0.8: Different panel names for different user states
        let panelName;
        if (this.state === 'visitor') {
            panelName = 'IntroPanel';
        } else if (this.state === 'guest') {
            panelName = 'PromptPanel';
        } else {
            panelName = 'MemberPromptPanel';
        }

        // v3.0.7: Use admin-configured header text from WP dashboard (autopromptHeaders)
        const stateKey = this.state === 'visitor' ? 'visitor' : (this.state === 'guest' ? 'guest' : 'member');
        const eyebrowText = (this.config.autopromptHeaders && this.config.autopromptHeaders[stateKey])
            ? this.config.autopromptHeaders[stateKey]
            : 'Try these AutoPrompts!';
        
        // v2.0.1: AutoPromptPanel always renders horizontal pills in carousel
        // Card style preserved in IVR data for future in-chat use, but carousel is pill-only

        const container = document.createElement('div');
        container.id = 'flosc_input_user_autoprompts_panel';
        container.className = 'prompt-panel prompt-panel-inline';
        
        // v2.0.1: Always use pills container for horizontal flow
        const containerClass = 'flosc-pills-container';
        
        // v2.0.1: Force pill style for all carousel items regardless of IVR MessageStyle
        // v3.0.5: Offer pills use _pill_label for display, _offer_id for click routing
        // v3.0.7: Admin pills use _trigger_type / _trigger_value for routing
        const buttonsHtml = replies.map(r => {
            const label = r._pill_label || r.user_input || '';
            // Offer pill: from config.offers pill format
            const offerAttr  = r._offer_pill ? ` data-offer-id="${this.escapeHtml(r._offer_id)}"` : '';
            const offerClass = r._offer_pill ? ' flosc-offer-pill' : '';
            // Admin pill trigger routing
            const triggerType  = r._trigger_type  || '';
            const triggerValue = r._trigger_value || '';
            const triggerAttrs = triggerType && triggerType !== 'ai'
                ? ` data-trigger-type="${this.escapeHtml(triggerType)}" data-trigger-value="${this.escapeHtml(triggerValue)}"`
                : '';
            return `
            <button class="flosc-style-pill${offerClass}" data-message="${this.escapeHtml(r.user_input)}"${offerAttr}${triggerAttrs}>
                ${r.icon ? `<span class="flosc-autoprompt-icon">${r.icon}</span>` : ''}
                <span class="flosc-autoprompt-label">${this.escapeHtml(label)}</span>
            </button>`;
        }).join('');
        
        // v8.1.0: DA1NI5 Michel Duck and Show Chevron — replaces header row + X-close
        // https://example.com/project
        container.setAttribute('data-da1-panel', '');
        container.setAttribute('data-da1-state', 'shown');
        container.innerHTML = `
            <button class="da1-close" aria-label="Close ${panelName}">&times;</button>
            <button class="da1-chevron" aria-expanded="true" aria-label="Hide ${panelName}"></button>
            <div class="da1-content">
                <div class="da1-content-inner">
                    <div class="flosc-carousel-container">
                        <button class="flosc-carousel-arrow flosc-carousel-prev" aria-label="Previous">‹</button>
                        <div class="flosc-carousel-track ${containerClass}">
                            ${buttonsHtml}
                        </div>
                        <button class="flosc-carousel-arrow flosc-carousel-next" aria-label="Next">›</button>
                    </div>
                </div>
            </div>
        `;

        // Button click handlers
        // v9.1.1: Track listeners for cleanup
        // v1.3.9: Added flosc-style-feature to selector (was missing, causing feature cards to not respond to clicks)
        // v3.0.5: Offer pills (data-offer-id) route to showOffer(), IVR pills route to floscHandleUserAutoPrompt()
        container.querySelectorAll('button.flosc-style-pill, button.flosc-style-chip, button.flosc-style-button, button.flosc-style-card, button.flosc-style-feature').forEach(btn => {
            const handler = () => {
                const label = btn.querySelector('.flosc-autoprompt-label')?.textContent || '';

                // v3.0.5: Config.offers pill — show a chat bubble then trigger the offer
                const offerId = btn.dataset.offerId;
                if (offerId) {
                    this.addMessage('user', label);
                    this.ivr.messageCount++;
                    this.ivr.lastInteraction = Date.now();
                    this.log('[FLOSC-OFFER] Offer pill clicked:', offerId);
                    setTimeout(() => this.showOffer(offerId, { source: 'user' }), 300);
                    return;
                }

                // v3.0.7: Admin pill with explicit trigger type
                const triggerType  = btn.dataset.triggerType;
                const triggerValue = btn.dataset.triggerValue;
                if (triggerType && triggerType !== 'ai') {
                    if (triggerType === 'offer') {
                        this.addMessage('user', label);
                        this.ivr.messageCount++;
                        this.ivr.lastInteraction = Date.now();
                        this.log('[FLOSC-ADMIN-PILL] Offer trigger:', triggerValue);
                        setTimeout(() => this.showOffer(triggerValue, { source: 'user' }), 300);
                        return;
                    }
                    if (triggerType === 'action') {
                        this.addMessage('user', label);
                        this.ivr.messageCount++;
                        this.ivr.lastInteraction = Date.now();
                        this.log('[FLOSC-ADMIN-PILL] Action trigger:', triggerValue);
                        this.performIVRAction(triggerValue);
                        return;
                    }
                }

                const userInput = btn.dataset.message;
                if (this.chatInput) {
                    this.chatInput.value = userInput;
                    this.sendMessage();
                }
            };
            
            btn.addEventListener('click', handler);
            this.activeEventListeners.set(btn, handler);
        });

        // v8.1.0: DA1NI5 Michel Duck and Show Chevron toggle
        const chevronBtn = container.querySelector('.da1-chevron');
        if (chevronBtn) {
            const chevronHandler = () => {
                const currentState = container.getAttribute('data-da1-state');
                if (currentState === 'shown') {
                    // Duck — collapse panel, chevron flips up
                    container.setAttribute('data-da1-state', 'ducked');
                    chevronBtn.setAttribute('aria-expanded', 'false');
                    chevronBtn.setAttribute('aria-label', `Show ${panelName}`);
                    this.addMessage('user', `Hide ${panelName}`);
                    this.addMessage('assistant', `${panelName} hidden. Type "Show ${panelName}," or click the upward chevron to bring it back.`);
                    // addMessage('user') adds flosc-hidden to the panel — remove it so ducked chevron stays visible
                    container.classList.remove('flosc-hidden');
                } else {
                    // Show — expand panel, chevron flips down
                    container.setAttribute('data-da1-state', 'shown');
                    chevronBtn.setAttribute('aria-expanded', 'true');
                    chevronBtn.setAttribute('aria-label', `Hide ${panelName}`);
                    this.addMessage('user', `Show ${panelName}`);
                    this.addMessage('assistant', `${panelName} restored.`);
                    // addMessage('user') adds flosc-hidden to the panel — remove it so panel is visible
                    container.classList.remove('flosc-hidden');
                }
            };
            chevronBtn.addEventListener('click', chevronHandler);
            this.activeEventListeners.set(chevronBtn, chevronHandler);
        }

        // v8.1.0: X close button — removes panel entirely until "Show IntroPanel" or hard refresh
        const closeBtn = container.querySelector('.da1-close');
        if (closeBtn) {
            const closeHandler = () => {
                container.classList.add('flosc-hidden');
                container.setAttribute('data-da1-state', 'closed');
                this._panelDismissed = true;
                this.addMessage('assistant', `${panelName} closed. Type "Show ${panelName}" to bring it back.`);
                this.log('[FLOSC] AutoPromptPanel closed by user via X button');
            };
            closeBtn.addEventListener('click', closeHandler);
            this.activeEventListeners.set(closeBtn, closeHandler);
        }

        // v1.6.9: Insert BEFORE composer so it stays within the flex column and doesn't overflow viewport
        const composer = document.getElementById('flosc_input_composer');
        if (composer && composer.parentElement) {
            composer.parentElement.insertBefore(container, composer);
        }
        
        // v1.1.0: Initialize carousel with overflow detection (user-controlled, no auto-rotate)
        this.initCarouselOverflow(container);
    }

    /**
     * ============================================================================
     * CAROUSEL - v1.2.3 (True Infinite Carousel)
     * ============================================================================
     * Previous versions had issues:
     * 1. Arrows only shown on overflow
     * 2. "Bounced" at ends instead of cycling
     * 
     * This version: TRUE INFINITE CYCLING
     * - Arrows ALWAYS visible (cycle through items)
     * - Items wrap around infinitely: 1,2,3,4,5,6,7 -> 4,5,6,7,1,2,3 when scrolling right
     * - Uses DOM manipulation to move items, not just scroll position
     * ============================================================================
     */
    initCarouselOverflow(container) {
        const track = container.querySelector('.flosc-carousel-track');
        const prevBtn = container.querySelector('.flosc-carousel-prev');
        const nextBtn = container.querySelector('.flosc-carousel-next');
        const carouselEl = container.querySelector('.flosc-carousel-container');

        if (!track || !prevBtn || !nextBtn || !carouselEl) {
            this.logWarn('[FLOSC Carousel] Missing elements:', { 
                track: !!track, 
                prevBtn: !!prevBtn, 
                nextBtn: !!nextBtn,
                carouselEl: !!carouselEl 
            });
            return;
        }

        const items = Array.from(track.children);
        const itemCount = items.length;
        
        this.log('[FLOSC Carousel] Initializing infinite carousel. Items:', itemCount);

        // Always show arrows if there's more than 1 item
        if (itemCount > 1) {
            carouselEl.classList.add('has-overflow');
            prevBtn.classList.remove('flosc-hidden');
            nextBtn.classList.remove('flosc-hidden');
        } else {
            prevBtn.classList.add('flosc-hidden');
            nextBtn.classList.add('flosc-hidden');
            return; // No carousel needed for single item
        }

        // Animation lock to prevent rapid clicks
        let isAnimating = false;
        const ANIMATION_DURATION = 250; // v2.0.1: Slightly faster for smoother feel

        // Scroll by one item width + gap
        const getScrollAmount = () => {
            const firstItem = track.children[0];
            if (!firstItem) return 200;
            const style = window.getComputedStyle(track);
            const gap = parseInt(style.gap) || 12;
            return firstItem.offsetWidth + gap;
        };

        // Move first item to end (scroll right / next)
        const rotateNext = () => {
            if (isAnimating || track.children.length < 2) return;
            isAnimating = true;
            
            const scrollAmount = getScrollAmount();

            // v2.0.1: Smooth ease-out for natural swipe-like feel
            const anim = track.animate(
                [
                    { transform: 'translateX(0)' },
                    { transform: `translateX(-${scrollAmount}px)` }
                ],
                {
                    duration: ANIMATION_DURATION,
                    easing: 'cubic-bezier(0.25, 0.1, 0.25, 1)'
                }
            );

            anim.onfinish = () => {
                // Move first item to end
                const firstItem = track.children[0];
                track.appendChild(firstItem);

                isAnimating = false;
            };
        };

        // Move last item to beginning (scroll left / prev)
        const rotatePrev = () => {
            if (isAnimating || track.children.length < 2) return;
            isAnimating = true;
            
            const scrollAmount = getScrollAmount();
            
            // Move last item to beginning FIRST (no animation)
            const lastItem = track.children[track.children.length - 1];
            track.insertBefore(lastItem, track.children[0]);

            // v2.0.1: Smooth ease-out for natural swipe-like feel
            const anim = track.animate(
                [
                    { transform: `translateX(-${scrollAmount}px)` },
                    { transform: 'translateX(0)' }
                ],
                {
                    duration: ANIMATION_DURATION,
                    easing: 'cubic-bezier(0.25, 0.1, 0.25, 1)'
                }
            );

            anim.onfinish = () => {
                isAnimating = false;
            };
        };

        // Click handlers
        prevBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            rotatePrev();
        });

        nextBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            rotateNext();
        });

        // Swipe support
        let touchStartX = 0;
        let touchStartY = 0;
        
        track.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        track.addEventListener('touchend', (e) => {
            const diffX = touchStartX - e.changedTouches[0].clientX;
            const diffY = Math.abs(touchStartY - e.changedTouches[0].clientY);
            
            // Only trigger if horizontal swipe is larger than vertical
            if (Math.abs(diffX) > 50 && Math.abs(diffX) > diffY) {
                if (diffX > 0) {
                    rotateNext(); // Swipe left = next
                } else {
                    rotatePrev(); // Swipe right = prev
                }
            }
        });

        // Keyboard support (when focused)
        carouselEl.setAttribute('tabindex', '0');
        carouselEl.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight') {
                e.preventDefault();
                rotateNext();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                rotatePrev();
            }
        });
    }

    floscHandleUserAutoPrompt(messageName) {
        // MTS-2026-02-02: [FIX] Check BOTH API messages AND full config messages
        // API only returns phase-filtered messages, but global messages like user_status_check
        // are in the full config set
        const msg = this.ivr.messages[messageName] || this.config.ivrMessages?.[messageName];
        if (!msg) {
            this.logError('FLOSC: IVR message not found:', messageName, '- Check IVR.md configuration');
            return;
        }
        
        // v1.8.1: If the IVR message defines an action (e.g. open_free_lesson, open_quiz),
        // execute it directly instead of sending user_input as a chat message.
        // Previously, actions only fired in the else branch (when chatInput was missing),
        // so pills like "View my free lesson!" sent text to the API and got a phase
        // default response ("Thanks for your interest!") instead of calling openFreeLesson().
        if (msg.action) {
            // Show the user's pill text as a chat bubble for visual feedback
            this.addMessage('user', msg.user_input);
            this.ivr.messageCount++;
            this.ivr.lastInteraction = Date.now();
            
            // Show the IVR message content, then perform the action
            const content = this.replaceVariables(msg.content);
            setTimeout(() => {
                const el = this.addMessage('assistant', content);
                if (el && msg.name) el.setAttribute('data-message-name', msg.name);
                this.performIVRAction(msg.action);
                this.floscShowUserAutoPrompts();
            }, 300);
            return;
        }
        
        // No action defined — send as normal chat message
        if (this.chatInput) {
            this.chatInput.value = msg.user_input;
            this.sendMessage();
        } else {
            this.addMessage('user', msg.user_input);
            this.ivr.messageCount++;
            this.ivr.lastInteraction = Date.now();
            const content = this.replaceVariables(msg.content);
            setTimeout(() => {
                const el = this.addMessage('assistant', content);
                if (el && msg.name) el.setAttribute('data-message-name', msg.name);
                this.floscShowUserAutoPrompts();
            }, 300);
        }
    }

    _getValidBadgeUrl() {
        const badgeUrl = (this.config.identity?.badgeUrl || '').trim();
        if (!badgeUrl) {
            return '';
        }

        try {
            const parsed = new URL(badgeUrl, window.location.href);
            const isHttp = parsed.protocol === 'http:' || parsed.protocol === 'https:';
            const looksLikeImage = /\.(png|jpe?g|gif|webp|svg)(?:$|\?)/i.test(parsed.pathname);
            return isHttp && looksLikeImage ? parsed.href : '';
        } catch (e) {
            return '';
        }
    }

    _appendWelcomeBadge(content, productName = '') {
        const baseContent = String(content || '').trim();
        if (baseContent === '' || /flosc-welcome-badge/i.test(baseContent)) {
            return baseContent;
        }

        const badgeUrl = this._getValidBadgeUrl();
        if (!badgeUrl) {
            return baseContent;
        }

        const safeProductName = productName || this.config.identity?.name || 'FLOSC';
        return `${baseContent}\n<div class="flosc-welcome-badge-wrap"><img src="${badgeUrl}" alt="${safeProductName}" class="flosc-welcome-badge"></div>`;
    }

    /**
     * v1.9.5: Generate an AI-powered welcome greeting.
     * Uses the IVR welcome message as guidance so AI crafts a natural,
     * short greeting consistent with the configured product personality.
     * Falls back to IVR content (or hardcoded) if the API call fails.
     */
    async _generateAIWelcome(ivrWelcomeMsg) {
        const productName = this.config.identity?.name || 'FLOSC';
        const badgeUrl = this._getValidBadgeUrl();
        const badge = badgeUrl ? `<div class="flosc-welcome-badge-wrap"><img src="${badgeUrl}" alt="${productName}" class="flosc-welcome-badge"></div>` : '';
        // Flow-neutral welcome: no "badge" and no learning-specific framing (those
        // Badge labels are flow-specific — avoid hardcoding a product badge name.
        // slug). The frontend still inserts a real badge image below, but only when
        // this flow actually has one configured.
        const syntheticMessage = `[SYSTEM: Generate a brief, warm welcome greeting for a new visitor to ${productName}, in your own voice for this site. Vary it each time. Open with a friendly hello and what ${productName} helps with, then invite them to share what they're looking for. Two short sentences. Do NOT output any badge, image, placeholder, slug, or markup — only the greeting text.]`;
        
        try {
            const response = await this.callAPI(syntheticMessage, ivrWelcomeMsg || null);
            this.hideTyping();
            
            if (response) {
                const md = response.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\*([^*]+)\*/g, '<em>$1</em>').replace(/~~([^~]+)~~/g, '<del>$1</del>');
                const lines = md.split(/(?<=[.!?])\s+/);
                const firstPart = lines[0] || md;
                const secondPart = lines.slice(1).join(' ') || '';
                const finalContent = firstPart + '\n' + badge + (secondPart ? '\n' + secondPart : '');
                const msgEl = this.addMessage('assistant', finalContent, true);
                if (msgEl && ivrWelcomeMsg?.name) {
                    msgEl.setAttribute('data-message-name', ivrWelcomeMsg.name);
                }
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', finalContent);
                }
            } else {
                this._showFallbackWelcome(ivrWelcomeMsg, productName);
            }
        } catch (e) {
            this.logError('FLOSC: AI welcome failed:', e);
            this.hideTyping();
            this._showFallbackWelcome(ivrWelcomeMsg, productName);
        }
    }
    
    _showFallbackWelcome(ivrMsg, productName) {
        const badgeUrl = this._getValidBadgeUrl();
        const badge = badgeUrl ? `<div class="flosc-welcome-badge-wrap"><img src="${badgeUrl}" alt="${productName}" class="flosc-welcome-badge"></div>` : '';
        if (ivrMsg) {
            let content = this.replaceVariables(ivrMsg.content);
            content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/~~([^~]+)~~/g, '<del>$1</del>');
            const parts = content.split(/(?<=[.!?])\s+/);
            const first = parts[0] || content;
            const rest = parts.slice(1).join(' ') || '';
            const finalContent = first + '\n' + badge + (rest ? '\n' + rest : '');
            const msgEl = this.addMessage('assistant', finalContent, true);
            if (msgEl && ivrMsg.name) msgEl.setAttribute('data-message-name', ivrMsg.name);
        } else {
            // Brand-neutral: tagline from Identity params when present.
            const tagline = String(this.config?.identity?.tagline || '').trim();
            const fallback = this.state === 'visitor'
                ? (tagline
                    ? `Welcome to ${productName}. ${tagline}\n${badge}`
                    : `Welcome to ${productName}.\n${badge}\nHow can I help you today?`)
                : `Welcome back!\n${badge}\nHow can I help you today?`;
            this.addMessage('assistant', fallback, true);
        }
    }

    showIVRMessage(msg) {
        let content = this.replaceVariables(msg.content);

        if (msg.type === 'offer') {
            this.showOfferMessage(msg);
            return;
        }

        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        const isWelcomeMessage = !!(msg && msg.name && String(msg.name).includes('welcome'));
        if (this.state === 'visitor' && isWelcomeMessage && !/flosc-welcome-badge/i.test(content)) {
            const productName = this.config.identity?.name || 'FLOSC';
            const badgeUrl = this._getValidBadgeUrl();
            if (badgeUrl) {
                content += `\n<div class="flosc-welcome-badge-wrap"><img src="${badgeUrl}" alt="${productName}" class="flosc-welcome-badge"></div>`;
            }
        }

        // v8.0.9: Add message to DOM with data attribute for idempotency checking
        // v1.9.7: Pass isHtml=true — content already has HTML from markdown conversion above
        const messageDiv = this.addMessage('assistant', content, true);
        if (messageDiv && msg.name) {
            messageDiv.setAttribute('data-message-name', msg.name);

            // v1.7.9: Set flag when quiz results are shown to ensure offers appear AFTER results
            if (msg.name.includes('quiz_results')) {
                this.ivr.context.quiz_results_shown = true;
                this.log('Quiz results displayed, offers now eligible');
            }
        }

        if (msg.offer_id) {
            this.ivr.shownThisSession['offer_' + msg.offer_id] = true;
        }

        if (this.state === 'visitor' && isWelcomeMessage) {
            this.saveVisitorMessage('assistant', content);
        }

        // v07.09: Track message shown persistently
        this.trackMessageShown(msg.name, msg.offer_id);
    }

    async trackMessageShown(messageName, offerId = null) {
        try {
            await this.authFetch(this.config.apiUrl + '/ivr/track', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    message_name: messageName,
                    offer_id: offerId,
                    offer_state: offerId ? 'shown' : null
                })
            });
        } catch (e) {
            this.logWarn('FLOSC: Could not track message', e);
        }
    }

    /**
     * v3.0.5: Match user message against offer reveal phrases (exact match only).
     * Returns the matched offer object, or null if no match.
     * AI interpretation matching is NOT done here — that goes through the server.
     */
    _matchOfferRevealPhrase(userMessage) {
        if (!Array.isArray(this.config.offers)) return null;
        const normalized = userMessage.trim().toLowerCase();
        if (!normalized) return null;

        for (const offer of this.config.offers) {
            if (!offer.reveal_phrase) continue;
            // Only match exact-type phrases client-side
            if (offer.match_type && offer.match_type !== 'exact') continue;
            // Skip inactive/purchased
            if (offer.status && offer.status !== 'active') continue;
            if (this.offers.shownOffers?.has(offer.id)) continue;

            const phrase = offer.reveal_phrase.trim().toLowerCase();
            if (phrase && phrase === normalized) {
                return offer;
            }
        }
        return null;
    }

    /**
     * v3.0.5: Extract [ACTION:...] tags from AI response text.
     * AI interpretation offers instruct the AI to include [ACTION:show_offer_ID] tags.
     * Returns { cleanText: string (tags removed), actions: string[] }.
     */
    _extractActionTags(text) {
        if (!text) return { cleanText: text, actions: [] };
        const actionRegex = /\[ACTION:([^\]]+)\]/g;
        const actions = [];
        let match;
        while ((match = actionRegex.exec(text)) !== null) {
            actions.push(match[1].trim());
        }
        // Remove action tags from displayed text
        const cleanText = text.replace(/\s*\[ACTION:[^\]]+\]\s*/g, '').trim();
        return { cleanText, actions };
    }

    /** True if this offer was auto- or user-shown (session flags or show count). */
    _wasOfferShown(offerId) {
        if (!offerId) return false;
        if (this.offers.shownOffers?.has(offerId)) return true;
        if (this.ivr.shownThisSession?.['offer_' + offerId]) return true;
        if (this.ivr.shownThisSession?.['offer_shown_' + offerId]) return true;
        if (this._getOfferShowCount(offerId) > 0) return true;
        return false;
    }

    /**
     * Whether an offer may be auto-presented (IVR, timer, after-offer chain).
     * source 'user' always allows (explicit CTA / reveal phrase / pill click).
     */
    canPresentOffer(offerId, { source = 'auto' } = {}) {
        if (!offerId) return false;
        if (source === 'user') return true;

        const offer = this.getOfferData(offerId);
        if (!offer) return false;

        if (this.offers.dismissedOffers?.has(offerId)
            || this.ivr.shownThisSession?.['offer_dismissed_' + offerId]) {
            return false;
        }
        if (this.offers.purchasedOffers?.has(offerId)
            || this.user?.purchased) {
            return false;
        }
        if (this.state === 'member' || this.state === 'admin') {
            return false;
        }

        // Chain: only after parent offer was shown.
        const revealEvent = String(offer.reveal_event || offer.reveal?.event || 'manual').toLowerCase();
        const parentId = String(offer.after_offer_id || offer.afterOfferId || '').trim();
        if (revealEvent === 'after_offer') {
            if (!parentId || !this._wasOfferShown(parentId)) {
                return false;
            }
        }

        // Offer condition (e.g. offer_shown_parent && !is_member). Fail closed on error.
        const cond = String(offer.condition || '').trim();
        if (cond && cond !== 'always' && typeof this.evaluateCondition === 'function') {
            try {
                if (!this.evaluateCondition(cond)) {
                    return false;
                }
            } catch (e) {
                this.logWarn('[FLOSC-OFFER] condition error', offerId, e);
                return false;
            }
        }

        const maxShows = Math.max(0, parseInt(
            offer.frequency_max_shows ?? offer.frequency?.max_shows ?? 1,
            10
        ));
        // maxShows 0 = unlimited auto shows
        if (maxShows > 0) {
            const shownCount = this._getOfferShowCount(offerId);
            if (shownCount >= maxShows) {
                return false;
            }
        }
        return true;
    }

    _offerCountStorage(offerId) {
        const offer = this.getOfferData(offerId);
        const scope = String(offer?.frequency_scope || offer?.frequency?.scope || 'browser').toLowerCase();
        return scope === 'session' ? sessionStorage : localStorage;
    }

    _getOfferShowCount(offerId) {
        try {
            const raw = this._offerCountStorage(offerId).getItem('flosc_offer_show_counts');
            const map = raw ? JSON.parse(raw) : {};
            return parseInt(map[offerId] || 0, 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    _incrementOfferShowCount(offerId) {
        try {
            const store = this._offerCountStorage(offerId);
            const raw = store.getItem('flosc_offer_show_counts');
            const map = raw ? JSON.parse(raw) : {};
            map[offerId] = (parseInt(map[offerId] || 0, 10) || 0) + 1;
            store.setItem('flosc_offer_show_counts', JSON.stringify(map));
        } catch (e) { /* ignore */ }
    }

    /**
     * Schedule offer presentation after reveal.delay_seconds when event matches.
     * Events: chat_start | lesson_open | quiz | login | after_offer | manual
     * For after_offer, eventName is "after_offer:" + parentOfferId.
     */
    scheduleOfferReveal(offerId, eventName) {
        const offer = this.getOfferData(offerId);
        if (!offer) return;
        const revealEvent = String(offer.reveal_event || offer.reveal?.event || 'manual').toLowerCase();
        if (revealEvent === 'manual' || revealEvent === '') return;

        const eventStr = String(eventName || '');
        if (revealEvent === 'after_offer') {
            const parentId = String(offer.after_offer_id || offer.afterOfferId || '').trim();
            if (!parentId) return;
            if (eventStr !== 'after_offer:' + parentId && eventStr !== 'after_offer') return;
            if (!this._wasOfferShown(parentId)) return;
        } else if (revealEvent !== eventStr.toLowerCase()) {
            return;
        }

        if (!this.canPresentOffer(offerId, { source: 'auto' })) return;

        const delaySec = Math.max(0, parseInt(
            offer.reveal_delay_seconds ?? offer.reveal?.delay_seconds ?? 0,
            10
        ) || 0);
        const key = 'flosc_reveal_sched_' + offerId;
        if (this._offerRevealTimers?.[key]) {
            clearTimeout(this._offerRevealTimers[key]);
        }
        this._offerRevealTimers = this._offerRevealTimers || {};
        this.log('[FLOSC-OFFER] schedule', offerId, 'event=', eventStr, 'delaySec=', delaySec);
        this._offerRevealTimers[key] = setTimeout(() => {
            if (this.canPresentOffer(offerId, { source: 'auto' })) {
                this.showOffer(offerId, { source: 'auto' });
            }
        }, delaySec * 1000);
    }

    /** Schedule all active offers whose reveal_event matches eventName. */
    _scheduleOffersForEvent(eventName) {
        const offers = this.config.offers || [];
        const list = Array.isArray(offers) ? offers : Object.values(offers || {});
        list.forEach((o) => {
            if (!o || !o.id) return;
            const active = o.active === true || o.active === 1 || o.active === '1'
                || String(o.status || '').toLowerCase() === 'active';
            if (!active) return;
            this.scheduleOfferReveal(o.id, eventName);
        });
    }

    /**
     * When offer parentId is shown, schedule every active child with
     * reveal_event=after_offer and after_offer_id=parentId (delay from each child).
     */
    _scheduleOffersAfterOfferShown(parentOfferId) {
        if (!parentOfferId) return;
        this._scheduleOffersForEvent('after_offer:' + parentOfferId);
    }

    // v1.6.2: Bridge from handleAction('show_offer_*') to showOfferMessage()
    showOffer(offerId, { source = 'auto' } = {}) {
        if (!this.canPresentOffer(offerId, { source })) {
            this.log('[FLOSC-OFFER] canPresentOffer blocked:', offerId, source);
            return;
        }
        const offer = this.getOfferData(offerId);
        // v1.7.1: Build human-readable content — never show raw IDs
        let offerContent = offer?.description || offer?.content || '';
        if (!offerContent || offerContent === offerId) {
            // Use name but humanize it (replace underscores, remove trailing numbers)
            const name = offer?.name || offerId;
            offerContent = name.replace(/_/g, ' ').replace(/\s*\d+$/, '').replace(/\b\w/g, c => c.toUpperCase());
        }
        const msg = {
            offer_id: offerId,
            content: offerContent,
            display_format: offer?.display_format || 'card',
            cta: offer?.cta || '🔓 Get Full Access Now',
            price: offer?.display_price || (offer?.price ? `$${offer.price}` : ''),
            type: 'offer',
            _presentSource: source,
        };
        this.showOfferMessage(msg);
    }

    // MTS-2026-02-03: [OFFER-DISPLAY] Comprehensive offer display system
    // Supports multiple formats: card, pill, compact, banner, featured, text, inline-checkout
    showOfferMessage(msg) {
        const offer = this.getOfferData(msg.offer_id);
        const source = msg._presentSource || 'auto';
        if (!this.canPresentOffer(msg.offer_id, { source })) {
            this.log('[FLOSC-OFFER] showOfferMessage blocked:', msg.offer_id);
            return;
        }
        const displayFormat = this.resolveOfferDisplayFormat(msg, offer);
        
        this.log('[FLOSC-OFFER] Showing offer:', msg.offer_id, 'format:', displayFormat);
        
        // Track offer shown
        this.offers.shownOffers.add(msg.offer_id);
        this.ivr.shownThisSession['offer_' + msg.offer_id] = true;
        this.ivr.shownThisSession['offer_shown_' + msg.offer_id] = true;
        this._incrementOfferShowCount(msg.offer_id);
        this._saveOfferStates(); // v1.6.2: Persist across refresh

        // Chain: start timers for offers that wait on this offer.
        this._scheduleOffersAfterOfferShown(msg.offer_id);
        
        // v1.6.2: If offer has a content source (HtmlFile/WooProduct/PostID),
        // load it and inject into the offer content before rendering.
        const sourceContent = msg.html_file || offer?.html_file 
                     || msg.woo_product || offer?.woo_product
                     || msg.post_id || offer?.post_id;
        if (sourceContent) {
            this.loadOfferContentSource(msg, offer, displayFormat);
            return;
        }
        
        this.renderOfferByFormat(msg, offer, displayFormat);
    }

    /**
     * Lessons (complimentary + library) only when this flow’s Lessons tab
     * is configured (FLOSC_CONFIG.servesLessons from flosc_flows / flosc_flow_*).
     */
    flowServesLessons() {
        return this.config?.servesLessons === true;
    }

    /**
     * This flow has quiz(es) in its own floscFlow parameters.
     * Empty enabledQuizzes + no default quiz keys → no quiz (never invent sample IDs).
     */
    flowHasQuizConfigured() {
        const enabled = Array.isArray(this.config?.enabledQuizzes)
            ? this.config.enabledQuizzes.filter((id) => String(id || '').trim() !== '')
            : [];
        if (enabled.length > 0) {
            return true;
        }
        const a = String(this.config?.defaultAudioQuizId || '').trim();
        const t = String(this.config?.defaultTextQuizId || '').trim();
        const q = String(this.config?.quizType || '').trim();
        return !!(a || t || q);
    }

    /** Short denial when this flow has no quiz. */
    denyQuizOnThisFlow(message = '') {
        const name = String(this.config?.productName || this.config?.identity?.name || 'this chat').trim();
        const msg = `${name} doesn’t include a quiz — I can help with other questions here.`;
        this.addMessage('assistant', msg, false);
        if (message) {
            this.logClientChatTurn(message, msg, { source: 'quiz_not_on_flow' });
        }
        return true;
    }

    /**
     * Server already filters FLOSC_USER.lastQuizData by flow quiz params.
     * Client only checks: flow has quiz config + (server data or this-flow local pending).
     */
    shouldSurfaceQuizResults(quizData = null) {
        if (!this.flowHasQuizConfigured()) {
            return false;
        }
        const data = quizData !== null && quizData !== undefined
            ? quizData
            : this.user?.lastQuizData;
        if (data) {
            return true;
        }
        if (typeof this._getPendingLocalQuizResult === 'function' && this._getPendingLocalQuizResult()) {
            return true;
        }
        return false;
    }

    /** Short denial when a non-lesson flow receives a lesson ask. */
    denyLessonsOnThisFlow(message = '') {
        const name = String(this.config?.productName || this.config?.identity?.name || 'this chat').trim();
        const msg = `${name} doesn’t include lessons — I can help with other questions here.`;
        this.addMessage('assistant', msg, false);
        if (message) {
            this.logClientChatTurn(message, msg, { source: 'lessons_not_on_flow' });
        }
        return true;
    }

    /** True if user text is a free-lesson request (phrase list; flow may extend later). */
    isFreeLessonRequest(message) {
        if (!this.flowServesLessons()) {
            return false;
        }
        const t = String(message || '').toLowerCase().replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ').trim();
        if (!t) return false;
        const phrases = [
            'id like to see my free lessons',
            'i would like to see my free lessons',
            'show me my free lessons',
            'show my free lessons',
            'see my free lessons',
            'view my free lessons',
            'view my free lesson',
            'my free lessons',
            'my free lesson',
            'free lessons',
            'free lesson',
            'unlock my free lesson',
            'open free lesson',
            'open my free lesson',
            'what free lessons',
            'which free lessons',
            'lessons available to me',
            'what lessons do i have',
            'show me my lessons',
            'my lessons',
        ];
        if (phrases.some((p) => t === p || t.includes(p))) return true;
        // free + lesson(s) with see/show/view/get/open
        if (/\bfree\s+lessons?\b/.test(t) && /\b(see|show|view|get|open|want|like|access)\b/.test(t)) {
            return true;
        }
        // "lessons available" without free — still free-lesson path for guests (access-gated loader).
        if (/\blessons?\b/.test(t) && /\b(available|access|show|see|view|list)\b/.test(t)
            && !/\b(offer|price|pricing|package|buy|subscribe|upgrade)\b/.test(t)) {
            return true;
        }
        return false;
    }

    /**
     * Normalize user text for "user is asking for…" matching.
     */
    _normalizeAskText(message) {
        return String(message || '').toLowerCase().replace(/[^\w\s]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    /**
     * User is asking for all offers available (full list).
     */
    isAllOffersRequest(message) {
        const t = this._normalizeAskText(message);
        if (!t) return false;
        if (/\b(all|every|entire|full)\b/.test(t) && /\b(offers?|packages?|pricing|plans?|specials?)\b/.test(t)) {
            return true;
        }
        const phrases = [
            'show me all offers',
            'show all offers',
            'list all offers',
            'all offers available',
            'all offers available to me',
            'every offer available',
            'what are all the offers',
            'show me every offer',
            'list every offer',
            'all packages available',
            'all pricing options',
            'all specials',
            'all specials available',
            'show me all specials',
            'what specials are available',
            'what specials are available to me',
        ];
        return phrases.some((p) => t === p || t.includes(p));
    }

    /**
     * User is asking for offers (next / catalog) — not free lessons.
     */
    isOfferRequest(message) {
        const t = this._normalizeAskText(message);
        if (!t) return false;
        if (this.isFreeLessonRequest(message) && !/\b(offer|price|pricing|package|buy|subscribe|upgrade|plan)\b/.test(t)) {
            return false;
        }
        const phrases = [
            'what are the offers',
            'what offers',
            'which offers',
            'offers available',
            'offer available',
            'available offers',
            'available offer',
            'show me the offer',
            'show me the offers',
            'show me offers',
            'show offers',
            'see the offers',
            'see offers',
            'my offers',
            'the offers',
            'what can i buy',
            'what can i purchase',
            'pricing',
            'what is the price',
            'what are the prices',
            'packages',
            'package options',
            'upgrade options',
            'upgrade offer',
            'how much does it cost',
            'how much is it',
            'subscribe options',
            'membership offer',
            'membership options',
            'what do you offer',
            'special offer',
            'special offers',
            'what specials',
            'which specials',
            'specials available',
            'available specials',
            'show me specials',
            'show specials',
            'my specials',
            'the specials',
        ];
        if (phrases.some((p) => t === p || t.includes(p))) return true;
        if (/\bspecials?\b/.test(t) && /\b(what|which|show|see|list|available|have|get|want|like)\b/.test(t)) {
            return true;
        }
        if (/\boffers?\b/.test(t) && /\b(what|which|show|see|list|available|have|get|want|like)\b/.test(t)) {
            return true;
        }
        if (/\b(pricing|packages?|subscribe|upgrade)\b/.test(t)
            && /\b(what|which|show|see|list|available|options?|how much|cost)\b/.test(t)) {
            return true;
        }
        return false;
    }

    /**
     * Active offers from flow config (array or map).
     */
    _listFlowOffers() {
        const raw = this.config.offers;
        if (!raw) return [];
        const list = Array.isArray(raw) ? raw : Object.values(raw);
        return list.filter((o) => o && (o.id || o.offer_id));
    }

    /**
     * Offer eligible when user is asking for offers (access + condition; not auto frequency).
     */
    isOfferEligibleForUserAsk(offerId) {
        const id = String(offerId || '').trim();
        if (!id) return false;
        const offer = this.getOfferData(id);
        if (!offer) return false;

        const active = offer.active === true || offer.active === 1 || offer.active === '1'
            || String(offer.status || '').toLowerCase() === 'active';
        if (!active) return false;

        if (this.offers.purchasedOffers?.has(id) || this.user?.purchased) {
            return false;
        }
        if (this.offers.dismissedOffers?.has(id)
            || this.ivr.shownThisSession?.['offer_dismissed_' + id]) {
            return false;
        }

        const cond = String(offer.condition || '').trim();
        if (cond && cond !== 'always' && typeof this.evaluateCondition === 'function') {
            try {
                if (!this.evaluateCondition(cond)) return false;
            } catch (e) {
                this.logWarn('[FLOSC-OFFER] user-ask condition error', id, e);
                return false;
            }
        }
        return true;
    }

    _offerPresentationPriority(offer) {
        const n = parseInt(offer?.presentation_priority ?? offer?.priority ?? 100, 10);
        return Number.isFinite(n) ? n : 100;
    }

    /**
     * Eligible offers for this user, sorted by presentation_priority (asc), then name.
     */
    getEligibleOffersForUserAsk() {
        const out = [];
        for (const raw of this._listFlowOffers()) {
            const id = String(raw.id || raw.offer_id || '').trim();
            if (!id || !this.isOfferEligibleForUserAsk(id)) continue;
            const offer = this.getOfferData(id) || raw;
            out.push(offer);
        }
        out.sort((a, b) => {
            const pa = this._offerPresentationPriority(a);
            const pb = this._offerPresentationPriority(b);
            if (pa !== pb) return pa - pb;
            const na = String(a.name || a.id || '');
            const nb = String(b.name || b.id || '');
            return na.localeCompare(nb);
        });
        return out;
    }

    /**
     * Next offer by priority: prefer not-yet-shown; else first eligible.
     */
    getNextOfferForUserAsk() {
        const eligible = this.getEligibleOffersForUserAsk();
        if (!eligible.length) return null;
        const unshown = eligible.find((o) => !this._wasOfferShown(o.id));
        return unshown || eligible[0];
    }

    /**
     * Clickable title list when user asks for all offers (or many cards would not fit).
     */
    renderOfferTitleList(offers, introText) {
        const list = Array.isArray(offers) ? offers : [];
        if (!list.length) return;

        const intro = introText || 'Here are the offers available for you:';
        this.addMessage('assistant', intro, false);

        let html = '<div class="flosc-offer-title-list-wrap"><div class="flosc-offer-title-list" role="list">';
        list.forEach((offer) => {
            const id = String(offer.id || '').trim();
            if (!id) return;
            const title = this.resolveOfferDisplayTitle(offer, null, id);
            const price = offer.display_price
                || (offer.price > 0 ? this.formatOfferMoney(offer.price) : '')
                || '';
            html += `<button type="button" class="flosc-offer-title-item" data-offer-id="${this.escapeHtml(id)}" role="listitem">`
                + `<span class="flosc-offer-title-item-name">${this.escapeHtml(title)}</span>`
                + (price ? `<span class="flosc-offer-title-item-price">${this.escapeHtml(String(price))}</span>` : '')
                + `</button>`;
        });
        html += '</div></div>';

        // Same message pipeline as offer cards so layout/scroll match the chat.
        this.addMessage('assistant', html, true);
        const root = this.chatMessages;
        if (root) {
            root.querySelectorAll('.flosc-offer-title-item').forEach((btn) => {
                if (btn.dataset.floscBound === '1') return;
                btn.dataset.floscBound = '1';
                btn.addEventListener('click', () => {
                    const oid = btn.getAttribute('data-offer-id');
                    if (oid) this.showOffer(oid, { source: 'user' });
                });
            });
        }
    }

    /**
     * Handle "user is asking for offers" / "all offers". Returns true if handled (no AI).
     */
    handleUserOfferAsk(message) {
        const wantAll = this.isAllOffersRequest(message);
        const wantOffer = wantAll || this.isOfferRequest(message);
        if (!wantOffer) return false;

        const eligible = this.getEligibleOffersForUserAsk();
        if (!eligible.length) {
            this.addMessage(
                'assistant',
                this.config.noOffersAvailableMessage
                    || 'No offers are available for your account right now.',
                false
            );
            return true;
        }

        // "Show me all offers" → clickable titles for every eligible offer (priority order).
        if (wantAll) {
            this.renderOfferTitleList(eligible);
            return true;
        }

        // Next-offer ask → one full presentation by presentation_priority (skip already shown).
        const next = this.getNextOfferForUserAsk();
        if (!next) {
            this.addMessage(
                'assistant',
                this.config.noOffersAvailableMessage
                    || 'No offers are available for your account right now.',
                false
            );
            return true;
        }
        this.showOffer(next.id, { source: 'user' });
        return true;
    }
    
    /**
     * v1.6.2: Load external content source for an offer
     * Supports HtmlFile (static HTML), WooProduct (WooCommerce), PostID (WordPress post)
     */
    async loadOfferContentSource(msg, offer, displayFormat) {
        const htmlFile = msg.html_file || offer?.html_file;
        const wooProduct = msg.woo_product || offer?.woo_product;
        const postId = msg.post_id || offer?.post_id;
        
        try {
            let content = msg.content; // fallback
            
            if (htmlFile) {
                // Load static HTML file from plugin directory
                const resp = await this.authFetch(`${this.config.apiUrl}/offer-content?source=html&file=${encodeURIComponent(htmlFile)}`, { credentials: 'same-origin' });
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                }
            } else if (wooProduct) {
                // Load WooCommerce product data
                const resp = await this.authFetch(`${this.config.apiUrl}/offer-content?source=woo&product=${encodeURIComponent(wooProduct)}`, { credentials: 'same-origin' });
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                    if (data.price) msg.price = data.price;
                }
            } else if (postId) {
                // Load WordPress post content
                const resp = await this.authFetch(`${this.config.apiUrl}/offer-content?source=post&id=${encodeURIComponent(postId)}`, { credentials: 'same-origin' });
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                }
            }
            
            msg.content = content;
        } catch (e) {
            this.logWarn('[FLOSC-OFFER] Could not load content source, using fallback', e);
        }
        
        this.renderOfferByFormat(msg, offer, displayFormat);
    }
    
    renderOfferByFormat(msg, offer, displayFormat) {
        // v1.6.2: Error boundary for offer rendering
        try {
        switch (displayFormat) {
            case 'pill':
                this.showOfferPill(msg, offer);
                break;
            case 'compact':
                this.showOfferCompact(msg, offer);
                break;
            case 'banner':
                this.showOfferBanner(msg, offer);
                break;
            case 'featured':
                this.showOfferFeatured(msg, offer);
                break;
            case 'text':
                this.showOfferText(msg, offer);
                break;
            case 'inline-checkout':
                this.showInlineCheckout(msg, offer);
                break;
            case 'card':
            default:
                this.showOfferCard(msg, offer);
                break;
        }
        
        // Track via API
        this.trackOfferShown(msg.offer_id, displayFormat);
        } catch (e) {
            // v1.6.2: Don't let a render error kill the whole app
            this.logError('[FLOSC-OFFER] Render error for', msg.offer_id, 'format:', displayFormat, e);
            // Fallback: show offer content as plain text
            const content = this.replaceVariables(msg.content || 'Special offer available!');
            this.addMessage('assistant', content);
        }
    }
    
    /**
     * Resolve offer product data for display/checkout.
     * Product identity (name, headline, price, badge, CTA, etc.) comes from the
     * Offers registry in WPDB → FLOSC_CONFIG.offers. Message/script rows may supply
     * offer_id and optional body copy, but must never clobber registry name with a
     * message slug (e.g. title "flow_offer" instead of a long offer display name).
     */
    getOfferData(offerId) {
        const wantId = String(offerId || '').trim();
        if (!wantId) {
            return null;
        }

        // Check cache first
        if (this.offers.loaded[wantId]) {
            return this.offers.loaded[wantId];
        }

        const ivrMsg = Object.values(this.ivr.messages || {}).find(
            m => String(m.offer_id || '') === wantId || String(m.name || '') === wantId
        );

        const configOffer = (this.config.offers || []).find(o => String(o.id || '') === wantId)
            || (this.config.adminTestOffers || []).find(o => String(o.id || '') === wantId);

        if (!ivrMsg && !configOffer) {
            return null;
        }

        // Start from script row (timing/id), then apply registry product fields on top.
        const merged = Object.assign({}, ivrMsg || {}, configOffer || {});

        // Explicit registry wins for commercial identity (product model: Offers tab = WPDB).
        if (configOffer) {
            const productKeys = [
                'name', 'headline', 'description', 'cta', 'display_price', 'original_price',
                'guarantee', 'status', 'active', 'type', 'pricing', 'grants', 'features',
                'display_format', 'display_formats', 'processor', 'currency',
                // Scheduling (Offers tab) — must win over IVR script rows
                'reveal_event', 'reveal_delay_seconds', 'after_offer_id',
                'frequency_max_shows', 'frequency_scope', 'condition', 'timer_minutes', 'timer_seconds',
            ];
            for (const key of productKeys) {
                if (configOffer[key] !== undefined && configOffer[key] !== null && configOffer[key] !== '') {
                    merged[key] = configOffer[key];
                }
            }
            if (configOffer.meta && typeof configOffer.meta === 'object') {
                merged.meta = Object.assign({}, (ivrMsg && ivrMsg.meta) || {}, configOffer.meta);
            }
        }

        merged.id = (configOffer && configOffer.id) || merged.id || merged.offer_id || wantId;
        merged.offer_id = merged.offer_id || merged.id;

        // Never show a bare offer id / message slug as the offer title.
        const rawName = String(merged.name || '').trim();
        if (!rawName || rawName === wantId || rawName === merged.id || rawName === merged.offer_id
            || /^[a-z0-9]+(?:_[a-z0-9]+)+$/i.test(rawName)) {
            const headline = String(merged.headline || configOffer?.headline || '').trim();
            if (headline && headline !== wantId && headline !== merged.id) {
                merged.name = headline;
            } else if (configOffer?.name && String(configOffer.name).trim()) {
                merged.name = String(configOffer.name).trim();
            } else {
                merged.name = this.humanizeOfferLabel(wantId);
            }
        }

        this.offers.loaded[wantId] = merged;
        return merged;
    }

    /** Turn offer_id slugs into a last-resort display label (registry name preferred). */
    humanizeOfferLabel(raw) {
        const s = String(raw || '').trim();
        if (!s) {
            return 'Special Offer';
        }
        return s
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .replace(/\b\w/g, (c) => c.toUpperCase())
            .trim();
    }

    /**
     * Title for in-chat offer surfaces: Offers registry name/headline only;
     * never the raw offer_id (e.g. flow_offer).
     */
    resolveOfferDisplayTitle(offer, msg, offerId) {
        const id = String(offerId || offer?.id || msg?.offer_id || msg?.name || '').trim();
        const candidates = [
            offer?.name,
            offer?.headline,
            msg?.headline,
            // msg.name only if it is not the id/slug
            (msg?.name && String(msg.name) !== id) ? msg.name : '',
        ];
        for (const c of candidates) {
            const t = String(c || '').trim();
            if (!t) continue;
            if (id && (t === id || t === offer?.id || t === msg?.offer_id)) continue;
            if (/^[a-z0-9]+(?:_[a-z0-9]+)+$/i.test(t)) continue;
            return t;
        }
        return this.humanizeOfferLabel(id || 'Special Offer');
    }

    /**
     * Prefer an enabled format from Offers registry display_formats;
     * fall back to offer.display_format / msg / card.
     */
    resolveOfferDisplayFormat(msg, offer) {
        const formats = (offer && offer.display_formats && typeof offer.display_formats === 'object')
            ? offer.display_formats
            : {};
        const enabled = Object.keys(formats).filter((k) => {
            const f = formats[k];
            return f && (f.enabled === true || f.enabled === 1 || f.enabled === '1');
        });
        const preferred = String(msg?.display_format || offer?.display_format || 'card').trim() || 'card';
        if (enabled.length === 0) {
            return preferred;
        }
        if (enabled.includes(preferred)) {
            return preferred;
        }
        const rank = ['featured', 'card', 'banner', 'compact', 'pill', 'text', 'inline-checkout'];
        for (const f of rank) {
            if (enabled.includes(f)) {
                return f;
            }
        }
        return enabled[0];
    }

    /** Money for strikethrough / secondary price (450 → $450). */
    formatOfferMoney(value) {
        if (value === undefined || value === null || value === '') {
            return '';
        }
        if (typeof value === 'number' && Number.isFinite(value)) {
            if (value <= 0) {
                return '';
            }
            return `$${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
        }
        const s = String(value).trim();
        if (!s) {
            return '';
        }
        if (/^\$/.test(s) || /yr|mo|month|year|free/i.test(s)) {
            return s;
        }
        const n = parseFloat(s.replace(/[^0-9.]/g, ''));
        if (Number.isFinite(n) && n > 0 && String(n) === s.replace(/[^0-9.]/g, '')) {
            return `$${n.toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
        }
        return s;
    }

    /** Registry description (and optional ** markdown) for offer body. */
    formatOfferRichText(text) {
        let t = this.escapeHtml(String(text || ''));
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/\n/g, '<br>');
        return t;
    }

    /**
     * Features from Offers registry: features string, features[], or grants.features.
     */
    normalizeOfferFeatures(offer, msg) {
        const sources = [
            offer?.features,
            offer?.grants?.features,
            msg?.features,
        ];
        let list = [];
        for (const src of sources) {
            if (src === undefined || src === null || src === '') {
                continue;
            }
            if (Array.isArray(src)) {
                list = src.map((x) => (typeof x === 'string' ? x : (x?.name || String(x || '')))).filter(Boolean);
            } else if (typeof src === 'string') {
                list = src.split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
            }
            if (list.length) {
                break;
            }
        }
        const humanize = (s) => String(s).replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()).trim();
        return list.slice(0, 8).map(humanize);
    }

    /** Countdown seconds from registry; 0 = no timer (never invent 3600). */
    resolveOfferTimerSeconds(msg, offer) {
        const fromMsg = parseInt(msg?.timer, 10);
        if (Number.isFinite(fromMsg) && fromMsg > 0) {
            return fromMsg;
        }
        const sec = parseInt(offer?.timer_seconds, 10);
        if (Number.isFinite(sec) && sec > 0) {
            return sec;
        }
        const min = parseInt(offer?.timer_minutes, 10);
        if (Number.isFinite(min) && min > 0) {
            return min * 60;
        }
        return 0;
    }

    /** Badge text; empty registry badge means no badge (no forced "Limited Time"). */
    resolveOfferBadge(offer, msg) {
        if (offer?.meta && Object.prototype.hasOwnProperty.call(offer.meta, 'badge')) {
            return String(offer.meta.badge || '').trim();
        }
        if (msg?.badge !== undefined && msg?.badge !== null) {
            return String(msg.badge || '').trim();
        }
        return '';
    }
    
    // Format: CARD (default, rich display) — body from Offers registry when available
    showOfferCard(msg, offer) {
        const registryBody = String(offer?.description || offer?.headline || '').trim();
        let content;
        if (registryBody) {
            content = this.formatOfferRichText(this.replaceVariables(registryBody));
        } else {
            content = this.replaceVariables(msg.content || '');
            content = this.escapeHtml(content);
            content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            content = content.replace(/~~([^~]+)~~/g, '<del>$1</del>');
            content = content.replace(/\n/g, '<br>');
        }

        this.offerStartTime = Date.now();
        const timerSeconds = this.resolveOfferTimerSeconds(msg, offer);
        const ctaText = offer?.cta || msg.cta || 'Get Access Now';
        const price = offer?.display_price || this.formatOfferMoney(offer?.price) || msg.price || '';
        const guarantee = String(offer?.guarantee || msg.guarantee || '').trim();

        const offerHtml = `
            <div class="flosc-offer-card" data-offer-id="${msg.offer_id}">
                <button class="flosc-offer-close" aria-label="Dismiss">×</button>
                <div class="flosc-offer-title">${this.escapeHtml(this.resolveOfferDisplayTitle(offer, msg, msg.offer_id))}</div>
                <div class="flosc-offer-content">${content}</div>
                ${timerSeconds > 0 ? `
                <div class="flosc-offer-timer" id="flosc-offer-timer-${msg.offer_id}">
                    <span class="flosc-timer-icon">⏱️</span>
                    <span class="flosc-timer-value">${this.formatTime(timerSeconds)}</span>
                </div>
                ` : ''}
                <button class="flosc-offer-cta flosc-style-button" data-action="checkout_${msg.offer_id}">
                    ${this.escapeHtml(String(ctaText))}${price ? ` <span class="flosc-offer-cta-price">${this.escapeHtml(String(price))}</span>` : ''}
                </button>
                ${guarantee ? `<div class="flosc-offer-guarantee">${this.escapeHtml(guarantee)}</div>` : ''}
            </div>
        `;

        this.addMessage('assistant', offerHtml, true);
        if (timerSeconds > 0) {
            this.startOfferTimer(msg.offer_id, timerSeconds);
        }
        this.bindOfferEvents(msg);
    }
    
    // Format: PILL (compact inline)
    showOfferPill(msg, offer) {
        const icon = offer?.meta?.icon || msg.icon || '⭐';
        const name = this.escapeHtml(this.resolveOfferDisplayTitle(offer, msg, msg.offer_id));
        const price = offer?.display_price || msg.price || '';
        const badge = offer?.meta?.badge || msg.badge || '';
        
        const pillHtml = `
            <div class="flosc-offer-pill" data-offer-id="${msg.offer_id}" data-action="checkout_${msg.offer_id}">
                <span class="flosc-offer-pill-icon">${icon}</span>
                <span>${name}</span>
                ${price ? `<span class="flosc-offer-pill-price">${this.escapeHtml(String(price))}</span>` : ''}
                ${badge ? `<span class="flosc-offer-pill-badge">${this.escapeHtml(String(badge))}</span>` : ''}
            </div>
        `;
        
        this.addMessage('assistant', pillHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: COMPACT (small card for panels)
    showOfferCompact(msg, offer) {
        const icon = offer?.meta?.icon || msg.icon || '⭐';
        const name = this.escapeHtml(this.resolveOfferDisplayTitle(offer, msg, msg.offer_id));
        const description = offer?.description || msg.description || '';
        const price = offer?.display_price || msg.price || '';
        const originalPrice = offer?.original_price || msg.original_price || '';
        
        const compactHtml = `
            <div class="flosc-offer-compact" data-offer-id="${msg.offer_id}" data-action="checkout_${msg.offer_id}">
                <span class="flosc-offer-compact-icon">${icon}</span>
                <div class="flosc-offer-compact-content">
                    <div class="flosc-offer-compact-title">${name}</div>
                    ${description ? `<div class="flosc-offer-compact-subtitle">${this.escapeHtml(String(description))}</div>` : ''}
                </div>
                <div>
                    ${originalPrice ? `<span class="flosc-offer-compact-original">${this.escapeHtml(String(originalPrice))}</span>` : ''}
                    <span class="flosc-offer-compact-price">${this.escapeHtml(String(price))}</span>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', compactHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: BANNER (full-width promotional)
    showOfferBanner(msg, offer) {
        const title = this.escapeHtml(this.resolveOfferDisplayTitle(offer, msg, msg.offer_id));
        const subtitle = offer?.description || msg.description || '';
        const ctaText = msg.cta || offer?.cta || 'Claim Offer';
        const timerSeconds = msg.timer || offer?.timer_seconds || 0;
        
        const bannerHtml = `
            <div class="flosc-offer-banner" data-offer-id="${msg.offer_id}">
                <button class="flosc-offer-banner-close" aria-label="Dismiss">×</button>
                <div class="flosc-offer-banner-content">
                    <div class="flosc-offer-banner-title">${title}</div>
                    ${subtitle ? `<div class="flosc-offer-banner-subtitle">${this.escapeHtml(String(subtitle))}</div>` : ''}
                    ${timerSeconds > 0 ? `
                        <div class="flosc-offer-banner-timer" id="flosc-offer-timer-${msg.offer_id}">
                            ⏱️ <span class="flosc-timer-value">${this.formatTime(timerSeconds)}</span>
                        </div>
                    ` : ''}
                </div>
                <button class="flosc-offer-banner-cta" data-action="checkout_${msg.offer_id}">
                    ${this.escapeHtml(String(ctaText))}
                </button>
            </div>
        `;
        
        this.addMessage('assistant', bannerHtml, true);
        if (timerSeconds > 0) {
            this.startOfferTimer(msg.offer_id, timerSeconds);
        }
        this.bindOfferEvents(msg);
    }
    
    // Format: FEATURED (large prominent card) — fully driven by Offers registry (WPDB)
    showOfferFeatured(msg, offer) {
        const badge = this.resolveOfferBadge(offer, msg);
        const title = this.escapeHtml(this.resolveOfferDisplayTitle(offer, msg, msg.offer_id));

        // Body: Offers description first (avoids conflicting script/msg copy e.g. save $20 vs $350)
        const registryDesc = String(offer?.description || '').trim();
        let description = '';
        if (registryDesc) {
            description = this.formatOfferRichText(this.replaceVariables(registryDesc));
        } else if (msg.content && String(msg.content).trim() && String(msg.content).trim() !== String(msg.offer_id || '')) {
            description = this.formatOfferRichText(this.replaceVariables(String(msg.content)));
        }

        const featureList = this.normalizeOfferFeatures(offer, msg);
        const price = offer?.display_price || this.formatOfferMoney(offer?.price) || msg.price || '';
        const originalPrice = this.formatOfferMoney(offer?.original_price ?? msg.original_price);
        const savings = (offer?.meta?.savings !== undefined && offer?.meta?.savings !== null)
            ? String(offer.meta.savings).trim()
            : String(msg.savings || '').trim();
        const ctaText = offer?.cta || msg.cta || 'Get Access Now';
        const guarantee = String(offer?.guarantee || msg.guarantee || '').trim();

        const featuresHtml = featureList.length > 0 ? `
            <div class="flosc-offer-featured-features">
                ${featureList.map((f) => `
                    <div class="flosc-offer-featured-feature">
                        <span>✓</span> ${this.escapeHtml(f)}
                    </div>
                `).join('')}
            </div>
        ` : '';

        const featuredHtml = `
            <div class="flosc-offer-featured" data-offer-id="${this.escapeHtml(String(msg.offer_id || offer?.id || ''))}">
                <button class="flosc-offer-close" aria-label="Dismiss">×</button>
                ${badge ? `<div class="flosc-offer-featured-badge">${this.escapeHtml(badge)}</div>` : ''}
                <div class="flosc-offer-featured-title">${title}</div>
                ${description ? `<div class="flosc-offer-featured-description">${description}</div>` : ''}
                ${featuresHtml}
                <div class="flosc-offer-featured-pricing">
                    ${price ? `<span class="flosc-offer-featured-price">${this.escapeHtml(String(price))}</span>` : ''}
                    ${originalPrice ? `<span class="flosc-offer-featured-original">${this.escapeHtml(originalPrice)}</span>` : ''}
                    ${savings ? `<span class="flosc-offer-featured-savings">${this.escapeHtml(savings)}</span>` : ''}
                </div>
                <button class="flosc-offer-featured-cta" data-action="checkout_${this.escapeHtml(String(msg.offer_id || offer?.id || ''))}">
                    ${this.escapeHtml(String(ctaText))}
                </button>
                ${guarantee ? `<div class="flosc-offer-featured-guarantee">${this.escapeHtml(guarantee)}</div>` : ''}
            </div>
        `;

        this.addMessage('assistant', featuredHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: TEXT (simple inline text)
    showOfferText(msg, offer) {
        let content = this.replaceVariables(msg.content);
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        const linkText = msg.link_text || 'Click here to learn more';
        
        const textHtml = `
            <div class="flosc-offer-text" data-offer-id="${msg.offer_id}">
                <div class="flosc-offer-text-content">
                    ${content} 
                    <span class="flosc-offer-text-link" data-action="checkout_${msg.offer_id}">${linkText}</span>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', textHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: INLINE-CHECKOUT (Stripe card form in chat)
    showInlineCheckout(msg, offer) {
        const icon = offer?.meta?.icon || this.config.identity?.emoji || '⭐';
        const name = this.resolveOfferDisplayTitle(offer, msg, msg.offer_id);
        const description = offer?.description || msg.description || 'Premium access to all content';
        const price = offer?.display_price || (offer?.price ? `$${offer.price}` : '') || msg.price || '';
        
        const checkoutHtml = `
            <div class="flosc-checkout-inline" data-offer-id="${msg.offer_id}">
                <div class="flosc-checkout-header">
                    <span class="flosc-checkout-product-icon">${icon}</span>
                    <div class="flosc-checkout-product-info">
                        <div class="flosc-checkout-product-name">${name}</div>
                        <div class="flosc-checkout-product-desc">${description}</div>
                    </div>
                    <div class="flosc-checkout-product-price">${price}</div>
                </div>
                <div class="flosc-checkout-form">
                    <div class="flosc-checkout-field">
                        <label class="flosc-checkout-label">Card details</label>
                        <div class="flosc-checkout-card-element" id="flosc-inline-card-${msg.offer_id}">
                            <!-- Stripe Elements will mount here -->
                        </div>
                    </div>
                    <div class="flosc-checkout-error" id="flosc-inline-error-${msg.offer_id}"></div>
                </div>
                <button class="flosc-checkout-btn" id="flosc-inline-pay-${msg.offer_id}" disabled>
                    <span class="flosc-checkout-btn-text">Pay ${price}</span>
                    <span class="flosc-checkout-btn-spinner flosc-hidden"></span>
                </button>
                <div class="flosc-checkout-footer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Secure payment powered by Stripe</span>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', checkoutHtml, true);
        
        // Mount Stripe Elements after DOM update
        setTimeout(() => {
            this.mountInlineStripeElement(msg.offer_id, price);
        }, 100);
    }
    
    // Mount Stripe Elements for inline checkout
    mountInlineStripeElement(offerId, price) {
        if (!this.stripe) {
            this.logWarn('[FLOSC-OFFER] Stripe not initialized');
            // v1.4.6: Show user-facing error instead of silent failure
            const mountPoint = document.getElementById(`flosc-inline-card-${offerId}`);
            if (mountPoint) {
                mountPoint.innerHTML = '<div class="flosc-payment-error">Payment is temporarily unavailable. Please try again later.</div>';
            }
            return;
        }
        
        const elements = this.stripe.elements();
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1f2937',
                    '::placeholder': { color: '#9ca3af' }
                }
            }
        });
        
        const mountPoint = document.getElementById(`flosc-inline-card-${offerId}`);
        const payBtn = document.getElementById(`flosc-inline-pay-${offerId}`);
        const errorEl = document.getElementById(`flosc-inline-error-${offerId}`);
        
        if (mountPoint && cardElement) {
            cardElement.mount(mountPoint);
            
            // Enable button when card is complete
            cardElement.on('change', (event) => {
                if (event.complete) {
                    payBtn.disabled = false;
                } else {
                    payBtn.disabled = true;
                }
                if (event.error) {
                    errorEl.textContent = event.error.message;
                    errorEl.classList.add('is-visible');
                } else {
                    errorEl.classList.remove('is-visible');
                }
            });
            
            // Handle payment
            payBtn.addEventListener('click', async () => {
                await this.processInlinePayment(offerId, cardElement, payBtn, errorEl, price);
            });
        }
    }
    
    // Process inline Stripe payment
    async processInlinePayment(offerId, cardElement, payBtn, errorEl, price) {
        if (this.offers.checkoutInProgress) return;
        this.offers.checkoutInProgress = true;
        
        // Update button state
        const btnText = payBtn.querySelector('.flosc-checkout-btn-text');
        const spinner = payBtn.querySelector('.flosc-checkout-btn-spinner');
        btnText.textContent = 'Processing...';
        if (spinner) spinner.classList.remove('flosc-hidden');
        payBtn.disabled = true;
        
        try {
            const bindingSessionId = (this.currentSession && this.currentSession.id) || this.getVisitorSessionId() || String(Date.now());
            const bindingToken = await this._mintCheckoutBinding(bindingSessionId, 'stripe', offerId);

            // Create payment intent (server applies coupon_code if set)
            const intentResponse = await this.authFetch(this.config.apiUrl + '/create-payment-intent', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    flow_id: this.config.flowId || '',
                    coupon_code: this._getCheckoutCouponCodeForCharge(),
                })
            });
            
            const intentData = await intentResponse.json();
            
            if (!intentData.client_secret) {
                throw new Error(intentData.message || 'Failed to create payment');
            }
            
            // Confirm payment
            const { error, paymentIntent } = await this.stripe.confirmCardPayment(
                intentData.client_secret,
                { payment_method: { card: cardElement } }
            );
            
            if (error) {
                throw error;
            }
            
            if (paymentIntent.status === 'succeeded') {
                // v1.4.1: Call complete-purchase to verify and grant access (fallback for slow webhook)
                try {
                    await this.authFetch(this.config.apiUrl + '/complete-purchase', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': this.config.nonce
                        },
                        body: JSON.stringify({
                            payment_intent_id: paymentIntent.id,
                            offer_id: offerId,
                            provider: 'stripe',
                            flow_id: this.config.flowId || '',
                            binding_token: bindingToken || '',
                            session_id: bindingSessionId,
                        })
                    });
                } catch (e) {
                    this.logWarn('[FLOSC] complete-purchase call failed, webhook should handle:', e);
                }
                
                // Payment successful - update UI
                this.handlePaymentSuccess(offerId);
            }
            
        } catch (error) {
            this.logError('[FLOSC-OFFER] Payment error:', error);
            errorEl.textContent = error.message || 'Payment failed. Please try again.';
            errorEl.classList.add('is-visible');
            btnText.textContent = `Pay ${price}`;
            if (spinner) spinner.classList.add('flosc-hidden');
            payBtn.disabled = false;
        }
        
        this.offers.checkoutInProgress = false;
    }
    
    // Handle successful payment
    handlePaymentSuccess(offerId) {
        this.offers.purchasedOffers.add(offerId);
        this.ivr.shownThisSession['offer_purchased_' + offerId] = true;
        this._saveOfferStates(); // v1.6.2: Persist across refresh
        
        // Find and replace checkout with success message
        const checkoutEl = document.querySelector(`.flosc-checkout-inline[data-offer-id="${offerId}"]`);
        if (checkoutEl) {
            checkoutEl.innerHTML = `
                <div class="flosc-checkout-success">
                    <div class="flosc-checkout-success-icon">🎉</div>
                    <div class="flosc-checkout-success-title">Payment Successful!</div>
                    <div class="flosc-checkout-success-message">
                        Thank you for your purchase! You now have full access.
                        <br>Refreshing your content...
                    </div>
                </div>
            `;
        }
        
        // Update user state and reload after delay
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }
    
    // Bind event listeners for offer elements
    bindOfferEvents(msg) {
        setTimeout(() => {
            // CTA buttons
            const ctaElements = document.querySelectorAll(`[data-action="checkout_${msg.offer_id}"]`);
            ctaElements.forEach(el => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.performIVRAction('checkout_' + msg.offer_id);
                });
            });
            
            // Close buttons
            const closeButtons = document.querySelectorAll(`[data-offer-id="${msg.offer_id}"] .flosc-offer-close, [data-offer-id="${msg.offer_id}"] .flosc-offer-banner-close`);
            closeButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    this.dismissOffer(msg);
                });
            });
            
            // Clickable cards (pill, compact)
            const clickableCards = document.querySelectorAll(`.flosc-offer-pill[data-offer-id="${msg.offer_id}"], .flosc-offer-compact[data-offer-id="${msg.offer_id}"]`);
            clickableCards.forEach(card => {
                card.classList.add('is-clickable');
                card.addEventListener('click', () => {
                    this.performIVRAction('checkout_' + msg.offer_id);
                });
            });
        }, 100);
    }
    
    // Dismiss offer and track
    dismissOffer(msg) {
        const offerEl = document.querySelector(`[data-offer-id="${msg.offer_id}"]`);
        if (offerEl) {
            offerEl.classList.add('is-dismissing');
            setTimeout(() => offerEl.remove(), 300);
        }
        
        this.offers.dismissedOffers.add(msg.offer_id);
        this.ivr.shownThisSession['offer_dismissed_' + msg.offer_id] = true;
        this._saveOfferStates(); // v1.6.2: Persist across refresh
        
        // Track dismissal
        this.trackOfferDismissed(msg.offer_id);
    }
    
    // Track offer shown via API
    async trackOfferShown(offerId, displayFormat) {
        try {
            await this.authFetch(this.config.apiUrl + '/ivr/track', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    offer_state: 'shown',
                    display_format: displayFormat
                })
            });
        } catch (e) {
            this.logWarn('[FLOSC-OFFER] Could not track offer shown', e);
        }
    }
    
    // Track offer dismissed via API
    async trackOfferDismissed(offerId) {
        try {
            await this.authFetch(this.config.apiUrl + '/ivr/track', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    offer_state: 'dismissed'
                })
            });
        } catch (e) {
            this.logWarn('[FLOSC-OFFER] Could not track offer dismissed', e);
        }
    }
    
    // v1.6.2: renderPanelOffers() REMOVED
    // Offer pills are just regular autoprompts (icon + label).
    // Admin creates them as suggested_user_autoprompt entries with Action: show_offer_*
    // No special rendering needed — a pill is a pill.

    startOfferTimer(offerId, totalSeconds) {
        let remaining = totalSeconds;
        const timerEl = document.getElementById(`flosc-offer-timer-${offerId}`);

        // v1.7.7: Clear only this offer's timer (Map prevents leak with multiple offers)
        if (this.offerTimers.has(offerId)) {
            clearInterval(this.offerTimers.get(offerId));
        }

        const timerId = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(timerId);
                this.offerTimers.delete(offerId);
                if (timerEl) {
                    timerEl.innerHTML = '<span class="flosc-timer-expired">Offer Expired</span>';
                }
                return;
            }

            if (timerEl) {
                const valueEl = timerEl.querySelector('.flosc-timer-value');
                if (valueEl) {
                    valueEl.textContent = this.formatTime(remaining);
                }
            }

            this.ivr.context.timer_remaining = this.formatTime(remaining);
        }, 1000);
        
        this.offerTimers.set(offerId, timerId);
    }

    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    replaceVariables(text) {
        if (!text) return '';
        const ctx = this.ivr.context;
        
        // MTS-2026-02-02: [USER-STATUS-FIX] Handle {user_status_response} client-side
        // This variable requires knowing the user state which is available in this.state and this.user
        // Previously this only worked server-side but IVR messages are processed client-side
        if (text.includes('{user_status_response}')) {
            text = text.replace(/{user_status_response}/g, this.generateUserStatusResponse());
        }
        
        return text
            .replace(/{name}/g, ctx.name || 'there')
            .replace(/{score}/g, ctx.score || '0')
            .replace(/{product_name}/g, ctx.product_name || 'the course')
            .replace(/{price}/g, ctx.price || '')
            .replace(/{discount_price}/g, ctx.discount_price || '')
            .replace(/{timer_remaining}/g, ctx.timer_remaining || '60:00')
            .replace(/{customer_count}/g, ctx.customer_count || '1,000')
            .replace(/{lessons_completed}/g, ctx.lessons_completed || '0')
            .replace(/{correct_items}/g, this.user?.correctItems || '')
            .replace(/{missed_items}/g, this.user?.missedItems || '')
            .replace(/{member_levels}/g, this._statusLevelsForThisFlow() || 'member access');
    }

    /**
     * Levels to mention in status copy: only this flow’s configured member level(s)
     * that the user holds — never the global WP role dump (other floscFlows).
     */
    _statusLevelsForThisFlow() {
        const configured = [
            this.config?.defaultMemberLevel,
            this.config?.defaultGuestLevel,
        ].map((x) => String(x || '').trim()).filter(Boolean);
        const held = Array.isArray(this.user?.memberLevels)
            ? this.user.memberLevels.map((x) => String(x || '').trim()).filter(Boolean)
            : [];
        if (!configured.length) {
            return '';
        }
        const match = configured.filter((c) => held.includes(c));
        // Prefer paid member level name only when this.state is member
        if (this.state === 'member' && this.config?.defaultMemberLevel) {
            const m = String(this.config.defaultMemberLevel).trim();
            if (m && (held.includes(m) || !held.length)) {
                return m;
            }
        }
        return match.join(', ');
    }
    
    /**
     * Apply flow-configured status template.
     * Placeholders: {first_name}, {product_name}, {member_level}, {name}, {email}
     */
    _fillStatusTemplate(template, vars) {
        let out = String(template || '');
        Object.keys(vars || {}).forEach((key) => {
            out = out.split('{' + key + '}').join(String(vars[key] ?? ''));
        });
        return out;
    }

    // Status text for THIS floscFlow — templates from FLOSC_CONFIG.userStatus (flow settings).
    generateUserStatusResponse() {
        if (this.user?.isAdmin) {
            return 'Here is your user status in this flosc ecosystem.\n\nYou are the FLOSC Admin.';
        }

        const rows = Array.isArray(this.user?.flowStatuses) ? this.user.flowStatuses : [];
        if (rows.length > 0) {
            const toLabel = (state) => {
                const s = String(state || '').toLowerCase();
                if (s === 'member') return 'Member';
                if (s === 'guest') return 'Guest';
                return 'Visitor';
            };

            const current = rows.find((r) => !!r.current) || rows[0];
            const lines = [
                'Here is your user status in this flosc ecosystem.',
                '',
                `Current floscFlow ${String(current.name || '')}, you are a ${toLabel(current.state)} with ${Number(current.tokens || 0).toLocaleString()} tokens.`,
            ];

            rows.forEach((row) => {
                if (row === current || row.current) return;
                lines.push(`${String(row.name || '')}, you are a ${toLabel(row.state)} with ${Number(row.tokens || 0).toLocaleString()} tokens.`);
            });

            return lines.join('\n');
        }

        const state = String(this.state || 'visitor').toLowerCase();
        if (state === 'member') {
            return 'Here is your user status in this flosc ecosystem.\n\nCurrent floscFlow, you are a Member.';
        }
        if (state === 'guest') {
            return 'Here is your user status in this flosc ecosystem.\n\nCurrent floscFlow, you are a Guest.';
        }
        return 'Here is your user status in this flosc ecosystem.\n\nCurrent floscFlow, you are a Visitor.';
    }

    performIVRAction(action) {
        this.log('FLOSC: Performing action:', action);

        // v4.0.7: Support open_quiz:quiz_id format so each pill loads a specific quiz.
        // Split on first colon only; all other actions are unaffected (no colons).
        const colonIdx   = action.indexOf(':');
        const baseAction = colonIdx >= 0 ? action.slice(0, colonIdx) : action;
        const actionParam = colonIdx >= 0 ? action.slice(colonIdx + 1) : '';

        switch (baseAction) {
            case 'open_quiz':
                if (!this.flowHasQuizConfigured()) {
                    this.denyQuizOnThisFlow();
                    break;
                }
                this.startInChatQuiz(actionParam || 'default');
                break;
            case 'open_registration':
                this.openRegistration();
                break;
            case 'open_login_modal':
                this.showLoginModal();
                break;
            case 'open_free_lesson':
                if (!this.flowServesLessons()) {
                    this.denyLessonsOnThisFlow();
                    break;
                }
                this.openFreeLesson();
                break;
            case 'open_lesson_library':
                if (!this.flowServesLessons()) {
                    this.denyLessonsOnThisFlow();
                    break;
                }
                this.openLessonLibrary();
                break;
            case 'open_personalized_path':
                if (!this.flowServesLessons()) {
                    this.denyLessonsOnThisFlow();
                    break;
                }
                this.openPersonalizedPath();
                break;
            case 'resume_last_lesson':
            case 'open_last_lesson':
            case 'repeat_last_lesson':
                if (!this.flowServesLessons()) {
                    this.denyLessonsOnThisFlow();
                    break;
                }
                this.resumeLastLesson();
                break;
            case 'show_quiz_results':
                this.openQuizResults();
                break;
            case 'show_quiz_topics':
                this.openQuizTopics();
                break;
            case 'open_quiz_lessons':
            case 'show_quiz_lessons':
                this.openQuizLessons();
                break;
            case 'open_quiz_library':
                this.openQuizLibrary();
                break;
            case 'open_support':
                this.openSupport();
                break;
            case 'view_profile':
                window.location.href = this.config.profileUrl || '/';
                break;
            case 'view_dashboard':
                window.location.href = this.config.dashboardUrl || '/wp-admin/';
                break;
            case 'logout': {
                // Brand-neutral: product name from flow Identity params, never hard-coded brand.
                const productLabel = String(
                    this.config?.productName
                    || this.config?.identity?.name
                    || this.config?.appName
                    || ''
                ).trim();
                const goodbye = productLabel
                    ? `See you later — thanks for learning with ${productLabel}!`
                    : 'See you later!';
                this.addMessage('assistant', goodbye);
                const ajaxLogoutUrl = this.config.ajaxUrl || '/wp-admin/admin-ajax.php';
                const serverLogoutUrl = this.config.logoutUrl || (this.config.appUrl || '/');
                const logoutBody = new URLSearchParams({
                    action: 'flosc_logout',
                    nonce: this.config.logoutNonce || '',
                });

                const redirectAfterLogout = (targetUrl) => {
                    setTimeout(() => {
                        window.location.href = targetUrl || (this.config.appUrl || '/');
                    }, 2000);
                };

                fetch(ajaxLogoutUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: logoutBody.toString(),
                })
                    .then(async (response) => {
                        let payload = null;
                        try {
                            payload = await response.json();
                        } catch (e) {
                            payload = null;
                        }

                        if (!response.ok || !payload || payload.success !== true) {
                            throw new Error('logout_failed');
                        }

                        const target = (payload.data && payload.data.redirect)
                            ? payload.data.redirect
                            : (this.config.appUrl || '/');
                        redirectAfterLogout(target);
                    })
                    .catch(() => {
                        this.addMessage(
                            'assistant',
                            'I could not confirm logout through chat. Redirecting you to secure logout now...'
                        );
                        redirectAfterLogout(serverLogoutUrl);
                    });
                break;
            }
            // v1.4.0: Sandbox purchase actions
            case 'send_prompt':
                if (actionParam) {
                    this.chatInput.value = actionParam;
                    this.sendMessage();
                }
                break;
            case 'open_sandbox_purchase':
            case 'sandbox_purchase':
                if (this._sandboxPurchaseAllowed()) {
                    this.openSandboxPurchase();
                }
                break;
            case 'show_offer':
                if (actionParam) {
                    this.showOffer(actionParam, { source: 'auto' });
                } else {
                    this.log('FLOSC: show_offer action missing offer ID parameter');
                }
                break;
            case 'checkout':
                if (actionParam) {
                    this.openCheckout(actionParam);
                } else {
                    this.log('FLOSC: checkout action missing offer ID parameter');
                }
                break;
            default:
                if (action.startsWith('open_filtered_lessons:') || action.startsWith('search_lessons:')) {
                    // AI action tag format: [ACTION:open_filtered_lessons:vowel sounds]
                    const topic = action.replace(/^(?:open_filtered_lessons|search_lessons):/, '').trim();
                    if (topic) {
                        this.openFilteredLessons(topic);
                    } else {
                        this.openLessonLibrary();
                    }
                } else if (action === 'open_filtered_lessons' || action === 'search_lessons') {
                    // Bare action with no topic — show full library
                    this.openLessonLibrary();
                } else if (action.startsWith('checkout_')) {
                    const offerId = action.replace('checkout_', '');
                    this.openCheckout(offerId);
                // v1.4.0: Product-specific sandbox purchase
                } else if (action.startsWith('sandbox_purchase_')) {
                    const productId = action.replace('sandbox_purchase_', '');
                    if (this._sandboxPurchaseAllowed()) {
                        this.openSandboxPurchaseForProduct(productId);
                    }
                } else if (action.startsWith('show_offer_')) {
                    const offerId = action.replace('show_offer_', '');
                    this.showOffer(offerId, { source: 'auto' });
                }
                break;
        }
    }
    
    _sandboxPurchaseAllowed() {
        if (this.user?.isAdmin) {
            return true;
        }
        this.addMessage(
            'assistant',
            'Sandbox purchases are for admin testing only. Use the upgrade offer to check out with PayPal.'
        );
        return false;
    }

    _showCheckoutUnavailable(offerId, processor = '', reason = '') {
        this.logError('[FLOSC-CHECKOUT] Checkout unavailable', { offerId, processor, reason });
        const proc = String(processor || '').toLowerCase();
        let msg = '';
        if (reason === 'paypal_sdk') {
            msg = 'PayPal is configured for this flow, but the PayPal checkout script did not load. Check your network, ad blockers, and that Payments → PayPal credentials match the offer type (one-time vs subscription). You can also use Access Code if your host provided one.';
        } else if (reason === 'stripe_sdk') {
            msg = 'Stripe is selected for this offer, but Stripe.js did not load. Confirm Payments → Stripe publishable key is set, then hard-refresh.';
        } else if (proc === 'redirect') {
            msg = 'This offer uses an external checkout URL, but no Checkout URL is set. In Offers, set Payment Processor to External / Redirect and paste your WooCommerce, Shopify, or other cart URL.';
        } else if (proc === 'stripe') {
            msg = 'Stripe is selected for this offer, but Stripe is not ready on this page (enable Stripe and add keys under Payments, and a Stripe Price ID on the offer).';
        } else if (proc === 'paypal') {
            msg = 'PayPal is selected for this offer, but PayPal is not ready (Payments → enable PayPal and add Client ID + Secret, then hard-refresh).';
        } else {
            msg = 'No checkout path is ready for this offer. In Offers pick PayPal, Stripe, Free, or External / Redirect (WooCommerce, Shopify, etc.), and complete Payments credentials when using PayPal or Stripe.';
        }
        this.addMessage('assistant', msg);
    }

    // v1.4.0: Open sandbox purchase for current flow's product
    openSandboxPurchase() {
        if (!this._sandboxPurchaseAllowed()) {
            return;
        }
        const productId = this.config.productId || this.detectProductFromFlow();
        const offerId = this.getOfferIdForProduct(productId);
        this.showSandboxPayment(offerId, productId);
    }
    
    // v1.4.0: Open sandbox purchase for specific product
    openSandboxPurchaseForProduct(productId) {
        if (!this._sandboxPurchaseAllowed()) {
            return;
        }
        const offerId = this.getOfferIdForProduct(productId);
        this.showSandboxPayment(offerId, productId);
    }
    
    // v1.4.0: Detect product from current IVR flow
    detectProductFromFlow() {
        // Product = configured flow id (instance of FLOSC), not a brand substring.
        const flowId = String(this.config.flowId || this.config.flow_id || '').trim();
        if (flowId && flowId !== 'default' && flowId !== 'flosc' && flowId !== 'flosc_plugin') {
            return flowId;
        }
        // Default/generic flows are not a paid product (WPORG-01).
        return '';
    }
    
    // Offer ID for Upgrade / checkout entry — flow offers registry only (no brand hardcodes).
    getOfferIdForProduct(productId) {
        const offers = this.config.offers || [];
        const active = offers.find(o => (o.status === 'active' || o.active) && o.id !== 'free_trial');
        if (active && active.id) {
            return active.id;
        }
        if (this.config.defaultOfferId) {
            return this.config.defaultOfferId;
        }
        if (offers[0] && offers[0].id) {
            return offers[0].id;
        }
        return '';
    }

    openQuiz() {
        if (!this.flowHasQuizConfigured()) {
            this.denyQuizOnThisFlow();
            return;
        }
        this.startInChatQuiz();
    }

    // v9.3.4: In-Chat Quiz System - Now supports TEXT SEQUENCE and AUDIO types!
    async startInChatQuiz(quizId = 'default') {
        if (!this.flowHasQuizConfigured()) {
            this.denyQuizOnThisFlow();
            return;
        }
        // v1.8.1: Guard — prevent duplicate quiz starts
        // v8.0.0: Allow replacing an active quiz with a DIFFERENT quiz type
        //         (e.g., user started text quiz but wants IPA audio quiz instead)
        if (this.quiz?.active) {
            if (this.quiz.id === quizId || quizId === 'default') {
                // Offer Continue / Restart — never leave user stuck
                const resumeHtml = `<div class="flosc-quiz-resume">
                    <p>You have a quiz in progress.</p>
                    <button class="flosc-btn flosc-quiz-resume-continue">Continue Quiz</button>
                    <button class="flosc-btn flosc-quiz-resume-restart">Restart Quiz</button>
                </div>`;
                const msgEl = this.addMessage('assistant', resumeHtml, true);
                setTimeout(() => {
                    const el = msgEl?.querySelector ? msgEl : this.chatMessages?.lastElementChild;
                    el?.querySelector('.flosc-quiz-resume-continue')?.addEventListener('click', () => {
                        const cur = (this.ipaQuiz?.currentIndex ?? 0) + 1;
                        this._reEnableIpaRecordButton(cur);
                        this.chatMessages?.querySelector(`#flosc-ipa-record-${cur}`)
                            ?.closest('.flosc-chat-message')
                            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    });
                    el?.querySelector('.flosc-quiz-resume-restart')?.addEventListener('click', () => {
                        this.quiz.active = false;
                        this.ipaQuiz = null;
                        this.startInChatQuiz(quizId);
                    });
                }, 100);
                return;
            }
            // Different quiz requested — cancel the old one and proceed
            this.log('[FLOSC Quiz] Replacing active quiz', this.quiz.id, 'with', quizId);
            this.quiz.active = false;
        }
        if (this.ivr?.context?.quiz_completed && this.state === 'visitor') {
            this.addMessage('assistant', 'You\'ve already completed the quiz! Sign up above to see your results.');
            return;
        }

        this.log('[FLOSC Quiz] Starting in-chat quiz:', quizId);

        // Flow-configured IPA audio quiz only — never invent pronunciation IDs when unset
        const defaultAudioQuizId = String(this.config.defaultAudioQuizId || '').trim();
        const audioQuizIds = new Set([defaultAudioQuizId].filter((id) => id !== ''));
        if (quizId && audioQuizIds.has(quizId)) {
            this.showQuizConsentGate();
            return;
        }
        
        // Show loading message
        this.addMessage('assistant', '📋 Loading your quiz...');
        
        try {
            // Fetch quiz from API — flow context only; empty enabled list returns 404 (no invent)
            const quizParams = new URLSearchParams({ id: quizId });
            if (this.config.flowId)  quizParams.append('flow_id',  this.config.flowId);
            if (this.config.ivrFile) quizParams.append('ivr_file', this.config.ivrFile);
            const response = await this.authFetch(`${this.config.apiUrl}/quiz?${quizParams.toString()}`, { credentials: 'same-origin' });
            const data = await response.json();
            
            this.log('[FLOSC Quiz] API response:', data);

            if (!data.success && (data.code === 'flosc_no_quiz' || response.status === 404)) {
                this.denyQuizOnThisFlow();
                return;
            }
            
            if (data.success) {
                // v9.3.4: Handle different quiz types
                if (data.type === 'text_sequence') {
                    // TEXT SEQUENCE QUIZ (MVP: type 1,2,3...10)
                    this.startTextSequenceQuiz(data);
                    return;
                }
                
                if (data.type === 'audio') {
                    // AUDIO PRONUNCIATION QUIZ
                    this.startAudioQuiz(data);
                    return;
                }
                
                // MULTIPLE CHOICE QUIZ (default)
                if (data.questions && data.questions.length > 0) {
                    this.quiz = {
                        active: true,
                        id: data.id,
                        title: data.title || 'Quick Assessment',
                        type: 'multiple_choice',
                        questions: data.questions,
                        currentIndex: 0,
                        answers: [],
                        startedAt: Date.now(),
                        completedAt: null
                    };
                    
                    this.log('[FLOSC Quiz] Loaded', this.quiz.questions.length, 'questions');
                    this.addMessage('assistant', `Great! Let's see where you stand. This quick ${this.quiz.questions.length}-question quiz takes about 30 seconds.`);
                    
                    setTimeout(() => {
                        this.showQuizQuestion();
                    }, 800);
                    return;
                }
            }
            
            // Fallback to hardcoded sample quiz
            this.log('[FLOSC Quiz] API returned no valid quiz, using sample');
            this.startSampleQuiz();
            
        } catch (error) {
            this.logError('[FLOSC Quiz] Failed to load quiz:', error);
            this.startSampleQuiz();
        }
    }
    
    // v9.3.4: TEXT SEQUENCE QUIZ - User types "1, 2, 3, 4, 5, 6, 7, 8, 9, 10"
    startTextSequenceQuiz(data) {
        this.log('[FLOSC Quiz] Starting TEXT SEQUENCE quiz');
        
        // Ensure expected is a valid array with actual values
        let expected = data.expected;
        if (!Array.isArray(expected) || expected.length === 0 || (expected.length === 1 && expected[0] === '')) {
            expected = ['1','2','3','4','5','6','7','8','9','10'];
        }
        
        this.quiz = {
            active: true,
            id: data.id,
            title: data.title || 'Sequence Quiz',
            type: 'text_sequence',
            expected: expected,
            prompt: data.prompt || 'Type the sequence from 1 to 10',
            startedAt: Date.now(),
            completedAt: null
        };
        
        const quizHtml = `
            <div class="flosc-quiz-text-sequence">
                <div class="flosc-quiz-question-header">
                    <span>Sequence Quiz</span>
                    <span>${this.quiz.title}</span>
                </div>
                <div class="flosc-quiz-question-text">${this.quiz.prompt}</div>
                <div class="flosc-quiz-text-input-wrapper">
                    <input type="text" 
                           class="flosc-quiz-text-input" 
                           id="flosc-sequence-input"
                           placeholder="1, 2, 3, 4, 5, 6, 7, 8, 9, 10"
                           autocomplete="off">
                    <button class="flosc-quiz-submit-btn" id="flosc-sequence-submit">
                        ✓ Submit
                    </button>
                </div>
                <p class="flosc-quiz-hint">Separate numbers with commas or spaces</p>
            </div>
        `;
        
        this.addMessage('assistant', quizHtml, true);
        
        // Bind submit handler
        setTimeout(() => {
            const input = document.getElementById('flosc-sequence-input');
            const submitBtn = document.getElementById('flosc-sequence-submit');
            
            if (submitBtn) {
                submitBtn.addEventListener('click', () => this.submitTextSequence());
            }
            if (input) {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') this.submitTextSequence();
                });
                input.focus();
            }
        }, 100);
    }
    
    // v9.3.4: Submit text sequence answer
    // v9.3.6: Fixed scoring - counts matching items regardless of position
    submitTextSequence() {
        const input = document.getElementById('flosc-sequence-input');
        if (!input) return;
        
        const userAnswer = input.value.trim();
        if (!userAnswer) {
            this.addMessage('assistant', '⚠️ Please enter the sequence before submitting.');
            return;
        }
        
        // Parse user input (accepts commas, spaces, or both)
        const userItems = userAnswer.split(/[\s,]+/).map(s => s.trim().toLowerCase()).filter(s => s);
        const expected = this.quiz.expected.map(e => e.toLowerCase());
        
        // v9.3.6: Score by matching content, not position
        // Count how many of the expected items the user provided
        let correct = 0;
        const correctItems = [];
        const missedItems = [];
        const userMatched = new Set();
        
        for (const exp of expected) {
            if (userItems.includes(exp)) {
                correct++;
                correctItems.push(exp);
                userMatched.add(exp);
            } else {
                missedItems.push(exp);
            }
        }
        
        // Find extra items user entered that weren't expected
        const extraItems = userItems.filter(item => !expected.includes(item));
        
        const score = Math.round((correct / expected.length) * 100);
        
        // v3.0.4: Removed addMessage('user', userAnswer) — answer already visible in quiz area
        
        // Store quiz data
        this.quiz.completedAt = Date.now();
        this.quiz.score = score;
        this.quiz.correctItems = correctItems;
        this.quiz.missedItems = missedItems;
        
        // v5.0.3 FIX: Also set on this.user so buildIVRContext() picks it up
        // on subsequent sendMessage() calls (it rebuilds context from this.user)
        this.user = this.user || {};
        this.user.lastQuizScore = score;
        this.user.quizCompletedAt = Date.now();
        
        // Store score (in localStorage and via API)
        this.storeQuizScore({
            score: score,
            correct: correct,
            total: expected.length,
            passed: score >= 70,
            userAnswer: userAnswer
        });
        
        // v9.3.4: LOGIN GATE - Visitors must sign up to see their score
        if (this.state === 'visitor') {
            // Store score for reveal after signup
            this.quiz.pendingScore = score;
            this.quiz.pendingCorrect = correct;
            this.quiz.pendingMissed = missedItems.length;
            
            const gateHtml = `
                <div class="flosc-quiz-gate">
                    <div class="flosc-gate-icon">📊</div>
                    <div class="flosc-gate-title">Your results are ready!</div>
                    <div class="flosc-gate-text">Sign up to see your score and get a personalized free lesson.</div>
                    <button class="flosc-gate-btn flosc-gate-signup-btn">Sign Up to See Results</button>
                </div>
            `;
            this.addMessage('assistant', gateHtml, true);
            this.saveVisitorMessage('assistant', gateHtml);
            
            // v1.8.1: Bind ALL gate signup buttons (class-based, not id-based)
            setTimeout(() => {
                document.querySelectorAll('.flosc-gate-signup-btn:not([data-bound])').forEach(btn => {
                    btn.dataset.bound = 'true';
                    btn.addEventListener('click', () => this.openRegistration());
                });
            }, 100);
            
            // Update context for IVR
            this.ivr.context.quiz_completed = true;
            this.ivr.context.first_message_after_quiz = true;
            
        } else {
            // Logged-in user: Show actual results
            this.showQuizResults(score, correct, missedItems.length);
        }
    }
    
    // v9.3.4: Show quiz results (for logged-in users or after login)
    showQuizResults(score, correct, incorrect) {
        const resultHtml = `
            <div class="flosc-quiz-result">
                <div class="flosc-quiz-score-circle" data-score="${score}">
                    <span class="flosc-quiz-score-value">${score}%</span>
                </div>
                <div class="flosc-quiz-score-label">
                    ${score === 100 ? '🎉 Perfect Score!' : score >= 70 ? '👍 Great job!' : '📚 Keep practicing!'}
                </div>
                <div class="flosc-quiz-breakdown">
                    <span class="correct">✓ ${correct} correct</span>
                    <span class="incorrect">✗ ${incorrect} missed</span>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', resultHtml, true);
    }
    
    // v9.3.4: AUDIO QUIZ - Record and analyze
    startAudioQuiz(data) {
        this.log('[FLOSC Quiz] Starting AUDIO quiz');
        
        this.quiz = {
            active: true,
            id: data.id,
            title: data.title || 'Audio Quiz',
            type: 'audio',
            expected: data.expected || ['1','2','3','4','5','6','7','8','9','10'],
            prompt: data.prompt || 'Record yourself saying the sequence',
            startedAt: Date.now(),
            completedAt: null
        };
        
        const quizHtml = `
            <div class="flosc-quiz-audio">
                <div class="flosc-quiz-question-header">
                    <span>Audio Quiz</span>
                    <span>${this.quiz.title}</span>
                </div>
                <div class="flosc-quiz-question-text">${this.quiz.prompt}</div>
                <div class="flosc-quiz-audio-controls">
                    <button class="flosc-quiz-record-btn" id="flosc-audio-record">
                        🎤 Start Recording
                    </button>
                    <div class="flosc-quiz-recording-status" id="flosc-recording-status"></div>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', quizHtml, true);
        
        // Bind record handler
        setTimeout(() => {
            const recordBtn = document.getElementById('flosc-audio-record');
            if (recordBtn) {
                recordBtn.addEventListener('click', () => this.toggleAudioQuizRecording());
            }
        }, 100);
    }
    
    // Audio recording for quiz (existing functionality adapted)
    async toggleAudioQuizRecording() {
        if (this.isRecording) {
            this.stopAudioQuizRecording();
        } else {
            await this.startAudioQuizRecording();
        }
    }

    _resolveMime(recorder) {
        const m = recorder?.mimeType || '';
        const _label = (mime) => {
            if (mime.includes('mp4')) return { mime, format: 'mp4' };
            if (mime.includes('ogg')) return { mime, format: 'ogg' };
            return { mime, format: 'webm' };
        };
        if (m) return _label(m);
        if (typeof MediaRecorder !== 'undefined') {
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) return { mime: 'audio/webm;codecs=opus', format: 'webm' };
            if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus'))  return { mime: 'audio/ogg;codecs=opus',  format: 'ogg'  };
            if (MediaRecorder.isTypeSupported('audio/mp4'))              return { mime: 'audio/mp4',              format: 'mp4'  };
        }
        return { mime: 'audio/webm', format: 'webm' };
    }

    async startAudioQuizRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.recordingStream = stream;
            this.audioChunks = [];

            this.mediaRecorder = new MediaRecorder(stream);
            const { mime: audioQuizMime, format: audioQuizFormat } = this._resolveMime(this.mediaRecorder);
            this.audioQuizMime   = audioQuizMime;
            this.audioQuizFormat = audioQuizFormat;
            this.mediaRecorder.ondataavailable = (e) => this.audioChunks.push(e.data);
            this.mediaRecorder.onstop = () => this.processAudioQuiz();
            
            this.mediaRecorder.start();
            this.isRecording = true;
            
            const btn = document.getElementById('flosc-audio-record');
            const status = document.getElementById('flosc-recording-status');
            if (btn) btn.innerHTML = '⏹️ Stop Recording';
            if (status) status.innerHTML = '🔴 Recording...';
            
        } catch (e) {
            this.logError('FLOSC: Audio recording failed', e);
            this.addMessage('assistant', '⚠️ Could not access microphone. Please allow microphone access and try again.');
        }
    }
    
    stopAudioQuizRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            if (this.recordingStream) {
                this.recordingStream.getTracks().forEach(t => t.stop());
            }
            
            const btn = document.getElementById('flosc-audio-record');
            const status = document.getElementById('flosc-recording-status');
            if (btn) btn.innerHTML = '🎤 Start Recording';
            if (status) status.innerHTML = '⏳ Processing...';
        }
    }
    
    async processAudioQuiz() {
        const mime   = this.audioQuizMime   || this._resolveMime(this.mediaRecorder).mime;
        const format = this.audioQuizFormat || this._resolveMime(this.mediaRecorder).format;
        const blob = new Blob(this.audioChunks, { type: mime });
        const formData = new FormData();
        formData.append('audio', blob, `quiz-audio.${format}`);
        
        try {
            const response = await this.authFetch(`${this.config.apiUrl}/process-audio`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.quiz.completedAt = Date.now();
                this.quiz.score = data.score || 0;
                this.displayAudioQuizResult(data);
            } else {
                this.addMessage('assistant', '⚠️ Could not process audio. Please try again.');
            }
        } catch (e) {
            this.logError('FLOSC: Audio processing failed', e);
            this.addMessage('assistant', '⚠️ Audio processing failed. Please try again.');
        }
    }
    
    displayAudioQuizResult(data) {
        const score = data.score || 0;
        let resultHtml = `
            <div class="flosc-quiz-result">
                <div class="flosc-quiz-score-circle" data-score-percent="${score}">
                    <span class="flosc-quiz-score-value">${score}%</span>
                </div>
                <div class="flosc-quiz-score-label">
                    ${score === 100 ? '🎉 Perfect!' : score >= 70 ? '👍 Great!' : '📚 Keep practicing!'}
                </div>
                ${data.transcript ? `<div class="flosc-quiz-transcript">You said: "${data.transcript}"</div>` : ''}
            </div>
        `;
        
        this.addMessage('assistant', resultHtml, true);
        this.storeQuizScore({ score, correct: data.correct || [], incorrect: data.incorrect || [], total: (data.correct || []).length + (data.incorrect || []).length, passed: score >= 70, timestamp: Date.now() });
        this.onQuizComplete(score);
    }

    // ============================================================
    // Flow-configured IPA Pronunciation Quiz — v8.0.0
    // Direct browser → configured pronunciation API host
    // No WordPress involvement for audio analysis
    // ============================================================

    showQuizConsentGate() {
        // Skip if already consented this session
        if (this._quizConsented) {
            this.checkMicAndStartQuiz();
            return;
        }

        const consentHtml = `
            <div class="flosc-consent-card">
                <div class="flosc-consent-header">Before we begin</div>
                <div class="flosc-consent-text">
                    We need these permissions to properly assess your pronunciation skills. 
                    For you to be able to record your voice, we need access to your microphone. 
                    For us to be able to create an account for you, we will need your email 
                    address and appropriate profile fields.
                </div>
                <div class="flosc-consent-legal">
                    By clicking below, you consent to microphone access, audio recording 
                    and storage, pronunciation assessment, cookies, and collection of personal 
                    information in accordance with applicable privacy laws including GDPR.
                </div>
                <button class="flosc-consent-btn" id="flosc-consent-agree">${this.config.consentButtonText || "I Agree — Let's Go!"}</button>
            </div>
        `;

        this.addMessage('assistant', consentHtml, true);

        setTimeout(() => {
            const btn = document.getElementById('flosc-consent-agree');
            if (btn) {
                btn.addEventListener('click', () => {
                    this._quizConsented = true;
                    try { localStorage.setItem('flosc_consent', '1'); } catch(e) {}
                    btn.textContent = '✓ Agreed';
                    btn.disabled = true;
                    btn.classList.add('agreed');
                    setTimeout(() => this.checkMicAndStartQuiz(), 400);
                });
            }
        }, 100);
    }

    checkMicAndStartQuiz() {
        // Go straight to tier selection — mic permission is handled at record time
        this.showQuizTierSelection();
    }

    showQuizTierSelection() {
        this.log('[FLOSC IPA Quiz] Showing tier selection');

        const html = `
            <div class="flosc-tier-card">
                <div class="flosc-tier-header">Which pronunciation quiz would you like to take?</div>
                <div class="flosc-tier-buttons">
                    <button class="flosc-tier-btn flosc-tier-beginner" data-tier="beginner">
                        <span class="flosc-tier-emoji">🐣</span>
                        <span class="flosc-tier-label"><span class="flosc-tier-name">Daring Beginner</span><span class="flosc-tier-desc">Short fun phrases</span></span>
                    </button>
                    <button class="flosc-tier-btn flosc-tier-intermediate" data-tier="intermediate">
                        <span class="flosc-tier-emoji">🎯</span>
                        <span class="flosc-tier-label"><span class="flosc-tier-name">Engaging Intermediate</span><span class="flosc-tier-desc">Longer real-world sentences</span></span>
                    </button>
                    <button class="flosc-tier-btn flosc-tier-advanced" data-tier="advanced">
                        <span class="flosc-tier-emoji">🚀</span>
                        <span class="flosc-tier-label"><span class="flosc-tier-name">Adventurous Advanced</span><span class="flosc-tier-desc">Complex literary passages</span></span>
                    </button>
                </div>
                <div class="flosc-tier-mic-note">(Requires microphone access)</div>
            </div>
        `;

        this.addMessage('assistant', html, true);

        setTimeout(() => {
            document.querySelectorAll('.flosc-tier-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tier = btn.dataset.tier;
                    document.querySelectorAll('.flosc-tier-btn').forEach(b => {
                        b.disabled = true;
                        b.classList.add(b === btn ? 'selected' : 'dimmed');
                    });
                    this.showTyping();
                    setTimeout(() => {
                        this.hideTyping();
                        this.startIpaQuiz(tier);
                    }, 600);
                });
            });
        }, 100);
    }

    startIpaQuiz(tier = 'intermediate') {
        this.log('[FLOSC IPA Quiz] Starting IPA pronunciation quiz \u2014 tier:', tier);

        // ── Draft Quiz 1 (original pre-tier quiz — archived) ──
        // 'Cousin sister brother mother father peanut butter',
        // "There's different rules in rural America.",
        // 'I think it would be fun to host a chili cook-off during our Super Bowl party on Sunday.',
        // "There's a lovely light comedy production at the university theater.",
        // 'Robert Frost took the road less traveled, and Langston Hughes asked what happens to a dream deferred.'

        const tierPhrases = {
            beginner: [
                "Cat, bat, mat -- where's my yellow hat?",
                'Your dog is adorable!',
                "It's a lovely evening, isn't it?",
                'The university theater.',
                'Would you ever settle down in a rural American town?'
            ],
            intermediate: [
                'Cousin sister brother mother father peanut butter',
                "There's a lovely light comedy production at the university theater.",
                'Take a left at the fork in the road.',
                "There's different rules in rural America.",
                'I think it would be fun to host a chili cook-off during our Super Bowl party on Sunday.'
            ],
            advanced: [
                'Cousin sister brother mother father peanut butter',
                "There's a lovely light comedy production at the university theater.",
                "There's different rules in rural America.",
                'I think it would be fun to host a chili cook-off during our Super Bowl party on Sunday.',
                'Robert Frost took the road less traveled, and Langston Hughes asked what happens to a dream deferred.'
            ]
        };

        const phrases = tierPhrases[tier] || tierPhrases.intermediate;

        const tierNames = {
            beginner: 'Daring Beginner',
            intermediate: 'Engaging Intermediate',
            advanced: 'Adventurous Advanced'
        };

        // Unified wordIpa — all words across all 3 tiers
        // espeak: ~90% precision reference (auto-generated)
        // mw: Merriam-Webster IPA reference ('' = no MW entry for contractions/proper nouns)
        // da1ni5: scoring target — the source of 100% correctness
        // \u2705 = da1ni5 APPROVED by the site operator | \u26A0\uFE0F = placeholder (espeak copy, awaiting approval)
        const wordIpa = {
            // \u2500\u2500 Beginner P1: Cat, bat, mat -- where's my yellow hat? \u2500\u2500 \u2705 APPROVED
            "cat,":      { espeak: 'k\u00E6t',   mw: 'k\u00E6t',   da1ni5: ['k\u00E6t'] },
            cat:         { espeak: 'k\u00E6t',   mw: 'k\u00E6t',   da1ni5: ['k\u00E6t'] },
            "bat,":      { espeak: 'b\u00E6t',   mw: 'b\u00E6t',   da1ni5: ['b\u00E6t'] },
            bat:         { espeak: 'b\u00E6t',   mw: 'b\u00E6t',   da1ni5: ['b\u00E6t'] },
            mat:         { espeak: 'm\u00E6t',   mw: 'm\u00E6t',   da1ni5: ['m\u00E6t'] },
            "where's":   { espeak: 'w\u025B\u0279z',  mw: 'w\u025B\u0279z',  da1ni5: ['w\u025B\u0279z', '\u028D\u025B\u0279z', 'w\u02B0\u025B\u0279z', 'we\u0279z', '\u028De\u0279z', 'w\u02B0e\u0279z', 'w\u025B\u025Az', '\u028D\u025B\u025Az', 'w\u02B0\u025B\u025Az'] },
            my:          { espeak: 'ma\u026A',   mw: 'ma\u026A',   da1ni5: ['ma\u026A'] },
            yellow:      { espeak: 'j\u025Blo\u028A', mw: 'j\u025Blo\u028A', da1ni5: ['j\u025Blo\u028A'] },
            "hat?":      { espeak: 'h\u00E6t',   mw: 'h\u00E6t',   da1ni5: ['h\u00E6t'] },
            hat:         { espeak: 'h\u00E6t',   mw: 'h\u00E6t',   da1ni5: ['h\u00E6t'] },
            // \u2500\u2500 Beginner P2: Your dog is adorable! \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            your:        { espeak: 'j\u0254\u02D0\u0279',       mw: 'j\u0254\u02D0\u0279',       da1ni5: ['j\u0254\u02D0\u0279'] },
            dog:         { espeak: 'd\u0254\u02D0\u0261',       mw: 'd\u0254\u02D0\u0261',       da1ni5: ['d\u0254\u02D0\u0261'] },
            is:          { espeak: '\u026Az',         mw: '\u026Az',         da1ni5: ['\u026Az'] },
            "adorable!": { espeak: '\u0259d\u0254\u02D0\u0279\u0259b\u0259l',  mw: '\u0259d\u0254\u02D0\u0279\u0259b\u0259l',  da1ni5: ['\u0259d\u0254\u02D0\u0279\u0259b\u0259l'] },
            adorable:    { espeak: '\u0259d\u0254\u02D0\u0279\u0259b\u0259l',  mw: '\u0259d\u0254\u02D0\u0279\u0259b\u0259l',  da1ni5: ['\u0259d\u0254\u02D0\u0279\u0259b\u0259l'] },
            // \u2500\u2500 Beginner P3: It's a lovely evening, isn't it? \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            "it's":      { espeak: '\u026Ats',    mw: '\u026Ats',       da1ni5: ['\u026Ats'] },
            a:           { espeak: '\u0259',      mw: '\u0259',      da1ni5: ['\u0259'] },
            lovely:      { espeak: 'l\u028Cvli',  mw: 'l\u028Cvli',  da1ni5: ['l\u028Cvli'] },
            "evening,":  { espeak: 'i\u02D0vn\u026A\u014B', mw: 'i\u02D0vn\u026A\u014B', da1ni5: ['i\u02D0vn\u026A\u014B'] },
            evening:     { espeak: 'i\u02D0vn\u026A\u014B', mw: 'i\u02D0vn\u026A\u014B', da1ni5: ['i\u02D0vn\u026A\u014B'] },
            "isn't":     { espeak: '\u026Az\u0259nt',  mw: '\u026Az\u0259nt',       da1ni5: ['\u026Az\u0259nt'] },
            "it?":       { espeak: '\u026At',     mw: '\u026At',     da1ni5: ['\u026At'] },
            it:          { espeak: '\u026At',     mw: '\u026At',     da1ni5: ['\u026At'] },
            // \u2500\u2500 Beginner P4: The university theater. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            the:         { espeak: '\u00F0\u0259',              mw: '\u00F0\u0259',              da1ni5: ['\u00F0\u0259'] },
            university:  { espeak: 'ju\u02D0n\u026Av\u025C\u02D0\u0279s\u026A\u027Ei',  mw: 'ju\u02D0n\u026Av\u025C\u02D0\u0279s\u026A\u027Ei',  da1ni5: ['ju\u02D0n\u026Av\u025C\u02D0\u0279s\u026A\u027Ei'] },
            "theater.":  { espeak: '\u03B8i\u02D0\u0259\u027E\u025A',          mw: '\u03B8i\u02D0\u0259\u027E\u025A',          da1ni5: ['\u03B8i\u02D0\u0259\u027E\u025A'] },
            theater:     { espeak: '\u03B8i\u02D0\u0259\u027E\u025A',          mw: '\u03B8i\u02D0\u0259\u027E\u025A',          da1ni5: ['\u03B8i\u02D0\u0259\u027E\u025A'] },
            // \u2500\u2500 Beginner P5: Would you ever settle down in a rural American town? \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            would:       { espeak: 'w\u028Ad',       mw: 'w\u028Ad',       da1ni5: ['w\u028Ad'] },
            you:         { espeak: 'ju\u02D0',       mw: 'ju\u02D0',       da1ni5: ['ju\u02D0'] },
            ever:        { espeak: '\u025Bv\u025A',       mw: '\u025Bv\u025A',       da1ni5: ['\u025Bv\u025A'] },
            settle:      { espeak: 's\u025B\u027E\u0259l',     mw: 's\u025Bt\u0259l',     da1ni5: ['s\u025B\u027E\u0259l'] },
            down:        { espeak: 'da\u028An',      mw: 'da\u028An',      da1ni5: ['da\u028An'] },
            in:          { espeak: '\u026An',        mw: '\u026An',        da1ni5: ['\u026An'] },
            rural:       { espeak: '\u0279\u028A\u0279\u0259l',     mw: '\u0279\u028A\u0279\u0259l',     da1ni5: ['\u0279\u028A\u0279\u0259l'] },
            american:    { espeak: '\u0259m\u025B\u0279\u026Ak\u0259n',  mw: '\u0259m\u025B\u0279\u026Ak\u0259n',  da1ni5: ['\u0259m\u025B\u0279\u026Ak\u0259n'] },
            "town?":     { espeak: 'ta\u028An',      mw: 'ta\u028An',      da1ni5: ['ta\u028An'] },
            town:        { espeak: 'ta\u028An',      mw: 'ta\u028An',      da1ni5: ['ta\u028An'] },
            // \u2500\u2500 Int/Adv: Cousin sister brother mother father peanut butter \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            cousin:      { espeak: 'k\u028Cz\u0259n',   mw: 'k\u028Cz\u0259n',   da1ni5: ['k\u028Cz\u0259n'] },
            sister:      { espeak: 's\u026Ast\u025A',   mw: 's\u026Ast\u025A',   da1ni5: ['s\u026Ast\u025A'] },
            brother:     { espeak: 'b\u0279\u028C\u00F0\u025A',  mw: 'b\u0279\u028C\u00F0\u025A',   da1ni5: ['b\u0279\u028C\u00F0\u025A'] },
            mother:      { espeak: 'm\u028C\u00F0\u025A',   mw: 'm\u028C\u00F0\u025A',    da1ni5: ['m\u028C\u00F0\u025A'] },
            father:      { espeak: 'f\u0251\u02D0\u00F0\u025A',  mw: 'f\u0251\u02D0\u00F0\u025A',   da1ni5: ['f\u0251\u02D0\u00F0\u025A'] },
            peanut:      { espeak: 'pi\u02D0n\u028Ct',  mw: 'pi\u02D0n\u028Ct',  da1ni5: ['pi\u02D0n\u028Ct'] },
            butter:      { espeak: 'b\u028C\u027E\u025A',   mw: 'b\u028C\u027E\u025A',    da1ni5: ['b\u028C\u027E\u025A'] },
            // \u2500\u2500 Int/Adv: There's a lovely light comedy production at the university theater. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            "there's":   { espeak: '\u00F0\u025B\u0279z',       mw: '\u00F0\u025B\u0279z',           da1ni5: ['\u00F0\u025B\u0279z'] },
            light:       { espeak: 'la\u026At',       mw: 'la\u026At',       da1ni5: ['la\u026At'] },
            comedy:      { espeak: 'k\u0251\u02D0m\u0259di',    mw: 'k\u0251\u02D0m\u0259di',    da1ni5: ['k\u0251\u02D0m\u0259di'] },
            production:  { espeak: 'p\u0279\u0259d\u028Ck\u0283\u0259n',  mw: 'p\u0279\u0259d\u028Ck\u0283\u0259n',  da1ni5: ['p\u0279\u0259d\u028Ck\u0283\u0259n'] },
            at:          { espeak: '\u00E6t',         mw: '\u00E6t',         da1ni5: ['\u00E6t'] },
            // \u2500\u2500 Intermediate: Take a left at the fork in the road. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            take:        { espeak: 'te\u026Ak',   mw: 'te\u026Ak',   da1ni5: ['te\u026Ak'] },
            left:        { espeak: 'l\u025Bft',   mw: 'l\u025Bft',   da1ni5: ['l\u025Bft'] },
            fork:        { espeak: 'f\u0254\u02D0\u0279k',  mw: 'f\u0254\u02D0\u0279k',  da1ni5: ['f\u0254\u02D0\u0279k'] },
            "road.":     { espeak: '\u0279o\u028Ad',   mw: '\u0279o\u028Ad',   da1ni5: ['\u0279o\u028Ad'] },
            road:        { espeak: '\u0279o\u028Ad',   mw: '\u0279o\u028Ad',   da1ni5: ['\u0279o\u028Ad'] },
            // \u2500\u2500 Int/Adv: There's different rules in rural America. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            different:   { espeak: 'd\u026Af\u025A\u0279\u0259nt',  mw: 'd\u026Af\u025A\u0279\u0259nt',  da1ni5: ['d\u026Af\u025A\u0279\u0259nt'] },
            rules:       { espeak: '\u0279u\u02D0lz',     mw: '\u0279u\u02D0lz',     da1ni5: ['\u0279u\u02D0lz'] },
            "america.":  { espeak: '\u0259m\u025B\u0279\u026Ak\u0259',   mw: '\u0259m\u025B\u0279\u026Ak\u0259',   da1ni5: ['\u0259m\u025B\u0279\u026Ak\u0259'] },
            america:     { espeak: '\u0259m\u025B\u0279\u026Ak\u0259',   mw: '\u0259m\u025B\u0279\u026Ak\u0259',   da1ni5: ['\u0259m\u025B\u0279\u026Ak\u0259'] },
            // \u2500\u2500 Int/Adv: I think it would be fun to host a chili cook-off ... Sunday. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            i:           { espeak: 'a\u026A',      mw: 'a\u026A',      da1ni5: ['a\u026A'] },
            think:       { espeak: '\u03B8\u026A\u014Bk',    mw: '\u03B8\u026A\u014Bk',    da1ni5: ['\u03B8\u026A\u014Bk'] },
            be:          { espeak: 'bi\u02D0',     mw: 'bi\u02D0',     da1ni5: ['bi\u02D0'] },
            fun:         { espeak: 'f\u028Cn',     mw: 'f\u028Cn',     da1ni5: ['f\u028Cn'] },
            to:          { espeak: 'tu\u02D0',     mw: 'tu\u02D0',     da1ni5: ['tu\u02D0'] },
            host:        { espeak: 'ho\u028Ast',   mw: 'ho\u028Ast',   da1ni5: ['ho\u028Ast'] },
            chili:       { espeak: 't\u0283\u026Ali',   mw: 't\u0283\u026Ali',   da1ni5: ['t\u0283\u026Ali'] },
            "cook-off":  { espeak: 'k\u028Ak\u0254\u02D0f',  mw: 'k\u028Ak\u0254\u02D0f',        da1ni5: ['k\u028Ak\u0254\u02D0f'] },
            during:      { espeak: 'd\u028A\u0279\u026A\u014B',   mw: 'd\u028A\u0279\u026A\u014B',   da1ni5: ['d\u028A\u0279\u026A\u014B'] },
            our:         { espeak: 'a\u028A\u025A',     mw: 'a\u028A\u025A',     da1ni5: ['a\u028A\u025A'] },
            super:       { espeak: 'su\u02D0p\u025A',   mw: 'su\u02D0p\u025A',   da1ni5: ['su\u02D0p\u025A'] },
            bowl:        { espeak: 'bo\u028Al',    mw: 'bo\u028Al',    da1ni5: ['bo\u028Al'] },
            party:       { espeak: 'p\u0251\u02D0\u0279\u027Ei',  mw: 'p\u0251\u02D0\u0279\u027Ei',  da1ni5: ['p\u0251\u02D0\u0279\u027Ei'] },
            on:          { espeak: '\u0251\u02D0n',     mw: '\u0251\u02D0n',     da1ni5: ['\u0251\u02D0n'] },
            "sunday.":   { espeak: 's\u028Cnde\u026A',  mw: 's\u028Cnde\u026A',  da1ni5: ['s\u028Cnde\u026A'] },
            sunday:      { espeak: 's\u028Cnde\u026A',  mw: 's\u028Cnde\u026A',  da1ni5: ['s\u028Cnde\u026A'] },
            // \u2500\u2500 Advanced: Robert Frost took the road less traveled ... deferred. \u2500\u2500 \u26A0\uFE0F PLACEHOLDER
            robert:      { espeak: '\u0279\u0251\u02D0b\u025At',   mw: '\u0279\u0251\u02D0b\u025At',         da1ni5: ['\u0279\u0251\u02D0b\u025At'] },
            frost:       { espeak: 'f\u0279\u0254\u02D0st',   mw: 'f\u0279\u0254\u02D0st',   da1ni5: ['f\u0279\u0254\u02D0st'] },
            took:        { espeak: 't\u028Ak',      mw: 't\u028Ak',      da1ni5: ['t\u028Ak'] },
            less:        { espeak: 'l\u025Bs',      mw: 'l\u025Bs',      da1ni5: ['l\u025Bs'] },
            "traveled,": { espeak: 't\u0279\u00E6v\u0259ld',  mw: 't\u0279\u00E6v\u0259ld',  da1ni5: ['t\u0279\u00E6v\u0259ld'] },
            traveled:    { espeak: 't\u0279\u00E6v\u0259ld',  mw: 't\u0279\u00E6v\u0259ld',  da1ni5: ['t\u0279\u00E6v\u0259ld'] },
            and:         { espeak: '\u00E6nd',      mw: '\u00E6nd',      da1ni5: ['\u00E6nd'] },
            langston:    { espeak: 'l\u00E6\u014Bst\u0259n',  mw: 'l\u00E6\u014Bst\u0259n',         da1ni5: ['l\u00E6\u014Bst\u0259n'] },
            hughes:      { espeak: 'hju\u02D0z',    mw: 'hju\u02D0z',         da1ni5: ['hju\u02D0z'] },
            asked:       { espeak: '\u00E6skt',     mw: '\u00E6skt',     da1ni5: ['\u00E6skt'] },
            what:        { espeak: 'w\u028Ct',      mw: 'w\u028Ct',      da1ni5: ['w\u028Ct'] },
            happens:     { espeak: 'h\u00E6p\u0259nz',   mw: 'h\u00E6p\u0259nz',   da1ni5: ['h\u00E6p\u0259nz'] },
            dream:       { espeak: 'd\u0279i\u02D0m',    mw: 'd\u0279i\u02D0m',    da1ni5: ['d\u0279i\u02D0m'] },
            "deferred.": { espeak: 'd\u026Af\u025C\u02D0\u0279d',  mw: 'd\u026Af\u025C\u02D0\u0279d',  da1ni5: ['d\u026Af\u025C\u02D0\u0279d'] },
            deferred:    { espeak: 'd\u026Af\u025C\u02D0\u0279d',  mw: 'd\u026Af\u025C\u02D0\u0279d',  da1ni5: ['d\u026Af\u025C\u02D0\u0279d'] },
            for:         { espeak: 'f\u0254\u02D0\u0279',     mw: 'f\u0254\u02D0\u0279',     da1ni5: ['f\u0254\u02D0\u0279'] }
        };

        this.ipaQuiz = {
            phrases: phrases,
            wordIpa: wordIpa,
            tier: tier,
            currentIndex: 0,
            results: [],
            retryCount: 0
        };

        const productForQuiz = String(this.config?.productName || this.config?.identity?.name || '').trim();
        this.quiz = {
            active: true,
            id: this.config.defaultAudioQuizId || 'pronunciation_ipa_audio_quiz',
            title: productForQuiz ? `${productForQuiz} Pronunciation Assessment` : 'Pronunciation Assessment',
            type: 'ipa_audio',
            tier: tier,
            startedAt: Date.now(),
            completedAt: null
        };

        const tierLabel = tierNames[tier] || 'Intermediate';
        this.addMessage('assistant', `**${tierLabel} Pronunciation Quiz** \u2014 You\'ll read **5 phrases** aloud, one at a time. I\'ll analyze each sound you make.`);

        setTimeout(() => {
            this.showIpaPhrase(0);
        }, 600);
    }

    showIpaPhrase(index) {
        const phrase = this.ipaQuiz.phrases[index];
        const num = index + 1;
        const total = this.ipaQuiz.phrases.length;
        this.ipaQuiz.retryCount = 0;

        // Build 5-step progress blocks: completed=gray+✓, current=highlighted, upcoming=gray
        let steps = '';
        for (let i = 1; i <= total; i++) {
            const cls = i < num ? 'completed' : i === num ? 'active' : 'upcoming';
            const check = i < num ? '<span class="flosc-ipa-step-check">✓</span>' : '';
            steps += `<div class="flosc-ipa-step flosc-ipa-step-${cls}">${check}<span class="flosc-ipa-step-num">${i}</span></div>`;
        }

        const html = `
            <div class="flosc-ipa-phrase-card">
                <div class="flosc-ipa-step-bar" id="flosc-ipa-step-bar-${num}">
                    ${steps}
                </div>
                <div class="flosc-ipa-phrase-text flosc-ipa-phrase-entrance">${this.escapeHtml(phrase)}</div>
                <div class="flosc-ipa-phrase-controls">
                    <button class="flosc-ipa-record-btn flosc-ipa-record-pulse" id="flosc-ipa-record-${num}">
                        🎤 Record
                    </button>
                    <div class="flosc-ipa-waveform" id="flosc-ipa-waveform-${num}">
                        <canvas class="flosc-ipa-waveform-canvas" id="flosc-ipa-canvas-${num}"></canvas>
                    </div>
                    <div class="flosc-ipa-status" id="flosc-ipa-status-${num}">Tap to record yourself saying this phrase</div>
                </div>
                <div class="flosc-ipa-flyoff" id="flosc-ipa-flyoff-${num}"></div>
            </div>
        `;

        this.addMessage('assistant', html, true);

        setTimeout(() => {
            const btn = document.getElementById(`flosc-ipa-record-${num}`);
            if (btn) {
                btn.addEventListener('click', () => this.toggleIpaRecording(num));
            }
            // Sweep animation: light sweeps left-to-right across all blocks 3 times
            const stepBar = document.getElementById(`flosc-ipa-step-bar-${num}`);
            if (stepBar) stepBar.classList.add('flosc-ipa-step-sweep');
        }, 100);
    }

    async toggleIpaRecording(phraseNum) {
        const btn = document.getElementById(`flosc-ipa-record-${phraseNum}`);
        const status = document.getElementById(`flosc-ipa-status-${phraseNum}`);

        // Stop branch — only when actively recording
        if (this.isRecording) {
            if (this.mediaRecorder) this.mediaRecorder.stop();
            this.isRecording = false;
            this.isAcquiringMic = false;
            this.stopWaveformVisualizer();
            if (btn) {
                const thankEmojis = ['❤️ ', '✨', '🙏', '😍', '💖', '💕', '🌟', '😊', '🥰', '💛', '💜', '🫶'];
                btn.textContent = thankEmojis[Math.floor(Math.random() * thankEmojis.length)];
                btn.classList.remove('recording', 'flosc-ipa-record-pulse');
                btn.classList.add('completed');
                btn.disabled = true;
            }
            const stepBar = btn?.closest('.flosc-ipa-phrase-card')?.querySelector('.flosc-ipa-step-bar');
            if (stepBar) {
                const activeStep = stepBar.querySelector('.flosc-ipa-step-active');
                if (activeStep) {
                    activeStep.classList.remove('flosc-ipa-step-active');
                    activeStep.classList.add('flosc-ipa-step-completed');
                    activeStep.innerHTML = '<span class="flosc-ipa-step-check">✓</span><span class="flosc-ipa-step-num">' + activeStep.querySelector('.flosc-ipa-step-num')?.textContent + '</span>';
                }
            }
            if (status) status.textContent = 'Analyzing...';
            requestAnimationFrame(() => {
                if (this.chatMessages) {
                    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
                }
            });
            this.showIpaFlyoff(phraseNum);
            return;
        }

        // Guard: ignore tap if mic acquisition is already in progress
        if (this.isAcquiringMic) return;

        if (typeof MediaRecorder === 'undefined') {
            if (status) status.textContent = 'Audio recording is not supported in this browser.';
            return;
        }

        this.isAcquiringMic = true;

        if (btn) {
            btn.disabled = true;
            btn.textContent = '🎤 Record';
            btn.classList.remove('recording');
        }
        if (status) status.textContent = 'Requesting microphone…';

        try {
            const stream = await Promise.race([
                navigator.mediaDevices.getUserMedia({ audio: true }),
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('Microphone request timed out')), 8000)
                )
            ]);

            this.recordingStream = stream;
            this.audioChunks = [];

            this.mediaRecorder = new MediaRecorder(stream);
            const { mime: actualMime, format: audioFormat } = this._resolveMime(this.mediaRecorder);

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) this.audioChunks.push(e.data);
            };

            this.mediaRecorder.onstop = () => {
                const blob = new Blob(this.audioChunks, { type: actualMime });
                const audioUrl = URL.createObjectURL(blob);
                this.processIpaRecording(blob, audioUrl, audioFormat, phraseNum);
            };

            this.mediaRecorder.start();

            // Recording is live — now switch UI to Stop state
            this.isRecording = true;
            this.isAcquiringMic = false;

            if (btn) {
                btn.disabled = false;
                btn.textContent = '⏹ Stop';
                btn.classList.add('recording');
            }
            if (status) status.textContent = 'Recording… tap Stop when done';

            this.startWaveformVisualizer(stream, phraseNum);

        } catch (e) {
            this.isAcquiringMic = false;
            this.isRecording = false;
            if (this.recordingStream) {
                this.recordingStream.getTracks().forEach(t => t.stop());
                this.recordingStream = null;
            }
            if (btn) {
                btn.disabled = false;
                btn.textContent = '🎤 Record';
                btn.classList.remove('recording', 'completed');
                btn.classList.add('flosc-ipa-record-pulse');
            }
            this.logError('[FLOSC IPA] Mic access failed', e);
            if (status) status.textContent = 'Could not access microphone. Tap to retry.';
        }
    }

    // WhatsApp-style scrolling waveform — bars appear on the right edge and
    // scroll left as you speak.  Each bar's height = amplitude at that moment.
    startWaveformVisualizer(stream, phraseNum) {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            audioCtx.resume();   // Android Chrome: new AudioContexts start suspended
            const source = audioCtx.createMediaStreamSource(stream);
            const analyser = audioCtx.createAnalyser();
            analyser.fftSize = 512;
            analyser.smoothingTimeConstant = 0.8;
            source.connect(analyser);

            const canvas = document.getElementById(`flosc-ipa-canvas-${phraseNum}`);
            const waveformEl = document.getElementById(`flosc-ipa-waveform-${phraseNum}`);
            if (!canvas || !waveformEl) return;

            // Show the waveform area and canvas
            waveformEl.classList.add('active');

            const containerRect = waveformEl.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            canvas.width = containerRect.width * dpr;
            canvas.height = containerRect.height * dpr;
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);
            const w = containerRect.width;
            const h = containerRect.height;

            const bufLen = analyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufLen);

            // Scrolling bar state
            const barWidth = 3;
            const barGap = 2;
            const barCount = Math.floor(w / (barWidth + barGap));
            const amplitudes = new Float32Array(barCount).fill(0);
            const sampleMs = 55;
            let lastSample = 0;

            this._waveformAnim = { audioCtx, analyser, running: true };

            const draw = (timestamp) => {
                if (!this._waveformAnim || !this._waveformAnim.running) return;
                requestAnimationFrame(draw);

                // Sample new amplitude at fixed intervals
                if (timestamp - lastSample >= sampleMs) {
                    lastSample = timestamp;
                    analyser.getByteTimeDomainData(dataArray);

                    let sum = 0;
                    for (let j = 0; j < bufLen; j++) {
                        const v = (dataArray[j] - 128) / 128;
                        sum += v * v;
                    }
                    const rms = Math.sqrt(sum / bufLen);

                    // Shift left, add new sample on right
                    for (let i = 0; i < barCount - 1; i++) {
                        amplitudes[i] = amplitudes[i + 1];
                    }
                    amplitudes[barCount - 1] = rms;
                }

                // Draw bars right-aligned (newest on right, oldest on left)
                ctx.clearRect(0, 0, w, h);
                const maxHeight = h - 4;

                for (let i = 0; i < barCount; i++) {
                    const barH = Math.max(2, amplitudes[i] * maxHeight * 3);
                    const x = i * (barWidth + barGap);
                    const y = (h - barH) / 2;

                    // Fade older bars slightly (left = older = more transparent)
                    const age = 1 - (i / barCount);
                    const alpha = 0.25 + Math.min(amplitudes[i] * 2.5, 0.55) - (age * 0.1);
                    ctx.fillStyle = `rgba(107, 114, 128, ${Math.max(0.15, alpha)})`;
                    ctx.beginPath();
                    ctx.roundRect(x, y, barWidth, barH, 1.5);
                    ctx.fill();
                }
            };
            requestAnimationFrame(draw);
        } catch (e) {
            this.log('[FLOSC] Waveform visualizer not available:', e.message);
        }
    }

    stopWaveformVisualizer() {
        if (this._waveformAnim) {
            this._waveformAnim.running = false;
            if (this._waveformAnim.audioCtx) {
                this._waveformAnim.audioCtx.close().catch(() => {});
            }
            this._waveformAnim = null;
        }
    }

    // IPA fly-off animation — shows IPA symbols floating up after recording stops
    showIpaFlyoff(phraseNum) {
        const index = this.ipaQuiz.currentIndex;
        const phrase = this.ipaQuiz.phrases[index];
        const words = phrase.trim().split(/\s+/);

        // Gather IPA symbols from the phrase
        const symbols = [];
        words.forEach(w => {
            const key = w.toLowerCase();
            const ipa = this.ipaQuiz.wordIpa[key];
            if (ipa && ipa.da1ni5 && ipa.da1ni5[0]) {
                const chars = ipa.da1ni5[0].split('');
                chars.forEach(c => {
                    if (c.match(/[^\s]/) && symbols.indexOf(c) === -1 && symbols.length < 8) {
                        symbols.push(c);
                    }
                });
            }
        });

        // Pick 4-5 random IPA symbols
        const picked = [];
        const pool = [...symbols];
        const count = Math.min(3 + Math.floor(Math.random() * 3), pool.length);
        for (let i = 0; i < count; i++) {
            const idx = Math.floor(Math.random() * pool.length);
            picked.push(pool.splice(idx, 1)[0]);
        }

        if (picked.length === 0) return;

        // Get the record button position as the origin point
        const btn = document.getElementById(`flosc-ipa-record-${phraseNum}`);
        const rect = btn ? btn.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2 };

        picked.forEach((s, i) => {
            const el = document.createElement('span');
            el.className = 'flosc-ipa-fly-symbol';
            el.textContent = '/' + s + '/';
            document.body.appendChild(el);

            // Start near the record button with slight random spread
            const startX = rect.left + Math.random() * 40 - 20;
            const startY = rect.top + Math.random() * 20 - 10;
            const endX = startX - (window.innerWidth * 0.45);
            const endY = startY - (window.innerHeight * 0.45);
            const midX = startX - 8;
            const midY = startY - 80;
            const opacityStart = 0.30 + Math.random() * 0.43;

            el.animate(
                [
                    { transform: `translate(${startX}px, ${startY}px) scale(1)`, opacity: opacityStart },
                    { transform: `translate(${midX}px, ${midY}px) scale(0.97)`, opacity: 0.6 },
                    { transform: `translate(${endX}px, ${endY}px) scale(0.5)`, opacity: 0 }
                ],
                {
                    duration: 1100,
                    delay: i * 80,
                    easing: 'ease-out',
                    fill: 'forwards'
                }
            );

            // Clean up after animation completes (1s animation + stagger)
            setTimeout(() => {
                if (el.parentNode) el.parentNode.removeChild(el);
            }, 1100 + i * 80);
        });
    }

    async processIpaRecording(blob, audioUrl, audioFormat, phraseNum) {
        const index = this.ipaQuiz.currentIndex;
        const phrase = this.ipaQuiz.phrases[index];
        const words = phrase.trim().split(/\s+/);

        // Single da1ni5 IPA per word — one alignment pass on server
        const targetIpa = {};
        words.forEach(w => {
            const key = w.toLowerCase();
            const ipa = this.ipaQuiz.wordIpa[key];
            if (ipa && ipa.da1ni5) {
                targetIpa[key] = ipa.da1ni5;
            }
        });

        let b64;
        try {
            const buf = await blob.arrayBuffer();
            const bytes = new Uint8Array(buf);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
            b64 = btoa(binary);
        } catch (e) {
            this.logError('[FLOSC IPA] Audio processing failed', e);
            const recBtn = document.getElementById(`flosc-ipa-record-${phraseNum}`);
            if (recBtn) { recBtn.disabled = false; recBtn.textContent = '🎤 Record'; recBtn.classList.remove('recording', 'completed'); recBtn.classList.add('flosc-ipa-record-pulse'); }
            const recStatus = document.getElementById(`flosc-ipa-status-${phraseNum}`);
            if (recStatus) recStatus.textContent = 'Could not submit — tap to retry';
            this.isRecording = false;
            return;
        }

        const endpoint = words.length === 1 ? '/analyze' : '/analyze-phrase';
        const body = { audio: b64, target_text: phrase, format: audioFormat, phrase_num: phraseNum };
        if (endpoint === '/analyze-phrase' && Object.keys(targetIpa).length > 0) {
            body.target_ipa = targetIpa;
        }

        // Reserve result slot so phrase order is preserved
        const resultIndex = index;
        this.ipaQuiz.results[resultIndex] = null;

        // Non-blocking UI: confirm and advance immediately
        this.addMessage('assistant', `Phrase ${phraseNum} recorded. ✓`);

        this.ipaQuiz.currentIndex++;
        if (this.ipaQuiz.currentIndex < this.ipaQuiz.phrases.length) {
            this._showQuizEscapeHatch(phraseNum);
            this.showTyping();
            setTimeout(() => {
                this.hideTyping();
                this.showIpaPhrase(this.ipaQuiz.currentIndex);
            }, 800);
        } else {
            this.showTyping();
        }

        // Sequential API queue: each phrase waits for the previous to finish.
        // Keeps DO server processing one at a time (~3-6s each).
        // Phrase 1 returns session_id before phrase 2 sends.
        if (!this.ipaQuiz.apiQueue) {
            this.ipaQuiz.apiQueue = Promise.resolve();
        }

        const analysisPromise = this.ipaQuiz.apiQueue = this.ipaQuiz.apiQueue.then(() => {
            if (this.ipaQuiz.sessionId) {
                body.session_id = this.ipaQuiz.sessionId;
            }
            const apiBaseUrl = this.config.ipaApiBaseUrl || '';
            return fetch(`${apiBaseUrl}${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body)
            })
            .then(resp => resp.ok ? resp.json() : Promise.reject(new Error(`API ${resp.status}`)))
            .then(data => {
                if (data.session_id) {
                    this.ipaQuiz.sessionId = data.session_id;
                }
                this.ipaQuiz.results[resultIndex] = { phrase, data, audioUrl };
                this.log(`[FLOSC IPA] Phrase ${phraseNum} analysis complete`);
            })
            .catch(e => {
                this.logError(`[FLOSC IPA] Phrase ${phraseNum} analysis failed`, e);
                this.ipaQuiz.results[resultIndex] = {
                    phrase,
                    data: { words: [], score: 0, skipped: true },
                    audioUrl
                };
            });
        });

        if (!this.ipaQuiz.pendingAnalyses) {
            this.ipaQuiz.pendingAnalyses = [];
        }
        this.ipaQuiz.pendingAnalyses.push(analysisPromise);

        if (this.ipaQuiz.currentIndex >= this.ipaQuiz.phrases.length) {
            Promise.allSettled(this.ipaQuiz.pendingAnalyses).then(() => {
                this.hideTyping();
                this.showIpaQuizSummary();
            });
        }
    }

    _reEnableIpaRecordButton(phraseNum) {
        const btn = document.getElementById(`flosc-ipa-record-${phraseNum}`);
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🎤 Record';
            btn.classList.remove('completed');
            btn.classList.add('flosc-ipa-record-pulse');
        }
        const stepBar = btn?.closest('.flosc-ipa-phrase-card')?.querySelector('.flosc-ipa-step-bar');
        if (stepBar) {
            const completedStep = stepBar.querySelector('.flosc-ipa-step-completed:last-of-type');
            if (completedStep && completedStep.querySelector('.flosc-ipa-step-num')?.textContent == phraseNum) {
                completedStep.classList.remove('flosc-ipa-step-completed');
                completedStep.classList.add('flosc-ipa-step-active');
                const num = completedStep.querySelector('.flosc-ipa-step-num');
                if (num) completedStep.innerHTML = '<span class="flosc-ipa-step-num">' + num.textContent + '</span>';
            }
        }
        const status = document.getElementById(`flosc-ipa-status-${phraseNum}`);
        if (status) status.textContent = 'Tap to try again';
    }

    _advanceIpaWithZeroScore(phrase, phraseNum) {
        this.ipaQuiz.results.push({
            phrase,
            data: { words: [], score: 0, skipped: true },
            audioUrl: null
        });
        this.ipaQuiz.currentIndex++;
        if (this.ipaQuiz.currentIndex < this.ipaQuiz.phrases.length) {
            this.showTyping();
            setTimeout(() => {
                this.hideTyping();
                this.showIpaPhrase(this.ipaQuiz.currentIndex);
            }, 800);
        } else {
            setTimeout(() => {
                this.showIpaQuizSummary();
            }, 800);
        }
    }

    /**
     * v8.0.1: Funny buy-now escape hatch shown between quiz phrases.
     * Clicking it opens the offer/purchase flow immediately.
     */
    /**
     * Tier-aware escape hatch shown between quiz phrases.
     * Beginner: upgrade link. Intermediate: drop to beginner. Advanced: drop to beginner or intermediate.
     */
    _showQuizEscapeHatch(phraseNum) {
        const tier = this.ipaQuiz.tier || 'intermediate';
        let escapeInner;

        if (tier === 'beginner') {
            escapeInner = `<a href="#" class="flosc-quiz-escape-link" data-action="buy">This is excellent\u2026I\u2019d like to upgrade now!</a>`;
        } else if (tier === 'advanced') {
            escapeInner = `<span class="flosc-quiz-escape-text">That\u2019s too hard! Take me to the </span><a href="#" class="flosc-quiz-escape-link" data-action="beginner">beginner</a><span class="flosc-quiz-escape-text"> / </span><a href="#" class="flosc-quiz-escape-link" data-action="intermediate">intermediate</a><span class="flosc-quiz-escape-text"> quiz! \uD83D\uDE2D</span>`;
        } else {
            escapeInner = `<a href="#" class="flosc-quiz-escape-link" data-action="beginner">That\u2019s too hard! I want the beginner\u2019s quiz! \uD83D\uDE2D</a>`;
        }

        const escapeHtml = `<div class="flosc-quiz-escape-hatch" id="flosc-quiz-escape-${phraseNum}">${escapeInner}</div>`;
        this.addMessage('assistant', escapeHtml, true);

        setTimeout(() => {
            const container = document.getElementById(`flosc-quiz-escape-${phraseNum}`);
            if (!container) return;
            container.querySelectorAll('a[data-action]').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const action = link.dataset.action;
                    this.quiz.active = false;
                    this.ipaQuiz.results = [];

                    if (action === 'buy') {
                        this.addMessage('assistant', "Let\u2019s get you set up with full access. \uD83C\uDF89");
                        const productForAuth = String(this.config?.productName || this.config?.identity?.name || '').trim();
                        this.config.authEscapeTitle = productForAuth
                            ? `Create Your ${productForAuth} Account`
                            : 'Create Your Account';
                        this.config.authEscapeSubtitle = 'Sign up to unlock full lessons and practice.';
                        setTimeout(() => this.showAuthModal('authEscape'), 400);
                    } else {
                        const label = action === 'beginner' ? 'beginner' : 'intermediate';
                        this.addMessage('assistant', `No problem \u2014 let\u2019s try the ${label} level.`);
                        this.showTyping();
                        setTimeout(() => {
                            this.hideTyping();
                            this.startIpaQuiz(action);
                        }, 800);
                    }
                });
            });
        }, 50);
    }

    showIpaPhraseResult(data, audioUrl, phrase, phraseNum) {
        const words = data.words || [{ word: data.target_text, expected_ipa: data.expected_ipa, phonemes: data.phonemes }];
        const allPh = words.flatMap(w => w.phonemes);
        const total = allPh.length;
        const avg = total ? allPh.reduce((s, p) => s + p.confidence, 0) / total : 0;
        const high = allPh.filter(p => p.confidence >= 0.5).length;
        const med = allPh.filter(p => p.confidence >= 0.1 && p.confidence < 0.5).length;
        const low = allPh.filter(p => p.confidence < 0.1).length;

        const cl = c => c >= 0.5 ? 'high' : c >= 0.1 ? 'med' : 'low';
        const cc = c => c >= 0.5 ? 'var(--flosc-ipa-high)' : c >= 0.1 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';

        let h = `<div class="flosc-ipa-result">`;
        h += `<div class="flosc-ipa-result-header"><span class="flosc-ipa-result-phrase">Phrase ${phraseNum}: ${this.escapeHtml(phrase)}</span></div>`;
        h += `<div class="flosc-ipa-playback"><audio controls src="${audioUrl}"></audio></div>`;
        h += `<div class="flosc-ipa-summary-line">${words.length} word${words.length > 1 ? 's' : ''}, ${total} phonemes &middot; avg ${(avg * 100).toFixed(0)}% &middot; <span class="flosc-ipa-c-high">${high} HIGH</span> &middot; <span class="flosc-ipa-c-med">${med} MED</span> &middot; <span class="flosc-ipa-c-low">${low} LOW</span></div>`;

        words.forEach(w => {
            const a = w.phonemes.length ? w.phonemes.reduce((s, p) => s + p.confidence, 0) / w.phonemes.length : 0;
            const key = w.word.toLowerCase();
            const ipaData = this.ipaQuiz.wordIpa[key] || {};

            h += `<div class="flosc-ipa-word-card">`;
            h += `<div class="flosc-ipa-word-head"><span class="flosc-ipa-word">${this.escapeHtml(w.word)}</span><span class="flosc-ipa-word-score flosc-ipa-c-${cl(a)}">${(a * 100).toFixed(0)}%</span></div>`;

            h += `<div class="flosc-ipa-rows">`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">espeak-ng</span><span class="flosc-ipa-val flosc-ipa-espeak">[${this.escapeHtml(ipaData.espeak || '')}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">merriam-webster</span><span class="flosc-ipa-val flosc-ipa-mw">[${this.escapeHtml(ipaData.mw || '')}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">da1ni5</span><span class="flosc-ipa-val flosc-ipa-da1ni5">[${this.escapeHtml((ipaData.da1ni5 || []).join(' | '))}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">scored as</span><span class="flosc-ipa-val flosc-ipa-scored">[${this.escapeHtml(w.expected_ipa)}]</span></div>`;
            h += `</div>`;

            w.phonemes.forEach(p => {
                const pct = (p.confidence * 100).toFixed(1);
                const barW = Math.max(1, p.confidence * 100);
                h += `<div class="flosc-ipa-ph"><span class="flosc-ipa-ph-sym">${this.escapeHtml(p.ipa)}</span><div class="flosc-ipa-ph-track"><div class="flosc-ipa-ph-fill flosc-ipa-ph-${cl(p.confidence)}" data-bar-width="${barW}"></div></div><span class="flosc-ipa-ph-pct flosc-ipa-c-${cl(p.confidence)}">${pct}%</span></div>`;
            });

            h += `</div>`;
        });

        h += `</div>`;
        this.addMessage('assistant', h, true);
    }

    showIpaPhraseResultsAfterLogin(result) {
        // Renders full pronunciation assessment results after login.
        // Called by checkPendingQuizResults() when quizType === 'ipa_audio'.
        // result.phraseResults: array of {phrase, data} from the pre-login quiz.
        // result.wordIpa: the static reference IPA dictionary (espeak, mw, da1ni5).
        const phraseResults = result.phraseResults;
        const wordIpa = result.wordIpa || {};
        const score = result.score;

        // Derive per-phrase stats for summary display
        const allWords = phraseResults.flatMap(r => r.data.words || [{ word: r.data.target_text, expected_ipa: r.data.expected_ipa, phonemes: r.data.phonemes }]);
        const allPh = allWords.flatMap(w => w.phonemes);
        const total = allPh.length;

        // Weakest phonemes across all phrases
        const phonemeScores = {};
        allPh.forEach(p => {
            if (!phonemeScores[p.ipa]) phonemeScores[p.ipa] = [];
            phonemeScores[p.ipa].push(p.confidence);
        });
        const weakest = Object.entries(phonemeScores)
            .map(([ipa, scores]) => ({ ipa, avg: scores.reduce((s, c) => s + c, 0) / scores.length }))
            .sort((a, b) => a.avg - b.avg || Math.random() - 0.5)
            .slice(0, 5);

        const scoreClass = score >= 70 ? '' : score >= 40 ? 'medium-score' : 'low-score';
        const cl = c => c >= 0.5 ? 'high' : c >= 0.1 ? 'med' : 'low';
        const cc = c => c >= 0.5 ? 'var(--flosc-ipa-high)' : c >= 0.1 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';

        // Overall summary
        let summary = `<div class="flosc-ipa-final ${scoreClass}">`;
        summary += `<div class="flosc-ipa-final-title">Pronunciation Assessment Results</div>`;
        summary += `<div class="flosc-quiz-score-circle" data-score-percent="${score}"><span class="flosc-quiz-score-value">${score}%</span></div>`;
        summary += `<div class="flosc-ipa-final-stats">${allWords.length} words &middot; ${total} phonemes across ${phraseResults.length} phrases</div>`;

        if (weakest.length > 0) {
            summary += `<div class="flosc-ipa-weak-title">Sounds to focus on:</div>`;
            summary += `<div class="flosc-ipa-weak-list">`;
            weakest.forEach(w => {
                summary += `<span class="flosc-ipa-weak-item"><span class="flosc-ipa-weak-sym">${this.escapeHtml(w.ipa)}</span> <span class="flosc-ipa-score-value flosc-ipa-c-${cl(w.avg)}">${(w.avg * 100).toFixed(0)}%</span></span>`;
            });
            summary += `</div>`;
        }
        summary += `</div>`;

        const introMsg = this.config.audioQuizResultsMessage || 'Welcome! Here are your pronunciation assessment results.';
        this.addMessage('assistant', introMsg, false);
        setTimeout(() => { this.addMessage('assistant', summary, true); }, 200);

        // Per-phrase accordions — each phrase is a collapsible <details> block
        setTimeout(() => {
            let accordion = `<div class="flosc-ipa-accordion">`;
            accordion += `<div class="flosc-ipa-accordion-hint">Expand each phrase to see your detailed word-by-word analysis</div>`;
            phraseResults.forEach((pr, idx) => {
                const data = pr.data;
                const words = data.words || [{ word: data.target_text, expected_ipa: data.expected_ipa, phonemes: data.phonemes }];
                const phAll = words.flatMap(w => w.phonemes);
                const phTotal = phAll.length;
                const phAvg = phTotal ? phAll.reduce((s, p) => s + p.confidence, 0) / phTotal : 0;
                const phScore = Math.round(phAvg * 100);

                accordion += `<details class="flosc-ipa-accordion-item">`;
                accordion += `<summary class="flosc-ipa-accordion-header">`;
                accordion += `<span class="flosc-ipa-accordion-chevron">▶</span>`;
                accordion += `<span class="flosc-ipa-accordion-phrase">Phrase ${idx + 1}: ${this.escapeHtml(pr.phrase)}</span>`;
                accordion += `<span class="flosc-ipa-accordion-score flosc-ipa-c-${cl(phAvg)}">${phScore}%</span>`;
                accordion += `</summary>`;
                accordion += `<div class="flosc-ipa-accordion-body">`;

                words.forEach(w => {
                    const wAvg = w.phonemes.length ? w.phonemes.reduce((s, p) => s + p.confidence, 0) / w.phonemes.length : 0;
                    const key = w.word.toLowerCase();
                    const ipaData = wordIpa[key] || {};

                    accordion += `<div class="flosc-ipa-word-card">`;
                    accordion += `<div class="flosc-ipa-word-head"><span class="flosc-ipa-word">${this.escapeHtml(w.word)}</span><span class="flosc-ipa-word-score flosc-ipa-c-${cl(wAvg)}">${(wAvg * 100).toFixed(0)}%</span></div>`;

                    accordion += `<div class="flosc-ipa-rows">`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">espeak-ng</span><span class="flosc-ipa-val flosc-ipa-espeak">[${this.escapeHtml(ipaData.espeak || '')}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">merriam-webster</span><span class="flosc-ipa-val flosc-ipa-mw">[${this.escapeHtml(ipaData.mw || '')}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">da1ni5</span><span class="flosc-ipa-val flosc-ipa-da1ni5">[${this.escapeHtml((ipaData.da1ni5 || []).join(' | '))}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">scored as</span><span class="flosc-ipa-val flosc-ipa-scored">[${this.escapeHtml(w.expected_ipa)}]</span></div>`;
                    accordion += `</div>`;

                    w.phonemes.forEach(p => {
                        const pct = (p.confidence * 100).toFixed(1);
                        const barW = Math.max(1, p.confidence * 100);
                        accordion += `<div class="flosc-ipa-ph"><span class="flosc-ipa-ph-sym">${this.escapeHtml(p.ipa)}</span><div class="flosc-ipa-ph-track"><div class="flosc-ipa-ph-fill flosc-ipa-ph-${cl(p.confidence)}" data-bar-width="${barW}"></div></div><span class="flosc-ipa-ph-pct flosc-ipa-c-${cl(p.confidence)}">${pct}%</span></div>`;
                    });

                    accordion += `</div>`;
                });

                accordion += `</div></details>`;
            });
            accordion += `</div>`;
            this.addMessage('assistant', accordion, true);

            // Upsell message — configurable, with ranked phoneme placeholders
            const ranked = result.rankedPhonemes || [];
            const upsellTpl = this.config.audioQuizUpsellMessage || '';
            if (upsellTpl && ranked.length >= 4) {
                const upsellMsg = upsellTpl
                    .replace('{1st}', ranked[0] || '')
                    .replace('{2nd}', ranked[1] || '')
                    .replace('{3rd}', ranked[2] || '')
                    .replace('{4th}', ranked[3] || '');
                setTimeout(() => {
                    this.addMessage('assistant', upsellMsg, false);
                }, 300);
            }

            // Congratulations message — after accordions and upsell
            const freeCount = parseInt(this.user?.freeLessonsCount) || 0;
            if (freeCount > 0 && this.state === 'guest') {
                const lessonWord = freeCount === 1 ? 'lesson' : 'lessons';
                setTimeout(() => {
                    this.addMessage('assistant', `🎉 Congratulations! You have been granted access to <strong>${freeCount}</strong> free ${lessonWord} — you can try them out right here in this chat!`, true);
                    setTimeout(() => this.floscShowUserAutoPrompts(), 500);
                }, 600);
            } else {
                setTimeout(() => this.floscShowUserAutoPrompts(), 500);
            }
        }, 500);
    }

    showIpaQuizSummary() {
        const results = this.ipaQuiz.results.filter(r => r !== null);
        const allWords = results.flatMap(r => r.data.words || [{ word: r.data.target_text, expected_ipa: r.data.expected_ipa, phonemes: r.data.phonemes }]);
        const allPh = allWords.flatMap(w => w.phonemes);
        const total = allPh.length;
        const avg = total ? allPh.reduce((s, p) => s + p.confidence, 0) / total : 0;
        const score = Math.round(avg * 100);

        // Build per-phoneme scores — needed for ranking (all states) and display (guest/member)
        const phonemeScores = {};
        allPh.forEach(p => {
            if (!phonemeScores[p.ipa]) phonemeScores[p.ipa] = [];
            phonemeScores[p.ipa].push(p.confidence);
        });

        if (this.state === 'visitor') {
            // Visitors: scores are computed and stored in this.ipaQuiz.results but NOT displayed.
            // Visitor registers → scores sent to PHP → stored in user meta → shown to guest.
            const completeMsg = (this.config.audioQuizCompleteMessage || 'Pronunciation assessment complete! All {total} phrases recorded and analyzed. Sign up to see your results.')
                .replace('{total}', results.length);
            this.addMessage('assistant', completeMsg);
        } else {
            // Guests and members: show full summary with score and weakest sounds
            const weakest = Object.entries(phonemeScores)
                .map(([ipa, scores]) => ({ ipa, avg: scores.reduce((s, c) => s + c, 0) / scores.length }))
                .sort((a, b) => a.avg - b.avg || Math.random() - 0.5)
                .slice(0, 5);

            const scoreClass = score >= 70 ? '' : score >= 40 ? 'medium-score' : 'low-score';

            let h = `<div class="flosc-ipa-final ${scoreClass}">`;
            h += `<div class="flosc-ipa-final-title">Pronunciation Assessment Complete</div>`;
            h += `<div class="flosc-quiz-score-circle" data-score-percent="${score}"><span class="flosc-quiz-score-value">${score}%</span></div>`;
            h += `<div class="flosc-ipa-final-stats">${allWords.length} words &middot; ${total} phonemes across ${results.length} phrases</div>`;

            if (weakest.length > 0) {
                h += `<div class="flosc-ipa-weak-title">Sounds to focus on:</div>`;
                h += `<div class="flosc-ipa-weak-list">`;
                weakest.forEach(w => {
                    h += `<span class="flosc-ipa-weak-item"><span class="flosc-ipa-weak-sym">${this.escapeHtml(w.ipa)}</span> <span class="flosc-ipa-score-value flosc-ipa-c-${cl(w.avg)}">${(w.avg * 100).toFixed(0)}%</span></span>`;
                });
                h += `</div>`;
            }

            h += `</div>`;
            this.addMessage('assistant', h, true);

            // Admin detail: per-phrase accordion with word-level phoneme scores
            if (this.state === 'admin' || (this.user && this.user.isAdmin)) {
                let d = `<div class="flosc-ipa-accordion">`;
                results.forEach((r, i) => {
                    const phraseWords = r.data.words || [{ word: r.data.target_text, phonemes: r.data.phonemes }];
                    const phrasePh = phraseWords.flatMap(w => w.phonemes);
                    const phraseAvg = phrasePh.length ? phrasePh.reduce((s, p) => s + p.confidence, 0) / phrasePh.length : 0;
                    const phraseScore = Math.round(phraseAvg * 100);
                    d += `<details class="flosc-ipa-accordion-item">`;
                    d += `<summary class="flosc-ipa-accordion-header"><span class="flosc-ipa-accordion-phrase">${this.escapeHtml(r.phrase)}</span><span class="flosc-ipa-accordion-score flosc-ipa-c-${cl(phraseAvg)}">${phraseScore}%</span></summary>`;
                    d += `<div class="flosc-ipa-accordion-body">`;
                    phraseWords.forEach(w => {
                        d += `<div class="flosc-ipa-word-row"><strong>${this.escapeHtml(w.word)}</strong>`;
                        d += `<div class="flosc-ipa-phoneme-list">`;
                        (w.phonemes || []).forEach(p => {
                            d += `<span class="flosc-ipa-phoneme-chip flosc-ipa-phoneme-chip-${cl(p.confidence)}"><span class="flosc-ipa-weak-sym">${this.escapeHtml(p.ipa)}</span> <span class="flosc-ipa-score-value flosc-ipa-c-${cl(p.confidence)}">${(p.confidence * 100).toFixed(0)}%</span></span>`;
                        });
                        d += `</div></div>`;
                    });
                    d += `</div></details>`;
                });
                d += `</div>`;
                this.addMessage('assistant', d, true);
            }
        }

        this.quiz.completedAt = Date.now();
        this.quiz.score = score;

        // Store quiz results on the ipaQuiz object so buildIVRContext can relay to AI
        this.ipaQuiz.score = score;
        this.ipaQuiz.rankedPhonemes = Object.entries(phonemeScores)
            .map(([ipa, scores]) => ({ ipa, avg: scores.reduce((s, c) => s + c, 0) / scores.length }))
            .sort((a, b) => a.avg - b.avg);
        this.ipaQuiz.weakestSounds = this.ipaQuiz.rankedPhonemes.slice(0, 5).map(p => `${p.ipa} ${(p.avg * 100).toFixed(0)}%`);
        this.buildIVRContext();

        // Finalize session on DO — compiles summary.json with aggregated scores.
        // Non-blocking: visitor sees "Sign up" message immediately.
        if (this.ipaQuiz.sessionId) {
            const apiBaseUrl = this.config.ipaApiBaseUrl || '';
            fetch(`${apiBaseUrl}/finalize-session`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: this.ipaQuiz.sessionId })
            }).then(r => r.json()).then(res => {
                this.log('[FLOSC] Session finalized on DO:', this.ipaQuiz.sessionId, 'score:', res.score);
            }).catch(e => this.logError('[FLOSC] finalize-session failed', e));
        }

        // Store detailed per-phrase results so they survive until after login.
        // checkPendingQuizResults() reads this to display full results to guests.
        const phraseResults = results.map(r => ({
            phrase: r.phrase,
            data: r.data
        }));

        // Rank all phonemes worst→best across all phrases
        const phonemeMap = this.config.audioQuizPhonemeLessonMap || {};
        const rankedPhonemes = Object.entries(phonemeScores)
            .map(([ipa, scores]) => ({ ipa, avg: scores.reduce((s, c) => s + c, 0) / scores.length }))
            .sort((a, b) => a.avg - b.avg || Math.random() - 0.5);

        // Filter to phonemes that have a lesson mapping, take 10 worst
        const mappedWorst = rankedPhonemes.filter(p => phonemeMap[p.ipa] !== undefined).slice(0, 10);

        // Each phoneme maps to an array of lesson numbers (one phoneme can appear in multiple lessons).
        // Flatten all lesson numbers into a single de-duplicated array for the Free Lesson Manager.
        const incorrectLessonNums = [...new Set(mappedWorst.flatMap(p => {
            const val = phonemeMap[p.ipa];
            return Array.isArray(val) ? val : [val];
        }))];

        // v8.0.0: Build ranked worst lessons array (ordered worst→best) for Free Lesson Manager.
        // Each entry: {ipa, score, lessons: [lesson_nums]}
        // score = avg confidence (0–1) so PHP can group tied phonemes into tiers.
        const rankedWorstLessons = mappedWorst.map(p => ({
            ipa: p.ipa,
            score: Math.round(p.avg * 1000) / 1000,
            lessons: Array.isArray(phonemeMap[p.ipa]) ? phonemeMap[p.ipa] : [phonemeMap[p.ipa]]
        }));

        // Store ranked phoneme names for upsell display after login
        const rankedForUpsell = mappedWorst.map(p => p.ipa);

        this.storeQuizScore({
            score: score,
            incorrect: incorrectLessonNums,
            ranked_worst_lessons: rankedWorstLessons,
            total: total,
            passed: score >= 50,
            userAnswer: `IPA audio quiz: ${results.length} phrases`,
            quizType: 'ipa_audio',
            phraseResults: phraseResults,
            wordIpa: this.ipaQuiz.wordIpa,
            rankedPhonemes: rankedForUpsell,
            skipServerStore: this.state === 'visitor',  // Don't call /store-score API — visitor sends quiz_data at registration instead
            sessionId: this.ipaQuiz.sessionId || null
        });

        this.onQuizComplete();
    }

    startSampleQuiz() {
        // Hardcoded sample quiz for testing/fallback
        this.quiz = {
            active: true,
            id: 'sample',
            title: 'Quick Assessment',
            questions: [
                {
                    id: 'q1',
                    text: 'How would you rate your current skill level?',
                    options: [
                        { key: 'A', text: 'Complete beginner' },
                        { key: 'B', text: 'Some basics' },
                        { key: 'C', text: 'Intermediate' },
                        { key: 'D', text: 'Advanced' }
                    ],
                    correct: null // No wrong answers for assessment
                },
                {
                    id: 'q2',
                    text: 'How much time can you dedicate to practice each week?',
                    options: [
                        { key: 'A', text: 'Less than 1 hour' },
                        { key: 'B', text: '1-3 hours' },
                        { key: 'C', text: '3-5 hours' },
                        { key: 'D', text: 'More than 5 hours' }
                    ],
                    correct: null
                },
                {
                    id: 'q3',
                    text: 'What is your primary goal?',
                    options: [
                        { key: 'A', text: 'Personal improvement' },
                        { key: 'B', text: 'Professional development' },
                        { key: 'C', text: 'Academic requirements' },
                        { key: 'D', text: 'Just curious to learn' }
                    ],
                    correct: null
                }
            ],
            currentIndex: 0,
            answers: [],
            startedAt: Date.now(),
            completedAt: null
        };

        this.addMessage('assistant', `Perfect! Let's do a quick ${this.quiz.questions.length}-question assessment to personalize your experience.`);
        
        setTimeout(() => {
            this.showQuizQuestion();
        }, 800);
    }

    showQuizQuestion() {
        if (!this.quiz.active || this.quiz.currentIndex >= this.quiz.questions.length) {
            this.finishQuiz();
            return;
        }

        const question = this.quiz.questions[this.quiz.currentIndex];
        const questionNum = this.quiz.currentIndex + 1;
        const totalQuestions = this.quiz.questions.length;
        const progressPercent = ((questionNum - 1) / totalQuestions) * 100;

        const optionsHtml = question.options.map(opt => `
            <button class="flosc-quiz-option" data-quiz-answer="${opt.key}" data-question-id="${question.id}">
                <span class="flosc-quiz-option-key">${opt.key}</span>
                <span class="flosc-quiz-option-text">${opt.text}</span>
            </button>
        `).join('');

        const questionHtml = `
            <div class="flosc-quiz-question" data-question-index="${this.quiz.currentIndex}">
                <div class="flosc-quiz-question-header">
                    <span>Question ${questionNum} of ${totalQuestions}</span>
                    <span>${this.quiz.title}</span>
                </div>
                <div class="flosc-quiz-question-text">${question.text}</div>
                <div class="flosc-quiz-options">
                    ${optionsHtml}
                </div>
                <div class="flosc-quiz-progress">
                    <div class="flosc-quiz-progress-bar" data-progress-percent="${progressPercent}"></div>
                </div>
            </div>
        `;

        const messageEl = this.addMessage('assistant', questionHtml, true);
        
        // Bind click handlers to options
        if (messageEl) {
            const options = messageEl.querySelectorAll('.flosc-quiz-option');
            options.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const answer = e.currentTarget.dataset.quizAnswer;
                    const questionId = e.currentTarget.dataset.questionId;
                    this.handleQuizAnswer(answer, questionId, e.currentTarget);
                });
            });
        }
    }

    handleQuizAnswer(answer, questionId, buttonEl) {
        if (!this.quiz.active) return;

        const question = this.quiz.questions[this.quiz.currentIndex];
        const selectedOption = question.options.find(o => o.key === answer);
        
        // Disable all options visually
        const allOptions = buttonEl.closest('.flosc-quiz-options').querySelectorAll('.flosc-quiz-option');
        allOptions.forEach(opt => {
            opt.classList.add('is-disabled');
        });
        
        // Highlight selected
        buttonEl.classList.add('is-selected');

        // Store answer
        this.quiz.answers.push({
            questionId: questionId,
            questionText: question.text,
            answer: answer,
            answerText: selectedOption?.text || answer,
            correct: question.correct ? (answer === question.correct) : null
        });

        // v3.0.4: Removed addMessage('user', ...) — answer already visible in quiz area

        // Move to next question
        this.quiz.currentIndex++;

        // Short delay then next question or finish
        setTimeout(() => {
            if (this.quiz.currentIndex < this.quiz.questions.length) {
                this.showQuizQuestion();
            } else {
                this.finishQuiz();
            }
        }, 600);
    }

    finishQuiz() {
        this.quiz.active = false;
        this.quiz.completedAt = Date.now();

        // Calculate score if questions have correct answers
        const scoredQuestions = this.quiz.answers.filter(a => a.correct !== null);
        const correctCount = this.quiz.answers.filter(a => a.correct === true).length;
        
        let scorePercent = 0;
        let scoreClass = '';
        let scoreMessage = '';

        if (scoredQuestions.length > 0) {
            scorePercent = Math.round((correctCount / scoredQuestions.length) * 100);
            
            if (scorePercent >= 70) {
                scoreClass = '';
                scoreMessage = "Excellent work! You've got a solid foundation.";
            } else if (scorePercent >= 40) {
                scoreClass = 'medium-score';
                scoreMessage = "Good effort! There's room for improvement.";
            } else {
                scoreClass = 'low-score';
                scoreMessage = "No worries! Everyone starts somewhere.";
            }
        } else {
            // Assessment quiz (no right/wrong)
            scorePercent = 100;
            scoreMessage = "Thanks for completing the assessment! Based on your answers, we can personalize your learning path.";
        }

        // Store quiz results via /quiz-result (sets flosc_quiz_result cookie)
        this.storeQuizResults(scorePercent);

        // v3.0.7: ALSO call /store-score with numeric lesson positions.
        // /store-score sets the flosc_prelogin_score signed cookie that
        // process_prelogin_data_for_user() reads on registration to assign
        // the free lesson. Without this, multiple-choice quiz users hit a
        // dead end when clicking "View my free lesson" as a new guest.
        if (scoredQuestions.length > 0) {
            const correctLessons  = this.quiz.answers.map((a, i) => a.correct === true  ? i + 1 : null).filter(n => n !== null);
            const incorrectLessons = this.quiz.answers.map((a, i) => a.correct === false ? i + 1 : null).filter(n => n !== null);
            this.authFetch(this.config.apiUrl + '/store-score', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                body: JSON.stringify({
                    score: scorePercent,
                    quiz_id: this.quiz.id || 'default',
                    quiz_type: 'multiple_choice',
                    correct: correctLessons,
                    incorrect: incorrectLessons,
                })
            }).catch(e => this.logWarn('[FLOSC] store-score failed (non-fatal):', e));
        }

        // Update IVR context
        this.ivr.context.quiz_taken = true;
        this.ivr.context.score = scorePercent;
        this.ivr.context.first_message_after_quiz = true;
        this._scheduleOffersForEvent('quiz');
        
        // v5.0.3 FIX: Also set on this.user so buildIVRContext() picks it up
        this.user = this.user || {};
        this.user.lastQuizScore = scorePercent;
        this.user.quizCompletedAt = Date.now();

        // Show results
        const ctaText = this.state === 'visitor'
            ? 'Create free account to see detailed results'
            : 'View your personalized recommendations';
        const ctaTarget = this.state === 'visitor' ? 'registration' : 'free-lesson';

        const resultHtml = `
            <div class="flosc-quiz-result ${scoreClass}">
                <div class="flosc-quiz-result-score">${scorePercent}%</div>
                <div class="flosc-quiz-result-label">${scoreMessage}</div>
                <button class="flosc-quiz-result-cta" data-quiz-cta="${ctaTarget}">
                    ${ctaText} →
                </button>
            </div>
        `;

        const resultEl = this.addMessage('assistant', resultHtml, true);
        const ctaBtn = resultEl?.querySelector('[data-quiz-cta]');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', () => {
                if (ctaBtn.dataset.quizCta === 'registration') {
                    this.openRegistration();
                } else {
                    this.openFreeLesson();
                }
            });
        }

        // Trigger post-quiz IVR messages after a delay
        setTimeout(() => {
            this.checkAutoMessages();
            this.floscShowUserAutoPrompts();
        }, 1500);

        this.log('[FLOSC Quiz] Completed. Score:', scorePercent, '% Answers:', this.quiz.answers);
    }

    async storeQuizResults(score) {
        try {
            // Store in session/localStorage
            // v1.4.6: Use 'flosc_quiz_result' key to match checkPendingQuizResults()
            const correct = this.quiz.answers.filter(a => a.correct).length;
            const total = this.quiz.answers.length;
            const quizResult = {
                id: this.quiz.id,
                score: score,
                correct: correct,
                total: total,
                answers: this.quiz.answers,
                completedAt: this.quiz.completedAt,
                duration: this.quiz.completedAt - this.quiz.startedAt,
                timestamp: Date.now()
            };

            localStorage.setItem(this.flowStorageKey('flosc_quiz_result'), JSON.stringify(quizResult));

            // Send to server if available
            await this.authFetch(`${this.config.apiUrl}/quiz-result`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify(quizResult)
            });

        } catch (error) {
            this.logError('[FLOSC Quiz] Failed to store results:', error);
        }
    }

    // ============================================
    // FLOSC AUTH MODAL v1.4.0
    // Email + SSO Login Options
    // ============================================
    
    openRegistration() {
        // v1.4.0: Show in-app auth modal instead of redirecting
        this.showAuthModal();
    }
    
    showAuthModal(configKey = 'authModal') {
        const ssoProviders = this.config.ssoProviders || [];
        
        // Read all 10 configurable fields using the configKey prefix
        const title = this.config[configKey + 'Title'] || 'Register Or Log In';
        const subtitle = this.config[configKey + 'Subtitle'] || '';
        const buttonText = this.config[configKey + 'ButtonText'] || 'Continue with Email';
        const ssoDivider = this.config[configKey + 'SsoDivider'] || 'or continue with';
        const emailLabel = this.config[configKey + 'EmailLabel'] || 'Email Address';
        const emailPlaceholder = this.config[configKey + 'EmailPlaceholder'] || 'you@example.com';
        const termsText = this.config[configKey + 'TermsText'] || 'By continuing, you agree to our Terms of Service and Privacy Policy.';
        
        // Build SSO buttons HTML
        let ssoButtonsHtml = '';
        if (ssoProviders.length > 0) {
            ssoButtonsHtml = `
                <div class="flosc-auth-divider">
                    <span>${ssoDivider}</span>
                </div>
                <div class="flosc-sso-buttons">
                    ${ssoProviders.map(p => `
                        <button type="button" class="flosc-sso-btn flosc-sso-${p.id}" 
                                data-provider="${p.id}" 
                                data-auth-url="${p.authUrl}"
                                data-sso-bg="${p.colors.background}"
                                data-sso-text="${p.colors.text}"
                                data-sso-border="${p.colors.border || p.colors.background}">
                            <span class="flosc-sso-icon">${p.icon}</span>
                            <span class="flosc-sso-label">${p.name}</span>
                        </button>
                    `).join('')}
                </div>
            `;
        }
        
        const modalHtml = `
            <div class="flosc-auth-modal-overlay" id="flosc-auth-modal" data-config-key="${configKey}">
                <div class="flosc-auth-modal">
                    <button class="flosc-auth-close" type="button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <div class="flosc-auth-header">
                        <h2>${title}</h2>
                        <p>${subtitle}</p>
                    </div>
                    <form class="flosc-auth-form" id="flosc-auth-form">
                        <div class="flosc-auth-field">
                            <label for="flosc-auth-email">${emailLabel}</label>
                            <input type="email" id="flosc-auth-email" placeholder="${emailPlaceholder}" required>
                        </div>
                        <button type="submit" class="flosc-auth-submit">
                            ${buttonText}
                        </button>
                    </form>
                    ${ssoButtonsHtml}
                    <p class="flosc-auth-terms">
                        ${termsText}
                    </p>
                    <div class="flosc-access-code-trigger">
                        <a href="#" class="flosc-access-code-link flosc-access-code-auth-trigger">Access Code</a>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existing = document.getElementById('flosc-auth-modal');
        if (existing) existing.remove();
        
        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Bind form submission
        document.getElementById('flosc-auth-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('flosc-auth-email').value;
            this.processEmailAuth(email);
        });

        const closeBtn = document.querySelector('#flosc-auth-modal .flosc-auth-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideAuthModal());
        }
        
        // Bind SSO button clicks
        document.querySelectorAll('.flosc-sso-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const provider = btn.dataset.provider;
                const authUrl = btn.dataset.authUrl;
                this.initiateSSO(provider, authUrl);
            });
        });

        // Apply dynamic CSS variables and data-driven measurements.
        this.applyDynamicStyleTokens(document.getElementById('flosc-auth-modal'));

        // Bind Access Code link in auth modal
        const acTrigger = document.querySelector('.flosc-access-code-auth-trigger');
        if (acTrigger) {
            acTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                this._showAccessCodeInput('auth');
            });
        }
        
        // Focus email input
        setTimeout(() => {
            document.getElementById('flosc-auth-email')?.focus();
        }, 100);
    }
    
    showLoginModal() {
        this.showAuthModal('authLoginModal');
    }
    
    hideAuthModal() {
        const modal = document.getElementById('flosc-auth-modal');
        const configKey = modal?.dataset?.configKey || 'authModal';
        if (modal) modal.remove();

        // Only show dismiss message for quiz-context modal when visitor dismissed after quiz
        const dismissMessage = this.config[configKey + 'DismissMessage'] || '';
        if (dismissMessage && this.state === 'visitor' && this.ivr?.context?.quiz_completed) {
            this.addMessage('assistant', dismissMessage, false);
        }
    }

    // Handle visitor menu actions
    handleVisitorMenuAction(action) {
        this.log('[FLOSC] Visitor menu action:', action);

        if (action === 'login' || action === 'open_login_modal') {
            this.showLoginModal();
            return;
        }

        // Legacy compat: old 'signup' action → open_registration
        if (action === 'signup') {
            action = 'open_registration';
        }
        // Legacy compat: old 'quiz' action → open_quiz
        if (action === 'quiz') {
            action = 'open_quiz';
        }

        this.performIVRAction(action);
    }
    
    _buildRegistrationQuizPayload(parsed) {
        if (!parsed) {
            return null;
        }

        const tempId = parsed.tempId || this.ipaQuiz?.tempId || null;

        if (parsed.phraseResults) {
            return {
                score: parsed.score,
                phraseResults: parsed.phraseResults,
                wordIpa: parsed.wordIpa || null,
                rankedPhonemes: parsed.rankedPhonemes || null,
                quizType: parsed.quizType || 'ipa_audio',
                quizId: parsed.quizId || null,
                tempId
            };
        }

        if (parsed.answers || parsed.correct !== undefined || parsed.total !== undefined) {
            return {
                score: parsed.score,
                answers: parsed.answers || null,
                correct: parsed.correct,
                incorrect: parsed.incorrect,
                total: parsed.total,
                passed: parsed.passed,
                quizType: parsed.quizType || 'sequence',
                quizId: parsed.quizId || null,
                tempId
            };
        }

        if (parsed.score !== undefined) {
            return {
                score: parsed.score,
                quizType: parsed.quizType || 'sequence',
                quizId: parsed.quizId || null,
                tempId
            };
        }

        return null;
    }

    async processEmailAuth(email) {
        this.log('[FLOSC Auth] Processing email auth:', email);

        // Ensure PHP can carry visitor token remaining on V→G.
        this._persistVisitorSessionCookie(this.getVisitorSessionId());
        
        // Read config from the active modal
        const modal = document.getElementById('flosc-auth-modal');
        const configKey = modal?.dataset?.configKey || 'authModal';
        const loadingText = this.config[configKey + 'LoadingText'] || 'Sending link...';
        const buttonText = this.config[configKey + 'ButtonText'] || 'Continue with Email';
        const productForLink = String(this.config?.productName || this.config?.identity?.name || '').trim();
        const defaultGuestLinkName = productForLink
            ? `Complimentary ${productForLink} Guest Access Link`
            : 'Complimentary Guest Access Link';
        const checkEmailMsg = (this.config.guestLinkCheckEmailMessage || "We've sent you a {link_name} to your email — click it to access this chat as a guest and view your quiz score, free lessons, and a special upgrade offer.")
            .replace('{link_name}', this.config.guestLinkName || defaultGuestLinkName);
        
        // Update button to show loading
        const submitBtn = document.querySelector('.flosc-auth-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = loadingText;
        }
        
        try {
            // Call the email registration API
            // v3.0.0: Use authFetch() for cross-domain support
            // v8.0.4: Include temp_id so server can link visitor's quiz audio to new account
            // Send browser-computed quiz data so PHP stores instantly (no re-scoring)
            const regBody = {
                email,
                flow_id: this.config.flowId || '',
                visitor_session_id: this.getVisitorSessionId() || ''
            };

            // Preserve exact page context for post-login return.
            try {
                regBody.redirect_to = window.location.href;
            } catch (e) {
                this.log('[FLOSC Auth] Could not read current URL for redirect_to:', e);
            }
            if (this.ipaQuiz?.tempId) {
                regBody.temp_id = this.ipaQuiz.tempId;
            }
            // Attach quiz results from localStorage so server stores them in user meta
            try {
                const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
                if (stored) {
                    const parsed = JSON.parse(stored);
                    const quizPayload = this._buildRegistrationQuizPayload(parsed);
                    if (quizPayload) {
                        regBody.quiz_data = quizPayload;
                        // v8.0.0: Send DO session_id so WP can pull audio + scores from DO
                        if (parsed.sessionId) {
                            regBody.session_id = parsed.sessionId;
                        }
                        this.log('[FLOSC Auth] Including quiz_data + session_id in registration body');
                    }
                }
            } catch (e) {
                this.log('[FLOSC Auth] Could not read quiz data from localStorage:', e);
            }
            const response = await this.authFetch(`${this.config.restUrl}register-email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(regBody)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Refresh nonce if server returned one — keeps WP REST calls valid
                if (result.nonce) {
                    this.config.nonce = result.nonce;
                }
                this.hideAuthModal();
                this.addMessage('assistant', result.magic_link_sent ? checkEmailMsg : (result.message || checkEmailMsg));
            } else {
                throw new Error(result.message || 'Could not send login link');
            }
        } catch (error) {
            this.logError('[FLOSC Auth] Email auth error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = buttonText;
            }
            alert('Registration failed: ' + error.message);
        }
    }

    showCredentialSetupCard() {
        // Generate a readable suggested password
        const _words = ['maple','river','stone','cloud','eagle','frost','grove','cedar','bloom','light'];
        const _w1 = _words[Math.floor(Math.random() * _words.length)];
        const _w2 = _words[Math.floor(Math.random() * _words.length)];
        const _num = Math.floor(Math.random() * 90) + 10;
        const suggestedPassword = `${_w1}-${_w2}-${_num}`;
        // Per-flow Engagement setting; product-neutral fallback
        const nudgeRaw = (this.config.engagementProfileNudgeMessage
            || 'Complete your profile to keep your results private and unlock recordings.').toString();
        const nudgeEsc = nudgeRaw
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const cardHtml = `
            <div class="flosc-profile-card">
                <p class="flosc-profile-title">What should I call you?</p>
                <p class="flosc-profile-help">${nudgeEsc}</p>
                <input type="text" class="flosc-profile-name" placeholder="First name or nickname..."
                    >
                <p class="flosc-profile-label">Set a password so you can log in directly:</p>
                <div class="flosc-profile-password-row">
                    <input type="text" class="flosc-profile-password" value="${suggestedPassword}"
                        >
                    <button class="flosc-profile-copy" title="Copy password">Copy</button>
                </div>
                <p class="flosc-profile-help">You'll receive this by email too — you can change it anytime.</p>
                <div class="flosc-profile-actions">
                    <button class="flosc-profile-save">Save & Continue</button>
                    <a href="#" class="flosc-profile-skip">Skip for now</a>
                </div>
                <p class="flosc-profile-error"></p>
            </div>`;
        this.addMessage('assistant', cardHtml, true);

        const card     = document.querySelector('.flosc-profile-card');
        const nameEl   = card?.querySelector('.flosc-profile-name');
        const passEl   = card?.querySelector('.flosc-profile-password');
        const saveBtn  = card?.querySelector('.flosc-profile-save');
        const skipBtn  = card?.querySelector('.flosc-profile-skip');
        const copyBtn  = card?.querySelector('.flosc-profile-copy');
        const errEl    = card?.querySelector('.flosc-profile-error');

        copyBtn?.addEventListener('click', () => {
            navigator.clipboard.writeText(passEl.value).then(() => {
                copyBtn.textContent = 'Copied!';
                setTimeout(() => { copyBtn.textContent = 'Copy'; }, 2000);
            });
        });

        skipBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            sessionStorage.setItem(this.flowStorageKey('flosc_credential_setup_dismissed'), 'true');
            card.closest('.message')?.remove();
        });

        saveBtn?.addEventListener('click', async () => {
            const name     = nameEl?.value?.trim();
            const password = passEl?.value?.trim();
            if (!name) { nameEl?.focus(); return; }
            if (!password || password.length < 6) {
                if (errEl) { errEl.textContent = 'Password must be at least 6 characters.'; errEl.classList.add('is-visible'); }
                passEl?.focus();
                return;
            }

            saveBtn.disabled    = true;
            saveBtn.textContent = 'Saving...';

            try {
                const body = { display_name: name };
                if (password) body.password = password;
                const response = await this.authFetch(`${this.config.restUrl}update-guest-profile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
                const result = await response.json();
                if (result.success) {
                    this.user.name = name;
                    const profileName  = document.getElementById('flosc_profile_name');
                    const dropdownName = document.getElementById('flosc_dropdown_name');
                    if (profileName)  profileName.textContent  = name;
                    if (dropdownName) dropdownName.textContent = name;

                    card.closest('.message')?.remove();
                    this.addMessage('assistant', `You're all set, ${name}! Loading your account… ✨`);
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    if (errEl) { errEl.textContent = result.message || 'Could not save — please try again.'; errEl.classList.add('is-visible'); }
                    saveBtn.disabled    = false;
                    saveBtn.textContent = 'Save →';
                }
            } catch (e) {
                if (errEl) { errEl.textContent = 'Could not save — please try again.'; errEl.classList.add('is-visible'); }
                saveBtn.disabled    = false;
                saveBtn.textContent = 'Save →';
            }
        });
    }

    async initiateSSO(provider, authUrl) {
        this.log('[FLOSC SSO] Initiating SSO with:', provider);

        // Visitor token wallet id for V→G additive grant after OAuth return.
        this._persistVisitorSessionCookie(this.getVisitorSessionId());

        try {
            const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
            if (stored) {
                const result = JSON.parse(stored);
                const quizPayload = this._buildRegistrationQuizPayload(result);
                if (quizPayload?.phraseResults?.length) {
                    const stashResp = await fetch(`${this.config.restUrl}stash-visitor-quiz`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ quiz_data: quizPayload })
                    });
                    const stashData = await stashResp.json();
                    if (stashData?.success && stashData.token) {
                        document.cookie = `flosc_quiz_stash=${encodeURIComponent(stashData.token)};path=/;max-age=3600;SameSite=Lax`;
                        this.log('[FLOSC SSO] Stashed quiz data for post-login restore');
                    }
                }
                if (result.sessionId) {
                    document.cookie = `flosc_pending_session=${encodeURIComponent(result.sessionId)};path=/;max-age=3600;SameSite=Lax`;
                    this.log('[FLOSC SSO] Set flosc_pending_session cookie:', result.sessionId);
                }
            }
        } catch (e) {
            this.logWarn('[FLOSC SSO] Could not stash quiz data before redirect:', e);
        }

        const redirectTo = window.location.href;
        const separator = authUrl.includes('?') ? '&' : '?';
        const fullAuthUrl = `${authUrl}${separator}redirect_to=${encodeURIComponent(redirectTo)}`;
        window.location.href = fullAuthUrl;
    }

    /**
     * v8.0.1: Handle return from a failed SSO attempt.
     * Shows a friendly error in chat, cleans the URL, and re-shows the auth modal
     * so the user can try a different login method (email, different provider, etc.)
     * without losing their quiz data or chat history.
     */
    handleSSOError(errorMessage) {
        this.log('[FLOSC SSO] Handling SSO error return:', errorMessage);
        
        // Show error in chat
        this.addMessage('assistant', 
            `Login didn\u2019t go through \u2014 ${this.escapeHtml(errorMessage)}. ` +
            'No worries, your progress is saved. You can try a different login method below.'
        );
        
        // Clean the SSO error param from the URL so a refresh doesn't re-trigger
        const url = new URL(window.location.href);
        url.searchParams.delete('flosc_sso_error');
        window.history.replaceState({}, '', url.toString());
        
        // Re-show the auth modal after a short delay so the error message is visible first
        setTimeout(() => this.showAuthModal(), 600);
    }

    /** Loose lesson intent for non-lesson flows (W sound, free lessons, curriculum). */
    _looksLikeLessonAsk(message) {
        const t = this._normalizeAskText(message);
        if (!t) return false;
        if (/\bfree\s+lessons?\b/.test(t)) return true;
        if (/\blessons?\b/.test(t) && /\b(see|show|view|access|my|free|w\b|sound|library|curriculum)\b/.test(t)) {
            return true;
        }
        if (/\b(w sound|lesson on|pronunciation lesson)\b/.test(t)) return true;
        return false;
    }

    openFreeLesson() {
        if (!this.flowServesLessons()) {
            this.denyLessonsOnThisFlow();
            return;
        }
        this.requestFreeLesson();
    }

    async openLessonLibrary() {
        if (!this.flowServesLessons()) {
            this.denyLessonsOnThisFlow();
            return;
        }
        // v1.8.1: Simplified access check — member state is the single source of truth.
        // Previously used triple-AND (!hasAccess && !purchased && !== 'member') which could
        // fail when any one flag was stale after purchase.
        if (this.state !== 'member') {
            this.addMessage('assistant', '🔒 Full lesson access requires membership. Would you like to see our offers?');
            return;
        }

        // Show loading message
        this.addMessage('assistant', '📚 Loading your lessons...');

        try {
            const lessons = await this.fetchAllLessons();

            if (!lessons || lessons.length === 0) {
                this.addMessage('assistant', '❌ No lessons found. Please contact support.');
                return;
            }

            this.renderLessonList(lessons);
        } catch (error) {
            this.logError('[FLOSC] Failed to load lessons:', error);
            this.addMessage('assistant', '❌ Could not load lessons. Please try again.');
        }
    }

    async fetchAllLessons() {
        const params = new URLSearchParams();
        if (this.config.flowId) params.append('flow_id', this.config.flowId);
        const qs = params.toString() ? `?${params.toString()}` : '';
        const response = await this.authFetch(`${this.config.apiUrl}/lessons${qs}`, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': this.config.nonce }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        return data.lessons || [];
    }

    async fetchLesson(lessonId) {
        const params = new URLSearchParams();
        if (this.config.flowId) params.append('flow_id', this.config.flowId);
        const qs = params.toString() ? `?${params.toString()}` : '';
        const response = await this.authFetch(`${this.config.apiUrl}/lessons/${lessonId}${qs}`, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': this.config.nonce }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();
        return data.lesson || null;
    }

    // v8.0.0: Sort lessons — numbered lessons (Lesson 1, 2, 3…) by number first,
    // then non-numbered titles alphabetically.
    _sortLessons(lessons) {
        return lessons.slice().sort((a, b) => {
            const numA = a.title.match(/^Lesson\s+(\d+(?:\.\d+)?)/i);
            const numB = b.title.match(/^Lesson\s+(\d+(?:\.\d+)?)/i);
            if (numA && numB) return parseFloat(numA[1]) - parseFloat(numB[1]);
            if (numA) return -1;
            if (numB) return 1;
            return a.title.localeCompare(b.title);
        });
    }

    // v3.0.8: Titles-only list — compact for long lesson libraries (65+10 = 75 items).
    // No excerpts; each row is title + arrow, clickable to open the lesson.
    // v8.0.0: options.header and options.countLabel for filtered views.
    renderLessonList(lessons, options = {}) {
        const sorted = this._sortLessons(lessons);
        const PAGE_SIZE = 10;
        const initial = sorted.slice(0, PAGE_SIZE);
        const hasMore = sorted.length > PAGE_SIZE;
        const listId = 'flosc-lesson-list-' + Date.now();
        const header = options.header || '📚 All Lessons';
        const countLabel = options.countLabel || 'available';

        const listHtml = `
            <div class="flosc-lesson-list" id="${listId}">
                <div class="flosc-lesson-list-header">
                    <h3>${this.escapeHtml(header)}</h3>
                    <p>${sorted.length} ${sorted.length === 1 ? 'lesson' : 'lessons'} ${this.escapeHtml(countLabel)}</p>
                </div>
                <div class="flosc-lesson-items flosc-lesson-items--compact">
                    ${initial.map(lesson => `
                        <div class="flosc-lesson-item flosc-lesson-item--title-only"
                             role="button" tabindex="0"
                             data-flosc-action="view-lesson"
                             data-lesson-id="${parseInt(lesson.id, 10) || 0}">
                            <span class="flosc-lesson-item-title">${this.escapeHtml(lesson.title)}</span>
                            <span class="flosc-lesson-item-arrow">›</span>
                        </div>
                    `).join('')}
                </div>
                ${hasMore ? `<button class="flosc-load-more-btn flosc-load-more-btn-inline" data-list-id="${listId}">Show more (${sorted.length - PAGE_SIZE} remaining)</button>` : ''}
            </div>
        `;
        this.addMessage('assistant', listHtml, true);

        if (hasMore) {
            this._lessonListData = this._lessonListData || {};
            this._lessonListData[listId] = { lessons: sorted, shown: PAGE_SIZE };
            setTimeout(() => {
                const btn = document.querySelector(`[data-list-id="${listId}"]`);
                if (btn) btn.addEventListener('click', () => this._loadMoreLessons(listId));
            }, 100);
        }
    }

    _loadMoreLessons(listId) {
        const data = this._lessonListData?.[listId];
        if (!data) return;
        const PAGE_SIZE = 10;
        const next = data.lessons.slice(data.shown, data.shown + PAGE_SIZE);
        if (!next.length) return;

        const container = document.querySelector(`#${listId} .flosc-lesson-items`);
        if (container) {
            next.forEach(lesson => {
                const div = document.createElement('div');
                div.className = 'flosc-lesson-item flosc-lesson-item--title-only';
                div.setAttribute('role', 'button');
                div.tabIndex = 0;
                div.setAttribute('data-flosc-action', 'view-lesson');
                div.setAttribute('data-lesson-id', String(parseInt(lesson.id, 10) || 0));
                div.innerHTML = `<span class="flosc-lesson-item-title">${this.escapeHtml(lesson.title)}</span><span class="flosc-lesson-item-arrow">›</span>`;
                container.appendChild(div);
            });
        }

        data.shown += next.length;
        const remaining = data.lessons.length - data.shown;
        const btn = document.querySelector(`[data-list-id="${listId}"]`);
        if (btn) {
            if (remaining > 0) {
                btn.textContent = `Show more (${remaining} remaining)`;
            } else {
                btn.remove();
            }
        }
    }

    async viewLesson(lessonId) {
        this.log('[FLOSC] Viewing lesson:', lessonId);

        try {
            const lesson = await this.fetchLesson(lessonId);

            if (!lesson) {
                this.addMessage('assistant', '❌ Could not load lesson. Please try again.');
                return;
            }

            // WordPress content is already formatted with the_content filter applied
            // Title is escaped; content is trusted (authored by floscAdmin, served via authenticated endpoint)
            const lessonHtml = `
                <div class="flosc-wp-lesson">
                    <div class="flosc-wp-lesson-header">
                        <h2>${this.escapeHtml(lesson.title)}</h2>
                    </div>
                    <div class="flosc-wp-lesson-content">
                        ${lesson.content}
                    </div>
                    <div class="flosc-wp-lesson-footer">
                        <button type="button" class="flosc-back-to-lessons-btn" data-flosc-action="open-lesson-library">
                            ← Back to All Lessons
                        </button>
                    </div>
                </div>
            `;

            this.addMessage('assistant', lessonHtml, true);
        } catch (error) {
            this.logError('[FLOSC] Failed to load lesson:', error);
            this.addMessage('assistant', '❌ Could not load lesson. Please try again.');
        }
    }

    openPersonalizedPath() {
        window.location.href = this.config.pathUrl || '/my-path/';
    }

    /**
     * Delegated chat UI actions (replaces inline onclick/onkeyup HTML attributes).
     * @param {Event|{target: Element, type?: string}} e
     */
    handleDelegatedFloscAction(e) {
        const t = e?.target;
        if (!t || !t.closest) return;
        const el = t.closest('[data-flosc-action]');
        if (!el || (this.chatMessages && !this.chatMessages.contains(el))) return;

        const action = el.getAttribute('data-flosc-action') || '';
        switch (action) {
            case 'view-lesson': {
                const id = parseInt(el.getAttribute('data-lesson-id') || '0', 10);
                if (id > 0) {
                    if (window.FLOSC && typeof window.FLOSC.viewLesson === 'function') {
                        window.FLOSC.viewLesson(id);
                    } else {
                        this.viewLesson(id);
                    }
                }
                break;
            }
            case 'open-lesson-library':
                if (window.FLOSC && typeof window.FLOSC.openLessonLibrary === 'function') {
                    window.FLOSC.openLessonLibrary();
                } else if (typeof this.openLessonLibrary === 'function') {
                    this.openLessonLibrary();
                }
                break;
            case 'start-quiz': {
                const quizId = el.getAttribute('data-quiz-id') || 'default';
                if (typeof this.startInChatQuiz === 'function') {
                    this.startInChatQuiz(quizId);
                }
                break;
            }
            case 'sandbox-preset': {
                const amount = el.getAttribute('data-amount') || '';
                const input = document.getElementById('flosc-sandbox-amount');
                if (input) input.value = amount;
                break;
            }
            case 'sandbox-pay': {
                const offerId = el.getAttribute('data-offer-id') || 'sandbox';
                const productId = el.getAttribute('data-product-id') || '';
                if (typeof this.processSandboxPayment === 'function') {
                    this.processSandboxPayment(offerId, productId);
                }
                break;
            }
            case 'free-lesson': {
                const idx = parseInt(el.getAttribute('data-lesson-index') || '-1', 10);
                if (idx >= 0 && typeof this.showFreeLessonContent === 'function') {
                    this.showFreeLessonContent(idx);
                }
                break;
            }
            default:
                break;
        }
    }

    // v1.8.1: Show quiz options in-chat instead of navigating away to /quizzes/
    openQuizLibrary() {
        const quizHtml = `
            <div class="flosc-quiz-library">
                <p><strong>Available Quizzes</strong></p>
                <p>Choose a quiz to test your skills:</p>
                <div class="flosc-quiz-library-actions">
                    <button type="button" class="flosc-quiz-result-cta" data-flosc-action="start-quiz" data-quiz-id="default">
                        🎯 Take the Quiz
                    </button>
                </div>
            </div>
        `;
        this.addMessage('assistant', quizHtml, true);
    }

    resumeLastLesson() {
        // v1.7.8: Resume by opening the lesson library — last-lesson tracking not yet implemented
        this.addMessage('assistant', '📚 Let me pull up your lessons...');
        this.openLessonLibrary();
    }

    // v3.0.8: Show stored quiz results for current member or guest.
    // Now handles IPA audio quiz data from this.user.lastQuizData.
    _getPendingLocalQuizResult() {
        try {
            const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
            if (!stored) return null;
            const result = JSON.parse(stored);
            const age = Date.now() - (result.timestamp || 0);
            if (age >= 86400000 || !result.phraseResults?.length) return null;
            return result;
        } catch (e) {
            return null;
        }
    }

    openQuizResults() {
        if (!this.shouldSurfaceQuizResults(this.user?.lastQuizData)) {
            this.log('[FLOSC] openQuizResults skipped — quiz not configured on this flow');
            return;
        }
        // Check for IPA audio quiz data (from server scoring via user meta)
        const serverData = this.user?.lastQuizData;
        if (this._hasIpaPhraseResults(serverData)) {
            // Render full IPA phrase results using the existing renderer
            this.showIpaPhraseResultsAfterLogin({
                score: serverData.score,
                phraseResults: serverData.phrase_results,
                wordIpa: serverData.word_ipa || {},
                rankedPhonemes: serverData.ranked_phonemes || [],
                quizType: 'ipa_audio'
            });
            this.ivr.context.quiz_results_shown = true;
            setTimeout(() => this.floscShowUserAutoPrompts(), 500);
            return;
        }

        const localResult = this._getPendingLocalQuizResult();
        if (localResult) {
            this.showIpaPhraseResultsAfterLogin({
                score: localResult.score,
                phraseResults: localResult.phraseResults,
                wordIpa: localResult.wordIpa || {},
                rankedPhonemes: localResult.rankedPhonemes || [],
                quizType: 'ipa_audio'
            });
            this.ivr.context.quiz_results_shown = true;
            setTimeout(() => this.floscShowUserAutoPrompts(), 500);
            return;
        }

        // v5.0.3: Read from in-session this.quiz first (has actual item names),
        // fall back to this.user (server-populated from user_meta).
        // Coerce empty string to null — WordPress returns '' for unset meta
        const rawScore = this.quiz?.score ?? this.user?.lastQuizScore ?? null;
        const score = (rawScore !== null && rawScore !== '') ? parseInt(rawScore, 10) : null;
        if (score === null || isNaN(score)) {
            // Check if scoring is still pending
            const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
            if (stored) {
                try {
                    const pending = JSON.parse(stored);
                    if (pending.pendingServerScore) {
                        this.addMessage('assistant', 'Your pronunciation results are still being processed. Please try refreshing the page — if your results are ready, they will appear here.');
                        setTimeout(() => this.floscShowUserAutoPrompts(), 500);
                        return;
                    }
                } catch (e) {}
            }
            this.addMessage('assistant', "I don't see a quiz result on file yet. Take the assessment and I'll show you your results right here.");
            return;
        }

        const missed  = this.quiz?.missedItems  || [];
        const correct = this.quiz?.correctItems || [];
        const total   = missed.length + correct.length;

        let html = `<div class="flosc-quiz-result-detail">`;
        html += `<div class="flosc-quiz-score-summary">📊 You scored <strong>${score}%</strong>`;
        if (total > 0) html += ` (${correct.length}/${total} correct)`;
        html += `</div>`;

        if (missed.length > 0) {
            html += `<div class="flosc-quiz-missed">`;
            html += `<strong>Sounds you missed:</strong><br>`;
            html += missed.map(s => `<span class="flosc-missed-sound">❌ ${this.escapeHtml(s)}</span>`).join(' ');
            html += `</div>`;
        }

        if (correct.length > 0) {
            html += `<div class="flosc-quiz-correct">`;
            html += `<strong>Sounds you got right:</strong><br>`;
            html += correct.map(s => `<span class="flosc-correct-sound">✅ ${this.escapeHtml(s)}</span>`).join(' ');
            html += `</div>`;
        }

        if (missed.length > 0) {
            html += `<div class="flosc-quiz-cta">`;
            html += `Your free lesson covers one of these sounds. `;
            if (this.state === 'guest') {
                html += `<strong>Unlock all 10 lessons</strong> to master every sound on the list.`;
            }
            html += `</div>`;
        }

        html += `</div>`;
        this.addMessage('assistant', html, true);

        // Congratulations message for guests with free lessons
        const freeCount = parseInt(this.user?.freeLessonsCount) || 0;
        if (freeCount > 0 && this.state === 'guest') {
            const lessonWord = freeCount === 1 ? 'lesson' : 'lessons';
            setTimeout(() => {
                this.addMessage('assistant', `🎉 Congratulations! You have been granted access to <strong>${freeCount}</strong> free ${lessonWord} — you can try them out right here in this chat!`, true);
                setTimeout(() => this.floscShowUserAutoPrompts(), 500);
            }, 300);
        } else {
            setTimeout(() => this.floscShowUserAutoPrompts(), 500);
        }
    }

    // v3.0.8: Open a filtered lesson list by topic keyword.
    // Called directly (action) or via the natural-language pattern matcher in handleUserInput().
    async openFilteredLessons(topic) {
        if (this.state !== 'member') {
            this.addMessage('assistant', '🔒 Lesson access requires membership.');
            return;
        }
        const displayTopic = topic.replace(/\bplease\b/gi, '').trim();
        this.addMessage('assistant', `🔍 Finding lessons for: <em>${this.escapeHtml(displayTopic)}</em>…`, true);
        try {
            const params = new URLSearchParams({ search: displayTopic });
            if (this.config.flowId) params.append('flow_id', this.config.flowId);
            const response = await this.authFetch(
                `${this.config.apiUrl}/lessons?${params.toString()}`,
                { credentials: 'same-origin', headers: { 'X-WP-Nonce': this.config.nonce } }
            );
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data    = await response.json();
            const lessons = data.lessons || [];
            if (!lessons.length) {
                this.addMessage('assistant', `No lessons found for "<strong>${this.escapeHtml(displayTopic)}</strong>". Try a different keyword, or say <em>show me all lessons</em>.`, true);
                return;
            }
            this.renderLessonList(lessons, {
                header: `📚 Lessons: ${displayTopic}`,
                countLabel: 'found'
            });
        } catch (e) {
            this.logError('[FLOSC] openFilteredLessons failed:', e);
            this.addMessage('assistant', '❌ Could not search lessons. Please try again.');
        }
    }

    // v8.0.0: Open a single lesson by its number ("show me lesson 2").
    // Fetches all lessons, finds the one whose title starts with "Lesson N:", and opens it directly.
    async openLessonByNumber(num) {
        if (this.state !== 'member') {
            this.addMessage('assistant', '🔒 Lesson access requires membership.');
            return;
        }
        this.addMessage('assistant', `📚 Opening Lesson ${this.escapeHtml(num)}...`);
        try {
            const lessons = await this.fetchAllLessons();
            const pattern = new RegExp('^Lesson\\s+' + num.replace('.', '\\.') + '[.:\\s]', 'i');
            const match = lessons.find(l => pattern.test(l.title));
            if (match) {
                this.viewLesson(match.id);
            } else {
                this.addMessage('assistant', `Lesson ${this.escapeHtml(num)} not found. Say <em>show me all lessons</em> to browse.`, true);
            }
        } catch (e) {
            this.logError('[FLOSC] openLessonByNumber failed:', e);
            this.addMessage('assistant', '❌ Could not load lesson. Please try again.');
        }
    }

    // v3.0.8: Open the lesson posts from the quiz-linked category (e.g. FLOSC Sample Data 10 posts).
    // Different from openLessonLibrary() (all 75) — this shows only the quiz-mapped lessons.
    // Triggered by: "show me the lessons covered in the quiz", "lessons from the quiz", "quiz lesson list"
    async openQuizLessons() {
        if (this.state !== 'member') {
            this.addMessage('assistant', '🔒 Lesson access requires membership.');
            return;
        }
        this.addMessage('assistant', '📋 Loading the lessons covered in the quiz...');
        try {
            const params = new URLSearchParams({ quiz_only: '1' });
            if (this.config.flowId) params.append('flow_id', this.config.flowId);
            const response = await this.authFetch(
                `${this.config.apiUrl}/lessons?${params.toString()}`,
                { credentials: 'same-origin', headers: { 'X-WP-Nonce': this.config.nonce } }
            );
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            const lessons = data.lessons || [];
            if (!lessons.length) {
                this.addMessage('assistant', '📚 No quiz-linked lessons found. Ask your instructor for help.');
                return;
            }
            this.renderLessonList(lessons);
        } catch (e) {
            this.logError('[FLOSC] openQuizLessons failed:', e);
            this.addMessage('assistant', '❌ Could not load quiz lessons. Please try again.');
        }
    }

    // v3.0.8: Show the 10 sound topics covered by the quiz
    // Triggered by: "quiz lessons", "topics from the quiz", "quiz answer sheet"
    openQuizTopics() {
        // Topic sheet when this flow enables a pronunciation assessment quiz (by quiz id).
        const enabledQuizzes = this.config.enabledQuizzes || this.config.enabled_quizzes || [];
        const quizIds = Array.isArray(enabledQuizzes) ? enabledQuizzes.map(String) : [];
        const isPronunciationQuiz = quizIds.some((id) =>
            id.includes('pronunciation') || id.includes('ipa') || id.includes('assessment')
        );

        if (isPronunciationQuiz) {
            const quizProduct = String(this.config?.productName || this.config?.identity?.name || 'Pronunciation').trim();
            const html = `
                <div class="flosc-quiz-topics">
                    <h3>🎤 ${this.escapeHtml(quizProduct)} Quiz — 10 Sound Topics</h3>
                    <ol class="flosc-quiz-topic-list">
                        <li>The <strong>/æ/</strong> short-a vowel — <em>cat, map, back</em></li>
                        <li>The <strong>American rhotic R</strong> — <em>car, bird, butter</em></li>
                        <li>Voiceless <strong>TH /θ/</strong> — <em>think, three, bath</em></li>
                        <li>Voiced <strong>TH /ð/</strong> — <em>this, that, the</em></li>
                        <li><strong>/ɪ/ vs /iː/</strong> — <em>ship vs sheep</em></li>
                        <li>The <strong>schwa /ə/</strong> and unstressed vowels — <em>banana</em></li>
                        <li>The <strong>flap T</strong> — <em>butter = "budder"</em></li>
                        <li><strong>Word stress</strong> patterns — <em>DES-ert vs de-SERT</em></li>
                        <li><strong>Connected speech</strong> / linking — <em>turn-it-off</em></li>
                        <li>Dark <strong>L</strong> vs light L — <em>full, ball, feel</em></li>
                    </ol>
                </div>`;
            this.addMessage('assistant', html, true);
        } else {
            this.addMessage('assistant', 'The quiz covered 10 topics. Ask me about any specific one to learn more!');
        }
    }

    openSupport() {
        this.addMessage('assistant', "I'm here to help! What do you need assistance with?");
    }

    // ============================================
    // FLOSC SANDBOX PAYMENT v1.4.0
    // Product-Aware "Pay What You Want" for testing
    // ============================================
    
    showSandboxPayment(offerId = 'sandbox', productId = '') {
        // v1.4.0: Get product info for display
        const productInfo = this.getProductInfo(productId);
        const productName = productInfo.name || 'FLOSC Sandbox Access';
        const productIcon = productInfo.icon || '🎮';
        const memberLevel = productInfo.memberLevel || 'flosc_sandbox';
        
        const sandboxHtml = `
            <div class="flosc-sandbox-payment" data-offer-id="${offerId}" data-product-id="${productId}">
                <h3>${productIcon} Sandbox Payment</h3>
                <p>Test the full purchase flow for <strong>${productName}</strong>!</p>
                <p class="flosc-sandbox-text">Enter any amount you want - it's fake money for testing.</p>
                <div class="flosc-sandbox-amount">
                    <span>$</span>
                    <input type="text" id="flosc-sandbox-amount" value="1,000,000,000"
                           data-flosc-action="sandbox-amount-filter" inputmode="decimal" autocomplete="off">
                </div>
                <div class="flosc-sandbox-presets">
                    <button type="button" data-flosc-action="sandbox-preset" data-amount="9.99">$9.99</button>
                    <button type="button" data-flosc-action="sandbox-preset" data-amount="99">$99</button>
                    <button type="button" data-flosc-action="sandbox-preset" data-amount="999">$999</button>
                    <button type="button" data-flosc-action="sandbox-preset" data-amount="1,000,000">$1M</button>
                    <button type="button" data-flosc-action="sandbox-preset" data-amount="1,000,000,000">$1B 🚀</button>
                </div>
                <button type="button" class="flosc-sandbox-pay-btn"
                        data-flosc-action="sandbox-pay"
                        data-offer-id="${this.escapeHtml(String(offerId || ''))}"
                        data-product-id="${this.escapeHtml(String(productId || ''))}">
                    🎉 Complete Fake Purchase
                </button>
                <p class="flosc-sandbox-subtext">
                    This grants <strong>${memberLevel}</strong> membership level
                </p>
            </div>
        `;
        
        this.addMessage('assistant', sandboxHtml, true);
    }
    
    // v1.4.0: Get product info for sandbox display
    // v3.0.5: Reads from config identity/offers instead of hardcoded map
    getProductInfo(productId) {
        // Read from actual config data
        const identity = this.config.identity || {};
        const offers = this.config.offers || [];
        const activeOffer = offers.find(o => (o.status === 'active' || o.active) && o.id !== 'free_trial');
        
        const name = identity.name || 'Full Access';
        const level = activeOffer?.grants_level || activeOffer?.grants?.level || 'member';
        const icon = activeOffer?.meta?.icon || identity.emoji || '🎓';

        return {
            id: productId || activeOffer?.id || '',
            name: `${name} Full Access`,
            icon: icon,
            memberLevel: level,
        };
    }
    
    async processSandboxPayment(offerId, productId = '') {
        const amountInput = document.getElementById('flosc-sandbox-amount');
        const amount = amountInput ? amountInput.value : '1,000,000,000';
        const formattedAmount = '$' + amount;
        
        // Show processing
        this.addMessage('assistant', '⏳ Processing your sandbox payment...');
        
        try {
            // v1.4.0: Call server with product_id for product-specific purchase
            // v3.0.0: Use authFetch() for cross-domain auth support
            const response = await this.authFetch(`${this.config.apiUrl}/sandbox-purchase`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    product_id: productId,
                    flow_id: this.config.flowId || '',
                    amount: amount,
                    sandbox: true
                })
            });
            
            let result = await response.json();
            
            // v1.7.4: If nonce/cookie/permission error, refresh nonce and retry once
            if (!result.success && ((result.message || '').match(/cookie|not allowed/i) || result.code === 'rest_cookie_invalid_nonce')) {
                this.log('[FLOSC] Cookie check failed, refreshing nonce and retrying...');
                const refreshed = await this.refreshNonce();
                if (refreshed) {
                    const retryResponse = await this.authFetch(`${this.config.apiUrl}/sandbox-purchase`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            offer_id: offerId,
                            product_id: productId,
                            flow_id: this.config.flowId || '',
                            amount: amount,
                            sandbox: true
                        })
                    });
                    result = await retryResponse.json();
                }
            }
            
            if (result.success) {
                // v1.4.0: Show product-aware success message
                // v1.7.7: Escape server-provided values to prevent XSS
                const productIcon = this.escapeHtml(result.product_icon || '🎉');
                const productName = this.escapeHtml(result.product_name || 'FLOSC Access');
                const memberLevel = this.escapeHtml(result.member_level || 'flosc_sandbox');
                
                const successHtml = `
                    <div class="flosc-sandbox-success">
                        <h3>${productIcon} Congratulations!</h3>
                        <p>Your sandbox purchase was successful!</p>
                        <div class="amount">${formattedAmount}</div>
                        <p class="flosc-sandbox-membership-note">You now have <strong>${memberLevel}</strong> membership!</p>
                        <p class="flosc-success-detail">
                            Full access to: <strong>${productName}</strong>
                        </p>
                        <p class="flosc-celebration">
                            ${this.getSandboxCelebrationMessage(amount)}
                        </p>
                    </div>
                `;
                this.addMessage('assistant', successHtml, true);
                
                // Update user state
                this.state = 'member';
                this.user.memberLevel = memberLevel;
                this.user.purchased = true;
                this.user.purchasedProduct = result.product_id;
                
                // Refresh page after delay to show new state
                setTimeout(() => {
                    this.addMessage('assistant', '🔄 Refreshing to load your new access...');
                    setTimeout(() => location.reload(), 1500);
                }, 3000);
                
            } else {
                this.addMessage('assistant', `❌ Sandbox payment failed: ${result.message || 'Unknown error'}`);
            }
            
        } catch (error) {
            this.logError('[FLOSC Sandbox] Payment error:', error);
            this.addMessage('assistant', '❌ Something went wrong with the sandbox payment. Check console for details.');
        }
    }
    
    getSandboxCelebrationMessage(amount) {
        const numAmount = parseInt(amount.replace(/,/g, ''));
        
        if (numAmount >= 1000000000) {
            return "🚀 A BILLION DOLLARS?! You're officially a FLOSC whale! 🐋";
        } else if (numAmount >= 1000000) {
            return "💰 A millionaire purchase! Your generosity knows no bounds! 💎";
        } else if (numAmount >= 10000) {
            return "🌟 Big spender energy! You clearly believe in quality! ✨";
        } else if (numAmount >= 100) {
            return "👏 A solid investment in your learning journey!";
        } else {
            return "🙌 Every journey starts with a single step!";
        }
    }

    // Checkout: native PayPal/Stripe, free, or external cart (Woo/Shopify/etc.)
    openCheckout(offerId) {
        const offer = this.getOfferData(offerId);
        this.log('[FLOSC-CHECKOUT] Opening checkout for offer:', offerId, offer);

        this.trackEvent('checkout_initiated', { offer_id: offerId });

        if (!offer) {
            this.addMessage('assistant', 'That offer could not be loaded. Confirm it is Active under Offers for this flow.');
            return;
        }

        const pricing = offer.pricing || {};
        // Registry may store processor on pricing.processor or top-level processor
        const processor = String(pricing.processor || offer.processor || '').toLowerCase();
        const priceNum = Number(pricing.price ?? offer.price ?? 0);

        // Free offer — grant access without payment
        if (processor === 'free' || (priceNum === 0 && processor !== 'paypal' && processor !== 'stripe' && processor !== 'redirect')) {
            this.addMessage('assistant', 'Granting free access — no payment needed.');
            this.authFetch(this.config.apiUrl + '/purchase', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ offer_id: offerId, flow_id: this.config.flowId || '', method: 'free' }),
            }).then(r => r.json()).then(d => {
                if (d.success) this.handlePaymentSuccess(offerId);
                else this.addMessage('assistant', d.message || 'Could not grant access.');
            }).catch(() => this.addMessage('assistant', 'Error granting free access.'));
            return;
        }

        // External cart: WooCommerce, Shopify, ThriveCart, member platforms, etc.
        if (processor === 'redirect' || pricing.redirect_url || offer.checkout_url) {
            const redirectUrl = String(pricing.redirect_url || offer.checkout_url || '').trim();
            if (!redirectUrl) {
                this._showCheckoutUnavailable(offerId, 'redirect');
                return;
            }
            this.addMessage('assistant', 'Taking you to secure checkout. You will return here after payment when your host has set that up.');
            localStorage.setItem(this.flowStorageKey('flosc_pending_purchase'), JSON.stringify({
                offer_id: offerId,
                timestamp: Date.now(),
                return_url: window.location.href
            }));
            setTimeout(() => { window.location.href = redirectUrl; }, 800);
            return;
        }

        // Paid product surface: pay (PayPal/Stripe) + Access Code for this offer.
        this.showPaymentModal(offerId);
    }
    
    // Check for returning from external checkout
    checkPendingPurchase() {
        try {
            const pending = localStorage.getItem(this.flowStorageKey('flosc_pending_purchase'));
            if (pending) {
                const data = JSON.parse(pending);
                // Only process if recent (within 1 hour)
                if (Date.now() - data.timestamp < 3600000) {
                    this.log('[FLOSC-CHECKOUT] Checking pending purchase:', data.offer_id);
                    // The server should have updated user state via webhook
                    // Just clear the pending state
                }
                localStorage.removeItem(this.flowStorageKey('flosc_pending_purchase'));
            }
        } catch (e) {
            this.logWarn('[FLOSC-CHECKOUT] Error checking pending purchase', e);
        }
    }

    // v9.1.1: Memory leak prevention - clear timer properly
    clearInactivityTimer() {
        if (this.ivr.inactivityTimer) {
            clearInterval(this.ivr.inactivityTimer);
            this.ivr.inactivityTimer = null;
        }
    }

    startInactivityTimer() {
        // v9.1.1: Clear existing timer first to prevent leaks
        this.clearInactivityTimer();

        this.ivr.inactivityTimer = setInterval(() => {
            // v1.6.2: Clear command before inactivity check — 
            // command conditions should only fire on the user message that set them
            this.ivr.context.command = '';
            this.updateIVRContext();
            this.checkAutoMessages();
        }, 30000);
    }

    onUserMessage(message) {
        this.ivr.messageCount++;
        this.ivr.lastInteraction = Date.now();
        this.ivr.context.first_show_session = false;
        this.ivr.context.first_message_after_quiz = false;
        this.ivr.context.first_message_after_login = false;
        this.ivr.context.first_message_after_purchase = false;
        
        // v1.6.2: Set command context so command == "..." conditions can match
        this.ivr.context.command = message;

        const lowerMessage = message.toLowerCase().trim();

        if (lowerMessage === 'show intropanel' || lowerMessage === 'show promptpanel' || lowerMessage === 'show memberpromptpanel' || lowerMessage === 'show suggested' || lowerMessage === 'show infopanel') {
            // v1.9.1: Clear dismissal flag so panel can render again
            this._panelDismissed = false;
            this.floscShowUserAutoPrompts();
            return true;
        }
        if (lowerMessage === 'hide intropanel' || lowerMessage === 'hide promptpanel' || lowerMessage === 'hide memberpromptpanel' || lowerMessage === 'hide suggested') {
            const container = document.getElementById('flosc_input_user_autoprompts_panel');
            if (container) container.remove();
            this._panelDismissed = true;
            this.addMessage('assistant', 'Panel hidden. Type "show suggested" to see it again.');
            return true;
        }

        if (lowerMessage === 'ivr status') {
            this.showIVRStatus();
            return true;
        }

        // v8.0.0: Access code chat flow
        if (lowerMessage === 'access code') {
            this.addMessage('assistant', 'Hey, Fam! Enter your access code:');
            this._awaitingAccessCode = true;
            return true;
        }
        if (this._awaitingAccessCode) {
            this._awaitingAccessCode = false;
            this._redeemAccessCode(message.trim());
            return true;
        }

        // v8.0.0: "show me lesson 2", "open lesson 5", "lesson 3", "go to lesson 12"
        if (this.state === 'member') {
            const lessonNumberMatch = message.match(
                /(?:show\s+(?:me\s+)?(?:the\s+)?|open\s+(?:the\s+)?|go\s+to\s+(?:the\s+)?)?lesson\s+(\d+(?:\.\d+)?)\b/i
            );
            if (lessonNumberMatch) {
                this.openLessonByNumber(lessonNumberMatch[1]);
                return true;
            }
        }

        // v8.0.0: "show me all lessons", "list all lessons", "all lessons"
        if (this.state === 'member') {
            if (/(?:show\s+(?:me\s+)?(?:the\s+)?|open\s+(?:the\s+)?|list\s+(?:the\s+)?|find\s+)?all\s+(?:the\s+)?lessons?/i.test(message)) {
                this.openLessonLibrary();
                return true;
            }
        }

        // v3.0.8: Lesson search — intercept "show me lessons for X", "lessons about X", etc.
        // Handles natural phrasing like "show me the lessons for vowel sounds please"
        // Only fires for members (others see the normal AI response / upsell).
        if (this.state === 'member') {
            const lessonSearchMatch = message.match(
                /(?:show\s+(?:me\s+)?(?:the\s+)?|open\s+|find\s+|list\s+|search\s+(?:for\s+)?)?lessons?\s+(?:for|about|on|covering?|related\s+to|with|containing)\s+(.+)/i
            ) || message.match(
                /(?:show\s+me|find|search\s+for|open|list)\s+(.+?)\s+lessons?/i
            );
            if (lessonSearchMatch) {
                const topic = lessonSearchMatch[1]
                    .replace(/\s*please\.?\s*$/i, '')
                    .replace(/\s+/g, ' ')
                    .trim();
                if (topic.length >= 2) {
                    this.openFilteredLessons(topic);
                    return true;
                }
            }
        }

        // v8.0.0: Quiz request interceptor — catch quiz-related phrases BEFORE they reach the AI.
        // This prevents the AI from ever having the opportunity to hallucinate quiz content.
        // The quiz is a separate system; the AI is an IVR humanizer, not a content creator.
        if (/(?:take|start|begin|do|try|launch|open)\s+(?:the\s+|a\s+)?(?:pronunciation\s+)?quiz/i.test(lowerMessage)
            || /(?:pronunciation\s+)?quiz\s*(?:please|now|time)?\s*[!?.]*$/i.test(lowerMessage)
            || /^quiz[!?.]*$/i.test(lowerMessage)
            || /(?:ready\s+for|let'?s\s+(?:do|take|try)|i\s+want\s+(?:to\s+)?(?:take|do|try))\s+(?:the\s+|a\s+)?(?:pronunciation\s+)?quiz/i.test(lowerMessage)
            || /(?:can\s+i|could\s+i|may\s+i)\s+(?:take|do|try)\s+(?:the\s+|a\s+)?quiz/i.test(lowerMessage)
        ) {
            if (!this.flowHasQuizConfigured()) {
                this.denyQuizOnThisFlow(message);
                return true;
            }
            // Only configured action strings — never invent a pronunciation quiz ID.
            const quizAction = this.config.ivrQuizAction || this.config.defaultQuizAction || 'open_quiz';
            this.performIVRAction(quizAction);
            return true;
        }

        setTimeout(() => this.floscShowUserAutoPrompts(), 500);
        
        // v1.6.2: Check auto/offer messages after each user message
        // This enables command-triggered offers (e.g., command == "1863723763746")
        setTimeout(() => {
            this.checkAutoMessages();
            // v1.6.2: Clear command after it's been consumed — prevents re-firing on inactivity ticks
            this.ivr.context.command = '';
        }, 600);

        return false;
    }

    showIVRStatus() {
        this.updateIVRContext();
        const ctx = this.ivr.context;
        const status = `
**IVR Status (v07.08)**
Phase: ${this.ivr.phase}
Messages: ${this.ivr.messageCount}
Session: ${ctx.session_minutes}m ${ctx.session_seconds % 60}s
Logged in: ${ctx.logged_in}
Quiz taken: ${ctx.quiz_taken}
Score: ${ctx.score}%
Lesson viewed: ${ctx.lesson_viewed}
Purchased: ${ctx.purchased}
        `.trim();
        this.addMessage('assistant', status.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>'));
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * v1.7.7: Debug-gated logging — only outputs when config.debug is true
     * Replaces raw console.log throughout the codebase to prevent info disclosure
     */
    log(...args) {
        if (this._debug) {
            this.logWarn(...args);
        }
    }

    logWarn(...args) {
        if (this._debug) console.warn('[FLOSC]', ...args);
    }

    logError(...args) {
        // Errors always log — these indicate real problems
        console.error('[FLOSC]', ...args);
    }

    /**
     * v1.7.1: Convert basic markdown to HTML for assistant messages
     * Supports: **bold**, *italic*, `code`, [links](url), line breaks
     */
    formatMarkdown(text) {
        let html = this.escapeHtml(text);
        // Bold: **text**
        html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        // Italic: *text*
        html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
        // Inline code: `code`
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
        // Links: [text](url)
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
        // Bare URLs: linkify any http(s) URL that is NOT already inside a tag.
        // The lead group (^|[\s(]) only matches URLs preceded by start-of-string,
        // whitespace, or "(", so URLs already emitted as href="..." (preceded by
        // ") or anchor text (preceded by ">") from the markdown-link pass above
        // are left untouched. Trailing sentence punctuation is kept outside the link.
        html = html.replace(/(^|[\s(])(https?:\/\/[^\s<]+)/g, function (match, lead, url) {
            var trail = '';
            var punct = url.match(/[.,;:!?)\]]+$/);
            if (punct) { trail = punct[0]; url = url.slice(0, -punct[0].length); }
            return lead + '<a href="' + url + '" target="_blank" rel="noopener">' + url + '</a>' + trail;
        });
        // Line breaks
        html = html.replace(/\n/g, '<br>');
        return html;
    }

    isCompanionSurface() {
        if (document.body.classList.contains('flosc-companion-embed')) {
            return true;
        }
        const surface = String(this.browsingContext?.surface || '').toLowerCase();
        if (surface === 'companion') {
            return true;
        }
        try {
            const params = new URLSearchParams(window.location.search || '');
            return String(params.get('flosc_surface') || '').toLowerCase() === 'companion';
        } catch (e) {
            return false;
        }
    }

    /**
     * v8.0.1: Inject provider-native players (YouTube, TikTok, Spotify,
     * SoundCloud, Apple Music, Vimeo) beneath media links in an assistant
     * message, via the FLOSC oEmbed proxy (WordPress core oEmbed). The clickable
     * link is kept; the player is added below it. Non-media links and
     * unsupported providers are left untouched.
     *
     * @param {HTMLElement} container The rendered message element.
     */
    _embedMedia(container) {
        if (!container || !this.config || !this.config.apiUrl) return;
        const providerRe = /(?:youtube\.com|youtu\.be|tiktok\.com|spotify\.com|soundcloud\.com|music\.apple\.com|vimeo\.com)/i;
        const anchors = container.querySelectorAll('.message-text a[href], .flosc-message-text a[href]');
        const self = this;

        if (!this._oembedCache) this._oembedCache = new Map();
        if (!this._oembedPending) this._oembedPending = new Map();

        const normalizeMediaUrl = function(rawUrl) {
            try {
                const parsed = new URL(String(rawUrl || ''), window.location.origin);
                if (!/^https?:$/.test(parsed.protocol)) return '';

                // Strip tracking-only params to improve cache hit consistency.
                ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'si'].forEach(function(param) {
                    parsed.searchParams.delete(param);
                });
                parsed.hash = '';
                return parsed.toString();
            } catch (e) {
                return '';
            }
        };

        const fetchOembedHtml = async function(mediaUrl) {
            const cachedHtml = self._oembedCache.get(mediaUrl);
            if (typeof cachedHtml === 'string') {
                return cachedHtml;
            }

            if (self._oembedPending.has(mediaUrl)) {
                return self._oembedPending.get(mediaUrl);
            }

            const loader = (async function() {
                const endpoint = self.config.apiUrl + '/oembed?url=' + encodeURIComponent(mediaUrl);

                // First request uses normal cache behavior; retry=1 asks server to
                // bypass short negative cache for transient provider misses.
                const attempts = [endpoint, endpoint + '&retry=1'];
                for (let i = 0; i < attempts.length; i++) {
                    try {
                        const res = await self.authFetch(attempts[i], { method: 'GET' });
                        if (!res.ok) {
                            continue;
                        }
                        const data = await res.json();
                        if (data && data.success && data.html) {
                            self._oembedCache.set(mediaUrl, data.html);
                            return data.html;
                        }
                    } catch (e) {
                        // Try next attempt.
                    }

                    if (i === 0) {
                        await new Promise(function(resolve) { setTimeout(resolve, 250); });
                    }
                }

                self._oembedCache.set(mediaUrl, '');
                return '';
            })();

            self._oembedPending.set(mediaUrl, loader);
            try {
                return await loader;
            } finally {
                self._oembedPending.delete(mediaUrl);
            }
        };

        anchors.forEach(function (a) {
            const normalizedUrl = normalizeMediaUrl(a.href) || a.href;
            if (!providerRe.test(normalizedUrl) || a.dataset.floscEmbedded) return;
            a.dataset.floscEmbedded = 'pending';

            fetchOembedHtml(normalizedUrl)
                .then(function (html) {
                    if (!html) {
                        // Keep links retryable if the message is re-rendered later.
                        delete a.dataset.floscEmbedded;
                        return;
                    }

                    if (!a.isConnected) {
                        return;
                    }

                    // Prevent duplicate wrappers when a message is re-rendered.
                    const existingWrap = a.parentElement
                        ? Array.from(a.parentElement.querySelectorAll('.flosc-oembed-wrap')).find(function(node) {
                            return String(node.dataset.oembedUrl || '') === normalizedUrl;
                        })
                        : null;
                    if (existingWrap) {
                        a.dataset.floscEmbedded = 'done';
                        return;
                    }

                    const wrap = document.createElement('div');
                    wrap.className = 'flosc-oembed-wrap';
                    wrap.dataset.oembedUrl = normalizedUrl;
                    wrap.innerHTML = html;
                    a.insertAdjacentElement('afterend', wrap);
                    a.dataset.floscEmbedded = 'done';
                    if (self.chatMessages) self.chatMessages.scrollTop = self.chatMessages.scrollHeight;
                })
                .catch(function () {
                    delete a.dataset.floscEmbedded;
                });
        });
    }

    /**
     * Real-world state machine for one host:
     *   no user id → visitor
     *   user id    → guest | member (never visitor)
     *
     * @param {object} user
     * @param {string} preferred  body / server hint
     * @returns {'visitor'|'guest'|'member'}
     */
    resolveAppUserState(user, preferred = 'visitor') {
        const uid = user && (user.id || user.ID);
        if (!uid) {
            return 'visitor';
        }
        const hint = String(preferred || user.state || 'guest').toLowerCase();
        if (hint === 'member') {
            return 'member';
        }
        return 'guest';
    }

    /**
     * Apply guest/member shell after auth is known (profile wallet, not visitor).
     * @param {object} user
     * @param {string} [stateHint]
     */
    applyAuthenticatedUserShell(user, stateHint) {
        if (!user || !(user.id || user.ID)) {
            return;
        }
        this.user = user;
        window.FLOSC_USER = user;
        this.state = this.resolveAppUserState(user, stateHint || user.state);
        if (document.body) {
            document.body.dataset.userState = this.state;
            document.body.setAttribute('data-user-state', this.state);
        }
        const tokens = parseInt(user.tokens ?? user.tokenBalance ?? user.flowTokens, 10);
        if (Number.isFinite(tokens) && tokens >= 0) {
            this.user.tokens = tokens;
            this.user.tokenBalance = tokens;
            this.user.flowTokens = user.flowTokens ?? tokens;
        }
    }

    /**
     * If PHP painted visitor but this host has a valid auth token, load the user
     * profile for THIS flow. Real-world: only needed when the first paint missed
     * the session cookie; same-host login normally already has FLOSC_USER filled.
     */
    async rehydrateSessionFromAuthToken() {
        if (this.user && (this.user.id || this.user.ID)) {
            this.applyAuthenticatedUserShell(this.user, this.state);
            return;
        }

        if (!this.authToken) {
            this.state = 'visitor';
            return;
        }

        const apiBase = (this.config.apiUrl || this.config.restUrl || '').replace(/\/$/, '');
        if (!apiBase) {
            return;
        }

        try {
            const params = new URLSearchParams();
            if (this.config.flowId) {
                params.set('flow_id', this.config.flowId);
            }
            const url = `${apiBase}/session?${params.toString()}`;
            this.log('[FLOSC] Rehydrating session from auth token…');
            const response = await this.authFetch(url, {
                method: 'GET',
                credentials: 'same-origin',
            });
            if (!response.ok) {
                this.logWarn('[FLOSC] Session rehydrate HTTP', response.status);
                return;
            }
            const data = await response.json();
            if (!data || !data.success || !data.user || !(data.user.id || data.user.ID)) {
                if (data && data.state === 'visitor') {
                    try {
                        localStorage.removeItem('flosc_auth_token');
                    } catch (e) { /* ignore */ }
                    this.authToken = '';
                }
                this.state = 'visitor';
                return;
            }

            this.applyAuthenticatedUserShell(data.user, data.state || data.user.state);

            if (data.authToken) {
                this.authToken = data.authToken;
                this.config.authToken = data.authToken;
                try {
                    localStorage.setItem('flosc_auth_token', data.authToken);
                } catch (e) { /* ignore */ }
            }

            this.log('[FLOSC] Session rehydrated:', this.state, this.user.name, 'tokens=', this.user.tokens);
        } catch (err) {
            this.logWarn('[FLOSC] Session rehydrate failed', err);
        }
    }

    /**
     * v3.0.0: Centralized authenticated fetch for all FLOSC API calls.
     *
     * Adds the FLOSC auth token header alongside the WordPress nonce.
     * When WordPress cookies work (same-domain), the nonce authenticates.
     * When cookies fail (cross-domain), the FLOSC token authenticates.
     *
     * @param {string} url - The URL to fetch
     * @param {object} options - Standard fetch options (method, body, etc.)
     * @returns {Promise<Response>} The fetch response
     */
    async authFetch(url, options = {}) {
        // Build headers — merge FLOSC auth headers with any caller-provided headers
        const headers = options.headers instanceof Headers
            ? Object.fromEntries(options.headers.entries())
            : { ...(options.headers || {}) };

        // Always include the WP nonce (for same-domain cookie auth)
        if (this.config.nonce && !headers['X-WP-Nonce']) {
            headers['X-WP-Nonce'] = this.config.nonce;
        }

        // Always include the FLOSC auth token (for cross-domain auth)
        if (this.authToken && !headers['X-FLOSC-Token']) {
            headers['X-FLOSC-Token'] = this.authToken;
        }

        return fetch(url, {
            ...options,
            credentials: 'same-origin',
            headers
        });
    }

    /**
     * v1.7.1: Refresh WP REST nonce (fixes "Cookie check failed" after long sessions)
     * v3.0.0: Uses authFetch() so nonce refresh works across domains too.
     */
    async refreshNonce() {
        try {
            const res = await this.authFetch(this.config.restUrl + 'nonce');
            if (res.ok) {
                const data = await res.json();
                if (data.nonce) {
                    this.config.nonce = data.nonce;
                    this.log('[FLOSC] Nonce refreshed');
                    return true;
                }
            }
        } catch (e) {
            this.logWarn('[FLOSC] Could not refresh nonce:', e);
        }
        return false;
    }

    // ==========================================
    // Core App Methods
    // ==========================================
    
    bindElements() {
        this.log('[FLOSC] bindElements() called');
        this.sidebar = document.getElementById('flosc_app_sidebar');
        this.sidebarToggle = document.getElementById('flosc_app_sidebar_toggle');
        this.dockCompanionBtn = document.getElementById('flosc_app_dock_companion_button');
        this.sessionList = document.getElementById('flosc_app_session_list');
        this.newSessionBtn = document.getElementById('flosc_app_new_session_button');
        this.chatMessages = document.getElementById('flosc_output_chat_responses');
        this.chatInput = document.getElementById('flosc_input_chat_field');
        this.sendBtn = document.getElementById('flosc_input_chat_send_button');
        this.quizSection = document.getElementById('flosc_quiz_section');
        this.shareBtn = document.getElementById('flosc_app_share_button');
        this.shareModal = document.getElementById('flosc_modal_share');
        
        this.log('[FLOSC] Critical elements found:', {
            chatInput: this.chatInput ? 'FOUND' : 'MISSING',
            sendBtn: this.sendBtn ? 'FOUND' : 'MISSING',
            chatMessages: this.chatMessages ? 'FOUND' : 'MISSING'
        });
    }
    
    bindEvents() {
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => {
                this.toggleSidebar();
            });
        }

        if (this.dockCompanionBtn) {
            this.dockCompanionBtn.addEventListener('click', () => {
                void this.handoffToCompanion();
            });
        }

        window.addEventListener('message', (event) => {
            try {
                if (window.self === window.top) {
                    return;
                }
                const allowedOrigins = this.getAllowedCompanionOrigins();
                if (!allowedOrigins.has(String(event.origin || ''))) {
                    return;
                }
                if (event.source !== window.parent) {
                    return;
                }
                const data = event.data;
                if (!data || typeof data !== 'object') {
                    return;
                }

                if (data.type === 'flosc_companion_request_session_handoff') {
                    this.postVisitorSessionHandoffToParent();
                    return;
                }

                if (data.type !== 'flosc_companion_context' || typeof data.payload !== 'object') {
                    return;
                }
                this.applyBrowsingContext(data.payload);
                this.buildIVRContext();
            } catch (e) {
                this.logWarn('[FLOSC] Companion context message parse failed:', e);
            }
        });
        
        // v1.7.0: Mobile menu button also toggles sidebar (works on desktop too)
        const mobileMenuBtn = document.getElementById('flosc_app_mobile_menu_button');
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => this.toggleSidebar());
        }
        
        // v1.7.0: Overlay click closes sidebar on mobile
        const sidebarOverlay = document.getElementById('flosc_app_sidebar_overlay');
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', () => {
                if (this.sidebar) this.sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            });
        }
        
        // v2.0.5: Clean up mobile sidebar state on viewport resize (e.g. iPad rotation)
        window.addEventListener('resize', () => {
            const overlay = document.getElementById('flosc_app_sidebar_overlay');
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                if (overlay) overlay.classList.remove('show');
                if (this.sidebar) {
                    this.sidebar.classList.remove('open');
                    this.sidebar.classList.remove('collapsed');
                }
            } else {
                if (overlay) overlay.classList.remove('show');
                if (this.sidebar) this.sidebar.classList.remove('open');
                this.restoreSidebarCollapsedState();
            }
        });
        
        if (this.newSessionBtn) {
            this.newSessionBtn.addEventListener('click', () => this.newSession());
        }
        
        if (this.sendBtn) {
            this.sendBtn.addEventListener('click', () => this.sendMessage());
        }
        
        if (this.chatInput) {
            this.chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }

        // E5 / Plugin Check: no inline onclick/onkeyup in rendered chat HTML.
        // Delegate from the messages root so dynamic message markup stays event-attribute free.
        if (this.chatMessages && !this.chatMessages.dataset.floscActionBound) {
            this.chatMessages.dataset.floscActionBound = '1';
            this.chatMessages.addEventListener('click', (e) => this.handleDelegatedFloscAction(e));
            this.chatMessages.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const target = e.target?.closest?.('[data-flosc-action="view-lesson"]');
                if (!target || !this.chatMessages.contains(target)) return;
                e.preventDefault();
                this.handleDelegatedFloscAction({ target, type: 'keydown' });
            });
            this.chatMessages.addEventListener('input', (e) => {
                const el = e.target;
                if (!el || el.getAttribute('data-flosc-action') !== 'sandbox-amount-filter') return;
                el.value = String(el.value || '').replace(/[^0-9,]/g, '');
            });
        }
        
        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', () => this.openShareModal());
        }

        // Share modal close handlers
        const shareModalClose = document.getElementById('shareModalClose');
        if (shareModalClose) {
            shareModalClose.addEventListener('click', () => {
                this.setDisplayState(this.shareModal, false, 'flex');
            });
        }
        if (this.shareModal) {
            this.shareModal.addEventListener('click', (e) => {
                if (e.target === this.shareModal) this.setDisplayState(this.shareModal, false, 'flex');
            });
        }
        // Copy button
        const copyBtn = document.getElementById('copyBtn');
        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const shareLink = document.getElementById('shareLink');
                if (shareLink?.value) {
                    navigator.clipboard.writeText(shareLink.value).then(() => {
                        const txt = document.getElementById('copyBtnText');
                        if (txt) { txt.textContent = 'Copied!'; setTimeout(() => txt.textContent = 'Copy', 2000); }
                    });
                }
            });
        }

        const restartBtn = document.getElementById('flosc_app_restart_chat');
        if (restartBtn) {
            restartBtn.addEventListener('click', () => this.restartChat());
        }

        // Profile button dropdown toggle (single unified bar)
        const profileBtn = document.getElementById('flosc_profile_button');
        const profileDropdown = document.getElementById('flosc_profile_dropdown');
        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('open');
                profileBtn.setAttribute('aria-expanded', profileDropdown.classList.contains('open'));
            });
        }

        // Handle visitor menu item clicks (data-action items inside the unified dropdown)
        if (profileDropdown) {
            profileDropdown.querySelectorAll('[data-action]').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const action = e.currentTarget.dataset.action;
                    this.handleVisitorMenuAction(action);
                    profileDropdown.classList.remove('open');
                });
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (profileDropdown && !e.target.closest('#flosc_user_profile_bar')) {
                profileDropdown.classList.remove('open');
                if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
            }
        });

        const recordingModalClose = document.getElementById('floscQuizModalClose');
        if (recordingModalClose) {
            recordingModalClose.addEventListener('click', () => this.hideRecordingModal());
        }
        
        document.addEventListener('click', (e) => {
            const card = e.target.closest('.flosc-prompt-card');
            if (card) {
                this.handlePromptCard(card);
            }
        });

        document.querySelectorAll('[data-flosc-action="open-quiz"]').forEach(btn => {
            btn.addEventListener('click', () => this.showRecordingModal());
        });

        // v3.0.4: Upgrade button → show offer (floscAdmin-configured)
        // v8.0.0: Use dynamic lookup — the offer ID comes from admin config,
        // Offer IDs come from flow config, not hardcode.
        const upgradeBtn = document.getElementById('flosc_upgrade_button');
        if (upgradeBtn) {
            const upgradeOfferId = this.getOfferIdForProduct();
            upgradeBtn.addEventListener('click', () => this.showOffer(upgradeOfferId, { source: 'user' }));
        }
        
        // v9.3.3: Quiz modal event bindings
        this.bindQuizEvents();
    }
    
    // v9.3.3: Bind quiz modal events
    bindQuizEvents() {
        // Tab switching
        document.querySelectorAll('.quiz-tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tabType = e.target.dataset.tab;
                this.switchQuizTab(tabType);
            });
        });
        
        // Text quiz submission
        const submitTextBtn = document.getElementById('floscQuizSubmitTextButton');
        if (submitTextBtn) {
            submitTextBtn.addEventListener('click', () => this.submitTextQuiz());
        }
        
        // Text input enter key
        const textInput = document.getElementById('floscQuizTextInput');
        if (textInput) {
            textInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.submitTextQuiz();
                }
            });
        }
        
        // Audio recording controls
        const recordBtn = document.getElementById('floscQuizRecordButton');
        const stopBtn = document.getElementById('floscQuizStopButton');
        const submitRecordingBtn = document.getElementById('floscQuizSubmitRecordingButton');
        
        if (recordBtn) {
            recordBtn.addEventListener('click', () => this.startQuizRecording());
        }
        if (stopBtn) {
            stopBtn.addEventListener('click', () => this.stopQuizRecording());
        }
        if (submitRecordingBtn) {
            submitRecordingBtn.addEventListener('click', () => this.submitQuizRecording());
        }
        
        // Continue button after quiz
        const continueBtn = document.getElementById('floscQuizContinueButton');
        if (continueBtn) {
            continueBtn.addEventListener('click', () => this.onQuizComplete());
        }
    }
    
    // v9.3.3: Switch between text and audio tabs
    switchQuizTab(tabType) {
        // Update tab buttons
        document.querySelectorAll('.quiz-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabType);
        });
        
        // Update panels
        const textPanel = document.getElementById('floscQuizTextPanel');
        const audioPanel = document.getElementById('floscQuizAudioPanel');
        
        this.setDisplayState(textPanel, tabType === 'text', 'block');
        this.setDisplayState(audioPanel, tabType === 'audio', 'block');
    }
    
    // v9.3.3: Submit text quiz answer
    submitTextQuiz() {
        const input = document.getElementById('floscQuizTextInput');
        if (!input) return;
        
        const userAnswer = input.value.trim();
        if (!userAnswer) {
            this.addMessage('assistant', 'Please enter your answer first.');
            return;
        }
        
        // Get expected sequence from modal data attribute
        const modal = document.getElementById('flosc_modal_recording');
        const expected = modal?.dataset.quizContent || '1,2,3,4,5,6,7,8,9,10';
        
        // Score the answer
        const result = this.scoreSequenceQuiz(userAnswer, expected);
        
        // Display result
        this.displayQuizResult(result);

        // Store score
        this.storeQuizScore(result);

        // v4.0.7: Mirror the MC quiz path — set quiz_taken so guest pills with
        // is_guest && quiz_taken conditions become visible after text quiz completion.
        this.ivr.context.quiz_taken = true;
        this.ivr.context.score = result.score;
        this.ivr.context.first_message_after_quiz = true;
        setTimeout(() => {
            this.checkAutoMessages();
            this.floscShowUserAutoPrompts();
        }, 1500);
    }

    // v9.3.3: Score a sequence quiz (compare user answer to expected)
    scoreSequenceQuiz(userAnswer, expected) {
        // Normalize both strings: remove spaces, convert to lowercase, handle variations
        const normalize = (str) => {
            return str
                .toLowerCase()
                .replace(/[,\s\-\.]+/g, ',')  // Normalize separators to commas
                .replace(/one/g, '1')
                .replace(/two/g, '2')
                .replace(/three/g, '3')
                .replace(/four/g, '4')
                .replace(/five/g, '5')
                .replace(/six/g, '6')
                .replace(/seven/g, '7')
                .replace(/eight/g, '8')
                .replace(/nine/g, '9')
                .replace(/ten/g, '10')
                .split(',')
                .map(s => s.trim())
                .filter(s => s.length > 0);
        };
        
        const userParts = normalize(userAnswer);
        const expectedParts = normalize(expected);
        
        let correctCount = 0;
        let totalExpected = expectedParts.length;
        
        // Compare each position
        for (let i = 0; i < totalExpected; i++) {
            if (userParts[i] === expectedParts[i]) {
                correctCount++;
            }
        }
        
        const percentage = Math.round((correctCount / totalExpected) * 100);
        
        return {
            score: percentage,
            correct: correctCount,
            total: totalExpected,
            userAnswer: userAnswer,
            expected: expected,
            passed: percentage >= 70  // 70% threshold
        };
    }
    
    // v9.3.3: Display quiz result in modal
    displayQuizResult(result) {
        // Hide input panels
        this.setDisplayState(document.getElementById('floscQuizTextPanel'), false, 'block');
        this.setDisplayState(document.getElementById('floscQuizAudioPanel'), false, 'block');
        this.setDisplayState(document.querySelector('.quiz-tabs'), false, 'flex');
        
        // Show result panel
        const resultPanel = document.getElementById('floscQuizResultPanel');
        if (resultPanel) {
            this.setDisplayState(resultPanel, true, 'block');
        }
        
        // Update score display
        const scoreDisplay = document.getElementById('floscQuizScoreDisplay');
        if (scoreDisplay) {
            scoreDisplay.textContent = `${result.score}%`;
            scoreDisplay.className = 'quiz-score-display ' + (result.passed ? 'passed' : 'failed');
        }
        
        // Update message
        const messageEl = document.getElementById('floscQuizResultMessage');
        if (messageEl) {
            if (result.passed) {
                messageEl.innerHTML = `<strong>Great job!</strong> You got ${result.correct} out of ${result.total} correct.<br>Continue to see your personalized learning path.`;
            } else {
                messageEl.innerHTML = `You got ${result.correct} out of ${result.total} correct.<br>Don't worry - we'll help you improve! Continue to get started.`;
            }
        }
    }
    
    // v9.3.3: Store quiz score (localStorage + API)
    // v3.0.2: Include quiz_id so server can resolve lesson category via lesson_groups
    async storeQuizScore(result) {
        // Store in localStorage as backup
        const quizData = {
            score: result.score,
            correct: result.correct,
            total: result.total,
            passed: result.passed,
            timestamp: Date.now(),
            userAnswer: result.userAnswer,
            quizId: this.quiz.id || ''
        };
        if (result.quizType) quizData.quizType = result.quizType;
        if (result.phraseResults) quizData.phraseResults = result.phraseResults;
        if (result.wordIpa) quizData.wordIpa = result.wordIpa;
        if (result.rankedPhonemes) quizData.rankedPhonemes = result.rankedPhonemes;
        // v8.0.0: For visitors taking the IPA audio quiz, mark that real scores
        // are pending server-side scoring (not available in localStorage yet).
        if (result.skipServerStore) quizData.pendingServerScore = true;
        // v8.0.7: Store temp_id so post-login scoring works for ALL auth methods
        // (email, Facebook, Google, any SSO). The cookie-based approach fails cross-domain.
        if (this.ipaQuiz?.tempId) quizData.tempId = this.ipaQuiz.tempId;
        // v8.0.0: Store DO session_id so registration/login can pull scores from DO
        if (result.sessionId) quizData.sessionId = result.sessionId;
        localStorage.setItem(this.flowStorageKey('flosc_quiz_result'), JSON.stringify(quizData));
        
        // v8.0.0: Visitors with audio quiz — server scores on register/login.
        // No need to set the prelogin score cookie (it would contain score: 0).
        if (result.skipServerStore) return;
        
        // Also store via API (sets cookie for server-side access)
        // v1.8.3: Convert text items to 1-indexed lesson POSITIONS so PHP
        // get_missed_lessons() receives numeric lesson numbers, not text strings
        const expected = (this.quiz.expected || []).map(e => e.toLowerCase());
        const correctPositions = (this.quiz.correctItems || []).map(item => {
            const idx = expected.indexOf(item);
            return idx >= 0 ? idx + 1 : null;
        }).filter(n => n !== null);
        const missedPositions = (this.quiz.missedItems || []).map(item => {
            const idx = expected.indexOf(item);
            return idx >= 0 ? idx + 1 : null;
        }).filter(n => n !== null);

        try {
            await this.authFetch(this.config.apiUrl + '/store-score', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    score: result.score,
                    quiz_id: this.quiz.id || '',
                    quiz_type: result.quizType || 'sequence',
                    correct: result.incorrect ? [] : correctPositions,
                    incorrect: result.incorrect || missedPositions,
                    ranked_worst_lessons: result.ranked_worst_lessons || [],
                    details: quizData
                })
            });
        } catch (e) {
            this.logError('FLOSC: Could not store quiz score', e);
        }
    }
    
    // v9.3.3: Handle quiz completion - close modal, trigger login gate
    onQuizComplete() {
        // Hide the modal
        this.hideRecordingModal();
        
        // Reset modal state for next time
        this.resetQuizModal();
        
        // Update IVR context
        this.ivr.context.quiz_completed = true;
        this.ivr.context.first_message_after_quiz = true;
        
        // Trigger login gate IVR message
        setTimeout(() => {
            this.checkAutoMessages();
            // v8.0.0: Visitors must sign up to see scored results.
            // Show auth modal directly — the IVR completion message already
            // told them to sign up; this provides the actual form.
            if (this.state === 'visitor') {
                setTimeout(() => this.showAuthModal(), 800);
            }
        }, 500);
    }
    
    // v9.3.3: Reset quiz modal to initial state
    resetQuizModal() {
        // Show tabs and text panel (default)
        this.setDisplayState(document.querySelector('.quiz-tabs'), true, 'flex');
        this.setDisplayState(document.getElementById('floscQuizTextPanel'), true, 'block');
        this.setDisplayState(document.getElementById('floscQuizAudioPanel'), false, 'block');
        this.setDisplayState(document.getElementById('floscQuizResultPanel'), false, 'block');
        
        // Reset tab buttons
        document.querySelectorAll('.quiz-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === 'text');
        });
        
        // Clear input
        const input = document.getElementById('floscQuizTextInput');
        if (input) input.value = '';
        
        // Reset recording UI
        this.setDisplayState(document.getElementById('floscQuizRecordButton'), true, 'inline-flex');
        this.setDisplayState(document.getElementById('floscQuizStopButton'), false, 'inline-flex');
        this.setDisplayState(document.getElementById('floscQuizSubmitRecordingButton'), false, 'inline-flex');
        const status = document.getElementById('floscQuizRecordingStatus');
        if (status) status.textContent = '';
    }
    
    // v9.3.3: Start quiz audio recording
    async startQuizRecording() {
        try {
            this.quizRecordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.quizMediaRecorder = new MediaRecorder(this.quizRecordingStream);
            const { mime: qMime, format: qFormat } = this._resolveMime(this.quizMediaRecorder);
            this.quizRecordingMime   = qMime;
            this.quizRecordingFormat = qFormat;
            this.quizAudioChunks = [];
            
            this.quizMediaRecorder.ondataavailable = (e) => {
                this.quizAudioChunks.push(e.data);
            };
            
            this.quizMediaRecorder.start();
            
            // Update UI
            this.setDisplayState(document.getElementById('floscQuizRecordButton'), false, 'inline-flex');
            this.setDisplayState(document.getElementById('floscQuizStopButton'), true, 'inline-flex');
            const status = document.getElementById('floscQuizRecordingStatus');
            if (status) {
                status.textContent = '🔴 Recording...';
                status.classList.add('recording');
            }
        } catch (e) {
            this.logError('FLOSC: Could not start quiz recording', e);
            const status = document.getElementById('floscQuizRecordingStatus');
            if (status) status.textContent = '⚠️ Could not access microphone';
        }
    }
    
    // v9.3.3: Stop quiz audio recording
    stopQuizRecording() {
        if (this.quizMediaRecorder) {
            this.quizMediaRecorder.stop();
            
            if (this.quizRecordingStream) {
                this.quizRecordingStream.getTracks().forEach(track => track.stop());
            }
            
            // Update UI
            this.setDisplayState(document.getElementById('floscQuizStopButton'), false, 'inline-flex');
            this.setDisplayState(document.getElementById('floscQuizSubmitRecordingButton'), true, 'inline-flex');
            const status = document.getElementById('floscQuizRecordingStatus');
            if (status) {
                status.textContent = '✅ Recording complete - ready to submit';
                status.classList.remove('recording');
            }
        }
    }
    
    // v9.3.3: Submit quiz audio recording for transcription and scoring
    async submitQuizRecording() {
        const status = document.getElementById('floscQuizRecordingStatus');
        if (status) status.textContent = '⏳ Processing...';
        
        const mime = this.quizRecordingMime || this._resolveMime(this.quizMediaRecorder).mime;
        const audioBlob = new Blob(this.quizAudioChunks, { type: mime });
        const formData = new FormData();
        formData.append('audio', audioBlob);
        
        try {
            const response = await this.authFetch(this.config.apiUrl + '/transcribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.transcript) {
                // Got transcript, now score it
                const modal = document.getElementById('flosc_modal_recording');
                const expected = modal?.dataset.quizContent || '1,2,3,4,5,6,7,8,9,10';
                
                const result = this.scoreSequenceQuiz(data.transcript, expected);
                result.userAnswer = data.transcript;  // Store what was transcribed
                
                this.displayQuizResult(result);
                this.storeQuizScore(result);
            } else {
                if (status) status.textContent = '⚠️ Could not process audio. Try typing instead.';
            }
        } catch (e) {
            this.logError('FLOSC: Quiz transcription failed', e);
            if (status) status.textContent = '⚠️ Error processing audio. Try typing instead.';
        }
    }

    async restartChat() {
        // v9.5.9: Add spinning animation to button
        const restartBtn = document.getElementById('flosc_app_restart_chat');
        if (restartBtn) {
            restartBtn.classList.add('spinning');
            setTimeout(() => restartBtn.classList.remove('spinning'), 600);
        }

        // v9.1.1: Clear timer to prevent memory leaks
        this.clearInactivityTimer();

        // Clear the chat messages
        if (this.chatMessages) {
            this.chatMessages.innerHTML = '';
        }

        // Reset IVR state
        this.ivr.messageCount = 0;
        this.ivr.shownThisSession = {};
        this.ivr.sessionStart = Date.now();
        this.ivr.lastInteraction = Date.now();

        // Reset anti-repetition memory so a total refresh starts clean.
        this._shownAssistant = {};
        this._repeatIdx = 0;

        // Total refresh means "new chat" across states.
        // Logged-in users get a fresh server session on next send.
        this.currentSession = null;
        this._adminPollSessionId = null;
        this._adminPollToken = '';
        this._adminSince = 0;

        // Clear session tracking (use same pattern as buildIVRContext)
        const sessionKey = 'flosc_session_' + this.getSessionKey();
        localStorage.removeItem(sessionKey);

        // Clear visitor messages, visitor quiz state, and the current visitor
        // balance key before minting a brand-new visitor session id, so the
        // restart begins a genuinely new conversation with its own concierge desk.
        if (this.state === 'visitor') {
            const previousTokenKey = this.getVisitorTokenStorageKey();
            localStorage.removeItem(this.flowStorageKey('flosc_visitor_messages'));
            localStorage.removeItem(this.flowStorageKey('flosc_quiz_result'));
            localStorage.removeItem(previousTokenKey);
            const freshVisitorSessionId = String(Date.now());
            this._visitorSessionId = freshVisitorSessionId;
            try { localStorage.setItem(this.flowStorageKey('flosc_visitor_session'), freshVisitorSessionId); } catch (e) {}

            this.visitorDepletedState.awaitingContactDetails = false;
            this.visitorDepletedState.inputLocked = false;
            this.visitorDepletedState.formRenderedAt = 0;
            this.visitorDepletedState.formSubmitted = false;
            if (this.chatInput) this.chatInput.disabled = false;
            if (this.sendBtn) this.sendBtn.disabled = false;

            // Circle restart is a new visitor conversation, so reset profile
            // token display into a pending state. Server session balance remains
            // authoritative and will repaint via poll/chat payload.
            this.updateVisitorTokenLabelPending();

            // Hydrate the brand-new visitor session balance immediately so the
            // restarted chat does not wait for poll/chat traffic to show count.
            await this.fetchVisitorSessionBalanceOnInit();

            // A hard reload guarantees the old chat DOM and transient runtime
            // state are gone, so the next init starts from the new visitor session.
            window.location.reload();
            return;
        }

        // Rebuild context
        this.buildIVRContext();

        // A manual chat refresh is an explicit new conversation request.
        // Clear companion carry-over context so restart always lands on a
        // fresh intro path instead of "continue from page" greeting.
        this.consumeBrowsingContextQueryParams();
        this.browsingContext = {
            page_url: '',
            page_title: '',
            page_path: '',
            page_referrer: '',
            surface: '',
            trail: []
        };

        // Restart IVR
        this.startIVR();

        this.log('FLOSC: Chat restarted');
    }
    
    setupUI() {
        this.restoreSidebarCollapsedState();

        const profileBar = document.getElementById('flosc_user_profile_bar');
        const getStateRadius = (state) => {
            if (!profileBar) {
                return '8px';
            }
            if (state === 'member') {
                return profileBar.dataset.memberAvatarRadius || '8px';
            }
            if (state === 'guest') {
                return profileBar.dataset.guestAvatarRadius || '8px';
            }
            return profileBar.dataset.visitorAvatarRadius || '8px';
        };
        const setAvatarRadius = (state) => {
            const radius = getStateRadius(state);
            document.documentElement.style.setProperty('--flosc-avatar-radius', radius);
        };
        setAvatarRadius(this.state);

        // v1.8.0: Populate profile bar — same bar for all states, content toggled via data-show
        if (this.state === 'visitor') {
            this.bindVisitorAvatarImageFallback();
            this.updateVisitorTokenLabelPending();
        }

        if (this.user && this.state !== 'visitor') {
            const profileAvatar = document.getElementById('flosc_profile_avatar');
            const profileAvatarIcon = document.getElementById('flosc_profile_avatar_icon');
            const profileName = document.getElementById('flosc_profile_name');
            const profileBadge = document.getElementById('flosc_profile_badge');
            const dropdownName = document.getElementById('flosc_dropdown_name');
            const dropdownEmail = document.getElementById('flosc_dropdown_email');
            const upgradeContainer = document.getElementById('flosc_upgrade_container');
            const upgradeBtn = document.getElementById('flosc_upgrade_button');
            const upgradeBtnLabel = document.getElementById('flosc_upgrade_button_label');

            const bar = document.getElementById('flosc_user_profile_bar');
            const isMember = this.state === 'member';
            const configuredImageUrl = isMember
                ? String(bar?.dataset.memberIconUrl || '').trim()
                : String(bar?.dataset.guestIconUrl || '').trim();
            const configuredIcon = isMember
                ? String(bar?.dataset.memberIcon || '').trim()
                : String(bar?.dataset.guestIcon || '').trim();
            const configuredName = isMember
                ? String(bar?.dataset.memberName || '').trim()
                : String(bar?.dataset.guestName || '').trim();
            const effectiveName = configuredName || this.user.name || '';

            if (profileAvatar && profileAvatarIcon) {
                if (configuredImageUrl) {
                    profileAvatarIcon.textContent = '';
                    profileAvatar.onerror = () => {
                        profileAvatar.removeAttribute('src');
                        profileAvatar.onerror = null;
                        if (configuredIcon) {
                            profileAvatarIcon.textContent = configuredIcon;
                            this.setDisplayState(profileAvatarIcon, true, 'flex');
                            this.setDisplayState(profileAvatar, false, 'block');
                        }
                    };
                    profileAvatar.src = configuredImageUrl;
                    profileAvatar.alt = effectiveName || 'User avatar';
                    this.setDisplayState(profileAvatar, true, 'block');
                    this.setDisplayState(profileAvatarIcon, false, 'flex');
                } else if (configuredIcon) {
                    profileAvatar.removeAttribute('src');
                    profileAvatar.onerror = null;
                    profileAvatarIcon.textContent = configuredIcon;
                    this.setDisplayState(profileAvatarIcon, true, 'flex');
                    this.setDisplayState(profileAvatar, false, 'block');
                } else {
                    profileAvatarIcon.textContent = '';
                    if (this.user.avatar) {
                        profileAvatar.src = this.user.avatar;
                        profileAvatar.alt = this.user.name || 'User avatar';
                    } else {
                        profileAvatar.removeAttribute('src');
                    }
                    this.setDisplayState(profileAvatar, true, 'block');
                    this.setDisplayState(profileAvatarIcon, false, 'flex');
                }
            }

            if (profileName && effectiveName) {
                profileName.dataset.baseName = effectiveName;
                // Token count is filled by updateLoggedInTokenLabel (visitor parity).
                profileName.textContent = effectiveName;
            }

            // Guest/Member wallet in the profile bar (V→G grant may still be pending;
            // applyGuestTokenGrantOnInit refreshes this again after the REST call).
            const initialTokens = this.resolveLoggedInTokenBalance();
            this.updateLoggedInTokenLabel(initialTokens);
            // Companion: profile row under the input (sidebar bar is hidden in embed).
            this.updateCompanionSessionStatus(initialTokens, effectiveName);

            if (profileBadge) {
                // Read badge text from admin-configured data attributes on the profile bar
                if (this.state === 'member') {
                    profileBadge.textContent = bar?.dataset.memberBadge || 'Member';
                } else {
                    profileBadge.textContent = bar?.dataset.guestBadge || 'Guest';
                }
            }

            if (dropdownName && effectiveName) {
                dropdownName.textContent = effectiveName;
            }

            if (dropdownEmail && this.user.email) {
                dropdownEmail.textContent = this.user.email;
            }

            if (upgradeContainer && upgradeBtnLabel && bar) {
                if (isMember) {
                    const showMemberUpgrade = String(bar.dataset.memberUpgradeShow || '0') === '1';
                    const memberUpgradeLabel = String(bar.dataset.memberUpgradeLabel || 'Upgrade').trim() || 'Upgrade';
                    this.setDisplayState(upgradeContainer, showMemberUpgrade, 'block');
                    upgradeBtnLabel.textContent = memberUpgradeLabel;
                } else {
                    const showGuestUpgrade = String(bar.dataset.guestUpgradeShow || '1') === '1';
                    const guestUpgradeLabel = String(bar.dataset.guestUpgradeLabel || 'Upgrade to Pro').trim() || 'Upgrade to Pro';
                    this.setDisplayState(upgradeContainer, showGuestUpgrade, 'block');
                    upgradeBtnLabel.textContent = guestUpgradeLabel;
                }
            }
        }

        // Companion profile row under the composer — paint once UI is ready
        // (covers visitor pending path + any missed logged-in updates).
        if (this.isCompanionSurface()) {
            if (this.state === 'visitor') {
                this.updateCompanionSessionStatus(undefined);
            } else {
                this.updateCompanionSessionStatus(this.resolveLoggedInTokenBalance());
            }
        }
    }

    formatProfileTokenDisplay(value) {
        const n = Math.max(0, parseInt(value, 10) || 0);
        return n.toLocaleString();
    }

    getVisitorRealMillicents(tokenValue) {
        const tokens = Math.max(0, parseInt(tokenValue, 10) || 0);
        const econ = this.config?.communicationTokenEconomics || {};
        const realCfg = econ.real_millicents_per_token || {};
        let num = parseInt(realCfg.numerator, 10);
        let den = parseInt(realCfg.denominator, 10);

        // Fallback: infer ratio from the configured first-paint pair when ratio
        // object is unavailable so dynamic updates remain aligned with server UI.
        if (!Number.isFinite(num) || !Number.isFinite(den) || num <= 0 || den <= 0) {
            const baseTokens = parseInt(this.config?.visitorTokenDisplay?.value, 10);
            const baseMillicents = parseInt(this.config?.visitorTokenDisplay?.realMillicents, 10);
            if (Number.isFinite(baseTokens) && Number.isFinite(baseMillicents) && baseTokens > 0 && baseMillicents >= 0) {
                num = baseMillicents;
                den = baseTokens;
            }
        }

        num = Math.max(1, parseInt(num, 10) || 1);
        den = Math.max(1, parseInt(den, 10) || 2);
        return Math.max(0, Math.round((tokens * num) / den));
    }

    formatProfileMillicentDisplay(value) {
        const mc = Math.max(0, parseInt(value, 10) || 0);
        return `${mc.toLocaleString()} mc`;
    }

    getVisitorTokenStorageKey() {
        const namespace = 'v3';
        const flowId = String(this.config?.flowId || 'default');
        const grantValueRaw = parseInt(this.config?.visitorTokenDisplay?.value, 10);
        const grantValue = Number.isFinite(grantValueRaw) && grantValueRaw >= 0 ? grantValueRaw : 0;
        const sessionId = String(this.getVisitorSessionId() || 'no_session');
        return `flosc_visitor_token_balance_${namespace}_${flowId}_g${grantValue}_${sessionId}`;
    }

    getPersistedVisitorTokenBalance() {
        try {
            const raw = localStorage.getItem(this.getVisitorTokenStorageKey());
            const parsed = parseInt(raw, 10);
            return Number.isFinite(parsed) ? Math.max(0, parsed) : null;
        } catch (e) {
            return null;
        }
    }

    persistVisitorTokenBalance(tokenValue) {
        try {
            const n = Math.max(0, parseInt(tokenValue, 10) || 0);
            localStorage.setItem(this.getVisitorTokenStorageKey(), String(n));
        } catch (e) {
            // no-op: localStorage may be unavailable in strict private mode
        }
    }

    parseVisitorTokenValue(tokenValue) {
        const numeric = Number(tokenValue);
        if (!Number.isFinite(numeric)) {
            return null;
        }
        return Math.max(0, Math.round(numeric));
    }

    syncVisitorTokenBalanceFromPayload(payload) {
        if (!payload || !payload.token_balance) {
            return;
        }

        // Logged-in chat charges return scope=user_flow — keep guest/member bar in sync.
        const scope = String(payload.token_balance.scope || '');
        if (this.state !== 'visitor' && (scope === 'user_flow' || scope === '')) {
            const value = this.parseVisitorTokenValue(payload.token_balance.value);
            if (Number.isFinite(value)) {
                this.updateLoggedInTokenLabel(value);
            }
            return;
        }

        if (this.state !== 'visitor') {
            return;
        }

        const value = this.parseVisitorTokenValue(payload.token_balance.value);
        if (!Number.isFinite(value)) {
            return;
        }

        this.updateVisitorTokenLabel(value);
        this.persistVisitorTokenBalance(value);
        this.showLowTokenMessageIfNeeded(payload.token_balance);

        // If tokens were assigned after a depleted-form submission, reopen chat
        // input so the visitor can continue and later hit depletion again.
        if (
            value > 0
            && this.visitorDepletedState?.inputLocked
        ) {
            const depletedForm = document.getElementById('flosc_depleted_contact_form');
            if (depletedForm) {
                const wrap = depletedForm.closest('.flosc-depleted-form-wrap');
                if (wrap) {
                    wrap.remove();
                } else {
                    depletedForm.remove();
                }
            }

            this.visitorDepletedState.inputLocked = false;
            this.visitorDepletedState.awaitingContactDetails = false;
            this.visitorDepletedState.formSubmitted = false;

            if (this.chatInput) {
                this.chatInput.disabled = false;
            }
            if (this.sendBtn) {
                this.sendBtn.disabled = false;
            }
        }
    }

    /**
     * Visitor avatar: hide broken icon_url image so emoji fallback shows (no blue empty tile).
     */
    bindVisitorAvatarImageFallback() {
        const wrap = document.querySelector('.user-profile-bar .visitor-avatar');
        if (!wrap) {
            return;
        }
        const img = wrap.querySelector('img[data-flosc-visitor-avatar-img]');
        if (!img) {
            return;
        }
        const fail = () => {
            img.setAttribute('data-failed', '1');
            wrap.classList.add('is-image-failed');
            img.removeAttribute('src');
        };
        img.addEventListener('error', fail, { once: true });
        if (img.complete && img.naturalWidth === 0) {
            fail();
        }
    }

    updateVisitorTokenLabel(tokenValue) {
        const visitorName = document.querySelector('.profile-name[data-show="visitor"]');
        if (!visitorName) {
            this.updateCompanionSessionStatus(tokenValue);
            return;
        }

        let baseLabel = (this.config?.visitorTokenDisplay?.label || '').trim();
        if (!baseLabel) {
            const existingLabel = visitorName.querySelector('.flosc-visitor-label-text')?.textContent || visitorName.textContent || 'Visitor';
            baseLabel = existingLabel.trim().replace(/\s*\([^)]*\)\s*$/i, '') || 'Visitor';
        }

        const formattedTokens = this.formatProfileTokenDisplay(tokenValue);
        // Show only the token count to users; real millicents stay internal (admin-only).
        visitorName.innerHTML = `<span class="flosc-visitor-label-text">${this.escapeHtml(baseLabel)}</span> <span class="flosc-visitor-token-count" id="flosc_visitor_token_count">(${this.escapeHtml(formattedTokens)})</span>`;

        this.postCompanionTokenUpdate(tokenValue, formattedTokens);
        this.updateCompanionSessionStatus(tokenValue, baseLabel);
    }

    /**
     * Guest/Member: show wallet beside the display name, same (N) pattern as visitors.
     * Uses #flosc_profile_name — there is no separate token DOM node for logged-in users.
     */
    resolveLoggedInTokenBalance() {
        if (!this.user) {
            return 0;
        }
        const candidates = [
            this.user.tokenBalance,
            this.user.tokens,
            this.user.flowTokens,
        ];
        for (const c of candidates) {
            const n = parseInt(c, 10);
            if (Number.isFinite(n) && n >= 0) {
                return n;
            }
        }
        return 0;
    }

    updateLoggedInTokenLabel(tokenValue) {
        if (this.state === 'visitor') {
            return;
        }

        const profileName = document.getElementById('flosc_profile_name');
        let baseName = '';
        if (profileName) {
            baseName = String(profileName.dataset.baseName || '').trim();
            if (!baseName) {
                const existing = profileName.querySelector('.flosc-user-label-text')?.textContent
                    || String(profileName.textContent || '').replace(/\s*\([^)]*\)\s*$/i, '').trim();
                baseName = existing
                    || String(this.user?.name || '').trim()
                    || (this.state === 'member' ? 'Member' : 'Guest');
                profileName.dataset.baseName = baseName;
            }
        } else {
            baseName = String(this.user?.name || '').trim()
                || (this.state === 'member' ? 'Member' : 'Guest');
        }

        const n = Math.max(0, parseInt(tokenValue, 10) || 0);
        const formattedTokens = this.formatProfileTokenDisplay(n);
        if (this.user) {
            this.user.tokenBalance = n;
            this.user.tokens = n;
            this.user.tokensFormatted = formattedTokens;
        }

        if (profileName) {
            profileName.innerHTML =
                `<span class="flosc-user-label-text">${this.escapeHtml(baseName)}</span> ` +
                `<span class="flosc-user-token-count" id="flosc_user_token_count" data-flosc-token-balance="1">(${this.escapeHtml(formattedTokens)})</span>`;
        }

        this.postCompanionTokenUpdate(n, formattedTokens, baseName);
        this.updateCompanionSessionStatus(n, baseName);
    }

    /**
     * Companion-embed only: profile row under the input.
     * Layout: Name (V|G|M) · TokenCount · ExpandSubMenuCaret
     * Compact tier letter in parentheses — no full "Member"/"Guest" word, no pill.
     */
    updateCompanionSessionStatus(tokenValue, displayName) {
        if (!this.isCompanionSurface()) {
            return;
        }

        const strip = document.getElementById('flosc_companion_session_status');
        if (!strip) {
            return;
        }

        const userEl = document.getElementById('flosc_companion_session_user');
        const tokensEl = document.getElementById('flosc_companion_session_tokens');
        const tierEl = document.getElementById('flosc_companion_session_tier');
        if (!userEl || !tokensEl) {
            return;
        }

        let name = String(displayName || '').trim();
        if (!name) {
            if (this.state === 'visitor') {
                name = String(this.config?.visitorTokenDisplay?.label || '').trim() || 'Visitor';
            } else {
                name = String(this.user?.name || '').trim() || 'You';
            }
        }

        // Compact access code from flow params (FLOSC defaults: V / G / M).
        const tierCodes = this.config?.companionProfileTier || {};
        const tierLabels = this.config?.companionProfileTierLabels || {};
        const stateKey = this.state === 'member' || this.state === 'guest' || this.state === 'visitor'
            ? this.state
            : 'visitor';
        const defaultCodes = { visitor: 'V', guest: 'G', member: 'M' };
        const defaultLabels = { visitor: 'Visitor', guest: 'Guest', member: 'Member' };
        let tierCode = String(tierCodes[stateKey] || defaultCodes[stateKey] || 'V').trim().toUpperCase();
        tierCode = tierCode.replace(/[^A-Z0-9]/g, '').slice(0, 3) || defaultCodes[stateKey] || 'V';
        let tierLabel = String(tierLabels[stateKey] || defaultLabels[stateKey] || stateKey).trim()
            || defaultLabels[stateKey]
            || stateKey;
        const tierText = `(${tierCode})`;

        let n;
        let tokenText;
        if (tokenValue === '...') {
            tokenText = '…';
        } else if (tokenValue === undefined || tokenValue === null || tokenValue === '') {
            if (this.state === 'visitor') {
                const persisted = this.getPersistedVisitorTokenBalance?.();
                n = Number.isFinite(persisted) ? persisted : 0;
            } else {
                n = this.resolveLoggedInTokenBalance();
            }
            if (!Number.isFinite(n)) {
                n = 0;
            }
            tokenText = this.formatProfileTokenDisplay(n);
        } else {
            n = Math.max(0, parseInt(tokenValue, 10) || 0);
            tokenText = this.formatProfileTokenDisplay(n);
        }

        userEl.textContent = name;
        tokensEl.textContent = tokenText;
        if (tierEl) {
            tierEl.textContent = tierText;
            tierEl.hidden = false;
            // No title tooltip (avoids odd browser “badge” popovers). Full meaning on the toggle.
            tierEl.removeAttribute('title');
            tierEl.setAttribute('aria-hidden', 'true');
        }
        // Accessible full name for the toggle button.
        const toggle = document.getElementById('flosc_companion_profile_toggle');
        if (toggle) {
            toggle.setAttribute('aria-label', `${name}, ${tierLabel}, ${tokenText} tokens`);
        }
        strip.hidden = false;

        this.ensureCompanionProfileMenu();
        this.bindCompanionProfileRowOnce();
    }

    /**
     * Build submenu items from the same profile-bar menu config as full page.
     * Rebuilds when user state changes (visitor/guest/member).
     */
    ensureCompanionProfileMenu() {
        const menu = document.getElementById('flosc_companion_profile_menu');
        if (!menu) {
            return;
        }

        const buildKey = String(this.state || 'visitor') + ':' + String(this.user?.id || 0);
        if (menu.dataset.builtKey === buildKey) {
            const nameEl = menu.querySelector('.flosc-companion-profile-menu-name');
            const emailEl = menu.querySelector('.flosc-companion-profile-menu-email');
            if (nameEl) {
                nameEl.textContent = String(this.user?.name || nameEl.textContent || '').trim();
            }
            if (emailEl) {
                emailEl.textContent = String(this.user?.email || '').trim();
                emailEl.hidden = !emailEl.textContent;
            }
            return;
        }

        const safeAction = (raw) => String(raw || '').trim().replace(/[^a-z0-9_:-]/gi, '');
        const parts = [];
        if (this.state !== 'visitor' && this.user) {
            const uname = this.escapeHtml(String(this.user.name || '').trim());
            const uemail = this.escapeHtml(String(this.user.email || '').trim());
            parts.push(
                `<div class="flosc-companion-profile-menu-header">` +
                    `<div class="flosc-companion-profile-menu-name">${uname}</div>` +
                    (uemail ? `<div class="flosc-companion-profile-menu-email">${uemail}</div>` : '') +
                `</div>`
            );
        }

        // Mirror sidebar profile dropdown items for the current state.
        const groupSel = this.state === 'visitor'
            ? '.profile-dropdown-group[data-show="visitor"] [data-action]'
            : '.profile-dropdown-group[data-show="logged-in"] [data-action]';
        const sourceItems = document.querySelectorAll(groupSel);
        if (sourceItems.length) {
            sourceItems.forEach((item) => {
                const action = safeAction(item.dataset.action);
                const label = String(item.textContent || '').trim();
                if (!action || !label) {
                    return;
                }
                if (action === 'open_sandbox_purchase' || action.startsWith('show_offer')) {
                    return;
                }
                parts.push(
                    `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="${this.escapeHtml(action)}">${this.escapeHtml(label)}</button>`
                );
            });
        } else if (this.state === 'visitor') {
            parts.push(
                `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="login">Log In</button>`,
                `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="open_registration">Sign Up</button>`
            );
        } else {
            parts.push(
                `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="view_profile">My Profile</button>`,
                `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="logout">Log Out</button>`
            );
        }

        // Guest/member upgrade when configured on profile bar params.
        if (this.state === 'guest' || this.state === 'member') {
            const bar = document.getElementById('flosc_user_profile_bar');
            const showUpgrade = this.state === 'member'
                ? String(bar?.dataset.memberUpgradeShow || '0') === '1'
                : String(bar?.dataset.guestUpgradeShow || '1') === '1';
            if (showUpgrade) {
                const label = this.state === 'member'
                    ? (bar?.dataset.memberUpgradeLabel || 'Upgrade')
                    : (bar?.dataset.guestUpgradeLabel || 'Upgrade to Pro');
                parts.push(
                    `<button type="button" class="flosc-companion-profile-menu-item" role="menuitem" data-action="show_upgrade">${this.escapeHtml(label)}</button>`
                );
            }
        }

        menu.innerHTML = parts.join('');
        menu.dataset.builtKey = buildKey;
        menu.hidden = true;
    }

    bindCompanionProfileRowOnce() {
        if (this._companionProfileRowBound) {
            return;
        }
        const toggle = document.getElementById('flosc_companion_profile_toggle');
        const menu = document.getElementById('flosc_companion_profile_menu');
        const strip = document.getElementById('flosc_companion_session_status');
        if (!toggle || !menu || !strip) {
            return;
        }
        this._companionProfileRowBound = true;

        const closeMenu = () => {
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        };

        const openMenu = () => {
            this.ensureCompanionProfileMenu();
            menu.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        };

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (menu.hidden) {
                openMenu();
            } else {
                closeMenu();
            }
        });

        menu.addEventListener('click', (e) => {
            const item = e.target.closest('[data-action]');
            if (!item) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const action = String(item.dataset.action || '').trim();
            closeMenu();
            if (!action) {
                return;
            }
            if (action === 'show_upgrade') {
                const offerId = this.getOfferIdForProduct?.();
                if (offerId) {
                    this.showOffer(offerId, { source: 'user' });
                }
                return;
            }
            if (typeof this.handleVisitorMenuAction === 'function') {
                this.handleVisitorMenuAction(action);
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#flosc_companion_session_status')) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeMenu();
            }
        });
    }

    postCompanionTokenUpdate(tokenValue, formattedTokens, userName) {
        if (window.self === window.top) {
            return;
        }
        try {
            let targetOrigin = '*';
            if (document.referrer) {
                const ref = new URL(document.referrer, window.location.origin);
                if (/^https?:$/.test(ref.protocol)) {
                    targetOrigin = ref.origin;
                }
            }
            window.parent.postMessage({
                type: 'flosc_companion_token_update',
                payload: {
                    formatted: formattedTokens,
                    value: Math.max(0, parseInt(tokenValue, 10) || 0),
                    userName: String(userName || this.user?.name || '').trim(),
                    ts: Date.now()
                }
            }, targetOrigin);
        } catch (e) {
            // Ignore cross-window messaging failures.
        }
    }

    updateVisitorTokenLabelPending() {
        const visitorName = document.querySelector('.profile-name[data-show="visitor"]');
        let baseLabel = (this.config?.visitorTokenDisplay?.label || '').trim();
        if (!baseLabel && visitorName) {
            const existingLabel = visitorName.querySelector('.flosc-visitor-label-text')?.textContent || visitorName.textContent || 'Visitor';
            baseLabel = existingLabel.trim().replace(/\s*\([^)]*\)\s*$/i, '') || 'Visitor';
        }
        if (!baseLabel) {
            baseLabel = 'Visitor';
        }

        if (visitorName) {
            visitorName.innerHTML = `<span class="flosc-visitor-label-text">${this.escapeHtml(baseLabel)}</span> <span class="flosc-visitor-token-count" id="flosc_visitor_token_count">(...)</span>`;
        }
        this.updateCompanionSessionStatus('...', baseLabel);
    }

    getLowTokenWarningStorageKey(thresholdValue) {
        const flowId = String(this.config?.flowId || 'default');
        const sessionId = String(this.getVisitorSessionId() || 'no_session');
        const threshold = Math.max(0, parseInt(thresholdValue, 10) || 0);
        return `flosc_low_token_warning_${flowId}_${sessionId}_${threshold}`;
    }

    showLowTokenMessageIfNeeded(tokenBalance) {
        if (this.state !== 'visitor' || !tokenBalance) return;

        const isLow = !!tokenBalance.is_low;
        const lowMessage = String(tokenBalance.low_message || '').trim();
        if (!isLow || !lowMessage) return;

        const warningKey = this.getLowTokenWarningStorageKey(tokenBalance.low_threshold);
        try {
            if (sessionStorage.getItem(warningKey) === '1') {
                return;
            }

            this.addMessage('assistant', this.formatMarkdown(lowMessage), true);
            this.saveVisitorMessage('assistant', lowMessage);
            sessionStorage.setItem(warningKey, '1');
        } catch (e) {
            this.addMessage('assistant', this.formatMarkdown(lowMessage), true);
            this.saveVisitorMessage('assistant', lowMessage);
        }
    }

    // v1.7.9: Initialize visitor bar for non-logged-in users
    initVisitorBar() {
        const visitorBar = document.getElementById('floscVisitorBar');
        if (!visitorBar) return;

        // Check if dismissed in this session
        const dismissed = sessionStorage.getItem(this.flowStorageKey('flosc_visitor_bar_dismissed'));
        if (dismissed) return;

        // Show bar after 2 second delay
        setTimeout(() => {
            this.setDisplayState(visitorBar, true, 'block');
        }, 2000);

        // CTA click - start quiz
        const ctaBtn = document.getElementById('floscVisitorBarCta');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', () => {
                this.sendMessage('Start quiz');
                this.setDisplayState(visitorBar, false, 'block');
                sessionStorage.setItem(this.flowStorageKey('flosc_visitor_bar_dismissed'), 'true');
            });
        }

        // Dismiss button
        const dismissBtn = document.getElementById('floscVisitorBarDismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                this.setDisplayState(visitorBar, false, 'block');
                sessionStorage.setItem(this.flowStorageKey('flosc_visitor_bar_dismissed'), 'true');
            });
        }
    }

    isCompanionHandoffAvailable() {
        return !!this.config?.companionHandoffEnabled;
    }

    getAllowedCompanionOrigins() {
        const allowed = new Set([window.location.origin]);
        const addOrigin = (raw) => {
            try {
                const parsed = new URL(String(raw || ''), window.location.origin);
                if (/^https?:$/.test(parsed.protocol)) {
                    allowed.add(parsed.origin);
                }
            } catch (e) {
                // Ignore invalid URL.
            }
        };
        // Hub companion / collapse targets (often cross-domain knowledge hubs).
        addOrigin(this.config?.companionCollapseUrl);
        addOrigin(this.config?.companionHubCompanionUrl);
        addOrigin(this.config?.companionHubFullScreenUrl);
        addOrigin(this.config?.appUrl);
        try {
            addOrigin(document.referrer);
        } catch (e) {
            // Ignore.
        }
        return allowed;
    }

    requestCompanionBrowsingContext() {
        if (window.self === window.top || !document.body.classList.contains('flosc-companion-embed')) {
            return;
        }

        const postRequest = () => {
            try {
                let targetOrigin = '*';
                if (document.referrer) {
                    const ref = new URL(document.referrer, window.location.origin);
                    if (/^https?:$/.test(ref.protocol)) {
                        targetOrigin = ref.origin;
                    }
                }
                window.parent.postMessage({ type: 'flosc_companion_context_request' }, targetOrigin);
            } catch (e) {
                // Ignore cross-window messaging failures.
            }
        };

        postRequest();
        window.setTimeout(postRequest, 250);
        window.setTimeout(postRequest, 1000);
    }

    getCompanionContextUrl() {
        try {
            const allowedOrigins = this.getAllowedCompanionOrigins();
            const params = new URLSearchParams(window.location.search || '');
            const raw = String(params.get('flosc_context_url') || '').trim();
            if (!raw) {
                return '';
            }
            const parsed = new URL(raw, window.location.origin);
            if (!/^https?:$/.test(parsed.protocol)) {
                return '';
            }
            if (!allowedOrigins.has(parsed.origin)) {
                return '';
            }
            return parsed.toString();
        } catch (e) {
            return '';
        }
    }

    resolveCompanionHandoffUrl() {
        const allowedOrigins = this.getAllowedCompanionOrigins();
        const contextUrl = this.getCompanionContextUrl();
        let lastSitePage = '';
        try {
            lastSitePage = String(sessionStorage.getItem('flosc_last_site_page') || '').trim();
        } catch (e) {
            lastSitePage = '';
        }

        const sameOriginReferrer = (() => {
            try {
                const ref = String(document.referrer || '').trim();
                if (!ref) {
                    return '';
                }
                const parsed = new URL(ref, window.location.origin);
                return parsed.origin === window.location.origin ? parsed.toString() : '';
            } catch (e) {
                return '';
            }
        })();

        // Prefer floscAdmin Hub Companion URL (knowledge hub), then collapse URL.
        const rawHub = String(
            this.config?.companionHubCompanionUrl
            || this.config?.companionCollapseUrl
            || ''
        ).trim();
        const rawFallback = rawHub || '/';

        const normalizeUrl = (input) => {
            try {
                const parsed = new URL(String(input || ''), window.location.origin);
                if (!/^https?:$/.test(parsed.protocol)) {
                    return null;
                }
                // Absolute hub URLs (cross-domain) must stay allowed via getAllowedCompanionOrigins.
                if (!allowedOrigins.has(parsed.origin)) {
                    return null;
                }
                return parsed;
            } catch (e) {
                return null;
            }
        };

        const contextParsed = normalizeUrl(contextUrl);
        const lastSiteParsed = normalizeUrl(lastSitePage);
        const referrerParsed = normalizeUrl(sameOriginReferrer);
        const fallbackParsed = normalizeUrl(rawFallback);

        // Hub mode (default): configured knowledge-hub URL first.
        // Domain persistence: prefer origin context, then hub fallback.
        const targetPolicy = String(this.config?.companionCollapseTargetPolicy || '').toLowerCase();
        const chosen = (targetPolicy === 'origin')
            ? (contextParsed || lastSiteParsed || referrerParsed || fallbackParsed)
            : (fallbackParsed || contextParsed || lastSiteParsed || referrerParsed);
        if (!chosen) {
            return '';
        }

        chosen.searchParams.set('flosc_companion_handoff', '1');
        chosen.searchParams.set('flosc_companion_open', '1');
        chosen.searchParams.set('flosc_companion_mode', 'panel');
        chosen.searchParams.delete('flosc_companion_expand_target');
        // Knowledge hub: owning flow id so the hub host loads the correct companion.
        const flowId = String(this.config?.companionFlowId || this.config?.flowId || '').trim();
        if (flowId) {
            chosen.searchParams.set('flosc_flow_id', flowId.slice(0, 80));
        }
        // Keep the same chat: guest/member server session id, or visitor client handoff.
        this.appendSessionContinuityParams(chosen);
        return chosen.toString();
    }

    forceCompanionMinimizedState() {
        const stateKey = String(this.config?.companionStateKey || '').trim();
        if (!stateKey) {
            return;
        }

        const storageType = String(this.config?.companionStateStorage || 'session').toLowerCase() === 'local'
            ? 'localStorage'
            : 'sessionStorage';

        try {
            const storage = window[storageType];
            if (storage) {
                storage.setItem(stateKey, 'closed');
            }
        } catch (e) {
            // Ignore storage failures.
        }
    }

    /**
     * Before collapse: ensure guest/member have a server session id to put on the hub URL.
     * Prefer currentSession, then active sidebar item, then most recent server session.
     */
    async ensureSessionIdForCompanionHandoff() {
        if (this.state === 'visitor') {
            return String(this.getVisitorSessionId() || '').trim();
        }
        if (this.currentSession?.id) {
            this.rememberActiveChatSessionId(this.currentSession.id);
            return String(this.currentSession.id).trim();
        }
        try {
            const active = this.sessionList?.querySelector('.flosc-session-item.active');
            const fromSidebar = String(active?.dataset?.sessionId || '').trim();
            if (fromSidebar) {
                await this.loadSession(fromSidebar);
                if (this.currentSession?.id) {
                    return String(this.currentSession.id).trim();
                }
            }
        } catch (e) {
            // Ignore.
        }
        try {
            await this.restoreLastSession();
        } catch (e) {
            // Ignore.
        }
        if (this.currentSession?.id) {
            this.rememberActiveChatSessionId(this.currentSession.id);
            return String(this.currentSession.id).trim();
        }
        return '';
    }

    async handoffToCompanion() {
        if (!this.isCompanionHandoffAvailable()) {
            this.log?.('FLOSC: companion handoff not enabled for this flow');
            return false;
        }

        // Guest/member: resolve server session before leaving full chat so the hub URL
        // always carries flosc_session_id (localStorage is origin-scoped and cannot
        // bridge the flow domain → the WordPress host iframe alone).
        if (this.state !== 'visitor') {
            await this.ensureSessionIdForCompanionHandoff();
        }

        let targetUrl = this.resolveCompanionHandoffUrl();
        // Last-resort absolute hub URL if origin allowlist rejected intermediate candidates.
        if (!targetUrl) {
            try {
                const hub = String(
                    this.config?.companionHubCompanionUrl
                    || this.config?.companionCollapseUrl
                    || ''
                ).trim();
                if (hub) {
                    const u = new URL(hub, window.location.origin);
                    if (/^https?:$/.test(u.protocol)) {
                        u.searchParams.set('flosc_companion_handoff', '1');
                        u.searchParams.set('flosc_companion_open', '1');
                        u.searchParams.set('flosc_companion_mode', 'panel');
                        const flowId = String(this.config?.companionFlowId || this.config?.flowId || '').trim();
                        if (flowId) {
                            u.searchParams.set('flosc_flow_id', flowId.slice(0, 80));
                        }
                        this.appendSessionContinuityParams(u);
                        targetUrl = u.toString();
                    }
                }
            } catch (e) {
                targetUrl = '';
            }
        }
        if (!targetUrl) {
            this.log?.('FLOSC: companion handoff URL empty');
            return false;
        }

        // Last check: guest/member URL must include flosc_session_id when we have one.
        if (this.state !== 'visitor' && this.currentSession?.id) {
            try {
                const u = new URL(targetUrl, window.location.origin);
                if (!u.searchParams.get('flosc_session_id')) {
                    u.searchParams.set('flosc_session_id', String(this.currentSession.id).slice(0, 80));
                    targetUrl = u.toString();
                }
            } catch (e) {
                // Keep targetUrl as-is.
            }
        }

        this.forceCompanionMinimizedState();
        window.location.assign(targetUrl);
        return true;
    }

    restoreSidebarCollapsedState() {
        if (!this.sidebar || window.innerWidth <= 768) {
            return;
        }

        try {
            if (localStorage.getItem('flosc_sidebar_collapsed') === 'true') {
                this.sidebar.classList.add('collapsed');
            } else {
                this.sidebar.classList.remove('collapsed');
            }
        } catch (e) {
            this.sidebar.classList.remove('collapsed');
        }
    }

    toggleSidebar() {
        if (this.sidebar) {
            const isMobile = window.innerWidth <= 768;
            const overlay = document.getElementById('flosc_app_sidebar_overlay');
            if (isMobile) {
                this.sidebar.classList.remove('collapsed');
                this.sidebar.classList.toggle('open');
                if (overlay) {
                    overlay.classList.toggle('show', this.sidebar.classList.contains('open'));
                }
            } else {
                if (overlay) overlay.classList.remove('show');
                this.sidebar.classList.remove('open');
                const isCollapsed = this.sidebar.classList.toggle('collapsed');
                try {
                    if (isCollapsed) {
                        localStorage.setItem('flosc_sidebar_collapsed', 'true');
                    } else {
                        localStorage.removeItem('flosc_sidebar_collapsed');
                    }
                } catch (e) {
                    // localStorage may be unavailable in strict private mode
                }
            }
        }
    }
    
    async sendMessage(directMessage = null, { executeActions = true } = {}) {
        const message = directMessage?.trim() || this.chatInput?.value?.trim();
        if (!message) return;

        if (this.state === 'visitor' && this.visitorDepletedState?.inputLocked) {
            return;
        }

        // Clear input
        this.chatInput.value = '';
        
        // Show user message
        this.addMessage('user', message);
        
        if (this.state === 'visitor') {
            this.saveVisitorMessage('user', message);
        }
        
        // Update context
        this.ivr.messageCount++;
        this.ivr.lastInteraction = Date.now();
        this.buildIVRContext();
        
        // Check for special commands
        if (this.onUserMessage(message)) {
            return;
        }

        // Free-lesson / lesson-library requests — only if this flow’s Lessons config is on.
        if (!this.flowServesLessons() && this._looksLikeLessonAsk(message)) {
            await this.syncSessionTitleFromFirstUserMessage(message);
            this.denyLessonsOnThisFlow(message);
            return;
        }
        if (this.isFreeLessonRequest(message)) {
            if (this.state === 'visitor') {
                const deny = 'To access your free lessons, please log in or create a free account first.';
                this.addMessage('assistant', deny, false);
                this.logClientChatTurn(message, deny, { source: 'free_lesson_guest_gate' });
                return;
            }
            await this.syncSessionTitleFromFirstUserMessage(message);
            this._pendingFreeLessonUserMessage = message;
            this.requestFreeLesson();
            return;
        }

        // User is asking for offers / all offers: real flow offers only (no AI catalog).
        if (this.handleUserOfferAsk(message)) {
            await this.syncSessionTitleFromFirstUserMessage(message);
            // Offer UI is rendered by handleUserOfferAsk; log the user ask + short summary.
            this.logClientChatTurn(message, '(Offer presentation — see chat UI)', {
                source: 'offer_ask',
                provider: 'client',
            });
            return;
        }
        
        // v3.0.5: Check if user message matches any offer's reveal_phrase (exact match).
        // Exact match happens client-side — no API call needed. AI interpretation
        // is handled server-side via the chat dispatch.
        const phraseMatchOffer = this._matchOfferRevealPhrase(message);
        if (phraseMatchOffer) {
            this.log('[FLOSC-OFFER] Reveal phrase matched offer:', phraseMatchOffer.id);
            setTimeout(() => this.showOffer(phraseMatchOffer.id, { source: 'user' }), 300);
            return;
        }
        
        this.showTyping();
        
        // Try IVR match first
        const ivrMatch = this.findIVRResponse(message);
        
        // v1.9.3: When AI is in charge, IVR matches are GUIDANCE for AI, not direct output.
        // IVR tells us WHAT to communicate. AI decides HOW to say it.
        // When AI is not configured (provider is 'ivr'), IVR displays directly.
        const aiActive = this.config.aiProvider && this.config.aiProvider !== 'ivr';
        
        if (ivrMatch && this.evaluateCondition(ivrMatch.conditions) && !aiActive) {
            // No AI — display IVR directly (original behavior)
            setTimeout(() => {
                this.hideTyping();
                const content = this.replaceVariables(ivrMatch.content);
                const el = this.addMessage('assistant', content);
                if (el && ivrMatch.name) el.setAttribute('data-message-name', ivrMatch.name);
                
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', content);
                }
                
                // v9.3.4: Execute action if present (e.g., open_quiz)
                // v2.0.8 FIX: Skip action when executeActions is false (prevents infinite loop
                // from requestFreeLesson → sendMessage → IVR action → requestFreeLesson)
                if (ivrMatch.action && executeActions) {
                    this.log('FLOSC: IVR action triggered:', ivrMatch.action);
                    this.performIVRAction(ivrMatch.action);
                }
                
                this.floscShowUserAutoPrompts();
            }, 500);
            return;
        }
        
        // v1.9.3: If IVR matched AND AI is active, send IVR guidance to AI.
        // If no IVR match, AI responds freely within Chatpack boundaries.
        // Either way, we call the API — AI is always in charge when configured.
        const ivrGuidance = (ivrMatch && this.evaluateCondition(ivrMatch.conditions) && aiActive)
            ? ivrMatch : null;
        
        if (ivrGuidance) {
            this.log('FLOSC: IVR match found, routing through AI:', ivrGuidance.name);
        }
        
        try {
            let response;
            try {
                response = await this.callAPI(message, ivrGuidance, { allowSessionAutoCreate: true });
            } catch (firstErr) {
                if (firstErr?.floscCode === 'visitor_tokens_depleted') {
                    throw firstErr;
                }
                // v8.0.0 FIX: Retry once with fresh nonce — handles stale-nonce after
                // registration page reload or long idle sessions.
                this.log('[FLOSC] Chat failed, refreshing nonce and retrying:', firstErr.message);
                await this.refreshNonce().catch(() => {});
                response = await this.callAPI(message, ivrGuidance, { allowSessionAutoCreate: true });
            }
            this.hideTyping();
            
            if (response) {
                // v3.0.5: Extract [ACTION:...] tags from AI response (for AI-interpretation offer triggers)
                const { cleanText, actions } = this._extractActionTags(response);
                
                // v8.1.0: Convert markdown to HTML before rendering — AI returns raw markdown
                const htmlText = this.formatMarkdown(cleanText);
                const msgEl = this.addMessage('assistant', htmlText, true);
                if (msgEl && ivrGuidance?.name) msgEl.setAttribute('data-message-name', ivrGuidance.name);
                
                // v1.9.0: Admin feedback buttons — flag bad or praise good AI responses
                if (msgEl && this.user?.isAdmin) {
                    this.addAdminFeedbackButtons(msgEl, message, cleanText);
                }
                
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', cleanText);
                }
                
                // v1.9.3: Execute IVR action AFTER AI response (e.g., open_quiz)
                // The action is structural (triggers quiz, opens panel) — not content.
                // v2.0.8 FIX: Skip action when executeActions is false (prevents infinite loop
                // from requestFreeLesson → sendMessage → IVR action → requestFreeLesson)
                if (ivrGuidance?.action && executeActions) {
                    this.log('FLOSC: IVR action triggered (via AI):', ivrGuidance.action);
                    this.performIVRAction(ivrGuidance.action);
                }
                
                // v3.0.5: Execute AI-embedded action tags (e.g., [ACTION:show_offer_full_access])
                if (actions.length > 0 && executeActions) {
                    for (const action of actions) {
                        this.log('[FLOSC-OFFER] AI action tag:', action);
                        this.performIVRAction(action);
                    }
                }
                
                this.floscShowUserAutoPrompts();
                // Server may have set session.title from first user message (placeholder "New Chat").
                if (this.state !== 'visitor' && this.currentSession) {
                    this.loadSessions();
                }
            } else {
                // AI returned nothing — fall back to raw IVR if available
                // v8.0.0: Try ivrGuidance first, then ivrMatch (even if conditions didn't pass)
                // as a last resort. The action (show_quiz_results, open_lesson_library) is still
                // useful even when conditions like is_member aren't met.
                const fallback = ivrGuidance || ivrMatch;
                if (fallback) {
                    const content = this.replaceVariables(fallback.content);
                    this.addMessage('assistant', content);
                    if (fallback.action && executeActions) this.performIVRAction(fallback.action);
                } else {
                    this.addMessage('assistant', this.formatChatFailureMessage(null));
                }
            }
        } catch (error) {
            this.logError('FLOSC: API error:', error);
            this.hideTyping();

            if (this.state === 'visitor' && error?.floscCode === 'visitor_tokens_depleted') {
                this.syncVisitorTokenBalanceFromPayload(error?.floscPayload || null);
                this.handleVisitorTokensDepleted(error.message || 'This session has run out of chat tokens.');
                return;
            }

            // On error, fall back to raw IVR if we had a match
            const fallback = ivrGuidance || ivrMatch;
            if (fallback) {
                const content = this.replaceVariables(fallback.content);
                this.addMessage('assistant', content);
                if (fallback.action && executeActions) this.performIVRAction(fallback.action);
            } else {
                this.addMessage('assistant', this.formatChatFailureMessage(error));
            }
        }
    }

    formatChatFailureMessage(error) {
        const raw = String(error?.message || '').trim();
        if (raw && raw !== 'Failed to fetch') {
            return raw;
        }
        return "I'm having trouble responding right now. Please try again.";
    }

    handleVisitorTokensDepleted(depletedMessage) {
        const content = String(depletedMessage || '').trim();
        if (content) {
            this.addMessage('assistant', this.formatMarkdown(content), true);
            this.saveVisitorMessage('assistant', content);
        }

        const depletedMode = String(this.config?.visitorTokenDisplay?.depletedContactMode || 'message');
        if (depletedMode !== 'in_chat_form') {
            this.visitorDepletedState.awaitingContactDetails = true;
            this.visitorDepletedState.inputLocked = false;
            return;
        }

        this.visitorDepletedState.awaitingContactDetails = false;
        this.visitorDepletedState.inputLocked = true;
        this.visitorDepletedState.formRenderedAt = Math.floor(Date.now() / 1000);
        this.visitorDepletedState.formSubmitted = false;

        if (this.chatInput) {
            this.chatInput.value = '';
            this.chatInput.disabled = true;
            this.chatInput.blur();
        }
        if (this.sendBtn) {
            this.sendBtn.disabled = true;
        }

        this.renderVisitorDepletedContactForm();
    }

    renderVisitorDepletedContactForm(options = {}) {
        if (document.getElementById('flosc_depleted_contact_form')) {
            return;
        }

        const labels = this.config?.visitorTokenDisplay?.depletedContactLabels || {};
        const title = this.escapeHtml(String(labels.title || 'Request Guest Account').trim());
        const intro = this.escapeHtml(String(labels.intro || 'To continue this conversation, you can request a Guest account from the site operator. Share what you are interested in, and an administrator will respond by email.').trim());
        const submitText = this.escapeHtml(String(labels.submitText || 'Request Guest Account').trim());

        // Stamp render time so the server's min-submit-seconds timing trap measures
        // render->submit elapsed correctly. Without this, rendered_at defaults to
        // submit time (~0s elapsed) and every submission is rejected as "too fast".
        this.visitorDepletedState = this.visitorDepletedState || {};
        this.visitorDepletedState.formRenderedAt = Math.floor(Date.now() / 1000);
        this.visitorDepletedState.formSubmitted = false;

        const formHtml = `
            <div class="flosc-depleted-form-wrap">
                <h4>${title}</h4>
                <p class="flosc-depleted-form-intro">${intro}</p>
                <form id="flosc_depleted_contact_form" class="flosc-depleted-contact-form" novalidate>
                    <input type="text" name="first_name" placeholder="First Name" required maxlength="80" autocomplete="given-name">
                    <input type="text" name="last_name" placeholder="Last Name" required maxlength="80" autocomplete="family-name">
                    <input type="text" name="email" placeholder="Email Address" required maxlength="190" inputmode="email" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
                    <input type="text" name="phone" placeholder="Phone Number" required maxlength="40" inputmode="tel" autocomplete="tel">
                    <textarea name="message" placeholder="Message" required rows="7" maxlength="4000"></textarea>
                    <input type="text" name="company" class="flosc-depleted-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="flosc-depleted-form-actions">
                        <button type="submit" data-flosc-guest="0" class="flosc-depleted-submit-contact">Submit Contact Request</button>
                        <button type="submit" data-flosc-guest="1" class="flosc-depleted-submit">${submitText}</button>
                    </div>
                    <p class="flosc-depleted-form-status" id="flosc_depleted_form_status"></p>
                </form>
            </div>
        `;

        this.addMessage('assistant', formHtml, true);
        // Persist a marker so a refresh can re-render the form (non-destructive).
        // On restore we call this with persistMarker:false to avoid stacking markers.
        if (options.persistMarker !== false) {
            this.saveVisitorMessage('assistant', '[CONTACT_FORM_RENDERED]');
        }

        const form = document.getElementById('flosc_depleted_contact_form');
        if (!form) return;

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (this.visitorDepletedState.formSubmitted) {
                return;
            }

            // Which button was pressed: "Request Guest Account" (guest=1) queues a
            // guest-account request for admin approval; "Submit Contact Request"
            // (guest=0) forwards the message to the site operator.
            const requestGuest = !!(event.submitter && event.submitter.dataset && event.submitter.dataset.floscGuest === '1');

            const statusEl = document.getElementById('flosc_depleted_form_status');
            const payload = {
                first_name: String(form.elements.first_name?.value || '').trim(),
                last_name: String(form.elements.last_name?.value || '').trim(),
                email: String(form.elements.email?.value || '').trim(),
                phone: String(form.elements.phone?.value || '').trim(),
                message: String(form.elements.message?.value || '').trim(),
                company: String(form.elements.company?.value || '').trim(),
                rendered_at: this.visitorDepletedState.formRenderedAt || Math.floor(Date.now() / 1000),
            };

            if (!payload.first_name || !payload.last_name || !payload.email || !payload.phone || !payload.message) {
                if (statusEl) statusEl.textContent = 'Please complete all fields.';
                return;
            }

            if (statusEl) statusEl.textContent = 'Sending...';
            this.showTyping();

            try {
                const result = await this.callAPI(requestGuest ? '[GUEST ACCOUNT REQUEST SUBMIT]' : '[CONTACT REQUEST SUBMIT]', null, {
                    sessionEndContactFormSubmit: true,
                    contactFormPayload: payload,
                    requestGuestAccount: requestGuest,
                    returnPayload: true,
                });

                const fallbackThanks = requestGuest
                    ? 'Your guest account request has been sent — an administrator will review it and email you a link.'
                    : 'Your message has been sent to the site operator, thank you!';
                const thanksMessage = String(result?.message || fallbackThanks).trim();
                const successHtml = `<div class="flosc-depleted-success-notice">${this.escapeHtml(thanksMessage)}</div>`;
                this.addMessage('assistant', successHtml, true);
                this.saveVisitorMessage('assistant', thanksMessage);

                this.visitorDepletedState.formSubmitted = true;
                Array.from(form.elements).forEach((el) => {
                    if (el && typeof el.disabled !== 'undefined') {
                        el.disabled = true;
                    }
                });
                // Prevent this submitted form from blocking future depletion cycles.
                form.removeAttribute('id');

                const redirectUrl = String(result?.session_end?.redirect_url || '').trim();
                this.lockVisitorChatInputAfterDepletion(redirectUrl);
                if (statusEl) statusEl.textContent = '';
            } catch (err) {
                this.logWarn('[FLOSC] Guest account request submit failed:', err);
                if (statusEl) statusEl.textContent = 'Please check your details and try again.';
            } finally {
                this.hideTyping();
            }
        });
    }

    lockVisitorChatInputAfterDepletion(redirectUrl = '') {
        this.visitorDepletedState.awaitingContactDetails = false;
        this.visitorDepletedState.inputLocked = true;

        if (this.chatInput) {
            this.chatInput.value = '';
            this.chatInput.disabled = true;
            this.chatInput.blur();
        }
        if (this.sendBtn) {
            this.sendBtn.disabled = true;
        }

        const safeRedirect = String(redirectUrl || '').trim();
        if (safeRedirect) {
            setTimeout(() => {
                window.location.href = safeRedirect;
            }, 1200);
        }
    }
    
    findIVRResponse(userMessage) {
        // MTS-2026-02-02: [FIX] Check config messages FIRST, then API messages
        // Config has {user_status_response} placeholder that triggers client-side generation
        // API may return pre-evaluated text from server (which lacks user context)
        const configMessages = Object.values(this.config.ivrMessages || {});
        const apiMessages = Object.values(this.ivr.messages || {});
        const allMessages = [...configMessages, ...apiMessages];
        
        this.log('[FLOSC-FIND] Looking for:', userMessage);
        this.log('[FLOSC-FIND] Config messages count:', configMessages.length);
        this.log('[FLOSC-FIND] API messages count:', apiMessages.length);
        
        const lowerInput = userMessage.toLowerCase().trim();

        // 1. Exact user_input match (highest priority)
        const exactMatch = allMessages.find(m => 
            m.user_input && 
            m.user_input.toLowerCase() === lowerInput
        );
        
        if (exactMatch) {
            this.log('[FLOSC-FIND] Exact match found:', exactMatch.name);
            return exactMatch;
        }

        // 2. Keyword match — check if user message matches any keyword in the message's keywords list
        // v1.9.6: Find ALL keyword matches, prefer the first whose conditions pass.
        // This prevents a strict-condition message from blocking a fallback with looser conditions.
        const keywordMatches = allMessages.filter(m => {
            if (!m.keywords) return false;
            const keywords = Array.isArray(m.keywords) 
                ? m.keywords.map(k => k.toLowerCase().trim())
                : m.keywords.toLowerCase().split(',').map(k => k.trim());
            return keywords.some(kw => kw === lowerInput || lowerInput.includes(kw));
        });

        if (keywordMatches.length > 0) {
            // Prefer the first match whose conditions pass
            const conditionMatch = keywordMatches.find(m => 
                !m.conditions || m.conditions === 'always' || this.evaluateCondition(m.conditions)
            );
            if (conditionMatch) {
                this.log('[FLOSC-FIND] Keyword match found (condition-verified):', conditionMatch.name);
                return conditionMatch;
            }
            // If no conditions pass, return first match anyway (caller handles condition check)
            this.log('[FLOSC-FIND] Keyword match found (no conditions pass):', keywordMatches[0].name);
            return keywordMatches[0];
        }

        // 3. Fuzzy word match — catch natural language variations
        // "i want to see my free lesson" should match a message with user_input "View my free lesson!"
        // Strips stop words, scores remaining words against each message's user_input + keywords
        const stopWords = new Set(['i','me','my','the','a','an','is','are','was','do','does','did','can',
            'to','for','of','in','on','it','and','or','but','not','this','that','with','have','has',
            'what','how','please','want','would','like','just','about','hey','hi','hello','ok','sure',
            'yeah','yes','no','could','should','let','lets','go','get','try','some','really','very',
            'also','well','so','if','be','been','being','at','by','from','up','out','then','than']);
        
        const inputWords = lowerInput.replace(/[^\w\s]/g, '').split(/\s+/).filter(w => w.length > 1 && !stopWords.has(w));
        
        if (inputWords.length >= 1) {
            let bestMatch = null;
            let bestScore = 0;

            for (const msg of allMessages) {
                if (!msg.user_input && !msg.keywords) continue;
                // Only match actionable messages (autoprompts/offers)
                if (msg.type !== 'suggested_user_autoprompt' && msg.type !== 'offer') continue;
                
                // Build word pool from user_input + keywords
                const pool = new Set();
                if (msg.user_input) {
                    msg.user_input.toLowerCase().replace(/[^\w\s]/g, '').split(/\s+/)
                        .filter(w => w.length > 1 && !stopWords.has(w))
                        .forEach(w => pool.add(w));
                }
                if (msg.keywords) {
                    const kws = Array.isArray(msg.keywords) ? msg.keywords : msg.keywords.split(',');
                    kws.forEach(kw => {
                        kw.toLowerCase().trim().split(/\s+/)
                            .filter(w => w.length > 1)
                            .forEach(w => pool.add(w));
                    });
                }
                
                if (pool.size === 0) continue;
                
                // Score: how many user words appear in the pool
                let score = 0;
                for (const word of inputWords) {
                    if (pool.has(word)) {
                        score += 2;
                    } else {
                        // Stem match: "lessons" matches "lesson", "viewing" matches "view" (4+ chars)
                        for (const poolWord of pool) {
                            if (word.length >= 4 && poolWord.length >= 4 && 
                                (word.startsWith(poolWord) || poolWord.startsWith(word))) {
                                score += 1;
                                break;
                            }
                        }
                    }
                }
                
                // Require minimum score (at least 2 meaningful word matches)
                if (score >= 3 && score > bestScore) {
                    bestScore = score;
                    bestMatch = msg;
                }
            }

            if (bestMatch) {
                this.log('[FLOSC-FIND] Fuzzy match found:', bestMatch.name, 'score:', bestScore);
                return bestMatch;
            }
        }

        this.log('[FLOSC-FIND] No match for:', userMessage);
        return null;
    }
    
    percentBucketClass(value, prefix = 'flosc-w') {
        const bounded = Math.max(0, Math.min(100, Number(value) || 0));
        const bucket = Math.round(bounded / 5) * 5;
        return `${prefix}-${bucket}`;
    }

    removeClassPrefix(el, prefix) {
        if (!el?.classList) return;
        Array.from(el.classList).forEach(className => {
            if (className.startsWith(prefix)) {
                el.classList.remove(className);
            }
        });
    }

    /**
     * Show/hide without inline styles or !important.
     * - Hidden: class flosc-hidden (CSS: display:none)
     * - Shown: remove flosc-hidden; set data-flosc-display so layout CSS can
     *   apply block/flex/inline-flex with normal cascade specificity.
     */
    setDisplayState(el, visible, mode = 'block') {
        if (!el) return;

        // Drop legacy force-show classes (pre data-flosc-display).
        el.classList.remove('flosc-visible', 'flosc-visible-flex', 'flosc-visible-inline-flex', 'show');

        if (visible) {
            el.classList.remove('flosc-hidden');
            const layout = (mode === 'flex' || mode === 'inline-flex' || mode === 'block') ? mode : 'block';
            el.setAttribute('data-flosc-display', layout);
        } else {
            el.classList.add('flosc-hidden');
            el.removeAttribute('data-flosc-display');
        }
    }

    applyDynamicStyleTokens(scope = document) {
        const root = scope && scope.querySelectorAll ? scope : document;

        root.querySelectorAll('[data-score]').forEach(el => {
            this.removeClassPrefix(el, 'flosc-ring-');
            el.classList.remove('flosc-score-ring-theme');
            el.classList.add('flosc-score-ring-solid');
            el.classList.add(this.percentBucketClass(el.dataset.score, 'flosc-ring'));
        });

        root.querySelectorAll('[data-score-percent]').forEach(el => {
            this.removeClassPrefix(el, 'flosc-ring-');
            el.classList.remove('flosc-score-ring-solid');
            el.classList.add('flosc-score-ring-theme');
            el.classList.add(this.percentBucketClass(el.dataset.scorePercent, 'flosc-ring'));
        });

        root.querySelectorAll('[data-bar-width]').forEach(el => {
            this.removeClassPrefix(el, 'flosc-w-');
            el.classList.add(this.percentBucketClass(el.dataset.barWidth, 'flosc-w'));
        });

        root.querySelectorAll('[data-progress-percent]').forEach(el => {
            this.removeClassPrefix(el, 'flosc-w-');
            el.classList.add(this.percentBucketClass(el.dataset.progressPercent, 'flosc-w'));
        });

        root.querySelectorAll('.flosc-sso-btn[data-provider]').forEach(el => {
            this.removeClassPrefix(el, 'flosc-sso-provider-');
            const provider = String(el.dataset.provider || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
            if (provider) {
                el.classList.add(`flosc-sso-provider-${provider}`);
            }
        });
    }

    addMessage(role, content, isHtml = false) {
        this.log('[FLOSC] addMessage() called:', {role, contentLength: content?.length, isHtml});

        // Never show the AI-history engagement label in the user chat pane.
        if (role === 'assistant' && typeof content === 'string') {
            content = this._stripEngagementAdminLabel
                ? this._stripEngagementAdminLabel(content)
                : content.replace(/^\[Admin engagement message\]\s*/i, '');
        }

        const contentStr = String(content || '');
        // Guardrail: never render internal synthetic prompts as visible user chat.
        if (role === 'user' && /^\s*\[SYSTEM:/i.test(contentStr)) {
            this.logWarn('[FLOSC] Skipping synthetic SYSTEM user bubble');
            return null;
        }

        // Duplicate plain-text assistant lines: skip re-render (do not substitute unrelated copy).
        if (role !== 'user' && !isHtml) {
            if (contentStr.includes('flosc-sandbox-payment') || contentStr.includes('flosc-offer-')) {
                isHtml = true;
            }
        }
        if (role !== 'user' && !isHtml) {
            this._shownAssistant = this._shownAssistant || {};
            let repKey = String(content || '').replace(/\s+/g, ' ').trim();
            // Collapse "Welcome back!" / "Welcome back." / "Welcome back..." into one key.
            const welcomeKey = repKey.toLowerCase().replace(/[.!…]+$/u, '').trim();
            if (welcomeKey === 'welcome back' || welcomeKey.startsWith('welcome back')) {
                const shortWelcome = welcomeKey === 'welcome back' || /^welcome back(\s|$)/.test(welcomeKey);
                if (shortWelcome && welcomeKey.length < 80) {
                    repKey = '__welcome_back__';
                }
            }
            if (repKey && this._shownAssistant[repKey]) {
                this.log('[FLOSC] Skipping duplicate assistant message');
                return null;
            }
            if (repKey) {
                this._shownAssistant[repKey] = true;
            }
        }

        if (!this.chatMessages) {
            this.logError('[FLOSC] ERROR: chatMessages container not found!');
            return null;
        }

        this.log('[FLOSC] Creating message element...');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        messageDiv.classList.add(role === 'user' ? 'flosc-message--user' : 'flosc-message--bot');

        const userInitial = this.user?.name?.charAt(0).toUpperCase() || 'U';

        if (role === 'user') {
            messageDiv.innerHTML = `
                <div class="message-avatar">${userInitial}</div>
                <div class="message-content">
                    <div class="message-text">${this.escapeHtml(content)}</div>
                </div>
            `;
        } else {
            // v1.9.7: Assistant messages — no avatar (Grok pattern). Just content.
            const formatted = isHtml ? content : this.formatMarkdown(content);
            messageDiv.innerHTML = `
                <div class="message-content">
                    <div class="message-text">${formatted}</div>
                </div>
            `;
        }

        this.log('[FLOSC] Appending to chatMessages container...');
        this.chatMessages.appendChild(messageDiv);
        this.applyDynamicStyleTokens(messageDiv);
        // v8.0.1: For assistant messages, inject native players beneath media links.
        if (role !== 'user') { this._embedMedia(messageDiv); }

        // v9.3.5: Reliable auto-scroll using double rAF to ensure DOM update completes
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            });
        });
        this.log('[FLOSC] Message added successfully');

        // v1.9.5: Hide empty-state elements when USER sends a message (Grok pattern).
        // Landing state stays visible during welcome/auto messages — only the user's
        // first typed message transitions to full chat mode.
        if (role === 'user') {
            const landing = document.getElementById('landingState');
            if (landing && !landing.classList.contains('flosc-hidden')) {
                landing.classList.add('flosc-hidden');
            }
            const greeting = document.getElementById('greeting');
            if (greeting && !greeting.classList.contains('flosc-hidden')) {
                greeting.classList.add('flosc-hidden');
            }
            const pills = document.getElementById('flosc_input_user_autoprompts_panel');
            if (pills && !pills.classList.contains('flosc-hidden')) {
                pills.classList.add('flosc-hidden');
            }
        }

        // v8.0.9: Return element so caller can add attributes
        return messageDiv;
    }

    /**
     * v1.9.0: Add admin feedback buttons (flag + praise) to an AI message.
     * Only shown to admin users on hover.
     */
    addAdminFeedbackButtons(messageEl, userMessage, aiResponse) {
        const contentDiv = messageEl.querySelector('.message-content');
        if (!contentDiv) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'flosc-admin-feedback';

        const praiseBtn = document.createElement('button');
        praiseBtn.className = 'flosc-praise-btn';
        praiseBtn.title = 'Praise this response — keep doing this';
        praiseBtn.innerHTML = '✓';
        praiseBtn.addEventListener('click', () => {
            this.showPraiseModal(userMessage, aiResponse);
        });

        const flagBtn = document.createElement('button');
        flagBtn.className = 'flosc-feedback-flag';
        flagBtn.title = 'Flag this response for improvement';
        flagBtn.innerHTML = '⚑';
        flagBtn.addEventListener('click', () => {
            this.showFeedbackModal(userMessage, aiResponse);
        });

        wrapper.appendChild(praiseBtn);
        wrapper.appendChild(flagBtn);
        contentDiv.appendChild(wrapper);
    }

    /**
     * v1.9.0: Show a modal for admin to submit a feedback for a bad AI response.
     */
    showFeedbackModal(userMessage, aiResponse) {
        // Remove any existing modal
        const existing = document.getElementById('flosc-feedback-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'flosc-feedback-modal';
        modal.className = 'flosc-feedback-modal-overlay';
        modal.innerHTML = `
            <div class="flosc-feedback-modal">
                <div class="flosc-feedback-modal-header">
                    <h3>Flag AI Response</h3>
                    <button class="flosc-feedback-modal-close">&times;</button>
                </div>
                <div class="flosc-feedback-modal-body">
                    <div class="flosc-feedback-field">
                        <label>User said:</label>
                        <div class="flosc-feedback-preview">${this.escapeHtml(userMessage)}</div>
                    </div>
                    <div class="flosc-feedback-field">
                        <label>AI responded:</label>
                        <div class="flosc-feedback-preview flosc-feedback-bad">${this.escapeHtml(aiResponse.substring(0, 500))}</div>
                    </div>
                    <div class="flosc-feedback-field">
                        <label for="flosc-feedback-note">What was wrong? <span class="required">*</span></label>
                        <textarea id="flosc-feedback-note" rows="3" placeholder="e.g. Too pushy, wrong information, off-topic..."></textarea>
                    </div>
                    <div class="flosc-feedback-field">
                        <label for="flosc-feedback-preferred">How should the AI have responded? <span class="optional">(optional)</span></label>
                        <textarea id="flosc-feedback-preferred" rows="3" placeholder="Write the ideal response here..."></textarea>
                    </div>
                </div>
                <div class="flosc-feedback-modal-footer">
                    <button class="flosc-feedback-cancel">Cancel</button>
                    <button class="flosc-feedback-submit">Save Feedback</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('.flosc-feedback-modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.flosc-feedback-cancel').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        // Submit handler
        modal.querySelector('.flosc-feedback-submit').addEventListener('click', async () => {
            const note = document.getElementById('flosc-feedback-note').value.trim();
            const preferred = document.getElementById('flosc-feedback-preferred').value.trim();

            if (!note) {
                document.getElementById('flosc-feedback-note').focus();
                return;
            }

            const submitBtn = modal.querySelector('.flosc-feedback-submit');
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;

            try {
                const res = await this.authFetch(this.config.apiUrl + '/feedback', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    body: JSON.stringify({
                        user_message: userMessage,
                        bad_response: aiResponse,
                        admin_note: note,
                        preferred_response: preferred,
                        flow_id: this.config.ivrFile || this.config.flowId
                    })
                });

                const data = await res.json();

                if (data.success || res.ok) {
                    submitBtn.textContent = 'Saved ✓';
                    setTimeout(() => modal.remove(), 800);
                } else {
                    submitBtn.textContent = 'Error — try again';
                    submitBtn.disabled = false;
                }
            } catch (err) {
                this.logError('[FLOSC] Feedback save error:', err);
                submitBtn.textContent = 'Error — try again';
                submitBtn.disabled = false;
            }
        });
    }
    
    /**
     * v1.9.0: Show a modal for admin to praise a good AI response.
     */
    showPraiseModal(userMessage, aiResponse) {
        const existing = document.getElementById('flosc-feedback-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'flosc-feedback-modal';
        modal.className = 'flosc-feedback-modal-overlay';
        modal.innerHTML = `
            <div class="flosc-feedback-modal">
                <div class="flosc-feedback-modal-header flosc-praise-header">
                    <h3>Praise AI Response</h3>
                    <button class="flosc-feedback-modal-close">&times;</button>
                </div>
                <div class="flosc-feedback-modal-body">
                    <div class="flosc-feedback-field">
                        <label>User said:</label>
                        <div class="flosc-feedback-preview">${this.escapeHtml(userMessage)}</div>
                    </div>
                    <div class="flosc-feedback-field">
                        <label>AI responded:</label>
                        <div class="flosc-feedback-preview flosc-praise-good">${this.escapeHtml(aiResponse.substring(0, 500))}</div>
                    </div>
                    <div class="flosc-feedback-field">
                        <label for="flosc-praise-note">What was good about this? <span class="required">*</span></label>
                        <textarea id="flosc-praise-note" rows="3" placeholder="e.g. Perfect tone, great explanation, helpful and not pushy..."></textarea>
                    </div>
                </div>
                <div class="flosc-feedback-modal-footer">
                    <button class="flosc-feedback-cancel">Cancel</button>
                    <button class="flosc-feedback-submit flosc-praise-submit-btn">Save Praise</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelector('.flosc-feedback-modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.flosc-feedback-cancel').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        modal.querySelector('.flosc-feedback-submit').addEventListener('click', async () => {
            const note = document.getElementById('flosc-praise-note').value.trim();

            if (!note) {
                document.getElementById('flosc-praise-note').focus();
                return;
            }

            const submitBtn = modal.querySelector('.flosc-feedback-submit');
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;

            try {
                const res = await this.authFetch(this.config.apiUrl + '/praises', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    body: JSON.stringify({
                        user_message: userMessage,
                        good_response: aiResponse,
                        admin_note: note,
                        flow_id: this.config.ivrFile || this.config.flowId
                    })
                });

                const data = await res.json();

                if (data.success || res.ok) {
                    submitBtn.textContent = 'Saved ✓';
                    setTimeout(() => modal.remove(), 800);
                } else {
                    submitBtn.textContent = 'Error — try again';
                    submitBtn.disabled = false;
                }
            } catch (err) {
                this.logError('[FLOSC] Praise save error:', err);
                submitBtn.textContent = 'Error — try again';
                submitBtn.disabled = false;
            }
        });
    }

    showTyping() {
        const typing = document.getElementById('flosc_output_chat_typing_indicator');
        if (typing) {
            typing.classList.add('show');
            // v8.0.1: Auto-scroll so typing indicator is visible
            requestAnimationFrame(() => {
                if (this.chatMessages) {
                    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
                }
            });
        }
    }

    hideTyping() {
        const typing = document.getElementById('flosc_output_chat_typing_indicator');
        if (typing) {
            typing.classList.remove('show');
        }
    }
    
    async callAPI(message, ivrMatch = null, options = {}) {
        const isSystemGenerated = /^\s*\[SYSTEM:/i.test(String(message || ''));
        const allowSessionAutoCreate = options && options.allowSessionAutoCreate === true;

        // Auto-create session on first message for logged-in users (server enforces guest max).
        // Hard rule: create sessions only from explicit user send/new-chat flows.
        if (this.state !== 'visitor' && !this.currentSession && !isSystemGenerated && allowSessionAutoCreate) {
            try {
                const sessionRes = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    // New session title is always "New Chat"; server renames on first user message.
                    body: JSON.stringify(this.sessionsFlowBody())
                });
                const sessionData = await sessionRes.json();
                if (sessionData.success && sessionData.session) {
                    this.currentSession = sessionData.session;
                    this.rememberActiveChatSessionId(this.currentSession.id);
                    this.log('[FLOSC] Auto-created session:', this.currentSession.id, this.currentSession.title);
                    this.loadSessions();
                } else if (sessionData.code === 'guest_chat_limit' || sessionData.error === 'guest_chat_limit') {
                    const msg = sessionData.message || this.formatChatListMessage(
                        this.config.guestNewChatLimitMessage || '',
                        { max: sessionData.max, count: sessionData.count }
                    );
                    if (msg) {
                        this.addMessage('assistant', msg, false);
                    }
                }
            } catch (e) {
                this.logWarn('[FLOSC] Could not auto-create session:', e);
            }
        }

        // v1.9.3: Build payload — include IVR guidance when frontend matched
        // IVR guidance tells the backend what the scripted system wants to communicate.
        // The backend's Chatpack system wraps this as context for AI to rewrite naturally.
        const payload = {
            message: message,
            // Logged-in users use their server session; visitors use a persistent
            // local session id so the concierge desk has a stable, per-conversation
            // key (and restartChat() can mint a new one for a fresh conversation).
            session_id: this.currentSession?.id || (this.state === 'visitor' ? this.getVisitorSessionId() : undefined),
            context: this.ivr.context,
            // v1.3.7: Flow context for multi-flow support
            flow_id: this.config.flowId,
            ivr_file: this.config.ivrFile
        };

        if (options && options.sessionEndContactCapture) {
            payload.session_end_contact_capture = true;
        }
        if (options && options.sessionEndContactFormSubmit) {
            payload.session_end_contact_form_submit = true;
            payload.contact_form = options.contactFormPayload || {};
            payload.request_guest_account = options.requestGuestAccount ? 1 : 0;
        }
        
        // v2.0.7: Send visitor conversation history so AI has memory across messages.
        // Visitors have no server-side session, so we send localStorage history.
        // This prevents AI from repeating itself and enables conversation-awareness.
        if (this.state === 'visitor') {
            try {
                const visitorMsgs = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
                // Send last 10 messages (5 pairs) to keep payload small
                payload.visitor_history = visitorMsgs.slice(-10).map(m => ({
                    role: m.role,
                    content: m.role === 'assistant' 
                        ? m.content.replace(/<[^>]*>/g, '').substring(0, 500) 
                        : m.content
                }));
            } catch (e) {
                this.logWarn('[FLOSC] Could not load visitor history:', e);
            }
        }

        // Engagement tab: tell the AI about admin-inserted chat messages (content + that admin wrote them).
        // Kept in sessionStorage for the browser session so subsequent replies always know.
        if (!Array.isArray(this._engagementAiNotes)) {
            this._loadEngagementAiNotes();
        }
        if (Array.isArray(this._engagementAiNotes) && this._engagementAiNotes.length) {
            payload.engagement_context = this._engagementAiNotes.slice(-5).map((n) => ({
                rule_id: String(n.rule_id || '').substring(0, 64),
                content: String(n.content || '').substring(0, 500),
            }));
        }
        
        // v1.9.3: When frontend finds an IVR match, send the guidance to the backend
        // so AI can use it as the basis for its response (instead of the backend
        // re-running its own IVR match which may lack client-side session context)
        if (ivrMatch) {
            payload.ivr_guidance = this.replaceVariables(ivrMatch.content);
            payload.ivr_message_name = ivrMatch.name || null;
        }

        // MTS-2026-02-02: [AUTH-FIX] credentials: 'same-origin' is REQUIRED
        // Without it, browser does not send cookies with the request.
        // WordPress REST API needs cookies + nonce to authenticate users.
        // This was the root cause of admin showing as "Visitor".
        const response = await this.authFetch(this.config.apiUrl + '/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.config.nonce
            },
            body: JSON.stringify(payload)
        });

        let data = {};
        try {
            data = await response.json();
        } catch (parseErr) {
            const err = new Error(`Server error (${response.status})`);
            err.floscCode = 'invalid_json';
            err.floscPayload = null;
            throw err;
        }

        // Keep the visitor token label in sync even when the API returns an error payload.
        this.syncVisitorTokenBalanceFromPayload(data);

        if (!response.ok) {
            const errorMsg = data.error || data.message || `Server error (${response.status})`;
            const err = new Error(errorMsg);
            err.floscCode = String(data.error_code || data.code || '');
            err.floscPayload = data;
            throw err;
        }

        if (!data.success) {
            const errorMsg = data.error || data.message || 'Unknown API error';
            const err = new Error(errorMsg);
            err.floscCode = String(data.error_code || '');
            err.floscPayload = data;
            throw err;
        }

        this.syncVisitorTokenBalanceFromPayload(data);

        return options && options.returnPayload ? data : data.message;
    }
    
    saveVisitorMessage(role, content, meta = null) {
        try {
            const contentStr = String(content || '');
            if (role === 'user' && /^\s*\[SYSTEM:/i.test(contentStr)) {
                return;
            }
            const messages = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
            const entry = { role, content, timestamp: Date.now() };
            if (meta && typeof meta === 'object') {
                entry.meta = {
                    source: meta.source || '',
                    name: meta.name || '',
                };
            }
            messages.push(entry);
            if (messages.length > 50) messages.shift();
            localStorage.setItem(this.flowStorageKey('flosc_visitor_messages'), JSON.stringify(messages));
        } catch (e) {
            this.logWarn('FLOSC: Could not save visitor message', e);
        }
    }
    
    restoreVisitorMessages() {
        try {
            const messages = JSON.parse(localStorage.getItem(this.flowStorageKey('flosc_visitor_messages')) || '[]');
            const normalizedMessages = [];
            let pendingStartupAssistant = null;
            let seenUserMessage = false;

            messages.forEach(msg => {
                if (!seenUserMessage && msg && msg.role === 'assistant') {
                    pendingStartupAssistant = msg;
                    return;
                }

                if (pendingStartupAssistant) {
                    normalizedMessages.push(pendingStartupAssistant);
                    pendingStartupAssistant = null;
                }

                if (msg && msg.role === 'user') {
                    seenUserMessage = true;
                }

                normalizedMessages.push(msg);
            });

            if (pendingStartupAssistant) {
                normalizedMessages.push(pendingStartupAssistant);
            }

            this._restoredVisitorMessages = normalizedMessages.length > 0;
            normalizedMessages.forEach(msg => {
                const meta = msg && msg.meta && typeof msg.meta === 'object' ? msg.meta : null;

                if (msg.role === 'assistant' && meta && meta.source === 'admin') {
                    this.renderAdminMessage(meta.name || 'Admin', msg.content || '');
                    return;
                }

                // Depleted-tokens contact form: re-render the styled form on refresh
                // (non-destructive) instead of printing the raw marker text.
                if (msg.role === 'assistant' && String(msg.content || '').trim() === '[CONTACT_FORM_RENDERED]') {
                    this.renderVisitorDepletedContactForm({ persistMarker: false });
                    return;
                }

                if (msg.role === 'assistant') {
                    // Saved content may already contain HTML (badge images, <strong>, etc.)
                    // Check for HTML tags — if present, pass through as-is; otherwise format markdown
                    const hasHtml = /<[a-z][\s\S]*>/i.test(msg.content);
                    if (hasHtml) {
                        this.addMessage(msg.role, msg.content, true);
                    } else {
                        this.addMessage(msg.role, this.formatMarkdown(msg.content), true);
                    }
                } else {
                    this.addMessage(msg.role, msg.content, false);
                }
            });
        } catch (e) {
            this.logWarn('FLOSC: Could not restore visitor messages', e);
        }
    }
    
    /** Query string for session APIs — always scoped to this flow. */
    sessionsQuery() {
        const params = new URLSearchParams();
        if (this.config.flowId) {
            params.set('flow_id', this.config.flowId);
        }
        if (this.config.ivrFile) {
            params.set('ivr_file', this.config.ivrFile);
        }
        const q = params.toString();
        return q ? `?${q}` : '';
    }

    /** Body fields so create/rename/delete stay on this flow. */
    sessionsFlowBody(extra = {}) {
        return {
            ...extra,
            flow_id: this.config.flowId || '',
            ivr_file: this.config.ivrFile || '',
        };
    }

    async loadSessions() {
        try {
            const response = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (data.success && this.sessionList) {
                this.renderSessions(data.sessions);
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not load sessions', e);
        }
    }
    
    renderSessions(sessions) {
        if (!this.sessionList) return;
        
        // Visitors: never see sidebar sessions (loadSessions not called)
        // Guests: list + rename/delete from flow params (defaults true)
        // Members/Admins: full manage
        const canRename = this.state === 'member' || this.state === 'admin'
            || (this.state === 'guest' && this.config.guestCanRenameChats !== false);
        const canDelete = this.state === 'member' || this.state === 'admin'
            || (this.state === 'guest' && this.config.guestCanDeleteChats !== false);
        
        // v1.7.0: sessions is a grouped object {today:[], yesterday:[], last_7_days:[], older:[]}
        const groupLabels = {
            today: 'Today',
            yesterday: 'Yesterday', 
            last_7_days: 'Previous 7 Days',
            older: 'Older'
        };
        
        let html = '';
        for (const [group, items] of Object.entries(sessions)) {
            if (!Array.isArray(items) || items.length === 0) continue;
            html += `<div class="flosc-session-group">
                <div class="flosc-session-group-title">${groupLabels[group] || group}</div>`;
            items.forEach(s => {
                const isActive = String(s.id) === String(this.currentSession?.id ?? '');
                html += `<div class="flosc-session-item ${isActive ? 'active' : ''}" 
                     data-session-id="${s.id}">
                    <span class="flosc-session-item-icon">💬</span>
                    <span class="flosc-session-item-title">${this.escapeHtml(s.title || 'New Chat')}</span>`;
                if (canRename || canDelete) {
                    html += `<span class="flosc-session-actions">`;
                    if (canRename) {
                        html += `<button class="flosc-session-action flosc-session-rename" data-session-id="${s.id}" title="Rename">✏️</button>`;
                    }
                    if (canDelete) {
                        html += `<button class="flosc-session-action flosc-session-delete" data-session-id="${s.id}" title="Delete">🗑️</button>`;
                    }
                    html += `</span>`;
                }
                html += `</div>`;
            });
            html += '</div>';
        }
        
        const emptyMsg = this.config.emptyChatListMessage || 'No chats yet';
        this.sessionList.innerHTML = html || `<div class="flosc-session-empty">${this.escapeHtml(emptyMsg)}</div>`;
        
        this.sessionList.querySelectorAll('.flosc-session-item').forEach(item => {
            item.addEventListener('click', (e) => {
                if (e.target.closest('.flosc-session-action')) return;
                this.loadSession(item.dataset.sessionId);
            });
        });

        this.sessionList.querySelectorAll('.flosc-session-rename').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.renameSession(btn.dataset.sessionId);
            });
        });

        this.sessionList.querySelectorAll('.flosc-session-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteSession(btn.dataset.sessionId);
            });
        });
    }
    
    async loadSession(sessionId) {
        try {
            const response = await this.authFetch(
                this.config.apiUrl + '/sessions/' + sessionId + this.sessionsQuery(),
                {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (data.success && data.session) {
                this.currentSession = data.session;
                this.rememberActiveChatSessionId(data.session?.id || sessionId);
                // Clear chat and show session messages
                const inner = this.chatMessages?.querySelector('.messages-inner');
                if (inner) inner.innerHTML = '';
                else if (this.chatMessages) this.chatMessages.innerHTML = '';

                // History replay must not hit anti-repetition substitutes.
                this._shownAssistant = {};
                this._repeatIdx = 0;
                
                const msgs = Array.isArray(data.session.messages) ? data.session.messages : [];
                msgs.forEach(msg => {
                    const meta = msg && msg.meta && typeof msg.meta === 'object' ? msg.meta : null;

                    if (msg.role === 'assistant' && meta && meta.source === 'admin') {
                        this.renderAdminMessage(meta.name || 'Admin', msg.content || '');
                        return;
                    }

                    let content = msg.content || '';
                    // Never show the AI-history label in the chat UI.
                    if (msg.role === 'assistant') {
                        content = this._stripEngagementAdminLabel(content);
                    }
                    if (msg.role === 'assistant' && !content) {
                        return;
                    }
                    this.addMessage(msg.role, content);
                });
                
                // Update active state in sidebar
                this.sessionList?.querySelectorAll('.flosc-session-item').forEach(item => {
                    item.classList.toggle('active', item.dataset.sessionId == sessionId);
                });
                
                // Close sidebar on mobile after selection
                if (window.innerWidth <= 768 && this.sidebar) {
                    this.sidebar.classList.remove('open');
                }
                return true;
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not load session', e);
        }
        return false;
    }
    
    /**
     * Substitute placeholders in flow-param chat strings.
     * Supports {NickName}, {name}, {email}, {flow_name}, {max}, {count}.
     */
    formatChatListMessage(template, extra = {}) {
        const raw = String(template || '');
        const nick = String(
            this.user?.displayName
            || this.user?.name
            || this.user?.firstName
            || this.config.userName
            || 'there'
        ).trim() || 'there';
        const email = String(this.user?.email || this.config.userEmail || '');
        const flowName = String(this.config.flowDisplayName || this.config.productName || 'FLOSC');
        const map = {
            '{NickName}': nick,
            '{name}': nick,
            '{email}': email,
            '{flow_name}': flowName,
            '{max}': String(extra.max ?? this.config.guestMaxChats ?? ''),
            '{count}': String(extra.count ?? ''),
        };
        let out = raw;
        Object.keys(map).forEach((key) => {
            out = out.split(key).join(map[key]);
        });
        return out;
    }

    getNewChatWelcomeFallback() {
        // Flow-specific welcome text belongs in IVR / settings, not product hardcode.
        if (this.state === 'member' || this.state === 'admin') {
            return 'Hi {NickName}, glad to be chatting with you! What would you like to work on in this session?';
        }
        return 'Welcome back, what would you like to work on?';
    }

    countSessionsFromGrouped(sessions) {
        if (!sessions || typeof sessions !== 'object') return 0;
        let n = 0;
        Object.values(sessions).forEach((items) => {
            if (Array.isArray(items)) n += items.length;
        });
        return n;
    }

    async newSession() {
        // Visitor conversations are persisted in localStorage and shared across
        // companion/full-page surfaces. Route visitor "New chat" through the
        // restart path so history + visitor session id are rotated consistently.
        // Visitors never get multi-chat management.
        if (this.state === 'visitor') {
            this.restartChat();
            return;
        }

        // Guest: enforce max chats (0 = unlimited) before creating.
        if (this.state === 'guest') {
            const max = Math.max(0, parseInt(this.config.guestMaxChats, 10) || 0);
            if (max > 0) {
                let count = 0;
                try {
                    const res = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': this.config.nonce },
                    });
                    const data = await res.json();
                    if (data.success) {
                        count = this.countSessionsFromGrouped(data.sessions);
                    }
                } catch (e) {
                    this.logWarn('FLOSC: Could not count sessions before new chat', e);
                }
                if (count >= max) {
                    const limitTpl = this.config.guestNewChatLimitMessage
                        || 'Your guest account allows {max} chats listed below. If you would like to start a new chat, you can delete one below.';
                    const msg = this.formatChatListMessage(limitTpl, { max, count });
                    this.addMessage('assistant', msg, false);
                    return;
                }
            }
        }

        // Create a real server session first. Do not clear the open pane until create succeeds.
        // New chat = new session; title always "New Chat" until first user message / rename.
        try {
            await this.refreshNonce();
            const res = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(this.sessionsFlowBody()),
            });
            const data = await res.json();
            if (!data.success || !data.session) {
                if (data.code === 'guest_chat_limit' || data.error === 'guest_chat_limit') {
                    const msg = data.message || this.formatChatListMessage(
                        this.config.guestNewChatLimitMessage || '',
                        { max: data.max, count: data.count }
                    );
                    this.addMessage('assistant', msg, false);
                    return;
                }
                this.logWarn('FLOSC: create session failed', data);
                this.addMessage('assistant', 'Could not start a new chat right now. Please try again.', false);
                return;
            }
            this.currentSession = data.session;
        } catch (e) {
            this.logWarn('FLOSC: create session error', e);
            this.addMessage('assistant', 'Could not start a new chat right now. Please try again.', false);
            return;
        }

        // Clear pane only after a new session exists — do not restore visitor intro / prior quiz thread.
        const inner = this.chatMessages?.querySelector('.messages-inner');
        if (inner) inner.innerHTML = '';
        else if (this.chatMessages) this.chatMessages.innerHTML = '';

        this._shownAssistant = {};
        this._repeatIdx = 0;
        this.ivr.messageCount = 0;
        this.ivr.shownThisSession = {};
        // Guests/members: not visitor first-run
        this.ivr.context.first_show_session = false;
        this.buildIVRContext();

        const welcomeTpl = (this.state === 'member' || this.state === 'admin')
            ? (this.config.memberNewChatWelcomeMessage || this.getNewChatWelcomeFallback())
            : (this.config.guestNewChatWelcomeMessage || this.getNewChatWelcomeFallback());
        const welcome = this.formatChatListMessage(welcomeTpl);
        if (welcome) {
            this.addMessage('assistant', welcome, false);
        }

        this.floscShowUserAutoPrompts();
        await this.loadSessions();

        this.sessionList?.querySelectorAll('.flosc-session-item').forEach(item => {
            item.classList.toggle('active', String(item.dataset.sessionId) === String(this.currentSession?.id));
        });

        if (window.innerWidth <= 768 && this.sidebar) {
            this.sidebar.classList.remove('open');
        }
    }

    /**
     * Ensure guest/member has a server session (new chat = new session).
     * Used when free-lesson / offer-ask bypass /chat auto-create.
     */
    async ensureServerSession() {
        if (this.state === 'visitor' || this.currentSession?.id) {
            return !!this.currentSession?.id;
        }
        try {
            await this.refreshNonce();
            const res = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(this.sessionsFlowBody()),
            });
            const data = await res.json();
            if (data.success && data.session) {
                this.currentSession = data.session;
                this.rememberActiveChatSessionId(this.currentSession.id);
                await this.loadSessions();
                return true;
            }
            if (data.code === 'guest_chat_limit' || data.error === 'guest_chat_limit') {
                const msg = data.message || this.formatChatListMessage(
                    this.config.guestNewChatLimitMessage || '',
                    { max: data.max, count: data.count }
                );
                if (msg) this.addMessage('assistant', msg, false);
            }
        } catch (e) {
            this.logWarn('FLOSC: ensureServerSession failed', e);
        }
        return false;
    }

    /**
     * When the user message never hits /chat (free-lesson / offer-ask), still set
     * session.title from the first user message if it is still "New Chat".
     */
    async syncSessionTitleFromFirstUserMessage(message) {
        if (this.state === 'visitor') return;
        await this.ensureServerSession();
        if (!this.currentSession?.id) return;

        const cur = String(this.currentSession.title || '').trim();
        if (cur && cur !== 'New Chat') return;

        const plain = String(message || '').replace(/\s+/g, ' ').trim();
        if (!plain) return;
        const title = plain.length > 40 ? plain.substring(0, 40) + '...' : plain;

        try {
            await this.refreshNonce();
            const res = await this.authFetch(
                this.config.apiUrl + '/sessions/' + this.currentSession.id + this.sessionsQuery(),
                {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify(this.sessionsFlowBody({ title })),
            });
            const data = await res.json();
            if (data.success) {
                this.currentSession.title = title;
                await this.loadSessions();
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not auto-title session', e);
        }
    }

    async renameSession(sessionId) {
        const item = this.sessionList?.querySelector(`.flosc-session-item[data-session-id="${sessionId}"]`);
        const titleEl = item?.querySelector('.flosc-session-item-title');
        const currentTitle = titleEl?.textContent || 'New Chat';
        const newTitle = prompt('Rename chat:', currentTitle);
        if (!newTitle || newTitle === currentTitle) return;

        try {
            const response = await this.authFetch(
                this.config.apiUrl + '/sessions/' + sessionId + this.sessionsQuery(),
                {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify(this.sessionsFlowBody({ title: newTitle }))
            });
            const data = await response.json();
            if (data.success) {
                if (titleEl) titleEl.textContent = newTitle;
                if (this.currentSession?.id == sessionId) {
                    this.currentSession.title = newTitle;
                }
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not rename session', e);
        }
    }

    async deleteSession(sessionId) {
        if (!confirm('Delete this chat? This cannot be undone.')) return;

        try {
            const deletingActive = String(this.currentSession?.id || '') === String(sessionId || '');
            const response = await this.authFetch(
                this.config.apiUrl + '/sessions/' + sessionId + this.sessionsQuery(),
                {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            if (data.success) {
                if (deletingActive) {
                    this.currentSession = null;
                    this.forgetRememberedActiveChatSessionId();

                    const inner = this.chatMessages?.querySelector('.messages-inner');
                    if (inner) inner.innerHTML = '';
                    else if (this.chatMessages) this.chatMessages.innerHTML = '';

                    this._shownAssistant = {};
                    this._repeatIdx = 0;
                }

                await this.loadSessions();

                if (deletingActive) {
                    await this.restoreLastSession();
                    if (!this.currentSession?.id) {
                        // No chats left: keep empty pane without creating a new server chat.
                        this.ivr.messageCount = 0;
                        this.ivr.shownThisSession = {};
                        this.ivr.lastInteraction = Date.now();

                        // Keep the pane neutral; no auto-open text after deletion.
                        this.floscShowUserAutoPrompts();
                    }
                }
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not delete session', e);
        }
    }
    
    /**
     * v1.7.0: Restore the most recent session on return visit
     * Checks if user has sessions, loads the newest one if chat area is empty
     */
    async restoreLastSession() {
        try {
            const response = await this.authFetch(this.config.apiUrl + '/sessions' + this.sessionsQuery(), {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (!data.success) return;
            
            // Only sessions for THIS flow (server filters by flow_id).
            const groups = ['today', 'yesterday', 'last_7_days', 'older'];
            let latestSession = null;
            
            for (const group of groups) {
                if (data.sessions[group] && data.sessions[group].length > 0) {
                    latestSession = data.sessions[group][0];
                    break;
                }
            }
            
            // List endpoint may omit message bodies — still load by id (server has full log).
            if (latestSession && latestSession.id) {
                this.log('[FLOSC] Restoring last session for flow:', this.config.flowId, latestSession.id, latestSession.title);
                await this.loadSession(latestSession.id);
            }
        } catch (e) {
            this.logWarn('[FLOSC] Could not restore last session:', e);
        }
    }
    
    _hasIpaPhraseResults(quizData) {
        return !!(quizData
            && quizData.quiz_type === 'ipa_audio'
            && Array.isArray(quizData.phrase_results)
            && quizData.phrase_results.length > 0);
    }

    // v8.0.13: Score 0 and phrase-only meta must still count as quiz_taken for IVR pills/offers.
    _userHasQuizTaken() {
        if (this._hasIpaPhraseResults(this.user?.lastQuizData)) {
            return true;
        }
        if (this._hasPendingQuizResult()) {
            return true;
        }
        if (this.quiz?.completedAt) {
            return true;
        }
        if (this.user?.quizCompletedAt) {
            return true;
        }
        const scoreRaw = this.user?.lastQuizScore;
        if (scoreRaw !== undefined && scoreRaw !== null && scoreRaw !== '') {
            const scoreNum = Number(scoreRaw);
            if (!Number.isNaN(scoreNum)) {
                return true;
            }
        }
        if (this.quiz?.score !== undefined && this.quiz?.score !== null && !Number.isNaN(Number(this.quiz.score))) {
            return true;
        }
        return false;
    }

    _markPendingQuizResultsWelcome() {
        if (this.user?.justLoggedIn
            && this.shouldSurfaceQuizResults(this.user?.lastQuizData)
            && this._hasIpaPhraseResults(this.user?.lastQuizData)) {
            this._pendingQuizResultsWelcome = true;
        }
    }

    async checkPendingQuizResults() {
        // Sets IVR context flags only when THIS flow is configured for quizzes.
        if (!this.flowHasQuizConfigured()) {
            // Clear foreign lastQuizData if it leaked into the client object.
            if (this.user && this.user.lastQuizData) {
                this.user.lastQuizData = null;
                this.user.lastQuizScore = null;
                this.user.lastQuizId = null;
            }
            return;
        }
        try {
            const stored = localStorage.getItem(this.flowStorageKey('flosc_quiz_result'));
            if (stored) {
                const result = JSON.parse(stored);
                const age = Date.now() - (result.timestamp || 0);
                if (age < 86400000) { // 24 hours
                    this.log('[FLOSC] checkPendingQuizResults: found stored result, setting context flags');

                    // Set context flags so IVR messages and autoprompt pills can fire
                    this.ivr.context.quiz_completed = true;
                    this.ivr.context.quiz_taken = true;
                    this.ivr.context.first_message_after_quiz = true;
                    if (result.score != null && !isNaN(result.score)) {
                        this.ivr.context.score = result.score;
                    }
                    if (result.quizId) this.ivr.context.quiz_id = result.quizId;

                    // Check if server already scored (email reg path scores during registration)
                    const serverData = this.user?.lastQuizData;
                    if (this._hasIpaPhraseResults(serverData)) {
                        this.log('[FLOSC] Server already has scored IPA data — storing wordIpa for openQuizResults()');
                        // Merge wordIpa from localStorage into server data so openQuizResults() has it
                        if (result.wordIpa && !serverData.word_ipa) {
                            serverData.word_ipa = result.wordIpa;
                        }
                        this.ivr.context.score = serverData.score;
                        this._markPendingQuizResultsWelcome();
                        localStorage.removeItem(this.flowStorageKey('flosc_quiz_result'));
                    } else if (result.phraseResults?.length && !(serverData?.phrase_results?.length)) {
                        // Email/SSO recovery: server meta empty but browser still has scored IPA data.
                        this.log('[FLOSC] Recovering quiz data from localStorage');
                        if (!this.user) this.user = {};
                        this.user.lastQuizData = {
                            quiz_type: 'ipa_audio',
                            score: result.score,
                            phrase_results: result.phraseResults,
                            word_ipa: result.wordIpa || {},
                            ranked_phonemes: result.rankedPhonemes || [],
                            timestamp: Math.floor((result.timestamp || Date.now()) / 1000)
                        };
                        this.user.lastQuizScore = result.score;
                        this.ivr.context.score = result.score;
                        this._markPendingQuizResultsWelcome();

                        // Persist to server in background (non-blocking). If it fails,
                        // results still display from this.user.lastQuizData above.
                        const storeQuizBody = {
                            quiz_data: {
                                score: result.score,
                                phraseResults: result.phraseResults,
                                wordIpa: result.wordIpa || null,
                                rankedPhonemes: result.rankedPhonemes || null,
                                quizType: 'ipa_audio',
                                tempId: result.tempId || null
                            },
                            temp_id: result.tempId || null
                        };
                        // Browser already scored each phrase — skip DO pull (avoids API/SSL dependency).
                        if (!result.phraseResults?.length) {
                            storeQuizBody.session_id = result.sessionId || null;
                        }
                        this.authFetch(`${this.config.restUrl}store-quiz-data`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(storeQuizBody)
                        }).then(r => {
                            if (!r.ok) {
                                this.logError('[FLOSC] store-quiz-data HTTP ' + r.status);
                                return r.text().then(t => { throw new Error(t); });
                            }
                            return r.json();
                        }).then(res => {
                            if (res && res.success) {
                                this.log('[FLOSC] Quiz data persisted to server');
                                localStorage.removeItem(this.flowStorageKey('flosc_quiz_result'));
                            }
                        }).catch(err => this.logError('[FLOSC] store-quiz-data failed', err));
                    } else if (this.user?.lastQuizScore && serverData?.phrase_results?.length) {
                        // Server has full IPA phrase data — safe to trust score and clear local backup
                        const score = parseInt(this.user.lastQuizScore) || 0;
                        this.ivr.context.score = score;
                        localStorage.removeItem(this.flowStorageKey('flosc_quiz_result'));
                    } else if (!result.pendingServerScore) {
                        // Non-IPA quiz with results already in localStorage
                        localStorage.removeItem(this.flowStorageKey('flosc_quiz_result'));
                    }
                }
            } else if (this.shouldSurfaceQuizResults(this.user?.lastQuizData)
                && this._hasIpaPhraseResults(this.user?.lastQuizData)) {
                // SSO wiped localStorage but server meta has scored IPA data for this flow.
                this.log('[FLOSC] checkPendingQuizResults: server IPA data only — setting context flags');
                this.ivr.context.quiz_completed = true;
                this.ivr.context.quiz_taken = true;
                this.ivr.context.first_message_after_quiz = true;
                const serverScore = Number(this.user?.lastQuizScore);
                if (!Number.isNaN(serverScore)) {
                    this.ivr.context.score = serverScore;
                } else if (this.user.lastQuizData?.score != null) {
                    this.ivr.context.score = this.user.lastQuizData.score;
                }
                this._markPendingQuizResultsWelcome();
            }
        } catch (e) {
            this.logError('[FLOSC] Could not check pending quiz results', e);
        }
        // Do NOT call checkAutoMessages() here. startIVR() handles all message
        // rendering. Calling it here pre-empts the quiz-data-as-welcome check
        // in startIVR() by inserting a login welcome message before startIVR runs.
    }
    
    showRecordingModal() {
        const modal = document.getElementById('flosc_modal_recording');
        if (modal) {
            this.setDisplayState(modal, true, 'flex');
            // v9.3.3: Reset quiz state when opening
            this.resetQuizModal();
        }
    }
    
    hideRecordingModal() {
        const modal = document.getElementById('flosc_modal_recording');
        if (modal) {
            this.setDisplayState(modal, false, 'flex');
        }
    }
    
    async requestFreeLesson() {
        if (!this.flowServesLessons()) {
            this.denyLessonsOnThisFlow(this._pendingFreeLessonUserMessage || '');
            this._pendingFreeLessonUserMessage = '';
            return;
        }
        // v8.0.1: Guard against double-calls (IVR action + pill can both fire)
        if (this._freeLessonInFlight) {
            this.log('FLOSC: requestFreeLesson already in flight — skipping duplicate call');
            return;
        }

        // v8.0.1: If already loaded this session, re-render cached cards (no re-fetch)
        if (this._cachedFreeLessons && this._cachedFreeLessons.length > 0) {
            this.log('FLOSC: Re-rendering cached free lessons');
            this._renderFreeLessonCards(this._cachedFreeLessons);
            return;
        }

        this._freeLessonInFlight = true;
        this.showTyping();

        if (this.state === 'visitor') {
            this.hideTyping();
            this._freeLessonInFlight = false;
            this.log('FLOSC: Visitor requested free lesson — not logged in');
            this.addMessage('assistant', 'To access your free lessons, please log in or create a free account first.', false);
            return;
        }

        // v8.0.1: Read free lessons from PHP config (embedded at page load).
        // This eliminates the cross-domain REST call that fails with 403
        // when frontend (e.g. the flow domain) and WP backend (e.g. the WordPress host) are on different domains.
        // PHP reads stored post IDs from user meta and passes title + content in the config.
        const configLessons = this.user?.freeLessons;
        if (configLessons && configLessons.length > 0) {
            this.log('FLOSC: Loading free lessons from config (' + configLessons.length + ' lessons)');
            this.hideTyping();
            this.ivr.context.lesson_viewed = true;
            this.ivr.context.first_message_after_free_lesson = true;
            this._cachedFreeLessons = configLessons;
            this._renderFreeLessonCards(configLessons);
            this.ivr.phase = 'offer';
            this._freeLessonInFlight = false;
            this._scheduleOffersForEvent('lesson_open');
            setTimeout(() => this.checkAutoMessages(), 2000);
            return;
        }

        // Config present but empty (no posts assigned / pool miss) — still try REST, then fail clearly.
        // Fallback: REST call (works on same-domain installs like flosc.ai)
        try {
            const response = await this.authFetch(this.config.apiUrl + '/free-lesson', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const data = await response.json();
            this.hideTyping();

            if (response.status === 403 || data.code === 'rest_forbidden') {
                this.log('FLOSC: Free lesson 403 — permission denied');
                const msg403 = 'It looks like your account doesn\'t have access to free lessons yet. If you just completed the quiz, please try refreshing the page and asking again.';
                this.addMessage('assistant', msg403, false);
                this.logClientChatTurn(this._pendingFreeLessonUserMessage || '', msg403, { source: 'free_lesson_error' });
                this._pendingFreeLessonUserMessage = '';
                return;
            }

            const lessons = data.lessons || (data.lesson ? [data.lesson] : []);

            if (data.success && lessons.length > 0) {
                this.ivr.context.lesson_viewed = true;
                this.ivr.context.first_message_after_free_lesson = true;
                this._cachedFreeLessons = lessons;
                this._renderFreeLessonCards(lessons);
                this.ivr.phase = 'offer';
                this._scheduleOffersForEvent('lesson_open');
                setTimeout(() => this.checkAutoMessages(), 2000);
            } else {
                this.log('FLOSC: Free lesson request unsuccessful — no lessons returned');
                const noneMsg = 'No free lessons are assigned to your account yet. Complete the quiz if you have not, then refresh and try again. If you already completed the quiz, ask your host to check free-lesson pool settings.';
                this.addMessage('assistant', noneMsg, false);
                this.logClientChatTurn(this._pendingFreeLessonUserMessage || '', noneMsg, { source: 'free_lesson_empty' });
                this._pendingFreeLessonUserMessage = '';
            }
        } catch (e) {
            this.hideTyping();
            this.logError('FLOSC: Free lesson request failed', e);
            const errMsg = 'Could not load free lessons (network or server error). Please try again.';
            this.addMessage('assistant', errMsg, false);
            this.logClientChatTurn(this._pendingFreeLessonUserMessage || '', errMsg, { source: 'free_lesson_error' });
            this._pendingFreeLessonUserMessage = '';
        } finally {
            this._freeLessonInFlight = false;
        }
    }

    /**
     * Write client-only turns into Chat Logs (and session history when possible).
     * Used when free-lesson / offer UI skips /chat.
     */
    async logClientChatTurn(userMessage, aiResponse, meta = {}) {
        try {
            await this.refreshNonce?.();
            const res = await this.authFetch(this.config.apiUrl + '/chat-log', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    user_message: userMessage || '',
                    ai_response: aiResponse || '',
                    session_id: this.currentSession?.id || 0,
                    flow_id: this.config.flowId || '',
                    phase: this.ivr?.phase || meta.phase || 'content',
                    provider: meta.provider || 'client',
                    response_source: meta.source || 'client_ui',
                }),
            });
            if (!res.ok) {
                this.logWarn('[FLOSC] chat-log HTTP', res.status);
            }
        } catch (e) {
            this.logWarn('[FLOSC] chat-log failed', e);
        }
    }

    // v8.0.1: Extracted card rendering — used by both fresh fetch and cache re-render
    _renderFreeLessonCards(lessons) {
        let cardHtml = `<div class="flosc-free-lesson-list">`;
        cardHtml += `<p class="flosc-free-lesson-intro">Here are your free lessons. Click on them to view.</p>`;
        const titles = [];
        lessons.forEach((lesson, i) => {
            titles.push(lesson.title || ('Lesson ' + (i + 1)));
            cardHtml += `<button type="button" class="flosc-free-lesson-card" data-flosc-action="free-lesson" data-lesson-index="${i}">`
                + `<span class="flosc-free-lesson-card-icon">\ud83c\udf93</span>`
                + `<span class="flosc-free-lesson-card-title">${this.escapeHtml(lesson.title)}</span>`
                + `</button>`;
        });
        cardHtml += `</div>`;
        this.addMessage('assistant', cardHtml, true);
        const userMsg = this._pendingFreeLessonUserMessage || 'I\'d like to see my free lessons';
        this._pendingFreeLessonUserMessage = '';
        const logText = 'Here are your free lessons. Click on them to view.\n'
            + titles.map((t, i) => (i + 1) + '. ' + t).join('\n');
        this.logClientChatTurn(userMsg, logText, { source: 'free_lesson_list', provider: 'client' });
    }

    // v8.0.0: Render full lesson content when user clicks a title card
    showFreeLessonContent(index) {
        const lessons = this._cachedFreeLessons;
        if (!lessons || !lessons[index]) {
            this.addMessage('assistant', 'Sorry, that lesson isn\'t available right now.', false);
            this.logClientChatTurn('(open free lesson)', 'Sorry, that lesson isn\'t available right now.', {
                source: 'free_lesson_open',
            });
            return;
        }
        const lesson = lessons[index];
        const lessonHtml = `<div class="flosc-free-lesson">`
            + `<h3 class="flosc-free-lesson-title">${this.escapeHtml(lesson.title)}</h3>`
            + `<div class="flosc-free-lesson-content">${lesson.content}</div>`
            + `</div>`;
        this.addMessage('assistant', lessonHtml, true);
        this.logClientChatTurn(
            'Open free lesson: ' + (lesson.title || index),
            lessonHtml,
            { source: 'free_lesson_open', provider: 'client' }
        );
    }
    
    initStripe() {
        if (window.Stripe && this.config.stripeKey) {
            this.stripe = Stripe(this.config.stripeKey);
        }
    }
    
    showPaymentModal(offerId) {
        const modal = document.getElementById('flosc_modal_payment');
        if (!modal) return;

        // Always show pay chrome; Access Code step must not leave a wiped body.
        this._showPaymentMainView();

        this.setDisplayState(modal, true, 'flex');
        modal.dataset.offerId = offerId;

        const offer = this.getOfferData(offerId);
        // Subscription path only when the offer is typed/configured as subscription.
        // SDK intent (capture vs subscription) is enqueued from the same offer type —
        // do not guess from display copy alone or Buttons will fail against the wrong intent.
        const hasSubPlans = !!(offer?.subscription?.plans && Object.keys(offer.subscription.plans).length);
        const isSubscription = (offer?.type === 'subscription') || hasSubPlans;
        
        // Update modal header from offer registry (not static PHP defaults).
        const nameEl = modal.querySelector('.flosc-product-name');
        if (nameEl) {
            nameEl.textContent = (offer?.name || offer?.headline || 'Membership').trim() || 'Membership';
        }
        const descEl = modal.querySelector('.flosc-product-desc');
        if (descEl) {
            let desc = String(offer?.headline || offer?.description || '').trim();
            if (desc.length > 160) desc = desc.slice(0, 157) + '…';
            descEl.textContent = desc || (isSubscription ? 'Choose a plan below' : 'Complete purchase below');
        }
        // Coupon state for this modal open (one-time amount and/or subscription promo).
        this._checkoutCouponCode = '';
        this._checkoutCouponAmount = null;
        this._checkoutCouponSub = null;
        this._paypalPromoMonthlyPlanId = '';
        this._paypalPromoYearlyPlanId = '';

        const priceEl = document.getElementById('paymentPrice');
        if (priceEl) {
            if (isSubscription) {
                // Plan prices live in the plan picker; keep header price empty.
                priceEl.textContent = '';
                priceEl.classList.add('flosc-hidden');
            } else {
                priceEl.classList.remove('flosc-hidden');
                const price = offer?.display_price
                    || (offer?.price ? `$${offer.price}` : '')
                    || (offer?.pricing?.price ? `$${offer.pricing.price}` : '');
                priceEl.textContent = price || '';
            }
        }

        // Coupon UI: only when floscAdmin opts in (offer.show_coupon_field) + native PayPal/Stripe.
        // Access Code remains a separate discreet link. Redirect shops keep their own coupons.
        const couponRow = document.getElementById('flosc-coupon-row');
        const couponInput = document.getElementById('flosc-coupon-input');
        const couponApply = document.getElementById('flosc-coupon-apply');
        const couponStatus = document.getElementById('flosc-coupon-status');
        const processorForCoupon = String(offer?.pricing?.processor || offer?.processor || 'paypal').toLowerCase();
        const couponFieldEnabled = offer?.show_coupon_field === true
            || offer?.show_coupon_field === 1
            || offer?.show_coupon_field === '1';
        const showCoupon = couponFieldEnabled
            && (processorForCoupon === 'paypal' || processorForCoupon === 'stripe');
        this._checkoutIsSubscription = !!isSubscription;
        if (couponRow) {
            this.setDisplayState(couponRow, showCoupon, 'block');
        }
        if (couponInput) couponInput.value = '';
        if (couponStatus) {
            couponStatus.textContent = '';
            couponStatus.classList.remove('is-success', 'is-error');
        }
        if (showCoupon && couponApply) {
            couponApply.onclick = () => this.applyCheckoutCoupon(offerId);
        }
        if (showCoupon && couponInput) {
            couponInput.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyCheckoutCoupon(offerId);
                }
            };
        }
        // Product icon size is CSS-only (.flosc-product-icon--image).
        const iconImg = modal.querySelector('.flosc-product-icon--image');
        if (iconImg) {
            iconImg.setAttribute('width', '40');
            iconImg.setAttribute('height', '40');
        }
        
        const paypalContainer = document.getElementById('paypal-button-container');
        const separator = document.getElementById('payment-separator');
        const stripeForm = document.getElementById('stripe-payment-form');
        const payBtn = document.getElementById('payBtn');

        const processor = String(offer?.pricing?.processor || offer?.processor || 'paypal').toLowerCase();
        const useStripe = processor === 'stripe' && !!this.config.stripeKey;

        // Access Code is a first-class unlock path for this product (not a failure fallback).
        const accessTrigger = document.getElementById('flosc-access-code-trigger');
        this.setDisplayState(accessTrigger, true, 'block');

        if (useStripe) {
            if (!this.stripe && window.Stripe) {
                this.initStripe();
            }
            if (!this.stripe) {
                this.logError('[FLOSC-CHECKOUT] Stripe selected but Stripe.js is not available');
                this._bindPaymentModalChrome(modal);
                return;
            }
            this.setDisplayState(paypalContainer, false, 'block');
            this.setDisplayState(stripeForm, true, 'block');
            this.setDisplayState(payBtn, true, 'block');
            this.setDisplayState(separator, false, 'block');
            const errorEl = document.getElementById('card-errors');
            const mountPoint = document.getElementById('card-element');
            if (mountPoint) {
                mountPoint.innerHTML = '';
                if (this.cardElement) {
                    try { this.cardElement.unmount(); } catch (e) { /* remount */ }
                    this.cardElement = null;
                }
                const elements = this.stripe.elements();
                this.cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#1f2937',
                            '::placeholder': { color: '#9ca3af' }
                        }
                    }
                });
                this.cardElement.mount(mountPoint);
                if (payBtn) payBtn.disabled = true;
                this.cardElement.on('change', (event) => {
                    if (errorEl) {
                        errorEl.textContent = event.error ? event.error.message : '';
                    }
                    if (payBtn) payBtn.disabled = !event.complete;
                });
            }
            if (payBtn) {
                payBtn.onclick = () => this.processModalPayment(offerId, payBtn, errorEl || document.getElementById('card-errors'));
            }
            this._bindPaymentModalChrome(modal);
            return;
        }

        // PayPal path (default for this product). SDK is enqueued correctly — no soft-fail UI.
        this.setDisplayState(stripeForm, false, 'block');
        this.setDisplayState(payBtn, false, 'block');
        this.setDisplayState(separator, false, 'block');
        if (paypalContainer) {
            this.setDisplayState(paypalContainer, true, 'block');
            paypalContainer.innerHTML = '';
            if (typeof paypal === 'undefined' || typeof paypal.Buttons !== 'function') {
                this.logError('[FLOSC-CHECKOUT] PayPal SDK missing (enqueue must load paypal-js without ?ver=)');
            } else if (isSubscription) {
                this._renderSubscriptionCheckout(offerId, offer, paypalContainer);
            } else {
                this._renderOneTimePayPal(offerId, paypalContainer);
            }
        }

        this._bindPaymentModalChrome(modal);
    }

    _bindPaymentModalChrome(modal) {
        const closeBtn = document.getElementById('paymentModalClose');
        if (closeBtn) {
            closeBtn.onclick = () => {
                this._showPaymentMainView();
                this.setDisplayState(modal, false, 'flex');
            };
        }
        const acLink = modal?.querySelector?.('[data-flosc-action="open-access-code-payment"]')
            || document.querySelector('#flosc-access-code-trigger .flosc-access-code-link');
        if (acLink && !acLink.dataset.floscBound) {
            acLink.dataset.floscBound = '1';
            acLink.addEventListener('click', (e) => {
                e.preventDefault();
                this._showAccessCodeInput('payment');
            });
        }
        const backBtn = document.getElementById('flosc-access-code-back');
        if (backBtn && !backBtn.dataset.floscBound) {
            backBtn.dataset.floscBound = '1';
            backBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._showPaymentMainView();
            });
        }
        const acSubmit = document.getElementById('flosc-access-code-submit');
        if (acSubmit && !acSubmit.dataset.floscBound) {
            acSubmit.dataset.floscBound = '1';
            acSubmit.addEventListener('click', () => {
                const code = (document.getElementById('flosc-access-code-input')?.value || '').trim();
                if (code) this._redeemAccessCode(code, 'payment');
            });
        }
        const acInput = document.getElementById('flosc-access-code-input');
        if (acInput && !acInput.dataset.floscBound) {
            acInput.dataset.floscBound = '1';
            acInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const code = (e.target.value || '').trim();
                    if (code) this._redeemAccessCode(code, 'payment');
                }
            });
        }
    }

    /** Payment modal: show pay UI (PayPal/Stripe/coupon). Does not rebuild DOM. */
    _showPaymentMainView() {
        const main = document.getElementById('flosc-payment-main');
        const panel = document.getElementById('flosc-access-code-panel');
        if (main) this.setDisplayState(main, true, 'block');
        if (panel) this.setDisplayState(panel, false, 'block');
        const errEl = document.getElementById('flosc-access-code-error');
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.remove('is-success');
        }
        const input = document.getElementById('flosc-access-code-input');
        if (input) input.value = '';
        const btn = document.getElementById('flosc-access-code-submit');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Submit';
        }
    }

    /** Payment modal: Access Code step only — never replaces pay DOM with innerHTML. */
    _showPaymentAccessCodeView() {
        const main = document.getElementById('flosc-payment-main');
        const panel = document.getElementById('flosc-access-code-panel');
        if (main) this.setDisplayState(main, false, 'block');
        if (panel) this.setDisplayState(panel, true, 'block');
        const errEl = document.getElementById('flosc-access-code-error');
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.remove('is-success');
        }
        const input = document.getElementById('flosc-access-code-input');
        if (input) {
            input.value = '';
            input.focus();
        }
        const btn = document.getElementById('flosc-access-code-submit');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Submit';
        }
    }

    // Access Code UI: payment = toggle panels; auth = separate modal fill (not checkout).
    _showAccessCodeInput(context) {
        if (context === 'payment') {
            const modal = document.getElementById('flosc_modal_payment');
            if (!modal) return;
            // Ensure modal is open and pay DOM still exists under #flosc-payment-main.
            this.setDisplayState(modal, true, 'flex');
            this._bindPaymentModalChrome(modal);
            this._showPaymentAccessCodeView();
            return;
        } else if (context === 'auth') {
            const modal = document.getElementById('flosc-auth-modal');
            if (!modal) return;
            const inner = modal.querySelector('.flosc-auth-modal');
            if (!inner) return;
            inner.innerHTML = `
                <button class="flosc-auth-close" type="button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="flosc-access-code-panel">
                    <div class="flosc-access-code-title">Enter Access Code</div>
                    <input type="text" id="flosc-access-code-input" maxlength="20" autocomplete="off" spellcheck="false"
                           class="flosc-access-code-input"
                           placeholder="CODE">
                    <div class="flosc-access-code-actions">
                        <button id="flosc-access-code-submit" class="flosc-access-code-submit">Submit</button>
                    </div>
                    <div id="flosc-access-code-error" class="flosc-access-code-error"></div>
                </div>
            `;
            document.getElementById('flosc-access-code-input').focus();
            document.getElementById('flosc-access-code-submit').addEventListener('click', () => {
                const code = document.getElementById('flosc-access-code-input').value.trim();
                if (code) this._redeemAccessCode(code, 'auth');
            });
            document.getElementById('flosc-access-code-input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const code = e.target.value.trim();
                    if (code) this._redeemAccessCode(code, 'auth');
                }
            });
            const closeBtn = inner.querySelector('.flosc-auth-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.hideAuthModal());
            }
        }
    }

    /** Coupon code for native charge: applied code, else current modal input. */
    _getCheckoutCouponCodeForCharge() {
        if (this._checkoutCouponCode) {
            return String(this._checkoutCouponCode).trim();
        }
        const input = document.getElementById('flosc-coupon-input');
        return (input?.value || '').trim();
    }

    /**
     * Apply native offer coupon in payment modal (preview + store code for create-order).
     */
    async applyCheckoutCoupon(offerId) {
        const input = document.getElementById('flosc-coupon-input');
        const status = document.getElementById('flosc-coupon-status');
        const priceEl = document.getElementById('paymentPrice');
        const code = (input?.value || '').trim();
        if (!code) {
            this._checkoutCouponCode = '';
            this._checkoutCouponAmount = null;
            if (status) {
                status.textContent = 'Enter a coupon code.';
                status.classList.remove('is-success');
                status.classList.add('is-error');
            }
            return;
        }
        if (status) {
            status.textContent = 'Checking…';
            status.classList.remove('is-success', 'is-error');
        }
        try {
            await this.refreshNonce();
            const res = await this.authFetch(this.config.apiUrl + '/apply-offer-coupon', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    flow_id: this.config.flowId || '',
                    code,
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                this._checkoutCouponCode = '';
                this._checkoutCouponAmount = null;
                if (status) {
                    status.textContent = data.message || data.code || 'Invalid or expired coupon.';
                    status.classList.add('is-error');
                    status.classList.remove('is-success');
                }
                return;
            }
            if (data.kind === 'access_code') {
                this._checkoutCouponCode = '';
                if (status) {
                    status.textContent = 'That is an access code — use Access Code.';
                    status.classList.add('is-error');
                    status.classList.remove('is-success');
                }
                return;
            }
            this._checkoutCouponCode = data.coupon_code || code;
            this._checkoutCouponAmount = data.amount;
            this._checkoutCouponSub = (data.billing === 'subscription')
                ? {
                    monthly: data.monthly,
                    yearly: data.yearly,
                    list_monthly: data.list_monthly,
                    list_yearly: data.list_yearly,
                }
                : null;

            if (data.billing === 'subscription') {
                if (priceEl) {
                    priceEl.classList.remove('flosc-hidden');
                    const list = data.list_display
                        ? `<span class="flosc-price-was">${this.escapeHtml(data.list_display)}</span> `
                        : '';
                    priceEl.innerHTML = list + this.escapeHtml(data.display || '');
                }
                if (status) {
                    status.textContent = 'Coupon applied: '
                        + (data.display || '')
                        + (data.yearly_display ? ' · ' + data.yearly_display : '')
                        + '. Choose a plan below.';
                    status.classList.add('is-success');
                    status.classList.remove('is-error');
                }
                // Re-render plan picker + PayPal subscription plans at promo amounts.
                const paypalContainer = document.getElementById('paypal-button-container');
                const offer = this.getOfferData(offerId);
                if (paypalContainer && offer) {
                    paypalContainer.innerHTML = '';
                    await this._renderSubscriptionCheckout(offerId, offer, paypalContainer, this._checkoutCouponSub);
                }
            } else {
                if (priceEl && data.display) {
                    priceEl.classList.remove('flosc-hidden');
                    const list = data.list_display
                        ? `<span class="flosc-price-was">${this.escapeHtml(data.list_display)}</span> `
                        : '';
                    priceEl.innerHTML = list + this.escapeHtml(data.display);
                }
                if (status) {
                    status.textContent = 'Coupon applied. You will be charged ' + (data.display || '') + '.';
                    status.classList.add('is-success');
                    status.classList.remove('is-error');
                }
                // Re-render PayPal one-time buttons so createOrder uses the new coupon.
                const paypalContainer = document.getElementById('paypal-button-container');
                if (paypalContainer && typeof paypal !== 'undefined' && typeof paypal.Buttons === 'function') {
                    paypalContainer.innerHTML = '';
                    this._renderOneTimePayPal(offerId, paypalContainer);
                }
            }
        } catch (e) {
            this.logError('[FLOSC] applyCheckoutCoupon', e);
            if (status) {
                status.textContent = 'Could not apply coupon. Try again.';
                status.classList.add('is-error');
            }
        }
    }

    // v8.0.0: Redeem access code via REST — grants role, reloads on success
    async _redeemAccessCode(code, context) {
        const errEl = document.getElementById('flosc-access-code-error');
        const btn = document.getElementById('flosc-access-code-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Checking...'; }
        try {
            await this.refreshNonce();
            const modalOfferId = document.getElementById('flosc_modal_payment')?.dataset?.offerId || '';
            const res = await this.authFetch(this.config.apiUrl + '/redeem-access-code', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                body: JSON.stringify({
                    code: code,
                    flow_id: this.config.flowId || '',
                    offer_id: modalOfferId || '',
                }),
            });
            const data = await res.json();
            if (data.success) {
                if (context === 'chat') {
                    this.addMessage('assistant', 'Welcome, fam! You\'re in. Refreshing...');
                } else if (errEl) {
                    errEl.classList.add('is-success');
                    errEl.textContent = 'Welcome, fam! You\'re in.';
                }
                sessionStorage.removeItem(this.flowStorageKey('flosc_credential_setup_dismissed'));
                setTimeout(() => window.location.reload(), 1500);
            } else {
                if (context === 'chat') {
                    this.addMessage('assistant', 'Invalid code. Try again.');
                } else if (errEl) {
                    errEl.textContent = 'Invalid code. Try again.';
                }
                if (btn) { btn.disabled = false; btn.textContent = 'Submit'; }
            }
        } catch (e) {
            this.logError('[FLOSC] Access code error:', e);
            if (context === 'chat') {
                this.addMessage('assistant', 'Something went wrong. Try again.');
            } else if (errEl) {
                errEl.textContent = 'Something went wrong. Try again.';
            }
            if (btn) { btn.disabled = false; btn.textContent = 'Submit'; }
        }
    }

    /**
     * Subscription checkout: plan picker (monthly/yearly) + PayPal subscription buttons.
     * Plan IDs come from config (pre-loaded) or fetched on-the-fly via /paypal/get-plans.
     * @param {object|null} promoPrices Optional coupon amounts { monthly, yearly, list_monthly, list_yearly }.
     */
    async _renderSubscriptionCheckout(offerId, offer, container, promoPrices = null) {
        // Plan picker from offer + flow token config — never brand-hardcoded prices.
        const plans = (offer && offer.subscription && offer.subscription.plans) ? offer.subscription.plans : {};
        let monthlyPrice = Number(plans.monthly?.price ?? offer?.pricing?.price ?? offer?.price ?? 0) || 0;
        let yearlyPrice = Number(plans.yearly?.price ?? 0) || (monthlyPrice > 0 ? monthlyPrice * 10 : 0);
        const listMonthly = monthlyPrice;
        const listYearly = yearlyPrice;
        // Applied coupon: final monthly/yearly recurring amounts (e.g. $25/mo → $10/mo).
        if (promoPrices && (promoPrices.monthly > 0 || promoPrices.yearly > 0)) {
            if (Number(promoPrices.monthly) > 0) monthlyPrice = Number(promoPrices.monthly);
            if (Number(promoPrices.yearly) > 0) yearlyPrice = Number(promoPrices.yearly);
        } else if (this._checkoutCouponSub) {
            const p = this._checkoutCouponSub;
            if (Number(p.monthly) > 0) monthlyPrice = Number(p.monthly);
            if (Number(p.yearly) > 0) yearlyPrice = Number(p.yearly);
        }
        const monthlyLabel = plans.monthly?.label || (monthlyPrice > 0 ? `$${monthlyPrice}/month` : 'Monthly');
        const yearlyLabel = plans.yearly?.label || (yearlyPrice > 0 ? `$${yearlyPrice}/year` : 'Yearly');
        const currencySym = (this.config.identity && this.config.identity.currency_symbol) || '$';
        const fmtMoney = (n) => (Number.isFinite(n) && n > 0 ? `${currencySym}${Number(n).toFixed(n % 1 ? 2 : 0)}` : '—');
        const monthlyTokens = Number(this.config.subscriptionMonthlyTokenGrant || this.config.productTokenGrantRecurring || 0);
        const yearlyTokens = Number(this.config.subscriptionYearlyTokenGrant || this.config.productTokenGrantRecurringYearly || 0);
        const tokenCap = Number(this.config.subscriptionTokenCap || this.config.productTokenCap || 0);
        const fmtTok = (n) => (Number.isFinite(n) && n > 0 ? n.toLocaleString() : '');
        const yearlySavings = (monthlyPrice > 0 && yearlyPrice > 0 && monthlyPrice * 12 > yearlyPrice)
            ? (monthlyPrice * 12 - yearlyPrice)
            : 0;
        const yearlyTokenLine = fmtTok(yearlyTokens)
            ? (tokenCap > 0
                ? `up to ${fmtTok(yearlyTokens)} tokens (cap ${fmtTok(tokenCap)})`
                : `up to ${fmtTok(yearlyTokens)} tokens`)
            : '';
        const monthlyTokenLine = fmtTok(monthlyTokens)
            ? (tokenCap > 0
                ? `+${fmtTok(monthlyTokens)} tokens / cycle (cap ${fmtTok(tokenCap)})`
                : `+${fmtTok(monthlyTokens)} tokens / cycle`)
            : '';
        const yearlyExtra = [
            yearlySavings > 0 ? `Save ${fmtMoney(yearlySavings)}` : '',
            yearlyTokenLine,
        ].filter(Boolean).join(' · ');
        container.innerHTML = `
            <div class="flosc-plan-picker">
                <div class="flosc-plan-picker-title">Choose your plan:</div>
                <div class="flosc-plan-options">
                    <label class="flosc-plan-option flosc-plan-option-selected" data-plan="yearly">
                        <div class="flosc-plan-badge">Best Value</div>
                        <input type="radio" name="flosc_plan" value="yearly" checked class="flosc-plan-option-input">
                        <div class="flosc-plan-amount">${listYearly > yearlyPrice && listYearly > 0
                            ? `<span class="flosc-price-was">${this.escapeHtml(fmtMoney(listYearly))}</span> `
                            : ''}${this.escapeHtml(fmtMoney(yearlyPrice))}</div>
                        <div class="flosc-plan-interval flosc-plan-interval-yearly">/year</div>
                        ${yearlyExtra ? `<div class="flosc-plan-savings">${this.escapeHtml(yearlyExtra)}</div>` : ''}
                    </label>
                    <label class="flosc-plan-option" data-plan="monthly">
                        <input type="radio" name="flosc_plan" value="monthly" class="flosc-plan-option-input">
                        <div class="flosc-plan-amount">${listMonthly > monthlyPrice && listMonthly > 0
                            ? `<span class="flosc-price-was">${this.escapeHtml(fmtMoney(listMonthly))}</span> `
                            : ''}${this.escapeHtml(fmtMoney(monthlyPrice))}</div>
                        <div class="flosc-plan-interval">/month</div>
                        ${monthlyTokenLine ? `<div class="flosc-plan-savings">${this.escapeHtml(monthlyTokenLine)}</div>` : ''}
                    </label>
                </div>
            </div>
            <div id="flosc-sub-paypal-btn" class="flosc-sub-paypal-btn"></div>
            <div id="flosc-sub-status" class="flosc-sub-status"></div>
        `;
        // Stash for welcome copy after activate
        container.dataset.floscMonthlyPrice = String(monthlyPrice);
        container.dataset.floscYearlyPrice = String(yearlyPrice);
        container.dataset.floscMonthlyLabel = monthlyLabel;
        container.dataset.floscYearlyLabel = yearlyLabel;
        container.dataset.floscPromoCoupon = this._getCheckoutCouponCodeForCharge() || '';

        // Plan selection toggle styling
        const planOptions = container.querySelectorAll('.flosc-plan-option');
        planOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                planOptions.forEach(o => {
                    o.classList.remove('flosc-plan-option-selected');
                });
                opt.classList.add('flosc-plan-option-selected');
                opt.querySelector('input').checked = true;
                // Re-render PayPal buttons for new plan
                this._mountSubscriptionButtons(offerId, container);
            });
        });

        // Plan IDs: list-price plans from config, or promo plans when coupon applied.
        const couponCode = this._getCheckoutCouponCodeForCharge();
        let monthlyPlanId = '';
        let yearlyPlanId = '';
        if (!couponCode) {
            monthlyPlanId = this.config.paypalMonthlyPlanId || '';
            yearlyPlanId = this.config.paypalYearlyPlanId || '';
        }

        if (!monthlyPlanId || !yearlyPlanId || couponCode) {
            const statusEl = container.querySelector('#flosc-sub-status');
            if (statusEl) statusEl.textContent = couponCode
                ? 'Setting up promo subscription plans...'
                : 'Setting up payment plans...';
            try {
                await this.refreshNonce();
                const res = await this.authFetch(this.config.apiUrl + '/paypal/get-plans', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                    body: JSON.stringify({
                        flow_id: this.config.flowId || '',
                        offer_id: offerId || '',
                        coupon_code: couponCode || '',
                    }),
                });
                const data = await res.json();
                if (data.monthly_plan_id && data.yearly_plan_id) {
                    monthlyPlanId = data.monthly_plan_id;
                    yearlyPlanId = data.yearly_plan_id;
                    if (couponCode) {
                        // Do not overwrite default list-price plan IDs in config.
                        this._paypalPromoMonthlyPlanId = monthlyPlanId;
                        this._paypalPromoYearlyPlanId = yearlyPlanId;
                    } else {
                        this.config.paypalMonthlyPlanId = monthlyPlanId;
                        this.config.paypalYearlyPlanId = yearlyPlanId;
                    }
                } else {
                    throw new Error(data.message || 'Could not get plan IDs');
                }
            } catch (err) {
                this.logError('[FLOSC-CHECKOUT] Failed to get PayPal plans:', err);
                const statusEl2 = container.querySelector('#flosc-sub-status');
                if (statusEl2) statusEl2.innerHTML = '<span class="flosc-status-error">Could not set up payment plans. Please try again.</span>';
                return;
            }
            if (statusEl) statusEl.textContent = '';
        }

        this._mountSubscriptionButtons(offerId, container);
    }

    /**
     * Mount (or re-mount) PayPal subscription buttons for the currently selected plan.
     */
    _mountSubscriptionButtons(offerId, container) {
        const btnContainer = container.querySelector('#flosc-sub-paypal-btn');
        if (!btnContainer) return;

        // Render-generation counter: stale polls/renders from prior calls self-abort
        this._paypalSubRenderGen = (this._paypalSubRenderGen || 0) + 1;
        const myGen = this._paypalSubRenderGen;

        // Close previous PayPal button instance before re-mounting
        if (this._paypalSubButtons) {
            try { this._paypalSubButtons.close(); } catch (e) { /* already closed */ }
            this._paypalSubButtons = null;
        }
        btnContainer.innerHTML = '';

        const selectedPlan = container.querySelector('input[name="flosc_plan"]:checked')?.value || 'yearly';
        const usePromo = !!(this._getCheckoutCouponCodeForCharge());
        const planId = selectedPlan === 'yearly'
            ? (usePromo ? (this._paypalPromoYearlyPlanId || this.config.paypalYearlyPlanId) : this.config.paypalYearlyPlanId)
            : (usePromo ? (this._paypalPromoMonthlyPlanId || this.config.paypalMonthlyPlanId) : this.config.paypalMonthlyPlanId);

        if (!planId) {
            btnContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error">Plan not configured.</div>';
            return;
        }

        const renderBtns = () => {
            if (myGen !== this._paypalSubRenderGen) return; // stale — a newer plan switch superseded us

            btnContainer.innerHTML = '';

            const btns = paypal.Buttons({
                style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'subscribe', height: 45 },
                createSubscription: async (data, actions) => {
                    this.log('[FLOSC-CHECKOUT] Preparing subscription intent: plan=' + planId + ', type=' + selectedPlan);
                    await this.refreshNonce();
                    const sessionId = this.getVisitorSessionId() || '';
                    // Industry standard: server mints purchase intent; UUID goes in PayPal custom_id.
                    const prepRes = await this.authFetch(this.config.apiUrl + '/paypal/prepare-subscription', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                        body: JSON.stringify({
                            offer_id: offerId || '',
                            plan_type: selectedPlan,
                            flow_id: this.config.flowId || '',
                            session_id: sessionId,
                        }),
                    });
                    const prepRaw = await prepRes.text();
                    let prep = {};
                    try {
                        prep = prepRaw ? JSON.parse(prepRaw) : {};
                    } catch (e) {
                        throw new Error('Could not prepare PayPal purchase (invalid JSON)');
                    }
                    if (!prepRes.ok || !prep.purchase_uuid || !prep.plan_id) {
                        throw new Error(prep.message || 'Could not prepare PayPal purchase intent');
                    }
                    this._paypalPurchaseUuid = prep.purchase_uuid;
                    this.log('[FLOSC-CHECKOUT] Creating subscription with purchase_uuid=' + prep.purchase_uuid);
                    return actions.subscription.create({
                        plan_id: prep.plan_id,
                        custom_id: String(prep.purchase_uuid).slice(0, 127),
                    });
                },
                onApprove: async (data) => {
                    this.log('[FLOSC-CHECKOUT] Subscription approved: subscriptionID=' + data.subscriptionID);
                    btnContainer.innerHTML = '<div class="flosc-paypal-status">Activating your subscription...</div>';

                    try {
                        await this.refreshNonce();

                        // Mint a server-issued binding token for this browser, then
                        // present it on activation. This is what lets the server
                        // safely issue an instant login session to a visitor buyer:
                        // it proves the activation request is this same browser, not
                        // a replayed subscription id. If minting fails for any reason,
                        // activation still proceeds — the buyer simply receives the
                        // emailed sign-in link instead of an instant session.
                        const bindingSessionId = this.getVisitorSessionId() || '';
                        const bindingToken = await this._mintCheckoutBinding(bindingSessionId, 'paypal', offerId);

                        const res = await this.authFetch(this.config.apiUrl + '/paypal/activate-subscription', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                            body: JSON.stringify({
                                subscription_id: data.subscriptionID,
                                plan_type: selectedPlan,
                                flow_id: this.config.flowId || '',
                                offer_id: offerId || '',
                                purchase_uuid: this._paypalPurchaseUuid || '',
                                binding_token: bindingToken || '',
                                session_id: bindingSessionId,
                            }),
                        });

                        const rawText = await res.text();
                        this.log('[FLOSC-CHECKOUT] activate-subscription response: HTTP ' + res.status + ' body=' + rawText.substring(0, 500));
                        let result = {};
                        try {
                            result = rawText ? JSON.parse(rawText) : {};
                        } catch (e) {
                            throw new Error('Activation failed: server returned non-JSON (HTTP ' + res.status + ')');
                        }

                        if (!res.ok || !result.success) {
                            throw new Error(result.message || ('Activation failed (HTTP ' + res.status + ')'));
                        }

                        // Close modal
                        const modal = document.getElementById('flosc_modal_payment');
                        this.setDisplayState(modal, false, 'flex');

                        // If server created a new account from PayPal (visitor purchase),
                        // store the auth token so subsequent API calls are authenticated.
                        if (result.auth_token) {
                            this.config.authToken = result.auth_token;
                            localStorage.setItem('flosc_auth_token', result.auth_token);
                            // Clear visitor message cache — they're a member now
                            localStorage.removeItem(this.flowStorageKey('flosc_visitor_messages'));
                        }

                        // Update local user state so IVR conditions reflect purchase
                        const displayName = result.user_display_name || result.user_email || 'Member';
                        const defaultMemberLevel = this.config.defaultMemberLevel || 'member';
                        if (this.user) {
                            this.user.justPurchased = true;
                            this.user.purchased = true;
                            this.user.memberLevel = result.member_level || defaultMemberLevel;
                            this.user.isMember = true;
                            if (result.user_email) this.user.email = result.user_email;
                            if (!this.user.name) this.user.name = displayName;
                        } else {
                            // Visitor had no user object — create one
                            this.user = {
                                id: result.user_id,
                                name: displayName,
                                email: result.user_email || '',
                                justPurchased: true,
                                purchased: true,
                                memberLevel: result.member_level || defaultMemberLevel,
                                isMember: true,
                            };
                        }
                        // Update app-level state so buildIVRContext() sees 'member'
                        this.state = 'member';
                        this.ivr.context.is_member = true;
                        this.ivr.context.is_guest = false;
                        this.ivr.context.purchased = true;
                        this.ivr.context.first_message_after_purchase = true;

                        // Transition profile bar from visitor → member without page reload
                        document.body.dataset.userState = 'member';
                        const profileName = document.getElementById('flosc_profile_name');
                        const profileBadge = document.getElementById('flosc_profile_badge');
                        const dropdownName = document.getElementById('flosc_dropdown_name');
                        const dropdownEmail = document.getElementById('flosc_dropdown_email');
                        const profileAvatar = document.getElementById('flosc_profile_avatar');
                        const profileAvatarIcon = document.getElementById('flosc_profile_avatar_icon');
                        const upgradeContainer = document.getElementById('flosc_upgrade_container');
                        const upgradeBtnLabel = document.getElementById('flosc_upgrade_button_label');
                        const bar = document.getElementById('flosc_user_profile_bar');
                        document.documentElement.style.setProperty('--flosc-avatar-radius', bar?.dataset.memberAvatarRadius || '8px');
                        const memberName = String(bar?.dataset.memberName || '').trim() || this.user.name;
                        if (profileName) profileName.textContent = memberName;
                        if (profileBadge) profileBadge.textContent = bar?.dataset.memberBadge || 'Member';
                        if (dropdownName) dropdownName.textContent = memberName;
                        if (dropdownEmail) dropdownEmail.textContent = this.user.email || '';
                        if (upgradeContainer && upgradeBtnLabel) {
                            const showMemberUpgrade = String(bar?.dataset.memberUpgradeShow || '0') === '1';
                            const memberUpgradeLabel = String(bar?.dataset.memberUpgradeLabel || 'Upgrade').trim() || 'Upgrade';
                            this.setDisplayState(upgradeContainer, showMemberUpgrade, 'block');
                            upgradeBtnLabel.textContent = memberUpgradeLabel;
                        }
                        if (profileAvatar && profileAvatarIcon) {
                            const memberImageUrl = String(bar?.dataset.memberIconUrl || '').trim();
                            const memberIcon = String(bar?.dataset.memberIcon || '').trim();
                            if (memberImageUrl) {
                                profileAvatar.src = memberImageUrl;
                                profileAvatar.alt = memberName || 'Member avatar';
                                this.setDisplayState(profileAvatar, true, 'block');
                                this.setDisplayState(profileAvatarIcon, false, 'flex');
                            } else if (memberIcon) {
                                profileAvatarIcon.textContent = memberIcon;
                                this.setDisplayState(profileAvatarIcon, true, 'flex');
                                this.setDisplayState(profileAvatar, false, 'block');
                            }
                        }

                        // Welcome message from flow identity + plan pricing (not brand hardcodes).
                        const productName = (result.product_name
                            || this.config.identity?.name
                            || this.config.productName
                            || 'your membership').trim();
                        const planLabel = selectedPlan === 'yearly'
                            ? (container.dataset.floscYearlyLabel || result.amount || 'yearly')
                            : (container.dataset.floscMonthlyLabel || result.amount || 'monthly');
                        const welcomeMsg = (result.login_handoff === 'email_link_sent')
                            ? `🎉 **Your ${planLabel} subscription is active!**\n\n` +
                              `Your account has been created and access is now active. ` +
                              `A sign-in link has been sent to your purchase email — click it to continue from any device.\n\n` +
                              `**What would you like to do first?**`
                            : `🎉 **Welcome to ${productName}!** Your ${planLabel} subscription is active.\n\n` +
                              `You now have full member access.\n\n` +
                              `**What would you like to do first?**`;
                        this.addMessage('assistant', welcomeMsg);

                        // Trigger post-purchase auto messages after short delay
                        setTimeout(() => this.checkAutoMessages(), 2000);
                    } catch (err) {
                        this.logError('[FLOSC-CHECKOUT] Subscription activation error:', err);
                        btnContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error">' +
                            this.escapeHtml(err.message || 'Failed to activate subscription. Please contact support.') + '</div>';
                    }
                },
                onError: (err) => {
                    this.logError('[FLOSC-CHECKOUT] PayPal subscription error:', err);
                    btnContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error">PayPal encountered an error. Please try again.</div>';
                },
                onCancel: () => {
                    this.log('[FLOSC-CHECKOUT] Subscription cancelled — re-rendering buttons');
                    if (myGen !== this._paypalSubRenderGen) return;
                    btnContainer.innerHTML = '';
                    requestAnimationFrame(() => renderBtns());
                },
            });

            // Always render — PayPal is a first-class FLOSC payment path (no soft-fail eligibility gate).
            this._paypalSubButtons = btns;
            btns.render(btnContainer).catch(err => {
                if (myGen !== this._paypalSubRenderGen) return;
                this.logError('[FLOSC-CHECKOUT] PayPal subscription render failed:', err);
                btnContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error">PayPal could not load. Please try again.</div>';
            });
        };

        // Poll for container dimensions — abort if a newer generation took over
        const poll = (attempt = 0) => {
            if (myGen !== this._paypalSubRenderGen) return;
            const rect = btnContainer.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                renderBtns();
            } else if (attempt < 40) {
                setTimeout(() => poll(attempt + 1), 50);
            } else {
                renderBtns();
            }
        };
        requestAnimationFrame(() => poll());
    }

    /**
     * One-time PayPal payment (Orders API capture).
     */
    _renderOneTimePayPal(offerId, paypalContainer) {
            const renderPayPalButtons = () => {
            if (typeof paypal === 'undefined' || typeof paypal.Buttons !== 'function') {
                this.logError('[FLOSC-CHECKOUT] PayPal.Buttons unavailable — SDK must be enqueued without ?ver=');
                return;
            }
            const paypalButtonsInstance = paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal',
                    height: 45,
                },
                createOrder: async () => {
                    this.log('[FLOSC-CHECKOUT] PayPal createOrder: offerId=' + offerId + ', flowId=' + (this.config.flowId || 'none'));
                    const doCreate = async () => {
                        const res = await this.authFetch(this.config.apiUrl + '/paypal/create-order', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': this.config.nonce,
                            },
                            body: JSON.stringify({
                                offer_id: offerId,
                                flow_id: this.config.flowId || '',
                                // Prefer applied code; else live input if user skipped Apply.
                                coupon_code: this._getCheckoutCouponCodeForCharge(),
                            }),
                        });
                        if (!res.ok) {
                            const errBody = await res.json().catch(() => ({}));
                            this.logError('[FLOSC-CHECKOUT] PayPal create-order HTTP ' + res.status + ':', errBody);
                            throw new Error(errBody.message || 'Server error ' + res.status);
                        }
                        return await res.json();
                    };
                    // Pre-flight nonce refresh — ensures nonce is valid for current session.
                    await this.refreshNonce();

                    try {
                        let data = await doCreate();
                        // If 2xx but no order_id and auth error in body, retry once
                        if (!data.order_id && (data.code === 'rest_cookie_invalid_nonce' || (data.message || '').match(/cookie|not allowed/i))) {
                            this.log('[FLOSC] PayPal create-order auth issue in body, retrying...');
                            await this.refreshNonce();
                            data = await doCreate();
                        }
                        if (data.order_id) {
                            return data.order_id;
                        }
                        throw new Error(data.message || 'Failed to create PayPal order');
                    } catch (err) {
                        // v3.0.7: If 403/401 thrown by doCreate, refresh nonce and retry once
                        if ((err.message || '').match(/not allowed|cookie|nonce|401|403/i) && !err._retried) {
                            this.log('[FLOSC] PayPal create-order 403, retrying after nonce refresh...');
                            try {
                                await this.refreshNonce();
                                const retryData = await doCreate();
                                if (retryData.order_id) return retryData.order_id;
                                throw new Error(retryData.message || 'Failed to create PayPal order');
                            } catch (retryErr) {
                                retryErr._retried = true;
                                // fall through to show error below
                                const errorEl = document.getElementById('card-errors');
                                if (errorEl) errorEl.textContent = retryErr.message;
                                if (paypalContainer) {
                                    paypalContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error flosc-paypal-status-sm">' +
                                        (retryErr.message || 'Could not create order. Please try again.') + '</div>';
                                }
                                throw retryErr;
                            }
                        }
                        this.logError('[FLOSC-CHECKOUT] PayPal create order error:', err);
                        const errorEl = document.getElementById('card-errors');
                        if (errorEl) errorEl.textContent = err.message;
                        if (paypalContainer) {
                            paypalContainer.innerHTML = '<div class="flosc-paypal-status flosc-paypal-status-error flosc-paypal-status-sm">' +
                                (err.message || 'Could not create order. Please try again.') + '</div>';
                        }
                        throw err;
                    }
                },
                onApprove: async (data, actions) => {
                    try {
                        this.log('[FLOSC-CHECKOUT] PayPal onApprove: orderID=' + data.orderID);
                        paypalContainer.innerHTML = '<div class="flosc-paypal-status">Processing payment...</div>';

                        // Binding is minted here (not outer scope) — required for capture handoff + visitor account grant.
                        const bindingSessionId = (this.currentSession && this.currentSession.id)
                            || this.getVisitorSessionId()
                            || String(Date.now());
                        const bindingToken = await this._mintCheckoutBinding(bindingSessionId, 'paypal', offerId);

                        const doCapture = async () => {
                            const r = await this.authFetch(this.config.apiUrl + '/paypal/capture-order', {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': this.config.nonce,
                                },
                                body: JSON.stringify({
                                    order_id: data.orderID,
                                    offer_id: offerId,
                                    provider: 'paypal',
                                    flow_id: this.config.flowId || '',
                                    binding_token: bindingToken || '',
                                    session_id: bindingSessionId,
                                }),
                            });
                            const body = await r.json().catch(() => ({
                                success: false,
                                message: 'Server returned non-JSON (HTTP ' + r.status + ')',
                            }));
                            body._httpStatus = r.status;
                            body._httpOk = r.ok;
                            return body;
                        };

                        await this.refreshNonce();
                        let result = await doCapture();
                        this.log('[FLOSC-CHECKOUT] PayPal capture result:', JSON.stringify({
                            success: result.success,
                            message: result.message,
                            issue: result.issue,
                            http: result._httpStatus,
                            handoff: result.login_handoff,
                        }));

                        if (!result._httpOk && ((result.message || '').match(/cookie|not allowed/i) || result.code === 'rest_cookie_invalid_nonce')) {
                            await this.refreshNonce();
                            result = await doCapture();
                        }

                        const errorDetail = result?.details?.[0];
                        if (errorDetail?.issue === 'INSTRUMENT_DECLINED' || result?.issue === 'INSTRUMENT_DECLINED') {
                            paypalContainer.innerHTML = '';
                            return actions.restart();
                        }

                        if (result.success) {
                            const paymentModal = document.getElementById('flosc_modal_payment');
                            this.setDisplayState(paymentModal, false, 'flex');

                            if (result.auth_token) {
                                this.config.authToken = result.auth_token;
                                localStorage.setItem('flosc_auth_token', result.auth_token);
                                localStorage.removeItem(this.flowStorageKey('flosc_visitor_messages'));
                            }

                            const displayName = result.user_display_name || result.user_email || 'Member';
                            const defaultMemberLevel = this.config.defaultMemberLevel || 'member';
                            const memberLevel = result.member_level || defaultMemberLevel;
                            if (this.user) {
                                this.user.justPurchased = true;
                                this.user.purchased = true;
                                this.user.memberLevel = memberLevel;
                                this.user.isMember = true;
                                if (result.user_email) this.user.email = result.user_email;
                                if (!this.user.name) this.user.name = displayName;
                            } else {
                                this.user = {
                                    id: result.user_id,
                                    name: displayName,
                                    email: result.user_email || '',
                                    justPurchased: true,
                                    purchased: true,
                                    memberLevel: memberLevel,
                                    isMember: true,
                                };
                            }
                            this.state = 'member';
                            if (this.ivr && this.ivr.context) {
                                this.ivr.context.is_member = true;
                                this.ivr.context.is_guest = false;
                                this.ivr.context.purchased = true;
                                this.ivr.context.first_message_after_purchase = true;
                            }
                            document.body.dataset.userState = 'member';

                            const welcomeMsg = (result.login_handoff === 'email_link_sent')
                                ? '🎉 **Payment successful!** Your membership is active.\n\nA sign-in link has been sent to your purchase email — click it to continue from any device.'
                                : '🎉 **Payment successful!** Welcome to full membership — you now have access.';
                            this.addMessage('assistant', welcomeMsg);
                            setTimeout(() => this.checkAutoMessages(), 2000);
                        } else {
                            throw new Error(result.message || 'Payment capture failed (HTTP ' + (result._httpStatus || '?') + ')');
                        }
                    } catch (err) {
                        this.logError('[FLOSC-CHECKOUT] PayPal capture error:', err);
                        paypalContainer.innerHTML = '';
                        requestAnimationFrame(() => renderPayPalButtons());
                        const errorEl = document.getElementById('card-errors');
                        if (errorEl) errorEl.textContent = err.message || 'Payment failed. Please try again.';
                    }
                },
                onError: (err) => {
                    this.logError('[FLOSC-CHECKOUT] PayPal error:', err);
                    paypalContainer.innerHTML = '';
                    requestAnimationFrame(() => renderPayPalButtons());
                },
                onCancel: () => {
                    this.log('[FLOSC-CHECKOUT] PayPal cancelled by user — re-rendering buttons');
                    paypalContainer.innerHTML = '';
                    requestAnimationFrame(() => renderPayPalButtons());
                },
            });

            // Always render. PayPal is required for this product path — no soft-fail UI.
            paypalButtonsInstance.render(paypalContainer).catch(err => {
                this.logError('[FLOSC-CHECKOUT] PayPal render failed:', err);
            });
            }; // end renderPayPalButtons

            // Ensure container has layout before render (modal may open with 0 size briefly).
            paypalContainer.style.minHeight = '48px';
            const pollAndRender = (attempt = 0) => {
                const rect = paypalContainer.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    renderPayPalButtons();
                } else if (attempt < 40) {
                    setTimeout(() => pollAndRender(attempt + 1), 50);
                } else {
                    renderPayPalButtons();
                }
            };
            requestAnimationFrame(() => pollAndRender());
    }

    // v1.5.4: Process payment from the modal
    async processModalPayment(offerId, payBtn, errorEl) {
        if (this.offers?.checkoutInProgress) return;
        if (this.offers) this.offers.checkoutInProgress = true;

        const originalText = payBtn.querySelector('.flosc-pay-btn-text')?.textContent || 'Pay';
        if (payBtn.querySelector('.flosc-pay-btn-text')) payBtn.querySelector('.flosc-pay-btn-text').textContent = 'Processing...';
        payBtn.disabled = true;

        try {
            const bindingSessionId = (this.currentSession && this.currentSession.id) || this.getVisitorSessionId() || String(Date.now());
            const bindingToken = await this._mintCheckoutBinding(bindingSessionId, 'stripe', offerId);

            // Create PaymentIntent on the server
            const intentRes = await this.authFetch(this.config.apiUrl + '/create-payment-intent', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    flow_id: this.config.flowId || '',
                    coupon_code: this._getCheckoutCouponCodeForCharge(),
                }),
            });

            const intentData = await intentRes.json();

            if (!intentData.client_secret) {
                throw new Error(intentData.message || 'Failed to create payment intent');
            }

            // Confirm payment with Stripe
            const { error, paymentIntent } = await this.stripe.confirmCardPayment(
                intentData.client_secret,
                { payment_method: { card: this.cardElement } }
            );

            if (error) {
                errorEl.textContent = error.message;
                if (payBtn.querySelector('.flosc-pay-btn-text')) payBtn.querySelector('.flosc-pay-btn-text').textContent = originalText;
                payBtn.disabled = false;
                return;
            }

            if (paymentIntent.status === 'succeeded') {
                // Verify and grant access on the server
                await this.authFetch(this.config.apiUrl + '/complete-purchase', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce,
                    },
                    body: JSON.stringify({
                        payment_intent_id: paymentIntent.id,
                        offer_id: offerId,
                        provider: 'stripe',
                        flow_id: this.config.flowId || '',
                        binding_token: bindingToken || '',
                        session_id: bindingSessionId,
                    }),
                });

                // Close modal
                const modal = document.getElementById('flosc_modal_payment');
                this.setDisplayState(modal, false, 'flex');

                // Show success in chat
                this.addMessage('assistant', '🎉 **Payment successful!** Welcome to full membership! Refreshing your access...');

                // Reload to get updated user state
                setTimeout(() => window.location.reload(), 2000);
            }
        } catch (err) {
            this.logError('[FLOSC-CHECKOUT] Payment error:', err);
            errorEl.textContent = err.message || 'Payment failed. Please try again.';
            if (payBtn.querySelector('.flosc-pay-btn-text')) payBtn.querySelector('.flosc-pay-btn-text').textContent = originalText;
            payBtn.disabled = false;
        } finally {
            if (this.offers) this.offers.checkoutInProgress = false;
        }
    }
    
    openShareModal() {
        if (this.shareModal) {
            // Populate share link with current page URL
            const shareLink = document.getElementById('shareLink');
            if (shareLink && !shareLink.value) {
                shareLink.value = window.location.href.split('?')[0];
            }
            this.setDisplayState(this.shareModal, true, 'flex');
        }
    }
    
    handlePromptCard(card) {
        const action = card.dataset.action;
        const text = card.dataset.text || card.textContent.trim();
        
        if (action === 'quiz') {
            this.showRecordingModal();
        } else if (text) {
            this.chatInput.value = text;
            this.sendMessage();
        }
    }
    
    trackEvent(event, data = {}) {
        // v1.7.7: Forward to GA4 if gtag is loaded, otherwise debug-log only
        if (typeof gtag === 'function') {
            gtag('event', event, data);
        }
        this.log('Event:', event, data);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.FLOSC = new floscApp();
    window.floscAppInstance = window.FLOSC; // v9.3.2: Alias for quiz button handlers
});
