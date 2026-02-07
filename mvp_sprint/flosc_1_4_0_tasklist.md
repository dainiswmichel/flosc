# FLOSC v1.4.0 Task List
## Maximum SSO + Product-Specific Sandboxed Sales

**Created:** 2026-02-06
**Base Version:** 1.4.0 (already has Admin Introspection)
**Target:** Full social login + sandboxed purchase flow for 3 products

---

## THE FLOSC FUNNEL FLOW (What We're Building)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         VISITOR ARRIVES                              │
│                    (flosc_default_ivr.md OR                         │
│                 simplified_solfeggio_ivr.md OR                       │
│                       lesaep_ivr.md)                                 │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                        TAKES QUIZ                                    │
│  • FLOSC Plugin: 1-10 number quiz (text or audio)                   │
│  • Solfeggio: "In C Major, name Do Re Mi Fa Sol La Ti Do"           │
│  • LeSAEp: "Press record, read this sentence" (audio)               │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│               LOGIN TO SEE YOUR SCORE                                │
│                                                                      │
│  "Log in with your email or your favorite social media account      │
│   to get your quiz results!"                                        │
│                                                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐               │
│  │  Google  │ │ Facebook │ │  Apple   │ │ LinkedIn │               │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘               │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐               │
│  │Microsoft │ │ Twitter  │ │  GitHub  │ │ TikTok   │               │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘               │
│  ┌────────────────────────────────────────────────────┐             │
│  │            📧 Continue with Email                  │             │
│  └────────────────────────────────────────────────────┘             │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    USER LOGGED IN (Guest)                           │
│                                                                      │
│  "You scored 7/10! Here's what you missed: {missed_items}"          │
│  "Want to master these? Check out our full course..."               │
│                                                                      │
│  ┌────────────────────────────────────────────────────┐             │
│  │         PRODUCT-SPECIFIC OFFER                     │             │
│  │  - Simplified Solfeggio: $47 (or sandbox: $∞)     │             │
│  │  - LeSAEp Pronunciation: $97 (or sandbox: $∞)     │             │
│  │  - FLOSC Plugin: $197 (or sandbox: $∞)            │             │
│  └────────────────────────────────────────────────────┘             │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                   SANDBOX PURCHASE                                   │
│                                                                      │
│  "Pay whatever you want (it's fake money for testing!)"             │
│                                                                      │
│  [$9.99] [$99] [$999] [$1M] [$1B 🚀]                                │
│                                                                      │
│  → Grants CORRECT floscMemberLevel based on PRODUCT:                │
│    • flosc_plugin_member                                            │
│    • simplified_solfeggio_member                                     │
│    • lesaep_member                                                   │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      MEMBER ACCESS                                   │
│                                                                      │
│  User now has product-specific member level and sees:               │
│  • Member-only IVR messages                                         │
│  • Full lesson access for their product                             │
│  • AI chat with full RAG access                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## PART A: SOCIAL MEDIA LOGIN (Maximum SSO based on BuddyBoss)

### A.1 Core SSO Infrastructure

| Task | Description | Files to Create/Modify |
|------|-------------|----------------------|
| A.1.1 | Create SSO directory structure | `includes/sso/` |
| A.1.2 | Create base provider abstract class | `class-flosc-sso-provider.php` |
| A.1.3 | Create OAuth2 base class (PKCE, state, CSRF) | `class-flosc-sso-oauth2.php` |
| A.1.4 | Create user handler (link social→WP user, auto-login, registration) | `class-flosc-sso-user.php` |
| A.1.5 | Create main SSO orchestrator | `class-flosc-sso.php` |
| A.1.6 | Create persistent storage for OAuth state | `class-flosc-sso-persistent.php` |
| A.1.7 | Create SSO REST API endpoints | REST routes in `flosc.php` |
| A.1.8 | Create admin settings page for SSO | `admin/sso-settings.php` |

### A.2 Provider Implementations (from BuddyBoss baseline + new)

| Provider | BuddyBoss Has | Priority | OAuth Type | Notes |
|----------|--------------|----------|------------|-------|
| **Google** | ✅ Yes | P1 - Critical | OAuth 2.0 | Most common, use as reference |
| **Facebook** | ✅ Yes | P1 - Critical | OAuth 2.0 | High adoption |
| **Apple** | ✅ Yes | P2 - Important | Sign In with Apple | iOS users |
| **LinkedIn** | ✅ Yes | P2 - Important | OAuth 2.0 | Professional audience |
| **Microsoft** | ✅ Yes | P2 - Important | OAuth 2.0 | Enterprise users |
| **Twitter/X** | ✅ Yes | P3 - Nice to have | OAuth 2.0 | Influencer audience |
| **GitHub** | ❌ New | P2 - Important | OAuth 2.0 | Developer audience (FLOSC plugin) |
| **TikTok** | ❌ New | P3 - Nice to have | OAuth 2.0 | Younger audience |
| **Instagram** | ❌ New | P3 - Nice to have | OAuth 2.0 (via Meta) | Creator audience |

#### Provider File Structure
```
includes/sso/providers/
├── google/
│   ├── class-flosc-provider-google.php
│   ├── class-flosc-provider-google-client.php
│   └── google.svg
├── facebook/
│   ├── class-flosc-provider-facebook.php
│   ├── class-flosc-provider-facebook-client.php
│   └── facebook.svg
├── apple/
├── linkedin/
├── microsoft/
├── twitter/
├── github/        # NEW - not in BuddyBoss
├── tiktok/        # NEW - not in BuddyBoss
└── instagram/     # NEW - not in BuddyBoss (uses Meta API)
```

### A.3 Frontend SSO Components

| Task | Description | Files |
|------|-------------|-------|
| A.3.1 | Create SSO button component (renders all enabled providers) | `assets/js/flosc-sso.js` |
| A.3.2 | Create SSO button styles (pill, icon, grid layouts) | `assets/css/flosc-sso.css` |
| A.3.3 | Create popup handler (opens OAuth, handles callback) | Part of `flosc-sso.js` |
| A.3.4 | Create BroadcastChannel handler (popup→parent communication) | Part of `flosc-sso.js` |
| A.3.5 | Add SSO buttons to login prompt in IVR flow | Update `flosc-app.js` |
| A.3.6 | Create "OR" separator component | CSS + JS |

### A.4 SSO Integration with FLOSC Flow

| Task | Description |
|------|-------------|
| A.4.1 | After quiz, show login prompt with social buttons + email option |
| A.4.2 | On successful social login, auto-link to WP user (or create new) |
| A.4.3 | After login, immediately show quiz results |
| A.4.4 | Preserve quiz score/data across the OAuth redirect flow |
| A.4.5 | Show product-specific offer after score reveal |
| A.4.6 | Track social login source in user meta (`_flosc_social_provider`) |

### A.5 SSO Admin Configuration

| Task | Description |
|------|-------------|
| A.5.1 | Admin UI to enable/disable each provider |
| A.5.2 | Admin UI to configure per-provider credentials (Client ID, Secret) |
| A.5.3 | Admin UI to test provider connection |
| A.5.4 | Admin UI to configure button style (pill, icon, grid) |
| A.5.5 | Admin UI to reorder providers |
| A.5.6 | Show redirect URI for each provider (for app setup) |

---

## PART B: PRODUCT-SPECIFIC SANDBOXED SALES

### B.1 Product/Offer Configuration

| Task | Description |
|------|-------------|
| B.1.1 | Define 3 product-specific offers in Offer Manager |
| B.1.2 | Map each offer to its floscMemberLevel |
| B.1.3 | Configure product metadata (name, price, features) |

**Product → Offer → Member Level Mapping:**

| Product | Offer ID | Member Level | IVR File |
|---------|----------|--------------|----------|
| FLOSC Plugin | `flosc_plugin_full` | `flosc_plugin_member` | `flosc_default_ivr.md` |
| Simplified Solfeggio | `simplified_solfeggio_full` | `simplified_solfeggio_member` | `simplified_solfeggio_ivr.md` |
| LeSAEp Pronunciation | `lesaep_full` | `lesaep_member` | `lesaep_ivr.md` |

### B.2 Product-Aware Sandbox Purchase

| Task | Description |
|------|-------------|
| B.2.1 | Update `/flosc/v1/sandbox-purchase` to accept `product_id` |
| B.2.2 | Grant product-specific member level (not generic `flosc_sandbox`) |
| B.2.3 | Track which product was purchased in user meta |
| B.2.4 | Show product-aware success message |

**Updated Sandbox Flow:**
```
POST /flosc/v1/sandbox-purchase
{
  "product_id": "simplified_solfeggio",  // NEW
  "offer_id": "simplified_solfeggio_full",
  "amount": "1,000,000,000"
}

Response:
{
  "success": true,
  "member_level": "simplified_solfeggio_member",  // Product-specific
  "transaction_id": "sandbox_123_1707234567_4321"
}
```

### B.3 Product-Specific Quiz Content

| Task | Description |
|------|-------------|
| B.3.1 | Create/configure Solfeggio quiz: "Name Do Re Mi Fa Sol La Ti Do in C Major" |
| B.3.2 | Create/configure LeSAEp audio quiz: "Read this sentence out loud" |
| B.3.3 | Ensure FLOSC Plugin quiz uses 1-10 number quiz |
| B.3.4 | Map quiz scores to product-specific lessons |

### B.4 IVR Flow Updates

| Task | Description |
|------|-------------|
| B.4.1 | Update each IVR to show product-specific offer after login |
| B.4.2 | Add sandbox purchase action to offer messages |
| B.4.3 | Add post-purchase member messages to each IVR |
| B.4.4 | Test conditions: `first_message_after_purchase`, `is_member` |

### B.5 Access Verification

| Task | Description |
|------|-------------|
| B.5.1 | Verify member level grants correct content access |
| B.5.2 | Verify non-members see offer, not content |
| B.5.3 | Verify cross-product isolation (Solfeggio member can't access LeSAEp content) |

---

## PART C: ADMIN INTROSPECTION ENHANCEMENTS (Already in v1.4.0)

| Task | Description |
|------|-------------|
| C.1 | ✅ DONE: Basic introspection (files, offers, system, flows) |
| C.2 | Add SSO provider status to introspection |
| C.3 | Add product/offer mapping to introspection |
| C.4 | Add user's social login provider to "current config" |

---

## IMPLEMENTATION ORDER

### Phase 1: Sandboxed Sales (Do First - Fastest Path to Testing)
1. B.1.1-B.1.3: Define product offers
2. B.2.1-B.2.4: Update sandbox purchase endpoint
3. B.4.1-B.4.4: Update IVR flows
4. B.5.1-B.5.3: Test access

**Why first?** Email login already works. We can test the full purchase flow without SSO.

### Phase 2: Core SSO Infrastructure
1. A.1.1-A.1.8: Build SSO infrastructure
2. A.3.1-A.3.6: Frontend components
3. A.5.1-A.5.6: Admin settings

### Phase 3: Priority 1 Providers (Google + Facebook)
1. A.2 Google: Full implementation
2. A.2 Facebook: Full implementation
3. A.4.1-A.4.6: Integrate with FLOSC flow

### Phase 4: Priority 2 Providers
1. Apple Sign In
2. LinkedIn
3. Microsoft
4. GitHub (NEW)

### Phase 5: Priority 3 Providers (Nice to Have)
1. Twitter/X
2. TikTok (NEW)
3. Instagram (NEW)

---

## FILES TO CREATE/MODIFY

### New Files (SSO)
```
includes/sso/
├── class-flosc-sso.php                    # Main orchestrator (~500 lines)
├── class-flosc-sso-provider.php           # Abstract base (~300 lines)
├── class-flosc-sso-oauth2.php             # OAuth2 base (~400 lines)
├── class-flosc-sso-user.php               # User linking (~600 lines)
├── class-flosc-sso-persistent.php         # State storage (~100 lines)
├── providers/
│   ├── google/class-flosc-provider-google.php
│   ├── google/class-flosc-provider-google-client.php
│   ├── facebook/class-flosc-provider-facebook.php
│   ├── facebook/class-flosc-provider-facebook-client.php
│   ├── apple/...
│   ├── linkedin/...
│   ├── microsoft/...
│   ├── twitter/...
│   ├── github/...
│   ├── tiktok/...
│   └── instagram/...
admin/
├── sso-settings.php                       # Admin SSO config page
assets/
├── js/flosc-sso.js                        # Frontend SSO (~300 lines)
├── css/flosc-sso.css                      # SSO button styles
```

### Modified Files
```
flosc.php                                  # REST routes, SSO loader
assets/js/flosc-app.js                     # Login prompt with SSO buttons
includes/sale/class-sale-manager.php       # Product-aware sandbox
includes/sale/class-offer-manager.php      # Product offers
ai_configuration_files/*.md                # IVR flow updates
```

---

## ESTIMATED EFFORT

| Part | Tasks | Estimated Lines | Complexity |
|------|-------|-----------------|------------|
| A (SSO) | 30+ tasks | ~4,000-5,000 | High |
| B (Sandbox) | 15 tasks | ~500-800 | Medium |
| C (Introspection) | 3 tasks | ~100 | Low |

**Total:** ~45-50 tasks, ~5,000-6,000 new lines of code

---

## DEPENDENCIES / PREREQUISITES

1. **Google Cloud Console** - Need project with OAuth credentials
2. **Meta Developer Portal** - Need App with Facebook Login + Instagram
3. **Apple Developer** - Need Sign In with Apple configured
4. **LinkedIn Developer** - Need OAuth app
5. **Microsoft Azure AD** - Need app registration
6. **Twitter Developer** - Need OAuth 2.0 app
7. **GitHub Developer** - Need OAuth app (NEW)
8. **TikTok Developer** - Need Login Kit (NEW)

---

## SUCCESS CRITERIA

### Sandbox Sales Ready
- [ ] Visitor takes product-specific quiz
- [ ] Prompted to login (email works) to see score
- [ ] Score revealed after login
- [ ] Product-specific offer shown
- [ ] Sandbox purchase grants correct member level
- [ ] Member sees product-specific content

### SSO Ready
- [ ] Google login works end-to-end
- [ ] Facebook login works end-to-end
- [ ] At least 6 providers fully implemented
- [ ] Popup flow works (no page reload needed)
- [ ] Social account links to WP user correctly
- [ ] Quiz data preserved across OAuth flow
- [ ] Admin can configure providers in WP admin

---

## NOTES

- **BuddyBoss baseline is ethical** - User owns the plugin and has contributed code to BuddyBoss
- **Maximize not minimize** - Full-featured SSO, not a stripped-down version
- **Product isolation** - Each product has its own member level, not shared access
- **Sandbox first** - Test the full flow with email before adding SSO complexity
