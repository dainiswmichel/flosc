Fix the CSS/display bugs listed below in mvp_sprint/flosc_1_7_8/. These are all visual/styling issues.

## RULES
- Only edit CSS files (flosc-layout.css, flosc-theme.css, flosc-offers.css) unless the bug is clearly in JS-generated HTML
- Use CSS custom properties from flosc-theme.css — never hardcode hex colors
- Read 50+ lines of context around each target before editing
- After each fix, state what changed and what the user should see differently in the browser
- Do NOT claim "fixed" — say "edit applied, needs browser testing"

## Bug 1: ProfileBar Cut Off (Bottom-Left)
The user profile bar at the bottom-left of the chat window is visually clipped/cut off. Find the ProfileBar component styles in flosc-layout.css or flosc-app.js inline styles. The issue is likely:
- `overflow: hidden` on a parent container cutting it off
- Insufficient height/padding on the profile bar container
- z-index stacking issue
Search for: `.flosc-profile`, `.flosc-user-profile`, `profile-bar`, `profileBar` in CSS and JS files.

## Bug 2: Quiz Result Card Cramped
The quiz results bubble (showing the score circle, percentage, and correct/missed counts) appears visually cramped. Find `.flosc-quiz-result` styles in flosc-offers.css and add appropriate padding/spacing. The score circle, text, and breakdown should have breathing room.

## Bug 3: Offer Cards Not Full-Width
Offer cards (`.flosc-offer-card`, `.flosc-offer-featured`) don't fill the available width within the chat message area. They should span the full message width. Check if there's a max-width constraint on the message container or the offer card itself.

## Bug 4: --flosc-scale Not Working
The admin setting for chat scale (`--flosc-scale`) defined in flosc-layout.css (~line 87) may not actually apply. Verify:
1. Is `--flosc-scale` set on the body or .flosc-app element?
2. Does the CSS `transform: scale(var(--flosc-scale))` or `font-size` actually reference it?
3. If the variable is set but unused, wire it up to the root .flosc-app container.

## Bug 5: Text Input in Sequence Quiz
The text input field for sequence quizzes (`.flosc-quiz-text-input`) may appear too narrow or unstyled on mobile. Verify it has:
- Full width within its container
- Adequate font-size (minimum 16px to prevent iOS zoom)
- Proper border-radius and padding matching the theme

## DO NOT
- Change any JavaScript logic (only CSS/HTML fixes)
- Touch IVR data or message conditions
- Modify payment or API code
- Add new features — only fix existing display bugs
