# FLOSC Funnel System

Created successfully! 

## What You Have

A complete, production-ready FLOSC (Freeline → Login → Offer → Sale → Content) funnel system for your LeSAEp course.

## Directory Structure

```
flosc-funnel-system/
├── app/                          # Backend (FastAPI)
│   ├── main.py                  # Main application
│   ├── config.py                # Configuration management
│   ├── models.py                # Pydantic models
│   ├── database.py              # Supabase integration
│   ├── routers/                 # API endpoints
│   │   ├── freeline.py         # Quiz submission
│   │   ├── auth.py             # Authentication
│   │   ├── offer.py            # Email offers
│   │   ├── payment.py          # Stripe integration
│   │   └── content.py          # Protected content
│   ├── services/               # Business logic
│   │   ├── whisper_service.py  # Speech-to-text
│   │   ├── phoneme_matcher.py  # Phoneme analysis
│   │   └── email_service.py    # Brevo emails
│   └── utils/
│       └── csv_loader.py       # Load course data
├── frontend/                    # Frontend (HTML/JS)
│   ├── index.html              # Landing/Quiz page
│   ├── css/styles.css
│   └── js/freeline.js
├── data/                        # Your course content (CSV)
│   ├── product.csv
│   ├── targeted_lessons.csv
│   └── magic_sentences.csv
├── static/lessons/             # Video/PDF files
├── scripts/
│   └── deploy.sh               # One-command deployment
├── requirements.txt
├── .env.example
├── DEPLOYMENT.md
└── README.md
```

## Key Features

### ✅ Freeline (Free Quiz)
- Text chatbot welcome
- 3-attempt "No" blocking
- Audio recording (MediaRecorder API)
- Faster-whisper transcription
- Phoneme comparison & flagging

### ✅ Login (Supabase Auth)
- Magic link authentication
- No password needed
- Secure, scalable

### ✅ Offer (Automated Emails)
- Personalized evaluation
- 1-2 free lessons based on errors
- 24-hour OTO with countdown
- Reminder emails at 12hr & 23hr
- Brevo integration (300/day free)

### ✅ Sale (Stripe)
- Dynamic checkout links
- Webhook processing
- Automatic user upgrade

### ✅ Content (Protected Dashboard)
- Free lessons for all
- Full course for paid users
- Progress tracking
- Video + PDF delivery

## CSV-Driven Content

All course content is managed via 3 simple CSV files:

**1. product.csv** - Pricing & product info
**2. targeted_lessons.csv** - Lesson library
**3. magic_sentences.csv** - Quiz sentences

Update CSVs → Upload → Restart → Done!

## Zero Ongoing Costs (Except Stripe Fees)

- Supabase: FREE (500MB, 50K users)
- Brevo: FREE (300 emails/day)
- Stripe: No monthly fee (just 2.9% + $0.30/transaction)
- DigitalOcean: $6/month (basic droplet)

## Quick Start (Local Development)

```bash
cd flosc-funnel-system

# Setup
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Configure
cp .env.example .env
nano .env  # Add your API keys

# Run
uvicorn app.main:app --reload

# Visit http://localhost:8000
```

## Deploy to Production

```bash
# One command deploy
chmod +x scripts/deploy.sh
sudo ./scripts/deploy.sh yourdomain.com
```

See `DEPLOYMENT.md` for full details.

## Next Steps for Launch

1. **Get API Keys:**
   - Supabase: https://supabase.com (sign up, create project)
   - Brevo: https://www.brevo.com (free account)
   - Stripe: https://stripe.com (create account)

2. **Customize Content:**
   - Edit `data/product.csv` with your pricing
   - Add lessons to `data/targeted_lessons.csv`
   - Update magic sentences in `data/magic_sentences.csv`

3. **Upload Your Videos:**
   - Place lesson videos in `static/lessons/`
   - Match filenames to CSV `video_url` column

4. **Test Locally:**
   - Run development server
   - Complete a quiz
   - Check email delivery
   - Test payment flow

5. **Deploy:**
   - Get DigitalOcean droplet ($6/mo)
   - Point domain to droplet
   - Run deploy script
   - Configure Stripe webhooks

## How Users Experience It

1. **Land on site** → Chatbot asks for free analysis
2. **Record 3 sentences** → AI analyzes pronunciation
3. **Enter email** → Get magic link
4. **Login** → See evaluation + 1-2 free lessons
5. **Email arrives** → Personalized offer (75% off, 24hr)
6. **Click to buy** → Stripe checkout
7. **Payment complete** → Access full course dashboard

## Creator Benefits

- **No coding needed** to update content (just edit CSVs)
- **Personalized at scale** (AI analyzes each student)
- **High conversion** (urgency + personalization)
- **Automated** (set and forget)
- **Scalable** (handle thousands of users)
- **Profitable** (minimal costs, high margins)

## Technical Highlights

- Modern Python (FastAPI, async)
- Self-hosted AI (faster-whisper)
- Free-tier services only
- Production-ready security
- Mobile-responsive frontend
- Real-time audio recording
- Phoneme-level analysis
- Automated email sequences

Ready to launch LeSAEp.com! 🚀
