/**
 * LeSAEp Chat - Simplified with WordPress Payment
 * 
 * Flow:
 * 1. Quiz (audio → FastAPI)
 * 2. Results + free lesson
 * 3. "Buy Now" → Redirect to WordPress checkout
 * 4. After payment → Return to chat → Full access
 */

class LeSAEpChat {
    constructor() {
        this.config = window.LESAEP;
        this.state = {
            recordings: [],
            currentSentence: 0,
            flaggedPhonemes: [],
            noCount: 0
        };
        
        this.sentences = [
            "The cat sat on the mat",
            "She sells seashells by the seashore",
            "How now brown cow"
        ];
        
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.isRecording = false;
        
        this.init();
    }
    
    async init() {
        this.elements = {
            messages: document.getElementById('chatMessages'),
            typing: document.getElementById('typingIndicator'),
            quickReplies: document.getElementById('quickReplies'),
            recorderArea: document.getElementById('recorderArea'),
            sentenceDisplay: document.getElementById('sentenceDisplay'),
            recordBtn: document.getElementById('recordBtn'),
            waveform: document.getElementById('waveform'),
            recordStatus: document.getElementById('recordStatus')
        };
        
        // Check user access
        const access = await this.checkAccess();
        
        if (access.has_paid_access) {
            await this.showWelcomeBack(access);
        } else {
            await this.startConversation();
        }
    }
    
    async checkAccess() {
        try {
            const response = await fetch(`${this.config.wpRestUrl}/access`);
            return await response.json();
        } catch (error) {
            console.error('Access check failed:', error);
            return { logged_in: false, has_paid_access: false };
        }
    }
    
    /**
     * STEP 1: Start conversation
     */
    async startConversation() {
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
        await this.addBotMessage("1️⃣ Record 3 simple sentences<br>2️⃣ AI analyzes your pronunciation<br>3️⃣ Get personalized feedback + free lessons<br>4️⃣ Takes less than 2 minutes!");
        
        this.showQuickReplies([
            { text: "I'm ready! 🎤", action: () => this.startQuiz() },
            { text: "No thanks", action: () => this.handleNo() }
        ]);
    }
    
    async handleNo() {
        this.state.noCount++;
        
        if (this.state.noCount >= 3) {
            await this.addBotMessage("I understand! Maybe another time. Have a great day! 👋");
            await this.addBotMessage("(Refresh the page if you change your mind!)");
            return;
        }
        
        await this.addBotMessage("No problem! This analysis is usually $150, but it's completely free for you today.");
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
        
        const sentence = this.sentences[this.state.currentSentence];
        this.elements.sentenceDisplay.textContent = sentence;
        this.elements.recordStatus.textContent = `Sentence ${this.state.currentSentence + 1} of ${this.sentences.length}`;
        
        this.elements.recorderArea.classList.remove('hidden');
        this.setupRecorder(sentence);
    }
    
    setupRecorder(expectedText) {
        const recordBtn = this.elements.recordBtn;
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
                    recordBtn.querySelector('.record-icon').textContent = '⏹️';
                    recordBtn.querySelector('.record-text').textContent = 'Stop Recording';
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
                recordBtn.querySelector('.record-icon').textContent = '🎤';
                recordBtn.querySelector('.record-text').textContent = 'Click to Record';
                waveform.classList.add('hidden');
            }
        };
    }
    
    async processRecording(expectedText) {
        this.elements.recorderArea.classList.add('hidden');
        
        await this.addBotMessage("✅ Got it! Analyzing your pronunciation...", 500);
        
        // Upload to FastAPI backend
        const audioBlob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', audioBlob);
        formData.append('expected_text', expectedText);
        formData.append('sentence_index', this.state.currentSentence);
        
        try {
            const response = await fetch(`${this.config.apiUrl}/process-audio`, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            this.state.recordings.push(result);
            
            await this.addBotMessage("Nice work on that one!", 1000);
            
            this.state.currentSentence++;
            
            if (this.state.currentSentence < this.sentences.length) {
                await this.addBotMessage(`Ready for sentence ${this.state.currentSentence + 1}?`);
                this.showQuickReplies([
                    { text: "Next sentence 🎤", action: () => this.showRecorder() }
                ]);
            } else {
                await this.completeQuiz();
            }
            
        } catch (error) {
            console.error('Upload failed:', error);
            await this.addBotMessage("Oops! There was an error. Let's try again.");
            this.showQuickReplies([
                { text: "Record again 🎤", action: () => this.showRecorder() }
            ]);
        }
    }
    
    /**
     * STEP 3: Complete quiz & check login
     */
    async completeQuiz() {
        await this.addBotMessage("Excellent! You've completed all 3 recordings. 🎉");
        await this.addBotMessage("Let me analyze your pronunciation...", 2000);
        
        // Aggregate flagged phonemes
        const allPhonemes = [];
        this.state.recordings.forEach(rec => {
            allPhonemes.push(...rec.flagged_phonemes);
        });
        this.state.flaggedPhonemes = [...new Set(allPhonemes)];
        
        await this.addBotMessage("Analysis complete!");
        
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
        const phonemes = this.state.flaggedPhonemes;
        
        await this.addBotMessage(`🎯 <strong>Analysis Results:</strong><br><br>I identified <strong>${phonemes.length} sound patterns</strong> that need attention:<br><br>${phonemes.join(', ')}`);
        
        await this.addBotMessage("The good news? These are <em>very common</em> challenges, and I can help you fix them!");
        
        // Fetch free lessons from WordPress
        await this.addBotMessage("Fetching your personalized free lessons...", 500);
        
        const lessons = await this.getLessons();
        
        if (lessons.length > 0) {
            await this.addBotMessage(`🎁 <strong>Your FREE Lesson:</strong>`);
            
            const firstLesson = lessons[0];
            await this.showLessonCard(firstLesson);
            
            await this.addBotMessage("This free lesson will help you with ONE sound.");
            await this.addBotMessage(`But what if you could master <strong>ALL ${phonemes.length} problem sounds</strong> plus every other aspect of English pronunciation?`);
            
            await this.showOffer();
        } else {
            await this.showOffer();
        }
    }
    
    async getLessons() {
        try {
            const response = await fetch(`${this.config.wpRestUrl}/lessons`);
            return await response.json();
        } catch (error) {
            console.error('Failed to fetch lessons:', error);
            return [];
        }
    }
    
    async showLessonCard(lesson) {
        await this.addBotMessage(`
            <div class="lesson-card" onclick="window.open('${lesson.permalink}', '_blank')">
                <h4>${lesson.title}</h4>
                <p>${lesson.excerpt}</p>
                <span class="lesson-badge">🎁 FREE</span>
            </div>
        `);
    }
    
    /**
     * STEP 5: Show offer (redirect to WordPress checkout)
     */
    async showOffer() {
        await this.addBotMessage("Now... I have something special for you. 🎁");
        await this.addBotMessage(`Your free lesson helps with ONE sound. But what if you could master <strong>ALL</strong> of them?`);
        
        const price = this.config.price;
        
        await this.addBotMessage(`
            <div class="oto-card">
                <h3>🎓 ${this.config.productName}</h3>
                <p class="product-desc">Complete pronunciation mastery course</p>
                <p class="pricing">
                    <span class="price-new">$${price}</span>
                </p>
                <p class="special">Get instant access to all lessons!</p>
            </div>
        `);
        
        await this.addBotMessage("Ready to get started?");
        
        this.showQuickReplies([
            { text: `Get Full Access for $${price} 🎉`, action: () => this.redirectToCheckout() },
            { text: "Let me think about it", action: () => this.handleThinking() }
        ]);
    }
    
    async redirectToCheckout() {
        await this.addBotMessage("Great choice! Redirecting you to checkout...");
        
        // Redirect to WordPress checkout page
        window.location.href = this.config.checkoutUrl;
    }
    
    async handleThinking() {
        await this.addBotMessage("I totally understand! This is an investment in yourself.");
        await this.addBotMessage("Just remember - you'll have access to every lesson immediately after purchase.");
        await this.addBotMessage("Ready to invest in your English pronunciation?");
        
        this.showQuickReplies([
            { text: `Yes! Get Full Access 🎉`, action: () => this.redirectToCheckout() },
            { text: "I'll stick with free lesson", action: () => this.showFreeLessonOnly() }
        ]);
    }
    
    async showFreeLessonOnly() {
        await this.addBotMessage("No problem! Your free lesson is always available.");
        await this.addBotMessage("When you're ready for the full course, just come back here!");
        
        const lessons = await this.getLessons();
        if (lessons.length > 0) {
            await this.showLessonCard(lessons[0]);
        }
    }
    
    /**
     * STEP 6: Welcome back paid user
     */
    async showWelcomeBack(access) {
        await this.addBotMessage(`Welcome back, ${access.display_name}! 👋`);
        await this.addBotMessage("You have full access. Let's continue your learning!");
        await this.showDashboard();
    }
    
    async showDashboard() {
        await this.addBotMessage("Loading your course...");
        
        const lessons = await this.getLessons();
        
        await this.addBotMessage(`📚 <strong>Your Course Dashboard</strong><br><br>You have access to ${lessons.length} lessons.`);
        
        // Show first 5 lessons
        for (const lesson of lessons.slice(0, 5)) {
            await this.showLessonCard(lesson);
            await new Promise(resolve => setTimeout(resolve, 300));
        }
        
        if (lessons.length > 5) {
            this.showQuickReplies([
                { text: "Show more lessons", action: () => this.showAllLessons(lessons) }
            ]);
        }
    }
    
    async showAllLessons(lessons) {
        for (const lesson of lessons.slice(5)) {
            await this.showLessonCard(lesson);
            await new Promise(resolve => setTimeout(resolve, 200));
        }
    }
    
    /**
     * UI Helper Methods
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
}

// Initialize when page loads
window.addEventListener('DOMContentLoaded', () => {
    new LeSAEpChat();
});
