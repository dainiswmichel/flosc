"""
Content Router - Protected Dashboard and Lessons
"""
from fastapi import APIRouter, HTTPException, Depends
from app.models import DashboardData, Lesson, User
from app.database import db, supabase
from app.utils.csv_loader import load_lessons
from typing import Optional
import logging

router = APIRouter()
logger = logging.getLogger(__name__)


async def get_current_user(token: str) -> User:
    """Get currently authenticated user"""
    try:
        user_data = supabase.auth.get_user(token)
        if not user_data:
            raise HTTPException(status_code=401, detail="Not authenticated")
        return User(**user_data)
    except Exception as e:
        logger.error(f"Auth error: {e}")
        raise HTTPException(status_code=401, detail="Invalid authentication")


@router.get("/dashboard")
async def get_dashboard(user: User = Depends(get_current_user)):
    """Get dashboard data for authenticated user"""
    try:
        # Get user's quiz results
        quiz_results = await db.get_user_latest_quiz(user.id)
        
        # Load all lessons
        all_lessons = load_lessons()
        
        # Filter lessons based on user status
        if user.status == "paid":
            # Show all lessons
            available_lessons = [l.dict() for l in all_lessons]
        else:
            # Show only free lessons
            available_lessons = [l.dict() for l in all_lessons if not l.is_premium]
        
        # Calculate progress
        total_lessons = len(all_lessons)
        completed_lessons = 0  # TODO: Track completed lessons
        progress_percent = (completed_lessons / total_lessons * 100) if total_lessons > 0 else 0
        
        return DashboardData(
            user=user,
            quiz_results=quiz_results,
            available_lessons=available_lessons,
            total_lessons=total_lessons,
            completed_lessons=completed_lessons,
            progress_percent=progress_percent
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Dashboard error: {e}")
        raise HTTPException(status_code=500, detail="Failed to load dashboard")


@router.get("/lesson/{lesson_id}")
async def get_lesson(lesson_id: int, user: User = Depends(get_current_user)):
    """Get specific lesson content"""
    try:
        lessons = load_lessons()
        lesson = next((l for l in lessons if l.lesson_id == lesson_id), None)
        
        if not lesson:
            raise HTTPException(status_code=404, detail="Lesson not found")
        
        # Check access
        if lesson.is_premium and user.status != "paid":
            raise HTTPException(status_code=403, detail="Premium content - Purchase required")
        
        return lesson.dict()
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Lesson retrieval error: {e}")
        raise HTTPException(status_code=500, detail="Failed to load lesson")
