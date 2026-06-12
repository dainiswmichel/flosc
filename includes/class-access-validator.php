<?php
/**
 * FLOSC Access Level Validator
 * Enforces strict content restrictions
 * 
 * CRITICAL: Prevents AI from leaking member content to visitors/guests
 * 
 * @since 9.1.7
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Access_Validator {
    
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validate AI response before sending to user
     * CRITICAL: This catches any content leakage
     * 
     * @param string $ai_response The AI's proposed response
     * @param string $access_level User's access level
     * @return array ['valid' => bool, 'response' => string, 'violations' => array]
     */
    public function validate_response($ai_response, $access_level) {
        
        $violations = [];
        
        // Get forbidden keywords for this access level
        $forbidden = $this->get_forbidden_keywords($access_level);
        
        // Check for forbidden keywords
        foreach ($forbidden as $keyword => $reason) {
            if (stripos($ai_response, $keyword) !== false) {
                $violations[] = [
                    'keyword' => $keyword,
                    'reason' => $reason,
                    'access_level' => $access_level
                ];
            }
        }
        
        // Check for VISITOR-specific violations
        if ($access_level === 'visitor') {
            $violations = array_merge($violations, $this->check_visitor_violations($ai_response));
        }
        
        // Check for GUEST-specific violations
        if ($access_level === 'guest') {
            $violations = array_merge($violations, $this->check_guest_violations($ai_response));
        }
        
        // If violations found, replace with safe response
        if (!empty($violations)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC SECURITY: Content leakage prevented for {$access_level}");
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC SECURITY: Violations - " . json_encode($violations));
            }
            
            return [
                'valid' => false,
                'response' => $this->get_safe_fallback_response($access_level),
                'violations' => $violations,
                'original_response' => $ai_response // For debugging
            ];
        }
        
        return [
            'valid' => true,
            'response' => $ai_response,
            'violations' => []
        ];
    }
    
    /**
     * Get forbidden keywords for access level
     */
    private function get_forbidden_keywords($access_level) {
        
        $all_forbidden = [
            // IPA and pronunciation terms (MEMBER ONLY)
            // v1.4.9: Use regex-style boundaries to avoid false positives on URLs/paths
            '/ʌ/' => 'IPA transcription',
            'IPA:' => 'IPA format',
            'transcription:' => 'Pronunciation transcription',
            
            // Member-only phrases
            'member content' => 'Direct reference to member content',
            'full lesson' => 'Member lesson reference',
            'complete guide' => 'Member guide reference',
        ];
        
        if ($access_level === 'visitor' || $access_level === 'guest') {
            return $all_forbidden;
        }
        
        return []; // Members can see everything
    }
    
    /**
     * Check VISITOR-specific violations
     * VISITORS should ONLY see quiz prompts
     */
    private function check_visitor_violations($response) {
        
        $violations = [];
        
        // VISITORS should NOT see pricing
        // v1.4.9: Removed '$' — too many false positives (currency mentions, variable names, etc.)
        $pricing_keywords = ['price', 'cost', 'discount', 'offer', 'purchase', 'buy'];
        foreach ($pricing_keywords as $keyword) {
            if (stripos($response, $keyword) !== false) {
                $violations[] = [
                    'keyword' => $keyword,
                    'reason' => 'Pricing information shown to visitor',
                    'severity' => 'HIGH'
                ];
            }
        }
        
        // VISITORS should NOT see lesson details
        $lesson_keywords = ['lesson 1', 'lesson 2', 'lesson 3', 'pronunciation guide', 'video demonstration'];
        foreach ($lesson_keywords as $keyword) {
            if (stripos($response, $keyword) !== false) {
                $violations[] = [
                    'keyword' => $keyword,
                    'reason' => 'Lesson details shown to visitor',
                    'severity' => 'CRITICAL'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Check GUEST-specific violations
     * GUESTS should see offers but NOT member content
     */
    private function check_guest_violations($response) {
        
        $violations = [];
        
        // GUESTS should NOT see full lesson content
        // They can see lesson TITLES and DESCRIPTIONS but not content
        
        // Check for detailed content (paragraphs with technical details)
        if (preg_match('/\b(specifically|detailed|step-by-step|complete breakdown)\b/i', $response)) {
            $violations[] = [
                'keyword' => 'detailed_content',
                'reason' => 'Detailed content shown to guest',
                'severity' => 'HIGH'
            ];
        }
        
        return $violations;
    }
    
    /**
     * Get safe fallback response for access level
     * This is shown when AI tries to leak content
     */
    private function get_safe_fallback_response($access_level) {
        
        $responses = [
            'visitor' => "I'd love to help you! The best way to get started is to take our free 2-minute quiz. It will show you exactly where you stand and what you need to work on. Ready to take the quiz?",
            
            'guest' => "Great question! Based on your quiz results, I can see exactly which lessons would help you most. To access the full content and detailed guides, check out our special member offer - it's available for a limited time!",
            
            'member' => "Let me find that information for you from our lesson library."
        ];
        
        return $responses[$access_level] ?? $responses['visitor'];
    }
    
    /**
     * Validate system prompt for access level
     * Ensures AI is instructed correctly
     */
    public function validate_system_prompt($system_prompt, $access_level) {
        
        $required_phrases = $this->get_required_prompt_phrases($access_level);
        
        $missing = [];
        foreach ($required_phrases as $phrase => $reason) {
            if (stripos($system_prompt, $phrase) === false) {
                $missing[] = [
                    'phrase' => $phrase,
                    'reason' => $reason
                ];
            }
        }
        
        if (!empty($missing)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC WARNING: System prompt missing required phrases for {$access_level}");
if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC WARNING: Missing - " . json_encode($missing));
            }
        }
        
        return empty($missing);
    }
    
    /**
     * Get required phrases in system prompt
     */
    private function get_required_prompt_phrases($access_level) {
        
        $phrases = [
            'visitor' => [
                'take the quiz' => 'Must encourage quiz taking',
                'DO NOT share' => 'Must have restrictions',
                'VISITOR' => 'Must specify access level'
            ],
            'guest' => [
                'quiz results' => 'Must reference quiz results',
                'offer' => 'Must mention offers',
                'DO NOT share full content' => 'Must restrict full content'
            ],
            'member' => [
                'full access' => 'Must confirm full access',
                'MEMBER' => 'Must specify member status'
            ]
        ];
        
        return $phrases[$access_level] ?? [];
    }
    
    /**
     * Get access level enforcement rules
     * Returns what AI CAN and CANNOT do at each level
     */
    public function get_enforcement_rules($access_level) {
        
        $rules = [
            'visitor' => [
                'CAN' => [
                    'Encourage quiz taking',
                    'Explain what the quiz tests',
                    'Answer general questions about the product',
                    'Create curiosity'
                ],
                'CANNOT' => [
                    'Show lesson content',
                    'Provide IPA transcriptions',
                    'Share pronunciation guides',
                    'Mention pricing or offers',
                    'Give away any member content'
                ],
                'MUST' => [
                    'Always redirect to taking the quiz',
                    'Be warm but keep redirecting to quiz'
                ]
            ],
            'guest' => [
                'CAN' => [
                    'Show quiz results',
                    'List lesson titles',
                    'Describe what lessons contain (briefly)',
                    'Show pricing and offers',
                    'Mention time-limited discounts',
                    'Build urgency'
                ],
                'CANNOT' => [
                    'Show full lesson content',
                    'Provide IPA transcriptions',
                    'Share detailed pronunciation guides',
                    'Give step-by-step instructions from lessons'
                ],
                'MUST' => [
                    'Present offers',
                    'Create urgency with timer',
                    'Show value of membership'
                ]
            ],
            'member' => [
                'CAN' => [
                    'Show full lesson content',
                    'Provide IPA transcriptions',
                    'Share pronunciation guides',
                    'Give detailed help',
                    'Link to specific lessons',
                    'Celebrate their membership'
                ],
                'CANNOT' => [
                    // Nothing restricted for members
                ],
                'MUST' => [
                    'Guide them to relevant lessons',
                    'Point to content rather than trying to teach'
                ]
            ]
        ];
        
        return $rules[$access_level] ?? $rules['visitor'];
    }
}
