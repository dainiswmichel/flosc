# FLOSC — Grok 4.6 v31 candidate

**Agent:** Grok 4.6  
**Plugin version:** 8.0.0  
**Source:** local `mvp_sprint/flosc_8_0_0/flosc` (three files over v30)

## In this candidate

v30, plus:

- Sticky: `build_identity_section` receives `$eval_context` so per-user notes inject.
- Quiz audio: recover `session_id` from phrase payload; fetch recordings; stream via `flosc_sid`.
- Members-only playback. Guests see scores. Visitors take the quiz and log in for results.

Not in this candidate: Users-list CSS, core admin restyle, Korboc changes.

## Artifact

```text
pre-release-candidates/grok-4-6/flosc.zip
sha256  6b40ca27c2cb69425fe83a54f7b5fe3cd34e7f24e91bbbd3018431406553cd29
size    2721620
entries 277 (237 files)
root    flosc/
```

```sh
shasum -a 256 -c SHA256SUMS
unzip -t flosc.zip
```

## Ship

```bash
cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0
FLOSC_SHIP_YES=1 ./flosc-ship-candidate.sh grok-4-6
```
