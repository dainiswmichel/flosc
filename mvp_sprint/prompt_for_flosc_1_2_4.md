# FLOSC v1.2.4 Development Prompt

## Context

FLOSC is a WordPress plugin that creates AI-powered conversational funnels. Version 1.2.3 established multi-flow support where each "flow" is an independent chatbot with its own:
- URL slug (e.g., `/flosc/`, `/lesaep/`, `/solfeggio/`)
- Custom domain mapping
- IVR file (conversation messages)
- Branding (name, emoji, colors)

## Current State (v1.2.3)

**Working:**
- Flow detection via `get_current_flow()` - checks custom_domain → slug → query_var
- Per-flow IVR file loading (e.g., `flosc_default_ivr.md`, `lesaep_ivr.md`)
- Basic flow admin: Identity tab, IVR tab, Content tab, Team tab
- REST API and frontend properly load flow-specific IVR content

**Flow data structure** (stored in `wp_options` as `flosc_flows`):
```php
[
    'default' => [
        'id' => 'default',
        'slug' => 'flosc',
        'custom_domain' => '',
        'status' => 'active',
        'product' => [
            'name' => 'FLOSC',
            'tagline' => '',
            'emoji' => '🎯',
            'logo_url' => '',
            'primary_color' => '#4f46e5',
            'share_text' => '',
        ],
        'ivr_file' => 'flosc_default_ivr.md',
        'wp_category_id' => 0,
        'quiz_type' => 'flosc_sample_text_based_quiz',
        'created_at' => '...',
        'updated_at' => '...',
    ],
    'lesaep' => [
        // Same structure for LeSAEp flow
    ]
]
```

## Task: Expand Flow Settings (v1.2.4)

### Goal
Each flow should have access to ALL settings that currently exist as global options. When a flow doesn't have a custom value for a setting, it falls back to the global default.

### Architecture (Keep It Simple)

**Storage approach:** Store all settings directly in the flow array. Empty/null values mean "use global."

```php
'lesaep' => [
    'id' => 'lesaep',
    'slug' => 'lesaep',
    'ivr_file' => 'lesaep_ivr.md',
    
    // Product/Identity (always flow-specific)
    'product' => [...],
    
    // Style settings (empty = use global)
    'chat_style_preset' => '',      // or 'dark', 'light', 'auto'
    'chat_style_bubble' => '',      // or 'subtle-notch', 'classic', etc.
    'chat_style_accent' => '',      // or '#2563eb'
    'chat_style_font' => '',        // or 'inter', 'roboto', etc.
    
    // AI settings (empty = use global)
    'ai_provider' => '',            // or 'openai', 'anthropic', 'xai', 'ivr'
    'ai_base_prompt' => '',
    'ai_prompt_freeline' => '',
    'ai_prompt_login' => '',
    'ai_prompt_offer' => '',
    'ai_prompt_sale' => '',
    'ai_prompt_content' => '',
    
    // Email settings (empty = use global)
    'email_from_name' => '',
    'email_from_address' => '',
    'email_subject' => '',
    'email_body' => '',
    
    // Lessons settings
    'lessons_category' => '',       // WordPress category ID
    'free_lesson_count' => '',
    'oto_offer_id' => '',
    
    // Offers (array of offer objects, empty = use global)
    'offers' => [],
    
    // Payments - typically global, but can override
    'stripe_enabled' => null,       // null = use global, true/false = override
]
```

**Retrieval helper function:**
```php
/**
 * Get a setting value, checking flow-specific first, then global
 * 
 * @param string $key Setting key (without 'flosc_' prefix)
 * @param mixed $default Default if neither flow nor global has value
 * @param string|null $flow_id Force specific flow (null = auto-detect)
 */
function flosc_get_setting($key, $default = '', $flow_id = null) {
    $flow = $flow_id ? flosc_flows()->get_flow($flow_id) : get_current_flow();
    
    // Check flow-specific value first
    if ($flow && isset($flow[$key]) && $flow[$key] !== '' && $flow[$key] !== null) {
        return $flow[$key];
    }
    
    // Fallback to global
    return get_option('flosc_' . $key, $default);
}
```

### Admin UI Requirements

**Option A: Flow Selector Dropdown (Recommended)**

Add a dropdown at the top of the Settings page:

```
┌─────────────────────────────────────────────────────────┐
│ FLOSC Settings                                          │
│                                                         │
│ Editing: [▼ Global Settings        ]                    │
│          ├─ Global Settings                             │
│          ├─ FLOSC (default)                             │
│          ├─ LeSAEp                                      │
│          └─ Simplified Solfeggio                        │
│                                                         │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Product │ IVR │ Style │ AI │ Quiz │ Email │ ...    │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ [Form fields for selected context]                      │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

When "Global Settings" is selected:
- All fields editable
- Values saved to `wp_options` as usual

When a specific flow is selected:
- Fields show placeholder text: "Using global: [value]" when empty
- Fields are editable
- Clear button to reset to global
- Values saved to flow array in `flosc_flows` option

**Option B: Merge into Flows Page**

Remove separate Settings page entirely. Each flow card in the Flows list has "Edit" button that opens full settings for that flow. Add a "Global Defaults" pseudo-flow at the top.

### Files to Modify

1. **admin/settings.php** - Add flow selector dropdown, modify form handling
2. **includes/class-flow-manager.php** - Expand `normalize_flow_data()` with all settings fields
3. **flosc.php** - Update all `get_option('flosc_...')` calls to use `flosc_get_setting()`
4. **admin/flosc-app.php** - Use `flosc_get_setting()` for style/config values
5. **includes/class-ivr-parser.php** - Already flow-aware, verify it uses helper

### Settings Categories to Include

**Always flow-specific (no global fallback):**
- Identity: slug, custom_domain, status
- Product: name, tagline, emoji, logo_url, primary_color
- IVR: ivr_file
- Team: user access (stored in user meta)

**Flow-specific with global fallback:**
- Chat Styling: preset, bubble, accent, font, scale, custom_css
- AI Configuration: provider, api keys (careful with security), base_prompt, phase prompts
- Quiz: type, settings
- Email: from_name, from_address, templates
- AI Knowledge: personality, mission, boundaries
- Offers: array of offers (or reference to global offers by ID)
- Lessons: category, free_lesson_count, oto_offer_id

**Typically global only (shared infrastructure):**
- Payments: Stripe/PayPal API keys (one account serves all flows)
- STT: AssemblyAI/Deepgram API keys

### WordPress Admin Best Practices

1. **Use WordPress UI patterns:**
   - `nav-tab-wrapper` for tabs
   - `form-table` for settings rows
   - `notice notice-success/error` for messages
   - `button button-primary` for submit

2. **Form handling:**
   - Use `wp_nonce_field()` and `wp_verify_nonce()`
   - Sanitize all inputs
   - Show success/error notices after save

3. **JavaScript:**
   - Use jQuery (WordPress includes it)
   - For dropdown changes, consider AJAX or simple page reload with query param

4. **Responsive:**
   - Test on mobile-width admin screens
   - Use `max-width` on form containers

### Testing Checklist

- [ ] Create new flow, verify all tabs appear
- [ ] Set flow-specific style, verify frontend uses it
- [ ] Leave flow style empty, verify global is used
- [ ] Switch between flows in dropdown, verify correct values load
- [ ] Save flow settings, verify stored in `flosc_flows` option
- [ ] Save global settings, verify stored in `wp_options`
- [ ] Test API endpoints return flow-specific values
- [ ] Test frontend chat loads correct IVR per flow

### Code Quality

- No hardcoded values
- Consistent naming: `flosc_` prefix for global options, no prefix for flow array keys
- PHPDoc comments on functions
- Error logging with `FLOSC_DEBUG` flag
- Escape output with `esc_html()`, `esc_attr()`, `esc_url()`

## Existing Files Reference

Key files in `/mvp_sprint/flosc_1_2_3/`:
- `flosc.php` - Main plugin file (4400+ lines)
- `includes/class-flow-manager.php` - FLOSC_Flow_Manager class
- `includes/class-ivr-parser.php` - IVR markdown parser
- `admin/settings.php` - Global settings page (tab router)
- `admin/flow-edit.php` - Individual flow edit page
- `admin/flows.php` - Flow list page
- `admin/flosc-app.php` - Frontend chat template
- `admin/*.php` - Individual settings tabs (product.php, chat-styling.php, etc.)
- `ai_configuration_files/*.md` - IVR files per flow

## Summary

The v1.2.4 update expands per-flow settings from just identity/IVR to ALL configuration options. The implementation should be:
1. **Simple storage**: All settings as flat keys in flow array
2. **Simple retrieval**: `flosc_get_setting()` checks flow first, then global
3. **Clear UI**: Dropdown selector for which context you're editing
4. **WordPress-native**: Standard admin patterns, proper sanitization, nonces

Do not overcomplicate. Copy the existing settings UI patterns and adapt them to work with the flow selector.
