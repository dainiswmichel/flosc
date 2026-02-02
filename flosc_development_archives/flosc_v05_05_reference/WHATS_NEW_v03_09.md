# FLOSC v3.0.9 - "Activation Hook Fix"

**Date:** 2026-01m-09d
**Built on:** v3.0.8 (hotfix)

---

## What's New

v3.0.9 is a critical hotfix that fixes the broken activation hook in v3.0.8.

---

## The Bug in v3.0.8

### Problem
Prompt cards in v3.0.8 showed user message but no bot response. Console debugging revealed `FLOSC_CONFIG.messages.getStarted` returned empty string `""`.

### Root Cause
**File:** `flosc.php` (line 100 in v3.0.8)

The activation hook was registered inside the class constructor:
```php
// Inside init_hooks() method
register_activation_hook(__FILE__, [$this, 'activate']);
```

**Why This Failed:**
- Plugin activation happens when admin clicks "Activate"
- Class constructor runs during `plugins_loaded` hook
- `plugins_loaded` fires AFTER activation completes
- Hook registration came too late - never executed

**Result:**
- Database defaults never set
- `FLOSC_CONFIG.messages.getStarted` = `""` (empty string)
- Conditional `if (FLOSC_CONFIG.messages.getStarted)` failed (falsy)
- No bot response appeared

---

## The Fix in v3.0.9

### Solution
Moved activation hook registration to root level (outside class).

**File:** `flosc.php` (lines 1262-1306)

**Before (v3.0.8):**
```php
class FLOSC_Framework {
    private function init_hooks() {
        // ... other hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
    }
}

add_action('plugins_loaded', 'flosc'); // Too late!
```

**After (v3.0.9):**
```php
class FLOSC_Framework {
    private function init_hooks() {
        // Activation hook removed from here
    }
}

// Standalone activation function (runs at root level)
function flosc_activate() {
    // Set defaults
    $defaults = [
        'flosc_app_slug' => 'app',
        'flosc_product_name' => '',
        'flosc_product_tagline' => '',
        'flosc_product_emoji' => '🎯',
        'flosc_primary_color' => '#4f46e5',
        'flosc_ai_provider' => 'ivr',
        'flosc_stt_provider' => 'assemblyai',
        'flosc_quiz_type' => 'simple_scoring',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }

    // Force critical "out of box" defaults
    $force_defaults = [
        'flosc_quiz_content_simple_scoring' => '1,2,3,4,5,6,7,8,9,10',
        'flosc_token_name' => 'tokens',
        'flosc_get_started_message' => 'FLOSC Default Get started text: Welcome! I\'m your FLOSC learning assistant. I\'m here to help you master new skills through interactive lessons and quizzes. Ready to get started?',
        'flosc_how_it_works_message' => 'FLOSC Default How does it work? text: Here\'s how it works: First, you\'ll take a quick quiz to assess your current level. Then, based on your results, I\'ll unlock a free lesson personalized to your needs. After that, you can upgrade for full access to all lessons and ongoing support.',
        'flosc_what_you_learn_message' => 'FLOSC Default What will I learn? text: You\'ll master practical skills through interactive lessons, get personalized feedback on your progress, and access a complete learning path designed to take you from beginner to advanced. Each lesson includes exercises, quizzes, and real-world applications.',
    ];

    foreach ($force_defaults as $key => $value) {
        update_option($key, $value);
    }

    flush_rewrite_rules();
}

// Register at root level (runs immediately when file loads)
register_activation_hook(__FILE__, 'flosc_activate');

add_action('plugins_loaded', 'flosc');
```

**Why This Works:**
- Activation hook registered at root level (file parse time)
- Hook is registered BEFORE WordPress processes activation
- `flosc_activate()` runs when admin clicks "Activate"
- Database defaults set correctly
- Prompt cards work!

---

## File Changes

### Modified Files

**`flosc.php`**
- Version 3.0.9 (lines 6, 17)
- Removed activation hook from `init_hooks()` method (line 100 deleted)
- Added standalone `flosc_activate()` function (lines 1265-1300)
- Added root-level activation hook registration (line 1303)

---

## Diagnosis Process

### How We Found It

**Step 1: Console Check**
```javascript
FLOSC_CONFIG.messages.getStarted
// Result: "" (empty string)
```

**Step 2: Database Check**
```bash
wp option get flosc_get_started_message
// Result: Error: Could not get 'flosc_get_started_message' option. Does it exist?
```

**Step 3: Activation Hook Investigation**
- Found hook registered in constructor (line 100)
- Constructor runs during `plugins_loaded`
- Activation happens before `plugins_loaded`
- Hook registration = too late

---

## Testing Checklist

**After Deploying v3.0.9:**
- [x] Deactivate v3.0.8
- [x] Upload v3.0.9
- [x] Activate v3.0.9
- [x] Check database: `wp option get flosc_get_started_message`
- [x] Should return: "FLOSC Default Get started text: Welcome!..."
- [x] Test prompt cards:
  - [x] Click "Get started" → User message → Bot response appears
  - [x] Click "How does it work?" → User message → Bot response appears
  - [x] Click "What will I learn?" → User message → Bot response appears
- [x] Test X button → "Hide IntroPanel" message → Bot explains → Panel hides
- [x] Test "Show IntroPanel" command → Panel reappears

---

## Breaking Changes

None - fully backward compatible with v3.0.8 features.

---

## Migration Notes

**Upgrading from v3.0.8:**
1. Deactivate FLOSC v3.0.8
2. Upload flosc_v03_09.zip
3. Activate FLOSC v3.0.9
4. Prompt cards will now work
5. All v3.0.8 features (IntroPanel, IVR commands) preserved

---

## Key Lessons

### WordPress Plugin Architecture

**Activation Hook Registration:**
- MUST be at root level (file parse time)
- Cannot be inside constructor that runs on `plugins_loaded`
- `register_activation_hook(__FILE__, 'function_name')` must execute before activation

**Execution Order:**
1. File parsed (root-level code runs)
2. Activation hook registered
3. Admin clicks "Activate"
4. Activation function runs
5. Later: `plugins_loaded` fires (class instantiated)

**Wrong Pattern (v3.0.8):**
```php
class Plugin {
    function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']); // Too late!
    }
}
add_action('plugins_loaded', function() { new Plugin(); });
```

**Right Pattern (v3.0.9):**
```php
function plugin_activate() {
    // Activation logic
}
register_activation_hook(__FILE__, 'plugin_activate'); // Root level!

class Plugin {
    // No activation hook here
}
add_action('plugins_loaded', function() { new Plugin(); });
```

---

## Credits

**Developed:** 2026-01m-09d
**Implemented:** Claude Code Agent
**Date Stamp Format:** Michel Date Stamp Innovation (YYYY-MMm-DDd)
**Debugging:** Dainis Michel (found issue, checked console, verified database)

---

## v3.0.8 vs v3.0.9 Summary

**v3.0.8 Status:**
- IntroPanel: ✅ Works
- X button: ✅ Works
- IVR commands: ✅ Work
- Prompt cards: ❌ BROKEN (no bot response)
- Database defaults: ❌ Never set

**v3.0.9 Status:**
- IntroPanel: ✅ Works
- X button: ✅ Works
- IVR commands: ✅ Work
- Prompt cards: ✅ FIXED (bot response appears)
- Database defaults: ✅ Set correctly

---

**Bottom Line:** v3.0.9 fixes the critical activation hook bug that broke prompt cards in v3.0.8. One-line change in hook registration location = plugin finally works "out of box."
