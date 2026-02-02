# FLOSC v05_09 - ClickBank Integration

**Release Date:** 2026-01-11

## New Features

### 🛒 ClickBank Payment Provider

Full ClickBank marketplace integration with IPN (Instant Payment Notification) support.

**Features:**
- Sandbox and Live modes
- IPN signature verification
- Automatic user creation on purchase
- Access level grants on verified purchase
- Refund/chargeback handling (revokes access)
- Subscription rebill tracking
- Subscription cancellation handling
- Affiliate tracking

**Admin Settings (FLOSC → Payments):**
- Enable/Disable ClickBank
- Mode (Sandbox/Live)
- Vendor Nickname
- Secret Key
- Product SKU
- Access Level on Purchase

**IPN URL:** `https://yoursite.com/wp-json/flosc/v1/webhooks/clickbank`

**Transaction Types Handled:**
- `SALE` / `TEST_SALE` - Grants access
- `RFND` / `CGBK` / `INSF` - Revokes access (refund/chargeback)
- `REBILL` / `TEST_REBILL` - Updates rebill date
- `CANCEL-REBILL` / `UNCANCEL-REBILL` - Updates subscription status

### Files Added
- `includes/sale/providers/class-clickbank-provider.php`

### Files Modified
- `flosc.php` - Added ClickBank settings registration, fixed cookie deletion
- `includes/sale/class-sale-manager.php` - Registered ClickBank provider
- `templates/admin/settings.php` - Added ClickBank settings UI

## Bug Fixes

### 🔧 Cookie Deletion Fix

**Problem:** Cookie set with secure options array but deleted with old-style parameters, causing cookie to persist.

**Fix:** Cookie deletion now uses matching options:
```php
setcookie('flosc_prelogin_score', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

## Payment Providers Now Available

| Provider | Description | Status |
|----------|-------------|--------|
| Stripe | Cards, Apple Pay, subscriptions | ✅ Full |
| ClickBank | Marketplace with affiliates | ✅ NEW |
| Tokens | Internal credit system | ✅ Full |
| Affiliate | Permission-based access | ✅ Full |

## ClickBank Setup Guide

**Sandbox Defaults (for testing):**
- Vendor Nickname: `SANDBOXVENDOR`
- Secret Key: `SANDBOX123456`
- Product SKU: `SANDBOXPRODUCT`
- Access Level: `full` (Content Phase)

1. Go to FLOSC → Payments in WordPress admin
2. Enable ClickBank
3. Enter your Vendor Nickname
4. Enter your Secret Key (from Account Settings → Advanced Tools)
5. Enter your Product SKU
6. Select Access Level to grant on purchase
7. Copy the IPN URL shown
8. In ClickBank: Account Settings → My Site → Advanced Tools → Instant Notification URL
9. Paste the IPN URL and save

## Testing ClickBank Integration

**Sandbox Mode:**
1. Set Mode to "Sandbox" in FLOSC settings
2. Use ClickBank's sandbox tools to simulate purchases
3. Check user meta for `_flosc_clickbank_receipt` after test purchase

**IPN Fields Used:**
- `ctransaction` - Transaction type
- `ctransreceipt` - Receipt number
- `cvendor` - Vendor nickname
- `ccustname` - Customer name
- `ccustemail` - Customer email
- `cproditem` - Product SKU
- `ctransamount` - Transaction amount
- `caffitid` - Affiliate ID (if any)
- `cverify` - Signature for verification

## Upgrade Notes

No database changes required. ClickBank integration is opt-in via settings.
