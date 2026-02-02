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
 */

if (!defined('ABSPATH')) exit;

// Get categories for dropdown
$categories = get_categories(['hide_empty' => false]);
$current_category = get_option('flosc_lessons_category', '');

// Get offers for OTO dropdown
$offers = flosc()->sale()->offers()->get_all_offers();
$current_oto = get_option('flosc_oto_offer_id', '');
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
