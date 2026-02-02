"""
LeSAEp FLOSC Backend - FastAPI Application
Handles: Whisper transcription, phoneme analysis, state management, Stripe integration
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Import routers
from routers import chat, stripe_router

# Initialize FastAPI
app = FastAPI(
    title="LeSAEp FLOSC API",
    description="Chat-only pronunciation quiz backend",
    version="1.0.0"
)

# CORS middleware (only needed if not using Nginx proxy)
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "https://lesaep.com",
        "https://dainis.net",
        "http://localhost:3000"  # For local testing
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include routers
app.include_router(chat.router, prefix="/chat", tags=["chat"])
app.include_router(stripe_router.router, prefix="/stripe", tags=["stripe"])

# Health check
@app.get("/health")
async def health_check():
    return {
        "status": "healthy",
        "version": "1.0.0",
        "whisper": "loaded" if chat.whisper_model else "not loaded"
    }

# Root endpoint
@app.get("/")
async def root():
    return {
        "message": "LeSAEp FLOSC API",
        "version": "1.0.0",
        "docs": "/docs"
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "main:app",
        host="0.0.0.0",
        port=8000,
        reload=True  # Remove in production
    )
