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

// F — Freeline: quiz + visitor pills + IVR file
$quiz_type       = $flow_settings['quiz_type'] ?? get_option( 'flosc_quiz_type', '' );
$quiz_label      = $quiz_type ? ucwords( str_replace( '_', ' ', $quiz_type ) ) : 'None configured';
$quiz_configured = ! empty( $quiz_type );

$visitor_pills   = count( $flow_settings['autoprompts']['visitor'] ?? [] );
$ivr_file_label  = $selected_ivr ?: 'None configured';

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
    $all_offers = flosc()->sale_manager->offer_manager->get_all_offers( $flow_id_key );
    foreach ( $all_offers as $o ) {
        if ( ( $o['status'] ?? 'draft' ) === 'active' ) {
            $active_count++;
        } else {
            $draft_count++;
        }
    }
}
$offers_label  = $active_count . ' active' . ( $draft_count ? ', ' . $draft_count . ' draft' : '' );
$guest_pills   = count( $flow_settings['autoprompts']['guest'] ?? [] );

// S — Sale: payment providers
$stripe_cfg  = false;
$paypal_cfg  = false;
$stripe_mode = '';
$paypal_mode = '';
if ( function_exists( 'flosc' ) ) {
    $stripe = flosc()->sale_manager->get_provider( 'stripe' );
    if ( $stripe && $stripe->is_configured() ) {
        $stripe_cfg  = true;
        $cfg         = $stripe->get_client_config();
        $stripe_mode = $cfg['mode'] ?? 'live';
    }
    $paypal = flosc()->sale_manager->get_provider( 'paypal' );
    if ( $paypal && $paypal->is_configured() ) {
        $paypal_cfg  = true;
        $cfg         = $paypal->get_client_config();
        $paypal_mode = $cfg['mode'] ?? 'sandbox';
    }
}

// C — Content: lessons + member pills + AI provider
$lesson_groups  = $flow_settings['lesson_groups'] ?? [];
if ( empty( $lesson_groups ) && ! empty( $flow_settings['lessons_category'] ) ) {
    $lesson_groups = [ [ 'category' => $flow_settings['lessons_category'] ] ];
}
$lesson_count  = count( $lesson_groups );
$lessons_label = $lesson_count ? $lesson_count . ' lesson group' . ( $lesson_count !== 1 ? 's' : '' ) : 'Not configured';

$member_pills  = count( $flow_settings['autoprompts']['member'] ?? [] );

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
            'label'      => 'Quiz: ' . esc_html( $quiz_label ) . ( $quiz_configured ? ' ✅' : ' ❌' ),
            'edit_url'   => $base_url . 'quiz',
            'edit_label' => 'Edit Quiz →',
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
            'edit_label' => 'Edit Less →',
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
