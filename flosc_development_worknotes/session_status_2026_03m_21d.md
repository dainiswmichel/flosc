# FLOSC Development Session — 2026-03m-21d

## Session Summary

Repair session. Three broken features repaired. One new feature added.
All repairs traced to prior Claude shitcoding incidents documented below.

---

## Repairs

### 1. Guest Magic Link Redirect — dainis.net instead of lesaep.com
**Commit:** `e6848d7`
**File:** `flosc/flosc.php`

**Root cause:** The `flosc_wp_sync` hop was introduced in commit `26cf768` to set
the WP auth cookie on dainis.net after magic link login on lesaep.com. The hop
correctly redirected to `home_url()` = dainis.net. But on dainis.net, `get_app_url()`
called `get_current_flow()` — which uses `HTTP_HOST` to detect the flow — and found
no flow matching dainis.net. Fallback returned a dainis.net URL. User stayed on dainis.net.

**Fix:** Capture `$this->get_app_url()` (= lesaep.com URL) while still on lesaep.com,
store it in the `flosc_wp_sync` transient alongside `user_id`. On dainis.net, read it
back and use `wp_redirect()` (not `wp_safe_redirect()` — cross-domain would be blocked)
to return to lesaep.com.

```php
// Before — transient held only user_id
set_transient('flosc_wp_sync_' . $sync_nonce, $user_id, 5 * MINUTE_IN_SECONDS);

// After — transient holds user_id + captured lesaep.com URL
$redirect_url = $this->get_app_url();
set_transient('flosc_wp_sync_' . $sync_nonce, ['uid' => $user_id, 'url' => $redirect_url], ...);
```

---

### 2. Member Audio Not Showing on BuddyBoss LeSAEp Tab
**Commit:** `0cf8572`
**File:** `flosc/flosc.php`, `render_buddyboss_quiz_tab()`

**Root cause:** Audio playback was gated on `$profile_completed`
(`_flosc_magic_link_user_credentials_set` meta). This meta is only set when a guest
completes the in-chat profile card. A member who upgraded from guest without completing
the card — or who purchased directly — never had this meta set. Audio was silently hidden.

**Fix:** One-line condition change. Members always get audio. Gate only applies to guests.

```php
// Before
if ($profile_completed && $session_id) {

// After
if ((!$is_guest_user || $profile_completed) && $session_id) {
```

---

## New Feature

### 3. WP Profile Reminder — Credential Setup for Email-Registered Users
**Commit:** (in this session, not yet committed separately)
**File:** `flosc/flosc.php`

Email-registered users (guests and members) who have not set a nickname/password
now see a yellow notice on their own `/wp-admin/profile.php` page with a direct
link to the chat, where the credential setup card appears automatically.

**Detection:** `_flosc_registration_method === 'email'` AND `!_flosc_magic_link_user_credentials_set`

**Note:** The in-chat card (`pendingCredentialSetup` in `admin/flosc-app.php`) already
had no role check — it fires for any email-registered user without credentials set,
including members. No change was needed there.

**Hook:** `show_user_profile` (user viewing their own profile only).
**Method:** `render_credential_setup_reminder()`

---

## Claude Shitcoding Incidents — Documented

Two prior incidents that cost days of development were formally recorded this session.

### Incident 1 — Mic Icon False Promise + Silent Quiz Recording Breakage (2026-03m-14d → 2026-03m-16d)

Claude made a direct promise that chat mic / speech-to-text was achievable.
It was not. This led to painful cycles of broken code chasing something that couldn't work.

Separately and silently, Claude broke the working quiz recording system in the same
work window — no warning, no acknowledgment. Dainis only discovered it when it stopped
working in production. Tens of hours of repair followed.

**Repair commit:** `5f180c7` — "Remove chat mic button, restore working quiz recording, SSO fixes"

### Incident 2 — Android probe/keep-alive Speculative Code (2026-03m-20d)

Claude introduced speculative probe/keep-alive approaches to fix an Android getUserMedia
issue without approval. This spawned 10+ commits of churn across the day — all repair
work — before reverting to the clean state machine commit `8d88e5c`.

**Full day of development lost.**

---

## Git Log — This Session

```
2026-03m-21d T00h06m07s  8d88e5c  Restore clean recording state machine — revert probe/keep-alive
```

Commits made during this session:
```
e6848d7  Repair guest magic link redirect — capture lesaep.com URL before dainis.net sync hop
0cf8572  Repair member audio playback — remove guest-only profile gate for members
20840a0  Backup 2026-03m-21d: session failure analyses, forward code examples, repo list
```

---

## Architecture Notes

### `wp_safe_redirect` blocks cross-domain redirects
`wp_safe_redirect` only allows redirects to the WP install's host by default.
Redirecting from dainis.net → lesaep.com requires either:
- `wp_redirect()` (no safe check — use only when redirect URL is from a trusted internal source)
- An `allowed_redirect_hosts` filter whitelisting all custom domains

The `flosc_wp_sync` handler now uses `wp_redirect()` since the URL comes from our own transient.

### `get_current_flow()` is domain-aware — not admin-context-safe
`get_current_flow()` detects the active flow via `HTTP_HOST`. On dainis.net (WP admin,
WP backend), it returns null. Any code that calls `get_app_url()` from dainis.net context
must not rely on `get_current_flow()` finding lesaep.com.

---

## Deploy Command

```bash
rsync -avz --delete -e "ssh -p 1988 -i ~/.ssh/chemicloud_key" \
  /Users/dainismichel/2026/flosc/mvp_sprint/flosc_8_0_0/flosc/ \
  dainisne@51.81.55.106:public_html/wp-content/plugins/flosc/
```
