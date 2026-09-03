# Grok 4.6 FLOSC deployment candidate

**Agent:** Grok 4.6 (xAI / Grok Build)  
**Candidate slug:** `grok-4-6`  
**Status:** Candidate build — WordPress runtime testing is deferred to the authorized remote test site.

This is a complete, independently deployable FLOSC plugin created by **Grok 4.6** from baseline commit `03f4b926c5502b7d2af0136fc1bac8359286b9f8`.

```text
pre-release-candidates/grok-4-6/
├── README.md
├── build-manifest.json
├── SHA256SUMS
├── flosc.zip
└── flosc-by-grok-4-6/
    └── flosc/
        └── complete independent plugin source
```

The artifact is always named **`flosc.zip`**. Its only top-level directory is exactly `flosc/`, so WordPress installs it at `wp-content/plugins/flosc/`.

Canonical source at `mvp_sprint/flosc_8_0_0/flosc/` was not modified. Other candidates were not modified.

## What this candidate changes

### Designer → library → chat, for any personality you invent

1. **All Flows → Personalities → Add** creates an empty row.
2. **Design** is available on every row, not only the one currently attached. The URL carries `?persona=<id>`, so the workshop boots *that* genome.
3. **Save** writes genome (`workshop_json`) + the designer’s compiled `ai_base_prompt` + `profile_hash` in one shot. An authored/designer profile is never regenerated from the short fields.
4. **Attach** on This flow writes only `personality_library_id`. Runtime re-reads the library row every turn.
5. Follow-up turns send the **complete current compiled profile**. They no longer say “same person as the opening turn,” so an admin switch (or a brand-new designed voice) takes effect on the next visitor reply.

Craft a radically different personality in the designer, save, attach, chat. Turn 2 still is that person.

### Distinct voices without deleting FLOSC sales technique

BubblyBetty and DadJokeDan defaults are restored to concise character profiles. The shared sales/truth/journey contract is a separate chatpack layer sent once per turn for every personality, including one you just designed. Sampling parameters and designer cards are unchanged.

### Honest dispatch

Production dispatch returns a structured result. RAG failure falls through to ordinary AI. A provider `WP_Error` is `fallback`, never a successful personality line. Visitors get safe copy; admins get the real error via `flosc_ai_dispatch_failed`.

### Live Chemicloud rows

If saved BubblyBetty / DadJokeDan still match a known shipped (never-edited) hash, they are re-seeded to the concise restored profiles. Any personality you designed or edited is left untouched.

Starter Packs still attach by personality ID only.

## Verification boundary

Static and packaging checks are recorded in `build-manifest.json`. Installation, activation, live provider calls, WordPress Plugin Check, Starter Pack lifecycle, database persistence, designer-crafted personality, and Betty → Dan → Betty browser tests are marked **DEFERRED — AUTHORIZED REMOTE TEST REQUIRED**. They are not represented as passed.

## Ship to Chemicloud

```bash
cd /Users/dainismichel/2026/flosc_project_folder/mvp_sprint/flosc_8_0_0
./flosc-ship-candidate.sh grok-4-6
```
