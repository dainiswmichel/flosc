/**
 * FLOSC App JavaScript
 * Main application controller
 */

class FloscApp {
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
        
        // Initialize
        this.init();
    }
    
    async init() {
        this.bindElements();
        this.bindEvents();
        this.setupUI();
        
        if (this.config.stripeKey) {
            this.initStripe();
        }
        
        if (this.state !== 'visitor') {
            await this.loadSessions();
            
            // Check if user has pending quiz results (just logged in after quiz)
            await this.checkPendingQuizResults();
        }
        
        // Check if free lesson already delivered (for chatbot lock)
        this.freeLessonDelivered = this.user?.freeLessonDelivered || false;
        
        // Track page view
        this.trackEvent('page_view');
    }
    
    bindElements() {
        // Sidebar
        this.sidebar = document.getElementById('floscSidebar');
        this.sidebarOverlay = document.getElementById('sidebarOverlay');
        this.sidebarClose = document.getElementById('sidebarClose');
        this.mobileMenuBtn = document.getElementById('mobileMenuBtn');
        this.newChatBtn = document.getElementById('newChatBtn');
        this.sessionHistory = document.getElementById('sessionHistory');
        
        // Profile
        this.profileButton = document.getElementById('profileButton');
        this.profileDropdown = document.getElementById('profileDropdown');
        this.profileAvatar = document.getElementById('profileAvatar');
        this.profileName = document.getElementById('profileName');
        this.profileBadge = document.getElementById('profileBadge');
        this.dropdownName = document.getElementById('dropdownName');
        this.dropdownEmail = document.getElementById('dropdownEmail');
        
        // Chat
        this.chatContainer = document.getElementById('chatContainer');
        this.landingState = document.getElementById('landingState');
        this.messages = document.getElementById('messages');
        this.greeting = document.getElementById('greeting');
        this.greetingTitle = document.getElementById('greetingTitle');
        this.typingIndicator = document.getElementById('typingIndicator');
        this.messageInput = document.getElementById('messageInput');
        this.sendBtn = document.getElementById('sendBtn');
        this.micBtn = document.getElementById('micBtn');
        
        // Prompt cards
        this.promptCards = document.querySelectorAll('.prompt-card');
        
        // Upgrade
        this.upgradeBanner = document.getElementById('upgradeBanner');
        this.upgradeBannerClose = document.getElementById('upgradeBannerClose');
        this.upgradeBtn = document.getElementById('upgradeBtn');
        this.upgradeBannerBtn = document.getElementById('upgradeBannerBtn');
        
        // Modals
        this.recordingModal = document.getElementById('recordingModal');
        this.shareModal = document.getElementById('shareModal');
        this.paymentModal = document.getElementById('paymentModal');
        this.loginGateModal = document.getElementById('loginGateModal');
        
        // Recording elements
        this.recordBtn = document.getElementById('recordBtn');
        this.stopBtn = document.getElementById('stopBtn');
        this.recordingTimer = document.getElementById('recordingTimer');
        this.waveformCanvas = document.getElementById('waveformCanvas');
        this.recordingPlayback = document.getElementById('recordingPlayback');
        this.recordingAudio = document.getElementById('recordingAudio');
        this.rerecordBtn = document.getElementById('rerecordBtn');
        this.submitRecordingBtn = document.getElementById('submitRecordingBtn');
        this.recordingError = document.getElementById('recordingError');
        
        // Share
        this.shareBtn = document.getElementById('shareBtn');
        this.shareLink = document.getElementById('shareLink');
        this.copyBtn = document.getElementById('copyBtn');
        
        // Payment
        this.cardElement = null;
        this.payBtn = document.getElementById('payBtn');
        this.cardErrors = document.getElementById('card-errors');
    }
    
    bindEvents() {
        // Mobile menu
        this.mobileMenuBtn?.addEventListener('click', () => this.toggleSidebar(true));
        this.sidebarClose?.addEventListener('click', () => this.toggleSidebar(false));
        this.sidebarOverlay?.addEventListener('click', () => this.toggleSidebar(false));
        
        // New chat
        this.newChatBtn?.addEventListener('click', () => this.startNewChat());
        
        // Profile dropdown
        this.profileButton?.addEventListener('click', () => this.toggleProfileDropdown());
        
        // Message input
        this.messageInput?.addEventListener('input', () => this.autoResizeTextarea());
        this.messageInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        this.sendBtn?.addEventListener('click', () => this.sendMessage());
        
        // Microphone
        this.micBtn?.addEventListener('click', () => this.openRecordingModal());
        
        // Prompt cards (v3.0.4 - fixed to use actions instead of echoing)
        this.promptCards.forEach(card => {
            card.addEventListener('click', () => {
                const action = card.dataset.action;
                this.handlePromptCardAction(action);
            });
        });
        
        // Upgrade buttons
        this.upgradeBannerClose?.addEventListener('click', () => {
            this.upgradeBanner.style.display = 'none';
        });
        
        [this.upgradeBtn, this.upgradeBannerBtn].forEach(btn => {
            btn?.addEventListener('click', () => this.openPaymentModal());
        });
        
        // Recording controls
        this.recordBtn?.addEventListener('click', () => this.startRecording());
        this.stopBtn?.addEventListener('click', () => this.stopRecording());
        this.rerecordBtn?.addEventListener('click', () => this.resetRecording());
        this.submitRecordingBtn?.addEventListener('click', () => this.submitRecording());
        
        // Share
        this.shareBtn?.addEventListener('click', () => this.openShareModal());
        this.copyBtn?.addEventListener('click', () => this.copyShareLink());
        
        // Payment
        this.payBtn?.addEventListener('click', () => this.processPayment());
        
        // Modal closes
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => this.closeAllModals());
        });
        
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.closeAllModals();
            });
        });
        
        // Close dropdowns on outside click
        document.addEventListener('click', (e) => {
            if (!this.profileButton?.contains(e.target) && !this.profileDropdown?.contains(e.target)) {
                this.profileDropdown?.classList.remove('show');
                this.profileButton?.classList.remove('open');
            }
        });
    }
    
    setupUI() {
        if (this.state !== 'visitor' && this.user) {
            // Set profile info
            if (this.profileAvatar) this.profileAvatar.src = this.user.avatar || '';
            if (this.profileName) this.profileName.textContent = this.user.name || 'User';
            if (this.profileBadge) this.profileBadge.textContent = this.state === 'paid' ? 'Pro' : 'Free';
            if (this.dropdownName) this.dropdownName.textContent = this.user.name || 'User';
            if (this.dropdownEmail) this.dropdownEmail.textContent = this.user.email || '';
            
            // Set greeting
            const hour = new Date().getHours();
            let greeting = 'Good evening';
            if (hour < 12) greeting = 'Good morning';
            else if (hour < 18) greeting = 'Good afternoon';
            
            if (this.greetingTitle) {
                this.greetingTitle.textContent = `${greeting}, ${this.user.name?.split(' ')[0] || 'there'}`;
            }
        }
    }
    
    // Sidebar
    toggleSidebar(open) {
        if (open) {
            this.sidebar?.classList.add('open');
            this.sidebarOverlay?.classList.add('show');
        } else {
            this.sidebar?.classList.remove('open');
            this.sidebarOverlay?.classList.remove('show');
        }
    }
    
    toggleProfileDropdown() {
        this.profileDropdown?.classList.toggle('show');
        this.profileButton?.classList.toggle('open');
    }
    
    // Sessions
    async loadSessions() {
        try {
            const response = await this.api('sessions');
            if (response.sessions) {
                this.renderSessions(response.sessions);
            }
        } catch (error) {
            console.error('Failed to load sessions:', error);
        }
    }
    
    renderSessions(grouped) {
        if (!this.sessionHistory) return;
        
        this.sessionHistory.innerHTML = '';
        
        const groups = [
            { key: 'today', label: 'Today' },
            { key: 'yesterday', label: 'Yesterday' },
            { key: 'last_7_days', label: 'Last 7 days' },
            { key: 'older', label: 'Older' },
        ];
        
        groups.forEach(({ key, label }) => {
            const sessions = grouped[key] || [];
            if (sessions.length === 0) return;
            
            const group = document.createElement('div');
            group.className = 'session-group';
            group.innerHTML = `<div class="session-group-title">${label}</div>`;
            
            sessions.forEach(session => {
                const item = document.createElement('button');
                item.className = 'session-item';
                item.textContent = session.title;
                item.addEventListener('click', () => this.loadSession(session.id));
                group.appendChild(item);
            });
            
            this.sessionHistory.appendChild(group);
        });
    }
    
    async loadSession(sessionId) {
        try {
            const response = await this.api(`sessions/${sessionId}`);
            if (response.session) {
                this.currentSession = response.session;
                this.showMessages();
                this.renderSessionMessages(response.session.messages);
            }
        } catch (error) {
            console.error('Failed to load session:', error);
        }
        
        this.toggleSidebar(false);
    }
    
    async startNewChat() {
        if (this.state === 'visitor') {
            this.showLanding();
            return;
        }
        
        try {
            const response = await this.api('sessions', 'POST', { title: 'New Chat' });
            if (response.session) {
                this.currentSession = response.session;
                this.showMessages();
                this.messages.innerHTML = `
                    <div class="greeting" id="greeting">
                        <h2 class="greeting-title">How can I help you today?</h2>
                    </div>
                `;
                await this.loadSessions();
            }
        } catch (error) {
            console.error('Failed to create session:', error);
        }
        
        this.toggleSidebar(false);
    }
    
    // Chat UI
    showLanding() {
        this.landingState.style.display = 'flex';
        this.messages.style.display = 'none';
    }
    
    showMessages() {
        this.landingState.style.display = 'none';
        this.messages.style.display = 'flex';
    }
    
    autoResizeTextarea() {
        this.messageInput.style.height = 'auto';
        this.messageInput.style.height = Math.min(this.messageInput.scrollHeight, 150) + 'px';
    }

    /**
     * Handle prompt card actions (v3.0.4)
     */
    handlePromptCardAction(action) {
        switch(action) {
            case 'get-started':
                // Show "Get Started" message from backend
                if (FLOSC_CONFIG.messages.getStarted) {
                    this.addBotMessage(FLOSC_CONFIG.messages.getStarted);
                    this.showMessages();
                }
                break;

            case 'start-quiz':
                // Open quiz modal or recording interface
                this.openRecordingModal();
                this.showMessages();
                break;

            case 'how-it-works':
                // Show "How It Works" message from backend
                if (FLOSC_CONFIG.messages.howItWorks) {
                    this.addBotMessage(FLOSC_CONFIG.messages.howItWorks);
                    this.showMessages();
                }
                break;

            case 'what-you-learn':
                // Show "What You Learn" message from backend
                if (FLOSC_CONFIG.messages.whatYouLearn) {
                    this.addBotMessage(FLOSC_CONFIG.messages.whatYouLearn);
                    this.showMessages();
                }
                break;

            default:
                console.warn('Unknown prompt card action:', action);
        }
    }

    async sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;
        
        // Check login gate for visitors
        if (this.state === 'visitor') {
            this.visitorInteractions++;
            if (this.visitorInteractions > 2) {
                this.openLoginGate();
                return;
            }
        }
        
        // Check chatbot lock for free users who got their free lesson
        if (this.state === 'free' && this.freeLessonDelivered) {
            this.showMessages();
            this.addMessage('user', message);
            this.addMessage('assistant', `I'd love to continue helping you! 🎯\n\nTo unlock unlimited conversations and all ${this.config.product?.name || 'our'} lessons, upgrade your account.\n\nYou've already taken a great first step with your free lesson. Ready to master everything?`);
            this.showUpgradePrompt();
            return;
        }
        
        this.showMessages();
        this.messageInput.value = '';
        this.autoResizeTextarea();
        
        // Add user message
        this.addMessage('user', message);
        
        // Track
        this.trackEvent('message_sent');
        
        // Show typing indicator
        this.typingIndicator?.classList.add('show');
        
        try {
            // Get AI response
            const response = await this.api('ai-query', 'POST', { message });
            
            this.typingIndicator?.classList.remove('show');
            
            if (response.response) {
                this.addMessage('assistant', response.response);
                
                // Check for quiz triggers
                if (this.shouldTriggerQuiz(message)) {
                    setTimeout(() => this.openRecordingModal(), 1000);
                }
            }
            
            // Save to session if logged in
            if (this.state !== 'visitor' && this.currentSession) {
                await this.api(`sessions/${this.currentSession.id}/messages`, 'POST', {
                    role: 'user',
                    content: message,
                });
                await this.api(`sessions/${this.currentSession.id}/messages`, 'POST', {
                    role: 'assistant',
                    content: response.response,
                });
            }
            
        } catch (error) {
            this.typingIndicator?.classList.remove('show');
            this.addMessage('assistant', 'Sorry, something went wrong. Please try again.');
            console.error('AI query failed:', error);
        }
    }
    
    shouldTriggerQuiz(message) {
        if (this.config.quizMode === 'none') return false;
        const triggers = ['quiz', 'test', 'analyze', 'pronunciation', 'start', 'begin', 'record'];
        const lower = message.toLowerCase();
        return triggers.some(t => lower.includes(t));
    }
    
    /**
     * Check for pending quiz results (user just logged in after taking quiz)
     */
    async checkPendingQuizResults() {
        // Check localStorage for pre-login score
        const storedScore = localStorage.getItem('flosc_prelogin_score');
        
        if (storedScore) {
            try {
                const scoreData = JSON.parse(storedScore);
                
                // Check if recent (within last hour)
                if (scoreData.timestamp && (Date.now() - scoreData.timestamp) < 3600000) {
                    // Clear the stored score
                    localStorage.removeItem('flosc_prelogin_score');
                    
                    // Show welcome back message with score
                    this.showMessages();
                    const productName = this.config.product?.name || 'our course';
                    
                    this.addMessage('assistant', `Welcome back, ${this.user?.name?.split(' ')[0] || 'there'}! 🎉\n\nYour quiz score: **${scoreData.score}%**\n\nI've got a **FREE lesson** ready just for you based on what you need to practice. Let me show you!`);
                    
                    // Deliver free lesson after brief delay
                    setTimeout(() => {
                        this.deliverFreeLesson();
                    }, 2000);
                }
            } catch (e) {
                console.error('Error parsing stored score:', e);
                localStorage.removeItem('flosc_prelogin_score');
            }
        }
    }
    
    /**
     * Fetch and deliver the user's free lesson
     */
    async deliverFreeLesson() {
        try {
            const response = await this.api('lessons/free');
            
            if (response.lesson) {
                const lesson = response.lesson;
                
                // Format lesson content for chat
                let lessonMessage = `**🎁 Your FREE Lesson: ${lesson.title}**\n\n`;
                
                if (lesson.content) {
                    // Strip HTML tags for chat display, or show excerpt
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = lesson.content;
                    const textContent = tempDiv.textContent || tempDiv.innerText || '';
                    
                    // Truncate if too long
                    const truncated = textContent.length > 800 
                        ? textContent.substring(0, 800) + '...' 
                        : textContent;
                    
                    lessonMessage += truncated;
                } else if (lesson.excerpt) {
                    lessonMessage += lesson.excerpt;
                }
                
                if (lesson.url) {
                    lessonMessage += `\n\n[Read full lesson](${lesson.url})`;
                }
                
                this.addMessage('assistant', lessonMessage);
                
                // Mark free lesson as delivered
                this.freeLessonDelivered = true;
                
                // After a pause, show upgrade offer
                setTimeout(() => {
                    this.showUpgradeOffer();
                }, 3000);
                
                this.trackEvent('free_lesson_delivered', {
                    lesson_id: lesson.id,
                    lesson_title: lesson.title
                });
            }
        } catch (error) {
            console.error('Failed to fetch free lesson:', error);
            this.addMessage('assistant', `Your personalized lesson is being prepared. In the meantime, would you like to explore our full course?`);
            this.showUpgradeOffer();
        }
    }
    
    /**
     * Show upgrade offer with OTO if configured
     */
    showUpgradeOffer() {
        const productName = this.config.product?.name || 'full access';
        const offers = this.config.offers || [];
        const otoOfferId = this.config.otoOfferId;
        
        // Find OTO offer
        let otoOffer = null;
        if (otoOfferId) {
            otoOffer = offers.find(o => o.id === otoOfferId);
        }
        if (!otoOffer && offers.length > 0) {
            otoOffer = offers[0];
        }
        
        let message = `**Ready to master everything?** 🚀\n\n`;
        
        if (otoOffer) {
            message += `Get **${otoOffer.name}** for just ${otoOffer.display_price}\n\n`;
            message += `${otoOffer.description || 'Unlock all lessons and features!'}\n\n`;
        } else {
            message += `Unlock all lessons and unlimited practice with ${productName}!\n\n`;
        }
        
        message += `You've taken the first step with your free lesson. The rest of your personalized learning path is waiting!`;
        
        this.addMessage('assistant', message);
        
        // Show upgrade banner/button
        this.showUpgradePrompt();
    }
    
    /**
     * Show upgrade UI elements
     */
    showUpgradePrompt() {
        if (this.upgradeBanner) {
            this.upgradeBanner.classList.add('show');
        }
        // Could also open payment modal directly
        // this.openPaymentModal();
    }
    
    addMessage(role, content) {
        const emoji = this.config.product?.emoji || '🎯';
        const userInitial = this.user?.name?.charAt(0).toUpperCase() || 'U';
        
        const messageEl = document.createElement('div');
        messageEl.className = `message ${role}`;
        
        if (role === 'user') {
            messageEl.innerHTML = `
                <div class="message-avatar">${userInitial}</div>
                <div class="message-content">
                    <div class="message-text">${this.escapeHtml(content)}</div>
                </div>
            `;
        } else {
            // Parse markdown-like formatting
            const formatted = this.formatMessage(content);
            messageEl.innerHTML = `
                <div class="message-avatar">${emoji}</div>
                <div class="message-content">
                    <div class="message-text">${formatted}</div>
                </div>
            `;
        }
        
        this.messages.appendChild(messageEl);
        this.scrollToBottom();
    }
    
    formatMessage(content) {
        // Basic markdown formatting
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\n\n/g, '</p><p>')
            .replace(/\n/g, '<br>')
            .replace(/^/, '<p>')
            .replace(/$/, '</p>');
    }
    
    renderSessionMessages(messages) {
        this.messages.innerHTML = '';
        messages.forEach(msg => {
            this.addMessage(msg.role, msg.content);
        });
    }
    
    scrollToBottom() {
        this.chatContainer?.scrollTo({
            top: this.chatContainer.scrollHeight,
            behavior: 'smooth'
        });
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Recording
    async openRecordingModal() {
        this.recordingModal?.classList.add('show');
        this.resetRecording();
        this.trackEvent('recording_modal_opened');
    }
    
    async startRecording() {
        try {
            this.recordingStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            
            this.mediaRecorder = new MediaRecorder(this.recordingStream);
            this.audioChunks = [];
            
            this.mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) {
                    this.audioChunks.push(e.data);
                }
            };
            
            this.mediaRecorder.onstop = () => {
                const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                const audioUrl = URL.createObjectURL(audioBlob);
                this.recordingAudio.src = audioUrl;
                this.recordingPlayback.style.display = 'block';
                this.stopRecordingUI();
            };
            
            this.mediaRecorder.start();
            this.isRecording = true;
            this.startRecordingUI();
            this.startTimer();
            this.startWaveform();
            
            this.trackEvent('recording_started');
            
        } catch (error) {
            console.error('Microphone access denied:', error);
            this.recordingError.style.display = 'block';
            this.trackEvent('recording_error', { error: 'microphone_denied' });
        }
    }
    
    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            this.stopRecordingStream();
            this.trackEvent('recording_stopped');
        }
    }
    
    stopRecordingStream() {
        if (this.recordingStream) {
            this.recordingStream.getTracks().forEach(track => track.stop());
            this.recordingStream = null;
        }
    }
    
    startRecordingUI() {
        this.recordBtn.style.display = 'none';
        this.stopBtn.style.display = 'flex';
        this.micBtn?.classList.add('recording');
    }
    
    stopRecordingUI() {
        this.recordBtn.style.display = 'none';
        this.stopBtn.style.display = 'none';
        this.micBtn?.classList.remove('recording');
    }
    
    resetRecording() {
        this.stopRecordingStream();
        this.isRecording = false;
        this.audioChunks = [];
        
        this.recordBtn.style.display = 'flex';
        this.stopBtn.style.display = 'none';
        this.recordingPlayback.style.display = 'none';
        this.recordingError.style.display = 'none';
        this.recordingTimer.textContent = '0:00';
        this.micBtn?.classList.remove('recording');
        
        // Clear waveform
        const ctx = this.waveformCanvas?.getContext('2d');
        if (ctx) {
            ctx.clearRect(0, 0, this.waveformCanvas.width, this.waveformCanvas.height);
        }
    }
    
    startTimer() {
        let seconds = 0;
        this.timerInterval = setInterval(() => {
            if (!this.isRecording) {
                clearInterval(this.timerInterval);
                return;
            }
            seconds++;
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            this.recordingTimer.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        }, 1000);
    }
    
    startWaveform() {
        if (!this.waveformCanvas || !this.recordingStream) return;
        
        const ctx = this.waveformCanvas.getContext('2d');
        const audioContext = new AudioContext();
        const source = audioContext.createMediaStreamSource(this.recordingStream);
        const analyser = audioContext.createAnalyser();
        
        analyser.fftSize = 256;
        source.connect(analyser);
        
        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        const draw = () => {
            if (!this.isRecording) return;
            
            requestAnimationFrame(draw);
            analyser.getByteFrequencyData(dataArray);
            
            ctx.fillStyle = '#f5f5f5';
            ctx.fillRect(0, 0, this.waveformCanvas.width, this.waveformCanvas.height);
            
            const barWidth = (this.waveformCanvas.width / bufferLength) * 2.5;
            let x = 0;
            
            const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--flosc-primary').trim() || '#4f46e5';
            
            for (let i = 0; i < bufferLength; i++) {
                const barHeight = (dataArray[i] / 255) * this.waveformCanvas.height * 0.8;
                
                ctx.fillStyle = primaryColor;
                ctx.fillRect(x, (this.waveformCanvas.height - barHeight) / 2, barWidth - 1, barHeight);
                
                x += barWidth;
            }
        };
        
        draw();
    }
    
    async submitRecording() {
        if (this.audioChunks.length === 0) return;
        
        this.submitRecordingBtn.disabled = true;
        this.submitRecordingBtn.textContent = 'Processing...';
        
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.webm');
        
        try {
            const response = await fetch(this.config.restUrl + 'process-audio', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
                body: formData,
            });
            
            const result = await response.json();
            
            this.closeAllModals();
            
            if (result.success) {
                this.trackEvent('quiz_completed', {
                    score: result.analysis?.score,
                    transcript: result.transcript,
                });
                
                // Show result in chat
                this.showMessages();
                
                // Add user's audio as a message indicator
                this.addMessage('user', `🎤 [Audio recording submitted]`);
                
                // Show analysis result
                if (result.analysis) {
                    this.addMessage('assistant', result.message || result.analysis.feedback);
                    
                    // Store score data for visitors
                    if (this.state === 'visitor') {
                        const scoreData = {
                            score: result.analysis.score,
                            correct: result.analysis.correct || [],
                            incorrect: result.analysis.incorrect || [],
                            quiz_type: this.config.quizType,
                            timestamp: Date.now()
                        };
                        
                        // Store in localStorage
                        localStorage.setItem('flosc_prelogin_score', JSON.stringify(scoreData));
                        
                        // Also store via API (sets cookie)
                        try {
                            await this.api('store-score', 'POST', scoreData);
                        } catch (e) {
                            console.log('Score storage via API failed, localStorage backup exists');
                        }
                        
                        // Prompt login to see free lesson
                        setTimeout(() => {
                            this.addMessage('assistant', `Great effort! 🎉\n\nTo get your **personalized score** and a **FREE lesson** based on your results, create a free account or log in.\n\nYour progress will be saved and waiting for you!`);
                            setTimeout(() => this.openLoginGate(), 2000);
                        }, 2000);
                        
                    } else if (this.state === 'free') {
                        // Logged in free user - offer free lesson immediately
                        setTimeout(() => {
                            this.deliverFreeLesson();
                        }, 2000);
                    }
                } else {
                    this.addMessage('assistant', `I heard: "${result.transcript}". How can I help you with your pronunciation?`);
                }
            } else {
                this.addMessage('assistant', 'Sorry, I had trouble processing your recording. Please try again.');
            }
            
        } catch (error) {
            console.error('Failed to process recording:', error);
            this.closeAllModals();
            this.addMessage('assistant', 'Sorry, something went wrong. Please try again.');
        }
        
        this.submitRecordingBtn.disabled = false;
        this.submitRecordingBtn.textContent = 'Submit';
    }
    
    // Share
    async openShareModal() {
        this.shareModal?.classList.add('show');
        
        try {
            const response = await this.api('referral');
            if (response.link) {
                this.shareLink.value = response.link;
            }
        } catch (error) {
            this.shareLink.value = this.config.appUrl || window.location.href;
        }
        
        this.trackEvent('share_modal_opened');
    }
    
    async copyShareLink() {
        try {
            await navigator.clipboard.writeText(this.shareLink.value);
            document.getElementById('copyBtnText').textContent = 'Copied!';
            setTimeout(() => {
                document.getElementById('copyBtnText').textContent = 'Copy';
            }, 2000);
            this.trackEvent('share_link_copied');
        } catch (error) {
            console.error('Failed to copy:', error);
        }
    }
    
    // Payment (Stripe)
    initStripe() {
        if (!this.config.stripeKey || typeof Stripe === 'undefined') return;
        
        this.stripe = Stripe(this.config.stripeKey);
        const elements = this.stripe.elements();
        
        this.cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#1a1a1a',
                    '::placeholder': {
                        color: '#999',
                    },
                },
            },
        });
        
        this.cardElement.mount('#card-element');
        
        this.cardElement.on('change', (event) => {
            this.cardErrors.textContent = event.error ? event.error.message : '';
            this.payBtn.disabled = !event.complete;
        });
    }
    
    openPaymentModal() {
        if (!this.config.stripeKey) {
            alert('Payment is not configured. Please contact support.');
            return;
        }
        
        this.paymentModal?.classList.add('show');
        this.trackEvent('payment_modal_opened');
    }
    
    async processPayment() {
        if (!this.stripe || !this.cardElement) return;
        
        this.payBtn.disabled = true;
        this.payBtn.classList.add('loading');
        
        try {
            // Create payment intent
            const response = await this.api('create-payment-intent', 'POST');
            
            if (!response.clientSecret) {
                throw new Error('Failed to create payment intent');
            }
            
            // Confirm payment
            const { error, paymentIntent } = await this.stripe.confirmCardPayment(
                response.clientSecret,
                {
                    payment_method: {
                        card: this.cardElement,
                    },
                }
            );
            
            if (error) {
                this.cardErrors.textContent = error.message;
                this.trackEvent('payment_failed', { error: error.message });
            } else if (paymentIntent.status === 'succeeded') {
                this.trackEvent('payment_succeeded', { amount: paymentIntent.amount });
                this.closeAllModals();
                
                // Show success message
                this.addMessage('assistant', '🎉 Payment successful! Welcome to full access. All lessons are now unlocked. Let me know what you\'d like to work on first!');
                
                // Update state
                document.body.dataset.userState = 'paid';
                this.state = 'paid';
                
                // Reload page after short delay to update everything
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
            
        } catch (error) {
            console.error('Payment error:', error);
            this.cardErrors.textContent = 'Payment failed. Please try again.';
            this.trackEvent('payment_error', { error: error.message });
        }
        
        this.payBtn.disabled = false;
        this.payBtn.classList.remove('loading');
    }
    
    // Login Gate
    openLoginGate() {
        this.loginGateModal?.classList.add('show');
        this.trackEvent('login_gate_shown');
    }
    
    // Modals
    closeAllModals() {
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.classList.remove('show');
        });
        this.resetRecording();
    }
    
    // API Helper
    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'X-WP-Nonce': this.config.nonce,
            },
        };
        
        if (data && method !== 'GET') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(this.config.restUrl + endpoint, options);
        return response.json();
    }
    
    // Analytics
    trackEvent(event, params = {}) {
        // Google Analytics
        if (typeof gtag === 'function' && this.config.ga4Id) {
            gtag('event', event, {
                ...params,
                product: this.config.product?.name,
                user_state: this.state,
            });
        }
        
        // Console for debugging
        console.log('FLOSC Event:', event, params);
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    window.floscApp = new FloscApp();
});
