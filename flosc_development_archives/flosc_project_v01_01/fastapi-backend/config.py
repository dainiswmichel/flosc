"""
Configuration for LeSAEp FLOSC Backend
"""

import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    # WordPress Integration
    WP_SESSION_SECRET: str = os.getenv("WP_SESSION_SECRET", "")
    WP_API_URL: str = os.getenv("WP_API_URL", "https://dainis.net/wp-json/lesaep/v1")
    
    # Stripe
    STRIPE_SECRET_KEY: str = os.getenv("STRIPE_SECRET_KEY", "")
    STRIPE_WEBHOOK_SECRET: str = os.getenv("STRIPE_WEBHOOK_SECRET", "")
    
    # Database
    DATABASE_URL: str = os.getenv("DATABASE_URL", "sqlite:///./flosc.db")
    
    # Whisper Model
    WHISPER_MODEL: str = os.getenv("WHISPER_MODEL", "tiny")  # tiny, base, small, medium, large
    
    # App Settings
    DEBUG: bool = os.getenv("DEBUG", "False").lower() == "true"
    API_URL: str = os.getenv("API_URL", "http://localhost:8000")
    
    # Magic Sentences
    MAGIC_SENTENCES: list = [
        "The cat sat on the mat",
        "She sells seashells by the seashore",
        "How now brown cow"
    ]
    
    # OTO Settings
    OTO_DURATIONS: dict = {
        "1hour": 3600,
        "1day": 86400,
        "7days": 604800
    }
    
    class Config:
        env_file = ".env"
        case_sensitive = True

settings = Settings()
