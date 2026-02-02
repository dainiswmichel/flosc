# 🚀 LeSAEp FLOSC - Quick Start Guide
## Get Up and Running in 30 Minutes

---

## 📦 What You Have

```
flosc_project_v01_01.zip (42KB)
├── flosc_plugin_v01_01.zip    ← Upload to WordPress first
├── fastapi-backend/           ← Deploy to droplet
├── nginx-config/              ← Configure Nginx
├── README.md                  ← Full documentation
└── TESTING.md                 ← Testing checklist
```

---

## ⚡ 5-Step Launch

### STEP 1: WordPress Plugin (5 minutes)
```
1. Go to: WP Admin → Plugins → Add New → Upload Plugin
2. Choose: flosc_plugin_v01_01.zip
3. Click: Install Now → Activate
4. Go to: WP Admin → LeSAEp Chat → Settings
5. Configure:
   - API URL: /lesaep-api
   - Copy the Session Secret (will use later)
   - Add Stripe keys
   - Add BuddyBoss group ID
   - Set pricing ($575 → $144)
   - Save
```

### STEP 2: Create Chat Page (2 minutes)
```
1. WP Admin → Pages → Add New
2. Title: Chat
3. Content: [lesaep_chat]
4. Publish
5. Visit: https://dainis.net/lesaep/chat/
6. ✓ Chat window should appear
```

### STEP 3: Deploy Backend (10 minutes)
```bash
# Upload files
cd fastapi-backend
scp -r . root@YOUR_DROPLET_IP:/root/lesaep-backend/

# SSH and deploy
ssh root@YOUR_DROPLET_IP
cd /root/lesaep-backend
chmod +x deploy.sh
sudo ./deploy.sh

# Edit .env file
sudo nano /opt/lesaep-backend/.env

# Paste Session Secret from WordPress settings
WP_SESSION_SECRET=your_secret_from_wordpress

# Add Stripe keys
STRIPE_SECRET_KEY=sk_test_your_key
STRIPE_WEBHOOK_SECRET=whsec_your_secret

# Save and restart
sudo systemctl restart lesaep-backend

# Test
curl http://localhost:8000/health
```

### STEP 4: Configure Nginx (5 minutes)
```bash
# Edit Nginx config (replace DROPLET_IP)
sudo nano /etc/nginx/sites-available/lesaep.com

# Paste from: nginx-config/lesaep.conf
# Replace: DROPLET_IP with your actual IP

# Enable
sudo ln -s /etc/nginx/sites-available/lesaep.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Get SSL
sudo certbot --nginx -d lesaep.com -d www.lesaep.com
```

### STEP 5: Test (8 minutes)
```
1. Visit: https://lesaep.com/lesaep/chat/
2. Complete quiz (record 3 sentences)
3. Login with BuddyBoss
4. View results
5. Test payment (card: 4242 4242 4242 4242)
6. ✓ Check BuddyBoss group membership
```

---

## ✅ Success Indicators

After each step, verify:

**Step 1 Complete:**
- ✓ Plugin shows in Plugins list
- ✓ Settings page loads
- ✓ No PHP errors

**Step 2 Complete:**
- ✓ Chat window visible
- ✓ Gradient purple header
- ✓ Bot avatar shows
- ✓ No JS errors in console

**Step 3 Complete:**
- ✓ `curl http://localhost:8000/health` returns JSON
- ✓ Service running: `systemctl status lesaep-backend`
- ✓ No errors in: `journalctl -u lesaep-backend -n 20`

**Step 4 Complete:**
- ✓ `curl https://lesaep.com/lesaep-api/health` works
- ✓ SSL certificate shows (padlock in browser)
- ✓ No Nginx errors: `nginx -t`

**Step 5 Complete:**
- ✓ Can record audio
- ✓ Login redirects work
- ✓ Payment succeeds
- ✓ User added to BuddyBoss group

---

## 🐛 Common Issues

### "Plugin failed to activate"
```
Check: wp-content/debug.log
Fix: Enable WP_DEBUG in wp-config.php
```

### "Backend not starting"
```
Check: sudo journalctl -u lesaep-backend -n 50
Common: Missing .env file or wrong Python path
Fix: Verify .env exists and has all keys
```

### "502 Bad Gateway"
```
Check: Is backend running? curl http://localhost:8000/health
Fix: sudo systemctl restart lesaep-backend
```

### "Token verification failed"
```
Check: Session secrets match
WordPress: WP Admin → LeSAEp Chat → Settings
Backend: /opt/lesaep-backend/.env
Fix: Copy exact secret from WordPress to .env
```

---

## 📞 Need Help?

**Check logs:**
```bash
# Backend
sudo journalctl -u lesaep-backend -f

# Nginx
sudo tail -f /var/log/nginx/error.log

# WordPress
tail -f /var/www/html/wp-content/debug.log
```

**Test endpoints:**
```bash
# Backend health
curl http://localhost:8000/health

# WordPress session
curl https://dainis.net/wp-json/lesaep/v1/session

# Proxy
curl https://lesaep.com/lesaep-api/health
```

---

## 🎯 Next Steps

After Quick Start:
1. ✓ See TESTING.md for complete testing
2. ✓ Create 10-15 lesson posts
3. ✓ Test with real users
4. ✓ Switch Stripe to live mode
5. ✓ Launch! 🚀

---

**Total Time: 30 minutes**  
**Launch Date: January 8, 2026**  
**Days Remaining: 9**

Let's ship this! 🎉
