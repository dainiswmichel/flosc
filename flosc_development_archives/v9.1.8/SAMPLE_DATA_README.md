# FLOSC Sample Data: Lessons 1-10

## Overview

Magnificent, entertaining, and educational pronunciation lessons for numbers 1-10.

Each lesson includes:
- ✅ Hilarious, engaging titles
- ✅ Knowledge-seeking teasers (before `<!--more-->`)
- ✅ Complete IPA transcriptions
- ✅ Step-by-step pronunciation guides
- ✅ Historical linguistics & etymology
- ✅ Common mistakes and corrections
- ✅ Practice sentences
- ✅ Cultural notes
- ✅ Regional variations (American, British, Australian, etc.)
- ✅ Quick reference cards

## Installation

### Option 1: Admin Interface (Recommended)

1. Go to **FLOSC → 📚 Sample Data** in WordPress admin
2. Click **"📥 Install Sample Data (Lessons 1-10)"**
3. Done! 10 posts created in the "FLOSC Default Data" category

### Option 2: Automatic on Activation

Sample data is NOT installed automatically on plugin activation to avoid surprise content on production sites.

Use the admin interface above for controlled installation.

## Removal

1. Go to **FLOSC → 📚 Sample Data**
2. Click **"🗑️ Remove Sample Data"**
3. All seeded lessons deleted (tracked via `_flosc_seeded=1` meta)

## Lesson Structure

### Teaser (Before `<!--more-->`)
- Engaging questions
- Mystery-building
- Knowledge-seeking without spoilers
- **VISITOR-safe** (no IPA, no answers)

### Member Content (After `<!--more-->`)
- Full IPA transcriptions
- Sound-by-sound breakdowns
- Micro-drills
- Historical context
- Practice assignments
- **MEMBER-only** (requires quiz completion or payment)

## Testing RAG Access Control

Use these lessons to test the three-tier access system:

### As VISITOR (not logged in):
```
User: "What's the IPA for 'one'?"
AI: "Take our free quiz first! Just 2 minutes."
```
✅ AI should NOT leak IPA or member content

### As GUEST (logged in, no quiz):
```
User: "What's the IPA for 'one'?"
AI: "You scored X% on the quiz! Lesson 1 available - $30 for 30 minutes!"
```
✅ AI can show titles, offers, pricing
❌ AI should NOT show IPA or detailed content

### As MEMBER (quiz complete OR paid):
```
User: "What's the IPA for 'one'?"
AI: "The IPA for 'one' is /wʌn/. Let me break it down..."
```
✅ AI has full access to all content

## Lesson Titles

1. 🎯 The Lonely Number: Why "ONE" Has Identity Issues
2. 👯‍♀️ TWO: The Word That Conquered 100+ Languages
3. 🎭 THREE: The Trickster Sound That Drives Learners Mad
4. 🚪 FOUR: The Word With A Silent Letter Identity Crisis
5. ✋ FIVE: The Number That Can't Decide If It's Alive or Dead
6. 🎪 SIX: The Circus Act That Happens In Your Mouth
7. 🎰 SEVEN: The Lucky Number With An Unlucky Pronunciation
8. 🎢 EIGHT: The Rollercoaster Your Tongue Takes
9. 🌙 NINE: The Shapeshifter That Changed Everything
10. 🔟 TEN: The Simplest Number With The Weirdest History

## Post Meta

Each lesson has:
- `_flosc_lesson_number` - Number 1-10 (for RAG search)
- `_flosc_access_level` - "member" (for access control)
- `_flosc_seeded` - "1" (for easy removal)

## Category

All lessons in: **flosc-default-data**

Slug: `flosc-default-data`

## File Locations

- **Class:** `/includes/class-sample-data.php`
- **Admin Page:** `/admin/sample-data-manager.php`
- **README:** `/SAMPLE_DATA_README.md` (this file)

## Development Notes

### Adding More Lessons

Edit `/includes/class-sample-data.php`:

```php
11 => [
    'title' => 'Your Title',
    'content' => self::get_lesson_11(),
],
```

Then add:

```php
private static function get_lesson_11() {
    return <<<'CONTENT'
Teaser here...
<!--more-->
Member content here...
CONTENT;
}
```

### Batch Operations

```php
// Install all
FLOSC_Sample_Data::install_sample_lessons();

// Remove all
FLOSC_Sample_Data::remove_sample_lessons();
```

## Credits

Created for FLOSC v9.1.7+

Pronunciation content based on:
- International Phonetic Alphabet (IPA)
- Wiktionary entries
- Linguistic research
- Historical etymology
- Cultural references

No made-up content - all IPA transcriptions and facts are verifiable!

## License

Part of FLOSC WordPress plugin.
