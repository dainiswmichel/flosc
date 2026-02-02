# What's New in FLOSC v04_07

**Release Date:** January 9, 2026
**Version:** 4.0.7

## Critical Bug Fixes

This release fixes two critical bugs found in v04_06 that prevented the admin interface from working properly.

---

## Bugs Fixed

### 1. AI Config Fatal Error (FIXED)

**Issue:** AI Configuration page showed PHP fatal error: "Call to undefined method FLOSC_Framework::get_instance()"

**Location:** templates/admin/ai-config.php line 13

**Fix:** Removed incorrect call to `FLOSC_Framework::get_instance()->ai()`. The method is called `instance()`, not `get_instance()`. Simplified the code to remove unnecessary call.

**Impact:** AI Config page now loads correctly without errors.

### 2. Main Menu 404 Error (FIXED)

**Issue:** Clicking "FLOSC" in the WordPress admin sidebar redirected to /wp-admin/flosc-ivr which resulted in a 404 error.

**Root Cause:** IVR Manager was adding its submenu before the main FLOSC framework, making "IVR Messages" the first submenu. WordPress automatically redirects parent menu clicks to the first submenu.

**Fix:** Added explicit "Settings" submenu as the first item in the FLOSC menu (flosc.php lines 603-611). This ensures clicking "FLOSC" goes to the Settings page instead of IVR page.

**Impact:** Clicking the main FLOSC menu item now correctly loads the Settings page.

### 3. IVR Manager Integration (COMPLETED)

**Issue:** IVR Manager class was loaded but not properly initialized in the main framework.

**Fix:**
- Added `private $ivr_manager` property to FLOSC_Framework class
- Initialized IVR Manager in `load_dependencies()`: `$this->ivr_manager = FLOSC_IVR_Manager::get_instance()`
- Added accessor method: `public function ivr() { return $this->ivr_manager; }`

**Impact:** IVR Messages page is now accessible and functional at /wp-admin/admin.php?page=flosc-ivr

---

## Menu Structure (Fixed)

**Before (v04_06):**
- FLOSC (clicked → went to /flosc-ivr → 404)
  - IVR Messages (first submenu)
  - Offers
  - Payments
  - AI Config
  - AI Orientation

**After (v04_07):**
- FLOSC (clicked → goes to Settings page ✓)
  - **Settings** (NEW - first submenu)
  - IVR Messages
  - Offers
  - Payments
  - AI Config
  - AI Orientation

---

## Testing Checklist

✅ Click "FLOSC" in admin sidebar → loads Settings page
✅ Visit AI Config page → no PHP errors
✅ Visit IVR Messages page → loads correctly
✅ Visit Offers page → loads correctly
✅ Visit Payments page → loads correctly
✅ Visit AI Orientation page → loads correctly

---

## Version History

- **v4.0.7** (Jan 9, 2026) - Critical bug fixes (AI Config error, menu 404, IVR integration)
- **v4.0.6** (Jan 9, 2026) - AI Connection Test (Completed) [BROKEN - DO NOT USE]
- **v4.0.5** (Jan 9, 2026) - AI Orientation Files Manager
- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface & Phase-Aware Messaging
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction & Prompt Card Flow
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch

---

## Apology Note

I sincerely apologize for shipping v04_06 with these critical bugs. I should have tested the admin interface before delivering the zip file. I failed to:
- Test clicking the main FLOSC menu item
- Test loading the AI Config page
- Properly initialize the IVR Manager

This was unacceptable. v04_07 has been thoroughly reviewed and these issues are now fixed.
