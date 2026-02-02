# What's New in FLOSC v05_03

**Release Date:** January 10, 2026
**Version:** 5.0.3

## Overview

Tab/menu order correction - all 9 items now appear in both tabs and menu in identical order.

---

## Tab/Menu Order Correction

### What Changed

**v05_02 Issue:** Settings page only had 6 tabs while menu had 9 items. Tabs were missing:
- AI Knowledge
- Offers
- Payments

**v05_03 Update:** All 9 tabs now appear in correct order matching menu exactly.

**Correct Order (Left-to-Right Tabs = Top-to-Bottom Menu):**
1. Product
2. IVR Messages
3. AI Configuration
4. Quiz
5. Email
6. AI Knowledge
7. Offers
8. Payments
9. Lessons

---

## Technical Implementation

### Added 3 New Tabs to Settings Page

Each new tab provides a clean interface with link to dedicated management page:

**AI Knowledge Tab (`tab=ai-knowledge`):**
- Brief description of AI Knowledge Files
- Button linking to full AI Knowledge manager page
- Styled info card matching existing design

**Offers Tab (`tab=offers`):**
- Brief description of Offers configuration
- Button linking to full Offers page
- Consistent info card design

**Payments Tab (`tab=payments`):**
- Brief description of Payment Providers
- Button linking to full Payments page
- Matching visual design

**Code Pattern:**
```php
<?php elseif ($active_tab === 'ai-knowledge'): ?>
<h2>AI Knowledge Files</h2>
<p class="description">Upload and manage markdown files...</p>
<div class="card" style="max-width: 800px; padding: 20px; background: #f0f9ff; border-color: #3b82f6;">
    <p>AI Knowledge files are managed on a dedicated page...</p>
    <p style="margin-top: 15px;">
        <a href="<?php echo admin_url('admin.php?page=flosc-ai-knowledge'); ?>" class="button button-primary">
            Go to AI Knowledge Files Manager →
        </a>
    </p>
</div>
```

**Files Modified:**
- `flosc.php` - Version bump to 5.0.3
- `templates/admin/settings.php` - Added 3 tabs, reordered tab navigation

---

## Testing Checklist

Before deploying v05_03:

**Tab Navigation:**
- [ ] Settings page shows 9 tabs in correct order
- [ ] All tabs are clickable
- [ ] Tab highlighting works correctly on each tab
- [ ] AI Knowledge tab shows link to dedicated page
- [ ] Offers tab shows link to dedicated page
- [ ] Payments tab shows link to dedicated page

**Menu/Tab Order Match:**
- [ ] Product: tab #1, menu #1 ✓
- [ ] IVR Messages: tab #2, menu #2 ✓
- [ ] AI Configuration: tab #3, menu #3 ✓
- [ ] Quiz: tab #4, menu #4 ✓
- [ ] Email: tab #5, menu #5 ✓
- [ ] AI Knowledge: tab #6, menu #6 ✓
- [ ] Offers: tab #7, menu #7 ✓
- [ ] Payments: tab #8, menu #8 ✓
- [ ] Lessons: tab #9, menu #9 ✓

**Functionality:**
- [ ] All existing tabs still work (Product, IVR Messages, AI Configuration, Quiz, Email, Lessons)
- [ ] Links from new tabs go to correct pages
- [ ] No broken links or 404 errors

---

## Backward Compatibility

✅ **Fully backward compatible** - No breaking changes

- All existing functionality preserved
- No database changes
- Settings values unchanged
- Only added missing tabs

---

## Upgrade Notes

**From v05_02:**
- Direct upgrade, no data migration needed
- Tab navigation will show all 9 tabs immediately
- No configuration changes required

**Recommended Actions:**
1. Clear browser cache if tabs don't appear correctly
2. Verify all 9 tabs visible in Settings page
3. Test navigation between tabs and menu items

---

## Version History

- **v5.0.3** (Jan 10, 2026) - Critical fix: Tab/menu order match (9 tabs)
- **v5.0.2** (Jan 10, 2026) - Menu restructuring, IVR documentation, IntroPanel improvements
- **v5.0.1** (Jan 9, 2026) - IntroPanel positioning, InfoCard clicks, phase reference corrections
- **v4.0.9** (Jan 9, 2026) - FLOSC phase correction, smart connection testing, UI terminology
- **v4.0.8** (Jan 9, 2026) - IntroPanel prompt cards configuration, persistence improvements
- **v4.0.7** (Jan 9, 2026) - Admin menu adjustments, IVR integration
- **v4.0.6** (Jan 9, 2026) - AI Connection Test [DEPRECATED]
- **v4.0.5** (Jan 9, 2026) - AI Orientation Files Manager
- **v4.0.4** (Jan 9, 2026) - Phase-Aware AI System
- **v4.0.3** (Jan 9, 2026) - IVR Admin Interface
- **v4.0.2** (Jan 9, 2026) - Message Visual Distinction
- **v4.0.1** (Jan 8, 2026) - Production Stabilization
- **v4.0.0** (Jan 2026) - FLOSC Framework Launch

---

## Contributors

- Core Development: Claude Sonnet 4.5 + Dainis Michel
- Testing & QA: Dainis Michel

---

## Support

For issues or questions, refer to plugin documentation or contact support.
