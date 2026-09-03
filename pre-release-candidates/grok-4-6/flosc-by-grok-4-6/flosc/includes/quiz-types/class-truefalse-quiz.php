<?php
/**
 * True/False Quiz Type
 *
 * User answers True or False questions.
 * Simple, effective, works out of the box.
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_TrueFalse_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'truefalse';
    }

    public function get_name() {
        return 'True/False';
    }

    public function get_description() {
        return 'User answers True or False to statements. Perfect for knowledge checks.';
    }

    public function get_icon() {
        return '✓✗';
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
        return "One statement per line. Format: Statement.|True or Statement.|False\n\nOptional pipe segments (add as many as you like — they all accumulate):\n  |CorrectContent: post:my-post-slug\n  |CorrectContent: tag:my-tag, id:1042\n  |RelatedContent: post:slug-one, category:parent/child\n  |RelatedContent: tag:another-tag, id:1043\n  |RelatedContent: search:distinctive words from title\n\nPrefixes — always required, no quotes:\n  post:slug              — post by URL slug; use post:parent/child if the same slug exists under multiple parents\n  id:1042           — one post by numeric ID\n  category:slug     — posts in a category; category:parent/child for sub-categories\n  tag:slug          — posts with a tag (use the tag slug, not the display name)\n  search:any words  — keyword search (avoid: unreliable, may match wrong posts)\n\nMultiple |CorrectContent: and |RelatedContent: segments accumulate. CorrectContent items are tier 1 — shown first when a learner asks to review what they got wrong.";
    }

    public function get_default_content() {
        // Subject-neutral sample — replace with your own statements in FLOSC → Quiz.
        return "Sample statement for Topic 1 — Getting started: this product ships ready for any subject.|True|CorrectContent: post:sample-topic-1-getting-started|RelatedContent: post:sample-topic-1-getting-started-extra|Topic: topic-1-getting-started\nSample statement for Topic 2 — Core ideas: admins configure freeline, guest gifts, and member gates in the flow.|True|CorrectContent: post:sample-topic-2-core-ideas|RelatedContent: category:sample_lessons|Topic: topic-2-core-ideas\nSample statement for Topic 3 — Practice basics: wrong answers must always unlock the full member library.|False|CorrectContent: post:sample-topic-3-practice-basics|RelatedContent: post:sample-topic-3-practice-basics-extra|Topic: topic-3-practice-basics";
    }

    public function validate_input($input) {
        if (empty($input) || !is_string($input)) {
            return new WP_Error('invalid_input', __('Please enter your answers.', 'flosc'));
        }

        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        // Parse questions
        $questions = $this->parse_questions($expected_content);

        // Parse answers (can be "T,F,T" or "True,False,True")
        $user_answers = $this->parse_user_answers($input);

        $correct = [];
        $incorrect = [];
        $total_correct = 0;

        foreach ($questions as $index => $question) {
            $correct_answer = strtolower($question['answer']);
            $user_answer = isset($user_answers[$index]) ? strtolower($user_answers[$index]) : '';

            // Normalize
            $correct_answer = $this->normalize_answer($correct_answer);
            $user_answer = $this->normalize_answer($user_answer);

            if ($user_answer === $correct_answer) {
                $correct[] = [
                    'question' => $question['text'],
                    'answer'   => $user_answer,
                    'topics'   => $question['topics'] ?? [],
                ];
                $total_correct++;
            } else {
                $incorrect[] = [
                    'question'        => $question['text'],
                    'user_answer'     => $user_answer,
                    'correct_answer'  => $correct_answer,
                    'topics'          => $question['topics'] ?? [],
                    'correct_content' => $question['correct_content'] ?? '',
                    'related_content' => $question['related_content'] ?? [],
                ];
            }
        }

        $total_possible = count($questions);
        $score = $this->calculate_percentage($total_correct, $total_possible);
        $response_key = $this->get_response_key_from_score($score);

        return [
            'score' => $score,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'response_key' => $response_key,
            'details' => [
                'total_correct' => $total_correct,
                'total_possible' => $total_possible,
            ],
        ];
    }

    public function get_settings_fields() {
        return [
            'answer_format' => [
                'type' => 'select',
                'label' => 'Answer Format',
                'options' => [
                    'tf' => 'T/F',
                    'truefalse' => 'True/False',
                    'yesno' => 'Yes/No',
                ],
                'default' => 'truefalse',
                'description' => 'How users should format their answers',
            ],
        ];
    }

    /**
     * Parse questions from content.
     * Format: "Statement.|True|Topic: slug1, slug2"
     * Topic segment is optional.
     */
    private function parse_questions($content) {
        $lines = explode("\n", $content);
        $questions = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) < 2) continue;

            $topics          = [];
            $correct_content = [];
            $related_content = [];
            for ($i = 2; $i < count($parts); $i++) {
                $seg = trim($parts[$i]);
                if (stripos($seg, 'correctcontent:') === 0) {
                    // Appends — multiple |CorrectContent: segments are all tier-1
                    foreach (array_map('trim', explode(',', trim(substr($seg, strlen('correctcontent:'))))) as $r) {
                        if ($r !== '') $correct_content[] = $r;
                    }
                } elseif (stripos($seg, 'relatedcontent:') === 0) {
                    // Appends — multiple |RelatedContent: pipe segments are cumulative
                    foreach (array_map('trim', explode(',', trim(substr($seg, strlen('relatedcontent:'))))) as $r) {
                        if ($r !== '') $related_content[] = $r;
                    }
                } elseif (stripos($seg, 'topic:') === 0) {
                    $topics = array_map('trim', explode(',', trim(str_ireplace('topic:', '', $seg))));
                }
            }

            $questions[] = [
                'text'            => trim($parts[0]),
                'answer'          => trim($parts[1]),
                'topics'          => $topics,
                'correct_content' => $correct_content,
                'related_content' => $related_content,
            ];
        }

        return $questions;
    }

    /**
     * Parse user answers
     * Accepts: "T,F,T" or "True,False,True" or "true\nfalse\ntrue"
     */
    private function parse_user_answers($input) {
        // Try comma-separated first
        if (strpos($input, ',') !== false) {
            $answers = explode(',', $input);
        } else {
            // Try newline-separated
            $answers = explode("\n", $input);
        }

        return array_map('trim', $answers);
    }

    /**
     * Normalize answer (T/True/Yes → true, F/False/No → false)
     */
    private function normalize_answer($answer) {
        $answer = strtolower(trim($answer));

        if (in_array($answer, ['t', 'true', 'yes', '1'])) {
            return 'true';
        }

        if (in_array($answer, ['f', 'false', 'no', '0'])) {
            return 'false';
        }

        return $answer;
    }
}
