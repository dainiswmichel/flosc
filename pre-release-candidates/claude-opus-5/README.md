# Home run candidate claude-code-v200

Review ID addressed: **R flosc/dainismichel/2Jun26/T15 24Oct26/4.3** (P0TDX321123HGN)
Base: canonical v190 (`4ae041a`)
Zip SHA-256: `bddc26d6f3e0a792753aefbfee5abd134434f20cb0ce1b8b9127fa1d7cae790b`
233 files · 144 PHP · one top-level `flosc/` directory

## Headline for human review

The 24 October email lists five issue classes. **Two of them describe code that no longer
exists in the canonical tree, and one asks for documentation that is already present.** That
is not a complaint about the reviewer — it means the reviewed artifact was older than the
tree, and it changes what should be said in the reply. The detail is below, with dates.

| # | Class in the email | Disposition in this candidate |
|---|---|---|
| 1 | Undocumented 3rd party / external service (43 cited) | **Fixed in code.** 50 citation URLs removed from the shipped plugin. |
| 2 | Creating / login users (16 cited) | **Already documented** at `readme.txt:196`. No change needed. |
| 3 | Other possible issues — TSV formula injection (1 cited) | **Fixed in code.** Formula prefixes neutralised. |
| 4 | Data must be sanitized/escaped/validated (47 + 6 cited) | **No code change, deliberately.** Measured clean; the change I tried made it worse. See below. |
| 5 | Variables must be escaped when echo'd (30 cited) | **Already fixed on 2026-10-18**, five days before the submission. |

## 1 — External service citations. Fixed.

`assets/js/flosc-personality-builder.js` carried 50 URLs in 30 `links:` arrays across the
92 personality archetypes — Wikipedia, Project Gutenberg, Bible Gateway, Stanford
Encyclopedia of Philosophy, the Rudolf Steiner Archive, BDK America, Nobel Prize, the
Viktor Frankl Institute and the Sumerian text corpus.

These were never services. FLOSC makes no request to any of them: no `wp_remote_*`, no
`fetch`, no embed. They were bibliography sitting in code, and a URL in plugin code reads
to a reviewer as a dependency.

Per Dainis's instruction they were **moved, not deleted**. Each archetype's citation now
sits with its character description in `docs/personality-archetype-sources.md` — 440 lines,
all 50 citations, all 30 archetypes, every character explanation preserved. `docs/` is
added to `.distignore`, so the reference material lives in the repository and not in the
distributed plugin.

The archetypes keep their `works` lists — the titles of the source texts — so an author
still sees what an archetype draws on inside the builder.

Verified: `grep` for all nine citation hosts across the unpacked zip returns **zero files**.

Two consequential details handled with it:

- `readme.txt` previously listed those nine hosts and stated they were references. That
  sentence is what the reviewer's scanner objected to ("Domain(s) mentioned in the readme
  file. Links to service terms and/or privacy policy not found"). With the URLs gone from
  the code, the list is gone from the readme too — so there is nothing left to disclose.
- The "Include reference URLs" checkbox governed those arrays and now governed nothing. Its
  markup is removed from `assets/personality-builder/flosc-personality-builder-markup.php`.
  Its change listener in the builder used `getElementById(...).addEventListener(...)` with
  **no null guard**, so removing the control without removing the listener would have thrown
  on boot and taken the whole builder down. Both are removed together. The one remaining
  reference, at builder line 4780, is already null-guarded and left alone.

## 2 — Creating / login users. Already documented.

The email asks for an explanation of why these calls are technically necessary.
`readme.txt:196` already gives it: why a subscriber account is required (binding purchased
access, membership state, quiz results and protected-content permissions to the customer
across sessions), the three journeys that create one (pending email registration, validated
SSO profile, verified PayPal or ClickBank purchase), the role assigned (subscriber, or
another nonprivileged role an administrator configured), and the explicit negatives —
FLOSC does not create an administrator account, a MagicLink never creates an account, and a
server-to-server payment notification does not log a browser in.

No code change. Worth pointing the reviewer at the line in the reply.

## 3 — TSV formula injection. Fixed.

`includes/class-flosc-framework.php`, the chat-log TSV export. The encoder escaped tabs,
quotes and newlines but not spreadsheet formula prefixes, so a visitor could type
`=HYPERLINK(...)` or the DDE form `-2+3+cmd|'/C calc'!A0` into a chat and have it execute
when an administrator opened the exported log.

A value that is not numeric and begins with `=`, `+`, `-`, `@`, tab or carriage return now
gets a leading apostrophe, which makes the cell literal. The `is_numeric` test is there on
purpose: an export is evidence, and `-5` should still read as the number minus five rather
than as text.

Measured:

```
'=1+1'                    -> '=1+1
'+cmd|calc'               -> '+cmd|calc
"-2+3+cmd|'/C calc'!A0"   -> '-2+3+cmd|'/C calc'!A0
'@SUM(A1)'                -> '@SUM(A1)
'-5'                      -> -5          (still a number)
'-5.25'                   -> -5.25       (still a number)
'hello'                   -> hello
'=HYPERLINK("http://x")'  -> "'=HYPERLINK(""http://x"")"
```

## 4 — Sanitize / escape / validate. No code change, and why.

The email's six `filter_var` / `filter_input` examples all use `FILTER_CALLBACK` with a
named sanitising callback. The email's stated rule is about leaving the filter parameter
empty, where PHP applies `FILTER_DEFAULT` and nothing is sanitised. These calls do not do
that.

I converted all six to call the sanitiser directly, which is unambiguous to a human reader.
Then I ran the gate: **that change introduced 6 PHPCS errors** where v190 had none, because
WPCS recognises `filter_var(..., FILTER_CALLBACK, ...)` as a sanitising boundary but does
not credit a project's own function unless the ruleset declares it — and the bare
`WordPress` standard, which is closer to what the reviewer's tooling applies, does not read
`phpcs.xml.dist`.

So the conversion traded a scanner's misreading for six real standard violations. **It is
reverted.** All six sites are byte-identical to v190.

The 47 `wp_unslash( $_POST )` examples are whole-array reads, each followed immediately by
`flosc_submitted_fields_are_text()` — the email quotes that guard line itself in four of
its own examples. Values are sanitised individually downstream. The artifact measures
0 errors under `WordPress.Security.ValidatedSanitizedInput` with annotations disabled.

This class needs a reply to the reviewer, not a patch. Changing verified, standard-clean
code to satisfy a false positive is how a regression gets shipped.

## 5 — Escaped when echo'd. Already fixed, five days before submission.

Every example in this section is a `phpcs:ignore WordPress.Security.EscapeOutput` line on a
`flosc_vgm_options_markup()` echo. Those were replaced with
`wp_kses( flosc_vgm_options_markup( ... ), flosc_vgm_option_allowed_html() )` in **v160,
committed 2026-10-18T16:11:17+03:00**. Every revision from v160 to the current tip carries
the `wp_kses` form; the shipped tree contains **zero** `phpcs:ignore` on `EscapeOutput`.

The email's other examples in this section are `flosc_capture_output()` blocks. That helper
is a balanced try/finally buffer whose captured string is passed to
**`wp_add_inline_script()`** — the mechanism the October 7 review asked for — with
`esc_js()` on every interpolated value. No raw `<script>` tag is emitted.

The cited line numbers match the frozen lane copies under `pre-release-candidates/`, not the
canonical tree. Worth saying in the reply, politely, so the next review looks at the right
artifact.

## Verification

On the unpacked zip, 144 shipped PHP files, count asserted on every run:

| gate | result |
|---|---|
| `php -l` × 144 | 0 failures |
| Full `WordPress` standard, errors, annotations respected | **0 errors** |
| Full `WordPress` standard, errors, `--ignore-annotations` | **0 errors** |
| `ValidatedSanitizedInput` + `EscapeOutput`, `--ignore-annotations` | **0 errors** |
| `PHPCompatibilityWP`, `testVersion 7.4-` | **0 findings** |
| `node --check` on the edited builder JS | passes |
| Citation hosts anywhere in the zip | **0 files** |

PHPCS 3.13.2 · WPCS 3.1.0 · PHPCSExtra 1.2.1 · PHPCompatibilityWP 2.1.6.

**Not run, not claimed:** official Plugin Check, and the clean-WordPress journeys with
`WP_DEBUG`. Those need a booting install. In particular the personality builder should be
opened once in a browser to confirm the removed checkbox left the chip row intact — the
markup and listener were removed together and the JS parses, but layout is a visual check.
