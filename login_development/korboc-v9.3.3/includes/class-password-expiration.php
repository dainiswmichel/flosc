<?php
/**
 * Password Expiration Handler
 * Handles 72-hour password expiration for Korboc survey email registrations
 * Version: 1.0.0
 * Date: 2025-12-12
 */

if (!defined('ABSPATH')) {
    exit;
}

class Korboc_Password_Expiration {
    
    const EXPIRATION_HOURS = 72; // 72 hours = 3 days
    
    /**
     * Initialize password expiration system
     */
    public function __construct() {
        // Check password age on login attempt
        add_filter('authenticate', array($this, 'check_password_expiration'), 30, 3);
        
        // Schedule twicedaily check for expired passwords
        add_action('korboc_check_expired_passwords', array($this, 'send_expiration_reminders'));
        
        // Register cron schedule on activation
        add_action('init', array($this, 'schedule_cron'));
    }
    
    /**
     * Schedule the cron job if not already scheduled
     */
    public function schedule_cron() {
        if (!wp_next_scheduled('korboc_check_expired_passwords')) {
            wp_schedule_event(time(), 'twicedaily', 'korboc_check_expired_passwords');
        }
    }
    
    /**
     * Check if user's password has expired (only for Korboc email registrations)
     */
    public function check_password_expiration($user, $username, $password) {
        // Skip if already an error or not a user object
        if (is_wp_error($user) || !isset($user->ID)) {
            return $user;
        }
        
        // Only check for users created via Korboc survey email registration
        $survey_started = get_user_meta($user->ID, 'korboc_survey_started', true);
        
        if (empty($survey_started)) {
            // Not a Korboc survey user, allow login
            return $user;
        }
        
        // Check if user has already reset their password
        $password_reset = get_user_meta($user->ID, 'korboc_password_reset', true);
        
        if ($password_reset === 'yes') {
            // User has reset password, allow login
            return $user;
        }
        
        // Check if password has expired (72 hours = 259200 seconds)
        $expiration_seconds = self::EXPIRATION_HOURS * 3600;
        $current_time = time();
        $age = $current_time - $survey_started;
        
        if ($age > $expiration_seconds) {
            // Password expired - block login with kind message
            return new WP_Error(
                'password_expired',
                $this->get_expiration_message()
            );
        }
        
        return $user;
    }
    
    /**
     * Get kind expiration message in Latvian
     */
    private function get_expiration_message() {
        $reset_url = wp_lostpassword_url();
        
        return sprintf(
            '<strong>Jūsu sākotnējā parole ir derīga 72 stundas.</strong><br><br>' .
            'Jūsu paroles derīguma termiņš ir beidzies drošības nolūkos. ' .
            'Lūdzu, <a href="%s">atjaunojiet savu paroli</a>, lai turpinātu.<br><br>' .
            '<small>Ja jums ir jautājumi, sazinieties ar Daini: <a href="mailto:dainis@dainis.net">dainis@dainis.net</a></small>',
            esc_url($reset_url)
        );
    }
    
    /**
     * Send reminder emails to users whose passwords expired recently
     * Runs twice daily (every 12 hours) via WordPress cron
     */
    public function send_expiration_reminders() {
        global $wpdb;
        
        // Find users whose passwords expired in the last 12 hours
        // (matches our twicedaily cron schedule)
        $expiration_seconds = self::EXPIRATION_HOURS * 3600; // 72 hours
        $twelve_hours_ago = 12 * 3600; // 12 hours (our cron interval)
        $current_time = time();
        
        // Calculate the time window
        $expired_recently_start = $current_time - $expiration_seconds - $twelve_hours_ago;
        $expired_recently_end = $current_time - $expiration_seconds;
        
        // Find users whose survey_started timestamp falls in this window
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, meta_value as survey_started
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'korboc_survey_started'
            AND meta_value > 0
            AND CAST(meta_value AS UNSIGNED) BETWEEN %d AND %d
        ", $expired_recently_start, $expired_recently_end));
        
        $sent_count = 0;
        
        foreach ($users as $user_data) {
            $user_id = $user_data->user_id;
            
            // Check if already sent reminder
            $reminder_sent = get_user_meta($user_id, 'korboc_expiration_reminder_sent', true);
            if ($reminder_sent === 'yes') {
                continue;
            }
            
            // Check if user already reset password
            $password_reset = get_user_meta($user_id, 'korboc_password_reset', true);
            if ($password_reset === 'yes') {
                continue;
            }
            
            // Send reminder email
            if ($this->send_expiration_email($user_id)) {
                // Mark as sent
                update_user_meta($user_id, 'korboc_expiration_reminder_sent', 'yes');
                $sent_count++;
            }
        }
        
        if ($sent_count > 0) {
            error_log('Korboc Password Expiration: ✓ Sent reminders to ' . $sent_count . ' user(s)');
        }
    }
    
    /**
     * Send kind expiration reminder email in Latvian
     */
    private function send_expiration_email($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $reset_url = wp_lostpassword_url();
        $first_name = get_user_meta($user_id, 'korboc_first_name', true);
        if (empty($first_name)) {
            $first_name = $user->display_name;
        }
        
        $subject = 'Lūdzu, atjaunojiet savu Korboc paroli 🔐';
        
        $message = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background-color: #1c4d1c; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .info-box { background-color: #fff9e6; border: 1px solid #b9944a; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .button { display: inline-block; background-color: #1c4d1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #667; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Paroles Atjaunošana</h1>
    </div>
    
    <div class="content">
        <p>Labdien, <strong>' . esc_html($first_name) . '</strong>!</p>
        
        <p>Jūsu Korboc konta sākotnējās paroles derīguma termiņš ir beidzies (72 stundas pēc konta izveides).</p>
        
        <div class="info-box">
            <h3>📌 Ko tas nozīmē?</h3>
            <p>Drošības nolūkos sākotnējās paroles ir derīgas tikai 3 dienas. Tas palīdz aizsargāt jūsu kontu.</p>
            <p><strong>Lūdzu, izveidojiet jaunu paroli, lai turpinātu piekļuvi saviem datiem.</strong></p>
        </div>
        
        <p style="text-align: center; margin: 20px 0;">
            <a href="' . esc_url($reset_url) . '" class="button" style="color: white; text-decoration: none;">
                🔑 Atjaunot Paroli
            </a>
        </p>
        
        <p>Process ir vienkāršs:</p>
        <ol>
            <li>Noklikšķiniet uz pogas "Atjaunot Paroli"</li>
            <li>Ievadiet savu e-pastu (<strong>' . esc_html($user->user_email) . '</strong>)</li>
            <li>Saņemsiet e-pastu ar paroles atjaunošanas saiti</li>
            <li>Izveidojiet jaunu paroli</li>
        </ol>
        
        <p>Ja jums ir jautājumi vai nepieciešama palīdzība, droši sazinieties!</p>
        
        <p>Ar cieņu,<br>
        <strong>Dainis W. Michel</strong><br>
        Komponists un Izgudrotājs<br>
        <a href="mailto:dainis@dainis.net">dainis@dainis.net</a><br>
        <a href="https://dainis.net">dainis.net</a></p>
    </div>
    
    <div class="footer">
        <p>© 2025 Korboc.lv | <a href="https://www.korboc.lv">www.korboc.lv</a><br>
        <small>Šis ir automatizēts paziņojums no Korboc sistēmas.</small></p>
    </div>
</body>
</html>';
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Korboc.lv <noreply@korboc.lv>'
        );
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        if ($sent) {
            error_log('Korboc Password Expiration: ✓ Reminder sent to ' . $user->user_email);
        } else {
            error_log('Korboc Password Expiration: ✗ Failed to send reminder to ' . $user->user_email);
        }
        
        return $sent;
    }
    
    /**
     * Mark password as reset (called after successful password reset)
     */
    public static function mark_password_reset($user_id) {
        update_user_meta($user_id, 'korboc_password_reset', 'yes');
        error_log('Korboc Password Expiration: Password reset marked for user ID ' . $user_id);
    }
    
    /**
     * Cleanup: Unschedule cron on deactivation
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled('korboc_check_expired_passwords');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'korboc_check_expired_passwords');
        }
    }
}

// Hook to mark password as reset when user changes password
add_action('password_reset', 'korboc_mark_password_reset_on_change', 10, 2);
function korboc_mark_password_reset_on_change($user, $new_pass) {
    if (isset($user->ID)) {
        Korboc_Password_Expiration::mark_password_reset($user->ID);
    }
}

// Also mark when password changed through profile
add_action('profile_update', 'korboc_mark_password_reset_on_profile_update', 10, 2);
function korboc_mark_password_reset_on_profile_update($user_id, $old_user_data) {
    $user = get_userdata($user_id);
    if ($user && $user->user_pass !== $old_user_data->user_pass) {
        Korboc_Password_Expiration::mark_password_reset($user_id);
    }
}
