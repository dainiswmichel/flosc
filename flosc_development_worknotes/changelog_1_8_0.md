# FLOSC v1.8.0 Changelog

**Release Date:** 2026-02m-14d

## Fixed

### Bug #4: Hamburger Menu Invisible
- **Malformed color fixed** — `color: #667` (invalid hex) replaced with `var(--flosc-text-secondary, #6b7280)` in `flosc-layout.css` line 2405
- **Mobile menu button color added** — `.mobile-menu-btn` now has `color: var(--flosc-text-primary, #374151)` so SVG `stroke="currentColor"` renders visibly
- **Hamburger hidden on desktop** — `.mobile-menu-btn` is `display: none` on desktop, `display: flex` on mobile only (was always visible, conflicting with sidebar)

### Bug #5: Duplicate FLOSC Branding
- **Mobile logo hidden on desktop** — `.logo-mobile` is `display: none` on desktop, `display: flex` on mobile (was always `display: flex`, showing product name in both sidebar and header)

### Bug #6: Free Lesson Routing
- **Category ID vs slug mismatch fixed** — `class-free-lesson-manager.php` `find_lesson_post()` now uses `cat` (numeric ID) when `flosc_lessons_category` is a number, and `category_name` (slug) when it's a string. Previously, numeric IDs were passed as slugs which never matched.

### Bug #2: Visitor Menu "Take Quiz" (from v1.7.9 fixes)
- **Keyword matching in `findIVRResponse()`** — Now checks `Keywords:` field in IVR messages, not just exact `UserInput:` match
- **`sendMessage()` accepts string parameter** — Programmatic sends (e.g., from visitor menu) pass message directly instead of requiring `chatInput.value` to be set first

### Bug #1: Unwanted Popup Banner (from v1.7.9 fixes)
- **Removed** — Auto-popup visitor engagement banner HTML removed from `flosc-app.php`. JS init commented out. Feature retained as configurable option via admin UI (default: OFF).

### Bug #7: Lessons Empty State (from v1.7.9 fixes)
- **Diagnostic logging added** — `class-lesson-manager.php` `get_all_lessons()` now logs to `wp-content/debug.log`:
  - Whether `flosc_lessons_category` option is empty
  - Category value and whether it's treated as ID or slug
  - WP_Query result count and actual SQL query
  - If empty: whether the category exists in the database at all, its name, and post count

### Bug #3: Guest Profile Bar Layout (from v1.7.9 fixes)
- **Sidebar overflow fixed** — `.flosc-sidebar` changed from `overflow: hidden` to `overflow-x: hidden; overflow-y: auto` so profile card at bottom is not clipped

## New Features

### Admin: UI & Navigation Settings Page
- **New admin page** — WordPress Admin → FLOSC → UI & Navigation
- **Visitor Profile Menu** — Configure labels and enable/disable Sign Up, Log In, Take Quiz items
- **Login Destination** — Choose where users redirect after login: FLOSC Chat, WooCommerce My Account, User Profile, or Home Page
- **Visitor Engagement Bar** — Toggle on/off, customize text and icon (default: OFF)
- **Quiz Trigger Reference** — Built-in documentation showing how user phrases map to IVR quiz actions

## Files Changed

| File | Change |
|---|---|
| `flosc.php` | Version bump (1.7.9 → 1.8.0), admin page registration, settings registration |
| `readme.md` | Updated version and title |
| `admin/flosc-app.php` | Popup banner HTML removed, replaced with comment |
| `admin/ui-navigation.php` | **NEW** — Admin settings page for UI & navigation |
| `assets/js/flosc-app.js` | Visitor bar init disabled, `sendMessage()` param fix, `findIVRResponse()` keyword matching, quiz trigger fix |
| `assets/css/flosc-layout.css` | Sidebar overflow fix, hamburger color fix, mobile-menu-btn desktop hide, logo-mobile desktop hide |
| `includes/class-lesson-manager.php` | Diagnostic `error_log()` in `get_all_lessons()` |
| `includes/class-free-lesson-manager.php` | Category ID vs slug handling in `find_lesson_post()` |

**Total: 7 files modified, 1 new file created**

## What Cannot Be Verified Without Browser Testing

1. Whether hamburger icon is now visible on all theme presets (color depends on CSS variable inheritance)
2. Whether free lesson routing returns correct lesson when `flosc_lessons_category` is a numeric ID
3. Whether `findIVRResponse()` keyword matching triggers the quiz modal correctly via `Action: open_quiz`
4. What `wp-content/debug.log` reveals about the "Browse all lessons" empty state
5. Whether the admin UI & Navigation page renders correctly and saves settings

## Breaking Changes

None. All changes are backward compatible. The admin settings page uses existing WordPress options API with sensible defaults.

## Upgrade Path

1.7.9 → 1.8.0 — Drop-in replacement. No database migrations.
