# FLOSC Technical Default IVR Configuration v1.2.5

## Personality: Professional, Direct, Efficient

This IVR variant uses clear, professional language designed for technical or business contexts.

---

## Freeline Messages (Visitors)

### Message: welcome_technical
Name: welcome_technical
Type: ai_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: 
Welcome. I'm your AI assistant.

How can I help you today?

### Message: intro_card_demo
Name: intro_card_demo
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: Request a demo

### Message: intro_card_features
Name: intro_card_features
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: View features

### Message: intro_card_pricing
Name: intro_card_pricing
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: See pricing

---

## Guest Messages (After Login)

### Message: guest_welcome_technical
Name: guest_welcome_technical
Type: ai_autoprompt
Phase: login
Conditions: is_guest AND first_message
MessagePanel: prompt
MessageStyle: pill
Content:
Account created successfully.

You now have access to the guest features. Select an option below or ask a question.

### Message: guest_prompt_documentation
Name: guest_prompt_documentation
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: View documentation

### Message: guest_prompt_quickstart
Name: guest_prompt_quickstart
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: Quick start guide

### Message: guest_prompt_support
Name: guest_prompt_support
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: Contact support

---

## Member Messages (After Purchase)

### Message: member_welcome_technical
Name: member_welcome_technical
Type: ai_autoprompt
Phase: sale
Conditions: is_member AND first_message_after_purchase
MessagePanel: prompt
MessageStyle: pill
Content:
Purchase confirmed. Full access granted.

Your account has been upgraded. All features are now available. How would you like to proceed?

### Message: member_prompt_dashboard
Name: member_prompt_dashboard
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: Open dashboard

### Message: member_prompt_api
Name: member_prompt_api
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: API reference

### Message: member_prompt_settings
Name: member_prompt_settings
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: Account settings
