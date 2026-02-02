# FLOSC Framework v2.0.3 - Production Ready

**Freeline → Login → Offer → Sale → Content** conversational sales funnel

---

## 🎯 What Is This?

A **WordPress plugin** that creates an AI-powered chat funnel for selling courses/products.

**Key Features:**
- ✅ Pluggable backends (mock/FastAPI/OpenAI/Claude/custom)
- ✅ All-in-chat content (no external links)
- ✅ BuddyBoss social login integration
- ✅ WooCommerce auto-access on purchase
- ✅ Session persistence across refresh
- ✅ Mobile responsive
- ✅ Production-ready error handling

**🆕 v2.0.3 Features:**
- ✅ **Referral tracking** via ?ref=XYZ parameter
- ✅ **Cooldown after 3×no** responses (prevents abuse)
- ✅ **Offer expiry countdown** timer (creates urgency)

---

## 🚀 Quick Start (Tonight)

### 1. Install Plugin
```
Upload wordpress-plugin/ folder to /wp-content/plugins/
Activate in WordPress admin
```

### 2. Configure (Mock Mode)
```
WP Admin → FLOSC
  Backend Type: Mock
  [Save Settings]
```

### 3. Create Chat Page
```
Pages → Add New
  Title: "Try Our Quiz"
  Content: [flosc]
  [Publish]
```

### 4. Test
Open page → Complete quiz → Test full flow

**Total Time:** 5 minutes

---

## 📁 Project Structure

```
flosc_project_v02_02/
├── wordpress-plugin/          # Main plugin (upload this to WordPress)
│   ├── flosc.php             # Main plugin file (735 lines, well-commented)
│   ├── includes/             # Modular PHP classes
│   │   ├── class-backend-manager.php      # Handles all backends
│   │   ├── class-woocommerce-hooks.php    # Purchase → access
│   │   ├── class-content-renderer.php     # In-chat content display
│   │   └── class-session-manager.php      # State persistence
│   └── assets/
│       ├── css/flosc.css     # Clean, modern chat UI
│       └── js/flosc.js       # State machine + error handling
│
├── docs/
│   ├── TESTING.md           # Comprehensive test scenarios
│   ├── CONFIGURATION.md     # Backend setup guides
│   └── README.md            # This file
│
└── tests/                    # Test scenarios (optional)
```

---

## 🔧 Configuration

### Backend Options (Test Tonight)

**Mock (No Setup Required):**
```
Backend Type: Mock
[Save] → Test immediately
```

**FastAPI (Self-Hosted):**
```
Backend Type: FastAPI
Backend URL: https://api.your-domain.com/process-audio
[Save]
```

**OpenAI (Cloud API):**
```
Backend Type: OpenAI
API Key: sk-xxxxx
Model: gpt-4
[Save]
```

**See `docs/CONFIGURATION.md` for detailed setup instructions**

---

## 🎬 FLOSC Flow

```
User enters chat
  ↓
F - FREELINE (Quiz/Hook)
  ↓ Record audio
  ↓ Process with backend
  ↓
L - LOGIN GATE (if not logged in)
  ↓ Social login or email
  ↓
O - OFFER (Results + Free Content)
  ↓ Show score
  ↓ Show tier message
  ↓ Show free lesson
  ↓
S - SALE (Paid Offer)
  ↓ Present price
  ↓ "Buy Now" button
  ↓ Redirect to WooCommerce
  ↓
Purchase Complete
  ↓
C - CONTENT (Full Access)
  ↓ All lessons display in chat
  ↓ No external links
```

---

## 📖 Documentation

| File | Purpose |
|------|---------|
| `docs/TESTING.md` | Step-by-step test scenarios for tonight |
| `docs/CONFIGURATION.md` | How to setup each backend type |
| `wordpress-plugin/flosc.php` | Inline code comments (735 lines) |
| `wordpress-plugin/includes/*.php` | Each class documented |

---

## ✨ Key Features Explained

### 1. Pluggable Backends
Switch between backends in WP Admin without code changes:
- **Mock:** Testing (instant, free)
- **FastAPI:** Self-hosted Whisper ($6/mo)
- **OpenAI:** Cloud API ($0.04/quiz)
- **Custom:** Your own endpoint

### 2. All-in-Chat Content
Everything renders inline - no external links:
- Quiz results
- Free lessons
- Paid content
- Images/videos embedded

### 3. BuddyBoss Integration
- Social login buttons (Google, Facebook, etc)
- Auto-add to groups on purchase
- Seamless in-chat experience

### 4. WooCommerce Auto-Access
- User completes purchase
- Hook fires automatically
- Access granted instantly
- User refreshes chat → sees content

### 5. Session Persistence
- Quiz score saved to localStorage
- State restored on refresh
- No need to retake quiz
- 24-hour expiry

---

## 🧪 Testing Tonight

### Test 1: Mock Backend (5 min)
```
1. Configure mock backend
2. Open chat page
3. Complete quiz
4. See random score
5. Test full flow
```

### Test 2: Social Login (if BuddyBoss installed)
```
1. Enable social login
2. Complete quiz (logged out)
3. See login buttons in chat
4. Login with Google
5. Return to chat automatically
```

### Test 3: Purchase Flow (with WooCommerce)
```
1. Create test product
2. Configure product ID
3. Complete quiz → Buy
4. Complete checkout
5. Return to chat → see content
```

**See `docs/TESTING.md` for detailed scenarios**

---

## 🐛 Troubleshooting

### "Could not access microphone"
- Requires HTTPS (or localhost)
- Check browser permissions
- Try different browser

### "Quiz processing failed"
- Check backend is configured
- Try mock mode first
- Check browser console (F12)
- See CONFIGURATION.md

### Social login not showing
- Verify BuddyBoss plugin active
- Check social login enabled in BB
- Check "Enable Social Login" in FLOSC

### Purchase doesn't grant access
- Verify product ID correct
- Check order status "completed"
- Check WordPress error log
- Manually grant: User meta `flosc_paid_access = 1`

**See `docs/TESTING.md` for full troubleshooting guide**

---

## 💰 Cost Comparison

| Backend | Setup | Monthly | Per Quiz |
|---------|-------|---------|----------|
| Mock | $0 | $0 | $0 |
| FastAPI | $0 | $6 | $0 |
| OpenAI | $0 | $0 | $0.04 |

**Recommendation:**
- **Testing:** Mock
- **Beta:** OpenAI
- **Production:** FastAPI

---

## 🔐 Security

**Implemented:**
- ✅ Nonce verification on all REST calls
- ✅ Capability checks for admin functions
- ✅ Input sanitization
- ✅ File upload validation
- ✅ CORS configured properly
- ✅ Error messages don't leak sensitive info

**Recommended (before production):**
- Rate limiting on quiz endpoint
- reCAPTCHA on quiz start
- IP-based abuse detection

---

## 📊 Architecture

### PHP (WordPress Plugin)
- **Modular classes:** Each file does ONE thing
- **Hooks over hacks:** Uses WP actions/filters properly
- **Graceful degradation:** Falls back if features unavailable
- **Comprehensive logging:** All actions logged for debugging

### JavaScript (State Machine)
- **Pure state transitions:** No global pollution
- **Error boundaries:** Try/catch on all async ops
- **Loading states:** User always knows what's happening
- **Session management:** Persist across refresh

### Backend (Pluggable)
- **Interface-based:** All backends return same format
- **Fallback logic:** Mock mode always available
- **Timeout handling:** Won't hang forever
- **Error propagation:** Meaningful errors to user

---

## 🎯 Next Steps

### Tonight
1. ✅ Install plugin
2. ✅ Test with mock backend
3. ✅ Verify full flow works
4. ✅ Read TESTING.md

### This Week
1. Deploy FastAPI OR get OpenAI key
2. Test with real backend
3. Create content (posts in category)
4. Create WooCommerce product

### Before Launch
1. Choose production backend
2. Test purchase flow end-to-end
3. Verify mobile responsiveness
4. Enable error logging

### After Launch
1. Monitor usage/costs
2. Collect user feedback
3. Iterate on tier messages
4. Add more content

---

## 📝 Version History

### v2.0.2 (January 6, 2026)
- ✅ Pluggable backend architecture
- ✅ All-in-chat content rendering
- ✅ BuddyBoss social login integration
- ✅ WooCommerce auto-access hooks
- ✅ Session persistence
- ✅ Comprehensive error handling
- ✅ Loading states throughout
- ✅ Production-ready logging
- ✅ Extensive inline documentation

### v2.0.1 (ChatGPT's version)
- Basic framework structure
- Simple state machine
- Mock mode only

---

## 🤝 Support

**Questions during testing?**
1. Check `docs/TESTING.md` first
2. Check `docs/CONFIGURATION.md`
3. Check inline code comments
4. Check browser console (F12)
5. Check WordPress error log

**Common issues all documented in TESTING.md**

---

## 📜 License

This is your code, Dainis. Use it however you want.

---

## 🎉 Ready to Test!

```bash
# Install
Upload wordpress-plugin/ to WordPress

# Configure
Set Backend Type = Mock

# Test
Open chat page and complete quiz

# Iterate
Switch backends, test features, launch!
```

**Total build time:** ~10 hours
**Your test time:** 1-2 hours tonight
**Launch readiness:** Tomorrow if tests pass

**Let's ship it!** 🚀
