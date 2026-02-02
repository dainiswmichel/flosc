# FLOSC v05_07 - Phase Logic Fixes

**Release Date:** 2026-01-11

## Bug Fixes

### 🔴 CRITICAL: Missing User Flags for Phase Logic
**Problem:** JavaScript `determineFLOSCPhase()` expected user properties (`offerShown`, `purchased`, `onboarded`, `quizScore`) that PHP wasn't providing.

**Fix:** Added missing flags to FLOSC_USER data in `flosc.php`:
- `quizScore` - User's last quiz score
- `offerShown` - Whether offer has been shown
- `purchased` - Whether user has paid access
- `onboarded` - Whether user completed post-purchase onboarding

### 🟡 Offer Shown Flag Never Persisted
**Problem:** `offerShown` was checked in phase logic but never saved when offer was displayed.

**Fix:** 
- Added `/flosc/v1/mark-offer-shown` REST endpoint
- `showUpgradeOffer()` now calls endpoint and updates local state

### 🟡 Hardcoded Marketing Messages
**Problem:** `showUpgradeOffer()` and paywall message used hardcoded copy instead of IVR config.

**Fix:** Both now check for IVR config first, fall back to defaults if not configured.

### 🟡 IntroPanel vs IVR Initial Message Conflict
**Problem:** Both IntroPanel and `startIVR()` could show welcome messages on page load.

**Fix:** `startIVR()` now checks if IntroPanel is visible and skips initial message if so.

## New REST Endpoints

- `POST /flosc/v1/mark-offer-shown` - Mark that user has seen the offer
- `POST /flosc/v1/mark-onboarded` - Mark that user completed onboarding

## Technical Details

### Files Changed
- `flosc.php` - Added user flags, new endpoints, new handlers
- `assets/js/flosc-app.js` - Fixed showUpgradeOffer, startIVR, paywall message

### User Meta Keys Added
- `_flosc_offer_shown` - Boolean, set when offer is displayed
- `_flosc_onboarded` - Boolean, set after post-purchase onboarding

## Upgrade Notes

No database changes required. New user meta keys are created on-demand.
