<?php
/**
 * FLOSC Flow Edit Page
 * 
 * v1.2.2: Create/edit a single flow
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
    'identity' => 'Identity',
    'ivr' => 'IVR',
    'content' => 'Content',
];
if ($is_admin && !$is_new) {
    $tabs['team'] = 'Team';
}

// Handle form submission
if (isset($_POST['flosc_save_flow']) && wp_verify_nonce($_POST['_wpnonce'], 'flosc_save_flow')) {

    // Save visitor profile bar settings (global settings) — only if posted from Identity tab
    // v1.8.0: Now writes to unified flosc_profile_bar option
    if (isset($_POST['visitor_bar_text']) || isset($_POST['visitor_bar_icon'])) {
        $profile_bar = get_option('flosc_profile_bar', []);
        if (isset($_POST['visitor_bar_text'])) {
            $profile_bar['visitor']['badge'] = sanitize_text_field($_POST['visitor_bar_text']);
        }
        if (isset($_POST['visitor_bar_icon'])) {
            $profile_bar['visitor']['icon'] = sanitize_text_field($_POST['visitor_bar_icon']);
        }
        update_option('flosc_profile_bar', $profile_bar);
    }

    // Save visitor menu items — preserve associative keys (signup, login, quiz)
    if (isset($_POST['visitor_menu_items']) && is_array($_POST['visitor_menu_items'])) {
        $visitor_menu_items = [];
        foreach ($_POST['visitor_menu_items'] as $key => $item) {
            $visitor_menu_items[$key] = [
                'label'   => sanitize_text_field($item['label'] ?? ''),
                'enabled' => isset($item['enabled']) && $item['enabled'] === '1',
            ];
        }
        update_option('flosc_visitor_menu_items', $visitor_menu_items);
    }

    $data = [
        'slug' => sanitize_title($_POST['flow_slug'] ?? ''),
        'custom_domain' => sanitize_text_field($_POST['flow_domain'] ?? ''),
        'status' => sanitize_key($_POST['flow_status'] ?? 'draft'),
        'product' => [
            'name' => sanitize_text_field($_POST['product_name'] ?? ''),
            'tagline' => sanitize_text_field($_POST['product_tagline'] ?? ''),
            'emoji' => sanitize_text_field($_POST['product_emoji'] ?? '🎯'),
            'logo_url' => esc_url_raw($_POST['product_logo'] ?? ''),
            'app_icon_url' => esc_url_raw($_POST['product_app_icon'] ?? ''),
            'primary_color' => sanitize_hex_color($_POST['product_color'] ?? '#4f46e5'),
            'share_text' => sanitize_text_field($_POST['product_share_text'] ?? ''),
        ],
        'ivr_file' => sanitize_file_name($_POST['ivr_file'] ?? 'flosc_default_ivr.md'),
        'wp_category_id' => intval($_POST['wp_category'] ?? 0),
        'quiz_type' => sanitize_key($_POST['quiz_type'] ?? ''),
    ];
    
    if ($is_new) {
        // Generate ID from slug or random
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
            // Refresh flow data
            $flow = flosc_flows()->get_flow($flow_id);
            $success_message = 'Flow updated successfully.';
        }
    }
}

// Handle team updates (admin only)
if (isset($_POST['flosc_update_team']) && $is_admin && !$is_new && wp_verify_nonce($_POST['_wpnonce'], 'flosc_update_team')) {
    $selected_users = isset($_POST['team_users']) ? array_map('intval', $_POST['team_users']) : [];
    
    // Get all users who currently have access
    $current_users = flosc_flows()->get_flow_users($flow_id);
    $current_user_ids = array_map(function($u) { return $u->ID; }, $current_users);
    
    // Revoke from users no longer selected
    foreach ($current_user_ids as $uid) {
        if (!in_array($uid, $selected_users)) {
            flosc_flows()->revoke_flow_access($uid, $flow_id);
        }
    }
    
    // Grant to newly selected users
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
?>

<div class="wrap">
    <h1>
        <a href="<?php echo admin_url('admin.php?page=flosc-flows'); ?>">← Flows</a>
        &nbsp;/&nbsp;
        <?php echo $is_new ? 'New Flow' : esc_html($flow['product']['name'] ?? $flow_id); ?>
    </h1>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!$is_new): ?>
        <!-- Tabs -->
        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $tab_id => $tab_label): ?>
                <a href="<?php echo admin_url('admin.php?page=flosc-flow-edit&flow_id=' . urlencode($flow_id) . '&tab=' . $tab_id); ?>" 
                   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    
    <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
        
        <?php if ($current_tab === 'identity' || $is_new): ?>
            <!-- IDENTITY TAB -->
            <form method="post">
                <?php wp_nonce_field('flosc_save_flow'); ?>
                
                <h2>Flow Identity</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" id="flow_slug" name="flow_slug" 
                                   value="<?php echo esc_attr($flow['slug'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="myapp"
                                   pattern="[a-z0-9-]+"
                                   style="width: 150px;">
                            <code>/</code>
                            <p class="description">Lowercase letters, numbers, and hyphens only. Leave empty if using custom domain only.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" 
                                   value="<?php echo esc_attr($flow['custom_domain'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="myapp.com">
                            <p class="description">Optional. Point your domain's DNS to this server, then enter it here.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($flow['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flow['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                            </select>
                            <p class="description">Draft flows are not accessible to users.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 style="margin-top: 30px;">Branding</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="product_name">App Name</label></th>
                        <td>
                            <input type="text" id="product_name" name="product_name" 
                                   value="<?php echo esc_attr($flow['product']['name'] ?? ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="product_tagline" name="product_tagline" 
                                   value="<?php echo esc_attr($flow['product']['tagline'] ?? ''); ?>" 
                                   class="large-text" 
                                   placeholder="Your AI-powered learning companion">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_emoji">Emoji</label></th>
                        <td>
                            <input type="text" id="product_emoji" name="product_emoji" 
                                   value="<?php echo esc_attr($flow['product']['emoji'] ?? '🎯'); ?>" 
                                   style="width: 60px; font-size: 24px; text-align: center;">
                            <p class="description">Shown in browser tab and headers.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_color">Primary Color</label></th>
                        <td>
                            <input type="color" id="product_color" name="product_color" 
                                   value="<?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>" 
                                   style="width: 60px; height: 40px; padding: 0; border: 1px solid #ccc;">
                            <input type="text" id="product_color_hex" 
                                   value="<?php echo esc_attr($flow['product']['primary_color'] ?? '#4f46e5'); ?>" 
                                   style="width: 80px; margin-left: 8px;"
                                   onchange="document.getElementById('product_color').value = this.value;">
                            <script>
                                document.getElementById('product_color').addEventListener('input', function() {
                                    document.getElementById('product_color_hex').value = this.value;
                                });
                            </script>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_logo">Logo URL</label></th>
                        <td>
                            <input type="url" id="product_logo" name="product_logo" 
                                   value="<?php echo esc_attr($flow['product']['logo_url'] ?? ''); ?>" 
                                   class="large-text" 
                                   placeholder="https://...">
                            <p class="description">Optional. Used instead of emoji if provided.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_app_icon">App Icon</label></th>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <input type="url" id="product_app_icon" name="product_app_icon" 
                                       value="<?php echo esc_attr($flow['product']['app_icon_url'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_app_icon">Upload</button>
                                <img src="<?php echo esc_url($flow['product']['app_icon_url'] ?? ''); ?>" 
                                     alt="" id="flosc_app_icon_preview"
                                     style="width: 32px; height: 32px; border-radius: 6px;<?php echo empty($flow['product']['app_icon_url']) ? ' display: none;' : ''; ?>">
                            </div>
                            <p class="description">Used as favicon, empty-state icon, and AI avatar. Square PNG recommended (512&times;512+). Falls back to Logo URL, then emoji.</p>
                            <?php wp_enqueue_media(); ?>
                            <script>
                            document.getElementById('flosc_upload_app_icon')?.addEventListener('click', function(e) {
                                e.preventDefault();
                                var frame = wp.media({ title: 'Choose App Icon', multiple: false, library: { type: 'image' } });
                                frame.on('select', function() {
                                    var url = frame.state().get('selection').first().toJSON().url;
                                    document.getElementById('product_app_icon').value = url;
                                    var preview = document.getElementById('flosc_app_icon_preview');
                                    preview.src = url;
                                    preview.style.display = '';
                                });
                                frame.open();
                            });
                            </script>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="product_share_text">Share Text</label></th>
                        <td>
                            <input type="text" id="product_share_text" name="product_share_text"
                                   value="<?php echo esc_attr($flow['product']['share_text'] ?? ''); ?>"
                                   class="large-text"
                                   placeholder="Check out this amazing app!">
                        </td>
                    </tr>
                </table>

                <h2 style="margin-top: 30px;">Visitor Profile Bar</h2>
                <p class="description">Configure the profile bar shown to non-logged-in visitors (bottom left of sidebar). Full profile bar settings available at <strong>FLOSC → UI &amp; Navigation</strong>.</p>

                <?php $pb = get_option('flosc_profile_bar', []); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="visitor_bar_icon">Profile Icon</label></th>
                        <td>
                            <input type="text" id="visitor_bar_icon" name="visitor_bar_icon"
                                   value="<?php echo esc_attr($pb['visitor']['icon'] ?? '👋'); ?>"
                                   style="width: 60px; font-size: 24px; text-align: center;">
                            <p class="description">Emoji shown in visitor profile avatar.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="visitor_bar_text">Profile Subtitle</label></th>
                        <td>
                            <input type="text" id="visitor_bar_text" name="visitor_bar_text"
                                   value="<?php echo esc_attr($pb['visitor']['badge'] ?? 'Sign in to get started'); ?>"
                                   class="large-text">
                            <p class="description">Text shown below "Visitor" in the profile bar.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Menu Items</th>
                        <td>
                            <p class="description" style="margin-bottom: 10px;">Configure what appears in the visitor dropdown menu (checked items are shown):</p>
                            <?php
                            $visitor_menu = get_option('flosc_visitor_menu_items', [
                                'signup' => ['label' => 'Sign Up', 'enabled' => true],
                                'login'  => ['label' => 'Log In',  'enabled' => true],
                                'quiz'   => ['label' => 'Take Quiz','enabled' => true],
                            ]);

                            // Legacy migration: convert old indexed format to associative
                            if (is_array($visitor_menu) && !empty($visitor_menu) &&
                                is_numeric(key($visitor_menu)) &&
                                isset($visitor_menu[0]['action'])) {
                                $legacy = $visitor_menu;
                                $visitor_menu = [];
                                foreach ($legacy as $item) {
                                    $action = $item['action'] ?? '';
                                    if ($action) {
                                        $visitor_menu[$action] = [
                                            'label'   => $item['label'] ?? '',
                                            'enabled' => (bool) ($item['enabled'] ?? false),
                                        ];
                                    }
                                }
                                update_option('flosc_visitor_menu_items', $visitor_menu);
                            }

                            foreach ($visitor_menu as $key => $item):
                            ?>
                            <label style="display: block; margin-bottom: 8px;">
                                <input type="checkbox" name="visitor_menu_items[<?php echo esc_attr($key); ?>][enabled]" value="1"
                                       <?php checked($item['enabled'] ?? true, true); ?>>
                                <input type="text" name="visitor_menu_items[<?php echo esc_attr($key); ?>][label]"
                                       value="<?php echo esc_attr($item['label'] ?? ''); ?>"
                                       style="width: 200px; margin-left: 8px;">
                                <code style="margin-left: 8px; background: #f0f0f1; padding: 2px 6px; border-radius: 2px;"><?php echo esc_html($key); ?></code>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="flosc_save_flow" class="button button-primary" value="<?php echo $is_new ? 'Create Flow' : 'Save Changes'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=flosc-flows'); ?>" class="button">Cancel</a>
                </p>
            </form>
            
        <?php elseif ($current_tab === 'ivr'): ?>
            <!-- IVR TAB -->
            <form method="post">
                <?php wp_nonce_field('flosc_save_flow'); ?>
                
                <h2>IVR Configuration</h2>
                <p class="description">Select the IVR file that contains this flow's conversation messages.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ivr_file">IVR File</label></th>
                        <td>
                            <select id="ivr_file" name="ivr_file" class="regular-text">
                                <?php foreach ($ivr_files as $file): ?>
                                    <option value="<?php echo esc_attr($file); ?>" <?php selected($flow['ivr_file'] ?? 'flosc_default_ivr.md', $file); ?>>
                                        <?php echo esc_html($file); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Files are located in <code>ai_configuration_files/</code><br>
                                Use naming convention: <code>{flowname}_ivr.md</code> (e.g., <code>lesaep_ivr.md</code>)
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
                </p>
                
                <hr style="margin: 30px 0;">
                
                <h3>Edit IVR Messages</h3>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ivr-messages'); ?>" class="button">
                        Open IVR Message Editor →
                    </a>
                </p>
                <p class="description">The IVR editor currently edits the global IVR messages. In a future version, it will be flow-aware.</p>
            </form>
            
        <?php elseif ($current_tab === 'content'): ?>
            <!-- CONTENT TAB -->
            <form method="post">
                <?php wp_nonce_field('flosc_save_flow'); ?>
                
                <h2>Content Configuration</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wp_category">Lessons Category</label></th>
                        <td>
                            <select id="wp_category" name="wp_category" class="regular-text">
                                <option value="0">— All Categories —</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($flow['wp_category_id'] ?? 0, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> posts)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Only lessons in this category will be available in this flow.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="quiz_type">Quiz Type</label></th>
                        <td>
                            <select id="quiz_type" name="quiz_type" class="regular-text">
                                <?php foreach ($quiz_types as $type_id => $type_label): ?>
                                    <option value="<?php echo esc_attr($type_id); ?>" <?php selected($flow['quiz_type'] ?? '', $type_id); ?>>
                                        <?php echo esc_html($type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">The type of quiz used for the FLOSC funnel in this flow.</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
                </p>
            </form>
            
        <?php elseif ($current_tab === 'team' && $is_admin): ?>
            <!-- TEAM TAB (Admin only) -->
            <form method="post">
                <?php wp_nonce_field('flosc_update_team'); ?>
                
                <h2>Team Access</h2>
                <p class="description">Select which Editors and Authors can manage this flow. Administrators always have access to all flows.</p>
                
                <?php
                // Get all editors and authors
                $team_users = get_users([
                    'role__in' => ['editor', 'author', 'contributor'],
                    'orderby' => 'display_name',
                ]);
                
                // Get users who currently have access
                $current_team = flosc_flows()->get_flow_users($flow_id);
                $current_team_ids = array_map(function($u) { return $u->ID; }, $current_team);
                ?>
                
                <?php if (empty($team_users)): ?>
                    <p><em>No editors, authors, or contributors found. Only administrators can currently manage flows.</em></p>
                <?php else: ?>
                    <table class="widefat" style="max-width: 600px;">
                        <thead>
                            <tr>
                                <th style="width: 40px;">Access</th>
                                <th>User</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team_users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="team_users[]" value="<?php echo $user->ID; ?>"
                                               <?php checked(in_array($user->ID, $current_team_ids)); ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($user->display_name); ?></strong><br>
                                        <small><?php echo esc_html($user->user_email); ?></small>
                                    </td>
                                    <td><?php echo esc_html(implode(', ', $user->roles)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="flosc_update_team" class="button button-primary" value="Update Team">
                    </p>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        
    </div>
</div>
