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

// ============================================
// SAVE HANDLER — runs at include time (same as delete/toggle handlers below)
// v1.6.5: Removed dead add_action('init',...) — file loads after init fires
// ============================================
function flosc_handle_offer_save() {
    if (!isset($_POST['save_offer']) || !wp_verify_nonce($_POST['flosc_save_offer_nonce'], 'flosc_save_offer')) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $offer_id = sanitize_text_field($_POST['offer_id']);
    if ($offer_id === 'new') {
        $offer_id = 'offer_' . wp_generate_password(8, false);
    }
    
    // Build display_formats array from checkboxes
    $all_formats = ['card','pill','compact','banner','featured','text','inline-checkout'];
    $display_formats = [];
    foreach ($all_formats as $fmt) {
        $key = str_replace('-', '_', $fmt);
        $enabled = !empty($_POST['fmt_' . $key . '_enabled']);
        $display_formats[$fmt] = [
            'enabled'   => $enabled,
            'condition' => sanitize_text_field($_POST['fmt_' . $key . '_condition'] ?? ''),
            'timer'     => intval($_POST['fmt_' . $key . '_timer'] ?? 0),
        ];
        if ($fmt === 'pill') {
            $display_formats[$fmt]['label']  = sanitize_text_field($_POST['fmt_pill_label'] ?? '');
            $display_formats[$fmt]['icon']   = sanitize_text_field($_POST['fmt_pill_icon'] ?? '');
            $display_formats[$fmt]['phrase'] = sanitize_text_field($_POST['fmt_pill_phrase'] ?? '');
        }
        if (in_array($fmt, ['card','featured','banner'])) {
            $display_formats[$fmt]['headline_override'] = sanitize_text_field($_POST['fmt_' . $key . '_headline'] ?? '');
        }
    }
    
    // Ensure at least one format is enabled — default to card
    $any_enabled = false;
    foreach ($display_formats as $f) { if ($f['enabled']) { $any_enabled = true; break; } }
    if (!$any_enabled) { $display_formats['card']['enabled'] = true; }
    
    // Primary display_format for backward compat
    $primary_format = 'card';
    foreach ($display_formats as $fid => $fdata) {
        if ($fdata['enabled']) { $primary_format = $fid; break; }
    }
    
    $offer_data = [
        'id'             => $offer_id,
        'name'           => sanitize_text_field($_POST['offer_name']),
        'type'           => sanitize_text_field($_POST['offer_type']),
        'price'          => floatval($_POST['offer_price']),
        'original_price' => floatval($_POST['offer_original_price']),
        'display_price'  => '$' . number_format(floatval($_POST['offer_price']), 2),
        'headline'       => sanitize_text_field($_POST['offer_headline']),
        'description'    => sanitize_textarea_field($_POST['offer_description']),
        'features'       => sanitize_textarea_field($_POST['offer_features']),
        'cta'            => sanitize_text_field($_POST['offer_cta']),
        'trigger'        => sanitize_text_field($_POST['offer_trigger']),
        'condition'      => sanitize_text_field($_POST['offer_condition']),
        'grants_level'   => sanitize_key($_POST['offer_grants_level'] ?? ''),
        'grants'         => [
            'features' => ['full_access'],
            'level'    => sanitize_key($_POST['offer_grants_level'] ?? ''),
        ],
        'timer_minutes'   => intval($_POST['offer_timer']),
        'timer_seconds'   => intval($_POST['offer_timer']) * 60,
        'display_formats' => $display_formats,
        'display_format'  => $primary_format,
        'guarantee'       => sanitize_text_field($_POST['offer_guarantee'] ?? ''),
        'meta'            => [
            'icon'    => sanitize_text_field($_POST['offer_icon'] ?? '⭐'),
            'badge'   => sanitize_text_field($_POST['offer_badge'] ?? ''),
            'savings' => sanitize_text_field($_POST['offer_savings'] ?? ''),
        ],
        'status'          => isset($_POST['offer_active']) ? 'active' : 'draft',
        'active'          => isset($_POST['offer_active']),
        'pill_label'      => sanitize_text_field($_POST['fmt_pill_label'] ?? ''),
        'pill_icon'       => sanitize_text_field($_POST['fmt_pill_icon'] ?? ''),
        'pill_phrase'     => sanitize_text_field($_POST['fmt_pill_phrase'] ?? ''),
        'conversions'     => 0,
        'views'           => 0,
        'created'         => current_time('mysql'),
        'updated'         => current_time('mysql'),
    ];
    
    // Preserve existing stats if editing
    $fk = sanitize_text_field($_POST['flosc_flow_key'] ?? '');
    if ($fk) {
        $fs = get_option($fk, []);
        $all_offers = $fs['offers'] ?? [];
    } else {
        $all_offers = [];
    }
    if (isset($all_offers[$offer_id])) {
        $offer_data['conversions'] = $all_offers[$offer_id]['conversions'] ?? 0;
        $offer_data['views']       = $all_offers[$offer_id]['views'] ?? 0;
        $offer_data['created']     = $all_offers[$offer_id]['created'] ?? current_time('mysql');
    }
    
    $all_offers[$offer_id] = $offer_data;
    if ($fk) {
        $fs['offers'] = $all_offers;
        update_option($fk, $fs);
    }
    
    $ivr = sanitize_file_name($_POST['flosc_ivr'] ?? '');
    wp_redirect(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($ivr) . '&tab=offers&saved=1'));
    exit;
}
flosc_handle_offer_save(); // v1.6.5: Execute at include time

// Handle delete
if (isset($_GET['delete_offer']) && isset($_GET['_wpnonce'])) {
    $del_id = sanitize_text_field($_GET['delete_offer']);
    if (wp_verify_nonce($_GET['_wpnonce'], 'flosc_delete_offer_' . $del_id) && current_user_can('manage_options')) {
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
    $toggle_id = sanitize_text_field($_GET['toggle_status']);
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $all = $fs['offers'] ?? [];
        if (isset($all[$toggle_id])) {
            $all[$toggle_id]['active'] = !($all[$toggle_id]['active'] ?? true);
            $all[$toggle_id]['status'] = $all[$toggle_id]['active'] ? 'active' : 'draft';
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
$expand_id = $_GET['edit_offer'] ?? $_GET['expand'] ?? null;

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

<?php if (isset($_GET['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>Offer saved successfully.</p></div>
<?php endif; ?>

<style>
.flosc-offer-card { border: 1px solid #ddd; background: #fff; }
.flosc-offer-card + .flosc-offer-card { border-top: none; }
.flosc-offer-card:first-child { border-radius: 6px 6px 0 0; }
.flosc-offer-card:last-child { border-radius: 0 0 6px 6px; }
.flosc-offer-header {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; cursor: pointer; user-select: none;
    transition: background 0.15s;
}
.flosc-offer-header:hover { background: #f0f6fc; }
.flosc-offer-header .toggle { font-size: 11px; color: #999; transition: transform 0.2s; }
.flosc-offer-card.is-open .toggle { transform: rotate(90deg); }
.flosc-offer-header .offer-name { font-weight: 600; font-size: 14px; }
.flosc-offer-header .offer-price { font-weight: 600; color: #10b981; font-size: 14px; }
.flosc-offer-header .offer-price .original { text-decoration: line-through; color: #999; font-weight: 400; font-size: 12px; margin-right: 4px; }
.flosc-offer-header .offer-status { font-size: 11px; }
.flosc-offer-header .offer-status.active { color: #10b981; }
.flosc-offer-header .offer-status.draft { color: #999; }
.flosc-offer-header .offer-formats { display: flex; gap: 4px; margin-left: auto; }
.flosc-offer-header .fmt-badge {
    display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 10px;
    background: #e8f0fe; color: #1a73e8;
}
.flosc-offer-header .offer-stats { font-size: 11px; color: #999; white-space: nowrap; }
.flosc-offer-editor { display: none; padding: 20px; border-top: 1px solid #eee; background: #fafbfc; }
.flosc-offer-card.is-open .flosc-offer-editor { display: block; }

.flosc-fmt-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 12px; margin: 12px 0;
}
.flosc-fmt-card {
    border: 1px solid #ddd; border-radius: 6px; background: #fff; padding: 12px 14px;
    opacity: 0.5; transition: opacity 0.2s, border-color 0.2s;
}
.flosc-fmt-card.is-enabled { opacity: 1; border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
.flosc-fmt-card .fmt-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.flosc-fmt-card .fmt-header label { font-weight: 600; font-size: 13px; cursor: pointer; }
.flosc-fmt-card .fmt-desc { font-size: 11px; color: #667; margin-bottom: 8px; }
.flosc-fmt-card .fmt-fields { display: none; }
.flosc-fmt-card.is-enabled .fmt-fields { display: block; }
.flosc-fmt-card .fmt-fields label { display: block; font-size: 12px; margin-bottom: 2px; color: #444; }
.flosc-fmt-card .fmt-fields input,
.flosc-fmt-card .fmt-fields select { font-size: 12px; }
.flosc-fmt-card .fmt-fields .field-row { margin-bottom: 6px; }

.flosc-offer-section-label {
    font-size: 13px; font-weight: 600; color: #1d2327;
    border-bottom: 1px solid #eee; padding-bottom: 6px; margin: 18px 0 10px;
}
.flosc-add-offer-btn {
    display: block; width: 100%; padding: 12px; border: 2px dashed #c3c4c7;
    background: #fafbfc; color: #2271b1; font-size: 14px; font-weight: 500;
    cursor: pointer; text-align: center; border-radius: 6px; margin-top: 12px;
    transition: background 0.15s;
}
.flosc-add-offer-btn:hover { background: #f0f6fc; color: #135e96; }
</style>

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
    $is_active = $offer['active'] ?? true;
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
<div class="flosc-offer-card <?php echo $is_open ? 'is-open' : ''; ?>" id="offer-<?php echo $safe_id; ?>">
    <div class="flosc-offer-header" onclick="floscToggleOffer('<?php echo esc_js($offer['id']); ?>')">
        <span class="toggle">▶</span>
        <span class="offer-name"><?php echo esc_html($offer['name']); ?></span>
        <span class="offer-price">
            <?php if (!empty($offer['original_price']) && $offer['original_price'] != $offer['price']): ?>
                <span class="original">$<?php echo esc_html(number_format($offer['original_price'], 2)); ?></span>
            <?php endif; ?>
            $<?php echo esc_html(number_format($offer['price'] ?? 0, 2)); ?>
        </span>
        <span class="offer-status <?php echo $is_active ? 'active' : 'draft'; ?>">
            <?php echo $is_active ? '● Active' : '○ Draft'; ?>
        </span>
        <span class="offer-formats">
            <?php foreach ($enabled_fmts as $ef): ?>
                <span class="fmt-badge"><?php echo esc_html($ef); ?></span>
            <?php endforeach; ?>
        </span>
        <span class="offer-stats"><?php echo $conversions; ?>/<?php echo $views; ?> (<?php echo $rate; ?>%)</span>
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

<button type="button" class="flosc-add-offer-btn" onclick="window.location='<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&edit_offer=new')); ?>'">
    + Create New Offer
</button>

<!-- ============================================ -->
<!-- TEMPLATES (collapsible) -->
<!-- ============================================ -->
<details style="margin-top: 30px;">
    <summary style="cursor: pointer; font-size: 15px; font-weight: 600; padding: 10px 0;">
        📋 High-Converting Offer Templates (click to expand)
    </summary>
    <div style="margin-top: 10px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
        <p class="description">Proven offer patterns you can use. Set up the offer details, then enable the right display formats.</p>
        
        <div style="background: white; border-left: 4px solid #f59e0b; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;">🎯 Post-Quiz OTO (One-Time Offer)</h4>
            <p><strong>Trigger:</strong> Quiz Completed | <strong>Price:</strong> $49 (was $99) | <strong>Timer:</strong> 15 min</p>
            <p>Lead with urgency + discount. Include feature list, guarantee, and countdown. Enable: <strong>card + pill</strong>.</p>
        </div>
        
        <div style="background: white; border-left: 4px solid #8b5cf6; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;">💎 Premium Upsell</h4>
            <p><strong>Trigger:</strong> Free Lesson Completed | <strong>Price:</strong> $299/yr or $39/mo</p>
            <p>Achievement-based pitch. Enable: <strong>featured + banner</strong>.</p>
        </div>
        
        <div style="background: white; border-left: 4px solid #10b981; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;">📦 High-Performer Bundle (Score 80%+)</h4>
            <p><strong>Trigger:</strong> Score ≥ 80 | <strong>Price:</strong> $399 (was $597) | <strong>Timer:</strong> 24h</p>
            <p>"You're top 10%." Enable: <strong>featured card</strong> with badge.</p>
        </div>
        
        <div style="background: white; border-left: 4px solid #ec4899; padding: 15px; margin: 15px 0;">
            <h4 style="margin-top: 0;">🔄 Re-engagement (Inactive Users)</h4>
            <p><strong>Trigger:</strong> 7+ Days Inactivity | <strong>Price:</strong> $29/mo (was $49)</p>
            <p>"We miss you!" Enable: <strong>banner</strong> with no timer pressure.</p>
        </div>
        
        <div style="background: #e7f3ff; border-left: 4px solid #2196f3; padding: 15px; margin-top: 20px;">
            <strong>💡 Tips:</strong> Lead with results, not features. Use specific numbers. Address objections. Always include a guarantee. Show the math. End with social proof.
        </div>
    </div>
</details>

<script>
function floscToggleOffer(id) {
    const card = document.getElementById('offer-' + id);
    if (card) card.classList.toggle('is-open');
}
function floscToggleFmt(checkbox) {
    const card = checkbox.closest('.flosc-fmt-card');
    card.classList.toggle('is-enabled', checkbox.checked);
}
document.addEventListener('DOMContentLoaded', function() {
    const open = document.querySelector('.flosc-offer-card.is-open');
    if (open) open.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>

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
        <input type="hidden" name="offer_id" value="<?php echo $safe_id; ?>">
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
                            <option value="<?php echo $v; ?>" <?php selected($offer['type'] ?? '', $v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Pricing</th>
                <td>
                    <label>Price: $<input type="number" name="offer_price" value="<?php echo esc_attr($offer['price'] ?? ''); ?>" step="0.01" min="0" class="small-text" required></label>
                    <label style="margin-left: 16px;">Was: $<input type="number" name="offer_original_price" value="<?php echo esc_attr($offer['original_price'] ?? ''); ?>" step="0.01" min="0" class="small-text"></label>
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
                        <?php foreach (['manual'=>'Manual','quiz_complete'=>'Quiz Completed','lesson_complete'=>'First Lesson Completed','login_phase'=>'User Enters Login Phase','inactivity'=>'After 7 Days Inactivity'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php selected($offer['trigger'] ?? '', $v); ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>Condition</label></th>
                <td>
                    <input type="text" name="offer_condition" value="<?php echo esc_attr($offer['condition'] ?? ''); ?>" class="large-text" placeholder="score >= 70 && !purchased">
                    <p class="description">Global condition for ALL formats. Per-format conditions below can override.</p>
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
                <td><input type="text" name="offer_grants_level" value="<?php echo esc_attr($offer['grants_level'] ?? ''); ?>" class="regular-text" placeholder="course110"></td>
            </tr>
        </table>
        
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
            <div class="flosc-fmt-card <?php echo $fmt_enabled ? 'is-enabled' : ''; ?>">
                <div class="fmt-header">
                    <span style="font-size: 16px;"><?php echo $fmt_meta['icon']; ?></span>
                    <label>
                        <input type="checkbox" name="fmt_<?php echo $fmt_key; ?>_enabled" value="1" 
                               <?php checked($fmt_enabled); ?>
                               onchange="floscToggleFmt(this)">
                        <?php echo esc_html($fmt_meta['label']); ?>
                    </label>
                </div>
                <div class="fmt-desc"><?php echo esc_html($fmt_meta['desc']); ?></div>
                
                <div class="fmt-fields">
                    <div class="field-row">
                        <label>Condition override:</label>
                        <input type="text" name="fmt_<?php echo $fmt_key; ?>_condition" 
                               value="<?php echo esc_attr($fmt_data['condition'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="Uses global condition if empty">
                    </div>
                    <div class="field-row">
                        <label>Timer override (seconds):</label>
                        <input type="number" name="fmt_<?php echo $fmt_key; ?>_timer" 
                               value="<?php echo esc_attr($fmt_data['timer'] ?? ''); ?>" 
                               class="small-text" placeholder="0">
                    </div>
                    
                    <?php if ($fmt_id === 'pill'): ?>
                    <div class="field-row">
                        <label>Pill label:</label>
                        <input type="text" name="fmt_pill_label" 
                               value="<?php echo esc_attr($fmt_data['label'] ?? $offer['pill_label'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="<?php echo esc_attr($offer['name'] ?? 'Special Offer'); ?>">
                    </div>
                    <div class="field-row">
                        <label>Pill icon:</label>
                        <input type="text" name="fmt_pill_icon" 
                               value="<?php echo esc_attr($fmt_data['icon'] ?? $offer['pill_icon'] ?? ''); ?>" 
                               class="small-text" placeholder="🎁">
                    </div>
                    <div class="field-row">
                        <label>Pill phrase (sent as user message):</label>
                        <input type="text" name="fmt_pill_phrase" 
                               value="<?php echo esc_attr($fmt_data['phrase'] ?? $offer['pill_phrase'] ?? ''); ?>" 
                               style="width: 100%;" placeholder="Show me the special offer">
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($fmt_id, ['card','featured','banner'])): ?>
                    <div class="field-row">
                        <label>Headline override:</label>
                        <input type="text" name="fmt_<?php echo $fmt_key; ?>_headline" 
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($current_ivr) . '&tab=offers&toggle_status=' . urlencode($offer_id))); ?>" 
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
