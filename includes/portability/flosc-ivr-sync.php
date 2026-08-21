<?php
/**
 * Per-flow IVR load/import helpers (uploads-bound runtime options).
 *
 * @package FLOSC
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default per-flow option key when a caller omits $flow_key.
 * Runtime config is never stored in global flosc_ivr_messages / flosc_ivr_styles options.
 */
function flosc_default_flow_option_key() {
    return 'flosc_flow_flosc_default_technical_ivr';
}

/**
 * Runtime-only fields that should never be exported as portable settings.
 *
 * @return string[]
 */
function flosc_portable_settings_runtime_excludes() {
    return array(
        'flow_messages',
        'flow_phases',
        'flow_styles',
        'flow_last_import',
        'ivr_last_import',
        'ivr_last_export',
        'offers_removed_by_sync',
    );
}

/**
 * Return true when a setting path segment should be treated as secret.
 *
 * @param string $segment
 * @return bool
 */
function flosc_portable_is_secret_segment($segment) {
    $segment = strtolower((string) $segment);
    if ($segment === '') {
        return false;
    }
    return (bool) preg_match(
        '/(^|_)(api_key|client_secret|secret|secret_key|private_key|access_token|refresh_token|webhook_secret|smtp_pass|oauth_client_secret|paypal_secret|stripe_secret)(_|$)/',
        $segment
    );
}

/**
 * Return true when a full setting path contains secret-looking segments.
 *
 * @param string[] $path
 * @return bool
 */
function flosc_portable_is_secret_path(array $path) {
    foreach ($path as $segment) {
        if (flosc_portable_is_secret_segment($segment)) {
            return true;
        }
    }
    return false;
}

/**
 * Deep clone + filter to exportable settings (non-secret, non-runtime).
 *
 * @param array $fs
 * @return array
 */
function flosc_portable_collect_exportable_settings(array $fs) {
    $excluded = array_fill_keys(flosc_portable_settings_runtime_excludes(), true);
    $out = array();

    foreach ($fs as $key => $value) {
        $key = (string) $key;
        if ($key === '' || isset($excluded[$key])) {
            continue;
        }
        if (flosc_portable_is_secret_path(array($key))) {
            continue;
        }
        $filtered = flosc_portable_filter_secret_values($value, array($key));
        if ($filtered === null && $value !== null) {
            continue;
        }
        $out[$key] = $filtered;
    }

    ksort($out);
    return $out;
}

/**
 * Remove secret subkeys recursively.
 *
 * @param mixed $value
 * @param string[] $path
 * @return mixed|null
 */
function flosc_portable_filter_secret_values($value, array $path) {
    if (flosc_portable_is_secret_path($path)) {
        return null;
    }

    if (!is_array($value)) {
        return $value;
    }

    $result = array();
    foreach ($value as $k => $v) {
        $child_path = $path;
        $child_path[] = (string) $k;
        $filtered = flosc_portable_filter_secret_values($v, $child_path);
        if ($filtered !== null || $v === null) {
            $result[$k] = $filtered;
        }
    }
    return $result;
}

/**
 * Convert arrays to scalar-friendly YAML using maps only (no '-' list syntax).
 *
 * @param mixed $value
 * @return mixed
 */
function flosc_portable_to_yaml_shape($value) {
    if (!is_array($value)) {
        return $value;
    }

    $result = array();
    if (array_values($value) === $value) {
        foreach ($value as $index => $item) {
            $result[(string) $index] = flosc_portable_to_yaml_shape($item);
        }
        return $result;
    }

    foreach ($value as $k => $v) {
        $result[(string) $k] = flosc_portable_to_yaml_shape($v);
    }
    return $result;
}

/**
 * Convert numeric-key maps back to indexed arrays.
 *
 * @param mixed $value
 * @return mixed
 */
function flosc_portable_from_yaml_shape($value) {
    if (!is_array($value)) {
        return $value;
    }

    $converted = array();
    foreach ($value as $k => $v) {
        $converted[$k] = flosc_portable_from_yaml_shape($v);
    }

    $keys = array_keys($converted);
    $all_numeric = !empty($keys);
    foreach ($keys as $k) {
        if (!preg_match('/^\d+$/', (string) $k)) {
            $all_numeric = false;
            break;
        }
    }
    if (!$all_numeric) {
        return $converted;
    }

    $ints = array_map('intval', $keys);
    sort($ints);
    $expected = range(0, count($ints) - 1);
    if ($ints !== $expected) {
        return $converted;
    }

    $list = array();
    foreach ($expected as $i) {
        $list[] = $converted[(string) $i];
    }
    return $list;
}

/**
 * YAML key formatter.
 *
 * @param string $key
 * @return string
 */
function flosc_portable_yaml_key($key) {
    $key = (string) $key;
    if (preg_match('/^[A-Za-z0-9_.-]+$/', $key)) {
        return $key;
    }
    return "'" . str_replace("'", "''", $key) . "'";
}

/**
 * YAML scalar formatter.
 *
 * @param mixed $value
 * @return string
 */
function flosc_portable_yaml_scalar($value) {
    if ($value === null) {
        return 'null';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    $text = (string) $value;
    $text = str_replace(array('\\', '"'), array('\\\\', '\\"'), $text);
    $text = str_replace(array("\r\n", "\r", "\n"), '\\n', $text);
    return '"' . $text . '"';
}

/**
 * Emit YAML from a map-only array shape.
 *
 * @param array $map
 * @param int $depth
 * @return string
 */
function flosc_portable_emit_yaml_map(array $map, $depth = 0) {
    $indent = str_repeat('  ', max(0, (int) $depth));
    $lines = array();
    foreach ($map as $key => $value) {
        $yaml_key = flosc_portable_yaml_key((string) $key);
        if (is_array($value)) {
            if (empty($value)) {
                $lines[] = $indent . $yaml_key . ': {}';
            } else {
                $lines[] = $indent . $yaml_key . ':';
                $lines[] = flosc_portable_emit_yaml_map($value, $depth + 1);
            }
        } else {
            $lines[] = $indent . $yaml_key . ': ' . flosc_portable_yaml_scalar($value);
        }
    }
    return implode("\n", $lines);
}

/**
 * Build YAML settings block text.
 *
 * @param array $settings
 * @return string
 */
function flosc_portable_build_settings_block(array $settings) {
    if (empty($settings)) {
        return '';
    }

    $yaml_shape = flosc_portable_to_yaml_shape($settings);
    $yaml = flosc_portable_emit_yaml_map($yaml_shape, 0);

    return "## Settings (YAML)\n```yaml\n" . $yaml . "\n```\n\n";
}

/**
 * Extract YAML settings block from markdown.
 *
 * @param string $markdown
 * @return string
 */
function flosc_portable_extract_settings_yaml($markdown) {
    $markdown = (string) $markdown;
    if ($markdown === '') {
        return '';
    }

    $pattern = '/^\s*#{1,2}\s+Settings(?:\s*\(YAML\))?\s*\R```yaml\R(.*?)\R```/ims';
    if (preg_match($pattern, $markdown, $m)) {
        return trim((string) ($m[1] ?? ''));
    }
    return '';
}

/**
 * Remove settings block from markdown before IVR message parsing.
 *
 * @param string $markdown
 * @return string
 */
function flosc_portable_strip_settings_block($markdown) {
    $markdown = (string) $markdown;
    if ($markdown === '') {
        return $markdown;
    }
    return preg_replace('/^\s*#{1,2}\s+Settings(?:\s*\(YAML\))?\s*\R```yaml\R.*?\R```\s*\R?/ims', '', $markdown);
}

/**
 * Parse simple map-based YAML (emitted by flosc_portable_emit_yaml_map).
 *
 * @param string $yaml
 * @return array{success:bool,data:array,error:string}
 */
function flosc_portable_parse_yaml_map($yaml) {
    $yaml = (string) $yaml;
    if (trim($yaml) === '') {
        return array('success' => true, 'data' => array(), 'error' => '');
    }

    $lines = preg_split('/\R/', $yaml);
    $root = array();
    $stack = array();
    $stack[] = array('indent' => -1, 'ref' => &$root);

    foreach ($lines as $raw_line) {
        $line = rtrim((string) $raw_line, "\r\n");
        if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
            continue;
        }

        if (!preg_match('/^(\s*)([^:]+):(.*)$/', $line, $m)) {
            return array('success' => false, 'data' => array(), 'error' => 'Invalid YAML line: ' . trim($line));
        }

        $indent = strlen($m[1]);
        if (($indent % 2) !== 0) {
            return array('success' => false, 'data' => array(), 'error' => 'Indent must be multiples of 2 spaces');
        }

        while (count($stack) > 0 && $stack[count($stack) - 1]['indent'] >= $indent) {
            array_pop($stack);
        }
        if (empty($stack)) {
            return array('success' => false, 'data' => array(), 'error' => 'Invalid YAML indentation hierarchy');
        }

        $key = trim((string) $m[2]);
        if (preg_match('/^\'(.*)\'$/', $key, $km)) {
            $key = str_replace("''", "'", $km[1]);
        }

        $tail = ltrim((string) $m[3]);
        $parent_ref = &$stack[count($stack) - 1]['ref'];

        if ($tail === '') {
            $parent_ref[$key] = array();
            $stack[] = array('indent' => $indent, 'ref' => &$parent_ref[$key]);
            continue;
        }

        if ($tail === '{}') {
            $parent_ref[$key] = array();
            continue;
        }

        $parent_ref[$key] = flosc_portable_parse_yaml_scalar($tail);
    }

    return array('success' => true, 'data' => $root, 'error' => '');
}

/**
 * Parse scalar values emitted by flosc_portable_yaml_scalar().
 *
 * @param string $tail
 * @return mixed
 */
function flosc_portable_parse_yaml_scalar($tail) {
    $tail = trim((string) $tail);
    $lower = strtolower($tail);

    if ($lower === 'null') {
        return null;
    }
    if ($lower === 'true') {
        return true;
    }
    if ($lower === 'false') {
        return false;
    }
    if (preg_match('/^-?\d+$/', $tail)) {
        return intval($tail);
    }
    if (preg_match('/^-?\d+\.\d+$/', $tail)) {
        return floatval($tail);
    }
    if (preg_match('/^"(.*)"$/s', $tail, $m)) {
        $text = $m[1];
        $text = str_replace('\\n', "\n", $text);
        $text = str_replace('\\"', '"', $text);
        $text = str_replace('\\\\', '\\', $text);
        return $text;
    }

    return $tail;
}

/**
 * Merge settings recursively (incoming overrides existing values).
 *
 * @param array $base
 * @param array $incoming
 * @return array
 */
function flosc_portable_deep_merge(array $base, array $incoming) {
    foreach ($incoming as $k => $v) {
        if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
            $base[$k] = flosc_portable_deep_merge($base[$k], $v);
        } else {
            $base[$k] = $v;
        }
    }
    return $base;
}

/**
 * Allowed top-level keys for import in addition to keys already present in flow option.
 *
 * @return string[]
 */
function flosc_portable_settings_bootstrap_allowlist() {
    return array(
        'ai_anthropic_model',
        'ai_base_prompt',
        'ai_boundaries',
        'ai_chain_provider_1',
        'ai_chain_provider_2',
        'ai_chain_provider_3',
        'ai_context_awareness',
        'ai_enable_chaining',
        'ai_enable_content_access',
        'ai_enable_ivr_context',
        'ai_freeline_restrictions',
        'ai_max_tokens',
        'ai_member_access',
        'ai_mission',
        'ai_off_topic_links',
        'ai_off_topic_message',
        'ai_openai_model',
        'ai_personality_name',
        'ai_personality_role',
        'ai_personality_traits',
        'personality_library_id',
        'ai_prompt_content',
        'ai_prompt_freeline',
        'ai_prompt_login',
        'ai_prompt_offer',
        'ai_prompt_sale',
        'ai_provider',
        'ai_response_mode',
        'ai_temperature',
        'ai_topic_scope',
        'ai_xai_model',
        'ai_gemini_model',
        'audio_conversion_provider',
        'audio_quiz_complete_message',
        'audio_quiz_escape_after_phrase',
        'audio_quiz_escape_enabled',
        'audio_quiz_escape_once',
        'audio_quiz_phoneme_lesson_map',
        'audio_quiz_phrase_complete_message',
        'audio_quiz_results_message',
        'audio_quiz_upsell_message',
        'auth_login_modal_button_text',
        'auth_login_modal_dismiss_message',
        'auth_login_modal_email_label',
        'auth_login_modal_email_placeholder',
        'auth_login_modal_loading_text',
        'auth_login_modal_sso_divider',
        'auth_login_modal_subtitle',
        'auth_login_modal_success_message',
        'auth_login_modal_terms_text',
        'auth_login_modal_title',
        'auth_modal_button_text',
        'auth_modal_dismiss_message',
        'auth_modal_email_label',
        'auth_modal_email_placeholder',
        'auth_modal_loading_text',
        'auth_modal_sso_divider',
        'auth_modal_subtitle',
        'auth_modal_success_message',
        'auth_modal_terms_text',
        'auth_modal_title',
        'avatar_radius',
        'badgeUrl',
        'catalog',
        'chat_style_accent',
        'chat_style_bubble',
        'chat_style_font',
        'chat_style_preset',
        'chat_style_scale',
        'chat_token_enforcement',
        'chatlogo_url',
        'companion_accent_color',
        'companion_allow_escape_close',
        'companion_auto_open_delay_ms',
        'companion_auto_open_enabled',
        'companion_auto_open_once_per_session',
        'companion_chat_app_url',
        'companion_close_aria_label',
        'companion_content_display_mode',
        'companion_context_scope',
        'companion_contextual_prompt',
        'companion_enable_keyboard_shortcut',
        'companion_enabled',
        'companion_exclude_categories',
        'companion_exclude_pages',
        'companion_exclude_posts',
        'companion_exclude_tags',
        'companion_flow_slug',
        'companion_focus_on_open',
        'companion_greeting',
        'companion_header_icon_url',
        'companion_hub_companion_url',
        'companion_hub_fullscreen_url',
        'companion_include_categories',
        'companion_include_pages',
        'companion_include_posts',
        'companion_include_tags',
        'companion_keyboard_shortcut_key',
        'companion_launch_on_exit_intent',
        'companion_launch_on_scroll_percent',
        'companion_launch_on_scroll_threshold',
        'companion_launcher_aria_label',
        'companion_launcher_icon',
        'companion_launcher_size',
        'companion_mobile_behavior',
        'companion_motion_mode',
        'companion_page_intent_phrases',
        'companion_panel_height',
        'companion_panel_width',
        'companion_pass_page_context',
        'companion_position',
        'companion_profile_tier_guest',
        'companion_profile_tier_guest_label',
        'companion_profile_tier_member',
        'companion_profile_tier_member_label',
        'companion_profile_tier_visitor',
        'companion_profile_tier_visitor_label',
        'companion_remember_open_state',
        'companion_routing_mode',
        'companion_show_for_visitors',
        'companion_show_header_subtitle',
        'companion_show_header_title',
        'companion_show_open_fullpage',
        'companion_state_storage',
        'companion_subtitle',
        'companion_target_exclude',
        'companion_target_exclude_custom',
        'companion_target_include',
        'companion_target_include_custom',
        'companion_target_mode',
        'companion_trigger_cooldown_ms',
        'companion_trigger_desktop_only',
        'companion_trigger_min_page_time_ms',
        'companion_trigger_suppress_on_auth_checkout',
        'companion_trigger_suppress_path_patterns',
        'contact_form_accent_color',
        'contact_form_button_bg_color',
        'contact_form_button_text_color',
        'contact_form_card_background',
        'contact_form_duplicate_window_minutes',
        'contact_form_email_subject',
        'contact_form_forward_to_email',
        'contact_form_intro',
        'contact_form_max_submissions_per_hour',
        'contact_form_message_font_family',
        'contact_form_message_font_size',
        'contact_form_min_submit_seconds',
        'contact_form_submit_text',
        'contact_form_success_message',
        'contact_form_title',
        'content_item_category',
        'content_item_group_category',
        'content_item_group_quiz',
        'content_item_groups',
        'content_item_label_plural',
        'content_item_label_singular',
        'content_type_plural',
        'content_type_singular',
        'content_types',
        'custom_stt_endpoint',
        'da1_payload',
        'da1_save_catalog',
        'data_deletion_content',
        'delete_ivr_file',
        'domain',
        'duplicate_ivr_file',
        'editing_file',
        'email',
        'email_body',
        'email_congrats_threshold',
        'email_encouragement_threshold',
        'email_from_address',
        'email_from_name',
        'email_on_quiz_complete',
        'email_provider',
        'email_reengagement_days',
        'email_reengagement_enabled',
        'email_subject',
        'email_weekly_summary',
        'empty_chat_list_message',
        'enabled_quizzes',
        'engagement_rule_audience',
        'engagement_rule_chat_message',
        'engagement_rule_condition',
        'engagement_rule_days',
        'engagement_rule_email_template',
        'engagement_rule_id',
        'engagement_rule_title',
        'engagement_rule_trigger',
        'engagement_rules',
        'exclude_items_from_freeline',
        'fallback_phrase',
        'favicon_url',
        'feedback_admin_note',
        'feedback_bad_response',
        'feedback_preferred_response',
        'feedback_user_message',
        'file_access_level',
        'file_content',
        'flosc_account_plan',
        'flosc_account_purchases_manual',
        'flosc_add_feedback',
        'flosc_add_praise',
        'flosc_clear_ivr_db',
        'flosc_cncrg_content',
        'flosc_cncrg_delivery',
        'flosc_cncrg_flow',
        'flosc_cncrg_keyword',
        'flosc_cncrg_max_tries',
        'flosc_cncrg_off_ramp_exactness',
        'flosc_cncrg_off_ramp_phrases',
        'flosc_cncrg_retry',
        'flosc_cncrg_success',
        'flosc_cncrg_title',
        'flosc_confirm_import',
        'flosc_create_concierge_post',
        'flosc_create_trajectory_post',
        'flosc_da1_assign_catalogs',
        'flosc_da1_create_catalog',
        'flosc_da1_upload_catalog',
        'flosc_da1_upload_file',
        'flosc_debug_mode',
        'flosc_delete_feedback',
        'flosc_delete_ivr_file',
        'flosc_delete_praise',
        'flosc_duplicate_ivr_file',
        'flosc_export_ivr',
        'flosc_flow_catalogs',
        'flosc_flow_editors',
        'flosc_flow_key',
        'flosc_force_resync',
        'flosc_import_mode',
        'flosc_import_selected_ivr_file',
        'flosc_ivr',
        'flosc_login_destination',
        'flosc_new_catalog_key',
        'flosc_new_catalog_label',
        'flosc_offer_token_amount',
        'flosc_offer_token_cap',
        'flosc_offer_token_cap_mode',
        'flosc_offer_token_mode',
        'flosc_offer_token_source',
        'flosc_preview_import',
        'flosc_reset_chat_style',
        'flosc_return_ivr',
        'flosc_save',
        'flosc_save_all_flows',
        'flosc_save_flow',
        'flosc_save_full_ivr',
        'flosc_toggle_trajectory_post',
        'flosc_trajectory_set',
        'flosc_trj_flow',
        'flosc_trj_instructions',
        'flosc_trj_keywords',
        'flosc_trj_off_ramp_exactness',
        'flosc_trj_off_ramp_phrases',
        'flosc_trj_priority',
        'flosc_trj_title',
        'flosc_update_flosc_editors',
        'flosc_upload_ivr_file',
        'flosc_upload_redirect_tab',
        'flosc_upload_redirect_view',
        'fmt_card_enabled',
        'fmt_featured_enabled',
        'fmt_pill_enabled',
        'fmt_pill_icon',
        'fmt_pill_label',
        'fmt_pill_phrase',
        'fmt_pill_target_panel',
        'free_content_item_count',
        'free_content_item_guaranteed',
        'free_content_item_mode',
        'free_content_item_pool_category',
        'free_content_item_proportion',
        'guest_access_days',
        'guest_can_delete_chats',
        'guest_can_rename_chats',
        'guest_link_check_email_message',
        'guest_link_email_subject',
        'guest_link_expired_offer_url',
        'guest_link_max_uses',
        'guest_link_name',
        'guest_link_profile_confirm_message',
        'guest_link_upgrade_url',
        'guest_link_welcome_message',
        'guest_link_window_days',
        'guest_max_chats',
        'guest_menu_action',
        'guest_menu_label',
        'guest_new_chat_limit_message',
        'guest_new_chat_welcome_message',
        'guest_token_grant',
        'header_login_action',
        'header_login_text',
        'header_signup_action',
        'header_signup_text',
        'id',
        'identity',
        'import_ivr_file',
        'ivr_file_upload',
        'ivr_full_text',
        'level_description',
        'level_name',
        'level_slug',
        'magic_access_links_enabled',
        'magic_link_admin_send_condition',
        'manual_payment_instructions',
        'manual_payments_enabled',
        'member_levels',
        'member_link_welcome_message',
        'member_menu_action',
        'member_menu_label',
        'member_new_chat_welcome_message',
        'member_token_grant',
        'message_action',
        'message_conditions',
        'message_content',
        'message_discount_price',
        'message_display_format',
        'message_html_file',
        'message_icon',
        'message_id',
        'message_keywords',
        'message_name',
        'message_offer_id',
        'message_password_max_tries',
        'message_password_prompt',
        'message_password_retry',
        'message_password_success',
        'message_phase',
        'message_post_id',
        'message_price',
        'message_style',
        'message_timer',
        'message_type',
        'message_user_input',
        'message_woo_product',
        'name',
        'new_chat_button_label',
        'newsletter_optin_text',
        'offer_access_codes',
        'offer_active',
        'offer_after_offer_id',
        'offer_badge',
        'offer_condition',
        'offer_coupon_code',
        'offer_coupon_type',
        'offer_coupon_valid_from',
        'offer_coupon_valid_until',
        'offer_coupon_value',
        'offer_cta',
        'offer_currency',
        'offer_description',
        'offer_display_price',
        'offer_external_sale_note',
        'offer_features',
        'offer_frequency_max_shows',
        'offer_frequency_scope',
        'offer_grants_level',
        'offer_guarantee',
        'offer_headline',
        'offer_icon',
        'offer_id',
        'offer_match_type',
        'offer_name',
        'offer_original_price',
        'offer_presentation_priority',
        'offer_price',
        'offer_processor',
        'offer_redirect_url',
        'offer_reveal_delay_seconds',
        'offer_reveal_event',
        'offer_reveal_phrase',
        'offer_savings',
        'offer_show_coupon_field',
        'offer_stripe_price_id',
        'offer_stripe_product_id',
        'offer_timer',
        'offer_trigger',
        'offer_type',
        'offers',
        'orientation_file',
        'panel_enabled_companion',
        'paypal_client_id',
        'paypal_enabled',
        'paypal_mode',
        'paypal_webhook_id',
        'phase_outcomes_content',
        'phase_outcomes_freeline',
        'phase_outcomes_login',
        'phase_outcomes_offer',
        'phase_outcomes_sale',
        'platform_compliance_content',
        'praise_admin_note',
        'praise_good_response',
        'praise_user_message',
        'primary_color',
        'privacy_policy_content',
        'product_token_cap',
        'product_token_grant_onetime',
        'product_token_grant_recurring',
        'product_token_grant_recurring_yearly',
        'profile_bar_guest_avatar_radius',
        'profile_bar_guest_badge',
        'profile_bar_guest_icon',
        'profile_bar_guest_icon_url',
        'profile_bar_guest_name',
        'profile_bar_guest_show_upgrade',
        'profile_bar_guest_upgrade',
        'profile_bar_member_avatar_radius',
        'profile_bar_member_badge',
        'profile_bar_member_icon',
        'profile_bar_member_icon_url',
        'profile_bar_member_name',
        'profile_bar_member_show_upgrade',
        'profile_bar_member_upgrade_label',
        'profile_bar_visitor_avatar_radius',
        'profile_bar_visitor_badge',
        'profile_bar_visitor_icon',
        'profile_bar_visitor_icon_url',
        'profile_bar_visitor_name',
        'profile_bar_visitor_show_upgrade',
        'profile_bar_visitor_upgrade_label',
        'protected_content',
        'protection_level',
        'protection_type',
        'protection_value',
        'save_ivr_message',
        'save_offer',
        'sessions',
        'slug',
        'sso_post_login_redirect_url',
        'status',
        'stripe_enabled',
        'stripe_live_pk',
        'stripe_mode',
        'stripe_test_pk',
        'stt_provider',
        'subscription_monthly_token_grant',
        'subscription_token_cap',
        'subscription_yearly_token_grant',
        'support_email',
        'tagline',
        'terms_of_service_content',
        'title',
        'tokens_communication_tokens_per_message',
        'tokens_nominal_millicents_per_token_decimal',
        'tokens_nominal_millicents_per_token_denominator',
        'tokens_nominal_millicents_per_token_numerator',
        'tokens_real_millicents_per_token_decimal',
        'tokens_real_millicents_per_token_denominator',
        'tokens_real_millicents_per_token_numerator',
        'user_status_admin',
        'user_status_guest',
        'user_status_member',
        'user_status_member_level',
        'user_status_visitor',
        'view',
        'visitor_depleted_contact_mode',
        'visitor_low_token_threshold',
        'visitor_low_tokens_message',
        'visitor_menu_action',
        'visitor_menu_label',
        'visitor_session_end_redirect_url',
        'visitor_tokens_depleted_message',
    );
}
/**
 * Apply parsed YAML settings to a flow settings array using top-level allow-list and secret deny-list.
 *
 * @param array $current_fs
 * @param array $incoming_settings
 * @return array{applied:array,skipped:array,fs:array}
 */
function flosc_portable_apply_yaml_settings(array $current_fs, array $incoming_settings) {
    $allowed = array_fill_keys(array_map('strval', array_keys($current_fs)), true);
    foreach (flosc_portable_settings_bootstrap_allowlist() as $k) {
        $allowed[(string) $k] = true;
    }

    $applied = array();
    $skipped = array();

    foreach ($incoming_settings as $key => $value) {
        $key = (string) $key;
        if ($key === '') {
            continue;
        }

        if (!isset($allowed[$key])) {
            $skipped[$key] = 'not_allowlisted';
            continue;
        }
        if (in_array($key, flosc_portable_settings_runtime_excludes(), true)) {
            $skipped[$key] = 'runtime_only';
            continue;
        }
        if (flosc_portable_is_secret_path(array($key))) {
            $skipped[$key] = 'secret';
            continue;
        }

        $filtered = flosc_portable_filter_secret_values($value, array($key));
        if ($filtered === null && $value !== null) {
            $skipped[$key] = 'secret';
            continue;
        }

        if (is_array($filtered) && isset($current_fs[$key]) && is_array($current_fs[$key])) {
            $current_fs[$key] = flosc_portable_deep_merge($current_fs[$key], $filtered);
        } else {
            $current_fs[$key] = $filtered;
        }
        $applied[] = $key;
    }

    return array(
        'applied' => array_values(array_unique($applied)),
        'skipped' => $skipped,
        'fs' => $current_fs,
    );
}

/**
 * Load messages/phases/styles for import/export.
 * Prefer flosc_flow_* option. Fall back once to legacy global options if empty.
 *
 * @param string|null $flow_key
 * @return array{0:array,1:array,2:array,3:string} messages, phases, styles, flow_key used
 */
function flosc_flow_load_runtime_triplet($flow_key = null) {
    $flow_key = $flow_key ? (string) $flow_key : flosc_default_flow_option_key();
    $fs = function_exists('flosc_flow_get_option_array')
        ? flosc_flow_get_option_array($flow_key, true)
        : get_option($flow_key, array());
    if (!is_array($fs)) {
        $fs = array();
    }
    $messages = flosc_flow_get_messages($fs);
    $phases   = flosc_flow_get_phases($fs);
    $styles   = flosc_flow_get_styles($fs);

    // One-time bridge: old global options (pre multi-flow).
    if (empty($messages)) {
        $legacy_m = get_option('flosc_ivr_messages', array());
        $legacy_p = get_option('flosc_ivr_phases', array());
        $legacy_s = get_option('flosc_ivr_styles', array());
        if (!empty($legacy_m) && is_array($legacy_m)) {
            $messages = $legacy_m;
            $phases   = is_array($legacy_p) ? $legacy_p : array();
            $styles   = is_array($legacy_s) ? $legacy_s : array();
            flosc_flow_set_runtime($fs, $messages, $phases, $styles);
            update_option($flow_key, $fs, false);
            // Stop using global keys for new writes; leave old rows for now (optional delete).
        }
    }

    return array($messages, $phases, $styles, $flow_key);
}

/**
 * Import IVR from ivr.md to database (REPLACE MODE - ivr.md is source of truth)
 * v9.2.2: IVR Database Integration
 * v1.6.4: Added $custom_ivr_file and $flow_key params for per-flow storage
 *
 * @param bool $preview_only If true, returns preview without making changes
 * @param string|null $custom_ivr_file Optional path to IVR file (defaults to flosc_default_technical_ivr.md)
 * @param string|null $flow_key Optional per-flow option key (e.g. 'flosc_flow_flosc_default_ivr')
 * @return array Result with success, stats, message, and preview data
 */
function flosc_import_ivr_to_database($preview_only = false, $custom_ivr_file = null, $flow_key = null, $mode = 'merge') {
    $ivr_file = $custom_ivr_file ?? flosc_config_file('flosc_default_technical_ivr.md');
    $mode = ($mode === 'replace') ? 'replace' : 'merge';

    if (!file_exists($ivr_file)) {
        return ['success' => false, 'message' => 'flosc_default_technical_ivr.md file not found'];
    }

    // Only read markdown from FLOSC data dir or shipped plugin IVR folder.
    if (function_exists('flosc_is_allowed_ivr_source_path') && !flosc_is_allowed_ivr_source_path($ivr_file)) {
        return ['success' => false, 'message' => 'IVR file path is not an allowed FLOSC configuration location'];
    }

    // flow_key must be a per-flow option name, never an arbitrary options row.
    if ($flow_key !== null && $flow_key !== '') {
        $flow_key = (string) $flow_key;
        if (strpos($flow_key, 'flosc_flow_') !== 0 || $flow_key === 'flosc_flow_') {
            return ['success' => false, 'message' => 'Invalid flow option key'];
        }
    }

    require_once FLOSC_PLUGIN_DIR . 'includes/portability/class-ivr-parser.php';
    $parser = FLOSC_IVR_Parser::flosc_instance();
    $markdown = flosc_fs_get_contents($ivr_file);

    $settings_yaml = flosc_portable_extract_settings_yaml($markdown);
    $settings_parse = flosc_portable_parse_yaml_map($settings_yaml);
    $incoming_settings = array();
    if (!$settings_parse['success']) {
        return [
            'success' => false,
            'message' => 'Failed to parse Settings YAML block: ' . (string) ($settings_parse['error'] ?? 'unknown parse error'),
        ];
    }
    if (!empty($settings_parse['data']) && is_array($settings_parse['data'])) {
        $incoming_settings = flosc_portable_from_yaml_shape($settings_parse['data']);
        if (!is_array($incoming_settings)) {
            $incoming_settings = array();
        }
    }

    $markdown_for_parser = flosc_portable_strip_settings_block($markdown);
    $config = $parser->flosc_parse($markdown_for_parser);

    if (empty($config)) {
        return ['success' => false, 'message' => 'Failed to parse flosc_default_technical_ivr.md'];
    }

    // Runtime state: always per-flow option flosc_flow_{stem} (flow_messages / flow_phases / flow_styles).
    if (!$flow_key) {
        $flow_key = flosc_default_flow_option_key();
    }
    list($current_messages, $_phases_unused, $_styles_unused, $flow_key) = flosc_flow_load_runtime_triplet($flow_key);
    $current_fs = get_option($flow_key, array());
    if (!is_array($current_fs)) {
        $current_fs = array();
    }
    $settings_apply = flosc_portable_apply_yaml_settings($current_fs, $incoming_settings);
    if (function_exists('flosc_normalize_content_item_flow_settings') && !empty($settings_apply['fs']) && is_array($settings_apply['fs'])) {
        $settings_apply['fs'] = flosc_normalize_content_item_flow_settings($settings_apply['fs']);
    }

    // Normalize DB defaults so compare logic matches runtime/export behavior.
    foreach ($current_messages as &$current_msg) {
        $msg_type = strtolower(trim((string)($current_msg['type'] ?? '')));
        if ($msg_type === 'offer' && empty($current_msg['display_format'])) {
            $current_msg['display_format'] = 'card';
        }
    }
    unset($current_msg);

    $incoming_messages = $config['messages'] ?? [];

    // Normalize defaults so preview/compare and runtime storage use the same shape.
    foreach ($incoming_messages as &$incoming_msg) {
        $msg_type = strtolower(trim((string)($incoming_msg['type'] ?? '')));
        if ($msg_type === 'offer' && empty($incoming_msg['display_format'])) {
            $incoming_msg['display_format'] = 'card';
        }
    }
    unset($incoming_msg);

    // Calculate changes
    $current_ids = array_keys($current_messages);
    $incoming_ids = array_keys($incoming_messages);

    $to_add = array_diff($incoming_ids, $current_ids);
    $to_update = array_intersect($incoming_ids, $current_ids);
    $to_delete = array_diff($current_ids, $incoming_ids);

    $stats = [
        'added' => array_values($to_add),
        'updated' => array_values($to_update),
        'deleted' => array_values($to_delete),
        'current_count' => count($current_messages),
        'incoming_count' => count($incoming_messages),
        'has_deletions' => !empty($to_delete),
        'mode' => $mode,
        'settings_detected' => !empty($incoming_settings),
        'settings_applied' => $settings_apply['applied'],
        'settings_skipped' => $settings_apply['skipped'],
    ];

    // PREVIEW MODE: Return analysis without making changes
    if ($preview_only) {
        // v2.0.0: Build field-level diffs for updated messages
        $field_diffs = [];
        $compare_fields = ['title', 'name', 'type', 'style', 'panel', 'icon',
            'user_input', 'keywords', 'action', 'conditions', 'phase',
            'offer_id', 'price', 'discount_price', 'timer', 'display_format', 'content'];

        // Normalize messages to a compare shape so sparse DB rows and parser-defaulted
        // file rows can be compared semantically instead of by raw array structure.
        $normalize_for_compare = static function ($msg) {
            if (!is_array($msg)) {
                $msg = [];
            }

            $normalized = [
                'name'           => (string) ($msg['name'] ?? ''),
                'type'           => (string) ($msg['type'] ?? 'auto'),
                'style'          => (string) ($msg['style'] ?? 'pill'),
                'panel'          => (string) ($msg['panel'] ?? ''),
                'icon'           => (string) ($msg['icon'] ?? ''),
                'user_input'     => (string) ($msg['user_input'] ?? ''),
                'keywords'       => (string) ($msg['keywords'] ?? ''),
                'action'         => (string) ($msg['action'] ?? ''),
                'conditions'     => (string) ($msg['conditions'] ?? 'always'),
                'phase'          => (string) ($msg['phase'] ?? 'freeline'),
                'offer_id'       => (string) ($msg['offer_id'] ?? ''),
                'price'          => (string) ($msg['price'] ?? ''),
                'discount_price' => (string) ($msg['discount_price'] ?? ''),
                'timer'          => (string) (isset($msg['timer']) ? intval($msg['timer']) : ''),
                'display_format' => (string) ($msg['display_format'] ?? ''),
                'content'        => (string) ($msg['content'] ?? ''),
            ];

            $normalized['title'] = (string) ($msg['title'] ?? $normalized['name']);

            if (strtolower(trim($normalized['type'])) === 'offer' && $normalized['display_format'] === '') {
                $normalized['display_format'] = 'card';
            }

            // Avoid CRLF/LF-only differences being reported as content drift.
            $normalized['content'] = trim((string) preg_replace('/\r\n?|\n/', "\n", $normalized['content']));

            return $normalized;
        };

        foreach ($to_update as $msg_id) {
            $db_msg = $normalize_for_compare($current_messages[$msg_id] ?? []);
            $file_msg = $normalize_for_compare($incoming_messages[$msg_id] ?? []);
            $diffs = [];
            foreach ($compare_fields as $field) {
                $db_val = (string) ($db_msg[$field] ?? '');
                $file_val = (string) ($file_msg[$field] ?? '');
                if ($db_val !== $file_val) {
                    $diffs[$field] = ['db' => $db_val, 'file' => $file_val];
                }
            }
            if (!empty($diffs)) {
                $field_diffs[$msg_id] = $diffs;
            }
        }
        $stats['field_diffs'] = $field_diffs;
        return ['success' => true, 'preview' => true, 'stats' => $stats];
    }

    // EXECUTE IMPORT: Auto-backup first, then merge or replace database
    $backup_file = '';
    if (!empty($current_messages)) {
        $backup_file = flosc_export_ivr_backup($flow_key);
    }

    // Extract autoprompt pills from IVR messages and organize by state
    $autoprompts_from_ivr = ['visitor' => [], 'guest' => [], 'member' => []];
    foreach ($incoming_messages as $msg) {
        if (($msg['type'] ?? '') !== 'suggested_user_autoprompt') {
            continue;
        }
        $cond = $msg['conditions'] ?? $msg['condition'] ?? '';
        foreach (['visitor', 'guest', 'member'] as $s) {
            if ($cond === 'always' || strpos($cond, 'is_' . $s) !== false) {
                $autoprompts_from_ivr[$s][] = [
                    'icon'          => $msg['icon'] ?? '',
                    'label'         => $msg['label'] ?? ($msg['name'] ?? ''),
                    'user_input'    => $msg['user_input'] ?? ($msg['label'] ?? ''),
                    'trigger_type'  => $msg['trigger_type'] ?? 'ai',
                    'trigger_value' => $msg['trigger_value'] ?? '',
                    'action'        => $msg['action'] ?? '',
                    'conditions'    => $cond,
                    'style'         => $msg['style'] ?? ($msg['message_style'] ?? 'pill'),
                ];
            }
        }
    }

    $final_messages = $incoming_messages;
    $final_phases = $config['phases'] ?? [];

    if ($mode === 'merge') {
        $final_messages = $current_messages;
        foreach ($incoming_messages as $msg_id => $incoming_msg) {
            $final_messages[$msg_id] = $incoming_msg;
        }

        $final_phases = [];
        foreach (($config['phases'] ?? []) as $phase_name => $message_ids) {
            $final_phases[$phase_name] = array_values(array_unique($message_ids));
        }

        foreach ($current_messages as $msg_id => $current_msg) {
            if (isset($incoming_messages[$msg_id])) {
                continue;
            }
            $phase_name = $current_msg['phase'] ?? '';
            if ($phase_name === '') {
                continue;
            }
            if (!isset($final_phases[$phase_name])) {
                $final_phases[$phase_name] = [];
            }
            if (!in_array($msg_id, $final_phases[$phase_name], true)) {
                $final_phases[$phase_name][] = $msg_id;
            }
        }
    }

    // Always write runtime lists into the per-flow option (flow_* keys only).
    if (!$flow_key) {
        $flow_key = flosc_default_flow_option_key();
    }
    $fs = $settings_apply['fs'];
    flosc_flow_set_runtime($fs, $final_messages, $final_phases, $config['styles'] ?? array());
    $fs['flow_last_import'] = current_time('mysql');
    // Keep historical key for admin diagnostics that still read ivr_last_import.
    $fs['ivr_last_import'] = $fs['flow_last_import'];
    $fs['autoprompts'] = $autoprompts_from_ivr;
    update_option($flow_key, $fs);

    flosc_sync_flow_offers_with_ivr_messages($flow_key, $final_messages);

    // Settings YAML commercial offer definitions win over IVR seed stubs (price, codes, grants).
    if (!empty($incoming_settings['offers']) && is_array($incoming_settings['offers'])) {
        $fs_after = get_option($flow_key, array());
        if (!is_array($fs_after)) {
            $fs_after = array();
        }
        $current_offers = (isset($fs_after['offers']) && is_array($fs_after['offers'])) ? $fs_after['offers'] : array();
        $yaml_offers    = $incoming_settings['offers'];
        foreach ($yaml_offers as $offer_key => $yaml_offer) {
            if (!is_array($yaml_offer)) {
                continue;
            }
            $oid = sanitize_key((string) ($yaml_offer['id'] ?? $offer_key));
            if ($oid === '') {
                continue;
            }
            $yaml_offer['id'] = $oid;
            $base = (isset($current_offers[$oid]) && is_array($current_offers[$oid])) ? $current_offers[$oid] : array();
            $current_offers[$oid] = flosc_portable_deep_merge($base, $yaml_offer);
        }
        $fs_after['offers'] = $current_offers;
        update_option($flow_key, $fs_after);
    }

    // Generate success message
    if ($mode === 'replace') {
        $message = sprintf(
            'Database replaced from IVR file. Added: %d, Updated: %d, Deleted: %d',
            count($stats['added']),
            count($stats['updated']),
            count($stats['deleted'])
        );
    } else {
        $message = sprintf(
            'Database merged from IVR file. Added: %d, Updated: %d, Kept DB-only: %d',
            count($stats['added']),
            count($stats['updated']),
            count($stats['deleted'])
        );
    }

    if ($backup_file) {
        $message .= sprintf('. Backup saved: %s', basename($backup_file));
    }

    return ['success' => true, 'stats' => $stats, 'message' => $message, 'backup_file' => $backup_file, 'mode' => $mode];
}

/**
 * Create timestamped backup of current IVR database state
 * v1.6.4: Added $flow_key param for per-flow storage
 *
 * @param string|null $flow_key Optional per-flow option key
 * @return string|false Backup filename on success, false on failure
 */
function flosc_export_ivr_backup($flow_key = null) {
    list($messages, $phases, $styles, $flow_key) = flosc_flow_load_runtime_triplet($flow_key);

    if (empty($messages)) {
        return false; // No data to backup
    }

    // Generate markdown (same format as export)
    $markdown = "# FLOSC IVR Configuration (AUTO-BACKUP)\n\n";
    $markdown .= "Backup created: " . current_time('mysql') . "\n\n";

    $flow_settings = get_option($flow_key, array());
    if (!is_array($flow_settings)) {
        $flow_settings = array();
    }
    $portable_settings = flosc_portable_collect_exportable_settings($flow_settings);
    $markdown .= flosc_portable_build_settings_block($portable_settings);

    // Add styles
    foreach ($styles as $style_name => $style_css) {
        $markdown .= "## MessageStyle: $style_name\n";
        $markdown .= $style_css . "\n\n";
    }

    $markdown .= "## Available Variables\n";
    $markdown .= "{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}\n\n";

    $markdown .= "---\n\n";

    // Add messages by phase
    foreach ($phases as $phase_name => $message_ids) {
        $markdown .= "# " . ucfirst($phase_name) . " Messages\n\n";

        foreach ($message_ids as $msg_id) {
            if (!isset($messages[$msg_id])) {
                continue;
            }
            $msg = $messages[$msg_id];

            $markdown .= "## " . ($msg['name'] ?? $msg_id) . "\n";
            $markdown .= "MessageName: " . $msg_id . "\n";
            $markdown .= "MessageType: " . ($msg['type'] ?? 'auto') . "\n";

            if (!empty($msg['style'])) {
                $markdown .= "MessageStyle: " . $msg['style'] . "\n";
            }
            if (!empty($msg['icon'])) {
                $markdown .= "Icon: " . $msg['icon'] . "\n";
            }
            if (!empty($msg['user_input'])) {
                $markdown .= "UserInput: " . $msg['user_input'] . "\n";
            }

            $markdown .= "MessageContent: " . $msg['content'] . "\n";

            if (!empty($msg['conditions'])) {
                $markdown .= "MessageConditions: " . $msg['conditions'] . "\n";
            }
            if (!empty($msg['action'])) {
                $markdown .= "MessageAction: " . $msg['action'] . "\n";
            }

            $markdown .= "\n";
        }
    }

    // Save to timestamped backup file inside the uploads data directory only.
    $backup_dir = flosc_data_dir();
    if ('' === $backup_dir) {
        return false;
    }
    $timestamp = current_time('Y-m-d_H-i-s');
    $backup_file = $backup_dir . "ivr-backup-{$timestamp}.md";

    if (flosc_write_data_file($backup_file, $markdown)) {
        return basename($backup_file);
    }

    return false;
}

/**
 * Auto-export IVR database to ivr.md file (write-through)
 * v9.2.8: Called after every save/delete to keep DB and file in sync
 *
 * @return bool Success
 */
function flosc_auto_export_ivr_to_file($flow_key = null, $target_ivr_file = null) {
    list($messages, $phases, $styles, $flow_key) = flosc_flow_load_runtime_triplet($flow_key);

    if (empty($messages)) {
        return false;
    }

    // Ensure export includes all option messages even if flow_phases is stale or incomplete.
    // Without this normalization, DB-only messages (often offers) can be dropped from IVR file output.
    $normalized_phases = [
        'freeline' => [],
        'login' => [],
        'offer' => [],
        'sale' => [],
        'content' => [],
    ];

    if (is_array($phases)) {
        foreach ($phases as $phase_name => $message_ids) {
            if (!isset($normalized_phases[$phase_name])) {
                $normalized_phases[$phase_name] = [];
            }
            if (is_array($message_ids)) {
                foreach ($message_ids as $msg_id) {
                    if (isset($messages[$msg_id])) {
                        $normalized_phases[$phase_name][] = $msg_id;
                    }
                }
            }
        }
    }

    foreach ($messages as $msg_id => $msg) {
        $msg_phase = sanitize_key((string)($msg['phase'] ?? ''));
        if ($msg_phase === '' || !isset($normalized_phases[$msg_phase])) {
            $msg_phase = 'freeline';
        }
        if (!in_array($msg_id, $normalized_phases[$msg_phase], true)) {
            $normalized_phases[$msg_phase][] = $msg_id;
        }
    }

    $phases = $normalized_phases;

    $flow_settings = get_option($flow_key, array());
    if (!is_array($flow_settings)) {
        $flow_settings = array();
    }
    $portable_settings = flosc_portable_collect_exportable_settings($flow_settings);

    // Build markdown in proper ivr.md format
    $markdown = "# FLOSC IVR Configuration\n\n";
    $markdown .= flosc_portable_build_settings_block($portable_settings);

    // Add styles
    foreach ($styles as $style_name => $style_data) {
        $markdown .= "## MessageStyle: $style_name\n";
        if (is_array($style_data)) {
            if (!empty($style_data['description'])) {
                $markdown .= "Description: " . $style_data['description'] . "\n";
            }
            if (!empty($style_data['css'])) {
                $markdown .= $style_data['css'] . "\n";
            }
        } else {
            $markdown .= $style_data . "\n";
        }
        $markdown .= "\n";
    }

    $markdown .= "## Available Variables\n";
    $markdown .= "{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}\n\n";

    $markdown .= "## Available Conditions\n";
    $markdown .= "- Scores: score > X, score < X, score >= X, score <= X, score == X, initial_score > X\n";
    $markdown .= "- Boolean: quiz_taken, !quiz_taken, logged_in, !logged_in, purchased, !purchased, lesson_viewed, returning_user, onboarded, has_incomplete_lesson, has_profile, !has_profile\n";
    $markdown .= "- Access: is_visitor, is_guest, is_member\n";
    $markdown .= "- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_content_item, first_show_session\n";
    $markdown .= "- Logic: &&, ||, !, ()\n\n";

    $markdown .= "---\n\n";

    // Phase descriptions
    $phase_descriptions = [
        'freeline' => 'Visitor (not logged in) → Take quiz → MUST login to see score.',
        'login' => 'Guest (logged in, not purchased) → See score → 1 free lesson → Offers → No quiz retake.',
        'offer' => 'Guests (completed quiz, not purchased) → See quiz results → Free preview lesson → Upgrade offer',
        'sale' => 'Member (purchased) → Full access → Retake quiz with timestamps → All lessons/quizzes.',
        'content' => 'Ongoing users - Support, encouragement, engagement',
    ];

    // Add messages by phase
    foreach ($phases as $phase_name => $message_ids) {
        if (empty($message_ids)) {
            continue;
        }

        $markdown .= "# " . ucfirst($phase_name) . " Messages\n";
        if (isset($phase_descriptions[$phase_name])) {
            $markdown .= $phase_descriptions[$phase_name] . "\n";
        }
        $markdown .= "\n";

        foreach ($message_ids as $msg_id) {
            if (!isset($messages[$msg_id])) {
                continue;
            }
            $msg = $messages[$msg_id];

            // Use title/display name if available
            $title = $msg['title'] ?? $msg['name'] ?? $msg_id;
            $markdown .= "## " . $title . "\n";
            $markdown .= "MessageName: " . $msg_id . "\n";
            $markdown .= "MessageType: " . ($msg['type'] ?? 'auto') . "\n";

            if (!empty($msg['style']) && $msg['style'] !== 'default') {
                $markdown .= "MessageStyle: " . $msg['style'] . "\n";
            }
            if (!empty($msg['panel'])) {
                $markdown .= "MessagePanel: " . $msg['panel'] . "\n";
            }
            if (!empty($msg['icon'])) {
                $markdown .= "Icon: " . $msg['icon'] . "\n";
            }
            if (!empty($msg['user_input'])) {
                $markdown .= "UserInput: " . $msg['user_input'] . "\n";
            }
            if (!empty($msg['keywords'])) {
                $markdown .= "Keywords: " . $msg['keywords'] . "\n";
            }
            if (!empty($msg['action'])) {
                $markdown .= "Action: " . $msg['action'] . "\n";
            }
            // v8.0.0: Concierge fields — keyword-triggered message with optional password gate.
            // PasswordRetry repeats once per try, mirroring the per-message retry list.
            if (!empty($msg['individual_message_password'])) {
                $markdown .= "IndividualMessagePassword: " . $msg['individual_message_password'] . "\n";
            }
            if (!empty($msg['password_prompt'])) {
                $markdown .= "PasswordPrompt: " . $msg['password_prompt'] . "\n";
            }
            if (!empty($msg['password_success'])) {
                $markdown .= "PasswordSuccess: " . $msg['password_success'] . "\n";
            }
            if (!empty($msg['password_max_tries'])) {
                $markdown .= "PasswordMaxTries: " . intval($msg['password_max_tries']) . "\n";
            }
            if (!empty($msg['password_retry_messages']) && is_array($msg['password_retry_messages'])) {
                foreach ($msg['password_retry_messages'] as $retry_line) {
                    $retry_line = trim((string) $retry_line);
                    if ($retry_line !== '') {
                        $markdown .= "PasswordRetry: " . $retry_line . "\n";
                    }
                }
            }
            // v1.6.2: Offer-specific fields — offers are IVR entries
            if (!empty($msg['offer_id'])) {
                $markdown .= "OfferID: " . $msg['offer_id'] . "\n";
            }
            if (!empty($msg['price'])) {
                $markdown .= "Price: " . $msg['price'] . "\n";
            }
            if (!empty($msg['discount_price'])) {
                $markdown .= "DiscountPrice: " . $msg['discount_price'] . "\n";
            }
            if (!empty($msg['timer'])) {
                $markdown .= "Timer: " . $msg['timer'] . "\n";
            }
            $display_format = trim((string)($msg['display_format'] ?? ''));
            if (strtolower(trim((string)($msg['type'] ?? ''))) === 'offer' && $display_format === '') {
                $display_format = 'card';
            }
            if ($display_format !== '') {
                $markdown .= "DisplayFormat: " . $display_format . "\n";
            }
            // v1.6.2: Offer content source fields
            if (!empty($msg['html_file'])) {
                $markdown .= "HtmlFile: " . $msg['html_file'] . "\n";
            }
            if (!empty($msg['woo_product'])) {
                $markdown .= "WooProduct: " . $msg['woo_product'] . "\n";
            }
            if (!empty($msg['post_id'])) {
                $markdown .= "PostID: " . $msg['post_id'] . "\n";
            }

            $markdown .= "MessageContent: " . ($msg['content'] ?? '') . "\n";

            if (!empty($msg['conditions']) && $msg['conditions'] !== 'always') {
                $markdown .= "MessageConditions: " . $msg['conditions'] . "\n";
            }

            $markdown .= "\n";
        }

        $markdown .= "---\n\n";
    }

    // Resolve destination IVR file — always inside the uploads data directory.
    // Per-flow save must write to that flow's active IVR file, not the global default.
    $data_dir = flosc_data_dir();
    if ('' === $data_dir) {
        return false; // Uploads unavailable — the DB copy stays authoritative until storage returns.
    }
    if ($target_ivr_file) {
        $ivr_file = $target_ivr_file;
    } elseif (!empty($flow_key)) {
        $fs = get_option($flow_key, []);
        $preferred_file = $fs['ivr_file'] ?? ($fs['active_ivr_file'] ?? 'flosc_default_technical_ivr.md');
        $ivr_file = $data_dir . basename($preferred_file);
    } else {
        $ivr_file = $data_dir . 'flosc_default_technical_ivr.md';
    }

    if (strpos($ivr_file, $data_dir) !== 0) {
        $ivr_file = $data_dir . basename($ivr_file);
    }
    $result = flosc_write_data_file($ivr_file, $markdown);

    if ($result) {
        // Update last export timestamp
        update_option('flosc_ivr_last_export', current_time('mysql'));
        if (!empty($flow_key)) {
            $fs = get_option($flow_key, []);
            $fs['ivr_last_export'] = current_time('mysql');
            update_option($flow_key, $fs);
        }
        return true;
    }

    return false;
}

/**
 * IVR-DB integrity: keep the portable .md in lockstep with the database, from ANY admin tab.
 *
 * A floscAdmin edits across screens — IVR messages, autoprompts, quiz, offers,
 * identity — and experiences them all as "settings." Each save lands in the
 * per-flow option flosc_flow_<stem>. Rather than ask every tab to remember to
 * re-export, this one hook re-writes that flow's .md whenever its option
 * changes, so the file and the database move together and never drift.
 *
 * The static guard prevents re-entry: flosc_auto_export_ivr_to_file() writes
 * the flow option itself (its export timestamp), which would otherwise recurse.
 *
 * @param string $option Name of the option that was added or updated.
 * @return void
 */
function flosc_sync_flow_option_to_ivr_file($option) {
    static $mirroring = false;
    if ($mirroring) {
        return;
    }
    if (strpos((string) $option, 'flosc_flow_') !== 0) {
        return;
    }
    $stem = substr($option, strlen('flosc_flow_'));
    if ($stem === '') {
        return;
    }
    $mirroring = true;
    flosc_auto_export_ivr_to_file($option, flosc_data_dir() . $stem . '.md');
    $mirroring = false;
}
add_action('updated_option', 'flosc_sync_flow_option_to_ivr_file', 20, 1);
add_action('added_option', 'flosc_sync_flow_option_to_ivr_file', 20, 1);

// v8.0.0: Concierge posts. A private post in the concierge category gets an admin
// "FLOSC Concierge" meta box (editable settings); on save the plugin syncs it into
// the post's flow as a concierge IVR message (which mirrors to the .md); on trash it
// is removed. Admins also see a read-only "what FLOSC understands" summary on the post.
add_action('add_meta_boxes_post', function ($post) {
    if (class_exists('FLOSC_Concierge') && FLOSC_Concierge::is_concierge_post($post)) {
        add_meta_box('flosc_concierge', 'FLOSC Concierge', array('FLOSC_Concierge', 'render_meta_box'), 'post', 'normal', 'high');
    }
    if (class_exists('FLOSC_Trajectory') && FLOSC_Trajectory::is_trajectory_post($post)) {
        add_meta_box('flosc_trajectory', 'FLOSC Trajectory', array('FLOSC_Trajectory', 'render_meta_box'), 'post', 'normal', 'high');
    }
});
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if (class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::save_meta_box($post_id);

        $post_status = is_object($post) ? (string) ($post->post_status ?? '') : '';
        if (in_array($post_status, array('publish', 'private'), true)) {
            FLOSC_Concierge::sync_post($post);
        } else {
            FLOSC_Concierge::unsync_post($post);
        }
    }

    if (class_exists('FLOSC_Trajectory')) {
        FLOSC_Trajectory::save_meta_box($post_id);

        $post_status = is_object($post) ? (string) ($post->post_status ?? '') : '';
        if (in_array($post_status, array('publish', 'private'), true)) {
            FLOSC_Trajectory::sync_post($post);
        } else {
            FLOSC_Trajectory::unsync_post($post);
        }
    }
}, 20, 2);
add_action('trashed_post', function ($post_id) {
    if (class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::unsync_post($post_id);
    }
    if (class_exists('FLOSC_Trajectory')) {
        FLOSC_Trajectory::unsync_post($post_id);
    }
}, 20, 1);
add_action('before_delete_post', function ($post_id) {
    if (class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::unsync_post($post_id);
    }
    if (class_exists('FLOSC_Trajectory')) {
        FLOSC_Trajectory::unsync_post($post_id);
    }
}, 20, 1);
add_filter('the_content', function ($content) {
    return class_exists('FLOSC_Concierge') ? FLOSC_Concierge::maybe_append_confirmation($content) : $content;
}, 20, 1);

/**
 * Align per-flow offers registry with offer messages currently present in IVR messages.
 * Keeps referenced offers and snapshots removed extras for recovery.
 */
function flosc_sync_flow_offers_with_ivr_messages($flow_key, $messages) {
    if (empty($flow_key) || !is_array($messages)) {
        return ['success' => false, 'error' => 'Invalid flow key or messages payload'];
    }

    $fs = get_option($flow_key, []);
    $existing_offers = is_array($fs['offers'] ?? null) ? $fs['offers'] : [];

    $referenced_offers = [];
    foreach ($messages as $msg_id => $msg) {
        $msg_type = strtolower(trim((string)($msg['type'] ?? '')));
        if ($msg_type !== 'offer') {
            continue;
        }

        $offer_id = sanitize_key((string)($msg['offer_id'] ?? $msg_id));
        if ($offer_id === '') {
            continue;
        }

        $seed = [
            'id' => $offer_id,
            'name' => trim((string)($msg['title'] ?? ($msg['name'] ?? $offer_id))),
            'description' => trim((string)($msg['content'] ?? '')),
            'display_format' => trim((string)($msg['display_format'] ?? 'card')),
            'condition' => trim((string)($msg['conditions'] ?? '')),
            'reveal_phrase' => trim((string)($msg['user_input'] ?? '')),
            'status' => 'draft',
            'type' => 'one_time',
            'meta' => [
                'icon' => trim((string)($msg['icon'] ?? '')),
            ],
        ];

        // Optional commercial hints from IVR lines (Price / DiscountPrice).
        $price_raw = trim((string) ($msg['price'] ?? ''));
        if ($price_raw !== '' && is_numeric($price_raw)) {
            $price_val = (float) $price_raw;
            $seed['price'] = $price_val;
            $seed['pricing'] = array(
                'price' => $price_val,
            );
            $seed['display_price'] = '$' . rtrim(rtrim(number_format($price_val, 2, '.', ''), '0'), '.');
        }
        $discount_raw = trim((string) ($msg['discount_price'] ?? ''));
        if ($discount_raw !== '' && is_numeric($discount_raw)) {
            $seed['discount_price'] = (float) $discount_raw;
        }

        $referenced_offers[$offer_id] = $seed;
    }

    $synced_offers = [];
    foreach ($referenced_offers as $offer_id => $seed_offer) {
        $existing_offer = (isset($existing_offers[$offer_id]) && is_array($existing_offers[$offer_id])) ? $existing_offers[$offer_id] : [];
        // Portable Settings / prior admin values keep commercial fields; IVR wins on message copy + display.
        $merged_offer = array_merge($seed_offer, $existing_offer);
        foreach (array('name', 'description', 'display_format', 'condition', 'reveal_phrase') as $msg_field) {
            if (isset($seed_offer[$msg_field]) && (string) $seed_offer[$msg_field] !== '') {
                $merged_offer[$msg_field] = $seed_offer[$msg_field];
            }
        }
        $merged_offer['id'] = $offer_id;

        if (empty($merged_offer['display_format'])) {
            $merged_offer['display_format'] = 'card';
        }
        $existing_meta = (isset($existing_offer['meta']) && is_array($existing_offer['meta'])) ? $existing_offer['meta'] : [];
        $seed_meta = (isset($seed_offer['meta']) && is_array($seed_offer['meta'])) ? $seed_offer['meta'] : array();
        $merged_offer['meta'] = array_merge($existing_meta, $seed_meta);

        // If Settings YAML already activated the offer, do not force draft from the seed default.
        $existing_status = strtolower(trim((string) ($existing_offer['status'] ?? '')));
        if ($existing_status !== '' && $existing_status !== 'draft') {
            $merged_offer['status'] = $existing_offer['status'];
            if (array_key_exists('active', $existing_offer)) {
                $merged_offer['active'] = $existing_offer['active'];
            }
        }

        $synced_offers[$offer_id] = $merged_offer;
    }

    $removed_offer_ids = array_values(array_diff(array_keys($existing_offers), array_keys($synced_offers)));
    if (!empty($removed_offer_ids)) {
        $removed_snapshot = [];
        foreach ($removed_offer_ids as $removed_id) {
            if (isset($existing_offers[$removed_id])) {
                $removed_snapshot[$removed_id] = $existing_offers[$removed_id];
            }
        }
        if (!empty($removed_snapshot)) {
            if (!isset($fs['offers_removed_by_sync']) || !is_array($fs['offers_removed_by_sync'])) {
                $fs['offers_removed_by_sync'] = [];
            }
            $fs['offers_removed_by_sync'][current_time('mysql')] = $removed_snapshot;
            if (count($fs['offers_removed_by_sync']) > 10) {
                $fs['offers_removed_by_sync'] = array_slice($fs['offers_removed_by_sync'], -10, null, true);
            }
        }
    }

    $fs['offers'] = $synced_offers;
    update_option($flow_key, $fs);

    return [
        'success' => true,
        'kept' => count($synced_offers),
        'removed' => count($removed_offer_ids),
        'removed_ids' => $removed_offer_ids,
    ];
}
