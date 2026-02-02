# LeSAEp FLOSC v02.07

**AI-Powered Pronunciation Coach with Multi-Provider Support**

Complete quiz funnel with audio recording, real-time pronunciation analysis, and intelligent lesson recommendations.

---

## 🎉 What's New in v02.07

### **Audio Recording & Quiz System**
- Browser-based audio recording (MediaRecorder API)
- Real-time waveform visualization
- Pronunciation quiz with counting (1-10) for testing
- Automatic transcription via AssemblyAI/OpenAI/Deepgram
- Pronunciation error detection and lesson mapping
- "1 free lesson + upsell" funnel

### **Multi-Provider STT (Speech-to-Text)**
- **AssemblyAI** - Best accuracy, speaker diarization ($0.0025/quiz)
- **OpenAI Whisper** - Fast, general purpose ($0.001/quiz)
- **Deepgram** - Real-time capable ($0.00072/quiz)
- **Custom Endpoint** - Self-hosted solutions

### **Enhanced Admin UI**
- 4-tab configuration: General | AI Provider | STT Provider | Quiz
- Easy provider switching without code changes
- Clear cost comparisons for each provider
- Quiz mode toggle (counting vs magic sentence)

---

## 🚀 Quick Start

### 1. Install Plugin
```bash
# Upload flosc_v02_07.zip to WordPress
# Navigate to: Plugins → Add New → Upload Plugin
# Activate: LeSAEp FLOSC
```

### 2. Configure Providers

**AI Provider** (for chat coaching):
1. Go to: WP Admin → LeSAEp → AI Provider
2. Select provider: OpenAI / Anthropic / xAI / IVR
3. Enter API key
4. Save changes

**STT Provider** (for quiz transcription):
1. Go to: WP Admin → LeSAEp → Speech-to-Text
2. Select provider: AssemblyAI (recommended)
3. Enter API key from https://www.assemblyai.com/
4. Save changes

**Quiz Settings**:
1. Go to: WP Admin → LeSAEp → Quiz Settings
2. Set mode: "Counting (1-10)" for testing
3. Later: Switch to "Magic Sentence" for production
4. Save changes

### 3. Flush Permalinks
Settings → Permalinks → Save Changes

### 4. Test
Visit: `yoursite.com/lesaep/`

---

## 📋 Features

### **Three User States**

**Visitor (Not Logged In)**:
- Landing page with suggested prompts
- Click "Start free quiz" → Audio recording modal
- Record pronunciation → Get instant analysis
- Login gate after 1 quiz

**Free User (Logged In)**:
- Full chat history in sidebar
- Unlimited quizzes
- Get 1 free lesson per quiz
- Upgrade banner for paid lessons

**Paid User**:
- Everything from Free
- Access to all lessons
- No upgrade nags
- "Pro" badge

### **Quiz Flow**

1. User clicks "Start free quiz"
2. Modal opens with instructions
3. User clicks "Record" → speaks sentence
4. Clicks "Stop" → can play back
5. Clicks "Analyze" → uploads to server
6. Server transcribes (AssemblyAI/Whisper/Deepgram)
7. Analyzes pronunciation errors
8. Maps errors to lessons (5-10 from counting quiz)
9. Displays results in chat:
   - Score (0-100)
   - Errors identified
   - 1 FREE lesson
   - Upsell for remaining lessons

---

## 🏗️ Architecture

### **Multi-Provider Design**

```
WordPress Plugin
├── AI Provider Factory (from v02_06)
│   ├── OpenAI (gpt-4o-mini)
│   ├── Anthropic (claude-3-5-sonnet)
│   ├── xAI (grok-beta)
│   └── IVR (scripted)
│
├── STT Provider Factory (NEW v02_07)
│   ├── AssemblyAI
│   ├── OpenAI Whisper
│   ├── Deepgram
│   └── Custom Endpoint
│
├── Pronunciation Analyzer (NEW)
│   ├── Compare transcript to expected
│   ├── Identify phoneme errors
│   ├── Map to lessons
│   └── Calculate score
│
└── Session Manager (from v02_06)
    ├── user_meta (logged-in)
    └── localStorage (visitors)
```

### **File Structure**

```
flosc_v02_07/
├── lesaep-flosc.php                    Main plugin (920 lines)
├── includes/
│   ├── class-ai-provider-factory.php   AI routing (280 lines)
│   ├── class-stt-provider-factory.php  STT routing (380 lines) ← NEW
│   ├── class-pronunciation-analyzer.php (250 lines) ← NEW
│   ├── class-session-manager.php       Sessions (200 lines)
│   └── class-access-control.php        Permissions (100 lines)
├── templates/
│   └── lesaep-app.php                  UI template (280 lines)
├── assets/
│   ├── css/lesaep-app.css              Styles (920 lines)
│   └── js/lesaep-app.js                Logic (750 lines)
├── README.md                           This file
└── WHATS_NEW_v02_07.md                 Release notes
```

**Total Code:** ~3,880 lines (production-ready)

---

## 🔌 REST API Endpoints

### From v02_06 (Chat)
- `POST /flosc/v1/ai-query` - Chat with AI
- `GET /flosc/v1/sessions` - List user sessions
- `POST /flosc/v1/sessions` - Create session
- `GET /flosc/v1/sessions/{id}` - Load session
- `POST /flosc/v1/sessions/{id}/messages` - Add message
- `GET /flosc/v1/access` - Check user access
- `POST /flosc/v1/referral` - Generate referral link

### NEW in v02_07 (Quiz)
- **`POST /flosc/v1/process-audio`** - Analyze pronunciation
  - Accepts: `audio` (file), `expected_text` (string)
  - Returns: `transcript`, `analysis`, `chat_message`, `free_lesson`, `paid_lessons`

---

## 💰 Cost Comparison

### STT Providers (per 10-second quiz)

| Provider | Cost/Quiz | Features | Best For |
|----------|-----------|----------|----------|
| **AssemblyAI** | $0.0025 | Speaker diarization, high accuracy, accent handling | Production (recommended) |
| **OpenAI Whisper** | $0.001 | Fast, multilingual, no speaker labels | Budget-conscious |
| **Deepgram** | $0.00072 | Real-time, streaming support | High volume |
| **Custom** | $0 | Self-hosted, full control | Privacy/scale |

### AI Providers (per chat message)

| Provider | Cost/Message | Model | Best For |
|----------|--------------|-------|----------|
| **OpenAI** | ~$0.001 | gpt-4o-mini | Affordable coaching |
| **Anthropic** | ~$0.003 | claude-3-5-sonnet | Highest quality |
| **xAI** | ~$0.002 | grok-beta | Fun personality |
| **IVR** | $0 | Scripted | Testing/demos |

### **Example Monthly Costs**

**Scenario: 1,000 users/month, each takes 1 quiz + 3 chat messages**

| Route | STT | AI | Total/Month |
|-------|-----|----|----|
| **Route 1** (All API) | AssemblyAI: $2.50 | OpenAI: $3 | **$5.50** |
| **Route 2** (Hybrid) | Self-hosted: $0 | OpenAI: $3 | **$3.00** |
| **Route 3** (Full Self) | Self-hosted: $0 | Self-hosted: $0 | **$0** (only VPS: ~$20-80/mo) |

---

## 🛠️ Configuration

### General Settings

```
App URL Slug: lesaep
BuddyBoss Group ID: [Your group ID]
Checkout URL: [Your WooCommerce product URL]
```

### AI Provider

```
Provider: anthropic (or openai / xai / ivr)
API Keys:
  - OpenAI: sk-...
  - Anthropic: sk-ant-...
  - xAI: xai-...
```

### STT Provider

```
Provider: assemblyai (recommended)
API Key: [Your AssemblyAI key]
Custom Endpoint: [Optional, for self-hosted]
```

### Quiz Settings

```
Mode: counting (for testing) | magic_sentence (for production)
Sentence: "one two three four five six seven eight nine ten"
```

---

## 🔄 Migration from v02.06

### Automatic
- All v02_06 features preserved
- Chat system works as before
- Sessions/access control unchanged

### New Features Added
- Audio recording modal
- STT provider configuration
- Pronunciation analysis
- Quiz flow
- Lesson recommendations

### No Breaking Changes
- URLs stay the same (/lesaep/)
- API endpoints backward compatible
- Admin settings additive

---

## 📱 Browser Support

- **Chrome/Edge** (latest 2): Full support
- **Firefox** (latest 2): Full support
- **Safari** (latest 2): Full support
- **Mobile Safari**: Full support (iOS 14.5+)
- **Chrome Android**: Full support

**MediaRecorder API Required** (95%+ browser support)

---

## 🔐 Security

- Nonce verification on all REST calls
- Input sanitization (sanitize_text_field, etc.)
- Output escaping (esc_html, esc_attr, etc.)
- API keys stored server-side only
- HTTPS required for audio upload
- Temporary audio files deleted after processing

---

## 🐛 Troubleshooting

### App not loading at /lesaep/
- Go to: Settings → Permalinks → Save (flush rewrite rules)
- Check: WP Admin → LeSAEp → General (verify slug)

### Audio recording not working
- Check browser permissions (camera/microphone)
- Use HTTPS (required by MediaRecorder API)
- Try different browser (Chrome recommended)

### STT not working
- Verify API key entered correctly
- Check provider selected matches key
- Review browser console for errors
- Test with IVR mode first (no API needed)

### AI chat not working
- Verify AI provider configured
- Check API key valid
- Try IVR mode for testing

---

## 📞 Support

- **Documentation:** This README
- **Email:** support@lesaep.com
- **Website:** https://lesaep.com

---

## 📄 License

GPL v2 or later

---

## 🙏 Credits

**Created by:** Dainis W. Michel
**Version:** 2.0.7
**Release Date:** January 8, 2026

**Powered by:**
- AssemblyAI (transcription)
- OpenAI / Anthropic / xAI (AI)
- WordPress + BuddyBoss + WooCommerce

---

**Upgrade today and transform your pronunciation coaching!** 🚀
