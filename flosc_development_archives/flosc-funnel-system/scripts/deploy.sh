#!/bin/bash

# FLOSC Funnel System - Quick Deploy Script
# Run this on your DigitalOcean droplet

set -e

echo "🚀 FLOSC Funnel System - Deployment Script"
echo "=========================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "Please run as root (use sudo)"
    exit 1
fi

# Variables
APP_USER="flosc"
APP_DIR="/home/$APP_USER/flosc-funnel-system"
DOMAIN="${1:-yourdomain.com}"

if [ "$DOMAIN" == "yourdomain.com" ]; then
    echo "⚠️  Please provide your domain as an argument:"
    echo "   ./deploy.sh example.com"
    exit 1
fi

echo "📦 Installing system dependencies..."
apt-get update
apt-get install -y python3.10 python3-pip python3-venv nginx certbot python3-certbot-nginx git ffmpeg

echo "👤 Creating application user..."
if ! id "$APP_USER" &>/dev/null; then
    adduser --disabled-password --gecos "" $APP_USER
fi

echo "📁 Setting up application directory..."
sudo -u $APP_USER mkdir -p $APP_DIR
sudo -u $APP_USER mkdir -p $APP_DIR/uploads
sudo -u $APP_USER mkdir -p $APP_DIR/static/lessons
sudo -u $APP_USER mkdir -p $APP_DIR/logs

echo "🐍 Creating Python virtual environment..."
sudo -u $APP_USER python3 -m venv $APP_DIR/venv

echo "📦 Installing Python dependencies..."
sudo -u $APP_USER $APP_DIR/venv/bin/pip install --upgrade pip
sudo -u $APP_USER $APP_DIR/venv/bin/pip install -r $APP_DIR/requirements.txt

echo "⚙️  Setting up environment file..."
if [ ! -f "$APP_DIR/.env" ]; then
    sudo -u $APP_USER cp $APP_DIR/.env.example $APP_DIR/.env
    echo "⚠️  Please edit $APP_DIR/.env with your credentials"
fi

echo "🌐 Configuring Nginx..."
cat > /etc/nginx/sites-available/flosc << EOF
server {
    listen 80;
    server_name $DOMAIN www.$DOMAIN;

    client_max_body_size 20M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }

    location /static {
        alias $APP_DIR/static;
    }
}
EOF

ln -sf /etc/nginx/sites-available/flosc /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

echo "🔒 Setting up SSL certificate..."
certbot --nginx -d $DOMAIN -d www.$DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

echo "🔧 Creating systemd service..."
cat > /etc/systemd/system/flosc.service << EOF
[Unit]
Description=FLOSC Funnel System
After=network.target

[Service]
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$APP_DIR
Environment="PATH=$APP_DIR/venv/bin"
ExecStart=$APP_DIR/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 --workers 2

Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable flosc
systemctl start flosc

echo "✅ Deployment complete!"
echo ""
echo "📋 Next steps:"
echo "1. Edit $APP_DIR/.env with your API keys"
echo "2. Upload CSV files to $APP_DIR/data/"
echo "3. Upload lesson videos to $APP_DIR/static/lessons/"
echo "4. Restart service: systemctl restart flosc"
echo ""
echo "🔍 Check status: systemctl status flosc"
echo "📊 View logs: journalctl -u flosc -f"
echo "🌐 Visit: https://$DOMAIN"
