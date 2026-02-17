# Claude Research Prompt: Get PayPal Sandbox Working in FLOSC v1.7.2

## Context

I have a WordPress plugin called **FLOSC** (Freeline-Login-Offer-Sale-Content) — a quiz-based learning and conversational sales funnel. It has a chatbot UI where users go through a quiz, get results, and are presented with an offer card to purchase full access.

**Stripe is disabled** (their verification process is pending). **PayPal sandbox is the only payment method.**

The "Get Access Now" button on the offer card should open a PayPal payment modal. Currently it's not working — PayPal buttons don't appear, and it falls back to a fake "sandbox purchase" flow instead.

---

## PayPal Sandbox Credentials

```
Client ID: Ac9vXbpAO30vY9QmbPDMy3tUkTapXTWMZ0qPP4N0MdSK7DPT0wDII-9urwbDTkTsEZg9RajgYRxNRzWg
Secret: (stored in WordPress options — needs to be set via WP admin)
Mode: sandbox
Facilitator account: dainis-facilitator@dainis.net
PayPal sandbox API base: https://api-m.sandbox.paypal.com
```

---

## Current Architecture

### How PayPal credentials flow from WP → Frontend

1. **Provider class** (`includes/sale/providers/class-paypal-provider.php`):
   - `get_flow_setting($key)` checks `flosc()->get_setting('paypal_' . $key)` first (per-flow), then fallback `get_option('flosc_paypal_' . $key)`
   - `is_configured()` returns `!empty(client_id) && !empty(secret)`
   - `get_client_config()` returns `['clientId' => ..., 'mode' => ...]`

2. **Sale Manager** (`includes/sale/class-sale-manager.php`):
   - Registers `new FLOSC_PayPal_Provider()` at `$this->providers['paypal']`
   - `get_provider('paypal')` returns the instance
   - `get_providers()` returns all providers as `['stripe' => {...}, 'paypal' => {...}, ...]`

3. **Frontend template** (`admin/flosc-app.php` line 662-669):
   ```php
   echo wp_json_encode([
       'restUrl' => rest_url('flosc/v1/'),
       'apiUrl' => rest_url('flosc/v1'),
       'nonce' => wp_create_nonce('wp_rest'),
       'stripeKey' => '', // DISABLED in v1.7.1
       'paypalClientId' => $providers['paypal']['config']['clientId'] ?? '',
       // ...
   ]);
   ```
   **IMPORTANT**: `$providers` comes from `$this->sale_manager->get_providers()` on line 3456 of flosc.php:
   ```php
   private function get_introspection_providers() {
       $providers = $this->sale_manager->get_providers();
   ```
   But the providers array structure passed to the template might not match what `get_providers()` returns. The `get_providers()` returns **provider objects**, not arrays with a `['config']` key. So `$providers['paypal']['config']['clientId']` likely evaluates to `''`.

4. **PayPal JS SDK** enqueued in `flosc.php` line 2389-2396:
   ```php
   $paypal = $this->sale_manager->get_provider('paypal');
   if ($paypal && $paypal->is_configured()) {
       $pp_config = $paypal->get_client_config();
       $pp_client_id = $pp_config['clientId'] ?? '';
       if ($pp_client_id) {
           wp_enqueue_script('paypal-js', 'https://www.paypal.com/sdk/js?client-id=' . urlencode($pp_client_id) . '&currency=USD', [], null, true);
       }
   }
   ```

5. **JS frontend** (`assets/js/flosc-app.js`):
   ```javascript
   // In openCheckout():
   if (this.config.stripeKey || this.config.paypalClientId) {
       this.showPaymentModal(offerId);  // Opens PayPal modal
   } else {
       this.openSandboxPurchase();  // Fallback
   }
   
   // In showPaymentModal():
   const hasPayPal = !!this.config.paypalClientId && typeof paypal !== 'undefined';
   ```

### REST API Routes

```
POST /wp-json/flosc/v1/paypal/create-order   → paypal_create_order()
POST /wp-json/flosc/v1/paypal/capture-order   → paypal_capture_order()
```

Both require `is_user_logged_in` permission callback.

### Create Order Handler (flosc.php line 5260):
```php
public function paypal_create_order($request) {
    $offer_id = sanitize_text_field($request->get_param('offer_id'));
    $offer = $this->sale_manager->offers()->get_offer($offer_id);
    // ...
    $amount = floatval($offer['pricing']['price'] ?? $product['price'] ?? 0);
    // Calls $paypal->create_order($user, $amount, $currency, $offer_id)
    // NOTE: create_order expects $amount_cents but we pass dollars!
}
```

### PayPal Provider create_order (class-paypal-provider.php):
```php
public function create_order($user, $amount_cents, $currency, $offer_id) {
    $amount = number_format($amount_cents / 100, 2, '.', '');
    // Sends to PayPal API
}
```
**BUG**: The handler passes `$amount` (dollars, e.g. 39.00) but `create_order()` treats it as **cents** and divides by 100, so PayPal sees `$0.39` instead of `$39.00`.

### Capture Order Handler (flosc.php line 5296):
```php
$capture_result = $paypal->capture_order($order_id);
// Then gets 'transaction_id' from result
```
But `capture_order()` returns `capture_id`, not `transaction_id`. Another potential mismatch.

---

## Known Issues / Suspected Problems

### 1. PayPal Client ID not reaching the frontend
The `$providers['paypal']['config']['clientId']` path in flosc-app.php doesn't work because `get_providers()` returns **objects**, not nested arrays. The template needs to call `$paypal->get_client_config()` directly, like the SDK enqueue does.

### 2. Amount cents vs dollars mismatch
`paypal_create_order()` passes dollars to `create_order()` which expects cents.

### 3. `capture_order()` return key mismatch
Returns `capture_id` but handler reads `transaction_id`.

### 4. PayPal JS SDK might not load
If `is_configured()` returns false (because client_id or secret aren't in the right WP options), the SDK script never gets enqueued, so `typeof paypal === 'undefined'` in JS.

### 5. WP options not set
The provider reads from `flosc()->get_setting('paypal_client_id')` → checks flow settings, then `get_option('flosc_paypal_client_id')`. The PayPal credentials may not be saved in these options. There may be no admin UI to set them.

---

## What I Need You To Figure Out

1. **Trace the complete path** from WordPress database → PHP provider → JS frontend → PayPal SDK to identify exactly where the chain breaks.

2. **How to store PayPal sandbox credentials** — which WordPress options need to be set? What are the exact option names? Should I use `wp_options` directly or is there a settings page?

3. **Fix the cents vs dollars bug** — should the handler multiply by 100, or should the provider not divide?

4. **Fix the `$providers` array structure** in flosc-app.php so `paypalClientId` actually gets the client ID value.

5. **Fix capture_order return keys** — ensure `transaction_id` vs `capture_id` alignment.

6. **Verify the PayPal JS SDK URL format** — does it need `&intent=capture` or `&components=buttons`? What's the correct sandbox SDK URL?

7. **Test the complete flow**: User clicks "Get Access Now" → Payment modal opens with PayPal buttons → User logs into PayPal sandbox → Approves payment → Order is captured → User gets `flosc_plugin_member` access level → Page reloads with member content.

---

## Files to Review

| File | Purpose |
|------|---------|
| `includes/sale/providers/class-paypal-provider.php` | PayPal API calls (create/capture order, auth) |
| `includes/sale/class-sale-manager.php` | Provider registry, `get_providers()`, `get_provider()` |
| `flosc.php` lines 2389-2396 | PayPal JS SDK enqueue |
| `flosc.php` lines 5260-5390 | REST handlers: create-order, capture-order |
| `flosc.php` lines 2808-2820 | REST route registration |  
| `admin/flosc-app.php` lines 660-670 | Frontend JS config (where paypalClientId is set) |
| `assets/js/flosc-app.js` lines 3087-3098 | `openCheckout()` — decides PayPal vs sandbox |
| `assets/js/flosc-app.js` lines 4380-4555 | `showPaymentModal()` — renders PayPal buttons |

---

## Offer Structure (from WP database)

The offer that shows up has:
- ID: `full_access_001` (or `default-flosc-full-access`)
- Price: $39.00
- Display price: $100 (crossed out)
- Grants: `flosc_plugin_member` level
- Badge: "Best Value"

---

## Expected End State

When a logged-in user (e.g., "Tina Hotz") clicks "Get Access Now" on the offer card:
1. Payment modal opens showing "Complete Your Purchase" 
2. PayPal gold button renders (PayPal JS SDK)
3. Clicking PayPal opens PayPal sandbox login popup
4. User pays with sandbox buyer account
5. Payment captured, user gets `flosc_plugin_member` access
6. Page reloads and user sees member content

Please provide all the code fixes needed to make this work end-to-end.
