# Session Failure Analysis — 2026-03-20
## IPA Quiz Recording Regression (Android)

### What was asked
Implement a plan to fix known bugs in the IPA quiz recording flow introduced between March 16–20.

### What went wrong

**Commit 1 (086fcfb)** — Removed `btn.disabled = true` before `getUserMedia` based on the reasoning that it caused an Android race condition (queued touch events). This caused `getUserMedia` to hang on Android because the browser needs a DOM mutation to preserve the user gesture context. Result: complete freeze on Record tap.

**Commit 2 (853a8f1)** — Added `isAcquiringMic` state and 8-second `getUserMedia` timeout. Correct architecture but still didn't fix the freeze because `getUserMedia` was still being called as the first mic access, which triggers the Android permission dialog — which may not appear if the gesture context is not properly established.

**Commit 3 (53f8033)** — Added `checkMicAndStartQuiz` probe to establish mic permission at consent time. Correct direction. But immediately stopped the probe stream after granting (`stream.getTracks().forEach(t => t.stop())`), then called `getUserMedia` again at Record time. Android Chrome hangs on the second `getUserMedia` call after hardware release.

**Commit 4 (35fe778)** — Kept probe stream alive in `this._probeStream`, reused it in `toggleIpaRecording` for phrase 1. Still broke on phrase 2+ because the stream was stopped at the end of phrase 1's Stop branch, causing the same hardware re-acquire hang for phrase 2.

**Commit 5 (de3e656)** — Current state. Stream kept alive across all phrases. Stop branch no longer stops stream tracks. Stream released in `showIpaQuizSummary` after all phrases complete. Reuse chain: probe stream → phrase 1 → phrase 2 → ... → phrase N → release.

### Root cause of the regression
The original working code (before March 16 changes) never had this Android hang because the mic was probed at consent time and the stream was reused. The plan I was given said `checkMicAndStartQuiz` "is actually fine" with no probe — that was incorrect. The probe is essential on Android Chrome.

### Time and tokens lost
Session ran approximately 3 hours on this single issue. Multiple incorrect diagnoses compounded the problem before arriving at the correct fix.

### What should have been done
1. Read the March 16 working code before touching anything
2. Preserve the `checkMicAndStartQuiz` probe
3. Keep the stream open across all phrases from the start
