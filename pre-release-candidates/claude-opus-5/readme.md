# FLOSC 8.0.0 — candidate v75

    artifact   flosc.zip
    sha256     67a1406e6c8631b0bdea0f435d2a97dd72fac40f38a48900f7f461f26128b167
    entries    277, single flosc/ root, 0 under tests/
    base       claude-opus-5 v74
    version    8.0.0 — unchanged; this is the release being resubmitted

## Retracted from the v70 readme

That file printed this under a heading reading **"Measured, not asserted"**:

    phpcs --standard=WordPress, errors only, whole tree:
      NonceVerification                  0

WPCS emits `NonceVerification.Missing` and `.Recommended` as **warnings**. An
errors-only filter returns 0 for that sniff on every codebase that exists — it
cannot report a nonce problem. An unfiltered run on that same tree measured
**84**, in 8 files. The row was not a measurement. It is withdrawn.

Nothing below is filtered by severity.

## What v75 changed, site by site

The five sites the review named, and what is true of each now.

### `flosc.php` — `ajax_serve_user_audio` — **repaired**

`build_audio_access_signature()` and `is_valid_audio_access_signature()` both
already existed. **Nothing ever called the verifier.** `?exp=` and `?sig=` were
parsed into variables and never read again, and two of the three places that
built those URLs did not sign at all. The comment above the handler said a
signed URL was the origin proof; that was untrue of the code beneath it.

Now: the verifier is called before a byte is served, after the filename has been
validated so the signature covers the file actually requested. Both unsigned
builders sign. The capability check stays ahead of the parse.

    grep -n "is_valid_audio_access_signature" flosc-by-claude-opus-5/flosc/flosc.php

Four hits — two in comments, the call, the definition. In v74 it was one.

**This handler still has no nonce, and cannot have one.** It is called from an
`<audio src>`, where a nonce has nowhere to travel. The origin proof is the
HMAC. A scanner searching for `check_ajax_referer` will flag it again.

### `flosc.php` — `ajax_flosc_manage_chat_sessions` — **already correct, not my fix**

`check_ajax_referer()` was already the first statement before this pass. It was
repaired in an earlier iteration. The review email describes an older zip.

    sed -n "/function ajax_flosc_manage_chat_sessions/,+9p" flosc-by-claude-opus-5/flosc/flosc.php

### Eleven admin-ajax handlers — **repaired**

Each unslashed `$_POST` before verifying the nonce; several verified after a
capability branch that can exit. `check_ajax_referer()` is now the first
statement in all of them:

`ajax_flosc_rate_log`, `ajax_flosc_delete_chat_session`,
`ajax_accuracy_test_message`, `ajax_protect_category`, `ajax_unprotect_category`,
`ajax_test_sso_connection`, `ajax_test_ai_connection`, `ajax_flosc_get_chat_logs`,
`ajax_flosc_clear_chat_logs`, `ajax_flosc_admin_join`,
`ajax_flosc_admin_assign_tokens`.

### `admin/offers.php` — **repaired**

Offer deletion tested capability and nonce joined by `&&` in one condition, and
a failure fell through into the rest of the page instead of ending the request —
the shape the review explicitly warns about. Now two separate refusals that
`wp_die`, matching the `toggle_status` and `set_status` branches beside it. The
unconditional file-scope `$flosc_get = wp_unslash($_GET)` above them is removed;
every branch already re-read `$_GET` after its own checks, so nothing used it.

### `admin/ivr-messages.php` — **partial**

`$_POST` is now read only on an actual POST. Every state-changing branch calls
`check_admin_referer` with its own action; the one branch without a nonce only
calls `add_settings_error` and writes nothing.

**Not done:** `$flosc_get = wp_unslash($_GET)` is still at file scope, and the
request handling was not moved inside a function. That is the review's
performance point and it stands. The scanner will flag this file again.

### `includes/flosc-admin.php:1051` — **not fixed**

`maybe_process_flosc_settings_post()` is unchanged: capability checked first,
each sub-handler verifying its own nonce, under a `phpcs:ignore` carrying that
reason. A single top-level nonce would need one shared action across handlers
that use different ones, and restructuring that dispatcher was outside what
this pass could do safely. It will be flagged again.

## Measured in this container

    php -l                    clean — flosc.php, admin/offers.php, admin/ivr-messages.php
    zip entries               277
    zip entries under tests/  0
    zip flosc.php version     8.0.0
    verifier called in zip    1   (0 in the v74 zip)
    check_ajax_referer count  28  in the zip's flosc.php

Nothing else. **This container has no phpcs, no phpstan, no wp-env and no Plugin
Check.** Every other number belongs to a command below, not to a claim here.

## Not measured — run these

    CAND=pre-release-candidates/claude-opus-5/flosc-by-claude-opus-5/flosc

    phpcs --standard=WordPress -s --report=summary \
      --sniffs=WordPress.Security.NonceVerification "$CAND"

    phpcs --standard=WordPress -s --report=summary \
      --sniffs=WordPress.Security.EscapeOutput,WordPress.Security.ValidatedSanitizedInput,WordPress.Security.SafeRedirect,WordPress.DB.PreparedSQL,WordPress.DB.DirectDatabaseQuery "$CAND"

    phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- \
      --report=summary "$CAND"

    wp plugin check flosc --include-experimental

**I do not know what the first one prints.** The v74 tree measured 84 warnings
in 8 files. v75 reordered eleven handlers and repaired one signature check;
`NonceVerification.Recommended` flags the *read*, not its position, so some of
those 84 will remain. Predicting the number would be guessing, and a guess in
this file is what produced the row at the top that had to be retracted.

## Two things still not claimed

**That this passes review.** Their AI pass is non-deterministic — T12 and T13
reviewed identical bytes and returned different lists.

**That `Requires at least: 7.0` is true.** Both `flosc.php:7` and `readme.txt:5`
declare it and agree with each other, which is all the version check proves.
wp-env has only ever booted this plugin on 7.1. Nothing has tested 7.0.
