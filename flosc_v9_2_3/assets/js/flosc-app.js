/**
 * FLOSC App JavaScript
 * Main application controller
 * v9.2.1: Standardized quiz naming - flosc_sample_text_based_quiz, flosc_sample_audio_quiz
 */

// v9.2.1: Clear FLOSC-specific localStorage on version change
(function() {
    const FLOSC_JS_VERSION = '9.2.1';
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
            console.log('FLOSC v9.2.1: Storage cleared - fresh session');
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

        // Offer timer
        this.offerTimer = null;
        this.offerStartTime = null;

        // Initialize
        this.init();
    }
    
        // v8.0.5: Fallback messages if config fails to load
        getFallbackMessages() {
            return {
                'welcome_fallback': {
                    name: 'welcome_fallback',
                    type: 'auto',
                    phase: 'freeline',
                    content: "Hi! I'm your pronunciation coach. Ready to improve your English speaking skills?",
                    conditions: 'always',
                    style: 'default'
                },
                'get_started_fallback': {
                    name: 'get_started_fallback',
                    type: 'suggested_user_autoprompt',
                    phase: 'freeline',
                    style: 'pill',
                    icon: '🚀',
                    user_input: 'Get started',
                    content: "Great! Let's begin your pronunciation journey. Take our free 30-second quiz to discover which sounds you should focus on.",
                    conditions: 'always'
                },
                'how_it_works_fallback': {
                    name: 'how_it_works_fallback',
                    type: 'suggested_user_autoprompt',
                    phase: 'freeline',
                    style: 'pill',
                    icon: '❓',
                    user_input: 'How does it work?',
                    content: "Here's how it works:\n\n1. Take the free quiz - Record yourself speaking\n2. Get instant analysis - I'll identify areas for improvement\n3. Try a free lesson - Experience the teaching method\n4. Unlock all lessons - Full access to master every sound",
                    conditions: 'always'
                },
                'are_you_there_fallback': {
                    name: 'are_you_there_fallback',
                    type: 'suggested_user_autoprompt',
                    phase: 'freeline',
                    style: 'pill',
                    user_input: 'Are you there?',
                    content: "Yes, I'm here! How can I help you improve your pronunciation today?",
                    conditions: 'always'
                }
            };
        }
    
    /**
     * Load IVR messages from database via REST API (v9.2.2)
     */
    async loadIVRMessages() {
        try {
            const response = await fetch(`${this.config.apiUrl}/ivr-messages?phase=${this.ivr.phase}`);
            
            if (!response.ok) {
                console.warn('[FLOSC] Failed to load IVR messages from API, using fallbacks');
                this.ivr.messages = this.getFallbackMessages();
                return;
            }
            
            const data = await response.json();
            
            if (data.success && data.messages && data.messages.length > 0) {
                // Convert array to object keyed by message name
                const messagesObj = {};
                data.messages.forEach(msg => {
                    const key = msg.name || msg.id || `msg_${Date.now()}`;
                    messagesObj[key] = msg;
                });
                
                this.ivr.messages = messagesObj;
                console.log('[FLOSC] Loaded', data.messages.length, 'messages for phase:', this.ivr.phase);
            } else {
                console.warn('[FLOSC] API returned no messages, using fallbacks');
                this.ivr.messages = this.getFallbackMessages();
            }
            
        } catch (error) {
            console.error('[FLOSC] Error loading IVR messages:', error);
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
        const baseStyles = document.createElement('style');
        baseStyles.id = 'flosc-ivr-base-styles';
        baseStyles.textContent = `
            .flosc-suggested-replies {
                padding: 12px 16px;
                background: #fafafa;
                border-top: 1px solid #e5e7eb;
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
                background: #d1d5db;
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

        // For visitors: always show up to 4 suggestions for current phase (no condition gating)
        if (this.state === 'visitor') {
            const visitorReplies = [];
            for (const msg of autoPrompts) {
                if (!msg.phase || msg.phase === this.ivr.phase) {
                    visitorReplies.push(msg);
                    if (visitorReplies.length >= 4) break;
                }
            }
            console.log('FLOSC: Visitor suggested replies:', visitorReplies.map(r => r.name));
            this.renderSuggestedReplies(visitorReplies);
            return;
        }

        const applicable = [];
        
        // Otherwise: try to find prompts that match conditions
        for (const msg of autoPrompts) {
            if (msg.phase && msg.phase !== this.ivr.phase) continue;
            if (msg.conditions === 'always' || this.evaluateCondition(msg.conditions)) {
                applicable.push(msg);
            }
        }
        
        if (applicable.length === 0) {
            console.log('FLOSC: No conditional matches, showing all phase prompts');
            for (const msg of autoPrompts) {
                if (!msg.phase || msg.phase === this.ivr.phase) {
                    applicable.push(msg);
                    if (applicable.length >= 4) break;
                }
            }
        }
        
        if (applicable.length === 0 && this.state === 'visitor') {
            console.log('FLOSC: Using hardcoded fallback replies');
            applicable.push(
                {
                    name: 'fallback_help',
                    user_input: '❓ How does it work?',
                    content: 'I can help you improve your pronunciation!',
                    style: 'pill'
                },
                {
                    name: 'fallback_start',
                    user_input: '🚀 Get started',
                    content: "Let's begin!",
                    style: 'pill'
                }
            );
        }

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

        const container = document.createElement('div');
        container.id = 'flosc_output_chat_suggested_replies';
        container.className = 'intro-panel intro-panel-inline';
        container.innerHTML = `
            <div class="intro-panel-header">
                <div class="intro-panel-eyebrow">Try these AutoPrompts!</div>
                <div class="intro-panel-title">IntroPanel</div>
                <button class="intro-panel-close" aria-label="Hide IntroPanel">×</button>
            </div>
            <div class="intro-panel-body">
                <div class="flosc-carousel-container">
                    <button class="flosc-carousel-arrow flosc-carousel-prev" aria-label="Previous">‹</button>
                    <div class="flosc-carousel-track">
                        ${replies.map(r => `
                            <button class="flosc-style-${r.style || 'pill'}" data-message="${this.escapeHtml(r.name)}">
                                ${r.icon ? `<span class="flosc-reply-icon">${r.icon}</span>` : ''}
                                <span class="flosc-reply-text">${this.escapeHtml(r.user_input)}</span>
                            </button>
                        `).join('')}
                    </div>
                    <button class="flosc-carousel-arrow flosc-carousel-next" aria-label="Next">›</button>
                </div>
            </div>
        `;

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
        const closeBtn = container.querySelector('.intro-panel-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                container.remove();
                this.addMessage('user', 'Hide IntroPanel');
                this.addMessage('assistant', 'IntroPanel hidden. If you ever wish to see it again, just type "Show IntroPanel" in the chat, and it will reappear.');
            });
        }

        // Initialize carousel
        this.initCarousel(container);

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

        let currentScroll = 0;
        const scrollAmount = 200;

        // Arrow navigation
        nextBtn.addEventListener('click', () => {
            track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        });

        prevBtn.addEventListener('click', () => {
            track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        });

        // Swipe support (touch)
        let touchStartX = 0;
        let touchEndX = 0;

        track.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });

        track.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            const diff = touchStartX - touchEndX;
            if (Math.abs(diff) > 50) {
                track.scrollBy({ left: diff > 0 ? scrollAmount : -scrollAmount, behavior: 'smooth' });
            }
        });

        // Update arrow visibility based on scroll position
        const updateArrows = () => {
            prevBtn.style.opacity = track.scrollLeft > 0 ? '1' : '0.3';
            nextBtn.style.opacity = track.scrollLeft < (track.scrollWidth - track.clientWidth) ? '1' : '0.3';
        };

        track.addEventListener('scroll', updateArrows);
        updateArrows();
    }

    handleSuggestedReply(messageName) {
        const msg = this.ivr.messages[messageName];
        if (!msg) {
            console.warn('FLOSC: Message not found:', messageName);
            // Fallback: treat messageName as prompt and call API
            const prompt = (messageName || '').replace(/_/g, ' ').trim() || 'Help';
            this.addMessage('user', prompt);
            this.showTyping();
            this.callAPI(prompt)
                .then(resp => {
                    this.hideTyping();
                    this.addMessage('assistant', resp || "I'm here to help! What would you like to do?");
                    this.showSuggestedReplies();
                })
                .catch(err => {
                    console.error('FLOSC: API error on fallback prompt', err);
                    this.hideTyping();
                    this.addMessage('assistant', "I'm having trouble responding right now. Please try again.");
                });
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
        const btn = document.querySelector('[data-flosc-action="open-quiz"]');
        if (btn) {
            btn.click();
        } else {
            this.showRecordingModal();
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
    }

    restartChat() {
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
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
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
        if (this.user?.justCompletedQuiz) {
            this.ivr.context.first_message_after_quiz = true;
            this.checkAutoMessages();
        }
    }
    
    showRecordingModal() {
        const modal = document.getElementById('flosc_modal_recording');
        if (modal) {
            modal.style.display = 'flex';
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
});
