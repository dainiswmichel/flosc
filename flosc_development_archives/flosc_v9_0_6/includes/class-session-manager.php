<?php
/**
 * FLOSC Session Manager
 * Handles chat session storage and retrieval
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Session_Manager {
    
    private $flosc_session_meta_key = '_flosc_sessions';
    
    /**
     * Get all sessions for a user (grouped by date)
     */
    public function get_flosc_user_sessions($user_id) {
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        
        if (!is_array($sessions)) {
            return [
                'today' => [],
                'yesterday' => [],
                'last_7_days' => [],
                'older' => [],
            ];
        }
        
        // Sort by updated_at descending
        usort($sessions, function($a, $b) {
            return strtotime($b['updated_at']) - strtotime($a['updated_at']);
        });
        
        // Group by date
        $grouped = [
            'today' => [],
            'yesterday' => [],
            'last_7_days' => [],
            'older' => [],
        ];
        
        $today = strtotime('today');
        $yesterday = strtotime('yesterday');
        $week_ago = strtotime('-7 days');
        
        foreach ($sessions as $session) {
            $updated = strtotime($session['updated_at']);
            
            if ($updated >= $today) {
                $grouped['today'][] = $session;
            } elseif ($updated >= $yesterday) {
                $grouped['yesterday'][] = $session;
            } elseif ($updated >= $week_ago) {
                $grouped['last_7_days'][] = $session;
            } else {
                $grouped['older'][] = $session;
            }
        }
        
        return $grouped;
    }
    
    /**
     * Create a new session
     */
    public function flosc_create_session($user_id, $title = 'New Chat') {
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        
        if (!is_array($sessions)) {
            $sessions = [];
        }
        
        $session_id = count($sessions) + 1;
        $now = current_time('mysql');
        
        $session = [
            'id' => $session_id,
            'title' => $title,
            'messages' => [],
            'created_at' => $now,
            'updated_at' => $now,
        ];
        
        $sessions[] = $session;
        update_user_meta($user_id, $this->flosc_session_meta_key, $sessions);
        
        return $session;
    }
    
    /**
     * Get a specific session
     */
    public function get_flosc_session($session_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        
        if (!is_array($sessions)) {
            return null;
        }
        
        foreach ($sessions as $session) {
            if ($session['id'] == $session_id) {
                return $session;
            }
        }
        
        return null;
    }
    
    /**
     * Add message to session
     */
    public function add_flosc_message($session_id, $role, $content, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        
        if (!is_array($sessions)) {
            return false;
        }
        
        foreach ($sessions as &$session) {
            if ($session['id'] == $session_id) {
                $session['messages'][] = [
                    'role' => $role,
                    'content' => $content,
                    'timestamp' => current_time('mysql'),
                ];
                $session['updated_at'] = current_time('mysql');
                
                // Update title from first user message if still "New Chat"
                if ($session['title'] === 'New Chat' && $role === 'user') {
                    $session['title'] = $this->generate_flosc_session_title($content);
                }
                
                update_user_meta($user_id, $this->flosc_session_meta_key, $sessions);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Delete a session
     */
    public function flosc_delete_session($session_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        
        if (!is_array($sessions)) {
            return false;
        }
        
        $sessions = array_filter($sessions, function($s) use ($session_id) {
            return $s['id'] != $session_id;
        });
        
        update_user_meta($user_id, $this->flosc_session_meta_key, array_values($sessions));
        return true;
    }
    
    /**
     * Generate session title from first message
     */
    private function generate_flosc_session_title($content) {
        // Take first 40 chars
        $title = substr($content, 0, 40);
        
        // Clean up
        $title = trim(preg_replace('/\s+/', ' ', $title));
        
        if (strlen($content) > 40) {
            $title .= '...';
        }
        
        return $title ?: 'New Chat';
    }
    
    /**
     * Get session count for user
     */
    public function get_flosc_session_count($user_id) {
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        return is_array($sessions) ? count($sessions) : 0;
    }
}
