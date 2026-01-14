/**
 * FLOSC App JavaScript
 * Main application controller
 * v8.0.1: Production-ready IVR system with consolidated changelog
 */

// v8.0.1: Clear localStorage on version change
(function() {
    const FLOSC_JS_VERSION = '8.0.1';
    try {
        const stored = localStorage.getItem('flosc_js_version');
        if (stored !== FLOSC_JS_VERSION) {
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('flosc_')) keys.push(key);
            }
            keys.forEach(k => { try { localStorage.removeItem(k); } catch(e){} });
            localStorage.setItem('flosc_js_version', FLOSC_JS_VERSION);
            console.log('FLOSC: Storage cleared for version ' + FLOSC_JS_VERSION);
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

        // v07.08: New IVR Engine
        this.ivr = {
            messages: this.config.ivrMessages || {},
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
    
    async init() {
        this.bindElements();
        this.bindEvents();
        this.setupUI();
        this.injectIVRStyles();
        
        if (this.config.stripeKey) {
            this.initStripe();
        }
        
        if (this.state !== 'visitor') {
            if (this.user?.funnelCompleted) {
                await this.loadSessions();
            }
            await this.checkPendingQuizResults();
        }
        
        this.freeLessonDelivered = this.user?.freeLessonDelivered || false;

        if (this.state === 'visitor') {
            this.restoreVisitorMessages();
        }

        this.trackEvent('page_view');

        // v07.08: Build context and start IVR
        this.buildIVRContext();
        this.startIVR();
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
        this.ivr.context = {
            logged_in: this.state !== 'visitor',
            user_id: this.user?.id || 0,
            name: this.user?.name?.split(' ')[0] || 'there',
            score: parseInt(this.user?.lastQuizScore) || 0,
            quiz_taken: !!this.user?.lastQuizScore || !!this.user?.quizCompletedAt,
            purchased: !!this.user?.purchased,
            lesson_viewed: !!this.user?.freeLessonDelivered,
            returning_user: !!localStorage.getItem('flosc_returning'),
            onboarded: !!this.user?.funnelCompleted,
            has_incomplete_lesson: false,
            lessons_completed: parseInt(this.user?.lessonsCompleted) || 0,
            message_count: this.ivr.messageCount,
            inactive_seconds: 0,
            session_seconds: 0,
            session_minutes: 0,
            first_show_session: !localStorage.getItem('flosc_session_' + this.getSessionKey()),
            first_message_after_quiz: false,
            first_message_after_login: false,
            first_message_after_purchase: false,
            first_message_after_free_lesson: false,
            product_name: this.config.product?.name || 'the course',
            price: this.config.product?.price || '$100',
            discount_price: '$25',
            customer_count: '1,247',
            timer_remaining: '60:00'
        };

        // Mark session started
        localStorage.setItem('flosc_session_' + this.getSessionKey(), 'true');
        localStorage.setItem('flosc_returning', 'true');

        // Check for first_message_after events
        if (this.user?.justCompletedQuiz) {
            this.ivr.context.first_message_after_quiz = true;
        }
        if (this.user?.justLoggedIn) {
            this.ivr.context.first_message_after_login = true;
        }
        if (this.user?.justPurchased) {
            this.ivr.context.first_message_after_purchase = true;
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
            }
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
        // Show auto messages for current phase on first load
        this.checkAutoMessages();

        // Start inactivity timer
        this.startInactivityTimer();

        // Show suggested replies
        setTimeout(() => this.showSuggestedReplies(), 500);
    }

    updateIVRContext() {
        const elapsed = (Date.now() - this.ivr.sessionStart) / 1000;
        this.ivr.context.message_count = this.ivr.messageCount;
        this.ivr.context.session_seconds = Math.floor(elapsed);
        this.ivr.context.session_minutes = Math.floor(elapsed / 60);
        this.ivr.context.inactive_seconds = Math.floor((Date.now() - this.ivr.lastInteraction) / 1000);
    }

    evaluateCondition(conditionString) {
        if (!conditionString || conditionString === 'always') return true;
        if (conditionString === 'never') return false;

        this.updateIVRContext();
        const ctx = this.ivr.context;

        try {
            return this.parseCondition(conditionString, ctx);
        } catch (e) {
            console.warn('FLOSC: Condition parse error:', conditionString, e);
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

        // Handle comparisons
        const compMatch = expr.match(/^(\w+)\s*(>=|<=|>|<|==)\s*(\d+)$/);
        if (compMatch) {
            const [, varName, op, value] = compMatch;
            const left = ctx[varName] || 0;
            const right = parseInt(value);
            switch (op) {
                case '>': return left > right;
                case '<': return left < right;
                case '>=': return left >= right;
                case '<=': return left <= right;
                case '==': return left == right;
            }
        }

        // Handle offer states
        if (expr.startsWith('offer_shown_')) {
            const offerId = expr.replace('offer_shown_', '');
            return !!this.ivr.shownThisSession['offer_' + offerId];
        }
        if (expr.startsWith('offer_dismissed_')) {
            return false;
        }
        if (expr.startsWith('offer_purchased_')) {
            return ctx.purchased;
        }

        // Handle boolean variables
        if (ctx.hasOwnProperty(expr)) {
            return !!ctx[expr];
        }

        return false;
    }

    checkAutoMessages() {
        const messages = Object.values(this.ivr.messages);
        const autoMessages = messages.filter(m => m.type === 'auto');

        for (const msg of autoMessages) {
            if (this.ivr.shownThisSession[msg.name]) continue;

            // Check phase match (allow cross-phase for logged_in conditions)
            if (msg.phase && msg.phase !== this.ivr.phase) {
                if (!(msg.phase === 'login' && this.ivr.context.logged_in && !this.ivr.context.purchased)) {
                    continue;
                }
            }

            if (this.evaluateCondition(msg.conditions)) {
                this.showIVRMessage(msg);
                this.ivr.shownThisSession[msg.name] = true;

                if (msg.conditions && msg.conditions.includes('first_show_session')) {
                    break;
                }
            }
        }
    }

    showSuggestedReplies() {
        const messages = Object.values(this.ivr.messages);
        const suggestedReplies = messages.filter(m => m.type === 'suggested_reply');

        const applicable = [];
        for (const msg of suggestedReplies) {
            if (this.evaluateCondition(msg.conditions)) {
                applicable.push(msg);
            }
        }

        this.renderSuggestedReplies(applicable);
    }

    renderSuggestedReplies(replies) {
        const existing = document.getElementById('flosc-suggested-replies');
        if (existing) existing.remove();

        if (replies.length === 0) return;

        const container = document.createElement('div');
        container.id = 'flosc-suggested-replies';
        container.className = 'flosc-suggested-replies';
        container.innerHTML = `
            <div class="flosc-replies-scroll">
                ${replies.map(r => `
                    <button class="flosc-style-${r.style || 'pill'}" data-message="${this.escapeHtml(r.name)}">
                        ${r.icon ? `<span class="flosc-reply-icon">${r.icon}</span>` : ''}
                        <span class="flosc-reply-text">${this.escapeHtml(r.user_input)}</span>
                    </button>
                `).join('')}
            </div>
        `;

        container.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const msgName = btn.dataset.message;
                this.handleSuggestedReply(msgName);
            });
        });

        const inputArea = document.querySelector('.flosc-input-area') || document.getElementById('chatInput')?.parentElement;
        if (inputArea) {
            inputArea.parentElement.insertBefore(container, inputArea);
        }
    }

    handleSuggestedReply(messageName) {
        const msg = this.ivr.messages[messageName];
        if (!msg) return;

        this.addMessage('user', msg.user_input);
        this.ivr.messageCount++;
        this.ivr.lastInteraction = Date.now();
        this.ivr.context.first_show_session = false;

        setTimeout(() => {
            this.showIVRMessage(msg);

            if (msg.action) {
                this.performIVRAction(msg.action);
            }

            this.showSuggestedReplies();
        }, 300);
    }

    showIVRMessage(msg) {
        let content = this.replaceVariables(msg.content);

        if (msg.type === 'offer') {
            this.showOfferMessage(msg);
            return;
        }

        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/~~([^~]+)~~/g, '<del>$1</del>');

        this.addMessage('assistant', content);

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

    startInactivityTimer() {
        if (this.ivr.inactivityTimer) {
            clearInterval(this.ivr.inactivityTimer);
        }

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
            const container = document.getElementById('flosc-suggested-replies');
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
        this.sidebar = document.getElementById('floscSidebar');
        this.sidebarToggle = document.getElementById('sidebarToggle');
        this.sessionList = document.getElementById('sessionList');
        this.newSessionBtn = document.getElementById('newSessionBtn');
        this.chatMessages = document.getElementById('chatMessages');
        this.chatInput = document.getElementById('chatInput');
        this.sendBtn = document.getElementById('sendBtn');
        this.voiceBtn = document.getElementById('voiceBtn');
        this.quizSection = document.getElementById('quizSection');
        this.shareBtn = document.getElementById('shareBtn');
        this.shareModal = document.getElementById('shareModal');
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
    
    setupUI() {
        const sidebarOpen = localStorage.getItem('flosc_sidebar_open') === 'true';
        if (sidebarOpen && this.sidebar) {
            this.sidebar.classList.add('open');
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
        
        this.chatInput.value = '';
        this.chatInput.style.height = 'auto';

        if (this.onUserMessage(message)) {
            return;
        }
        
        this.addMessage('user', message);
        
        if (this.state === 'visitor') {
            this.saveVisitorMessage('user', message);
        }
        
        this.showTyping();
        
        try {
            const response = await this.callAPI(message);
            this.hideTyping();
            
            if (response.success) {
                this.addMessage('assistant', response.message);
                
                if (this.state === 'visitor') {
                    this.saveVisitorMessage('assistant', response.message);
                }
                
                if (response.phaseChange) {
                    this.ivr.phase = response.phaseChange;
                    this.checkAutoMessages();
                }
            } else {
                this.addMessage('assistant', 'Sorry, I encountered an error. Please try again.');
            }
        } catch (error) {
            this.hideTyping();
            console.error('FLOSC API Error:', error);
            this.addMessage('assistant', 'Sorry, something went wrong. Please try again.');
        }
    }
    
    addMessage(role, content, isHtml = false) {
        if (!this.chatMessages) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `flosc-message flosc-message-${role}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'flosc-message-bubble';
        
        if (isHtml || content.includes('<strong>') || content.includes('<del>') || content.includes('<div')) {
            bubble.innerHTML = content;
        } else {
            bubble.textContent = content;
        }
        
        messageDiv.appendChild(bubble);
        this.chatMessages.appendChild(messageDiv);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
    
    showTyping() {
        if (!this.chatMessages) return;
        
        const typing = document.createElement('div');
        typing.className = 'flosc-message flosc-message-assistant flosc-typing';
        typing.id = 'typingIndicator';
        typing.innerHTML = `
            <div class="flosc-message-bubble">
                <span class="flosc-typing-dot"></span>
                <span class="flosc-typing-dot"></span>
                <span class="flosc-typing-dot"></span>
            </div>
        `;
        this.chatMessages.appendChild(typing);
        this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }
    
    hideTyping() {
        const typing = document.getElementById('typingIndicator');
        if (typing) typing.remove();
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
                context: {
                    phase: this.ivr.phase,
                    message_count: this.ivr.messageCount
                }
            })
        });
        
        return response.json();
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
        const modal = document.getElementById('recordingModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }
    
    hideRecordingModal() {
        const modal = document.getElementById('recordingModal');
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
        const modal = document.getElementById('paymentModal');
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
