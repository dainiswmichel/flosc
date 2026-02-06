<?php
/**
 * Flow Edit Tab: Lessons
 * 
 * Content delivery and access configuration
 */

if (!defined('ABSPATH')) exit;

$lessons = $flow['lessons'] ?? [];

// Get offers for OTO dropdown
$offers = flosc()->sale()->offers()->get_all_offers();

$free_mode_options = [
    '' => '— Use Global —',
    'first' => 'First lesson free',
    'count' => 'Specific number free',
    'tagged' => 'Lessons tagged "free"',
    'none' => 'No free lessons',
];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">Lesson Source</h2>
    
    <div class="flosc-form-field">
        <label for="lessons_category">Lessons Category</label>
        <select id="lessons_category" name="lessons_category" style="max-width: 400px;">
            <option value="">— Use Global Default —</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($lessons['category'] ?? '', $cat->slug); ?>>
                    <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?> posts)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($lessons['category'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('lessons.category', 'All Categories')); ?>
            </span>
        <?php endif; ?>
        <p class="description">WordPress category containing this flow's lesson posts.</p>
    </div>
    
    <h2 class="flosc-section-header">One-Time Offer (OTO)</h2>
    
    <div class="flosc-form-field">
        <label for="lessons_oto_offer_id">OTO Offer</label>
        <select id="lessons_oto_offer_id" name="lessons_oto_offer_id" style="max-width: 400px;">
            <option value="">— Use Global Default —</option>
            <?php foreach ($offers as $offer): ?>
                <option value="<?php echo esc_attr($offer['id']); ?>" <?php selected($lessons['oto_offer_id'] ?? '', $offer['id']); ?>>
                    <?php echo esc_html($offer['name']); ?> (<?php echo esc_html($offer['display_price']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Offer shown after quiz completion. <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=offers'); ?>">Manage Offers →</a></p>
    </div>
    
    <h2 class="flosc-section-header">Free Lesson Access</h2>
    
    <div class="flosc-form-field">
        <label for="lessons_free_mode">Free Lesson Mode</label>
        <select id="lessons_free_mode" name="lessons_free_mode" style="max-width: 300px;">
            <?php foreach ($free_mode_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($lessons['free_mode'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($lessons['free_mode'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('lessons.free_mode', 'first')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <div class="flosc-form-field" id="free_count_field" style="<?php echo ($lessons['free_mode'] ?? '') === 'count' ? '' : 'display: none;'; ?>">
        <label for="lessons_free_count">Number of Free Lessons</label>
        <input type="number" id="lessons_free_count" name="lessons_free_count" 
               value="<?php echo esc_attr($lessons['free_count'] ?? ''); ?>"
               placeholder="<?php echo esc_attr(flosc_global_placeholder('lessons.free_count', '1')); ?>"
               min="0" max="99" style="width: 80px; max-width: 80px;">
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
    
    <hr style="margin: 40px 0;">
    
    <h3>How Lessons Work</h3>
    <div style="background: #f9fafb; padding: 20px; border-radius: 8px;">
        <ol style="margin: 0; padding-left: 20px;">
            <li>Create posts in your lessons category</li>
            <li>Add tags to each post matching quiz items (e.g., "5", "phoneme-5")</li>
            <li>When a user misses quiz item "5", they get the lesson tagged "5"</li>
            <li>Free lessons are delivered based on your free lesson mode</li>
            <li>Additional lessons require purchasing the OTO offer</li>
        </ol>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    $('#lessons_free_mode').on('change', function() {
        if ($(this).val() === 'count') {
            $('#free_count_field').show();
        } else {
            $('#free_count_field').hide();
        }
    });
});
</script>
