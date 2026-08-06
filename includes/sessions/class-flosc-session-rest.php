<?php
/**
 * Domain collaborator — FLOSC_Session_Rest
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Session_Rest {

    /** @var FLOSC_Framework */
    private $flosc;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    public function get_sessions($request) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $flow_stem = $this->flosc->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }

        $sessions = $this->flosc->sessions()->get_flosc_user_sessions( $user_id, $flow_stem );
        return new WP_REST_Response(
            array(
                'success'  => true,
                'sessions' => is_array( $sessions ) ? $sessions : array(),
                'flow_id'  => $flow_stem,
            )
        );
    }

    /**
     * Get a single session by ID (owner + flow scoped; fail closed).
     */
    public function get_single_session($request) {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $session_id = absint( $request->get_param( 'id' ) );
        $flow_stem  = $this->flosc->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }

        $session = $this->flosc->sessions()->get_flosc_session(
            $session_id,
            $user_id,
            $flow_stem
        );

        if ( ! is_array( $session ) ) {
            // Same status for missing, wrong user, wrong flow — no disclosure.
            return new WP_Error(
                'flosc_session_forbidden',
                __( 'Session not available.', 'flosc' ),
                array( 'status' => 403 )
            );
        }

        return new WP_REST_Response(
            array(
                'success' => true,
                'session' => $session,
                'flow_id' => $flow_stem,
            )
        );
    }

    public function create_session($request) {
        // New chat = new session on THIS flow only.
        $title   = 'New Chat';
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }

        $flow_stem = $this->flosc->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }

        // Guest chat cap (0 = unlimited). Members are not capped by guest_max_chats.
        $user_state = $this->get_user_state_for_session_limits($user_id, $flow_stem);
        if ($user_state === 'guest') {
            $max_chats = max(0, intval(flosc_get_setting('guest_max_chats', 0)));
            $count = $this->flosc->sessions()->get_flosc_session_count($user_id, $flow_stem);
            if ($max_chats > 0 && $count >= $max_chats) {
                $limit_msg = flosc_get_setting(
                    'guest_new_chat_limit_message',
                    'Your guest account allows {max} chats listed below. If you would like to start a new chat, you can delete one below.'
                );
                $user = get_userdata($user_id);
                $identity = method_exists($this, 'get_floscflow_identity')
                    ? $this->flosc->get_floscflow_identity()
                    : [];
                $name = $user ? (string) ($user->display_name ?: $user->user_login) : '';
                $flow_name = is_array($identity) ? (string) ($identity['name'] ?? 'FLOSC') : 'FLOSC';
                $limit_msg = str_replace(
                    ['{max}', '{count}', '{flow_name}', '{name}', '{NickName}'],
                    [(string) $max_chats, (string) $count, $flow_name, $name, $name],
                    (string) $limit_msg
                );
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'guest_chat_limit',
                    'code' => 'guest_chat_limit',
                    'message' => $limit_msg,
                    'max' => $max_chats,
                    'count' => $count,
                ], 403);
            }
        }

        $session = $this->flosc->sessions()->flosc_create_session($user_id, $title, $flow_stem);
        if (!$session) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'flow_id required',
                'code'    => 'flow_id_required',
            ], 400);
        }
        return new WP_REST_Response(['success' => true, 'session' => $session]);
    }

    /**
     * Map current user to visitor|guest|member for chat-session limits (per flow).
     *
     * @param int    $user_id User ID.
     * @param string $flow_id Flow stem.
     * @return string
     */
    public function get_user_state_for_session_limits($user_id, $flow_id = '') {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 'visitor';
        }
        if (user_can($user_id, 'manage_options')) {
            return 'member';
        }
        if ($this->flosc->sale() && method_exists($this->flosc->sale(), 'access')) {
            $state = $this->flosc->sale()->access()->get_simple_state($user_id, $flow_id);
            return ($state === 'member') ? 'member' : 'guest';
        }
        $member_access = $this->flosc->member_access();
        if (is_object($member_access)
            && method_exists($member_access, 'is_member')
            && $member_access->is_member($user_id, $flow_id)) {
            return 'member';
        }
        return 'guest';
    }

    /**
     * Guest chat sidebar flags: default true if key never saved; ''/'0' = false.
     *
     * @param string $key Flow setting key (guest_can_delete_chats | guest_can_rename_chats).
     * @return bool
     */
    public function flosc_guest_chat_flag_enabled($key) {
        $key = sanitize_key((string) $key);
        $val = flosc_get_setting($key, null);
        // flosc_get_setting may not distinguish missing vs empty; probe flow option.
        $flow = $this->flosc->get_current_flow();
        $stem = '';
        if (is_array($flow)) {
            $ivr = (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '');
            $stem = sanitize_key(pathinfo(basename($ivr), PATHINFO_FILENAME));
        }
        if ($stem !== '') {
            $fs = get_option('flosc_flow_' . $stem, []);
            if (is_array($fs) && array_key_exists($key, $fs)) {
                $v = $fs[$key];
                return !($v === '' || $v === '0' || $v === 0 || $v === false || $v === null);
            }
        }
        if ($val === null || $val === false) {
            return true;
        }
        return !($val === '' || $val === '0' || $val === 0);
    }

    /**
     * v8.0.11: Delete a session
     */
    public function delete_session($request) {
        $session_id = absint( $request->get_param( 'id' ) );
        $user_id    = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }
        $flow_stem = $this->flosc->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }
        $state = $this->get_user_state_for_session_limits( $user_id, $flow_stem );
        if ( $state === 'guest' && ! $this->flosc_guest_chat_flag_enabled( 'guest_can_delete_chats' ) ) {
            return new WP_Error(
                'flosc_guest_delete_disabled',
                __( 'Deleting chats is not available for this account.', 'flosc' ),
                array( 'status' => 403 )
            );
        }
        $deleted = $this->flosc->sessions()->flosc_delete_session( $session_id, $user_id, $flow_stem );
        if ( ! $deleted ) {
            return new WP_Error(
                'flosc_session_forbidden',
                __( 'Session not available.', 'flosc' ),
                array( 'status' => 403 )
            );
        }
        return new WP_REST_Response( array( 'success' => true, 'flow_id' => $flow_stem ) );
    }

    /**
     * Rename a session (owner + flow scoped).
     */
    public function rename_session($request) {
        $session_id = absint( $request->get_param( 'id' ) );
        $title      = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
        $user_id    = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error(
                'flosc_login_required',
                __( 'Login is required.', 'flosc' ),
                array( 'status' => 401 )
            );
        }
        if ( $title === '' ) {
            return new WP_Error(
                'flosc_title_required',
                __( 'Title is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }
        $flow_stem = $this->flosc->flosc_request_flow_stem( $request );
        if ( $flow_stem === '' ) {
            return new WP_Error(
                'flosc_flow_required',
                __( 'Flow is required.', 'flosc' ),
                array( 'status' => 400 )
            );
        }
        $sessions = get_user_meta( $user_id, '_flosc_sessions', true );
        if ( ! is_array( $sessions ) ) {
            return new WP_Error(
                'flosc_session_forbidden',
                __( 'Session not available.', 'flosc' ),
                array( 'status' => 403 )
            );
        }
        $found = false;
        $state = $this->get_user_state_for_session_limits( $user_id, $flow_stem );
        foreach ( $sessions as &$s ) {
            if ( (int) ( $s['id'] ?? 0 ) !== $session_id ) {
                continue;
            }
            if ( ! $this->flosc->sessions()->session_belongs_to_flow( $s, $flow_stem, $user_id ) ) {
                return new WP_Error(
                    'flosc_session_forbidden',
                    __( 'Session not available.', 'flosc' ),
                    array( 'status' => 403 )
                );
            }
            $old            = trim( (string) ( $s['title'] ?? '' ) );
            $is_placeholder = ( $old === '' || $old === 'New Chat' );
            // Manual rename: guests need guest_can_rename_chats.
            // Auto-title from first user message while still "New Chat": always allowed.
            if ( $state === 'guest'
                && ! $is_placeholder
                && ! $this->flosc_guest_chat_flag_enabled( 'guest_can_rename_chats' )
            ) {
                return new WP_Error(
                    'flosc_guest_rename_disabled',
                    __( 'Renaming chats is not available for this account.', 'flosc' ),
                    array( 'status' => 403 )
                );
            }
            $s['title'] = $title;
            $found      = true;
            break;
        }
        unset( $s );
        if ( ! $found ) {
            return new WP_Error(
                'flosc_session_forbidden',
                __( 'Session not available.', 'flosc' ),
                array( 'status' => 403 )
            );
        }
        update_user_meta( $user_id, '_flosc_sessions', $sessions );
        return new WP_REST_Response( array( 'success' => true, 'flow_id' => $flow_stem ) );
    }

    /**
     * Delete a DO session directory after its data has been pulled to WP.
     * Fire-and-forget: failures are logged but do not block the login flow.
     */
    public function delete_session_from_do($session_id) {
        if (!preg_match('/^\d{4}-\d{2}m-\d{2}d-\d{2}h-\d{2}m-\d{2}s-[0-9a-f]{5}$/', $session_id)) {
            return;
        }
        $api_base = untrailingslashit(flosc_get_setting('ipa_api_base_url', ''));
        $response = flosc_safe_remote_request('DELETE', $api_base . '/session/' . $session_id, [
            'timeout'   => 10,
        ]);
        if (is_wp_error($response)) {
            if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC: delete_session_from_do — {$session_id}: " . $response->get_error_message());
        }
    }

    /**
     * Normalize client session id values (numeric, hex, opaque strings)
     * into a stable positive integer for storage/log/token accounting.
     */
    public function flosc_normalize_session_id($session_id_raw) {
        $raw = trim((string) $session_id_raw);
        if ($raw === '') {
            return 0;
        }

        // Legacy short numeric ids (logged-in chat sessions, older clients).
        if (ctype_digit($raw) && strlen($raw) <= 9) {
            return max(1, intval($raw));
        }

        // Opaque visitor ids (UUID, etc.): stable positive int via SHA-256, not CRC32.
        // Full opaque string remains the client identity; this maps for int-keyed stores.
        $hex = substr(hash('sha256', $raw), 0, 8);
        $value = (int) (hexdec($hex) % 2147483647);
        if ($value <= 0) {
            $value = 1;
        }
        return $value;
    }

    /**
     * Stable, per-visitor key for a concierge desk.
     *
     * The desk is scoped to ONE guest. A logged-in user keys by user id; an
     * anonymous visitor keys by their session id when present, otherwise by a
     * salted hash of their IP. It must NEVER fall back to a shared constant
     * (e.g. "user_0") — that would leak one guest's open desk into every other
     * anonymous visitor's chat (it contaminated even the welcome greeting).
     *
     * @param int $session_id Frontend session id (0 when absent).
     * @return string
     */
    public function flosc_concierge_session_key($session_id) {
        if (is_user_logged_in()) {
            return 'u' . get_current_user_id();
        }
        $session_id = intval($session_id);
        if ($session_id > 0) {
            return 's' . $session_id;
        }
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : 'unknown';
        return 'ip' . substr(hash('sha256', $ip . wp_salt()), 0, 16);
    }

}
