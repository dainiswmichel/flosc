# LeSAEp FLOSC - Simplified WordPress Architecture
## Chat-Only Quiz with WordPress Payment Integration

**Version:** 1.0.1 (Simplified)  
**Status:** Ready for Testing  
**Launch:** January 8, 2026  

---

## 🎯 What Changed (MUCH Simpler!)

### ❌ Removed from FastAPI:
- Stripe integration (no payment processing)
- SQLite database (no state management)
- Session management (stateless)
- Webhooks (not needed)
- Complex routing (just one endpoint)

### ✅ Kept in FastAPI:
- **ONE job: Process audio with Whisper**
- Returns transcription + phoneme analysis
- Completely stateless

### ✅ Added to WordPress:
- Payment handling (via WooCommerce/EDD/PMPro/Custom)
- Access control (BuddyBoss group OR user meta)
- All state management (WordPress database)
- Content delivery (already there)

---

## 🏗️ Clean Architecture

```
User visits lesaep.com
    ↓
WordPress Chat Page (dainis.net/lesaep/chat/)
    ↓
Chat UI (JavaScript)
    ├─ Records audio → Sends to FastAPI
    ├─ Gets results → Shows free lesson (WordPress)
    ├─ "Buy Now" → Redirects to WordPress checkout
    └─ After payment → User returns → Full access
    
FastAPI Backend (port 8000)
    └─ /process-audio (Whisper transcription only)

WordPress (dainis.net)
    ├─ Authentication (BuddyBoss social logins)
    ├─ Payment (WooCommerce/EDD/PMPro/Custom)
    ├─ Access Control (BuddyBoss group check)
    └─ Content (WordPress posts)
```

---

## 📦 Package Contents

```
flosc_simplified/
├── main.py                              # FastAPI (Whisper only)
├── lesaep-chat-simplified.php           # WordPress plugin
├── chat.js                              # Chat interface
├── requirements.txt                     # Python dependencies
└── README.md                            # This file
```

---

## 🚀 Installation (4 Steps)

### STEP 1: FastAPI Backend (5 minutes)

```bash
# Upload to droplet
scp main.py root@YOUR_DROPLET:/opt/lesaep-backend/
scp requirements.txt root@YOUR_DROPLET:/opt/lesaep-backend/

# SSH and setup
ssh root@YOUR_DROPLET
cd /opt/lesaep-backend

# Install dependencies
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Create systemd service
sudo nano /etc/systemd/system/lesaep-backend.service
```

```ini
[Unit]
Description=LeSAEp Audio Processor
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/lesaep-backend
Environment="PATH=/opt/lesaep-backend/venv/bin"
Environment="WHISPER_MODEL=tiny"
ExecStart=/opt/lesaep-backend/venv/bin/python main.py
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
# Start service
sudo systemctl daemon-reload
sudo systemctl enable lesaep-backend
sudo systemctl start lesaep-backend

# Test
curl http://localhost:8000/health
# Should return: {"status": "healthy", "whisper": "loaded"}
```

### STEP 2: Nginx Proxy (2 minutes)

```bash
sudo nano /etc/nginx/sites-available/lesaep.com
```

```nginx
server {
    server_name lesaep.com;
    
    location / {
        proxy_pass https://dainis.net;
        proxy_set_header Host dainis.net;
        proxy_set_header X-Forwarded-Host lesaep.com;
    }
    
    location /lesaep-api/ {
        proxy_pass http://localhost:8000/;
        proxy_set_header Host $host;
        proxy_connect_timeout 300;
        proxy_read_timeout 300;
    }
    
    listen 443 ssl;
    ssl_certificate /etc/letsencrypt/live/lesaep.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/lesaep.com/privkey.pem;
}

server {
    listen 80;
    server_name lesaep.com;
    return 301 https://$server_name$request_uri;
}
```

```bash
# Enable and reload
sudo ln -s /etc/nginx/sites-available/lesaep.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Get SSL
sudo certbot --nginx -d lesaep.com
```

### STEP 3: WordPress Plugin (3 minutes)

```
1. Upload lesaep-chat-simplified.php to:
   wp-content/plugins/lesaep-chat/

2. Activate: WP Admin → Plugins → Activate

3. Configure: WP Admin → LeSAEp → Settings
   - Backend API URL: /lesaep-api
   - BuddyBoss Paid Group ID: [your group ID]
   - Product Name: LeSAEp Course
   - Price: 144
   - Checkout URL: [your checkout page URL]

4. Create chat page:
   WP Admin → Pages → Add New
   Content: [lesaep_chat]
   Publish
```

### STEP 4: WordPress Payment (Your Choice!)

Choose ONE of these methods:

#### Option A: WooCommerce
```php
// Add to theme functions.php
add_action('woocommerce_order_status_completed', 'lesaep_mark_paid');

function lesaep_mark_paid($order_id) {
    $order = wc_get_order($order_id);
    $user_id = $order->get_user_id();
    
    // Check if order contains LeSAEp product
    foreach ($order->get_items() as $item) {
        if ($item->get_product_id() == YOUR_PRODUCT_ID) {
            // Add to BuddyBoss group
            $group_id = get_option('lesaep_paid_group_id');
            groups_join_group($group_id, $user_id);
            break;
        }
    }
}
```

#### Option B: Easy Digital Downloads
```php
add_action('edd_complete_purchase', 'lesaep_mark_paid_edd');

function lesaep_mark_paid_edd($payment_id) {
    $payment = edd_get_payment($payment_id);
    $user_id = $payment->user_id;
    $downloads = $payment->downloads;
    
    foreach ($downloads as $download) {
        if ($download['id'] == YOUR_DOWNLOAD_ID) {
            $group_id = get_option('lesaep_paid_group_id');
            groups_join_group($group_id, $user_id);
            break;
        }
    }
}
```

#### Option C: Paid Memberships Pro
```php
add_action('pmpro_after_checkout', 'lesaep_mark_paid_pmpro');

function lesaep_mark_paid_pmpro($user_id) {
    $level = pmpro_getMembershipLevelForUser($user_id);
    
    if ($level->id == YOUR_LEVEL_ID) {
        $group_id = get_option('lesaep_paid_group_id');
        groups_join_group($group_id, $user_id);
    }
}
```

#### Option D: Custom Payment Form
```php
// After successful payment:
$group_id = get_option('lesaep_paid_group_id');
groups_join_group($group_id, $user_id);

// OR set user meta:
update_user_meta($user_id, 'lesaep_paid_access', '1');
```

---

## 🎯 Complete User Flow

```
1. User visits lesaep.com/lesaep/chat/
   ↓
2. Bot: "Want free analysis?" (Yes/No gate)
   ↓
3. Record 3 sentences
   → Audio sent to /lesaep-api/process-audio
   → Whisper transcribes
   → Returns flagged phonemes
   ↓
4. Bot: "Login to see results"
   → BuddyBoss social login
   → Returns to chat
   ↓
5. Bot shows results + free lesson
   → Fetched from WordPress posts
   → Shows lesson card with link
   ↓
6. Bot: "Want full access?"
   → Shows price + benefit
   → "Buy Now" button
   ↓
7. Click "Buy Now"
   → Redirects to WordPress checkout
   → WooCommerce/EDD/PMPro/Custom
   ↓
8. Complete payment
   → WordPress hook fires
   → User added to BuddyBoss group
   ↓
9. Return to chat
   → WordPress checks group membership
   → Chat loads all lessons
   ↓
10. Full access granted
    → All lesson cards clickable
    → Can view any lesson
```

---

## ✅ Access Control Logic

**WordPress Plugin checks (in order):**

1. **BuddyBoss Group Membership**
   ```php
   $group_id = get_option('lesaep_paid_group_id');
   groups_is_user_member($user_id, $group_id)
   ```

2. **User Meta Flag**
   ```php
   get_user_meta($user_id, 'lesaep_paid_access', true) === '1'
   ```

3. **Custom Capability**
   ```php
   $user->has_cap('lesaep_full_access')
   ```

**If ANY of these return true → Full access**

---

## 📊 FastAPI Endpoints

Only **ONE** endpoint:

### POST /process-audio

**Request:**
```bash
curl -X POST http://localhost:8000/process-audio \
  -F "audio=@recording.webm" \
  -F "expected_text=The cat sat on the mat" \
  -F "sentence_index=0"
```

**Response:**
```json
{
  "transcription": "the cat sat on the mat",
  "expected": "The cat sat on the mat",
  "accuracy": 0.95,
  "flagged_phonemes": ["/æ/"],
  "sentence_index": 0
}
```

That's it! No other endpoints needed.

---

## 📊 WordPress REST Endpoints

### GET /wp-json/lesaep/v1/access
Returns user's access status

**Response:**
```json
{
  "logged_in": true,
  "user_id": 123,
  "email": "user@example.com",
  "display_name": "John Doe",
  "has_paid_access": false
}
```

### GET /wp-json/lesaep/v1/lessons
Returns lessons (filtered by access)

**Response:**
```json
[
  {
    "id": 456,
    "title": "Mastering the Short A Sound",
    "excerpt": "Learn how to...",
    "content": "<h2>Introduction</h2>...",
    "is_free": true,
    "phoneme_group": "/æ/",
    "permalink": "https://dainis.net/lesaep/lesson-1/"
  }
]
```

### GET /wp-json/lesaep/v1/lessons/{id}
Returns single lesson (with access control)

### POST /wp-json/lesaep/v1/mark-paid
Manually mark user as paid (admin only)

**Request:**
```json
{
  "user_id": 123
}
```

---

## 🔧 Testing

### Test FastAPI
```bash
# Health check
curl http://localhost:8000/health

# Process audio (create test.webm first)
curl -X POST http://localhost:8000/process-audio \
  -F "audio=@test.webm" \
  -F "expected_text=The cat sat on the mat"
```

### Test WordPress
```
1. Visit: https://lesaep.com/lesaep/chat/
2. Complete quiz (record 3 sentences)
3. Login with BuddyBoss
4. View results + free lesson
5. Click "Buy Now" → Checkout page
6. Complete payment
7. Return to chat
8. ✓ Verify full access
```

### Test Access Control
```
1. Create test user
2. Add to BuddyBoss paid group
3. Visit chat → Should show full dashboard
4. Remove from group
5. Refresh chat → Should show free lesson only
```

---

## 💰 Cost Structure

**Fixed Costs:**
- DigitalOcean Droplet: $6/month (1GB RAM)
- Domain: $1/month
- **Total: $7/month**

**Variable Costs:**
- Payment gateway fees (WooCommerce/EDD/etc)
- Example: Stripe 2.9% + $0.30
- $144 sale = $4.48 fee = $139.52 profit

**No Costs:**
- Database (using WordPress)
- Email (WordPress)
- Auth (WordPress)
- Content (WordPress)
- Whisper API (self-hosted)

---

## 🎯 Why This is Better

### vs. Original Architecture:
- ❌ **Removed:** Stripe integration in FastAPI
- ❌ **Removed:** SQLite database
- ❌ **Removed:** Complex webhooks
- ❌ **Removed:** Session management
- ✅ **Added:** Native WordPress payment
- ✅ **Added:** Simpler access control
- ✅ **Result:** 70% less code, easier to maintain

### Benefits:
1. **Use existing payment system** (WooCommerce/EDD/PMPro)
2. **Familiar WordPress flow** (you already know this)
3. **No new dependencies** (Stripe SDK, etc)
4. **Easier debugging** (WordPress admin)
5. **Natural user experience** (stay on your site)

---

## 🐛 Troubleshooting

### FastAPI not responding
```bash
sudo systemctl status lesaep-backend
sudo journalctl -u lesaep-backend -n 50
```

### Whisper model not loading
```bash
cd /opt/lesaep-backend
source venv/bin/activate
pip install faster-whisper --force-reinstall
```

### Access control not working
```
1. Check BuddyBoss group ID is correct
2. Verify user is in group: WP Admin → BuddyBoss → Groups
3. Check user meta: WP Admin → Users → Edit User → Custom Fields
```

### Audio upload fails
```
1. Check Nginx timeout (should be 300s)
2. Check file size limit in php.ini
3. Test FastAPI directly: curl http://localhost:8000/health
```

---

## 📝 Lesson Content Structure

### Create Lesson Posts:
```
WP Admin → Posts → Add New

Title: Mastering the Short A Sound (/æ/)
Category: lesaep
Content: [Your lesson HTML]

Custom Fields:
- _lesaep_is_free: 1 (free) or 0 (paid)
- _lesaep_phoneme_group: /æ/

Publish
```

### Example Phoneme Groups:
- `/æ/` - Short A (cat, bat, mat)
- `/ð/` - Voiced TH (the, that)
- `/θ/` - Unvoiced TH (think, thank)
- `/r/` - R sound
- `/l/` - L sound
- `/v/` - V sound
- `/w/` - W sound
- `/ʃ/` - SH sound (she, shell)
- `/ʒ/` - ZH sound (measure, vision)

---

## 🚀 Launch Checklist

- [ ] FastAPI running: `curl http://localhost:8000/health`
- [ ] Nginx proxy working: `curl https://lesaep.com/lesaep-api/health`
- [ ] WordPress plugin activated
- [ ] Chat page created with shortcode
- [ ] BuddyBoss paid group created
- [ ] Payment system configured (WooCommerce/EDD/PMPro)
- [ ] Hook fires after payment (test purchase)
- [ ] 10-15 lesson posts created
- [ ] Access control tested
- [ ] Mobile responsive verified
- [ ] **LAUNCH January 8!** 🎉

---

## 🎉 This is WAY Simpler!

**Before:**
- FastAPI: 500+ lines
- Stripe integration
- Webhook handling
- Database management
- Complex state tracking

**After:**
- FastAPI: 150 lines (just Whisper)
- WordPress handles everything else
- Use your existing payment system
- Native BuddyBoss integration
- Natural user flow

**Result: Cleaner, simpler, easier to maintain!** 🚀

---

**Questions? Just check WordPress admin or FastAPI logs.** 

**Ready to launch by January 8!**
