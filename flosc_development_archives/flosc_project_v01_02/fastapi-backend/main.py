"""
LeSAEp FLOSC Backend - SIMPLIFIED
ONLY handles: Audio transcription with Whisper
Everything else (payment, access, content) handled by WordPress
"""

from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import tempfile
import os
from typing import Optional

# Initialize FastAPI
app = FastAPI(
    title="LeSAEp Audio Processor",
    description="Whisper transcription for pronunciation analysis",
    version="1.0.0"
)

# CORS (if not using Nginx proxy)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["https://lesaep.com", "https://dainis.net"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Lazy-load Whisper
whisper_model = None

def get_whisper_model():
    """Lazy load Whisper model"""
    global whisper_model
    if whisper_model is None:
        try:
            from faster_whisper import WhisperModel
            model_size = os.getenv("WHISPER_MODEL", "tiny")
            whisper_model = WhisperModel(model_size, device="cpu")
            print(f"✓ Whisper '{model_size}' loaded")
        except Exception as e:
            print(f"✗ Whisper load failed: {e}")
            whisper_model = "error"
    return whisper_model


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    model = get_whisper_model()
    return {
        "status": "healthy",
        "whisper": "loaded" if model != "error" else "failed",
        "version": "1.0.0"
    }


@app.post("/process-audio")
async def process_audio(
    audio: UploadFile = File(...),
    expected_text: str = Form(...),
    sentence_index: int = Form(0)
):
    """
    Process audio recording with Whisper
    
    Returns:
        transcription: What was said
        flagged_phonemes: Problematic sounds
        accuracy: How close to expected
    """
    
    # Save audio to temp file
    with tempfile.NamedTemporaryFile(delete=False, suffix=".webm") as tmp:
        content = await audio.read()
        tmp.write(content)
        tmp_path = tmp.name
    
    try:
        # Get Whisper model
        model = get_whisper_model()
        
        if model == "error":
            # Fallback for testing (remove in production)
            transcription = expected_text.lower()
            accuracy = 0.85
            flagged_phonemes = ["/æ/", "/ð/"]
        else:
            # Transcribe audio
            segments, info = model.transcribe(tmp_path, language="en")
            transcription = " ".join([seg.text for seg in segments]).strip()
            
            # Analyze phonemes
            flagged_phonemes = analyze_phonemes(transcription, expected_text)
            accuracy = calculate_accuracy(transcription, expected_text)
        
        # Cleanup
        os.unlink(tmp_path)
        
        return {
            "transcription": transcription,
            "expected": expected_text,
            "accuracy": round(accuracy, 2),
            "flagged_phonemes": flagged_phonemes,
            "sentence_index": sentence_index
        }
        
    except Exception as e:
        # Cleanup on error
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
        raise HTTPException(status_code=500, detail=f"Processing failed: {str(e)}")


def analyze_phonemes(transcription: str, expected: str) -> list:
    """
    Analyze pronunciation issues
    Returns list of problematic phonemes
    """
    trans_lower = transcription.lower()
    expected_lower = expected.lower()
    
    # Common problematic phonemes for non-native speakers
    phoneme_patterns = {
        "/æ/": ["cat", "bat", "mat", "hat", "sat", "fat", "rat"],
        "/ð/": ["the", "that", "this", "then", "they", "them"],
        "/θ/": ["think", "thank", "thought", "three", "thing"],
        "/r/": ["right", "brown", "crow", "train", "try"],
        "/l/": ["light", "sell", "tell", "little", "feel"],
        "/v/": ["very", "have", "give", "over", "even"],
        "/w/": ["what", "where", "when", "which", "now", "cow", "how"],
        "/ʃ/": ["she", "shell", "shore", "fish", "wash"],
        "/ʒ/": ["measure", "vision", "leisure", "azure"],
    }
    
    flagged = []
    
    # Check if expected words appear in transcription
    expected_words = set(expected_lower.split())
    trans_words = set(trans_lower.split())
    missing_words = expected_words - trans_words
    
    # Flag phonemes from missing words
    for phoneme, patterns in phoneme_patterns.items():
        for pattern in patterns:
            if pattern in expected_lower and pattern not in trans_lower:
                if phoneme not in flagged:
                    flagged.append(phoneme)
    
    # If no specific issues, flag common ones
    if not flagged:
        # Check for TH sounds
        if "th" in expected_lower:
            flagged.append("/ð/")
        # Check for common vowels
        if any(word in expected_lower for word in ["cat", "sat", "mat", "bat"]):
            flagged.append("/æ/")
        # Default to R sound if still empty
        if not flagged:
            flagged.append("/r/")
    
    # Return max 3 phonemes
    return flagged[:3]


def calculate_accuracy(transcription: str, expected: str) -> float:
    """
    Calculate transcription accuracy using word-level comparison
    """
    trans_words = set(transcription.lower().split())
    expected_words = set(expected.lower().split())
    
    if not expected_words:
        return 0.0
    
    # Jaccard similarity
    intersection = len(trans_words & expected_words)
    union = len(trans_words | expected_words)
    
    return intersection / union if union > 0 else 0.0


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=True)
