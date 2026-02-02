"""
WordPress Session Token Verification
Verifies HMAC-signed tokens from WordPress
"""

import hmac
import json
import base64
import time
from fastapi import HTTPException, Header
from typing import Optional, Dict
from config import settings

def verify_session_token(authorization: Optional[str] = Header(None)) -> Dict:
    """
    Verify WordPress-issued session token
    
    Token format: base64(json_data).hmac_signature
    
    Returns:
        dict: {
            'user_id': int,
            'email': str,
            'display_name': str,
            'has_paid_access': bool,
            'expires': int
        }
    """
    
    # Check if token provided
    if not authorization:
        raise HTTPException(
            status_code=401,
            detail="No authorization header provided"
        )
    
    # Check Bearer format
    if not authorization.startswith("Bearer "):
        raise HTTPException(
            status_code=401,
            detail="Invalid authorization format. Expected: Bearer <token>"
        )
    
    # Extract token
    token = authorization[7:]  # Remove "Bearer "
    
    # Split into data and signature
    parts = token.split(".")
    if len(parts) != 2:
        raise HTTPException(
            status_code=401,
            detail="Invalid token format"
        )
    
    data_b64, signature = parts
    
    try:
        # Decode base64 data
        data_json = base64.b64decode(data_b64)
        
        # Verify HMAC signature
        expected_sig = hmac.new(
            settings.WP_SESSION_SECRET.encode(),
            data_json,
            "sha256"
        ).hexdigest()
        
        if signature != expected_sig:
            raise HTTPException(
                status_code=401,
                detail="Invalid token signature"
            )
        
        # Parse JSON data
        data = json.loads(data_json)
        
        # Check expiration
        if "expires" in data and data["expires"] < time.time():
            raise HTTPException(
                status_code=401,
                detail="Token expired"
            )
        
        return data
        
    except json.JSONDecodeError:
        raise HTTPException(
            status_code=401,
            detail="Invalid token data"
        )
    except Exception as e:
        raise HTTPException(
            status_code=401,
            detail=f"Token verification failed: {str(e)}"
        )


def optional_session_token(authorization: Optional[str] = Header(None)) -> Optional[Dict]:
    """
    Optional token verification (for endpoints that work with or without auth)
    """
    if not authorization:
        return None
    
    try:
        return verify_session_token(authorization)
    except HTTPException:
        return None
