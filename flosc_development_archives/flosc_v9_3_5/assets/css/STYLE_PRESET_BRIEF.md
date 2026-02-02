# FLOSC Chat Style Preset Design Brief

**Project:** FLOSC WordPress Plugin - AI Chat Interface
**Task:** Create 2 professional chat style presets
**Deadline:** January 23, 2026

---

## CONTEXT

FLOSC is a WordPress plugin that provides an AI-powered chat interface for educational/sales funnels. The current style presets look amateurish and need professional redesign.

**See screenshot reference:** The current "FLOSC default" theme uses an ugly orange/tan background that looks unprofessional.

---

## YOUR DELIVERABLES

Create **2 CSS preset files** following this naming convention:
- `chat-style-{YOUR_MODEL}_01.css`
- `chat-style-{YOUR_MODEL}_02.css`

Example for GPT: `chat-style-gpt_01.css`, `chat-style-gpt_02.css`
Example for Grok: `chat-style-grok-pro_01.css`, `chat-style-grok-pro_02.css`

---

## TECHNICAL REQUIREMENTS

### File Structure
Each preset MUST define these CSS custom properties:

```css
/**
 * {Model Name} Professional Style 01
 * {Brief description of the aesthetic}
 * Created by: {Model Name}
 * Date: January 23, 2026
 */

[data-flosc-style-preset="{your-preset-id}"] {
    /* === BACKGROUNDS === */
    --flosc-bg: #ffffff;                    /* Main chat background */
    --flosc-header-bg: #ffffff;             /* Header bar background */
    --flosc-input-bg: #ffffff;              /* Message input area background */
    
    /* === TEXT COLORS === */
    --flosc-assistant-text: #1a1a1a;        /* AI message text color */
    --flosc-user-text: #ffffff;             /* User message text color */
    --flosc-muted-text: #666666;            /* Secondary/timestamp text */
    --flosc-heading-text: #111111;          /* Heading text color */
    
    /* === USER BUBBLE === */
    --flosc-user-bubble-bg: #0066ff;        /* User message bubble background */
    --flosc-user-bubble-radius: 18px;       /* Bubble corner radius */
    
    /* === ASSISTANT MESSAGE === */
    --flosc-assistant-bubble-bg: transparent; /* Usually transparent or subtle */
    --flosc-assistant-border: none;         /* Optional border */
    
    /* === ACCENTS & UI === */
    --flosc-accent: #0066ff;                /* Primary accent color */
    --flosc-accent-hover: #0052cc;          /* Accent hover state */
    --flosc-button-bg: #0066ff;             /* Primary button background */
    --flosc-button-text: #ffffff;           /* Primary button text */
    --flosc-border-color: #e5e7eb;          /* General border color */
    --flosc-shadow: 0 2px 8px rgba(0,0,0,0.08); /* Subtle shadows */
    
    /* === INPUT FIELD === */
    --flosc-input-border: #d1d5db;          /* Input field border */
    --flosc-input-focus-border: #0066ff;    /* Input field focus border */
    --flosc-input-placeholder: #9ca3af;     /* Placeholder text color */
    
    /* === SPACING (optional overrides) === */
    --flosc-message-padding: 12px 16px;     /* Message bubble padding */
    --flosc-gap: 16px;                      /* Gap between messages */
}
```

### Additional Styles (Optional but Encouraged)
You can add additional selectors for deeper customization:

```css
/* Example: Custom scrollbar */
[data-flosc-style-preset="{your-preset-id}"] .flosc-messages::-webkit-scrollbar {
    width: 6px;
}

/* Example: Custom send button */
[data-flosc-style-preset="{your-preset-id}"] .flosc-send-btn {
    /* your styles */
}

/* Example: Avatar styling */
[data-flosc-style-preset="{your-preset-id}"] .flosc-avatar {
    /* your styles */
}
```

---

## DESIGN GUIDELINES

### DO:
- ✅ Use **professional, modern color palettes** (think Linear, Vercel, Stripe, Notion)
- ✅ Ensure **high contrast** for readability (WCAG AA minimum)
- ✅ Use **subtle shadows** for depth, not harsh borders
- ✅ Make user messages **clearly distinguishable** from assistant messages
- ✅ Keep it **clean and minimal** - chat interfaces should feel effortless
- ✅ Consider **both light AND dark variants** if you want (01 = light, 02 = dark)

### DON'T:
- ❌ Use orange, tan, or beige backgrounds (looks dated/unprofessional)
- ❌ Use low-contrast text (gray on gray)
- ❌ Use harsh borders or boxy designs
- ❌ Use overly saturated/neon colors
- ❌ Make it look like a 2010 web app

---

## INSPIRATION REFERENCES

**Best-in-class chat UIs:**
- ChatGPT: Clean white, subtle gray assistant area, teal accents
- Linear: Purple/indigo accents, clean white, excellent typography
- Vercel: Black/white with subtle purple accents
- Intercom: Modern SaaS feel, gradient accents
- Stripe: Professional blue accents, clean hierarchy

**Color Palette Suggestions:**

**Modern Blue:**
- Background: #ffffff
- User bubble: #2563eb (indigo-600)
- Accent: #3b82f6 (blue-500)

**Sophisticated Purple:**
- Background: #fafafa
- User bubble: #7c3aed (violet-600)
- Accent: #8b5cf6 (violet-500)

**Professional Teal:**
- Background: #ffffff  
- User bubble: #0d9488 (teal-600)
- Accent: #14b8a6 (teal-500)

**Elegant Dark:**
- Background: #18181b (zinc-900)
- User bubble: #3b82f6 (blue-500)
- Text: #fafafa

---

## HTML STRUCTURE FOR REFERENCE

```html
<div class="flosc-app" data-flosc-style-preset="your-preset-id">
    <header class="flosc-header">
        <span class="flosc-logo">FLOSC</span>
        <button class="flosc-share-btn">Share</button>
    </header>
    
    <div class="flosc-messages">
        <div class="flosc-message flosc-message-assistant">
            <img class="flosc-avatar" src="..." />
            <div class="flosc-message-content">
                <p>Welcome! How can I help you today?</p>
            </div>
        </div>
        
        <div class="flosc-message flosc-message-user">
            <div class="flosc-message-content">
                <p>What will I learn?</p>
            </div>
        </div>
    </div>
    
    <div class="flosc-input-area">
        <button class="flosc-voice-btn">🎤</button>
        <input type="text" placeholder="Message FLOSC..." />
        <button class="flosc-send-btn">➤</button>
    </div>
</div>
```

---

## SUBMISSION

Save your files to:
```
/Users/dainismichel/2026/flosc/flosc_v9_3_5/assets/css/
```

Name them:
- `chat-style-{model}_01.css`
- `chat-style-{model}_02.css`

---

## EVALUATION CRITERIA

| Criteria | Weight |
|----------|--------|
| Professional appearance | 30% |
| Readability & contrast | 25% |
| Modern/current aesthetic | 20% |
| Consistency & polish | 15% |
| Creativity & distinction | 10% |

---

**Good luck. Make it beautiful.**
