# YFEvents Deployment Guide for Digital Ocean

This guide walks you through deploying YFEvents on a Digital Ocean droplet from scratch.

## Prerequisites

- A Digital Ocean account
- A domain name pointed to your droplet's IP address
- Basic SSH knowledge

## Quick Start

1. **Create a Droplet**
   - Ubuntu 22.04 LTS
   - Minimum 2GB RAM (4GB recommended)
   - Choose a datacenter near your users

2. **SSH into your droplet and run:**
   ```bash
   # Download and run the setup script
   wget https://raw.githubusercontent.com/yourusername/yfevents/main/scripts/deploy/setup-server.sh
   chmod +x setup-server.sh
   sudo ./setup-server.sh
   
   # Then run the deployment script
   wget https://raw.githubusercontent.com/yourusername/yfevents/main/scripts/deploy/deploy.sh
   chmod +x deploy.sh
   sudo ./deploy.sh
   ```

## Detailed Steps

### Step 1: Server Setup

The `setup-server.sh` script will:
- Install Apache, PHP 8.1, MySQL, and all dependencies
- Create the database and user
- Configure the firewall
- Set up basic security (fail2ban)
- Create a swap file for better performance

**What you'll need to provide:**
- Your domain name
- Email for SSL certificates
- MySQL password for the yfevents user

### Step 2: Application Deployment

The `deploy.sh` script will:
- Clone/copy your application code
- Install Composer dependencies
- Run the YFEvents installer
- Set up the database schemas
- Configure Apache with SSL
- Create your first admin user
- Set up cron jobs

**What you'll need to provide:**
- Git repository URL (or use 'local' for testing)
- Domain name (again)
- Email for SSL (again)
- Admin user details

### Step 3: Post-Deployment

After deployment:
1. Visit `https://yourdomain.com` to see the main site
2. Visit `https://yourdomain.com/admin` to access the admin panel
3. Configure your Google Maps API key in `.env`
4. Set up email settings if needed

## Script Descriptions

### setup-server.sh
Prepares a fresh Ubuntu droplet with all system dependencies. Run this once per server.

### deploy.sh
Deploys the YFEvents application. Can be run multiple times for updates.

### run-sql-files.php
Executes database schemas in the correct order. Called automatically by deploy.sh.

### create-admin.php
Interactive script to create admin users. Can be run anytime to add more admins.

### health-check.php
Verifies the deployment was successful. Run this to diagnose issues.

### apache-vhost.conf
Template for Apache virtual host configuration. Automatically customized during deployment.

## Common Tasks

### Creating Additional Admin Users
```bash
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/create-admin.php
```

### Updating the Application
```bash
cd /var/www/yfevents
sudo -u www-data git pull
sudo -u www-data composer install --no-dev
sudo -u www-data php scripts/deploy/run-sql-files.php
```

### Checking Application Health
```bash
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/health-check.php
```

### Viewing Logs
```bash
# Apache logs
tail -f /var/log/apache2/yfevents_error.log
tail -f /var/log/apache2/yfevents_access.log

# Application logs
tail -f /var/www/yfevents/storage/logs/yfevents.log

# Cron logs
tail -f /var/www/yfevents/storage/logs/cron.log
```

## Troubleshooting

### Database Connection Errors
- Check `.env` file has correct credentials
- Verify MySQL is running: `sudo systemctl status mysql`
- Test connection: `mysql -u yfevents -p yakima_finds`

### Permission Errors
```bash
cd /var/www/yfevents
sudo chown -R www-data:www-data .
sudo chmod -R 755 cache logs storage public/uploads
```

### Apache Not Working
```bash
# Check configuration
sudo apache2ctl configtest

# Restart Apache
sudo systemctl restart apache2

# Check error log
sudo tail -f /var/log/apache2/error.log
```

### SSL Certificate Issues
```bash
# Renew certificate
sudo certbot renew

# Force renewal
sudo certbot renew --force-renewal
```

## Security Recommendations

1. **Change MySQL root password**
   ```bash
   sudo mysql_secure_installation
   ```

2. **Set up SSH key authentication**
   - Disable password authentication in `/etc/ssh/sshd_config`

3. **Configure application firewall rules**
   - The setup script configures basic rules
   - Add more restrictive rules as needed

4. **Enable automatic security updates**
   ```bash
   sudo apt install unattended-upgrades
   sudo dpkg-reconfigure -plow unattended-upgrades
   ```

5. **Regular backups**
   - Set up automated database backups
   - Back up the uploads directory

## Performance Optimization

1. **Enable OPcache** (already configured)
2. **Configure Redis** for caching (optional)
3. **Use a CDN** for static assets
4. **Monitor with tools** like New Relic or Datadog

## Support

- Check the main YFEvents documentation
- Review application logs for errors
- Ensure all health checks pass

Remember to keep your system updated:
```bash
sudo apt update && sudo apt upgrade
```