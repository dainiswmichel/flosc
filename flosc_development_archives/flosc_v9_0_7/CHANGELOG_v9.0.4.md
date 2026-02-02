# FLOSC v9.0.4 - Chat Responsiveness Fixes

**Release Date:** 2025-01-17  
**Based On:** v9.0.2  
**Status:** Ready for Testing

---

## What Was Fixed

### Issue: Chat Completely Unresponsive
- Welcome message not showing
- Buttons appearing but not working when clicked
- Typed messages not getting responses
- All IVR conditions failing

### Root Cause
`first_show_session` variable missing from context object, causing all welcome message conditions to evaluate to `FALSE`.

---

## Changes Made

### 1. Added `first_show_session` to Context ✅
**File:** `assets/js/flosc-app.js` - `buildIVRContext()` method

- Checks localStorage for session key
- Sets `first_show_session: true` if no session exists
- Marks session as started in localStorage
- Now welcome messages will show for fresh visitors

### 2. Fixed Button Click Handler ✅
**File:** `assets/js/flosc-app.js` - `handleSuggestedReply()` method

**Before:** Directly called `showIVRMessage()` - bypassed normal flow
**After:** Inserts text into input field → calls `sendMessage()`

This means buttons and typed input now go through the SAME code path.

### 3. Fixed IVR Message Matching ✅
**File:** `assets/js/flosc-app.js` - `findIVRResponse()` method

**Before:** Only matched messages with `type === 'suggested_reply'`
**After:** Matches ANY message that has `user_input` defined

Now all input-output pairs work regardless of message type.

### 4. Simplified `sendMessage()` Flow ✅
**File:** `assets/js/flosc-app.js` - `sendMessage()` method

**New Flow:**
1. User types or clicks button
2. Text inserted into chat input
3. `sendMessage()` called
4. Find IVR match
5. Check conditions
6. Show response (or fallback to API)

**Key improvement:** Condition check happens AFTER finding match, ensures messages only show when appropriate.

### 5. Fixed Restart Button ✅
**File:** `assets/js/flosc-app.js` - `restartChat()` method

**Before:** Used `this.getSessionKey()` (wrong pattern)
**After:** Uses `'flosc_session_' + (this.user?.id || 'visitor')` (matches buildIVRContext)

Now restart button properly clears session, making user a "fresh visitor" again.

### 6. Updated Version Numbers ✅
- `flosc.php` → 9.0.4
- `assets/js/flosc-app.js` → 9.0.4

---

## Expected Behavior After Update

### Fresh Visitor
1. Loads page for first time
2. `first_show_session = TRUE`
3. Welcome message shows automatically
4. Suggested reply buttons appear
5. Clicking button inserts text → sends message → shows response
6. Typing and pressing Enter → finds IVR match or calls API → shows response

### Return Visitor
1. Has localStorage from previous visit
2. `first_show_session = FALSE`
3. Old messages restored
4. Can continue conversation
5. Press "Restart Chat" → becomes fresh visitor

### Restart Button
1. Clears chat messages
2. Removes session key from localStorage
3. Clears visitor messages (if visitor)
4. Rebuilds context (`first_show_session` becomes TRUE again)
5. Restarts IVR (welcome message shows)

---

## Testing Checklist

- [ ] Fresh visitor sees welcome message
- [ ] Suggested reply buttons appear
- [ ] Clicking button shows user message + assistant response
- [ ] Typing message and pressing Enter works
- [ ] IVR input-output pairs match correctly
- [ ] Messages only show when conditions pass
- [ ] Restart button clears session and shows welcome again
- [ ] Return visitor sees old messages
- [ ] No JavaScript errors in console

---

## Installation

1. Deactivate FLOSC plugin
2. Delete old FLOSC folder
3. Upload `flosc_v9_0_4.zip`
4. Extract in `/wp-content/plugins/`
5. Activate plugin
6. Test in incognito window
7. Check browser console for any errors

---

## Rollback Plan

If v9.0.4 doesn't work:
1. Deactivate plugin
2. Delete v9.0.4 folder
3. Reinstall v9.0.2 (last known version on site)
4. Report errors with console log

---

## Technical Notes

### Session Key Pattern
```javascript
const sessionKey = 'flosc_session_' + (this.user?.id || 'visitor');
```
This pattern is now used consistently in:
- `buildIVRContext()` - sets first_show_session
- `restartChat()` - clears session

### Message Matching
Any message in `ivr.md` with a `UserInput:` field will now be matched, regardless of `MessageType`.

### Condition Evaluation
Conditions are checked AFTER finding a match:
```javascript
if (ivrMatch && this.evaluateCondition(ivrMatch.conditions)) {
    // Show response
}
```

---

**Questions? Check console log for detailed execution flow.**
