# YFEvents Deployment Guide

This guide walks you through deploying YFEvents to a Digital Ocean Ubuntu 22.04 droplet using the automated deployment script.

## Prerequisites

### 1. Digital Ocean Droplet
- **OS**: Ubuntu 22.04 LTS (required)
- **Size**: Minimum 2GB RAM, 4GB recommended
- **Storage**: 25GB+ recommended
- **Region**: Choose closest to your users

### 2. Domain Configuration
- Domain name pointing to your droplet's IP address
- DNS A record configured

### 3. Required Information
Gather these before starting:
- **Domain name** (e.g., events.example.com)
- **Admin email** (for SSL certificates and notifications)
- **Google Maps API key** (from Google Cloud Console)
- **Strong passwords** for MySQL root and application database

### 4. Access Requirements
- Root or sudo access to the droplet
- SSH key configured for secure access

### 5. GitHub Repository Access
Since the deployment uses SSH to clone the repository, you'll need to set up a deploy key:

#### Option A: Deploy Key (Recommended)
1. Generate an SSH key on your droplet:
   ```bash
   ssh-keygen -t rsa -b 4096 -f ~/.ssh/yfevents_deploy -N ""
   ```

2. Copy the public key:
   ```bash
   cat ~/.ssh/yfevents_deploy.pub
   ```

3. Add to GitHub:
   - Go to https://github.com/AndrewSmigaj/YFEventsCopy/settings/keys
   - Click "Add deploy key"
   - Title: "YFEvents Production Server"
   - Paste the public key
   - Check "Allow write access" if you need push capabilities
   - Click "Add key"

#### Option B: Personal Access Token
If you prefer HTTPS, you can use a personal access token instead. Update the REPO_URL in the script to use HTTPS.

## Step-by-Step Deployment

### Step 1: Access Your Droplet

```bash
# Connect via SSH
ssh root@your-droplet-ip

# Or if using non-root user with sudo
ssh username@your-droplet-ip
```

### Step 2: Set Up SSH Key for Repository Access

The deployment script needs SSH access to clone the repository. You'll use the root user's SSH key.

```bash
# Generate SSH key (as root)
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa -N ""

# Display the public key
cat ~/.ssh/id_rsa.pub

# Test GitHub connection
ssh -T git@github.com
```

**Important**: Copy the public key output and add it to your GitHub repository:
1. Go to https://github.com/AndrewSmigaj/YFEventsCopy/settings/keys
2. Click "Add deploy key"
3. Title: "YFEvents Production Deploy Key"
4. Paste the public key
5. Leave "Allow write access" unchecked (read-only is safer)
6. Click "Add key"

**Note**: The deployment script will create the `yfevents` user automatically - do not create it manually.

### Step 3: Download the Deployment Script

```bash
# Download the script
wget https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/main/yfevents-deploy.sh

# Or use curl
curl -O https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/main/yfevents-deploy.sh

# Make it executable
chmod +x yfevents-deploy.sh
```

### Step 4: Run the Deployment Script

```bash
# Run with default settings (interactive mode)
sudo ./yfevents-deploy.sh

# Or run with parameters
sudo ./yfevents-deploy.sh --domain events.example.com --email admin@example.com
```

### Step 5: Follow Interactive Prompts

The script will prompt for:

1. **Domain Name**: Enter your full domain (e.g., events.example.com)
2. **Admin Email**: Your email for SSL certificates and notifications
3. **Google Maps API Key**: Your API key from Google Cloud Console (optional)
4. **MySQL Root Password**: Create a strong password for MySQL root user
5. **Database User Password**: Create a password for the yfevents database user

### Step 6: Monitor Installation Progress

The script displays progress with colored output:
- 🔵 Blue headers for each major section
- ✅ Green checkmarks for successful steps
- ⚠️ Yellow warnings for non-critical issues
- ❌ Red X's for errors that need attention

Installation typically takes 10-15 minutes.

## What the Script Does

### 1. System Validation
- Verifies Ubuntu 22.04 LTS
- Checks root/sudo access
- Tests internet connectivity
- Validates available ports

### 2. System Updates
- Updates package lists
- Upgrades installed packages
- Installs essential tools

### 3. Service Installation
- **Apache 2.4**: Web server with mod_rewrite, headers, SSL
- **PHP 8.2**: With extensions (curl, mysql, gd, xml, mbstring, zip)
- **MySQL 8.0**: Database server with secure configuration
- **Git**: For repository cloning
- **Composer**: PHP dependency manager

### 4. Security Configuration
- Configures UFW firewall (ports 22, 80, 443)
- Sets up fail2ban for intrusion prevention
- Configures secure MySQL installation
- Creates dedicated deployment user

### 5. Application Deployment
- Clones repository from GitHub
- Installs PHP dependencies via Composer
- Creates .env configuration file
- Sets proper file permissions

### 6. Web Server Configuration
- Creates Apache virtual host
- Configures PHP-FPM for better performance
- Enables site and required modules
- Sets up URL rewriting

### 7. SSL Certificate
- Installs Certbot
- Obtains Let's Encrypt certificate
- Configures automatic renewal

### 8. Database Setup
- Creates application database
- Creates database user with limited privileges
- Installs all database schemas in correct order:
  - Core tables (events, shops, sources)
  - Communication system tables
  - Module tables (auth, claims)
  - Performance optimizations

### 9. Cron Jobs
- Sets up event scraping (daily at 2 AM)
- Configures log rotation
- Adds health check monitoring

## Post-Deployment Steps

### 1. Verify Installation

```bash
# Check services are running
systemctl status apache2
systemctl status mysql
systemctl status php8.2-fpm

# Test the application
curl -I https://your-domain.com

# Check application health
curl https://your-domain.com/health-check.php
```

### 2. Access Admin Panel

1. Navigate to: `https://your-domain.com/admin/`
2. Default credentials will be displayed after installation
3. **IMPORTANT**: Change default password immediately

### 3. Configure Application

1. **Update Settings**:
   - Go to Admin → Settings
   - Configure site name, timezone, etc.

2. **Set Up Event Sources**:
   - Go to Admin → Scrapers
   - Configure event data sources

3. **Configure Email** (if using email features):
   - Go to Admin → Email Config
   - Set up IMAP/SMTP settings

### 4. Security Hardening

```bash
# Disable root SSH (if not already done)
sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
systemctl restart sshd

# Set up automatic security updates
apt install unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

## Troubleshooting

### Common Issues

#### 1. Git Clone Failed
- **SSH Key Issues**:
  ```bash
  # Test SSH connection as deployment user
  sudo -u yfevents ssh -T git@github.com
  
  # Check SSH key exists
  sudo ls -la /home/yfevents/.ssh/
  
  # Verify key is added to GitHub
  # Go to: https://github.com/AndrewSmigaj/YFEventsCopy/settings/keys
  ```

- **Permission denied**:
  ```bash
  # Fix SSH directory permissions
  sudo chmod 700 /home/yfevents/.ssh
  sudo chmod 600 /home/yfevents/.ssh/id_rsa
  sudo chmod 644 /home/yfevents/.ssh/id_rsa.pub
  sudo chown -R yfevents:yfevents /home/yfevents/.ssh
  ```

- **Host verification failed**:
  ```bash
  # Add GitHub to known hosts
  sudo -u yfevents ssh-keyscan -H github.com >> /home/yfevents/.ssh/known_hosts
  ```

#### 2. Domain Not Accessible
- **Check DNS**: `dig your-domain.com`
- **Verify A record**: Points to droplet IP
- **Wait for propagation**: Can take up to 48 hours

#### 3. SSL Certificate Failed
- **Manual retry**: `sudo certbot --apache -d your-domain.com`
- **Check domain**: Ensure it resolves to your server
- **Firewall**: Verify port 80 is open

#### 4. Database Connection Errors
- **Check credentials**: In `/var/www/yfevents/.env`
- **Test connection**: `mysql -u yfevents -p yakima_finds`
- **Check logs**: `/var/log/mysql/error.log`

#### 5. Permission Errors
- **Fix ownership**: `sudo chown -R www-data:www-data /var/www/yfevents`
- **Fix permissions**: `sudo chmod -R 755 /var/www/yfevents`

### Log Files

Check these for detailed error information:
- **Deployment log**: `/var/log/yfevents-deploy.log`
- **Apache error**: `/var/log/apache2/yfevents_error.log`
- **Apache access**: `/var/log/apache2/yfevents_access.log`
- **PHP errors**: `/var/log/php8.2-fpm.log`
- **MySQL errors**: `/var/log/mysql/error.log`

## Rollback Procedure

If deployment fails:

### 1. Check State File
```bash
cat /tmp/yfevents-deploy-state.json
```

### 2. Manual Rollback
```bash
# Remove application files
sudo rm -rf /var/www/yfevents

# Drop database
mysql -u root -p -e "DROP DATABASE IF EXISTS yakima_finds;"

# Remove Apache config
sudo a2dissite yfevents.conf
sudo rm /etc/apache2/sites-available/yfevents.conf

# Remove created user
sudo userdel -r yfevents

# Reset firewall (careful!)
sudo ufw --force reset
sudo ufw allow 22
sudo ufw enable
```

### 3. Retry Deployment
The script tracks progress and can resume from failure point.

## Maintenance

### Regular Tasks

#### Daily
- Monitor error logs
- Check disk space: `df -h`
- Verify backups completed

#### Weekly
- Review security logs
- Update event sources
- Check SSL certificate expiry

#### Monthly
- System updates: `sudo apt update && sudo apt upgrade`
- Database optimization: `mysqlcheck -o yakima_finds`
- Review user accounts

### Backup Strategy

#### Manual Backup
```bash
# Database backup
mysqldump -u root -p yakima_finds > /backup/yfevents_$(date +%Y%m%d).sql

# File backup
tar -czf /backup/yfevents_files_$(date +%Y%m%d).tar.gz /var/www/yfevents

# Copy to external storage
scp /backup/*.tar.gz backup-server:/path/to/backups/
```

#### Automated Backup Script
Create `/usr/local/bin/backup-yfevents.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/backup/yfevents"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u root -pYOUR_PASSWORD yakima_finds > $BACKUP_DIR/db_$DATE.sql

# Backup files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/yfevents

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

Add to crontab:
```bash
0 3 * * * /usr/local/bin/backup-yfevents.sh
```

## Getting Help

### Resources
- **Logs**: Always check `/var/log/yfevents-deploy.log` first
- **Documentation**: See `/var/www/yfevents/README.md`
- **Admin Guide**: Available in admin panel

### Support Channels
- GitHub Issues: [Repository Issues Page]
- Email: [Your Support Email]
- Documentation: [Your Docs Site]

## Security Notes

### Important Reminders
1. **Change default passwords** immediately after installation
2. **Keep system updated** with security patches
3. **Monitor logs** for suspicious activity
4. **Backup regularly** before making changes
5. **Test updates** in staging environment first

### API Key Security
- Never commit API keys to version control
- Store in `.env` file only
- Restrict Google Maps API key by domain
- Rotate keys periodically

## Quick Reference

### Service Commands
```bash
# Restart services
sudo systemctl restart apache2
sudo systemctl restart php8.2-fpm
sudo systemctl restart mysql

# View logs
sudo tail -f /var/log/apache2/yfevents_error.log
sudo tail -f /var/log/yfevents-deploy.log

# Check status
sudo systemctl status apache2 php8.2-fpm mysql
```

### File Locations
- **Application**: `/var/www/yfevents`
- **Config**: `/var/www/yfevents/.env`
- **Logs**: `/var/log/apache2/yfevents_*.log`
- **Apache Config**: `/etc/apache2/sites-available/yfevents.conf`
- **PHP Config**: `/etc/php/8.2/fpm/pool.d/yfevents.conf`

---

**Last Updated**: January 2025
**Script Version**: 1.0