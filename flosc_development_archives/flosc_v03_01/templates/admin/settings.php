<?php
/**
 * FLOSC Admin Settings Page
 */

if (!defined('ABSPATH')) exit;

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'product';
$slug = get_option('flosc_app_slug', 'app');
$app_url = home_url('/' . $slug . '/');
?>
<div class="wrap flosc-admin">
    <h1>FLOSC Settings</h1>
    
    <div class="flosc-admin-header">
        <p>Your app is live at: <a href="<?php echo esc_url($app_url); ?>" target="_blank"><?php echo esc_html($app_url); ?></a></p>
    </div>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=flosc-settings&tab=product" class="nav-tab <?php echo $active_tab === 'product' ? 'nav-tab-active' : ''; ?>">Product</a>
        <a href="?page=flosc-settings&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Provider</a>
        <a href="?page=flosc-settings&tab=stt" class="nav-tab <?php echo $active_tab === 'stt' ? 'nav-tab-active' : ''; ?>">Speech-to-Text</a>
        <a href="?page=flosc-settings&tab=quiz" class="nav-tab <?php echo $active_tab === 'quiz' ? 'nav-tab-active' : ''; ?>">Quiz</a>
    </nav>
    
    <form method="post" action="options.php">
        <?php settings_fields('flosc_settings'); ?>
        
        <?php if ($active_tab === 'product'): ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_app_slug">App URL Slug</label></th>
                <td>
                    <input type="text" id="flosc_app_slug" name="flosc_app_slug" value="<?php echo esc_attr(get_option('flosc_app_slug', 'app')); ?>" class="regular-text">
                    <p class="description">Your app will be at: <?php echo esc_html(home_url('/')); ?><strong>[slug]</strong>/</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_product_name">Product Name</label></th>
                <td>
                    <input type="text" id="flosc_product_name" name="flosc_product_name" value="<?php echo esc_attr(get_option('flosc_product_name', '')); ?>" class="regular-text" placeholder="e.g., LeSAEp">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_product_tagline">Tagline</label></th>
                <td>
                    <input type="text" id="flosc_product_tagline" name="flosc_product_tagline" value="<?php echo esc_attr(get_option('flosc_product_tagline', '')); ?>" class="regular-text" placeholder="e.g., Your AI pronunciation coach">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_product_emoji">Logo Emoji</label></th>
                <td>
                    <input type="text" id="flosc_product_emoji" name="flosc_product_emoji" value="<?php echo esc_attr(get_option('flosc_product_emoji', '🎯')); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_primary_color">Primary Color</label></th>
                <td>
                    <input type="color" id="flosc_primary_color" name="flosc_primary_color" value="<?php echo esc_attr(get_option('flosc_primary_color', '#4f46e5')); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_ga4_id">Google Analytics ID</label></th>
                <td>
                    <input type="text" id="flosc_ga4_id" name="flosc_ga4_id" value="<?php echo esc_attr(get_option('flosc_ga4_id', '')); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                </td>
            </tr>
        </table>
        
        <?php elseif ($active_tab === 'ai'): ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_ai_provider">AI Provider</label></th>
                <td>
                    <select name="flosc_ai_provider" id="flosc_ai_provider">
                        <option value="ivr" <?php selected(get_option('flosc_ai_provider'), 'ivr'); ?>>IVR (Scripted - Free)</option>
                        <option value="openai" <?php selected(get_option('flosc_ai_provider'), 'openai'); ?>>OpenAI (GPT-4o-mini)</option>
                        <option value="anthropic" <?php selected(get_option('flosc_ai_provider'), 'anthropic'); ?>>Anthropic (Claude)</option>
                        <option value="xai" <?php selected(get_option('flosc_ai_provider'), 'xai'); ?>>xAI (Grok)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_openai_api_key">OpenAI API Key</label></th>
                <td>
                    <input type="password" id="flosc_openai_api_key" name="flosc_openai_api_key" value="<?php echo esc_attr(get_option('flosc_openai_api_key', '')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_anthropic_api_key">Anthropic API Key</label></th>
                <td>
                    <input type="password" id="flosc_anthropic_api_key" name="flosc_anthropic_api_key" value="<?php echo esc_attr(get_option('flosc_anthropic_api_key', '')); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        
        <?php elseif ($active_tab === 'stt'): ?>
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
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_assemblyai_api_key">AssemblyAI API Key</label></th>
                <td>
                    <input type="password" id="flosc_assemblyai_api_key" name="flosc_assemblyai_api_key" value="<?php echo esc_attr(get_option('flosc_assemblyai_api_key', '')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_deepgram_api_key">Deepgram API Key</label></th>
                <td>
                    <input type="password" id="flosc_deepgram_api_key" name="flosc_deepgram_api_key" value="<?php echo esc_attr(get_option('flosc_deepgram_api_key', '')); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        
        <?php elseif ($active_tab === 'quiz'):
            $active_quiz_type_id = get_option('flosc_quiz_type', 'simple_scoring');
            $active_quiz_type = FLOSC_Quiz_Type_Factory::get_quiz_type($active_quiz_type_id);
            $all_quiz_types = FLOSC_Quiz_Type_Factory::get_all_quiz_types();
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
        
        <?php endif; ?>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.flosc-admin { max-width: 900px; }
.flosc-admin-header { background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 20px; }
</style>
