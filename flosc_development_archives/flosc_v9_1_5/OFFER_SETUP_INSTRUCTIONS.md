# v9.1.5 OTO Offer Configuration

## Offer Setup for 1-10 Lessons

Create this offer via: FLOSC → Settings → Offers → Create New Offer

---

## Offer Details

**Offer ID:** `full_access_1_10`

**Headline:**
```
🎯 Get Full Access to All 10 Lessons - Limited Time Offer!
```

**Description:**
```
You just got a FREE lesson that showed you the quality of our content.

Now unlock the complete 1-10 lesson series and get:
- Full access to all 10 numbered lessons
- Complete lesson content (not just previews)
- Lifetime access - learn at your own pace
- No recurring fees - one-time payment

This is placeholder content proving FLOSC works with ANY curriculum.
Once we plug in real content (LeSAEP, solfeggio, scripture, etc.),
the same framework delivers professional educational products.
```

**Features (one per line):**
```
✓ All 10 lessons unlocked
✓ Complete content access
✓ Lifetime availability
✓ Learn at your own pace
✓ Proof FLOSC works with any subject
```

**Price:** `$49`

**CTA Button:** `Get Full Access Now`

**Trigger:** `Quiz Completed` or `First Free Lesson Delivered`

**Condition (optional):** `score < 100` (only show if they didn't get perfect score)

**Urgency Timer:** `15` minutes (creates scarcity)

**Status:** Active ✓

---

## Offer Flow

1. **User takes quiz** → types `4,7,9`
2. **Scores 30%** (3 out of 10 correct)
3. **System picks missed lesson** → e.g., lesson 8
4. **Delivers free lesson** → Shows complete lesson_08.md content
5. **Shows OTO offer** → "Get Full Access to All 10 Lessons"
6. **User buys** → Unlocks lessons 1-10
7. **Full access** → Can view all lesson content

---

## Integration Points

**Frontend (flosc-app.js):**
- After quiz submission, check `score < 100`
- Request free lesson: `/wp-json/flosc/v1/free-lesson`
- Display lesson content in chat
- Show offer modal/message after lesson delivery

**Backend (flosc.php REST API):**
- Endpoint: `/free-lesson`
- Logic: Pick random from `quiz_results['details']['missed']`
- Load: `ai_configuration_files/lesson_0X.md`
- Return: Lesson content + offer details

**IVR Messages:**
- Phase: OFFER
- Condition: `quiz_taken && score < 100`
- Message: Trigger OTO offer presentation

---

## Testing the Flow

1. Navigate to `/app/` on your site
2. Take quiz: Type `1,2,5,8` (40% score)
3. Verify: System delivers ONE free lesson (e.g., #3, #4, #6, #7, #9, or #10)
4. Check: Offer appears with 15-minute countdown
5. Click: CTA button processes payment
6. Confirm: All 10 lessons unlock after purchase

---

## Future: Real Content

Replace skeleton lessons 1-10 with:
- **LeSAEP:** Standard American English pronunciation lessons
- **Solfeggio:** Music theory chatbot lessons
- **Scripture:** Personalized Bible reading guides
- **Any curriculum:** FLOSC is content-independent

Same offer structure, same flow, different content.

This proves the framework works BEFORE investing in content creation.
