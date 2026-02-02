# FLOSC v9.1.1 - Security & Performance Fixes

**Release Date:** 2026-01-19  
**Status:** Production Ready

## Critical Fixes Applied

### 1. Security - Condition Parser Vulnerability Fixed 🔒

**Issue:** The IVR condition parser accepted any variable name, allowing potential prototype pollution and XSS attacks.

**Fix:**
- Added whitelist of allowed condition variables in constructor
- Added security checks in `parseCondition()` method to block unauthorized variables
- Invalid variable access attempts now logged with console warnings

**Impact:** Prevents malicious ivr.md conditions from accessing JavaScript prototype chain or executing arbitrary code.

### 2. Performance - Memory Leaks Fixed 💧

**Issue:** Multiple memory leaks caused chat performance to degrade over time:
- Inactivity timer never cleared, causing multiple timers to stack
- Event listeners never removed, accumulating in memory with each interaction

**Fix:**
- Added `clearInactivityTimer()` method to properly cleanup timers
- Modified `startInactivityTimer()` to clear existing timer first
- Added timer cleanup to `restartChat()` method
- Added `cleanupSuggestedReplies()` method to remove event listeners
- Modified `renderSuggestedReplies()` to track and cleanup listeners
- Added `activeEventListeners` Map to track all listeners for cleanup

**Impact:** Chat remains performant during extended sessions. No memory accumulation from repeated interactions.

### 3. Code Quality - Duplicate Code Removed 🔄

**Issue:** Activation logic existed in two places:
- Line 157: `FLOSC_Framework::activate()` (class method)
- Line 2285: `flosc_activate()` (global function)

**Fix:**
- Removed duplicate class method
- Global function remains as single source of truth
- Added documentation comment explaining the change

**Impact:** Eliminates maintenance confusion and ensures activation logic stays consistent.

## Files Modified

### JavaScript
- `assets/js/flosc-app.js`
  - Added `allowedConditionVars` Set to constructor
  - Added `activeEventListeners` Map to constructor  
  - Modified `parseCondition()` with security checks
  - Added `clearInactivityTimer()` method
  - Modified `startInactivityTimer()` to use cleanup
  - Modified `restartChat()` to clear timer
  - Added `cleanupSuggestedReplies()` method
  - Modified `renderSuggestedReplies()` to track listeners

### PHP
- `flosc.php`
  - Removed duplicate `activate()` method from class (lines 157-258)
  - Updated version to 9.1.1

## Testing Recommendations

### Security Test
```javascript
// In browser console at /app/
window.FLOSC.evaluateCondition('logged_in')  // Should work
window.FLOSC.evaluateCondition('__proto__.polluted')  // Should fail with warning
```

### Memory Leak Test
1. Open /app/ in Chrome DevTools
2. Performance tab → Take heap snapshot
3. Click suggested replies 50+ times
4. Take another heap snapshot
5. Compare - detached DOM nodes should be minimal (<10)

### Functionality Test
- [ ] Chat loads without errors
- [ ] Welcome message displays
- [ ] Suggested replies work
- [ ] Send message works
- [ ] Chat restart works cleanly
- [ ] No console errors after 10 minutes of use

## Upgrade Notes

**From v9.1.0 to v9.1.1:**
- Direct upgrade, no database changes
- Clear browser cache to ensure new JavaScript loads
- No admin configuration changes needed

**Breaking Changes:** None

## Version History

- v9.1.1 (2026-01-19) - Security & performance fixes
- v9.1.0 (2026-01-19) - Modular admin architecture
- v9.0.9 (2026-01-19) - IVR message management UI
- v9.0.8 - Chat functionality improvements

## Credits

**Architecture:** Dainis Michel  
**Code Review & Security Fixes:** Claude (Anthropic)  
**Testing:** Ready for production validation
