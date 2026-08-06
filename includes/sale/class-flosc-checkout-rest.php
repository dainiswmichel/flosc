<?php
/**
 * Domain collaborator — FLOSC_Checkout_Rest
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Checkout_Rest {

    /** @var FLOSC_Framework */
    private $flosc;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    public function get_offers($request) {
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        // v1.6.2: Flow-aware offer loading
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if (empty($flow_id)) {
            $flow = $this->flosc->get_current_flow();
            if ($flow && !empty($flow['ivr_file'])) {
                $flow_id = pathinfo(basename($flow['ivr_file']), PATHINFO_FILENAME);
            }
        }
        $offers = $this->flosc->sale()->get_available_offers($user_id, $flow_id ?: null);
        return new WP_REST_Response(['offers' => array_values($offers)]);
    }

    /**
     * v1.6.2: Serve offer content from external sources
     * Supports: HtmlFile (static HTML in plugin), WooProduct (WooCommerce), PostID (WP post)
     * Sanitizes output to prevent XSS.
     */
    public function get_offer_content($request) {
        $source = sanitize_text_field($request->get_param('source'));
        
        switch ($source) {
            case 'html':
                $file = sanitize_file_name($request->get_param('file'));
                if (empty($file)) {
                    return new WP_REST_Response(['error' => 'Missing file parameter'], 400);
                }
                // Only allow files from the offer_content directory
                $path = flosc_config_file('offer_content/' . $file);
                if (!file_exists($path) || !is_readable($path)) {
                    return new WP_REST_Response(['error' => 'File not found'], 404);
                }
                // Only allow .html and .htm extensions
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (!in_array($ext, ['html', 'htm'], true)) {
                    return new WP_REST_Response(['error' => 'Invalid file type'], 400);
                }
                $html = wp_kses_post(flosc_fs_get_contents($path));
                return new WP_REST_Response(['html' => $html]);
                
            case 'woo':
                $product_id = intval($request->get_param('product'));
                if (!$product_id || !function_exists('wc_get_product')) {
                    return new WP_REST_Response(['error' => 'WooCommerce not available or invalid product'], 400);
                }
                $product = wc_get_product($product_id);
                if (!$product || 'publish' !== $product->get_status()) {
                    return new WP_REST_Response(['error' => 'Product not found'], 404);
                }
                // Catalog-hidden products are not public offer surfaces.
                if (method_exists($product, 'get_catalog_visibility')
                    && 'hidden' === $product->get_catalog_visibility()
                    && !current_user_can('manage_options')) {
                    return new WP_REST_Response(['error' => 'Product not available'], 403);
                }
                return new WP_REST_Response([
                    'html' => wp_kses_post($product->get_description()),
                    'price' => $product->get_price(),
                    'name' => $product->get_name(),
                ]);
                
            case 'post':
                $post_id = intval($request->get_param('id'));
                if (!$post_id) {
                    return new WP_REST_Response(['error' => 'Missing post ID'], 400);
                }
                $post = get_post($post_id);
                if (!$post || $post->post_status !== 'publish') {
                    return new WP_REST_Response(['error' => 'Post not found'], 404);
                }
                // REST is not is_singular(); content protection filters do not apply.
                // Enforce FLOSC entitlement before returning post body.
                require_once FLOSC_PLUGIN_DIR . 'includes/class-content-protection.php';
                if (!FLOSC_Content_Protection::instance()->user_can_access($post_id)
                    && !current_user_can('manage_options')) {
                    return new WP_REST_Response(
                        ['error' => 'Not entitled to this content', 'locked' => true],
                        403
                    );
                }
                // WordPress content filters (shortcodes, embeds, blocks, etc.).
                $html = apply_filters( 'the_content', $post->post_content );
                return new WP_REST_Response(
                    array(
                        'html' => wp_kses_post( $html ),
                    )
                );
                
            default:
                return new WP_REST_Response(['error' => 'Invalid source type'], 400);
        }
    }

    /**
     * Handle purchase
     * 
     * STATUS: ✅ FULLY FUNCTIONAL (v9.1.9)
     * - Processes purchase via payment provider
     * - Fires flosc_purchase_completed action ✅
     * - Grants member access automatically ✅
     * - Sets _flosc_member_access = 'true' ✅
     * - User can now access ALL 10 posts ✅
     * 
     * TESTING: Use 'tokens' provider for sandbox testing
     */
    public function handle_purchase($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_Error('not_logged_in', __('You must be logged in to make a purchase', 'flosc'), ['status' => 401]);
        }
        
        // Verify nonce for REST security
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if (!wp_verify_nonce(sanitize_text_field((string) $nonce), 'wp_rest')) {
            return new WP_Error('invalid_nonce', __('Security token invalid. Please refresh.', 'flosc'), ['status' => 403]);
        }
        
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $method   = sanitize_text_field($request->get_param('method') ?? '');
        $provider_id = sanitize_text_field($request->get_param('provider') ?? '');
        $payment_data = $request->get_param('payment_data') ?? [];

        // Free offer: grant access directly, no payment provider needed
        if ($method === 'free' || empty($provider_id)) {
            $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
            if (!empty($flow_id)) {
                $this->flosc->set_flow_context($flow_id);
            }
            $offer = $this->flosc->sale()->offers()->get_offer($offer_id, $flow_id ?: null);
            if (!$offer) {
                return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
            }
            $price = floatval($offer['pricing']['price'] ?? $offer['price'] ?? 0);
            if ($price > 0 && $method === 'free') {
                return new WP_Error('not_free', __('This offer requires payment', 'flosc'), ['status' => 400]);
            }
            // Grant access
            $this->flosc->sale()->access()->grant_from_offer($user_id, $offer, [
                'transaction_id' => 'free_' . time(),
                'provider' => 'free',
                'amount' => 0,
            ]);
            do_action('flosc_purchase_completed', $user_id, [
                'offer_id' => $offer_id,
                'grants_level' => $offer['grants_level'] ?? '',
                'provider' => 'free',
                'transaction_id' => 'free_' . time(),
                'amount' => 0,
                'timestamp' => time(),
            ]);
            return new WP_REST_Response([
                'success' => true,
                'message' => __('Free access granted', 'flosc'),
            ]);
        }
        
        // Paid purchase via provider
        $result = $this->flosc->sale()->process_purchase(
            $user_id,
            $offer_id,
            $provider_id,
            $payment_data
        );
        
        if (is_wp_error($result)) {
            return $result;
        }

        // process_purchase() already fires flosc_purchase_completed
        
        return new WP_REST_Response($result);
    }

    /**
     * List price for an offer (dollars).
     *
     * @param array $offer Offer row.
     * @return float
     */
    private function flosc_offer_list_price($offer) {
        $amount = 0.0;
        if (!empty($offer['pricing']['price'])) {
            $amount = floatval($offer['pricing']['price']);
        }
        if ($amount <= 0 && !empty($offer['price'])) {
            $amount = floatval($offer['price']);
        }
        return max(0.0, $amount);
    }

    /**
     * Apply a native price coupon to an offer. Server is source of truth.
     * fixed_price = final amount charged; percent = % off list. Windows = UTC.
     *
     * @param array  $offer Offer.
     * @param string $code  Coupon code.
     * @return array|WP_Error { payable, list_price, code, type, value }
     */
    private function flosc_apply_offer_price_coupon(array $offer, $code) {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return new WP_Error('missing_code', __('No coupon code provided', 'flosc'), ['status' => 400]);
        }
        $coupons = $offer['coupons'] ?? [];
        if (!is_array($coupons) || empty($coupons)) {
            return new WP_Error('invalid_coupon', __('Invalid or expired coupon', 'flosc'), ['status' => 403]);
        }
        $now = time(); // UTC unix
        $list = $this->flosc_offer_list_price($offer);

        foreach ($coupons as $c) {
            if (!is_array($c)) {
                continue;
            }
            if (isset($c['active']) && empty($c['active'])) {
                continue;
            }
            if (strtoupper((string) ($c['code'] ?? '')) !== $code) {
                continue;
            }
            $from = $this->flosc_parse_utc_mts_timestamp((string) ($c['valid_from_utc'] ?? ''));
            $until = $this->flosc_parse_utc_mts_timestamp((string) ($c['valid_until_utc'] ?? ''));
            if ($from === false || $until === false) {
                continue;
            }
            if ($from !== null && $now < $from) {
                continue;
            }
            if ($until !== null && $now > $until) {
                continue;
            }
            $type = sanitize_key((string) ($c['type'] ?? 'fixed_price'));
            $value = floatval($c['value'] ?? 0);
            if ($type === 'percent') {
                $payable = max(0.0, round($list * (1.0 - (max(0.0, min(100.0, $value)) / 100.0)), 2));
            } else {
                // fixed_price = final charge (admin does not reverse-% from list).
                $payable = max(0.0, round($value, 2));
            }
            return [
                'payable'    => $payable,
                'list_price' => $list,
                'code'       => $code,
                'type'       => $type === 'percent' ? 'percent' : 'fixed_price',
                'value'      => $value,
            ];
        }
        return new WP_Error('invalid_coupon', __('Invalid or expired coupon', 'flosc'), ['status' => 403]);
    }

    /**
     * Resolve payable amount for native one-time checkout (list price or coupon).
     *
     * @param array  $offer Offer.
     * @param string $coupon_code Optional.
     * @return array|WP_Error { amount, list_price, coupon_code, currency_hint }
     */
    private function flosc_resolve_native_payable_amount(array $offer, $coupon_code = '') {
        $list = $this->flosc_offer_list_price($offer);
        $coupon_code = trim((string) $coupon_code);
        if ($coupon_code === '') {
            if ($list <= 0) {
                return new WP_Error('no_price', __('No price configured for this offer.', 'flosc'), ['status' => 400]);
            }
            return [
                'amount'      => $list,
                'list_price'  => $list,
                'coupon_code' => '',
            ];
        }
        $applied = $this->flosc_apply_offer_price_coupon($offer, $coupon_code);
        if (is_wp_error($applied)) {
            return $applied;
        }
        if ($applied['payable'] <= 0) {
            return new WP_Error(
                'coupon_is_access',
                __('This code is an access code. Use Access Code instead of payment.', 'flosc'),
                ['status' => 400]
            );
        }
        return [
            'amount'      => $applied['payable'],
            'list_price'  => $applied['list_price'],
            'coupon_code' => $applied['code'],
            'coupon_type' => $applied['type'],
        ];
    }

    /**
     * List monthly/yearly subscription prices from offer (dollars).
     *
     * @param array $offer Offer.
     * @return array{monthly:float,yearly:float}
     */
    private function flosc_offer_subscription_list_prices(array $offer) {
        $plans = is_array($offer['subscription']['plans'] ?? null) ? $offer['subscription']['plans'] : [];
        $monthly = floatval($plans['monthly']['price'] ?? 0);
        if ($monthly <= 0) {
            $monthly = $this->flosc_offer_list_price($offer);
        }
        $yearly = floatval($plans['yearly']['price'] ?? 0);
        if ($yearly <= 0 && $monthly > 0) {
            $yearly = round($monthly * 10, 2);
        }
        return [
            'monthly' => max(0.0, $monthly),
            'yearly'  => max(0.0, $yearly),
        ];
    }

    /**
     * Apply coupon to subscription plan amounts.
     * fixed_price = final monthly amount; yearly scales by same ratio as monthly discount.
     * percent = both intervals reduced by %.
     *
     * @param array  $offer Offer.
     * @param string $coupon_code Code.
     * @return array|WP_Error
     */
    private function flosc_resolve_subscription_coupon_prices(array $offer, $coupon_code = '') {
        $list = $this->flosc_offer_subscription_list_prices($offer);
        $coupon_code = trim((string) $coupon_code);
        if ($coupon_code === '') {
            if ($list['monthly'] <= 0 && $list['yearly'] <= 0) {
                return new WP_Error('no_price', __('No subscription prices on this offer.', 'flosc'), ['status' => 400]);
            }
            return [
                'monthly'     => $list['monthly'],
                'yearly'      => $list['yearly'],
                'list_monthly'=> $list['monthly'],
                'list_yearly' => $list['yearly'],
                'coupon_code' => '',
            ];
        }
        // Reuse coupon validation (window/type) against monthly list as the base amount.
        $probe = $offer;
        if (empty($probe['pricing']) || !is_array($probe['pricing'])) {
            $probe['pricing'] = [];
        }
        $probe['pricing']['price'] = $list['monthly'] > 0 ? $list['monthly'] : $list['yearly'];
        $probe['price'] = $probe['pricing']['price'];
        $applied = $this->flosc_apply_offer_price_coupon($probe, $coupon_code);
        if (is_wp_error($applied)) {
            return $applied;
        }
        if ($applied['payable'] <= 0) {
            return new WP_Error(
                'coupon_is_access',
                __('This code is an access code. Use Access Code instead of payment.', 'flosc'),
                ['status' => 400]
            );
        }
        $type = $applied['type'] ?? 'fixed_price';
        $value = floatval($applied['value'] ?? 0);
        if ($type === 'percent') {
            $factor = 1.0 - (max(0.0, min(100.0, $value)) / 100.0);
            $monthly = max(0.0, round($list['monthly'] * $factor, 2));
            $yearly  = max(0.0, round($list['yearly'] * $factor, 2));
        } else {
            // fixed_price = final monthly; yearly keeps same discount ratio as monthly.
            $monthly = max(0.0, round($value, 2));
            if ($list['monthly'] > 0 && $list['yearly'] > 0) {
                $yearly = max(0.0, round($list['yearly'] * ($monthly / $list['monthly']), 2));
            } else {
                $yearly = $monthly > 0 ? round($monthly * 10, 2) : 0.0;
            }
        }
        if ($monthly <= 0 && $yearly <= 0) {
            return new WP_Error('no_price', __('Coupon reduced subscription to zero. Use Access Code to unlock.', 'flosc'), ['status' => 400]);
        }
        return [
            'monthly'      => $monthly,
            'yearly'       => $yearly,
            'list_monthly' => $list['monthly'],
            'list_yearly'  => $list['yearly'],
            'coupon_code'  => $applied['code'],
            'coupon_type'  => $type,
        ];
    }

    /**
     * Whether offer is treated as subscription for checkout coupons.
     */
    private function flosc_offer_is_subscription(array $offer) {
        if (($offer['type'] ?? '') === 'subscription') {
            return true;
        }
        $plans = $offer['subscription']['plans'] ?? null;
        return is_array($plans) && !empty($plans);
    }

    /**
     * Preview coupon for payment modal (native only). Does not charge.
     */
    public function handle_apply_offer_coupon($request) {
        $offer_id = sanitize_text_field($request->get_param('offer_id') ?? '');
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        $code = sanitize_text_field($request->get_param('code') ?? '');
        if ($flow_id !== '') {
            $this->flosc->set_flow_context($flow_id);
        }
        $offer = $this->flosc->sale()->offers()->get_offer($offer_id, $flow_id ?: null);
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        $proc = strtolower((string) ($offer['pricing']['processor'] ?? $offer['processor'] ?? 'paypal'));
        if ($proc === 'redirect') {
            return new WP_Error(
                'external_shop',
                __('Coupons for external checkout are handled by the shop (Woo/Shopify/etc.).', 'flosc'),
                ['status' => 400]
            );
        }
        // Access code on this offer?
        $access_codes = $offer['access_codes'] ?? [];
        if (is_array($access_codes)) {
            $up = strtoupper(trim($code));
            foreach ($access_codes as $ac) {
                if (strtoupper((string) $ac) === $up) {
                    return new WP_REST_Response([
                        'success' => true,
                        'kind'    => 'access_code',
                        'message' => 'Access code — use Access Code to unlock.',
                    ]);
                }
            }
        }
        $currency = strtoupper((string) ($offer['pricing']['currency'] ?? 'USD')) ?: 'USD';

        // Subscription: return monthly/yearly payable (for plan UI + PayPal plan create).
        if ($this->flosc_offer_is_subscription($offer)) {
            $sub = $this->flosc_resolve_subscription_coupon_prices($offer, $code);
            if (is_wp_error($sub)) {
                return $sub;
            }
            return new WP_REST_Response([
                'success'           => true,
                'kind'              => 'coupon',
                'billing'           => 'subscription',
                'monthly'           => $sub['monthly'],
                'yearly'            => $sub['yearly'],
                'list_monthly'      => $sub['list_monthly'],
                'list_yearly'       => $sub['list_yearly'],
                'currency'          => $currency,
                'coupon_code'       => $sub['coupon_code'],
                'display'           => '$' . number_format((float) $sub['monthly'], 2) . '/mo',
                'list_display'      => '$' . number_format((float) $sub['list_monthly'], 2) . '/mo',
                'yearly_display'    => '$' . number_format((float) $sub['yearly'], 2) . '/yr',
                'list_yearly_display' => '$' . number_format((float) $sub['list_yearly'], 2) . '/yr',
            ]);
        }

        $resolved = $this->flosc_resolve_native_payable_amount($offer, $code);
        if (is_wp_error($resolved)) {
            return $resolved;
        }
        return new WP_REST_Response([
            'success'     => true,
            'kind'        => 'coupon',
            'billing'     => 'one_time',
            'amount'      => $resolved['amount'],
            'list_price'  => $resolved['list_price'],
            'currency'    => $currency,
            'coupon_code' => $resolved['coupon_code'],
            'display'     => '$' . number_format((float) $resolved['amount'], 2),
            'list_display'=> '$' . number_format((float) $resolved['list_price'], 2),
        ]);
    }

    /**
     * v1.4.4: Product-Aware Sandbox Purchase
     * Grants product-specific membership level based on product_id
     * Fun "Pay What You Want" for testing the full purchase flow
     * 
     * v1.4.4 FIX: Now fires flosc_purchase_completed AND directly calls
     * FLOSC_Member_Access::grant_level() so content protection works immediately.
     * Previous bug: sandbox set _flosc_member_level but content protection
     * checks _flosc_memberlevel_{level} via has_level(). Mismatch = no access.
     */
    public function handle_sandbox_purchase($request) {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'You must be logged in to make a purchase.'
            ], 401);
        }
        
        // Verify nonce for REST security
        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if (!wp_verify_nonce(sanitize_text_field((string) $nonce), 'wp_rest')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Security token invalid. Please refresh.'
            ], 403);
        }
        
        // v3.0.5: Set flow context so offer lookup finds per-flow data
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if (!empty($flow_id)) {
            $this->flosc->set_flow_context($flow_id);
        }
        
        // v1.4.0: Get product_id for product-specific purchase
        $product_id = sanitize_text_field($request->get_param('product_id') ?? '');
        $offer_id = sanitize_text_field($request->get_param('offer_id') ?? 'sandbox');
        $amount = sanitize_text_field($request->get_param('amount') ?? '1,000,000,000');
        
        // Format amount for display
        $numeric_amount = intval(str_replace(',', '', $amount));
        $formatted_amount = '$' . number_format($numeric_amount);
        
        // Generate fun transaction ID
        $transaction_id = 'sandbox_' . $user_id . '_' . time() . '_' . wp_rand(1000, 9999);
        
        // v3.0.5: Determine member level — check offer first (flow-aware), then product fallback
        $offer_manager = $this->flosc->sale()->offers();
        $member_level = 'member'; // Default fallback
        $product_name = 'Full Access';
        $product_icon = '🎁';
        
        // Try the offer (flow-aware lookup)
        if (!empty($offer_id) && $offer_id !== 'sandbox') {
            $offer = $offer_manager->get_offer($offer_id, $flow_id ?: null);
            if ($offer && !empty($offer['grants']['level'])) {
                $member_level = $offer['grants']['level'];
                $product_name = $offer['name'];
                $product_icon = $offer['meta']['icon'] ?? '🎁';
                $product_id = $offer['product_id'] ?? '';
            }
        }
        
        // Grant product-specific membership level
        update_user_meta($user_id, '_flosc_member_level', $member_level);
        update_user_meta($user_id, '_flosc_purchased', true);
        update_user_meta($user_id, '_flosc_purchased_at', current_time('mysql'));
        update_user_meta($user_id, '_flosc_sandbox_amount', $formatted_amount);
        update_user_meta($user_id, '_flosc_sandbox_transaction', $transaction_id);
        update_user_meta($user_id, '_flosc_purchased_product', $product_id);
        
        // v1.4.4 FIX: Grant access through FLOSC_Member_Access so content protection works
        // This sets _flosc_member_access='true' AND _flosc_memberlevel_{level}='yes'
        $member_access = FLOSC_Member_Access::instance();
        $member_access->grant_member_access($user_id, [
            'offer_id' => $offer_id,
            'grants_level' => $member_level,
            'provider' => 'sandbox',
            'transaction_id' => $transaction_id,
            'amount' => $formatted_amount,
            'sandbox' => true,
            'flow_id' => $flow_id,
        ]);
        
        // v1.4.0: Add to member levels array (user can have multiple products)
        $existing_levels = get_user_meta($user_id, '_flosc_member_levels', true) ?: [];
        if (!in_array($member_level, $existing_levels)) {
            $existing_levels[] = $member_level;
            update_user_meta($user_id, '_flosc_member_levels', $existing_levels);
        }
        
        // Grant full access via access manager
        $access_manager = $this->flosc->sale()->access();
        $sandbox_offer = [
            'id' => $offer_id ?: 'flosc_sandbox',
            'name' => $product_name,
            'grants' => [
                'level' => $member_level,
                'features' => ['all_lessons', 'all_quizzes', 'ai_chat', 'premium_content'],
            ],
        ];
        $access_manager->grant_from_offer($user_id, $sandbox_offer, [
            'transaction_id' => $transaction_id,
            'amount' => $formatted_amount,
            'sandbox' => true,
            'flow_id' => $flow_id,
        ]);
        
        // Log the sandbox purchase
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
    if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC Sandbox: User {$user_id} purchased {$product_name} ({$member_level}) for {$formatted_amount} (Transaction: {$transaction_id})");
        }
        
        // Set transient for first_message_after_purchase condition
        set_transient('flosc_just_purchased_' . $user_id, true, 300);
        
        // v1.4.4 FIX: Fire flosc_purchase_completed (was only firing flosc_sandbox_purchase)
        // This triggers FLOSC_Member_Access::grant_member_access for any other listeners
        do_action('flosc_purchase_completed', $user_id, [
            'offer_id' => $offer_id,
            'grants_level' => $member_level,
            'provider' => 'sandbox',
            'transaction_id' => $transaction_id,
            'amount' => $formatted_amount,
            'timestamp' => time(),
            'flow_id' => $flow_id,
        ]);
        
        do_action('flosc_sandbox_purchase', $user_id, [
            'offer_id' => $offer_id,
            'product_id' => $product_id,
            'amount' => $formatted_amount,
            'transaction_id' => $transaction_id,
            'member_level' => $member_level,
            'timestamp' => time()
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Sandbox purchase completed!',
            'transaction_id' => $transaction_id,
            'amount' => $formatted_amount,
            'member_level' => $member_level,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_icon' => $product_icon,
        ]);
    }

    public function create_payment_intent($request) {
        // Stripe is first-class: requires publishable + secret keys on Payments (per-flow WPDB).
        $stripe = $this->flosc->sale()->get_provider('stripe');
        if (!$stripe || !$stripe->is_configured()) {
            return new WP_Error('stripe_not_configured', __('Stripe is not configured. Add keys under Payments for this flow.', 'flosc'), ['status' => 503]);
        }
        
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        $coupon_code = sanitize_text_field($request->get_param('coupon_code') ?? '');
        $flow_id = sanitize_text_field($request->get_param('flow_id') ?? '');
        if ($flow_id !== '') {
            $this->flosc->set_flow_context($flow_id);
        }
        $offer = $this->flosc->sale()->offers()->get_offer($offer_id, $flow_id ?: null);
        
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        
        $stripe = $this->flosc->sale()->get_provider('stripe');
        if (!$stripe || !$stripe->is_configured()) {
            return new WP_Error('stripe_not_configured', __('Stripe is not configured', 'flosc'), ['status' => 500]);
        }
        
        $price_id = $offer['pricing']['stripe']['price_id'] ?? '';
        $currency = strtolower((string) ($offer['pricing']['currency'] ?? 'usd')) ?: 'usd';

        // With a coupon, always charge dynamic amount (cents) — Stripe Price ID is full list price.
        if ($coupon_code !== '') {
            $payable = $this->flosc_resolve_native_payable_amount($offer, $coupon_code);
            if (is_wp_error($payable)) {
                return $payable;
            }
            $price_or_amount = intval(round(floatval($payable['amount']) * 100));
        } elseif ($price_id) {
            $price_or_amount = $price_id;
        } else {
            $payable = $this->flosc_resolve_native_payable_amount($offer, '');
            if (is_wp_error($payable)) {
                return $payable;
            }
            $price_or_amount = intval(round(floatval($payable['amount']) * 100));
        }
        if (is_numeric($price_or_amount) && (int) $price_or_amount <= 0) {
            return new WP_Error('no_price', 'No price configured for offer "' . $offer_id . '". Set a Stripe Price ID or a price in FLOSC → Offers tab.', ['status' => 400]);
        }
        
        $user = wp_get_current_user();
        
        // v1.4.1: Pass offer_id to include in metadata for webhook processing
        $result = $stripe->create_payment_intent($user, $price_or_amount, $currency, $offer_id);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return new WP_REST_Response($result);
    }

    /**
     * v1.4.1: Complete purchase after client-side payment confirmation
     * Verifies payment with Stripe and grants access (fallback if webhook is slow)
     */
    public function complete_purchase($request) {
        $payment_intent_id = sanitize_text_field($request->get_param('payment_intent_id'));
        $offer_id = sanitize_text_field($request->get_param('offer_id'));
        
        if (empty($payment_intent_id) || empty($offer_id)) {
            return new WP_Error('missing_params', __('Missing payment_intent_id or offer_id', 'flosc'), ['status' => 400]);
        }
        
        $user_id = get_current_user_id();
        
        // Check if already has access (webhook might have already processed)
        $access_manager = $this->flosc->sale()->access();
        if ($access_manager->has_offer($user_id, $offer_id)) {
            // v1.4.6: Still set transient so post-purchase greeting shows on reload
            set_transient('flosc_just_purchased_' . $user_id, true, 300);
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Access already granted',
            ]);
        }
        
        // Get offer
        $offer = $this->flosc->sale()->offers()->get_offer($offer_id);
        if (!$offer) {
            return new WP_Error('invalid_offer', __('Offer not found', 'flosc'), ['status' => 404]);
        }
        
        // Verify payment with Stripe
        $stripe = $this->flosc->sale()->get_provider('stripe');
        if (!$stripe || !$stripe->is_configured()) {
            return new WP_Error('stripe_not_configured', __('Stripe is not configured', 'flosc'), ['status' => 500]);
        }
        
        $payment_intent = $stripe->retrieve_payment_intent($payment_intent_id);
        if (is_wp_error($payment_intent)) {
            return $payment_intent;
        }
        
        // Verify payment succeeded and belongs to this user
        if ($payment_intent['status'] !== 'succeeded') {
            return new WP_Error('payment_not_succeeded', __('Payment not completed', 'flosc'), ['status' => 400]);
        }
        
        $pi_user_id = $payment_intent['metadata']['user_id'] ?? null;
        if (intval($pi_user_id) !== $user_id) {
            return new WP_Error('user_mismatch', __('Payment does not belong to this user', 'flosc'), ['status' => 403]);
        }
        
        // Grant access
        $transaction = [
            'transaction_id' => $payment_intent['id'],
            'provider' => 'stripe',
            'amount' => $payment_intent['amount'],
            'currency' => $payment_intent['currency'],
        ];
        
        $access_manager->grant_from_offer($user_id, $offer, $transaction);
        
        // v1.4.6: Set transient so chatbot shows post-purchase greeting on reload
        set_transient('flosc_just_purchased_' . $user_id, true, 300);
        
        // v1.5.4: Store which flow this purchase belongs to
        $current_flow = $this->flosc->get_current_flow();
        $flow_id = $current_flow ? ($current_flow['id'] ?? '') : '';
        if ($flow_id) {
            update_user_meta($user_id, '_flosc_purchased_flow_id', $flow_id);
        }

        // v1.4.6: Fire purchase_completed for any listeners (e.g. FLOSC_Member_Access)
        do_action('flosc_purchase_completed', $user_id, [
            'offer_id' => $offer_id,
            'grants_level' => $offer['grants']['level'] ?? 'member',
            'provider' => 'stripe',
            'transaction_id' => $payment_intent['id'],
            'amount' => $payment_intent['amount'],
            'flow_id' => $flow_id,
            'timestamp' => time(),
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Access granted',
            'access' => $access_manager->get_user_access($user_id),
        ]);
    }

    /**
     * Mint a checkout binding token for the calling browser.
     *
     * The frontend calls this when the buyer begins checkout, before approving
     * payment. The returned token is held in memory and presented back at
     * completion, where flosc_checkout_binding_verify() consumes it as proof the
     * completion request is this same browser. See §5b for the full rationale.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_checkout_binding($request) {
        $session_id = sanitize_text_field((string) $request->get_param('session_id'));
        $flow_id    = sanitize_text_field((string) $request->get_param('flow_id'));
        $offer_id   = sanitize_text_field((string) $request->get_param('offer_id'));
        $token = flosc_checkout_binding_create([
            'session_id' => $session_id,
            'flow_id'    => $flow_id,
            'provider'   => sanitize_text_field((string) $request->get_param('provider')),
            'offer_id'   => $offer_id,
        ]);
        return new WP_REST_Response(['success' => true, 'binding_token' => $token], 200);
    }

    public function handle_webhook($request) {
        $provider_id = $request->get_param('provider');
        $provider = $this->flosc->sale()->get_provider($provider_id);

        if (!$provider) {
            return new WP_Error('invalid_provider', __('Unknown provider', 'flosc'), ['status' => 400]);
        }

        // Collect provider-specific authenticity headers for signature verification.
        // Webhook routes are public by design; crypto verification lives in each provider.
        $headers = [];
        if ($provider_id === 'stripe') {
            $stripe_sig = $request->get_header('stripe-signature');
            if ($stripe_sig) {
                $headers['stripe-signature'] = is_array($stripe_sig) ? $stripe_sig[0] : $stripe_sig;
            }
        } elseif ($provider_id === 'paypal') {
            // Single source of truth: FLOSC_PayPal_Provider::collect_transmission_headers_from_request()
            // (also re-run as fallback inside the provider so this path cannot go unwired).
            if (class_exists('FLOSC_PayPal_Provider')
                && method_exists('FLOSC_PayPal_Provider', 'collect_transmission_headers_from_request')
            ) {
                $headers = FLOSC_PayPal_Provider::collect_transmission_headers_from_request($request);
            }
        }

        $result = $provider->handle_webhook(
            $request->get_body(),
            $headers
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result);
    }

    public function check_access($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['state' => 'visitor', 'level' => 'visitor']);
        }
        
        $user_id = get_current_user_id();
        $access = $this->flosc->sale()->access();
        $member_access = $this->flosc->member_access();
        $flow_id = sanitize_key((string) ($request->get_param('flow_id') ?? ''));
        if ($flow_id === '') {
            $flow = $this->flosc->get_current_flow();
            if (is_array($flow)) {
                $flow_id = $this->flosc->flosc_normalize_flow_stem(
                    (string) ($flow['ivr_file'] ?? $flow['ivr'] ?? $flow['id'] ?? '')
                );
            }
        }
        
        return new WP_REST_Response([
            'state' => $access->get_simple_state($user_id, $flow_id),
            'level' => $member_access->get_access_level($user_id, $flow_id),
            'flow_id' => $flow_id,
            'access' => $access->get_user_access($user_id),
        ]);
    }

    /**
     * Parse UTC timestamp string (ISO or FLOSC MTS) to Unix time.
     * Empty string → null (no bound). Invalid → false.
     *
     * @param string $raw UTC stamp.
     * @return int|null|false
     */
    private function flosc_parse_utc_mts_timestamp($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        // MTS: 2026-07m-27d-UTC08h:26m13s
        if (preg_match('/^(\d{4})-(\d{2})m-(\d{2})d-UTC(\d{2})h:(\d{2})m(\d{2})s$/i', $raw, $m)) {
            return gmmktime((int) $m[4], (int) $m[5], (int) $m[6], (int) $m[2], (int) $m[3], (int) $m[1]);
        }
        try {
            $dt = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
            return $dt->getTimestamp();
        } catch (Exception $e) {
            return false;
        }
    }

}
