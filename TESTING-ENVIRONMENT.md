# The FLOSC testing environment

Owner: Dainis W. Michel. Runs on his machine. No agent is in the reporting path.

Location: `/Users/dainismichel/2026/flosc_project_folder/`

```
flosc_project_folder/
  .flosc-mirror/           hidden sparse git checkout — the only thing that talks to GitHub
  pre-release-candidates/  five candidate trees, flat, refreshed by rsync
  testing-bench.sh         the runner
  testing-logs/            one dated transcript per run, unedited
```

## Why it exists

`tests/check_packaging.php` asserted `Requires at least: 7.0.4` as correct — the
exact value WordPress.org returns as an ERROR. It printed `0 failing gates`
through four review rounds. The commit that introduced it is `864d765`, "Home
run candidate v14 coded by Claude Opus 5".

A gate written by an agent, taught to agree with the code instead of with the
standard, and then trusted by every agent after. The answer to that is not a
better gate written by an agent. It is real tools, run by the Captain, on his
hardware, printing their own bytes.

## Refresh and run

One block. Pulls the candidates and the runner:

```
cd /Users/dainismichel/2026/flosc_project_folder/.flosc-mirror && git pull --depth 1 origin main && cd .. && rsync -a --delete .flosc-mirror/pre-release-candidates/ pre-release-candidates/ && git -C .flosc-mirror show origin/main:testing-bench.sh > testing-bench.sh && chmod +x testing-bench.sh && echo INSTALLED
```

Then:

```
./testing-bench.sh --steps    runs NOTHING; prints every check as a numbered
                              command to paste one at a time
./testing-bench.sh            the same checks, one table, all five candidates
./testing-bench.sh --stan     adds PHPStan
./testing-bench.sh --plugincheck   adds Plugin Check (UNVERIFIED — see below)
./testing-bench.sh --all      everything
./testing-bench.sh --no-log   do not write a transcript
```

`--steps` is the primary one. The table has the runner's own layout and summing
in it. The step list has nothing in it but the tools.

## What is installed, and the commands that installed it

```
composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer global require squizlabs/php_codesniffer wp-coding-standards/wpcs phpcompatibility/phpcompatibility-wp
composer global require phpstan/phpstan szepeviktor/phpstan-wordpress php-stubs/wordpress-stubs
```

Verified present: PHPCS 3.13.6, WPCS 3.4.0, PHPCompatibilityWP, PHPStan 2.2.14.

The `allow-plugins` line is the one that unblocks the install. Without it
composer silently refuses to register the WordPress standard and `phpcs
--standard=WordPress` fails with exit 3.

## Whose code is whose

| check | who wrote the thing that decides |
|---|---|
| `sec` `php74` `enq` `i18n` `strict` | PHPCS + WordPress Coding Standards. **Real tool.** |
| `stan` | PHPStan. **Real tool.** |
| `pcheck` | Plugin Check. **WordPress.org's own tool.** |
| `hdr` `inline` | grep and a regex |
| `rules` | `tests/check_wporg_rules.php` — **written by Claude Opus 5.** Encodes the T8/T10/T11/T12/T13 review emails. Has produced four known classes of false positive. Read its findings. Never sweep them. |

## Verified and unverified

**Verified** — run, output seen:
PHPCS security sniffs, PHPCompatibilityWP at 7.4, EnqueuedResources, I18n,
StrictInArray, the header grep, the inline-style grep, the rules gate.

**Unverified** — never completed a run anywhere:
`--plugincheck`. The Playground blueprint and the `npx @wp-playground/cli`
invocation are written, but the container they were written in returns `000`
for `playground.wordpress.net`, `downloads.wordpress.org` and
`api.wordpress.org`. The first real evidence of whether it works will come from
this machine. The block prints `UNVERIFIED` above its own output.

**Not installed, not run:** PHPStan is installed but has never been run against
any candidate.

## The one rule

A check that did not run prints `NORUN` and makes the exit code 1. It never
prints `0`.

This is not theoretical. The first version of `testing-bench.sh`, run from a
directory where PHPCS was installed somewhere else, printed

```
  phpcs : NOT INSTALLED
  opencode-big-pickle   sec 0
```

for a tree that has 18 security errors. Same failure as `864d765`, new file.
It now prints `NORUN` in every PHPCS column and exits 1.

PHPCS exit codes are the authority, not the report text — a clean run prints
nothing at all. `0` clean, `1` or `2` findings, `3` or more did not run.

## Logs

Every run writes `testing-logs/<MTS>.txt` — the full transcript, unedited,
before anyone can summarise it. Each one records the timestamp, the tool
versions actually found, and the git commit the candidates were at.

A number without a commit and a date is not evidence. The log is what makes a
claim checkable a month later.

## Changelog of this environment

- **2026-09-15** — `testing-bench.sh` created. PHPCS + WPCS +
  PHPCompatibilityWP verified working. `--steps` added so every check can be
  run by hand. Transcript logging added. Plugin Check path written, unverified.
