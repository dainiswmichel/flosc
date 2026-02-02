# FLOSC Framework Chatbot Demo

## 🎯 What This Is

A **working demonstration** of the complete FLOSC framework flow:

1. **Freeline** - Interactive quiz (no content, just the flow)
2. **Login** - Email/phone capture
3. **Offer** - Personalized results + OTO
4. **Sale** - Simulated purchase
5. **Content** - Access granted

**No backend required.** Just open the HTML file in your browser!

---

## 🚀 How to Run

### Option 1: Double-Click (Easiest)
1. Open the `index.html` file in any web browser
2. That's it!

### Option 2: Local Server (Best)
```bash
# If you have Python 3
python3 -m http.server 8080

# Then open: http://localhost:8080
```

---

## 🎭 The Complete Flow

### 1. **Intro / Freeline**
- Bot greets user
- Offers free pronunciation analysis
- "Yes" → Start quiz
- "No" → Bot persists (max 3 times, then stops)

### 2. **Quiz**
- Shows 3 sentences to record
- User clicks microphone, reads aloud
- Records audio (simulated - just clicks through)
- Each sentence confirmed before moving to next

### 3. **Email Capture / Login**
- After completing all 3 recordings
- Requests email (required)
- Requests phone (optional)
- "Submit & Get Results" button

### 4. **Results + Free Lesson**
- Shows AI "analysis" results
- Lists flagged phonemes (/æ/, /ð/, /r/)
- Offers 1 FREE lesson preview
- User clicks "Show me the free lesson!"

### 5. **Offer (OTO)**
- Shows special one-time offer
- **Full Price**: $575 → **OTO Price**: $144 (75% OFF)
- User chooses offer duration:
  - 1 hour ⏰
  - 1 day 📅  
  - 7 days 🗓️
- Countdown timer starts in bottom-right corner
- Quick replies: "Yes! Get 75% OFF" or "Let me think"

### 6. **Sale**
- "Complete Purchase" button (simulated)
- Shows $144 checkout
- Click to complete
- Success message

### 7. **Content Access**
- Dashboard shows available lessons
- Free users: 1 lesson
- Paid users: All 30+ lessons
- "Start over" button to test again

---

## ⏰ Countdown Timer Options

When user reaches the OTO, they choose:

- **1 hour**: High urgency, best for impulse buyers
- **1 day**: Balanced approach, most common
- **7 days**: Low pressure, good for high-ticket items

Timer shows in bottom-right corner:
```
⏰ LIMITED TIME OFFER
00 : 59 : 45
Hours  Minutes  Seconds
```

---

## 🎨 What's Included

### Visual Features:
✅ Modern chat interface (gradient background)
✅ Typing indicators (animated dots)
✅ Quick reply buttons
✅ Audio recorder UI (simulated)
✅ Email/phone input forms
✅ Countdown timer overlay
✅ Mobile responsive design

### Flow Features:
✅ Bot messages with realistic delays
✅ User message display
✅ State management throughout flow
✅ "No" tracking (blocks after 3)
✅ Recording progress (1 of 3, 2 of 3, etc.)
✅ Configurable OTO duration
✅ Persistent offer expiry
✅ Purchase simulation
✅ Access control (free vs paid)

---

## 🛠️ Customization

Edit `static/js/chatbot.js` to change:

```javascript
// Configuration at top of file
const Config = {
    sentences: [
        "The cat sat on the mat",
        "She sells seashells by the seashore", 
        "How now brown cow"
    ],
    productName: "English Pronunciation Mastery",
    fullPrice: 575,
    otoPrice: 144,
    discount: 75,
    otoDurations: {
        '1hour': 3600,    // seconds
        '1day': 86400,
        '7days': 604800
    }
};
```

---

## 📱 Mobile Responsive

Works perfectly on:
- Desktop browsers
- Tablets
- Mobile phones

The chat interface adapts to screen size automatically.

---

## 🎯 Use Cases

### For You (Testing):
- Walk clients through the framework
- Demonstrate FLOSC concept
- Test different offer durations
- Show stakeholders the flow

### For Development:
- Prototype before building backend
- Test conversation flow
- Validate UX decisions
- Get user feedback early

---

## 🔧 Technical Details

- **Pure HTML/CSS/JS** - No dependencies
- **No backend needed** - Everything runs in browser
- **MediaRecorder API** - For microphone access (optional)
- **LocalStorage** - Could be added for persistence
- **~300 lines of JS** - Simple and readable

---

## 🚀 Next Steps

Once you're happy with the flow:

1. **Add Real Audio Processing**
   - Connect to FastAPI backend
   - Actual Whisper transcription
   - Real phoneme analysis

2. **Add Real Payments**
   - Stripe integration
   - Webhook handling
   - Order confirmation

3. **Add Real Content**
   - WordPress integration
   - Lesson database
   - User accounts

4. **Add Email Automation**
   - OTO reminder emails
   - Abandoned cart sequences
   - Welcome series

---

## 🎉 What You're Seeing

This demo shows you **exactly** what users will experience:

1. Friendly conversational intro
2. Easy 2-minute quiz
3. Immediate value (free lesson)
4. Compelling offer with urgency
5. Simple purchase flow
6. Clear access to content

**The framework works.** This proves it. Now you just need to add the backend!

---

## 💡 Tips

- Try clicking "No thanks" a few times to see persistence
- Test all 3 countdown durations
- Try the "Let me think" option in the offer
- Click "Start over" to test again
- Check on mobile - it's fully responsive

---

**Questions?** Just open `index.html` and click through the flow. It's self-explanatory!

**Refresh** the page at any time to start over.

Enjoy exploring the FLOSC framework! 🎉
