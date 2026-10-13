# FLOSC Pre-release Candidate Rules

These rules govern every AI coding agent working anywhere under
`pre-release-candidates/`. They supplement the repository-wide rules. When a
session instruction conflicts with this file, this file wins unless Dainis W.
Michel explicitly changes the rule for that session.

## 1. Purpose and sources of truth

`pre-release-candidates/` is the review area for complete FLOSC home-run
candidates. It is not the canonical deployed plugin and it is not a temporary
workspace for arbitrary repository copies.

- GitHub `main/pre-release-candidates/` is the candidate source of record.
- The sole local mirror is
  `/Users/dainismichel/2026/flosc_project_folder/pre-release-candidates`.
- The canonical local plugin used for promotion, deployment, and further
  testing is
  `/Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc`.
- Publishing a candidate does not promote or deploy it.
- Only Dainis may select a winning candidate and authorize promotion or live
  deployment.

## 2. Operational coding states

These terms describe the agent's demonstrated behavior. They are not
expressions of confidence and cannot be established by an agent's promise.

### Trusted coding state

The agent is in a **trusted coding state** only while it is:

- coding properly within the exact authorized scope;
- following all repository and session instructions;
- preserving existing functionality and unrelated work;
- producing WPCS-compliant, PHP 7.4-compatible code;
- running the required verification and reporting its actual output;
- making only the related changes authorized for the iteration; and
- describing limitations, failures, and unverified behavior truthfully.

Past good work does not guarantee that the agent remains in a trusted coding
state. Ninety minutes or two hours of correct work does not authorize broader
changes, repository repair, deployment, or relaxed verification.

### Shitcoding state

The agent is in a **shitcoding state** when it delivers sloppy, incomplete,
unverified, misleading, non-compliant, or instruction-violating work. Examples
include:

- introducing or retaining WPCS or WordPress.org review violations;
- breaking or silently removing functionality;
- changing unrelated files or exceeding the stated scope;
- supplying a partial or stripped candidate when a complete candidate is
  required;
- skipping required checks while claiming completion or readiness;
- publishing stale source, ZIP, checksum, manifest, version, or commit data;
- hiding failures, inventing results, or making promises unsupported by
  evidence; or
- ignoring the directory, naming, versioning, or commit conventions.

### Destructacoding

**Destructacoding** is shitcoding that also destroys, overwrites, loses,
corrupts, or materially endangers working code, history, artifacts, data, or
environments. Examples include:

- deleting or replacing `.git`, branches, refs, reflogs, worktrees, or
  uncommitted work;
- using destructive Git commands or recursive filesystem deletion outside the
  exact authorized disposable target;
- overwriting the canonical plugin, another agent's candidate, or unrelated
  project files;
- creating duplicate repositories or `pre-release-candidates/` trees around
  the local drive;
- improvising a repository "repair" after an unexpected Git or filesystem
  state;
- deploying to live without explicit authorization; or
- turning a previously working user journey into a broken one while claiming
  it was preserved.

### Required response to state degradation

If the agent encounters evidence that it may have entered a shitcoding state or
destructacoding state, it must immediately:

1. Stop all writes, commits, pushes, copies, deletions, repairs, and deployments.
2. Preserve the current filesystem and Git state exactly as found.
3. Report the triggering event, commands already run, changed files, failed
   checks, and known consequences.
4. Wait for explicit instructions from Dainis before resuming.

The agent must never conceal the degradation, attempt an unrequested cleanup,
or use destructive actions to make the repository appear clean.

## 3. Supported candidate workflows

There are two valid ways to create a candidate. Both converge on GitHub before
evaluation or promotion.

### GitHub-only agent

1. Work only in the assigned `pre-release-candidates/<agent-id>/` directory.
2. Publish the complete candidate to GitHub using the authorized branch and
   commit workflow.
3. Do not claim that the candidate exists locally or was tested locally unless
   that actually occurred.
4. The candidate is pulled into the sole local mirror after publication.

### Local-coding agent

1. Work only in the assigned directory under the sole local candidate mirror.
2. Do not create another clone, worktree, backup tree, or candidate root.
3. Commit and push the complete candidate to GitHub for review.
4. Confirm that the GitHub commit contains the same source, ZIP, checksum, and
   manifest as the local candidate.

Neither workflow authorizes editing the canonical plugin or deploying live.

## 4. Required directory structure

Each agent owns one named directory:

```text
pre-release-candidates/
  AGENTS.md
  <agent-id>/
    README.md
    SHA256SUMS
    build-manifest.json
    flosc.zip
    flosc-by-<agent-id>/
      flosc/
        flosc.php
        readme.txt
        admin/
        assets/
        includes/
        ... complete reviewable plugin source ...
```

Additional verification reports are allowed inside the assigned agent
directory. The required source tree and ZIP are complete resubmission
candidates, never patches, stripped builds, or registration-disabled variants.

An agent must not:

- modify another agent's directory;
- create a nested `pre-release-candidates/` directory;
- create another `pre-release-candidates/` directory elsewhere;
- place `.git`, worktree administration data, dependency caches, or unrelated
  repository copies inside a candidate;
- copy the repository into itself; or
- change this policy without explicit authorization from Dainis.

If the expected path does not exist or the structure differs, stop and report
the discrepancy. Do not recreate, relocate, or repair it autonomously.

## 5. Candidate requirements

Every candidate must include:

- the complete reviewable plugin source;
- the exact distributable `flosc.zip` built from that source;
- `SHA256SUMS` identifying the ZIP;
- `build-manifest.json` identifying the source and artifact inventory;
- a README stating the baseline, exact scope, retained user journeys, tests
  actually run, failures, unverified items, commit, and ZIP SHA-256; and
- matching plugin-header and `readme.txt` compatibility metadata.

The public version and stable tag remain unchanged unless Dainis explicitly
requests a bump. Internal iteration names and commit subjects must follow the
exact convention specified for the iteration. The normal candidate subject is:

```text
Home run candidate <iteration> coded by <agent>.
```

Do not invent a new iteration, silently reuse one, or publish mismatched source
and artifact metadata.

## 6. Coding and verification requirements

- Trace the affected user journey before editing.
- Read the complete function, its callers, and at least 50 surrounding lines.
- Make no more than five related changes per iteration.
- Preserve registration, login, flow editing, IVR, SSO, offers, content access,
  and other existing journeys unless the authorized task explicitly changes
  one.
- Write WPCS-compliant WordPress code and PHP 7.4-compatible syntax from the
  outset.
- Sanitize input, escape output, verify nonces, check capabilities, prepare SQL,
  and avoid global behavior changes as required by the repository rules.
- Run PHP syntax checks, the full repository PHPCS rules, PHPCompatibilityWP,
  applicable regression tests, the release gate, and Plugin Check when its
  environment is available.
- A failed or unverified required check means the candidate is not verified for
  that check. Report that fact plainly.
- Never declare WordPress.org compliance, full remediation, no functionality
  loss, or submission readiness without evidence supporting that exact claim.

Verification reports must distinguish commands actually run from assumptions,
static inspection, prior-agent claims, and tests that could not run.

## 7. Git and filesystem boundaries

- Never delete, replace, rebuild, relocate, or "repair" `.git`.
- Never run `git reset --hard`, `git clean`, `git worktree remove`, recursive
  deletion, or an equivalent destructive operation on a non-disposable path.
- Never remove or overwrite work belonging to Dainis or another agent.
- Never treat a dirty worktree as permission to clean it.
- Never force-push or rewrite shared history unless Dainis explicitly requests
  that exact operation.
- Never make broad tree copies to simulate a merge.
- Never create local clones under `tmp/`, the canonical plugin, or another
  project directory merely to complete a candidate.
- Before committing, list every changed path and confirm that all are inside
  the authorized agent directory.
- Before pushing, pull or fetch as instructed and stop on divergence rather
  than resolving it destructively.

Unexpected Git state is a reporting condition, not a repair invitation.

## 8. Review, promotion, and deployment

GitHub is the review point for all candidates. A candidate remains quarantined
until Dainis evaluates its code, claims, artifact, and verification evidence.

Selection as the winning candidate requires an explicit statement from Dainis.
Promotion then imports the exact approved candidate, identified by Git commit
and ZIP SHA-256, into the canonical local plugin directory. Promotion must not
silently alter the reviewed artifact.

No candidate agent may infer permission to:

- promote its own candidate;
- overwrite the canonical plugin;
- build or replace a live package;
- access or change the live site; or
- deploy to live.

Each of those actions requires separate, explicit authorization.

## 9. Accountability

An agent's strong language, assurance, signature, or remediation statement is
not evidence. The diff, artifact identity, and recorded verification output are
the evidence.

If an agent cannot complete the authorized work without violating these rules,
it must stop and report the blocker. It must not convert uncertainty into
shitcoding or destructacoding.
