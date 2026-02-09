<?php
/**
 * FLOSC Quiz Configuration Tab (v9.3.4)
 * 
 * ENHANCED QUIZ SYSTEM with:
 * - Active quiz selection (enable/disable individual quizzes)
 * - Native FLOSC quizzes (Text Sequence, Audio Pronunciation, Multiple Choice)
 * - Third-party plugin compatibility (Wp-Pro-Quiz, LearnDash, QSM)
 * - Quiz builder foundation for custom quizzes
 * - Multiple quiz variants (A, B, C) with rotation
 * - Quiz-specific content and questions
 * - Type-specific settings (audio, STT, AI requirements)
 * - Response templates based on score ranges
 * - Freeline availability conditions
 * 
 * @package FLOSC
 * @version 1.2.9
 */

if (!defined('ABSPATH')) exit;

// v1.2.9: Output tab header
flosc_tab_header('❓', 'Quiz');

// Get quiz rotation settings
$quiz_variants = get_option('flosc_quiz_variants', ['A' => ['enabled' => true, 'condition' => '']]);
$rotation_mode = get_option('flosc_quiz_rotation_mode', 'progressive'); // 'progressive', 'random', 'sequential'

$active_quiz_type_id = get_option('flosc_quiz_type', 'flosc_sample_text_based_quiz');
$active_quiz_type = FLOSC_Quiz_Type_Factory::get_quiz_type($active_quiz_type_id);
$all_quiz_types = FLOSC_Quiz_Type_Factory::get_all_quiz_types();

// Get enabled quizzes array
$enabled_quizzes = get_option('flosc_enabled_quizzes', ['flosc_sample_text_based_quiz']);
if (!is_array($enabled_quizzes)) $enabled_quizzes = ['flosc_sample_text_based_quiz'];

// Plugin compatibility detection
$detected_plugins = [];
if (class_exists('WpProQuiz_Controller_Quiz')) {
    $detected_plugins['wp_pro_quiz'] = ['name' => 'Wp-Pro-Quiz', 'status' => 'active'];
} else {
    $detected_plugins['wp_pro_quiz'] = ['name' => 'Wp-Pro-Quiz', 'status' => 'not_installed'];
}
if (defined('LEARNDASH_VERSION')) {
    $detected_plugins['learndash'] = ['name' => 'LearnDash', 'status' => 'active'];
} else {
    $detected_plugins['learndash'] = ['name' => 'LearnDash', 'status' => 'not_installed'];
}
if (class_exists('QSM_Quiz') || function_exists('qsm_register_quiz_setting')) {
    $detected_plugins['qsm'] = ['name' => 'Quiz & Survey Master', 'status' => 'active'];
} else {
    $detected_plugins['qsm'] = ['name' => 'Quiz & Survey Master', 'status' => 'not_installed'];
}

// Safety: fallback if factory returned null or no types registered
if (!$active_quiz_type) {
    if (!empty($all_quiz_types)) {
        // Pick the first available quiz type as fallback
        $first_key = array_key_first($all_quiz_types);
        $active_quiz_type_id = $first_key;
        $active_quiz_type = $all_quiz_types[$first_key];
    } else {
        // As a last resort, render a friendly warning instead of fatal error
        echo '<div class="notice notice-error"><p>No quiz types are registered. Please reinstall or enable quiz types.</p></div>';
        return;
    }
}
?>
<style>
    .flosc-quiz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin: 20px 0; }
    .flosc-quiz-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 2px; padding: 16px; }
    .flosc-quiz-card.active { border-color: #2271b1; background: #f0f6fc; }
    .flosc-quiz-card:hover { border-color: #2271b1; }
    .flosc-quiz-card h4 { margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .flosc-quiz-card .icon { font-size: 20px; }
    .flosc-quiz-card .desc { color: #50575e; font-size: 13px; margin-bottom: 12px; line-height: 1.4; }
    .flosc-quiz-card .badge { font-size: 10px; padding: 2px 8px; border-radius: 2px; font-weight: 600; }
    .flosc-quiz-card .badge.native { background: #e7f3ff; color: #004085; }
    .flosc-quiz-card .badge.plugin { background: #fff3cd; color: #856404; }
    .flosc-quiz-card .badge.mvp { background: #d4edda; color: #155724; }
    .flosc-quiz-toggle { display: flex; align-items: center; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #c3c4c7; }
    .flosc-quiz-toggle input[type="checkbox"], .flosc-quiz-toggle input[type="radio"] { width: 16px; height: 16px; }
    .flosc-section-header { background: #1d2327; color: #f0f0f1; padding: 20px; border-radius: 2px; margin-bottom: 20px; }
    .flosc-section-header h2 { margin: 0 0 8px 0; }
    .flosc-section-header p { margin: 0; color: #a7aaad; }
    .flosc-plugin-status { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; padding: 2px 6px; border-radius: 2px; }
    .flosc-plugin-status.active { background: #d4edda; color: #155724; }
    .flosc-plugin-status.not_installed { background: #f0f0f1; color: #50575e; }
    .flosc-flow-preview { background: #f0f6fc; border: 1px solid #c3c4c7; border-radius: 2px; padding: 20px; display: flex; align-items: center; justify-content: space-around; flex-wrap: wrap; gap: 10px; margin: 20px 0; }
    .flosc-flow-step { text-align: center; min-width: 70px; }
    .flosc-flow-step .icon { font-size: 28px; }
    .flosc-flow-step .label { font-weight: 600; font-size: 12px; margin-top: 4px; }
    .flosc-flow-arrow { font-size: 20px; color: #787c82; }
</style>

<div class="flosc-quiz-config">
    <!-- Section Header -->
    <div class="flosc-section-header">
        <h2>Quiz System Configuration</h2>
        <p>Enable quizzes to power the FLOSC funnel: Quiz &rarr; Score &rarr; Login Gate &rarr; Content &rarr; Sale</p>
    </div>

    <!-- Funnel Flow Preview -->
    <div class="flosc-flow-preview">
        <div class="flosc-flow-step"><div class="icon">📝</div><div class="label">Quiz</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">📊</div><div class="label">Score</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🔐</div><div class="label">Login</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🎁</div><div class="label">Free Lesson</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">💰</div><div class="label">Offer</div></div>
        <div class="flosc-flow-arrow">→</div>
        <div class="flosc-flow-step"><div class="icon">🎓</div><div class="label">Content</div></div>
    </div>

    <!-- ============================================ -->
    <!-- ACTIVE QUIZZES SECTION -->
    <!-- ============================================ -->
    <h3>📋 Active Quizzes</h3>
    <p>Enable the quizzes you want to use. The first enabled quiz will be shown to visitors.</p>

    <div class="flosc-quiz-grid">
        <!-- Native FLOSC: Text Sequence Quiz (MVP) -->
        <div class="flosc-quiz-card <?php echo in_array('flosc_sample_text_based_quiz', $enabled_quizzes) ? 'active' : ''; ?>">
            <h4>
                <span class="icon">⌨️</span>
                Text Sequence
                <span class="badge native">NATIVE</span>
                <span class="badge mvp">MVP TEST</span>
            </h4>
            <p class="desc">Type the sequence: 1, 2, 3... 10. Perfect for testing the full FLOSC funnel flow.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_enabled_quizzes[]" value="flosc_sample_text_based_quiz" <?php checked(in_array('flosc_sample_text_based_quiz', $enabled_quizzes)); ?>>
                <label>Enabled</label>
            </div>
        </div>

        <!-- Native FLOSC: Audio Pronunciation Quiz -->
        <div class="flosc-quiz-card <?php echo in_array('flosc_sample_audio_quiz', $enabled_quizzes) ? 'active' : ''; ?>">
            <h4>
                <span class="icon">🎤</span>
                Audio Pronunciation
                <span class="badge native">NATIVE</span>
            </h4>
            <p class="desc">Speech-to-text quiz. User speaks the sequence and AI analyzes pronunciation accuracy.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_enabled_quizzes[]" value="flosc_sample_audio_quiz" <?php checked(in_array('flosc_sample_audio_quiz', $enabled_quizzes)); ?>>
                <label>Enabled</label>
            </div>
        </div>

        <!-- Native FLOSC: Multiple Choice -->
        <div class="flosc-quiz-card <?php echo in_array('multiplechoice', $enabled_quizzes) ? 'active' : ''; ?>">
            <h4>
                <span class="icon">☑️</span>
                Multiple Choice
                <span class="badge native">NATIVE</span>
            </h4>
            <p class="desc">Classic format with 2-4 options per question. Define questions in Quiz Content below.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_enabled_quizzes[]" value="multiplechoice" <?php checked(in_array('multiplechoice', $enabled_quizzes)); ?>>
                <label>Enabled</label>
            </div>
        </div>

        <!-- Native FLOSC: True/False -->
        <div class="flosc-quiz-card <?php echo in_array('truefalse', $enabled_quizzes) ? 'active' : ''; ?>">
            <h4>
                <span class="icon">✓✗</span>
                True/False
                <span class="badge native">NATIVE</span>
            </h4>
            <p class="desc">Simple true/false statements. Fast to complete, good for quick assessments.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_enabled_quizzes[]" value="truefalse" <?php checked(in_array('truefalse', $enabled_quizzes)); ?>>
                <label>Enabled</label>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- THIRD-PARTY PLUGIN COMPATIBILITY -->
    <!-- ============================================ -->
    <h3 style="margin-top: 30px;">🔌 Third-Party Quiz Plugin Integration</h3>
    <p>FLOSC can capture scores from popular WordPress quiz plugins and use them in the funnel.</p>

    <div class="flosc-quiz-grid">
        <!-- Wp-Pro-Quiz -->
        <div class="flosc-quiz-card">
            <h4>
                <span class="icon">📊</span>
                Wp-Pro-Quiz
                <span class="badge plugin">PLUGIN</span>
                <span class="flosc-plugin-status <?php echo $detected_plugins['wp_pro_quiz']['status']; ?>">
                    <?php echo $detected_plugins['wp_pro_quiz']['status'] === 'active' ? '✓ Active' : '○ Not Installed'; ?>
                </span>
            </h4>
            <p class="desc">Free quiz plugin with 7 question types. FLOSC hooks into completion to capture scores.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_wpq_integration" value="1" <?php checked(get_option('flosc_wpq_integration', 0)); ?> <?php echo $detected_plugins['wp_pro_quiz']['status'] !== 'active' ? 'disabled' : ''; ?>>
                <label>Enable Integration</label>
            </div>
        </div>

        <!-- LearnDash -->
        <div class="flosc-quiz-card">
            <h4>
                <span class="icon">🎓</span>
                LearnDash
                <span class="badge plugin">PLUGIN</span>
                <span class="flosc-plugin-status <?php echo $detected_plugins['learndash']['status']; ?>">
                    <?php echo $detected_plugins['learndash']['status'] === 'active' ? '✓ Active' : '○ Not Installed'; ?>
                </span>
            </h4>
            <p class="desc">Premium LMS with robust quizzes. FLOSC captures completion for funnel progression.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_ld_integration" value="1" <?php checked(get_option('flosc_ld_integration', 0)); ?> <?php echo $detected_plugins['learndash']['status'] !== 'active' ? 'disabled' : ''; ?>>
                <label>Enable Integration</label>
            </div>
        </div>

        <!-- Quiz & Survey Master -->
        <div class="flosc-quiz-card">
            <h4>
                <span class="icon">📋</span>
                Quiz & Survey Master
                <span class="badge plugin">PLUGIN</span>
                <span class="flosc-plugin-status <?php echo $detected_plugins['qsm']['status']; ?>">
                    <?php echo $detected_plugins['qsm']['status'] === 'active' ? '✓ Active' : '○ Not Installed'; ?>
                </span>
            </h4>
            <p class="desc">Free quiz + survey plugin. FLOSC captures completion to trigger login gate.</p>
            <div class="flosc-quiz-toggle">
                <input type="checkbox" name="flosc_qsm_integration" value="1" <?php checked(get_option('flosc_qsm_integration', 0)); ?> <?php echo $detected_plugins['qsm']['status'] !== 'active' ? 'disabled' : ''; ?>>
                <label>Enable Integration</label>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- QUIZ TYPE SELECTION (Legacy) -->
    <!-- ============================================ -->
    <h3 style="margin-top: 30px;">⚙️ Primary Quiz Settings</h3>
    <p>Configure the primary quiz type and content.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="flosc_quiz_type">Quiz Type</label></th>
            <td>
                <select name="flosc_quiz_type" id="flosc_quiz_type" class="regular-text">
                    <?php foreach ($all_quiz_types as $quiz_id => $quiz_type): ?>
                        <option value="<?php echo esc_attr($quiz_id); ?>" <?php selected($active_quiz_type_id, $quiz_id); ?>>
                            <?php echo esc_html($quiz_type->get_icon() . ' ' . $quiz_type->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <strong><?php echo esc_html($active_quiz_type->get_description()); ?></strong><br>
                    Requirements:
                    <?php if ($active_quiz_type->needs_audio()): ?>
                        Audio Recording
                    <?php endif; ?>
                    <?php if ($active_quiz_type->needs_stt()): ?>
                        + Speech-to-Text
                    <?php endif; ?>
                    <?php if ($active_quiz_type->needs_ai_analysis()): ?>
                        + AI Analysis
                    <?php endif; ?>
                    <?php if (!$active_quiz_type->needs_audio() && !$active_quiz_type->needs_stt() && !$active_quiz_type->needs_ai_analysis()): ?>
                        None (Works with default configuration)
                    <?php endif; ?>
                </p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flosc_quiz_content_<?php echo esc_attr($active_quiz_type_id); ?>">Quiz Content</label></th>
            <td>
                <textarea
                    id="flosc_quiz_content_<?php echo esc_attr($active_quiz_type_id); ?>"
                    name="flosc_quiz_content_<?php echo esc_attr($active_quiz_type_id); ?>"
                    rows="8"
                    class="large-text code"
                    placeholder="<?php echo esc_attr($active_quiz_type->get_default_content()); ?>"
                ><?php echo esc_textarea(get_option('flosc_quiz_content_' . $active_quiz_type_id, $active_quiz_type->get_default_content())); ?></textarea>
                <p class="description">
                    <strong>Instructions:</strong> <?php echo esc_html($active_quiz_type->get_instructions()); ?>
                </p>
            </td>
        </tr>

        <!-- ============================================ -->
        <!-- QUIZ ROTATION & VARIANTS -->
        <!-- ============================================ -->
        <tr>
            <td colspan="2">
                <h3 style="margin-top: 30px;">Quiz Rotation & Variants <span style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">BACKEND NEEDED</span></h3>
                <p>Configure multiple quiz variants and control which quizzes visitors see based on rotation and conditions.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="flosc_quiz_rotation_mode">Rotation Mode</label></th>
            <td>
                <select name="flosc_quiz_rotation_mode" id="flosc_quiz_rotation_mode">
                    <option value="progressive" <?php selected($rotation_mode, 'progressive'); ?>>Progressive (A → AB → ABC)</option>
                    <option value="random" <?php selected($rotation_mode, 'random'); ?>>Random (all enabled quizzes)</option>
                    <option value="sequential" <?php selected($rotation_mode, 'sequential'); ?>>Sequential (A, B, C, A, B, C...)</option>
                </select>
                <p class="description">
                    <strong>Progressive:</strong> First visitor sees Quiz A, second sees random from A/B, third sees random from A/B/C<br>
                    <strong>Random:</strong> Each visitor gets random quiz from all enabled variants<br>
                    <strong>Sequential:</strong> Visitors get quizzes in order (A, B, C, then back to A)
                </p>
                
                <div style="background: #f0f0f1; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 12px;">
                    <strong>Backend Implementation:</strong><br>
                    /* PSEUDOCODE - Developer Reference */<br>
                    <br>
                    function flosc_get_quiz_for_visitor($rotation_mode, $variants) {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$visitor_count = get_option('flosc_total_quiz_visitors', 0);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;update_option('flosc_total_quiz_visitors', $visitor_count + 1);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;// Get enabled quizzes that pass freeline conditions<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$available = array_filter($variants, function($v) {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;if (!$v['enabled']) return false;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;if (flosc_get_user_phase() === 'freeline' && !empty($v['freeline_condition'])) {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return flosc_evaluate_condition($v['freeline_condition']);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;}<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return true;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;});<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;if ($rotation_mode === 'progressive') {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;// Expand pool as visitor count increases<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$pool_size = min($visitor_count, count($available));<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$pool = array_slice(array_keys($available), 0, $pool_size);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return $pool[array_rand($pool)];<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;} elseif ($rotation_mode === 'sequential') {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$keys = array_keys($available);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return $keys[$visitor_count % count($keys)];<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;} else { // random<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;return array_rand($available);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;}<br>
                    }
                </div>
            </td>
        </tr>

        <!-- Quiz Variants Configuration -->
        <tr>
            <td colspan="2">
                <h4>Quiz Variants</h4>
                <p class="description">Configure multiple quiz versions. Each can have different content and availability conditions.</p>
                
                <table style="width: 100%; border: 1px solid #ddd; margin-top: 15px;">
                    <thead style="background: #f0f0f1;">
                        <tr>
                            <th style="padding: 10px; text-align: left; width: 80px;">Variant</th>
                            <th style="padding: 10px; text-align: left; width: 100px;">Enabled</th>
                            <th style="padding: 10px; text-align: left;">Freeline Condition</th>
                            <th style="padding: 10px; text-align: left; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $variant_letters = ['A', 'B', 'C', 'D', 'E'];
                        foreach ($variant_letters as $letter):
                            $variant_key = 'quiz_' . strtolower($letter);
                            $variant_enabled = get_option('flosc_quiz_variant_' . $letter . '_enabled', $letter === 'A');
                            $variant_condition = get_option('flosc_quiz_variant_' . $letter . '_freeline_condition', '');
                        ?>
                        <tr>
                            <td style="padding: 10px; border-top: 1px solid #ddd;">
                                <strong>Quiz <?php echo $letter; ?></strong>
                            </td>
                            <td style="padding: 10px; border-top: 1px solid #ddd;">
                                <input 
                                    type="checkbox" 
                                    name="flosc_quiz_variant_<?php echo $letter; ?>_enabled" 
                                    value="1" 
                                    <?php checked($variant_enabled, true); ?>
                                >
                            </td>
                            <td style="padding: 10px; border-top: 1px solid #ddd;">
                                <input 
                                    type="text" 
                                    name="flosc_quiz_variant_<?php echo $letter; ?>_freeline_condition" 
                                    value="<?php echo esc_attr($variant_condition); ?>" 
                                    class="regular-text"
                                    placeholder="e.g., session.utm_source === 'google'"
                                    style="width: 100%;"
                                >
                                <p class="description" style="margin: 5px 0 0 0; font-size: 11px;">
                                    Leave empty to always show in freeline. Use JavaScript expressions to restrict availability.
                                </p>
                            </td>
                            <td style="padding: 10px; border-top: 1px solid #ddd;">
                                <a href="#quiz-content-<?php echo $letter; ?>" class="button button-small">Edit Content</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="background: #e7f3ff; border-left: 4px solid #0073aa; padding: 15px; margin: 20px 0;">
                    <h4 style="margin-top: 0;">How Quiz Rotation Works</h4>
                    <ul style="margin: 10px 0 0 20px; line-height: 1.6;">
                        <li><strong>Progressive Mode:</strong> First visitor gets Quiz A. Second visitor gets random choice from [A, B]. Third gets random from [A, B, C]. Naturally expands exposure.</li>
                        <li><strong>Random Mode:</strong> Every visitor gets a random quiz from all enabled variants. Equal distribution.</li>
                        <li><strong>Sequential Mode:</strong> Visitors get quizzes in order: 1st=A, 2nd=B, 3rd=C, 4th=A, etc. Predictable rotation.</li>
                        <li><strong>Freeline Conditions:</strong> In freeline phase only, quizzes can be restricted by conditions (e.g., show Quiz B only to Google traffic). Other phases ignore conditions.</li>
                    </ul>
                </div>
            </td>
        </tr>

        <!-- Quiz Content for Each Variant -->
        <tr>
            <td colspan="2">
                <h3 style="margin-top: 30px;">Quiz Content by Variant</h3>
                <p>Configure the content for each quiz variant. Content format depends on selected quiz type above.</p>
            </td>
        </tr>

        <?php foreach ($variant_letters as $letter): 
            $variant_enabled = get_option('flosc_quiz_variant_' . $letter . '_enabled', $letter === 'A');
            $variant_content = get_option('flosc_quiz_content_variant_' . $letter, $active_quiz_type->get_default_content());
        ?>
        <tr id="quiz-content-<?php echo $letter; ?>" style="<?php echo !$variant_enabled ? 'opacity: 0.5;' : ''; ?>">
            <th scope="row">
                <label for="flosc_quiz_content_variant_<?php echo $letter; ?>">
                    Quiz <?php echo $letter; ?> Content
                    <?php if (!$variant_enabled): ?>
                        <span style="color: #999; font-weight: normal;">(Disabled)</span>
                    <?php endif; ?>
                </label>
            </th>
            <td>
                <textarea
                    id="flosc_quiz_content_variant_<?php echo $letter; ?>"
                    name="flosc_quiz_content_variant_<?php echo $letter; ?>"
                    rows="8"
                    class="large-text code"
                    placeholder="<?php echo esc_attr($active_quiz_type->get_default_content()); ?>"
                    <?php echo !$variant_enabled ? 'disabled' : ''; ?>
                ><?php echo esc_textarea($variant_content); ?></textarea>
                <p class="description">
                    <strong>Quiz <?php echo $letter; ?>:</strong> <?php echo esc_html($active_quiz_type->get_instructions()); ?>
                </p>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php
        // Quiz-specific settings
        $settings_fields = $active_quiz_type->get_settings_fields();
        if (!empty($settings_fields)):
        ?>
            <tr>
                <td colspan="2">
                    <h3>Quiz-Specific Settings</h3>
                </td>
            </tr>
            <?php foreach ($settings_fields as $field_key => $field_config):
                $field_name = 'flosc_quiz_' . $active_quiz_type_id . '_' . $field_key;
                $field_value = get_option($field_name, $field_config['default'] ?? '');
            ?>
                <tr>
                    <th scope="row"><label for="<?php echo esc_attr($field_name); ?>"><?php echo esc_html($field_config['label']); ?></label></th>
                    <td>
                        <?php if ($field_config['type'] === 'checkbox'): ?>
                            <input
                                type="checkbox"
                                id="<?php echo esc_attr($field_name); ?>"
                                name="<?php echo esc_attr($field_name); ?>"
                                value="1"
                                <?php checked($field_value, 1); ?>
                            >
                        <?php elseif ($field_config['type'] === 'select'): ?>
                            <select id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>">
                                <?php foreach ($field_config['options'] as $opt_value => $opt_label): ?>
                                    <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($field_value, $opt_value); ?>>
                                        <?php echo esc_html($opt_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input
                                type="text"
                                id="<?php echo esc_attr($field_name); ?>"
                                name="<?php echo esc_attr($field_name); ?>"
                                value="<?php echo esc_attr($field_value); ?>"
                                class="regular-text"
                            >
                        <?php endif; ?>
                        <?php if (!empty($field_config['description'])): ?>
                            <p class="description"><?php echo esc_html($field_config['description']); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php
        // Response templates
        $templates = $active_quiz_type->get_default_response_templates();
        ?>
        <tr>
            <td colspan="2">
                <h3>Response Templates</h3>
                <p>Customize the feedback messages shown to users based on their score. Use placeholders: <code>{score}</code>, <code>{total_correct}</code>, <code>{total_possible}</code>, <code>{lesson_recommendations}</code></p>
            </td>
        </tr>
        <?php foreach ($templates as $template_key => $default_template):
            $template_name = 'flosc_quiz_' . $active_quiz_type_id . '_template_' . $template_key;
            $template_value = get_option($template_name, $default_template);
        ?>
            <tr>
                <th scope="row"><label for="<?php echo esc_attr($template_name); ?>">Score Range: <?php echo esc_html($template_key); ?>%</label></th>
                <td>
                    <textarea
                        id="<?php echo esc_attr($template_name); ?>"
                        name="<?php echo esc_attr($template_name); ?>"
                        rows="4"
                        class="large-text"
                    ><?php echo esc_textarea($template_value); ?></textarea>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
