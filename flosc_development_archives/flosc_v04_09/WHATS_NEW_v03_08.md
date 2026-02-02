# FLOSC v3.0.8 - "IntroPanel + IVR Command System"

**Date:** 2026-01m-09d
**Built on:** v3.0.7 fixes

---

## What's New

v3.0.8 adds two major features:
1. **IntroPanel** - Dismissible welcome panel with chat-based control
2. **IVR Command System** - Status commands that work without AI

---

## 1. Fixed Broken Prompt Cards (from v3.0.7)

### Problem
v3.0.7 used `addBotMessage()` which doesn't exist - clicking prompt cards did nothing.

### Fix
**File:** `assets/js/flosc-app.js` (lines 338-373)

Changed to proper `addMessage()` pattern:
```javascript
case 'get-started':
    this.addMessage('user', 'Get started');
    if (FLOSC_CONFIG.messages.getStarted) {
        this.addMessage('assistant', FLOSC_CONFIG.messages.getStarted);
    }
    this.showMessages();
    this.hideIntroPanel();
    break;
```

**Result:** Prompt cards now work - user message appears, then bot response.

---

## 2. Database Defaults with "FLOSC Default" Prefix

### Why This Matters
Shows users they're seeing default content that admin can customize.

### Implementation
**File:** `flosc.php` (lines 179-187)

```php
$force_defaults = [
    'flosc_quiz_content_simple_scoring' => '1,2,3,4,5,6,7,8,9,10',
    'flosc_token_name' => 'tokens',
    'flosc_get_started_message' => 'FLOSC Default Get started text: Welcome! I\'m your FLOSC learning assistant...',
    'flosc_how_it_works_message' => 'FLOSC Default How does it work? text: Here\'s how it works...',
    'flosc_what_you_learn_message' => 'FLOSC Default What will I learn? text: You\'ll master practical skills...',
];
```

**Example Response:**
```
FLOSC Default Get started text: Welcome! I'm your FLOSC learning assistant. I'm here to help you master new skills through interactive lessons and quizzes. Ready to get started?
```

---

## 3. IntroPanel with X Button

### User Experience
- Panel shows on page load with 4 prompt cards
- User can click X to dismiss
- X button triggers chat-based dismissal (fun!)

### Implementation

**Template:** `templates/flosc-app.php` (lines 149-175)
```html
<div class="intro-panel" id="introPanel">
    <button class="intro-panel-close" id="introPanelClose">
        <svg>...</svg>
    </button>
    <div class="suggested-prompts">
        <!-- 4 prompt cards -->
    </div>
</div>
```

**CSS:** `assets/css/flosc-app.css` (lines 445-468)
- Close button positioned top-right
- Subtle, hover effect
- Doesn't interfere with cards

**JavaScript:** `assets/js/flosc-app.js`
- `hideIntroPanel()` - Hides panel
- `showIntroPanel()` - Shows panel
- `handleIntroPanelClose()` - Triggers dismissal message

---

## 4. Show/Hide IntroPanel Commands

### Chat-Based Control
Users can control IntroPanel through conversation (self-documenting!).

### Commands (case-insensitive)

**"Hide IntroPanel"**
```
User: Hide IntroPanel
Bot: IntroPanel hidden. If you ever wish to see it again, just type "Show IntroPanel" in the chat, and it will reappear.
[Panel disappears]
```

**"Show IntroPanel"**
```
User: Show IntroPanel
Bot: Here's the IntroPanel again!
[Panel reappears]
```

**X Button Behavior:**
```
[User clicks X]
User: Hide IntroPanel
Bot: IntroPanel hidden. If you ever wish to see it again, just type "Show IntroPanel" in the chat, and it will reappear.
[Panel disappears]
```

---

## 5. IVR Command System (Works Without AI)

### Purpose
Status commands that work instantly without AI API calls - foundation for IVR mode.

### Implementation
**File:** `assets/js/flosc-app.js` - `sendMessage()` (lines 386-440)

Before sending to AI, checks for IVR commands and handles them locally.

---

### Command: "Show Profile Status"

**Variants (all work):**
- "show profile status"
- "profile status"
- "my profile status"
- "what is my profile status"

**Visitor Response:**
```
You are a Visitor.

Next steps:
1. Take the free quiz
2. Create account to see your score
3. Get your FREE personalized lesson + 100 tokens
4. Upgrade for full access

Start now!
```

**Guest Response:**
```
You are a Guest.

Status:
• Quiz score: 70%
• Free lesson: Delivered
• Tokens: 87/100 remaining

Use your tokens to explore your free lesson.

Upgrade to Member:
• 1000 tokens/month
• Full lesson library
• Unlimited quiz retakes

Type "Upgrade" to unlock everything!
```

**Member Response:**
```
You are a Member. 🎉

Status:
• Full lesson library access
• Tokens: 847/1000 this month
• Tokens reset: [billing date]

You have full access to all lessons and features!
```

---

### Command: "Show Token Count"

Quick display without full profile status.

**Visitor:**
```
You don't have tokens yet. Complete the quiz and create an account to earn 100 tokens!
```

**Guest:**
```
You have 87/100 tokens remaining.

Upgrade to Member for 1000 tokens/month!
```

**Member:**
```
You have 847/1000 tokens this month.

Your tokens reset on [billing date].
```

---

### Command: "Show Quiz Score"

**No quiz taken:**
```
You haven't taken the quiz yet. Click "Start free quiz" to get started!
```

**Quiz completed:**
```
Your last quiz score: 70%

Keep practicing - you're making progress!
```

---

### Command: "Show Lessons Available"

**Visitor:**
```
Take the quiz first to unlock your personalized free lesson!
```

**Guest:**
```
Your Free Lesson:
• Introduction Lesson 1

Upgrade to unlock the full course library with 20+ lessons!
```

**Member:**
```
Full Course Library Available:

Complete Course
• 20+ comprehensive lessons
• Personalized learning path
• Unlimited access

Type "My lessons" to see your personalized path.
```

---

## 6. Configurable Token Name

### Why
Admins can customize "tokens" to match their brand (e.g., "LeSAEp tokens", "credits", "points").

### Implementation

**Database Default:**
**File:** `flosc.php` (line 183)
```php
'flosc_token_name' => 'tokens',
```

**Pass to Frontend:**
**File:** `templates/flosc-app.php` (line 419)
```php
'tokenName' => get_option('flosc_token_name', 'tokens'),
```

**Use in JavaScript:**
```javascript
const tokenName = FLOSC_CONFIG.tokenName || 'tokens';
```

**Admin Configuration:**
Settings → FLOSC → Token Name field (admin can change anytime)

---

## File Changes

### Modified Files

**`flosc.php`**
- Version 3.0.8 (lines 6, 17)
- Added `flosc_token_name` to force_defaults (line 183)
- Updated message defaults with "FLOSC Default" prefix (lines 184-186)

**`assets/js/flosc-app.js`**
- Fixed prompt cards: `addBotMessage()` → `addMessage()` (lines 338-373)
- Added IVR command system in `sendMessage()` (lines 386-440)
- Added IntroPanel methods: `hideIntroPanel()`, `showIntroPanel()`, `handleIntroPanelClose()` (lines 1083-1096)
- Added IVR response methods: `showProfileStatus()`, `showTokenCount()`, `showQuizScore()`, `showLessonsAvailable()` (lines 1098-1140)
- Added IntroPanel close button event listener (lines 154-158)

**`templates/flosc-app.php`**
- Wrapped prompt cards in IntroPanel with close button (lines 149-175)
- Added `tokenName` to FLOSC_CONFIG (line 419)

**`assets/css/flosc-app.css`**
- Added IntroPanel styles (lines 445-468)
- Close button positioning and hover effect

---

## User Experience Improvements

### 1. Self-Documenting Interface
- X button triggers chat message explaining how to bring panel back
- Commands visible in conversation history
- Users learn through interaction

### 2. Instant Feedback
- IVR commands don't wait for AI
- No loading delay
- Works even if AI fails

### 3. Clear Status Information
- Users always know: Who they are (Visitor/Guest/Member)
- What they have (tokens, quiz score, lessons)
- What they can do (next steps, upgrade path)

### 4. Sales Funnel Transparency
- Guest sees "87/100 tokens remaining" - creates urgency
- Clear upgrade path at every status level
- Next steps always visible

---

## Testing Checklist

**IntroPanel:**
- [ ] Shows on page load
- [ ] Click "Get started" → User message appears → Bot responds → Panel hides
- [ ] Same for "How does it work?" and "What will I learn?"
- [ ] Click "Start free quiz" → Recording modal opens → Panel hides
- [ ] Click X button → "Hide IntroPanel" message → Bot explains → Panel hides

**Show/Hide Commands:**
- [ ] Type "show intropanel" → Panel reappears
- [ ] Type "hide intropanel" → Panel disappears
- [ ] All case variations work (SHOW, Show, show, etc.)

**IVR Commands (Visitor):**
- [ ] "show profile status" → Shows visitor status
- [ ] "show token count" → Explains need to create account
- [ ] "show quiz score" → Prompts to take quiz
- [ ] "show lessons available" → Prompts to take quiz

**IVR Commands (Guest):**
- [ ] "show profile status" → Shows tokens (X/100), quiz score, upgrade CTA
- [ ] "show token count" → Shows X/100 tokens
- [ ] "show quiz score" → Shows last score
- [ ] "show lessons available" → Shows free lesson only

**IVR Commands (Member):**
- [ ] "show profile status" → Shows tokens (X/1000), full access confirmation
- [ ] "show token count" → Shows X/1000 tokens this month
- [ ] "show quiz score" → Shows last score
- [ ] "show lessons available" → Shows full course info

**Token Name Customization:**
- [ ] Default shows "tokens"
- [ ] Admin can change to custom name (e.g., "LeSAEp tokens")
- [ ] Custom name appears in all IVR responses

---

## Breaking Changes

None - fully backward compatible with v3.0.7.

---

## Migration Notes

**Upgrading from v3.0.7:**
- Prompt cards now work (were broken in v3.0.7)
- IntroPanel with dismissal added
- IVR commands added
- Token name configurable
- All v3.0.7 features preserved

---

## Known Limitations

1. **Token reset date:** Currently shows placeholder "[billing date]" - requires billing system integration
2. **Lesson count:** Uses default "20+" if not configured in product settings
3. **IntroPanel state:** Not persisted - shows again on page reload (by design)

---

## Future Enhancements

**Potential v3.0.9:**
- localStorage persistence for IntroPanel dismissed state
- "My lessons" command implementation
- Token usage tracking and warnings
- Billing date integration
- Admin settings UI for all IVR messages

---

## Architecture Notes

### IVR vs AI

**IVR Commands (v3.0.8):**
- Processed before AI
- Instant responses
- No API calls
- Always available

**AI Queries:**
- Processed after IVR check fails
- Requires API/token
- Can fail gracefully
- IVR provides fallback

### Command Recognition

Case-insensitive string matching:
```javascript
const lowerMessage = message.toLowerCase();
if (lowerMessage === 'show profile status') { ... }
```

Future: Could expand to fuzzy matching or natural language understanding.

---

## Credits

**Developed:** 2026-01m-09d
**Implemented:** Claude Code Agent
**Date Stamp Format:** Michel Date Stamp Innovation (YYYY-MMm-DDd)
**Testing:** Dainis Michel

---

## Key Lessons

### 1. Self-Documenting is Best UX
X button triggers chat message teaching users about "Show IntroPanel" - users learn by doing.

### 2. IVR Foundation Matters
Simple string matching works. Builds foundation for AI-enhanced responses later.

### 3. Status Transparency Drives Conversion
Showing token count (87/100) creates urgency naturally without pushy messaging.

---

**Bottom Line:** v3.0.8 makes the plugin actually work (fixes v3.0.7 broken cards) and adds self-documenting IntroPanel + IVR command foundation.
