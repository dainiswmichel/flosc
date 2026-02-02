# LeSAEp v01_04 - Step-by-Step Deployment Guide
**Target: Working demo by end of day**  
**Date: December 31, 2025**  
**Estimated Time: 4-6 hours (with contingency)**

---

## 📋 Pre-Deployment Checklist

Before you start, ensure you have:

- [ ] Server access (SSH credentials)
- [ ] Domain name pointing to server IP
- [ ] WordPress hosting ready (or fresh install)
- [ ] BuddyBoss license key (optional - can use WP native first)
- [ ] Text editor for `.env` configuration
- [ ] Terminal window open

**Server Requirements:**
- Ubuntu 20.04+ or similar Linux
- Python 3.8 or higher
- 2GB RAM minimum (4GB recommended)
- 10GB free disk space
- Root or sudo access

---

## Phase 1: Server Foundation (30 minutes)

### Step 1.1: Connect to Server
```bash
# SSH into your server
ssh user@your-server-ip

# Update system packages
sudo apt update && sudo apt upgrade -y
```

**Verify:**
```bash
# Should show Ubuntu 20.04 or higher
lsb_release -a
```

### Step 1.2: Install Core Dependencies
```bash
# Install Python, pip, and build tools
sudo apt install -y python3 python3-pip python3-venv

# Install ffmpeg (required for Whisper)
sudo apt install -y ffmpeg

# Install nginx
sudo apt install -y nginx

# Install git (if not already installed)
sudo apt install -y git
```

**Verify:**
```bash
python3 --version    # Should be 3.8+
ffmpeg -version      # Should show ffmpeg installed
nginx -v             # Should show nginx version
```

### Step 1.3: Create Working Directory
```bash
# Create app directory
sudo mkdir -p /var/www/lesaep
sudo chown -R $USER:$USER /var/www/lesaep
cd /var/www/lesaep
```

---

## Phase 2: Deploy FastAPI Backend (45 minutes)

### Step 2.1: Upload Code
**Option A: Git Clone (Recommended)**
```bash
cd /var/www/lesaep
git clone https://github.com/dainiswmichel/lesaep.git .
cd flosc_project_v01_04/fastapi-backend/
```

**Option B: Upload ZIP**
```bash
# On your local machine:
scp flosc_project_v01_04.zip user@your-server:/var/www/lesaep/

# On server:
cd /var/www/lesaep
unzip flosc_project_v01_04.zip
cd flosc_project_v01_04/fastapi-backend/
```

### Step 2.2: Create Virtual Environment
```bash
# Create venv
python3 -m venv venv

# Activate venv
source venv/bin/activate

# Verify you're in venv (prompt should show (venv))
which python  # Should show path with 'venv' in it
```

### Step 2.3: Install Python Dependencies
```bash
# Upgrade pip first
pip install --upgrade pip

# Install requirements (this will take 5-10 minutes)
pip install -r requirements.txt

# Verify key packages
pip list | grep -E 'fastapi|uvicorn|faster-whisper'
```

**Expected output:**
```
fastapi                    0.109.0
faster-whisper             0.10.0
uvicorn                    0.27.0
```

### Step 2.4: Configure Environment
```bash
# Copy example env file
cp .env.example .env

# Edit with your settings
nano .env
```

**Minimal .env configuration:**
```bash
# API Settings
WP_API_URL=https://your-wordpress-domain.com
ALLOWED_ORIGINS=https://your-wordpress-domain.com,https://www.your-wordpress-domain.com

# Whisper Settings (start with tiny for testing)
WHISPER_MODEL=tiny
WHISPER_DEVICE=cpu
MOCK_MODE=false

# Logging
LOG_LEVEL=INFO
LOG_FILE=logs/lesaep_api.log
```

**Save and exit:** `Ctrl+X`, then `Y`, then `Enter`

### Step 2.5: Create Logs Directory
```bash
mkdir -p logs
touch logs/lesaep_api.log
```

### Step 2.6: Test FastAPI Manually (First Run)
```bash
# Make sure you're in venv
source venv/bin/activate

# Start FastAPI manually for testing
python3 main.py
```

**Expected output:**
```
INFO:     Started server process [12345]
INFO:     Waiting for application startup.
INFO:     Application startup complete.
INFO:     Uvicorn running on http://0.0.0.0:8000
```

**Open a second terminal** and test:
```bash
# Test health endpoint
curl http://localhost:8000/health

# Expected response:
# {"status":"healthy","timestamp":"2025-12-31T..."}

# Test root endpoint
curl http://localhost:8000/

# Expected response:
# {"message":"LeSAEp API","version":"1.0.0","status":"running"}
```

**If tests pass, stop the server:** `Ctrl+C` in the first terminal

---

## Phase 3: Set Up System Service (30 minutes)

### Step 3.1: Create Systemd Service
```bash
sudo nano /etc/systemd/system/lesaep-api.service
```

**Paste this configuration:**
```ini
[Unit]
Description=LeSAEp FastAPI Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/lesaep/flosc_project_v01_04/fastapi-backend
Environment="PATH=/var/www/lesaep/flosc_project_v01_04/fastapi-backend/venv/bin"
ExecStart=/var/www/lesaep/flosc_project_v01_04/fastapi-backend/venv/bin/python3 main.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

**Save and exit:** `Ctrl+X`, `Y`, `Enter`

### Step 3.2: Fix Permissions
```bash
# Make www-data owner of the app directory
sudo chown -R www-data:www-data /var/www/lesaep/flosc_project_v01_04/
```

### Step 3.3: Start and Enable Service
```bash
# Reload systemd
sudo systemctl daemon-reload

# Start the service
sudo systemctl start lesaep-api

# Check status
sudo systemctl status lesaep-api
```

**Expected output:**
```
● lesaep-api.service - LeSAEp FastAPI Service
   Loaded: loaded (/etc/systemd/system/lesaep-api.service; disabled)
   Active: active (running) since ...
```

**If Active shows "failed":**
```bash
# Check logs for errors
sudo journalctl -u lesaep-api -n 50
```

### Step 3.4: Enable Auto-Start on Boot
```bash
sudo systemctl enable lesaep-api
```

### Step 3.5: Verify Service is Running
```bash
# Test from localhost
curl http://localhost:8000/health

# Check if port 8000 is listening
sudo netstat -tulpn | grep :8000
```

**Expected:**
```
tcp  0  0  0.0.0.0:8000  0.0.0.0:*  LISTEN  12345/python3
```

---

## Phase 4: Configure Nginx Reverse Proxy (30 minutes)

### Step 4.1: Create Nginx Configuration
```bash
sudo nano /etc/nginx/sites-available/lesaep
```

**Paste this configuration (adjust YOUR_DOMAIN):**
```nginx
server {
    listen 80;
    server_name your-wordpress-domain.com www.your-wordpress-domain.com;

    # WordPress root (adjust path to your WP installation)
    root /var/www/html;
    index index.php index.html;

    # Proxy API requests to FastAPI
    location /lesaep-api/ {
        proxy_pass http://127.0.0.1:8000/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Increase timeout for audio processing
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }

    # WordPress PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;  # Adjust PHP version
    }

    # Static files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        log_not_found off;
    }

    # Deny access to sensitive files
    location ~ /\.ht {
        deny all;
    }
}
```

**Save and exit:** `Ctrl+X`, `Y`, `Enter`

### Step 4.2: Enable Site and Test Configuration
```bash
# Create symbolic link
sudo ln -s /etc/nginx/sites-available/lesaep /etc/nginx/sites-enabled/

# Remove default site (optional)
sudo rm /etc/nginx/sites-enabled/default

# Test nginx configuration
sudo nginx -t
```

**Expected output:**
```
nginx: the configuration file /etc/nginx/nginx.conf syntax is ok
nginx: configuration file /etc/nginx/nginx.conf test is successful
```

**If errors appear:**
- Check for typos in server_name
- Verify paths exist
- Check PHP-FPM socket path matches your PHP version

### Step 4.3: Restart Nginx
```bash
sudo systemctl restart nginx

# Check status
sudo systemctl status nginx
```

### Step 4.4: Test Reverse Proxy
```bash
# From server
curl http://localhost/lesaep-api/health

# From your local machine (use your domain)
curl http://your-domain.com/lesaep-api/health
```

**Expected:**
```json
{"status":"healthy","timestamp":"2025-12-31T..."}
```

### Step 4.5: (Optional) Set Up SSL with Let's Encrypt
```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Get SSL certificate (replace with your domain)
sudo certbot --nginx -d your-wordpress-domain.com -d www.your-wordpress-domain.com

# Follow prompts, choose redirect HTTP to HTTPS
```

**Verify SSL:**
```bash
# Should return JSON over HTTPS
curl https://your-domain.com/lesaep-api/health
```

---

## Phase 5: WordPress Setup (60 minutes)

### Step 5.1: Install WordPress (If Not Already Installed)
```bash
# Download WordPress
cd /var/www/html
sudo wget https://wordpress.org/latest.tar.gz
sudo tar -xvzf latest.tar.gz
sudo cp -r wordpress/* .
sudo rm -rf wordpress latest.tar.gz

# Set permissions
sudo chown -R www-data:www-data /var/www/html
sudo chmod -R 755 /var/www/html
```

**Visit your domain and complete WordPress installation:**
- Go to `http://your-domain.com`
- Follow setup wizard
- Create admin account

### Step 5.2: Install BuddyBoss (Optional - Can Skip for Testing)
**Option A: BuddyBoss Platform (Premium)**
1. Download from BuddyBoss.com
2. Upload via WordPress admin: Plugins → Add New → Upload
3. Activate BuddyBoss Platform

**Option B: Use WordPress Native Users for Testing**
- Skip BuddyBoss initially
- Test with standard WordPress login
- Add BuddyBoss later when ready

### Step 5.3: Install LeSAEp WordPress Plugin
```bash
# Upload plugin ZIP to server
scp flosc_project_v01_04/flosc_plugin_v01_04.zip user@your-server:/tmp/

# On server, move to WordPress plugins directory
sudo mv /tmp/flosc_plugin_v01_04.zip /var/www/html/wp-content/plugins/
cd /var/www/html/wp-content/plugins/
sudo unzip flosc_plugin_v01_04.zip
sudo chown -R www-data:www-data lesaep-chat/
```

**Or upload via WordPress admin:**
1. Login to WP admin
2. Go to Plugins → Add New → Upload Plugin
3. Choose `flosc_plugin_v01_04.zip`
4. Click "Install Now"
5. Click "Activate"

### Step 5.4: Configure Plugin Settings
1. In WordPress admin, go to **Settings → LeSAEp Chat**
2. Set **API Endpoint URL:** `https://your-domain.com/lesaep-api`
3. Enable **Debug Mode** for testing
4. Save Changes

### Step 5.5: Create Test Page
1. Go to **Pages → Add New**
2. Title: "Test LeSAEp Chat"
3. Add this shortcode in a paragraph block:
   ```
   [lesaep_chat]
   ```
4. Click **Publish**
5. View the page

**Expected:** You should see the chat interface loaded on the page

---

## Phase 6: End-to-End Testing (60 minutes)

### Step 6.1: Test Chat Interface
1. Visit your test page with `[lesaep_chat]` shortcode
2. **Open browser console** (F12 → Console tab)
3. Look for JavaScript errors

**Expected console output:**
```
LeSAEp Chat initialized
API endpoint: https://your-domain.com/lesaep-api
```

### Step 6.2: Test Mock Mode (No Audio)
**Edit `.env` on server:**
```bash
cd /var/www/lesaep/flosc_project_v01_04/fastapi-backend/
nano .env
```

**Set:**
```bash
MOCK_MODE=true
```

**Restart service:**
```bash
sudo systemctl restart lesaep-api
```

**Test from browser:**
1. Record or upload a test audio file in chat
2. Submit
3. Check browser console for API response
4. Should receive mock transcription: "This is a mock transcription..."

### Step 6.3: Test Real Whisper Mode
**Edit `.env`:**
```bash
MOCK_MODE=false
WHISPER_MODEL=tiny  # Start with smallest model
```

**Restart service:**
```bash
sudo systemctl restart lesaep-api

# Watch logs during first request (model download)
sudo journalctl -u lesaep-api -f
```

**First audio upload will trigger model download (~5-10 minutes):**
```
Downloading model: tiny
This may take several minutes on first run...
Model downloaded successfully
Processing audio with Whisper...
```

**Subsequent requests will be fast (model is cached)**

### Step 6.4: Test Audio Processing Pipeline

**Record test audio in chat saying:**
```
"The cat sat on the mat"
```

**Expected response:**
- Transcription: "the cat sat on the mat"
- Phoneme analysis showing detected sounds
- Accuracy percentage
- Feedback on pronunciation

### Step 6.5: Test Error Handling

**Upload invalid file (not audio):**
- Upload a `.txt` or `.jpg` file
- Should receive error: "Invalid audio format"

**Send request with server stopped:**
```bash
sudo systemctl stop lesaep-api
```
- Try uploading audio
- Should see connection error in browser console
- Restart: `sudo systemctl start lesaep-api`

---

## Phase 7: Payment Integration (Optional - Can Skip for Demo)

### Step 7.1: Install Payment Plugin
**Choose one:**
- WooCommerce (free)
- Easy Digital Downloads (free)
- Paid Memberships Pro (premium)

```bash
# Example: Install WooCommerce
# In WP admin: Plugins → Add New → Search "WooCommerce"
# Install & Activate
```

### Step 7.2: Configure Payment Gateway
1. Go to WooCommerce → Settings → Payments
2. Enable **Stripe** or **PayPal**
3. Add test API keys
4. Save settings

### Step 7.3: Create Lesson Product
1. Go to Products → Add New
2. Create a "Lesson Access" product
3. Set price (e.g., $9.99)
4. Publish

### Step 7.4: Test Purchase Flow
1. Visit shop as logged-out user
2. Add lesson to cart
3. Checkout with test payment
4. Verify access granted after purchase

---

## 🎯 Success Checklist

By end of day, you should have:

### ✅ Backend (Must Have)
- [ ] FastAPI running on port 8000
- [ ] `/health` endpoint returns 200 OK
- [ ] Service auto-starts on reboot
- [ ] Logs are being written to `logs/lesaep_api.log`

### ✅ Nginx (Must Have)
- [ ] Reverse proxy `/lesaep-api/` working
- [ ] WordPress site loads on port 80/443
- [ ] SSL certificate installed (optional but recommended)

### ✅ WordPress (Must Have)
- [ ] WordPress installed and accessible
- [ ] LeSAEp plugin activated
- [ ] Chat interface appears on test page
- [ ] No JavaScript console errors

### ✅ Integration (Must Have)
- [ ] Chat sends request to FastAPI
- [ ] FastAPI receives and processes request
- [ ] Response returns to WordPress
- [ ] User sees feedback in chat

### 🎁 Bonus (Nice to Have)
- [ ] Real Whisper transcription working
- [ ] BuddyBoss social login functional
- [ ] Payment gateway accepting test payments
- [ ] Production-ready SSL certificate

---

## 🚨 Common Issues & Fixes

### Issue 1: "Connection Refused" on Port 8000
**Symptoms:** `curl http://localhost:8000` fails

**Check:**
```bash
# Is service running?
sudo systemctl status lesaep-api

# Is port open?
sudo netstat -tulpn | grep :8000

# Check logs
sudo journalctl -u lesaep-api -n 50
```

**Fix:**
```bash
# Restart service
sudo systemctl restart lesaep-api

# If still failing, run manually to see errors
cd /var/www/lesaep/flosc_project_v01_04/fastapi-backend/
source venv/bin/activate
python3 main.py
```

### Issue 2: CORS Errors in Browser Console
**Symptoms:** 
```
Access to fetch at '...' from origin '...' has been blocked by CORS policy
```

**Fix:**
```bash
# Edit .env
nano .env

# Update ALLOWED_ORIGINS with your exact domain (including https://)
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Restart
sudo systemctl restart lesaep-api
```

### Issue 3: Nginx 502 Bad Gateway
**Symptoms:** Visiting `/lesaep-api/health` shows "502 Bad Gateway"

**Check:**
```bash
# Is FastAPI running?
curl http://localhost:8000/health

# Check nginx error log
sudo tail -f /var/log/nginx/error.log
```

**Fix:**
```bash
# Ensure FastAPI is running
sudo systemctl start lesaep-api

# Restart nginx
sudo systemctl restart nginx
```

### Issue 4: WordPress Plugin Not Loading
**Symptoms:** Shortcode shows as text, no chat interface

**Check:**
```bash
# Is plugin activated?
# WP Admin → Plugins → check LeSAEp Chat is active

# Check browser console for JS errors
# F12 → Console tab
```

**Fix:**
```bash
# Re-upload and activate plugin
# Or check file permissions
sudo chown -R www-data:www-data /var/www/html/wp-content/plugins/
```

### Issue 5: Whisper Model Won't Download
**Symptoms:** First audio upload hangs or times out

**Check:**
```bash
# Watch logs during upload
sudo journalctl -u lesaep-api -f

# Check disk space
df -h

# Check internet connectivity
ping huggingface.co
```

**Fix:**
```bash
# Increase proxy timeout in nginx
# Edit: /etc/nginx/sites-available/lesaep
# Add under location /lesaep-api/:
proxy_read_timeout 600;

# Or switch to mock mode temporarily
nano /var/www/lesaep/flosc_project_v01_04/fastapi-backend/.env
# Set: MOCK_MODE=true
sudo systemctl restart lesaep-api
```

---

## 📊 Timeline Estimates

| Phase | Task | Estimated Time |
|-------|------|----------------|
| 1 | Server foundation | 30 min |
| 2 | FastAPI deployment | 45 min |
| 3 | System service | 30 min |
| 4 | Nginx setup | 30 min |
| 5 | WordPress setup | 60 min |
| 6 | Testing | 60 min |
| 7 | Payments (optional) | 60 min |
| **Total** | **Without payments** | **4 hours** |
| **Total** | **With payments** | **5 hours** |

**Add 1-2 hours buffer for troubleshooting**

---

## 📞 Next Steps After Successful Deployment

1. **Test with real users** - Create test accounts, record audio
2. **Monitor logs** - Watch for errors: `sudo journalctl -u lesaep-api -f`
3. **Optimize Whisper model** - Consider upgrading from `tiny` to `base` or `small`
4. **Add content** - Upload lesson materials as WordPress posts
5. **Configure backups** - Set up automated backups of WordPress + FastAPI
6. **Set up monitoring** - Install uptime monitoring (UptimeRobot, etc.)
7. **Load testing** - Test with multiple concurrent users
8. **Security hardening** - Firewall, fail2ban, security plugins

---

## 🎉 You're Ready!

If you complete this guide successfully, you'll have:
- ✅ Working LeSAEp demo
- ✅ FastAPI processing audio
- ✅ WordPress chat interface
- ✅ End-to-end integration tested
- ✅ Foundation for Jan 8 launch

**Good luck with deployment tomorrow!** 🚀

---

**Created:** December 30, 2025  
**Version:** v01_04  
**Next Review:** After first deployment
