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

$flosc_contact_trj_default_title = 'Contact Exchange Encouragement';
$flosc_contact_trj_default_keywords = 'music, songs, album, support, collaborate, contact, phone, email';
$flosc_contact_trj_default_priority = 80;
$flosc_contact_trj_default_off_ramp_exactness = 'preferred';
$flosc_contact_trj_default_off_ramp_phrases = "Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?\nDo you want to keep chatting about this trajectory, or would you like to chat about something else?\nDo you have any other questions, or are you interested in something else?";
$flosc_contact_trj_default_instructions = "Encourage direct human-to-human connection when relevant.\nInvite exchange of contact information (email, phone, or message).\nAsk for one concrete next step and keep tone warm, concise, and natural.\nOffer a clear off-ramp: Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?";

$flosc_trajectory_posts = get_posts([
    'post_type' => 'post',
    'post_status' => ['private', 'publish', 'draft'],
    'posts_per_page' => 200,
    'category_name' => 'flosc-internal-trajectories,trajectory,trajectories',
    'orderby' => 'modified',
    'order' => 'DESC',
]);

// Keep Trajectories tab flow-specific: show only posts mapped to the selected flow.
$flosc_trajectory_posts = array_values(array_filter((array) $flosc_trajectory_posts, static function ($flosc_post) use ($flosc_selected_ivr) {
    if (!($flosc_post instanceof WP_Post) || !class_exists('FLOSC_Trajectory')) {
        return false;
    }
    if (!FLOSC_Trajectory::is_trajectory_post($flosc_post)) {
        return false;
    }
    $flosc_cfg = FLOSC_Trajectory::config_from_post($flosc_post);
    $flosc_flow = sanitize_file_name((string) ($flosc_cfg['flow'] ?? ''));
    return $flosc_flow !== '' && $flosc_flow === $flosc_selected_ivr;
}));
?>
<div class="flosc-admin-panel">
    <h2>Trajectories</h2>

    <?php if (!empty($flosc_get['trajectory_created'])): ?>
        <div class="notice notice-success"><p>Trajectory post created and synced to flow guidance.</p></div>
    <?php endif; ?>
    <?php if (($flosc_get['trajectory_error'] ?? '') === 'missing_required'): ?>
        <div class="notice notice-error"><p>Trajectory instructions are required.</p></div>
    <?php elseif (($flosc_get['trajectory_error'] ?? '') === 'create_failed'): ?>
        <div class="notice notice-error"><p>Could not create trajectory post. Please try again.</p></div>
    <?php elseif (($flosc_get['trajectory_error'] ?? '') === 'toggle_failed'): ?>
        <div class="notice notice-error"><p>Could not update trajectory status. Please try again.</p></div>
    <?php endif; ?>

    <?php if (($flosc_get['trajectory_toggled'] ?? '') === 'on'): ?>
        <div class="notice notice-success"><p>Trajectory entry set to LIVE.</p></div>
    <?php elseif (($flosc_get['trajectory_toggled'] ?? '') === 'off'): ?>
        <div class="notice notice-success"><p>Trajectory entry set to OFF.</p></div>
    <?php endif; ?>

    <p>Trajectories run through the same existing AI engine. Each private trajectory post adds silent steering guidance to the flow before Br3nda responds. No separate response engine is used.</p>
    <p><strong>Primary workflow:</strong> create a private post in <strong>flosc-internal/trajectories</strong>. FLOSC syncs that post into flow DB guidance instantly.</p>

    <h3>Post Template (Copy/Paste)</h3>
    <pre><code>floscFlow: dainis_net_ivr.md
Keywords: contact, connect, phone, email
Priority: 80
Off-ramp exactness: preferred
Off-ramp phrases:
Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?
Do you have any other questions, or are you interested in something else?

Instructions:
Encourage human-to-human connection.
Offer phone, electronic communication, or in-person options.
Ask for one concrete next step.
Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?</code></pre>
    <p class="description"><strong>Priority range is 0-100.</strong> Higher number wins when multiple trajectories match the same message. Suggested ranges: 0-39 low, 40-69 medium, 70-89 strong, 90-100 highest override.</p>

    <h3>Quick Create Trajectory Post</h3>
    <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_selected_ivr, 'tab' => 'trajectories'], admin_url('admin.php'))); ?>">
        <?php wp_nonce_field('flosc_create_trajectory_post', 'flosc_trajectory_create_nonce'); ?>
        <input type="hidden" name="flosc_create_trajectory_post" value="1">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="flosc_trj_title">Post Title</label></th>
                <td><input id="flosc_trj_title" name="flosc_trj_title" type="text" class="regular-text" value="<?php echo esc_attr($flosc_contact_trj_default_title); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_flow">Flow</label></th>
                <td>
                    <select id="flosc_trj_flow" name="flosc_trj_flow">
                        <?php foreach ($flosc_flow_options as $flosc_file => $flosc_label): ?>
                            <option value="<?php echo esc_attr($flosc_file); ?>" <?php selected($flosc_file, $flosc_selected_ivr); ?>>
                                <?php echo esc_html($flosc_label . ' (' . $flosc_file . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_keywords">Keywords (optional)</label></th>
                <td>
                    <input id="flosc_trj_keywords" name="flosc_trj_keywords" type="text" class="regular-text" value="<?php echo esc_attr($flosc_contact_trj_default_keywords); ?>" placeholder="music, inventions, contact, support">
                    <p class="description">Comma-separated. Blank means this trajectory can apply generally.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_priority">Priority</label></th>
                <td>
                    <input id="flosc_trj_priority" name="flosc_trj_priority" type="number" min="0" max="100" class="small-text" value="<?php echo esc_attr((string) $flosc_contact_trj_default_priority); ?>">
                    <p class="description">Priority is 0-100. Higher number wins when multiple trajectories match. Recommended starting value for contact-exchange steering: 80.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_instructions">Trajectory Guidance</label></th>
                <td>
                    <textarea id="flosc_trj_instructions" name="flosc_trj_instructions" rows="8" class="large-text" placeholder="You serve Dainis in facilitating human-to-human connection. Encourage exchange of contact information and suggest one next step."><?php echo esc_textarea($flosc_contact_trj_default_instructions); ?></textarea>
                    <p class="description">This is silent steering for Br3nda. It is injected into the existing prompt path before response generation.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_off_ramp_exactness">Off-ramp Exactness</label></th>
                <td>
                    <select id="flosc_trj_off_ramp_exactness" name="flosc_trj_off_ramp_exactness">
                        <option value="flexible">Flexible (paraphrase allowed)</option>
                        <option value="preferred" selected>Preferred (close wording)</option>
                        <option value="exact">Exact (verbatim phrase)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_trj_off_ramp_phrases">Off-ramp Phrases</label></th>
                <td>
                    <textarea id="flosc_trj_off_ramp_phrases" name="flosc_trj_off_ramp_phrases" rows="4" class="large-text"><?php echo esc_textarea($flosc_contact_trj_default_off_ramp_phrases); ?></textarea>
                    <p class="description">One phrase per line. AI will apply exactness mode when presenting an exit choice.</p>
                </td>
            </tr>
        </table>

        <?php submit_button('Create Trajectory Post'); ?>
    </form>

    <hr>
    <h3>Trajectory Posts</h3>
    <p class="description">Private internal posts in category <strong>flosc-internal/trajectories</strong> are synced into flow guidance.</p>

    <div class="flosc-cncrg-accordion-wrap">
    <?php if (empty($flosc_trajectory_posts)): ?>
        <p class="description">No trajectory posts yet.</p>
    <?php else: ?>
        <?php foreach ($flosc_trajectory_posts as $flosc_post): ?>
            <?php
            $flosc_cfg = class_exists('FLOSC_Trajectory') ? FLOSC_Trajectory::config_from_post($flosc_post) : null;
            $flosc_flow = is_array($flosc_cfg) ? (string) ($flosc_cfg['flow'] ?? '') : '';
            $flosc_keywords = is_array($flosc_cfg) ? (string) ($flosc_cfg['keywords'] ?? '') : '';
            $flosc_priority = is_array($flosc_cfg) ? intval($flosc_cfg['priority'] ?? 0) : 0;
            $flosc_preview_source = is_array($flosc_cfg) ? (string) ($flosc_cfg['instructions'] ?? '') : '';
            $flosc_preview = wp_html_excerpt(trim(preg_replace('/\s+/', ' ', $flosc_preview_source)), 160, '...');
            $flosc_is_live = in_array((string) ($flosc_post->post_status ?? ''), ['private', 'publish'], true);
            ?>
            <details class="flosc-cncrg-accordion">
                <summary class="flosc-cncrg-accordion__head">
                    <span class="flosc-cncrg-accordion__chevron" aria-hidden="true">▸</span>
                    <span class="flosc-cncrg-accordion__title"><?php echo esc_html(get_the_title($flosc_post)); ?></span>
                    <span class="flosc-status <?php echo esc_attr($flosc_is_live ? 'flosc-status--active' : 'flosc-status--inactive'); ?>"><?php echo esc_html($flosc_is_live ? 'LIVE' : 'OFF'); ?></span>
                    <span class="flosc-cncrg-accordion__meta"><?php echo esc_html($flosc_flow !== '' ? $flosc_flow : 'No flow'); ?></span>
                    <span class="flosc-cncrg-accordion__meta">Keywords: <?php echo esc_html($flosc_keywords !== '' ? $flosc_keywords : 'general'); ?></span>
                    <span class="flosc-cncrg-accordion__meta">Priority: <?php echo esc_html((string) $flosc_priority); ?></span>
                    <span class="flosc-cncrg-accordion__when"><?php echo esc_html(get_the_modified_date('Y-m-d H:i', $flosc_post)); ?></span>
                </summary>
                <div class="flosc-cncrg-accordion__body">
                    <p><strong>Guidance preview:</strong> <?php echo esc_html($flosc_preview !== '' ? $flosc_preview : '(empty)'); ?></p>
                    <p>
                        <form method="post" action="<?php echo esc_url(add_query_arg(['page' => 'flosc-settings', 'ivr' => $flosc_selected_ivr, 'tab' => 'trajectories'], admin_url('admin.php'))); ?>" class="flosc-admin-inline-action-form">
                            <?php wp_nonce_field('flosc_toggle_trajectory_post', 'flosc_toggle_trajectory_nonce'); ?>
                            <input type="hidden" name="flosc_toggle_trajectory_post" value="<?php echo esc_attr((string) $flosc_post->ID); ?>">
                            <input type="hidden" name="flosc_trajectory_set" value="<?php echo esc_attr($flosc_is_live ? 'off' : 'on'); ?>">
                            <?php submit_button($flosc_is_live ? 'Turn OFF' : 'Turn ON', 'secondary', 'submit', false); ?>
                        </form>
                        <a class="button button-small" href="<?php echo esc_url(get_edit_post_link($flosc_post->ID)); ?>">Edit Post</a>
                    </p>
                </div>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <hr>
    <h3>Template For New Trajectory Posts</h3>
    <p class="description">Copy this into a new WordPress post, then edit values. Save as <strong>Private</strong> to activate, or <strong>Draft</strong> to keep OFF.</p>
    <p class="description"><strong>Priority uses a 0-100 scale.</strong> 100 has stronger precedence than 80, and 80 has stronger precedence than 50 when keyword matches overlap.</p>
    <pre><code>Flow: Br3nda
Deployment: dainis.net/chat
Keywords: contact, connect, phone, email
Priority: 80
Off-ramp exactness: preferred
Off-ramp phrases:
Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?
Do you have any other questions, or are you interested in something else?

Instructions:
Encourage human-to-human connection.
Offer phone, electronic communication, or in-person options.
Ask for one concrete next step.
Would you like me to continue facilitating human-to-human connection between you and Dainis, or would you like to chat about something else?</code></pre>
</div>
