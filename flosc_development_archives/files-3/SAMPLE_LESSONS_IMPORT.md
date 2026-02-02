# FLOSC Sample Lessons: Import Instructions

## 📚 What You're Importing

**10 Magnificent Pronunciation Lessons** for English numbers 1-10:

1. 🎯 ONE - The Lonely Number: Why "ONE" Has Identity Issues  
2. 👯‍♀️ TWO - The Word That Conquered 100+ Languages  
3. 🎭 THREE - The Trickster Sound That Drives Learners Mad  
4. 🚪 FOUR - The Word With A Silent Letter Identity Crisis  
5. ✋ FIVE - The Number That Can't Decide If It's Alive or Dead  
6. 🎪 SIX - The Circus Act That Happens In Your Mouth  
7. 🎰 SEVEN - The Lucky Number With An Unlucky Pronunciation  
8. 🎢 EIGHT - The Rollercoaster Your Tongue Takes  
9. 🌙 NINE - The Shapeshifter That Changed Everything  
10. 🔟 TEN - The Simplest Number With The Weirdest History

**Each lesson includes:**
- ✅ Hilarious, engaging titles
- ✅ Teaser content (before `<!--flosc_read_more-->` tag)
- ✅ Complete IPA transcriptions (MEMBER-ONLY content)
- ✅ Step-by-step pronunciation guides
- ✅ Historical linguistics & etymology
- ✅ Common mistakes with fixes
- ✅ Practice sentences
- ✅ Cultural notes
- ✅ Regional variations
- ✅ Quick reference cards

---

## 📥 How to Import

### Step 1: Install WordPress Importer Plugin

1. Go to **WordPress Admin → Tools → Import**
2. Click **"WordPress"** in the list
3. Click **"Install Now"** if not already installed
4. Click **"Run Importer"** after installation

### Step 2: Upload the XML File

1. Click **"Choose File"** button
2. Select **`flosc-sample-lessons.xml`** from your computer
3. Click **"Upload file and import"**

### Step 3: Configure Import Options

On the import page:

1. **Assign Authors:**
   - Option 1: Map to existing user (recommended: your admin account)
   - Option 2: Create new user "FLOSC Admin" (optional)

2. **Download and import file attachments:**
   - ✅ **Check this box** if you want to download any images
   - ⚠️ These lessons are text-only, so this isn't critical

3. Click **"Submit"** button

### Step 4: Verify Import

After import completes, you should see:
```
All done. Have fun!
Remember to update the passwords and roles of imported users.
```

Go to **Posts → All Posts** and verify:
- ✅ 10 new posts created
- ✅ All in "FLOSC Default Data" category
- ✅ Titles show emojis + lesson names
- ✅ Post meta fields set (check with Advanced Custom Fields or similar)

---

## 🔍 Post Meta Fields (Technical Details)

Each imported lesson has these meta fields:

| Meta Key | Value | Purpose |
|----------|-------|---------|
| `_flosc_lesson_number` | 1-10 | For RAG search by lesson number |
| `_flosc_access_level` | member | Indicates member-only content |
| `_flosc_seeded` | 1 | Marks as sample data (for cleanup) |

These are set automatically during import!

---

## 🔒 Access Control Testing

The lessons use `<!--flosc_read_more-->` to split content:

### As VISITOR (not logged in):
```
Ask AI: "What's the IPA for 'one'?"
Expected: "Take our free quiz first!"
```
✅ AI should NOT leak IPA or member content

### As GUEST (logged in):
```
Ask AI: "What's the IPA for 'one'?"
Expected: "Complete the quiz to unlock full access!"
```
✅ AI shows teasers, NO detailed IPA

### As MEMBER (quiz complete OR paid):
```
Ask AI: "What's the IPA for 'one'?"
Expected: "The IPA for 'one' is /wʌn/. Let me break it down..."
```
✅ AI has FULL access to member content

---

## 🧹 How to Remove Sample Data

If you want to delete all imported lessons:

### Option 1: Manual Deletion
1. Go to **Posts → All Posts**
2. Filter by category: **FLOSC Default Data**
3. Select all posts (checkbox at top)
4. Choose **"Move to Trash"** from bulk actions
5. Click **"Apply"**

### Option 2: Using Post Meta (Advanced)
Run this in **Tools → Database** or via code:

```php
// Find all posts with _flosc_seeded = 1
$seeded_posts = get_posts([
    'post_type' => 'post',
    'meta_key' => '_flosc_seeded',
    'meta_value' => '1',
    'posts_per_page' => -1,
]);

foreach ($seeded_posts as $post) {
    wp_delete_post($post->ID, true); // true = bypass trash
}
```

---

## 🎓 Why Import (Instead of Auto-Install)?

**Training Value:**
- ✅ Teaches you the WordPress import process
- ✅ Shows you how to structure your own lesson content
- ✅ Lets you inspect the XML format for custom lessons
- ✅ Gives you full control over when/if to add sample data

**Practical Benefits:**
- ✅ Keeps plugin lighter (no embedded data)
- ✅ No surprise content on production sites
- ✅ Easier to update lessons separately from plugin
- ✅ Can customize lessons before importing

---

## 📝 Creating Your Own Lessons

Want to create custom lessons? Follow this structure:

### Example Post Structure:

```html
<h1>Your Engaging Title Here</h1>

<p>Teaser content that hooks the learner...</p>
<p>Build curiosity without giving away the answer!</p>

<strong>Why this matters:</strong>
- Reason 1
- Reason 2
- Reason 3

<!--flosc_read_more-->

<h2>🎓 MEMBER-ONLY: The Complete Guide</h2>

<h3>IPA Transcription</h3>
<p><strong>WORD = /aɪpiːeɪ/</strong></p>

<h3>Sound-by-Sound Breakdown</h3>
<p>Full explanation here...</p>

<!-- More member content -->
```

### Important Post Meta:

```php
// In your lesson post, add these custom fields:
_flosc_lesson_number: 1 (or 2, 3, etc.)
_flosc_access_level: member
```

### Then Export for Sharing:

1. Go to **Tools → Export**
2. Select **"Posts"**
3. Choose category: **FLOSC Default Data**
4. Click **"Download Export File"**
5. Share your custom lesson pack!

---

## 🆘 Troubleshooting

### Import fails with "Parse error"
**Solution:** The XML file may be corrupted. Re-download from plugin folder.

### Posts imported but show as drafts
**Solution:** Edit each post, change status to "Published", update.

### Meta fields not set
**Solution:** Some imports skip custom fields. Add them manually via Custom Fields box.

### AI can't find lessons
**Solution:** Verify post meta `_flosc_lesson_number` is set (1-10).

### Content not split correctly
**Solution:** Check that `<!--flosc_read_more-->` tag exists in post content (edit → Text mode).

---

## 📚 Advanced: Bulk Creating Lessons

If you want to create 50+ lessons programmatically:

```php
function flosc_create_lesson($number, $title, $teaser, $member_content) {
    $content = $teaser . "\n\n<!--flosc_read_more-->\n\n" . $member_content;
    
    $post_id = wp_insert_post([
        'post_title' => $title,
        'post_content' => $content,
        'post_status' => 'publish',
        'post_type' => 'post',
        'post_category' => [get_cat_ID('FLOSC Default Data')],
    ]);
    
    if ($post_id) {
        update_post_meta($post_id, '_flosc_lesson_number', $number);
        update_post_meta($post_id, '_flosc_access_level', 'member');
    }
    
    return $post_id;
}

// Example usage:
flosc_create_lesson(
    11,
    "Lesson 11: Advanced Topic",
    "Teaser goes here...",
    "Full member content with IPA..."
);
```

---

## ✅ Import Checklist

Before importing:
- [ ] WordPress Importer plugin installed
- [ ] `flosc-sample-lessons.xml` file downloaded
- [ ] Decided on author mapping
- [ ] Ready to test access control

After importing:
- [ ] 10 posts visible in **Posts → All Posts**
- [ ] All posts in "FLOSC Default Data" category
- [ ] Meta fields verified (use Custom Fields plugin)
- [ ] Tested as visitor (content hidden)
- [ ] Tested as member (full content visible)
- [ ] AI responds correctly to quiz/access levels

---

## 🎉 You're Done!

Your FLOSC installation now has 10 magnificent sample lessons ready for testing!

**Next Steps:**
1. Take the quiz to become a "member"
2. Ask the AI about pronunciation of numbers 1-10
3. Verify access control is working
4. Start creating your own custom lessons!

**Questions?** Check the main FLOSC documentation or plugin support.
