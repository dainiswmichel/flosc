<?php
/**
 * Flow Edit Tab: STT
 * 
 * Speech-to-Text provider configuration
 */

if (!defined('ABSPATH')) exit;

$stt = $flow['stt'] ?? [];

$provider_options = [
    '' => '— Use Global —',
    'assemblyai' => 'AssemblyAI',
    'openai' => 'OpenAI Whisper',
    'deepgram' => 'Deepgram',
    'custom' => 'Custom Endpoint',
];
?>

<form method="post">
    <?php wp_nonce_field('flosc_save_flow'); ?>
    
    <h2 class="flosc-section-header">Speech-to-Text Configuration</h2>
    <p class="description">
        Configure STT for audio-based quizzes (pronunciation, simple scoring with audio).
        Leave empty to use global settings.
    </p>
    
    <div class="flosc-form-field">
        <label for="stt_provider">STT Provider</label>
        <select id="stt_provider" name="stt_provider" style="max-width: 300px;">
            <?php foreach ($provider_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($stt['provider'] ?? '', $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($stt['provider'])): ?>
            <span class="description" style="margin-left: 10px;">
                Global: <?php echo esc_html(flosc_get_global_setting('stt.provider', 'assemblyai')); ?>
            </span>
        <?php endif; ?>
    </div>
    
    <h2 class="flosc-section-header">API Keys (Flow-Specific)</h2>
    <p class="description">
        Leave empty to use global API keys. Set flow-specific keys if this flow needs its own STT account.
    </p>
    
    <div class="flosc-form-field">
        <label for="stt_assemblyai_key">AssemblyAI API Key</label>
        <input type="password" id="stt_assemblyai_key" name="stt_assemblyai_key" 
               value="<?php echo esc_attr($stt['assemblyai_key'] ?? ''); ?>"
               placeholder="<?php echo empty($stt['assemblyai_key']) ? 'Using global key' : ''; ?>">
        <p class="description">Get your key at <a href="https://www.assemblyai.com/" target="_blank">assemblyai.com</a></p>
    </div>
    
    <div class="flosc-form-field">
        <label for="stt_deepgram_key">Deepgram API Key</label>
        <input type="password" id="stt_deepgram_key" name="stt_deepgram_key" 
               value="<?php echo esc_attr($stt['deepgram_key'] ?? ''); ?>"
               placeholder="<?php echo empty($stt['deepgram_key']) ? 'Using global key' : ''; ?>">
        <p class="description">Get your key at <a href="https://www.deepgram.com/" target="_blank">deepgram.com</a></p>
    </div>
    
    <div class="flosc-form-field" id="custom_endpoint_field" style="<?php echo ($stt['provider'] ?? '') === 'custom' ? '' : 'display: none;'; ?>">
        <label for="stt_custom_endpoint">Custom STT Endpoint</label>
        <input type="url" id="stt_custom_endpoint" name="stt_custom_endpoint" 
               value="<?php echo esc_attr($stt['custom_endpoint'] ?? ''); ?>"
               placeholder="https://your-stt-api.com/transcribe">
        <p class="description">URL for your self-hosted STT endpoint.</p>
    </div>
    
    <p class="submit">
        <input type="submit" name="flosc_save_flow" class="button button-primary" value="Save Changes">
    </p>
    
    <hr style="margin: 40px 0;">
    
    <div style="background: #f0f6ff; border: 1px solid #c3dafe; padding: 15px; border-radius: 8px;">
        <strong>💡 When to Use Flow-Specific STT</strong>
        <ul style="margin: 10px 0 0 20px;">
            <li>Different billing accounts per flow</li>
            <li>Different usage limits or rate limits</li>
            <li>Testing new providers on specific flows</li>
            <li>Specialized STT needs (e.g., music terminology for Solfeggio)</li>
        </ul>
    </div>
</form>

<script>
jQuery(document).ready(function($) {
    $('#stt_provider').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#custom_endpoint_field').show();
        } else {
            $('#custom_endpoint_field').hide();
        }
    });
});
</script>
