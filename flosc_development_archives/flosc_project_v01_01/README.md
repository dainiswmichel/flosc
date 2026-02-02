# LeSAEp FLOSC Chat-Only System
## Complete Launch Package v1.01

**Status:** Ready for Testing  
**Launch Date:** January 8, 2026  
**Architecture:** WordPress + FastAPI + BuddyBoss  

---

## 🎯 What This Is

**Pure chat-only pronunciation quiz** from hello → audio recording → evaluation → payment → course delivery.

**Zero page navigation. Everything happens in one persistent chat window.**

---

## 📦 Package Contents

```
flosc_project_v01_01/
├── flosc_plugin_v01_01.zip     # WordPress plugin (upload to WP)
├── fastapi-backend/             # Python backend
│   ├── main.py                  # FastAPI app
│   ├── config.py                # Configuration
│   ├── auth.py                  # Token verification
│   ├── database.py              # SQLite management
│   ├── routers/
│   │   ├── chat.py              # Quiz endpoints
│   │   └── stripe_router.py    # Payment endpoints
│   ├── requirements.txt         # Python dependencies
│   ├── .env.example             # Environment template
│   └── deploy.sh                # Deployment script
├── nginx-config/
│   └── lesaep.conf              # Nginx proxy config
├── README.md                    # This file
├── TESTING.md                   # Testing checklist
└── DEPLOYMENT.md                # Deployment guide
```

---

## 🏗️ Architecture

```
lesaep.com
    ↓
Nginx Proxy
    ├─ / → WordPress (dainis.net)
    └─ /lesaep-api/ → FastAPI (port 8000)
        ↓
WordPress
    ├─ [lesaep_chat] shortcode → Chat UI
    ├─ REST API → Lesson content
    └─ BuddyBoss → Social login
        ↓
FastAPI Backend
    ├─ Whisper (audio transcription)
    ├─ SQLite (session state)
    └─ Stripe (payments)
```

---

## ✅ STEP 1: Install WordPress Plugin

### Option A: Upload via WordPress Admin
```
1. Go to: WP Admin → Plugins → Add New → Upload Plugin
2. Choose: flosc_plugin_v01_01.zip
3. Click: Install Now → Activate
```

### Option B: Upload via SSH/FTP
```bash
# Extract plugin
unzip flosc_plugin_v01_01.zip -d flosc_plugin_v01_01

# Upload to WordPress
scp -r flosc_plugin_v01_01 user@dainis.net:/var/www/html/wp-content/plugins/

# Activate via WP Admin
WP Admin → Plugins → Activate "LeSAEp Chat-Only FLOSC"
```

### Configure Plugin Settings
```
WP Admin → LeSAEp Chat → Settings

Required Settings:
- API URL: /lesaep-api
- Session Secret: [copy from WordPress settings page]
- Stripe Public Key: pk_test_...
- Stripe Secret Key: sk_test_...
- Paid Group ID: [BuddyBoss group ID for paid members]

Pricing:
- Product Name: LeSAEp English Pronunciation
- Full Price: 575
- OTO Price: 144
- Discount: 75

Save Settings
```

### Create Chat Page
```
WP Admin → Pages → Add New

Title: Chat
Slug: chat
Parent: lesaep (if you have a /lesaep/ parent page)
Content: [lesaep_chat]

Publish
```

**Result:** Chat interface visible at `https://dainis.net/lesaep/chat/`

---

## ✅ STEP 2: Deploy FastAPI Backend

### Requirements
- DigitalOcean Droplet (Ubuntu 22.04, 1GB RAM minimum)
- Python 3.8+
- Root access

### Quick Deploy
```bash
# Upload backend files to droplet
cd fastapi-backend
scp -r . root@YOUR_DROPLET_IP:/root/lesaep-backend/

# SSH into droplet
ssh root@YOUR_DROPLET_IP

# Run deployment script
cd /root/lesaep-backend
chmod +x deploy.sh
sudo ./deploy.sh
```

### Manual Deploy (if script fails)
```bash
# Install dependencies
sudo apt-get update
sudo apt-get install -y python3 python3-pip python3-venv

# Create app directory
sudo mkdir -p /opt/lesaep-backend
sudo cp -r . /opt/lesaep-backend/
cd /opt/lesaep-backend

# Setup Python environment
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Configure environment
cp .env.example .env
nano .env  # Edit with your values
```

### Configure .env File
```bash
nano /opt/lesaep-backend/.env
```

```ini
# Copy WP_SESSION_SECRET from WordPress settings page
WP_SESSION_SECRET=your_32_char_secret_from_wordpress

# WordPress API
WP_API_URL=https://dainis.net/wp-json/lesaep/v1

# Stripe keys
STRIPE_SECRET_KEY=sk_test_your_key
STRIPE_WEBHOOK_SECRET=whsec_your_secret

# Whisper model (tiny = fastest, small = better)
WHISPER_MODEL=tiny
```

### Start Service
```bash
# Create systemd service
sudo nano /etc/systemd/system/lesaep-backend.service
```

```ini
[Unit]
Description=LeSAEp FLOSC Backend
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/lesaep-backend
Environment="PATH=/opt/lesaep-backend/venv/bin"
ExecStart=/opt/lesaep-backend/venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
# Start service
sudo systemctl daemon-reload
sudo systemctl enable lesaep-backend
sudo systemctl start lesaep-backend

# Check status
sudo systemctl status lesaep-backend
```

### Test Backend
```bash
curl http://localhost:8000/health
```

**Expected:**
```json
{
  "status": "healthy",
  "version": "1.0.0",
  "whisper": "loaded"
}
```

---

## ✅ STEP 3: Configure Nginx Proxy

### Edit Nginx Config
```bash
sudo nano /etc/nginx/sites-available/lesaep.com
```

**Paste contents from `nginx-config/lesaep.conf`** (replace DROPLET_IP with actual IP)

### Enable Site
```bash
# Create symlink
sudo ln -s /etc/nginx/sites-available/lesaep.com /etc/nginx/sites-enabled/

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### Get SSL Certificate
```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot --nginx -d lesaep.com -d www.lesaep.com
```

---

## ✅ STEP 4: Create Content

### Create Lesson Posts
```
WP Admin → Posts → Add New

Title: Mastering the Short A Sound (/æ/)
Category: lesaep
Content: [Your lesson HTML/text]

Custom Fields:
- _lesaep_is_free: 1 (for free) or 0 (for paid)
- _lesaep_phoneme_group: /æ/ (or /ð/, /r/, etc.)

Publish
```

**Repeat for 10-15 lessons** (mix of free and paid)

### Example Phoneme Groups
- `/æ/` - Short A (cat, bat, mat)
- `/ð/` - Voiced TH (the, that)
- `/θ/` - Unvoiced TH (think, thank)
- `/r/` - R sound
- `/l/` - L sound
- `/v/` - V sound
- `/w/` - W sound

---

## ✅ STEP 5: Test Complete Flow

See **TESTING.md** for complete testing checklist.

**Quick Test:**
1. Visit `https://lesaep.com/lesaep/chat/`
2. Click "Yes, let's do it!"
3. Record 3 sentences
4. Login with BuddyBoss
5. See results + free lesson
6. Test OTO countdown
7. Test Stripe payment (use test card: 4242 4242 4242 4242)
8. Verify BuddyBoss group membership
9. Check lesson access

---

## 🎯 User Flow

```
1. Visit lesaep.com
   └─ See chat window

2. Bot: "Want free analysis?"
   └─ Yes/No gate (3 "No" = blocked)

3. Record 3 sentences
   └─ In-chat recorder → Upload to backend

4. Bot: "Login to see results"
   └─ BuddyBoss social login → Return with token

5. Show personalized results
   └─ Fetch lessons from WordPress

6. Show OTO + countdown
   └─ User chooses timer duration

7. Stripe payment (embedded in chat)
   └─ Complete purchase

8. Bot: "Here are your lessons"
   └─ Deliver sequentially in chat
```

**Everything in one chat window. Zero page navigation.**

---

## 📊 Monitoring

### Check Backend Logs
```bash
# Real-time logs
sudo journalctl -u lesaep-backend -f

# Last 100 lines
sudo journalctl -u lesaep-backend -n 100
```

### Check Nginx Logs
```bash
# Access log
sudo tail -f /var/log/nginx/access.log

# Error log
sudo tail -f /var/log/nginx/error.log
```

### Database Stats
```bash
cd /opt/lesaep-backend
source venv/bin/activate
python3 << EOF
from database import db
print(db.get_stats())
EOF
```

---

## 🔧 Troubleshooting

### Backend Not Starting
```bash
# Check logs
sudo journalctl -u lesaep-backend -n 50

# Common issues:
# 1. Missing .env file → cp .env.example .env
# 2. Wrong Python path → which python3
# 3. Port 8000 in use → sudo lsof -i :8000
```

### Nginx 502 Bad Gateway
```bash
# Check if backend is running
curl http://localhost:8000/health

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Verify proxy_pass IP matches droplet
```

### Whisper Model Not Loading
```bash
# Check model download
cd /opt/lesaep-backend
source venv/bin/activate
python3 -c "from faster_whisper import WhisperModel; WhisperModel('tiny')"

# If fails, manually install
pip install faster-whisper --break-system-packages
```

### WordPress Session Token Issues
```bash
# Verify secret matches
# In WordPress: WP Admin → LeSAEp Chat → Settings → Session Secret
# In Backend: /opt/lesaep-backend/.env → WP_SESSION_SECRET
```

---

## 💰 Cost Structure

**Fixed Costs:**
- DigitalOcean Droplet: $6/month
- Domain: ~$1/month
- **Total: $7/month**

**Variable Costs:**
- Stripe: 2.9% + $0.30 per transaction
- Example: $144 sale = $4.48 fee = $139.52 profit

**No Costs:**
- Supabase (using WordPress DB)
- OpenAI/Whisper API (self-hosted)
- Email (WordPress/Brevo free tier)

---

## 🚀 Launch Checklist

- [ ] WordPress plugin installed & configured
- [ ] FastAPI backend deployed & running
- [ ] Nginx proxy configured
- [ ] SSL certificate installed
- [ ] 10-15 lesson posts created
- [ ] BuddyBoss paid group created
- [ ] Stripe test mode working
- [ ] Full flow tested end-to-end
- [ ] Mobile responsive tested
- [ ] Stripe live mode enabled
- [ ] Marketing materials ready
- [ ] **LAUNCH January 8, 2026** 🎉

---

## 📞 Support

**Backend Issues:**
- Logs: `sudo journalctl -u lesaep-backend -f`
- Restart: `sudo systemctl restart lesaep-backend`

**WordPress Issues:**
- Error log: `/wp-content/debug.log`
- Plugin settings: WP Admin → LeSAEp Chat

**Nginx Issues:**
- Test config: `sudo nginx -t`
- Reload: `sudo systemctl reload nginx`

---

**Ready to test! See TESTING.md for complete testing guide.** 🚀
