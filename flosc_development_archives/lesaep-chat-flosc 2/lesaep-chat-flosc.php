<?php
/**
 * Plugin Name: LeSAEp Chat FLOSC
 * Description: Chat-only pronunciation quiz funnel with BuddyBoss/WP gating and optional WooCommerce auto-unlock.
 * Version: 1.0.5
 * Author: LeSAEp
 */

if (!defined('ABSPATH')) { exit; }

class LeSAEp_Chat_FLOSC {
    const VERSION = '1.0.5';
    const OPTION_GROUP = 'lesaep_settings';

    public function __construct() {
        add_shortcode('lesaep_chat', array($this, 'render_chat'));

        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Lesson meta box (posts/pages) for easy marking
        add_action('add_meta_boxes', array($this, 'add_lesson_meta_box'));
        add_action('save_post', array($this, 'save_lesson_meta_box'));

        // Optional WooCommerce auto-unlock
        add_action('plugins_loaded', array($this, 'maybe_enable_woocommerce_hook'));
    }

    /**
     * Render chat interface
     */
    public function render_chat() {
        $user = wp_get_current_user();
        $has_access = $this->user_has_paid_access($user->ID);

        ob_start();
        ?>
        <div id="lesaep-chat-container">
            <div class="lesaep-chat-window">
                <div class="chat-header">
                    <div class="bot-avatar">🎯</div>
                    <div class="bot-info">
                        <h1>LeSAEp Pronunciation Coach</h1>
                        <p class="bot-status">
                            <span class="status-dot"></span>
                            <?php if ($user->ID): ?>
                                <?php echo esc_html($has_access ? 'Full Access' : 'Free Access'); ?>
                            <?php else: ?>
                                Start your free analysis
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="chat-messages" id="chatMessages"></div>

                <div class="chat-input-area">
                    <div class="typing-indicator hidden" id="typingIndicator">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="quick-replies" id="quickReplies"></div>

                    <div class="email-capture hidden" id="emailCapture">
                        <label for="lesaepEmail">Optional: email results to you</label>
                        <div class="email-row">
                            <input type="email" id="lesaepEmail" placeholder="you@example.com" autocomplete="email" />
                            <button type="button" id="emailSaveBtn">Save</button>
                        </div>
                        <p class="email-hint">(This helps if you refresh before logging in.)</p>
                    </div>

                    <div class="recorder-area hidden" id="recorderArea">
                        <div class="sentence-display" id="sentenceDisplay"></div>
                        <button class="record-btn" id="recordBtn" type="button">
                            <span class="record-icon">🎤</span>
                            <span class="record-text">Click to Record</span>
                        </button>
                        <div class="waveform hidden" id="waveform">
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div>
                        </div>
                        <p class="record-status" id="recordStatus"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue assets (safe null checks)
     */
    public function enqueue_assets() {
        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return;
        }
        if (!has_shortcode($post->post_content, 'lesaep_chat')) {
            return;
        }

        wp_enqueue_style(
            'lesaep-chat',
            plugins_url('assets/css/chat.css', __FILE__),
            array(),
            self::VERSION
        );

        wp_enqueue_script(
            'lesaep-chat',
            plugins_url('assets/js/chat.js', __FILE__),
            array(),
            self::VERSION,
            true
        );

        $user = wp_get_current_user();

        $sentences = $this->get_magic_sentences();

        wp_localize_script('lesaep-chat', 'LESAEP', array(
            'apiUrl' => get_option('lesaep_api_url', '/lesaep-api'),
            'wpRestUrl' => rest_url('lesaep/v1'),
            'userId' => $user->ID,
            'isLoggedIn' => is_user_logged_in(),
            'hasPaidAccess' => $this->user_has_paid_access($user->ID),
            'userEmail' => $user->user_email,
            'userName' => $user->display_name,
            'loginUrl' => wp_login_url(get_permalink()),
            'checkoutUrl' => $this->get_checkout_url(),
            'productName' => get_option('lesaep_product_name', 'LeSAEp Course'),
            'price' => intval(get_option('lesaep_price', 144)),
            'currencySymbol' => get_option('lesaep_currency_symbol', '€'),
            'sentences' => $sentences,
            'nonce' => wp_create_nonce('wp_rest'),
        ));
    }

    /**
     * REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('lesaep/v1', '/access', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_access'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('lesaep/v1', '/lessons', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_lessons'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('lesaep/v1', '/lessons/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_single_lesson'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('lesaep/v1', '/mark-paid', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_user_paid'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ));
    }

    public function get_user_access($request) {
        $user = wp_get_current_user();
        if (!$user->ID) {
            return new WP_REST_Response(array(
                'logged_in' => false,
                'has_paid_access' => false
            ));
        }

        return new WP_REST_Response(array(
            'logged_in' => true,
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'has_paid_access' => $this->user_has_paid_access($user->ID)
        ));
    }

    public function get_lessons($request) {
        $user_id = get_current_user_id();
        $has_access = $this->user_has_paid_access($user_id);

        // Optional: phoneme filtering for personalization
        $phonemes_raw = $request->get_param('phonemes');
        $phonemes = array();
        if (is_string($phonemes_raw) && strlen(trim($phonemes_raw)) > 0) {
            $phonemes = array_values(array_filter(array_map('trim', explode(',', $phonemes_raw))));
        }

        $args = array(
            'post_type' => 'post',
            'category_name' => 'lesaep',
            'posts_per_page' => -1,
            'meta_key' => '_lesaep_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC'
        );

        if (!$has_access) {
            $args['meta_query'] = array(
                array(
                    'key' => '_lesaep_is_free',
                    'value' => '1',
                )
            );
        }

        if (!empty($phonemes)) {
            // Add phoneme group filter (AND with free filter if present)
            $phoneme_clause = array(
                'key' => '_lesaep_phoneme_group',
                'value' => $phonemes,
                'compare' => 'IN'
            );

            if (!isset($args['meta_query'])) {
                $args['meta_query'] = array($phoneme_clause);
            } else {
                $args['meta_query'][] = $phoneme_clause;
            }
        }

        $posts = get_posts($args);

        // If phoneme filter produced no results, fall back to non-filtered
        if (empty($posts) && !empty($phonemes)) {
            unset($args['meta_query']);
            if (!$has_access) {
                $args['meta_query'] = array(
                    array(
                        'key' => '_lesaep_is_free',
                        'value' => '1',
                    )
                );
            }
            $posts = get_posts($args);
        }

        $lessons = array_map(function($post) {
            return array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => get_the_excerpt($post),
                'content' => apply_filters('the_content', $post->post_content),
                'is_free' => (get_post_meta($post->ID, '_lesaep_is_free', true) === '1'),
                'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true),
                'permalink' => get_permalink($post)
            );
        }, $posts);

        return new WP_REST_Response($lessons);
    }

    public function get_single_lesson($request) {
        $lesson_id = intval($request->get_param('id'));
        $user_id = get_current_user_id();

        $post = get_post($lesson_id);
        if (!$post) {
            return new WP_Error('not_found', 'Lesson not found', array('status' => 404));
        }

        $is_free = (get_post_meta($post->ID, '_lesaep_is_free', true) === '1');
        $has_access = $is_free || $this->user_has_paid_access($user_id);

        if (!$has_access) {
            return new WP_Error('forbidden', 'Access denied', array('status' => 403));
        }

        return new WP_REST_Response(array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
            'is_free' => $is_free,
            'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true),
        ));
    }

    public function mark_user_paid($request) {
        $user_id = intval($request->get_param('user_id'));
        if (!$user_id) {
            return new WP_Error('invalid', 'User ID required', array('status' => 400));
        }

        $method = $this->grant_paid_access($user_id);

        return new WP_REST_Response(array(
            'success' => true,
            'user_id' => $user_id,
            'method' => $method
        ));
    }

    /**
     * Grant access via BuddyBoss group or user meta.
     */
    private function grant_paid_access($user_id) {
        $group_id = intval(get_option('lesaep_paid_group_id'));

        if ($group_id && function_exists('groups_join_group')) {
            groups_join_group($group_id, $user_id);
            return 'buddyboss_group';
        }

        update_user_meta($user_id, 'lesaep_paid_access', '1');
        return 'user_meta';
    }

    private function user_has_paid_access($user_id) {
        if (!$user_id) return false;

        $group_id = intval(get_option('lesaep_paid_group_id'));
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) return true;
        }

        if (get_user_meta($user_id, 'lesaep_paid_access', true) === '1') return true;

        $user = get_user_by('id', $user_id);
        if ($user && $user->has_cap('lesaep_full_access')) return true;

        return false;
    }

    /**
     * Checkout URL (append return param so chat can refresh state after purchase)
     */
    private function get_checkout_url() {
        $custom_url = get_option('lesaep_checkout_url');
        $url = $custom_url ? $custom_url : home_url('/lesaep/checkout/');

        // Add a return marker (non-breaking) so chat can refresh access on landing
        return add_query_arg(array('lesaep_return' => '1'), $url);
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'LeSAEp Settings',
            'LeSAEp',
            'manage_options',
            'lesaep-settings',
            array($this, 'admin_page'),
            'dashicons-format-chat'
        );
    }

    public function admin_page() {
        if (isset($_POST['lesaep_save'])) {
            check_admin_referer('lesaep_settings_save', 'lesaep_nonce');

            update_option('lesaep_api_url', sanitize_text_field($_POST['api_url'] ?? ''));
            update_option('lesaep_paid_group_id', intval($_POST['paid_group_id'] ?? 0));
            update_option('lesaep_product_name', sanitize_text_field($_POST['product_name'] ?? ''));
            update_option('lesaep_price', intval($_POST['price'] ?? 0));
            update_option('lesaep_currency_symbol', sanitize_text_field($_POST['currency_symbol'] ?? '€'));
            update_option('lesaep_checkout_url', esc_url_raw($_POST['checkout_url'] ?? ''));

            update_option('lesaep_wc_enabled', isset($_POST['wc_enabled']) ? '1' : '0');
            update_option('lesaep_wc_product_id', intval($_POST['wc_product_id'] ?? 0));

            update_option('lesaep_magic_sentences', sanitize_textarea_field($_POST['magic_sentences'] ?? ''));

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $api_url = get_option('lesaep_api_url', '/lesaep-api');
        $group_id = get_option('lesaep_paid_group_id', 0);
        $product_name = get_option('lesaep_product_name', 'LeSAEp Course');
        $price = get_option('lesaep_price', 144);
        $currency_symbol = get_option('lesaep_currency_symbol', '€');
        $checkout_url = get_option('lesaep_checkout_url', '');

        $wc_enabled = (get_option('lesaep_wc_enabled', '0') === '1');
        $wc_product_id = intval(get_option('lesaep_wc_product_id', 0));

        $magic_sentences = get_option('lesaep_magic_sentences', "The cat sat on the mat\nShe sells seashells by the seashore\nHow now brown cow");

        ?>
        <div class="wrap">
            <h1>LeSAEp Chat Settings</h1>

            <form method="post">
                <?php wp_nonce_field('lesaep_settings_save', 'lesaep_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">Backend API URL</th>
                        <td>
                            <input type="text" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text">
                            <p class="description">Your FastAPI base path (e.g., https://api.dainis.net/lesaep-api)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">BuddyBoss Paid Group ID</th>
                        <td>
                            <input type="number" name="paid_group_id" value="<?php echo esc_attr($group_id); ?>">
                            <p class="description">If set, paid users are added to this BuddyBoss group.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Product Name</th>
                        <td>
                            <input type="text" name="product_name" value="<?php echo esc_attr($product_name); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Price</th>
                        <td>
                            <input type="text" name="currency_symbol" value="<?php echo esc_attr($currency_symbol); ?>" style="width:55px;"> 
                            <input type="number" name="price" value="<?php echo esc_attr($price); ?>" style="width:120px;">
                            <p class="description">Currency symbol default is €.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Checkout URL</th>
                        <td>
                            <input type="url" name="checkout_url" value="<?php echo esc_attr($checkout_url); ?>" class="regular-text">
                            <p class="description">Woo / EDD / PMPro / custom checkout page. We append <code>?lesaep_return=1</code> automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Magic Sentences (one per line)</th>
                        <td>
                            <textarea name="magic_sentences" rows="6" class="large-text code"><?php echo esc_textarea($magic_sentences); ?></textarea>
                            <p class="description">These are the 3 (or more) sentences shown in the chat quiz.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">WooCommerce Auto-Unlock</th>
                        <td>
                            <label>
                                <input type="checkbox" name="wc_enabled" <?php checked($wc_enabled); ?>> Enable
                            </label>
                            <p class="description">If enabled, completing an order containing the product ID below grants access automatically.</p>
                            <p>
                                Product ID: <input type="number" name="wc_product_id" value="<?php echo esc_attr($wc_product_id); ?>" style="width:120px;">
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings', 'primary', 'lesaep_save'); ?>
            </form>

            <hr>
            <h2>Content Setup</h2>
            <ol>
                <li>Create WordPress posts in category <code>lesaep</code>.</li>
                <li>Use the LeSAEp Lesson meta box to mark lessons as Free/Paid and set phoneme group + order.</li>
                <li>Create a page containing <code>[lesaep_chat]</code>.</li>
            </ol>

            <h2>Webhook / Manual Access</h2>
            <p>If you use a custom payment system, you can still unlock a user manually by calling:</p>
            <pre>POST <?php echo esc_html(rest_url('lesaep/v1/mark-paid')); ?>
Body: { "user_id": 123 }</pre>
        </div>
        <?php
    }

    /**
     * Magic sentences from options.
     */
    private function get_magic_sentences() {
        $raw = get_option('lesaep_magic_sentences', "The cat sat on the mat\nShe sells seashells by the seashore\nHow now brown cow");
        $lines = preg_split('/\r\n|\r|\n/', trim($raw));
        $lines = array_values(array_filter(array_map('trim', $lines)));
        if (count($lines) < 3) {
            // Enforce at least 3
            $fallback = array('The cat sat on the mat', 'She sells seashells by the seashore', 'How now brown cow');
            $lines = array_merge($lines, array_slice($fallback, 0, 3 - count($lines)));
        }
        return $lines;
    }

    /**
     * Lesson meta box
     */
    public function add_lesson_meta_box() {
        $screens = array('post', 'page');
        foreach ($screens as $screen) {
            add_meta_box(
                'lesaep_lesson_meta',
                'LeSAEp Lesson',
                array($this, 'render_lesson_meta_box'),
                $screen,
                'side',
                'high'
            );
        }
    }

    public function render_lesson_meta_box($post) {
        wp_nonce_field('lesaep_lesson_meta_save', 'lesaep_lesson_meta_nonce');

        $is_free = (get_post_meta($post->ID, '_lesaep_is_free', true) === '1');
        $phoneme = get_post_meta($post->ID, '_lesaep_phoneme_group', true);
        $order = get_post_meta($post->ID, '_lesaep_order', true);

        echo '<p><label><input type="checkbox" name="lesaep_is_free" '.checked($is_free, true, false).'> Free lesson</label></p>';
        echo '<p><label>Phoneme group<br><input type="text" name="lesaep_phoneme_group" value="'.esc_attr($phoneme).'" placeholder="/æ/" style="width:100%;"></label></p>';
        echo '<p><label>Order (number)<br><input type="number" name="lesaep_order" value="'.esc_attr($order).'" style="width:100%;" placeholder="10"></label></p>';
        echo '<p class="description">Tip: keep paid lessons unchecked; free lessons checked.</p>';
    }

    public function save_lesson_meta_box($post_id) {
        if (!isset($_POST['lesaep_lesson_meta_nonce']) || !wp_verify_nonce($_POST['lesaep_lesson_meta_nonce'], 'lesaep_lesson_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $is_free = isset($_POST['lesaep_is_free']) ? '1' : '0';
        update_post_meta($post_id, '_lesaep_is_free', $is_free);

        $phoneme = sanitize_text_field($_POST['lesaep_phoneme_group'] ?? '');
        update_post_meta($post_id, '_lesaep_phoneme_group', $phoneme);

        $order = intval($_POST['lesaep_order'] ?? 0);
        if ($order > 0) {
            update_post_meta($post_id, '_lesaep_order', strval($order));
        } else {
            delete_post_meta($post_id, '_lesaep_order');
        }
    }

    /**
     * Enable WooCommerce hook if configured.
     */
    public function maybe_enable_woocommerce_hook() {
        if (!class_exists('WooCommerce')) return;

        $enabled = (get_option('lesaep_wc_enabled', '0') === '1');
        $product_id = intval(get_option('lesaep_wc_product_id', 0));

        if (!$enabled || !$product_id) return;

        add_action('woocommerce_order_status_completed', array($this, 'handle_wc_order_completed'), 10, 1);
    }

    public function handle_wc_order_completed($order_id) {
        if (!class_exists('WC_Order')) return;

        $product_id = intval(get_option('lesaep_wc_product_id', 0));
        if (!$product_id) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $user_id = $order->get_user_id();
        if (!$user_id) return;

        foreach ($order->get_items() as $item) {
            $pid = $item->get_product_id();
            if (intval($pid) === $product_id) {
                $this->grant_paid_access($user_id);
                break;
            }
        }
    }
}

new LeSAEp_Chat_FLOSC();
