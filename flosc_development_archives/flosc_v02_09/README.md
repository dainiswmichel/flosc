# FLOSC v0.2.9 - Complete SALE System

**Freeline-Login-Offer-Sale-Content** - A WordPress framework for conversational AI sales funnels with multi-payment support.

## The SALE System

v02.09 introduces a complete, extensible payment and monetization system. No more hard-coded prices.

### Payment Providers

| Provider | Description | Use Case |
|----------|-------------|----------|
| **Stripe** | Cards, Apple Pay, subscriptions | Traditional payments |
| **Tokens** | Internal credit system | Pay-per-use, gamification |
| **Affiliate** | Permission-based purchase intent | Free access via affiliate commissions |

### How Each Provider Works

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

### Offers System

Offers define what users can purchase:

```php
[
    'id' => 'pro_access',
    'name' => 'Pro Access',
    'type' => 'one_time',           // one_time, subscription, tokens, hybrid
    
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

### Usage Tracking

Metered billing and limits:
- Track: AI queries, STT minutes, quizzes, lesson views
- Set limits per user/tier
- Automatic quota checking
- Upgrade prompts when limits hit

### Access Control

Granular access based on:
- Purchased offers
- Subscription status
- Feature flags
- Token balance
- Expiration dates

Levels: `visitor` → `free` → `basic` → `pro` → `premium`

## Architecture

```
flosc/
├── flosc.php                           # Main plugin
├── includes/
│   ├── class-ai-provider-factory.php   # AI (IVR, OpenAI, Anthropic, xAI)
│   ├── class-stt-provider-factory.php  # STT (AssemblyAI, Whisper, Deepgram)
│   ├── class-session-manager.php       # Chat persistence
│   ├── class-pronunciation-analyzer.php # Quiz analysis
│   └── sale/
│       ├── class-sale-manager.php      # SALE orchestrator
│       ├── class-offer-manager.php     # Offer CRUD
│       ├── class-usage-tracker.php     # Metered billing
│       ├── class-access-manager.php    # Access control
│       ├── class-payment-provider.php  # Provider interface
│       └── providers/
│           ├── class-stripe-provider.php
│           ├── class-token-provider.php
│           └── class-affiliate-provider.php
├── templates/
│   ├── flosc-app.php                   # Main app UI
│   └── admin/
│       ├── settings.php                # General settings
│       ├── offers.php                  # Offer management
│       └── payments.php                # Provider configuration
└── assets/
    ├── css/flosc-app.css
    └── js/flosc-app.js
```

## REST API

### Core Endpoints
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/flosc/v1/ai-query` | POST | AI chat with usage tracking |
| `/flosc/v1/process-audio` | POST | STT + pronunciation analysis |
| `/flosc/v1/sessions` | GET/POST | Chat session management |

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
| `/flosc/v1/webhooks/{provider}` | POST | Payment webhooks |

## Configuration

### 1. General Settings (FLOSC → Settings)
- Product name, tagline, branding
- AI provider + API keys
- STT provider + API keys
- Quiz configuration

### 2. Offers (FLOSC → Offers)
- Create/edit purchasable offers
- Set pricing per provider
- Configure access grants

### 3. Payment Providers (FLOSC → Payments)
- Stripe: API keys, webhook secret
- Tokens: Names, signup bonus, costs
- Affiliate: Network credentials, categories

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

## Example: LeSAEp Configuration

```php
// Offers
- Free Quiz (free)
- Full Access (€144 Stripe OR 1000 tokens OR $50 affiliate credit)
- Monthly Pro (€19/month Stripe subscription)
- 500 Tokens (€29 Stripe)

// Token Costs
- AI query: 1 token
- Quiz attempt: 0 tokens (free)
- Lesson view: 5 tokens

// Signup Bonus: 10 tokens (enough for 10 AI queries)
// Referral Bonus: 25 tokens
```

## Adding Custom Payment Providers

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

## Requirements

- WordPress 5.8+
- PHP 7.4+
- SSL (required for Stripe)

## License

GPL v2 or later

---

**FLOSC** - Because the SALE shouldn't be an afterthought.
