/**
 * FLOSC Framework - Chat State Machine
 * 
 * States: FREELINE → LOGIN_GATE → RESULTS → OFFER → SALE → CONTENT
 */

class FLOSC {
    constructor() {
        this.config = window.FLOSC_CONFIG;
        this.state = 'INIT';
        this.data = {
            quizScore: null,
            quizDetails: null,
            audioBlob: null,
        };
        
        // Session storage key
        this.storageKey = 'flosc_session';
        
        // Recording state
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        
        this.init();
    }
    
    async init() {
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
        };
        
        // Restore session if exists
        this.restoreSession();
        
        // Determine starting state based on user status and session
        if (this.config.hasPaidAccess) {
            this.transition('CONTENT');
        } else if (this.data.quizScore !== null && this.config.isLoggedIn) {
            // User completed quiz, logged in, show results
            this.transition('RESULTS');
        } else if (this.data.quizScore !== null && !this.config.isLoggedIn) {
            // User completed quiz but not logged in
            this.transition('LOGIN_GATE');
        } else {
            this.transition('FREELINE');
        }
    }
    
    /**
     * State Machine - Transition Handler
     */
    async transition(newState) {
        console.log(`FLOSC: ${this.state} → ${newState}`);
        this.state = newState;
        this.updateStatus(newState);
        
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
    }
    
    /**
     * F - FREELINE: Welcome + Quiz
     */
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
    
    showRecorder() {
        this.clearReplies();
        this.elements.quizText.textContent = this.config.quizText;
        this.elements.recorder.classList.add('visible');
        this.elements.recordStatus.textContent = 'Click the microphone when ready';
        this.setupRecorder();
    }
    
    hideRecorder() {
        this.elements.recorder.classList.remove('visible');
    }
    
    setupRecorder() {
        const btn = this.elements.recordBtn;
        const waveform = this.elements.waveform;
        
        btn.onclick = async () => {
            if (!this.isRecording) {
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
                    
                    btn.classList.add('recording');
                    btn.querySelector('.flosc-record-icon').textContent = '⏹️';
                    btn.querySelector('.flosc-record-label').textContent = 'Stop';
                    waveform.classList.add('active');
                    this.elements.recordStatus.textContent = 'Recording... Click to stop';
                    
                } catch (error) {
                    console.error('Microphone error:', error);
                    await this.botMessage("Please allow microphone access to continue.");
                }
            } else {
                this.mediaRecorder.stop();
                this.isRecording = false;
                
                btn.classList.remove('recording');
                btn.querySelector('.flosc-record-icon').textContent = '🎤';
                btn.querySelector('.flosc-record-label').textContent = 'Click to Record';
                waveform.classList.remove('active');
            }
        };
    }
    
    async processQuiz() {
        this.hideRecorder();
        await this.botMessage("Got it! Analyzing...");
        
        this.data.audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        
        try {
            let result;
            
            if (this.config.processingEndpoint) {
                // Use external API
                const formData = new FormData();
                formData.append('audio', this.data.audioBlob);
                formData.append('expected_text', this.config.quizText);
                
                const response = await fetch(this.config.processingEndpoint, {
                    method: 'POST',
                    body: formData,
                });
                result = await response.json();
            } else {
                // Use mock endpoint
                const formData = new FormData();
                formData.append('expected_text', this.config.quizText);
                
                const response = await fetch(`${this.config.restUrl}/process-quiz`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ expected_text: this.config.quizText }),
                });
                result = await response.json();
            }
            
            this.data.quizScore = result.score;
            this.data.quizDetails = result.details;
            this.saveSession();
            
            await this.botMessage("Analysis complete! ✅");
            
            // Check login status
            if (!this.config.isLoggedIn) {
                this.transition('LOGIN_GATE');
            } else {
                this.transition('RESULTS');
            }
            
        } catch (error) {
            console.error('Processing error:', error);
            await this.botMessage("There was an error processing your recording. Let's try again.");
            this.showReplies([
                { text: "Try again 🎤", action: () => this.showRecorder() },
            ]);
        }
    }
    
    /**
     * L - LOGIN GATE
     */
    async runLoginGate() {
        await this.botMessage(this.config.loginPrompt);
        
        this.showReplies([
            { 
                text: "Login to See Results 🔐", 
                action: () => {
                    window.location.href = this.config.loginUrl;
                }
            },
        ]);
    }
    
    /**
     * O - RESULTS + OFFER
     */
    async runResults() {
        const score = this.data.quizScore;
        
        // Find matching tier
        let tierMessage = "Here are your results.";
        for (const tier of this.config.tiers) {
            if (score >= tier.min && score <= tier.max) {
                tierMessage = tier.message;
                break;
            }
        }
        
        await this.botMessage(`Your score: <strong>${score}/100</strong>`);
        await this.botMessage(tierMessage);
        
        // Show free content
        await this.botMessage(this.config.freeContentMessage);
        await this.showFreeContent();
        
        // Transition to offer
        setTimeout(() => this.transition('OFFER'), 2000);
    }
    
    async showFreeContent() {
        try {
            const response = await fetch(`${this.config.restUrl}/free-content`);
            const content = await response.json();
            
            await this.botMessage(`
                <div class="flosc-content-card" onclick="window.open('${content.url}', '_blank')">
                    <h4>${content.title}</h4>
                    <p>${content.excerpt}</p>
                    <span class="flosc-badge free">🎁 FREE</span>
                </div>
            `);
        } catch (error) {
            console.error('Error loading free content:', error);
        }
    }
    
    /**
     * O - OFFER
     */
    async runOffer() {
        await this.botMessage("Now, here's something special for you...");
        
        const { offerHeadline, offerDescription, offerPrice, offerCurrency } = this.config;
        
        await this.botMessage(`
            <div class="flosc-offer-card">
                <h3>🎓 ${offerHeadline}</h3>
                <p>${offerDescription}</p>
                <p class="flosc-price">${offerCurrency}${offerPrice}</p>
            </div>
        `);
        
        this.showReplies([
            { 
                text: `Get Full Access ${offerCurrency}${offerPrice} 🎉`, 
                action: () => this.transition('SALE') 
            },
            { 
                text: "Maybe later", 
                action: () => this.handleMaybeLater() 
            },
        ]);
    }
    
    async handleMaybeLater() {
        await this.botMessage("No problem! Your free lesson is always available.");
        await this.botMessage("When you're ready for the full course, just come back!");
        
        this.showReplies([
            { text: "View Free Lesson", action: () => this.showFreeContent() },
            { text: `I'm ready - Get Access 🎉`, action: () => this.transition('SALE') },
        ]);
    }
    
    /**
     * S - SALE
     */
    async runSale() {
        await this.botMessage("Excellent choice! Redirecting to checkout...");
        
        if (this.config.checkoutUrl) {
            setTimeout(() => {
                window.location.href = this.config.checkoutUrl;
            }, 1500);
        } else {
            await this.botMessage("⚠️ Checkout URL not configured. Please set up in FLOSC settings.");
        }
    }
    
    /**
     * C - CONTENT (Post-Sale)
     */
    async runContent() {
        await this.botMessage(`Welcome back, ${this.config.userName}! 👋`);
        await this.botMessage("You have full access. Here's your content:");
        
        try {
            const response = await fetch(`${this.config.restUrl}/content`);
            const data = await response.json();
            
            if (data.items && data.items.length > 0) {
                await this.botMessage(`📚 <strong>${data.total} lessons available</strong>`);
                
                for (const item of data.items.slice(0, 5)) {
                    await this.botMessage(`
                        <div class="flosc-content-card" onclick="window.open('${item.url}', '_blank')">
                            <h4>${item.title}</h4>
                            <p>${item.excerpt}</p>
                        </div>
                    `, 300);
                }
                
                if (data.items.length > 5) {
                    this.showReplies([
                        { text: "Show more lessons", action: () => this.showAllContent(data.items) },
                    ]);
                }
            } else {
                await this.botMessage("No content configured yet. Add posts to your content category.");
            }
        } catch (error) {
            console.error('Error loading content:', error);
            await this.botMessage("There was an error loading your content.");
        }
    }
    
    async showAllContent(items) {
        for (const item of items.slice(5)) {
            await this.botMessage(`
                <div class="flosc-content-card" onclick="window.open('${item.url}', '_blank')">
                    <h4>${item.title}</h4>
                    <p>${item.excerpt}</p>
                </div>
            `, 200);
        }
    }
    
    /**
     * Session Management
     */
    saveSession() {
        const session = {
            quizScore: this.data.quizScore,
            quizDetails: this.data.quizDetails,
            timestamp: Date.now(),
        };
        sessionStorage.setItem(this.storageKey, JSON.stringify(session));
    }
    
    restoreSession() {
        try {
            const stored = sessionStorage.getItem(this.storageKey);
            if (stored) {
                const session = JSON.parse(stored);
                // Only restore if less than 1 hour old
                if (Date.now() - session.timestamp < 3600000) {
                    this.data.quizScore = session.quizScore;
                    this.data.quizDetails = session.quizDetails;
                }
            }
        } catch (e) {
            console.log('No session to restore');
        }
    }
    
    /**
     * UI Helpers
     */
    async botMessage(html, delay = 800) {
        return new Promise(resolve => {
            this.showTyping();
            
            setTimeout(() => {
                this.hideTyping();
                
                const div = document.createElement('div');
                div.className = 'flosc-message bot';
                div.innerHTML = `
                    <div class="flosc-msg-avatar">${this.config.botAvatar}</div>
                    <div class="flosc-msg-content">${html}</div>
                `;
                
                this.elements.messages.appendChild(div);
                this.scrollToBottom();
                resolve();
            }, delay);
        });
    }
    
    userMessage(text) {
        const div = document.createElement('div');
        div.className = 'flosc-message user';
        div.innerHTML = `
            <div class="flosc-msg-avatar">👤</div>
            <div class="flosc-msg-content">${text}</div>
        `;
        this.elements.messages.appendChild(div);
        this.scrollToBottom();
    }
    
    showTyping() {
        this.elements.typing.classList.add('visible');
        this.scrollToBottom();
    }
    
    hideTyping() {
        this.elements.typing.classList.remove('visible');
    }
    
    showReplies(options) {
        this.elements.replies.innerHTML = '';
        
        options.forEach(opt => {
            const btn = document.createElement('button');
            btn.className = 'flosc-reply-btn';
            btn.textContent = opt.text;
            btn.onclick = () => {
                this.userMessage(opt.text);
                this.clearReplies();
                opt.action();
            };
            this.elements.replies.appendChild(btn);
        });
    }
    
    clearReplies() {
        this.elements.replies.innerHTML = '';
    }
    
    scrollToBottom() {
        this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    }
    
    updateStatus(state) {
        const labels = {
            'INIT': 'Starting...',
            'FREELINE': 'Online',
            'LOGIN_GATE': 'Waiting for login',
            'RESULTS': 'Showing results',
            'OFFER': 'Special offer',
            'SALE': 'Checkout',
            'CONTENT': 'Full access',
        };
        this.elements.statusText.textContent = labels[state] || 'Online';
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.floscInstance = new FLOSC();
});
