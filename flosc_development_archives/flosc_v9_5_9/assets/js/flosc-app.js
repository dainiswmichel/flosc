/**
 * FLOSC App JavaScript
 * Main application controller
 * v9.5.3: Sidebar profile positioning, consolidated release
 */

// v9.4.7: Clear FLOSC-specific localStorage on version change
(function() {
    const FLOSC_JS_VERSION = '9.5.9';
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
            console.log('FLOSC v9.3.3: Storage cleared - fresh session');
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
            'purchased', 'lesson_viewed', 'onboarded', 'lessons_completed',
            'has_incomplete_lesson', 'completed_quizzes',
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
            shownThisSession: {},
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

        // Initialize
        this.init();
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
     */
    async loadIVRMessages() {
        try {
            const url = `${this.config.apiUrl}/ivr-messages?phase=${this.ivr.phase}`;
            console.log('[FLOSC] Fetching IVR messages from:', url);
            
            const response = await fetch(url);
            
            if (!response.ok) {
                console.error('[FLOSC] API returned error:', response.status);
                this.ivr.messages = this.getFallbackMessages();
                return;
            }
            
            const data = await response.json();
            console.log('[FLOSC] API response:', data);
            
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

        // Add base suggested replies CSS
        // v9.3.5: Superlight styling
        const baseStyles = document.createElement('style');
        baseStyles.id = 'flosc-ivr-base-styles';
        baseStyles.textContent = `
            .flosc-suggested-replies {
                padding: 12px 16px;
                background: transparent;
                border-top: 1px solid rgba(0, 0, 0, 0.04);
            }
            .flosc-replies-scroll {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
            }
            .flosc-replies-scroll::-webkit-scrollbar {
                height: 4px;
            }
            .flosc-replies-scroll::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.1);
                border-radius: 2px;
            }
            .flosc-reply-icon {
                font-size: 16px;
            }
            .flosc-reply-text {
                white-space: nowrap;
            }
            .flosc-offer-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 12px;
                padding: 20px;
                margin: 8px 0;
                position: relative;
            }
            .flosc-offer-close {
                position: absolute;
                top: 8px;
                right: 8px;
                background: transparent;
                color: #fff;
                border: none;
                font-size: 20px;
                line-height: 1;
                cursor: pointer;
                opacity: 0.8;
            }
            .flosc-offer-close:hover { opacity: 1; }
            .flosc-offer-content {
                margin-bottom: 16px;
                line-height: 1.6;
            }
            .flosc-offer-timer {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 16px;
            }
            .flosc-offer-cta {
                width: 100%;
                padding: 14px 24px !important;
                font-size: 16px !important;
                background: #10b981 !important;
            }
            .flosc-offer-cta:hover {
                background: #059669 !important;
            }
            .flosc-timer-expired {
                color: #fca5a5;
            }
            
            /* v9.3.2: In-Chat Quiz Styles */
            .flosc-quiz-question {
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                border-radius: 12px;
                padding: 16px;
                margin: 8px 0;
            }
            .flosc-quiz-question-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
                font-size: 12px;
                color: #0369a1;
                font-weight: 600;
            }
            .flosc-quiz-question-text {
                font-size: 16px;
                font-weight: 500;
                color: #0c4a6e;
                margin-bottom: 16px;
                line-height: 1.5;
            }
            .flosc-quiz-options {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .flosc-quiz-option {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                background: white;
                border: 2px solid #e0f2fe;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s ease;
                font-size: 14px;
                color: #0c4a6e;
                text-align: left;
                width: 100%;
            }
            .flosc-quiz-option:hover {
                border-color: #38bdf8;
                background: #f0f9ff;
            }
            .flosc-quiz-option:active {
                transform: scale(0.98);
            }
            .flosc-quiz-option-key {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                background: #0ea5e9;
                color: white;
                border-radius: 6px;
                font-weight: 600;
                font-size: 13px;
                flex-shrink: 0;
            }
            .flosc-quiz-option-text {
                flex: 1;
            }
            .flosc-quiz-progress {
                height: 4px;
                background: #e0f2fe;
                border-radius: 2px;
                overflow: hidden;
                margin-top: 12px;
            }
            .flosc-quiz-progress-bar {
                height: 100%;
                background: #0ea5e9;
                transition: width 0.3s ease;
            }
            .flosc-quiz-result {
                background: linear-gradient(135deg, #059669 0%, #10b981 100%);
                color: white;
                border-radius: 12px;
                padding: 20px;
                margin: 8px 0;
                text-align: center;
            }
            .flosc-quiz-result.low-score {
                background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
            }
            .flosc-quiz-result.medium-score {
                background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
            }
            .flosc-quiz-result-score {
                font-size: 48px;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .flosc-quiz-result-label {
                font-size: 14px;
                opacity: 0.9;
                margin-bottom: 16px;
            }
            .flosc-quiz-result-cta {
                display: inline-block;
                padding: 12px 24px;
                background: white;
                color: #059669;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                border: none;
                font-size: 14px;
                transition: transform 0.2s ease;
            }
            .flosc-quiz-result-cta:hover {
                transform: scale(1.05);
            }
        `;
        document.head.appendChild(baseStyles);
    }

    startIVR() {
        console.log('FLOSC v9.0.6: Starting IVR for phase:', this.ivr.phase);
        console.log('FLOSC: Total messages available:', Object.keys(this.ivr.messages).length);
        
        // v8.0.9: RULE #1 - If chat is empty, ALWAYS show welcome. Period.
        // This is idempotent and defensive - check DOM, not localStorage
        const existingMessages = this.chatMessages?.querySelectorAll('.message, .flosc-message') || [];
        
        if (existingMessages.length === 0) {
            console.log('FLOSC: Chat is empty - showing welcome message');
            
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

            // Show suggested replies
            this.showSuggestedReplies();
            
            // Start inactivity timer
            this.startInactivityTimer();
            
            return; // Done - chat is now responsive
        }
        
        // v8.0.9: RULE #2 - If chat has messages, try IVR matching (but don't fail silently)
        console.log('FLOSC: Chat has', existingMessages.length, 'messages - checking for auto messages');
        this.checkAutoMessages();
        
        // Always show suggested replies (helps user know what to do)
        setTimeout(() => this.showSuggestedReplies(), 500);
        
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

    checkAutoMessages() {
        console.log('FLOSC: Checking auto messages for phase:', this.ivr.phase);

        const messages = Object.values(this.ivr.messages);
        console.log('FLOSC: Total messages loaded:', messages.length);

        const autoMessages = messages.filter(m => m.type === 'auto');
        console.log('FLOSC: Auto messages found:', autoMessages.length);

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

    showSuggestedReplies() {
        const messages = Object.values(this.ivr.messages);
        const autoPrompts = messages.filter(m => m.type === 'suggested_user_autoprompt');

        // v9.3.6: Always show PromptPanel for all user states
        // Collect applicable prompts based on phase
        const applicable = [];
        
        for (const msg of autoPrompts) {
            // Skip if wrong phase
            if (msg.phase && msg.phase !== this.ivr.phase) continue;
            
            // For visitors, show all prompts in current phase
            if (this.state === 'visitor') {
                applicable.push(msg);
                continue;
            }
            
            // For logged-in users (guest/member), check conditions if specified
            if (!msg.conditions || msg.conditions === 'always' || this.evaluateCondition(msg.conditions)) {
                applicable.push(msg);
            }
        }
        
        // v9.3.6: If no conditional matches for logged-in user, show all phase prompts anyway
        if (applicable.length === 0 && this.state !== 'visitor') {
            console.log('FLOSC: No conditional matches, showing all phase prompts for logged-in user');
            for (const msg of autoPrompts) {
                if (!msg.phase || msg.phase === this.ivr.phase) {
                    applicable.push(msg);
                }
            }
        }

        // v9.5.9: NO hardcoded fallbacks - IVR.md ONLY
        if (applicable.length === 0) {
            console.log('FLOSC: No suggested replies from IVR for phase:', this.ivr.phase, 'state:', this.state);
            return; // Don't render anything - IVR.md should provide all prompts
        }

        console.log('FLOSC: Suggested replies for', this.state, ':', applicable.map(r => r.name));
        this.renderSuggestedReplies(applicable);
    }

    // v9.1.1: Memory leak prevention - cleanup event listeners
    cleanupSuggestedReplies() {
        // Remove all tracked event listeners
        this.activeEventListeners.forEach((handler, element) => {
            if (element && element.parentNode) {
                element.removeEventListener('click', handler);
            }
        });
        this.activeEventListeners.clear();
        
        // Remove DOM element
        const existing = document.getElementById('flosc_output_chat_suggested_replies');
        if (existing) {
            existing.remove();
        }
    }

    renderSuggestedReplies(replies) {
        // v9.1.1: Clean up old listeners first
        this.cleanupSuggestedReplies();

        if (replies.length === 0) return;

        // v9.3.5: Use PromptPanel for logged-in users (guest/member), IntroPanel for visitors
        const isLoggedIn = this.state === 'guest' || this.state === 'member';
        const panelName = isLoggedIn ? 'PromptPanel' : 'IntroPanel';
        
        // v9.5.7: Check if any replies use card style - cards get carousel, pills get simple flex
        const hasCards = replies.some(r => r.style === 'card');
        const useCarousel = hasCards;

        const container = document.createElement('div');
        container.id = 'flosc_output_chat_suggested_replies';
        container.className = 'prompt-panel prompt-panel-inline';
        
        // v9.5.7: Conditional carousel or simple pill layout
        const buttonsHtml = replies.map(r => `
            <button class="flosc-style-${r.style || 'pill'}" data-message="${this.escapeHtml(r.name)}">
                ${r.icon ? `<span class="flosc-reply-icon">${r.icon}</span>` : ''}
                <span class="flosc-reply-text">${this.escapeHtml(r.user_input)}</span>
            </button>
        `).join('');
        
        if (useCarousel) {
            // Cards: use carousel with arrows
            container.innerHTML = `
                <div class="prompt-panel-header">
                    <div class="prompt-panel-eyebrow">Try these AutoPrompts!</div>
                    <div class="prompt-panel-title">${panelName}</div>
                    <button class="prompt-panel-close" aria-label="Hide ${panelName}">×</button>
                </div>
                <div class="prompt-panel-body">
                    <div class="flosc-carousel-container">
                        <button class="flosc-carousel-arrow flosc-carousel-prev" aria-label="Previous">‹</button>
                        <div class="flosc-carousel-track">
                            ${buttonsHtml}
                        </div>
                        <button class="flosc-carousel-arrow flosc-carousel-next" aria-label="Next">›</button>
                    </div>
                </div>
            `;
        } else {
            // Pills: simple flex layout, no carousel
            container.innerHTML = `
                <div class="prompt-panel-header">
                    <div class="prompt-panel-eyebrow">Try these AutoPrompts!</div>
                    <div class="prompt-panel-title">${panelName}</div>
                    <button class="prompt-panel-close" aria-label="Hide ${panelName}">×</button>
                </div>
                <div class="prompt-panel-body">
                    <div class="flosc-pills-container">
                        ${buttonsHtml}
                    </div>
                </div>
            `;
        }

        // Button click handlers
        // v9.1.1: Track listeners for cleanup
        container.querySelectorAll('button.flosc-style-pill, button.flosc-style-chip, button.flosc-style-button, button.flosc-style-card').forEach(btn => {
            const handler = () => {
                const msgName = btn.dataset.message;
                this.handleSuggestedReply(msgName);
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

        // Initialize carousel only if using cards
        if (useCarousel) {
            this.initCarousel(container);
        }

        const composer = document.getElementById('flosc_input_composer');
        if (composer && composer.parentElement) {
            composer.parentElement.insertBefore(container, composer.nextElementSibling);
        }
    }

    initCarousel(container) {
        const track = container.querySelector('.flosc-carousel-track');
        const prevBtn = container.querySelector('.flosc-carousel-prev');
        const nextBtn = container.querySelector('.flosc-carousel-next');

        if (!track || !prevBtn || !nextBtn) return;

        const items = Array.from(track.children);
        if (items.length < 2) {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
            return;
        }

        // Simple carousel navigation - scroll by visible width
        const scrollAmount = () => track.clientWidth * 0.8;

        const updateButtons = () => {
            const atStart = track.scrollLeft <= 0;
            const atEnd = track.scrollLeft >= track.scrollWidth - track.clientWidth - 5;
            prevBtn.disabled = atStart;
            nextBtn.disabled = atEnd;
        };

        prevBtn.addEventListener('click', () => {
            track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' });
            setTimeout(updateButtons, 400);
        });

        nextBtn.addEventListener('click', () => {
            track.scrollBy({ left: scrollAmount(), behavior: 'smooth' });
            setTimeout(updateButtons, 400);
        });

        // Touch/swipe support
        let touchStartX = 0;
        track.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        track.addEventListener('touchend', (e) => {
            const diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                track.scrollBy({ left: diff > 0 ? scrollAmount() : -scrollAmount(), behavior: 'smooth' });
                setTimeout(updateButtons, 400);
            }
        });

        track.addEventListener('scroll', () => {
            updateButtons();
        });

        updateButtons();
    }

    handleSuggestedReply(messageName) {
        const msg = this.ivr.messages[messageName];
        if (!msg) {
            console.error('FLOSC: IVR message not found:', messageName, '- Check IVR.md configuration');
            return; // v9.5.9: NO fallbacks - IVR.md ONLY
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
                this.showSuggestedReplies();
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

    showOfferMessage(msg) {
        let content = this.replaceVariables(msg.content);
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        this.offerStartTime = Date.now();
        const timerSeconds = msg.timer || 3600;

        const offerHtml = `
            <div class="flosc-offer-card">
                <button class="flosc-offer-close" aria-label="Dismiss">×</button>
                <div class="flosc-offer-content">${content.replace(/\n/g, '<br>')}</div>
                <div class="flosc-offer-timer" id="flosc-offer-timer-${msg.offer_id}">
                    <span class="flosc-timer-icon">⏱️</span>
                    <span class="flosc-timer-value">${this.formatTime(timerSeconds)}</span>
                </div>
                <button class="flosc-offer-cta flosc-style-button" data-action="checkout_${msg.offer_id}">
                    🔓 Get Full Access Now
                </button>
            </div>
        `;

        this.addMessage('assistant', offerHtml, true);
        this.ivr.shownThisSession['offer_' + msg.offer_id] = true;
        this.startOfferTimer(msg.offer_id, timerSeconds);

        setTimeout(() => {
            const cta = document.querySelector(`[data-action="checkout_${msg.offer_id}"]`);
            if (cta) {
                cta.addEventListener('click', () => this.performIVRAction('checkout_' + msg.offer_id));
            }
            const closeBtn = document.querySelector('.flosc-offer-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    const card = closeBtn.closest('.flosc-offer-card');
                    if (card) card.remove();
                    this.ivr.shownThisSession['offer_dismissed_' + msg.offer_id] = true;
                    // Track dismissal
                    try {
                        fetch(this.config.apiUrl + '/ivr/track', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': this.config.nonce
                            },
                            body: JSON.stringify({
                                message_name: msg.name,
                                offer_id: msg.offer_id,
                                offer_state: 'dismissed'
                            })
                        });
                    } catch(e) { console.warn('FLOSC: Could not track offer dismissal', e); }
                });
            }
        }, 100);
    }

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
            .replace(/{missed_items}/g, this.user?.missedItems || '');
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
                this.resumeLastLesson();
                break;
            case 'open_support':
                this.openSupport();
                break;
            default:
                if (action.startsWith('checkout_')) {
                    const offerId = action.replace('checkout_', '');
                    this.openCheckout(offerId);
                }
                break;
        }
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
            this.showSuggestedReplies();
        }, 1500);

        console.log('[FLOSC Quiz] Completed. Score:', scorePercent, '% Answers:', this.quiz.answers);
    }

    async storeQuizResults(score) {
        try {
            // Store in session/localStorage
            const quizResult = {
                id: this.quiz.id,
                score: score,
                answers: this.quiz.answers,
                completedAt: this.quiz.completedAt,
                duration: this.quiz.completedAt - this.quiz.startedAt
            };

            localStorage.setItem('flosc_last_quiz', JSON.stringify(quizResult));

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

    openRegistration() {
        window.location.href = this.config.registrationUrl || '/register/';
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

    resumeLastLesson() {
        this.addMessage('assistant', 'Resuming your last lesson...');
    }

    openSupport() {
        this.addMessage('assistant', "I'm here to help! What do you need assistance with?");
    }

    openCheckout(offerId) {
        if (this.config.stripeKey) {
            this.showPaymentModal(offerId);
        } else {
            window.location.href = this.config.checkoutUrl || '/checkout/';
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

        const lowerMessage = message.toLowerCase().trim();

        if (lowerMessage === 'show intropanel' || lowerMessage === 'show suggested') {
            this.showSuggestedReplies();
            return true;
        }
        if (lowerMessage === 'hide intropanel' || lowerMessage === 'hide suggested') {
            const container = document.getElementById('flosc_output_chat_suggested_replies');
            if (container) container.remove();
            this.addMessage('assistant', 'Suggested replies hidden. Type "show suggested" to see them again.');
            return true;
        }

        if (lowerMessage === 'ivr status') {
            this.showIVRStatus();
            return true;
        }

        setTimeout(() => this.showSuggestedReplies(), 500);

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

        const recordingModalClose = document.getElementById('recordingModalClose');
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
        const submitTextBtn = document.getElementById('submitTextQuizBtn');
        if (submitTextBtn) {
            submitTextBtn.addEventListener('click', () => this.submitTextQuiz());
        }
        
        // Text input enter key
        const textInput = document.getElementById('quizTextInput');
        if (textInput) {
            textInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.submitTextQuiz();
                }
            });
        }
        
        // Audio recording controls
        const recordBtn = document.getElementById('quizRecordBtn');
        const stopBtn = document.getElementById('quizStopBtn');
        const submitRecordingBtn = document.getElementById('submitRecordingBtn');
        
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
        const continueBtn = document.getElementById('quizContinueBtn');
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
        const textPanel = document.getElementById('quizTextPanel');
        const audioPanel = document.getElementById('quizAudioPanel');
        
        if (textPanel) textPanel.style.display = tabType === 'text' ? 'block' : 'none';
        if (audioPanel) audioPanel.style.display = tabType === 'audio' ? 'block' : 'none';
    }
    
    // v9.3.3: Submit text quiz answer
    submitTextQuiz() {
        const input = document.getElementById('quizTextInput');
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
        document.getElementById('quizTextPanel')?.style.setProperty('display', 'none');
        document.getElementById('quizAudioPanel')?.style.setProperty('display', 'none');
        document.querySelector('.quiz-tabs')?.style.setProperty('display', 'none');
        
        // Show result panel
        const resultPanel = document.getElementById('quizResultPanel');
        if (resultPanel) {
            resultPanel.style.display = 'block';
        }
        
        // Update score display
        const scoreDisplay = document.getElementById('quizScoreDisplay');
        if (scoreDisplay) {
            scoreDisplay.textContent = `${result.score}%`;
            scoreDisplay.className = 'quiz-score-display ' + (result.passed ? 'passed' : 'failed');
        }
        
        // Update message
        const messageEl = document.getElementById('quizResultMessage');
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
        document.getElementById('quizTextPanel')?.style.setProperty('display', 'block');
        document.getElementById('quizAudioPanel')?.style.setProperty('display', 'none');
        document.getElementById('quizResultPanel')?.style.setProperty('display', 'none');
        
        // Reset tab buttons
        document.querySelectorAll('.quiz-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === 'text');
        });
        
        // Clear input
        const input = document.getElementById('quizTextInput');
        if (input) input.value = '';
        
        // Reset recording UI
        document.getElementById('quizRecordBtn')?.style.setProperty('display', 'inline-flex');
        document.getElementById('quizStopBtn')?.style.setProperty('display', 'none');
        document.getElementById('submitRecordingBtn')?.style.setProperty('display', 'none');
        const status = document.getElementById('quizRecordingStatus');
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
            document.getElementById('quizRecordBtn')?.style.setProperty('display', 'none');
            document.getElementById('quizStopBtn')?.style.setProperty('display', 'inline-flex');
            const status = document.getElementById('quizRecordingStatus');
            if (status) {
                status.textContent = '🔴 Recording...';
                status.classList.add('recording');
            }
        } catch (e) {
            console.error('FLOSC: Could not start quiz recording', e);
            const status = document.getElementById('quizRecordingStatus');
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
            document.getElementById('quizStopBtn')?.style.setProperty('display', 'none');
            document.getElementById('submitRecordingBtn')?.style.setProperty('display', 'inline-flex');
            const status = document.getElementById('quizRecordingStatus');
            if (status) {
                status.textContent = '✅ Recording complete - ready to submit';
                status.classList.remove('recording');
            }
        }
    }
    
    // v9.3.3: Submit quiz audio recording for transcription and scoring
    async submitQuizRecording() {
        const status = document.getElementById('quizRecordingStatus');
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
                
                this.showSuggestedReplies();
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
                
                this.showSuggestedReplies();
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
        const messages = Object.values(this.ivr.messages);
        
        // Match ANY message that has user_input defined
        const match = messages.find(m => 
            m.user_input && 
            m.user_input.toLowerCase() === userMessage.toLowerCase()
        );
        
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
        const response = await fetch(this.config.apiUrl + '/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.config.nonce
            },
            body: JSON.stringify({
                message: message,
                session_id: this.currentSession?.id,
                context: this.ivr.context
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
        this.showSuggestedReplies();
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
                    'X-WP-Nonce': this.config.nonce
                }
            });
            
            const data = await response.json();
            this.hideTyping();
            
            if (data.success && data.lesson) {
                this.ivr.context.lesson_viewed = true;
                this.ivr.context.first_message_after_free_lesson = true;
                this.addMessage('assistant', `Here's your free lesson: <strong>${data.lesson.title}</strong>\n\n${data.lesson.content}`);
                
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
        if (modal) {
            modal.style.display = 'flex';
            modal.dataset.offerId = offerId;
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
