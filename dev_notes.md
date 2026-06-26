# FLOSC — Dev Notes

Single source of truth for anyone (human or AI coding assistant) working on
this plugin: **where the files are**, **how we do git**, and **what we're
working on** (dev log + task tracking with MTS timestamps).

This file lives in the plugin repo but is excluded from the shipped zip via
`.distignore`. Keep it current.

---

## 1. Where things are

### The canonical repo (verify before any work)

The workspace `/Users/dainismichel/2026/flosc_project_folder` contains **11
nested git repositories**. There is exactly ONE active plugin repo:

    /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc

- Remote: `origin` → `https://github.com/dainiswmichel/flosc.git`, branch `main`.
- The top folder was renamed `flosc/` → `flosc_project_folder/` on 2026-06-09.
  Any path still saying `/2026/flosc/...` is STALE.
- Everything else with a `.git` (the outer `flosc_project_folder/` wrapper,
  `lesaep/`, `flosc_development_archives/flosc_v8_0_*`, `quiz_development/*`,
  `dev_cool/*`, any `*.pre-restore-*` snapshot) is NOT the plugin. Never commit
  plugin work there.

Always confirm first:

    cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc
    git rev-parse --show-toplevel   # must print the path above

### Plugin internal layout (runtime code)

| Path | Purpose |
|------|---------|
| `flosc.php` | Main plugin bootstrap / header |
| `uninstall.php` | Uninstall cleanup |
| `admin/` | Admin UI pages (~29 `*.php`: flows, lessons, quiz, payments, AI config, IVR, etc.) |
| `includes/` | Core classes (`class-*.php`): flow manager, access/member, RAG/AI chat, IVR parser, quiz, sessions, pronunciation analyzer |
| `includes/quiz-types/`, `includes/sale/` | Quiz type implementations; sale/offer logic |
| `assets/css/`, `assets/js/`, `assets/img/` | Front-end assets |
| `ai_configuration_files/` | IVR + lesson config (markdown) for AI personalities |
| `sample-data/` | Seed/sample data |

Scratch/dev files present in the tree but NOT plugin runtime (already in
`.distignore`, candidates for removal): `check_tina.php`, `debug-bp-search.php`,
`mvp_sprint/` (nested duplicate), various `font-comparison*.html`.

---

## 2. Git procedure (do this every time)

1. **Verify the repo** before any git command or any claim about state:
   `git rev-parse --show-toplevel` must equal the canonical path above.
2. **Read before asserting.** Never state HEAD/branch/cleanliness without:
   `git status -b`, `git rev-parse HEAD`, `git log --oneline -5`.
   If two checks disagree, STOP — investigate, don't report a conclusion.
3. **Commit full state.** Stage all intended tracked changes; GitHub is a
   complete backup, not a per-session snapshot. Clear, scoped commit messages.
4. **Human owns push & deploy.** Assistants supply exact commands; they do not
   `push`/`force-push`/deploy. A pre-push allowlist hook exists (`.githooks/pre-push`,
   added in commit `298bf5e`) — respect it.
5. **Back up before any destructive op** (`reset --hard`, `clean`, `rm -rf`,
   `checkout -- .`, `restore`, history rewrite):

       git bundle create ../flosc-<MTS>.bundle --all
       tar czf ../flosc-worktree-<MTS>.tar.gz .

   Dry-run first where possible (`git clean -nd`), get explicit confirmation,
   keep the backup until the result is confirmed correct.
6. **Deploy = rsync to ChemiCloud — ALWAYS with `--exclude-from='.distignore'`.**
   The plugin root is ALSO the git repo root and holds large non-runtime working
   dirs (`flosc_development_archives/` ~470MB, `.git/` ~300MB, `mvp_sprint/`, `tmp/`,
   the doc folders) plus workspace files (`dev_notes.md`, `claude.md`, `*.html`
   scratch, `.DS_Store`). Plain `rsync` does NOT read `.distignore`, so it copies ALL
   of that into the live plugin folder. On 2026-06m-11d a plain rsync pushed ~780MB —
   including the full `.git` history — into a public web path. The required command,
   run FROM the plugin root:

       rsync -avz --exclude-from='.distignore' \
         -e "ssh -p 1988 -i ~/.ssh/chemicloud_key" \
         /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc/ \
         dainisne@51.81.55.106:public_html/wp-content/plugins/flosc/

   - NEVER `rsync --delete` without per-run confirmation (a prior `--delete` deleted
     production files). Without `--delete`, the server's private `*_ivr.md` flow
     configs are simply skipped (excluded) and left intact.
   - VERIFY after EVERY deploy: `ls -la` the live plugin dir and `find . -name .git`.
     Only runtime may be present: `flosc.php`, `uninstall.php`, `readme.txt`, `admin/`,
     `ai_configuration_files/`, `assets/`, `includes/`, `sample-data/`. Anything else
     is a deploy error — remove it.
   - Then reset opcache (ChemiCloud does not auto-reload): curl a one-shot
     `<?php opcache_reset();` file over HTTPS, then delete it. `wp cache flush` is not
     enough.
7. **Never commit secrets** (`.env`, `*.pem`, `*.key`, `*client_secret*.json`).
   Redact secret values when echoing config.
8. **Code to the Plugin Check + PHPCS WPCS bar** from the start.

### Building the release zip (clean, plugin-only)

There is NO wp-cli / build step on this machine, so `.distignore` is currently
inert (nothing reads it). The working release mechanism is **`git archive` +
`.gitattributes export-ignore`** (built into git, no install):

    git archive --worktree-attributes --prefix=flosc/ --format=zip \
      -o ../flosc.zip HEAD

The release zip MUST be named `flosc.zip` (the wp.org plugin slug) — never an
MTS-suffixed name. The MTS goes on the git TAG, not the zip filename.

- `.gitattributes` marks the non-plugin dirs and dev docs `export-ignore`, so they
  stay TRACKED in the repo but are omitted from the zip (additive — no deletions).
- `git archive` ships only files COMMITTED at HEAD. Therefore the real plugin
  files must be committed for the zip to be complete (see §4).
- `.distignore` is kept for future wp-cli/`wp dist-archive` use; it mirrors the
  same exclusions.

---

## 3. MTS — Michel Time Signature (how we timestamp)

MTS is our colon-free, second-precision timestamp used for git tags and the dev
log below. Format:

    YYYY-MMm-DDd-TZ<zone>-HHhMMmSSs
    e.g.  2026-06m-09d-TZEEST-08h38m18s

- The `m`/`d`/`h`/`m`/`s` letters delimit fields so no `:` is needed (git refs
  forbid `:`). Zone is the live `date` zone (EEST = UTC+3 in summer, Europe/Riga).
- **Generate live** (never hand-type):

      TZ="Europe/Riga" date "+%Y-%mm-%dd-TZ%Z-%Hh%Mm%Ss"

- **Checkpoint tag:** `git tag -a <MTS> -m "<what changed>, toward vX.Y.Z"`
  Tags advance per checkpoint; the plugin header version is NOT bumped each time.
  Tags stay local until explicitly pushed.
- Canonical spec: `flosc_development_worknotes/michel-date-stamp-innovation.md`.

---

## 4. Current state (update as it changes)

_As of 2026-06m-12d-TZEEST-17h12m41s:_

- **Fresh repository baseline.** Pre-launch history (all commits and tags
  through the v8.0.0 resubmission work) is archived as complete `--all` bundles
  in `flosc_development_archives/repo_cleanup_2026-06m-12d/` at the project
  root. The repo was re-initialized at a plugin-only baseline; GitHub `main`
  was reset to it and pre-baseline tags removed from the remote. Project
  history begins at wp.org acceptance.
- **The repo carries only the plugin + dev docs** (~6 MB working tree). The
  February 2026 cruft commits (469 MB of archives, nested working dirs) exist
  only in the archived bundles now — they can never resurface via reset/clone.
- **Release zip — built per §2 and verified.** `flosc.zip` (built with
  `git archive --worktree-attributes`) sits in the plugin root; `*.zip` is in
  both `.gitignore` and `.distignore`, so it never enters git or a deploy.
  Contents verified: 146 files, no macOS metadata, no `.git`, no workspace
  files; the four private site-specific `*_ivr.md` configs are excluded, the
  three public default personas ship.
- **Live deploy verified 2026-06m-12d.** ChemiCloud plugin files hash-match
  local on all checked files (flosc.php, admin/settings.php,
  assets/css/flosc-admin.css, class-stt-dispatch.php, class-payment-provider.php,
  class-paypal-provider.php). Opcache reset applies after any future rsync (§2.6).
- Version markers agree: `flosc.php` header `Version: 8.0.0`,
  `readme.txt` `Stable tag: 8.0.0`.
- The old secrets-in-tracked-cruft concern is closed for the remote: cruft is
  no longer in the repo. The archived bundles are local-only.

---

## 5. FLOSC house coding standard

These rules state the invariants the codebase is written to. Every contributor
(human or AI assistant) must follow them in every file they touch. The rationale
for each rule is in the comment that follows it.

**a. Input.** Every superglobal read is per-key, unslashed and sanitized at the
read site with a type-appropriate sanitizer (`sanitize_key`, `sanitize_text_field`,
`absint`, `esc_url_raw`, etc.). Whole-array snapshots (`$get = wp_unslash($_GET)`)
introduce all keys at once and postpone sanitization, which makes it easy for a
later edit to use an unsanitized value.

**b. Output.** Escaping is applied once, at the site where FLOSC constructs
markup, using the narrowest escaper that fits (`esc_html`, `esc_attr`, `esc_url`,
`wp_kses_post`). Content received from WordPress core and relayed unchanged is not
re-filtered, because double-filtering can corrupt valid markup and implies FLOSC
does not trust core's output.

**c. State changes.** Every request that mutates persistent state — regardless of
HTTP method — verifies a nonce and a capability before the first read of its
parameters. This prevents cross-site request forgery and privilege escalation
through the admin UI.

**d. REST.** The `permission_callback` on every REST route expresses entitlement:
login state, flow access, or a named WordPress capability. Rate limiting alone is
not a permission model; it only limits throughput, not authorization.

**e. Storage.** `flosc_data_dir()` is the only writable location; all file writes
go through `flosc_write_data_file()`. The plugin directory contains shipped
defaults and is treated as read-only at runtime, because WordPress replaces it on
upgrade and it is publicly accessible via HTTP.

**f. Authentication.** `wp_set_auth_cookie()` appears in exactly five places: the
post-purchase session issuance function (`flosc_issue_post_purchase_session`), the
magic-link/token consumer (`handle_login_token`), the cross-domain sync handler, the
login-token continuation, and the continuation after `wp_set_password()`. Payment
endpoints grant access meta unconditionally on verified payment, but they create an
authenticated session only by calling `flosc_issue_post_purchase_session()` after
`flosc_checkout_binding_verify()` confirms a server-issued, single-use binding token
proving the request is the buyer's own browser (see §5b in `flosc.php`). This separates
"you paid" from "you are logged in", which WordPress.org requires and which prevents a
copied payment identifier from minting a session. Server-to-server paths (webhooks, IPN)
have no browser and never issue sessions; those buyers arrive via the emailed single-use
link. Line numbers shift as the file changes; locate by symbol, not by line.

**g. Registration APIs.** Callbacks passed to `register_setting()` and similar
WordPress registration functions are literal callables, statically resolvable at
read time. Dynamic method-name construction (`array($this, $this->someMethod())`)
prevents static analysis, makes PHPCS audits unreliable, and obscures what will
actually run.

**h. Assets.** Styles and scripts reach pages through `wp_enqueue_style`,
`wp_enqueue_script`, or `wp_add_inline_style` / `wp_add_inline_script`, called
during the appropriate enqueue hook. Markup-embedded `<style>` and `<script>` tags
bypass WordPress's dependency and deduplication system and can appear in unexpected
page contexts.

**i. Identifiers.** Code references only identifiers — option keys, meta keys,
action names, style handles, function names — that exist in the repository or are
defined explicitly in the task specification. Zero-occurrence identifiers introduce
a parallel data model that nothing reads and nothing writes.

**j. Reporting.** A completion claim is accompanied by the verbatim, unedited
output of the verification commands specified in the task. Formatted prose
asserting "all tests pass" without the command output is not evidence.

---

## 6. Dev log (reverse chronological — newest entry on top)

### 2026-06m-12d-TZEEST-17h53m20s — fresh-baseline repo, Plugin Check clean, submission candidate

- **Repo re-initialized at a plugin-only baseline** (`ac425d0`). Pre-launch history
  (all commits, 9 tags, all branches) archived as verified `--all` bundles in
  `flosc_development_archives/repo_cleanup_2026-06m-12d/` at the project root, with
  a browsable copy of every removed file alongside. GitHub `main` force-reset to
  the baseline; pre-baseline tags removed from the remote. `.git` went 306 MB → 2.5 MB;
  the plugin folder is now ~9 MB total. §4 records the rationale.
- **Folder discipline restored:** `mvp_sprint/flosc_8_0_0/` holds only `flosc/` and
  `svn-assets/`; all dev material lives at the project root. The February cruft
  commits can no longer resurface via reset/clone — they are not in the repo.
- **Plugin Check findings resolved and tool-verified** (phpcs + WPCS 3.x installed
  locally; control run confirmed the sniff flags the pre-edit code):
  - `flow-edit.php`: `visitor_menu_items` sanitized at intake via `map_deep()`;
    `team_users` folded to a single sanitized intake expression. Behavior identical.
  - `offers.php`: both nonce reads wrapped in `wp_unslash()`.
  - `flosc.php`: dead `load_plugin_textdomain()` removed (`languages/` holds only an
    index stub; wp.org language packs auto-load on WP 7.0).
  - `class-usage-tracker.php` / `class-clickbank-provider.php`: translators comments
    added for placeholder strings. Plugin-wide I18n + ValidatedSanitizedInput sweeps: clean.
- **Delivery state:** HEAD `360ffee` == `origin/main`. `flosc.zip` built per §2
  (`git archive`), 146 files, SHA-256 `02b11affaffe19f6fae85d0b1058db74a2336d9df4c58934546fd01a92d9ab3a`.
  Live ChemiCloud deploy hash-matched, opcache reset, all three domains 200.
  The local Plugin Check WP install (`flosc_development_worknotes/wp-content/`) now
  carries the exact zip contents — its next scan certifies the submission artifact.
- Open: a clean Plugin Check re-run on the updated install is the final binary gate.

Each work session = one MTS-stamped entry below, prepended above the previous
one. Each entry lists task items with a priority and status.

- **Priority:** `[P1]` critical/blocking · `[P2]` important · `[P3]` nice-to-have
- **Status:** `[ ]` open · `[~]` in progress · `[x]` done

### 2026-06m-12d-TZEEST-14h15m00s — third review pass + honest readiness statement

- `[P2] [x]` Content protection: all four visibility exits now wrap their filter
  return in `wp_kses_post()` (the public-post branch was the last unwrapped one).
- `[P2] [x]` Eliminated the `(bool) _flosc_member_access` bug class entirely — four
  reads now use strict `'true' ===` (the meta stores the string `'false'` on
  revocation; `(bool) 'false'` is truthy). Commits `e6870ce`/`d8af813`.
- **Verified-false review claims (no change):** offers.php / flow-edit.php nonces are
  present; Deepgram is intentionally removed (not missing); AssemblyAI URLs return 200.

**Honest readiness — what "100%" still requires, and why it is not code I can fix here:**
1. **Plugin Check in WordPress Playground against this exact zip** — the only binary
   certification of wp.org-readiness, and it cannot be run outside a WP install.
   Every code-level finding the reviews raised is now resolved or verified-false, but
   that is necessary, not sufficient.
2. **Internationalization is absent** — no `load_plugin_textdomain`, no `languages/`,
   user-facing strings are not wrapped in `__()`/`esc_html__()`. Plugin Check flags
   this. It is a large mechanical pass across the whole plugin, not a one-liner —
   the single biggest remaining gap to a clean check. Delegatable.
3. **~53 security-category `phpcs:ignore` directives** (NotPrepared / NonceVerification /
   InputNotSanitized / MissingUnslash). Defensible case-by-case for a custom-table
   plugin, but the concentration is what a human reviewer weighs; reducing it is an
   audit, not a quick edit.
4. **Owner decisions, not code bugs:** whether to trim the ClickBank / LinkedIn /
   Microsoft / xAI providers (D4), and whether instant post-purchase login stays the
   default (it does, per owner; the binding+claim+is_new_user design secures it).

### 2026-06m-12d-TZEEST-13h30m00s — second review pass (built-zip review)

External review of the built zip caught issues the static in-repo review missed.
Verified each against the code; fixed the real ones, rejected the false ones.

- `[P1] [x]` **FATAL fixed:** the six `register_setting` sanitize_callback methods
  (`sanitize_{text,textarea,url,hex,bool,array}_setting`) were `private`. Core calls
  that callback from outside the class via `sanitize_option_{$option}`, so saving any
  FLOSC settings page threw "Call to private method" and fataled. Now public, with a
  docblock forbidding re-narrowing. This is precisely what a WP_DEBUG clean-install
  settings-save test exists to catch; the static review could not.
- `[P1] [x]` **Binding-token replay closed.** The token proved "this browser started a
  checkout", not "buyer of THIS subscription" — a leaked subscription id replayed with
  the attacker's own token resolved to the victim's existing account and logged the
  attacker in. Instant login now requires `$is_new_user` (account created in THIS
  verified request) plus a one-time per-subscription claim transient; existing/returning
  buyers always use the emailed link. New-buyer instant-login UX preserved.
- `[P1] [x]` **Member gate bug.** `/ivr/messages` used `(bool) get_user_meta(..., '_flosc_member_access')`;
  the meta is stored as the string `'false'` on revocation, and `(bool) 'false'` is true,
  so revoked members passed. Now `'true' === `. NOTE (tracked): the same `(bool)` pattern
  exists at three other read sites (≈6560, 7351, 8434) — latent identical bug, follow-up.
- `[P2] [x]` Removed the last inline `<style>` (`admin/chat-logs.php`); rules moved to the
  already-enqueued `assets/css/flosc-admin.css`.
- `[P2] [x]` Removed the dead `BACKEND INTEGRATION NOTES` pseudo-code block from
  `admin/ai-knowledge.php` (a PHP comment, never served, but cruft with an obsolete path).
- **Rejected (verified false):** AssemblyAI readme URLs — `www.assemblyai.com/terms` and
  `/privacy-policy` both return 200, so no change (the "guaranteed re-flag" was wrong).
  `register_setting` already uses a literal callable map. Default instant-login stays true
  (the replay fix secures it without sacrificing UX).
- `[ ]` **Tracked non-blocker:** `admin/ivr-messages.php` write handlers inherit
  `$flosc_ivr_dir` without re-checking `''` (only matters when uploads are unavailable,
  where the page is already non-functional).
- Commits `e6870ce`, `f181e20`. Deployed + opcache reset on all three domains; zip rebuilt.

### 2026-06m-12d-TZEEST-12h46m16s — session close

Full wp.org review-remediation session, end to end: every finding from the
June 11 automated review addressed, the house standard written, the corrected
binding-token login built, and the result deployed live and verified. Work was
split between this session (review, correction, integration, deploy) and
several lower-cost agents (first drafts of individual findings, reviewed and
corrected here before landing). Net repository state: `f8180c3` on `origin/main`,
working tree clean, local and origin identical.

- `[P1] [x]` **Deepgram speech-to-text removed end-to-end** (`0be269a`). It had
  entered the codebase pre-repo as unrequested AI scope — the owner had never
  heard of the service and no flow ever selected it (lesaep transcribes via
  AssemblyAI). Removing it also retired the dead `deepgram.com/terms` review
  finding by deleting the disclosure it described. Recorded the positioning that
  followed: FLOSC is a conversational journey facilitator, not a
  language-processing plugin; quizzing is a per-flow capability lesaep uses.
- `[P1] [x]` **Uploads-only storage chokepoint** (`3d76b58` + agent follow-ups).
  `flosc_data_dir()` returns an uploads path or '' (never the plugin folder);
  `flosc_write_data_file()` is the sole write path with realpath containment.
- `[P1] [x]` **Post-purchase login — three attempts, one correct outcome.**
  `c7c11bc` removed the public-endpoint cookie (compliant but email-only);
  `ecec35d` reintroduced an unconditional cookie (the finding, back); `8073f7c`
  added a binding token minted in the browser and never registered server-side
  (verification always failed → instant login dead). Backed both out to the
  secure base (`f251124`), then built it correctly (`f8180c3`): a server-minted,
  session-bound, single-use checkout binding token (`POST /checkout/binding`),
  verified at completion, gating `flosc_issue_post_purchase_session()`. Instant
  login is the default; `flosc_post_purchase_instant_login` (default true) is the
  documented operator switch to email-only. See the 16h30m entry below for detail.
- `[P2] [x]` **Remaining findings** (agent work, reviewed + corrected here):
  `/ivr/messages` entitlement permission (defective per-message filter removed,
  `e05a5de`); offers.php nonce+capability; flow-edit.php per-key reads; Apple
  OAuth field sanitization; literal `register_setting` callbacks; content
  protection escaped where built, relayed where core-owned (`497093c`); concierge
  styles enqueued at the printable moment.
- `[P2] [x]` **House standard + declaration.** dev_notes §5 (the ten invariants),
  readme.txt "Code standard" section, `verification_gates.sh` in worknotes.
- `[P1] [x]` **Deployed live + verified.** Incremental rsync to ChemiCloud
  (`--exclude-from='.distignore'`, no `--delete`, `.gitattributes` excluded
  inline). Post-deploy: live dir runtime-only, no `.git`, zero deepgram, binding
  code present, private `*_ivr.md` untouched. Opcache reset on all three domains
  (`reset-ok` ×3); one-shot reset file removed and 404-confirmed cache-busted.
- `[P3] [ ]` **Open, non-blocking, tracked:** several REST routes use
  `is_user_logged_in`/closures rather than named `check_*` callbacks (valid, just
  not the house pattern); offers.php top-of-file broad superglobal snapshots
  (mutations are gated; per-key conversion is the tidy-up).
- `[P3] [ ]` **Owner-side close-out (cannot be done from here):** one real PayPal
  sandbox subscription on staging to watch the instant session land; Plugin Check
  against `../flosc.zip` in Playground; finish the wp.org reply draft against this
  ledger; resubmit the zip.

### 2026-06m-12d-TZEEST-16h30m00s

Instant post-purchase login restored, made provably secure via a server-minted
checkout binding token. Supersedes two earlier same-day attempts: `ecec35d`
(unconditional cookie on payment — reintroduced the wp.org finding) and `8073f7c`
(binding token minted in the browser and never registered server-side — verification
always failed, so instant login never fired). Both backed out to the secure base in
`f251124`; this entry is the correct implementation on top of it.

- `[P1] [x]` **Three binding helpers, server-authoritative (§5b in `flosc.php`).**
  `flosc_checkout_binding_create()` mints a 43-char token SERVER-SIDE, stores only its
  HMAC in a 1-hour transient keyed to the caller's session, returns the raw token once.
  `flosc_checkout_binding_verify()` consumes it (read+delete, single-use) and confirms the
  session id matches when present. `flosc_issue_post_purchase_session()` is the lone
  post-purchase login path: filter-gated by `flosc_post_purchase_instant_login` (default
  true), uses the `flosc()` singleton (no phantom class), sets the WP + FLOSC cross-domain
  cookies, fires `wp_login`. Commit: `<this>`.

- `[P1] [x]` **Mint endpoint `POST /checkout/binding`** (`handle_checkout_binding`),
  public + rate-limited. The browser calls it as checkout begins; the token it returns is
  the proof presented at completion. This is the server-side registration step the prior
  attempt omitted.

- `[P2] [x]` **PayPal subscription activation issues an instant session on verified binding.**
  A visitor buyer with a valid binding token is logged in immediately (the original,
  desired experience); a logged-in buyer keeps their session; anyone without a valid token
  still gets access plus the emailed single-use link. Response `login_handoff` is
  `session_issued` / `already_authenticated` / `email_link_sent`. One-time capture
  (`paypal_capture_order`) requires an existing login, so it issues no new session; Stripe
  (webhook) and the sale-manager path are server-to-server and remain email-only.

- `[P2] [x]` **JS mints then presents the token.** `_mintCheckoutBinding()` calls the new
  endpoint at approval; `onApprove` sends `binding_token` + `session_id` on activation.
  Mint failure degrades to the email link, never blocks the purchase. The success branch
  already distinguishes `session_issued` (full welcome, stores `auth_token`) from
  `email_link_sent`. File: `assets/js/flosc-app.js`.

- `[P2] [x]` **Docs + gate.** Rule (f) rewritten to describe the binding model and to locate
  cookie sites by symbol rather than line number; gate 3 expects five `wp_set_auth_cookie`
  sites; a new gate asserts `flosc_checkout_binding_create` has a real caller (the defect
  that made the prior attempt non-functional).

### 2026-06m-12d-TZEEST-11h17m07s

WordPress.org plugin review hardening cycle — security and code-quality findings addressed.

- `[P1] [x]` **Deepgram STT removed end-to-end.** The integration entered the codebase
  pre-repo as unrequested provider scope; no flow ever selected it (lesaep runs on
  AssemblyAI). Removed: the dispatch branch and transcriber
  (`includes/class-stt-dispatch.php`), the per-flow provider option and key field
  (`admin/ai-configuration.php`), the setting registration (`flosc.php`), the glossary
  mention (`admin/docs/part5-glossary.php`), and the external-services disclosure with
  renumbering (`readme.txt`) — which also retires the dead deepgram.com terms-link
  finding. Commit: `0be269a`.
  Gate: `grep -RIn -i "deepgram" flosc.php readme.txt admin includes assets sample-data ai_configuration_files` → no output.

- `[P1] [x]` **Storage chokepoint — uploads-only write path.** Introduced `flosc_data_dir()`,
  `flosc_write_data_file()`, `flosc_config_file()`, `flosc_config_glob()`. All write paths
  updated across `admin/ivr-messages.php`, `admin/ai-knowledge.php`, `includes/class-ivr-parser.php`,
  `includes/class-flow-manager.php`. Commits: `3d76b58`, `90b7f5a`.
  Gate: `grep -n "FLOSC_PLUGIN_DIR . 'ai_configuration_files'" flosc.php admin/*.php includes/*.php` → read-resolver and legacy migration reads only.

- `[P1] [x]` **PayPal activation endpoint no longer issues session cookies.** Removed
  `wp_set_auth_cookie()` block from `handle_paypal_activate_subscription()`. Access is granted;
  the emailed single-use login link (via `handle_purchase_completed` → `flosc_login_token_*`
  transient) is the authentication path. JS updated to show email-link handoff message when
  `result.login_handoff === 'email_link_sent'`. Files: `flosc.php`, `assets/js/flosc-app.js`.
  Commit: `c7c11bc`.
  Gate: `grep -n "wp_set_auth_cookie" flosc.php` → four sanctioned sites only (lines 3574, 3653, 3675, 9317).

- `[P2] [x]` **offers.php mutation branches — nonce and capability gate.** `toggle_status` and
  `set_status` handlers now verify `current_user_can('manage_options')` and
  `wp_verify_nonce()` before any state change. All URL-generation sites carry nonces.
  Files: `admin/offers.php`. Commit: `76de3be`.
  Gate: `grep -n "wp_verify_nonce.*flosc_toggle_status\|wp_verify_nonce.*flosc_set_status" admin/offers.php`.

- `[P2] [x]` **flow-edit.php — per-key sanitized superglobal reads.** Broad snapshot reads
  `$get = wp_unslash($_GET)` and `$post = wp_unslash($_POST)` replaced with per-key reads at
  use sites. Files: `admin/flow-edit.php`. Commit: `36e685b`.
  Gate: `grep -cn "wp_unslash(\$_GET)" admin/flow-edit.php` → 0;
        `grep -cn "wp_unslash(\$_POST)" admin/flow-edit.php` → 0.

- `[P2] [x]` **Apple OAuth decoded JSON field sanitization.** Decoded JWT payload and `$_POST['user']`
  JSON now sanitized field-by-field immediately after decode.
  Files: `includes/sso/providers/class-apple-provider.php`. Commit: `fe621b5`.

- `[P2] [x]` **register_setting() literal sanitize callbacks.** Dynamic method-name resolution
  removed; each setting type maps to a named literal callable.
  Files: `flosc.php`. Commit: `c058e5a`.

- `[P2] [x]` **`/ivr/messages` permission is entitlement-based.** The legacy route now uses
  `check_ivr_messages_permission` (rate limit + sale/content phases gated to entitled
  members), the same callback as the newer `/ivr-messages` route. A delegated
  per-message filter was removed in review: it keyed offer visibility to a meta field
  nothing writes and substring-matched 'member' in conditions, which would have hidden
  the default visitor welcome messages; the Condition Evaluator remains the per-message
  authority. Files: `flosc.php`. Commits: `90b7f5a` (route), `e05a5de` (filter removal).

- `[P2] [x]` **Content protection — escape where FLOSC builds, relay where core owns.**
  The tier helpers (hidden/teaser/preview) end in `wp_kses_post()` after their filters;
  the public-post CTA box is escaped before appending. Pass-throughs of core post
  content return unmodified — re-filtering strips oEmbed iframes from lessons members
  are entitled to see. Files: `includes/class-content-protection.php`.
  Commits: `2bf957c` (initial), `497093c` (final design — pass-through wraps removed).

- `[P2] [x]` **Concierge metabox styles via wp_add_inline_style.** Raw `<style>` echo removed;
  the rules ride the existing `flosc-metabox` handle inside `enqueue_admin_assets()`
  (admin_enqueue_scripts) — inline data attached during metabox render arrives after the
  head styles have printed and is silently discarded. Files:
  `includes/class-flosc-concierge.php`, `flosc.php`. Commits: `d51a2f9` (initial),
  `497093c` (enqueue-time placement).

- `[P3] [x]` **Workspace baseline.** The June 11 reorg deletions (3,970 non-plugin files)
  committed so repository and working tree are identical; push-guard hook now exempts
  deletions; `.DS_Store` untracked and ignored. Commit: `e289836`.

Open items noted (not addressed in this cycle, require separate tasks):
- `[ ]` REST routes: several use `is_user_logged_in` or anonymous closures rather than named
  `check_*` callbacks. Gate 4 reports these as deviations. Scope: audit and migrate each to
  a named callback on a follow-up pass.
- `[ ]` offers.php: top-of-file broad snapshot reads (`$get = wp_unslash($_GET)`,
  `$post = wp_unslash($_POST)`) remain. Mutation branches are gated; read-only initializations
  need per-key conversion. Gate 6 reports these. Scope: Task A spec + offers.php on follow-up.

### 2026-06m-11d-TZEEST-21h00m18s
Closed an information-disclosure leak Michael R (an outside contact) spotted in the served
chat HTML, then deployed/verified across all three flows.
- `[P1] [x]` **Template doc banner no longer served to browsers.** `admin/flosc-app.php`
  carried its "MAIN CHAT APPLICATION TEMPLATE" documentation (architecture overview, the
  AI-tooling "hard-learned lesson", QA checklist) as an HTML comment sitting between
  `<!DOCTYPE html>` and `<html>`, so View-Source on every flow exposed internal dev
  commentary. Resolved at source: moved it into a non-emitted `<?php /* … */ ?>` comment —
  documentation stays in the file for maintainers, browser now gets a clean
  `<!DOCTYPE html>` → `<html>`. Had to swap three inner `*/` CSS examples to `--` so they
  don't close the PHP block comment. `php -l` clean; no rendered-output change.
- `[P1] [x]` **Deployed + verified live.** Single-file no-`--delete` rsync to ChemiCloud;
  opcache reset via one-shot curl'd `opcache_reset()` (returned `bool(true)`), helper removed.
  Verified 0 leak-lines on flosc.ai, lesaep.com, dainis.net/chat (the banner text is gone
  from served HTML on all three).
- `[P2] [x]` **Hosting topology recorded in memory.** Confirmed all three domains resolve to
  one ChemiCloud account (`51.81.55.106`) = ONE WordPress install / ONE plugin copy serving
  three per-flow front-ends. One deploy + one opcache reset covers all three.
- `[x]` **Checkpoint:** commit `44220d1` on `origin/main` ("flosc-app: stop emitting the
  template doc banner to the browser"). Cruft-deletion reorg left unstaged per §4.
- `[P3] [ ]` **Optional hardening (not required):** the remaining ~41 short structural/version
  HTML comments in `flosc-app.php` are ordinary and benign; a pass to strip ALL HTML comments
  from served output is available as a standards choice if wanted.
- Assessment note: this was information disclosure (internal notes in page source), NOT a
  code-execution vuln. Michael's "browsers will block it" was overstated; the catch itself
  was valid and is now resolved.

### 2026-06m-11d-TZEEST-15h51m34s
Session close — standards tooling stood up, deploy remediated, checkpoint committed/tagged/zipped.
- `[P2] [x]` **PHPCS + WordPress Coding Standards installed** (global composer:
  `squizlabs/php_codesniffer` 3.13.5, `wp-coding-standards/wpcs` 3.3.0,
  `phpcompatibility/phpcompatibility-wp`). Binary at `~/.composer/vendor/bin/phpcs`;
  run with `-d memory_limit=2G` (PHPCS default 128M is too low for a full run). No
  `.vscode/` or `composer.json` standards config existed in the repo before this.
- `[P3] [x]` **Full-WPCS baseline (informational only).** `--standard=WordPress` over the
  runtime PHP reported ~92.5K errors / 5K warnings across 92 files, but ~95% (92,820) are
  PHPCBF auto-fixable formatting nits (whitespace, alignment, Yoda, array spacing) — NOT
  wp.org submission blockers. The submission-relevant set (output escaping, sanitization,
  nonces, i18n, prohibited functions) is a small subset; the 5 files changed this session
  were written to that bar. The only worthwhile future run is the focused Plugin-Check
  security/correctness ruleset on the whole plugin.
- `[P1] [x]` **Deploy cruft fully remediated.** The earlier no-exclude rsync had copied
  `.git` + the working dirs (~780MB) onto live; all removed. Live plugin dir is now
  runtime-only (~4.7MB): `flosc.php`, `uninstall.php`, `readme.txt`, `admin/`,
  `ai_configuration_files/`, `assets/`, `includes/`, `sample-data/`. `error_log` (host
  php.ini `gc_divisor` startup warnings, not the plugin) cleared.
- `[x]` **Checkpoint:** commit `af7cf36` on `origin/main`; tag
  `2026-06m-11d-TZEEST-15h40m36s`; `../flosc.zip` rebuilt (1.7MB, plugin-only — private
  `*_ivr.md` flow configs and dev docs verified excluded).

### 2026-06m-11d-TZEEST-15h35m24s
Per-flow Knowledge Base wired live, plus a deploy-discipline correction after a bad rsync.
- `[P1] [x]` **Per-flow KB basket, 3-tier access.** Each floscFlow now has its own
  physically-separate basket at `uploads/flosc/ai_configuration_files/kb/<flow>/`
  (web-protected). Upload / list / edit / delete (`admin/ai-configuration.php` +
  the `handle_kb_*` handlers in `flosc.php`) operate on the active flow's folder only —
  no cross-flow content. New helper `flosc_flow_kb_dir($flow_stem)`. Access tiers are
  Visitor < Guest < Member, cumulative: visitor = pre-login, guest = logged-in,
  member = full access through Content. Both prompt builders read the same basket:
  `FLOSC_Chatpack::load_knowledge_files()` and `FLOSC_AI_Chat_Dispatch::load_orientation_files()`
  (the latter previously globbed the shared dir + a global access option — that
  cross-flow read is removed). Deployed; opcache reset.
- `[P1] [x]` **Deploy discipline — `.distignore` exclude is mandatory (see §2.6).** A
  plain `rsync` with no exclude copied the entire project root — `.git` (~300MB),
  `flosc_development_archives/` (~470MB), all working dirs, and workspace docs
  (`dev_notes.md`, `claude.md`, `tech_summary.md`, `flosc_ai_integration_plan.md`,
  `*.html` scratch, `.DS_Store`) — into the LIVE plugin folder (~780MB, git history in
  a public path). All of it removed from the server; live dir is runtime-only (4.7MB).
  Every future deploy uses `--exclude-from='.distignore'` and a post-deploy
  `ls` / `find . -name .git` check. Do not repeat this.

### 2026-06m-11d-TZEEST-14h14m22s
Concierge AI-hosted reveal, admin-join (human in the chat), flow-isolation work, and a
readable session-grouped chat log. Shipped across commits c774d3f → e2b7ef6 → f9bcd3b →
bd5f422 → b54c65a (all on origin/main); `../flosc.zip` rebuilt.
- `[P2] [x]` **Concierge = AI-hosted, consent-first reveal (no dump).** Keyword unlock opens
  a per-guest "desk" (3-day window) keyed to the guest's own session; the AI invites, then
  reveals the post's content gradually (1–3 sentences/turn) drawing on the post as an
  authoritative SOURCE — contact details quoted verbatim, never invented. Per-post
  "Delivery style" field carries tone/language (Nora: per-Sie, warm, reads-for-flirt).
  No canned-content path exists. New: open_session / active_guidance in the concierge class.
- `[P2] [x]` **Concierge is server-only.** Removed from the frontend payload
  (`get_ivr_messages` + `FLOSC_CONFIG.ivrMessages`) so the brief can't reach the browser or
  be matched/served client-side; per-visitor concierge session key (never the shared
  `user_0`); desk guidance never injected on `[SYSTEM:]` welcome generations.
- `[P2] [x]` **Flow isolation (resolved at source so far).** Re-anchor the flow IDENTITY on
  every turn in `build_followup_chatpack` (visitors carry no server memory, so follow-ups
  drifted to a generic FLOSC voice = one flow bleeding into another); scope
  `get_available_lessons()` to the flow's own lesson categories; welcome prompt made
  flow-neutral and badge-free (it was LeSAEp-flavoured and made the AI invent a badge slug).
- `[P2] [x]` **Flow isolation — knowledge base now per-flow.** Root cause: `load_knowledge_files()`
  globbed the shared `ai_configuration_files/*.md` (sample lesson catalog etc.) into EVERY
  flow as authoritative KB — so dainis.net described itself as LeSAEp. Now a flow loads ONLY
  the files in its own `knowledge_files` setting; none assigned → no KB. (RAG `search_posts()`
  was already flow-category-scoped.) Verified: dainis.net "what is this site about?" returns
  its secretary intake, no LeSAEp. NOTE: needs an admin UI to assign KB files to a flow
  (lesaep currently has none assigned → loads no .md KB; its real lessons are posts).
- `[P2] [x]` **Visitor sessions + ↻ fresh-start.** Visitors get a persistent
  `flosc_visitor_session` id (sent as `session_id`) → a stable, unique desk/log key; ↻
  Restart mints a new id = a genuinely new conversation; a returning visitor who doesn't
  restart keeps continuity (the MichaelR case).
- `[P2] [x]` **Chat Logs — session view.** Conversations grouped (click to expand), a 6-char
  conversation code in the label, per-message ids (`{code}-{u|b|a}-NNN`), the opening welcome
  shown on expand, each stored message rendered verbatim as ONE entry (no fragmenting),
  per-session Delete, visitor-right / AI-left, `[SYSTEM:]` greetings filtered.
- `[P2] [x]` **Admin join — human in the chat.** Any admin who can see a conversation posts
  into it from Chat Logs via a "Send as" selector: "{display name} (admin)" (pale-green,
  bot-side) or the bot's name (renders as a normal AI message — for live corrections). It is
  injection, not takeover — the AI keeps running. Bottom-append only (anchored/nested replies
  parked for 8.0.1). Delivery: the visitor's widget polls `/admin-messages` (POST, off the AI
  rate limit) every ~8s; admin/bot lines stored as `response_source` `admin` / `admin_bot`.
- `[P2] [x]` **Public visitor chat rate limit 30 → 60/hour** (`check_public_endpoint_permission`).
- `[P3] [x]` **Ops notes (cost the most time this session):** ChemiCloud PHP **opcache does
  NOT auto-reload** — every deploy needs a web-SAPI `opcache_reset()` (curl a one-shot file);
  `wp cache flush` only clears the object cache. And **LiteSpeed page-caches GET `wp-json`
  responses** — the admin-join poll uses POST to avoid being served a stale empty result.
- `[P3] [ ]` **8.0.1 backlog:** knowledge/RAG flow-isolation (above, priority); nested/anchored
  admin replies + group-chat; AI Feedback efficiency (carried).

### 2026-06m-10d-TZEEST-18h34m29s
Built the **Concierge** feature end-to-end, closed a cross-route gap, deployed to
dainis.net and verified live (three concierge posts gating correctly).
- `[P2] [x]` Concierge = an IVR message `type: concierge` — keyword-triggered with an
  optional password gate. Authored as a private post in the `concierge` category (by
  OpenClaw or an admin); the plugin syncs it into the flow DB↔.md. New class
  `includes/class-flosc-concierge.php`. Keyword match is approximate + case-insensitive;
  password is case-insensitive (a friendly gate, not a security boundary).
- `[P2] [x]` Flow resolution on a concierge post, in order: explicit `.md` → flow NAME
  (`Brenda`, `LeSAEp`, `flosc.ai`) → Deployment URL (`dainis.net/chat` → `dainis_net_ivr`).
  OpenClaw writes the human-facing Deployment; the plugin maps it to the flow.
- `[P2] [x]` Concierge now runs on BOTH chat routes. `/chat-rag` (the RAG handler) skipped
  the IVR entirely, so "lesson"-looking queries bypassed the gate; added the concierge
  check at the top of `handle_chat_with_rag`, with the session key derived identically so
  a gate opened on one route is honoured on the other.
- `[P2] [x]` Resolved a latent fatal: the admin "what FLOSC understands" summary referenced
  an undefined `parse_post`; it now uses `config_from_post`.
- `[P2] [x]` Content delivered = the post body after `Content to deliver:` (the read-more
  tier was removed for one clear rule).
- `[P3] [ ]` Richer content-delivery modes (serve a linked post's content inline vs. the
  link) parked for later.

### 2026-06m-10d-TZEEST-11h38m31s
Tool-verified review of the June 2 wp.org pre-review against current code (HEAD
`7546000`); stood up PHPCS 3.13.5 + WPCS 3.3.0 locally (`~/flosc-phpcs-tools`,
outside the plugin). Authoritative result: with `--ignore-annotations`, the whole
shipping codebase carries only 8 sniff-errors, not 190 problems — most of the 190
`phpcs:ignore` directives cover legitimate `DirectQuery`/`NoCaching` *warnings*
on plugin-owned tables or sniffs that never fire.
- `[P2] [x]` Resolved the one real prepared-SQL error at source: `uninstall.php`
  `DROP TABLE` now binds the table via `$wpdb->prepare('… %i', $table)`.
- `[P2] [x]` Bound table names via `%i` in `class-lesaep-lessons-table.php` (6 sites)
  and `class-flosc-chat-logger.php` (3 sites) — real identifier binding, decorative
  false-positive exemptions removed. PHPCS-clean on all DB/security sniffs; `php -l` clean.
- `[P2] [~]` Removing orphaned/doubled `phpcs:ignore` directives (stacked standalone
  comments that silence nothing). Done in `class-ai-chat-dispatch.php` (13 → 3,
  PHPCS-verified clean). Remaining files identified; backups at
  `~/flosc-edit-backups/2026-06m-10d-TZEEST-11h34m24s`.
- `[P2] [x]` Renamed `corrections` → `feedback` as ONE coherent pass (no patchwork):
  file `admin/ai-corrections.php` → `admin/ai-feedback.php` (git mv), option key
  `ai_corrections` → `ai_feedback`, REST routes `/corrections` → `/feedback` (+ JS caller),
  handlers `handle_*_correction` → `handle_*_feedback`, POST fields, PHP vars, CSS classes
  (`flosc-corrections-*`/`flosc-correction-*`/`flosc-corr-*` → `flosc-feedback-*`), chat flag
  modal, docs glossary, prompt labels. Verified: `php -l` (all), `node --check` (JS), 0
  residual `correction` tokens, routes/keys/classes consistent across PHP/JS/CSS.
  Pre-launch (one disposable live install, 0 customers, 0 wp.org installs) → no migration
  needed; dainis.net read-only check confirmed nothing was stored under the old key.
- `[P2] [x]` Deployed to dainis.net (surgical rsync, explicit 17-file list, NO `--delete`,
  so site-specific `*_ivr.md` untouched). Removed the orphaned `admin/ai-corrections.php`
  and both stale `*.bak-michael` files on the server. Verified on live: 0 residual
  `correction` tokens, 3 `/feedback` routes, `php -l` clean, object cache flushed.
  dainis.net now matches local.
- `[P3] [ ]` **AI Feedback Efficiency (8.0.1).** The feedback panel is not yet working the
  way the owner wants — barely used, needs completion + testing. 8.0.1 is now purely the
  FUNCTIONALITY (the concept rename is done in 8.0.0).
- `[P3] [ ]` 8.0.1 `SHOW COLUMNS … admin_rating` probe simplification (carried over).

### 2026-06m-09d-TZEEST-19h14m00s
Plugin Check remediation complete — FLOSC 8.0.0 passes cleanly in WordPress
Playground: all categories (General, Plugin Repo, Security, Performance,
Accessibility), both Error and Warning types, AI Analysis enabled →
"Checks complete. No errors found." Submission zip rebuilt and verified clean.
- `[P1] [x]` Cleared 6 `PluginCheck.Security.DirectDB.UnescapedDBParameter` warnings
  via the `%i` identifier placeholder in `$wpdb->prepare()` (proper escaping, not
  false-positive exemption). Files: `admin/ai-corrections.php`, `includes/class-ai-chat-dispatch.php`,
  `includes/class-flosc-chat-logger.php`.
- `[P1] [x]` Rewrote `flosc_get_logs()` to a single fully-literal prepared statement
  with pass-through guards (`( %s = '' OR col = %s )`) — removes the dynamic-WHERE
  interpolation the analyzer couldn't prove safe. Behavior-identical.
- `[P1] [x]` Removed `WordPress.DB.DirectDatabaseQuery.SchemaChange` at its source:
  the 5 rating columns (admin_rating, admin_note, rated_at, rated_by, is_protected)
  were only ever added by a post-create `ALTER TABLE` in `flosc_upgrade_table()`.
  Since 8.0.0 is the first public release there are no legacy schemas to migrate, so
  the columns now live in `CREATE TABLE` and the migration method was deleted — no
  false-positive exemption comment needed. Fresh installs get the full schema directly; existing
  tables already have the columns.
- `[P2] [x]` Commits: `36c66ba` (%i + WHERE rewrite) → `c8f85d3` (superseded, ALTER
  ignore) → `6ef1fa8` (CREATE TABLE columns; ALTER removed). All pushed to origin/main.
  Rollback tag: `2026-06m-09d-TZEEST-18h27m25s` (on `36c66ba`).
- `[P2] [x]` Rebuilt `../flosc.zip` from HEAD (`git archive --worktree-attributes
  --prefix=flosc/`): 143 entries, top-level `flosc/`, 0 badly-named files, 0
  ALTER/SchemaChange. NOTE: Plugin Check reads the INSTALLED plugin — replace the
  uploaded copy when re-checking, don't re-run against a stale install.
- `[P2] [x]` Surgical non-destructive deploy to live (explicit file list, no
  `--delete`) so `*_ivr.md` configs untouched. Live table already has all 5 columns,
  so CREATE-TABLE-only code is a no-op there.
- `[P2] [x]` Removed the temporary "Michael contact handoff" hot-patch from live
  `includes/class-flosc-rag-chat-handler.php` (deployed clean local handler over it;
  verified gone). Pre-patch `.bak-michael` baseline retained on server.
- `[P3] [ ]` 8.0.1 optional cleanup: the runtime `SHOW COLUMNS ... admin_rating`
  probes in `class-ai-chat-dispatch.php` and `ai-corrections.php` are now redundant
  (column guaranteed by CREATE TABLE); harmless and Plugin-Check-clean, simplify later.

### 2026-06m-09d-TZEEST-08h38m18s
Renamed project folder `flosc/` → `flosc_project_folder/` for clarity; created
this `dev_notes.md` as the shared file-map + git-procedure + dev log; set up and
VERIFIED the release-build mechanism.
- `[P1] [ ]` Commit the untracked plugin files (additive) so a clean release zip is
  buildable. Backup first (§2.5); human runs the commit. Leave cruft deletions unstaged.
- `[P2] [ ]` Verify the built zip passes Plugin Check + PHPCS WPCS.
- `[P2] [ ]` Security review tracked cruft for secrets (already on origin/main).
- `[P3] [ ]` Decide later whether to actually remove cruft from tracking (destructive — not required for shipping; `.gitattributes` already excludes it).
- `[P2] [x]` Created `dev_notes.md`; `claude.md` → `@dev_notes.md`.
- `[P1] [x]` `.gitattributes export-ignore` — VERIFIED `git archive` drops 28,938→cruft-excluded, plugin kept.
- `[P3] [x]` `.distignore` updated (kept for future wp-cli; inert without it).

### 2026-06m-09d (earlier — committed)
- `[P2] [x]` `298bf5e` push guardrails with allowlist enforcement.
- `[P2] [x]` `3184f6f` DB-to-IVR export includes all messages across stale phase maps.
