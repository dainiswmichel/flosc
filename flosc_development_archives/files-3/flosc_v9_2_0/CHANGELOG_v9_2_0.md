# FLOSC v9.2.0 - Access Control Shortcodes & Sample Data Improvements

**Release Date:** January 21, 2026  
**Type:** Feature Release  
**From:** v9.1.9a → v9.2.0

---

## 🎯 Major Features

### 1. **New Access Control Shortcodes**

Added two powerful shortcodes for content protection:

#### `[flosc_visitor_only]`
Shows content ONLY to non-logged-in visitors.

**Usage:**
```
[flosc_visitor_only]
  Take our free quiz to get started! → [Start Quiz]
[/flosc_visitor_only]
```

**Perfect for:**
- Quiz CTAs that disappear after login
- Visitor-specific messaging
- Registration prompts

#### `[flosc_member_only]`
Shows content ONLY to members (quiz complete OR paid).

**Basic Usage:**
```
[flosc_member_only]
  <h2>Welcome, Member!</h2>
  <p>Here's your exclusive IPA transcription...</p>
[/flosc_member_only]
```

**With Fallback Message:**
```
[flosc_member_only fallback="Upgrade to see this content"]
  Premium member content here
[/flosc_member_only]
```

**Perfect for:**
- Inline member-only sections
- Premium content blocks
- IPA transcriptions
- Advanced lessons

### 2. **Custom Content Split Tag: `<!--flosc_read_more-->`**

**Why the change?**
- ✅ Avoids conflicts with WordPress's native `<!--more-->` tag
- ✅ FLOSC-specific behavior (access control, not just truncation)
- ✅ Clearer intent in lesson content
- ✅ **Still supports `<!--more-->` for backwards compatibility!**

**How it works:**
```html
Public content visible to everyone (teaser)

<!--flosc_read_more-->

Member-only content here (IPA, detailed guides, etc.)
```

**Access Levels:**
- **Visitor:** Sees only teaser
- **Guest:** Sees teaser + "🔒 Complete quiz to unlock"
- **Member:** Sees EVERYTHING

**Backwards Compatible:**
- Old posts with `<!--more-->` still work!
- System checks for both tags: `<!--flosc_read_more-->` OR `<!--more-->`

### 3. **Sample Data as Importable XML**

**NEW APPROACH:** Sample lessons are now a separate import file, not auto-installed.

**Why this is better:**
- ✅ **Trains users** on WordPress import process
- ✅ **No surprise content** on production sites
- ✅ **Lighter plugin** (no embedded 4,000+ line data)
- ✅ **User control** over when/if to add samples
- ✅ **Easier to customize** lessons before importing
- ✅ **Teaches content structure** for creating own lessons

**What's included:**
- 📄 `flosc-sample-lessons.xml` - WordPress XML export format
- 📄 `SAMPLE_LESSONS_IMPORT.md` - Comprehensive import guide
- 📚 10 complete lessons (same magnificent content!)
- 🎓 All IPA transcriptions, practice drills, etc.

**File Details:**
- **Format:** WordPress XML (WXR)
- **Size:** ~129 KB (all 10 lessons)
- **Category:** "FLOSC Default Data" (auto-created)
- **Post Meta:** Properly set (`_flosc_lesson_number`, `_flosc_access_level`, `_flosc_seeded`)
- **Split Tag:** Uses new `<!--flosc_read_more-->`

---

## 📁 New Files

### Core Functionality
- `includes/class-shortcode-handler.php` - Shortcode system (106 lines)

### Sample Data
- `flosc-sample-lessons.xml` - WordPress XML import (129 KB, 10 lessons)
- `SAMPLE_LESSONS_IMPORT.md` - Complete import guide with troubleshooting

### Modified Files
- `includes/class-content-filter.php` - Updated for `<!--flosc_read_more-->`
- `flosc.php` - Loads shortcode handler, version bump

---

## 🔧 Technical Changes

### Class: `FLOSC_Shortcode_Handler`

**Location:** `includes/class-shortcode-handler.php`

**Methods:**
```php
public function visitor_only_shortcode($atts, $content)
public function member_only_shortcode($atts, $content)
```

**Integration:**
- Auto-loaded on `init` hook
- Uses `FLOSC_User_Access_Manager` for access checks
- Supports nested shortcodes via `do_shortcode()`
- Fallback message support via `fallback` attribute

### Class: `FLOSC_Content_Filter`

**Updated Method:** `filter_post_content()`

**Changes:**
```php
// OLD:
$parts = preg_split('/<!--more(.*?)?-->/', $content, -1);

// NEW:
$parts = preg_split('/<!--flosc_read_more(.*?)?-->|<!--more(.*?)?-->/', $content, -1);
```

**Backwards Compatibility:**
- Checks for `<!--flosc_read_more-->` FIRST (preferred)
- Falls back to `<!--more-->` (legacy support)
- No breaking changes for existing content

---

## 📋 Sample Lesson Structure

### Lesson Post Format

```html
<h1>🎯 Engaging Title With Emoji</h1>

<p>Teaser paragraph that builds curiosity...</p>

<strong>Why this matters:</strong>
<ul>
  <li>Hook point 1</li>
  <li>Hook point 2</li>
</ul>

<p>More teaser content...</p>

<!--flosc_read_more-->

<h2>🎓 MEMBER-ONLY: Complete Pronunciation Guide</h2>

<h3>IPA Transcription</h3>
<p><strong>WORD = /aɪpiːeɪ/</strong></p>

<h3>Sound-by-Sound Breakdown</h3>
<p>/aɪ/ - Diphthong: jaw opens wide...</p>

<!-- Full member content continues -->
```

### Required Post Meta

```php
_flosc_lesson_number: 1      // For RAG search
_flosc_access_level: member  // Access control
_flosc_seeded: 1            // Sample data flag
```

### Category
- **Name:** FLOSC Default Data
- **Slug:** `flosc-default-data`
- **Purpose:** Easy filtering/removal

---

## 🎓 Import Process

### Step-by-Step

1. **Install WordPress Importer**
   - Admin → Tools → Import → WordPress

2. **Upload XML File**
   - Choose `flosc-sample-lessons.xml`
   - Click "Upload file and import"

3. **Map Authors**
   - Assign to existing admin user (recommended)
   - OR create new "FLOSC Admin" user

4. **Verify Import**
   - Posts → All Posts
   - Should see 10 new posts
   - All in "FLOSC Default Data" category

5. **Test Access Control**
   - As visitor: Only sees teasers
   - As member: Sees full IPA content

---

## ⚠️ Breaking Changes

**NONE!** This is a fully backwards-compatible release.

**Legacy Support:**
- ✅ Old `<!--more-->` tags still work
- ✅ Existing lessons unchanged
- ✅ No database migrations needed
- ✅ No content updates required

---

## 🆙 Upgrade Path

### From v9.1.9 / v9.1.9a

**Automatic:**
- Deactivate old version
- Upload v9.2.0 zip
- Activate
- Done!

**What happens:**
- Shortcode handler loads automatically
- Content filter updated (supports both tags)
- No data loss
- No config changes needed

**Optional:**
- Import `flosc-sample-lessons.xml` for testing

---

## 📚 Documentation Updates

### New Guides
- `SAMPLE_LESSONS_IMPORT.md` - Complete import tutorial
  - Step-by-step instructions
  - Troubleshooting section
  - Custom lesson creation guide
  - Bulk creation examples
  - Post meta requirements
  - Export/sharing instructions

### Updated Docs
- Main README updated with shortcode examples
- Content filter documentation updated
- Access control examples added

---

## 🧪 Testing Checklist

- [x] `[flosc_visitor_only]` shows only to visitors
- [x] `[flosc_member_only]` shows only to members
- [x] `[flosc_member_only fallback="text"]` shows fallback to non-members
- [x] `<!--flosc_read_more-->` splits content correctly
- [x] Legacy `<!--more-->` still works
- [x] Sample lessons import successfully
- [x] Post meta fields set correctly
- [x] Category created automatically
- [x] RAG system finds lessons by number
- [x] Access control enforced in AI responses
- [x] Nested shortcodes work
- [x] Backwards compatibility maintained

---

## 🐛 Known Issues

**None reported.**

---

## 🚀 What's Next (Future Versions)

### Planned for v9.3.0
- Additional shortcodes (`[flosc_guest_only]`, `[flosc_quiz_score]`)
- Visual shortcode builder in editor
- Sample lesson library (50+ lessons)
- One-click lesson import from cloud
- Lesson templates for common topics

### Planned for v10.0.0
- Custom post type for lessons (instead of posts)
- Lesson progression tracking
- Quiz analytics dashboard
- Advanced RAG with semantic search
- Multi-language support

---

## 📦 Release Assets

- `flosc_v9_2_0.zip` - Main plugin (218 KB)
- `flosc-sample-lessons.xml` - Sample data (129 KB)
- `SAMPLE_LESSONS_IMPORT.md` - Import guide
- `CHANGELOG_v9_2_0.md` - This file

---

## 👏 Credits

**Contributors:**
- FLOSC Development Team
- Pronunciation lesson content based on IPA standards
- Community feedback on sample data approach

**Special Thanks:**
- WordPress XML format for easy imports
- Users who requested better content protection
- Testers who validated backwards compatibility

---

## 📞 Support

**Issues?** Open a GitHub issue or contact support.

**Questions about shortcodes?** See README.md for examples.

**Import problems?** Check SAMPLE_LESSONS_IMPORT.md troubleshooting section.

---

**Version:** 9.2.0  
**Released:** January 21, 2026  
**License:** Same as plugin  
**Tested With:** WordPress 6.4+
