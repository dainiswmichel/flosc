# FLOSC v05_06 Hardcore Code Review

**Date:** 2026-01-11
**Reviewer:** Claude
**Verdict:** 7.5/10 - Solid foundation, 4 bugs need fixing

---

## ✅ WHAT'S WORKING WELL

1. **REST Endpoint Security** - All cost endpoints have rate limiting via `check_paid_endpoint_permission()`
2. **Webhook Security** - Stripe signature validation is properly implemented
3. **No Code Corruption** - All `...` occurrences are in help text strings, not logic
4. **IVR Duplicate Menu Fixed** - IVR Manager menu registration is properly commented out
5. **Quiz Items Display** - Both pronunciation and simple_scoring show items in modal
6. **Session Timing** - Uses `session_minutes` consistently
7. **transitionToPhase()** - Function exists and works correctly
8. **Cache Key** - Includes system_prompt so context is properly differentiated

---

## 🔴 BUGS TO FIX (4 issues)

### Bug 1: Missing User Flags in FLOSC_USER (CRITICAL)

**File:** `flosc.php` lines 448-459

**Problem:** JavaScript `determineFLOSCPhase()` expects these user properties:
- `offerShown`
- `purchased`
- `onboarded`
- `quizScore`

But PHP only provides:
- `lastQuizScore` (wrong name)
- `freeLessonDelivered`
- `funnelCompleted`

**Impact:** Phase logic breaks - users may get stuck in wrong phase.

**FIX:**

```php
// flosc.php line 448-459 - REPLACE WITH:
            $user_data = [
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 40]),
                'state' => $user_state,
                'access' => $this->sale_manager->access()->get_user_access($user->ID),
                'tokens' => $this->sale_manager->get_provider('tokens')->get_balance($user->ID),
                'freeLessonDelivered' => (bool) get_user_meta($user->ID, '_flosc_free_lesson_delivered', true),
                'lastQuizScore' => get_user_meta($user->ID, '_flosc_last_quiz_score', true),
                'funnelCompleted' => (bool) get_user_meta($user->ID, '_flosc_funnel_completed', true),
                // v05_07: Add missing flags for phase logic
                'quizScore' => get_user_meta($user->ID, '_flosc_last_quiz_score', true),
                'offerShown' => (bool) get_user_meta($user->ID, '_flosc_offer_shown', true),
                'purchased' => ($user_state === 'paid' || $this->sale_manager->access()->has_access($user->ID, 'content')),
                'onboarded' => (bool) get_user_meta($user->ID, '_flosc_onboarded', true),
            ];
```

---

### Bug 2: Hardcoded OTO Message (MEDIUM)

**File:** `assets/js/flosc-app.js` lines 732-746

**Problem:** `showUpgradeOffer()` has hardcoded marketing copy instead of using IVR config.

**FIX:**

```javascript
// flosc-app.js - REPLACE showUpgradeOffer() method:
    showUpgradeOffer() {
        // Use IVR config if available, otherwise use fallback
        const offerConfig = this.ivr?.config?.offer;
        let message;
        
        if (offerConfig && offerConfig.initial_message) {
            message = this.replaceIVRVariables(offerConfig.initial_message);
        } else {
            // Fallback message
            message = `**Ready to master everything?** 🚀\n\n`;
            message += `You've made great progress with your free lesson!\n\n`;
            
            if (this.offers && this.offers.length > 0) {
                const offer = this.offers[0];
                message += `**${offer.name}** - ${offer.display_price}\n\n`;
                message += `${offer.description || 'Unlock all lessons and unlimited practice.'}\n\n`;
            }
            
            message += `Ready to continue your journey?`;
        }
        
        this.addMessage('assistant', message);
        this.transitionToPhase('offer');
    }
```

---

### Bug 3: IntroPanel vs IVR Initial Message Conflict (MEDIUM)

**File:** `assets/js/flosc-app.js` line 1420-1430

**Problem:** Both IntroPanel AND `startIVR()` can show messages on page load, confusing users.

**FIX:**

```javascript
// flosc-app.js - REPLACE startIVR() method:
    startIVR() {
        // Don't show initial message if IntroPanel is visible
        if (this.introPanel && !this.introPanel.classList.contains('hidden')) {
            return; // IntroPanel handles initial interaction
        }
        
        // Show initial message for current phase
        if (!this.ivr.initialMessageShown) {
            const phaseConfig = this.ivr.config[this.ivr.phase];
            if (phaseConfig && phaseConfig.initial_message) {
                // Replace variables
                const message = this.replaceIVRVariables(phaseConfig.initial_message);
                this.addMessage('assistant', message);
                this.ivr.initialMessageShown = true;
            }
        }
    }
```

---

### Bug 4: Offer Shown Flag Never Set (MEDIUM)

**Problem:** `offerShown` is checked in phase logic but never set when offer is actually shown.

**FIX - Add to flosc-app.js after showUpgradeOffer():**

```javascript
    showUpgradeOffer() {
        // ... existing code ...
        
        // Mark offer as shown (persist to server)
        if (this.user.id) {
            fetch(this.config.restUrl + 'flosc/v1/mark-offer-shown', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.config.nonce
                }
            }).catch(e => console.warn('Failed to mark offer shown:', e));
        }
        
        this.user.offerShown = true; // Update local state
        this.transitionToPhase('offer');
    }
```

**FIX - Add REST endpoint to flosc.php:**

```php
// Add after line 1000 in register_rest_routes():
        register_rest_route('flosc/v1', '/mark-offer-shown', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_mark_offer_shown'],
            'permission_callback' => 'is_user_logged_in',
        ]);

// Add handler method:
    public function handle_mark_offer_shown($request) {
        $user_id = get_current_user_id();
        update_user_meta($user_id, '_flosc_offer_shown', true);
        return new WP_REST_Response(['success' => true]);
    }
```

---

## ⚠️ WARNINGS (Non-breaking but should address)

### Warning 1: Lessons REST Endpoint Too Permissive

**File:** `flosc.php` line 977

```php
        register_rest_route('flosc/v1', '/lessons', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_get_lessons'],
            'permission_callback' => '__return_true',  // <-- Anyone can list lessons
        ]);
```

**Recommendation:** If lesson listing reveals paid content structure, consider restricting:

```php
            'permission_callback' => function() {
                return is_user_logged_in();
            },
```

---

### Warning 2: No CSRF on IVR Config Save

**File:** `includes/class-ivr-manager.php` - AJAX handler

The IVR config save uses `flosc_ivr_nonce` but should verify it properly:

```php
// In ajax_save_config() - ensure this check exists:
    public function ajax_save_config() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'flosc_ivr_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        // ... rest of save logic
    }
```

---

### Warning 3: AI Knowledge Upload Missing Nonce Verification

**File:** `templates/admin/settings.php` - AI Knowledge tab

The file upload form has a nonce but I didn't see verification in the handler.

**Recommendation:** Add to the upload handler:

```php
if (!wp_verify_nonce($_POST['flosc_ai_knowledge_nonce'] ?? '', 'flosc_ai_knowledge')) {
    $error = 'Security check failed';
}
```

---

## 📋 PRIORITY FIX ORDER

1. **Bug 1** (CRITICAL) - Missing user flags breaks phase logic
2. **Bug 4** (MEDIUM) - Offer shown flag never persists
3. **Bug 2** (MEDIUM) - Hardcoded marketing copy
4. **Bug 3** (MEDIUM) - Dual welcome message issue

---

## 🔒 SECURITY AUDIT SUMMARY

| Endpoint | Permission | Status |
|----------|------------|--------|
| /ai-query | rate limited + login check | ✅ |
| /process-audio | rate limited + login check | ✅ |
| /process-quiz | rate limited + login check | ✅ |
| /sessions | is_user_logged_in | ✅ |
| /offers | public (read-only) | ✅ |
| /purchase | is_user_logged_in | ✅ |
| /create-payment-intent | is_user_logged_in | ✅ |
| /webhooks | public (signature verified) | ✅ |
| /access | public (read-only) | ✅ |
| /tokens | is_user_logged_in | ✅ |
| /lessons | public | ⚠️ Consider restricting |
| /lessons/{id} | is_user_logged_in | ✅ |
| /lessons/free | is_user_logged_in | ✅ |
| /store-score | public | ✅ (pre-login flow) |
| /test-ai | manage_options | ✅ |

---

## OVERALL VERDICT

**7.5/10 - Production-viable with Bug 1 fix**

The codebase is well-structured with proper security on cost endpoints. The main issue is the phase logic breaking due to missing user flags. Fix Bug 1 first, then the others.

The architecture is sound:
- Clean class separation
- Proper WordPress patterns
- IVR system well-designed
- Multiple AI provider support
- Payment provider abstraction

Fix the 4 bugs and this is production-ready.
