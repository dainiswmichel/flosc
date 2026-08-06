<?php
if (!defined('ABSPATH')) {
    exit;
}

// $flosc_get prepared by admin/settings.php (display flags only).
if ( ! isset( $flosc_get ) || ! is_array( $flosc_get ) ) {
	$flosc_get = array();
}
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

$flosc_concierge_default_title = 'Concierge Access';
$flosc_concierge_default_keyword = 'banana';
$flosc_concierge_default_password = 'orange';
$flosc_concierge_default_max_tries = 3;
$flosc_concierge_default_retry = "Hmm, not quite - that's try {try} of {max}.\nDo you want to continue trying to enter the correct password for {keyword}, or would you like to chat about something else?";
$flosc_concierge_default_success = 'Password confirmed - here you go.';
$flosc_concierge_default_delivery = "Warm, concise, and human.\nAlways offer a soft off-ramp: Would you like to continue this concierge exchange, or would you like to chat about something else?";
$flosc_concierge_default_off_ramp_exactness = 'preferred';
$flosc_concierge_default_off_ramp_phrases = "Do you want to continue trying to enter the correct password for {keyword}, or would you like to chat about something else?\nWould you like to continue this concierge exchange, or would you like to chat about something else?\nWould you like to continue with this, or are you interested in something else?";
$flosc_concierge_default_content = "https://dainis.net/music/put-vejini-saulstavu-apdare/\n\nWould you like to continue this concierge exchange, or would you like to chat about something else?";

$flosc_concierge_posts = get_posts([
    'post_type' => 'post',
    'post_status' => ['private', 'publish', 'draft'],
    'posts_per_page' => 200,
    'category_name' => 'flosc-internal-concierge,concierge',
    'orderby' => 'modified',
    'order' => 'DESC',
]);

// Keep Concierge tab flow-specific: show only posts mapped to the selected flow.
$flosc_concierge_posts = array_values(array_filter((array) $flosc_concierge_posts, static function ($flosc_post) use ($flosc_selected_ivr) {
    if (!($flosc_post instanceof WP_Post) || !class_exists('FLOSC_Concierge')) {
        return false;
    }
    if (!FLOSC_Concierge::is_concierge_post($flosc_post)) {
        return false;
    }
    $flosc_cfg = FLOSC_Concierge::config_from_post($flosc_post);
    $flosc_flow = sanitize_file_name((string) ($flosc_cfg['flow'] ?? ''));
    return $flosc_flow !== '' && $flosc_flow === $flosc_selected_ivr;
}));
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

    <p>Primary workflow: create a <strong>private WordPress post</strong> in category path <strong>flosc-internal/concierge</strong>. FLOSC syncs it into the flow DB immediately, and Br3nda uses the same live AI engine to host it.</p>

    <h3>Post Template (Copy/Paste)</h3>
    <p class="description">All a floscAdmin has to do is create a private post in <strong>flosc-internal/concierge</strong> and follow this shape:</p>
    <pre><code>Flow: Br3nda
Deployment: dainis.net/chat
Keyword: concierge-keyword
Password: optional-password
Delivery style: Warm, concise, and human.
Off-ramp exactness: preferred
Off-ramp phrases:
Would you like to continue this concierge exchange, or would you like to chat about something else?
Would you like to continue with this, or are you interested in something else?
Instructions template: Ask consent before revealing details.

Content to deliver:
Content A (before password or as guided)
Content B (after password)
Content C (full post content made available to this chatter)

Standard off-ramp line:
Would you like to continue this concierge exchange, or would you like to chat about something else?</code></pre>

    <h3>Quick Create Concierge Post</h3>
    <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_selected_ivr, 'tab' => 'concierge'], admin_url('admin.php'))); ?>">
        <?php wp_nonce_field('flosc_create_concierge_post', 'flosc_concierge_create_nonce'); ?>
        <input type="hidden" name="flosc_create_concierge_post" value="1">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="flosc_cncrg_title">Post Title</label></th>
                <td><input id="flosc_cncrg_title" name="flosc_cncrg_title" type="text" class="regular-text" value="<?php echo esc_attr($flosc_concierge_default_title); ?>"></td>
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
                    <input id="flosc_cncrg_keyword" name="flosc_cncrg_keyword" type="text" class="regular-text" value="<?php echo esc_attr($flosc_concierge_default_keyword); ?>" placeholder="Example: Frank">
                    <p class="description">Guest says this keyword to trigger concierge mode. You can also author this via a private post in flosc-internal/concierge.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_password">Password (optional)</label></th>
                <td>
                    <input id="flosc_cncrg_password" name="flosc_cncrg_password" type="text" class="regular-text" value="<?php echo esc_attr($flosc_concierge_default_password); ?>" placeholder="Example: 123">
                    <p class="description">Leave blank for instant access without a password gate.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_max_tries">Max Tries</label></th>
                <td><input id="flosc_cncrg_max_tries" name="flosc_cncrg_max_tries" type="number" min="1" class="small-text" value="<?php echo esc_attr((string) $flosc_concierge_default_max_tries); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_retry">Retry Messages</label></th>
                <td>
                    <textarea id="flosc_cncrg_retry" name="flosc_cncrg_retry" rows="4" class="large-text" placeholder="One line per retry. Use {try} and {max} placeholders."><?php echo esc_textarea($flosc_concierge_default_retry); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_success">Success Message</label></th>
                <td><input id="flosc_cncrg_success" name="flosc_cncrg_success" type="text" class="large-text" value="<?php echo esc_attr($flosc_concierge_default_success); ?>" placeholder="Access confirmed. I will guide you through the private material."></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_delivery">Delivery Style</label></th>
                <td>
                    <textarea id="flosc_cncrg_delivery" name="flosc_cncrg_delivery" rows="3" class="large-text" placeholder="Example: Warm, concise, professional. Reveal one item at a time."><?php echo esc_textarea($flosc_concierge_default_delivery); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_off_ramp_exactness">Off-ramp Exactness</label></th>
                <td>
                    <select id="flosc_cncrg_off_ramp_exactness" name="flosc_cncrg_off_ramp_exactness">
                        <option value="flexible">Flexible (paraphrase allowed)</option>
                        <option value="preferred" selected>Preferred (close wording)</option>
                        <option value="exact">Exact (verbatim phrase)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_off_ramp_phrases">Off-ramp Phrases</label></th>
                <td>
                    <textarea id="flosc_cncrg_off_ramp_phrases" name="flosc_cncrg_off_ramp_phrases" rows="4" class="large-text"><?php echo esc_textarea($flosc_concierge_default_off_ramp_phrases); ?></textarea>
                    <p class="description">One phrase per line. AI will apply exactness mode when presenting exit choices.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_cncrg_content">Content To Deliver</label></th>
                <td>
                    <textarea id="flosc_cncrg_content" name="flosc_cncrg_content" rows="10" class="large-text" placeholder="Put private concierge content here (links, PDFs, contact details, instructions)." required><?php echo esc_textarea($flosc_concierge_default_content); ?></textarea>
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
            $flosc_is_live = in_array((string) ($flosc_post->post_status ?? ''), ['private', 'publish'], true);
            ?>
            <details class="flosc-cncrg-accordion">
                <summary class="flosc-cncrg-accordion__head">
                    <span class="flosc-cncrg-accordion__chevron" aria-hidden="true">▸</span>
                    <span class="flosc-cncrg-accordion__title"><?php echo esc_html(get_the_title($flosc_post)); ?></span>
                    <span class="flosc-status <?php echo esc_attr($flosc_is_live ? 'flosc-status--active' : 'flosc-status--inactive'); ?>"><?php echo esc_html($flosc_is_live ? 'LIVE' : 'OFF'); ?></span>
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

    <hr>
    <h3>Template For New Concierge Posts</h3>
    <p class="description">Copy this into a new WordPress post, then edit values. Save as <strong>Private</strong> to activate, or <strong>Draft</strong> to keep OFF.</p>
    <pre><code>Flow: Br3nda
Deployment: dainis.net/chat
Keyword: banana
Password: orange
Delivery style: Warm, concise, and human.
Off-ramp exactness: preferred
Off-ramp phrases:
Would you like to continue this concierge exchange, or would you like to chat about something else?
Would you like to continue with this, or are you interested in something else?
Instructions template: Ask consent before revealing details.

Content to deliver:
https://dainis.net/music/put-vejini-saulstavu-apdare/

Would you like to continue this concierge exchange, or would you like to chat about something else?</code></pre>
</div>
