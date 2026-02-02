"""
Database Management - SQLite
Stores quiz sessions, recordings, OTO timers
"""

import sqlite3
import json
from datetime import datetime
from typing import Optional, Dict, List
import uuid

class Database:
    def __init__(self, db_path: str = "./flosc.db"):
        self.db_path = db_path
        self.init_db()
    
    def get_connection(self):
        conn = sqlite3.connect(self.db_path)
        conn.row_factory = sqlite3.Row
        return conn
    
    def init_db(self):
        """Initialize database schema"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        # Sessions table
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS sessions (
                session_id TEXT PRIMARY KEY,
                quiz_id TEXT UNIQUE,
                wp_user_id INTEGER,
                email TEXT,
                no_count INTEGER DEFAULT 0,
                blocked BOOLEAN DEFAULT 0,
                recordings TEXT,  -- JSON array
                flagged_phonemes TEXT,  -- JSON array
                oto_expires_at TIMESTAMP,
                is_paid BOOLEAN DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)
        
        conn.commit()
        conn.close()
    
    def create_session(self, wp_user_id: Optional[int] = None, email: Optional[str] = None) -> Dict:
        """Create new session"""
        session_id = str(uuid.uuid4())
        quiz_id = str(uuid.uuid4())
        
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            INSERT INTO sessions (session_id, quiz_id, wp_user_id, email)
            VALUES (?, ?, ?, ?)
        """, (session_id, quiz_id, wp_user_id, email))
        
        conn.commit()
        conn.close()
        
        return {
            "session_id": session_id,
            "quiz_id": quiz_id
        }
    
    def get_session(self, session_id: str) -> Optional[Dict]:
        """Get session by ID"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("SELECT * FROM sessions WHERE session_id = ?", (session_id,))
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return None
        
        return dict(row)
    
    def get_session_by_user(self, wp_user_id: int) -> Optional[Dict]:
        """Get most recent session for user"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            SELECT * FROM sessions 
            WHERE wp_user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        """, (wp_user_id,))
        
        row = cursor.fetchone()
        conn.close()
        
        if not row:
            return None
        
        return dict(row)
    
    def update_session(self, session_id: str, updates: Dict):
        """Update session fields"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        # Build UPDATE query
        fields = []
        values = []
        
        for key, value in updates.items():
            if key in ["recordings", "flagged_phonemes"]:
                value = json.dumps(value)
            fields.append(f"{key} = ?")
            values.append(value)
        
        # Add updated_at
        fields.append("updated_at = ?")
        values.append(datetime.now())
        
        # Add session_id for WHERE
        values.append(session_id)
        
        query = f"UPDATE sessions SET {', '.join(fields)} WHERE session_id = ?"
        cursor.execute(query, values)
        
        conn.commit()
        conn.close()
    
    def increment_no_count(self, session_id: str) -> int:
        """Increment 'No' count, return new count"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("""
            UPDATE sessions 
            SET no_count = no_count + 1, updated_at = ? 
            WHERE session_id = ?
        """, (datetime.now(), session_id))
        
        cursor.execute("SELECT no_count FROM sessions WHERE session_id = ?", (session_id,))
        count = cursor.fetchone()[0]
        
        # Block if >= 3
        if count >= 3:
            cursor.execute("""
                UPDATE sessions SET blocked = 1 WHERE session_id = ?
            """, (session_id,))
        
        conn.commit()
        conn.close()
        
        return count
    
    def is_blocked(self, session_id: str) -> bool:
        """Check if session is blocked"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("SELECT blocked FROM sessions WHERE session_id = ?", (session_id,))
        row = cursor.fetchone()
        conn.close()
        
        return bool(row[0]) if row else False
    
    def add_recording(self, session_id: str, recording_data: Dict):
        """Add recording to session"""
        session = self.get_session(session_id)
        if not session:
            return
        
        # Parse existing recordings
        recordings = json.loads(session["recordings"]) if session["recordings"] else []
        recordings.append(recording_data)
        
        self.update_session(session_id, {"recordings": recordings})
    
    def set_oto_timer(self, session_id: str, expires_at: datetime):
        """Set OTO expiry timestamp"""
        self.update_session(session_id, {"oto_expires_at": expires_at})
    
    def is_oto_expired(self, session_id: str) -> bool:
        """Check if OTO has expired"""
        session = self.get_session(session_id)
        if not session or not session["oto_expires_at"]:
            return True
        
        expires = datetime.fromisoformat(session["oto_expires_at"])
        return datetime.now() > expires
    
    def mark_paid(self, session_id: str):
        """Mark session as paid"""
        self.update_session(session_id, {"is_paid": True})
    
    def get_stats(self) -> Dict:
        """Get overall stats"""
        conn = self.get_connection()
        cursor = conn.cursor()
        
        cursor.execute("SELECT COUNT(*) as total FROM sessions")
        total = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) as paid FROM sessions WHERE is_paid = 1")
        paid = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) as blocked FROM sessions WHERE blocked = 1")
        blocked = cursor.fetchone()[0]
        
        conn.close()
        
        return {
            "total_sessions": total,
            "paid_sessions": paid,
            "blocked_sessions": blocked,
            "conversion_rate": (paid / total * 100) if total > 0 else 0
        }

# Global database instance
db = Database()
