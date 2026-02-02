// Dashboard Logic

document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('auth_token');
    
    if (!token) {
        window.location.href = '/login';
        return;
    }
    
    loadDashboard(token);
});

async function loadDashboard(token) {
    try {
        const response = await fetch('/api/content/dashboard', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        });
        
        if (!response.ok) {
            throw new Error('Failed to load dashboard');
        }
        
        const data = await response.json();
        renderDashboard(data);
        
    } catch (error) {
        console.error('Dashboard error:', error);
        localStorage.removeItem('auth_token');
        window.location.href = '/login';
    }
}

function renderDashboard(data) {
    const dashboard = document.getElementById('dashboard');
    
    const isPaid = data.user.status === 'paid';
    const quizResults = data.quiz_results;
    
    let html = '<div class="dashboard-content">';
    
    // Welcome section
    html += `
        <div class="welcome-section">
            <h2>Welcome, ${data.user.email.split('@')[0]}!</h2>
            <div class="status-badge ${isPaid ? 'badge-paid' : 'badge-free'}">
                ${isPaid ? '⭐ Premium Member' : '🎁 Free Access'}
            </div>
        </div>
    `;
    
    // Quiz results (if available)
    if (quizResults) {
        html += `
            <div class="results-section">
                <h3>Your Pronunciation Analysis</h3>
                <div class="results-card">
                    <p>${quizResults.message}</p>
                    ${quizResults.flagged_phonemes && quizResults.flagged_phonemes.length > 0 ? `
                        <div class="phoneme-list">
                            <h4>Sounds to Practice:</h4>
                            <div class="phoneme-tags">
                                ${quizResults.flagged_phonemes.map(p => `<span class="phoneme-tag">/${p}/</span>`).join('')}
                            </div>
                        </div>
                    ` : ''}
                    <div class="accuracy-score">
                        <span class="score-label">Accuracy:</span>
                        <span class="score-value">${Math.round(quizResults.accuracy_score * 100)}%</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Upgrade CTA (if not paid)
    if (!isPaid) {
        html += `
            <div class="upgrade-section">
                <h3>🎉 Limited Time Offer!</h3>
                <p>Unlock all ${data.total_lessons} lessons with 75% off</p>
                <a href="/offer" class="btn btn-primary">View My Exclusive Offer</a>
            </div>
        `;
    }
    
    // Progress
    html += `
        <div class="progress-section">
            <h3>Your Progress</h3>
            <div class="progress-stats">
                <div class="stat">
                    <span class="stat-value">${data.available_lessons.length}</span>
                    <span class="stat-label">Available Lessons</span>
                </div>
                <div class="stat">
                    <span class="stat-value">${data.total_lessons}</span>
                    <span class="stat-label">Total Lessons</span>
                </div>
                <div class="stat">
                    <span class="stat-value">${data.completed_lessons}</span>
                    <span class="stat-label">Completed</span>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: ${data.progress_percent}%"></div>
            </div>
        </div>
    `;
    
    // Lessons
    html += `
        <div class="lessons-section">
            <h3>${isPaid ? '📚 All Lessons' : '🎁 Your Free Lessons'}</h3>
            <div class="lessons-grid">
    `;
    
    data.available_lessons.forEach(lesson => {
        html += `
            <div class="lesson-card ${lesson.is_premium && !isPaid ? 'locked' : ''}">
                <div class="lesson-header">
                    <h4>${lesson.lesson_title}</h4>
                    <span class="phoneme-badge">/${lesson.phoneme_group}/</span>
                </div>
                <div class="lesson-actions">
                    ${lesson.is_premium && !isPaid ? `
                        <button class="btn btn-secondary" disabled>🔒 Premium</button>
                    ` : `
                        <a href="${lesson.video_url}" target="_blank" class="btn btn-primary">
                            ▶️ Watch Lesson
                        </a>
                        ${lesson.pdf_url ? `
                            <a href="${lesson.pdf_url}" target="_blank" class="btn btn-secondary">
                                📄 Download PDF
                            </a>
                        ` : ''}
                    `}
                </div>
            </div>
        `;
    });
    
    html += '</div></div></div>';
    
    dashboard.innerHTML = html;
}

function logout() {
    localStorage.removeItem('auth_token');
    window.location.href = '/';
}
