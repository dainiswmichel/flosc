<?php
/**
 * FLOSC Lessons Configuration Tab
 * 
 * Configures lesson delivery system:
 * - WordPress category for lesson posts
 * - Tag-based lesson mapping (quiz item → lesson)
 * - One-Time Offer (OTO) after quiz
 * - Speech-to-Text (STT) provider configuration
 * 
 * LESSON MAPPING SYSTEM:
 * - Lessons are WordPress posts in a designated category
 * - Each lesson tagged with quiz items (e.g., "5", "phoneme-5")
 * - When user misses quiz item, they get matching lesson
 * - First lesson FREE, additional lessons require payment
 * 
 * STT INTEGRATION:
 * - Required for audio-based quizzes
 * - Supports AssemblyAI, OpenAI Whisper, Deepgram, Custom endpoints
 * 
 * v1.2.9: Added tab header for flow context
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('📚', 'Lessons');

$flow_settings = $GLOBALS['flosc_current_settings'] ?? [];

// Get categories for dropdown
$categories = get_categories(['hide_empty' => false]);
$current_category = $flow_settings['lessons_category'] ?? '';

// Get offers for OTO dropdown
$offers = flosc()->sale()->offers()->get_all_offers();
$current_oto = $flow_settings['oto_offer_id'] ?? '';

// Get protected category settings
$protected_categories = get_option('flosc_protected_categories', []);
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
                        <?php echo esc_html($offer['name']); ?> (<?php echo esc_html($offer['display_price']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">Offer shown after quiz completion. <a href="?page=flosc-settings&tab=offers">Manage Offers</a></p>
        </td>
    </tr>
</table>

<!-- Category Protection (v1.0.1, updated v1.4.4) -->
<hr style="margin: 40px 0;">
<h2>Content Protection</h2>
<p>Protect lesson categories so only members with the required level can access full content.</p>

<?php
// v1.4.4: Gather offer levels for dropdown
$all_offers = flosc()->sale()->offers()->get_all_offers();
$available_levels = [];
foreach ($all_offers as $offer) {
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

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_protect_category">Protected Category</label></th>
        <td>
            <select name="flow_protect_category" id="flow_protect_category" class="regular-text">
                <option value="">— Select Category to Protect —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?> posts)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_required_level">Required Level</label></th>
        <td>
            <?php if (!empty($available_levels)): ?>
                <select name="flow_required_level" id="flow_required_level" class="regular-text">
                    <option value="">— Select Required Level —</option>
                    <?php foreach ($available_levels as $level_key => $level_info): ?>
                        <option value="<?php echo esc_attr($level_key); ?>">
                            <?php echo esc_html($level_key); ?> — from "<?php echo esc_html($level_info['offer_name']); ?>" <?php echo $level_info['price'] ? '(' . esc_html($level_info['price']) . ')' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Auto-populated from your Offers. The level here must match the "Grants Level" on your Offer.</p>
            <?php else: ?>
                <input type="text" name="flow_required_level" id="flow_required_level" class="regular-text" placeholder="flosc_plugin_member">
                <p class="description">No offers with "Grants Level" configured yet. <a href="?page=flosc-settings&tab=offers">Create an Offer</a> with a "Grants Level" and it will appear here as a dropdown.</p>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <th></th>
        <td>
            <button type="button" id="flow_add_protected_category" class="button button-primary">🔒 Protect This Category</button>
        </td>
    </tr>
</table>

<!-- v1.4.4: How It Works info box -->
<div class="flosc-info-box" style="background: #f0f7ff; border: 1px solid #c3dafe; padding: 20px; border-radius: 8px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #1e40af;">📋 How Member Levels Work</h3>
    <p style="margin-bottom: 12px; color: #374151;">The content protection chain connects <strong>Categories → Levels → Offers</strong>:</p>
    <table class="widefat" style="max-width: 800px; margin-bottom: 12px;">
        <thead>
            <tr style="background: #e0ecff;">
                <th>WordPress Category</th>
                <th>→</th>
                <th>Required Level</th>
                <th>→</th>
                <th>Granted By Offer</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($protected_cats) && !empty($available_levels)): ?>
                <?php foreach ($protected_cats as $pcat): ?>
                    <?php $matching_offer = $available_levels[$pcat['level']] ?? null; ?>
                    <tr>
                        <td><strong><?php echo esc_html($pcat['name']); ?></strong></td>
                        <td>→</td>
                        <td><code><?php echo esc_html($pcat['level']); ?></code></td>
                        <td>→</td>
                        <td><?php echo $matching_offer ? esc_html($matching_offer['offer_name']) : '<em style="color:#999;">No matching offer</em>'; ?></td>
                        <td><?php echo $matching_offer ? esc_html($matching_offer['price']) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($available_levels)): ?>
                <?php foreach ($available_levels as $level_key => $level_info): ?>
                    <?php 
                    // Skip if already shown above
                    $already_shown = false;
                    foreach ($protected_cats ?? [] as $pcat) {
                        if ($pcat['level'] === $level_key) { $already_shown = true; break; }
                    }
                    if ($already_shown) continue;
                    ?>
                    <tr style="opacity: 0.6;">
                        <td><em>No category protected yet</em></td>
                        <td>→</td>
                        <td><code><?php echo esc_html($level_key); ?></code></td>
                        <td>→</td>
                        <td><?php echo esc_html($level_info['offer_name']); ?></td>
                        <td><?php echo esc_html($level_info['price']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (empty($protected_cats) && empty($available_levels)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #667; font-style: italic;">
                        No offers or protected categories configured yet. Start by <a href="?page=flosc-settings&tab=offers">creating an Offer</a>.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <p style="margin: 0; color: #4b5563; font-size: 13px;">
        <strong>Flow:</strong> User purchases offer → gets <code>_flosc_memberlevel_{level}</code> → content protection checks level → shows/hides content.
    </p>
</div>

<!-- Currently Protected Categories -->
<h3>Currently Protected Categories</h3>
<?php
$protected_cats = [];
foreach ($categories as $cat) {
    $is_protected = get_term_meta($cat->term_id, '_flosc_protected', true);
    if ($is_protected === 'yes') {
        $required_level = get_term_meta($cat->term_id, '_flosc_required_level', true);
        $protected_cats[] = [
            'id' => $cat->term_id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'level' => $required_level ?: '(any member)'
        ];
    }
}

if (empty($protected_cats)): ?>
    <p style="color: #667; font-style: italic;">No categories are protected yet. Add protection above to require membership for access.</p>
<?php else: ?>
    <table class="widefat" style="max-width: 600px;">
        <thead>
            <tr>
                <th>Category</th>
                <th>Required Level</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($protected_cats as $pcat): ?>
            <tr>
                <td><strong><?php echo esc_html($pcat['name']); ?></strong></td>
                <td><code><?php echo esc_html($pcat['level']); ?></code></td>
                <td>
                    <button type="button" class="button button-small flosc-remove-protection" data-cat-id="<?php echo esc_attr($pcat['id']); ?>">Remove</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // Add protection
    $('#flosc_add_protected_category').on('click', function() {
        var catId = $('#flosc_protect_category').val();
        var level = $('#flosc_required_level').val().toLowerCase().replace(/\s+/g, '_');
        
        if (!catId) {
            alert('Please select a category to protect.');
            return;
        }
        
        $.post(ajaxurl, {
            action: 'flosc_protect_category',
            cat_id: catId,
            level: level,
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
    $('.flosc-remove-protection').on('click', function() {
        var catId = $(this).data('cat-id');
        
        if (!confirm('Remove protection from this category?')) return;
        
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
<div class="flosc-info-box" style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 20px 0;">
    <ol>
        <li>Create posts in your lessons category (e.g., "LeSAEp Lessons")</li>
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

<!-- STT Configuration (merged from STT tab) -->
<hr style="margin: 40px 0;">
<h2>Speech-to-Text Configuration</h2>
<p>Configure your speech-to-text provider for audio-based quizzes (Pronunciation, Simple Scoring with audio).</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flow_stt_provider">STT Provider</label></th>
        <td>
            <select name="flow_stt_provider" id="flow_stt_provider">
                <option value="assemblyai" <?php selected($flow_settings['stt_provider'] ?? '', 'assemblyai'); ?>>AssemblyAI</option>
                <option value="openai" <?php selected($flow_settings['stt_provider'] ?? '', 'openai'); ?>>OpenAI Whisper</option>
                <option value="deepgram" <?php selected($flow_settings['stt_provider'] ?? '', 'deepgram'); ?>>Deepgram</option>
                <option value="custom" <?php selected($flow_settings['stt_provider'] ?? '', 'custom'); ?>>Custom</option>
            </select>
            <p class="description">Choose your speech-to-text provider for transcribing audio recordings.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_assemblyai_api_key">AssemblyAI API Key</label></th>
        <td>
            <input type="password" id="flow_assemblyai_api_key" name="flow_assemblyai_api_key" value="<?php echo esc_attr($flow_settings['assemblyai_api_key'] ?? ''); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://www.assemblyai.com/" target="_blank">assemblyai.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_deepgram_api_key">Deepgram API Key</label></th>
        <td>
            <input type="password" id="flow_deepgram_api_key" name="flow_deepgram_api_key" value="<?php echo esc_attr($flow_settings['deepgram_api_key'] ?? ''); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://www.deepgram.com/" target="_blank">deepgram.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flow_custom_stt_endpoint">Custom STT Endpoint</label></th>
        <td>
            <input type="url" id="flow_custom_stt_endpoint" name="flow_custom_stt_endpoint" value="<?php echo esc_attr($flow_settings['custom_stt_endpoint'] ?? ''); ?>" class="regular-text">
            <p class="description">URL for your self-hosted STT endpoint (for "Custom" provider)</p>
        </td>
    </tr>
</table>
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
        var mode = $('#flosc_free_lesson_mode').val();
        if (mode === 'fixed') {
            $('#flosc_free_lesson_count_row').show();
            $('#flosc_free_lesson_proportion_row').hide();
        } else {
            $('#flosc_free_lesson_count_row').hide();
            $('#flosc_free_lesson_proportion_row').show();
        }
    }
    toggleFreeLessonFields();
    $('#flosc_free_lesson_mode').on('change', toggleFreeLessonFields);
});
</script>