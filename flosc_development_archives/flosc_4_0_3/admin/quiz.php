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

$flow_settings   = $GLOBALS['flosc_current_settings'] ?? [];
$enabled_quizzes = $flow_settings['enabled_quizzes'] ?? ['flosc_sample_text_based_quiz'];
if ( ! is_array( $enabled_quizzes ) ) $enabled_quizzes = ['flosc_sample_text_based_quiz'];
$all_quiz_types  = FLOSC_Quiz_Type_Factory::get_all_quiz_types();

if ( empty( $all_quiz_types ) ) {
    echo '<div class="notice notice-error"><p>No quiz types registered. Please reinstall FLOSC.</p></div>';
    return;
}

// ── Demo library ──────────────────────────────────────────────────────────────
// Each entry: [ 'name', 'desc', 'content' ]
// Content format must match the quiz type's own get_instructions() format.
$quiz_demos = [

    // ── LeSAEp Pronunciation (Q+options block format) ──────────────────────
    'lesaep_pronunciation' => [

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
    'flosc_sample_text_based_quiz' => [

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

    <!-- ── Active quiz cards (functional, no STT required) ──────────────── -->
    <h3>📋 Active Quizzes</h3>
    <p>Enable quizzes and edit their questions. Load a demo below to get started quickly.</p>

    <?php
    $ready_quizzes  = [];
    $coming_quizzes = [];
    foreach ( $all_quiz_types as $qid => $qt ) {
        if ( method_exists( $qt, 'needs_stt' ) && $qt->needs_stt() ) {
            $coming_quizzes[ $qid ] = $qt;
        } else {
            $ready_quizzes[ $qid ] = $qt;
        }
    }
    ?>

    <div class="flosc-quiz-grid">
    <?php foreach ( $ready_quizzes as $quiz_id => $qt ):
        $is_enabled = in_array( $quiz_id, $enabled_quizzes );
        $content    = $flow_settings[ 'quiz_content_' . $quiz_id ] ?? $qt->get_default_content();
        $ta_id      = 'flosc_quiz_editor_' . esc_attr( $quiz_id );
        $det_id     = 'flosc_quiz_details_' . esc_attr( $quiz_id );
    ?>
        <div class="flosc-quiz-card <?php echo $is_enabled ? 'active' : ''; ?>">
            <h4>
                <span class="icon"><?php echo esc_html( $qt->get_icon() ); ?></span>
                <?php echo esc_html( $qt->get_name() ); ?>
                <span class="badge native">NATIVE</span>
            </h4>
            <p class="desc"><?php echo esc_html( $qt->get_description() ); ?></p>

            <div class="flosc-quiz-toggle">
                <input type="checkbox"
                       name="flow_enabled_quizzes[]"
                       value="<?php echo esc_attr( $quiz_id ); ?>"
                       <?php checked( $is_enabled ); ?>>
                <label>Enabled</label>
            </div>

            <details id="<?php echo $det_id; ?>" style="margin-top:12px;" <?php echo $is_enabled ? 'open' : ''; ?>>
                <summary style="cursor:pointer;color:#0073aa;font-size:12px;font-weight:600;list-style:none;display:flex;align-items:center;gap:4px;">
                    ✏️ Edit Questions
                </summary>
                <div style="margin-top:8px;">
                    <textarea
                        id="<?php echo $ta_id; ?>"
                        name="flow_quiz_content_<?php echo esc_attr( $quiz_id ); ?>"
                        rows="12"
                        class="large-text code"
                        style="font-size:12px;"
                        placeholder="<?php echo esc_attr( $qt->get_default_content() ); ?>"
                    ><?php echo esc_textarea( $content ); ?></textarea>
                    <p class="description" style="margin-top:6px;">
                        <strong>Format:</strong> <?php echo esc_html( $qt->get_instructions() ); ?>
                    </p>
                </div>
            </details>
        </div>
    <?php endforeach; ?>
    </div>

    <!-- ── Quiz Deck — Coming Soon ────────────────────────────────────────── -->
    <?php if ( ! empty( $coming_quizzes ) ): ?>
    <h3 style="margin-top:32px;">🗂 Quiz Deck — Coming Soon</h3>
    <p class="description" style="margin-bottom:12px;">
        These quiz types are in development. They require microphone access and speech-to-text processing that is not yet available. Preview the format — they cannot be enabled yet.
    </p>
    <div class="flosc-quiz-grid">
    <?php foreach ( $coming_quizzes as $quiz_id => $qt ):
        $content = $flow_settings[ 'quiz_content_' . $quiz_id ] ?? $qt->get_default_content();
        $ta_id   = 'flosc_quiz_editor_' . esc_attr( $quiz_id );
        $det_id  = 'flosc_quiz_details_' . esc_attr( $quiz_id );
    ?>
        <div class="flosc-quiz-card" style="opacity:0.7;border-style:dashed;">
            <h4>
                <span class="icon"><?php echo esc_html( $qt->get_icon() ); ?></span>
                <?php echo esc_html( $qt->get_name() ); ?>
                <span class="badge" style="background:#f0ad4e;color:#5a3e00;">🚧 COMING SOON</span>
            </h4>
            <p class="desc"><?php echo esc_html( $qt->get_description() ); ?></p>
            <p style="font-size:12px;color:#996633;background:#fff8e1;border:1px solid #f0ad4e;border-radius:4px;padding:6px 10px;margin:8px 0 0;">
                🎤 Requires microphone + speech-to-text — in development
            </p>

            <details id="<?php echo $det_id; ?>" style="margin-top:12px;">
                <summary style="cursor:pointer;color:#6c757d;font-size:12px;font-weight:600;list-style:none;display:flex;align-items:center;gap:4px;">
                    👁 Preview Format
                </summary>
                <div style="margin-top:8px;">
                    <textarea
                        id="<?php echo $ta_id; ?>"
                        name="flow_quiz_content_<?php echo esc_attr( $quiz_id ); ?>"
                        rows="8"
                        class="large-text code"
                        style="font-size:12px;color:#888;"
                        disabled
                    ><?php echo esc_textarea( $content ); ?></textarea>
                    <p class="description" style="margin-top:6px;">
                        <strong>Format:</strong> <?php echo esc_html( $qt->get_instructions() ); ?>
                    </p>
                </div>
            </details>
        </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ── Demo library ──────────────────────────────────────────────────── -->
    <details style="margin-top:28px;" open>
        <summary style="cursor:pointer;font-size:15px;font-weight:600;padding:10px 0;">
            🎯 Demo Quiz Sets — load ready-made questions into any editor
        </summary>
        <div style="margin-top:12px;padding:16px;background:#f9f9f9;border-radius:6px;">
            <p class="description" style="margin-bottom:16px;">
                Click <strong>Load →</strong> to fill that quiz's editor with demo content. Then enable the quiz above, customize as needed, and save.
            </p>

            <?php foreach ( $quiz_demos as $quiz_id => $demos ):
                $qt = FLOSC_Quiz_Type_Factory::get_quiz_type( $quiz_id );
                if ( ! $qt ) continue;
                $ta_id  = 'flosc_quiz_editor_' . esc_attr( $quiz_id );
                $det_id = 'flosc_quiz_details_' . esc_attr( $quiz_id );
            ?>
            <h4 style="margin:20px 0 10px;font-size:13px;color:#1d2327;border-bottom:1px solid #e0e0e0;padding-bottom:6px;">
                <?php echo esc_html( $qt->get_icon() . ' ' . $qt->get_name() ); ?>
            </h4>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <?php foreach ( $demos as $i => $demo ): ?>
                <div style="background:#fff;border:1px solid #ddd;border-radius:5px;padding:12px 14px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                    <div style="flex:1;">
                        <strong style="font-size:13px;"><?php echo esc_html( $demo['name'] ); ?></strong>
                        <span style="display:block;font-size:12px;color:#50575e;margin-top:2px;"><?php echo esc_html( $demo['desc'] ); ?></span>
                    </div>
                    <textarea
                        id="flosc_demo_<?php echo esc_attr( $quiz_id ); ?>_<?php echo $i; ?>"
                        style="display:none;"
                        readonly
                    ><?php echo esc_textarea( $demo['content'] ); ?></textarea>
                    <button
                        type="button"
                        class="button button-small flosc-load-demo"
                        data-textarea="<?php echo esc_attr( $ta_id ); ?>"
                        data-details="<?php echo esc_attr( $det_id ); ?>"
                        data-source="flosc_demo_<?php echo esc_attr( $quiz_id ); ?>_<?php echo $i; ?>"
                        style="flex-shrink:0;white-space:nowrap;"
                    >Load →</button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- ── Response templates (enabled quizzes only) ─────────────────────── -->
    <?php foreach ( $enabled_quizzes as $quiz_id ):
        $qt = FLOSC_Quiz_Type_Factory::get_quiz_type( $quiz_id );
        if ( ! $qt ) continue;
        $templates = $qt->get_default_response_templates();
        if ( empty( $templates ) ) continue;
    ?>
    <h3 style="margin-top:28px;">
        <?php echo esc_html( $qt->get_icon() . ' ' . $qt->get_name() ); ?> — Response Templates
    </h3>
    <p class="description">
        Feedback shown after scoring. Placeholders:
        <code>{score}</code> <code>{total_correct}</code> <code>{total_possible}</code> <code>{lesson_recommendations}</code>
    </p>
    <table class="form-table" style="margin-bottom:0;">
        <?php foreach ( $templates as $range => $default ):
            $key   = 'quiz_' . $quiz_id . '_template_' . $range;
            $value = $flow_settings[ $key ] ?? $default;
        ?>
        <tr>
            <th scope="row" style="width:140px;">
                <label for="<?php echo esc_attr( 'flow_' . $key ); ?>">Score <?php echo esc_html( $range ); ?>%</label>
            </th>
            <td>
                <textarea
                    id="<?php echo esc_attr( 'flow_' . $key ); ?>"
                    name="flow_<?php echo esc_attr( $key ); ?>"
                    rows="3"
                    class="large-text"
                ><?php echo esc_textarea( $value ); ?></textarea>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php endforeach; ?>

    <!-- Save is handled by the main settings form Save button at top of page -->

</div>

<script>
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
</script>
