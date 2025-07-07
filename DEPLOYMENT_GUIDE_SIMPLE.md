# YFEvents Deployment Guide

## Overview
YFEvents uses a configuration-driven deployment system that supports multiple environments (production, staging, development). The deployment process is automated and includes validation, backup, and rollback capabilities.

## Prerequisites

### Server Requirements
- **OS**: Ubuntu 22.04 LTS (recommended)
- **RAM**: 2GB minimum
- **Storage**: 20GB minimum
- **Access**: Root or sudo access

### Software Requirements
- PHP 8.1+ with extensions (mysql, curl, mbstring, json, xml, gd, fileinfo)
- MySQL 8.0+ or MariaDB 10.5+
- Apache 2.4+ with modules (rewrite, headers, expires, ssl)
- Composer 2.0+
- Git
- Certbot (for SSL certificates)

## Quick Start Guide

### Step 1: Initial Server Setup

For a fresh server, run the setup script:

```bash
# Download and run server setup
wget https://raw.githubusercontent.com/YOUR_ORG/YFEvents/main/scripts/deploy/setup-server.sh
chmod +x setup-server.sh
sudo ./setup-server.sh
```

When prompted, provide:
- Domain name (e.g., example.com)
- Email for SSL certificate
- MySQL root password

### Step 2: Clone the Repository

```bash
# Clone your repository
git clone YOUR_REPOSITORY_URL /var/www/yfevents
cd /var/www/yfevents

# For a specific branch
git checkout YOUR_BRANCH_NAME
```

### Step 3: Configure Deployment

The deployment system uses YAML configuration files located in `config/deployment/`:

1. **Review main configuration**: `config/deployment/deployment.yaml`
2. **Check environment settings**: `config/deployment/environments/production.yaml`
3. **Update repository settings** in deployment.yaml:
   ```yaml
   repository:
     url: "git@github.com:YOUR_ORG/YFEvents.git"
     branch: "main"  # or your preferred branch
   ```

### Step 4: Run Deployment

```bash
# Basic deployment
sudo ./scripts/deploy/deploy-new.sh

# With specific environment
sudo ./scripts/deploy/deploy-new.sh --environment production

# Skip validation checks (not recommended)
sudo ./scripts/deploy/deploy-new.sh --skip-validation

# Skip backup (for fresh installs)
sudo ./scripts/deploy/deploy-new.sh --skip-backup
```

### Step 5: Create Admin User

The deployment script will prompt you to create an admin user. If you need to create additional admins later:

```bash
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/create-admin.php
```

## Configuration System

### Directory Structure
```
config/deployment/
├── deployment.yaml          # Main configuration
├── environments/           
│   ├── production.yaml     # Production overrides
│   ├── staging.yaml        # Staging overrides
│   └── development.yaml    # Development overrides
└── versions/
    ├── 2.3.0.yaml         # Version-specific settings
    └── latest.yaml        # Symlink to current version
```

### Environment Variables

You can override configuration values using environment variables:

```bash
# Set repository URL
export YFEVENTS_REPO_URL="git@github.com:YOUR_ORG/YFEvents.git"

# Set branch
export YFEVENTS_BRANCH="develop"

# Set database password
export DB_PASSWORD="your_secure_password"

# Run deployment
sudo -E ./scripts/deploy/deploy-new.sh
```

### Key Configuration Options

#### Repository Settings
- `deployment.repository.url`: Git repository URL
- `deployment.repository.branch`: Branch to deploy

#### Database Settings
- `deployment.database.name`: Database name (default: yakima_finds)
- `deployment.database.user`: Database user (default: yfevents)
- `deployment.database.schemas.communication.type`: Chat system type (full/subset/none)

#### Module Settings
- `deployment.modules.active`: List of active modules
- `deployment.modules.inactive`: Disabled modules

## Deployment Process

The deployment script performs these steps:

1. **Load Configuration**: Reads YAML configuration files
2. **Pre-deployment Validation**: Checks system requirements
3. **Backup**: Backs up database and files (if updating)
4. **Deploy Code**: Clones/updates repository
5. **Install Dependencies**: Runs composer install
6. **Run Installer**: Executes YFEvents installer
7. **Configure Environment**: Updates .env file
8. **Set Permissions**: Creates directories and sets ownership
9. **Database Setup**: Runs migrations in correct order
10. **Configure Apache**: Sets up virtual host and SSL
11. **Setup Cron**: Configures event scraping
12. **Create Admin**: Prompts for admin user creation
13. **Post-deployment Validation**: Verifies installation
14. **Cleanup**: Removes temporary files

## Database Schema Order

Schemas are executed in this order:
1. Core tables (calendar, shops, modules)
2. Communication system (choose one):
   - `communication_schema_fixed.sql` (full system - recommended)
   - `yfchat_subset.sql` (simplified admin-seller chat)
3. Module schemas (YFAuth, YFClaim, YFTheme)
4. Optional improvements (performance, security, audit)

## Troubleshooting

### Check Deployment Health
```bash
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/health-check.php
```

### View Logs
```bash
# Apache error log
tail -f /var/log/apache2/error.log

# Application logs
tail -f /var/www/yfevents/storage/logs/app.log

# Deployment validation
./scripts/deploy/deploy-new.sh --environment production 2>&1 | tee deploy.log
```

### Common Issues

#### Permission Errors
```bash
cd /var/www/yfevents
sudo chown -R www-data:www-data .
sudo chmod -R 755 storage public/uploads
sudo chmod 600 .env
```

#### Database Connection Failed
1. Check credentials in `.env`
2. Verify MySQL is running: `sudo systemctl status mysql`
3. Test connection: `mysql -u yfevents -p yakima_finds`

#### Route Not Found (404)
1. Check Apache mod_rewrite: `sudo a2enmod rewrite`
2. Verify `.htaccess` in public directory
3. Restart Apache: `sudo systemctl restart apache2`

### Rollback Deployment

If deployment fails:
```bash
# Restore database from backup
cd /var/www/yfevents
sudo -u www-data php scripts/deploy/rollback.sh

# Or manually restore
mysql -u yfevents -p yakima_finds < /var/backups/yfevents/latest.sql.gz
```

## Security Considerations

1. **Environment File**: The `.env` file contains sensitive data
   - Permissions should be 600 (read/write by owner only)
   - Never commit to version control

2. **Database Credentials**: Use strong passwords
   - Different passwords for different environments
   - Rotate credentials regularly

3. **SSL Certificates**: Always use HTTPS in production
   - Auto-renewed via Certbot
   - Check expiry: `sudo certbot certificates`

4. **File Permissions**: Regularly audit permissions
   ```bash
   find /var/www/yfevents -type f -perm 0777 -ls
   find /var/www/yfevents -type d -perm 0777 -ls
   ```

## Maintenance

### Update Deployment
```bash
cd /var/www/yfevents
git pull origin main
sudo ./scripts/deploy/deploy-new.sh
```

### Backup Database
```bash
cd /var/www/yfevents/scripts/deploy
sudo -u www-data ./lib/database.sh backup_database
```

### Clear Caches
```bash
cd /var/www/yfevents
sudo -u www-data rm -rf storage/cache/*
sudo -u www-data php artisan cache:clear 2>/dev/null || true
```

## Support

- **Documentation**: Check `architecture.yaml` for detailed system information
- **Configuration**: Review files in `config/deployment/`
- **Validation**: Run health checks and validation scripts
- **Logs**: Check application and system logs for errors

Remember to always test deployments in a staging environment before deploying to production!