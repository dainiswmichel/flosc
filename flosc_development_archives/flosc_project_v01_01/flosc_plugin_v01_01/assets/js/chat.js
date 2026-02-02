/**
 * LeSAEp Chat-Only Interface
 * Pure chat experience from hello → payment → content
 */

class LeSAEpChat {
    constructor() {
        this.config = window.LESAEP_CONFIG;
        this.state = {
            step: 'intro',
            quizId: null,
            sessionToken: null,
            recordings: [],
            currentSentence: 0,
            flaggedPhonemes: [],
            otoExpires: null,
            countdownInterval: null
        };
        
        this.elements = {};
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.stripe = null;
        this.paymentElement = null;
        this.noCount = 0;
        
        this.init();
    }
    
    async init() {
        // Initialize DOM elements
        this.initElements();
        
        // Initialize Stripe
        if (this.config.stripePublicKey) {
            this.stripe = Stripe(this.config.stripePublicKey);
        }
        
        // Get session token from WordPress
        await this.getSessionToken();
        
        // Start conversation
        await this.startConversation();
    }
    
    initElements() {
        this.elements = {
            messages: document.getElementById('chatMessages'),
            typing: document.getElementById('typingIndicator'),
            quickReplies: document.getElementById('quickReplies'),
            recorderArea: document.getElementById('recorderArea'),
            sentenceDisplay: document.getElementById('sentenceDisplay'),
            recordBtn: document.getElementById('recordBtn'),
            waveform: document.getElementById('waveform'),
            recordStatus: document.getElementById('recordStatus'),
            paymentModal: document.getElementById('paymentModal'),
            submitPaymentBtn: document.getElementById('submitPaymentBtn'),
            cancelPaymentBtn: document.getElementById('cancelPaymentBtn'),
            lessonViewer: document.getElementById('lessonViewer'),
            lessonContent: document.getElementById('lessonContent'),
            countdownOverlay: document.getElementById('countdownOverlay')
        };
        
        // Setup payment cancel
        if (this.elements.cancelPaymentBtn) {
            this.elements.cancelPaymentBtn.onclick = () => this.hidePaymentModal();
        }
    }
    
    /**
     * Get WordPress session token
     */
    async getSessionToken() {
        try {
            const response = await fetch(`${this.config.wpRestUrl}/session`);
            const data = await response.json();
            
            if (data.logged_in && data.lesaep_token) {
                this.state.sessionToken = data.lesaep_token;
                this.config.userId = data.user_id;
                this.config.isLoggedIn = true;
                this.config.hasPaidAccess = data.has_paid_access;
            }
        } catch (error) {
            console.warn('Session token fetch failed:', error);
        }
    }
    
    /**
     * Add bot message
     */
    async addBotMessage(html, delay = 1000) {
        return new Promise(resolve => {
            this.showTyping();
            
            setTimeout(() => {
                this.hideTyping();
                
                const div = document.createElement('div');
                div.className = 'message bot';
                div.innerHTML = `
                    <div class="message-avatar">🎯</div>
                    <div class="message-content">${html}</div>
                `;
                
                this.elements.messages.appendChild(div);
                this.scrollToBottom();
                resolve();
            }, delay);
        });
    }
    
    /**
     * Add user message
     */
    addUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'message user';
        div.innerHTML = `
            <div class="message-avatar">👤</div>
            <div class="message-content">${text}</div>
        `;
        
        this.elements.messages.appendChild(div);
        this.scrollToBottom();
    }
    
    showTyping() {
        this.elements.typing.classList.remove('hidden');
        this.scrollToBottom();
    }
    
    hideTyping() {
        this.elements.typing.classList.add('hidden');
    }
    
    scrollToBottom() {
        this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    }
    
    /**
     * Show quick reply buttons
     */
    showQuickReplies(options) {
        this.elements.quickReplies.innerHTML = '';
        
        options.forEach(option => {
            const btn = document.createElement('button');
            btn.className = 'quick-reply-btn';
            btn.textContent = option.text;
            btn.onclick = () => {
                this.addUserMessage(option.text);
                this.clearQuickReplies();
                option.action();
            };
            this.elements.quickReplies.appendChild(btn);
        });
    }
    
    clearQuickReplies() {
        this.elements.quickReplies.innerHTML = '';
    }
    
    /**
     * STEP 1: Start conversation
     */
    async startConversation() {
        // Check if returning user with access
        if (this.config.hasPaidAccess) {
            await this.addBotMessage(`Welcome back, ${this.config.userName || 'friend'}! 👋`);
            await this.addBotMessage("You have full access. Let's continue your learning!");
            await this.showDashboard();
            return;
        }
        
        // Start new quiz
        await this.callBackend('/chat/start');
        
        await this.addBotMessage("👋 Hi! I'm your AI pronunciation coach.");
        await this.addBotMessage("I can analyze your English pronunciation in just 2 minutes.");
        await this.addBotMessage("Want a <strong>FREE pronunciation analysis</strong>?");
        
        this.showQuickReplies([
            { text: "Yes, let's do it! 🎤", action: () => this.startQuiz() },
            { text: "Tell me more first", action: () => this.explainMore() },
            { text: "No thanks", action: () => this.handleNo() }
        ]);
    }
    
    async explainMore() {
        await this.addBotMessage("Here's how it works:");
        await this.addBotMessage("1️⃣ You'll record 3 simple sentences<br>2️⃣ AI analyzes your pronunciation<br>3️⃣ Get personalized feedback + free lessons<br>4️⃣ Takes less than 2 minutes!");
        
        this.showQuickReplies([
            { text: "I'm ready! 🎤", action: () => this.startQuiz() },
            { text: "No thanks", action: () => this.handleNo() }
        ]);
    }
    
    async handleNo() {
        this.noCount++;
        
        // Track "No" on backend
        await this.callBackend('/chat/message', {
            message_type: 'no_response',
            no_count: this.noCount
        });
        
        if (this.noCount >= 3) {
            await this.addBotMessage("I understand! Maybe another time. Have a great day! 👋");
            await this.addBotMessage("(Just refresh the page if you change your mind!)");
            return;
        }
        
        await this.addBotMessage("No problem! Just so you know, this analysis is normally worth $150, but it's completely free for you today.");
        await this.addBotMessage("Are you sure you don't want to give it a quick try?");
        
        this.showQuickReplies([
            { text: "Okay, let's try it! 🎤", action: () => this.startQuiz() },
            { text: "No, I'm sure", action: () => this.handleNo() }
        ]);
    }
    
    /**
     * STEP 2: Quiz
     */
    async startQuiz() {
        this.state.step = 'quiz';
        this.state.currentSentence = 0;
        this.state.recordings = [];
        
        await this.addBotMessage("Awesome! 🎉");
        await this.addBotMessage("I'll show you 3 sentences. Just click the microphone and read each one out loud.");
        await this.addBotMessage("Ready for the first sentence?");
        
        this.showQuickReplies([
            { text: "I'm ready! 🎤", action: () => this.showRecorder() }
        ]);
    }
    
    async showRecorder() {
        this.clearQuickReplies();
        
        // Get sentences from backend
        const response = await this.callBackend('/chat/get-sentences');
        const sentences = response.sentences || [
            "The cat sat on the mat",
            "She sells seashells by the seashore",
            "How now brown cow"
        ];
        
        const sentence = sentences[this.state.currentSentence];
        this.elements.sentenceDisplay.textContent = sentence;
        this.elements.recordStatus.textContent = `Sentence ${this.state.currentSentence + 1} of ${sentences.length}`;
        
        this.elements.recorderArea.classList.remove('hidden');
        this.setupRecorder(sentence);
    }
    
    setupRecorder(expectedText) {
        const recordBtn = this.elements.recordBtn;
        const recordIcon = recordBtn.querySelector('.record-icon');
        const recordText = recordBtn.querySelector('.record-text');
        const waveform = this.elements.waveform;
        
        recordBtn.onclick = async () => {
            if (!this.isRecording) {
                // Start recording
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    this.mediaRecorder = new MediaRecorder(stream);
                    this.audioChunks = [];
                    
                    this.mediaRecorder.ondataavailable = e => this.audioChunks.push(e.data);
                    this.mediaRecorder.onstop = () => {
                        stream.getTracks().forEach(track => track.stop());
                        this.processRecording(expectedText);
                    };
                    
                    this.mediaRecorder.start();
                    this.isRecording = true;
                    
                    recordBtn.classList.add('recording');
                    recordIcon.textContent = '⏹️';
                    recordText.textContent = 'Stop Recording';
                    waveform.classList.remove('hidden');
                    this.elements.recordStatus.textContent = 'Recording... Click to stop';
                } catch (error) {
                    alert('Please allow microphone access to record.');
                    console.error(error);
                }
            } else {
                // Stop recording
                this.mediaRecorder.stop();
                this.isRecording = false;
                
                recordBtn.classList.remove('recording');
                recordIcon.textContent = '🎤';
                recordText.textContent = 'Click to Record';
                waveform.classList.add('hidden');
            }
        };
    }
    
    async processRecording(expectedText) {
        this.elements.recorderArea.classList.add('hidden');
        
        await this.addBotMessage("✅ Got it! Analyzing your pronunciation...", 500);
        
        // Upload to backend
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', audioBlob);
        formData.append('expected_text', expectedText);
        formData.append('sentence_index', this.state.currentSentence);
        
        try {
            const result = await this.callBackend('/chat/upload-audio', formData, true);
            
            this.state.recordings.push(result);
            
            await this.addBotMessage("Nice work on that one!", 1500);
            
            this.state.currentSentence++;
            
            if (this.state.currentSentence < 3) {
                await this.addBotMessage(`Ready for sentence ${this.state.currentSentence + 1}?`);
                this.showQuickReplies([
                    { text: "Next sentence 🎤", action: () => this.showRecorder() }
                ]);
            } else {
                await this.completeQuiz();
            }
        } catch (error) {
            await this.addBotMessage("Oops! There was an error processing your recording. Let's try again.");
            this.showQuickReplies([
                { text: "Record again 🎤", action: () => this.showRecorder() }
            ]);
        }
    }
    
    /**
     * STEP 3: Complete quiz & require login
     */
    async completeQuiz() {
        await this.addBotMessage("Excellent! You've completed all 3 recordings. 🎉");
        await this.addBotMessage("I'm analyzing your pronunciation now...");
        
        // Get evaluation from backend
        const evaluation = await this.callBackend('/chat/evaluate');
        this.state.flaggedPhonemes = evaluation.flagged_phonemes || [];
        
        await this.addBotMessage("Analysis complete!", 2000);
        
        // Check if logged in
        if (!this.config.isLoggedIn) {
            await this.addBotMessage("To see your personalized results, please login with Google, Facebook, or your email.");
            
            this.showQuickReplies([
                {
                    text: "Login to See Results 🔐",
                    action: () => {
                        window.location.href = this.config.loginUrl;
                    }
                }
            ]);
        } else {
            await this.showResults();
        }
    }
    
    /**
     * STEP 4: Show results + free lesson
     */
    async showResults() {
        await this.addBotMessage("Here are your results...");
        
        const phonemes = this.state.flaggedPhonemes;
        await this.addBotMessage(`🎯 <strong>Analysis Results:</strong><br><br>I identified <strong>${phonemes.length} sound patterns</strong> that need attention:<br><br>${phonemes.join(', ')}`);
        
        await this.addBotMessage("The good news? These are <em>very common</em> challenges, and I can help you fix them!");
        
        // Get free lessons from WordPress
        await this.addBotMessage("Fetching your personalized free lessons...", 500);
        
        const response = await fetch(`${this.config.wpRestUrl}/lessons?user_id=${this.config.userId}`);
        const lessons = await response.json();
        
        if (lessons.length > 0) {
            await this.addBotMessage(`🎁 <strong>Your FREE Lesson:</strong>`);
            
            const firstLesson = lessons[0];
            await this.addBotMessage(`
                <div class="lesson-preview">
                    <h4>${firstLesson.title}</h4>
                    ${firstLesson.excerpt}
                </div>
            `);
            
            this.showQuickReplies([
                { text: "View my free lesson 📚", action: () => this.viewLesson(firstLesson.id) }
            ]);
        } else {
            await this.showOffer();
        }
    }
    
    async viewLesson(lessonId) {
        const response = await fetch(`${this.config.wpRestUrl}/lessons/${lessonId}?user_id=${this.config.userId}`);
        const lesson = await response.json();
        
        await this.addBotMessage(`
            <div class="lesson-full">
                <h3>${lesson.title}</h3>
                ${lesson.content}
            </div>
        `);
        
        await this.showOffer();
    }
    
    /**
     * STEP 5: Show OTO
     */
    async showOffer() {
        await this.addBotMessage("Now... I have something special for you.");
        await this.addBotMessage("You did the quiz, so you clearly care about your pronunciation. 👏");
        await this.addBotMessage("Your free lesson helps with ONE sound. But what if you could master <strong>ALL</strong> of them?");
        
        const fullPrice = this.config.fullPrice;
        const otoPrice = this.config.otoPrice;
        const discount = this.config.discount;
        
        await this.addBotMessage(`
            <div class="oto-card">
                <h3>🎁 ONE-TIME OFFER</h3>
                <p class="product-name"><strong>${this.config.productName}</strong></p>
                <p class="product-desc">Complete pronunciation mastery course</p>
                <p class="pricing">
                    <span class="price-old">$${fullPrice}</span>
                    <span class="price-new">$${otoPrice}</span>
                </p>
                <p class="savings">Save ${discount}% - Only $${otoPrice}!</p>
                <p class="urgency">⏰ This offer expires soon!</p>
            </div>
        `);
        
        await this.addBotMessage("How long should this offer last?");
        
        this.showQuickReplies([
            { text: "1 hour ⏰", action: () => this.setOfferDuration(3600) },
            { text: "1 day 📅", action: () => this.setOfferDuration(86400) },
            { text: "7 days 🗓️", action: () => this.setOfferDuration(604800) }
        ]);
    }
    
    async setOfferDuration(seconds) {
        // Set timer on backend
        const response = await this.callBackend('/chat/set-oto-timer', { duration: seconds });
        this.state.otoExpires = response.expires_at;
        
        const labels = { 3600: '1 hour', 86400: '24 hours', 604800: '7 days' };
        const label = labels[seconds] || `${Math.floor(seconds / 3600)} hours`;
        
        await this.addBotMessage(`⏰ Offer set to expire in ${label}!`);
        await this.addBotMessage("Ready to get started?");
        
        // Start countdown
        this.startCountdown(seconds);
        
        this.showQuickReplies([
            { text: `Get ${this.config.discount}% OFF 🎉`, action: () => this.processPurchase() },
            { text: "Let me think about it", action: () => this.handleThinking() }
        ]);
    }
    
    startCountdown(seconds) {
        this.elements.countdownOverlay.classList.remove('hidden');
        
        const endTime = Date.now() + (seconds * 1000);
        
        this.state.countdownInterval = setInterval(() => {
            const remaining = Math.max(0, endTime - Date.now());
            const h = Math.floor(remaining / 3600000);
            const m = Math.floor((remaining % 3600000) / 60000);
            const s = Math.floor((remaining % 60000) / 1000);
            
            document.getElementById('hours').textContent = String(h).padStart(2, '0');
            document.getElementById('minutes').textContent = String(m).padStart(2, '0');
            document.getElementById('seconds').textContent = String(s).padStart(2, '0');
            
            if (remaining === 0) {
                clearInterval(this.state.countdownInterval);
                this.offerExpired();
            }
        }, 1000);
    }
    
    async offerExpired() {
        this.elements.countdownOverlay.classList.add('hidden');
        await this.addBotMessage("⏰ The special offer has expired!");
        await this.addBotMessage("But don't worry - you still have access to your free lesson. 🎁");
    }
    
    async handleThinking() {
        await this.addBotMessage("I totally understand! This is an investment in yourself.");
        await this.addBotMessage(`Just remember - this special ${this.config.discount}% discount is only available RIGHT NOW.`);
        await this.addBotMessage(`Once you leave, it goes back to the full $${this.config.fullPrice} price.`);
        
        this.showQuickReplies([
            { text: `Yes! Get ${this.config.discount}% OFF 🎉`, action: () => this.processPurchase() },
            { text: "I'll stick with free lesson", action: () => this.showDashboard() }
        ]);
    }
    
    /**
     * STEP 6: Process payment (Stripe embedded)
     */
    async processPurchase() {
        await this.addBotMessage("Excellent choice! 🎉");
        await this.addBotMessage("Let me prepare your checkout...");
        
        // Create Payment Intent on backend
        const response = await this.callBackend('/stripe/create-intent', {
            amount: this.config.otoPrice * 100,
            user_id: this.config.userId
        });
        
        const clientSecret = response.client_secret;
        
        // Show payment modal
        this.showPaymentModal(clientSecret);
    }
    
    async showPaymentModal(clientSecret) {
        this.elements.paymentModal.classList.remove('hidden');
        
        // Create Payment Element
        const elements = this.stripe.elements({ clientSecret });
        this.paymentElement = elements.create('payment');
        this.paymentElement.mount('#stripe-payment-element');
        
        // Handle payment submission
        this.elements.submitPaymentBtn.onclick = async () => {
            await this.confirmPayment(clientSecret);
        };
    }
    
    async confirmPayment(clientSecret) {
        const { error, paymentIntent } = await this.stripe.confirmPayment({
            elements: this.stripe.elements({ clientSecret }),
            confirmParams: {
                return_url: window.location.href
            },
            redirect: 'if_required'
        });
        
        if (error) {
            await this.addBotMessage(`❌ Payment failed: ${error.message}`);
        } else if (paymentIntent.status === 'succeeded') {
            await this.completePurchase();
        }
    }
    
    hidePaymentModal() {
        this.elements.paymentModal.classList.add('hidden');
    }
    
    async completePurchase() {
        this.hidePaymentModal();
        
        // Update session
        await this.getSessionToken();
        
        await this.addBotMessage("✅ Payment successful!");
        await this.addBotMessage(`🎉 Welcome to ${this.config.productName}!`);
        await this.addBotMessage("You now have FULL ACCESS to all lessons!");
        
        if (this.state.countdownInterval) {
            clearInterval(this.state.countdownInterval);
            this.elements.countdownOverlay.classList.add('hidden');
        }
        
        await this.showDashboard();
    }
    
    /**
     * STEP 7: Dashboard (lessons in chat)
     */
    async showDashboard() {
        await this.addBotMessage("Loading your course...");
        
        const response = await fetch(`${this.config.wpRestUrl}/lessons?user_id=${this.config.userId}`);
        const lessons = await response.json();
        
        await this.addBotMessage(`📚 <strong>Your Course Dashboard</strong><br><br>You have access to ${lessons.length} lessons.`);
        
        // Show first 5 lessons
        for (const lesson of lessons.slice(0, 5)) {
            await this.addBotMessage(`
                <div class="lesson-card" onclick="window.lesaepChat.viewFullLesson(${lesson.id})">
                    <h4>${lesson.title}</h4>
                    <p>${lesson.excerpt}</p>
                    <span class="lesson-badge">${lesson.is_free ? '🎁 FREE' : '✅ FULL ACCESS'}</span>
                </div>
            `, 300);
        }
        
        this.showQuickReplies([
            { text: "Show more lessons", action: () => this.showAllLessons() },
            { text: "Start over 🔄", action: () => location.reload() }
        ]);
    }
    
    async viewFullLesson(lessonId) {
        const response = await fetch(`${this.config.wpRestUrl}/lessons/${lessonId}?user_id=${this.config.userId}`);
        const lesson = await response.json();
        
        await this.addBotMessage(`
            <div class="lesson-full">
                <h3>${lesson.title}</h3>
                ${lesson.content}
            </div>
        `);
    }
    
    async showAllLessons() {
        const response = await fetch(`${this.config.wpRestUrl}/lessons?user_id=${this.config.userId}`);
        const lessons = await response.json();
        
        for (const lesson of lessons) {
            await this.addBotMessage(`
                <div class="lesson-card" onclick="window.lesaepChat.viewFullLesson(${lesson.id})">
                    <h4>${lesson.title}</h4>
                    <p>${lesson.excerpt}</p>
                </div>
            `, 200);
        }
    }
    
    /**
     * Call backend API
     */
    async callBackend(endpoint, data = null, isFormData = false) {
        const url = `${this.config.apiUrl}${endpoint}`;
        
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {}
        };
        
        // Add session token if available
        if (this.state.sessionToken) {
            options.headers['Authorization'] = `Bearer ${this.state.sessionToken}`;
        }
        
        // Add body
        if (data) {
            if (isFormData) {
                options.body = data;
            } else {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`API error: ${response.status}`);
        }
        
        return await response.json();
    }
}

// Initialize when page loads
window.addEventListener('DOMContentLoaded', () => {
    window.lesaepChat = new LeSAEpChat();
});
