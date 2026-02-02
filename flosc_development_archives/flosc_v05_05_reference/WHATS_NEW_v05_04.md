# What's New in FLOSC v05_04

**Release Date:** January 10, 2026
**Version:** 5.0.4

## Overview

IntroPanel improvements - cards now properly centered and functional with fallback support.

---

## IntroPanel Card Centering Fix

### What Changed

**v05_03 Issue:** IntroPanel cards appeared left-aligned instead of centered.

**v05_04 Update:** Cards now properly centered with `margin: 0 auto` applied to `.suggested-prompts`.

**CSS Change:**
```css
.suggested-prompts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    max-width: 500px;
    width: 100%;
    margin: 0 auto;  /* Added for proper centering */
}
```

---

## IntroPanel Card Click Functionality

### What Changed

**v05_03 Issue:** IntroPanel cards (Get started, How does it work?, What will I learn?) did not respond to clicks if IVR configuration was missing or failed to load.

**v05_04 Update:** Added fallback system for prompt card actions.

### Technical Implementation

**Fallback Logic (flosc-app.js):**
- Primary: Uses IVR configuration from WordPress settings
- Fallback: Uses WordPress message options (flosc_get_started_message, etc.)
- Final fallback: Uses hardcoded sensible defaults

**Code Pattern:**
```javascript
// Fallback to default messages if no IVR config
if (!cardConfig) {
    const fallbacks = {
        'get-started': {
            user_message: 'Get started',
            assistant_response: this.config.messages?.getStarted || "Welcome! I'm here to help you...",
            enabled: true,
            send_to_ai: false
        },
        // ... other actions
    };
    cardConfig = fallbacks[action];
}
```

**Benefits:**
- Cards work even if IVR configuration fails to load
- Uses configured messages when available
- Graceful degradation to sensible defaults
- Console warnings help debug configuration issues

---

## Files Modified

**Version Bump:**
- `flosc.php` - Version 5.0.3 → 5.0.4

**CSS Changes:**
- `assets/css/flosc-app.css` - Added `margin: 0 auto` to `.suggested-prompts`

**JavaScript Changes:**
- `assets/js/flosc-app.js` - Added fallback logic to `handlePromptCardAction()` method

---

## Testing Checklist

Before deploying v05_04:

**Visual:**
- [ ] IntroPanel cards appear centered on page
- [ ] Cards remain centered on mobile devices
- [ ] No layout shifts when cards are displayed

**Functionality:**
- [ ] "Get started" card responds to clicks
- [ ] "Start free quiz" card responds to clicks (opens recording modal)
- [ ] "How does it work?" card responds to clicks
- [ ] "What will I learn?" card responds to clicks
- [ ] Messages appear in chat after clicking cards
- [ ] Fallback messages display if IVR config missing

**Console Checks:**
- [ ] Check browser console for warnings
- [ ] Verify "Using fallback message" appears if IVR config not loaded
- [ ] Confirm no JavaScript errors

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- All existing functionality preserved
- IVR configuration still used when available
- Fallback only activates when config unavailable
- No database changes
- Settings values unchanged

---

## Upgrade Notes

**From v05_03:**
- Direct upgrade, no data migration needed
- IntroPanel cards will be centered immediately
- Cards will work with or without IVR configuration
- No configuration changes required

**Recommended Actions:**
1. Clear browser cache if cards don't appear centered
2. Test all four IntroPanel cards
3. Check browser console for any warnings
4. Verify IVR configuration is loading (check console)

---

## Version History

- **v5.0.4** (Jan 10, 2026) - IntroPanel card centering and fallback functionality
- **v5.0.3** (Jan 10, 2026) - Tab/menu order match (9 tabs), greeting update
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
