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
        <a href="?page=flosc-settings&tab=ivr-messages" class="nav-tab <?php echo $active_tab === 'ivr-messages' ? 'nav-tab-active' : ''; ?>">IVR Messages</a>
        <a href="?page=flosc-settings&tab=ai" class="nav-tab <?php echo $active_tab === 'ai' ? 'nav-tab-active' : ''; ?>">AI Configuration</a>
        <a href="?page=flosc-settings&tab=style" class="nav-tab <?php echo $active_tab === 'style' ? 'nav-tab-active' : ''; ?>">Style Settings</a>
        <a href="?page=flosc-settings&tab=quiz" class="nav-tab <?php echo $active_tab === 'quiz' ? 'nav-tab-active' : ''; ?>">Quiz</a>
        <a href="?page=flosc-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">Email</a>
        <a href="?page=flosc-settings&tab=ai-knowledge" class="nav-tab <?php echo $active_tab === 'ai-knowledge' ? 'nav-tab-active' : ''; ?>">AI Knowledge</a>
        <a href="?page=flosc-settings&tab=offers" class="nav-tab <?php echo $active_tab === 'offers' ? 'nav-tab-active' : ''; ?>">Offers</a>
        <a href="?page=flosc-settings&tab=payments" class="nav-tab <?php echo $active_tab === 'payments' ? 'nav-tab-active' : ''; ?>">Payments</a>
        <a href="?page=flosc-settings&tab=lessons" class="nav-tab <?php echo $active_tab === 'lessons' ? 'nav-tab-active' : ''; ?>">Lessons</a>
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

        <?php elseif ($active_tab === 'ivr-messages'):
            $ivr_config = FLOSC_IVR_Parser::flosc_instance()->get_flosc_config();
        ?>
        </form>
        <h2>IVR Messages Configuration</h2>
        <p><strong>IVR (Interactive Voice Response)</strong> is our framework for delivering contextual messages based on user behavior, FLOSC phase, quiz scores, and conditions.</p>

        <div class="flosc-info-box" style="margin-bottom: 20px;">
            <strong>Understanding FLOSC Phases:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong>Freeline:</strong> Visitor (not logged in) - Goal: Encourage them to take the quiz</li>
                <li><strong>Login:</strong> Post-quiz visitors + Logged-in users - Goal: Deliver free lesson, present offer</li>
                <li><strong>Offer:</strong> Sales pitch - Goal: Encourage purchase</li>
                <li><strong>Sale:</strong> Post-purchase - Goal: Onboard to content</li>
                <li><strong>Content:</strong> Ongoing access - Goal: Support and encourage</li>
            </ul>
        </div>


        <!-- IVR Messages Display -->
        <h3>IVR Messages <a href="<?php echo admin_url('admin.php?page=flosc-ivr'); ?>" class="button button-secondary" style="margin-left: 10px;">Edit Messages</a></h3>
        <p class="description">All messages are configured in ivr.md markdown format. Click "Edit Messages" to modify.</p>

        <div id="flosc-ivr-display">
            <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $phase):
                $phase_messages = $ivr_config['phases'][$phase] ?? [];
            ?>
            <div class="ivr-phase-section" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; text-transform: capitalize;"><?php echo esc_html($phase); ?> Phase
                    <span style="font-weight: normal; font-size: 14px;">(<?php echo count($phase_messages); ?> messages)</span>
                </h4>

                <?php if (empty($phase_messages)): ?>
                    <p style="color: #999;">No messages configured for this phase.</p>
                <?php else: ?>
                    <?php foreach ($phase_messages as $msg_id):
                        $message = $ivr_config['messages'][$msg_id] ?? null;
                        if (!$message) continue;
                    ?>
                    <div style="background: #fff; padding: 10px; margin-top: 10px; border: 1px solid #eee; border-left: 3px solid #0073aa;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                            <strong><?php echo esc_html($message['name']); ?></strong>
                            <span style="color: #666; font-size: 12px;"><?php echo esc_html($message['type'] ?? 'auto'); ?></span>
                        </div>
                        <?php if (!empty($message['condition'])): ?>
                            <div style="background: #f0f0f1; padding: 5px; border-radius: 3px; font-size: 12px; margin-bottom: 8px; font-family: monospace;">
                                Condition: <?php echo esc_html($message['condition']); ?>
                            </div>
                        <?php endif; ?>
                        <div style="color: #333;">
                            <?php echo esc_html(strlen($message['content']) > 150 ? substr($message['content'], 0, 150) . '...' : $message['content']); ?>
                        </div>
                        <?php if (!empty($message['action'])): ?>
                            <div style="margin-top: 5px; color: #0073aa; font-size: 12px;">
                                ➜ Action: <?php echo esc_html($message['action']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="post" action="options.php">
        <?php settings_fields('flosc_settings'); ?>

        <?php elseif ($active_tab === 'ai'): 
            $base_prompt = get_option('flosc_ai_base_prompt', '');
            if (empty($base_prompt)) {
                $base_prompt = "You are " . get_option('flosc_product_name', 'FLOSC App') . ", an AI assistant. Your mission is to help users through personalized guidance. Be helpful, friendly, specific, and action-oriented.";
            }
            $ai_provider = get_option('flosc_ai_provider', 'ivr');
        ?>
        <h2>AI Configuration</h2>
        <p class="description">Configure your AI assistant's personality, API keys, and test connection.</p>

        <!-- Connection Test -->
        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <h3>Test AI Connection</h3>
            <p class="description">Verify that your AI provider is configured correctly and responding.</p>
            <button type="button" id="test-ai-connection" class="button button-large">Test Connection</button>
            <div id="test-results" style="margin-top: 15px; display: none;">
                <div id="test-status"></div>
                <div id="test-details" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; white-space: pre-wrap;"></div>
            </div>
            <div id="test-loading" style="margin-top: 15px; display: none;">
                <span class="spinner is-active" style="float: none; margin-right: 10px;"></span>
                Testing connection...
            </div>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_ai_provider">AI Provider</label></th>
                <td>
                    <select name="flosc_ai_provider" id="flosc_ai_provider">
                        <option value="ivr" <?php selected($ai_provider, 'ivr'); ?>>IVR (Scripted - Free)</option>
                        <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT-4o-mini)</option>
                        <option value="anthropic" <?php selected($ai_provider, 'anthropic'); ?>>Anthropic (Claude)</option>
                        <option value="xai" <?php selected($ai_provider, 'xai'); ?>>xAI (Grok)</option>
                    </select>
                    <p class="description">IVR is free but limited. AI providers require API keys.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_openai_api_key">OpenAI API Key</label></th>
                <td>
                    <input type="password" id="flosc_openai_api_key" name="flosc_openai_api_key" value="<?php echo esc_attr(get_option('flosc_openai_api_key', '')); ?>" class="regular-text">
                    <p class="description">Get your key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_anthropic_api_key">Anthropic API Key</label></th>
                <td>
                    <input type="password" id="flosc_anthropic_api_key" name="flosc_anthropic_api_key" value="<?php echo esc_attr(get_option('flosc_anthropic_api_key', '')); ?>" class="regular-text">
                    <p class="description">Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_xai_api_key">xAI API Key</label></th>
                <td>
                    <input type="password" id="flosc_xai_api_key" name="flosc_xai_api_key" value="<?php echo esc_attr(get_option('flosc_xai_api_key', '')); ?>" class="regular-text">
                    <p class="description">Get your key at <a href="https://console.x.ai" target="_blank">console.x.ai</a></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_ai_base_prompt">Base System Prompt</label></th>
                <td>
                    <textarea id="flosc_ai_base_prompt" name="flosc_ai_base_prompt" rows="6" class="large-text"><?php echo esc_textarea($base_prompt); ?></textarea>
                    <p class="description">The AI's base personality and instructions. Phase-specific prompts are added automatically.</p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('#test-ai-connection').on('click', function() {
                var $btn = $(this);
                var $loading = $('#test-loading');
                var $results = $('#test-results');
                var $status = $('#test-status');
                var $details = $('#test-details');

                $btn.prop('disabled', true);
                $loading.show();
                $results.hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'flosc_test_ai_connection',
                        nonce: '<?php echo wp_create_nonce('flosc_test_ai'); ?>'
                    },
                    success: function(response) {
                        $loading.hide();
                        $results.show();
                        
                        if (response.success) {
                            $status.html('<span style="color: #46b450; font-weight: bold;">✓ Connection Successful!</span>');
                            $details.text('Provider: ' + response.data.provider + '\nResponse: ' + response.data.response);
                        } else {
                            $status.html('<span style="color: #dc3232; font-weight: bold;">✗ Connection Failed</span>');
                            $details.text(response.data.message || 'Unknown error');
                        }
                    },
                    error: function() {
                        $loading.hide();
                        $results.show();
                        $status.html('<span style="color: #dc3232; font-weight: bold;">✗ Request Failed</span>');
                        $details.text('Could not reach the server. Please try again.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>

        <?php elseif ($active_tab === 'style'):
            $chat_font = get_option('flosc_chat_style_font', 'system');
            $chat_theme = get_option('flosc_chat_style_theme', 'default');
        ?>
        <h2>Chat Style Settings</h2>
        <p>Configure the appearance and typography of the chat interface.</p>

        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_chat_style_font">Font Family</label></th>
                <td>
                    <select name="flosc_chat_style_font" id="flosc_chat_style_font">
                        <option value="system" <?php selected($chat_font, 'system'); ?>>System Default (-apple-system, Segoe UI)</option>
                        <option value="ibm-plex-mono" <?php selected($chat_font, 'ibm-plex-mono'); ?>>IBM Plex Mono</option>
                        <option value="source-code-pro" <?php selected($chat_font, 'source-code-pro'); ?>>Source Code Pro</option>
                        <option value="inter" <?php selected($chat_font, 'inter'); ?>>Inter</option>
                        <option value="roboto-mono" <?php selected($chat_font, 'roboto-mono'); ?>>Roboto Mono</option>
                        <option value="fira-code" <?php selected($chat_font, 'fira-code'); ?>>Fira Code</option>
                    </select>
                    <p class="description">Changes the font used in the chat interface.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_chat_style_theme">Theme</label></th>
                <td>
                    <select name="flosc_chat_style_theme" id="flosc_chat_style_theme">
                        <option value="default" <?php selected($chat_theme, 'default'); ?>>Default (Light)</option>
                        <option value="dark" <?php selected($chat_theme, 'dark'); ?>>Dark</option>
                        <option value="high-contrast" <?php selected($chat_theme, 'high-contrast'); ?>>High Contrast</option>
                    </select>
                    <p class="description">Choose a color scheme for the chat interface.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button-primary">Save Style Settings</button>
        </p>

        <!-- Live Preview -->
        <h3 style="margin-top: 40px;">Live Preview</h3>
        <p>See how your selected font and theme will appear in the chat interface.</p>
        
        <div id="flosc-style-preview" style="margin-top: 20px; padding: 20px; border: 2px solid #ccc; border-radius: 8px; max-width: 700px;">
            <link rel="stylesheet" href="<?php echo FLOSC_PLUGIN_URL; ?>assets/css/chat-style-flosc.css">
            
            <div class="messages" data-flosc-font="<?php echo esc_attr($chat_font); ?>" data-flosc-theme="<?php echo esc_attr($chat_theme); ?>" style="background: var(--flosc-bg); padding: 20px; border-radius: 8px;">
                
                <!-- Assistant message -->
                <div class="message assistant">
                    <div class="message-avatar">AI</div>
                    <div class="message-content">
                        <div class="message-text">
                            <p>This is an assistant message showing the current theme and font. Notice the background color and text color.</p>
                        </div>
                    </div>
                </div>

                <!-- User message -->
                <div class="message user">
                    <div class="message-avatar">U</div>
                    <div class="message-content">
                        <div class="message-text">
                            <p>This is a user message showing the blue bubble with white text.</p>
                        </div>
                    </div>
                </div>

                <!-- Another assistant message -->
                <div class="message assistant">
                    <div class="message-avatar">AI</div>
                    <div class="message-content">
                        <div class="message-text">
                            <p>You can see the <strong>font family</strong>, <em>text styling</em>, and <code>inline code</code> appearance here.</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Live preview update
            $('#flosc_chat_style_font, #flosc_chat_style_theme').on('change', function() {
                var font = $('#flosc_chat_style_font').val();
                var theme = $('#flosc_chat_style_theme').val();
                
                $('#flosc-style-preview .messages')
                    .attr('data-flosc-font', font)
                    .attr('data-flosc-theme', theme);
            });
        });
        </script>

        <?php elseif ($active_tab === 'quiz'):
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

        <?php elseif ($active_tab === 'lessons'):
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
                    <p class="description">Offer shown after quiz completion. <a href="?page=flosc-offers">Manage Offers</a></p>
                </td>
            </tr>
        </table>
        
        <h3>How Lesson Mapping Works</h3>
        <div class="flosc-info-box">
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

        <?php elseif ($active_tab === 'email'):
            $product_name = get_option('flosc_product_name', 'FLOSC App');
            $default_subject = "Your {$product_name} Quiz Results: {score}%";
            $default_body = "Hi {name},

Thanks for taking the {product_name} quiz!

YOUR SCORE: {score}%

✅ Correct: {correct}
❌ Needs Practice: {incorrect}
{oto_section}
Your personalized learning path is ready. Log in to get your FREE lesson and start improving today!

{app_link}

Best,
The {product_name} Team";
        ?>
        <h2>Email Templates</h2>
        <p>Customize the emails sent to users after they complete a quiz and log in.</p>
        
        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_email_subject">Email Subject</label></th>
                <td>
                    <input type="text" id="flosc_email_subject" name="flosc_email_subject" 
                           value="<?php echo esc_attr(get_option('flosc_email_subject', $default_subject)); ?>" 
                           class="large-text">
                    <p class="description">Available placeholders: <code>{score}</code>, <code>{product_name}</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_email_body">Email Body</label></th>
                <td>
                    <textarea id="flosc_email_body" name="flosc_email_body" rows="15" class="large-text code"><?php 
                        echo esc_textarea(get_option('flosc_email_body', $default_body)); 
                    ?></textarea>
                    <p class="description">
                        Available placeholders: <code>{name}</code>, <code>{score}</code>, <code>{correct}</code>, 
                        <code>{incorrect}</code>, <code>{oto_section}</code>, <code>{app_link}</code>, <code>{product_name}</code>
                    </p>
                </td>
            </tr>
        </table>

        <h3>Email Template Examples (Copy-Paste Reference)</h3>
        <p class="description">Best practice email templates for different scenarios. Copy and customize as needed.</p>
        
        <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #4f46e5;">
            <h4 style="margin-top: 0;">📊 Quiz Results Email (High Score)</h4>
            <p><strong>Subject:</strong> <code>Great job! You scored {score}% on the {product_name} quiz 🎉</code></p>
            <p><strong>Body:</strong></p>
            <pre style="background: white; padding: 10px; overflow-x: auto;">Hi {name},

Congratulations! You scored {score}% on the {product_name} quick assessment.

🎯 Your Results:
• {correct} correct answers
• {incorrect} areas for improvement

Based on your performance, we've prepared a personalized lesson plan to help you master the topics where you can improve most.

👉 Get Your Free Personalized Lesson: {app_link}

Ready to take your skills to the next level?

{oto_section}

Keep up the great work!

Best regards,
The {product_name} Team</pre>
        </div>

        <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
            <h4 style="margin-top: 0;">📚 Quiz Results Email (Needs Improvement)</h4>
            <p><strong>Subject:</strong> <code>Your {product_name} assessment results + FREE lesson inside</code></p>
            <p><strong>Body:</strong></p>
            <pre style="background: white; padding: 10px; overflow-x: auto;">Hi {name},

Thanks for taking the {product_name} quick assessment! You scored {score}%.

Don't worry - everyone starts somewhere, and we're here to help you improve.

📊 Your Results:
• {correct} correct answers
• {incorrect} questions to focus on

The good news? We've analyzed your results and created a FREE personalized lesson that targets exactly the areas where you can improve fastest.

👉 Start Your Free Lesson Now: {app_link}

This lesson includes:
✓ Step-by-step guidance for the concepts you found challenging
✓ Practice exercises with instant feedback
✓ Real-world examples you can apply immediately

{oto_section}

You're just one lesson away from a breakthrough!

Best regards,
The {product_name} Team

P.S. Most of our successful students started right where you are. The difference? They took the first step.</pre>
        </div>

        <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #10b981;">
            <h4 style="margin-top: 0;">🎓 Welcome Email (New User)</h4>
            <p><strong>Subject:</strong> <code>Welcome to {product_name} - Let's get started! 🚀</code></p>
            <p><strong>Body:</strong></p>
            <pre style="background: white; padding: 10px; overflow-x: auto;">Hi {name},

Welcome to {product_name}! We're excited to have you here.

Here's what happens next:

1️⃣ Take the Quick Assessment
   Find out your current skill level (takes just 2 minutes)
   
2️⃣ Get Your Personalized Learning Path
   We'll create a custom lesson plan based on your results
   
3️⃣ Start Learning Immediately
   Access your first FREE lesson right away

👉 Start Your Assessment Now: {app_link}

Questions? Just reply to this email - we're here to help!

Best regards,
The {product_name} Team</pre>
        </div>

        <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #ec4899;">
            <h4 style="margin-top: 0;">⏰ Re-engagement Email (Inactive User)</h4>
            <p><strong>Subject:</strong> <code>We miss you! Your personalized lesson is waiting</code></p>
            <p><strong>Body:</strong></p>
            <pre style="background: white; padding: 10px; overflow-x: auto;">Hi {name},

We noticed you haven't visited {product_name} in a while, and your personalized lesson is still waiting for you.

Remember, you scored {score}% on your assessment, and we created a custom learning path just for you.

The hardest part of learning? Showing up consistently.

But here's the thing: Every expert was once a beginner who refused to give up.

👉 Pick Up Where You Left Off: {app_link}

Your progress is saved, and it only takes 10 minutes to complete your next lesson.

What are you waiting for?

Best regards,
The {product_name} Team

P.S. This personalized lesson expires in 7 days. Don't let your progress go to waste!</pre>
        </div>

        <div style="background: #f9f9f9; padding: 15px; margin: 20px 0; border-left: 4px solid #8b5cf6;">
            <h4 style="margin-top: 0;">💰 Upgrade Offer Email (Free User)</h4>
            <p><strong>Subject:</strong> <code>Ready for the full {product_name} experience?</code></p>
            <p><strong>Body:</strong></p>
            <pre style="background: white; padding: 10px; overflow-x: auto;">Hi {name},

You've completed your free lesson, and your results show real progress!

You're ready for the next level.

{product_name} Premium includes:
✓ Complete access to all lessons and modules
✓ Advanced practice exercises with AI feedback
✓ Personalized coaching and progress tracking
✓ Certificate of completion
✓ Lifetime updates and new content

{oto_section}

Most students who upgrade see 3x faster improvement.

Ready to accelerate your learning?

👉 Upgrade to Premium Now: {app_link}

Best regards,
The {product_name} Team

P.S. Have questions? Reply to this email and we'll help you decide if Premium is right for you.</pre>
        </div>

        <h3>Planned Email Automations (UI scaffold)</h3>
        <p class="description">Planned functionality only — backend is pseudocode/stub. Mirrors IVR conditions for future weekly summaries and quiz-result sends.</p>
        <table class="form-table">
            <tr>
                <th scope="row">Provider (planned)</th>
                <td>
                    <select disabled>
                        <option>WordPress Mail (default)</option>
                        <option>BuddyBoss Mailer</option>
                        <option>Mailjet</option>
                    </select>
                    <p class="description">Adapter planned; no sending logic wired yet.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Weekly Summary (planned)</th>
                <td>
                    <label><input type="checkbox" disabled> Send weekly progress summary (planned)</label>
                    <p class="description">Would use IVR-like conditions: inactivity, lessons_completed, quiz_taken, purchased.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Quiz Result Email (planned)</th>
                <td>
                    <label><input type="checkbox" disabled checked> Send after quiz completion</label>
                    <p class="description">Uses placeholders above; routing and send are stubbed for now.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Trigger Conditions (planned)</th>
                <td>
                    <p class="description">Future routing mirrors IVR conditions: quiz_taken, score thresholds, has_profile/email, purchased, first_message_after_quiz, inactivity.</p>
                </td>
            </tr>
        </table>
        
        <?php elseif ($active_tab === 'ai-knowledge'): 
            // AI Knowledge Files Manager
            $orientation_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
            $files = [];
            if (is_dir($orientation_dir)) {
                $scan = scandir($orientation_dir);
                foreach ($scan as $file) {
                    if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                        $files[] = $file;
                    }
                }
            }
            $editing_file = isset($_GET['edit']) ? sanitize_file_name($_GET['edit']) : '';
            $editing_content = '';
            if ($editing_file) {
                $filepath = $orientation_dir . $editing_file;
                if (file_exists($filepath)) {
                    $editing_content = file_get_contents($filepath);
                }
            }
        ?>
        </form>
        <h2>AI Knowledge Files</h2>
        <p class="description">Upload and manage markdown files that provide knowledge, catalogs, and instructions for your AI assistant.</p>

        <!-- Upload File -->
        <div class="card" style="max-width: 800px; margin-bottom: 20px;">
            <h3>Upload Markdown File</h3>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('flosc_ai_knowledge', 'flosc_ai_knowledge_nonce'); ?>
                <input type="file" name="orientation_file" accept=".md" required>
                <p class="description">Upload a .md file containing lesson catalogs, knowledge base content, or AI instructions.</p>
                <?php submit_button('Upload File', 'secondary'); ?>
            </form>
        </div>

        <!-- Existing Files -->
        <div class="card" style="max-width: 800px;">
            <h3>Existing Knowledge Files</h3>
            <?php if (empty($files)): ?>
                <p>No knowledge files yet. Upload your first file above.</p>
            <?php else: ?>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <?php
                            $filepath = $orientation_dir . $file;
                            $size = filesize($filepath);
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($file); ?></strong></td>
                                <td><?php echo size_format($size); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=flosc-settings&tab=ai-knowledge&edit=' . urlencode($file)); ?>" class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <form method="post" action="options.php">
        <?php settings_fields('flosc_settings'); ?>

        <?php elseif ($active_tab === 'offers'): 
            $offers = flosc()->sale()->offers()->get_all_offers();
        ?>
        </form>
        <h2>Offers Configuration</h2>
        <p class="description">Create and manage your product offers with pricing and descriptions.</p>

        <div class="card" style="max-width: 800px;">
            <h3>Current Offers</h3>
            <?php if (empty($offers)): ?>
                <p>No offers configured yet.</p>
            <?php else: ?>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($offers as $offer): ?>
                            <tr>
                                <td><strong><?php echo esc_html($offer['name']); ?></strong></td>
                                <td><?php echo esc_html($offer['display_price']); ?></td>
                                <td><?php echo esc_html($offer['type'] ?? 'one-time'); ?></td>
                                <td><?php echo ($offer['active'] ?? true) ? '✓ Active' : 'Inactive'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <p style="margin-top: 15px;">
                <em>Offer management coming soon. Configure offers in database for now.</em>
            </p>
        </div>
        <form method="post" action="options.php">
        <?php settings_fields('flosc_settings'); ?>

        <?php elseif ($active_tab === 'payments'): 
            $stripe_enabled = get_option('flosc_stripe_enabled', false);
            $stripe_mode = get_option('flosc_stripe_mode', 'test');
        ?>
        <h2>Payment Providers</h2>
        <p class="description">Configure your payment providers to accept payments for offers.</p>

        <table class="form-table">
            <tr>
                <th scope="row">Stripe</th>
                <td>
                    <label>
                        <input type="checkbox" name="flosc_stripe_enabled" value="1" <?php checked($stripe_enabled); ?>>
                        Enable Stripe Payments
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_stripe_mode">Stripe Mode</label></th>
                <td>
                    <select name="flosc_stripe_mode" id="flosc_stripe_mode">
                        <option value="test" <?php selected($stripe_mode, 'test'); ?>>Test Mode</option>
                        <option value="live" <?php selected($stripe_mode, 'live'); ?>>Live Mode</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_stripe_test_pk">Test Publishable Key</label></th>
                <td>
                    <input type="text" id="flosc_stripe_test_pk" name="flosc_stripe_test_pk" value="<?php echo esc_attr(get_option('flosc_stripe_test_pk', '')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_stripe_test_sk">Test Secret Key</label></th>
                <td>
                    <input type="password" id="flosc_stripe_test_sk" name="flosc_stripe_test_sk" value="<?php echo esc_attr(get_option('flosc_stripe_test_sk', '')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_stripe_live_pk">Live Publishable Key</label></th>
                <td>
                    <input type="text" id="flosc_stripe_live_pk" name="flosc_stripe_live_pk" value="<?php echo esc_attr(get_option('flosc_stripe_live_pk', '')); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_stripe_live_sk">Live Secret Key</label></th>
                <td>
                    <input type="password" id="flosc_stripe_live_sk" name="flosc_stripe_live_sk" value="<?php echo esc_attr(get_option('flosc_stripe_live_sk', '')); ?>" class="regular-text">
                </td>
            </tr>
        </table>

        <?php endif; ?>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.flosc-admin { max-width: 900px; }
.flosc-admin-header { background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 4px; }
.flosc-admin .nav-tab-wrapper { margin-bottom: 20px; }
.flosc-info-box { background: #f9f9f9; border-left: 4px solid #2271b1; padding: 15px 20px; margin: 20px 0; }
.flosc-info-box ol { margin: 10px 0 0 20px; }
.flosc-info-box ul { margin: 5px 0 5px 20px; list-style-type: disc; }
</style>
