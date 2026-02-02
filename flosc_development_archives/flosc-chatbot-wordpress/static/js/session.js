/**
 * FLOSC Session Management
 * Handles user state, access control, and WordPress authentication
 */

class FLOSCSession {
    constructor() {
        this.storageKey = 'flosc_session';
        this.session = this.loadSession();
    }

    /**
     * Load session from localStorage
     */
    loadSession() {
        const stored = localStorage.getItem(this.storageKey);
        
        if (stored) {
            return JSON.parse(stored);
        }
        
        // Default empty session
        return {
            quizId: null,
            email: null,
            phone: null,
            completed: false,
            flaggedPhonemes: [],
            isPaid: false,
            otoDuration: null,
            otoExpires: null,
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString()
        };
    }

    /**
     * Save session to localStorage
     */
    saveSession() {
        this.session.updatedAt = new Date().toISOString();
        localStorage.setItem(this.storageKey, JSON.stringify(this.session));
    }

    /**
     * Start a new quiz
     */
    startQuiz() {
        this.session.quizId = 'quiz_' + Math.random().toString(36).substr(2, 9);
        this.saveSession();
        return this.session.quizId;
    }

    /**
     * Set user contact info
     */
    setContact(email, phone = null) {
        this.session.email = email;
        this.session.phone = phone;
        this.saveSession();
    }

    /**
     * Mark quiz as completed with results
     */
    completeQuiz(flaggedPhonemes) {
        this.session.completed = true;
        this.session.flaggedPhonemes = flaggedPhonemes;
        this.saveSession();
    }

    /**
     * Set OTO details
     */
    setOTO(duration) {
        this.session.otoDuration = duration;
        this.session.otoExpires = new Date(Date.now() + duration * 1000).toISOString();
        this.saveSession();
    }

    /**
     * Mark as paid
     */
    markPaid() {
        this.session.isPaid = true;
        this.session.paidAt = new Date().toISOString();
        this.saveSession();
    }

    /**
     * Check if OTO has expired
     */
    isOTOExpired() {
        if (!this.session.otoExpires) return true;
        return new Date() > new Date(this.session.otoExpires);
    }

    /**
     * Get access level
     */
    getAccessLevel() {
        if (this.session.isPaid) {
            return 'full';
        } else if (this.session.completed) {
            return 'free';
        } else {
            return 'none';
        }
    }

    /**
     * Can access lesson?
     */
    canAccessLesson(lesson) {
        const accessLevel = this.getAccessLevel();
        
        if (accessLevel === 'full') {
            return true;
        }
        
        if (accessLevel === 'free' && lesson.isFree) {
            return true;
        }
        
        return false;
    }

    /**
     * Get recommended lessons based on flagged phonemes
     */
    async getRecommendedLessons(wordpressApi) {
        if (this.session.flaggedPhonemes.length === 0) {
            return [];
        }
        
        const lessons = await wordpressApi.getLessonsByPhonemes(
            this.session.flaggedPhonemes,
            true  // free only
        );
        
        // Return max 2 lessons
        return lessons.slice(0, 2);
    }

    /**
     * Reset session (for testing)
     */
    reset() {
        localStorage.removeItem(this.storageKey);
        this.session = this.loadSession();
    }

    /**
     * Export session data (for WordPress sync)
     */
    export() {
        return {
            ...this.session,
            currentUrl: window.location.href,
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * Sync with WordPress (future implementation)
     * This will send session data to WordPress backend
     */
    async syncWithWordPress(wpUrl) {
        // Future: POST session data to WordPress endpoint
        // WordPress will create/update user and session in database
        
        const data = this.export();
        
        try {
            const response = await fetch(`${wpUrl}/wp-json/flosc/v1/session`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log('✅ Session synced with WordPress', result);
                return result;
            }
        } catch (error) {
            console.warn('⚠️ WordPress sync failed (endpoint may not exist yet)', error);
        }
        
        return null;
    }
}

// Export
window.FLOSCSession = FLOSCSession;
