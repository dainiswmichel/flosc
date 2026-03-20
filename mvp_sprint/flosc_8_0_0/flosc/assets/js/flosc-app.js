/**
 * FLOSC App JavaScript
 * Main application controller
 * v1.4.2: Flow-aware IVR messages - loadIVRMessages() sends flowId/ivrFile
 */

// v9.4.7: Clear FLOSC-specific localStorage on version change
(function() {
    const FLOSC_JS_VERSION = '1.7.7';
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
        this.state = document.body.dataset.userState || 'visitor';
        
        // v3.0.0: FLOSC Auth Token — cross-domain authentication
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
            const raw = localStorage.getItem('flosc_offer_states');
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    _saveOfferStates() {
        try {
            localStorage.setItem('flosc_offer_states', JSON.stringify(this.ivr.shownThisSession));
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
                // v1.7.0: Load sessions for ALL logged-in users (not just funnel-completed)
                this.log('[FLOSC] Loading sessions...');
                await this.loadSessions();
                
                // v1.7.0: Auto-restore last session if user has one and chat is empty
                if (this.currentSession === null) {
                    await this.restoreLastSession();
                }
            }

            this.freeLessonDelivered = this.user?.freeLessonDelivered || false;

            // v9.0.6: Defer visitor restore until context is built; only restore on returning sessions

            this.trackEvent('page_view');

            // v07.08: Build context and start IVR
            this.log('[FLOSC] Building IVR context...');
            this.buildIVRContext();

            // v8.0.5: checkPendingQuizResults MUST run AFTER buildIVRContext so the
            // context flags it sets (quiz_results_shown, first_message_after_quiz)
            // aren't wiped by buildIVRContext's fresh context object.
            if (this.state !== 'visitor') {
                this.log('[FLOSC] Checking pending quiz results...');
                await this.checkPendingQuizResults();
            }
            this.log('[FLOSC] IVR context built:', this.ivr.context);

            if (this.state === 'visitor') {
                if (this.ivr.context.first_show_session) {
                    this.log('[FLOSC] First session - clearing old visitor messages');
                    try { localStorage.removeItem('flosc_visitor_messages'); } catch(e) { this.logWarn('FLOSC: Could not clear visitor messages', e); }
                } else {
                    this.log('[FLOSC] Restoring visitor messages from previous session...');
                    this.restoreVisitorMessages();
                }
            }
            
            this.log('[FLOSC] Starting IVR...');
            // v1.6.2: initOfferMessages() REMOVED — offers ARE IVR entries, no bridge needed
            this.startIVR();
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
                setTimeout(() => this.showOffer(_upgradeOfferId), 600);
            }

            // Guest link: show welcome message after redirect-back login, with days remaining appended
            if (this.config.guestLinkRemaining !== null && this.config.guestLinkRemaining !== undefined) {
                const days = this.config.guestDaysRemaining;
                const upgradeUrl = this.config.guestLinkUpgradeUrl || '#';
                const daysStr = (days !== null && days !== undefined)
                    ? ` You have <strong>${days}</strong> day${days !== 1 ? 's' : ''} of guest access remaining — we hope you are enjoying your experience as a Complimentary Guest LeSAEp Learner! <a href="${upgradeUrl}">Upgrade for full access here.</a>`
                    : '';
                const welcomeMsg = (this.config.guestLinkWelcomeMessage || '')
                    .replace('{email}', this.user?.email || '')
                    .replace('{n}', this.config.guestLinkRemaining)
                    .replace('{link_name}', this.config.guestLinkName || 'Complimentary LeSAEp Learners Guest Access Link')
                    .replace('{upgrade_url}', this.config.guestLinkUpgradeUrl || '#')
                    + daysStr;
                this.addMessage('assistant', welcomeMsg, true);
            }

            // SSO guest: show days remaining once per browser session
            // (magic link guests get days via guestLinkRemaining block above)
            if (this.config.hasSsoProvider
                && this.config.guestDaysRemaining !== null
                && this.config.guestDaysRemaining !== undefined
                && !sessionStorage.getItem('flosc_sso_guest_days_shown')) {
                const days = this.config.guestDaysRemaining;
                const upgradeUrl = this.config.guestLinkUpgradeUrl || '#';
                const msg = `Welcome back! You have <strong>${days}</strong> day${days !== 1 ? 's' : ''} of guest access remaining — we hope you are enjoying your experience as a Complimentary Guest LeSAEp Learner! <a href="${upgradeUrl}">Upgrade for full access here.</a>`;
                this.addMessage('assistant', msg, true);
                sessionStorage.setItem('flosc_sso_guest_days_shown', 'true');
            }

            // Profile completion prompt — independent of welcome message, persistent across redirects
            if (this.config.pendingCredentialSetup
                && !sessionStorage.getItem('flosc_credential_setup_dismissed')) {
                this.showCredentialSetupCard();
            }

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
            const stored = localStorage.getItem('flosc_quiz_result');
            if (!stored) return false;
            const result = JSON.parse(stored);
            const age = Date.now() - (result.timestamp || 0);
            return age < 3600000;
        } catch (e) { return false; }
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
            quiz_taken: !!(this.user?.lastQuizScore || this.user?.quizCompletedAt || this.quiz?.completedAt),
            // v5.0.3 FIX: Include actual quiz items so AI knows what was missed/correct
            correct_items: this.quiz?.correctItems || [],
            incorrect_items: this.quiz?.missedItems || [],
            // v8.0.0: Track quiz completion and results display state
            quiz_completed: !!(this.user?.lastQuizScore || this.quiz?.completedAt),
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

    injectIVRStyles() {
        const stylesCss = this.config.ivrStylesCss || '';
        if (stylesCss) {
            const style = document.createElement('style');
            style.id = 'flosc-ivr-styles';
            style.textContent = stylesCss;
            document.head.appendChild(style);
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

            // Try to find a welcome message from IVR config
            let welcomeShown = false;
            
            // v1.9.5: When AI is active, route the welcome through AI.
            // AI uses the IVR welcome as guidance and crafts a natural greeting.
            const aiActive = this.config.aiProvider && this.config.aiProvider !== 'ivr';
            
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
            
            // v8.0.0: User has quiz data — show results as welcome (any state)
            if (this.user?.lastQuizData?.quiz_type === 'ipa_audio' && this.user.lastQuizData.phrase_results) {
                this.log('FLOSC: Quiz data present — showing results as welcome');
                this.openQuizResults();
                welcomeShown = true;
            } else if (aiActive) {
                // AI generates the greeting using IVR welcome as inspiration
                this.log('FLOSC: AI active — routing welcome through AI');
                this.showTyping();
                this._generateAIWelcome(welcomeMsg);
                welcomeShown = true;
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
            }

            // v9.5.8: Hide typing indicator after welcome message
            this.hideTyping();

            // Show user autoprompts
            this.floscShowUserAutoPrompts();
            
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

    // v1.6.2: initOfferMessages() DELETED
    // Offers ARE IVR entries — no synthetic bridging needed.
    // The IVR file defines offer entries (type: offer), the parser reads them,
    // the condition evaluator returns them, and they arrive in this.ivr.messages.
    // The admin Offers tab data (config.offers) provides supplementary display fields.

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
            return !!this.ivr.shownThisSession['offer_' + offerId];
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
            <div class="flosc-admin-pill-group" style="background:${cfg.bg};border:1px solid ${cfg.border};border-radius:8px;padding:7px 10px;margin-bottom:5px;">
                <div style="font-size:10px;font-weight:700;color:${cfg.color};text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">${cfg.emoji} ${cfg.label} (${pills.length})</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">${pillsHtml}</div>
            </div>`;
        }

        // Offers section — all offers, including drafts
        let offersHtml = '';
        if (testOffers.length) {
            const btns = testOffers.map(o => {
                const active = (o.status === 'active') ? '✅' : '📝';
                const price  = o.display_price || (o.price ? `$${o.price}` : '');
                return `<button class="flosc-style-pill" data-offer-id="${this.escapeHtml(String(o.id))}"
                            style="background:#fef3c7;border-color:#f59e0b;color:#92400e;">
                            💰 ${active} ${this.escapeHtml(o.name || o.id)}${price ? ' · ' + this.escapeHtml(price) : ''}
                        </button>`;
            }).join('');
            offersHtml = `
            <div class="flosc-admin-pill-group" style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:7px 10px;margin-bottom:5px;">
                <div style="font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">🧪 ALL OFFERS — click to test</div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">${btns}</div>
            </div>`;
        }

        const panel = document.createElement('div');
        panel.id = 'flosc_input_user_autoprompts_panel';
        panel.className = 'prompt-panel prompt-panel-inline';
        panel.innerHTML = `
            <div class="prompt-panel-header" style="padding:6px 10px;border-bottom:1px solid #e5e7eb;cursor:pointer;display:flex;align-items:center;justify-content:space-between;" id="flosc-admin-panel-toggle">
                <div>
                    <div class="prompt-panel-eyebrow" style="font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">🧪 Admin Test Mode — all states visible</div>
                    <div style="font-size:10px;color:#9ca3af;margin-top:2px;">🛒 Purchases: ${this.user?.purchaseCount ?? 0} | 🏷️ Level: ${this.user?.memberLevel || 'none'} | 📊 State: ${this.state || '?'}</div>
                </div>
                <span id="flosc-admin-panel-chevron" style="font-size:16px;color:#6b7280;transition:transform 0.2s;">▼</span>
            </div>
            <div class="prompt-panel-body" id="flosc-admin-panel-body" style="display:flex;flex-direction:column;gap:0;max-height:40vh;overflow-y:auto;padding:5px;">
                ${sectionsHtml}${offersHtml}
                <div class="flosc-admin-pill-group" style="background:#ede9fe;border:1px solid #a78bfa;border-radius:8px;padding:7px 10px;margin-bottom:5px;">
                    <div style="font-size:10px;font-weight:700;color:#5b21b6;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px;">🎤 Quiz Cycle</div>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                        <button class="flosc-style-pill" data-action="open_quiz:lesaep_ipa_audio_quiz" style="background:#ede9fe;border-color:#a78bfa;color:#5b21b6;">🎤 Start Pronunciation Quiz</button>
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
                    const collapsed = body.style.display === 'none';
                    body.style.display = collapsed ? 'flex' : 'none';
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
                    setTimeout(() => this.showOffer(offerId), 300);
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
        // https://github.com/dainismichel/DA1NI5_michel_duck_and_show_chevron
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
                    setTimeout(() => this.showOffer(offerId), 300);
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
                        setTimeout(() => this.showOffer(triggerValue), 300);
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
            prevBtn.style.display = 'flex';
            nextBtn.style.display = 'flex';
        } else {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
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
            track.style.transition = `transform ${ANIMATION_DURATION}ms cubic-bezier(0.25, 0.1, 0.25, 1)`;
            track.style.transform = `translateX(-${scrollAmount}px)`;
            
            setTimeout(() => {
                // Move first item to end
                const firstItem = track.children[0];
                track.appendChild(firstItem);
                
                // Reset transform instantly
                track.style.transition = 'none';
                track.style.transform = 'translateX(0)';
                
                // Force reflow
                track.offsetHeight;
                
                isAnimating = false;
            }, ANIMATION_DURATION);
        };

        // Move last item to beginning (scroll left / prev)
        const rotatePrev = () => {
            if (isAnimating || track.children.length < 2) return;
            isAnimating = true;
            
            const scrollAmount = getScrollAmount();
            
            // Move last item to beginning FIRST (no animation)
            const lastItem = track.children[track.children.length - 1];
            track.insertBefore(lastItem, track.children[0]);
            
            // Set initial offset
            track.style.transition = 'none';
            track.style.transform = `translateX(-${scrollAmount}px)`;
            
            // Force reflow
            track.offsetHeight;
            
            // v2.0.1: Smooth ease-out for natural swipe-like feel
            track.style.transition = `transform ${ANIMATION_DURATION}ms cubic-bezier(0.25, 0.1, 0.25, 1)`;
            track.style.transform = 'translateX(0)';
            
            setTimeout(() => {
                isAnimating = false;
            }, ANIMATION_DURATION);
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

    /**
     * v1.9.5: Generate an AI-powered welcome greeting.
     * Uses the IVR welcome message as guidance so AI crafts a natural,
     * short greeting consistent with the configured product personality.
     * Falls back to IVR content (or hardcoded) if the API call fails.
     */
    async _generateAIWelcome(ivrWelcomeMsg) {
        const productName = this.config.identity?.name || 'FLOSC';
        const badgeUrl = this.config.identity?.badgeUrl || '';
        const badge = badgeUrl ? `<div style="display:flex;justify-content:center;width:calc(100% + 44px);margin:16px 0 16px -44px"><img src="${badgeUrl}" alt="${productName}" style="display:block;max-width:55%;border:3px solid rgba(184,148,68,0.2)"></div>` : '';
        const syntheticMessage = `[SYSTEM: Generate a brief welcome greeting for a new visitor to ${productName}. Vary it each time. First line: a warm welcome to ${productName} and what it stands for. Second line (after badge): what the learner will master. Keep each part to one sentence.]`;
        
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
        const badgeUrl = this.config.identity?.badgeUrl || '';
        const badge = badgeUrl ? `<div style="display:flex;justify-content:center;width:calc(100% + 44px);margin:16px 0 16px -44px"><img src="${badgeUrl}" alt="${productName}" style="display:block;max-width:55%;border:3px solid rgba(184,148,68,0.2)"></div>` : '';
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
            const fallback = this.state === 'visitor'
                ? `Welcome to ${productName}. Learn Excellent Standard American English Pronunciation.\n${badge}\nMaster the sounds that make up clear, confident American English speech.`
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

    // v1.6.2: Bridge from handleAction('show_offer_*') to showOfferMessage()
    showOffer(offerId) {
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
            type: 'offer'
        };
        this.showOfferMessage(msg);
    }

    // MTS-2026-02-03: [OFFER-DISPLAY] Comprehensive offer display system
    // Supports multiple formats: card, pill, compact, banner, featured, text, inline-checkout
    showOfferMessage(msg) {
        const offer = this.getOfferData(msg.offer_id);
        const displayFormat = msg.display_format || offer?.display_format || 'card';
        
        this.log('[FLOSC-OFFER] Showing offer:', msg.offer_id, 'format:', displayFormat);
        
        // Track offer shown
        this.offers.shownOffers.add(msg.offer_id);
        this.ivr.shownThisSession['offer_' + msg.offer_id] = true;
        this._saveOfferStates(); // v1.6.2: Persist across refresh
        
        // v1.6.2: If offer has a content source (HtmlFile/WooProduct/PostID),
        // load it and inject into the offer content before rendering.
        const source = msg.html_file || offer?.html_file 
                     || msg.woo_product || offer?.woo_product
                     || msg.post_id || offer?.post_id;
        if (source) {
            this.loadOfferContentSource(msg, offer, displayFormat);
            return;
        }
        
        this.renderOfferByFormat(msg, offer, displayFormat);
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
    
    // v1.6.2: Get offer data — IVR messages are the primary source
    getOfferData(offerId) {
        // Check cache first
        if (this.offers.loaded[offerId]) {
            return this.offers.loaded[offerId];
        }
        
        // Primary: find in IVR messages (offers ARE IVR entries)
        const ivrMsg = Object.values(this.ivr.messages || {}).find(
            m => m.offer_id === offerId || m.name === offerId
        );
        
        // Secondary: check admin offers for supplementary display fields
        const configOffer = (this.config.offers || []).find(o => o.id === offerId);
        
        // Merge: IVR entry is base, admin offer adds rich display fields
        if (ivrMsg || configOffer) {
            const merged = Object.assign({}, configOffer || {}, ivrMsg || {});
            // Normalize: ensure 'id' is set for downstream code
            merged.id = merged.id || merged.offer_id || offerId;
            this.offers.loaded[offerId] = merged;
            return merged;
        }
        
        return null;
    }
    
    // Format: CARD (default, rich display)
    showOfferCard(msg, offer) {
        let content = this.replaceVariables(msg.content);
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        this.offerStartTime = Date.now();
        const timerSeconds = msg.timer || offer?.timer_seconds || 3600;
        const ctaText = msg.cta || offer?.cta || '🔓 Get Full Access Now';
        const price = offer?.display_price || msg.price || '';

        const offerHtml = `
            <div class="flosc-offer-card" data-offer-id="${msg.offer_id}">
                <button class="flosc-offer-close" aria-label="Dismiss">×</button>
                <div class="flosc-offer-content">${content.replace(/\n/g, '<br>')}</div>
                ${timerSeconds > 0 ? `
                <div class="flosc-offer-timer" id="flosc-offer-timer-${msg.offer_id}">
                    <span class="flosc-timer-icon">⏱️</span>
                    <span class="flosc-timer-value">${this.formatTime(timerSeconds)}</span>
                </div>
                ` : ''}
                <button class="flosc-offer-cta flosc-style-button" data-action="checkout_${msg.offer_id}">
                    ${ctaText} ${price ? `<span style="opacity:0.9">${price}</span>` : ''}
                </button>
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
        const name = offer?.name || msg.name || 'Special Offer';
        const price = offer?.display_price || msg.price || '';
        const badge = offer?.meta?.badge || msg.badge || '';
        
        const pillHtml = `
            <div class="flosc-offer-pill" data-offer-id="${msg.offer_id}" data-action="checkout_${msg.offer_id}">
                <span class="flosc-offer-pill-icon">${icon}</span>
                <span>${name}</span>
                ${price ? `<span class="flosc-offer-pill-price">${price}</span>` : ''}
                ${badge ? `<span class="flosc-offer-pill-badge">${badge}</span>` : ''}
            </div>
        `;
        
        this.addMessage('assistant', pillHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: COMPACT (small card for panels)
    showOfferCompact(msg, offer) {
        const icon = offer?.meta?.icon || msg.icon || '⭐';
        const name = offer?.name || msg.name || 'Special Offer';
        const description = offer?.description || msg.description || '';
        const price = offer?.display_price || msg.price || '';
        const originalPrice = offer?.original_price || msg.original_price || '';
        
        const compactHtml = `
            <div class="flosc-offer-compact" data-offer-id="${msg.offer_id}" data-action="checkout_${msg.offer_id}">
                <span class="flosc-offer-compact-icon">${icon}</span>
                <div class="flosc-offer-compact-content">
                    <div class="flosc-offer-compact-title">${name}</div>
                    ${description ? `<div class="flosc-offer-compact-subtitle">${description}</div>` : ''}
                </div>
                <div>
                    ${originalPrice ? `<span class="flosc-offer-compact-original">${originalPrice}</span>` : ''}
                    <span class="flosc-offer-compact-price">${price}</span>
                </div>
            </div>
        `;
        
        this.addMessage('assistant', compactHtml, true);
        this.bindOfferEvents(msg);
    }
    
    // Format: BANNER (full-width promotional)
    showOfferBanner(msg, offer) {
        const title = offer?.name || msg.name || 'Special Offer';
        const subtitle = offer?.description || msg.description || '';
        const ctaText = msg.cta || offer?.cta || 'Claim Offer';
        const timerSeconds = msg.timer || offer?.timer_seconds || 0;
        
        const bannerHtml = `
            <div class="flosc-offer-banner" data-offer-id="${msg.offer_id}">
                <button class="flosc-offer-banner-close" aria-label="Dismiss">×</button>
                <div class="flosc-offer-banner-content">
                    <div class="flosc-offer-banner-title">${title}</div>
                    ${subtitle ? `<div class="flosc-offer-banner-subtitle">${subtitle}</div>` : ''}
                    ${timerSeconds > 0 ? `
                        <div class="flosc-offer-banner-timer" id="flosc-offer-timer-${msg.offer_id}">
                            ⏱️ <span class="flosc-timer-value">${this.formatTime(timerSeconds)}</span>
                        </div>
                    ` : ''}
                </div>
                <button class="flosc-offer-banner-cta" data-action="checkout_${msg.offer_id}">
                    ${ctaText}
                </button>
            </div>
        `;
        
        this.addMessage('assistant', bannerHtml, true);
        if (timerSeconds > 0) {
            this.startOfferTimer(msg.offer_id, timerSeconds);
        }
        this.bindOfferEvents(msg);
    }
    
    // Format: FEATURED (large prominent card)
    showOfferFeatured(msg, offer) {
        const badge = offer?.meta?.badge || msg.badge || 'Limited Time';
        const title = offer?.name || msg.name || 'Full Access';
        // Use IVR MessageContent (msg.content) if provided — it's the floscAdmin's custom copy
        let description = '';
        if (msg.content) {
            description = this.replaceVariables(msg.content);
            description = description.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            description = description.replace(/\n/g, '<br>');
        } else {
            description = offer?.description || msg.description || '';
        }
        const features = offer?.grants?.features || msg.features || [];
        const price = offer?.display_price || msg.price || '';
        const originalPrice = offer?.original_price || msg.original_price || '';
        const savings = offer?.meta?.savings || msg.savings || '';
        const ctaText = msg.cta || offer?.cta || '🔓 Get Full Access Now';
        const guarantee = msg.guarantee || offer?.guarantee || '';
        
        // Humanize feature identifiers (snake_case → Title Case)
        const humanize = (s) => typeof s === 'string' ? s.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) : (s.name || s);
        const featuresHtml = features.length > 0 ? `
            <div class="flosc-offer-featured-features">
                ${features.slice(0, 5).map(f => `
                    <div class="flosc-offer-featured-feature">
                        <span>✓</span> ${humanize(f)}
                    </div>
                `).join('')}
            </div>
        ` : '';
        
        const featuredHtml = `
            <div class="flosc-offer-featured" data-offer-id="${msg.offer_id}">
                <button class="flosc-offer-close" aria-label="Dismiss">×</button>
                ${badge ? `<div class="flosc-offer-featured-badge">${badge}</div>` : ''}
                <div class="flosc-offer-featured-title">${title}</div>
                ${description ? `<div class="flosc-offer-featured-description">${description}</div>` : ''}
                ${featuresHtml}
                <div class="flosc-offer-featured-pricing">
                    <span class="flosc-offer-featured-price">${price}</span>
                    ${originalPrice ? `<span class="flosc-offer-featured-original">${originalPrice}</span>` : ''}
                    ${savings ? `<span class="flosc-offer-featured-savings">${savings}</span>` : ''}
                </div>
                <button class="flosc-offer-featured-cta" data-action="checkout_${msg.offer_id}">
                    ${ctaText}
                </button>
                ${guarantee ? `<div class="flosc-offer-featured-guarantee">${guarantee}</div>` : ''}
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
        const name = offer?.name || msg.name || 'Full Access';
        const description = offer?.description || msg.description || 'Lifetime access to all content';
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
                    <span class="flosc-checkout-btn-spinner" style="display:none"></span>
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
                    errorEl.style.display = 'block';
                } else {
                    errorEl.style.display = 'none';
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
        spinner.style.display = 'inline-block';
        payBtn.disabled = true;
        
        try {
            // Create payment intent
            const intentResponse = await this.authFetch(this.config.apiUrl + '/create-payment-intent', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({ offer_id: offerId })
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
                            offer_id: offerId
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
            errorEl.style.display = 'block';
            btnText.textContent = `Pay ${price}`;
            spinner.style.display = 'none';
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
                card.style.cursor = 'pointer';
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
            offerEl.style.transition = 'all 0.3s ease';
            offerEl.style.opacity = '0';
            offerEl.style.transform = 'translateY(-10px)';
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
            .replace(/{member_levels}/g, (this.user?.memberLevels || []).join(', ') || 'full access');
    }
    
    // MTS-2026-02-02: [USER-STATUS-FIX] Generate user status response CLIENT-SIDE
    // This runs in the browser where we have access to this.user and this.state
    // The IVR message user_status_check_001 has {user_status_response} which calls this function
    generateUserStatusResponse() {
        const productName = this.config.identity?.name || 'our course';
        const firstName = this.user?.name?.split(' ')[0] || 'there';
        const memberLevels = (this.user?.memberLevels || []).join(', ') || 'full access';
        
        this.log('[FLOSC-STATUS] Generating status response...');
        this.log('[FLOSC-STATUS] this.user:', this.user);
        this.log('[FLOSC-STATUS] this.state:', this.state);
        this.log('[FLOSC-STATUS] isAdmin:', this.user?.isAdmin);
        
        // Check admin first (user object has isAdmin flag set by PHP)
        if (this.user?.isAdmin) {
            this.log('[FLOSC-STATUS] → Returning ADMIN status');
            return `Hey, thanks for asking about your user status! You are the **FLOSC Admin**. You have access to all member levels. Hope you're enjoying the FLOSC experience!`;
        }
        
        // Check member (purchased)
        if (this.state === 'member' || this.user?.purchased) {
            this.log('[FLOSC-STATUS] → Returning MEMBER status');
            return `Hey, thanks for asking about your user status! You are a **Member**. You like to be called **${firstName}** and have access to **${memberLevels}** within "${productName}". Ask me anything about "${productName}" right here in this chat!`;
        }
        
        // Check guest (logged in but not purchased)
        if (this.state === 'guest' || (this.user?.id && !this.user?.purchased)) {
            this.log('[FLOSC-STATUS] → Returning GUEST status');
            return `Hey, thanks for asking about your user status! You are a **Guest**. You like to be called **${firstName}**. Check out your free lesson and upgrade for full access to "${productName}"!`;
        }
        
        // Default: visitor (not logged in)
        this.log('[FLOSC-STATUS] → Returning VISITOR status');
        return `Hey, thanks for asking about your user status! You are a **Visitor**. Take our free quiz and create an account to unlock personalized learning!`;
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
                this.startInChatQuiz(actionParam || 'default');
                break;
            case 'open_registration':
                this.openRegistration();
                break;
            case 'open_login_modal':
                this.showLoginModal();
                break;
            case 'open_free_lesson':
                this.openFreeLesson();
                break;
            case 'open_lesson_library':
                this.openLessonLibrary();
                break;
            case 'open_personalized_path':
                this.openPersonalizedPath();
                break;
            case 'resume_last_lesson':
            case 'open_last_lesson':
                this.resumeLastLesson();
                break;
            case 'repeat_last_lesson':
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
            case 'logout':
                this.addMessage('assistant', 'See you later LeSAEp Fam! 👋');
                // Clear flosc token cookie client-side — handles cross-domain AJAX gap
                document.cookie = 'flosc_auth_token=; Max-Age=0; path=/; SameSite=Lax';
                fetch((this.config.ajaxUrl || '/wp-admin/admin-ajax.php') + '?action=flosc_logout', { method: 'POST' })
                    .then(r => r.json())
                    .then(data => {
                        setTimeout(() => {
                            window.location.href = (data.data && data.data.redirect) ? data.data.redirect : (this.config.appUrl || '/');
                        }, 2000);
                    })
                    .catch(() => {
                        setTimeout(() => { window.location.href = this.config.appUrl || '/'; }, 2000);
                    });
                break;
            // v1.4.0: Sandbox purchase actions
            case 'send_prompt':
                if (actionParam) {
                    this.chatInput.value = actionParam;
                    this.sendMessage();
                }
                break;
            case 'open_sandbox_purchase':
            case 'sandbox_purchase':
                this.openSandboxPurchase();
                break;
            case 'show_offer':
                if (actionParam) {
                    this.showOffer(actionParam);
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
                    this.openSandboxPurchaseForProduct(productId);
                } else if (action.startsWith('show_offer_')) {
                    const offerId = action.replace('show_offer_', '');
                    this.showOffer(offerId);
                }
                break;
        }
    }
    
    // v1.4.0: Open sandbox purchase for current flow's product
    openSandboxPurchase() {
        const productId = this.config.productId || this.detectProductFromFlow();
        const offerId = this.getOfferIdForProduct(productId);
        this.showSandboxPayment(offerId, productId);
    }
    
    // v1.4.0: Open sandbox purchase for specific product
    openSandboxPurchaseForProduct(productId) {
        const offerId = this.getOfferIdForProduct(productId);
        this.showSandboxPayment(offerId, productId);
    }
    
    // v1.4.0: Detect product from current IVR flow
    detectProductFromFlow() {
        const ivrFile = this.config.ivrFile || '';
        
        if (ivrFile.includes('simplified_solfeggio')) {
            return 'simplified_solfeggio';
        } else if (ivrFile.includes('lesaep')) {
            return 'lesaep';
        } else if (ivrFile.includes('flosc_default') || ivrFile.includes('flosc_technical')) {
            return 'flosc_plugin';
        }
        
        return ''; // Default/generic sandbox
    }
    
    // v1.4.0: Get offer ID for product
    // v3.0.5: Reads from config offers instead of hardcoded map
    getOfferIdForProduct(productId) {
        // Find the first active offer from admin config
        const offers = this.config.offers || [];
        const active = offers.find(o => (o.status === 'active' || o.active) && o.id !== 'free_trial');
        if (active) return active.id;
        
        // Legacy hardcoded fallback
        const productOfferMap = {
            'flosc_plugin': 'flosc_plugin_full',
            'simplified_solfeggio': 'simplified_solfeggio_full',
            'lesaep': 'lesaep_full',
        };
        
        return productOfferMap[productId] || 'lesaep_full';
    }

    openQuiz() {
        // v9.3.4: Start in-chat quiz
        this.startInChatQuiz();
    }

    // v9.3.4: In-Chat Quiz System - Now supports TEXT SEQUENCE and AUDIO types!
    async startInChatQuiz(quizId = 'default') {
        // v1.8.1: Guard — prevent duplicate quiz starts
        // v8.0.0: Allow replacing an active quiz with a DIFFERENT quiz type
        //         (e.g., user started text quiz but wants IPA audio quiz instead)
        if (this.quiz?.active) {
            if (this.quiz.id === quizId || quizId === 'default') {
                this.addMessage('assistant', 'You already have a quiz in progress. Please complete it above.');
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

        // LeSAEp IPA Audio Quiz — direct to LeSAEp API, no WP involvement
        if (quizId === 'lesaep_ipa_audio_quiz') {
            this.showQuizConsentGate();
            return;
        }
        
        // Show loading message
        this.addMessage('assistant', '📋 Loading your quiz...');
        
        try {
            // Fetch quiz from API — passes flow context so server picks the right quiz
            // v3.0.7: include flow_id + ivr_file so get_quiz_questions() can auto-select
            //         lesaep_text_based_pronunciation_quiz for the LeSAEp flow when no explicit setting is saved
            const quizParams = new URLSearchParams({ id: quizId });
            if (this.config.flowId)  quizParams.append('flow_id',  this.config.flowId);
            if (this.config.ivrFile) quizParams.append('ivr_file', this.config.ivrFile);
            const response = await this.authFetch(`${this.config.apiUrl}/quiz?${quizParams.toString()}`, { credentials: 'same-origin' });
            const data = await response.json();
            
            this.log('[FLOSC Quiz] API response:', data);
            
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
        
        // Apply score circle styling after render
        setTimeout(() => {
            const circle = document.querySelector('.flosc-quiz-score-circle[data-score]');
            if (circle) {
                const s = parseInt(circle.dataset.score, 10);
                circle.style.background = `conic-gradient(#10b981 ${s * 3.6}deg, #e5e7eb ${s * 3.6}deg)`;
            }
        }, 50);
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
    
    async startAudioQuizRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.recordingStream = stream;
            this.audioChunks = [];
            
            this.mediaRecorder = new MediaRecorder(stream);
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
        const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', blob, 'quiz-audio.webm');
        
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
                <div class="flosc-quiz-score-circle" style="--score-percent: ${score}%">
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
    // LeSAEp IPA Pronunciation Quiz — v8.0.0
    // Direct browser → LeSAEp API (api.lesaep.com via nginx on 443)
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

    async checkMicAndStartQuiz() {
        // Test mic access before starting quiz
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(t => t.stop());
            this.showQuizTierSelection();
        } catch (e) {
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            let guidance = 'Please allow microphone access to take the pronunciation quiz.';
            if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                if (isSafari) {
                    guidance = '**Microphone blocked.** On Safari, go to <strong>Settings → Safari → Microphone</strong> and allow access for this site, then tap the button below.';
                } else {
                    guidance = '**Microphone blocked.** Click the 🔒 icon in your address bar, allow Microphone, then tap the button below.';
                }
            } else if (e.name === 'NotFoundError') {
                guidance = 'No microphone detected. Please connect a microphone and try again.';
            }

            const retryHtml = `
                <div class="flosc-consent-card">
                    <div class="flosc-consent-text">${guidance}</div>
                    <button class="flosc-consent-btn" id="flosc-mic-retry">🎤 Try Again</button>
                </div>
            `;
            this.addMessage('assistant', retryHtml, true);

            setTimeout(() => {
                const retryBtn = document.getElementById('flosc-mic-retry');
                if (retryBtn) {
                    retryBtn.addEventListener('click', () => this.checkMicAndStartQuiz());
                }
            }, 100);
        }
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
        // \u2705 = da1ni5 APPROVED by Dainis | \u26A0\uFE0F = placeholder (espeak copy, awaiting approval)
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

        this.quiz = {
            active: true,
            id: 'lesaep_ipa_audio_quiz',
            title: 'LeSAEp Pronunciation Assessment',
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

        if (this.isRecording) {
            if (this.mediaRecorder) this.mediaRecorder.stop();
            this.isRecording = false;
            this.stopWaveformVisualizer();
            if (this.recordingStream) {
                this.recordingStream.getTracks().forEach(t => t.stop());
            }
            if (btn) {
                const thankEmojis = ['❤️', '✨', '🙏', '😍', '💖', '💕', '🌟', '😊', '🥰', '💛', '💜', '🫶'];
                btn.textContent = thankEmojis[Math.floor(Math.random() * thankEmojis.length)];
                btn.classList.remove('recording', 'flosc-ipa-record-pulse');
                btn.classList.add('completed');
                btn.disabled = true;
            }
            // Mark current step as completed (gray + green checkmark)
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
            // v8.0.1: Auto-scroll so user sees the analyzing state
            requestAnimationFrame(() => {
                if (this.chatMessages) {
                    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
                }
            });
            this.showIpaFlyoff(phraseNum);
            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.recordingStream = stream;
            this.audioChunks = [];

            const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
            let mimeType = '';
            for (const t of types) {
                if (MediaRecorder.isTypeSupported(t)) { mimeType = t; break; }
            }

            this.mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
            const actualMime = this.mediaRecorder.mimeType;
            let audioFormat = 'webm';
            if (actualMime.includes('mp4')) audioFormat = 'mp4';
            else if (actualMime.includes('ogg')) audioFormat = 'ogg';

            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) this.audioChunks.push(e.data);
            };

            this.mediaRecorder.onstop = () => {
                const blob = new Blob(this.audioChunks, { type: actualMime });
                const audioUrl = URL.createObjectURL(blob);
                this.processIpaRecording(blob, audioUrl, audioFormat, phraseNum);
            };

            this.mediaRecorder.start();
            this.isRecording = true;

            if (btn) { btn.textContent = '⏹ Stop'; btn.classList.remove('flosc-ipa-record-pulse'); btn.classList.add('recording'); }
            if (status) status.textContent = 'Recording... tap Stop when done';

            // Start waveform visualizer
            this.startWaveformVisualizer(stream, phraseNum);

        } catch (e) {
            this.logError('[FLOSC IPA] Mic access failed', e);
            if (status) status.textContent = 'Could not access microphone. Please allow mic access and try again.';
        }
    }

    // WhatsApp-style scrolling waveform — bars appear on the right edge and
    // scroll left as you speak.  Each bar's height = amplitude at that moment.
    startWaveformVisualizer(stream, phraseNum) {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
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
            canvas.style.display = 'block';

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
            // Start near the record button with slight random spread
            el.style.left = (rect.left + Math.random() * 40 - 20) + 'px';
            el.style.top = (rect.top + Math.random() * 20 - 10) + 'px';
            el.style.animationDelay = (i * 0.08) + 's';
            el.style.opacity = (0.30 + Math.random() * 0.43).toFixed(2);
            document.body.appendChild(el);

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

        const buf = await blob.arrayBuffer();
        const bytes = new Uint8Array(buf);
        let binary = '';
        for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
        const b64 = btoa(binary);

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
            return fetch(`https://api.lesaep.com${endpoint}`, {
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
                        this.config.authEscapeTitle = 'Create Your LeSAEp Account';
                        this.config.authEscapeSubtitle = 'Sign up to unlock full pronunciation lessons and practice.';
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
            h += `<div class="flosc-ipa-word-head"><span class="flosc-ipa-word">${this.escapeHtml(w.word)}</span><span class="flosc-ipa-word-score" style="color:${cc(a)}">${(a * 100).toFixed(0)}%</span></div>`;

            h += `<div class="flosc-ipa-rows">`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">espeak-ng</span><span class="flosc-ipa-val flosc-ipa-espeak">[${this.escapeHtml(ipaData.espeak || '')}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">merriam-webster</span><span class="flosc-ipa-val flosc-ipa-mw">[${this.escapeHtml(ipaData.mw || '')}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">da1ni5</span><span class="flosc-ipa-val flosc-ipa-da1ni5">[${this.escapeHtml((ipaData.da1ni5 || []).join(' | '))}]</span></div>`;
            h += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">scored as</span><span class="flosc-ipa-val flosc-ipa-scored">[${this.escapeHtml(w.expected_ipa)}]</span></div>`;
            h += `</div>`;

            w.phonemes.forEach(p => {
                const pct = (p.confidence * 100).toFixed(1);
                const barW = Math.max(1, p.confidence * 100);
                h += `<div class="flosc-ipa-ph"><span class="flosc-ipa-ph-sym">${this.escapeHtml(p.ipa)}</span><div class="flosc-ipa-ph-track"><div class="flosc-ipa-ph-fill flosc-ipa-ph-${cl(p.confidence)}" style="width:${barW}%"></div></div><span class="flosc-ipa-ph-pct" style="color:${cc(p.confidence)}">${pct}%</span></div>`;
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
        summary += `<div class="flosc-quiz-score-circle" style="--score-percent: ${score}%"><span class="flosc-quiz-score-value">${score}%</span></div>`;
        summary += `<div class="flosc-ipa-final-stats">${allWords.length} words &middot; ${total} phonemes across ${phraseResults.length} phrases</div>`;

        if (weakest.length > 0) {
            summary += `<div class="flosc-ipa-weak-title">Sounds to focus on:</div>`;
            summary += `<div class="flosc-ipa-weak-list">`;
            weakest.forEach(w => {
                const color = w.avg >= 0.5 ? 'var(--flosc-ipa-high)' : w.avg >= 0.1 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';
                summary += `<span class="flosc-ipa-weak-item"><span class="flosc-ipa-weak-sym">${this.escapeHtml(w.ipa)}</span> <span style="color:${color}">${(w.avg * 100).toFixed(0)}%</span></span>`;
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
                accordion += `<span class="flosc-ipa-accordion-score" style="color:${cc(phAvg)}">${phScore}%</span>`;
                accordion += `</summary>`;
                accordion += `<div class="flosc-ipa-accordion-body">`;

                words.forEach(w => {
                    const wAvg = w.phonemes.length ? w.phonemes.reduce((s, p) => s + p.confidence, 0) / w.phonemes.length : 0;
                    const key = w.word.toLowerCase();
                    const ipaData = wordIpa[key] || {};

                    accordion += `<div class="flosc-ipa-word-card">`;
                    accordion += `<div class="flosc-ipa-word-head"><span class="flosc-ipa-word">${this.escapeHtml(w.word)}</span><span class="flosc-ipa-word-score" style="color:${cc(wAvg)}">${(wAvg * 100).toFixed(0)}%</span></div>`;

                    accordion += `<div class="flosc-ipa-rows">`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">espeak-ng</span><span class="flosc-ipa-val flosc-ipa-espeak">[${this.escapeHtml(ipaData.espeak || '')}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">merriam-webster</span><span class="flosc-ipa-val flosc-ipa-mw">[${this.escapeHtml(ipaData.mw || '')}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">da1ni5</span><span class="flosc-ipa-val flosc-ipa-da1ni5">[${this.escapeHtml((ipaData.da1ni5 || []).join(' | '))}]</span></div>`;
                    accordion += `<div class="flosc-ipa-row"><span class="flosc-ipa-label">scored as</span><span class="flosc-ipa-val flosc-ipa-scored">[${this.escapeHtml(w.expected_ipa)}]</span></div>`;
                    accordion += `</div>`;

                    w.phonemes.forEach(p => {
                        const pct = (p.confidence * 100).toFixed(1);
                        const barW = Math.max(1, p.confidence * 100);
                        accordion += `<div class="flosc-ipa-ph"><span class="flosc-ipa-ph-sym">${this.escapeHtml(p.ipa)}</span><div class="flosc-ipa-ph-track"><div class="flosc-ipa-ph-fill flosc-ipa-ph-${cl(p.confidence)}" style="width:${barW}%"></div></div><span class="flosc-ipa-ph-pct" style="color:${cc(p.confidence)}">${pct}%</span></div>`;
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
            h += `<div class="flosc-quiz-score-circle" style="--score-percent: ${score}%"><span class="flosc-quiz-score-value">${score}%</span></div>`;
            h += `<div class="flosc-ipa-final-stats">${allWords.length} words &middot; ${total} phonemes across ${results.length} phrases</div>`;

            if (weakest.length > 0) {
                h += `<div class="flosc-ipa-weak-title">Sounds to focus on:</div>`;
                h += `<div class="flosc-ipa-weak-list">`;
                weakest.forEach(w => {
                    const color = w.avg >= 0.5 ? 'var(--flosc-ipa-high)' : w.avg >= 0.1 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';
                    h += `<span class="flosc-ipa-weak-item"><span class="flosc-ipa-weak-sym">${this.escapeHtml(w.ipa)}</span> <span style="color:${color}">${(w.avg * 100).toFixed(0)}%</span></span>`;
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
                    const phColor = phraseScore >= 70 ? 'var(--flosc-ipa-high)' : phraseScore >= 40 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';
                    d += `<details class="flosc-ipa-accordion-item">`;
                    d += `<summary class="flosc-ipa-accordion-header"><span class="flosc-ipa-accordion-phrase">${this.escapeHtml(r.phrase)}</span><span class="flosc-ipa-accordion-score" style="color:${phColor}">${phraseScore}%</span></summary>`;
                    d += `<div class="flosc-ipa-accordion-body">`;
                    phraseWords.forEach(w => {
                        d += `<div class="flosc-ipa-word-row"><strong>${this.escapeHtml(w.word)}</strong>`;
                        d += `<div class="flosc-ipa-phoneme-list">`;
                        (w.phonemes || []).forEach(p => {
                            const c = p.confidence >= 0.5 ? 'var(--flosc-ipa-high)' : p.confidence >= 0.1 ? 'var(--flosc-ipa-med)' : 'var(--flosc-ipa-low)';
                            d += `<span class="flosc-ipa-phoneme-chip" style="border-color:${c}"><span class="flosc-ipa-weak-sym">${this.escapeHtml(p.ipa)}</span> <span style="color:${c}">${(p.confidence * 100).toFixed(0)}%</span></span>`;
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
            fetch('https://api.lesaep.com/finalize-session', {
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
                    <div class="flosc-quiz-progress-bar" style="width: ${progressPercent}%"></div>
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
            opt.style.pointerEvents = 'none';
            opt.style.opacity = '0.6';
        });
        
        // Highlight selected
        buttonEl.style.opacity = '1';
        buttonEl.style.borderColor = '#0ea5e9';
        buttonEl.style.background = '#e0f2fe';

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
        
        // v5.0.3 FIX: Also set on this.user so buildIVRContext() picks it up
        this.user = this.user || {};
        this.user.lastQuizScore = scorePercent;
        this.user.quizCompletedAt = Date.now();

        // Show results
        const ctaText = this.state === 'visitor'
            ? 'Create free account to see detailed results'
            : 'View your personalized recommendations';

        const ctaAction = this.state === 'visitor'
            ? `onclick="window.floscAppInstance.openRegistration()"`
            : `onclick="window.floscAppInstance.openFreeLesson()"`;

        const resultHtml = `
            <div class="flosc-quiz-result ${scoreClass}">
                <div class="flosc-quiz-result-score">${scorePercent}%</div>
                <div class="flosc-quiz-result-label">${scoreMessage}</div>
                <button class="flosc-quiz-result-cta" ${ctaAction}>
                    ${ctaText} →
                </button>
            </div>
        `;

        this.addMessage('assistant', resultHtml, true);

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

            localStorage.setItem('flosc_quiz_result', JSON.stringify(quizResult));

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
                                style="--sso-bg: ${p.colors.background}; --sso-text: ${p.colors.text}; --sso-border: ${p.colors.border || p.colors.background};">
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
                    <button class="flosc-auth-close" onclick="window.floscAppInstance.hideAuthModal()">
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
                    <div style="text-align:center;margin-top:14px;">
                        <a href="#" style="color:#aaa;font-size:12px;text-decoration:none;" class="flosc-access-code-auth-trigger">Access Code</a>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if present
        const existing = document.getElementById('flosc-auth-modal');
        if (existing) existing.remove();
        
        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Add modal styles inline (one-time)
        this.addAuthModalStyles();
        
        // Bind form submission
        document.getElementById('flosc-auth-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const email = document.getElementById('flosc-auth-email').value;
            this.processEmailAuth(email);
        });
        
        // Bind SSO button clicks
        document.querySelectorAll('.flosc-sso-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const provider = btn.dataset.provider;
                const authUrl = btn.dataset.authUrl;
                this.initiateSSO(provider, authUrl);
            });
        });

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
    
    addAuthModalStyles() {
        if (document.getElementById('flosc-auth-modal-styles')) return;
        
        const styles = `
            <style id="flosc-auth-modal-styles">
                .flosc-auth-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.6);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                    backdrop-filter: blur(4px);
                }
                .flosc-auth-modal {
                    background: var(--flosc-surface, #fff);
                    border-radius: 16px;
                    padding: 32px;
                    width: 90%;
                    max-width: 400px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    position: relative;
                    color: var(--flosc-text, #1a1a1a);
                }
                .flosc-auth-close {
                    position: absolute;
                    top: 16px;
                    right: 16px;
                    background: none;
                    border: none;
                    color: var(--flosc-text-secondary, #666);
                    cursor: pointer;
                    padding: 4px;
                    border-radius: 4px;
                }
                .flosc-auth-close:hover {
                    background: var(--flosc-surface-hover, #f0f0f0);
                }
                .flosc-auth-header {
                    text-align: center;
                    margin-bottom: 24px;
                }
                .flosc-auth-header h2 {
                    margin: 0 0 8px 0;
                    color: #1fad0d;
                    font-weight: bold;
                    font-family: 'Atkinson Hyperlegible Next', sans-serif;
                    font-size: 28px;
                }
                .flosc-auth-header p {
                    margin: 0;
                    color: var(--flosc-text-secondary, #888);
                    font-family: 'Atkinson Hyperlegible Next', sans-serif;
                    font-size: 16px;
                }
                .flosc-auth-form {
                    margin-bottom: 20px;
                }
                .flosc-auth-field {
                    margin-bottom: 16px;
                }
                .flosc-auth-field label {
                    display: block;
                    margin-bottom: 6px;
                    font-size: 14px;
                    font-weight: 500;
                }
                .flosc-auth-field input {
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid var(--flosc-border, #ddd);
                    border-radius: 8px;
                    font-size: 16px;
                    background: var(--flosc-input-bg, #fff);
                    color: var(--flosc-text, #1a1a1a);
                    box-sizing: border-box;
                }
                .flosc-auth-field input:focus {
                    outline: none;
                    border-color: var(--flosc-primary, #4f46e5);
                    box-shadow: 0 0 0 3px var(--flosc-primary-light, rgba(79, 70, 229, 0.1));
                }
                .flosc-auth-submit {
                    width: 100%;
                    padding: 14px 24px;
                    background: var(--flosc-primary, #4f46e5);
                    color: white;
                    border: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .flosc-auth-submit:hover {
                    background: var(--flosc-primary-hover, #4338ca);
                }
                .flosc-auth-divider {
                    position: relative;
                    text-align: center;
                    margin: 20px 0;
                }
                .flosc-auth-divider::before,
                .flosc-auth-divider::after {
                    content: "";
                    position: absolute;
                    top: 50%;
                    width: 40%;
                    height: 1px;
                    background: var(--flosc-border, #ddd);
                }
                .flosc-auth-divider::before { left: 0; }
                .flosc-auth-divider::after { right: 0; }
                .flosc-auth-divider span {
                    background: var(--flosc-surface, #fff);
                    padding: 0 12px;
                    color: var(--flosc-text-secondary, #666);
                    font-size: 13px;
                }
                .flosc-sso-buttons {
                    display: flex;
                    flex-direction: column;
                    gap: 10px;
                }
                .flosc-sso-btn {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid var(--sso-border, #ddd);
                    border-radius: 8px;
                    background: var(--sso-bg, #fff);
                    color: var(--sso-text, #333);
                    font-size: 14px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: all 0.2s;
                }
                .flosc-sso-btn:hover {
                    opacity: 0.9;
                    transform: translateY(-1px);
                }
                .flosc-sso-icon {
                    display: flex;
                    align-items: center;
                    flex-shrink: 0;
                }
                .flosc-sso-icon svg {
                    width: 20px;
                    height: 20px;
                    display: block;
                }
                .flosc-auth-terms {
                    text-align: center;
                    font-size: 12px;
                    color: var(--flosc-text-secondary, #888);
                    margin-top: 16px;
                    margin-bottom: 0;
                }
            </style>
        `;
        document.head.insertAdjacentHTML('beforeend', styles);
    }
    
    async processEmailAuth(email) {
        this.log('[FLOSC Auth] Processing email auth:', email);
        
        // Read config from the active modal
        const modal = document.getElementById('flosc-auth-modal');
        const configKey = modal?.dataset?.configKey || 'authModal';
        const loadingText = this.config[configKey + 'LoadingText'] || 'Sending link...';
        const buttonText = this.config[configKey + 'ButtonText'] || 'Continue with Email';
        const checkEmailMsg = (this.config.guestLinkCheckEmailMessage || "We've sent you a {link_name} to your email — click it to access this chat as a guest and view your quiz score, free lessons, and a special upgrade offer.")
            .replace('{link_name}', this.config.guestLinkName || 'Complimentary LeSAEp Learners Guest Access Link');
        
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
            // v8.0.8: Send browser-computed quiz data so PHP stores instantly (no re-scoring)
            const regBody = { email };
            if (this.ipaQuiz?.tempId) {
                regBody.temp_id = this.ipaQuiz.tempId;
            }
            // Attach quiz results from localStorage so server stores them in user meta
            try {
                const stored = localStorage.getItem('flosc_quiz_result');
                if (stored) {
                    const parsed = JSON.parse(stored);
                    if (parsed && parsed.phraseResults) {
                        regBody.quiz_data = {
                            score: parsed.score,
                            phraseResults: parsed.phraseResults,
                            wordIpa: parsed.wordIpa || null,
                            rankedPhonemes: parsed.rankedPhonemes || null,
                            quizType: parsed.quizType || 'ipa_audio',
                            tempId: parsed.tempId || this.ipaQuiz?.tempId || null
                        };
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

        const cardHtml = `
            <div class="flosc-profile-card" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px;max-width:360px;font-family:inherit;">
                <p style="margin:0 0 12px;font-size:15px;font-weight:600;color:#111827;">What should I call you?</p>
                <input type="text" class="flosc-profile-name" placeholder="First name or nickname..."
                    style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;margin-bottom:16px;outline:none;">
                <p style="margin:0 0 8px;font-size:14px;color:#374151;">Set a password so you can log in directly:</p>
                <div style="display:flex;gap:8px;margin-bottom:4px;">
                    <input type="text" class="flosc-profile-password" value="${suggestedPassword}"
                        style="flex:1;box-sizing:border-box;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;outline:none;font-family:monospace;">
                    <button class="flosc-profile-copy" title="Copy password" style="padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;background:#f9fafb;font-size:13px;cursor:pointer;white-space:nowrap;">Copy</button>
                </div>
                <p style="margin:0 0 16px;font-size:12px;color:#6b7280;">You'll receive this by email too — you can change it anytime.</p>
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="flosc-profile-save" style="background:#2563eb;color:#fff;border:none;border-radius:8px;padding:9px 20px;font-size:14px;font-weight:600;cursor:pointer;">Save & Continue</button>
                    <a href="#" class="flosc-profile-skip" style="font-size:13px;color:#6b7280;text-decoration:none;">Skip for now</a>
                </div>
                <p class="flosc-profile-error" style="display:none;margin:10px 0 0;font-size:13px;color:#dc2626;"></p>
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
            sessionStorage.setItem('flosc_credential_setup_dismissed', 'true');
            card.closest('.message')?.remove();
        });

        saveBtn?.addEventListener('click', async () => {
            const name     = nameEl?.value?.trim();
            const password = passEl?.value?.trim();
            if (!name) { nameEl?.focus(); return; }
            if (!password || password.length < 6) {
                if (errEl) { errEl.textContent = 'Password must be at least 6 characters.'; errEl.style.display = 'block'; }
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
                    if (errEl) { errEl.textContent = result.message || 'Could not save — please try again.'; errEl.style.display = 'block'; }
                    saveBtn.disabled    = false;
                    saveBtn.textContent = 'Save →';
                }
            } catch (e) {
                if (errEl) { errEl.textContent = 'Could not save — please try again.'; errEl.style.display = 'block'; }
                saveBtn.disabled    = false;
                saveBtn.textContent = 'Save →';
            }
        });
    }

    initiateSSO(provider, authUrl) {
        this.log('[FLOSC SSO] Initiating SSO with:', provider);
        
        // v8.0.0: Store pending DO session_id in a cookie before leaving for OAuth.
        // When the user returns via handle_login_token(), PHP reads this cookie and
        // calls pull_session_from_do() immediately — no fragile client-side POST needed.
        // The cookie is on the current domain (lesaep.com), survives the OAuth round-trip,
        // and is available when we land back on lesaep.com with the login token.
        try {
            const stored = localStorage.getItem('flosc_quiz_result');
            if (stored) {
                const result = JSON.parse(stored);
                if (result.sessionId) {
                    document.cookie = `flosc_pending_session=${encodeURIComponent(result.sessionId)};path=/;max-age=3600;SameSite=Lax`;
                    this.log('[FLOSC SSO] Set flosc_pending_session cookie:', result.sessionId);
                }
            }
        } catch (e) {
            // Non-critical — server-side pull won't fire, client POST is fallback
        }

        // Add redirect_to parameter so user comes back here
        // v1.4.6: Use & if URL already has query params (e.g. non-pretty permalinks)
        const redirectTo = window.location.href;
        const separator = authUrl.includes('?') ? '&' : '?';
        const fullAuthUrl = `${authUrl}${separator}redirect_to=${encodeURIComponent(redirectTo)}`;
        
        // Redirect to SSO provider
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

    openFreeLesson() {
        this.requestFreeLesson();
    }

    async openLessonLibrary() {
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
                             onclick="window.FLOSC.viewLesson(${parseInt(lesson.id)})">
                            <span class="flosc-lesson-item-title">${this.escapeHtml(lesson.title)}</span>
                            <span class="flosc-lesson-item-arrow">›</span>
                        </div>
                    `).join('')}
                </div>
                ${hasMore ? `<button class="flosc-load-more-btn" data-list-id="${listId}" style="display:block;width:100%;padding:10px;margin-top:4px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:6px;cursor:pointer;font-size:13px;color:#4b5563;">Show more (${sorted.length - PAGE_SIZE} remaining)</button>` : ''}
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
                div.onclick = () => window.FLOSC.viewLesson(parseInt(lesson.id));
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
                        <button class="flosc-back-to-lessons-btn" onclick="window.FLOSC.openLessonLibrary()">
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

    // v1.8.1: Show quiz options in-chat instead of navigating away to /quizzes/
    openQuizLibrary() {
        const quizHtml = `
            <div class="flosc-quiz-library">
                <p><strong>Available Quizzes</strong></p>
                <p>Choose a quiz to test your skills:</p>
                <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
                    <button class="flosc-quiz-result-cta" onclick="window.floscAppInstance.startInChatQuiz('default')">
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
    // v8.0.8: Now handles IPA audio quiz data from this.user.lastQuizData.
    openQuizResults() {
        // v8.0.8: Check for IPA audio quiz data (from server scoring via user meta)
        const serverData = this.user?.lastQuizData;
        if (serverData && serverData.quiz_type === 'ipa_audio' && serverData.phrase_results) {
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

        // v5.0.3: Read from in-session this.quiz first (has actual item names),
        // fall back to this.user (server-populated from user_meta).
        // v8.0.8: Coerce empty string to null — WordPress returns '' for unset meta
        const rawScore = this.quiz?.score ?? this.user?.lastQuizScore ?? null;
        const score = (rawScore !== null && rawScore !== '') ? parseInt(rawScore, 10) : null;
        if (score === null || isNaN(score)) {
            // Check if scoring is still pending
            const stored = localStorage.getItem('flosc_quiz_result');
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
        const quizType = this.config.ivrFile && this.config.ivrFile.includes('lesaep')
            ? 'lesaep' : 'default';

        if (quizType === 'lesaep') {
            const html = `
                <div class="flosc-quiz-topics">
                    <h3>🎤 LeSAEp Quiz — 10 Sound Topics</h3>
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
                           onkeyup="this.value = this.value.replace(/[^0-9,]/g, '')">
                </div>
                <div class="flosc-sandbox-presets">
                    <button onclick="document.getElementById('flosc-sandbox-amount').value='9.99'">$9.99</button>
                    <button onclick="document.getElementById('flosc-sandbox-amount').value='99'">$99</button>
                    <button onclick="document.getElementById('flosc-sandbox-amount').value='999'">$999</button>
                    <button onclick="document.getElementById('flosc-sandbox-amount').value='1,000,000'">$1M</button>
                    <button onclick="document.getElementById('flosc-sandbox-amount').value='1,000,000,000'">$1B 🚀</button>
                </div>
                <button class="flosc-sandbox-pay-btn" onclick="window.floscAppInstance.processSandboxPayment('${offerId}', '${productId}')">
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
                        <p style="opacity: 0.9;">You now have <strong>${memberLevel}</strong> membership!</p>
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

    // MTS-2026-02-03: [CHECKOUT] Enhanced checkout with multiple payment methods
    openCheckout(offerId) {
        const offer = this.getOfferData(offerId);
        this.log('[FLOSC-CHECKOUT] Opening checkout for offer:', offerId, offer);
        
        // Track checkout initiated
        this.trackEvent('checkout_initiated', { offer_id: offerId });
        
        // Determine payment method
        const pricing = offer?.pricing || {};
        // v3.0.7: Read processor from offer config; fall back to capability detection
        const processor = pricing.processor || '';

        // Free offer — grant access without payment
        if (processor === 'free' || (Number(pricing.price) === 0 && processor !== 'paypal' && processor !== 'stripe')) {
            this.addMessage('assistant', '🎁 Granting free access — no payment needed!');
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

        // External redirect checkout
        if (processor === 'redirect' || pricing.redirect_url || offer?.checkout_url) {
            const redirectUrl = pricing.redirect_url || offer.checkout_url;
            this.addMessage('assistant', `Redirecting you to checkout... You'll be brought back here after payment.`);
            localStorage.setItem('flosc_pending_purchase', JSON.stringify({
                offer_id: offerId,
                timestamp: Date.now(),
                return_url: window.location.href
            }));
            setTimeout(() => { window.location.href = redirectUrl; }, 1500);
            return;
        }

        // v3.0.7: PayPal or Stripe — show payment modal.
        // Check both the offer's explicit processor setting and global availability.
        const wantsPayPal = processor === 'paypal' || (!processor && this.config.paypalClientId);
        const wantsStripe = processor === 'stripe' || (!processor && this.config.stripeKey);

        if (wantsPayPal || wantsStripe) {
            // Check if we should use inline checkout (already in chat)
            const inlineCheckout = document.querySelector(`.flosc-checkout-inline[data-offer-id="${offerId}"]`);
            if (inlineCheckout) {
                const cardEl = document.getElementById(`flosc-inline-card-${offerId}`);
                if (cardEl) cardEl.focus();
                return;
            }
            this.showPaymentModal(offerId);
        } else {
            // v1.7.0: Fallback to sandbox purchase instead of external checkout page
            this.openSandboxPurchase();
        }
    }
    
    // Check for returning from external checkout
    checkPendingPurchase() {
        try {
            const pending = localStorage.getItem('flosc_pending_purchase');
            if (pending) {
                const data = JSON.parse(pending);
                // Only process if recent (within 1 hour)
                if (Date.now() - data.timestamp < 3600000) {
                    this.log('[FLOSC-CHECKOUT] Checking pending purchase:', data.offer_id);
                    // The server should have updated user state via webhook
                    // Just clear the pending state
                }
                localStorage.removeItem('flosc_pending_purchase');
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
            // Route through performIVRAction with the same action string the autoprompt pills
            // and IVR messages use — ensures the correct quiz loads (e.g., lesaep_ipa_audio_quiz).
            // Falls back to 'default' only if no IVR quiz action is configured.
            const quizAction = this.config.ivrQuizAction || 'open_quiz:lesaep_ipa_audio_quiz';
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
        if (this._debug) console.log('[FLOSC]', ...args);
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
        // Line breaks
        html = html.replace(/\n/g, '<br>');
        return html;
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
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
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
            if (window.innerWidth > 768) {
                const overlay = document.getElementById('flosc_app_sidebar_overlay');
                if (overlay) overlay.classList.remove('show');
                if (this.sidebar) this.sidebar.classList.remove('open');
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
            
            this.chatInput.addEventListener('input', () => {
                this.chatInput.style.height = 'auto';
                this.chatInput.style.height = Math.min(this.chatInput.scrollHeight, 120) + 'px';
            });
        }
        
        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', () => this.openShareModal());
        }

        // Share modal close handlers
        const shareModalClose = document.getElementById('shareModalClose');
        if (shareModalClose) {
            shareModalClose.addEventListener('click', () => {
                if (this.shareModal) this.shareModal.style.display = 'none';
            });
        }
        if (this.shareModal) {
            this.shareModal.addEventListener('click', (e) => {
                if (e.target === this.shareModal) this.shareModal.style.display = 'none';
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
        // not hardcoded. LeSAEp uses 'lesaep_full', other flows use their own IDs.
        const upgradeBtn = document.getElementById('flosc_upgrade_button');
        if (upgradeBtn) {
            const upgradeOfferId = this.getOfferIdForProduct();
            upgradeBtn.addEventListener('click', () => this.showOffer(upgradeOfferId));
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
        
        if (textPanel) textPanel.style.display = tabType === 'text' ? 'block' : 'none';
        if (audioPanel) audioPanel.style.display = tabType === 'audio' ? 'block' : 'none';
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
        document.getElementById('floscQuizTextPanel')?.style.setProperty('display', 'none');
        document.getElementById('floscQuizAudioPanel')?.style.setProperty('display', 'none');
        document.querySelector('.quiz-tabs')?.style.setProperty('display', 'none');
        
        // Show result panel
        const resultPanel = document.getElementById('floscQuizResultPanel');
        if (resultPanel) {
            resultPanel.style.display = 'block';
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
            quizId: this.quiz.id || 'flosc_sample_data_numbers_quiz'
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
        localStorage.setItem('flosc_quiz_result', JSON.stringify(quizData));
        
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
                    quiz_id: this.quiz.id || 'flosc_sample_data_numbers_quiz',
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
        document.querySelector('.quiz-tabs')?.style.setProperty('display', 'flex');
        document.getElementById('floscQuizTextPanel')?.style.setProperty('display', 'block');
        document.getElementById('floscQuizAudioPanel')?.style.setProperty('display', 'none');
        document.getElementById('floscQuizResultPanel')?.style.setProperty('display', 'none');
        
        // Reset tab buttons
        document.querySelectorAll('.quiz-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === 'text');
        });
        
        // Clear input
        const input = document.getElementById('floscQuizTextInput');
        if (input) input.value = '';
        
        // Reset recording UI
        document.getElementById('floscQuizRecordButton')?.style.setProperty('display', 'inline-flex');
        document.getElementById('floscQuizStopButton')?.style.setProperty('display', 'none');
        document.getElementById('floscQuizSubmitRecordingButton')?.style.setProperty('display', 'none');
        const status = document.getElementById('floscQuizRecordingStatus');
        if (status) status.textContent = '';
    }
    
    // v9.3.3: Start quiz audio recording
    async startQuizRecording() {
        try {
            this.quizRecordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.quizMediaRecorder = new MediaRecorder(this.quizRecordingStream);
            this.quizAudioChunks = [];
            
            this.quizMediaRecorder.ondataavailable = (e) => {
                this.quizAudioChunks.push(e.data);
            };
            
            this.quizMediaRecorder.start();
            
            // Update UI
            document.getElementById('floscQuizRecordButton')?.style.setProperty('display', 'none');
            document.getElementById('floscQuizStopButton')?.style.setProperty('display', 'inline-flex');
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
            document.getElementById('floscQuizStopButton')?.style.setProperty('display', 'none');
            document.getElementById('floscQuizSubmitRecordingButton')?.style.setProperty('display', 'inline-flex');
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
        
        const audioBlob = new Blob(this.quizAudioChunks, { type: 'audio/webm' });
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

    restartChat() {
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

        // Clear session tracking (use same pattern as buildIVRContext)
        const sessionKey = 'flosc_session_' + this.getSessionKey();
        localStorage.removeItem(sessionKey);

        // Clear visitor messages if visitor
        if (this.state === 'visitor') {
            localStorage.removeItem('flosc_visitor_messages');
        }

        // Rebuild context
        this.buildIVRContext();

        // Restart IVR
        this.startIVR();

        this.log('FLOSC: Chat restarted');
    }
    
    setupUI() {
        // v1.7.0: Restore sidebar collapsed state on desktop
        if (this.sidebar && window.innerWidth > 768) {
            const wasCollapsed = localStorage.getItem('flosc_sidebar_collapsed') === 'true';
            if (wasCollapsed) {
                this.sidebar.classList.add('collapsed');
            }
        }

        // v1.8.0: Populate profile bar — same bar for all states, content toggled via data-show
        if (this.user && this.state !== 'visitor') {
            const profileAvatar = document.getElementById('flosc_profile_avatar');
            const profileName = document.getElementById('flosc_profile_name');
            const profileBadge = document.getElementById('flosc_profile_badge');
            const dropdownName = document.getElementById('flosc_dropdown_name');
            const dropdownEmail = document.getElementById('flosc_dropdown_email');

            if (profileAvatar && this.user.avatar) {
                profileAvatar.src = this.user.avatar;
                profileAvatar.alt = this.user.name || 'User avatar';
            }

            if (profileName && this.user.name) {
                profileName.textContent = this.user.name;
            }

            if (profileBadge) {
                // Read badge text from admin-configured data attributes on the profile bar
                const bar = document.getElementById('flosc_user_profile_bar');
                if (this.state === 'member') {
                    profileBadge.textContent = bar?.dataset.memberBadge || 'Member';
                } else {
                    profileBadge.textContent = bar?.dataset.guestBadge || 'Guest';
                }
            }

            if (dropdownName && this.user.name) {
                dropdownName.textContent = this.user.name;
            }

            if (dropdownEmail && this.user.email) {
                dropdownEmail.textContent = this.user.email;
            }
        }
    }

    // v1.7.9: Initialize visitor bar for non-logged-in users
    initVisitorBar() {
        const visitorBar = document.getElementById('floscVisitorBar');
        if (!visitorBar) return;

        // Check if dismissed in this session
        const dismissed = sessionStorage.getItem('flosc_visitor_bar_dismissed');
        if (dismissed) return;

        // Show bar after 2 second delay
        setTimeout(() => {
            visitorBar.style.display = 'block';
        }, 2000);

        // CTA click - start quiz
        const ctaBtn = document.getElementById('floscVisitorBarCta');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', () => {
                this.sendMessage('Start quiz');
                visitorBar.style.display = 'none';
                sessionStorage.setItem('flosc_visitor_bar_dismissed', 'true');
            });
        }

        // Dismiss button
        const dismissBtn = document.getElementById('floscVisitorBarDismiss');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', () => {
                visitorBar.style.display = 'none';
                sessionStorage.setItem('flosc_visitor_bar_dismissed', 'true');
            });
        }
    }

    toggleSidebar() {
        if (this.sidebar) {
            const isMobile = window.innerWidth <= 768;
            const overlay = document.getElementById('flosc_app_sidebar_overlay');
            if (isMobile) {
                this.sidebar.classList.toggle('open');
                if (overlay) {
                    overlay.classList.toggle('show', this.sidebar.classList.contains('open'));
                }
            } else {
                // v2.0.5: Clean up mobile overlay state when toggling in desktop mode
                if (overlay) overlay.classList.remove('show');
                this.sidebar.classList.remove('open');
                this.sidebar.classList.toggle('collapsed');
                localStorage.setItem('flosc_sidebar_collapsed', this.sidebar.classList.contains('collapsed'));
            }
        }
    }
    
    async sendMessage(directMessage = null, { executeActions = true } = {}) {
        const message = directMessage?.trim() || this.chatInput?.value?.trim();
        if (!message) return;
        
        // Clear input
        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';
        
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
        
        // v3.0.5: Check if user message matches any offer's reveal_phrase (exact match).
        // Exact match happens client-side — no API call needed. AI interpretation
        // is handled server-side via the chat dispatch.
        const phraseMatchOffer = this._matchOfferRevealPhrase(message);
        if (phraseMatchOffer) {
            this.log('[FLOSC-OFFER] Reveal phrase matched offer:', phraseMatchOffer.id);
            setTimeout(() => this.showOffer(phraseMatchOffer.id), 300);
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
                response = await this.callAPI(message, ivrGuidance);
            } catch (firstErr) {
                // v8.0.0 FIX: Retry once with fresh nonce — handles stale-nonce after
                // registration page reload or long idle sessions.
                this.log('[FLOSC] Chat failed, refreshing nonce and retrying:', firstErr.message);
                await this.refreshNonce().catch(() => {});
                response = await this.callAPI(message, ivrGuidance);
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
                    this.addMessage('assistant', "I'm having trouble responding right now. Please try again.");
                }
            }
        } catch (error) {
            this.logError('FLOSC: API error:', error);
            this.hideTyping();
            // On error, fall back to raw IVR if we had a match
            const fallback = ivrGuidance || ivrMatch;
            if (fallback) {
                const content = this.replaceVariables(fallback.content);
                this.addMessage('assistant', content);
                if (fallback.action && executeActions) this.performIVRAction(fallback.action);
            } else {
                this.addMessage('assistant', "I'm having trouble responding right now. Please try again.");
            }
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
    
    addMessage(role, content, isHtml = false) {
        this.log('[FLOSC] addMessage() called:', {role, contentLength: content?.length, isHtml});

        if (!this.chatMessages) {
            this.logError('[FLOSC] ERROR: chatMessages container not found!');
            return null;
        }

        this.log('[FLOSC] Creating message element...');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;

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
        flagBtn.className = 'flosc-correction-flag';
        flagBtn.title = 'Flag this response for improvement';
        flagBtn.innerHTML = '⚑';
        flagBtn.addEventListener('click', () => {
            this.showCorrectionModal(userMessage, aiResponse);
        });

        wrapper.appendChild(praiseBtn);
        wrapper.appendChild(flagBtn);
        contentDiv.appendChild(wrapper);
    }

    /**
     * v1.9.0: Show a modal for admin to submit a correction for a bad AI response.
     */
    showCorrectionModal(userMessage, aiResponse) {
        // Remove any existing modal
        const existing = document.getElementById('flosc-correction-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'flosc-correction-modal';
        modal.className = 'flosc-correction-modal-overlay';
        modal.innerHTML = `
            <div class="flosc-correction-modal">
                <div class="flosc-correction-modal-header">
                    <h3>Flag AI Response</h3>
                    <button class="flosc-correction-modal-close">&times;</button>
                </div>
                <div class="flosc-correction-modal-body">
                    <div class="flosc-correction-field">
                        <label>User said:</label>
                        <div class="flosc-correction-preview">${this.escapeHtml(userMessage)}</div>
                    </div>
                    <div class="flosc-correction-field">
                        <label>AI responded:</label>
                        <div class="flosc-correction-preview flosc-correction-bad">${this.escapeHtml(aiResponse.substring(0, 500))}</div>
                    </div>
                    <div class="flosc-correction-field">
                        <label for="flosc-correction-note">What was wrong? <span class="required">*</span></label>
                        <textarea id="flosc-correction-note" rows="3" placeholder="e.g. Too pushy, wrong information, off-topic..."></textarea>
                    </div>
                    <div class="flosc-correction-field">
                        <label for="flosc-correction-preferred">How should the AI have responded? <span class="optional">(optional)</span></label>
                        <textarea id="flosc-correction-preferred" rows="3" placeholder="Write the ideal response here..."></textarea>
                    </div>
                </div>
                <div class="flosc-correction-modal-footer">
                    <button class="flosc-correction-cancel">Cancel</button>
                    <button class="flosc-correction-submit">Save Correction</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close handlers
        modal.querySelector('.flosc-correction-modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.flosc-correction-cancel').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        // Submit handler
        modal.querySelector('.flosc-correction-submit').addEventListener('click', async () => {
            const note = document.getElementById('flosc-correction-note').value.trim();
            const preferred = document.getElementById('flosc-correction-preferred').value.trim();

            if (!note) {
                document.getElementById('flosc-correction-note').focus();
                return;
            }

            const submitBtn = modal.querySelector('.flosc-correction-submit');
            submitBtn.textContent = 'Saving...';
            submitBtn.disabled = true;

            try {
                const res = await this.authFetch(this.config.apiUrl + '/corrections', {
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
                this.logError('[FLOSC] Correction save error:', err);
                submitBtn.textContent = 'Error — try again';
                submitBtn.disabled = false;
            }
        });
    }
    
    /**
     * v1.9.0: Show a modal for admin to praise a good AI response.
     */
    showPraiseModal(userMessage, aiResponse) {
        const existing = document.getElementById('flosc-correction-modal');
        if (existing) existing.remove();

        const modal = document.createElement('div');
        modal.id = 'flosc-correction-modal';
        modal.className = 'flosc-correction-modal-overlay';
        modal.innerHTML = `
            <div class="flosc-correction-modal">
                <div class="flosc-correction-modal-header flosc-praise-header">
                    <h3>Praise AI Response</h3>
                    <button class="flosc-correction-modal-close">&times;</button>
                </div>
                <div class="flosc-correction-modal-body">
                    <div class="flosc-correction-field">
                        <label>User said:</label>
                        <div class="flosc-correction-preview">${this.escapeHtml(userMessage)}</div>
                    </div>
                    <div class="flosc-correction-field">
                        <label>AI responded:</label>
                        <div class="flosc-correction-preview flosc-praise-good">${this.escapeHtml(aiResponse.substring(0, 500))}</div>
                    </div>
                    <div class="flosc-correction-field">
                        <label for="flosc-praise-note">What was good about this? <span class="required">*</span></label>
                        <textarea id="flosc-praise-note" rows="3" placeholder="e.g. Perfect tone, great explanation, helpful and not pushy..."></textarea>
                    </div>
                </div>
                <div class="flosc-correction-modal-footer">
                    <button class="flosc-correction-cancel">Cancel</button>
                    <button class="flosc-correction-submit flosc-praise-submit-btn">Save Praise</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelector('.flosc-correction-modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.flosc-correction-cancel').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        modal.querySelector('.flosc-correction-submit').addEventListener('click', async () => {
            const note = document.getElementById('flosc-praise-note').value.trim();

            if (!note) {
                document.getElementById('flosc-praise-note').focus();
                return;
            }

            const submitBtn = modal.querySelector('.flosc-correction-submit');
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
    
    async callAPI(message, ivrMatch = null) {
        // v1.7.0: Auto-create session on first message for logged-in users
        if (this.state !== 'visitor' && !this.currentSession) {
            try {
                const sessionRes = await this.authFetch(this.config.apiUrl + '/sessions', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': this.config.nonce
                    },
                    body: JSON.stringify({ title: message.substring(0, 40), first_chat: true })
                });
                const sessionData = await sessionRes.json();
                if (sessionData.success && sessionData.session) {
                    this.currentSession = sessionData.session;
                    this.log('[FLOSC] Auto-created session:', this.currentSession.id, this.currentSession.title);
                    // Refresh sidebar session list
                    this.loadSessions();
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
            session_id: this.currentSession?.id,
            context: this.ivr.context,
            // v1.3.7: Flow context for multi-flow support
            flow_id: this.config.flowId,
            ivr_file: this.config.ivrFile
        };
        
        // v2.0.7: Send visitor conversation history so AI has memory across messages.
        // Visitors have no server-side session, so we send localStorage history.
        // This prevents AI from repeating itself and enables conversation-awareness.
        if (this.state === 'visitor') {
            try {
                const visitorMsgs = JSON.parse(localStorage.getItem('flosc_visitor_messages') || '[]');
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

        const data = await response.json();

        if (!response.ok) {
            const errorMsg = data.error || `Server error (${response.status})`;
            throw new Error(errorMsg);
        }

        if (!data.success) {
            const errorMsg = data.error || 'Unknown API error';
            throw new Error(errorMsg);
        }

        return data.message;
    }
    
    saveVisitorMessage(role, content) {
        try {
            const messages = JSON.parse(localStorage.getItem('flosc_visitor_messages') || '[]');
            messages.push({ role, content, timestamp: Date.now() });
            if (messages.length > 50) messages.shift();
            localStorage.setItem('flosc_visitor_messages', JSON.stringify(messages));
        } catch (e) {
            this.logWarn('FLOSC: Could not save visitor message', e);
        }
    }
    
    restoreVisitorMessages() {
        try {
            const messages = JSON.parse(localStorage.getItem('flosc_visitor_messages') || '[]');
            messages.forEach(msg => {
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
    
    async loadSessions() {
        try {
            const response = await this.authFetch(this.config.apiUrl + '/sessions', {
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
        
        // v8.0.11: Session management by access level
        // Visitors: never see sidebar sessions (loadSessions not called)
        // Guests: see their chat list, read-only (no rename/delete)
        // Members/Admins: full ChatGPT-style management — rename, delete
        const canManage = (this.state === 'member' || this.state === 'admin');
        
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
                html += `<div class="flosc-session-item ${s.id === this.currentSession?.id ? 'active' : ''}" 
                     data-session-id="${s.id}">
                    <span class="flosc-session-item-icon">💬</span>
                    <span class="flosc-session-item-title">${this.escapeHtml(s.title || 'New Chat')}</span>`;
                if (canManage) {
                    html += `<span class="flosc-session-actions">
                        <button class="flosc-session-action flosc-session-rename" data-session-id="${s.id}" title="Rename">✏️</button>
                        <button class="flosc-session-action flosc-session-delete" data-session-id="${s.id}" title="Delete">🗑️</button>
                    </span>`;
                }
                html += `</div>`;
            });
            html += '</div>';
        }
        
        this.sessionList.innerHTML = html || '<div style="padding: 16px; text-align: center; font-size: 13px; color: #999;">No chats yet</div>';
        
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
            const response = await this.authFetch(this.config.apiUrl + '/sessions/' + sessionId, {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (data.success) {
                this.currentSession = data.session;
                // Clear chat and show session messages
                const inner = this.chatMessages?.querySelector('.messages-inner');
                if (inner) inner.innerHTML = '';
                else if (this.chatMessages) this.chatMessages.innerHTML = '';
                
                data.session.messages.forEach(msg => {
                    this.addMessage(msg.role, msg.content);
                });
                
                // Update active state in sidebar
                this.sessionList?.querySelectorAll('.flosc-session-item').forEach(item => {
                    item.classList.toggle('active', item.dataset.sessionId == sessionId);
                });
                
                // Close sidebar on mobile after selection
                if (window.innerWidth <= 768 && this.sidebar) {
                    this.sidebar.classList.remove('open');
                }
            }
        } catch (e) {
            this.logWarn('FLOSC: Could not load session', e);
        }
    }
    
    newSession() {
        // v1.7.0: Reset to a fresh chat (session will be auto-created on first message)
        this.currentSession = null;
        const inner = this.chatMessages?.querySelector('.messages-inner');
        if (inner) inner.innerHTML = '';
        else if (this.chatMessages) this.chatMessages.innerHTML = '';
        
        this.ivr.messageCount = 0;
        this.ivr.shownThisSession = {};
        this.ivr.context.first_show_session = true;
        this.buildIVRContext();
        this.checkAutoMessages();
        this.floscShowUserAutoPrompts();
        
        // Deselect all sessions in sidebar
        this.sessionList?.querySelectorAll('.flosc-session-item').forEach(item => {
            item.classList.remove('active');
        });
    }

    async renameSession(sessionId) {
        const item = this.sessionList?.querySelector(`.flosc-session-item[data-session-id="${sessionId}"]`);
        const titleEl = item?.querySelector('.flosc-session-item-title');
        const currentTitle = titleEl?.textContent || 'New Chat';
        const newTitle = prompt('Rename chat:', currentTitle);
        if (!newTitle || newTitle === currentTitle) return;

        try {
            const response = await this.authFetch(this.config.apiUrl + '/sessions/' + sessionId, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({ title: newTitle })
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
            const response = await this.authFetch(this.config.apiUrl + '/sessions/' + sessionId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            if (data.success) {
                if (this.currentSession?.id == sessionId) {
                    this.newSession();
                }
                this.loadSessions();
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
            const response = await this.authFetch(this.config.apiUrl + '/sessions', {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (!data.success) return;
            
            // Find the most recent session (first item in 'today', then 'yesterday', etc.)
            const groups = ['today', 'yesterday', 'last_7_days', 'older'];
            let latestSession = null;
            
            for (const group of groups) {
                if (data.sessions[group] && data.sessions[group].length > 0) {
                    latestSession = data.sessions[group][0];
                    break;
                }
            }
            
            if (latestSession && latestSession.messages && latestSession.messages.length > 0) {
                this.log('[FLOSC] Restoring last session:', latestSession.id, latestSession.title);
                await this.loadSession(latestSession.id);
            }
        } catch (e) {
            this.logWarn('[FLOSC] Could not restore last session:', e);
        }
    }
    
    async checkPendingQuizResults() {
        // v8.0.8: This method sets IVR context flags ONLY. It does NOT render results.
        // Results are presented through the IVR message flow:
        //   - lesaep_login_success fires (conditioned on first_message_after_login)
        //   - guest_quiz_review autoprompt pill appears (conditioned on is_guest && quiz_taken)
        //   - Guest clicks pill → show_quiz_results action → openQuizResults() renders results
        // Previously this method called showIpaPhraseResultsAfterLogin() directly,
        // dumping results into chat before the IVR flow had a chance to run.
        try {
            const stored = localStorage.getItem('flosc_quiz_result');
            if (stored) {
                const result = JSON.parse(stored);
                const age = Date.now() - (result.timestamp || 0);
                if (age < 86400000) { // 24 hours
                    this.log('[FLOSC] checkPendingQuizResults: found stored result, setting context flags');

                    // Set context flags so IVR messages and autoprompt pills can fire
                    this.ivr.context.quiz_completed = true;
                    this.ivr.context.quiz_taken = true;
                    this.ivr.context.first_message_after_quiz = true;
                    if (result.score) this.ivr.context.score = result.score;
                    if (result.quizId) this.ivr.context.quiz_id = result.quizId;

                    // Check if server already scored (email reg path scores during registration)
                    const serverData = this.user?.lastQuizData;
                    if (serverData && serverData.quiz_type === 'ipa_audio' && serverData.phrase_results) {
                        this.log('[FLOSC] Server already has scored IPA data — storing wordIpa for openQuizResults()');
                        // Merge wordIpa from localStorage into server data so openQuizResults() has it
                        if (result.wordIpa && !serverData.word_ipa) {
                            serverData.word_ipa = result.wordIpa;
                        }
                        this.ivr.context.score = serverData.score;
                        localStorage.removeItem('flosc_quiz_result');
                    } else if (result.pendingServerScore && result.phraseResults) {
                        // SSO path: data is in localStorage. Populate this.user.lastQuizData
                        // immediately so openQuizResults() can render without waiting for server.
                        this.log('[FLOSC] SSO path: loading quiz data from localStorage');
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

                        // Persist to server in background (non-blocking). If it fails,
                        // results still display from this.user.lastQuizData above.
                        this.authFetch(`${this.config.restUrl}store-quiz-data`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                quiz_data: {
                                    score: result.score,
                                    phraseResults: result.phraseResults,
                                    wordIpa: result.wordIpa || null,
                                    rankedPhonemes: result.rankedPhonemes || null,
                                    quizType: 'ipa_audio'
                                },
                                session_id: result.sessionId || null
                            })
                        }).then(r => {
                            if (!r.ok) {
                                this.logError('[FLOSC] store-quiz-data HTTP ' + r.status);
                                return r.text().then(t => { throw new Error(t); });
                            }
                            return r.json();
                        }).then(res => {
                            if (res && res.success) {
                                this.log('[FLOSC] Quiz data persisted to server');
                                localStorage.removeItem('flosc_quiz_result');
                            }
                        }).catch(err => this.logError('[FLOSC] store-quiz-data failed', err));
                    } else if (this.user?.lastQuizScore) {
                        // Server has a score but no full phrase data
                        const score = parseInt(this.user.lastQuizScore) || 0;
                        this.ivr.context.score = score;
                        localStorage.removeItem('flosc_quiz_result');
                    } else if (!result.pendingServerScore) {
                        // Non-IPA quiz with results already in localStorage
                        localStorage.removeItem('flosc_quiz_result');
                    }
                }
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
            modal.style.display = 'flex';
            // v9.3.3: Reset quiz state when opening
            this.resetQuizModal();
        }
    }
    
    hideRecordingModal() {
        const modal = document.getElementById('flosc_modal_recording');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    async requestFreeLesson() {
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
        // when frontend (e.g. lesaep.com) and WP backend (e.g. dainis.net) are on different domains.
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
            setTimeout(() => this.checkAutoMessages(), 2000);
            return;
        }

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
                this.addMessage('assistant', 'It looks like your account doesn\'t have access to free lessons yet. If you just completed the quiz, please try refreshing the page and asking again.', false);
                return;
            }

            const lessons = data.lessons || (data.lesson ? [data.lesson] : []);

            if (data.success && lessons.length > 0) {
                this.ivr.context.lesson_viewed = true;
                this.ivr.context.first_message_after_free_lesson = true;
                this._cachedFreeLessons = lessons;
                this._renderFreeLessonCards(lessons);
                this.ivr.phase = 'offer';
                setTimeout(() => this.checkAutoMessages(), 2000);
            } else {
                this.log('FLOSC: Free lesson request unsuccessful — no lessons returned');
                this.addMessage('assistant', 'I wasn\'t able to load your free lessons right now. Please try again in a moment.', false);
            }
        } catch (e) {
            this.hideTyping();
            this.logError('FLOSC: Free lesson request failed', e);
            this.addMessage('assistant', 'Something went wrong loading your free lessons. Please try again.', false);
        } finally {
            this._freeLessonInFlight = false;
        }
    }

    // v8.0.1: Extracted card rendering — used by both fresh fetch and cache re-render
    _renderFreeLessonCards(lessons) {
        let cardHtml = `<div class="flosc-free-lesson-list">`;
        cardHtml += `<p class="flosc-free-lesson-intro">Here are your free lessons. Click on them to view.</p>`;
        lessons.forEach((lesson, i) => {
            cardHtml += `<button class="flosc-free-lesson-card" onclick="window.floscAppInstance.showFreeLessonContent(${i})">`
                + `<span class="flosc-free-lesson-card-icon">\ud83c\udf93</span>`
                + `<span class="flosc-free-lesson-card-title">${this.escapeHtml(lesson.title)}</span>`
                + `</button>`;
        });
        cardHtml += `</div>`;
        this.addMessage('assistant', cardHtml, true);
    }

    // v8.0.0: Render full lesson content when user clicks a title card
    showFreeLessonContent(index) {
        const lessons = this._cachedFreeLessons;
        if (!lessons || !lessons[index]) {
            this.addMessage('assistant', 'Sorry, that lesson isn\'t available right now.', false);
            return;
        }
        const lesson = lessons[index];
        const lessonHtml = `<div class="flosc-free-lesson">`
            + `<h3 class="flosc-free-lesson-title">${this.escapeHtml(lesson.title)}</h3>`
            + `<div class="flosc-free-lesson-content">${lesson.content}</div>`
            + `</div>`;
        this.addMessage('assistant', lessonHtml, true);
    }
    
    initStripe() {
        if (window.Stripe && this.config.stripeKey) {
            this.stripe = Stripe(this.config.stripeKey);
        }
    }
    
    showPaymentModal(offerId) {
        const modal = document.getElementById('flosc_modal_payment');
        if (!modal) return;

        modal.style.display = 'flex';
        modal.dataset.offerId = offerId;

        const offer = this.getOfferData(offerId);
        const isSubscription = (offer?.type === 'subscription') || (offer?.subscription?.plans);
        
        // Update modal header from offer data
        const priceEl = document.getElementById('paymentPrice');
        if (priceEl && offer) {
            const price = offer.display_price || (offer.price ? `$${offer.price}` : '') || (offer.pricing?.price ? `$${offer.pricing.price}` : '');
            if (price) priceEl.textContent = price;
        }
        const nameEl = modal.querySelector('.flosc-product-name');
        if (nameEl && offer?.name) {
            nameEl.textContent = offer.name;
        }
        const descEl = modal.querySelector('.flosc-product-desc');
        if (descEl && offer?.description) {
            descEl.textContent = offer.description;
        }
        
        const paypalContainer = document.getElementById('paypal-button-container');
        const separator = document.getElementById('payment-separator');
        const stripeForm = document.getElementById('stripe-payment-form');
        const payBtn = document.getElementById('payBtn');
        
        const hasPayPal = !!this.config.paypalClientId && typeof paypal !== 'undefined';

        if (!hasPayPal) {
            modal.style.display = 'none';
            this.openSandboxPurchase();
            return;
        }

        // Hide Stripe for now (PayPal subscriptions only)
        if (stripeForm) stripeForm.style.display = 'none';
        if (payBtn) payBtn.style.display = 'none';
        if (separator) separator.style.display = 'none';

        if (hasPayPal && paypalContainer) {
            paypalContainer.style.display = 'block';
            paypalContainer.innerHTML = '';

            if (isSubscription) {
                // ====================================================
                // SUBSCRIPTION FLOW — plan picker + PayPal sub buttons
                // ====================================================
                this._renderSubscriptionCheckout(offerId, offer, paypalContainer);
            } else {
                // ====================================================
                // ONE-TIME FLOW — existing createOrder / capture flow
                // ====================================================
                this._renderOneTimePayPal(offerId, paypalContainer);
            }
        }

        // Close button
        const closeBtn = document.getElementById('paymentModalClose');
        if (closeBtn) {
            closeBtn.onclick = () => { modal.style.display = 'none'; };
        }
    }

    // v8.0.0: Show access code input — replaces modal content with a code entry field
    _showAccessCodeInput(context) {
        if (context === 'payment') {
            const modal = document.getElementById('flosc_modal_payment');
            if (!modal) return;
            const body = modal.querySelector('.flosc-modal-body');
            if (!body) return;
            body.innerHTML = `
                <div style="text-align:center;padding:20px 0;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:16px;">Enter Access Code</div>
                    <input type="text" id="flosc-access-code-input" maxlength="20" autocomplete="off" spellcheck="false"
                           style="font-size:18px;text-align:center;letter-spacing:3px;padding:10px 16px;border:2px solid #d1d5db;border-radius:8px;width:180px;text-transform:uppercase;"
                           placeholder="CODE">
                    <div style="margin-top:14px;">
                        <button id="flosc-access-code-submit" style="background:#10b981;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Submit</button>
                    </div>
                    <div id="flosc-access-code-error" style="color:#dc2626;font-size:13px;margin-top:10px;"></div>
                </div>
            `;
            document.getElementById('flosc-access-code-input').focus();
            document.getElementById('flosc-access-code-submit').addEventListener('click', () => {
                const code = document.getElementById('flosc-access-code-input').value.trim();
                if (code) this._redeemAccessCode(code, 'payment');
            });
            document.getElementById('flosc-access-code-input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const code = e.target.value.trim();
                    if (code) this._redeemAccessCode(code, 'payment');
                }
            });
        } else if (context === 'auth') {
            const modal = document.getElementById('flosc-auth-modal');
            if (!modal) return;
            const inner = modal.querySelector('.flosc-auth-modal');
            if (!inner) return;
            inner.innerHTML = `
                <button class="flosc-auth-close" onclick="window.floscAppInstance.hideAuthModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div style="text-align:center;padding:20px 0;">
                    <div style="font-size:15px;font-weight:600;margin-bottom:16px;">Enter Access Code</div>
                    <input type="text" id="flosc-access-code-input" maxlength="20" autocomplete="off" spellcheck="false"
                           style="font-size:18px;text-align:center;letter-spacing:3px;padding:10px 16px;border:2px solid #d1d5db;border-radius:8px;width:180px;text-transform:uppercase;"
                           placeholder="CODE">
                    <div style="margin-top:14px;">
                        <button id="flosc-access-code-submit" style="background:#10b981;color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Submit</button>
                    </div>
                    <div id="flosc-access-code-error" style="color:#dc2626;font-size:13px;margin-top:10px;"></div>
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
        }
    }

    // v8.0.0: Redeem access code via REST — grants role, reloads on success
    async _redeemAccessCode(code, context) {
        const errEl = document.getElementById('flosc-access-code-error');
        const btn = document.getElementById('flosc-access-code-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Checking...'; }
        try {
            await this.refreshNonce();
            const res = await this.authFetch(this.config.apiUrl + '/redeem-access-code', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                body: JSON.stringify({ code: code, flow_id: this.config.flowId || '' }),
            });
            const data = await res.json();
            if (data.success) {
                if (context === 'chat') {
                    this.addMessage('assistant', 'Welcome, fam! You\'re in. Refreshing...');
                } else if (errEl) {
                    errEl.style.color = '#10b981';
                    errEl.textContent = 'Welcome, fam! You\'re in.';
                }
                sessionStorage.removeItem('flosc_credential_setup_dismissed');
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
     */
    async _renderSubscriptionCheckout(offerId, offer, container) {
        // Plan picker UI
        container.innerHTML = `
            <div class="flosc-plan-picker" style="margin-bottom:16px;">
                <div style="font-weight:600;font-size:15px;margin-bottom:10px;text-align:center;">Choose your plan:</div>
                <div style="display:flex;gap:10px;justify-content:center;">
                    <label class="flosc-plan-option" data-plan="yearly" style="flex:1;max-width:180px;padding:14px 10px;border:2px solid #10b981;border-radius:10px;text-align:center;cursor:pointer;background:#ecfdf5;position:relative;">
                        <div style="position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;font-size:11px;padding:2px 8px;border-radius:8px;white-space:nowrap;">Best Value</div>
                        <input type="radio" name="flosc_plan" value="yearly" checked style="display:none;">
                        <div style="font-size:20px;font-weight:700;">$100</div>
                        <div style="font-size:13px;color:#065f46;">/year</div>
                        <div style="font-size:11px;color:#10b981;margin-top:4px;">Save $20!</div>
                    </label>
                    <label class="flosc-plan-option" data-plan="monthly" style="flex:1;max-width:180px;padding:14px 10px;border:2px solid #d1d5db;border-radius:10px;text-align:center;cursor:pointer;background:#fff;">
                        <input type="radio" name="flosc_plan" value="monthly" style="display:none;">
                        <div style="font-size:20px;font-weight:700;">$10</div>
                        <div style="font-size:13px;color:#374151;">/month</div>
                    </label>
                </div>
            </div>
            <div id="flosc-sub-paypal-btn" style="min-height:55px;"></div>
            <div id="flosc-sub-status" style="text-align:center;font-size:13px;color:#666;margin-top:8px;"></div>
        `;

        // Plan selection toggle styling
        const planOptions = container.querySelectorAll('.flosc-plan-option');
        planOptions.forEach(opt => {
            opt.addEventListener('click', () => {
                planOptions.forEach(o => {
                    o.style.borderColor = '#d1d5db';
                    o.style.background = '#fff';
                });
                opt.style.borderColor = '#10b981';
                opt.style.background = '#ecfdf5';
                opt.querySelector('input').checked = true;
                // Re-render PayPal buttons for new plan
                this._mountSubscriptionButtons(offerId, container);
            });
        });

        // Get plan IDs (from config or fetch)
        let monthlyPlanId = this.config.paypalMonthlyPlanId || '';
        let yearlyPlanId = this.config.paypalYearlyPlanId || '';

        if (!monthlyPlanId || !yearlyPlanId) {
            const statusEl = container.querySelector('#flosc-sub-status');
            if (statusEl) statusEl.textContent = 'Setting up payment plans...';
            try {
                await this.refreshNonce();
                const res = await this.authFetch(this.config.apiUrl + '/paypal/get-plans', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                    body: JSON.stringify({ flow_id: this.config.flowId || '' }),
                });
                const data = await res.json();
                if (data.monthly_plan_id && data.yearly_plan_id) {
                    monthlyPlanId = data.monthly_plan_id;
                    yearlyPlanId = data.yearly_plan_id;
                    this.config.paypalMonthlyPlanId = monthlyPlanId;
                    this.config.paypalYearlyPlanId = yearlyPlanId;
                } else {
                    throw new Error(data.message || 'Could not get plan IDs');
                }
            } catch (err) {
                this.logError('[FLOSC-CHECKOUT] Failed to get PayPal plans:', err);
                const statusEl2 = container.querySelector('#flosc-sub-status');
                if (statusEl2) statusEl2.innerHTML = '<span style="color:#dc2626;">Could not set up payment plans. Please try again.</span>';
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
        const planId = selectedPlan === 'yearly' ? this.config.paypalYearlyPlanId : this.config.paypalMonthlyPlanId;

        if (!planId) {
            btnContainer.innerHTML = '<div style="text-align:center;color:#dc2626;padding:12px;">Plan not configured.</div>';
            return;
        }

        const renderBtns = () => {
            if (myGen !== this._paypalSubRenderGen) return; // stale — a newer plan switch superseded us

            btnContainer.innerHTML = '';

            const btns = paypal.Buttons({
                style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'subscribe', height: 45 },
                createSubscription: (data, actions) => {
                    this.log('[FLOSC-CHECKOUT] Creating subscription: plan=' + planId + ', type=' + selectedPlan);
                    return actions.subscription.create({ plan_id: planId });
                },
                onApprove: async (data) => {
                    this.log('[FLOSC-CHECKOUT] Subscription approved: subscriptionID=' + data.subscriptionID);
                    btnContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#666;">Activating your subscription...</div>';

                    try {
                        await this.refreshNonce();
                        const res = await this.authFetch(this.config.apiUrl + '/paypal/activate-subscription', {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': this.config.nonce },
                            body: JSON.stringify({
                                subscription_id: data.subscriptionID,
                                plan_type: selectedPlan,
                                flow_id: this.config.flowId || '',
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
                        if (modal) modal.style.display = 'none';

                        // If server created a new account from PayPal (visitor purchase),
                        // store the auth token so subsequent API calls are authenticated.
                        if (result.auth_token) {
                            this.config.authToken = result.auth_token;
                            localStorage.setItem('flosc_auth_token', result.auth_token);
                            // Clear visitor message cache — they're a member now
                            localStorage.removeItem('flosc_visitor_messages');
                        }

                        // Update local user state so IVR conditions reflect purchase
                        const displayName = result.user_display_name || result.user_email || 'Member';
                        if (this.user) {
                            this.user.justPurchased = true;
                            this.user.purchased = true;
                            this.user.memberLevel = result.member_level || 'lesaep_learners';
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
                                memberLevel: result.member_level || 'lesaep_learners',
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
                        const bar = document.getElementById('flosc_user_profile_bar');
                        if (profileName) profileName.textContent = this.user.name;
                        if (profileBadge) profileBadge.textContent = bar?.dataset.memberBadge || 'Member';
                        if (dropdownName) dropdownName.textContent = this.user.name;
                        if (dropdownEmail) dropdownEmail.textContent = this.user.email || '';

                        // Welcome message
                        const planLabel = selectedPlan === 'yearly' ? '$100/year' : '$10/month';
                        this.addMessage('assistant',
                            `🎉 **Welcome to LeSAEp!** Your ${planLabel} subscription is active.\n\n` +
                            `You now have full access to all pronunciation lessons, IPA training, audio recordings, and AI coaching.\n\n` +
                            `**What would you like to do first?**`
                        );

                        // Trigger post-purchase auto messages after short delay
                        setTimeout(() => this.checkAutoMessages(), 2000);
                    } catch (err) {
                        this.logError('[FLOSC-CHECKOUT] Subscription activation error:', err);
                        btnContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;">' +
                            this.escapeHtml(err.message || 'Failed to activate subscription. Please contact support.') + '</div>';
                    }
                },
                onError: (err) => {
                    this.logError('[FLOSC-CHECKOUT] PayPal subscription error:', err);
                    btnContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;">PayPal encountered an error. Please try again.</div>';
                },
                onCancel: () => {
                    this.log('[FLOSC-CHECKOUT] Subscription cancelled — re-rendering buttons');
                    if (myGen !== this._paypalSubRenderGen) return;
                    btnContainer.innerHTML = '';
                    requestAnimationFrame(() => renderBtns());
                },
            });

            if (!btns.isEligible()) {
                btnContainer.innerHTML = '<div style="text-align:center;padding:14px;color:#666;font-size:13px;">PayPal is not available right now.</div>';
                return;
            }

            this._paypalSubButtons = btns;
            btns.render(btnContainer).catch(err => {
                if (myGen !== this._paypalSubRenderGen) return;
                this.logError('[FLOSC-CHECKOUT] PayPal subscription render failed:', err);
                btnContainer.innerHTML = '<div style="text-align:center;padding:14px;color:#dc2626;">PayPal could not load. Please try again.</div>';
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
     * One-time PayPal payment (existing Orders API flow, kept for non-subscription offers)
     */
    _renderOneTimePayPal(offerId, paypalContainer) {
            const renderPayPalButtons = () => {
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
                            body: JSON.stringify({ offer_id: offerId, flow_id: this.config.flowId || '' }),
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
                                    paypalContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;font-size:14px;">' +
                                        (retryErr.message || 'Could not create order. Please try again.') + '</div>';
                                }
                                throw retryErr;
                            }
                        }
                        this.logError('[FLOSC-CHECKOUT] PayPal create order error:', err);
                        const errorEl = document.getElementById('card-errors');
                        if (errorEl) errorEl.textContent = err.message;
                        if (paypalContainer) {
                            paypalContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;font-size:14px;">' +
                                (err.message || 'Could not create order. Please try again.') + '</div>';
                        }
                        throw err;
                    }
                },
                onApprove: async (data, actions) => {
                    try {
                        // Show processing state
                        this.log('[FLOSC-CHECKOUT] PayPal onApprove: orderID=' + data.orderID);
                        paypalContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#666;">Processing payment...</div>';

                        // v5.0.7: doCapture now checks HTTP status before parsing JSON.
                        // Previous versions blindly called r.json() which swallowed server errors.
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
                                    flow_id: this.config.flowId || '',
                                }),
                            });
                            const body = await r.json().catch(() => ({ success: false, message: 'Server returned non-JSON (HTTP ' + r.status + ')' }));
                            // v5.0.7: Attach HTTP status so callers can distinguish server errors
                            body._httpStatus = r.status;
                            body._httpOk = r.ok;
                            return body;
                        };

                        // Pre-flight nonce refresh for capture
                        await this.refreshNonce();

                        let result = await doCapture();
                        this.log('[FLOSC-CHECKOUT] PayPal capture result:', JSON.stringify({ success: result.success, message: result.message, issue: result.issue, http: result._httpStatus }));

                        // Nonce retry for capture
                        if (!result._httpOk && ((result.message || '').match(/cookie|not allowed/i) || result.code === 'rest_cookie_invalid_nonce')) {
                            this.log('[FLOSC-CHECKOUT] PayPal capture nonce issue, retrying...');
                            await this.refreshNonce();
                            result = await doCapture();
                        }

                        // Handle INSTRUMENT_DECLINED (per PayPal official pattern)
                        const errorDetail = result?.details?.[0];
                        if (errorDetail?.issue === 'INSTRUMENT_DECLINED' || result?.issue === 'INSTRUMENT_DECLINED') {
                            this.log('[FLOSC-CHECKOUT] INSTRUMENT_DECLINED — restarting PayPal');
                            paypalContainer.innerHTML = '';
                            return actions.restart();
                        }

                        if (result.success) {
                            const paymentModal = document.getElementById('flosc_modal_payment');
                            if (paymentModal) paymentModal.style.display = 'none';
                            this.addMessage('assistant', '\ud83c\udf89 **Payment successful!** Welcome to full membership! Refreshing your access...');
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            throw new Error(result.message || 'Payment capture failed (HTTP ' + (result._httpStatus || '?') + ')');
                        }
                    } catch (err) {
                        this.logError('[FLOSC-CHECKOUT] PayPal capture error:', err);
                        paypalContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;">' + (err.message || 'Payment failed. Please try again.') + '</div>';
                    }
                },
                onError: (err) => {
                    this.logError('[FLOSC-CHECKOUT] PayPal error:', err);
                    // v1.7.6: Show error in both places for visibility
                    const errorEl = document.getElementById('card-errors');
                    if (errorEl) errorEl.textContent = 'PayPal error. Please try again.';
                    if (paypalContainer) {
                        paypalContainer.innerHTML = '<div style="text-align:center;padding:16px;color:#dc2626;font-size:14px;">PayPal encountered an error. Please try again.</div>';
                    }
                },
                onCancel: () => {
                    this.log('[FLOSC-CHECKOUT] PayPal cancelled by user — re-rendering buttons');
                    // v5.0.7: Actually re-render PayPal buttons after cancel so user can retry
                    paypalContainer.innerHTML = '';
                    requestAnimationFrame(() => renderPayPalButtons());
                },
            });

            // v3.0.9: isEligible() guards against environments where PayPal can't render
            if (!paypalButtonsInstance.isEligible()) {
                this.logWarn('[FLOSC-CHECKOUT] PayPal buttons not eligible in this environment');
                paypalContainer.innerHTML = '<div style="text-align:center;padding:14px;color:#666;font-size:13px;">PayPal is not available right now. Please try again or contact support.</div>';
                return;
            }

            paypalButtonsInstance.render(paypalContainer).catch(err => {
                this.logError('[FLOSC-CHECKOUT] PayPal render failed:', err);
                paypalContainer.innerHTML =
                    '<div style="text-align:center;padding:14px;font-size:13px;">' +
                    '<div style="color:#dc2626;margin-bottom:10px;">PayPal could not load. Please try again.</div>' +
                    '<button onclick="this.closest(\'#flosc_modal_payment\') && window.floscAppInstance && window.floscAppInstance.showPaymentModal(\'' + offerId + '\')" ' +
                    'style="padding:8px 18px;background:#0070ba;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;">↺ Retry</button>' +
                    '</div>';
            });
            }; // end renderPayPalButtons

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
            // Create PaymentIntent on the server
            const intentRes = await this.authFetch(this.config.apiUrl + '/create-payment-intent', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({ offer_id: offerId }),
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
                    }),
                });

                // Close modal
                const modal = document.getElementById('flosc_modal_payment');
                if (modal) modal.style.display = 'none';

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
            this.shareModal.style.display = 'flex';
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
