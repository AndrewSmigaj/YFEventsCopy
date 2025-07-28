#!/bin/bash

################################################################################
# YFEvents Deployment Script for Digital Ocean Ubuntu 22.04
################################################################################
# This script automates the deployment of YFEvents to a fresh Ubuntu droplet
# Version: 1.0
# Requirements: Ubuntu 22.04 LTS, root or sudo access, internet connection
################################################################################

set -euo pipefail

# Global Variables
readonly SCRIPT_VERSION="1.0"
readonly SCRIPT_NAME="YFEvents Deploy"
readonly LOG_FILE="/var/log/yfevents-deploy.log"
readonly STATE_FILE="/tmp/yfevents-deploy-state.json"
readonly BACKUP_DIR="/tmp/yfevents-deploy-backup"
readonly REQUIRED_UBUNTU_VERSION="22.04"
readonly PHP_VERSION="8.2"
readonly MYSQL_VERSION="8.0"

# Color codes for output
readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[1;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m' # No Color

# Deployment configuration (will be set via prompts)
DOMAIN_NAME=""
DB_ROOT_PASSWORD=""
DB_NAME="yakima_finds"
DB_USER="yfevents"
DB_PASSWORD=""
ADMIN_EMAIL=""
GOOGLE_MAPS_API_KEY=""
DEPLOY_USER="yfevents"
DEPLOY_DIR="/var/www/yfevents"
REPO_URL="git@github.com:AndrewSmigaj/YFEventsCopy.git"
REPO_BRANCH="main"

################################################################################
# Utility Functions
################################################################################

print_header() {
    echo -e "\n${BLUE}=== $1 ===${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] HEADER: $1" >> "$LOG_FILE"
}

print_success() {
    echo -e "${GREEN}[✓] $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1" >> "$LOG_FILE"
}

print_error() {
    echo -e "${RED}[✗] $1${NC}" >&2
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$LOG_FILE"
}

print_warning() {
    echo -e "${YELLOW}[!] $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1" >> "$LOG_FILE"
}

print_info() {
    echo -e "[i] $1"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] INFO: $1" >> "$LOG_FILE"
}

confirm_action() {
    local prompt="$1"
    local response
    echo -en "${YELLOW}$prompt (y/N): ${NC}"
    read -r response
    [[ "$response" =~ ^[Yy]$ ]]
}

check_command() {
    local cmd="$1"
    if command -v "$cmd" &> /dev/null; then
        return 0
    else
        return 1
    fi
}

generate_password() {
    openssl rand -base64 32 | tr -d "=+/" | cut -c1-25
}

save_state() {
    local step="$1"
    local status="$2"
    echo "{\"step\": \"$step\", \"status\": \"$status\", \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" > "$STATE_FILE"
}

cleanup_on_exit() {
    if [[ -f "$STATE_FILE" ]]; then
        local last_step=$(grep -o '"step": "[^"]*"' "$STATE_FILE" | cut -d'"' -f4)
        print_warning "Deployment interrupted at step: $last_step"
        print_info "State saved to $STATE_FILE for potential recovery"
        print_info "Logs available at: $LOG_FILE"
    fi
}

trap cleanup_on_exit EXIT

################################################################################
# Validation Functions
################################################################################

validate_system() {
    print_header "System Validation"
    
    check_ubuntu_version
    check_root_access
    check_internet
    check_ports
    
    print_success "System validation complete"
}

check_ubuntu_version() {
    print_info "Checking Ubuntu version..."
    
    if [[ ! -f /etc/lsb-release ]]; then
        print_error "This script requires Ubuntu Linux"
        return 2
    fi
    
    . /etc/lsb-release
    
    if [[ "$DISTRIB_ID" != "Ubuntu" ]]; then
        print_error "This script requires Ubuntu (found: $DISTRIB_ID)"
        return 2
    fi
    
    if [[ "$DISTRIB_RELEASE" != "$REQUIRED_UBUNTU_VERSION" ]]; then
        print_error "This script requires Ubuntu $REQUIRED_UBUNTU_VERSION (found: $DISTRIB_RELEASE)"
        return 2
    fi
    
    print_success "Ubuntu $DISTRIB_RELEASE detected"
}

check_root_access() {
    print_info "Checking root access..."
    
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        print_info "Try: sudo $0"
        return 2
    fi
    
    print_success "Root access confirmed"
}

check_internet() {
    print_info "Checking internet connectivity..."
    
    if ! ping -c 1 -W 5 google.com &> /dev/null; then
        print_error "No internet connection detected"
        print_info "Please ensure the server has internet access"
        return 2
    fi
    
    print_success "Internet connection confirmed"
}

check_ports() {
    print_info "Checking required ports..."
    
    local ports=(80 443 3306)
    local port_issues=0
    
    for port in "${ports[@]}"; do
        if ss -tuln | grep -q ":$port "; then
            print_warning "Port $port is already in use"
            port_issues=$((port_issues + 1))
        fi
    done
    
    if [[ $port_issues -gt 0 ]]; then
        print_warning "$port_issues ports are already in use"
        if ! confirm_action "Continue anyway?"; then
            return 2
        fi
    else
        print_success "All required ports are available"
    fi
}

################################################################################
# Installation Functions
################################################################################

update_system() {
    print_header "Updating System Packages"
    save_state "update_system" "started"
    
    print_info "Updating package lists..."
    if ! apt-get update -y >> "$LOG_FILE" 2>&1; then
        print_error "Failed to update package lists"
        return 3
    fi
    
    print_info "Upgrading packages..."
    if ! DEBIAN_FRONTEND=noninteractive apt-get upgrade -y >> "$LOG_FILE" 2>&1; then
        print_error "Failed to upgrade packages"
        return 3
    fi
    
    print_info "Installing basic utilities..."
    if ! apt-get install -y curl wget git unzip software-properties-common >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install basic utilities"
        return 3
    fi
    
    save_state "update_system" "completed"
    print_success "System updated successfully"
}

install_apache() {
    print_header "Installing Apache Web Server"
    save_state "install_apache" "started"
    
    print_info "Installing Apache2..."
    if ! apt-get install -y apache2 >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install Apache2"
        return 3
    fi
    
    print_info "Installing Apache modules..."
    local modules=(rewrite headers deflate expires ssl)
    for module in "${modules[@]}"; do
        if ! a2enmod "$module" >> "$LOG_FILE" 2>&1; then
            print_error "Failed to enable module: $module"
            return 3
        fi
    done
    
    print_info "Starting Apache service..."
    if ! systemctl start apache2 >> "$LOG_FILE" 2>&1; then
        print_error "Failed to start Apache2"
        return 3
    fi
    
    if ! systemctl enable apache2 >> "$LOG_FILE" 2>&1; then
        print_error "Failed to enable Apache2 on boot"
        return 3
    fi
    
    save_state "install_apache" "completed"
    print_success "Apache installed and configured"
}

install_php() {
    print_header "Installing PHP $PHP_VERSION"
    save_state "install_php" "started"
    
    print_info "Adding PHP repository..."
    if ! add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1; then
        print_error "Failed to add PHP repository"
        return 3
    fi
    
    if ! apt-get update -y >> "$LOG_FILE" 2>&1; then
        print_error "Failed to update package lists after adding PHP repo"
        return 3
    fi
    
    print_info "Installing PHP and required extensions..."
    local php_packages=(
        "php${PHP_VERSION}"
        "php${PHP_VERSION}-fpm"
        "php${PHP_VERSION}-mysql"
        "php${PHP_VERSION}-curl"
        "php${PHP_VERSION}-mbstring"
        "php${PHP_VERSION}-xml"
        "php${PHP_VERSION}-zip"
        "php${PHP_VERSION}-gd"
        "php${PHP_VERSION}-intl"
        "libapache2-mod-php${PHP_VERSION}"
    )
    
    if ! apt-get install -y "${php_packages[@]}" >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install PHP packages"
        return 3
    fi
    
    print_info "Configuring PHP..."
    local php_ini="/etc/php/${PHP_VERSION}/fpm/php.ini"
    if [[ -f "$php_ini" ]]; then
        sed -i 's/;date.timezone =/date.timezone = America\/Los_Angeles/' "$php_ini"
        sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' "$php_ini"
        sed -i 's/post_max_size = 8M/post_max_size = 10M/' "$php_ini"
        sed -i 's/max_execution_time = 30/max_execution_time = 300/' "$php_ini"
    fi
    
    print_info "Starting PHP-FPM service..."
    if ! systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1; then
        print_error "Failed to start PHP-FPM"
        return 3
    fi
    
    save_state "install_php" "completed"
    print_success "PHP $PHP_VERSION installed and configured"
}

install_mysql() {
    print_header "Installing MySQL $MYSQL_VERSION"
    save_state "install_mysql" "started"
    
    print_info "Installing MySQL Server..."
    export DEBIAN_FRONTEND=noninteractive
    
    # Pre-configure MySQL to avoid prompts
    debconf-set-selections <<< "mysql-server mysql-server/root_password password $DB_ROOT_PASSWORD"
    debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $DB_ROOT_PASSWORD"
    
    if ! apt-get install -y mysql-server >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install MySQL Server"
        return 3
    fi
    
    print_info "Starting MySQL service..."
    if ! systemctl start mysql >> "$LOG_FILE" 2>&1; then
        print_error "Failed to start MySQL"
        return 3
    fi
    
    if ! systemctl enable mysql >> "$LOG_FILE" 2>&1; then
        print_error "Failed to enable MySQL on boot"
        return 3
    fi
    
    print_info "Securing MySQL installation..."
    # Run mysql_secure_installation programmatically
    mysql --user=root --password="$DB_ROOT_PASSWORD" <<-EOSQL
        DELETE FROM mysql.user WHERE User='';
        DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
        DROP DATABASE IF EXISTS test;
        DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
        FLUSH PRIVILEGES;
EOSQL
    
    save_state "install_mysql" "completed"
    print_success "MySQL installed and secured"
}

install_git() {
    print_header "Installing Git"
    save_state "install_git" "started"
    
    if check_command git; then
        print_success "Git is already installed"
        save_state "install_git" "completed"
        return 0
    fi
    
    print_info "Installing Git..."
    if ! apt-get install -y git >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install Git"
        return 3
    fi
    
    save_state "install_git" "completed"
    print_success "Git installed successfully"
}

install_composer() {
    print_header "Installing Composer"
    save_state "install_composer" "started"
    
    print_info "Downloading Composer installer..."
    local expected_signature=$(wget -q -O - https://composer.github.io/installer.sig)
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    local actual_signature=$(php -r "echo hash_file('SHA384', 'composer-setup.php');")
    
    if [[ "$expected_signature" != "$actual_signature" ]]; then
        print_error "Composer installer signature verification failed"
        rm composer-setup.php
        return 3
    fi
    
    print_info "Installing Composer..."
    if ! php composer-setup.php --install-dir=/usr/local/bin --filename=composer >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install Composer"
        rm composer-setup.php
        return 3
    fi
    
    rm composer-setup.php
    
    save_state "install_composer" "completed"
    print_success "Composer installed successfully"
}

################################################################################
# Configuration Functions
################################################################################

create_deployment_user() {
    print_header "Creating Deployment User"
    save_state "create_deployment_user" "started"
    
    print_info "Creating user: $DEPLOY_USER..."
    if id "$DEPLOY_USER" &>/dev/null; then
        print_warning "User $DEPLOY_USER already exists"
    else
        if ! useradd -m -s /bin/bash "$DEPLOY_USER" >> "$LOG_FILE" 2>&1; then
            print_error "Failed to create user $DEPLOY_USER"
            return 4
        fi
    fi
    
    print_info "Adding user to www-data group..."
    if ! usermod -a -G www-data "$DEPLOY_USER" >> "$LOG_FILE" 2>&1; then
        print_error "Failed to add user to www-data group"
        return 4
    fi
    
    save_state "create_deployment_user" "completed"
    print_success "Deployment user created"
}

configure_firewall() {
    print_header "Configuring Firewall"
    save_state "configure_firewall" "started"
    
    print_info "Installing UFW..."
    if ! apt-get install -y ufw >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install UFW"
        return 4
    fi
    
    print_info "Configuring firewall rules..."
    ufw --force reset >> "$LOG_FILE" 2>&1
    ufw default deny incoming >> "$LOG_FILE" 2>&1
    ufw default allow outgoing >> "$LOG_FILE" 2>&1
    ufw allow ssh >> "$LOG_FILE" 2>&1
    ufw allow 80/tcp >> "$LOG_FILE" 2>&1
    ufw allow 443/tcp >> "$LOG_FILE" 2>&1
    
    print_info "Enabling firewall..."
    if ! ufw --force enable >> "$LOG_FILE" 2>&1; then
        print_error "Failed to enable firewall"
        return 4
    fi
    
    save_state "configure_firewall" "completed"
    print_success "Firewall configured"
}

configure_apache_vhost() {
    print_header "Configuring Apache Virtual Host"
    save_state "configure_apache_vhost" "started"
    
    print_info "Creating virtual host configuration..."
    local vhost_file="/etc/apache2/sites-available/yfevents.conf"
    
    cat > "$vhost_file" <<EOF
<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    DocumentRoot $DEPLOY_DIR/www/html
    
    <Directory $DEPLOY_DIR/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/yfevents_error.log
    CustomLog \${APACHE_LOG_DIR}/yfevents_access.log combined
    
    # PHP-FPM Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php${PHP_VERSION}-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
EOF
    
    print_info "Enabling virtual host..."
    if ! a2ensite yfevents.conf >> "$LOG_FILE" 2>&1; then
        print_error "Failed to enable virtual host"
        return 4
    fi
    
    print_info "Disabling default site..."
    if ! a2dissite 000-default.conf >> "$LOG_FILE" 2>&1; then
        print_warning "Failed to disable default site"
    fi
    
    print_info "Enabling proxy modules for PHP-FPM..."
    if ! a2enmod proxy_fcgi setenvif >> "$LOG_FILE" 2>&1; then
        print_error "Failed to enable proxy modules"
        return 4
    fi
    
    print_info "Restarting Apache..."
    if ! systemctl restart apache2 >> "$LOG_FILE" 2>&1; then
        print_error "Failed to restart Apache"
        return 4
    fi
    
    save_state "configure_apache_vhost" "completed"
    print_success "Apache virtual host configured"
}

configure_php() {
    print_header "Configuring PHP-FPM"
    save_state "configure_php" "started"
    
    print_info "Configuring PHP-FPM pool..."
    local pool_file="/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"
    
    if [[ -f "$pool_file" ]]; then
        sed -i "s/^user = .*/user = $DEPLOY_USER/" "$pool_file"
        sed -i "s/^group = .*/group = www-data/" "$pool_file"
        sed -i "s/^listen.owner = .*/listen.owner = www-data/" "$pool_file"
        sed -i "s/^listen.group = .*/listen.group = www-data/" "$pool_file"
    fi
    
    print_info "Restarting PHP-FPM..."
    if ! systemctl restart "php${PHP_VERSION}-fpm" >> "$LOG_FILE" 2>&1; then
        print_error "Failed to restart PHP-FPM"
        return 4
    fi
    
    save_state "configure_php" "completed"
    print_success "PHP-FPM configured"
}

setup_mysql_database() {
    print_header "Setting Up MySQL Database"
    save_state "setup_mysql_database" "started"
    
    print_info "Creating database and user..."
    mysql --user=root --password="$DB_ROOT_PASSWORD" <<-EOSQL
        CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
        GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
        FLUSH PRIVILEGES;
EOSQL
    
    if [[ $? -ne 0 ]]; then
        print_error "Failed to create database or user"
        return 4
    fi
    
    save_state "setup_mysql_database" "completed"
    print_success "Database and user created"
}

################################################################################
# Deployment Functions
################################################################################

deploy_application() {
    print_header "Deploying YFEvents Application"
    save_state "deploy_application" "started"
    
    print_info "Creating deployment directory..."
    if ! mkdir -p "$DEPLOY_DIR" >> "$LOG_FILE" 2>&1; then
        print_error "Failed to create deployment directory"
        return 5
    fi
    
    print_info "Cloning repository..."
    if [[ -d "$DEPLOY_DIR/.git" ]]; then
        print_warning "Repository already exists, pulling latest changes..."
        cd "$DEPLOY_DIR"
        if ! git pull origin "$REPO_BRANCH" >> "$LOG_FILE" 2>&1; then
            print_error "Failed to pull repository updates"
            return 5
        fi
    else
        if ! sudo -u "$DEPLOY_USER" git clone -b "$REPO_BRANCH" "$REPO_URL" "$DEPLOY_DIR" >> "$LOG_FILE" 2>&1; then
            print_error "Failed to clone repository"
            print_info "Make sure the deployment user's SSH key is added to GitHub"
            return 5
        fi
    fi
    
    save_state "deploy_application" "completed"
    print_success "Application deployed from repository"
}

install_dependencies() {
    print_header "Installing Application Dependencies"
    save_state "install_dependencies" "started"
    
    cd "$DEPLOY_DIR"
    
    print_info "Installing Composer dependencies..."
    if ! sudo -u "$DEPLOY_USER" composer install --no-dev --optimize-autoloader >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install Composer dependencies"
        return 5
    fi
    
    save_state "install_dependencies" "completed"
    print_success "Dependencies installed"
}

configure_application() {
    print_header "Configuring Application"
    save_state "configure_application" "started"
    
    cd "$DEPLOY_DIR"
    
    print_info "Creating .env file from template..."
    if [[ -f .env.example ]]; then
        cp .env.example .env
        
        # Update .env with our values
        sed -i "s/DB_HOST=.*/DB_HOST=localhost/" .env
        sed -i "s/DB_PORT=.*/DB_PORT=3306/" .env
        sed -i "s/DB_NAME=.*/DB_NAME=$DB_NAME/" .env
        sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
        sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
        sed -i "s/GOOGLE_MAPS_API_KEY=.*/GOOGLE_MAPS_API_KEY=$GOOGLE_MAPS_API_KEY/" .env
        sed -i "s/APP_URL=.*/APP_URL=https:\/\/$DOMAIN_NAME/" .env
        sed -i "s/APP_ENV=.*/APP_ENV=production/" .env
        sed -i "s/APP_DEBUG=.*/APP_DEBUG=false/" .env
    else
        print_warning ".env.example not found, creating basic .env file"
        cat > .env <<EOF
DB_HOST=localhost
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASSWORD
GOOGLE_MAPS_API_KEY=$GOOGLE_MAPS_API_KEY
APP_URL=https://$DOMAIN_NAME
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Los_Angeles
EOF
    fi
    
    print_info "Creating required directories..."
    mkdir -p cache logs cache/geocode
    
    print_info "Running database migrations..."
    local sql_files=(
        "database/calendar_schema.sql"
        "database/modules_schema.sql"
        "database/communication_schema.sql"
        "database/shop_claim_system.sql"
        "database/intelligent_scraper_schema.sql"
        "database/batch_processing_schema.sql"
        "modules/yfauth/database/schema.sql"
        "modules/yfclaim/database/schema.sql"
    )
    
    for sql_file in "${sql_files[@]}"; do
        if [[ -f "$sql_file" ]]; then
            print_info "Applying $sql_file..."
            if ! mysql --user="$DB_USER" --password="$DB_PASSWORD" "$DB_NAME" < "$sql_file" >> "$LOG_FILE" 2>&1; then
                print_warning "Failed to apply $sql_file (may already exist)"
            fi
        fi
    done
    
    save_state "configure_application" "completed"
    print_success "Application configured"
}

set_permissions() {
    print_header "Setting File Permissions"
    save_state "set_permissions" "started"
    
    cd "$DEPLOY_DIR"
    
    print_info "Setting ownership..."
    chown -R "$DEPLOY_USER:www-data" .
    
    print_info "Setting directory permissions..."
    find . -type d -exec chmod 755 {} \;
    
    print_info "Setting file permissions..."
    find . -type f -exec chmod 644 {} \;
    
    print_info "Setting executable permissions..."
    chmod +x cron/scrape-events.php
    
    print_info "Setting write permissions for cache and logs..."
    chmod -R 775 cache logs
    
    save_state "set_permissions" "completed"
    print_success "Permissions configured"
}

configure_ssl() {
    print_header "Configuring SSL Certificate"
    save_state "configure_ssl" "started"
    
    print_info "Installing Certbot..."
    if ! apt-get install -y certbot python3-certbot-apache >> "$LOG_FILE" 2>&1; then
        print_error "Failed to install Certbot"
        return 5
    fi
    
    print_info "Obtaining SSL certificate..."
    if ! certbot --apache -d "$DOMAIN_NAME" --non-interactive --agree-tos --email "$ADMIN_EMAIL" >> "$LOG_FILE" 2>&1; then
        print_warning "Failed to obtain SSL certificate"
        print_info "You can manually run: certbot --apache -d $DOMAIN_NAME"
    else
        print_success "SSL certificate obtained"
    fi
    
    print_info "Setting up auto-renewal..."
    echo "0 0,12 * * * root certbot renew --quiet" > /etc/cron.d/certbot-renew
    
    save_state "configure_ssl" "completed"
}

setup_cron_jobs() {
    print_header "Setting Up Cron Jobs"
    save_state "setup_cron_jobs" "started"
    
    print_info "Creating cron job for event scraping..."
    cat > /etc/cron.d/yfevents <<EOF
# YFEvents Cron Jobs
0 2 * * * $DEPLOY_USER cd $DEPLOY_DIR && /usr/bin/php cron/scrape-events.php >> logs/scrape-events.log 2>&1
EOF
    
    chmod 644 /etc/cron.d/yfevents
    
    save_state "setup_cron_jobs" "completed"
    print_success "Cron jobs configured"
}

install_monitoring() {
    print_header "Installing Monitoring Tools"
    save_state "install_monitoring" "started"
    
    print_info "Installing fail2ban..."
    if ! apt-get install -y fail2ban >> "$LOG_FILE" 2>&1; then
        print_warning "Failed to install fail2ban"
    else
        print_info "Configuring fail2ban..."
        cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[sshd]
enabled = true

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true

[apache-overflows]
enabled = true
EOF
        
        systemctl restart fail2ban >> "$LOG_FILE" 2>&1
        print_success "fail2ban installed and configured"
    fi
    
    save_state "install_monitoring" "completed"
}

################################################################################
# Main Functions
################################################################################

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_help
                exit 0
                ;;
            -v|--version)
                echo "$SCRIPT_NAME version $SCRIPT_VERSION"
                exit 0
                ;;
            --domain)
                DOMAIN_NAME="$2"
                shift 2
                ;;
            --repo)
                REPO_URL="$2"
                shift 2
                ;;
            --branch)
                REPO_BRANCH="$2"
                shift 2
                ;;
            *)
                print_error "Unknown option: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

show_help() {
    cat << EOF
$SCRIPT_NAME - Deploy YFEvents to Digital Ocean Ubuntu 22.04

Usage: $0 [OPTIONS]

Options:
    -h, --help          Show this help message
    -v, --version       Show script version
    --domain DOMAIN     Set domain name (otherwise prompted)
    --repo URL          Set repository URL (default: $REPO_URL)
    --branch BRANCH     Set repository branch (default: $REPO_BRANCH)

Example:
    sudo $0 --domain example.com

This script will:
1. Validate system requirements
2. Install and configure LAMP stack
3. Deploy YFEvents application
4. Configure SSL certificate
5. Set up monitoring and cron jobs

Requirements:
- Fresh Ubuntu 22.04 installation
- Root or sudo access
- Internet connection
- Valid domain name pointing to server

EOF
}

collect_configuration() {
    print_header "Configuration Setup"
    
    if [[ -z "$DOMAIN_NAME" ]]; then
        echo -n "Enter domain name (e.g., example.com): "
        read -r DOMAIN_NAME
    fi
    
    if [[ -z "$DOMAIN_NAME" ]]; then
        print_error "Domain name is required"
        exit 1
    fi
    
    echo -n "Enter admin email for SSL certificate: "
    read -r ADMIN_EMAIL
    
    if [[ -z "$ADMIN_EMAIL" ]]; then
        print_error "Admin email is required"
        exit 1
    fi
    
    echo -n "Enter Google Maps API key (or press Enter to skip): "
    read -r GOOGLE_MAPS_API_KEY
    
    print_info "Generating secure passwords..."
    DB_ROOT_PASSWORD=$(generate_password)
    DB_PASSWORD=$(generate_password)
    
    print_success "Configuration collected"
}

show_summary() {
    print_header "Deployment Summary"
    
    cat << EOF
Domain Name:        $DOMAIN_NAME
Admin Email:        $ADMIN_EMAIL
Repository:         $REPO_URL
Branch:             $REPO_BRANCH
Deploy Directory:   $DEPLOY_DIR
Deploy User:        $DEPLOY_USER
Database Name:      $DB_NAME
Database User:      $DB_USER
PHP Version:        $PHP_VERSION
MySQL Version:      $MYSQL_VERSION

This script will install:
- Apache 2.4 with mod_rewrite, mod_headers, mod_deflate
- PHP $PHP_VERSION with FPM and required extensions
- MySQL $MYSQL_VERSION
- Composer for PHP dependency management
- Certbot for SSL certificates
- UFW firewall
- fail2ban for security

EOF
    
    if ! confirm_action "Proceed with deployment?"; then
        print_info "Deployment cancelled"
        exit 0
    fi
}

run_deployment() {
    local steps=(
        "update_system"
        "install_apache"
        "install_php"
        "install_mysql"
        "install_git"
        "install_composer"
        "create_deployment_user"
        "configure_firewall"
        "configure_apache_vhost"
        "configure_php"
        "setup_mysql_database"
        "deploy_application"
        "install_dependencies"
        "configure_application"
        "set_permissions"
        "configure_ssl"
        "setup_cron_jobs"
        "install_monitoring"
    )
    
    local total_steps=${#steps[@]}
    local current_step=0
    
    for step in "${steps[@]}"; do
        current_step=$((current_step + 1))
        echo -e "\n${BLUE}[Step $current_step/$total_steps]${NC}"
        
        if ! $step; then
            print_error "Deployment failed at step: $step"
            print_info "Check log file: $LOG_FILE"
            exit $?
        fi
    done
}

show_completion() {
    print_header "Deployment Complete!"
    
    cat << EOF

${GREEN}YFEvents has been successfully deployed!${NC}

Application URLs:
- Main Site: https://$DOMAIN_NAME
- Admin Panel: https://$DOMAIN_NAME/admin/

Database Credentials:
- Database: $DB_NAME
- Username: $DB_USER
- Password: $DB_PASSWORD
- Root Password: $DB_ROOT_PASSWORD

Important Files:
- Application: $DEPLOY_DIR
- Logs: $DEPLOY_DIR/logs/
- Apache Logs: /var/log/apache2/yfevents_*.log
- Deployment Log: $LOG_FILE

Next Steps:
1. Update DNS records to point to this server
2. Test the application at https://$DOMAIN_NAME
3. Log into admin panel and configure settings
4. Monitor logs for any issues

Security Notes:
- Firewall (UFW) is enabled
- fail2ban is protecting SSH and Apache
- SSL certificate will auto-renew
- Database passwords have been generated securely

IMPORTANT: Save the database credentials shown above!

EOF
}

main() {
    # Create log file
    mkdir -p "$(dirname "$LOG_FILE")"
    touch "$LOG_FILE"
    
    echo "=== YFEvents Deployment Started at $(date) ===" >> "$LOG_FILE"
    
    # Parse command line arguments
    parse_arguments "$@"
    
    # Show header
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║     YFEvents Deployment Script v$SCRIPT_VERSION    ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    
    # Validate system
    validate_system
    
    # Collect configuration
    collect_configuration
    
    # Show summary and confirm
    show_summary
    
    # Run deployment
    run_deployment
    
    # Show completion message
    show_completion
    
    echo "=== YFEvents Deployment Completed at $(date) ===" >> "$LOG_FILE"
}

# Run main function
main "$@"