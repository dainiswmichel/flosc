"""
Supabase Database Client
"""
from supabase import create_client, Client
from app.config import settings
from typing import Optional, Dict, Any, List
from datetime import datetime, timedelta
import logging

logger = logging.getLogger(__name__)

# Supabase client instance
supabase: Optional[Client] = None


async def init_database():
    """Initialize Supabase client"""
    global supabase
    try:
        supabase = create_client(settings.SUPABASE_URL, settings.SUPABASE_KEY)
        logger.info("✅ Supabase client initialized")
        
        # Create tables if they don't exist (run migrations)
        await create_tables()
        
    except Exception as e:
        logger.error(f"❌ Failed to initialize Supabase: {e}")
        raise


async def create_tables():
    """
    Create database tables using Supabase SQL Editor or migrations
    This is a reference - actual tables should be created via Supabase Dashboard
    
    SQL to run in Supabase SQL Editor:
    
    -- Users table
    CREATE TABLE IF NOT EXISTS users (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        email TEXT UNIQUE NOT NULL,
        status TEXT DEFAULT 'freeline',
        session_id TEXT,
        created_at TIMESTAMP DEFAULT NOW(),
        paid_at TIMESTAMP,
        metadata JSONB
    );
    
    -- Quizzes table
    CREATE TABLE IF NOT EXISTS quizzes (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        user_id UUID REFERENCES users(id),
        session_id TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        results JSONB,
        flagged_phonemes TEXT[],
        accuracy_score DECIMAL,
        created_at TIMESTAMP DEFAULT NOW(),
        completed_at TIMESTAMP
    );
    
    -- Recordings table
    CREATE TABLE IF NOT EXISTS recordings (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        quiz_id UUID REFERENCES quizzes(id),
        sentence_id INTEGER NOT NULL,
        audio_path TEXT NOT NULL,
        transcription TEXT,
        expected_text TEXT,
        phoneme_errors JSONB,
        created_at TIMESTAMP DEFAULT NOW()
    );
    
    -- Offers table
    CREATE TABLE IF NOT EXISTS offers (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        user_id UUID REFERENCES users(id),
        quiz_id UUID REFERENCES quizzes(id),
        sent_at TIMESTAMP DEFAULT NOW(),
        expires_at TIMESTAMP NOT NULL,
        opened_at TIMESTAMP,
        clicked_at TIMESTAMP,
        purchased_at TIMESTAMP,
        offer_data JSONB
    );
    
    -- Payments table
    CREATE TABLE IF NOT EXISTS payments (
        id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
        user_id UUID REFERENCES users(id),
        stripe_session_id TEXT UNIQUE NOT NULL,
        amount INTEGER NOT NULL,
        currency TEXT DEFAULT 'USD',
        status TEXT DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT NOW(),
        completed_at TIMESTAMP
    );
    
    -- Enable Row Level Security
    ALTER TABLE users ENABLE ROW LEVEL SECURITY;
    ALTER TABLE quizzes ENABLE ROW LEVEL SECURITY;
    ALTER TABLE recordings ENABLE ROW LEVEL SECURITY;
    ALTER TABLE offers ENABLE ROW LEVEL SECURITY;
    ALTER TABLE payments ENABLE ROW LEVEL SECURITY;
    
    -- Create policies (users can only see their own data)
    CREATE POLICY "Users can view own data" ON users FOR SELECT USING (auth.uid() = id);
    CREATE POLICY "Users can view own quizzes" ON quizzes FOR SELECT USING (auth.uid() = user_id);
    CREATE POLICY "Users can view own offers" ON offers FOR SELECT USING (auth.uid() = user_id);
    """
    logger.info("📝 Database schema reference logged. Create tables via Supabase Dashboard.")


class DatabaseOperations:
    """Database CRUD operations"""
    
    @staticmethod
    async def create_user(email: str, session_id: str) -> Dict[str, Any]:
        """Create or get existing user"""
        try:
            # Check if user exists
            result = supabase.table('users').select('*').eq('email', email).execute()
            
            if result.data:
                user = result.data[0]
                # Update session_id
                supabase.table('users').update({
                    'session_id': session_id
                }).eq('id', user['id']).execute()
                return user
            
            # Create new user
            new_user = supabase.table('users').insert({
                'email': email,
                'session_id': session_id,
                'status': 'freeline'
            }).execute()
            
            return new_user.data[0]
            
        except Exception as e:
            logger.error(f"Error creating user: {e}")
            raise
    
    @staticmethod
    async def create_quiz(user_id: str, session_id: str) -> str:
        """Create a new quiz record"""
        try:
            result = supabase.table('quizzes').insert({
                'user_id': user_id if user_id else None,
                'session_id': session_id,
                'status': 'pending'
            }).execute()
            
            return result.data[0]['id']
            
        except Exception as e:
            logger.error(f"Error creating quiz: {e}")
            raise
    
    @staticmethod
    async def save_recording(quiz_id: str, sentence_id: int, audio_path: str, 
                           expected_text: str) -> str:
        """Save recording metadata"""
        try:
            result = supabase.table('recordings').insert({
                'quiz_id': quiz_id,
                'sentence_id': sentence_id,
                'audio_path': audio_path,
                'expected_text': expected_text
            }).execute()
            
            return result.data[0]['id']
            
        except Exception as e:
            logger.error(f"Error saving recording: {e}")
            raise
    
    @staticmethod
    async def update_quiz_results(quiz_id: str, results: Dict[str, Any]):
        """Update quiz with analysis results"""
        try:
            supabase.table('quizzes').update({
                'status': 'completed',
                'results': results,
                'flagged_phonemes': results.get('flagged_phonemes', []),
                'accuracy_score': results.get('accuracy_score', 0),
                'completed_at': datetime.utcnow().isoformat()
            }).eq('id', quiz_id).execute()
            
        except Exception as e:
            logger.error(f"Error updating quiz results: {e}")
            raise
    
    @staticmethod
    async def create_offer(user_id: str, quiz_id: str, offer_data: Dict[str, Any]) -> str:
        """Create an offer record"""
        try:
            expires_at = datetime.utcnow() + timedelta(hours=settings.OTO_DURATION_HOURS)
            
            result = supabase.table('offers').insert({
                'user_id': user_id,
                'quiz_id': quiz_id,
                'expires_at': expires_at.isoformat(),
                'offer_data': offer_data
            }).execute()
            
            return result.data[0]['id']
            
        except Exception as e:
            logger.error(f"Error creating offer: {e}")
            raise
    
    @staticmethod
    async def mark_payment_complete(stripe_session_id: str, user_id: str):
        """Mark user as paid"""
        try:
            # Update payment record
            supabase.table('payments').update({
                'status': 'completed',
                'completed_at': datetime.utcnow().isoformat()
            }).eq('stripe_session_id', stripe_session_id).execute()
            
            # Update user status
            supabase.table('users').update({
                'status': 'paid',
                'paid_at': datetime.utcnow().isoformat()
            }).eq('id', user_id).execute()
            
        except Exception as e:
            logger.error(f"Error marking payment complete: {e}")
            raise
    
    @staticmethod
    async def get_user_by_email(email: str) -> Optional[Dict[str, Any]]:
        """Get user by email"""
        try:
            result = supabase.table('users').select('*').eq('email', email).execute()
            return result.data[0] if result.data else None
        except Exception as e:
            logger.error(f"Error getting user: {e}")
            return None
    
    @staticmethod
    async def get_quiz_results(quiz_id: str) -> Optional[Dict[str, Any]]:
        """Get quiz results"""
        try:
            result = supabase.table('quizzes').select('*').eq('id', quiz_id).execute()
            return result.data[0] if result.data else None
        except Exception as e:
            logger.error(f"Error getting quiz results: {e}")
            return None


# Export database operations
db = DatabaseOperations()
