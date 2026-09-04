# Home run candidate — Claude Opus 5

Assembled from the four tested candidates. Version held at **8.0.0** — this is
a resubmission, not a release.

## Where the code is

    branch:  claude/ready-to-help-jsw2li
    tree:    the plugin at the repository root on that branch
    commits: 15, from 477f252 to the branch head

https://github.com/dainiswmichel/flosc/tree/claude/ready-to-help-jsw2li

`flosc-by-claude-opus-5/flosc/` is the complete tree at that commit, same as
the other four candidates carry, so this folder can be deployed from directly
without pulling the branch first.

**No `flosc.zip` here** — the Captain builds those with `./flosc-ship.sh`. Build
from either copy; they are identical.

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
