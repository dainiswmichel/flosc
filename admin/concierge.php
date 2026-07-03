<?php
if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin display flags below; no state change is performed from these values.
$flosc_get = wp_unslash($_GET);
$flosc_selected_ivr = sanitize_file_name((string) ($GLOBALS['flosc_current_ivr'] ?? ''));

$flosc_files = function_exists('flosc_config_glob') ? flosc_config_glob(['*_ivr.md', 'ivr*.md']) : [];
$flosc_flow_options = [];
foreach ((array) $flosc_files as $flosc_file) {
    $flosc_name = basename((string) $flosc_file);
    if ($flosc_name === '' || strpos($flosc_name, 'backup') !== false) {
        continue;
    }
    $flosc_key = 'flosc_flow_' . sanitize_key(pathinfo($flosc_name, PATHINFO_FILENAME));
    $flosc_settings = get_option($flosc_key, []);
    $flosc_label = trim((string) ($flosc_settings['identity']['name'] ?? ''));
    if ($flosc_label === '') {
        $flosc_label = ucwords(str_replace(['_', '-', '.md'], [' ', ' ', ''], $flosc_name));
    }
    $flosc_flow_options[$flosc_name] = $flosc_label;
}
ksort($flosc_flow_options);

$flosc_concierge_posts = get_posts([
    'post_type' => 'post',
    'post_status' => ['private', 'publish', 'draft'],
    'posts_per_page' => 20,
    'category_name' => 'concierge',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only concierge listing, bounded to 20 posts.
    'meta_key' => '_flosc_concierge_flow',
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- admin-only concierge listing, bounded to 20 posts.
    'meta_value' => $flosc_selected_ivr,
    'orderby' => 'modified',
    'order' => 'DESC',
]);
?>
<div class="flosc-admin-panel">
    <h2>Concierge</h2>

    <?php if (!empty($flosc_get['concierge_created'])): ?>
        <div class="notice notice-success"><p>Concierge post created and synced to chat.</p></div>
    <?php endif; ?>
    <?php if (($flosc_get['concierge_error'] ?? '') === 'missing_required'): ?>
        <div class="notice notice-error"><p>Keyword and content are required.</p></div>
    <?php elseif (($flosc_get['concierge_error'] ?? '') === 'create_failed'): ?>
        <div class="notice notice-error"><p>Could not create concierge post. Please try again.</p></div>
    <?php endif; ?>

    <p>Workflow: create a private concierge post with flow, keyword, optional password, and delivery content. FLOSC syncs it into the flow and Br3nda delivers it through the concierge gate. Regular private WordPress concierge posts are ingested immediately when saved.</p>

    <h3>Quick Create Concierge Post</h3>
    <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_selected_ivr, 'tab' => 'concierge'], admin_url('admin.php'))); ?>">
        <?php wp_nonce_field('flosc_create_concierge_post', 'flosc_concierge_create_nonce'); ?>
        <input type="hidden" name="flosc_create_concierge_post" value="1">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="flosc_cncrg_title">Post Title</label></th>
                <td><input id="flosc_cncrg_title" name="flosc_cncrg_title" type="text" class="regular-text" value=""></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_flow">Flow</label></th>
                <td>
                    <select id="flosc_cncrg_flow" name="flosc_cncrg_flow">
                        <?php foreach ($flosc_flow_options as $flosc_file => $flosc_label): ?>
                            <option value="<?php echo esc_attr($flosc_file); ?>" <?php selected($flosc_file, $flosc_selected_ivr); ?>>
                                <?php echo esc_html($flosc_label . ' (' . $flosc_file . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_keyword">Keyword</label></th>
                <td>
                    <input id="flosc_cncrg_keyword" name="flosc_cncrg_keyword" type="text" class="regular-text" placeholder="Example: Frank">
                    <p class="description">Guest says this keyword to trigger concierge mode.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_password">Password (optional)</label></th>
                <td>
                    <input id="flosc_cncrg_password" name="flosc_cncrg_password" type="text" class="regular-text" placeholder="Example: 123">
                    <p class="description">Leave blank for instant access without a password gate.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_max_tries">Max Tries</label></th>
                <td><input id="flosc_cncrg_max_tries" name="flosc_cncrg_max_tries" type="number" min="1" class="small-text" value="3"></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_retry">Retry Messages</label></th>
                <td>
                    <textarea id="flosc_cncrg_retry" name="flosc_cncrg_retry" rows="4" class="large-text" placeholder="One line per retry. Use {try} and {max} placeholders."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_success">Success Message</label></th>
                <td><input id="flosc_cncrg_success" name="flosc_cncrg_success" type="text" class="large-text" placeholder="Access confirmed. I will guide you through the private material."></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_delivery">Delivery Style</label></th>
                <td>
                    <textarea id="flosc_cncrg_delivery" name="flosc_cncrg_delivery" rows="3" class="large-text" placeholder="Example: Warm, concise, professional. Reveal one item at a time."></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_content">Content To Deliver</label></th>
                <td>
                    <textarea id="flosc_cncrg_content" name="flosc_cncrg_content" rows="10" class="large-text" placeholder="Put private concierge content here (links, PDFs, contact details, instructions)." required></textarea>
                    <p class="description">This is the private source Br3nda uses when the guest clears keyword/password.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Create Concierge Post'); ?>
    </form>

    <hr>
    <h3>Concierge Posts</h3>
    <p class="description">Accordion view: click a heading row to expand details.</p>

    <div class="flosc-cncrg-accordion-wrap">
    <?php if (empty($flosc_concierge_posts)): ?>
        <p class="description">No concierge posts yet.</p>
    <?php else: ?>
        <?php foreach ($flosc_concierge_posts as $flosc_post): ?>
            <?php
            $flosc_cfg = class_exists('FLOSC_Concierge') ? FLOSC_Concierge::config_from_post($flosc_post) : null;
            $flosc_flow = is_array($flosc_cfg) ? (string) ($flosc_cfg['flow'] ?? '') : '';
            $flosc_keyword = is_array($flosc_cfg) ? (string) ($flosc_cfg['keyword'] ?? '') : '';
            $flosc_has_password = is_array($flosc_cfg) ? (trim((string) ($flosc_cfg['password'] ?? '')) !== '') : false;
            $flosc_preview_source = is_array($flosc_cfg) ? (string) ($flosc_cfg['content'] ?? '') : '';
            $flosc_preview = wp_html_excerpt(trim(preg_replace('/\s+/', ' ', $flosc_preview_source)), 140, '...');
            ?>
            <details class="flosc-cncrg-accordion">
                <summary class="flosc-cncrg-accordion__head">
                    <span class="flosc-cncrg-accordion__chevron" aria-hidden="true">▸</span>
                    <span class="flosc-cncrg-accordion__title"><?php echo esc_html(get_the_title($flosc_post)); ?></span>
                    <span class="flosc-cncrg-accordion__meta"><?php echo esc_html($flosc_flow !== '' ? $flosc_flow : 'No flow'); ?></span>
                    <span class="flosc-cncrg-accordion__meta">Keyword: <?php echo esc_html($flosc_keyword !== '' ? $flosc_keyword : '-'); ?></span>
                    <span class="flosc-cncrg-accordion__meta"><?php echo esc_html($flosc_has_password ? 'Password: Yes' : 'Password: No'); ?></span>
                    <span class="flosc-cncrg-accordion__when"><?php echo esc_html(get_the_modified_date('Y-m-d H:i', $flosc_post)); ?></span>
                </summary>
                <div class="flosc-cncrg-accordion__body">
                    <p><strong>Preview:</strong> <?php echo esc_html($flosc_preview !== '' ? $flosc_preview : '(empty)'); ?></p>
                    <p>
                        <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($flosc_post->ID)); ?>">Edit Post</a>
                    </p>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>
