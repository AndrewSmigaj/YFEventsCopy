#!/bin/bash
# YFEvents Robust Deployment Script
# This version handles errors gracefully and shows what's happening

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/AndrewSmigaj/YFEventsCopy.git"
APP_DIR="/var/www/yfevents"
DB_NAME="yakima_finds"
DB_USER="yfevents"

# Generate secure password
DB_PASS=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-25)

# Progress tracking
TOTAL_STEPS=10
CURRENT_STEP=0

# Helper functions
print_step() {
    CURRENT_STEP=$((CURRENT_STEP + 1))
    echo -e "\n${BLUE}[Step $CURRENT_STEP/$TOTAL_STEPS]${NC} $1"
    echo "=================================================="
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗ ERROR:${NC} $1" >&2
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root (use sudo)"
   exit 1
fi

# Welcome message
clear
echo "======================================"
echo "  YFEvents Robust Deployment Script"
echo "  Clean Architecture Edition"
echo "======================================"
echo ""
echo "This script will:"
echo "• Install all required software"
echo "• Set up the database securely"
echo "• Deploy the Clean Architecture system"
echo "• Configure Apache web server"
echo "• Create an admin account"
echo ""
read -p "Press ENTER to begin deployment... "

# Step 1: Update system
print_step "Updating system packages"
apt update || print_warning "Update had warnings"
apt upgrade -y || print_warning "Upgrade had warnings"
print_success "System updated"

# Step 2: Install required packages
print_step "Installing required software"
echo "Installing packages one by one to identify any issues..."

# Install each package separately to identify failures
packages=(
    "apache2"
    "php8.1"
    "php8.1-mysql"
    "php8.1-curl"
    "php8.1-mbstring"
    "php8.1-json"
    "php8.1-xml"
    "php8.1-gd"
    "php8.1-fileinfo"
    "php8.1-zip"
    "mysql-server"
    "composer"
    "git"
    "openssl"
)

failed_packages=()
for package in "${packages[@]}"; do
    echo -n "Installing $package... "
    if DEBIAN_FRONTEND=noninteractive apt install -y "$package" >/dev/null 2>&1; then
        echo "OK"
    else
        echo "FAILED"
        failed_packages+=("$package")
    fi
done

if [ ${#failed_packages[@]} -gt 0 ]; then
    print_warning "Some packages failed to install: ${failed_packages[*]}"
    print_warning "Trying alternative installation method..."
    
    # Try to install failed packages with more verbose output
    for package in "${failed_packages[@]}"; do
        echo "Attempting to install $package with verbose output:"
        DEBIAN_FRONTEND=noninteractive apt install -y "$package" || true
    done
fi

print_success "Software installation completed"

# Verify critical components
echo "Verifying installations:"
command -v apache2 >/dev/null && echo "✓ Apache installed" || print_error "Apache not found"
command -v php >/dev/null && echo "✓ PHP installed" || print_error "PHP not found"
command -v mysql >/dev/null && echo "✓ MySQL installed" || print_error "MySQL not found"
command -v composer >/dev/null && echo "✓ Composer installed" || print_error "Composer not found"

# Step 3: Configure MySQL
print_step "Setting up MySQL database"
echo "Creating database and user..."

# Start MySQL if not running
systemctl start mysql || print_warning "MySQL already running"

# Create database and user with mysql_native_password
if mysql << EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
then
    print_success "Database configured"
    # Save credentials for later reference
    echo "Database Password: ${DB_PASS}" > /root/.yfevents_db_credentials
    chmod 600 /root/.yfevents_db_credentials
else
    print_error "Database setup failed"
    echo "Manual fix: Check MySQL is running and root has access"
    exit 1
fi

# Step 4: Clone repository
print_step "Downloading YFEvents code"
if [ -d "$APP_DIR" ]; then
    print_warning "Removing existing installation"
    rm -rf "$APP_DIR"
fi

if git clone -b main "$REPO_URL" "$APP_DIR"; then
    cd "$APP_DIR"
    print_success "Code downloaded"
else
    print_error "Failed to clone repository"
    exit 1
fi

# Step 5: Install PHP dependencies
print_step "Installing PHP dependencies"
if sudo -u www-data composer install --no-dev --optimize-autoloader; then
    print_success "Dependencies installed"
else
    print_warning "Composer install had issues"
fi

# Step 6: Configure environment
print_step "Configuring application"
echo "Setting up environment file..."

# Create .env with proper format (semicolon comments)
cat > .env << EOF
; YFEvents Environment Configuration
; Generated by DEPLOY_ROBUST.sh

; Application Settings
APP_NAME=YFEvents
APP_ENV=production
APP_DEBUG=false
APP_URL=http://$(hostname -I | awk '{print $1}')

; Database Configuration
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

; Logging
LOG_CHANNEL=file
LOG_LEVEL=INFO

; Google Maps (add your API key here)
GOOGLE_MAPS_API_KEY=

; Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120

; Module Settings
MODULES_ENABLED=yfauth,yfclaim,yftheme
EOF

# Set proper permissions
chown www-data:www-data .env
chmod 600 .env

print_success "Application configured"

# Step 7: Import database schemas
print_step "Setting up database tables"
echo "Importing schemas in correct order..."

# Import in dependency order
schemas=(
    "database/calendar_schema.sql:Core tables"
    "database/modules_schema.sql:Module registry"
    "modules/yfauth/database/schema.sql:Authentication tables"
    "database/shop_claim_system.sql:Shop system tables"
    "modules/yfclaim/database/schema.sql:Estate sales tables"
)

for schema_info in "${schemas[@]}"; do
    IFS=':' read -r schema_file schema_name <<< "$schema_info"
    if mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$schema_file" 2>/dev/null; then
        print_success "$schema_name created"
    else
        print_error "Failed to import $schema_name"
    fi
done

# Step 8: Set permissions
print_step "Setting file permissions"
# Create required directories
mkdir -p storage/{cache,logs,sessions,uploads}
mkdir -p public/uploads/{events,shops,claims}

# Set ownership and permissions
chown -R www-data:www-data .
chmod -R 755 .
chmod 600 .env
chmod -R 775 storage
chmod -R 775 public/uploads

print_success "Permissions configured"

# Step 9: Configure Apache
print_step "Configuring web server"
echo "Setting up Apache for Clean Architecture..."

# Create virtual host configuration
cat > /etc/apache2/sites-available/yfevents.conf << 'EOF'
<VirtualHost *:80>
    # Clean Architecture - Point to public directory
    DocumentRoot /var/www/yfevents/public
    
    <Directory /var/www/yfevents/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/yfevents-error.log
    CustomLog ${APACHE_LOG_DIR}/yfevents-access.log combined
</VirtualHost>
EOF

# Enable required modules and site
a2enmod rewrite headers expires
a2ensite yfevents.conf
a2dissite 000-default.conf || true

# Restart Apache
if systemctl restart apache2; then
    print_success "Web server configured"
else
    print_error "Apache restart failed"
fi

# Step 10: Create admin user
print_step "Creating administrator account"
echo ""
echo "Please set up your admin account:"
cd "$APP_DIR"
if [ -f "scripts/deploy/create-admin.php" ]; then
    sudo -u www-data php scripts/deploy/create-admin.php || {
        print_warning "Admin creation skipped - you can create one later"
    }
else
    print_warning "Admin creation script not found"
fi

# Final summary
echo ""
echo "======================================"
echo -e "${GREEN}  DEPLOYMENT COMPLETE!${NC}"
echo "======================================"
echo ""
echo "Your YFEvents installation is ready!"
echo ""
echo -e "${BLUE}Access URLs:${NC}"
SERVER_IP=$(hostname -I | awk '{print $1}')
echo "• Main Site: http://$SERVER_IP/"
echo "• Admin Panel: http://$SERVER_IP/admin/"
echo "• Estate Sales: http://$SERVER_IP/modules/yfclaim/www/"
echo ""
echo -e "${BLUE}Database Credentials:${NC}"
echo "• Database: $DB_NAME"
echo "• Username: $DB_USER"
echo "• Password saved in: /root/.yfevents_db_credentials"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "1. Test the site in your browser"
echo "2. Add your Google Maps API key to .env"
echo "3. Configure event sources in the admin panel"
echo ""

# Test site accessibility
echo "Testing site accessibility..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost/" | grep -q "200\|301\|302"; then
    print_success "Site is responding!"
else
    print_warning "Site may need troubleshooting - check Apache logs"
fi

echo ""
echo -e "${GREEN}Script completed!${NC}"