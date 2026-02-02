// Freeline Quiz Logic
let currentSentenceIndex = 0;
let sentences = [];
let recordings = [];
let mediaRecorder = null;
let audioChunks = [];
let noCounter = 0;
const MAX_NO_ATTEMPTS = 3;
const sessionId = generateSessionId();

// Initialize
document.addEventListener('DOMContentLoaded', async () => {
    await loadSentences();
});

function generateSessionId() {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

async function loadSentences() {
    try {
        const response = await fetch('/api/freeline/sentences');
        const data = await response.json();
        sentences = data.sentences;
        console.log('Loaded sentences:', sentences);
    } catch (error) {
        console.error('Error loading sentences:', error);
        alert('Failed to load quiz. Please refresh the page.');
    }
}

function startQuiz() {
    hideScreen('welcome-screen');
    showScreen('quiz-screen');
    loadCurrentSentence();
    updateProgress();
}

function handleNo() {
    noCounter++;
    const attemptsLeft = MAX_NO_ATTEMPTS - noCounter;
    
    if (noCounter >= MAX_NO_ATTEMPTS) {
        document.getElementById('welcome-screen').innerHTML = `
            <div class="blocked-message">
                <h2>😔 We're Sorry!</h2>
                <p>It seems you're not interested right now. That's okay!</p>
                <p>Feel free to come back anytime when you're ready to improve your pronunciation.</p>
                <button class="btn btn-secondary" onclick="location.reload()">Start Over</button>
            </div>
        `;
    } else {
        const counter = document.getElementById('no-counter');
        counter.style.display = 'block';
        document.getElementById('attempts-left').textContent = `(${attemptsLeft} more decline${attemptsLeft > 1 ? 's' : ''} and you'll be blocked)`;
    }
}

function loadCurrentSentence() {
    const sentence = sentences[currentSentenceIndex];
    const carousel = document.getElementById('sentence-carousel');
    
    carousel.innerHTML = `
        <div class="sentence-card">
            <div class="sentence-number">Sentence ${currentSentenceIndex + 1} of ${sentences.length}</div>
            <div class="sentence-text">"${sentence.sentence_text}"</div>
        </div>
    `;
}

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        
        mediaRecorder.ondataavailable = (event) => {
            audioChunks.push(event.data);
        };
        
        mediaRecorder.onstop = async () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const reader = new FileReader();
            reader.readAsDataURL(audioBlob);
            reader.onloadend = () => {
                const base64Audio = reader.result;
                recordings[currentSentenceIndex] = {
                    sentence_id: sentences[currentSentenceIndex].sentence_id,
                    audio_base64: base64Audio
                };
                document.getElementById('next-btn').disabled = false;
                document.getElementById('recording-status').innerHTML = 
                    '<div class="success">✅ Recording saved!</div>';
            };
        };
        
        mediaRecorder.start();
        
        document.getElementById('record-btn').disabled = true;
        document.getElementById('stop-btn').disabled = false;
        document.getElementById('recording-status').innerHTML = 
            '<div class="recording">🔴 Recording...</div>';
        
    } catch (error) {
        console.error('Error starting recording:', error);
        alert('Please allow microphone access to continue.');
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        mediaRecorder.stream.getTracks().forEach(track => track.stop());
        
        document.getElementById('record-btn').disabled = false;
        document.getElementById('stop-btn').disabled = true;
    }
}

function nextSentence() {
    currentSentenceIndex++;
    
    if (currentSentenceIndex < sentences.length) {
        loadCurrentSentence();
        updateProgress();
        document.getElementById('next-btn').disabled = true;
        document.getElementById('recording-status').innerHTML = '';
    } else {
        // All sentences recorded
        hideScreen('quiz-screen');
        showScreen('submit-screen');
    }
}

function updateProgress() {
    const progress = ((currentSentenceIndex) / sentences.length) * 100;
    document.getElementById('progress-fill').style.width = progress + '%';
}

async function submitQuiz(event) {
    event.preventDefault();
    
    const email = document.getElementById('email-input').value;
    
    hideScreen('submit-screen');
    showScreen('processing-screen');
    
    try {
        const response = await fetch('/api/freeline/submit-quiz', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                recordings: recordings,
                email: email,
                session_id: sessionId
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            // Success - show results screen
            setTimeout(() => {
                hideScreen('processing-screen');
                showScreen('results-screen');
            }, 2000);
        } else {
            throw new Error(result.detail || 'Submission failed');
        }
        
    } catch (error) {
        console.error('Submission error:', error);
        alert('Failed to submit quiz. Please try again.');
        hideScreen('processing-screen');
        showScreen('submit-screen');
    }
}

function hideScreen(screenId) {
    document.getElementById(screenId).classList.remove('active');
}

function showScreen(screenId) {
    document.getElementById(screenId).classList.add('active');
}
