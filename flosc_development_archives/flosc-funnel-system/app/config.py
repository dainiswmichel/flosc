"""
Configuration Management
Loads settings from environment variables
"""
from pydantic_settings import BaseSettings
from typing import List
import os


class Settings(BaseSettings):
    # App Settings
    APP_NAME: str = "FLOSC Funnel System"
    APP_ENV: str = "development"
    DEBUG: bool = True
    SECRET_KEY: str
    DOMAIN: str = "localhost:8000"
    HTTPS_ENABLED: bool = False
    
    # Supabase
    SUPABASE_URL: str
    SUPABASE_KEY: str
    SUPABASE_SERVICE_KEY: str
    
    # Brevo (Email)
    BREVO_API_KEY: str
    BREVO_SENDER_EMAIL: str
    BREVO_SENDER_NAME: str = "Course Platform"
    
    # Stripe
    STRIPE_PUBLIC_KEY: str
    STRIPE_SECRET_KEY: str
    STRIPE_WEBHOOK_SECRET: str
    STRIPE_SUCCESS_URL: str
    STRIPE_CANCEL_URL: str
    
    # Twilio (Optional)
    TWILIO_ACCOUNT_SID: str = ""
    TWILIO_AUTH_TOKEN: str = ""
    TWILIO_WHATSAPP_FROM: str = ""
    TWILIO_ENABLED: bool = False
    
    # Telegram (Optional)
    TELEGRAM_BOT_TOKEN: str = ""
    TELEGRAM_ENABLED: bool = False
    
    # Whisper
    WHISPER_MODEL: str = "base"
    WHISPER_DEVICE: str = "cpu"
    WHISPER_COMPUTE_TYPE: str = "int8"
    
    # File Storage
    UPLOAD_DIR: str = "./uploads"
    STATIC_DIR: str = "./static"
    MAX_FILE_SIZE_MB: int = 10
    
    # Rate Limiting
    RATE_LIMIT_FREELINE: int = 3
    RATE_LIMIT_WINDOW_HOURS: int = 24
    
    # OTO Configuration
    OTO_DURATION_HOURS: int = 24
    OTO_REMINDER_HOURS: List[int] = [12, 23]
    
    # CORS
    CORS_ORIGINS: List[str] = ["http://localhost:3000", "http://localhost:8000"]
    
    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_FILE: str = "./logs/app.log"
    
    class Config:
        env_file = ".env"
        case_sensitive = True


# Global settings instance
settings = Settings()
