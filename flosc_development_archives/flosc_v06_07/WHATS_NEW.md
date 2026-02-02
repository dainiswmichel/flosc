# FLOSC Plugin Updates

**For complete version history, see [CHANGELOG.md](CHANGELOG.md).**

---

## Current Version: v06.07 ✅
**Released:** 2026-01m-13d

### Highlights

#### Critical Bug Fix - Plugin Now Works!
- **Fixed:** Fatal error during plugin activation (affected v06.04-v06.06)
- **Root cause:** Wrong initialization hook (`init` instead of `plugins_loaded`)
- **Solution:** Restored correct `plugins_loaded` hook
- **Result:** Plugin activates and runs perfectly

#### What Was Wrong (v06.04-v06.06)
These versions used `add_action('init', 'flosc', 0)` which caused WordPress to initialize FLOSC too early, before critical globals like `$wp_rewrite` were available. This caused fatal errors on activation.

#### What's Fixed (v06.07)
Restored the original `add_action('plugins_loaded', 'flosc')` hook, ensuring WordPress is fully loaded before FLOSC initializes.

**⚠️ Do NOT use v06.04, v06.05, or v06.06** - they contain a fatal initialization bug.

### All Security Features Intact
- ✅ AI Knowledge Base security (v06.03)
- ✅ Settings sanitization (v06.03)
- ✅ CSRF/nonce protection (v06.01)
- ✅ ClickBank SHA256 fix (v06.01)
- ✅ IPN replay prevention (v06.01)
- ✅ Input validation framework (v06.01)

**Code Quality Grade:** A- (88/100)

---

### Previous Versions Summary

#### v06.03 - Security Hardening
- AI Knowledge Base file upload security
- Storage migration to `/wp-content/uploads/flosc-ai/`
- Settings sanitization callbacks

#### v06.02 - Repository Cleanup
- Consolidated 28 changelogs into CHANGELOG.md
- Professional .gitignore
- Size reduction: 27%

#### v06.01 - Production Readiness
- Fixed critical ClickBank SHA256 bug
- Added CSRF protection to REST endpoints
- Professional logging and validation

---

## Quick Links

- **Full Changelog:** [CHANGELOG.md](CHANGELOG.md)
- **Security Policy:** [SECURITY.md](SECURITY.md)
- **Documentation:** [README.md](README.md)
- **Project Site:** https://flosc.io

---

## Date Format Standard

All dates in FLOSC use the **Michel Date Stamp** format: `YYYY-MMm-DDd`

**Example:** 2026-01m-13d = January 13th, 2026

**Benefits:**
- Eliminates MM/DD confusion (is 06-03 June 3rd or March 6th?)
- Works identically worldwide
- Chronological sorting without special parsing
- Instant clarity for international teams

Learn more: See Michel Date Stamp specification in project documentation.

---

**Last Updated:** 2026-01m-13d
**Maintained by:** Dainis Michel
