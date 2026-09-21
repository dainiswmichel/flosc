# Home run candidate v90 — Claude Opus 5

Base: **v89.1** (`5f5c094`, dwm-local). v89.2 (`84f26d0`, GPT-5.6-sol) is a
byte-identical tree and an identical zip, so either is the same starting point.

Scope of this candidate: **the security errors, and nothing else.** No formatting
pass, no file moves, no version bump. Plugin version stays 8.0.0.

---

## Why the base tree read as clean when it was not

v89.1 measured 0 security findings. That number was produced in part by
suppression annotations: the base tree carries **103 `phpcs:ignore` /
`phpcs:disable` directives, 72 of which name a `WordPress.Security.*` or
`WordPress.DB.*` sniff.** A suppressed finding is not a repaired one.

Every security figure below is therefore measured **with every directive in the
tree neutered**, so no annotation can flatter the result:

```
grep -rl "phpcs:ignore\|phpcs:disable\|phpcs:enable" --include="*.php" . \
  | xargs sed -i 's/phpcs:ignore/phpcsWASignore/g; s/phpcs:disable/phpcsWASdisable/g; s/phpcs:enable/phpcsWASenable/g'
```

Measured that way, the base tree had **12 errors and 145 warnings**, not 0.

---

## Measured results

PHPCS 3.13.6 + WPCS 3.4.0, both trees, identical rulesets and identical method.

| | v89.1 base | v90 |
|---|---|---|
| Security errors, suppressions neutered | **12** | **0** |
| Security warnings, suppressions neutered | 145 | 139 |
| WPCS total, project ruleset | 7094 errors / 511 warnings | 7094 errors / 511 warnings |
| phpcbf fixable | 960 | 960 |
| Suppression directives in tree | 103 | 92 |
| `php -l` | 139/139 clean | 139/139 clean |

The style totals being identical is the point: the 13 error sites were repaired
without adding a single new style violation.

Eleven suppressions were removed and **none were added**.

### Reproducing the security number

```bash
php phpcs.phar -d memory_limit=2G --standard=<ruleset> --report=summary .
```

against a copy of the tree with the directives neutered as above. The ruleset is
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

## What is NOT verified

- **No WordPress runtime was available.** Activation, the admin screens, and the
  FLOSC journeys are untested. Every claim above is static.
- **Official Plugin Check has not been run** against this zip.
- **PHPCompatibilityWP was not installed**, so `testVersion 7.4-` did not run
  here. The project ruleset totals above exclude it and are therefore not the
  number the release gate prints.
- The **139 remaining warnings** and the **92 remaining suppressions** were not
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
  sha256  66e769ea8a0ea5e53e6b64db57151dc760be437ff9e7dd23351122e03bca0993
  bytes   2128593
  entries 240
  root    flosc/
```

Built with `build-dist-zip.sh` (`FLOSC_ZIP_OUT_DIR` set to this folder). The
script's deny list and its post-build zip inspection both passed; a separate
check for `tests/`, `vendor/`, `composer.*`, `.git*`, `phpcs.xml*`, `CLAUDE.md`,
`AGENTS.md`, `.cursorrules`, worknotes, `create-sample-data.php`,
`push-allowlist.txt` and `build-dist-zip.sh` inside the archive returned nothing.
