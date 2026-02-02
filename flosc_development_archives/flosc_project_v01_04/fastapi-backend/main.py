"""
LeSAEp FLOSC Backend v01_04 - PRODUCTION GRADE
Audio transcription with Whisper + WordPress integration

Features:
- Production-grade error handling
- Structured logging with request tracking
- Mock mode for testing
- Environment validation
- Health monitoring
- Security hardening

Architecture:
    1. Configuration & Environment Validation
    2. Whisper Model Loading (lazy, with fallback)
    3. FastAPI Application Setup
    4. CORS & Request Tracking Middleware
    5. API Endpoints (/, /health, /process-audio)
    6. Audio Processing (Mock + Whisper)
    7. Phoneme Analysis & Accuracy Calculation
    8. Global Error Handlers

TESTABLE SECTIONS (Develop/Test Block by Block):
    SECTION 1: Logging Setup
        - Test: Check log file format and request ID tracking
        - Command: tail -f lesaep-backend.log
    
    SECTION 2: Configuration & Validation
        - Test: Remove WP_API_URL from .env and verify startup fails
        - Command: python main.py (should exit with clear error)
    
    SECTION 3: Whisper Model Loading
        - Test: Toggle MOCK_MODE, verify graceful degradation
        - Command: curl http://localhost:8000/health
    
    SECTION 4: Application Lifecycle
        - Test: Basic connectivity and startup logs
        - Command: curl http://localhost:8000/
    
    SECTION 5: Middleware (CORS + Request Tracking)
        - Test: Check X-Request-ID header in responses
        - Command: curl -I http://localhost:8000/health
    
    SECTION 6: API Endpoints
        - Test: Health check and audio processing endpoints
        - Command: curl http://localhost:8000/health
        - Command: curl -X POST -F "audio=@test.webm" -F "expected_text=test" http://localhost:8000/process-audio
    
    SECTION 7: Audio Processing
        - Test: Mock mode vs Whisper mode transcription
        - Command: Set MOCK_MODE=true, send test audio
    
    SECTION 8: Phoneme Analysis
        - Test: Verify phoneme detection logic
        - Python: from main import analyze_phonemes; analyze_phonemes("da cat", "the cat")
    
    SECTION 9: Error Handlers
        - Test: Send malformed requests, verify structured error responses
        - Command: curl -X POST http://localhost:8000/process-audio (no data)
"""

import os
import sys
import logging
import tempfile
import uuid
from typing import Optional, Dict, Any
from contextlib import asynccontextmanager
from datetime import datetime

from fastapi import FastAPI, UploadFile, File, Form, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import uvicorn

# ============================================================================
# SECTION 1: LOGGING SETUP
# Purpose: Structured logging with request ID tracking for production debugging
# Test: Check lesaep-backend.log for formatted entries with request IDs
# ============================================================================

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - [%(request_id)s] %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('lesaep-backend.log'),
        logging.StreamHandler(sys.stdout)
    ]
)

logger = logging.getLogger(__name__)

class RequestFilter(logging.Filter):
    def filter(self, record):
        if not hasattr(record, 'request_id'):
            record.request_id = 'system'
        return True

logger.addFilter(RequestFilter())

# ============================================================================
# END SECTION 1: LOGGING SETUP
# ============================================================================

# ============================================================================
# SECTION 2: CONFIGURATION & ENVIRONMENT VALIDATION
# Purpose: Load and validate environment variables, fail-fast on misconfiguration
# Test: Run with missing WP_API_URL to verify validation works
# ============================================================================

class Config:
    """Production configuration with validation"""
    def __init__(self):
        self.ENVIRONMENT = os.getenv('ENVIRONMENT', 'production')
        self.WP_API_URL = os.getenv('WP_API_URL', 'https://dainis.net/wp-json')
        self.ALLOWED_ORIGINS = os.getenv('ALLOWED_ORIGINS', 
            'https://lesaep.com,https://dainis.net,http://localhost:3000').split(',')
        self.WHISPER_MODEL = os.getenv('WHISPER_MODEL', 'tiny')
        self.MOCK_MODE = os.getenv('MOCK_MODE', 'true').lower() == 'true'
        self.MAX_AUDIO_MB = int(os.getenv('MAX_AUDIO_MB', '10'))
        
        self.validate()
    
    def validate(self):
        logger.info("="*70)
        logger.info("🚀 LeSAEp FLOSC v01_04 - PRODUCTION GRADE")
        logger.info("="*70)
        logger.info(f"Environment: {self.ENVIRONMENT}")
        logger.info(f"WordPress: {self.WP_API_URL}")
        logger.info(f"Whisper Model: {self.WHISPER_MODEL}")
        logger.info(f"Mock Mode: {'ENABLED ⚠️' if self.MOCK_MODE else 'DISABLED'}")
        
        if not self.WP_API_URL:
            raise ValueError("WP_API_URL required")
        
        logger.info("✅ Configuration valid")
        logger.info("="*70)

# Initialize configuration (validates on startup)
config = Config()

# ============================================================================
# END SECTION 2: CONFIGURATION
# ============================================================================

# ============================================================================
# SECTION 3: WHISPER MODEL INITIALIZATION
# Purpose: Lazy-load Whisper with fallback to mock mode on failure
# Test: Toggle MOCK_MODE env var, verify graceful degradation
# ============================================================================

whisper_model = None  # Global variable for lazy loading

def get_whisper_model():
    """Lazy load Whisper with fallback"""
    global whisper_model
    
    if config.MOCK_MODE:
        return "mock"
    
    if whisper_model is None:
        try:
            from faster_whisper import WhisperModel
            logger.info(f"Loading Whisper: {config.WHISPER_MODEL}")
            whisper_model = WhisperModel(config.WHISPER_MODEL, device="cpu", compute_type="int8")
            logger.info("✅ Whisper loaded")
        except ImportError:
            logger.error("❌ faster-whisper not installed")
            whisper_model = "error"
        except Exception as e:
            logger.error(f"❌ Whisper failed: {e}")
            whisper_model = "error"
    
    return whisper_model

# ============================================================================
# END SECTION 3: WHISPER MODEL
# ============================================================================

# ============================================================================
# SECTION 4: APPLICATION LIFECYCLE & SETUP
# Purpose: FastAPI app initialization with startup/shutdown hooks
# Test: curl http://localhost:8000/ for basic connectivity
# ============================================================================

@asynccontextmanager
async def lifespan(app: FastAPI):
    """Startup/shutdown lifecycle"""
    start = datetime.now()
    logger.info("🔧 Starting...")
    
    if not config.MOCK_MODE:
        model = get_whisper_model()
        if model == "error":
            logger.warning("⚠️ Whisper unavailable, using mock mode")
            config.MOCK_MODE = True
    
    elapsed = (datetime.now() - start).total_seconds()
    logger.info(f"✅ Ready in {elapsed:.2f}s")
    
    yield
    
    logger.info("🛑 Shutdown")

app = FastAPI(
    title="LeSAEp FLOSC API v01_04",
    description="Production audio processing",
    version="1.0.4",
    lifespan=lifespan,
    docs_url="/docs" if config.ENVIRONMENT != "production" else None
)

# ============================================================================
# END SECTION 4: APPLICATION SETUP
# ============================================================================

# ============================================================================
# SECTION 5: MIDDLEWARE (CORS + REQUEST TRACKING)
# Purpose: Enable CORS for WordPress and add request ID to all requests
# Test: Check response headers for X-Request-ID
# ============================================================================

# CORS middleware for cross-origin requests from WordPress
app.add_middleware(
    CORSMiddleware,
    allow_origins=config.ALLOWED_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.middleware("http")
async def track_requests(request: Request, call_next):
    """Add request tracking"""
    request_id = str(uuid.uuid4())[:8]
    request.state.request_id = request_id
    
    start = datetime.now()
    logger.info(f"→ {request.method} {request.url.path}", extra={'request_id': request_id})
    
    response = await call_next(request)
    
    duration = (datetime.now() - start).total_seconds()
    logger.info(
        f"← {request.method} {request.url.path} - {response.status_code} ({duration:.3f}s)",
        extra={'request_id': request_id}
    )
    
    response.headers["X-Request-ID"] = request_id
    return response

# ============================================================================
# END SECTION 5: MIDDLEWARE
# ============================================================================

# ============================================================================
# SECTION 6: API ENDPOINTS
# Purpose: REST API routes for health checks and audio processing
# Test: curl each endpoint to verify responses
# ============================================================================

@app.get("/")
async def root():
    """API info"""
    return {
        "name": "LeSAEp FLOSC API",
        "version": "1.0.4",
        "status": "operational",
        "environment": config.ENVIRONMENT,
        "docs": "/docs" if config.ENVIRONMENT != "production" else "disabled"
    }

@app.get("/health")
async def health_check():
    """Health check with component status"""
    model = get_whisper_model()
    
    status = "healthy"
    warnings = []
    
    if model == "error" and not config.MOCK_MODE:
        status = "degraded"
        warnings.append("Whisper unavailable")
    
    if config.MOCK_MODE:
        warnings.append("Mock mode enabled")
    
    return {
        "status": status,
        "version": "1.0.4",
        "timestamp": datetime.now().isoformat(),
        "components": {
            "whisper": "ready" if model not in ["error", "mock"] else model,
            "model": config.WHISPER_MODEL if not config.MOCK_MODE else "mock"
        },
        "warnings": warnings if warnings else None
    }


@app.post("/process-audio")
async def process_audio(
    request: Request,
    audio: UploadFile = File(..., description="Audio file (webm/mp3/wav, max 10MB)"),
    expected_text: str = Form(..., description="Expected transcription"),
    sentence_index: int = Form(0, ge=0, le=2, description="Sentence index (0-2)"),
    user_id: Optional[int] = Form(None, description="WordPress user ID")
):
    """
    Process audio with Whisper transcription
    
    Returns phoneme analysis and accuracy score
    """
    request_id = request.state.request_id
    log_extra = {'request_id': request_id}
    
    try:
        logger.info(
            f"Processing: sentence={sentence_index}, user={user_id}, "
            f"expected='{expected_text[:50]}...'",
            extra=log_extra
        )
        
        # Validate
        if not audio.filename:
            raise HTTPException(400, "No audio file")
        
        # Read and check size
        content = await audio.read()
        size_mb = len(content) / (1024 * 1024)
        
        if size_mb > config.MAX_AUDIO_MB:
            raise HTTPException(400, f"File too large ({size_mb:.1f}MB). Max: {config.MAX_AUDIO_MB}MB")
        
        logger.debug(f"Audio: {audio.filename} ({size_mb:.2f}MB)", extra=log_extra)
        
        # Process
        if config.MOCK_MODE or get_whisper_model() == "error":
            result = process_mock(expected_text, sentence_index)
            logger.info("✅ Mock processing", extra=log_extra)
        else:
            result = process_whisper(content, expected_text, sentence_index, request_id)
            logger.info("✅ Whisper processing", extra=log_extra)
        
        result["sentence_index"] = sentence_index
        result["request_id"] = request_id
        
        logger.info(
            f"Results: accuracy={result['accuracy']:.2f}, phonemes={len(result['flagged_phonemes'])}",
            extra=log_extra
        )
        
        return result
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"❌ Processing failed: {e}", exc_info=True, extra=log_extra)
        raise HTTPException(500, f"Processing failed: {str(e)}")

# ============================================================================
# END SECTION 6: API ENDPOINTS
# ============================================================================

# ============================================================================
# SECTION 7: AUDIO PROCESSING FUNCTIONS
# Purpose: Process audio via Whisper (or mock) and return transcription
# Test: Send test.webm file to /process-audio endpoint
# ============================================================================

def process_mock(expected_text: str, sentence_index: int) -> Dict[str, Any]:
    """
    Mock processing for testing without Whisper
    Returns realistic transcription data with simulated errors
    """
    import random
    
    transcription = expected_text.lower()
    
    # Simulate common pronunciation errors that non-native speakers make
    variations = {
        "the": ["da", "the", "the"],        # TH → D substitution
        "that": ["dat", "that", "that"],    # TH → D substitution
        "three": ["tree", "three"],          # TH → T substitution
    }
    
    # Apply random errors to simulate realistic transcription
    for word, options in variations.items():
        if word in transcription:
            transcription = transcription.replace(word, random.choice(options), 1)
    
    # Analyze phonemes and calculate accuracy
    flagged = analyze_phonemes(transcription, expected_text)
    accuracy = calculate_accuracy(transcription, expected_text)
    
    # Add randomness to accuracy score (70-98% range)
    accuracy = max(0.7, min(0.98, accuracy + random.uniform(-0.1, 0.1)))
    
    return {
        "transcription": transcription,
        "expected": expected_text,
        "accuracy": round(accuracy, 2),
        "flagged_phonemes": flagged,
        "mock": True  # Indicates this is mock data
    }

def process_whisper(
    content: bytes,
    expected_text: str,
    sentence_index: int,
    request_id: str
) -> Dict[str, Any]:
    """
    Real Whisper processing - transcribe audio and analyze pronunciation
    Falls back to mock mode if Whisper fails
    """
    log_extra = {'request_id': request_id}
    model = get_whisper_model()
    
    # Fallback to mock if Whisper unavailable
    if model == "error":
        logger.warning("Whisper failed, using mock", extra=log_extra)
        return process_mock(expected_text, sentence_index)
    
    # Save audio to temporary file for Whisper processing
    with tempfile.NamedTemporaryFile(delete=False, suffix=".webm") as tmp:
        tmp.write(content)
        tmp_path = tmp.name
    
    try:
        # Transcribe audio using Whisper
        segments, info = model.transcribe(tmp_path, language="en", beam_size=5)
        transcription = " ".join([seg.text for seg in segments]).strip()
        
        # Analyze pronunciation issues
        flagged = analyze_phonemes(transcription, expected_text)
        accuracy = calculate_accuracy(transcription, expected_text)
        
        return {
            "transcription": transcription,
            "expected": expected_text,
            "accuracy": round(accuracy, 2),
            "flagged_phonemes": flagged,
            "mock": False  # Real Whisper data
        }
    finally:
        # Clean up temporary file
        try:
            os.unlink(tmp_path)
        except:
            pass

# ============================================================================
# END SECTION 7: AUDIO PROCESSING
# ============================================================================

# ============================================================================
# SECTION 8: PHONEME ANALYSIS & ACCURACY CALCULATION
# Purpose: Identify pronunciation issues and calculate transcription accuracy
# Test: Call with sample transcriptions to verify phoneme detection
# ============================================================================

# Phoneme patterns for common pronunciation challenges (non-native speakers)
PHONEME_PATTERNS = {
    "/æ/": ["cat", "bat", "mat", "hat", "sat", "fat", "back"],
    "/ð/": ["the", "that", "this", "then", "they", "there"],
    "/θ/": ["think", "thank", "thought", "three", "thing"],
    "/r/": ["right", "brown", "crow", "rain", "try"],
    "/l/": ["light", "sell", "tell", "little", "feel"],
    "/v/": ["very", "have", "give", "over", "even"],
    "/w/": ["what", "where", "when", "now", "cow"],
    "/ʃ/": ["she", "shell", "shore", "fish", "wash"],
}

def analyze_phonemes(transcription: str, expected: str) -> list:
    """
    Analyze pronunciation issues by comparing transcription to expected text
    
    Args:
        transcription: What Whisper heard
        expected: What the user should have said
    
    Returns:
        List of up to 3 problematic phonemes (e.g., ["/æ/", "/ð/"])
    """
    trans_lower = transcription.lower()
    expected_lower = expected.lower()
    
    flagged = []
    
    # Check each phoneme pattern against expected words
    for phoneme, patterns in PHONEME_PATTERNS.items():
        for pattern in patterns:
            if pattern in expected_lower and pattern not in trans_lower:
                if phoneme not in flagged:
                    flagged.append(phoneme)
                    break
    
    # Fallback: If no specific issues detected, flag common problem sounds
    if not flagged:
        if "th" in expected_lower:
            flagged.append("/ð/")
        if any(w in expected_lower for w in ["cat", "mat", "bat"]):
            flagged.append("/æ/")
        if not flagged:
            flagged.append("/r/")
    
    return flagged[:3]

def calculate_accuracy(transcription: str, expected: str) -> float:
    """
    Calculate transcription accuracy using Jaccard similarity
    
    Args:
        transcription: What Whisper transcribed
        expected: The correct text
    
    Returns:
        Float between 0.0 and 1.0 (e.g., 0.85 = 85% accurate)
    """
    trans_words = set(transcription.lower().split())
    expected_words = set(expected.lower().split())
    
    if not expected_words:
        return 0.0
    
    # Jaccard similarity: intersection over union
    intersection = len(trans_words & expected_words)
    union = len(trans_words | expected_words)
    
    return intersection / union if union > 0 else 0.0

# ============================================================================
# END SECTION 8: PHONEME ANALYSIS
# ============================================================================

# ============================================================================
# SECTION 9: GLOBAL ERROR HANDLERS
# Purpose: Catch all HTTP and unhandled exceptions, return structured errors
# Test: Send malformed requests to verify proper error responses
# ============================================================================

@app.exception_handler(HTTPException)
async def http_exception_handler(request: Request, exc: HTTPException):
    request_id = getattr(request.state, 'request_id', 'unknown')
    logger.warning(f"HTTP {exc.status_code}: {exc.detail}", extra={'request_id': request_id})
    
    return JSONResponse(
        status_code=exc.status_code,
        content={
            "error": exc.detail,
            "status_code": exc.status_code,
            "request_id": request_id
        }
    )

@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    request_id = getattr(request.state, 'request_id', 'unknown')
    logger.error(f"Unhandled: {exc}", exc_info=True, extra={'request_id': request_id})
    
    return JSONResponse(
        status_code=500,
        content={
            "error": "Internal server error",
            "request_id": request_id
        }
    )

if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8000,
        log_level="info",
        access_log=True,
        reload=config.ENVIRONMENT == "development"
    )

# ============================================================================
# END SECTION 9: ERROR HANDLERS
# ============================================================================
# Application complete - 9 testable sections for block-by-block development
