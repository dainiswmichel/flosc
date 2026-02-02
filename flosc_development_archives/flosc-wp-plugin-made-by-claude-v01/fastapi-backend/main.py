"""
FLOSC Funnel - FastAPI Backend (Simplified for WordPress)
"""

from fastapi import FastAPI, File, UploadFile, Form, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from faster_whisper import WhisperModel
import os
from typing import Dict
from pathlib import Path

app = FastAPI(title="FLOSC Backend API")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Whisper model
whisper_model = None

def get_whisper_model():
    global whisper_model
    if whisper_model is None:
        whisper_model = WhisperModel("tiny", device="cpu")
    return whisper_model

# Phoneme mapping
PHONEME_MAP = {
    'cat': ['/æ/'], 'sat': ['/æ/'], 'mat': ['/æ/'],
    'she': ['/ʃ/', '/i/'], 'sells': ['/ɛ/', '/l/'],
    'seashells': ['/ʃ/', '/ɛ/', '/l/'],
    'how': ['/aʊ/'], 'now': ['/aʊ/'], 'brown': ['/r/', '/aʊ/'], 'cow': ['/aʊ/'],
}

def analyze_transcription(target: str, transcription: str) -> Dict:
    target_words = set(target.lower().split())
    trans_words = set(transcription.lower().split())
    
    missing_words = target_words - trans_words
    
    flagged_phonemes = []
    for word in missing_words:
        if word in PHONEME_MAP:
            flagged_phonemes.extend(PHONEME_MAP[word])
    
    correct = len(target_words & trans_words)
    accuracy = (correct / len(target_words) * 100) if target_words else 0
    
    return {
        "accuracy": round(accuracy, 2),
        "flagged_phonemes": list(set(flagged_phonemes)),
        "missing_words": list(missing_words),
        "is_perfect": accuracy >= 95
    }

@app.get("/health")
async def health():
    return {"status": "online"}

@app.post("/api/process-audio")
async def process_audio(
    quiz_id: str = Form(...),
    sentence_index: int = Form(...),
    target_sentence: str = Form(...),
    audio: UploadFile = File(...)
):
    temp_dir = Path("/tmp/flosc-audio")
    temp_dir.mkdir(exist_ok=True)
    
    audio_path = temp_dir / f"{quiz_id}_{sentence_index}.webm"
    
    with open(audio_path, "wb") as f:
        f.write(await audio.read())
    
    model = get_whisper_model()
    segments, _ = model.transcribe(str(audio_path), language="en")
    transcription = " ".join([seg.text for seg in segments])
    
    analysis = analyze_transcription(target_sentence, transcription)
    
    audio_path.unlink()
    
    return {"transcription": transcription, "target": target_sentence, **analysis}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
