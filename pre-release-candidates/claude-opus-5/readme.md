# FLOSC 8.0.0 — candidate v86

Built by Claude Opus 5. Renumbered onto the shared candidate counter, beside
opencode-big-pickle v83, grok-4-6 v84 and gpt-5-6-sol v85.

    php -l             194 files, 0 errors
    gates              41 of 41 green (39 PHP, 2 JS)
    phpcs WordPress    1,524 errors   30 warnings   87 files
    zip                281 entries, 0 under tests/, version 8.0.0
    sha256             5230a31606a687c4…

Every number above was measured after the last change in the tree, not before.

**Functionally unverified.** Nothing here has been run against a live site. The
standards work is measured; the behaviour is not.

## Eight runtime bugs fixed

None of these are coding-standards findings. Each one was doing something wrong
on a live site, and `php -l` and every coding-standards sniff pass over all of
them without comment.

| file | what was broken |
|---|---|
| `admin/flow-edit.php` | Quiz Type dropdown rendered every option with an empty value and an empty label |
| `admin/ai-feedback.php` | four `update_option()` calls named a variable nothing assigns — every AI feedback and praise save was written to nothing |
| `admin/ivr-messages.php:1891` | IVR file-vs-database diff table rendered the db and file columns empty |
| `admin/ivr-messages.php:1901` | the same table rendered the field-name column empty |
| `admin/settings.php` | the "All flows saved!" notice never appeared |
| `admin/documentation.php` + 3 others | 14 reads of `$selected_ivr` — every documentation deep-link built `ivr=` with nothing after it |
| `admin/ui-navigation.php:19` | the page always fell back to `array()`, so the avatar radius always took its `8px` default instead of the saved value |
| `includes/class-flosc-framework.php` | `score_visitor_audio()` read two variables assigned only in other methods, and logged three PayPal endpoint hits that never happened |

Two of them matter more than the rest for different reasons.

**`admin/flow-edit.php` was introduced by this candidate, in v82.1.** The
`$flosc_` prefixing pass renamed the loop bindings and did not rename the reads
in the loop body. It was found by opencode-big-pickle's PHPStan run, not by me.

**`admin/ui-navigation.php:19` was not introduced by the remediation.** The
canonical tree at the repository root carries the identical mismatch:
`settings.php` assigns `$flosc_flow_settings` while `ui-navigation.php` tests
`isset( $flow_settings )`. That has been live.

## The bug class, and the gate for it

Renaming a variable where it is written and not where it is read produces code
that parses, passes every sniff, and silently renders nothing. Six instances
were found this way; two more came out of the gate built for it.

`tests/check_undefined_variables.php` is token-based and scope-aware:

- Each file splits into one unit per function scope — parameter list plus body,
  with nested functions lifted out in turn — and each unit resolves against
  itself alone. An assignment in another method does not satisfy a read.
- Arrow functions are deliberately **not** split out: `fn() => …` captures the
  enclosing scope, so its body must resolve against the unit it sits in.
  Closures **are** split out, because a closure sees only what it names in
  `use()`.
- The include graph is recorded **per scope**, not per file. A template included
  from inside a method inherits that method's variables — `admin/flosc-app.php`
  is included at `class-flosc-full-page-mode.php:493` inside
  `render_flosc_app()`, which assigns all seven names first. Recording this per
  file instead produced seven false positives.

It was verified in both directions: reintroducing the `flow-edit.php` bug makes
it red at line 531, and reintroducing the framework case makes it red at 11849
and 11850. A gate that has only ever been seen to pass has not been tested.

**Known looseness, stated rather than left implicit:** WordPress globals such as
`$wpdb` are treated as always defined even inside a function that never declares
`global $wpdb`. That hides a real WordPress bug class. It is deliberate for now —
tightening it without a survey first would bury real findings under noise.

## What was taken from the other candidates

**opencode-big-pickle v83** — its PHPStan run named `flow-edit.php` and
`ai-feedback.php`. Both taken. Its remaining finding classes were not examined.

**grok-4-6 v84 and gpt-5-6-sol v85** — byte-identical trees. `diff -rq` between
them reports zero differing files, and both are this candidate at v82.18. Their
single contribution is the `score_visitor_audio()` dead-code removal, taken
verbatim.

## Standards work

`Squiz.Commenting.BlockComment` is at zero: 55 blank lines inserted before block
comment openers across 14 files, and 31 closers realigned with their own openers.

Applied by the sniff's own reported line numbers rather than by a regex over the
source. A regex for `/*` cannot tell a block comment from the same two
characters inside a string literal, and this tree has both.

No `phpcs:ignore`, `phpcs:disable` or severity flag was added anywhere in this
candidate.

## What is not done

The documentation sniffs are the bulk of the remaining 1,524. Comment formatting
is being done by hand by the maintainer, not by this candidate.

Plugin version stays **8.0.0**.
