# YFEvents Comprehensive Deployment Guide

## Quick Start (Correct Order!)

```bash
# 1. Clone repository
git clone https://github.com/yourusername/yfevents.git /var/www/yfevents
cd /var/www/yfevents

# 2. Make scripts executable (ALWAYS NEEDED!)
chmod +x scripts/deploy/*.sh

# 3. Run server setup
./scripts/deploy/setup-server.sh

# 4. Configure MySQL (CRITICAL - Do this BEFORE deploy.sh!)
./scripts/deploy/fix-mysql-setup.sh
# This will show you the password - copy it or note the export command shown

# 5. Configure deployment
cp config/deployment/deployment.yaml.example config/deployment/deployment.yaml
nano config/deployment/deployment.yaml  # Edit with your domain

# 6. Deploy application (use the password from step 4)
export DB_PASSWORD=$(cat /root/.yfevents_db_pass)
./scripts/deploy/deploy.sh

# 7. Install modules
php modules/install.php yfauth
php modules/install.php yfclaim

# 8. Configure SSL (after domain is working)
certbot --apache -d yourdomain.com -d www.yourdomain.com
```

**Note**: On DigitalOcean as root, no 'sudo' needed

## Table of Contents
1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Server Setup Phase](#server-setup-phase)
4. [Application Deployment Phase](#application-deployment-phase)
5. [Module Installation](#module-installation)
6. [Configuration Files](#configuration-files)
7. [SSL Certificates](#ssl-certificates)
8. [Cron Jobs](#cron-jobs)
9. [Troubleshooting](#troubleshooting)
10. [Maintenance](#maintenance)
11. [Security Considerations](#security-considerations)

## Overview

YFEvents uses a two-phase deployment approach:
1. **Server Setup** (`setup-server.sh`) - Installs all system dependencies
2. **Application Deployment** (`deploy.sh`) - Deploys and configures the application

The deployment is configuration-driven using YAML files and supports multiple environments.

## Prerequisites

### Server Requirements
- **OS**: Ubuntu 22.04 LTS
- **RAM**: 2GB minimum (4GB recommended)
- **Storage**: 20GB minimum
- **CPU**: 1 core minimum (2+ recommended)
- **Network**: Public IP address

### Required Access
- Root or sudo access
- SSH access to server
- Domain name with DNS pointing to server IP

### Pre-deployment Checklist
- [ ] Domain DNS configured (A record pointing to server IP)
- [ ] Server firewall allows ports 22, 80, 443
- [ ] You have chosen a secure MySQL password
- [ ] You have an email for SSL certificates

## Server Setup Phase

### Running setup-server.sh

The server setup script handles all system-level installations:

```bash
cd ~
wget https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/refactor/unified-structure/scripts/deploy/setup-server.sh
chmod +x setup-server.sh
sudo ./setup-server.sh
```

#### What it installs:
- **Web Server**: Apache 2.4 with modules (rewrite, headers, expires, ssl, proxy_fcgi)
- **PHP**: Version 8.1+ with extensions (mysql, curl, mbstring, json, xml, gd, fileinfo)
- **Database**: MySQL 8.0 or compatible
- **Tools**: Git, Composer, Certbot, Fail2ban, Unzip, Curl
- **Security**: UFW firewall, Fail2ban for brute force protection

#### Script Process:
1. Updates system packages
2. Detects available PHP version (8.1-8.3)
3. Installs all required packages
4. Configures PHP with production settings
5. Sets up MySQL with secure installation
6. Creates database and user
7. Configures firewall rules
8. Creates swap file for performance
9. Sets up basic Apache configuration

#### Common Issues:
- **MySQL Auth Socket**: The script handles both auth_socket and password authentication
- **PHP Version**: Automatically detects the latest available PHP 8.x version
- **Permission Errors**: Script must be run with sudo

### MySQL Configuration

The setup script creates:
- Database: `yakima_finds`
- User: `yfevents`
- Password: Must be configured before deployment

**IMPORTANT - Do this BEFORE running deploy.sh:**
```bash
cd /var/www/yfevents
./scripts/deploy/fix-mysql-setup.sh
```

This script will:
- Create the database and user if they don't exist
- Generate a secure random password
- Update the .env file automatically
- Test the connection

**Note**: On DigitalOcean as root, omit 'sudo' from commands.

## Application Deployment Phase

### Running deploy.sh

The deployment script handles application-specific setup:

```bash
cd /var/www/yfevents
sudo ./scripts/deploy/deploy.sh
```

#### Deployment Steps (13 total):

1. **Load Configuration** - Reads deployment.yaml
2. **Validate Environment** - Checks all prerequisites
3. **Deploy Application Code** - Clones/updates repository
4. **Install Dependencies** - Runs Composer
5. **Configure Environment** - Creates .env file
6. **Set Directory Permissions** - Sets ownership and permissions
7. **Configure Database** - Runs migrations and schemas
8. **Configure Apache** - Sets up virtual host
9. **Install/Update Modules** - Placeholder for modules
10. **Configure Cron Jobs** - Sets up scheduled tasks
11. **Create Admin User** - Interactive admin creation
12. **Post-Deployment Validation** - Health checks
13. **Cleanup** - Cache clearing and optimization

#### Configuration Required:

Edit `config/deployment/deployment.yaml`:
```yaml
apache:
  server_name: "yourdomain.com"
  server_admin: "admin@yourdomain.com"
```

### Pre-Deployment Checklist

**Before running deploy.sh, ensure:**

1. ✅ MySQL is configured (run fix-mysql-setup.sh first - see above)
2. ✅ Domain is pointed to your server's IP
3. ✅ You've edited deployment.yaml with your domain info

## Module Installation

After deployment, install the required modules:

### YFAuth Module (Authentication)
```bash
cd /var/www/yfevents
sudo -u www-data php modules/install.php yfauth
```
- Provides centralized authentication
- Creates auth tables with yfa_ prefix
- Default admin: Set during installation

### YFClaim Module (Estate Sales)
```bash
sudo -u www-data php modules/install.php yfclaim
```
- Estate sale management system
- Creates tables with yfc_ prefix
- Integrates with YFAuth for seller accounts

### YFTheme Module (Theming)
```bash
sudo -u www-data php modules/install.php yftheme
```
- Theme customization system
- Admin interface for theme management

### Module Installation Process:
1. Checks PHP version and extension requirements
2. Runs database migrations from module's database/ directory
3. Copies public files to appropriate directories
4. Registers module in database
5. Runs module-specific installation scripts

## Configuration Files

### Hierarchy of Configuration:

1. **deployment.yaml** - Deployment-specific settings
   - Repository details
   - Server configuration
   - Module activation
   - Directory permissions

2. **.env** - Runtime application settings
   - Database credentials
   - API keys
   - Cache settings
   - Session configuration

3. **module.json** - Module manifests
   - Dependencies
   - Hooks
   - Permissions
   - Settings

### Key Configuration Values:

**.env essentials:**
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_HOST=localhost
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=your_password

GOOGLE_MAPS_API_KEY=your_api_key_here
```

## Firewall Configuration

### Check Open Ports:
```bash
# Check UFW status and rules
sudo ufw status verbose

# List all UFW rules with numbers
sudo ufw status numbered

# Check if specific port is allowed
sudo ufw status | grep 80
sudo ufw status | grep 443
sudo ufw status | grep 22

# Alternative: Check with iptables directly
sudo iptables -L -n -v

# Check listening ports
sudo netstat -tlnp
# or
sudo ss -tlnp
```

### Common Required Ports:
- **22** - SSH (should be limited to specific IPs if possible)
- **80** - HTTP (required for web access and Let's Encrypt)
- **443** - HTTPS (required for SSL)
- **3306** - MySQL (only if remote database access needed)

### Configure Firewall:

**WARNING**: Before enabling UFW, ensure SSH (port 22) is allowed to avoid locking yourself out!

```bash
# If UFW is inactive, set up rules BEFORE enabling
# First, allow SSH to prevent lockout
sudo ufw allow 22/tcp

# Allow other essential ports
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# THEN enable UFW
sudo ufw enable

# Allow from specific IP only (more secure for SSH)
sudo ufw allow from YOUR_IP_ADDRESS to any port 22

# Deny a port
sudo ufw deny 3306

# Delete a rule
sudo ufw delete allow 80/tcp
```

### If Firewall is Inactive:
```bash
# Check current iptables rules (even if UFW is inactive)
sudo iptables -L -n -v

# If you need to check what's actually accessible, scan from another machine
nmap -p 22,80,443,3306 YOUR_SERVER_IP

# Or check locally what's listening
sudo netstat -tlnp | grep LISTEN
```

## SSL Certificates

SSL certificates are managed by Certbot (Let's Encrypt):

### Automatic Setup:
The deployment process should automatically:
1. Configure Apache for SSL
2. Request certificate from Let's Encrypt
3. Set up automatic renewal

### Manual Certificate Generation:
```bash
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com
```

### Certificate Renewal:
```bash
# Test renewal
sudo certbot renew --dry-run

# Force renewal
sudo certbot renew --force-renewal

# Check renewal timer
sudo systemctl status certbot.timer
```

## Cron Jobs

### Event Scraping Cron:
The deployment sets up automatic event scraping:
```bash
# Runs every 6 hours
0 */6 * * * cd /var/www/yfevents && /usr/bin/php cron/scrape-events.php >> storage/logs/cron.log 2>&1
```

### Verify Cron Installation:
```bash
# Check www-data user's crontab
sudo crontab -u www-data -l

# Check cron logs
tail -f /var/www/yfevents/storage/logs/cron.log
```

### Manual Cron Management:
```bash
# Edit crontab
sudo crontab -u www-data -e

# Run scraper manually
cd /var/www/yfevents
sudo -u www-data php cron/scrape-events.php
```

## Troubleshooting

### Common Issues and Solutions:

#### Missing .env.example Error
```bash
# If you get "[✗] No .env.example found"
# Create it manually:
cat > /var/www/yfevents/.env.example << 'EOF'
# YFEvents Environment Configuration
APP_NAME="YFEvents"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://yourdomain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=your_password_here
DB_CHARSET=utf8mb4

# Google Maps API
GOOGLE_MAPS_API_KEY=your_api_key_here

# Mail Configuration
MAIL_DRIVER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="YFEvents"

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=warning
EOF

# Then retry deployment
```

#### MySQL Connection Errors
```bash
# Check credentials in .env
cat .env | grep DB_

# Test connection
mysql -u yfevents -p yakima_finds -e "SELECT 1"

# Fix MySQL setup
sudo ./scripts/deploy/fix-mysql-setup.sh
```

#### Permission Errors
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/yfevents

# Fix directory permissions
sudo find /var/www/yfevents -type d -exec chmod 755 {} \;

# Fix file permissions
sudo find /var/www/yfevents -type f -exec chmod 644 {} \;

# Storage directories need write permission
sudo chmod -R 775 storage/ public/uploads/
```

#### Apache Issues
```bash
# Test configuration
sudo apache2ctl configtest

# Check error logs
sudo tail -f /var/log/apache2/error.log

# Restart Apache
sudo systemctl restart apache2

# Enable site
sudo a2ensite yfevents
sudo systemctl reload apache2
```

#### Module Not Working
```bash
# Check module registration
mysql -u yfevents -p yakima_finds -e "SELECT * FROM modules;"

# Re-run module installation
cd /var/www/yfevents
sudo -u www-data php modules/install.php [module_name]

# Check module files exist
ls -la modules/[module_name]/
```

#### SSL Certificate Issues
```bash
# Check certificate status
sudo certbot certificates

# View Apache SSL config
sudo cat /etc/apache2/sites-available/yfevents-le-ssl.conf

# Test SSL
curl -I https://yourdomain.com
```

### Health Check

Run the comprehensive health check:
```bash
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/health-check.php
```

This checks:
- Database connectivity
- Required PHP extensions
- Directory permissions
- Module status
- Configuration validity

## Maintenance

### Regular Tasks

#### Updates
```bash
cd /var/www/yfevents
sudo -u www-data git pull origin refactor/unified-structure
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php scripts/deploy/run-sql-files.php
```

#### Backup Database
```bash
# Create backup
mysqldump -u yfevents -p yakima_finds > backup_$(date +%Y%m%d).sql

# Compress backup
gzip backup_$(date +%Y%m%d).sql
```

#### Monitor Logs
```bash
# Application logs
tail -f storage/logs/yfevents.log

# Apache logs
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/access.log

# Cron logs
tail -f storage/logs/cron.log
```

#### Clear Cache
```bash
cd /var/www/yfevents
sudo rm -rf storage/cache/*
sudo -u www-data php artisan cache:clear  # If using framework
```

### Performance Optimization

#### Enable OPcache
Already configured in PHP installation, verify with:
```bash
php -i | grep opcache
```

#### Monitor Resource Usage
```bash
# Check memory
free -m

# Check disk space
df -h

# Check CPU
top
```

## Security Considerations

### Implemented Security Measures:
- Fail2ban for brute force protection
- UFW firewall with minimal open ports
- SSL/TLS encryption
- Secure MySQL installation
- File permissions properly set

### Additional Recommendations:

#### SSH Hardening
```bash
# Disable password authentication
sudo nano /etc/ssh/sshd_config
# Set: PasswordAuthentication no

# Use SSH keys only
ssh-copy-id user@server
```

#### Regular Updates
```bash
# Enable automatic security updates
sudo apt install unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

#### Application Security
- Keep `.env` file secure (600 permissions)
- Regularly update dependencies
- Monitor logs for suspicious activity
- Use strong passwords for all accounts

### Monitoring

Set up monitoring for:
- Server resources (CPU, RAM, disk)
- Application errors
- SSL certificate expiration
- Database performance
- Uptime monitoring

## Appendix

### File Locations
- Application: `/var/www/yfevents/`
- Apache Config: `/etc/apache2/sites-available/yfevents.conf`
- PHP Config: `/etc/php/8.x/fpm/php.ini`
- Logs: `/var/www/yfevents/storage/logs/`
- Uploads: `/var/www/yfevents/public/uploads/`

### Useful Commands
```bash
# Check PHP version
php -v

# Check MySQL version
mysql --version

# Check Apache version
apache2 -v

# List installed PHP extensions
php -m

# Check server resources
htop  # or top

# Check disk usage
df -h
du -sh /var/www/yfevents/
```

### Support Resources
- GitHub Issues: https://github.com/AndrewSmigaj/YFEventsCopy/issues
- Documentation: This guide and QUICK_DEPLOY_GUIDE.txt
- Logs: Always check logs first for error details