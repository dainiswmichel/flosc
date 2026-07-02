<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import IVR from ivr.md to database (REPLACE MODE - ivr.md is source of truth)
 * v9.2.2: IVR Database Integration
 * v1.6.4: Added $custom_ivr_file and $flow_key params for per-flow storage
 *
 * @param bool $preview_only If true, returns preview without making changes
 * @param string|null $custom_ivr_file Optional path to IVR file (defaults to flosc_starter_ivr.md)
 * @param string|null $flow_key Optional per-flow option key (e.g. 'flosc_flow_flosc_default_ivr')
 * @return array Result with success, stats, message, and preview data
 */
function flosc_import_ivr_to_database($preview_only = false, $custom_ivr_file = null, $flow_key = null, $mode = 'merge') {
    $ivr_file = $custom_ivr_file ?? flosc_config_file('flosc_starter_ivr.md');
    $mode = ($mode === 'replace') ? 'replace' : 'merge';

    if (!file_exists($ivr_file)) {
        return ['success' => false, 'message' => 'IVR file not found'];
    }

    require_once FLOSC_PLUGIN_DIR . 'includes/class-ivr-parser.php';
    $parser = FLOSC_IVR_Parser::flosc_instance();
    $markdown = file_get_contents($ivr_file);
    $config = $parser->flosc_parse($markdown);

    if (empty($config)) {
        return ['success' => false, 'message' => 'Failed to parse IVR file'];
    }

    // Get current database state (per-flow if flow_key provided, else global)
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $current_messages = $fs['ivr_messages'] ?? [];
    } else {
        $current_messages = get_option('flosc_ivr_messages', []);
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

    if ($flow_key) {
        // Per-flow storage
        $fs = get_option($flow_key, []);
        $fs['ivr_messages'] = $final_messages;
        $fs['ivr_phases'] = $final_phases;
        $fs['ivr_styles'] = $config['styles'] ?? [];
        $fs['ivr_last_import'] = current_time('mysql');
        $fs['autoprompts'] = $autoprompts_from_ivr;
        update_option($flow_key, $fs);

        // Keep offers registry aligned to IVR offer messages on import.
        flosc_sync_flow_offers_with_ivr_messages($flow_key, $final_messages);
    } else {
        // Global storage (activation hook fallback)
        update_option('flosc_ivr_messages', $final_messages);
        update_option('flosc_ivr_phases', $final_phases);
        update_option('flosc_ivr_styles', $config['styles'] ?? []);
        update_option('flosc_ivr_last_import', current_time('mysql'));
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
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $messages = $fs['ivr_messages'] ?? [];
        $phases = $fs['ivr_phases'] ?? [];
        $styles = $fs['ivr_styles'] ?? [];
    } else {
        $messages = get_option('flosc_ivr_messages', []);
        $phases = get_option('flosc_ivr_phases', []);
        $styles = get_option('flosc_ivr_styles', []);
    }

    if (empty($messages)) {
        return false; // No data to backup
    }

    // Generate markdown (same format as export)
    $markdown = "# FLOSC IVR Configuration (AUTO-BACKUP)\n\n";
    $markdown .= "Backup created: " . current_time('mysql') . "\n\n";

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
    if ($flow_key) {
        $fs = get_option($flow_key, []);
        $messages = $fs['ivr_messages'] ?? [];
        $phases = $fs['ivr_phases'] ?? [];
        $styles = $fs['ivr_styles'] ?? [];
    } else {
        $messages = get_option('flosc_ivr_messages', []);
        $phases = get_option('flosc_ivr_phases', []);
        $styles = get_option('flosc_ivr_styles', []);
    }

    if (empty($messages)) {
        return false;
    }

    // Ensure export includes all DB messages even if ivr_phases is stale or incomplete.
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

    // Build markdown in proper ivr.md format
    $markdown = "# FLOSC IVR Configuration\n\n";

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
    $markdown .= "- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_lesson, first_show_session\n";
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
        $preferred_file = $fs['ivr_file'] ?? ($fs['active_ivr_file'] ?? 'flosc_starter_ivr.md');
        $ivr_file = $data_dir . basename($preferred_file);
    } else {
        $ivr_file = $data_dir . 'flosc_starter_ivr.md';
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
    flosc_auto_export_ivr_to_file($option, null);
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
});
add_action('save_post', function ($post_id, $post) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if (class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::save_meta_box($post_id);
        FLOSC_Concierge::sync_post($post);
    }
}, 20, 2);
add_action('trashed_post', function ($post_id) {
    if (class_exists('FLOSC_Concierge')) {
        FLOSC_Concierge::unsync_post($post_id);
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

        $referenced_offers[$offer_id] = [
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
    }

    $synced_offers = [];
    foreach ($referenced_offers as $offer_id => $seed_offer) {
        $existing_offer = (isset($existing_offers[$offer_id]) && is_array($existing_offers[$offer_id])) ? $existing_offers[$offer_id] : [];
        // IVR offer messages are the sync source of truth for core offer fields.
        $merged_offer = array_merge($existing_offer, $seed_offer);

        if (empty($merged_offer['display_format'])) {
            $merged_offer['display_format'] = 'card';
        }
        $existing_meta = (isset($existing_offer['meta']) && is_array($existing_offer['meta'])) ? $existing_offer['meta'] : [];
        $seed_meta = $seed_offer['meta'] ?? [];
        $merged_offer['meta'] = array_merge($existing_meta, $seed_meta);

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
