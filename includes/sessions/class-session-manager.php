<?php
/**
 * FLOSC Session Manager
 * Handles chat session storage and retrieval — scoped per flow.
 *
 * Real-world: a guest on Br3nda must not see LeSAEp chats (and vice versa).
 * Sessions live in user meta `_flosc_sessions` with an optional `flow_id` stem.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Session_Manager {

    private $flosc_session_meta_key = '_flosc_sessions';

    /**
     * Normalize a flow id / ivr path to a stable stem.
     *
     * @param string $flow_id
     * @return string
     */
    public function normalize_flow_stem($flow_id = '') {
        $raw = (string) $flow_id;
        $stem = sanitize_key(pathinfo(basename($raw), PATHINFO_FILENAME));
        if ($stem === '' && $raw !== '') {
            $stem = sanitize_key($raw);
        }
        return $stem;
    }

    /**
     * Resolve current request flow stem when not passed explicitly.
     *
     * @param string $flow_id
     * @return string
     */
    public function resolve_flow_stem($flow_id = '') {
        $stem = $this->normalize_flow_stem($flow_id);
        if ($stem !== '' && $stem !== 'default') {
            return $stem;
        }
        if (function_exists('flosc') && is_object(flosc()) && method_exists(flosc(), 'get_current_flow')) {
            $flow = flosc()->get_current_flow();
            if (is_array($flow)) {
                $stem = $this->normalize_flow_stem(
                    (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '')
                );
            }
        }
        return ($stem !== '' && $stem !== 'default') ? $stem : '';
    }

    /**
     * Whether a session belongs to the requested flow.
     *
     * @param array  $session
     * @param string $stem Requested flow stem (empty = no filter / all flows).
     * @param int    $user_id For legacy untagged sessions.
     * @return bool
     */
    public function session_belongs_to_flow(array $session, $stem, $user_id = 0) {
        $stem = $this->normalize_flow_stem($stem);
        // Fail closed: unscoped stem never matches (prevents cross-flow history bleed).
        if ($stem === '') {
            return false;
        }

        $session_stem = $this->normalize_flow_stem((string) ($session['flow_id'] ?? ''));
        if ($session_stem !== '' && $session_stem !== 'default') {
            return $session_stem === $stem;
        }

        // Legacy sessions (no flow_id): only show on the user's registration flow,
        // or on a LeSAEp stem when registration is empty (old LeSAEp accounts).
        $reg = $this->normalize_flow_stem(
            (string) get_user_meta((int) $user_id, '_flosc_registration_flow', true)
        );
        if ($reg !== '' && $reg !== 'default') {
            return $reg === $stem;
        }

        // No registration: treat untagged as LeSAEp-only (historical default product).
        return (strpos($stem, 'lesaep') !== false);
    }

    /**
     * Get sessions for a user, optionally filtered to one flow (grouped by date).
     *
     * @param int    $user_id
     * @param string $flow_id Flow stem / ivr / id. Empty = all (admin only use).
     * @return array
     */
    public function get_flosc_user_sessions($user_id, $flow_id = '') {
        $empty = [
            'today'        => [],
            'yesterday'    => [],
            'last_7_days'  => [],
            'older'        => [],
        ];

        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            return $empty;
        }

        $stem = $this->resolve_flow_stem($flow_id);
        // No stem = no sessions for normal runtime (fail closed). Admin tools must pass a stem or use a dedicated API.
        if ($stem === '') {
            return $empty;
        }
        $sessions = array_values(array_filter($sessions, function ($session) use ($stem, $user_id) {
            return is_array($session) && $this->session_belongs_to_flow($session, $stem, $user_id);
        }));

        usort($sessions, function ($a, $b) {
            return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0');
        });

        $grouped = $empty;
        $today     = strtotime('today');
        $yesterday = strtotime('yesterday');
        $week_ago  = strtotime('-7 days');

        foreach ($sessions as $session) {
            $updated = strtotime($session['updated_at'] ?? '0');
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
     * Create a new session on a flow.
     *
     * @param int    $user_id
     * @param string $title
     * @param string $flow_id Flow stem for isolation.
     * @return array
     */
    public function flosc_create_session($user_id, $title = 'New Chat', $flow_id = '') {

        $stem = $this->resolve_flow_stem($flow_id);
        if ($stem === '') {
            return null;
        }

        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            $sessions = [];
        }

        $ids = array_column($sessions, 'id');
        $session_id = empty($ids) ? 1 : (int) max($ids) + 1;
        $now = current_time('mysql');

        $session = [
            'id'         => $session_id,
            'title'      => $title,
            'messages'   => [],
            'created_at' => $now,
            'updated_at' => $now,
            'flow_id'    => $stem,
        ];

        $sessions[] = $session;
        update_user_meta($user_id, $this->flosc_session_meta_key, $sessions);

        return $session;
    }

    /**
     * Get a specific session (must belong to user; optional flow match).
     *
     * @param int         $session_id
     * @param int|null    $user_id
     * @param string|null $flow_id When set, reject sessions from other flows.
     * @return array|null
     */
    public function get_flosc_session($session_id, $user_id = null, $flow_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $stem = $this->resolve_flow_stem((string) ($flow_id ?? ''));
        if ($stem === '') {
            return null;
        }

        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            return null;
        }

        foreach ($sessions as $session) {
            if ((int) ($session['id'] ?? 0) !== (int) $session_id) {
                continue;
            }
            if (!$this->session_belongs_to_flow($session, $stem, $user_id)) {
                return null;
            }
            return $session;
        }

        return null;
    }

    /**
     * Add message to session.
     *
     * @param int         $session_id
     * @param string      $role
     * @param string      $content
     * @param int|null    $user_id
     * @param array|null  $meta
     * @param string|null $flow_id Optional flow guard.
     * @return bool
     */
    public function add_flosc_message($session_id, $role, $content, $user_id = null, $meta = null, $flow_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            return false;
        }

        $stem = $this->resolve_flow_stem((string) ($flow_id ?? ''));
        if ($stem === '') {
            return false;
        }

        foreach ($sessions as &$session) {
            if ((int) ($session['id'] ?? 0) !== (int) $session_id) {
                continue;
            }
            if (!$this->session_belongs_to_flow($session, $stem, $user_id)) {
                return false;
            }

            // Backfill flow_id on legacy sessions when we know the flow.
            if (empty($session['flow_id'])) {
                $session['flow_id'] = $stem;
            }

            $message = [
                'role'      => $role,
                'content'   => $content,
                'timestamp' => current_time('mysql'),
            ];

            if (is_array($meta)) {
                $source = sanitize_text_field((string) ($meta['source'] ?? ''));
                $name   = sanitize_text_field((string) ($meta['name'] ?? ''));
                if ($source !== '' || $name !== '') {
                    $message['meta'] = [
                        'source' => $source,
                        'name'   => $name,
                    ];
                }
            }

            $session['messages'][] = $message;
            $session['updated_at'] = current_time('mysql');

            $current_title = trim((string) ($session['title'] ?? ''));
            if ($role === 'user' && ($current_title === '' || $current_title === 'New Chat')) {
                $session['title'] = $this->generate_flosc_session_title($content);
            }

            update_user_meta($user_id, $this->flosc_session_meta_key, $sessions);
            return true;
        }

        return false;
    }

    /**
     * Delete a session (optional flow guard).
     *
     * @param int         $session_id
     * @param int|null    $user_id
     * @param string|null $flow_id
     * @return bool
     */
    public function flosc_delete_session($session_id, $user_id = null, $flow_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            return false;
        }

        $stem = $this->resolve_flow_stem((string) ($flow_id ?? ''));
        if ($stem === '') {
            return false;
        }

        $before = count($sessions);
        $sessions = array_values(array_filter($sessions, function ($s) use ($session_id, $stem, $user_id) {
            if ((int) ($s['id'] ?? 0) !== (int) $session_id) {
                return true;
            }
            if (!$this->session_belongs_to_flow($s, $stem, $user_id)) {
                return true; // wrong flow — do not delete
            }
            return false; // drop matching session
        }));

        if (count($sessions) === $before) {
            return false;
        }

        update_user_meta($user_id, $this->flosc_session_meta_key, $sessions);
        return true;
    }

    /**
     * Generate session title from first message.
     *
     * @param string $content
     * @return string
     */
    private function generate_flosc_session_title($content) {
        $plain = wp_strip_all_tags((string) $content);
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? '');
        if ($plain === '') {
            return 'New Chat';
        }
        if (function_exists('mb_substr')) {
            $title  = mb_substr($plain, 0, 40, 'UTF-8');
            $longer = mb_strlen($plain, 'UTF-8') > 40;
        } else {
            $title  = substr($plain, 0, 40);
            $longer = strlen($plain) > 40;
        }
        if ($longer) {
            $title .= '...';
        }
        return $title !== '' ? $title : 'New Chat';
    }

    /**
     * Session count for user (optional per-flow).
     *
     * @param int    $user_id
     * @param string $flow_id
     * @return int
     */
    public function get_flosc_session_count($user_id, $flow_id = '') {
        $sessions = get_user_meta($user_id, $this->flosc_session_meta_key, true);
        if (!is_array($sessions)) {
            return 0;
        }
        $stem = $this->resolve_flow_stem($flow_id);
        if ($stem === '') {
            return count($sessions);
        }
        $n = 0;
        foreach ($sessions as $session) {
            if (is_array($session) && $this->session_belongs_to_flow($session, $stem, $user_id)) {
                $n++;
            }
        }
        return $n;
    }
}
