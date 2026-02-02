"""
FastAPI Main Application
FLOSC Funnel System for Online Courses
"""
from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, FileResponse
from fastapi.templating import Jinja2Templates
import os
from pathlib import Path

from app.config import settings
from app.routers import freeline, auth, offer, payment, content
from app.database import init_database

# Initialize FastAPI app
app = FastAPI(
    title=settings.APP_NAME,
    version="1.0.0",
    docs_url="/api/docs" if settings.DEBUG else None,
    redoc_url="/api/redoc" if settings.DEBUG else None,
)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Mount static files
app.mount("/static", StaticFiles(directory="static"), name="static")
app.mount("/css", StaticFiles(directory="frontend/css"), name="css")
app.mount("/js", StaticFiles(directory="frontend/js"), name="js")

# Include routers
app.include_router(freeline.router, prefix="/api/freeline", tags=["Freeline"])
app.include_router(auth.router, prefix="/api/auth", tags=["Authentication"])
app.include_router(offer.router, prefix="/api/offer", tags=["Offer"])
app.include_router(payment.router, prefix="/api/payment", tags=["Payment"])
app.include_router(content.router, prefix="/api/content", tags=["Content"])


@app.on_event("startup")
async def startup_event():
    """Initialize services on startup"""
    await init_database()
    
    # Create necessary directories
    os.makedirs(settings.UPLOAD_DIR, exist_ok=True)
    os.makedirs(settings.STATIC_DIR, exist_ok=True)
    os.makedirs("logs", exist_ok=True)
    
    print(f"🚀 {settings.APP_NAME} started successfully!")
    print(f"📍 Environment: {settings.APP_ENV}")
    print(f"🔗 API Docs: http://{settings.DOMAIN}/api/docs")


@app.get("/", response_class=HTMLResponse)
async def root():
    """Serve the freeline landing page"""
    with open("frontend/index.html", "r") as f:
        return HTMLResponse(content=f.read())


@app.get("/login", response_class=HTMLResponse)
async def login_page():
    """Serve the login page"""
    with open("frontend/login.html", "r") as f:
        return HTMLResponse(content=f.read())


@app.get("/offer", response_class=HTMLResponse)
async def offer_page():
    """Serve the offer page"""
    with open("frontend/offer.html", "r") as f:
        return HTMLResponse(content=f.read())


@app.get("/dashboard", response_class=HTMLResponse)
async def dashboard_page():
    """Serve the content dashboard"""
    with open("frontend/dashboard.html", "r") as f:
        return HTMLResponse(content=f.read())


@app.get("/success", response_class=HTMLResponse)
async def success_page():
    """Payment success page"""
    return HTMLResponse(content="""
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Successful!</title>
        <link rel="stylesheet" href="/css/styles.css">
    </head>
    <body>
        <div class="container">
            <h1>🎉 Payment Successful!</h1>
            <p>Welcome to the course! Redirecting to your dashboard...</p>
            <script>
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 3000);
            </script>
        </div>
    </body>
    </html>
    """)


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "app": settings.APP_NAME}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host="0.0.0.0",
        port=8000,
        reload=settings.DEBUG
    )
