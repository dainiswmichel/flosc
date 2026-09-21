# FLOSC — Project Rules for AI Coding Agents

These are durable project defaults. Dainis Michel's explicit instructions for
the current task determine its scope and may authorize candidate creation,
packaging, GitHub publication, testing, or deployment. Do not use this file to
refuse an action Dainis has explicitly requested. System safety requirements
and tool permission controls still apply.

## Project identity

- FLOSC means Freeline → Login → Offer → Sale → Content.
- It is a WordPress plugin for configurable conversational flows.
- Author: Dainis W. Michel. Plugin URI: `https://flosc.ai`.
- License: GPLv3 or later. Text domain and plugin slug: `flosc`.
- Main plugin file: `flosc.php`.
- Upstream repository: `https://github.com/dainiswmichel/flosc`, branch `main`.
- Use “flow,” never “funnel,” in new or edited project language.

## Compatibility and release metadata

- Preserve PHP 7.4 compatibility.
- Keep `Requires at least` identical in `flosc.php` and `readme.txt`.
- The current plugin version and stable tag are `8.0.0`. Do not change them
  unless Dainis explicitly requests a plugin-version change.
- Candidate numbers are release-candidate identifiers, not plugin versions.
- Use Michel Date Stamp format for project dates: `YYYY-MMm-DDd`.

## Source and candidate scope

- The local working source is normally
  `mvp_sprint/flosc_8_0_0/flosc`, unless the current task names another source.
- `pre-release-candidates/` is a valid destination when Dainis explicitly asks
  for candidate work. Modify only the named candidate folder and preserve every
  other candidate.
- Do not assume that candidate numbers represent one linear source lineage.
  Verify the source commit, tree, artifact hash, and actual diff before treating
  one candidate as the successor to another.
- An explicit task may designate a candidate tree as the source of truth for
  copying, testing, packaging, or deployment.

## Non-destructive work

- Preserve unrelated working-tree changes.
- Do not reset, restore, clean, force-push, rewrite history, or delete material
  unless Dainis explicitly requests that exact operation.
- Inspect exact targets before overwriting or transferring files.
- Do not use broad recursive deletion or an unresolved path.
- Do not invent deployment credentials, hosts, paths, or transfer methods.
  When a task names a deployment script, read and use that script as directed.
- Candidate creation, ZIP builds, GitHub pushes, and live deployment are allowed
  only when the current task explicitly requests them.
- A GitHub push is not a live-site deployment. Keep those actions distinct.

## WordPress engineering requirements

- Sanitize input and escape output at the appropriate boundaries.
- State-changing browser requests require authorization and CSRF protection
  appropriate to their architecture.
- Do not add fake WordPress nonces to bearer-token, HMAC, OAuth callback,
  magic-link, signed-capability, webhook, or equivalent protocol flows. Trace
  and verify their actual authentication design.
- Use `$wpdb->prepare()` for dynamic SQL. Retain direct/custom queries only when
  the architecture requires them and document the concrete reason narrowly.
- Do not introduce inline script or style blocks in PHP. Use enqueued assets and
  WordPress inline-asset APIs. Dynamic CSS custom properties in
  `admin/flosc-app.php` are the established narrow exception.
- Keep visual values in the established CSS-variable system.
- Do not add credentials, API keys, sandbox identifiers, or passwords to source.
- Do not add `error_log`, `var_dump`, `print_r`, or `console.log` debugging.
  Conditional project logging must use the established `flosc_log()` facility.
- Do not introduce process-execution functions.
- Do not remove compatibility shims or rename public classes, hooks, endpoints,
  option names, or files unless the task explicitly requires it and callers have
  been traced.
- Preserve IVR-driven behavior and administrator configuration. Do not replace
  settings-driven decisions with hardcoded product behavior.
- Sample data must remain clearly identified, realistic, customizable, and free
  of fabricated social proof.

## Coding standards and suppressions

- Write WordPress Coding Standards-compatible PHP.
- Preserve PHP string semantics when changing quotes or escapes.
- Do not run broad PHPCBF when the task asks for direct or narrow remediation.
- Do not use a PHPCS suppression as a substitute for a repair.
- When a proven false positive or architectural exception genuinely requires an
  annotation, scope it to the exact sniff and line/block and include a concrete
  reason after ` -- `. Obey stricter no-suppression instructions in the current
  task.
- Avoid unrelated refactors and formatting sweeps.

## Verification and honesty

- Never claim a check passed unless it was run after the final relevant change.
- Distinguish verified facts, reasoned conclusions, and unverified runtime
  behavior.
- Run `php -l` on every touched PHP file. For release candidates, lint the full
  shipping PHP tree.
- Run PHPCS with the standard and scope required by the current task.
- For release readiness, run PHPCompatibilityWP for PHP 7.4 and the repository
  release gate against the built ZIP.
- Official Plugin Check requires a booting WordPress installation. If it cannot
  run, report it as unverified rather than passed.
- Live behavior requires runtime testing. Static analysis, syntax checks, and
  scanner counts do not prove behavior.
- Report failed or unavailable checks directly. Do not hide them behind a
  summary or describe “zero fixable findings” as WordPress.org readiness.

Useful commands from the local plugin root:

```bash
find . -name '*.php' -type f -exec php -l {} \;
vendor/bin/phpcs -p . --standard=WordPress
vendor/bin/phpcs -p . --standard=PHPCompatibilityWP --extensions=php --runtime-set testVersion 7.4-7.4
PHPCS_VENDOR=/Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc/vendor \
  /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/wordpress-remediation-plan/flosc-gate.sh "$PWD"
```

## Packaging and deployment

- Build distributable ZIPs only with `./build-dist-zip.sh`; do not hand-build
  them or bypass its deny list.
- `flosc_documentation/` and `admin/docs/` are shipping plugin documentation,
  not development junk. Preserve them in release artifacts and deployments.
- Do not deploy AI-agent instructions or development tooling such as
  `AGENTS.md`, `agents.md`, `CLAUDE.md`, `.cursorrules`, or `phpcs.xml.dist`.
- Regenerate and verify `SHA256SUMS` after every ZIP rebuild.
- Confirm the ZIP itself contains the intended files and excludes development
  dependencies and prohibited paths.
- Before a requested live deployment, inspect the named deployment script,
  identify its destination and deletion behavior, create the requested rollback
  point, and verify transferred files afterward.
- Account for server-side caches such as OPcache using the method specified by
  the deployment task. CLI cache state does not prove web-server cache state.

## Working method

- Read enough surrounding code and callers to understand behavior before editing.
- For a behavior change, be able to state: user action → code path → result →
  visible outcome.
- Keep each change within the current task's declared scope. There is no fixed
  numerical change limit; use a size that can be reviewed and verified.
- When a prior approach fails, explain the concrete cause before trying a new
  approach.
- When blocked by missing authority or an external decision, stop and ask one
  precise question. Do not manufacture certainty or activity.
