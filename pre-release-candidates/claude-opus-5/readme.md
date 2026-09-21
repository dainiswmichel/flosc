# Home run candidate v90.2 — Claude Opus 5

Base: **v89.1** (`5f5c094`, dwm-local). v89.2 (`84f26d0`, GPT-5.6-sol) is a
byte-identical tree and an identical zip, so either is the same starting point.

Scope of this candidate: **the security errors, and nothing else.** No formatting
pass, no file moves, no version bump. Plugin version stays 8.0.0.

This candidate is **v90.2**. It landed across several commits rather than one,
and they are kept separate on purpose — each records a distinct finding, and
the point of this candidate is that its claims can be checked:

| commit | what it found |
|---|---|
| `d3d2aa7` | 13 WPCS security errors the base tree reported as 0 |
| `a7af32d` | the 2 errors WordPress.org actually blocks on, in `uninstall.php` |
| `b9665d3` | stale v86 `readme.md`/`sha256sums` shadowing the real ones on macOS |
| `77416d8` | lowercase filenames |
| `59fd42a` | `wporg-audit.sh` — every category from review rounds T7 → T13 |
| this one | the 11 file-scope superglobal reads the reviewer quoted twice |

---

## Why the base tree read as clean when it was not

v89.1 measured 0 security findings. That number was produced in part by
suppression annotations: the base tree carries **103 `phpcs:ignore` /
`phpcs:disable` directives, 72 of which name a `WordPress.Security.*` or
`WordPress.DB.*` sniff.** A suppressed finding is not a repaired one.

Every security figure below is therefore measured **with every directive in the
tree switched off**, so no annotation can flatter the result:

```
grep -rl "phpcs:ignore\|phpcs:disable\|phpcs:enable" --include="*.php" . \
  | xargs sed -i 's/phpcs:ignore/phpcsWASignore/g; s/phpcs:disable/phpcsWASdisable/g; s/phpcs:enable/phpcsWASenable/g'
```

Measured that way, the base tree had **12 errors and 145 warnings**, not 0.

---

## Measured results

PHPCS 3.13.6 + WPCS 3.4.0, both trees, identical rulesets and identical method.

*(v90.1 figures; the v90.2 table is at the end of this file.)*

| | v89.1 base | v90.1 |
|---|---|---|
| Security errors, suppressions switched off | **12** | **0** |
| Security warnings, suppressions switched off | 145 | 139 |
| WPCS total, project ruleset | 7094 errors / 511 warnings | 7094 errors / 511 warnings |
| phpcbf fixable | 960 | 960 |
| Suppression directives in tree | 103 | 89 |
| `php -l` | 139/139 clean | 139/139 clean |
| **Plugin Check ruleset, on the shipped zip** | **2 errors / 69 warnings** | **0 errors / 69 warnings** |

That last row is the one WordPress.org actually enforces. It is the official
ruleset from `WordPress/plugin-check`
(`phpcs-rulesets/plugin-check.ruleset.xml`), run against the contents of the
built zip rather than the source tree.

Worth knowing how different it is from WPCS: Plugin Check downgrades
`NonceVerification` and `ValidatedSanitizedInput` to **warnings**, and promotes
`WordPress.WP.AlternativeFunctions` to **error**. So the thirteen WPCS errors
repaired above were never the blocking ones — and the two that *were* blocking
sat in `uninstall.php` behind annotations, in every candidate, unnoticed.

The style totals being identical is the point: the 13 error sites were repaired
without adding a single new style violation.

Fourteen suppressions were removed and **none were added**.

### Reproducing the security number

```bash
php phpcs.phar -d memory_limit=2G --standard=<ruleset> --report=summary .
```

against a copy of the tree with the directives switched off as above. The ruleset is
`WordPress.Security.{NonceVerification,ValidatedSanitizedInput,EscapeOutput,
SafeRedirect,PluginMenuSlug}`, `WordPress.DB.{PreparedSQL,DirectDatabaseQuery}`,
`WordPress.WP.GlobalVariablesOverride`, `WordPress.PHP.NoSilencedErrors` — the
security set `AGENTS.md` §3 names — plus the `customSanitizingFunctions`
property described below.

---

## The thirteen errors, and what was done about each

**1. `includes/email/class-flosc-email.php` — `save_newsletter_profile_field()` (2 errors)**

Hooked to `personal_options_update` / `edit_user_profile_update`. Core verifies
`update-user_<id>` before those fire, which is what the old annotation said, and
it was correct. It was also an argument about a caller, living in a function that
acts on `$_POST` itself. The function now verifies the nonce, so the save is
refused if this hook is ever reached by a path that did not.

*Behavioural note:* code that fires `personal_options_update` programmatically
without a nonce will no longer save the newsletter flag. That is the intended
posture and matches what core does at the same point.

**2. `includes/flosc-personality-library.php` — `persona`, `persona_delete` (2 errors)**

The loop sanitized `id` and `label` and ignored every other leaf. Both arrays are
now sanitized at the read with `map_deep( …, 'sanitize_text_field' )`, so the
whole submitted structure is clean, not just the two fields that get used.

**3. `includes/flosc-personality-library.php` — `ai_base_prompt`, `workshop_json` (2 errors)**

These were already sanitized, by two project functions the sniff does not know:

- `flosc_sanitize_personality_profile_text` — rejects invalid UTF-8, strips C0
  control characters, caps length. Keeps Markdown deliberately; the value is an
  AI prompt, and it is escaped at output (`esc_html`/`esc_attr`, and
  `wp_json_encode` with `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`
  for the builder payload — verified, not assumed).
- `flosc_sanitize_personality_workshop` — rejects invalid UTF-8, strips control
  characters, caps length, requires the payload to decode to a non-empty
  associative array, and returns only `wp_json_encode()` of that decoded
  structure. Anything else returns `''`.

They are declared in `phpcs.xml.dist` under `customSanitizingFunctions`. That is
the documented WPCS mechanism for naming a project's own sanitizers, and it is
the opposite of a suppression: it points the sniff at two functions a reviewer
can open and check, instead of scattering claims across call sites.

`workshop_json` also needed restructuring — the sniff only credits a sanitizer
that wraps the read directly, and the old code assigned to an intermediate first.

**4. `includes/magic-link/class-flosc-magic-link-trait.php` — `build_guest_request_admin_redirect()` (3 errors)**

A private helper that reached into `$_POST['ivr']` / `$_GET['ivr']` on its own.
The old annotation was an assertion about all callers, unenforceable from inside
the helper. The helper now takes the flow file as an argument and reads no
superglobal; all **12** call sites pass the value their own handler already read
from the payload it had verified. Nothing to assert — the shape of the code
carries it.

**5. `includes/flosc-admin.php` — `maybe_process_flosc_settings_post()` (4 errors)**

A dispatcher that read all of `$_POST` and routed on button names, relying on
each downstream handler to verify its own nonce. They do. But an unverified POST
was still routed before anything checked it.

It now verifies, before routing, the same nonce the chosen branch's form emitted:

| button | nonce action | field |
|---|---|---|
| `flosc_upload_ivr_file` | `flosc_portability_kit` | `_wpnonce` |
| `flosc_portability_submit` | `flosc_portability_kit` | `_wpnonce` |
| `flosc_portability_pack_action` | `flosc_portability_pack` | `_wpnonce` |
| `flosc_save` | `flosc_save_settings` | `_wpnonce` |
| `flosc_toggle_trajectory_post` | `flosc_toggle_trajectory_post` | `flosc_toggle_trajectory_nonce` |
| `flosc_create_concierge_post` | `flosc_create_concierge_post` | `flosc_concierge_create_nonce` |
| `flosc_create_trajectory_post` | `flosc_create_trajectory_post` | `flosc_trajectory_create_nonce` |

A branch is taken only when its nonce verifies **and** its button is present, so
list order does not matter and a mismatched pairing routes nothing. The
downstream checks all remain — this is a second gate, not a replacement.

The function is also gated on `current_user_can( 'edit_others_posts' )`. That is
the capability `add_menu_page()` registers for the FLOSC menu — **not**
`manage_options`, which would have locked Editors out of a screen they are
entitled to.

*Behavioural note:* a POST whose nonce has expired now returns before the include
instead of being routed and silently refused inside `admin/settings.php`. Net
effect for the user is unchanged — nothing saved, no notice — because the
downstream checks were already silent on failure.

---

**6. `uninstall.php` — the two actual WordPress.org blockers**

Found by running the official Plugin Check ruleset rather than WPCS. Two raw
filesystem calls, both masked by annotations, both **errors** under the ruleset
the directory enforces:

| line | call | sniff |
|---|---|---|
| 157 | `@unlink( $path )` | `WordPress.WP.AlternativeFunctions.unlink_unlink` |
| 161 | `@rmdir( $dir )` | `AlternativeFunctions.file_system_operations_rmdir` |

`flosc_uninstall_rm_rf()` now works entirely through `WP_Filesystem` and
`wp_delete_file()`. The recursive `rmdir( $dir, true )` runs first; if it fails,
the fallback walks the tree with `dirlist()` instead of `scandir()`, removes
files with `wp_delete_file()` and directories with `$wp_filesystem->rmdir()`.
No `unlink`, no `rmdir`, no `scandir`, no `@`. Three suppressions removed.

If `WP_Filesystem` is unavailable the function now returns without deleting,
rather than reaching for raw calls. Options, user meta, post meta and the custom
tables are already gone at that point; an upload directory surviving on a host
with no filesystem API is the correct trade against an error that blocks review.

---

## What is NOT verified

- **No WordPress runtime was available.** Activation, the admin screens, and the
  FLOSC journeys are untested. Every claim above is static.
- **Plugin Check's PHPCS half has been run** against the shipped zip (0 errors). Its runtime checks — activation, enqueue behaviour, readme and header validation — need a booting WordPress and have NOT been run.
- **PHPCompatibilityWP was not installed**, so `testVersion 7.4-` did not run
  here. The project ruleset totals above exclude it and are therefore not the
  number the release gate prints.
- The **139 remaining warnings** and the **89 remaining suppressions** were not
  touched. Most are `DirectDatabaseQuery.NoCaching` and
  `NonceVerification.Recommended` on read-only admin routing. They are the next
  piece of work, not a claim of cleanliness.

**Readiness claim: none.** This candidate is a static-checks-only improvement
over v89.1. It needs a clean-install run under `WP_DEBUG` and Plugin Check before
anyone calls it submittable.

---

## Artifact

```
flosc.zip
  sha256  92f0f3a72db4fdba7393bee4d5db6e8fb40a24ad09a4e1ef5897f95a1b1914e4
  bytes   2130303
  entries 240
  root    flosc/
```

Built with `build-dist-zip.sh` (`FLOSC_ZIP_OUT_DIR` set to this folder). The
script's deny list and its post-build zip inspection both passed; a separate
check for `tests/`, `vendor/`, `composer.*`, `.git*`, `phpcs.xml*`, `CLAUDE.md`,
`AGENTS.md`, `.cursorrules`, worknotes, `create-sample-data.php`,
`push-allowlist.txt` and `build-dist-zip.sh` inside the archive returned nothing.


---

## v90.2 — the file-scope superglobal reads

The reviewer quoted `$flosc_get = wp_unslash($_GET);` in **T7 (11 Jun)** and
again in **T13 (14 Sep)**, three months apart, and objected on two grounds:
CSRF, and performance — *"Don't check for post submission outside of functions.
Doing so means that the check will run on every single load of the plugin."*

Eleven such reads are gone, across `admin/settings.php`, `admin/flow.php`,
`admin/flows.php`, `admin/offers.php`, `admin/ivr-messages.php` and
`admin/ai-configuration.php`.

**Query values** now come from `FLOSC_Request_Guard::query_params()`, which
reads only the keys the admin screens actually use and sanitizes each one on
the read. The list of keys was derived mechanically, not by hand, and is
verified to cover every literal key used anywhere in `admin/` — **36 used, 36
declared, none missing**. It lives in one method, so a reviewer checks one list
instead of eleven scattered reads.

**POST** comes from `FLOSC_Request_Guard::admin_post_payload()`, which returns
an empty array unless the request really is a POST from a user holding
`edit_others_posts`. An ordinary page view now does no POST work at all, which
is the performance objection answered directly.

### Why the WPCS error count went 0 → 1

`admin_post_payload()` reports `NonceVerification.Missing`. It is reported here
rather than suppressed.

The settings screen reads 146 POST fields, iterates the entire payload three
times (`settings.php:759, 2354, 2406`) and builds 24 keys dynamically. An
allowlist is not possible there without rewriting the save logic, which is not
a safe change to make in the same pass as everything else. Every branch that
writes still verifies its own nonce.

The count moved from 0 to 1 **because the code got better, not worse.** Before,
the read sat at file scope, where WPCS treats the whole file as one scope — a
`check_admin_referer()` at `ivr-messages.php:593` silenced a read at line 114.
That is the exact quirk that let a locally clean tree keep coming back flagged:
WPCS looks at scope, the reviewer's analysis looks at execution order, and at
line 114 nothing had been verified yet. Moving the read into a function put it
somewhere WPCS genuinely checks, and it immediately said so.

Plugin Check — the ruleset WordPress.org actually enforces — treats
`NonceVerification` as a warning and reports **0 errors** on the shipped zip.

### Measured

| | v89.1 base | v90.1 | v90.2 |
|---|---|---|---|
| Plugin Check ruleset, shipped zip | 2 errors / 69 warnings | 0 / 69 | **0 / 72** |
| WPCS security set, suppressions off | 12 errors / 145 warnings | 0 / 139 | **1 / 142** |
| Suppression directives | 103 | 89 | **87** |
| File-scope superglobal reads | 11 | 11 | **0** |
| `php -l` | 139/139 | 139/139 | **139/139** |

`wporg-audit.sh` — every category raised across rounds T7 through T13:
**17 PASS, 0 FAIL, 5 READ.**

The five READs are not code changes. They are the 16 user-login sites (a design
decision needing a written justification), the `the_content`/shortcode return
escaping, one `json_decode(stripslashes())` in `class-clickbank-provider.php`,
the HMAC-signed `ajax_serve_user_audio` endpoint, and confirming the external
host list against the 16 readme entries.
