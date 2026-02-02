// Authentication Logic

async function requestLogin(event) {
    event.preventDefault();
    
    const email = document.getElementById('email').value;
    const form = document.getElementById('login-form');
    const successMessage = document.getElementById('success-message');
    const errorMessage = document.getElementById('error-message');
    
    try {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ email })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            form.style.display = 'none';
            successMessage.style.display = 'block';
        } else {
            throw new Error(data.detail || 'Login failed');
        }
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.style.display = 'block';
        document.getElementById('error-text').textContent = error.message;
    }
}

// Handle magic link token on page load
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (token) {
        verifyToken(token);
    }
});

async function verifyToken(token) {
    try {
        const response = await fetch('/api/auth/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ token })
        });
        
        if (response.ok) {
            // Store token and redirect to dashboard
            localStorage.setItem('auth_token', token);
            window.location.href = '/dashboard';
        } else {
            throw new Error('Invalid or expired link');
        }
    } catch (error) {
        alert('Login link is invalid or expired. Please request a new one.');
    }
}
