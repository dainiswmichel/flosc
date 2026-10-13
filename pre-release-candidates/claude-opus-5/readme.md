# FLOSC 8.0.0 — ClaudeCode home-run candidate v156

This directory holds the complete v156 review candidate. The plugin source is
`flosc-by-claude-opus-5/flosc`; `flosc.zip` is the artifact built from it with
`./build-dist-zip.sh`, and `SHA256SUMS` and `build-manifest.json` record what it
is.

| | |
|---|---|
| Internal Iteration | v156 |
| Plugin version / stable tag | 8.0.0 |
| Code commit | `c06e389bddec2a95a394521a900065ba03b8f9ca` |
| ZIP SHA-256 | `8595fd580558ed60e358e664084753e5ad6a8b82ff64d9866716ec374baf1745` |
| ZIP contents | 283 entries, 243 files, no `tests/` |

## Where this starts from

Codex v154 (`4333777`), which carries review Issues 10 and 11. This lane was on
v150 and did not, so the lane was replaced with v154 rather than forward-porting
that work a second time. The delta from v154 is twelve modified files, two
renames and one added test — small enough to read in full.

## What v156 changes

**Carried back from v150, which v154 did not have.** Seven handlers read the
return-navigation hint as `isset( $_GET['ivr'] ) ? sanitize_file_name( … )`. An
array passes `isset()`, `wp_unslash()` returns it unchanged, and
`sanitize_file_name()` opens at `remove_accents()`, which calls `preg_match()`.
Measured against WordPress core on PHP 8.3: `preg_match(): Argument #2
($subject) must be of type string, array given`. So `?ivr[]=x` was a fatal.
All seven sit behind `check_admin_referer()` and `current_user_can()`, so an
administrator could only crash their own request — but a 500 from a query
parameter is what the clean-install `WP_DEBUG` pass exists to find. The
test-offer nonce is its own statement again rather than one term of an `&&`
chain. v154's Reply-To fix was kept over this lane's: dropping the display name
removes the comma problem entirely, and the name is still in the mail body.

**Issue 09 — nonces and permissions.** `flosc_set_status` named no offer and no
status and `flosc_toggle_status` named no offer, so a link minted for one row
verified for every row on the screen. A nonce proves the request came from a form
this user was served; without the id in the action it cannot also prove which row
that form was for. `set_status` carries the status too, because the link encodes
the outcome — a link minted to set draft must not verify a request setting
active. `flosc_offer_action_nonce_action()` builds the action for all three
controls and both the eight links and the handler call it, so they cannot drift.
Separately, submission detection is no longer folded into nonce verification at
five sites: *did this form post* is routing, *is the request genuine* is
security, and sharing one condition made a page that posted nothing and a forged
save indistinguishable. Routing is now its own statement and the nonce is
`check_admin_referer()`, which refuses rather than returns.

**Issue 10 — the suppression-disabled sanitization gate.** The five
`ValidatedSanitizedInput` errors that appeared with annotations disabled were not
hidden defects. With annotations renamed and the project ruleset in force: zero
errors. With annotations renamed and the bare WordPress standard: five. The
difference is the standard discarding `customSanitizingFunctions`. So the
annotations were removed rather than defended — all five are gone, and the
ruleset's declared list is now the only place the project tells PHPCS that a
function of its own sanitizes. There are no `EscapeOutput` annotations either, so
disabling every annotation for both review sniffs changes nothing they report.

**Issue 03 — not permitted files.** Both cited catalogs are renamed to a
permitted extension. Nothing about DA1 changes: the runtime filename is
`flosc_da1_catalog_{key}.tsv`, built from the catalog key in each `pack.json`,
which is explicit in both and untouched. The shipped source filename was never
the runtime one. The zip carries no `.tsv` at all.

**Issue 05 — the readme heading.** v152 promoted `= External Services =` to
`== External Services ==`. The wordpress.org parser recognises seven section
names and External Services is not one, so the promotion routed 130 lines
through Description and past its 2500-word trim, truncating the published
disclosure. The heading is back to the H3 form.

## The gate

`tests/check_tab_scope_and_nonce_binding.php` was rewritten. Its previous
version asserted this lane's implementation and failed fourteen times against
v154 — which meets every one of those guarantees by other means. Thirteen of the
fourteen were the test being wrong. Each assertion now names a property rather
than a shape: the emitter's and verifier's nonce prefixes are compared against
each other, not against a literal, and escaping is checked at the echo whatever
function performs it. Every assertion was proven to fail when its defect is
reintroduced.

## Measured

| | |
|---|---|
| `php -l` | 155/155 shipping files |
| Regression suite | 53/53 PHP, 6/6 JS |
| Project PHPCS ruleset | 0 errors, 50 warnings |
| The two review sniffs | 0 errors, 0 warnings |
| Same, annotations disabled | 0 errors, 0 warnings |
| PHPCompatibilityWP `7.4-` | 0 errors, 0 warnings |
| `unzip -t` | clean |
| Source-to-zip parity | 243/243 identical |

## Not run

Official Plugin Check, and a clean WordPress install with `WP_DEBUG` on. Neither
can run in the container this was built in. No runtime click has exercised a
repaired offer control, a split settings handler, or a renamed starter-pack
catalog. Those three remain the open conditions on this candidate.
