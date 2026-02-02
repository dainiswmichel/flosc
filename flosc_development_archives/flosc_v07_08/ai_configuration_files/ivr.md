# FLOSC IVR Configuration

## MessageStyle: pill
Description: Rounded ChatGPT/Claude style
.flosc-style-pill {
  background: #f0f0f0;
  border: 1px solid #d0d0d0;
  border-radius: 20px;
  padding: 8px 16px;
  font-size: 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-pill:hover {
  background: #e0e0e0;
}

## MessageStyle: button
Description: Standard rectangular button
.flosc-style-button {
  background: #4f46e5;
  color: #ffffff;
  border: none;
  border-radius: 6px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-button:hover {
  background: #4338ca;
}

## MessageStyle: chip
Description: Small compact chip
.flosc-style-chip {
  background: #e5e7eb;
  border: 1px solid #d1d5db;
  border-radius: 12px;
  padding: 4px 12px;
  font-size: 12px;
  cursor: pointer;
  transition: background 0.2s;
}
.flosc-style-chip:hover {
  background: #d1d5db;
}

## MessageStyle: card
Description: Larger card format with icon
.flosc-style-card {
  background: #ffffff;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  padding: 16px;
  text-align: center;
  min-width: 120px;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.flosc-style-card:hover {
  border-color: #4f46e5;
  box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
}

## Available Variables
{name}, {score}, {correct_items}, {missed_items}, {product_name}, {price}, {discount_price}, {timer_remaining}, {customer_count}, {lessons_completed}

## Available Conditions
- Scores: score > X, score < X, score >= X, score <= X, score == X
- Boolean: quiz_taken, !quiz_taken, logged_in, !logged_in, purchased, !purchased, lesson_viewed, returning_user, onboarded, has_incomplete_lesson
- Counters: message_count >= X, lessons_completed >= X
- Time: inactive_seconds > X, session_seconds > X, session_minutes > X
- Events: first_message_after_quiz, first_message_after_login, first_message_after_purchase, first_message_after_free_lesson, first_show_session
- Offers: offer_shown_[id], offer_dismissed_[id], offer_purchased_[id]
- Commands: command == "hide_intropanel", command == "show_intropanel"
- Logic: &&, ||, !, ()

---

# Freeline Messages
Visitor → Quiz → Login. Encourage quiz, then encourage login to see results.

## Welcome Message
MessageName: welcome_freeline_001
MessageType: auto
MessageContent: Hi! I'm your {product_name} assistant. I'm here to help you master new skills through personalized practice. Ready to discover exactly where you can improve?
MessageConditions: first_show_session && !logged_in

## Get Started
MessageName: get_started_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🚀
UserInput: Get started
MessageContent: Great! Let's begin. The best way to start is by taking our free quiz. It only takes 30 seconds and shows you exactly what you need to work on. Ready to see where you stand?
MessageConditions: !quiz_taken

## Start Free Quiz
MessageName: start_quiz_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 📝
UserInput: Start free quiz
Action: open_quiz
MessageContent: Perfect! Click the button below to begin your free quiz. It takes just 30 seconds.
MessageConditions: !quiz_taken

## How It Works
MessageName: how_it_works_001
MessageType: suggested_reply
MessageStyle: pill
Icon: ❓
UserInput: How does it work?
MessageContent: Here's how it works: First, you'll take a quick quiz to assess your current level. Then, based on your results, I'll unlock a free lesson personalized to your needs. After that, you can upgrade for full access to all content.
MessageConditions: always

## What You Learn
MessageName: what_you_learn_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 📚
UserInput: What will I learn?
MessageContent: You'll master practical skills through interactive lessons, get personalized feedback on your progress, and access a complete learning path designed to take you from beginner to advanced. Each lesson includes exercises, quizzes, and real-world applications.
MessageConditions: always

## Quiz Nudge - Challenges
MessageName: quiz_nudge_challenges_001
MessageType: auto
MessageContent: What are your biggest challenges with {product_name}?
MessageConditions: message_count >= 2 && !quiz_taken && !logged_in

## Quiz Nudge - Areas
MessageName: quiz_nudge_areas_001
MessageType: auto
MessageContent: Which areas give you the most trouble?
MessageConditions: message_count >= 3 && !quiz_taken && !logged_in

## Quiz Reminder
MessageName: quiz_reminder_001
MessageType: auto
MessageContent: Ready to see where you stand? Take the free quiz! It takes just 30 seconds and shows you exactly where you can improve.
MessageConditions: message_count >= 5 && !quiz_taken && !logged_in

## Inactive Check
MessageName: inactive_check_001
MessageType: auto
MessageContent: Still there? Let me know if you need help!
MessageConditions: inactive_seconds > 120 && !logged_in

---

# Login Messages
Show quiz results → Deliver free lesson(s) → Encourage purchase

## Welcome Back Logged In
MessageName: welcome_back_login_001
MessageType: auto
MessageContent: Welcome back, {name}! 🎉
MessageConditions: logged_in && returning_user && first_show_session && !purchased

## Quiz Results - High Score
MessageName: quiz_results_high_001
MessageType: auto
MessageContent: Excellent work, {name}! You scored {score}%. You're doing great! I've unlocked a FREE lesson to help you master even more.
MessageConditions: first_message_after_quiz && score >= 70

## Quiz Results - Medium Score
MessageName: quiz_results_medium_001
MessageType: auto
MessageContent: Good job, {name}! You scored {score}%. I've prepared a FREE lesson to help you improve in your challenge areas!
MessageConditions: first_message_after_quiz && score >= 40 && score < 70

## Quiz Results - Low Score
MessageName: quiz_results_low_001
MessageType: auto
MessageContent: Thanks for completing the quiz, {name}! Your score was {score}%. Don't worry - everyone starts somewhere! I've prepared a FREE lesson to help you get started.
MessageConditions: first_message_after_quiz && score < 40

## Create Account Prompt
MessageName: create_account_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🔐
UserInput: Create free account
Action: open_registration
MessageContent: Great job completing the quiz! Create your free profile to see your personalized results and unlock your free lesson.
MessageConditions: quiz_taken && !logged_in

## View Free Lesson
MessageName: view_lesson_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📖
UserInput: View my free lesson
Action: open_free_lesson
MessageContent: Perfect! Here's your free lesson based on your quiz results. Click below to start learning!
MessageConditions: logged_in && quiz_taken && !lesson_viewed

## After Free Lesson
MessageName: after_lesson_001
MessageType: auto
MessageContent: How was that lesson? Any questions about what we covered?
MessageConditions: lesson_viewed && first_show_session && !purchased

## Progress Interest
MessageName: progress_interest_001
MessageType: auto
MessageContent: You're making great progress! Want to see more lessons like this?
MessageConditions: lesson_viewed && message_count >= 2 && !purchased

## Full Course Teaser
MessageName: course_teaser_001
MessageType: auto
MessageContent: This is just the beginning. The full course has 50+ targeted exercises designed for your specific needs.
MessageConditions: lesson_viewed && session_minutes >= 2 && !purchased

---

# Offer Messages
Messages during/after free lesson experience → Making the sale

## Main OTO
MessageName: oto_main_001
MessageType: offer
OfferID: oto_main
Price: $100
DiscountPrice: $25
Timer: 3600
Icon: 🚀
MessageContent: You've experienced the power of personalized learning. **Ready to master everything?**

Get **{product_name}** for just **$25**

✓ 50+ targeted lessons
✓ Unlimited practice exercises  
✓ 3-month full access

~~$100~~ **$25** - Save 75% for the next {timer_remaining}!

This is your lowest price. After the timer expires, price goes to $100.

Ready to unlock your full potential?
MessageConditions: lesson_viewed && !purchased

## Get Full Access Button
MessageName: get_access_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🔓
UserInput: Get full access now
Action: checkout_oto_main
MessageContent: Click here to unlock your complete learning path at this special price!
MessageConditions: offer_shown_oto_main && !purchased

## Limited Time Push
MessageName: oto_push_001
MessageType: auto
MessageContent: This is a limited-time offer. Are you ready to unlock your full potential?
MessageConditions: offer_shown_oto_main && !purchased && session_minutes >= 1

## What's Included Question
MessageName: oto_questions_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 💬
UserInput: What's included?
MessageContent: Full access includes 50+ lessons, unlimited practice, personalized feedback, and 3 months of premium content. Any other questions?
MessageConditions: offer_shown_oto_main && !purchased

## Social Proof
MessageName: oto_social_001
MessageType: auto
MessageContent: Join {customer_count}+ students who have already transformed their skills with {product_name}!
MessageConditions: offer_shown_oto_main && !purchased && message_count >= 4

## Exit Intent
MessageName: oto_exit_001
MessageType: auto
MessageContent: Before you go - this special price won't last. Ready to start today?
MessageConditions: offer_shown_oto_main && !purchased && inactive_seconds > 180

---

# Sale Messages
Post-purchase onboarding

## Purchase Confirmation
MessageName: purchase_confirm_001
MessageType: auto
MessageContent: 🎉 **Congratulations, {name}!**

Welcome to {product_name}! You now have full access to all premium content.

Let me help you get started with your personalized learning journey.
MessageConditions: first_message_after_purchase

## Browse Lessons
MessageName: browse_lessons_001
MessageType: suggested_reply
MessageStyle: button
Icon: 📚
UserInput: Browse all lessons
Action: open_lesson_library
MessageContent: Great! Click here to explore your full lesson library.
MessageConditions: purchased && !onboarded

## Start Learning Path
MessageName: start_path_001
MessageType: suggested_reply
MessageStyle: button
Icon: 🎯
UserInput: Start my learning path
Action: open_personalized_path
MessageContent: Perfect! Your personalized path is based on your quiz results. Let's begin!
MessageConditions: purchased && !onboarded

## Getting Started Guide
MessageName: getting_started_001
MessageType: auto
MessageContent: Here's how to get the most from your membership:

1. Complete your profile for personalized recommendations
2. Browse the full lesson library
3. Start with lessons matched to your quiz results

Ready to dive in?
MessageConditions: purchased && !onboarded && message_count >= 1

## Onboarding Complete
MessageName: onboarding_complete_001
MessageType: auto
MessageContent: You're all set up! Your personalized learning path is ready. Where would you like to start?
MessageConditions: purchased && onboarded && first_show_session

---

# Content Messages
Ongoing users - Support, encouragement, engagement

## Welcome Back Content User
MessageName: welcome_back_content_001
MessageType: auto
MessageContent: Welcome back, {name}! Ready to continue your learning journey?
MessageConditions: purchased && returning_user && first_show_session

## Continue Lesson
MessageName: continue_lesson_001
MessageType: suggested_reply
MessageStyle: pill
Icon: ▶️
UserInput: Continue where I left off
Action: resume_last_lesson
MessageContent: Great! Let's pick up where you left off.
MessageConditions: purchased && has_incomplete_lesson

## Progress Encouragement
MessageName: progress_encouragement_001
MessageType: auto
MessageContent: Great progress, {name}! You've completed {lessons_completed} lessons. Keep up the excellent work!
MessageConditions: purchased && lessons_completed >= 3 && first_show_session

## Engagement Check
MessageName: engagement_check_001
MessageType: auto
MessageContent: How are you finding the lessons? Any topics you'd like to explore more?
MessageConditions: purchased && session_minutes >= 10

## Need Help
MessageName: need_help_001
MessageType: suggested_reply
MessageStyle: pill
Icon: 🆘
UserInput: I need help
Action: open_support
MessageContent: I'm here to help! What do you need assistance with?
MessageConditions: purchased

## Support Available
MessageName: support_available_001
MessageType: auto
MessageContent: I'm here if you need any help or have questions about the content!
MessageConditions: purchased && inactive_seconds > 180

## Milestone Celebration
MessageName: milestone_celebration_001
MessageType: auto
MessageContent: 🎉 Amazing milestone, {name}! You've completed {lessons_completed} lessons. Your dedication is paying off!
MessageConditions: purchased && lessons_completed >= 10 && first_show_session
