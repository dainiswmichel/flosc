# What's New in FLOSC v05_01

**Release Date:** January 9, 2026
**Version:** 5.0.1

## Overview

This release resolves IntroPanel positioning issues and InfoCard interaction problems that emerged in v04_09, and corrects phase references after the FLOSC phase structure consolidation.

---

## Bug Fixes

### 1. IntroPanel Positioning Correction

**Problem:** IntroPanel was "scooting left" instead of staying centered in the chat interface.

**Solution:** Enhanced CSS centering with additional flex properties:
- Added `align-self: center` to ensure proper alignment within flex parent
- Added `justify-content: center` for internal content centering
- Maintained `margin: 0 auto` for horizontal centering

**Technical Details:**
```css
.intro-panel {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 24px 20px;
    max-width: 800px;
    width: 100%;
    margin: 0 auto;
    align-self: center;        /* NEW */
    justify-content: center;   /* NEW */
}
```

**Files Modified:**
- `assets/css/flosc-app.css` - Enhanced IntroPanel centering

---

### 2. InfoCard Click Blocking Resolution

**Problem:** Close button was overlapping InfoCards, preventing user clicks and interactions.

**Solution:** Repositioned close button outside the IntroPanel boundary:
- Changed `top: 8px` → `top: -8px`
- Changed `right: 8px` → `right: -8px`
- Close button now floats outside the panel, clearing InfoCards for clicks

**Technical Details:**
```css
.intro-panel-close {
    position: absolute;
    top: -8px;       /* Was: 8px */
    right: -8px;     /* Was: 8px */
    /* ... rest of styles ... */
    z-index: 10;
}
```

**Files Modified:**
- `assets/css/flosc-app.css` - Repositioned close button

---

### 3. Phase Reference Correction

**Problem:** Code still referenced removed `login_prompt` phase (consolidated in v04_09).

**Solution:** Updated all phase references from `login_prompt` to `login`:
- `determineFLOSCPhase()` method: Quiz-taken visitors now correctly transition to `login` phase
- `submitRecording()` method: Post-quiz phase transition corrected to `login`

**Technical Details:**
- Line 1375 in `flosc-app.js`: `return quizTaken ? 'login' : 'freeline';`
- Line 1082 in `flosc-app.js`: `this.transitionToPhase('login');`

**Why:** The v04_09 release consolidated 6 phases → 5 phases (F-L-O-S-C only). The `login_prompt` phase was merged into `login` with sub-states, but some JavaScript code still referenced the old phase name.

**Files Modified:**
- `assets/js/flosc-app.js` - Updated phase transition calls

---

## Testing Checklist

Before deploying v05_01:

**IntroPanel:**
- [ ] IntroPanel displays centered (not left-aligned)
- [ ] IntroPanel persists when clicking InfoCards
- [ ] IntroPanel hides only on X button click or 300-second timeout
- [ ] "Show IntroPanel" and "Hide IntroPanel" commands work

**InfoCards:**
- [ ] All four InfoCards are clickable
- [ ] Clicking "Get started" shows configured response
- [ ] Clicking "Start free quiz" opens recording modal
- [ ] Clicking "How does it work?" shows configured response
- [ ] Clicking "What will I learn?" shows configured response
- [ ] Close button does NOT block InfoCard clicks

**Phase Transitions:**
- [ ] Visitor takes quiz → transitions to `login` phase
- [ ] No console errors about undefined `login_prompt` phase
- [ ] IVR messages display correctly for `login` phase

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- All v04_09 configurations preserved
- Login phase continues to handle both sub-states correctly
- No database migrations required

---

## Upgrade Notes

**From v04_09:**
- Direct upgrade, no special actions needed
- IntroPanel positioning will auto-correct on first load
- InfoCards will immediately become clickable

**Recommended Actions:**
1. Clear browser cache to load updated CSS/JS
2. Test IntroPanel centering and InfoCard clicks
3. Verify phase transitions work correctly

---

## Version History

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
