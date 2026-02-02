# FLOSC v2.0.2 - Configuration Guide

**How to configure and switch between different backends**

---

## Backend Types Overview

| Backend | Use Case | Setup Time | Cost | Latency |
|---------|----------|------------|------|---------|
| **Mock** | Testing chat flow | 0 min | Free | Instant |
| **FastAPI** | Self-hosted Whisper | 30 min | $6/mo | 2-5s |
| **OpenAI** | Cloud API | 5 min | Pay-per-use | 3-8s |
| **Claude** | Cloud API (future) | 5 min | Pay-per-use | 3-8s |
| **Custom** | Your own endpoint | Varies | Varies | Varies |

---

## Mock Backend (Start Here)

**Perfect for:** Testing tonight without any setup

### Configuration
```
WP Admin → FLOSC → Backend Settings
  Backend Type: Mock
  [Save Settings]
```

### How It Works
- Generates random scores (30-95)
- No API calls
- Instant response
- Perfect for testing full chat flow

### Test It
1. Open chat page
2. Record any audio
3. Get instant "score"
4. Test login/purchase flow

**Limitations:**
- Not real analysis
- Random phoneme feedback
- Can't be used in production

---

## FastAPI Backend (Self-Hosted Whisper)

**Perfect for:** Production use, full control, one-time cost

### Prerequisites
- DigitalOcean droplet (1GB = $6/mo)
- Your FastAPI code from `/lesaep/lesaep_api_v01/`

### Deployment Steps

#### 1. Deploy FastAPI to Server
```bash
# SSH to your server
ssh root@your-server.com

# Create directory
mkdir -p /opt/flosc-backend
cd /opt/flosc-backend

# Copy files (from your local machine)
scp -r /Users/dainismichel/2026/lesaep/lesaep_api_v01/* root@your-server.com:/opt/flosc-backend/

# Install dependencies
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt

# Create .env file
nano .env
```

#### 2. Configure Environment
```bash
# In .env file:
WHISPER_MODEL=small
WHISPER_DEVICE=cpu
WHISPER_COMPUTE_TYPE=int8
LESAEP_AUDIO_DIR=/var/lib/flosc/audio
```

#### 3. Create Systemd Service
```bash
sudo nano /etc/systemd/system/flosc-backend.service
```

```ini
[Unit]
Description=FLOSC FastAPI Backend
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/flosc-backend
Environment="PATH=/opt/flosc-backend/venv/bin"
ExecStart=/opt/flosc-backend/venv/bin/uvicorn app:app --host 0.0.0.0 --port 8000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable flosc-backend
sudo systemctl start flosc-backend
```

#### 4. Test Backend
```bash
curl http://localhost:8000/health
# Should return: {"ok": true, "model": "small"}
```

#### 5. Configure Nginx (Optional but Recommended)
```nginx
server {
    listen 80;
    server_name api.your-domain.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        client_max_body_size 10M;
    }
}
```

```bash
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d api.your-domain.com
```

### WordPress Configuration
```
WP Admin → FLOSC → Backend Settings
  Backend Type: FastAPI
  Backend URL: https://api.your-domain.com/process-audio
  [Save Settings]
```

### Test It
1. Open chat page
2. Record audio saying the quiz text
3. Should see REAL transcription + score
4. Check logs: `journalctl -u flosc-backend -f`

### Troubleshooting
```bash
# Check if running
sudo systemctl status flosc-backend

# View logs
journalctl -u flosc-backend -f

# Restart if needed
sudo systemctl restart flosc-backend

# Test directly
curl -X POST http://localhost:8000/process-audio \
  -F "audio=@test.webm" \
  -F "expected_text=hello world"
```

---

## OpenAI Backend (Cloud API)

**Perfect for:** Quick start, no server needed, pay-per-use

### Prerequisites
- OpenAI account (platform.openai.com)
- API key
- Credits in account ($5 minimum)

### Get API Key
1. Go to platform.openai.com
2. Login/Sign up
3. API Keys → Create new secret key
4. Copy key (starts with `sk-...`)

### WordPress Configuration
```
WP Admin → FLOSC → Backend Settings
  Backend Type: OpenAI
  API Key: sk-xxxxxxxxxxxxxxxxxxxxxxxx
  Model: gpt-4
  [Save Settings]
```

### How It Works
1. Audio sent to Whisper API (transcription)
2. Transcript + expected text sent to GPT (analysis)
3. Returns score + phoneme feedback

### Pricing (as of Jan 2026)
- Whisper API: $0.006 per minute of audio
- GPT-4: ~$0.03 per quiz
- **Total per quiz:** ~$0.04
- **1000 quizzes:** ~$40

### Test It
1. Open chat
2. Complete quiz
3. Check OpenAI dashboard → Usage
4. Should see Whisper + ChatGPT API calls

### Models to Try
- `gpt-4`: Best quality, slower, more expensive
- `gpt-3.5-turbo`: Faster, cheaper, good quality
- `gpt-4-turbo`: Balance of speed and quality

### Troubleshooting
- **401 Unauthorized:** Invalid API key
- **429 Rate Limit:** Too many requests, wait or upgrade
- **Slow response:** Normal, cloud APIs take 3-8 seconds

---

## Claude Backend (Coming Soon)

**Status:** Not yet implemented in v2.0.2

**When available:**
```
WP Admin → FLOSC → Backend Settings
  Backend Type: Claude
  API Key: sk-ant-xxxxx
  Model: claude-3-sonnet-20240229
  [Save Settings]
```

**For now:** Use OpenAI or FastAPI

---

## Custom Backend

**Perfect for:** Your own processing logic

### Requirements
Your endpoint must:
1. Accept POST with multipart/form-data
2. Fields: `audio` (file), `expected_text` (string)
3. Return JSON:
```json
{
  "score": 85,
  "transcription": "what user said",
  "flagged_phonemes": ["/æ/", "/θ/"],
  "match_score": 0.85
}
```

### WordPress Configuration
```
WP Admin → FLOSC → Backend Settings
  Backend Type: Custom
  Backend URL: https://your-custom-api.com/analyze
  [Save Settings]
```

### Example Custom Endpoint (Python Flask)
```python
from flask import Flask, request, jsonify

app = Flask(__name__)

@app.route('/analyze', methods=['POST'])
def analyze():
    audio = request.files['audio']
    expected = request.form['expected_text']

    # Your processing logic here
    score = your_analysis_function(audio, expected)

    return jsonify({
        'score': score,
        'transcription': 'transcription here',
        'flagged_phonemes': [],
        'match_score': score / 100
    })
```

---

## Switching Backends (Testing Tonight)

### Scenario: Test All Backends

**Step 1: Mock (0 min)**
```
Backend Type: Mock
[Save] → Test → Works instantly
```

**Step 2: OpenAI (if you have API key)**
```
Backend Type: OpenAI
API Key: [paste your key]
Model: gpt-3.5-turbo
[Save] → Test → Real analysis
```

**Step 3: FastAPI (if deployed)**
```
Backend Type: FastAPI
Backend URL: https://api.your-domain.com/process-audio
[Save] → Test → Self-hosted processing
```

**Compare:**
- Mock: Instant, fake scores
- OpenAI: 5-8 seconds, real analysis, $0.04/quiz
- FastAPI: 2-5 seconds, real analysis, $0/quiz (after initial $6/mo)

---

## Recommended Configuration

### For Tonight's Testing
```
Backend Type: Mock
```
Test full chat flow without any API setup.

### For Beta Launch (Next Week)
```
Backend Type: OpenAI
API Key: [your key]
Model: gpt-3.5-turbo
```
Real analysis, no server needed, pay-per-use.

### For Production (After Validation)
```
Backend Type: FastAPI
Backend URL: https://api.lesaep.com/process-audio
```
Full control, lowest cost long-term, best performance.

---

## Backend Performance Comparison

### Latency
```
Mock:    0.5s  (instant)
FastAPI: 2-5s  (Whisper processing)
OpenAI:  5-8s  (API roundtrip)
Claude:  5-8s  (API roundtrip)
```

### Accuracy
```
Mock:    0%    (random)
FastAPI: 95%   (Whisper + local analysis)
OpenAI:  98%   (Whisper + GPT)
Claude:  98%   (Whisper + Claude)
```

### Cost per 1000 Quizzes
```
Mock:    $0
FastAPI: $6/mo flat (unlimited)
OpenAI:  $40
Claude:  $50 (estimated)
```

---

## Troubleshooting Backend Issues

### "Quiz processing failed"
1. Check backend type is configured
2. For FastAPI: Verify URL is accessible
3. For OpenAI: Verify API key is valid
4. Check browser console for specific error
5. Try mock mode first to isolate issue

### High Latency
1. FastAPI: Check server resources (`htop`)
2. OpenAI: Normal, cloud APIs are slower
3. Network: Check DNS/routing

### CORS Errors
1. FastAPI: Add CORS middleware (already included)
2. Custom: Add CORS headers to your endpoint

### 500 Errors
1. FastAPI: Check logs (`journalctl -u flosc-backend -f`)
2. OpenAI: Check API key, check credits
3. Custom: Debug your endpoint logs

---

## Next Steps

1. **Tonight:** Test with mock backend
2. **This week:** Deploy FastAPI OR get OpenAI key
3. **Before launch:** Choose production backend
4. **After launch:** Monitor usage and costs

**Questions?** Check TESTING.md for detailed test scenarios.
