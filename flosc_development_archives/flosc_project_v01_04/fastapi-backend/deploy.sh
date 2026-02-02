#!/bin/bash
# LeSAEp FLOSC v01_04 - Production Deployment Script

set -e

echo "🚀 LeSAEp FLOSC v01_04 - Production Deployment"
echo "=============================================="

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Config
APP_DIR="/opt/lesaep-backend"
SERVICE_NAME="lesaep-backend"

# Check root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}❌ Run as root: sudo ./deploy.sh${NC}"
    exit 1
fi

echo -e "${YELLOW}📦 Step 1: Installing dependencies${NC}"
apt-get update
apt-get install -y python3 python3-pip python3-venv

echo -e "${GREEN}✅ System ready${NC}"

echo -e "${YELLOW}📁 Step 2: Setting up application${NC}"
mkdir -p $APP_DIR
cp -r . $APP_DIR/
cd $APP_DIR

echo -e "${GREEN}✅ Files copied${NC}"

echo -e "${YELLOW}🐍 Step 3: Python environment${NC}"
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

echo -e "${GREEN}✅ Python ready${NC}"

echo -e "${YELLOW}⚙️  Step 4: Configuration${NC}"
if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${YELLOW}⚠️  Edit .env: nano $APP_DIR/.env${NC}"
fi

echo -e "${GREEN}✅ Config ready${NC}"

echo -e "${YELLOW}🔧 Step 5: Systemd service${NC}"
cat > /etc/systemd/system/${SERVICE_NAME}.service << SERVICEEOF
[Unit]
Description=LeSAEp FLOSC v01_04
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$APP_DIR
Environment="PATH=$APP_DIR/venv/bin"
ExecStart=$APP_DIR/venv/bin/python main.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
SERVICEEOF

systemctl daemon-reload
systemctl enable ${SERVICE_NAME}

echo -e "${GREEN}✅ Service created${NC}"

echo -e "${YELLOW}▶️  Step 6: Starting service${NC}"
systemctl restart ${SERVICE_NAME}
sleep 2

if systemctl is-active --quiet ${SERVICE_NAME}; then
    echo -e "${GREEN}✅ Service running${NC}"
else
    echo -e "${RED}❌ Service failed${NC}"
    echo "Check logs: journalctl -u ${SERVICE_NAME} -n 50"
    exit 1
fi

echo ""
echo -e "${GREEN}🎉 Deployment Complete!${NC}"
echo ""
echo "Next steps:"
echo "  1. Edit .env: sudo nano $APP_DIR/.env"
echo "  2. Restart: sudo systemctl restart ${SERVICE_NAME}"
echo "  3. Test: curl http://localhost:8000/health"
echo "  4. Logs: journalctl -u ${SERVICE_NAME} -f"
echo ""
