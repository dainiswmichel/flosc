# What's New in FLOSC v04_04

**Release Date:** January 9, 2026
**Version:** 4.0.4

## Major Feature: Phase-Aware AI System

This release introduces a sophisticated three-tier AI instruction system that makes your chatbot context-aware and tailored to each stage of the FLOSC funnel.

---

## Key Features

### 1. Three-Tier AI Prompt System

**Tier 1: Base System Prompt (Database)**
- Editable via WordPress admin (FLOSC > AI Config)
- Defines core personality and mission
- Applies to all phases
- Example: "You are a pronunciation coach. Be helpful, friendly, and action-oriented."

**Tier 2: Phase-Specific Instructions (Markdown Files)**
- Located in `/prompts/` directory
- One file per FLOSC phase
- Detailed instructions for each funnel stage
- Includes conversation style, key messages, objection handling, etc.

**Tier 3: Automatic Context Variables**
- Phase detection (freeline, login-prompt, login, offer, sale, content)
- User info (name, email, status)
- Quiz results (score, completed)
- Purchase status
- Product info

### 2. Phase-Specific Prompt Files

**`freeline-prompt.md`**
- Role: Friendly coach meeting visitor for first time
- Goal: Motivate quiz completion
- Style: Welcoming, non-pushy, enthusiastic
- Key message: "Taking the quiz is the fastest way to understand your challenges"

**`login-prompt-prompt.md`**
- Role: Motivational coach post-quiz
- Goal: Encourage account creation to see results
- Style: Celebratory, curiosity-building
- Key message: "Your results are waiting! Create free account to see everything"

**`login-prompt.md`**
- Role: Supportive coach who delivered quiz results
- Goal: Build trust before presenting offer
- Style: Encouraging, knowledgeable, personalized
- Key message: "You've experienced the free lesson - there's 50+ more like that"

**`offer-prompt.md`**
- Role: Skilled sales consultant
- Goal: Convert free user to paid customer
- Style: Confident, value-focused, objection-ready
- Includes: Common objections and responses (price, time, efficacy)
- Closing techniques: Trial close, assumptive close, direct close

**`sale-prompt.md`**
- Role: Enthusiastic onboarding coach
- Goal: Get them started immediately
- Style: Celebratory, action-oriented
- Key message: "Congratulations! Let's get you started with lesson 1 right now"

**`content-prompt.md`**
- Role: Supportive ongoing coach
- Goal: Retention and engagement
- Style: Patient, encouraging, progress-focused
- Key message: "Welcome back! Consistent practice builds mastery"

### 3. AI Configuration Admin Page

Navigate to **FLOSC > AI Config** to:

- **Select AI Provider:**
  - IVR (Scripted) - Free pattern-matching responses
  - OpenAI (GPT-4o-mini)
  - Anthropic (Claude 3.5 Sonnet)
  - xAI (Grok Beta)

- **Configure API Keys:**
  - OpenAI API key
  - Anthropic API key
  - xAI API key

- **Edit Base System Prompt:**
  - Define core personality
  - Set tone and style
  - Specify guiding principles

- **View Phase Prompt Info:**
  - See which phase prompts are available
  - Understand how prompts are merged
  - Learn about automatic context variables

### 4. Automatic Context Variables

The system automatically passes these variables to AI with each request:

| Variable | Description | Example |
|----------|-------------|---------|
| `phase` | Current FLOSC phase | freeline, login, offer, etc. |
| `user_name` | User's display name | John Doe |
| `user_status` | Account type | visitor, free, paid |
| `quiz_score` | Last quiz result | 75% |
| `free_lesson_delivered` | Has user received free lesson | Yes/No |
| `purchased` | Has user purchased | Yes/No |
| `product_name` | Your product name | FLOSC App |

### 5. Smart Phase Detection

The system automatically detects which FLOSC phase the user is in:

```
Visitor + No Quiz → freeline
Visitor + Quiz Done → login-prompt
Logged In + Pre-Offer → login
Logged In + Offer Shown → offer
Purchased + Pre-Onboarding → sale
Purchased + Onboarded → content
```

---

## Technical Implementation

### New Files

**Prompts Directory:**
- `/prompts/freeline-prompt.md`
- `/prompts/login-prompt-prompt.md`
- `/prompts/login-prompt.md`
- `/prompts/offer-prompt.md`
- `/prompts/sale-prompt.md`
- `/prompts/content-prompt.md`

**Admin Template:**
- `/templates/admin/ai-config.php` - AI Configuration admin page

### Updated Files

**Backend:**
- `flosc.php`:
  - Added `build_ai_context()` method
  - Added `determine_flosc_phase()` method
  - Updated `handle_ai_query()` to use phase-aware prompts
  - Added AI Config submenu page
  - Registered `flosc_ai_base_prompt` setting

- `includes/class-ai-provider-factory.php`:
  - Added `build_system_prompt()` method
  - Added `load_phase_prompt()` method
  - Added `build_context_string()` method
  - Added `get_default_base_prompt()` method

### How AI Prompts Are Merged

When a user sends a message, the system:

1. Detects current FLOSC phase (e.g., "freeline")
2. Loads base prompt from database
3. Loads phase-specific prompt from `/prompts/freeline-prompt.md`
4. Builds context string with user data
5. Merges all three tiers:

```
Base Prompt
+
Phase-Specific Prompt
+
Current Context (user_name: John, quiz_score: 75%, etc.)
=
Full System Prompt sent to AI
```

---

## Usage Examples

### Example 1: Visitor Chat (Freeline Phase)

**Context Passed to AI:**
```
- phase: freeline
- user_status: visitor
- purchased: No
- product_name: FLOSC App
```

**Prompt Used:**
- Base prompt + `freeline-prompt.md` + context

**AI Behavior:**
- Friendly greeting
- Emphasizes free quiz value
- Non-pushy encouragement
- Guides toward taking quiz

### Example 2: Post-Purchase Chat (Sale Phase)

**Context Passed to AI:**
```
- phase: sale
- user_name: Sarah Johnson
- user_status: paid
- quiz_score: 82%
- purchased: Yes
- product_name: Pronunciation Mastery
```

**Prompt Used:**
- Base prompt + `sale-prompt.md` + context

**AI Behavior:**
- Congratulates Sarah
- References her 82% quiz score
- Suggests first lesson based on her needs
- Removes any overwhelm
- Gets her started immediately

---

## Customization Guide

### Customize Base Prompt

1. Go to **FLOSC > AI Config**
2. Edit the "Base System Prompt" textarea
3. Click "Save AI Configuration"

### Customize Phase-Specific Prompts

1. Access your plugin files via FTP or file manager
2. Navigate to `/wp-content/plugins/flosc/prompts/`
3. Edit the desired `.md` file (e.g., `offer-prompt.md`)
4. Save the file
5. Changes take effect immediately (no cache)

### Add New Context Variables

Edit `flosc.php`, method `build_ai_context()`:

```php
// Add custom variable
$context['user_accent'] = get_user_meta($user->ID, 'accent_type', true);
```

This variable will automatically be passed to AI:
```
- user_accent: Spanish
```

---

## Upgrade Instructions

1. **Backup your site** before upgrading
2. Upload `flosc_v04_04.zip` via WordPress Plugins > Add New > Upload
3. Activate the plugin
4. Navigate to **FLOSC > AI Config**
5. Configure your AI provider and API key
6. Review and customize the base system prompt
7. (Optional) Customize phase-specific prompts via FTP

---

## Breaking Changes

None. This is a feature addition with backward compatibility.

---

## Known Limitations

- Phase-specific prompts must be edited via FTP (no GUI editor yet)
- No A/B testing for different prompts
- No analytics on prompt effectiveness
- Context variables are read-only (no dynamic updates during conversation)

---

## Future Enhancements (Planned)

- In-admin editor for phase-specific prompts
- Prompt versioning and rollback
- A/B testing framework
- Analytics dashboard for prompt performance
- Dynamic context updates (e.g., update quiz_score mid-conversation)
- Conversation branching based on sentiment

---

## Developer Notes

### Extending Phase Detection

Add new phases by editing `determine_flosc_phase()` in `flosc.php`:

```php
private function determine_flosc_phase() {
    // Add your custom logic
    if ($this->custom_condition()) {
        return 'custom-phase';
    }
    // ...
}
```

Then create `/prompts/custom-phase-prompt.md`.

### Testing Prompts

Test how prompts merge together:

```php
$ai_factory = FLOSC_Framework::get_instance()->ai();
$context = ['phase' => 'offer', 'user_name' => 'Test User', 'quiz_score' => '75%'];
$prompt = $ai_factory->build_system_prompt('offer', $context);
error_log($prompt); // View full merged prompt
```

---

## Credits

- **Developed by:** Dainis Michel
- **Framework:** FLOSC (Freeline-Login-Offer-Sale-Content)
- **Prompt Engineering:** Phase-aware conversational AI patterns

---

## Support

For issues, questions, or feature requests, please contact support or file an issue in the project repository.

---

## Version History

- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface & Phase-Aware Messaging
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction & Prompt Card Flow
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch
