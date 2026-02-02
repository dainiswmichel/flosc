<?php
/**
 * FLOSC Offers Tab
 *
 * Admin UI for managing offers stored in the Offer Manager.
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

$notice = null;
$notice_type = 'success';

$offer_manager = null;
if (function_exists('flosc') && flosc() && method_exists(flosc(), 'sale') && flosc()->sale()) {
    $offer_manager = flosc()->sale()->offers();
}

if (!$offer_manager) {
    echo '<div class="notice notice-error"><p>Offer manager not available. Please check that the Sale module is enabled.</p></div>';
    return;
}

// --------------------------------
// Handle actions
// --------------------------------
if (!empty($_POST['flosc_offer_action'])) {
    check_admin_referer('flosc_offers_action');

    $action = sanitize_key(wp_unslash($_POST['flosc_offer_action']));
    $offer_id = isset($_POST['offer_id']) ? sanitize_text_field(wp_unslash($_POST['offer_id'])) : '';

    try {
        if ($action === 'delete') {
            if ($offer_id) {
                $offer_manager->delete_offer($offer_id);
                $notice = 'Offer deleted.';
            }
        } elseif ($action === 'toggle') {
            if ($offer_id) {
                $offer = $offer_manager->get_offer($offer_id);
                if ($offer) {
                    $new_status = ($offer['status'] ?? 'draft') === 'active' ? 'draft' : 'active';
                    $offer_manager->update_offer($offer_id, ['status' => $new_status]);
                    $notice = 'Offer status updated.';
                }
            }
        } elseif ($action === 'save') {
            $data = [
                'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
                'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
                'type' => sanitize_key(wp_unslash($_POST['type'] ?? 'one_time')),
                'status' => sanitize_key(wp_unslash($_POST['status'] ?? 'draft')),
                'pricing' => [
                    // Keep providers intact unless user explicitly sets them elsewhere
                    'token_cost' => isset($_POST['token_cost']) ? floatval($_POST['token_cost']) : null,
                    'currency' => sanitize_key(wp_unslash($_POST['currency'] ?? 'usd')),
                    'display_price' => sanitize_text_field(wp_unslash($_POST['display_price'] ?? '')),
                ],
            ];

            // Basic defaults
            if (empty($data['name'])) {
                throw new Exception('Offer name is required.');
            }

            if (empty($offer_id)) {
                $new = $offer_manager->create_offer($data);
                $notice = 'Offer created.';
                // Redirect to edit the new offer (avoid resubmission)
                wp_safe_redirect(add_query_arg(['tab' => 'offers', 'edit' => $new['id']], admin_url('admin.php?page=flosc-settings')));
                exit;
            } else {
                // Merge with existing offer to avoid wiping provider config
                $existing = $offer_manager->get_offer($offer_id);
                if ($existing && isset($existing['pricing']) && is_array($existing['pricing'])) {
                    $data['pricing'] = array_merge($existing['pricing'], array_filter($data['pricing'], function($v) { return $v !== null; }));
                }
                $offer_manager->update_offer($offer_id, $data);
                $notice = 'Offer saved.';
            }
        }
    } catch (Exception $e) {
        $notice = $e->getMessage();
        $notice_type = 'error';
    }
}

$edit_id = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
$edit_offer = $edit_id ? $offer_manager->get_offer($edit_id) : null;

$offers = $offer_manager->get_offers();
?>

<h2>Offers</h2>
<p>Offers are what you sell: one-time purchases, subscriptions, order bumps, and upsells. These are used by the chat UI and checkout flows.</p>

<?php if ($notice): ?>
    <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
<?php endif; ?>

<div class="flosc-info-box" style="margin-bottom: 20px;">
    <strong>Pro tips:</strong>
    <ul>
        <li>Keep <em>display price</em> human-friendly (e.g., “$29” or “29 tokens”).</li>
        <li>Use <em>token_cost</em> if your pricing is token-based. Use Stripe provider fields (in code) for card checkout.</li>
        <li>Status <strong>active</strong> = shown in the app. Status <strong>draft</strong> = hidden.</li>
    </ul>
</div>

<h3>Existing Offers</h3>
<?php if (empty($offers)): ?>
    <p>No offers yet. Create your first offer below.</p>
<?php else: ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Status</th>
                <th>Display Price</th>
                <th>Token Cost</th>
                <th style="width: 260px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($offers as $offer):
                $id = $offer['id'] ?? '';
                $pricing = $offer['pricing'] ?? [];
                $display_price = $pricing['display_price'] ?? '';
                $token_cost = $pricing['token_cost'] ?? '';
                $edit_url = add_query_arg(['tab' => 'offers', 'edit' => $id], admin_url('admin.php?page=flosc-settings'));
            ?>
                <tr>
                    <td><code><?php echo esc_html($id); ?></code></td>
                    <td><?php echo esc_html($offer['name'] ?? ''); ?></td>
                    <td><?php echo esc_html($offer['type'] ?? ''); ?></td>
                    <td><?php echo esc_html($offer['status'] ?? 'draft'); ?></td>
                    <td><?php echo esc_html($display_price); ?></td>
                    <td><?php echo esc_html($token_cost); ?></td>
                    <td>
                        <a class="button button-small" href="<?php echo esc_url($edit_url); ?>">Edit</a>
                        <form method="post" style="display:inline-block;margin:0 4px;">
                            <?php wp_nonce_field('flosc_offers_action'); ?>
                            <input type="hidden" name="offer_id" value="<?php echo esc_attr($id); ?>" />
                            <button type="submit" class="button button-small" name="flosc_offer_action" value="toggle">Toggle Active</button>
                        </form>
                        <form method="post" style="display:inline-block;margin:0;" onsubmit="return confirm('Delete this offer?');">
                            <?php wp_nonce_field('flosc_offers_action'); ?>
                            <input type="hidden" name="offer_id" value="<?php echo esc_attr($id); ?>" />
                            <button type="submit" class="button button-small button-link-delete" name="flosc_offer_action" value="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<hr />

<h3><?php echo $edit_offer ? 'Edit Offer' : 'Create Offer'; ?></h3>
<form method="post">
    <?php wp_nonce_field('flosc_offers_action'); ?>
    <input type="hidden" name="offer_id" value="<?php echo esc_attr($edit_offer['id'] ?? ''); ?>" />

    <table class="form-table">
        <tr>
            <th scope="row"><label for="flosc_offer_name">Name</label></th>
            <td><input id="flosc_offer_name" type="text" name="name" class="regular-text" value="<?php echo esc_attr($edit_offer['name'] ?? ''); ?>" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_description">Description</label></th>
            <td><textarea id="flosc_offer_description" name="description" rows="4" class="large-text"><?php echo esc_textarea($edit_offer['description'] ?? ''); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_type">Type</label></th>
            <td>
                <?php $type = $edit_offer['type'] ?? 'one_time'; ?>
                <select id="flosc_offer_type" name="type">
                    <option value="one_time" <?php selected($type, 'one_time'); ?>>one_time</option>
                    <option value="subscription" <?php selected($type, 'subscription'); ?>>subscription</option>
                    <option value="order_bump" <?php selected($type, 'order_bump'); ?>>order_bump</option>
                    <option value="upsell" <?php selected($type, 'upsell'); ?>>upsell</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_status">Status</label></th>
            <td>
                <?php $status = $edit_offer['status'] ?? 'draft'; ?>
                <select id="flosc_offer_status" name="status">
                    <option value="draft" <?php selected($status, 'draft'); ?>>draft</option>
                    <option value="active" <?php selected($status, 'active'); ?>>active</option>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_display_price">Display Price</label></th>
            <td>
                <?php $pricing = $edit_offer['pricing'] ?? []; ?>
                <input id="flosc_offer_display_price" type="text" name="display_price" class="regular-text" value="<?php echo esc_attr($pricing['display_price'] ?? ''); ?>" placeholder="$29 / 29 tokens">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_token_cost">Token Cost</label></th>
            <td>
                <input id="flosc_offer_token_cost" type="number" name="token_cost" step="0.01" value="<?php echo esc_attr($pricing['token_cost'] ?? ''); ?>" placeholder="29">
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="flosc_offer_currency">Currency</label></th>
            <td>
                <input id="flosc_offer_currency" type="text" name="currency" class="small-text" value="<?php echo esc_attr($pricing['currency'] ?? 'usd'); ?>">
            </td>
        </tr>
    </table>

    <p>
        <button type="submit" class="button button-primary" name="flosc_offer_action" value="save">Save Offer</button>
        <?php if ($edit_offer): ?>
            <a class="button" href="<?php echo esc_url(add_query_arg(['tab' => 'offers'], admin_url('admin.php?page=flosc-settings'))); ?>">Cancel</a>
        <?php endif; ?>
    </p>
</form>
