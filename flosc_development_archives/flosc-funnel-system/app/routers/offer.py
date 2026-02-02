"""
Offer Router - Send Personalized Offers
"""
from fastapi import APIRouter, HTTPException, BackgroundTasks
from app.models import OfferRequest, OfferData
from app.database import db
from app.services.email_service import send_evaluation_email, send_reminder_email
from app.services.phoneme_matcher import get_lessons_for_phonemes
from app.utils.csv_loader import load_product, load_lessons
from app.config import settings
from datetime import datetime, timedelta
import logging

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/send", response_model=OfferData)
async def send_offer(request: OfferRequest, background_tasks: BackgroundTasks):
    """Send personalized offer email based on quiz results"""
    try:
        quiz_id = request.quiz_id
        user_id = request.user_id
        
        # Get quiz results
        quiz_results = await db.get_quiz_results(quiz_id)
        if not quiz_results:
            raise HTTPException(status_code=404, detail="Quiz not found")
        
        # Get user
        user = await db.get_user_by_email(quiz_results.get('user_email'))  # Assuming email stored
        if not user:
            raise HTTPException(status_code=404, detail="User not found")
        
        # Load product and lessons
        product = load_product()
        all_lessons = load_lessons()
        
        # Get flagged phonemes
        flagged_phonemes = quiz_results.get('flagged_phonemes', [])
        
        # Get recommended free lessons
        free_lessons = []
        for phoneme in flagged_phonemes[:2]:  # Max 2 free lessons
            lessons = get_lessons_for_phonemes([phoneme], [l.dict() for l in all_lessons])
            if lessons:
                free_lessons.append(lessons[0])
        
        # Create checkout URL (will be implemented in payment router)
        checkout_url = f"{settings.DOMAIN}/api/payment/checkout?quiz_id={quiz_id}&user_id={user_id}"
        
        # Calculate expiration
        expires_at = datetime.utcnow() + timedelta(hours=settings.OTO_DURATION_HOURS)
        
        # Prepare offer data
        offer_data = {
            'evaluation': quiz_results.get('message', ''),
            'flagged_phonemes': flagged_phonemes,
            'needs_work_count': len(flagged_phonemes),
            'free_lessons': free_lessons,
            'oto_price': product.oto_price,
            'full_price': product.full_price,
            'discount_percent': product.oto_discount_percent,
            'expires_at': expires_at,
            'checkout_url': checkout_url
        }
        
        # Save offer to database
        offer_id = await db.create_offer(user_id, quiz_id, offer_data)
        
        # Send evaluation email
        await send_evaluation_email(
            to_email=user['email'],
            to_name=user.get('name', user['email'].split('@')[0]),
            evaluation_data=offer_data,
            checkout_url=checkout_url
        )
        
        # Schedule reminder emails in background
        for hours in settings.OTO_REMINDER_HOURS:
            background_tasks.add_task(
                schedule_reminder,
                user['email'],
                user.get('name', user['email'].split('@')[0]),
                checkout_url,
                hours
            )
        
        logger.info(f"✅ Offer sent to {user['email']}")
        
        return OfferData(**offer_data)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Offer sending error: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to send offer: {str(e)}")


async def schedule_reminder(email: str, name: str, checkout_url: str, hours: int):
    """Schedule a reminder email (would use Celery or similar in production)"""
    import asyncio
    await asyncio.sleep(hours * 3600)  # Wait specified hours
    await send_reminder_email(email, name, checkout_url, 24 - hours)
