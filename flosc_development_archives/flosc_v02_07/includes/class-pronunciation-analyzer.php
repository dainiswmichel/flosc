<?php
/**
 * Pronunciation Analyzer
 *
 * Analyzes pronunciation by comparing transcript to expected text
 * Maps errors to lesson recommendations
 *
 * @package LeSAEp_FLOSC
 * @version 2.0.7
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeSAEp_Pronunciation_Analyzer {

    /**
     * Analyze pronunciation from transcript
     *
     * @param string $transcript What user said
     * @param string $expected What they should have said
     * @param array $stt_data Raw STT data (words, confidence, etc.)
     * @return array Analysis results with lesson recommendations
     */
    public static function analyze($transcript, $expected, $stt_data = []) {
        // Normalize both strings
        $transcript_words = self::normalize_text($transcript);
        $expected_words = self::normalize_text($expected);

        // Find differences
        $errors = self::find_errors($transcript_words, $expected_words);

        // Map errors to lessons
        $lesson_recommendations = self::map_to_lessons($errors);

        // Calculate score
        $score = self::calculate_score($transcript_words, $expected_words, $errors);

        return [
            'score' => $score,
            'transcript' => $transcript,
            'expected' => $expected,
            'errors' => $errors,
            'lesson_recommendations' => $lesson_recommendations,
            'total_words' => count($expected_words),
            'correct_words' => count($expected_words) - count($errors),
            'error_count' => count($errors),
            'confidence' => $stt_data['confidence'] ?? 0.9,
        ];
    }

    /**
     * Normalize text for comparison
     */
    private static function normalize_text($text) {
        // Lowercase
        $text = strtolower($text);

        // Remove punctuation
        $text = preg_replace('/[^\w\s]/', '', $text);

        // Split into words
        $words = preg_split('/\s+/', trim($text));

        return array_filter($words);
    }

    /**
     * Find pronunciation errors
     */
    private static function find_errors($transcript_words, $expected_words) {
        $errors = [];

        foreach ($expected_words as $index => $expected_word) {
            $transcript_word = $transcript_words[$index] ?? '';

            // Word is missing
            if (empty($transcript_word)) {
                $errors[] = [
                    'type' => 'missing',
                    'expected' => $expected_word,
                    'actual' => '',
                    'position' => $index,
                ];
                continue;
            }

            // Word is different
            if ($transcript_word !== $expected_word) {
                $errors[] = [
                    'type' => 'mispronounced',
                    'expected' => $expected_word,
                    'actual' => $transcript_word,
                    'position' => $index,
                    'phoneme_issue' => self::identify_phoneme_issue($expected_word, $transcript_word),
                ];
            }
        }

        // Extra words at the end
        if (count($transcript_words) > count($expected_words)) {
            for ($i = count($expected_words); $i < count($transcript_words); $i++) {
                $errors[] = [
                    'type' => 'extra',
                    'expected' => '',
                    'actual' => $transcript_words[$i],
                    'position' => $i,
                ];
            }
        }

        return $errors;
    }

    /**
     * Identify phoneme issue (simplified)
     */
    private static function identify_phoneme_issue($expected, $actual) {
        // Common pronunciation issues in SAE
        $phoneme_patterns = [
            'th' => ['t', 'd', 'f', 'v', 's', 'z'], // "three" → "tree", "free"
            'v' => ['b', 'w', 'f'], // "seven" → "seben"
            'r' => ['l', 'w'], // "four" → "foul"
            'l' => ['r', 'w'], // English L vs other languages
            'w' => ['v', 'b'], // "one" → "von"
        ];

        foreach ($phoneme_patterns as $phoneme => $substitutions) {
            if (strpos($expected, $phoneme) !== false) {
                foreach ($substitutions as $sub) {
                    if (strpos($actual, $sub) !== false) {
                        return $phoneme;
                    }
                }
            }
        }

        return 'general';
    }

    /**
     * Map errors to lesson numbers (1-10 counting quiz)
     */
    private static function map_to_lessons($errors) {
        // For counting 1-10 quiz, lessons map to numbers 5-10
        // Numbers 1-4 are diagnostic only
        $lesson_map = [
            'five' => [
                'lesson_number' => 5,
                'title' => 'The F Sound',
                'phoneme' => 'f',
                'description' => 'Master the /f/ sound in Standard American English',
            ],
            'six' => [
                'lesson_number' => 6,
                'title' => 'The S and X Sounds',
                'phoneme' => 's',
                'description' => 'Perfect your /s/ and /ks/ sounds',
            ],
            'seven' => [
                'lesson_number' => 7,
                'title' => 'The V Sound',
                'phoneme' => 'v',
                'description' => 'Learn to pronounce /v/ correctly',
            ],
            'eight' => [
                'lesson_number' => 8,
                'title' => 'The Long A Sound',
                'phoneme' => 'eɪ',
                'description' => 'Master the diphthong in "eight"',
            ],
            'nine' => [
                'lesson_number' => 9,
                'title' => 'The N Sound',
                'phoneme' => 'n',
                'description' => 'Perfect your nasal /n/ sound',
            ],
            'ten' => [
                'lesson_number' => 10,
                'title' => 'The Short E Sound',
                'phoneme' => 'ɛ',
                'description' => 'Master the /ɛ/ vowel in "ten"',
            ],
            'three' => [
                'lesson_number' => 11, // Could be "th" lesson
                'title' => 'The TH Sound',
                'phoneme' => 'θ',
                'description' => 'Master the voiced and voiceless /th/',
            ],
            'four' => [
                'lesson_number' => 12,
                'title' => 'The R Sound',
                'phoneme' => 'r',
                'description' => 'Perfect your American /r/',
            ],
        ];

        $recommendations = [];
        $seen_lessons = [];

        foreach ($errors as $error) {
            if ($error['type'] !== 'mispronounced') {
                continue;
            }

            $expected_word = $error['expected'];

            if (isset($lesson_map[$expected_word]) && !in_array($expected_word, $seen_lessons)) {
                $recommendations[] = $lesson_map[$expected_word];
                $seen_lessons[] = $expected_word;
            }
        }

        // Sort by lesson number
        usort($recommendations, function($a, $b) {
            return $a['lesson_number'] - $b['lesson_number'];
        });

        return $recommendations;
    }

    /**
     * Calculate pronunciation score (0-100)
     */
    private static function calculate_score($transcript_words, $expected_words, $errors) {
        if (empty($expected_words)) {
            return 0;
        }

        $total_words = count($expected_words);
        $error_count = count($errors);

        // Penalize missing words more than mispronunciations
        $penalty = 0;
        foreach ($errors as $error) {
            if ($error['type'] === 'missing') {
                $penalty += 2; // Missing = double penalty
            } else {
                $penalty += 1;
            }
        }

        $score = max(0, 100 - ($penalty / $total_words * 100));

        return round($score);
    }

    /**
     * Get the "free lesson" recommendation
     * For funnel: Give lesson with lowest number (first error) for free
     */
    public static function get_free_lesson($lesson_recommendations) {
        if (empty($lesson_recommendations)) {
            return null;
        }

        // Return the first recommended lesson (lowest number)
        return $lesson_recommendations[0];
    }

    /**
     * Get paid lessons for upsell
     */
    public static function get_paid_lessons($lesson_recommendations) {
        if (count($lesson_recommendations) <= 1) {
            return [];
        }

        // All lessons except the first (free) one
        return array_slice($lesson_recommendations, 1);
    }

    /**
     * Format analysis for chat display
     */
    public static function format_for_chat($analysis) {
        $message = "**Your Pronunciation Analysis**\n\n";
        $message .= "📊 **Score:** {$analysis['score']}/100\n";
        $message .= "✅ **Correct:** {$analysis['correct_words']}/{$analysis['total_words']} words\n\n";

        if (!empty($analysis['errors'])) {
            $message .= "**Areas for Improvement:**\n";
            foreach ($analysis['errors'] as $error) {
                if ($error['type'] === 'mispronounced') {
                    $message .= "• You said \"{$error['actual']}\" instead of \"{$error['expected']}\"\n";
                } elseif ($error['type'] === 'missing') {
                    $message .= "• You missed the word \"{$error['expected']}\"\n";
                }
            }
            $message .= "\n";
        }

        if (!empty($analysis['lesson_recommendations'])) {
            $free_lesson = self::get_free_lesson($analysis['lesson_recommendations']);
            $paid_lessons = self::get_paid_lessons($analysis['lesson_recommendations']);

            $message .= "**📚 Recommended Lessons:**\n\n";

            if ($free_lesson) {
                $message .= "🎁 **Lesson {$free_lesson['lesson_number']} (FREE):** {$free_lesson['title']}\n";
                $message .= "   {$free_lesson['description']}\n\n";
            }

            if (!empty($paid_lessons)) {
                $message .= "🔒 **Unlock {count($paid_lessons)} more lessons to master:**\n";
                foreach ($paid_lessons as $lesson) {
                    $message .= "   • Lesson {$lesson['lesson_number']}: {$lesson['title']}\n";
                }
            }
        }

        return $message;
    }
}
