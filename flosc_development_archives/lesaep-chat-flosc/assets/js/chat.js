/**
 * LeSAEp Chat FLOSC
 * Chat-only funnel in a single window.
 *
 * Notes:
 * - Sentences are configurable from WP admin (localized into LESAEP.sentences)
 * - Persists progress in localStorage so refresh doesn't lose state
 * - Currency symbol configurable from WP admin
 */

class LeSAEpChat {
  constructor() {
    this.config = window.LESAEP || {};

    this.storageKey = 'lesaep_chat_state_v1';
    this.state = Object.assign(
      {
        recordings: [],
        currentSentence: 0,
        flaggedPhonemes: [],
        noCount: 0,
        email: '',
        quizComplete: false,
        pendingCheckout: false
      },
      this.loadState()
    );

    this.sentences = Array.isArray(this.config.sentences) && this.config.sentences.length
      ? this.config.sentences
      : [
          'The cat sat on the mat',
          'She sells seashells by the seashore',
          'How now brown cow'
        ];

    this.mediaRecorder = null;
    this.audioChunks = [];
    this.isRecording = false;

    this.init();
  }

  loadState() {
    try {
      const raw = window.localStorage.getItem(this.storageKey);
      if (!raw) return {};
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
      return {};
    }
  }

  saveState() {
    try {
      window.localStorage.setItem(this.storageKey, JSON.stringify(this.state));
    } catch {
      // ignore
    }
  }

  clearState() {
    try {
      window.localStorage.removeItem(this.storageKey);
    } catch {
      // ignore
    }
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

    // Access status
    const access = await this.checkAccess();

    // If coming back from checkout, force an access refresh and react
    if (this.state.pendingCheckout) {
      await this.addBotMessage('Checking your access…', 300);
      const refreshed = await this.checkAccess();
      if (refreshed && refreshed.has_paid_access) {
        this.state.pendingCheckout = false;
        this.saveState();
        await this.showWelcomeBack(refreshed);
        return;
      }
      // Not paid (yet) – show a gentle nudge and continue
      await this.addBotMessage('If you completed checkout, refresh this page in a moment. Your access will appear automatically.');
    }

    // Resume logic
    if (access.has_paid_access) {
      await this.showWelcomeBack(access);
      return;
    }

    if (this.state.quizComplete) {
      // User refreshed after completing quiz
      if (!this.config.isLoggedIn) {
        await this.addBotMessage('Welcome back. Your analysis is ready, but login is required to display results.');
        this.showQuickReplies([
          {
            text: 'Login to See Results 🔐',
            action: () => (window.location.href = this.config.loginUrl)
          }
        ]);
        return;
      }
      await this.showResults();
      return;
    }

    // If mid-quiz, resume at current sentence
    if (this.state.currentSentence > 0 && this.state.currentSentence < this.sentences.length) {
      await this.addBotMessage('Welcome back — continuing where you left off.');
      await this.addBotMessage(`We are on sentence ${this.state.currentSentence + 1} of ${this.sentences.length}.`);
      this.showQuickReplies([{ text: 'Continue 🎤', action: () => this.showRecorder() }]);
      return;
    }

    // Default: start
    await this.startConversation();
  }

  async checkAccess() {
    try {
      const response = await fetch(`${this.config.wpRestUrl}/access`, { credentials: 'same-origin' });
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
    await this.addBotMessage('Want a <strong>FREE pronunciation analysis</strong>?');

    this.showQuickReplies([
      { text: "Yes, let's do it! 🎤", action: () => this.preQuizEmailStep() },
      { text: 'Tell me more first', action: () => this.explainMore() },
      { text: 'No thanks', action: () => this.handleNo() }
    ]);
  }

  async explainMore() {
    await this.addBotMessage('Here’s how it works:');
    await this.addBotMessage('1️⃣ Record a few sentences<br>2️⃣ AI analyzes your pronunciation<br>3️⃣ You get personalized feedback + a free lesson');

    this.showQuickReplies([
      { text: "I'm ready! 🎤", action: () => this.preQuizEmailStep() },
      { text: 'No thanks', action: () => this.handleNo() }
    ]);
  }

  async handleNo() {
    this.state.noCount++;
    this.saveState();

    if (this.state.noCount >= 3) {
      await this.addBotMessage('Understood. Have a great day. 👋');
      return;
    }

    await this.addBotMessage('No problem. This takes about 2 minutes and is free today.');
    await this.addBotMessage('Want to try it?');

    this.showQuickReplies([
      { text: "Okay, let's try it! 🎤", action: () => this.preQuizEmailStep() },
      { text: "No, I'm sure", action: () => this.handleNo() }
    ]);
  }

  /**
   * Optional email capture (stored in localStorage and passed to backend).
   */
  async preQuizEmailStep() {
    await this.addBotMessage('Optional: add your email so you can receive results even if you refresh.');

    this.showQuickReplies([
      { text: 'Skip email', action: () => this.startQuiz() },
      { text: 'Enter email', action: () => this.showEmailCapture() }
    ]);
  }

  async showEmailCapture() {
    this.clearQuickReplies();

    await this.addBotMessage(`
      <div class="email-capture">
        <label class="email-label">Email (optional)</label>
        <input id="lesaepEmailInput" class="email-input" type="email" placeholder="you@example.com" />
        <button id="lesaepEmailSave" class="email-save">Continue</button>
      </div>
    `, 50);

    // Attach handler after it renders
    setTimeout(() => {
      const input = document.getElementById('lesaepEmailInput');
      const btn = document.getElementById('lesaepEmailSave');
      if (!btn) return;
      btn.onclick = () => {
        const val = (input && input.value ? input.value : '').trim();
        if (val && !this.isValidEmail(val)) {
          alert('Please enter a valid email, or click Skip email.');
          return;
        }
        this.state.email = val;
        this.saveState();
        this.addUserMessage(val ? `Email: ${val}` : 'No email');
        this.startQuiz();
      };
    }, 0);
  }

  isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  /**
   * STEP 2: Quiz
   */
  async startQuiz() {
    this.state.currentSentence = 0;
    this.state.recordings = [];
    this.state.flaggedPhonemes = [];
    this.state.quizComplete = false;
    this.saveState();

    await this.addBotMessage('Great. 🎉');
    await this.addBotMessage('Click the microphone and read each sentence out loud.');

    this.showQuickReplies([{ text: "I'm ready! 🎤", action: () => this.showRecorder() }]);
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
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

          // Prefer browser-chosen mimeType.
          this.mediaRecorder = new MediaRecorder(stream);
          this.audioChunks = [];

          this.mediaRecorder.ondataavailable = (e) => this.audioChunks.push(e.data);
          this.mediaRecorder.onstop = () => {
            stream.getTracks().forEach((track) => track.stop());
            this.processRecording(expectedText);
          };

          this.mediaRecorder.start();
          this.isRecording = true;

          recordBtn.classList.add('recording');
          recordBtn.querySelector('.record-icon').textContent = '⏹️';
          recordBtn.querySelector('.record-text').textContent = 'Stop Recording';
          waveform.classList.remove('hidden');
          this.elements.recordStatus.textContent = 'Recording… Click to stop';
        } catch (error) {
          alert('Please allow microphone access to record.');
          console.error(error);
        }
      } else {
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

    await this.addBotMessage('✅ Got it. Analyzing…', 400);

    const mimeType = (this.mediaRecorder && this.mediaRecorder.mimeType) || (this.audioChunks[0] && this.audioChunks[0].type) || 'audio/webm';
    const audioBlob = new Blob(this.audioChunks, { type: mimeType });

    const formData = new FormData();
    formData.append('audio', audioBlob, `sentence_${this.state.currentSentence + 1}`);
    formData.append('expected_text', expectedText);
    formData.append('sentence_index', String(this.state.currentSentence));

    if (this.config.userId) formData.append('wp_user_id', String(this.config.userId));
    if (this.state.email) formData.append('email', this.state.email);

    try {
      const response = await fetch(`${this.config.apiUrl}/process-audio`, {
        method: 'POST',
        body: formData
      });

      const result = await response.json();
      this.state.recordings.push(result);
      this.saveState();

      await this.addBotMessage('Nice work.', 300);

      this.state.currentSentence++;
      this.saveState();

      if (this.state.currentSentence < this.sentences.length) {
        await this.addBotMessage(`Ready for sentence ${this.state.currentSentence + 1}?`);
        this.showQuickReplies([{ text: 'Next sentence 🎤', action: () => this.showRecorder() }]);
      } else {
        await this.completeQuiz();
      }
    } catch (error) {
      console.error('Upload failed:', error);
      await this.addBotMessage('There was an error uploading your audio. Let’s try again.');
      this.showQuickReplies([{ text: 'Record again 🎤', action: () => this.showRecorder() }]);
    }
  }

  /**
   * STEP 3: Complete quiz & check login
   */
  async completeQuiz() {
    await this.addBotMessage('Excellent — recordings complete.');
    await this.addBotMessage('Finalizing analysis…', 900);

    const allPhonemes = [];
    this.state.recordings.forEach((rec) => {
      if (rec && Array.isArray(rec.flagged_phonemes)) allPhonemes.push(...rec.flagged_phonemes);
    });
    this.state.flaggedPhonemes = [...new Set(allPhonemes)].slice(0, 12);
    this.state.quizComplete = true;
    this.saveState();

    await this.addBotMessage('Analysis complete.');

    if (!this.config.isLoggedIn) {
      await this.addBotMessage('To see your personalized results, please login.');
      this.showQuickReplies([
        {
          text: 'Login to See Results 🔐',
          action: () => {
            window.location.href = this.config.loginUrl;
          }
        }
      ]);
      return;
    }

    await this.showResults();
  }

  /**
   * STEP 4: Results + free lesson
   */
  async showResults() {
    const phonemes = this.state.flaggedPhonemes || [];

    await this.addBotMessage(
      `🎯 <strong>Results:</strong><br><br>` +
        `I found <strong>${phonemes.length}</strong> sound patterns to improve:<br><br>` +
        `${phonemes.length ? phonemes.join(', ') : 'No major issues detected — great baseline!'}`
    );

    await this.addBotMessage('Fetching your personalized free lesson…', 300);

    const lessons = await this.getLessons(this.state.flaggedPhonemes);

    if (Array.isArray(lessons) && lessons.length > 0) {
      await this.addBotMessage('🎁 <strong>Your FREE Lesson:</strong>');
      await this.showLessonCard(lessons[0]);
    }

    await this.showOffer();
  }

  async getLessons(phonemes = []) {
    try {
      const qs = Array.isArray(phonemes) && phonemes.length
        ? `?phonemes=${encodeURIComponent(phonemes.join(','))}`
        : '';
      const response = await fetch(`${this.config.wpRestUrl}/lessons${qs}`, { credentials: 'same-origin' });
      return await response.json();
    } catch (error) {
      console.error('Failed to fetch lessons:', error);
      return [];
    }
  }

  async showLessonCard(lesson) {
    const safeTitle = lesson.title || 'Lesson';
    const safeExcerpt = lesson.excerpt || '';
    const safeLink = lesson.permalink || '#';

    await this.addBotMessage(
      `
      <div class="lesson-card" onclick="window.open('${safeLink}', '_blank')">
        <h4>${safeTitle}</h4>
        <p>${safeExcerpt}</p>
        <span class="lesson-badge">🎁 FREE</span>
      </div>
    `,
      50
    );
  }

  /**
   * STEP 5: Offer
   */
  async showOffer() {
    await this.addBotMessage('If you want the full course:');

    const price = this.config.price;
    const sym = this.config.currencySymbol || '€';

    await this.addBotMessage(
      `
      <div class="oto-card">
        <h3>🎓 ${this.config.productName}</h3>
        <p class="product-desc">Complete pronunciation mastery</p>
        <p class="pricing"><span class="price-new">${sym}${price}</span></p>
        <p class="special">Instant access after purchase.</p>
      </div>
    `,
      50
    );

    this.showQuickReplies([
      {
        text: `Get Full Access for ${sym}${price} ✅`,
        action: () => this.redirectToCheckout()
      },
      { text: 'Not right now', action: () => this.showFreeLessonOnly() }
    ]);
  }

  async redirectToCheckout() {
    await this.addBotMessage('Opening checkout…');
    this.state.pendingCheckout = true;
    this.saveState();
    window.location.href = this.config.checkoutUrl;
  }

  async showFreeLessonOnly() {
    await this.addBotMessage('No problem. You can come back any time for the full course.');
  }

  /**
   * Paid user
   */
  async showWelcomeBack(access) {
    await this.addBotMessage(`Welcome back${access.display_name ? `, ${access.display_name}` : ''}.`);
    await this.addBotMessage('You have full access.');
    await this.showDashboard();
  }

  async showDashboard() {
    await this.addBotMessage('Loading lessons…', 300);
    const lessons = await this.getLessons();
    await this.addBotMessage(`📚 <strong>Lessons available:</strong> ${Array.isArray(lessons) ? lessons.length : 0}`);

    if (!Array.isArray(lessons) || lessons.length === 0) return;

    for (const lesson of lessons.slice(0, 6)) {
      await this.showLessonCard(lesson);
      await new Promise((r) => setTimeout(r, 150));
    }
  }

  /**
   * UI helpers
   */
  async addBotMessage(html, delay = 600) {
    return new Promise((resolve) => {
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
      <div class="message-content">${this.escapeHtml(text)}</div>
    `;
    this.elements.messages.appendChild(div);
    this.scrollToBottom();
  }

  escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
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
    options.forEach((option) => {
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

window.addEventListener('DOMContentLoaded', () => {
  new LeSAEpChat();
});
