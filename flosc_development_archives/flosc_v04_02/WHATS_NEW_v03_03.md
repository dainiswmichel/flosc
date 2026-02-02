# FLOSC v3.0.3 - "Works Out of the Box"

## What's New

### 🔧 Critical Fix: FLOSC_CONFIG Now Passed to JavaScript
- Fixed: JavaScript config object was never being set
- All frontend functionality now receives proper configuration

### 📚 Dynamic Lesson System
- **New: Lesson Manager** (`class-lesson-manager.php`)
  - Lessons are now WordPress posts in a configured category
  - Tag posts to match quiz items (e.g., tag "5" or "phoneme-5")
  - First matched lesson is FREE, rest require payment
- **Admin Settings > Lessons Tab**
  - Select your lessons category
  - Select OTO offer to show after quiz

### 📧 Email Integration
- Quiz scores emailed to users after login
- Configurable email subject and body templates
- OTO offer included in email
- **Admin Settings > Email Tab** for templates

### 🎯 Complete FLOSC Flow
The full funnel now works:

1. **Visitor arrives** → Sees chatbot, takes quiz
2. **Quiz complete** → Score stored, prompted to login
3. **User logs in** → Score retrieved, email sent with results + OTO
4. **Free lesson delivered** → Based on what they got wrong
5. **Chatbot locked** → Free users can't continue until payment
6. **Payment** → Full access unlocked

### 🔒 Chatbot Lock for Free Users
- After free lesson delivered, chatbot shows upgrade prompt
- Prevents unlimited free usage
- Clear path to conversion

### 💾 Pre-Login Score Capture
- Scores stored in localStorage AND cookie
- Survives login/registration redirect
- Retrieved automatically after authentication

## Admin Setup Required

### 1. Create Lessons Category
```
Posts > Categories > Add "LeSAEp Lessons" (or your product name)
```

### 2. Create Lesson Posts
```
- Create posts in your lessons category
- Tag each post with the quiz item it addresses:
  - For numbers: "5", "6", "7", etc.
  - Or: "phoneme-5", "phoneme-6", etc.
```

### 3. Configure FLOSC Settings
```
FLOSC > Settings > Lessons Tab:
- Select your lessons category
- Select your OTO offer

FLOSC > Settings > Email Tab:
- Customize email templates
```

### 4. Create Offers
```
FLOSC > Offers:
- Create at least one offer for your OTO
- Set Stripe price ID for payment
```

## File Changes

### New Files
- `includes/class-lesson-manager.php` - WordPress post-based lesson system

### Modified Files
- `flosc.php` - Version bump, lesson manager, email system, new REST endpoints
- `templates/flosc-app.php` - FLOSC_CONFIG now passed to JS
- `templates/admin/settings.php` - New Lessons and Email tabs
- `includes/class-pronunciation-analyzer.php` - Dynamic lesson mapping
- `assets/js/flosc-app.js` - Pre-login score, free lesson delivery, chatbot lock

## REST API Additions

```
GET  /flosc/v1/lessons          - List all lessons (metadata)
GET  /flosc/v1/lessons/{id}     - Get single lesson with content
GET  /flosc/v1/lessons/free     - Get user's free lesson
POST /flosc/v1/store-score      - Store pre-login quiz score
```

## Testing Checklist

- [ ] Upload plugin to WordPress
- [ ] Create lessons category with test posts
- [ ] Configure Lessons tab settings
- [ ] Take quiz as visitor
- [ ] Verify score stored (check localStorage)
- [ ] Login/Register
- [ ] Verify email received with score + OTO
- [ ] Verify free lesson delivered in chat
- [ ] Verify chatbot locked after free lesson
- [ ] Complete payment (Stripe sandbox)
- [ ] Verify full access granted
