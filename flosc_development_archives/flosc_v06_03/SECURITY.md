# FLOSC Security Documentation

**Version:** 6.0.1
**Last Updated:** 2026-01-13

## Security Controls

### Authentication & Authorization

| Endpoint Type | Authentication | CSRF Protection | Rate Limit |
|---------------|---------------|-----------------|------------|
| Public (quiz) | None | No | Yes (5/hr visitor) |
| Authenticated | WordPress session | Yes (nonce) | Yes (20/hr) |
| Admin | WordPress capability | Yes (nonce) | No |
| Webhooks | Signature verification | No (not needed) | Yes (100/hr) |

### Rate Limiting

Rate limits are enforced per endpoint type:

- **Visitors:** 5 requests/hour per IP + cookie
- **Logged-in users:** 20 requests/hour per user ID
- **Webhooks:** 100 requests/hour per IP

Rate limit state is stored in WordPress transients with automatic expiration.

### CSRF Protection

All authenticated REST endpoints require a valid WordPress nonce:

```javascript
// Frontend must include nonce in requests
fetch('/wp-json/flosc/v1/ai-query', {
    method: 'POST',
    headers: {
        'X-WP-Nonce': floscConfig.nonce,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({ message: '...' })
});
```

### Payment Security

#### Stripe
- Webhook signature verification using `stripe-signature` header
- Secret key stored in WordPress options (not autoloaded)
- Payment intents created server-side only

#### ClickBank
- IPN signature verification using SHA-256
- Idempotency check via transients (24hr TTL)
- Vendor ID validation
- Case-insensitive email matching

### Input Validation

All user input is validated before processing:

- **Messages:** Max 10,000 characters, no null bytes
- **Arrays:** Max 5 levels deep, max 100 items
- **Emails:** WordPress `is_email()` validation
- **IDs:** Positive integers only

### Session Security

- Session IDs are cryptographically random (timestamp + random string)
- Sessions are tied to user ID
- No session enumeration possible

### XSS Prevention

- All markdown output sanitized via DOMPurify
- User input escaped with `escapeHtml()` before display
- Admin content sanitized with WordPress functions

### Cookie Security

All cookies use secure options:

```php
setcookie('name', 'value', [
    'expires' => time() + HOUR_IN_SECONDS,
    'path' => '/',
    'secure' => is_ssl(),
    'httponly' => true,
    'samesite' => 'Lax'
]);
```

## Logging

Security events are logged using `FLOSC_Logger`:

```php
FLOSC_Logger::security('Event description', [
    'ip' => $ip,
    'user_id' => $user_id,
    'endpoint' => $endpoint
]);
```

Sensitive data (passwords, API keys, tokens) is automatically redacted from logs.

## Threat Model

### Assets Protected
1. User credentials and personal data
2. Payment information (handled by Stripe/ClickBank, not stored)
3. AI API keys and quotas
4. User-generated content

### Threat Actors
1. **Script kiddies:** Automated scanning, credential stuffing
2. **Competitors:** Rate limit abuse, scraping
3. **Fraudsters:** Payment manipulation, replay attacks
4. **Malicious users:** XSS injection, CSRF attacks

### Mitigations

| Threat | Mitigation |
|--------|------------|
| Credential stuffing | Rate limiting, WordPress login protection |
| API abuse | Per-user rate limits, quota system |
| Payment fraud | Signature verification, idempotency |
| XSS | DOMPurify sanitization |
| CSRF | Nonce verification |
| Session hijacking | Secure cookies, user-bound sessions |

## Responsible Disclosure

If you discover a security vulnerability, please report it responsibly:

1. **DO NOT** disclose publicly until fixed
2. Email: security@flosc.io (or plugin author)
3. Include: Description, reproduction steps, potential impact
4. Expected response: Within 48 hours

## Security Checklist for Deployment

- [ ] All API keys are in production mode
- [ ] Webhook URLs are configured in payment providers
- [ ] SSL is enabled (for secure cookies)
- [ ] WP_DEBUG is disabled in production
- [ ] WordPress is updated to latest version
- [ ] File permissions are correct (755 dirs, 644 files)
- [ ] Database backups are configured
- [ ] Error logging is enabled but not displayed

## Version History

| Version | Security Changes |
|---------|------------------|
| 5.0.8 | XSS protection via DOMPurify, secure cookies |
| 5.0.9 | ClickBank integration (had signature bug) |
| 6.0.1 | CSRF protection, fixed sha256 bug, idempotency, input validation |
| 6.0.2 | Repository cleanup, naming consistency |
| 6.0.3 | Admin file manager security, settings sanitization, storage migration |
