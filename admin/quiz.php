<?php
/**
 * FLOSC Quiz Configuration Tab
 *
 * Enable/disable quizzes, edit questions inline, and load ready-made demo sets.
 *
 * v4.0.2: Stripped dead weight; added per-card inline edit panel + demo library
 *         with Load → buttons that fill the editor directly.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

flosc_tab_header( '❓', 'Quiz' );

$flosc_flow_settings   = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr     = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_quiz_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-quiz';
// Empty = no quizzes on this flow. Do not invent sample quiz IDs.
$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? [];
if ( ! is_array( $flosc_enabled_quizzes ) ) {
    $flosc_enabled_quizzes = [];
}
$flosc_enabled_quizzes = array_values( array_filter( array_map( 'sanitize_key', $flosc_enabled_quizzes ) ) );
$flosc_all_quiz_types  = FLOSC_Quiz_Registry::get_all_quizzes();

echo '<div class="flosc-docs-link-wrap">'
    . '<a href="' . esc_url($flosc_quiz_docs_url) . '" class="flosc-docs-link">Docs</a>'
   . '</div>';

if ( empty( $flosc_all_quiz_types ) ) {
    echo '<div class="notice notice-error"><p>No quiz types registered. Please reinstall FLOSC.</p></div>';
    return;
}

// ── Demo library ──────────────────────────────────────────────────────────────
// Each entry: [ 'name', 'desc', 'content' ]
// Content format must match the quiz type's own get_instructions() format.
$flosc_quiz_demos = [

    // ── LeSAEp Pronunciation (Q+options block format) ──────────────────────
    'pronunciation_assessment_quiz' => [

        [
            'name' => 'Minimal Pairs Discrimination',
            'desc' => '10 questions on distinguishing sounds that differ by one phoneme — great for intermediate learners.',
            'content' => <<<'DEMO'
Which word pair are minimal pairs (differ by only ONE sound)?
A: cat / bat
B: cat / cats
C: cat / catfish
D: bat / bad
CORRECT: A

In "sheep" vs "ship" — what single difference makes them different words?
A: The consonants
B: The vowel — /iː/ (long) vs /ɪ/ (short)
C: The word stress
D: The final consonant
CORRECT: B

"Fan" and "van" are minimal pairs. What single sound is different?
A: The vowel
B: The final consonant
C: The initial consonant — /f/ vs /v/
D: The word stress
CORRECT: C

Which pair is NOT a minimal pair?
A: pin / bin
B: cat / hat
C: run / running
D: seat / sit
CORRECT: C

"Think" and "sink" differ in only one sound. What is different?
A: The vowel
B: The initial consonant — /θ/ vs /s/
C: The final consonant
D: The word stress
CORRECT: B

"Bed" and "bad" are minimal pairs. What sound is different?
A: The initial consonant
B: The final consonant
C: The vowel — /ɛ/ vs /æ/
D: The word stress
CORRECT: C

Which pair represents the /p/ vs /b/ minimal pair distinction?
A: pat / bat
B: pat / cat
C: pat / hat
D: pat / fat
CORRECT: A

"Light" and "night" are minimal pairs. Which sound changes?
A: The vowel
B: The final consonant
C: The initial consonant — /l/ vs /n/
D: Both consonants
CORRECT: C

"Cold" and "gold" differ in only one sound. What is it?
A: The vowel
B: The initial consonant — /k/ vs /g/
C: The final consonant
D: The word stress
CORRECT: B

In "this" vs "these" — what is the main pronunciation difference?
A: The initial consonant
B: The vowel — /ɪ/ (short) in "this" vs /iː/ (long) in "these"
C: The final consonant
D: The TH sound changes
CORRECT: B
DEMO,
        ],

        [
            'name' => 'American Vowel Sounds',
            'desc' => '10 questions focused specifically on American English vowel identification and contrast.',
            'content' => <<<'DEMO'
Which word has the /æ/ (short A) vowel sound?
A: late
B: cat
C: caught
D: cut
CORRECT: B

"Caught", "call", and "law" all share which vowel?
A: /æ/ as in "cat"
B: /ɑː/ as in "father"
C: /ɔː/ as in "thought"
D: /oʊ/ as in "go"
CORRECT: C

In "butter", "above", and "fun" — the stressed vowel is:
A: /æ/ (short A)
B: /ʌ/ (short U — "uh" sound)
C: /ɒ/ (short O)
D: /ə/ (schwa)
CORRECT: B

"Bit", "sit", "tip" — these words share which vowel?
A: /iː/ (long E, as in "see")
B: /ɪ/ (short I, as in "bit")
C: /e/ (as in "bed")
D: /aɪ/ (as in "bike")
CORRECT: B

Which word has a DIFFERENT vowel from the others?
A: beat
B: feet
C: bit
D: meat
CORRECT: C

The words "go", "home", and "boat" share which vowel?
A: /ɒ/ (short O as in "hot")
B: /ɔː/ (as in "thought")
C: /oʊ/ (long O diphthong)
D: /uː/ (as in "boot")
CORRECT: C

"Food", "moon", and "blue" share which vowel?
A: /ʊ/ (as in "book")
B: /uː/ (long U, as in "boot")
C: /oʊ/ (as in "go")
D: /juː/ (as in "cute")
CORRECT: B

In "book", "put", and "should" — the vowel is:
A: /uː/ (long U as in "boot")
B: /ʌ/ (as in "cut")
C: /ʊ/ (short U as in "book")
D: /oʊ/ (as in "go")
CORRECT: C

"Cot" and "caught" — in General American English (most of the US):
A: Sound completely different
B: Sound identical (the cot-caught merger)
C: "Cot" is longer
D: Only differ in spelling
CORRECT: B

The schwa /ə/ is:
A: A stressed vowel found in content words
B: The unstressed reduced vowel found in weak syllables
C: Only found at the end of words
D: The same as the short I sound
CORRECT: B
DEMO,
        ],

        [
            'name' => 'Connected Speech & Rhythm',
            'desc' => '10 questions on linking, reduction, stress timing, and natural American speech flow.',
            'content' => <<<'DEMO'
In natural American English, "I'm going to go" is most often reduced to:
A: "I am going to go" (all words fully pronounced)
B: "I'm gonna go"
C: "I go"
D: "Am going go"
CORRECT: B

"Want to" in casual speech becomes:
A: "wanna"
B: "wan-to"
C: "want-a"
D: "wanto"
CORRECT: A

In American English, which syllables receive the MOST stress?
A: Function words (the, a, to, of)
B: Content words (nouns, main verbs, adjectives, adverbs)
C: All syllables equally
D: The last syllable of every sentence
CORRECT: B

"Did you eat yet?" in fast natural speech sounds most like:
A: "Did — you — eat — yet?" (each word distinct)
B: "Didja eat yet?"
C: "Did you ate yet?"
D: "You eat yet?"
CORRECT: B

Word linking — "an apple" in natural speech sounds like:
A: "an — apple" (brief pause between words)
B: "a-napple" (the N links to the next word)
C: "ann apple"
D: "a apple"
CORRECT: B

In American English rhythm, unstressed syllables are typically:
A: Longer and louder than stressed syllables
B: Shorter, quieter, and often reduced to schwa
C: The same length as stressed syllables
D: Always silent
CORRECT: B

The phrase "I don't know" reduced to "I dunno" is an example of:
A: Incorrect grammar
B: Informal contracted pronunciation in casual speech
C: A regional dialect only
D: Rude speech
CORRECT: B

Which sentence has the typical American English stress pattern?
A: "I BOUGHT a COFFEE at the STORE" (stress on all content words)
B: "I bought a coffee at the store" (all words equal stress)
C: "I bought A COFFEE at THE STORE" (stress on articles)
D: "i bought a coffee at the STORE" (stress only on last word)
CORRECT: A

"What do you think?" in natural speech most commonly sounds like:
A: "What — do — you — think?" (fully separated)
B: "Whadya think?" or "Whaddya think?"
C: "What you think?"
D: "What do think?"
CORRECT: B

The "t" in words like "water", "better", "butter" in American English sounds like:
A: A strong, aspirated /t/ like in "top"
B: A soft /d/-like flap
C: Silent — not pronounced
D: A glottal stop like in British English
CORRECT: B
DEMO,
        ],
    ],

    // ── Multiple Choice (pipe-delimited format) ────────────────────────────
    'multiplechoice' => [

        [
            'name' => 'American Idioms',
            'desc' => '10 common American English idioms with 4 choices each.',
            'content' => <<<'DEMO'
What does "break a leg" mean?|A) Get injured|B) Good luck|C) Work very hard|D) Stop trying|Correct: B
To "hit the sack" means to:|A) Win a fight|B) Pack for travel|C) Go to bed|D) Lose something|Correct: C
What does "under the weather" mean?|A) It's raining|B) Feeling sick|C) Working outdoors|D) Running late|Correct: B
"Bite the bullet" means to:|A) Lose a fight|B) Eat something tough|C) Endure pain without complaining|D) Give up|Correct: C
What does "cost an arm and a leg" mean?|A) Injury compensation|B) Very expensive|C) A fair trade|D) Worth the price|Correct: B
To "spill the beans" means to:|A) Make a mess|B) Cook a meal|C) Reveal a secret|D) Waste food|Correct: C
"Hit the nail on the head" means to:|A) Use tools correctly|B) Be exactly right|C) Work in construction|D) Get lucky|Correct: B
To "burn the midnight oil" means to:|A) Forget to turn off lights|B) Cook late at night|C) Work late into the night|D) Waste energy|Correct: C
What does "bite off more than you can chew" mean?|A) Eat too fast|B) Take on more than you can handle|C) Be greedy|D) Speak with a full mouth|Correct: B
To "get cold feet" means to:|A) Need warmer socks|B) Go swimming|C) Feel nervous and hesitate|D) Feel physically cold|Correct: C
DEMO,
        ],

        [
            'name' => 'Business English Communication',
            'desc' => '10 questions on professional American English vocabulary and email etiquette.',
            'content' => <<<'DEMO'
"Please advise" in a business email typically means:|A) Give me your address|B) Let me know your thoughts or decision|C) Tell me what to do immediately|D) Send me an invoice|Correct: B
To "circle back" in business jargon means to:|A) Return to an earlier topic or person|B) Walk around the office|C) Send a follow-up invoice|D) Cancel a meeting|Correct: A
What does "let's take this offline" mean in a meeting?|A) Turn off the internet|B) Discuss privately, outside the group|C) Stop working|D) Schedule a video call later|Correct: B
"Moving the needle" means:|A) Sewing a garment|B) Making measurable progress|C) Changing a policy|D) Starting a new project|Correct: B
A "pain point" in business refers to:|A) Back pain from desk work|B) A specific problem that frustrates customers|C) A budget shortfall|D) A difficult employee|Correct: B
"Low-hanging fruit" means:|A) Fruit from short trees|B) Easy tasks or opportunities with quick results|C) A summer sale|D) Entry-level employees|Correct: B
To "get everyone on the same page" means:|A) Use the same document format|B) Ensure all team members share the same understanding|C) Work on one project at a time|D) Agree on a meeting date|Correct: B
"bandwidth" in a business context usually means:|A) Internet connection speed|B) Available time and capacity to take on new work|C) The width of a presentation screen|D) Budget allocation|Correct: B
A "deliverable" is:|A) A package shipped to a client|B) A specific result or output expected from a project|C) An employee ready to work remotely|D) A promised discount|Correct: B
"Touch base" means to:|A) Play baseball at work|B) Briefly check in or make contact with someone|C) Review the basics|D) Start from the beginning|Correct: B
DEMO,
        ],
    ],

    // ── True/False (Statement.|True or Statement.|False format) ────────────
    'truefalse' => [

        [
            'name' => 'Pronunciation Myths vs Facts',
            'desc' => '10 True/False statements debunking common American English pronunciation myths.',
            'content' => <<<'DEMO'
In American English, the "r" in "car" is silent.|False
The TH sound in "think" (/θ/) is the same as in "this" (/ð/).|False
In natural connected speech, Americans often link words together smoothly.|True
Every syllable in an English word should be pronounced with equal stress.|False
The word "butter" contains the same /t/ sound as the /t/ in "stop".|False
Reducing unstressed vowels to schwa /ə/ is considered poor pronunciation.|False
The word "comfortable" is commonly pronounced as 3 syllables in American English.|True
In American English, the letter "p" in "spin" sounds slightly different from "p" in "pin".|True
Rhotic accents (like General American) fully pronounce the "r" after vowels.|True
Slowing down your speech is the only technique needed to improve American English clarity.|False
DEMO,
        ],

        [
            'name' => 'Grammar Confidence Check',
            'desc' => '10 True/False statements about common American English grammar points.',
            'content' => <<<'DEMO'
"I have been living here for five years" is grammatically correct.|True
"Could you please send me the report?" is a polite and grammatically correct request.|True
"Between you and I" is grammatically correct in standard American English.|False
The sentence "She don't know" uses standard American English grammar.|False
"I look forward to hearing from you" is correct American business English.|True
"Me and my friend went to the store" is considered standard formal English.|False
In American English, collective nouns like "team" and "staff" take singular verbs.|True
"I could care less" and "I couldn't care less" mean the same thing in American usage.|False
The Oxford comma (final comma in a list) is widely used in American English writing.|True
"Literally" is only used in American English to describe things that are factually true.|False
DEMO,
        ],
    ],

    // ── 1-10 Numbers (comma-separated format) ─────────────────────────────
    'flosc_sample_data_numbers_quiz' => [

        [
            'name' => 'Classic 1–10 Sequence',
            'desc' => 'The default flow test. User types all 10 numbers — perfect for testing the full FLOSC pipeline.',
            'content' => '1,2,3,4,5,6,7,8,9,10',
        ],

        [
            'name' => 'Primary Color Names',
            'desc' => 'Type the 6 primary and secondary color names. Tests text-matching with words instead of numbers.',
            'content' => 'red,orange,yellow,green,blue,purple',
        ],

        [
            'name' => 'Days of the Week',
            'desc' => 'Type all 7 days in order. Good for testing case-insensitive matching.',
            'content' => 'monday,tuesday,wednesday,thursday,friday,saturday,sunday',
        ],
    ],

];
?>

<div class="flosc-quiz-config">

    <!-- ── Flow preview ──────────────────────────────────────────────────── -->
    <div class="flosc-section-header">
        <h2>Quiz Configuration</h2>
        <p>Enable quizzes and edit their questions. The first enabled quiz is shown to visitors.</p>
    </div>

    <div class="flosc-flow-preview">
        <div class="flosc-flow-step"><div class="icon">📝</div><div class="label">Quiz</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">📊</div><div class="label">Score</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🔐</div><div class="label">Login</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🎁</div><div class="label">Free Lesson</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">💰</div><div class="label">Offer</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🎓</div><div class="label">Content</div></div>
    </div>

    <?php
    // Split quiz types: ready (functional) vs coming soon (needs STT/microphone)
    $flosc_ready_quizzes  = [];
    $flosc_coming_quizzes = [];
    foreach ( $flosc_all_quiz_types as $flosc_qid => $flosc_qt ) {
        if ( method_exists( $flosc_qt, 'needs_stt' ) && $flosc_qt->needs_stt() ) {
            $flosc_coming_quizzes[ $flosc_qid ] = $flosc_qt;
        } else {
            $flosc_ready_quizzes[ $flosc_qid ] = $flosc_qt;
        }
    }
    $flosc_active_quiz_types = array_filter( $flosc_enabled_quizzes, fn( $flosc_qid ) => isset( $flosc_ready_quizzes[ $flosc_qid ] ) );
    ?>

    <!-- ── Audio Quiz Messages — configurable chatbot responses ─────────── -->
    <h3>💬 Audio Quiz Messages
        <?php
        $flosc_helplink_url = add_query_arg([
            'page' => 'flosc-settings',
            'ivr'  => isset($selected_ivr) ? $selected_ivr : '',
            'tab'  => 'documentation',
            'doc'  => 'ref-audio-quiz-flow',
        ], admin_url('admin.php'));
        ?>
        <a href="<?php echo esc_url($flosc_helplink_url); ?>" class="flosc-help-link" title="Full documentation for the Audio Quiz Flow">📖 Help</a>
    </h3>
    <p class="description">These messages appear in the chatbot during and after the audio pronunciation quiz. Placeholders: <code>{current}</code> = phrase number, <code>{total}</code> = total phrases.</p>
    <table class="form-table flosc-form-table-margin-bottom-24">
        <tr>
            <th scope="row"><label for="flow_audio_conversion_provider">Audio Conversion Provider</label></th>
            <td>
                <select id="flow_audio_conversion_provider" name="flow_audio_conversion_provider">
                    <option value="none" <?php selected( $flosc_flow_settings['audio_conversion_provider'] ?? 'none', 'none' ); ?>>none (default)</option>
                    <option value="lesaep" <?php selected( $flosc_flow_settings['audio_conversion_provider'] ?? 'none', 'lesaep' ); ?>>lesaep</option>
                </select>
                <p class="description">Select <code>lesaep</code> only when your flow is configured to call a compatible remote conversion endpoint. <code>none</code> keeps conversion dispatch disabled.</p>
            </td>
        </tr>
        <tr>
            <th scope="row" class="flosc-width-200"><label for="flow_audio_quiz_phrase_complete_message">Phrase Complete</label></th>
            <td>
                <input type="text" id="flow_audio_quiz_phrase_complete_message" name="flow_audio_quiz_phrase_complete_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_phrase_complete_message'] ?? 'Thank you. {current} of {total} recorded.' ); ?>">
                <p class="description">Shown after each phrase is recorded.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_audio_quiz_complete_message">Quiz Complete (Visitors)</label></th>
            <td>
                <input type="text" id="flow_audio_quiz_complete_message" name="flow_audio_quiz_complete_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_complete_message'] ?? 'Pronunciation assessment complete! All {total} phrases recorded and analyzed. Sign up to see your results.' ); ?>">
                <p class="description">Shown to visitors after all phrases are done.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_audio_quiz_results_message">Results Intro</label></th>
            <td>
                <input type="text" id="flow_audio_quiz_results_message" name="flow_audio_quiz_results_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_results_message'] ?? 'Welcome! Here are your pronunciation assessment results.' ); ?>">
                <p class="description">Shown after login, before the detailed results.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_audio_quiz_upsell_message">Upsell Message</label></th>
            <td>
                <input type="text" id="flow_audio_quiz_upsell_message" name="flow_audio_quiz_upsell_message" class="large-text" value="<?php echo esc_attr( $flosc_flow_settings['audio_quiz_upsell_message'] ?? 'Our accent analysis shows you would benefit from lessons on {1st}, {2nd}, and {4th}. Upgrade today for full access to all lessons.' ); ?>">
                <p class="description">Shown after login results. Placeholders: <code>{1st}</code>, <code>{2nd}</code>, <code>{3rd}</code>, <code>{4th}</code> = worst phoneme names (by rank).</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flow_audio_quiz_phoneme_lesson_map">Phoneme → Lesson Map</label></th>
            <td>
                <textarea id="flow_audio_quiz_phoneme_lesson_map" name="flow_audio_quiz_phoneme_lesson_map" class="large-text" rows="6"><?php echo esc_textarea( $flosc_flow_settings['audio_quiz_phoneme_lesson_map'] ?? '{}' ); ?></textarea>
                <p class="description">JSON object mapping IPA phoneme symbols (as returned by the pronunciation API) to lesson numbers. Example: <code>{"æ": 1, "θ": 35, "ð": 36}</code></p>
            </td>
        </tr>
    </table>

    <!-- ── Active Quizzes — summary of what is live in the funnel ────────── -->
    <h3>✅ Active Quizzes</h3>
    <?php if ( empty( $flosc_active_quiz_types ) ): ?>
    <p class="flosc-quiz-warning">
        No quizzes are currently active. Enable one in the Quiz Deck below.
    </p>
    <?php else: ?>
    <div class="flosc-quiz-pill-row">
        <?php foreach ( $flosc_active_quiz_types as $flosc_qid ):
            $flosc_qt = $flosc_ready_quizzes[ $flosc_qid ] ?? null;
            if ( ! $flosc_qt ) continue;
        ?>
        <span class="flosc-quiz-pill">
            ✅ <?php echo esc_html( $flosc_qt->get_icon() . ' ' . $flosc_qt->get_name() ); ?>
        </span>
        <?php endforeach; ?>
    </div>
    <p class="description">These quizzes are currently live in the visitor funnel. Enable or disable them in the Quiz Deck below.</p>
    <?php endif; ?>

    <!-- ── Quiz Deck — configure and enable quizzes ───────────────────────── -->
    <h3 class="flosc-heading-top-28">🗂 Quiz Deck</h3>
    <p>Your library of available quizzes. Enable a quiz to make it Active. Load a demo below to populate the question editor.</p>

    <div class="flosc-quiz-grid">
    <?php foreach ( $flosc_ready_quizzes as $flosc_quiz_id => $flosc_qt ):
        $flosc_is_enabled = in_array( $flosc_quiz_id, $flosc_enabled_quizzes );
        $flosc_content    = ! empty( $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ] )
                        ? $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ]
                        : $flosc_qt->get_default_content();
        $flosc_ta_id      = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
        $flosc_det_id     = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
    ?>
        <div class="flosc-quiz-card <?php echo esc_attr($flosc_is_enabled ? 'active' : ''); ?>">
            <h4>
                <span class="icon"><?php echo esc_html( $flosc_qt->get_icon() ); ?></span>
                <?php echo esc_html( $flosc_qt->get_name() ); ?>
                <span class="badge native">NATIVE</span>
                <?php if ( $flosc_is_enabled ): ?>
                <span class="badge flosc-quiz-badge-active">✅ Active</span>
                <?php endif; ?>
            </h4>
            <p class="desc"><?php echo esc_html( $flosc_qt->get_description() ); ?></p>

            <div class="flosc-quiz-toggle">
                <input type="checkbox"
                       name="flow_enabled_quizzes[]"
                       value="<?php echo esc_attr( $flosc_quiz_id ); ?>"
                       <?php checked( $flosc_is_enabled ); ?>>
                <label>Enable (make Active)</label>
            </div>

            <details id="<?php echo esc_attr($flosc_det_id); ?>" class="flosc-margin-top-12">
                <summary class="flosc-details-summary flosc-details-summary--blue">
                    ✏️ Edit Quiz
                </summary>
                <div class="flosc-details-content-top-8">

                    <p class="flosc-micro-heading">Questions, Correct Answers &amp; WordPress Topic Links</p>
                    <textarea
                        id="<?php echo esc_attr($flosc_ta_id); ?>"
                        name="flow_quiz_content_<?php echo esc_attr( $flosc_quiz_id ); ?>"
                        rows="14"
                        class="large-text code"
                        class="large-text code flosc-textarea-12"
                        placeholder="<?php echo esc_attr( $flosc_qt->get_default_content() ); ?>"
                    ><?php echo esc_textarea( $flosc_content ); ?></textarea>
                    <p class="description flosc-description-top-6">
                        <strong>Format:</strong> <?php echo esc_html( $flosc_qt->get_instructions() ); ?>
                    </p>

                    <?php
                    $flosc_templates = $flosc_qt->get_default_response_templates();
                    if ( ! empty( $flosc_templates ) ):
                    ?>
                    <div class="flosc-top-border-panel">
                        <p class="flosc-micro-heading">Score Feedback Templates</p>
                        <p class="description flosc-description-margin-0-0-10">
                            Shown to the learner after scoring.
                            Placeholders: <code>{score}</code> <code>{total_correct}</code> <code>{total_possible}</code> <code>{lesson_recommendations}</code>
                        </p>
                        <table class="form-table flosc-form-table-reset flosc-form-table-reset-row">
                        <?php foreach ( $flosc_templates as $flosc_range => $flosc_default ):
                            $flosc_key   = 'quiz_' . $flosc_quiz_id . '_template_' . $flosc_range;
                            $flosc_value = ! empty( $flosc_flow_settings[ $flosc_key ] ) ? $flosc_flow_settings[ $flosc_key ] : $flosc_default;
                        ?>
                        <tr>
                            <th scope="row" class="flosc-score-template-col">
                                <label for="<?php echo esc_attr( 'flow_' . $flosc_key ); ?>"><?php echo esc_html( $flosc_range ); ?>%</label>
                            </th>
                            <td class="flosc-score-template-cell">
                                <textarea
                                    id="<?php echo esc_attr( 'flow_' . $flosc_key ); ?>"
                                    name="flow_<?php echo esc_attr( $flosc_key ); ?>"
                                    rows="2"
                                    class="large-text flosc-textarea-12"
                                ><?php echo esc_textarea( $flosc_value ); ?></textarea>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </details>
        </div>
    <?php endforeach; ?>

    <?php foreach ( $flosc_coming_quizzes as $flosc_quiz_id => $flosc_qt ):
        $flosc_content = ! empty( $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ] )
                    ? $flosc_flow_settings[ 'quiz_content_' . $flosc_quiz_id ]
                    : $flosc_qt->get_default_content();
        $flosc_ta_id   = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
        $flosc_det_id  = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
    ?>
        <div class="flosc-quiz-card flosc-quiz-card-dim">
            <h4>
                <span class="icon"><?php echo esc_html( $flosc_qt->get_icon() ); ?></span>
                <?php echo esc_html( $flosc_qt->get_name() ); ?>
                <span class="badge native">NATIVE</span>
            </h4>
            <p class="desc"><?php echo esc_html( $flosc_qt->get_description() ); ?></p>
            <p class="flosc-quiz-warning flosc-quiz-warning--block">
                🎤 Requires microphone + speech-to-text — not yet functional.
            </p>

            <details id="<?php echo esc_attr($flosc_det_id); ?>" class="flosc-margin-top-12">
                <summary class="flosc-details-summary flosc-details-summary--gray">
                    ✏️ Edit Quiz
                </summary>
                <div class="flosc-details-content-top-8">
                    <p class="flosc-micro-heading">Questions, Correct Answers &amp; WordPress Topic Links</p>
                    <textarea
                        id="<?php echo esc_attr($flosc_ta_id); ?>"
                        name="flow_quiz_content_<?php echo esc_attr( $flosc_quiz_id ); ?>"
                        rows="8"
                        class="large-text code flosc-textarea-12"
                        readonly
                    ><?php echo esc_textarea( $flosc_content ); ?></textarea>
                    <p class="description flosc-description-top-6">
                        <strong>Format:</strong> <?php echo esc_html( $flosc_qt->get_instructions() ); ?>
                    </p>
                </div>
            </details>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- ── Demo library ──────────────────────────────────────────────────── -->
    <details class="flosc-margin-top-28" open>
        <summary class="flosc-ai-section-heading">
            🎯 Demo Quiz Sets — load ready-made questions into any editor
        </summary>
        <div class="flosc-demo-panel">
            <p class="description flosc-demo-description">
                Click <strong>Load →</strong> to fill that quiz's editor with demo content. Then enable the quiz above, customize as needed, and save.
            </p>

            <?php foreach ( $flosc_quiz_demos as $flosc_quiz_id => $flosc_demos ):
                $flosc_qt = FLOSC_Quiz_Registry::get_quiz( $flosc_quiz_id );
                if ( ! $flosc_qt ) continue;
                $flosc_ta_id  = 'flosc_quiz_editor_' . esc_attr( $flosc_quiz_id );
                $flosc_det_id = 'flosc_quiz_details_' . esc_attr( $flosc_quiz_id );
            ?>
            <h4 class="flosc-demo-section-title">
                <?php echo esc_html( $flosc_qt->get_icon() . ' ' . $flosc_qt->get_name() ); ?>
            </h4>
            <div class="flosc-demo-list">
                <?php foreach ( $flosc_demos as $flosc_i => $flosc_demo ): ?>
                <div class="flosc-demo-item">
                    <div class="flosc-demo-item__body">
                        <strong class="flosc-demo-item__title"><?php echo esc_html( $flosc_demo['name'] ); ?></strong>
                        <span class="flosc-demo-item__desc"><?php echo esc_html( $flosc_demo['desc'] ); ?></span>
                    </div>
                    <textarea
                        id="flosc_demo_<?php echo esc_attr( $flosc_quiz_id ); ?>_<?php echo esc_attr((string) $flosc_i); ?>"
                        class="flosc-hidden"
                        readonly
                    ><?php echo esc_textarea( $flosc_demo['content'] ); ?></textarea>
                    <button
                        type="button"
                        class="button button-small flosc-load-demo"
                        data-textarea="<?php echo esc_attr( $flosc_ta_id ); ?>"
                        data-details="<?php echo esc_attr( $flosc_det_id ); ?>"
                        data-source="flosc_demo_<?php echo esc_attr( $flosc_quiz_id ); ?>_<?php echo esc_attr((string) $flosc_i); ?>"
                        class="button button-small flosc-load-demo flosc-nowrap-shrink"
                    >Load →</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- Score feedback templates now live inside each quiz card's ✏️ Edit Questions panel -->

    <!-- Save is handled by the main settings form Save button at top of page -->

</div>

<?php ob_start(); ?>
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.flosc-load-demo');
    if (!btn) return;

    var sourceId  = btn.dataset.source;
    var targetId  = btn.dataset.textarea;
    var detailsId = btn.dataset.details;

    var source  = document.getElementById(sourceId);
    var target  = document.getElementById(targetId);
    var details = document.getElementById(detailsId);

    if (!source || !target) return;

    target.value = source.value;

    if (details) details.open = true;
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.focus();

    var original = btn.textContent;
    btn.textContent = '✓ Loaded!';
    btn.disabled = true;
    setTimeout(function() {
        btn.textContent = original;
        btn.disabled = false;
    }, 1800);
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
