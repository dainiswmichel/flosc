# SVG Icon Visibility Diagnosis Report
**Date:** 2026-01-25 17:10
**Tested Versions:** v9.6.3, v9.6.4, v9.6.5
**Problem:** Restart chat icon and send message arrow icon are NOT VISIBLE

---

## HTML Structure (Confirmed Correct)

### Restart Button
```html
<aside class="flosc-sidebar">
    <button class="sidebar-action-btn" id="flosc_app_restart_chat">
        <svg width="18" height="18" viewBox="0 0 24 24" 
             fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 12a9 9 0 0 1 9-9..."></path>
        </svg>
    </button>
</aside>
```

### Send Button
```html
<button class="flosc_input_chat_send_button" id="flosc_input_chat_send_button">
    <svg width="20" height="20" viewBox="0 0 24 24" 
         fill="none" stroke="currentColor" stroke-width="2">
        <line x1="22" y1="2" x2="11" y2="13"></line>
        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
    </svg>
</button>
```

**HTML Status:** ✅ CORRECT
- SVGs have `stroke="currentColor"` attribute
- SVGs have `fill="none"` attribute
- SVGs have proper viewBox and paths
- Buttons are properly wrapped with correct classes

---

## CSS Structure (Current)

### Layout CSS (flosc-layout.css)
```css
/* Button container */
.flosc-sidebar-action-btn,
.flosc-sidebar .sidebar-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

/* SVG sizing */
.flosc-sidebar-action-btn svg,
.flosc-sidebar .sidebar-action-btn svg {
    width: 20px;
    height: 20px;
    display: block;
}

/* Send button container */
.flosc_input_chat_send_button {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
    transition: background 0.15s;
}

/* Send button SVG */
.flosc_input_chat_send_button svg {
    width: 22px;
    height: 22px;
    display: block;
    transform: translateX(1px) translateY(-1px);
}
```

### Theme CSS (flosc-theme.css)
```css
/* Sidebar buttons color */
.flosc-sidebar-action-btn,
.flosc-sidebar .sidebar-action-btn {
    background: transparent;
    color: var(--flosc-sidebar-text-muted);
}

/* Send button color */
.flosc_input_chat_send_button {
    background: var(--flosc-send-btn-bg);
    color: var(--flosc-send-btn-text);
    border: none;
    border-radius: 8px;
}

/* CSS Variables (root) */
:root {
    --flosc-sidebar-text-muted: #6b7280;
    --flosc-send-btn-text: #ffffff;
    --flosc-send-btn-bg: #2563eb;
}
```

**CSS Status:** ✅ APPEARS CORRECT
- Buttons have `color` property set
- SVGs have `display: block` and dimensions
- No conflicting `stroke` or `fill` rules with `!important`

---

## Expected Behavior vs Actual

### How It Should Work:
1. Button CSS sets: `color: var(--flosc-sidebar-text-muted)` → evaluates to `#6b7280` (gray)
2. SVG has inline attribute: `stroke="currentColor"`
3. Browser resolves `currentColor` → inherits button's `color` (#6b7280)
4. SVG strokes should be visible gray color

### What's Actually Happening:
1. Icons are NOT VISIBLE (completely invisible)
2. Buttons themselves might be visible (need confirmation)
3. No errors in console (presumably - needs verification)

---

## Possible Root Causes

### 1. CSS Variable Not Resolving
**Hypothesis:** `var(--flosc-sidebar-text-muted)` might not be defined or is evaluating to `transparent`

**Test Needed:** Check browser DevTools computed styles on button element
- Does `color` compute to `#6b7280` or something else?
- Is CSS variable defined in :root?

### 2. CSS Load Order Issue
**Hypothesis:** flosc-layout.css might be loading AFTER flosc-theme.css, causing button color to not be set

**Test Needed:** Check browser Network tab
- What order do CSS files load?
- Is flosc-theme.css actually loaded?

**Current PHP load order:**
```php
// Need to verify this in flosc.php
wp_enqueue_style('flosc-layout', ...);
wp_enqueue_style('flosc-theme', ...);
```

### 3. currentColor Not Working
**Hypothesis:** `stroke="currentColor"` inline attribute might not be inheriting from parent

**Test Needed:** Check browser DevTools computed styles on SVG element
- Does `stroke` compute to a color or `currentColor` string?
- What is the actual computed stroke value?

### 4. Z-Index or Visibility Issue
**Hypothesis:** Icons might be rendering but hidden behind something or opacity: 0

**Test Needed:** Check computed styles
- Is `opacity` set to 0?
- Is `visibility: hidden`?
- Is there a z-index stacking issue?

### 5. SVG Display Issue
**Hypothesis:** `display: block` might not be correct for SVGs in flex containers

**Test Needed:** Try removing `display: block` or try `display: inline-block`

### 6. Browser Caching
**Hypothesis:** Old CSS might be cached

**Test Needed:** Hard refresh (Cmd+Shift+R) or clear browser cache

---

## What We Need From You

To diagnose this properly, we need:

1. **Browser DevTools Screenshot**
   - Right-click on the restart button
   - Select "Inspect Element"
   - Screenshot showing:
     - The button element's computed styles (specifically `color` property)
     - The SVG element's computed styles (specifically `stroke` property)
     - The Styles panel showing which CSS rules are applied

2. **Console Errors**
   - Open browser console (F12)
   - Any red errors showing?
   - Screenshot if yes

3. **Network Tab**
   - Check if flosc-layout.css and flosc-theme.css are loading
   - Any 404 errors?

4. **Basic Visibility Test**
   - Can you see the BUTTON itself (the clickable area)?
   - Or is everything invisible?
   - If you hover where the button should be, does it respond?

---

## Next Steps (After Diagnosis)

Once we know which of the above issues it is, we can:

### If CSS variables not resolving:
→ Add fallback colors directly in layout.css

### If CSS load order wrong:
→ Fix PHP enqueue order in flosc.php

### If currentColor not working:
→ Set stroke explicitly in CSS (not with !important)

### If display issue:
→ Try different display values

### If browser cache:
→ Add version query string to CSS files

---

**Awaiting your diagnosis info before proceeding.**
