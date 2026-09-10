# FLOSC — Grok 4.6 v30 candidate

**Agent:** Grok 4.6  
**Plugin version:** 8.0.0  
**Source:** local `mvp_sprint/flosc_8_0_0/flosc`

## In this candidate

- FAQ External Services item 17 (oEmbed).
- MagicLink small set (rate-limit, two-places, hashed transients, kind fallback). Default off.
- Sticky for Users: enable is a third row under Sticky aspects on the AI tab, per attached personality. User profile has the note textarea only; greyed out until a personality enables it.
- Compiled profile reserved slot: `# 1 Personalization` (blank until runtime fills it). Density 0 left free.

## Artifact

```text
pre-release-candidates/grok-4-6/flosc.zip
sha256  802aaaee06c4de3a42dd9af7452a4c6721b84ae279f516b152782d6b2f3d6d96
size    2719009
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
