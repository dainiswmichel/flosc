# What's New in FLOSC v03_01

## Major Release: Quiz Type Framework

FLOSC v03_01 introduces a complete **Quiz Type Framework** that transforms FLOSC from a single-purpose plugin into a flexible framework for ANY quiz-based learning and sales funnel.

---

## Breaking Changes

### Quiz Settings Migration

The old quiz settings have been replaced:

**Old (v02_09):**
- `flosc_quiz_mode` (counting/sentence/none)
- `flosc_quiz_expected` (single text field)
- `flosc_quiz_instructions` (single text field)

**New (v03_01):**
- `flosc_quiz_type` (dropdown with multiple quiz types)
- `flosc_quiz_content_{quiz_id}` (quiz-specific content)
- Dynamic settings per quiz type
- Customizable response templates

### Action Required

After upgrading to v03_01:
1. Go to **FLOSC → Settings → Quiz tab**
2. Select your quiz type from the dropdown
3. Configure quiz content and settings
4. Customize response templates (optional)

---

## New Features

### 1. Quiz Type System

FLOSC now supports **5 quiz types out of the box**:

#### ✍️ Simple Scoring
- **Use Case:** Testing, counting, simple answer matching
- **Example:** User enters "1,2,3,4,5" for a 1-10 quiz
- **Requires:** Text input only (no audio/STT)
- **Perfect for:** Quick validation, number tests, comma-separated lists

#### ✓✗ True/False
- **Use Case:** Knowledge checks, fact verification
- **Example:** "The sky is blue. | True"
- **Requires:** Text input only
- **Perfect for:** Quick assessments, comprehension checks

#### ☑️ Multiple Choice
- **Use Case:** Classic quizzes with 2-4 options
- **Format:** "Question? | A) Option 1 | B) Option 2 | Correct: A"
- **Requires:** Text input only
- **Perfect for:** Traditional testing, certification prep

#### 🔗 Word Matching
- **Use Case:** Vocabulary, classification, pairing
- **Format:** "cat:mammal\ndog:mammal\nfish:aquatic"
- **Requires:** Text input only
- **Perfect for:** Language learning, category matching

#### 🎤 Pronunciation
- **Use Case:** Language learning, accent coaching, speech therapy
- **Requires:** Audio recording + STT provider
- **Perfect for:** LeSAEp-style pronunciation analysis

### 2. Zero-Configuration Quiz Types

4 out of 5 quiz types work **immediately with default settings**:
- Simple Scoring
- True/False
- Multiple Choice
- Word Matching

Only Pronunciation requires STT configuration (AssemblyAI/Deepgram/OpenAI Whisper).

### 3. Customizable Response Templates

Every quiz type includes **4 score-based response templates**:
- **0-30%:** Needs significant practice
- **31-60%:** Good effort, keep improving
- **61-85%:** Nice work, almost there
- **86-100%:** Excellent performance

Customize these in the admin UI with placeholders:
- `{score}` - User's percentage score
- `{total_correct}` - Number of correct answers
- `{total_possible}` - Total possible points
- `{lesson_recommendations}` - Auto-generated lesson suggestions

### 4. Quiz-Specific Settings

Each quiz type can define its own settings:

**Simple Scoring:**
- Answer separator (comma, semicolon, pipe)
- Case sensitive matching
- Partial credit (future feature)

**Pronunciation:**
- Target language (en, es, fr, de, it, pt)
- Target accent (US, UK, Australian, Canadian)

**True/False:**
- Answer format (T/F, True/False, Yes/No)

**Multiple Choice:**
- Show options in results

**Word Matching:**
- Case sensitive matching
- Show valid categories to users

### 5. New REST API Endpoints

#### `POST /wp-json/flosc/v1/process-quiz`
Process text-based quiz submissions.

**Request:**
```json
{
  "input": "1,2,3,4,5",
  "quiz_type": "simple_scoring"
}
```

**Response:**
```json
{
  "success": true,
  "analysis": {
    "score": 50,
    "correct": ["1", "2", "3", "4", "5"],
    "incorrect": [],
    "response_key": "31-60"
  },
  "lessons": [],
  "message": "Score: 50%\n\nGood effort! You got 5 out of 10 correct..."
}
```

#### Updated: `POST /wp-json/flosc/v1/process-audio`
Now routes through quiz type system for audio-based quizzes.

### 6. Admin UI Overhaul

The **Quiz tab** in FLOSC Settings now features:
- Quiz type dropdown with icons and descriptions
- Dynamic settings based on selected quiz type
- Response template editors with live placeholders
- Requirement indicators (audio/STT/AI needed)
- Default content examples

---

## Architecture

### Quiz Type Interface

All quiz types extend `FLOSC_Abstract_Quiz_Type`:

```php
abstract class FLOSC_Abstract_Quiz_Type {
    // Identification
    abstract public function get_id();           // 'simple_scoring'
    abstract public function get_name();         // 'Simple Scoring'
    abstract public function get_description();  // Brief description
    abstract public function get_icon();         // '✍️'

    // Requirements
    abstract public function needs_audio();      // true/false
    abstract public function needs_stt();        // true/false
    abstract public function needs_ai_analysis(); // true/false

    // Content
    abstract public function get_instructions(); // User instructions
    abstract public function get_default_content(); // Default quiz content

    // Analysis
    abstract public function validate_input($input);
    abstract public function analyze($input, $expected_content, $context = []);

    // Configuration
    abstract public function get_settings_fields(); // Admin settings

    // Output
    public function get_default_response_templates();
    public function format_results($analysis, $lessons, $templates);
    public function map_to_lessons($analysis);
}
```

### Quiz Type Factory

`FLOSC_Quiz_Type_Factory` automatically loads all quiz types from `/includes/quiz-types/`:

```php
// Get active quiz type
$quiz_type = FLOSC_Quiz_Type_Factory::get_active_quiz_type();

// Get specific quiz type
$quiz_type = FLOSC_Quiz_Type_Factory::get_quiz_type('simple_scoring');

// Get all quiz types
$all_types = FLOSC_Quiz_Type_Factory::get_all_quiz_types();

// Get zero-config quiz types
$zero_config = FLOSC_Quiz_Type_Factory::get_zero_config_quiz_types();
```

---

## Backward Compatibility

### v02_09 → v03_01

FLOSC v03_01 is built on top of v02_09 and preserves all SALE system functionality:
- ✅ Stripe integration
- ✅ Token-based payments
- ✅ Affiliate provider system
- ✅ Offer management
- ✅ Access control (visitor/free/paid)
- ✅ Usage tracking

### Migration Path

Old pronunciation quizzes will continue to work:
1. Default quiz type is `simple_scoring`
2. To use pronunciation, select "🎤 Pronunciation" from quiz type dropdown
3. Content from `flosc_quiz_expected` can be copied to new quiz content field

---

## For Developers

### Creating Custom Quiz Types

See `QUIZ_TYPES.md` for full documentation.

**Quick Example:**

```php
class FLOSC_My_Custom_Quiz extends FLOSC_Abstract_Quiz_Type {
    public function get_id() { return 'my_custom'; }
    public function get_name() { return 'My Custom Quiz'; }
    public function get_icon() { return '🎯'; }

    public function needs_audio() { return false; }
    public function needs_stt() { return false; }
    public function needs_ai_analysis() { return false; }

    public function analyze($input, $expected_content, $context = []) {
        // Your analysis logic here
        return [
            'score' => 85,
            'correct' => ['item1', 'item2'],
            'incorrect' => ['item3'],
            'response_key' => '61-85',
            'details' => [],
        ];
    }

    // ... implement other required methods
}
```

Save to `/includes/quiz-types/class-my-custom-quiz.php` and it will auto-load.

---

## Files Changed

### New Files
- `/includes/class-quiz-type-factory.php` - Quiz type loader and registry
- `/includes/quiz-types/abstract-quiz-type.php` - Base class
- `/includes/quiz-types/class-simple-scoring-quiz.php`
- `/includes/quiz-types/class-truefalse-quiz.php`
- `/includes/quiz-types/class-multiplechoice-quiz.php`
- `/includes/quiz-types/class-wordmatching-quiz.php`
- `/includes/quiz-types/class-pronunciation-quiz.php`

### Modified Files
- `/flosc.php` - Updated to v3.0.1, integrated quiz type factory
- `/templates/admin/settings.php` - New quiz type configuration UI

### Preserved Files
- All `/includes/sale/` files (untouched)
- All `/includes/ai-providers/` files (untouched)
- All `/includes/stt-providers/` files (untouched)
- All `/templates/flosc-app.php` and assets (untouched)

---

## Known Issues

### None

This is a stable release. All quiz types have been tested with default configurations.

---

## Roadmap

### Future Enhancements (v03_02+)
- Email notifications on quiz completion
- User profile quiz history
- Quiz result export (CSV/PDF)
- More quiz types:
  - Solfeggio (pitch detection)
  - Meditation (chat-only)
  - Drawing/Diagram matching
  - Code evaluation
- Multi-language support for quiz UI
- A/B testing for response templates

---

## Credits

**FLOSC Framework v03_01**
Developed by: Dainis Michel
Built with: Claude Sonnet 4.5
License: GPL v2 or later

---

## Support

For issues, feature requests, or questions:
- GitHub: https://github.com/dainismichel/flosc (coming soon)
- Documentation: See `README.md` and `QUIZ_TYPES.md`
- Email: Support coming with public release

---

**Thank you for using FLOSC!** 🚀

This release marks FLOSC's evolution from a single-purpose plugin to a true framework for quiz-based learning and conversational sales funnels.
