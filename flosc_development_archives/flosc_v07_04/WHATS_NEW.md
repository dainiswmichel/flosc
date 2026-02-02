# What's New in FLOSC v07_04

**Release Date:** 2026-01-13
**Status:** Stable Release
**Based on:** v06.01 with security fixes

---

## Overview

v07_04 is a **stable release** built from v06.01 (v06.02 naming). Includes all security improvements and functionality developed through the v06 series.

---

## What's Included

### Security Improvements ✅
- CSRF/nonce protection on all REST endpoints
- User ID-based rate limiting (can't be spoofed via IP)
- Security event logging
- ClickBank SHA256 signature fix
- IPN replay attack prevention
- Cryptographically random session IDs

### Core Features ✅
- Complete FLOSC funnel system
- 5 quiz types
- Multi-provider AI (OpenAI, Anthropic, xAI, IVR)
- Multi-provider STT (AssemblyAI, Deepgram, Whisper)
- Payment providers (Stripe, Tokens, Affiliate, ClickBank)
- IVR phase-aware messaging
- Professional logging infrastructure
- Input validation framework

### Code Quality ✅
- Logger class for structured logging
- Validator class for input validation
- Proper asset versioning (cache busting)
- Clean naming (ai_configuration_files)

---

## Installation

1. Backup your site
2. Deactivate old FLOSC version
3. Delete old plugin completely
4. Upload flosc_v07_04.zip
5. Activate
6. **Clear ALL browser data** (cookies, cache, localStorage)
7. Test /app/ URL

### CRITICAL: Clear Browser Data

After installation, you MUST clear ALL browser data:
- Cookies
- Cache
- LocalStorage
- SessionStorage

**How to clear everything:**
1. Cmd+Shift+Delete (Mac) or Ctrl+Shift+Delete (Windows)
2. Select "All time"
3. Check ALL boxes
4. Click "Clear data"
5. Close ALL browser tabs
6. Quit browser completely
7. Reopen browser

---

## Testing

1. Open browser in **private/incognito mode**
2. Visit /app/
3. Verify chat interface loads
4. Test quiz submission
5. Test login/registration
6. Test offer display
7. Test payment (if configured)

---

## Technical Documentation

See `technical_guide_for_developers.md` for complete technical reference including:
- Common problems & solutions
- Architecture overview
- API reference
- Debugging guide

---

**This version includes all security fixes and is ready for production testing.**
