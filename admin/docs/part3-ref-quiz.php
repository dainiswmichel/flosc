<?php if (!defined('ABSPATH')) exit; // Part 3: Quiz System Reference — FLOSC Documentation ?>

<h1 id="ref-quiz">Part 3: Reference — Quiz System</h1>
<p>The quiz system is a pluggable assessment engine. Each quiz type is a PHP class extending <code>FLOSC_Abstract_Quiz_Type</code>. The factory loads all registered types. The admin enables quizzes per-flow. Wrong answers map to WordPress content via TOPIC tags.</p>

<h2 id="quiz-architecture">Architecture Overview</h2>
<p>Three layers:</p>
<ol>
  <li><strong>Abstract base</strong> (<code>abstract-quiz-type.php</code>) — defines the contract every quiz type must fulfill, plus shared scoring and lesson-lookup logic</li>
  <li><strong>Factory</strong> (<code>class-quiz-type-factory.php</code>) — discovers and instantiates quiz types</li>
  <li><strong>Implementations</strong> — one PHP class per quiz format (Pronunciation Assessment, Multiple Choice, True/False, Numbers, Audio)</li>
</ol>
<p>The admin quiz tab exposes two UI zones: <strong>Active Quizzes</strong> (summary of what is live in the flow) and <strong>Quiz Deck</strong> (library of all quiz types with enable toggle and inline editor).</p>

<h2 id="quiz-manager">class-quiz-manager.php — External Integration Bridge</h2>
<p>Handles integration with <em>external</em> quiz plugins. Not used for FLOSC native quiz types.</p>

<h3 id="quiz-manager-purpose">What It Does</h3>
<p>Provides a unified API for third-party quiz plugins to submit scores to FLOSC. Three entry points:</p>
<ol>
  <li>Direct PHP call: <code>FLOSC_Quiz_Manager::submit_score($user_id, $quiz_id, $score_data)</code></li>
  <li>WordPress action hook: <code>do_action('flosc_external_quiz_score', $user_id, $quiz_id, $score_data)</code></li>
  <li>REST API: <code>POST /wp-json/flosc/v1/external-quiz</code></li>
</ol>

<h3 id="quiz-manager-registration">Quiz Registration</h3>
<p>Register external quiz metadata so FLOSC can map question IDs to lesson numbers:</p>
<pre><code>FLOSC_Quiz_Manager::register_quiz('my_quiz_id', [
    'title'          => 'My Quiz',
    'category'       => 'grammar',
    'lesson_mapping' => ['q1' => 1, 'q2' => 2],
    'pass_score'     => 70,
    'source'         => 'learndash',
]);</code></pre>
<p>Stored in <code>flosc_quiz_registry</code> WordPress option. Retrieved via <code>get_quiz($flosc_id)</code>, <code>get_all_quizzes()</code>, removed via <code>unregister_quiz($flosc_id)</code>.</p>

<h3 id="quiz-manager-lesson-mapping">apply_lesson_mapping() — Question IDs to Lesson Numbers</h3>
<p>When <code>submit_score()</code> is called with a registered quiz, <code>apply_lesson_mapping()</code> converts question IDs in <code>correct_items</code> and <code>incorrect_items</code> to lesson numbers based on the registered mapping array. This lets FLOSC know which lessons to recommend from external quiz results.</p>

<h3 id="quiz-manager-history">User History Methods</h3>
<ul>
  <li><code>get_user_quiz_history($user_id, $quiz_id)</code> — returns quiz history from Bridge Data Manager</li>
  <li><code>user_passed_quiz($user_id, $quiz_id)</code> — returns true/false/null (null = not taken)</li>
  <li><code>get_best_score($user_id)</code> — returns highest score across all quizzes</li>
</ul>

<h3 id="quiz-manager-shortcode">Shortcode — [flosc_quiz_results]</h3>
<pre><code>[flosc_quiz_results quiz_id="my_quiz" show_correct="yes" show_incorrect="yes"]</code></pre>
<p>Renders a score card for logged-in users showing their results for the specified quiz.</p>

<h3 id="quiz-manager-rest">REST Route — External Submission</h3>
<p>Endpoint: <code>POST /wp-json/flosc/v1/external-quiz</code><br>
Auth: valid <code>X-FLOSC-API-Key</code> header OR logged-in WordPress user.<br>
Params: <code>user_id</code>, <code>quiz_id</code>, <code>score_data</code> (object with <code>score</code>, <code>correct_items</code>, <code>incorrect_items</code>).</p>

<h2 id="quiz-type-factory">class-quiz-type-factory.php — The Factory</h2>
<p>Auto-discovers and instantiates quiz type classes from the <code>includes/quiz-types/</code> directory.</p>

<h3 id="quiz-factory-purpose">What It Does</h3>
<p>Maintains a registry of all available quiz types. Provides methods to get all types, get a specific type by ID, and filter types by capability.</p>

<h3 id="quiz-factory-registry">Key Methods</h3>
<ul>
  <li><code>get_all_quiz_types()</code> — returns all registered <code>FLOSC_Abstract_Quiz_Type</code> instances keyed by ID</li>
  <li><code>get_quiz_type($flosc_id)</code> — returns a single quiz type instance or null</li>
  <li><code>get_active_quiz_type()</code> — returns the currently selected quiz type from flow settings</li>
</ul>

<h2 id="quiz-abstract">abstract-quiz-type.php — The Base Class</h2>
<p>All quiz types extend <code>FLOSC_Abstract_Quiz_Type</code>. Defines the full contract for content format, scoring, and lesson mapping.</p>

<h3 id="quiz-abstract-contract">Required Abstract Methods</h3>
<table>
  <tr><th>Method</th><th>Returns</th><th>Purpose</th></tr>
  <tr><td><code>get_id()</code></td><td>string</td><td>Unique slug (e.g. <code>pronunciation_assessment</code>)</td></tr>
  <tr><td><code>get_name()</code></td><td>string</td><td>Display name for admin UI</td></tr>
  <tr><td><code>get_description()</code></td><td>string</td><td>Short description for quiz card</td></tr>
  <tr><td><code>get_icon()</code></td><td>string</td><td>Emoji icon</td></tr>
  <tr><td><code>needs_audio()</code></td><td>bool</td><td>Requires microphone recording</td></tr>
  <tr><td><code>needs_stt()</code></td><td>bool</td><td>Requires speech-to-text processing — if true, quiz is in Quiz Deck but cannot be enabled until STT pipeline is built</td></tr>
  <tr><td><code>needs_ai_analysis()</code></td><td>bool</td><td>Requires AI to score (vs deterministic scoring)</td></tr>
  <tr><td><code>get_instructions()</code></td><td>string</td><td>Format instructions shown in admin quiz editor</td></tr>
  <tr><td><code>get_default_content()</code></td><td>string</td><td>Default textarea content for fresh installs</td></tr>
  <tr><td><code>validate_input($input)</code></td><td>bool|WP_Error</td><td>Validate user's answer before scoring</td></tr>
  <tr><td><code>analyze($input, $expected_content, $context)</code></td><td>array</td><td>Score the answer — see return shape below</td></tr>
  <tr><td><code>get_settings_fields()</code></td><td>array</td><td>Additional admin settings fields for this quiz type</td></tr>
</table>

<h3 id="quiz-abstract-analyze-return">analyze() Return Shape</h3>
<pre><code>[
    'score'        => int,       // 0-100
    'correct'      => array,     // items answered correctly (each may include 'topics')
    'incorrect'    => array,     // items answered incorrectly (each may include 'topics')
    'response_key' => string,    // '0-30' | '31-60' | '61-85' | '86-100'
    'details'      => array,     // total_correct, total_possible, and quiz-specific data
]</code></pre>
<p>Each item in <code>incorrect</code> should include a <code>'topics'</code> key (array of strings) for the lesson lookup to work.</p>

<h3 id="quiz-abstract-templates">get_default_response_templates() — Score-Based Messaging</h3>
<p>Returns an array of score-range templates shown to the learner after scoring. Keys are score ranges: <code>'0-30'</code>, <code>'31-60'</code>, <code>'61-85'</code>, <code>'86-100'</code>. Overridden in the admin Quiz Editor under "Score Feedback Templates." Available placeholders:</p>
<ul>
  <li><code>{score}</code> — numeric score 0-100</li>
  <li><code>{total_correct}</code> — number correct</li>
  <li><code>{total_possible}</code> — total questions</li>
  <li><code>{lesson_recommendations}</code> — formatted lesson list from <code>map_to_lessons()</code></li>
</ul>

<h3 id="quiz-abstract-map-lessons">map_to_lessons() — Wrong Answers → WordPress Content</h3>
<p>Default implementation (as of v4.0.4) collects all <code>'topics'</code> values from incorrect items, deduplicates them, then resolves each via <code>lookup_lesson_by_tag()</code>.</p>
<p>Resolution order for each topic tag:</p>
<ol>
  <li><strong>Numeric post/lesson ID</strong> — <code>get_post((int)$flosc_tag)</code></li>
  <li><strong>WordPress category slug</strong> — <code>get_category_by_slug($flosc_tag)</code> → posts in that category</li>
  <li><strong>WordPress post tag slug</strong> — <code>get_term_by('slug', $flosc_tag, 'post_tag')</code> → tagged posts</li>
  <li><strong>Title / full-text search</strong> — <code>get_posts(['s' => $flosc_tag])</code></li>
</ol>
<p>Returns array of <code>['id' => int, 'title' => string, 'reason' => string]</code>. First result becomes the free lesson; remaining become locked upgrade suggestions.</p>

<h3 id="quiz-abstract-format">format_results() — Result Presentation</h3>
<p>Takes the analysis result, lessons from <code>map_to_lessons()</code>, and the admin-configured templates. Replaces placeholders and returns the chat message string. Override in subclass for custom formatting.</p>

<h3 id="quiz-abstract-helpers">Scoring Helpers</h3>
<ul>
  <li><code>calculate_percentage($correct, $total)</code> — returns 0-100 integer</li>
  <li><code>get_response_key_from_score($score)</code> — maps score to template key: ≤30 → <code>'0-30'</code>, ≤60 → <code>'31-60'</code>, ≤85 → <code>'61-85'</code>, else <code>'86-100'</code></li>
</ul>

<h2 id="quiz-types">Quiz Type Implementations</h2>

<h3 id="quiz-pronunciation">class-flosc-pronunciation-assessment-quiz.php — Pronunciation Assessment</h3>
<p>10-question multiple-choice accent assessment for Standard American English. Each question maps to a specific sound lesson. Designed so typical non-native speakers score 40–70%, creating a natural moment for lesson recommendations.</p>

<h4 id="quiz-pronunciation-format">Question Format</h4>
<pre><code>Which pair of words use the SAME vowel sound?
A: cat / cut
B: map / mop
C: cat / map
D: bat / bit
CORRECT: C
TOPIC: short-a-vowel</code></pre>
<p>One question per block, blocks separated by blank lines. <code>TOPIC:</code> is optional but required for lesson recommendations to work. Accepts comma-separated multiple topics: <code>TOPIC: voiceless-th, th-sounds</code></p>

<h4 id="quiz-pronunciation-defaults">Default Question-to-Topic Mapping (v4.0.4)</h4>
<table>
  <tr><th>Question</th><th>Sound</th><th>Topic Slug</th></tr>
  <tr><td>Q1</td><td>/æ/ short-a vowel</td><td>short-a-vowel</td></tr>
  <tr><td>Q2</td><td>American rhotic R</td><td>rhotic-r</td></tr>
  <tr><td>Q3</td><td>Voiceless TH /θ/</td><td>voiceless-th</td></tr>
  <tr><td>Q4</td><td>Voiced TH /ð/</td><td>voiced-th</td></tr>
  <tr><td>Q5</td><td>/ɪ/ vs /iː/ — ship vs sheep</td><td>short-i-long-e</td></tr>
  <tr><td>Q6</td><td>Schwa /ə/ unstressed vowels</td><td>schwa</td></tr>
  <tr><td>Q7</td><td>Flap T (butter = "budder")</td><td>flap-t</td></tr>
  <tr><td>Q8</td><td>Word stress patterns</td><td>word-stress</td></tr>
  <tr><td>Q9</td><td>Connected speech / linking</td><td>connected-speech</td></tr>
  <tr><td>Q10</td><td>Dark L vs light L</td><td>dark-l</td></tr>
</table>
<p>To activate TOPIC-based lesson recommendations: create WordPress posts/categories/tags using these slugs (or customize the TOPIC: lines in the quiz editor to match your own WordPress structure).</p>

<h3 id="quiz-multiplechoice">class-multiplechoice-quiz.php — Multiple Choice</h3>
<p>Classic format with 2–4 options per question. One question per line, pipe-delimited.</p>

<h4 id="quiz-mc-format">Question Format</h4>
<pre><code>What does "break a leg" mean?|A) Get injured|B) Good luck|C) Work very hard|Correct: B|Topic: idioms</code></pre>
<p><code>Topic:</code> segment is optional. Multiple topics: <code>|Topic: idioms, expressions</code></p>

<h4 id="quiz-mc-scoring">Scoring Logic</h4>
<p>User submits comma-separated answer letters (e.g. <code>A,B,C,B</code>). Each answer compared against the parsed <code>Correct:</code> value (case-insensitive). Score = correct / total × 100.</p>

<h3 id="quiz-truefalse">class-truefalse-quiz.php — True/False</h3>
<p>One statement per line, pipe-delimited. Accepts T/F, True/False, Yes/No, 1/0 as user answers (normalized internally).</p>

<h4 id="quiz-tf-format">Question Format</h4>
<pre><code>In American English, the "r" in "car" is silent.|False|Topic: rhotic-r</code></pre>
<p><code>Topic:</code> as 3rd pipe segment, optional.</p>

<h3 id="quiz-numbers">class-flosc-sample-text-based-quiz.php — FLOSC Sample 1-10 Numbers Quiz</h3>
<p>Pipeline test quiz. Admin configures a comma-separated list of expected answers. User types their answers comma-separated. Used to verify the full FLOSC flow works end-to-end without needing a real subject-matter quiz.</p>
<pre><code>1,2,3,4,5,6,7,8,9,10</code></pre>
<p>No per-question TOPIC: support (flat sequence format). Settings: separator character, case sensitivity, partial credit (future).</p>

<h3 id="quiz-audio">class-flosc-sample-audio-quiz.php — Sample Audio Quiz</h3>
<p>Aspirational quiz type requiring microphone access + speech-to-text processing. <strong>Not yet functional.</strong> Visible in the Quiz Deck with a warning: "Requires microphone + speech-to-text — not yet functional." Checkbox is disabled; cannot be enabled until the STT pipeline is built. <code>needs_stt()</code> and <code>needs_audio()</code> both return <code>true</code> — the admin tab uses this to separate it from functional quiz types.</p>

<h2 id="quiz-admin-ui">Admin Quiz Tab — UI Zones</h2>

<h3 id="quiz-admin-active">Active Quizzes</h3>
<p>Compact summary at top of the tab. Shows green badges for each currently enabled quiz. If none are enabled, shows an amber warning. Reflects the <code>flow_settings['enabled_quizzes']</code> array.</p>

<h3 id="quiz-admin-deck">Quiz Deck</h3>
<p>All registered quiz types shown as cards. Each card:</p>
<ul>
  <li>Quiz name + icon + NATIVE badge + ✅ Active badge if enabled</li>
  <li>Description</li>
  <li>"Enable (make Active)" checkbox</li>
  <li>"Edit Quiz" toggle — opens editor panel containing:
    <ul>
      <li><strong>Questions, Correct Answers &amp; WordPress Topic Links</strong> textarea (the quiz content in its native format)</li>
      <li>Format instructions with full syntax reference including TOPIC:</li>
      <li><strong>Score Feedback Templates</strong> — one textarea per score range (0-30%, 31-60%, 61-85%, 86-100%)</li>
    </ul>
  </li>
</ul>
<p>STT-required quiz types appear at the end of the deck with dashed border, disabled checkbox, and amber warning. No "COMING SOON" badge.</p>

<h3 id="quiz-admin-demo-library">Demo Quiz Sets</h3>
<p>Collapsible section (open by default) with ready-made question sets. Each demo has a "Load →" button that fills the corresponding quiz's editor textarea and opens the editor panel. Available demos:</p>
<ul>
  <li><strong>Pronunciation assessment:</strong> Minimal Pairs Discrimination, American Vowel Sounds, Connected Speech &amp; Rhythm</li>
  <li><strong>Multiple Choice:</strong> American Idioms, Business English Communication</li>
  <li><strong>True/False:</strong> Pronunciation Myths vs Facts, Grammar Confidence Check</li>
  <li><strong>1-10 Numbers:</strong> Classic 1–10 Sequence, Primary Color Names, Days of the Week</li>
</ul>

<h3 id="quiz-admin-save">Save Behavior</h3>
<p>Quiz content, enabled state, and score templates are all part of the main FLOSC settings form. The main page Save button (which submits <code>flosc_save</code>) is the only save mechanism. There is no separate quiz save button — a previous dead button (<code>save_quiz</code>) was removed in v4.0.4 because the settings handler never checked for it.</p>

<h2 id="quiz-topic-linking">TOPIC: Linking — How Wrong Answers Recommend Lessons</h2>

<h3 id="quiz-topic-workflow">Workflow</h3>
<ol>
  <li>Admin creates WordPress posts, categories, or tags for each lesson topic</li>
  <li>Admin notes the category slug, post tag slug, post title, or post ID</li>
  <li>Admin adds <code>TOPIC: slug</code> to relevant quiz questions in the editor</li>
  <li>Visitor takes quiz</li>
  <li><code>analyze()</code> returns wrong answers with their topic tags</li>
  <li><code>map_to_lessons()</code> resolves tags to WordPress posts</li>
  <li><code>format_results()</code> renders the lesson list in the chat response via <code>{lesson_recommendations}</code></li>
</ol>

<h3 id="quiz-topic-resolution">Resolution Order</h3>
<p>For each TOPIC tag, <code>lookup_lesson_by_tag()</code> tries:</p>
<ol>
  <li>Numeric → <code>get_post((int)$flosc_tag)</code> — direct post or lesson ID</li>
  <li>Category slug → posts in that category (up to 5)</li>
  <li>Post tag slug → posts with that tag (up to 5)</li>
  <li>Search → <code>get_posts(['s' => $flosc_tag])</code> — catches title matches and content (up to 3)</li>
</ol>
<p>First resolution that returns results wins. Deduplication by post ID across all topics.</p>

<h2 id="quiz-frontend">Quiz Frontend</h2>
<p class="skeleton-marker">Content pending — covers how quizzes render inside the chat window, user interaction flow, and results display. See <code>assets/js/flosc-app.js</code> quiz methods and <code>admin/flosc-app.php</code> quiz HTML template.</p>
