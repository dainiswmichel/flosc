# Brief for Grok — FLOSC companion nesting: verify the fix, then close the gap

You are analysing a WordPress plugin called FLOSC (repo `dainiswmichel/flosc`).
A defect was found, diagnosed, and fixed — **but the fix landed in only one of
two copies of the code in this repository.** Your job is to verify the diagnosis
independently, then close the gap without repeating the mistakes listed at the
end of this brief.

Do not trust this document. Every claim in it is checkable against the tree.
Check them.

---

## 1. The defect

FLOSC ships a "companion" chat: a floating bubble on ordinary WordPress pages
that opens a panel. The panel is an `<iframe>` whose `src` is a FLOSC *app
route* — the chat application, not a normal site page.

On a live test site running candidate v108, the panel on a recipe post rendered
the WordPress admin bar **three times, nested**. The frame tree at the end of
the sequence was:

```
[0] /2026/09/23/vegan-latvian-kitchen-pdf/
  [1] /category/vegan_latvian_recipes/
    [2] /vegan-latvian-kitchen/?flosc_companion=1
```

Two visible symptoms, one causal chain:

* an entire WordPress page — header, admin bar, content — loaded **inside** the
  chat panel;
* that page then mounted **its own** companion bubble, which opened its own
  chat frame. Each repetition added a level.

## 2. The causal chain, as diagnosed

Verify each link independently.

1. `admin/flosc-app.php` decides "am I the embed surface?" from a **single query
   parameter**, `flosc_companion`, which it turns into the body class
   `flosc-companion-embed`.

2. The app navigates *itself* in several places — the address `wp_login_url()`
   returns the reader to, **View profile**, **View dashboard**. Every one of
   those arrives **without** that parameter. The app is therefore still
   physically inside the iframe while its own test says it is not.

3. Because the test said "not embedded", the **Minimize to companion** control
   rendered inside the panel.

4. `handoffToCompanion()` ended at `window.location.assign()` and **never asked
   whether it was the top window**. So the panel navigated *itself* to the hub
   page — an ordinary WordPress page, with an admin bar and a companion widget
   of its own — which mounted a bubble and opened its own chat frame.

The parameter-based test is the fragile link. Whether a document sits in a frame
is `window.self !== window.top`: a fact that survives navigation. A query
parameter does not.

## 3. The fix that is believed correct

Two layers, in this order of importance.

**Layer 1 — the structural rule (the one that matters).** A companion must
refuse to mount when it is inside a frame, *before the boot flag, before
anything renders*. Not "prevented at each door that could reach it" — one door
missed, or one added later, and the whole thing returns. In the fixed copy this
sits at the top of `assets/js/flosc-companion.js`:

```js
try {
    if (window.self !== window.top) {
        return;
    }
} catch (e) {
    // A cross-origin parent throws on access, which answers the question.
    return;
}
```

**Layer 2 — the individual doors**, in `assets/js/flosc-app.js`:

* `isFramed()` — `window.self !== window.top`, with the cross-origin throw
  treated as "yes, framed". Distinct from `isCompanionEmbed()`, which answers
  the narrower question of the embed *surface* and needs the body class.
* `navigateToHostPage()` — from inside the panel, host pages (View profile,
  View dashboard) open in a **new tab**, so the conversation is not replaced by
  wp-admin in a 380px column.
* `handoffToCompanion()` refuses inside a frame, and the dock button is removed
  from the DOM there rather than left on screen to be refused.
* `wp_login_url()` is given the app address **with** `flosc_surface` and
  `flosc_companion` on it when the current request is the embed surface, so a
  login started in the panel returns to the panel.

**Do not delete layer 2 once layer 1 is in place.** Layer 1 stops nesting;
layer 2 stops the panel from being replaced by wp-admin, which is a separate
defect with the same origin.

## 4. THE ACTUAL OPEN PROBLEM — this is the work

**This repository contains two copies of the plugin, and only one carries the
fix.**

| | root of repo | `pre-release-candidates/claude-opus-5/flosc-by-claude-opus-5/flosc/` |
|---|---|---|
| frame guard in `assets/js/flosc-companion.js` | **absent** | present, line 37 |
| PHP + JS files | 149 | 211 |
| declared version | 8.0.0 | 8.0.0 |

The root tree still carries, verbatim, the comment that the fix was written to
disprove:

```js
// Single boot: PHP enqueues this only on host pages with a validated FLOSC app
// route as iframe target. App routes never load this script (is_flosc_request).
// Nesting is impossible by that invariant — not by runtime "nest guards."
```

Both halves of that claim are true and both still work. **Neither governs what
the page inside the panel does after it loads** — which is exactly how a
WordPress page, its admin bar and a second bubble got inside the first one.
The PHP-side guard `is_flosc_app_route_url()` validates the address the panel is
*handed*; it has no say over where that page navigates itself afterwards.

`diff -rq` between the two trees: 10 differing entries under `assets/`, 132
under `includes/`, 49 under `admin/`. Both declare Version 8.0.0.

### What to determine

1. **Which tree is authoritative?** Is the root a stale export, a deliberate
   older baseline, or the thing that actually ships? The answer decides
   everything else. Do not guess it — establish it from `.distignore`,
   `build-dist-zip.sh`, the git history of both paths, and whichever tree the
   released zip is built from.
2. **If the root ships**, it has the nesting bug and a version number that lies
   about it. Port the fix.
3. **Is the 149 vs 211 file gap a second, larger regression?** This repository
   has a documented history of exactly that: see
   `pre-release-candidates/claude-opus-5/regression-lost-runtime.md`, which
   records a v87→v88 loss of 30 runtime entries plus a 20-file shipped payload
   (`starter-packs/`), where nothing fataled and every static checker stayed
   silent because the references were removed along with the files. Check
   whether the same shape is present here before assuming the gap is benign.
4. **Two copies of a plugin in one repo, both claiming 8.0.0, is itself the
   root defect.** Propose a structure where this class of drift cannot recur.

---

## 5. Traps — each of these was hit for real on this codebase

1. **`phpcs` silently scans nothing.** `phpcs.xml.dist` carries
   `<exclude-pattern>*/pre-release-candidates/*</exclude-pattern>`, and the
   candidates live there. A scan started inside a candidate folder matches
   **zero files** and exits clean in about 90ms. Copy the tree out first:
   ```bash
   cp -a flosc-by-claude-opus-5/flosc/. /tmp/scan/ && cd /tmp/scan && phpcs ...
   ```
   Any WPCS figure measured in place is meaningless. Always run a negative
   control — plant a known violation and confirm it is reported — before
   reporting a clean scan.

2. **Never fix by hypothesis on a live site.** A fatal was chased across v111,
   v112 and v113 with no error text and no local reproduction. Two of those
   three commits existed only to undo the previous one. Get the error text, or
   say plainly that you cannot and stop.

3. **Cross-file calls are a deploy hazard here.** These sites are updated by
   unpacking a zip over a live directory with OPcache in play, so a caller can
   be new while the callee's file is still old — a partial unzip, an OPcache
   entry not yet invalidated. PHP's answer to a call to a function that does not
   exist yet is a fatal, and a fatal during render is *"There has been a
   critical error on this website."* A value needed twice in one file is
   computed twice rather than shared through a third file. Duplication that
   cannot fail beats a dependency that can.

4. **Do not route click handlers through `window.open()` behind a timer or an
   `await`.** Three call sites — checkout, the visitor-depletion redirect, the
   SSO fallback — were nearly broken this way. Past an await, the click is no
   longer the active user gesture and the browser can refuse the popup; a reader
   pressing **Buy** would have got nothing at all.

5. **Hiding a control is not guarding an action.** The round before the real fix
   hid the trigger, left the navigation unguarded, and tied the hiding to a
   parameter that ordinary navigation removes. It looked fixed and was not.

6. **Static checks cannot see a missing feature.** `php -l`, WPCS, Plugin Check
   and a zip checksum were all working correctly and all silent through the
   v87→v88 loss, because the requires were removed along with the files.

---

## 6. What to produce

1. A verdict on each link in §2 — confirmed, refuted, or not determinable —
   with the file and line you checked.
2. A determination of which tree is authoritative, with evidence.
3. A minimal patch closing the gap in §4, layer 1 first.
4. A statement of what you could **not** verify. There is no WordPress runtime
   in a static checkout: the plugin cannot be activated, no admin screen
   renders, and no navigation can be exercised. Say so rather than implying a
   browser result you did not obtain.
5. If you propose a restructure for §4.4, keep it separate from the patch in
   (3). The bug fix should be reviewable on its own.

Report confidence honestly. "I could not reproduce this" is a useful result.
A confident wrong answer on this codebase has already cost several days.
