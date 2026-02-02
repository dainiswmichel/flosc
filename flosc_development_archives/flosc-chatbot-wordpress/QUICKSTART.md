# FLOSC + WordPress - QUICK START

## 🎯 What You Have

A **WordPress-connected FLOSC chatbot** that's ready to serve content from **dainis.net/lesaep/**

### Two Versions:

1. **`index.html`** - Standalone demo (no WordPress)
2. **`index-wordpress.html`** - WordPress-connected version ✨ **USE THIS ONE**

---

## 🚀 Test It NOW (30 seconds)

```bash
# Download the folder above
cd flosc-chatbot-wordpress

# Open in browser
open index-wordpress.html

# OR with server:
python3 -m http.server 8080
# Then open: http://localhost:8080/index-wordpress.html
```

**What happens:**
- ✅ Chatbot tries to connect to dainis.net/lesaep/
- ✅ Shows "Connected" if category exists
- ✅ Shows "Demo mode" if not yet created
- ✅ Works either way!

---

## 📝 Add WordPress Content (5 minutes)

### Step 1: Create Category
```
WordPress Admin → Posts → Categories
Name: LeSAEp
Slug: lesaep
```

### Step 2: Create a Post
```
WordPress Admin → Posts → Add New

Title: Mastering the Short A Sound
Content: [Your lesson HTML]
Category: ✓ lesaep

Custom Fields:
- Name: _flosc_is_free, Value: 1
- Name: _flosc_phoneme_group, Value: /æ/

Publish!
```

### Step 3: Refresh Chatbot
- Open `index-wordpress.html` again
- Complete the quiz
- Your WordPress post appears as a free lesson!

---

## 🏗️ The Architecture

```
Chatbot (Browser)
    ↓ Fetch via REST API
WordPress (dainis.net/lesaep/)
    ↓ Posts with custom fields
Lessons (free vs paid)
```

**No backend code needed.** Just WordPress posts!

---

## 🔑 Key Files

### `wordpress-api.js`
Connects to WordPress REST API:
```javascript
wpApi = new WordPressAPI('https://dainis.net', 'lesaep');
await wpApi.init();  // Connect
lessons = await wpApi.getLessons(freeOnly);  // Fetch
```

### `session.js`
Manages user state (localStorage):
```javascript
session = new FLOSCSession();
session.startQuiz();
session.setContact(email, phone);
session.completeQuiz(['/æ/', '/ð/']);
session.markPaid();
```

### `chatbot-wp.js`
Complete FLOSC flow with WordPress integration:
- Fetches real posts
- Shows in dashboard
- Controls access (free/paid)

---

## 📋 WordPress Custom Fields

For each lesson post, add:

| Field Name | Value | Purpose |
|------------|-------|---------|
| `_flosc_is_free` | `1` or `0` | Free lesson? |
| `_flosc_phoneme_group` | `/æ/` | Which sound? |

**That's it!** The chatbot handles everything else.

---

## 🎨 Customization

Edit `chatbot-wp.js`:

```javascript
const Config = {
    wpSiteUrl: 'https://dainis.net',    // Your WordPress
    wpCategory: 'lesaep',               // Your category
    productName: "Your Course Name",
    fullPrice: 575,
    otoPrice: 144,
    sentences: ["Your", "Test", "Sentences"]
};
```

---

## ✅ Testing Checklist

- [ ] Open `index-wordpress.html`
- [ ] See connection status (green or yellow)
- [ ] Complete quiz
- [ ] Enter email
- [ ] See results
- [ ] Choose OTO duration
- [ ] Complete "purchase"
- [ ] See dashboard
- [ ] Verify lesson access control
- [ ] Refresh → session persists

---

## 🔄 The Flow

1. **User opens chatbot** → Connects to WordPress
2. **Completes quiz** → Gets flagged phonemes
3. **Enters email** → Session saved (localStorage)
4. **Sees results** → WordPress fetches matching free lessons
5. **Views offer** → Shows total lesson count from WP
6. **Purchases** → Marks session as paid
7. **Accesses dashboard** → WordPress shows all paid lessons

**Content = WordPress posts**  
**Logic = Chatbot**  
**Perfect separation!**

---

## 📚 Documentation

- **README-WORDPRESS.md** - Complete WordPress integration guide
- **README-STANDALONE.md** - Original demo version (no WordPress)

Read **README-WORDPRESS.md** for full details!

---

## 🎯 What's Next

### Today:
1. ✅ Open `index-wordpress.html`
2. ✅ See it connect to dainis.net/lesaep/
3. ✅ Test the complete flow

### This Week:
1. Create "lesaep" category in WordPress
2. Add 3-5 lesson posts
3. Set custom fields (free/paid, phoneme)
4. Watch them appear in chatbot!

### Future:
1. Add WordPress sync endpoint
2. Connect real audio processing (Whisper)
3. Integrate Stripe payments
4. Deploy to production

---

## 💡 The Big Idea

**No content in the chatbot.** Everything comes from WordPress:

- Freeline content = Free WordPress posts
- Paid content = All WordPress posts
- Categories = Courses
- Custom fields = Access control

**You manage content in WordPress. The chatbot just displays it.**

**This is the skeleton you asked for!** 🎉

---

## 🚀 Launch Commands

```bash
# Test locally
python3 -m http.server 8080

# Open
open http://localhost:8080/index-wordpress.html

# Or just
open index-wordpress.html
```

---

**Download the folder above, open `index-wordpress.html`, and see the WordPress-connected FLOSC framework in action!**
