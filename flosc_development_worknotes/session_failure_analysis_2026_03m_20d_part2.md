# Session Failure Analysis — 2026-03-20 (Part 2)
## AudioContext Keep-Alive Regression

### What was asked
Fix getUserMedia freeze on Android Chrome (probe stream going inactive between consent and Record tap).

### What went wrong

**Commit 61c0153** — Added `AudioContext.createMediaStreamSource(probeStream)` connected to `createMediaStreamDestination()` to keep the probe stream's hardware awake on Android. This broke the quiz flow: instead of showing the quiz, the welcome message loaded again. Root cause: `createMediaStreamSource` attaches the stream to the AudioContext's audio processing graph. On Android Chrome, a MediaStream track connected to an AudioContext cannot also be consumed by a MediaRecorder — the stream has one active audio graph consumer at a time. This caused MediaRecorder creation or the recording flow to fail, which triggered an error path or state reset that re-showed the welcome message.

**Commit ce53ae2** — Replaced AudioContext approach with a muted `Audio` element (`new Audio(); audio.srcObject = stream; audio.muted = true; audio.play()`). Audio elements and MediaRecorder can consume the same MediaStream simultaneously without conflict. This is the correct keep-alive mechanism.

### Root cause of this regression
AudioContext and MediaRecorder are not compatible co-consumers of the same MediaStream on Android Chrome. Should have used the Audio element pattern from the start.

### What should have been done
Use `new Audio()` with `.srcObject = stream` and `.muted = true` for stream keep-alive. Never use `createMediaStreamSource` on a stream that will also be passed to MediaRecorder.
