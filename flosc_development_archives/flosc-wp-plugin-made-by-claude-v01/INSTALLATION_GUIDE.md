# FLOSC WordPress Plugin - Complete Installation Guide

## 🎯 What You're Installing

A complete WordPress plugin that handles:
- **Freeline**: Interactive chatbot with audio recording
- **Login**: WordPress native authentication  
- **Offer**: Personalized pronunciation analysis + OTO
- **Sale**: Stripe payment integration
- **Content**: Protected lesson delivery

**Bridge Architecture**: WordPress frontend → FastAPI backend (Whisper processing)

---

## 📋 Prerequisites

- WordPress 6.0+ 
- PHP 7.4+
- Server with SSH access (VPS/Dedicated)
- Python 3.10+
- Stripe account
- Email service (WP Mail, Brevo, or SendGrid)

---

## 🚀 Installation Steps

### Step 1: Install WordPress Plugin

1. **Upload Plugin**:
   ```bash
   # Upload flosc-funnel-plugin folder to your server
   scp -r flosc-funnel-plugin/ user@yoursite.com:/var/www/html/wp-content/plugins/
   ```

2. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "FLOSC Funnel"
   - Click "Activate"
   
3. **Automatic Setup**:
   - Plugin creates database tables
   - Creates default pages (Freeline, Offer, Dashboard, Success)
   - Sets default settings

### Step 2: Install FastAPI Backend

1. **SSH into your server**:
   ```bash
   ssh user@yoursite.com
   ```

2. **Upload FastAPI backend**:
   ```bash
   # Create directory
   sudo mkdir -p /opt/flosc-backend
   cd /opt/flosc-backend
   
   # Upload files
   # (Upload main.py and requirements.txt to this directory)
   ```

3. **Install Python dependencies**:
   ```bash
   # Create virtual environment
   python3 -m venv venv
   source venv/bin/activate
   
   # Install requirements
   pip install -r requirements.txt
   ```

4. **Download Whisper model** (first run takes a few minutes):
   ```bash
   python3 << EOF
   from faster_whisper import WhisperModel
   model = WhisperModel("tiny", device="cpu")
   print("Whisper model downloaded!")
   EOF
   ```

5. **Create systemd service**:
   ```bash
   sudo nano /etc/systemd/system/flosc-backend.service
   ```
   
   Paste:
   ```ini
   [Unit]
   Description=FLOSC FastAPI Backend
   After=network.target

   [Service]
   Type=simple
   User=www-data
   WorkingDirectory=/opt/flosc-backend
   Environment="PATH=/opt/flosc-backend/venv/bin"
   ExecStart=/opt/flosc-backend/venv/bin/uvicorn main:app --host 127.0.0.1 --port 8000
   Restart=always

   [Install]
   WantedBy=multi-user.target
   ```

6. **Start the service**:
   ```bash
   sudo systemctl daemon-reload
   sudo systemctl enable flosc-backend
   sudo systemctl start flosc-backend
   
   # Check status
   sudo systemctl status flosc-backend
   ```

### Step 3: Configure WordPress Plugin

1. **Go to**: WordPress Admin → FLOSC Funnel → Settings

2. **General Settings**:
   - Product Name: "English Pronunciation Mastery"
   - Full Price: $575
   - OTO Price: $144
   - OTO Discount: 75%
   - OTO Duration: 24 hours
   
3. **Magic Sentences** (test sentences):
   - Sentence 1: "The cat sat on the mat"
   - Sentence 2: "She sells seashells by the seashore"
   - Sentence 3: "How now brown cow"

4. **API Settings**:
   - FastAPI URL: `http://localhost:8000` (or `http://127.0.0.1:8000`)
   - Test connection → Should show "Online"

5. **Stripe Settings**:
   - Mode: Test (switch to Live when ready)
   - Test Publishable Key: `pk_test_...` (from Stripe dashboard)
   - Test Secret Key: `sk_test_...` (from Stripe dashboard)
   - Webhook URL: `https://yoursite.com/?flosc_stripe_webhook=1`
   
6. **Email Settings**:
   - Provider: WordPress (or Brevo/SendGrid)
   - From Name: Your course name
   - From Email: noreply@yoursite.com

### Step 4: Create Lessons

1. **Go to**: WordPress Admin → Lessons → Add New

2. **Create a lesson**:
   - Title: "Mastering the Short A Sound"
   - Content: (Write your lesson in the editor)
   - Phoneme Group: /æ/ (cat, bat)
   - Free Lesson: ✓ (Check for free lessons)
   - Display Order: 1

3. **Repeat** for at least 3-5 lessons

### Step 5: Set Up Stripe Webhook

1. **Go to**: https://dashboard.stripe.com/webhooks

2. **Add endpoint**:
   - URL: `https://yoursite.com/?flosc_stripe_webhook=1`
   - Events: Select `checkout.session.completed`
   
3. **Save webhook secret** to plugin settings

### Step 6: Test the Funnel

1. **Visit** the Freeline page (auto-created during activation)

2. **Complete the flow**:
   - Click "Yes" to analysis
   - Record 3 audio samples
   - Submit
   - Log in (create test account if needed)
   - View offer page
   - Test Stripe checkout (use test card: 4242 4242 4242 4242)
   - Verify success page
   - Access dashboard

---

## 🔧 Configuration Options

### Shortcodes

The plugin creates these pages automatically, but you can use shortcodes anywhere:

```
[flosc_chatbot]   - Freeline quiz interface
[flosc_offer]     - Offer page with OTO
[flosc_dashboard] - Course dashboard
[flosc_success]   - Payment success page
```

### Phoneme Groups

When creating lessons, use these phoneme groups:

- `/æ/` - cat, bat, hat
- `/ɪ/` - sit, bit, hit  
- `/θ/` - think, path
- `/ð/` - this, that
- `/ʃ/` - ship, cash
- `/r/` - red, very
- `/l/` - let, fill
- `/aɪ/` - my, try
- `/aʊ/` - how, now

### Email Providers

**WordPress Mail** (default, free):
- Uses wp_mail() function
- Works immediately
- May go to spam

**Brevo** (recommended):
- Free tier: 300 emails/day
- Better deliverability
- Get API key from brevo.com

**SendGrid**:
- Free tier: 100 emails/day
- Excellent deliverability
- Get API key from sendgrid.com

---

## 🐛 Troubleshooting

### FastAPI Backend Not Running

```bash
# Check status
sudo systemctl status flosc-backend

# View logs
sudo journalctl -u flosc-backend -f

# Restart
sudo systemctl restart flosc-backend
```

### WordPress Can't Connect to Backend

1. Check FastAPI is running on port 8000
2. Test: `curl http://localhost:8000/health`
3. Verify firewall allows internal connections
4. Check API URL in plugin settings

### Whisper Processing Fails

```bash
# Test Whisper manually
cd /opt/flosc-backend
source venv/bin/activate
python3 -c "from faster_whisper import WhisperModel; m=WhisperModel('tiny', device='cpu'); print('OK')"
```

### Stripe Webhook Not Working

1. Verify webhook URL in Stripe dashboard
2. Check webhook secret in plugin settings
3. Test with Stripe CLI: `stripe listen --forward-to localhost/wordpress/?flosc_stripe_webhook=1`

### Audio Recording Not Working

- Check browser permissions (microphone access)
- Try different browser (Chrome/Firefox recommended)
- Verify HTTPS (required for MediaRecorder API)

---

## 📊 Monitoring

### Check Backend Status

```bash
# Service status
sudo systemctl status flosc-backend

# Recent logs
sudo journalctl -u flosc-backend -n 50

# Real-time logs
sudo journalctl -u flosc-backend -f
```

### Database Queries

Check funnel performance in WordPress:

```sql
-- Total quizzes
SELECT COUNT(*) FROM wp_flosc_quiz_sessions;

-- Completed quizzes
SELECT COUNT(*) FROM wp_flosc_quiz_sessions WHERE completed = 1;

-- Paid conversions
SELECT COUNT(*) FROM wp_flosc_quiz_sessions WHERE is_paid = 1;

-- Conversion rate
SELECT 
  (COUNT(CASE WHEN is_paid = 1 THEN 1 END) * 100.0 / 
   COUNT(CASE WHEN completed = 1 THEN 1 END)) as conversion_rate
FROM wp_flosc_quiz_sessions;
```

---

## 🔒 Security

### Production Checklist

- [ ] HTTPS enabled (required for audio recording)
- [ ] Strong WordPress admin password
- [ ] Stripe in live mode with live keys
- [ ] Webhook secrets configured
- [ ] PHP file upload limits appropriate (10MB+)
- [ ] FastAPI backend not exposed publicly (127.0.0.1 only)
- [ ] Regular WordPress updates
- [ ] Database backups scheduled

---

## 🚀 Going Live

1. **Switch Stripe to Live Mode**:
   - Settings → Stripe Settings
   - Mode: Live
   - Enter live publishable and secret keys

2. **Update webhook** in Stripe to live mode

3. **Test live payment** with real card

4. **Set up email provider** (Brevo/SendGrid for better deliverability)

5. **Create real lessons** (at least 8-10 total)

6. **Monitor** first few days closely

---

## 📈 Optimization Tips

### Increase Conversions:
- Test different magic sentences
- A/B test OTO pricing
- Adjust OTO duration (12hr, 24hr, 48hr)
- Add testimonials to offer page
- Include demo videos in lessons

### Performance:
- Use caching plugin (WP Super Cache, W3 Total Cache)
- Optimize images
- Enable Gzip compression
- Use CDN for static assets

### Email Deliverability:
- Set up SPF/DKIM records
- Use professional email service (Brevo/SendGrid)
- Warm up domain (start with low volume)
- Monitor bounce/spam rates

---

## 🆘 Support

### Logs Location:
- WordPress: Check Debug Log (if WP_DEBUG enabled)
- FastAPI: `sudo journalctl -u flosc-backend -f`
- Nginx/Apache: Check error logs

### Common Issues:

**"Backend offline"**:
- Restart FastAPI: `sudo systemctl restart flosc-backend`
- Check Python errors in journalctl

**"Failed to save audio"**:
- Check upload directory permissions
- Increase PHP upload_max_filesize

**"Stripe error"**:
- Verify API keys are correct
- Check webhook is receiving events

---

## 🎉 You're Ready!

Your WordPress FLOSC funnel is now live with:
✅ AI-powered pronunciation analysis
✅ Automated email sequences
✅ Stripe payment processing
✅ Protected content delivery
✅ Zero ongoing costs (except Stripe fees)

**Next Steps**:
1. Create more lessons
2. Drive traffic to your freeline page
3. Monitor conversions
4. Optimize based on data

---

**Questions?** Review the troubleshooting section or check WordPress/FastAPI logs for specific errors.
