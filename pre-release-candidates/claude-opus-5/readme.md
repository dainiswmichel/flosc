# FLOSC candidate v95 — Claude Opus 5

Built on `c0ada21` (Codex v94.1). Plugin version stays **8.0.0**. Three changes,
nothing else. Not deployed, not uploaded, not a claim of resubmission readiness.

## What changed

### 1. Two DA1 navigation keys, and why they were missing

`catalog` and `da1_export` are now declared in `flosc_nav_param_keys()`.

v94 replaced five file-scope `wp_unslash( $_GET )` reads with
`flosc_nav_params()`, which reads a declared list. Those two keys were left off
it. `admin/da1.php:112` copies the array — `$flosc_da1_get = $flosc_get;` — so
the DA1 screen stopped being able to select a catalog from a URL, and its
**Export TSV button did nothing at all.**

Nothing failed loudly. Forty-two gates passed, `php -l` passed, WPCS passed, the
button was dead. Codex found it by reading the diff.

The v94 check that should have caught it was wrong twice over: it matched only
literal `$flosc_get['key']`, so it could not follow the alias; and its alias
detector used `[a-z_]+`, which does not match the digits in `$flosc_da1_get`.

Re-derived properly, the answer is now exact rather than lucky:

```
aliases of $flosc_get       : one pair, admin/da1.php:112-113
keys read across all aliases: 32
declared                    : 32
declared but never read     : none
```

### 2. The file-scope POST read in `admin/flow.php`

Gone. Two guarded helpers replace it:

- `flosc_flow_requested_file_action()` refuses anything that is not a POST from
  a user with `edit_others_posts`, then names an action only when **both** the
  submit button and its file field are present. That is the exact condition the
  three branches always used, so a request carrying only the button still does
  nothing rather than newly reaching `check_admin_referer()`.
- `flosc_flow_posted_file_name()` reads one field, and is called only after
  `check_admin_referer()` has run for that action.

The duplicate, import and delete branches keep their own nonce check, their
`manage_options` check and their `wp_die()` messages unchanged.

This is remediation against the reviewer, not a sniff. WPCS reported nothing
here, because the nonce checks further down satisfy it for the whole file scope.
The sniff measures scope; the reviewer reads execution order, and raised this
shape in both T7 and T13.

**Five WPCS findings are now reported rather than suppressed.** Three
`phpcs:ignore` lines were written into these helpers while drafting and removed
before commit. `WordPress.Security.NonceVerification.Missing` fires five times
and is accurate about what the code does:

- the detector must read `$_POST` to learn which button was pressed, because a
  nonce cannot be verified before knowing which action was submitted. It reads
  presence only, never a value, and writes nothing;
- the reader does read a value, but after the caller verified the nonce, and the
  sniff cannot follow that across a call.

Both are written into the file instead of being silenced. That is why the WPCS
error count is 1525 and not 1520. Plugin Check classes NonceVerification as a
warning.

### 3. A gate for the class of bug in (1)

`tests/check_nav_param_keys.php`. It resolves aliases of `$flosc_get`, collects
every literal key read through any of them, and requires each to be declared —
and reports the reverse direction too.

Verified by breaking it on purpose: removing `da1_export` from the declared list
turns it red with two failures and exit 1; restoring it returns exit 0.

## Numbers, and how to reproduce them

`phpcs.xml.dist` carries `<exclude-pattern>*/pre-release-candidates/*</exclude-pattern>`,
and this tree lives there. **A scan started inside the candidate folder matches
zero files and finishes in about 90ms.** Copy the tree out first:

```bash
cp -a flosc-by-claude-opus-5/flosc/. /tmp/scan/ && cd /tmp/scan
phpcs -d memory_limit=2G --standard=./phpcs.xml.dist --report=summary --no-colors \
  --ignore='*/tests/*,*/vendor/*' .
```

| | |
|---|---|
| `php -l` | 196 / 196 clean |
| gates | 41 PHP + 2 JS, all pass |
| WPCS, shipped code only | 1525 errors / 35 warnings, 6 fixable |
| WPCS, including `tests/` | 5999 errors / 567 warnings, 3034 fixable |
| PHPCompatibilityWP 7.4- | 0 findings |
| file-scope superglobal reads | **1** (v93 had 7, v94 had 2) |
| suppressions in the tree | 93, unchanged |

`tests/` never ships, so 1525 is the figure that means anything.

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors'
for f in tests/test_*.php tests/check_*.php; do php "$f" >/dev/null || echo "FAIL $f"; done
grep -rnE '^\$flosc_(get|post)[a-z_]* *= *(isset\( *\$_|wp_unslash\( *\$_|\$_)' --include='*.php' admin includes
```

## Artifact

```
sha256  893c57366c84ae3155b79e4353a0fd738f34a02d9404712c8fa8f5d8f299b151
files   241          starter packs 4          size 2.7M
built   ./build-dist-zip.sh
```

Verified absent from the zip: `tests/`, `admin/create-sample-data.php`,
`phpcs.xml.dist`, `agents.md`, `CLAUDE.md`, `.cursorrules`, `.distignore`,
`build-dist-zip.sh`, `WORDPRESS-ORG-RELEASE.md`. Both changed source files
checksum-match their copies inside the zip.

## Not verified

No WordPress runtime was available. The plugin was not activated, no admin
screen was rendered, and **the duplicate, import and delete actions were not
exercised against a real request.** Official Plugin Check and
`tests/verify-standards.sh` both need wp-cli and a booting WordPress; neither
was run. Nothing here should be read as a pass on them.

## Flagged, not changed

**`admin/create-sample-data.php` is excluded from the artifact** by both
`.distignore` and the hard deny list in `build-dist-zip.sh`. That rule is
inherited and was deliberately left alone this round. It is a WP-CLI
`eval-file` seeder — nothing requires or includes it, and its own header says
*"Run via: `wp eval-file`"*. The Codex v94.1 candidate removed the rule, which is
why that file sits inside its zip. Which behaviour is right is a functional
decision, not a remediation one.

**`admin/settings.php:370`** still reads `wp_unslash( $_POST )` at file scope,
and is not being converted to a declared list. `$flosc_post` is read with 251
distinct keys across the tree and ten are built at runtime, such as
`$flosc_post[ $state . '_pill_icon' ]`. An allowlist there would silently drop
fields and break saves — the same failure mode as (1), at much larger scale.
