<?php
/**
 * FLOSC Payment Providers Admin Page
 */

if (!defined('ABSPATH')) exit;

$sale = flosc()->sale();
$providers = $sale->get_providers();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flosc_payment_nonce'])) {
    if (!wp_verify_nonce($_POST['flosc_payment_nonce'], 'flosc_save_payment_settings')) {
        wp_die('Security check failed');
    }
    
    $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');
    $provider = $sale->get_provider($provider_id);
    
    if ($provider) {
        $fields = $provider->get_settings_fields();
        
        foreach ($fields as $key => $field) {
            if ($field['type'] === 'heading') continue;
            
            $value = $_POST['flosc_' . $provider_id . '_' . $key] ?? '';
            
            // Sanitize based on field type
            switch ($field['type']) {
                case 'number':
                    $value = floatval($value);
                    break;
                case 'url':
                    $value = esc_url_raw($value);
                    break;
                default:
                    $value = sanitize_text_field($value);
            }
            
            update_option('flosc_' . $provider_id . '_' . $key, $value);
        }
        
        // Handle enabled toggle
        $enabled = isset($_POST['flosc_' . $provider_id . '_enabled']);
        $provider->set_enabled($enabled);
        
        $message = ucfirst($provider_id) . ' settings saved.';
    }
}

$active_provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : 'stripe';
?>
<div class="wrap flosc-admin">
    <h1>Payment Providers</h1>
    
    <?php if (!empty($message)): ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    
    <div class="flosc-payments-container">
        <!-- Provider List -->
        <div class="flosc-providers-list">
            <h2>Providers</h2>
            
            <?php foreach ($providers as $id => $provider): ?>
            <a href="?page=flosc-payments&provider=<?php echo esc_attr($id); ?>" 
               class="flosc-provider-card <?php echo $active_provider === $id ? 'active' : ''; ?> <?php echo $provider->is_configured() ? 'configured' : ''; ?>">
                <span class="provider-icon"><?php echo $provider->get_icon(); ?></span>
                <div class="provider-info">
                    <strong><?php echo esc_html($provider->get_name()); ?></strong>
                    <span class="provider-status">
                        <?php if ($provider->is_configured() && $provider->is_enabled()): ?>
                            <span class="status-active">✓ Active</span>
                        <?php elseif ($provider->is_configured()): ?>
                            <span class="status-disabled">Disabled</span>
                        <?php else: ?>
                            <span class="status-unconfigured">Not configured</span>
                        <?php endif; ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Provider Settings -->
        <div class="flosc-provider-settings">
            <?php 
            $provider = $sale->get_provider($active_provider);
            if ($provider):
                $fields = $provider->get_settings_fields();
            ?>
            <h2><?php echo $provider->get_icon(); ?> <?php echo esc_html($provider->get_name()); ?></h2>
            <p class="description"><?php echo esc_html($provider->get_description()); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('flosc_save_payment_settings', 'flosc_payment_nonce'); ?>
                <input type="hidden" name="provider_id" value="<?php echo esc_attr($active_provider); ?>">
                
                <table class="form-table">
                    <tr>
                        <th>Enable Provider</th>
                        <td>
                            <label>
                                <input type="checkbox" name="flosc_<?php echo esc_attr($active_provider); ?>_enabled" value="1" <?php checked($provider->is_enabled()); ?>>
                                Enable <?php echo esc_html($provider->get_name()); ?> as a payment option
                            </label>
                        </td>
                    </tr>
                    
                    <?php foreach ($fields as $key => $field): ?>
                        <?php if ($field['type'] === 'heading'): ?>
                        <tr>
                            <th colspan="2">
                                <h3><?php echo esc_html($field['label']); ?></h3>
                                <?php if (!empty($field['description'])): ?>
                                <p class="description"><?php echo esc_html($field['description']); ?></p>
                                <?php endif; ?>
                            </th>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <th><label for="flosc_<?php echo esc_attr($active_provider . '_' . $key); ?>"><?php echo esc_html($field['label']); ?></label></th>
                            <td>
                                <?php 
                                $value = get_option('flosc_' . $active_provider . '_' . $key, $field['default'] ?? '');
                                $input_id = 'flosc_' . $active_provider . '_' . $key;
                                $input_name = $input_id;
                                
                                switch ($field['type']):
                                    case 'select': ?>
                                        <select id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_name); ?>">
                                            <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                            <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                                                <?php echo esc_html($opt_label); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php break;
                                    
                                    case 'password': ?>
                                        <input type="password" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_name); ?>" 
                                               value="<?php echo esc_attr($value); ?>" class="regular-text"
                                               placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
                                        <?php break;
                                    
                                    case 'number': ?>
                                        <input type="number" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_name); ?>" 
                                               value="<?php echo esc_attr($value); ?>" class="small-text"
                                               min="<?php echo esc_attr($field['min'] ?? ''); ?>"
                                               step="<?php echo esc_attr($field['step'] ?? '1'); ?>">
                                        <?php break;
                                    
                                    case 'url': ?>
                                        <input type="url" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_name); ?>" 
                                               value="<?php echo esc_attr($value); ?>" class="regular-text"
                                               placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
                                        <?php break;
                                    
                                    default: ?>
                                        <input type="text" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_name); ?>" 
                                               value="<?php echo esc_attr($value); ?>" class="regular-text"
                                               placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>">
                                <?php endswitch; ?>
                                
                                <?php if (!empty($field['description'])): ?>
                                <p class="description"><?php echo wp_kses_post($field['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <?php if ($active_provider === 'stripe'): ?>
            <div class="flosc-provider-info">
                <h3>Webhook Setup</h3>
                <p>Add this webhook URL in your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Dashboard</a>:</p>
                <code><?php echo esc_html(rest_url('flosc/v1/webhooks/stripe')); ?></code>
                <p class="description">Events to listen for: <code>payment_intent.succeeded</code>, <code>customer.subscription.created</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code></p>
            </div>
            <?php endif; ?>
            
            <?php if ($active_provider === 'affiliate'): ?>
            <div class="flosc-provider-info">
                <h3>How Purchase Intent Works</h3>
                <ol>
                    <li>User tells the system what they're planning to buy</li>
                    <li>System finds matching affiliate offers from configured networks</li>
                    <li>User clicks through and makes their planned purchase</li>
                    <li>Commission flows back and credits user's account</li>
                    <li>User can "spend" credits to unlock FLOSC offers</li>
                </ol>
                <p><strong>Webhook URL for postbacks:</strong></p>
                <code><?php echo esc_html(rest_url('flosc/v1/webhooks/affiliate')); ?></code>
            </div>
            <?php endif; ?>
            
            <?php if ($active_provider === 'tokens'): ?>
            <div class="flosc-provider-info">
                <h3>Token System</h3>
                <p>Tokens are internal credits that users can:</p>
                <ul>
                    <li>Earn through signup bonuses</li>
                    <li>Earn through referrals</li>
                    <li>Earn through affiliate purchases</li>
                    <li>Purchase with real money (via Stripe token pack offers)</li>
                    <li>Spend on pay-per-use features or unlock offers</li>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.flosc-admin { max-width: 1200px; }
.flosc-payments-container { display: flex; gap: 30px; }
.flosc-providers-list { width: 280px; flex-shrink: 0; }
.flosc-provider-settings { flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; }

.flosc-provider-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    margin-bottom: 10px;
    background: #fff;
    border: 2px solid #ddd;
    border-radius: 8px;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
}

.flosc-provider-card:hover {
    border-color: #2271b1;
}

.flosc-provider-card.active {
    border-color: #2271b1;
    background: #f0f6fc;
}

.flosc-provider-card.configured {
    border-left: 4px solid #46b450;
}

.provider-icon {
    font-size: 28px;
}

.provider-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.provider-status {
    font-size: 12px;
}

.status-active { color: #46b450; }
.status-disabled { color: #d63638; }
.status-unconfigured { color: #999; }

.flosc-provider-info {
    margin-top: 30px;
    padding: 20px;
    background: #f0f6fc;
    border-radius: 8px;
}

.flosc-provider-info h3 {
    margin-top: 0;
}

.flosc-provider-info code {
    display: block;
    padding: 10px;
    background: #fff;
    border: 1px solid #ddd;
    margin: 10px 0;
    word-break: break-all;
}

.flosc-provider-info ol, .flosc-provider-info ul {
    margin-left: 20px;
}
</style>
