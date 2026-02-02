"""
Faster-Whisper Speech-to-Text Service
"""
from faster_whisper import WhisperModel
from app.config import settings
import logging
from pathlib import Path
from typing import Optional, Tuple
import os

logger = logging.getLogger(__name__)

# Global Whisper model instance
whisper_model: Optional[WhisperModel] = None


def init_whisper():
    """Initialize Whisper model (lazy loading)"""
    global whisper_model
    if whisper_model is None:
        try:
            logger.info(f"Loading Whisper model: {settings.WHISPER_MODEL}")
            whisper_model = WhisperModel(
                settings.WHISPER_MODEL,
                device=settings.WHISPER_DEVICE,
                compute_type=settings.WHISPER_COMPUTE_TYPE
            )
            logger.info("✅ Whisper model loaded successfully")
        except Exception as e:
            logger.error(f"❌ Failed to load Whisper model: {e}")
            raise
    return whisper_model


async def transcribe_audio(audio_path: str) -> Tuple[str, float]:
    """
    Transcribe audio file to text
    
    Args:
        audio_path: Path to audio file
        
    Returns:
        Tuple of (transcription_text, confidence_score)
    """
    try:
        if not os.path.exists(audio_path):
            raise FileNotFoundError(f"Audio file not found: {audio_path}")
        
        model = init_whisper()
        
        # Transcribe
        segments, info = model.transcribe(
            audio_path,
            language="en",
            vad_filter=True,  # Voice activity detection
            word_timestamps=False
        )
        
        # Combine all segments
        transcription = " ".join([segment.text.strip() for segment in segments])
        
        # Average confidence (if available)
        confidence = info.language_probability if hasattr(info, 'language_probability') else 0.9
        
        logger.info(f"Transcribed: {transcription[:50]}... (confidence: {confidence:.2f})")
        
        return transcription.strip(), confidence
        
    except Exception as e:
        logger.error(f"Transcription error: {e}")
        raise


async def batch_transcribe(audio_paths: list) -> list:
    """
    Transcribe multiple audio files
    
    Args:
        audio_paths: List of audio file paths
        
    Returns:
        List of (transcription, confidence) tuples
    """
    results = []
    for path in audio_paths:
        try:
            transcription, confidence = await transcribe_audio(path)
            results.append((transcription, confidence))
        except Exception as e:
            logger.error(f"Failed to transcribe {path}: {e}")
            results.append(("", 0.0))
    
    return results


def cleanup_whisper():
    """Clean up Whisper model from memory"""
    global whisper_model
    if whisper_model is not None:
        del whisper_model
        whisper_model = None
        logger.info("Whisper model cleaned up")
