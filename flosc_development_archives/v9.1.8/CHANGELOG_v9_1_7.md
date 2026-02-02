# FLOSK v9.1.7 Changelog

## Release Date: 2025-01-20

## 🔐 Major Feature: Strict Access Level Enforcement

### Critical Security Update
**FLOSK now enforces strict content restrictions based on user access level**

---

## 🚨 What's New

### 1. Access Level Validator
**NEW FILE:** `includes/class-access-validator.php`

Scans every AI response before sending to user:
- ✅ Detects forbidden keywords (IPA symbols, member content)
- ✅ Validates access level compliance
- ✅ Blocks content leakage automatically
- ✅ Logs security violations
- ✅ Returns safe fallback responses

**Example:**
```
Visitor asks: "What's the IPA for 'one'?"
AI tries to respond: "The IPA is /wʌn/..."
Validator: ❌ BLOCKED - IPA shown to visitor
User sees: "Take our free quiz first - it's just 2 minutes!"
```

### 2. Strict System Prompts
Updated AI instructions for each access level:

**VISITOR (Not logged in):**
- ❌ NO lesson content
- ❌ NO IPA transcriptions  
- ❌ NO pricing
- ✅ ONLY quiz prompts
- Every response redirects to quiz

**GUEST (Logged in, not member):**
- ✅ Quiz results
- ✅ Lesson TITLES only
- ✅ Pricing and offers
- ❌ NO detailed content
- ❌ NO IPA transcriptions
- Creates urgency with timer

**MEMBER (Full access):**
- ✅ All content
- ✅ IPA transcriptions
- ✅ Complete guides
- Uses RAG search tools
- Still acts as GUIDE (not teacher)

### 3. IVR Editor Confirmed
**Verified existing IVR functionality is intact:**
- ✅ Vertical scrolling editor
- ✅ Upload/Download ivr.md files
- ✅ Add new entries (merge mode)
- ✅ Replace all entries
- ✅ Individual message editing
- ✅ Condition builder
- ✅ Auto-prompts for each access level

Located at: `WordPress Admin → FLOSC → IVR Messages`

---

## 🔍 Access Control Flow

```
User sends message
    ↓
Get user's access level (visitor/guest/member)
    ↓
Build system prompt with STRICT rules
    ↓
AI generates response
    ↓
🔐 VALIDATOR scans response
    ↓
If violations found → Block & use safe fallback
If clean → Send to user
```

---

## 📋 Testing Guide

**NEW FILE:** `ACCESS_LEVEL_TESTING.md`

Complete testing procedures:
- Test scenarios for each access level
- Expected vs bad responses
- Security violation detection
- Automated test checklist
- Log monitoring guide

---

## 🎯 Enforcement Rules

### What Each Level Can Do:

**VISITOR:**
```
CAN:
- Be encouraged to take quiz
- Hear about the product generally

CANNOT:
- See any lesson content
- See any pricing
- See any IPA or guides

MUST:
- Every response redirect to quiz
```

**GUEST:**
```
CAN:
- See quiz results
- See lesson titles
- See pricing offers
- See timer/urgency

CANNOT:
- See detailed lesson content
- See IPA transcriptions
- See step-by-step guides

MUST:
- Present offers with urgency
- Show what they'll get
```

**MEMBER:**
```
CAN:
- See all content
- See IPA transcriptions
- Access full guides
- Use all search tools

CANNOT:
- [No restrictions]

MUST:
- Act as guide to content
- Point to WordPress lessons
```

---

## 🔧 Technical Implementation

### New Classes:
1. **FLOSC_Access_Validator**
   - `validate_response()` - Scans AI output
   - `check_visitor_violations()` - Visitor-specific checks
   - `check_guest_violations()` - Guest-specific checks
   - `get_safe_fallback_response()` - Returns safe message

### Updated Methods:
1. **handle_chat_with_rag()**
   - Now validates response before returning
   - Logs violations
   - Uses fallback if needed

2. **build_rag_system_prompt()**
   - Much stricter instructions
   - Clear examples of good/bad responses
   - Emoji warnings (🚨) for critical rules

3. **get_access_level_instructions()**
   - Complete rewrite with strict rules
   - Forbidden content lists
   - Example responses

---

## 📊 Security Logging

All violations logged to WordPress debug log:

```
FLOSC SECURITY ALERT: Content leakage prevented
FLOSC SECURITY: Original response: "The IPA is /wʌn/..."
FLOSC SECURITY: Violations: [{"keyword":"/w/","reason":"IPA transcription"}]
```

Enable debugging:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

---

## 🚀 Upgrade from v9.1.6

1. Backup your installation
2. Replace plugin files
3. No database changes needed
4. Test with ACCESS_LEVEL_TESTING.md
5. Monitor logs for 24 hours

---

## ✅ Backwards Compatibility

- ✅ Fully compatible with v9.1.6
- ✅ All existing IVR functions work
- ✅ Quiz system unchanged
- ✅ Payment integration ready
- ✅ RAG tools functioning

---

## ⚠️ Breaking Changes

None! This is a security enhancement that adds validation without changing existing functionality.

---

## 🐛 Known Limitations

- Validator checks for common keywords only
- Very clever prompt injection might bypass
- Recommend manual spot-checking initially
- Vector similarity detection (future enhancement)

---

## 📖 Files Modified

### New Files:
- `includes/class-access-validator.php`
- `ACCESS_LEVEL_TESTING.md`
- `CHANGELOG_v9_1_7.md`

### Modified Files:
- `flosc.php`
  - Added validator integration
  - Updated system prompts
  - Added response validation

### Unchanged:
- All IVR files (confirmed working)
- RAG manager
- Content filter
- User access manager

---

## 🎓 Usage Examples

### Example 1: Visitor Protection
```
User (visitor): "What's the IPA for 'seven'?"

Without validator ❌:
"The IPA is /ˈsɛvən/..." ← LEAK!

With validator ✅:
"Great question! Take our free 2-minute quiz first!"
```

### Example 2: Guest Protection
```
User (guest): "How do I pronounce 'six'?"

Without validator ❌:
"Here's how to pronounce it: /sɪks/..." ← LEAK!

With validator ✅:
"You scored 45% on Lesson 6! 🔥 $30 offer - 28 min left!"
```

### Example 3: Member Access
```
User (member): "Show me the IPA for 'three'"

Response ✅:
"As a member, here's the full guide: /θriː/ [Link to Lesson 3]"
```

---

## 🔒 Security Features Summary

1. ✅ Keyword scanning (IPA symbols, forbidden terms)
2. ✅ Access level validation
3. ✅ Automatic content blocking
4. ✅ Safe fallback responses
5. ✅ Security logging
6. ✅ Strict system prompts
7. ✅ Multiple validation layers

---

## 📞 Support

If you encounter content leakage:
1. Check logs for violations
2. Review ACCESS_LEVEL_TESTING.md
3. Run test scenarios
4. Report with query + response + access level

---

## 🎯 Next Version (9.1.8)

Planned features:
- Payment integration (Stripe/PayPal)
- Advanced vector-based leak detection
- Admin dashboard for violations
- Daisy-chain AI (optional 3-AI system)
- Enhanced quiz types

---

## Credits

**Security enhancement developed with:**
- Claude Sonnet 4.5
- Dainis Michel (FLOSK Framework)
- Focus on protecting member content
- Strict access level enforcement

**Version:** 9.1.7
**Release:** 2025-01-20
**Status:** Production Ready (with testing)
