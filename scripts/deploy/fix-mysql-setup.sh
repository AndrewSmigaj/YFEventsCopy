#!/bin/bash
# YFEvents MySQL Setup Recovery Script
# Run this if the initial setup didn't complete the MySQL configuration

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Output functions
print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1" >&2
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

echo "================================================"
echo "YFEvents MySQL Setup Recovery"
echo "================================================"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   print_error "This script must be run as root or with sudo"
   exit 1
fi

# Check if password file already exists
if [ -f "/root/.yfevents_db_pass" ]; then
    print_warning "Database password file already exists at /root/.yfevents_db_pass"
    read -p "Do you want to reconfigure MySQL? (y/N): " response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        print_info "Exiting without changes"
        exit 0
    fi
fi

# Detect MySQL access method
print_status "Detecting MySQL access method..."
MYSQL_CMD=""

if mysql -e "SELECT 1" >/dev/null 2>&1; then
    MYSQL_CMD="mysql"
    print_status "MySQL access confirmed (auth_socket)"
elif sudo mysql -e "SELECT 1" >/dev/null 2>&1; then
    MYSQL_CMD="sudo mysql"
    print_status "MySQL access confirmed (sudo)"
elif mysql -u root -ptemp_root_pass -e "SELECT 1" >/dev/null 2>&1; then
    MYSQL_CMD="mysql -u root -ptemp_root_pass"
    print_status "MySQL access confirmed (temporary password)"
else
    print_error "Cannot access MySQL. Please ensure MySQL is installed and running."
    print_info "Try running: sudo systemctl status mysql"
    exit 1
fi

# Check if database and user already exist
print_status "Checking existing database and user..."
DB_EXISTS=$($MYSQL_CMD -e "SHOW DATABASES LIKE 'yakima_finds';" | grep -c yakima_finds || echo 0)
USER_EXISTS=$($MYSQL_CMD -e "SELECT User FROM mysql.user WHERE User='yfevents' AND Host='localhost';" | grep -c yfevents || echo 0)

if [ "$DB_EXISTS" -eq 1 ]; then
    print_info "Database 'yakima_finds' already exists"
fi

if [ "$USER_EXISTS" -eq 1 ]; then
    print_info "User 'yfevents'@'localhost' already exists"
fi

# Get password for yfevents user
print_status "Setting up MySQL user password..."
read -sp "Enter password for MySQL yfevents user: " DB_PASSWORD
echo ""
read -sp "Confirm password: " DB_PASSWORD_CONFIRM
echo ""

if [ "$DB_PASSWORD" != "$DB_PASSWORD_CONFIRM" ]; then
    print_error "Passwords do not match"
    exit 1
fi

if [ -z "$DB_PASSWORD" ]; then
    print_error "Password cannot be empty"
    exit 1
fi

# Create or update database and user
print_status "Configuring MySQL database and user..."
$MYSQL_CMD <<EOF
-- Create database if not exists
CREATE DATABASE IF NOT EXISTS yakima_finds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create or update user
CREATE USER IF NOT EXISTS 'yfevents'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'yfevents'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';

-- Grant privileges
GRANT ALL PRIVILEGES ON yakima_finds.* TO 'yfevents'@'localhost';
FLUSH PRIVILEGES;

-- Show result
SELECT User, Host FROM mysql.user WHERE User='yfevents';
SHOW DATABASES LIKE 'yakima_finds';
EOF

# Save database password for deployment
print_status "Saving database password..."
echo "DB_PASSWORD=${DB_PASSWORD}" > /root/.yfevents_db_pass
chmod 600 /root/.yfevents_db_pass
print_info "Password saved to /root/.yfevents_db_pass"

# Test connection
print_status "Testing database connection..."
if mysql -u yfevents -p"${DB_PASSWORD}" yakima_finds -e "SELECT 1;" >/dev/null 2>&1; then
    print_success "✓ Database connection successful!"
else
    print_error "Database connection failed. Please check the configuration."
    exit 1
fi

echo ""
echo "================================================"
echo "MySQL Setup Complete!"
echo "================================================"
echo ""
print_status "Next steps:"
echo "1. Continue with deployment:"
echo "   cd /var/www/yfevents"
echo "   export DB_PASSWORD='${DB_PASSWORD}'"
echo "   ./scripts/deploy/deploy.sh"
echo ""
print_info "The password is also saved in /root/.yfevents_db_pass"