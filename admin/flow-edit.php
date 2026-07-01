<?php
/**
 * FLOSC Flow Edit Page
 * 
 * v1.2.2: Create/edit a single flow
 */

if (!defined('ABSPATH')) exit;

$flosc_is_admin = current_user_can('manage_options');
$flosc_flow_id = sanitize_key(wp_unslash($_GET['flow_id'] ?? ''));
$flosc_is_new = ($flosc_flow_id === 'new');
$flosc_flow = $flosc_is_new ? null : flosc_flows()->get_flow($flosc_flow_id);

// Permission check
if (!$flosc_is_new && $flosc_flow && !flosc_flows()->can_access_flow_admin($flosc_flow_id)) {
    wp_die('You do not have permission to edit this flow.');
}

// Only admins can create new flows
if ($flosc_is_new && !$flosc_is_admin) {
    wp_die('Only administrators can create new flows.');
}

// Redirect if flow not found
if (!$flosc_is_new && !$flosc_flow) {
    wp_safe_redirect(admin_url('admin.php?page=flosc-flows&error=not_found'));
    exit;
}

// Get current tab
$flosc_current_tab = sanitize_key(wp_unslash($_GET['tab'] ?? 'identity'));
$tabs = [
    'identity' => 'Identity',
    'ivr' => 'IVR',
    'content' => 'Content',
];
if ($flosc_is_admin && !$flosc_is_new) {
    $tabs['team'] = 'Team';
}

// Handle form submission
if (isset($_POST['flosc_save_flow']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'flosc_save_flow')) {

    // Save visitor profile bar settings (global settings) — only if posted from Identity tab
    // v1.8.0: Now writes to unified flosc_profile_bar option
    if (isset($_POST['visitor_bar_text']) || isset($_POST['visitor_bar_icon'])) {
        $flosc_profile_bar = get_option('flosc_profile_bar', []);
        if (isset($_POST['visitor_bar_text'])) {
            $flosc_profile_bar['visitor']['badge'] = sanitize_text_field(wp_unslash($_POST['visitor_bar_text']));
        }
        if (isset($_POST['visitor_bar_icon'])) {
            $flosc_profile_bar['visitor']['icon'] = sanitize_text_field(wp_unslash($_POST['visitor_bar_icon']));
        }
        update_option('flosc_profile_bar', $flosc_profile_bar);
    }

    // Save visitor menu items — preserve associative keys (signup, login, quiz).
    // map_deep() sanitizes every leaf value at intake; the loop below shapes
    // the structure and applies the final per-field types.
    $flosc_visitor_menu_items_post = isset($_POST['visitor_menu_items']) ? map_deep(wp_unslash($_POST['visitor_menu_items']), 'sanitize_text_field') : null;
    if (is_array($flosc_visitor_menu_items_post)) {
        $flosc_visitor_menu_items = [];
        foreach ($flosc_visitor_menu_items_post as $flosc_key => $flosc_item) {
            $flosc_visitor_menu_items[$key] = [
                'label'   => sanitize_text_field($flosc_item['label'] ?? ''),
                'enabled' => isset($flosc_item['enabled']) && $flosc_item['enabled'] === '1',
            ];
        }
        update_option('flosc_visitor_menu_items', $flosc_visitor_menu_items);
    }

    $flosc_data = [
        'slug' => sanitize_title(wp_unslash($_POST['flow_slug'] ?? '')),
        'custom_domain' => sanitize_text_field(wp_unslash($_POST['flow_domain'] ?? '')),
        'status' => sanitize_key(wp_unslash($_POST['flow_status'] ?? 'draft')),
        'identity' => [
            'name' => sanitize_text_field(wp_unslash($_POST['floscflow_name'] ?? '')),
            'tagline' => sanitize_text_field(wp_unslash($_POST['floscflow_tagline'] ?? '')),
            'chatlogo_url' => esc_url_raw(wp_unslash($_POST['floscflow_chatlogo'] ?? '')),
            'favicon_url' => esc_url_raw(wp_unslash($_POST['floscflow_favicon'] ?? '')),
            'badgeUrl' => esc_url_raw(wp_unslash($_POST['floscflow_badge'] ?? '')),
            'primary_color' => sanitize_hex_color(wp_unslash($_POST['floscflow_color'] ?? '#4f46e5')),
            'share_text' => sanitize_text_field(wp_unslash($_POST['floscflow_share_text'] ?? '')),
        ],
        'ivr_file' => sanitize_file_name(wp_unslash($_POST['ivr_file'] ?? 'flosc_default_ivr.md')),
        'wp_category_id' => intval(wp_unslash($_POST['wp_category'] ?? 0)),
        'quiz_type' => sanitize_key(wp_unslash($_POST['quiz_type'] ?? '')),
    ];
    
    if ($flosc_is_new) {
        // Generate ID from slug or random
        $flosc_data['id'] = !empty($flosc_data['slug']) ? sanitize_key($flosc_data['slug']) : 'flow_' . wp_generate_password(6, false, false);
        $flosc_result = flosc_flows()->create_flow($flosc_data);
        
        if (is_wp_error($flosc_result)) {
            $flosc_error_message = $flosc_result->get_error_message();
        } else {
            wp_safe_redirect(admin_url('admin.php?page=flosc-flows&saved=1'));
            exit;
        }
    } else {
        $flosc_result = flosc_flows()->update_flow($flosc_flow_id, $flosc_data);
        
        if (is_wp_error($flosc_result)) {
            $flosc_error_message = $flosc_result->get_error_message();
        } else {
            // Refresh flow data
            $flosc_flow = flosc_flows()->get_flow($flosc_flow_id);
            $flosc_success_message = 'Flow updated successfully.';
        }
    }
}

// Handle team updates (admin only)
if (isset($_POST['flosc_update_team']) && $flosc_is_admin && !$flosc_is_new && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'flosc_update_team')) {
    // Sanitize at intake: every submitted value becomes an integer user ID.
    $flosc_selected_users = isset($_POST['team_users']) ? array_map('intval', (array) wp_unslash($_POST['team_users'])) : [];
    
    // Get all users who currently have access
    $flosc_current_users = flosc_flows()->get_flow_users($flosc_flow_id);
    $flosc_current_user_ids = array_map(function($u) { return $u->ID; }, $flosc_current_users);
    
    // Revoke from users no longer selected
    foreach ($flosc_current_user_ids as $flosc_uid) {
        if (!in_array($flosc_uid, $flosc_selected_users)) {
            flosc_flows()->revoke_flow_access($flosc_uid, $flosc_flow_id);
        }
    }
    
    // Grant to newly selected users
    foreach ($flosc_selected_users as $flosc_uid) {
        if (!in_array($flosc_uid, $flosc_current_user_ids)) {
            flosc_flows()->grant_flow_access($flosc_uid, $flosc_flow_id);
        }
    }
    
    $flosc_success_message = 'Team updated successfully.';
}

// Get available options
$flosc_ivr_files = flosc_flows()->get_available_ivr_files();
$flosc_quiz_types = flosc_flows()->get_available_quiz_types();
$flosc_categories = get_categories(['hide_empty' => false]);
?>

<div class="wrap">
    <h1>
        <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-flows') ); ?>">← Flows</a>
        &nbsp;/&nbsp;
        <?php echo $flosc_is_new ? 'New Flow' : esc_html(($flosc_flow['identity']['name'] ?? $flosc_flow_id)); ?>
    </h1>
    
    <?php if (isset($flosc_error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($flosc_error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($flosc_success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($flosc_success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (!$flosc_is_new): ?>
        <!-- Tabs -->
        <nav class="nav-tab-wrapper">
            <?php foreach ($tabs as $flosc_tab_id => $flosc_tab_label): ?>
                                         <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-flow-edit&flow_id=' . urlencode($flosc_flow_id) . '&tab=' . $tab_id) ); ?>" 
                                     class="nav-tab <?php echo $flosc_current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
    
    <div class="card flosc-flow-edit-card">
        
        <?php if ($flosc_current_tab === 'identity' || $flosc_is_new): ?>
            <!-- IDENTITY TAB -->
            <form method="post">
                <?php wp_nonce_field('flosc_save_flow'); ?>
                
                <h2>Flow Identity</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="flow_slug">URL Slug</label></th>
                        <td>
                            <code><?php echo esc_html( home_url('/') ); ?></code>
                            <input type="text" id="flow_slug" name="flow_slug" 
                                   value="<?php echo esc_attr($flosc_flow['slug'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="myapp"
                                   pattern="[a-z0-9-]+"
                                   >
                            <code>/</code>
                            <p class="description">Lowercase letters, numbers, and hyphens only. Leave empty if using custom domain only.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="flow_domain">Custom Domain</label></th>
                        <td>
                            <input type="text" id="flow_domain" name="flow_domain" 
                                   value="<?php echo esc_attr($flosc_flow['custom_domain'] ?? ''); ?>" 
                                   class="regular-text" 
                                   placeholder="myapp.com">
                            <p class="description">Optional. Point your domain's DNS to this server, then enter it here.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="flow_status">Status</label></th>
                        <td>
                            <select id="flow_status" name="flow_status">
                                <option value="active" <?php selected($flosc_flow['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($flosc_flow['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                            </select>
                            <p class="description">Draft flows are not accessible to users.</p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="flosc-flow-edit-section-title">Branding</h2>
                
                <?php $flosc_identity = $flosc_flow['identity'] ?? []; ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="floscflow_name">App Name</label></th>
                        <td>
                            <input type="text" id="floscflow_name" name="floscflow_name" 
                                   value="<?php echo esc_attr($flosc_identity['name'] ?? ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_tagline">Tagline</label></th>
                        <td>
                            <input type="text" id="floscflow_tagline" name="floscflow_tagline" 
                                   value="<?php echo esc_attr($flosc_identity['tagline'] ?? ''); ?>" 
                                   class="large-text" 
                                   placeholder="Your AI-powered learning companion">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_color">Brand Color</label></th>
                        <td>
                            <input type="color" id="floscflow_color" name="floscflow_color" 
                                   value="<?php echo esc_attr($flosc_identity['primary_color'] ?? '#4f46e5'); ?>" 
                                class="flosc-flow-edit-color-input">
                            <input type="text" id="floscflow_color_hex" 
                                   value="<?php echo esc_attr($flosc_identity['primary_color'] ?? '#4f46e5'); ?>" 
                                class="flosc-flow-edit-color-hex"
                                              data-flosc-action="sync-color-target"
                                              data-sync-target="floscflow_color">
                            <p class="description">Lesson highlights, form buttons, focus rings. Sets <code>--flosc-primary</code>.</p>
                            <?php ob_start(); ?>
                                document.getElementById('floscflow_color').addEventListener('input', function() {
                                    document.getElementById('floscflow_color_hex').value = this.value;
                                });
                            <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_chatlogo">Chat Logo URL</label></th>
                        <td>
                            <input type="url" id="floscflow_chatlogo" name="floscflow_chatlogo" 
                                   value="<?php echo esc_attr($flosc_identity['chatlogo_url'] ?? ''); ?>" 
                                   class="large-text" 
                                   placeholder="https://...">
                            <p class="description">Image shown in the landing state header beside the app name. Wider/rectangular formats welcome.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_favicon">Favicon</label></th>
                        <td>
                            <div class="flosc-flow-edit-favicon-row">
                                <input type="url" id="floscflow_favicon" name="floscflow_favicon" 
                                       value="<?php echo esc_attr($flosc_identity['favicon_url'] ?? ''); ?>" 
                                       class="regular-text" 
                                       placeholder="https://...">
                                <button type="button" class="button" id="flosc_upload_app_icon">Upload</button>
                                <?php $flosc_favicon_url = $flosc_identity['favicon_url'] ?? ''; ?>
                                <img src="<?php echo esc_url($flosc_favicon_url); ?>" 
                                     alt="" id="flosc_app_icon_preview"
                                  class="flosc-flow-edit-favicon-preview <?php echo empty($flosc_favicon_url) ? 'flosc-flow-edit-hidden' : ''; ?>">
                            </div>
                            <p class="description">Browser tab icon. Square PNG recommended (512&times;512+).</p>
                            <?php wp_enqueue_media(); ?>
                            <?php ob_start(); ?>
                            document.getElementById('flosc_upload_app_icon')?.addEventListener('click', function(e) {
                                e.preventDefault();
                                var frame = wp.media({ title: 'Choose App Icon', multiple: false, library: { type: 'image' } });
                                frame.on('select', function() {
                                    var url = frame.state().get('selection').first().toJSON().url;
                                    document.getElementById('floscflow_favicon').value = url;
                                    var preview = document.getElementById('flosc_app_icon_preview');
                                    preview.src = url;
                                    preview.style.display = '';
                                });
                                frame.open();
                            });
                            <?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_badge">Badge Image URL</label></th>
                        <td>
                            <input type="url" id="floscflow_badge" name="floscflow_badge" 
                                   value="<?php echo esc_attr($flosc_identity['badgeUrl'] ?? ''); ?>" 
                                   class="large-text" 
                                   placeholder="https://...">
                            <p class="description">Badge shown in the AI welcome message. Leave empty for no badge.</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="floscflow_share_text">Share Text</label></th>
                        <td>
                            <input type="text" id="floscflow_share_text" name="floscflow_share_text"
                                   value="<?php echo esc_attr($flosc_identity['share_text'] ?? ''); ?>"
                                   class="large-text"
                                   placeholder="Check out this amazing app!">
                        </td>
                    </tr>
                </table>

                <h2 class="flosc-flow-edit-section-title">Visitor Profile Bar</h2>
                <p class="description">Configure the profile bar shown to non-logged-in visitors (bottom left of sidebar). Full profile bar settings available at <strong>FLOSC → UI &amp; Navigation</strong>.</p>

                <?php $flosc_pb = get_option('flosc_profile_bar', []); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="visitor_bar_icon">Profile Icon</label></th>
                        <td>
                            <input type="text" id="visitor_bar_icon" name="visitor_bar_icon"
                                   value="<?php echo esc_attr($flosc_pb['visitor']['icon'] ?? '👋'); ?>"
                                class="flosc-flow-edit-emoji-input">
                            <p class="description">Emoji shown in visitor profile bar.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="visitor_bar_text">Profile Subtitle</label></th>
                        <td>
                            <input type="text" id="visitor_bar_text" name="visitor_bar_text"
                                   value="<?php echo esc_attr($flosc_pb['visitor']['badge'] ?? 'Sign in to get started'); ?>"
                                   class="large-text">
                            <p class="description">Text shown below "Visitor" in the profile bar.</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Menu Items</th>
                        <td>
                            <p class="description flosc-flow-edit-menu-help">Configure what appears in the visitor dropdown menu (checked items are shown):</p>
                            <?php
                            $flosc_visitor_menu = get_option('flosc_visitor_menu_items', [
                                'signup' => ['label' => 'Sign Up', 'enabled' => true],
                                'login'  => ['label' => 'Log In',  'enabled' => true],
                                'quiz'   => ['label' => 'Take Quiz','enabled' => true],
                            ]);

                            // Legacy migration: convert old indexed format to associative
                            if (is_array($flosc_visitor_menu) && !empty($flosc_visitor_menu) &&
                                is_numeric(key($flosc_visitor_menu)) &&
                                isset($flosc_visitor_menu[0]['action'])) {
                                $flosc_legacy = $flosc_visitor_menu;
                                $flosc_visitor_menu = [];
                                foreach ($flosc_legacy as $flosc_item) {
                                    $action = $flosc_item['action'] ?? '';
                                    if ($action) {
                                        $flosc_visitor_menu[$action] = [
                                            'label'   => $flosc_item['label'] ?? '',
                                            'enabled' => (bool) ($flosc_item['enabled'] ?? false),
                                        ];
                                    }
                                }
                                update_option('flosc_visitor_menu_items', $flosc_visitor_menu);
                            }

                            foreach ($flosc_visitor_menu as $flosc_key => $flosc_item):
                            ?>
                            <label class="flosc-flow-edit-menu-row">
                                <input type="checkbox" name="visitor_menu_items[<?php echo esc_attr($key); ?>][enabled]" value="1"
                                       <?php checked($flosc_item['enabled'] ?? true, true); ?>>
                                <input type="text" name="visitor_menu_items[<?php echo esc_attr($key); ?>][label]"
                                       value="<?php echo esc_attr($flosc_item['label'] ?? ''); ?>"
                                    class="flosc-flow-edit-menu-label-input">
                                <code class="flosc-flow-edit-menu-key"><?php echo esc_html($key); ?></code>
                            </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="flosc_save_flow" class="button button-primary" value="<?php echo $flosc_is_new ? 'Create Flow' : 'Save Changes'; ?>">
                    <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-flows') ); ?>" class="button">Cancel</a>
                </p>
            </form>
            
        <?php elseif ($flosc_current_tab === 'ivr'): ?>
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
                                <?php foreach ($flosc_ivr_files as $flosc_file): ?>
                                    <option value="<?php echo esc_attr($flosc_file); ?>" <?php selected($flosc_flow['ivr_file'] ?? 'flosc_default_ivr.md', $flosc_file); ?>>
                                        <?php echo esc_html($flosc_file); ?>
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
                
                <hr class="flosc-flow-edit-divider">
                
                <h3>Edit IVR Messages</h3>
                <p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=flosc-settings&tab=ivr-messages') ); ?>" class="button">
                        Open IVR Message Editor →
                    </a>
                </p>
                <p class="description">The IVR editor currently edits the global IVR messages. In a future version, it will be flow-aware.</p>
            </form>
            
        <?php elseif ($flosc_current_tab === 'content'): ?>
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
                                <?php foreach ($flosc_categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($flosc_flow['wp_category_id'] ?? 0, $cat->term_id); ?>>
                                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html( (string) $cat->count ); ?> posts)
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
                                <?php foreach ($flosc_quiz_types as $flosc_type_id => $flosc_type_label): ?>
                                    <option value="<?php echo esc_attr($type_id); ?>" <?php selected($flosc_flow['quiz_type'] ?? '', $type_id); ?>>
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
            
        <?php elseif ($flosc_current_tab === 'team' && $flosc_is_admin): ?>
            <!-- TEAM TAB (Admin only) -->
            <form method="post">
                <?php wp_nonce_field('flosc_update_team'); ?>
                
                <h2>Team Access</h2>
                <p class="description">Select which Editors and Authors can manage this flow. Administrators always have access to all flows.</p>
                
                <?php
                // Get all editors and authors
                $flosc_team_users = get_users([
                    'role__in' => ['editor', 'author', 'contributor'],
                    'orderby' => 'display_name',
                ]);
                
                // Get users who currently have access
                $flosc_current_team = flosc_flows()->get_flow_users($flosc_flow_id);
                $flosc_current_team_ids = array_map(function($u) { return $u->ID; }, $flosc_current_team);
                ?>
                
                <?php if (empty($flosc_team_users)): ?>
                    <p><em>No editors, authors, or contributors found. Only administrators can currently manage flows.</em></p>
                <?php else: ?>
                    <table class="widefat flosc-flow-edit-team-table">
                        <thead>
                            <tr>
                                <th class="flosc-flow-edit-team-access-col">Access</th>
                                <th>User</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flosc_team_users as $flosc_user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="team_users[]" value="<?php echo esc_attr( $flosc_user->ID ); ?>"
                                               <?php checked(in_array($flosc_user->ID, $flosc_current_team_ids)); ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($flosc_user->display_name); ?></strong><br>
                                        <small><?php echo esc_html($flosc_user->user_email); ?></small>
                                    </td>
                                    <td><?php echo esc_html(implode(', ', $flosc_user->roles)); ?></td>
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
