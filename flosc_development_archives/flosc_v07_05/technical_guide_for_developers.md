# FLOSC Technical Guide for Developers

**Version:** 7.0.5  
**Last Updated:** January 2026

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Plugin Initialization](#plugin-initialization)
3. [REST API Endpoints](#rest-api-endpoints)
4. [Payment System](#payment-system)
5. [Quiz System](#quiz-system)
6. [AI Integration](#ai-integration)
7. [Common Problems and Solutions](#common-problems-and-solutions)
8. [Version History Issues](#version-history-issues)
9. [Testing Checklist](#testing-checklist)

---

## Architecture Overview

FLOSC (Freeline-Login-Offer-Sale-Content) is a WordPress plugin that implements a quiz-based learning and sales funnel system.

### Core Components

| Component | File | Purpose |
|-----------|------|---------|
| Main Plugin | `flosc.php` | Plugin bootstrap, hooks, REST API registration |
| Sale Manager | `includes/sale/class-sale-manager.php` | Payment orchestration |
| Quiz Factory | `includes/class-quiz-type-factory.php` | Quiz type handling |
| AI Factory | `includes/class-ai-provider-factory.php` | AI provider abstraction |
| Session Manager | `includes/class-session-manager.php` | User session tracking |
| Lesson Manager | `includes/class-lesson-manager.php` | Content access control |

### Data Flow

```
Visitor → Quiz → Score → Login Gate → Offer → Payment → Content Access
```

---

## Plugin Initialization

### Hook Sequence

```php
// flosc.php line ~1644
add_action('plugins_loaded', 'flosc');
```

**Critical:** The plugin initializes on `plugins_loaded` hook. Previous versions that used `init` hook caused fatal errors because WordPress wasn't fully loaded.

### Class Instantiation

```php
function flosc() {
    return FLOSC::instance();
}

class FLOSC {
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
}
```

---

## REST API Endpoints

### Namespace

All endpoints use namespace: `flosc/v1`

### Endpoint List

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/quiz/start` | POST | Public | Start quiz session |
| `/quiz/answer` | POST | Public | Submit quiz answer |
| `/quiz/complete` | POST | Public | Complete quiz |
| `/ai/query` | POST | Logged-in | Send AI query |
| `/payment/create-intent` | POST | Logged-in | Create payment intent |
| `/payment/webhook/stripe` | POST | Webhook | Stripe webhook |
| `/payment/webhook/clickbank` | POST | Webhook | ClickBank IPN |

### Permission Callbacks

```php
// Public endpoint (rate limited)
'permission_callback' => [$this, 'check_public_permission']

// Authenticated endpoint
'permission_callback' => function($request) {
    return is_user_logged_in();
}

// Webhook endpoint (signature verified separately)
'permission_callback' => '__return_true'
```

---

## Payment System

### Provider Architecture

All payment providers extend `FLOSC_Payment_Provider`:

```php
abstract class FLOSC_Payment_Provider {
    abstract public function get_id();
    abstract public function get_name();
    abstract public function is_configured();
    abstract public function handle_webhook($payload, $headers);
    abstract public function create_intent($user_id, $offer, $params);
    abstract public function complete_purchase($user_id, $intent_id, $params);
}
```

### Available Providers

| Provider | File | Notes |
|----------|------|-------|
| Stripe | `class-stripe-provider.php` | Credit card payments |
| ClickBank | `class-clickbank-provider.php` | Marketplace with affiliates |
| Token | `class-token-provider.php` | Internal token system |
| Affiliate | `class-affiliate-provider.php` | Affiliate tracking |

### ClickBank IPN Handling

**Critical Bug Fixed:** Previous versions used `sha256()` which doesn't exist in PHP. Correct usage:

```php
// WRONG - Fatal Error
$expected = strtoupper(sha256($string_to_hash));

// CORRECT
$expected = strtoupper(hash('sha256', $string_to_hash));
```

### Idempotency

Webhooks use transient-based idempotency to prevent replay attacks:

```php
$idempotency_key = 'flosc_cb_' . md5($receipt . $transaction_type);

if (get_transient($idempotency_key)) {
    return ['already_processed' => true];
}

// Process payment...

set_transient($idempotency_key, time(), DAY_IN_SECONDS);
```

---

## Quiz System

### Quiz Types

Located in `includes/quiz-types/`:

1. **Multiple Choice** - Standard A/B/C/D selection
2. **Fill in Blank** - Text input completion
3. **Speaking** - Audio recording with STT
4. **Matching** - Pair matching exercise
5. **Ordering** - Sequence ordering

### Quiz Factory

```php
$factory = new FLOSC_Quiz_Type_Factory();
$quiz_type = $factory->create($type_id);
$result = $quiz_type->evaluate($user_answer, $correct_answer);
```

---

## AI Integration

### Provider Factory

```php
$factory = new FLOSC_AI_Provider_Factory();
$provider = $factory->create('openai'); // or 'anthropic'
$response = $provider->query($message, $context);
```

### Configuration Files

AI knowledge base files stored in: `ai_configuration_files/`

- Markdown files only (`.md`)
- Used to provide context to AI queries
- Managed via admin interface

---

## Common Problems and Solutions

### Problem 1: Site shows "Critical Error" after activation

**Cause:** Plugin conflict or PHP version incompatibility

**Solution:**
1. Access site via FTP/File Manager
2. Rename plugin folder to disable: `flosc-framework` → `flosc-framework-disabled`
3. Check PHP error log for actual error
4. Enable WP_DEBUG in wp-config.php:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```

### Problem 2: App page shows blank or old content

**Cause:** Browser cache

**Solution:**
1. Clear ALL browser data (cookies, cache, localStorage)
2. Test in private/incognito window
3. Flush permalinks: Settings → Permalinks → Save Changes

### Problem 3: REST API returns 403 Forbidden

**Cause:** Nonce verification failure or permissions issue

**Solution:**
1. Ensure frontend sends `X-WP-Nonce` header
2. Check if user is logged in for authenticated endpoints
3. Verify `wp_create_nonce('wp_rest')` is called on page load

### Problem 4: ClickBank payments not processing

**Cause:** IPN not reaching site or signature mismatch

**Solution:**
1. Verify IPN URL is set in ClickBank account
2. Check vendor nickname matches exactly
3. Verify secret key is correct
4. Check server error logs for webhook failures

### Problem 5: "Headers already sent" error

**Cause:** Output before `setcookie()` or `wp_redirect()`

**Solution:**
1. Ensure no whitespace before `<?php`
2. Remove BOM from PHP files
3. Don't use `echo` before headers sent
4. Avoid cookie array syntax on PHP < 7.3:
   ```php
   // PHP 7.3+ only
   setcookie('name', 'value', ['expires' => time() + 3600]);
   
   // PHP 7.2 compatible
   setcookie('name', 'value', time() + 3600, '/');
   ```

### Problem 6: Session ID collision / enumeration

**Cause:** Sequential session IDs

**Solution:** Use timestamp + random string:
```php
// WRONG - predictable
$session_id = $wpdb->get_var("SELECT MAX(id)") + 1;

// CORRECT - unpredictable
$session_id = time() . '_' . wp_generate_password(8, false);
```

---

## Version History Issues

### Versions v06.01-v07.04 Problems

Multiple versions were created rapidly (14 in 24 hours) with various issues:

| Version | Issue |
|---------|-------|
| v06.01-v06.07 | Added Logger/Validator dependencies that broke sites |
| v06.08 | Copy of v05.08 with only version number changed |
| v07.01-v07.04 | Various attempts to fix, introduced new problems |

### Key Lessons

1. **Don't add dependencies without testing** - Logger and Validator classes were added without verifying all call sites
2. **Cookie array syntax breaks PHP 7.2** - Use traditional `setcookie()` parameters
3. **CSRF checks can block legitimate requests** - Ensure frontend sends nonces before requiring them
4. **`init` hook is too early** - Use `plugins_loaded` for plugin initialization

### v07.05 Resolution

v07.05 returns to v05.08 base (proven stable) and adds only:
- ClickBank provider (self-contained)
- Directory renaming (cosmetic)
- No new dependencies

---

## Testing Checklist

### Before Deployment

- [ ] Plugin activates without errors
- [ ] Admin pages load (Settings, Payments, Offers)
- [ ] Frontend app page renders
- [ ] Quiz starts and completes
- [ ] Login gate appears after quiz
- [ ] User registration works
- [ ] Payment flow works (Stripe/ClickBank)
- [ ] AI queries return responses
- [ ] No JavaScript console errors
- [ ] No PHP errors in debug.log

### Payment Testing

**Stripe:**
- [ ] Test mode checkout works
- [ ] Webhook receives events
- [ ] Access granted on successful payment

**ClickBank:**
- [ ] Sandbox IPN received
- [ ] Signature verification passes
- [ ] User created on sale
- [ ] Access revoked on refund

### Browser Testing

- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Mobile browsers
- [ ] Private/Incognito mode

---

## File Reference

### Main Plugin (flosc.php)

| Line Range | Purpose |
|------------|---------|
| 1-20 | Plugin header, constants |
| 21-100 | Class definition, singleton |
| 100-200 | Permission callbacks, rate limiting |
| 200-500 | REST API endpoint registration |
| 500-800 | Admin menu, settings registration |
| 800-1000 | Admin page render functions |
| 1000-1400 | Quiz, AI, payment handlers |
| 1400-1660 | Utility functions, activation |

### Key Functions

```php
// Get plugin instance
$flosc = flosc();

// Get sale manager
$sale = FLOSC_Sale_Manager::instance();

// Get payment provider
$provider = $sale->get_provider('clickbank');

// Check user access
$has_access = flosc_user_has_access($user_id, 'premium');
```

---

## Contact

For technical questions or issues not covered in this guide, contact the development team with:

1. WordPress version
2. PHP version
3. Error messages (from debug.log)
4. Steps to reproduce
5. Browser console output (if frontend issue)
