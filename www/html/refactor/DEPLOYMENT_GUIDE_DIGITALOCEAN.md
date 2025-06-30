# YFEvents Digital Ocean Deployment Guide

**Version**: 2.0.0  
**Last Updated**: June 2025  
**Estimated Time**: 45-60 minutes  
**Estimated Cost**: $12-24/month (Basic setup)

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Digital Ocean Setup](#digital-ocean-setup)
3. [Server Provisioning](#server-provisioning)
4. [Environment Setup](#environment-setup)
5. [Application Deployment](#application-deployment)
6. [Database Setup](#database-setup)
7. [Web Server Configuration](#web-server-configuration)
8. [SSL/TLS Setup](#ssltls-setup)
9. [Performance Optimization](#performance-optimization)
10. [Monitoring & Maintenance](#monitoring--maintenance)
11. [Backup Strategy](#backup-strategy)
12. [Troubleshooting](#troubleshooting)

## Prerequisites

### Required Knowledge
- Basic Linux command line
- SSH key management
- Domain DNS configuration
- Basic PHP/MySQL understanding

### Required Assets
- Digital Ocean account
- Domain name (e.g., yakimafinds.com)
- GitHub repository access
- Google Maps API key
- Local development environment

### Recommended Droplet Specifications

**For Production (Recommended)**:
- **Size**: 2 vCPUs, 4GB RAM, 80GB SSD ($24/month)
- **OS**: Ubuntu 22.04 LTS
- **Region**: SFO3 (or closest to target audience)
- **Additional**: Enable backups (+20%)

**For Testing/Staging**:
- **Size**: 1 vCPU, 2GB RAM, 50GB SSD ($12/month)
- **OS**: Ubuntu 22.04 LTS

## Digital Ocean Setup

### 1. Create Droplet

```bash
# Using DO CLI (optional)
doctl compute droplet create yfevents-prod \
  --region sfo3 \
  --size s-2vcpu-4gb \
  --image ubuntu-22-04-x64 \
  --ssh-keys YOUR_SSH_KEY_ID \
  --enable-monitoring \
  --enable-backups
```

Or use the Digital Ocean control panel:
1. Click "Create" → "Droplets"
2. Choose Ubuntu 22.04 LTS
3. Select datacenter region
4. Choose droplet size
5. Add SSH key
6. Enable backups and monitoring
7. Set hostname: `yfevents-prod`

### 2. Initial Server Access

```bash
# Connect to your droplet
ssh root@YOUR_DROPLET_IP

# Create a non-root user
adduser yfadmin
usermod -aG sudo yfadmin

# Copy SSH keys to new user
rsync --archive --chown=yfadmin:yfadmin ~/.ssh /home/yfadmin

# Test new user access (from local machine)
ssh yfadmin@YOUR_DROPLET_IP
```

## Server Provisioning

### 1. Update System

```bash
# Update package lists
sudo apt update && sudo apt upgrade -y

# Install essential packages
sudo apt install -y \
  curl \
  wget \
  git \
  unzip \
  software-properties-common \
  apt-transport-https \
  ca-certificates \
  gnupg \
  lsb-release \
  ufw \
  fail2ban \
  htop \
  ncdu
```

### 2. Configure Firewall

```bash
# Setup UFW firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw --force enable

# Check status
sudo ufw status
```

### 3. Install PHP 8.2

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP and required extensions
sudo apt install -y \
  php8.2-fpm \
  php8.2-cli \
  php8.2-common \
  php8.2-mysql \
  php8.2-xml \
  php8.2-curl \
  php8.2-gd \
  php8.2-mbstring \
  php8.2-zip \
  php8.2-bcmath \
  php8.2-intl \
  php8.2-readline \
  php8.2-opcache

# Verify installation
php -v
```

### 4. Install Nginx

```bash
# Install Nginx
sudo apt install -y nginx

# Start and enable Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Verify
sudo systemctl status nginx
```

### 5. Install MySQL 8.0

```bash
# Install MySQL
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation
# Answer: Y, 2 (STRONG), Y, Y, Y, Y

# Create database and user
sudo mysql -u root -p

# In MySQL prompt:
CREATE DATABASE yakima_finds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yfevents'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON yakima_finds.* TO 'yfevents'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 6. Install Composer

```bash
# Download and install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Verify
composer --version
```

## Environment Setup

### 1. Create Directory Structure

```bash
# Create web directory
sudo mkdir -p /var/www/yfevents
sudo chown -R yfadmin:www-data /var/www/yfevents
sudo chmod -R 755 /var/www/yfevents

# Create storage directories
cd /var/www/yfevents
mkdir -p storage/{logs,cache,sessions,uploads}
mkdir -p public/uploads/{events,shops,claims}

# Set permissions
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### 2. Clone Repository

```bash
# Clone the repository
cd /var/www/yfevents
git clone https://github.com/AndrewSmigaj/YFEventsCopy.git .
git checkout origin/refactor/v2-complete-rebuild

# Or if using deploy keys
git clone git@github.com:AndrewSmigaj/YFEventsCopy.git .
```

### 3. Install Dependencies

```bash
# Install PHP dependencies
cd /var/www/yfevents/www/html/refactor
composer install --no-dev --optimize-autoloader

# Fix permissions
sudo chown -R yfadmin:www-data /var/www/yfevents
sudo find /var/www/yfevents -type f -exec chmod 664 {} \;
sudo find /var/www/yfevents -type d -exec chmod 775 {} \;
```

### 4. Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit environment configuration
nano .env
```

Update `.env` with:
```env
# Application
APP_NAME=YFEvents
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=YOUR_DB_PASSWORD

# APIs
GOOGLE_MAPS_API_KEY=YOUR_API_KEY

# Mail (optional)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# Cache
CACHE_DRIVER=file
SESSION_DRIVER=file
```

## Database Setup

### 1. Import Schema

```bash
# Import base schema
cd /var/www/yfevents/www/html/refactor
mysql -u yfevents -p yakima_finds < database/schema.sql

# Import module schemas
mysql -u yfevents -p yakima_finds < database/modules/yfauth_schema.sql
mysql -u yfevents -p yakima_finds < database/modules/yfclaim_schema.sql
mysql -u yfevents -p yakima_finds < database/modules/communication_schema.sql

# Import sample data (optional)
mysql -u yfevents -p yakima_finds < database/sample_data.sql
```

### 2. Run Migrations (if available)

```bash
# Check for migrations
php artisan migrate --force
```

## Web Server Configuration

### 1. Configure PHP-FPM

```bash
# Edit PHP-FPM pool configuration
sudo nano /etc/php/8.2/fpm/pool.d/www.conf
```

Update these values:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

```bash
# Edit PHP configuration
sudo nano /etc/php/8.2/fpm/php.ini
```

Update these values:
```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
```

### 2. Configure Nginx

```bash
# Create Nginx configuration
sudo nano /etc/nginx/sites-available/yfevents
```

Add this configuration:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/yfevents/www/html/refactor/public;

    index index.php index.html;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Logging
    access_log /var/log/nginx/yfevents_access.log;
    error_log /var/log/nginx/yfevents_error.log;

    # Main application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP processing
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_buffer_size 32k;
        fastcgi_buffers 8 16k;
        fastcgi_read_timeout 300;
    }

    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Deny access to sensitive files
    location ~* \.(env|git|gitignore|lock)$ {
        deny all;
    }

    # API rate limiting
    location /api/ {
        limit_req zone=api burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Upload size for specific endpoints
    location ~ ^/(api/upload|admin/upload) {
        client_max_body_size 20M;
        try_files $uri $uri/ /index.php?$query_string;
    }
}

# Rate limiting zone
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/yfevents /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

## SSL/TLS Setup

### Using Let's Encrypt (Recommended)

```bash
# Install Certbot
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

### Using Digital Ocean Load Balancer (Alternative)

1. Create Load Balancer in DO panel
2. Add SSL certificate
3. Point to your droplet
4. Update DNS to point to Load Balancer

## Performance Optimization

### 1. Enable OPcache

```bash
# Edit OPcache configuration
sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
```

Add optimized settings:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
opcache.enable_cli=1
opcache.validate_timestamps=0
```

### 2. Configure Nginx Caching

```bash
# Add to Nginx configuration
fastcgi_cache_path /var/cache/nginx levels=1:2 keys_zone=YFEVENTS:100m inactive=60m;
fastcgi_cache_key "$scheme$request_method$host$request_uri";
```

### 3. Setup Redis (Optional)

```bash
# Install Redis
sudo apt install -y redis-server

# Configure Redis
sudo nano /etc/redis/redis.conf
# Set: maxmemory 256mb
# Set: maxmemory-policy allkeys-lru

# Restart Redis
sudo systemctl restart redis
```

### 4. Setup Cron Jobs

```bash
# Edit crontab
sudo crontab -e

# Add cron jobs
# Event scraping (daily at 2 AM)
0 2 * * * cd /var/www/yfevents/www/html/refactor && php scripts/scrape-events.php >> /var/log/yfevents/scraper.log 2>&1

# Cache cleanup (daily at 3 AM)
0 3 * * * cd /var/www/yfevents/www/html/refactor && php scripts/cleanup-cache.php >> /var/log/yfevents/cleanup.log 2>&1

# Database backup (daily at 4 AM)
0 4 * * * /usr/bin/mysqldump -u yfevents -p'PASSWORD' yakima_finds | gzip > /var/backups/yfevents/db_$(date +\%Y\%m\%d).sql.gz
```

## Monitoring & Maintenance

### 1. Setup Monitoring

```bash
# Install monitoring agent
curl -sSL https://repos.insights.digitalocean.com/install.sh | sudo bash

# Setup log rotation
sudo nano /etc/logrotate.d/yfevents
```

Add:
```
/var/log/yfevents/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload nginx
    endscript
}
```

### 2. Setup Health Checks

Create health check endpoint:
```bash
# Create health check file
nano /var/www/yfevents/www/html/refactor/public/health.php
```

```php
<?php
// Simple health check
try {
    // Check database
    $pdo = new PDO('mysql:host=localhost;dbname=yakima_finds', 'yfevents', 'PASSWORD');
    $pdo->query('SELECT 1');
    
    // Check disk space
    $free = disk_free_space('/');
    $total = disk_total_space('/');
    $percent = ($free / $total) * 100;
    
    if ($percent < 10) {
        throw new Exception('Low disk space');
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'healthy', 'disk_free' => round($percent, 2) . '%']);
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['status' => 'unhealthy', 'error' => $e->getMessage()]);
}
```

### 3. Setup Alerts

Use Digital Ocean monitoring:
1. Go to Droplet → Monitoring
2. Create alert policies for:
   - CPU usage > 80%
   - Memory usage > 85%
   - Disk usage > 90%
   - Bandwidth usage thresholds

## Backup Strategy

### 1. Automated Backups

```bash
# Create backup script
sudo nano /usr/local/bin/yfevents-backup.sh
```

```bash
#!/bin/bash
# YFEvents Backup Script

BACKUP_DIR="/var/backups/yfevents"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="yakima_finds"
DB_USER="yfevents"
DB_PASS="YOUR_PASSWORD"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz -C /var/www/yfevents \
    www/html/refactor/storage \
    www/html/refactor/public/uploads \
    www/html/refactor/.env \
    www/html/refactor/config

# Backup to Digital Ocean Spaces (optional)
# s3cmd put $BACKUP_DIR/db_$DATE.sql.gz s3://your-space/backups/
# s3cmd put $BACKUP_DIR/files_$DATE.tar.gz s3://your-space/backups/

# Keep only last 7 days of local backups
find $BACKUP_DIR -name "*.gz" -mtime +7 -delete

echo "Backup completed: $DATE"
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/yfevents-backup.sh

# Add to crontab
0 1 * * * /usr/local/bin/yfevents-backup.sh >> /var/log/yfevents/backup.log 2>&1
```

### 2. Digital Ocean Backups

Enable in DO panel:
- Weekly backups: $4.80/month
- Snapshots: On-demand before major changes

## Troubleshooting

### Common Issues

#### 1. 502 Bad Gateway
```bash
# Check PHP-FPM
sudo systemctl status php8.2-fpm
sudo journalctl -u php8.2-fpm

# Check socket
ls -la /var/run/php/php8.2-fpm.sock

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

#### 2. Permission Denied
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/yfevents/www/html/refactor/storage
sudo chmod -R 775 /var/www/yfevents/www/html/refactor/storage
```

#### 3. Database Connection Error
```bash
# Test connection
mysql -u yfevents -p -h localhost yakima_finds

# Check credentials in .env
cat /var/www/yfevents/www/html/refactor/.env | grep DB_
```

#### 4. Memory Issues
```bash
# Check memory usage
free -h
htop

# Adjust PHP memory limit
sudo nano /etc/php/8.2/fpm/php.ini
# memory_limit = 512M

# Add swap if needed
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

### Logs to Check

```bash
# Application logs
tail -f /var/www/yfevents/www/html/refactor/storage/logs/*.log

# Nginx logs
tail -f /var/log/nginx/yfevents_error.log

# PHP logs
tail -f /var/log/php8.2-fpm.log

# System logs
sudo journalctl -xe
```

## Post-Deployment Checklist

- [ ] Site accessible via HTTPS
- [ ] Database connections working
- [ ] File uploads functioning
- [ ] Cron jobs running
- [ ] Email sending (if configured)
- [ ] Google Maps loading
- [ ] Admin panel accessible
- [ ] API endpoints responding
- [ ] Error logging working
- [ ] Backups scheduled
- [ ] Monitoring enabled
- [ ] Health check endpoint working
- [ ] Rate limiting active
- [ ] Security headers present

## Scaling Considerations

### When to Scale

- CPU consistently > 80%
- Memory usage > 85%
- Response times > 1 second
- Concurrent users > 100

### Scaling Options

1. **Vertical Scaling**: Resize droplet
2. **Horizontal Scaling**: Add load balancer + multiple droplets
3. **Database Scaling**: Managed MySQL cluster
4. **Caching Layer**: Redis/Memcached
5. **CDN**: CloudFlare or DO Spaces CDN

## Cost Optimization

- Use reserved IPs ($5/month)
- Enable backups selectively
- Use Spaces for static assets
- Monitor bandwidth usage
- Right-size droplets based on metrics

## Security Hardening

```bash
# Additional security measures
# Install fail2ban
sudo apt install fail2ban

# Configure fail2ban for Nginx
sudo nano /etc/fail2ban/jail.local
```

Add:
```ini
[nginx-limit-req]
enabled = true
filter = nginx-limit-req
action = iptables-multiport[name=ReqLimit, port="http,https", protocol=tcp]
logpath = /var/log/nginx/*error.log
findtime = 600
maxretry = 10
bantime = 7200
```

## Support Resources

- Digital Ocean Support: https://www.digitalocean.com/support/
- YFEvents Documentation: [Your docs URL]
- Community: [Your community URL]
- Emergency Contact: [Your contact]

---

**Deployment Complete!** 🎉

Your YFEvents application should now be running on Digital Ocean. Monitor the logs and metrics for the first 24-48 hours to ensure stability.