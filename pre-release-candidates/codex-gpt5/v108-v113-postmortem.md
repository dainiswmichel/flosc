# v108–v113 companion incident: evidence and recovery

Date: 2026-09m-30d

## Conclusion

The requested v108 work was a four-file content cleanup. It did not modify the
companion JavaScript or routing classes. The companion failure surfaced on the
v108 test installation because that environment exercised latent behavior that
the live v107 installation did not: a newly installed flow could lack a current
rewrite rule, and the app running in the panel could navigate its own iframe to
a host page.

The damaging part of the response was v109–v113. Instead of returning to the
known-good live state and defining the panel contract first, the work expanded
into successive navigation, mount, routing, template-order, and cross-file
changes. v110 used the wrong success condition: it proved that no second bubble
mounted, not that the first panel still contained chat. That allowed the visible
regression reported by the owner: the website remained where chat belonged.

## Established facts

1. The live ChemiCloud plugin contains 242 files. On 2026-09m-30d, these six
   files matched v107 commit `d28003f1` byte-for-byte:

   - `admin/flosc-app.php`
   - `assets/js/flosc-app.js`
   - `assets/js/flosc-companion.js`
   - `includes/class-flosc-framework.php`
   - `includes/full-page-mode/class-flosc-full-page-mode.php`
   - `includes/starter-packs/class-flosc-starter-packs.php`

2. v108 (`17338160`) changed only four files, +56/−56:

   - `admin/quiz.php`
   - `admin/settings.php`
   - `includes/class-flosc-chatpack.php`
   - one default sentence in `admin/flosc-app.php`

   Those edits neutralized pronunciation-school defaults and replaced built-in
   quiz text. They did not change iframe, companion, or route behavior.

3. v109 (`2363d778`) was the first companion repair. Its own commit message
   records the reproduced frame tree: a post, then a category page, then the
   FLOSC app. It changed `flosc-app.js` and `admin/flosc-app.php` to intercept
   selected navigation paths.

4. v110 (`423f132c`) added an early return to `flosc-companion.js` when the
   document is framed. That prevents a second companion from mounting. Its
   reported checks were bubble count and frame depth.

5. v111 (`5b433a7d` plus artifact commit `669b2961`) records the decisive
   negative control: v110 passed “no bubble inside panel” and “depth 1” while
   failing “chat input present” and “panel is the FLOSC app.” It also reproduced
   a `301` from the intended flow URL to a similarly named blog post when the
   flow rewrite was missing.

6. v112 and v113 changed how the login return value was shared between two
   places in `flosc-app.php`. v112 introduced a helper in `flosc-request.php`;
   v113 removed it because a partial live unzip could expose a new caller before
   the new definition. Neither commit had the error text for the wp-admin fatal
   they were discussing.

## Where the engineering process failed

### 1. Scope escaped the authorized task

The intended v108 delta was content. Once a runtime defect appeared, five new
candidate versions changed the companion system without first separating the
known-good live bytes, the clean-test environment, and the requested content
delta.

### 2. The test oracle described implementation shape, not user behavior

“No nested bubble” and “frame depth 1” are insufficient. The actual invariant
is: the panel contains the FLOSC app and a working chat input. If it does not,
the panel must fail closed. v110’s checks certified the wrong document.

### 3. Symptom suppression preceded root-cause isolation

The mount guard stopped recursive companions, but it did not prevent the iframe
from being navigated to a host page or redirected there by WordPress. It changed
the visible symptom from nested companions to a website inside the panel.

### 4. The clean-install routing state was not part of the test fixture

A starter-pack flow could be registered after rewrite rules were generated.
WordPress then treated the app URL as a 404 and canonical guessing selected a
similarly named post. A browser `load` event still fired, so the panel accepted
the wrong document unless it verified application readiness.

### 5. The repository did not retain the claimed browser contract test

Commit messages describe useful browser checks, but the committed `tests/`
tree contained no regression test for the companion surface. The next agent
could not rerun the test that supposedly justified the release.

### 6. An undiagnosed fatal triggered code movement

v112 and v113 reasoned about a fatal without its stack trace. Even though v113
removed a genuine partial-deploy hazard, neither version could establish the
cause of the reported fatal. That work should have stopped at evidence
collection.

## v114 recovery design

v114 starts from the live/v107 companion implementation and retains the bounded
v108 content edits. The v109–v113 outcomes are reimplemented as one contract:

1. Companion chrome cannot boot in a frame.
2. The iframe is invisible until the FLOSC app sends `flosc_app_ready`.
3. A first failure retries once without continuity payload.
4. A second failure removes the frame source and shows an error. A site page or
   server error is never presented as chat.
5. Every navigation that must leave chat is classified explicitly:
   profile/dashboard/path open separately; checkout/depletion/SSO move the top
   window through the already verified parent-message channel.
6. Minimize is rejected before state mutation when already framed.
7. Login returns to the companion surface.
8. Flow routes run before canonical 404 guessing, clear 404 only for confirmed
   FLOSC requests, and starter-pack registration refreshes rewrite rules.

The committed regression test checks all of these source-level invariants. It
does not substitute for a browser-running WordPress test.

## Release decision

Do not deploy or resubmit v114 yet. Run the browser matrix recorded in the
candidate README against the exact zip SHA-256. A passing result must assert
the chat input and FLOSC app document, not just frame depth.

After the product gate is green, the WordPress.org gate still has independent
open work: official Plugin Check, the missing External Services disclosure, the
SSO `HTTP_HOST` trust issue, callback-return escaping review, decoded-JSON
validation, and durable signed evidence for the exact submitted zip.

## 2026-10m-01d correction

The preceding final sentence repeated stale conclusions from the older release
roadmap. Current `readme.txt` does contain an External Services section with 16
numbered services. The specific T12 defect that added `HTTP_HOST` to the SSO
redirect allowlist is also absent; remaining uses of that request header require
their own present-tense review and must not be mislabeled as the closed finding.

Candidate v114 also flushed rewrite rules after creating a flow without first
registering the rule for the file created later in that same request. v114.1
adds `flosc()->add_rewrite_rules()` immediately before the existing soft flush
at the starter-pack and uploaded-flow creation sites. Its runtime delta is two
added lines; the non-shipping contract test now requires registration before
flush at both sites. Browser WordPress testing, official Plugin Check, the
current callback/decoded-JSON/remaining-host review, and checksum-bound release
evidence remain open.
