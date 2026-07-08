<?php
/**
 * FLOSC LESAEP Lessons Table
 * 
 * Custom table for structured pronunciation lesson data.
 * Stores the 55 LESAEP lessons with discrete, queryable fields.
 * Word sequences and IPA transcriptions stored as ordered JSON arrays.
 *
 * @package FLOSC
 * @since 1.9.7
 * @date 2026-02m-24d
 */

if (!defined('ABSPATH')) exit;

class FLOSC_Lesaep_Lessons_Table {

    private static $instance = null;
    private $table_name;
    private $db_version = '1.0.0';
    private $db_version_option = 'flosc_lesaep_lessons_db_version';

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'flosc_lessons';
    }

    /**
     * Get the full table name (with prefix)
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Create or update the lessons table via dbDelta
     */
    public function ensure_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Use utf8mb4 explicitly for IPA characters
        if (empty($charset_collate)) {
            $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        $sql = "CREATE TABLE {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_number VARCHAR(10) NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            session_title TEXT NOT NULL,
            session_short_description TEXT NOT NULL,
            words_to_record TEXT NOT NULL,
            words_to_record_ipa TEXT NOT NULL,
            how_to LONGTEXT NOT NULL,
            note TEXT DEFAULT NULL,
            video_url VARCHAR(2083) DEFAULT NULL,
            ipa_symbol VARCHAR(20) DEFAULT NULL,
            sound_category VARCHAR(50) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_lesson_number (lesson_number),
            KEY idx_sort_order (sort_order),
            KEY idx_sound_category (sound_category)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option($this->db_version_option, $this->db_version);

        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log('[FLOSC LESAEP] Lessons table ensured: ' . $this->table_name);
        }
    }

    /**
     * Check if the table exists
     */
    public function table_exists() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only existence check
        $result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->table_name
            )
        );
        return ($result === $this->table_name);
    }

    /**
     * Get lesson count
     */
    public function get_count() {
        global $wpdb;
        if (!$this->table_exists()) return 0;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only count on plugin-owned table
        return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table_name )
        );
    }

    /**
     * Insert a single lesson
     *
     * @param array $data Associative array with lesson fields
     * @return int|false Inserted ID or false on failure
     */
    public function insert_lesson($data) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional insert into plugin-owned lessons table
        $result = $wpdb->insert(
            $this->table_name,
            [
                'lesson_number'             => $data['lesson_number'],
                'sort_order'                => $data['sort_order'] ?? 0,
                'session_title'             => $data['session_title'],
                'session_short_description' => $data['session_short_description'],
                'words_to_record'           => $data['words_to_record'],
                'words_to_record_ipa'       => $data['words_to_record_ipa'],
                'how_to'                    => $data['how_to'],
                'note'                      => $data['note'] ?? null,
                'video_url'                 => $data['video_url'] ?? null,
                'ipa_symbol'                => $data['ipa_symbol'] ?? null,
                'sound_category'            => $data['sound_category'] ?? null,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return $result !== false ? $wpdb->insert_id : false;
    }

    /**
     * Get all lessons ordered by sort_order
     *
     * @return array
     */
    public function get_all_lessons() {
        global $wpdb;

        if (!$this->table_exists()) return [];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only query on plugin-owned table
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SELECT * FROM %i ORDER BY sort_order ASC',
                $this->table_name
            ),
            ARRAY_A
        );
    }

    /**
     * Get a single lesson by lesson_number (e.g. "1", "20.1")
     *
     * @param string $lesson_number
     * @return array|null
     */
    public function get_lesson_by_number($lesson_number) {
        global $wpdb;

        if (!$this->table_exists()) return null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only query on plugin-owned table
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SELECT * FROM %i WHERE lesson_number = %s',
                $this->table_name,
                $lesson_number
            ),
            ARRAY_A
        );
    }

    /**
     * Get a single lesson by database ID
     *
     * @param int $id
     * @return array|null
     */
    public function get_lesson_by_id($id) {
        global $wpdb;

        if (!$this->table_exists()) return null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only query on plugin-owned table
        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->table_name,
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get lessons by sound category (e.g. "vowel", "consonant")
     *
     * @param string $category
     * @return array
     */
    public function get_lessons_by_category($category) {
        global $wpdb;

        if (!$this->table_exists()) return [];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only query on plugin-owned table
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SELECT * FROM %i WHERE sound_category = %s ORDER BY sort_order ASC',
                $this->table_name,
                $category
            ),
            ARRAY_A
        );
    }

    /**
     * Get lesson metadata only (for listing, without full how_to content)
     *
     * @return array
     */
    public function get_lesson_list() {
        global $wpdb;

        if (!$this->table_exists()) return [];

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only lesson metadata query
        return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare(
                'SELECT id, lesson_number, sort_order, session_title, session_short_description,
                        ipa_symbol, sound_category, video_url
                 FROM %i
                 ORDER BY sort_order ASC',
                $this->table_name
            ),
            ARRAY_A
        );
    }

    /**
     * Clear all lessons (for reimport)
     *
     * @return int|false Number of rows deleted or false on error
     */
    public function truncate() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- admin-triggered reset of plugin-owned lessons table
        return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct query on FLOSC-owned tables/data path where no core API exists
            $wpdb->prepare( 'TRUNCATE TABLE %i', $this->table_name )
        );
    }

    /**
     * Format a lesson row for REST API output
     *
     * @param array $row Raw DB row
     * @param bool $include_content Include how_to and full word lists
     * @return array Formatted lesson
     */
    public function format_for_api($row, $include_content = false) {
        if (!$row) return null;

        $lesson = [
            'id'                => (int) $row['id'],
            'lesson_number'     => $row['lesson_number'],
            'title'             => $row['session_title'],
            'description'       => $row['session_short_description'],
            'ipa_symbol'        => $row['ipa_symbol'],
            'sound_category'    => $row['sound_category'],
            'has_video'         => !empty($row['video_url']),
        ];

        if ($include_content) {
            $lesson['words_to_record']     = $row['words_to_record'];
            $lesson['words_to_record_ipa'] = $row['words_to_record_ipa'];
            $lesson['how_to']              = $row['how_to'];
            $lesson['note']                = $row['note'];
            $lesson['video_url']           = $row['video_url'];
        }

        return $lesson;
    }
}
