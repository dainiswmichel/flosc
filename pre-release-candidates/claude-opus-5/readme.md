# FLOSC — Claude Opus 5 candidate

A complete, deployable FLOSC tree and its built artifact, for testing before
the WordPress.org resubmission.

    Plugin version    8.0.0
    Requires          WordPress 7.0.4 · PHP 7.4
    Artifact          flosc.zip — 277 files, 2.6 MB
    Checksum          sha256sums

The plugin version is 8.0.0 and does not move: this is a resubmission, not a
release. The candidate iteration in `build-manifest.json` counts rebuilds of
this folder and is a different number.

## Contents

    flosc.zip                      the built artifact
    sha256sums                     its checksum
    flosc-by-claude-opus-5/flosc/  the same code as a plain tree
    build-manifest.json            what was built, from where, and what passed

The zip and the tree are the same code. Either can be deployed.

## Deploy

From the artifact:

    curl -L -o flosc.zip \
      https://github.com/dainiswmichel/flosc/raw/main/pre-release-candidates/claude-opus-5/flosc.zip
    sha256sum -c sha256sums
    unzip -q -o flosc.zip -d <wp-content/plugins>

From the tree:

    rsync -a flosc-by-claude-opus-5/flosc/ <wp-content/plugins>/flosc/

Never with `--delete`. No database migration; nothing to activate.

## What is in this build

The DA1 AI Personality Designer, rebuilt on one idea: **a wellspring is an
aspect, a category is a group of aspects, and both are the same card.** Drop an
aspect onto a card and that card becomes the heading it sits under, keeping its
density, gain, binding, hue, star and trajectory.

The thirteen soul.md headings are also the aspect palette's shelves — one list,
not two, so a card waits on the shelf it will be written under.

| Density | Heading |
|---:|---|
| 6 | Identity and Role |
| 12 | Philosophy and Values |
| 18 | Boundaries and Prohibitions |
| 24 | Knowledge, Doubt and Correction |
| 30 | Opinions and Preferences |
| 40 | Tone and Communication Style |
| 48 | Stance Toward the Human |
| 56 | Behavior in Ambiguity |
| 62 | Adaptation |
| 68 | Resourcefulness |
| 74 | Decisions including Infrequent Cases |
| 84 | Banned Words and Fillers to Avoid |
| 94 | Output and Delivery |

**Nested density.** A colon separates levels, a period separates decimals. An
aspect at 16 inside a card at 95 reads `95:016`; one at 100 reads `95:100`.
Any depth. Derived, never stored — drag it out and it is 16 again. Equal
densities sort alphabetically.

**Trajectories** are WordPress posts in the `trajectory` category, managed on
the Trajectories tab. An aspect's trajectory can name one by id: `412`,
`?post=412`, or its permalink.

## Verify a deploy

    grep -m1 "^ \* Version:" <plugins>/flosc/flosc.php     # 8.0.0
    find <plugins>/flosc -name '*.php' -exec php -l {} \; | grep -v "No syntax errors"

## Verify the source

    node --check assets/js/flosc-personality-builder.js
    node tests/check_density_nesting.js
    find . -name '*.php' -not -path './.git/*' -exec php -l {} \;
    for f in tests/test_*.php tests/check_*.php; do php "$f" || echo "FAIL $f"; done

`tests/check_packaging.php` gates the version headers, the artifact's
exclusions, and that nothing in this folder can reach a build.

## Where the code is

    branch  claude/ready-to-help-jsw2li
    tree    the plugin at the repository root on that branch

https://github.com/dainiswmichel/flosc/tree/claude/ready-to-help-jsw2li

This folder is a snapshot of that branch. When the branch moves, this does not.
Deploy from one or the other, never rsync one over a site built from the other.

`pre-release-candidates/` is excluded from the artifact by `.distignore` and by
the build's hard deny list, so nothing here can ship.

## Not to be changed without a named failure

- the page-wide Save, its label, and the last-save MTS line
- the Step 2b Model Tuning localised Save and its state machine
- the model catalog and parameter surfaces
- the full-page to companion handoff
- prohibitions living in two places by design — Boundaries for what the
  personality must never do, Banned Words for what it must never say
