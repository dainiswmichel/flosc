# FLOSC Version 1.7.8 Changelog
**Date:** 2026-02m-13d
**Previous Version:** 1.7.7
**Status:** Ready for testing

---

## What Changed - Summary

### ✅ Completed Features:
1. **Lesson System Integration** - WordPress posts display inline in chat
2. **Visitor Profile Bar** - Configurable visitor menu in bottom-left sidebar
3. **CSS Architecture Cleanup** - All inline `<style>` blocks moved to external stylesheets
4. **AI Configuration Guide** - Comprehensive documentation for FloscAdmins
5. **Offer Timing Fix** - Offer no longer appears before quiz results

### 🎯 User-Facing Changes:
- Members can browse all lessons via "📚 Browse all lessons" autoprompt
- Lessons render with full WordPress formatting (images, embeds, shortcodes)
- Visitors see profile card with configurable menu items
- Offer card appears AFTER quiz results, not before
- New "AI Guide" tab in FLOSC Settings with templates and best practices

---

## File Changes - Detailed

### 1. NEW FILE: `admin/ai-configuration-guide.php`
**What it is:** Comprehensive guide for configuring AI to deliver any content type
**Where:** FLOSC Settings → AI Guide tab
**Size:** ~680 lines

**Sections:**
- The AI to AGI Bridge concept
- System prompt templates (pronunciation, music, programming, fitness)
- Knowledge base configuration
- Available variables reference
- Phase-specific behavior guide
- Troubleshooting

**User sees:** New tab between "AI" and "Knowledge" with copy/paste templates and documentation

---

### 2. MODIFIED: `admin/flosc-app.php`
**Changes:**
- Lines ~175-201: Added visitor profile card in sidebar (bottom-left)
- Profile card shows configurable icon, text, and dropdown menu
- Visitor menu items controlled by admin settings

**User sees:**
- Non-logged-in visitors: Profile card says "Visitor" with admin-configured text
- Click profile card → dropdown menu with configurable actions
- Logged-in users: Existing profile card unchanged

---

### 3. MODIFIED: `admin/flow-edit.php`
**Changes:**
- Lines ~45-59: Save handler for visitor profile bar settings
- Lines ~268-305: Admin UI to configure visitor bar (icon, text, menu items)

**User sees:**
- In Flow Settings → Identity tab → "Visitor Profile Bar" section
- Can set visitor icon (emoji), text ("Sign in to get started"), and menu items
- Menu items: Sign In, Take Quiz, Learn More (each can be enabled/disabled)

---

### 4. MODIFIED: `admin/settings.php`
**Changes:**
- Line 342: Added 'ai-guide' => 'AI Guide' tab
- Line 783: Include ai-configuration-guide.php
- Line 819: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:**
- New "AI Guide" tab in settings navigation

---

### 5. MODIFIED: `admin/ivr-messages.php`
**Changes:**
- Line 838: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:** No visual change (CSS still loads, just from external file)

---

### 6. MODIFIED: `admin/ivr-settings.php`
**Changes:**
- Line 418: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:** No visual change

---

### 7. MODIFIED: `admin/offers.php`
**Changes:**
- Line 199: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:** No visual change

---

### 8. MODIFIED: `admin/quiz.php`
**Changes:**
- Line 68: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:** No visual change

---

### 9. MODIFIED: `admin/sso.php`
**Changes:**
- Line 313: Removed inline `<style>` block (moved to flosc-admin.css)

**User sees:** No visual change

---

### 10. MODIFIED: `assets/css/flosc-admin.css`
**Changes:**
- Appended ~250 lines of CSS from inline blocks in 6 admin files
- Sections: IVR Settings, Offers, Quiz, SSO, Settings footer, IVR Messages, AI Guide

**User sees:** No visual change (CSS that was inline is now external)

---

### 11. MODIFIED: `assets/css/flosc-layout.css`
**Changes:**
- Lines ~398-415: Added dropdown arrow rotation CSS for profile cards
- Lines ~2410-2543: Removed redundant WordPress content styling (images rule kept)
- Appended ~220 lines: Lesson list and lesson content container styles

**User sees:**
- Lesson list displays as clickable cards with hover effects
- Lesson content renders in chat with WordPress theme styling
- Back to lessons button styled

---

### 12. MODIFIED: `assets/js/flosc-app.js`
**Changes:**
- Lines ~2903-3020: Lesson system implementation
  - `async openLessonLibrary()` - Fetches and displays lesson list
  - `async fetchAllLessons()` - GET /wp-json/flosc/v1/lessons
  - `async fetchLesson(lessonId)` - GET /wp-json/flosc/v1/lessons/{id}
  - `renderLessonList(lessons)` - Displays clickable lesson cards
  - `async viewLesson(lessonId)` - Shows WordPress content with back button

- Lines ~3377-3416: Profile dropdown handlers
  - Click handlers for both profile dropdowns (visitor and member)
  - Visitor menu action handler (sign_in, take_quiz, learn_more)

- Lines ~443-467: Public post referral handler
  - Detects `?from=public-post&post_id=123&slug=lesson-name` in URL
  - Greets visitor with contextual message referencing the post
  - Cleans URL after showing greeting

**User sees:**
- Click "Browse all lessons" → See list of all lessons from WordPress category
- Click lesson → Full WordPress post content displays in chat
- Click "Back to All Lessons" → Returns to lesson list
- Click visitor profile card → Dropdown menu appears

---

### 13. MODIFIED: `flosc.php`
**Changes:**
- Lines ~2113-2116: Registered visitor bar settings
  - `flosc_visitor_bar_text`
  - `flosc_visitor_bar_icon`
  - `flosc_visitor_menu_items`
- Line 555: Auto-protect "Default FLOSC Lessons" category
  - Sets `_flosc_protected = 'yes'` on activation
  - Category hidden from public by default
  - Individual posts can override with `_flosc_public_post = 'yes'`

**User sees:** Visitor bar settings saved in database, lesson category protected

---

### 14. MODIFIED: `ai_configuration_files/flosc_default_ivr.md`
**Changes:**
- Line 391: Fixed offer timing condition
  - Before: `MessageConditions: is_guest && quiz_taken && first_message_after_quiz`
  - After: `MessageConditions: is_guest && quiz_taken && !first_message_after_quiz && message_count >= 1`

**User sees:**
- Quiz results display first
- Offer card appears AFTER results (not simultaneously)

---

## Testing Checklist

### ✅ Can Test in Admin (No Browser Needed):
- [ ] Plugin activates without errors
- [ ] FLOSC Settings loads
- [ ] AI Guide tab exists and displays content
- [ ] Flow Settings → Identity → Visitor Profile Bar section exists

### 🌐 Requires Browser Testing:

**As Visitor:**
- [ ] See visitor profile card in bottom-left
- [ ] Click visitor card → dropdown menu appears
- [ ] Menu items match admin configuration
- [ ] Click "Sign In" → WordPress login page

**As Member:**
- [ ] See "Browse all lessons" autoprompt
- [ ] Click autoprompt → lesson list displays
- [ ] Lesson list shows all posts from configured category
- [ ] Click lesson → WordPress content renders
- [ ] Images/embeds/shortcodes work
- [ ] Click "Back to All Lessons" → returns to list

**Quiz Flow:**
- [ ] Take quiz → see results
- [ ] Results display before offer
- [ ] Offer appears after seeing score

**Admin Styling:**
- [ ] All admin pages render correctly (CSS from external file, not inline)
- [ ] IVR Settings page styled correctly
- [ ] Offers page styled correctly
- [ ] Quiz page styled correctly
- [ ] SSO page styled correctly
- [ ] Settings footer styled correctly
- [ ] IVR Messages page styled correctly

---

## Known Limitations (Not Blocking)

1. **Multipass integration incomplete** - AI doesn't remember previous sessions yet
2. **Dynamic lesson catalog not implemented** - AI doesn't receive WordPress lesson list yet
3. **Testing console not built** - Admins can't simulate user states in admin panel
4. **Template library not created** - Only 4 templates in guide, not selectable from UI

These are documentation/infrastructure features, not blocking launch. IVR works without them.

---

## Architecture Changes

### CSS Rules Enforced:
- **No inline `<style>` blocks** (except flosc-app.php dynamic PHP variables)
- All admin CSS → `assets/css/flosc-admin.css`
- All frontend CSS → `assets/css/flosc-layout.css` or `flosc-theme.css`
- Rule documented in CLAUDE.md and copilot-instructions.md

---

## Installation Verification

### Files That Must Exist:
- ✅ `admin/ai-configuration-guide.php` (new)
- ✅ `assets/css/flosc-admin.css` (modified, +250 lines)
- ✅ `assets/css/flosc-layout.css` (modified, ~220 lines added)
- ✅ `assets/js/flosc-app.js` (modified, ~120 lines added)

### Settings That Must Register:
- ✅ `flosc_visitor_bar_text`
- ✅ `flosc_visitor_bar_icon`
- ✅ `flosc_visitor_menu_items`

### REST Endpoints That Must Work:
- ✅ `GET /wp-json/flosc/v1/lessons` (already existed, verified working)
- ✅ `GET /wp-json/flosc/v1/lessons/{id}` (already existed, verified working)

---

## What Can Break (Risk Assessment)

### Low Risk (Additive Changes):
- ✅ Lesson system (new JS methods, doesn't affect existing code)
- ✅ Visitor bar (new HTML/CSS, only shows for non-logged-in users)
- ✅ AI Guide tab (new file, doesn't modify existing tabs)

### Medium Risk (Modifications):
- ⚠️ CSS cleanup - If flosc-admin.css doesn't load, admin pages will lose styling
- ⚠️ Offer timing fix - Condition change could affect when offer appears

### Zero Risk (Documentation):
- ✅ AI configuration guide (read-only content)

---

## Version Bump Checklist

Before zipping:
- [ ] Change `Version: 1.7.7` to `Version: 1.7.8` in flosc.php line 5
- [ ] Change `define('FLOSC_VERSION', '1.7.7');` to `define('FLOSC_VERSION', '1.7.8');` in flosc.php line 16
- [ ] Test plugin activation in clean WordPress install
- [ ] Verify no PHP errors in error log
- [ ] Create flosc_1_7_8.zip

---

## Summary for User

**What you'll see differently:**
1. New "AI Guide" tab with documentation and templates
2. Visitors see profile card with configurable menu
3. Members can browse WordPress lessons in chat
4. Offer appears after quiz results, not before
5. All admin pages load CSS from external file (cleaner code)

**What won't change:**
- IVR flow still works the same
- Quiz still works the same
- Payment flow still works the same
- Existing features unchanged

**What to test first:**
1. Install plugin → verify no errors
2. Open FLOSC Settings → verify AI Guide tab exists
3. Open site as visitor → verify visitor profile card appears
4. Log in as member → click "Browse all lessons" → verify lessons display
5. Take quiz → verify offer appears AFTER results

**Ready to zip:** Yes, pending version bump.
