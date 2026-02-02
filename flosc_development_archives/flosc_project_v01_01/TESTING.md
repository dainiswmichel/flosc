# LeSAEp FLOSC - Testing Guide
## Complete End-to-End Testing Checklist

---

## 🎯 Testing Overview

Test **everything** in this order:
1. Backend API (isolated)
2. WordPress Plugin (isolated)
3. Integration (WordPress → Backend)
4. Full User Flow (end-to-end)
5. Edge Cases & Error Handling

---

## ✅ PHASE 1: Backend API Testing

### 1.1 Health Check
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

### 1.2 Session Creation
```bash
curl -X POST http://localhost:8000/chat/start
```

**Expected:**
```json
{
  "session_id": "uuid...",
  "quiz_id": "uuid...",
  "existing": false
}
```

### 1.3 Get Sentences
```bash
curl http://localhost:8000/chat/get-sentences
```

**Expected:**
```json
{
  "sentences": [
    "The cat sat on the mat",
    "She sells seashells by the seashore",
    "How now brown cow"
  ]
}
```

### 1.4 Audio Upload (Manual Test)
```bash
# Record a test audio file (test.webm)
# Then upload:
curl -X POST http://localhost:8000/chat/upload-audio \
  -F "audio=@test.webm" \
  -F "expected_text=The cat sat on the mat" \
  -F "sentence_index=0"
```

**Expected:**
```json
{
  "session_id": "uuid...",
  "transcription": "the cat sat on the mat",
  "accuracy": 0.95,
  "flagged_phonemes": ["/æ/"],
  "sentence_index": 0
}
```

### 1.5 Payment Intent Creation
```bash
# First get a valid token from WordPress (see next section)
curl -X POST http://localhost:8000/stripe/create-intent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "amount=14400" \
  -F "user_id=1"
```

**Expected:**
```json
{
  "client_secret": "pi_xxx_secret_xxx",
  "payment_intent_id": "pi_xxx"
}
```

---

## ✅ PHASE 2: WordPress Plugin Testing

### 2.1 Plugin Activation
```
✓ Plugin appears in: WP Admin → Plugins
✓ No PHP errors in debug.log
✓ Settings page loads: WP Admin → LeSAEp Chat
```

### 2.2 Settings Page
```
✓ Can save all settings
✓ Session secret auto-generates if empty
✓ No errors on save
```

### 2.3 Shortcode Rendering
```
Create test page with [lesaep_chat]

✓ Chat window appears
✓ Header shows correctly
✓ Bot avatar visible
✓ CSS loads (gradient header, rounded corners)
✓ No JavaScript errors in browser console
```

### 2.4 Session Token Endpoint
```bash
# With logged-in user cookie
curl https://dainis.net/wp-json/lesaep/v1/session \
  --cookie "wordpress_logged_in_xxx=..."
```

**Expected:**
```json
{
  "logged_in": true,
  "user_id": 123,
  "email": "user@example.com",
  "display_name": "User Name",
  "has_paid_access": false,
  "lesaep_token": "eyJ1c2VyX2lkIjoxMjN9.abc123..."
}
```

### 2.5 Lessons Endpoint
```bash
# Get all lessons
curl https://dainis.net/wp-json/lesaep/v1/lessons

# Get free lessons only (no user_id)
curl "https://dainis.net/wp-json/lesaep/v1/lessons"

# Get lessons for paid user
curl "https://dainis.net/wp-json/lesaep/v1/lessons?user_id=123"
```

**Expected:** JSON array of lesson objects

### 2.6 Lesson Content Creation
```
Create test lesson:

Title: Test Lesson 1
Category: lesaep
Custom Fields:
  _lesaep_is_free: 1
  _lesaep_phoneme_group: /æ/

✓ Appears in lessons endpoint
✓ Custom fields saved correctly
```

---

## ✅ PHASE 3: Integration Testing

### 3.1 Token Verification
```bash
# Get token from WordPress
TOKEN=$(curl https://dainis.net/wp-json/lesaep/v1/session --cookie "..." | jq -r .lesaep_token)

# Test in backend
curl -X POST http://localhost:8000/chat/start \
  -H "Authorization: Bearer $TOKEN"
```

**Expected:** No 401 error, returns session

### 3.2 Nginx Proxy
```bash
# Test from external
curl https://lesaep.com/lesaep-api/health
```

**Expected:** Same response as localhost:8000/health

### 3.3 CORS (if not using proxy)
```javascript
// In browser console on lesaep.com
fetch('https://lesaep.com/lesaep-api/health')
  .then(r => r.json())
  .then(d => console.log(d))
```

**Expected:** No CORS errors

---

## ✅ PHASE 4: Full User Flow Testing

### Test 1: Anonymous User (Not Logged In)

#### 4.1 Welcome Message
```
✓ Visit https://lesaep.com/lesaep/chat/
✓ Bot greeting appears
✓ "Want free analysis?" message
✓ Quick reply buttons: "Yes" / "Tell me more" / "No thanks"
```

#### 4.2 No Gate
```
✓ Click "No thanks"
✓ Bot explains value
✓ Offers again
✓ Click "No" again
✓ Third "No" → Bot says goodbye + blocks
✓ Refresh → Can start over
```

#### 4.3 Start Quiz
```
✓ Click "Yes, let's do it!"
✓ Bot explains process
✓ "I'm ready!" button appears
✓ Click → Recorder shows
```

#### 4.4 Audio Recording
```
✓ Sentence displayed: "The cat sat on the mat"
✓ Microphone button visible
✓ Click mic → Browser requests permission
✓ Grant permission
✓ Recording indicator shows (red pulse)
✓ Waveform animates
✓ Click stop → Upload starts
✓ Bot says "Got it! Analyzing..."
✓ Bot says "Nice work!"
✓ Progress: "Sentence 1 of 3"
```

#### 4.5 Complete Quiz
```
✓ Record all 3 sentences
✓ Bot: "Excellent! Analysis complete"
✓ Bot: "Login to see results"
✓ Login button appears
```

### Test 2: Logged-In User (Free Access)

#### 4.6 Login Flow
```
✓ Click "Login to See Results"
✓ Redirects to WordPress login
✓ Login with Google/Facebook/Apple (BuddyBoss)
✓ Redirects back to chat
✓ Chat remembers quiz state
```

#### 4.7 Results Display
```
✓ Bot: "Here are your results..."
✓ Shows flagged phonemes: "/æ/, /ð/, /r/"
✓ Explains results
✓ Fetches free lesson from WordPress
✓ Lesson card appears in chat
✓ Can click "View my free lesson"
```

#### 4.8 View Lesson
```
✓ Lesson content displays in chat
✓ HTML formatting preserved
✓ Images load (if any)
✓ Can scroll through lesson
```

#### 4.9 OTO Offer
```
✓ Bot shows OTO card
✓ Pricing shows: $575 → $144 (75% off)
✓ Three timer options appear
✓ Click "1 hour"
✓ Countdown overlay appears (bottom-right)
✓ Timer counts down
✓ "Buy Now" button appears
```

### Test 3: Payment Flow

#### 4.10 Stripe Payment
```
✓ Click "Get 75% OFF"
✓ Payment modal appears IN CHAT
✓ Stripe Payment Element loads
✓ Enter test card: 4242 4242 4242 4242
✓ Expiry: any future date
✓ CVC: any 3 digits
✓ ZIP: any 5 digits
✓ Click "Pay Now"
✓ Processing indicator
✓ Success message
✓ Modal closes
```

#### 4.11 Post-Payment
```
✓ Bot: "Payment successful!"
✓ Bot: "Welcome to the course!"
✓ Countdown timer disappears
✓ Webhook fires (check backend logs)
✓ User added to BuddyBoss group (check WP)
```

### Test 4: Paid User Access

#### 4.12 Dashboard
```
✓ Bot: "Loading your course..."
✓ Shows lesson count: "You have access to X lessons"
✓ Lesson cards appear (5 at a time)
✓ Each card shows:
  - Title
  - Excerpt
  - Badge: "FULL ACCESS"
✓ Can click "Show more lessons"
```

#### 4.13 View Paid Lessons
```
✓ Click any lesson card
✓ Full lesson loads in chat
✓ Can navigate: Next / Previous
✓ All lessons accessible
```

#### 4.14 Return Visit
```
✓ Close chat
✓ Come back later
✓ Bot: "Welcome back, [Name]!"
✓ Immediately shows dashboard
✓ No need to re-quiz
```

---

## ✅ PHASE 5: Edge Cases

### 5.1 Session Expiry
```
✓ Get token
✓ Wait 1+ hour
✓ Try to use expired token
✓ Gets 401 error or prompts re-login
```

### 5.2 OTO Expiry
```
✓ Start OTO timer (1 hour)
✓ Wait for expiry
✓ Try to purchase
✓ Backend denies (403 error)
✓ Chat shows "Offer expired"
```

### 5.3 Microphone Denied
```
✓ Click record
✓ Deny mic permission
✓ Error message shows
✓ Suggests enabling mic
```

### 5.4 Audio Upload Failure
```
✓ Simulate network failure
✓ Upload times out
✓ Error message shows
✓ Offers to retry
```

### 5.5 Payment Failure
```
✓ Use declined card: 4000 0000 0000 0002
✓ Payment fails
✓ Error message shows
✓ Can retry
```

### 5.6 No Internet
```
✓ Disconnect internet
✓ Try to interact
✓ Error messages appropriate
✓ Reconnect → Resumes
```

### 5.7 Multiple Tabs
```
✓ Open chat in 2 tabs
✓ Complete actions in one
✓ Other tab syncs (or shows reload prompt)
```

---

## ✅ PHASE 6: Mobile Testing

### 6.1 Responsive Design
```
✓ Test on iPhone (Safari)
✓ Test on Android (Chrome)
✓ Chat fills screen (100vh)
✓ Buttons easy to tap
✓ Recorder works on mobile
✓ Payment form mobile-friendly
✓ Countdown timer positioned well
```

### 6.2 Mobile Audio
```
✓ Microphone permission flow
✓ Recording works
✓ Upload works on cellular
```

---

## ✅ PHASE 7: Performance Testing

### 7.1 Load Time
```
✓ Chat loads in < 2 seconds
✓ JavaScript loads async
✓ CSS doesn't block render
```

### 7.2 Audio Processing
```
✓ Whisper processes in < 10 seconds
✓ Shows progress indicator
✓ Doesn't timeout
```

### 7.3 Concurrent Users
```
✓ Test with 5-10 simultaneous users
✓ Backend doesn't crash
✓ Response times acceptable
```

---

## ✅ PHASE 8: Security Testing

### 8.1 Token Security
```
✓ Token expires after 1 hour
✓ Invalid signature rejected
✓ Tampered token rejected
```

### 8.2 Payment Security
```
✓ Card data never touches backend
✓ Stripe webhook signature verified
✓ Can't create intent after OTO expiry
```

### 8.3 Access Control
```
✓ Free users see only free lessons
✓ Paid users see all lessons
✓ Can't access paid lessons without payment
```

---

## 🐛 Bug Tracking Template

```
Bug ID: #001
Title: [Brief description]
Steps to Reproduce:
1. 
2. 
3. 

Expected: 
Actual: 
Screenshot: 
Priority: High / Medium / Low
Status: Open / Fixed / Closed
```

---

## ✅ Pre-Launch Final Checklist

### Critical
- [ ] All API endpoints working
- [ ] Token verification working
- [ ] Audio recording/upload working
- [ ] Whisper transcription working
- [ ] Stripe test payments working
- [ ] BuddyBoss group assignment working
- [ ] Lesson access control working
- [ ] Mobile responsive

### Important
- [ ] Error messages clear
- [ ] Loading indicators show
- [ ] Countdown timer accurate
- [ ] Email notifications working
- [ ] SSL certificate valid
- [ ] HTTPS redirects working

### Nice to Have
- [ ] Animations smooth
- [ ] Typography polished
- [ ] Copy edited
- [ ] Help text clear

---

## 📊 Test Results Template

```
Test Date: YYYY-MM-DD
Tester: [Name]
Environment: Production / Staging / Local

Phase 1: Backend API         ✓ / ✗
Phase 2: WordPress Plugin     ✓ / ✗
Phase 3: Integration          ✓ / ✗
Phase 4: User Flow            ✓ / ✗
Phase 5: Edge Cases           ✓ / ✗
Phase 6: Mobile               ✓ / ✗
Phase 7: Performance          ✓ / ✗
Phase 8: Security             ✓ / ✗

Bugs Found: [Number]
Critical Issues: [Number]
Ready for Launch: Yes / No

Notes:
[...]
```

---

**Test thoroughly before launch!** 🧪🚀
