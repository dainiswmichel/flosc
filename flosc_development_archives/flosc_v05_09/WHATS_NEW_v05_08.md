# FLOSC v05_08 - Security & Bug Fixes

**Release Date:** 2026-01-11

## Security Fixes

### 🔴 CRITICAL: XSS via Markdown Rendering
**Problem:** `marked.parse()` output was inserted directly into innerHTML without sanitization. Malicious AI responses or echoed user input could execute arbitrary JavaScript.

**Fix:** 
- Added DOMPurify library for HTML sanitization
- New `sanitizeHtml()` method with fallback if DOMPurify unavailable
- All markdown output now sanitized before DOM insertion

**Files Changed:**
- `templates/flosc-app.php` - Added DOMPurify CDN
- `assets/js/flosc-app.js` - Added sanitizeHtml(), applied to all message rendering

### 🟡 Cookie Security Flags Missing
**Problem:** Cookies (`flosc_prelogin_score`, `flosc_referrer`) lacked security flags.

**Fix:** Added secure cookie options:
- `httponly: true` - Prevents JavaScript access
- `secure: is_ssl()` - HTTPS-only when site uses SSL
- `samesite: 'Lax'` - CSRF protection

## Bug Fixes

### 🔴 User Meta Key Mismatches
**Problem:** Some code read meta keys without underscore prefix (`flosc_*`) while data was stored with prefix (`_flosc_*`). This broke:
- Phase detection
- Funnel completion tracking
- Offer shown flag
- Quiz score in AI context

**Fixed Keys:**
- `flosc_last_quiz_score` → `_flosc_last_quiz_score`
- `flosc_free_lesson_delivered` → `_flosc_free_lesson_delivered`
- `flosc_funnel_completed` → `_flosc_funnel_completed`
- `flosc_offer_shown` → `_flosc_offer_shown`

### 🟡 Session ID Collision
**Problem:** `count($sessions) + 1` for new session ID could collide if sessions were deleted.

**Fix:** Now uses `max(existing_ids) + 1` to guarantee unique IDs.

### 🟡 Lesson Query OrderBy Syntax
**Problem:** `'orderby' => 'menu_order date'` is invalid WP_Query syntax.

**Fix:** 
```php
'orderby' => [
    'menu_order' => 'ASC',
    'date' => 'ASC',
]
```

## Files Changed

| File | Changes |
|------|---------|
| `flosc.php` | Meta key fixes, cookie security, version bump |
| `templates/flosc-app.php` | Added DOMPurify CDN |
| `assets/js/flosc-app.js` | Added sanitizeHtml(), XSS protection |
| `includes/class-session-manager.php` | Session ID collision fix |
| `includes/class-lesson-manager.php` | WP_Query orderby syntax fix |

## Verified Non-Issues

The following reported issues were **already correct** in v05.07:
- ✅ REST endpoint names match between JS and PHP
- ✅ OpenAI system prompt is properly included in requests
- ✅ Stripe webhook signature verification exists

## Upgrade Notes

No database changes required. All fixes are backward-compatible.

**Recommendation:** Clear browser cache after update to ensure new JavaScript is loaded.
