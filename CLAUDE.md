# CLAUDE.md - FLOSC Development Guide for AI Assistants

## Project Overview

**FLOSC** (Freeline-Login-Offer-Sale-Content) is a WordPress plugin framework for conversational sales funnels with quiz-based learning, AI-powered chat, and multi-provider payment systems.

- **Version:** 1.3.8 (Active MVP)
- **Author:** Dainis Michel
- **License:** GPL v2+
- **Requirements:** WordPress 5.8+, PHP 7.4+, SSL certificate
- **Website:** https://flosc.ai

## Repository Structure

```
flosc/
├── CLAUDE.md                              # This file
├── .gitignore                             # WordPress-focused exclusions
├── mvp_sprint/                            # ACTIVE DEVELOPMENT AREA
│   ├── flosc_1_3_8/                       # Current plugin version (main codebase)
│   │   ├── flosc.php                      # Main plugin file (~4700 lines, singleton)
│   │   ├── readme.md                      # Plugin documentation
│   │   ├── includes/                      # Core PHP classes (19 classes)
│   │   │   ├── class-ai-provider-factory.php
│   │   │   ├── class-stt-provider-factory.php
│   │   │   ├── class-quiz-type-factory.php
│   │   │   ├── class-session-manager.php
│   │   │   ├── class-ivr-parser.php
│   │   │   ├── class-condition-evaluator.php
│   │   │   ├── class-pronunciation-analyzer.php
│   │   │   ├── class-lesson-manager.php
│   │   │   ├── class-content-filter.php
│   │   │   ├── class-content-protection.php
│   │   │   ├── class-user-access-manager.php
│   │   │   ├── class-member-access.php
│   │   │   ├── class-access-validator.php
│   │   │   ├── class-free-lesson-manager.php
│   │   │   ├── class-rag-manager.php
│   │   │   ├── class-bridge-data-manager.php
│   │   │   ├── class-quiz-manager.php
│   │   │   ├── class-flow-manager.php
│   │   │   ├── quiz-types/               # 5 quiz type implementations
│   │   │   └── sale/                     # Payment & offer subsystem
│   │   │       ├── class-sale-manager.php
│   │   │       ├── class-offer-manager.php
│   │   │       ├── class-access-manager.php
│   │   │       ├── class-usage-tracker.php
│   │   │       ├── class-payment-provider.php
│   │   │       └── providers/            # Stripe, Token, Affiliate, ClickBank
│   │   ├── admin/                        # WordPress admin pages (19 PHP files)
│   │   ├── assets/
│   │   │   ├── css/                      # 9 stylesheets (layout, themes, admin)
│   │   │   └── js/                       # flosc-app.js (frontend), ivr-admin.js
│   │   ├── ai_configuration_files/       # IVR markdown configs + lesson prompts
│   │   └── sample-data/                  # XML import templates
│   ├── lesaep_dev_files/                 # LeSAEp language project files
│   └── *.md                              # Sprint development notes
├── flosc_development_archives/            # 100+ versioned archives (historical)
├── flosc_development_worknotes/           # Development guides and workflow docs
├── flosc_styling_development/             # CSS architecture & style guide
├── login_development/                     # Login system (korboc survey plugin)
└── quiz_development/                      # Quiz system research
```

## Technology Stack

| Tech | Details |
|------|---------|
| **Backend** | PHP 7.4+ (WordPress plugin architecture) |
| **Frontend** | Vanilla JavaScript (no framework), plain CSS with CSS variables |
| **Database** | WordPress options table (`wp_options`), user meta |
| **Build** | None - no compilation step, files served directly |
| **Package Manager** | None - no npm/composer dependencies |
| **Tests** | None - no automated test framework |
| **Linting** | None - no eslint/phpcs configured |
| **CI/CD** | None - manual deployment |

## Architecture

### Core Pattern: Singleton + Factory

```
FLOSC_Framework (Singleton via ::instance())
├── FLOSC_AI_Provider_Factory     (OpenAI, Anthropic, xAI, IVR scripted)
├── FLOSC_STT_Provider_Factory    (AssemblyAI, Whisper, Deepgram)
├── FLOSC_Quiz_Type_Factory       (5 quiz types: text, audio, MCQ, T/F, word-matching)
├── FLOSC_Sale_Manager (Singleton)
│   ├── FLOSC_Offer_Manager
│   ├── FLOSC_Usage_Tracker
│   ├── FLOSC_Access_Manager
│   └── Payment Providers         (Stripe, Token, Affiliate, ClickBank)
├── FLOSC_RAG_Manager (Singleton)
├── FLOSC_User_Access_Manager (Singleton)
├── FLOSC_Content_Filter (Singleton)
├── FLOSC_Lesson_Manager (Singleton)
├── FLOSC_Session_Manager
└── FLOSC_Flow_Manager            (Multi-flow system, v1.2.2+)
```

### 5-Phase User Journey (FLOSC Funnel)

```
Freeline → Login → Offer → Sale → Content
(Visitor)  (Guest)  (OTO)   (Pay)  (Member)
```

Each phase has its own IVR messages, conditions, and UI behavior.

### Multi-Flow System

Each "flow" is an isolated conversion funnel with its own branding, IVR file, product config, and slug-based routing. Flows are managed via `FLOSC_Flow_Manager` and configured in WordPress admin.

### REST API Namespace

All endpoints are under `flosc/v1`:
- `/chat` - IVR/AI chat processing
- `/chat-rag` - RAG-powered chat
- `/quiz` - Quiz submission (GET/POST)
- `/process-audio` - Audio quiz processing
- `/process-quiz` - External quiz processing
- `/sessions` - Chat session CRUD
- `/offers` - Available offers
- `/purchase` - Purchase processing
- `/create-payment-intent` - Stripe payment intents
- `/webhooks/(?P<provider>[a-z]+)` - Payment webhooks
- `/access` - Access checking
- `/tokens` - Token balance
- `/lessons` - Lesson CRUD
- `/lessons/free` - Free lesson access
- `/ivr-messages` - IVR message retrieval
- `/bridge-data` - External quiz integration data
- `/store-score` - Score storage
- `/debug/funnel-state` - Debug endpoint (FLOSC_DEBUG only)

### IVR System (Markdown-Based)

IVR messages are configured in markdown files under `ai_configuration_files/`. The `FLOSC_IVR_Parser` reads these files and the `FLOSC_Condition_Evaluator` interprets boolean conditions (`&&`, `||`, `!`, `()`) with variable substitution (`{name}`, `{score}`, `{product_name}`, etc.).

## Development Conventions

### PHP Naming

- **Classes:** `FLOSC_Snake_Case` (e.g., `FLOSC_AI_Provider_Factory`)
- **Files:** `class-kebab-case.php` (e.g., `class-ai-provider-factory.php`)
- **Functions:** `snake_case` prefixed with `flosc_` for globals
- **Constants:** `FLOSC_UPPER_SNAKE` (e.g., `FLOSC_VERSION`, `FLOSC_DEBUG`)
- **Options:** `flosc_snake_case` (stored in wp_options)
- **User meta:** `_flosc_snake_case` (prefixed with underscore)
- **Hooks:** `flosc_action_name` / `flosc_filter_name`

### CSS Naming

- **Variables:** `--flosc-{area}-{property}` (e.g., `--flosc-sidebar-bg`, `--flosc-user-message-text`)
- **Classes:** `.flosc-kebab-case` for layout, `.flosc_{snake_case}` for some JS-controlled elements
- **Theme files:** `chat-style-{theme}.css` (claude, chatgpt, grok, dark, light)
- **Layout/theme separation:** `flosc-layout.css` (structure) + `flosc-theme.css` (colors/variables)
- Full style guide: `flosc_styling_development/FLOSC_STYLE_GUIDE.md`

### JavaScript

- Vanilla JS only (no frameworks, no build step)
- Main frontend: `assets/js/flosc-app.js` (IVR engine, chat UI, quiz modal, offers)
- Admin: `assets/js/ivr-admin.js` (IVR editor)
- Element toggling via `el.style.display` (not CSS classes)
- WordPress data passed via `wp_localize_script()` → `floscData` global

### Version Control

- Commit messages follow: `vX.Y.Z: Brief description of changes`
- Version bumped in plugin header and `FLOSC_VERSION` constant
- Never repackage/overwrite older version numbers
- SSH key signing enabled for commits

### Michel Date Stamp Innovation

This project uses a custom date format for timestamps in development notes and the plugin itself:
- **Standard:** `YYYY-MMm-DDd` (e.g., `2026-02m-06d`)
- **With time:** `YYYY-MMm-DDd-THHh:MMm:SSs` (e.g., `2026-02m-06d-T14h:30m:00s`)
- **PHP generator:** `flosc_michel_timestamp_global()` in `flosc.php`
- The `m` suffix marks month, `d` marks day, eliminating MM/DD ambiguity
- Full spec: `flosc_development_worknotes/michel-date-stamp-innovation.md`

### Development Worknotes Convention

- Entries are **additive only** - never delete existing entries
- **Reverse chronological order** - newest at top
- Use the Past/Present/Future framework for session reports
- Reference: `flosc_development_worknotes/flosc_active_devguide.md`

## Key Development Rules

### Plan First, Code Second
Do not start coding changes without understanding the full scope. Read existing code, understand the patterns, then implement. This is explicitly called out in project documentation after multiple incidents of hasty changes causing regressions.

### KISS Principle
Avoid over-engineering. The project documentation emphasizes keeping things simple:
- Align PHP IDs with what JS expects (don't rewrite both sides)
- Use simple inline styles for JS-toggled elements when JS uses `el.style.display`
- Don't add features nobody asked for when fixing bugs

### Debug Logging
All `error_log()` calls must be guarded by `FLOSC_DEBUG`:
```php
if (FLOSC_DEBUG) {
    error_log("FLOSC: descriptive message here");
}
```

### Security Considerations
- Every PHP file starts with `if (!defined('ABSPATH')) exit;`
- REST endpoints should have proper `permission_callback` (not `__return_true` for sensitive ops)
- Rate limiting via `check_rate_limit()` on public API endpoints
- Signed cookies for pre-login quiz scores (`set_signed_cookie()`)
- Nonce verification on admin forms
- Input sanitization with `sanitize_text_field()`, `intval()`, etc.
- Output escaping with `esc_html()`, `esc_attr()`, `wp_kses()`

### WordPress Standards
- Use WordPress hooks (`add_action`, `add_filter`) for extensibility
- Store settings in `wp_options` via Settings API
- Use `wp_enqueue_scripts` / `wp_enqueue_style` for assets
- Use `$wpdb->prepare()` for direct database queries
- Use transients for caching (`get_transient`, `set_transient`)

## Working with This Codebase

### Where to Find Things

| What | Where |
|------|-------|
| Main plugin logic | `mvp_sprint/flosc_1_3_8/flosc.php` |
| REST API routes | `flosc.php` → `register_rest_routes()` (line ~2025) |
| REST API handlers | `flosc.php` → `handle_*()` methods |
| AI providers | `includes/class-ai-provider-factory.php` |
| IVR parsing | `includes/class-ivr-parser.php` + `class-condition-evaluator.php` |
| Payment system | `includes/sale/` directory |
| Quiz types | `includes/quiz-types/` directory |
| Admin UI pages | `admin/` directory |
| Frontend JS | `assets/js/flosc-app.js` |
| IVR configs | `ai_configuration_files/*.md` |
| CSS themes | `assets/css/chat-style-*.css` |
| Flow management | `includes/class-flow-manager.php` |
| Access control | `includes/class-user-access-manager.php`, `class-access-validator.php` |
| RAG system | `includes/class-rag-manager.php`, `class-content-filter.php` |

### Settings Cascade

Settings resolve in order: **flow-specific** → **global** → **default**

```php
flosc_get_setting('setting_name');  // Uses cascade
```

### Adding a New REST Endpoint

1. Register in `register_rest_routes()` method of `FLOSC_Framework`
2. Add handler method to `FLOSC_Framework` class
3. Use proper `permission_callback` (not `__return_true` for anything sensitive)
4. Guard with rate limiting for public endpoints
5. Sanitize inputs, validate, return `WP_REST_Response` or `WP_Error`

### Adding a New Payment Provider

1. Create `includes/sale/providers/class-{name}-provider.php`
2. Extend `FLOSC_Payment_Provider` base class
3. Register in `FLOSC_Sale_Manager::register_providers()`
4. Add admin UI in `admin/payments.php`

### Adding a New Quiz Type

1. Create `includes/quiz-types/class-flosc-{name}-quiz.php`
2. Follow the interface pattern from existing quiz types
3. Register via `FLOSC_Quiz_Type_Factory`
4. Add admin config in `admin/quiz.php`

## External Service Integrations

All configured via WordPress admin, credentials stored in `wp_options`:

| Service | Purpose | Provider Class |
|---------|---------|----------------|
| OpenAI | GPT chat | `FLOSC_AI_Provider_Factory` |
| Anthropic | Claude chat | `FLOSC_AI_Provider_Factory` |
| xAI | Grok chat | `FLOSC_AI_Provider_Factory` |
| AssemblyAI | Speech-to-text | `FLOSC_STT_Provider_Factory` |
| Stripe | Payments | `FLOSC_Stripe_Provider` |
| ClickBank | Payments | `FLOSC_ClickBank_Provider` |

## Known Technical Debt

From the v9.4.1 code review and development notes:

1. **Main plugin file is too large** (~4700 lines) - REST, email, and quiz logic should be extracted into separate classes
2. **Some REST endpoints use `__return_true`** for permission callbacks - should have proper auth checks
3. **No automated tests** - condition evaluator and core logic need unit tests
4. **No linting/formatting** - no phpcs or eslint configuration
5. **JavaScript is monolithic** - `flosc-app.js` handles UI, quiz, IVR, and payments in one file
6. **Version mismatches** - `readme.md` says v1.2.6 while plugin header says v1.3.8
7. **No .pot file** for translations despite i18n-ready code

## Archives

The `flosc_development_archives/` directory contains 100+ versioned snapshots (v1.0.0 through v9.7.5). These are historical reference only - do not modify. All active development happens in `mvp_sprint/flosc_1_3_8/`.
