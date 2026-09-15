# FLOSC 8.0.0 — candidate v69

    artifact   flosc.zip
    sha256     cd4192b463b80a931a746772d9f4f067da487ac0cfda744d4b25afd7b51565ba
    entries    277, single flosc/ root
    base       claude-opus-5 v67

## The thing that should have happened first

PHP_CodeSniffer with `wp-coding-standards/wpcs` installs in this container from
packagist. It took one command. Nobody ran it in three months, me included, and
"WordPress.org compliant" was asserted by agents instead of measured by the tool
that measures it.

**PHPCS, WordPress standard, whole tree, errors only:**

    EscapeOutput                       0
    ValidatedSanitizedInput            0
    NonceVerification                  0
    SafeRedirect                       0
    PreparedSQL                        0
    DirectDatabaseQuery                0

    phpcs exit 0, no output

84 `Nonce verification recommended` **warnings** remain, all on `$_GET` reads
that render admin screens without changing state. Named here rather than
quietly suppressed.

## Formatting is NOT done, and that is deliberate

`WordPress-Extra` + `WordPress-Docs` report **153,723 errors and 7,576
warnings across 138 files**. 71,466 are space indent instead of tabs; 35,362
are function-call spacing. `phpcbf` fixes 154,329 of them automatically.

I ran that pass and reverted it. Behaviour was provably unchanged — the
whitespace-stripped fingerprint of every PHP file was byte-identical before and
after — but it broke **nine** gates that assert on source text, including the
method, log and request-protection contracts.

WordPress.org rejected this plugin four times and never once mentioned
formatting. All 153,723 findings were present in every rejected submission.
The pass buys nothing toward approval and cost nine working contracts, so it
belongs in its own change after resubmission, with the gates updated alongside.

## The T12 findings

| finding | state |
|---|---|
| `Requires at least: 7.0.4` | **fixed** — `7.0` in both files |
| `WP_PLUGIN_DIR` importer path | **fixed** — load removed, not rewritten |
| `import.php` core include | **fixed** — removed |
| 38 `filter_input` sites | **fixed** — sanitized at the read |
| inline `<script>`/`<style>` | never real — my rule was reading comments as code |
| SSO redirect-host allowlist | **traced, clean** — see below |

`check_packaging.php` asserted `7.0.4` as **correct** for three rounds while the
suite printed `0 failing gates`. It now checks the shape — major.minor, no patch
digit — and carries no version literal at all.

**The SSO item, traced by hand because no pattern matcher reaches it:**
`get_app_url()` returns only options-derived values; its single `$_SERVER` read
is `HTTP_X_FORWARDED_PROTO`, which picks the scheme, never the host.
`resolve_app_url_from_flow_id()` is `get_option()` only.
`get_current_request_base_url()` does read `HTTP_HOST` but has exactly two
callers and neither is in the allowlist path. The reviewer's flagged line,
`:603 $add( home_url( '/' ) )`, is a false positive.

## Defects I introduced in this candidate and caught before shipping

**`flosc-app.php:106`** tested `null !== filter_input(...)`. A converted read
always returns a string, so `null !== ''` is always true — **every page would
have rendered as a companion embed.** Now `isset()`.

**31 sites would have unslashed twice**, once at the read and once downstream.
Two passes corrupt any value carrying a backslash. Reconciled: unslash at the
read, redundant downstream call removed, zero double-unslash sites confirmed by
script across the tree.

**Four false-positive classes in my own rules gate** — class files used via
`new`, grouped core includes, comments naming a constant, comments containing a
tag. All fixed. Counts from that gate were not trustworthy until now and should
not have been quoted as if they were.

## The gate tells you what it is

    WPORG-01,02,03,04,06,07,09   blocking, all clear, exit code counts these
    WPORG-05, WPORG-08           advisory, 69 findings, print but never block

PHPCS `ValidatedSanitizedInput` is the authority on sanitization and reports 0,
so the two hand-rolled rules that approximate it are advisory. WPORG-05 is now
titled for what it actually tests — *a superglobal read with no sanitizer on
that line* — which is **not** the standard, so its count cannot be mistaken for
a count of defects. Three reviewed exceptions live in
`tests/wporg-rule-exceptions.txt`, each with a written reason; an entry without
one counts as a finding.

## Verification

    php gates            0 failing
    js gates             clean
    php -l, whole tree   clean
    phpcs security       exit 0, no output
    zip                  277 entries, single flosc/ root
    version              8.0.0 in header, FLOSC_VERSION and Stable tag

**Unverified, and it needs one look from you:** whether `Tested up to: 7.1` is
the current WordPress release. `api.wordpress.org` is 403 through this
container's proxy. One line off https://wordpress.org/download/ — it must not
be churned on.

**Not claimed:** that this passes review. Plugin Check needs a WordPress
install, and WordPress.org also runs an AI pass that reads intent across call
paths, which no tool here reaches.
