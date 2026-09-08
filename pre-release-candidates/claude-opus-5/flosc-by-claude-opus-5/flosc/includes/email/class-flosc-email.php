<?php
/**
 * Outbound FLOSC email.
 *
 * @package FLOSC
 */
if (!defined('ABSPATH')) {
    exit;
}

class FLOSC_Email {

    /** @var FLOSC_Framework */
    private $flosc;

    /** @var int Emails sent in current cron/run (rate limit). */
    private $flosc_email_sent_this_run = 0;

    public function __construct($flosc) {
        $this->flosc = $flosc;
    }

    /**
     * Send quiz score email with OTO
     */
    public function send_score_email($user, $score_data) {
        $context = $this->get_guest_email_context('', (int) $user->ID);
        $flow_settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $flow_id = sanitize_key((string) ($context['flow_id'] ?? ''));

        // Per-flow switch from Email tab: allow score email only when enabled.
        $has_quiz_email_setting = array_key_exists('email_on_quiz_complete', $flow_settings);
        $send_on_quiz_complete = $has_quiz_email_setting
            ? !empty($flow_settings['email_on_quiz_complete'])
            : false;
        if (!$send_on_quiz_complete) {
            return;
        }

        $product_name = trim((string) ($flow_settings['name'] ?? $context['app_name'] ?? ''));
        if ($product_name === '') {
            $product_name = get_option('flosc_product_name', 'FLOSC App');
        }

        $score = $score_data['score'];
        $correct = $score_data['correct'] ?? [];
        $incorrect = $score_data['incorrect'] ?? [];
        
        // Get OTO offer
        $oto_offer_id = sanitize_text_field((string) ($flow_settings['oto_offer_id'] ?? get_option('flosc_oto_offer_id', '')));
        $oto_offer = null;
        $oto_link = $context['chat_url'] ?: home_url('/' . get_option('flosc_app_slug', 'flosc') . '/');
        
        if ($oto_offer_id) {
            $sale = method_exists($this->flosc, 'sale') ? $this->flosc->sale() : null;
            if ($sale && method_exists($sale, 'offers')) {
                $oto_offer = $sale->offers()->get_offer($oto_offer_id, $flow_id ?: null);
            }
        }
        
        // Build email
        $subject = (string) ($flow_settings['email_subject'] ?? get_option('flosc_email_subject', "Your {$product_name} Quiz Results: {$score}%"));
        $subject = str_replace(['{score}', '{product_name}'], [$score, $product_name], $subject);
        
        // Email body
        $body_template = (string) ($flow_settings['email_body'] ?? get_option('flosc_email_body', $this->get_default_email_template()));
        
        $correct_list = !empty($correct) ? implode(', ', $correct) : 'None';
        $incorrect_list = !empty($incorrect) ? implode(', ', $incorrect) : 'None';
        
        $oto_section = '';
        if ($oto_offer) {
            $oto_section = "\n\n🎁 SPECIAL OFFER FOR YOU:\n";
            $oto_section .= "{$oto_offer['name']} - {$oto_offer['display_price']}\n";
            $oto_section .= "{$oto_offer['description']}\n";
            $oto_section .= "Claim your offer: {$oto_link}\n";
        }
        
        $body = str_replace(
            ['{name}', '{score}', '{correct}', '{incorrect}', '{oto_section}', '{app_link}', '{product_name}'],
            [$user->display_name, $score, $correct_list, $incorrect_list, $oto_section, $oto_link, $product_name],
            $body_template
        );
        
        // Send
        $headers = array_merge(
            ['Content-Type: text/plain; charset=UTF-8'],
            $this->get_flosc_mail_headers($flow_id, (int) $user->ID, false)
        );
        wp_mail($user->user_email, $subject, $body, $headers);
        
        // Track
        do_action('flosc_score_email_sent', $user->ID, $score_data);
    }

    /**
     * Helper: resolve guest email identity for a flow.
     */
    public function get_guest_email_context($flow_id = '', $user_id = 0) {
        $flow_id = sanitize_key((string) $flow_id);
        if (empty($flow_id) && $user_id) {
            $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_flow', true));
        }

        $settings = [];
        if (!empty($flow_id)) {
            $settings_key = 'flosc_flow_' . sanitize_key(pathinfo($flow_id, PATHINFO_FILENAME));
            $settings = get_option($settings_key, []);
        } elseif ($user_id) {
            $settings = $this->flosc->get_flow_settings_for_user($user_id);
        }

        $identity = is_array($settings['identity'] ?? null) ? $settings['identity'] : [];
        $app_name = trim((string) ($identity['name'] ?? ($settings['name'] ?? '')));
        if ($app_name === '') {
            $app_name = 'FLOSC';
        }

        $link_name = trim((string) ($settings['guest_link_name'] ?? ''));
        if ($link_name === '') {
            $link_name = 'Guest Access Link';
        }

        $upgrade_url = trim((string) ($settings['guest_link_upgrade_url'] ?? ''));
        if ($upgrade_url !== '') {
            $upgrade_url = esc_url_raw($upgrade_url);
            if (!wp_http_validate_url($upgrade_url)) {
                $upgrade_url = '';
            }
        }

        return [
            'flow_id' => $flow_id,
            'settings' => $settings,
            'app_name' => $app_name,
            'team_name' => $app_name . ' Team',
            'link_name' => $link_name,
            'chat_url' => $this->flosc->get_guest_link_base_url($flow_id),
            'upgrade_url' => $upgrade_url,
        ];
    }

    /**
     * Helper: replace guest email placeholders.
     */
    public function replace_guest_email_placeholders($text, $user, $days_remaining) {
        $context     = $this->get_guest_email_context('', (int) $user->ID);
        $chat_url    = $context['chat_url'];
        $profile_url = function_exists('bp_core_get_user_domain')
            ? bp_core_get_user_domain($user->ID)
            : home_url('/members/' . $user->user_login . '/');
        $upgrade_url = $context['upgrade_url'] ?: home_url();
        return str_replace(
            ['{name}', '{days_remaining}', '{chat_url}', '{profile_url}', '{upgrade_url}', '{app_name}', '{team_name}', '{link_name}'],
            [$user->display_name, $days_remaining, $chat_url, $profile_url, $upgrade_url, $context['app_name'], $context['team_name'], $context['link_name']],
            $text
        );
    }

    /**
     * Resolve flow-aware sender identity for FLOSC emails.
     */
    public function get_flosc_mail_identity($flow_id = '', $user_id = 0) {
        $context = $this->get_guest_email_context($flow_id, (int) $user_id);
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];

        $from_name = trim((string) ($settings['email_from_name'] ?? ($context['app_name'] ?? 'FLOSC')));
        if ($from_name === '') {
            $from_name = 'FLOSC';
        }
        $from_name = trim(str_replace(["\r", "\n"], '', $from_name));

        $from_email = sanitize_email((string) ($settings['email_from_address'] ?? get_option('admin_email', '')));
        if (!is_email($from_email)) {
            $from_email = sanitize_email((string) get_option('admin_email', ''));
        }

        $reply_to = sanitize_email((string) ($settings['support_email'] ?? $from_email));
        if (!is_email($reply_to)) {
            $reply_to = $from_email;
        }

        return [
            'from_name' => $from_name,
            'from_email' => $from_email,
            'reply_to' => $reply_to,
        ];
    }

    /**
     * Build standard FLOSC email headers for consistent sender identity.
     */
    public function get_flosc_mail_headers($flow_id = '', $user_id = 0, $is_html = false) {
        $identity = $this->get_flosc_mail_identity($flow_id, $user_id);
        $headers = [];

        if ($is_html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $headers[] = sprintf('From: %s <%s>', $identity['from_name'], $identity['from_email']);
        $headers[] = 'Reply-To: ' . $identity['reply_to'];

        return $headers;
    }

    /**
     * Build Reply-To header for FLOSC emails.
     */
    public function get_flosc_reply_to_header($flow_id = '', $user_id = 0) {
        $identity = $this->get_flosc_mail_identity($flow_id, $user_id);
        return 'Reply-To: ' . $identity['reply_to'];
    }

    /**
     * SSO welcome email (no MagicLink).
     * Provider login already authenticated the user; MagicLink is admin/opt-in only.
     * Skips non-SSO registration methods (email pending flow, purchase, etc.).
     */
    public function send_sso_welcome_email($user_id, $provider_id, $user_data = []) {
        $user_id = absint($user_id);
        $provider_id = sanitize_key((string) $provider_id);
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return;
        }

        // Only SSO providers. Email registration uses verify → activate → welcome-with-magic.
        $sso_providers = array('google', 'facebook', 'apple', 'microsoft', 'linkedin');
        $is_sso = in_array($provider_id, $sso_providers, true) || strpos($provider_id, 'sso_') === 0;
        if (!$is_sso) {
            return;
        }

        if (!is_array($user_data)) {
            $user_data = array();
        }

        $flow_id = sanitize_key((string) ($user_data['flow_id'] ?? get_user_meta($user_id, '_flosc_registration_flow', true)));
        $is_new_flow = $this->flosc->is_user_new_to_flow($user_id, $flow_id);

        if (!empty($flow_id)) {
            update_user_meta($user_id, '_flosc_registration_flow', $flow_id);
            $this->flosc->record_user_flow_usage($user_id, $flow_id, 'sso_' . $provider_id);
        }

        if (!$is_new_flow) {
            return;
        }

        // SSO accounts are active (provider verified session). No pending email gate.
        update_user_meta($user_id, '_flosc_email_account_status', 'active');
        update_user_meta($user_id, '_flosc_email_verified_at', current_time('mysql'));

        $context = $this->get_guest_email_context($flow_id, $user_id);
        $chat_url = !empty($context['chat_url']) ? $context['chat_url'] : $this->flosc->get_app_url();
        $app_name = !empty($context['app_name']) ? $context['app_name'] : 'FLOSC';
        $team_name = !empty($context['team_name']) ? $context['team_name'] : $app_name;
        $safe_email = esc_html($user->user_email);
        $safe_url = esc_url($chat_url);
        $display = $user->display_name ? $user->display_name : $user->user_login;

        $subject_tpl = trim((string) ($context['settings']['guest_welcome_subject'] ?? 'Welcome to {app_name}'));
        $body_tpl = trim((string) ($context['settings']['guest_welcome_body'] ?? "Hi {name}!\n\nWelcome to {app_name}!\n\nYou signed in with social login. Continue here: {chat_url}\n\n— The {team_name}"));

        // No MagicLink for SSO: strip access-link placeholders if templates still contain them.
        $subject = $this->replace_guest_email_placeholders($subject_tpl, $user, 30);
        $subject = str_replace(array('{magic_url}', '{link_name}'), array($chat_url, $app_name), $subject);

        $welcome_text = $this->replace_guest_email_placeholders($body_tpl, $user, 30);
        $welcome_text = str_replace(array('{magic_url}', '{link_name}'), array($chat_url, $app_name), $welcome_text);
        // Remove empty "Access link:" lines if template left a bare label.
        $welcome_text = preg_replace('/Access link:\s*\n/i', '', $welcome_text);
        $welcome_html = nl2br(esc_html($welcome_text));

        $body = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap">'
            . '<div class="flosc-email-card">'
            . '<h1 class="flosc-email-title">Welcome to ' . esc_html($app_name) . '</h1>'
            . '<p class="flosc-email-lead">' . $welcome_html . '</p>'
            . '<p class="flosc-email-cta-wrap"><a class="flosc-email-cta" href="' . $safe_url . '">' . esc_html__('Continue', 'flosc') . '</a></p>'
            . '<p class="flosc-email-copy">If the button does not work, copy and paste this link into your browser:</p>'
            . '<p class="flosc-email-url"><a href="' . $safe_url . '">' . $safe_url . '</a></p>'
            . '<p class="flosc-email-foot">This message was sent to ' . $safe_email . '.</p>'
            . '</div></div></body></html>';

        $sent = wp_mail(
            $user->user_email,
            $subject,
            $body,
            $this->get_flosc_mail_headers($flow_id, (int) $user_id, true)
        );
        if ($sent) {
            $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
            if ($flow_stem !== '') {
                $sent_by_flow = get_user_meta($user_id, '_flosc_sso_welcome_email_sent_flows', true);
                if (!is_array($sent_by_flow)) {
                    $sent_by_flow = array();
                }
                $sent_by_flow[$flow_stem] = time();
                update_user_meta($user_id, '_flosc_sso_welcome_email_sent_flows', $sent_by_flow);
            } else {
                update_user_meta($user_id, '_flosc_sso_welcome_email_sent', time());
            }
        } elseif (defined('FLOSC_DEBUG') && FLOSC_DEBUG) {
            flosc_log("FLOSC SSO: wp_mail failed for user {$user_id} ({$user->user_email})");
        }
    }

    /**
     * Send due guest follow-up emails for a single guest user (slot-based windows).
     */
    public function send_due_guest_followups_for_user($user_id) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) return;

        if (get_user_meta($user->ID, '_flosc_purchased', true)) return;

        $linked_providers = get_user_meta($user->ID, '_flosc_sso_linked_providers', true);
        $is_sso_user = is_array($linked_providers) && !empty($linked_providers);
        $is_email_user = ((string) get_user_meta($user->ID, '_flosc_registration_method', true) === 'email');
        if (!$is_sso_user && !$is_email_user) return;

        $days_elapsed = (int) floor((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS);
        $sent = get_user_meta($user->ID, '_flosc_guest_emails_sent', true) ?: [];
        if (!is_array($sent)) {
            $sent = [];
        }
        $settings = $this->flosc->get_flow_settings_for_user($user->ID);
        if (!is_array($settings)) {
            $settings = [];
        }
        // Per-flow guest window — never hardcode access length for all flows.
        $guest_window = max(0, (int) ($settings['guest_access_days'] ?? 0));
        $days_remaining = ($guest_window > 0)
            ? max(0, $guest_window - $days_elapsed)
            : 0;
        $updated = false;

        if (!function_exists('flosc_guest_followup_slots')) {
            return;
        }

        foreach (flosc_guest_followup_slots() as $slot_id => $meta) {
            if (function_exists('flosc_guest_followup_was_sent') && flosc_guest_followup_was_sent($sent, $slot_id)) {
                continue;
            }

            $min_day = (int) flosc_guest_followup_get($settings, $slot_id, 'min_day', (int) ($meta['default_min_day'] ?? 0));
            $max_day = (int) flosc_guest_followup_get($settings, $slot_id, 'max_day', (int) ($meta['default_max_day'] ?? $min_day));
            $min_day = max(0, min(365, $min_day));
            $max_day = max(0, min(365, $max_day));
            if ($max_day < $min_day) {
                $max_day = $min_day;
            }

            if ($days_elapsed < $min_day || $days_elapsed > $max_day) {
                continue;
            }

            $subject = (string) flosc_guest_followup_get($settings, $slot_id, 'subject', (string) ($meta['default_subject'] ?? ''));
            $body    = (string) flosc_guest_followup_get($settings, $slot_id, 'body', (string) ($meta['default_body'] ?? ''));
            $subject = $this->replace_guest_email_placeholders($subject, $user, $days_remaining);
            $body    = $this->replace_guest_email_placeholders($body, $user, $days_remaining);
            $this->send_email_throttled(
                $user->user_email,
                $subject,
                $body,
                $this->get_flosc_mail_headers('', (int) $user->ID, false)
            );
            $sent[]  = $slot_id;
            $updated = true;
        }

        if ($updated) {
            update_user_meta($user->ID, '_flosc_guest_emails_sent', $sent);
        }
    }

    /**
     * Throttled mailer for batch (cron) sends: caps emails per run and spaces them slightly so a
     * daily run cannot burst the mail server. Welcome emails are event-driven and NOT throttled.
     * Cap is filterable via 'flosc_email_max_per_run' (0 = unlimited). Returns false when the cap
     * is reached so callers can stop and resume on the next run.
     */
    public function send_email_throttled($to, $subject, $body, $headers) {
        $max = (int) apply_filters('flosc_email_max_per_run', 50);
        if ($max > 0 && $this->flosc_email_sent_this_run >= $max) {
            return false;
        }
        $this->flosc_email_sent_this_run++;
        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Shared HTML email card (matches the guest/member welcome styling).
     */
    public function flosc_email_html_card($context, $user, $body_text, $button_url = '', $button_label = '') {
        $safe_email = esc_html($user->user_email);
        $body_html  = nl2br(esc_html($body_text));
        $html = '<!doctype html><html><body class="flosc-email-body">'
            . '<div class="flosc-email-wrap">'
            . '<div class="flosc-email-card">'
            . '<p class="flosc-email-lead">' . $body_html . '</p>';
        if ($button_url !== '') {
            $safe_url = esc_url($button_url);
            $label    = esc_html($button_label !== '' ? $button_label : (string) ($context['link_name'] ?? 'Open'));
            $html .= '<p class="flosc-email-cta-wrap"><a class="flosc-email-cta" href="' . $safe_url . '">' . $label . '</a></p>'
                . '<p class="flosc-email-copy">If the button does not work, copy and paste this link into your browser:</p>'
                . '<p class="flosc-email-url"><a href="' . $safe_url . '">' . $safe_url . '</a></p>';
        }
        $html .= '<p class="flosc-email-foot">This message was sent to ' . $safe_email . '.</p>'
            . '</div></div></body></html>';
        return $html;
    }

    public function dispatch_member_welcome_email($user_id, $purchase_data = []) {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) return;
        if (!is_array($purchase_data)) $purchase_data = [];

        $flow_id = sanitize_key((string) ($purchase_data['flow_id'] ?? get_user_meta($user_id, '_flosc_registration_flow', true)));
        $level   = sanitize_key((string) ($purchase_data['grants_level'] ?? get_user_meta($user_id, '_flosc_member_level', true)));
        if ($level === '') $level = 'member';
        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        $dedup_key = $flow_stem . ':' . $level;

        $sent = get_user_meta($user_id, '_flosc_member_welcome_sent', true);
        if (!is_array($sent)) $sent = [];
        if (!empty($sent[$dedup_key])) return; // already welcomed for this flow+level

        $context  = $this->get_guest_email_context($flow_id, (int) $user_id);
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $prefix   = 'member_' . $level;
        $subject_tpl = trim((string) ($settings[$prefix . '_welcome_subject'] ?? 'Welcome to {app_name} — your membership is active'));
        $body_tpl    = trim((string) ($settings[$prefix . '_welcome_body'] ?? "Hi {name}!\n\nYour {app_name} membership is now active. Continue here: {chat_url}\n\n— The {team_name}"));

        $magic_url = $this->flosc->flosc_user_magic_url($user, $context, $flow_id);
        $subject   = str_replace('{magic_url}', $magic_url, $this->replace_guest_email_placeholders($subject_tpl, $user, 0));
        $text      = str_replace('{magic_url}', $magic_url, $this->replace_guest_email_placeholders($body_tpl, $user, 0));
        $body      = $this->flosc_email_html_card($context, $user, $text, $magic_url, $context['link_name']);

        $ok = wp_mail($user->user_email, $subject, $body, $this->get_flosc_mail_headers($flow_id, (int) $user_id, true));
        if ($ok) {
            $sent[$dedup_key] = time();
            update_user_meta($user_id, '_flosc_member_welcome_sent', $sent);
        }
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC member welcome: user {$user_id} flow {$flow_stem} level {$level} sent=" . ($ok ? '1' : '0'));
    }

    /**
     * Newsletter welcome — one per user per flow. Records send time (anchors newsletter follow-ups).
     */
    public function dispatch_newsletter_welcome_email($user_id, $flow_id = '') {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) return;
        $flow_id = sanitize_key((string) ($flow_id ?: get_user_meta($user_id, '_flosc_registration_flow', true)));
        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));

        $sent = get_user_meta($user_id, '_flosc_newsletter_welcome_sent', true);
        if (!is_array($sent)) $sent = [];
        if (!empty($sent[$flow_stem])) return;

        $context  = $this->get_guest_email_context($flow_id, (int) $user_id);
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $subject_tpl = trim((string) ($settings['newsletter_welcome_subject'] ?? 'Thanks for subscribing to {app_name}'));
        $body_tpl    = trim((string) ($settings['newsletter_welcome_body'] ?? "Hi {name}!\n\nThanks for subscribing to the {app_name} newsletter.\n\n— The {team_name}"));

        $subject = $this->replace_guest_email_placeholders($subject_tpl, $user, 0);
        $text    = $this->replace_guest_email_placeholders($body_tpl, $user, 0);
        $body    = $this->flosc_email_html_card($context, $user, $text, $context['chat_url'], 'Visit ' . $context['app_name']);

        $ok = wp_mail($user->user_email, $subject, $body, $this->get_flosc_mail_headers($flow_id, (int) $user_id, true));
        if ($ok) {
            $sent[$flow_stem] = time();
            update_user_meta($user_id, '_flosc_newsletter_welcome_sent', $sent);
        }
        if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC newsletter welcome: user {$user_id} flow {$flow_stem} sent=" . ($ok ? '1' : '0'));
    }

    public function subscribe_to_newsletter($user_id, $flow_id = '') {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return;
        if (get_user_meta($user_id, 'flosc_newsletter_optin', true)) return; // already subscribed
        update_user_meta($user_id, 'flosc_newsletter_optin', time());
        $this->dispatch_newsletter_welcome_email($user_id, $flow_id);
    }

    /**
     * Render the optional newsletter opt-in checkbox on the WP user profile.
     */
    public function render_newsletter_profile_field($user) {
        $checked = get_user_meta($user->ID, 'flosc_newsletter_optin', true) ? 'checked' : '';
        echo '<h3>' . esc_html__('Newsletter', 'flosc') . '</h3>';
        echo '<table class="form-table"><tr>';
        echo '<th><label for="flosc_newsletter_optin">' . esc_html__('Newsletter subscription', 'flosc') . '</label></th>';
        echo '<td><label><input type="checkbox" name="flosc_newsletter_optin" id="flosc_newsletter_optin" value="1" ' . esc_attr($checked) . '> ' . esc_html__('Subscribed to the newsletter', 'flosc') . '</label></td>';
        echo '</tr></table>';
    }

    /**
     * Save the newsletter opt-in checkbox; sends the welcome on first opt-in.
     */
    public function save_newsletter_profile_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) return;
        // WP core verifies the profile-update nonce before these hooks fire.
        $opted = (bool) filter_input( INPUT_POST, 'flosc_newsletter_optin', FILTER_UNSAFE_RAW );
        if ($opted) {
            $this->subscribe_to_newsletter($user_id);
        } else {
            delete_user_meta($user_id, 'flosc_newsletter_optin');
        }
    }

    /**
     * Generic per-series follow-up sender. Reads <prefix>_followups (repeater rows: day/subject/body)
     * and sends those whose day offset has elapsed since $anchor_ts (the welcome-email send time)
     * and were not already sent. Idempotent; flow- and series-aware sent-state.
     */
    public function send_due_series_followups($user, $prefix, $anchor_ts, $sent_meta_key, $flow_id) {
        if (!$user || empty($user->user_email)) return;
        $anchor_ts = (int) $anchor_ts;
        if ($anchor_ts <= 0) return; // follow-ups only start once the welcome has been sent

        $context  = $this->get_guest_email_context($flow_id, (int) $user->ID);
        $settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
        $followups = $settings[$prefix . '_followups'] ?? [];
        if (!is_array($followups) || empty($followups)) return;

        $days_elapsed = (int) floor((time() - $anchor_ts) / DAY_IN_SECONDS);
        $flow_stem = sanitize_key(pathinfo(basename((string) $flow_id), PATHINFO_FILENAME));
        $bucket = $flow_stem . ':' . $prefix;

        $sent = get_user_meta($user->ID, $sent_meta_key, true);
        if (!is_array($sent)) $sent = [];
        $done = is_array($sent[$bucket] ?? null) ? $sent[$bucket] : [];
        $updated = false;

        foreach ($followups as $i => $fu) {
            $day = max(0, (int) ($fu['day'] ?? 0));
            if ($days_elapsed >= $day && !in_array($i, $done, true)) {
                $subject = $this->replace_guest_email_placeholders((string) ($fu['subject'] ?? ''), $user, 0);
                $body    = $this->replace_guest_email_placeholders((string) ($fu['body'] ?? ''), $user, 0);
                if ($subject !== '' || $body !== '') {
                    $ok = $this->send_email_throttled($user->user_email, $subject, $body, $this->get_flosc_mail_headers($flow_id, (int) $user->ID, false));
                    if ($ok === false) { break; } // per-run send cap reached — resume on the next cron run
                }
                $done[] = $i;
                $updated = true;
                if (defined('FLOSC_DEBUG') && FLOSC_DEBUG) flosc_log("FLOSC followup: user {$user->ID} {$bucket} idx {$i} day {$day}");
            }
        }
        if ($updated) {
            $sent[$bucket] = $done;
            update_user_meta($user->ID, $sent_meta_key, $sent);
        }
    }

    /**
     * Cron: flosc_guest_followup_cron — guest day-window emails + member & newsletter follow-up series.
     * Member/newsletter follow-up day offsets are measured from the welcome-email send time.
     */
    public function run_guest_followup_emails() {
        $this->flosc_email_sent_this_run = 0; // reset per-run rate-limit counter
        // Guest pass: SSO/email guests (excludes purchased) — existing behavior.
        $guest_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_sso_linked_providers' )
            : array();
        foreach ( $guest_ids as $guest_uid ) {
            $this->send_due_guest_followups_for_user( (int) $guest_uid );
        }

        // Member pass: members get their level's follow-up series (anchor = member welcome send time).
        $member_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( '_flosc_member_access', 'true', '=' )
            : array();
        foreach ( $member_ids as $member_uid ) {
            $user = get_userdata( (int) $member_uid );
            if ( ! $user ) {
                continue;
            }
            $level     = sanitize_key((string) get_user_meta($user->ID, '_flosc_member_level', true)) ?: 'member';
            $flow_id   = (string) get_user_meta($user->ID, '_flosc_registration_flow', true);
            $flow_stem = sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
            $wsent     = get_user_meta($user->ID, '_flosc_member_welcome_sent', true);
            $anchor    = is_array($wsent) ? (int) ($wsent[$flow_stem . ':' . $level] ?? 0) : 0;
            $this->send_due_series_followups($user, 'member_' . $level, $anchor, '_flosc_member_emails_sent', $flow_id);
        }

        // Newsletter pass: opted-in subscribers (anchor = newsletter welcome send time).
        $subscriber_ids = function_exists( 'flosc_get_user_ids_for_meta' )
            ? flosc_get_user_ids_for_meta( 'flosc_newsletter_optin' )
            : array();
        foreach ( $subscriber_ids as $sub_uid ) {
            $user = get_userdata( (int) $sub_uid );
            if ( ! $user ) {
                continue;
            }
            $flow_id   = (string) get_user_meta($user->ID, '_flosc_registration_flow', true);
            $flow_stem = sanitize_key(pathinfo(basename($flow_id), PATHINFO_FILENAME));
            $wsent     = get_user_meta($user->ID, '_flosc_newsletter_welcome_sent', true);
            $anchor    = is_array($wsent) ? (int) ($wsent[$flow_stem] ?? 0) : 0;
            $this->send_due_series_followups($user, 'newsletter', $anchor, '_flosc_newsletter_emails_sent', $flow_id);
        }
    }

    /**
     * Default email template
     */
    public function get_default_email_template() {
        return "Hi {name},

Thanks for taking the {product_name} quiz!

YOUR SCORE: {score}%

✅ Correct: {correct}
❌ Needs Practice: {incorrect}
{oto_section}
Your personalized learning path is ready. Log in to get your FREE lesson and start improving today!

{app_link}

Best,
The {product_name} Team";
    }

    /**
     * Reliability guard for SSO email sequence.
     * - Ensures welcome email exists once for SSO-created users
     * - Sends any due guest follow-up emails (slot windows)
     */
    public function maybe_run_sso_email_sequence_for_user($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return;

        $linked_providers = get_user_meta($user_id, '_flosc_sso_linked_providers', true);
        if (!is_array($linked_providers) || empty($linked_providers)) {
            return;
        }

        $provider_id = sanitize_key((string) ($linked_providers[0] ?? 'google'));
        $flow_id = sanitize_key((string) get_user_meta($user_id, '_flosc_registration_flow', true));
        $this->send_sso_welcome_email($user_id, $provider_id, ['flow_id' => $flow_id]);

        $this->send_due_guest_followups_for_user($user_id);
    }

    /**
     * Process SSO flow email sequence on SSO entry events.
     * Fires on successful SSO login/auto-link and sends welcome only when user is new to that flow.
     */
    public function maybe_process_sso_flow_email_sequence($user_id, $provider_id, $user_data) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return;

        $flow_id = sanitize_key((string) ($user_data['flow_id'] ?? ''));
        if ($flow_id !== '') {
            update_user_meta($user_id, '_flosc_registration_flow', $flow_id);
        }

        $this->send_sso_welcome_email($user_id, $provider_id, is_array($user_data) ? $user_data : []);
        $this->send_due_guest_followups_for_user($user_id);
    }

}
