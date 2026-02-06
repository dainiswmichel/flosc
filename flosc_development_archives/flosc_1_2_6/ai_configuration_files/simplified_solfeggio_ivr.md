# Simplified Solfeggio IVR Configuration
# The Michel Hand of Music - Ancient Wisdom, Modern Method
# Sight-Singing and Music Literacy Made Simple

---

# Message Styles

## MessageStyle: pill
Description: Light musical tip bubble
.flosc-style-pill {
  background: rgba(139, 92, 246, 0.1);
  border: 1px solid rgba(139, 92, 246, 0.2);
  border-radius: 18px;
  padding: 8px 16px;
  font-size: 14px;
  color: #5b21b6;
}

## MessageStyle: card
Description: Music lesson card
.flosc-style-card {
  background: linear-gradient(135deg, #f5f3ff, #ede9fe);
  border: 1px solid #c4b5fd;
  border-radius: 12px;
  padding: 16px 20px;
  font-size: 15px;
  box-shadow: 0 2px 8px rgba(139, 92, 246, 0.15);
}

## MessageStyle: feature
Description: Key concept highlight
.flosc-style-feature {
  background: linear-gradient(135deg, #5b21b6, #8b5cf6);
  color: white;
  border-radius: 16px;
  padding: 20px 24px;
  font-size: 16px;
  box-shadow: 0 4px 12px rgba(91, 33, 182, 0.3);
}

---

# Freeline Messages

## Welcome to Simplified Solfeggio
MessageName: solfeggio_welcome
MessageType: auto
MessageStyle: feature
MessageContent: 🎵 **Welcome to Simplified Solfeggio!** The fastest way to improve your sight-singing is also the most ancient. Discover the Michel Hand of Music - putting the piano, the clock, and all of music onto one hand!
MessageConditions: visit_count == 1

---

## Returning Musician
MessageName: solfeggio_return
MessageType: auto
MessageStyle: card
MessageContent: 🎼 **Welcome back, musician!** Ready to continue your solfeggio journey? Whether you're a singer, composer, or instrumentalist, let's keep building your musical literacy.
MessageConditions: visit_count > 1 && !logged_in

---

## The Ancient Secret
MessageName: ancient_secret
MessageType: auto
MessageStyle: card
MessageContent: ✋ **An Ancient Teaching Secret Rediscovered!** Medieval monks used the Guidonian Hand. Ancient Egyptians used cheironomy (hand signs). The Michel Hand of Music modernizes this thousand-year tradition for today's musicians!
MessageConditions: visit_count == 1

---

## The Problem with Traditional Solfeggio
MessageName: solfeggio_problem
MessageType: auto
MessageStyle: card
MessageContent: 🤔 Traditional systems have limitations: **Movable Do** requires on-the-fly key analysis. **Fixed Do** can sprain your tongue with accidentals. **Nashville Numbers** shares movable do's problems. You need something **limitless** that works for ALL music, ALL genres, ALL times!
MessageConditions: lesson_progress >= 1

---

## The Michel Hand Solution
MessageName: michel_hand_intro
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ✋
UserInput: Show me the Michel Hand of Music
MessageContent: The **Michel Hand of Music** puts the entire piano keyboard on ONE hand! Combined with single-syllable solfeggio and the analog clock, you can sight-sing freely in any key, any genre, any complexity level. Your hand becomes your musical reference!
MessageConditions: always

---

## Clock and Piano Connection
MessageName: clock_piano
MessageType: auto
MessageStyle: feature
MessageContent: ⏰🎹 **The Clock-Piano Connection:** If you can read time on an analog clock, you can learn to "draw the piano" on your hand! The 12-hour layout maps perfectly to music's 12 semitones. This visual-kinesthetic approach accelerates learning dramatically!
MessageConditions: lesson_progress >= 1

---

## Single-Syllable Solfeggio
MessageName: single_syllable
MessageType: auto
MessageStyle: card
MessageContent: 🎤 **Single-Syllable Advantage:** Traditional solfeggio uses multi-syllable names that slow you down. Simplified Solfeggio uses quick, single-syllable sounds that your voice can produce at any tempo. Speed AND accuracy!
MessageConditions: lesson_progress >= 2

---

## Sight-Singing Freedom
MessageName: sight_singing_freedom
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📖
UserInput: Help me with sight-singing
MessageContent: 🎯 Sight-singing should feel like **freedom**, not stress! With Simplified Solfeggio, you develop a clear connection between what you SEE on the page, what you FEEL in your hand, and what you HEAR in your voice. No more guessing, hesitating, or waiting for others!
MessageConditions: always

---

## Write Your Songs Down
MessageName: write_songs
MessageType: auto
MessageStyle: card
MessageContent: ✍️ **From Imagination to Notation:** Many musicians can hear music in their heads but struggle to write it down. Simplified Solfeggio bridges that gap. If you can hum it, you can learn to notate it - accurately and quickly!
MessageConditions: lesson_progress >= 2

---

## Rhythm Made Simple
MessageName: rhythm_method
MessageType: auto
MessageStyle: card
MessageContent: 🥁 **Rhythm Simplified:** If you can write rhythms perfectly, you'll be better at reading them. Dainis' method uses simple dots and slashes on blank paper to internalize rhythm patterns. You'll read, write, and interpret rhythms correctly - quickly!
MessageConditions: lesson_progress >= 3

---

## For All Skill Levels
MessageName: all_levels
MessageType: auto
MessageStyle: pill
MessageContent: 🌟 Whether you're a **beginner** just starting out, an **intermediate** musician wanting to level up, or a **professional** seeking mastery - Simplified Solfeggio meets you where you are and takes you further than you imagined!
MessageConditions: visit_count >= 2

---

## Quiz Prompt
MessageName: solfeggio_quiz
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📝
UserInput: Test my solfeggio knowledge
Action: open_quiz
MessageContent: Let's see where you are on your solfeggio journey! This quick assessment will help identify which areas to focus on for maximum improvement.
MessageConditions: lesson_progress >= 2

---

## Free Lesson Offer
MessageName: solfeggio_free_lesson
MessageType: suggested_user_autoprompt
MessageStyle: card
Icon: 🎁
UserInput: Show me a free solfeggio lesson
Action: open_free_lesson
MessageContent: 🎁 **Free Introduction Available!** Experience the Michel Hand of Music basics. Learn how the piano maps to your hand and discover why this ancient technique accelerates sight-singing mastery!
MessageConditions: !is_member

---

## Student Success Stories
MessageName: success_stories
MessageType: auto
MessageStyle: card
MessageContent: 🌟 **Real Results:** Edmund Rumpler, in his mid-70s with no notation skills, wrote his first complete score. Akash Thakkar went from Berklee student to TEDx featured composer and award-nominated game audio designer. Emily Sanders experienced the Golden Buzzer!
MessageConditions: visit_count >= 3

---

# Login Messages

## Login Prompt
MessageName: solfeggio_login_prompt
MessageType: auto
MessageStyle: card
MessageContent: 🔐 **Save Your Progress!** Log in to track your solfeggio development, access all lessons, and get personalized practice recommendations based on your goals.
MessageConditions: !logged_in && visit_count >= 2

---

## Login Success
MessageName: solfeggio_login_success
MessageType: auto
MessageStyle: feature
MessageContent: 🎵 **Welcome back, {name}!** Your musical journey continues. You've completed {lessons_completed} lessons. Ready to unlock more sight-singing freedom?
MessageConditions: logged_in && phase == login

---

# Offer Messages

## Simplified Solfeggio Offer
MessageName: solfeggio_offer
MessageType: offer
MessageStyle: feature
MessageContent: 🎼 **Complete eLearning Package!** Get "Sight-Singing for the 21st Century," plus bonus eBooks "How to Write YOUR Songs Down" and "Become a Better Songwriter" + 1 year Mastermind access. **$208 value for only ${discount_price}!** That's 87% off!
MessageConditions: !is_member && visit_count >= 3

---

## Transform Your Musicianship
MessageName: transformation
MessageType: auto
MessageStyle: card
MessageContent: 🚀 Imagine: **No more sight-singing anxiety.** No more holding back at jam sessions. No more feeling musically illiterate. Feel powerful, in charge, creatively free, confident, and connected to the history of music and composition!
MessageConditions: !is_member && visit_count >= 4

---

## Professional Benefits
MessageName: professional_benefits
MessageType: auto
MessageStyle: card
MessageContent: 💼 **Professional-level musicianship:** Compose more complex works with a clear connection between imagination and the written page. Stop making excuses about being a "natural" musician - become a complete musician!
MessageConditions: !is_member && visit_count >= 5

---

# Sale Messages

## Purchase Thank You
MessageName: solfeggio_purchase_thanks
MessageType: auto
MessageStyle: feature
MessageContent: 🎊 **Congratulations, {name}!** You now have full access to Simplified Solfeggio! Your complete eLearning package includes all eBooks, the Michel Hand of Music instruction, and Mastermind group access. From this moment forward, music-making becomes a joy instead of a challenge!
MessageConditions: is_member && phase == sale

---

# Content Messages

## Member Welcome
MessageName: solfeggio_member_welcome
MessageType: auto
MessageStyle: feature
MessageContent: 🎹 **Ready to practice, {name}?** All resources are at your fingertips. Continue with the Michel Hand lessons, rhythm exercises, or explore the bonus songwriting materials.
MessageConditions: is_member && phase == content

---

## Continue Learning
MessageName: solfeggio_continue
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: ▶️
UserInput: Continue my solfeggio lessons
Action: resume_last_lesson
MessageContent: Let's pick up where you left off! Consistent practice with the Michel Hand builds the muscle memory that makes sight-singing feel natural.
MessageConditions: is_member

---

## Lesson Library
MessageName: solfeggio_library
MessageType: suggested_user_autoprompt
MessageStyle: pill
Icon: 📖
UserInput: Show me all available lessons
Action: open_lesson_library
MessageContent: Browse your complete library: Michel Hand of Music fundamentals, sight-singing exercises, rhythm training, songwriting techniques, and the bonus materials. Each lesson builds on the last!
MessageConditions: is_member

---

## Hand Practice Reminder
MessageName: hand_practice
MessageType: auto
MessageStyle: card
MessageContent: ✋ **Daily Hand Practice:** Draw the piano on your hand for 2 minutes every day. This kinesthetic practice builds neural pathways that make sight-singing automatic. Your hand IS your musical reference - always with you!
MessageConditions: is_member && lesson_progress >= 2

---

## Mastermind Group
MessageName: mastermind_access
MessageType: auto
MessageStyle: pill
MessageContent: 👥 **Mastermind Group:** Connect with fellow musicians on the same journey! Share your progress, ask questions, and get feedback. Your year of Mastermind access is included!
MessageConditions: is_member && lesson_progress >= 3

---

## Become an Instructor
MessageName: instructor_path
MessageType: auto
MessageStyle: card
MessageContent: 🎓 **Teach Simplified Solfeggio!** Ready to help others? You can become a Certified Simplified Solfeggio instructor and share this powerful method with your own students. Ask about certification!
MessageConditions: is_member && lessons_completed >= 10

---

## From Struggle to Mastery
MessageName: struggle_to_mastery
MessageType: auto
MessageStyle: feature
MessageContent: 🦋 **Your Transformation:** From feeling insecure about sight-singing to confidently reading any music. From scribbling ideas you'll forget to properly notating your compositions. This is what Simplified Solfeggio delivers!
MessageConditions: is_member && visit_count >= 5

---
