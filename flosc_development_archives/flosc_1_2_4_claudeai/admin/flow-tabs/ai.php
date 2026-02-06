<?php
/**
 * Flow Edit Tab: AI
 * 
 * AI provider and prompt configuration
 */

if (!defined('ABSPATH')) exit;

$ai = $flow['ai'] ?? [];

$provider_options = [
    '' => '— Use Global —',
    'ivr' => 'IVR (Scripted - Free)',
    'openai' => 'OpenAI (GPT-4o-mini)',
    'anthropic' => 'Anthropic (Claude)',
    'xai' => 'xAI (Grok)',
];

$mode_options = [
    '' => '— Use Global —',
    'strict' => 'Strict IVR (AI only rephrases configured messages)',
    'enhanced' => 'Enhanced (AI can expand with context)',
];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">AI Provider</h2>
    
    <div class="flosc-form-field">
        <label for="ai_provider">Primary AI Provider</label>
        <select id="ai_provider" name="ai_provider" style="max-width: 300px;">
            <?php foreach ($provider_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($ai['provider'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($ai['provider'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('ai.provider', 'ivr')); ?>
            </span>
        <?php endif; ?>
        <p class="description">IVR uses scripted messages only. AI providers require API keys.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_response_mode">Response Mode</label>
        <select id="ai_response_mode" name="ai_response_mode" style="max-width: 400px;">
            <?php foreach ($mode_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($ai['response_mode'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <h2 class="flosc-section-header">API Keys (Flow-Specific)</h2>
    <p class="description">
        Leave empty to use global API keys. Set flow-specific keys if this flow needs its own API account
        (e.g., different billing, usage limits, or separate API projects).
    </p>
    
    <div class="flosc-form-field">
        <label for="ai_openai_key">OpenAI API Key</label>
        <input type="password" id="ai_openai_key" name="ai_openai_key" 
               value="<?php echo esc_attr($ai['openai_key'] ?? ''); ?>" 
               placeholder="<?php echo empty($ai['openai_key']) ? 'Using global key' : ''; ?>">
        <p class="description">Get your key at <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_anthropic_key">Anthropic API Key</label>
        <input type="password" id="ai_anthropic_key" name="ai_anthropic_key" 
               value="<?php echo esc_attr($ai['anthropic_key'] ?? ''); ?>" 
               placeholder="<?php echo empty($ai['anthropic_key']) ? 'Using global key' : ''; ?>">
        <p class="description">Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a></p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_xai_key">xAI API Key</label>
        <input type="password" id="ai_xai_key" name="ai_xai_key" 
               value="<?php echo esc_attr($ai['xai_key'] ?? ''); ?>" 
               placeholder="<?php echo empty($ai['xai_key']) ? 'Using global key' : ''; ?>">
        <p class="description">Get your key at <a href="https://console.x.ai" target="_blank">console.x.ai</a></p>
    </div>
    
    <h2 class="flosc-section-header">Base System Prompt</h2>
    
    <div class="flosc-form-field">
        <label for="ai_base_prompt">Base System Prompt</label>
        <textarea id="ai_base_prompt" name="ai_base_prompt" rows="6"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.base_prompt')); ?>"><?php echo esc_textarea($ai['base_prompt'] ?? ''); ?></textarea>
        <p class="description">The AI's base personality and instructions for this flow. Phase-specific prompts are added automatically.</p>
    </div>
    
    <h2 class="flosc-section-header">Phase-Specific Instructions</h2>
    <p class="description">Customize AI behavior for each FLOSC phase. These are appended to the base prompt.</p>
    
    <div class="flosc-form-field">
        <label for="ai_prompt_freeline">Freeline Phase</label>
        <textarea id="ai_prompt_freeline" name="ai_prompt_freeline" rows="3"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.prompt_freeline')); ?>"><?php echo esc_textarea($ai['prompt_freeline'] ?? ''); ?></textarea>
        <p class="description">Visitors who haven't taken the quiz yet.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_prompt_login">Login Phase</label>
        <textarea id="ai_prompt_login" name="ai_prompt_login" rows="3"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.prompt_login')); ?>"><?php echo esc_textarea($ai['prompt_login'] ?? ''); ?></textarea>
        <p class="description">Post-quiz visitors and logged-in users.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_prompt_offer">Offer Phase</label>
        <textarea id="ai_prompt_offer" name="ai_prompt_offer" rows="3"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.prompt_offer')); ?>"><?php echo esc_textarea($ai['prompt_offer'] ?? ''); ?></textarea>
        <p class="description">Sales pitch mode.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_prompt_sale">Sale Phase</label>
        <textarea id="ai_prompt_sale" name="ai_prompt_sale" rows="3"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.prompt_sale')); ?>"><?php echo esc_textarea($ai['prompt_sale'] ?? ''); ?></textarea>
        <p class="description">Post-purchase onboarding.</p>
    </div>
    
    <div class="flosc-form-field">
        <label for="ai_prompt_content">Content Phase</label>
        <textarea id="ai_prompt_content" name="ai_prompt_content" rows="3"
                  placeholder="<?php echo esc_attr(flosc_global_placeholder('ai.prompt_content')); ?>"><?php echo esc_textarea($ai['prompt_content'] ?? ''); ?></textarea>
        <p class="description">Ongoing support for paying customers.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
</form>
