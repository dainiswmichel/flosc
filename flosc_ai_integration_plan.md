# FLOSC AI Integration Plan

**Date:** 2026-02m-19d
**Version:** v1.8.5 → v1.9.0
**Prepared for:** Dainis Michel

---

## Executive Summary

FLOSC v1.8.5 has a well-architected AI system with four layers already built:

1. **IVR scripted fallback** — keyword-matching responses (works with zero API keys)
2. **AI Provider Factory** — OpenAI, Anthropic, xAI support with system prompt composition
3. **RAG Manager** — AI tool-calling for WordPress content search
4. **Condition Evaluator** — boolean expression engine for message visibility

The integration work is about **wiring these layers together tightly** so the AI operates as a single coherent conversational agent that respects IVR boundaries, accesses the AI KB dynamically, honors the sacred flosc flow category, delivers exactly one free lesson when instructed, and maintains full conversational fluency.

---

## The Five Requirements

### 1. "The AI needs to be aware of the IVR set boundaries"

**Current state:** The AI Provider Factory builds a system prompt with phase-specific instructions (`get_flosc_process_prompt()`), but the IVR message conditions are **not injected into the AI's context**. The Condition Evaluator runs server-side to show/hide UI elements — the AI doesn't know what conditions are currently true or what IVR messages are currently visible.

**What needs to happen:**

| Task | File | Description |
|------|------|-------------|
| A1 | `class-ai-provider-factory.php` | Add a new method `build_ivr_context()` that evaluates all IVR messages through the Condition Evaluator and produces a summary: "Currently visible messages: [list]. Currently active conditions: [list]." |
| A2 | `class-ai-provider-factory.php` | Inject `build_ivr_context()` output into `build_system_prompt()` as section 2.5 (between FLOSC process and phase prompt) |
| A3 | `class-condition-evaluator.php` | Add `get_active_conditions()` method that returns all currently-true condition flags as a flat array for AI consumption |
| A4 | `class-ai-provider-factory.php` | Include the `flosc_ai_boundaries` option content in every phase prompt, not just the identity section — boundaries must be reinforced per-phase |

**Boundary enforcement rules for the AI:**
- In FREELINE: AI must NOT reveal premium content, must NOT show lesson details, must NOT quote prices unless asked
- In LOGIN: AI can reference quiz results, must encourage account creation, must NOT deliver lessons
- In OFFER: AI can present value proposition, can mention pricing, must NOT bypass the offer flow
- In SALE: AI can answer objections, must NOT give content away for free
- In CONTENT: AI has full access, must still respect per-lesson access levels

**Verification:** After implementation, test by asking the AI "give me all the lesson content" in FREELINE phase — it should refuse and redirect to quiz.

---

### 2. "It will need to be able to access the AI KB"

**Current state:** Two KB systems exist but are loosely coupled:

- **Static KB:** `load_orientation_files()` loads all `.md` files from `ai_configuration_files/` into the system prompt at startup. Access-level filtering exists (`flosc_knowledge_access_` option per file).
- **Dynamic KB (RAG):** `search_knowledge_base()` searches `wp-content/uploads/flosc-knowledge/` directory. This directory may not exist yet on a fresh install.

**What needs to happen:**

| Task | File | Description |
|------|------|-------------|
| B1 | `class-rag-manager.php` | Extend `search_knowledge_base()` to ALSO search `ai_configuration_files/` when `flosc-knowledge/` is empty or as a fallback — the lesson files (lesson_01.md through lesson_10.md) and lesson_catalog.md are already there |
| B2 | `class-rag-manager.php` | Add a new tool: `get_ivr_guidance` — allows AI to query the IVR config for what to say in specific situations (e.g., "what should I say when user asks about pricing?") |
| B3 | `class-ai-provider-factory.php` | Make KB loading flow-aware: each flow has its own `ivr_file` — the AI should only load KB files relevant to the current flow, not all flows |
| B4 | `admin/ai-knowledge.php` | Ensure the admin UI has a clear "Upload Knowledge File" button that saves to `wp-content/uploads/flosc-knowledge/` with access-level tagging |
| B5 | `class-content-filter.php` | Verify `filter_markdown_by_access()` works correctly for the three tiers: visitor gets teaser, guest gets descriptions + pricing, member gets everything |

**KB access matrix:**

| Access Level | Static KB (ai_configuration_files/) | Dynamic KB (uploads/flosc-knowledge/) | WordPress Posts |
|---|---|---|---|
| Visitor | Public files only | Public files only | Title + excerpt only |
| Guest | Public files | Public + guest files | Title + excerpt + offer details |
| Member | All files | All files | Full content |

**Verification:** Ask the AI "what lessons are available?" as a visitor — should get titles only. As a member — should get full details.

---

### 3. "The flosc flow WordPress category is sacred"

**Current state:** The Flow Manager links each flow to a `wp_category_id`. The Lesson Manager resolves categories via `resolve_category()` (per-flow → global → scan). Content Protection marks categories as protected (`_flosc_protected` term meta).

**What needs to happen:**

| Task | File | Description |
|------|------|-------------|
| C1 | `class-rag-manager.php` | `search_posts()` currently hardcodes `'category_name' => 'flosc_sample_data'`. Change to dynamically resolve from current flow's `wp_category_id` via Lesson Manager |
| C2 | `class-rag-manager.php` | `get_lesson_content()` must validate that the requested post belongs to the current flow's category. Reject cross-flow content requests. |
| C3 | `class-content-protection.php` | Ensure `is_category_protected()` is called before any RAG tool returns content from a protected category |
| C4 | `class-flow-manager.php` | Add `get_current_flow_category()` convenience method that returns the resolved category for the active flow |
| C5 | System prompt | Add explicit instruction: "You serve content from ONE flow at a time. Never reference or deliver content from other flows." |

**The "sacred" principle:**
- Each flow's WordPress category is its **content boundary**
- The AI must NEVER serve content from a different flow's category
- The AI must NEVER create, modify, or suggest modifying posts in the flow category
- The AI is a **reader and guide**, not a content creator in the flow category
- The WordPress category structure is the floscAdmin's domain — the AI respects it absolutely

**Verification:** If two flows exist (LeSAEp + Simplified Solfeggio), asking the LeSAEp AI about solfeggio lessons should return nothing or a polite "that's a different program."

---

### 4. "It will need to follow the quiz instructions and hand out one lesson not more"

**Current state:** The Free Lesson Manager (`class-free-lesson-manager.php`) already implements this correctly:
- Hooks into `flosc_quiz_completed`
- Calculates missed lessons from quiz results
- Uses `calculate_free_lesson_count()` from Member Access (admin-configurable count)
- Picks random missed lesson(s), stores in `_flosc_free_lesson_number` user meta
- Grants guest access to specific post(s)

**What needs to happen:**

| Task | File | Description |
|------|------|-------------|
| D1 | `class-ai-provider-factory.php` | Add quiz state to system prompt context: "User has taken quiz: [yes/no]. Score: [X%]. Free lesson assigned: [lesson #N]. Free lesson viewed: [yes/no]." |
| D2 | `class-rag-chat-handler.php` | When AI calls `get_lesson_content`, check: if user is guest (not member) AND lesson is NOT their assigned free lesson → return "This lesson requires membership." |
| D3 | `class-rag-manager.php` | Add tool: `deliver_free_lesson` — dedicated tool that returns the user's assigned free lesson (from `_flosc_free_lesson_number`). If no free lesson assigned, returns "Take the quiz first!" |
| D4 | System prompt | Add: "After quiz completion, the user receives access to exactly ONE free lesson. Do not offer additional free lessons. Do not summarize other lessons' content. Guide them to explore their free lesson, then present the membership offer." |
| D5 | `class-condition-evaluator.php` | Ensure `first_message_after_free_lesson` condition works and is passed to AI context so the AI knows when the user has viewed their free lesson |

**The one-lesson rule:**
- Admin configures how many free lessons via `calculate_free_lesson_count()` (default: 1)
- The AI must respect this number exactly — no more
- If the admin sets 1, the AI gives 1. If admin sets 2, the AI gives 2
- The AI must NOT teach the lesson content directly — it directs the user to the WordPress post
- The AI CAN discuss what the lesson is about at a high level to build interest

**Verification:** After quiz, ask the AI "can I see lesson 3 and lesson 7?" — should only deliver the one assigned free lesson and redirect to membership for the other.

---

### 5. "It will need to be AI — fully conversational and focused on the flosc flow"

**Current state:** The AI Provider Factory supports real AI conversations via OpenAI/Anthropic/xAI. The RAG Chat Handler implements a multi-turn tool-calling loop. The IVR provides keyword-based fallback.

**What needs to happen:**

| Task | File | Description |
|------|------|-------------|
| E1 | `class-rag-chat-handler.php` | Implement conversation history — currently each message is stateless. Store conversation in PHP session or user meta (last 10 messages) so the AI has context |
| E2 | `class-ai-provider-factory.php` | Increase `max_tokens` from 500 (OpenAI) to 1000+ for conversational responses. 500 tokens truncates mid-thought |
| E3 | `class-ai-provider-factory.php` | Build personality continuity: the AI's name, role, and traits must persist across messages, not just be set in system prompt |
| E4 | REST API (`flosc.php`) | Ensure `/flosc/v1/ai-query` passes conversation history to the provider, not just the latest message |
| E5 | `class-ai-provider-factory.php` | Add conversation-aware prompting: "You are in an ongoing conversation. Reference what was discussed earlier. Use the user's name if known." |
| E6 | Frontend JS | Ensure the chat UI sends the full conversation history (or a conversation ID) with each API call |

**"Fully conversational" means:**
- Multi-turn awareness (remembers what was said 5 messages ago)
- Natural language (not robotic IVR-style responses)
- Contextual references ("As I mentioned, your quiz showed...")
- Emotional intelligence (encouraging after low scores, celebratory after high scores)
- Goal-oriented (always guiding toward the next FLOSC phase)
- Knows when to stop talking and let the user act (take quiz, log in, buy)

**"Focused on the flosc flow" means:**
- The AI serves the funnel: Freeline → Login → Offer → Sale → Content
- It does NOT go off-topic for extended periods
- It gently redirects tangential questions back to the flow
- It uses IVR autoprompt pills as conversation anchors
- It knows the user's current phase and what the next action should be

**Verification:** Have a 10-message conversation. On message 10, reference something from message 2. The AI should recall it.

---

## Implementation Order (Tomorrow's Sprint)

### Phase 1: Foundation (Tasks A1–A4)
**Estimated effort:** 45 min
Wire the Condition Evaluator output into the AI system prompt. This is the critical foundation — the AI must know what the IVR allows before anything else.

### Phase 2: KB Access (Tasks B1–B5)
**Estimated effort:** 30 min
Make the RAG tools search the right directories and respect access levels. Most of this code exists; it needs wiring and testing.

### Phase 3: Flow Category Isolation (Tasks C1–C5)
**Estimated effort:** 30 min
Replace hardcoded `flosc_sample_data` with dynamic flow category resolution. Add cross-flow guards.

### Phase 4: Quiz → Free Lesson Pipeline (Tasks D1–D5)
**Estimated effort:** 45 min
Inject quiz state into AI context. Add the `deliver_free_lesson` tool. Enforce the one-lesson boundary.

### Phase 5: Conversational Memory (Tasks E1–E6)
**Estimated effort:** 60 min
This is the most complex. Requires conversation storage, history passing, and frontend changes.

### Phase 6: Integration Testing
**Estimated effort:** 30 min per flow
Walk through the complete FLOSC funnel as each user type (visitor, guest, member) and verify all five requirements hold.

**Total estimated effort:** ~4 hours

---

## Files To Be Modified

| File | Changes | Priority |
|------|---------|----------|
| `includes/class-ai-provider-factory.php` | IVR context injection, conversation history, token limits, boundary reinforcement | Critical |
| `includes/class-rag-manager.php` | Flow-aware search, KB fallback, new tools | Critical |
| `includes/class-rag-chat-handler.php` | Conversation memory, history passing | Critical |
| `includes/class-condition-evaluator.php` | `get_active_conditions()` method | High |
| `includes/class-flow-manager.php` | `get_current_flow_category()` convenience method | Medium |
| `includes/class-content-filter.php` | Verify access filtering works correctly | Medium |
| `flosc.php` | REST API endpoint updates for conversation history | Medium |
| Frontend JS | Conversation history management | Medium |
| `admin/ai-knowledge.php` | KB upload UI verification | Low |

---

## What I Cannot Verify

Per the copilot-instructions.md accountability rules:

- I cannot run WordPress or test PHP execution paths
- I cannot verify that the Condition Evaluator context builds correctly at runtime
- I cannot test AI API calls (OpenAI/Anthropic/xAI)
- I cannot verify frontend JS behavior in a browser
- I cannot test the quiz → free lesson → offer pipeline end-to-end
- I cannot test multi-flow isolation without two configured flows

**Each change will need manual testing** through the actual FLOSC funnel on a live WordPress installation.

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    USER (Browser)                        │
│                                                         │
│  Chat UI → sends message + conversation_id              │
│         ← receives AI response + updated context        │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│              REST API: /flosc/v1/ai-query                │
│                                                         │
│  1. Authenticate user (or recognize visitor)             │
│  2. Resolve current flow (by slug/domain)                │
│  3. Load conversation history                            │
│  4. Build context (user meta, session, quiz state)       │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│           AI Provider Factory: build_system_prompt()     │
│                                                         │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌───────────┐  │
│  │ Identity │ │ IVR      │ │ Phase    │ │ Knowledge │  │
│  │ (name,   │ │ Context  │ │ Prompt   │ │ Base      │  │
│  │  role,   │ │ (active  │ │ (per-    │ │ (static + │  │
│  │  traits, │ │  conds,  │ │  phase   │ │  dynamic) │  │
│  │  bounds) │ │  visible │ │  rules)  │ │           │  │
│  │          │ │  msgs)   │ │          │ │           │  │
│  └──────────┘ └──────────┘ └──────────┘ └───────────┘  │
│                    +                                     │
│  ┌──────────────────────────────────────────────────┐   │
│  │ Session Context (quiz state, score, free lesson, │   │
│  │ user name, access level, flow ID, phase)         │   │
│  └──────────────────────────────────────────────────┘   │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│        RAG Chat Handler: call_ai_with_rag()             │
│                                                         │
│  AI Provider (Anthropic/OpenAI/xAI/IVR)                 │
│       │                                                  │
│       ├── Tool call: search_knowledge_base(query)        │
│       │   └── Search ai_configuration_files/ +           │
│       │       uploads/flosc-knowledge/                   │
│       │                                                  │
│       ├── Tool call: search_posts(keywords)              │
│       │   └── Search current flow's WP category ONLY     │
│       │                                                  │
│       ├── Tool call: get_lesson_content(lesson_num)      │
│       │   └── Check: is this the free lesson? →          │
│       │       Access check + content filter              │
│       │                                                  │
│       ├── Tool call: deliver_free_lesson()        [NEW]  │
│       │   └── Return assigned free lesson content        │
│       │                                                  │
│       └── Tool call: get_ivr_guidance(situation)  [NEW]  │
│           └── Query IVR config for scripted response     │
│                                                          │
│  ┌──────────────────────────────────────────────────┐   │
│  │ Content Filter: access-level gating              │   │
│  │ visitor → teaser only                            │   │
│  │ guest   → descriptions + pricing                 │   │
│  │ member  → full content                           │   │
│  └──────────────────────────────────────────────────┘   │
│                                                         │
│  Conversation History (last 10 messages stored)         │
└────────────────────────┬────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────┐
│              Condition Evaluator                         │
│                                                         │
│  Every IVR message is tested against:                    │
│  quiz_taken, logged_in, purchased, score >= X,           │
│  lesson_viewed, first_message_after_quiz, etc.           │
│                                                         │
│  Active conditions → fed back to AI as context           │
│  "User has: quiz_taken=true, score=70, logged_in=true,   │
│   purchased=false, free_lesson_viewed=true"               │
└─────────────────────────────────────────────────────────┘
```

---

## Notes

- The hardcoded `'category_name' => 'flosc_sample_data'` in RAG Manager (line ~233) is the most urgent fix — it breaks multi-flow
- The IVR `ivr_response()` method in AI Provider Factory has hardcoded LeSAEp-specific text ("pronunciation coach") — should pull from flow config
- Conversation history is the biggest new feature — needs a storage strategy decision (user_meta vs transient vs session vs custom table)
- The `max_tokens: 500` on OpenAI requests is too low for conversational AI — needs to be at least 1000, ideally 1500
- The RAG loop's `max_iterations: 5` is good — prevents runaway API costs
- The AI model default is `claude-sonnet-4-5-20250929` — confirm this is the desired model

---

*This plan traces the user journey: Visitor loads page → takes quiz → sees results → gets offer → pays → accesses content. Every task maps to a point in that journey.*
