# FLOSC Style Guide v1.0
## Naming Conventions & CSS Architecture

---

## 1. Screen Area Names (Generic → FLOSC → CSS)

| Generic Name | FLOSC Name | CSS Class | Description |
|--------------|------------|-----------|-------------|
| App Container | flosc_app | `.flosc-app` | Root container for entire app |
| Sidebar | flosc_sidebar | `.flosc-sidebar` | Left navigation panel |
| Sidebar Header | flosc_sidebar_header | `.sidebar-header` | Top of sidebar (logo, toggle) |
| Session List | flosc_session_list | `.flosc_app_session_list` | Chat history list |
| Session Item | flosc_session_item | `.session-item` | Individual chat session |
| Main Area | flosc_main | `.flosc-main` | Right side content area |
| Header | flosc_header | `.flosc-header` | Top bar (mobile: hamburger) |
| Chat Container | flosc_chat_container | `.chat-container` | Messages + input wrapper |
| Messages Area | flosc_messages | `.messages` | Scrollable message list |
| Greeting | flosc_greeting | `.greeting` | Welcome screen |
| Message Row | flosc_message | `.message` | Single message container |
| Message Avatar | flosc_avatar | `.message-avatar` | User/assistant icon |
| Message Content | flosc_message_content | `.message-content` | Text wrapper |
| Message Text | flosc_message_text | `.message-text` | Actual message bubble |
| Input Composer | flosc_input_composer | `.flosc_input_composer` | Bottom input area |
| Input Field | flosc_input_field | `.flosc_input_composer_inner` | Text input wrapper |
| Send Button | flosc_send_button | `.flosc-send-btn` | Send message button |
| Suggested Replies | flosc_suggested_replies | `.flosc_output_chat_suggested_replies_scroll` | Autoprompt pills |
| Visitor Intro Panel | floscVisitorIntroPanel | `.intro-panel` | Pre-login prompts |
| Guest Prompt Panel | floscGuestPromptPanel | `.prompt-panel` | Post-login free tier prompts |
| Member Prompt Panel | floscMemberPromptPanel | `.prompt-panel` | Paid user prompts |

> **Note on Panel Naming:**
> - **Internal/CSS names:** `floscVisitorIntroPanel`, `floscGuestPromptPanel`, `floscMemberPromptPanel`
> - **User-facing names:** Users see and type `IntroPanel` (visitors) or `PromptPanel` (guests/members)
> - Commands: `Show IntroPanel`, `Hide IntroPanel`, `Show PromptPanel`, `Hide PromptPanel`

---

## 2. CSS Variable Naming Convention

**Pattern:** `--flosc-{area}-{property}`

### Global Variables
```css
--flosc-bg                    /* Page/app background */
--flosc-text                  /* Primary text color */
--flosc-text-muted            /* Secondary/muted text */
--flosc-border                /* Default border color */
--flosc-accent                /* Primary brand/action color */
--flosc-accent-hover          /* Hover state for accent */
```

### User Message Variables
```css
--flosc-user-message-bg       /* User bubble background */
--flosc-user-message-text     /* User bubble text color */
--flosc-user-message-radius   /* User bubble border-radius */
--flosc-user-avatar-bg        /* User avatar background */
--flosc-user-avatar-text      /* User avatar text/icon color */
```

### Assistant Message Variables
```css
--flosc-assistant-message-bg      /* Assistant bubble background */
--flosc-assistant-message-text    /* Assistant bubble text color */
--flosc-assistant-message-border  /* Assistant bubble border */
--flosc-assistant-message-radius  /* Assistant bubble border-radius */
--flosc-assistant-avatar-bg       /* Assistant avatar background */
--flosc-assistant-avatar-text     /* Assistant avatar text/icon color */
```

### Input Area Variables
```css
--flosc-input-bg              /* Input composer background */
--flosc-input-field-bg        /* Text field background */
--flosc-input-field-border    /* Text field border */
--flosc-input-field-text      /* Text field text color */
--flosc-input-placeholder     /* Placeholder text color */
--flosc-send-btn-bg           /* Send button background */
--flosc-send-btn-text         /* Send button text/icon */
```

### Sidebar Variables
```css
--flosc-sidebar-bg            /* Sidebar background */
--flosc-sidebar-text          /* Sidebar text color */
--flosc-sidebar-hover         /* Sidebar item hover bg */
--flosc-sidebar-active        /* Active session background */
--flosc-sidebar-border        /* Sidebar border color */
```

### Suggested Replies Variables
```css
/* Pills (compact, single-line) */
--flosc-pill-bg               /* Pill button background */
--flosc-pill-text             /* Pill button text */
--flosc-pill-border           /* Pill button border */
--flosc-pill-hover-bg         /* Pill hover background */
--flosc-pill-hover-border     /* Pill hover border */

/* Cards (larger format, 164x164 or credit-card ratio) */
--flosc-card-bg               /* Card background */
--flosc-card-text             /* Card text color */
--flosc-card-border           /* Card border */
--flosc-card-hover-bg         /* Card hover background */
--flosc-card-hover-text       /* Card hover text */
--flosc-card-hover-border     /* Card hover border */
--flosc-card-shadow           /* Card box-shadow */
--flosc-card-hover-shadow     /* Card hover shadow */
```

> **Pills vs Cards:**
> - **Pills** (`.flosc-style-pill`): Compact, single-line, rounded-full (999px radius)
> - **Cards** (`.flosc-style-card`): 164×164 square, or `--wide` variant (~260×164 credit-card ratio)
> - **Carousel**: Activates automatically when content would wrap/stack vertically

### Quiz Modal Variables
```css
--flosc-quiz-prompt-bg        /* Quiz prompt box background */
--flosc-quiz-prompt-border    /* Quiz prompt box border */
--flosc-quiz-sequence-text    /* Sequence number text color */
--flosc-quiz-tab-bg           /* Tab button background */
--flosc-quiz-tab-border       /* Tab button border */
--flosc-quiz-tab-text         /* Tab button text */
--flosc-quiz-tab-active-bg    /* Active tab background */
--flosc-quiz-tab-active-text  /* Active tab text */
--flosc-quiz-input-bg         /* Text input background */
--flosc-quiz-input-border     /* Text input border */
--flosc-quiz-input-focus-border /* Text input focus border */
--flosc-quiz-record-bg        /* Record button background (red) */
--flosc-quiz-record-hover-bg  /* Record button hover */
--flosc-quiz-stop-bg          /* Stop button background */
--flosc-quiz-stop-hover-bg    /* Stop button hover */
--flosc-quiz-waveform-bg      /* Waveform canvas background */
--flosc-quiz-success          /* Passed score color (green) */
--flosc-quiz-warning          /* Failed score color (amber) */
--flosc-quiz-error-bg         /* Error message background */
--flosc-quiz-error-text       /* Error message text */
--flosc-quiz-error-border     /* Error message border */
```

### Content Formatting Variables (inside `.message-text`)
```css
--flosc-content-link          /* Link text color */
--flosc-content-link-hover    /* Link hover color */
--flosc-content-blockquote-border  /* Blockquote left border */
--flosc-content-blockquote-bg      /* Blockquote background */
--flosc-content-blockquote-text    /* Blockquote text color */
--flosc-content-hr            /* Horizontal rule color */
--flosc-content-table-border       /* Table cell borders */
--flosc-content-table-header-bg    /* Table header background */
--flosc-content-table-stripe-bg    /* Table alternating row bg */
--flosc-content-list-marker        /* List bullet/number color */
```

### Panel Variables (floscVisitorIntroPanel, floscGuestPromptPanel, floscMemberPromptPanel)
```css
--flosc-panel-bg              /* Panel background */
--flosc-panel-border          /* Panel border */
--flosc-panel-shadow          /* Panel box-shadow */
--flosc-panel-header-text     /* Header/title text */
--flosc-panel-eyebrow-text    /* Eyebrow label text */
--flosc-panel-close-bg        /* Close button background */
--flosc-panel-close-border    /* Close button border */
--flosc-panel-close-text      /* Close button icon color */
--flosc-panel-close-hover-bg  /* Close button hover background */
--flosc-panel-close-hover-border /* Close button hover border */
```

---

## 3. Message Modifier Classes

| Class | Description |
|-------|-------------|
| `.message.user` | User's sent message |
| `.message.assistant` | AI assistant reply |
| `.message.system` | System notification |
| `.message.error` | Error message |

---

## 4. Theme Data Attributes

Apply to `.flosc-app` or `:root`:

```html
<div class="flosc-app" data-flosc-theme="day">
<div class="flosc-app" data-flosc-theme="night">
```

CSS targeting:
```css
[data-flosc-theme="day"] { ... }
[data-flosc-theme="night"] { ... }
```

---

## 5. Bubble Tail Styles (Reference)

| Style | User Radius | Assistant Radius |
|-------|-------------|------------------|
| Subtle Notch | `18px 18px 4px 18px` | `4px 18px 18px 18px` |
| Classic Triangle | `18px 18px 0 18px` | `0 18px 18px 18px` |
| Modern Curve | `20px 20px 6px 20px` | `6px 20px 20px 20px` |
| Minimal | `16px` | `16px` |
| Sharp Corner | `12px 12px 2px 12px` | `2px 12px 12px 12px` |

---

## 6. File Naming Convention

**Preset files:** `chat-style-{name}.css`

Examples:
- `chat-style-day.css`
- `chat-style-night.css`
- `chat-style-custom.css`

---

## 7. CSS Specificity Rules

1. **flosc-app.css** = Layout ONLY (flexbox, grid, positioning)
2. **chat-style-*.css** = Colors, borders, shadows, typography
3. Use `!important` in preset files to override base styles
4. Prefix all selectors with `.flosc-app` for specificity

---

## 8. Responsive Breakpoints

```css
/* Mobile first */
@media (min-width: 768px) { /* Tablet */ }
@media (min-width: 1024px) { /* Desktop */ }
```

---

## 9. Animation Classes (Future Premium)

Reserved for premium themes:
```css
.flosc-animate-fade
.flosc-animate-slide
.flosc-animate-sparkle
.flosc-animate-bloom
```

---

## 10. Element ID Naming Convention (JavaScript Targets)

**Pattern:** `flosc[Feature][Element]` (camelCase)

IDs are used for JavaScript `getElementById()` lookups. Use clear, unmistakable names:

### ✅ Good Examples (Clear Verbosity)
```
floscQuizRecordButton      ← Not "recordBtn"
floscQuizSubmitTextButton  ← Not "submitTextQuizBtn"
floscQuizTextPanel         ← Not "quizTextPanel"
floscQuizModalClose        ← Not "recordingModalClose"
```

### Quiz Modal IDs (Complete Reference)
| ID | Purpose |
|----|---------|
| `floscQuizModalClose` | Close button for quiz modal |
| `floscQuizSequence` | Displays the sequence to memorize |
| `floscQuizTextPanel` | Text input panel container |
| `floscQuizAudioPanel` | Audio recording panel container |
| `floscQuizResultPanel` | Result display panel |
| `floscQuizTextInput` | Text input field |
| `floscQuizSubmitTextButton` | Submit text answer button |
| `floscQuizRecordButton` | Start recording button |
| `floscQuizStopButton` | Stop recording button |
| `floscQuizSubmitRecordingButton` | Submit recording button |
| `floscQuizRerecordButton` | Re-record button |
| `floscQuizRecordingStatus` | Recording status text |
| `floscQuizRecordingTimer` | Timer display |
| `floscQuizRecordingPlayback` | Playback container |
| `floscQuizRecordingAudio` | Audio element for playback |
| `floscQuizRecordingError` | Error message container |
| `floscQuizWaveformContainer` | Waveform visualization |
| `floscQuizScoreDisplay` | Score percentage display |
| `floscQuizResultMessage` | Result feedback text |
| `floscQuizContinueButton` | Continue after quiz button |

### Naming Rules
1. **Always prefix with `flosc`** - identifies the plugin
2. **Feature name next** - `Quiz`, `Chat`, `Sidebar`, etc.
3. **Element name last** - descriptive, unmistakable
4. **Use `Button` not `Btn`** - clarity over brevity
5. **Match PHP IDs to JS lookups exactly**

---

## 11. Quick Reference: Current CSS Classes

```
.flosc-app
  └─ .flosc-sidebar
  │    └─ .sidebar-header
  │    └─ .flosc_app_session_list
  │         └─ .session-item
  └─ .flosc-main
       └─ .flosc-header
       └─ .chat-container
            └─ .messages
            │    └─ .greeting
            │    └─ .message
            │         └─ .message-avatar
            │         └─ .message-content
            │              └─ .message-text
            └─ .flosc_input_composer
                 └─ .flosc_input_composer_inner
                 └─ .flosc-send-btn
```
