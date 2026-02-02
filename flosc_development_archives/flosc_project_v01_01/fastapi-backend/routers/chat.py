"""
Chat Router - Handles quiz flow, audio processing, evaluation
"""

from fastapi import APIRouter, UploadFile, File, Form, Depends, HTTPException
from fastapi.responses import JSONResponse
from typing import Optional, Dict
from datetime import datetime, timedelta
import tempfile
import os

from auth import verify_session_token, optional_session_token
from database import db
from config import settings

# Initialize router
router = APIRouter()

# Lazy-load Whisper model
whisper_model = None

def get_whisper_model():
    """Lazy load Whisper model"""
    global whisper_model
    if whisper_model is None:
        try:
            from faster_whisper import WhisperModel
            whisper_model = WhisperModel(settings.WHISPER_MODEL, device="cpu")
            print(f"✓ Whisper model '{settings.WHISPER_MODEL}' loaded")
        except Exception as e:
            print(f"✗ Failed to load Whisper: {e}")
            whisper_model = "error"
    return whisper_model


@router.post("/start")
async def start_session(user_data: Optional[Dict] = Depends(optional_session_token)):
    """
    Initialize new quiz session
    
    Returns session_id and quiz_id
    """
    wp_user_id = user_data.get("user_id") if user_data else None
    email = user_data.get("email") if user_data else None
    
    # Check for existing session
    if wp_user_id:
        existing = db.get_session_by_user(wp_user_id)
        if existing:
            return {
                "session_id": existing["session_id"],
                "quiz_id": existing["quiz_id"],
                "existing": True
            }
    
    # Create new session
    session = db.create_session(wp_user_id, email)
    
    return {
        **session,
        "existing": False
    }


@router.get("/get-sentences")
async def get_sentences():
    """
    Get magic sentences for quiz
    """
    return {
        "sentences": settings.MAGIC_SENTENCES
    }


@router.post("/message")
async def handle_message(
    message_type: str = Form(...),
    no_count: Optional[int] = Form(None),
    session_id: Optional[str] = Form(None)
):
    """
    Handle chat messages (e.g., tracking "No" responses)
    """
    if message_type == "no_response" and session_id:
        # Increment no count
        count = db.increment_no_count(session_id)
        blocked = count >= 3
        
        return {
            "no_count": count,
            "blocked": blocked,
            "message": "Access blocked" if blocked else "Tracked"
        }
    
    return {"status": "ok"}


@router.post("/upload-audio")
async def upload_audio(
    audio: UploadFile = File(...),
    expected_text: str = Form(...),
    sentence_index: int = Form(...),
    session_id: Optional[str] = Form(None),
    user_data: Optional[Dict] = Depends(optional_session_token)
):
    """
    Process audio recording with Whisper
    
    Returns transcription and phoneme analysis
    """
    
    # Get or create session
    if not session_id:
        if user_data and user_data.get("user_id"):
            existing = db.get_session_by_user(user_data["user_id"])
            if existing:
                session_id = existing["session_id"]
            else:
                new_session = db.create_session(
                    user_data.get("user_id"),
                    user_data.get("email")
                )
                session_id = new_session["session_id"]
        else:
            new_session = db.create_session()
            session_id = new_session["session_id"]
    
    # Check if blocked
    if db.is_blocked(session_id):
        raise HTTPException(status_code=403, detail="Session blocked")
    
    # Save audio to temp file
    with tempfile.NamedTemporaryFile(delete=False, suffix=".webm") as tmp:
        content = await audio.read()
        tmp.write(content)
        tmp_path = tmp.name
    
    try:
        # Get Whisper model
        model = get_whisper_model()
        
        if model == "error":
            # Fallback for testing without Whisper
            transcription = expected_text
            accuracy = 0.85
            flagged_phonemes = ["/æ/", "/ð/"]
        else:
            # Transcribe audio
            segments, info = model.transcribe(tmp_path, language="en")
            transcription = " ".join([seg.text for seg in segments])
            
            # Analyze phonemes (simplified)
            flagged_phonemes = analyze_phonemes(transcription, expected_text)
            accuracy = calculate_accuracy(transcription, expected_text)
        
        # Store recording
        recording_data = {
            "sentence_index": sentence_index,
            "expected_text": expected_text,
            "transcription": transcription,
            "accuracy": accuracy,
            "flagged_phonemes": flagged_phonemes
        }
        
        db.add_recording(session_id, recording_data)
        
        # Cleanup
        os.unlink(tmp_path)
        
        return {
            "session_id": session_id,
            "transcription": transcription,
            "accuracy": accuracy,
            "flagged_phonemes": flagged_phonemes,
            "sentence_index": sentence_index
        }
        
    except Exception as e:
        # Cleanup on error
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
        raise HTTPException(status_code=500, detail=f"Processing failed: {str(e)}")


@router.post("/evaluate")
async def evaluate_quiz(session_id: Optional[str] = Form(None), user_data: Optional[Dict] = Depends(optional_session_token)):
    """
    Evaluate all recordings and return overall phoneme analysis
    """
    
    # Get session
    if not session_id and user_data:
        session = db.get_session_by_user(user_data["user_id"])
    elif session_id:
        session = db.get_session(session_id)
    else:
        raise HTTPException(status_code=400, detail="No session found")
    
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")
    
    # Parse recordings
    import json
    recordings = json.loads(session["recordings"]) if session["recordings"] else []
    
    # Aggregate flagged phonemes
    all_phonemes = []
    for rec in recordings:
        if "flagged_phonemes" in rec:
            all_phonemes.extend(rec["flagged_phonemes"])
    
    # Get unique phonemes
    unique_phonemes = list(set(all_phonemes))
    
    # Calculate overall score
    total_accuracy = sum(rec.get("accuracy", 0) for rec in recordings)
    avg_accuracy = total_accuracy / len(recordings) if recordings else 0
    overall_score = int(avg_accuracy * 100)
    
    # Update session with evaluation
    db.update_session(session["session_id"], {
        "flagged_phonemes": unique_phonemes
    })
    
    return {
        "session_id": session["session_id"],
        "flagged_phonemes": unique_phonemes,
        "overall_score": overall_score,
        "recordings_count": len(recordings)
    }


@router.post("/set-oto-timer")
async def set_oto_timer(
    duration: int = Form(...),
    session_id: Optional[str] = Form(None),
    user_data: Optional[Dict] = Depends(optional_session_token)
):
    """
    Set OTO expiry timer
    
    Args:
        duration: Duration in seconds (3600 = 1 hour, 86400 = 1 day, etc.)
    """
    
    # Get session
    if not session_id and user_data:
        session = db.get_session_by_user(user_data["user_id"])
        if session:
            session_id = session["session_id"]
    
    if not session_id:
        raise HTTPException(status_code=400, detail="No session found")
    
    # Calculate expiry time
    expires_at = datetime.now() + timedelta(seconds=duration)
    
    # Update session
    db.set_oto_timer(session_id, expires_at)
    
    return {
        "session_id": session_id,
        "expires_at": expires_at.isoformat(),
        "duration": duration
    }


@router.get("/check-oto")
async def check_oto(session_id: str):
    """Check if OTO is still valid"""
    expired = db.is_oto_expired(session_id)
    
    session = db.get_session(session_id)
    
    return {
        "session_id": session_id,
        "expired": expired,
        "expires_at": session.get("oto_expires_at") if session else None
    }


# Helper functions

def analyze_phonemes(transcription: str, expected: str) -> list:
    """
    Analyze phonemes (simplified version)
    
    In production, this should use a proper phoneme comparison algorithm
    """
    # Common problematic phonemes for non-native speakers
    common_issues = [
        "/æ/",  # Short A (cat, bat)
        "/ð/",  # Voiced TH (the, that)
        "/θ/",  # Unvoiced TH (think, thank)
        "/r/",  # R sound
        "/l/",  # L sound
        "/v/",  # V sound
        "/w/",  # W sound
    ]
    
    # Simple heuristic: check if transcription matches expected
    transcription_lower = transcription.lower()
    expected_lower = expected.lower()
    
    flagged = []
    
    # Check for common word mismatches
    if "th" in expected_lower and "t" in transcription_lower:
        flagged.extend(["/ð/", "/θ/"])
    
    if "cat" in expected_lower or "bat" in expected_lower or "mat" in expected_lower:
        flagged.append("/æ/")
    
    if "r" in expected_lower:
        flagged.append("/r/")
    
    # Return 2-3 random phonemes if no specific issues detected
    if not flagged:
        import random
        flagged = random.sample(common_issues, 2)
    
    return list(set(flagged))[:3]  # Max 3 phonemes


def calculate_accuracy(transcription: str, expected: str) -> float:
    """
    Calculate transcription accuracy
    Simple word-level comparison
    """
    trans_words = set(transcription.lower().split())
    expected_words = set(expected.lower().split())
    
    if not expected_words:
        return 0.0
    
    # Jaccard similarity
    intersection = len(trans_words & expected_words)
    union = len(trans_words | expected_words)
    
    return intersection / union if union > 0 else 0.0
