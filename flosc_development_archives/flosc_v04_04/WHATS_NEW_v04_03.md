# What's New in FLOSC v04_03

**Release Date:** January 9, 2026
**Version:** 4.0.3

## Major Feature: IVR Admin Interface & Phase-Aware Messaging System

This release introduces a comprehensive **Interactive Voice Response (IVR)** admin interface that allows you to configure intelligent, phase-aware chatbot messages across the entire FLOSC funnel.

---

## Key Features

### 1. FLOSC Phase-Aware Messaging

The chatbot now intelligently delivers messages based on the user's current position in the FLOSC funnel:

- **Freeline Phase** (Visitor) - Goal: Get them to take the quiz
- **Login Prompt Phase** (Post-Quiz, Not Logged In) - Goal: Get them to create an account
- **Login Phase** (Logged-In User) - Goal: Present the offer
- **Offer Phase** (Sales Pitch) - Goal: Get them to purchase
- **Sale Phase** (Post-Purchase) - Goal: Onboard to content
- **Content Phase** (Ongoing Access) - Goal: Support and encourage

### 2. Four Message Types

**Initial Messages:**
- First message shown when entering a phase
- Celebration/greeting for phase transition
- Supports variable replacement: `{name}`, `{score}`, `{product_name}`, `{price}`, `{product_description}`

**Sequential Messages:**
- Ordered list of messages delivered in sequence
- Delivered after initial message
- Perfect for structured onboarding flows

**Pool Messages (Conditional Random):**
- Messages randomly selected from eligible pool
- Conditional eligibility based on:
  - Message count (e.g., "show after 5 messages")
  - Session time (e.g., "show after 5 minutes")
  - Inactivity time (e.g., "show after 30 seconds idle")
- Never pure random - always condition-based

**Triggered Messages:**
- Fire on specific conditions with optional actions
- Actions available:
  - `open_quiz` - Open the quiz modal
  - `open_login` - Open the login gate
  - `open_payment` - Open the payment modal
  - `warn_close` - Show chat close warning
- Perfect for proactive engagement

### 3. Inactivity Handling

- Automatic detection of user inactivity
- Escalating prompts based on inactivity duration
- Inactivity timers pause while user is typing
- Configurable warning before chat close

### 4. Rich Text Support

- Markdown parsing with **marked.js v11.1.1**
- Supports:
  - **Bold** and *italic* text
  - Links: `[text](url)`
  - Lists (bulleted and numbered)
  - Code blocks
  - Paragraphs and line breaks

### 5. Admin Interface

Navigate to **FLOSC > IVR Configuration** in WordPress admin to:

- Configure messages for all 6 FLOSC phases
- Set conditions for pool and triggered messages
- Enable/disable individual messages
- Add actions to triggered messages
- Preview conditions with visual tags
- Vertical scrollable layout for easy navigation

---

## Technical Implementation

### New Files

**Backend:**
- `includes/class-ivr-manager.php` - IVR configuration manager
  - Database schema via WordPress options table
  - Default configuration with stock messages
  - AJAX endpoints for save/load
  - Sanitization and validation
  - Frontend config preparation

**Admin Interface:**
- `templates/admin/ivr-settings.php` - Admin page template
- `assets/css/ivr-admin.css` - Admin styling
- `assets/js/ivr-admin.js` - Admin interactions

**Frontend:**
- Integrated IVR engine in `assets/js/flosc-app.js`

### New Methods in flosc-app.js

- `determineFLOSCPhase()` - Determines current user phase
- `startIVR()` - Initializes IVR and shows initial message
- `replaceIVRVariables()` - Variable replacement in messages
- `onUserInteraction()` - Called on each user message
- `evaluateConditions()` - Checks if message conditions are met
- `checkTriggeredMessages()` - Fires eligible triggered messages
- `checkPoolMessages()` - Selects random from eligible pool
- `performIVRAction()` - Executes actions (open_quiz, etc.)
- `scheduleInactivityChecks()` - Sets timers for inactivity
- `transitionToPhase()` - Moves to new FLOSC phase

### Phase Transition Hooks

Phase transitions are automatically triggered on:

1. **Quiz Completion** → Transitions to `login_prompt` phase
   - Sets `localStorage` flag for quiz completion
   - Triggered when visitor completes quiz

2. **User Login (Post-Quiz)** → Transitions to `login` phase
   - Triggered in `checkPendingQuizResults()`
   - Shows welcome message with quiz score

3. **Upgrade Offer Shown** → Transitions to `offer` phase
   - Triggered in `showUpgradeOffer()`
   - Presents sales pitch via chat

4. **Purchase Complete** → Transitions to `sale` phase
   - Triggered on successful payment
   - Begins post-purchase onboarding

5. **Funnel Complete** → Transitions to `content` phase
   - For returning users with full access
   - Ongoing support and encouragement

### Typing Detection

- Input event listener updates `ivr.lastInteraction` timestamp
- Inactivity timers check elapsed time since last interaction
- Effectively pauses timers while user is typing

---

## Database Schema

Configuration stored in WordPress options table as JSON:

```php
'flosc_ivr_config' => [
    'freeline' => [
        'initial_message' => '...',
        'sequential_messages' => [],
        'pool_messages' => [
            [
                'text' => '...',
                'conditions' => ['message_count' => 5],
                'enabled' => true
            ]
        ],
        'triggered_messages' => [
            [
                'text' => '...',
                'conditions' => ['inactivity_seconds' => 30],
                'action' => 'open_quiz',
                'enabled' => true
            ]
        ]
    ],
    // ... 5 more phases
]
```

---

## Default Configuration Highlights

### Freeline Phase (Visitor)
- Initial: Friendly greeting and quiz invitation
- Pool: Engagement questions based on message count
- Triggered: Quiz prompts after inactivity, close warnings

### Login Prompt Phase (Post-Quiz)
- Initial: Encourage account creation to see results
- Pool: Benefits of creating account
- Triggered: Login gate after delay

### Login Phase (Logged-In)
- Initial: Welcome with quiz score
- Sequential: Personalized results and value proposition
- Pool: Contextual questions about needs

### Offer Phase (Sales)
- Initial: Product pitch with price
- Sequential: Features, benefits, testimonials
- Pool: Objection handlers

### Sale Phase (Post-Purchase)
- Initial: Congratulations and next steps
- Sequential: Onboarding instructions

### Content Phase (Returning User)
- Initial: Welcome back message
- Pool: Encouragement and support

---

## Upgrade Instructions

1. **Backup your site** before upgrading
2. Upload `flosc_v04_03.zip` via WordPress Plugins > Add New > Upload
3. Activate the plugin
4. Navigate to **FLOSC > IVR Configuration** to customize messages
5. Test the chatbot flow in different phases

---

## Breaking Changes

None. This is a feature addition with backward compatibility.

---

## Known Limitations

- IVR messages do not yet support AI-generated responses
- Admin interface does not yet support drag-and-drop reordering
- No A/B testing functionality yet
- Markdown rendering is client-side only

---

## Future Enhancements (Planned)

- AI-powered message suggestions
- A/B testing for message effectiveness
- Analytics dashboard for message performance
- Drag-and-drop message ordering
- Message templates library
- Conditional logic builder (visual)
- Integration with CRM systems

---

## Developer Notes

### Extending the IVR System

**Add a new condition type:**

```javascript
// In evaluateConditions() method
if (conditions.my_custom_condition !== undefined) {
    const myValue = this.getMyCustomValue();
    if (myValue !== conditions.my_custom_condition) {
        return false;
    }
}
```

**Add a new action type:**

```javascript
// In performIVRAction() method
case 'my_custom_action':
    this.myCustomActionMethod();
    break;
```

**Add a new variable replacement:**

```javascript
// In replaceIVRVariables() method
.replace(/{my_variable}/g, this.getMyVariable())
```

### Filters and Hooks

The IVR system respects existing WordPress hooks and provides clean integration points for custom extensions.

---

## Credits

- **Developed by:** Dainis Michel
- **Framework:** FLOSC (Freeline-Login-Offer-Sale-Content)
- **Markdown Parser:** marked.js v11.1.1
- **Inspiration:** Claude.ai, ChatGPT, Grok conversational UX patterns

---

## Support

For issues, questions, or feature requests, please contact support or file an issue in the project repository.

---

## Version History

- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface & Phase-Aware Messaging
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction & Prompt Card Flow
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch
