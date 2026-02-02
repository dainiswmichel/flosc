# FLOSC v9.5.7 - Stable Release

**Freeline-Login-Offer-Sale-Content** - A WordPress framework for quiz-based learning and conversational sales funnels.

**Version:** 9.5.7
**Status:** ✅ PRODUCTION READY - Flush permalinks fixed, security hardened
**Author:** Dainis Michel
**License:** GPL v2 or later
**Requires:** WordPress 5.8+, PHP 7.4+

---

## 🆕 What's New in v9.5.7

### 🔧 Critical Fixes
- **Flush Permalinks button** — Fixed "link expired" error (uses admin-post.php handler)
- **Security hardening** — HMAC-signed cookies, rate-limited public endpoints
- **Modal CSS** — Restored modal system CSS lost in v9.3.9 refactor

### 🎨 Clean CSS Architecture
- **flosc-layout.css** - Structure only (flexbox, grid, positioning)
- **flosc-theme.css** - Variable consumption with backward-compatible selectors
- **chat-style-light.css / chat-style-dark.css** - Variable definitions only (43 lines each)

### ✅ Backward Compatibility
- Supports both legacy class names (HTML/JS) and new `flosc-` prefixed names
- No breaking changes to existing HTML or JavaScript
- Grouped CSS selectors: `.flosc-message-user, .message.user { }`

### 🛡️ Bulletproof PHP
- Safe file loading with `file_exists()` checks
- Error suppression with `@file_get_contents()`
- Graceful fallbacks when files missing
- Always attaches inline styles to valid handle

### 🎛️ Enhanced Admin Settings
- **Theme Preset**: Auto (system preference), Light, Dark
- **Bubble Style**: 5 options with live preview
- **Accent Color**: Color picker + quick swatches
- **Font Family**: System, Inter, IBM Plex, Roboto, Fira Code
- **Text Scale**: 100-150%
- **Custom CSS**: Full override capability

---

## Previous Versions
- **True/False** - Quick assessment statements

---

## 🎯 What Works Out of the Box (v9.3.4)

### ✅ FULLY FUNCTIONAL - No AI Required

**Complete Sales Funnel:**
1. **Quiz System** - User types "1,2,3,4,5,6,7,8,9,10" for 100% score
2. **Free Lesson** - System picks ONE random lesson from missed numbers
3. **Offer Trigger** - Automatic OTO after free lesson delivery
4. **Purchase Flow** - Token/Stripe/ClickBank payment processing
5. **Member Access** - Automatic unlock of all 10 posts on purchase

**WordPress Integration:**
- Searches `flosc_sample_data` category ✅
- Post meta: `_flosc_lesson_number` (1-10) ✅
- Post meta: `_flosc_access_level` (visitor/guest/member) ✅
- Content filtering by `<!--more-->` tag ✅

**IVR Message System:**
- Import/export messages ✅
- Add/replace modes ✅
- Individual message editing ✅
- Condition builder ✅

### ⚙️ OPTIONAL - AI Integration

**RAG System (works without AI too):**
- WordPress post search (works) ✅
- AI conversational layer (optional) ⚙️
- Anthropic Claude API (optional) ⚙️

**You can test the complete funnel WITHOUT configuring AI!**

---

## 🚀 Quick Start

### 1. Create Sample Data
```bash
wp eval-file wp-content/plugins/flosc_v9_1_9/admin/create-sample-data.php
```
This creates 10 WordPress posts titled "1" through "10" in `flosc_sample_data` category.

### 2. Configure Quiz
- Go to: FLOSC → Settings → Quiz
- Set correct answer: `1,2,3,4,5,6,7,8,9,10`
- Save

### 3. Create Offer
- Go to: FLOSC → Settings → Offers
- Create offer: "Full Access to All 10 Lessons"
- Trigger: Quiz Completed
- Condition: `score < 100`
- Price: $49 (or use tokens for testing)

### 4. Test Complete Flow
1. Go to `/app/` on your site
2. Take quiz: type "4,7,9" (30% score)
3. System delivers ONE free lesson (e.g., #8)
4. OTO offer appears
5. Purchase using tokens (testing)
6. Verify member access to all 10 posts

---
**Author:** Dainis Michel
**License:** GPL v2 or later
**Requires:** WordPress 5.8+, PHP 7.4+

---

## The 5 Phases

FLOSC guides users through a structured journey:

- **Freeline:** Visitor (not logged in) - Goal: Encourage them to take the quiz
- **Login:** Post-quiz visitors + Logged-in users - Goal: Deliver free lesson, present offer
- **Offer:** Sales pitch - Goal: Encourage purchase
- **Sale:** Post-purchase - Goal: Onboard to content
- **Content:** Ongoing access - Goal: Support and encourage

---

## What's New in v8.0.1?

🎯 **Production Ready** - First stable 8.x release with fully functional IVR system and unified changelog.

**Critical Fix:**
- Fixed admin settings fatal error (`FLOSC_IVR_Manager` → `FLOSC_IVR_Parser`)

**Documentation:**
- Consolidated 30+ changelog files into one WHATS_NEW.md
- Adopted proper versioning convention (8.0.1, not 08.00)
- Adopted proper directory naming (flosc_v8_0_1, not flosc_v08_00)

See [WHATS_NEW.md](WHATS_NEW.md) for full changelog from v2.0.9 through v8.0.1.

---

## What is FLOSC?

FLOSC is a **conversational sales funnel framework** for WordPress that connects AI-powered chat interfaces with quiz-based learning and multi-provider payment systems. Built for teachers, coaches, and educators who want to convert visitors into paying customers through personalized, interactive experiences.

**Key Features:**

- **AI-Powered Conversations** - Connect OpenAI, Anthropic, or xAI for intelligent responses
- **Markdown-Based IVR** - Configure conditional messaging via simple markdown format
- **5 Quiz Types** - Simple Scoring, True/False, Multiple Choice, Word Matching, Pronunciation
- **Multi-Provider Payments** - Stripe, Token economy, Affiliate commissions
- **Speech-to-Text** - AssemblyAI, Whisper, Deepgram integration for pronunciation analysis
- **Complete REST API** - Extensible architecture for custom quiz types and payment providers

---

## 🎯 Quiz Types

### ✍️ Simple Scoring
**Use Case:** Testing, counting, simple answer matching
**Example:** User enters "1,2,3,4,5" for a 1-10 quiz → 50% score
**Requires:** Text input only (no audio/STT)
**Perfect for:** Quick validation, number tests, comma-separated lists

### ✓✗ True/False
**Use Case:** Knowledge checks, fact verification
**Example:** "The sky is blue. | True"
**Requires:** Text input only
**Perfect for:** Quick assessments, comprehension checks

### ☑️ Multiple Choice
**Use Case:** Classic quizzes with 2-4 options
**Format:** "Question? | A) Option 1 | B) Option 2 | Correct: A"
**Requires:** Text input only
**Perfect for:** Traditional testing, certification prep

### 🔗 Word Matching
**Use Case:** Vocabulary, classification, pairing
**Format:** "cat:mammal\ndog:mammal\nfish:aquatic"
**Requires:** Text input only
**Perfect for:** Language learning, category matching

### 🎤 Pronunciation
**Use Case:** Language learning, accent coaching, speech therapy
**Requires:** Audio recording + STT provider
**Perfect for:** Pronunciation analysis with phoneme-level feedback

---

## 💰 The SALE System

### Payment Providers

| Provider | Description | Use Case |
|----------|-------------|----------|
| **Stripe** | Cards, Apple Pay, subscriptions | Traditional payments |
| **Tokens** | Internal credit system | Pay-per-use, gamification |
| **Affiliate** | Permission-based purchase intent | Free access via affiliate commissions |

#### 💳 Stripe Provider
- Create products/prices in Stripe Dashboard
- Configure Price IDs in FLOSC Offers
- Supports one-time payments AND subscriptions
- Webhook handling for payment confirmation
- Auto-creates Stripe Customers, manages subscriptions

#### 🪙 Token Provider
Internal credit economy:
- **Earn tokens**: Signup bonus, referrals, affiliate commissions
- **Spend tokens**: Unlock offers, pay for metered features
- Configurable costs per action (AI query, STT minute, lesson view)
- Full ledger tracking

#### 🎁 Affiliate Provider (Permission-Based Purchase Intent)
The spiritual successor to ADZ.world:

1. User declares: "I'm planning to buy a laptop"
2. System searches affiliate networks (Amazon, CJ, ShareASale, custom)
3. User clicks affiliate link, makes their planned purchase
4. Commission ($50-200 on electronics) flows back
5. User earns credits toward free FLOSC access

**Win-win-win:**
- User gets free access to your product
- You get affiliate commission
- User buys what they planned to buy anyway

---

## 🤖 AI-Powered IVR System

FLOSC's IVR (Interactive Voice Response) system delivers contextual messages based on:

- **User Phase** - Freeline, Login, Offer, Sale, Content
- **Conditions** - Quiz score, login status, purchase history, event flags
- **Variable Replacement** - {name}, {score}, {product_name}, custom variables
- **Message Types** - Auto-triggered, suggested replies with actions, offers with timers
- **Event Flags** - justCompletedQuiz, justLoggedIn, justPurchased for first-time messaging

Configure everything in `ai_configuration_files/ivr.md` using simple markdown format. The system evaluates complex boolean logic (&&, ||, !, ()) to deliver the right message at the right time.

---

## Quick Start

### Installation

1. Upload `flosc_v8_0_1.zip` to WordPress
2. Activate the plugin
3. Go to **FLOSC → Settings**

### Basic Configuration (5 Minutes)

#### Step 1: Product Settings
```
Product Name: Your Product Name
Tagline: Your tagline
Logo Emoji: 🎯
Primary Color: #4f46e5
```

#### Step 2: Choose a Quiz Type
```
Quiz Type: Simple Scoring (or any other)
Quiz Content: 1,2,3,4,5,6,7,8,9,10
```

**Done!** Visit `yoursite.com/app/` to test your quiz.

#### Optional: AI Configuration

For intelligent conversational responses:
```
AI Provider: OpenAI (or Anthropic, xAI)
API Key: [your key]
```

#### Optional: Audio/STT Configuration

For pronunciation or audio-based quizzes:
```
STT Provider: AssemblyAI
AssemblyAI API Key: [your key]
```

#### Optional: Payment Configuration

Go to **FLOSC → Offers** to create purchasable offers.
Go to **FLOSC → Payments** to configure Stripe/Tokens/Affiliate.

---

## Architecture

```
flosc_v8_0_1/
├── flosc.php                           # Main plugin file (v8.0.1)
├── includes/
│   ├── class-quiz-type-factory.php     # Quiz type loader
│   ├── class-ai-provider-factory.php   # AI (IVR, OpenAI, Anthropic, xAI)
│   ├── class-stt-provider-factory.php  # STT (AssemblyAI, Whisper, Deepgram)
│   ├── class-session-manager.php       # Chat persistence
│   ├── class-ivr-parser.php            # IVR markdown parser
│   ├── class-condition-evaluator.php   # Boolean condition evaluation
│   ├── class-pronunciation-analyzer.php # Phoneme analysis
│   ├── quiz-types/                     # Quiz type modules
│   │   ├── abstract-quiz-type.php      # Base class
│   │   ├── class-simple-scoring-quiz.php
│   │   ├── class-truefalse-quiz.php
│   │   ├── class-multiplechoice-quiz.php
│   │   ├── class-wordmatching-quiz.php
│   │   └── class-pronunciation-quiz.php
│   └── sale/
│       ├── class-sale-manager.php      # SALE orchestrator
│       ├── class-offer-manager.php     # Offer CRUD
│       ├── class-usage-tracker.php     # Metered billing
│       ├── class-access-manager.php    # Access control
│       └── providers/
│           ├── class-stripe-provider.php
│           ├── class-token-provider.php
│           └── class-affiliate-provider.php
├── templates/
│   ├── flosc-app.php                   # Main app UI
│   └── admin/
│       ├── settings.php                # Admin settings page
│       ├── ivr-settings.php            # IVR configuration
│       ├── ai-config.php               # AI provider setup
│       ├── ai-knowledge-base.php       # AI files manager
│       ├── offers.php                  # Offer management
│       └── payments.php                # Provider configuration
├── assets/
│   ├── css/
│   │   ├── flosc-app.css              # Main app styles
│   │   └── ivr-admin.css              # Admin IVR styles
│   └── js/
│       ├── flosc-app.js               # Frontend IVR engine (v8.0.1)
│       └── ivr-admin.js               # Admin IVR editor
├── ai_configuration_files/
│   └── ivr.md                          # IVR message configuration
├── prompts/
│   ├── freeline-prompt.md              # Phase-specific AI prompts
│   ├── login-prompt.md
│   ├── offer-prompt.md
│   ├── sale-prompt.md
│   └── content-prompt.md
├── README.md                           # This file
└── WHATS_NEW.md                        # Consolidated changelog
```

---

## REST API

### IVR Endpoints (New in v7.0.9)

#### `POST /wp-json/flosc/v1/ivr/track`
Track message display for persistent "shown" state.

**Request:**
```json
{
  "message_id": "welcome_visitor"
}
```

#### `GET /wp-json/flosc/v1/ivr/messages`
Get all IVR messages for current user context.

### Quiz Endpoints

#### `POST /wp-json/flosc/v1/process-quiz`
Process text-based quiz submission.

**Request:**
```json
{
  "input": "1,2,3,4,5",
  "quiz_type": "flosc_sample_text_based_quiz"
}
```

**Response:**
```json
{
  "success": true,
  "analysis": {
    "score": 50,
    "correct": ["1", "2", "3", "4", "5"],
    "incorrect": [],
    "response_key": "31-60"
  },
  "lessons": [],
  "message": "Score: 50%\n\nGood effort!..."
}
```

#### `POST /wp-json/flosc/v1/process-audio`
Process audio-based quiz submission with STT.

### SALE Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/flosc/v1/offers` | GET | List available offers |
| `/flosc/v1/purchase` | POST | Process purchase |
| `/flosc/v1/create-payment-intent` | POST | Stripe PaymentIntent |
| `/flosc/v1/access` | GET | Check user access |
| `/flosc/v1/tokens` | GET | Token balance + ledger |
| `/flosc/v1/intents` | POST | Declare purchase intent |
| `/flosc/v1/intents/{id}/offers` | GET | Get affiliate offers |
| `/flosc/v1/referral` | GET | Generate referral link |

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- SSL certificate (required for audio recording and Stripe)

---

## License

GPL v2 or later

FLOSC is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.

---

## Credits

**Developed by:** Dainis Michel
**Built with:** Claude Sonnet 4.5
**Inspired by:** Claude.ai, LeSAEp, ADZ.world

---

**FLOSC v8.0.1** - WordPress framework for conversational sales funnels. 🚀
