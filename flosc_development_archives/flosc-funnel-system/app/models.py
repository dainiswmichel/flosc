"""
Pydantic Models for Request/Response Validation
"""
from pydantic import BaseModel, EmailStr, validator
from typing import Optional, List, Dict
from datetime import datetime
from enum import Enum


class QuizStatus(str, Enum):
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"


class UserStatus(str, Enum):
    FREELINE = "freeline"
    REGISTERED = "registered"
    OFFERED = "offered"
    PAID = "paid"


# Freeline Models
class SentenceRecording(BaseModel):
    sentence_id: int
    audio_base64: str
    
    @validator('audio_base64')
    def validate_audio(cls, v):
        if not v or len(v) < 100:
            raise ValueError('Invalid audio data')
        return v


class QuizSubmission(BaseModel):
    recordings: List[SentenceRecording]
    email: Optional[EmailStr] = None
    session_id: str


class QuizResult(BaseModel):
    quiz_id: str
    total_sentences: int
    sentences_analyzed: int
    flagged_phonemes: List[str]
    accuracy_score: float
    needs_work_count: int
    message: str


# Auth Models
class LoginRequest(BaseModel):
    email: EmailStr


class LoginResponse(BaseModel):
    success: bool
    message: str


class User(BaseModel):
    id: str
    email: str
    status: UserStatus
    created_at: datetime
    paid_at: Optional[datetime] = None


# Offer Models
class OfferRequest(BaseModel):
    quiz_id: str
    user_id: str


class LessonPreview(BaseModel):
    lesson_id: int
    title: str
    phoneme_group: str
    video_url: str
    pdf_url: Optional[str] = None


class OfferData(BaseModel):
    evaluation: str
    flagged_phonemes: List[str]
    needs_work_count: int
    free_lessons: List[LessonPreview]
    oto_price: float
    full_price: float
    discount_percent: int
    expires_at: datetime
    checkout_url: str


# Payment Models
class CheckoutRequest(BaseModel):
    quiz_id: str
    user_id: str


class CheckoutResponse(BaseModel):
    checkout_url: str
    session_id: str


class WebhookEvent(BaseModel):
    event_type: str
    session_id: str
    customer_email: str
    amount_total: int
    payment_status: str


# Content Models
class Lesson(BaseModel):
    lesson_id: int
    title: str
    phoneme_group: str
    video_url: str
    pdf_url: Optional[str] = None
    is_premium: bool
    duration_minutes: Optional[int] = None


class DashboardData(BaseModel):
    user: User
    quiz_results: Optional[QuizResult] = None
    available_lessons: List[Lesson]
    total_lessons: int
    completed_lessons: int
    progress_percent: float


# CSV Data Models
class Product(BaseModel):
    product_id: int
    product_name: str
    full_price: float
    oto_price: float
    oto_discount_percent: int
    currency: str = "USD"


class TargetedLesson(BaseModel):
    lesson_id: int
    lesson_title: str
    phoneme_group: str
    video_url: str
    pdf_url: Optional[str] = None
    is_premium: bool


class MagicSentence(BaseModel):
    sentence_id: int
    sentence_text: str
    target_phonemes: List[str]
    
    @validator('target_phonemes', pre=True)
    def parse_phonemes(cls, v):
        if isinstance(v, str):
            return [p.strip() for p in v.split('|')]
        return v


# Analytics Models
class ConversionMetrics(BaseModel):
    total_visitors: int
    freeline_completions: int
    registrations: int
    offers_sent: int
    sales: int
    conversion_rate: float
    revenue: float
