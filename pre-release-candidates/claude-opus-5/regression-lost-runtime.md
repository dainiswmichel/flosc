# Lost runtime inventory — v87 → v88, and everything downstream

Measured 21 September 2026 in the build container, from the committed candidate
trees in this repository. No claim here is inferred; each one is a file listing
or a grep.

## What happened, in order

| candidate | agent | date | files | starter packs |
|---|---|---|---|---|
| v68 `codex-gpt5` | Codex | 15 Sep | 297 | present |
| v83 `opencode-big-pickle` | opencode | 17 Sep | 296 | present |
| **v87 `grok-4-6`** | Grok 4.6 | 18 Sep | **303** | **present — last full tree** |
| **v88 `dwm-local`** | Dainis W. Michel | 18 Sep | **231** | **gone** |
| v89.1 `dwm-local` | Dainis W. Michel | 21 Sep | 226 | gone |
| v90.2 `claude-opus-5` | Claude Opus 5 | 21 Sep | 226 | gone |
| v91 `gpt-5-6-sol` | GPT-5.6-sol | 21 Sep | 226 | gone |

`pre-release-candidates/dwm-local/` was created at v88 and has never contained a
`starter-packs/` directory — `git log --diff-filter=D` finds no commit that
deleted one from it. The files were already absent from the tree that was
exported into it.

v90.2 and v91 are both edits on top of v89.1: v90.2 changes 12 files, v91
changes 11. Neither removed anything. `diff -rq` between v89.1 and v91 reports
11 differing files and no removals; between v89.1 and v90.2, 12.

Git does record deletions under agent names — `d3d2aa7` (v90, Claude Opus 5),
`84f26d0` (v89.2, GPT-5.6-sol), `2588d5d` (opencode). Each of those is a
candidate folder being re-seeded from v89.1, so git compares the new 226-file
tree against whatever that folder held before and records the difference as
deletions *inside that folder*. They are a consequence of the v88 loss, not its
cause.

## The removal was complete on both sides

No candidate at 226 files carries a dangling `require_once`. The files went, and
so did every reference to them:

- `flosc.php` lost the five `includes/ai/*` requires, the
  `includes/da1/class-flosc-da1-catalogs.php` require, the
  `includes/starter-packs/class-flosc-starter-packs.php` require, and the
  `includes/flosc-request.php` / `includes/flosc-accessors.php` requires.
- `admin/settings.php` lost the `'starter-packs' === $flosc_active_tab` branch
  that included `admin/starter-packs.php`.
- `grep -rniE 'starter.?pack|FLOSC_Starter_Packs|flosc-model-catalog|da1-catalogs'`
  over v89.1 returns one incidental hit: the string `'Visitor Starter Pack'` in
  `admin/autoprompts.php:535`.

This is why nothing caught it. The plugin does not fatal on activation, no file
is orphaned, and no static checker has an opinion about a feature that is simply
not there. `php -l`, WPCS, Plugin Check and the zip checksum were all working
correctly and all of them were silent.

## What is actually missing

Compared name-normalised against v87, so that the `class-flosc-*` → `class-*`
renames between v87 and v89.1 are not counted as losses. Thirty runtime entries
plus the twenty-file payload.

### Subsystems removed whole

```
admin/starter-packs.php
includes/starter-packs/class-flosc-starter-packs.php
includes/ai/flosc-model-catalog.php
includes/ai/flosc-model-parameters.php
includes/ai/flosc-provider-identity.php
includes/ai/flosc-provider-keys.php
includes/ai/flosc-provider-profiles.php
```

### Single files removed

```
includes/da1/class-flosc-da1-catalogs.php
includes/sso/flosc-sso-accessor.php
includes/class-flosc-framework.php
includes/flosc-accessors.php
includes/flosc-request.php
admin/docs/part3-ref-personality-profile.php
```

### Shipped payload removed (20 files, 956K)

```
starter-packs/wordpress-content-membership/   pack.json content.json flow_ivr.md
                                              README.txt 100-content-items.xml
starter-packs/da1-catalog-sales/              pack.json content.json flow_ivr.md
                                              README.txt catalog.tsv UberManual.pdf
starter-packs/membership-craft/               pack.json content.json flow_ivr.md
starter-packs/vegan-latvian-kitchen/          pack.json content.json flow_ivr.md
                                              catalog.tsv vegan-latvian-recipes.xml
                                              vegan-latvian-kitchen-cookbook.pdf
```

`starter-packs/` is in neither `.distignore` nor the `DENY_PATTERNS` array in
`build-dist-zip.sh`. It was always meant to ship.

### Not a loss

- `includes/class-rag-chat-handler.php` — a deprecated shim; its own header
  points at `includes/class-flosc-rag-chat-handler.php`, which is present.
- `admin/docs/ref-admin-skeleton.php`, `ref-core-skeleton.php` — present as
  `ref_admin_skeleton.php` / `ref_core_skeleton.php`.
- `includes/quiz-types/class-flosc-*` — all present under shorter names.
- 46 `tests/` files and `handoff*.md` — excluded from the artifact by
  `.distignore` and `DENY_PATTERNS` by design.
- `includes/flosc-content-sanitizers.php` — a separate, older matter. It exists
  only in v68 and was already gone by v83 (17 Sep), before this regression, and
  nothing in v87 references it.

## Donor question

The newest tree in this repository carrying the full runtime is **v87
(`grok-4-6`, 18 Sep)**. All thirteen donor PHP files parse clean on PHP 8.4.

LOCAL may hold a newer copy. If it does, LOCAL is the donor and v87 is only the
fallback — v87 predates every WordPress.org repair made between 18 and 21
September, so the restored files will need the security and style work applied
to them regardless of which donor is used.
