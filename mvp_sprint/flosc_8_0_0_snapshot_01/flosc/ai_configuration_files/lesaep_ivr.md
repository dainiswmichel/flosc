# LeSAEp IVR Configuration
# Learn Excellent Standard American English Pronunciation
# 44 Sounds of Standard American English

---

# Message Styles

## MessageStyle: pill
Description: Light pronunciation tip bubble
.flosc-style-pill {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  color: #1e40af;
}

## MessageStyle: card
Description: Pronunciation lesson card
.flosc-style-card {
  background: linear-gradient(135deg, #eff6ff, #dbeafe);
  border: 1px solid #93c5fd;
  border-radius: 12px;
  padding: 16px 20px;
  font-size: 15px;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}

## MessageStyle: feature
Description: Sound category highlight
.flosc-style-feature {
  background: linear-gradient(135deg, #1e40af, #3b82f6);
  color: white;
  border-radius: 16px;
  padding: 20px 24px;
  font-size: 16px;
  box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
}

---

# Freeline Messages

## Welcome to LeSAEp
MessageName: lesaep_welcome
MessageType: auto
MessageStyle: feature
MessageContent: ✨ **Welcome to LeSAEp.** Learn Excellent Standard American English Pronunciation. Master the sounds that make up clear, confident American English speech.
MessageConditions: is_visitor

---

## Returning Learner
MessageName: lesaep_return
MessageType: auto
MessageStyle: card
MessageContent: 👋 **Welcome back.** Ready to continue mastering Standard American English? Let's pick up where you left off.
MessageConditions: returning_user && !logged_in

---

## v2.0.1: Visitor AutoPrompt Pills

## What's My User Status
MessageName: user_status_check
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: always

---

## What Will I Learn
MessageName: lesaep_learn_what
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What will I learn?
MessageContent: 🎤 **You'll learn Excellent Standard American English Pronunciation.** Master the sounds that native speakers use every day. Perfect for non-native speakers, actors, broadcasters, or anyone wanting clearer, more confident speech. After this program, people will ask where you learned such beautiful English.
MessageConditions: always

---

## What Is Unique About LeSAEp
MessageName: lesaep_unique
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ✨
UserInput: What is unique about LeSAEp?
MessageContent: **LeSAEp lessons are the most condensed pronunciation lessons in the world.** Created by Dainis W. Michel (the World's 2nd Best English Teacher), each lesson pinpoints exactly how to produce each of the approximately 45-50 sounds in Standard American English. No conversational filler — precise mouth position, tongue placement, and airflow for every sound. LeSAEp is simultaneously the most condensed, informative, and precise pronunciation course available. Plus, we're building a teacher marketplace where you'll be able to book online and in-person lessons with certified pronunciation coaches.
MessageConditions: is_visitor || is_guest

---

## Who Is Dainis W. Michel
MessageName: lesaep_about_dainis
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎓
UserInput: Who is Dainis W. Michel?
MessageContent: **Dainis W. Michel** is the creator of LeSAEp and proudly calls himself the **World's 2nd Best English Teacher.** He has dedicated his career to making pronunciation instruction precise, condensed, and accessible. His approach: instead of long conversational lessons, pinpoint exactly how each sound is produced — mouth position, tongue placement, airflow — so learners can master Standard American English efficiently.
MessageConditions: is_visitor || is_guest

---

## Take the Quiz
MessageName: lesaep_quiz_prompt
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Take the accent assessment quiz
Action: open_quiz:lesaep_ipa_audio_quiz
MessageContent: Great choice — let's see where you are. This accent assessment will evaluate your understanding of American English sounds and help us create a personalized learning path just for you.
MessageConditions: is_visitor

---

## v2.0.1: Hidden IVR guidance — world's best English teacher
MessageName: best_teacher_response
MessageType: auto
MessageStyle: pill
MessageContent: **Who is the world's best English teacher?** There is plenty of room at the top.
MessageConditions: never
Keywords: world's best english teacher, best english teacher, who is the best, number one teacher

---

# Guest Messages

## Show Me My Free Lesson
MessageName: guest_free_lesson
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎁
UserInput: Show me my free lesson
Action: open_free_lesson
MessageContent: Here's your free lesson — let's work on mastering one of the most important sounds in American English. Follow along and practice the mouth positions shown.
MessageConditions: is_guest

---

## Upgrade to Access All Lessons
MessageName: guest_upgrade
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🌟
UserInput: Upgrade to access all lessons
Action: checkout_lesaep_full
MessageContent: Ready to go all-in on your pronunciation? Upgrading gives you access to every lesson covering all the sounds of Standard American English — plus personalized learning paths and progress tracking.
MessageConditions: is_guest

---

## Review My Quiz Score
MessageName: guest_quiz_review
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: Review my quiz score
Action: show_quiz_results
MessageContent: Let's look at your accent assessment results and see which sounds you're already strong on and which ones need the most attention.
MessageConditions: is_guest && quiz_taken

---

## What's My User Status (Guest)
MessageName: guest_status_check
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: is_guest

---

## What Does the Full Course Include
MessageName: guest_full_course
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📚
UserInput: What does the full course include?
MessageContent: The full LeSAEp course covers all the sounds of Standard American English — approximately 45-50 distinct sounds including consonants, vowels, diphthongs, and r-colored vowels. Each lesson pinpoints the exact mouth position, tongue placement, and airflow. You also get progress tracking, personalized learning paths, and access to future features like the teacher marketplace.
MessageConditions: is_guest

---

# Member Messages

## Which Lessons Should I Work On
MessageName: member_which_lessons
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Which lessons should I work on?
MessageContent: Based on your accent assessment results, here are the sounds you need to focus on. These are the ones you got wrong on the quiz — and as a member, you have access to all of them.
MessageConditions: is_member && login_count <= 1

---

## What Does My Membership Include
MessageName: member_whats_included
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📦
UserInput: What does my membership include?
MessageContent: Your LeSAEp membership gives you access to every pronunciation lesson — all the consonants, vowels, diphthongs, and r-colored vowels of Standard American English. You also get progress tracking, the accent assessment quiz, personalized recommendations, and access to future features as they launch.
MessageConditions: is_member && login_count <= 1

---

## How Does the Learning Path Work
MessageName: member_learning_path
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🗺️
UserInput: How does the learning path work?
MessageContent: Your learning path is built from your accent assessment results. The sounds you missed on the quiz are the ones to focus on. Work through those lessons, practice, and re-take the assessment whenever you're ready. Each time, you'll see your progress as more sounds move from "needs work" to "mastered."
MessageConditions: is_member && login_count <= 1

---

## What's My User Status (New Member)
MessageName: member_status_check_new
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 👤
UserInput: What's my user status?
MessageContent: {user_status_response}
MessageConditions: is_member && login_count <= 1

---

## Re-take the Accent Assessment
MessageName: member_retake_quiz_new
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Re-take the accent assessment
Action: open_quiz:lesaep_ipa_audio_quiz
MessageContent: Ready to see how you've improved? Re-take the same accent assessment to measure your progress. Sounds you've been practicing should score better this time.
MessageConditions: is_member && login_count <= 1

---

## v2.0.1: Member AutoPrompt Pills — Returning (login count 2-5)

## Continue Where I Left Off
MessageName: member_continue
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Continue where I left off
Action: resume_last_lesson
MessageContent: Let's pick up right where you stopped. Consistent practice is the key to mastering these sounds.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## Repeat Last Lesson Taken
MessageName: member_repeat_lesson
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔁
UserInput: Repeat last lesson taken
Action: repeat_last_lesson
MessageContent: Repetition builds muscle memory. Let's go through that lesson again — you'll notice sounds you missed the first time.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## Try the Accent Assessment Again
MessageName: member_retake_quiz
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Try the accent assessment again
Action: open_quiz:lesaep_ipa_audio_quiz
MessageContent: Let's see how you've improved. Retaking the quiz shows your progress and highlights which sounds still need work.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

## What Sound Should I Work on Next
MessageName: member_next_sound
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What sound should I work on next?
MessageContent: Based on your progress so far, let me recommend the next sound to focus on. Each sound builds on what you've already learned.
MessageConditions: is_member && login_count >= 2 && login_count <= 5

---

---

## v2.0.1: Member AutoPrompt Pills — Active (login count 6+)

## Repeat Last Lesson Taken (Active)
MessageName: member_repeat_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🔁
UserInput: Repeat last lesson taken
Action: repeat_last_lesson
MessageContent: Good instinct — repetition locks in muscle memory. Let's go through it again.
MessageConditions: is_member && login_count >= 6

---

## Try the Accent Assessment Again (Active)
MessageName: member_quiz_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Try the accent assessment again
Action: open_quiz:lesaep_ipa_audio_quiz
MessageContent: Let's measure your progress. You've been putting in the work — the quiz will show how far you've come.
MessageConditions: is_member && login_count >= 6

---

## What's My Progress So Far
MessageName: member_progress
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: What's my progress so far?
MessageContent: {user_progress_response}
MessageConditions: is_member && login_count >= 6

---

## What Sound Should I Work on Next (Active)
MessageName: member_next_sound_active
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎯
UserInput: What sound should I work on next?
MessageContent: Based on your progress and quiz results, let me point you to the sound that will make the biggest difference right now.
MessageConditions: is_member && login_count >= 6

---

# Login Messages

## Login Prompt
MessageName: lesaep_login_prompt
MessageType: auto
MessageStyle: card
MessageContent: 🔐 **Track Your Progress.** Log in to save your pronunciation journey, access all lessons, and get personalized practice recommendations.
MessageConditions: !logged_in && quiz_taken

---

## Login Success
MessageName: lesaep_login_success
MessageType: auto
MessageStyle: feature
MessageContent: **Welcome back, {name}.** Your pronunciation journey continues. You've completed {lessons_completed} lessons. Ready for the next one?
MessageConditions: logged_in && first_message_after_login

---

# Offer Messages

## LeSAEp Prelaunch Offer
MessageName: lesaep_offer
MessageType: offer
OfferID: lesaep_full
DisplayFormat: featured
MessageContent: 🎤 **Pre-Launch Special from Dainis W. Michel — the World's 2nd Best English Teacher.**

You just heard how your accent sounds to a native speaker. Now imagine fixing it — for real.

LeSAEp teaches you every single sound in Standard American English. Lessons, recordings, IPA training, and an AI pronunciation coach — all yours.

**This is a pre-launch price. Early supporters get grandfathered in — this rate stays yours forever, even when the price goes up.**

👉 **$10/month** or **$100/year** (save $20)

I personally check every new sign-up. If you join, I'll reach out to make sure you're getting exactly what you need.

— Dainis W. Michel
MessageConditions: !is_member && quiz_taken

---

## Sandbox Purchase Button (Testing)
MessageName: sandbox_purchase_lesaep
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎮
UserInput: Test purchase (sandbox)
Action: sandbox_purchase_lesaep
MessageContent: Opening sandbox purchase for LeSAEp — test the full flow with fake money.
MessageConditions: !is_member

---

# Sale Messages

## Purchase Thank You
MessageName: lesaep_purchase_thanks
MessageType: auto
MessageStyle: feature
MessageContent: **Congratulations, {name}.** You now have full access to all pronunciation lessons, video demonstrations, practice recordings, and assessments. Your journey to excellent American English pronunciation starts now.
MessageConditions: is_member && first_message_after_purchase

---

# Content Messages

## Member Welcome
MessageName: lesaep_member_welcome
MessageType: auto
MessageStyle: feature
MessageContent: **Ready to practice, {name}?** You have access to all pronunciation resources. Choose a sound category to focus on, or continue with your personalized learning path.
MessageConditions: is_member && !first_message_after_purchase

---

## Show All Lessons
MessageName: member_all_lessons
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📚
UserInput: Show me all lessons
Action: open_lesson_library
MessageContent: Opening your full lesson library — all pronunciation lessons are ready for you.
MessageConditions: is_member
Keywords: show me all lessons, all lessons, lesson library, browse lessons, see all lessons

---

## Show My Quiz Results
MessageName: member_show_quiz_results
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📊
UserInput: Show my quiz results
Action: show_quiz_results
MessageContent: Here are your accent assessment results — your score and which sounds you nailed versus which ones need more practice.
MessageConditions: is_member && quiz_taken
Keywords: show my quiz results, quiz results, my results, how did i do, my score, quiz score

---

## Quiz Topics — What the Assessment Covered
MessageName: member_quiz_topics
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📋
UserInput: What did the quiz cover?
Action: show_quiz_topics
MessageContent: Here are the 10 American English sound topics the assessment covered — your answers to these questions showed which sounds to focus on first.
MessageConditions: is_member
Keywords: quiz lessons, topics from the quiz, quiz answer sheet, what did the quiz cover, show quiz topics, what are the quiz topics, quiz topic list, 10 topics, what sounds did the quiz test

---

## Quiz Lessons — The Lesson Posts for Each Quiz Topic
MessageName: member_quiz_lessons
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 🎓
UserInput: Show me the lessons covered in the quiz
Action: open_quiz_lessons
MessageContent: Here are the lesson posts that go with the 10 quiz topics — one lesson for each sound the assessment tested.
MessageConditions: is_member
Keywords: show me the lessons from the quiz, lessons covered in the quiz, quiz lesson list, lessons we covered, open quiz lessons, the quiz lessons, show lessons from the quiz

---
