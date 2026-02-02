/**
 * FLOSC Framework Chatbot Demo
 * Walks through: Freeline → Login → Offer → Sale → Content
 */

// State management
const State = {
    step: 'intro',
    quizId: null,
    recordings: {},
    currentSentence: 0,
    email: null,
    phone: null,
    flaggedPhonemes: [],
    countdownInterval: null,
    otoDuration: 3600, // 1 hour in seconds (configurable)
};

// Configuration
const Config = {
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

// DOM Elements
let chatMessages, typingIndicator, quickReplies, inputContainer;
let recorderArea, emailCapture, countdownOverlay;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    initElements();
    startConversation();
});

function initElements() {
    chatMessages = document.getElementById('chatMessages');
    typingIndicator = document.getElementById('typingIndicator');
    quickReplies = document.getElementById('quickReplies');
    inputContainer = document.getElementById('inputContainer');
    recorderArea = document.getElementById('recorderArea');
    emailCapture = document.getElementById('emailCapture');
    countdownOverlay = document.getElementById('countdownOverlay');
}

// Message Functions
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

// Quick Reply Buttons
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

// STEP 1: INTRO
async function startConversation() {
    State.quizId = generateQuizId();
    
    await addBotMessage("👋 Hi there! I'm your pronunciation coach.");
    await addBotMessage("I can help you identify and improve your English pronunciation in just 2 minutes.");
    await addBotMessage("Would you like a <strong>FREE pronunciation analysis</strong>?");
    
    showQuickReplies([
        { text: "Yes, let's do it! 🎤", action: startQuiz },
        { text: "Tell me more first", action: explainMore },
        { text: "No thanks", action: handleNo }
    ]);
}

async function explainMore() {
    await addBotMessage("Great question! Here's how it works:");
    await addBotMessage("1️⃣ You'll record yourself saying 3 simple sentences<br>2️⃣ I'll analyze your pronunciation using AI<br>3️⃣ You'll get personalized feedback + free lessons<br>4️⃣ Takes less than 2 minutes!");
    await addBotMessage("Sound good?");
    
    showQuickReplies([
        { text: "Yes, I'm ready! 🎤", action: startQuiz },
        { text: "No thanks", action: handleNo }
    ]);
}

let noCount = 0;
async function handleNo() {
    noCount++;
    
    if (noCount >= 3) {
        await addBotMessage("I understand! Maybe another time. Have a great day! 👋");
        await addBotMessage("(Just refresh the page if you change your mind!)");
        return;
    }
    
    await addBotMessage("No problem! Just so you know, this analysis is normally worth $150, but it's completely free for you today.");
    await addBotMessage("Are you sure you don't want to give it a quick try?");
    
    showQuickReplies([
        { text: "Okay, let's try it! 🎤", action: startQuiz },
        { text: "No, I'm sure", action: handleNo }
    ]);
}

// STEP 2: QUIZ
async function startQuiz() {
    State.step = 'quiz';
    State.currentSentence = 0;
    
    await addBotMessage("Awesome! Let's get started. 🎉");
    await addBotMessage("I'll show you 3 sentences. Just click the microphone and read each one out loud.");
    await addBotMessage("Ready for the first sentence?");
    
    showQuickReplies([
        { text: "I'm ready! 🎤", action: showRecorder }
    ]);
}

function showRecorder() {
    clearQuickReplies();
    
    const sentence = Config.sentences[State.currentSentence];
    document.getElementById('sentenceToRecord').textContent = sentence;
    document.getElementById('recordStatus').textContent = `Sentence ${State.currentSentence + 1} of ${Config.sentences.length}`;
    
    recorderArea.classList.remove('hidden');
    
    // Setup recorder
    setupRecorder();
}

let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;

function setupRecorder() {
    const recordBtn = document.getElementById('recordBtn');
    const recordIcon = document.querySelector('.record-icon');
    const recordText = document.querySelector('.record-text');
    const waveform = document.getElementById('waveform');
    const recordStatus = document.getElementById('recordStatus');
    
    recordBtn.onclick = async () => {
        if (!isRecording) {
            // Start recording
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = (e) => {
                    audioChunks.push(e.data);
                };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    State.recordings[State.currentSentence] = audioBlob;
                    
                    // Stop all tracks
                    stream.getTracks().forEach(track => track.stop());
                    
                    // Show next step
                    processRecording();
                };
                
                mediaRecorder.start();
                isRecording = true;
                
                recordBtn.classList.add('recording');
                recordIcon.textContent = '⏹️';
                recordText.textContent = 'Click to Stop';
                waveform.classList.remove('hidden');
                recordStatus.textContent = 'Recording... Click to stop';
                
            } catch (err) {
                alert('Please allow microphone access to record.');
                console.error(err);
            }
        } else {
            // Stop recording
            mediaRecorder.stop();
            isRecording = false;
            
            recordBtn.classList.remove('recording');
            recordIcon.textContent = '🎤';
            recordText.textContent = 'Click to Record';
            waveform.classList.add('hidden');
        }
    };
}

async function processRecording() {
    recorderArea.classList.add('hidden');
    
    await addBotMessage("✅ Got it! Analyzing...", 500);
    await addBotMessage("Nice work on that one!", 1500);
    
    State.currentSentence++;
    
    if (State.currentSentence < Config.sentences.length) {
        await addBotMessage(`Ready for sentence ${State.currentSentence + 1}?`);
        showQuickReplies([
            { text: "Next sentence 🎤", action: showRecorder }
        ]);
    } else {
        await completeQuiz();
    }
}

// STEP 3: EMAIL CAPTURE
async function completeQuiz() {
    await addBotMessage("Excellent! You've completed all 3 recordings. 🎉");
    await addBotMessage("I'm analyzing your pronunciation now...");
    
    // Simulate analysis
    State.flaggedPhonemes = ['/æ/', '/ð/', '/r/'];
    
    await addBotMessage("Analysis complete! I found some interesting patterns.", 2000);
    await addBotMessage("Where should I send your personalized results?");
    
    emailCapture.classList.remove('hidden');
    
    document.getElementById('submitContactBtn').onclick = submitContact;
}

async function submitContact() {
    const emailInput = document.getElementById('emailInput');
    const phoneInput = document.getElementById('phoneInput');
    
    State.email = emailInput.value;
    State.phone = phoneInput.value;
    
    if (!State.email || !State.email.includes('@')) {
        alert('Please enter a valid email address');
        return;
    }
    
    emailCapture.classList.add('hidden');
    
    addUserMessage(`📧 ${State.email}`);
    if (State.phone) {
        addUserMessage(`📱 ${State.phone}`);
    }
    
    await showResults();
}

// STEP 4: RESULTS + FREE LESSON
async function showResults() {
    State.step = 'results';
    
    await addBotMessage("Perfect! I've sent the full report to your email. 📧");
    await addBotMessage("But let me share the highlights right now...");
    await addBotMessage(`🎯 <strong>Analysis Results:</strong><br><br>I identified <strong>${State.flaggedPhonemes.length} sound patterns</strong> that need attention:<br><br>${State.flaggedPhonemes.join(', ')}`);
    await addBotMessage("The good news? These are <em>very common</em> challenges, and I can help you fix them!");
    await addBotMessage("🎁 <strong>Free Lesson for You:</strong><br><br>Based on your recording, I'm giving you FREE access to:<br><br>📚 'Mastering the Short A Sound (/æ/)'<br><br>This lesson will help you with words like 'cat', 'mat', 'bad', etc.");
    
    showQuickReplies([
        { text: "Show me the free lesson!", action: showFreeLesson }
    ]);
}

async function showFreeLesson() {
    await addBotMessage("Here's a preview of your free lesson:");
    await addBotMessage(`
        <div style="background: #f0f0ff; padding: 15px; border-radius: 8px; margin: 10px 0;">
            <strong>📚 Mastering the Short A Sound</strong><br><br>
            <strong>The Challenge:</strong> The /æ/ sound doesn't exist in many languages...<br><br>
            <strong>The Fix:</strong> Open your mouth wider than you think...<br><br>
            <strong>Practice:</strong> cat, bat, hat, mat...<br><br>
            <em>[This is just a preview - full lesson includes audio examples, practice exercises, and more!]</em>
        </div>
    `);
    
    await showOffer();
}

// STEP 5: OFFER (OTO)
async function showOffer() {
    State.step = 'offer';
    
    await addBotMessage("Now, I have something special for you...");
    await addBotMessage("You did the quiz, so you clearly care about your pronunciation. 👏");
    await addBotMessage(`Most people struggle with <strong>${State.flaggedPhonemes.length} or more</strong> sounds. Your free lesson helps with ONE of them.`);
    await addBotMessage("But what if you could master <strong>ALL</strong> of them?");
    await addBotMessage(`
        <div style="background: linear-gradient(135deg, #dcfce7, #d1fae5); padding: 20px; border-radius: 12px; border: 3px solid #10b981;">
            <h3 style="margin: 0 0 15px 0; color: #065f46;">🎁 SPECIAL ONE-TIME OFFER</h3>
            <p style="font-size: 18px; margin: 10px 0;"><strong>${Config.productName}</strong></p>
            <p style="margin: 10px 0;">Complete Course + Personalized Training</p>
            <p style="margin: 15px 0;">
                <span style="text-decoration: line-through; color: #6b7280; font-size: 20px;">$${Config.fullPrice}</span>
                <span style="color: #059669; font-size: 32px; font-weight: bold; margin-left: 15px;">$${Config.otoPrice}</span>
            </p>
            <p style="color: #dc2626; font-weight: bold; margin: 10px 0;">Save ${Config.discount}% - $${Config.fullPrice - Config.otoPrice} OFF!</p>
            <p style="font-size: 13px; color: #6b7280; margin-top: 15px;">⏰ This offer expires soon!</p>
        </div>
    `);
    
    await addBotMessage("How long should this special offer last?");
    
    showQuickReplies([
        { text: "1 hour ⏰", action: () => setOfferDuration('1hour') },
        { text: "1 day 📅", action: () => setOfferDuration('1day') },
        { text: "7 days 🗓️", action: () => setOfferDuration('7days') }
    ]);
}

async function setOfferDuration(duration) {
    State.otoDuration = Config.otoDurations[duration];
    
    const durationText = {
        '1hour': '1 hour',
        '1day': '24 hours',
        '7days': '7 days'
    }[duration];
    
    await addBotMessage(`⏰ Offer set to expire in ${durationText}!`);
    await addBotMessage("Ready to get started?");
    
    // Start countdown
    startCountdown(State.otoDuration);
    
    showQuickReplies([
        { text: `Yes! Get ${Config.discount}% OFF 🎉`, action: processPurchase },
        { text: "Let me think about it", action: handleThinking }
    ]);
}

function startCountdown(seconds) {
    countdownOverlay.classList.remove('hidden');
    
    const endTime = Date.now() + (seconds * 1000);
    
    State.countdownInterval = setInterval(() => {
        const remaining = Math.max(0, endTime - Date.now());
        const hours = Math.floor(remaining / 3600000);
        const minutes = Math.floor((remaining % 3600000) / 60000);
        const secs = Math.floor((remaining % 60000) / 1000);
        
        document.getElementById('hours').textContent = String(hours).padStart(2, '0');
        document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
        document.getElementById('seconds').textContent = String(secs).padStart(2, '0');
        
        if (remaining === 0) {
            clearInterval(State.countdownInterval);
            offerExpired();
        }
    }, 1000);
}

async function offerExpired() {
    countdownOverlay.classList.add('hidden');
    await addBotMessage("⏰ The special offer has expired!");
    await addBotMessage("But don't worry - you still have access to your FREE lesson. 🎁");
}

async function handleThinking() {
    await addBotMessage("I totally understand! This is an investment in yourself.");
    await addBotMessage("Just remember - this special ${Config.discount}% discount is only available RIGHT NOW.");
    await addBotMessage("Once you leave, it goes back to the full $${Config.fullPrice} price.");
    await addBotMessage("Ready to claim your discount?");
    
    showQuickReplies([
        { text: `Yes! Get ${Config.discount}% OFF 🎉`, action: processPurchase },
        { text: "I'll stick with the free lesson", action: freeOnly }
    ]);
}

async function freeOnly() {
    await addBotMessage("No problem! You'll still have access to your free lesson.");
    await addBotMessage("I'll also send you some reminder emails in case you change your mind. 📧");
    await addBotMessage("Enjoy your free lesson, and feel free to come back anytime!");
    
    showQuickReplies([
        { text: "Access my free lesson", action: showDashboard }
    ]);
}

// STEP 6: PURCHASE
async function processPurchase() {
    State.step = 'purchase';
    
    await addBotMessage("Excellent choice! 🎉");
    await addBotMessage("I'm redirecting you to our secure checkout...");
    await addBotMessage(`
        <div style="background: white; padding: 20px; border-radius: 8px; border: 2px solid #e5e7eb; text-align: center;">
            <p style="margin-bottom: 15px;">💳 <strong>Secure Checkout</strong></p>
            <p style="margin-bottom: 15px; color: #6b7280; font-size: 14px;">This is a demo, so we'll simulate the purchase process</p>
            <button id="simulatePurchaseBtn" style="padding: 12px 24px; background: #10b981; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                Complete Purchase ($${Config.otoPrice})
            </button>
        </div>
    `);
    
    setTimeout(() => {
        document.getElementById('simulatePurchaseBtn').onclick = completePurchase;
    }, 100);
}

async function completePurchase() {
    await addBotMessage("✅ Payment successful!");
    await addBotMessage("🎉 <strong>Welcome to ${Config.productName}!</strong>");
    await addBotMessage("You now have FULL ACCESS to all lessons, exercises, and personalized training.");
    await addBotMessage("I've sent a confirmation email to ${State.email} with your login details.");
    
    if (State.countdownInterval) {
        clearInterval(State.countdownInterval);
        countdownOverlay.classList.add('hidden');
    }
    
    showDashboard();
}

// STEP 7: CONTENT ACCESS
async function showDashboard() {
    State.step = 'dashboard';
    
    await addBotMessage(`
        <div style="background: #f0f9ff; padding: 20px; border-radius: 12px; border: 2px solid #0ea5e9;">
            <h3 style="margin: 0 0 15px 0;">📚 Your Course Dashboard</h3>
            <p style="margin: 10px 0;"><strong>Available Lessons:</strong></p>
            <ul style="list-style: none; padding: 0; margin: 15px 0;">
                <li style="margin: 8px 0;">✅ Mastering the Short A Sound (/æ/) - FREE</li>
                ${State.step === 'dashboard' && !State.countdownInterval ? `
                    <li style="margin: 8px 0;">✅ The TH Sound (/θ/)</li>
                    <li style="margin: 8px 0;">✅ The R Sound Mastery</li>
                    <li style="margin: 8px 0;">✅ Vowel Sounds Complete Guide</li>
                    <li style="margin: 8px 0;">✅ Connected Speech Training</li>
                    <li style="margin: 8px 0;">✅ ... and 25 more lessons!</li>
                ` : `
                    <li style="margin: 8px 0; color: #9ca3af;">🔒 Additional lessons (upgrade to unlock)</li>
                `}
            </ul>
            <p style="margin-top: 15px; font-size: 13px; color: #6b7280;">
                This is where your actual course content would appear in the real system.
            </p>
        </div>
    `);
    
    await addBotMessage("That's the complete FLOSC framework! 🎉");
    await addBotMessage(`
        <strong>What you just experienced:</strong><br><br>
        ✅ <strong>Freeline</strong> - Free quiz with value<br>
        ✅ <strong>Login</strong> - Email capture<br>
        ✅ <strong>Offer</strong> - Personalized OTO with countdown<br>
        ✅ <strong>Sale</strong> - Simulated checkout<br>
        ✅ <strong>Content</strong> - Dashboard access<br><br>
        <em>This is the framework in action!</em>
    `);
    
    showQuickReplies([
        { text: "Start over 🔄", action: () => location.reload() }
    ]);
}

// Utilities
function generateQuizId() {
    return 'quiz_' + Math.random().toString(36).substr(2, 9);
}
