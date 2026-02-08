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

// Get categories for dropdown
$categories = get_categories(['hide_empty' => false]);
$current_category = get_option('flosc_lessons_category', '');

// Get offers for OTO dropdown
$offers = flosc()->sale()->offers()->get_all_offers();
$current_oto = get_option('flosc_oto_offer_id', '');

// Get protected category settings
$protected_categories = get_option('flosc_protected_categories', []);
?>

<h2>Lesson Configuration</h2>
<p>Connect your lessons (WordPress posts) to the quiz system. Lessons should be regular posts in a category, with tags matching quiz items.</p>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_lessons_category">Lessons Category</label></th>
        <td>
            <select name="flosc_lessons_category" id="flosc_lessons_category" class="regular-text">
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
        <th scope="row"><label for="flosc_oto_offer_id">One-Time Offer (OTO)</label></th>
        <td>
            <select name="flosc_oto_offer_id" id="flosc_oto_offer_id" class="regular-text">
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

<!-- Category Protection (v1.0.1, v1.4.4: improved UI clarity) -->
<hr style="margin: 40px 0;">
<h2>Content Protection</h2>

<!-- v1.4.4: How It Works explanation box -->
<div style="background: #f0f7ff; border: 1px solid #c8ddf5; border-radius: 8px; padding: 20px; margin: 16px 0 24px;">
    <h3 style="margin: 0 0 12px; font-size: 15px;">How FLOSC Content Protection Works</h3>
    <div style="font-size: 13px; line-height: 1.7; color: #333;">
        <strong>The chain:</strong> Category Protection + Member Level = Access Control<br>
        <ol style="margin: 8px 0; padding-left: 20px;">
            <li>You put lesson posts in a WordPress <strong>category</strong> (e.g., "FLOSC Sample Data")</li>
            <li>You <strong>protect</strong> that category below and set a <strong>required level</strong> (e.g., <code>flosc_plugin_member</code>)</li>
            <li>When a user completes a <strong>sandbox or real purchase</strong>, FLOSC grants them that level</li>
            <li>Now they can see the full content of posts in that category</li>
        </ol>
        <strong>The required level must match</strong> what the purchase grants. Your current offers grant these levels:
    </div>
    <table style="margin-top: 10px; font-size: 13px; border-collapse: collapse;">
        <tr style="background: #e8f0fe;">
            <th style="padding: 6px 12px; text-align: left; border: 1px solid #c8ddf5;">Offer</th>
            <th style="padding: 6px 12px; text-align: left; border: 1px solid #c8ddf5;">Grants Level</th>
            <th style="padding: 6px 12px; text-align: left; border: 1px solid #c8ddf5;">Sandbox Action</th>
        </tr>
        <?php
        $offer_mgr = flosc()->sale()->offers();
        $all_offers = $offer_mgr->get_all_offers();
        foreach ($all_offers as $oid => $odata) {
            $level = $odata['grants']['level'] ?? '';
            if (empty($level)) continue;
            $product_id = $odata['product_id'] ?? '';
            $sandbox_action = $product_id ? 'sandbox_purchase_' . $product_id : '';
            ?>
            <tr>
                <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><?php echo esc_html($odata['name']); ?></td>
                <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><code><?php echo esc_html($level); ?></code></td>
                <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><code><?php echo esc_html($sandbox_action ?: '—'); ?></code></td>
            </tr>
        <?php } ?>
        <tr>
            <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><em>Default sandbox</em></td>
            <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><code>flosc_sandbox</code></td>
            <td style="padding: 6px 12px; border: 1px solid #c8ddf5;"><code>sandbox_purchase</code></td>
        </tr>
    </table>
</div>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_protect_category">Category to Protect</label></th>
        <td>
            <select name="flosc_protect_category" id="flosc_protect_category" class="regular-text">
                <option value="">— Select Category —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->term_id); ?>">
                        <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?> posts)
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_required_level">Required Level</label></th>
        <td>
            <select name="flosc_required_level" id="flosc_required_level" class="regular-text">
                <option value="">— Any Member (no specific level) —</option>
                <?php
                // v1.4.4: Auto-populate from offers so user doesn't have to type
                $seen_levels = [];
                foreach ($all_offers as $oid => $odata) {
                    $level = $odata['grants']['level'] ?? '';
                    if ($level && !in_array($level, $seen_levels)) {
                        $seen_levels[] = $level;
                        echo '<option value="' . esc_attr($level) . '">' . esc_html($level) . ' (' . esc_html($odata['name']) . ')</option>';
                    }
                }
                // Always include flosc_sandbox as an option
                if (!in_array('flosc_sandbox', $seen_levels)) {
                    echo '<option value="flosc_sandbox">flosc_sandbox (Default sandbox)</option>';
                }
                ?>
            </select>
            <p class="description">Must match the level granted by your offer/sandbox purchase. Leave blank to allow any member.</p>
        </td>
    </tr>
    <tr>
        <th></th>
        <td>
            <button type="button" id="flosc_add_protected_category" class="button button-primary">Protect This Category</button>
        </td>
    </tr>
</table>

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
        var level = $('#flosc_required_level').val();
        
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
        <th scope="row"><label for="flosc_stt_provider">STT Provider</label></th>
        <td>
            <select name="flosc_stt_provider" id="flosc_stt_provider">
                <option value="assemblyai" <?php selected(get_option('flosc_stt_provider'), 'assemblyai'); ?>>AssemblyAI</option>
                <option value="openai" <?php selected(get_option('flosc_stt_provider'), 'openai'); ?>>OpenAI Whisper</option>
                <option value="deepgram" <?php selected(get_option('flosc_stt_provider'), 'deepgram'); ?>>Deepgram</option>
                <option value="custom" <?php selected(get_option('flosc_stt_provider'), 'custom'); ?>>Custom</option>
            </select>
            <p class="description">Choose your speech-to-text provider for transcribing audio recordings.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_assemblyai_api_key">AssemblyAI API Key</label></th>
        <td>
            <input type="password" id="flosc_assemblyai_api_key" name="flosc_assemblyai_api_key" value="<?php echo esc_attr(get_option('flosc_assemblyai_api_key', '')); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://www.assemblyai.com/" target="_blank">assemblyai.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_deepgram_api_key">Deepgram API Key</label></th>
        <td>
            <input type="password" id="flosc_deepgram_api_key" name="flosc_deepgram_api_key" value="<?php echo esc_attr(get_option('flosc_deepgram_api_key', '')); ?>" class="regular-text">
            <p class="description">Get your key at <a href="https://www.deepgram.com/" target="_blank">deepgram.com</a></p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_custom_stt_endpoint">Custom STT Endpoint</label></th>
        <td>
            <input type="url" id="flosc_custom_stt_endpoint" name="flosc_custom_stt_endpoint" value="<?php echo esc_attr(get_option('flosc_custom_stt_endpoint', '')); ?>" class="regular-text">
            <p class="description">URL for your self-hosted STT endpoint (for "Custom" provider)</p>
        </td>
    </tr>
</table>
<!-- Guest Access & Free Lessons Configuration (v1.0.1) -->
<hr style="margin: 40px 0;">
<h2>Guest Access & Free Lessons</h2>
<p>Configure how many free lessons guests receive after completing the quiz, and how long they have access.</p>

<?php
$free_lesson_mode = get_option('flosc_free_lesson_mode', 'fixed');
$free_lesson_count = get_option('flosc_free_lesson_count', 1);
$free_lesson_proportion = get_option('flosc_free_lesson_proportion', '1/3');
$guest_access_days = get_option('flosc_guest_access_days', 0);
?>

<table class="form-table">
    <tr>
        <th scope="row"><label for="flosc_free_lesson_mode">Free Lesson Mode</label></th>
        <td>
            <select name="flosc_free_lesson_mode" id="flosc_free_lesson_mode">
                <option value="fixed" <?php selected($free_lesson_mode, 'fixed'); ?>>Fixed Number</option>
                <option value="proportion" <?php selected($free_lesson_mode, 'proportion'); ?>>Proportion of Missed</option>
            </select>
            <p class="description">How to calculate how many free lessons guests receive.</p>
        </td>
    </tr>
    <tr id="flosc_free_lesson_count_row">
        <th scope="row"><label for="flosc_free_lesson_count">Free Lesson Count</label></th>
        <td>
            <input type="number" id="flosc_free_lesson_count" name="flosc_free_lesson_count" value="<?php echo esc_attr($free_lesson_count); ?>" min="1" max="50" class="small-text">
            <p class="description">Number of free lessons to give guests. (For "Fixed Number" mode)</p>
        </td>
    </tr>
    <tr id="flosc_free_lesson_proportion_row">
        <th scope="row"><label for="flosc_free_lesson_proportion">Free Lesson Proportion</label></th>
        <td>
            <select name="flosc_free_lesson_proportion" id="flosc_free_lesson_proportion">
                <option value="1/5" <?php selected($free_lesson_proportion, '1/5'); ?>>1/5 of missed lessons</option>
                <option value="1/4" <?php selected($free_lesson_proportion, '1/4'); ?>>1/4 of missed lessons</option>
                <option value="1/3" <?php selected($free_lesson_proportion, '1/3'); ?>>1/3 of missed lessons</option>
                <option value="1/2" <?php selected($free_lesson_proportion, '1/2'); ?>>1/2 of missed lessons</option>
            </select>
            <p class="description">Proportion of missed quiz items to give as free lessons. (For "Proportion" mode)</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="flosc_guest_access_days">Guest Access Duration</label></th>
        <td>
            <input type="number" id="flosc_guest_access_days" name="flosc_guest_access_days" value="<?php echo esc_attr($guest_access_days); ?>" min="0" max="365" class="small-text"> days
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