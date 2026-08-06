<?php
/**
 * FLOSC Engagement Tab
 *
 * Purpose: trigger floscFlow responses when profiles behave a certain way.
 * Structure: accordion by audience (Visitor / Guest / Member), each with
 * when → condition → then (chat and/or email). Freeform conditions use the same
 * language as Offers (FLOSC_Condition_Evaluator).
 *
 * Not: offer builder (→ Offers). Not: letter body editor (→ Email). Not: F→L→O→S→C map (→ Flow).
 */

if (!defined('ABSPATH')) {
    exit;
}

flosc_tab_header('📊', 'Engagement');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr   = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_flow_id       = $flosc_current_ivr !== '' ? pathinfo($flosc_current_ivr, PATHINFO_FILENAME) : '';

$flosc_base_q = ['page' => 'flosc-settings', 'ivr' => $flosc_current_ivr];
$flosc_tab_url = static function ($tab) use ($flosc_base_q) {
    return add_query_arg(array_merge($flosc_base_q, ['tab' => $tab]), admin_url('admin.php'));
};

// ── Default rule parameters (starter values only — floscAdmin edits these) ────
$flosc_default_rules = [
    [
        'id'            => 'visitor_chat_open',
        'title'         => 'Visitor · chat open',
        'audience'      => 'visitor',
        'enabled'       => '',
        'trigger'       => 'chat_open',
        'trigger_days'  => '0',
        'condition'     => 'is_visitor',
        'action_chat'   => '1',
        'action_email'  => '',
        'email_template'=> '',
        'chat_message'  => 'Hi — ask me anything.',
    ],
    [
        'id'            => 'guest_return_chat',
        'title'         => 'Guest · welcome back',
        'audience'      => 'guest',
        'enabled'       => '1',
        'trigger'       => 'return_login',
        'trigger_days'  => '0',
        'condition'     => 'is_guest && login_count >= 2',
        'action_chat'   => '1',
        'action_email'  => '',
        'email_template'=> '',
        'chat_message'  => 'Welcome back!',
    ],
    [
        'id'            => 'guest_inactive_email',
        'title'         => 'Guest · inactive email',
        'audience'      => 'guest',
        'enabled'       => '',
        'trigger'       => 'inactive_days',
        'trigger_days'  => '7',
        'condition'     => 'is_guest && !purchased',
        'action_chat'   => '',
        'action_email'  => '1',
        'email_template'=> 'reengagement',
        'chat_message'  => '',
    ],
    [
        'id'            => 'member_inactive_email',
        'title'         => 'Member · inactive email',
        'audience'      => 'member',
        'enabled'       => '1',
        'trigger'       => 'inactive_days',
        'trigger_days'  => '10',
        'condition'     => 'is_member',
        'action_chat'   => '',
        'action_email'  => '1',
        'email_template'=> 'reengagement',
        'chat_message'  => '',
    ],
    [
        'id'            => 'member_return_chat',
        'title'         => 'Member · welcome back',
        'audience'      => 'member',
        'enabled'       => '',
        'trigger'       => 'return_login',
        'trigger_days'  => '0',
        'condition'     => 'is_member && login_count >= 2',
        'action_chat'   => '1',
        'action_email'  => '',
        'email_template'=> '',
        'chat_message'  => 'Welcome back.',
    ],
];

$flosc_rules = $flosc_flow_settings['engagement_rules'] ?? null;
if (!is_array($flosc_rules) || empty($flosc_rules)) {
    $flosc_rules = $flosc_default_rules;
}

$flosc_guest_access_days = isset($flosc_flow_settings['guest_access_days'])
    ? max(0, min(365, intval($flosc_flow_settings['guest_access_days'])))
    : 0;

// Labels describe WHEN the user sees the response (not just the condition name).
$flosc_triggers = [
    'chat_open'               => 'Chat opens — once per browser session',
    'return_login'            => 'Return login — when chat opens (login #2+)',
    'inactive_days'           => 'Inactive for N days — email schedule (chat not auto-shown)',
    'days_since_registration' => 'Days since registration ≥ N — when chat opens',
    'profile_incomplete'      => 'Profile incomplete — when chat opens',
];
$flosc_email_templates = [
    ''              => '— none —',
    'reengagement'  => 'Re-engagement (Email tab template)',
    'guest_welcome' => 'Guest welcome',
    'guest_day10'   => 'Guest sequence · early window (Email keys guest_day10_*)',
    'guest_day20'   => 'Guest sequence · mid window (guest_day20_*)',
    'guest_day28'   => 'Guest sequence · late window (guest_day28_*)',
];

/**
 * One rule as a collapsible accordion (title in summary).
 *
 * @param array  $rule
 * @param string $audience visitor|guest|member
 * @param int    $index
 * @param bool   $open
 */
$flosc_render_rule = static function ($rule, $audience, $index, $open = false) use ($flosc_triggers, $flosc_email_templates) {
    $rid = sanitize_key((string) ($rule['id'] ?? ('rule_' . $index)));
    $title = trim((string) ($rule['title'] ?? ''));
    if ($title === '') {
        $title = $rid !== '' ? $rid : ('Rule ' . ((int) $index + 1));
    }
    $en  = !empty($rule['enabled']);
    $trig = sanitize_key((string) ($rule['trigger'] ?? 'chat_open'));
    $days = max(0, min(365, intval($rule['trigger_days'] ?? 0)));
    $cond = (string) ($rule['condition'] ?? '');
    $chat_on = !empty($rule['action_chat']);
    $email_on = !empty($rule['action_email']);
    $tpl = sanitize_key((string) ($rule['email_template'] ?? ''));
    $msg = (string) ($rule['chat_message'] ?? '');
    $trig_lab = $flosc_triggers[$trig] ?? $trig;
    $preview_bits = [];
    if ($en) {
        $preview_bits[] = 'active';
    } else {
        $preview_bits[] = 'off';
    }
    if ($chat_on) {
        $preview_bits[] = 'chat';
    }
    if ($email_on) {
        $preview_bits[] = 'email';
    }
    $preview_bits[] = $trig_lab;
    $preview = implode(' · ', $preview_bits);
    ?>
    <details class="flosc-eng-rule" data-audience="<?php echo esc_attr($audience); ?>" data-rule-index="<?php echo esc_attr((string) $index); ?>"<?php echo $open ? ' open' : ''; ?>>
        <summary class="flosc-eng-rule__summary">
            <span class="flosc-eng-rule__summary-title"><?php echo esc_html($title); ?></span>
            <span class="flosc-eng-rule__summary-preview"><?php echo esc_html($preview); ?></span>
        </summary>
        <div class="flosc-eng-rule__body">
            <input type="hidden" name="engagement_rule_id[]" class="flosc-eng-rule-id" value="<?php echo esc_attr($rid); ?>">
            <input type="hidden" name="engagement_rule_audience[]" class="flosc-eng-rule-audience" value="<?php echo esc_attr($audience); ?>">
            <p style="margin:0 0 10px;display:flex;flex-wrap:wrap;gap:12px 20px;align-items:center;">
                <label>
                    <input type="checkbox" class="flosc-eng-rule-enabled" name="engagement_rule_enabled[<?php echo esc_attr((string) $index); ?>]" value="1" <?php checked($en); ?>>
                    <strong>Rule active</strong>
                </label>
                <button type="button" class="button-link-delete flosc-eng-rule-remove" style="color:#b32d2e;">Remove rule</button>
            </p>
            <table class="form-table" style="margin:0;">
                <tr>
                    <th scope="row"><label>Title</label></th>
                    <td>
                        <input type="text" name="engagement_rule_title[]" class="regular-text flosc-eng-rule-title"
                               value="<?php echo esc_attr($title); ?>" placeholder="e.g. Welcome back chat">
                        <p class="description">Shown in the rule accordion header. Id: <code class="flosc-eng-rule-id-label"><?php echo esc_html($rid); ?></code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">When</th>
                    <td>
                        <select name="engagement_rule_trigger[]" class="regular-text flosc-eng-rule-trigger">
                            <?php foreach ($flosc_triggers as $k => $lab) : ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($trig, $k); ?>><?php echo esc_html($lab); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flosc-email-inline-label-right">N =
                            <input type="number" name="engagement_rule_days[]" value="<?php echo esc_attr((string) $days); ?>"
                                   min="0" max="365" class="small-text"> days
                        </label>
                        <p class="description">
                            In-chat: once per browser session when chat finishes loading — not mid-conversation.
                            Return login = login count ≥ 2. Inactive is primarily for email.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Condition</th>
                    <td>
                        <input type="text" name="engagement_rule_condition[]" value="<?php echo esc_attr($cond); ?>"
                               class="large-text flosc-eng-condition-input" placeholder="is_guest && login_count >= 2">
                        <p class="description">
                            Freeform condition (same language as Offers / IVR). Focus this field, then use
                            <strong>Available conditions</strong> above to insert chips.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Then · floscResponse</th>
                    <td>
                        <label style="display:block;margin-bottom:6px;">
                            <input type="checkbox" class="flosc-eng-rule-action-chat" name="engagement_rule_action_chat[<?php echo esc_attr((string) $index); ?>]" value="1" <?php checked($chat_on); ?>>
                            In-chat message
                        </label>
                        <textarea name="engagement_rule_chat_message[]" rows="2" class="large-text flosc-eng-rule-chat-message"
                                  placeholder="Welcome back!"><?php echo esc_textarea($msg); ?></textarea>
                        <p class="description" style="margin:4px 0 10px;">
                            Parameter you edit. AI is told this was an <strong>admin engagement</strong> insert.
                        </p>
                        <label style="display:block;margin:10px 0 6px;">
                            <input type="checkbox" class="flosc-eng-rule-action-email" name="engagement_rule_action_email[<?php echo esc_attr((string) $index); ?>]" value="1" <?php checked($email_on); ?>>
                            Send email when this fires
                        </label>
                        <select name="engagement_rule_email_template[]" class="regular-text">
                            <?php foreach ($flosc_email_templates as $ek => $elab) : ?>
                                <option value="<?php echo esc_attr($ek); ?>" <?php selected($tpl, $ek); ?>><?php echo esc_html($elab); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Letter copy on
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $GLOBALS['flosc_current_ivr'] ?? '', 'tab' => 'email'], admin_url('admin.php'))); ?>">Email</a>.
                            Offers:
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $GLOBALS['flosc_current_ivr'] ?? '', 'tab' => 'offers'], admin_url('admin.php'))); ?>">Offers</a>.
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </details>
    <?php
};

// Split rules by audience (form index assigned when rendering so arrays stay aligned)
$flosc_visitor_rules = [];
$flosc_guest_rules   = [];
$flosc_member_rules  = [];
foreach ($flosc_rules as $flosc_rule) {
    if (!is_array($flosc_rule)) {
        continue;
    }
    $flosc_aud = sanitize_key((string) ($flosc_rule['audience'] ?? 'guest'));
    if ($flosc_aud === 'visitor') {
        $flosc_visitor_rules[] = $flosc_rule;
    } elseif ($flosc_aud === 'member') {
        $flosc_member_rules[] = $flosc_rule;
    } else {
        $flosc_guest_rules[] = $flosc_rule;
    }
}
if (empty($flosc_visitor_rules)) {
    $flosc_visitor_rules[] = $flosc_default_rules[0];
}
if (empty($flosc_guest_rules)) {
    $flosc_guest_rules[] = $flosc_default_rules[1];
}
if (empty($flosc_member_rules)) {
    $flosc_member_rules[] = $flosc_default_rules[3];
}
$flosc_form_index = 0;

$flosc_count_active = static function ($rules) {
    $n = 0;
    foreach ($rules as $r) {
        if (is_array($r) && !empty($r['enabled'])) {
            $n++;
        }
    }
    return $n;
};

// Profile activity (this flow only).
// Match registration_flow / last_flow against stem variants — writers sometimes
// sanitize_key() a full "file.md" (becomes filmd) while the admin stem is pathinfo().
$flosc_summary_users = [];
if ($flosc_flow_id !== '' || $flosc_current_ivr !== '') {
    $flosc_ivr_base = basename((string) $flosc_current_ivr);
    $flosc_stem     = sanitize_key(pathinfo($flosc_ivr_base, PATHINFO_FILENAME));
    if ($flosc_stem === '' && $flosc_flow_id !== '') {
        $flosc_stem = sanitize_key((string) $flosc_flow_id);
    }
    $flosc_flow_match_vals = array_values(array_unique(array_filter([
        $flosc_stem,
        $flosc_flow_id,
        $flosc_ivr_base,
        pathinfo($flosc_ivr_base, PATHINFO_FILENAME),
        sanitize_key($flosc_ivr_base), // e.g. dainis_net_ivrmd when source was …ivr.md
        sanitize_key((string) $flosc_current_ivr),
    ], static function ($v) {
        return is_string($v) && $v !== '';
    })));

    $flosc_by_id = [];
    if (!empty($flosc_flow_match_vals) && function_exists( 'flosc_get_user_ids_for_meta_in' )) {
        $flosc_merged_ids = array();
        foreach ( array( '_flosc_registration_flow', '_flosc_last_flow', '_flosc_first_flow' ) as $flosc_mk ) {
            $flosc_merged_ids = array_merge(
                $flosc_merged_ids,
                flosc_get_user_ids_for_meta_in( $flosc_mk, $flosc_flow_match_vals, 80 )
            );
        }
        $flosc_merged_ids = array_values( array_unique( array_map( 'intval', $flosc_merged_ids ) ) );
        if ( ! empty( $flosc_merged_ids ) ) {
            $flosc_found = get_users(
                array(
                    'include' => $flosc_merged_ids,
                    'orderby' => 'registered',
                    'order'   => 'DESC',
                    'fields'  => array( 'ID', 'user_email', 'display_name', 'user_registered' ),
                )
            );
            foreach ( $flosc_found as $flosc_u_row ) {
                $flosc_by_id[ (int) $flosc_u_row->ID ] = $flosc_u_row;
            }
        }
    }
    // Also surface users who used this flow (counts array) even if registration_flow missing.
    if ($flosc_stem !== '') {
        $flosc_count_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_flow_use_counts', null, '=', 100 )
            : array();
        $flosc_count_users = ! empty( $flosc_count_ids )
            ? get_users(
                array(
                    'include' => $flosc_count_ids,
                    'orderby' => 'registered',
                    'order'   => 'DESC',
                    'fields'  => array( 'ID', 'user_email', 'display_name', 'user_registered' ),
                )
            )
            : array();
        foreach ($flosc_count_users as $flosc_cu) {
            $flosc_counts = get_user_meta((int) $flosc_cu->ID, '_flosc_flow_use_counts', true);
            if (!is_array($flosc_counts)) {
                continue;
            }
            foreach ($flosc_flow_match_vals as $flosc_mv) {
                if (isset($flosc_counts[$flosc_mv]) || isset($flosc_counts[$flosc_stem])) {
                    $flosc_by_id[(int) $flosc_cu->ID] = $flosc_cu;
                    break;
                }
            }
        }
    }
    $flosc_summary_users = array_values($flosc_by_id);
    usort($flosc_summary_users, static function ($a, $b) {
        return strcmp((string) ($b->user_registered ?? ''), (string) ($a->user_registered ?? ''));
    });
    $flosc_summary_users = array_slice($flosc_summary_users, 0, 50);
}
$flosc_framework = class_exists('FLOSC_Framework') ? FLOSC_Framework::instance() : null;
?>

<div class="flosc-flow-overview">
    <div class="flosc-flow-overview-header">
        <h2 class="flosc-flow-overview-title">
            <span>Engagement — when profiles behave, FLOSC responds</span>
            <a href="<?php echo esc_url($flosc_tab_url('documentation')); ?>" class="flosc-docs-link">Docs</a>
        </h2>
        <p class="flosc-flow-overview-summary">
            <strong>This tab:</strong> parameters for <em>when</em> floscResponses fire (including <em>when</em> to send email)
            and which conditions can trigger them.
            <strong>In-chat timing:</strong> once per browser session when chat finishes loading — not randomly mid-chat.
            Related surfaces (not reconfigured here): Flow, Quiz, Concierge, Trajectories, Offers, Email.
        </p>
        <p class="flosc-eng-related-links" style="margin:10px 0 0;display:flex;flex-wrap:wrap;gap:8px;">
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('flow')); ?>">Flow →</a>
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('quiz')); ?>">Quiz →</a>
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('concierge')); ?>">Concierge →</a>
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('trajectories')); ?>">Trajectories →</a>
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('offers')); ?>">Offers →</a>
            <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('email')); ?>">Email →</a>
        </p>
    </div>

    <!-- Conditions catalog (collapsed by default — same pattern as audience accordions) -->
    <details class="flosc-condition-reference flosc-eng-accordion" id="flosc-eng-conditions-catalog">
        <summary>Available conditions that trigger floscResponses</summary>
        <div class="flosc-condition-ref-body">
            <p class="description">
                Click a chip to <strong>copy</strong> it. Focus a rule’s Condition field first, then click a chip to
                <strong>insert</strong> into that field. Combine with <code>&&</code> <code>||</code> <code>!</code> <code>()</code>.
                Same evaluator language as Offers and IVR.
            </p>
            <div class="flosc-condition-ref-grid">
                <div class="flosc-condition-ref-section">
                    <h4>Audience &amp; access</h4>
                    <button type="button" class="flosc-condition-copy" data-condition="is_visitor"><code>is_visitor</code></button>
                    <span class="flosc-cond-desc">Not logged in</span>
                    <button type="button" class="flosc-condition-copy" data-condition="is_guest"><code>is_guest</code></button>
                    <span class="flosc-cond-desc">Logged in, not purchased</span>
                    <button type="button" class="flosc-condition-copy" data-condition="is_member"><code>is_member</code></button>
                    <span class="flosc-cond-desc">Member / full access on this flow</span>
                    <button type="button" class="flosc-condition-copy" data-condition="logged_in"><code>logged_in</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="!logged_in"><code>!logged_in</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="purchased"><code>purchased</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="!purchased"><code>!purchased</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="has_sso"><code>has_sso</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="has_profile"><code>has_profile</code></button>
                </div>
                <div class="flosc-condition-ref-section">
                    <h4>Quiz &amp; score</h4>
                    <button type="button" class="flosc-condition-copy" data-condition="quiz_taken"><code>quiz_taken</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="!quiz_taken"><code>!quiz_taken</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="score >= 70"><code>score >= 70</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="score < 50"><code>score < 50</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="initial_score >= 80"><code>initial_score >= 80</code></button>
                </div>
                <div class="flosc-condition-ref-section">
                    <h4>Login &amp; time</h4>
                    <button type="button" class="flosc-condition-copy" data-condition="login_count >= 2"><code>login_count >= 2</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="login_count >= 3"><code>login_count >= 3</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="days_since_registration >= 10"><code>days_since_registration >= 10</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="returning_user"><code>returning_user</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="message_count > 3"><code>message_count > 3</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="session_minutes >= 5"><code>session_minutes >= 5</code></button>
                </div>
                <div class="flosc-condition-ref-section">
                    <h4>Lessons &amp; content</h4>
                    <button type="button" class="flosc-condition-copy" data-condition="lesson_viewed"><code>lesson_viewed</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="lessons_completed >= 1"><code>lessons_completed >= 1</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="onboarded"><code>onboarded</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="has_incomplete_lesson"><code>has_incomplete_lesson</code></button>
                </div>
                <div class="flosc-condition-ref-section">
                    <h4>Offer tracking <span class="description">(configure offers on Offers tab)</span></h4>
                    <button type="button" class="flosc-condition-copy" data-condition="offer_shown_full_access"><code>offer_shown_{id}</code></button>
                    <span class="flosc-cond-desc">Replace with real offer id — used when Offers auto-show</span>
                    <button type="button" class="flosc-condition-copy" data-condition="!offer_purchased_full_access"><code>!offer_purchased_{id}</code></button>
                    <p class="description" style="margin-top:8px;">
                        <a class="button button-small" href="<?php echo esc_url($flosc_tab_url('offers')); ?>">Open Offers to configure →</a>
                    </p>
                </div>
                <div class="flosc-condition-ref-section">
                    <h4>Always / never</h4>
                    <button type="button" class="flosc-condition-copy" data-condition="always"><code>always</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="never"><code>never</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="is_guest && login_count >= 2"><code>is_guest && login_count >= 2</code></button>
                    <span class="flosc-cond-desc">Example: returning guest</span>
                    <button type="button" class="flosc-condition-copy" data-condition="is_member"><code>is_member</code></button>
                    <button type="button" class="flosc-condition-copy" data-condition="quiz_taken && !purchased"><code>quiz_taken && !purchased</code></button>
                    <span class="flosc-cond-desc">Classic post-quiz, not yet member</span>
                </div>
            </div>
        </div>
    </details>

    <div class="flosc-eng-audience-accordions" style="margin:0 0 1.5rem;">

        <?php
        // ── Visitor ──────────────────────────────────────────────────────────
        $flosc_v_active = $flosc_count_active($flosc_visitor_rules);
        ?>
        <details class="flosc-eng-accordion flosc-eng-accordion--visitor">
            <summary>
                <span class="flosc-eng-accordion__title">Visitor</span>
                <span class="flosc-eng-accordion__sub">Not logged in · <?php echo esc_html((string) $flosc_v_active); ?> active rule(s)</span>
            </summary>
            <div class="flosc-eng-accordion__body">
                <p class="description">Anonymous chat users. In-chat when chat opens (once per browser session). Email is rarely useful here (no address yet).</p>
                <div class="flosc-eng-rule-list" data-audience="visitor">
                    <?php
                    foreach ($flosc_visitor_rules as $flosc_rule_row) {
                        $flosc_render_rule($flosc_rule_row, 'visitor', $flosc_form_index, false);
                        $flosc_form_index++;
                    }
                    ?>
                </div>
                <p style="margin:12px 0 0;">
                    <button type="button" class="button flosc-eng-add-rule" data-audience="visitor">+ Add visitor rule</button>
                </p>
            </div>
        </details>

        <?php
        // ── Guest ────────────────────────────────────────────────────────────
        $flosc_g_active = $flosc_count_active($flosc_guest_rules);
        ?>
        <details class="flosc-eng-accordion flosc-eng-accordion--guest" open>
            <summary>
                <span class="flosc-eng-accordion__title">Guest</span>
                <span class="flosc-eng-accordion__sub">Logged in, not purchased · <?php echo esc_html((string) $flosc_g_active); ?> active rule(s)</span>
            </summary>
            <div class="flosc-eng-accordion__body">
                <p class="description">Free / non-member profiles. Example: welcome-back chat, inactive email.</p>
                <table class="form-table" style="margin:0 0 12px;">
                    <tr>
                        <th scope="row"><label for="flow_guest_access_days">Guest access duration</label></th>
                        <td>
                            <input type="number" id="flow_guest_access_days" name="flow_guest_access_days"
                                   value="<?php echo esc_attr((string) $flosc_guest_access_days); ?>"
                                   min="0" max="365" class="small-text"> days
                            <p class="description" style="margin:4px 0 0;">
                                Journey length / remaining-day math. <code>0</code> = no countdown.
                                Same key may appear under Content.
                                <a href="<?php echo esc_url($flosc_tab_url('email')); ?>">Write email letters →</a>
                            </p>
                        </td>
                    </tr>
                </table>
                <div class="flosc-eng-rule-list" data-audience="guest">
                    <?php
                    foreach ($flosc_guest_rules as $flosc_rule_row) {
                        $flosc_render_rule($flosc_rule_row, 'guest', $flosc_form_index, false);
                        $flosc_form_index++;
                    }
                    ?>
                </div>
                <p style="margin:12px 0 0;">
                    <button type="button" class="button flosc-eng-add-rule" data-audience="guest">+ Add guest rule</button>
                </p>
            </div>
        </details>

        <?php
        // ── Member ───────────────────────────────────────────────────────────
        $flosc_m_active = $flosc_count_active($flosc_member_rules);
        ?>
        <details class="flosc-eng-accordion flosc-eng-accordion--member">
            <summary>
                <span class="flosc-eng-accordion__title">Member</span>
                <span class="flosc-eng-accordion__sub">Purchased / full access · <?php echo esc_html((string) $flosc_m_active); ?> active rule(s)</span>
            </summary>
            <div class="flosc-eng-accordion__body">
                <p class="description">Members: full access on this flow. Example: inactive → email; return login → chat.</p>
                <div class="flosc-eng-rule-list" data-audience="member">
                    <?php
                    foreach ($flosc_member_rules as $flosc_rule_row) {
                        $flosc_render_rule($flosc_rule_row, 'member', $flosc_form_index, false);
                        $flosc_form_index++;
                    }
                    ?>
                </div>
                <p style="margin:12px 0 0;">
                    <button type="button" class="button flosc-eng-add-rule" data-audience="member">+ Add member rule</button>
                </p>
            </div>
        </details>
    </div>

    <?php
    // Blank rule template for JS clone (not submitted until added to a list).
    $flosc_blank_rule = [
        'id'             => '',
        'title'          => 'New rule',
        'enabled'        => '1',
        'trigger'        => 'chat_open',
        'trigger_days'   => '0',
        'condition'      => '',
        'action_chat'    => '1',
        'action_email'   => '',
        'email_template' => '',
        'chat_message'   => '',
    ];
    ?>
    <template id="flosc-eng-rule-template">
        <?php $flosc_render_rule($flosc_blank_rule, 'guest', 9999, true); ?>
    </template>

    <hr>

    <h2>Profile activity</h2>
    <p class="description">
        Who is on this floscFlow — people who registered here, last used it, or have activity on it.
        Monitor progress toward gated content.
    </p>
    <div class="flosc-engagement-summary-scroll" style="max-height: 22rem; overflow: auto; border: 1px solid #c3c4c7; background: #fff;">
        <table class="widefat striped flosc-login-activity-table" style="margin:0;">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Registered</th>
                    <th>State</th>
                    <th>Purchased</th>
                    <th>Logins</th>
                    <th>Profile</th>
                    <th>Chat</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($flosc_summary_users)) : ?>
                <tr><td colspan="7">No users registered to this flow yet.</td></tr>
            <?php else : ?>
                <?php foreach ($flosc_summary_users as $flosc_u) :
                    $flosc_uid = (int) $flosc_u->ID;
                    $flosc_purchased = (bool) get_user_meta($flosc_uid, '_flosc_purchased', true);
                    $flosc_logins = (int) get_user_meta($flosc_uid, '_flosc_login_count', true);
                    $flosc_creds = (bool) get_user_meta($flosc_uid, '_flosc_magic_link_user_credentials_set', true)
                        || !empty(get_user_meta($flosc_uid, '_flosc_sso_linked_providers', true));
                    $flosc_state = '—';
                    if ($flosc_framework && method_exists($flosc_framework, 'sale')) {
                        $flosc_sale = $flosc_framework->sale();
                        if ($flosc_sale && method_exists($flosc_sale, 'access')) {
                            $flosc_state = (string) $flosc_sale->access()->get_simple_state($flosc_uid, $flosc_flow_id);
                        }
                    }
                    if ($flosc_state === '—' && $flosc_purchased) {
                        $flosc_state = 'member';
                    }
                    $flosc_edit = get_edit_user_link($flosc_uid);
                    $flosc_label = $flosc_u->display_name !== '' ? $flosc_u->display_name : $flosc_u->user_email;
                    $flosc_chat_url = add_query_arg([
                        'page' => 'flosc-settings',
                        'ivr' => $flosc_current_ivr,
                        'tab' => 'chat-logs',
                        'flosc_user_id' => $flosc_uid,
                    ], admin_url('admin.php'));
                    ?>
                    <tr>
                        <td>
                            <?php if ($flosc_edit) : ?>
                                <a href="<?php echo esc_url($flosc_edit); ?>"><?php echo esc_html($flosc_label); ?></a>
                                <br><span class="description"><?php echo esc_html($flosc_u->user_email); ?></span>
                            <?php else : ?>
                                <?php echo esc_html($flosc_label); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(mysql2date('Y-m-d', $flosc_u->user_registered)); ?></td>
                        <td><?php echo esc_html($flosc_state !== '' ? $flosc_state : '—'); ?></td>
                        <td><?php echo $flosc_purchased ? 'yes' : 'no'; ?></td>
                        <td><?php echo $flosc_logins > 0 ? esc_html((string) $flosc_logins) : '—'; ?></td>
                        <td><?php echo $flosc_creds ? 'complete' : 'pending'; ?></td>
                        <td><a href="<?php echo esc_url($flosc_chat_url); ?>">logs</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="description" style="margin-top:8px;">
        <?php echo esc_html((string) count($flosc_summary_users)); ?> row(s).
        <a href="<?php echo esc_url($flosc_tab_url('chat-logs')); ?>">Chat Logs</a>
    </p>
</div>

<?php
// Condition chips + add/remove rules + reindex form fields.
ob_start();
?>
(function () {
    var lastCond = null;

    function slugify(s) {
        return String(s || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '')
            .substring(0, 48) || 'rule';
    }

    function reindexRules() {
        var rules = document.querySelectorAll('.flosc-eng-rule-list .flosc-eng-rule');
        rules.forEach(function (rule, i) {
            rule.setAttribute('data-rule-index', String(i));
            var en = rule.querySelector('.flosc-eng-rule-enabled');
            if (en) en.name = 'engagement_rule_enabled[' + i + ']';
            var ach = rule.querySelector('.flosc-eng-rule-action-chat');
            if (ach) ach.name = 'engagement_rule_action_chat[' + i + ']';
            var aem = rule.querySelector('.flosc-eng-rule-action-email');
            if (aem) aem.name = 'engagement_rule_action_email[' + i + ']';
            var idInput = rule.querySelector('.flosc-eng-rule-id');
            var titleInput = rule.querySelector('.flosc-eng-rule-title');
            if (idInput && (!idInput.value || idInput.value.indexOf('rule_') === 0 || idInput.value === '')) {
                var t = titleInput ? titleInput.value : '';
                idInput.value = slugify(t) + '_' + i;
            }
            var idLab = rule.querySelector('.flosc-eng-rule-id-label');
            if (idLab && idInput) idLab.textContent = idInput.value;
            var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
            if (sumTitle && titleInput) sumTitle.textContent = titleInput.value || idInput.value || ('Rule ' + (i + 1));
        });
    }

    function refreshAudienceCounts() {
        document.querySelectorAll('.flosc-eng-accordion').forEach(function (acc) {
            var list = acc.querySelector('.flosc-eng-rule-list');
            if (!list) return;
            var n = 0;
            list.querySelectorAll('.flosc-eng-rule').forEach(function (rule) {
                var en = rule.querySelector('.flosc-eng-rule-enabled');
                if (en && en.checked) n++;
            });
            var sub = acc.querySelector('.flosc-eng-accordion__sub');
            if (!sub) return;
            var base = sub.textContent.replace(/\d+\s*active rule\(s\)/, '').replace(/·\s*$/, '').trim();
            sub.textContent = base + ' · ' + n + ' active rule(s)';
        });
    }

    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-condition-input')) {
            lastCond = e.target;
        }
    });

    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-title')) {
            var rule = e.target.closest('.flosc-eng-rule');
            if (!rule) return;
            var sum = rule.querySelector('.flosc-eng-rule__summary-title');
            if (sum) sum.textContent = e.target.value || 'New rule';
            var idInput = rule.querySelector('.flosc-eng-rule-id');
            if (idInput) {
                var idx = rule.getAttribute('data-rule-index') || '0';
                idInput.value = slugify(e.target.value) + '_' + idx;
                var idLab = rule.querySelector('.flosc-eng-rule-id-label');
                if (idLab) idLab.textContent = idInput.value;
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList && e.target.classList.contains('flosc-eng-rule-enabled')) {
            refreshAudienceCounts();
            var rule = e.target.closest('.flosc-eng-rule');
            if (rule) {
                var prev = rule.querySelector('.flosc-eng-rule__summary-preview');
                if (prev) {
                    var t = prev.textContent.replace(/\b(active|off)\b/, e.target.checked ? 'active' : 'off');
                    prev.textContent = t;
                }
            }
        }
    });

    document.addEventListener('click', function (e) {
        var chip = e.target.closest('.flosc-condition-copy');
        if (chip) {
            e.preventDefault();
            var condition = chip.getAttribute('data-condition') || '';
            if (!condition) return;
            var input = lastCond;
            if (!input || !document.body.contains(input)) {
                input = document.querySelector('.flosc-eng-condition-input');
            }
            if (input) {
                var cur = (input.value || '').trim();
                input.value = cur ? (cur + ' && ' + condition) : condition;
                input.focus();
                lastCond = input;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(condition);
            }
            return;
        }

        var addBtn = e.target.closest('.flosc-eng-add-rule');
        if (addBtn) {
            e.preventDefault();
            var audience = addBtn.getAttribute('data-audience') || 'guest';
            var list = document.querySelector('.flosc-eng-rule-list[data-audience="' + audience + '"]');
            var tpl = document.getElementById('flosc-eng-rule-template');
            if (!list || !tpl) return;
            var node = tpl.content.cloneNode(true);
            var rule = node.querySelector('.flosc-eng-rule');
            if (!rule) return;
            rule.setAttribute('data-audience', audience);
            var audInput = rule.querySelector('.flosc-eng-rule-audience');
            if (audInput) audInput.value = audience;
            var titleInput = rule.querySelector('.flosc-eng-rule-title');
            if (titleInput) {
                var label = audience.charAt(0).toUpperCase() + audience.slice(1) + ' · new rule';
                titleInput.value = label;
            }
            var sumTitle = rule.querySelector('.flosc-eng-rule__summary-title');
            if (sumTitle && titleInput) sumTitle.textContent = titleInput.value;
            var idInput = rule.querySelector('.flosc-eng-rule-id');
            if (idInput) idInput.value = '';
            var cond = rule.querySelector('.flosc-eng-condition-input');
            if (cond) {
                if (audience === 'visitor') cond.value = 'is_visitor';
                else if (audience === 'guest') cond.value = 'is_guest';
                else if (audience === 'member') cond.value = 'is_member';
            }
            rule.setAttribute('open', '');
            list.appendChild(rule);
            reindexRules();
            refreshAudienceCounts();
            return;
        }

        var rem = e.target.closest('.flosc-eng-rule-remove');
        if (rem) {
            e.preventDefault();
            var ruleEl = rem.closest('.flosc-eng-rule');
            if (!ruleEl) return;
            var listEl = ruleEl.closest('.flosc-eng-rule-list');
            if (listEl && listEl.querySelectorAll('.flosc-eng-rule').length <= 1) {
                // Keep at least one shell: clear fields instead of removing last.
                var t = ruleEl.querySelector('.flosc-eng-rule-title');
                if (t) t.value = 'New rule';
                var c = ruleEl.querySelector('.flosc-eng-condition-input');
                if (c) c.value = '';
                var m = ruleEl.querySelector('.flosc-eng-rule-chat-message');
                if (m) m.value = '';
                var en = ruleEl.querySelector('.flosc-eng-rule-enabled');
                if (en) en.checked = false;
                var sum = ruleEl.querySelector('.flosc-eng-rule__summary-title');
                if (sum) sum.textContent = 'New rule';
                reindexRules();
                refreshAudienceCounts();
                return;
            }
            ruleEl.remove();
            reindexRules();
            refreshAudienceCounts();
        }
    });

    reindexRules();
})();
<?php
wp_add_inline_script('flosc-admin', ob_get_clean());
