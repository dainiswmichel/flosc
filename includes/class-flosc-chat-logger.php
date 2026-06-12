<?php
/**
 * FLOSC Chat Logger
 * v1.9.0: Logs all chat exchanges for real-time monitoring and later retrieval.
 *
 * Storage: Custom WordPress table {prefix}flosc_chat_logs
 * Access: Admin-only viewer via FLOSC Settings → Chat Logs tab
 */

if (!defined('ABSPATH')) exit;

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
     * Return a conservative SQL-safe table identifier.
     */
    private function get_sql_safe_table_name() {
        return preg_replace('/[^A-Za-z0-9_]/', '', (string) $this->table_name);
    }

    /**
     * Create the chat logs table if it doesn't exist.
     * Called on plugin activation and on first use.
     */
    public function flosc_ensure_table() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only table existence check
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        );

        if ($existing === $this->table_name) {
            // Table already exists. The rating columns are part of the CREATE
            // TABLE schema below, so there is nothing to migrate.
            return true;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            flow_id VARCHAR(100) DEFAULT '',
            phase VARCHAR(50) DEFAULT 'freeline',
            user_id BIGINT UNSIGNED DEFAULT 0,
            session_id BIGINT UNSIGNED DEFAULT 0,
            visitor_ip VARCHAR(45) DEFAULT '',
            user_message TEXT NOT NULL,
            ai_response TEXT NOT NULL,
            provider VARCHAR(50) DEFAULT 'ivr',
            chain_detail VARCHAR(255) DEFAULT '',
            response_source VARCHAR(50) DEFAULT 'ivr',
            response_time_ms INT UNSIGNED DEFAULT 0,
            admin_rating TINYINT NOT NULL DEFAULT 0,
            admin_note TEXT DEFAULT NULL,
            rated_at DATETIME DEFAULT NULL,
            rated_by BIGINT UNSIGNED DEFAULT NULL,
            is_protected TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_flosc_chat_timestamp (timestamp),
            KEY idx_flosc_chat_user (user_id),
            KEY idx_flosc_chat_flow (flow_id),
            KEY idx_flosc_chat_phase (phase)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional admin rating update on plugin-owned table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional admin rating update on plugin-owned table
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

        return $result !== false;
    }

    /**
     * Log a chat exchange.
     *
     * @param array $data {
     *     @type string $flow_id        Flow identifier
     *     @type string $phase          Current funnel phase
     *     @type int    $user_id        WordPress user ID (0 for visitors)
     *     @type int    $session_id     Session ID if available
     *     @type string $user_message   What the user said
     *     @type string $ai_response    What the AI/IVR responded
     *     @type string $provider       AI provider used (openai, anthropic, xai, ivr)
     *     @type array  $chain_detail   Provider names if chaining was used
     *     @type string $response_source How the response was generated (ivr, ai, ai+ivr, rag, fallback)
     *     @type int    $response_time_ms Response time in milliseconds
     * }
     * @return int|false Insert ID on success, false on failure
     */
    public function flosc_log_chat($data) {
        global $wpdb;

        // Ensure table exists (lightweight check — cached after first call)
        $this->flosc_ensure_table();

        $visitor_ip = '';
        if (empty($data['user_id'])) {
            $visitor_ip = $this->flosc_get_hashed_ip();
        }

        $chain_detail = '';
        if (!empty($data['chain_detail']) && is_array($data['chain_detail'])) {
            $chain_detail = implode(' → ', $data['chain_detail']);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional insert into plugin-owned chat log table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional insert into plugin-owned chat log table
        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp'       => current_time('mysql'),
                'flow_id'         => sanitize_text_field($data['flow_id'] ?? ''),
                'phase'           => sanitize_text_field($data['phase'] ?? 'freeline'),
                'user_id'         => intval($data['user_id'] ?? 0),
                'session_id'      => intval($data['session_id'] ?? 0),
                'visitor_ip'      => $visitor_ip,
                'user_message'    => sanitize_textarea_field($data['user_message'] ?? ''),
                'ai_response'     => wp_kses_post($data['ai_response'] ?? ''),
                'provider'        => sanitize_text_field($data['provider'] ?? 'ivr'),
                'chain_detail'    => sanitize_text_field($chain_detail),
                'response_source' => sanitize_text_field($data['response_source'] ?? 'ivr'),
                'response_time_ms'=> intval($data['response_time_ms'] ?? 0),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        return $result ? $wpdb->insert_id : false;
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only log listing query on plugin-owned table
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

        return $results ?: [];
    }

    /**
     * Get total log count (for admin stats).
     */
    public function flosc_get_log_count($flow_id = '') {
        global $wpdb;
        $this->flosc_ensure_table();
        // When a flow is given, count only that flow's rows so "Total" matches the filtered view.
        $flow_id = sanitize_text_field((string) $flow_id);
        if ($flow_id !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only stats count on plugin-owned table
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE flow_id = %s", $this->table_name, $flow_id));
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only stats query on plugin-owned table
        return (int) $wpdb->get_var(
            $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table_name )
        );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared retention cleanup on plugin-owned table
        return $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE is_protected = 0 AND timestamp < %s',
                $this->table_name,
                $cutoff
            )
        );
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
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        $text       = trim((string) $text);
        if ($session_id <= 0 || $text === '') {
            return false;
        }

        // 'admin' → renders pale-green "(admin)"; 'bot' → renders as a normal AI
        // (Brenda) message, but still admin-authored and delivered via the poll.
        $response_source = ($source === 'bot') ? 'admin_bot' : 'admin';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional insert into plugin-owned chat log table
        $result = $wpdb->insert(
            $this->table_name,
            [
                'timestamp'       => current_time('mysql'),
                'flow_id'         => sanitize_text_field((string) $flow_id),
                'phase'           => 'freeline',
                'user_id'         => 0,
                'session_id'      => $session_id,
                'visitor_ip'      => '',
                'user_message'    => '',
                'ai_response'     => wp_kses_post($text),
                'provider'        => sanitize_text_field((string) $admin_name),
                'chain_detail'    => '',
                'response_source' => $response_source,
                'response_time_ms'=> 0,
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
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
        $this->flosc_ensure_table();

        $session_id = intval($session_id);
        $since_id   = intval($since_id);
        if ($session_id <= 0) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only admin-message poll on plugin-owned table
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

        $out = [];
        foreach (($rows ?: []) as $r) {
            $out[] = [
                'id'        => intval($r['id']),
                'text'      => (string) $r['ai_response'],
                'name'      => (string) $r['provider'],
                // 'bot' → render as a normal Brenda message; 'admin' → pale-green "(admin)".
                'source'    => ($r['response_source'] === 'admin_bot') ? 'bot' : 'admin',
                'timestamp' => (string) $r['timestamp'],
            ];
        }
        return $out;
    }

    /**
     * Identify which conversation a log row belongs to.
     *
     * Multiple people can be chatting at once, so the flat log is unreadable. We
     * group rows into conversations using the most specific key a row carries:
     * an explicit session id, else the logged-in user, else the (hashed) visitor
     * IP for anonymous guests. The returned descriptor also drives deletion, so
     * the grouping key and the delete WHERE clause always agree.
     *
     * @param array $row A chat log row (ARRAY_A).
     * @return array { by: 'session'|'user'|'ip', value: string, key: string, label: string }
     */
    public static function flosc_session_descriptor($row) {
        $session_id = intval($row['session_id'] ?? 0);
        $user_id    = intval($row['user_id'] ?? 0);
        $ip         = (string) ($row['visitor_ip'] ?? '');

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
    public function flosc_get_sessions($flow_id = '', $max_rows = 800) {
        global $wpdb;
        $this->flosc_ensure_table();

        $flow_id  = sanitize_text_field((string) $flow_id);
        $max_rows = max(1, min(5000, intval($max_rows)));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only grouped log listing on plugin-owned table
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
        if (!$rows) {
            return [];
        }

        $sessions = [];
        foreach ($rows as $r) {
            $d = self::flosc_session_descriptor($r);
            $k = $d['key'];
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
                    'turns'    => 0,
                    'rows'     => [],
                ];
            }
            // Build the thread oldest-first by prepending each older row.
            array_unshift($sessions[$k]['rows'], $r);
            if ((string) $r['timestamp'] < $sessions[$k]['first_ts']) {
                $sessions[$k]['first_ts'] = (string) $r['timestamp'];
            }
            if (strncmp((string) $r['user_message'], '[SYSTEM:', 8) !== 0) {
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
        $this->flosc_ensure_table();

        $by      = in_array($by, ['session', 'user', 'ip'], true) ? $by : '';
        $flow_id = sanitize_text_field((string) $flow_id);
        if ($by === '') {
            return 0;
        }

        if ($by === 'session') {
            $sid = intval($value);
            if ($sid <= 0) {
                return 0;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit admin session delete on plugin-owned table
            return (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE session_id = %d AND ( %s = '' OR flow_id = %s )",
                $this->table_name, $sid, $flow_id, $flow_id
            ));
        }

        if ($by === 'user') {
            $uid = intval($value);
            if ($uid <= 0) {
                return 0;
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit admin session delete on plugin-owned table
            return (int) $wpdb->query($wpdb->prepare(
                "DELETE FROM %i WHERE user_id = %d AND session_id = 0 AND ( %s = '' OR flow_id = %s )",
                $this->table_name, $uid, $flow_id, $flow_id
            ));
        }

        $ip = sanitize_text_field((string) $value);
        if ($ip === '') {
            return 0;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit admin session delete on plugin-owned table
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM %i WHERE visitor_ip = %s AND user_id = 0 AND session_id = 0 AND ( %s = '' OR flow_id = %s )",
            $this->table_name, $ip, $flow_id, $flow_id
        ));
    }

    /**
     * Hash the visitor IP for privacy.
     * We don't store raw IPs — just a one-way hash for grouping sessions.
     */
    private function flosc_get_hashed_ip() {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR is unslashed and sanitized inline below
        $ip = isset($_SERVER['REMOTE_ADDR']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REMOTE_ADDR is unslashed and sanitized inline below
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : 'unknown';
        return substr(hash('sha256', $ip . wp_salt()), 0, 16);
    }
}
