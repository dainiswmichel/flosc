# FLOSC Technical Default — IVR Configuration
# Personality: professional, direct, efficient. No emojis.
# Best for SaaS, B2B, and technical products. Flow: evaluate → decide.
# This is a starter template — rename it to your flow and edit freely.

---

# Freeline Messages (visitors)

## Welcome
MessageName: tech_welcome
MessageType: auto
MessageStyle: card
MessageContent: Welcome. I'm your AI assistant. Ask me anything, or pick an option below to get oriented.
MessageConditions: is_visitor && first_show_session

---

## What is this
MessageName: tech_intro_what
MessageType: suggested_user_autoprompt
MessageStyle: card
UserInput: What is this?
MessageContent: A plain, quick overview of what this product does and who it's for.
MessageConditions: is_visitor || is_guest || is_member
Keywords: what is this, overview, what do you do, about

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
MessageContent: A straight answer on pricing and what each tier includes.
MessageConditions: is_visitor || is_guest || is_member
Keywords: pricing, cost, how much, plans

---

# Guest Messages

## Guest Welcome
MessageName: tech_guest_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Account created. You now have access to the guest features. Pick an option or ask a question.
MessageConditions: is_guest && first_show_session

---

## Documentation
MessageName: tech_guest_docs
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: View documentation
MessageContent: Here's where to find the documentation and reference material.
MessageConditions: is_guest || is_member
Keywords: docs, documentation, reference, manual

---

## Get support
MessageName: tech_guest_support
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Contact support
MessageContent: Tell me the issue in one line and I'll route you to the fastest path to a fix.
MessageConditions: is_guest || is_member
Keywords: support, help, contact, issue, problem

---

# Member Messages

## Member Welcome
MessageName: tech_member_welcome
MessageType: auto
MessageStyle: pill
MessageContent: Purchase confirmed. Full access granted. How would you like to proceed?
MessageConditions: is_member && first_show_session

---

## Dashboard
MessageName: tech_member_dashboard
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: Open the dashboard
MessageContent: Taking you to your dashboard and current status.
MessageConditions: is_member
Keywords: dashboard, account, status, home

---

## What to set up first
MessageName: tech_member_next
MessageType: suggested_user_autoprompt
MessageStyle: pill
UserInput: What should I set up first?
MessageContent: A short, prioritized setup checklist so you get value fast.
MessageConditions: is_member
Keywords: next, setup, get started, first steps
