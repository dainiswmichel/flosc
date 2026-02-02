# FLOSC v9.0.3 - Critical Chat Responsiveness Corrections

**Release Date:** 2026-01-17
**Status:** TESTING REQUIRED

---

## What Was Broken in v9.0.2

Based on console log analysis from live site:

1. **Chat had 2 old messages when loading** - welcome message skipped because chat wasn't empty
2. **`first_show_session` missing from context** - all welcome message conditions failed (returned `false`)
3. **Button clicks may not work** - needs testing

---

## What v9.0.3 Changes

### Change #1: Added `first_show_session` to Context

**Result:** Welcome message condition `is_visitor && first_show_session` now evaluates correctly

### Change #2: Don't Restore Old Visitor Messages on First Session

**Result:**
- First-time visitors get empty chat → welcome message shows
- Returning visitors get previous messages restored

---

## Expected Console Output for v9.0.3

When you load the page **fresh** (first session):
- Log shows `first_show_session: true`
- Log shows `Chat is empty - showing welcome message`
- Welcome message appears immediately
- 4 suggested reply buttons appear below

---

## Testing Checklist

### Test 1: Fresh Visitor (Incognito Window)
1. Open incognito window
2. Go to /app/
3. Open console (F12)
4. Check: Welcome message shows, buttons appear
5. Click button: Message sends, response appears

### Test 2: Force Fresh Session
1. Console: `localStorage.clear(); location.reload();`
2. Should behave like fresh visitor

---

**v9.0.3 addresses the specific issues from your v9.0.2 console log.**
