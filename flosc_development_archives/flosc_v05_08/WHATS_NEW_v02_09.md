# FLOSC v0.2.9 - The SALE Release

**Release Date:** January 8, 2026

## 🎯 The Big Picture

v02.08 made FLOSC a configurable framework. v02.09 makes the **S** in FLOSC real.

**SALE is no longer a price field.** It's a complete monetization system supporting:
- Multiple payment providers
- Multiple offer types
- Usage-based billing
- Token economies
- Permission-based affiliate funding

## 🆕 New: Complete SALE System

### Payment Providers

**💳 Stripe Provider**
- One-time payments via PaymentIntents
- Recurring subscriptions
- Auto customer creation
- Webhook handling (payment success, subscription changes)
- Test/Live mode toggle

**🪙 Token Provider**
- Internal credit/currency system
- Signup bonuses
- Referral bonuses
- Configurable action costs
- Full transaction ledger
- Token pack purchases (via Stripe)

**🎁 Affiliate Provider** (Permission-Based Purchase Intent)
The successor to ADZ.world:
- User declares what they plan to buy
- System finds affiliate offers (Amazon, CJ, ShareASale, custom)
- User makes planned purchase through affiliate link
- Commission credits their account
- Credits unlock FLOSC offers

**User gets free access. You get commission. They buy what they planned anyway.**

### Offer Management

New admin page: **FLOSC → Offers**

Create offers with:
- Multiple pricing options (Stripe price, token cost, affiliate credit)
- Access grants (features, levels, duration)
- Token pack amounts
- Display badges

Offer types:
- `one_time` - Single purchase, lifetime or fixed duration
- `subscription` - Recurring via Stripe
- `tokens` - Credit pack purchase
- `hybrid` - Subscription + token allocation

### Usage Tracking

Track and limit:
- AI queries
- STT minutes  
- Quiz attempts
- Lesson views
- Custom events

Features:
- Per-user limits
- Period-based tracking (monthly)
- Quota checking
- Analytics aggregation

### Access Manager

Granular control:
- Feature flags (`quiz`, `all_lessons`, `ai_coach`, etc.)
- Access levels (`visitor`, `free`, `basic`, `pro`, `premium`)
- Offer-based grants
- Subscription status
- Expiration dates

## 📁 New Files

```
includes/sale/
├── class-sale-manager.php          # Main orchestrator
├── class-offer-manager.php         # Offer CRUD
├── class-usage-tracker.php         # Metered billing
├── class-access-manager.php        # Access control
├── class-payment-provider.php      # Abstract interface
└── providers/
    ├── class-stripe-provider.php   # Cards + subscriptions
    ├── class-token-provider.php    # Internal credits
    └── class-affiliate-provider.php # Purchase intent system

templates/admin/
├── settings.php                    # General settings
├── offers.php                      # Offer management UI
└── payments.php                    # Provider config UI
```

## 🔧 New REST Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /offers` | List available offers |
| `POST /purchase` | Process multi-provider purchase |
| `POST /create-payment-intent` | Stripe payment setup |
| `GET /tokens` | Token balance + ledger |
| `POST /intents` | Declare purchase intent |
| `GET /intents/{id}/offers` | Get affiliate matches |
| `POST /webhooks/{provider}` | Payment callbacks |

## 🚀 Migration from v02.08

1. Backup your site
2. Deactivate v02.08
3. Upload v02.09
4. Activate
5. Go to **FLOSC → Payments** to configure Stripe
6. Go to **FLOSC → Offers** to set up your offers
7. Remove any hard-coded price references

**Note:** The old `flosc_product_price` and `flosc_currency` options are no longer used. Pricing now comes from offers and Stripe.

## 💡 Example Configurations

### Basic Course Sale
```
Offer: Full Access
Type: one_time
Stripe Price: price_xxx (€144)
Grants: access_level=pro, features=[all_lessons,ai_coach]
```

### Freemium + Tokens
```
Offer: Free Trial (auto-granted)
Grants: features=[quiz,free_lesson], usage_limits=[ai_queries:10]

Offer: 100 Tokens
Type: tokens
Stripe Price: price_yyy (€9)
Tokens: 100

Token Costs: ai_query=1, lesson=5
```

### Affiliate-Funded Access
```
Offer: Pro Access
Affiliate Credit: $25

User flow:
1. User wants Pro Access
2. Declares "buying new headphones"
3. Clicks Amazon affiliate link
4. Buys $150 headphones
5. You earn ~$7.50 commission
6. After 4 purchases, user has $30 credit
7. User "buys" Pro Access for $25 credit
8. Everyone wins
```

## ⚠️ Breaking Changes

- Removed: `flosc_product_price` option
- Removed: `flosc_currency` option  
- Removed: Hard-coded payment intent amount
- Changed: `FLOSC_Access_Control` → `FLOSC_Access_Manager` (in sale/ directory)

The old `class-access-control.php` is replaced by the comprehensive `sale/class-access-manager.php`.

## 🔮 Next Steps (v02.10+)

- Admin dashboard with revenue analytics
- Email notifications (purchase, subscription renewal)
- Coupon/discount codes
- A/B testing for offers
- Multi-currency support
- Subscription pause/resume

---

**The S in FLOSC finally means something.**
