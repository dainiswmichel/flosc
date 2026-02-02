<?php
/**
 * Admin Page Class
 * View and export survey responses
 */

if (!defined('ABSPATH')) {
    exit;
}

class Korboc_Admin_Page {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_session_deletion'));
    }
    
    /**
     * Add admin menu page
     */
    public function add_menu_page() {
        add_users_page(
            'Aptaujas Atbildes',
            'Aptaujas Atbildes',
            'manage_options',
            'korboc-survey-responses',
            array($this, 'render_page')
        );
    }
    
    /**
     * Handle CSV export
     */
    public function handle_export() {
        if (!isset($_GET['korboc_export']) || $_GET['korboc_export'] !== 'csv') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        check_admin_referer('korboc_export_csv');
        
        $this->export_to_csv();
        exit;
    }
    
    /**
     * Handle deletion of incomplete session
     * Removes session data from wp_korboc_survey_sessions table
     */
    public function handle_session_deletion() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'delete_session') {
            return;
        }
        
        if (!isset($_GET['session_id'])) {
            return;
        }
        
        $session_id = sanitize_text_field($_GET['session_id']);
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_session_' . $session_id)) {
            wp_die('Security check failed');
        }
        
        // Check admin capability
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Delete from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'korboc_survey_sessions';
        
        $deleted = $wpdb->delete(
            $table_name,
            array('session_id' => $session_id),
            array('%s')
        );
        
        // Redirect back with success message
        if ($deleted) {
            wp_redirect(add_query_arg(array(
                'page' => 'korboc-survey-responses',
                'deleted' => '1'
            ), admin_url('users.php')));
            exit;
        } else {
            wp_die('Failed to delete session');
        }
    }
    
    /**
     * Get sortable column link
     */
    private function get_sortable_link($column, $label) {
        $current_orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $current_order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';

        // Toggle order if clicking the same column
        $new_order = ($current_orderby === $column && $current_order === 'asc') ? 'desc' : 'asc';

        $url = add_query_arg(array(
            'orderby' => $column,
            'order' => $new_order
        ));

        $arrow = '';
        if ($current_orderby === $column) {
            $arrow = $current_order === 'asc' ? ' ▲' : ' ▼';
        }

        return '<a href="' . esc_url($url) . '">' . esc_html($label) . $arrow . '</a>';
    }

    /**
     * Render admin page
     */
    public function render_page() {
        // Show success message if session was deleted
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>Incomplete session deleted successfully.</p></div>';
        }
        
        // Check if viewing single response or table view
        if (isset($_GET['action']) && $_GET['action'] === 'view') {
            $this->render_single_response();
            return;
        }
        
        // Check for table view
        if (isset($_GET['view']) && $_GET['view'] === 'table') {
            $this->render_table_view();
            return;
        }
        
        // Default list view
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';

        $responses = $this->get_all_responses($orderby, $order);
        
        // v9.2.8: Separate users from sessions
        $users = array_filter($responses, function($r) { return $r['source'] === 'user'; });
        $sessions = array_filter($responses, function($r) { return $r['source'] === 'session'; });
        
        ?>
        <div class="wrap">
            <h1>Korboc.lv Aptaujas Atbildes</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo wp_nonce_url(add_query_arg('korboc_export', 'csv'), 'korboc_export_csv'); ?>" class="button">
                        📊 Eksportēt CSV
                    </a>
                    <a href="?page=korboc-survey-responses&view=table" class="button">
                        📋 Table View
                    </a>
                </div>
                <div class="alignright">
                    <span class="displaying-num"><?php echo count($users); ?> lietotāji<?php if (count($sessions) > 0): ?> + <?php echo count($sessions); ?> sesijas<?php endif; ?></span>
                </div>
            </div>
            
            <!-- USERS TABLE -->
            <?php if (empty($users)): ?>
                <p>Nav neviena lietotāja ar aptaujas atbildēm.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php echo $this->get_sortable_link('status', 'Status'); ?></th>
                            <th><?php echo $this->get_sortable_link('choir_name', 'Kora nosaukums'); ?></th>
                            <th><?php echo $this->get_sortable_link('name', 'Kontaktpersona'); ?></th>
                            <th><?php echo $this->get_sortable_link('email', 'E-pasts'); ?></th>
                            <th><?php echo $this->get_sortable_link('singer_count', 'Dziedātāji'); ?></th>
                            <th><?php echo $this->get_sortable_link('date', 'Datums'); ?></th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $response): ?>
                        <tr>
                            <td>
                                <?php if ($response['is_complete']): ?>
                                    <span style="color: green; font-weight: bold;">✓ Pabeigta</span>
                                <?php else: ?>
                                    <span style="color: orange; font-weight: bold;">⚠ Nepabeigta</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($response['choir_name'] ?: '(nav norādīts)'); ?></strong></td>
                            <td><?php echo esc_html($response['first_name'] . ' ' . $response['last_name']); ?></td>
                            <td><a href="mailto:<?php echo esc_attr($response['email']); ?>"><?php echo esc_html($response['email']); ?></a></td>
                            <td><?php echo esc_html($response['singer_count']); ?></td>
                            <td>
                                <?php echo esc_html($response['date']); ?>
                                <?php if ($response['submission_count'] > 1): ?>
                                    <span style="color: #667; font-size: 0.9em;"> (<?php echo $response['submission_count']; ?>×)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('user-edit.php?user_id=' . $response['user_id']); ?>">Skatīt WP Profilu</a><br>
                                <a href="<?php echo home_url('/members/' . $response['user_login'] . '/'); ?>">Skatīt BuddyBoss Profilu</a><br>
                                <a href="#" onclick="showResponse<?php echo $response['user_id']; ?>(); return false;">Skatīt Atbildes</a>
                            </td>
                        </tr>
                        <?php if ($response['source'] !== 'session'): ?>
                        <!-- Expandable row only for users with accounts -->
                        <tr id="response-<?php echo $response['user_id']; ?>" style="display:none;">
                            <td colspan="7">
                                <div style="background: #f9f9f9; padding: 20px; margin: 10px 0;">

                                    <!-- Expand/Collapse All Buttons -->
                                    <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <?php if (!$response['is_complete']): ?>
                                                <button onclick="korbocMarkComplete(<?php echo $response['user_id']; ?>)" class="button button-primary" id="mark-complete-<?php echo $response['user_id']; ?>">
                                                    ✓ Saglabāt kā Pabeigtu
                                                </button>
                                                <span id="complete-status-<?php echo $response['user_id']; ?>" style="margin-left: 10px; color: #46b450; font-weight: bold; display: none;">✓ Saglabāts!</span>
                                            <?php else: ?>
                                                <span style="color: #46b450; font-weight: bold;">✓ Aptauja pabeigta</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($response['submission_count'] > 1): ?>
                                        <div>
                                            <button onclick="korbocAdminExpandAll(<?php echo $response['user_id']; ?>)" class="button" style="margin-right: 5px;">Atvērt Visas</button>
                                            <button onclick="korbocAdminCollapseAll(<?php echo $response['user_id']; ?>)" class="button">Aizvērt Visas</button>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- LATEST SUBMISSION (expanded by default) -->
                                    <div class="korboc-admin-accordion-<?php echo $response['user_id']; ?>">
                                        <div class="korboc-admin-accordion-header" onclick="korbocAdminToggle('admin-sub-<?php echo $response['user_id']; ?>-latest')" style="cursor: pointer; padding: 10px; background: #e8f4f8; border-left: 4px solid #2271b1; margin-bottom: 5px;">
                                            <h3 style="margin: 0; color: #0073aa;">
                                                ▼ Aptauja izpildīta <?php echo esc_html(function_exists('korboc_format_latvian_timestamp') ? korboc_format_latvian_timestamp($response['date']) : $response['date']); ?>
                                            </h3>
                                        </div>
                                        <div id="admin-sub-<?php echo $response['user_id']; ?>-latest" class="korboc-admin-accordion-content-<?php echo $response['user_id']; ?>" style="display: block;">
                                            <div style="background: #fff; padding: 20px; margin-bottom: 20px; border-left: 4px solid #2271b1;">

                                    <!-- Song Festival Memory (first in survey) -->
                                    <h4>Dalāmies ar atmiņām:</h4>
                                    <p><strong>Kāda ir Jūsu visskaistākā Dziesmu svētku atmiņa?</strong><br>
                                    <?php if (!empty($response['song_festival_memory'])): ?>
                                        <?php echo nl2br(esc_html($response['song_festival_memory'])); ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>

                                    <!-- Drone Vision (second in survey) -->
                                    <h4 style="margin-top: 20px;">Vīzija:</h4>
                                    <p><strong>Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka?</strong><br>
                                    <?php if (!empty($response['vision_opinion'])): ?>
                                        <?php echo esc_html(implode(', ', $response['vision_opinion'])); ?>
                                        <?php if (!empty($response['vision_opinion_other'])): ?>
                                            <br><em>Cits: <?php echo nl2br(esc_html($response['vision_opinion_other'])); ?></em>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>

                                    <!-- Profile Basics (numbered 1-11) -->
                                    <h4 style="margin-top: 20px;">Profila Pamati:</h4>
                                    <p><strong>Vārds:</strong> <?php echo esc_html($response['first_name'] . ' ' . $response['last_name']); ?></p>
                                    <p><strong>Kā uzrunāt:</strong> <?php echo esc_html($response['how_to_address']); ?></p>
                                    <p><strong>Kora nosaukums:</strong> <?php echo esc_html($response['choir_name']); ?></p>
                                    <p><strong>Mēģinājumu vieta:</strong> <?php echo esc_html($response['rehearsal_location']); ?></p>
                                    <p><strong>Loma korī:</strong> <?php echo esc_html($response['role_in_choir']); ?></p>
                                    <p><strong>Balss:</strong> <?php echo esc_html($response['voice_part']); ?></p>
                                    <p><strong>Solists/e:</strong> <?php echo esc_html($response['is_soloist']); ?></p>
                                    <?php if (!empty($response['soloist_repertoire'])): ?>
                                    <p><strong>Solistu repertuārs:</strong><br><?php echo nl2br(esc_html($response['soloist_repertoire'])); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Tālrunis:</strong> <?php echo esc_html($response['phone']); ?></p>
                                    <p><strong>Dziedātāju skaits:</strong> <?php echo esc_html($response['singer_count']); ?></p>

                                    <h4 style="margin-top: 20px;">Aptaujas Jautājumi:</h4>
                                    
                                    <p><strong>1.J - Vissvarīgākās funkcijas:</strong><br>
                                    <?php if (!empty($response['important_features'])): ?>
                                        <?php echo esc_html(implode(', ', $response['important_features'])); ?>
                                        <?php if (!empty($response['important_features_other'])): ?>
                                            <br><em>Cits: <?php echo esc_html($response['important_features_other']); ?></em>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>
                                    
                                    <p><strong>2.J - Kas vēl jāiekļauj:</strong><br>
                                    <?php if (!empty($response['additional_valuable'])): ?>
                                        <?php echo nl2br(esc_html($response['additional_valuable'])); ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>

                                    <p><strong>4.J - Viedoklis par cenu:</strong><br>
                                    <?php if (!empty($response['pricing_opinion'])): ?>
                                        <?php echo esc_html(implode(', ', $response['pricing_opinion'])); ?>
                                        <?php if (!empty($response['pricing_opinion_other'])): ?>
                                            <br><em>Cits: <?php echo nl2br(esc_html($response['pricing_opinion_other'])); ?></em>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>
                                    
                                    <p><strong>5.J - Komunikācijas domas:</strong><br>
                                    <?php if (!empty($response['communication_thoughts'])): ?>
                                        <?php echo nl2br(esc_html($response['communication_thoughts'])); ?>
                                    <?php else: ?>
                                        <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                    <?php endif; ?>
                                    </p>
                                    
                                    <p><strong>GDPR piekrišana:</strong> <?php echo esc_html($response['gdpr_consent']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- END LATEST SUBMISSION -->

                                    <!-- PREVIOUS SUBMISSIONS (collapsed by default) -->
                                    <?php if (!empty($response['previous_submissions'])): ?>
                                        <?php
                                        $submission_num = count($response['previous_submissions']);
                                        foreach (array_reverse($response['previous_submissions']) as $prev):
                                            $prev_data = $prev['data'];
                                            $prev_timestamp = isset($prev['timestamp']) ? $prev['timestamp'] : '';
                                        ?>
                                        <div class="korboc-admin-accordion-<?php echo $response['user_id']; ?>">
                                            <div class="korboc-admin-accordion-header" onclick="korbocAdminToggle('admin-sub-<?php echo $response['user_id']; ?>-<?php echo $submission_num; ?>')" style="cursor: pointer; padding: 10px; background: #f5f5f5; border-left: 4px solid #998; margin-bottom: 5px;">
                                                <h3 style="margin: 0; color: #667;">
                                                    ► Aptauja izpildīta <?php echo esc_html(function_exists('korboc_format_latvian_timestamp') ? korboc_format_latvian_timestamp($prev_timestamp) : $prev_timestamp); ?>
                                                </h3>
                                            </div>
                                            <div id="admin-sub-<?php echo $response['user_id']; ?>-<?php echo $submission_num; ?>" class="korboc-admin-accordion-content-<?php echo $response['user_id']; ?>" style="display: none;">
                                                <div style="background: #fff; padding: 20px; margin-bottom: 20px; border-left: 4px solid #998;">

                                            <!-- Song Festival Memory -->
                                            <h4>Dalāmies ar atmiņām:</h4>
                                            <p><strong>Kāda ir Jūsu visskaistākā Dziesmu svētku atmiņa?</strong><br>
                                            <?php if (!empty($prev_data['song_festival_memory'])): ?>
                                                <?php echo nl2br(esc_html($prev_data['song_festival_memory'])); ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>

                                            <!-- Drone Vision -->
                                            <h4 style="margin-top: 20px;">Vīzija:</h4>
                                            <p><strong>Kā Jums patīk vīzija par dronu gaismas šovu virs Meža parka?</strong><br>
                                            <?php if (!empty($prev_data['vision_opinion'])): ?>
                                                <?php echo esc_html(is_array($prev_data['vision_opinion']) ? implode(', ', $prev_data['vision_opinion']) : $prev_data['vision_opinion']); ?>
                                                <?php if (!empty($prev_data['vision_opinion_other'])): ?>
                                                    <br><em>Cits: <?php echo nl2br(esc_html($prev_data['vision_opinion_other'])); ?></em>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>

                                            <!-- Profile Basics -->
                                            <h4 style="margin-top: 20px;">Profila Pamati:</h4>
                                            <p><strong>Vārds:</strong> <?php echo esc_html($prev_data['first_name'] . ' ' . $prev_data['last_name']); ?></p>
                                            <p><strong>Kā uzrunāt:</strong> <?php echo esc_html($prev_data['how_to_address']); ?></p>
                                            <p><strong>Kora nosaukums:</strong> <?php echo esc_html($prev_data['choir_name']); ?></p>
                                            <p><strong>Mēģinājumu vieta:</strong> <?php echo esc_html($prev_data['rehearsal_location']); ?></p>
                                            <p><strong>Loma korī:</strong> <?php echo esc_html($prev_data['role_in_choir']); ?></p>
                                            <p><strong>Balss:</strong> <?php echo esc_html($prev_data['voice_part']); ?></p>
                                            <p><strong>Solists/e:</strong> <?php echo esc_html($prev_data['is_soloist']); ?></p>
                                            <?php if (!empty($prev_data['soloist_repertoire'])): ?>
                                            <p><strong>Solistu repertuārs:</strong><br><?php echo nl2br(esc_html($prev_data['soloist_repertoire'])); ?></p>
                                            <?php endif; ?>
                                            <p><strong>Tālrunis:</strong> <?php echo esc_html($prev_data['phone']); ?></p>
                                            <p><strong>Dziedātāju skaits:</strong> <?php echo esc_html($prev_data['singer_count']); ?></p>

                                            <h4 style="margin-top: 20px;">Aptaujas Jautājumi:</h4>

                                            <p><strong>1.J - Vissvarīgākās funkcijas:</strong><br>
                                            <?php if (!empty($prev_data['important_features'])): ?>
                                                <?php echo esc_html(is_array($prev_data['important_features']) ? implode(', ', $prev_data['important_features']) : $prev_data['important_features']); ?>
                                                <?php if (!empty($prev_data['important_features_other'])): ?>
                                                    <br><em>Cits: <?php echo esc_html($prev_data['important_features_other']); ?></em>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>

                                            <p><strong>2.J - Kas vēl jāiekļauj:</strong><br>
                                            <?php if (!empty($prev_data['additional_valuable'])): ?>
                                                <?php echo nl2br(esc_html($prev_data['additional_valuable'])); ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>

                                            <p><strong>4.J - Viedoklis par cenu:</strong><br>
                                            <?php if (!empty($prev_data['pricing_opinion'])): ?>
                                                <?php echo esc_html(is_array($prev_data['pricing_opinion']) ? implode(', ', $prev_data['pricing_opinion']) : $prev_data['pricing_opinion']); ?>
                                                <?php if (!empty($prev_data['pricing_opinion_other'])): ?>
                                                    <br><em>Cits: <?php echo nl2br(esc_html($prev_data['pricing_opinion_other'])); ?></em>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>

                                            <p><strong>5.J - Komunikācijas domas:</strong><br>
                                            <?php if (!empty($prev_data['communication_thoughts'])): ?>
                                                <?php echo nl2br(esc_html($prev_data['communication_thoughts'])); ?>
                                            <?php else: ?>
                                                <span style="color: #998; font-style: italic;">Neatbildēts</span>
                                            <?php endif; ?>
                                            </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                        $submission_num--;
                                        endforeach;
                                        ?>
                                    <?php endif; ?>
                                    <!-- END PREVIOUS SUBMISSIONS -->

                                </div>
                                <script>
                                function korbocMarkComplete(userId) {
                                    if (!confirm('Vai tiesām vēlaties atzīmēt šo aptauju kā pabeigtu?')) {
                                        return;
                                    }

                                    var button = document.getElementById('mark-complete-' + userId);
                                    var status = document.getElementById('complete-status-' + userId);
                                    button.disabled = true;
                                    button.textContent = 'Saglabā...';

                                    jQuery.ajax({
                                        url: ajaxurl,
                                        type: 'POST',
                                        data: {
                                            action: 'korboc_admin_mark_complete',
                                            user_id: userId,
                                            nonce: '<?php echo wp_create_nonce('korboc_admin_nonce'); ?>'
                                        },
                                        success: function(response) {
                                            if (response.success) {
                                                button.style.display = 'none';
                                                status.style.display = 'inline';
                                                // Reload page after 1 second to update status
                                                setTimeout(function() {
                                                    location.reload();
                                                }, 1000);
                                            } else {
                                                alert('Kļūda: ' + (response.data ? response.data.message : 'Unknown error'));
                                                button.disabled = false;
                                                button.textContent = '✓ Saglabāt kā Pabeigtu';
                                            }
                                        },
                                        error: function() {
                                            alert('Kļūda saglabājot.');
                                            button.disabled = false;
                                            button.textContent = '✓ Saglabāt kā Pabeigtu';
                                        }
                                    });
                                }

                                function showResponse<?php echo $response['user_id']; ?>() {
                                    var row = document.getElementById('response-<?php echo $response['user_id']; ?>');
                                    row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
                                }

                                function korbocAdminToggle(id) {
                                    var content = document.getElementById(id);
                                    var header = content.previousElementSibling;
                                    var arrow = header.querySelector('h3');

                                    if (content.style.display === 'none') {
                                        content.style.display = 'block';
                                        arrow.innerHTML = arrow.innerHTML.replace('►', '▼');
                                    } else {
                                        content.style.display = 'none';
                                        arrow.innerHTML = arrow.innerHTML.replace('▼', '►');
                                    }
                                }

                                function korbocAdminExpandAll(userId) {
                                    var contents = document.querySelectorAll('.korboc-admin-accordion-content-' + userId);
                                    var headers = document.querySelectorAll('.korboc-admin-accordion-' + userId + ' .korboc-admin-accordion-header h3');
                                    contents.forEach(function(content) {
                                        content.style.display = 'block';
                                    });
                                    headers.forEach(function(header) {
                                        header.innerHTML = header.innerHTML.replace('►', '▼');
                                    });
                                }

                                function korbocAdminCollapseAll(userId) {
                                    var contents = document.querySelectorAll('.korboc-admin-accordion-content-' + userId);
                                    var headers = document.querySelectorAll('.korboc-admin-accordion-' + userId + ' .korboc-admin-accordion-header h3');
                                    contents.forEach(function(content) {
                                        content.style.display = 'none';
                                    });
                                    headers.forEach(function(header) {
                                        header.innerHTML = header.innerHTML.replace('▼', '►');
                                    });
                                }
                                </script>
                            </td>
                        </tr>
                        <?php endif; // End if not session ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- SESSIONS ACCORDION -->
            <?php if (!empty($sessions)): ?>
            <div style="margin-top: 30px;">
                <h2 id="sessions-accordion-header" style="cursor: pointer; background: #f0f0f1; padding: 10px; border-left: 4px solid #2271b1;" 
                    onclick="
                        var table = document.getElementById('sessions-table');
                        var header = document.getElementById('sessions-accordion-header');
                        if (table.style.display === 'none') {
                            table.style.display = 'table';
                            header.innerHTML = '▼ Incomplete Sessions (<?php echo count($sessions); ?>)';
                        } else {
                            table.style.display = 'none';
                            header.innerHTML = '► Incomplete Sessions (<?php echo count($sessions); ?>)';
                        }
                    ">
                    ▼ Incomplete Sessions (<?php echo count($sessions); ?>)
                </h2>
                
                <table id="sessions-table" class="wp-list-table widefat fixed striped" style="margin-top: 10px; display: table;">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Kontaktpersona</th>
                            <th>E-pasts</th>
                            <th>Datums</th>
                            <th>Darbības</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td>
                                <span style="background-color: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 3px; font-size: 0.9em; font-weight: 600;">⚠ Incomplete Session</span>
                            </td>
                            <td><?php echo esc_html(($session['first_name'] . ' ' . $session['last_name']) ?: '(nav norādīts)'); ?></td>
                            <td><?php echo esc_html($session['email'] ?: '(nav norādīts)'); ?></td>
                            <td><?php echo esc_html($session['date']); ?></td>
                            <td>
                                <a href="?page=korboc-survey-responses&action=view&session_id=<?php echo esc_attr($session['session_id']); ?>" class="button" style="display: block; margin-bottom: 5px;">Skatīt Atbildes</a>
                                <a href="?page=korboc-survey-responses&action=delete_session&session_id=<?php echo esc_attr($session['session_id']); ?>&_wpnonce=<?php echo wp_create_nonce('delete_session_' . $session['session_id']); ?>" 
                                   class="button button-secondary" 
                                   style="display: block;"
                                   onclick="return confirm('Vai tiešām dzēst šo incomplete session? Šo darbību nevar atsaukt.');">
                                    Delete Session
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * Get all survey responses (completed AND incomplete)
     * v9.2.3: Get ALL users, filter to those with any survey data
     */
    private function get_all_responses($orderby = 'date', $order = 'desc') {
        global $wpdb;
        
        $responses = array();
        
        // ============================================
        // SOURCE 1: Users with accounts
        // ============================================
        
        // Get user IDs who have ANY korboc meta key
        $user_ids = $wpdb->get_col("
            SELECT DISTINCT user_id 
            FROM {$wpdb->usermeta} 
            WHERE meta_key LIKE 'korboc_%'
        ");
        
        if (!empty($user_ids)) {
            $users = get_users(array('include' => $user_ids));
            
            foreach ($users as $user) {
                // v9.2.8: Filter - only include users who have started survey
                $survey_started = get_user_meta($user->ID, 'korboc_survey_started', true);
                $survey_completed = get_user_meta($user->ID, 'korboc_survey_completed', true);
                
                // Skip users without survey_started (not survey participants)
                if (empty($survey_started) && empty($survey_completed)) {
                    continue;
                }
                
                $important_features = get_user_meta($user->ID, 'korboc_important_features', true);
                if (!is_array($important_features)) {
                    $important_features = array();
                }
                $vision_opinion = get_user_meta($user->ID, 'korboc_vision_opinion', true);
                if (!is_array($vision_opinion)) {
                    $vision_opinion = array();
                }
                $pricing_opinion = get_user_meta($user->ID, 'korboc_pricing_opinion', true);
                if (!is_array($pricing_opinion)) {
                    $pricing_opinion = array();
                }
                
                // v9.2.8: Check completion by timestamp (backward compatible with 'yes')
                $is_complete = !empty($survey_completed);

                // Get submission history
                $previous_submissions = get_user_meta($user->ID, 'korboc_survey_submissions', true);
                if (!is_array($previous_submissions)) {
                    $previous_submissions = array();
                }
                $submission_count = count($previous_submissions) + 1; // +1 for current submission

                $responses[] = array(
                    'source' => 'user',  // Mark as user data
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'is_complete' => $is_complete,
                    'survey_started' => $survey_started,
                    'survey_completed' => $survey_completed,
                    'submission_count' => $submission_count,
                    'previous_submissions' => $previous_submissions,
                    // Profile Basics
                    'first_name' => get_user_meta($user->ID, 'korboc_first_name', true),
                    'last_name' => get_user_meta($user->ID, 'korboc_last_name', true),
                    'how_to_address' => get_user_meta($user->ID, 'korboc_how_to_address', true),
                    'choir_name' => get_user_meta($user->ID, 'korboc_choir_name', true),
                    'rehearsal_location' => get_user_meta($user->ID, 'korboc_rehearsal_location', true),
                    'role_in_choir' => get_user_meta($user->ID, 'korboc_role_in_choir', true),
                    'voice_part' => get_user_meta($user->ID, 'korboc_voice_part', true),
                    'is_soloist' => get_user_meta($user->ID, 'korboc_is_soloist', true),
                    'soloist_repertoire' => get_user_meta($user->ID, 'korboc_soloist_repertoire', true),
                    'email' => $user->user_email,
                    'phone' => get_user_meta($user->ID, 'korboc_phone', true),
                    'singer_count' => get_user_meta($user->ID, 'korboc_singer_count', true),
                    'song_festival_memory' => get_user_meta($user->ID, 'korboc_song_festival_memory', true),
                    // Survey Questions
                    'important_features' => $important_features,
                    'important_features_other' => get_user_meta($user->ID, 'korboc_important_features_other', true),
                    'additional_valuable' => get_user_meta($user->ID, 'korboc_additional_valuable', true),
                    'vision_opinion' => $vision_opinion,
                    'vision_opinion_other' => get_user_meta($user->ID, 'korboc_vision_opinion_other', true),
                    'pricing_opinion' => $pricing_opinion,
                    'pricing_opinion_other' => get_user_meta($user->ID, 'korboc_pricing_opinion_other', true),
                    'communication_thoughts' => get_user_meta($user->ID, 'korboc_communication_thoughts', true),
                    'gdpr_consent' => get_user_meta($user->ID, 'korboc_gdpr_consent', true),
                    'date' => get_user_meta($user->ID, 'korboc_survey_date', true),
                );
            }
        }
        
        // ============================================
        // SOURCE 2: Anonymous sessions
        // ============================================
        
        $sessions = $wpdb->get_results("
            SELECT * 
            FROM {$wpdb->prefix}korboc_survey_sessions 
            WHERE (user_id IS NULL OR user_id = 0)
            ORDER BY updated_at DESC
        ");
        
        foreach ($sessions as $session) {
            $field_data = json_decode($session->field_data, true);
            
            // Skip if JSON decode failed
            if (!is_array($field_data)) {
                continue;
            }
            
            // Build response array matching user format
            $responses[] = array(
                'source' => 'session',
                'session_id' => $session->session_id,
                'user_id' => null,
                'user_login' => 'session_' . substr($session->session_id, 0, 8),
                'is_complete' => false,
                'submission_count' => 1,
                'previous_submissions' => array(),
                // Extract fields from JSON
                'first_name' => $field_data['first_name'] ?? '',
                'last_name' => $field_data['last_name'] ?? '',
                'how_to_address' => $field_data['how_to_address'] ?? '',
                'choir_name' => $field_data['choir_name'] ?? '',
                'rehearsal_location' => $field_data['rehearsal_location'] ?? '',
                'role_in_choir' => $field_data['role_in_choir'] ?? '',
                'voice_part' => $field_data['voice_part'] ?? '',
                'is_soloist' => $field_data['is_soloist'] ?? '',
                'soloist_repertoire' => $field_data['soloist_repertoire'] ?? '',
                'email' => $session->email ?: ($field_data['email'] ?? ''),
                'phone' => $field_data['phone'] ?? '',
                'singer_count' => $field_data['singer_count'] ?? '',
                'song_festival_memory' => $field_data['song_festival_memory'] ?? '',
                'important_features' => $field_data['important_features'] ?? array(),
                'important_features_other' => $field_data['important_features_other'] ?? '',
                'additional_valuable' => $field_data['additional_valuable'] ?? '',
                'vision_opinion' => $field_data['vision_opinion'] ?? array(),
                'vision_opinion_other' => $field_data['vision_opinion_other'] ?? '',
                'pricing_opinion' => $field_data['pricing_opinion'] ?? array(),
                'pricing_opinion_other' => $field_data['pricing_opinion_other'] ?? '',
                'communication_thoughts' => $field_data['communication_thoughts'] ?? '',
                'gdpr_consent' => $field_data['gdpr_consent'] ?? '',
                'date' => $session->updated_at,
            );
        }

        // Sort responses
        usort($responses, function($a, $b) use ($orderby, $order) {
            $result = 0;

            switch ($orderby) {
                case 'status':
                    $result = ($a['is_complete'] ? 1 : 0) - ($b['is_complete'] ? 1 : 0);
                    break;
                case 'source':
                    $result = strcasecmp($a['source'], $b['source']);
                    break;
                case 'song_festival_memory':
                    $result = strcasecmp($a['song_festival_memory'], $b['song_festival_memory']);
                    break;
                case 'vision_opinion':
                    $val_a = is_array($a['vision_opinion']) ? implode(' ', $a['vision_opinion']) : $a['vision_opinion'];
                    $val_b = is_array($b['vision_opinion']) ? implode(' ', $b['vision_opinion']) : $b['vision_opinion'];
                    $result = strcasecmp($val_a, $val_b);
                    break;
                case 'first_name':
                    $result = strcasecmp($a['first_name'], $b['first_name']);
                    break;
                case 'last_name':
                    $result = strcasecmp($a['last_name'], $b['last_name']);
                    break;
                case 'how_to_address':
                    $result = strcasecmp($a['how_to_address'], $b['how_to_address']);
                    break;
                case 'email':
                    $result = strcasecmp($a['email'], $b['email']);
                    break;
                case 'phone':
                    $result = strcasecmp($a['phone'], $b['phone']);
                    break;
                case 'choir_name':
                    $result = strcasecmp($a['choir_name'], $b['choir_name']);
                    break;
                case 'rehearsal_location':
                    $result = strcasecmp($a['rehearsal_location'], $b['rehearsal_location']);
                    break;
                case 'role_in_choir':
                    $result = strcasecmp($a['role_in_choir'], $b['role_in_choir']);
                    break;
                case 'voice_part':
                    $result = strcasecmp($a['voice_part'], $b['voice_part']);
                    break;
                case 'is_soloist':
                    $result = strcasecmp($a['is_soloist'], $b['is_soloist']);
                    break;
                case 'soloist_repertoire':
                    $result = strcasecmp($a['soloist_repertoire'], $b['soloist_repertoire']);
                    break;
                case 'singer_count':
                    $result = (int)$a['singer_count'] - (int)$b['singer_count'];
                    break;
                case 'additional_valuable':
                    $result = strcasecmp($a['additional_valuable'], $b['additional_valuable']);
                    break;
                case 'pricing_opinion':
                    $val_a = is_array($a['pricing_opinion']) ? implode(' ', $a['pricing_opinion']) : $a['pricing_opinion'];
                    $val_b = is_array($b['pricing_opinion']) ? implode(' ', $b['pricing_opinion']) : $b['pricing_opinion'];
                    $result = strcasecmp($val_a, $val_b);
                    break;
                case 'communication_thoughts':
                    $result = strcasecmp($a['communication_thoughts'], $b['communication_thoughts']);
                    break;
                case 'gdpr_consent':
                    $result = strcasecmp($a['gdpr_consent'], $b['gdpr_consent']);
                    break;
                case 'survey_started':
                    $result = strcmp($a['survey_started'] ?? '', $b['survey_started'] ?? '');
                    break;
                case 'survey_completed':
                    $result = strcmp($a['survey_completed'] ?? '', $b['survey_completed'] ?? '');
                    break;
                case 'name':
                    $name_a = $a['first_name'] . ' ' . $a['last_name'];
                    $name_b = $b['first_name'] . ' ' . $b['last_name'];
                    $result = strcasecmp($name_a, $name_b);
                    break;
                case 'date':
                default:
                    // v9.2.8: Sort by completed timestamp, fallback to started timestamp
                    $date_a = !empty($a['survey_completed']) ? $a['survey_completed'] : (!empty($a['survey_started']) ? $a['survey_started'] : $a['date']);
                    $date_b = !empty($b['survey_completed']) ? $b['survey_completed'] : (!empty($b['survey_started']) ? $b['survey_started'] : $b['date']);
                    $result = strcmp($date_a, $date_b);
                    break;
            }

            return $order === 'asc' ? $result : -$result;
        });

        return $responses;
    }
    
    /**
     * Export responses to CSV
     */
    private function export_to_csv() {
        $responses = $this->get_all_responses();
        
        // Set headers for download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=korboc-aptauja-' . date('Y') . '-' . date('m') . 'm-' . date('d') . 'd-' . date('H-i-s') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Headers
        fputcsv($output, array(
            'Vārds',
            'Uzvārds',
            'Kā uzrunāt',
            'Kora nosaukums',
            'Mēģinājumu vieta',
            'Loma korī',
            'Balss',
            'Solists/e',
            'Solistu repertuārs',
            'E-pasts',
            'Tālrunis',
            'Dziedātāju skaits',
            'Dziesmu svētku atmiņa',
            'Galvenā atbildes (1.J)',
            'Papildu funkcijas (2.J)',
            'Vīzija par dronu šovu (3.J)',
            'Cenas vērtējums (4.J)',
            'Komunikācijas domas (5.J)',
            'GDPR piekrišana',
            'Datums'
        ));
        
        // Data - export ALL submissions (current + previous)
        foreach ($responses as $response) {
            // Export latest submission
            fputcsv($output, array(
                $response['first_name'],
                $response['last_name'],
                $response['how_to_address'],
                $response['choir_name'],
                $response['rehearsal_location'],
                $response['role_in_choir'],
                $response['voice_part'],
                $response['is_soloist'],
                $response['soloist_repertoire'],
                $response['email'],
                $response['phone'],
                $response['singer_count'],
                $response['song_festival_memory'],
                is_array($response['important_features']) ? implode('; ', $response['important_features']) : '' . ($response['important_features_other'] ? ' | Cits: ' . $response['important_features_other'] : ''),
                $response['additional_valuable'],
                is_array($response['vision_opinion']) ? implode('; ', $response['vision_opinion']) : '' . ($response['vision_opinion_other'] ? ' | Cits: ' . $response['vision_opinion_other'] : ''),
                is_array($response['pricing_opinion']) ? implode('; ', $response['pricing_opinion']) : '' . ($response['pricing_opinion_other'] ? ' | Cits: ' . $response['pricing_opinion_other'] : ''),
                $response['communication_thoughts'],
                $response['gdpr_consent'],
                $response['date']
            ));

            // Export previous submissions (if any)
            if (!empty($response['previous_submissions']) && is_array($response['previous_submissions'])) {
                foreach ($response['previous_submissions'] as $prev) {
                    $prev_data = $prev['data'];
                    $prev_timestamp = isset($prev['timestamp']) ? $prev['timestamp'] : '';

                    fputcsv($output, array(
                        isset($prev_data['first_name']) ? $prev_data['first_name'] : '',
                        isset($prev_data['last_name']) ? $prev_data['last_name'] : '',
                        isset($prev_data['how_to_address']) ? $prev_data['how_to_address'] : '',
                        isset($prev_data['choir_name']) ? $prev_data['choir_name'] : '',
                        isset($prev_data['rehearsal_location']) ? $prev_data['rehearsal_location'] : '',
                        isset($prev_data['role_in_choir']) ? $prev_data['role_in_choir'] : '',
                        isset($prev_data['voice_part']) ? $prev_data['voice_part'] : '',
                        isset($prev_data['is_soloist']) ? $prev_data['is_soloist'] : '',
                        isset($prev_data['soloist_repertoire']) ? $prev_data['soloist_repertoire'] : '',
                        $response['email'], // Email from user object
                        isset($prev_data['phone']) ? $prev_data['phone'] : '',
                        isset($prev_data['singer_count']) ? $prev_data['singer_count'] : '',
                        isset($prev_data['song_festival_memory']) ? $prev_data['song_festival_memory'] : '',
                        isset($prev_data['important_features']) && is_array($prev_data['important_features']) ? implode('; ', $prev_data['important_features']) : '' . (isset($prev_data['important_features_other']) && $prev_data['important_features_other'] ? ' | Cits: ' . $prev_data['important_features_other'] : ''),
                        isset($prev_data['additional_valuable']) ? $prev_data['additional_valuable'] : '',
                        isset($prev_data['vision_opinion']) && is_array($prev_data['vision_opinion']) ? implode('; ', $prev_data['vision_opinion']) : '' . (isset($prev_data['vision_opinion_other']) && $prev_data['vision_opinion_other'] ? ' | Cits: ' . $prev_data['vision_opinion_other'] : ''),
                        isset($prev_data['pricing_opinion']) && is_array($prev_data['pricing_opinion']) ? implode('; ', $prev_data['pricing_opinion']) : '' . (isset($prev_data['pricing_opinion_other']) && $prev_data['pricing_opinion_other'] ? ' | Cits: ' . $prev_data['pricing_opinion_other'] : ''),
                        isset($prev_data['communication_thoughts']) ? $prev_data['communication_thoughts'] : '',
                        '', // GDPR not stored in previous
                        $prev_timestamp
                    ));
                }
            }
        }
        
        fclose($output);
    }
    
    /**
     * Render single response view (for both users and sessions)
     */
    private function render_single_response() {
        // Check if viewing session or user
        if (isset($_GET['session_id'])) {
            $this->render_session_response();
            return;
        }
        
        // User response view - this already exists in the inline expandable section
        // Redirect back to list view
        wp_redirect(admin_url('users.php?page=korboc-survey-responses'));
        exit;
    }
    
    /**
     * Render single session response
     */
    private function render_session_response() {
        $session_id = sanitize_text_field($_GET['session_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'korboc_survey_sessions';
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s",
            $session_id
        ));
        
        if (!$session) {
            echo '<div class="wrap"><p>Session not found</p></div>';
            return;
        }
        
        $field_data = json_decode($session->field_data, true);
        if (!is_array($field_data)) {
            echo '<div class="wrap"><p>Invalid session data</p></div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>Incomplete Session Details</h1>
            <p><a href="?page=korboc-survey-responses">← Atpakaļ uz sarakstu</a></p>
            
            <table class="form-table">
                <tr><th colspan="2"><h2>Profils</h2></th></tr>
                <tr><th>Vārds:</th><td><?php echo esc_html($field_data['first_name'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Uzvārds:</th><td><?php echo esc_html($field_data['last_name'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Kā uzrunāt:</th><td><?php echo esc_html($field_data['how_to_address'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>E-pasts:</th><td><?php echo esc_html($session->email ?: ($field_data['email'] ?? '(nav norādīts)')); ?></td></tr>
                <tr><th>Telefons:</th><td><?php echo esc_html($field_data['phone'] ?? '(nav norādīts)'); ?></td></tr>
                
                <tr><th colspan="2"><h2>Par Kori</h2></th></tr>
                <tr><th>Kora nosaukums:</th><td><?php echo esc_html($field_data['choir_name'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Mēģinājumu vieta:</th><td><?php echo esc_html($field_data['rehearsal_location'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Loma korī:</th><td><?php echo esc_html($field_data['role_in_choir'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Balss daļa:</th><td><?php echo esc_html($field_data['voice_part'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Solists:</th><td><?php echo esc_html($field_data['is_soloist'] ?? '(nav norādīts)'); ?></td></tr>
                <tr><th>Solistu repertuārs:</th><td><?php echo nl2br(esc_html($field_data['soloist_repertoire'] ?? '(nav norādīts)')); ?></td></tr>
                <tr><th>Dziedātāju skaits:</th><td><?php echo esc_html($field_data['singer_count'] ?? '(nav norādīts)'); ?></td></tr>
                
                <tr><th colspan="2"><h2>Aptaujas Atbildes</h2></th></tr>
                <tr><th>Dziesmu svētku atmiņa:</th><td><?php echo nl2br(esc_html($field_data['song_festival_memory'] ?? '(nav norādīts)')); ?></td></tr>
                <tr><th>Vision opinion:</th><td>
                    <?php 
                    if (!empty($field_data['vision_opinion']) && is_array($field_data['vision_opinion'])) {
                        echo implode(', ', array_map('esc_html', $field_data['vision_opinion']));
                        if (!empty($field_data['vision_opinion_other'])) {
                            echo '<br>Cits: ' . esc_html($field_data['vision_opinion_other']);
                        }
                    } else {
                        echo '(nav norādīts)';
                    }
                    ?>
                </td></tr>
                <tr><th>Additional valuable:</th><td><?php echo nl2br(esc_html($field_data['additional_valuable'] ?? '(nav norādīts)')); ?></td></tr>
                <tr><th>Pricing opinion:</th><td>
                    <?php 
                    if (!empty($field_data['pricing_opinion']) && is_array($field_data['pricing_opinion'])) {
                        echo implode(', ', array_map('esc_html', $field_data['pricing_opinion']));
                        if (!empty($field_data['pricing_opinion_other'])) {
                            echo '<br>Cits: ' . esc_html($field_data['pricing_opinion_other']);
                        }
                    } else {
                        echo '(nav norādīts)';
                    }
                    ?>
                </td></tr>
                <tr><th>Communication thoughts:</th><td><?php echo nl2br(esc_html($field_data['communication_thoughts'] ?? '(nav norādīts)')); ?></td></tr>
                
                <tr><th colspan="2"><h2>Session Info</h2></th></tr>
                <tr><th>Session ID:</th><td><code><?php echo esc_html($session->session_id); ?></code></td></tr>
                <tr><th>Created:</th><td><?php echo esc_html($session->created_at); ?></td></tr>
                <tr><th>Last updated:</th><td><?php echo esc_html($session->updated_at); ?></td></tr>
            </table>
            
            <p>
                <a href="?page=korboc-survey-responses&action=delete_session&session_id=<?php echo esc_attr($session_id); ?>&_wpnonce=<?php echo wp_create_nonce('delete_session_' . $session_id); ?>" 
                   class="button button-secondary"
                   onclick="return confirm('Vai tiešām dzēst šo incomplete session?');">
                    Delete This Session
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render table view with all fields visible
     */
    private function render_table_view() {
        // v9.2.8: Get sort parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'desc';
        
        $responses = $this->get_all_responses($orderby, $order);
        
        // v9.3.0: Helper function to format timestamp
        $format_timestamp = function($timestamp) {
            if (empty($timestamp) || $timestamp === 'yes') {
                return '';
            }
            // Format: 20251213171045_a7 → 2025-12-13 17:10:45
            if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $timestamp, $matches)) {
                return sprintf('%s-%s-%s %s:%s:%s', 
                    $matches[1], $matches[2], $matches[3], 
                    $matches[4], $matches[5], $matches[6]
                );
            }
            return $timestamp;
        };
        
        // v9.3.0: Helper function to create sortable link with 3-state sorting
        $sortable_link = function($column, $label) use ($orderby, $order) {
            // 3-state sorting: default → asc ▲ → desc ▼ → default
            if ($orderby === $column) {
                if ($order === 'asc') {
                    // Currently ascending, next click = descending
                    $new_order = 'desc';
                    $arrow = ' ▲';
                } else {
                    // Currently descending, next click = clear (back to default)
                    $url = add_query_arg(array(
                        'page' => 'korboc-survey-responses',
                        'view' => 'table'
                        // No orderby - returns to default sort
                    ), admin_url('users.php'));
                    return '<a href="' . esc_url($url) . '" style="color: inherit; text-decoration: none;">' . esc_html($label) . ' ▼</a>';
                }
            } else {
                // Not currently sorted by this column, next click = ascending
                $new_order = 'asc';
                $arrow = '';
            }
            
            $url = add_query_arg(array(
                'page' => 'korboc-survey-responses',
                'view' => 'table',
                'orderby' => $column,
                'order' => $new_order
            ), admin_url('users.php'));
            return '<a href="' . esc_url($url) . '" style="color: inherit; text-decoration: none;">' . esc_html($label) . $arrow . '</a>';
        };
        
        ?>
        <div class="wrap" style="max-width: 100%;">
            <h1>Korboc Survey - Table View</h1>
            <p>
                <a href="?page=korboc-survey-responses">← Atpakaļ uz parastā skata</a>
                <a href="<?php echo wp_nonce_url(add_query_arg('korboc_export', 'csv'), 'korboc_export_csv'); ?>" class="button" style="margin-left: 10px;">📊 Eksportēt CSV</a>
            </p>
            
            <div style="overflow-x: auto; overflow-y: auto; max-height: 80vh;">
                <table class="wp-list-table widefat striped" style="table-layout: fixed; width: 100%;">
                    <thead>
                        <tr>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 160px; white-space: nowrap;"><?php echo $sortable_link('status', 'Status'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 80px;"><?php echo $sortable_link('source', 'Source'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 350px;"><?php echo $sortable_link('song_festival_memory', 'Dziesmu svētku atmiņa'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 200px;"><?php echo $sortable_link('vision_opinion', 'Vision opinion'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 120px;"><?php echo $sortable_link('first_name', 'Vārds'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 120px;"><?php echo $sortable_link('last_name', 'Uzvārds'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 120px;"><?php echo $sortable_link('how_to_address', 'Kā uzrunāt'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 200px;"><?php echo $sortable_link('email', 'E-pasts'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 120px;"><?php echo $sortable_link('phone', 'Telefons'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 180px;"><?php echo $sortable_link('choir_name', 'Kora nosaukums'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 150px;"><?php echo $sortable_link('rehearsal_location', 'Mēģinājumu vieta'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 120px;"><?php echo $sortable_link('role_in_choir', 'Loma korī'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 100px;"><?php echo $sortable_link('voice_part', 'Balss daļa'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 80px;"><?php echo $sortable_link('is_soloist', 'Solists'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 250px;"><?php echo $sortable_link('soloist_repertoire', 'Solistu repertuārs'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 80px;"><?php echo $sortable_link('singer_count', 'Dziedātāji'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 350px;"><?php echo $sortable_link('additional_valuable', 'Additional valuable'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 200px;"><?php echo $sortable_link('pricing_opinion', 'Pricing opinion'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 350px;"><?php echo $sortable_link('communication_thoughts', 'Communication thoughts'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 80px;"><?php echo $sortable_link('gdpr_consent', 'GDPR'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 140px;"><?php echo $sortable_link('date', 'Datums'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 140px;"><?php echo $sortable_link('survey_started', 'Survey Started'); ?></th>
                            <th style="position: sticky; top: 0; background: #f0f0f1; z-index: 10; text-align: center; font-weight: bold; padding: 8px; width: 140px;"><?php echo $sortable_link('survey_completed', 'Survey Completed'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($responses as $response): ?>
                        <tr>
                            <td style="vertical-align: top; text-align: left; white-space: nowrap; padding: 8px;">
                                <?php if ($response['source'] === 'session'): ?>
                                    Incomplete Session
                                <?php elseif ($response['is_complete']): ?>
                                    ✓ Pabeigta
                                <?php else: ?>
                                    ⚠ Nepabeigta
                                <?php endif; ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['source']); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px; max-width: 400px;">
                                <?php echo esc_html($response['song_festival_memory'] ?: '(nav)'); ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;">
                                <?php 
                                if (!empty($response['vision_opinion']) && is_array($response['vision_opinion'])) {
                                    echo implode("\n", array_map('esc_html', $response['vision_opinion']));
                                    if (!empty($response['vision_opinion_other'])) {
                                        echo "\nCits: " . esc_html($response['vision_opinion_other']);
                                    }
                                } else {
                                    echo '(nav)';
                                }
                                ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['first_name'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['last_name'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['how_to_address'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['email'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['phone'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['choir_name'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['rehearsal_location'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['role_in_choir'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['voice_part'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['is_soloist'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px; max-width: 300px;">
                                <?php echo esc_html($response['soloist_repertoire'] ?: '(nav)'); ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['singer_count'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px; max-width: 400px;">
                                <?php echo esc_html($response['additional_valuable'] ?: '(nav)'); ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;">
                                <?php 
                                if (!empty($response['pricing_opinion']) && is_array($response['pricing_opinion'])) {
                                    echo implode("\n", array_map('esc_html', $response['pricing_opinion']));
                                    if (!empty($response['pricing_opinion_other'])) {
                                        echo "\nCits: " . esc_html($response['pricing_opinion_other']);
                                    }
                                } else {
                                    echo '(nav)';
                                }
                                ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px; max-width: 400px;">
                                <?php echo esc_html($response['communication_thoughts'] ?: '(nav)'); ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['gdpr_consent'] ?: '(nav)'); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;"><?php echo esc_html($response['date']); ?></td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;">
                                <?php echo esc_html($format_timestamp($response['survey_started'] ?? '') ?: '(nav)'); ?>
                            </td>
                            <td style="vertical-align: top; text-align: left; white-space: normal; padding: 8px;">
                                <?php echo esc_html($format_timestamp($response['survey_completed'] ?? '') ?: '(nav)'); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <p style="margin-top: 20px;">
                <strong><?php echo count($responses); ?> total responses</strong>
                (<?php echo count(array_filter($responses, function($r) { return $r['source'] === 'session'; })); ?> incomplete sessions)
            </p>
        </div>
        <?php
    }
}
