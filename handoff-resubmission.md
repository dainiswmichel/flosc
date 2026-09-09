# FLOSC 8.0.0 — handoff for the resubmission session

Written 2026-09-09 by the session that built candidate v25 and cleared Plugin
Check.

Scope of this file: **packaging and WordPress.org resubmission only.** Stress
the current zip, and build a new one if it changes. For the design model, the
codebase laws and the working rules, read `handoff.md` in this same folder —
it is the build handoff and it is not superseded.

---

## 1. What FLOSC is

**(F)reeline → (L)ogin → (O)ffer → (S)ale → (C)ontent** — try-before-you-buy
WordPress journeys. A visitor gets a freeline sample, logs in, sees an offer,
buys, and receives content. Starter Packs ship whole working journeys.

Inside it is the **DA1 AI Personality Designer**: a document builder that
composes an AI personality out of aspects, each carrying density (sequence),
gain (frequency), binding (must/should/may/dam), hue and star. Density order
writes the thirteen headings of the profile document. The composed document is
stored as `ai_base_prompt` in `wp_options → flosc_personality_library`, and
that field is the only one that reaches the AI provider.

**Version is 8.0.0 and does not move.** This is a resubmission of 8.0.0, not a
new release. `flosc.php` header, `FLOSC_VERSION`, and the `readme.txt` Stable
tag all stay `8.0.0`.

---

## 2. Where everything lives

    remote    https://github.com/dainiswmichel/flosc
    branch    claude/ready-to-help-jsw2li      <- develop and push here, only here
    main                                       <- candidates only

Working copy in a remote session: `/home/user/flosc` (the plugin root — this
repo *is* the plugin directory, so `flosc.php` and `readme.txt` sit beside
`.git`).

Five candidate folders live on `main` under `pre-release-candidates/`:

    claude-opus-5          <- ours
    codex-gpt5
    github-copilot
    grok-4-6
    opencode-big-pickle

**Only `pre-release-candidates/claude-opus-5/` may be changed on `main`.** The
other four are other agents' builds, kept for comparison.

Ours holds:

    build-manifest.json
    flosc.zip                          the artifact
    sha256sums                         covers the artifact
    readme.md                          clean summary of the candidate
    flosc-by-claude-opus-5/flosc/      full source mirror (not the zip contents)

`pre-release-candidates/` never enters a zip: it is excluded by `.distignore`
**and** by the build script's hard deny list, and the finished archive is
re-scanned for it.

---

## 3. The artifact as this session left it

    branch    claude/ready-to-help-jsw2li   55220e5   clean
    main      a1ff23e   "Home run candidate v25 coded by Claude Opus 5"
    version   8.0.0     flosc.php:6 · FLOSC_VERSION flosc.php:20 · readme.txt:8
    headers   Requires at least 7.0.4 · Requires PHP 7.4 · Tested up to 7.1
    zip       pre-release-candidates/claude-opus-5/flosc.zip   2,713,760 bytes
    sha256    cc24148382b19382419bcc45c1ed5561639a8884eb8e14e590d1f12da40a31ee
    contents  277 files, 2.6 MB, forbidden-path scan clean

Plugin Check on this zip: **Checks complete. No errors found.** Zero errors,
zero warnings.

### build-manifest.json is stale in four places

Do not read it as current state. It still says:

    head_commit                a62220c        v10 — branch head is 55220e5
    status                     DESIGNER_PASS_AWAITING_CAPTAIN_TEST
    wordpress_version_headers  "Tested up to 7.0.4"     it is 7.1
    plugin_check               "two findings addressed"  it is now clean

If the candidate is refreshed, these four fields get corrected in the same
commit. `candidate_iteration` is 25; the next rebuild is 26.

---

## 4. Building a zip

    FLOSC_ZIP_OUT_DIR=/tmp/floscship ./build-dist-zip.sh

**Never `zip -r` by hand.** The script is fail-closed and does four things a
hand zip does not:

1. Refuses to run at all if dev directories are sitting in the ship tree
   (`flosc_development_worknotes`, `flosc_development_archives`, `zip-files`,
   `tmp`, `node_modules`).
2. Stages with rsync using `.distignore` **plus** a hard-coded deny list, so a
   bad edit to `.distignore` cannot leak `tests/`, `sample-data/`, `vendor/`,
   `.git/`, `.github/`, `composer.json`, `composer.lock`,
   `pre-release-candidates/`, `build-dist-zip.sh`, `.distignore`, or any
   nested `*.zip`.
3. Scans the staged tree and aborts on any forbidden path, on filenames
   shaped like worknotes (`*worknote*`, `*wporg-review-reply*`,
   `*SESSION-SUMMARY*`, `*agent-review*`), and if `flosc.php` or `readme.txt`
   is missing.
4. Re-inspects the finished archive with `unzip -l` and deletes it if
   anything forbidden survived.

Everything inside the archive is under `flosc/`, which is what WordPress.org
requires.

`tests/` must never ship: the harnesses define `ABSPATH` and redeclare core
functions on purpose. `handoff*.md` must never ship: it carries the operator's
local path and his machine's username.

---

## 5. The gates. Run all of them. They are cheap.

    for f in tests/test_*.php tests/check_*.php; do php "$f" || echo "FAIL $f"; done
    find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
    node --check assets/js/flosc-app.js
    node tests/check_density_nesting.js
    php tests/check_provider_identity.php
    php tests/check_packaging.php
    php tests/check_starter_pack_assets.php /tmp/floscship/flosc.zip

Two of these exist because of a specific, expensive failure:

**`tests/check_provider_identity.php`** implements the WordPress.org readme
parser's own section-routing rule and measures the description bucket in
words. It was wrong twice before it was rewritten to match the parser instead
of an assumption. It fails over 2,200 words and lists any heading it caught
being routed by accident.

**`tests/check_packaging.php`** gates the version headers **by value**, not by
presence: `readme.txt` Requires at least = 7.0.4, `readme.txt` Tested up to =
7.1, `flosc.php` Requires at least = 7.0.4, and
`version_compare($tested, $requires, '>=')`. It also covers what Plugin Check
flags most — inline style attributes, hand-written script and stylesheet tags,
direct-request guards — and that the version reads 8.0.0 in both files.

`php tests/check_starter_pack_assets.php` takes the **built archive**, not the
tree. Point it at the zip.

---

## 6. The readme rule that cost three iterations

From WordPress.org's own `plugin-directory/readme/class-parser.php`:

```php
public $expected_sections = array(
    'description', 'installation', 'faq', 'screenshots',
    'changelog', 'upgrade_notice', 'other_notes',
);

if ( ! in_array( $section_name, $this->expected_sections ) ) {
    $section_name = 'other_notes';              // unknown heading -> other_notes
}

if ( ! empty( $this->sections['other_notes'] ) ) {
    $this->sections['description'] .= "\n" . $this->sections['other_notes'];
}
```

**Every unrecognised `== Heading ==` ends up inside Description.** Renaming one
to `== Other Notes ==` changes nothing. Moving prose between Description and
Other Notes changes nothing. Both land in the same bucket.

And the limit is **words, not characters** — the warning text lies:

```php
public $maximum_field_lengths = array(
    'short_description' => 150,
    'section'           => 2500,
    'section-changelog' => 5000,
    'section-faq'       => 5000,
);
// trim_length( $content, 'section', 'words' ):
$word_count_with_spaces = $length * 2;          // 2500 words
```

Current budgets, measured by the gate:

    description bucket   1,028 words   (limit 2,500)
    faq                  2,616 words   (limit 5,000)
    changelog              176 words   (limit 5,000)

Consequences for editing `readme.txt`:

- **Do not add a top-level `== … ==` heading** unless it is one of the seven
  the parser knows. Anything else silently inflates Description.
- Long disclosure belongs under `= Subsection =` inside FAQ, which has its own
  5,000-word budget. That is where External Services (1,626 words) lives now.
- Code standard, Support & Contribution and Stay Connected are `=`
  subsections of Changelog for the same reason.
- Re-run `php tests/check_provider_identity.php` after any readme edit.

---

## 7. The version headers

    Requires at least: 7.0.4        the release floor — stated repeatedly, not negotiable
    Requires PHP: 7.4
    Tested up to: 7.1               a real WordPress version, >= the floor
    Stable tag: 8.0.0

`Tested up to` is a different field with a different rule: it must name a
WordPress version that exists, and Plugin Check errors if it is lower than
`Requires at least`. Setting it to 7.0.4 produced
`outdated_tested_upto_header` because 7.0 < 7.1. Both values are held by
`tests/check_packaging.php`; change one there or the gate fails.

---

## 8. ChemiCloud — how code reaches the live host

Syncing to ChemiCloud is how anything gets out to the live site. The zip is
for WordPress.org; ChemiCloud is for testing on his running install.

    live plugins path   /home/dainisne/public_html/wp-content/plugins

His ship command runs on his Mac, not in this repo:

    cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0 && ./flosc-ship.sh

`flosc-ship.sh` is not in this repository. Do not look for it here and do not
attempt the deploy from a remote session — offer, and he ships.

Two standing facts about that host:

**OPcache.** Shared hosting caches PHP bytecode. After a file-level deploy the
web server can keep serving old code. CLI `opcache_reset()` does **not** touch
the web server's pool — separate process pools. Flush by calling
`opcache_invalidate()` from within a web request, or by changing the file's
mtime. (`.github/copilot-instructions.md:45`)

**Stale tests on the host.** `deploy-live` mirrors `tests/` to the live host.
Harmless — the harnesses are CLI-guarded — but untidy:

    ssh chemicloud "rm -rf public_html/wp-content/plugins/flosc/tests"

(`handoff.md:144`)

---

## 9. Stress-testing the current zip

Static, in this session, cheap:

    cd pre-release-candidates/claude-opus-5
    shasum -a 256 -c sha256sums                        # expect: flosc.zip: OK
    unzip -l flosc.zip | tail -3                       # expect 277 files
    unzip -l flosc.zip | grep -v '^ *[0-9]* .*  flosc/' # expect nothing outside flosc/
    unzip -l flosc.zip | grep -Ei 'worknote|handoff|/tests/|/vendor/|composer\.|sample-data/|\.git'
    unzip -p flosc.zip flosc/flosc.php  | sed -n '1,20p'   # Version: 8.0.0
    unzip -p flosc.zip flosc/readme.txt | sed -n '1,12p'   # Stable tag: 8.0.0
    php tests/check_starter_pack_assets.php pre-release-candidates/claude-opus-5/flosc.zip

Then, on a fresh WordPress install:

1. Upload the zip, activate. No fatals, no notices.
2. Run **Plugin Check**. The bar is what v25 already cleared: no errors, no
   warnings.
3. Install a Starter Pack, connect an AI provider.
4. **The acceptance test, in his words** (`handoff.md` §1): two live turns as
   BubblyBetty, admin changes the Personality dropdown mid-conversation, the
   next reply is unmistakably DadJokeDan — no reload. That is the WOW.

Item 3 of `handoff.md` §6 is the standing reviewer concern to expect:
custom auth via `determine_current_user` and `rest_authentication_errors`.
Tokens are revocable per user (generation in the signature, bumped on logout
and password change) — see `tests/test_auth_token.php`. The hooks stay; they
solve real cross-domain identity.

---

## 10. If the zip changes: rebuild and republish

1. Do the work on `claude/ready-to-help-jsw2li`. Fetch before pushing — other
   agents push to this branch too. Rebase your own commits on top. **Never
   force-push.**
2. Run every gate in §5.
3. `FLOSC_ZIP_OUT_DIR=/tmp/floscship ./build-dist-zip.sh`
4. Refresh, on `main`, in `pre-release-candidates/claude-opus-5/`:
   `flosc.zip`, `sha256sums`, and `build-manifest.json` — including the four
   stale fields listed in §3, plus `candidate_iteration` and a new `iterations`
   entry. Nothing else on `main` changes.
5. Commit subject: `Home run candidate v<N> coded by Claude Opus 5`.
   v25 is the last one. The next is v26.

**The `.gitignore` trap.** The root `.gitignore` has `*.zip`, with a negation:

    !pre-release-candidates/*/flosc.zip

Without that line `git add -A` silently skips the artifact — which is how
`main` once carried a v7 zip beside a v9 `sha256sums`. After committing,
confirm the zip is actually in the commit:

    git show --stat HEAD | grep flosc.zip

---

## 11. Working rules

Full versions in `handoff.md` §0, §7 and §9. The short form:

- **Version frozen at 8.0.0.** No bumps, ever.
- **Branch `claude/ready-to-help-jsw2li`.** Nothing else without explicit
  permission. On `main`, only `pre-release-candidates/claude-opus-5/`.
- **Roadmap → approval → code.** He approves before anything is written.
- **Never propose reverting, discarding, or resetting committed work.**
- **Tech only.** No emotional language, no commentary on how he feels.
- Captain / Boss / Maestro / Sir. Never issue him instructions — no "run
  this", no "say go". Offer; he decides.
- Do not condense, reword or jargonize his words back at him.
- **Spend is real.** No subagents, no speculative refactors, no mountains out
  of molehills. Keep sessions short; every message re-reads its history.
- Filenames do not shout — lower-case. `readme.txt` is the one exception,
  because WordPress.org mandates it.
- When he says a thing does not work, believe the symptom and go measure it.
  Do not reason about the code from memory.
