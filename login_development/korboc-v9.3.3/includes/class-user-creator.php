<?php
/**
 * User Creator Class v3.0
 * Handles user creation, BuddyBoss profile setup, and private messaging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Korboc_User_Creator {
    
    /**
     * Create quick account from email only
     */
    public function create_quick_account($email) {
        // Generate username from email
        $username = $this->generate_username($email);
        
        // Generate secure password
        $password = wp_generate_password(12, true, true);
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('subscriber');

        // v5.0.0: Track when survey started (account created)
        update_user_meta($user_id, 'korboc_survey_started', time());

        // Store temporary password for email
        update_user_meta($user_id, '_temp_password', $password);

        // Send quick account email
        $this->send_quick_account_email($user_id, $password);

        return $user_id;
    }
    
    /**
     * Create WordPress user from survey data
     */
    public function create_user_from_survey($data) {
        $email = sanitize_email($data['email']);
        $first_name = sanitize_text_field($data['first_name'] ?? '');
        $last_name = sanitize_text_field($data['last_name'] ?? '');
        $full_name = trim($first_name . ' ' . $last_name);
        
        // Check if user already exists
        if (email_exists($email)) {
            $user = get_user_by('email', $email);
            return $user->ID;
        }
        
        // Generate username from email
        $username = $this->generate_username($email);
        
        // Generate secure password
        $password = wp_generate_password(12, true, true);
        
        // Create the user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'nickname', $full_name);
        update_user_meta($user_id, '_temp_password', $password);

        // v5.0.0: Track when survey started (account created)
        update_user_meta($user_id, 'korboc_survey_started', time());

        // Set user role
        $user = new WP_User($user_id);
        $user->set_role('subscriber');
        
        // Save survey data
        $this->save_survey_data($user_id, $data);
        
        // Setup BuddyBoss profile
        if (function_exists('bp_is_active')) {
            $this->setup_buddypress_profile($user_id, $data);
        }
        
        // Add to WLM level
        $this->add_to_wlm_level($user_id);
        
        // Add to BuddyBoss group
        $this->add_to_buddyboss_group($user_id);
        
        return $user_id;
    }
    
    /**
     * Update existing user's survey data
     */
    public function update_user_survey_data($user_id, $data) {
        $this->save_survey_data($user_id, $data);
        
        if (function_exists('bp_is_active')) {
            $this->setup_buddypress_profile($user_id, $data);
        }
        
        $this->add_to_buddyboss_group($user_id);
    }
    
    /**
     * Generate unique username from email
     */
    private function generate_username($email) {
        $username = sanitize_user(current(explode('@', $email)));
        
        if (username_exists($username)) {
            $i = 1;
            $new_username = $username;
            while (username_exists($new_username)) {
                $new_username = $username . $i;
                $i++;
            }
            $username = $new_username;
        }
        
        return $username;
    }
    
    /**
     * Save survey data as user meta
     */
    private function save_survey_data($user_id, $data) {
        // Profile Basics
        update_user_meta($user_id, 'korboc_first_name', sanitize_text_field($data['first_name'] ?? ''));
        update_user_meta($user_id, 'korboc_last_name', sanitize_text_field($data['last_name'] ?? ''));
        update_user_meta($user_id, 'korboc_how_to_address', sanitize_text_field($data['how_to_address'] ?? ''));
        update_user_meta($user_id, 'korboc_choir_name', sanitize_text_field($data['choir_name'] ?? ''));
        update_user_meta($user_id, 'korboc_rehearsal_location', sanitize_text_field($data['rehearsal_location'] ?? ''));
        update_user_meta($user_id, 'korboc_role_in_choir', sanitize_text_field($data['role_in_choir'] ?? ''));
        update_user_meta($user_id, 'korboc_voice_part', sanitize_text_field($data['voice_part'] ?? ''));
        update_user_meta($user_id, 'korboc_is_soloist', sanitize_text_field($data['is_soloist'] ?? ''));
        update_user_meta($user_id, 'korboc_soloist_repertoire', sanitize_textarea_field($data['soloist_repertoire'] ?? ''));
        update_user_meta($user_id, 'korboc_phone', sanitize_text_field($data['phone'] ?? ''));
        update_user_meta($user_id, 'korboc_singer_count', intval($data['singer_count'] ?? 0));
        update_user_meta($user_id, 'korboc_song_festival_memory', sanitize_textarea_field($data['song_festival_memory'] ?? ''));

        // Survey Questions
        update_user_meta($user_id, 'korboc_important_features', isset($data['important_features']) ? $data['important_features'] : array());
        update_user_meta($user_id, 'korboc_important_features_other', sanitize_text_field($data['important_features_other'] ?? ''));
        update_user_meta($user_id, 'korboc_additional_valuable', sanitize_textarea_field($data['additional_valuable'] ?? ''));
        update_user_meta($user_id, 'korboc_vision_opinion', isset($data['vision_opinion']) ? $data['vision_opinion'] : array());
        update_user_meta($user_id, 'korboc_vision_opinion_other', sanitize_textarea_field($data['vision_opinion_other'] ?? ''));
        update_user_meta($user_id, 'korboc_pricing_opinion', isset($data['pricing_opinion']) ? $data['pricing_opinion'] : array());
        update_user_meta($user_id, 'korboc_pricing_opinion_other', sanitize_textarea_field($data['pricing_opinion_other'] ?? ''));
        update_user_meta($user_id, 'korboc_communication_thoughts', sanitize_textarea_field($data['communication_thoughts'] ?? ''));
        update_user_meta($user_id, 'korboc_gdpr_consent', isset($data['gdpr_consent']) ? 'yes' : 'no');
    }
    
    /**
     * Setup BuddyPress/BuddyBoss extended profile fields
     */
    private function setup_buddypress_profile($user_id, $data) {
        if (!function_exists('xprofile_set_field_data')) {
            return;
        }
        
        // Name field (field ID 1)
        $full_name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        xprofile_set_field_data(1, $user_id, $full_name);
        
        // Try to find and set custom fields
        $this->set_xprofile_field($user_id, 'Choir Name', $data['choir_name'] ?? '');
        $this->set_xprofile_field($user_id, 'Kora Nosaukums', $data['choir_name'] ?? '');
        $this->set_xprofile_field($user_id, 'Phone', $data['phone'] ?? '');
        $this->set_xprofile_field($user_id, 'Tālrunis', $data['phone'] ?? '');
        $this->set_xprofile_field($user_id, 'Voice Part', $data['voice_part'] ?? '');
        $this->set_xprofile_field($user_id, 'Balss', $data['voice_part'] ?? '');
        $this->set_xprofile_field($user_id, 'Number of Singers', $data['singer_count'] ?? '');
        $this->set_xprofile_field($user_id, 'Dziedātāju Skaits', $data['singer_count'] ?? '');
    }
    
    /**
     * Set xprofile field by name
     */
    private function set_xprofile_field($user_id, $field_name, $value) {
        if (empty($value)) return;
        
        $field_id = xprofile_get_field_id_from_name($field_name);
        if ($field_id) {
            xprofile_set_field_data($field_id, $user_id, $value);
        }
    }
    
    /**
     * Add user to WishList Member level
     */
    private function add_to_wlm_level($user_id) {
        $level_id = get_option('korboc_wlm_level_id', '');
        
        if (empty($level_id) || !function_exists('wlmapi_add_member_to_level')) {
            return;
        }
        
        wlmapi_add_member_to_level($level_id, $user_id);
    }
    
    /**
     * Add user to BuddyBoss/BuddyPress group
     */
    private function add_to_buddyboss_group($user_id) {
        $group_id = get_option('korboc_buddyboss_group_id', '');
        
        if (empty($group_id) || !function_exists('groups_join_group')) {
            return;
        }
        
        groups_join_group($group_id, $user_id);
    }
    
    /**
     * Auto-login user after creation
     */
    public function auto_login_user($user_id) {
        wp_clear_auth_cookie();
        wp_set_auth_cookie($user_id, true, is_ssl());
        wp_set_current_user($user_id);
        do_action('wp_login', wp_get_current_user()->user_login, wp_get_current_user());
        return true;
    }
    
    /**
     * Send BuddyBoss private message with survey responses
     */
    public function send_buddyboss_message($user_id, $survey_data) {
        error_log('Korboc Survey: send_buddyboss_message called for user ID: ' . $user_id);
        
        // Check if BuddyPress/BuddyBoss messaging is available
        if (!function_exists('messages_new_message')) {
            error_log('Korboc Survey: BuddyBoss/BuddyPress messaging not available - messages_new_message function does not exist');
            error_log('Korboc Survey: Make sure BuddyBoss Platform is active and Private Messaging component is enabled');
            return false;
        }
        
        error_log('Korboc Survey: messages_new_message function exists');
        
        // v5.0.2: Get admin ID - auto-detect first administrator if not set
        $admin_id = get_option('korboc_admin_user_id', 0);

        if (empty($admin_id)) {
            // Auto-detect first administrator
            $admins = get_users(array(
                'role' => 'administrator',
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC'
            ));

            if (!empty($admins)) {
                $admin_id = $admins[0]->ID;
                error_log('Korboc Survey: Auto-detected admin user ID: ' . $admin_id . ' (' . $admins[0]->user_login . ')');
            } else {
                error_log('Korboc Survey: CRITICAL - No administrators found on this site!');
                return false;
            }
        } else {
            error_log('Korboc Survey: Using configured admin user ID: ' . $admin_id);
        }

        $user = get_user_by('id', $user_id);

        if (!$user) {
            error_log('Korboc Survey: User not found for ID ' . $user_id);
            return false;
        }

        error_log('Korboc Survey: Sender user found: ' . $user->user_login);

        // Verify admin user exists
        $admin = get_user_by('id', $admin_id);
        if (!$admin) {
            error_log('Korboc Survey: CRITICAL - Admin user not found for ID ' . $admin_id);
            error_log('Korboc Survey: Go to Settings > Korboc Survey and set a valid Admin User ID');
            return false;
        }

        error_log('Korboc Survey: Recipient admin user found: ' . $admin->user_login);
        
        // Format survey responses
        $message = $this->format_survey_message($user_id, $survey_data);
        error_log('Korboc Survey: Message formatted, length: ' . strlen($message) . ' characters');

        // v5.0.0: Changed subject to indicate completion
        $subject = 'Anketa Izpildīta!';
        error_log('Korboc Survey: Message subject: ' . $subject);
        
        // Send private message
        $thread_id = messages_new_message(array(
            'sender_id'  => $user_id,
            'recipients' => array($admin_id),
            'subject'    => $subject,
            'content'    => $message,
        ));
        
        if ($thread_id) {
            error_log('Korboc Survey: ✓ SUCCESS - Message sent! Thread ID: ' . $thread_id);
            error_log('Korboc Survey: Check messages at: ' . home_url('/members/' . $admin->user_login . '/messages/'));
        } else {
            error_log('Korboc Survey: ✗ FAILED - messages_new_message returned false');
            error_log('Korboc Survey: Sender ID: ' . $user_id . ', Recipient ID: ' . $admin_id);
            error_log('Korboc Survey: Possible causes:');
            error_log('  1. BuddyBoss Private Messaging component is disabled');
            error_log('  2. Admin user cannot receive messages');
            error_log('  3. Permissions issue');
        }
        
        return $thread_id;
    }

    /**
     * v5.0.2: Send BuddyBoss message when user registers (before completing survey) - improved admin detection
     */
    public function send_registration_message($user_id, $email) {
        error_log('Korboc Survey: send_registration_message called for user ID: ' . $user_id);

        // Check if BuddyPress/BuddyBoss messaging is available
        if (!function_exists('messages_new_message')) {
            error_log('Korboc Survey: BuddyBoss/BuddyPress messaging not available - messages_new_message function does not exist');
            return false;
        }

        error_log('Korboc Survey: messages_new_message function exists');

        // v5.0.2: Get admin ID - auto-detect first administrator if not set
        $admin_id = get_option('korboc_admin_user_id', 0);

        if (empty($admin_id)) {
            // Auto-detect first administrator
            $admins = get_users(array(
                'role' => 'administrator',
                'number' => 1,
                'orderby' => 'ID',
                'order' => 'ASC'
            ));

            if (!empty($admins)) {
                $admin_id = $admins[0]->ID;
                error_log('Korboc Survey: Auto-detected admin user ID: ' . $admin_id . ' (' . $admins[0]->user_login . ')');
            } else {
                error_log('Korboc Survey: CRITICAL - No administrators found on this site!');
                return false;
            }
        } else {
            error_log('Korboc Survey: Using configured admin user ID: ' . $admin_id);
        }

        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log('Korboc Survey: Sender user not found for ID ' . $user_id);
            return false;
        }

        error_log('Korboc Survey: Sender user found: ' . $user->user_login);

        // Verify admin user exists
        $admin = get_user_by('id', $admin_id);
        if (!$admin) {
            error_log('Korboc Survey: CRITICAL - Admin user not found for ID ' . $admin_id);
            return false;
        }

        error_log('Korboc Survey: Recipient admin user found: ' . $admin->user_login);

        // Format simple registration message
        $message = "JAUNA REĢISTRĀCIJA\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "Lietotājs izveidoja profilu, bet vēl nav pabeidzis aptauju.\n\n";
        $message .= "📧 E-pasts: " . $email . "\n";
        $message .= "👤 Lietotājvārds: " . $user->user_login . "\n";
        $message .= "🆔 ID: " . $user_id . "\n";
        $message .= "⏰ Reģistrācijas laiks: " . current_time('Y-m-d H:i:s') . "\n\n";
        $message .= "🔗 Profils: " . admin_url('user-edit.php?user_id=' . $user_id) . "\n\n";
        $message .= "Gaidīsim, kad viņi pabeigs aptauju! 🎵\n";

        // Subject: "Jauna reģistrācija...anketa iesākta :-)"
        $subject = 'Jauna reģistrācija...anketa iesākta :-)';

        // Send private message
        $thread_id = messages_new_message(array(
            'sender_id'  => $user_id,
            'recipients' => array($admin_id),
            'subject'    => $subject,
            'content'    => $message,
        ));

        if ($thread_id) {
            error_log('Korboc Survey: ✓ Registration message sent! Thread ID: ' . $thread_id);
        } else {
            error_log('Korboc Survey: ✗ Failed to send registration message');
        }

        return $thread_id;
    }

    /**
     * Format survey data into readable message
     */
    private function format_survey_message($user_id, $data) {
        $message = "PROFILA PAMATI:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Vārds: " . ($data['first_name'] ?? '') . " " . ($data['last_name'] ?? '') . "\n";
        $message .= "Uzrunā: " . ($data['how_to_address'] ?? '') . "\n";
        $message .= "Kora nosaukums: " . ($data['choir_name'] ?? '') . "\n";
        $message .= "Mēģinājumu vieta: " . ($data['rehearsal_location'] ?? 'Nav norādīts') . "\n";
        $message .= "Loma korī: " . ($data['role_in_choir'] ?? 'Nav norādīts') . "\n";
        $message .= "Balss: " . ($data['voice_part'] ?? 'Nav norādīts') . "\n";
        $message .= "Solists/e: " . ($data['is_soloist'] ?? 'Nav norādīts') . "\n";
        
        if (!empty($data['soloist_repertoire'])) {
            $message .= "Solistu repertuārs:\n" . $data['soloist_repertoire'] . "\n";
        }
        
        $message .= "E-pasts: " . ($data['email'] ?? '') . "\n";
        $message .= "Tālrunis: " . ($data['phone'] ?? 'Nav norādīts') . "\n";
        $message .= "Dziedātāju skaits: " . ($data['singer_count'] ?? '0') . "\n";

        if (!empty($data['song_festival_memory'])) {
            $message .= "Dziesmu svētku atmiņa:\n" . $data['song_festival_memory'] . "\n";
        }

        $message .= "\nAPTAUJAS ATBILDES:\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
        // Question 1
        if (!empty($data['important_features'])) {
            $message .= "1.J: Vissvarīgākās funkcijas:\n";
            $feature_labels = array(
                'pdf_distribution' => 'Nošu PDF izdale',
                'printing' => 'Izdevīgi drukas piedāvājumi',
                'audio' => 'Audio failu izdale',
                'notice_board' => 'Pasākumu/sadarbību dēlis',
                'tablet_access' => '"Aizmirsu notis" piekļuve planšetē',
                'other' => 'Cits'
            );
            foreach ($data['important_features'] as $feature) {
                $message .= "• " . ($feature_labels[$feature] ?? $feature) . "\n";
            }
            if (!empty($data['important_features_other'])) {
                $message .= "  └ " . $data['important_features_other'] . "\n";
            }
            $message .= "\n";
        }
        
        // Question 2
        if (!empty($data['additional_valuable'])) {
            $message .= "2.J: Kas vēl jāiekļauj:\n";
            $message .= $data['additional_valuable'] . "\n\n";
        }
        
        // Question 3
        if (!empty($data['vision_opinion'])) {
            $message .= "3.J: Viedoklis par dronu gaismas šovu:\n";
            $vision_labels = array(
                'amazing' => '🔥 Brīnišķīga vīzija...lai top!',
                'like' => '✨ Patīk! Interesanta ideja.',
                'support' => '👍 Labi, varu atbalstīt.',
                'neutral' => '🤔 Neitrāli / Nav viedokļa.',
                'unsure' => '😐 Neesmu pārliecināts/a.',
                'dislike' => '❌ Nepatīk',
                'other' => 'Cits viedoklis'
            );
            foreach ($data['vision_opinion'] as $opinion) {
                $message .= "• " . ($vision_labels[$opinion] ?? $opinion) . "\n";
            }
            if (!empty($data['vision_opinion_other'])) {
                $message .= "  └ " . $data['vision_opinion_other'] . "\n";
            }
            $message .= "\n";
        }
        
        // Question 4
        if (!empty($data['pricing_opinion'])) {
            $message .= "4.J: Viedoklis par cenu:\n";
            $pricing_labels = array(
                'yes' => 'Jā',
                'maybe' => 'Varbūt',
                'no' => 'Nē',
                'other' => 'Cits viedoklis'
            );
            foreach ($data['pricing_opinion'] as $opinion) {
                $message .= "• " . ($pricing_labels[$opinion] ?? $opinion) . "\n";
            }
            if (!empty($data['pricing_opinion_other'])) {
                $message .= "  └ " . $data['pricing_opinion_other'] . "\n";
            }
            $message .= "\n";
        }
        
        // Question 5
        if (!empty($data['communication_thoughts'])) {
            $message .= "5.J: Domas par komunikācijas tehnoloģijām:\n";
            $message .= $data['communication_thoughts'] . "\n\n";
        }
        
        $message .= "GDPR piekrišana: " . (isset($data['gdpr_consent']) ? 'Jā' : 'Nē') . "\n";
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Datums: " . current_time('Y-m-d H:i:s');
        
        return $message;
    }
    
    /**
     * v5.0.2: Send quick account creation email (improved HTML version with headers)
     */
    private function send_quick_account_email($user_id, $password) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            error_log('Korboc Survey: send_quick_account_email - User not found for ID ' . $user_id);
            return false;
        }

        $subject = 'Jūsu Korboc konts izveidots! 🎵';

        $survey_url = get_option('korboc_survey_url', home_url());

        // HTML email matching completion email style
        $message = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .header { background-color: #1c4d1c; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .credentials { background-color: #fff9e6; border: 1px solid #b9944a; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .button { display: inline-block; background-color: #1c4d1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #667; }
        code { background-color: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎵 Jūsu Konts Izveidots!</h1>
    </div>

    <div class="content">
        <p>Labdien!</p>

        <p>Jūsu profils ir veiksmīgi izveidots <strong>korboc.lv</strong> sistēmā.</p>

        <div class="credentials">
            <h3>🔐 Jūsu Konta Informācija</h3>
            <p>
                <strong>Lietotājvārds:</strong> ' . esc_html($user->user_login) . '<br>
                <strong>E-pasts:</strong> ' . esc_html($user->user_email) . '<br>
                <strong>Pagaidu Parole:</strong> <code>' . esc_html($password) . '</code>
            </p>
            <p><small>⚠️ Lūdzu, saglabājiet šo informāciju drošā vietā!</small></p>
            <p><small>🕐 <strong>Svarīgi:</strong> Šī sākotnējā parole ir derīga 72 stundas (3 dienas). Pēc tam jums būs jāizveido jauna parole drošības nolūkos.</small></p>
        </div>

        <p>Tagad varat turpināt aizpildīt aptauju un saņemt dāvanu failus! 🎁</p>

        <p style="text-align: center; margin: 20px 0;">
            <a href="' . esc_url($survey_url) . '" class="button" style="color: white; text-decoration: none;">
                🎶 Turpināt Aptauju
            </a>
        </p>

        <p>Paldies, ka pievienojaties Latvijas koru kopienai!</p>

        <p>Ar cieņu,<br>
        <strong>Dainis W. Michel</strong><br>
        Komponists un Izgudrotājs<br>
        <a href="mailto:dainis@dainis.net">dainis@dainis.net</a><br>
        <a href="https://dainis.net">dainis.net</a></p>
    </div>

    <div class="footer">
        <p>© 2025 Korboc.lv | <a href="https://www.korboc.lv">www.korboc.lv</a></p>
    </div>
</body>
</html>';

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Korboc.lv <noreply@korboc.lv>'
        );

        error_log('Korboc Survey: Attempting to send quick account email to ' . $user->user_email);

        $sent = wp_mail($user->user_email, $subject, $message, $headers);

        if (!$sent) {
            error_log('Korboc Survey: ✗ FAILED to send quick account email to ' . $user->user_email);
        } else {
            error_log('Korboc Survey: ✓ SUCCESS - Quick account email sent to ' . $user->user_email);
        }

        return $sent;
    }
    
    /**
     * Send welcome email with credentials and download links
     */
    public function send_welcome_email($user_id, $survey_data) {
        $user = get_user_by('id', $user_id);
        if (!$user) return false;
        
        $password = get_user_meta($user_id, '_temp_password', true);
        $download_page = get_option('korboc_redirect_url', home_url('/members/' . $user->user_login . '/'));
        $first_name = get_user_meta($user_id, 'korboc_first_name', true);
        
        $subject = 'Paldies par Jūsu atbildēm - Korboc.lv Aptauja';
        
        $message = $this->get_welcome_email_template($user, $password, $first_name, $download_page);
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: Korboc.lv <noreply@korboc.lv>'
        );
        
        $sent = wp_mail($user->user_email, $subject, $message, $headers);
        
        // Delete temporary password
        delete_user_meta($user_id, '_temp_password');
        
        return $sent;
    }
    
    /**
     * Get welcome email template
     */
    private function get_welcome_email_template($user, $password, $first_name, $download_page) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background-color: #1c4d1c; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .credentials { background-color: #fff9e6; border: 1px solid #b9944a; padding: 15px; margin: 20px 0; }
                .gift-box { background-color: #f9f9f9; border-left: 4px solid #1c4d1c; padding: 15px; margin: 20px 0; }
                .button { display: inline-block; background-color: #1c4d1c; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 5px; }
                .footer { background-color: #f4f4f4; padding: 15px; text-align: center; font-size: 12px; color: #667; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>✻ Paldies par Jūsu Atbildēm! ✻</h1>
            </div>
            
            <div class="content">
                <p>Labdien, <strong><?php echo esc_html($first_name); ?></strong>!</p>
                
                <p>Liels paldies, ka veltījāt laiku un dalījāties ar saviem vērtīgajiem ieteikumiem!</p>
                
                <?php if ($password): ?>
                <div class="credentials">
                    <h3>🔐 Jūsu Konta Informācija</h3>
                    <p>
                        <strong>Lietotājvārds:</strong> <?php echo esc_html($user->user_login); ?><br>
                        <strong>E-pasts:</strong> <?php echo esc_html($user->user_email); ?><br>
                        <strong>Parole:</strong> <?php echo esc_html($password); ?>
                    </p>
                    <p><small>Lūdzu, saglabājiet šo informāciju drošā vietā!</small></p>
                </div>
                <?php endif; ?>
                
                <div class="gift-box">
                    <h3>🎁 Jūsu Dāvana</h3>
                    <p>Kā solīts, šeit ir pieeja Jūsu kora aranžējumam <em>"Es bitītei namu daru"</em> un citiem resursiem:</p>
                    
                    <?php if ($download_page): ?>
                    <p style="text-align: center; margin: 20px 0;">
                        <a href="<?php echo esc_url($download_page); ?>" class="button" style="background-color: #1c4d1c; font-size: 18px;">
                            👤 Apmeklēt Manu Profilu
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                
                <p>Ar cieņu un pateicību,<br>
                <strong>Dainis W. Michel</strong><br>
                Komponists un Izgudrotājs<br>
                <a href="mailto:dainis@dainis.net">dainis@dainis.net</a><br>
                <a href="https://dainis.net">dainis.net</a></p>
            </div>
            
            <div class="footer">
                <p>© 2025 Korboc.lv | <a href="https://www.korboc.lv">www.korboc.lv</a></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
