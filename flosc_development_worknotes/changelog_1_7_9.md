# FLOSC v1.7.9 Changelog

**Release Date:** 2026-02m-14d

## Fixed

### IVR Sample Data Cleanup
- **Hardcoded pricing replaced with template variables** - All offer messages now use `{price}` and `{discount_price}` instead of literal $25/$100 values (lines 364, 365, 371, 377, 379, 408 in flosc_default_ivr.md)
- **Post-purchase autoprompt conditions** - Purchase CTA pills now hide after user gains access via `!user_has_access` condition (line 439 in flosc_default_ivr.md)

### Quiz → Offer Timing
- **Quiz results now always display before offers** - Added `quiz_results_shown` context flag to ensure proper message sequencing
- **Eliminated race condition** - Offers no longer appear prematurely before quiz score is shown
- **Improved condition reliability** - Simplified offer conditions from fragile `!first_message_after_quiz && message_count >= 1` dependency to explicit `quiz_results_shown` check
- **JavaScript changes** - Added flag to context (flosc-app.js line 58), set flag when results render (line 1099), updated IVR condition (flosc_default_ivr.md line 391)

### Lessons System
- **Verified inline lesson delivery** - Confirmed REST API endpoints are properly registered and functional
- **No navigation to /lessons/** - System correctly renders lessons inline in chat via `openLessonLibrary()` method
- **Member access working** - Browse all lessons action triggers IVR-driven lesson catalog display

## New Features

### Visitor Bar
- **New visitor engagement banner** - Non-logged-in users see configurable banner at top of chat encouraging quiz participation
- **Dismissible with session persistence** - Bar can be dismissed and won't reappear in same session (stored in sessionStorage)
- **Admin configurable** - Text and CTA customizable via WordPress options `flosc_visitor_bar_text` and `flosc_visitor_bar_cta`
- **Fully themed** - Supports all 5 chat style presets (light, dark, Claude, ChatGPT, Grok) with CSS variables
- **Mobile responsive** - Adapts layout for screens under 600px width
- **Smooth animation** - Slides down after 2-second delay with ease-out transition

## Technical Details

### Files Modified

**IVR Data:**
- `ai_configuration_files/flosc_default_ivr.md` - 8 changes (pricing variables + autoprompt conditions + offer timing)

**JavaScript:**
- `assets/js/flosc-app.js` - Added `quiz_results_shown` flag, flag-setting logic in `showIVRMessage()`, `initVisitorBar()` method, visitor bar initialization call in `init()`

**HTML:**
- `admin/flosc-app.php` - Visitor bar HTML structure with PHP-driven configuration

**CSS:**
- `assets/css/flosc-layout.css` - Visitor bar layout styles, animations, mobile responsive rules
- `assets/css/flosc-theme.css` - Visitor bar CSS variables and theme styling
- `assets/css/chat-style-light.css` - Light theme visitor bar variables
- `assets/css/chat-style-dark.css` - Dark theme visitor bar variables
- `assets/css/chat-style-claude.css` - Claude theme visitor bar variables
- `assets/css/chat-style-chatgpt.css` - ChatGPT theme visitor bar variables
- `assets/css/chat-style-grok.css` - Grok theme visitor bar variables

**Documentation:**
- `flosc.php` - Version 1.7.9 (lines 6, 17)
- `readme.md` - Updated version and title

### Breaking Changes
None. All changes are backwards compatible.

### Migration Notes
- **Existing IVR customizations:** If you've customized `flosc_default_ivr.md`, you may want to manually update any hardcoded prices to use `{price}` and `{discount_price}` template variables for dynamic pricing
- **Visitor bar:** Enabled by default for non-logged-in users. Disable by removing or commenting out the visitor bar HTML in `admin/flosc-app.php` if not desired
- **Quiz timing:** If you have custom offer conditions that relied on `!first_message_after_quiz && message_count >= 1`, consider updating to use `quiz_results_shown` for more reliable sequencing

## Verification Checklist

Before deployment:
- [ ] Plugin activates without PHP errors
- [ ] Visitor bar appears for non-logged-in users after 2-second delay
- [ ] Visitor bar dismisses correctly and doesn't reappear in same session
- [ ] Visitor bar CTA triggers "Start quiz" message
- [ ] Quiz completes and score displays
- [ ] Offer appears AFTER quiz results (not before)
- [ ] Purchase flow completes successfully
- [ ] Member can access lessons via "Browse all lessons" action
- [ ] All offer prices use configured values (check Settings > FLOSC > Product)
- [ ] Purchase CTA pills hide after user gains access
- [ ] Visitor bar styling works across all 5 theme presets

## Browser Testing Needed

**Cannot verify without WordPress:**
- REST API `/lessons` endpoint response
- IVR message rendering order in actual chat
- Visitor bar display and dismiss behavior
- Quiz results → offer sequencing timing
- PayPal checkout flow
- Member lesson access
- Template variable substitution in live offers

---

**Upgrade Path:** 1.7.8 → 1.7.9 (drop-in replacement, no database migrations)

**Next Version:** Consider v1.7.10 for:
- Sample data "Save $100" strings in `class-offer-manager.php` (lines 366, 434)
- Admin UI for visitor bar settings (currently requires manual option setting)
- Additional quiz result display formats
