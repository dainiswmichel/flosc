# FLOSC v3.0.3 - Works Out of the Box

**Freeline-Login-Offer-Sale-Content** - A WordPress framework for quiz-based learning and conversational sales funnels.

**Version:** 3.0.3
**Author:** Dainis Michel
**License:** GPL v2 or later
**Requires:** WordPress 5.8+, PHP 7.4+

---

## What's New in v3.0.3?

🎯 **Works Out of the Box** - Complete FLOSC funnel now functional end-to-end!

- ✅ FLOSC_CONFIG now passed to JavaScript (was missing!)
- ✅ Lessons from WordPress posts (dynamic, not hard-coded)
- ✅ Email sent with quiz results + OTO after login
- ✅ Pre-login score capture (survives login redirect)
- ✅ Free lesson delivery based on quiz results
- ✅ Chatbot lock for free users after free lesson
- ✅ Admin settings for Lessons and Email

See [WHATS_NEW_v03_03.md](WHATS_NEW_v03_03.md) for full changelog.

---

## What is FLOSC?

FLOSC is a **conversational sales funnel framework** for WordPress that allows teachers, coaches, and educators to:

1. **Offer a free quiz** to visitors (no login required)
2. **Require login** to see full results
3. **Show personalized recommendations** based on quiz performance
4. **Present upsell offers** (1 free lesson + unlock more)
5. **Accept payments** via Stripe, Tokens, or Affiliate commissions

FLOSC combines:
- **Quiz-based lead generation** (5 quiz types included)
- **Multi-payment monetization** (Stripe, Tokens, Affiliate)
- **Claude.ai-style UI** (clean, conversational interface)
- **Multi-provider AI & STT** (OpenAI, Anthropic, xAI, AssemblyAI, Deepgram, Whisper)

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
**Perfect for:** LeSAEp-style pronunciation analysis

---

## 💰 The SALE System

v03_01 preserves the complete payment and monetization system from v02_09.

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

## Quick Start

### Installation

1. Upload `flosc_v03_01.zip` to WordPress
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

## Usage Examples

### Example 1: Language Learning (Pronunciation)

**Quiz Type:** Pronunciation
**Content:** "one two three four five six seven eight nine ten"
**STT Provider:** AssemblyAI
**Target Language:** English
**Target Accent:** US

**User Experience:**
1. User clicks "Record" and says the numbers
2. STT transcribes their audio
3. System analyzes pronunciation accuracy
4. Shows score + which sounds need practice
5. Recommends specific pronunciation lessons
6. 1 lesson free, unlock more for $9.99

### Example 2: Certification Prep (Multiple Choice)

**Quiz Type:** Multiple Choice
**Content:**
```
What is 2+2?|A) 3|B) 4|C) 5|D) 6|Correct: B
What color is grass?|A) Red|B) Green|C) Blue|D) Yellow|Correct: B
```

**User Experience:**
1. User answers: "B,B"
2. System grades instantly
3. Shows score + explanations
4. Offers full practice test access

### Example 3: Simple Testing (Simple Scoring)

**Quiz Type:** Simple Scoring
**Content:** 1,2,3,4,5,6,7,8,9,10
**Instructions:** "Count from 1 to 10"

**User Experience:**
1. User types: "1,2,3,4,5"
2. System counts matches: 5/10 = 50%
3. Shows which numbers were correct/missed
4. Offers full course access

---

## Architecture

```
flosc_v03_01/
├── flosc.php                           # Main plugin file (v3.0.1)
├── includes/
│   ├── class-quiz-type-factory.php     # ⭐ NEW: Quiz type loader
│   ├── class-ai-provider-factory.php   # AI (IVR, OpenAI, Anthropic, xAI)
│   ├── class-stt-provider-factory.php  # STT (AssemblyAI, Whisper, Deepgram)
│   ├── class-session-manager.php       # Chat persistence
│   ├── class-pronunciation-analyzer.php # Phoneme analysis
│   ├── quiz-types/                     # ⭐ NEW: Quiz type modules
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
│       ├── settings.php                # ⭐ UPDATED: Quiz type selector UI
│       ├── offers.php                  # Offer management
│       └── payments.php                # Provider configuration
├── assets/
│   ├── css/flosc-app.css
│   └── js/flosc-app.js
├── README.md                           # This file
├── WHATS_NEW.md                        # Changelog
└── QUIZ_TYPES.md                       # Quiz type development guide
```

### Data Flow

```
1. User visits /app/
2. Sees quiz interface (based on active quiz type)
3. Submits quiz (text or audio)
4. FLOSC routes to appropriate quiz type
5. Quiz type analyzes input vs expected content
6. Returns score, feedback, lesson recommendations
7. User must login to see full results
8. After login: see results + offers
9. Purchase with Stripe/Tokens/Affiliate
10. Access granted to premium content
```

---

## REST API

### Quiz Endpoints (New in v3.0.1)

#### `POST /wp-json/flosc/v1/process-quiz`
Process text-based quiz submission.

**Request:**
```json
{
  "input": "1,2,3,4,5",
  "quiz_type": "simple_scoring"
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

#### `POST /wp-json/flosc/v1/process-audio` (Updated)
Now routes through quiz type system for audio-based quizzes.

### SALE Endpoints (Preserved from v02_09)

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

## Configuration

### FLOSC → Settings

**Product Tab:**
- App URL slug
- Product name, tagline, emoji
- Primary color
- Google Analytics ID

**AI Provider Tab:**
- Provider selection (IVR, OpenAI, Anthropic, xAI)
- API keys

**Speech-to-Text Tab:**
- Provider selection (AssemblyAI, Whisper, Deepgram, Custom)
- API keys

**Quiz Tab:** ⭐ NEW in v3.0.1
- Quiz type selector
- Quiz content (auto-defaults to appropriate format)
- Quiz-specific settings (case sensitive, separators, etc.)
- Response template editor (4 score ranges)

### FLOSC → Offers

Create purchasable offers:
```php
[
    'id' => 'pro_access',
    'name' => 'Pro Access',
    'type' => 'one_time',

    // Multiple payment methods
    'pricing' => [
        'stripe' => ['price_id' => 'price_xxx'],
        'tokens' => ['cost' => 500],
        'affiliate' => ['credit_amount' => 25],
    ],

    // What they get
    'grants' => [
        'features' => ['quiz', 'all_lessons', 'ai_coach'],
        'access_level' => 'pro',
        'duration_days' => 0,       // 0 = lifetime
    ],
]
```

### FLOSC → Payments

Configure payment providers:
- **Stripe:** API keys, webhook secret
- **Tokens:** Currency name, signup bonus, action costs
- **Affiliate:** Network credentials, categories

---

## Monetization Strategies

### Strategy 1: Traditional SaaS
- Stripe subscriptions (monthly/yearly)
- Token top-ups for overage

### Strategy 2: Pay-Per-Use
- Free signup with token bonus
- Charge tokens per AI query, lesson, etc.
- Sell token packs

### Strategy 3: Affiliate-Funded
- Free access via purchase intent
- User plans to buy laptop → clicks affiliate link → you get commission → user gets access
- Zero out-of-pocket for user

### Strategy 4: Hybrid
- Free tier with limits
- Token purchases for casual users
- Subscription for power users
- Affiliate option for budget-conscious

---

## Extending FLOSC

### Creating Custom Quiz Types

See [QUIZ_TYPES.md](QUIZ_TYPES.md) for full guide.

**Quick Example:**

```php
<?php
class FLOSC_My_Custom_Quiz extends FLOSC_Abstract_Quiz_Type {
    public function get_id() { return 'my_custom'; }
    public function get_name() { return 'My Custom Quiz'; }
    public function get_icon() { return '🎯'; }
    public function get_description() { return 'My custom quiz type'; }

    public function needs_audio() { return false; }
    public function needs_stt() { return false; }
    public function needs_ai_analysis() { return false; }

    public function get_instructions() {
        return 'Enter your answer';
    }

    public function get_default_content() {
        return 'expected_answer';
    }

    public function validate_input($input) {
        if (empty($input)) {
            return new WP_Error('invalid_input', 'Please enter your answer.');
        }
        return true;
    }

    public function analyze($input, $expected_content, $context = []) {
        // Your analysis logic
        $score = ($input === $expected_content) ? 100 : 0;

        return [
            'score' => $score,
            'correct' => $score === 100 ? [$input] : [],
            'incorrect' => $score === 0 ? [$input] : [],
            'response_key' => $this->get_response_key_from_score($score),
            'details' => []
        ];
    }

    public function get_settings_fields() {
        return []; // No custom settings
    }
}
```

Save to `/includes/quiz-types/class-my-custom-quiz.php` and it will auto-load!

### Adding Custom Payment Providers

```php
class My_Custom_Provider extends FLOSC_Payment_Provider {
    public function get_id() { return 'my_provider'; }
    public function get_name() { return 'My Provider'; }
    public function is_configured() { return !empty($this->get_setting('api_key')); }

    public function get_settings_fields() {
        return [
            'api_key' => ['type' => 'password', 'label' => 'API Key'],
        ];
    }

    public function process_payment($user_id, $offer, $payment_data) {
        // Your payment logic
        return ['success' => true, 'transaction_id' => '...'];
    }
}

// Register
add_filter('flosc_payment_providers', function($providers) {
    $providers['my_provider'] = new My_Custom_Provider();
    return $providers;
});
```

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

**FLOSC v3.0.1** - Quiz-based learning and sales funnels made easy. 🚀
