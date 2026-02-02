# What's New in FLOSC v7.0.5

**Release Date:** January 2026  
**Base Version:** v5.0.8 (stable)

---

## Overview

FLOSC v7.0.5 is a stable release based on the proven v5.0.8 codebase with targeted additions:

- ClickBank payment provider integration
- Renamed AI system directories for clarity
- Clean, tested, production-ready code

---

## New Features

### ClickBank Payment Provider

Full integration with ClickBank marketplace:

- **IPN Webhook Handling** - Automatic processing of sale, refund, rebill, and subscription events
- **Signature Verification** - Secure SHA256 verification of incoming notifications
- **Idempotency Protection** - Prevents duplicate processing of replayed webhooks
- **Auto User Creation** - Creates WordPress users automatically on purchase
- **Welcome Emails** - Sends login credentials to new customers
- **Refund Handling** - Automatically revokes access on refund/chargeback

**Settings:**
- Enable/Disable toggle
- Sandbox/Live mode switch
- Vendor Nickname
- Secret Key
- Product SKU
- Access Level on purchase

---

## Changes

### Renamed: AI Knowledge Base

The AI configuration system has been renamed for clarity:

| Old Name | New Name |
|----------|----------|
| `ai_orientation_files/` | `ai_configuration_files/` |
| `ai-orientation.php` | `ai-knowledge-base.php` |

Functionality remains identical. Only directory and file names changed.

---

## Technical Notes

### File Structure

```
flosc_v07_05/
├── flosc.php                      # Main plugin file (1665 lines)
├── assets/
│   ├── css/flosc-app.css
│   └── js/flosc-app.js
├── includes/
│   ├── class-ai-provider-factory.php
│   ├── class-ivr-manager.php
│   ├── class-lesson-manager.php
│   ├── class-pronunciation-analyzer.php
│   ├── class-quiz-type-factory.php
│   ├── class-session-manager.php
│   ├── class-stt-provider-factory.php
│   ├── quiz-types/
│   └── sale/
│       ├── class-sale-manager.php
│       ├── class-offer-manager.php
│       ├── class-usage-tracker.php
│       ├── class-access-manager.php
│       ├── class-payment-provider.php
│       └── providers/
│           ├── class-stripe-provider.php
│           ├── class-token-provider.php
│           ├── class-affiliate-provider.php
│           └── class-clickbank-provider.php  # NEW
├── templates/
│   ├── flosc-app.php
│   └── admin/
│       ├── settings.php
│       ├── payments.php
│       ├── offers.php
│       ├── ivr-settings.php
│       ├── ai-config.php
│       └── ai-knowledge-base.php  # RENAMED
└── ai_configuration_files/        # RENAMED
    └── README.md
```

### What's NOT Included

This version intentionally excludes experimental features from v6.x that caused instability:

- ❌ class-logger.php (unnecessary dependency)
- ❌ class-validator.php (unnecessary dependency)
- ❌ Cookie array syntax (PHP 7.3+ only)
- ❌ CSRF nonce verification on all endpoints (blocked legitimate requests)
- ❌ Enhanced rate limiting with cookies (header issues)

These features may be added in future versions after proper testing.

---

## Installation

1. Deactivate and delete any existing FLOSC version
2. Upload `flosc_v07_05.zip` via WordPress Plugins → Add New → Upload
3. Activate the plugin
4. Clear browser cache completely (Cmd+Shift+Delete on Mac, Ctrl+Shift+Delete on Windows)
5. Flush permalinks: Settings → Permalinks → Save Changes
6. Test in private/incognito browser first

---

## Compatibility

- WordPress: 5.0+
- PHP: 7.2+
- Tested with: BuddyBoss, WishList Member, WooCommerce

---

## Support

For issues or questions, contact the development team.
