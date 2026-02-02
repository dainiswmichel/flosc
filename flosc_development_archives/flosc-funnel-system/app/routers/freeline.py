"""
Freeline Router - Quiz Submission and Processing
"""
from fastapi import APIRouter, HTTPException, UploadFile, File, Form
from fastapi.responses import JSONResponse
from app.models import QuizSubmission, QuizResult, SentenceRecording
from app.database import db
from app.services.whisper_service import transcribe_audio
from app.services.phoneme_matcher import identify_phoneme_errors, analyze_quiz_results
from app.utils.csv_loader import load_magic_sentences
from app.config import settings
import base64
import uuid
from pathlib import Path
import os
import logging
from typing import List
from datetime import datetime

router = APIRouter()
logger = logging.getLogger(__name__)


@router.get("/sentences")
async def get_magic_sentences():
    """Get the 3 magic sentences for the quiz"""
    try:
        sentences = load_magic_sentences()
        return {
            "sentences": [
                {
                    "sentence_id": s.sentence_id,
                    "sentence_text": s.sentence_text
                }
                for s in sentences[:3]  # Return first 3
            ]
        }
    except Exception as e:
        logger.error(f"Error getting sentences: {e}")
        raise HTTPException(status_code=500, detail="Failed to load sentences")


@router.post("/submit-quiz")
async def submit_quiz(submission: QuizSubmission):
    """
    Submit quiz with audio recordings
    Process audio -> transcribe -> analyze phonemes -> save results
    """
    try:
        session_id = submission.session_id
        recordings = submission.recordings
        email = submission.email
        
        # Create quiz record
        user_id = None
        if email:
            user = await db.create_user(email, session_id)
            user_id = user['id']
        
        quiz_id = await db.create_quiz(user_id, session_id)
        
        # Load magic sentences for comparison
        magic_sentences = load_magic_sentences()
        sentence_map = {s.sentence_id: s for s in magic_sentences}
        
        # Process each recording
        recording_results = []
        
        for rec in recordings:
            try:
                sentence_id = rec.sentence_id
                audio_data = rec.audio_base64
                
                # Decode base64 audio
                audio_bytes = base64.b64decode(audio_data.split(',')[1] if ',' in audio_data else audio_data)
                
                # Save audio file
                audio_filename = f"{quiz_id}_{sentence_id}_{uuid.uuid4()}.webm"
                audio_path = os.path.join(settings.UPLOAD_DIR, audio_filename)
                
                os.makedirs(settings.UPLOAD_DIR, exist_ok=True)
                with open(audio_path, 'wb') as f:
                    f.write(audio_bytes)
                
                # Get expected sentence
                expected_sentence = sentence_map[sentence_id]
                expected_text = expected_sentence.sentence_text
                target_phonemes = expected_sentence.target_phonemes
                
                # Save recording metadata
                await db.save_recording(quiz_id, sentence_id, audio_path, expected_text)
                
                # Transcribe audio
                transcription, confidence = await transcribe_audio(audio_path)
                
                # Analyze phonemes
                analysis = identify_phoneme_errors(
                    expected=expected_text,
                    actual=transcription,
                    target_phonemes=target_phonemes
                )
                
                recording_results.append({
                    'sentence_id': sentence_id,
                    'expected': expected_text,
                    'transcription': transcription,
                    'confidence': confidence,
                    **analysis
                })
                
            except Exception as e:
                logger.error(f"Error processing recording {rec.sentence_id}: {e}")
                recording_results.append({
                    'sentence_id': rec.sentence_id,
                    'error': str(e),
                    'passed': False,
                    'similarity': 0.0,
                    'flagged_phonemes': []
                })
        
        # Analyze overall quiz results
        overall_results = analyze_quiz_results(recording_results)
        
        # Save results to database
        await db.update_quiz_results(quiz_id, overall_results)
        
        # Return results
        return {
            "quiz_id": quiz_id,
            "status": "completed",
            "results": overall_results,
            "recordings": recording_results,
            "message": "Quiz completed successfully! Login to see your personalized evaluation."
        }
        
    except Exception as e:
        logger.error(f"Quiz submission error: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to process quiz: {str(e)}")


@router.get("/results/{quiz_id}")
async def get_quiz_results(quiz_id: str):
    """Get quiz results (requires authentication in production)"""
    try:
        results = await db.get_quiz_results(quiz_id)
        
        if not results:
            raise HTTPException(status_code=404, detail="Quiz not found")
        
        return {
            "quiz_id": quiz_id,
            "results": results.get('results', {}),
            "status": results.get('status', 'unknown')
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Error getting quiz results: {e}")
        raise HTTPException(status_code=500, detail="Failed to retrieve results")


@router.post("/check-attempts")
async def check_attempts(session_id: str):
    """Check if user has exceeded attempt limit (rate limiting)"""
    # TODO: Implement rate limiting logic
    # For now, always allow
    return {"allowed": True, "attempts_left": 3}
