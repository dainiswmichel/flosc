# FLOSC — Development & Security Recommendations

**Date:** 2026-01-13

This document summarizes findings from an initial code review and lists prioritized recommendations, concrete fixes and example patches to improve security, robustness, and developer experience.

---

## TL;DR (Quick summary) ✅

- High priority: CSRF & capability checks missing in AI Knowledge file manager (`templates/admin/ai-knowledge-base.php`). This enables CSRF or accidental privileged file ops.
- Medium: Knowledge files stored inside plugin directory (risk of being overwritten on update), `register_setting()` calls without sanitize callbacks, and missing size checks on uploads.
- Good: Stripe/ClickBank webhook signature verification and idempotency, prepared statements for DB access, structured logging, and front-end XSS mitigations.

---

## Prioritized Findings & Recommendations

### 1) Missing nonce / capability checks — AI Knowledge manager (HIGH) ⚠️

- File: `templates/admin/ai-knowledge-base.php`
- Issue: POST handlers perform file upload/create/edit/delete without `check_admin_referer()` or `current_user_can()` checks.
- Risk: CSRF or malicious link can cause admin user to upload/overwrite/delete knowledge files (prompt injection, data loss).

Recommended fix (minimum):

1. Add nonce fields to forms (server and upload forms):

```php
<?php wp_nonce_field('flosc_ai_files_nonce'); ?>
```

2. At top of POST handler, verify nonce & capability:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! current_user_can('manage_options') ) {
        wp_die('Unauthorized', 403);
    }
    if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'flosc_ai_files_nonce')) {
        wp_die('Invalid request (nonce)', 403);
    }
    // handle upload/create/edit/delete now
}
```

Longer-term: move file operations into an admin handler (e.g., `admin_post_flosc_ai_save`) or secured AJAX action (`wp_ajax_...`) rather than handling in template.

Additional: sanitize and validate filenames (already using `sanitize_file_name`) and restrict extension to `.md` only.

---

### 2) Storing knowledge files inside plugin folder (MEDIUM)

- Location: `ai_configuration_files/` under plugin directory.
- Issue: plugin updates can overwrite plugin dir; some hosts restrict write permissions.
- Recommendation: store files in uploads directory (`wp_upload_dir()`), e.g. `wp_upload_dir()['basedir'] . '/flosc-ai/'`. Ensure plugin creates directory with `wp_mkdir_p()` and uses `wp_upload_dir()['baseurl']` when exposing links.

Snippet:

```php
$upload_dir = wp_upload_dir();
$target_dir = trailingslashit($upload_dir['basedir']) . 'flosc-ai/';
if (! file_exists($target_dir)) { wp_mkdir_p($target_dir); }
$upload_path = $target_dir . $filename;
```

Also update `load_configuration_files()` in `includes/class-ai-provider-factory.php` to read from the uploads dir (with a fallback to plugin dir for migration/backwards compatibility).

---

### 3) `register_setting()` calls missing sanitize callbacks (MEDIUM)

- File: `flosc.php`
- Issue: Many settings are registered without a sanitize callback, which means raw values can be saved in options.
- Recommendation: add sanitize callbacks appropriate to each setting, e.g.: `sanitize_text_field`, `absint`, `sanitize_email`, or custom sanitizer for structured data.

Example:

```php
register_setting('flosc_settings', 'flosc_openai_api_key', ['sanitize_callback' => 'sanitize_text_field']);
register_setting('flosc_settings', 'flosc_ga4_id', ['sanitize_callback' => 'sanitize_text_field']);
```

---

### 4) Upload file size & content checks (MEDIUM)

- Add server-side size limit (e.g., 10MB) for `.md` uploads and reject files containing `<?php` or other indicators of non-markdown content.
- Check MIME type if necessary.

Example:

```php
if ($file['size'] > 10 * 1024 * 1024) { $error = 'File too large'; }
$content = file_get_contents($file['tmp_name']);
if (strpos($content, '<?php') !== false) { $error = 'Invalid file content'; }
```

---

### 5) Webhooks & idempotency (GOOD) ✅

- Stripe: HMAC signature verification and timestamp check implemented, good idempotency via transient.
- ClickBank: signature verification via `hash('sha256', ...)` and idempotency — good.

No immediate changes required; keep monitoring.

---

### 6) XSS / output sanitization (GOOD) ✅

- Server-side templates use `esc_html`, `esc_attr`, `wp_kses_post` in many places.
- Front-end uses DOMPurify or fallback sanitization for assistant messages and `escapeHtml()` for user messages — good.

Recommendation: ensure DOMPurify is included for production or add a dependency note in README.

---

### 7) Tests & CI (MISSING)

- No obvious GitHub Actions or PHPUnit tests found.
- Recommendation: add basic unit tests and a CI workflow to run `phpcs`, `phpunit`, and static analysis (phpstan) on PRs. Add tests for:
  - Webhook signature verification
  - IPN/callback idempotency
  - AI provider error handling (mock remote requests)
  - File upload handling and sanitize/nonce checks

---

### 8) Privacy & uninstall behavior (LOW)

- Add `register_uninstall_hook()` or `uninstall.php` to allow site owners to remove plugin data (options, stored files) on uninstall, and document stored data in a privacy policy.

---

## Suggested Implementation Plan (short)

1. Add nonce + capability checks to `ai-knowledge-base.php` and move file ops to admin handler (HIGH).
2. Move knowledge files to uploads dir with migration code (MEDIUM).
3. Add sanitize callbacks to `register_setting()` calls (MEDIUM).
4. Add file size/content validation (MEDIUM).
5. Add tests and a GitHub Actions configuration (LOW/Medium depending on priorities).
6. Add uninstall and privacy docs (LOW).

---

## Example code patches (copy/paste)

Nonce + capability check (replace the top of the POST handling block):

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! current_user_can('manage_options')) {
        wp_die('Unauthorized', 403);
    }

    if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'flosc_ai_files_nonce')) {
        wp_die('Invalid request (nonce)', 403);
    }

    // Continue with sanitized request handling
}
```

Add the nonce field to forms:

```php
<form method="post" enctype="multipart/form-data">
    <?php wp_nonce_field('flosc_ai_files_nonce'); ?>
    <!-- file input ... -->
</form>
```

Move storage to uploads dir and validate:

```php
$upload_dir = wp_upload_dir();
$target_dir = trailingslashit($upload_dir['basedir']) . 'flosc-ai/';
if (! file_exists($target_dir)) { wp_mkdir_p($target_dir); }
if ($file['size'] > 10 * 1024 * 1024) { $error = 'File too large'; }
$upload_path = $target_dir . $filename;
```

---

## PR checklist template

- [ ] Add nonce checks and capability checks where necessary
- [ ] Add unit tests for new/modified code
- [ ] Add sanitize callbacks to `register_setting()` calls
- [ ] Add file size/content validation
- [ ] Migrate AI configs to uploads dir and add migration fallback
- [ ] Update README and SECURITY.md describing fixed issues and usage guidance

---

## Notes & follow-up items

- Consider enabling content-scanning for uploaded MD files (optional) to detect suspicious strings.
- Consider documenting recommended secret management (e.g., use constants or env vars for production-critical keys rather than DB options).

---

If you'd like, I can open a PR patching `ai-knowledge-base.php` to add nonce + capability checks and move upload logic into a small admin handler (I can include tests for that change). Which fix should I prioritize next? 
