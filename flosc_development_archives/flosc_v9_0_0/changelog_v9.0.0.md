# FLOSC v9.0.0 - Major Business Logic Rewrite
**Michel TimeStamp: 2026-01-16**

## Major Changes

### Three-Tier Business Logic (FREELINE → GUEST → MEMBER)
Completely rewrote IVR to match actual business flow instead of wishy-washy conditional logic.

**FREELINE (Visitor - Not Logged In)**
- Can take quiz
- **MUST LOGIN** to see score (hard requirement)
- No "Create account" - uses "Login to see my score" button
- Clear messaging: quiz first, then login for results

**GUEST (Logged In - Not Purchased)**
- See quiz score
- Get 1 FREE lesson (randomly selected from missed items)
- **CANNOT retake quiz** (members only)
- CAN retake their free lesson anytime
- See offers (OTO with timer)
- Data persisted: name, email, phone, score
- Can return days/weeks later - everything is saved

**MEMBER (Purchased - Full Access)**
- **CAN retake quiz** with Michel Timestamp tracking
- See complete score breakdown
- Access ALL lessons
- Access ALL quizzes admin created
- Continue where left off
- Progress tracking across sessions

### IVR Condition Changes
- Replaced ALL negative assertions (`!logged_in`, `!quiz_taken`) with positive state checks
- Uses `is_visitor`, `is_guest`, `is_member` throughout
- Conditions are explicit and readable
- No more silent failures from undefined variables

### UX Improvements
- Welcome message shows to EVERYONE (removed login gate)
- "Get started" always available (removed quiz_taken check)
- "View my score" instead of "View free lesson" for guests
- Clear button hierarchy: visitors see "Login to see score", guests see "View my free lesson", members see "Retake quiz"

### Architecture
- Clean separation of concerns by user tier
- Positive assertions prevent silent condition failures
- DOM-based idempotency prevents duplicate messages
- Hardcoded fallbacks ensure chat is always responsive

## Breaking Changes
- IVR structure completely rewritten
- Old condition variables may not work if custom messages added
- Requires update to any custom IVR messages

## Migration Notes
- Backup v8.x IVR before deploying
- Test all three user flows: visitor → guest → member
- Verify quiz modal login requirement works
- Check localStorage clearing on version change
