<?php
/**
 * Quiz Handler - Manages quiz sessions and results
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Quiz_Handler {
    
    /**
     * Single instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create new quiz session
     */
    public function create_session($user_id = null, $email = null) {
        global $wpdb;
        
        // Generate unique quiz ID
        $quiz_id = $this->generate_quiz_id();
        
        // Insert into database
        $wpdb->insert(
            $wpdb->prefix . 'flosc_quiz_sessions',
            array(
                'quiz_id' => $quiz_id,
                'user_id' => $user_id,
                'email' => $email,
                'no_count' => 0,
                'completed' => 0,
                'blocked' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%d', '%d', '%d', '%s')
        );
        
        return $quiz_id;
    }
    
    /**
     * Get quiz session
     */
    public function get_session($quiz_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}flosc_quiz_sessions WHERE quiz_id = %s",
            $quiz_id
        ), ARRAY_A);
    }
    
    /**
     * Update quiz session
     */
    public function update_session($quiz_id, $data) {
        global $wpdb;
        
        $data['updated_at'] = current_time('mysql');
        
        return $wpdb->update(
            $wpdb->prefix . 'flosc_quiz_sessions',
            $data,
            array('quiz_id' => $quiz_id)
        );
    }
    
    /**
     * Record "No" response
     */
    public function record_no($quiz_id) {
        $session = $this->get_session($quiz_id);
        
        if (!$session) {
            return array('error' => 'Session not found');
        }
        
        if ($session['blocked']) {
            return array('blocked' => true, 'message' => 'User is blocked');
        }
        
        $no_count = intval($session['no_count']) + 1;
        $max_attempts = get_option('flosc_max_no_attempts', 3);
        
        $update_data = array('no_count' => $no_count);
        
        if ($no_count >= $max_attempts) {
            $update_data['blocked'] = 1;
        }
        
        $this->update_session($quiz_id, $update_data);
        
        return array(
            'no_count' => $no_count,
            'blocked' => $no_count >= $max_attempts
        );
    }
    
    /**
     * Save quiz result
     */
    public function save_result($quiz_id, $sentence_index, $audio_path, $analysis_data) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'flosc_quiz_results',
            array(
                'quiz_id' => $quiz_id,
                'sentence_index' => $sentence_index,
                'audio_path' => $audio_path,
                'transcription' => isset($analysis_data['transcription']) ? $analysis_data['transcription'] : '',
                'accuracy' => isset($analysis_data['accuracy']) ? $analysis_data['accuracy'] : 0,
                'flagged_phonemes' => isset($analysis_data['flagged_phonemes']) ? json_encode($analysis_data['flagged_phonemes']) : '[]',
                'missing_words' => isset($analysis_data['missing_words']) ? json_encode($analysis_data['missing_words']) : '[]',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%s', '%f', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get quiz results
     */
    public function get_results($quiz_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}flosc_quiz_results WHERE quiz_id = %s ORDER BY sentence_index ASC",
            $quiz_id
        ), ARRAY_A);
        
        // Decode JSON fields
        foreach ($results as &$result) {
            $result['flagged_phonemes'] = json_decode($result['flagged_phonemes'], true);
            $result['missing_words'] = json_decode($result['missing_words'], true);
        }
        
        return $results;
    }
    
    /**
     * Complete quiz
     */
    public function complete_quiz($quiz_id) {
        $results = $this->get_results($quiz_id);
        
        // Aggregate flagged phonemes
        $all_phonemes = array();
        foreach ($results as $result) {
            if (!empty($result['flagged_phonemes'])) {
                $all_phonemes = array_merge($all_phonemes, $result['flagged_phonemes']);
            }
        }
        $all_phonemes = array_unique($all_phonemes);
        
        // Update session
        $this->update_session($quiz_id, array(
            'completed' => 1,
            'flagged_phonemes' => json_encode($all_phonemes),
            'completed_at' => current_time('mysql')
        ));
        
        return array(
            'flagged_phonemes' => $all_phonemes,
            'phoneme_count' => count($all_phonemes)
        );
    }
    
    /**
     * Mark as paid
     */
    public function mark_paid($quiz_id, $stripe_session_id) {
        return $this->update_session($quiz_id, array(
            'is_paid' => 1,
            'stripe_session_id' => $stripe_session_id
        ));
    }
    
    /**
     * Get lessons for phonemes
     */
    public function get_lessons_for_phonemes($phonemes, $free_only = false) {
        $args = array(
            'post_type' => 'flosc_lesson',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_flosc_phoneme_group',
                    'value' => $phonemes,
                    'compare' => 'IN'
                )
            )
        );
        
        if ($free_only) {
            $args['meta_query'][] = array(
                'key' => '_flosc_is_free',
                'value' => '1'
            );
        }
        
        $lessons = get_posts($args);
        
        $result = array();
        foreach ($lessons as $lesson) {
            $result[] = array(
                'id' => $lesson->ID,
                'title' => $lesson->post_title,
                'phoneme_group' => get_post_meta($lesson->ID, '_flosc_phoneme_group', true),
                'is_free' => get_post_meta($lesson->ID, '_flosc_is_free', true) === '1',
                'permalink' => get_permalink($lesson->ID)
            );
        }
        
        return $result;
    }
    
    /**
     * Generate unique quiz ID
     */
    private function generate_quiz_id() {
        return substr(md5(uniqid(rand(), true)), 0, 16);
    }
    
    /**
     * Get user's quiz sessions
     */
    public function get_user_sessions($user_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}flosc_quiz_sessions WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ), ARRAY_A);
    }
    
    /**
     * Check if user has paid
     */
    public function user_has_paid($user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}flosc_quiz_sessions WHERE user_id = %d AND is_paid = 1",
            $user_id
        ));
        
        return $count > 0;
    }
}
