# Pills & Cards Styling Plan

**Created:** 2026-01m-30d  
**Author:** Claude Opus 4.5 (AI) + Human Direction  
**Status:** Planning (DO NOT CODE YET)

---

## Context

The IntroPanel (visitors) and PromptPanel (guests/members) display suggested prompts to users. These prompts can be styled as **pills** (compact, single-line) or **cards** (larger format with icon + text).

### Vocabulary (LOCKED)

| User State | User-Facing Name | Internal Name | CSS Class |
|------------|------------------|---------------|-----------|
| Visitor | IntroPanel | `floscVisitorIntroPanel` | `.intro-panel` |
| Guest | PromptPanel | `floscGuestPromptPanel` | `.prompt-panel` |
| Member | PromptPanel | `floscMemberPromptPanel` | `.prompt-panel` |

---

## Research: Original Card Styling (v3–v5 Era)

Found in `flosc_development_archives/flosc_v05_09/assets/css/flosc-app.css`:

```css
.suggested-prompts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    max-width: 500px;
    width: 100%;
    margin: 0 auto;
}

.prompt-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px;
    background: white;
    border: 1px solid #e5e5e5;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    /* NO FIXED HEIGHT - flexes to content */
}

.prompt-icon {
    font-size: 24px;
}

.prompt-text {
    font-size: 14px;
    font-weight: 500;
}
```

### Key Characteristics (What Worked)

- **No fixed height** — cards flex to content ✅
- **Equal widths** via CSS grid `1fr` ✅
- **2 columns** on all screens
- **Container max-width:** 500px (may be too narrow)
- **Padding:** 20px
- **Gap:** 12px between cards, 8px between icon/text
- **Border-radius:** 8px

### Issue Reported

"Didn't expand horizontally" — the 500px max-width container was too restrictive.

---

## Current State (flosc_1_0_1)

I made hasty changes that need to be reviewed/reverted:

### CSS Variables Added (Keep)
```css
/* Cards / Suggested Replies (larger format) */
--flosc-card-bg: #ffffff;
--flosc-card-text: #374151;
--flosc-card-border: #e5e7eb;
--flosc-card-hover-bg: #f9fafb;
--flosc-card-hover-text: var(--flosc-accent);
--flosc-card-hover-border: var(--flosc-accent);
--flosc-card-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
--flosc-card-hover-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
```

### Card CSS Added (Needs Review)
I added card styling but kept changing it. Current state may be broken.

### JS Carousel Logic (Needs Review)
Added overflow detection that may or may not work.

---

## SPECIFICATIONS (APPROVED 2026-01-30)

### Business Rules (LOCKED)

1. **Never mix pills and cards in one panel** - Panel-level style toggle, not per-item
2. **Single row only** - If items overflow container width, carousel activates
3. **Carousel triggered by overflow** - Not item count (was `>4`, now `scrollWidth > clientWidth`)
4. **Majority wins if mixed** - Pills break ties (but admin shouldn't allow mixing)

### Container Specifications

| Property | Pills Container | Cards Container |
|----------|-----------------|-----------------|
| Display | `flex` | `grid` |
| Layout | `flex-wrap: wrap` | `grid-template-columns: repeat(2, 1fr)` |
| Gap | `8px` | `12px` |
| Max Width | `100%` | `100%` |
| Overflow | Carousel on `scrollWidth > clientWidth` | Carousel on `scrollWidth > clientWidth` |

### Carousel Logic

```javascript
// Pseudo-code for overflow detection
function checkCarouselNeeded(container) {
    const track = container.querySelector('.track');
    const needsCarousel = track.scrollWidth > track.clientWidth;
    
    if (needsCarousel) {
        container.classList.add('has-carousel');
        showArrows(container);
    } else {
        container.classList.remove('has-carousel');
        hideArrows(container);
    }
}

// Check on: render, resize, content change
```

### IVR.md New Parameters

Each `suggested_user_autoprompt` message will have:

```markdown
MessagePanel: intro | prompt
MessageStyle: pill | card | button | chip
```

**Panel Logic:**
- `intro` → Only appears in IntroPanel (visitors)
- `prompt` → Only appears in PromptPanel (guests/members)
- Default: Inferred from conditions (`is_visitor` → intro, `is_guest|is_member` → prompt)

**Style Logic:**
- Per-message style still supported for backward compatibility
- Admin can set panel-level default style
- Panel-level style overrides individual styles to prevent mixing

---

## Implementation Checklist

### Phase 1: IVR.md Updates ✅
- [x] Document `MessagePanel` parameter
- [x] Document style enforcement rules
- [x] Added examples (get_started_001, view_free_lesson_001)

### Phase 2: Overflow Carousel Detection ✅
- [x] Update `renderSuggestedReplies()` in flosc-app.js
- [x] New `initCarouselOverflow()` method with overflow check
- [x] Resize listener with debounce
- [x] CSS: Arrows hidden by default, shown when `.has-overflow`
- [x] Updated sample HTML with working JS demo

### Phase 3: Admin Integration (PENDING)
- [ ] Update ivr-admin.js to collect `panel` parameter
- [ ] Add panel-level style toggle option
- [ ] Validate: prevent mixing styles in same panel

---

## References

- Archive source: `flosc_development_archives/flosc_v05_09/assets/css/flosc-app.css`
- Current work: `mvp_sprint/flosc_1_0_1/assets/css/flosc-theme.css`
- Style guide: `flosc_styling_development/FLOSC_STYLE_GUIDE.md`
- Shell plan: `flosc_styling_development/FLOSC_STYLING_SHELL_PLAN.md`

---

*Awaiting direction before any further code changes.*
