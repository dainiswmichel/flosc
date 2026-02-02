<?php
/**
 * FLOSC Offers Admin Page
 */

if (!defined('ABSPATH')) exit;

$offer_manager = flosc()->sale()->offers();
$offers = $offer_manager->get_all_offers();
$offer_types = $offer_manager->get_offer_types();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['flosc_offer_nonce'])) {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!wp_verify_nonce($_POST['flosc_offer_nonce'], 'flosc_save_offer')) {
        wp_die('Security check failed');
    }

    $action = $_POST['offer_action'] ?? '';
    
    if ($action === 'save') {
        $offer_data = [
            'id' => sanitize_text_field($_POST['offer_id'] ?? ''),
            'name' => sanitize_text_field($_POST['offer_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['offer_description'] ?? ''),
            'type' => sanitize_text_field($_POST['offer_type'] ?? 'one_time'),
            'status' => sanitize_text_field($_POST['offer_status'] ?? 'draft'),
            'display_price' => sanitize_text_field($_POST['display_price'] ?? ''),
            'pricing' => [
                'stripe' => [
                    'price_id' => sanitize_text_field($_POST['stripe_price_id'] ?? ''),
                ],
                'tokens' => [
                    'cost' => intval($_POST['token_cost'] ?? 0),
                ],
                'affiliate' => [
                    'credit_amount' => floatval($_POST['affiliate_credit'] ?? 0),
                ],
            ],
            'grants' => [
                'features' => array_map('sanitize_text_field', explode(',', $_POST['grant_features'] ?? '')),
                'access_level' => sanitize_text_field($_POST['access_level'] ?? 'free'),
                'duration_days' => intval($_POST['duration_days'] ?? 0),
            ],
            'tokens' => [
                'amount' => intval($_POST['token_amount'] ?? 0),
                'bonus' => intval($_POST['token_bonus'] ?? 0),
            ],
            'meta' => [
                'icon' => sanitize_text_field($_POST['offer_icon'] ?? ''),
                'badge' => sanitize_text_field($_POST['offer_badge'] ?? ''),
            ],
            'sort_order' => intval($_POST['sort_order'] ?? 0),
        ];
        
        if (empty($offer_data['id'])) {
            $offer_manager->create_offer($offer_data);
            $message = 'Offer created successfully.';
        } else {
            $offer_manager->update_offer($offer_data['id'], $offer_data);
            $message = 'Offer updated successfully.';
        }
        
        // Refresh offers list
        $offers = $offer_manager->get_all_offers();
    }
    
    if ($action === 'delete' && !empty($_POST['delete_offer_id'])) {
        $offer_manager->delete_offer(sanitize_text_field($_POST['delete_offer_id']));
        $message = 'Offer deleted.';
        $offers = $offer_manager->get_all_offers();
    }
}

$editing = isset($_GET['edit']) ? $offer_manager->get_offer(sanitize_text_field($_GET['edit'])) : null;
?>
<div class="wrap flosc-admin">
    <h1>FLOSC Offers</h1>
    
    <?php if (!empty($message)): ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>
    
    <div class="flosc-offers-container">
        <!-- Offers List -->
        <div class="flosc-offers-list">
            <h2>All Offers</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Display Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $offer): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($offer['meta']['icon'] ?? ''); ?> <?php echo esc_html($offer['name']); ?></strong>
                            <br><small><?php echo esc_html($offer['description']); ?></small>
                        </td>
                        <td><?php echo esc_html($offer_types[$offer['type']]['label'] ?? $offer['type']); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($offer['status']); ?>">
                                <?php echo esc_html(ucfirst($offer['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($offer['display_price'] ?: '—'); ?></td>
                        <td>
                            <a href="?page=flosc-offers&edit=<?php echo esc_attr($offer['id']); ?>" class="button button-small">Edit</a>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('flosc_save_offer', 'flosc_offer_nonce'); ?>
                                <input type="hidden" name="offer_action" value="delete">
                                <input type="hidden" name="delete_offer_id" value="<?php echo esc_attr($offer['id']); ?>">
                                <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Delete this offer?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p><a href="?page=flosc-offers&edit=new" class="button button-primary">Add New Offer</a></p>
        </div>
        
        <!-- Edit Form -->
        <?php if ($editing || isset($_GET['edit'])): ?>
        <div class="flosc-offer-edit">
            <h2><?php echo $editing ? 'Edit Offer' : 'New Offer'; ?></h2>
            
            <form method="post">
                <?php wp_nonce_field('flosc_save_offer', 'flosc_offer_nonce'); ?>
                <input type="hidden" name="offer_action" value="save">
                <input type="hidden" name="offer_id" value="<?php echo esc_attr($editing['id'] ?? ''); ?>">
                
                <table class="form-table">
                    <tr>
                        <th><label for="offer_name">Name</label></th>
                        <td><input type="text" id="offer_name" name="offer_name" value="<?php echo esc_attr($editing['name'] ?? ''); ?>" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="offer_description">Description</label></th>
                        <td><textarea id="offer_description" name="offer_description" class="large-text" rows="2"><?php echo esc_textarea($editing['description'] ?? ''); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="offer_type">Type</label></th>
                        <td>
                            <select id="offer_type" name="offer_type">
                                <?php foreach ($offer_types as $type_id => $type): ?>
                                <option value="<?php echo esc_attr($type_id); ?>" <?php selected($editing['type'] ?? '', $type_id); ?>>
                                    <?php echo esc_html($type['label']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html($offer_types[$editing['type'] ?? 'one_time']['description'] ?? ''); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="offer_status">Status</label></th>
                        <td>
                            <select id="offer_status" name="offer_status">
                                <option value="active" <?php selected($editing['status'] ?? '', 'active'); ?>>Active</option>
                                <option value="draft" <?php selected($editing['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="display_price">Display Price</label></th>
                        <td>
                            <input type="text" id="display_price" name="display_price" value="<?php echo esc_attr($editing['display_price'] ?? ''); ?>" class="regular-text" placeholder="e.g., €144 or 500 tokens or Free">
                            <p class="description">Shown to users (actual price comes from Stripe)</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Payment Provider Pricing</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="stripe_price_id">Stripe Price ID</label></th>
                        <td>
                            <input type="text" id="stripe_price_id" name="stripe_price_id" value="<?php echo esc_attr($editing['pricing']['stripe']['price_id'] ?? ''); ?>" class="regular-text" placeholder="price_...">
                            <p class="description">From Stripe Dashboard → Products → Price ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="token_cost">Token Cost</label></th>
                        <td>
                            <input type="number" id="token_cost" name="token_cost" value="<?php echo esc_attr($editing['pricing']['tokens']['cost'] ?? 0); ?>" class="small-text" min="0">
                            <p class="description">How many tokens to purchase this offer (0 = free with tokens)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="affiliate_credit">Affiliate Credit Required</label></th>
                        <td>
                            <input type="number" id="affiliate_credit" name="affiliate_credit" value="<?php echo esc_attr($editing['pricing']['affiliate']['credit_amount'] ?? 0); ?>" class="small-text" min="0" step="0.01">
                            <p class="description">$ in affiliate credits needed (0 = free via affiliate)</p>
                        </td>
                    </tr>
                </table>
                
                <h3>Access Grants</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="access_level">Access Level</label></th>
                        <td>
                            <select id="access_level" name="access_level">
                                <option value="free" <?php selected($editing['grants']['access_level'] ?? '', 'free'); ?>>Free</option>
                                <option value="basic" <?php selected($editing['grants']['access_level'] ?? '', 'basic'); ?>>Basic</option>
                                <option value="pro" <?php selected($editing['grants']['access_level'] ?? '', 'pro'); ?>>Pro</option>
                                <option value="premium" <?php selected($editing['grants']['access_level'] ?? '', 'premium'); ?>>Premium</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="grant_features">Features</label></th>
                        <td>
                            <input type="text" id="grant_features" name="grant_features" value="<?php echo esc_attr(implode(',', $editing['grants']['features'] ?? [])); ?>" class="large-text" placeholder="quiz,all_lessons,ai_coach,certificates">
                            <p class="description">Comma-separated feature flags to enable</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="duration_days">Duration (Days)</label></th>
                        <td>
                            <input type="number" id="duration_days" name="duration_days" value="<?php echo esc_attr($editing['grants']['duration_days'] ?? 0); ?>" class="small-text" min="0">
                            <p class="description">0 = lifetime access</p>
                        </td>
                    </tr>
                </table>
                
                <div class="token-pack-fields" style="<?php echo ($editing['type'] ?? '') !== 'tokens' ? 'display:none;' : ''; ?>">
                    <h3>Token Pack Settings</h3>
                    <table class="form-table">
                        <tr>
                            <th><label for="token_amount">Tokens Granted</label></th>
                            <td><input type="number" id="token_amount" name="token_amount" value="<?php echo esc_attr($editing['tokens']['amount'] ?? 0); ?>" class="small-text" min="0"></td>
                        </tr>
                        <tr>
                            <th><label for="token_bonus">Bonus Tokens</label></th>
                            <td><input type="number" id="token_bonus" name="token_bonus" value="<?php echo esc_attr($editing['tokens']['bonus'] ?? 0); ?>" class="small-text" min="0"></td>
                        </tr>
                    </table>
                </div>
                
                <h3>Display</h3>
                <table class="form-table">
                    <tr>
                        <th><label for="offer_icon">Icon/Emoji</label></th>
                        <td><input type="text" id="offer_icon" name="offer_icon" value="<?php echo esc_attr($editing['meta']['icon'] ?? ''); ?>" class="small-text" placeholder="⭐"></td>
                    </tr>
                    <tr>
                        <th><label for="offer_badge">Badge</label></th>
                        <td><input type="text" id="offer_badge" name="offer_badge" value="<?php echo esc_attr($editing['meta']['badge'] ?? ''); ?>" class="regular-text" placeholder="e.g., Most Popular"></td>
                    </tr>
                    <tr>
                        <th><label for="sort_order">Sort Order</label></th>
                        <td><input type="number" id="sort_order" name="sort_order" value="<?php echo esc_attr($editing['sort_order'] ?? 0); ?>" class="small-text"></td>
                    </tr>
                </table>
                
                <?php submit_button($editing ? 'Update Offer' : 'Create Offer'); ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.flosc-admin { max-width: 1200px; }
.flosc-offers-container { display: flex; gap: 30px; }
.flosc-offers-list { flex: 1; }
.flosc-offer-edit { flex: 1; background: #fff; padding: 20px; border: 1px solid #ccc; }
.status-active { color: #46b450; font-weight: bold; }
.status-draft { color: #999; }
</style>

<script>
document.getElementById('offer_type')?.addEventListener('change', function() {
    document.querySelector('.token-pack-fields').style.display = this.value === 'tokens' ? '' : 'none';
});
</script>
