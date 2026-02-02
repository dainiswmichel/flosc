/**
 * LeSAEp App - Main JavaScript
 * Handles all client-side functionality for the AI pronunciation coach app
 */

class LeSAEpApp {
    constructor() {
        this.config = window.LESAEP || {};
        this.currentSessionId = null;
        this.messages = [];

        // Initialize app
        this.init();
    }

    init() {
        // Set up user interface based on state
        this.setupUI();

        // Bind event listeners
        this.bindEvents();

        // Load session history if logged in
        if (this.config.isLoggedIn) {
            this.loadSessionHistory();
        }

        // Check for referral parameter
        this.handleReferral();
    }

    setupUI() {
        const userState = this.config.userState;

        if (userState === 'visitor') {
            // Show landing state
            document.getElementById('landingState').style.display = 'flex';
        } else {
            // Show logged-in state
            document.getElementById('landingState').style.display = 'none';
            document.getElementById('greeting').style.display = 'block';
            document.getElementById('greetingTitle').textContent = `Welcome back, ${this.config.userName}`;

            // Set up profile card
            document.getElementById('profileAvatar').src = this.config.userAvatar;
            document.getElementById('profileAvatar').alt = this.config.userName;
            document.getElementById('profileName').textContent = this.config.userName;
            document.getElementById('profileDropdownName').textContent = this.config.userName;
            document.getElementById('profileDropdownEmail').textContent = this.config.userEmail;

            // Set badge
            const badge = userState === 'paid' ? 'Pro' : 'Free';
            document.getElementById('profileBadge').textContent = badge;

            // Show/hide elements based on state
            if (userState === 'free') {
                document.getElementById('upgradeBanner').style.display = 'block';
                document.getElementById('upgradeContainer').style.display = 'block';
            }

            // Show profile card and share button
            document.getElementById('userProfileCard').style.display = 'block';
            document.getElementById('shareBtn').style.display = 'flex';
            document.getElementById('sessionHistory').style.display = 'block';
            document.getElementById('authButtons').style.display = 'none';
        }
    }

    bindEvents() {
        // New chat button
        document.getElementById('newChatBtn').addEventListener('click', () => this.startNewChat());

        // Send message
        document.getElementById('sendBtn').addEventListener('click', () => this.sendMessage());
        document.getElementById('messageInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Auto-resize textarea
        document.getElementById('messageInput').addEventListener('input', (e) => {
            e.target.style.height = 'auto';
            e.target.style.height = e.target.scrollHeight + 'px';
        });

        // Suggested prompts
        document.querySelectorAll('.prompt-card').forEach(card => {
            card.addEventListener('click', () => {
                const prompt = card.dataset.prompt;
                document.getElementById('messageInput').value = prompt;
                this.sendMessage();
            });
        });

        // Mobile menu
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            document.querySelector('.lesaep-sidebar').classList.toggle('open');
        });

        // Profile dropdown
        document.getElementById('profileButton').addEventListener('click', () => {
            const dropdown = document.getElementById('profileDropdown');
            const button = document.getElementById('profileButton');
            const isOpen = dropdown.style.display === 'block';
            dropdown.style.display = isOpen ? 'none' : 'block';
            button.classList.toggle('active', !isOpen);
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-profile-card')) {
                document.getElementById('profileDropdown').style.display = 'none';
                document.getElementById('profileButton').classList.remove('active');
            }
        });

        // Share button
        document.getElementById('shareBtn').addEventListener('click', () => this.openShareModal());

        // Share modal
        document.getElementById('shareModalClose').addEventListener('click', () => {
            document.getElementById('shareModal').style.display = 'none';
        });

        document.getElementById('copyBtn').addEventListener('click', () => this.copyShareLink());

        // Upgrade buttons
        const checkoutUrl = this.config.checkoutUrl || '#';
        document.querySelectorAll('#upgradeBtn, #upgradeBannerBtn').forEach(btn => {
            btn.href = checkoutUrl;
        });

        // Close upgrade banner
        const upgradeBannerClose = document.getElementById('upgradeBannerClose');
        if (upgradeBannerClose) {
            upgradeBannerClose.addEventListener('click', () => {
                document.getElementById('upgradeBanner').style.display = 'none';
            });
        }
    }

    async startNewChat() {
        try {
            const response = await fetch(`${this.config.restUrl}/sessions`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const data = await response.json();
            this.currentSessionId = data.session_id;
            this.messages = [];

            // Clear messages display
            document.getElementById('messages').innerHTML = '';
            document.getElementById('greeting').style.display = 'block';
            document.getElementById('landingState').style.display = 'none';

            // Reload session history
            this.loadSessionHistory();

        } catch (error) {
            console.error('Error creating new session:', error);
        }
    }

    async loadSessionHistory() {
        try {
            const response = await fetch(`${this.config.restUrl}/sessions`, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const data = await response.json();
            this.renderSessionHistory(data);

        } catch (error) {
            console.error('Error loading session history:', error);
        }
    }

    renderSessionHistory(groupedSessions) {
        const historyEl = document.getElementById('sessionHistory');
        historyEl.innerHTML = '';

        const groups = [
            { key: 'today', title: 'Today' },
            { key: 'yesterday', title: 'Yesterday' },
            { key: 'last_7_days', title: 'Last 7 days' },
            { key: 'older', title: 'Older' },
        ];

        groups.forEach(group => {
            const sessions = groupedSessions[group.key];
            if (sessions && sessions.length > 0) {
                const groupEl = document.createElement('div');
                groupEl.className = 'session-group';

                const titleEl = document.createElement('div');
                titleEl.className = 'session-group-title';
                titleEl.textContent = group.title;
                groupEl.appendChild(titleEl);

                sessions.forEach(session => {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'session-item';
                    itemEl.textContent = session.title;
                    itemEl.dataset.sessionId = session.session_id;

                    itemEl.addEventListener('click', () => {
                        this.loadSession(session.session_id);
                    });

                    groupEl.appendChild(itemEl);
                });

                historyEl.appendChild(groupEl);
            }
        });
    }

    async loadSession(sessionId) {
        try {
            const response = await fetch(`${this.config.restUrl}/sessions/${sessionId}`, {
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const session = await response.json();
            this.currentSessionId = sessionId;
            this.messages = session.messages || [];

            // Render messages
            this.renderMessages();

            // Update active state in history
            document.querySelectorAll('.session-item').forEach(item => {
                item.classList.toggle('active', item.dataset.sessionId === sessionId);
            });

            // Hide landing, show messages
            document.getElementById('landingState').style.display = 'none';
            document.getElementById('greeting').style.display = 'none';

        } catch (error) {
            console.error('Error loading session:', error);
        }
    }

    renderMessages() {
        const messagesEl = document.getElementById('messages');
        messagesEl.innerHTML = '';

        this.messages.forEach(message => {
            const messageEl = this.createMessageElement(message);
            messagesEl.appendChild(messageEl);
        });

        // Scroll to bottom
        const chatContainer = document.querySelector('.chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    createMessageElement(message) {
        const messageEl = document.createElement('div');
        messageEl.className = `message ${message.role}`;

        const avatarEl = document.createElement('div');
        avatarEl.className = 'message-avatar';
        avatarEl.textContent = message.role === 'user' ?
            (this.config.userName ? this.config.userName.charAt(0).toUpperCase() : 'U') :
            '🎯';

        const contentEl = document.createElement('div');
        contentEl.className = 'message-content';

        const roleEl = document.createElement('div');
        roleEl.className = 'message-role';
        roleEl.textContent = message.role === 'user' ? 'You' : 'LeSAEp';

        const textEl = document.createElement('div');
        textEl.className = 'message-text';
        textEl.textContent = message.content;

        contentEl.appendChild(roleEl);
        contentEl.appendChild(textEl);

        messageEl.appendChild(avatarEl);
        messageEl.appendChild(contentEl);

        return messageEl;
    }

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const messageText = input.value.trim();

        if (!messageText) {
            return;
        }

        // Clear input
        input.value = '';
        input.style.height = 'auto';

        // Create session if needed
        if (!this.currentSessionId) {
            await this.startNewChat();
        }

        // Add user message to display
        const userMessage = {
            role: 'user',
            content: messageText,
            timestamp: new Date().toISOString(),
        };

        this.messages.push(userMessage);
        const messageEl = this.createMessageElement(userMessage);
        document.getElementById('messages').appendChild(messageEl);

        // Hide landing/greeting
        document.getElementById('landingState').style.display = 'none';
        document.getElementById('greeting').style.display = 'none';

        // Save message to session
        this.saveMessage(this.currentSessionId, 'user', messageText);

        // Show typing indicator
        document.getElementById('typingIndicator').style.display = 'flex';

        // Scroll to bottom
        this.scrollToBottom();

        // Get AI response
        try {
            const response = await fetch(`${this.config.restUrl}/ai-query`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({
                    prompt: messageText,
                    session_id: this.currentSessionId,
                }),
            });

            const data = await response.json();

            // Hide typing indicator
            document.getElementById('typingIndicator').style.display = 'none';

            // Check if visitor needs to log in
            if (!this.config.isLoggedIn && this.messages.length >= 2) {
                this.showLoginGate();
                return;
            }

            // Add bot message
            const botMessage = {
                role: 'assistant',
                content: data.message,
                timestamp: new Date().toISOString(),
            };

            this.messages.push(botMessage);
            const botMessageEl = this.createMessageElement(botMessage);
            document.getElementById('messages').appendChild(botMessageEl);

            // Save message to session
            this.saveMessage(this.currentSessionId, 'assistant', data.message);

            // Scroll to bottom
            this.scrollToBottom();

            // Reload session history
            if (this.config.isLoggedIn) {
                this.loadSessionHistory();
            }

        } catch (error) {
            console.error('Error sending message:', error);
            document.getElementById('typingIndicator').style.display = 'none';
        }
    }

    async saveMessage(sessionId, role, content) {
        if (!this.config.isLoggedIn) {
            // For visitors, save to localStorage
            const sessions = JSON.parse(localStorage.getItem('lesaep_sessions') || '[]');
            let session = sessions.find(s => s.session_id === sessionId);

            if (!session) {
                session = {
                    session_id: sessionId,
                    created_at: new Date().toISOString(),
                    title: 'New chat',
                    messages: [],
                };
                sessions.push(session);
            }

            session.messages.push({ role, content, timestamp: new Date().toISOString() });

            // Update title from first user message
            if (role === 'user' && session.title === 'New chat' && session.messages.filter(m => m.role === 'user').length === 1) {
                session.title = content.substring(0, 50) + (content.length > 50 ? '...' : '');
            }

            localStorage.setItem('lesaep_sessions', JSON.stringify(sessions));
            return;
        }

        // For logged-in users, save to server
        try {
            await fetch(`${this.config.restUrl}/sessions/${sessionId}/messages`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce,
                },
                body: JSON.stringify({ role, content }),
            });
        } catch (error) {
            console.error('Error saving message:', error);
        }
    }

    showLoginGate() {
        const gateMessage = {
            role: 'assistant',
            content: 'To see your full results and continue, please create a free account or log in.',
            timestamp: new Date().toISOString(),
        };

        this.messages.push(gateMessage);
        const messageEl = this.createMessageElement(gateMessage);

        // Add login buttons
        const actionsEl = document.createElement('div');
        actionsEl.style.cssText = 'display: flex; gap: 12px; margin-top: 16px;';

        const loginBtn = document.createElement('a');
        loginBtn.href = this.config.loginUrl;
        loginBtn.className = 'btn-primary';
        loginBtn.textContent = 'Log in';

        const signupBtn = document.createElement('a');
        signupBtn.href = this.config.signupUrl;
        signupBtn.className = 'btn-secondary';
        signupBtn.textContent = 'Sign up';

        actionsEl.appendChild(loginBtn);
        actionsEl.appendChild(signupBtn);

        const contentEl = messageEl.querySelector('.message-content');
        contentEl.appendChild(actionsEl);

        document.getElementById('messages').appendChild(messageEl);
        this.scrollToBottom();

        // Disable input
        document.getElementById('messageInput').disabled = true;
        document.getElementById('sendBtn').disabled = true;
    }

    async openShareModal() {
        // Generate referral link
        try {
            const response = await fetch(`${this.config.restUrl}/referral`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.config.nonce,
                },
            });

            const data = await response.json();

            document.getElementById('shareText').textContent = data.share_text;
            document.getElementById('shareLink').value = data.referral_url;
            document.getElementById('shareModal').style.display = 'flex';

        } catch (error) {
            console.error('Error generating referral link:', error);
        }
    }

    copyShareLink() {
        const input = document.getElementById('shareLink');
        input.select();
        document.execCommand('copy');

        // Update button text
        const btnText = document.getElementById('copyBtnText');
        btnText.textContent = 'Copied!';
        setTimeout(() => {
            btnText.textContent = 'Copy link';
        }, 2000);
    }

    handleReferral() {
        const urlParams = new URLSearchParams(window.location.search);
        const ref = urlParams.get('ref');

        if (ref) {
            // Save referral code to cookie
            document.cookie = `lesaep_ref=${ref}; path=/; max-age=${30 * 24 * 60 * 60}`;
        }
    }

    scrollToBottom() {
        const chatContainer = document.querySelector('.chat-container');
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new LeSAEpApp();
});

// ===== v02_07 NEW: Audio Recording Feature =====

/**
 * Audio Recorder Extension
 * Handles audio recording, waveform visualization, and pronunciation analysis
 */
class LeSAEpAudioRecorder {
    constructor(app) {
        this.app = app;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.audioBlob = null;
        this.stream = null;
        this.audioContext = null;
        this.analyser = null;
        this.animationId = null;

        // Bind event listeners
        this.bindRecordingEvents();
    }

    bindRecordingEvents() {
        // Open modal when "Start free quiz" is clicked
        document.querySelectorAll('.prompt-card').forEach(card => {
            if (card.dataset.prompt === 'Start the free quiz') {
                card.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent default prompt handling
                    this.openRecordingModal();
                });
            }
        });

        // Recording controls
        document.getElementById('recordBtn').addEventListener('click', () => this.startRecording());
        document.getElementById('stopBtn').addEventListener('click', () => this.stopRecording());
        document.getElementById('playBtn').addEventListener('click', () => this.playBack());
        document.getElementById('submitRecordingBtn').addEventListener('click', () => this.submitRecording());

        // Close modal
        document.getElementById('recordingModalClose').addEventListener('click', () => this.closeRecordingModal());
    }

    openRecordingModal() {
        // Set quiz sentence from config
        document.getElementById('quizSentence').textContent = this.app.config.quizSentence;

        // Show modal
        document.getElementById('recordingModal').style.display = 'flex';

        // Reset state
        this.resetRecording();
    }

    closeRecordingModal() {
        document.getElementById('recordingModal').style.display = 'none';

        // Clean up
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
        }
        if (this.audioContext) {
            this.audioContext.close();
            this.audioContext = null;
        }
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
            this.animationId = null;
        }
    }

    resetRecording() {
        this.audioChunks = [];
        this.audioBlob = null;

        // Reset UI
        document.getElementById('recordBtn').style.display = 'flex';
        document.getElementById('stopBtn').style.display = 'none';
        document.getElementById('playBtn').style.display = 'none';
        document.getElementById('submitRecordingBtn').style.display = 'none';
        document.getElementById('statusText').textContent = 'Ready to record';
        document.getElementById('recordingVisualizer').classList.remove('recording');

        // Clear canvas
        const canvas = document.getElementById('waveformCanvas');
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    async startRecording() {
        try {
            // Request microphone access
            this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            // Setup MediaRecorder
            this.mediaRecorder = new MediaRecorder(this.stream);
            this.audioChunks = [];

            this.mediaRecorder.ondataavailable = (event) => {
                this.audioChunks.push(event.data);
            };

            this.mediaRecorder.onstop = () => {
                this.audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
                this.onRecordingComplete();
            };

            // Setup audio visualization
            this.setupAudioVisualization();

            // Start recording
            this.mediaRecorder.start();

            // Update UI
            document.getElementById('recordBtn').style.display = 'none';
            document.getElementById('stopBtn').style.display = 'flex';
            document.getElementById('statusText').textContent = 'Recording... Speak now!';
            document.getElementById('recordingVisualizer').classList.add('recording');

        } catch (error) {
            console.error('Error starting recording:', error);
            alert('Could not access microphone. Please check permissions.');
        }
    }

    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }

        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }

        // Update UI
        document.getElementById('stopBtn').style.display = 'none';
        document.getElementById('statusText').textContent = 'Recording complete';
        document.getElementById('recordingVisualizer').classList.remove('recording');
    }

    setupAudioVisualization() {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        this.analyser = this.audioContext.createAnalyser();
        const source = this.audioContext.createMediaStreamSource(this.stream);
        source.connect(this.analyser);

        this.analyser.fftSize = 256;
        const bufferLength = this.analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);

        const canvas = document.getElementById('waveformCanvas');
        const ctx = canvas.getContext('2d');

        const draw = () => {
            this.animationId = requestAnimationFrame(draw);

            this.analyser.getByteFrequencyData(dataArray);

            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const barWidth = (canvas.width / bufferLength) * 2.5;
            let x = 0;

            for (let i = 0; i < bufferLength; i++) {
                const barHeight = (dataArray[i] / 255) * canvas.height;

                const gradient = ctx.createLinearGradient(0, canvas.height - barHeight, 0, canvas.height);
                gradient.addColorStop(0, '#ef4444');
                gradient.addColorStop(1, '#dc2626');

                ctx.fillStyle = gradient;
                ctx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);

                x += barWidth + 1;
            }
        };

        draw();
    }

    onRecordingComplete() {
        // Show play and submit buttons
        document.getElementById('playBtn').style.display = 'flex';
        document.getElementById('submitRecordingBtn').style.display = 'flex';
    }

    playBack() {
        if (!this.audioBlob) return;

        const audio = new Audio(URL.createObjectURL(this.audioBlob));
        audio.play();

        document.getElementById('statusText').textContent = 'Playing back...';

        audio.onended = () => {
            document.getElementById('statusText').textContent = 'Playback complete';
        };
    }

    async submitRecording() {
        if (!this.audioBlob) {
            alert('No recording to submit');
            return;
        }

        // Close recording modal
        this.closeRecordingModal();

        // Show chat interface if hidden
        document.getElementById('landingState').style.display = 'none';
        document.getElementById('greeting').style.display = 'none';

        // Add user message
        const userMessage = {
            role: 'user',
            content: `🎤 Recorded: "${this.app.config.quizSentence}"`,
            timestamp: new Date().toISOString(),
        };

        this.app.messages.push(userMessage);
        const messageEl = this.app.createMessageElement(userMessage);
        document.getElementById('messages').appendChild(messageEl);

        // Show typing indicator
        document.getElementById('typingIndicator').style.display = 'flex';
        this.app.scrollToBottom();

        // Upload and analyze
        try {
            const formData = new FormData();
            formData.append('audio', this.audioBlob, 'recording.webm');
            formData.append('expected_text', this.app.config.quizSentence);

            const response = await fetch(`${this.app.config.restUrl}/process-audio`, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this.app.config.nonce,
                },
                body: formData,
            });

            const data = await response.json();

            // Hide typing indicator
            document.getElementById('typingIndicator').style.display = 'none';

            if (data.success) {
                // Add analysis message
                const botMessage = {
                    role: 'assistant',
                    content: data.chat_message,
                    timestamp: new Date().toISOString(),
                };

                this.app.messages.push(botMessage);
                const botMessageEl = this.app.createMessageElement(botMessage);
                document.getElementById('messages').appendChild(botMessageEl);

                // Save to session
                if (this.app.currentSessionId) {
                    this.app.saveMessage(this.app.currentSessionId, 'user', userMessage.content);
                    this.app.saveMessage(this.app.currentSessionId, 'assistant', botMessage.content);
                }

                // Show upsell if there are paid lessons
                if (data.paid_lessons && data.paid_lessons.length > 0) {
                    this.showUpsellMessage(data.free_lesson, data.paid_lessons);
                }

                this.app.scrollToBottom();

                // Reload session history if logged in
                if (this.app.config.isLoggedIn) {
                    this.app.loadSessionHistory();
                }

            } else {
                // Error message
                const errorMessage = {
                    role: 'assistant',
                    content: 'Sorry, I encountered an error analyzing your pronunciation. Please try again.',
                    timestamp: new Date().toISOString(),
                };

                this.app.messages.push(errorMessage);
                const errorMessageEl = this.app.createMessageElement(errorMessage);
                document.getElementById('messages').appendChild(errorMessageEl);

                this.app.scrollToBottom();
            }

        } catch (error) {
            console.error('Error submitting recording:', error);
            document.getElementById('typingIndicator').style.display = 'none';

            const errorMessage = {
                role: 'assistant',
                content: 'Sorry, I encountered a network error. Please try again.',
                timestamp: new Date().toISOString(),
            };

            this.app.messages.push(errorMessage);
            const errorMessageEl = this.app.createMessageElement(errorMessage);
            document.getElementById('messages').appendChild(errorMessageEl);

            this.app.scrollToBottom();
        }
    }

    showUpsellMessage(freeLesson, paidLessons) {
        const upsellMessage = {
            role: 'assistant',
            content: `\n\n🎁 **Your FREE lesson is now available!**\n\nWant to unlock all ${paidLessons.length} remaining lessons? [Upgrade now](${this.app.config.checkoutUrl}) to get full access.`,
            timestamp: new Date().toISOString(),
        };

        this.app.messages.push(upsellMessage);
        const upsellMessageEl = this.app.createMessageElement(upsellMessage);
        document.getElementById('messages').appendChild(upsellMessageEl);
    }
}

// Initialize audio recorder when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait for LeSAEpApp to be initialized
    setTimeout(() => {
        const app = window.lesaepApp;
        if (app) {
            window.lesaepRecorder = new LeSAEpAudioRecorder(app);
        }
    }, 100);
});

// Store app instance globally for recorder access
const originalInit = LeSAEpApp.prototype.init;
LeSAEpApp.prototype.init = function() {
    originalInit.call(this);
    window.lesaepApp = this;
};
