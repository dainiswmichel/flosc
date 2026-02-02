# FLOSC Project Summary
## Past → Present → Future

**Document Purpose:** Onboarding document for new Claude instances to continue FLOSC development
**Last Updated:** 2026-01-11
**Current Version:** 5.0.8

---

# PART 1: THE PAST

## What is FLOSC?

**FLOSC** = **F**reeline → **L**ogin → **O**ffer → **S**ale → **C**ontent

A WordPress plugin framework that creates conversational AI sales funnels. Think "ChatGPT meets landing page meets payment system."

## The Problem It Solves

Traditional sales funnels are:
- Static landing pages that feel impersonal
- Forms that capture leads but don't engage
- Paywalls that feel abrupt

FLOSC creates a **conversational journey** where:
1. Visitors chat with an AI assistant
2. Take a quiz (no login required)
3. Must log in to see full results
4. Get 1 free lesson based on their weak areas
5. See personalized upsell offer
6. Pay to unlock everything

## Origin Story

FLOSC was extracted from **LeSAEp** (Learn Standard American English Pronunciation) - a pronunciation teaching app built by Dainis Michel. The sales funnel pattern worked so well it was abstracted into a reusable framework.

## Version History

| Version | Date | Major Changes |
|---------|------|---------------|
| v02.04 | 2026-01-07 | Domain landing mode, text quiz, Stripe |
| v02.06 | 2026-01-07 | Full AI app shell (Claude-style UI) |
| v02.07 | 2026-01-07 | Audio recording + STT |
| v02.08 | 2026-01-08 | Framework conversion (configurable) |
| v02.09 | 2026-01-09 | SALE system (multi-payment providers) |
| v03.01 | 2026-01-09 | Security audit fixes |
| v03.03 | 2026-01-09 | "Works out of box" |
| v03.04-09 | 2026-01-10 | UX fixes, architectural refactoring |
| v04.01-09 | 2026-01-10 | IVR system, phase-aware AI |
| v05.01-06 | 2026-01-10 | Admin menu unification |
| v05.07 | 2026-01-11 | Phase logic bug fixes |
| v05.08 | 2026-01-11 | Security fixes (XSS, cookies), meta key fixes |

## Key Architectural Decisions

### 1. Virtual App Route (not shortcode)
```php
// Creates /app/ or /lesaep/ route
add_rewrite_rule('^' . $slug . '/?$', 'index.php?flosc_app=1', 'top');
```
The entire app lives at a single URL, not embedded in pages.

### 2. IVR System (scripted fallback)
When no AI API key is configured, FLOSC runs in "IVR mode" - scripted responses based on keywords. This means the funnel works even without paying for AI.

### 3. Multi-Provider Architecture
- **AI:** OpenAI, Anthropic, xAI, or IVR (free)
- **STT:** AssemblyAI, Deepgram, Whisper
- **Payments:** Stripe, Tokens, Affiliate

### 4. Phase-Aware AI
The AI's personality/instructions change based on funnel phase:
- **Freeline:** Helpful, inviting to try quiz
- **Login:** Show results, encourage signup
- **Offer:** Present value proposition
- **Sale:** Handle objections, close
- **Content:** Teaching mode

### 5. FLOSC_USER State
```javascript
window.FLOSC_USER = {
    id: 123,
    name: "John",
    state: "free", // visitor|free|paid
    quizScore: 85,
    freeLessonDelivered: true,
    offerShown: true,
    purchased: false,
    onboarded: false,
    funnelCompleted: false
};
```

---

# PART 2: THE PRESENT

## Current State: v05.08

### File Structure
```
flosc_v05_08/
├── flosc.php                    # Main plugin (1640 lines)
├── includes/
│   ├── class-ai-provider-factory.php
│   ├── class-stt-provider-factory.php
│   ├── class-ivr-manager.php
│   ├── class-session-manager.php
│   ├── class-lesson-manager.php
│   ├── class-quiz-type-factory.php
│   ├── quiz-types/              # 5 quiz types
│   └── sale/                    # Payment system
│       ├── class-sale-manager.php
│       ├── class-offer-manager.php
│       ├── class-access-manager.php
│       ├── class-usage-tracker.php
│       └── providers/
│           ├── class-stripe-provider.php
│           ├── class-token-provider.php
│           └── class-affiliate-provider.php
├── templates/
│   ├── flosc-app.php            # Frontend app shell
│   └── admin/
│       └── settings.php         # Unified admin settings
├── assets/
│   ├── js/flosc-app.js          # Frontend app (1650 lines)
│   └── css/flosc-app.css
├── prompts/                     # Phase-specific AI prompts
└── ai_orientation_files/        # Custom knowledge files
```

### Admin Settings (9 tabs)
1. **Product** - Name, tagline, logo, colors
2. **IVR Messages** - Phase-specific welcome messages
3. **AI Configuration** - Provider selection, API keys
4. **Quiz** - Quiz type selection, content
5. **Email** - SMTP settings, templates
6. **AI Knowledge** - Upload custom .md files
7. **Offers** - Products and pricing
8. **Payments** - Stripe configuration
9. **Lessons** - WordPress post integration

### REST API Endpoints
```
GET  /flosc/v1/offers          # List available offers
GET  /flosc/v1/lessons         # List lessons
GET  /flosc/v1/lessons/free    # Get recommended free lesson
POST /flosc/v1/ai-query        # Chat with AI
POST /flosc/v1/process-audio   # STT transcription
POST /flosc/v1/process-quiz    # Analyze quiz response
POST /flosc/v1/purchase        # Initiate purchase
POST /flosc/v1/store-score     # Save pre-login score
POST /flosc/v1/funnel-complete # Mark funnel done
POST /flosc/v1/mark-offer-shown
POST /flosc/v1/mark-onboarded
POST /flosc/v1/test-ai         # Admin: test AI connection
POST /flosc/v1/webhooks/stripe # Stripe webhooks
```

### Security Measures
- Rate limiting on cost endpoints (AI, STT)
- Stripe webhook signature verification
- Nonce verification on admin actions
- Permission callbacks on all REST routes

### What Works
✅ Complete 5-phase funnel (Freeline → Content)
✅ 5 quiz types with scoring
✅ Multi-provider AI and STT
✅ Stripe payments with webhooks
✅ Token economy
✅ WordPress lesson integration
✅ Pre-login score capture
✅ Email notifications
✅ Phase-aware AI prompts
✅ IVR fallback mode
✅ Admin configuration UI

### Known Limitations
- Offers tab is read-only (no CRUD UI yet)
- AI Knowledge file management needs polish
- No subscription billing management UI
- No analytics dashboard

---

# PART 3: THE FUTURE

## Immediate Priorities (Pre-Launch)

### 1. Test Full Funnel End-to-End
- [ ] Visitor arrives → sees IntroPanel → clicks card
- [ ] Takes quiz → gets score
- [ ] Login gate appears → logs in
- [ ] Score email sent → OTO presented
- [ ] Free lesson delivered
- [ ] Paywall enforced
- [ ] Stripe payment works
- [ ] Content unlocked

### 2. LeSAEp Deployment
The first production deployment will be at **lesaep.com** for English pronunciation teaching.

**LeSAEp-specific needs:**
- 47 SAE phoneme quiz items
- Pronunciation quiz type with audio
- Lessons mapped to phonemes
- Custom AI knowledge files

### 3. Complete Admin UI
- [ ] Offers CRUD (create/edit/delete offers)
- [ ] Lesson ordering/assignment UI
- [ ] Email template editor
- [ ] Analytics/funnel visualization

## Post-Launch Roadmap

### Phase 1: Monetization Polish
- Subscription management UI
- Token purchase flow
- Affiliate tracking dashboard
- Revenue reporting

### Phase 2: Engagement
- Progress tracking
- Streaks/gamification
- Push notifications
- Mobile app wrapper

### Phase 3: Scale
- Multi-site support
- White-label capability
- API for third-party integrations
- Marketplace for quiz types

## Business Model

**Target:** €1,000/day profit

**Revenue Streams:**
1. **LeSAEp subscriptions** - Pronunciation courses
2. **FLOSC licenses** - Sell framework to other educators
3. **Korboc.lv** - Choir management SaaS (separate product)

**Pricing Ideas:**
- LeSAEp: €47 one-time or €9.99/month
- FLOSC: €297 one-time + €47/year support

---

# PART 4: TECHNICAL REFERENCE

## Key Classes

### FLOSC_Framework (flosc.php)
Main plugin class. Handles:
- WordPress hooks
- REST API registration
- Route handling
- Frontend rendering

### FLOSC_AI_Provider_Factory
Abstracts AI providers:
```php
$factory = new FLOSC_AI_Provider_Factory();
$response = $factory->get_response($message, $system_prompt, $context);
```

### FLOSC_Sale_Manager
Payment orchestration:
```php
$sale = new FLOSC_Sale_Manager();
$sale->get_provider('stripe')->create_checkout($offer_id, $user_id);
```

### FLOSC_IVR_Manager
Phase-based message configuration:
```php
$ivr = new FLOSC_IVR_Manager();
$config = $ivr->get_phase_config('offer');
```

## Frontend State Machine

```javascript
class floscApp {
    constructor() {
        this.state = 'visitor'; // visitor|free|paid
        this.user = window.FLOSC_USER || {};
        this.ivr = {
            phase: this.determineFLOSCPhase(),
            initialMessageShown: false,
            messageCount: 0
        };
    }
    
    determineFLOSCPhase() {
        if (this.user.purchased) return 'content';
        if (this.state === 'visitor') {
            return localStorage.getItem('flosc_quiz_taken') ? 'login' : 'freeline';
        }
        if (!this.user.offerShown) return 'login';
        if (!this.user.purchased) return 'offer';
        if (!this.user.onboarded) return 'sale';
        return 'content';
    }
}
```

## Database Schema

FLOSC uses WordPress user_meta and options, not custom tables:

**User Meta:**
- `_flosc_access_level` (free|basic|pro|premium)
- `_flosc_token_balance`
- `_flosc_last_quiz_score`
- `_flosc_free_lesson_delivered`
- `_flosc_offer_shown`
- `_flosc_onboarded`
- `_flosc_funnel_completed`

**Options:**
- `flosc_app_slug`
- `flosc_product_name`
- `flosc_ai_provider`
- `flosc_openai_key`
- `flosc_stripe_*`
- `flosc_ivr_config`

## Deployment Checklist

1. Upload plugin ZIP via WP admin
2. Activate plugin
3. Go to FLOSC → Product, configure name/colors
4. Go to FLOSC → AI Configuration, add API key
5. Go to FLOSC → Payments, configure Stripe
6. Go to FLOSC → Quiz, set quiz content
7. Create lesson posts, assign to FLOSC
8. Visit /app/ (or configured slug)
9. Test full funnel

---

# PART 5: COMMUNICATION NOTES

## Working with Dainis

**Do:**
- Be direct and efficient
- Show code, not just explanations
- Fix bugs completely before moving on
- Test assumptions before implementing

**Don't:**
- Use condescending language
- Ask permission before searching/fixing
- Create duplicate entry points for same settings
- Add unnecessary complexity

## Code Style Preferences

- WordPress coding standards
- Clear variable names
- Comments for non-obvious logic
- Version comments (e.g., `// v05_07: Fixed phase logic`)

## Project Context

Dainis is a 54-year-old composer and music educator based in Vienna, working on his doctoral dissertation while building multiple tech products. FLOSC is part of his "Success Planning 2026" goal of €1,000/day profit.

---

# APPENDIX: QUICK START FOR NEW CLAUDE

```
1. User will upload flosc_v05_08.zip (or newer)
2. Extract to /home/claude/flosc_vXX_XX/
3. Review WHATS_NEW_vXX_XX.md for recent changes
4. Check this summary for architecture context
5. Ask user what they want to work on
6. Make changes, test, create new version
7. Package as flosc_vXX_XX.zip
8. Present files to user
```

**Common Tasks:**
- "Hard core review" = security + bug audit with code fixes
- "Deploy to lesaep" = configure for pronunciation teaching
- "Add feature X" = implement and integrate
- "Fix bug" = find root cause, fix all instances

**Key Files to Check:**
- `flosc.php` - Main plugin, REST routes, handlers
- `assets/js/flosc-app.js` - Frontend state machine
- `templates/flosc-app.php` - App shell HTML
- `templates/admin/settings.php` - Admin UI
