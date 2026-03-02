<?php
/**
 * FLOSC Sample Text-Based Quiz
 *
 * User enters comma-separated numbers; scored by set intersection.
 * Example: Correct answers = "1,2,3,4,5,6,7,8,9,10"
 *          User enters "3,7,9" = 30%
 *          User enters "1,3,7,10" = 40%
 *
 * Each answer can optionally carry CorrectContent/RelatedContent refs
 * (one answer per line, pipe-separated). Old flat format still works.
 *
 * @package FLOSC
 * @version 3.0.7
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Sample_Text_Based_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'flosc_sample_text_based_quiz';
    }

    public function get_name() {
        return 'FLOSC Sample 1-10 Numbers Quiz';
    }

    public function get_description() {
        return 'Input the following numbers: 1, 2, 3, 4, 5, 6, 7, 8, 9, 10';
    }

    public function get_icon() {
        return '✍️';
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
        return "One answer per line. Format: answer|CorrectContent: ref|RelatedContent: ref, ref\n\nOr legacy flat format (no content refs): 1,2,3,4,5,6,7,8,9,10\n\nScoring: set-based — order doesn't matter. Score = how many correct answers the user included / total.\n\nPrefixes — always required, no quotes:\n  post:slug              — post by URL slug; use post:parent/child if the same slug exists under multiple parents\n  id:1042           — one post by numeric ID\n  category:slug     — posts in a category; category:parent/child for sub-categories\n  tag:slug          — posts with a tag (use the tag slug, not the display name)\n  search:any words  — keyword search (avoid: unreliable, may match wrong posts)\n\nMultiple |CorrectContent: and |RelatedContent: segments accumulate.";
    }

    public function get_default_content() {
        return "1|CorrectContent: post:lesson-one|RelatedContent: post:lesson-two, post:lesson-three\n2|CorrectContent: post:lesson-two|RelatedContent: post:lesson-one, post:lesson-three\n3|CorrectContent: post:lesson-three|RelatedContent: post:lesson-two, post:lesson-four\n4|CorrectContent: post:lesson-four|RelatedContent: post:lesson-three, post:lesson-five\n5|CorrectContent: post:lesson-five|RelatedContent: post:lesson-four, post:lesson-six\n6|CorrectContent: post:lesson-six|RelatedContent: post:lesson-five, post:lesson-seven\n7|CorrectContent: post:lesson-seven|RelatedContent: post:lesson-six, post:lesson-eight\n8|CorrectContent: post:lesson-eight|RelatedContent: post:lesson-seven, post:lesson-nine\n9|CorrectContent: post:lesson-nine|RelatedContent: post:lesson-eight, post:lesson-ten\n10|CorrectContent: post:lesson-ten|RelatedContent: post:lesson-nine, post:lesson-one";
    }

    public function validate_input($input) {
        if (empty($input) || !is_string($input)) {
            return new WP_Error('invalid_input', 'Please enter your answers.');
        }

        $separator = $this->get_setting('separator', ',');
        $items = $this->parse_input($input, $separator);

        if (empty($items)) {
            return new WP_Error('empty_input', 'No valid answers found.');
        }

        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        $separator      = $this->get_setting('separator', ',');
        $case_sensitive = $this->get_setting('case_sensitive', false);

        // Parse correct answers and per-answer content refs
        $parsed          = $this->parse_content($expected_content);
        $correct_answers = $parsed['answers'];
        $content_map     = $parsed['content_map'];

        // Parse user answers
        $user_answers = $this->parse_input($input, $separator);

        // Normalize if case-insensitive
        if (!$case_sensitive) {
            $correct_answers = array_map('strtolower', $correct_answers);
            $user_answers    = array_map('strtolower', $user_answers);
            $normalized_map  = [];
            foreach ($content_map as $k => $v) {
                $normalized_map[strtolower(trim($k))] = $v;
            }
            $content_map = $normalized_map;
        }

        $correct_answers = array_map('trim', $correct_answers);
        $user_answers    = array_map('trim', $user_answers);

        // Correct = user typed something that IS in the correct set
        $correct       = [];
        $wrong_typed   = [];
        $total_correct = 0;

        foreach ($user_answers as $answer) {
            if (in_array($answer, $correct_answers)) {
                $correct[] = $answer;
                $total_correct++;
            } else {
                $wrong_typed[] = $answer;
            }
        }

        // Missed = in correct_answers but NOT typed by user
        // These are the items we recommend lessons for
        $missed    = array_values(array_diff($correct_answers, $user_answers));
        $incorrect = [];
        foreach ($missed as $answer) {
            $refs        = $content_map[$answer] ?? [];
            $incorrect[] = [
                'answer'          => $answer,
                'correct_content' => $refs['correct_content'] ?? [],
                'related_content' => $refs['related_content'] ?? [],
                'topics'          => [],
            ];
        }

        $total_possible = count($correct_answers);
        $score          = $this->calculate_percentage($total_correct, $total_possible);
        $response_key   = $this->get_response_key_from_score($score);

        return [
            'score'        => $score,
            'correct'      => $correct,
            'incorrect'    => $incorrect,
            'response_key' => $response_key,
            'details'      => [
                'total_correct'  => $total_correct,
                'total_possible' => $total_possible,
                'total_answered' => count($user_answers),
                'missed'         => $missed,
                'wrong_typed'    => $wrong_typed,
            ],
        ];
    }

    public function get_settings_fields() {
        return [
            'separator' => [
                'type'        => 'text',
                'label'       => 'Answer Separator',
                'default'     => ',',
                'description' => 'Character(s) separating answers (e.g., comma, semicolon, pipe)',
            ],
            'case_sensitive' => [
                'type'        => 'checkbox',
                'label'       => 'Case Sensitive',
                'default'     => false,
                'description' => 'Require exact letter case matches',
            ],
            'partial_credit' => [
                'type'        => 'checkbox',
                'label'       => 'Partial Credit',
                'default'     => false,
                'description' => 'Give credit for partially correct answers (future feature)',
            ],
        ];
    }

    public function get_default_response_templates() {
        return [
            '0-30'   => "**Score: {score}%**\n\nYou got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '31-60'  => "**Score: {score}%**\n\nGood effort! You got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '61-85'  => "**Score: {score}%**\n\nNice work! You got {total_correct} out of {total_possible} correct.\n\n{lesson_recommendations}",
            '86-100' => "**Score: {score}%**\n\nExcellent! You got {total_correct} out of {total_possible} correct!\n\n{lesson_recommendations}",
        ];
    }

    public function format_results($analysis, $lessons, $response_templates) {
        $score        = $analysis['score'];
        $response_key = $analysis['response_key'];
        $details      = $analysis['details'];

        $template = $response_templates[$response_key] ?? $response_templates['31-60'] ?? "Score: {score}%";

        $lesson_text = '';
        if (!empty($lessons)) {
            $free_lesson  = $lessons[0];
            $paid_lessons = array_slice($lessons, 1);

            $lesson_text  = "\n**📚 Recommended Lessons:**\n\n";
            $lesson_text .= "🎁 **{$free_lesson['title']}** (FREE)\n";
            $lesson_text .= "   {$free_lesson['reason']}\n";

            if (!empty($paid_lessons)) {
                $lesson_text .= "\n🔒 **Unlock " . count($paid_lessons) . " more lessons:**\n";
                foreach ($paid_lessons as $lesson) {
                    $lesson_text .= "   • {$lesson['title']}\n";
                }
            }
        }

        if (!empty($analysis['correct'])) {
            $lesson_text .= "\n\n✅ **Correct:** " . implode(', ', $analysis['correct']);
        }

        if (!empty($details['missed'])) {
            $lesson_text .= "\n❌ **Missed:** " . implode(', ', $details['missed']);
        }

        $message = str_replace(
            ['{score}', '{total_correct}', '{total_possible}', '{lesson_recommendations}'],
            [$score, $details['total_correct'], $details['total_possible'], $lesson_text],
            $template
        );

        return $message;
    }

    /**
     * Parse expected_content into answers array + per-answer content map.
     *
     * New format (one per line):
     *   1|CorrectContent: post:lesson-one|RelatedContent: post:lesson-two, post:lesson-three
     *
     * Legacy format (flat, backward compat):
     *   1,2,3,4,5,6,7,8,9,10
     */
    private function parse_content($expected_content) {
        $answers     = [];
        $content_map = [];

        $lines = array_filter(array_map('trim', explode("\n", $expected_content)));

        foreach ($lines as $line) {
            if (strpos($line, '|') !== false) {
                // New per-line format with content refs
                $parts  = explode('|', $line);
                $answer = trim($parts[0]);
                if ($answer === '') continue;
                $answers[] = $answer;

                $correct_content = [];
                $related_content = [];
                for ($i = 1; $i < count($parts); $i++) {
                    $seg = trim($parts[$i]);
                    if (stripos($seg, 'correctcontent:') === 0) {
                        foreach (array_map('trim', explode(',', trim(substr($seg, strlen('correctcontent:'))))) as $r) {
                            if ($r !== '') $correct_content[] = $r;
                        }
                    } elseif (stripos($seg, 'relatedcontent:') === 0) {
                        foreach (array_map('trim', explode(',', trim(substr($seg, strlen('relatedcontent:'))))) as $r) {
                            if ($r !== '') $related_content[] = $r;
                        }
                    }
                }
                $content_map[$answer] = [
                    'correct_content' => $correct_content,
                    'related_content' => $related_content,
                ];
            } elseif (strpos($line, ',') !== false) {
                // Legacy flat format: 1,2,3,4,5,6,7,8,9,10
                foreach (array_map('trim', explode(',', $line)) as $a) {
                    if ($a !== '') $answers[] = $a;
                }
            } else {
                // Single answer per line, no content refs
                if ($line !== '') $answers[] = $line;
            }
        }

        return ['answers' => $answers, 'content_map' => $content_map];
    }

    /**
     * Parse a flat input string into an array of answer strings.
     * Used for user input only (never for expected_content).
     */
    private function parse_input($input, $separator) {
        if (empty($input)) return [];

        // Also accept space-separated
        if ($separator === ',' && strpos($input, ',') === false && strpos($input, ' ') !== false) {
            $items = explode(' ', $input);
        } else {
            $items = explode($separator, $input);
        }

        return array_values(array_filter(array_map('trim', $items)));
    }
}
