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
        return 'lesaep_pronunciation';
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
        return 'One question per block, separated by a blank line. Format: first line = question text, then A: option, B: option, C: option, D: option (2–4 options), then CORRECT: X on the last line.';
    }

    /**
     * Returns the 10 default questions in admin-editable text format.
     * Each block: question text, A:/B:/C:/D: options, CORRECT: key — blocks separated by blank lines.
     */
    public function get_default_content() {
        $lines = [];
        foreach ( $this->get_default_questions() as $q ) {
            $lines[] = $q['text'];
            foreach ( $q['options'] as $opt ) {
                $lines[] = $opt['key'] . ': ' . $opt['text'];
            }
            $lines[] = 'CORRECT: ' . $q['correct'];
            $lines[] = '';
        }
        return trim( implode( "\n", $lines ) );
    }

    /**
     * Parse admin-editable textarea content into the same array format as get_default_questions().
     */
    public function parse_content_to_questions( $content ) {
        $questions = [];
        $blocks    = preg_split( '/\n[ \t]*\n/', trim( $content ) );
        $qnum      = 0;
        foreach ( $blocks as $block ) {
            $block = trim( $block );
            if ( empty( $block ) ) continue;
            $lines         = array_values( array_filter( array_map( 'trim', explode( "\n", $block ) ) ) );
            $question_text = '';
            $options       = [];
            $correct       = '';
            foreach ( $lines as $line ) {
                if ( preg_match( '/^([A-D]):\s*(.+)$/i', $line, $m ) ) {
                    $options[] = [ 'key' => strtoupper( $m[1] ), 'text' => $m[2] ];
                } elseif ( preg_match( '/^CORRECT:\s*([A-D])$/i', $line, $m ) ) {
                    $correct = strtoupper( $m[1] );
                } elseif ( empty( $question_text ) ) {
                    $question_text = $line;
                }
            }
            if ( ! empty( $question_text ) && ! empty( $options ) && ! empty( $correct ) ) {
                $qnum++;
                $questions[] = [
                    'id'      => 'q' . $qnum,
                    'text'    => $question_text,
                    'options' => $options,
                    'correct' => $correct,
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
                $incorrect_lessons[] = $lesson_num;
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

            // Q1 → Lesson 1 — The /æ/ short-a vowel
            [
                'id'      => 'q1',
                'text'    => 'Which pair of words use the SAME vowel sound?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'cat / cut' ],
                    [ 'key' => 'B', 'text' => 'map / mop' ],
                    [ 'key' => 'C', 'text' => 'cat / map' ],
                    [ 'key' => 'D', 'text' => 'bat / bit' ],
                ],
                'correct' => 'C',
            ],

            // Q2 → Lesson 2 — The American rhotic R
            [
                'id'      => 'q2',
                'text'    => 'In Standard American English, the "r" in "car" and "bird" is:',
                'options' => [
                    [ 'key' => 'A', 'text' => 'Silent — not pronounced' ],
                    [ 'key' => 'B', 'text' => 'Trilled, like in Spanish' ],
                    [ 'key' => 'C', 'text' => 'Fully pronounced (rhotic r)' ],
                    [ 'key' => 'D', 'text' => 'Like the French uvular r' ],
                ],
                'correct' => 'C',
            ],

            // Q3 → Lesson 3 — Voiceless TH /θ/
            [
                'id'      => 'q3',
                'text'    => 'How do you correctly make the "th" sound in "think", "three", and "bath"?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'Like the "f" sound — e.g., "fink"' ],
                    [ 'key' => 'B', 'text' => 'Like the "t" sound — e.g., "tink"' ],
                    [ 'key' => 'C', 'text' => 'With the tongue tip near or lightly between the teeth' ],
                    [ 'key' => 'D', 'text' => 'Like the "s" sound — e.g., "sink"' ],
                ],
                'correct' => 'C',
            ],

            // Q4 → Lesson 4 — Voiced TH /ð/
            [
                'id'      => 'q4',
                'text'    => 'What is the key difference between the "th" in "think" and the "th" in "this"?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'They are pronounced exactly the same' ],
                    [ 'key' => 'B', 'text' => '"Think" = voiceless /θ/,  "this" = voiced /ð/' ],
                    [ 'key' => 'C', 'text' => '"This" drops the "th" — it is silent' ],
                    [ 'key' => 'D', 'text' => '"Think" = voiced,  "this" = voiceless' ],
                ],
                'correct' => 'B',
            ],

            // Q5 → Lesson 5 — /ɪ/ vs /iː/ (short I vs. long E)
            [
                'id'      => 'q5',
                'text'    => 'Which pair of words are pronounced DIFFERENTLY in American English?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'sheep / cheap' ],
                    [ 'key' => 'B', 'text' => 'ship / sheep' ],
                    [ 'key' => 'C', 'text' => 'feet / feat' ],
                    [ 'key' => 'D', 'text' => 'beat / beet' ],
                ],
                'correct' => 'B',
            ],

            // Q6 → Lesson 6 — Schwa /ə/ and unstressed vowels
            [
                'id'      => 'q6',
                'text'    => 'In the word "banana", how many syllables use the unstressed schwa /ə/ sound?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'None — all three vowels are fully pronounced' ],
                    [ 'key' => 'B', 'text' => 'One syllable (only the last one)' ],
                    [ 'key' => 'C', 'text' => 'Two syllables (the first and the last)' ],
                    [ 'key' => 'D', 'text' => 'All three syllables use schwa' ],
                ],
                'correct' => 'C',
            ],

            // Q7 → Lesson 7 — Flap T (American English)
            [
                'id'      => 'q7',
                'text'    => 'In fast natural American English, "butter", "water", and "better" all have what sound in the middle?',
                'options' => [
                    [ 'key' => 'A', 'text' => 'A clear, crisp "t" sound' ],
                    [ 'key' => 'B', 'text' => 'A silent "t" — the t is not heard at all' ],
                    [ 'key' => 'C', 'text' => 'A quick "d"-like tap (flap T)' ],
                    [ 'key' => 'D', 'text' => 'A glottal stop' ],
                ],
                'correct' => 'C',
            ],

            // Q8 → Lesson 8 — Word stress patterns
            [
                'id'      => 'q8',
                'text'    => 'Which word has stress on the FIRST syllable?',
                'options' => [
                    [ 'key' => 'A', 'text' => '"desert" (noun: the sandy land)' ],
                    [ 'key' => 'B', 'text' => '"repair"' ],
                    [ 'key' => 'C', 'text' => '"balloon"' ],
                    [ 'key' => 'D', 'text' => '"decide"' ],
                ],
                'correct' => 'A',
            ],

            // Q9 → Lesson 9 — Linking / connected speech
            [
                'id'      => 'q9',
                'text'    => 'In natural American English, "Turn it off" sounds most like:',
                'options' => [
                    [ 'key' => 'A', 'text' => 'Three separate, clearly divided words' ],
                    [ 'key' => 'B', 'text' => '"Tur-nit-off" — words linked smoothly together' ],
                    [ 'key' => 'C', 'text' => 'The "t" sounds are all dropped' ],
                    [ 'key' => 'D', 'text' => 'Only the last word is clearly heard' ],
                ],
                'correct' => 'B',
            ],

            // Q10 → Lesson 10 — Dark L vs. light L
            [
                'id'      => 'q10',
                'text'    => 'In American English, the "l" at the END of words like "full", "ball", and "feel" is:',
                'options' => [
                    [ 'key' => 'A', 'text' => 'The same as "l" at the start of "love" and "lake"' ],
                    [ 'key' => 'B', 'text' => 'Silent — not pronounced' ],
                    [ 'key' => 'C', 'text' => '"Dark l" — produced further back in the mouth' ],
                    [ 'key' => 'D', 'text' => 'Always replaced by a vowel sound' ],
                ],
                'correct' => 'C',
            ],

        ];
    }
}
