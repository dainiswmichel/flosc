"""
Email Service using Brevo (SendinBlue) API
"""
import sib_api_v3_sdk
from sib_api_v3_sdk.rest import ApiException
from app.config import settings
from datetime import datetime, timedelta
from typing import Dict, List
import logging

logger = logging.getLogger(__name__)

# Initialize Brevo API client
configuration = sib_api_v3_sdk.Configuration()
configuration.api_key['api-key'] = settings.BREVO_API_KEY
api_instance = sib_api_v3_sdk.TransactionalEmailsApi(sib_api_v3_sdk.ApiClient(configuration))


async def send_evaluation_email(
    to_email: str,
    to_name: str,
    evaluation_data: Dict,
    checkout_url: str
) -> bool:
    """
    Send personalized evaluation + OTO email
    
    Args:
        to_email: Recipient email
        to_name: Recipient name
        evaluation_data: Quiz results and recommendations
        checkout_url: Stripe checkout URL
        
    Returns:
        Success boolean
    """
    try:
        flagged_phonemes = evaluation_data.get('flagged_phonemes', [])
        needs_work_count = evaluation_data.get('needs_work_count', 0)
        free_lessons = evaluation_data.get('free_lessons', [])
        oto_price = evaluation_data.get('oto_price', 144)
        full_price = evaluation_data.get('full_price', 575)
        discount_percent = evaluation_data.get('discount_percent', 75)
        expires_at = evaluation_data.get('expires_at')
        
        # Format expiration time
        expires_str = expires_at.strftime('%B %d, %Y at %I:%M %p') if isinstance(expires_at, datetime) else "24 hours"
        
        # Build HTML email
        html_content = f"""
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body {{ font-family: Arial, sans-serif; line-height: 1.6; color: #333; }}
                .container {{ max-width: 600px; margin: 0 auto; padding: 20px; }}
                .header {{ background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }}
                .content {{ background: #f8f9fa; padding: 30px; }}
                .evaluation {{ background: white; padding: 20px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #667eea; }}
                .phoneme-list {{ background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }}
                .lesson-preview {{ background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border: 1px solid #ddd; }}
                .cta-button {{ display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }}
                .cta-button:hover {{ background: #218838; }}
                .urgent {{ background: #ff4757; color: white; padding: 20px; text-align: center; border-radius: 5px; margin: 20px 0; }}
                .price {{ font-size: 2em; font-weight: bold; color: #28a745; }}
                .strike {{ text-decoration: line-through; color: #999; }}
                .footer {{ text-align: center; padding: 20px; color: #666; font-size: 0.9em; }}
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🎯 Your Pronunciation Evaluation Results</h1>
                    <p>Hi {to_name}!</p>
                </div>
                
                <div class="content">
                    <div class="evaluation">
                        <h2>Your Analysis</h2>
                        <p>{evaluation_data.get('message', 'Great job on completing the quiz!')}</p>
                        
                        {'<div class="phoneme-list"><h3>Sounds That Need Work (' + str(needs_work_count) + '):</h3><ul>' + ''.join([f'<li><strong>/{p}/</strong></li>' for p in flagged_phonemes]) + '</ul></div>' if needs_work_count > 0 else '<p><strong>✅ All sounds were accurate!</strong></p>'}
                    </div>
                    
                    <h2>🎁 Your Free Lessons</h2>
                    <p>Based on your evaluation, here are personalized lessons to help you improve:</p>
                    
                    {''.join([f'''
                    <div class="lesson-preview">
                        <h3>{lesson.get('title', 'Lesson')}</h3>
                        <p>Target Sound: <strong>/{lesson.get('phoneme_group', '')}/</strong></p>
                        <a href="{settings.DOMAIN}/dashboard?lesson={lesson.get('lesson_id')}" style="color: #667eea;">Access Lesson →</a>
                    </div>
                    ''' for lesson in free_lessons])}
                    
                    <div class="urgent">
                        <h2>⏰ SPECIAL 24-HOUR OFFER</h2>
                        <p style="font-size: 1.2em; margin: 10px 0;">Master ALL 44 Sounds of English</p>
                        <p class="price"><span class="strike">${full_price}</span> ${oto_price}</p>
                        <p><strong>Save {discount_percent}%</strong> - This offer expires in 24 hours!</p>
                        <p style="font-size: 0.9em;">Expires: {expires_str}</p>
                        <a href="{checkout_url}" class="cta-button">🚀 CLAIM YOUR DISCOUNT NOW</a>
                    </div>
                    
                    <div style="margin: 30px 0; padding: 20px; background: #e9ecef; border-radius: 5px;">
                        <h3>What You'll Get:</h3>
                        <ul>
                            <li>✅ Complete mastery of all 44 English sounds</li>
                            <li>✅ Video lessons for each phoneme with native speaker demonstrations</li>
                            <li>✅ Downloadable practice PDFs and exercises</li>
                            <li>✅ Lifetime access to all content</li>
                            <li>✅ Certificate of completion</li>
                        </ul>
                    </div>
                    
                    <p style="text-align: center; font-weight: bold; color: #ff4757;">
                        ⚠️ This {discount_percent}% discount is only available for the next 24 hours!
                    </p>
                </div>
                
                <div class="footer">
                    <p>Questions? Reply to this email anytime.</p>
                    <p>© {datetime.now().year} {settings.APP_NAME}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        """
        
        # Plain text version
        text_content = f"""
        Your Pronunciation Evaluation Results
        
        Hi {to_name}!
        
        {evaluation_data.get('message', 'Great job on completing the quiz!')}
        
        {'Sounds that need work (' + str(needs_work_count) + '): ' + ', '.join(flagged_phonemes) if needs_work_count > 0 else 'All sounds were accurate!'}
        
        Your free lessons are ready in your dashboard!
        
        🎁 SPECIAL 24-HOUR OFFER
        Master ALL 44 Sounds of English
        
        Regular Price: ${full_price}
        Your Price: ${oto_price} (Save {discount_percent}%)
        
        This offer expires: {expires_str}
        
        Claim your discount: {checkout_url}
        
        Questions? Reply to this email anytime.
        """
        
        send_smtp_email = sib_api_v3_sdk.SendSmtpEmail(
            to=[{"email": to_email, "name": to_name}],
            sender={"email": settings.BREVO_SENDER_EMAIL, "name": settings.BREVO_SENDER_NAME},
            subject=f"🎯 Your Results + Special {discount_percent}% Offer (24 Hours Only)",
            html_content=html_content,
            text_content=text_content
        )
        
        api_instance.send_transac_email(send_smtp_email)
        logger.info(f"✅ Evaluation email sent to {to_email}")
        return True
        
    except ApiException as e:
        logger.error(f"❌ Brevo API error: {e}")
        return False
    except Exception as e:
        logger.error(f"❌ Email sending error: {e}")
        return False


async def send_reminder_email(to_email: str, to_name: str, checkout_url: str, hours_left: int) -> bool:
    """Send reminder email before OTO expires"""
    try:
        html_content = f"""
        <!DOCTYPE html>
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <div style="background: #ff4757; color: white; padding: 30px; text-align: center; border-radius: 10px;">
                    <h1>⏰ {hours_left} Hours Left!</h1>
                    <p style="font-size: 1.2em;">Your 75% discount expires soon</p>
                </div>
                
                <div style="padding: 30px; background: #f8f9fa;">
                    <p>Hi {to_name},</p>
                    <p>This is a friendly reminder that your special offer expires in <strong>{hours_left} hours</strong>.</p>
                    
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="{checkout_url}" style="display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                            🚀 CLAIM YOUR 75% DISCOUNT
                        </a>
                    </div>
                    
                    <p style="color: #ff4757; font-weight: bold; text-align: center;">
                        Don't miss out on this limited-time offer!
                    </p>
                </div>
            </div>
        </body>
        </html>
        """
        
        send_smtp_email = sib_api_v3_sdk.SendSmtpEmail(
            to=[{"email": to_email, "name": to_name}],
            sender={"email": settings.BREVO_SENDER_EMAIL, "name": settings.BREVO_SENDER_NAME},
            subject=f"⏰ {hours_left} Hours Left - 75% Discount Expiring Soon!",
            html_content=html_content
        )
        
        api_instance.send_transac_email(send_smtp_email)
        logger.info(f"✅ Reminder email sent to {to_email}")
        return True
        
    except Exception as e:
        logger.error(f"❌ Reminder email error: {e}")
        return False


async def send_magic_link_email(to_email: str, magic_link: str) -> bool:
    """Send magic link for authentication"""
    try:
        html_content = f"""
        <!DOCTYPE html>
        <html>
        <body style="font-family: Arial, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2>🔐 Your Login Link</h2>
                <p>Click the link below to access your account:</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{magic_link}" style="display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                        Log In to Your Account
                    </a>
                </div>
                <p style="color: #666; font-size: 0.9em;">This link expires in 1 hour.</p>
                <p style="color: #666; font-size: 0.9em;">If you didn't request this, please ignore this email.</p>
            </div>
        </body>
        </html>
        """
        
        send_smtp_email = sib_api_v3_sdk.SendSmtpEmail(
            to=[{"email": to_email}],
            sender={"email": settings.BREVO_SENDER_EMAIL, "name": settings.BREVO_SENDER_NAME},
            subject="🔐 Your Login Link",
            html_content=html_content
        )
        
        api_instance.send_transac_email(send_smtp_email)
        logger.info(f"✅ Magic link sent to {to_email}")
        return True
        
    except Exception as e:
        logger.error(f"❌ Magic link email error: {e}")
        return False
