<?php
/**
 * LeSAEp Phoneme Analyzer
 *
 * Compares STT transcript against reference text at the word level.
 * For each mismatched word, determines which phonemes differ using
 * IPA lookup + phoneme-to-lesson mapping.
 *
 * Phoneme mapping covers all 52 LeSAEp lessons (L1-L52).
 *
 * @package FLOSC
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class LeSAEp_Phoneme_Analyzer {

    /**
     * Complete L1-L52 lesson mapping
     * Each entry: lesson_id => [ipa, title, phoneme_chars[], examples]
     * phoneme_chars are the IPA characters that trigger this lesson
     */
    private $lessons = [
        // Vowels L1-L11
        1  => ['ipa' => 'æ',      'title' => 'L1: [æ] Short A',           'chars' => ['æ']],
        2  => ['ipa' => 'ɛ',      'title' => 'L2: [ɛ] Short E',           'chars' => ['ɛ']],
        3  => ['ipa' => 'ɪ',      'title' => 'L3: [ɪ] Short I',           'chars' => ['ɪ']],
        4  => ['ipa' => 'ɑ',      'title' => 'L4: [ɑ] Short O',           'chars' => ['ɑ', 'ɔ']],
        5  => ['ipa' => 'ʌ',      'title' => 'L5: [ʌ] Short U',           'chars' => ['ʌ']],
        6  => ['ipa' => 'ʊ',      'title' => 'L6: [ʊ] as in book',        'chars' => ['ʊ']],
        7  => ['ipa' => 'eɪ',     'title' => 'L7: [eɪ] Long A',           'chars' => ['eɪ']],
        8  => ['ipa' => 'i',      'title' => 'L8: [i] Long E',            'chars' => ['i']],
        9  => ['ipa' => 'aɪ',     'title' => 'L9: [aɪ] Long I',           'chars' => ['aɪ']],
        10 => ['ipa' => 'oʊ',     'title' => 'L10: [oʊ] Long O',          'chars' => ['oʊ']],
        11 => ['ipa' => 'u',      'title' => 'L11: [u] Long U',           'chars' => ['u']],
        // R-colored vowels L12-L19
        12 => ['ipa' => 'ɑr',     'title' => 'L12: [ɑr] as in car',       'chars' => ['ɑr']],
        13 => ['ipa' => 'ɝ',      'title' => 'L13: [ɝ/ɜr/œr] as in bird', 'chars' => ['ɝ', 'ɜr', 'ɚ']],
        14 => ['ipa' => 'ɔr',     'title' => 'L14: [ɔr] as in cork',      'chars' => ['ɔr']],
        15 => ['ipa' => 'aʊ',     'title' => 'L15: [aʊ] as in house',     'chars' => ['aʊ']],
        16 => ['ipa' => 'ɔɪ',     'title' => 'L16: [ɔɪ] as in joy',       'chars' => ['ɔɪ']],
        17 => ['ipa' => 'ɪr',     'title' => 'L17: [ɪr/iɚ] as in fear',   'chars' => ['ɪr']],
        18 => ['ipa' => 'ɛr',     'title' => 'L18: [ɛr] as in hair',      'chars' => ['ɛr']],
        19 => ['ipa' => 'ʊr',     'title' => 'L19: [ʊr] as in sure',      'chars' => ['ʊr']],
        // Schwa L20
        20 => ['ipa' => 'ə',      'title' => 'L20: [ə] Schwa',            'chars' => ['ə']],
        // Plosives L21-L26
        21 => ['ipa' => 'p',      'title' => 'L21: [p/pʰ]',               'chars' => ['p']],
        22 => ['ipa' => 'b',      'title' => 'L22: [b]',                   'chars' => ['b']],
        23 => ['ipa' => 't',      'title' => 'L23: [t/tʰ]',               'chars' => ['t']],
        24 => ['ipa' => 'd',      'title' => 'L24: [d]',                   'chars' => ['d']],
        25 => ['ipa' => 'k',      'title' => 'L25: [k/kʰ]',               'chars' => ['k']],
        26 => ['ipa' => 'ɡ',      'title' => 'L26: [ɡ]',                   'chars' => ['ɡ', 'g']],
        // Plosive comparisons L27-L29
        27 => ['ipa' => 'pʰ/b',   'title' => 'L27: [pʰ/b] Comparison',    'chars' => []],
        28 => ['ipa' => 'tʰ/d',   'title' => 'L28: [tʰ/d] Comparison',    'chars' => []],
        29 => ['ipa' => 'kʰ/ɡ',   'title' => 'L29: [kʰ/ɡ] Comparison',    'chars' => []],
        // Nasals L30-L32
        30 => ['ipa' => 'm',      'title' => 'L30: [m]',                   'chars' => ['m']],
        31 => ['ipa' => 'n',      'title' => 'L31: [n]',                   'chars' => ['n']],
        32 => ['ipa' => 'ŋ',      'title' => 'L32: [ŋ]',                   'chars' => ['ŋ']],
        // Fricatives L33-L41
        33 => ['ipa' => 'f',      'title' => 'L33: [f]',                   'chars' => ['f']],
        34 => ['ipa' => 'v',      'title' => 'L34: [v]',                   'chars' => ['v']],
        35 => ['ipa' => 'θ',      'title' => 'L35: [θ]',                   'chars' => ['θ']],
        36 => ['ipa' => 'ð',      'title' => 'L36: [ð]',                   'chars' => ['ð']],
        37 => ['ipa' => 's',      'title' => 'L37: [s]',                   'chars' => ['s']],
        38 => ['ipa' => 'z',      'title' => 'L38: [z]',                   'chars' => ['z']],
        39 => ['ipa' => 'ʃ',      'title' => 'L39: [ʃ]',                   'chars' => ['ʃ']],
        40 => ['ipa' => 'ʒ',      'title' => 'L40: [ʒ]',                   'chars' => ['ʒ']],
        41 => ['ipa' => 'h',      'title' => 'L41: [h]',                   'chars' => ['h']],
        // Affricates L42-L44
        42 => ['ipa' => 'ʧ',      'title' => 'L42: [ʧ]',                   'chars' => ['tʃ', 'ʧ']],
        43 => ['ipa' => 'dʒ',     'title' => 'L43: [dʒ]',                  'chars' => ['dʒ']],
        44 => ['ipa' => 'ʧ/dʒ',   'title' => 'L44: [ʧ/dʒ] Comparison',    'chars' => []],
        // Approximants L45-L50
        45 => ['ipa' => 'w',      'title' => 'L45: [w]',                   'chars' => ['w']],
        46 => ['ipa' => 'w/wʰ',   'title' => 'L46: [w/wʰ] Comparison',    'chars' => []],
        47 => ['ipa' => 'r',      'title' => 'L47: [r/ɹ̠]',                'chars' => ['r', 'ɹ']],
        48 => ['ipa' => 'j',      'title' => 'L48: [j]',                   'chars' => ['j']],
        49 => ['ipa' => 'w-ɹ̠-j',  'title' => 'L49: [w-ɹ̠-j] Comparison',  'chars' => []],
        50 => ['ipa' => 'l',      'title' => 'L50: [l]',                   'chars' => ['l']],
        // Clusters L51-L52
        51 => ['ipa' => 'ks',     'title' => 'L51: [ks]',                  'chars' => ['ks']],
        52 => ['ipa' => 'kw',     'title' => 'L52: [kw]',                  'chars' => ['kw']],
    ];

    /**
     * Map: each reference word → which lesson IDs it primarily tests.
     * Built from the reference paragraph. Words that test a specific phoneme
     * are mapped here so that when a word is mispronounced, we know which
     * lesson(s) to recommend.
     *
     * This is the authoritative word→lesson mapping. The reference paragraph
     * is designed so that each word targets specific phonemes.
     */
    private $word_lesson_map = [
        // L1 æ
        'cat' => [1], 'bat' => [1],
        // L2 ɛ
        'bed' => [2], 'get' => [2],
        // L3 ɪ
        'skip' => [3],
        // L4 ɑ
        'clock' => [4], 'stock' => [4], 'stalk' => [4], 'cot' => [4],
        // L5 ʌ
        'truck' => [5], 'cut' => [5], 'button' => [5],
        // L6 ʊ
        'cook' => [6],
        // L7 eɪ
        'paid' => [7], 'sail' => [7],
        // L8 i
        'we' => [8], 'meal' => [8], 'celery' => [8],
        // L9 aɪ
        'pie' => [9],
        // L10 oʊ
        'flow' => [10],
        // L11 u
        'bloom' => [11], 'tune' => [11],
        // L12 ɑr
        'bark' => [12],
        // L13 ɝ
        'bird' => [13], 'birth' => [13],
        // L14 ɔr
        'cork' => [14], 'north' => [14], 'horse' => [14], 'hoarse' => [14],
        'war' => [14], 'wore' => [14],
        // L15 aʊ
        'house' => [15], 'clown' => [15],
        // L16 ɔɪ
        'joy' => [16], 'voice' => [16], 'choice' => [16],
        // L17 ɪr
        'veer' => [17], 'steer' => [17],
        // L18 ɛr
        'hair' => [18], 'wear' => [18], 'compare' => [18], 'vary' => [18], 'very' => [18],
        // L19 ʊr
        'sure' => [19], 'cure' => [19],
        // L20 ə
        'poodle' => [20], 'comfortable' => [20], 'temperature' => [20], 'beautiful' => [20],
        // L21 p
        'power' => [21], 'apparent' => [21],
        // L22 b
        'boom' => [22], 'trouble' => [22],
        // L23 t
        'tap' => [23], 'attack' => [23],
        // L24 d
        'adorable' => [24], 'dog' => [24],
        // L25 k
        'cap' => [25], 'accredited' => [25],
        // L26 ɡ
        'good' => [26], 'jogging' => [26],
        // L27 pʰ/b comparison — triggered when p or b words are wrong
        // L28 tʰ/d comparison — triggered when t or d words are wrong
        // L29 kʰ/ɡ comparison — triggered when k or g words are wrong
        // L30 m
        'moon' => [30], 'yummy' => [30],
        // L31 n
        'noodle' => [31], 'announce' => [31],
        // L32 ŋ
        'greeting' => [32], 'driving' => [32],
        // L33 f
        'fan' => [33], 'laugh' => [33],
        // L34 v
        'van' => [34], 'beaver' => [34],
        // L35 θ
        'three' => [35], 'froth' => [35],
        // L36 ð
        'the' => [36], 'feather' => [36], 'scathe' => [36],
        // L37 s
        'see' => [37], 'muscle' => [37],
        // L38 z
        'zoo' => [38], 'sunrise' => [38],
        // L39 ʃ
        'shape' => [39], 'lotion' => [39], 'brush' => [39],
        // L40 ʒ
        'beige' => [40], 'pleasure' => [40], 'casual' => [40],
        // L41 h
        'hop' => [41], 'rehearse' => [41],
        // L42 ʧ
        'children' => [42], 'ketchup' => [42],
        // L43 dʒ
        'jeans' => [43], 'badger' => [43], 'marriage' => [43],
        // L44 ʧ/dʒ comparison
        'joke' => [43, 44],
        // L45 w
        'wet' => [45], 'liquid' => [45],
        // L46 w/wʰ
        'want' => [45, 46], 'nowhere' => [45, 46],
        // L47 r
        'rock' => [47], 'orange' => [47],
        // L48 j
        'yes' => [48], 'player' => [48],
        // L49 w-ɹ̠-j comparison — triggered contextually
        // L50 l
        'love' => [50], 'balloon' => [50],
        // L51 ks
        'box' => [51], 'rocks' => [51],
        // L52 kw
        'quiet' => [52], 'counterclockwise' => [52],
        // Nuance-adjacent (these words also test primary phonemes above)
        'water' => [45, 23], 'ladder' => [50, 24],
        'caught' => [4, 14], 'pin' => [3], 'pen' => [2],
        'mill' => [3, 50], 'sell' => [37, 2],
        'salary' => [1, 37], 'order' => [14],
    ];

    /**
     * Main analysis entry point
     *
     * @param string $transcript What the STT heard
     * @param string $expected Reference text the user was reading
     * @param array $word_confidences Optional: [{word, confidence}, ...] from STT
     * @return array Analysis results
     */
    public function analyze($transcript, $expected, $word_confidences = []) {
        $expected_words = $this->tokenize($expected);
        $transcript_words = $this->tokenize($transcript);

        // Build confidence lookup if provided
        $conf_lookup = [];
        foreach ($word_confidences as $wc) {
            $w = mb_strtolower(trim($wc['word'] ?? ''));
            if ($w !== '') {
                $conf_lookup[$w] = (float) ($wc['confidence'] ?? 1.0);
            }
        }

        $threshold = (float) get_option('flosc_quiz_lesaep_pronunciation_quiz_confidence_threshold', 0.6);

        // Align expected vs transcript using sequence matching
        $alignment = $this->align_words($expected_words, $transcript_words);

        $correct_words = [];
        $incorrect_words = [];
        $skipped_words = [];
        $word_details = [];

        // Track which lessons were hit vs missed
        $lessons_hit = [];
        $lessons_missed = [];

        foreach ($alignment as $pair) {
            $exp = $pair['expected'];
            $got = $pair['transcript'];
            $exp_lower = mb_strtolower($exp);

            if ($got === null) {
                // Word was omitted entirely
                $skipped_words[] = $exp;
                $word_details[] = [
                    'expected'   => $exp,
                    'heard'      => '(omitted)',
                    'status'     => 'omitted',
                    'confidence' => 0,
                    'lessons'    => $this->word_lesson_map[$exp_lower] ?? [],
                ];
                $this->flag_missed($exp_lower, $lessons_missed);
                continue;
            }

            $got_lower = mb_strtolower($got);
            $confidence = $conf_lookup[$got_lower] ?? null;

            // Match check: exact match (case-insensitive) AND confidence above threshold
            $text_match = ($exp_lower === $got_lower);
            $conf_ok = ($confidence === null || $confidence >= $threshold);

            if ($text_match && $conf_ok) {
                $correct_words[] = $exp;
                $word_details[] = [
                    'expected'   => $exp,
                    'heard'      => $got,
                    'status'     => 'correct',
                    'confidence' => $confidence,
                    'lessons'    => $this->word_lesson_map[$exp_lower] ?? [],
                ];
                $this->flag_hit($exp_lower, $lessons_hit);
            } else {
                $incorrect_words[] = $exp;
                $reason = !$text_match
                    ? "heard \"{$got}\" instead of \"{$exp}\""
                    : "low confidence (" . round($confidence, 2) . ") on \"{$exp}\"";

                $word_details[] = [
                    'expected'   => $exp,
                    'heard'      => $got,
                    'status'     => 'incorrect',
                    'confidence' => $confidence,
                    'reason'     => $reason,
                    'lessons'    => $this->word_lesson_map[$exp_lower] ?? [],
                ];
                $this->flag_missed($exp_lower, $lessons_missed);
            }
        }

        // Comparison lessons (L27-L29, L44, L46, L49) are triggered when
        // BOTH members of a pair are missed
        $this->check_comparison_lessons($lessons_hit, $lessons_missed);

        // Calculate score: percentage of expected words pronounced correctly
        $total = count($expected_words);
        $matched = count($correct_words);
        $score = $total > 0 ? round(($matched / $total) * 100) : 0;

        // Build missed lesson recommendations
        $missed_lesson_ids = array_unique(array_keys($lessons_missed));
        sort($missed_lesson_ids);

        $missed_lessons = [];
        foreach ($missed_lesson_ids as $lid) {
            if (!isset($this->lessons[$lid])) continue;
            $lesson = $this->lessons[$lid];
            $words = $lessons_missed[$lid] ?? [];
            $missed_lessons[] = [
                'lesson_id'  => $lid,
                'title'      => $lesson['title'],
                'ipa'        => $lesson['ipa'],
                'reason'     => 'Missed in: ' . implode(', ', array_unique($words)),
            ];
        }

        // Phonemes hit/missed summary
        $hit_ids = array_unique(array_keys($lessons_hit));
        sort($hit_ids);

        return [
            'score'           => $score,
            'total_words'     => $total,
            'matched_count'   => $matched,
            'mismatched_count'=> count($incorrect_words),
            'skipped_count'   => count($skipped_words),
            'correct_words'   => $correct_words,
            'incorrect_words' => $incorrect_words,
            'skipped_words'   => $skipped_words,
            'word_details'    => $word_details,
            'phonemes_hit'    => $hit_ids,
            'phonemes_missed' => $missed_lesson_ids,
            'missed_lessons'  => $missed_lessons,
            'free_lesson'     => !empty($missed_lessons) ? $missed_lessons[0] : null,
        ];
    }

    /**
     * Tokenize text into words (lowercase, strip punctuation, keep order)
     */
    private function tokenize($text) {
        $text = mb_strtolower(trim($text));
        // Remove punctuation except apostrophes within words
        $text = preg_replace("/[^\w\s']/u", '', $text);
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return $words;
    }

    /**
     * Align expected words with transcript words using longest common subsequence.
     * Returns array of {expected, transcript} pairs. transcript=null means omitted.
     */
    private function align_words($expected, $transcript) {
        $m = count($expected);
        $n = count($transcript);

        // Build LCS table
        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if (mb_strtolower($expected[$i - 1]) === mb_strtolower($transcript[$j - 1])) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to find alignment
        $alignment = [];
        $i = $m;
        $j = $n;
        $result = [];

        while ($i > 0 && $j > 0) {
            if (mb_strtolower($expected[$i - 1]) === mb_strtolower($transcript[$j - 1])) {
                $result[] = ['expected' => $expected[$i - 1], 'transcript' => $transcript[$j - 1]];
                $i--;
                $j--;
            } elseif ($lcs[$i - 1][$j] >= $lcs[$i][$j - 1]) {
                // Expected word not in transcript — find closest match in remaining transcript
                $closest = $this->find_closest($expected[$i - 1], $transcript, $j);
                $result[] = ['expected' => $expected[$i - 1], 'transcript' => $closest];
                $i--;
            } else {
                // Extra word in transcript — skip
                $j--;
            }
        }

        // Remaining expected words not matched
        while ($i > 0) {
            $result[] = ['expected' => $expected[$i - 1], 'transcript' => null];
            $i--;
        }

        return array_reverse($result);
    }

    /**
     * Find the closest match for an expected word in nearby transcript words
     * Uses Levenshtein distance. Returns the word or null if nothing close.
     */
    private function find_closest($expected, $transcript, $max_j) {
        $exp_lower = mb_strtolower($expected);
        $best = null;
        $best_dist = PHP_INT_MAX;
        $window = min($max_j, 5); // Look at last 5 words

        for ($k = max(0, $max_j - $window); $k < $max_j; $k++) {
            $t = mb_strtolower($transcript[$k]);
            $dist = levenshtein($exp_lower, $t);
            if ($dist < $best_dist && $dist <= max(2, mb_strlen($exp_lower) / 2)) {
                $best_dist = $dist;
                $best = $transcript[$k];
            }
        }

        return $best;
    }

    /**
     * Record that a word was pronounced correctly → its lessons are "hit"
     */
    private function flag_hit($word, &$lessons_hit) {
        $lesson_ids = $this->word_lesson_map[$word] ?? [];
        foreach ($lesson_ids as $lid) {
            if (!isset($lessons_hit[$lid])) {
                $lessons_hit[$lid] = [];
            }
            $lessons_hit[$lid][] = $word;
        }
    }

    /**
     * Record that a word was mispronounced → its lessons are "missed"
     */
    private function flag_missed($word, &$lessons_missed) {
        $lesson_ids = $this->word_lesson_map[$word] ?? [];
        foreach ($lesson_ids as $lid) {
            if (!isset($lessons_missed[$lid])) {
                $lessons_missed[$lid] = [];
            }
            $lessons_missed[$lid][] = $word;
        }
    }

    /**
     * Comparison lessons (L27-L29, L44, L46, L49) only trigger when
     * both members of the compared pair are problematic.
     * E.g., L27 (p/b comparison) only if BOTH p-words AND b-words were missed.
     */
    private function check_comparison_lessons(&$lessons_hit, &$lessons_missed) {
        $comparisons = [
            27 => [21, 22],  // p/b → if both L21 and L22 missed
            28 => [23, 24],  // t/d → if both L23 and L24 missed
            29 => [25, 26],  // k/ɡ → if both L25 and L26 missed
            44 => [42, 43],  // ʧ/dʒ → if both L42 and L43 missed
            46 => [45, 45],  // w/wʰ → if L45 missed (same base)
            49 => [45, 47, 48], // w-ɹ̠-j → if multiple approximants missed
        ];

        foreach ($comparisons as $comp_id => $required) {
            $all_missed = true;
            foreach ($required as $req_id) {
                if (!isset($lessons_missed[$req_id])) {
                    $all_missed = false;
                    break;
                }
            }
            if ($all_missed && !empty($required)) {
                $lessons_missed[$comp_id] = ['(comparison)'];
            }
        }
    }
}
