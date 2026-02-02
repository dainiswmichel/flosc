<?php
if (!defined('ABSPATH')) exit;

class FLOSC_Settings {
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function render_page() {
        if (isset($_POST['flosc_save_settings'])) {
            check_admin_referer('flosc_settings');
            $this->save_settings();
        }
        ?>
        <div class="wrap">
            <h1>FLOSC Settings</h1>
            <form method="post">
                <?php wp_nonce_field('flosc_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Product Name</th>
                        <td><input type="text" name="flosc_product_name" value="<?php echo esc_attr(get_option('flosc_product_name')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Full Price ($)</th>
                        <td><input type="number" name="flosc_full_price" value="<?php echo esc_attr(get_option('flosc_full_price')); ?>"></td>
                    </tr>
                    <tr>
                        <th>OTO Price ($)</th>
                        <td><input type="number" name="flosc_oto_price" value="<?php echo esc_attr(get_option('flosc_oto_price')); ?>"></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="flosc_save_settings" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function save_settings() {
        update_option('flosc_product_name', sanitize_text_field($_POST['flosc_product_name']));
        update_option('flosc_full_price', intval($_POST['flosc_full_price']));
        update_option('flosc_oto_price', intval($_POST['flosc_oto_price']));
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
}
