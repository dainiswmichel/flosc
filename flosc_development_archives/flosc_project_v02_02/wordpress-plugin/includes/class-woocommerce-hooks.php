<?php
/**
 * FLOSC WooCommerce Integration
 *
 * Automatically grants access when user completes purchase
 * Works with placeholder product IDs for testing
 *
 * How it works:
 * 1. User completes WooCommerce purchase
 * 2. Hook fires: woocommerce_order_status_completed
 * 3. Check if order contains FLOSC product
 * 4. Grant access via BuddyBoss group OR user meta
 * 5. User refreshes chat → sees paid content
 */

if (!defined('ABSPATH')) exit;

class FLOSC_WooCommerce_Hooks {

    private $flosc;

    public function __construct($flosc_framework) {
        $this->flosc = $flosc_framework;

        // Hook into WooCommerce order completion
        add_action('woocommerce_order_status_completed', array($this, 'on_order_completed'));

        // Also catch payment complete (fires before status change)
        add_action('woocommerce_payment_complete', array($this, 'on_payment_complete'));
    }

    /**
     * Triggered when order status changes to "completed"
     * Most common trigger for digital products
     *
     * @param int $order_id WooCommerce order ID
     */
    public function on_order_completed($order_id) {
        $this->grant_access_if_flosc_product($order_id);
    }

    /**
     * Triggered when payment is received
     * Fires before order status changes
     * Backup trigger in case status doesn't change to "completed"
     *
     * @param int $order_id WooCommerce order ID
     */
    public function on_payment_complete($order_id) {
        $this->grant_access_if_flosc_product($order_id);
    }

    /**
     * Check if order contains FLOSC product and grant access
     *
     * Logic:
     * 1. Get configured product ID
     * 2. Loop through order items
     * 3. If FLOSC product found → grant access
     * 4. Prevent duplicate grants (check if already has access)
     *
     * @param int $order_id WooCommerce order ID
     */
    private function grant_access_if_flosc_product($order_id) {
        // Get configured product ID
        $flosc_product_id = $this->flosc->get_config('woocommerce_product_id');

        if (!$flosc_product_id) {
            // No product configured - skip
            return;
        }

        // Get order
        if (!function_exists('wc_get_order')) {
            // WooCommerce not active
            error_log('FLOSC: WooCommerce not active');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('FLOSC: Order not found: ' . $order_id);
            return;
        }

        // Get customer user ID
        $user_id = $order->get_customer_id();
        if (!$user_id) {
            // Guest checkout - can't grant access without user account
            error_log('FLOSC: Guest order, cannot grant access: ' . $order_id);
            return;
        }

        // Check if already has access (prevent duplicate grants)
        if ($this->flosc->user_has_paid_access($user_id)) {
            error_log('FLOSC: User already has access: ' . $user_id);
            return;
        }

        // Check if order contains FLOSC product
        $has_flosc_product = false;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if ($product_id == $flosc_product_id) {
                $has_flosc_product = true;
                break;
            }
        }

        if (!$has_flosc_product) {
            // Order doesn't contain FLOSC product
            return;
        }

        // Grant access!
        $this->grant_access($user_id, $order_id);
    }

    /**
     * Grant FLOSC access to user
     *
     * Method 1 (preferred): Add to BuddyBoss group
     * Method 2 (fallback): Set user meta flag
     *
     * @param int $user_id WordPress user ID
     * @param int $order_id WooCommerce order ID (for logging)
     */
    private function grant_access($user_id, $order_id) {
        $group_id = $this->flosc->get_config('paid_group_id');

        // Method 1: BuddyBoss group (if configured and available)
        if ($group_id && function_exists('groups_join_group')) {
            $result = groups_join_group($group_id, $user_id);

            if ($result) {
                error_log("FLOSC: ✓ Granted access via BuddyBoss group {$group_id} to user {$user_id} (order {$order_id})");
                $method = 'buddyboss_group';
            } else {
                error_log("FLOSC: ✗ Failed to add user {$user_id} to BuddyBoss group {$group_id}");
                // Fall through to Method 2
            }
        }

        // Method 2: User meta (always set as backup)
        if (!isset($method)) {
            update_user_meta($user_id, 'flosc_paid_access', '1');
            update_user_meta($user_id, 'flosc_purchase_order_id', $order_id);
            update_user_meta($user_id, 'flosc_purchase_date', current_time('mysql'));

            error_log("FLOSC: ✓ Granted access via user meta to user {$user_id} (order {$order_id})");
            $method = 'user_meta';
        }

        // Log purchase for analytics
        $this->log_purchase($user_id, $order_id, $method);

        // Optional: Send welcome email, trigger automations, etc
        do_action('flosc_access_granted', $user_id, $order_id, $method);
    }

    /**
     * Log purchase for analytics
     * Stores purchase data in options table for reporting
     *
     * @param int $user_id
     * @param int $order_id
     * @param string $method Access grant method used
     */
    private function log_purchase($user_id, $order_id, $method) {
        $purchases = get_option('flosc_purchases', []);

        $purchases[] = [
            'user_id' => $user_id,
            'order_id' => $order_id,
            'method' => $method,
            'timestamp' => current_time('timestamp'),
            'date' => current_time('mysql'),
        ];

        // Keep last 1000 purchases
        if (count($purchases) > 1000) {
            $purchases = array_slice($purchases, -1000);
        }

        update_option('flosc_purchases', $purchases);
    }
}
