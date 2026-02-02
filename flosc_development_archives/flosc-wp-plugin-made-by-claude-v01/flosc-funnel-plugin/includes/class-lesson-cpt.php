<?php
/**
 * Lesson Custom Post Type
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Lesson_CPT {
    
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
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_flosc_lesson', array($this, 'save_meta_boxes'));
    }
    
    /**
     * Register lesson post type
     */
    public function register_post_type() {
        $labels = array(
            'name' => __('Lessons', 'flosc-funnel'),
            'singular_name' => __('Lesson', 'flosc-funnel'),
            'add_new' => __('Add New Lesson', 'flosc-funnel'),
            'add_new_item' => __('Add New Lesson', 'flosc-funnel'),
            'edit_item' => __('Edit Lesson', 'flosc-funnel'),
            'new_item' => __('New Lesson', 'flosc-funnel'),
            'view_item' => __('View Lesson', 'flosc-funnel'),
            'search_items' => __('Search Lessons', 'flosc-funnel'),
            'not_found' => __('No lessons found', 'flosc-funnel'),
            'not_found_in_trash' => __('No lessons found in trash', 'flosc-funnel')
        );
        
        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => false,
            'show_in_menu' => 'flosc-funnel',
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'rewrite' => array('slug' => 'lesson'),
            'show_in_rest' => true,
            'capability_type' => 'post'
        );
        
        register_post_type('flosc_lesson', $args);
    }
    
    /**
     * Register phoneme taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name' => __('Phoneme Groups', 'flosc-funnel'),
            'singular_name' => __('Phoneme Group', 'flosc-funnel'),
            'search_items' => __('Search Phoneme Groups', 'flosc-funnel'),
            'all_items' => __('All Phoneme Groups', 'flosc-funnel'),
            'edit_item' => __('Edit Phoneme Group', 'flosc-funnel'),
            'update_item' => __('Update Phoneme Group', 'flosc-funnel'),
            'add_new_item' => __('Add New Phoneme Group', 'flosc-funnel'),
            'new_item_name' => __('New Phoneme Group', 'flosc-funnel')
        );
        
        register_taxonomy('flosc_phoneme', 'flosc_lesson', array(
            'labels' => $labels,
            'hierarchical' => true,
            'show_in_menu' => true,
            'show_admin_column' => true,
            'rewrite' => array('slug' => 'phoneme'),
            'show_in_rest' => true
        ));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'flosc_lesson_settings',
            __('Lesson Settings', 'flosc-funnel'),
            array($this, 'render_meta_box'),
            'flosc_lesson',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box($post) {
        wp_nonce_field('flosc_lesson_meta', 'flosc_lesson_nonce');
        
        $phoneme_group = get_post_meta($post->ID, '_flosc_phoneme_group', true);
        $is_free = get_post_meta($post->ID, '_flosc_is_free', true);
        $lesson_order = get_post_meta($post->ID, '_flosc_lesson_order', true);
        
        ?>
        <p>
            <label for="flosc_phoneme_group"><strong><?php _e('Phoneme Group:', 'flosc-funnel'); ?></strong></label><br>
            <select name="flosc_phoneme_group" id="flosc_phoneme_group" class="widefat">
                <option value=""><?php _e('Select phoneme...', 'flosc-funnel'); ?></option>
                <?php
                $phonemes = array(
                    '/æ/' => '/æ/ (cat, bat)',
                    '/ɪ/' => '/ɪ/ (sit, bit)',
                    '/θ/' => '/θ/ (think, path)',
                    '/ð/' => '/ð/ (this, that)',
                    '/ʃ/' => '/ʃ/ (ship, cash)',
                    '/r/' => '/r/ (red, very)',
                    '/l/' => '/l/ (let, fill)',
                    '/ɛ/' => '/ɛ/ (bed, red)',
                    '/i/' => '/i/ (see, tree)',
                    '/aɪ/' => '/aɪ/ (my, try)',
                    '/aʊ/' => '/aʊ/ (how, now)',
                    '/oʊ/' => '/oʊ/ (go, no)'
                );
                foreach ($phonemes as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '"' . selected($phoneme_group, $key, false) . '>' . esc_html($label) . '</option>';
                }
                ?>
            </select>
        </p>
        
        <p>
            <label>
                <input type="checkbox" name="flosc_is_free" value="1" <?php checked($is_free, '1'); ?>>
                <strong><?php _e('Free Lesson', 'flosc-funnel'); ?></strong>
            </label><br>
            <small><?php _e('Check if this lesson should be available without payment', 'flosc-funnel'); ?></small>
        </p>
        
        <p>
            <label for="flosc_lesson_order"><strong><?php _e('Display Order:', 'flosc-funnel'); ?></strong></label><br>
            <input type="number" name="flosc_lesson_order" id="flosc_lesson_order" value="<?php echo esc_attr($lesson_order); ?>" class="small-text">
            <br><small><?php _e('Lower numbers appear first', 'flosc-funnel'); ?></small>
        </p>
        <?php
    }
    
    /**
     * Save meta boxes
     */
    public function save_meta_boxes($post_id) {
        // Verify nonce
        if (!isset($_POST['flosc_lesson_nonce']) || !wp_verify_nonce($_POST['flosc_lesson_nonce'], 'flosc_lesson_meta')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save phoneme group
        if (isset($_POST['flosc_phoneme_group'])) {
            update_post_meta($post_id, '_flosc_phoneme_group', sanitize_text_field($_POST['flosc_phoneme_group']));
        }
        
        // Save free status
        $is_free = isset($_POST['flosc_is_free']) ? '1' : '0';
        update_post_meta($post_id, '_flosc_is_free', $is_free);
        
        // Save lesson order
        if (isset($_POST['flosc_lesson_order'])) {
            update_post_meta($post_id, '_flosc_lesson_order', intval($_POST['flosc_lesson_order']));
        }
    }
}
