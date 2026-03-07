<?php
/**
 * FLOSC Member Levels Tab — Level Registry + Content Protection
 *
 * SINGLE SOURCE OF TRUTH for member levels in a flow.
 * Other tabs (Offers, Lessons) reference this registry via dropdown.
 *
 * Sections:
 * 1. Level Registry — admin defines level slugs + display names
 * 2. Content Protection — assign categories, tags, posts, pages to levels
 * 3. Guest Access Rules — free lesson count/proportion, access duration
 *
 * Extracted from Lessons tab (v8.0.0 → v8.1.0) where content protection
 * and guest access were previously mixed with lesson group configuration.
 *
 * @package FLOSC
 * @since 8.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

flosc_tab_header( '🔐', 'Member Levels' );

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$settings_key  = $GLOBALS['flosc_settings_key']     ?? '';
$current_ivr   = $GLOBALS['flosc_current_ivr']      ?? '';

// ─── 1. Level Registry ──────────────────────────────────────────────────────

$member_levels = $flow_settings['member_levels'] ?? [];

// Empty row so the admin has something to fill in on first visit
if ( empty( $member_levels ) ) {
    $member_levels[''] = [ 'slug' => '', 'name' => '', 'description' => '' ];
}

?>

<!-- ─── Level Registry ────────────────────────────────────────────────── -->
<h2>Level Registry</h2>
<p>Define all membership levels available in this flow. Offers will grant these levels. Content protection will require them. The AI uses this to know what content each member can access.</p>

<table class="widefat flosc-levels-table" style="max-width: 860px;">
    <thead>
        <tr>
            <th style="width: 25%;">Slug <span style="color:#999; font-weight:normal;">(machine name)</span></th>
            <th style="width: 30%;">Display Name</th>
            <th style="width: 35%;">Description <span style="color:#999; font-weight:normal;">(optional)</span></th>
            <th style="width: 10%; text-align: center;">Actions</th>
        </tr>
    </thead>
    <tbody id="flosc-levels-body">
        <?php foreach ( $member_levels as $level_key => $level ) : ?>
        <tr class="flosc-level-row">
            <td>
                <input type="text" name="level_slug[]" 
                       value="<?php echo esc_attr( $level['slug'] ?? $level_key ); ?>" 
                       class="regular-text flosc-level-slug" 
                       placeholder="full_access" 
                       pattern="[a-z0-9_]+" 
                       title="Lowercase letters, numbers, underscores only"
                       style="width: 100%; font-family: monospace;">
            </td>
            <td>
                <input type="text" name="level_name[]" 
                       value="<?php echo esc_attr( $level['name'] ?? '' ); ?>" 
                       class="regular-text" 
                       placeholder="Full Access"
                       style="width: 100%;">
            </td>
            <td>
                <input type="text" name="level_description[]" 
                       value="<?php echo esc_attr( $level['description'] ?? '' ); ?>" 
                       class="regular-text" 
                       placeholder="Complete access to all course content"
                       style="width: 100%;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="button flosc-remove-level" title="Remove this level">&times;</button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<p style="margin-top: 10px;">
    <button type="button" class="button" id="flosc-add-level">+ Add Level</button>
</p>
<p class="description" style="margin-top: 6px;">
    Slugs must be unique, lowercase, and use underscores (e.g., <code>full_access</code>, <code>trial</code>, <code>premium</code>).
    These levels appear as dropdown options in the Offers tab and Content Protection below.
</p>

<?php
// ─── 2. Content Protection ──────────────────────────────────────────────────

// Build the level list for dropdowns (from saved levels, not from the form — form hasn't been submitted yet)
$saved_levels = $flow_settings['member_levels'] ?? $member_levels;

// Gather all WordPress categories and tags
$categories = get_categories( [ 'hide_empty' => false ] );
$tags       = get_tags( [ 'hide_empty' => false ] );

// Load existing protection data
$protected_items = $flow_settings['protected_content'] ?? [];
?>

<hr style="margin: 40px 0;">
<h2>Content Protection</h2>
<p>Assign WordPress content to member levels. Protected content is blocked for non-members and available to the AI for members with the required level.</p>

<table class="widefat flosc-protection-table" style="max-width: 960px;">
    <thead>
        <tr>
            <th style="width: 18%;">Type</th>
            <th style="width: 37%;">Content</th>
            <th style="width: 30%;">Required Level</th>
            <th style="width: 15%; text-align: center;">Actions</th>
        </tr>
    </thead>
    <tbody id="flosc-protection-body">
        <?php if ( ! empty( $protected_items ) ) : ?>
            <?php foreach ( $protected_items as $i => $item ) : ?>
            <tr class="flosc-protection-row">
                <td>
                    <select name="protection_type[]" class="flosc-protection-type" style="width: 100%;">
                        <option value="category" <?php selected( $item['type'] ?? '', 'category' ); ?>>Category</option>
                        <option value="tag" <?php selected( $item['type'] ?? '', 'tag' ); ?>>Tag</option>
                        <option value="post" <?php selected( $item['type'] ?? '', 'post' ); ?>>Post (ID)</option>
                        <option value="page" <?php selected( $item['type'] ?? '', 'page' ); ?>>Page (ID)</option>
                    </select>
                </td>
                <td>
                    <?php
                    $item_type = $item['type'] ?? 'category';
                    if ( in_array( $item_type, [ 'post', 'page' ] ) ) : ?>
                        <input type="text" name="protection_value[]" 
                               value="<?php echo esc_attr( $item['id'] ?? '' ); ?>" 
                               class="regular-text flosc-protection-value" 
                               placeholder="Post/Page ID" style="width: 100%;">
                    <?php else : ?>
                        <select name="protection_value[]" class="flosc-protection-value" style="width: 100%;">
                            <option value="">— Select —</option>
                            <?php if ( $item_type === 'category' ) : ?>
                                <?php foreach ( $categories as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" 
                                            <?php selected( $item['id'] ?? '', $cat->term_id ); ?>>
                                        <?php echo esc_html( $cat->name ); ?> (<?php echo $cat->count; ?> posts)
                                    </option>
                                <?php endforeach; ?>
                            <?php elseif ( $item_type === 'tag' ) : ?>
                                <?php foreach ( $tags as $tag ) : ?>
                                    <option value="<?php echo esc_attr( $tag->term_id ); ?>" 
                                            <?php selected( $item['id'] ?? '', $tag->term_id ); ?>>
                                        <?php echo esc_html( $tag->name ); ?> (<?php echo $tag->count; ?> posts)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="protection_level[]" class="flosc-protection-level-select" style="width: 100%;">
                        <option value="">— Any Member —</option>
                        <?php foreach ( $saved_levels as $lk => $lv ) : 
                            $slug = $lv['slug'] ?? $lk;
                            if ( empty( $slug ) ) continue;
                        ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" 
                                    <?php selected( $item['level'] ?? '', $slug ); ?>>
                                <?php echo esc_html( ( $lv['name'] ?? '' ) ?: $slug ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td style="text-align: center;">
                    <button type="button" class="button flosc-remove-protection-row" title="Remove">&times;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<p style="margin-top: 10px;">
    <button type="button" class="button" id="flosc-add-protection">+ Add Protection Rule</button>
</p>
<p class="description" style="margin-top: 6px;">
    <strong>Category/Tag:</strong> All posts in that category or tag are protected.<br>
    <strong>Post/Page ID:</strong> Protect a specific post or page by its WordPress ID.<br>
    <strong>"Any Member":</strong> Any logged-in member can access, regardless of level.
</p>

<?php
// ─── 3. Guest Access & Free Lessons ─────────────────────────────────────────

$free_lesson_mode       = $flow_settings['free_lesson_mode']       ?? 'fixed';
$free_lesson_count      = $flow_settings['free_lesson_count']      ?? 1;
$free_lesson_proportion = $flow_settings['free_lesson_proportion'] ?? '1/3';
$guest_access_days      = $flow_settings['guest_access_days']      ?? 0;
?>

<hr style="margin: 40px 0;">
<h2>Guest Access & Free Lessons</h2>
<p>Configure how many free lessons guests receive after completing the quiz, and how long they have access before they must purchase.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_free_lesson_mode">Free Lesson Mode</label></th>
        <td>
            <select name="flow_free_lesson_mode" id="flow_free_lesson_mode">
                <option value="fixed" <?php selected( $free_lesson_mode, 'fixed' ); ?>>Fixed Number</option>
                <option value="proportion" <?php selected( $free_lesson_mode, 'proportion' ); ?>>Proportion of Missed</option>
            </select>
            <p class="description">How to calculate how many free lessons guests receive.</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_count_row">
        <th scope="row"><label for="flow_free_lesson_count">Free Lesson Count</label></th>
        <td>
            <input type="number" id="flow_free_lesson_count" name="flow_free_lesson_count" 
                   value="<?php echo esc_attr( $free_lesson_count ); ?>" min="1" max="50" class="small-text">
            <p class="description">Number of free lessons to give guests. (For "Fixed Number" mode)</p>
        </td>
    </tr>
    <tr id="flow_free_lesson_proportion_row">
        <th scope="row"><label for="flow_free_lesson_proportion">Free Lesson Proportion</label></th>
        <td>
            <select name="flow_free_lesson_proportion" id="flow_free_lesson_proportion">
                <option value="1/5" <?php selected( $free_lesson_proportion, '1/5' ); ?>>1/5 of missed lessons</option>
                <option value="1/4" <?php selected( $free_lesson_proportion, '1/4' ); ?>>1/4 of missed lessons</option>
                <option value="1/3" <?php selected( $free_lesson_proportion, '1/3' ); ?>>1/3 of missed lessons</option>
                <option value="1/2" <?php selected( $free_lesson_proportion, '1/2' ); ?>>1/2 of missed lessons</option>
            </select>
            <p class="description">Proportion of missed quiz items to give as free lessons. (For "Proportion" mode)</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_guest_access_days">Guest Access Duration</label></th>
        <td>
            <input type="number" id="flow_guest_access_days" name="flow_guest_access_days" 
                   value="<?php echo esc_attr( $guest_access_days ); ?>" min="0" max="365" class="small-text"> days
            <p class="description">How long guests can access their free lessons. Set to 0 for unlimited access.</p>
        </td>
    </tr>
</table>

<!-- ─── How It Works ──────────────────────────────────────────────────── -->
<div style="background: #f0f7ff; border: 1px solid #c3dafe; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #1e40af;">How Member Levels Work</h3>
    <p style="margin-bottom: 8px; color: #374151;">
        <strong>1. Define levels</strong> in the registry above (e.g., <code>full_access</code>).<br>
        <strong>2. Protect content</strong> — assign categories, tags, posts, or pages to a level.<br>
        <strong>3. Create an offer</strong> (Offers tab) that <em>grants</em> that level on purchase.<br>
        <strong>4. Purchase completes</strong> → user gets <code>_flosc_memberlevel_{level} = yes</code> → content unlocks.<br>
        <strong>5. AI knows</strong> what content each member can access and serves it in chat.
    </p>
</div>

<!-- ─── JavaScript ────────────────────────────────────────────────────── -->
<script>
jQuery(document).ready(function($) {

    // ─── Level repeater ─────────────────────────────────────────────

    $('#flosc-add-level').on('click', function() {
        var row = '<tr class="flosc-level-row">'
            + '<td><input type="text" name="level_slug[]" class="regular-text flosc-level-slug" placeholder="full_access" pattern="[a-z0-9_]+" title="Lowercase letters, numbers, underscores only" style="width:100%;font-family:monospace;"></td>'
            + '<td><input type="text" name="level_name[]" class="regular-text" placeholder="Full Access" style="width:100%;"></td>'
            + '<td><input type="text" name="level_description[]" class="regular-text" placeholder="Complete access to all course content" style="width:100%;"></td>'
            + '<td style="text-align:center;"><button type="button" class="button flosc-remove-level" title="Remove this level">&times;</button></td>'
            + '</tr>';
        $('#flosc-levels-body').append(row);
    });

    $(document).on('click', '.flosc-remove-level', function() {
        if ($('.flosc-level-row').length > 1) {
            $(this).closest('tr').remove();
        } else {
            // Clear the fields instead of removing last row
            var $row = $(this).closest('tr');
            $row.find('input').val('');
        }
    });

    // Auto-generate slug from display name
    $(document).on('input', '.flosc-level-row input[name="level_name[]"]', function() {
        var $row = $(this).closest('tr');
        var $slug = $row.find('.flosc-level-slug');
        // Only auto-fill if slug is currently empty
        if ($slug.val() === '') {
            var slug = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            $slug.val(slug);
        }
    });

    // Enforce slug format on blur
    $(document).on('blur', '.flosc-level-slug', function() {
        var v = $(this).val().toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, '');
        $(this).val(v);
    });

    // ─── Content Protection repeater ────────────────────────────────

    // Build option strings for JS-generated rows
    var categoryOptions = <?php
        $opts = '<option value="">— Select —</option>';
        foreach ( $categories as $cat ) {
            $opts .= '<option value="' . esc_attr( $cat->term_id ) . '">' . esc_html( $cat->name ) . ' (' . intval( $cat->count ) . ' posts)</option>';
        }
        echo wp_json_encode( $opts );
    ?>;

    var tagOptions = <?php
        $opts = '<option value="">— Select —</option>';
        foreach ( $tags as $tag ) {
            $opts .= '<option value="' . esc_attr( $tag->term_id ) . '">' . esc_html( $tag->name ) . ' (' . intval( $tag->count ) . ' posts)</option>';
        }
        echo wp_json_encode( $opts );
    ?>;

    var levelOptions = <?php
        $opts = '<option value="">— Any Member —</option>';
        foreach ( $saved_levels as $lk => $lv ) {
            $slug = $lv['slug'] ?? $lk;
            if ( empty( $slug ) ) continue;
            $label = ( $lv['name'] ?? '' ) ?: $slug;
            $opts .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
        }
        echo wp_json_encode( $opts );
    ?>;

    function buildContentField(type) {
        if (type === 'post' || type === 'page') {
            return '<input type="text" name="protection_value[]" class="regular-text flosc-protection-value" placeholder="Post/Page ID" style="width:100%;">';
        } else if (type === 'tag') {
            return '<select name="protection_value[]" class="flosc-protection-value" style="width:100%;">' + tagOptions + '</select>';
        }
        // Default: category
        return '<select name="protection_value[]" class="flosc-protection-value" style="width:100%;">' + categoryOptions + '</select>';
    }

    $('#flosc-add-protection').on('click', function() {
        var row = '<tr class="flosc-protection-row">'
            + '<td><select name="protection_type[]" class="flosc-protection-type" style="width:100%;"><option value="category">Category</option><option value="tag">Tag</option><option value="post">Post (ID)</option><option value="page">Page (ID)</option></select></td>'
            + '<td>' + buildContentField('category') + '</td>'
            + '<td><select name="protection_level[]" class="flosc-protection-level-select" style="width:100%;">' + levelOptions + '</select></td>'
            + '<td style="text-align:center;"><button type="button" class="button flosc-remove-protection-row" title="Remove">&times;</button></td>'
            + '</tr>';
        $('#flosc-protection-body').append(row);
    });

    // When type changes, swap the content field
    $(document).on('change', '.flosc-protection-type', function() {
        var type = $(this).val();
        var $td = $(this).closest('tr').find('td:eq(1)');
        $td.html(buildContentField(type));
    });

    $(document).on('click', '.flosc-remove-protection-row', function() {
        $(this).closest('tr').remove();
    });

    // ─── Free lesson mode toggle ────────────────────────────────────

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
