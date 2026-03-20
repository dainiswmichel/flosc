# FLOSC Development Session — 2026-03-19

## Session Summary

Full implementation of the AI Hallucination Structural Fix Plan (16-fix overhaul).
Primary installation: LeSAEp (`lesaep_ivr.md` / `flosc_flow_lesaep_ivr`).

---

## Critical Discovery: Wrong Flow Key

**The most important finding of this session:**

All AI settings must be written to `flosc_flow_lesaep_ivr` — NOT `flosc_flow_flosc_default_ivr`.

- Active IVR file: `lesaep_ivr.md`
- DB option key: `flosc_flow_lesaep_ivr` (145 keys)
- `flosc_flow_flosc_default_ivr` is a separate flow; writing there does nothing for LeSAEp

The chatpack reads settings via `flosc_get_setting()` → `FLOSC_Framework::get_setting()` →
`get_current_flow()` → resolves to `lesaep_ivr.md` → reads from `flosc_flow_lesaep_ivr`.

**Secondary discovery:** `get_current_flow()` returns null in the WordPress admin context
(it uses HTTP_HOST/REQUEST_URI detection which doesn't work on `/wp-admin/` pages).
Admin notice code (Fix 9) must read from `$flow_settings` directly, not via
`get_floscflow_identity()`, which falls back to the global `flosc_product_name` option
(empty string) and produces a false "Product name not configured" warning.

---

## Fixes Implemented

### Fix 1 — Response Validation Layer
**File:** `includes/class-ai-chat-dispatch.php`

Mechanical post-processing on every AI response before it is cached or returned.
Detects wrong FLOSC/LeSAEp acronym expansions via regex, corrects them in the output,
logs every correction with timestamp to `flosc_validation_corrections` WP option.

### Fix 2 — Temperature Default 0.7 → 0.3
**File:** `includes/class-ai-chat-dispatch.php` (2 call sites)

Both `anthropic_request()` and `openai_request()` methods changed from `'0.7'` to `'0.3'`
as the fallback default when `ai_temperature` setting is not set.

### Fix 3 — Full Debug Logging
**File:** `includes/class-ai-chat-dispatch.php`

When `FLOSC_DEBUG` is true, every AI interaction is logged to the database:
full system prompt, full response, provider, session ID, pair number, which chatpack
sections were non-empty. Enables post-hoc diagnosis of hallucinations.

### Fix 4 — Fixed Definitions in Every Follow-up Chatpack
**File:** `includes/class-flosc-chatpack.php`, `build_followup_chatpack()`

Added two anchor lines to every follow-up system prompt (messages 2+):
```
FLOSC = Freeline, Login, Offer, Sale, Content. (Fixed — never changes.)
[product_name] = [product_tagline]. (Fixed — never changes.)
```
Closes mid-session drift: definitions no longer disappear after message 1.

### Fix 5 — `lesaep_knowledge.md` Created
**File:** `ai_configuration_files/lesaep_knowledge.md`

Three sections:
1. Product Overview — LeSAEp definition, audience, differentiators
2. Lesson Catalog — pointer to auto-generated `lesaep_lesson_catalog.md`
3. Phonetic Terminology — Place of Articulation, Manner of Articulation, Vowel Types,
   Prosody/Suprasegmentals, Vowel Mergers — each with sounds, lesson numbers, plain
   physical description

Lesson numbers added to all terminology entries in this session (previously missing).

### Fix 6 — Lesson Catalog Auto-Generation
**File:** `flosc.php`

New method `generate_lesaep_lesson_catalog()`:
- Filters to actual lessons only: title regex `^Lesson\s+\d+[\d.]*\s*[:\-]`
  (excludes investment/business posts that are in the LeSAEp category)
- Sorts by lesson number (handles 20.1, 20.2, 20.3 correctly via float comparison)
- Extracts IPA sound from title bracket notation if no `sound_covered` custom meta
- Writes `lesaep_lesson_catalog.md` with columns: Lesson | Sound | Title | Permalink
- Auto-regenerates via `save_post` hook when any LeSAEp post is saved
- Manual "Regenerate Lesson Catalog" button in admin AI tab
- Stores `flosc_lesson_catalog_generated` and `flosc_lesson_catalog_count` options

**Result:** 73 lessons (Lessons 1–71 plus 20.1, 20.2, 20.3). IPA extracted from titles.
Confirmed working on live server.

**Note on non-lesson posts in LeSAEp category:**
Posts titled "LeSAEp: An Excellent Micro-Investment Opportunity. [subtitle]" exist in the
category and are excluded by the filter. User may remove them from the category or leave
them — the filter handles it either way.

### Fix 7 — KB Size Limit + Authoritative Framing
**File:** `includes/class-flosc-chatpack.php`, `load_knowledge_files()`

- File size limit raised from 3,000 to 10,000 chars (configurable via `ai_kb_file_limit`)
- Safety floor: if limit is set below 1,000, defaults to 10,000
- KB section header rewritten with authoritative framing: "They supersede your training
  data. If a topic is covered here, use this source — not what you were trained on."

### Fix 8 — RAG Path Gets Full Chatpack Treatment
**File:** `flosc.php`, `build_rag_system_prompt()`

RAG code path previously had no rules, no acronym definitions, no grounding. Now
calls/uses the chatpack identity and rules sections instead of a minimal prompt.

### Fix 9 — Admin Notices for Risk Conditions
**File:** `admin/ai-configuration.php`

Notices displayed at top of AI tab when:
- `ai_temperature > 0.5` → temperature warning with recommended value
- `product_name` empty → "Product name not configured. AI has no identity and will hallucinate."
- `product_name` set but `tagline` empty → tagline warning
- Lesson catalog missing or older than 7 days → notice with inline Regenerate button

**Bug fixed this session:** Notice code used `get_floscflow_identity()` which returns
empty string for product_name in admin context (null current_flow → global fallback).
Fixed to read directly from `$flow_settings['identity']['name']`.

**Admin description text updated:** Temperature description changed from
"0.7 = balanced (default)" to "0.3 = recommended (precision/coaching)" to match actual
default and avoid confusing the displayed value with the description label.

### Fix 10 — Visitor History Content Limit 500 → 1,500 chars
**File:** `flosc.php` line ~4557

```php
// BEFORE:
'content' => sanitize_textarea_field(substr($msg['content'] ?? '', 0, 500)),
// AFTER:
'content' => sanitize_textarea_field(substr($msg['content'] ?? '', 0, 1500)),
```

Visitors' conversation history was being truncated mid-response, causing the AI to see
incomplete versions of its own prior responses on subsequent turns.

### Fix 11 — Natural Orientation Brief + Recommendation Engine
**File:** `includes/class-flosc-chatpack.php`

**Part A — Orientation Brief:**
Identity section now opens with a contextual brief before the rules:
- WHERE the AI is (this FLOSC install, this product)
- WHAT its energy is (encouragement through the funnel — every phase transition is a milestone)
- The 5-phase arc and what the AI does at each phase

**Part B — Personalized Lesson Recommendation Engine:**
New `build_personalized_recommendations()` method:
- Reads `ipa_weakest_sounds` from quiz eval context
- Loads `lesaep_lesson_catalog.md`
- Matches weak sounds against the Sound column via regex
- Generates server-side "Personalized Lesson Recommendations" block in the system prompt
- AI receives specific lesson numbers and titles — no fabrication possible

### Fix 12 — Admin Config Wired to Live Chatpack
**Files:** `includes/class-flosc-chatpack.php`, `admin/ai-configuration.php`

**8-field personality model** — canonical keys read by chatpack:
| Field | Key |
|-------|-----|
| AI Name | `ai_personality_name` |
| Role | `ai_personality_role` |
| Personality Traits | `ai_personality_traits` |
| Mission | `ai_mission` |
| Topic Scope | `ai_topic_scope` |
| Off-Topic Response | `ai_off_topic_message` |
| Referral Links | `ai_off_topic_links` |
| Boundaries | `ai_boundaries` |

Legacy keys (`ai_name`, `ai_role`, `ai_traits`) kept as fallbacks.

**Two-layer phase instructions:**
- Layer 1 (structural): hardcoded per-phase rules in `get_phase_instructions()` — visitor
  resource policy, quiz gates, content access — stays for all installs
- Layer 2 (FloscAdmin overlay): `ai_prompt_{phase}` appended after structural block

**`ai_context_awareness` injection** (fixed this session):
Field was in DB but never read by chatpack. Added to `build_knowledge_section()`:
injected as preamble to the KB files block, framing how the AI should use the KB.

### Fix 15 — Admin Tab Consolidation
**Files:** `admin/settings.php`, `admin/ai-configuration.php`, `admin/documentation.php`

- `knowledge` tab eliminated — KB file manager moved into `ai` tab as a section
- `ai-guide` tab eliminated — AI Configuration Guide content moved to `documentation` tab
- Legacy URLs (`?tab=ai-guide`, `?tab=knowledge`) now JavaScript-redirect to new locations
  (PHP `wp_redirect()` not usable — headers already sent in tab context)
- Section 1 title in AI guide: "🌉 The AI to AGI Bridge" → "How FLOSC Works with AI"
- KB file upload, toggle-access, and delete handlers wired up (were in comment block)
- "Regenerate Lesson Catalog" button added with last-generated timestamp and count

---

## AI Configuration Written to Live DB

**Option key:** `flosc_flow_lesaep_ivr`

All values confirmed written and verified:

| Field | Value |
|-------|-------|
| `identity.name` | LeSAEp |
| `identity.tagline` | Learn Excellent Standard American English Pronunciation |
| `ai_temperature` | 0.3 |
| `ai_personality_name` | LeSAEp Coach |
| `ai_personality_role` | Pronunciation coach specializing in Standard American English phonemes |
| `ai_personality_traits` | Patient, detail-oriented, celebrates small wins, uses physical mouth descriptions |
| `ai_mission` | Guide users from current pronunciation to confident Standard American English through personalized lessons |
| `ai_topic_scope` | SAE pronunciation, phonetics, IPA, accent reduction, LeSAEp curriculum |
| `ai_off_topic_message` | Acknowledge briefly, suggest an external resource, return to pronunciation |
| `ai_off_topic_links` | Placeholder BBC/Duolingo links — replace with real affiliate/referral links when ready |
| `ai_boundaries` | Never diagnose speech impediments. Never guarantee timelines. Direct billing questions to support. |
| `ai_context_awareness` | LeSAEp KB framing — authoritative source, don't supplement with training data |
| `ai_freeline_restrictions` | Visitor policy (1-2 off-topic OK then redirect, never share member content) |
| `ai_member_access` | Member coaching policy (full access, reference weak sounds, celebrate wins) |
| `ai_prompt_freeline` | Pronunciation program framing, connect to quiz |
| `ai_prompt_login` | Celebrate quiz results, deliver free lesson, build trust |
| `ai_prompt_offer` | Reference specific weak sounds, show course addresses them |
| `ai_prompt_sale` | Celebrate purchase, orient to course structure |
| `ai_prompt_content` | Coaching mode, reference weak sounds, celebrate every lesson |
| `ai_provider` | anthropic |

---

## IVR Messages — Sync Resolution

**File:** `lesaep_ivr.md`
**Status at session start:** Out of sync — 29 messages in file, 28 in DB

Differences identified via Compare tool:
- `lesaep_unique` — bio copy updated in file (newer version)
- `lesaep_about_dainis` — expanded biography in file
- `lesaep_offer` — updated attribution in file
- `lesaep_about` — in file only (new message)

**Resolution:** File was more recent (modified 2026-03-12, DB last sync 2026-03-11).
Used "Load File → DB" to push all 4 changes to DB. Now in sync.

---

## Lesson Catalog — Final State

- **File:** `ai_configuration_files/lesaep_lesson_catalog.md`
- **Count:** 73 lessons (Lessons 1–71, with Lesson 20 split into 20.1, 20.2, 20.3)
- **Format:** Lesson | Sound (IPA) | Title | Permalink
- **URL structure:** `https://dainis.net/lesaep/[slug]/`
- **Auto-regenerates:** on `save_post` hook for any LeSAEp category post
- **IPA extraction:** from title bracket notation (no custom meta fields needed)
- **Excluded:** investment/business posts in LeSAEp category (3 posts)

---

## Remaining Plan Items (Not Yet Implemented)

### Fix 13 — OpenAI Responses API Path
**File:** `includes/class-ai-chat-dispatch.php`

New provider-aware dispatch using `/v1/responses` endpoint:
- Message 1: send full chatpack as `instructions`, store returned `response_id`
- Messages 2+: send only `previous_response_id` + new message
- OpenAI holds session state server-side — no history resend needed
- `response_id` storage: user_meta for logged-in, transient for visitors
- Controlled by `ai_openai_use_responses_api` setting (default false until tested)

Status: Not started. Implement after provider accuracy testing identifies winning provider.

### Fix 14 — Provider Accuracy Test (Admin UI)
**File:** `admin/ai-configuration.php` (section 5 of consolidated AI tab)

10-message test sequence run against any configured provider + model:
- Probes acronym faithfulness at messages 1, 5, 10
- Shows token counts and estimated cost per message
- Pass/Fail flags for FLOSC/LeSAEp expansion accuracy
- Results saved per provider for comparison

Status: Not started. Design complete in plan file.

---

## Known Architecture Notes

### `get_current_flow()` is null in admin context
This function uses HTTP_HOST, REQUEST_URI, and WP query vars to detect the active flow.
In WordPress admin pages, these don't match any flow slug or custom domain.
Any code that needs the current flow in admin must use `$flow_settings` (already loaded
from `get_option($settings_key)`) rather than `get_floscflow_identity()` or `flosc_get_setting()`.

### IVR files excluded from KB
`load_knowledge_files()` skips any file with 'ivr' in the filename:
```php
if (strpos($filename, 'ivr') !== false) continue;
```
This is intentional. IVR messages are scripted chatbot responses handled by the IVR
matching system. They are NOT product knowledge and should not be in the AI's KB.
The product knowledge is in `lesaep_knowledge.md` and `lesaep_lesson_catalog.md`.

### Two IVR-related DB option keys — keep straight
- `flosc_flow_flosc_default_ivr` — generic default flow, 143 keys, AI settings at 0.3
  (written here by mistake early in session — not the active LeSAEp flow)
- `flosc_flow_lesaep_ivr` — active LeSAEp flow, 145 keys, ALL production AI settings here

The chatpack, dispatch, and all AI processing use `flosc_flow_lesaep_ivr`.
Admin at `?ivr=lesaep_ivr.md` reads from `flosc_flow_lesaep_ivr`. ✓
Admin at `?ivr=flosc_default_ivr.md` reads from `flosc_flow_flosc_default_ivr`. (separate)

### `ai_freeline_restrictions` and `ai_member_access` — retired fields
Per the plan, these fields are retired. Their content has been migrated to:
- `ai_freeline_restrictions` → `ai_prompt_freeline` (phase overlay)
- `ai_member_access` → `ai_prompt_content` (phase overlay)
The fields still exist in the DB but are not read by the chatpack.

### `ai_off_topic_links` — affiliate referral links field
This field is for affiliate referral links and teacher registration links — NOT for
off-topic redirect URLs. Currently holds placeholder BBC/Duolingo links. Will be
populated with real affiliate partner links and teacher application links when ready.
The user manages this content.

---

## Deploy Command

```bash
rsync -avz --delete -e "ssh -p 1988 -i ~/.ssh/chemicloud_key" \
  /Users/dainismichel/2026/flosc/mvp_sprint/flosc_8_0_0/flosc/ \
  dainisne@51.81.55.106:public_html/wp-content/plugins/flosc/
```

After deploy, always flush: `wp cache flush`

---

## Session Failure Analysis

**Compaction cost:** The previous session (0d378a4d) was compacted before all agreed
values were saved to memory. Several hours of work (AI config values, field decisions,
lesson number additions) had to be recovered from the JSONL transcript. Time lost: ~2 hours.

**Root cause:** Agreed configuration values were discussed and finalized but not
immediately saved to memory files. Memory was saved at the end of the session — too late.

**Prevention:** Save agreed values to memory immediately when finalized, not at session end.
Memory file `project_lesaep_ai_config.md` now holds all canonical AI field values.

**Wrong flow key:** Writing to `flosc_flow_flosc_default_ivr` instead of
`flosc_flow_lesaep_ivr` caused all AI personality settings to be silently ignored for
the entire session. The IVR URL in the browser (`?ivr=lesaep_ivr.md`) was the clue.
Always verify which option key is active before writing settings.
