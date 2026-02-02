# v9.1.5 Quiz Configuration

## Quiz Setup Instructions

Configure the Simple Scoring quiz via WordPress admin:

### 1. Navigate to Quiz Tab
- FLOSC → Settings → Quiz
- Quiz Type: ✍️ Simple Scoring (already selected by default)

### 2. Quiz Content (Correct Answer)
Replace the quiz content field with:
```
1,2,3,4,5,6,7,8,9,10
```

This is the CORRECT answer users need to type to get 100%.

### 3. How Scoring Works

**Perfect Score (100%):**
- User types: `1,2,3,4,5,6,7,8,9,10`
- Result: 10/10 correct = 100%
- Action: No purchase needed (they proved competence)

**Partial Score (30%):**
- User types: `4,7,9`
- Result: 3/10 correct = 30%
- Action: Give ONE free lesson from missed numbers (e.g., lesson 8)

**Any Incomplete Answer:**
- User types: `1,2,3,5,6`
- Result: 5/10 correct = 50%
- Missed: 4, 7, 8, 9, 10
- Action: Give ONE free lesson (pick randomly from missed or always give #8)

### 4. Free Lesson Logic

After incomplete quiz:
1. System picks ONE lesson user missed
2. Deliver full content (e.g., lesson_08.md)
3. Show OTO offer for full 1-10 access

### 5. Quiz Instructions

Update the quiz prompt in IVR Messages tab:
```markdown
## Quiz: Type the Numbers 1-10

**Instructions:** Type the numbers from 1 to 10, separated by commas.

**Example:** 1,2,3,4,5,6,7,8,9,10

This tests your knowledge of the complete 10-lesson sequence.
```

### 6. Response Templates

**High Score (90-100%):**
"Excellent! You got {score}% correct. You clearly know the material!"

**Good Score (70-89%):**
"Good work! You scored {score}%. Here's a free lesson to help with what you missed."

**Needs Improvement (< 70%):**
"You scored {score}%. Don't worry - here's a complete lesson to help you improve!"

---

## Backend Implementation Notes

The quiz analysis happens in:
- `class-simple-scoring-quiz.php` → `analyze()` method
- Returns: `score`, `correct`, `incorrect`, `missed` array

Free lesson delivery:
- Check `details.missed` array from quiz results
- Pick random lesson from missed (or always pick #8 as example)
- Load content from `ai_configuration_files/lesson_0X.md`
- Deliver via chat or redirect to lesson page

Offer trigger:
- After free lesson delivery
- Show OTO for full 1-10 access
- Price: $49 (example)
- Timer: 15 minutes (optional urgency)
