"""
Authentication Router - Supabase Magic Link
"""
from fastapi import APIRouter, HTTPException
from app.models import LoginRequest, LoginResponse
from app.services.email_service import send_magic_link_email
from app.database import supabase
from app.config import settings
import logging

router = APIRouter()
logger = logging.getLogger(__name__)


@router.post("/login", response_model=LoginResponse)
async def request_magic_link(request: LoginRequest):
    """Send magic link to user's email"""
    try:
        email = request.email
        
        # Generate magic link via Supabase
        response = supabase.auth.sign_in_with_otp({
            "email": email,
            "options": {
                "email_redirect_to": f"{settings.DOMAIN}/dashboard"
            }
        })
        
        logger.info(f"✅ Magic link sent to {email}")
        
        return LoginResponse(
            success=True,
            message="Check your email for the login link!"
        )
        
    except Exception as e:
        logger.error(f"Login error: {e}")
        raise HTTPException(status_code=500, detail="Failed to send login link")


@router.post("/verify")
async def verify_token(token: str):
    """Verify magic link token"""
    try:
        user = supabase.auth.get_user(token)
        return {"user": user, "authenticated": True}
    except Exception as e:
        logger.error(f"Token verification error: {e}")
        raise HTTPException(status_code=401, detail="Invalid or expired token")


@router.post("/logout")
async def logout():
    """Logout user"""
    try:
        supabase.auth.sign_out()
        return {"success": True, "message": "Logged out successfully"}
    except Exception as e:
        logger.error(f"Logout error: {e}")
        raise HTTPException(status_code=500, detail="Logout failed")
