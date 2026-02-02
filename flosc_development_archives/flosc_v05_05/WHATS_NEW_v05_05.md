# What's New in FLOSC v05_05

**Release Date:** January 10, 2026
**Version:** 5.0.5

## Overview

Repairs to menu/tab structure and UX improvements - restored missing menu items, fixed tab navigation, added thinking delay, removed user message background.

---

## REPAIRS (What Was Broken in v05_04)

### Restored 4 Missing Menu Items

**Issue:** v05_04 removed Product, Quiz, Email, and Lessons from admin menu - only 6 items instead of 9.

**Fixed:** All 9 menu items now present in correct order:
1. Settings
2. Product
3. IVR Messages
4. AI Configuration
5. Quiz
6. Email
7. AI Knowledge
8. Offers
9. Payments
10. Lessons

**Implementation:**
- Added back `redirect_to_product_tab()`, `redirect_to_quiz_tab()`, `redirect_to_email_tab()`, `redirect_to_lessons_tab()` functions
- Menu items link to Settings page with appropriate tab parameters
- All menu items functional

### Fixed AI Knowledge, Offers, Payments Tabs

**Issue:** Clicking these tabs showed stupid info cards saying "go to the real page" instead of actually going to the pages.

**Fixed:** Tabs now navigate directly to dedicated configuration pages:
- AI Knowledge tab → `?page=flosc-ai-knowledge`
- Offers tab → `?page=flosc-offers`
- Payments tab → `?page=flosc-payments`

**What Changed:**
- Modified tab hrefs in `templates/admin/settings.php`
- Removed info card sections for these 3 tabs
- Tabs now work identically to menu items (left-to-right = top-to-bottom)

---

## UPDATES (New Features)

### Slight Thinking Delay for Chat Responses

**What Changed:** Added 600ms delay before assistant messages appear to create more natural conversation flow.

**Implementation (flosc-app.js):**
```javascript
// Show typing indicator
this.typingIndicator?.classList.add('show');

setTimeout(() => {
    this.typingIndicator?.classList.remove('show');
    this.addMessage('assistant', cardConfig.assistant_response);
    this.scrollToBottom();
}, 600); // Slight thinking delay
```

**Benefits:**
- More human-like conversation feel
- User sees thinking indicator before response
- Prevents instant responses that feel robotic

### Removed Grey Background from User Messages

**Issue:** User message bubbles had unnecessary grey background.

**Fixed:** Removed `background: var(--flosc-bg-hover);` from `.message.user .message-text` in flosc-app.css.

**Result:**
- Cleaner, lighter visual appearance
- User messages now transparent/white
- Better visual hierarchy between user and assistant messages

---

## Files Modified

**PHP:**
- `flosc.php` - Version 5.0.4 → 5.0.5, restored 4 menu items, added redirect functions

**Templates:**
- `templates/admin/settings.php` - Fixed tab navigation for AI Knowledge, Offers, Payments; removed info cards

**JavaScript:**
- `assets/js/flosc-app.js` - Added 600ms thinking delay to `handlePromptCardAction()`

**CSS:**
- `assets/css/flosc-app.css` - Removed grey background from `.message.user .message-text`

---

## Testing Checklist

**Menu Navigation:**
- [ ] All 10 menu items visible (Settings + 9 items)
- [ ] Product menu item → Settings page with Product tab
- [ ] Quiz menu item → Settings page with Quiz tab
- [ ] Email menu item → Settings page with Email tab
- [ ] Lessons menu item → Settings page with Lessons tab
- [ ] AI Knowledge menu item → AI Knowledge page
- [ ] Offers menu item → Offers page
- [ ] Payments menu item → Payments page

**Tab Navigation:**
- [ ] All 9 tabs visible on Settings page
- [ ] Product, IVR Messages, AI Configuration, Quiz, Email, Lessons tabs switch content on Settings page
- [ ] AI Knowledge tab → Navigates to AI Knowledge page
- [ ] Offers tab → Navigates to Offers page
- [ ] Payments tab → Navigates to Payments page

**UX Features:**
- [ ] IntroPanel cards show thinking indicator for ~600ms before response
- [ ] User message bubbles have no grey background
- [ ] Assistant messages appear after slight delay
- [ ] Typing indicator shows during thinking time

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- All existing functionality preserved
- Menu and tabs now work correctly
- No database changes
- Settings values unchanged

---

## Upgrade Notes

**From v05_04:**
- Direct upgrade, no data migration needed
- Menu will show all 9 items immediately
- Tabs will navigate correctly
- No configuration changes required

**Recommended Actions:**
1. Test all menu items navigate correctly
2. Test all tabs (both inline and navigation)
3. Test IntroPanel card responses with thinking delay
4. Verify user messages no longer have grey background

---

## Version History

- **v5.0.5** (Jan 10, 2026) - Menu/tab repairs, thinking delay, user message background removal
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
