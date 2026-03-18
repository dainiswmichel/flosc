# Session Status — 2026-03m-17d

## Sync Status

- **Local = ChemiCloud** ✅ — full directory rsync confirmed working (see note below)
- **GitHub** — commit `308dd5f` local only, push still pending
- **Files deployed:** everything via full directory rsync (see standard command)

### ⚠️ Rsync Gotcha — Use Full Directory Sync Only
`--relative` with multi-file rsync deploys to wrong paths (doubles the `flosc/` prefix). Always use the standard full-directory command:
```bash
cd /Users/dainismichel/2026/flosc/mvp_sprint/flosc_8_0_0 && rsync -avz -e 'ssh -p 1988 -i ~/.ssh/flosc_deploy' flosc/ dainisne@dainis.net:~/public_html/wp-content/plugins/flosc/ --exclude='.git' --exclude='*.bak*'
```
Single-file explicit path rsync also works:
```bash
rsync -avz -e 'ssh -p 1988 -i ~/.ssh/flosc_deploy' flosc/flosc.php dainisne@dainis.net:~/public_html/wp-content/plugins/flosc/flosc.php
```

---

## SSH / Rsync Access — Setup Notes

### Server Details
- **Host:** `dainis.net` (VPS Hosting - Cloud 1, ChemiCloud)
- **Server:** `cvps1505.serverhostgroup.com`
- **SSH Port:** `1988` (NOT 22 — port 22 is blocked)
- **cPanel Username:** `dainisne`
- **Plugin path:** `~/public_html/wp-content/plugins/flosc/`

### Claude Code SSH Key
- **Key name on server:** `claude_code` (authorized in cPanel → SSH Access)
- **Key type:** `ed25519`
- **Local key path:** `~/.ssh/flosc_deploy`
- **Public key fingerprint:** `SHA256:HND23G+elUPhGuOr5NA87R/q7c2iMg2bPpTkCLQRcgA`
- **Key label:** `claude-code-flosc`

### From User's Terminal (if Claude Code IP is blocked)
```bash
cd /Users/dainismichel/2026/flosc/mvp_sprint/flosc_8_0_0 && rsync -avz -e 'ssh -p 1988' flosc/ dainisne@dainis.net:~/public_html/wp-content/plugins/flosc/ --exclude='.git' --exclude='*.bak*'
```
(Uses `id_rsa` key which is already authorized on server)

### fail2ban Warning
- ChemiCloud uses fail2ban. Too many failed SSH auth attempts will block the IP temporarily (~10-30 min).
- If Claude Code environment IP changes between sessions and SSH times out, get current IP via `curl -s https://api.ipify.org` and ask ChemiCloud support to unblock it.
- ChemiCloud live chat is fast — Jerusalem T resolved the block within minutes on 2026-03m-17d.

---

## Completed This Session

### 1. Admin InfoPanel — Not Rendering (Fix)
- **File:** `assets/js/flosc-app.js` — `_renderAdminTestPanel()`
- **Bug:** PHP template (`admin/flosc-app.php` line 396) pre-renders an empty `<div id="flosc_input_user_autoprompts_panel">` placeholder div. `_renderAdminTestPanel()` checked `getElementById(...)` and returned early if it existed — so the panel was never built.
- **Fix:** Now checks `existingPanel.querySelector('#flosc-admin-panel-toggle')` before short-circuiting.
- **Bonus:** Added `show infopanel` to the local command handler so typing "Show InfoPanel" in chat triggers the panel directly.

### 2. Guest Link Abuse Tracking
- **Files:** `flosc.php`, `admin/login-registration.php`
- Persistent `flosc_guest_link_log` WP option (replaces transient counter)
- `send_guest_link_warning_email()` — triggered on 6th request from same email
- `_flosc_links_sent` user meta snapshot on first link click
- Admin "Guest Link Activity Log" table in Register & Login settings tab (sorted by count, ⚠️ on ≥6)

### 3. Guest LeSAEp Learner WP Role
- **Files:** `flosc.php`, `includes/class-member-access.php`
- New WP role: `guest_lesaep_learner` / "Guest LeSAEp Learner" (registered on init + activation)
- Email link users get this role on first click (instead of Subscriber)
- Existing subscriber-only email link users upgraded on next click
- On upgrade/purchase → `guest_lesaep_learner` removed, `lesaep_learners` added (clean swap in `grant_level()`)
- Admins can now filter/sort WP Users list by Guest vs Member

### 4. flosc.ai DKIM — Now Verified ✅
- Zoho Mail Admin → Domains → flosc.ai → DKIM → Added selector `flosc`
- TXT record `flosc._domainkey.flosc.ai` added in ChemiCloud cPanel Zone Editor
- Verified in Zoho — guest link emails from flosc.ai addresses now deliverable

### 5. Instant Logout (No Confirmation Page)
- **Files:** `flosc.php`, `admin/flosc-app.php`, `assets/js/flosc-app.js`
- Old: `wp_logout_url()` → `wp-login.php` → "Do you really want to log out?" confirmation screen (cross-domain nonce issue)
- New: AJAX action `flosc_logout` → `admin-ajax.php` (always PHP, never cached) → instant logout
- JS shows **"See you later LeSAEp Fam! 👋"** in chat → 2 second pause → redirect to `dainis.net`
- Logout redirect configurable via `flosc_get_setting('logout_redirect_url', home_url())`

---

## Past (Previous Sessions — Key Context)

### Task 3: Complimentary LeSAEp Learners Guest Access Link (2026-03m-17d, earlier)
Full implementation across `flosc.php`, `admin/login-registration.php`, `admin/flosc-app.php`, `assets/js/flosc-app.js`:
- Deferred user creation (pending transient → active on first click)
- 30-day / 10-use token lifecycle
- In-chat check-email message, welcome message with `{n}` remaining uses
- In-chat profile card on first login (name + optional password)
- Admin "Send Guest Link" dispatch tool
- UUID-based username (`usr_xxxxxx`)
- Cross-domain auth via `remove_query_arg('flosc_magic')` without URL arg
- DO session copy + delete on first click

---

## Present — Known Issues

### Profile Card: Should Show on Every Guest Login (Not Just First)
- Currently `isFirstGuestLogin` flag controls profile card display
- Decision: show on every login if display_name is still `usr_xxxxxx` (not yet personalized)
- Not yet implemented

### Git Push Pending
- Commit `308dd5f` is local only — not yet pushed to GitHub
- Session changes also uncommitted locally

### Logout — Needs Testing
- AJAX logout + "See you later LeSAEp Fam!" message not yet confirmed working in browser
- Test: click Log out → should see goodbye message in chat → redirect to dainis.net (no wp-login.php)

---

## Future / Next Session

- [ ] Test logout flow: goodbye message + redirect (no confirmation page)
- [ ] Test full guest link flow end-to-end as `d@da1.fm` — send link, click, confirm Guest LeSAEp Learner role in WP admin
- [ ] Profile card: show on every guest login when name not yet set (check if `display_name` starts with `usr_`)
- [ ] Verify admin InfoPanel renders automatically below composer on page load
- [ ] Verify Guest Link Activity Log appears in admin settings tab
- [ ] Push git commits to GitHub
- [ ] Consider: rate-limit guest link requests (max 10 per email, block after that)
- [ ] Access Code feature (back door for testers/family/friends) — from 2026-03m-12d backlog
- [ ] `logout_redirect_url` admin setting — so admin can point to a custom "See you later" page
