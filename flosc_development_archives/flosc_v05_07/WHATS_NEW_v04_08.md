# What's New in FLOSC v04_08

**Release Date:** January 9, 2026
**Version:** 4.0.8

## Improvements in This Release

This release enhances the AI configuration experience, improves IntroPanel behavior, and refines IVR response patterns for better user interactions.

---

## What's New

### 1. Comprehensive AI Setup Guide

**Enhancement:** AI Configuration page now includes detailed step-by-step setup instructions

**Details:**
- Added "Quick Start: Connect Your AI" guide at the top of AI Config page
- Step-by-step instructions for each provider (IVR, OpenAI, Anthropic, xAI)
- Direct links to API key pages for all providers
- Cost comparisons and "best for" recommendations
- Billing setup guidance with links to payment pages
- Visual design with color-coded sections for easy scanning

**Why:** Plugin administrators can now easily set up AI providers without external documentation. Clear instructions reduce setup friction and support requests.

**Files Changed:**
- templates/admin/ai-config.php

### 2. Enhanced IVR Response Patterns

**Enhancement:** IVR (scripted) mode now handles more user question types accurately

**New Patterns Added:**
- Connection test responses: "connection test" → "Connection successful! I'm ready to help you."
- Presence confirmation: "are you there", "can you hear me", "anyone there" → Confirming presence
- Identity questions: "who are you", "who is this", "what are you" → Introducing the AI assistant
- More specific quiz triggers to avoid false positives
- More conversational default response

**Why:** Users were getting misleading or irrelevant responses from the IVR system. These enhanced patterns ensure appropriate, contextual responses that don't confuse or mislead users.

**Files Changed:**
- includes/class-ai-provider-factory.php (lines 164-222)

### 3. IntroPanel Persistence & Behavior Enhancement

**Enhancement:** IntroPanel now persists independently and cards send real AI requests

**Details:**
- IntroPanel moved outside landing-state container for independent persistence
- Clicking cards sends real messages to the AI (not canned responses)
- Conversation history properly builds and accumulates
- IntroPanel stays visible alongside messages after first interaction
- "Start free quiz" continues to open quiz modal directly
- IntroPanel only hides when:
  - User clicks the X button (manual close)
  - 300-second auto-hide timer expires
  - User manually closes it with "hide intropanel" command
- Messages now persist across page refreshes via localStorage for visitors

**Why:** Previous implementation had IntroPanel inside landing-state, so it disappeared when messages were shown. Users couldn't continue using prompt cards after first interaction, and conversation history was lost on refresh. New structure allows users to interact with cards multiple times while building conversation context, then dismiss IntroPanel when ready.

**Files Changed:**
- templates/flosc-app.php (IntroPanel structure moved outside landing-state)
- assets/js/flosc-app.js (handlePromptCardAction method)
- assets/css/flosc-app.css (IntroPanel positioning and close button styling)

### 4. IntroPanel Close Button Enhancement

**Enhancement:** Close button now clearly positioned and styled as a circular button

**Details:**
- Close button positioned at top-right corner of IntroPanel area
- Styled as circular button with white background and border
- Clear visual separation from prompt cards (no overlap)
- Larger icon (20x20) for better visibility and clickability
- Drop shadow for depth

**Why:** Previous positioning with inline-block layout caused close button to overlap prompt cards, creating confusion about what would be closed. New circular button design clearly indicates it closes the entire IntroPanel, not individual cards.

**Files Changed:**
- assets/css/flosc-app.css (IntroPanel and close button styles)

---

## Technical Details

### Admin Menu Priority
- Main FLOSC admin menu hook now uses priority 5 (default is 10)
- Ensures Settings submenu loads before IVR Messages submenu
- Prevents 404 errors when clicking main "FLOSC" menu item

### AI Provider Links
All provider links are direct and up-to-date:
- **OpenAI:** https://platform.openai.com/api-keys
- **OpenAI Billing:** https://platform.openai.com/settings/organization/billing/overview
- **Anthropic Keys:** https://console.anthropic.com/settings/keys
- **Anthropic Billing:** https://console.anthropic.com/settings/billing
- **xAI Console:** https://console.x.ai

---

## Testing Checklist

Before deploying v04_08, verify:

✓ AI Config page loads and displays setup guide
✓ Setup guide includes all four providers with correct links
✓ Links to API key pages open in new tabs
✓ IntroPanel displays centered on landing page
✓ Clicking IntroPanel cards sends real AI requests
✓ Conversation builds properly (doesn't reset)
✓ IntroPanel stays visible after clicking cards
✓ IntroPanel hides only when X is clicked OR after 300 seconds
✓ "Start free quiz" card opens recording modal
✓ IVR responses are contextually appropriate
✓ Test "are you there" → gets presence confirmation
✓ Test "who are you" → gets identity introduction
✓ Test "connection test" → gets connection success message
✓ Main FLOSC menu goes to Settings page (not 404)

---

## Version History

- **v4.0.8** (Jan 9, 2026) - AI setup guide, IVR enhancements, IntroPanel improvements
- **v4.0.7** (Jan 9, 2026) - Admin menu priority adjustment, IVR integration
- **v4.0.6** (Jan 9, 2026) - AI Connection Test [DEPRECATED - DO NOT USE]
- **v4.0.5** (Jan 9, 2026) - AI Orientation Files Manager
- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface & Phase-Aware Messaging
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction & Prompt Card Flow
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch

---

## Migration Notes

No database migrations or breaking changes. Plugin can be updated directly from v04_07.

**Recommended Actions After Update:**
1. Visit AI Config page to review new setup guide
2. Test IntroPanel behavior on frontend
3. Test AI responses with various question types
4. Verify admin menu navigation works correctly

---

## Known Issues

None reported in this release.

---

## Support

For issues, questions, or feature requests, refer to plugin documentation or contact support.
