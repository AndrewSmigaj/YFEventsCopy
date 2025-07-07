#!/bin/bash
# YFEvents Deployment Script - Configuration-Driven
# This script deploys YFEvents using the configuration system
# Prerequisites: Run setup-server.sh first

set -euo pipefail  # Exit on error, undefined vars, pipe failures

# Trap for better error reporting
trap 'echo "Error occurred in deploy.sh at line $LINENO. Exit code: $?" >&2' ERR

# Script directory
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Load libraries
source "$SCRIPT_DIR/lib/common.sh"
source "$SCRIPT_DIR/lib/config.sh"
source "$SCRIPT_DIR/lib/database.sh"
source "$SCRIPT_DIR/lib/validation.sh"

# Setup cleanup trap
setup_cleanup_trap

# Default values
ENVIRONMENT="${DEPLOY_ENV:-production}"
APP_DIR="${APP_DIR:-/var/www/yfevents}"
CONFIG_DIR="${CONFIG_DIR:-$APP_DIR/config/deployment}"
VERBOSE=false
DRY_RUN=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        -v|--verbose)
            VERBOSE=true
            DEBUG=true
            shift
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  -e, --environment <env>  Environment to deploy (default: production)"
            echo "  -v, --verbose           Enable verbose output"
            echo "  --dry-run              Show what would happen without making changes"
            echo "  -h, --help             Show this help message"
            echo ""
            echo "Configuration files must exist in: $CONFIG_DIR"
            echo "See config/deployment/deployment.yaml.example for setup"
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            echo "Use -h or --help for usage information"
            exit 1
            ;;
    esac
done

# Main deployment function
main() {
    local start_time=$(date +%s)
    
    print_header "YFEvents Deployment Script"
    print_info "Environment: $ENVIRONMENT"
    print_info "App Directory: $APP_DIR"
    print_info "Config Directory: $CONFIG_DIR"
    print_info "Started at: $(date '+%Y-%m-%d %H:%M:%S')"
    
    if [ "$DRY_RUN" = true ]; then
        print_warning "DRY RUN MODE - No changes will be made"
    fi
    echo ""
    
    # Step 1: Load configuration
    print_header "Step 1/13: Loading Configuration"
    print_info "Loading deployment.yaml and environment-specific configs..."
    
    # Check if configuration exists
    if [ ! -f "$CONFIG_DIR/deployment.yaml" ]; then
        print_error "Configuration not found at $CONFIG_DIR/deployment.yaml"
        echo ""
        echo "Please create your deployment configuration first."
        echo "Example configuration available at: config/deployment/deployment.yaml"
        echo ""
        echo "Quick setup:"
        echo "1. cd $APP_DIR"
        echo "2. cp config/deployment/deployment.yaml.example config/deployment/deployment.yaml"
        echo "3. Edit config/deployment/deployment.yaml with your settings"
        echo "4. Run this script again"
        exit 1
    fi
    
    if ! load_all_configs "$ENVIRONMENT"; then
        print_error "Failed to load configuration"
        exit 1
    fi
    
    # Validate configuration
    if ! validate_config; then
        exit 1
    fi
    
    # Export configuration as environment variables
    export_config_env
    show_config_summary
    print_success "✓ Configuration loaded successfully"
    
    if [ "$DRY_RUN" = true ]; then
        print_info "DRY RUN MODE - Continuing to show what would happen..."
    fi
    
    # Step 2: Pre-deployment validation
    print_header "Step 2/13: Pre-deployment Validation"
    print_info "Checking system requirements and permissions..."
    if ! run_pre_deployment_validation; then
        print_error "Pre-deployment validation failed"
        if ! confirm "Continue anyway?"; then
            exit 1
        fi
    fi
    
    # Step 3: Backup existing deployment
    if [ -d "$APP_DIR" ] && [ -f "$APP_DIR/.env" ]; then
        print_header "Step 3/13: Backup Current Deployment"
        print_info "Creating safety backup of database and files..."
        
        # Backup database
        if test_db_connection; then
            if backup_database; then
                print_status "Database backed up successfully"
            else
                print_warning "Database backup failed"
                if ! confirm "Continue without database backup?"; then
                    exit 1
                fi
            fi
        fi
        
        # Backup important files
        backup_item "$APP_DIR/.env"
        backup_item "$APP_DIR/storage/logs"
        backup_item "$APP_DIR/public/uploads"
    fi
    
    # Step 4: Deploy application code
    print_header "Step 4/13: Deploy Application Code"
    print_info "Fetching latest code from repository..."
    deploy_application_code
    
    # Step 5: Install dependencies
    print_header "Step 5/13: Install Dependencies"
    print_info "Running composer install..."
    install_dependencies
    
    # Step 6: Configure environment
    print_header "Step 6/13: Configure Environment"
    print_info "Setting up .env file and configuration..."
    configure_environment
    
    # Step 7: Set up directories and permissions
    print_header "Step 7/13: Set Up Directories and Permissions"
    print_info "Creating required directories and setting ownership..."
    setup_directories_and_permissions
    
    # Step 8: Run database migrations
    print_header "Step 8/13: Database Setup"
    print_info "Running database migrations and schema updates..."
    if ! run_database_migrations; then
        print_error "Database migration failed"
        exit 1
    fi
    
    show_database_stats
    
    # Step 9: Configure web server
    if [ "$ENVIRONMENT" = "production" ]; then
        print_header "Step 9/13: Configure Web Server"
        print_info "Setting up Apache virtual host and SSL..."
        configure_apache
    fi
    
    # Step 10: Set up cron jobs
    print_header "Step 10/13: Set Up Cron Jobs"
    print_info "Installing scheduled tasks..."
    setup_cron_jobs
    
    # Step 11: Create admin user if needed
    if ! table_exists "yfa_auth_users" || [ $(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
        -e "SELECT COUNT(*) FROM yfa_auth_users WHERE JSON_CONTAINS(roles, '\"admin\"')" -s --skip-column-names 2>/dev/null || echo "0") -eq 0 ]; then
        print_header "Step 11/13: Create Admin User"
        print_info "Setting up initial admin account..."
        create_admin_user
    fi
    
    # Step 12: Post-deployment validation
    print_header "Step 12/13: Post-Deployment Validation"
    print_info "Running health checks and verifying installation..."
    if ! run_post_deployment_validation; then
        print_warning "Post-deployment validation reported issues"
    fi
    
    # Step 13: Final cleanup
    print_header "Step 13/13: Cleanup"
    print_info "Clearing caches and optimizing..."
    cleanup_deployment
    
    # Success!
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    local minutes=$((duration / 60))
    local seconds=$((duration % 60))
    
    print_header "Deployment Complete!"
    print_success "✓ All deployment steps completed successfully"
    print_info "Total time: ${minutes}m ${seconds}s"
    print_info "Completed at: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
    show_deployment_summary
}

# Deploy application code
deploy_application_code() {
    local repo_url=$(get_config "deployment.repository.url")
    local repo_branch=$(get_config "deployment.repository.branch" "main")
    
    if [ -z "$repo_url" ] || [ "$repo_url" = "local" ]; then
        print_info "Using local deployment mode"
        if [ -d "$APP_DIR" ]; then
            print_warning "Backing up existing application directory..."
            backup_item "$APP_DIR"
            dry_run_exec "Remove existing application directory" rm -rf "$APP_DIR"
        fi
        dry_run_exec "Copy local files to application directory" cp -r "$(dirname "$(dirname "$SCRIPT_DIR")")" "$APP_DIR"
    else
        if [ -d "$APP_DIR/.git" ]; then
            print_info "Updating existing repository..."
            cd "$APP_DIR"
            
            # Stash any local changes
            dry_run_exec "Stash local changes" git stash save "Deployment backup $(date +%Y%m%d_%H%M%S)" --include-untracked || true
            
            # Fetch and checkout
            dry_run_exec "Fetch from origin" git fetch origin
            dry_run_exec "Checkout branch $repo_branch" git checkout "$repo_branch"
            dry_run_exec "Reset to origin/$repo_branch" git reset --hard "origin/$repo_branch"
        else
            print_info "Cloning repository..."
            if [ -d "$APP_DIR" ]; then
                backup_item "$APP_DIR"
                dry_run_exec "Remove existing directory" rm -rf "$APP_DIR"
            fi
            
            # Clone with specific branch
            dry_run_exec "Clone repository from $repo_url" git clone -b "$repo_branch" "$repo_url" "$APP_DIR"
        fi
    fi
    
    cd "$APP_DIR"
    print_success "✓ Application code deployed successfully"
}

# Install dependencies
install_dependencies() {
    cd "$APP_DIR"
    
    if [ -f "composer.json" ]; then
        print_info "Installing Composer dependencies..."
        
        local composer_args="--no-interaction --prefer-dist"
        
        if [ "$ENVIRONMENT" = "production" ]; then
            composer_args="$composer_args --no-dev --optimize-autoloader"
        fi
        
        dry_run_exec "Install composer dependencies" run_as_user "www-data" "composer install $composer_args"
        print_success "✓ Dependencies installed successfully"
    else
        print_warning "No composer.json found"
    fi
}

# Configure environment
configure_environment() {
    cd "$APP_DIR"
    
    # Create .env if it doesn't exist
    if [ ! -f ".env" ]; then
        if [ -f ".env.example" ]; then
            dry_run_exec "Create .env from example" cp .env.example .env
            print_info "Created .env from example"
        else
            print_error "No .env.example found"
            return 1
        fi
    fi
    
    # Update .env with deployment values
    print_info "Updating environment configuration..."
    
    # Update database configuration
    dry_run_exec "Update DB_HOST in .env" sed -i "s/^DB_HOST=.*/DB_HOST=$DB_HOST/" .env
    dry_run_exec "Update DB_DATABASE in .env" sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$DB_DATABASE/" .env
    dry_run_exec "Update DB_USERNAME in .env" sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USERNAME/" .env
    dry_run_exec "Update DB_PASSWORD in .env" sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
    
    # Update application configuration
    local app_url=$(get_config "deployment.apache.server_name" "localhost")
    if [ "$(get_config "deployment.apache.ssl.enabled")" = "true" ]; then
        app_url="https://$app_url"
    else
        app_url="http://$app_url"
    fi
    
    dry_run_exec "Update APP_URL in .env" sed -i "s|^APP_URL=.*|APP_URL=$app_url|" .env
    dry_run_exec "Update APP_ENV in .env" sed -i "s/^APP_ENV=.*/APP_ENV=$ENVIRONMENT/" .env
    
    if [ "$ENVIRONMENT" = "production" ]; then
        sed -i "s/^APP_DEBUG=.*/APP_DEBUG=false/" .env
    fi
    
    # Generate APP_KEY if not set
    if ! grep -q "^APP_KEY=.+" .env; then
        local app_key=$(generate_random_string 32)
        sed -i "s/^APP_KEY=.*/APP_KEY=$app_key/" .env
        print_info "Generated new APP_KEY"
    fi
    
    # Set permissions on .env
    chmod 600 .env
    chown www-data:www-data .env
    
    print_status "Environment configured"
}

# Setup directories and permissions
setup_directories_and_permissions() {
    cd "$APP_DIR"
    
    # Create required directories
    local dirs=$(get_config "deployment.directories.create")
    
    for dir in $dirs; do
        ensure_directory "$APP_DIR/$dir" "755" "www-data:www-data"
    done
    
    # Set file permissions
    print_info "Setting file permissions..."
    
    # Default permissions
    find "$APP_DIR" -type f -exec chmod 644 {} \;
    find "$APP_DIR" -type d -exec chmod 755 {} \;
    
    # Executable files
    chmod +x "$APP_DIR/cron/scrape-events.php" 2>/dev/null || true
    chmod +x "$APP_DIR/scripts/deploy/"*.sh 2>/dev/null || true
    chmod +x "$APP_DIR/scripts/deploy/"*.php 2>/dev/null || true
    
    # Set ownership
    chown -R www-data:www-data "$APP_DIR"
    
    print_status "Permissions configured"
}

# Configure Apache
configure_apache() {
    local domain=$(get_config "deployment.apache.server_name" "localhost")
    local ssl_email=$(get_config "deployment.apache.server_admin" "admin@$domain")
    
    print_info "Configuring Apache for domain: $domain"
    
    # Copy virtual host configuration
    if [ -f "$APP_DIR/scripts/deploy/apache-vhost.conf" ]; then
        cp "$APP_DIR/scripts/deploy/apache-vhost.conf" "/etc/apache2/sites-available/yfevents.conf"
        
        # Replace placeholders
        sed -i "s/DOMAIN_NAME/$domain/g" "/etc/apache2/sites-available/yfevents.conf"
        sed -i "s|APP_DIR|$APP_DIR|g" "/etc/apache2/sites-available/yfevents.conf"
        
        # Enable site
        a2ensite yfevents.conf
        a2dissite 000-default.conf 2>/dev/null || true
        
        # Reload Apache
        systemctl reload apache2
        print_status "Apache configured"
        
        # Set up SSL if enabled
        if [ "$(get_config "deployment.apache.ssl.enabled")" = "true" ] && [ "$domain" != "localhost" ]; then
            print_info "Setting up SSL certificate..."
            
            if command_exists certbot; then
                certbot --apache -d "$domain" -d "www.$domain" \
                    --non-interactive --agree-tos --email "$ssl_email" --redirect || {
                    print_warning "SSL setup failed - site will be available via HTTP only"
                }
            else
                print_warning "Certbot not installed - skipping SSL setup"
            fi
        fi
    else
        print_warning "Apache configuration template not found"
    fi
}

# Setup cron jobs
setup_cron_jobs() {
    print_info "Setting up cron jobs..."
    
    if [ "$DRY_RUN" = true ]; then
        print_info "[DRY RUN] Would configure cron jobs for www-data user"
        return 0
    fi
    
    # Create temporary file for cron jobs
    local temp_cron=$(mktemp)
    
    # Get existing cron jobs (excluding YFEvents)
    crontab -u www-data -l 2>/dev/null | grep -v "# YFEvents" | grep -v "$APP_DIR" > "$temp_cron" || true
    
    # Add header for YFEvents jobs
    echo "# YFEvents Automated Tasks - Added by deploy.sh on $(date)" >> "$temp_cron"
    
    # Ensure cron log file exists and is writable
    touch "$APP_DIR/storage/logs/cron.log"
    chown www-data:www-data "$APP_DIR/storage/logs/cron.log"
    
    # Add configured cron jobs
    local cron_schedule=$(get_config "deployment.cron.jobs.0.schedule" "0 */6 * * *")
    local cron_command="cd $APP_DIR && /usr/bin/php cron/scrape-events.php >> storage/logs/cron.log 2>&1"
    echo "$cron_schedule $cron_command # YFEvents event scraper" >> "$temp_cron"
    
    # Install the new crontab
    crontab -u www-data "$temp_cron"
    local result=$?
    
    # Clean up
    rm -f "$temp_cron"
    
    if [ $result -eq 0 ]; then
        print_success "✓ Cron jobs configured successfully"
        
        # Verify installation
        local job_count=$(crontab -u www-data -l 2>/dev/null | grep -c "YFEvents" || echo "0")
        print_info "Installed $job_count YFEvents cron job(s)"
    else
        print_error "Failed to install cron jobs"
        return 1
    fi
}

# Create admin user
create_admin_user() {
    if [ -f "$APP_DIR/scripts/deploy/create-admin.php" ]; then
        cd "$APP_DIR"
        run_as_user "www-data" "php scripts/deploy/create-admin.php"
    else
        print_warning "Admin creation script not found"
    fi
}

# Cleanup deployment
cleanup_deployment() {
    print_info "Cleaning up..."
    
    # Clear any caches
    if [ -d "$APP_DIR/storage/cache" ]; then
        find "$APP_DIR/storage/cache" -type f -delete 2>/dev/null || true
    fi
    
    # Remove temporary files
    find "$APP_DIR" -name "*.tmp" -delete 2>/dev/null || true
    
    # Clean old backups
    cleanup_old_backups "${BACKUP_DIR:-/var/backups/yfevents}" 3
    
    # Run any post-deployment hooks
    local post_hooks=$(get_config "deployment.hooks.post_deploy")
    if [ -n "$post_hooks" ]; then
        print_info "Running post-deployment hooks..."
        for hook in $post_hooks; do
            cd "$APP_DIR"
            run_as_user "www-data" "$hook" || print_warning "Hook failed: $hook"
        done
    fi
    
    print_status "Cleanup complete"
}

# Show deployment summary
show_deployment_summary() {
    local domain=$(get_config "deployment.apache.server_name" "localhost")
    local base_url="http://$domain"
    
    if [ "$(get_config "deployment.apache.ssl.enabled")" = "true" ]; then
        base_url="https://$domain"
    fi
    
    echo ""
    print_status "YFEvents has been successfully deployed!"
    echo ""
    echo "Access URLs:"
    echo "- Main site: ${BLUE}$base_url${NC}"
    echo "- Admin panel: ${BLUE}$base_url/admin${NC}"
    echo "- API endpoints: ${BLUE}$base_url/api/${NC}"
    echo "- Chat system: ${BLUE}$base_url/communication${NC}"
    echo ""
    echo "Configuration:"
    echo "- Environment: $ENVIRONMENT"
    echo "- Config file: $CONFIG_DIR/deployment.yaml"
    echo "- App directory: $APP_DIR"
    echo ""
    echo "Next steps:"
    echo "1. Test the site in your browser"
    echo "2. Review the post-deployment validation results above"
    echo "3. Configure any additional settings in the admin panel"
    echo ""
    print_info "Log files: $APP_DIR/storage/logs/"
    print_info "To create more admins: cd $APP_DIR && sudo -u www-data php scripts/deploy/create-admin.php"
    echo ""
}

# Run main function
main "$@"