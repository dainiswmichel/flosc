<?php
/**
 * FLOSC Chat Logger
 * v1.9.0: Logs all chat exchanges for real-time monitoring and later retrieval.
 *
 * Storage: Custom WordPress table {prefix}flosc_chat_logs
 * Access: Admin-only viewer via FLOSC Settings → Chat Logs tab
 */

if (!defined('ABSPATH')) exit;

// Custom table {prefix}flosc_chat_logs — no WP API equivalent for schema/CRUD.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- custom table flosc_chat_logs; no WP API

class FLOSC_Chat_Logger {

    private static $instance = null;
    private $table_name;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'flosc_chat_logs';
    }

    /**
     * Bust short-lived chat-log object-cache keys after writes.
     *
     * @return void
     */
    private function flosc_bust_log_caches() {
        foreach ( array( 25, 50, 100 ) as $flosc_lim ) {
            wp_cache_delete( 'rated_logs_' . $flosc_lim, 'flosc_chat_logs' );
        }
        wp_cache_delete( 'log_count_all', 'flosc_chat_logs' );
        wp_cache_delete( 'log_count_' . md5( '' ), 'flosc_chat_logs' );
    }

    private function flosc_archived_sessions_option_name() {
        return 'flosc_archived_chat_sessions';
    }

    private function flosc_archive_bucket_key($flow_id = '') {
        $flow_id = sanitize_text_field((string) $flow_id);
        return $flow_id !== '' ? $flow_id : '__all';
    }

    /**
     * Normalize a client-supplied journey id.
     *
     * The journey id is an opaque string the browser mints once and keeps across
     * the login boundary, so every turn of one conversation carries the same
     * value even though session_id changes from the visitor's hashed id to the
     * numeric user-meta session id at login. Restricted to the characters a
     * UUID (or the client's fallback id) can contain, so it is safe to use as a
     * grouping key and in an option array key.
     *
     * @param mixed $raw Client-supplied value.
     * @return string Sanitized journey id, or '' when unusable.
     */
    public static function flosc_sanitize_journey_id($raw) {
        $raw = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $raw);
        return substr((string) $raw, 0, 64);
    }

    public static function flosc_session_key_from_descriptor($by, $value) {
        $by = in_array($by, ['journey', 'session', 'user', 'ip'], true) ? $by : '';
        if ($by === '') {
            return '';
        }

        if ($by === 'journey') {
            $journey_id = self::flosc_sanitize_journey_id($value);
            return $journey_id !== '' ? 'j' . $journey_id : '';
        }

        if ($by === 'session') {
            $session_id = intval($value);
            return $session_id > 0 ? 's' . $session_id : '';
        }

        if ($by === 'user') {
            $user_id = intval($value);
            return $user_id > 0 ? 'u' . $user_id : '';
        }

        $ip = sanitize_text_field((string) $value);
        return $ip !== '' ? 'ip' . $ip : '';
    }

    public function flosc_get_archived_session_keys($flow_id = '') {
        $bucket = $this->flosc_archive_bucket_key($flow_id);
        $all = get_option($this->flosc_archived_sessions_option_name(), []);
        if (!is_array($all)) {
            return [];
        }

        $keys = $all[$bucket] ?? [];
        if (!is_array($keys)) {
            return [];
        }

        $keys = array_values(array_filter(array_map('sanitize_text_field', $keys)));
        return array_values(array_unique($keys));
    }

    public function flosc_set_session_archived($by, $value, $flow_id = '', $archived = true) {
        $key = self::flosc_session_key_from_descriptor($by, $value);
        if ($key === '') {
            return false;
        }

        $bucket = $this->flosc_archive_bucket_key($flow_id);
        $all = get_option($this->flosc_archived_sessions_option_name(), []);
        if (!is_array($all)) {
            $all = [];
        }

        $keys = $all[$bucket] ?? [];
        if (!is_array($keys)) {
            $keys = [];
        }

        $keys = array_values(array_unique(array_filter(array_map('sanitize_text_field', $keys))));

        if ($archived) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        } else {
            $keys = array_values(array_filter($keys, static function ($existing_key) use ($key) {
                return $existing_key !== $key;
            }));
        }

        if (empty($keys)) {
            unset($all[$bucket]);
        } else {
            $all[$bucket] = $keys;
        }

        return update_option($this->flosc_archived_sessions_option_name(), $all, false);
    }

    /**
     * Create the chat logs table if it doesn't exist.
     * Called on plugin activation and on first use.
     */
    public function flosc_ensure_table() {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            flow_id VARCHAR(100) DEFAULT '',
            phase VARCHAR(50) DEFAULT 'freeline',
            user_id BIGINT UNSIGNED DEFAULT 0,
            session_id BIGINT UNSIGNED DEFAULT 0,
            journey_id VARCHAR(64) NOT NULL DEFAULT '',
            visitor_ip VARCHAR(45) DEFAULT '',
            user_message TEXT NOT NULL,
            ai_response TEXT NOT NULL,
            provider VARCHAR(50) DEFAULT 'ivr',
            chain_detail VARCHAR(255) DEFAULT '',
            response_source VARCHAR(50) DEFAULT 'ivr',
            surface VARCHAR(20) DEFAULT '',
            page_url VARCHAR(255) DEFAULT '',
            page_title VARCHAR(255) DEFAULT '',
            personality_id VARCHAR(100) DEFAULT '',
            personality_name VARCHAR(120) DEFAULT '',
            profile_hash VARCHAR(64) DEFAULT '',
            turn_status VARCHAR(20) DEFAULT 'complete',
            turn_id VARCHAR(64) DEFAULT '',
            response_time_ms INT UNSIGNED DEFAULT 0,
            billing_source VARCHAR(50) DEFAULT '',
            billing_model VARCHAR(120) DEFAULT '',
            billing_input_tokens INT UNSIGNED DEFAULT 0,
            billing_output_tokens INT UNSIGNED DEFAULT 0,
            billing_total_tokens INT UNSIGNED DEFAULT 0,
            billing_real_millicents INT UNSIGNED DEFAULT 0,
            admin_rating TINYINT NOT NULL DEFAULT 0,
            admin_note TEXT DEFAULT NULL,
            rated_at DATETIME DEFAULT NULL,
            rated_by BIGINT UNSIGNED DEFAULT NULL,
            is_protected TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_flosc_chat_timestamp (timestamp),
            KEY idx_flosc_chat_user (user_id),
            KEY idx_flosc_chat_flow (flow_id),
            KEY idx_flosc_chat_phase (phase),
            KEY idx_flosc_chat_session (session_id),
            KEY idx_flosc_chat_journey (journey_id),
            KEY idx_flosc_chat_personality (personality_id),
            KEY idx_flosc_chat_turn (turn_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        // dbDelta safely creates or updates plugin-owned table schema without manual ALTER statements.
        dbDelta($sql);

        return true;
    }

    /**
     * v1.9.5: Rate a chat log entry. Score from -10 to +10 with optional note.
     * Any non-zero rating auto-protects the log from expunge.
     *
     * @param int    $log_id  The chat log row ID
     * @param int    $rating  Score from -10 to +10
     * @param string $note    Admin's note (why this score)
     * @return bool True on success
     */
    public function flosc_rate_log($log_id, $rating, $note = '') {
        global $wpdb;

        // Clamp to -10..+10
        $rating = max(-10, min(10, intval($rating)));

        // Any non-zero rating auto-protects the row from auto-expunge
        $is_protected = ($rating !== 0) ? 1 : 0;

        $this->flosc_ensure_table();

        $result = $wpdb->update(
            $this->table_name,
            [
                'admin_rating' => $rating,
                'admin_note'   => sanitize_textarea_field($note),
                'rated_at'     => current_time('mysql'),
                'rated_by'     => get_current_user_id(),
                'is_protected' => $is_protected,
            ],
            ['id' => intval($log_id)],
            ['%d', '%s', '%s', '%d', '%d'],
            ['%d']
        );

        if ( $result !== false ) {
            wp_cache_delete( 'rated_logs_50', 'flosc_chat_logs' );
            wp_cache_delete( 'log_count_' . md5( '' ), 'flosc_chat_logs' );
            $this->flosc_bust_log_caches();
        }

        return $result !== false;
    }

    /**
     * Load recent turns for one session the current request is allowed to see.
     *
     * Visitor rows require the opaque client session string (not a short numeric
     * guess) and a matching hashed visitor IP already stored on that session.
     * Logged-in rows require the current WordPress user to own the session.
     * Destination flow is not required so floscDomain visits keep the transcript.
     *
     * @param int    $session_id     Normalized session id.
     * @param int    $max_pairs      Max user/assistant pairs (each pair is two roles).
     * @param string $session_id_raw Client-supplied session string.
     * @return array<int,array{role:string,content:string}>
     */
    public function flosc_get_session_turns($session_id, $max_pairs = 10, $session_id_raw = '') {
        global $wpdb;

        $session_id = absint($session_id);
        $max_pairs  = max(1, min(50, absint($max_pairs)));
        $raw        = trim((string) $session_id_raw);
        if ($session_id <= 0) {
            return [];
        }
        if ($raw === '' || (ctype_digit($raw) && strlen($raw) <= 9)) {
            return [];
        }
        if (!$this->flosc_current_request_owns_session($session_id)) {
            return [];
        }

        $this->flosc_ensure_table();
        $limit = $max_pairs;
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_message, ai_response FROM %i
                     WHERE session_id = %d AND user_id = %d
                       AND response_source <> 'state_change'
                     ORDER BY id DESC
                     LIMIT %d",
                    $this->table_name,
                    $session_id,
                    $user_id,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $visitor_ip = $this->flosc_get_hashed_ip();
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_message, ai_response FROM %i
                     WHERE session_id = %d AND user_id = 0 AND visitor_ip = %s
                       AND response_source <> 'state_change'
                     ORDER BY id DESC
                     LIMIT %d",
                    $this->table_name,
                    $session_id,
                    $visitor_ip,
                    $limit
                ),
                ARRAY_A
            );
        }
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $rows = array_reverse($rows);
        $out  = [];
        foreach ($rows as $row) {
            $user = trim((string) ($row['user_message'] ?? ''));
            $ai   = trim((string) ($row['ai_response'] ?? ''));
            if ($user !== '') {
                $out[] = [
                    'role'    => 'user',
                    'content' => $user,
                ];
            }
            if ($ai !== '') {
                $out[] = [
                    'role'    => 'assistant',
                    'content' => $ai,
                ];
            }
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────
    // Journey marks — VGM state changes as rows in the thread
    // ──────────────────────────────────────────────────────────────

    /**
     * Meta key holding acquisition marks waiting to be written into a thread.
     *
     * @return string
     */
    private static function flosc_journey_marks_meta_key() {
        return '_flosc_journey_marks_pending';
    }

    /**
     * Normalize a flow id to the stem the log table and the grant meta both use.
     *
     * @param string $flow_id
     * @return string Stem, or '' when there is no flow.
     */
    public static function flosc_journey_flow_stem($flow_id) {
        $stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        if ($stem === '') {
            $stem = sanitize_key((string) $flow_id);
        }
        return $stem;
    }

    /**
     * Record that a user acquired something, to be written into their thread later.
     *
     * The acquisition events fire where the browser is not present -- a PayPal IPN,
     * a ClickBank postback, an OAuth callback -- so the journey id is unknown at
     * that moment. Parking the mark on the user and redeeming it on their next
     * logged turn is what lets the row land in the right thread, in order, without
     * guessing from timestamps.
     *
     * One-shot per user per (mark, flow): you can only become a guest once, and a
     * member of any one flow once, so a re-grant or a renewal must not queue a
     * second "+M".
     *
     * @param int    $user_id
     * @param string $mark    '+G' or '+M'.
     * @param string $flow_id Flow the acquisition belongs to. '' = account-wide.
     * @return void
     */
    public static function flosc_queue_journey_mark($user_id, $mark, $flow_id = '') {
        $user_id = (int) $user_id;
        $mark    = in_array($mark, ['+G', '+M'], true) ? $mark : '';
        if ($user_id <= 0 || $mark === '') {
            return;
        }

        $stem = self::flosc_journey_flow_stem($flow_id);

        // One-shot guard. Kept as its own meta key rather than scanning the queue,
        // because the queue is emptied as soon as the marks are written.
        $once_key = '_flosc_journey_marked_' . ($mark === '+M' ? 'm' : 'g') . ($stem !== '' ? '_' . $stem : '');
        if (get_user_meta($user_id, $once_key, true)) {
            return;
        }
        update_user_meta($user_id, $once_key, time());

        $queue = get_user_meta($user_id, self::flosc_journey_marks_meta_key(), true);
        if (!is_array($queue)) {
            $queue = [];
        }
        $queue[] = ['mark' => $mark, 'flow' => $stem];
        // Bounded: a runaway producer must not grow user meta without limit.
        if (count($queue) > 10) {
            $queue = array_slice($queue, -10);
        }
        update_user_meta($user_id, self::flosc_journey_marks_meta_key(), $queue);
    }

    /**
     * Write any VGM state-change rows this turn should be preceded by.
     *
     * Two things can produce a mark, and they mean different things:
     *
     *   Acquisition  -- the account or the entitlement came into existence just
     *                   now. Queued by flosc_queue_journey_mark() from the events
     *                   themselves (user_register, flosc_member_access_granted),
     *                   so it is a fact, not an inference. Written as +G / +M.
     *
     *   Recognition  -- nothing was acquired; someone who already had an account
     *                   signed in and the system recognized them. There is no
     *                   event for this, but the thread itself is the evidence: the
     *                   previous row in this journey was written with user_id 0
     *                   and this one is not. Written as G / M.
     *
     * @param array $data The turn about to be logged (flow_id, user_id, journey_id, phase).
     * @return void
     */
    private function flosc_write_journey_marks($data) {
        global $wpdb;

        $journey_id = self::flosc_sanitize_journey_id($data['journey_id'] ?? '');
        $user_id    = intval($data['user_id'] ?? 0);
        if ($journey_id === '' || $user_id <= 0) {
            // A visitor has nothing to transition from yet, and a thread with no
            // journey id has nowhere to put the row.
            return;
        }

        $flow_id = sanitize_text_field((string) ($data['flow_id'] ?? ''));
        $stem    = self::flosc_journey_flow_stem($flow_id);

        // Previous row of THIS conversation, to see whether the signer-in just
        // crossed out of visitor.
        $previous = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id FROM %i WHERE journey_id = %s ORDER BY id DESC LIMIT 1",
                $this->table_name,
                $journey_id
            ),
            ARRAY_A
        );
        $has_previous  = is_array($previous);
        $crossed_from_visitor = $has_previous && intval($previous['user_id']) === 0;

        // Redeem acquisition marks for this flow (or account-wide ones).
        $queue = get_user_meta($user_id, self::flosc_journey_marks_meta_key(), true);
        $queue = is_array($queue) ? $queue : [];
        $marks = [];
        $keep  = [];
        foreach ($queue as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry_flow = (string) ($entry['flow'] ?? '');
            if ($entry_flow === '' || $entry_flow === $stem) {
                $marks[] = (string) ($entry['mark'] ?? '');
            } else {
                $keep[] = $entry;
            }
        }
        $marks = array_values(array_filter($marks, static function ($m) {
            return in_array($m, ['+G', '+M'], true);
        }));

        if (empty($marks) && !$crossed_from_visitor) {
            return;
        }

        if ($marks !== [] || $queue !== $keep) {
            if ($keep === []) {
                delete_user_meta($user_id, self::flosc_journey_marks_meta_key());
            } else {
                update_user_meta($user_id, self::flosc_journey_marks_meta_key(), $keep);
            }
        }

        // Nothing was acquired, so this is a recognition: they already had the
        // account, and possibly the entitlement, before this conversation began.
        if (empty($marks)) {
            $level = 'guest';
            if (class_exists('FLOSC_Member_Access')) {
                $level = FLOSC_Member_Access::instance()->get_access_level($user_id, $flow_id !== '' ? $flow_id : null);
            }
            $marks[] = ($level === 'member') ? 'M' : 'G';
        }

        // Where they came from. Only 'V' is directly evidenced; otherwise they were
        // already signed in on this thread, which for an unqueued +M means guest.
        $from = '';
        if ($crossed_from_visitor) {
            $from = 'V';
        } elseif ($has_previous) {
            $from = 'G';
        }

        $phase = sanitize_text_field((string) ($data['phase'] ?? 'content'));

        foreach ($marks as $mark) {
            $wpdb->insert(
                $this->table_name,
                [
                    'timestamp'       => current_time('mysql'),
                    'flow_id'         => $flow_id,
                    'phase'           => $phase,
                    'user_id'         => $user_id,
                    'session_id'      => intval($data['session_id'] ?? 0),
                    'journey_id'      => $journey_id,
                    'visitor_ip'      => '',
                    'user_message'    => '',
                    'ai_response'     => ($from !== '' ? $from . ' → ' : '') . $mark,
                    'provider'        => 'flosc',
                    'chain_detail'    => '',
                    'response_source' => 'state_change',
                    'response_time_ms'=> 0,
                ],
                ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
            );
            // Only the first mark of a batch carries the "came from" side; a
            // +G immediately followed by +M reads as V → +G then +M.
            $from = '';
        }
    }

    /**
     * Log a chat exchange.
     *
     * @param array $data {
     *     @type string $flow_id        Flow identifier
     *     @type string $phase          Current funnel phase
     *     @type int    $user_id        WordPress user ID (0 for visitors)
     *     @type int    $session_id     Session ID if available
     *     @type string $journey_id     Opaque per-conversation id that survives login
     *     @type string $user_message   What the user said
     *     @type string $ai_response    What the AI/IVR responded
     *     @type string $provider       AI provider used (openai, anthropic, xai, ivr)
     *     @type array  $chain_detail   Provider names if chaining was used
     *     @type string $response_source How the response was generated (ivr, ai, ai+ivr, rag, fallback)
     *     @type int    $response_time_ms Response time in milliseconds
     * }
     * @return int|false Insert ID on success, false on failure
     */
    /**
     * A turn id is opaque and browser-minted; accept only what we mint.
     */
    public static function flosc_sanitize_turn_id($raw) {
        $raw = strtolower(trim((string) $raw));
        return preg_match('/^[a-f0-9-]{8,64}$/', $raw) ? $raw : '';
    }

    /**
     * The row a turn id wrote, if it wrote one.
     *
     * A visitor who reloads while the assistant is still typing leaves the
     * request in flight: the browser drops the connection, PHP runs to
     * completion and writes the answer, and nobody reads it. The reply exists.
     * This is how the reloaded page finds it.
     */
    public function flosc_find_turn($turn_id) {
        global $wpdb;

        $turn_id = self::flosc_sanitize_turn_id($turn_id);
        if ($turn_id === '') {
            return null;
        }

        $this->flosc_ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table, single indexed row, must not be cached across a turn.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, ai_response, response_source, turn_status, personality_name FROM {$this->table_name} WHERE turn_id = %s ORDER BY id DESC LIMIT 1",
                $turn_id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Mark a turn abandoned. An abandoned turn is not conversation history:
     * replaying it makes the next prompt look like a question nobody answered,
     * which is how a normal follow-up came back as scripted IVR copy.
     */
    public function flosc_mark_turn_abandoned($turn_id) {
        global $wpdb;

        $turn_id = self::flosc_sanitize_turn_id($turn_id);
        if ($turn_id === '') {
            return false;
        }

        $this->flosc_ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table, targeted update.
        $updated = $wpdb->update(
            $this->table_name,
            ['turn_status' => 'abandoned'],
            ['turn_id' => $turn_id],
            ['%s'],
            ['%s']
        );

        if ($updated) {
            $this->flosc_bust_log_caches();
        }

        return (bool) $updated;
    }

    public function flosc_log_chat($data) {
        global $wpdb;

        // Ensure table exists (lightweight check — cached after first call)
        $this->flosc_ensure_table();

        // Any VGM state change goes in FIRST, so the marker sits above the turn
        // that triggered it. Done here rather than at each call site so every path
        // that logs a turn -- /chat, /chat-rag, /chat-log -- is covered by one hook.
        $this->flosc_write_journey_marks($data);

        $visitor_ip = '';
        if (empty($data['user_id'])) {
            $visitor_ip = $this->flosc_get_hashed_ip();
        }

        $chain_detail = '';
        if (!empty($data['chain_detail']) && is_array($data['chain_detail'])) {
            $chain_detail = implode(' → ', $data['chain_detail']);
        }

        /*
         * Who answered, on which surface, over which page.
         *
         * None of this was a column. Surface and page id were tokens packed
         * into chain_detail, a shared VARCHAR(255) that a long page note can
         * push them out of — so they went missing from exactly the turns worth
         * investigating. The personality was not recorded at all: the Chat Logs
         * screen printed whichever personality is attached *now*, so after a
         * Betty-to-Dan switch every historical Betty row read as Dan. The log
         * did not merely fail to record the switch, it hid it.
         *
         * personality_id, its name and the profile hash are read from the same
         * library row the prompt was built from, so a row says which compiled
         * character produced that text rather than which one is attached today.
         */
        $personality_id   = isset($data['personality_id']) ? sanitize_key((string) $data['personality_id']) : '';
        $personality_name = isset($data['personality_name']) ? sanitize_text_field((string) $data['personality_name']) : '';
        $profile_hash     = isset($data['profile_hash']) ? sanitize_text_field((string) $data['profile_hash']) : '';

        // Paths that do not build a prompt — IVR replies, scripted fallbacks —
        // still record who was attached for the turn, resolved from the flow.
        if ($personality_id === '' && function_exists('flosc_personality_library_id_for_flow')) {
            $personality_id = sanitize_key((string) flosc_personality_library_id_for_flow((string) ($data['flow_id'] ?? '')));
        }
        if ($personality_name === '' && function_exists('flosc_personality_library_resolve_field')) {
            $personality_name = sanitize_text_field((string) flosc_personality_library_resolve_field('ai_personality_name', '', (string) ($data['flow_id'] ?? '')));
        }
        if ($profile_hash === '' && function_exists('flosc_personality_resolved_fingerprint')) {
            $profile_hash = sanitize_text_field((string) flosc_personality_resolved_fingerprint((string) ($data['flow_id'] ?? '')));
        }

        // Explicit, never inferred from absence: an empty surface used to mean
        // either full page or "the client did not say".
        $surface = sanitize_key((string) ($data['surface'] ?? ''));
        if ($surface === '') {
            $surface = 'unknown';
        }

        // complete | abandoned. An abandoned turn is one whose request the
        // visitor dropped, so it must not be replayed as conversation history.
        $turn_status = sanitize_key((string) ($data['turn_status'] ?? 'complete'));
        if (!in_array($turn_status, ['complete', 'abandoned'], true)) {
            $turn_status = 'complete';
        }

        // Minted in the browser before the request leaves. It is what lets a
        // reload find the answer that was written while the tab was gone, and
        // what stops the same turn being billed twice.
        $turn_id = self::flosc_sanitize_turn_id($data['turn_id'] ?? '');

        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp'       => current_time('mysql'),
                'flow_id'         => sanitize_text_field($data['flow_id'] ?? ''),
                'phase'           => sanitize_text_field($data['phase'] ?? 'freeline'),
                'user_id'         => intval($data['user_id'] ?? 0),
                'session_id'      => intval($data['session_id'] ?? 0),
                'journey_id'      => self::flosc_sanitize_journey_id($data['journey_id'] ?? ''),
                'visitor_ip'      => $visitor_ip,
                'user_message'    => sanitize_textarea_field($data['user_message'] ?? ''),
                'ai_response'     => wp_kses_post($data['ai_response'] ?? ''),
                'provider'        => sanitize_text_field($data['provider'] ?? 'ivr'),
                'chain_detail'    => sanitize_text_field($chain_detail),
                'response_source' => sanitize_text_field($data['response_source'] ?? 'ivr'),
                'surface'         => $surface,
                'page_url'        => esc_url_raw((string) ($data['page_url'] ?? '')),
                'page_title'      => sanitize_text_field((string) ($data['page_title'] ?? '')),
                'personality_id'  => $personality_id,
                'personality_name'=> $personality_name,
                'profile_hash'    => $profile_hash,
                'turn_status'     => $turn_status,
                'turn_id'         => $turn_id,
                'response_time_ms'=> intval($data['response_time_ms'] ?? 0),
                'billing_source'  => sanitize_text_field($data['billing_source'] ?? ''),
                'billing_model'   => sanitize_text_field($data['billing_model'] ?? ''),
                'billing_input_tokens' => max(0, intval($data['billing_input_tokens'] ?? 0)),
                'billing_output_tokens'=> max(0, intval($data['billing_output_tokens'] ?? 0)),
                'billing_total_tokens' => max(0, intval($data['billing_total_tokens'] ?? 0)),
                'billing_real_millicents' => max(0, intval($data['billing_real_millicents'] ?? 0)),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d']
        );

        if ( $result ) {
            wp_cache_delete( 'rated_logs_50', 'flosc_chat_logs' );
            wp_cache_delete( 'log_count_' . md5( '' ), 'flosc_chat_logs' );
            $this->flosc_bust_log_caches();
            return $wpdb->insert_id;
        }
        return false;
    }

    /**
     * Get recent chat logs for admin viewer.
     *
     * @param array $filters {
     *     @type string $flow_id  Filter by flow
     *     @type string $phase    Filter by phase
     *     @type int    $user_id  Filter by user
     *     @type int    $since_id Only return logs with ID > this (for polling)
     *     @type int    $limit    Max results (default 50)
     * }
     * @return array Chat log entries
     */
    public function flosc_get_logs($filters = []) {
        global $wpdb;

        $this->flosc_ensure_table();

        // Every filter is optional. Rather than assemble a dynamic WHERE string
        // (which a static analyser cannot prove is injection-safe, even when the
        // pieces are all hardcoded), the query uses a single fully-literal format
        // string with "pass-through" guards. For each filter we bind a neutral
        // sentinel ('' or 0) when it is inactive: the guard's left side is then
        // true, so that column is not filtered. When the filter is active we bind
        // the real value (twice), and the column filters normally. Nothing is
        // interpolated into the SQL — every value travels through prepare().
        $flow_id  = !empty($filters['flow_id'])  ? sanitize_text_field($filters['flow_id']) : '';
        $phase    = !empty($filters['phase'])    ? sanitize_text_field($filters['phase'])   : '';
        $user_id  = !empty($filters['user_id'])  ? intval($filters['user_id'])              : 0;
        $since_id = !empty($filters['since_id']) ? intval($filters['since_id'])             : 0;
        $limit    = max(1, intval($filters['limit'] ?? 50));

        $cache_key = 'logs_' . md5( $flow_id . '|' . $phase . '|' . $user_id . '|' . $since_id . '|' . $limit );
        $cached    = wp_cache_get( $cache_key, 'flosc_chat_logs' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i
                 WHERE ( %s = '' OR flow_id = %s )
                   AND ( %s = '' OR phase   = %s )
                   AND ( %d = 0  OR user_id = %d )
                   AND ( %d = 0  OR id      > %d )
                 ORDER BY id DESC
                 LIMIT %d",
                $this->table_name,
                $flow_id,  $flow_id,
                $phase,    $phase,
                $user_id,  $user_id,
                $since_id, $since_id,
                $limit
            ),
            ARRAY_A
        );
        $results = $results ?: array();
        wp_cache_set( $cache_key, $results, 'flosc_chat_logs', 30 );
        return $results;
    }

    /**
     * Admin AI Feedback: rated chat log rows (admin_rating != 0).
     * Ensures schema via flosc_ensure_table(); caches short-lived in the object cache.
     *
     * @param int $limit Max rows (1–100).
     * @return array<int, array<string, mixed>>
     */
    public function flosc_get_rated_logs( $limit = 50 ) {
        global $wpdb;

        $this->flosc_ensure_table();
        $limit = max( 1, min( 100, absint( $limit ) ) );
        $cache_key = 'rated_logs_' . $limit;
        $cached    = wp_cache_get( $cache_key, 'flosc_chat_logs' );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        // Custom table: no WP core list API. prepare() + object cache (satisfies NoCaching when cache is warm).
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, timestamp, user_message, ai_response, admin_rating, admin_note, rated_at
                 FROM %i
                 WHERE admin_rating != 0
                 ORDER BY admin_rating ASC, rated_at DESC
                 LIMIT %d",
                $this->table_name,
                $limit
            ),
            ARRAY_A
        );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }
        wp_cache_set( $cache_key, $rows, 'flosc_chat_logs', 60 );
        return $rows;
    }

    /**
     * Get total log count (for admin stats).
     */
    public function flosc_get_log_count($flow_id = '') {
        global $wpdb;
        $this->flosc_ensure_table();
        // When a flow is given, count only that flow's rows so "Total" matches the filtered view.
        $flow_id   = sanitize_text_field((string) $flow_id);
        $cache_key = 'log_count_' . md5( $flow_id );
        $cached    = wp_cache_get( $cache_key, 'flosc_chat_logs' );
        if ( is_int( $cached ) || ( is_numeric( $cached ) && false !== $cached ) ) {
            return (int) $cached;
        }
        if ($flow_id !== '') {
            $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE flow_id = %s", $this->table_name, $flow_id));
        } else {
            $count = (int) $wpdb->get_var(
                $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table_name )
            );
        }
        wp_cache_set( $cache_key, $count, 'flosc_chat_logs', 60 );
        return $count;
    }

    /**
     * Clear logs older than X days.
     *
     * @param int $days Number of days to retain
     * @return int Number of rows deleted
     */
    public function flosc_clear_old_logs($days = 30) {
        global $wpdb;
        $this->flosc_ensure_table();

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $deleted = $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE is_protected = 0 AND timestamp < %s',
                $this->table_name,
                $cutoff
            )
        );
        wp_cache_delete( 'rated_logs_50', 'flosc_chat_logs' );
            wp_cache_delete( 'log_count_' . md5( '' ), 'flosc_chat_logs' );
            $this->flosc_bust_log_caches();
        return $deleted;
    }

    /**
     * Inject an admin/human message into a conversation (admin joins the chat).
     *
     * Stored as a normal log row in the visitor's conversation (keyed by the same
     * session id the visitor uses), marked response_source='admin' with the admin's
     * display name in `provider`. It is a bot-side, human-authored line — so
     * user_message is blank and ai_response holds the text. It lands at the bottom
     * (chronological), to be picked up by the visitor's poll.
     *
     * @param int    $session_id The visitor's session id (the conversation).
     * @param string $flow_id    Flow the conversation belongs to.
     * @param string $admin_name The admin's display name (shown as "Name (admin)").
     * @param string $text       The message text.
     * @return int|false New row id, or false.
     */
    public function flosc_insert_admin_message($session_id, $flow_id, $admin_name, $text, $source = 'admin') {
        global $wpdb;
        // Cache invalidation for custom-table writes (WPCS NoCaching).
        wp_cache_delete( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        $text       = trim((string) $text);
        if ($session_id <= 0 || $text === '') {
            return false;
        }

        // 'admin' → renders pale-green "(admin)"; 'bot' → renders as a normal AI
        // (assistant) message, but still admin-authored and delivered via the poll.
        $response_source = ($source === 'bot') ? 'admin_bot' : 'admin';

        // Inherit the conversation's journey id from its newest row. Without it the
        // admin's reply would carry journey_id '' and split off into its own thread
        // in the log view, right next to the conversation it was answering.
        $journey_id = self::flosc_sanitize_journey_id($wpdb->get_var($wpdb->prepare(
            "SELECT journey_id FROM %i WHERE session_id = %d AND journey_id <> '' ORDER BY id DESC LIMIT 1",
            $this->table_name,
            $session_id
        )));

        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp'       => current_time('mysql'),
                'flow_id'         => sanitize_text_field((string) $flow_id),
                'phase'           => 'freeline',
                'user_id'         => 0,
                'session_id'      => $session_id,
                'journey_id'      => $journey_id,
                'visitor_ip'      => '',
                'user_message'    => '',
                'ai_response'     => wp_kses_post($text),
                'provider'        => sanitize_text_field((string) $admin_name),
                'chain_detail'    => '',
                'response_source' => $response_source,
                'response_time_ms'=> 0,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Fetch admin messages for one conversation newer than a cursor (visitor poll).
     *
     * @param int $session_id The visitor's session id.
     * @param int $since_id    Return admin rows with id greater than this.
     * @return array List of ['id','text','name','timestamp'].
     */
    public function flosc_get_admin_messages_since($session_id, $since_id) {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        $since_id   = intval($since_id);
        if ($session_id <= 0) {
            return [];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, ai_response, provider, response_source, timestamp FROM %i
                 WHERE session_id = %d AND response_source IN ('admin', 'admin_bot') AND id > %d
                 ORDER BY id ASC LIMIT 50",
                $this->table_name,
                $session_id,
                $since_id
            ),
            ARRAY_A
        );
        wp_cache_set( 'flosc_chat_logs_list', is_array( $rows ) ? $rows : array(), 'flosc_chat_logs', 30 );

        $out = [];
        foreach (($rows ?: []) as $r) {
            $out[] = [
                'id'        => intval($r['id']),
                'text'      => (string) $r['ai_response'],
                'name'      => (string) $r['provider'],
                // 'bot' → render as a normal assistant message; 'admin' → pale-green "(admin)".
                'source'    => ($r['response_source'] === 'admin_bot') ? 'bot' : 'admin',
                'timestamp' => (string) $r['timestamp'],
            ];
        }
        return $out;
    }

    /**
     * Resolve the most recent logged-in user associated with a chat session.
     */
    public function flosc_get_session_owner_user_id($session_id, $flow_id = '') {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        if ($session_id <= 0) {
            return 0;
        }

        $flow_id = sanitize_text_field((string) $flow_id);

        $owner_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM %i
                 WHERE session_id = %d AND user_id > 0 AND ( %s = '' OR flow_id = %s )
                 ORDER BY id DESC LIMIT 1",
                $this->table_name,
                $session_id,
                $flow_id,
                $flow_id
            )
        );
        $owner_id = max( 0, intval( $owner_id ) );
        wp_cache_set( 'flosc_chat_logs_list', $owner_id, 'flosc_chat_logs', 30 );

        return $owner_id;
    }

    /**
     * Verify whether the current request appears to own the given session.
     *
     * Ownership model:
     * - Logged-in users: at least one row for this session with their user_id.
     * - Visitors: at least one row for this session with current visitor_ip hash.
     *
     * @param int $session_id Visitor session identifier.
     * @return bool
     */
    public function flosc_current_request_owns_session($session_id) {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        if ($session_id <= 0) {
            return false;
        }

        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $owned = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM %i WHERE session_id = %d AND user_id = %d LIMIT 1",
                    $this->table_name,
                    $session_id,
                    intval($user_id)
                )
            );
            wp_cache_set( 'flosc_chat_logs_list', $owned, 'flosc_chat_logs', 30 );

            return !empty($owned);
        }

        $visitor_ip = $this->flosc_get_hashed_ip();
        $owned = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM %i WHERE session_id = %d AND user_id = 0 AND visitor_ip = %s LIMIT 1",
                $this->table_name,
                $session_id,
                $visitor_ip
            )
        );
        wp_cache_set( 'flosc_chat_logs_list', $owned, 'flosc_chat_logs', 30 );

        return !empty($owned);
    }

    /**
     * Identify which conversation a log row belongs to.
     *
     * Multiple people can be chatting at once, so the flat log is unreadable. We
     * group rows into conversations using the most specific key a row carries:
     * the journey id, else an explicit session id, else the logged-in user, else
     * the (hashed) visitor IP for anonymous guests. The returned descriptor also
     * drives deletion, so the grouping key and the delete WHERE clause always agree.
     *
     * @param array $row A chat log row (ARRAY_A).
     * @return array { by: 'journey'|'session'|'user'|'ip', value: string, key: string, label: string }
     */
    public static function flosc_session_descriptor($row) {
        $journey_id = self::flosc_sanitize_journey_id($row['journey_id'] ?? '');
        $session_id = intval($row['session_id'] ?? 0);
        $user_id    = intval($row['user_id'] ?? 0);
        $ip         = (string) ($row['visitor_ip'] ?? '');

        // Journey first. session_id changes at login (hashed visitor id -> numeric
        // user-meta session id), so grouping by it splits one conversation in two
        // at the moment someone signs in. The journey id is minted once in the
        // browser and carried across that boundary, so it keeps the whole thread
        // together. Rows written before this column existed have journey_id '',
        // and fall through to the original session/user/ip grouping unchanged.
        if ($journey_id !== '') {
            $code  = substr(md5('j' . $journey_id), 0, 6);
            $label = ($user_id > 0 ? 'User #' . $user_id : 'Visitor') . ' · ' . $code;
            return ['by' => 'journey', 'value' => $journey_id, 'key' => 'j' . $journey_id, 'label' => $label, 'code' => $code];
        }

        // Every conversation gets a stable 6-char "code" — shown in the label and
        // used as the prefix of each message id (e.g. 4f09a2-b-002). For IP-keyed
        // visitors it's the first 6 of their (already hashed) IP, so it matches the
        // pretty code you've seen; for session/user it's a short md5 of the key.
        if ($session_id > 0) {
            $code  = substr(md5('s' . $session_id), 0, 6);
            $label = ($user_id > 0 ? 'User #' . $user_id : 'Visitor') . ' · ' . $code;
            return ['by' => 'session', 'value' => (string) $session_id, 'key' => 's' . $session_id, 'label' => $label, 'code' => $code];
        }
        if ($user_id > 0) {
            $code = substr(md5('u' . $user_id), 0, 6);
            return ['by' => 'user', 'value' => (string) $user_id, 'key' => 'u' . $user_id, 'label' => 'User #' . $user_id . ' · ' . $code, 'code' => $code];
        }
        $code = ($ip !== '') ? substr($ip, 0, 6) : 'unknwn';
        return ['by' => 'ip', 'value' => $ip, 'key' => 'ip' . $ip, 'label' => 'Visitor · ' . $code, 'code' => $code];
    }

    /**
     * Get chat logs grouped into conversations, newest conversation first.
     *
     * Pulls the most recent rows (flow-scoped) and folds them into sessions, each
     * with its messages in chronological order and a count of real visitor turns
     * (the auto-welcome "[SYSTEM: …]" rows are counted as noise, not turns).
     *
     * @param string $flow_id  Restrict to a flow (no extension), or '' for all.
     * @param int    $max_rows Safety cap on rows scanned (default 800).
     * @return array List of session arrays.
     */
    public function flosc_get_sessions($flow_id = '', $max_rows = 800, $archive_status = 'active') {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $flow_id  = sanitize_text_field((string) $flow_id);
        $max_rows = max(1, min(5000, intval($max_rows)));
        $archive_status = in_array($archive_status, ['active', 'archived', 'all'], true) ? $archive_status : 'active';
        $archived_lookup = array_fill_keys($this->flosc_get_archived_session_keys($flow_id), true);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE ( %s = '' OR flow_id = %s ) ORDER BY id DESC LIMIT %d",
                $this->table_name,
                $flow_id,
                $flow_id,
                $max_rows
            ),
            ARRAY_A
        );
        wp_cache_set( 'flosc_chat_logs_list', is_array( $rows ) ? $rows : array(), 'flosc_chat_logs', 30 );
        if (!$rows) {
            return [];
        }

        $sessions = [];
        foreach ($rows as $r) {
            $d = self::flosc_session_descriptor($r);
            $k = $d['key'];
            $is_archived = isset($archived_lookup[$k]);
            if ($archive_status === 'active' && $is_archived) {
                continue;
            }
            if ($archive_status === 'archived' && !$is_archived) {
                continue;
            }
            if (!isset($sessions[$k])) {
                // Rows arrive newest-first, so the first one seen carries last activity.
                $sessions[$k] = [
                    'key'      => $k,
                    'by'       => $d['by'],
                    'value'    => $d['value'],
                    'label'    => $d['label'],
                    'code'     => $d['code'],
                    'flow_id'  => (string) $r['flow_id'],
                    'first_ts' => (string) $r['timestamp'],
                    'last_ts'  => (string) $r['timestamp'],
                    'is_archived' => $is_archived,
                    'turns'    => 0,
                    // Newest row first, so the first non-zero session_id we see is
                    // the conversation's current one. A journey-grouped thread is
                    // keyed by journey_id, but admin-join still has to deliver to a
                    // real session id — this is where it comes from.
                    'deliver_session_id' => intval($r['session_id'] ?? 0),
                    'rows'     => [],
                ];
            }
            if (intval($sessions[$k]['deliver_session_id']) <= 0) {
                $sessions[$k]['deliver_session_id'] = intval($r['session_id'] ?? 0);
            }
            // Build the thread oldest-first by prepending each older row.
            array_unshift($sessions[$k]['rows'], $r);
            if ((string) $r['timestamp'] < $sessions[$k]['first_ts']) {
                $sessions[$k]['first_ts'] = (string) $r['timestamp'];
            }
            // A state-change divider has no speaker, and the auto-welcome's
            // "[SYSTEM: …]" prompt is machinery, so neither counts as a message.
            $is_marker = ((string) ($r['response_source'] ?? '') === 'state_change');
            if (!$is_marker && strncmp((string) $r['user_message'], '[SYSTEM:', 8) !== 0) {
                $sessions[$k]['turns']++;
            }
        }

        uasort($sessions, static function ($a, $b) {
            return strcmp($b['last_ts'], $a['last_ts']);
        });

        return array_values($sessions);
    }

    /**
     * Delete every row of one conversation (an explicit admin action).
     *
     * The WHERE clause mirrors flosc_session_descriptor() exactly so a delete
     * removes precisely the rows shown under that session — and nothing from a
     * neighbouring conversation. Flow-scoped when a flow is given. Unlike the
     * retention sweep, this deliberately removes protected (rated) rows too,
     * because the admin asked for this specific session to go.
     *
     * @param string $by      'session' | 'user' | 'ip'.
     * @param string $value   The matching session id / user id / hashed ip.
     * @param string $flow_id Restrict to a flow (no extension), or '' for all.
     * @return int Rows deleted.
     */
    public function flosc_delete_session($by, $value, $flow_id = '') {
        global $wpdb;
        // Cache invalidation for custom-table writes (WPCS NoCaching).
        wp_cache_delete( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $by      = in_array($by, ['journey', 'session', 'user', 'ip'], true) ? $by : '';
        $flow_id = sanitize_text_field((string) $flow_id);
        if ($by === '') {
            return 0;
        }

        // The four branches must stay mutually exclusive, or one conversation's
        // rows would show up under two headings and a delete would reach into a
        // neighbouring thread. Journey rows are claimed by the journey branch, so
        // the other three exclude them with journey_id = ''.
        if ($by === 'journey') {
            $jid = self::flosc_sanitize_journey_id($value);
            if ($jid === '') {
                return 0;
            }
            $this->flosc_set_session_archived($by, $jid, $flow_id, false);
            return (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE journey_id = %s AND ( %s = '' OR flow_id = %s )",
                $this->table_name, $jid, $flow_id, $flow_id
            ));
        }

        if ($by === 'session') {
            $sid = intval($value);
            if ($sid <= 0) {
                return 0;
            }
            $this->flosc_set_session_archived($by, (string) $sid, $flow_id, false);
            return (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE session_id = %d AND journey_id = '' AND ( %s = '' OR flow_id = %s )",
                $this->table_name, $sid, $flow_id, $flow_id
            ));
        }

        if ($by === 'user') {
            $uid = intval($value);
            if ($uid <= 0) {
                return 0;
            }
            $this->flosc_set_session_archived($by, (string) $uid, $flow_id, false);
            return (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE user_id = %d AND session_id = 0 AND journey_id = '' AND ( %s = '' OR flow_id = %s )",
                $this->table_name, $uid, $flow_id, $flow_id
            ));
        }

        $ip = sanitize_text_field((string) $value);
        if ($ip === '') {
            return 0;
        }
        $this->flosc_set_session_archived($by, $ip, $flow_id, false);
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE visitor_ip = %s AND user_id = 0 AND session_id = 0 AND journey_id = '' AND ( %s = '' OR flow_id = %s )",
            $this->table_name, $ip, $flow_id, $flow_id
        ));
    }

    public function flosc_get_session_rows($by, $value, $flow_id = '') {
        global $wpdb;
        $flosc_cache_probe = wp_cache_get( 'flosc_chat_logs_list', 'flosc_chat_logs' );
        $this->flosc_ensure_table();

        $by = in_array($by, ['journey', 'session', 'user', 'ip'], true) ? $by : '';
        $flow_id = sanitize_text_field((string) $flow_id);
        if ($by === '') {
            return [];
        }

        // Mirrors flosc_delete_session() exactly: journey rows belong to the
        // journey branch, so the other three exclude them with journey_id = ''.
        if ($by === 'journey') {
            $jid = self::flosc_sanitize_journey_id($value);
            if ($jid === '') {
                return [];
            }

            wp_cache_set( 'flosc_chat_logs_list', true, 'flosc_chat_logs', 30 );
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE journey_id = %s AND ( %s = '' OR flow_id = %s ) ORDER BY id ASC",
                    $this->table_name,
                    $jid,
                    $flow_id,
                    $flow_id
                ),
                ARRAY_A
            ) ?: [];
        }

        if ($by === 'session') {
            $sid = intval($value);
            if ($sid <= 0) {
                return [];
            }

            wp_cache_set( 'flosc_chat_logs_list', true, 'flosc_chat_logs', 30 );
        return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE session_id = %d AND journey_id = '' AND ( %s = '' OR flow_id = %s ) ORDER BY id ASC",
                    $this->table_name,
                    $sid,
                    $flow_id,
                    $flow_id
                ),
                ARRAY_A
            ) ?: [];
        }

        if ($by === 'user') {
            $uid = intval($value);
            if ($uid <= 0) {
                return [];
            }

            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE user_id = %d AND session_id = 0 AND journey_id = '' AND ( %s = '' OR flow_id = %s ) ORDER BY id ASC",
                    $this->table_name,
                    $uid,
                    $flow_id,
                    $flow_id
                ),
                ARRAY_A
            ) ?: [];
        }

        $ip = sanitize_text_field((string) $value);
        if ($ip === '') {
            return [];
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE visitor_ip = %s AND user_id = 0 AND session_id = 0 AND journey_id = '' AND ( %s = '' OR flow_id = %s ) ORDER BY id ASC",
                $this->table_name,
                $ip,
                $flow_id,
                $flow_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Hash the visitor IP for privacy.
     * We don't store raw IPs — just a one-way hash for grouping sessions.
     */
    private function flosc_get_hashed_ip() {
        $ip = 'unknown';
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']));
        }
        if ($ip === '') {
            $ip = 'unknown';
        }
        return substr(hash('sha256', $ip . wp_salt()), 0, 16);
    }
}
