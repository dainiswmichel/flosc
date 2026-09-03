<?php
/**
 * Multiple Choice Quiz Type
 *
 * Classic quiz format: question with 2-4 options, user picks one.
 * Format: "Question?|A) Option 1|B) Option 2|C) Option 3|Correct: A"
 *
 * @package FLOSC
 * @version 3.0.1
 */

if (!defined('ABSPATH')) exit;

class FLOSC_MultipleChoice_Quiz extends FLOSC_Abstract_Quiz_Type {

    public function get_id() {
        return 'multiplechoice';
    }

    public function get_name() {
        return 'Multiple Choice';
    }

    public function get_description() {
        return 'Classic quiz format with 2-4 options per question.';
    }

    public function get_icon() {
        return '☑️';
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
        return "One question per line. Format: Question?|A) Option|B) Option|C) Option|Correct: A\n\nOptional pipe segments (add as many as you like — they all accumulate):\n  |CorrectContent: post:my-post-slug\n  |CorrectContent: tag:my-tag, id:1042\n  |RelatedContent: post:slug-one, category:parent/child\n  |RelatedContent: tag:another-tag, id:1043\n  |RelatedContent: search:distinctive words from title\n\nPrefixes — always required, no quotes:\n  post:slug              — post by URL slug; use post:parent/child if the same slug exists under multiple parents\n  id:1042           — one post by numeric ID\n  category:slug     — posts in a category; category:parent/child for sub-categories\n  tag:slug          — posts with a tag (use the tag slug, not the display name)\n  search:any words  — keyword search (avoid: unreliable, may match wrong posts)\n\nMultiple |CorrectContent: and |RelatedContent: segments accumulate. CorrectContent items are tier 1 — shown first when a learner asks to review what they got wrong.";
    }

    public function get_default_content() {
        // Subject-neutral sample — replace with your own questions in FLOSC → Quiz.
        return "Sample question for Topic 1 — Getting started. Which statement is true?|A) Placeholder wrong answer|B) Sample correct answer for this topic|C) Another placeholder wrong answer|D) Another placeholder wrong answer|Correct: B|CorrectContent: post:sample-topic-1-getting-started|RelatedContent: post:sample-topic-1-getting-started-extra|Topic: topic-1-getting-started\nSample question for Topic 2 — Core ideas. Which statement is true?|A) Placeholder wrong answer|B) Sample correct answer for this topic|C) Another placeholder wrong answer|D) Another placeholder wrong answer|Correct: B|CorrectContent: post:sample-topic-2-core-ideas|RelatedContent: post:sample-topic-2-core-ideas-extra|Topic: topic-2-core-ideas\nSample question for Topic 3 — Practice basics. Which statement is true?|A) Placeholder wrong answer|B) Sample correct answer for this topic|C) Another placeholder wrong answer|D) Another placeholder wrong answer|Correct: C|CorrectContent: post:sample-topic-3-practice-basics|RelatedContent: category:sample_lessons|Topic: topic-3-practice-basics";
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

        // Parse user answers (e.g., "A,B,C" or "a,b,c")
        $user_answers = $this->parse_user_answers($input);

        $correct = [];
        $incorrect = [];
        $total_correct = 0;

        foreach ($questions as $index => $question) {
            $correct_answer = strtoupper(trim($question['correct']));
            $user_answer = isset($user_answers[$index]) ? strtoupper(trim($user_answers[$index])) : '';

            if ($user_answer === $correct_answer) {
                $correct[] = [
                    'question' => $question['text'],
                    'answer'   => $correct_answer,
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
            'show_options' => [
                'type' => 'checkbox',
                'label' => 'Show Options in Results',
                'default' => true,
                'description' => 'Display what each letter (A, B, C, D) represented',
            ],
        ];
    }

    /**
     * Parse questions from content.
     * Format: "Question?|A) Option 1|B) Option 2|Correct: A|Topic: slug1, slug2"
     * Separated by newlines (or double newlines).
     */
    private function parse_questions($content) {
        $questions = [];
        $blocks = explode("\n\n", $content);

        if (count($blocks) === 1) {
            $blocks = explode("\n", $content);
        }

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) continue;

            $parts = explode('|', $block);
            if (count($parts) < 3) continue;

            $question_text   = trim($parts[0]);
            $options         = [];
            $correct         = '';
            $topics          = [];
            $correct_content = [];
            $related_content = [];

            for ($i = 1; $i < count($parts); $i++) {
                $part = trim($parts[$i]);

                if (stripos($part, 'correctcontent:') === 0) {
                    // Appends — multiple |CorrectContent: segments are all tier-1
                    foreach (array_map('trim', explode(',', trim(substr($part, strlen('correctcontent:'))))) as $r) {
                        if ($r !== '') $correct_content[] = $r;
                    }
                } elseif (stripos($part, 'relatedcontent:') === 0) {
                    // Appends — multiple |RelatedContent: pipe segments are cumulative
                    foreach (array_map('trim', explode(',', trim(substr($part, strlen('relatedcontent:'))))) as $r) {
                        if ($r !== '') $related_content[] = $r;
                    }
                } elseif (stripos($part, 'correct:') === 0) {
                    $correct = trim(str_ireplace('correct:', '', $part));
                } elseif (stripos($part, 'topic:') === 0) {
                    $topics = array_map('trim', explode(',', trim(str_ireplace('topic:', '', $part))));
                } else {
                    $options[] = $part;
                }
            }

            if (!empty($question_text) && !empty($correct)) {
                $questions[] = [
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

    /**
     * Parse user answers
     * Accepts: "A,B,C" or "a,b,c" or "A\nB\nC"
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
}
