# Deployment Guide - DigitalOcean Droplet

## Prerequisites
- DigitalOcean account
- Domain name pointed to your droplet
- Droplet with Ubuntu 22.04 LTS (minimum 2GB RAM recommended)

## Step 1: Initial Server Setup

```bash
# SSH into your droplet
ssh root@your-server-ip

# Update system
apt-get update && apt-get upgrade -y

# Install required packages
apt-get install -y python3.10 python3-pip python3-venv nginx certbot python3-certbot-nginx git ffmpeg

# Create application user
adduser --disabled-password --gecos "" flosc
usermod -aG sudo flosc
```

## Step 2: Clone and Setup Application

```bash
# Switch to application user
su - flosc

# Clone repository (or upload files via SFTP)
mkdir -p /home/flosc/flosc-funnel-system
cd /home/flosc/flosc-funnel-system

# Create virtual environment
python3 -m venv venv
source venv/bin/activate

# Install dependencies
pip install -r requirements.txt
```

## Step 3: Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit with your credentials
nano .env
```

**Required Configuration:**
- `SUPABASE_URL`: Your Supabase project URL
- `SUPABASE_KEY`: Supabase anon key
- `BREVO_API_KEY`: Get from Brevo dashboard
- `STRIPE_SECRET_KEY`: Get from Stripe dashboard
- `STRIPE_WEBHOOK_SECRET`: Configure webhook endpoint
- Set `DOMAIN` to your actual domain
- Set `HTTPS_ENABLED=True` in production

## Step 4: Setup Supabase Database

1. Go to Supabase Dashboard
2. Run the SQL from `app/database.py` create_tables() docstring
3. Enable Row Level Security
4. Configure Auth providers (Email magic link)

## Step 5: Setup Nginx

```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/flosc
```

Paste this configuration:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    client_max_body_size 20M;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /static {
        alias /home/flosc/flosc-funnel-system/static;
    }

    location /uploads {
        alias /home/flosc/flosc-funnel-system/uploads;
        internal;
    }
}
```

Enable site and restart Nginx:

```bash
sudo ln -s /etc/nginx/sites-available/flosc /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## Step 6: SSL Certificate

```bash
# Get Let's Encrypt SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
```

## Step 7: Setup Systemd Service

```bash
sudo nano /etc/systemd/system/flosc.service
```

Paste:

```ini
[Unit]
Description=FLOSC Funnel System
After=network.target

[Service]
User=flosc
Group=flosc
WorkingDirectory=/home/flosc/flosc-funnel-system
Environment="PATH=/home/flosc/flosc-funnel-system/venv/bin"
ExecStart=/home/flosc/flosc-funnel-system/venv/bin/uvicorn app.main:app --host 0.0.0.0 --port 8000 --workers 2

Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable flosc
sudo systemctl start flosc
sudo systemctl status flosc
```

## Step 8: Configure Stripe Webhook

1. Go to Stripe Dashboard → Developers → Webhooks
2. Add endpoint: `https://yourdomain.com/api/payment/webhook`
3. Select events: `checkout.session.completed`
4. Copy webhook secret to `.env`

## Step 9: Upload Content

### CSV Files
Upload your CSV files to `/home/flosc/flosc-funnel-system/data/`

```bash
# Via SFTP or SCP
scp product.csv flosc@your-server:/home/flosc/flosc-funnel-system/data/
scp targeted_lessons.csv flosc@your-server:/home/flosc/flosc-funnel-system/data/
scp magic_sentences.csv flosc@your-server:/home/flosc/flosc-funnel-system/data/
```

### Lesson Videos/PDFs
Upload to `/home/flosc/flosc-funnel-system/static/lessons/`

```bash
# Create lessons directory
mkdir -p /home/flosc/flosc-funnel-system/static/lessons/

# Upload files via SFTP
```

## Step 10: Test Installation

```bash
# Check logs
sudo journalctl -u flosc -f

# Test endpoints
curl https://yourdomain.com/health
curl https://yourdomain.com/api/freeline/sentences
```

## Updating Content

### To Update CSVs:
1. Upload new CSV files via SFTP
2. Restart service: `sudo systemctl restart flosc`

### To Add New Lessons:
1. Add lesson to `targeted_lessons.csv`
2. Upload video/PDF to `static/lessons/`
3. Reload data (automatic on restart)

## Monitoring

```bash
# View logs
sudo journalctl -u flosc -f

# Check service status
sudo systemctl status flosc

# Restart if needed
sudo systemctl restart flosc
```

## Backup

```bash
# Backup script (run daily via cron)
#!/bin/bash
tar -czf /home/flosc/backups/flosc-$(date +%Y%m%d).tar.gz \
    /home/flosc/flosc-funnel-system/data/ \
    /home/flosc/flosc-funnel-system/uploads/ \
    /home/flosc/flosc-funnel-system/.env
```

## Cost Breakdown (Free Tier)

- **DigitalOcean**: $6/month (Basic Droplet)
- **Supabase**: $0 (500MB database, 50K MAU)
- **Brevo**: $0 (300 emails/day)
- **Stripe**: $0 monthly + 2.9% + $0.30 per transaction
- **Twilio** (optional): $0 (sandbox mode)
- **Telegram** (optional): $0
- **Domain**: ~$12/year
- **SSL**: $0 (Let's Encrypt)

**Total**: ~$6/month + transaction fees

## Troubleshooting

### Whisper Model Issues
If Whisper fails to load:
```bash
# Try smaller model
# In .env: WHISPER_MODEL=tiny
```

### Permission Issues
```bash
sudo chown -R flosc:flosc /home/flosc/flosc-funnel-system/
sudo chmod 755 /home/flosc/flosc-funnel-system/uploads/
```

### Email Not Sending
- Check Brevo API key
- Verify sender email is verified in Brevo
- Check logs for errors

## Security Checklist

- [ ] HTTPS enabled
- [ ] Firewall configured (ufw)
- [ ] SSH key authentication only
- [ ] Database RLS enabled
- [ ] Stripe webhook signature verified
- [ ] Regular backups scheduled
- [ ] Environment variables secured
- [ ] File upload size limited
- [ ] Rate limiting enabled

## Support

For issues, check:
1. Application logs: `sudo journalctl -u flosc -f`
2. Nginx logs: `sudo tail -f /var/log/nginx/error.log`
3. System resources: `htop`
