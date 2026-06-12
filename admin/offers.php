<?php
/**
 * FLOSC Offers Configuration Tab v1.6.2
 * 
 * Single scrollable page with inline editing.
 * Multi-format support per offer — one offer can render as
 * pill, card, banner, etc., each with independent conditions.
 * 
 * Data model:
 *   offer['display_formats'] = [
 *       'card'   => ['enabled'=>true, 'condition'=>'...', 'timer'=>900, ...],
 *       'pill'   => ['enabled'=>true, 'label'=>'...', 'icon'=>'🎁', ...],
 *       'banner' => ['enabled'=>false],
 *       ...
 *   ]
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('💰', 'Offers');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flow_key = $GLOBALS['flosc_settings_key'] ?? '';
$current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';

$offers_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-offers';
// tab links are rendered into admin HTML below.

echo '<div style="margin:-8px 0 14px; text-align:right;">'
   . '<a href="' . esc_url($offers_docs_url) . '" style="font-size:12px; text-decoration:none; color:#2271b1;">Docs</a>'
   . '</div>';

// ============================================
// SAVE HANDLER — runs at include time (same as delete/toggle handlers below)
// v1.6.5: Removed dead add_action('init',...) — file loads after init fires
// ============================================
function flosc_handle_offer_save() {
    $post = wp_unslash($_POST);

    if (!isset($post['save_offer']) || !wp_verify_nonce(sanitize_text_field($post['flosc_save_offer_nonce'] ?? ''), 'flosc_save_offer')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }

    // §10: accept the posted flow key only if it is a known flow option key.
    // Validate without transforming, so the key matches where settings are stored.
    $fk = sanitize_text_field($post['flosc_flow_key'] ?? '');
    if ($fk === '' || !in_array($fk, flosc_known_flow_option_keys(), true)) {
        return;
    }

    $offer_id = sanitize_text_field($post['offer_id'] ?? '');
    if ($offer_id === 'new') {
        $offer_id = 'offer_' . wp_generate_password(8, false);
    }

    $all_formats = ['card', 'pill', 'compact', 'banner', 'featured', 'text', 'inline-checkout'];
    $display_formats = [];
    foreach ($all_formats as $fmt) {
        $key = str_replace('-', '_', $fmt);
        $display_formats[$fmt] = [
            'enabled'   => !empty($post['fmt_' . $key . '_enabled']),
            'condition' => sanitize_text_field($post['fmt_' . $key . '_condition'] ?? ''),
            'timer'     => intval($post['fmt_' . $key . '_timer'] ?? 0),
        ];
        if ($fmt === 'pill') {
            $display_formats[$fmt]['label'] = sanitize_text_field($post['fmt_pill_label'] ?? '');
            $display_formats[$fmt]['icon'] = sanitize_text_field($post['fmt_pill_icon'] ?? '');
            $display_formats[$fmt]['phrase'] = sanitize_text_field($post['fmt_pill_phrase'] ?? '');
            $display_formats[$fmt]['target_panel'] = sanitize_text_field($post['fmt_pill_target_panel'] ?? 'guest');
        }
        if (in_array($fmt, ['card', 'featured', 'banner'], true)) {
            $display_formats[$fmt]['headline_override'] = sanitize_text_field($post['fmt_' . $key . '_headline'] ?? '');
        }
    }

    if (!array_filter($display_formats, static function ($format) {
        return !empty($format['enabled']);
    })) {
        $display_formats['card']['enabled'] = true;
    }

    $offer_data = [
        'id'             => $offer_id,
        'name'           => sanitize_text_field($post['offer_name'] ?? ''),
        'type'           => sanitize_text_field($post['offer_type'] ?? ''),
        'price'          => floatval($post['offer_price'] ?? 0),
        'original_price' => floatval($post['offer_original_price'] ?? 0),
        'display_price'  => !empty($post['offer_display_price'])
            ? sanitize_text_field($post['offer_display_price'])
            : (floatval($post['offer_price'] ?? 0) > 0 ? '$' . number_format(floatval($post['offer_price'] ?? 0), 2) : 'Free'),
        'headline'       => sanitize_text_field($post['offer_headline'] ?? ''),
        'description'    => sanitize_textarea_field($post['offer_description'] ?? ''),
        'features'       => sanitize_textarea_field($post['offer_features'] ?? ''),
        'cta'            => sanitize_text_field($post['offer_cta'] ?? ''),
        'trigger'        => sanitize_text_field($post['offer_trigger'] ?? ''),
        'condition'      => sanitize_text_field($post['offer_condition'] ?? ''),
        'reveal_phrase'  => sanitize_text_field($post['offer_reveal_phrase'] ?? ''),
        'match_type'     => sanitize_text_field($post['offer_match_type'] ?? 'exact'),
        'grants_level'   => sanitize_key($post['offer_grants_level'] ?? ''),
        'grants'         => [
            'features' => ['full_access'],
            'level'    => sanitize_key($post['offer_grants_level'] ?? ''),
        ],
        'pricing'        => [
            'price'        => floatval($post['offer_price'] ?? 0),
            'currency'     => strtoupper(sanitize_text_field($post['offer_currency'] ?? 'USD')) ?: 'USD',
            'processor'    => sanitize_key($post['offer_processor'] ?? 'paypal'),
            'stripe'       => [
                'price_id'   => sanitize_text_field($post['offer_stripe_price_id'] ?? ''),
                'product_id' => sanitize_text_field($post['offer_stripe_product_id'] ?? ''),
            ],
            'redirect_url' => esc_url_raw($post['offer_redirect_url'] ?? ''),
        ],
        'timer_minutes'   => intval($post['offer_timer'] ?? 0),
        'timer_seconds'   => intval($post['offer_timer'] ?? 0) * 60,
        'guarantee'       => sanitize_text_field($post['offer_guarantee'] ?? ''),
        'preview'         => [
            'icon'    => sanitize_text_field($post['offer_icon'] ?? '⭐'),
            'badge'   => sanitize_text_field($post['offer_badge'] ?? ''),
            'savings' => sanitize_text_field($post['offer_savings'] ?? ''),
        ],
        'status'          => isset($post['offer_active']) ? 'active' : 'draft',
        'active'          => isset($post['offer_active']),
        'updated'         => current_time('mysql'),
    ];

    $fs = get_option($fk, []);
    $all_offers = $fs['offers'] ?? [];
    $existing_offer = $all_offers[$offer_id] ?? null;

    if ($existing_offer) {
        $offer_data['conversions'] = $existing_offer['conversions'] ?? 0;
        $offer_data['views'] = $existing_offer['views'] ?? 0;
        $offer_data['created'] = $existing_offer['created'] ?? current_time('mysql');
        if (strtolower((string)($existing_offer['status'] ?? '')) !== 'draft' && empty($post['offer_active'])) {
            $offer_data['status'] = 'inactive';
        }
    }

    $offer_data = array_merge($offer_data, [
        'display_formats' => $display_formats,
        'pill_label'      => sanitize_text_field($post['fmt_pill_label'] ?? ''),
        'pill_icon'       => sanitize_text_field($post['fmt_pill_icon'] ?? ''),
        'pill_phrase'     => sanitize_text_field($post['fmt_pill_phrase'] ?? ''),
    ]);

    $all_offers[$offer_id] = $offer_data;
    $fs['offers'] = $all_offers;
    update_option($fk, $fs);

    $ivr = sanitize_file_name($post['flosc_ivr'] ?? '');
    wp_safe_redirect(esc_url_raw(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($ivr) . '&tab=offers&saved=1')));
    exit;
}
flosc_handle_offer_save(); // v1.6.5: Execute at include time
$get = wp_unslash($_GET);

// Handle delete
if (isset($_GET['delete_offer']) && isset($_GET['_wpnonce'])) {
    $get = wp_unslash($_GET);
    $del_id = sanitize_text_field($get['delete_offer'] ?? '');
    if (wp_verify_nonce(sanitize_text_field($get['_wpnonce'] ?? ''), 'flosc_delete_offer_' . $del_id) && current_user_can('manage_options')) {
        if ($flow_key) {
            $fs = get_option($flow_key, []);
            $all = $fs['offers'] ?? [];
            unset($all[$del_id]);
            $fs['offers'] = $all;
            update_option($flow_key, $fs);
            $flow_settings = $fs;
        }
        add_settings_error('flosc_settings', 'offer_deleted', 'Offer deleted.', 'success');
    }
}

// Handle toggle status
if (isset($_GET['toggle_status'])) {
    // Task 6: Verify nonce and capability
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized action.', 'Insufficient permissions', ['response' => 403]);
    }
    $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'flosc_toggle_status')) {
        wp_die('Nonce verification failed.', 'Invalid token', ['response' => 403]);
    }
    
    $get = wp_unslash($_GET);
    $toggle_id = sanitize_text_field($get['toggle_status'] ?? '');
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $all = $fs['offers'] ?? [];
        if (isset($all[$toggle_id])) {
            $current_status = strtolower((string)($all[$toggle_id]['status'] ?? 'active'));
            $currently_active = !empty($all[$toggle_id]['active']);

            if ($current_status === 'draft') {
                $all[$toggle_id]['active'] = true;
                $all[$toggle_id]['status'] = 'active';
            } elseif ($currently_active) {
                $all[$toggle_id]['active'] = false;
                $all[$toggle_id]['status'] = 'inactive';
            } else {
                $all[$toggle_id]['active'] = true;
                $all[$toggle_id]['status'] = 'active';
            }
            $fs['offers'] = $all;
            update_option($flow_key, $fs);
            $flow_settings = $fs;
        }
    }
}

// Handle explicit status set (draft|inactive|active)
if (isset($_GET['set_status']) && isset($_GET['status'])) {
    // Task 6: Verify nonce and capability
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized action.', 'Insufficient permissions', ['response' => 403]);
    }
    $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');
    if (!wp_verify_nonce($nonce, 'flosc_set_status')) {
        wp_die('Nonce verification failed.', 'Invalid token', ['response' => 403]);
    }
    
    $get = wp_unslash($_GET);
    $target_id = sanitize_text_field($get['set_status'] ?? '');
    $target_status = sanitize_key($get['status'] ?? '');
    if (in_array($target_status, ['draft', 'inactive', 'active'], true) && $flow_key) {
        $fs = get_option($flow_key, []);
        $all = $fs['offers'] ?? [];
        if (isset($all[$target_id])) {
            $all[$target_id]['status'] = $target_status;
            $all[$target_id]['active'] = ($target_status === 'active');
            $fs['offers'] = $all;
            update_option($flow_key, $fs);
            $flow_settings = $fs;
        }
    }
}

// Load offers
$flow_id_for_offers = null;
if (!empty($flow_key)) {
    $flow_id_for_offers = str_replace('flosc_flow_', '', $flow_key);
}
$offers = flosc()->sale()->offers()->get_all_offers($flow_id_for_offers);
$get = wp_unslash($_GET);
$expand_id = $get['edit_offer'] ?? $get['expand'] ?? null;

// All 7 display formats with metadata
$all_format_meta = [
    'card'            => ['icon' => '🃏', 'label' => 'Card',            'desc' => 'Rich card with headline, features, pricing, CTA button'],
    'pill'            => ['icon' => '💊', 'label' => 'Pill',            'desc' => 'Compact clickable pill in PromptPanel (AutoPrompt-style)'],
    'compact'         => ['icon' => '📋', 'label' => 'Compact',         'desc' => 'Minimal one-line offer with price and CTA'],
    'banner'          => ['icon' => '🏗️', 'label' => 'Banner',          'desc' => 'Wide banner with gradient background and countdown'],
    'featured'        => ['icon' => '⭐', 'label' => 'Featured',        'desc' => 'Premium card with badge, savings callout, guarantee'],
    'text'            => ['icon' => '📝', 'label' => 'Text',            'desc' => 'Plain text offer mention within conversation'],
    'inline-checkout' => ['icon' => '🛒', 'label' => 'Inline Checkout', 'desc' => 'Full checkout form embedded in chat'],
];
?>

</form>

<h2>Offers & Pricing — All Offers</h2>
<p>Create and manage product offers. Each offer can appear in <strong>multiple display formats</strong> — pill in the panel, card in chat, banner on timer, etc.</p>

<?php if (isset($get['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>Offer saved successfully.</p></div>
<?php endif; ?>

<!-- Styles in assets/css/flosc-admin.css -->

<?php
// ============================================
// ACTIVE OFFERS SUMMARY
// ============================================
$active_offers = [];
foreach ($offers as $offer_id => $offer) {
    $status = strtolower((string)($offer['status'] ?? ''));
    $is_active = !empty($offer['active']) && $status !== 'draft';
    if (!$is_active) {
        continue;
    }

    $formats = [];
    $df = $offer['display_formats'] ?? [];
    foreach ($all_format_meta as $fmt_key => $fmt_meta) {
        if (!empty($df[$fmt_key]['enabled'])) {
            $formats[] = $fmt_meta['label'];
        }
    }
    if (empty($formats)) {
        $formats[] = ucfirst((string)($offer['display_format'] ?? 'card'));
    }

    $active_offers[] = [
        'id' => (string)($offer['id'] ?? $offer_id),
        'name' => (string)($offer['name'] ?? $offer_id),
        'formats' => implode(', ', $formats),
        'condition' => (string)($offer['condition'] ?? 'always'),
        'cta' => (string)($offer['cta'] ?? ''),
        'status' => $status === '' ? 'active' : $status,
    ];
}
?>

<?php if (!empty($active_offers)): ?>
<div class="flosc-pills-summary">
    <div class="flosc-pills-summary-header">
        <strong>Active Offers</strong>
        <span class="flosc-pills-count"><?php echo count($active_offers); ?> active</span>
    </div>
    <table class="flosc-pills-summary-table">
        <thead>
            <tr>
                <th>Offer</th>
                <th>Formats</th>
                <th>CTA</th>
                <th>Condition</th>
                <th>Edit</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($active_offers as $item): ?>
            <tr>
                <td class="pill-source"><?php echo esc_html($item['name']); ?></td>
                <td><?php echo esc_html($item['formats']); ?></td>
                <td><code><?php echo esc_html($item['cta'] ?: '(none)'); ?></code></td>
                <td><code><?php echo esc_html($item['condition'] ?: 'always'); ?></code></td>
                <td><a class="button button-small" href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&edit_offer=' . urlencode($item['id']))); ?>">Edit</a></td>
                <td>
                    <select onchange="if(this.value){window.location=this.value;}">
                        <option value="">Set...</option>
                        <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($item['id']) . '&status=draft&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($item['status'], 'draft'); ?>>draft</option>
                        <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($item['id']) . '&status=inactive&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($item['status'], 'inactive'); ?>>inactive</option>
                        <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($item['id']) . '&status=active&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($item['status'], 'active'); ?>>active</option>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (empty($offers) && $expand_id !== 'new'): ?>
<div style="background: #e7f3ff; border-left: 4px solid #2196f3; padding: 20px; border-radius: 4px; margin-bottom: 20px;">
    <strong>💡 No offers yet.</strong>
    <p style="margin: 8px 0 0;">Click "+ Create New Offer" below to set up your first product offer with multi-format display support.</p>
</div>
<?php else: ?>
<p style="margin-bottom: 12px; color: #667;">
    <strong><?php echo count($offers); ?></strong> offer(s) configured
</p>
<?php endif; ?>

<!-- ============================================ -->
<!-- OFFERS LIST — SCROLLABLE ACCORDION -->
<!-- ============================================ -->
<?php foreach ($offers as $offer):
    $is_open = ($expand_id === $offer['id']);
    $raw_status = strtolower((string)($offer['status'] ?? ''));
    $is_active = !empty($offer['active']) && $raw_status !== 'draft';
    if ($raw_status === 'draft') {
        $status_label = '○ Draft';
        $status_class = 'draft';
    } elseif ($is_active) {
        $status_label = '● Active';
        $status_class = 'active';
    } else {
        $status_label = '◐ Inactive';
        $status_class = 'draft';
    }
    $status_for_select = $raw_status !== '' ? $raw_status : ($is_active ? 'active' : 'inactive');
    $conversions = $offer['conversions'] ?? 0;
    $views = $offer['views'] ?? 0;
    $rate = $views > 0 ? round(($conversions / $views) * 100, 1) : 0;
    $safe_id = esc_attr($offer['id']);
    
    // Determine enabled formats
    $df = $offer['display_formats'] ?? [];
    $enabled_fmts = [];
    foreach ($all_format_meta as $fid => $fm) {
        if (!empty($df[$fid]['enabled'])) $enabled_fmts[] = $fm['icon'] . ' ' . $fm['label'];
    }
    // Backward compat: old single display_format
    if (empty($enabled_fmts) && !empty($offer['display_format'])) {
        $bf = $offer['display_format'];
        if (isset($all_format_meta[$bf])) $enabled_fmts[] = $all_format_meta[$bf]['icon'] . ' ' . $all_format_meta[$bf]['label'];
    }
    if (empty($enabled_fmts)) $enabled_fmts[] = '🃏 Card';
?>
<div class="flosc-offer-card <?php echo esc_attr( $is_open ? 'is-open' : '' ); ?>" id="offer-<?php echo esc_attr( $safe_id ); ?>">
    <div class="flosc-offer-header" onclick="floscToggleOffer('<?php echo esc_js($offer['id']); ?>')">
        <span class="toggle">▶</span>
        <span class="offer-name"><?php echo esc_html($offer['name']); ?></span>
        <span class="offer-price">
            <?php if (!empty($offer['original_price']) && $offer['original_price'] != $offer['price']): ?>
                <span class="original">$<?php echo esc_html(number_format($offer['original_price'], 2)); ?></span>
            <?php endif; ?>
            $<?php echo esc_html(number_format($offer['price'] ?? 0, 2)); ?>
        </span>
        <span class="offer-status <?php echo esc_attr($status_class); ?>">
            <?php echo esc_html($status_label); ?>
        </span>
        <span class="offer-formats">
            <?php foreach ($enabled_fmts as $ef): ?>
                <span class="fmt-badge"><?php echo esc_html($ef); ?></span>
            <?php endforeach; ?>
        </span>
        <span class="offer-stats" onclick="event.stopPropagation();">
            <select onchange="if(this.value){window.location=this.value;}">
                <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($offer['id']) . '&status=draft&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($status_for_select, 'draft'); ?>>draft</option>
                <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($offer['id']) . '&status=inactive&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($status_for_select, 'inactive'); ?>>inactive</option>
                <option value="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&set_status=' . urlencode($offer['id']) . '&status=active&_wpnonce=' . wp_create_nonce('flosc_set_status'))); ?>" <?php selected($status_for_select, 'active'); ?>>active</option>
            </select>
        </span>
    </div>
    
    <div class="flosc-offer-editor">
        <?php flosc_render_offer_editor_v2($offer, $flow_key, $current_ivr, $all_format_meta); ?>
    </div>
</div>
<?php endforeach; ?>

<!-- NEW OFFER (inline) -->
<?php if ($expand_id === 'new'): ?>
<div class="flosc-offer-card is-open" id="offer-new">
    <div class="flosc-offer-header">
        <span class="toggle" style="transform: rotate(90deg);">▶</span>
        <span class="offer-name" style="color: #2271b1;">✨ New Offer</span>
    </div>
    <div class="flosc-offer-editor" style="display: block;">
        <?php flosc_render_offer_editor_v2(null, $flow_key, $current_ivr, $all_format_meta); ?>
    </div>
</div>
<?php endif; ?>

<button type="button" class="flosc-add-offer-btn" onclick="window.location='<?php echo esc_js( esc_url( admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&edit_offer=new') ) ); ?>'">
    + Create New Offer
</button>

<!-- ============================================ -->
<!-- DEMO OFFERS — create as drafts, then customize & activate -->
<!-- ============================================ -->
<?php
$demo_offers = [
    [
        'key'              => 'post_quiz_oto',
        'color'            => '#f59e0b',
        'emoji'            => '🎯',
        'title'            => 'Post-Quiz OTO',
        'subtitle'         => 'One-time offer shown right after quiz — urgency + discount. Card + pill. 15 min timer.',
        'offer_name'       => 'Full Access — Quiz Special',
        'offer_headline'   => 'You just found your pronunciation gaps. Now fix them.',
        'offer_description'=> "You've identified exactly which American English sounds trip you up. LeSAEp's full course gives you targeted lessons for every gap — so you stop guessing and start sounding natural.",
        'offer_features'   => "65+ pronunciation lessons\nAI-powered chat coach available 24/7\nAll 10 sound modules (R, TH, vowels, stress, linking)\nUnlimited quiz attempts\nInstant access — start today",
        'offer_cta'        => 'Get Full Access Now',
        'offer_price'      => 49,
        'offer_original'   => 99,
        'offer_timer'      => 15,
        'offer_guarantee'  => '30-day money-back guarantee — no questions asked.',
        'offer_icon'       => '🎯',
        'offer_badge'      => 'Quiz Special',
        'offer_savings'    => 'Save $50 today',
        'offer_grants'     => 'lesaep_learner',
        'fmt_card'         => true,
        'fmt_pill'         => true,
        'pill_label'       => '🎯 Get full access — $49',
        'pill_icon'        => '🎯',
        'pill_phrase'      => 'Get full access',
        'pill_panel'       => 'guest',
    ],
    [
        'key'              => 'premium_annual',
        'color'            => '#8b5cf6',
        'emoji'            => '💎',
        'title'            => 'Premium Annual Access',
        'subtitle'         => 'Main product offer — full course, featured display. No timer pressure.',
        'offer_name'       => 'LeSAEp Pronunciation Mastery',
        'offer_headline'   => 'Master American English Pronunciation — Complete System',
        'offer_description'=> "The complete LeSAEp pronunciation program. Every lesson, every sound, every accent pattern — with an AI coach that knows exactly where you are and what you need next.",
        'offer_features'   => "65+ structured lessons across all 10 sound categories\nPersonalized AI coaching based on your quiz results\nAudio examples for every sound and word\nReal-world practice phrases and sentences\nProgress tracking and quiz retakes\nAccess on any device, anytime",
        'offer_cta'        => 'Start Mastering Pronunciation',
        'offer_price'      => 297,
        'offer_original'   => 497,
        'offer_timer'      => 0,
        'offer_guarantee'  => '30-day money-back guarantee.',
        'offer_icon'       => '💎',
        'offer_badge'      => 'Most Popular',
        'offer_savings'    => 'Save $200',
        'offer_grants'     => 'lesaep_learner',
        'fmt_card'         => true,
        'fmt_pill'         => true,
        'fmt_featured'     => true,
        'pill_label'       => '💎 LeSAEp Full Access — $297',
        'pill_icon'        => '💎',
        'pill_phrase'      => 'Tell me about full access',
        'pill_panel'       => 'guest',
    ],
    [
        'key'              => 'high_scorer_bundle',
        'color'            => '#10b981',
        'emoji'            => '📦',
        'title'            => 'High Scorer Bundle',
        'subtitle'         => 'For learners who scored 70%+ — "You\'re already good, let\'s make you great." Featured + 24h timer.',
        'offer_name'       => 'LeSAEp Advanced Bundle',
        'offer_headline'   => 'You scored in the top 30%. Here\'s how to go further.',
        'offer_description'=> "Your quiz shows you've already mastered the basics. The Advanced Bundle gives you the fine-tuning that separates good pronunciation from native-level fluency — intonation, rhythm, connected speech, and accent reduction at speed.",
        'offer_features'   => "Everything in full access\nAdvanced connected speech modules\nIntonation and rhythm masterclass\nSpeed-listening practice\nNative speaker comparison exercises\nPriority AI coaching sessions",
        'offer_cta'        => 'Claim My Advanced Bundle',
        'offer_price'      => 397,
        'offer_original'   => 597,
        'offer_timer'      => 1440,
        'offer_guarantee'  => '30-day money-back guarantee.',
        'offer_icon'       => '📦',
        'offer_badge'      => 'Advanced',
        'offer_savings'    => 'Save $200 — 24h only',
        'offer_grants'     => 'lesaep_learner',
        'fmt_card'         => true,
        'fmt_featured'     => true,
        'pill_label'       => '📦 Advanced Bundle — $397',
        'pill_icon'        => '📦',
        'pill_phrase'      => 'Tell me about the advanced bundle',
        'pill_panel'       => 'guest',
    ],
    [
        'key'              => 'free_lesson',
        'color'            => '#06b6d4',
        'emoji'            => '🎁',
        'title'            => 'Free Lesson Unlock',
        'subtitle'         => 'Lead magnet — free access to the lesson matching their biggest quiz gap. Card + pill.',
        'offer_name'       => 'Your Free Pronunciation Lesson',
        'offer_headline'   => 'Get your first lesson free — matched to your quiz results.',
        'offer_description'=> "Based on your quiz, we know exactly which sound gives you the most trouble. Get the lesson for that specific sound completely free — no card required.",
        'offer_features'   => "1 personalized lesson — matched to your quiz\nFull lesson access (video + exercises)\nAI coach to answer your questions\nNo credit card required",
        'offer_cta'        => 'Unlock My Free Lesson',
        'offer_price'      => 0,
        'offer_original'   => 0,
        'offer_timer'      => 0,
        'offer_guarantee'  => '',
        'offer_icon'       => '🎁',
        'offer_badge'      => 'Free',
        'offer_savings'    => '',
        'offer_grants'     => 'lesaep_guest',
        'fmt_card'         => true,
        'fmt_pill'         => true,
        'pill_label'       => '🎁 Unlock my free lesson',
        'pill_icon'        => '🎁',
        'pill_phrase'      => 'Unlock my free lesson',
        'pill_panel'       => 'visitor',
    ],
];
?>
<details style="margin-top:30px;" open>
    <summary style="cursor:pointer;font-size:15px;font-weight:600;padding:10px 0;">
        🎯 Demo Offers — create as drafts, customize, then activate
    </summary>
    <div style="margin-top:10px;padding:16px;background:#f9f9f9;border-radius:6px;">
        <p class="description" style="margin-bottom:16px;">
            Click <strong>Create as Draft →</strong> to add the offer to your list. It will appear above as a draft — open it, update the price and details for your product, then activate.
        </p>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ( $demo_offers as $demo ): ?>
        <div style="background:#fff;border:1px solid #e0e0e0;border-left:4px solid <?php echo esc_attr( $demo['color'] ); ?>;border-radius:5px;padding:14px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
            <div style="flex:1;">
                <strong style="font-size:13px;"><?php echo esc_html( $demo['emoji'] . ' ' . $demo['title'] ); ?></strong>
                <span style="display:block;font-size:12px;color:#50575e;margin-top:3px;"><?php echo esc_html( $demo['subtitle'] ); ?></span>
            </div>
            <form method="post" style="flex-shrink:0;">
                <?php wp_nonce_field( 'flosc_save_offer', 'flosc_save_offer_nonce' ); ?>
                <input type="hidden" name="save_offer"            value="1">
                <input type="hidden" name="offer_id"              value="new">
                <input type="hidden" name="flosc_flow_key"        value="<?php echo esc_attr( $flow_key ); ?>">
                <input type="hidden" name="flosc_ivr"             value="<?php echo esc_attr( $current_ivr ); ?>">
                <input type="hidden" name="offer_name"            value="<?php echo esc_attr( $demo['offer_name'] ); ?>">
                <input type="hidden" name="offer_type"            value="one_time">
                <input type="hidden" name="offer_price"           value="<?php echo esc_attr( $demo['offer_price'] ); ?>">
                <input type="hidden" name="offer_original_price"  value="<?php echo esc_attr( $demo['offer_original'] ); ?>">
                <input type="hidden" name="offer_headline"        value="<?php echo esc_attr( $demo['offer_headline'] ); ?>">
                <input type="hidden" name="offer_description"     value="<?php echo esc_attr( $demo['offer_description'] ); ?>">
                <input type="hidden" name="offer_features"        value="<?php echo esc_attr( $demo['offer_features'] ); ?>">
                <input type="hidden" name="offer_cta"             value="<?php echo esc_attr( $demo['offer_cta'] ); ?>">
                <input type="hidden" name="offer_trigger"         value="always">
                <input type="hidden" name="offer_condition"       value="">
                <input type="hidden" name="offer_reveal_phrase"   value="">
                <input type="hidden" name="offer_match_type"      value="exact">
                <input type="hidden" name="offer_grants_level"    value="<?php echo esc_attr( $demo['offer_grants'] ); ?>">
                <input type="hidden" name="offer_timer"           value="<?php echo esc_attr( $demo['offer_timer'] ); ?>">
                <input type="hidden" name="offer_currency"        value="USD">
                <input type="hidden" name="offer_processor"       value="paypal">
                <input type="hidden" name="offer_stripe_price_id" value="">
                <input type="hidden" name="offer_stripe_product_id" value="">
                <input type="hidden" name="offer_redirect_url"    value="">
                <input type="hidden" name="offer_guarantee"       value="<?php echo esc_attr( $demo['offer_guarantee'] ); ?>">
                <input type="hidden" name="offer_icon"            value="<?php echo esc_attr( $demo['offer_icon'] ); ?>">
                <input type="hidden" name="offer_badge"           value="<?php echo esc_attr( $demo['offer_badge'] ); ?>">
                <input type="hidden" name="offer_savings"         value="<?php echo esc_attr( $demo['offer_savings'] ); ?>">
                <?php if ( ! empty( $demo['fmt_card'] ) ): ?>
                <input type="hidden" name="fmt_card_enabled"      value="1">
                <?php endif; ?>
                <?php if ( ! empty( $demo['fmt_featured'] ) ): ?>
                <input type="hidden" name="fmt_featured_enabled"  value="1">
                <?php endif; ?>
                <?php if ( ! empty( $demo['fmt_pill'] ) ): ?>
                <input type="hidden" name="fmt_pill_enabled"      value="1">
                <input type="hidden" name="fmt_pill_label"        value="<?php echo esc_attr( $demo['pill_label'] ); ?>">
                <input type="hidden" name="fmt_pill_icon"         value="<?php echo esc_attr( $demo['pill_icon'] ); ?>">
                <input type="hidden" name="fmt_pill_phrase"       value="<?php echo esc_attr( $demo['pill_phrase'] ); ?>">
                <input type="hidden" name="fmt_pill_target_panel" value="<?php echo esc_attr( $demo['pill_panel'] ); ?>">
                <?php endif; ?>
                <!-- status: no offer_active input = saved as draft -->
                <button type="submit" class="button button-small" style="white-space:nowrap;">
                    Create as Draft →
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        </div>
        <div style="background:#e7f3ff;border-left:4px solid #2196f3;padding:12px 15px;margin-top:16px;font-size:12px;">
            <strong>💡 Tip:</strong> Update the price and headline to match your actual product before activating. Keep the structure — it's already optimized for conversion.
        </div>
    </div>
</details>

<?php ob_start(); ?>
function floscToggleOffer(id) {
    const card = document.getElementById('offer-' + id);
    if (card) card.classList.toggle('is-open');
}
function floscToggleFmt(checkbox) {
    const card = checkbox.closest('.flosc-fmt-card');
    card.classList.toggle('is-enabled', checkbox.checked);
}
function floscToggleProcessorFields(safeId, processor) {
    const processors = ['paypal', 'stripe', 'redirect'];
    processors.forEach(function(p) {
        const el = document.getElementById('proc-' + p + '-' + safeId);
        if (el) el.style.display = (p === processor) ? 'block' : 'none';
    });
}
// Condition reference: click to copy to clipboard and nearest condition input
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.flosc-condition-copy');
    if (!btn) return;
    const condition = btn.dataset.condition;
    if (!condition) return;
    
    // Copy to clipboard
    navigator.clipboard.writeText(condition).then(function() {
        const original = btn.innerHTML;
        btn.innerHTML = '<code>✓ Copied!</code>';
        btn.classList.add('copied');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('copied');
        }, 1500);
    });
    
    // Also paste into the nearest offer's global condition input
    const offerCard = btn.closest('.flosc-offer-editor');
    if (offerCard) {
        const condInput = offerCard.querySelector('input[name="offer_condition"]');
        if (condInput) {
            // Append with && if there's already a value
            if (condInput.value.trim()) {
                condInput.value = condInput.value.trim() + ' && ' + condition;
            } else {
                condInput.value = condition;
            }
            condInput.focus();
            condInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});
document.addEventListener('DOMContentLoaded', function() {
    const open = document.querySelector('.flosc-offer-card.is-open');
    if (open) open.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<?php
// ============================================
// OFFER EDITOR RENDER FUNCTION
// ============================================
function flosc_render_offer_editor_v2($offer, $flow_key, $current_ivr, $all_format_meta) {
    $is_new = empty($offer);
    $offer_id = $offer['id'] ?? 'new';
    $safe_id = esc_attr($offer_id);
    
    // Merge display_formats with defaults
    $df = $offer['display_formats'] ?? [];
    // Backward compat
    if (empty($df) && !empty($offer['display_format'])) {
        $df[$offer['display_format']] = ['enabled' => true];
    }
    if (empty($df) && $is_new) {
        $df['card'] = ['enabled' => true];
    }
?>
    <form method="post">
        <?php wp_nonce_field('flosc_save_offer', 'flosc_save_offer_nonce'); ?>
        <input type="hidden" name="offer_id" value="<?php echo esc_attr( $safe_id ); ?>">
        <input type="hidden" name="flosc_flow_key" value="<?php echo esc_attr($flow_key); ?>">
        <input type="hidden" name="flosc_ivr" value="<?php echo esc_attr($current_ivr); ?>">
        
        <!-- CORE OFFER DATA -->
        <div class="flosc-offer-section-label">📦 Offer Details</div>
        <table class="form-table" style="margin: 0;">
            <tr>
                <th style="width: 150px;"><label>Offer Name</label></th>
                <td><input type="text" name="offer_name" value="<?php echo esc_attr($offer['name'] ?? ''); ?>" class="large-text" required placeholder="Premium Annual - 50% Off OTO"></td>
            </tr>
            <tr>
                <th><label>Type</label></th>
                <td>
                    <select name="offer_type">
                        <?php foreach (['one-time'=>'One-Time Purchase','subscription'=>'Recurring Subscription','bundle'=>'Bundle','upsell'=>'Upsell / Cross-sell'] as $v => $l): ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected($offer['type'] ?? '', $v); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Pricing</th>
                <td>
                    <label>Price: $<input type="number" name="offer_price" value="<?php echo esc_attr($offer['price'] ?? ''); ?>" step="0.01" min="0" class="small-text" id="offer-price-<?php echo esc_attr( $safe_id ); ?>"></label>
                    <label style="margin-left: 16px;">Was: $<input type="number" name="offer_original_price" value="<?php echo esc_attr($offer['original_price'] ?? ''); ?>" step="0.01" min="0" class="small-text"></label>
                    <p class="description">Enter 0 for a free offer.</p>
                    <div style="margin-top: 8px;">
                        <label>Button price label: <input type="text" name="offer_display_price" value="<?php echo esc_attr($offer['display_price'] ?? ''); ?>" class="regular-text" placeholder="e.g. $100/yr or $10/mo"></label>
                        <p class="description">Shown on the CTA button. Leave empty to auto-generate from price.</p>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label>Payment Processor</label></th>
                <td>
                    <?php
                    $proc = $offer['pricing']['processor'] ?? 'paypal';
                    ?>
                    <select name="offer_processor" id="offer-processor-<?php echo esc_attr( $safe_id ); ?>"
                            onchange="floscToggleProcessorFields('<?php echo esc_js($safe_id); ?>', this.value)">
                        <option value="paypal"   <?php selected($proc, 'paypal'); ?>>PayPal</option>
                        <option value="stripe"   <?php selected($proc, 'stripe'); ?>>Stripe</option>
                        <option value="free"     <?php selected($proc, 'free'); ?>>Free (no payment)</option>
                        <option value="redirect" <?php selected($proc, 'redirect'); ?>>External / Redirect URL</option>
                    </select>
                    <p class="description">Choose how this offer is purchased. PayPal and Stripe require payment credentials in FLOSC Settings.</p>

                    <!-- PayPal fields (shown when processor = paypal) -->
                    <div id="proc-paypal-<?php echo esc_attr( $safe_id ); ?>" class="flosc-proc-fields" style="<?php echo esc_attr( $proc !== 'paypal' ? 'display:none;' : '' ); ?> margin-top:10px;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:4px 8px 4px 0; width:120px;"><label>Currency</label></td>
                                <td>
                                    <select name="offer_currency" style="width:100px;">
                                        <?php foreach (['USD','EUR','GBP','CAD','AUD'] as $cur): ?>
                                            <option value="<?php echo esc_attr( $cur ); ?>" <?php selected($offer['pricing']['currency'] ?? 'USD', $cur); ?>><?php echo esc_html( $cur ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="description" style="margin-left:8px;">PayPal will charge the price above in this currency.</span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Stripe fields (shown when processor = stripe) -->
                    <div id="proc-stripe-<?php echo esc_attr( $safe_id ); ?>" class="flosc-proc-fields" style="<?php echo esc_attr( $proc !== 'stripe' ? 'display:none;' : '' ); ?> margin-top:10px;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:4px 8px 4px 0; width:120px;"><label>Stripe Price ID</label></td>
                                <td>
                                    <input type="text" name="offer_stripe_price_id"
                                           value="<?php echo esc_attr($offer['pricing']['stripe']['price_id'] ?? ''); ?>"
                                           style="width:100%;" placeholder="price_1ABC...">
                                    <p class="description">From your <a href="https://dashboard.stripe.com/products" target="_blank">Stripe Dashboard → Products</a>. Format: <code>price_1...</code></p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:4px 8px 4px 0;"><label>Stripe Product ID</label></td>
                                <td>
                                    <input type="text" name="offer_stripe_product_id"
                                           value="<?php echo esc_attr($offer['pricing']['stripe']['product_id'] ?? ''); ?>"
                                           style="width:100%;" placeholder="prod_1ABC... (optional)">
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Redirect fields (shown when processor = redirect) -->
                    <div id="proc-redirect-<?php echo esc_attr( $safe_id ); ?>" class="flosc-proc-fields" style="<?php echo esc_attr( $proc !== 'redirect' ? 'display:none;' : '' ); ?> margin-top:10px;">
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="padding:4px 8px 4px 0; width:120px;"><label>Checkout URL</label></td>
                                <td>
                                    <input type="url" name="offer_redirect_url"
                                           value="<?php echo esc_attr($offer['pricing']['redirect_url'] ?? ''); ?>"
                                           style="width:100%;" placeholder="https://checkout.example.com/buy">
                                    <p class="description">User will be redirected to this URL when they click the CTA.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
            <tr>
                <th><label>Sales Headline</label></th>
                <td><input type="text" name="offer_headline" value="<?php echo esc_attr($offer['headline'] ?? ''); ?>" class="large-text" placeholder="Get Full Access - Limited Time 50% Off!"></td>
            </tr>
            <tr>
                <th><label>Description</label></th>
                <td><textarea name="offer_description" rows="4" class="large-text"><?php echo esc_textarea($offer['description'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><label>Features (one/line)</label></th>
                <td><textarea name="offer_features" rows="4" class="large-text" placeholder="Complete access to all 50+ lessons&#10;AI-powered feedback&#10;Certificate of completion"><?php echo esc_textarea($offer['features'] ?? ''); ?></textarea></td>
            </tr>
            <tr>
                <th><label>CTA Button</label></th>
                <td><input type="text" name="offer_cta" value="<?php echo esc_attr($offer['cta'] ?? 'Get Access Now'); ?>" class="regular-text"></td>
            </tr>
        </table>
        
        <!-- TRIGGER & CONDITIONS -->
        <div class="flosc-offer-section-label">⚡ Trigger & Conditions</div>
        <table class="form-table" style="margin: 0;">
            <tr>
                <th style="width: 150px;"><label>Show When</label></th>
                <td>
                    <select name="offer_trigger">
                        <?php foreach (['manual'=>'Manual (pill/phrase only)','quiz_complete'=>'Quiz Completed','lesson_complete'=>'First Lesson Completed','login_phase'=>'User Enters Login Phase','inactivity'=>'After 7 Days Inactivity'] as $v => $l): ?>
                            <option value="<?php echo esc_attr( $v ); ?>" <?php selected($offer['trigger'] ?? '', $v); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Condition</label></th>
                <td>
                    <input type="text" name="offer_condition" value="<?php echo esc_attr($offer['condition'] ?? ''); ?>" class="large-text" placeholder="score >= 70 && !purchased">
                    <p class="description">Global condition for ALL formats. Per-format conditions below can override. <a href="#" onclick="document.getElementById('flosc-condition-ref-<?php echo esc_js( $safe_id ); ?>').open = !document.getElementById('flosc-condition-ref-<?php echo esc_js( $safe_id ); ?>').open; return false;">📖 View all available conditions</a></p>
                </td>
            </tr>
            <tr>
                <th><label>Timer (min)</label></th>
                <td>
                    <input type="number" name="offer_timer" value="<?php echo esc_attr($offer['timer_minutes'] ?? ''); ?>" min="0" class="small-text" placeholder="15">
                    <span class="description" style="margin-left: 8px;">Global default. Per-format timers can override.</span>
                </td>
            </tr>
            <tr>
                <th><label>Grants Level</label></th>
                <td>
                    <?php
                    // v8.1.0: Dropdown from Member Levels registry (single source of truth)
                    $ml_registry = $flow_settings['member_levels'] ?? [];
                    $current_level = $offer['grants_level'] ?? '';
                    ?>
                    <select name="offer_grants_level" class="regular-text">
                        <option value="">— None —</option>
                        <?php foreach ( $ml_registry as $lk => $lv ) :
                            $slug  = $lv['slug'] ?? $lk;
                            $label = ( $lv['name'] ?? '' ) ?: $slug;
                            if ( empty( $slug ) ) continue;
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current_level, $slug ); ?>>
                                <?php echo esc_html( $label ); ?> (<?php echo esc_html( $slug ); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Select a level from the <a href="?page=flosc-settings&ivr=<?php echo urlencode($current_ivr); ?>&tab=member-levels">Member Levels</a> tab.</p>
                </td>
            </tr>
        </table>
        
        <!-- REVEAL PHRASE -->
        <div class="flosc-offer-section-label">💬 Reveal Phrase — Trigger This Offer by Text</div>
        <table class="form-table" style="margin: 0;">
            <tr>
                <th style="width: 150px;"><label>Reveal Phrase</label></th>
                <td>
                    <input type="text" name="offer_reveal_phrase" value="<?php echo esc_attr($offer['reveal_phrase'] ?? ''); ?>" class="large-text" placeholder="Tell me about the full access plan">
                    <p class="description">When a user types this phrase (or clicks a pill that sends it), this offer will be displayed. Leave empty to disable phrase-triggering.</p>
                </td>
            </tr>
            <tr>
                <th><label>Match Type</label></th>
                <td>
                    <select name="offer_match_type">
                        <option value="exact" <?php selected($offer['match_type'] ?? 'exact', 'exact'); ?>>Exact Match — user's text must match the phrase exactly (case-insensitive)</option>
                        <option value="ai_interpretation" <?php selected($offer['match_type'] ?? '', 'ai_interpretation'); ?>>AI Interpretation — AI decides if user's intent matches this offer's phrase</option>
                    </select>
                    <p class="description">
                        <strong>Exact Match:</strong> User types "Tell me about full access" → phrase is "Tell me about full access" → match. Fast, reliable, no AI cost.<br>
                        <strong>AI Interpretation:</strong> User types "how much does premium cost?" → AI recognizes this is about your "Full Access" offer → match. Flexible, uses AI credits.
                    </p>
                </td>
            </tr>
        </table>
        
        <!-- CONDITION REFERENCE (collapsible) -->
        <details class="flosc-condition-reference" id="flosc-condition-ref-<?php echo esc_attr( $safe_id ); ?>">
            <summary>📖 Condition Reference — All Available Conditions (click to expand)</summary>
            <div class="flosc-condition-ref-body">
                <p>Conditions control when an offer (or a specific format of an offer) is shown. Combine conditions with <code>&&</code> (AND), <code>||</code> (OR), <code>!</code> (NOT), and <code>()</code> (grouping). Click any condition to copy it.</p>
                
                <div class="flosc-condition-ref-grid">
                    <div class="flosc-condition-ref-section">
                        <h4>Quiz & Score</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="score >= 70"><code>score >= 70</code></button>
                        <span class="flosc-cond-desc">Quiz score is 70 or above</span>
                        <button type="button" class="flosc-condition-copy" data-condition="score < 50"><code>score < 50</code></button>
                        <span class="flosc-cond-desc">Quiz score below 50</span>
                        <button type="button" class="flosc-condition-copy" data-condition="initial_score >= 80"><code>initial_score >= 80</code></button>
                        <span class="flosc-cond-desc">First-ever quiz score was 80+</span>
                        <button type="button" class="flosc-condition-copy" data-condition="quiz_taken"><code>quiz_taken</code></button>
                        <span class="flosc-cond-desc">User has completed at least one quiz</span>
                        <button type="button" class="flosc-condition-copy" data-condition="!quiz_taken"><code>!quiz_taken</code></button>
                        <span class="flosc-cond-desc">User has NOT taken a quiz yet</span>
                        <button type="button" class="flosc-condition-copy" data-condition="quiz_id == &quot;pronunciation_01&quot;"><code>quiz_id == "pronunciation_01"</code></button>
                        <span class="flosc-cond-desc">Last quiz was this specific quiz ID</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>User Status</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="logged_in"><code>logged_in</code></button>
                        <span class="flosc-cond-desc">User is logged in</span>
                        <button type="button" class="flosc-condition-copy" data-condition="!logged_in"><code>!logged_in</code></button>
                        <span class="flosc-cond-desc">User is NOT logged in (visitor)</span>
                        <button type="button" class="flosc-condition-copy" data-condition="is_visitor"><code>is_visitor</code></button>
                        <span class="flosc-cond-desc">Access level is visitor (not logged in)</span>
                        <button type="button" class="flosc-condition-copy" data-condition="is_guest"><code>is_guest</code></button>
                        <span class="flosc-cond-desc">Logged in but has not purchased</span>
                        <button type="button" class="flosc-condition-copy" data-condition="is_member"><code>is_member</code></button>
                        <span class="flosc-cond-desc">Logged in and has purchased / has member access</span>
                        <button type="button" class="flosc-condition-copy" data-condition="has_profile"><code>has_profile</code></button>
                        <span class="flosc-cond-desc">User has a quiz profile / bridge data</span>
                        <button type="button" class="flosc-condition-copy" data-condition="returning_user"><code>returning_user</code></button>
                        <span class="flosc-cond-desc">User has visited before (not first session)</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Purchase & Access</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="purchased"><code>purchased</code></button>
                        <span class="flosc-cond-desc">User has made a purchase</span>
                        <button type="button" class="flosc-condition-copy" data-condition="!purchased"><code>!purchased</code></button>
                        <span class="flosc-cond-desc">User has NOT purchased (show offers to non-buyers)</span>
                        <button type="button" class="flosc-condition-copy" data-condition="lesson_viewed"><code>lesson_viewed</code></button>
                        <span class="flosc-cond-desc">User has viewed/received their free lesson</span>
                        <button type="button" class="flosc-condition-copy" data-condition="onboarded"><code>onboarded</code></button>
                        <span class="flosc-cond-desc">User completed the full onboarding flow</span>
                        <button type="button" class="flosc-condition-copy" data-condition="has_incomplete_lesson"><code>has_incomplete_lesson</code></button>
                        <span class="flosc-cond-desc">User started a lesson but didn't finish</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Offer Tracking (replace {offer_id} with actual ID)</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="offer_shown_full_access"><code>offer_shown_{offer_id}</code></button>
                        <span class="flosc-cond-desc">This specific offer was shown to the user</span>
                        <button type="button" class="flosc-condition-copy" data-condition="offer_dismissed_full_access"><code>offer_dismissed_{offer_id}</code></button>
                        <span class="flosc-cond-desc">User dismissed this offer</span>
                        <button type="button" class="flosc-condition-copy" data-condition="!offer_purchased_full_access"><code>!offer_purchased_{offer_id}</code></button>
                        <span class="flosc-cond-desc">User has NOT purchased this specific offer</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Session & Interaction</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="message_count > 3"><code>message_count > 3</code></button>
                        <span class="flosc-cond-desc">User has sent more than 3 chat messages</span>
                        <button type="button" class="flosc-condition-copy" data-condition="lessons_completed >= 1"><code>lessons_completed >= 1</code></button>
                        <span class="flosc-cond-desc">User completed at least 1 lesson</span>
                        <button type="button" class="flosc-condition-copy" data-condition="session_minutes >= 5"><code>session_minutes >= 5</code></button>
                        <span class="flosc-cond-desc">User has been on the site for 5+ minutes</span>
                        <button type="button" class="flosc-condition-copy" data-condition="inactive_seconds >= 300"><code>inactive_seconds >= 300</code></button>
                        <span class="flosc-cond-desc">User idle for 5+ minutes (300 seconds)</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Milestone Triggers (true once per session)</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="first_message_after_quiz"><code>first_message_after_quiz</code></button>
                        <span class="flosc-cond-desc">First message user sends after completing quiz</span>
                        <button type="button" class="flosc-condition-copy" data-condition="first_message_after_login"><code>first_message_after_login</code></button>
                        <span class="flosc-cond-desc">First message after logging in</span>
                        <button type="button" class="flosc-condition-copy" data-condition="first_message_after_purchase"><code>first_message_after_purchase</code></button>
                        <span class="flosc-cond-desc">First message after completing a purchase</span>
                        <button type="button" class="flosc-condition-copy" data-condition="first_message_after_free_lesson"><code>first_message_after_free_lesson</code></button>
                        <span class="flosc-cond-desc">First message after viewing free lesson</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Special Values</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="always"><code>always</code></button>
                        <span class="flosc-cond-desc">Always show (no conditions)</span>
                        <button type="button" class="flosc-condition-copy" data-condition="never"><code>never</code></button>
                        <span class="flosc-cond-desc">Never show — useful for temporarily disabling without deleting</span>
                    </div>
                    
                    <div class="flosc-condition-ref-section">
                        <h4>Combining Conditions — Examples</h4>
                        <button type="button" class="flosc-condition-copy" data-condition="quiz_taken && !purchased"><code>quiz_taken && !purchased</code></button>
                        <span class="flosc-cond-desc">Took quiz AND hasn't purchased — classic OTO target</span>
                        <button type="button" class="flosc-condition-copy" data-condition="score >= 80 && !purchased"><code>score >= 80 && !purchased</code></button>
                        <span class="flosc-cond-desc">High scorer who hasn't bought — premium upsell</span>
                        <button type="button" class="flosc-condition-copy" data-condition="(score < 50 || !quiz_taken) && !purchased"><code>(score < 50 || !quiz_taken) && !purchased</code></button>
                        <span class="flosc-cond-desc">Low scorer OR no quiz, AND hasn't purchased</span>
                        <button type="button" class="flosc-condition-copy" data-condition="is_guest && message_count > 5 && !offer_shown_full_access"><code>is_guest && message_count > 5 && !offer_shown_full_access</code></button>
                        <span class="flosc-cond-desc">Engaged guest, offer not yet shown</span>
                    </div>
                </div>
                
                <p class="flosc-condition-ref-operators">
                    <strong>Operators:</strong>
                    <code>>=</code> greater or equal &nbsp;·&nbsp;
                    <code><=</code> less or equal &nbsp;·&nbsp;
                    <code>></code> greater than &nbsp;·&nbsp;
                    <code><</code> less than &nbsp;·&nbsp;
                    <code>==</code> equals &nbsp;·&nbsp;
                    <code>&&</code> AND &nbsp;·&nbsp;
                    <code>||</code> OR &nbsp;·&nbsp;
                    <code>!</code> NOT &nbsp;·&nbsp;
                    <code>( )</code> grouping
                </p>
            </div>
        </details>
        
        <!-- APPEARANCE -->
        <div class="flosc-offer-section-label">🎨 Appearance</div>
        <table class="form-table" style="margin: 0;">
            <tr>
                <th style="width: 150px;"><label>Icon</label></th>
                <td><input type="text" name="offer_icon" value="<?php echo esc_attr($offer['meta']['icon'] ?? '⭐'); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th><label>Badge</label></th>
                <td><input type="text" name="offer_badge" value="<?php echo esc_attr($offer['meta']['badge'] ?? ''); ?>" class="regular-text" placeholder="Most Popular"></td>
            </tr>
            <tr>
                <th><label>Savings Text</label></th>
                <td><input type="text" name="offer_savings" value="<?php echo esc_attr($offer['meta']['savings'] ?? ''); ?>" class="regular-text" placeholder="Save $50!"></td>
            </tr>
            <tr>
                <th><label>Guarantee</label></th>
                <td><input type="text" name="offer_guarantee" value="<?php echo esc_attr($offer['guarantee'] ?? 'Risk-free with our 30-day money-back guarantee'); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label>Status</label></th>
                <td><label><input type="checkbox" name="offer_active" value="1" <?php checked($offer['active'] ?? true); ?>> Active (visible to users)</label></td>
            </tr>
        </table>
        
        <!-- MULTI-FORMAT CONFIGURATION -->
        <div class="flosc-offer-section-label">📐 Display Formats — enable one or more</div>
        <p class="description" style="margin: 0 0 8px;">
            Each offer can appear in multiple formats simultaneously. Enable the formats you want and configure per-format conditions and overrides.
        </p>
        
        <div class="flosc-fmt-grid">
        <?php foreach ($all_format_meta as $fmt_id => $fmt_meta):
            $fmt_key = str_replace('-', '_', $fmt_id);
            $fmt_data = $df[$fmt_id] ?? [];
            $fmt_enabled = !empty($fmt_data['enabled']);
        ?>
            <div class="flosc-fmt-card <?php echo esc_attr( $fmt_enabled ? 'is-enabled' : '' ); ?>">
                <div class="fmt-header">
                    <span style="font-size: 16px;"><?php echo esc_html( $fmt_meta['icon'] ); ?></span>
                    <label>
                        <input type="checkbox" name="fmt_<?php echo esc_attr( $fmt_key ); ?>_enabled" value="1" 
                               <?php checked($fmt_enabled); ?>
                               onchange="floscToggleFmt(this)">
                        <?php echo esc_html($fmt_meta['label']); ?>
                    </label>
                </div>
                <div class="fmt-desc"><?php echo esc_html($fmt_meta['desc']); ?></div>
                
                <div class="fmt-fields">
                    <div class="field-row">
                        <label>Condition override:</label>
                        <input type="text" name="fmt_<?php echo esc_attr( $fmt_key ); ?>_condition" 
                               value="<?php echo esc_attr($fmt_data['condition'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="Uses global condition if empty">
                    </div>
                    <div class="field-row">
                        <label>Timer override (seconds):</label>
                        <input type="number" name="fmt_<?php echo esc_attr( $fmt_key ); ?>_timer" 
                               value="<?php echo esc_attr($fmt_data['timer'] ?? ''); ?>" 
                               class="small-text" placeholder="0">
                    </div>
                    
                    <?php if ($fmt_id === 'pill'): ?>
                    <div class="field-row">
                        <label>Target panel (where this pill appears):</label>
                        <select name="fmt_pill_target_panel" style="width: 100%;">
                            <option value="guest" <?php selected($fmt_data['target_panel'] ?? $offer['pill_target_panel'] ?? '', 'guest'); ?>>🟢 GuestPanel — logged-in users who haven't purchased</option>
                            <option value="member" <?php selected($fmt_data['target_panel'] ?? '', 'member'); ?>>🔵 MemberPanel — users who have purchased</option>
                            <option value="intro" <?php selected($fmt_data['target_panel'] ?? '', 'intro'); ?>>⚪ IntroPanel — visitors (not logged in)</option>
                            <option value="both" <?php selected($fmt_data['target_panel'] ?? '', 'both'); ?>>🟢🔵 Guest + Member — all logged-in users</option>
                        </select>
                    </div>
                    <div class="field-row">
                        <label>Pill label (text shown on the pill):</label>
                        <input type="text" name="fmt_pill_label" 
                               value="<?php echo esc_attr($fmt_data['label'] ?? $offer['pill_label'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="<?php echo esc_attr($offer['name'] ?? 'Special Offer'); ?>">
                    </div>
                    <div class="field-row">
                        <label>Pill icon (emoji shown before the label):</label>
                        <input type="text" name="fmt_pill_icon" 
                               value="<?php echo esc_attr($fmt_data['icon'] ?? $offer['pill_icon'] ?? ''); ?>" 
                               class="small-text" placeholder="🎁">
                    </div>
                    <div class="field-row">
                        <label>Pill phrase (sent as user message when clicked):</label>
                        <input type="text" name="fmt_pill_phrase" 
                               value="<?php echo esc_attr($fmt_data['phrase'] ?? $offer['pill_phrase'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="Uses Reveal Phrase if empty">
                        <p class="description" style="margin-top: 2px; font-size: 11px;">When user clicks this pill, this text is sent as their message. If empty, uses the Reveal Phrase from above. If both empty, sends the pill label text.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($fmt_id, ['card','featured','banner'])): ?>
                    <div class="field-row">
                        <label>Headline override:</label>
                        <input type="text" name="fmt_<?php echo esc_attr( $fmt_key ); ?>_headline" 
                               value="<?php echo esc_attr($fmt_data['headline_override'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="Uses main headline if empty">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        
        <!-- ACTIONS -->
        <div style="display: flex; gap: 8px; align-items: center; margin-top: 16px; padding-top: 16px; border-top: 1px solid #ddd;">
            <?php submit_button('Save Offer', 'primary', 'save_offer', false); ?>
            
            <?php if (!$is_new): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&toggle_status=' . urlencode($offer_id) . '&_wpnonce=' . wp_create_nonce('flosc_toggle_status'))); ?>" 
                   class="button">
                    <?php echo ($offer['active'] ?? true) ? 'Deactivate' : 'Activate'; ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&delete_offer=' . urlencode($offer_id) . '&_wpnonce=' . wp_create_nonce('flosc_delete_offer_' . $offer_id))); ?>" 
                   class="button" style="color: #d63638; margin-left: auto;"
                   onclick="return confirm('Permanently delete this offer?');">
                    Delete Offer
                </a>
            <?php endif; ?>
        </div>
    </form>
<?php } ?>

<form method="post" action="options.php">
<?php settings_fields('flosc_settings'); ?>
