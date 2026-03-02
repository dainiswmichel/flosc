<?php
/**
 * LeSAEp Pronunciation Assessment Quiz Type
 *
 * 10-question accent assessment for Standard American English pronunciation.
 * Each question maps to a specific sound lesson (question N = lesson N).
 * Designed to score typical non-native speakers at 40–70% so the funnel
 * can deliver a free lesson on a missed sound.
 *
 * Lesson mapping:
 *   Q1  → Lesson 1:  The /æ/ short-a vowel (cat, map, back)
 *   Q2  → Lesson 2:  The American rhotic R (car, bird, butter)
 *   Q3  → Lesson 3:  Voiceless TH /θ/ (think, three, bath)
 *   Q4  → Lesson 4:  Voiced TH /ð/ (this, that, the)
 *   Q5  → Lesson 5:  /ɪ/ vs /iː/ — ship vs sheep
 *   Q6  → Lesson 6:  Schwa /ə/ and unstressed vowels (banana)
 *   Q7  → Lesson 7:  Flap T (butter = "budder")
 *   Q8  → Lesson 8:  Word stress patterns (DES-ert vs de-SERT)
 *   Q9  → Lesson 9:  Connected speech / linking (turn-it-off)
 *   Q10 → Lesson 10: Dark L vs. light L (full, ball, feel)
 *
 * @package FLOSC
 * @version 3.0.7
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class FLOSC_LeSAEp_Pronunciation_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'lesaep_text_based_pronunciation_quiz';
    }

    public function get_name() {
        return 'LeSAEp Pronunciation Assessment';
    }

    public function get_description() {
        return '10-question accent assessment for Standard American English. Identifies which sounds each learner needs to focus on first.';
    }

    public function get_icon() {
        return '🎤';
    }

    public function needs_audio() {
        return false;
    }

    public function needs_stt() {
        return false;
    }

    public function needs_ai_analysis() {
        return false;
    }

    public function get_instructions() {
        return "One question per block, separated by a blank line.\n\nEach block:\n  Question text\n  A: Option text\n  B: Option text\n  C: Option text  (D: optional)\n  CORRECT: A\n  CorrectContent: post:my-post-slug\n  CorrectContent: tag:my-tag, id:1042\n  RelatedContent: post:slug-one, category:parent/child\n  RelatedContent: tag:another-tag, id:1043\n  RelatedContent: search:any words here\n\nPrefixes — always required, no quotes:\n  post:slug              — post by URL slug; use post:parent/child if the same slug exists under multiple parents\n  id:1042           — one post by numeric ID\n  category:slug     — posts in a category; category:parent/child for sub-categories\n  tag:slug          — posts with a tag (use the tag slug, not the display name)\n  search:any words  — keyword search (avoid: unreliable, may match wrong posts)\n\nMultiple CorrectContent: and RelatedContent: lines all accumulate — pile them in. CorrectContent items are shown first (tier 1) when a learner asks to review what they got wrong.";
    }

    /**
     * Returns the 10 default questions in admin-editable text format.
     * Each block: question text, A:/B:/C:/D: options, CORRECT: key,
     * CorrectContent: slug, RelatedContent: slugs — separated by blank lines.
     */
    public function get_default_content() {
        $lines = [];
        foreach ( $this->get_default_questions() as $q ) {
            $lines[] = $q['text'];
            foreach ( $q['options'] as $opt ) {
                $lines[] = $opt['key'] . ': ' . $opt['text'];
            }
            $lines[] = 'CORRECT: ' . $q['correct'];
            foreach ( (array) ( $q['correct_content'] ?? [] ) as $ref ) {
                if ( $ref !== '' ) $lines[] = 'CorrectContent: ' . $ref;
            }
            if ( ! empty( $q['related_content'] ) ) {
                $lines[] = 'RelatedContent: ' . implode( ', ', (array) $q['related_content'] );
            }
            $lines[] = '';
        }
        return trim( implode( "\n", $lines ) );
    }

    /**
     * Parse admin-editable textarea content into the same array format as get_default_questions().
     * Supports optional lines per block:
     *   TOPIC: slug1, slug2            — general topic slugs (backward compat)
     *   CorrectContent: slug-or-id     — primary lesson reference (tier 1)
     *   RelatedContent: slug1, slug2   — secondary lesson references (tier 2)
     */
    public function parse_content_to_questions( $content ) {
        $questions = [];
        $blocks    = preg_split( '/\n[ \t]*\n/', trim( $content ) );
        $qnum      = 0;
        foreach ( $blocks as $block ) {
            $block = trim( $block );
            if ( empty( $block ) ) continue;
            $lines           = array_values( array_filter( array_map( 'trim', explode( "\n", $block ) ) ) );
            $question_text   = '';
            $options         = [];
            $correct         = '';
            $topics          = [];
            $correct_content = [];
            $related_content = [];
            foreach ( $lines as $line ) {
                if ( preg_match( '/^([A-D]):\s*(.+)$/i', $line, $m ) ) {
                    $options[] = [ 'key' => strtoupper( $m[1] ), 'text' => $m[2] ];
                } elseif ( preg_match( '/^CORRECT:\s*([A-D])$/i', $line, $m ) ) {
                    $correct = strtoupper( $m[1] );
                } elseif ( preg_match( '/^CorrectContent:\s*(.+)$/i', $line, $m ) ) {
                    // Appends — multiple CorrectContent: lines are all tier-1 (same as RelatedContent but primary)
                    foreach ( array_map( 'trim', explode( ',', $m[1] ) ) as $r ) {
                        if ( $r !== '' ) $correct_content[] = $r;
                    }
                } elseif ( preg_match( '/^RelatedContent:\s*(.+)$/i', $line, $m ) ) {
                    // Appends — multiple RelatedContent: lines are cumulative (vertical format supported)
                    foreach ( array_map( 'trim', explode( ',', $m[1] ) ) as $r ) {
                        if ( $r !== '' ) $related_content[] = $r;
                    }
                } elseif ( preg_match( '/^TOPIC:\s*(.+)$/i', $line, $m ) ) {
                    $topics = array_map( 'trim', explode( ',', $m[1] ) );
                } elseif ( empty( $question_text ) ) {
                    $question_text = $line;
                }
            }
            if ( ! empty( $question_text ) && ! empty( $options ) && ! empty( $correct ) ) {
                $qnum++;
                $questions[] = [
                    'id'              => 'q' . $qnum,
                    'text'            => $question_text,
                    'options'         => $options,
                    'correct'         => $correct,
                    'topics'          => $topics,
                    'correct_content' => $correct_content,
                    'related_content' => $related_content,
                ];
            }
        }
        return $questions;
    }

    public function validate_input( $input ) {
        return true;
    }

    /**
     * Analyze user answers and return correct/incorrect lesson numbers.
     * $input: comma-separated answer keys or array (e.g. "A,C,C,B,B,C,C,A,B,C")
     */
    public function analyze( $input, $expected_content, $context = [] ) {
        // Use admin-configured content when available; fall back to hardcoded defaults
        $questions = ! empty( $expected_content )
            ? $this->parse_content_to_questions( $expected_content )
            : [];
        if ( empty( $questions ) ) {
            $questions = $this->get_default_questions();
        }
        $user_answers    = is_array( $input ) ? $input : array_map( 'trim', explode( ',', $input ) );
        $correct_lessons = [];
        $incorrect_lessons = [];
        $total_correct   = 0;

        foreach ( $questions as $i => $question ) {
            $lesson_num  = $i + 1;
            $correct_key = strtoupper( trim( $question['correct'] ) );
            $user_key    = isset( $user_answers[ $i ] ) ? strtoupper( trim( $user_answers[ $i ] ) ) : '';

            if ( $user_key === $correct_key ) {
                $correct_lessons[] = $lesson_num;
                $total_correct++;
            } else {
                $incorrect_lessons[] = [
                    'lesson'          => $lesson_num,
                    'topics'          => $question['topics'] ?? [],
                    'correct_content' => $question['correct_content'] ?? [],
                    'related_content' => $question['related_content'] ?? [],
                ];
            }
        }

        $total = count( $questions );
        $score = $total > 0 ? (int) round( ( $total_correct / $total ) * 100 ) : 0;

        return [
            'score'        => $score,
            'correct'      => $correct_lessons,
            'incorrect'    => $incorrect_lessons,
            'response_key' => $this->get_response_key_from_score( $score ),
            'details'      => [
                'total_correct'   => $total_correct,
                'total_possible'  => $total,
            ],
        ];
    }

    public function get_settings_fields() {
        return [];
    }

    /**
     * Return the 10 pronunciation assessment questions.
     * Public so get_quiz_questions() in flosc.php can call it directly.
     *
     * @return array
     */
    public function get_default_questions() {
        return [

            // Q1 — The /æ/ short-a vowel (Lesson 1)
            [
                'id'              => 'q1',
                'text'            => 'Which pair of words use the SAME vowel sound?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'cat / cut' ],
                    [ 'key' => 'B', 'text' => 'map / mop' ],
                    [ 'key' => 'C', 'text' => 'cat / map' ],
                    [ 'key' => 'D', 'text' => 'bat / bit' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-1-cat-bat-mat',
                'related_content' => [ 'post:lesson-5-rub-bluff-truck', 'post:lesson-4-hot-top-clock', 'post:lesson-3-sit-skip-spin' ],
                'topics'          => [],
            ],

            // Q2 — The American rhotic R (Lesson 47 + r-controlled vowels)
            [
                'id'              => 'q2',
                'text'            => 'In Standard American English, the "r" in "car" and "bird" is:',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'Silent — not pronounced' ],
                    [ 'key' => 'B', 'text' => 'Trilled, like in Spanish' ],
                    [ 'key' => 'C', 'text' => 'Fully pronounced (rhotic r)' ],
                    [ 'key' => 'D', 'text' => 'Like the French uvular r' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-47-r-rock-orange-care',
                'related_content' => [ 'post:lesson-12-r-car-bark-smart-star', 'post:lesson-13-r-r-her-bird-birth' ],
                'topics'          => [],
            ],

            // Q3 — Voiceless TH /θ/ (Lesson 35)
            [
                'id'              => 'q3',
                'text'            => 'How do you correctly make the "th" sound in "think", "three", and "bath"?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'Like the "f" sound — e.g., "fink"' ],
                    [ 'key' => 'B', 'text' => 'Like the "t" sound — e.g., "tink"' ],
                    [ 'key' => 'C', 'text' => 'With the tongue tip near or lightly between the teeth' ],
                    [ 'key' => 'D', 'text' => 'Like the "s" sound — e.g., "sink"' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-35-three-athens-froth',
                'related_content' => [ 'post:lesson-36-the-feather-scathe' ],
                'topics'          => [],
            ],

            // Q4 — Voiced TH /ð/ (Lesson 36)
            [
                'id'              => 'q4',
                'text'            => 'What is the key difference between the "th" in "think" and the "th" in "this"?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'They are pronounced exactly the same' ],
                    [ 'key' => 'B', 'text' => '"Think" = voiceless /θ/,  "this" = voiced /ð/' ],
                    [ 'key' => 'C', 'text' => '"This" drops the "th" — it is silent' ],
                    [ 'key' => 'D', 'text' => '"Think" = voiced,  "this" = voiceless' ],
                ],
                'correct'         => 'B',
                'correct_content' => 'post:lesson-36-the-feather-scathe',
                'related_content' => [ 'post:lesson-35-three-athens-froth' ],
                'topics'          => [],
            ],

            // Q5 — /ɪ/ vs /iː/ short-I vs long-E (Lessons 3 + 8)
            [
                'id'              => 'q5',
                'text'            => 'Which pair of words are pronounced DIFFERENTLY in American English?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'sheep / cheap' ],
                    [ 'key' => 'B', 'text' => 'ship / sheep' ],
                    [ 'key' => 'C', 'text' => 'feet / feat' ],
                    [ 'key' => 'D', 'text' => 'beat / beet' ],
                ],
                'correct'         => 'B',
                'correct_content' => 'post:lesson-3-sit-skip-spin',
                'related_content' => [ 'post:lesson-8-i-he-she-we-be' ],
                'topics'          => [],
            ],

            // Q6 — Schwa /ə/ and unstressed vowels (Lessons 20.1, 20.2, 20.3)
            [
                'id'              => 'q6',
                'text'            => 'In the word "banana", how many syllables use the unstressed schwa /ə/ sound?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'None — all three vowels are fully pronounced' ],
                    [ 'key' => 'B', 'text' => 'One syllable (only the last one)' ],
                    [ 'key' => 'C', 'text' => 'Two syllables (the first and the last)' ],
                    [ 'key' => 'D', 'text' => 'All three syllables use schwa' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-20-1-introduction-to-the-schwa',
                'related_content' => [ 'post:lesson-20-2-replacing-unstressed-vowels', 'post:lesson-20-3-exploring-the-schwa' ],
                'topics'          => [],
            ],

            // Q7 — Flap T in American English (Lesson 28)
            [
                'id'              => 'q7',
                'text'            => 'In fast natural American English, "butter", "water", and "better" all have what sound in the middle?',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'A clear, crisp "t" sound' ],
                    [ 'key' => 'B', 'text' => 'A silent "t" — the t is not heard at all' ],
                    [ 'key' => 'C', 'text' => 'A quick "d"-like tap (flap T)' ],
                    [ 'key' => 'D', 'text' => 'A glottal stop' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-28-t-d-tip-dip-cat-dad',
                'related_content' => [ 'post:lesson-23-t-t-tap-attack-cat', 'post:lesson-24-d-dog-adorable-blinded' ],
                'topics'          => [],
            ],

            // Q8 — Word stress patterns (Lessons 20.2, 20.1, 20.3)
            [
                'id'              => 'q8',
                'text'            => 'Which word has stress on the FIRST syllable?',
                'options'         => [
                    [ 'key' => 'A', 'text' => '"desert" (noun: the sandy land)' ],
                    [ 'key' => 'B', 'text' => '"repair"' ],
                    [ 'key' => 'C', 'text' => '"balloon"' ],
                    [ 'key' => 'D', 'text' => '"decide"' ],
                ],
                'correct'         => 'A',
                'correct_content' => 'post:lesson-20-2-replacing-unstressed-vowels',
                'related_content' => [ 'post:lesson-20-1-introduction-to-the-schwa', 'post:lesson-20-3-exploring-the-schwa' ],
                'topics'          => [],
            ],

            // Q9 — Linking / connected speech (Lesson 28 + 20.2)
            [
                'id'              => 'q9',
                'text'            => 'In natural American English, "Turn it off" sounds most like:',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'Three separate, clearly divided words' ],
                    [ 'key' => 'B', 'text' => '"Tur-nit-off" — words linked smoothly together' ],
                    [ 'key' => 'C', 'text' => 'The "t" sounds are all dropped' ],
                    [ 'key' => 'D', 'text' => 'Only the last word is clearly heard' ],
                ],
                'correct'         => 'B',
                'correct_content' => 'post:lesson-28-t-d-tip-dip-cat-dad',
                'related_content' => [ 'post:lesson-20-2-replacing-unstressed-vowels', 'post:lesson-47-r-rock-orange-care' ],
                'topics'          => [],
            ],

            // Q10 — Dark L vs. light L (Lesson 50)
            [
                'id'              => 'q10',
                'text'            => 'In American English, the "l" at the END of words like "full", "ball", and "feel" is:',
                'options'         => [
                    [ 'key' => 'A', 'text' => 'The same as "l" at the start of "love" and "lake"' ],
                    [ 'key' => 'B', 'text' => 'Silent — not pronounced' ],
                    [ 'key' => 'C', 'text' => '"Dark l" — produced further back in the mouth' ],
                    [ 'key' => 'D', 'text' => 'Always replaced by a vowel sound' ],
                ],
                'correct'         => 'C',
                'correct_content' => 'post:lesson-50-l-love-balloon-owl',
                'related_content' => [ 'post:lesson-47-r-rock-orange-care', 'post:lesson-49-w-j-wonder-rocky-yes' ],
                'topics'          => [],
            ],

        ];
    }
}
