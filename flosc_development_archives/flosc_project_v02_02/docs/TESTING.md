# FLOSC v2.0.2 - Testing Guide

**Test tonight with multiple backend configurations**

---

## Quick Start (5 Minutes)

1. **Install Plugin**
   ```
   Upload flosc_project_v02_02/wordpress-plugin/ to /wp-content/plugins/
   Activate in WordPress admin
   ```

2. **Configure Mock Backend**
   ```
   WP Admin → FLOSC → Backend Type = "Mock"
   Save Settings
   ```

3. **Create Test Page**
   ```
   Pages → Add New
   Content: [flosc]
   Publish
   ```

4. **Test Chat Flow**
   - Open page
   - Click "Yes, let's go!"
   - Record audio (any audio works in mock mode)
   - See random score
   - Complete full flow

---

## Test Scenarios

### Scenario 1: Mock Backend (No API Required)

**Setup:**
- Backend Type: Mock
- Save settings

**Test Flow:**
1. Open chat page
2. Click "Yes, let's go!"
3. Click microphone → record → stop
4. Should see random score (30-95)
5. If not logged in → see login prompt
6. Login → see results + free content
7. See offer → click "Buy Now"
8. Complete purchase (or skip for now)

**Expected Behavior:**
- ✅ Random scores generated instantly
- ✅ No backend API needed
- ✅ Full flow works without external dependencies

**Debug:**
- Open browser console (F12)
- Look for "FLOSC:" logs showing state transitions

---

### Scenario 2: FastAPI Backend

**Prerequisites:**
- FastAPI deployed to server
- Whisper model loaded
- API URL accessible

**Setup:**
- Backend Type: FastAPI
- Backend URL: `https://your-server.com/process-audio`
- Save settings

**Test Flow:**
1. Open chat (fresh incognito window)
2. Complete quiz flow
3. Should see REAL transcription + score
4. Check backend logs for processing

**Expected Behavior:**
- ✅ Audio sent to FastAPI
- ✅ Real Whisper transcription returned
- ✅ Phoneme analysis included

**Debug:**
- Check browser Network tab (F12 → Network)
- Look for POST to `/flosc/v1/process-quiz`
- Check response contains `backend_used: "fastapi"`
- Check FastAPI logs: `journalctl -u lesaep-backend -f`

**Common Issues:**
- CORS errors → Check FastAPI CORS settings
- Timeout → Check FastAPI is running
- 500 error → Check FastAPI logs

---

### Scenario 3: OpenAI API

**Prerequisites:**
- OpenAI API key (get from platform.openai.com)
- Credits in account

**Setup:**
- Backend Type: OpenAI
- API Key: `sk-...` (your key)
- Model: `gpt-4` or `gpt-3.5-turbo`
- Save settings

**Test Flow:**
1. Open chat
2. Complete quiz
3. Should see GPT-analyzed results

**Expected Behavior:**
- ✅ Audio transcribed via Whisper API
- ✅ Analysis done via GPT
- ✅ Score + phoneme feedback returned

**Debug:**
- Check console for API errors
- Verify API key is valid
- Check OpenAI dashboard for usage

**Common Issues:**
- 401 error → Invalid API key
- 429 error → Rate limit (wait or upgrade plan)
- High latency → Normal for API calls

---

### Scenario 4: Social Login (BuddyBoss)

**Prerequisites:**
- BuddyBoss plugin installed
- Social login configured (Google, Facebook, etc)

**Setup:**
- Enable Social Login: ✓ checked
- Save settings

**Test Flow:**
1. Open chat (logged out)
2. Complete quiz
3. At login gate → should see social login buttons
4. Click Google/Facebook
5. Authorize
6. Return to chat → see results automatically

**Expected Behavior:**
- ✅ Social buttons appear in chat
- ✅ No redirect to separate login page
- ✅ Seamless return to chat after login

**Debug:**
- If buttons don't appear → check BuddyBoss social login is activated
- Check console: `BB_SSO` should be defined

---

### Scenario 5: Purchase Flow (WooCommerce)

**Prerequisites:**
- WooCommerce installed
- Test product created

**Setup:**
1. Create WooCommerce product:
   - Products → Add New
   - Name: "FLOSC Test Product"
   - Price: €1 (for testing)
   - Publish
   - Copy product ID (in URL: post=123, ID is 123)

2. Configure FLOSC:
   - WooCommerce Product ID: 123 (your ID)
   - Save settings

**Test Flow:**
1. Complete quiz flow
2. Click "Buy Now" at offer stage
3. Complete WooCommerce checkout
4. Return to chat page
5. Should see paid content automatically

**Expected Behavior:**
- ✅ Redirect to checkout
- ✅ After purchase → access granted
- ✅ Refresh chat → shows full content

**Debug:**
- Check WordPress error log
- Look for: "FLOSC: ✓ Granted access via..."
- If access not granted:
  - Verify product ID is correct
  - Check order status is "completed"
  - Manually grant access: User → Edit → Meta → Add `flosc_paid_access = 1`

**Manual Access Grant:**
```php
// In WordPress admin → Users → Edit user
// Custom Fields → Add New:
// Name: flosc_paid_access
// Value: 1
```

---

### Scenario 6: Content Display

**Prerequisites:**
- Posts created in WordPress
- Category assigned

**Setup:**
1. Create category:
   - Posts → Categories → Add New
   - Name: "FLOSC Lessons"
   - Slug: `flosc-lessons`
   - Save

2. Create test posts:
   - Posts → Add New
   - Title: "Lesson 1: Introduction"
   - Content: Full lesson content with images/videos
   - Category: FLOSC Lessons
   - Publish
   - Repeat for 2-3 more posts

3. Configure FLOSC:
   - Content Category: `flosc-lessons`
   - Content Display Mode: `inline` (or `list`)
   - Save settings

**Test Flow (with paid access):**
1. Open chat with paid account
2. Should see all lessons rendered in chat
3. Scroll through lessons
4. Images/videos should display properly

**Expected Behavior:**
- ✅ All lessons appear inline
- ✅ No external links (everything in chat)
- ✅ Images/videos embedded properly

**Debug:**
- If no content appears:
  - Check category slug matches exactly
  - Verify posts are published (not draft)
  - Check user has paid access
- Check Network tab for `/flosc/v1/content` call

---

## Session Persistence Test

**Objective:** Verify state survives page refresh

**Test Flow:**
1. Start quiz → record → stop
2. **Refresh page before logging in**
3. Should return to login gate (not start over)
4. Login
5. Should see results immediately

**Expected Behavior:**
- ✅ Quiz score persists in localStorage
- ✅ State restored on page load
- ✅ No need to retake quiz

**Debug:**
- Open localStorage in browser (F12 → Application → Local Storage)
- Should see: `flosc_session` with score data

---

## Performance Tests

### Test 1: Audio Upload Size
```
Record 30 seconds → should upload successfully
Record 60 seconds → should handle large file
```

### Test 2: Multiple Users
```
Open 3 incognito windows
Complete quiz in each simultaneously
All should process without conflicts
```

### Test 3: Error Recovery
```
Kill backend mid-quiz → should show error
Retry button should work
```

---

## Integration Tests

### BuddyBoss Group Access
1. Create BuddyBoss group
2. Copy group ID
3. Configure: Paid Group ID = [your group ID]
4. Complete purchase
5. Check user is auto-added to group

### WooCommerce Webhooks
1. Enable WooCommerce webhook logging
2. Complete purchase
3. Check logs show access granted

---

## Troubleshooting Common Issues

### "Could not access microphone"
- Check browser permissions
- Try HTTPS (mic requires secure context)
- Try different browser

### "Quiz processing failed"
- Check backend is running
- Verify URL is correct
- Check CORS settings
- Try mock mode first

### "No content found"
- Verify category slug is exact
- Check posts are published
- Verify user has paid access

### Social login buttons not showing
- Verify BuddyBoss plugin active
- Check social login configured in BB settings
- Check "Enable Social Login" is checked in FLOSC

### Purchase doesn't grant access
- Check product ID is correct
- Verify WooCommerce hooks are firing
- Check WordPress error log
- Manually grant access for testing

---

## Testing Checklist

**Before Launch:**
- [ ] Mock mode works end-to-end
- [ ] Real backend processes audio correctly
- [ ] Social login appears and works
- [ ] Purchase grants access automatically
- [ ] Content displays inline in chat
- [ ] Session persists on refresh
- [ ] Mobile responsive (test on phone)
- [ ] Error messages are user-friendly

**Production Readiness:**
- [ ] Backend type set to real backend (not mock)
- [ ] Product ID configured correctly
- [ ] Content category has published posts
- [ ] SSL certificate valid (HTTPS)
- [ ] Error logging enabled
- [ ] Backup created before launch

---

## Next Steps

1. **Tonight:** Test all scenarios above
2. **Tomorrow:** Fix any issues found
3. **Day 3:** Launch to beta users
4. **Week 1:** Iterate based on feedback

**Questions?** Check CONFIGURATION.md for backend setup details.
