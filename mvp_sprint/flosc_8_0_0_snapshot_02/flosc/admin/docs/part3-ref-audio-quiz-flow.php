<?php if (!defined('ABSPATH')) exit; // Part 3: Audio Quiz Flow — FLOSC Documentation ?>

<h1 id="ref-audio-quiz-flow">Part 3: Reference — Audio Quiz Flow</h1>
<p>The audio quiz lets visitors record spoken phrases and receive a pronunciation assessment. Results are withheld until after signup/login, following the FLOSC phase flow: <strong>Freeline → Login → Offer → Sale → Content</strong>. This section documents the visitor-to-member journey, the three configurable messages, and the data persistence mechanism.</p>

<h2 id="aqf-visitor-journey">Visitor Journey</h2>
<p>When a visitor (not logged in, no account) takes the audio quiz, the flow is:</p>
<ol>
  <li><strong>Visitor starts quiz</strong> — <code>startIpaQuiz()</code> loads phrases and IPA reference data from the quiz configuration</li>
  <li><strong>Each phrase recorded</strong> — <code>processIpaRecording()</code> sends the audio to the pronunciation API, receives phoneme-level confidence scores, and stores the result in memory. The visitor sees only the <strong>Phrase Complete</strong> message (no scores, no phoneme data)</li>
  <li><strong>All phrases done</strong> — <code>showIpaQuizSummary()</code> calculates the overall score internally but shows the visitor only the <strong>Quiz Complete</strong> message, which encourages signup</li>
  <li><strong>Data saved to localStorage</strong> — <code>storeQuizScore()</code> persists the score, per-phrase API results, and IPA reference dictionary to the browser's localStorage under key <code>flosc_quiz_result</code></li>
  <li><strong>Visitor signs up / logs in</strong> — page reloads with user state changed from <code>visitor</code> to <code>guest</code></li>
  <li><strong>Results revealed</strong> — <code>checkPendingQuizResults()</code> reads localStorage, detects <code>quizType === 'ipa_audio'</code>, and calls <code>showIpaPhraseResultsAfterLogin()</code> to render the full assessment</li>
</ol>

<h2 id="aqf-configurable-messages">Configurable Messages</h2>
<p>Three messages control what the chatbot says during the audio quiz. All are editable in <strong>FLOSC → Quiz tab → Audio Quiz Messages</strong>.</p>

<table>
  <tr>
    <th>Setting</th>
    <th>Admin Field Name</th>
    <th>Config Key (JS)</th>
    <th>When Shown</th>
    <th>Placeholders</th>
    <th>Default</th>
  </tr>
  <tr>
    <td>Phrase Complete</td>
    <td><code>audio_quiz_phrase_complete_message</code></td>
    <td><code>audioQuizPhraseCompleteMessage</code></td>
    <td>After each phrase is recorded (visitors only)</td>
    <td><code>{current}</code> = phrase number, <code>{total}</code> = total phrases</td>
    <td>Thank you. {current} of {total} recorded.</td>
  </tr>
  <tr>
    <td>Quiz Complete</td>
    <td><code>audio_quiz_complete_message</code></td>
    <td><code>audioQuizCompleteMessage</code></td>
    <td>After all phrases are done (visitors only)</td>
    <td><code>{total}</code> = total phrases</td>
    <td>Pronunciation assessment complete! All {total} phrases recorded and analyzed. Sign up to see your results.</td>
  </tr>
  <tr>
    <td>Results Intro</td>
    <td><code>audio_quiz_results_message</code></td>
    <td><code>audioQuizResultsMessage</code></td>
    <td>After login, before the detailed results</td>
    <td>None</td>
    <td>Welcome! Here are your pronunciation assessment results.</td>
  </tr>
</table>

<h3 id="aqf-message-pipeline">How Messages Flow from Admin to Chatbot</h3>
<ol>
  <li>FloscAdmin edits text in <strong>Quiz tab → Audio Quiz Messages</strong></li>
  <li>On save, the form field (e.g. <code>flow_audio_quiz_phrase_complete_message</code>) is stored in the WordPress <code>wp_options</code> table as part of the flow settings array</li>
  <li>On page load, <code>flosc-app.php</code> calls <code>flosc_get_setting('audio_quiz_phrase_complete_message', 'default...')</code> and injects the value into the <code>window.FLOSC_CONFIG</code> JavaScript object</li>
  <li>The JavaScript reads <code>this.config.audioQuizPhraseCompleteMessage</code>, replaces any <code>{current}</code> and <code>{total}</code> placeholders with actual numbers, and passes the result to <code>addMessage()</code></li>
</ol>

<h2 id="aqf-localstorage">localStorage Persistence</h2>
<p>The audio quiz stores detailed results in the browser's localStorage so they survive the signup/login page reload. The stored object under key <code>flosc_quiz_result</code> contains:</p>
<table>
  <tr><th>Key</th><th>Type</th><th>Purpose</th></tr>
  <tr><td><code>score</code></td><td>integer (0-100)</td><td>Overall pronunciation score (average phoneme confidence × 100)</td></tr>
  <tr><td><code>quizType</code></td><td>string</td><td><code>'ipa_audio'</code> — used by <code>checkPendingQuizResults()</code> to route to the audio-specific results display</td></tr>
  <tr><td><code>phraseResults</code></td><td>array</td><td>Per-phrase objects, each containing <code>phrase</code> (the text) and <code>data</code> (the full API response with words, phonemes, confidence scores)</td></tr>
  <tr><td><code>wordIpa</code></td><td>object</td><td>IPA reference dictionary keyed by lowercase word. Each entry has <code>espeak</code>, <code>mw</code> (Merriam-Webster), and <code>da1ni5</code> (curated) transcriptions</td></tr>
  <tr><td><code>timestamp</code></td><td>integer</td><td>Unix timestamp (milliseconds). Results expire after 1 hour</td></tr>
  <tr><td><code>quizId</code></td><td>string</td><td>The quiz ID from flow settings</td></tr>
</table>
<p><strong>Size note:</strong> A 5-phrase quiz with multi-word phrases typically produces 20–50 KB of localStorage data. Well within the browser's ~5 MB localStorage limit.</p>
<p><strong>Expiry:</strong> <code>checkPendingQuizResults()</code> ignores stored results older than 1 hour (<code>3600000</code> ms). After displaying results, the localStorage entry is removed.</p>

<h2 id="aqf-post-login-display">Post-Login Results Display</h2>
<p><code>showIpaPhraseResultsAfterLogin()</code> renders the full assessment in three chat messages:</p>
<ol>
  <li><strong>Intro message</strong> — the configurable <strong>Results Intro</strong> text (plain text, not HTML)</li>
  <li><strong>Score summary</strong> — score circle (CSS animated), total words/phonemes/phrases count, and top 5 weakest phonemes with color-coded confidence percentages</li>
  <li><strong>Per-phrase accordions</strong> — each phrase is a collapsible <code>&lt;details&gt;/&lt;summary&gt;</code> block (first phrase open by default). Inside each accordion:
    <ul>
      <li>Phrase score percentage in the header</li>
      <li>Per-word cards showing all IPA transcription sources (espeak-ng, Merriam-Webster, da1ni5, scored-as)</li>
      <li>Per-phoneme confidence bars (green = high ≥50%, amber = medium ≥10%, red = low &lt;10%)</li>
    </ul>
  </li>
</ol>

<h3 id="aqf-color-coding">Confidence Color Coding</h3>
<table>
  <tr><th>Level</th><th>Confidence Range</th><th>CSS Variable</th><th>CSS Class</th></tr>
  <tr><td>High</td><td>≥ 50%</td><td><code>--flosc-ipa-high</code></td><td><code>.flosc-ipa-ph-high</code></td></tr>
  <tr><td>Medium</td><td>10% – 49%</td><td><code>--flosc-ipa-med</code></td><td><code>.flosc-ipa-ph-med</code></td></tr>
  <tr><td>Low</td><td>&lt; 10%</td><td><code>--flosc-ipa-low</code></td><td><code>.flosc-ipa-ph-low</code></td></tr>
</table>

<h2 id="aqf-guests-members">Guests and Members</h2>
<p>When a guest (logged in, no purchase) or member (logged in, has purchased) takes the audio quiz, they see results immediately — no withholding. The per-phrase detail (<code>showIpaPhraseResult()</code>) and the full summary (<code>showIpaQuizSummary()</code>) render inline in the chat as each phrase completes and after all phrases finish. The configurable visitor-only messages are skipped for these user states.</p>

<h2 id="aqf-js-methods">JavaScript Methods Reference</h2>
<table>
  <tr><th>Method</th><th>File</th><th>Purpose</th></tr>
  <tr><td><code>startIpaQuiz()</code></td><td>flosc-app.js</td><td>Initializes quiz state, loads phrases and IPA reference data, shows first phrase</td></tr>
  <tr><td><code>showIpaPhrase(index)</code></td><td>flosc-app.js</td><td>Displays the phrase prompt and record button for phrase at <code>index</code></td></tr>
  <tr><td><code>toggleIpaRecording()</code></td><td>flosc-app.js</td><td>Starts/stops the MediaRecorder for the current phrase</td></tr>
  <tr><td><code>processIpaRecording(blob, audioUrl, format, phraseNum)</code></td><td>flosc-app.js</td><td>Sends audio to API, stores result, shows visitor acknowledgment or guest/member detail</td></tr>
  <tr><td><code>showIpaPhraseResult(data, audioUrl, phrase, phraseNum)</code></td><td>flosc-app.js</td><td>Renders per-phrase detail for guests/members (word cards, phoneme bars)</td></tr>
  <tr><td><code>showIpaQuizSummary()</code></td><td>flosc-app.js</td><td>Calculates score, shows visitor completion message OR guest/member full summary, calls <code>storeQuizScore()</code></td></tr>
  <tr><td><code>storeQuizScore(result)</code></td><td>flosc-app.js</td><td>Saves to localStorage (including <code>phraseResults</code>, <code>wordIpa</code>, <code>quizType</code> for audio quizzes) and POST to server API</td></tr>
  <tr><td><code>checkPendingQuizResults()</code></td><td>flosc-app.js</td><td>On page load, reads localStorage. If <code>quizType === 'ipa_audio'</code>, calls <code>showIpaPhraseResultsAfterLogin()</code></td></tr>
  <tr><td><code>showIpaPhraseResultsAfterLogin(result)</code></td><td>flosc-app.js</td><td>Renders full post-login assessment: intro message, score summary, per-phrase accordions</td></tr>
</table>
