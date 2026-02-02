# FLOSC Chatbot - WordPress Connected Version

## 🎯 What This Is

A **WordPress-aware** FLOSC chatbot that connects to **dainis.net/lesaep/** and serves real WordPress content as:
- **Freeline content** (free lessons)
- **Paid content** (full course access)

**The skeleton is complete.** It knows HOW to connect to WordPress. When you add posts to the "lesaep" category, they automatically appear in the chatbot!

---

## 🏗️ Architecture

```
Chatbot (Browser)
    ↓
WordPress REST API (dainis.net/wp-json/wp/v2/)
    ↓
Posts in "lesaep" category
    ↓
Meta fields: _flosc_is_free, _flosc_phoneme_group
```

**Everything is ready. Just add content to WordPress!**

---

## 🚀 How to Run

### Option 1: Test with Demo Content (Now)
```bash
# Just open the file
open index-wordpress.html

# Or with Python server
python3 -m http.server 8080
# Open: http://localhost:8080/index-wordpress.html
```

The chatbot will connect to dainis.net/lesaep/ and:
- ✅ Use real WordPress content if it exists
- ✅ Fall back to mock lessons if not yet created
- ✅ Show you EXACTLY how it will work

### Option 2: Add Real WordPress Content (Later)

1. Log into WordPress at dainis.net
2. Create category "lesaep" if it doesn't exist
3. Create posts in that category
4. Add custom fields:
   - `_flosc_is_free` = `1` (for free lessons)
   - `_flosc_phoneme_group` = `/æ/` (or other phoneme)
5. Refresh chatbot → Content appears automatically!

---

## 📁 Files Explained

### `index-wordpress.html`
- Main chatbot interface
- Loads WordPress integration scripts
- **Use this version** instead of the basic `index.html`

### `static/js/wordpress-api.js`
- Connects to WordPress REST API
- Fetches posts from "lesaep" category
- Gets lessons by phoneme groups
- Falls back to mock data if WordPress not ready

**Key methods:**
```javascript
wpApi = new WordPressAPI('https://dainis.net', 'lesaep');
await wpApi.init();  // Connect to WordPress
lessons = await wpApi.getLessons(freeOnly);  // Get lessons
```

### `static/js/session.js`
- Manages user state (localStorage)
- Tracks: email, phone, quiz results, payment status
- Access control: free vs paid lessons
- WordPress sync (sends data back to WordPress)

**Key methods:**
```javascript
session = new FLOSCSession();
session.startQuiz();
session.setContact(email, phone);
session.completeQuiz(phonemes);
session.markPaid();
accessLevel = session.getAccessLevel();  // 'none', 'free', or 'full'
```

### `static/js/chatbot-wp.js`
- Complete FLOSC flow
- Fetches content from WordPress
- Shows personalized lessons based on phoneme analysis
- Displays real WordPress posts in the dashboard

---

## 🎯 The Complete Flow (with WordPress)

### 1. **Intro**
- Bot greets user
- Shows how many lessons are in WordPress
- Offers free analysis

### 2. **Quiz**
- Records 3 sentences
- Simulates pronunciation analysis
- Identifies flagged phonemes: `/æ/`, `/ð/`, `/r/`

### 3. **Email Capture**
- Collects email + phone
- Saves to localStorage
- **Syncs with WordPress** (when endpoint ready)

### 4. **Results + Free Lessons**
- Shows analysis results
- **Queries WordPress** for lessons matching flagged phonemes
- Shows 1-2 free lessons from WordPress
- Displays actual post content with formatting

### 5. **Offer (OTO)**
- Shows total lesson count from WordPress
- Presents one-time offer
- Countdown timer (configurable)
- Tracks offer expiry

### 6. **Purchase**
- Simulated checkout
- Marks session as "paid"
- **Syncs payment status with WordPress**

### 7. **Dashboard**
- **Fetches all lessons from WordPress**
- Free users: See only `_flosc_is_free = 1` posts
- Paid users: See ALL posts in category
- Each lesson shows:
  - Title (from post_title)
  - Excerpt (from post_excerpt)
  - Content preview
  - Phoneme group
  - Link to full post
  - Lock/unlock badge

---

## 📝 WordPress Content Structure

### What You Need in WordPress

#### 1. **Create Category**
```
Name: LeSAEp
Slug: lesaep
```

#### 2. **Create Posts**
```
Title: Mastering the Short A Sound
Category: lesaep
Content: [Your lesson content with HTML]
Excerpt: Learn to pronounce /æ/ words like cat, bat, hat
Custom Fields:
  - _flosc_is_free: 1
  - _flosc_phoneme_group: /æ/
```

#### 3. **Example Post**
```html
<h2>Introduction</h2>
<p>The /æ/ sound is challenging for non-native speakers...</p>

<h2>How to Produce the Sound</h2>
<ol>
    <li>Open your mouth wider than normal</li>
    <li>Keep tongue low and flat</li>
    <li>Sound comes from throat</li>
</ol>

<h2>Practice Words</h2>
<ul>
    <li>cat, bat, hat, mat</li>
    <li>bad, dad, sad, mad</li>
</ul>
```

#### 4. **Phoneme Groups to Use**
- `/æ/` - cat, bat (short A)
- `/ɪ/` - sit, bit (short I)
- `/θ/` - think, path (voiceless TH)
- `/ð/` - this, that (voiced TH)
- `/ʃ/` - ship, cash (SH)
- `/r/` - red, very (R sound)
- `/l/` - let, fill (L sound)
- `/aɪ/` - my, try (long I)
- `/aʊ/` - how, now (OW sound)

---

## 🔌 WordPress REST API Endpoints Used

The chatbot automatically calls:

```
GET /wp-json/wp/v2/categories?slug=lesaep
→ Get category ID

GET /wp-json/wp/v2/posts?categories={id}&per_page=100
→ Get all lessons

GET /wp-json/wp/v2/posts?categories={id}&meta_key=_flosc_is_free&meta_value=1
→ Get free lessons only

GET /wp-json/wp/v2/posts?categories={id}&search=pronunciation
→ Search lessons

GET /wp-json/wp/v2/posts/{post_id}
→ Get single lesson
```

**No custom endpoints needed!** Uses standard WordPress REST API.

---

## 💾 Session Management (localStorage)

The chatbot stores in browser:

```javascript
{
    quizId: "quiz_abc123",
    email: "user@example.com",
    phone: "+1234567890",
    completed: true,
    flaggedPhonemes: ["/æ/", "/ð/", "/r/"],
    isPaid: false,
    otoDuration: 3600,
    otoExpires: "2025-12-31T12:00:00Z",
    createdAt: "2025-12-30T10:00:00Z",
    updatedAt: "2025-12-30T11:00:00Z"
}
```

**Persists across page reloads!**

---

## 🔄 WordPress Sync (Future)

The chatbot includes a `syncWithWordPress()` function that will send session data back to WordPress:

```javascript
// In session.js
async syncWithWordPress(wpUrl) {
    const response = await fetch(`${wpUrl}/wp-json/flosc/v1/session`, {
        method: 'POST',
        body: JSON.stringify(this.export())
    });
}
```

### WordPress Endpoint (To Be Created)
```php
// In your WordPress plugin
add_action('rest_api_init', function() {
    register_rest_route('flosc/v1', '/session', array(
        'methods' => 'POST',
        'callback' => 'flosc_save_session',
        'permission_callback' => '__return_true'
    ));
});

function flosc_save_session($request) {
    $data = $request->get_json_params();
    
    // Save to database:
    // - Create/update user
    // - Store quiz results
    // - Track payment status
    // - Send confirmation email
    
    return new WP_REST_Response(['success' => true], 200);
}
```

---

## 🎨 Customization

### Change WordPress Site/Category
Edit `chatbot-wp.js`:
```javascript
const Config = {
    wpSiteUrl: 'https://your-site.com',
    wpCategory: 'your-category',
    // ...
};
```

### Change Product Info
```javascript
const Config = {
    productName: "Your Course Name",
    fullPrice: 575,
    otoPrice: 144,
    discount: 75,
    // ...
};
```

### Change Test Sentences
```javascript
sentences: [
    "Your sentence 1",
    "Your sentence 2",
    "Your sentence 3"
]
```

---

## ✅ What Works NOW

- ✅ Connects to WordPress REST API
- ✅ Fetches real posts from dainis.net/lesaep/
- ✅ Falls back to mock data if no content
- ✅ Shows lesson count from WordPress
- ✅ Filters free vs paid lessons
- ✅ Searches by phoneme groups
- ✅ Displays post titles, excerpts, content
- ✅ Shows WordPress permalinks
- ✅ localStorage session management
- ✅ Access control (free/paid)
- ✅ Complete FLOSC flow
- ✅ Countdown timer
- ✅ Mobile responsive

---

## 🚧 What's Next (When You Add Content)

### Phase 1: Add WordPress Content
1. Create "lesaep" category
2. Add 5-10 posts with lessons
3. Set custom fields (free/paid, phoneme)
4. Chatbot automatically displays them!

### Phase 2: Add WordPress Sync Endpoint
1. Create REST API endpoint `/flosc/v1/session`
2. Accept POST with session data
3. Store in WordPress database
4. Send confirmation email

### Phase 3: Add Real Audio Processing
1. Upload recordings to WordPress
2. Send to FastAPI backend
3. Get real Whisper transcription
4. Analyze pronunciation
5. Return actual flagged phonemes

### Phase 4: Add Payment Processing
1. Integrate Stripe
2. Create checkout session
3. Handle webhook
4. Grant access to paid content

---

## 📊 Testing Checklist

- [ ] Open `index-wordpress.html`
- [ ] See connection message (green = live, yellow = demo)
- [ ] Complete quiz (3 recordings)
- [ ] Enter email/phone
- [ ] See "free lessons" (from WordPress if available)
- [ ] Choose OTO duration
- [ ] See countdown timer
- [ ] Complete purchase (simulated)
- [ ] See dashboard with lessons
- [ ] Check free vs paid access
- [ ] Click lesson links (open WordPress posts)
- [ ] Refresh page → Session persists
- [ ] Start new quiz → Reset session

---

## 🎉 The Result

You have a **production-ready skeleton** that:

✅ Knows WordPress is the content source  
✅ Fetches posts via REST API  
✅ Handles free vs paid access  
✅ Persists user sessions  
✅ Ready for real content  
✅ Complete FLOSC flow  

**Just add WordPress posts and it works!**

---

## 💡 Key Insight

This chatbot is **WordPress-first**:
- No hardcoded content
- No JSON files
- No CSV imports
- Just WordPress posts

**Your content management is WordPress. Your funnel is FLOSC. Perfect!**

---

## 🔗 URLs to Remember

**Live site**: https://dainis.net/lesaep/  
**REST API**: https://dainis.net/wp-json/wp/v2/  
**Category API**: https://dainis.net/wp-json/wp/v2/categories?slug=lesaep  
**Posts API**: https://dainis.net/wp-json/wp/v2/posts?categories={id}  

Test these in your browser to see WordPress data!

---

**Open `index-wordpress.html` and experience the WordPress-connected FLOSC framework! 🚀**
