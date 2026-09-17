# FLOSC Technical Default — IVR Configuration
# Personality: professional, direct, efficient. No emojis.
# Best for SaaS, B2B, docs, and technical products.
# Identity: technical answers agent — not a lesson coach.

---

# Freeline Messages (visitors)

## Welcome
MessageName: tech_welcome
MessageType: auto
MessageStyle: card
MessageContent: Hello. I am your technical answers agent. Do you have any technical questions for me? Note: this is IVR-style copy — configure your preferred AI API under Settings → AI for much more intelligent free-form responses.
MessageConditions: is_visitor && first_show_session

---

## What is this
MessageName: tech_intro_what
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is this?
MessageContent: I am a technical assistant for this product. Ask a concrete question, or pick an option below. Without an AI API key, I stay on IVR buttons and keywords; floscAdmins enable full chat under Settings → AI.
MessageConditions: is_visitor || is_guest || is_member
Keywords: what is this, overview, what do you do, about, who are you

---

## Configure AI API
MessageName: tech_intro_ai_api
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How do I enable AI chat?
MessageContent: This is just IVR-style copy until AI is connected. Configure your preferred AI API under Settings → AI (Anthropic, OpenAI, xAI, or Gemini — BYOK: paste key, test, save) for much more intelligent free-form technical answers.
MessageConditions: is_visitor || is_guest || is_member
Keywords: ai, api, openai, anthropic, grok, xai, byok, enable ai, configure ai

---

## Ask a tech question
MessageName: tech_intro_ask
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: I have a technical question
MessageContent: Good. State the system, the symptom, and what you already tried. For best free-form answers, floscAdmin should connect an AI API under Settings → AI.
MessageConditions: is_visitor || is_guest || is_member
Keywords: technical, bug, error, how do i, configure, setup

---

## Capabilities
MessageName: tech_intro_capabilities
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What can it do?
MessageContent: A rundown of the core capabilities and where they fit your workflow.
MessageConditions: is_visitor || is_guest || is_member
Keywords: features, capabilities, what can it do, specs

---

## Pricing
MessageName: tech_intro_pricing
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: How much does it cost?
MessageContent: A straight answer on pricing and what each tier includes — when your admin has configured offers.
MessageConditions: is_visitor || is_guest || is_member
Keywords: pricing, cost, how much, plans

---

# Guest Messages

## Guest Welcome
MessageName: tech_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Signed in. I'm still your technical answers agent. What do you need to solve?
MessageConditions: is_guest && first_show_session

---

## Documentation
MessageName: tech_guest_docs
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: View documentation
MessageContent: Point me at the topic — setup, API, errors, or architecture — and I'll keep the answer tight.
MessageConditions: is_guest || is_member
Keywords: docs, documentation, reference, manual

---

## Get support
MessageName: tech_guest_support
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Contact support
MessageContent: One-line summary of the issue, plus environment if you have it. Fastest path to a fix.
MessageConditions: is_guest || is_member
Keywords: support, help, contact, issue, problem

---

# Member Messages

## Member Welcome
MessageName: tech_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Full access. Technical answers agent online. What should we debug or configure next?
MessageConditions: is_member && first_show_session

---

## Dashboard
MessageName: tech_member_dashboard
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Open the dashboard
MessageContent: If a dashboard is configured for this flow, start there; otherwise ask me a technical question and we'll proceed.
MessageConditions: is_member
Keywords: dashboard, account, status, home

---

## What to set up first
MessageName: tech_member_next
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What should I set up first?
MessageContent: Prioritized setup: credentials, core config, then verify with one test path. Which area first?
MessageConditions: is_member
Keywords: next, setup, get started, first steps
