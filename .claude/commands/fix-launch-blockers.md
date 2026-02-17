Audit the FLOSC plugin at mvp_sprint/flosc_1_7_8/ and fix the issues listed below. Work in flosc_1_7_8/ only.

## CRITICAL: Read Before Writing

Before making ANY edit:
1. Read the full CLAUDE.md at the project root
2. Read the target file's surrounding 50+ lines of context
3. Trace the user journey the fix affects
4. State what will change in the browser

After ALL edits:
1. List what you changed and why
2. List what you CANNOT verify without a browser
3. Do NOT claim "fixed" — say "edit applied, needs browser testing"

## Issues to Fix (Priority Order)

### 1. Offer appears before quiz results
The offer card renders before the quiz score is displayed. Trace the message rendering flow in flosc-app.js — find where offer messages and quiz result messages are queued/rendered and ensure quiz_result renders BEFORE any offer. This is likely an IVR message ordering or timing issue in the `processIvrMessages()` or `renderMessage()` flow.

### 2. Lessons page 404
When a paid user clicks "Browse all lessons", it navigates to `/lessons/` which doesn't exist. The lesson system needs to either:
- Render lessons inline in the chat from the configured WordPress category
- OR create a proper WordPress page/archive for the lessons category
Investigate how `flosc_lessons_category` setting is used and what happens when the user triggers "Browse all lessons" in flosc-app.js.

### 3. Visitor bar missing
A visitor bar was scoped but never implemented. This should be a banner/bar visible to non-logged-in users encouraging them to take the quiz or sign up. Check if any visitor bar HTML/CSS exists in the codebase. If not, implement it as a simple, dismissible bar at the top of the chat area that:
- Shows only when `user_logged_in` is false
- Has configurable text (IVR setting, not hardcoded)
- Can be dismissed (stores dismissal in sessionStorage)

### 4. Broken sample IVR data
Find the IVR message that contains "Join 1,000+ students who have already transformed their skills with !" — the template variable for course name is empty, and "1,000+ students" is fabricated. Fix the sample data to use honest placeholder text. Also audit ALL sample IVR messages for:
- Missing template variables
- Fabricated statistics
- Hardcoded values that should be settings
Make sample data clearly marked as customizable placeholders.

### 5. Post-purchase autoprompt conditions
The sample IVR data should have visibility conditions on purchase-related autoprompt pills. Pills like "Get full access now" should have condition `user_has_access != true` so they hide after purchase. This is a DATA fix in the default IVR messages, not a code fix.

## DO NOT

- Do not touch more than these 5 issues
- Do not version-bump (user will confirm when to bump)
- Do not fabricate social proof in any text
- Do not hardcode settings that should be admin-configurable
- Do not use raw console.log (use this.log())
