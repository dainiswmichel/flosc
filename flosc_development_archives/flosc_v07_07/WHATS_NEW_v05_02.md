# What's New in FLOSC v05_02

**Release Date:** January 10, 2026
**Version:** 5.0.2

## Overview

This release restructures the admin menu for better usability, improves IntroPanel aesthetics, consolidates settings, and adds IVR framework documentation. Major focus on professional UI/UX and logical menu organization.

---

## Major Changes

### 1. Admin Menu Restructuring

**What Changed:** Created a logical menu structure with shortcuts to Settings tabs, matching left-to-right tab order with top-to-bottom menu order.

**New Menu Structure:**
```
FLOSC
├── 1. Product (shortcut to Settings > Product tab)
├── 2. IVR Messages (comprehensive phase-aware configuration)
├── 3. AI Configuration (provider setup, API keys, system prompts)
├── 4. Quiz (shortcut to Settings > Quiz tab)
├── 5. Email (shortcut to Settings > Email tab)
├── 6. AI Knowledge (markdown knowledge base files)
├── 7. Offers (product offers configuration)
├── 8. Payments (payment provider setup)
└── 9. Lessons (shortcut to Settings > Lessons tab)
```

**Why:** Users requested all configuration options accessible from the left menu, not buried in tabs. Menu order now matches tab order for consistency.

**Technical Details:**
- Added redirect functions for tab shortcuts (Product, Quiz, Email, Lessons)
- Menu items use proper slugs (flosc-product, flosc-quiz, flosc-email, flosc-lessons)
- Redirects use `wp_redirect(admin_url('admin.php?page=flosc-settings&tab=...'))` pattern
- Settings tabs remain functional for users who prefer tabbed interface

**Files Modified:**
- `flosc.php` - Updated `add_admin_menu()`, added redirect functions

---

### 2. Settings Tab Reorganization

**What Changed:** Renamed and reordered tabs to match menu structure, merged STT into Quiz tab.

**Previous Tab Order:**
- Product, Messages, Lessons, AI Provider, Speech-to-Text, Quiz, Email

**New Tab Order:**
- Product, IVR Messages, AI Configuration, Quiz, Email, Lessons

**Changes Made:**
1. **Renamed "Messages" → "IVR Messages"**
   - Added IVR framework explanation at top of tab
   - Clarified that this controls Welcome message and IntroCard responses

2. **Renamed "AI Provider" → "AI Configuration"**
   - Matches dedicated AI Config page naming
   - Consolidates terminology

3. **Merged "Speech-to-Text" into "Quiz" tab**
   - STT is only used for audio-based quizzes
   - Logical grouping: Quiz type → Quiz content → STT provider
   - Added section divider and clear heading

4. **Removed STT as standalone tab**
   - Now appears as "Speech-to-Text Configuration" section within Quiz tab

**Files Modified:**
- `templates/admin/settings.php` - Tab structure, merged STT content

---

### 3. IVR Framework Documentation

**What Added:** Clear explanation of IVR (Interactive Voice Response) framework at top of IVR Messages tab.

**New Help Text:**
> **IVR (Interactive Voice Response)** is our framework for delivering contextual messages based on user behavior, FLOSC phase, quiz scores, and conditions. Configure all chatbot responses here.

**Why:** Users needed to understand what "IVR" means and why it's used throughout the plugin. Framework explanation provides context for message configuration.

**Where:** Settings > IVR Messages tab header

**Files Modified:**
- `templates/admin/settings.php` - Added IVR explanation

---

### 4. IntroPanel Visual Improvements

**What Changed:** Enhanced IntroPanel and prompt card styling for professional appearance.

**IntroPanel Container:**
- Added light gray background (#fafafa)
- Added subtle 1px border (#e5e5e5)
- Added 8px border-radius (slightly rounded corners)
- Added soft box-shadow for depth
- Increased padding (32px vertical, 24px horizontal)

**Close Button:**
- Repositioned from outside panel to inside top-right corner
- Changed from `top: -8px, right: -8px` to `top: 12px, right: 12px`
- Reduced size from 32px to 28px for better proportions
- Lighter shadow to match panel aesthetic

**Prompt Cards:**
- Reduced border-radius from 12px to 8px (matches panel)
- Consistent 1px border color (#e5e5e5)
- Added subtle box-shadow for depth
- Enhanced hover state remains unchanged

**Why:** Previous flat design lacked visual hierarchy. New styling creates professional card-like appearance with clear boundaries and depth.

**Files Modified:**
- `assets/css/flosc-app.css` - IntroPanel and prompt card styles

---

## Testing Checklist

Before deploying v05_02:

**Menu Navigation:**
- [ ] All 9 menu items appear in correct order
- [ ] Product menu item redirects to Settings > Product tab
- [ ] Quiz menu item redirects to Settings > Quiz tab
- [ ] Email menu item redirects to Settings > Email tab
- [ ] Lessons menu item redirects to Settings > Lessons tab
- [ ] AI Configuration, AI Knowledge, Offers, Payments load their dedicated pages
- [ ] IVR Messages loads comprehensive configuration page

**Settings Tabs:**
- [ ] 6 tabs appear in order: Product, IVR Messages, AI Configuration, Quiz, Email, Lessons
- [ ] IVR Messages tab shows IVR framework explanation
- [ ] Quiz tab includes STT Configuration section at bottom
- [ ] All settings save correctly
- [ ] No 404 errors when navigating

**IntroPanel:**
- [ ] IntroPanel displays with light background and border
- [ ] Panel has slightly rounded corners (8px)
- [ ] Close button positioned in top-right corner inside panel
- [ ] Prompt cards have matching rounded corners
- [ ] Cards remain clickable (no overlap)
- [ ] Visual hierarchy is clear and professional

**Backwards Compatibility:**
- [ ] Existing settings preserved (no data loss)
- [ ] Old tab URLs still work (Settings > Messages redirects to IVR Messages)
- [ ] API integrations unchanged

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- All existing settings and configurations preserved
- Old tab URLs (`tab=messages`, `tab=stt`) redirect to new tabs
- Menu structure additive (doesn't remove functionality)
- Settings values unchanged

---

## Upgrade Notes

**From v05_01:**
- Direct upgrade, no data migration needed
- Menu structure automatically updated
- Test menu navigation after upgrading

**Recommended Actions:**
1. Navigate through new menu structure to familiarize
2. Verify all settings tabs accessible via menu and tabs
3. Check IntroPanel appearance on frontend
4. Clear browser cache if styling doesn't update

---

## Known Issues & Future Work

**IVR Messages Tab vs. Menu Item:**
- IVR Messages tab contains simple Welcome/IntroCard messages
- IVR Messages menu item contains comprehensive phase-aware configuration
- These will be merged in future release for unified interface

**Remaining Tasks for Future Releases:**
1. Make triggered message conditions configurable (message_count, session_seconds, score_range, inactivity_seconds)
2. Add score-based OTO configuration
3. Remove hardcoded `showUpgradeOffer()` function, use IVR configuration
4. Add condition builder UI to IVR Messages page

---

## Version History

- **v5.0.2** (Jan 10, 2026) - Menu restructuring, IVR documentation, IntroPanel improvements
- **v5.0.1** (Jan 9, 2026) - IntroPanel positioning, InfoCard clicks, phase reference corrections
- **v4.0.9** (Jan 9, 2026) - FLOSC phase correction, smart connection testing, UI terminology
- **v4.0.8** (Jan 9, 2026) - IntroPanel prompt cards configuration, persistence improvements
- **v4.0.7** (Jan 9, 2026) - Admin menu adjustments, IVR integration
- **v4.0.6** (Jan 9, 2026) - AI Connection Test [DEPRECATED]
- **v4.0.5** (Jan 9, 2026) - AI Orientation Files Manager
- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch

---

## Contributors

- Core Development: Claude Sonnet 4.5 + Dainis Michel
- Testing & QA: Dainis Michel

---

## Support

For issues or questions, refer to plugin documentation or contact support.
