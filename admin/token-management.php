<?php
/**
 * FLOSC Token Management Tab
 *
 * Per-flow wallet economics + product token grants.
 * Products (offers) are editable accordion rows — set grant mode, amount, and cap
 * per product, or inherit flow defaults.
 */

if (!defined('ABSPATH')) {
    exit;
}

flosc_tab_header('🪙', 'Token Management');

$flosc_flow_settings = $GLOBALS['flosc_current_settings'] ?? [];
$flosc_current_ivr = $GLOBALS['flosc_current_ivr'] ?? '';
$flosc_token_docs_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'documentation',
    'doc'  => 'ref-admin',
], admin_url('admin.php')) . '#tab-payments';
$flosc_offers_url = add_query_arg([
    'page' => 'flosc-settings',
    'ivr'  => $flosc_current_ivr,
    'tab'  => 'offers',
    'view' => 'single',
], admin_url('admin.php'));

$flosc_global_tokens_per_message = max(1, intval(get_option('flosc_tokens_communication_tokens_per_message', 5000)));
$flosc_global_nom_num = max(1, intval(get_option('flosc_tokens_nominal_millicents_per_token_numerator', 1)));
$flosc_global_nom_den = max(1, intval(get_option('flosc_tokens_nominal_millicents_per_token_denominator', 1)));
$flosc_global_real_num = max(1, intval(get_option('flosc_tokens_real_millicents_per_token_numerator', 1)));
$flosc_global_real_den = max(1, intval(get_option('flosc_tokens_real_millicents_per_token_denominator', 2)));

$flosc_tokens_per_message = max(1, intval($flosc_flow_settings['tokens_communication_tokens_per_message'] ?? $flosc_global_tokens_per_message));
$flosc_nom_num = max(1, intval($flosc_flow_settings['tokens_nominal_millicents_per_token_numerator'] ?? $flosc_global_nom_num));
$flosc_nom_den = max(1, intval($flosc_flow_settings['tokens_nominal_millicents_per_token_denominator'] ?? $flosc_global_nom_den));
$flosc_real_num = max(1, intval($flosc_flow_settings['tokens_real_millicents_per_token_numerator'] ?? $flosc_global_real_num));
$flosc_real_den = max(1, intval($flosc_flow_settings['tokens_real_millicents_per_token_denominator'] ?? $flosc_global_real_den));
$flosc_nominal_rate = $flosc_nom_den > 0 ? ($flosc_nom_num / $flosc_nom_den) : 1.0;
$flosc_real_rate = $flosc_real_den > 0 ? ($flosc_real_num / $flosc_real_den) : 1.0;
$flosc_nominal_rate_display = rtrim(rtrim(number_format($flosc_nominal_rate, 3, '.', ''), '0'), '.');
$flosc_real_rate_display = rtrim(rtrim(number_format($flosc_real_rate, 3, '.', ''), '0'), '.');
if ($flosc_nominal_rate_display === '') {
    $flosc_nominal_rate_display = '1';
}
if ($flosc_real_rate_display === '') {
    $flosc_real_rate_display = '1';
}

$flosc_nominal_millicents_per_message = intval(round(($flosc_tokens_per_message * $flosc_nom_num) / $flosc_nom_den));
$flosc_real_millicents_per_message = intval(round(($flosc_tokens_per_message * $flosc_real_num) / $flosc_real_den));
$flosc_chat_token_enforcement = array_key_exists('chat_token_enforcement', $flosc_flow_settings)
    ? !empty($flosc_flow_settings['chat_token_enforcement'])
    : true;
$flosc_guest_token_grant = max(0, intval($flosc_flow_settings['guest_token_grant'] ?? $flosc_tokens_per_message));
$flosc_member_token_grant = max(0, intval($flosc_flow_settings['member_token_grant'] ?? $flosc_guest_token_grant));

$flosc_product_token_grant_recurring = max(0, intval(
    $flosc_flow_settings['product_token_grant_recurring']
    ?? $flosc_flow_settings['subscription_monthly_token_grant']
    ?? 10000
));
$flosc_product_token_cap = max(0, intval(
    $flosc_flow_settings['product_token_cap']
    ?? $flosc_flow_settings['subscription_token_cap']
    ?? 35000
));
$flosc_product_token_grant_recurring_yearly = max(0, intval(
    $flosc_flow_settings['product_token_grant_recurring_yearly']
    ?? $flosc_flow_settings['subscription_yearly_token_grant']
    ?? $flosc_product_token_cap
));
$flosc_product_token_grant_onetime = max(0, intval(
    $flosc_flow_settings['product_token_grant_onetime']
    ?? $flosc_product_token_grant_recurring
));

$flosc_low_token_threshold = max(0, intval($flosc_flow_settings['visitor_low_token_threshold'] ?? 0));
$flosc_default_low_message = 'You\'re running low on chat tokens. Pretty soon, you\'ll be invited to register or log in to receive {token_grant} more tokens.';
$flosc_low_message = trim((string) ($flosc_flow_settings['visitor_low_tokens_message'] ?? $flosc_default_low_message));
if ($flosc_low_message === '') {
    $flosc_low_message = $flosc_default_low_message;
}
$flosc_default_depleted_message = 'Dear guest, your chat tokens are used up for now. Please get in touch with Dainis personally, communicate with him in one of your shared groups, or make sure your purchases have been registered to your account so you have more chat tokens. I\'ll be shutting down this chat for now. Thanks for stopping by! Sincerely, Br3nda, Dainis\' virtual AI assistant.';
$flosc_depleted_message = trim((string) ($flosc_flow_settings['visitor_tokens_depleted_message'] ?? $flosc_default_depleted_message));
if ($flosc_depleted_message === '') {
    $flosc_depleted_message = $flosc_default_depleted_message;
}
$flosc_session_end_redirect_url = trim((string) ($flosc_flow_settings['visitor_session_end_redirect_url'] ?? ''));
$flosc_depleted_contact_mode = sanitize_key((string) ($flosc_flow_settings['visitor_depleted_contact_mode'] ?? 'message'));
if (!in_array($flosc_depleted_contact_mode, ['message', 'in_chat_form'], true)) {
    $flosc_depleted_contact_mode = 'message';
}

// Products (offers) for this flow — active first.
$flosc_offers = is_array($flosc_flow_settings['offers'] ?? null) ? $flosc_flow_settings['offers'] : [];
$flosc_product_rows = [];
foreach ($flosc_offers as $flosc_oid => $flosc_offer) {
    if (!is_array($flosc_offer)) {
        continue;
    }
    $status = strtolower((string) ($flosc_offer['status'] ?? ''));
    $active = !empty($flosc_offer['active']) || $status === 'active';
    if ($status === 'draft') {
        $active = false;
    }
    $type = strtolower((string) ($flosc_offer['type'] ?? 'one_time'));
    $processor = strtolower((string) ($flosc_offer['pricing']['processor'] ?? $flosc_offer['processor'] ?? 'paypal'));
    $price = floatval($flosc_offer['pricing']['price'] ?? $flosc_offer['price'] ?? 0);
    $tokens = is_array($flosc_offer['tokens'] ?? null) ? $flosc_offer['tokens'] : [];
    $source = sanitize_key((string) ($tokens['source'] ?? 'flow'));
    if (!in_array($source, ['flow', 'custom', 'none'], true)) {
        // Infer: explicit amount means custom.
        $source = (isset($tokens['amount']) && $tokens['amount'] !== '' && $tokens['amount'] !== null) ? 'custom' : 'flow';
    }
    $mode = sanitize_key((string) ($tokens['mode'] ?? ''));
    if ($mode === '') {
        $mode = ($type === 'subscription') ? 'recurring' : 'onetime';
    }
    if (!in_array($mode, ['onetime', 'recurring', 'recurring_yearly'], true)) {
        $mode = 'onetime';
    }
    $cap_mode = sanitize_key((string) ($tokens['cap_mode'] ?? 'flow'));
    if (!in_array($cap_mode, ['flow', 'none', 'custom'], true)) {
        if (array_key_exists('cap', $tokens) && $tokens['cap'] !== '' && $tokens['cap'] !== null) {
            $cap_mode = (intval($tokens['cap']) === 0 && array_key_exists('cap', $tokens)) ? 'none' : 'custom';
        } else {
            $cap_mode = 'flow';
        }
    }

    // Effective preview numbers for the summary chip.
    if ($source === 'none') {
        $eff_grant = 0;
        $eff_cap_label = '—';
        $eff_mode_label = 'No tokens';
    } else {
        if ($source === 'custom' && isset($tokens['amount']) && $tokens['amount'] !== '') {
            $eff_grant = max(0, intval($tokens['amount']));
            $eff_mode_label = $mode;
        } else {
            if ($type === 'subscription' || $mode === 'recurring_yearly') {
                // Prefer yearly if mode says so, else recurring for subs.
                if ($mode === 'recurring_yearly') {
                    $eff_grant = $flosc_product_token_grant_recurring_yearly;
                    $eff_mode_label = 'recurring_yearly';
                } else {
                    $eff_grant = $flosc_product_token_grant_recurring;
                    $eff_mode_label = 'recurring';
                }
            } else {
                $eff_grant = $flosc_product_token_grant_onetime;
                $eff_mode_label = 'onetime';
            }
            if ($source === 'flow') {
                $eff_mode_label = 'flow → ' . $eff_mode_label;
            }
        }
        if ($cap_mode === 'none') {
            $eff_cap_label = 'no cap';
        } elseif ($cap_mode === 'custom' && isset($tokens['cap']) && $tokens['cap'] !== '') {
            $eff_cap_label = 'cap ' . number_format_i18n(max(0, intval($tokens['cap'])));
        } else {
            $eff_cap_label = $flosc_product_token_cap > 0
                ? 'cap ' . number_format_i18n($flosc_product_token_cap)
                : 'no cap';
        }
    }

    $flosc_product_rows[] = [
        'id' => (string) ($flosc_offer['id'] ?? $flosc_oid),
        'name' => (string) ($flosc_offer['name'] ?? $flosc_oid),
        'type' => $type,
        'processor' => $processor,
        'price' => $price,
        'display_price' => (string) ($flosc_offer['display_price'] ?? ''),
        'active' => $active,
        'status' => $status !== '' ? $status : ($active ? 'active' : 'inactive'),
        'source' => $source,
        'mode' => $mode,
        'amount' => isset($tokens['amount']) && $tokens['amount'] !== '' ? max(0, intval($tokens['amount'])) : '',
        'cap_mode' => $cap_mode,
        'cap' => isset($tokens['cap']) && $tokens['cap'] !== '' ? max(0, intval($tokens['cap'])) : '',
        'eff_grant' => $eff_grant,
        'eff_cap_label' => $eff_cap_label,
        'eff_mode_label' => $eff_mode_label,
    ];
}

usort($flosc_product_rows, static function ($a, $b) {
    if ($a['active'] !== $b['active']) {
        return $a['active'] ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

$flosc_active_count = count(array_filter($flosc_product_rows, static function ($r) {
    return !empty($r['active']);
}));
$flosc_filter = sanitize_key((string) (wp_unslash($_GET['product_filter'] ?? 'active')));
if (!in_array($flosc_filter, ['active', 'all'], true)) {
    $flosc_filter = 'active';
}
$flosc_visible_products = array_values(array_filter($flosc_product_rows, static function ($r) use ($flosc_filter) {
    return $flosc_filter === 'all' || !empty($r['active']);
}));
?>

<div class="flosc-settings-docs-row">
    <a href="<?php echo esc_url($flosc_token_docs_url); ?>" class="flosc-settings-docs-link">Docs</a>
</div>

<div class="flosc-token-mgmt">
    <p class="flosc-token-mgmt__lead">
        floscTokens power chat billing on this flow. Set <strong>flow defaults</strong>, then open each
        <strong>product</strong> accordion to inherit those defaults or customize grant / cap per product.
    </p>

    <!-- ========== PRODUCTS ========== -->
    <div class="flosc-token-section">
        <div class="flosc-token-section__bar">
            <h2 class="flosc-token-section__title">Products</h2>
            <div class="flosc-token-section__tools">
                <label class="screen-reader-text" for="flosc-token-product-filter">Filter products</label>
                <select id="flosc-token-product-filter" class="flosc-token-filter" data-flosc-action="redirect-on-change">
                    <option value="<?php echo esc_url(add_query_arg('product_filter', 'active')); ?>" <?php selected($flosc_filter, 'active'); ?>>
                        Active only (<?php echo (int) $flosc_active_count; ?>)
                    </option>
                    <option value="<?php echo esc_url(add_query_arg('product_filter', 'all')); ?>" <?php selected($flosc_filter, 'all'); ?>>
                        All products (<?php echo count($flosc_product_rows); ?>)
                    </option>
                </select>
                <a class="button button-small" href="<?php echo esc_url($flosc_offers_url); ?>">Manage offers</a>
            </div>
        </div>

        <?php if (empty($flosc_visible_products)) : ?>
            <div class="flosc-token-empty">
                <strong>No <?php echo $flosc_filter === 'active' ? 'active ' : ''; ?>products on this flow.</strong>
                <p>Create or activate an offer under Offers, then return here to set token grants.</p>
                <a class="button button-primary" href="<?php echo esc_url($flosc_offers_url); ?>">Go to Offers</a>
            </div>
        <?php else : ?>
            <div class="flosc-token-product-list">
                <?php foreach ($flosc_visible_products as $p) :
                    $pid = $p['id'];
                    $safe = esc_attr($pid);
                    $is_custom = ($p['source'] === 'custom');
                    $is_none = ($p['source'] === 'none');
                    $status_class = $p['active'] ? 'is-active' : 'is-inactive';
                    $price_label = $p['display_price'] !== ''
                        ? $p['display_price']
                        : ('$' . number_format((float) $p['price'], 2));
                    $type_label = $p['type'] === 'subscription' ? 'Subscription' : ($p['type'] === 'tokens' ? 'Token pack' : 'One-time');
                    $summary_tokens = $is_none
                        ? 'No product tokens'
                        : ('+' . number_format_i18n((int) $p['eff_grant']) . ' · ' . $p['eff_cap_label']);
                    ?>
                    <details class="flosc-token-product <?php echo esc_attr($status_class); ?>" data-product-id="<?php echo $safe; ?>">
                        <summary class="flosc-token-product__head">
                            <span class="flosc-token-product__caret" aria-hidden="true">&rsaquo;</span>
                            <span class="flosc-token-product__name"><?php echo esc_html($p['name']); ?></span>
                            <span class="flosc-token-product__meta"><?php echo esc_html($type_label); ?> · <?php echo esc_html($p['processor']); ?></span>
                            <span class="flosc-token-product__price"><?php echo esc_html($price_label); ?></span>
                            <span class="flosc-token-product__chip <?php echo $is_none ? 'is-muted' : ($is_custom ? 'is-custom' : 'is-flow'); ?>">
                                <?php echo esc_html($summary_tokens); ?>
                            </span>
                            <span class="flosc-token-product__status <?php echo esc_attr($status_class); ?>">
                                <?php echo $p['active'] ? '● Active' : '○ ' . esc_html(ucfirst($p['status'])); ?>
                            </span>
                        </summary>
                        <div class="flosc-token-product__body">
                            <input type="hidden" name="flosc_product_tokens[<?php echo $safe; ?>][id]" value="<?php echo $safe; ?>">

                            <div class="flosc-token-product__grid">
                                <label class="flosc-token-field">
                                    <span class="flosc-token-field__label">Token source</span>
                                    <select
                                        name="flosc_product_tokens[<?php echo $safe; ?>][source]"
                                        class="flosc-token-source-select"
                                        data-flosc-token-source
                                        data-product="<?php echo $safe; ?>"
                                    >
                                        <option value="flow" <?php selected($p['source'], 'flow'); ?>>Use flow defaults</option>
                                        <option value="custom" <?php selected($p['source'], 'custom'); ?>>Custom for this product</option>
                                        <option value="none" <?php selected($p['source'], 'none'); ?>>No product token grant</option>
                                    </select>
                                    <span class="description">Flow defaults live in the section below. Custom lets this product add more or less, once or on a schedule.</span>
                                </label>

                                <div class="flosc-token-product__custom <?php echo $is_custom ? '' : 'is-disabled'; ?>" data-flosc-token-custom="<?php echo $safe; ?>">
                                    <label class="flosc-token-field">
                                        <span class="flosc-token-field__label">Grant mode</span>
                                        <select name="flosc_product_tokens[<?php echo $safe; ?>][mode]">
                                            <option value="onetime" <?php selected($p['mode'], 'onetime'); ?>>One-time (each purchase)</option>
                                            <option value="recurring" <?php selected($p['mode'], 'recurring'); ?>>Recurring cycle (e.g. monthly)</option>
                                            <option value="recurring_yearly" <?php selected($p['mode'], 'recurring_yearly'); ?>>Recurring yearly cycle</option>
                                        </select>
                                    </label>

                                    <label class="flosc-token-field">
                                        <span class="flosc-token-field__label">Token grant amount</span>
                                        <input
                                            type="number"
                                            name="flosc_product_tokens[<?php echo $safe; ?>][amount]"
                                            value="<?php echo esc_attr($p['amount'] === '' ? '' : (string) $p['amount']); ?>"
                                            min="0"
                                            step="1"
                                            class="regular-text"
                                            placeholder="<?php echo esc_attr((string) $flosc_product_token_grant_onetime); ?>"
                                        >
                                        <span class="description">Leave blank to use the matching flow default for the selected mode.</span>
                                    </label>

                                    <label class="flosc-token-field">
                                        <span class="flosc-token-field__label">Cap</span>
                                        <select
                                            name="flosc_product_tokens[<?php echo $safe; ?>][cap_mode]"
                                            data-flosc-token-cap-mode
                                            data-product="<?php echo $safe; ?>"
                                        >
                                            <option value="flow" <?php selected($p['cap_mode'], 'flow'); ?>>Use flow product cap (<?php echo esc_html($flosc_product_token_cap > 0 ? number_format_i18n($flosc_product_token_cap) : 'none'); ?>)</option>
                                            <option value="none" <?php selected($p['cap_mode'], 'none'); ?>>No cap</option>
                                            <option value="custom" <?php selected($p['cap_mode'], 'custom'); ?>>Custom cap</option>
                                        </select>
                                    </label>

                                    <label class="flosc-token-field flosc-token-cap-custom <?php echo $p['cap_mode'] === 'custom' ? '' : 'is-disabled'; ?>" data-flosc-token-cap-input="<?php echo $safe; ?>">
                                        <span class="flosc-token-field__label">Custom cap value</span>
                                        <input
                                            type="number"
                                            name="flosc_product_tokens[<?php echo $safe; ?>][cap]"
                                            value="<?php echo esc_attr($p['cap'] === '' ? '' : (string) $p['cap']); ?>"
                                            min="0"
                                            step="1"
                                            class="regular-text"
                                            placeholder="<?php echo esc_attr((string) $flosc_product_token_cap); ?>"
                                        >
                                    </label>
                                </div>
                            </div>

                            <p class="flosc-token-product__hint">
                                Preview:
                                <strong><?php echo esc_html($summary_tokens); ?></strong>
                                · mode <code><?php echo esc_html($p['eff_mode_label']); ?></code>
                                · id <code><?php echo esc_html($pid); ?></code>
                                · <a href="<?php echo esc_url(add_query_arg(['tab' => 'offers', 'edit_offer' => $pid], $flosc_offers_url)); ?>">Edit offer</a>
                            </p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ========== FLOW DEFAULTS ========== -->
    <details class="flosc-token-accordion" open>
        <summary class="flosc-token-accordion__head">
            <span class="flosc-token-product__caret" aria-hidden="true">&rsaquo;</span>
            <span class="flosc-token-accordion__title">Flow product defaults</span>
            <span class="flosc-token-accordion__preview">
                one-time +<?php echo esc_html(number_format_i18n($flosc_product_token_grant_onetime)); ?>
                · recurring +<?php echo esc_html(number_format_i18n($flosc_product_token_grant_recurring)); ?>
                · yearly +<?php echo esc_html(number_format_i18n($flosc_product_token_grant_recurring_yearly)); ?>
                · cap <?php echo $flosc_product_token_cap > 0 ? esc_html(number_format_i18n($flosc_product_token_cap)) : 'none'; ?>
            </span>
        </summary>
        <div class="flosc-token-accordion__body">
            <p class="description">Used when a product is set to <strong>Use flow defaults</strong>, or when custom amount/cap is left blank.</p>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="flow_product_token_grant_onetime">One-time Product Token Grant</label></th>
                    <td>
                        <input type="number" id="flow_product_token_grant_onetime" name="flow_product_token_grant_onetime" value="<?php echo esc_attr($flosc_product_token_grant_onetime); ?>" min="0" step="1" class="regular-text">
                        <p class="description">Default tokens for one-time paid products.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_product_token_grant_recurring">Recurring Product Token Grant</label></th>
                    <td>
                        <input type="number" id="flow_product_token_grant_recurring" name="flow_product_token_grant_recurring" value="<?php echo esc_attr($flosc_product_token_grant_recurring); ?>" min="0" step="1" class="regular-text">
                        <p class="description">Default tokens on each paid recurring cycle (e.g. monthly).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_product_token_grant_recurring_yearly">Recurring Yearly Product Token Grant</label></th>
                    <td>
                        <input type="number" id="flow_product_token_grant_recurring_yearly" name="flow_product_token_grant_recurring_yearly" value="<?php echo esc_attr($flosc_product_token_grant_recurring_yearly); ?>" min="0" step="1" class="regular-text">
                        <p class="description">Default tokens on each paid yearly cycle.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_product_token_cap">Product Token Cap</label></th>
                    <td>
                        <input type="number" id="flow_product_token_cap" name="flow_product_token_cap" value="<?php echo esc_attr($flosc_product_token_cap); ?>" min="0" step="1" class="regular-text">
                        <p class="description"><strong>0 = no cap.</strong> At the cap, payment still processes; product credits become 0 until the wallet is spent down.</p>
                    </td>
                </tr>
            </table>
        </div>
    </details>

    <!-- ========== WALLETS ========== -->
    <details class="flosc-token-accordion">
        <summary class="flosc-token-accordion__head">
            <span class="flosc-token-product__caret" aria-hidden="true">&rsaquo;</span>
            <span class="flosc-token-accordion__title">Visitor / Guest / Member wallets</span>
            <span class="flosc-token-accordion__preview">
                visitor <?php echo esc_html(number_format_i18n($flosc_tokens_per_message)); ?>
                · guest +<?php echo esc_html(number_format_i18n($flosc_guest_token_grant)); ?>
                · member +<?php echo esc_html(number_format_i18n($flosc_member_token_grant)); ?>
            </span>
        </summary>
        <div class="flosc-token-accordion__body">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="flow_chat_token_enforcement">Enforce Chat Token Charging</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="flow_chat_token_enforcement" name="flow_chat_token_enforcement" value="1" <?php checked($flosc_chat_token_enforcement); ?>>
                            Charge chat interactions against this flow's token wallets.
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_tokens_communication_tokens_per_message">Visitor Wallet Initial Amount</label></th>
                    <td>
                        <input type="number" id="flow_tokens_communication_tokens_per_message" name="flow_tokens_communication_tokens_per_message" value="<?php echo esc_attr($flosc_tokens_per_message); ?>" min="1" step="1" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_guest_token_grant">Guest Wallet Additional Grant</label></th>
                    <td>
                        <input type="number" id="flow_guest_token_grant" name="flow_guest_token_grant" value="<?php echo esc_attr($flosc_guest_token_grant); ?>" min="0" step="1" class="regular-text">
                        <p class="description">V→G once: guest = visitor remaining + this grant.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_member_token_grant">Member Wallet Additional Grant</label></th>
                    <td>
                        <input type="number" id="flow_member_token_grant" name="flow_member_token_grant" value="<?php echo esc_attr($flosc_member_token_grant); ?>" min="0" step="1" class="regular-text">
                        <p class="description">G→M once (Access Code / first membership): member = guest remaining + this grant.</p>
                    </td>
                </tr>
            </table>
        </div>
    </details>

    <!-- ========== ECONOMICS ========== -->
    <details class="flosc-token-accordion">
        <summary class="flosc-token-accordion__head">
            <span class="flosc-token-product__caret" aria-hidden="true">&rsaquo;</span>
            <span class="flosc-token-accordion__title">Billing conversion</span>
            <span class="flosc-token-accordion__preview">
                nominal <?php echo esc_html($flosc_nominal_rate_display); ?> · real <?php echo esc_html($flosc_real_rate_display); ?> millicents/token
            </span>
        </summary>
        <div class="flosc-token-accordion__body">
            <table class="form-table">
                <tr>
                    <th scope="row">Nominal Display Factor</th>
                    <td>
                        <input type="number" name="flow_tokens_nominal_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_nominal_rate_display); ?>" min="0.001" step="0.001" class="small-text">
                        <p class="description">Millicents per floscToken (display / model side).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Real Billing Factor</th>
                    <td>
                        <input type="number" name="flow_tokens_real_millicents_per_token_decimal" value="<?php echo esc_attr($flosc_real_rate_display); ?>" min="0.001" step="0.001" class="small-text">
                        <p class="description">Real API millicents per floscToken ($1 = 100,000 millicents). Converts provider cost → wallet debit.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Conversion Summary</th>
                    <td>
                        <p class="description">
                            Nominal ref: <strong><?php echo esc_html(number_format_i18n($flosc_nominal_millicents_per_message / 1000, 4)); ?> ¢</strong>
                            · Real ref: <strong><?php echo esc_html(number_format_i18n($flosc_real_millicents_per_message / 1000, 4)); ?> ¢</strong>
                            (at visitor wallet size <?php echo esc_html(number_format_i18n($flosc_tokens_per_message)); ?>).
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </details>

    <!-- ========== MESSAGING ========== -->
    <details class="flosc-token-accordion">
        <summary class="flosc-token-accordion__head">
            <span class="flosc-token-product__caret" aria-hidden="true">&rsaquo;</span>
            <span class="flosc-token-accordion__title">Low / depleted messaging</span>
            <span class="flosc-token-accordion__preview">threshold <?php echo esc_html(number_format_i18n($flosc_low_token_threshold)); ?></span>
        </summary>
        <div class="flosc-token-accordion__body">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="flow_visitor_low_token_threshold">Low Tokens Threshold</label></th>
                    <td>
                        <input type="number" id="flow_visitor_low_token_threshold" name="flow_visitor_low_token_threshold" value="<?php echo esc_attr($flosc_low_token_threshold); ?>" min="0" step="1" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_visitor_low_tokens_message">Low Tokens Message</label></th>
                    <td>
                        <input type="text" id="flow_visitor_low_tokens_message" name="flow_visitor_low_tokens_message" value="<?php echo esc_attr($flosc_low_message); ?>" class="large-text">
                        <p class="description">Placeholder: <code>{token_grant}</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_visitor_tokens_depleted_message">Depleted Token Message</label></th>
                    <td>
                        <input type="text" id="flow_visitor_tokens_depleted_message" name="flow_visitor_tokens_depleted_message" value="<?php echo esc_attr($flosc_depleted_message); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_visitor_session_end_redirect_url">Redirect After Session End</label></th>
                    <td>
                        <input type="url" id="flow_visitor_session_end_redirect_url" name="flow_visitor_session_end_redirect_url" value="<?php echo esc_attr($flosc_session_end_redirect_url); ?>" class="large-text" placeholder="https://…">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="flow_visitor_depleted_contact_mode">Depleted Token Mode</label></th>
                    <td>
                        <select id="flow_visitor_depleted_contact_mode" name="flow_visitor_depleted_contact_mode" class="regular-text">
                            <option value="message" <?php selected($flosc_depleted_contact_mode, 'message'); ?>>One Message Capture</option>
                            <option value="in_chat_form" <?php selected($flosc_depleted_contact_mode, 'in_chat_form'); ?>>In-Chat Contact Form</option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
    </details>
</div>

<script>
(function () {
    function setDisabled(el, off) {
        if (!el) return;
        el.classList.toggle('is-disabled', !!off);
        el.querySelectorAll('input, select, textarea').forEach(function (field) {
            // Keep fields enabled for POST; only dim UI. Cap input still posts when custom.
            field.disabled = false;
        });
    }

    document.querySelectorAll('[data-flosc-token-source]').forEach(function (sel) {
        var pid = sel.getAttribute('data-product');
        var custom = document.querySelector('[data-flosc-token-custom="' + pid + '"]');
        function sync() {
            setDisabled(custom, sel.value !== 'custom');
        }
        sel.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-flosc-token-cap-mode]').forEach(function (sel) {
        var pid = sel.getAttribute('data-product');
        var wrap = document.querySelector('[data-flosc-token-cap-input="' + pid + '"]');
        function sync() {
            setDisabled(wrap, sel.value !== 'custom');
        }
        sel.addEventListener('change', sync);
        sync();
    });
})();
</script>
