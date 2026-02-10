# FLOSC Positive Default IVR Configuration v1.2.5

## Personality: Warm, Encouraging, Supportive

This IVR variant uses positive, uplifting language designed to create a welcoming atmosphere.

---

## Freeline Messages (Visitors)

### Message: welcome_positive
Name: welcome_positive
Type: ai_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: 
🌟 Welcome! I'm so glad you're here!

I'm here to help you discover something wonderful today. What brings you by?

### Message: intro_card_learn
Name: intro_card_learn
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: I'd love to learn something new! ✨

### Message: intro_card_explore
Name: intro_card_explore
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: Show me what you've got! 🎉

### Message: intro_card_curious
Name: intro_card_curious
Type: suggested_user_autoprompt
Phase: freeline
Conditions: is_visitor
MessagePanel: intro
MessageStyle: card
Content: I'm just curious! 🤔

---

## Guest Messages (After Login)

### Message: guest_welcome_positive
Name: guest_welcome_positive
Type: ai_autoprompt
Phase: login
Conditions: is_guest AND first_message
MessagePanel: prompt
MessageStyle: pill
Content:
🎊 Amazing! You're officially part of the family now!

I'm excited to help you on your journey. What would you like to explore first?

### Message: guest_prompt_start
Name: guest_prompt_start
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: Let's get started! 🚀

### Message: guest_prompt_tell_me
Name: guest_prompt_tell_me
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: Tell me more about this! 💡

### Message: guest_prompt_help
Name: guest_prompt_help
Type: suggested_user_autoprompt
Phase: login
Conditions: is_guest
MessagePanel: prompt
MessageStyle: pill
Content: I could use some guidance 🙏

---

## Member Messages (After Purchase)

### Message: member_welcome_positive
Name: member_welcome_positive
Type: ai_autoprompt
Phase: sale
Conditions: is_member AND first_message_after_purchase
MessagePanel: prompt
MessageStyle: pill
Content:
🎉 Congratulations! Welcome to the full experience!

You've made a fantastic decision. I'm here to make sure you get the absolute most out of everything. What would you like to dive into?

### Message: member_prompt_lessons
Name: member_prompt_lessons
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: Show me the lessons! 📚

### Message: member_prompt_progress
Name: member_prompt_progress
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: How am I doing? 📊

### Message: member_prompt_next
Name: member_prompt_next
Type: suggested_user_autoprompt
Phase: content
Conditions: is_member
MessagePanel: prompt
MessageStyle: pill
Content: What should I try next? ⭐
