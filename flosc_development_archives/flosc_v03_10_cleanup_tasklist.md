# FLOSC v3.0.10 Cleanup Task List

**Date:** 2026-01m-09d
**Purpose:** Code hygiene - Remove "fix" terminology and ensure all identifiers use flosc_ prefix

---

## Task Category 1: Remove "Fix" Terminology

### 1.1 Documentation Files (WHATS_NEW_*.md)

**Files to Update:**
- `WHATS_NEW_v03_04.md`
- `WHATS_NEW_v03_05.md`
- `WHATS_NEW_v03_06.md`
- `WHATS_NEW_v03_07.md`
- `WHATS_NEW_v03_08.md`
- `WHATS_NEW_v03_09.md`

**Changes:**
- Replace "Fix" → "Resolution"
- Replace "Fixed" → "Resolved"
- Replace "Hotfix" → "Critical Update"
- Replace "What's Fixed" → "What's Resolved"
- Replace "fixes" → "resolves" or "addresses"
- Exception: Keep CSS `position: fixed` (technical term)

**Examples:**
- "v3.0.9 - Activation Hook Fix" → "v3.0.9 - Activation Hook Resolution"
- "v3.0.8 (hotfix)" → "v3.0.8 (critical update)"
- "Fixed Broken Prompt Cards" → "Resolved Broken Prompt Cards"
- "The Fix in v3.0.9" → "The Resolution in v3.0.9"
- "What This Fixes" → "What This Resolves"
- "Architectural Fixes" → "Architectural Improvements"

### 1.2 PHP Code Comments

**File:** `flosc.php` (line 1263)
```php
// BEFORE:
* Plugin activation (v3.0.9 - Fixed: moved outside class so hook fires correctly)

// AFTER:
* Plugin activation (v3.0.9 - Resolved: moved outside class so hook fires correctly)
```

**File:** `flosc.php` (line 175)
```php
// Keep as-is (not about fixing, about forcing defaults)
// Force critical "out of box" defaults (v3.0.8 - added FLOSC Default prefix)
```

### 1.3 JavaScript Comments

**File:** `assets/js/flosc-app.js` (line 146)
```javascript
// BEFORE:
// Prompt cards (v3.0.4 - fixed to use actions instead of echoing)

// AFTER:
// Prompt cards (v3.0.4 - updated to use actions instead of echoing)
```

---

## Task Category 2: CSS Class Naming Convention

### 2.1 Problem Statement
Currently only 5 out of 151 CSS classes use the `flosc-` prefix:
- `.flosc-app`
- `.flosc-sidebar`
- `.flosc-main`
- `.flosc-header`
- `.flosc-chat-container` (not in initial grep, need to verify)

All other classes are unprefixed and could conflict with other plugins/themes.

### 2.2 CSS Classes to Rename (146 classes)

**Naming Convention:**
- All CSS classes must start with `flosc-`
- Use lowercase with hyphens (kebab-case)
- Example: `.upgrade-btn` → `.flosc-upgrade-btn`

**Files to Update:**
1. `assets/css/flosc-app.css` - Update all class definitions
2. `templates/flosc-app.php` - Update all HTML class references
3. `assets/js/flosc-app.js` - Update any querySelector/classList references

**Classes to Rename (alphabetical):**

```
.auth-buttons → .flosc-auth-buttons
.btn-large → .flosc-btn-large
.btn-primary → .flosc-btn-primary
.btn-secondary → .flosc-btn-secondary
.chat-container → .flosc-chat-container
.composer → .flosc-composer
.composer-inner → .flosc-composer-inner
.copy-btn → .flosc-copy-btn
.dropdown-email → .flosc-dropdown-email
.dropdown-icon → .flosc-dropdown-icon
.dropdown-name → .flosc-dropdown-name
.greeting → .flosc-greeting
.greeting-title → .flosc-greeting-title
.header-left → .flosc-header-left
.header-right → .flosc-header-right
.intro-panel → .flosc-intro-panel
.intro-panel-close → .flosc-intro-panel-close
.landing-state → .flosc-landing-state
.landing-subtitle → .flosc-landing-subtitle
.landing-title → .flosc-landing-title
.login-gate-body → .flosc-login-gate-body
.login-gate-buttons → .flosc-login-gate-buttons
.logo → .flosc-logo
.logo-emoji → .flosc-logo-emoji
.logo-img → .flosc-logo-img
.logo-mobile → .flosc-logo-mobile
.message → .flosc-message
.message-avatar → .flosc-message-avatar
.message-content → .flosc-message-content
.message-text → .flosc-message-text
.messages → .flosc-messages
.mic-btn → .flosc-mic-btn
.mobile-menu-btn → .flosc-mobile-menu-btn
.modal → .flosc-modal
.modal-body → .flosc-modal-body
.modal-close → .flosc-modal-close
.modal-header → .flosc-modal-header
.modal-overlay → .flosc-modal-overlay
.new-chat-btn → .flosc-new-chat-btn
.pay-btn → .flosc-pay-btn
.pay-btn-spinner → .flosc-pay-btn-spinner
.pay-btn-text → .flosc-pay-btn-text
.payment-footer → .flosc-payment-footer
.payment-form → .flosc-payment-form
.payment-modal → .flosc-payment-modal
.payment-price → .flosc-payment-price
.payment-product → .flosc-payment-product
.payment-summary → .flosc-payment-summary
.playback-actions → .flosc-playback-actions
.product-desc → .flosc-product-desc
.product-icon → .flosc-product-icon
.product-name → .flosc-product-name
.profile-avatar → .flosc-profile-avatar
.profile-badge → .flosc-profile-badge
.profile-button → .flosc-profile-button
.profile-dropdown → .flosc-profile-dropdown
.profile-dropdown-header → .flosc-profile-dropdown-header
.profile-dropdown-item → .flosc-profile-dropdown-item
.profile-info → .flosc-profile-info
.profile-name → .flosc-profile-name
.prompt-card → .flosc-prompt-card
.prompt-icon → .flosc-prompt-icon
.prompt-text → .flosc-prompt-text
.record-btn → .flosc-record-btn
.recording-controls → .flosc-recording-controls
.recording-error → .flosc-recording-error
.recording-instructions → .flosc-recording-instructions
.recording-modal → .flosc-recording-modal
.recording-playback → .flosc-recording-playback
.recording-timer → .flosc-recording-timer
.send-btn → .flosc-send-btn
.session-group → .flosc-session-group
.session-group-title → .flosc-session-group-title
.session-history → .flosc-session-history
.session-item → .flosc-session-item
.share-btn → .flosc-share-btn
.share-link-container → .flosc-share-link-container
.share-link-input → .flosc-share-link-input
.share-text → .flosc-share-text
.sidebar-close → .flosc-sidebar-close
.sidebar-header → .flosc-sidebar-header
.sidebar-overlay → .flosc-sidebar-overlay
.stop-btn → .flosc-stop-btn
.stripe-card-element → .flosc-stripe-card-element
.stripe-errors → .flosc-stripe-errors
.suggested-prompts → .flosc-suggested-prompts
.typing-avatar → .flosc-typing-avatar
.typing-dots → .flosc-typing-dots
.typing-indicator → .flosc-typing-indicator
.upgrade-banner → .flosc-upgrade-banner
.upgrade-banner-btn → .flosc-upgrade-banner-btn
.upgrade-banner-close → .flosc-upgrade-banner-close
.upgrade-banner-content → .flosc-upgrade-banner-content
.upgrade-btn → .flosc-upgrade-btn
.upgrade-container → .flosc-upgrade-container
.user-profile-card → .flosc-user-profile-card
.waveform-container → .flosc-waveform-container
```

**Note:** This is a comprehensive change affecting 146 CSS classes across multiple files.

---

## Task Category 3: Standardize Underscore (_) vs Hyphen (-)

### 3.1 Naming Convention Standards

**FLOSC Ecosystem Naming Rules:**

**PHP (Backend):**
- Functions: `flosc_function_name` (lowercase + underscore)
- Classes: `FLOSC_Class_Name` (UPPERCASE prefix + PascalCase with underscore separators)
- Variables: `$flosc_variable_name` (lowercase + underscore)
- Constants: `FLOSC_CONSTANT_NAME` (all UPPERCASE + underscore)
- Database options: `flosc_option_name` (lowercase + underscore)
- Examples:
  - `flosc_activate()` ✅
  - `FLOSC_Framework` ✅
  - `$flosc_defaults` ✅
  - `FLOSC_VERSION` ✅
  - `flosc_quiz_content_simple_scoring` ✅

**JavaScript (Frontend):**
- Classes: `floscClassName` (lowercase prefix + camelCase) OR `flosc_class_name` (lowercase + underscore)
- Variables/Functions: `floscVariableName` (lowercase prefix + camelCase) OR `flosc_variable_name` (lowercase + underscore)
- Global constants: `FLOSC_CONSTANT_NAME` (all UPPERCASE + underscore)
- Examples:
  - `class floscApp` ✅ OR `class flosc_app` ✅
  - `floscHandleSubmit()` ✅ OR `flosc_handle_submit()` ✅
  - `const floscConfig = {...}` ✅ OR `const flosc_config = {...}` ✅
  - `window.FLOSC_CONFIG` ✅

**Note:** Use lowercase whenever possible. Choose either camelCase or snake_case and stay consistent within each file.

**CSS (Styling):**
- Classes: `flosc-class-name` (all lowercase + hyphen)
- IDs: `flosc-element-id` (all lowercase + hyphen)
- CSS variables: `--flosc-variable-name` (all lowercase + hyphen)
- Examples:
  - `.flosc-message-container` ✅
  - `#flosc-app-root` ✅
  - `var(--flosc-primary-color)` ✅

**HTML (Markup):**
- Classes: `flosc-class-name` (all lowercase + hyphen)
- IDs: `flosc-element-id` (all lowercase + hyphen)
- Data attributes: `data-flosc-attribute-name` (all lowercase + hyphen)
- Examples:
  - `<div class="flosc-message">` ✅
  - `<div id="flosc-intro-panel">` ✅
  - `<button data-flosc-action="start-quiz">` ✅

### 3.2 Audit Results

**Audit completed - No violations found! ✅**

Checked:
- ✅ PHP files: No hyphens in function/variable names
- ✅ JavaScript files: No underscores in camelCase names
- ✅ CSS files: No underscores in class names
- ✅ HTML files: No underscores in classes/IDs/data attributes

**Conclusion:** Current code already follows the underscore/hyphen convention correctly. No changes needed for this category.

---

## Task Category 4: JavaScript Class Naming

### 4.1 JavaScript Class Name

**File:** `assets/js/flosc-app.js` (line 6)

```javascript
// BEFORE:
class FloscApp {

// AFTER:
class FLOSCApp {
```

**Reasoning:**
- PHP classes use `FLOSC_Framework` (all caps FLOSC with underscore)
- JavaScript should use `FLOSCApp` (all caps FLOSC, PascalCase for App)
- Maintains consistency with FLOSC_CONFIG and FLOSC_USER global variables

**Files to Update:**
1. `assets/js/flosc-app.js` - Class declaration and any internal references
2. `templates/flosc-app.php` - Class instantiation if referenced

---

## Task Category 5: Global PHP Functions (Already Correct)

**No changes needed** - All global functions already use flosc_ prefix:
- `flosc()`
- `flosc_adjust_brightness()`
- `flosc_activate()`
- `flosc_sale()`

---

## Task Category 6: PHP Classes (Already Correct)

**No changes needed** - All classes already use FLOSC_ prefix:
- `FLOSC_Framework`
- `FLOSC_Offer_Manager`
- `FLOSC_Access_Manager`
- `FLOSC_Sale_Manager`
- `FLOSC_Payment_Provider` (abstract)
- `FLOSC_Affiliate_Provider`
- `FLOSC_Stripe_Provider`
- `FLOSC_Token_Provider`
- `FLOSC_Usage_Tracker`
- `FLOSC_Quiz_Type_Factory`
- `FLOSC_Lesson_Manager`
- `FLOSC_STT_Provider_Factory`
- `FLOSC_Abstract_Quiz_Type` (abstract)
- `FLOSC_Pronunciation_Quiz`
- `FLOSC_Simple_Scoring_Quiz`
- `FLOSC_MultipleChoice_Quiz`
- `FLOSC_WordMatching_Quiz`
- `FLOSC_TrueFalse_Quiz`
- `FLOSC_Pronunciation_Analyzer`
- `FLOSC_Session_Manager`
- `FLOSC_AI_Provider_Factory`

---

## Task Category 7: Global JavaScript Variables (Already Correct)

**No changes needed** - Already properly prefixed:
- `window.FLOSC_CONFIG`
- `window.FLOSC_USER`

---

## Implementation Strategy

### Phase 1: Documentation (Low Risk)
1. Update all WHATS_NEW_*.md files
2. Replace "fix/fixed/hotfix" terminology
3. Test: Read through docs to ensure language flows naturally

### Phase 2: Code Comments (Low Risk)
1. Update PHP code comments in flosc.php
2. Update JavaScript code comments in flosc-app.js
3. Test: Review comments for clarity

### Phase 3: JavaScript Class Name (Medium Risk)
1. Rename FloscApp → FLOSCApp in flosc-app.js
2. Update any references in templates/flosc-app.php
3. Test: Verify app still initializes correctly

### Phase 4: CSS Classes (HIGH RISK - Major Change)
1. Create backup of v3.0.9
2. Update assets/css/flosc-app.css (all 146 class definitions)
3. Update templates/flosc-app.php (all HTML class references)
4. Update assets/js/flosc-app.js (any querySelector/classList references)
5. Test extensively:
   - All UI elements render correctly
   - All modals open/close
   - All buttons work
   - All hover effects work
   - All responsive behaviors work
   - No visual regressions

---

## Version Number

**Target:** v3.0.10
**Description:** "Code Hygiene - Consistent Naming"

---

## Testing Checklist

**After Phase 1-3:**
- [ ] All documentation reads naturally
- [ ] No "fix" terminology in user-facing docs (except CSS position:fixed)
- [ ] JavaScript app initializes correctly

**After Phase 4:**
- [ ] Page loads without console errors
- [ ] All UI elements visible and styled correctly
- [ ] Sidebar opens/closes
- [ ] Modals (recording, payment, share) open/close
- [ ] All buttons clickable and styled
- [ ] Prompt cards work
- [ ] IntroPanel shows/hides correctly
- [ ] Message bubbles display correctly
- [ ] User profile dropdown works
- [ ] Upgrade banner shows/dismisses
- [ ] Mobile responsive view works
- [ ] No CSS conflicts with WordPress admin
- [ ] No CSS conflicts with common themes (Twenty Twenty-Four, etc.)

---

## Risk Assessment

**Low Risk (Phases 1-3):**
- Documentation changes: No functional impact
- Comment changes: No functional impact
- JavaScript class rename: Minimal, self-contained

**HIGH Risk (Phase 4):**
- CSS class renaming affects 146 classes across 3 files
- Any missed reference = broken layout or non-functional element
- Requires careful find/replace and thorough testing
- Recommendation: Use automated search/replace with verification

---

## Estimated Changes

- **Documentation files:** 6 files, ~50-80 instances of "fix" terminology
- **PHP comments:** 2 instances
- **JavaScript comments:** 1 instance
- **JavaScript class name:** 1 class declaration + any instantiation references
- **CSS definitions:** 146 class definitions (including pseudo-selectors and combinators)
- **HTML class attributes:** ~150-200 class references in template
- **JavaScript selectors:** ~30-50 querySelector/classList references

**Total lines changed:** ~400-500 lines across 9 files

---

## Approval Questions

1. **Terminology:** Approve replacing "fix/fixed/hotfix" with "resolution/resolved/critical update"?
2. **JavaScript class:** Approve FloscApp → FLOSCApp?
3. **CSS classes:** Approve prefixing all 146 CSS classes with `flosc-`?
4. **Phasing:** Should we do this in one version (v3.0.10) or split across multiple versions?
5. **Risk tolerance:** The CSS changes are high-risk. Should we proceed or wait?

---

## Alternative Approach (If CSS Changes Too Risky)

**Option: Incremental CSS Prefixing**
- v3.0.10: Only fix terminology + JS class name (low risk)
- v3.0.11: Prefix CSS classes in phases (sidebar classes only)
- v3.0.12: Prefix CSS classes (message/chat classes only)
- v3.0.13: Prefix CSS classes (modal/button classes only)
- v3.0.14: Prefix remaining CSS classes

This spreads risk but requires more versions.

---

## Recommendation

**Proceed with all phases in v3.0.10** if:
- You can do thorough testing before deployment
- You have a backup/rollback plan
- Development site available for testing

**Split into phases** if:
- Production deployment with no staging environment
- Limited time for testing
- Want to minimize risk per version

---

**Awaiting approval before coding.**
