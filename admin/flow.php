<?php
/**
 * FLOSC Flow Tab — F→L→O→S→C read-only phase overview
 *
 * Shows live counts and edit links for each of the five FLOSC flow phases.
 * Data sourced from $flosc_flow_settings (via $GLOBALS) and flosc() helper objects.
 *
 * v4.0.0: Initial implementation
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$flosc_flow_settings  = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_selected_ivr   = $GLOBALS['flosc_current_ivr']      ?? '';
$flosc_ivr_param      = urlencode( $flosc_selected_ivr );
$flosc_base_url       = admin_url( 'admin.php?page=flosc-settings&ivr=' . $flosc_ivr_param . '&tab=' );
$flosc_flow_docs_url  = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_selected_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-flow';

// ── Gather live data ──────────────────────────────────────────────────────────

// ── Parse IVR file once — used for pill counts across all phases ──────────────
$flosc_ivr_pill_counts = [ 'freeline' => 0, 'offer' => 0, 'content' => 0 ];
$flosc_ivr_path = flosc_config_file($flosc_selected_ivr);
if ( $flosc_selected_ivr && file_exists( $flosc_ivr_path ) && class_exists( 'FLOSC_IVR_Parser' ) ) {
    $flosc_ivr_parser = FLOSC_IVR_Parser::flosc_instance();
    $flosc_ivr_data   = $flosc_ivr_parser->flosc_parse( flosc_fs_get_contents( $flosc_ivr_path ) );
    foreach ( array_keys( $flosc_ivr_pill_counts ) as $flosc_phase ) {
        foreach ( $flosc_ivr_data['phases'][ $flosc_phase ] ?? [] as $flosc_name ) {
            if ( ( $flosc_ivr_data['messages'][ $flosc_name ]['type'] ?? '' ) === 'suggested_user_autoprompt' ) {
                $flosc_ivr_pill_counts[ $flosc_phase ]++;
            }
        }
    }
}

// F — Freeline: quiz + visitor pills + IVR file
// Empty enabled_quizzes = no quiz for this flow. Never invent a sample quiz.
$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? [];
if ( ! is_array( $flosc_enabled_quizzes ) ) {
    $flosc_enabled_quizzes = [];
}
$flosc_enabled_quizzes  = array_values( array_filter( array_map( 'sanitize_key', $flosc_enabled_quizzes ) ) );
$flosc_quiz_configured  = ! empty( $flosc_enabled_quizzes );
$flosc_quiz_count       = count( $flosc_enabled_quizzes );
$flosc_quiz_word        = $flosc_quiz_count === 1 ? 'Quiz' : 'Quizzes';
$flosc_edit_quiz_label  = $flosc_quiz_count === 1 ? 'Edit Quiz →' : 'Edit Quizzes →';

if ( $flosc_quiz_configured && class_exists( 'FLOSC_Quiz_Registry' ) ) {
    $flosc_names = [];
    foreach ( $flosc_enabled_quizzes as $flosc_qid ) {
        $flosc_qt      = FLOSC_Quiz_Registry::get_quiz( $flosc_qid );
        $flosc_names[] = $flosc_qt ? $flosc_qt->get_name() : ucwords( str_replace( '_', ' ', $flosc_qid ) );
    }
    $flosc_bullets         = array_map( fn( $n ) => '<span class="flosc-flow-bullet">• ' . esc_html( $n ) . '</span>', $flosc_names );
    $flosc_quiz_label_html = '<strong>' . $flosc_quiz_count . ' ' . $flosc_quiz_word . ' ✅</strong>' . implode( '', $flosc_bullets );
} else {
    $flosc_quiz_label_html = '<strong>0 Quizzes ❌</strong>';
    $flosc_edit_quiz_label = 'Edit Quiz →';
}

$flosc_visitor_pills = count( $flosc_flow_settings['autoprompts']['visitor'] ?? [] );
$flosc_ivr_file_label = $flosc_selected_ivr ? esc_html( $flosc_selected_ivr ) : 'None configured';

// L — Login: SSO providers
$flosc_sso_providers   = [];
foreach ( ['google', 'apple', 'facebook', 'microsoft', 'linkedin'] as $flosc_p ) {
    if ( ! empty( $flosc_flow_settings[ 'sso_' . $flosc_p . '_enabled' ] ) ) {
        $flosc_sso_providers[] = ucfirst( $flosc_p );
    }
}
$flosc_sso_label = $flosc_sso_providers ? implode( ', ', $flosc_sso_providers ) : 'WordPress native';

// O — Offer: offers count + guest pills
$flosc_flow_id_key    = $flosc_selected_ivr ? pathinfo( $flosc_selected_ivr, PATHINFO_FILENAME ) : null;
$flosc_all_offers     = [];
$flosc_active_count   = 0;
$flosc_draft_count    = 0;
if ( function_exists( 'flosc' ) && $flosc_flow_id_key ) {
    $flosc_all_offers = flosc()->sale()->offers()->get_all_offers( $flosc_flow_id_key );
    foreach ( $flosc_all_offers as $flosc_o ) {
        if ( ( $flosc_o['status'] ?? 'draft' ) === 'active' ) {
            $flosc_active_count++;
        } else {
            $flosc_draft_count++;
        }
    }
}
$flosc_offers_label  = $flosc_active_count . ' active' . ( $flosc_draft_count ? ', ' . $flosc_draft_count . ' draft' : '' );
$flosc_guest_pills   = count( $flosc_flow_settings['autoprompts']['guest'] ?? [] ) + $flosc_ivr_pill_counts['offer'];

// S — Sale: payment providers (read directly from flow_settings — same source as Payments tab)
$flosc_paypal_cfg  = ! empty( $flosc_flow_settings['paypal_enabled'] )
               && ! empty( $flosc_flow_settings['paypal_client_id'] )
               && ! empty( $flosc_flow_settings['paypal_secret'] );
$flosc_paypal_mode = $flosc_flow_settings['paypal_mode'] ?? 'sandbox';

$flosc_stripe_mode = $flosc_flow_settings['stripe_mode'] ?? 'test';
$flosc_stripe_sk   = $flosc_stripe_mode === 'live'
               ? ( $flosc_flow_settings['stripe_live_sk'] ?? '' )
               : ( $flosc_flow_settings['stripe_test_sk'] ?? '' );
$flosc_stripe_cfg  = ! empty( $flosc_flow_settings['stripe_enabled'] ) && ! empty( $flosc_stripe_sk );

// C — Content: lessons + member pills + AI provider
$flosc_lesson_groups  = $flosc_flow_settings['lesson_groups'] ?? [];
if ( empty( $flosc_lesson_groups ) && ! empty( $flosc_flow_settings['lessons_category'] ) ) {
    $flosc_lesson_groups = [ [ 'category' => $flosc_flow_settings['lessons_category'] ] ];
}
$flosc_lesson_count  = count( $flosc_lesson_groups );
$flosc_lessons_label = $flosc_lesson_count ? $flosc_lesson_count . ' lesson group' . ( $flosc_lesson_count !== 1 ? 's' : '' ) : 'Not configured';

$flosc_member_pills  = count( $flosc_flow_settings['autoprompts']['member'] ?? [] ) + $flosc_ivr_pill_counts['content'];

$flosc_companion_mode = sanitize_text_field((string) ($flosc_flow_settings['companion_content_display_mode'] ?? 'in_chat'));
$flosc_companion_enabled = !empty($flosc_flow_settings['companion_enabled']);
$flosc_companion_mode_labels = [
    'in_chat' => 'In-Chat Only',
    'companion' => 'Companion',
    'both' => 'Hybrid',
];
$flosc_companion_sitewide_profile_active = (
    $flosc_companion_enabled
    && ($flosc_companion_mode === 'companion' || $flosc_companion_mode === 'both')
    && !empty($flosc_flow_settings['companion_show_for_visitors'])
    && !empty($flosc_flow_settings['companion_allow_fullscreen'])
    && !empty($flosc_flow_settings['companion_default_fullscreen'])
);
$flosc_companion_mode_label = $flosc_companion_mode_labels[$flosc_companion_mode] ?? 'In-Chat Only';
if ($flosc_companion_sitewide_profile_active) {
    $flosc_companion_mode_label = 'Sitewide ON Profile (' . $flosc_companion_mode_label . ')';
}
$flosc_companion_status = ($flosc_companion_mode === 'companion' || $flosc_companion_mode === 'both')
    ? ($flosc_companion_enabled ? 'Widget enabled' : 'Widget disabled')
    : 'Widget not used in this mode';
$flosc_companion_auto = !empty($flosc_flow_settings['companion_auto_open_enabled']) ? 'Auto open' : 'Manual open';
$flosc_companion_exit = !empty($flosc_flow_settings['companion_launch_on_exit_intent']) ? 'Exit trigger on' : 'Exit trigger off';
$flosc_companion_scroll = !empty($flosc_flow_settings['companion_launch_on_scroll_threshold'])
    ? ('Scroll trigger ' . max(0, min(100, intval($flosc_flow_settings['companion_launch_on_scroll_percent'] ?? 0))) . '%')
    : 'Scroll trigger off';
$flosc_companion_cooldown_ms = max(0, intval($flosc_flow_settings['companion_trigger_cooldown_ms'] ?? 0));
$flosc_companion_cooldown_label = $flosc_companion_cooldown_ms > 0
    ? ('Cooldown ' . round($flosc_companion_cooldown_ms / 1000) . 's')
    : 'No cooldown';
$flosc_companion_remember_state = !empty($flosc_flow_settings['companion_remember_open_state']);
$flosc_companion_state_storage = sanitize_text_field((string) ($flosc_flow_settings['companion_state_storage'] ?? 'session'));
$flosc_companion_storage_label = ucfirst($flosc_companion_state_storage);
$flosc_companion_effective_parts = [];

if (!$flosc_companion_enabled || ($flosc_companion_mode !== 'companion' && $flosc_companion_mode !== 'both')) {
    $flosc_companion_effective_parts[] = 'Companion widget inactive in current mode';
} else {
    $flosc_companion_effective_parts[] = 'Companion widget active';

    if (!empty($flosc_flow_settings['companion_auto_open_enabled'])) {
        $flosc_companion_effective_parts[] = 'Auto open enabled';
    }

    if (!empty($flosc_flow_settings['companion_launch_on_exit_intent']) || !empty($flosc_flow_settings['companion_launch_on_scroll_threshold'])) {
        $flosc_companion_effective_parts[] = 'Behavior triggers enabled';
    } else {
        $flosc_companion_effective_parts[] = 'Behavior triggers disabled';
    }

    if ($flosc_companion_remember_state) {
        $flosc_companion_effective_parts[] = 'Open state remembered (' . $flosc_companion_storage_label . ')';
    } else {
        $flosc_companion_effective_parts[] = 'Open state not remembered';
    }

    if ($flosc_companion_cooldown_ms > 0 && (!empty($flosc_flow_settings['companion_launch_on_exit_intent']) || !empty($flosc_flow_settings['companion_launch_on_scroll_threshold']))) {
        $flosc_companion_effective_parts[] = 'Trigger cooldown active';
    } elseif ($flosc_companion_cooldown_ms > 0) {
        $flosc_companion_effective_parts[] = 'Trigger cooldown set but triggers are off';
    }
}

$flosc_companion_effective_label = implode(' · ', $flosc_companion_effective_parts);
$flosc_companion_numeric_limits = [
    'panel_width_min' => 280,
    'panel_width_max' => 900,
    'panel_height_min' => 320,
    'panel_height_max' => 1200,
    'auto_open_delay_min_ms' => 0,
    'auto_open_delay_max_ms' => 60000,
    'trigger_cooldown_min_ms' => 0,
    'trigger_cooldown_max_ms' => 86400000,
];
$flosc_companion_numeric_limits = wp_parse_args(
    apply_filters('flosc_companion_numeric_limits', $flosc_companion_numeric_limits),
    $flosc_companion_numeric_limits
);
foreach ($flosc_companion_numeric_limits as $flosc_limit_key => $flosc_limit_value) {
    $flosc_companion_numeric_limits[$flosc_limit_key] = absint($flosc_limit_value);
}
if ($flosc_companion_numeric_limits['panel_width_max'] < $flosc_companion_numeric_limits['panel_width_min']) {
    $flosc_companion_numeric_limits['panel_width_max'] = $flosc_companion_numeric_limits['panel_width_min'];
}
if ($flosc_companion_numeric_limits['panel_height_max'] < $flosc_companion_numeric_limits['panel_height_min']) {
    $flosc_companion_numeric_limits['panel_height_max'] = $flosc_companion_numeric_limits['panel_height_min'];
}
if ($flosc_companion_numeric_limits['auto_open_delay_max_ms'] < $flosc_companion_numeric_limits['auto_open_delay_min_ms']) {
    $flosc_companion_numeric_limits['auto_open_delay_max_ms'] = $flosc_companion_numeric_limits['auto_open_delay_min_ms'];
}
if ($flosc_companion_numeric_limits['trigger_cooldown_max_ms'] < $flosc_companion_numeric_limits['trigger_cooldown_min_ms']) {
    $flosc_companion_numeric_limits['trigger_cooldown_max_ms'] = $flosc_companion_numeric_limits['trigger_cooldown_min_ms'];
}
$flosc_companion_limits_label = sprintf(
    'Panel %d-%dpx W · %d-%dpx H · Auto delay %d-%dms · Cooldown %d-%dms',
    $flosc_companion_numeric_limits['panel_width_min'],
    $flosc_companion_numeric_limits['panel_width_max'],
    $flosc_companion_numeric_limits['panel_height_min'],
    $flosc_companion_numeric_limits['panel_height_max'],
    $flosc_companion_numeric_limits['auto_open_delay_min_ms'],
    $flosc_companion_numeric_limits['auto_open_delay_max_ms'],
    $flosc_companion_numeric_limits['trigger_cooldown_min_ms'],
    $flosc_companion_numeric_limits['trigger_cooldown_max_ms']
);

$flosc_diagnostics_stem = $flosc_selected_ivr ? sanitize_key(pathinfo($flosc_selected_ivr, PATHINFO_FILENAME)) : 'global';
$flosc_diagnostics_start = 'start_' . $flosc_diagnostics_stem;
$flosc_diagnostics_end = 'end_' . $flosc_diagnostics_stem;
$flosc_prompt_panel_counts = [
    'visitor' => $flosc_visitor_pills,
    'guest' => $flosc_guest_pills,
    'member' => $flosc_member_pills,
];
$flosc_diagnostics_flow_name = trim((string) ($flosc_flow_settings['identity']['name'] ?? ''));
$flosc_diagnostics_flow_label = $flosc_diagnostics_flow_name !== '' ? $flosc_diagnostics_flow_name : ($flosc_selected_ivr ?: 'this flow');

$flosc_ai_provider   = $flosc_flow_settings['ai_provider'] ?? 'ivr';
$flosc_ai_labels     = [
    'ivr'       => 'IVR / Scripted only',
    'anthropic' => 'Anthropic Claude',
    'openai'    => 'OpenAI',
    'xai'       => 'xAI Grok',
];
$flosc_ai_label = $flosc_ai_labels[ $flosc_ai_provider ] ?? ucfirst( $flosc_ai_provider );
if ( $flosc_ai_provider === 'anthropic' ) {
    $flosc_ai_model  = $flosc_flow_settings['ai_model'] ?? flosc_get_setting( 'ai_model', 'claude-sonnet-4-6' );
    $flosc_ai_label .= ' (' . esc_html( $flosc_ai_model ) . ')';
}

// ── Helper: render a phase card ───────────────────────────────────────────────
function flosc_flow_card( $letter, $flosc_phase_name, $subtitle, $rows ) {
    $phase_class = strtolower( $letter );
    echo '<div class="flosc-flow-card flosc-flow-card--' . esc_attr( $phase_class ) . '">';
    echo '<div class="flosc-flow-card__header">';
    echo '<span class="flosc-flow-card__badge flosc-flow-card__badge--' . esc_attr( $phase_class ) . '">' . esc_html( $letter ) . '</span>';
    echo '<div>';
    echo '<div class="flosc-flow-card__phase">' . esc_html( strtoupper( $flosc_phase_name ) ) . '</div>';
    echo '<div class="flosc-flow-card__subtitle">' . esc_html( $subtitle ) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '<table class="flosc-flow-card__table">';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td class="flosc-flow-card__label">' . wp_kses_post( $row['label'] ) . '</td>';
        echo '<td class="flosc-flow-card__action">';
        if ( ! empty( $row['edit_url'] ) ) {
            echo '<a href="' . esc_url( $row['edit_url'] ) . '" class="button button-small flosc-flow-card__action-link">' . esc_html( $row['edit_label'] ?? 'Edit →' ) . '</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
}

// ── Output ────────────────────────────────────────────────────────────────────
?>
<div class="flosc-flow-overview">

    <div class="flosc-flow-overview-header">
        <h2 class="flosc-flow-overview-title">
            <span>🗺 FLOSC Flow Overview</span>
            <a href="<?php echo esc_url($flosc_flow_docs_url); ?>" class="flosc-docs-link">Docs</a>
        </h2>
        <p class="flosc-flow-overview-summary">Read-only snapshot of the five flow phases for <strong><?php echo esc_html( $flosc_selected_ivr ?: 'this flow' ); ?></strong>. Click any Edit button to jump to that tab.</p>
    </div>

    <?php
    // ── F — Freeline ──────────────────────────────────────────────────────────
    flosc_flow_card( 'F', 'Freeline', 'What visitors see before logging in', [
        [
            'label'      => $flosc_quiz_label_html,
            'edit_url'   => $flosc_base_url . 'quiz',
            'edit_label' => $flosc_edit_quiz_label,
        ],
        [
            'label'      => 'Visitor pills: ' . $flosc_visitor_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'IVR file: ' . $flosc_ivr_file_label,
            'edit_url'   => $flosc_base_url . 'ivr-messages',
            'edit_label' => 'Edit IVR →',
        ],
    ] );

    // ── L — Login ─────────────────────────────────────────────────────────────
    flosc_flow_card( 'L', 'Login', 'Account gate', [
        [
            'label'      => 'SSO: ' . esc_html( $flosc_sso_label ),
            'edit_url'   => $flosc_base_url . 'sso',
            'edit_label' => 'Edit SSO →',
        ],
    ] );

    // ── O — Offer ─────────────────────────────────────────────────────────────
    // v8.1.0: Member levels summary
    $flosc_ml_registry = $flosc_flow_settings['member_levels'] ?? [];
    $flosc_ml_count    = count( array_filter( $flosc_ml_registry, fn( $l ) => ! empty( $l['slug'] ?? '' ) ) );
    $flosc_ml_names    = array_map( fn( $l ) => $l['name'] ?: ( $l['slug'] ?? '?' ), array_filter( $flosc_ml_registry, fn( $l ) => ! empty( $l['slug'] ?? '' ) ) );
    $flosc_ml_label    = $flosc_ml_count ? $flosc_ml_count . ' level' . ( $flosc_ml_count !== 1 ? 's' : '' ) . ' (' . implode( ', ', $flosc_ml_names ) . ')' : 'None configured';

    flosc_flow_card( 'O', 'Offer', 'Upgrade prompts for guests', [
        [
            'label'      => 'Member Levels: ' . esc_html( $flosc_ml_label ),
            'edit_url'   => $flosc_base_url . 'member-levels',
            'edit_label' => 'Edit Levels →',
        ],
        [
            'label'      => 'Offers: ' . esc_html( $flosc_offers_label ?: 'None configured' ),
            'edit_url'   => $flosc_base_url . 'offers',
            'edit_label' => 'Edit Offers →',
        ],
        [
            'label'      => 'Guest pills: ' . $flosc_guest_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
    ] );

    // ── S — Sale ──────────────────────────────────────────────────────────────
    $flosc_stripe_status = $flosc_stripe_cfg
        ? '✅ configured' . ( $flosc_stripe_mode ? ' (' . esc_html( $flosc_stripe_mode ) . ')' : '' )
        : '❌ not configured';
    $flosc_paypal_status = $flosc_paypal_cfg
        ? '✅ configured' . ( $flosc_paypal_mode ? ' (' . esc_html( $flosc_paypal_mode ) . ')' : '' )
        : '❌ not configured';

    flosc_flow_card( 'S', 'Sale', 'Payment processing', [
        [
            'label'      => 'Stripe: ' . $flosc_stripe_status,
            'edit_url'   => $flosc_base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
        [
            'label'      => 'PayPal: ' . $flosc_paypal_status,
            'edit_url'   => $flosc_base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
    ] );

    // ── C — Content ───────────────────────────────────────────────────────────
    flosc_flow_card( 'C', 'Content', 'What learners see after purchase', [
        [
            'label'      => 'Companion: ' . esc_html($flosc_companion_mode_label . ' · ' . $flosc_companion_status),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Launch policy: ' . esc_html($flosc_companion_auto . ' · ' . $flosc_companion_exit . ' · ' . $flosc_companion_scroll . ' · ' . $flosc_companion_cooldown_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Effective behavior: ' . esc_html($flosc_companion_effective_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Active limits: ' . esc_html($flosc_companion_limits_label),
            'edit_url'   => $flosc_base_url . 'style',
            'edit_label' => 'Edit Style →',
        ],
        [
            'label'      => 'Lessons: ' . esc_html( $flosc_lessons_label ),
            'edit_url'   => $flosc_base_url . 'lessons',
            'edit_label' => 'Edit Lessons →',
        ],
        [
            'label'      => 'Member pills: ' . $flosc_member_pills . ' configured',
            'edit_url'   => $flosc_base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'AI: ' . esc_html( $flosc_ai_label ),
            'edit_url'   => $flosc_base_url . 'ai',
            'edit_label' => 'Edit AI →',
        ],
    ] );

    echo '<div class="flosc-flow-diagnostics">';
    echo '<div id="' . esc_attr( $flosc_diagnostics_start ) . '" class="flosc-flow-diagnostics__anchor"></div>';
    echo '<h3 class="flosc-flow-diagnostics__title">Diagnostics</h3>';
    echo '<p class="flosc-flow-diagnostics__hashes">Open <code>#' . esc_html( $flosc_diagnostics_start ) . '</code> to jump here, and <code>#' . esc_html( $flosc_diagnostics_end ) . '</code> to jump past the diagnostics block.</p>';
    echo '<ul class="flosc-flow-diagnostics__list">';
    echo '<li><strong>Flow:</strong> ' . esc_html( $flosc_diagnostics_flow_label ) . ' <code>' . esc_html( $flosc_selected_ivr ?: '(no IVR selected)' ) . '</code></li>';
    echo '<li><strong>Prompt panels:</strong> Visitor ' . esc_html( (string) $flosc_prompt_panel_counts['visitor'] ) . ', Guest ' . esc_html( (string) $flosc_prompt_panel_counts['guest'] ) . ', Member ' . esc_html( (string) $flosc_prompt_panel_counts['member'] ) . '</li>';
    echo '<li><strong>AI:</strong> ' . esc_html( $flosc_ai_label ) . '</li>';
    echo '<li><strong>Companion:</strong> ' . esc_html( $flosc_companion_mode_label . ' · ' . $flosc_companion_status . ' · ' . $flosc_companion_effective_label ) . '</li>';
    echo '</ul>';
    echo '<div id="' . esc_attr( $flosc_diagnostics_end ) . '" class="flosc-flow-diagnostics__anchor"></div>';
    echo '</div>';
    ?>

</div>
<?php flosc_tab_footer(); ?>
