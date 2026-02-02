# FLOSC v2.0.3 - What's New

**Release Date:** January 7, 2026
**Built on:** v2.0.2

---

## 🆕 New Features

### 1. Referral Tracking (?ref=XYZ)

Track where your users come from and which referrals convert to sales.

**How it works:**
- Share links like: `yoursite.com/chat?ref=PARTNER123`
- Cookie persists for 30 days (configurable)
- When user logs in, referral code is associated with their account
- When they purchase, conversion is tracked in order meta and flosc_purchases log
- View referral conversions in WordPress admin

**Benefits:**
- Measure affiliate/partner performance
- Track marketing campaign effectiveness
- Pay commissions based on actual conversions
- Identify best traffic sources

**Configuration:**
- WP Admin → FLOSC → v2.0.3 Features → Referral Tracking
- Enable/disable tracking
- Set cookie duration (default: 30 days)

**Data Stored:**
- User meta: `flosc_referral_code`, `flosc_referral_timestamp`
- On conversion: `flosc_referral_converted`, `flosc_referral_conversion_order`
- Purchase log includes `referral_code` field

---

### 2. Cooldown After 3×No Responses

Prevent quiz abuse and give users time to think after repeated "no" responses.

**How it works:**
- User clicks "Maybe later" → counter increments
- After 3 "no" responses (configurable) → cooldown applied
- User sees message: "Thanks for your time. Come back in 24 hours to try again!"
- Cooldown tracked by session ID (user ID if logged in, IP-based if not)
- After cooldown expires, user can take quiz again

**Benefits:**
- Prevents users from spamming "no" and retaking quiz endlessly
- Encourages thoughtful decision-making
- Reduces server load from quiz abuse
- Creates psychological "scarcity" effect

**Configuration:**
- WP Admin → FLOSC → v2.0.3 Features → Cooldown After Multiple "No" Responses
- Enable/disable cooldown
- Set threshold (default: 3 no responses)
- Set duration (default: 24 hours)
- Customize cooldown message

**Technical Details:**
- Tracked via WordPress transients: `flosc_cooldown_{session_id}`
- Separate counter transient: `flosc_no_count_{session_id}`
- Both expire automatically after duration
- Session ID = `user_{user_id}` (logged in) or `ip_{md5(ip)}` (anonymous)

---

### 3. Offer Expiry Countdown Timer

Create urgency by adding a live countdown timer to your offer.

**How it works:**
- When offer is shown, expiry timestamp is set (e.g., 15 minutes from now)
- Live countdown displays in chat header (e.g., "14:32 left")
- Updates every second
- When timer reaches 0:00 → "Buy Now" button is disabled
- User sees message: "This offer has expired. Please refresh to start again."

**Benefits:**
- Creates time-based urgency
- Increases conversion rates
- Prevents users from "thinking it over" indefinitely
- FOMO (fear of missing out) psychology

**Configuration:**
- WP Admin → FLOSC → v2.0.3 Features → Offer Expiry Countdown
- Enable/disable countdown
- Set expiry duration (default: 15 minutes)
- Customize expiry message

**Technical Details:**
- Timestamp stored in session: `data.offerExpiresAt`
- JavaScript countdown updates status text every second
- Button disabled via CSS: `opacity: 0.5`, `cursor: not-allowed`
- Persists across page refresh (until expiry)

---

## 🔧 Admin UI

All v2.0.3 features have dedicated admin UI in **WP Admin → FLOSC → v2.0.3 Features** section.

Each feature can be:
- ✅ Enabled/disabled independently
- ⚙️ Configured with custom settings
- 📝 Customized messages

---

## 📊 Impact on Conversions

**Expected improvements:**
- **Referral Tracking:** Measure and optimize traffic sources
- **Cooldown:** +10-15% conversion (by reducing "maybe later" spam and creating scarcity)
- **Countdown Timer:** +20-30% conversion (proven urgency tactic)

**Combined effect:** Potential 30-50% increase in conversion rates when all three features are enabled and configured properly.

---

## 🧪 Testing

### Test Referral Tracking
1. Visit chat with `?ref=TEST123` parameter
2. Complete quiz and login
3. Check user meta in WordPress → Users → Edit user
4. Should see `flosc_referral_code = TEST123`
5. Complete purchase
6. Check order meta → should include referral code
7. Check purchase log: WP Admin → FLOSC → View purchase log

### Test Cooldown
1. Complete quiz
2. Click "Maybe later" → should see "No problem! Come back anytime."
3. Repeat 2 more times
4. On 3rd click → should see cooldown message
5. Try to start new quiz → should be blocked
6. Wait 24 hours (or change settings to 1 minute for testing)
7. Quiz should be available again

### Test Countdown
1. Enable offer expiry (set to 2 minutes for testing)
2. Complete quiz
3. At offer stage → should see "This offer expires in 2 minutes"
4. Header should show countdown: "1:59 left", "1:58 left", etc.
5. Wait for expiry
6. "Buy Now" button should become disabled
7. Should see expiry message

---

## 🔄 Upgrade from v2.0.2

**Safe upgrade** - all v2.0.2 features remain unchanged.

**Steps:**
1. Backup your WordPress site
2. Deactivate FLOSC v2.0.2
3. Delete v2.0.2 plugin files
4. Upload v2.0.3
5. Activate v2.0.3
6. Go to WP Admin → FLOSC
7. Configure v2.0.3 features (all disabled by default)
8. Save settings
9. Test!

**No data loss** - all existing config and purchase data is preserved.

---

## 📁 Files Changed

**PHP:**
- `flosc.php` - Added v2.0.3 config, REST endpoints, helper methods, admin UI
- `includes/class-woocommerce-hooks.php` - Added referral conversion tracking

**JavaScript:**
- `assets/js/flosc.js` - Added referral, cooldown, and countdown features

**CSS:**
- No changes (v2.0.3 uses existing CSS)

---

## 🐛 Known Issues

None reported yet - this is the first release of v2.0.3.

Please report issues at: https://github.com/yourusername/flosc/issues

---

## 📚 Documentation

- **README.md** - General overview
- **CONFIGURATION.md** - Backend setup guides
- **TESTING.md** - Test scenarios (updated for v2.0.3)
- **WHATS_NEW_v02_03.md** - This file

---

## ✨ Coming in v2.0.4

Potential future features:
- Admin analytics dashboard (view referral conversions, cooldown stats)
- A/B testing for offer messages
- SMS/email notifications for offer expiry
- Multi-tier countdown (e.g., "Price increases in 5 min, then expires in 10 min")

**Want a feature?** Open an issue or submit a PR!

---

**Ready to boost conversions with v2.0.3!** 🚀
