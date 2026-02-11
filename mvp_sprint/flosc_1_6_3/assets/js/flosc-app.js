/**
 * FLOSC App JavaScript
 * Main application controller
 * v1.4.2: Flow-aware IVR messages - loadIVRMessages() sends flowId/ivrFile
 */

// v9.4.7: Clear FLOSC-specific localStorage on version change
(function() {
    const FLOSC_JS_VERSION = '1.6.3';
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
            console.log('FLOSC v1.4.9: Storage cleared - fresh session');
        }
    } catch(e) {
        console.warn('FLOSC: Storage check failed', e);
    }
})();

class floscApp {
    constructor() {
        this.config = window.FLOSC_CONFIG || {};
        this.user = window.FLOSC_USER || {};
        this.state = document.body.dataset.userState || 'visitor';
        
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
            'first_message_after_purchase', 'returning_user', 'command',
            // User info
            'user_id', 'name', 'email',
            // Quiz info
            'score', 'quiz_id', 'initial_score', 'initial_quiz_id', 'quiz_taken',
            // Purchase/access
            'purchased', 'lesson_viewed', 'free_lessons_count', 'onboarded', 'access_level',
            'lessons_completed', 'has_incomplete_lesson', 'completed_quizzes',
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

        // Offer timer
        this.offerTimer = null;
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
            console.warn('[FLOSC] Could not persist offer states', e);
        }
    }
    
        // v9.2.7: Minimal fallback - only if DB completely fails
        getFallbackMessages() {
            console.warn('[FLOSC] Using emergency fallback - DB messages not loaded!');
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
            console.log('[FLOSC] Fetching IVR messages from:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                console.error('[FLOSC] API returned error:', response.status);
                this.ivr.messages = this.getFallbackMessages();
                return;
            }
            
            const data = await response.json();
            console.log('[FLOSC] API response:', data);
            
            // v1.3.8: Log flow context for debugging
            if (data.flow_context) {
                console.log('[FLOSC] Flow context:', data.flow_context);
            }
            
            if (data.success && data.messages && data.messages.length > 0) {
                // Convert array to object keyed by message name
                const messagesObj = {};
                data.messages.forEach(msg => {
                    const key = msg.name || msg.id || `msg_${Date.now()}`;
                    messagesObj[key] = msg;
                });
                
                this.ivr.messages = messagesObj;
                console.log('[FLOSC] ✓ Loaded', data.messages.length, 'messages from DB for phase:', this.ivr.phase);
            } else {
                console.warn('[FLOSC] API returned empty messages array - check condition evaluator');
                console.log('[FLOSC] User context:', data.user_context);
                this.ivr.messages = this.getFallbackMessages();
            }
            
        } catch (error) {
            console.error('[FLOSC] Failed to fetch IVR messages:', error);
            this.ivr.messages = this.getFallbackMessages();
        }
    }
    
    async init() {
        console.log('[FLOSC] Initializing app...');
        
        try {
            console.log('[FLOSC] Loading IVR messages from API...');
            await this.loadIVRMessages();
            console.log('[FLOSC] IVR messages loaded:', Object.keys(this.ivr.messages).length, 'messages');
            
            console.log('[FLOSC] Binding elements...');
            this.bindElements();
            console.log('[FLOSC] Elements bound:', {
                chatInput: !!this.chatInput,
                sendBtn: !!this.sendBtn,
                chatMessages: !!this.chatMessages,
                voiceBtn: !!this.voiceBtn
            });
            
            console.log('[FLOSC] Binding events...');
            this.bindEvents();
            console.log('[FLOSC] Events bound successfully');
            
            console.log('[FLOSC] Setting up UI...');
            this.setupUI();
            console.log('[FLOSC] UI setup complete');
            
            console.log('[FLOSC] Injecting IVR styles...');
            this.injectIVRStyles();
            console.log('[FLOSC] IVR styles injected');

            if (this.config.stripeKey) {
                console.log('[FLOSC] Initializing Stripe...');
                this.initStripe();
            }

            if (this.state !== 'visitor') {
                if (this.user?.funnelCompleted) {
                    console.log('[FLOSC] Loading sessions...');
                    await this.loadSessions();
                }
                console.log('[FLOSC] Checking pending quiz results...');
                await this.checkPendingQuizResults();
            }

            this.freeLessonDelivered = this.user?.freeLessonDelivered || false;

            // v9.0.6: Defer visitor restore until context is built; only restore on returning sessions

            this.trackEvent('page_view');

            // v07.08: Build context and start IVR
            console.log('[FLOSC] Building IVR context...');
            this.buildIVRContext();
            console.log('[FLOSC] IVR context built:', this.ivr.context);

            if (this.state === 'visitor') {
                if (this.ivr.context.first_show_session) {
                    console.log('[FLOSC] First session - clearing old visitor messages');
                    try { localStorage.removeItem('flosc_visitor_messages'); } catch(e) { console.warn('FLOSC: Could not clear visitor messages', e); }
                } else {
                    console.log('[FLOSC] Restoring visitor messages from previous session...');
                    this.restoreVisitorMessages();
                }
            }
            
            console.log('[FLOSC] Starting IVR...');
            // v1.6.2: initOfferMessages() REMOVED — offers ARE IVR entries, no bridge needed
            this.startIVR();
            console.log('[FLOSC] Initialization complete!');
            
            // Verify window.FLOSC is set for debugging
            window.FLOSC = this;
            console.log('[FLOSC] App instance available at window.FLOSC');
            
        } catch (error) {
            console.error('[FLOSC] INITIALIZATION FAILED:', error);
            console.error('[FLOSC] Error stack:', error.stack);
            throw error;
        }
    }

    // ==========================================
    // v07.08: IVR System
    // ==========================================

    determinePhase() {
        if (this.user?.purchased) return 'content';
        if (this.user?.funnelCompleted) return 'sale';
        if (this.user?.freeLessonDelivered) return 'offer';
        if (this.state !== 'visitor') return 'login';
        return 'freeline';
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
            first_message_after_quiz: false,
            first_message_after_login: false,
            first_message_after_purchase: false,
            returning_user: hasSession,
            command: '',
            
            // User info
            user_id: this.user?.id || 0,
            name: this.user?.name?.split(' ')[0] || 'there',
            email: this.user?.email || '',
            
            // Quiz info
            score: parseInt(this.user?.lastQuizScore) || 0,
            quiz_id: this.user?.lastQuizId || 'unknown',
            initial_score: parseInt(this.user?.initialScore) || 0,
            initial_quiz_id: this.user?.initialQuizId || 'unknown',
            quiz_taken: !!(this.user?.lastQuizScore || this.user?.quizCompletedAt),
            
            // Purchase/access
            purchased: isMember,
            lesson_viewed: !!this.user?.freeLessonDelivered,
            free_lessons_count: parseInt(this.user?.freeLessonsCount) || 0,
            onboarded: !!this.user?.funnelCompleted,
            lessons_completed: parseInt(this.user?.lessonsCompleted) || 0,
            has_incomplete_lesson: !!this.user?.hasIncompleteLesson,
            completed_quizzes: Array.isArray(this.user?.completedQuizzes) ? this.user.completedQuizzes : [],
            
            // Session tracking
            message_count: this.ivr.messageCount,
            inactive_seconds: 0,
            session_seconds: 0,
            session_minutes: 0,
            
            // Product info
            product_name: this.config.product?.name || 'the course',
            price: this.config.product?.price || '$100',
            discount_price: '$25',
            customer_count: '1,247',
            timer_remaining: '60:00'
        };
        
        // Mark session as started
        if (!hasSession) {
            try {
                localStorage.setItem(sessionKey, Date.now().toString());
            } catch(e) {
                console.warn('FLOSC: Could not set session key', e);
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
        console.log('FLOSC v9.0.6: Starting IVR for phase:', this.ivr.phase);
        console.log('FLOSC: Total messages available:', Object.keys(this.ivr.messages).length);
        
        // v1.3.5: Show admin verification message in chat (not banner)
        if (this.user?.isAdmin && this.user?.adminVerification) {
            const av = this.user.adminVerification;
            const adminMsg = `🔧 **Admin View**\n\n` +
                `**IVR File:** \`${av.ivrFile}\`\n` +
                `**Slug:** /${av.slug}/\n` +
                (av.name ? `**Name:** ${av.name}\n` : '') +
                (av.tagline ? `**Tagline:** "${av.tagline}"\n` : '') +
                (av.domain ? `**Custom Domain:** ${av.domain}\n` : '') +
                `\n_This message is only visible to admins._`;
            this.addMessage('assistant', adminMsg);
        }
        
        // v8.0.9: RULE #1 - If chat is empty, ALWAYS show welcome. Period.
        // This is idempotent and defensive - check DOM, not localStorage
        const existingMessages = this.chatMessages?.querySelectorAll('.message, .flosc-message') || [];
        
        if (existingMessages.length === 0) {
            console.log('FLOSC: Chat is empty - showing welcome message');
            
            // v1.4.7: Check URL params for contextual greeting (e.g., ?from=lesson&title=...)
            const urlParams = new URLSearchParams(window.location.search);
            const fromParam = urlParams.get('from');
            const titleParam = urlParams.get('title');
            
            if (fromParam === 'lesson' && titleParam) {
                console.log('[FLOSC] URL param greeting for lesson:', titleParam);
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
            
            // Try to find a welcome message from IVR config
            let welcomeShown = false;
            const messages = Object.values(this.ivr.messages);
            const welcomeMessages = messages.filter(m => 
                m.type === 'auto' && 
                m.name && 
                m.name.includes('welcome')
            );
            
            // Show first welcome message that matches current phase (or any welcome)
            for (const msg of welcomeMessages) {
                if (!msg.phase || msg.phase === this.ivr.phase) {
                    console.log('FLOSC: Using IVR welcome:', msg.name);
                    this.showIVRMessage(msg);
                    welcomeShown = true;
                    break;
                }
            }

            // If no IVR welcome found, show hardcoded fallback (ALWAYS works)
            if (!welcomeShown) {
                console.log('FLOSC: Using fallback welcome');
                const fallbackWelcome = this.state === 'visitor'
                    ? "Hi! I'm your pronunciation coach. Ready to improve your English speaking skills?"
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
        console.log('FLOSC: Chat has', existingMessages.length, 'messages - checking for auto messages');
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
        console.log('FLOSC: Evaluating condition:', conditionString);

        if (!conditionString || conditionString === 'always') {
            console.log('FLOSC: → TRUE (always)');
            return true;
        }
        if (conditionString === 'never') {
            console.log('FLOSC: → FALSE (never)');
            return false;
        }

        this.updateIVRContext();
        const ctx = this.ivr.context;
        console.log('FLOSC: Context:', ctx);

        try {
            const result = this.parseCondition(conditionString, ctx);
            console.log('FLOSC: → Result:', result);
            return result;
        } catch (e) {
            console.error('FLOSC: Condition parse error:', conditionString, e);
            // v8.0.9: On error, default to FALSE (safe) but log it clearly
            console.error('FLOSC: Returning FALSE due to parse error');
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
                console.error('[FLOSC Security] Blocked invalid condition variable:', varName);
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
            console.warn('[FLOSC Security] Blocked invalid condition variable:', expr);
        }
        
        return false;
    }

    // v1.6.2: Debounced wrapper — multiple rapid callers get coalesced into one check
    checkAutoMessages() {
        if (this._autoMsgTimer) clearTimeout(this._autoMsgTimer);
        this._autoMsgTimer = setTimeout(() => this._checkAutoMessagesNow(), 50);
    }

    _checkAutoMessagesNow() {
        console.log('FLOSC: Checking auto messages for phase:', this.ivr.phase);

        const messages = Object.values(this.ivr.messages);
        console.log('FLOSC: Total messages loaded:', messages.length);

        const autoMessages = messages.filter(m => m.type === 'auto' || m.type === 'offer');
        console.log('FLOSC: Auto/offer messages found:', autoMessages.length);

        for (const msg of autoMessages) {
            // v8.0.9: Skip if already shown (DOM check - idempotent)
            const alreadyShown = document.querySelector(`[data-message-name="${msg.name}"]`);
            if (alreadyShown) {
                console.log('FLOSC: Message already in DOM, skipping:', msg.name);
                continue;
            }
            
            // Skip if marked as shown this session
            if (this.ivr.shownThisSession[msg.name]) continue;

            // Check phase match (allow cross-phase for logged_in conditions)
            if (msg.phase && msg.phase !== this.ivr.phase) {
                if (!(msg.phase === 'login' && this.ivr.context.logged_in && !this.ivr.context.purchased)) {
                    continue;
                }
            }

            console.log('FLOSC: Testing message:', msg.name, 'condition:', msg.conditions);

            // v8.0.9: Simplify conditions - 'always' or 'never' bypass evaluation
            if (msg.conditions === 'always' || !msg.conditions) {
                console.log('FLOSC: Condition is "always" - showing message');
                this.showIVRMessage(msg);
                this.ivr.shownThisSession[msg.name] = true;
                break;
            }
            
            if (msg.conditions === 'never') {
                console.log('FLOSC: Condition is "never" - skipping');
                continue;
            }

            if (this.evaluateCondition(msg.conditions)) {
                console.log('FLOSC: Condition matched! Showing:', msg.name);
                this.showIVRMessage(msg);
                this.ivr.shownThisSession[msg.name] = true;
                break; // Only show one auto message at a time
            }
        }
    }

    floscShowUserAutoPrompts() {
        const messages = Object.values(this.ivr.messages);
        const autoPrompts = messages.filter(m => m.type === 'suggested_user_autoprompt');

        console.log('FLOSC: Total suggested_user_autoprompt messages:', autoPrompts.length);

        // v1.2.5: Filter by MessagePanel field
        // IntroPanel (visitors) = panel:'intro', PromptPanel (guests/members) = panel:'prompt'
        const targetPanel = this.state === 'visitor' ? 'intro' : 'prompt';
        const applicable = [];
        
        for (const msg of autoPrompts) {
            // v1.2.5: Check panel field first - if set, use it for filtering
            if (msg.panel) {
                if (msg.panel.toLowerCase() === targetPanel) {
                    // Panel matches, now check conditions
                    if (this.state === 'visitor') {
                        // Visitors: phase must be freeline or empty
                        if (!msg.phase || msg.phase === 'freeline') {
                            applicable.push(msg);
                        }
                    } else {
                        // Guests/members: check conditions
                        if (!msg.conditions || msg.conditions === 'always' || this.evaluateCondition(msg.conditions)) {
                            applicable.push(msg);
                        }
                    }
                }
                continue;
            }
            
            // v1.2.5: Fallback for messages without panel field (legacy support)
            if (this.state === 'visitor') {
                if (!msg.phase || msg.phase === 'freeline') {
                    applicable.push(msg);
                }
            } else {
                if (!msg.conditions || msg.conditions === 'always' || this.evaluateCondition(msg.conditions)) {
                    applicable.push(msg);
                }
            }
        }
        
        // v1.2.5: Fallback - if no panel-filtered matches, try without panel filter
        if (applicable.length === 0) {
            console.log('FLOSC: No panel-filtered matches, trying fallback');
            for (const msg of autoPrompts) {
                if (this.state === 'visitor') {
                    if (!msg.phase || msg.phase === 'freeline') {
                        applicable.push(msg);
                    }
                } else {
                    if (!msg.conditions || msg.conditions === 'always' || this.evaluateCondition(msg.conditions)) {
                        applicable.push(msg);
                    }
                }
            }
        }

        if (applicable.length === 0) {
            console.log('FLOSC: No user autoprompts from IVR for state:', this.state);
            return;
        }

        console.log('FLOSC: User autoprompts for', this.state, '(panel:', targetPanel, '):', applicable.map(r => r.name));
        this.floscRenderUserAutoPrompts(applicable);
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
        
        // v9.5.7: Check if any replies use card style - panel-level style (no mixing)
        const hasCards = replies.some(r => r.style === 'card');

        const container = document.createElement('div');
        container.id = 'flosc_input_user_autoprompts_panel';
        container.className = 'prompt-panel prompt-panel-inline';
        
        // v1.0.1: Cards use grid, Pills use flex - with carousel wrapper for overflow
        const containerClass = hasCards ? 'flosc-cards-container' : 'flosc-pills-container';
        
        const buttonsHtml = replies.map(r => `
            <button class="flosc-style-${r.style || 'pill'}" data-message="${this.escapeHtml(r.name)}">
                ${r.icon ? `<span class="flosc-autoprompt-icon">${r.icon}</span>` : ''}
                <span class="flosc-autoprompt-label">${this.escapeHtml(r.user_input)}</span>
            </button>
        `).join('');
        
        // v1.1.0: Carousel structure - arrows shown when items overflow, loop infinitely
        container.innerHTML = `
            <div class="prompt-panel-header">
                <div class="prompt-panel-eyebrow">Try these AutoPrompts!</div>
                <div class="prompt-panel-title">${panelName}</div>
                <button class="prompt-panel-close" aria-label="Hide ${panelName}">×</button>
            </div>
            <div class="prompt-panel-body">
                <div class="flosc-carousel-container">
                    <button class="flosc-carousel-arrow flosc-carousel-prev" aria-label="Previous">‹</button>
                    <div class="flosc-carousel-track ${containerClass}">
                        ${buttonsHtml}
                    </div>
                    <button class="flosc-carousel-arrow flosc-carousel-next" aria-label="Next">›</button>
                </div>
            </div>
        `;

        // Button click handlers
        // v9.1.1: Track listeners for cleanup
        // v1.3.9: Added flosc-style-feature to selector (was missing, causing feature cards to not respond to clicks)
        container.querySelectorAll('button.flosc-style-pill, button.flosc-style-chip, button.flosc-style-button, button.flosc-style-card, button.flosc-style-feature').forEach(btn => {
            const handler = () => {
                const msgName = btn.dataset.message;
                this.floscHandleUserAutoPrompt(msgName);
            };
            
            btn.addEventListener('click', handler);
            this.activeEventListeners.set(btn, handler);
        });

        // Close button
        const closeBtn = container.querySelector('.prompt-panel-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                container.remove();
                this.addMessage('user', `Hide ${panelName}`);
                this.addMessage('assistant', `${panelName} hidden. If you ever wish to see it again, just type "Show ${panelName}" in the chat, and it will reappear.`);
            });
        }

        const composer = document.getElementById('flosc_input_composer');
        if (composer && composer.parentElement) {
            composer.parentElement.insertBefore(container, composer.nextElementSibling);
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
            console.warn('[FLOSC Carousel] Missing elements:', { 
                track: !!track, 
                prevBtn: !!prevBtn, 
                nextBtn: !!nextBtn,
                carouselEl: !!carouselEl 
            });
            return;
        }

        const items = Array.from(track.children);
        const itemCount = items.length;
        
        console.log('[FLOSC Carousel] Initializing infinite carousel. Items:', itemCount);

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
        const ANIMATION_DURATION = 300; // ms

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
            
            // Animate scroll
            track.style.transition = `transform ${ANIMATION_DURATION}ms ease`;
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
            
            // Animate back to 0
            track.style.transition = `transform ${ANIMATION_DURATION}ms ease`;
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
            console.error('FLOSC: IVR message not found:', messageName, '- Check IVR.md configuration');
            return;
        }
        
        // Insert text into input field if available, otherwise add directly
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
                if (msg.action) this.performIVRAction(msg.action);
                this.floscShowUserAutoPrompts();
            }, 300);
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
        const messageDiv = this.addMessage('assistant', content);
        if (messageDiv && msg.name) {
            messageDiv.setAttribute('data-message-name', msg.name);
        }

        if (msg.offer_id) {
            this.ivr.shownThisSession['offer_' + msg.offer_id] = true;
        }

        // v07.09: Track message shown persistently
        this.trackMessageShown(msg.name, msg.offer_id);
    }

    async trackMessageShown(messageName, offerId = null) {
        try {
            await fetch(this.config.apiUrl + '/ivr/track', {
                method: 'POST',
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
            console.warn('FLOSC: Could not track message', e);
        }
    }

    // v1.6.2: Bridge from handleAction('show_offer_*') to showOfferMessage()
    showOffer(offerId) {
        const offer = this.getOfferData(offerId);
        const msg = {
            offer_id: offerId,
            content: offer?.description || offer?.content || offer?.name || 'Check out this special offer!',
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
        
        console.log('[FLOSC-OFFER] Showing offer:', msg.offer_id, 'format:', displayFormat);
        
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
                const resp = await fetch(`${this.config.apiUrl}/offer-content?source=html&file=${encodeURIComponent(htmlFile)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                }
            } else if (wooProduct) {
                // Load WooCommerce product data
                const resp = await fetch(`${this.config.apiUrl}/offer-content?source=woo&product=${encodeURIComponent(wooProduct)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                    if (data.price) msg.price = data.price;
                }
            } else if (postId) {
                // Load WordPress post content
                const resp = await fetch(`${this.config.apiUrl}/offer-content?source=post&id=${encodeURIComponent(postId)}`);
                if (resp.ok) {
                    const data = await resp.json();
                    if (data.html) content = data.html;
                }
            }
            
            msg.content = content;
        } catch (e) {
            console.warn('[FLOSC-OFFER] Could not load content source, using fallback', e);
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
            console.error('[FLOSC-OFFER] Render error for', msg.offer_id, 'format:', displayFormat, e);
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
        const description = offer?.description || msg.description || '';
        const features = offer?.grants?.features || msg.features || [];
        const price = offer?.display_price || msg.price || '';
        const originalPrice = offer?.original_price || msg.original_price || '';
        const savings = offer?.meta?.savings || msg.savings || '';
        const ctaText = msg.cta || offer?.cta || '🔓 Get Full Access Now';
        const guarantee = msg.guarantee || 'Risk-free with our 30-day money-back guarantee';
        
        const featuresHtml = features.length > 0 ? `
            <div class="flosc-offer-featured-features">
                ${features.slice(0, 5).map(f => `
                    <div class="flosc-offer-featured-feature">
                        <span>✓</span> ${typeof f === 'string' ? f : f.name || f}
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
                <div class="flosc-offer-featured-guarantee">${guarantee}</div>
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
        const icon = offer?.meta?.icon || this.config.product?.emoji || '⭐';
        const name = offer?.name || msg.name || 'Full Access';
        const description = offer?.description || msg.description || 'Lifetime access to all content';
        const price = offer?.display_price || msg.price || '$99';
        
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
            console.warn('[FLOSC-OFFER] Stripe not initialized');
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
            const intentResponse = await fetch(this.config.apiUrl + '/create-payment-intent', {
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
                    await fetch(this.config.apiUrl + '/complete-purchase', {
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
                    console.warn('[FLOSC] complete-purchase call failed, webhook should handle:', e);
                }
                
                // Payment successful - update UI
                this.handlePaymentSuccess(offerId);
            }
            
        } catch (error) {
            console.error('[FLOSC-OFFER] Payment error:', error);
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
            await fetch(this.config.apiUrl + '/ivr/track', {
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
            console.warn('[FLOSC-OFFER] Could not track offer shown', e);
        }
    }
    
    // Track offer dismissed via API
    async trackOfferDismissed(offerId) {
        try {
            await fetch(this.config.apiUrl + '/ivr/track', {
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
            console.warn('[FLOSC-OFFER] Could not track offer dismissed', e);
        }
    }
    
    // v1.6.2: renderPanelOffers() REMOVED
    // Offer pills are just regular autoprompts (icon + label).
    // Admin creates them as suggested_user_autoprompt entries with Action: show_offer_*
    // No special rendering needed — a pill is a pill.

    startOfferTimer(offerId, totalSeconds) {
        let remaining = totalSeconds;
        const timerEl = document.getElementById(`flosc-offer-timer-${offerId}`);

        if (this.offerTimer) clearInterval(this.offerTimer);

        this.offerTimer = setInterval(() => {
            remaining--;
            if (remaining <= 0) {
                clearInterval(this.offerTimer);
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
            .replace(/{price}/g, ctx.price || '$100')
            .replace(/{discount_price}/g, ctx.discount_price || '$25')
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
        const productName = this.config.product?.name || 'our course';
        const firstName = this.user?.name?.split(' ')[0] || 'there';
        const memberLevels = (this.user?.memberLevels || []).join(', ') || 'full access';
        
        console.log('[FLOSC-STATUS] Generating status response...');
        console.log('[FLOSC-STATUS] this.user:', this.user);
        console.log('[FLOSC-STATUS] this.state:', this.state);
        console.log('[FLOSC-STATUS] isAdmin:', this.user?.isAdmin);
        
        // Check admin first (user object has isAdmin flag set by PHP)
        if (this.user?.isAdmin) {
            console.log('[FLOSC-STATUS] → Returning ADMIN status');
            return `Hey, thanks for asking about your user status! You are the **FLOSC Admin**. You have access to all member levels. Hope you're enjoying the FLOSC experience!`;
        }
        
        // Check member (purchased)
        if (this.state === 'member' || this.user?.purchased) {
            console.log('[FLOSC-STATUS] → Returning MEMBER status');
            return `Hey, thanks for asking about your user status! You are a **Member**. You like to be called **${firstName}** and have access to **${memberLevels}** within "${productName}". Ask me anything about "${productName}" right here in this chat!`;
        }
        
        // Check guest (logged in but not purchased)
        if (this.state === 'guest' || (this.user?.id && !this.user?.purchased)) {
            console.log('[FLOSC-STATUS] → Returning GUEST status');
            return `Hey, thanks for asking about your user status! You are a **Guest**. You like to be called **${firstName}**. Check out your free lesson and upgrade for full access to "${productName}"!`;
        }
        
        // Default: visitor (not logged in)
        console.log('[FLOSC-STATUS] → Returning VISITOR status');
        return `Hey, thanks for asking about your user status! You are a **Visitor**. Take our free quiz and create an account to unlock personalized learning!`;
    }

    performIVRAction(action) {
        console.log('FLOSC: Performing action:', action);

        switch (action) {
            case 'open_quiz':
                this.openQuiz();
                break;
            case 'open_registration':
                this.openRegistration();
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
            case 'open_quiz_library':
                this.openQuizLibrary();
                break;
            case 'open_support':
                this.openSupport();
                break;
            // v1.4.0: Sandbox purchase actions
            case 'open_sandbox_purchase':
            case 'sandbox_purchase':
                this.openSandboxPurchase();
                break;
            default:
                if (action.startsWith('checkout_')) {
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
    getOfferIdForProduct(productId) {
        const productOfferMap = {
            'flosc_plugin': 'flosc_plugin_full',
            'simplified_solfeggio': 'simplified_solfeggio_full',
            'lesaep': 'lesaep_full',
        };
        
        return productOfferMap[productId] || 'sandbox';
    }

    openQuiz() {
        // v9.3.4: Start in-chat quiz
        this.startInChatQuiz();
    }

    // v9.3.4: In-Chat Quiz System - Now supports TEXT SEQUENCE and AUDIO types!
    async startInChatQuiz(quizId = 'default') {
        console.log('[FLOSC Quiz] Starting in-chat quiz:', quizId);
        
        // Show loading message
        this.addMessage('assistant', '📋 Loading your quiz...');
        
        try {
            // Fetch quiz from API - this now reads the PRIMARY quiz from admin settings!
            const response = await fetch(`${this.config.apiUrl}/quiz?id=${quizId}`);
            const data = await response.json();
            
            console.log('[FLOSC Quiz] API response:', data);
            
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
                    
                    console.log('[FLOSC Quiz] Loaded', this.quiz.questions.length, 'questions');
                    this.addMessage('assistant', `Great! Let's see where you stand. This quick ${this.quiz.questions.length}-question quiz takes about 30 seconds.`);
                    
                    setTimeout(() => {
                        this.showQuizQuestion();
                    }, 800);
                    return;
                }
            }
            
            // Fallback to hardcoded sample quiz
            console.log('[FLOSC Quiz] API returned no valid quiz, using sample');
            this.startSampleQuiz();
            
        } catch (error) {
            console.error('[FLOSC Quiz] Failed to load quiz:', error);
            this.startSampleQuiz();
        }
    }
    
    // v9.3.4: TEXT SEQUENCE QUIZ - User types "1, 2, 3, 4, 5, 6, 7, 8, 9, 10"
    startTextSequenceQuiz(data) {
        console.log('[FLOSC Quiz] Starting TEXT SEQUENCE quiz');
        
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
        
        // Show user's answer
        this.addMessage('user', userAnswer);
        
        // Store quiz data
        this.quiz.completedAt = Date.now();
        this.quiz.score = score;
        this.quiz.correctItems = correctItems;
        this.quiz.missedItems = missedItems;
        
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
                    <button class="flosc-gate-btn" id="flosc-gate-signup">Sign Up to See Results</button>
                </div>
            `;
            this.addMessage('assistant', gateHtml, true);
            
            // Bind signup button
            setTimeout(() => {
                const btn = document.getElementById('flosc-gate-signup');
                if (btn) {
                    btn.addEventListener('click', () => this.openRegistration());
                }
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
        console.log('[FLOSC Quiz] Starting AUDIO quiz');
        
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
            console.error('FLOSC: Audio recording failed', e);
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
            const response = await fetch(`${this.config.apiUrl}/process-audio`, {
                method: 'POST',
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
            console.error('FLOSC: Audio processing failed', e);
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
        this.storeQuizScore(score, data.correct || [], data.incorrect || []);
        this.onQuizComplete(score);
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

        // Show user's answer as their message
        this.addMessage('user', `${answer}) ${selectedOption?.text || answer}`);

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

        // Store quiz results
        this.storeQuizResults(scorePercent);

        // Update IVR context
        this.ivr.context.quiz_taken = true;
        this.ivr.context.score = scorePercent;
        this.ivr.context.first_message_after_quiz = true;

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

        console.log('[FLOSC Quiz] Completed. Score:', scorePercent, '% Answers:', this.quiz.answers);
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
            await fetch(`${this.config.apiUrl}/quiz-result`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify(quizResult)
            });

        } catch (error) {
            console.error('[FLOSC Quiz] Failed to store results:', error);
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
    
    showAuthModal() {
        const ssoProviders = this.config.ssoProviders || [];
        
        // Build SSO buttons HTML
        let ssoButtonsHtml = '';
        if (ssoProviders.length > 0) {
            ssoButtonsHtml = `
                <div class="flosc-auth-divider">
                    <span>or continue with</span>
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
            <div class="flosc-auth-modal-overlay" id="flosc-auth-modal">
                <div class="flosc-auth-modal">
                    <button class="flosc-auth-close" onclick="window.floscAppInstance.hideAuthModal()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                    <div class="flosc-auth-header">
                        <h2>🔓 Sign Up or Log In</h2>
                        <p>Create an account to save your progress</p>
                    </div>
                    <form class="flosc-auth-form" id="flosc-auth-form">
                        <div class="flosc-auth-field">
                            <label for="flosc-auth-email">Email Address</label>
                            <input type="email" id="flosc-auth-email" placeholder="you@example.com" required>
                        </div>
                        <button type="submit" class="flosc-auth-submit">
                            Continue with Email
                        </button>
                    </form>
                    ${ssoButtonsHtml}
                    <p class="flosc-auth-terms">
                        By continuing, you agree to our Terms of Service and Privacy Policy.
                    </p>
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
        
        // Focus email input
        setTimeout(() => {
            document.getElementById('flosc-auth-email')?.focus();
        }, 100);
    }
    
    hideAuthModal() {
        const modal = document.getElementById('flosc-auth-modal');
        if (modal) modal.remove();
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
                    font-size: 24px;
                }
                .flosc-auth-header p {
                    margin: 0;
                    color: var(--flosc-text-secondary, #666);
                    font-size: 14px;
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
        console.log('[FLOSC Auth] Processing email auth:', email);
        
        // Update button to show loading
        const submitBtn = document.querySelector('.flosc-auth-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating account...';
        }
        
        try {
            // Call the email registration API
            const response = await fetch(`${this.config.restUrl}register-email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({ email })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.hideAuthModal();
                this.addMessage('assistant', `✅ Welcome! You're now logged in as ${email}. Let's continue!`);
                
                // Refresh page to update user state
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                throw new Error(result.message || 'Registration failed');
            }
        } catch (error) {
            console.error('[FLOSC Auth] Email auth error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Continue with Email';
            }
            alert('Registration failed: ' + error.message);
        }
    }
    
    initiateSSO(provider, authUrl) {
        console.log('[FLOSC SSO] Initiating SSO with:', provider);
        
        // Add redirect_to parameter so user comes back here
        // v1.4.6: Use & if URL already has query params (e.g. non-pretty permalinks)
        const redirectTo = window.location.href;
        const separator = authUrl.includes('?') ? '&' : '?';
        const fullAuthUrl = `${authUrl}${separator}redirect_to=${encodeURIComponent(redirectTo)}`;
        
        // Redirect to SSO provider
        window.location.href = fullAuthUrl;
    }

    openFreeLesson() {
        this.requestFreeLesson();
    }

    openLessonLibrary() {
        window.location.href = this.config.lessonsUrl || '/lessons/';
    }

    openPersonalizedPath() {
        window.location.href = this.config.pathUrl || '/my-path/';
    }

    // MTS-2026-02-08: Added to match IVR action open_quiz_library
    openQuizLibrary() {
        window.location.href = this.config.quizzesUrl || '/quizzes/';
    }

    resumeLastLesson() {
        this.addMessage('assistant', 'Resuming your last lesson...');
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
    getProductInfo(productId) {
        const products = {
            'flosc_plugin': {
                id: 'flosc_plugin',
                name: 'FLOSC WordPress Plugin',
                icon: '🔌',
                memberLevel: 'flosc_plugin_member',
            },
            'simplified_solfeggio': {
                id: 'simplified_solfeggio',
                name: 'Simplified Solfeggio',
                icon: '🎵',
                memberLevel: 'simplified_solfeggio_member',
            },
            'lesaep': {
                id: 'lesaep',
                name: 'LeSAEp Pronunciation',
                icon: '🎤',
                memberLevel: 'lesaep_member',
            },
        };
        
        return products[productId] || {
            id: '',
            name: 'FLOSC Sandbox Access',
            icon: '🎮',
            memberLevel: 'flosc_sandbox',
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
            const response = await fetch(`${this.config.apiUrl}/sandbox-purchase`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    offer_id: offerId,
                    product_id: productId,
                    amount: amount,
                    sandbox: true
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // v1.4.0: Show product-aware success message
                const productIcon = result.product_icon || '🎉';
                const productName = result.product_name || 'FLOSC Access';
                const memberLevel = result.member_level || 'flosc_sandbox';
                
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
            console.error('[FLOSC Sandbox] Payment error:', error);
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
        console.log('[FLOSC-CHECKOUT] Opening checkout for offer:', offerId, offer);
        
        // Track checkout initiated
        this.trackEvent('checkout_initiated', { offer_id: offerId });
        
        // Determine payment method
        const pricing = offer?.pricing || {};
        
        // Priority: Stripe in-chat > Stripe modal > Redirect checkout
        // v1.6.3: Don't require price_id client-side — server resolves amount from offer or product config
        if (this.config.stripeKey) {
            // Check if we should use inline checkout (already in chat)
            const inlineCheckout = document.querySelector(`.flosc-checkout-inline[data-offer-id="${offerId}"]`);
            if (inlineCheckout) {
                // Already showing inline checkout - focus on card field
                const cardEl = document.getElementById(`flosc-inline-card-${offerId}`);
                if (cardEl) cardEl.focus();
                return;
            }
            
            // Show payment modal
            this.showPaymentModal(offerId);
            
        } else if (pricing.redirect_url || offer?.checkout_url) {
            // External checkout (ClickBank, etc.)
            const redirectUrl = pricing.redirect_url || offer.checkout_url;
            this.addMessage('assistant', `Redirecting you to checkout... You'll be brought back here after payment.`);
            
            // Store state for return
            localStorage.setItem('flosc_pending_purchase', JSON.stringify({
                offer_id: offerId,
                timestamp: Date.now(),
                return_url: window.location.href
            }));
            
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, 1500);
            
        } else {
            // Fallback to checkout page
            window.location.href = this.config.checkoutUrl || '/checkout/';
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
                    console.log('[FLOSC-CHECKOUT] Checking pending purchase:', data.offer_id);
                    // The server should have updated user state via webhook
                    // Just clear the pending state
                }
                localStorage.removeItem('flosc_pending_purchase');
            }
        } catch (e) {
            console.warn('[FLOSC-CHECKOUT] Error checking pending purchase', e);
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

        if (lowerMessage === 'show intropanel' || lowerMessage === 'show promptpanel' || lowerMessage === 'show suggested') {
            this.floscShowUserAutoPrompts();
            return true;
        }
        if (lowerMessage === 'hide intropanel' || lowerMessage === 'hide promptpanel' || lowerMessage === 'hide suggested') {
            const container = document.getElementById('flosc_input_user_autoprompts_panel');
            if (container) container.remove();
            this.addMessage('assistant', 'Panel hidden. Type "show suggested" to see it again.');
            return true;
        }

        if (lowerMessage === 'ivr status') {
            this.showIVRStatus();
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

    // ==========================================
    // Core App Methods
    // ==========================================
    
    bindElements() {
        console.log('[FLOSC] bindElements() called');
        this.sidebar = document.getElementById('flosc_app_sidebar');
        this.sidebarToggle = document.getElementById('flosc_app_sidebar_toggle');
        this.sessionList = document.getElementById('flosc_app_session_list');
        this.newSessionBtn = document.getElementById('flosc_app_new_session_button');
        this.chatMessages = document.getElementById('flosc_output_chat_responses');
        this.chatInput = document.getElementById('flosc_input_chat_field');
        this.sendBtn = document.getElementById('flosc_input_chat_send_button');
        this.voiceBtn = document.getElementById('flosc_input_chat_voice_button');
        this.quizSection = document.getElementById('flosc_quiz_section');
        this.shareBtn = document.getElementById('flosc_app_share_button');
        this.shareModal = document.getElementById('flosc_modal_share');
        
        console.log('[FLOSC] Critical elements found:', {
            chatInput: this.chatInput ? 'FOUND' : 'MISSING',
            sendBtn: this.sendBtn ? 'FOUND' : 'MISSING',
            chatMessages: this.chatMessages ? 'FOUND' : 'MISSING'
        });
    }
    
    bindEvents() {
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
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
        
        if (this.voiceBtn) {
            this.voiceBtn.addEventListener('click', () => this.toggleRecording());
        }
        
        if (this.shareBtn) {
            this.shareBtn.addEventListener('click', () => this.openShareModal());
        }

        const restartBtn = document.getElementById('flosc_app_restart_chat');
        if (restartBtn) {
            restartBtn.addEventListener('click', () => this.restartChat());
        }

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
    async storeQuizScore(result) {
        // Store in localStorage as backup
        const quizData = {
            score: result.score,
            correct: result.correct,
            total: result.total,
            passed: result.passed,
            timestamp: Date.now(),
            userAnswer: result.userAnswer
        };
        localStorage.setItem('flosc_quiz_result', JSON.stringify(quizData));
        
        // Also store via API (sets cookie for server-side access)
        try {
            await fetch(this.config.apiUrl + '/store-score', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                },
                body: JSON.stringify({
                    score: result.score,
                    quiz_type: 'sequence',
                    details: quizData
                })
            });
        } catch (e) {
            console.error('FLOSC: Could not store quiz score', e);
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
            console.error('FLOSC: Could not start quiz recording', e);
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
            const response = await fetch(this.config.apiUrl + '/transcribe', {
                method: 'POST',
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
            console.error('FLOSC: Quiz transcription failed', e);
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

        console.log('FLOSC: Chat restarted');
    }
    
    setupUI() {
        const sidebarOpen = localStorage.getItem('flosc_sidebar_open') === 'true';
        if (sidebarOpen && this.sidebar) {
            this.sidebar.classList.add('open');
        }

        // v9.0.6: Set profile avatar, name, and email from WordPress
        if (this.user && this.state !== 'visitor') {
            const profileAvatar = document.getElementById('flosc_app_profile_avatar');
            const profileName = document.getElementById('flosc_app_profile_name');
            const profileBadge = document.getElementById('flosc_app_profile_badge');
            const dropdownName = document.getElementById('flosc_app_dropdown_name');
            const dropdownEmail = document.getElementById('flosc_app_dropdown_email');

            if (profileAvatar && this.user.avatar) {
                profileAvatar.src = this.user.avatar;
                profileAvatar.alt = this.user.name || 'User avatar';
            }

            if (profileName && this.user.name) {
                profileName.textContent = this.user.name;
            }

            if (profileBadge) {
                // Show user state: Visitor, Guest, or Member
                const stateMap = {
                    'visitor': 'Visitor',
                    'guest': 'Guest',
                    'member': 'Member'
                };
                profileBadge.textContent = stateMap[this.state] || 'Guest';
            }

            if (dropdownName && this.user.name) {
                dropdownName.textContent = this.user.name;
            }

            if (dropdownEmail && this.user.email) {
                dropdownEmail.textContent = this.user.email;
            }
        }
    }

    toggleSidebar() {
        if (this.sidebar) {
            this.sidebar.classList.toggle('open');
            localStorage.setItem('flosc_sidebar_open', this.sidebar.classList.contains('open'));
        }
    }
    
    async sendMessage() {
        const message = this.chatInput?.value?.trim();
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
        
        this.showTyping();
        
        // Try IVR match first
        const ivrMatch = this.findIVRResponse(message);
        if (ivrMatch && this.evaluateCondition(ivrMatch.conditions)) {
            setTimeout(() => {
                this.hideTyping();
                const content = this.replaceVariables(ivrMatch.content);
                const el = this.addMessage('assistant', content);
                if (el && ivrMatch.name) el.setAttribute('data-message-name', ivrMatch.name);
                
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', content);
                }
                
                // v9.3.4: Execute action if present (e.g., open_quiz)
                if (ivrMatch.action) {
                    console.log('FLOSC: IVR action triggered:', ivrMatch.action);
                    this.performIVRAction(ivrMatch.action);
                }
                
                this.floscShowUserAutoPrompts();
            }, 500);
            return;
        }
        
        // No IVR match - call API
        try {
            const response = await this.callAPI(message);
            this.hideTyping();
            
            if (response) {
                this.addMessage('assistant', response);
                
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', response);
                }
                
                this.floscShowUserAutoPrompts();
            } else {
                this.addMessage('assistant', "I'm having trouble responding right now. Please try again.");
            }
        } catch (error) {
            console.error('FLOSC: API error:', error);
            this.hideTyping();
            this.addMessage('assistant', "I'm having trouble responding right now. Please try again.");
        }
    }
    
    findIVRResponse(userMessage) {
        // MTS-2026-02-02: [FIX] Check config messages FIRST, then API messages
        // Config has {user_status_response} placeholder that triggers client-side generation
        // API may return pre-evaluated text from server (which lacks user context)
        const configMessages = Object.values(this.config.ivrMessages || {});
        const apiMessages = Object.values(this.ivr.messages || {});
        const allMessages = [...configMessages, ...apiMessages];
        
        console.log('[FLOSC-FIND] Looking for:', userMessage);
        console.log('[FLOSC-FIND] Config messages count:', configMessages.length);
        console.log('[FLOSC-FIND] API messages count:', apiMessages.length);
        
        // Match ANY message that has user_input defined
        const match = allMessages.find(m => 
            m.user_input && 
            m.user_input.toLowerCase() === userMessage.toLowerCase()
        );
        
        console.log('[FLOSC-FIND] Match found:', match ? match.name : 'NONE');
        console.log('[FLOSC-FIND] Match content:', match?.content);
        
        return match || null;
    }
    
    addMessage(role, content, isHtml = false) {
        console.log('[FLOSC] addMessage() called:', {role, contentLength: content?.length, isHtml});

        if (!this.chatMessages) {
            console.error('[FLOSC] ERROR: chatMessages container not found!');
            return null;
        }

        console.log('[FLOSC] Creating message element...');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;

        const emoji = this.config.product?.emoji || '🎯';
        const userInitial = this.user?.name?.charAt(0).toUpperCase() || 'U';

        if (role === 'user') {
            messageDiv.innerHTML = `
                <div class="message-avatar">${userInitial}</div>
                <div class="message-content">
                    <div class="message-text">${this.escapeHtml(content)}</div>
                </div>
            `;
        } else {
            // Format content based on isHtml flag
            const formatted = isHtml ? content : this.escapeHtml(content);
            messageDiv.innerHTML = `
                <div class="message-avatar">${emoji}</div>
                <div class="message-content">
                    <div class="message-text">${formatted}</div>
                </div>
            `;
        }

        console.log('[FLOSC] Appending to chatMessages container...');
        this.chatMessages.appendChild(messageDiv);
        
        // v9.3.5: Reliable auto-scroll using double rAF to ensure DOM update completes
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
            });
        });
        console.log('[FLOSC] Message added successfully');

        // v9.0.6: Hide greeting title after 3 messages for returning guests
        if (this.ivr.messageCount >= 3) {
            const greetingTitle = document.getElementById('greetingTitle');
            if (greetingTitle) {
                greetingTitle.style.display = 'none';
            }
        }

        // v8.0.9: Return element so caller can add attributes
        return messageDiv;
    }
    
    showTyping() {
        const typing = document.getElementById('flosc_output_chat_typing_indicator');
        if (typing) {
            typing.classList.add('show');
        }
    }

    hideTyping() {
        const typing = document.getElementById('flosc_output_chat_typing_indicator');
        if (typing) {
            typing.classList.remove('show');
        }
    }
    
    async callAPI(message) {
        // MTS-2026-02-02: [AUTH-FIX] credentials: 'same-origin' is REQUIRED
        // Without it, browser does not send cookies with the request.
        // WordPress REST API needs cookies + nonce to authenticate users.
        // This was the root cause of admin showing as "Visitor".
        const response = await fetch(this.config.apiUrl + '/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.config.nonce
            },
            body: JSON.stringify({
                message: message,
                session_id: this.currentSession?.id,
                context: this.ivr.context,
                // v1.3.7: Flow context for multi-flow support
                flow_id: this.config.flowId,
                ivr_file: this.config.ivrFile
            })
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
            console.warn('FLOSC: Could not save visitor message', e);
        }
    }
    
    restoreVisitorMessages() {
        try {
            const messages = JSON.parse(localStorage.getItem('flosc_visitor_messages') || '[]');
            messages.forEach(msg => {
                this.addMessage(msg.role, msg.content);
            });
        } catch (e) {
            console.warn('FLOSC: Could not restore visitor messages', e);
        }
    }
    
    async loadSessions() {
        try {
            const response = await fetch(this.config.apiUrl + '/sessions', {
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (data.success && this.sessionList) {
                this.renderSessions(data.sessions);
            }
        } catch (e) {
            console.warn('FLOSC: Could not load sessions', e);
        }
    }
    
    renderSessions(sessions) {
        if (!this.sessionList) return;
        
        this.sessionList.innerHTML = sessions.map(s => `
            <div class="flosc-session-item ${s.id === this.currentSession?.id ? 'active' : ''}" 
                 data-session-id="${s.id}">
                <span class="flosc-session-title">${this.escapeHtml(s.title || 'Untitled')}</span>
                <span class="flosc-session-date">${s.date}</span>
            </div>
        `).join('');
        
        this.sessionList.querySelectorAll('.flosc-session-item').forEach(item => {
            item.addEventListener('click', () => this.loadSession(item.dataset.sessionId));
        });
    }
    
    async loadSession(sessionId) {
        try {
            const response = await fetch(this.config.apiUrl + '/sessions/' + sessionId, {
                headers: { 'X-WP-Nonce': this.config.nonce }
            });
            const data = await response.json();
            
            if (data.success) {
                this.currentSession = data.session;
                this.chatMessages.innerHTML = '';
                data.session.messages.forEach(msg => {
                    this.addMessage(msg.role, msg.content);
                });
            }
        } catch (e) {
            console.warn('FLOSC: Could not load session', e);
        }
    }
    
    newSession() {
        this.currentSession = null;
        if (this.chatMessages) {
            this.chatMessages.innerHTML = '';
        }
        
        this.ivr.messageCount = 0;
        this.ivr.shownThisSession = {};
        this.ivr.context.first_show_session = true;
        this.buildIVRContext();
        this.checkAutoMessages();
        this.floscShowUserAutoPrompts();
    }
    
    async checkPendingQuizResults() {
        // v9.3.4: Check for quiz result stored before login
        try {
            const stored = localStorage.getItem('flosc_quiz_result');
            if (stored) {
                const result = JSON.parse(stored);
                // Only show if recent (within last hour) and user just logged in
                const age = Date.now() - (result.timestamp || 0);
                if (age < 3600000) { // 1 hour
                    console.log('[FLOSC] Revealing quiz score after login:', result.score);
                    
                    // Show the score they earned before signup
                    const correct = result.correct || 0;
                    const total = result.total || 10;
                    const incorrect = total - correct;
                    
                    this.addMessage('assistant', `🎉 Welcome! Here are your quiz results:`);
                    setTimeout(() => {
                        this.showQuizResults(result.score, correct, incorrect);
                    }, 300);
                    
                    // Clear the stored result
                    localStorage.removeItem('flosc_quiz_result');
                    
                    // Update context
                    this.ivr.context.first_message_after_quiz = true;
                    this.ivr.context.first_message_after_login = true;
                    this.ivr.context.score = result.score;
                }
            }
        } catch (e) {
            console.error('[FLOSC] Could not check pending quiz results', e);
        }
        
        if (this.user?.justCompletedQuiz) {
            this.ivr.context.first_message_after_quiz = true;
        }
        
        // v1.4.6: Check if user just logged in (transient set by wp_login hook)
        if (this.user?.justLoggedIn) {
            this.ivr.context.first_message_after_login = true;
        }
        
        // v1.4.6: Check if user just completed a purchase (transient set by complete_purchase/webhook)
        if (this.user?.justPurchased) {
            this.ivr.context.first_message_after_purchase = true;
        }
        
        // v1.6.2: Single check after all flags are set (debounced, so rapid calls coalesce)
        if (this.user?.justCompletedQuiz || this.user?.justLoggedIn || this.user?.justPurchased) {
            this.checkAutoMessages();
        }
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
    
    async toggleRecording() {
        if (this.isRecording) {
            this.stopRecording();
        } else {
            await this.startRecording();
        }
    }
    
    async startRecording() {
        try {
            this.recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(this.recordingStream);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = (e) => {
                this.audioChunks.push(e.data);
            };
            
            this.mediaRecorder.onstop = () => {
                this.processRecording();
            };
            
            this.mediaRecorder.start();
            this.isRecording = true;
            
            if (this.voiceBtn) {
                this.voiceBtn.classList.add('recording');
            }
        } catch (e) {
            console.error('FLOSC: Could not start recording', e);
            this.addMessage('assistant', 'Could not access microphone. Please check permissions.');
        }
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            if (this.recordingStream) {
                this.recordingStream.getTracks().forEach(track => track.stop());
            }
            
            if (this.voiceBtn) {
                this.voiceBtn.classList.remove('recording');
            }
        }
    }
    
    async processRecording() {
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        
        const formData = new FormData();
        formData.append('audio', audioBlob);
        
        try {
            const response = await fetch(this.config.apiUrl + '/transcribe', {
                method: 'POST',
                headers: { 'X-WP-Nonce': this.config.nonce },
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success && data.transcript) {
                this.chatInput.value = data.transcript;
                this.sendMessage();
            }
        } catch (e) {
            console.error('FLOSC: Transcription failed', e);
        }
    }
    
    async requestFreeLesson() {
        this.showTyping();

        try {
            const response = await fetch(this.config.apiUrl + '/free-lesson', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const data = await response.json();
            this.hideTyping();

            // v1.5.4: Handle multiple lessons
            const lessons = data.lessons || (data.lesson ? [data.lesson] : []);

            if (data.success && lessons.length > 0) {
                this.ivr.context.lesson_viewed = true;
                this.ivr.context.first_message_after_free_lesson = true;

                if (lessons.length === 1) {
                    this.addMessage('assistant', `Here's your complimentary lesson: **${lessons[0].title}**\n\n${lessons[0].content}`);
                } else {
                    let msg = `Here are your **${lessons.length} complimentary lessons**:\n\n`;
                    lessons.forEach((lesson, i) => {
                        msg += `---\n\n### Lesson ${i + 1}: ${lesson.title}\n\n${lesson.content}\n\n`;
                    });
                    this.addMessage('assistant', msg);
                }

                this.ivr.phase = 'offer';

                setTimeout(() => this.checkAutoMessages(), 2000);
            } else {
                this.addMessage('assistant', data.message || 'Could not load your free lesson. Please try again.');
            }
        } catch (e) {
            this.hideTyping();
            console.error('FLOSC: Free lesson request failed', e);
            this.addMessage('assistant', 'Sorry, there was an error loading your lesson.');
        }
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

        // v1.5.4: Mount Stripe Elements into the modal card-element div
        if (!this.stripe) {
            console.error('[FLOSC-CHECKOUT] Stripe not initialized');
            return;
        }

        const mountPoint = document.getElementById('card-element');
        if (!mountPoint) return;

        // Unmount previous card element if any
        if (this.cardElement) {
            this.cardElement.unmount();
            this.cardElement.destroy();
        }

        const elements = this.stripe.elements();
        this.cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#374151',
                    '::placeholder': { color: '#9ca3af' },
                },
            },
        });
        this.cardElement.mount(mountPoint);

        const payBtn = document.getElementById('payBtn');
        const errorEl = document.getElementById('card-errors');

        // Enable pay button when card is complete
        this.cardElement.on('change', (event) => {
            if (event.complete) {
                payBtn.disabled = false;
            } else {
                payBtn.disabled = true;
            }
            if (event.error) {
                errorEl.textContent = event.error.message;
            } else {
                errorEl.textContent = '';
            }
        });

        // Remove old listener, add new
        const newPayBtn = payBtn.cloneNode(true);
        payBtn.parentNode.replaceChild(newPayBtn, payBtn);
        newPayBtn.disabled = true;

        newPayBtn.addEventListener('click', async () => {
            await this.processModalPayment(offerId, newPayBtn, errorEl);
        });

        // Close button
        const closeBtn = document.getElementById('paymentModalClose');
        if (closeBtn) {
            closeBtn.onclick = () => { modal.style.display = 'none'; };
        }
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
            const intentRes = await fetch(this.config.apiUrl + '/create-payment-intent', {
                method: 'POST',
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
                await fetch(this.config.apiUrl + '/complete-purchase', {
                    method: 'POST',
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
            console.error('[FLOSC-CHECKOUT] Payment error:', err);
            errorEl.textContent = err.message || 'Payment failed. Please try again.';
            if (payBtn.querySelector('.flosc-pay-btn-text')) payBtn.querySelector('.flosc-pay-btn-text').textContent = originalText;
            payBtn.disabled = false;
        } finally {
            if (this.offers) this.offers.checkoutInProgress = false;
        }
    }
    
    openShareModal() {
        if (this.shareModal) {
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
        console.log('FLOSC Event:', event, data);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    window.FLOSC = new floscApp();
    window.floscAppInstance = window.FLOSC; // v9.3.2: Alias for quiz button handlers
});
