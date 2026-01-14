# FLOSC Changelog

All notable changes to FLOSC are documented here using Michel TimeStamp Innovation format.

---

## v8.0.1 (2026-01m-14d-17:03:02) - Consolidated Changelog & Production Ready

First stable 8.x release with fully functional IVR system and unified changelog.

**Critical Fix**:
- Fixed admin settings fatal error (`FLOSC_IVR_Manager` → `FLOSC_IVR_Parser`)

**Documentation**:
- Consolidated 30+ changelog files into one WHATS_NEW.md
- Adopted proper versioning convention (8.0.1, not 08.00)
- Adopted proper directory naming (flosc_v8_0_1, not flosc_v08_00)

**Status**: Production ready, all legacy conflicts resolved

---

## v7.0.9 (2026-01m-14d-00:00:00) - IVR System Cleanup

Complete cleanup of IVR implementation, removing legacy code conflicts.

**Removed**:
- Old `FLOSC_IVR_Manager` system (renamed to `-legacy.php`)
- Old Quick Messages settings (`flosc_welcome_message`, etc.)
- IntroPanel static HTML (now fully dynamic)

**Added**:
- Persistent message tracking (REST endpoint `/ivr/track`)
- Event flags: `justCompletedQuiz`, `justLoggedIn`, `justPurchased`
- Auto-create default ivr.md on activation

**Status**: 95% complete (admin page had one fatal error)

---

## v7.0.8 (2026-01m-14d-00:00:00) - Complete IVR Overhaul

Markdown-based IVR system with condition evaluation and dynamic message rendering.

**New System**:
- `FLOSC_IVR_Parser`: Parse ivr.md markdown format
- `FLOSC_Condition_Evaluator`: Evaluate complex conditions with boolean logic
- Default ivr.md with 50+ stock messages across all phases
- Admin interface for editing ivr.md

**Frontend**:
- Dynamic suggested replies with icons and styles
- Auto messages triggered by conditions
- Offer display with countdown timers
- Variable replacement ({name}, {score}, etc.)
- Action handlers (open_quiz, checkout, etc.)

**Status**: 70% complete (dual systems conflict, no persistence)

---

## v5.0.6 (2026-01m-10d-00:00:00) - Unified Tab/Menu Architecture

All menu items redirect to Settings page tabs with full content.

- Consistent 9-tab structure matching menu order
- Simplified navigation: No separate pages; everything centralized
- Read-only Offers display (CRUD pending)

---

## v5.0.5 (2026-01m-10d-00:00:00) - Menu Repairs & UX Enhancements

Restored missing menu items and improved messaging UX.

- Restored missing menu items (Product, Quiz, Email, Lessons)
- Fixed tab navigation and content loading
- Added 1-second "thinking" delay for bot responses
- Removed user message backgrounds for cleaner UI
- Improved message styling with subtle backgrounds and no borders

---

## v5.0.4 (2026-01m-10d-00:00:00) - IntroPanel Centering & Card Fallbacks

Fixed IntroPanel layout and added fallback messaging.

- Centered prompt cards with `margin: 0 auto`
- Added fallback texts for cards if database options empty
- Ensured cards functional with proper messaging triggers

---

## v5.0.3 (2026-01m-10d-00:00:00) - Tab/Menu Order Correction

Aligned all 9 tabs with menu order.

- Fixed missing tabs (AI Knowledge, Offers, Payments)
- Proper tab order matching admin menu

---

## v5.0.2 (2026-01m-10d-00:00:00) - Menu Restructure & IntroPanel Improvements

Logical menu with shortcuts to tabs.

- Improved IntroPanel: Rounded corners, centered content, subtle shadows
- Added IVR framework docs
- Enhanced prompt card flow and persistence

---

## v5.0.1 (2026-01m-09d-00:00:00) - IntroPanel & InfoCard Fixes

Fixed IntroPanel layout issues.

- Fixed IntroPanel "scooting left" with flex centering
- Resolved InfoCard interaction issues
- Consolidated phase structure references

---

## v4.0.9 (2026-01m-09d-00:00:00) - Phase Correction & AI Testing

Reduced to 5 phases and improved AI diagnostics.

- Consolidated Login Prompt into Login (5 phases total)
- Improved AI test with error messages and troubleshooting
- Renamed "AI Orientation" to "AI Knowledge" for clarity

---

## v4.0.8 (2026-01m-09d-00:00:00) - AI Guide & IntroPanel Enhancements

Added AI setup guidance and improved IntroPanel.

- AI setup guide with steps, links, and comparisons
- Fixed IntroPanel prompt cards and persistence
- Refined IVR responses for better interactions

---

## v4.0.7 (2026-01m-09d-00:00:00) - Critical Bug Fixes

Fixed AI Config and IVR Manager errors.

- Fixed AI Config fatal error (method name correction)
- Resolved IVR Manager initialization issue
- Ensured admin interface loads properly

---

## v4.0.5 (2026-01m-09d-00:00:00) - AI Orientation Files Manager

Added file management for AI knowledge bases.

- Upload, list, delete files for AI context
- Auto-inclusion in system prompts
- Topic-agnostic for custom content

---

## v4.0.4 (2026-01m-09d-00:00:00) - Phase-Aware AI System

Three-tier prompt system for context-aware responses.

- Base prompts (database)
- Phase-specific prompts (markdown files)
- Knowledge files (uploaded content)
- Dynamic merging for full context
- Fallback to IVR if AI fails

---

## v4.0.3 (2026-01m-09d-00:00:00) - IVR Admin Interface

Phase-aware messaging configuration interface.

- Sequential/triggered messages with Markdown support
- Admin UI with add/remove, enable/disable
- Inactivity timers and phase transitions

---

## v4.0.2 (2026-01m-09d-00:00:00) - UI/UX Improvements

Improved message display and user experience.

- Distinct user/bot messages (right/left alignment, backgrounds)
- Prompt card flow: User text first, then bot response
- Removed upgrade banner
- Improved color system and markdown rendering

---

## v4.0.1 (2026-01m-08d-00:00:00) - Professional Standards

Applied professional coding standards throughout.

- Proper naming conventions and structure
- Fixed activation hook
- Terminology cleanup
- JavaScript standards compliance

---

## v3.0.9 (2026-01m-09d-00:00:00) - Activation Hook Fix

Fixed plugin activation to properly set defaults.

- Moved registration outside class for proper execution
- Ensured database defaults set on activation

---

## v3.0.8 (2026-01m-09d-00:00:00) - IntroPanel + IVR Commands

Added dismissible welcome panel with chat control.

- Dismissible IntroPanel
- IVR commands (e.g., "Show IntroPanel", "Status")
- Self-documenting UX via chat messages

---

## v3.0.7 (2026-01m-09d-00:00:00) - Database Defaults Fix

Reliable out-of-box experience with proper defaults.

- Set defaults on activation with `update_option()`
- No more cache clearing required

---

## v3.0.6 (2026-01m-09d-00:00:00) - Default Fallbacks

Added JavaScript fallbacks for all content.

- JS fallbacks for messages and quiz content
- Fixed prompt cards and recording modal
- Marked funnel complete after payment

---

## v3.0.5 (2026-01m-09d-00:00:00) - Proper Architecture

Conditional rendering based on user state.

- Fixed sidebar/profile layout with flexbox
- No more hidden elements via CSS
- Proper show/hide logic

---

## v3.0.4 (2026-01m-09d-00:00:00) - Functional Prompt Cards

Prompt cards trigger actions with sensible defaults.

- Cards trigger actions properly
- New Messages tab for editing
- Dynamic quiz/lesson/offer content

---

## v3.0.3 (2026-01m-09d-00:00:00) - Out-of-Box Functionality

Plugin works immediately after installation.

- Passed `FLOSC_CONFIG` to JavaScript
- Dynamic lessons from posts
- Email quiz results + OTO
- Pre-login score storage
- Free lesson delivery
- Chatbot lock post-free lesson

---

## v3.0.1 (2026-01m-08d-00:00:00) - Quiz Type Framework

Extensible quiz type system with dynamic configuration.

- Extensible quiz types with registry
- Dynamic settings and responses per type
- Audio/STT/AI support per type

---

## v2.0.9 (2026-01m-08d-00:00:00) - SALE System

Complete sales and access management system.

- Multiple payment providers (Stripe, Tokens, Affiliates, ClickBank)
- Offer types (one-time, subscriptions, hybrids)
- Usage tracking and access management
- Webhook handling for all providers

---

For full technical details on any version, see the Git commit history or contact support.
