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

if (!function_exists('flosc_autoprompt_is_machine_label')) {
    /**
     * Detect technical key-style labels (e.g., host_flow_music_overview)
     * that should not be shown as user-facing autoprompt labels.
     *
     * @param string $label
     * @return bool
     */
    function flosc_autoprompt_is_machine_label($label) {
        $label = trim((string) $label);
        if ($label === '') {
            return false;
        }
        return (bool) (strpos($label, '_') !== false && preg_match('/^[a-z0-9_]+$/', $label));
    }
}

$flosc_flow_key    = $GLOBALS['flosc_settings_key'] ?? '';
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';

$flosc_autoprompt_docs_base = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php'));

$flosc_autoprompt_docs_anchor = [
    'visitor' => 'visitor-autoprompts-intropanelshow-panel',
    'guest'   => 'guest-autoprompts-promptpanelshow-panel',
    'member'  => 'member-autoprompts-memberpromptpanelshow-panel',
];

// ============================================
// SAVE HANDLER
// ============================================
function flosc_handle_autoprompts_save() {
    $flosc_post = wp_unslash($_POST);

    if (!isset($flosc_post['save_autoprompts']) || !wp_verify_nonce(sanitize_text_field($flosc_post['flosc_autoprompts_nonce'] ?? ''), 'flosc_save_autoprompts')) {
        return;
    }
    if (!current_user_can('manage_options')) return;

    // §10: accept the posted flow key only if it is a known flow option key.
    // Validate without transforming, so the key matches where settings are stored.
    $fk = sanitize_text_field($flosc_post['flosc_flow_key'] ?? '');
    if ($fk === '' || !in_array($fk, flosc_known_flow_option_keys(), true)) return;

    $flosc_fs = get_option($fk, []);
    $states = ['visitor', 'guest', 'member'];
    $autoprompts = [];

    // Save panel header text per state
    $flosc_panel_headers = [];
    foreach ($states as $state) {
        $flosc_panel_headers[$state] = sanitize_text_field($flosc_post['panel_header_' . $state] ?? 'Try these AutoPrompts!');
    }

    // Save panel show/hide toggle per state (checkbox — absent means unchecked = 0)
    $flosc_panel_enabled = [];
    foreach ($states as $state) {
        $flosc_panel_enabled[$state] = isset($flosc_post['panel_enabled_' . $state]) ? 1 : 0;
    }

    // Companion Mode panel visibility (ship default: off)
    $flosc_companion_panel_enabled = isset($flosc_post['panel_enabled_companion']) ? 1 : 0;

    foreach ($states as $state) {
        $icons          = $flosc_post[$state . '_pill_icon']           ?? [];
        $labels         = $flosc_post[$state . '_pill_label']          ?? [];
        $inputs         = $flosc_post[$state . '_pill_user_input']     ?? [];
        $trigger_types  = $flosc_post[$state . '_pill_trigger_type']   ?? [];
        $trigger_values = $flosc_post[$state . '_pill_trigger_value']  ?? [];
        $flosc_conditions     = $flosc_post[$state . '_pill_conditions']     ?? [];
        $styles         = $flosc_post[$state . '_pill_style']          ?? [];

        $flosc_pills = [];
        foreach ($labels as $i => $label) {
            $label = sanitize_text_field($label);
            $input_text = sanitize_text_field($inputs[$i] ?? '');

            if ($label === '' && $input_text === '') {
                continue;
            }

            if (flosc_autoprompt_is_machine_label($label) && $input_text !== '') {
                $label = $input_text;
            }

            if ($label === '') {
                $label = $input_text;
            }

            if ($label === '') {
                continue;
            }

            $flosc_ttype  = sanitize_key($trigger_types[$i] ?? 'ai') ?: 'ai';
            $flosc_tval   = sanitize_text_field($trigger_values[$i] ?? '');
            $flosc_pills[] = [
                'icon'          => sanitize_text_field($icons[$i] ?? ''),
                'label'         => $label,
                'user_input'    => $input_text !== '' ? $input_text : $label,
                'trigger_type'  => $flosc_ttype,
                'trigger_value' => $flosc_tval,
                'action'        => $flosc_ttype === 'ai' ? '' : ($flosc_ttype === 'offer' ? 'show_offer_' . $flosc_tval : $flosc_tval),
                'conditions'    => sanitize_text_field($flosc_conditions[$i] ?? ('is_' . $state)),
                'style'         => 'pill',
            ];
        }
        $autoprompts[$state] = $flosc_pills;
    }

    $flosc_fs['autoprompts']              = $autoprompts;
    $flosc_fs['autoprompt_headers']       = $flosc_panel_headers;
    $flosc_fs['autoprompt_panel_enabled'] = $flosc_panel_enabled;
    $flosc_fs['autoprompt_companion_enabled'] = $flosc_companion_panel_enabled;
    update_option($fk, $flosc_fs);

    $ivr = sanitize_file_name($flosc_post['flosc_ivr'] ?? '');
    wp_safe_redirect(admin_url('admin.php?page=flosc-settings&ivr=' . rawurlencode($ivr) . '&tab=autoprompts&saved=1'));
    exit;
}
flosc_handle_autoprompts_save();
// $flosc_get is prepared by admin/settings.php before include (tab/view state).
if ( ! isset( $flosc_get ) || ! is_array( $flosc_get ) ) {
	$flosc_get = array();
}

// ============================================
// LOAD CURRENT DATA
// ============================================
$flosc_fs             = $flosc_flow_key ? get_option($flosc_flow_key, []) : [];

$flosc_flow_display_name = trim((string)($flosc_fs['identity']['name'] ?? ''));
if ($flosc_flow_display_name === '') {
    $flosc_flow_display_name = trim((string)($flosc_fs['name'] ?? ''));
}
if ($flosc_flow_display_name === '') {
    $flosc_flow_display_name = trim((string) pathinfo((string) $flosc_current_ivr, PATHINFO_FILENAME));
}
if ($flosc_flow_display_name === '') {
    $flosc_flow_display_name = 'Flow';
}

// Strip all accumulated backslash layers from previously corrupted DB data
$flosc_ap_raw  = $flosc_fs['autoprompts'] ?? [];
$flosc_ap_prev = null;
while ( $flosc_ap_prev !== $flosc_ap_raw ) { $flosc_ap_prev = $flosc_ap_raw; $flosc_ap_raw = stripslashes_deep( $flosc_ap_raw ); }
$flosc_saved_prompts = $flosc_ap_raw;

$flosc_ah_raw  = $flosc_fs['autoprompt_headers'] ?? [];
$flosc_ah_prev = null;
while ( $flosc_ah_prev !== $flosc_ah_raw ) { $flosc_ah_prev = $flosc_ah_raw; $flosc_ah_raw = stripslashes_deep( $flosc_ah_raw ); }
$flosc_saved_headers = $flosc_ah_raw;
$flosc_panel_headers  = [
    'visitor' => $flosc_saved_headers['visitor'] ?? 'Try these AutoPrompts!',
    'guest'   => $flosc_saved_headers['guest']   ?? 'You\'re almost there — try these:',
    'member'  => $flosc_saved_headers['member']  ?? 'Welcome back! What would you like to do?',
];
$flosc_saved_panel_enabled = $flosc_fs['autoprompt_panel_enabled'] ?? [];
$flosc_panel_enabled = [
    'visitor' => $flosc_saved_panel_enabled['visitor'] ?? 1,
    'guest'   => $flosc_saved_panel_enabled['guest']   ?? 1,
    'member'  => $flosc_saved_panel_enabled['member']  ?? 1,
];
$flosc_companion_panel_enabled = !empty($flosc_fs['autoprompt_companion_enabled']);

$flosc_prompts = [
    'visitor' => is_array($flosc_saved_prompts['visitor'] ?? null) ? $flosc_saved_prompts['visitor'] : [],
    'guest'   => is_array($flosc_saved_prompts['guest'] ?? null)   ? $flosc_saved_prompts['guest']   : [],
    'member'  => is_array($flosc_saved_prompts['member'] ?? null)  ? $flosc_saved_prompts['member']  : [],
];

// Normalize visible labels so machine keys are not shown in UI.
foreach ($flosc_prompts as $flosc_state => &$flosc_state_pills) {
    if (!is_array($flosc_state_pills)) {
        $flosc_state_pills = [];
        continue;
    }
    foreach ($flosc_state_pills as &$flosc_pill) {
        if (!is_array($flosc_pill)) {
            continue;
        }
        $flosc_current_label = trim((string) ($flosc_pill['label'] ?? ''));
        $flosc_current_input = trim((string) ($flosc_pill['user_input'] ?? ''));

        if (flosc_autoprompt_is_machine_label($flosc_current_label) && $flosc_current_input !== '') {
            $flosc_pill['label'] = $flosc_current_input;
            $flosc_current_label = $flosc_current_input;
        }

        if ($flosc_current_label === '' && $flosc_current_input !== '') {
            $flosc_pill['label'] = $flosc_current_input;
        }

        // AutoPrompts panel currently renders pills only.
        $flosc_pill['style'] = 'pill';
    }
    unset($flosc_pill);
}
unset($flosc_state_pills);

// Available offers for trigger dropdown
$flosc_offers_for_trigger = [];
$flosc_flow_id_for_offers = $flosc_flow_key ? str_replace('flosc_flow_', '', $flosc_flow_key) : null;
if (function_exists('flosc') && $flosc_flow_id_for_offers) {
    foreach (flosc()->sale()->offers()->get_all_offers($flosc_flow_id_for_offers) as $flosc_oid => $flosc_o) {
        $flosc_offers_for_trigger[$flosc_oid] = $flosc_o['name'] ?? $flosc_oid;
    }
}

// Available actions
$flosc_available_actions = [
    'open_quiz'            => 'open_quiz — Take Quiz',
    'open_free_lesson'     => 'open_free_lesson — View Free Lesson',
    'open_lesson_library'  => 'open_lesson_library — Lesson Library',
    'open_quiz_library'    => 'open_quiz_library — Quiz Library',
    'open_registration'    => 'open_registration — Sign Up / Register',
    'open_support'         => 'open_support — Support / Help',
    'checkout_full_access' => 'checkout_full_access — Checkout (Full Access)',
    'open_last_lesson'     => 'open_last_lesson — Continue Last Lesson',
    'show_full_score'      => 'show_full_score — Show Full Score',
];

$flosc_available_conditions = [
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

// Declare before table rendering so expected-behavior rows can call it safely.
if (!function_exists('flosc_autoprompt_expected_behavior_text')) {
    function flosc_autoprompt_expected_behavior_text($flosc_state, array $flosc_item) {
        $flosc_label = trim((string) ($flosc_item['label'] ?? ''));
        $flosc_user_input = trim((string) ($flosc_item['user_input'] ?? ''));
        $flosc_input_to_send = $flosc_user_input !== '' ? $flosc_user_input : $flosc_label;
        $flosc_trigger_type = strtolower(trim((string) ($flosc_item['trigger_type'] ?? 'ai')));
        $flosc_trigger_value = trim((string) ($flosc_item['trigger_value'] ?? ''));
        $flosc_condition = trim((string) ($flosc_item['conditions'] ?? ($flosc_item['condition'] ?? ('is_' . $flosc_state))));
        $flosc_style = trim((string) ($flosc_item['style'] ?? 'pill'));

        $flosc_visibility_text = $flosc_condition !== '' ? $flosc_condition : ('is_' . $flosc_state);

        if ($flosc_trigger_type === 'offer') {
            $flosc_offer_id = $flosc_trigger_value !== '' ? $flosc_trigger_value : 'full_access';
            $flosc_trigger_text = 'The offer flow runs and attempts to display offer id "' . $flosc_offer_id . '" in chat.';
        } elseif ($flosc_trigger_type === 'action') {
            $flosc_action = $flosc_trigger_value !== '' ? $flosc_trigger_value : 'open_quiz';
            $flosc_trigger_text = 'The action trigger runs with value "' . $flosc_action . '" and should open the matching in-chat UI flow.';
        } else {
            $flosc_trigger_text = 'The AI trigger runs and sends this text into the chat pipeline for a conversational response.';
        }

        return sprintf(
            'User sees a %1$flosc_s labeled "%2$flosc_s" when condition "%3$flosc_s" is true. On click, "%4$flosc_s" is sent as user text. %5$flosc_s',
            $flosc_style !== '' ? $flosc_style : 'pill',
            $flosc_label !== '' ? $flosc_label : '(empty label)',
            $flosc_visibility_text,
            $flosc_input_to_send,
            $flosc_trigger_text
        );
    }
}
?>

<?php if (isset($flosc_get['saved'])): ?>
<div class="notice notice-success is-dismissible"><p>AutoPrompts saved successfully.</p></div>
<?php endif; ?>

<p>Configure the quick-reply pills shown in FLOSC chat per user state. Pills send a chat message to the AI, trigger an offer to display, or fire an in-chat action (quiz, lesson, checkout, etc.).</p>

<div class="flosc-autoprompt-section flosc-autoprompt-section--companion">
    <h3 class="flosc-autoprompt-section__title">
        <span>🤝 Companion Mode AutoPrompt Panel</span>
    </h3>
    <p class="description flosc-ap-desc-flush">Controls whether AutoPrompt panels render inside the Companion widget panel. Default is off for cleaner companion shipping behavior.</p>
    <label class="flosc-autoprompt-section__panel-toggle">
        <input type="checkbox" name="panel_enabled_companion" value="1" <?php checked($flosc_companion_panel_enabled, true); ?>>
        Show AutoPrompt panel in Companion Mode
    </label>
</div>

<?php wp_nonce_field('flosc_save_autoprompts', 'flosc_autoprompts_nonce'); ?>
    <input type="hidden" name="flosc_flow_key" value="<?php echo esc_attr($flosc_flow_key); ?>">
    <input type="hidden" name="flosc_ivr" value="<?php echo esc_attr($flosc_current_ivr); ?>">

<?php
$flosc_state_config = [
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
        'label'  => 'Member AutoPrompts — MemberPromptPanel',
        'icon'   => '🔵',
        'color'  => '#eff6ff',
        'border' => '#93c5fd',
        'panel'  => 'MemberPromptPanel',
        'desc'   => 'Logged in and purchased. Shown in the <strong>MemberPromptPanel</strong>.',
    ],
];

foreach ($flosc_state_config as $flosc_state => $flosc_sc):
    $flosc_pills = $flosc_prompts[$flosc_state];
?>
<div class="flosc-autoprompt-section flosc-autoprompt-section--<?php echo esc_attr( $flosc_state ); ?>">
    <h3 class="flosc-autoprompt-section__title">
        <span><?php echo esc_html( $flosc_sc['icon'] ); ?> <?php echo esc_html( $flosc_sc['label'] ); ?></span>
        <label class="flosc-autoprompt-section__panel-toggle">
            <input type="checkbox" name="panel_enabled_<?php echo esc_attr( $flosc_state ); ?>" value="1" <?php checked($flosc_panel_enabled[$flosc_state], 1); ?>>
            Show panel
        </label>
        <a href="<?php echo esc_url($flosc_autoprompt_docs_base . '#' . ($flosc_autoprompt_docs_anchor[$flosc_state] ?? 'admin-doc-jit-links')); ?>"
           class="flosc-autoprompt-section__docs">Docs</a>
    </h3>
    <p class="description flosc-ap-desc-flush"><?php echo esc_html( $flosc_sc['desc'] ); ?></p>

    <!-- Panel header text -->
    <div class="flosc-ap-field">
        <label class="flosc-ap-field__label">
            Panel Header Message
            <span class="flosc-ap-hint">(the eyebrow text shown above the pills)</span>
        </label>
        <input
            type="text"
            name="panel_header_<?php echo esc_attr( $flosc_state ); ?>"
            value="<?php echo esc_attr($flosc_panel_headers[$flosc_state]); ?>"
            class="large-text flosc-ap-header-input"
            data-state="<?php echo esc_attr( $flosc_state ); ?>"
            placeholder="Try these AutoPrompts!"
        >
    </div>

    <!-- Live pill preview -->
    <div class="flosc-ap-preview">
        <div class="flosc-ap-preview__eyebrow"><?php echo esc_html( $flosc_sc['panel'] ); ?> — Preview</div>
        <div id="header-preview-<?php echo esc_attr( $flosc_state ); ?>" class="flosc-ap-preview__header"><?php echo esc_html( $flosc_panel_headers[$flosc_state] ); ?></div>
        <div id="preview-<?php echo esc_attr( $flosc_state ); ?>" class="flosc-ap-preview__pills">
            <?php foreach ($flosc_pills as $flosc_pill): ?>
            <span class="flosc-preview-pill-chip">
                <?php if ($flosc_pill['icon']): ?><span><?php echo esc_html($flosc_pill['icon']); ?></span><?php endif; ?>
                <?php echo esc_html($flosc_pill['label']); ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flosc-ap-table-wrap">
    <table class="widefat flosc-ap-table">
        <thead>
            <tr class="flosc-ap-table__head">
                <th class="flosc-ap-col-icon">Icon</th>
                <th class="flosc-ap-col-label">Label</th>
                <th colspan="3" class="flosc-ap-col-input">User Input <span class="flosc-ap-hint">(sent to AI; blank=label)</span></th>
                <th class="flosc-ap-col-remove"></th>
            </tr>
        </thead>
        <tbody id="tbody-<?php echo esc_attr( $flosc_state ); ?>">
        <?php foreach ($flosc_pills as $flosc_pill):
            $flosc_ttype = $flosc_pill['trigger_type'] ?? 'ai';
            $flosc_tval  = $flosc_pill['trigger_value'] ?? '';
            // Back-compat: derive trigger_type from action field
            if (!isset($flosc_pill['trigger_type']) && !empty($flosc_pill['action'])) {
                if (strpos($flosc_pill['action'], 'show_offer_') === 0) {
                    $flosc_ttype = 'offer';
                    $flosc_tval  = str_replace('show_offer_', '', $flosc_pill['action']);
                } else {
                    $flosc_ttype = 'action';
                    $flosc_tval  = $flosc_pill['action'];
                }
            }
            $flosc_expected_behavior = flosc_autoprompt_expected_behavior_text($flosc_state, [
                'label'         => $flosc_pill['label'] ?? '',
                'user_input'    => $flosc_pill['user_input'] ?? '',
                'trigger_type'  => $flosc_ttype,
                'trigger_value' => $flosc_tval,
                'conditions'    => $flosc_pill['conditions'] ?? ('is_' . $flosc_state),
                'style'         => $flosc_pill['style'] ?? 'pill',
            ]);
        ?>
            <tr class="flosc-ap-row flosc-ap-item-row flosc-ap-row-primary">
                <td class="flosc-ap-cell" rowspan="2"><div class="flosc-ap-microlabel">Icon</div><input type="text" name="<?php echo esc_attr( $flosc_state ); ?>_pill_icon[]" value="<?php echo esc_attr($flosc_pill['icon'] ?? ''); ?>" class="flosc-ap-icon-input" placeholder="💊"></td>
                <td class="flosc-ap-cell"><div class="flosc-ap-microlabel">Label</div><input type="text" name="<?php echo esc_attr( $flosc_state ); ?>_pill_label[]" value="<?php echo esc_attr($flosc_pill['label'] ?? ''); ?>" class="flosc-ap-input" placeholder="Pill label"></td>
                <td class="flosc-ap-cell" colspan="3"><div class="flosc-ap-microlabel">User Input</div><input type="text" name="<?php echo esc_attr( $flosc_state ); ?>_pill_user_input[]" value="<?php echo esc_attr($flosc_pill['user_input'] ?? ''); ?>" class="flosc-ap-input flosc-ap-input--w400" placeholder="Same as label"></td>
                <td class="flosc-ap-cell" rowspan="2"><button type="button" class="button flosc-ap-remove" title="Remove">&times;</button></td>
            </tr>
            <tr class="flosc-ap-row flosc-ap-row-secondary">
                <td class="flosc-ap-cell">
                    <div class="flosc-ap-microlabel">Conditions</div>
                    <select name="<?php echo esc_attr( $flosc_state ); ?>_pill_conditions[]" class="flosc-ap-select">
                        <?php
                        $flosc_cond = $flosc_pill['conditions'] ?? ('is_' . $flosc_state);
                        $flosc_found = false;
                        foreach ($flosc_available_conditions as $flosc_cval => $flosc_cdesc):
                            if ($flosc_cond === $flosc_cval) $flosc_found = true;
                        ?>
                            <option value="<?php echo esc_attr($flosc_cval); ?>" <?php selected($flosc_cond, $flosc_cval); ?>><?php echo esc_html($flosc_cdesc); ?></option>
                        <?php endforeach; ?>
                        <?php if (!$flosc_found && $flosc_cond): ?>
                            <option value="<?php echo esc_attr($flosc_cond); ?>" selected><?php echo esc_html($flosc_cond); ?> (custom)</option>
                        <?php endif; ?>
                    </select>
                </td>
                <td class="flosc-ap-cell flosc-ap-cell--w150">
                    <div class="flosc-ap-microlabel">Trigger</div>
                    <select name="<?php echo esc_attr( $flosc_state ); ?>_pill_trigger_type[]" class="flosc-ap-select flosc-ap-select--w150 flosc-ap-trigger-type">
                        <option value="ai"     <?php selected($flosc_ttype,'ai'); ?>>💬 AI</option>
                        <option value="offer"  <?php selected($flosc_ttype,'offer'); ?>>💰 Offer</option>
                        <option value="action" <?php selected($flosc_ttype,'action'); ?>>⚡ Action</option>
                    </select>
                </td>
                <td class="flosc-trigger-value-cell">
                    <div class="flosc-ap-microlabel">Trigger Value</div>
                    <span class="flosc-tv-ai flosc-tv-hint<?php echo esc_attr( $flosc_ttype !== 'ai' ? ' flosc-hidden' : '' ); ?>">Sends user_input to AI</span>
                    <select name="<?php echo esc_attr( $flosc_state ); ?>_pill_trigger_value_offer[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-offer<?php echo esc_attr( $flosc_ttype !== 'offer' ? ' flosc-hidden' : '' ); ?>">
                        <?php foreach ($flosc_offers_for_trigger as $flosc_oid => $flosc_oname): ?>
                            <option value="<?php echo esc_attr($flosc_oid); ?>" <?php selected($flosc_tval, $flosc_oid); ?>><?php echo esc_html($flosc_oname); ?> (<?php echo esc_html($flosc_oid); ?>)</option>
                        <?php endforeach; ?>
                        <?php if (empty($flosc_offers_for_trigger)): ?>
                            <option value="full_access" <?php selected($flosc_tval,'full_access'); ?>>full_access (default)</option>
                        <?php endif; ?>
                    </select>
                    <select name="<?php echo esc_attr( $flosc_state ); ?>_pill_trigger_value_action[]" class="flosc-ap-select flosc-ap-select--w200 flosc-tv-action<?php echo esc_attr( $flosc_ttype !== 'action' ? ' flosc-hidden' : '' ); ?>">
                        <?php foreach ($flosc_available_actions as $flosc_aval => $flosc_adesc): ?>
                            <option value="<?php echo esc_attr($flosc_aval); ?>" <?php selected($flosc_tval, $flosc_aval); ?>><?php echo esc_html($flosc_adesc); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="<?php echo esc_attr( $flosc_state ); ?>_pill_trigger_value[]" value="<?php echo esc_attr($flosc_tval); ?>" class="flosc-tv-hidden">
                </td>
            </tr>
            <tr class="flosc-ap-expected-row">
                <td colspan="6" class="flosc-ap-expected-cell">
                    <span class="flosc-ap-expected-label">Expected behavior:</span>
                    <span class="flosc-ap-expected-text"><?php echo esc_html($flosc_expected_behavior); ?></span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <p class="flosc-ap-add-row">
        <button type="button" class="button flosc-ap-add" data-state="<?php echo esc_attr( $flosc_state ); ?>" data-default-cond="is_<?php echo esc_attr( $flosc_state ); ?>">+ Add Pill</button>
    </p>
</div>
<?php endforeach; ?>

<div class="flosc-ap-legend">
    <p class="description flosc-ap-legend__intro">
        <strong>Trigger types:</strong>
        💬 <strong>AI</strong> — sends User Input text as a chat message (AI responds naturally).
        💰 <strong>Offer</strong> — immediately displays the selected offer card/modal in chat.
        ⚡ <strong>Action</strong> — fires an in-chat action (open quiz, lesson library, checkout, etc).
    </p>
    <?php submit_button('Save AutoPrompts', 'primary', 'save_autoprompts', false); ?>
</div>

<!-- ── Demo Pill Sets ─────────────────────────────────────────────────────── -->
<?php
$flosc_pill_demos = [
    'visitor' => [
        [
            'name' => 'Visitor Starter Pack',
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
            'name' => 'Guest Conversion Pack',
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
            'name' => 'Member Navigation Pack',
            'desc' => '8 pills for active members — lessons, results, AI coaching, sound search, and quiz retake.',
            'pills' => [
                [ 'icon'=>'📚', 'label'=>'My lesson library',         'user_input'=>'Open my lesson library',               'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'condition'=>'is_member',                'style'=>'button' ],
                [ 'icon'=>'🎓', 'label'=>'Start my first lesson',     'user_input'=>"I'm ready to start my first lesson",   'trigger_type'=>'action', 'trigger_value'=>'open_lesson_library', 'condition'=>'is_member && first_member','style'=>'button' ],
                [ 'icon'=>'📊', 'label'=>'My quiz results',           'user_input'=>'Show me my quiz results and progress', 'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member && quiz_taken',  'style'=>'pill'   ],
                [ 'icon'=>'🎯', 'label'=>'What should I study next?', 'user_input'=>'What should I study next?',            'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member && quiz_taken',  'style'=>'pill'   ],
                [ 'icon'=>'🔍', 'label'=>'Find R sound lessons',      'user_input'=>'Find R sound lessons for me',          'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member',                'style'=>'pill'   ],
                [ 'icon'=>'🔍', 'label'=>'Find TH lessons',           'user_input'=>'Find TH lessons for me',               'trigger_type'=>'ai',     'trigger_value'=>'',                    'condition'=>'is_member',                'style'=>'pill'   ],
                [ 'icon'=>'▶️', 'label'=>'Continue last lesson',      'user_input'=>'Continue my last lesson',              'trigger_type'=>'action', 'trigger_value'=>'continue_lesson',     'condition'=>'is_member',                'style'=>'button' ],
                [ 'icon'=>'🔄', 'label'=>'Retake quiz',               'user_input'=>'Retake my assessment',   'trigger_type'=>'action', 'trigger_value'=>'open_quiz',           'condition'=>'is_member',                'style'=>'pill'   ],
            ],
        ],
    ],
];
?>
<div class="flosc-demo-sets-section">
    <h3 class="flosc-demo-sets-heading">🎯 Demo Pill Sets — load ready-made pills into any section</h3>
    <div class="flosc-demo-sets-body">
        <p class="description flosc-demo-sets-desc">
            Click <strong>Load Set →</strong> to append the demo pills to that section, or load individual items from each set preview below. Existing pills are kept. Review, edit, then Save.
        </p>
        <div class="flosc-demo-set-list">
        <?php
        foreach ( $flosc_pill_demos as $flosc_state => $flosc_sets ):
            foreach ( $flosc_sets as $flosc_set ):
        ?>
        <div class="flosc-demo-set-card flosc-demo-set-card--state-<?php echo esc_attr( $flosc_state ); ?>">
            <div class="flosc-demo-set-card-top">
                <div class="flosc-demo-set-meta">
                    <strong class="flosc-demo-set-title"><?php echo esc_html( ucfirst($flosc_state) . ' — ' . $flosc_set['name'] ); ?></strong>
                    <span class="flosc-demo-set-description"><?php echo esc_html( $flosc_set['desc'] ); ?></span>
                </div>
                <button type="button"
                        class="button button-small flosc-load-pill-set"
                        data-state="<?php echo esc_attr( $flosc_state ); ?>"
                        data-pills="<?php echo esc_attr( wp_json_encode( $flosc_set['pills'] ) ); ?>">
                    Load Set →
                </button>
            </div>
            <details class="flosc-demo-set-preview">
                <summary>View items (<?php echo intval(count($flosc_set['pills'])); ?>)</summary>
                <div class="flosc-demo-set-preview-body flosc-ap-table-wrap">
                    <table class="widefat striped flosc-demo-items-table">
                        <thead>
                            <tr>
                                <th>Icon</th>
                                <th>Label</th>
                                <th>User Input</th>
                                <th>Trigger Type</th>
                                <th>Trigger Value</th>
                                <th>Condition</th>
                                <th>Style</th>
                                <th>Load</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( (array) $flosc_set['pills'] as $flosc_item_index => $flosc_item ): ?>
                            <?php
                            $flosc_behavior_text = flosc_autoprompt_expected_behavior_text($flosc_state, (array) $flosc_item);
                            $flosc_pair_class = ((int) $flosc_item_index % 2 === 0) ? 'is-even' : 'is-odd';
                            ?>
                            <tr class="flosc-demo-item-row <?php echo esc_attr($flosc_pair_class); ?>">
                                <td><?php echo esc_html( (string) ($flosc_item['icon'] ?? '') ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['label'] ?? '') ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['user_input'] ?? '') ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['trigger_type'] ?? 'ai') ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['trigger_value'] ?? '') ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['condition'] ?? ('is_' . $flosc_state)) ); ?></td>
                                <td><?php echo esc_html( (string) ($flosc_item['style'] ?? 'pill') ); ?></td>
                                <td>
                                    <button type="button"
                                            class="button button-small flosc-load-pill-item"
                                            data-state="<?php echo esc_attr( $flosc_state ); ?>"
                                            data-pill="<?php echo esc_attr( wp_json_encode( $flosc_item ) ); ?>">
                                        Load Item
                                    </button>
                                </td>
                            </tr>
                            <tr class="flosc-demo-item-expected-row <?php echo esc_attr($flosc_pair_class); ?>">
                                <td colspan="8">
                                    <span class="flosc-demo-item-expected-label">Expected behavior:</span>
                                    <span class="flosc-demo-item-expected-text"><?php echo esc_html($flosc_behavior_text); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
        <?php endforeach; endforeach; ?>
        </div>
        <div class="flosc-demo-sets-tip">
            <strong>💡 Tip:</strong> Offer trigger pills use <code>full_access</code> as a placeholder. After loading, select your actual offer ID in the Trigger Value column.
        </div>
    </div>
</div>

<?php
$flosc_autoprompts_admin_config = [
    'conditions' => $flosc_available_conditions,
    'actions'    => $flosc_available_actions,
    'offers'     => $flosc_offers_for_trigger,
];

if (wp_script_is('flosc-autoprompts-admin', 'enqueued')) {
    wp_add_inline_script(
        'flosc-autoprompts-admin',
        'window.floscAutopromptsAdminConfig = ' . wp_json_encode($flosc_autoprompts_admin_config) . ';',
        'before'
    );
}
?>
