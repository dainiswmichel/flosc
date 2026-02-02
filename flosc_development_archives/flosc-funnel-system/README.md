# FLOSC Funnel System for Online Courses

## Overview
A complete Freeline → Login → Offer → Sale → Content funnel system designed for zero ongoing costs (except Stripe fees). Perfect for language courses and other skills-based online education.

## System Architecture

### Components
1. **Freeline**: Text chatbot + audio recording → speech analysis
2. **Login**: Supabase Auth (magic link)
3. **Offer**: Automated personalized emails (Brevo) + optional WhatsApp/Telegram
4. **Sale**: Stripe Checkout integration
5. **Content**: Protected lesson dashboard

### Tech Stack
- **Backend**: FastAPI (Python 3.9+)
- **Frontend**: Vanilla HTML/CSS/JavaScript
- **Database**: Supabase (PostgreSQL)
- **Auth**: Supabase Auth
- **Speech-to-Text**: faster-whisper (self-hosted)
- **Email**: Brevo (free tier: 300 emails/day)
- **Payment**: Stripe
- **Deployment**: DigitalOcean Droplet

## Quick Start

### 1. Prerequisites
```bash
# Install Python 3.9+
python3 --version

# Install system dependencies
sudo apt-get update
sudo apt-get install -y ffmpeg python3-pip python3-venv nginx certbot python3-certbot-nginx
```

### 2. Setup Environment
```bash
cd flosc-funnel-system
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### 3. Configure Environment Variables
```bash
cp .env.example .env
# Edit .env with your credentials
nano .env
```

### 4. Run Development Server
```bash
# Start backend
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000

# Frontend served via backend static files
```

### 5. Deploy to DigitalOcean
```bash
# See DEPLOYMENT.md for full instructions
./scripts/deploy.sh
```

## CSV Configuration

### product.csv
Defines your course product details:
```csv
product_id,product_name,full_price,oto_price,oto_discount_percent,currency
1,Master the 44 Sounds of English,575,144,75,USD
```

### targeted_lessons.csv
Maps phoneme groups to specific lessons:
```csv
lesson_id,lesson_title,phoneme_group,video_url,pdf_url,is_premium
1,The /æ/ Sound Mastery,æ,lessons/ae-sound.mp4,lessons/ae-sound.pdf,0
2,The /ɪ/ Sound Practice,ɪ,lessons/i-sound.mp4,lessons/i-sound.pdf,0
```

### magic_sentences.csv
Sentences for the freeline quiz (phonetically rich):
```csv
sentence_id,sentence_text,target_phonemes
1,The cat sat on the flat mat,æ|ɪ|ɒ
2,I think this thing is thick,ɪ|θ|ŋ
3,She sells seashells by the seashore,ʃ|s|iː
```

## How It Works

### User Flow
1. **Freeline**: User lands → chatbot asks for analysis → records 3 sentences
2. **Login**: Must login to see results (Supabase magic link)
3. **Offer**: Receives evaluation + 1-2 free lessons + 24hr OTO email
4. **Sale**: Clicks Stripe link, pays discounted price
5. **Content**: Access to full course dashboard

### Creator Workflow
1. Upload/update CSVs via web interface or SFTP
2. Add lesson videos/PDFs to `static/lessons/` folder
3. System automatically adapts to new content
4. Monitor conversions via dashboard

## Free Tier Limits
- **Supabase**: 500MB database, 50,000 monthly active users
- **Brevo**: 300 emails/day
- **Stripe**: No monthly fee, just transaction fees (2.9% + $0.30)
- **Twilio Sandbox**: WhatsApp testing (free, watermarked)
- **Telegram Bot**: Unlimited free messages

## File Structure
```
flosc-funnel-system/
├── app/
│   ├── main.py              # FastAPI application
│   ├── config.py            # Configuration loader
│   ├── models.py            # Pydantic models
│   ├── database.py          # Supabase client
│   ├── routers/
│   │   ├── freeline.py      # Quiz submission
│   │   ├── auth.py          # Login/logout
│   │   ├── offer.py         # Email sending
│   │   ├── payment.py       # Stripe webhooks
│   │   └── content.py       # Dashboard
│   ├── services/
│   │   ├── whisper_service.py    # Speech-to-text
│   │   ├── phoneme_matcher.py    # Phoneme comparison
│   │   ├── email_service.py      # Brevo integration
│   │   └── messaging_service.py  # WhatsApp/Telegram
│   └── utils/
│       ├── csv_loader.py    # CSV parsing
│       └── validators.py    # Input validation
├── frontend/
│   ├── index.html           # Landing/Freeline
│   ├── login.html           # Auth page
│   ├── offer.html           # Evaluation results
│   ├── dashboard.html       # Content delivery
│   ├── css/
│   │   └── styles.css
│   └── js/
│       ├── freeline.js      # Audio recorder
│       ├── auth.js          # Login handler
│       └── dashboard.js     # Content viewer
├── data/
│   ├── product.csv
│   ├── targeted_lessons.csv
│   └── magic_sentences.csv
├── static/
│   └── lessons/             # Video/PDF files
├── uploads/                 # User audio recordings
├── scripts/
│   ├── deploy.sh
│   └── backup.sh
├── requirements.txt
├── .env.example
├── DEPLOYMENT.md
└── README.md
```

## Security Considerations
- All audio files anonymized (UUID naming)
- HTTPS enforced via Let's Encrypt
- CORS configured for your domain only
- Supabase RLS (Row Level Security) enabled
- Stripe webhook signature verification
- Rate limiting on all endpoints

## Monitoring & Analytics
- Track conversion funnel via Supabase queries
- Monitor email open/click rates in Brevo
- Stripe dashboard for revenue tracking
- Custom admin dashboard (planned)

## Support
For issues or questions, contact: [your-email]

## License
Proprietary - All Rights Reserved
