# FLOSC v3.0.4 - "Works Out of the Box" (COMPLETED)

## What's New

### 🎯 Critical Fix: Prompt Cards Now Functional
- **Fixed: Prompt cards were echoing text instead of triggering actions**
- "Get started" → Shows welcome message and guides user
- "Start free quiz" → Opens recording modal to begin quiz
- "How does it work?" → Explains the FLOSC flow
- "What will I learn?" → Shows learning outcomes
- No more "I want to get started" echoing back!

### 💬 New: Default Messages System
- **New: Messages Tab** in admin settings (`FLOSC > Settings > Messages`)
- All messages pre-filled with sensible defaults using "Default FLOSC" naming
- Fully editable via WordPress admin
- Messages passed to frontend via `FLOSC_CONFIG.messages`
- Backend fields for:
  - Welcome Message
  - Get Started Response
  - How It Works Response
  - What You Learn Response

### 🚀 One-Click Activation Setup
Plugin now creates default content automatically on first activation:

- **Default Quiz**: Simple Scoring with items "1,2,3,4,5,6,7,8,9,10"
- **Default Lessons**: 10 sample lesson posts in "Default FLOSC Lessons" category
- **Default Offer**: "Default FLOSC Full Access" ($97) with premium features
- **Default Messages**: Pre-filled welcome, get started, how it works, what you learn

All labeled with "Default FLOSC" prefix so admins know what to customize.

### 🔒 Sidebar Hidden for New Users
- New users don't see "New Chat" button or chat history
- Sidebar elements hidden until funnel completed
- CSS: `.flosc-funnel-incomplete` hides `.new-chat-btn` and `.session-history`
- User must complete: quiz → login → free lesson → upgrade prompt
- After funnel completion, full sidebar appears

### 🎬 Complete "Works Out of Box" Experience

**First-time install flow:**
1. Activate plugin → Default content created automatically
2. Visit /app/ → See welcome screen with 4 working prompt cards
3. Click "Start free quiz" → Recording modal opens
4. Complete quiz → Score stored, prompted to login
5. Login → Email sent with score + OTO
6. Free lesson delivered → Chatbot locks with upgrade prompt
7. Sidebar appears → User can now start new chats

**Admin experience:**
- All default content clearly labeled "Default FLOSC..."
- Admin Settings > Messages tab shows all default messages
- Easy to customize messages without touching code
- Sample lessons ready to replace with real content

## File Changes

### Modified Files

**`flosc.php`**
- Version bumped to 3.0.4 (lines 6, 17)
- Added `create_default_content()` method (lines 185-279)
  - Creates default messages with "Default FLOSC" prefix
  - Creates default quiz: Simple Scoring 1-10
  - Creates 10 default lesson posts
  - Creates default "Full Access" offer ($97)
  - Sets all defaults only on first activation
- Added `funnelCompleted` to user_data (line 442)
- Registered new settings for messages (lines 576-580)
- Added `/funnel-complete` REST endpoint (lines 748-753)
- Added `mark_funnel_complete()` handler (lines 1207-1225)

**`templates/admin/settings.php`**
- Added "Messages" tab to nav (line 21)
- Added Messages tab content with 4 textarea fields (lines 73-114)
  - Welcome Message
  - Get Started Response
  - How It Works Response
  - What You Learn Response

**`templates/flosc-app.php`**
- Added body class logic for funnel completion (lines 33-40)
- Body gets `.flosc-funnel-incomplete` class if not completed
- Added `messages` object to FLOSC_CONFIG (lines 395-400)
  - Passes all 4 default messages to JavaScript
- Changed prompt cards from `data-prompt` to `data-action` (lines 152-167)
  - `data-action="get-started"`
  - `data-action="start-quiz"`
  - `data-action="how-it-works"`
  - `data-action="what-you-learn"`

**`assets/js/flosc-app.js`**
- Fixed prompt card event handlers (lines 143-149)
  - Changed from echoing text to calling `handlePromptCardAction()`
- Added `handlePromptCardAction()` method (lines 330-368)
  - get-started: Shows backend message
  - start-quiz: Opens recording modal
  - how-it-works: Shows backend message
  - what-you-learn: Shows backend message

**`assets/css/flosc-app.css`**
- Added sidebar hiding rules (lines 1217-1221)
  - Hides `.new-chat-btn` and `.session-history` for `.flosc-funnel-incomplete`

## REST API Additions

```
POST /flosc/v1/funnel-complete  - Mark funnel as completed for logged-in user
```

**Purpose**: Called after user completes the FLOSC flow to unlock sidebar.

## Database Changes

**New User Meta:**
- `_flosc_funnel_completed` (boolean) - Tracks if user completed the funnel flow

**New Options:**
- `flosc_welcome_message` - Default welcome message
- `flosc_get_started_message` - Get started response
- `flosc_how_it_works_message` - How it works response
- `flosc_what_you_learn_message` - What you learn response
- `flosc_default_content_created` - Flag to prevent re-creating defaults

## Testing Checklist

- [x] Fresh install creates all default content
- [x] Admin > Messages tab shows pre-filled fields
- [x] Visit /app/ as visitor - sidebar hidden
- [x] Click "Get started" - shows message (doesn't echo)
- [x] Click "Start free quiz" - opens recording modal
- [x] Click "How does it work?" - shows explanation
- [x] Click "What will I learn?" - shows learning outcomes
- [x] Default lessons category created with 10 posts
- [x] Default offer created and configured
- [x] After login, sidebar appears for returning users
- [x] Funnel completion tracked correctly

## Breaking Changes

None - this version only fixes broken functionality and adds new features.

## Migration Notes

**Upgrading from v3.0.3:**
- Default content will be created automatically if not present
- Existing messages/quizzes/lessons/offers are NOT overwritten
- New Messages tab added to admin settings
- Prompt cards will start working correctly

## Security Notes

- Funnel completion endpoint requires authentication
- All messages sanitized with `esc_textarea()` and `wp_json_encode()`
- No new SQL queries or file operations
- Uses standard WordPress options and user_meta APIs

## Performance Impact

- Minimal: One-time content creation on activation
- No additional database queries on frontend
- CSS hiding is instant (no JavaScript required)
- Messages loaded once in FLOSC_CONFIG object
