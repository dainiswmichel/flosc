# Claude Code Collaboration Guide

This file tells Claude Code (in VSCode or web) how to work with this project.

---

## The Process

### Every session, in this order:

1. **Write the code** — make the changes in the repo files
2. **Show the code in chat** — paste every new or changed method directly in the conversation so the human can read it without opening GitHub
3. **Push to a branch** — immediately after writing, not at the end of the session
4. **Say the branch name** — clearly, so it can be pulled in VSCode

### Branch naming:
```
claude/short-description-sessionID
```
Example: `claude/fix-free-lesson-cards-session_01GajGrP8wJS5ycBG2K16vrM`

### To push:
```bash
git checkout -b claude/description-sessionID
git add <changed files>
git commit -m "Short description of what and why"
git push -u origin claude/description-sessionID
```

---

## What the Human Does

- Reviews the code shown in chat
- Pulls the branch in VSCode when ready to test:
  ```
  git fetch origin
  git checkout claude/description-sessionID
  ```
- Merges to main when satisfied

---

## Rules for Claude

**Always do:**
- Show changed code in chat — not a summary, the actual code
- Push to a branch before saying "done"
- State the branch name explicitly

**Never do:**
- Say "deploy the files" without first having pushed to GitHub
- Describe what the code does instead of showing it
- Wait until the end of a session to commit and push
- Make the human ask where the code is

---

## Project Context

- **Plugin:** FLOSC — WordPress plugin at `flosc.php`
- **Main JS:** `assets/js/flosc-app.js`
- **Layout CSS:** `assets/css/flosc-layout.css`
- **Theme CSS:** `assets/css/flosc-theme.css`
- **IVR config:** `ai_configuration_files/lesaep_ivr.md`
- **Remote:** `https://github.com/dainiswmichel/flosc`
- **Live site:** lesaep.com (WordPress, hosted externally — not on this machine)

Changes reach the live site when the human pulls and deploys from VSCode.
