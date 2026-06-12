# FLOSC — Cross-Assistant Tech Handoff

Purpose: get every coding assistant (Claude Code, vscodeclaude/Copilot, human)
on the same page after the project folder was renamed and chat context was lost.
Snapshot date: 2026-06-09.

---

## 1. Canonical paths (READ FIRST)

The workspace contains **11 nested git repositories**. Past mistakes came from
operating on the wrong one. There is exactly ONE active plugin repo:

    /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc

- The top folder was renamed: `flosc/` → `flosc_project_folder/`. Any path that
  still says `/2026/flosc/...` is STALE — update it.
- Other `.git` dirs (the outer `flosc_project_folder/` wrapper, `lesaep/`,
  `flosc_development_archives/flosc_v8_0_*`, `quiz_development/*`, `dev_cool/*`,
  any `*.pre-restore-*` snapshot) are NOT the plugin. Do not commit plugin work there.

**Before any git command or claim, verify:**

    cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc
    git rev-parse --show-toplevel   # must print the path above

---

## 2. Current git state (verified 2026-06-09)

- Repo: the path above. Branch: `main`. Remote: `origin` →
  `https://github.com/dainiswmichel/flosc.git`.
- `main` == `origin/main` (`+0 -0`). Nothing to push at HEAD.
- HEAD: `298bf5e` — "Add professional push guardrails with allowlist enforcement"
- Recent history:
  - `298bf5e` Add professional push guardrails with allowlist enforcement
  - `3184f6f` Fix DB-to-IVR export to include all messages across stale phase maps
  - `42a8d1c` Stabilize IVR tools with unified read/write path handling
  - `b0392da` Repair IVR path resolution for diagnostics and DB-to-file sync
- Tracked files at HEAD: 24,804.

### Uncommitted working tree — UNRESOLVED (do not blindly stage)

There are **~4,010 uncommitted entries**. This is a large, in-progress
repo-cleanup reorganization that has never been committed:
- `.gitignore` rewritten (clean plugin-focused ignore + secrets block).
- ~3,970 unstaged DELETIONS of non-plugin cruft: `mvp_sprint/`,
  `flosc_development_archives/`, `latvijas_kori/`, `zip-files/`,
  `da1ni5_personal_profitability/`, `flosc_development_worknotes/`, `.claude/`, …
- ~36 UNTRACKED entries that are the actual plugin files: `flosc.php`,
  `readme.txt`, `uninstall.php`, `admin/*.php`, `includes/`, `assets/`,
  `ai_configuration_files/`, `sample-data/`.

**Contradiction to resolve:** commit `298bf5e` ADDED `.githooks/pre-push` and
`.github/push-allowlist.txt`, but the uncommitted reorg shows BOTH as deleted.
Committing the reorg as-is would remove the push guardrails just added. Decide
intentionally.

---

## 3. The one open decision (blocks all working-tree git ops)

Is the canonical state:
- **A.** the big 24,804-file tree at `298bf5e` (= origin/main) — reorg is scratch,
  restore working tree to HEAD; or
- **B.** the plugin-only reorganization — supersede with a reviewed commit on a
  branch that removes cruft and tracks the plugin files (and consciously decides
  whether to keep the new push-guardrail files).

Do not `git add -A`, `commit`, `reset`, `clean`, or `checkout -- .` on the
working tree until this is decided AND a backup exists (see §5).

---

## 4. In-progress deliverable: shared coding procedure

A tool-agnostic git/GitHub procedure for all assistants:
- `agents.md` (canonical procedure) — NOT yet created.
- `claude.md` — one line `@agents.md` so Claude Code auto-loads it
  (Claude Code reads `claude.md`, not `agents.md`, but supports `@import`).
- `.distignore` — ALREADY EXISTS at repo root (created 2026-06-09). Should list
  `agents.md`, `claude.md`, dev docs/tests/build/CI so they ship in the repo but
  NOT in the WordPress.org end-user zip (Plugin Check flags dev-only files).
- Filenames lowercase by owner's instruction (caveat: lowercase `claude.md` may
  not auto-load on case-sensitive Linux/CI; fine on the owner's macOS).

---

## 5. Git discipline rules (apply every session)

1. Verify the repo (`git rev-parse --show-toplevel`) before any op or claim.
2. Read before asserting: `git status -b`, `git rev-parse HEAD`, `git log --oneline -5`.
   If two checks disagree, STOP — do not report a conclusion.
3. Back up before any destructive op:
   `git bundle create ../flosc-<stamp>.bundle --all` and
   `tar czf ../flosc-worktree-<stamp>.tar.gz .` Keep until result confirmed.
4. The human owns `git push` and deploys. Assistants supply exact commands; they
   do not push or force-push. A pre-push allowlist hook now exists (`298bf5e`).
5. No `reset --hard` / `clean` / `rm -rf` / `checkout -- .` without dry-run
   (`git clean -nd`) + backup + explicit human confirmation.
6. Deploy is rsync to ChemiCloud; NEVER `rsync --delete` without per-run
   confirmation (a prior `--delete` deleted production files).
7. Never commit secrets (`.env`, `*.pem`, `*.key`, `*client_secret*.json`, …);
   redact secret values when echoing config.
8. Code to the Plugin Check + PHPCS WPCS bar from the start.

---

## 6. TL;DR for the next assistant

- Work only in `…/flosc_project_folder/mvp_sprint/flosc_8_0_0/flosc`; verify it.
- HEAD is `298bf5e`; `main` == `origin/main`.
- A ~4,010-entry cleanup reorg is uncommitted and UNDECIDED — do not stage it
  until the owner picks option A or B and a backup is made.
- Create `agents.md` + `claude.md` shim; keep them out of the distributed zip via
  `.distignore`.
