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
        
        <?php elseif ($active_tab === 'quiz'): ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="flosc_quiz_mode">Quiz Mode</label></th>
                <td>
                    <select name="flosc_quiz_mode" id="flosc_quiz_mode">
                        <option value="counting" <?php selected(get_option('flosc_quiz_mode'), 'counting'); ?>>Counting (1-10)</option>
                        <option value="sentence" <?php selected(get_option('flosc_quiz_mode'), 'sentence'); ?>>Custom Sentence</option>
                        <option value="none" <?php selected(get_option('flosc_quiz_mode'), 'none'); ?>>No Quiz</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_quiz_expected">Expected Response</label></th>
                <td>
                    <textarea id="flosc_quiz_expected" name="flosc_quiz_expected" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_quiz_expected', '1 2 3 4 5 6 7 8 9 10')); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="flosc_quiz_instructions">Quiz Instructions</label></th>
                <td>
                    <textarea id="flosc_quiz_instructions" name="flosc_quiz_instructions" rows="3" class="large-text"><?php echo esc_textarea(get_option('flosc_quiz_instructions', 'Please count from 1 to 10 clearly.')); ?></textarea>
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
</style>
