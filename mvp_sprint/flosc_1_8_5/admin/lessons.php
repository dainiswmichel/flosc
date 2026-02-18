<?php
/**
 * FLOSC Lessons Configuration Tab
 * 
 * Configures lesson delivery system:
 * - WordPress category for lesson posts (auto-protected)
 * - Tag-based lesson mapping (quiz item → lesson)
 * - One-Time Offer (OTO) after quiz
 * - Per-post content protection override
 * - Guest access & free lessons
 * 
 * LESSON MAPPING:
 * - Lessons = WordPress posts in a designated category
 * - Each lesson tagged with quiz items (e.g., "5", "phoneme-5")
 * - When user misses quiz item → they get matching lesson
 * - First lesson FREE, additional lessons require payment
 * 
 * CONTENT PROTECTION:
 * - The lessons category IS the protected category (auto-protected on save)
 * - Individual posts can override protection: Title+Excerpt, Title+ReadMore, Full
 * 
 * v1.8.2: Removed redundant "Protected Category" selector — lessons category = protected category
 * v1.8.2: Fixed OTO dropdown not loading offers (missing flow_id)
 * v1.8.2: Moved Speech-to-Text config to AI Configuration tab
 * v1.8.2: Added per-post protection override options
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('📚', 'Lessons');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$settings_key = $GLOBALS['flosc_settings_key'] ?? '';

// Get categories for dropdown
$categories = get_categories(['hide_empty' => false]);
$current_category = $flow_settings['lessons_category'] ?? '';

// v1.8.2: Get offers using flow_id so they actually load
$flow_id_for_offers = $settings_key ? str_replace('flosc_flow_', '', $settings_key) : null;
$offers = flosc()->sale()->offers()->get_all_offers($flow_id_for_offers);
$current_oto = $flow_settings['oto_offer_id'] ?? '';

// v1.8.2: Check current protection status (the lessons category)
$lessons_cat_obj = $current_category ? get_term_by('slug', sanitize_title($current_category), 'category') : null;
$is_currently_protected = $lessons_cat_obj ? (get_term_meta($lessons_cat_obj->term_id, '_flosc_protected', true) === 'yes') : false;
$current_required_level = $lessons_cat_obj ? get_term_meta($lessons_cat_obj->term_id, '_flosc_required_level', true) : '';

// v1.8.2: Gather offer levels for required-level dropdown
$available_levels = [];
foreach ($offers as $offer) {
    $level = $offer['grants_level'] ?? ($offer['grants']['level'] ?? '');
    if (!empty($level)) {
        $available_levels[$level] = [
            'level' => $level,
            'offer_name' => $offer['name'] ?? 'Unknown Offer',
            'price' => $offer['display_price'] ?? '',
        ];
    }
}
?>

<h2>Lesson Configuration</h2>
<p>Connect your lessons (WordPress posts) to the quiz system. Lessons should be regular posts in a category, with tags matching quiz items.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_lessons_category">Lessons Category</label></th>
        <td>
            <select name="flow_lessons_category" id="flow_lessons_category" class="regular-text">
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($current_category, $cat->slug); ?>>
                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?> posts)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Category containing your lesson posts. Each lesson should have tags matching quiz items (e.g., "5", "phoneme-5").</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_oto_offer_id">One-Time Offer (OTO)</label></th>
        <td>
            <select name="flow_oto_offer_id" id="flow_oto_offer_id" class="regular-text">
                <option value="">— No OTO —</option>
                <?php foreach ($offers as $offer): ?>
                    <option value="<?php echo esc_attr($offer['id']); ?>" <?php selected($current_oto, $offer['id']); ?>>
                        <?php echo esc_html($offer['name']); ?> (<?php echo esc_html($offer['display_price'] ?? 'no price'); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Offer shown after quiz completion. <a href="?page=flosc-settings&ivr=<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>&tab=offers">Manage Offers</a></p>
        </td>
    </tr>
</table>

<!-- Content Protection (v1.8.2: simplified — lessons category = protected category) -->
<hr style="margin: 40px 0;">
<h2>Content Protection</h2>

<?php if ($lessons_cat_obj): ?>
    <p>The lessons category <strong>"<?php echo esc_html($lessons_cat_obj->name); ?>"</strong> is automatically protected by FLOSC.
    <?php if ($is_currently_protected): ?>
        <span style="color: #46b450;">🔒 Currently protected<?php echo $current_required_level ? ' — requires level: <code>' . esc_html($current_required_level) . '</code>' : ''; ?></span>
    <?php else: ?>
        <span style="color: #dba617;">⚠️ Not yet protected. Click "Protect This Category" below.</span>
    <?php endif; ?>
    </p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="flow_required_level">Required Member Level</label></th>
            <td>
                <?php if (!empty($available_levels)): ?>
                    <select name="flow_required_level" id="flow_required_level" class="regular-text">
                        <option value="">— Select Required Level —</option>
                        <?php foreach ($available_levels as $level_key => $level_info): ?>
                            <option value="<?php echo esc_attr($level_key); ?>" <?php selected($current_required_level, $level_key); ?>>
                                <?php echo esc_html($level_key); ?> — from "<?php echo esc_html($level_info['offer_name']); ?>" <?php echo $level_info['price'] ? '(' . esc_html($level_info['price']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Auto-populated from your Offers. The level must match the "Grants Level" on your Offer.</p>
                <?php else: ?>
                    <input type="text" name="flow_required_level" id="flow_required_level" class="regular-text" 
                           value="<?php echo esc_attr($current_required_level); ?>" placeholder="flosc_plugin_member">
                    <p class="description">No offers with "Grants Level" configured yet. <a href="?page=flosc-settings&ivr=<?php echo esc_attr($GLOBALS['flosc_current_ivr'] ?? ''); ?>&tab=offers">Create an Offer</a> with a "Grants Level" and it will appear here as a dropdown.</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th></th>
            <td>
                <?php if (!$is_currently_protected): ?>
                    <button type="button" id="flosc_protect_lessons_cat" class="button button-primary" 
                            data-cat-id="<?php echo esc_attr($lessons_cat_obj->term_id); ?>">🔒 Protect This Category</button>
                <?php else: ?>
                    <button type="button" id="flosc_update_protection_level" class="button" 
                            data-cat-id="<?php echo esc_attr($lessons_cat_obj->term_id); ?>">Update Required Level</button>
                    <button type="button" id="flosc_remove_protection" class="button" style="color: #b32d2e; margin-left: 8px;"
                            data-cat-id="<?php echo esc_attr($lessons_cat_obj->term_id); ?>">Remove Protection</button>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- Per-Post Protection Override (v1.8.2) -->
    <h3>Per-Post Protection Overrides</h3>
    <p>Individual posts in this category can selectively disable content protection. Edit a post in the <strong>"<?php echo esc_html($lessons_cat_obj->name); ?>"</strong> category to see the FLOSC Content Access meta box with these options:</p>
    <ul style="list-style: disc; padding-left: 20px; color: #50575e;">
        <li><strong>Protected (default)</strong> — Full FLOSC protection. Non-members see nothing.</li>
        <li><strong>Show Title &amp; Excerpt</strong> — Non-members see the title and excerpt only.</li>
        <li><strong>Show Title &amp; Content through Read More</strong> — Non-members see content before the <code>&lt;!--more--&gt;</code> tag.</li>
        <li><strong>Full Post (Public)</strong> — Disable FLOSC protection entirely for this post.</li>
    </ul>

    <!-- How It Works -->
    <div style="background: #f0f7ff; border: 1px solid #c3dafe; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0; color: #1e40af;">📋 How Content Protection Works</h3>
        <p style="margin-bottom: 8px; color: #374151;"><strong>Flow:</strong> User purchases offer → gets <code>_flosc_memberlevel_{level}</code> → content protection checks level → shows/hides content.</p>
        <?php if (!empty($available_levels)): ?>
        <table class="widefat" style="max-width: 700px;">
            <thead>
                <tr style="background: #e0ecff;">
                    <th>Category</th>
                    <th>Required Level</th>
                    <th>Granted By Offer</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php echo esc_html($lessons_cat_obj->name); ?></strong></td>
                    <td><code><?php echo esc_html($current_required_level ?: '(not set)'); ?></code></td>
                    <td>
                        <?php 
                        $matching = $available_levels[$current_required_level] ?? null;
                        echo $matching ? esc_html($matching['offer_name']) : '<em style="color:#999;">—</em>';
                        ?>
                    </td>
                    <td><?php echo $matching ? esc_html($matching['price']) : '—'; ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php else: ?>
    <p style="color: #667; font-style: italic;">Select a Lessons Category above and save to configure content protection.</p>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // Protect / Update protection
    $('#flosc_protect_lessons_cat, #flosc_update_protection_level').on('click', function() {
        var catId = $(this).data('cat-id');
        var level = $('#flow_required_level').val();
        if (typeof level === 'string') {
            level = level.toLowerCase().replace(/\s+/g, '_');
        }
        
        $.post(ajaxurl, {
            action: 'flosc_protect_category',
            cat_id: catId,
            level: level || '',
            nonce: '<?php echo wp_create_nonce('flosc_protect_category'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });
    
    // Remove protection
    $('#flosc_remove_protection').on('click', function() {
        if (!confirm('Remove FLOSC content protection from the lessons category?')) return;
        
        var catId = $(this).data('cat-id');
        $.post(ajaxurl, {
            action: 'flosc_unprotect_category',
            cat_id: catId,
            nonce: '<?php echo wp_create_nonce('flosc_unprotect_category'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });
});
</script>

<h3>How Lesson Mapping Works</h3>
<div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <ol>
        <li>Create posts in your lessons category (e.g., "FLOSC Sample Data")</li>
        <li>Add tags to each post matching quiz items:
            <ul>
                <li>For number quiz: tag with "5", "6", "7" etc.</li>
                <li>Or use "phoneme-5", "phoneme-6" format</li>
            </ul>
        </li>
        <li>When a user misses item "5", they get the lesson tagged "5" or "phoneme-5"</li>
        <li>First matched lesson is FREE, rest require payment</li>
    </ol>
</div>

<!-- Guest Access & Free Lessons Configuration (v1.0.1) -->
<hr style="margin: 40px 0;">
<h2>Guest Access & Free Lessons</h2>
<p>Configure how many free lessons guests receive after completing the quiz, and how long they have access.</p>

<?php
$free_lesson_mode = $flow_settings['free_lesson_mode'] ?? 'fixed';
$free_lesson_count = $flow_settings['free_lesson_count'] ?? 1;
$free_lesson_proportion = $flow_settings['free_lesson_proportion'] ?? '1/3';
$guest_access_days = $flow_settings['guest_access_days'] ?? 0;
?>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_free_lesson_mode">Free Lesson Mode</label></th>
        <td>
            <select name="flow_free_lesson_mode" id="flow_free_lesson_mode">
                <option value="fixed" <?php selected($free_lesson_mode, 'fixed'); ?>>Fixed Number</option>
                <option value="proportion" <?php selected($free_lesson_mode, 'proportion'); ?>>Proportion of Missed</option>
            </select>
            <p class="description">How to calculate how many free lessons guests receive.</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_count_row">
        <th scope="row"><label for="flow_free_lesson_count">Free Lesson Count</label></th>
        <td>
            <input type="number" id="flow_free_lesson_count" name="flow_free_lesson_count" value="<?php echo esc_attr($free_lesson_count); ?>" min="1" max="50" class="small-text">
            <p class="description">Number of free lessons to give guests. (For "Fixed Number" mode)</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_proportion_row">
        <th scope="row"><label for="flow_free_lesson_proportion">Free Lesson Proportion</label></th>
        <td>
            <select name="flow_free_lesson_proportion" id="flow_free_lesson_proportion">
                <option value="1/5" <?php selected($free_lesson_proportion, '1/5'); ?>>1/5 of missed lessons</option>
                <option value="1/4" <?php selected($free_lesson_proportion, '1/4'); ?>>1/4 of missed lessons</option>
                <option value="1/3" <?php selected($free_lesson_proportion, '1/3'); ?>>1/3 of missed lessons</option>
                <option value="1/2" <?php selected($free_lesson_proportion, '1/2'); ?>>1/2 of missed lessons</option>
            </select>
            <p class="description">Proportion of missed quiz items to give as free lessons. (For "Proportion" mode)</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_access_days">Guest Access Duration</label></th>
        <td>
            <input type="number" id="flow_guest_access_days" name="flow_guest_access_days" value="<?php echo esc_attr($guest_access_days); ?>" min="0" max="365" class="small-text"> days
            <p class="description">How long guests can access their free lessons. Set to 0 for unlimited access.</p>
        </td>
    </tr>
</table>

<script>
jQuery(document).ready(function($) {
    function toggleFreeLessonFields() {
        var mode = $('#flow_free_lesson_mode').val();
        if (mode === 'fixed') {
            $('#flow_free_lesson_count_row').show();
            $('#flow_free_lesson_proportion_row').hide();
        } else {
            $('#flow_free_lesson_count_row').hide();
            $('#flow_free_lesson_proportion_row').show();
        }
    }
    toggleFreeLessonFields();
    $('#flow_free_lesson_mode').on('change', toggleFreeLessonFields);
});
</script>