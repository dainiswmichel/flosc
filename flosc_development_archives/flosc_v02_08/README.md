# FLOSC - Freeline-Login-Offer-Sale-Content

A WordPress framework for building conversational AI sales funnels. Create quiz-based lead magnets with AI chat, audio recording, pronunciation analysis, and in-chat Stripe payments.

## Version 0.2.8

**Framework Edition** - Fully configurable via WordPress admin. No hard-coded product names.

## Features

### Core Framework
- **Configurable Product Settings** - Name, tagline, emoji/logo, primary color, pricing
- **Virtual Page System** - Clean URLs at `/your-slug/` with no theme interference
- **Three User States** - Visitor → Free → Paid with appropriate UI for each
- **Session Persistence** - Chat history saved to user meta, grouped by date

### AI Chat
- **Multi-Provider Support**
  - IVR (Scripted responses - FREE, no API cost)
  - OpenAI GPT-4o-mini (~$0.005/interaction)
  - Anthropic Claude (~$0.003/interaction)  
  - xAI Grok
- **Response Caching** - Transients reduce API costs by ~50%
- **Automatic Fallback** - Falls back to IVR if API fails

### Audio Recording & STT
- **Browser Recording** - MediaRecorder API with waveform visualization
- **Multi-Provider STT**
  - AssemblyAI (Recommended - best accent handling, ~$0.0025/10s)
  - OpenAI Whisper (~$0.001/10s)
  - Deepgram (~$0.00072/10s)
  - Custom endpoint (self-hosted)
- **Pronunciation Analysis** - Item-level scoring with lesson mapping

### Payments
- **In-Chat Stripe Elements** - Card input inside modal, no redirects
- **Test/Live Mode Toggle** - Configure both sets of keys
- **Webhook Handler** - Automatic access grant on successful payment
- **BuddyBoss Integration** - Auto-add to paid group

### Growth & Analytics
- **Referral System** - Unique links with tracking cookies
- **Google Analytics 4** - Funnel event tracking built-in
- **Login Gate** - Captures visitors after 2 interactions

## Installation

1. Upload `flosc` folder to `/wp-content/plugins/`
2. Activate in WordPress admin
3. Go to **FLOSC** in admin menu
4. Configure your product settings

## Configuration Tabs

### Product Tab
- App URL slug (e.g., `lesaep` → yoursite.com/lesaep/)
- Product name, tagline, emoji/logo
- Primary brand color
- Price and currency
- Share text for referrals
- Google Analytics ID

### Stripe Tab
- Test/Live mode toggle
- Publishable and Secret keys for both modes
- Webhook signing secret
- Webhook URL displayed for easy copying

### AI Provider Tab
- Select provider (IVR, OpenAI, Anthropic, xAI)
- API keys for each provider
- Cost estimates displayed

### Speech-to-Text Tab
- Select provider (AssemblyAI, OpenAI Whisper, Deepgram, Custom)
- API keys for each
- Custom endpoint URL for self-hosted STT

### Quiz Tab
- Quiz mode (Counting 1-10, Custom sentence, None)
- Expected response text
- Recording instructions

### Access Control Tab
- BuddyBoss paid group ID (optional)

## REST API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/flosc/v1/ai-query` | POST | No | Send message, get AI response |
| `/flosc/v1/process-audio` | POST | No | Upload audio, get transcript + analysis |
| `/flosc/v1/sessions` | GET | Yes | Get user's chat sessions |
| `/flosc/v1/sessions` | POST | Yes | Create new session |
| `/flosc/v1/sessions/{id}` | GET | Yes | Get session with messages |
| `/flosc/v1/sessions/{id}/messages` | POST | Yes | Add message to session |
| `/flosc/v1/access` | GET | No | Check user access level |
| `/flosc/v1/referral` | GET | Yes | Generate referral link |
| `/flosc/v1/create-payment-intent` | POST | Yes | Create Stripe PaymentIntent |
| `/flosc/v1/stripe-webhook` | POST | No | Handle Stripe webhooks |

## Funnel Flow

```
Visitor lands → Sees landing page with prompts
    ↓
Clicks "Start free quiz" → Chat appears
    ↓
AI prompts to record → Recording modal opens
    ↓
User records audio → STT transcribes
    ↓
Pronunciation analyzed → Score + feedback shown
    ↓
Free lesson offered → Demonstrates value
    ↓
Upgrade CTA → In-chat Stripe payment
    ↓
Payment succeeds → Full access granted
    ↓
User shares referral link → Viral growth
```

## Cost Analysis (Per User Acquisition)

| Component | Provider | Cost |
|-----------|----------|------|
| AI Chat (3 messages) | IVR | $0.00 |
| AI Chat (3 messages) | OpenAI | $0.015 |
| STT (10s recording) | AssemblyAI | $0.0025 |
| STT (10s recording) | Deepgram | $0.00072 |

**Cheapest stack:** IVR + Deepgram = ~$0.001/user  
**Recommended stack:** IVR + AssemblyAI = ~$0.0025/user  
**Full AI stack:** OpenAI + AssemblyAI = ~$0.02/user

## €1,000/Day Profit Target

At €144 price point:
- **7 sales/day** = €1,008/day
- **210 sales/month** = €30,240/month

Conversion funnel (conservative):
- 1,000 visitors/day
- 30% take quiz = 300
- 20% complete quiz = 60
- 15% convert = 9 sales

**Focus areas for optimization:**
1. Quiz completion rate (reduce drop-offs)
2. Upsell conversion (A/B test CTAs)
3. Referral virality (k-factor > 0.3)

## Requirements

- WordPress 5.8+
- PHP 7.4+
- SSL certificate (required for Stripe)
- BuddyBoss (optional, for group-based access)

## First Implementation: LeSAEp

Configure FLOSC for the LeSAEp pronunciation course:

1. **Product Name:** LeSAEp
2. **Tagline:** Your AI pronunciation coach for Standard American English
3. **Emoji:** 🎯
4. **Primary Color:** #4f46e5
5. **Price:** €144.00 EUR
6. **Quiz Mode:** Counting (1-10)
7. **Quiz Instructions:** Please count from 1 to 10 clearly and at a normal pace.

## License

GPL v2 or later

## Credits

Created by Dainis Michel for the FLOSC framework ecosystem.
