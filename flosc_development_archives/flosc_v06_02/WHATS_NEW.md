# FLOSC Plugin Updates

**For complete version history, see [CHANGELOG.md](CHANGELOG.md).**

---

## Current Version: v06.02
**Released:** 2026-01m-13d

### Highlights

#### Refactoring & Cleanup
- **AI System Renamed:** "AI Orientation" → "AI Knowledge Base" (better reflects purpose)
- **Repository Cleanup:** Consolidated 28 separate changelog files into single CHANGELOG.md
- **Size Reduction:** Plugin reduced from 800KB to ~580KB (-27%)
- **Documentation:** Added comprehensive README to ai_configuration_files directory
- **Version Control:** Created .gitignore, removed system artifacts

#### Code Quality
- Professional inline documentation added
- Consistent naming throughout codebase
- All dates use Michel Date Stamp format (YYYY-MMm-DDd)
- Production-grade comments and structure

### Previous Version: v06.01
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
