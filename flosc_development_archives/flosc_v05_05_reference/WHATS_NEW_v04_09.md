# What's New in FLOSC v04_09

**Release Date:** January 9, 2026
**Version:** 4.0.9

## Overview

This release corrects the FLOSC phase structure, implements intelligent AI connection testing with helpful error messages, and renames "AI Orientation" to "AI Knowledge" for clarity.

---

## Major Changes

### 1. FLOSC Phase Structure Correction (5 Phases Only)

**What Changed:** Removed the erroneous 6th phase "Login Prompt" and consolidated it into the Login phase.

**Why:** FLOSC is a 5-letter acronym representing 5 phases only:
- **F** = Freeline
- **L** = Login
- **O** = Offer
- **S** = Sale
- **C** = Content

The Login phase now properly handles two sub-states:
1. Post-quiz visitors (not logged in) - encourage account creation
2. Logged-in users - deliver free lesson and present offer

**Technical Details:**
- Merged `login_prompt` configuration into `login` phase in IVR Manager
- Updated `ivr-admin.js` phases array from 6 to 5 phases
- Removed "Login Prompt Phase" section from IVR admin UI
- Updated phase descriptions to clarify Login handles both sub-states
- Consolidated messaging: post-quiz and logged-in messages now coexist in single Login phase

**Files Modified:**
- `includes/class-ivr-manager.php` - Merged login_prompt into login phase config
- `assets/js/ivr-admin.js` - Removed login_prompt from phases array
- `templates/admin/ivr-settings.php` - Removed Login Prompt section, updated descriptions

---

### 2. Smart AI Connection Testing

**What Changed:** AI connection test now provides intelligent, actionable error messages with step-by-step guidance.

**Problem Solved:** Previously, the test would show "Connection successful!" even when API keys were missing or invalid because failed requests fell back to IVR responses. Users thought they were connected to OpenAI when they were actually using scripted IVR responses.

**New Behavior:**
- Test mode bypasses IVR fallback
- Returns specific WP_Error messages for each failure type
- Provides contextual help based on error type:
  - **No API key:** Step-by-step instructions to get and configure key
  - **Invalid API key:** Instructions to create new key and verify format
  - **Quota exceeded:** Links to billing page and quota management
  - **Connection error:** Internet troubleshooting and service status checks

**Error Message Examples:**

**OpenAI - No API Key:**
```
No OpenAI API key configured.

📝 Next steps:
1. Go to https://platform.openai.com/api-keys
2. Sign up or log in to your OpenAI account
3. Click 'Create new secret key'
4. Copy the key (starts with sk-proj-...)
5. Paste it in the 'OpenAI API Key' field above
6. Click 'Save AI Configuration'
7. Try testing again!
```

**Anthropic - Invalid Key:**
```
Anthropic API Error: authentication_error

📝 Next steps:
1. Your API key appears to be invalid
2. Go to https://console.anthropic.com/settings/keys
3. Create a new API key
4. Replace the old key with the new one above
5. Make sure you copied the entire key (starts with sk-ant-...)
```

**OpenAI - Quota Exceeded:**
```
OpenAI API Error: insufficient_quota

📝 Next steps:
1. You've exceeded your OpenAI usage quota
2. Go to https://platform.openai.com/settings/organization/billing
3. Add a payment method or increase your quota
4. Wait for quota to reset or upgrade your plan
```

**Technical Details:**
- Added `$test_mode` parameter to `get_response()` method
- When `$test_mode = true`, provider methods return `WP_Error` instead of IVR fallback
- Each provider (OpenAI, Anthropic, xAI) returns context-specific error messages
- Test handler passes `$test_mode = true` and checks for `WP_Error` responses
- Frontend displays error messages with proper formatting (red background, clear typography)

**Files Modified:**
- `includes/class-ai-provider-factory.php` - Added test mode to all provider methods
- `flosc.php` - Updated `handle_test_ai()` to use test mode and handle WP_Error
- `templates/admin/ai-config.php` - Enhanced error display with better styling

---

### 3. UI Terminology Update: "AI Orientation" → "AI Knowledge"

**What Changed:** Renamed "AI Orientation Files" to "AI Knowledge Files" throughout the user interface.

**Why:** "Knowledge Files" is clearer and more intuitive than "Orientation Files" for users uploading documentation and catalogs.

**Changes:**
- Menu item: "AI Orientation" → "AI Knowledge"
- Page title: "AI Orientation Files" → "AI Knowledge Files"
- URL slug: `flosc-ai-orientation` → `flosc-ai-knowledge`
- Empty state message: "No orientation files yet" → "No knowledge files yet"
- Code comments updated to reference "knowledge files"

**Note:** Internal code (method names, directory names) intentionally left unchanged to avoid breaking changes. Only user-facing text was updated.

**Files Modified:**
- `flosc.php` - Menu registration
- `templates/admin/ai-orientation.php` - Page title and descriptions
- `includes/class-ai-provider-factory.php` - Code comments

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- Existing IVR configurations with `login_prompt` phase will be preserved
- `login_prompt` messages automatically merge into `login` phase
- Internal `ai_orientation_files/` directory unchanged
- Method names unchanged for compatibility

---

## Testing Checklist

Before deploying v04_09:

**Phase Structure:**
- [ ] IVR admin shows 5 phases (not 6)
- [ ] Login phase description mentions both sub-states
- [ ] Login phase messages save correctly
- [ ] No references to "Login Prompt" in UI

**AI Connection Testing:**
- [ ] Test with NO API key configured → shows helpful error
- [ ] Test with INVALID API key → shows key validation error
- [ ] Test with VALID API key → shows success
- [ ] IVR mode test → always succeeds
- [ ] Error messages include clickable links
- [ ] Error messages display with proper formatting

**UI Terminology:**
- [ ] Menu shows "AI Knowledge" (not "AI Orientation")
- [ ] Page title shows "AI Knowledge Files"
- [ ] URL is `/wp-admin/admin.php?page=flosc-ai-knowledge`
- [ ] No "orientation" references in user-facing text

---

## Upgrade Notes

**From v04_08:**
- Direct upgrade, no data migration needed
- Existing `login_prompt` configurations preserved
- Test AI connection after upgrading to verify keys

**Recommended Actions:**
1. Visit AI Config page and test your AI connection
2. Review IVR Messages page - verify Login phase has correct messages
3. If using AI Knowledge Files, verify page loads at new URL

---

## Version History

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
