<?php
/**
 * FLOSC Quiz Configuration Tab
 * 
 * Configures quiz type and settings:
 * - Quiz type selection (simple scoring, pronunciation analysis, etc.)
 * - Multiple quiz variants (A, B, C) with rotation
 * - Quiz-specific content and questions
 * - Type-specific settings (audio, STT, AI requirements)
 * - Response templates based on score ranges
 * - Freeline availability conditions
 * 
 * QUIZ ROTATION SYSTEM:
 * - Configure multiple quiz variants (Quiz A, Quiz B, Quiz C)
 * - Rotation pattern: A → AB → ABC (progressive availability)
 * - First visitor gets Quiz A only
 * - Second visitor gets random from [A, B]
 * - Third visitor gets random from [A, B, C]
 * - Freeline phase can restrict which quizzes are available via conditions
 * 
 * Uses FLOSC_Quiz_Type_Factory pattern for extensibility.
 */

if (!defined('ABSPATH')) exit;

// Get quiz rotation settings
$quiz_variants = get_option('flosc_quiz_variants', ['A' => ['enabled' => true, 'condition' => '']]);
$rotation_mode = get_option('flosc_quiz_rotation_mode', 'progressive'); // 'progressive', 'random', 'sequential'

$active_quiz_type_id = get_option('flosc_quiz_type', 'simple_scoring');
$active_quiz_type = FLOSC_Quiz_Type_Factory::get_quiz_type($active_quiz_type_id);
$all_quiz_types = FLOSC_Quiz_Type_Factory::get_all_quiz_types();

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
<div class="flosc-quiz-config">
    <h2>Quiz Type Configuration</h2>
    <p>Choose a quiz type and configure its settings. FLOSC supports multiple quiz formats out of the box.</p>

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
