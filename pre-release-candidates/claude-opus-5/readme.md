# FLOSC 8.0.0 — candidate v82.1

**WordPress Coding Standards applied to the shipping PHP.**

    full WordPress standard, shipping PHP
    before   153,957 errors   7,533 warnings   139 files
    after      8,511 errors     174 warnings   136 files

    php -l        0 errors / 187 files
    CLI gates     37 of 37 pass
    WPORG gate    exit 0
    zip           278 entries, 0 under tests/, version 8.0.0
    sha256        e945c8708f5a798f…

JavaScript and CSS were left alone: WPCS's JS tokenizer mishandles modern
syntax, and roughly 69,000 of the original 222,714 came from `.js` and `.css`,
which WordPress.org does not reject on.

## What the fixer could not do by itself

`phpcbf` refused to write `flosc.php` (577KB) and `class-paypal-provider.php`.
It reports *FAILED TO FIX* and discards its work when conflicting fixers exhaust
its internal loop limit, so those two files came back byte-identical, holding
31,357 of the remainder. Applying one sniff group at a time — indent, then
superfluous whitespace, then short array syntax, then call signatures, and so on
— converges, because a single fixer has nothing to fight with. 31,357 → 3,199,
with `php -l` between every step.

## What the gates caught

The reformat broke 11 of the 37 gates. Nine were the gates' own fault: they
asserted formatting. One needle required a run of exactly twenty-one alignment
spaces; another searched for a newline followed by eight spaces. Those now strip
whitespace from both sides of the comparison, or accept either array syntax —
same assertion, formatting no longer decides it. Each was verified by breaking
the thing under test and confirming the gate still goes red.

**Two were real.**

`class-stt-dispatch.php` and `class-ai-chat-dispatch.php` each carried a
`phpcs:ignore` for `PluginCheck.CodeAnalysis.AIProvider.DirectIntegration`
directly above a `wp_remote_post()` whose endpoint URL sat on the same line.
The fixer split that call across lines, leaving the suppression above
`wp_remote_post(` and the host it annotates one line further down, out of its
reach. The endpoint is now named on its own line under the annotation, which
survives any future reformat.

`tests/wporg-rule-exceptions.txt` pins its reviewed exceptions by line number.
The WPORG-02 exception for `flosc-data-paths.php` moved from line 382 to 383 and
stopped applying, so your rules gate went red on code that had not changed.

## Not done

    auto-fixable, still stuck on convergence   ~1,759
    Yoda conditions                             2,382
    inline comment end punctuation              1,690
    missing docblock @param tags                  797
    missing function docblocks                    467
    short ternaries                               172

**0 of 30 behavioural tests executed.** No WordPress in this container. This pass
changed formatting and nothing else on purpose — but that is a statement about
intent, not a measurement.

## What v78 got right, and keeps

The settings and IVR dispatchers verify the nonce for the matched route before
the POST body is read. `ajax_serve_user_audio` calls the HMAC verifier that
existed and was never invoked. `handle_admin_activate_email_account` no longer
builds its nonce action from an unvalidated id.
`save_newsletter_profile_field` verifies `update-user_{id}` itself. Those are
controls, and they stay.

## Measured in this container

    php tests/check_wporg_rules.php        0 findings, exit 0   (was 14, exit 1)
    every CLI gate                         37 of 37 pass
    php -l, whole tree                     0 errors / 187 files
    FILTER_UNSAFE_RAW in shipping code     0
    NonceVerification suppressions         0
    zip                                    278 entries, 0 under tests/, 8.0.0

There is no phpcs, phpstan, wp-env or Plugin Check in this container, and no
WordPress. 0 of 30 behavioral tests have been executed.

## What v80 did

**R-3, introduced by v78.** `admin/ivr-messages.php` replaced
`$flosc_get = wp_unslash( $_GET )` with an array declaring all ten nav keys
unconditionally, each with a default. `$_GET` only ever contains the keys the
request carried; that array always contained all ten. So
`isset( $flosc_get['delete_message'] )` was permanently true, the delete branch
ran on every render, and `check_admin_referer( 'flosc_delete_message_' )` with an
empty id and no nonce in the URL ended the request. The IVR Messages tab
`wp_die()`d on every load. `?? 'freeline'` also stopped falling back, because
`??` tests for null and the declared default was `''`. The array is now built
with `flosc_nav_param_present()`, so a key exists only if the request carried
it, and every value that is present still passes its allowlist.

**R-4, introduced by v78.** A 2048-character cap on the OAuth
`code`/`state`/`error`/`error_description` and an 8192 cap on Apple's `user`
field. Neither was required by the nonce work; both could only reject a login.
Removed. The Apple one was also redundant against the 20000 cap already sitting
at the `json_decode` below it.

## What v79 did

v78 was advanced on a phpcs PASS alone. That was the wrong standard, and it hid
two real defects of the same class -- a read narrowed for the scanner, with a
consumer downstream that depended on the breadth.

**R-1, introduced by v78.** `admin/ai-configuration.php` changed `$flosc_get`'s
fallback from `wp_unslash( $_GET )` to `array()`. Nothing in the plugin ever
writes `$GLOBALS['flosc_get']`, so the fallback is always taken and the reads of
`site_index_action` and `site_index_error` went permanently empty.
`redirect_ai()` carries the outcome in the query string on all ten of its paths;
`set_transient()` runs on exactly one. Nine of ten Site Index outcomes stopped
showing any notice at all -- the action ran, the write landed, the screen said
nothing. Both values now read through `flosc_nav_param()`.

**R-2, pre-existing.** `admin/knowledge-base.php` read `view`, `kb_edit` and
`kb_id` from the same never-written global, so the edit-a-knowledge-base route
was dead in every candidate commit in this repository. Same fix.

Neither fix restores a bulk superglobal read and neither adds a suppression.

## The standard v78 failed

A static-analysis PASS is not a completed repair if the repair changes
executable behavior and that behavior has not been regression-tested. The nonce
work can be called `WPCS NonceVerification: PASS`. It cannot be called complete,
ready, verified, or safe to advance until the changed flows have been exercised.

## What v78 did

The measurement this answers, from the Captain's machine:

    phpcs -d memory_limit=1G --standard=WordPress -s --report=summary \
      --sniffs=WordPress.Security.NonceVerification "$CAND"
    -> 0 ERRORS, 84 WARNINGS, 8 FILES

v76 did not move that number and never could have. v75 reordered eleven
admin-ajax handlers, which answered the reviewer's ordering complaint, but WPCS
is satisfied by a nonce anywhere in the enclosing function rather than by its
position -- so those handlers were never among the 84. `--report=full` on
flosc.php settled it: all 15 of its warnings were `ajax_serve_user_audio` and
nothing else.

The 84 were reads inside functions that verify no nonce at all. Each was
classified by what the request actually does, and given the control that suits
it.

### Read-only navigation -> a real input boundary

`includes/flosc-request.php` is new. `flosc_nav_param()`, `flosc_nav_param_int()`
and `flosc_nav_param_present()` read through `filter_input()` and validate
against a closed set of expected values. Roughly thirty scattered reads of
`page`, `tab`, `view`, `ivr` and list filters now go through it. Several of
those sites previously accepted any string the URL carried and used it to pick a
template or a script handle; they are allowlisted now, which is a narrowing
rather than tidier code.

### State-changing dispatchers -> nonce before the body

`maybe_process_flosc_settings_post()` declares each route with the nonce action
and field its own form prints, matches the route, and calls
`check_admin_referer()` before `$_POST` is unslashed. `admin/ivr-messages.php`
does the same at file scope with its eleven submit keys. Both previously read
and dispatched on the body first and left verification to whatever handler
control reached.

### Signed and third-party callbacks -> the control named and verified

`ajax_serve_user_audio` now validates its parameters as types --
`FILTER_VALIDATE_INT` with ranges for `user_id` and `exp`, a 64-char hex match
for `sig` -- rather than running them through `FILTER_SANITIZE_NUMBER_INT`,
which strips characters and returns a string: `"12abc"` became `12`. Its HMAC is
verified before a byte is served.

An earlier version of this file claimed a WordPress nonce "cannot travel in an
`<audio src>`". That is false -- it is an ordinary query string. The HMAC is the
right control here for a different reason: the URL is minted server-side with a
short expiry over the four values that select the file, so it is a capability
URL rather than a form submission. The claim was wrong; the design stands on its
own reasons.

OAuth (`verify_state()`) and magic-link (the emailed token) keep their own
controls, now bounded and sanitized at the read.

### Every suppression removed

24 `phpcs:ignore` / `phpcs:disable` directives for `NonceVerification` are gone,
including two I wrote earlier the same day. Plugin-wide count is **0**. No
suppression was removed without changing the code beneath it -- verified by
diffing every hunk that removes one for a corresponding code change.

Two exposed real defects, both fixed rather than re-annotated:
`handle_admin_activate_email_account()` built its nonce action from an
`absint()` of the raw id, so `""` produced the live action name
`flosc_activate_email_0`; and `save_newsletter_profile_field()` depended on a
nonce core verifies in a caller no reader of that method can see, so it verifies
`update-user_{id}` itself now.

## Measured in this container

    php -l, whole tree                                  0 errors
    NonceVerification suppressions, plugin-wide         0
    superglobal reads in functions with no nonce        0
    zip entries                                         278, 0 under tests/
    zip flosc.php version                               8.0.0

**There is no phpcs, phpstan, wp-env or Plugin Check in this container.** The
third line above is my own static sweep, not PHPCS, and the two are not the same
instrument.

## Measured on the Captain's machine — the acceptance test

    phpcs -d memory_limit=1G --standard=WordPress -s --report=summary \
      --sniffs=WordPress.Security.NonceVerification "$CAND"

    v76   0 ERRORS, 84 WARNINGS in 8 FILES   exit 1
    v78   no output                          exit 0

    84 -> 0

`--report=summary` prints nothing when there is nothing to report, and phpcs
exits 0 only when it found neither errors nor warnings. The v76 run exited 1.

Empty output and exit 0 is also what a scan of *zero files* looks like, so the
run was repeated with `-p`, which prints one character per file -- a dot for a
clean file, `W` for warnings, `E` for errors:

    ............................................................  60 / 218
    ............................................................ 120 / 218
    ............................................................ 180 / 218
    ......................................                       218 / 218

    Time: 17.28 secs; Memory: 192.01MB

218 dots, no `W`, no `E`. The count reconciles exactly against the tree:
187 `.php` + 11 `.js` + 20 `.css` = 218. Every file phpcs can read was read.

The tree carries 187 PHP files; the zip ships 149. The other 38 are `tests/`
and build scripts, which do not ship -- so the scan covered more than the
artifact does, not less.

**This is not the same claim the v70 readme made.** That one also read "exit 0,
no output", but it was produced under a filter that excluded warnings, and this
sniff only ever emits warnings -- so it could not have reported a problem on any
codebase. The run above carries no such filter. The difference between the two
lines is the whole point of building the bench.

Run by the Captain, not by me. There is no phpcs in my container.

## Still not claimed

**That this passes review.** Their AI pass is non-deterministic -- T12 and T13
reviewed identical bytes and returned different lists.

**That `Requires at least: 7.0` is true.** wp-env has only ever booted this
plugin on 7.1.
