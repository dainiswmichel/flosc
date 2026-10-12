# FLOSC 8.0.0 — Grok candidate V145

V145 starts from the V144 source at commit `9fab042357c00235b4760748c8a3f52b6ad81c8e`. The public plugin version and stable tag remain 8.0.0. Requires at least remains 7.1. Requires PHP remains 7.4. `Github HashID` is that V144 commit.

This commit replaces the V139 `grok-4-6` folder.

## Scope

Admin submissions for flow identity, flow records, team updates, flow delete, feedback, praise, and chat-style reset post to `admin-post.php`. Each handler calls `check_admin_referer()` before it reads the request, then `current_user_can()` as its own check. Knowledge-base toggle and delete, the profile email-account activation button, and term VGM use that order. Term VGM returns early when its nonce field is absent, because `created_term` and `edited_term` also run for other term saves.

## Verification

`php -l` passed on the eleven edited PHP files. PHPCS with `phpcs.xml.dist` and `memory_limit=2G`, errors only, reported no errors on those files. `unzip -t` passed. The zip has 243 files, one `flosc/` root, and no `tests/` entries.

Plugin Check was not run. This commit is not a live deploy.

## Exact artifact

```text
1a1c5dd2e7e64cce09a287d9a6c426895f17c6de023aa689c8f24a7d46e75756  flosc.zip
```

283 zip entries, 243 files, 2,826,276 bytes. Sorted `sha256  path` manifest of those files:

```text
600df173f184c04f8b858aaaa16b5f26c589b7e267779557ebad78b53f5195c0
```
