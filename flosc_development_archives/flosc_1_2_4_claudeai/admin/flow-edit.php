<?php
/**
 * FLOSC Flow Edit Page v1.2.4
 * 
 * Comprehensive flow configuration with all settings categories:
 * - Identity (slug, domain, product info)
 * - IVR (conversation messages)
 * - Style (chat appearance)
 * - AI (provider, prompts)
 * - Knowledge (personality, content)
 * - Email (templates)
 * - Lessons (content config)
 * - STT (speech-to-text)
 * - Payments (Stripe, PayPal)
 * - Team (user access)
 */

if (!defined('ABSPATH')) exit;

$is_admin = current_user_can('manage_options');
$flow_id = sanitize_key($_GET['flow_id'] ?? '');
$is_new = ($flow_id === 'new');
$flow = $is_new ? null : flosc_flows()->get_flow($flow_id);

// Permission check
if (!$is_new && $flow && !flosc_flows()->can_access_flow_admin($flow_id)) {
    wp_die('You do not have permission to edit this flow.');
}

// Only admins can create new flows
if ($is_new && !$is_admin) {
    wp_die('Only administrators can create new flows.');
}

// Redirect if flow not found
if (!$is_new && !$flow) {
    wp_redirect(admin_url('admin.php?page=flosc-flows&error=not_found'));
    exit;
}

// Get current tab
$current_tab = sanitize_key($_GET['tab'] ?? 'identity');
$tabs = [
    'identity' => '🎯 Identity',
    'ivr' => '💬 IVR',
    'style' => '🎨 Style',
    'ai' => '🤖 AI',
    'knowledge' => '📚 Knowledge',
    'email' => '📧 Email',
    'lessons' => '📖 Lessons',
    'stt' => '🎙️ STT',
    'payments' => '💳 Payments',
];
if ($is_admin && !$is_new) {
    $tabs['team'] = '👥 Team';
}

// Handle form submission
$success_message = null;
$error_message = null;

if (isset($_POST['flosc_save_flow']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_flow')) {
    $data = flosc_process_flow_form_data($_POST, $current_tab);
    
    if ($is_new) {
        $data['id'] = !empty($data['slug']) ? sanitize_key($data['slug']) : 'flow_' . wp_generate_password(6, false, false);
        $result = flosc_flows()->create_flow($data);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } else {
            wp_redirect(admin_url('admin.php?page=flosc-flows&saved=1'));
            exit;
        }
    } else {
        $result = flosc_flows()->update_flow($flow_id, $data);
        
        if (is_wp_error($result)) {
            $error_message = $result->get_error_message();
        } else {
            $flow = flosc_flows()->get_flow($flow_id);
            $success_message = 'Flow updated successfully.';
        }
    }
}

// Handle team updates
if (isset($_POST['flosc_update_team']) && $is_admin && !$is_new && wp_verify_nonce($_POST['_wpnonce'], 'flosc_update_team')) {
    $selected_users = isset($_POST['team_users']) ? array_map('intval', $_POST['team_users']) : [];
    $current_users = flosc_flows()->get_flow_users($flow_id);
    $current_user_ids = array_map(function($u) { return $u->ID; }, $current_users);
    
    foreach ($current_user_ids as $uid) {
        if (!in_array($uid, $selected_users)) {
            flosc_flows()->revoke_flow_access($uid, $flow_id);
        }
    }
    
    foreach ($selected_users as $uid) {
        if (!in_array($uid, $current_user_ids)) {
            flosc_flows()->grant_flow_access($uid, $flow_id);
        }
    }
    
    $success_message = 'Team updated successfully.';
}

// Get available options
$ivr_files = flosc_flows()->get_available_ivr_files();
$quiz_types = flosc_flows()->get_available_quiz_types();
$categories = get_categories(['hide_empty' => false]);

/**
 * Process form data based on current tab
 */
function flosc_process_flow_form_data($post, $tab) {
    $data = [];
    
    switch ($tab) {
        case 'identity':
            $data = [
                'slug' => sanitize_title($post['flow_slug'] ?? ''),
                'custom_domain' => sanitize_text_field($post['flow_domain'] ?? ''),
                'status' => sanitize_key($post['flow_status'] ?? 'draft'),
                'product' => [
                    'name' => sanitize_text_field($post['product_name'] ?? ''),
                    'tagline' => sanitize_text_field($post['product_tagline'] ?? ''),
                    'emoji' => sanitize_text_field($post['product_emoji'] ?? '🎯'),
                    'logo_url' => esc_url_raw($post['product_logo'] ?? ''),
                    'primary_color' => sanitize_hex_color($post['product_color'] ?? '#4f46e5'),
                    'share_text' => sanitize_text_field($post['product_share_text'] ?? ''),
                ],
            ];
            break;
            
        case 'ivr':
            $data = [
                'ivr_file' => sanitize_file_name($post['ivr_file'] ?? ''),
                'wp_category_id' => intval($post['wp_category'] ?? 0),
                'quiz_type' => sanitize_key($post['quiz_type'] ?? ''),
            ];
            break;
            
        case 'style':
            $data = [
                'style' => [
                    'preset' => sanitize_key($post['style_preset'] ?? ''),
                    'bubble' => sanitize_key($post['style_bubble'] ?? ''),
                    'accent' => sanitize_hex_color($post['style_accent'] ?? '') ?: '',
                    'font' => sanitize_key($post['style_font'] ?? ''),
                    'scale' => isset($post['style_scale']) && $post['style_scale'] !== '' ? intval($post['style_scale']) : '',
                    'custom_css' => $post['style_custom_css'] ?? '', // Allow CSS
                ],
            ];
            break;
            
        case 'ai':
            $data = [
                'ai' => [
                    'provider' => sanitize_key($post['ai_provider'] ?? ''),
                    'openai_key' => $post['ai_openai_key'] ?? '',
                    'anthropic_key' => $post['ai_anthropic_key'] ?? '',
                    'xai_key' => $post['ai_xai_key'] ?? '',
                    'base_prompt' => sanitize_textarea_field($post['ai_base_prompt'] ?? ''),
                    'response_mode' => sanitize_key($post['ai_response_mode'] ?? ''),
                    'prompt_freeline' => sanitize_textarea_field($post['ai_prompt_freeline'] ?? ''),
                    'prompt_login' => sanitize_textarea_field($post['ai_prompt_login'] ?? ''),
                    'prompt_offer' => sanitize_textarea_field($post['ai_prompt_offer'] ?? ''),
                    'prompt_sale' => sanitize_textarea_field($post['ai_prompt_sale'] ?? ''),
                    'prompt_content' => sanitize_textarea_field($post['ai_prompt_content'] ?? ''),
                ],
            ];
            break;
            
        case 'knowledge':
            $data = [
                'knowledge' => [
                    'personality_name' => sanitize_text_field($post['knowledge_personality_name'] ?? ''),
                    'personality_role' => sanitize_text_field($post['knowledge_personality_role'] ?? ''),
                    'personality_traits' => sanitize_textarea_field($post['knowledge_personality_traits'] ?? ''),
                    'mission' => sanitize_textarea_field($post['knowledge_mission'] ?? ''),
                    'context_awareness' => sanitize_textarea_field($post['knowledge_context_awareness'] ?? ''),
                    'freeline_restrictions' => sanitize_textarea_field($post['knowledge_freeline_restrictions'] ?? ''),
                    'member_access' => sanitize_textarea_field($post['knowledge_member_access'] ?? ''),
                ],
            ];
            break;
            
        case 'email':
            $data = [
                'email' => [
                    'from_name' => sanitize_text_field($post['email_from_name'] ?? ''),
                    'from_email' => sanitize_email($post['email_from_email'] ?? ''),
                    'quiz_subject' => sanitize_text_field($post['email_quiz_subject'] ?? ''),
                    'quiz_body' => sanitize_textarea_field($post['email_quiz_body'] ?? ''),
                    'send_on_quiz_complete' => isset($post['email_send_on_quiz_complete']) ? '1' : '',
                    'congrats_threshold' => isset($post['email_congrats_threshold']) && $post['email_congrats_threshold'] !== '' ? intval($post['email_congrats_threshold']) : '',
                ],
            ];
            break;
            
        case 'lessons':
            $data = [
                'lessons' => [
                    'category' => sanitize_text_field($post['lessons_category'] ?? ''),
                    'oto_offer_id' => sanitize_key($post['lessons_oto_offer_id'] ?? ''),
                    'free_mode' => sanitize_key($post['lessons_free_mode'] ?? ''),
                    'free_count' => isset($post['lessons_free_count']) && $post['lessons_free_count'] !== '' ? intval($post['lessons_free_count']) : '',
                ],
            ];
            break;
            
        case 'stt':
            $data = [
                'stt' => [
                    'provider' => sanitize_key($post['stt_provider'] ?? ''),
                    'assemblyai_key' => $post['stt_assemblyai_key'] ?? '',
                    'deepgram_key' => $post['stt_deepgram_key'] ?? '',
                    'custom_endpoint' => esc_url_raw($post['stt_custom_endpoint'] ?? ''),
                ],
            ];
            break;
            
        case 'payments':
            $data = [
                'payments' => [
                    'stripe_enabled' => isset($post['payments_stripe_enabled']) ? '1' : '',
                    'stripe_mode' => sanitize_key($post['payments_stripe_mode'] ?? ''),
                    'stripe_test_pk' => $post['payments_stripe_test_pk'] ?? '',
                    'stripe_test_sk' => $post['payments_stripe_test_sk'] ?? '',
                    'stripe_live_pk' => $post['payments_stripe_live_pk'] ?? '',
                    'stripe_live_sk' => $post['payments_stripe_live_sk'] ?? '',
                    'stripe_webhook_secret' => $post['payments_stripe_webhook_secret'] ?? '',
                    'paypal_enabled' => isset($post['payments_paypal_enabled']) ? '1' : '',
                    'paypal_client_id' => $post['payments_paypal_client_id'] ?? '',
                    'paypal_secret' => $post['payments_paypal_secret'] ?? '',
                    'paypal_mode' => sanitize_key($post['payments_paypal_mode'] ?? ''),
                ],
            ];
            break;
    }
    
    return $data;
}

/**
 * Helper to show "Using global" placeholder
 */
function flosc_global_placeholder($key, $label = null) {
    $global_value = flosc_get_global_setting($key, '');
    if (empty($global_value)) {
        return $label ? "Using global ({$label})" : "Using global default";
    }
    $display = is_string($global_value) ? substr($global_value, 0, 50) : '(configured)';
    if (strlen($global_value) > 50) $display .= '...';
    return "Using global: " . esc_attr($display);
}
?>

<div class="wrap">
    <h1>
        <a href="<?php echo admin_url('admin.php?page=flosc-flows'); ?>">← Flows</a>
        &nbsp;/&nbsp;
        <?php echo $is_new ? 'New Flow' : esc_html($flow['product']['name'] ?? $flow_id); ?>
    </h1>
    
    <?php if ($error_message): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="notice notice-success is-dismissible">
            <p>✓ <?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!$is_new): ?>
        <!-- Tabs -->
        <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
            <?php foreach ($tabs as $tab_id => $tab_label): ?>
                <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=' . urlencode($flow_id) . '&tab=' . $tab_id); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    
    <div class="card" style="max-width: 900px; padding: 20px;">
        
        <?php if (!$is_new && $current_tab !== 'team'): ?>
            <div style="background: #f0f6ff; border: 1px solid #c3dafe; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px;">
                <strong>💡 Tip:</strong> Leave fields empty to use global defaults. Only override what needs to be different for this flow.
            </div>
        <?php endif; ?>
        
        <?php 
        // Include the appropriate tab content
        switch ($current_tab) {
            case 'identity':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/identity.php';
                break;
            case 'ivr':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/ivr.php';
                break;
            case 'style':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/style.php';
                break;
            case 'ai':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/ai.php';
                break;
            case 'knowledge':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/knowledge.php';
                break;
            case 'email':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/email.php';
                break;
            case 'lessons':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/lessons.php';
                break;
            case 'stt':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/stt.php';
                break;
            case 'payments':
                include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/payments.php';
                break;
            case 'team':
                if ($is_admin && !$is_new) {
                    include FLOSC_PLUGIN_DIR . 'admin/flow-tabs/team.php';
                }
                break;
        }
        ?>
    </div>
</div>

<style>
.flosc-form-field {
    margin-bottom: 20px;
}
.flosc-form-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}
.flosc-form-field .description {
    color: #6b7280;
    font-size: 13px;
    margin-top: 6px;
}
.flosc-form-field input[type="text"],
.flosc-form-field input[type="password"],
.flosc-form-field input[type="email"],
.flosc-form-field input[type="url"],
.flosc-form-field select,
.flosc-form-field textarea {
    width: 100%;
    max-width: 500px;
}
.flosc-form-field textarea {
    min-height: 100px;
}
.flosc-form-field input::placeholder,
.flosc-form-field textarea::placeholder {
    color: #9ca3af;
    font-style: italic;
}
.flosc-section-header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 10px;
    margin: 30px 0 20px 0;
}
.flosc-section-header:first-child {
    margin-top: 0;
}
.flosc-clear-btn {
    font-size: 11px;
    color: #6b7280;
    cursor: pointer;
    margin-left: 8px;
}
.flosc-clear-btn:hover {
    color: #dc2626;
}
</style>
