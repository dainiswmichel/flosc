"""
Payment Router - Stripe Integration
"""
from fastapi import APIRouter, HTTPException, Request, Response
from app.models import CheckoutRequest, CheckoutResponse
from app.database import db
from app.utils.csv_loader import load_product
from app.config import settings
import stripe
import logging

router = APIRouter()
logger = logging.getLogger(__name__)

stripe.api_key = settings.STRIPE_SECRET_KEY


@router.post("/checkout", response_model=CheckoutResponse)
async def create_checkout_session(request: CheckoutRequest):
    """Create Stripe checkout session"""
    try:
        quiz_id = request.quiz_id
        user_id = request.user_id
        
        # Load product
        product = load_product()
        
        # Get user
        quiz_results = await db.get_quiz_results(quiz_id)
        if not quiz_results:
            raise HTTPException(status_code=404, detail="Quiz not found")
        
        # Create Stripe checkout session
        checkout_session = stripe.checkout.Session.create(
            payment_method_types=['card'],
            line_items=[{
                'price_data': {
                    'currency': product.currency.lower(),
                    'product_data': {
                        'name': product.product_name,
                        'description': 'Complete course access - Limited time offer',
                    },
                    'unit_amount': int(product.oto_price * 100),  # Convert to cents
                },
                'quantity': 1,
            }],
            mode='payment',
            success_url=settings.STRIPE_SUCCESS_URL,
            cancel_url=settings.STRIPE_CANCEL_URL,
            client_reference_id=user_id,
            metadata={
                'quiz_id': quiz_id,
                'user_id': user_id
            }
        )
        
        # Save payment intent
        await db.create_payment_record(
            user_id=user_id,
            stripe_session_id=checkout_session.id,
            amount=int(product.oto_price * 100),
            currency=product.currency
        )
        
        return CheckoutResponse(
            checkout_url=checkout_session.url,
            session_id=checkout_session.id
        )
        
    except Exception as e:
        logger.error(f"Checkout error: {e}")
        raise HTTPException(status_code=500, detail=f"Failed to create checkout: {str(e)}")


@router.post("/webhook")
async def stripe_webhook(request: Request):
    """Handle Stripe webhooks for payment confirmation"""
    try:
        payload = await request.body()
        sig_header = request.headers.get('stripe-signature')
        
        # Verify webhook signature
        event = stripe.Webhook.construct_event(
            payload, sig_header, settings.STRIPE_WEBHOOK_SECRET
        )
        
        # Handle successful payment
        if event['type'] == 'checkout.session.completed':
            session = event['data']['object']
            
            user_id = session.get('client_reference_id')
            session_id = session.get('id')
            
            # Mark user as paid
            await db.mark_payment_complete(session_id, user_id)
            
            logger.info(f"✅ Payment confirmed for user {user_id}")
        
        return {"status": "success"}
        
    except ValueError as e:
        logger.error(f"Webhook error: {e}")
        raise HTTPException(status_code=400, detail="Invalid payload")
    except stripe.error.SignatureVerificationError as e:
        logger.error(f"Webhook signature error: {e}")
        raise HTTPException(status_code=400, detail="Invalid signature")
    except Exception as e:
        logger.error(f"Webhook processing error: {e}")
        raise HTTPException(status_code=500, detail=str(e))
