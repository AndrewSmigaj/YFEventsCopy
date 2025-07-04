#!/bin/bash

# YFEvents Deployment Script
# This script deploys YFEvents to a prepared Digital Ocean droplet
# Prerequisites: Run setup-server.sh first

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

# Configuration
APP_DIR="/var/www/yfevents"
REPO_URL=""
DOMAIN_NAME=""
SSL_EMAIL=""
GITHUB_BRANCH="main"

echo "================================================"
echo "YFEvents Deployment Script"
echo "================================================"
echo ""

# Check if running as appropriate user
if [[ $EUID -eq 0 ]]; then
   print_warning "Running as root. Some commands will use sudo -u www-data."
fi

# Get deployment configuration
if [ -z "$1" ]; then
    read -p "Enter your Git repository URL (or 'local' for local files): " REPO_URL
else
    REPO_URL="$1"
fi

read -p "Enter your domain name (e.g., example.com): " DOMAIN_NAME
read -p "Enter your email for SSL certificate: " SSL_EMAIL

# Load database password if saved from setup script
if [ -f /root/.yfevents_db_pass ]; then
    source /root/.yfevents_db_pass
    print_status "Loaded database password from setup"
else
    read -sp "Enter MySQL password for yfevents user: " DB_PASSWORD
    echo ""
fi

# Step 1: Deploy application code
print_status "Deploying application code..."
if [ "$REPO_URL" = "local" ]; then
    # For local deployment (useful for testing)
    print_info "Using local files for deployment"
    if [ -d "$APP_DIR" ]; then
        print_warning "Removing existing application directory..."
        rm -rf "$APP_DIR"
    fi
    cp -r "$(dirname "$(dirname "$(dirname "$0")")")" "$APP_DIR"
else
    # Clone from Git repository
    if [ -d "$APP_DIR/.git" ]; then
        print_info "Updating existing repository..."
        cd "$APP_DIR"
        sudo -u www-data git fetch origin
        sudo -u www-data git reset --hard origin/$GITHUB_BRANCH
    else
        print_info "Cloning repository..."
        if [ -d "$APP_DIR" ]; then
            rm -rf "$APP_DIR"
        fi
        git clone -b $GITHUB_BRANCH "$REPO_URL" "$APP_DIR"
    fi
fi

cd "$APP_DIR"

# Step 2: Install dependencies
print_status "Installing Composer dependencies..."
sudo -u www-data composer install --no-dev --optimize-autoloader

# Step 3: Run installer.php for environment setup
print_status "Running YFEvents installer..."
# The installer will prompt for database details and create .env
sudo -u www-data php install/installer.php core admin api scraping geocoding shops modules yfauth yfclaim production

# Update .env with production settings
if [ -f .env ]; then
    # Update database password if we have it
    if [ ! -z "$DB_PASSWORD" ]; then
        sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
    fi
    
    # Set production URL
    sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN_NAME|" .env
    
    # Ensure production mode
    sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
    sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
    
    print_status "Environment configuration updated"
fi

# Step 4: Create required directories
print_status "Creating required directories..."
directories=(
    "cache"
    "logs"
    "storage"
    "storage/cache"
    "storage/logs"
    "storage/sessions"
    "public/uploads"
)

for dir in "${directories[@]}"; do
    mkdir -p "$APP_DIR/$dir"
    chown www-data:www-data "$APP_DIR/$dir"
    chmod 755 "$APP_DIR/$dir"
done

# Step 5: Set file permissions
print_status "Setting file permissions..."
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod +x "$APP_DIR/cron/scrape-events.php"
chmod +x "$APP_DIR/scripts/deploy/"*.sh
chmod +x "$APP_DIR/scripts/deploy/"*.php

# Secure sensitive files
chmod 600 "$APP_DIR/.env"
chown www-data:www-data "$APP_DIR/.env"

# Step 6: Run database schemas
print_status "Setting up database schemas..."
cd "$APP_DIR"
sudo -u www-data php scripts/deploy/run-sql-files.php

# Step 7: Configure Apache
print_status "Configuring Apache..."
# Copy virtual host configuration
cp scripts/deploy/apache-vhost.conf /etc/apache2/sites-available/yfevents.conf

# Replace domain name in config
sed -i "s/DOMAIN_NAME/$DOMAIN_NAME/g" /etc/apache2/sites-available/yfevents.conf

# Enable the site
a2ensite yfevents.conf
a2dissite 000-default.conf || true

# Reload Apache
systemctl reload apache2

# Step 8: Set up SSL certificate
print_status "Setting up SSL certificate with Let's Encrypt..."
certbot --apache -d "$DOMAIN_NAME" -d "www.$DOMAIN_NAME" --non-interactive --agree-tos --email "$SSL_EMAIL" --redirect

# Step 9: Set up cron job
print_status "Setting up cron job for event scraping..."
CRON_CMD="0 */6 * * * cd $APP_DIR && /usr/bin/php cron/scrape-events.php >> storage/logs/cron.log 2>&1"

# Add cron job for www-data user
(sudo -u www-data crontab -l 2>/dev/null | grep -v "scrape-events.php"; echo "$CRON_CMD") | sudo -u www-data crontab -

# Step 10: Create first admin user
print_status "Creating admin user..."
cd "$APP_DIR"
sudo -u www-data php scripts/deploy/create-admin.php

# Step 11: Run health check
print_status "Running health check..."
sudo -u www-data php scripts/deploy/health-check.php

# Final summary
echo ""
echo "================================================"
echo "Deployment Complete!"
echo "================================================"
echo ""
print_status "Your YFEvents installation is ready!"
echo ""
echo "Access URLs:"
echo "- Main site: ${BLUE}https://$DOMAIN_NAME${NC}"
echo "- Admin panel: ${BLUE}https://$DOMAIN_NAME/admin${NC}"
echo "- API endpoints: ${BLUE}https://$DOMAIN_NAME/api/${NC}"
echo ""
echo "Next steps:"
echo "1. Test the site in your browser"
echo "2. Configure Google Maps API key in .env if not already done"
echo "3. Review and adjust email settings in .env"
echo "4. Set up monitoring (optional)"
echo ""
print_info "Log files location: $APP_DIR/storage/logs/"
print_info "To create more admin users: cd $APP_DIR && sudo -u www-data php scripts/deploy/create-admin.php"
echo ""