<?php
/**
 * Session Manager
 * Handles chat session creation, storage, and retrieval
 */

if (!defined('ABSPATH')) {
    exit;
}

class LeSAEp_Session_Manager {

    /**
     * List all sessions for current user
     */
    public function list_sessions() {
        $user_id = get_current_user_id();

        if ($user_id) {
            // Get sessions from user meta
            $sessions = get_user_meta($user_id, '_lesaep_sessions', true);
        } else {
            // For visitors, return empty (sessions stored in localStorage client-side)
            $sessions = array();
        }

        if (!is_array($sessions)) {
            $sessions = array();
        }

        // Sort by created_at descending
        usort($sessions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        // Group by date
        return $this->group_sessions_by_date($sessions);
    }

    /**
     * Load specific session
     */
    public function load_session($session_id) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        $sessions = get_user_meta($user_id, '_lesaep_sessions', true);

        if (!is_array($sessions)) {
            return false;
        }

        foreach ($sessions as $session) {
            if ($session['session_id'] === $session_id) {
                return $session;
            }
        }

        return false;
    }

    /**
     * Create new session
     */
    public function create_session() {
        $session_id = 'session_' . uniqid() . '_' . time();
        $user_id = get_current_user_id();

        $session = array(
            'session_id' => $session_id,
            'created_at' => current_time('mysql'),
            'title' => 'New chat',
            'messages' => array(),
        );

        if ($user_id) {
            // Save to user meta
            $sessions = get_user_meta($user_id, '_lesaep_sessions', true);

            if (!is_array($sessions)) {
                $sessions = array();
            }

            $sessions[] = $session;
            update_user_meta($user_id, '_lesaep_sessions', $sessions);
        }

        return $session_id;
    }

    /**
     * Append message to session
     */
    public function append_message($session_id, $role, $content) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        $sessions = get_user_meta($user_id, '_lesaep_sessions', true);

        if (!is_array($sessions)) {
            return false;
        }

        $found = false;
        foreach ($sessions as $key => $session) {
            if ($session['session_id'] === $session_id) {
                // Add message
                $sessions[$key]['messages'][] = array(
                    'role' => $role,
                    'content' => $content,
                    'timestamp' => current_time('mysql'),
                );

                // Update title from first user message
                if ($role === 'user' && $session['title'] === 'New chat' && count($sessions[$key]['messages']) === 1) {
                    $sessions[$key]['title'] = $this->generate_title($content);
                }

                $found = true;
                break;
            }
        }

        if ($found) {
            update_user_meta($user_id, '_lesaep_sessions', $sessions);
            return true;
        }

        return false;
    }

    /**
     * Generate title from first message
     */
    private function generate_title($content) {
        // Take first 50 characters
        $title = substr($content, 0, 50);

        if (strlen($content) > 50) {
            $title .= '...';
        }

        return $title;
    }

    /**
     * Group sessions by date
     */
    private function group_sessions_by_date($sessions) {
        $grouped = array(
            'today' => array(),
            'yesterday' => array(),
            'last_7_days' => array(),
            'older' => array(),
        );

        $now = current_time('timestamp');
        $today_start = strtotime('today', $now);
        $yesterday_start = strtotime('yesterday', $now);
        $seven_days_ago = strtotime('-7 days', $now);

        foreach ($sessions as $session) {
            $session_time = strtotime($session['created_at']);

            if ($session_time >= $today_start) {
                $grouped['today'][] = $session;
            } elseif ($session_time >= $yesterday_start) {
                $grouped['yesterday'][] = $session;
            } elseif ($session_time >= $seven_days_ago) {
                $grouped['last_7_days'][] = $session;
            } else {
                $grouped['older'][] = $session;
            }
        }

        return $grouped;
    }

    /**
     * Delete session
     */
    public function delete_session($session_id) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        $sessions = get_user_meta($user_id, '_lesaep_sessions', true);

        if (!is_array($sessions)) {
            return false;
        }

        $sessions = array_filter($sessions, function($session) use ($session_id) {
            return $session['session_id'] !== $session_id;
        });

        update_user_meta($user_id, '_lesaep_sessions', $sessions);
        return true;
    }
}
