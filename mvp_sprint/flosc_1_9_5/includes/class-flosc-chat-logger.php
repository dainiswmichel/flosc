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
     * Create the chat logs table if it doesn't exist.
     * Called on plugin activation and on first use.
     */
    public function flosc_ensure_table() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") === $this->table_name) {
            // v1.9.5: Ensure rating columns exist on existing tables
            $this->flosc_upgrade_table();
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
     * v1.9.5: Upgrade table — add rating columns if they don't exist.
     * Called alongside flosc_ensure_table() on activation.
     */
    public function flosc_upgrade_table() {
        global $wpdb;

        // Check if the admin_rating column already exists
        $col = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'admin_rating'");
        if (!empty($col)) {
            return; // Already upgraded
        }

        $wpdb->query("ALTER TABLE {$this->table_name}
            ADD COLUMN admin_rating TINYINT NOT NULL DEFAULT 0,
            ADD COLUMN admin_note TEXT DEFAULT NULL,
            ADD COLUMN rated_at DATETIME DEFAULT NULL,
            ADD COLUMN rated_by BIGINT UNSIGNED DEFAULT NULL,
            ADD COLUMN is_protected TINYINT(1) NOT NULL DEFAULT 0
        ");
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

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['flow_id'])) {
            $where[] = 'flow_id = %s';
            $params[] = $filters['flow_id'];
        }

        if (!empty($filters['phase'])) {
            $where[] = 'phase = %s';
            $params[] = $filters['phase'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = intval($filters['user_id']);
        }

        if (!empty($filters['since_id'])) {
            $where[] = 'id > %d';
            $params[] = intval($filters['since_id']);
        }

        $limit = intval($filters['limit'] ?? 50);
        $where_sql = implode(' AND ', $where);

        $query = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY id DESC LIMIT %d";
        $params[] = $limit;

        if (count($params) > 1) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);
        } else {
            $results = $wpdb->get_results($wpdb->prepare($query, $limit), ARRAY_A);
        }

        return $results ?: [];
    }

    /**
     * Get total log count (for admin stats).
     */
    public function flosc_get_log_count() {
        global $wpdb;
        $this->flosc_ensure_table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
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
        // v1.9.5: Never delete rated/protected logs
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE is_protected = 0 AND timestamp < %s",
            $cutoff
        ));
    }

    /**
     * Hash the visitor IP for privacy.
     * We don't store raw IPs — just a one-way hash for grouping sessions.
     */
    private function flosc_get_hashed_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return substr(hash('sha256', $ip . wp_salt()), 0, 16);
    }
}
