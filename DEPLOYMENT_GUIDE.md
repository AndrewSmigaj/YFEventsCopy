# YFEvents Comprehensive Deployment Guide

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
- Password: User-provided during setup

If MySQL setup fails, use the recovery script:
```bash
cd /var/www/yfevents
sudo ./scripts/deploy/fix-mysql-setup.sh
```

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