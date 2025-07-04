#!/bin/bash

# YFEvents Server Setup Script for Digital Ocean
# This script sets up a fresh Ubuntu 22.04 droplet with all required dependencies
# Run as root or with sudo

set -e  # Exit on any error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root or with sudo"
   exit 1
fi

echo "================================================"
echo "YFEvents Server Setup for Digital Ocean"
echo "================================================"
echo ""

# Get server information
print_status "Gathering server information..."
read -p "Enter your domain name (e.g., example.com): " DOMAIN_NAME
read -p "Enter your email for SSL certificate: " SSL_EMAIL

# Update system
print_status "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
print_status "Installing Apache, PHP 8.1, and MySQL..."
apt install -y \
    apache2 \
    php8.1 \
    php8.1-fpm \
    php8.1-mysql \
    php8.1-curl \
    php8.1-mbstring \
    php8.1-json \
    php8.1-xml \
    php8.1-gd \
    libapache2-mod-php8.1 \
    mysql-server \
    composer \
    git \
    certbot \
    python3-certbot-apache \
    unzip \
    curl

# Enable Apache modules
print_status "Enabling Apache modules..."
a2enmod rewrite
a2enmod headers
a2enmod expires
a2enmod ssl

# Configure PHP
print_status "Configuring PHP..."
# Increase PHP limits for production
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 50M/g' /etc/php/8.1/apache2/php.ini
sed -i 's/post_max_size = 8M/post_max_size = 50M/g' /etc/php/8.1/apache2/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/g' /etc/php/8.1/apache2/php.ini
sed -i 's/max_execution_time = 30/max_execution_time = 300/g' /etc/php/8.1/apache2/php.ini

# Secure MySQL installation
print_status "Securing MySQL installation..."
mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'temp_root_pass';"

# Create database and user
print_status "Creating YFEvents database and user..."
read -sp "Enter password for MySQL yfevents user: " DB_PASSWORD
echo ""

mysql -u root -ptemp_root_pass <<EOF
CREATE DATABASE IF NOT EXISTS yakima_finds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'yfevents'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON yakima_finds.* TO 'yfevents'@'localhost';
FLUSH PRIVILEGES;
EOF

# Save database password for later use
echo "DB_PASSWORD=${DB_PASSWORD}" > /root/.yfevents_db_pass
chmod 600 /root/.yfevents_db_pass

# Configure firewall
print_status "Configuring firewall..."
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Create application directory
print_status "Creating application directory..."
mkdir -p /var/www/yfevents
chown -R www-data:www-data /var/www/yfevents

# Create swap file (helpful for smaller droplets)
if [ ! -f /swapfile ]; then
    print_status "Creating 2GB swap file..."
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# Install fail2ban for security
print_status "Installing fail2ban..."
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Create a basic Apache config (will be replaced by deploy script)
print_status "Creating temporary Apache configuration..."
cat > /etc/apache2/sites-available/yfevents.conf <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN_NAME}
    DocumentRoot /var/www/yfevents/public
    
    <Directory /var/www/yfevents/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/yfevents_error.log
    CustomLog \${APACHE_LOG_DIR}/yfevents_access.log combined
</VirtualHost>
EOF

# Enable the site
a2ensite yfevents.conf
a2dissite 000-default.conf

# Restart Apache
systemctl restart apache2

# Create deployment user (optional but recommended)
print_status "Creating deployment user..."
if ! id -u yfevents >/dev/null 2>&1; then
    useradd -m -s /bin/bash yfevents
    usermod -aG www-data yfevents
    print_warning "Remember to set up SSH keys for the yfevents user"
fi

# Final instructions
echo ""
echo "================================================"
echo "Server setup complete!"
echo "================================================"
echo ""
print_status "Next steps:"
echo "1. Run the deploy.sh script to install YFEvents"
echo "2. The database password is saved in /root/.yfevents_db_pass"
echo "3. SSL certificate will be configured during deployment"
echo ""
print_warning "MySQL root password is temporarily set to 'temp_root_pass'"
print_warning "Please change it with: mysql_secure_installation"
echo ""
print_status "Server is ready for YFEvents deployment!"