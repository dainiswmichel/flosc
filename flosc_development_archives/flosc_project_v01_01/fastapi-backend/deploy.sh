#!/bin/bash
# LeSAEp FLOSC Backend Deployment Script
# Deploy FastAPI to DigitalOcean droplet

set -e

echo "🚀 LeSAEp FLOSC Backend Deployment"
echo "=================================="

# Configuration
APP_DIR="/opt/lesaep-backend"
SERVICE_NAME="lesaep-backend"
PYTHON_VERSION="python3"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}✗ Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Installing system dependencies${NC}"
apt-get update
apt-get install -y python3 python3-pip python3-venv nginx

echo -e "${GREEN}✓ System dependencies installed${NC}"

echo -e "${YELLOW}Step 2: Creating application directory${NC}"
mkdir -p $APP_DIR
cp -r . $APP_DIR/
cd $APP_DIR

echo -e "${GREEN}✓ Files copied to $APP_DIR${NC}"

echo -e "${YELLOW}Step 3: Setting up Python virtual environment${NC}"
$PYTHON_VERSION -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

echo -e "${GREEN}✓ Python environment configured${NC}"

echo -e "${YELLOW}Step 4: Configuring environment variables${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${YELLOW}⚠ Created .env file. Please edit with your actual values:${NC}"
    echo "  sudo nano $APP_DIR/.env"
fi

echo -e "${GREEN}✓ Environment file ready${NC}"

echo -e "${YELLOW}Step 5: Creating systemd service${NC}"
cat > /etc/systemd/system/${SERVICE_NAME}.service << EOF
[Unit]
Description=LeSAEp FLOSC Backend
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$APP_DIR
Environment="PATH=$APP_DIR/venv/bin"
ExecStart=$APP_DIR/venv/bin/uvicorn main:app --host 0.0.0.0 --port 8000
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable ${SERVICE_NAME}

echo -e "${GREEN}✓ Systemd service created${NC}"

echo -e "${YELLOW}Step 6: Starting service${NC}"
systemctl restart ${SERVICE_NAME}
sleep 2

if systemctl is-active --quiet ${SERVICE_NAME}; then
    echo -e "${GREEN}✓ Service started successfully${NC}"
else
    echo -e "${RED}✗ Service failed to start. Check logs:${NC}"
    echo "  journalctl -u ${SERVICE_NAME} -f"
    exit 1
fi

echo ""
echo -e "${GREEN}=================================="
echo "🎉 Deployment Complete!"
echo "==================================${NC}"
echo ""
echo "Service status:"
systemctl status ${SERVICE_NAME} --no-pager
echo ""
echo "Useful commands:"
echo "  Check logs:    journalctl -u ${SERVICE_NAME} -f"
echo "  Restart:       sudo systemctl restart ${SERVICE_NAME}"
echo "  Stop:          sudo systemctl stop ${SERVICE_NAME}"
echo "  Status:        sudo systemctl status ${SERVICE_NAME}"
echo ""
echo "Next steps:"
echo "  1. Edit .env file: sudo nano $APP_DIR/.env"
echo "  2. Restart service: sudo systemctl restart ${SERVICE_NAME}"
echo "  3. Test health: curl http://localhost:8000/health"
echo "  4. Configure Nginx proxy (see nginx-config/lesaep.conf)"
echo ""
