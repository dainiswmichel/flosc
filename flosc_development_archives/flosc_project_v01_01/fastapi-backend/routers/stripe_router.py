"""
Stripe Router - Payment processing and webhooks
"""

from fastapi import APIRouter, HTTPException, Request, Form, Depends
import stripe
import httpx
from typing import Optional, Dict

from auth import verify_session_token
from database import db
from config import settings

# Initialize Stripe
stripe.api_key = settings.STRIPE_SECRET_KEY

# Initialize router
router = APIRouter()


@router.post("/create-intent")
async def create_payment_intent(
    amount: int = Form(...),
    user_id: Optional[int] = Form(None),
    session_id: Optional[str] = Form(None),
    user_data: Optional[Dict] = Depends(verify_session_token)
):
    """
    Create Stripe PaymentIntent
    
    Args:
        amount: Amount in cents (e.g., 14400 = $144.00)
        user_id: WordPress user ID
        session_id: Quiz session ID
    """
    
    # Get session
    if not session_id and user_data:
        session = db.get_session_by_user(user_data["user_id"])
        if session:
            session_id = session["session_id"]
    
    if not session_id:
        raise HTTPException(status_code=400, detail="No session found")
    
    # Check if OTO is still valid
    if db.is_oto_expired(session_id):
        raise HTTPException(
            status_code=403,
            detail="Offer has expired"
        )
    
    # Get user info
    wp_user_id = user_data.get("user_id") if user_data else user_id
    email = user_data.get("email") if user_data else None
    
    try:
        # Create PaymentIntent
        intent = stripe.PaymentIntent.create(
            amount=amount,
            currency="usd",
            metadata={
                "session_id": session_id,
                "wp_user_id": wp_user_id,
                "source": "lesaep_flosc"
            },
            receipt_email=email
        )
        
        return {
            "client_secret": intent.client_secret,
            "payment_intent_id": intent.id
        }
        
    except stripe.error.StripeError as e:
        raise HTTPException(status_code=400, detail=str(e))


@router.post("/webhook")
async def stripe_webhook(request: Request):
    """
    Handle Stripe webhooks
    
    Main event: checkout.session.completed or payment_intent.succeeded
    """
    
    # Get raw body
    payload = await request.body()
    sig_header = request.headers.get("stripe-signature")
    
    if not sig_header:
        raise HTTPException(status_code=400, detail="Missing signature")
    
    try:
        # Verify webhook signature
        event = stripe.Webhook.construct_event(
            payload, sig_header, settings.STRIPE_WEBHOOK_SECRET
        )
        
    except ValueError as e:
        raise HTTPException(status_code=400, detail="Invalid payload")
    except stripe.error.SignatureVerificationError as e:
        raise HTTPException(status_code=400, detail="Invalid signature")
    
    # Handle the event
    if event["type"] == "payment_intent.succeeded":
        payment_intent = event["data"]["object"]
        
        # Extract metadata
        session_id = payment_intent["metadata"].get("session_id")
        wp_user_id = payment_intent["metadata"].get("wp_user_id")
        
        if session_id and wp_user_id:
            # Mark session as paid
            db.mark_paid(session_id)
            
            # Add user to BuddyBoss group via WordPress API
            await add_user_to_buddyboss_group(int(wp_user_id))
            
            print(f"✓ Payment successful for user {wp_user_id}, session {session_id}")
        
        return {"status": "success", "message": "Payment processed"}
    
    # Return 200 for all other events
    return {"status": "received"}


async def add_user_to_buddyboss_group(wp_user_id: int):
    """
    Call WordPress API to add user to BuddyBoss paid group
    
    This assumes WordPress has an endpoint to handle this.
    Alternative: Handle directly in WordPress via Stripe webhook.
    """
    
    try:
        async with httpx.AsyncClient() as client:
            response = await client.post(
                f"{settings.WP_API_URL}/mark-paid",
                json={
                    "user_id": wp_user_id,
                    "paid": True
                },
                timeout=10.0
            )
            
            if response.status_code != 200:
                print(f"✗ Failed to update WordPress for user {wp_user_id}")
            else:
                print(f"✓ WordPress updated for user {wp_user_id}")
                
    except Exception as e:
        print(f"✗ Error calling WordPress API: {e}")


@router.get("/check-payment")
async def check_payment(session_id: str):
    """Check if session has been paid"""
    
    session = db.get_session(session_id)
    
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")
    
    return {
        "session_id": session_id,
        "is_paid": bool(session["is_paid"])
    }
