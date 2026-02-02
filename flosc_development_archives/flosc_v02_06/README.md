# LeSAEp FLOSC v02.06

Professional AI-powered pronunciation coach with Claude/ChatGPT-style interface.

## Overview

LeSAEp FLOSC is a WordPress plugin that creates a modern AI chat experience for Standard American English pronunciation coaching. The app features a clean, professional interface inspired by Claude.ai, ChatGPT, and Grok, with full conversational AI support and seamless payment integration.

## Key Features

### 🎨 Professional UI
- Claude-inspired clean design
- 300px sidebar with session history
- User profile card at bottom
- Mobile-responsive (sidebar drawer)
- Three user states: Visitor, Free, Paid

### 🤖 Multi-Provider AI Support
- **OpenAI** (ChatGPT) - gpt-4o-mini
- **Anthropic** (Claude) - claude-3-5-sonnet
- **xAI** (Grok) - grok-beta
- **IVR Mode** - Scripted responses (no AI)

### 💬 Chat Features
- Session persistence (user_meta + localStorage)
- Chat history grouped by date
- Auto-generated session titles
- Typing indicators
- Message streaming
- In-chat login gate for visitors

### 🔐 Access Control
- BuddyBoss group integration
- User meta flags
- Three-tier access (visitor/free/paid)
- Automatic upgrade flow

### 🔗 Sharing & Referrals
- User-specific referral codes
- Share modal with copy link
- 30-day cookie tracking
- Conversion tracking

## Installation

1. Upload `flosc_v02_06.zip` to `/wp-content/plugins/`
2. Extract the zip file
3. Activate through WordPress admin
4. Configure settings at **WP Admin → LeSAEp**

## Configuration

### General Settings

**App URL Slug** (default: `lesaep`)
- Your app will be accessible at: `yoursite.com/lesaep/`
- Supports both path and domain mapping

**BuddyBoss Paid Group ID**
- Users in this group automatically get paid access
- Find group ID in BuddyBoss → Groups

**Checkout URL**
- URL where users can purchase full access
- Supports WooCommerce, Stripe, or custom checkout

### AI Provider Settings

**AI Provider**
- Select: IVR, OpenAI, Anthropic, or xAI
- IVR mode requires no API keys (uses scripted responses)

**API Keys**
- OpenAI: Get from https://platform.openai.com/api-keys
- Anthropic: Get from https://console.anthropic.com/
- xAI: Get from https://x.ai/api

**Provider Details:**

| Provider | Model | Cost (approx) | Best For |
|----------|-------|---------------|----------|
| OpenAI | gpt-4o-mini | $0.15/1M tokens | Fast, affordable |
| Anthropic | claude-3-5-sonnet | $3/1M tokens | Highest quality |
| xAI | grok-beta | Varies | Fun personality |
| IVR | Scripted | Free | Testing, demos |

## Architecture

### Virtual Page System

The plugin intercepts requests to `/lesaep/` (or your configured slug) and renders a complete HTML page without WordPress theme interference. This provides full control over the UI and ensures a consistent experience.

**How it works:**
1. Rewrite rule captures URL
2. Template redirect loads `templates/lesaep-app.php`
3. Assets enqueued: `assets/css/lesaep-app.css` + `assets/js/lesaep-app.js`
4. JavaScript initializes app based on user state

### User States

**Visitor (not logged in)**
- Sees landing page with suggested prompts
- Can chat for 2 interactions
- Login gate appears after 2 messages
- Sessions stored in localStorage

**Free User (logged in, no payment)**
- Full chat history in sidebar
- Personalized greeting
- Upgrade banner (dismissible)
- Sessions stored in user_meta

**Paid User (logged in, paid access)**
- Same as Free but:
- No upgrade nag
- Access to all lessons
- "Pro" badge in profile

### Session Management

**Data Model:**
```php
[
    'session_id' => 'session_abc123_1234567890',
    'created_at' => '2026-01-07 12:00:00',
    'title' => 'How to improve my pronunciation',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'How to improve my pronunciation?',
            'timestamp' => '2026-01-07 12:00:05'
        ],
        [
            'role' => 'assistant',
            'content' => 'I can help you with that...',
            'timestamp' => '2026-01-07 12:00:08'
        ]
    ]
]
```

**Storage:**
- Logged-in users: WordPress user_meta (`_lesaep_sessions`)
- Visitors: Browser localStorage (`lesaep_sessions`)
- Grouping: Today, Yesterday, Last 7 days, Older

### REST API Endpoints

**Base:** `yoursite.com/wp-json/flosc/v1/`

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/ai-query` | POST | Send message, get AI response |
| `/sessions` | GET | List user's sessions |
| `/sessions` | POST | Create new session |
| `/sessions/{id}` | GET | Load specific session |
| `/sessions/{id}/messages` | POST | Append message to session |
| `/access` | GET | Check user access level |
| `/referral` | POST | Generate referral link |

### File Structure

```
flosc_v02_06/
├── lesaep-flosc.php                    Main plugin file
├── templates/
│   └── lesaep-app.php                  Virtual page template
├── assets/
│   ├── css/
│   │   └── lesaep-app.css              Claude-inspired styling
│   └── js/
│       └── lesaep-app.js               App logic & state management
├── includes/
│   ├── class-ai-provider-factory.php   AI routing & providers
│   ├── class-session-manager.php       Session CRUD operations
│   └── class-access-control.php        Permission checks
└── README.md                           This file
```

## Usage

### For End Users

1. **Visitor Experience:**
   - Visit `/lesaep/`
   - See landing page
   - Click suggested prompt or type message
   - Chat for 2 interactions
   - Login gate appears → create account or log in

2. **Free User Experience:**
   - Log in
   - See chat history in sidebar
   - Continue conversations
   - Upgrade banner visible
   - Limited lesson access

3. **Paid User Experience:**
   - Log in
   - Full access to all features
   - No upgrade nag
   - Pro badge in profile
   - All lessons unlocked

### For Developers

**Granting Paid Access:**
```php
// Via BuddyBoss group
groups_join_group($group_id, $user_id);

// Via user meta
update_user_meta($user_id, '_lesaep_paid_access', true);

// Via access control class
LeSAEp_Access_Control::grant_access($user_id);
```

**Custom AI Provider:**
Extend `LeSAEp_AI_Provider_Base` in `class-ai-provider-factory.php`:
```php
class LeSAEp_Custom_Provider extends LeSAEp_AI_Provider_Base {
    public function get_response($prompt) {
        // Your custom logic
        return "Response text";
    }
}
```

**Hooks & Filters:**
```php
// Modify user state detection
add_filter('lesaep_user_state', function($state, $user_id) {
    // Custom logic
    return $state;
}, 10, 2);

// Customize AI system prompt
add_filter('lesaep_ai_system_prompt', function($prompt, $provider) {
    return "Custom prompt...";
}, 10, 2);
```

## Mobile Responsive

- **Desktop (>768px):** Fixed 300px sidebar
- **Mobile (≤768px):** Sidebar becomes drawer
  - Hamburger menu in header
  - Swipe or tap to open/close
  - Overlay when open

## Browser Support

- Chrome/Edge (latest 2 versions)
- Firefox (latest 2 versions)
- Safari (latest 2 versions)
- iOS Safari (latest 2 versions)
- Chrome Android (latest version)

## Performance

**Optimizations:**
- Lazy loading of session history
- Message pagination (future)
- Asset minification (production)
- CDN for static assets (optional)

**Recommended Limits:**
- Max 100 sessions per user
- Max 50 messages per session
- Archive old sessions (>30 days)

## Security

**Built-in protections:**
- Nonce verification on all REST calls
- Input sanitization (sanitize_text_field, etc.)
- Output escaping (esc_html, esc_attr, etc.)
- API keys stored server-side only
- HTTPS required for API calls

## Troubleshooting

**App not loading at /lesaep/:**
- Go to Settings → Permalinks → Save (flush rewrite rules)
- Check App URL Slug in LeSAEp settings
- Verify no conflicting plugins

**AI not responding:**
- Check API key is entered correctly
- Verify provider is selected
- Check browser console for errors
- Try IVR mode to test basic functionality

**Sessions not saving:**
- For logged-in users: Check user_meta in database
- For visitors: Check localStorage in browser dev tools
- Verify REST API is accessible: `/wp-json/flosc/v1/sessions`

**Styling issues:**
- Clear browser cache
- Check for theme CSS conflicts
- Verify assets are loading (Network tab)

## Support

- **Documentation:** This README
- **Issues:** [GitHub Issues](https://github.com/yourusername/lesaep-flosc)
- **Email:** support@lesaep.com

## License

GPL v2 or later

## Credits

**Created by:** Dainis W. Michel
**Version:** 2.0.6
**Release Date:** January 7, 2026

**Inspired by:**
- Claude.ai (Anthropic)
- ChatGPT (OpenAI)
- Grok (xAI)

---

Built with ❤️ for language learners worldwide.
