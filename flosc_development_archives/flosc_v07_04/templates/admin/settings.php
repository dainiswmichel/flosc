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
            $ivr_config = FLOSC_IVR_Manager::get_instance()->get_config();
        ?>
        </form>
        <h2>IVR Messages Configuration</h2>
        <p><strong>IVR (Interactive Voice Response)</strong> is our framework for delivering contextual messages based on user behavior, FLOSC phase, quiz scores, and conditions.</p>

        <div class="flosc-info-box" style="margin-bottom: 20px;">
            <strong>Understanding FLOSC Phases:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li><strong>Freeline:</strong> Visitor (not logged in) - Goal: Get them to take the quiz</li>
                <li><strong>Login:</strong> Post-quiz visitors + Logged-in users - Goal: Deliver free lesson, present offer</li>
                <li><strong>Offer:</strong> Sales pitch - Goal: Get purchase</li>
                <li><strong>Sale:</strong> Post-purchase - Goal: Onboard to content</li>
                <li><strong>Content:</strong> Ongoing access - Goal: Support and encourage</li>
            </ul>
        </div>

        <!-- Simple Message Settings (saved via options.php) -->
        <form method="post" action="options.php">
            <?php settings_fields('flosc_settings'); ?>
            <h3>Quick Message Settings</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="flosc_welcome_message">Welcome Message</label></th>
                    <td>
                        <textarea id="flosc_welcome_message" name="flosc_welcome_message" rows="3" class="large-text"><?php
                            echo esc_textarea(get_option('flosc_welcome_message'));
                        ?></textarea>
                        <p class="description">Shown when user first opens the app (Freeline phase)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flosc_get_started_message">Get Started Response</label></th>
                    <td>
                        <textarea id="flosc_get_started_message" name="flosc_get_started_message" rows="3" class="large-text"><?php
                            echo esc_textarea(get_option('flosc_get_started_message'));
                        ?></textarea>
                        <p class="description">Response when user clicks "Get started" prompt card</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flosc_how_it_works_message">How It Works Response</label></th>
                    <td>
                        <textarea id="flosc_how_it_works_message" name="flosc_how_it_works_message" rows="4" class="large-text"><?php
                            echo esc_textarea(get_option('flosc_how_it_works_message'));
                        ?></textarea>
                        <p class="description">Response when user clicks "How does it work?" prompt card</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flosc_what_you_learn_message">What You Learn Response</label></th>
                    <td>
                        <textarea id="flosc_what_you_learn_message" name="flosc_what_you_learn_message" rows="4" class="large-text"><?php
                            echo esc_textarea(get_option('flosc_what_you_learn_message'));
                        ?></textarea>
                        <p class="description">Response when user clicks "What will I learn?" prompt card</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Quick Messages'); ?>
        </form>

        <hr style="margin: 30px 0;">

        <!-- Advanced IVR Configuration -->
        <h3>Advanced Phase Configuration</h3>
        <p class="description">Configure detailed messages for each FLOSC phase including prompt cards, pool messages, and triggered messages.</p>
        
        <form id="flosc-ivr-form">
            <?php foreach (['freeline', 'login', 'offer', 'sale', 'content'] as $phase): ?>
            <div class="ivr-phase-section" style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                <h4 style="margin-top: 0; text-transform: capitalize;"><?php echo esc_html($phase); ?> Phase</h4>
                
                <div style="margin-bottom: 15px;">
                    <label><strong>Initial Message:</strong></label>
                    <textarea name="<?php echo esc_attr($phase); ?>_initial" class="large-text" rows="3" style="margin-top: 5px;"><?php 
                        echo esc_textarea($ivr_config[$phase]['initial_message'] ?? ''); 
                    ?></textarea>
                </div>

                <?php if (!empty($ivr_config[$phase]['prompt_cards'])): ?>
                <div style="margin-bottom: 15px;">
                    <label><strong>Prompt Cards:</strong></label>
                    <?php foreach ($ivr_config[$phase]['prompt_cards'] as $card): ?>
                    <div style="background: #fff; padding: 10px; margin-top: 5px; border: 1px solid #eee;">
                        <strong><?php echo esc_html($card['action'] ?? 'unknown'); ?>:</strong>
                        <?php echo esc_html(substr($card['assistant_response'] ?? '', 0, 100)); ?>...
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            
            <button type="button" id="save-ivr-config" class="button button-primary">Save Advanced IVR Configuration</button>
            <span id="ivr-save-status" style="margin-left: 10px;"></span>
        </form>

        <script>
        jQuery(document).ready(function($) {
            $('#save-ivr-config').on('click', function() {
                var $btn = $(this);
                var $status = $('#ivr-save-status');
                
                $btn.prop('disabled', true);
                $status.text('Saving...');
                
                // Collect phase data
                var config = {};
                ['freeline', 'login', 'offer', 'sale', 'content'].forEach(function(phase) {
                    config[phase] = {
                        initial_message: $('textarea[name="' + phase + '_initial"]').val()
                    };
                });
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'flosc_save_ivr_config',
                        nonce: '<?php echo wp_create_nonce('flosc_ivr_nonce'); ?>',
                        config: JSON.stringify(config)
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color: green;">✓ Saved!</span>');
                        } else {
                            $status.html('<span style="color: red;">Error: ' + (response.data?.message || 'Unknown') + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color: red;">Request failed</span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>

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
        
        <?php elseif ($active_tab === 'ai-knowledge'): 
            // AI Knowledge Files Manager
            $configuration_dir = FLOSC_PLUGIN_DIR . 'ai_configuration_files/';
            $files = [];
            if (is_dir($configuration_dir)) {
                $scan = scandir($configuration_dir);
                foreach ($scan as $file) {
                    if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'md') {
                        $files[] = $file;
                    }
                }
            }
            $editing_file = isset($_GET['edit']) ? sanitize_file_name($_GET['edit']) : '';
            $editing_content = '';
            if ($editing_file) {
                $filepath = $configuration_dir . $editing_file;
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
                <input type="file" name="configuration_file" accept=".md" required>
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
                            $filepath = $configuration_dir . $file;
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

        <!-- v05_09: ClickBank Settings -->
        <h3 style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc;">ClickBank</h3>
        <?php
            $cb_enabled = get_option('flosc_clickbank_enabled', false);
            $cb_mode = get_option('flosc_clickbank_mode', 'sandbox');
            $cb_vendor = get_option('flosc_clickbank_vendor', 'SANDBOXVENDOR');
            $cb_secret = get_option('flosc_clickbank_secret', 'SANDBOX123456');
            $cb_product = get_option('flosc_clickbank_product', 'SANDBOXPRODUCT');
            $cb_access = get_option('flosc_clickbank_access_level', 'full');
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">ClickBank</th>
                <td>
                    <label>
                        <input type="checkbox" name="flosc_clickbank_enabled" value="1" <?php checked($cb_enabled); ?>>
                        Enable ClickBank Payments
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_clickbank_mode">ClickBank Mode</label></th>
                <td>
                    <select name="flosc_clickbank_mode" id="flosc_clickbank_mode">
                        <option value="sandbox" <?php selected($cb_mode, 'sandbox'); ?>>Sandbox (Testing)</option>
                        <option value="live" <?php selected($cb_mode, 'live'); ?>>Live</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_clickbank_vendor">Vendor Nickname</label></th>
                <td>
                    <input type="text" id="flosc_clickbank_vendor" name="flosc_clickbank_vendor" value="<?php echo esc_attr($cb_vendor); ?>" class="regular-text" placeholder="Your ClickBank vendor nickname">
                    <p class="description">Your ClickBank account nickname (found in Account Settings)</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_clickbank_secret">Secret Key</label></th>
                <td>
                    <input type="password" id="flosc_clickbank_secret" name="flosc_clickbank_secret" value="<?php echo esc_attr($cb_secret); ?>" class="regular-text" placeholder="Your ClickBank secret key">
                    <p class="description">Found in Account Settings → My Site → Advanced Tools → Secret Key</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_clickbank_product">Product SKU</label></th>
                <td>
                    <input type="text" id="flosc_clickbank_product" name="flosc_clickbank_product" value="<?php echo esc_attr($cb_product); ?>" class="regular-text" placeholder="e.g., LESAEPPRO">
                    <p class="description">The ClickBank product item number to match for access grants</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_clickbank_access_level">Access Level on Purchase</label></th>
                <td>
                    <select name="flosc_clickbank_access_level" id="flosc_clickbank_access_level">
                        <option value="basic" <?php selected($cb_access, 'basic'); ?>>Basic</option>
                        <option value="pro" <?php selected($cb_access, 'pro'); ?>>Pro</option>
                        <option value="premium" <?php selected($cb_access, 'premium'); ?>>Premium</option>
                        <option value="full" <?php selected($cb_access, 'full'); ?>>Full Access (Content Phase)</option>
                    </select>
                    <p class="description">Grants access to the Content phase at end of FLOSC cycle. Default: Full Access</p>
                </td>
            </tr>
            <tr>
                <th scope="row">IPN URL</th>
                <td>
                    <code style="background: #f0f0f0; padding: 5px 10px; display: inline-block;"><?php echo esc_url(rest_url('flosc/v1/webhooks/clickbank')); ?></code>
                    <p class="description">Enter this URL in your ClickBank account under My Site → Advanced Tools → Instant Notification URL</p>
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
