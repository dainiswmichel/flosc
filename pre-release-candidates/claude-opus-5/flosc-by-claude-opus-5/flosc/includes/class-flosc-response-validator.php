<?php
/**
 * FLOSC Response Validator
 * Last-resort validation of AI outputs before showing to users
 *
 * @package FLOSC
 * @since 1.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Response_Validator {

    private $flosc_user_session;

    public function __construct($flosc_user_session) {
        $this->flosc_user_session = $flosc_user_session;
    }

    /**
     * Validate AI response
     *
     * @param string $flosc_response AI response text
     * @param array $flosc_tool_calls_made Tools that were called
     * @return array Validation result
     */
    public function flosc_validate($flosc_response, $flosc_tool_calls_made = []) {
        $flosc_violations = [];

        // Check 1: Lesson content without tool use
        if ($this->flosc_contains_lesson_content($flosc_response) &&
            !$this->flosc_used_lesson_tool($flosc_tool_calls_made)) {
            $flosc_violations[] = 'lesson_content_without_tool';
        }

        // Check 2: Pricing to visitors
        if ($this->flosc_contains_pricing($flosc_response) &&
            $this->flosc_user_session->flosc_get('flosc_user_type') === 'flosc_visitor') {
            $flosc_violations[] = 'premature_pricing';
        }

        if (empty($flosc_violations)) {
            return ['flosc_valid' => true, 'flosc_response' => $flosc_response];
        }

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log('FLOSC Response Integrity Violation: ' . implode(', ', $flosc_violations));
        }

        return [
            'flosc_valid' => false,
            'flosc_violations' => $flosc_violations,
            'flosc_response' => $this->flosc_get_override_response($flosc_violations),
        ];
    }

    private function flosc_contains_lesson_content($flosc_response) {
        return strlen($flosc_response) > 3000 || preg_match('/^Lesson \d+:/m', $flosc_response);
    }

    private function flosc_used_lesson_tool($flosc_tool_calls) {
        foreach ($flosc_tool_calls as $flosc_call) {
            if (in_array($flosc_call['name'], ['flosc_get_lesson_content', 'flosc_deliver_free_lesson'])) {
                return true;
            }
        }
        return false;
    }

    private function flosc_contains_pricing($flosc_response) {
        $flosc_keywords = ['$', 'price', 'cost', 'pay', 'purchase', 'buy'];
        foreach ($flosc_keywords as $flosc_keyword) {
            if (stripos($flosc_response, $flosc_keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function flosc_get_override_response($flosc_violations) {
        $flosc_user_type = $this->flosc_user_session->flosc_get('flosc_user_type');

        $flosc_overrides = [
            'flosc_visitor' => "I'd love to help! First, take our quick 2-minute quiz to get your personalized free lesson. 🎯",
            'flosc_guest' => "To access all lessons, unlock full access and continue your learning journey. ✨",
        ];

        return $flosc_overrides[$flosc_user_type] ?? "Let me rephrase that.";
    }
}
