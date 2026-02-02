<?php
/**
 * Plugin Name: LeSAEp Chat FLOSC (Simplified)
 * Description: Chat-only quiz with WordPress payment integration
 * Version: 1.0.1
 */

if (!defined('ABSPATH')) exit;

class LeSAEp_Chat_Simplified {
    
    public function __construct() {
        add_shortcode('lesaep_chat', array($this, 'render_chat'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
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
                                <?php echo $has_access ? 'Full Access' : 'Free Access'; ?>
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
                    <div class="recorder-area hidden" id="recorderArea">
                        <div class="sentence-display" id="sentenceDisplay"></div>
                        <button class="record-btn" id="recordBtn">
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
     * Enqueue assets
     */
    public function enqueue_assets() {
        if (!has_shortcode(get_post()->post_content, 'lesaep_chat')) {
            return;
        }
        
        wp_enqueue_style('lesaep-chat', plugins_url('assets/css/chat.css', __FILE__), [], '1.0.1');
        wp_enqueue_script('lesaep-chat', plugins_url('assets/js/chat.js', __FILE__), [], '1.0.1', true);
        
        $user = wp_get_current_user();
        wp_localize_script('lesaep-chat', 'LESAEP', [
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
        ]);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        
        // Get user access status
        register_rest_route('lesaep/v1', '/access', [
            'methods' => 'GET',
            'callback' => array($this, 'get_user_access'),
            'permission_callback' => '__return_true'
        ]);
        
        // Get lessons (filtered by access)
        register_rest_route('lesaep/v1', '/lessons', [
            'methods' => 'GET',
            'callback' => array($this, 'get_lessons'),
            'permission_callback' => '__return_true'
        ]);
        
        // Get single lesson
        register_rest_route('lesaep/v1', '/lessons/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => array($this, 'get_single_lesson'),
            'permission_callback' => '__return_true'
        ]);
        
        // Mark user as paid (called after WooCommerce/EDD purchase)
        register_rest_route('lesaep/v1', '/mark-paid', [
            'methods' => 'POST',
            'callback' => array($this, 'mark_user_paid'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    /**
     * Get user access status
     */
    public function get_user_access($request) {
        $user = wp_get_current_user();
        
        if (!$user->ID) {
            return new WP_REST_Response([
                'logged_in' => false,
                'has_access' => false
            ]);
        }
        
        return new WP_REST_Response([
            'logged_in' => true,
            'user_id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'has_paid_access' => $this->user_has_paid_access($user->ID)
        ]);
    }
    
    /**
     * Get lessons (filtered by access level)
     */
    public function get_lessons($request) {
        $user_id = get_current_user_id();
        $has_access = $this->user_has_paid_access($user_id);
        
        $args = [
            'post_type' => 'post',
            'category_name' => 'lesaep',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ];
        
        // If not paid, only show free lessons
        if (!$has_access) {
            $args['meta_query'] = [
                [
                    'key' => '_lesaep_is_free',
                    'value' => '1'
                ]
            ];
        }
        
        $posts = get_posts($args);
        
        $lessons = array_map(function($post) {
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'excerpt' => get_the_excerpt($post),
                'content' => apply_filters('the_content', $post->post_content),
                'is_free' => get_post_meta($post->ID, '_lesaep_is_free', true) === '1',
                'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true),
                'permalink' => get_permalink($post)
            ];
        }, $posts);
        
        return new WP_REST_Response($lessons);
    }
    
    /**
     * Get single lesson (with access control)
     */
    public function get_single_lesson($request) {
        $lesson_id = $request->get_param('id');
        $user_id = get_current_user_id();
        
        $post = get_post($lesson_id);
        if (!$post) {
            return new WP_Error('not_found', 'Lesson not found', ['status' => 404]);
        }
        
        $is_free = get_post_meta($post->ID, '_lesaep_is_free', true) === '1';
        $has_access = $is_free || $this->user_has_paid_access($user_id);
        
        if (!$has_access) {
            return new WP_Error('forbidden', 'Access denied', ['status' => 403]);
        }
        
        return new WP_REST_Response([
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => apply_filters('the_content', $post->post_content),
            'is_free' => $is_free,
            'phoneme_group' => get_post_meta($post->ID, '_lesaep_phoneme_group', true)
        ]);
    }
    
    /**
     * Mark user as paid (called after purchase)
     */
    public function mark_user_paid($request) {
        $user_id = $request->get_param('user_id');
        
        if (!$user_id) {
            return new WP_Error('invalid', 'User ID required', ['status' => 400]);
        }
        
        // Get BuddyBoss group ID
        $group_id = get_option('lesaep_paid_group_id');
        
        if ($group_id && function_exists('groups_join_group')) {
            // Add to BuddyBoss group
            groups_join_group($group_id, $user_id);
        } else {
            // Fallback: Set user meta
            update_user_meta($user_id, 'lesaep_paid_access', '1');
        }
        
        return new WP_REST_Response([
            'success' => true,
            'user_id' => $user_id,
            'method' => $group_id ? 'buddyboss_group' : 'user_meta'
        ]);
    }
    
    /**
     * Check if user has paid access
     * 
     * Checks in this order:
     * 1. BuddyBoss group membership
     * 2. User meta flag
     * 3. WordPress capability
     */
    private function user_has_paid_access($user_id) {
        if (!$user_id) {
            return false;
        }
        
        // Check BuddyBoss group
        $group_id = get_option('lesaep_paid_group_id');
        if ($group_id && function_exists('groups_is_user_member')) {
            if (groups_is_user_member($user_id, $group_id)) {
                return true;
            }
        }
        
        // Check user meta
        if (get_user_meta($user_id, 'lesaep_paid_access', true) === '1') {
            return true;
        }
        
        // Check custom capability (for Advanced Access Manager etc)
        $user = get_user_by('id', $user_id);
        if ($user && $user->has_cap('lesaep_full_access')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get checkout URL
     * 
     * This should point to your WordPress checkout page
     * Examples:
     * - WooCommerce: /checkout/?add-to-cart=PRODUCT_ID
     * - Easy Digital Downloads: /checkout/?edd_action=add_to_cart&download_id=ID
     * - Paid Memberships Pro: /membership-checkout/?level=LEVEL_ID
     * - Simple page with payment form
     */
    private function get_checkout_url() {
        $custom_url = get_option('lesaep_checkout_url');
        
        if ($custom_url) {
            return $custom_url;
        }
        
        // Default: Simple checkout page
        return home_url('/lesaep/checkout/');
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
    
    /**
     * Admin page
     */
    public function admin_page() {
        if (isset($_POST['lesaep_save'])) {
            update_option('lesaep_api_url', sanitize_text_field($_POST['api_url']));
            update_option('lesaep_paid_group_id', intval($_POST['paid_group_id']));
            update_option('lesaep_product_name', sanitize_text_field($_POST['product_name']));
            update_option('lesaep_price', intval($_POST['price']));
            update_option('lesaep_checkout_url', esc_url($_POST['checkout_url']));
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        $api_url = get_option('lesaep_api_url', '/lesaep-api');
        $group_id = get_option('lesaep_paid_group_id');
        $product_name = get_option('lesaep_product_name', 'LeSAEp Course');
        $price = get_option('lesaep_price', 144);
        $checkout_url = get_option('lesaep_checkout_url');
        
        ?>
        <div class="wrap">
            <h1>LeSAEp Chat Settings</h1>
            
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th>Backend API URL</th>
                        <td>
                            <input type="text" name="api_url" value="<?php echo esc_attr($api_url); ?>" class="regular-text">
                            <p class="description">Whisper audio processing endpoint (e.g., /lesaep-api)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>BuddyBoss Paid Group ID</th>
                        <td>
                            <input type="number" name="paid_group_id" value="<?php echo esc_attr($group_id); ?>">
                            <p class="description">Group ID for paid members. <a href="<?php echo admin_url('admin.php?page=bp-groups'); ?>">View Groups</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Product Name</th>
                        <td>
                            <input type="text" name="product_name" value="<?php echo esc_attr($product_name); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>Price</th>
                        <td>
                            $<input type="number" name="price" value="<?php echo esc_attr($price); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Checkout URL</th>
                        <td>
                            <input type="url" name="checkout_url" value="<?php echo esc_attr($checkout_url); ?>" class="regular-text">
                            <p class="description">WordPress checkout page URL (WooCommerce, EDD, PMPro, or custom)</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings', 'primary', 'lesaep_save'); ?>
            </form>
            
            <hr>
            
            <h2>Quick Setup Guide</h2>
            <ol>
                <li>Create a BuddyBoss group for paid members</li>
                <li>Set up payment in WordPress:
                    <ul>
                        <li><strong>WooCommerce:</strong> Create product, add action hook to mark user paid</li>
                        <li><strong>Easy Digital Downloads:</strong> Create download, use purchase action</li>
                        <li><strong>Paid Memberships Pro:</strong> Create membership level</li>
                        <li><strong>Custom:</strong> Use payment form, call <code>/wp-json/lesaep/v1/mark-paid</code></li>
                    </ul>
                </li>
                <li>After payment, add user to BuddyBoss group OR call REST endpoint</li>
                <li>Create WordPress page with <code>[lesaep_chat]</code> shortcode</li>
                <li>Add lesson posts with category "lesaep" and custom fields:
                    <ul>
                        <li><code>_lesaep_is_free</code> = 1 (free) or 0 (paid)</li>
                        <li><code>_lesaep_phoneme_group</code> = /æ/ (or other phoneme)</li>
                    </ul>
                </li>
            </ol>
            
            <h3>Example: WooCommerce Integration</h3>
            <pre>// In theme functions.php or custom plugin
add_action('woocommerce_order_status_completed', 'lesaep_mark_paid_after_purchase');

function lesaep_mark_paid_after_purchase($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    
    // Check if order contains LeSAEp product
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        if ($product_id == YOUR_LESAEP_PRODUCT_ID) {
            // Add to BuddyBoss group
            $group_id = get_option('lesaep_paid_group_id');
            if ($group_id && function_exists('groups_join_group')) {
                groups_join_group($group_id, $user_id);
            }
            break;
        }
    }
}</pre>
        </div>
        <?php
    }
}

// Initialize
new LeSAEp_Chat_Simplified();
