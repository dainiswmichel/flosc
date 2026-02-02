<?php
/**
 * Plugin Installer
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Installer {
    
    /**
     * Run on plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_pages();
        self::set_default_options();
        self::create_upload_dirs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Check for FastAPI backend
        self::check_backend();
    }
    
    /**
     * Run on plugin deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Quiz sessions table
        $table_quiz_sessions = $wpdb->prefix . 'flosc_quiz_sessions';
        $sql_quiz_sessions = "CREATE TABLE IF NOT EXISTS $table_quiz_sessions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id varchar(16) NOT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            no_count int(11) DEFAULT 0,
            completed tinyint(1) DEFAULT 0,
            blocked tinyint(1) DEFAULT 0,
            flagged_phonemes text DEFAULT NULL,
            oto_sent_at datetime DEFAULT NULL,
            oto_expires_at datetime DEFAULT NULL,
            is_paid tinyint(1) DEFAULT 0,
            stripe_session_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY quiz_id (quiz_id),
            KEY user_id (user_id),
            KEY email (email)
        ) $charset_collate;";
        
        // Quiz results table
        $table_quiz_results = $wpdb->prefix . 'flosc_quiz_results';
        $sql_quiz_results = "CREATE TABLE IF NOT EXISTS $table_quiz_results (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id varchar(16) NOT NULL,
            sentence_index int(11) NOT NULL,
            audio_path text NOT NULL,
            transcription text DEFAULT NULL,
            accuracy decimal(5,2) DEFAULT NULL,
            flagged_phonemes text DEFAULT NULL,
            missing_words text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_quiz_sessions);
        dbDelta($sql_quiz_results);
    }
    
    /**
     * Create default pages
     */
    private static function create_pages() {
        $pages = array(
            'flosc_freeline_page' => array(
                'title' => 'Free Pronunciation Analysis',
                'content' => '[flosc_chatbot]',
                'slug' => 'pronunciation-analysis'
            ),
            'flosc_offer_page' => array(
                'title' => 'Your Analysis Results',
                'content' => '[flosc_offer]',
                'slug' => 'your-results'
            ),
            'flosc_dashboard_page' => array(
                'title' => 'Course Dashboard',
                'content' => '[flosc_dashboard]',
                'slug' => 'course-dashboard'
            ),
            'flosc_success_page' => array(
                'title' => 'Payment Successful',
                'content' => '[flosc_success]',
                'slug' => 'payment-success'
            )
        );
        
        foreach ($pages as $option_key => $page_data) {
            // Check if page already exists
            $page_id = get_option($option_key);
            
            if (!$page_id || !get_post($page_id)) {
                // Create page
                $page_id = wp_insert_post(array(
                    'post_title' => $page_data['title'],
                    'post_content' => $page_data['content'],
                    'post_name' => $page_data['slug'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'comment_status' => 'closed',
                    'ping_status' => 'closed'
                ));
                
                if ($page_id) {
                    update_option($option_key, $page_id);
                }
            }
        }
    }
    
    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            'flosc_api_url' => 'http://localhost:8000',
            'flosc_max_no_attempts' => 3,
            'flosc_oto_duration_hours' => 24,
            'flosc_product_name' => 'English Pronunciation Mastery',
            'flosc_full_price' => 575,
            'flosc_oto_price' => 144,
            'flosc_oto_discount_percent' => 75,
            'flosc_magic_sentence_1' => 'The cat sat on the mat',
            'flosc_magic_sentence_2' => 'She sells seashells by the seashore',
            'flosc_magic_sentence_3' => 'How now brown cow',
            'flosc_email_from_name' => get_bloginfo('name'),
            'flosc_email_from_email' => get_option('admin_email'),
            'flosc_stripe_mode' => 'test',
            'flosc_stripe_test_publishable_key' => '',
            'flosc_stripe_test_secret_key' => '',
            'flosc_stripe_live_publishable_key' => '',
            'flosc_stripe_live_secret_key' => '',
            'flosc_email_provider' => 'wordpress',
            'flosc_brevo_api_key' => '',
            'flosc_sendgrid_api_key' => ''
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * Create upload directories
     */
    private static function create_upload_dirs() {
        $upload_dir = wp_upload_dir();
        $flosc_dir = $upload_dir['basedir'] . '/flosc-audio';
        
        if (!file_exists($flosc_dir)) {
            wp_mkdir_p($flosc_dir);
            
            // Add .htaccess to protect audio files
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents($flosc_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Check if FastAPI backend is running
     */
    private static function check_backend() {
        $api_url = get_option('flosc_api_url', 'http://localhost:8000');
        
        $response = wp_remote_get($api_url . '/health', array(
            'timeout' => 5
        ));
        
        if (is_wp_error($response)) {
            update_option('flosc_backend_status', 'offline');
            update_option('flosc_backend_error', $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                update_option('flosc_backend_status', 'online');
                delete_option('flosc_backend_error');
            } else {
                update_option('flosc_backend_status', 'error');
                update_option('flosc_backend_error', 'Backend returned status code: ' . $code);
            }
        }
    }
}
