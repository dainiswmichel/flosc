# What's New in FLOSC v04_05

**Release Date:** January 9, 2026
**Version:** 4.0.5

## Major Feature: AI Orientation Files Manager

This release introduces a complete file management system for AI knowledge bases, lesson catalogs, and custom instructions - fully topic-agnostic and user-controlled.

---

## Key Features

### 1. AI Orientation Files Directory

A dedicated `/ai_orientation_files/` directory where you can upload or create markdown files containing:
- Lesson catalogs with URLs
- Knowledge base content
- Teaching methodologies
- Content tables of contents
- Access control rules
- Custom AI instructions
- **Any topic-specific content you need**

### 2. Complete File Management Interface

Navigate to **FLOSC > AI Orientation** to:

**Upload Files:**
- Upload .md files directly from your computer
- Drag and drop support
- Only .md files accepted for consistency

**Create Files:**
- Create new files directly in WordPress admin
- Built-in textarea editor with monospace font
- Auto-adds .md extension if missing

**Edit Files:**
- Edit any existing file inline
- Full-screen textarea editor
- Save changes instantly

**Delete Files:**
- Remove files with confirmation dialog
- Permanent deletion

**View Files:**
- List all orientation files
- See file size and last modified date
- Quick access to edit/delete actions

### 3. Automatic AI Loading

**All files in `/ai_orientation_files/` are automatically loaded into the AI's context.**

The AI receives:
```
Base System Prompt
+
Phase-Specific Prompt (from /prompts/)
+
ALL Orientation Files (from /ai_orientation_files/)
+
Current Context Variables
=
Complete AI System Prompt
```

### 4. Topic-Agnostic Design

**FLOSC doesn't care what you're teaching.** You can use this for:
- Pronunciation lessons (English sounds)
- Music education (solfeggio scales)
- Language learning (vocabulary lists)
- Math tutoring (formula catalogs)
- Coding bootcamps (algorithm references)
- **Anything you want**

The system simply:
1. Loads your markdown files
2. Gives them to the AI
3. Lets the AI reference your content

---

## How It Works

### File Organization

You have complete control over file organization. Common approaches:

**By Content Type:**
```
/ai_orientation_files/
├── lesson-catalog.md
├── teaching-guide.md
├── troubleshooting.md
└── access-rules.md
```

**By Topic:**
```
/ai_orientation_files/
├── consonants-catalog.md
├── vowels-catalog.md
├── intonation-catalog.md
└── common-mistakes.md
```

**By Phase:**
```
/ai_orientation_files/
├── free-content-toc.md
├── paid-content-toc.md
└── capabilities-guide.md
```

**Mix and Match - Organize However You Want!**

### Example Orientation File

**`/ai_orientation_files/lesson-catalog.md`**

```markdown
# Pronunciation Lesson Catalog

## Consonants

### TH Sounds
1. **Voiced TH** - /lessons/voiced-th
   - Duration: 15 minutes
   - Difficulty: Medium
   - For: Spanish, French, Italian speakers

2. **Unvoiced TH** - /lessons/unvoiced-th
   - Duration: 20 minutes
   - Difficulty: Hard
   - Prerequisites: Voiced TH

### R Sound
3. **American R** - /lessons/american-r
   - Duration: 25 minutes
   - Difficulty: Very Hard
   - For: Chinese, Japanese, Korean speakers

[Continue with all your lessons...]
```

When a user asks "How do I improve my R sound?", the AI:
1. Sees this catalog
2. Finds the American R lesson
3. Responds: "I recommend the American R lesson (/lessons/american-r). It's 25 minutes and specifically designed for your needs. Ready to start?"

---

## Admin Interface Features

### Upload Section
- Clean, simple file upload form
- Accepts only .md files
- Instant feedback on success/error

### Create Section
- Filename input with auto .md extension
- Large textarea for markdown content
- Monospace font for easy editing

### File List Table
- Shows all existing files
- Displays file size (automatically formatted)
- Shows last modified date/time
- Edit and Delete buttons for each file

### Edit Mode
- Full-screen editing experience
- Pre-filled with existing content
- Save or Cancel options

---

## Use Cases

### Use Case 1: Lesson Catalog
Upload a markdown file listing all your lessons with URLs, descriptions, and metadata. AI can now recommend specific lessons.

### Use Case 2: Teaching Methodology
Create a file explaining HOW to teach certain concepts. AI uses this to provide better coaching.

### Use Case 3: Access Control
Define what content is available at each FLOSC phase (free vs paid). AI respects these boundaries.

### Use Case 4: Troubleshooting Guide
List common student problems and solutions. AI references this when students struggle.

### Use Case 5: FAQ Database
Upload frequently asked questions and answers. AI can handle common questions consistently.

---

## Benefits

✅ **Complete Control** - You decide what the AI knows
✅ **Topic-Agnostic** - Works for any subject matter
✅ **Easy Updates** - Edit files anytime without code changes
✅ **No Coding Required** - Upload or type markdown, that's it
✅ **Organized Knowledge** - Keep content separate from prompts
✅ **Scalable** - Add unlimited files as your content grows
✅ **Version Control Friendly** - .md files work great with git

---

## Technical Details

### File Loading

The AI factory's `load_orientation_files()` method:
1. Scans `/ai_orientation_files/` directory
2. Reads all `.md` files
3. Concatenates content with file headers
4. Injects into AI system prompt

### File Format

Files are plain markdown (.md):
- Use markdown headers (# ## ###) for structure
- Lists, tables, code blocks all supported
- No special formatting required
- AI receives raw markdown (understands it naturally)

### Security

- Only .md files allowed (prevents code execution)
- File operations require `manage_options` capability (admin only)
- Filenames are sanitized (prevents directory traversal)
- WordPress nonces protect against CSRF

---

## Upgrade Instructions

1. **Backup your site** before upgrading
2. Upload `flosc_v04_05.zip` via WordPress Plugins > Add New > Upload
3. Activate the plugin
4. Navigate to **FLOSC > AI Orientation**
5. Upload or create your first orientation file
6. Test by asking the AI about content in your file

---

## Breaking Changes

None. This is a feature addition with backward compatibility.

---

## Workflow Example

**Step 1:** Create your lesson catalog in a text editor
```markdown
# My Lesson Catalog
1. Lesson A - /lessons/lesson-a
2. Lesson B - /lessons/lesson-b
```

**Step 2:** Go to FLOSC > AI Orientation

**Step 3:** Either:
- Upload the .md file, OR
- Paste content into "Create New File" textarea

**Step 4:** Save

**Step 5:** AI now knows about your lessons and can recommend them!

---

## Future Enhancements (Planned)

- File templates for common use cases
- Syntax highlighting in editor
- Preview mode (see formatted markdown)
- Search within files
- Conditional file loading (only load certain files in certain phases)
- File tagging/categories
- Bulk upload

---

## Developer Notes

### Accessing Files Programmatically

```php
$ai_factory = FLOSC_Framework::get_instance()->ai();
$orientation_content = $ai_factory->load_orientation_files();
// Returns concatenated content of all .md files
```

### Custom File Loading

You can extend the AI factory to load files conditionally:

```php
private function load_orientation_files() {
    // Custom logic here
    // Load different files based on user role, phase, etc.
}
```

---

## Credits

- **Developed by:** Dainis Michel
- **Framework:** FLOSC (Freeline-Login-Offer-Sale-Content)
- **Design Philosophy:** User-controlled, topic-agnostic knowledge management

---

## Support

For issues, questions, or feature requests, please contact support or file an issue in the project repository.

---

## Version History

- **v4.0.5** (Jan 9, 2026) - AI Orientation Files Manager
- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface & Phase-Aware Messaging
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction & Prompt Card Flow
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch
