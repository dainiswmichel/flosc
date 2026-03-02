<?php
/**
 * FLOSC AutoPrompts Configuration Tab v1.1.0
 *
 * Manage AutoPromptPanel pills per user state (Visitor, Guest, Member).
 * Each pill can:
 *   - Send text to AI (default)
 *   - Trigger an offer (show_offer_OFFER_ID)
 *   - Trigger an in-chat action (open_quiz, open_free_lesson, etc.)
 *
 * Fields per pill:
 *   icon          — emoji
 *   label         — display text on pill/button
 *   user_input    — text sent or displayed (blank = same as label)
 *   trigger_type  — 'ai' | 'offer' | 'action'
 *   trigger_value — offer_id (for offer), action key (for action), empty for ai
 *   conditions    — when to show (is_visitor, is_guest, is_member, custom expression)
 *   style         — pill | button | chip
 */

if (!defined('ABSPATH')) exit;

flosc_tab_header('💊', 'AutoPrompts');

$flow_key    = $GLOBALS['flosc_settings_key'] ?? '';
$current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';

// ============================================
// SAVE HANDLER
// ============================================
function flosc_handle_autoprompts_save() {
    if (!isset($_POST['save_autoprompts']) || !wp_verify_nonce($_POST['flosc_autoprompts_nonce'], 'flosc_save_autoprompts')) {
        return;
    }
    if (!current_user_can('manage_options')) return;

    $fk = sanitize_text_field($_POST['flosc_flow_key'] ?? '');
    if (!$fk) return;

    $fs = get_option($fk, []);
    $states = ['visitor', 'guest', 'member'];
    $autoprompts = [];

    // Save panel header text per state
    $panel_headers = [];
    foreach ($states as $state) {
        $panel_headers[$state] = sanitize_text_field(wp_unslash($_POST['panel_header_' . $state] ?? 'Try these AutoPrompts!'));
    }

    // Save panel show/hide toggle per state (checkbox — absent means unchecked = 0)
    $panel_enabled = [];
    foreach ($states as $state) {
        $panel_enabled[$state] = isset($_POST['panel_enabled_' . $state]) ? 1 : 0;
    }

    foreach ($states as $state) {
        $icons          = wp_unslash($_POST[$state . '_pill_icon']           ?? []);
        $labels         = wp_unslash($_POST[$state . '_pill_label']          ?? []);
        $inputs         = wp_unslash($_POST[$state . '_pill_user_input']     ?? []);
        $trigger_types  = $_POST[$state . '_pill_trigger_type']   ?? [];
        $trigger_values = wp_unslash($_POST[$state . '_pill_trigger_value']  ?? []);
        $conditions     = $_POST[$state . '_pill_conditions']     ?? [];
        $styles         = $_POST[$state . '_pill_style']          ?? [];

        $pills = [];
        foreach ($labels as $i => $label) {
            $label = sanitize_text_field($label);
            if (!$label) continue;
            $ttype  = sanitize_key($trigger_types[$i] ?? 'ai') ?: 'ai';
            $tval   = sanitize_text_field($trigger_values[$i] ?? '');
            $pills[] = [
                'icon'          => sanitize_text_field($icons[$i] ?? ''),
                'label'         => $label,
                'user_input'    => sanitize_text_field($inputs[$i] ?? '') ?: $label,
                'trigger_type'  => $ttype,
                'trigger_value' => $tval,
                'action'        => $ttype === 'ai' ? '' : ($ttype === 'offer' ? 'show_offer_' . $tval : $tval),
                'conditions'    => sanitize_text_field($conditions[$i] ?? ('is_' . $state)),
                'style'         => sanitize_key($styles[$i] ?? 'pill') ?: 'pill',
            ];
        }
        $autoprompts[$state] = $pills;
    }

    $fs['autoprompts']              = $autoprompts;
    $fs['autoprompt_headers']       = $panel_headers;
    $fs['autoprompt_panel_enabled'] = $panel_enabled;
    update_option($fk, $fs);

    $ivr = sanitize_file_name($_POST['flosc_ivr'] ?? '');
    wp_redirect(admin_url('admin.php?page=flosc-settings&ivr=' . urlencode($ivr) . '&tab=autoprompts&saved=1'));
    exit;
}
flosc_handle_autoprompts_save();

// ============================================
// LOAD CURRENT DATA
// ============================================
$fs             = $flow_key ? get_option($flow_key, []) : [];

// Strip all accumulated backslash layers from previously corrupted DB data
$_ap_raw  = $fs['autoprompts'] ?? [];
$_ap_prev = null;
while ( $_ap_prev !== $_ap_raw ) { $_ap_prev = $_ap_raw; $_ap_raw = stripslashes_deep( $_ap_raw ); }
$saved_prompts = $_ap_raw;

$_ah_raw  = $fs['autoprompt_headers'] ?? [];
$_ah_prev = null;
while ( $_ah_prev !== $_ah_raw ) { $_ah_prev = $_ah_raw; $_ah_raw = stripslashes_deep( $_ah_raw ); }
$saved_headers = $_ah_raw;
$panel_headers  = [
    'visitor' => $saved_headers['visitor'] ?? 'Try these AutoPrompts!',
    'guest'   => $saved_headers['guest']   ?? 'You\'re almost there — try these:',
    'member'  => $saved_headers['member']  ?? 'Welcome, LeSAEp Learner! What would you like to do?',
];
$saved_panel_enabled = $fs['autoprompt_panel_enabled'] ?? [];
$panel_enabled = [
    'visitor' => $saved_panel_enabled['visitor'] ?? 1,
    'guest'   => $saved_panel_enabled['guest']   ?? 1,
    'member'  => $saved_panel_enabled['member']  ?? 1,
];

// ============================================
// IVR-DEFINED DEFAULTS — all existing pills from the IVR file
// ============================================
$ivr_defaults = [
    'visitor' => [
        ['icon'=>'🚀','label'=>'Get started',               'user_input'=>'Get started',                   'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'is_visitor',                                          'style'=>'pill'],
        ['icon'=>'📝','label'=>'Take the free quiz',        'user_input'=>'Start free quiz',               'trigger_type'=>'action', 'trigger_value'=>'open_quiz',         'conditions'=>'is_visitor',                                          'style'=>'button'],
        ['icon'=>'🔐','label'=>'Login to see my score',     'user_input'=>'Login to see my score',         'trigger_type'=>'action', 'trigger_value'=>'open_registration', 'conditions'=>'is_visitor && quiz_taken',                            'style'=>'button'],
        ['icon'=>'❓','label'=>'How does it work?',         'user_input'=>'How does it work?',             'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'is_visitor',                                          'style'=>'pill'],
        ['icon'=>'📚','label'=>'What will I learn?',        'user_input'=>'What will I learn?',            'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'is_visitor',                                          'style'=>'pill'],
        ['icon'=>'🎉','label'=>'Purchase Now!',             'user_input'=>'PURCHASE Now!',                 'trigger_type'=>'action', 'trigger_value'=>'checkout_full_access','conditions'=>'is_visitor',                                       'style'=>'button'],
        ['icon'=>'✅','label'=>'Are you there?',            'user_input'=>'Are you there?',                'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'always',                                              'style'=>'pill'],
        ['icon'=>'👤','label'=>'What\'s my user status?',  'user_input'=>'What\'s my user status?',       'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'always',                                              'style'=>'pill'],
    ],
    'guest' => [
        // Post-quiz pills — show quiz results to motivate purchase
        ['icon'=>'📊','label'=>'Which sounds did I miss?',  'user_input'=>'Which pronunciation sounds did I miss on the quiz?',        'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'is_guest && quiz_taken',                              'style'=>'pill'],
        ['icon'=>'🎯','label'=>'My learning path',          'user_input'=>'Based on my quiz results, what\'s my personalized learning path?', 'trigger_type'=>'ai', 'trigger_value'=>'',              'conditions'=>'is_guest && quiz_taken',                              'style'=>'pill'],
        // Free lesson delivery
        ['icon'=>'🎁','label'=>'See my free lesson',        'user_input'=>'Show me my free lesson',        'trigger_type'=>'action', 'trigger_value'=>'open_free_lesson',  'conditions'=>'is_guest && quiz_taken && !lesson_viewed',             'style'=>'pill'],
        ['icon'=>'🔄','label'=>'Re-watch my free lesson',   'user_input'=>'I want to re-watch my free lesson', 'trigger_type'=>'action', 'trigger_value'=>'open_free_lesson', 'conditions'=>'is_guest && lesson_viewed',                          'style'=>'pill'],
        // Upgrade path
        ['icon'=>'🔓','label'=>'Unlock all 10 lessons',     'user_input'=>'What\'s included in full access?',  'trigger_type'=>'offer',  'trigger_value'=>'full_access',       'conditions'=>'is_guest && !offer_shown_full_access',                'style'=>'pill'],
        ['icon'=>'🎉','label'=>'Get full access now',        'user_input'=>'Get full access now',           'trigger_type'=>'action', 'trigger_value'=>'checkout_full_access','conditions'=>'is_guest && offer_shown_full_access',               'style'=>'button'],
        ['icon'=>'💬','label'=>'What\'s included?',         'user_input'=>'What\'s included in the full LeSAEp program?', 'trigger_type'=>'ai', 'trigger_value'=>'',          'conditions'=>'is_guest',                                            'style'=>'pill'],
        ['icon'=>'🔄','label'=>'Retake quiz',               'user_input'=>'I want to retake the pronunciation quiz', 'trigger_type'=>'action', 'trigger_value'=>'open_quiz',   'conditions'=>'is_guest',                                            'style'=>'pill'],
    ],
    'member' => [
        // Welcome back / get started
        ['icon'=>'📚','label'=>'My lesson library',         'user_input'=>'Open my lesson library',        'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library','conditions'=>'is_member',                                          'style'=>'button'],
        ['icon'=>'🎓','label'=>'Start my first lesson',     'user_input'=>'I\'m ready to start my first lesson — which one?', 'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'conditions'=>'is_member && first_message_after_purchase', 'style'=>'button'],
        // Quiz results and progress
        ['icon'=>'📊','label'=>'My quiz results',           'user_input'=>'Show me my full pronunciation quiz results and which sounds I need to work on', 'trigger_type'=>'ai', 'trigger_value'=>'', 'conditions'=>'is_member && quiz_taken',  'style'=>'pill'],
        ['icon'=>'🎯','label'=>'What should I study next?', 'user_input'=>'Based on my quiz results, which sound should I focus on first?', 'trigger_type'=>'ai', 'trigger_value'=>'', 'conditions'=>'is_member && quiz_taken',             'style'=>'pill'],
        // Sound-specific search
        ['icon'=>'🔍','label'=>'Find R sound lessons',      'user_input'=>'Find lessons on the American R sound', 'trigger_type'=>'ai', 'trigger_value'=>'',                 'conditions'=>'is_member',                                           'style'=>'pill'],
        ['icon'=>'🔍','label'=>'Find TH lessons',           'user_input'=>'Find lessons on the TH sound',  'trigger_type'=>'ai',     'trigger_value'=>'',                  'conditions'=>'is_member',                                           'style'=>'pill'],
        // Continue / retake
        ['icon'=>'▶️','label'=>'Continue last lesson',      'user_input'=>'Continue where I left off',     'trigger_type'=>'action', 'trigger_value'=>'open_last_lesson',  'conditions'=>'is_member && has_incomplete_lesson',                  'style'=>'pill'],
        ['icon'=>'🔄','label'=>'Retake quiz',               'user_input'=>'I want to retake the pronunciation quiz to track my progress', 'trigger_type'=>'action', 'trigger_value'=>'open_quiz', 'conditions'=>'is_member',                  'style'=>'pill'],
    ],
];

$prompts = [
    'visitor' => !empty($saved_prompts['visitor']) ? $saved_prompts['visitor'] : $ivr_defaults['visitor'],
    'guest'   => !empty($saved_prompts['guest'])   ? $saved_prompts['guest']   : $ivr_defaults['guest'],
    'member'  => !empty($saved_prompts['member'])  ? $saved_prompts['member']  : $ivr_defaults['member'],
];

// Available offers for trigger dropdown
$offers_for_trigger = [];
$flow_id_for_offers = $flow_key ? str_replace('flosc_flow_', '', $flow_key) : null;
if (function_exists('flosc') && $flow_id_for_offers) {
    foreach (flosc()->sale()->offers()->get_all_offers($flow_id_for_offers) as $oid => $o) {
        $offers_for_trigger[$oid] = $o['name'] ?? $oid;
    }
}

// Available actions
$available_actions = [
    'open_quiz'            => 'open_quiz — Take Quiz',
    'open_free_lesson'     => 'open_free_lesson — View Free Lesson',
    'open_lesson_library'  => 'open_lesson_library — Lesson Library',
    'open_quiz_library'    => 'open_quiz_library — Quiz Library',
    'open_registration'    => 'open_registration — Sign Up / Register',
    'open_support'         => 'open_support — Support / Help',
    'open_sandbox_purchase'=> 'open_sandbox_purchase — Sandbox Purchase',
    'checkout_full_access' => 'checkout_full_access — Checkout (Full Access)',
    'open_last_lesson'     => 'open_last_lesson — Continue Last Lesson',
    'show_full_score'      => 'show_full_score — Show Full Score',
];

$available_conditions = [
    'is_visitor'                                    => 'is_visitor',
    'is_visitor && quiz_taken'                      => 'is_visitor && quiz_taken',
    'is_guest'                                      => 'is_guest',
    'is_guest && quiz_taken'                        => 'is_guest && quiz_taken',
    'is_guest && !lesson_viewed'                    => 'is_guest && !lesson_viewed',
    'is_guest && lesson_viewed'                     => 'is_guest && lesson_viewed',
    'is_guest && !offer_shown_full_access'          => 'is_guest && !offer_shown_full_access',
    'is_guest && offer_shown_full_access'           => 'is_guest && offer_shown_full_access',
    'is_guest && message_count >= 3'                => 'is_guest && message_count >= 3',
    'is_member'                                     => 'is_member',
    'is_member && quiz_taken'                       => 'is_member && quiz_taken',
    'is_member && first_message_after_purchase'     => 'is_member && first_message_after_purchase',
    'is_member && has_incomplete_lesson'            => 'is_member && has_incomplete_lesson',
    'always'                                        => 'always — all users',
    'never'                                         => 'never — disabled',
];
?>

<?php if (isset($_GET['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>AutoPrompts saved successfully.</p></div>
<?php endif; ?>

<p>Configure the quick-reply pills shown in FLOSC chat per user state. Pills send a chat message to the AI, trigger an offer to display, or fire an in-chat action (quiz, lesson, checkout, etc.).</p>

<form method="post">
    <?php wp_nonce_field('flosc_save_autoprompts', 'flosc_autoprompts_nonce'); ?>
    <input type="hidden" name="flosc_flow_key" value="<?php echo esc_attr($flow_key); ?>">
    <input type="hidden" name="flosc_ivr" value="<?php echo esc_attr($current_ivr); ?>">

<?php
$state_config = [
    'visitor' => [
        'label'  => 'Visitor AutoPrompts — IntroPanel',
        'icon'   => '⚪',
        'color'  => '#f9f9f9',
        'border' => '#c3c4c7',
        'panel'  => 'IntroPanel',
        'desc'   => 'Not logged in. Shown in the <strong>IntroPanel</strong> with "Try these AutoPrompts!" header.',
    ],
    'guest' => [
        'label'  => 'Guest AutoPrompts — PromptPanel',
        'icon'   => '🟢',
        'color'  => '#f0fdf4',
        'border' => '#86efac',
        'panel'  => 'PromptPanel',
        'desc'   => 'Logged in, not purchased. Shown in the <strong>PromptPanel</strong>.',
    ],
    'member' => [
        'label'  => 'LeSAEp Learner AutoPrompts — MemberPromptPanel',
        'icon'   => '🔵',
        'color'  => '#eff6ff',
        'border' => '#93c5fd',
        'panel'  => 'MemberPromptPanel',
        'desc'   => 'Logged in and purchased (LeSAEp Learner). Shown in the <strong>MemberPromptPanel</strong>.',
    ],
];

foreach ($state_config as $state => $sc):
    $pills = $prompts[$state];
?>
<div class="flosc-autoprompt-section" style="background:<?php echo $sc['color']; ?>; border:1px solid <?php echo $sc['border']; ?>; border-radius:6px; padding:20px; margin-bottom:24px;">
    <h3 style="margin-top:0; display:flex; align-items:center; gap:12px;">
        <span><?php echo $sc['icon']; ?> <?php echo esc_html($sc['label']); ?></span>
        <label style="display:inline-flex; align-items:center; gap:5px; font-size:13px; font-weight:normal; cursor:pointer; margin:0;">
            <input type="checkbox" name="panel_enabled_<?php echo $state; ?>" value="1" <?php checked($panel_enabled[$state], 1); ?>>
            Show panel
        </label>
    </h3>
    <p class="description" style="margin-top:0;"><?php echo $sc['desc']; ?></p>

    <!-- Panel header text -->
    <div style="margin-bottom:14px; max-width:600px;">
        <label style="display:block; font-weight:600; margin-bottom:4px; font-size:13px;">
            Panel Header Message
            <span style="font-weight:normal; color:#787c82;">(the eyebrow text shown above the pills)</span>
        </label>
        <input type="text" name="panel_header_<?php echo $state; ?>"
               value="<?php echo esc_attr($panel_headers[$state]); ?>"
               class="large-text" placeholder="Try these AutoPrompts!"
               oninput="document.getElementById('header-preview-<?php echo $state; ?>').textContent = this.value">
    </div>

    <!-- Live pill preview -->
    <div style="background:#fff; border:1px solid <?php echo $sc['border']; ?>; border-radius:8px; padding:10px 14px; margin-bottom:14px; max-width:600px;">
        <div style="font-size:11px; font-weight:600; color:#787c82; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;"><?php echo $sc['panel']; ?> — Preview</div>
        <div id="header-preview-<?php echo $state; ?>" style="font-size:11px; color:#50575e; margin-bottom:6px;"><?php echo esc_html($panel_headers[$state]); ?></div>
        <div id="preview-<?php echo $state; ?>" style="display:flex; flex-wrap:wrap; gap:6px; min-height:28px;">
            <?php foreach ($pills as $pill): ?>
            <span class="flosc-preview-pill-chip" style="background:rgba(255,255,255,0.9);border:1px solid rgba(0,0,0,0.1);border-radius:16px;padding:4px 12px;font-size:12px;display:inline-flex;align-items:center;gap:5px;">
                <?php if ($pill['icon']): ?><span><?php echo esc_html($pill['icon']); ?></span><?php endif; ?>
                <?php echo esc_html($pill['label']); ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="overflow-x:auto;">
    <table class="widefat flosc-ap-table" style="border-collapse:collapse; min-width:900px; width:100%; font-size:13px;">
        <thead>
            <tr style="background:#f0f0f1;">
                <th style="width:50px; padding:6px 8px;">Icon</th>
                <th style="min-width:120px; padding:6px 8px;">Label</th>
                <th style="min-width:160px; padding:6px 8px;">User Input <span style="font-weight:normal; color:#787c82;">(sent to AI; blank=label)</span></th>
                <th style="width:130px; padding:6px 8px;">Trigger</th>
                <th style="width:200px; padding:6px 8px;">Trigger Value</th>
                <th style="width:200px; padding:6px 8px;">Conditions</th>
                <th style="width:80px; padding:6px 8px;">Style</th>
                <th style="width:36px; padding:6px 4px;"></th>
            </tr>
        </thead>
        <tbody id="tbody-<?php echo $state; ?>">
        <?php foreach ($pills as $pill):
            $ttype = $pill['trigger_type'] ?? 'ai';
            $tval  = $pill['trigger_value'] ?? '';
            // Back-compat: derive trigger_type from action field
            if (!isset($pill['trigger_type']) && !empty($pill['action'])) {
                if (strpos($pill['action'], 'show_offer_') === 0) {
                    $ttype = 'offer';
                    $tval  = str_replace('show_offer_', '', $pill['action']);
                } else {
                    $ttype = 'action';
                    $tval  = $pill['action'];
                }
            }
        ?>
            <tr class="flosc-ap-row" style="border-bottom:1px solid #eee;">
                <td style="padding:5px 6px;"><input type="text" name="<?php echo $state; ?>_pill_icon[]" value="<?php echo esc_attr($pill['icon'] ?? ''); ?>" style="width:44px;font-size:18px;text-align:center;border:1px solid #c3c4c7;border-radius:3px;" placeholder="💊"></td>
                <td style="padding:5px 6px;"><input type="text" name="<?php echo $state; ?>_pill_label[]" value="<?php echo esc_attr($pill['label'] ?? ''); ?>" style="width:100%;box-sizing:border-box;" class="regular-text" placeholder="Pill label"></td>
                <td style="padding:5px 6px;"><input type="text" name="<?php echo $state; ?>_pill_user_input[]" value="<?php echo esc_attr($pill['user_input'] ?? ''); ?>" style="width:100%;box-sizing:border-box;" placeholder="Same as label"></td>
                <td style="padding:5px 6px;">
                    <select name="<?php echo $state; ?>_pill_trigger_type[]" style="width:100%;" onchange="floscToggleTriggerValue(this)">
                        <option value="ai"     <?php selected($ttype,'ai'); ?>>💬 AI</option>
                        <option value="offer"  <?php selected($ttype,'offer'); ?>>💰 Offer</option>
                        <option value="action" <?php selected($ttype,'action'); ?>>⚡ Action</option>
                    </select>
                </td>
                <td style="padding:5px 6px;" class="flosc-trigger-value-cell">
                    <!-- AI: no value needed -->
                    <span class="flosc-tv-ai"  style="<?php echo $ttype !== 'ai'     ? 'display:none;' : ''; ?> color:#787c82; font-size:12px; padding:4px;">Sends user_input to AI</span>
                    <!-- Offer picker -->
                    <select name="<?php echo $state; ?>_pill_trigger_value_offer[]" style="width:100%; <?php echo $ttype !== 'offer' ? 'display:none;' : ''; ?>" class="flosc-tv-offer">
                        <?php foreach ($offers_for_trigger as $oid => $oname): ?>
                            <option value="<?php echo esc_attr($oid); ?>" <?php selected($tval, $oid); ?>><?php echo esc_html($oname); ?> (<?php echo esc_html($oid); ?>)</option>
                        <?php endforeach; ?>
                        <?php if (empty($offers_for_trigger)): ?>
                            <option value="full_access" <?php selected($tval,'full_access'); ?>>full_access (default)</option>
                        <?php endif; ?>
                    </select>
                    <!-- Action picker -->
                    <select name="<?php echo $state; ?>_pill_trigger_value_action[]" style="width:100%; <?php echo $ttype !== 'action' ? 'display:none;' : ''; ?>" class="flosc-tv-action">
                        <?php foreach ($available_actions as $aval => $adesc): ?>
                            <option value="<?php echo esc_attr($aval); ?>" <?php selected($tval, $aval); ?>><?php echo esc_html($adesc); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <!-- Hidden field that submits the actual trigger_value -->
                    <input type="hidden" name="<?php echo $state; ?>_pill_trigger_value[]" value="<?php echo esc_attr($tval); ?>" class="flosc-tv-hidden">
                </td>
                <td style="padding:5px 6px;">
                    <select name="<?php echo $state; ?>_pill_conditions[]" style="width:100%;">
                        <?php
                        $cond = $pill['conditions'] ?? ('is_' . $state);
                        $found = false;
                        foreach ($available_conditions as $cval => $cdesc):
                            $sel = ($cond === $cval) ? 'selected' : '';
                            if ($cond === $cval) $found = true;
                        ?>
                            <option value="<?php echo esc_attr($cval); ?>" <?php echo $sel; ?>><?php echo esc_html($cdesc); ?></option>
                        <?php endforeach; ?>
                        <?php if (!$found && $cond): ?>
                            <option value="<?php echo esc_attr($cond); ?>" selected><?php echo esc_html($cond); ?> (custom)</option>
                        <?php endif; ?>
                    </select>
                </td>
                <td style="padding:5px 6px;">
                    <select name="<?php echo $state; ?>_pill_style[]" style="width:100%;">
                        <option value="pill"   <?php selected($pill['style'] ?? 'pill', 'pill'); ?>>pill</option>
                        <option value="button" <?php selected($pill['style'] ?? '', 'button'); ?>>button</option>
                        <option value="chip"   <?php selected($pill['style'] ?? '', 'chip'); ?>>chip</option>
                    </select>
                </td>
                <td style="padding:5px 4px;"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <p style="margin-top:10px;">
        <button type="button" class="button flosc-ap-add" data-state="<?php echo $state; ?>" data-default-cond="is_<?php echo $state; ?>">+ Add Pill</button>
    </p>
</div>
<?php endforeach; ?>

<div style="padding:16px 0;">
    <p class="description" style="margin-bottom:12px;">
        <strong>Trigger types:</strong>
        💬 <strong>AI</strong> — sends User Input text as a chat message (AI responds naturally).
        💰 <strong>Offer</strong> — immediately displays the selected offer card/modal in chat.
        ⚡ <strong>Action</strong> — fires an in-chat action (open quiz, lesson library, checkout, etc).
    </p>
    <?php submit_button('Save AutoPrompts', 'primary', 'save_autoprompts', false); ?>
</div>

<!-- ── Demo Pill Sets ─────────────────────────────────────────────────────── -->
<?php
$pill_demos = [
    'visitor' => [
        [
            'name' => 'LeSAEp Visitor Starter Pack',
            'desc' => '8 pills covering the full visitor experience — quiz CTA, login, purchase, and general engagement.',
            'pills' => [
                [ 'icon'=>'🚀', 'label'=>'Get started',            'user_input'=>'Get started',                               'trigger_type'=>'ai',     'trigger_value'=>'',                     'condition'=>'is_visitor',               'style'=>'pill'   ],
                [ 'icon'=>'📝', 'label'=>'Take the free quiz',     'user_input'=>'Start free quiz',                           'trigger_type'=>'action', 'trigger_value'=>'open_quiz',            'condition'=>'is_visitor',               'style'=>'button' ],
                [ 'icon'=>'🔐', 'label'=>'Login to see my score',  'user_input'=>'Sign in / Register',                        'trigger_type'=>'action', 'trigger_value'=>'open_registration',    'condition'=>'is_visitor && quiz_taken', 'style'=>'button' ],
                [ 'icon'=>'❓', 'label'=>'How does it work?',      'user_input'=>'How does it work?',                         'trigger_type'=>'ai',     'trigger_value'=>'',                     'condition'=>'is_visitor',               'style'=>'pill'   ],
                [ 'icon'=>'📚', 'label'=>'What will I learn?',     'user_input'=>'What will I learn?',                        'trigger_type'=>'ai',     'trigger_value'=>'',                     'condition'=>'is_visitor',               'style'=>'pill'   ],
                [ 'icon'=>'🎉', 'label'=>'Purchase Now!',          'user_input'=>'PURCHASE',                                  'trigger_type'=>'action', 'trigger_value'=>'checkout_full_access', 'condition'=>'is_visitor',               'style'=>'button' ],
                [ 'icon'=>'✅', 'label'=>'Are you there?',         'user_input'=>'Are you there?',                            'trigger_type'=>'ai',     'trigger_value'=>'',                     'condition'=>'always',                   'style'=>'pill'   ],
                [ 'icon'=>'👤', 'label'=>"What's my user status?", 'user_input'=>"What's my user status?",                    'trigger_type'=>'ai',     'trigger_value'=>'',                     'condition'=>'always',                   'style'=>'pill'   ],
            ],
        ],
    ],
    'guest' => [
        [
            'name' => 'LeSAEp Guest Conversion Pack',
            'desc' => '6 pills to move guests from free lesson to purchase — results, offers, and quiz retake.',
            'pills' => [
                [ 'icon'=>'💰', 'label'=>'See my personalized offer', 'user_input'=>'Show me my personalized offer',               'trigger_type'=>'offer',  'trigger_value'=>'full_access',         'condition'=>'is_guest',                 'style'=>'pill'   ],
                [ 'icon'=>'🎁', 'label'=>'Unlock my free lesson',     'user_input'=>'Unlock my free lesson',                       'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'condition'=>'is_guest',                 'style'=>'button' ],
                [ 'icon'=>'📊', 'label'=>'View my quiz results',      'user_input'=>'Show me my quiz results and recommendations',  'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_guest',                 'style'=>'pill'   ],
                [ 'icon'=>'🎯', 'label'=>'What should I study?',      'user_input'=>'Based on my quiz, what should I study first?', 'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_guest',                 'style'=>'pill'   ],
                [ 'icon'=>'🔓', 'label'=>'Get full access',           'user_input'=>'Get full access',                             'trigger_type'=>'offer',  'trigger_value'=>'full_access',         'condition'=>'is_guest',                 'style'=>'button' ],
                [ 'icon'=>'🔄', 'label'=>'Retake the quiz',           'user_input'=>'Retake quiz',                                 'trigger_type'=>'action', 'trigger_value'=>'open_quiz',           'condition'=>'is_guest',                 'style'=>'pill'   ],
            ],
        ],
    ],
    'member' => [
        [
            'name' => 'LeSAEp Learner Navigation Pack',
            'desc' => '8 pills for active learners — lessons, results, AI coaching, sound search, and quiz retake.',
            'pills' => [
                [ 'icon'=>'📚', 'label'=>'My lesson library',         'user_input'=>'Open my lesson library',               'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'condition'=>'is_member',                'style'=>'button' ],
                [ 'icon'=>'🎓', 'label'=>'Start my first lesson',     'user_input'=>"I'm ready to start my first lesson",   'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'condition'=>'is_member && first_member','style'=>'button' ],
                [ 'icon'=>'📊', 'label'=>'My quiz results',           'user_input'=>'Show me my quiz results and progress', 'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member && quiz_taken',  'style'=>'pill'   ],
                [ 'icon'=>'🎯', 'label'=>'What should I study next?', 'user_input'=>'What should I study next?',            'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member && quiz_taken',  'style'=>'pill'   ],
                [ 'icon'=>'🔍', 'label'=>'Find R sound lessons',      'user_input'=>'Find R sound lessons for me',          'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member',                'style'=>'pill'   ],
                [ 'icon'=>'🔍', 'label'=>'Find TH lessons',           'user_input'=>'Find TH lessons for me',               'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member',                'style'=>'pill'   ],
                [ 'icon'=>'▶️', 'label'=>'Continue last lesson',      'user_input'=>'Continue my last lesson',              'trigger_type'=>'action', 'trigger_value'=>'continue_lesson',     'condition'=>'is_member',                'style'=>'button' ],
                [ 'icon'=>'🔄', 'label'=>'Retake quiz',               'user_input'=>'Retake my pronunciation assessment',   'trigger_type'=>'action', 'trigger_value'=>'open_quiz',           'condition'=>'is_member',                'style'=>'pill'   ],
            ],
        ],
    ],
];
?>
<details style="margin-top:28px;" open>
    <summary style="cursor:pointer;font-size:15px;font-weight:600;padding:10px 0;">
        🎯 Demo Pill Sets — load ready-made pills into any section
    </summary>
    <div style="margin-top:10px;padding:16px;background:#f9f9f9;border-radius:6px;">
        <p class="description" style="margin-bottom:16px;">
            Click <strong>Load Set →</strong> to append the demo pills to that section. Existing pills are kept. Review, reorder, then Save.
        </p>
        <div style="display:flex;flex-direction:column;gap:10px;">
        <?php
        $state_colors = [ 'visitor'=>'#c3c4c7', 'guest'=>'#f59e0b', 'member'=>'#86efac' ];
        foreach ( $pill_demos as $state => $sets ):
            $border = $state_colors[$state] ?? '#c3c4c7';
            foreach ( $sets as $set ):
        ?>
        <div style="background:#fff;border:1px solid <?php echo $border; ?>;border-left:4px solid <?php echo $border; ?>;border-radius:5px;padding:14px 16px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
            <div style="flex:1;">
                <strong style="font-size:13px;"><?php echo esc_html( ucfirst($state) . ' — ' . $set['name'] ); ?></strong>
                <span style="display:block;font-size:12px;color:#50575e;margin-top:3px;"><?php echo esc_html( $set['desc'] ); ?></span>
            </div>
            <button type="button"
                    class="button button-small flosc-load-pill-set"
                    data-state="<?php echo esc_attr( $state ); ?>"
                    data-pills="<?php echo esc_attr( json_encode( $set['pills'] ) ); ?>"
                    style="flex-shrink:0;white-space:nowrap;">
                Load Set →
            </button>
        </div>
        <?php endforeach; endforeach; ?>
        </div>
        <div style="background:#e7f3ff;border-left:4px solid #2196f3;padding:12px 15px;margin-top:16px;font-size:12px;">
            <strong>💡 Tip:</strong> Offer trigger pills use <code>full_access</code> as a placeholder. After loading, select your actual offer ID in the Trigger Value column.
        </div>
    </div>
</details>

</form>

<script>
(function() {
    // ---- Trigger value toggling ----
    window.floscToggleTriggerValue = function(select) {
        const row = select.closest('tr');
        const ai     = row.querySelector('.flosc-tv-ai');
        const offer  = row.querySelector('.flosc-tv-offer');
        const action = row.querySelector('.flosc-tv-action');
        const hidden = row.querySelector('.flosc-tv-hidden');
        const val = select.value;
        if (ai)     ai.style.display     = val === 'ai'     ? '' : 'none';
        if (offer)  offer.style.display  = val === 'offer'  ? '' : 'none';
        if (action) action.style.display = val === 'action' ? '' : 'none';
        // Sync hidden value
        if (val === 'ai' && hidden)     hidden.value = '';
        if (val === 'offer' && offer && hidden)  hidden.value = offer.value;
        if (val === 'action' && action && hidden) hidden.value = action.value;
    };

    // Sync hidden on offer/action change
    document.addEventListener('change', function(e) {
        const el = e.target;
        if (el.classList.contains('flosc-tv-offer') || el.classList.contains('flosc-tv-action')) {
            const hidden = el.closest('td').querySelector('.flosc-tv-hidden');
            if (hidden) hidden.value = el.value;
        }
    });

    // ---- Available data for new rows ----
    const conditionOptions = <?php echo json_encode($available_conditions); ?>;
    const actionOptions    = <?php echo json_encode($available_actions); ?>;
    const offerOptions     = <?php echo json_encode($offers_for_trigger); ?>;

    function buildOptions(opts, selected) {
        return Object.entries(opts).map(([v, l]) =>
            `<option value="${v}"${v === selected ? ' selected' : ''}>${l}</option>`
        ).join('');
    }

    function buildRow(state, defaultCond) {
        const offerOpts  = Object.keys(offerOptions).length
            ? buildOptions(offerOptions, 'full_access')
            : '<option value="full_access">full_access (default)</option>';
        const actionOpts = buildOptions(actionOptions, 'open_quiz');
        const condOpts   = buildOptions(conditionOptions, defaultCond);
        return `
        <tr class="flosc-ap-row" style="border-bottom:1px solid #eee;">
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_icon[]" style="width:44px;font-size:18px;text-align:center;border:1px solid #c3c4c7;border-radius:3px;" placeholder="💊"></td>
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_label[]" style="width:100%;box-sizing:border-box;" class="regular-text" placeholder="Pill label"></td>
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_user_input[]" style="width:100%;box-sizing:border-box;" placeholder="Same as label"></td>
            <td style="padding:5px 6px;">
                <select name="${state}_pill_trigger_type[]" style="width:100%;" onchange="floscToggleTriggerValue(this)">
                    <option value="ai" selected>💬 AI</option>
                    <option value="offer">💰 Offer</option>
                    <option value="action">⚡ Action</option>
                </select>
            </td>
            <td style="padding:5px 6px;" class="flosc-trigger-value-cell">
                <span class="flosc-tv-ai" style="color:#787c82;font-size:12px;padding:4px;">Sends user_input to AI</span>
                <select name="${state}_pill_trigger_value_offer[]" style="display:none;width:100%;" class="flosc-tv-offer">${offerOpts}</select>
                <select name="${state}_pill_trigger_value_action[]" style="display:none;width:100%;" class="flosc-tv-action">${actionOpts}</select>
                <input type="hidden" name="${state}_pill_trigger_value[]" value="" class="flosc-tv-hidden">
            </td>
            <td style="padding:5px 6px;"><select name="${state}_pill_conditions[]" style="width:100%;">${condOpts}</select></td>
            <td style="padding:5px 6px;">
                <select name="${state}_pill_style[]" style="width:100%;">
                    <option value="pill">pill</option>
                    <option value="button">button</option>
                    <option value="chip">chip</option>
                </select>
            </td>
            <td style="padding:5px 4px;"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
        </tr>`;
    }

    // Add blank pill
    document.querySelectorAll('.flosc-ap-add').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const state     = btn.dataset.state;
            const defaultCond = btn.dataset.defaultCond || ('is_' + state);
            const tbody = document.getElementById('tbody-' + state);
            const tmpDiv = document.createElement('div');
            tmpDiv.innerHTML = buildRow(state, defaultCond);
            const tr = tmpDiv.querySelector('tr');
            tbody.appendChild(tr);
            tr.querySelector('input[name$="_pill_label[]"]').focus();
        });
    });

    // Build a row pre-filled with demo pill data
    function buildRowWithData(state, pill) {
        const ttype = pill.trigger_type || 'ai';
        const tval  = pill.trigger_value || '';
        const cond  = pill.condition || ('is_' + state);
        const style = pill.style || 'pill';

        const offerOpts  = Object.keys(offerOptions).length
            ? buildOptions(offerOptions, ttype === 'offer' ? tval : 'full_access')
            : `<option value="full_access"${ttype === 'offer' && tval === 'full_access' ? ' selected' : ''}>full_access (default)</option>`;
        const actionOpts = buildOptions(actionOptions, ttype === 'action' ? tval : 'open_quiz');
        const condOpts   = buildOptions(conditionOptions, cond);

        const aiDisplay     = ttype === 'ai'     ? '' : 'display:none;';
        const offerDisplay  = ttype === 'offer'  ? '' : 'display:none;';
        const actionDisplay = ttype === 'action' ? '' : 'display:none;';

        const esc = s => s.replace(/"/g, '&quot;').replace(/</g, '&lt;');

        return `
        <tr class="flosc-ap-row" style="border-bottom:1px solid #eee;">
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_icon[]" value="${esc(pill.icon||'')}" style="width:44px;font-size:18px;text-align:center;border:1px solid #c3c4c7;border-radius:3px;" placeholder="💊"></td>
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_label[]" value="${esc(pill.label||'')}" style="width:100%;box-sizing:border-box;" class="regular-text" placeholder="Pill label"></td>
            <td style="padding:5px 6px;"><input type="text" name="${state}_pill_user_input[]" value="${esc(pill.user_input||'')}" style="width:100%;box-sizing:border-box;" placeholder="Same as label"></td>
            <td style="padding:5px 6px;">
                <select name="${state}_pill_trigger_type[]" style="width:100%;" onchange="floscToggleTriggerValue(this)">
                    <option value="ai"${ttype==='ai'?' selected':''}>💬 AI</option>
                    <option value="offer"${ttype==='offer'?' selected':''}>💰 Offer</option>
                    <option value="action"${ttype==='action'?' selected':''}>⚡ Action</option>
                </select>
            </td>
            <td style="padding:5px 6px;" class="flosc-trigger-value-cell">
                <span class="flosc-tv-ai" style="${aiDisplay}color:#787c82;font-size:12px;padding:4px;">Sends user_input to AI</span>
                <select name="${state}_pill_trigger_value_offer[]" style="${offerDisplay}width:100%;" class="flosc-tv-offer">${offerOpts}</select>
                <select name="${state}_pill_trigger_value_action[]" style="${actionDisplay}width:100%;" class="flosc-tv-action">${actionOpts}</select>
                <input type="hidden" name="${state}_pill_trigger_value[]" value="${esc(tval)}" class="flosc-tv-hidden">
            </td>
            <td style="padding:5px 6px;"><select name="${state}_pill_conditions[]" style="width:100%;">${condOpts}</select></td>
            <td style="padding:5px 6px;">
                <select name="${state}_pill_style[]" style="width:100%;">
                    <option value="pill"${style==='pill'?' selected':''}>pill</option>
                    <option value="button"${style==='button'?' selected':''}>button</option>
                    <option value="chip"${style==='chip'?' selected':''}>chip</option>
                </select>
            </td>
            <td style="padding:5px 4px;"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
        </tr>`;
    }

    // Load demo pill set
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.flosc-load-pill-set');
        if (!btn) return;
        const state = btn.dataset.state;
        const pills = JSON.parse(btn.dataset.pills || '[]');
        const tbody = document.getElementById('tbody-' + state);
        if (!tbody || !pills.length) return;
        pills.forEach(function(pill) {
            const tmpDiv = document.createElement('div');
            tmpDiv.innerHTML = buildRowWithData(state, pill);
            tbody.appendChild(tmpDiv.querySelector('tr'));
        });
        tbody.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const original = btn.textContent;
        btn.textContent = '✓ ' + pills.length + ' pills loaded!';
        btn.disabled = true;
        setTimeout(function() { btn.textContent = original; btn.disabled = false; }, 2000);
    });

    // Remove pill (delegated)
    document.querySelectorAll('.flosc-ap-table').forEach(function(table) {
        table.addEventListener('click', function(e) {
            if (e.target.classList.contains('flosc-ap-remove')) {
                e.target.closest('.flosc-ap-row').remove();
            }
        });
    });
})();
</script>
