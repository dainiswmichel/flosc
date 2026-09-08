# Home run candidate v11 — Claude Opus 5

Assembled from the four tested candidates. Version held at **8.0.0** — this is
a resubmission, not a release.

**Two numbers, and they are not the same number.** `v11` is this candidate's
iteration — how many times this folder has been rebuilt. `8.0.0` is the
plugin's version, and it does not move: 8.0.0 is what goes to wordpress.org.

| Iteration | What went in | Commit on `main` |
|---|---|---|
| v7 | The assembled candidate, plus the empty-profile guard | `8734efe`, `7d329fa` |
| v8 | The designer pass — one card, nested density, both dialogs gone | `48b951b` |
| v9 | A colon for nesting levels, a period for decimals | `c5364b5` |
| v10 | The headings are the palette; ticking reveals; shelves open on one click | `9fbd41b` |
| v11 | Summary keeps its ellipsis; the full line is a wrapped card footer | this commit |

v8 and v9 were committed before this counter was picked back up, so their
subject lines read `Candidate claude-opus-5 …` rather than `Home run candidate
v8 / v9`. The table is the mapping; the history is not being rewritten.

## The designer pass — read this first

Candidate iteration **v11**. Plugin version still **8.0.0**, and it stays there.

The DA1 AI Personality Designer now works on one idea instead of three.

**A wellspring is an aspect. A category is a group of aspects. Both are the
same card.** Drop an aspect onto a card and that card becomes the heading the
aspect sits under — keeping its density, gain, binding, hue, star and
trajectory, and gaining the one field a plain aspect has no use for: what to
call the group. `heading · wellspring · cloud · rain cloud · pool · category`,
defaulting from density — cloud in the soul band, rain cloud in character,
pool in behavior.

**Nested density.** A colon separates levels, a period separates decimals, so
the two can never be read for each other. An aspect at 16 dropped into a card
at 95 reads `95:016`; one at 100 reads `95:100`, so the larger number sorts
last. A nested card keeps its own decimals — `95:016.5` — which a period doing
both jobs could not carry. Any depth: `95:025:100`. Root and nested alike run
0–100 to three decimal places, 100,001 positions per level.

Never stored — the card still holds 16, and dragging it out is 16 again with
nothing to restore. Equal densities sort alphabetically.

### New in v10

**The thirteen headings are the palette's shelves.** One list, not two: an
aspect waits on the shelf it will be written under, and renaming a shelf
renames its heading, because they are the same object.

    Identity and Role             6      (was Name and Core Role)
    Philosophy and Values        12
    Boundaries and Prohibitions  18      (was Hard Boundaries and Prohibitions)
    Knowledge, Doubt, Correction 24
    Opinions and Preferences     30      new
    Tone and Communication Style 40
    Stance Toward the Human      48
    Behavior in Ambiguity        56
    Adaptation                   62
    Resourcefulness              68      new
    Decisions incl. Infrequent   74
    Banned Words and Fillers     84
    Output and Delivery          94

The 58 catalog cards are re-shelved by rule, and all still ship unticked —
template defaults, not decisions. Drag one and it moves for good.

**Three things that were broken on v9 and are fixed here.**

1. Opening a shelf took two clicks. Clicking its summary re-rendered the panel
   from the open state as it stood *before* the browser's own toggle landed,
   so the shelf sprang shut. The click belongs to `<details>` now.
2. Ticking a card left it inside a collapsed heading, scrolled to and
   invisible. Every container between the card and the top opens.
3. A card row printed over itself. The summary's last grid column was sized
   `auto`, which cannot shrink, so a long meta line crushed the label and
   overflowed across it. It is `minmax(0, auto)` now, so the column gives way
   and the ellipsis does what an ellipsis is for. The full line is not lost:
   it is the footer at the bottom of the opened card, wrapped, and it names
   the heading the card is written under.

**The builder's own trajectory list is gone.** FLOSC already has trajectories:
posts in the `trajectory` category, managed on the Trajectories tab. Anything
written in the old panel migrates to aspect cards, so nothing stops reaching
the AI. An aspect's trajectory can be `412`, `?post=412`, or a permalink.

### What to try on the site

1. **+ Category.** It did nothing at all before. Both dialogs opened a
   `<form>` inside WordPress's own settings form, which is invalid — the
   browser dropped the inner tag, `#tribForm` came back null, and the listener
   on it threw, taking every line of setup after it including + Category's own
   listener. Both dialogs are gone; the buttons make the card outright.
2. **Drag an aspect onto another aspect's coloured bar.** The lower one goes
   inside the upper one, which becomes the heading. Its row says
   `pool · 3 members`, and each member reads `95:016`.
3. **Save and reload.** The group survives. It would not have before:
   `workshopFile()` wrote the placement map and `importSpec()` never read it,
   so every placement was rebuilt from density on load.
4. **Open any card.** Gain, binding, hue and shape each on their own line, and
   every field says where it goes — the AI's copy, the design document, or
   neither.
5. **Soul section**, on every card, read from where the card actually is
   rather than recalculated. Default is the last heading at or below the
   card's density.
6. **A trajectory that is a post.** Type `412`, `?post=412`, or the permalink.
   It compiles to that post's title and excerpt.
7. **Edit on a palette category.** Fields in place, not two browser prompt
   boxes — plus a button that puts the category into the personality as a
   group card with its aspects inside it.

## Where the code is

    branch:  claude/ready-to-help-jsw2li
    tree:    the plugin at the repository root on that branch
    commits: 19, from 477f252 to the branch head

https://github.com/dainiswmichel/flosc/tree/claude/ready-to-help-jsw2li

`flosc-by-claude-opus-5/flosc/` is the complete tree at that commit, same as
the other four candidates carry, so this folder can be deployed from directly
without pulling the branch first.

**`flosc.zip` is here**, built from this tree with `./build-dist-zip.sh` at the
head commit — 277 files, 2.6 MB, forbidden-path scan clean. `SHA256SUMS` covers
it. Build your own from either copy if you prefer; they are identical.

**If you keep both, one will drift.** The branch is the trunk and this tree is a
snapshot of it. When the branch moves, this does not. Deploy from whichever you
choose, but do not rsync one over a site built from the other, and never with
`--delete` — that is how live repairs get wiped by an older snapshot.

`pre-release-candidates/` is excluded from the artifact by `.distignore` and by
the build's hard deny list, so none of these folders can ride along into a ZIP.

## What went in, and from where

| Source | What was taken |
|---|---|
| **Codex GPT-5** (9/10) | Full profile every turn; RAG miss falls through to the provider; `get_response_result()`; personality boilerplate removed; genome/profile fingerprint |
| **OpenCode big-pickle** (8/10) | The no-hedge method pair and the `method_exists` guards on both call sites |
| **Grok 4.6** (5/10) | Public Request Protection; the six IVR greeting variables; the three line-break forms; Public Title out of the landing header |
| **GitHub Copilot** (3/10) | Nothing. It lacks `flosc-model-catalog.php` and `flosc-model-parameters.php` outright. |

Codex cleaned BubblyBetty and DadJokeDan. Friendly Guide and Tech Agent still
carried the full 5.4 KB, and two of the four starter packs attach Friendly
Guide — so the packs would have been tested on the diluted path. All four are
clean here.

## The three that had to work

**Starter packs extract and work.** Each `pack.json` now declares a `ships`
list of everything in its directory, and the gate holds the build to it in both
directions. That covers the two WXR files the READMEs tell a floscAdmin to
import by hand — the installer never reads them, so nothing referenced them and
nothing would have stopped a build exclusion dropping them.

**Personalities work.** The compiled profile goes on every turn, resolved fresh
from the library row by id, so a mid-conversation switch takes effect on the
next reply. Each shipped profile went from ~6.3 KB to ~1 KB by returning the
sales trajectory to the flow section that already sends it — which costs less
per turn than the short anchor did and keeps the character. The chat log now
records which personality answered, so the switch is provable from the record
rather than judged by ear.

**A refresh mid-answer does not break the chat.** The browser mints a turn id
before the request leaves. On the next load it asks what became of that turn:
recovered, and the answer the visitor reloaded away from is shown; not
recovered, and the orphaned message is dropped so the next request carries a
clean history. The same id makes a resend idempotent, so one question is never
billed twice.

## Before shipping

```
for f in tests/test_*.php tests/check_*.php; do php "$f" || echo "FAIL $f"; done
find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
php tests/check_starter_pack_assets.php <path-to-built>/flosc.zip
grep -n "Version:" flosc.php          # 8.0.0
grep -n "Stable tag" readme.txt       # 8.0.0
```

Then, on the fresh install, the mechanical matrix:

```
wp --require=wp-content/plugins/flosc/tests/journey-harness.php \
   flosc-journey --transcript=/tmp/journey.txt
wp --require=wp-content/plugins/flosc/tests/journey-harness.php \
   flosc-journey --pack=vegan-latvian-kitchen --personality=<the one you built>
```

## What only a person can decide

The harness answers whether the gating, the offers, the generated turns and the
log rows are right. It cannot answer whether it sells gracefully, whether the
character is alive, or whether you would buy the PDF from that conversation.
Those are the four transcripts and the three questions per pack.

And the contract itself: install on a fresh WordPress, extract the packs,
connect a provider, **build a personality from scratch in the builder**, attach
it to a pack, and see it carry that flow to its outcome — the PDF sale, the
membership, the contact exchange — in character.

## Frozen

Not touched, and not to be touched without a named failure first:

- the page-wide Save, its label, and the last-save MTS line
- the Step 2b Model Tuning localised Save and its state machine
- the model catalog and parameter surfaces (Fetch models, Describe this model)
- the full-page → companion handoff
- prohibitions living in two places by design — Soul · rules for what the
  personality must never do, Behavior · language for what it must never say

## Known, and left alone

`flosc_ensure_table()` runs `dbDelta()` on every logged chat turn, which means
a table introspection per turn. Pre-existing, works, and outside anything that
was failing. Worth a schema-version guard later.

Per-card saves in IVR Management would write into the same file the full-file
Save writes. Not complex code, but a shared-write conflict is how data goes
missing quietly. An enhancement for after the resubmission, done with the
conflict solved rather than around it.
