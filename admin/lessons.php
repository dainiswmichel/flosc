<?php
/**
 * FLOSC Lessons Configuration Tab
 * 
 * v3.0.0: LESSON GROUPS — multi-quiz-to-category mapping
 * 
 * Configures lesson delivery system:
 * - Lesson Groups: repeatable Quiz → Category mappings
 *   - Each group maps an optional quiz to a required WordPress category
 *   - Categories without a quiz serve as standalone lesson libraries
 *   - Multiple quizzes can map to the same category (A/B testing)
 * - Per-post content protection override
 * - Guest access & free lessons
 * 
 * LESSON MAPPING:
 * - Lessons = WordPress posts in designated categories
 * - Each lesson tagged with quiz items (e.g., "5", "phoneme-5")
 * - When user misses quiz item → they get matching lesson from that quiz's category
 * - First lesson FREE, additional lessons require payment
 * 
 * CONTENT PROTECTION:
 * - Each lesson group's category is auto-protected on save
 * - Individual posts can override protection: Title+Excerpt, Title+ReadMore, Full
 * 
 * v3.0.0: Replaced single lessons_category with lesson_groups repeater
 * v1.8.2: Removed redundant "Protected Category" selector
 * v1.8.2: Fixed OTO dropdown not loading offers (missing flow_id)
 * v1.8.2: Added per-post protection override options
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('📚', 'Lessons');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_settings_key = $GLOBALS['flosc_settings_key'] ?? '';
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_lessons_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-lessons';

echo '<div class="flosc-docs-link-wrap">'
    . '<a href="' . esc_url($flosc_lessons_docs_url) . '" class="flosc-docs-link">Docs</a>'
   . '</div>';

// ─── Data for dropdowns ────────────────────────────────────────────

// WordPress categories (for the Category column)
$flosc_categories = get_categories(['hide_empty' => false]);

// Per-flow enabled quizzes (for the Quiz column)
// v3.0.2: Default must match quiz.php — fallback to text quiz so it appears even before Quiz tab is saved
$flosc_enabled_quizzes = $flosc_flow_settings['enabled_quizzes'] ?? ['flosc_sample_data_numbers_quiz'];
if (!is_array($flosc_enabled_quizzes)) $flosc_enabled_quizzes = ['flosc_sample_data_numbers_quiz'];

// v3.0.0: Build quiz label map from quiz type factory
// Quiz types are OBJECTS (FLOSC_Abstract_Quiz_Type instances), not arrays
$flosc_quiz_label_map = [];
if (class_exists('FLOSC_Quiz_Registry')) {
    $flosc_all_quiz_types = FLOSC_Quiz_Registry::get_all_quizzes();
    foreach ($flosc_all_quiz_types as $flosc_qid => $flosc_qt) {
        $flosc_quiz_label_map[$flosc_qid] = ($flosc_qt instanceof FLOSC_Abstract_Quiz_Type) ? $flosc_qt->get_name() : ucwords(str_replace(['_', 'flosc-', 'flosc_'], [' ', '', ''], $flosc_qid));
    }
}

// v3.0.0: Load lesson_groups (array of {quiz_id, category}) with backward compat
$flosc_lesson_groups = $flosc_flow_settings['lesson_groups'] ?? [];
if (empty($flosc_lesson_groups) && !empty($flosc_flow_settings['lessons_category'])) {
    // Migrate legacy single-category to lesson_groups format
    $flosc_lesson_groups = [
        ['quiz_id' => '', 'category' => $flosc_flow_settings['lessons_category']],
    ];
}
// Ensure at least one empty row for a fresh flow
if (empty($flosc_lesson_groups)) {
    $flosc_lesson_groups = [['quiz_id' => '', 'category' => '']];
}

// v1.8.2: Get offers for level dropdown (offers themselves are configured on the Offers tab)
$flosc_flow_id_for_offers = $flosc_settings_key ? str_replace('flosc_flow_', '', $flosc_settings_key) : null;
$flosc_offers = flosc()->sale()->offers()->get_all_offers($flosc_flow_id_for_offers);

// v1.8.2: Gather offer levels for required-level dropdown
$flosc_available_levels = [];
foreach ($flosc_offers as $flosc_offer) {
    $flosc_level = $flosc_offer['grants_level'] ?? ($flosc_offer['grants']['level'] ?? '');
    if (!empty($flosc_level)) {
        $flosc_available_levels[$flosc_level] = [
            'level' => $flosc_level,
            'offer_name' => $flosc_offer['name'] ?? 'Unknown Offer',
            'price' => $flosc_offer['display_price'] ?? '',
        ];
    }
}

// v3.0.0: Determine protection status per lesson group category
$flosc_protected_categories = [];
foreach ($flosc_lesson_groups as $flosc_group) {
    $flosc_cat_slug = $flosc_group['category'] ?? '';
    if (!$flosc_cat_slug) continue;
    $flosc_cat_obj = get_term_by('slug', sanitize_title($flosc_cat_slug), 'category');
    if ($flosc_cat_obj) {
        $flosc_protected_categories[$flosc_cat_slug] = [
            'obj'       => $flosc_cat_obj,
            'protected' => (get_term_meta($flosc_cat_obj->term_id, '_flosc_protected', true) === 'yes'),
            'level'     => get_term_meta($flosc_cat_obj->term_id, '_flosc_required_level', true) ?: '',
        ];
    }
}
?>

<!-- ─── Lesson Groups (v3.0.0) ───────────────────────────────────── -->
<h2>Lesson Groups</h2>
<p>Map quizzes to lesson categories. Each row connects an optional quiz to a WordPress category containing lesson posts. Categories without a quiz serve as standalone lesson libraries.</p>

<table class="widefat flosc-lesson-groups-table flosc-table-max-860">
    <thead>
        <tr>
            <th class="flosc-col-quiz">Quiz <span class="flosc-note-optional">(optional)</span></th>
            <th class="flosc-col-category">Category <span class="flosc-note-optional">(required)</span></th>
            <th class="flosc-col-actions">Actions</th>
        </tr>
    </thead>
    <tbody id="flosc-lesson-groups-body">
        <?php foreach ($flosc_lesson_groups as $flosc_i => $flosc_group): ?>
        <tr class="flosc-lesson-group-row">
            <td>
                <select name="lesson_group_quiz[]" class="regular-text flosc-width-full">
                    <option value="">— No Quiz (Standalone) —</option>
                    <?php foreach ($flosc_enabled_quizzes as $flosc_eq): ?>
                        <option value="<?php echo esc_attr($flosc_eq); ?>" <?php selected($flosc_group['quiz_id'] ?? '', $flosc_eq); ?>>
                            <?php echo esc_html($flosc_quiz_label_map[$flosc_eq] ?? $flosc_eq); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td>
                <select name="lesson_group_category[]" class="regular-text flosc-width-full">
                    <option value="">— Select Category —</option>
                    <?php foreach ($flosc_categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($flosc_group['category'] ?? '', $cat->slug); ?>>
                            <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?> posts)
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="flosc-text-center">
                <button type="button" class="button flosc-remove-lesson-group" title="Remove this group">&times;</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p class="flosc-margin-top-10">
    <button type="button" class="button" id="flosc-add-lesson-group">+ Add Lesson Group</button>
</p>
<p class="description flosc-margin-top-6">
    Each lesson post should be tagged with quiz item numbers (e.g., "5", "phoneme-5").
    When a user misses an item, FLOSC delivers the matching lesson from the quiz's mapped category.
</p>

<!-- Content Protection (v3.0.0: per-lesson-group categories) -->
<hr class="flosc-section-divider">
<h2>Content Protection</h2>

<?php if (!empty($flosc_protected_categories)): ?>

    <?php foreach ($flosc_protected_categories as $flosc_cat_slug => $flosc_cat_info):
        $flosc_cat_obj    = $flosc_cat_info['obj'];
        $flosc_is_protected = $flosc_cat_info['protected'];
        $flosc_req_level  = $flosc_cat_info['level'];
        // Resolve a friendly label for the stored level (may not match any current offer)
        $flosc_req_level_label = isset($flosc_available_levels[$flosc_req_level])
            ? '"' . ($flosc_available_levels[$flosc_req_level]['offer_name'] ?? '') . '"'
            : $flosc_req_level;
    ?>
    <div class="flosc-protection-block">
        <p class="flosc-protection-block__title">
            <strong>"<?php echo esc_html($flosc_cat_obj->name); ?>"</strong> (<?php echo esc_html($flosc_cat_obj->count); ?> posts)
            <?php if ($flosc_is_protected): ?>
                <span class="flosc-text-success">— Protected</span>
                <?php if ($flosc_req_level): ?>
                    <span class="flosc-text-muted"> &mdash; required level: <code><?php echo esc_html($flosc_req_level); ?></code> (<?php echo esc_html($flosc_req_level_label); ?>)</span>
                <?php endif; ?>
            <?php else: ?>
                <span class="flosc-text-warning">— Not yet protected</span>
            <?php endif; ?>
        </p>
        <div class="flosc-flex-row-gap-8">
            <?php if (!empty($flosc_available_levels)): ?>
                <select class="flosc-protection-level regular-text" data-cat-id="<?php echo esc_attr($flosc_cat_obj->term_id); ?>">
                    <option value="">— Select Required Level —</option>
                    <?php if (!empty($flosc_req_level) && !isset($flosc_available_levels[$flosc_req_level])): ?>
                        <option value="<?php echo esc_attr($flosc_req_level); ?>" selected>
                            <?php echo esc_html($flosc_req_level); ?> (current — not in offer list)
                        </option>
                    <?php endif; ?>
                    <?php foreach ($flosc_available_levels as $flosc_level_key => $flosc_level_info): ?>
                        <option value="<?php echo esc_attr($flosc_level_key); ?>" <?php selected($flosc_req_level, $flosc_level_key); ?>>
                            <?php echo esc_html($flosc_level_key); ?> — "<?php echo esc_html($flosc_level_info['offer_name']); ?>"
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" class="flosc-protection-level regular-text"
                       data-cat-id="<?php echo esc_attr($flosc_cat_obj->term_id); ?>"
                       value="<?php echo esc_attr($flosc_req_level); ?>" placeholder="flosc_plugin_member">
            <?php endif; ?>

            <?php if (!$flosc_is_protected): ?>
                <button type="button" class="button button-primary flosc-protect-cat"
                        data-cat-id="<?php echo esc_attr($flosc_cat_obj->term_id); ?>">Protect</button>
            <?php else: ?>
                <button type="button" class="button button-primary flosc-update-protection"
                        data-cat-id="<?php echo esc_attr($flosc_cat_obj->term_id); ?>">Save</button>
                <button type="button" class="button flosc-remove-protection flosc-button-danger"
                        data-cat-id="<?php echo esc_attr($flosc_cat_obj->term_id); ?>">Remove Protection</button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

<?php else: ?>
    <p class="flosc-text-muted-italic">Configure at least one Lesson Group with a category above and save to see content protection options.</p>
<?php endif; ?>

    <!-- Per-Post Protection Override (v1.8.2, v3.0.0: updated for multi-category) -->
    <h3>Per-Post Protection Overrides</h3>
    <p>Individual posts can selectively disable content protection. Edit any lesson post to see the FLOSC Content Access meta box with these options:</p>
    <ul class="flosc-list-disc-muted">
        <li><strong>Protected (default)</strong> — Full FLOSC protection. Non-members see nothing.</li>
        <li><strong>Show Title &amp; Excerpt</strong> — Non-members see the title and excerpt only.</li>
        <li><strong>Show Title &amp; Content through Read More</strong> — Non-members see content before the <code>&lt;!--more--&gt;</code> tag.</li>
        <li><strong>Full Post (Public)</strong> — Disable FLOSC protection entirely for this post.</li>
    </ul>

    <!-- How It Works (v3.0.0: updated for lesson_groups) -->
    <div class="flosc-lessons-info-box">
        <h3 class="flosc-lessons-info-box__title">How Content Protection Works</h3>
        <p class="flosc-lessons-info-box__lead">
            <strong>Flow:</strong> User takes quiz → missed items map to lessons in the quiz's category →
            first lesson is free → purchase offer → gets <code>_flosc_memberlevel_{level}</code> → all lessons unlock.
        </p>
        <?php if (!empty($flosc_available_levels) && !empty($flosc_protected_categories)): ?>
        <table class="widefat flosc-table-max-700">
            <thead>
            <tr class="flosc-lessons-table-head">
                    <th>Category</th>
                    <th>Required Level</th>
                    <th>Granted By Offer</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($flosc_protected_categories as $flosc_cat_slug => $flosc_cat_info): 
                    $flosc_matching_level = $flosc_available_levels[$flosc_cat_info['level']] ?? null;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($flosc_cat_info['obj']->name); ?></strong></td>
                    <td><code><?php echo esc_html($flosc_cat_info['level'] ?: '(not set)'); ?></code></td>
                    <td>
                        <?php if ( $flosc_matching_level ) : ?>
                            <?php echo esc_html($flosc_matching_level['offer_name']); ?>
                        <?php else : ?>
                            <em class="flosc-empty-dash">—</em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $flosc_matching_level ? esc_html($flosc_matching_level['price']) : esc_html('—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

<?php ob_start(); ?>
jQuery(document).ready(function($) {

    // ─── Lesson Groups repeater (v3.0.0) ────────────────────────────

    // Template for a new lesson group row
    var quizOptions = <?php
        $flosc_opts = '<option value="">— No Quiz (Standalone) —</option>';
        foreach ($flosc_enabled_quizzes as $flosc_eq) {
            $flosc_label = esc_html($flosc_quiz_label_map[$flosc_eq] ?? $flosc_eq);
            $flosc_opts .= '<option value="' . esc_attr($flosc_eq) . '">' . $flosc_label . '</option>';
        }
        echo wp_json_encode($flosc_opts);
    ?>;
    var catOptions = <?php
        $flosc_opts = '<option value="">— Select Category —</option>';
        foreach ($flosc_categories as $cat) {
            $flosc_opts .= '<option value="' . esc_attr($cat->slug) . '">' . esc_html($cat->name) . ' (' . esc_html($cat->count) . ' posts)</option>';
        }
        echo wp_json_encode($flosc_opts);
    ?>;

    // Add new row
    $('#flosc-add-lesson-group').on('click', function() {
        var row = '<tr class="flosc-lesson-group-row">'
            + '<td><select name="lesson_group_quiz[]" class="regular-text flosc-width-full">' + quizOptions + '</select></td>'
            + '<td><select name="lesson_group_category[]" class="regular-text flosc-width-full">' + catOptions + '</select></td>'
            + '<td class="flosc-text-center"><button type="button" class="button flosc-remove-lesson-group" title="Remove this group">&times;</button></td>'
            + '</tr>';
        $('#flosc-lesson-groups-body').append(row);
    });

    // Remove row (keep at least one)
    $(document).on('click', '.flosc-remove-lesson-group', function() {
        if ($('.flosc-lesson-group-row').length > 1) {
            $(this).closest('tr').remove();
        } else {
            alert('At least one lesson group is required.');
        }
    });

    // ─── Content Protection (v3.0.0: per-category buttons) ──────────

    // Protect / Update protection
    $(document).on('click', '.flosc-protect-cat, .flosc-update-protection', function() {
        var catId = $(this).data('cat-id');
        var $block = $(this).closest('.flosc-protection-block');
        var levelEl = $block.find('.flosc-protection-level');
        var level = levelEl.val() || '';
        if (levelEl.is('input')) {
            level = level.toLowerCase().replace(/\s+/g, '_');
        }
        
        $.post(ajaxurl, {
            action: 'flosc_protect_category',
            cat_id: catId,
            level: level || '',
            nonce: '<?php echo esc_attr(wp_create_nonce('flosc_protect_category')); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });
    
    // Remove protection
    $(document).on('click', '.flosc-remove-protection', function() {
        if (!confirm('Remove FLOSC content protection from this category?')) return;
        
        var catId = $(this).data('cat-id');
        $.post(ajaxurl, {
            action: 'flosc_unprotect_category',
            cat_id: catId,
            nonce: '<?php echo esc_attr(wp_create_nonce('flosc_unprotect_category')); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + (response.data || 'Unknown error'));
            }
        });
    });
});
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>

<h3>How Lesson Groups Work</h3>
<div class="flosc-lessons-workflow-box">
    <ol>
        <li>Create a <strong>Lesson Group</strong> above: pick a quiz (optional) and a category (required)</li>
        <li>Create posts in that category, tagged with quiz item numbers:
            <ul>
                <li>For number quiz: tag with "5", "6", "7" etc.</li>
                <li>Or use "phoneme-5", "phoneme-6" format</li>
            </ul>
        </li>
        <li>When a user completes a quiz and misses item "5", FLOSC delivers the lesson tagged "5" from the quiz's mapped category</li>
        <li>Categories without a quiz are standalone libraries — available to members directly</li>
        <li>First matched lesson is FREE, the rest require payment</li>
    </ol>
</div>

<!-- Guest Access & Free Lessons Configuration (v1.0.1) -->
<hr class="flosc-section-divider">
<h2>Guest Access & Free Lessons</h2>
<p>Configure how many free lessons guests receive after completing the quiz, and how long they have access.</p>

<?php
$flosc_free_lesson_mode        = $flosc_flow_settings['free_lesson_mode']        ?? 'fixed';
$flosc_free_lesson_count       = $flosc_flow_settings['free_lesson_count']       ?? 1;
$flosc_free_lesson_proportion  = $flosc_flow_settings['free_lesson_proportion']  ?? '1/3';
$flosc_guest_access_days       = $flosc_flow_settings['guest_access_days']       ?? 0;
$flosc_free_lesson_guaranteed  = $flosc_flow_settings['free_lesson_guaranteed']  ?? 35;
// Pool of free-sample posts = a WP category (often a child of the main lessons category).
$flosc_free_lesson_pool_category = sanitize_title((string) ($flosc_flow_settings['free_lesson_pool_category'] ?? ''));
// Lesson numbers that must never be complimentary (upsell-only).
$flosc_free_lesson_never_free = (string) ($flosc_flow_settings['free_lesson_never_free'] ?? '');
// Categories for the pool dropdown (reuse $flosc_categories loaded above)
?>

<table class="form-table">
    <tr>
        <th scope="row">Complimentary content (guests)</th>
        <td>
            <p class="description flosc-description-margin-top-0">
                Per <strong>floscFlow</strong>: pick up to <em>N</em> complimentary items from a <strong>pool</strong>
                (category of posts / lessons / recipes / topics). Exclude numbers that must <strong>never</strong> be free.
                Video is <strong>not</strong> required — put only guest-ready posts in the pool if you want a shortlist.
                IPA quizzes still prefer multi-phoneme tiers when choosing which pool lessons to grant.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_free_lesson_pool_category">Complimentary pool category</label></th>
        <td>
            <select name="flow_free_lesson_pool_category" id="flow_free_lesson_pool_category" class="regular-text">
                <option value="">— Same as lesson group category (all mapped lessons) —</option>
                <?php foreach ($flosc_categories as $cat):
                    $flosc_cat_label = $cat->name;
                    if (!empty($cat->parent)) {
                        $flosc_cat_parent = get_category((int) $cat->parent);
                        if ($flosc_cat_parent && !is_wp_error($flosc_cat_parent)) {
                            $flosc_cat_label = $flosc_cat_parent->name . ' → ' . $cat->name;
                        }
                    }
                    ?>
                    <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($flosc_free_lesson_pool_category, $cat->slug); ?>>
                        <?php echo esc_html($flosc_cat_label); ?> (<?php echo esc_html((string) $cat->count); ?> posts)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <strong>Saved as <code>free_lesson_pool_category</code>.</strong>
                Only published posts in this category (matching <code>_flosc_lesson_number</code>) can be free samples.
                Tip: child category under main lessons (e.g. <code>lesaep</code> → <code>4guests</code>).
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_free_lesson_never_free">Never free (lesson numbers)</label></th>
        <td>
            <input type="text" id="flow_free_lesson_never_free" name="flow_free_lesson_never_free"
                   value="<?php echo esc_attr($flosc_free_lesson_never_free); ?>"
                   class="large-text" placeholder="e.g. 1, 2, 7, 12">
            <p class="description">
                Comma-separated lesson <em>numbers</em> that must <strong>never</strong> be given as complimentary guest content
                (upsell-only). Saved as <code>free_lesson_never_free</code>.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_free_lesson_mode">How many complimentary items</label></th>
        <td>
            <select name="flow_free_lesson_mode" id="flow_free_lesson_mode">
                <option value="fixed" <?php selected($flosc_free_lesson_mode, 'fixed'); ?>>Fixed number</option>
                <option value="proportion" <?php selected($flosc_free_lesson_mode, 'proportion'); ?>>Proportion of missed (non-IPA)</option>
            </select>
            <p class="description">Saved as <code>free_lesson_mode</code>. Fixed count applies to IPA tier-walk and non-IPA quizzes.</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_count_row">
        <th scope="row"><label for="flow_free_lesson_count">Complimentary count (x)</label></th>
        <td>
            <input type="number" id="flow_free_lesson_count" name="flow_free_lesson_count" value="<?php echo esc_attr($flosc_free_lesson_count); ?>" min="1" max="50" class="small-text">
            <p class="description">How many pool items to grant guests (e.g. <strong>2</strong>). Plus optional bonus below. Saved as <code>free_lesson_count</code>.</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_proportion_row">
        <th scope="row"><label for="flow_free_lesson_proportion">Proportion of missed</label></th>
        <td>
            <select name="flow_free_lesson_proportion" id="flow_free_lesson_proportion">
                <option value="1/5" <?php selected($flosc_free_lesson_proportion, '1/5'); ?>>1/5 of missed lessons</option>
                <option value="1/4" <?php selected($flosc_free_lesson_proportion, '1/4'); ?>>1/4 of missed lessons</option>
                <option value="1/3" <?php selected($flosc_free_lesson_proportion, '1/3'); ?>>1/3 of missed lessons</option>
                <option value="1/2" <?php selected($flosc_free_lesson_proportion, '1/2'); ?>>1/2 of missed lessons</option>
            </select>
            <p class="description">Non-IPA quizzes only when mode is Proportion. Saved as <code>free_lesson_proportion</code>.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_free_lesson_guaranteed">Bonus complimentary number</label></th>
        <td>
            <input type="number" id="flow_free_lesson_guaranteed" name="flow_free_lesson_guaranteed" value="<?php echo esc_attr($flosc_free_lesson_guaranteed); ?>" min="0" max="9999" class="small-text">
            <p class="description">
                Extra lesson number for every guest (e.g. <strong>35</strong>), in addition to the count above.
                Must be in the pool and not on the never-free list. <code>0</code> = off. Saved as <code>free_lesson_guaranteed</code>.
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_access_days">Guest Access Duration</label></th>
        <td>
            <input type="number" id="flow_guest_access_days" name="flow_guest_access_days" value="<?php echo esc_attr($flosc_guest_access_days); ?>" min="0" max="365" class="small-text"> days
            <p class="description">How long guests can access free samples. <code>0</code> = unlimited. Saved as <code>guest_access_days</code>.</p>
        </td>
    </tr>
</table>

<?php ob_start(); ?>
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
<?php wp_add_inline_script('flosc-admin', ob_get_clean()); ?>