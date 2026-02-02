/**
 * =============================================================================
 * FLOSC FRAMEWORK - JavaScript State Machine v2.0.4
 * =============================================================================
 * 
 * Controls the entire chat-based funnel flow:
 * 
 *   INIT → FREELINE → LOGIN_GATE → RESULTS → OFFER → SALE → CONTENT
 * 
 * =============================================================================
 * v2.0.4 NEW FEATURES
 * =============================================================================
 * 
 * 1. TEXT QUIZ MODE
 *    - User types their answer (e.g., "1 2 3")
 *    - Compares to expected items (1-10)
 *    - Returns which items correct vs missed
 * 
 * 2. ITEM-LEVEL RESULTS
 *    - Shows exactly which items were correct
 *    - Shows which items need practice
 *    - Free lesson selected from missed items
 * 
 * 3. IN-CHAT STRIPE CHECKOUT
 *    - Creates checkout session via REST API
 *    - Redirects to Stripe's hosted checkout
 *    - Returns to chat after payment
 * 
 * 4. SESSION PERSISTENCE
 *    - Survives page refresh
 *    - Survives login/logout
 *    - Backed up to server
 * 
 * =============================================================================
 * ARCHITECTURE NOTE
 * =============================================================================
 * 
 * The 1-10 quiz maps to SAE phonemes:
 * 
 *   Now:    1,  2,  3,  4,  5,  6,  7,  8,  9,  10
 *   Later: /æ/, /ɛ/, /ɪ/, /ɑ/, /ʌ/, /ʊ/, /u/, /oʊ/, /aɪ/, /aʊ/
 * 
 * Framework doesn't care what items ARE - just tracks correct vs missed.
 * 
 * =============================================================================
 */

class FLOSC {
    
    /**
     * Constructor
     * Initialize state machine and restore session
     */
    constructor() {
        // Configuration from WordPress (via wp_localize_script)
        this.config = window.FLOSC_CONFIG || {};
        
        // Current state in the funnel
        this.state = 'INIT';
        
        // Session data (persisted to localStorage + server)
        this.data = {
            sessionId: this.getSessionId(),
            quizAnswer: null,
            quizResults: null,
            correctItems: [],
            missedItems: [],
            score: null,
            total: null,
            freeLesson: null,
            offerExpiresAt: null,
            noCount: 0,
        };
        
        // Recording state (for audio quiz mode)
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        
        // Countdown timer
        this.countdownInterval = null;
        
        // DOM elements cache
        this.elements = {};
        
        // Initialize
        this.init();
    }
    
    
    /* =========================================================================
     * SECTION: INITIALIZATION
     * =========================================================================
     */
    
    async init() {
        console.log('FLOSC: Initializing v2.0.4');
        
        // Cache DOM elements
        this.cacheElements();
        
        // Restore session from localStorage/server
        await this.restoreSession();
        
        // Handle referral tracking
        if (this.config.enableReferralTracking && this.config.referralCode) {
            this.handleReferral();
        }
        
        // Check for cooldown
        if (this.config.enableCooldown && this.config.isCooledDown) {
            this.showCooldownMessage();
            return;
        }
        
        // Check for payment return
        if (this.checkPaymentReturn()) {
            return;
        }
        
        // Determine starting state
        this.determineStartingState();
    }
    
    
    /**
     * Cache DOM elements for performance
     */
    cacheElements() {
        this.elements = {
            messages:     document.getElementById('floscMessages'),
            typing:       document.getElementById('floscTyping'),
            replies:      document.getElementById('floscReplies'),
            textInput:    document.getElementById('floscTextInput'),
            quizDisplay:  document.getElementById('floscQuizDisplay'),
            quizInput:    document.getElementById('floscQuizInput'),
            submitBtn:    document.getElementById('floscSubmitBtn'),
            recorder:     document.getElementById('floscRecorder'),
            quizText:     document.getElementById('floscQuizText'),
            recordBtn:    document.getElementById('floscRecordBtn'),
            waveform:     document.getElementById('floscWaveform'),
            recordStatus: document.getElementById('floscRecordStatus'),
            statusText:   document.getElementById('floscStatusText'),
            socialLogin:  document.getElementById('floscSocialLogin'),
            countdown:    document.getElementById('floscCountdown'),
            countdownTime: document.getElementById('floscCountdownTime'),
        };
    }
    
    
    /**
     * Determine which state to start in
     */
    determineStartingState() {
        if (this.config.hasPaidAccess) {
            // User has paid - show content
            this.transition('CONTENT');
        } else if (this.data.score !== null && this.config.isLoggedIn) {
            // Has quiz results and logged in - show results
            this.transition('RESULTS');
        } else if (this.data.score !== null && !this.config.isLoggedIn) {
            // Has quiz results but not logged in - login gate
            this.transition('LOGIN_GATE');
        } else {
            // Fresh start
            this.transition('FREELINE');
        }
    }
    
    
    /**
     * Check if returning from payment
     */
    checkPaymentReturn() {
        const params = new URLSearchParams(window.location.search);
        
        if (params.get('flosc_payment') === 'success') {
            // Clear URL params
            window.history.replaceState({}, '', window.location.pathname);
            
            // Refresh status and transition to content
            this.refreshStatus().then(() => {
                if (this.config.hasPaidAccess) {
                    this.transition('CONTENT');
                } else {
                    // Payment may still be processing
                    this.botMessage('Processing your payment... Please wait a moment.');
                    setTimeout(() => this.refreshStatus().then(() => this.transition('CONTENT')), 3000);
                }
            });
            
            return true;
        }
        
        if (params.get('flosc_payment') === 'cancelled') {
            window.history.replaceState({}, '', window.location.pathname);
            this.transition('OFFER');
            return true;
        }
        
        return false;
    }
    
    
    /* =========================================================================
     * SECTION: STATE MACHINE
     * =========================================================================
     * 
     * Core state transitions. Each state has a corresponding run method.
     */
    
    async transition(newState) {
        console.log(`FLOSC: ${this.state} → ${newState}`);
        this.state = newState;
        this.updateStatusDisplay(newState);
        this.saveSession();
        
        try {
            switch (newState) {
                case 'FREELINE':
                    await this.runFreeline();
                    break;
                case 'LOGIN_GATE':
                    await this.runLoginGate();
                    break;
                case 'RESULTS':
                    await this.runResults();
                    break;
                case 'OFFER':
                    await this.runOffer();
                    break;
                case 'SALE':
                    await this.runSale();
                    break;
                case 'CONTENT':
                    await this.runContent();
                    break;
            }
        } catch (error) {
            console.error('FLOSC State Error:', error);
            this.showError('Something went wrong. Please refresh the page.');
        }
    }
    
    
    /* =========================================================================
     * SECTION: F - FREELINE (Quiz/Hook Stage)
     * =========================================================================
     * 
     * The initial interaction. Hooks user with a simple quiz.
     */
    
    async runFreeline() {
        // Show referral greeting if applicable
        if (this.config.referralCode && this.config.referralGreeting) {
            const greeting = this.config.referralGreeting.replace('{ref}', this.config.referralCode);
            await this.botMessage(greeting);
        }
        
        // Welcome message (AI optional)
        const aiWelcome = await this.getAIResponse(
            `Write a short, warm welcome (1-2 sentences) for a chatbot funnel. Product: ${this.config.productName}. Keep it direct.`,
            'FREELINE',
            { referralCode: this.config.referralCode || null }
        );
        await this.botMessage(aiWelcome || this.config.welcomeMessage);
        
        // Show start options
        this.showReplies([
            { text: "Yes, let's go! ✨", action: () => this.startQuiz() },
            { text: "Tell me more", action: () => this.explainQuiz() },
        ]);
    }
    
    
    async explainQuiz() {
        await this.botMessage("It's super quick! Just a simple test to see where you're at.");
        await this.botMessage("Takes about 30 seconds. Ready?");
        
        this.showReplies([
            { text: "I'm ready! 🎯", action: () => this.startQuiz() },
        ]);
    }
    
    
    async startQuiz() {
        await this.botMessage(this.config.quizPrompt);
        
        if (this.config.quizType === 'text') {
            this.showTextInput();
        } else {
            this.showRecorder();
        }
    }
    
    
    /* =========================================================================
     * SECTION: TEXT QUIZ INPUT (v2.0.4)
     * =========================================================================
     * 
     * Handles text-based quiz for testing the funnel.
     * User types their answer, we compare to expected items.
     */
    
    showTextInput() {
        this.clearReplies();
        
        // Show quiz display (what they should type)
        this.elements.quizDisplay.textContent = this.config.quizDisplayText;
        this.elements.quizInput.value = '';
        this.elements.quizInput.placeholder = 'Type what you know...';
        
        // Show input area
        this.elements.textInput.classList.remove('hidden');
        this.elements.textInput.classList.add('visible');
        
        // Focus input
        this.elements.quizInput.focus();
        
        // Set up submit handler
        this.elements.submitBtn.onclick = () => this.submitTextQuiz();
        this.elements.quizInput.onkeypress = (e) => {
            if (e.key === 'Enter') this.submitTextQuiz();
        };
    }
    
    
    hideTextInput() {
        this.elements.textInput.classList.remove('visible');
        this.elements.textInput.classList.add('hidden');
    }
    
    
    async submitTextQuiz() {
        const answer = this.elements.quizInput.value.trim();

        // Manual AI test (does NOT progress the funnel): type "/ai hello"
        if (answer.toLowerCase().startsWith('/ai ')) {
            const prompt = answer.slice(4).trim();
            if (!prompt) {
                this.showError('Try: /ai write one sentence ...');
                return;
            }

            // Keep input visible; just run a single AI round-trip
            this.elements.quizInput.value = '';
            this.userMessage(answer);
            this.showLoading('Asking AI...');
            const reply = await this.getAIResponse(prompt, 'MANUAL_TEST', {});
            this.hideLoading();
            await this.botMessage(reply || 'AI mode is OFF (or not configured). Enable it in FLOSC → Settings.');
            return;
        }
        
        if (!answer) {
            this.showError('Please type your answer first!');
            return;
        }
        
        this.hideTextInput();
        
        // Show user's answer as a message
        this.userMessage(answer);
        
        this.showLoading('Checking your answer...');
        
        try {
            const response = await fetch(this.config.restUrl + '/process-quiz', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({ answer }),
            });
            
            if (!response.ok) throw new Error('Quiz processing failed');
            
            const result = await response.json();
            this.hideLoading();
            
            // Store results
            this.data.quizAnswer = answer;
            this.data.quizResults = result;
            this.data.score = result.score;
            this.data.total = result.total;
            this.data.correctItems = result.correct_items || [];
            this.data.missedItems = result.missed_items || [];
            this.saveSession();
            
            const aiAck = await this.getAIResponse(
                `User submitted a quiz answer. Reply with a short confirmation (1 sentence), no emojis.`,
                'ACK',
                { answerLength: answer.length }
            );
            await this.botMessage(aiAck || "Got it! I've analyzed your answer.");
            
            // Transition based on login state
            if (this.config.isLoggedIn) {
                // Save results to user account
                await this.saveResultsToServer();
                this.transition('RESULTS');
            } else {
                this.transition('LOGIN_GATE');
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Quiz error:', error);
            this.showError('Could not process your answer. Please try again.');
            
            this.showReplies([
                { text: 'Try Again', action: () => this.startQuiz() },
            ]);
        }
    }
    
    
    /* =========================================================================
     * SECTION: AUDIO RECORDING (for future audio quiz mode)
     * =========================================================================
     */
    
    showRecorder() {
        this.clearReplies();
        this.elements.quizText.textContent = this.config.quizDisplayText;
        this.elements.recorder.classList.remove('hidden');
        this.elements.recorder.classList.add('visible');
        this.elements.recordStatus.textContent = 'Click the microphone when ready';
        this.setupRecorder();
    }
    
    hideRecorder() {
        this.elements.recorder.classList.remove('visible');
        this.elements.recorder.classList.add('hidden');
    }
    
    setupRecorder() {
        this.elements.recordBtn.onclick = async () => {
            if (!this.isRecording) {
                await this.startRecording();
            } else {
                this.stopRecording();
            }
        };
    }
    
    async startRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = e => this.audioChunks.push(e.data);
            this.mediaRecorder.onstop = () => {
                stream.getTracks().forEach(track => track.stop());
                this.processAudioQuiz();
            };
            
            this.mediaRecorder.start();
            this.isRecording = true;
            
            this.elements.recordBtn.classList.add('recording');
            this.elements.recordBtn.querySelector('.flosc-record-icon').textContent = '⏹️';
            this.elements.recordBtn.querySelector('.flosc-record-label').textContent = 'Stop';
            this.elements.waveform.classList.add('active');
            this.elements.recordStatus.textContent = 'Recording... Click to stop';
            
        } catch (error) {
            console.error('Microphone error:', error);
            this.showError('Could not access microphone. Please check permissions.');
        }
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            this.elements.recordBtn.classList.remove('recording');
            this.elements.recordBtn.querySelector('.flosc-record-icon').textContent = '🎤';
            this.elements.recordBtn.querySelector('.flosc-record-label').textContent = 'Record';
            this.elements.waveform.classList.remove('active');
        }
    }
    
    async processAudioQuiz() {
        this.hideRecorder();
        this.showLoading('Processing your recording...');
        
        try {
            const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');
            
            const response = await fetch(this.config.restUrl + '/process-quiz', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });
            
            if (!response.ok) throw new Error('Processing failed');
            
            const result = await response.json();
            this.hideLoading();
            
            // Store results (same as text quiz)
            this.data.quizResults = result;
            this.data.score = result.score;
            this.data.total = result.total;
            this.data.correctItems = result.correct_items || [];
            this.data.missedItems = result.missed_items || [];
            this.saveSession();
            
            await this.botMessage("Great! I've analyzed your recording.");
            
            if (this.config.isLoggedIn) {
                await this.saveResultsToServer();
                this.transition('RESULTS');
            } else {
                this.transition('LOGIN_GATE');
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Audio processing error:', error);
            this.showError('Could not process your recording. Please try again.');
            
            this.showReplies([
                { text: 'Try Again', action: () => this.startQuiz() },
            ]);
        }
    }
    
    
    /* =========================================================================
     * SECTION: L - LOGIN GATE
     * =========================================================================
     * 
     * User must log in to see their results.
     * Uses BuddyBoss social login buttons.
     */
    
    async runLoginGate() {
        await this.botMessage(this.config.loginPrompt);
        
        // Try to load social login buttons
        if (this.config.enableSocialLogin) {
            await this.showSocialLogin();
        }
        
        // Show email login fallback
        this.showReplies([
            { text: '📧 Login with Email', action: () => this.redirectToLogin() },
        ]);
    }
    
    
    async showSocialLogin() {
        try {
            const response = await fetch(this.config.restUrl + '/social-login');
            const result = await response.json();
            
            if (result.available && result.html) {
                this.elements.socialLogin.innerHTML = result.html;
                this.elements.socialLogin.classList.remove('hidden');
            }
        } catch (error) {
            console.error('Social login error:', error);
        }
    }
    
    
    redirectToLogin() {
        // Save session before redirect
        this.saveSession();
        this.saveSessionToServer();
        
        // Redirect to WordPress login
        window.location.href = this.config.loginUrl;
    }
    
    
    /* =========================================================================
     * SECTION: O - OFFER (Results + Free Content)
     * =========================================================================
     * 
     * Show quiz results with item-level detail.
     * Give ONE free lesson from their missed items.
     */
    
    async runResults() {
        const score = this.data.score;
        const total = this.data.total;
        const correctItems = this.data.correctItems || [];
        const missedItems = this.data.missedItems || [];
        
        // Show score
        const resultsMsg = this.config.resultsMessage
            .replace('{score}', score)
            .replace('{total}', total);
        await this.botMessage(resultsMsg);
        
        // Show correct items
        if (correctItems.length > 0) {
            const correctMsg = this.config.itemsCorrectMessage
                .replace('{correct_items}', correctItems.join(', '));
            await this.botMessage(correctMsg);
        }
        
        // Show missed items
        if (missedItems.length > 0) {
            const missedMsg = this.config.itemsMissedMessage
                .replace('{missed_items}', missedItems.join(', '));
            await this.botMessage(missedMsg);
        }
        
        // Load and show free lesson
        await this.showFreeLesson();
        
        // Transition to offer
        setTimeout(() => this.transition('OFFER'), 2000);
    }
    
    
    async showFreeLesson() {
        await this.botMessage(this.config.freeContentMessage);
        this.showLoading('Loading your free lesson...');
        
        try {
            const response = await fetch(this.config.restUrl + '/free-content', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    missed_items: this.data.missedItems,
                }),
            });
            
            const lesson = await response.json();
            this.hideLoading();
            
            if (lesson.content) {
                this.data.freeLesson = lesson.id;
                this.saveSession();
                this.renderLesson(lesson);
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Free lesson error:', error);
        }
    }
    
    
    /* =========================================================================
     * SECTION: S - SALE (Offer + Payment)
     * =========================================================================
     * 
     * Present paid offer with countdown timer.
     * Handle Stripe checkout.
     */
    
    async runOffer() {
        await this.botMessage(this.config.offerHeadline);
        await this.botMessage(this.config.offerDescription);
        
        const price = `${this.config.offerCurrencySymbol || this.config.offerCurrency}${this.config.offerPrice}`;
        await this.botMessage(`💰 Price: ${price}`);
        
        // Start countdown if enabled
        if (this.config.enableOfferExpiry) {
            this.startOfferCountdown();
        }
        
        // Show buy/decline buttons
        this.showReplies([
            { 
                text: `🛒 Buy Now (${price})`, 
                action: () => this.initiatePurchase(),
                id: 'flosc-buy-btn',
            },
            { 
                text: 'Maybe later', 
                action: () => this.handleDecline(),
            },
        ]);
    }
    
    
    async initiatePurchase() {
        this.showLoading('Preparing checkout...');
        
        try {
            const response = await fetch(this.config.restUrl + '/create-checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
            });
            
            const result = await response.json();
            
            if (result.checkout_url) {
                // Save session before redirect
                this.saveSession();
                await this.saveSessionToServer();
                
                // Redirect to Stripe
                window.location.href = result.checkout_url;
            } else {
                throw new Error(result.message || 'Could not create checkout');
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Checkout error:', error);
            this.showError('Could not start checkout. Please try again.');
        }
    }
    
    
    async handleDecline() {
        await this.botMessage('No problem! Take your time.');
        
        // Track "no" for cooldown
        if (this.config.enableCooldown) {
            await this.trackNoResponse();
        }
    }
    
    
    async runSale() {
        // This state is mostly handled by redirect
        // Could show manual checkout link as fallback
        if (this.config.manualCheckoutUrl) {
            window.location.href = this.config.manualCheckoutUrl;
        }
    }
    
    
    /* =========================================================================
     * SECTION: C - CONTENT (Post-Purchase)
     * =========================================================================
     * 
     * Show all lessons the user needs (based on their quiz results).
     */
    
    async runContent() {
        await this.botMessage(`Welcome back, ${this.config.userName || 'there'}! 🎉`);
        await this.botMessage('You now have full access. Here are your lessons:');
        
        this.showLoading('Loading your lessons...');
        
        try {
            const response = await fetch(this.config.restUrl + '/content', {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });
            
            const result = await response.json();
            this.hideLoading();
            
            if (result.items && result.items.length > 0) {
                for (const item of result.items) {
                    this.renderLesson(item);
                }
            } else {
                await this.botMessage(result.message || 'Your lessons are being prepared. Check back soon!');
            }
            
        } catch (error) {
            this.hideLoading();
            console.error('Content error:', error);
            this.showError('Could not load lessons. Please refresh the page.');
        }
    }
    
    
    renderLesson(lesson) {
        const div = document.createElement('div');
        div.className = 'flosc-message bot flosc-lesson-message';
        div.innerHTML = `
            <div class="flosc-message-content">
                ${lesson.content || `
                    <div class="flosc-lesson">
                        <h3 class="flosc-lesson-title">${this.escapeHtml(lesson.title)}</h3>
                        <p class="flosc-lesson-excerpt">${this.escapeHtml(lesson.excerpt || '')}</p>
                    </div>
                `}
            </div>
        `;
        this.elements.messages.appendChild(div);
        this.scrollToBottom();
    }
    
    
    /* =========================================================================
     * SECTION: COUNTDOWN TIMER
     * =========================================================================
     */
    
    startOfferCountdown() {
        // Set expiry time
        if (!this.data.offerExpiresAt) {
            this.data.offerExpiresAt = Date.now() + (this.config.offerExpiryMinutes * 60 * 1000);
            this.saveSession();
        }
        
        // Show countdown element
        this.elements.countdown.classList.remove('hidden');
        
        // Clear any existing interval
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        // Update every second
        this.countdownInterval = setInterval(() => {
            const remaining = this.data.offerExpiresAt - Date.now();
            
            if (remaining <= 0) {
                this.handleOfferExpired();
            } else {
                const minutes = Math.floor(remaining / 60000);
                const seconds = Math.floor((remaining % 60000) / 1000);
                this.elements.countdownTime.textContent = 
                    `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        }, 1000);
    }
    
    
    async handleOfferExpired() {
        clearInterval(this.countdownInterval);
        this.countdownInterval = null;
        
        // Disable buy button
        const buyBtn = document.getElementById('flosc-buy-btn');
        if (buyBtn) {
            buyBtn.disabled = true;
            buyBtn.style.opacity = '0.5';
        }
        
        await this.botMessage(this.config.offerExpiredMessage);
        
        this.showReplies([
            { text: '🔄 Start Over', action: () => location.reload() },
        ]);
    }
    
    
    /* =========================================================================
     * SECTION: COOLDOWN & REFERRAL
     * =========================================================================
     */
    
    async trackNoResponse() {
        try {
            const response = await fetch(this.config.restUrl + '/track-no', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });
            
            const result = await response.json();
            
            if (result.cooldown_applied) {
                await this.botMessage("⏰ You've declined a few times. Come back later!");
                this.clearReplies();
            }
        } catch (error) {
            console.error('Track no error:', error);
        }
    }
    
    
    async showCooldownMessage() {
        const endsAt = this.config.cooldownEndsAt;
        const hoursLeft = Math.ceil((endsAt - Date.now() / 1000) / 3600);
        
        const message = this.config.cooldownMessage.replace('{hours}', hoursLeft);
        await this.botMessage(message);
    }
    
    
    handleReferral() {
        if (this.config.isLoggedIn) {
            // Save referral to user account
            fetch(this.config.restUrl + '/save-referral', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    referral_code: this.config.referralCode,
                }),
            }).catch(console.error);
        }
    }


    /* =========================================================================
     * SECTION: AI MODE (v2.0.5)
     * =========================================================================
     *
     * When enabled, the frontend asks WordPress for AI-generated copy.
     * WordPress then proxies to your configured AI backend URL.
     *
     * - AI OFF: returns empty string (the caller should fallback to scripted text).
     * - AI ON : returns a short message string (or empty on error).
     */
    async getAIResponse(prompt, state = 'GEN', context = {}) {
        if (!this.config.enableAIMode) return '';
        if (!this.config.aiBackendConfigured) return '';

        try {
            const resp = await fetch(this.config.restUrl + '/ai', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    prompt,
                    state,
                    context,
                    session_id: this.config.sessionId || null,
                })
            });

            if (!resp.ok) return '';
            const data = await resp.json();
            return (data && data.message) ? String(data.message) : '';
        } catch (e) {
            console.warn('FLOSC AI error:', e);
            return '';
        }
    }
    
    
    /* =========================================================================
     * SECTION: UI HELPERS
     * =========================================================================
     */
    
    async botMessage(text, delay = 600) {
        this.showTyping();
        await this.sleep(delay);
        this.hideTyping();
        
        const div = document.createElement('div');
        div.className = 'flosc-message flosc-message-bot';
        div.innerHTML = `
            <div class="flosc-message-avatar">${this.config.botAvatar}</div>
            <div class="flosc-message-content">${this.escapeHtml(text)}</div>
        `;
        this.elements.messages.appendChild(div);
        this.scrollToBottom();
    }
    
    
    userMessage(text) {
        const div = document.createElement('div');
        div.className = 'flosc-message flosc-message-user';
        div.innerHTML = `
            <div class="flosc-message-content">${this.escapeHtml(text)}</div>
        `;
        this.elements.messages.appendChild(div);
        this.scrollToBottom();
    }
    
    
    showReplies(buttons) {
        this.clearReplies();
        buttons.forEach(btn => {
            const el = document.createElement('button');
            el.className = 'flosc-reply-btn';
            el.textContent = btn.text;
            el.onclick = btn.action;
            if (btn.id) el.id = btn.id;
            this.elements.replies.appendChild(el);
        });
    }
    
    
    clearReplies() {
        this.elements.replies.innerHTML = '';
    }
    
    
    showTyping() {
        this.elements.typing.classList.remove('hidden');
        this.scrollToBottom();
    }
    
    
    hideTyping() {
        this.elements.typing.classList.add('hidden');
    }
    
    
    showLoading(text) {
        this.elements.statusText.textContent = text;
        this.showTyping();
    }
    
    
    hideLoading() {
        this.hideTyping();
        this.elements.statusText.textContent = 'Online';
    }
    
    
    async showError(message) {
        await this.botMessage(`⚠️ ${message}`);
    }
    
    
    updateStatusDisplay(state) {
        const map = {
            'FREELINE': 'Ready',
            'LOGIN_GATE': 'Login required',
            'RESULTS': 'Your results',
            'OFFER': 'Special offer',
            'SALE': 'Checkout',
            'CONTENT': 'Full access ✓',
        };
        this.elements.statusText.textContent = map[state] || 'Online';
    }
    
    
    scrollToBottom() {
        this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    }
    
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    
    /* =========================================================================
     * SECTION: SESSION PERSISTENCE
     * =========================================================================
     */
    
    getSessionId() {
        let id = localStorage.getItem('flosc_session_id');
        if (!id) {
            id = 'flosc_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('flosc_session_id', id);
            // Also set cookie for server-side access
            document.cookie = `flosc_session_id=${id}; path=/; max-age=${60*60*24*30}`;
        }
        return id;
    }
    
    
    saveSession() {
        const data = {
            state: this.state,
            quizAnswer: this.data.quizAnswer,
            quizResults: this.data.quizResults,
            score: this.data.score,
            total: this.data.total,
            correctItems: this.data.correctItems,
            missedItems: this.data.missedItems,
            freeLesson: this.data.freeLesson,
            offerExpiresAt: this.data.offerExpiresAt,
            noCount: this.data.noCount,
        };
        localStorage.setItem('flosc_session', JSON.stringify(data));
    }
    
    
    async restoreSession() {
        // Try localStorage first
        const stored = localStorage.getItem('flosc_session');
        if (stored) {
            try {
                const data = JSON.parse(stored);
                Object.assign(this.data, data);
                this.state = data.state || 'INIT';
                console.log('FLOSC: Restored session from localStorage');
            } catch (e) {
                console.error('Session parse error:', e);
            }
        }
        
        // Also check server for logged-in users
        if (this.config.isLoggedIn) {
            try {
                const response = await fetch(
                    this.config.restUrl + '/session/get?session_id=' + this.data.sessionId
                );
                const result = await response.json();
                
                if (result.found && result.data) {
                    Object.assign(this.data, result.data);
                    console.log('FLOSC: Restored session from server');
                }
            } catch (e) {
                console.error('Server session error:', e);
            }
        }
    }
    
    
    async saveSessionToServer() {
        try {
            await fetch(this.config.restUrl + '/session/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    session_id: this.data.sessionId,
                    ...this.data,
                }),
            });
        } catch (e) {
            console.error('Session save error:', e);
        }
    }
    
    
    async saveResultsToServer() {
        if (!this.config.isLoggedIn) return;
        
        try {
            await fetch(this.config.restUrl + '/save-results', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    score: this.data.score,
                    total: this.data.total,
                    correct_items: this.data.correctItems,
                    missed_items: this.data.missedItems,
                }),
            });
        } catch (e) {
            console.error('Results save error:', e);
        }
    }
    
    
    async refreshStatus() {
        try {
            const response = await fetch(this.config.restUrl + '/status');
            const status = await response.json();
            
            this.config.isLoggedIn = status.logged_in;
            this.config.hasPaidAccess = status.has_paid_access;
            this.config.userName = status.display_name;
            
        } catch (e) {
            console.error('Status refresh error:', e);
        }
    }
}


/* =============================================================================
 * INITIALIZE ON DOM READY
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('flosc-container')) {
        window.flosc = new FLOSC();
    }
});
