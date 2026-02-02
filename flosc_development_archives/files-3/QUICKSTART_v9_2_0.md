# FLOSC v9.2.0 - QUICKSTART GUIDE

## 🎉 What's New in v9.2.0

### 1. **Access Control Shortcodes**

**`[flosc_visitor_only]`** - Shows content ONLY to non-logged-in visitors
**`[flosc_member_only]`** - Shows content ONLY to members (with optional fallback)

### 2. **Custom Content Split Tag**

**`<!--flosc_read_more-->`** - FLOSC-specific tag (avoids WordPress conflicts)
- Still supports legacy `<!--more-->` tag!

### 3. **Sample Data as Importable XML**

**10 magnificent lessons** now in separate XML file for manual import
- Teaches you the import process
- No auto-install surprise content
- Full control over when/if to add samples

---

## 📦 Files in This Release

| File | Size | Purpose |
|------|------|---------|
| `flosc_v9_2_0.zip` | 266 KB | Main plugin |
| `flosc-sample-lessons.xml` | 128 KB | 10 sample lessons (import separately) |
| `SAMPLE_LESSONS_IMPORT.md` | 8 KB | Complete import guide |
| `CHANGELOG_v9_2_0.md` | 8 KB | Full changelog |

---

## 🚀 Installation

### Step 1: Install Plugin

1. Go to **WordPress → Plugins → Add New → Upload**
2. Upload `flosc_v9_2_0.zip`
3. Click **"Activate"**
4. Done!

### Step 2: Import Sample Lessons (Optional)

1. Go to **Tools → Import → WordPress**
2. Install WordPress Importer (if not installed)
3. Upload `flosc-sample-lessons.xml`
4. Map to your admin user
5. Click **"Submit"**
6. Verify 10 posts created in "FLOSC Default Data" category

**Full instructions:** See `SAMPLE_LESSONS_IMPORT.md`

---

## ✨ Using the New Features

### Shortcodes

**Show content to visitors only:**
```
[flosc_visitor_only]
  Take our free quiz! → [Start Now]
[/flosc_visitor_only]
```

**Show content to members only:**
```
[flosc_member_only]
  <h2>IPA Transcription: /aɪpiːeɪ/</h2>
  <p>Full breakdown here...</p>
[/flosc_member_only]
```

**With fallback message:**
```
[flosc_member_only fallback="Upgrade to unlock"]
  Premium content here
[/flosc_member_only]
```

### Content Split Tag

**In your post/lesson editor:**
```html
Public content visible to everyone (teaser)

<!--flosc_read_more-->

Member-only content here (IPA, detailed guides, etc.)
```

**Access behavior:**
- **Visitor:** Sees only teaser
- **Guest:** Sees teaser + "🔒 Complete quiz to unlock"
- **Member:** Sees EVERYTHING

---

## 🧪 Testing Checklist

After installation, test:

- [ ] Shortcodes work (create test post with both shortcodes)
- [ ] `<!--flosc_read_more-->` splits content correctly
- [ ] Sample lessons import successfully (if you imported them)
- [ ] AI access control works (test as visitor/guest/member)
- [ ] IVR editor accessible (FLOSC → IVR Messages → Edit Messages)
- [ ] All 48 IVR messages present (if using IVR system)

---

## 📚 Documentation

**For shortcodes:**
- See main plugin README

**For sample lessons:**
- `SAMPLE_LESSONS_IMPORT.md` - Complete import guide

**For all changes:**
- `CHANGELOG_v9_2_0.md` - Full changelog

---

## ⚡ Quick Feature Reference

### Shortcodes
```
[flosc_visitor_only]...[/flosc_visitor_only]  → Visitors only
[flosc_member_only]...[/flosc_member_only]    → Members only
```

### Content Split
```
<!--flosc_read_more-->  → FLOSC-specific (recommended)
<!--more-->             → Legacy support (still works)
```

### Sample Data
```
flosc-sample-lessons.xml  → 10 lessons, import via Tools → Import
```

---

## 🆙 Upgrading from v9.1.x

**Safe to upgrade!**
- ✅ 100% backwards compatible
- ✅ No breaking changes
- ✅ Legacy `<!--more-->` tags still work
- ✅ No database migrations
- ✅ No config changes needed

**Just:**
1. Deactivate old version
2. Upload v9.2.0
3. Activate
4. Done!

---

## 🐛 Troubleshooting

### Shortcodes not working
- **Solution:** Clear cache, verify plugin activated

### Split tag not working
- **Solution:** Check post uses `<!--flosc_read_more-->` or `<!--more-->`

### Sample lessons won't import
- **Solution:** See `SAMPLE_LESSONS_IMPORT.md` troubleshooting section

### IVR editor blank page
- **Solution:** You need v9.1.9a or later (v9.2.0 has the fix)

---

## 📞 Support

**Questions?** Check full documentation in plugin files.

**Issues?** All features tested and working in v9.2.0.

**Custom content?** Use sample lessons as templates!

---

## 🎓 What's Included in Sample Lessons

**10 pronunciation lessons for numbers 1-10:**

1. 🎯 ONE - Identity Issues  
2. 👯‍♀️ TWO - Conquered 100+ Languages  
3. 🎭 THREE - Trickster Sound  
4. 🚪 FOUR - Silent Letter Crisis  
5. ✋ FIVE - Alive or Dead  
6. 🎪 SIX - Circus Act  
7. 🎰 SEVEN - Unlucky Pronunciation  
8. 🎢 EIGHT - Rollercoaster  
9. 🌙 NINE - Shapeshifter  
10. 🔟 TEN - Weirdest History

**Each lesson ~400-500 lines with:**
- Hilarious titles
- Engaging teasers
- Complete IPA transcriptions
- Step-by-step guides
- Historical linguistics
- Practice drills
- Regional variations

---

## 🎯 Next Steps

1. ✅ Install plugin
2. ✅ (Optional) Import sample lessons
3. ✅ Test shortcodes in a post
4. ✅ Configure your product settings
5. ✅ Add your own custom lessons
6. ✅ Test access control with quiz
7. ✅ Launch your pronunciation coaching funnel!

---

**Version:** 9.2.0  
**Released:** January 21, 2026  
**Compatibility:** WordPress 6.4+  
**License:** Same as plugin

**Enjoy! 🚀**
