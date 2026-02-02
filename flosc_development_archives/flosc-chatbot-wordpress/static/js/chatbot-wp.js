/**
 * FLOSC Chatbot - WordPress Integrated Version
 * Connects to dainis.net/lesaep/ for real content
 */

// Configuration
const Config = {
    wpSiteUrl: 'https://dainis.net',
    wpCategory: 'lesaep',
    sentences: [
        "The cat sat on the mat",
        "She sells seashells by the seashore",
        "How now brown cow"
    ],
    productName: "English Pronunciation Mastery",
    fullPrice: 575,
    otoPrice: 144,
    discount: 75,
    otoDurations: {
        '1hour': 3600,
        '1day': 86400,
        '7days': 604800
    }
};

// Global instances
let wpApi, session;
let chatMessages, typingIndicator, quickReplies;
let recorderArea, emailCapture, countdownOverlay;
let mediaRecorder, audioChunks, isRecording;
let currentSentenceIndex = 0;
let noCount = 0;

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await initApp();
});

async function initApp() {
    // Initialize WordPress API
    wpApi = new WordPressAPI(Config.wpSiteUrl, Config.wpCategory);
    const connected = await wpApi.init();
    
    if (connected) {
        addSystemMessage('✅ Connected to WordPress: dainis.net/lesaep/');
    } else {
        addSystemMessage('⚠️ Using demo content (WordPress not connected yet)');
    }
    
    // Initialize session
    session = new FLOSCSession();
    
    // Initialize DOM elements
    initElements();
    
    // Check if returning user
    if (session.session.completed && session.session.email) {
        await welcomeBackUser();
    } else {
        await startConversation();
    }
}

function initElements() {
    chatMessages = document.getElementById('chatMessages');
    typingIndicator = document.getElementById('typingIndicator');
    quickReplies = document.getElementById('quickReplies');
    recorderArea = document.getElementById('recorderArea');
    emailCapture = document.getElementById('emailCapture');
    countdownOverlay = document.getElementById('countdownOverlay');
}

function addSystemMessage(text) {
    const div = document.createElement('div');
    div.style.cssText = 'padding: 8px; background: #f0f0f0; border-radius: 8px; font-size: 12px; color: #666; margin-bottom: 10px; text-align: center;';
    div.textContent = text;
    chatMessages.appendChild(div);
    scrollToBottom();
}

// Message functions
function addBotMessage(text, delay = 1000) {
    return new Promise(resolve => {
        showTyping();
        
        setTimeout(() => {
            hideTyping();
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message bot';
            messageDiv.innerHTML = `
                <div class="message-avatar">🤖</div>
                <div class="message-content">${text}</div>
            `;
            
            chatMessages.appendChild(messageDiv);
            scrollToBottom();
            resolve();
        }, delay);
    });
}

function addUserMessage(text) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'message user';
    messageDiv.innerHTML = `
        <div class="message-avatar">👤</div>
        <div class="message-content">${text}</div>
    `;
    
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
}

function showTyping() {
    typingIndicator.classList.remove('hidden');
    scrollToBottom();
}

function hideTyping() {
    typingIndicator.classList.add('hidden');
}

function scrollToBottom() {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// Quick replies
function showQuickReplies(options) {
    quickReplies.innerHTML = '';
    
    options.forEach(option => {
        const btn = document.createElement('button');
        btn.className = 'quick-reply-btn';
        btn.textContent = option.text;
        btn.onclick = () => {
            addUserMessage(option.text);
            clearQuickReplies();
            option.action();
        };
        quickReplies.appendChild(btn);
    });
}

function clearQuickReplies() {
    quickReplies.innerHTML = '';
}

// Returning user flow
async function welcomeBackUser() {
    const email = session.session.email;
    const accessLevel = session.getAccessLevel();
    
    await addBotMessage(`Welcome back, ${email}! 👋`);
    
    if (accessLevel === 'full') {
        await addBotMessage("You have FULL ACCESS to all lessons. Let's continue your learning!");
        await showDashboard();
    } else if (accessLevel === 'free') {
        await addBotMessage("You have access to your FREE lessons!");
        
        if (!session.isOTOExpired()) {
            await addBotMessage("Your special offer is still active! 🎉");
            await showOffer();
        } else {
            await showDashboard();
        }
    }
    
    showQuickReplies([
        { text: "Start a new quiz 🔄", action: resetAndStart }
    ]);
}

function resetAndStart() {
    session.reset();
    location.reload();
}

// STEP 1: INTRO
async function startConversation() {
    session.startQuiz();
    
    await addBotMessage("👋 Hi! I'm your AI pronunciation coach.");
    await addBotMessage("I can analyze your English pronunciation in just 2 minutes using real WordPress content from dainis.net/lesaep/");
    await addBotMessage("Want a <strong>FREE pronunciation analysis</strong>?");
    
    showQuickReplies([
        { text: "Yes, let's do it! 🎤", action: startQuiz },
        { text: "Tell me more", action: explainMore },
        { text: "No thanks", action: handleNo }
    ]);
}

async function explainMore() {
    await addBotMessage("Here's how it works:");
    await addBotMessage("1️⃣ Record yourself saying 3 sentences<br>2️⃣ AI analyzes your pronunciation<br>3️⃣ Get personalized lessons from WordPress<br>4️⃣ Takes 2 minutes!");
    
    const lessonCount = await wpApi.getLessonCount();
    await addBotMessage(`We have <strong>${lessonCount} lessons</strong> ready for you on dainis.net/lesaep/`);
    
    showQuickReplies([
        { text: "I'm ready! 🎤", action: startQuiz },
        { text: "No thanks", action: handleNo }
    ]);
}

async function handleNo() {
    noCount++;
    
    if (noCount >= 3) {
        await addBotMessage("No problem! Come back anytime. 👋");
        return;
    }
    
    await addBotMessage("This analysis is worth $150, but FREE for you today.");
    await addBotMessage("Plus, you'll get instant access to WordPress lessons tailored to YOUR pronunciation needs.");
    
    showQuickReplies([
        { text: "Okay, let's try! 🎤", action: startQuiz },
        { text: "No, I'm sure", action: handleNo }
    ]);
}

// STEP 2: QUIZ
async function startQuiz() {
    currentSentenceIndex = 0;
    
    await addBotMessage("Awesome! 🎉");
    await addBotMessage("I'll show you 3 sentences. Read each one out loud.");
    await addBotMessage("Ready?");
    
    showQuickReplies([
        { text: "I'm ready! 🎤", action: showRecorder }
    ]);
}

function showRecorder() {
    clearQuickReplies();
    
    const sentence = Config.sentences[currentSentenceIndex];
    document.getElementById('sentenceToRecord').textContent = sentence;
    document.getElementById('recordStatus').textContent = 
        `Sentence ${currentSentenceIndex + 1} of ${Config.sentences.length}`;
    
    recorderArea.classList.remove('hidden');
    setupRecorder();
}

function setupRecorder() {
    const recordBtn = document.getElementById('recordBtn');
    const recordIcon = document.querySelector('.record-icon');
    const recordText = document.querySelector('.record-text');
    const waveform = document.getElementById('waveform');
    
    recordBtn.onclick = async () => {
        if (!isRecording) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = () => {
                    stream.getTracks().forEach(track => track.stop());
                    processRecording();
                };
                
                mediaRecorder.start();
                isRecording = true;
                
                recordBtn.classList.add('recording');
                recordIcon.textContent = '⏹️';
                recordText.textContent = 'Stop';
                waveform.classList.remove('hidden');
            } catch (err) {
                alert('Please allow microphone access');
            }
        } else {
            mediaRecorder.stop();
            isRecording = false;
            
            recordBtn.classList.remove('recording');
            recordIcon.textContent = '🎤';
            recordText.textContent = 'Record';
            waveform.classList.add('hidden');
        }
    };
}

async function processRecording() {
    recorderArea.classList.add('hidden');
    
    await addBotMessage("✅ Analyzing...", 500);
    
    // Simulate analysis (in real version, this sends to backend)
    await addBotMessage("Nice work!", 1500);
    
    currentSentenceIndex++;
    
    if (currentSentenceIndex < Config.sentences.length) {
        await addBotMessage(`Ready for sentence ${currentSentenceIndex + 1}?`);
        showQuickReplies([
            { text: "Next 🎤", action: showRecorder }
        ]);
    } else {
        await completeQuiz();
    }
}

// STEP 3: EMAIL CAPTURE
async function completeQuiz() {
    await addBotMessage("Excellent! All 3 recordings complete. 🎉");
    await addBotMessage("Analyzing your pronunciation...");
    
    // Simulate analysis
    const flaggedPhonemes = ['/æ/', '/ð/', '/r/'];
    session.completeQuiz(flaggedPhonemes);
    
    await addBotMessage("Analysis complete!", 2000);
    await addBotMessage("Where should I send your results?");
    
    emailCapture.classList.remove('hidden');
    document.getElementById('submitContactBtn').onclick = submitContact;
}

async function submitContact() {
    const email = document.getElementById('emailInput').value;
    const phone = document.getElementById('phoneInput').value;
    
    if (!email || !email.includes('@')) {
        alert('Please enter a valid email');
        return;
    }
    
    session.setContact(email, phone);
    
    // Sync with WordPress
    await session.syncWithWordPress(Config.wpSiteUrl);
    
    emailCapture.classList.add('hidden');
    addUserMessage(`📧 ${email}`);
    
    await showResults();
}

// STEP 4: RESULTS + FREE LESSONS (WordPress)
async function showResults() {
    await addBotMessage("Perfect! Full report sent to your email. 📧");
    await addBotMessage("Here are the highlights...");
    
    const phonemes = session.session.flaggedPhonemes;
    await addBotMessage(`🎯 <strong>Analysis:</strong><br><br>Found <strong>${phonemes.length} sounds</strong> that need work:<br><br>${phonemes.join(', ')}`);
    
    // Get recommended lessons from WordPress
    await addBotMessage("Fetching your personalized lessons from WordPress...", 500);
    
    const recommendedLessons = await session.getRecommendedLessons(wpApi);
    
    if (recommendedLessons.length > 0) {
        await addBotMessage(`🎁 <strong>Your FREE Lessons:</strong><br><br>Based on your pronunciation, I'm giving you:`);
        
        for (const lesson of recommendedLessons) {
            await addBotMessage(`
                <div style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 10px 0;">
                    <strong>📚 ${lesson.title}</strong><br>
                    ${lesson.excerpt}
                    <small style="color: #666;">From: dainis.net/lesaep/</small>
                </div>
            `);
        }
    } else {
        await addBotMessage("🎁 You'll get personalized free lessons once content is added to WordPress!");
    }
    
    showQuickReplies([
        { text: "Show me the lessons!", action: showFreeLessonPreview }
    ]);
}

async function showFreeLessonPreview() {
    const lessons = await wpApi.getLessons(true);  // free only
    
    if (lessons.length > 0) {
        const firstLesson = lessons[0];
        await addBotMessage(`
            <div style="background: #fff; padding: 20px; border-radius: 12px; border: 2px solid #e5e7eb;">
                <h3 style="margin: 0 0 15px 0;">${firstLesson.title}</h3>
                ${firstLesson.content}
                <p style="margin-top: 15px; font-size: 12px; color: #666;">
                    <a href="${firstLesson.permalink}" target="_blank">View full lesson →</a>
                </p>
            </div>
        `);
    }
    
    await showOffer();
}

// STEP 5: OFFER
async function showOffer() {
    await addBotMessage("Now... something special for you.");
    
    const totalLessons = await wpApi.getLessonCount();
    const freeLessons = await wpApi.getLessonCount(true);
    
    await addBotMessage(`You have ${freeLessons} free lesson(s). But there are <strong>${totalLessons} total lessons</strong> in the WordPress system!`);
    await addBotMessage("What if you could access <strong>ALL of them</strong>?");
    
    await addBotMessage(`
        <div style="background: linear-gradient(135deg, #dcfce7, #d1fae5); padding: 20px; border-radius: 12px; border: 3px solid #10b981;">
            <h3 style="margin: 0 0 10px 0; color: #065f46;">🎁 ONE-TIME OFFER</h3>
            <p style="font-size: 18px; margin: 10px 0;"><strong>${Config.productName}</strong></p>
            <p>Full access to all ${totalLessons} WordPress lessons</p>
            <p style="margin: 15px 0;">
                <span style="text-decoration: line-through; color: #6b7280; font-size: 20px;">$${Config.fullPrice}</span>
                <span style="color: #059669; font-size: 32px; font-weight: bold; margin-left: 15px;">$${Config.otoPrice}</span>
            </p>
            <p style="color: #dc2626; font-weight: bold;">Save ${Config.discount}% - Only $${Config.otoPrice}!</p>
        </div>
    `);
    
    await addBotMessage("How long should this offer last?");
    
    showQuickReplies([
        { text: "1 hour ⏰", action: () => setOfferDuration('1hour') },
        { text: "1 day 📅", action: () => setOfferDuration('1day') },
        { text: "7 days 🗓️", action: () => setOfferDuration('7days') }
    ]);
}

async function setOfferDuration(duration) {
    const seconds = Config.otoDurations[duration];
    session.setOTO(seconds);
    
    const labels = { '1hour': '1 hour', '1day': '24 hours', '7days': '7 days' };
    await addBotMessage(`⏰ Offer expires in ${labels[duration]}!`);
    
    startCountdown(seconds);
    
    showQuickReplies([
        { text: `Get ${Config.discount}% OFF 🎉`, action: processPurchase },
        { text: "Let me think", action: handleThinking }
    ]);
}

function startCountdown(seconds) {
    countdownOverlay.classList.remove('hidden');
    const endTime = Date.now() + (seconds * 1000);
    
    const interval = setInterval(() => {
        const remaining = Math.max(0, endTime - Date.now());
        const h = Math.floor(remaining / 3600000);
        const m = Math.floor((remaining % 3600000) / 60000);
        const s = Math.floor((remaining % 60000) / 1000);
        
        document.getElementById('hours').textContent = String(h).padStart(2, '0');
        document.getElementById('minutes').textContent = String(m).padStart(2, '0');
        document.getElementById('seconds').textContent = String(s).padStart(2, '0');
        
        if (remaining === 0) {
            clearInterval(interval);
            offerExpired();
        }
    }, 1000);
}

async function offerExpired() {
    countdownOverlay.classList.add('hidden');
    await addBotMessage("⏰ Offer expired! But you still have your free lessons.");
}

async function handleThinking() {
    await addBotMessage("I understand! This is an investment.");
    await addBotMessage(`Remember - this ${Config.discount}% discount is ONLY available now. After you leave, it's back to $${Config.fullPrice}.`);
    
    showQuickReplies([
        { text: "Get discount! 🎉", action: processPurchase },
        { text: "Free lessons only", action: freeOnly }
    ]);
}

async function freeOnly() {
    await addBotMessage("No problem! Your free lessons are ready.");
    await showDashboard();
}

// STEP 6: PURCHASE
async function processPurchase() {
    await addBotMessage("Excellent! 🎉");
    await addBotMessage("Redirecting to checkout...");
    await addBotMessage(`
        <div style="background: white; padding: 20px; border-radius: 8px; text-align: center;">
            <p style="margin-bottom: 15px;">💳 Secure Checkout</p>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">Demo mode - simulating purchase</p>
            <button id="simulateBtn" style="padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                Complete Purchase ($${Config.otoPrice})
            </button>
        </div>
    `);
    
    setTimeout(() => {
        document.getElementById('simulateBtn').onclick = completePurchase;
    }, 100);
}

async function completePurchase() {
    session.markPaid();
    await session.syncWithWordPress(Config.wpSiteUrl);
    
    await addBotMessage("✅ Payment successful!");
    await addBotMessage(`🎉 Welcome to ${Config.productName}!`);
    
    countdownOverlay.classList.add('hidden');
    
    await showDashboard();
}

// STEP 7: CONTENT (WordPress)
async function showDashboard() {
    await addBotMessage("Loading your dashboard from WordPress...", 500);
    
    const accessLevel = session.getAccessLevel();
    const lessons = accessLevel === 'full' 
        ? await wpApi.getLessons()
        : await wpApi.getLessons(true);
    
    const accessBadge = accessLevel === 'full' 
        ? '<div style="background: #10b981; color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px;"><strong>✅ FULL ACCESS</strong></div>'
        : '<div style="background: #3b82f6; color: white; padding: 10px; border-radius: 8px; margin-bottom: 15px;"><strong>🎁 FREE ACCESS</strong> <a href="#" style="color: white; text-decoration: underline;">Upgrade for all lessons</a></div>';
    
    let lessonsHtml = '';
    for (const lesson of lessons) {
        const canAccess = session.canAccessLesson(lesson);
        const badge = lesson.isFree ? '🎁 FREE' : (canAccess ? '✅' : '🔒 LOCKED');
        
        lessonsHtml += `
            <div style="background: #f9fafb; padding: 15px; border-radius: 8px; margin: 10px 0; border: 2px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 8px 0;">${lesson.title}</h4>
                        ${lesson.excerpt}
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">
                            ${lesson.phonemeGroup ? `Sound: ${lesson.phonemeGroup}` : ''}
                        </p>
                    </div>
                    <div style="margin-left: 15px;">
                        <span style="background: ${canAccess ? '#10b981' : '#9ca3af'}; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; white-space: nowrap;">
                            ${badge}
                        </span>
                    </div>
                </div>
                ${canAccess ? `<a href="${lesson.permalink}" target="_blank" style="color: #3b82f6;">View lesson →</a>` : ''}
            </div>
        `;
    }
    
    await addBotMessage(`
        <div style="background: #fff; padding: 20px; border-radius: 12px; border: 2px solid #e5e7eb;">
            <h3 style="margin: 0 0 15px 0;">📚 Your Course Dashboard</h3>
            ${accessBadge}
            <p style="margin-bottom: 15px;"><strong>Available Lessons (${lessons.length}):</strong></p>
            ${lessonsHtml}
            <p style="margin-top: 20px; font-size: 13px; color: #666;">
                Content source: <code>${Config.wpSiteUrl}/${Config.wpCategory}/</code>
            </p>
        </div>
    `);
    
    await addBotMessage("🎉 <strong>That's the complete FLOSC framework!</strong><br><br>Connected to WordPress, ready for your content.");
    
    showQuickReplies([
        { text: "Start over 🔄", action: resetAndStart }
    ]);
}
