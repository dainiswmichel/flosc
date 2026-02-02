# FLOSC v4.0.1 - Task List for Approval

**Date:** 2026-01m-09d
**Built on:** v3.0.9 architecture + Michel Coding Standards
**Purpose:** Production-ready version with standards applied

---

## Version Strategy

**Why v4.0.1 (not v3.0.10)?**
- Major version bump signals: "Professional standards applied"
- Clean break from v3.x experimental iterations
- v4.x series = production-ready, standards-compliant
- Marketing: "FLOSC 4.0 - Professional Grade"

---

## Task List

### 1. Activation Hook (From v3.0.9) ✅
**Status:** Already resolved in v3.0.9 code
**Action:** Verify fix is included in v4.0.1 base

**What it does:**
- Moves activation hook to root level (outside class)
- Ensures database defaults set on activation
- Prevents empty `FLOSC_CONFIG.messages` issue

### 2. Terminology Updates (Low Risk)
**Files:** All `WHATS_NEW_*.md` documentation files

**Changes:**
- "fix/fixed/hotfix" → "resolve/resolved/critical update"
- "What's Fixed" → "What's Resolved"
- Exception: Keep CSS `position: fixed`

**Estimated:** ~50-80 replacements across 6 files

### 3. JavaScript Class Rename (Low Risk)
**File:** `assets/js/flosc-app.js` line 6

**Change:**
```javascript
// Before:
class FloscApp {

// After:
class floscApp {
```

**Why:** Follows Michel Coding Standards (lowercase prefix)

**Risk:** Low (class only instantiated in one place)

### 4. Version Numbers and Documentation
**Files:**
- `flosc.php` - Update version to 4.0.1
- Create `WHATS_NEW_v04_01.md`
- Update `README.md` if needed

**Content:**
- Major version: Professional standards applied
- Activation hook resolution from v3.0.9
- Terminology cleanup
- JavaScript naming standard compliance
- AI integration fully functional (document)

### 5. Code Comments Terminology
**Files:** `flosc.php`, `assets/js/flosc-app.js`

**Changes:**
- Line 1263 flosc.php: "Fixed:" → "Resolved:"
- Line 146 flosc-app.js: "fixed to use" → "updated to use"

**Estimated:** 2-3 comment updates

---

## NOT Included in v4.0.1 (Too Risky)

### CSS Class Renaming (Deferred to v4.1.0)
**Reason:** 146 classes across 3 files = high risk
**Why defer:**
- Requires extensive testing
- One missed reference = broken UI
- Better to ship working v4.0.1 first
- Then tackle CSS in focused v4.1.0 release

**Plan for v4.1.0:**
- Dedicated CSS renaming release
- Thorough testing checklist
- Screenshots before/after
- Staging environment testing

---

## Testing Checklist (v4.0.1)

### Pre-deployment Tests
- [ ] PHP syntax check: `php -l flosc.php`
- [ ] Verify activation hook at root level
- [ ] Check all version numbers updated to 4.0.1
- [ ] Verify terminology changes don't break code
- [ ] JavaScript class rename doesn't break instantiation

### Post-deployment Tests (Live Site)
- [ ] Plugin activates without errors
- [ ] Check database: `wp option get flosc_get_started_message`
- [ ] Should return: "FLOSC Default Get started text: Welcome!..."
- [ ] Test prompt cards:
  - [ ] Click "Get started" → User message + Bot response
  - [ ] Click "How does it work?" → User message + Bot response
  - [ ] Click "What will I learn?" → User message + Bot response
- [ ] Test IntroPanel:
  - [ ] X button → "Hide IntroPanel" message + panel hides
  - [ ] Type "show intropanel" → Panel reappears
- [ ] Test IVR commands:
  - [ ] "show profile status" → Status display
  - [ ] "show token count" → Token display
  - [ ] "show quiz score" → Score display
- [ ] Test chat:
  - [ ] Type "hello" → IVR greeting
  - [ ] Type "how does it work" → IVR explanation
  - [ ] Type "price" → IVR pricing

### AI Provider Tests (Optional)
- [ ] Admin → AI Provider tab exists
- [ ] Can select provider (ivr/openai/anthropic/xai)
- [ ] API key fields visible
- [ ] If OpenAI key added → AI responses work
- [ ] If API fails → Falls back to IVR

---

## File Changes Summary

### Modified Files (v4.0.1)
1. `flosc.php`
   - Version 4.0.1 (lines 6, 17)
   - Comment updates (line 1263)
   - Activation hook already at root level

2. `assets/js/flosc-app.js`
   - Class name: `FloscApp` → `floscApp` (line 6)
   - Comment update (line 146)

3. Documentation files:
   - `WHATS_NEW_v03_04.md` - Terminology updates
   - `WHATS_NEW_v03_05.md` - Terminology updates
   - `WHATS_NEW_v03_06.md` - Terminology updates
   - `WHATS_NEW_v03_07.md` - Terminology updates
   - `WHATS_NEW_v03_08.md` - Terminology updates
   - `WHATS_NEW_v03_09.md` - Terminology updates

4. New files:
   - `WHATS_NEW_v04_01.md` - Document v4.0.1 changes

---

## Estimated Impact

**Lines changed:** ~100-150 lines
**Files modified:** 9 files
**Risk level:** LOW (documentation + minor code updates)
**Breaking changes:** None
**Backwards compatible:** Yes (fully compatible with v3.0.9)

---

## v4.0.1 Description

**"Professional Standards Release"**

FLOSC v4.0.1 applies Michel Coding Standards across the codebase, resolves the activation hook issue from v3.0.9, and establishes v4.x as the production-ready series. All code now follows consistent naming conventions, terminology standards, and professional documentation practices.

**Key improvements:**
- Activation hook resolved (database defaults set correctly)
- Terminology cleaned (no "fix" language in docs)
- JavaScript naming standards applied
- AI integration documented and ready
- Foundation for v4.1.0 CSS standards compliance

---

## Approval Questions

1. **Version number:** Approve v4.0.1 (major bump to signal professional standards)?
2. **Scope:** Approve low-risk changes (terminology + JS class name)?
3. **CSS deferral:** Approve deferring CSS renaming to v4.1.0?
4. **Testing:** Who will test on live site after deployment?
5. **Timeline:** Ship v4.0.1 today or wait?

---

## Next Steps After v4.0.1

### v4.1.0 (CSS Standards Compliance)
- Rename all 146 CSS classes with `flosc-` prefix
- Update HTML templates
- Update JavaScript selectors
- Extensive UI testing
- Target: After v4.0.1 proven stable

### v4.2.0 (Feature Enhancements)
- Enhanced IVR responses
- Conversation history persistence
- User context in AI prompts
- Multi-language support foundation

### v4.3.0 (Analytics & Optimization)
- Conversion tracking
- A/B testing framework
- Performance optimization
- Token usage analytics

---

## Deployment Steps

1. Create `flosc_v04_01/` directory
2. Copy from v3.0.9
3. Apply all changes
4. Run syntax checks
5. Create deployment zip
6. Create `WHATS_NEW_v04_01.md`
7. WordPress: Deactivate → Upload → Activate
8. Run testing checklist
9. Monitor for 24 hours
10. If stable → proceed to v4.1.0 planning

---

**Awaiting approval to code v4.0.1**
