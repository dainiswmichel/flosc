<?php
/**
 * FLOSC Flow Tab — F→L→O→S→C read-only phase overview
 *
 * Shows live counts and edit links for each of the five FLOSC funnel phases.
 * Data sourced from $flow_settings (via $GLOBALS) and flosc() helper objects.
 *
 * v4.0.0: Initial implementation
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$flow_settings  = $GLOBALS['flosc_current_settings'] ?? [];
$selected_ivr   = $GLOBALS['flosc_current_ivr']      ?? '';
$ivr_param      = urlencode( $selected_ivr );
$base_url       = admin_url( 'admin.php?page=flosc-settings&ivr=' . $ivr_param . '&tab=' );

// ── Gather live data ──────────────────────────────────────────────────────────

// ── Parse IVR file once — used for pill counts across all phases ──────────────
$ivr_pill_counts = [ 'freeline' => 0, 'offer' => 0, 'content' => 0 ];
$ivr_path = FLOSC_PLUGIN_DIR . 'ai_configuration_files/' . $selected_ivr;
if ( $selected_ivr && file_exists( $ivr_path ) && class_exists( 'FLOSC_IVR_Parser' ) ) {
    $ivr_parser = FLOSC_IVR_Parser::flosc_instance();
    $ivr_data   = $ivr_parser->flosc_parse( file_get_contents( $ivr_path ) );
    foreach ( array_keys( $ivr_pill_counts ) as $phase ) {
        foreach ( $ivr_data['phases'][ $phase ] ?? [] as $name ) {
            if ( ( $ivr_data['messages'][ $name ]['type'] ?? '' ) === 'suggested_user_autoprompt' ) {
                $ivr_pill_counts[ $phase ]++;
            }
        }
    }
}

// F — Freeline: quiz + visitor pills + IVR file
// Match quiz.php's default: flosc_sample_data_numbers_quiz is the default active quiz
$enabled_quizzes = $flow_settings['enabled_quizzes'] ?? ['flosc_sample_data_numbers_quiz'];
if ( ! is_array( $enabled_quizzes ) ) $enabled_quizzes = ['flosc_sample_data_numbers_quiz'];
$quiz_configured  = ! empty( $enabled_quizzes );
$quiz_count       = count( $enabled_quizzes );
$quiz_word        = $quiz_count === 1 ? 'Quiz' : 'Quizzes';
$edit_quiz_label  = $quiz_count === 1 ? 'Edit Quiz →' : 'Edit Quizzes →';

if ( $quiz_configured && class_exists( 'FLOSC_Quiz_Registry' ) ) {
    $names = [];
    foreach ( $enabled_quizzes as $qid ) {
        $qt      = FLOSC_Quiz_Registry::get_quiz( $qid );
        $names[] = $qt ? $qt->get_name() : ucwords( str_replace( '_', ' ', $qid ) );
    }
    $bullets         = array_map( fn( $n ) => '<span style="display:block;padding-left:8px;font-size:12px;color:#50575e;">• ' . esc_html( $n ) . '</span>', $names );
    $quiz_label_html = '<strong>' . $quiz_count . ' ' . $quiz_word . ' ✅</strong>' . implode( '', $bullets );
} else {
    $quiz_label_html = '<strong>0 Quizzes ❌</strong>';
    $edit_quiz_label = 'Edit Quiz →';
}

$visitor_pills = count( $flow_settings['autoprompts']['visitor'] ?? [] );
$ivr_file_label      = $selected_ivr ?: 'None configured';

// L — Login: SSO providers
$sso_providers   = [];
foreach ( ['google', 'apple', 'facebook', 'microsoft', 'linkedin'] as $p ) {
    if ( ! empty( $flow_settings[ 'sso_' . $p . '_enabled' ] ) ) {
        $sso_providers[] = ucfirst( $p );
    }
}
$sso_label = $sso_providers ? implode( ', ', $sso_providers ) : 'WordPress native';

// O — Offer: offers count + guest pills
$flow_id_key    = $selected_ivr ? pathinfo( $selected_ivr, PATHINFO_FILENAME ) : null;
$all_offers     = [];
$active_count   = 0;
$draft_count    = 0;
if ( function_exists( 'flosc' ) && $flow_id_key ) {
    $all_offers = flosc()->sale_manager->offers()->get_all_offers( $flow_id_key );
    foreach ( $all_offers as $o ) {
        if ( ( $o['status'] ?? 'draft' ) === 'active' ) {
            $active_count++;
        } else {
            $draft_count++;
        }
    }
}
$offers_label  = $active_count . ' active' . ( $draft_count ? ', ' . $draft_count . ' draft' : '' );
$guest_pills   = count( $flow_settings['autoprompts']['guest'] ?? [] ) + $ivr_pill_counts['offer'];

// S — Sale: payment providers (read directly from flow_settings — same source as Payments tab)
$paypal_cfg  = ! empty( $flow_settings['paypal_enabled'] )
               && ! empty( $flow_settings['paypal_client_id'] )
               && ! empty( $flow_settings['paypal_secret'] );
$paypal_mode = $flow_settings['paypal_mode'] ?? 'sandbox';

$stripe_mode = $flow_settings['stripe_mode'] ?? 'test';
$stripe_sk   = $stripe_mode === 'live'
               ? ( $flow_settings['stripe_live_sk'] ?? '' )
               : ( $flow_settings['stripe_test_sk'] ?? '' );
$stripe_cfg  = ! empty( $flow_settings['stripe_enabled'] ) && ! empty( $stripe_sk );

// C — Content: lessons + member pills + AI provider
$lesson_groups  = $flow_settings['lesson_groups'] ?? [];
if ( empty( $lesson_groups ) && ! empty( $flow_settings['lessons_category'] ) ) {
    $lesson_groups = [ [ 'category' => $flow_settings['lessons_category'] ] ];
}
$lesson_count  = count( $lesson_groups );
$lessons_label = $lesson_count ? $lesson_count . ' lesson group' . ( $lesson_count !== 1 ? 's' : '' ) : 'Not configured';

$member_pills  = count( $flow_settings['autoprompts']['member'] ?? [] ) + $ivr_pill_counts['content'];

$ai_provider   = $flow_settings['ai_provider'] ?? 'ivr';
$ai_labels     = [
    'ivr'       => 'IVR / Scripted only',
    'anthropic' => 'Anthropic Claude',
    'openai'    => 'OpenAI',
    'xai'       => 'xAI Grok',
];
$ai_label = $ai_labels[ $ai_provider ] ?? ucfirst( $ai_provider );
if ( $ai_provider === 'anthropic' ) {
    $ai_model  = $flow_settings['ai_model'] ?? flosc_get_setting( 'ai_model', 'claude-sonnet-4-6' );
    $ai_label .= ' (' . esc_html( $ai_model ) . ')';
}

// ── Helper: render a phase card ───────────────────────────────────────────────
function flosc_flow_card( $letter, $phase_name, $subtitle, $rows ) {
    $colors = [
        'F' => [ 'bg' => '#f0f0f1', 'border' => '#c3c4c7', 'badge' => '#3c434a', 'badge_text' => '#fff' ],
        'L' => [ 'bg' => '#fff4e6', 'border' => '#f59e0b', 'badge' => '#f59e0b', 'badge_text' => '#fff' ],
        'O' => [ 'bg' => '#fef3c7', 'border' => '#fbbf24', 'badge' => '#d97706', 'badge_text' => '#fff' ],
        'S' => [ 'bg' => '#fef9c3', 'border' => '#facc15', 'badge' => '#ca8a04', 'badge_text' => '#fff' ],
        'C' => [ 'bg' => '#f0fdf4', 'border' => '#86efac', 'badge' => '#16a34a', 'badge_text' => '#fff' ],
    ];
    $c = $colors[ $letter ] ?? $colors['F'];
    echo '<div style="background:' . $c['bg'] . ';border:1px solid ' . $c['border'] . ';border-radius:6px;padding:16px 18px;margin-bottom:12px;">';
    echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">';
    echo '<span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:' . $c['badge'] . ';color:' . $c['badge_text'] . ';font-size:18px;font-weight:900;flex-shrink:0;">' . esc_html( $letter ) . '</span>';
    echo '<div>';
    echo '<div style="font-size:15px;font-weight:700;color:#1d2327;">' . esc_html( strtoupper( $phase_name ) ) . '</div>';
    echo '<div style="font-size:12px;color:#50575e;">' . esc_html( $subtitle ) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '<table style="width:100%;border-collapse:collapse;">';
    foreach ( $rows as $row ) {
        echo '<tr>';
        echo '<td style="padding:5px 0;color:#3c434a;font-size:13px;width:65%;">' . $row['label'] . '</td>';
        echo '<td style="padding:5px 0;text-align:right;">';
        if ( ! empty( $row['edit_url'] ) ) {
            echo '<a href="' . esc_url( $row['edit_url'] ) . '" class="button button-small" style="font-size:11px;">' . esc_html( $row['edit_label'] ?? 'Edit →' ) . '</a>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
}

// ── Output ────────────────────────────────────────────────────────────────────
?>
<div style="max-width:720px;">

    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:14px 18px;margin-bottom:20px;">
        <h2 style="margin:0 0 4px;font-size:16px;color:#1d2327;">🗺 FLOSC Funnel Overview</h2>
        <p style="margin:0;color:#50575e;font-size:13px;">Read-only snapshot of all five funnel phases for <strong><?php echo esc_html( $selected_ivr ?: 'this flow' ); ?></strong>. Click any Edit button to jump to that tab.</p>
    </div>

    <?php
    // ── F — Freeline ──────────────────────────────────────────────────────────
    flosc_flow_card( 'F', 'Freeline', 'What visitors see before logging in', [
        [
            'label'      => $quiz_label_html,
            'edit_url'   => $base_url . 'quiz',
            'edit_label' => $edit_quiz_label,
        ],
        [
            'label'      => 'Visitor pills: ' . $visitor_pills . ' configured',
            'edit_url'   => $base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'IVR file: ' . esc_html( $ivr_file_label ),
            'edit_url'   => $base_url . 'ivr-messages',
            'edit_label' => 'Edit IVR →',
        ],
    ] );

    // ── L — Login ─────────────────────────────────────────────────────────────
    flosc_flow_card( 'L', 'Login', 'Account gate', [
        [
            'label'      => 'SSO: ' . esc_html( $sso_label ),
            'edit_url'   => $base_url . 'sso',
            'edit_label' => 'Edit SSO →',
        ],
    ] );

    // ── O — Offer ─────────────────────────────────────────────────────────────
    flosc_flow_card( 'O', 'Offer', 'Upgrade prompts for guests', [
        [
            'label'      => 'Offers: ' . esc_html( $offers_label ?: 'None configured' ),
            'edit_url'   => $base_url . 'offers',
            'edit_label' => 'Edit Offers →',
        ],
        [
            'label'      => 'Guest pills: ' . $guest_pills . ' configured',
            'edit_url'   => $base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
    ] );

    // ── S — Sale ──────────────────────────────────────────────────────────────
    $stripe_status = $stripe_cfg
        ? '✅ configured' . ( $stripe_mode ? ' (' . esc_html( $stripe_mode ) . ')' : '' )
        : '❌ not configured';
    $paypal_status = $paypal_cfg
        ? '✅ configured' . ( $paypal_mode ? ' (' . esc_html( $paypal_mode ) . ')' : '' )
        : '❌ not configured';

    flosc_flow_card( 'S', 'Sale', 'Payment processing', [
        [
            'label'      => 'Stripe: ' . $stripe_status,
            'edit_url'   => $base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
        [
            'label'      => 'PayPal: ' . $paypal_status,
            'edit_url'   => $base_url . 'payments',
            'edit_label' => 'Edit Pay →',
        ],
    ] );

    // ── C — Content ───────────────────────────────────────────────────────────
    flosc_flow_card( 'C', 'Content', 'What learners see after purchase', [
        [
            'label'      => 'Lessons: ' . esc_html( $lessons_label ),
            'edit_url'   => $base_url . 'lessons',
            'edit_label' => 'Edit Lessons →',
        ],
        [
            'label'      => 'Member pills: ' . $member_pills . ' configured',
            'edit_url'   => $base_url . 'autoprompts',
            'edit_label' => 'Edit Pills →',
        ],
        [
            'label'      => 'AI: ' . esc_html( $ai_label ),
            'edit_url'   => $base_url . 'ai',
            'edit_label' => 'Edit AI →',
        ],
    ] );
    ?>

</div>
<?php flosc_tab_footer(); ?>
