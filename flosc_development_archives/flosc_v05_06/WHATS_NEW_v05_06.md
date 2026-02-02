# What's New in FLOSC v05_06

**Release Date:** January 10, 2026
**Version:** 5.0.6

## Overview

Unified tab/menu architecture - all menu items now redirect to Settings page tabs. Each tab contains full configuration content. No more separate pages with different content for the same menu item.

---

## Architecture Change: Unified Tab/Menu System

**Problem in v05_05:** 
- Some menu items (AI Configuration, AI Knowledge, Offers, Payments) went to separate pages
- Same-named tabs showed different (limited) content
- IVR Messages tab and menu item showed completely different interfaces

**Solution in v05_06:**
- ALL menu items now redirect to Settings page with appropriate tab
- Each tab contains the FULL configuration for that section
- Menu = Tab = Same content (two entry points, one destination)

### Menu Structure (9 items):
1. **Product** → Settings > Product tab
2. **IVR Messages** → Settings > IVR Messages tab  
3. **AI Configuration** → Settings > AI Configuration tab
4. **Quiz** → Settings > Quiz tab
5. **Email** → Settings > Email tab
6. **AI Knowledge** → Settings > AI Knowledge tab
7. **Offers** → Settings > Offers tab
8. **Payments** → Settings > Payments tab
9. **Lessons** → Settings > Lessons tab

---

## Tab Content Updates

### AI Configuration Tab
- Added Connection Test feature (previously only on dedicated page)
- Added xAI API key field
- Added Base System Prompt field
- Full API key configuration with documentation links

### IVR Messages Tab
- Consolidated simple message settings AND advanced phase configuration
- Quick Messages: Welcome, Get Started, How It Works, What You Learn
- Advanced Phase Config: Initial messages for all 5 FLOSC phases
- FLOSC phase explanation included

### AI Knowledge Tab
- Full file manager interface
- Upload markdown files
- View existing knowledge files
- Edit capability

### Offers Tab
- Display current offers with name, price, type, status
- Read from offers database

### Payments Tab
- Stripe configuration: Enable/Disable, Mode (test/live)
- Test and Live API key fields
- All keys saved via WordPress options

---

## Technical Changes

### flosc.php
- Menu items all use redirect functions
- Added redirect functions: `redirect_to_ivr_tab()`, `redirect_to_ai_tab()`, `redirect_to_ai_knowledge_tab()`, `redirect_to_offers_tab()`, `redirect_to_payments_tab()`
- Registered payment settings: `flosc_stripe_enabled`, `flosc_stripe_mode`, `flosc_stripe_test_pk`, etc.

### class-ivr-manager.php
- Disabled own menu registration (handled by main FLOSC class)
- Updated admin script enqueue to work on Settings page IVR tab

### settings.php
- All tab URLs use `?page=flosc-settings&tab=X` format
- Each tab includes full configuration content
- Proper form handling for each section

---

## Testing Checklist

- [ ] Click each menu item → redirects to correct Settings tab
- [ ] Each tab shows full configuration (not stub/link)
- [ ] AI Connection Test works
- [ ] IVR quick messages save correctly
- [ ] Payment settings save correctly
- [ ] No duplicate menu items appear

---

## Backward Compatibility

- Old direct URLs to dedicated pages will continue to work for now
- Settings are saved to same options as before
- No data migration needed

---

## Known Limitations

- Offers tab shows read-only display (full CRUD coming later)
- AI Knowledge file delete/edit requires page refresh
- Advanced IVR phase config is simplified compared to dedicated page

---

## Version History

- **v5.0.6** (Jan 10, 2026) - Unified tab/menu architecture
- **v5.0.5** (Jan 10, 2026) - Menu repair, thinking delay, user message styling
- **v5.0.4** (Jan 10, 2026) - IntroPanel centering, card fallbacks
- **v5.0.3** (Jan 10, 2026) - Tab/menu order correction
- **v5.0.2** (Jan 10, 2026) - Menu restructuring, IntroPanel improvements
