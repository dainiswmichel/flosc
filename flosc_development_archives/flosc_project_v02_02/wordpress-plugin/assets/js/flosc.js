/**
 * FLOSC Framework - JavaScript State Machine v2.0.2
 *
 * State Flow: INIT → FREELINE → LOGIN_GATE → RESULTS → OFFER → SALE → CONTENT
 *
 * Key Features:
 * - Pluggable backend (mock/FastAPI/OpenAI/Claude/custom)
 * - Session persistence across page refresh
 * - Error handling with user-friendly messages
 * - Loading states for all async operations
 * - All content rendered in-chat (no external links)
 * - BuddyBoss social login integration
 *
 * Testing Tonight:
 * - Switch backends via WP Admin
 * - Test purchase flow end-to-end
 * - Verify session persists on refresh
 */

class FLOSC {
    constructor() {
        // Configuration from WordPress
        this.config = window.FLOSC_CONFIG;

        // Current state
        this.state = 'INIT';

        // Session data
        this.data = {
            quizScore: null,
            quizDetails: null,
            audioBlob: null,
            sessionId: this.getSessionId(),
        };

        // Recording state
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;

        // DOM elements
        this.elements = {};

        this.init();
    }

    // ========================================================================
    // INITIALIZATION
    // ========================================================================

    async init() {
        // Cache DOM elements
        this.elements = {
            messages: document.getElementById('floscMessages'),
            typing: document.getElementById('floscTyping'),
            replies: document.getElementById('floscReplies'),
            recorder: document.getElementById('floscRecorder'),
            quizText: document.getElementById('floscQuizText'),
            recordBtn: document.getElementById('floscRecordBtn'),
            waveform: document.getElementById('floscWaveform'),
            recordStatus: document.getElementById('floscRecordStatus'),
            statusText: document.getElementById('floscStatusText'),
            socialLogin: document.getElementById('floscSocialLogin'),
            contentArea: document.getElementById('floscContentArea'),
        };

        // Restore session if exists
        await this.restoreSession();

        // Determine starting state
        if (this.config.hasPaidAccess) {
            this.transition('CONTENT');
        } else if (this.data.quizScore !== null && this.config.isLoggedIn) {
            this.transition('RESULTS');
        } else if (this.data.quizScore !== null && !this.config.isLoggedIn) {
            this.transition('LOGIN_GATE');
        } else {
            this.transition('FREELINE');
        }
    }

    // ========================================================================
    // STATE MACHINE
    // ========================================================================

    async transition(newState) {
        console.log(`FLOSC: ${this.state} → ${newState}`);
        this.state = newState;
        this.updateStatus(newState);
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
            this.showError('Something went wrong. Please refresh and try again.');
            console.error('FLOSC State Error:', error);
        }
    }

    // ========================================================================
    // F - FREELINE (Quiz Hook)
    // ========================================================================

    async runFreeline() {
        await this.botMessage(this.config.welcomeMessage);

        this.showReplies([
            { text: "Yes, let's go! 🎤", action: () => this.startQuiz() },
            { text: "Tell me more", action: () => this.explainMore() },
        ]);
    }

    async explainMore() {
        await this.botMessage("It's quick and easy! Just read a simple sentence aloud, and I'll analyze your pronunciation.");
        await this.botMessage("Takes less than a minute. Ready?");

        this.showReplies([
            { text: "I'm ready! 🎤", action: () => this.startQuiz() },
        ]);
    }

    async startQuiz() {
        await this.botMessage(this.config.quizPrompt);
        this.showRecorder();
    }

    // ========================================================================
    // AUDIO RECORDING
    // ========================================================================

    showRecorder() {
        this.clearReplies();
        this.elements.quizText.textContent = this.config.quizText;
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
        const btn = this.elements.recordBtn;

        btn.onclick = async () => {
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
                this.processQuiz();
            };

            this.mediaRecorder.start();
            this.isRecording = true;

            this.elements.recordBtn.classList.add('recording');
            this.elements.recordBtn.querySelector('.flosc-record-icon').textContent = '⏹️';
            this.elements.recordBtn.querySelector('.flosc-record-label').textContent = 'Stop Recording';
            this.elements.waveform.classList.add('active');
            this.elements.recordStatus.textContent = 'Recording... Click to stop';

        } catch (error) {
            this.showError('Could not access microphone. Please check permissions.');
            console.error('Microphone error:', error);
        }
    }

    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;

            this.elements.recordBtn.classList.remove('recording');
            this.elements.recordBtn.querySelector('.flosc-record-icon').textContent = '🎤';
            this.elements.recordBtn.querySelector('.flosc-record-label').textContent = 'Click to Record';
            this.elements.waveform.classList.remove('active');
        }
    }

    async processQuiz() {
        this.hideRecorder();
        this.showLoading('Processing your recording...');

        try {
            const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
            this.data.audioBlob = audioBlob;

            const formData = new FormData();
            formData.append('audio', audioBlob, 'recording.webm');
            formData.append('expected_text', this.config.quizText);

            const response = await fetch(this.config.restUrl + '/process-quiz', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            if (!response.ok) throw new Error('Quiz processing failed');

            const result = await response.json();
            this.hideLoading();

            this.data.quizScore = result.score;
            this.data.quizDetails = result.details;
            this.saveSession();

            await this.botMessage(`Great! I've analyzed your recording.`);

            if (this.config.isLoggedIn) {
                this.transition('RESULTS');
            } else {
                this.transition('LOGIN_GATE');
            }

        } catch (error) {
            this.hideLoading();
            this.showError('Could not process your recording. Please try again.');
            console.error('Quiz processing error:', error);

            this.showReplies([
                { text: 'Try Again', action: () => this.startQuiz() },
            ]);
        }
    }

    // ========================================================================
    // L - LOGIN GATE
    // ========================================================================

    async runLoginGate() {
        await this.botMessage(this.config.loginPrompt);

        if (this.config.enableSocialLogin) {
            await this.showSocialLogin();
        }

        this.showReplies([
            { text: 'Login with Email', action: () => window.location.href = this.config.loginUrl },
        ]);
    }

    async showSocialLogin() {
        this.showLoading('Loading login options...');

        try {
            const response = await fetch(this.config.restUrl + '/social-login');
            const result = await response.json();

            this.hideLoading();

            if (result.available && result.html) {
                this.elements.socialLogin.innerHTML = result.html;
                this.elements.socialLogin.classList.remove('hidden');
            }
        } catch (error) {
            this.hideLoading();
            console.error('Social login error:', error);
        }
    }

    // ========================================================================
    // O - OFFER (Results + Free Content)
    // ========================================================================

    async runResults() {
        const score = this.data.quizScore;
        const tier = this.getTier(score);

        await this.botMessage(`Your score: ${score}/100`);
        await this.botMessage(tier.message);

        // Show free content
        await this.showFreeContent();

        // Move to offer
        this.transition('OFFER');
    }

    getTier(score) {
        for (const tier of this.config.tiers) {
            if (score >= tier.min && score <= tier.max) {
                return tier;
            }
        }
        return this.config.tiers[0];
    }

    async showFreeContent() {
        this.showLoading('Loading your free lesson...');

        try {
            const response = await fetch(this.config.restUrl + '/free-content');
            const result = await response.json();

            this.hideLoading();

            if (result.content) {
                await this.botMessage(this.config.freeContentMessage);
                this.renderContent(result);
            }
        } catch (error) {
            this.hideLoading();
            console.error('Free content error:', error);
        }
    }

    // ========================================================================
    // S - SALE (Offer)
    // ========================================================================

    async runOffer() {
        await this.botMessage(this.config.offerHeadline);
        await this.botMessage(this.config.offerDescription);

        const price = `${this.config.offerCurrency}${this.config.offerPrice}`;
        await this.botMessage(`Price: ${price}`);

        this.showReplies([
            { text: `Buy Now (${price})`, action: () => this.transition('SALE') },
            { text: 'Maybe later', action: () => this.botMessage('No problem! Come back anytime.') },
        ]);
    }

    async runSale() {
        if (this.config.checkoutUrl && this.config.checkoutUrl !== '#') {
            window.location.href = this.config.checkoutUrl;
        } else {
            await this.botMessage('Checkout not configured. Please contact support.');
        }
    }

    // ========================================================================
    // C - CONTENT (Post-Purchase)
    // ========================================================================

    async runContent() {
        await this.botMessage(`Welcome back, ${this.config.userName || 'there'}! 🎉`);
        await this.botMessage('Here are your lessons:');

        this.showLoading('Loading your content...');

        try {
            const response = await fetch(this.config.restUrl + '/content', {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });
            const result = await response.json();

            this.hideLoading();

            if (result.items && result.items.length > 0) {
                result.items.forEach(item => this.renderContent(item));
            } else {
                await this.botMessage(result.message || 'No content available yet. Check back soon!');
            }
        } catch (error) {
            this.hideLoading();
            this.showError('Could not load content. Please refresh the page.');
            console.error('Content loading error:', error);
        }
    }

    renderContent(item) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flosc-message bot lesson-content';
        msgDiv.innerHTML = `
            <div class="flosc-message-content">
                ${item.content}
            </div>
        `;
        this.elements.messages.appendChild(msgDiv);
        this.scrollToBottom();
    }

    // ========================================================================
    // UI HELPERS
    // ========================================================================

    async botMessage(text, delay = 800) {
        this.showTyping();
        await this.sleep(delay);
        this.hideTyping();

        const msgDiv = document.createElement('div');
        msgDiv.className = 'flosc-message bot';
        msgDiv.innerHTML = `
            <div class="flosc-message-avatar">${this.config.botAvatar}</div>
            <div class="flosc-message-content">${this.escapeHtml(text)}</div>
        `;
        this.elements.messages.appendChild(msgDiv);
        this.scrollToBottom();
    }

    showReplies(buttons) {
        this.clearReplies();
        buttons.forEach(btn => {
            const btnEl = document.createElement('button');
            btnEl.className = 'flosc-reply-button';
            btnEl.textContent = btn.text;
            btnEl.onclick = btn.action;
            this.elements.replies.appendChild(btnEl);
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

    updateStatus(state) {
        const statusMap = {
            'FREELINE': 'Ready to start',
            'LOGIN_GATE': 'Login required',
            'RESULTS': 'Analyzing results',
            'OFFER': 'Special offer',
            'SALE': 'Checkout',
            'CONTENT': 'Full access',
        };
        this.elements.statusText.textContent = statusMap[state] || 'Online';
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

    // ========================================================================
    // SESSION PERSISTENCE
    // ========================================================================

    getSessionId() {
        let id = localStorage.getItem('flosc_session_id');
        if (!id) {
            id = 'sess_' + Math.random().toString(36).substring(2, 15);
            localStorage.setItem('flosc_session_id', id);
        }
        return id;
    }

    saveSession() {
        const sessionData = {
            state: this.state,
            quizScore: this.data.quizScore,
            quizDetails: this.data.quizDetails,
        };
        localStorage.setItem('flosc_session', JSON.stringify(sessionData));
    }

    async restoreSession() {
        const stored = localStorage.getItem('flosc_session');
        if (!stored) return;

        try {
            const session = JSON.parse(stored);
            this.state = session.state || 'INIT';
            this.data.quizScore = session.quizScore;
            this.data.quizDetails = session.quizDetails;
        } catch (error) {
            console.error('Session restore error:', error);
        }
    }
}

// Initialize when DOM ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('flosc-container')) {
        window.floscInstance = new FLOSC();
    }
});
