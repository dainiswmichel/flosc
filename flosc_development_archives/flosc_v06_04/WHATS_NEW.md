# FLOSC Plugin Updates

**For complete version history, see [CHANGELOG.md](CHANGELOG.md).**

---

## Current Version: v06.04
**Released:** 2026-01m-13d

### Highlights

#### Clean Package Release
- **Standard folder name:** Plugin folder is now `flosc/` (WordPress standard)
- **Verified clean:** All PHP files syntax-checked, no encoding issues
- **Known conflicts:** May conflict with `read-more-login` plugin (see below)

#### Known Plugin Conflicts
The following plugins have been reported to cause issues when used with FLOSC:
- **read-more-login** - Hooks into `login_url` filter too early, causes fatal error during WordPress recovery mode

If you experience errors, try deactivating other plugins to isolate the conflict.

### Previous Version: v06.03
**Released:** 2026-01m-13d

#### Critical Security Fixes
- Fixed ClickBank SHA256 signature verification (was using invalid PHP function)
- Added CSRF/nonce protection to all REST endpoints
- Implemented IPN replay attack prevention
- Changed to cryptographically random session IDs

#### New Features
- Professional logging infrastructure (FLOSC_Logger)
- Input validation framework (FLOSC_Validator)
- Enhanced rate limiting (user-based + IP-based)

**Code Quality Grade:** 75% (B-) → 88% (A-)

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
