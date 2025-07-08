#!/bin/bash
# YFEvents Deployment Validation Library
# Pre-flight checks and post-deployment validation

# Source common functions
LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$LIB_DIR/common.sh"

# Validation results
declare -A VALIDATION_RESULTS
VALIDATION_ERRORS=0
VALIDATION_WARNINGS=0

# Record validation result
# Usage: record_result <component> <status> <message>
record_result() {
    local component="$1"
    local status="$2"  # pass, fail, warn
    local message="$3"
    
    VALIDATION_RESULTS["$component"]="$status:$message"
    
    case "$status" in
        "fail")
            ((VALIDATION_ERRORS++))
            print_error "$component: $message"
            ;;
        "warn")
            ((VALIDATION_WARNINGS++))
            print_warning "$component: $message"
            ;;
        "pass")
            print_status "$component: $message"
            ;;
    esac
}

# Check PHP version and extensions
# Usage: validate_php_requirements
validate_php_requirements() {
    print_info "Validating PHP requirements..."
    
    # Check PHP version
    local required_version=$(get_config "deployment.requirements.php.version" "^8.1")
    required_version=${required_version#^}  # Remove ^ prefix
    required_version=${required_version%.*}  # Get major.minor only
    
    if command_exists php; then
        local current_version=$(get_php_version)
        local current_major_minor=${current_version%.*}
        
        if version_compare "$current_major_minor" "ge" "$required_version"; then
            record_result "PHP Version" "pass" "$current_version (>= $required_version)"
        else
            record_result "PHP Version" "fail" "$current_version (requires >= $required_version)"
        fi
        
        # Check PHP extensions
        local required_extensions=$(get_config "deployment.requirements.php.extensions" "mysql curl mbstring json xml gd fileinfo")
        local installed_extensions=$(php -m 2>/dev/null | tr '[:upper:]' '[:lower:]')
        
        for ext in $required_extensions; do
            # Handle different extension names (mysql vs mysqli)
            local found=false
            case "$ext" in
                "mysql")
                    if echo "$installed_extensions" | grep -qE "^(mysql|mysqli|pdo_mysql)$"; then
                        found=true
                    fi
                    ;;
                *)
                    if echo "$installed_extensions" | grep -q "^$ext$"; then
                        found=true
                    fi
                    ;;
            esac
            
            if [ "$found" = true ]; then
                record_result "PHP Extension: $ext" "pass" "Installed"
            else
                record_result "PHP Extension: $ext" "fail" "Not installed"
            fi
        done
    else
        record_result "PHP" "fail" "Not installed"
    fi
}

# Check MySQL/MariaDB requirements
# Usage: validate_mysql_requirements
validate_mysql_requirements() {
    print_info "Validating MySQL requirements..."
    
    if command_exists mysql; then
        # Try to get version safely
        local version=$(mysql --version 2>/dev/null | grep -oP '(?<=Ver |Distrib )\d+\.\d+\.\d+' | head -1)
        
        if [ -n "$version" ]; then
            local required_version=$(get_config "deployment.requirements.mysql.version" "^8.0")
            required_version=${required_version#^}
            
            if version_compare "$version" "ge" "$required_version"; then
                record_result "MySQL Version" "pass" "$version"
            else
                # MariaDB 10.5+ is equivalent to MySQL 8.0
                if [[ "$version" =~ ^10\. ]] && version_compare "$version" "ge" "10.5.0"; then
                    record_result "MySQL Version" "pass" "MariaDB $version (compatible)"
                else
                    record_result "MySQL Version" "warn" "$version (MySQL 8.0+ recommended)"
                fi
            fi
        else
            record_result "MySQL Version" "warn" "Could not determine version"
        fi
    else
        record_result "MySQL" "fail" "Not installed"
    fi
}

# Check Apache requirements
# Usage: validate_apache_requirements
validate_apache_requirements() {
    print_info "Validating Apache requirements..."
    
    if command_exists apache2 || command_exists httpd; then
        record_result "Apache" "pass" "Installed"
        
        # Check required modules
        local required_modules=$(get_config "deployment.requirements.apache.modules" "rewrite headers expires ssl")
        local apache_cmd="apache2ctl"
        
        # Handle different Apache commands
        if ! command_exists apache2ctl && command_exists apachectl; then
            apache_cmd="apachectl"
        fi
        
        # Get loaded modules safely
        local loaded_modules=$($apache_cmd -M 2>/dev/null | grep -oE '^\s*\w+_module' | tr -d ' ' || echo "")
        
        if [ -n "$loaded_modules" ]; then
            for mod in $required_modules; do
                if echo "$loaded_modules" | grep -q "^${mod}_module$"; then
                    record_result "Apache Module: $mod" "pass" "Enabled"
                else
                    record_result "Apache Module: $mod" "fail" "Not enabled"
                fi
            done
        else
            record_result "Apache Modules" "warn" "Could not check modules (apache may not be running)"
        fi
    else
        record_result "Apache" "fail" "Not installed"
    fi
}

# Check system requirements
# Usage: validate_system_requirements
validate_config_files() {
    print_info "Validating configuration files..."
    
    # Check deployment config
    if [ -f "$CONFIG_DIR/deployment.yaml" ]; then
        record_result "Deployment Config" "pass" "Found deployment.yaml"
    else
        record_result "Deployment Config" "fail" "Missing deployment.yaml"
    fi
    
    # Check environment config
    local env_config="$CONFIG_DIR/environments/${ENVIRONMENT}.yaml"
    if [ -f "$env_config" ]; then
        record_result "Environment Config" "pass" "Found ${ENVIRONMENT}.yaml"
    else
        record_result "Environment Config" "warn" "Missing ${ENVIRONMENT}.yaml (using defaults)"
    fi
    
    # Check example files
    if [ -f "${APP_DIR}/.env.example" ]; then
        record_result ".env Template" "pass" "Found .env.example"
    else
        record_result ".env Template" "warn" "Missing .env.example"
    fi
}

validate_system_requirements() {
    print_info "Validating system requirements..."
    
    # Check OS
    if [ -f /etc/os-release ]; then
        source /etc/os-release
        local target_platform=$(get_config "deployment.automated_deployment.target_platform" "Ubuntu 22.04")
        
        if [[ "$PRETTY_NAME" =~ $target_platform ]]; then
            record_result "Operating System" "pass" "$PRETTY_NAME"
        else
            record_result "Operating System" "warn" "$PRETTY_NAME ($target_platform recommended)"
        fi
    fi
    
    # Check memory
    local total_mem=$(free -m 2>/dev/null | awk '/^Mem:/{print $2}' || echo "0")
    local required_mem=$(get_config "deployment.automated_deployment.requirements.server.memory" "2048")
    
    if [ "$total_mem" -ge "$required_mem" ]; then
        record_result "Memory" "pass" "${total_mem}MB (>= ${required_mem}MB)"
    else
        record_result "Memory" "warn" "${total_mem}MB (${required_mem}MB+ recommended)"
    fi
    
    # Check disk space
    if [ -d "${APP_DIR:-/var/www/yfevents}" ] || [ -d "$(dirname "${APP_DIR:-/var/www/yfevents}")" ]; then
        local mount_point="${APP_DIR:-/var/www/yfevents}"
        [ ! -d "$mount_point" ] && mount_point=$(dirname "$mount_point")
        
        local free_space=$(df -BG "$mount_point" 2>/dev/null | awk 'NR==2 {print $4}' | sed 's/G//' || echo "0")
        local required_space=$(get_config "deployment.automated_deployment.requirements.server.storage" "10")
        
        if [ "$free_space" -ge "$required_space" ]; then
            record_result "Disk Space" "pass" "${free_space}GB free"
        else
            record_result "Disk Space" "warn" "${free_space}GB free (${required_space}GB+ recommended)"
        fi
    fi
    
    # Check required commands
    local commands="git composer openssl"
    
    for cmd in $commands; do
        if command_exists "$cmd"; then
            local version_info=""
            case "$cmd" in
                "git")
                    version_info=" ($(git --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1))"
                    ;;
                "composer")
                    version_info=" ($(composer --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1))"
                    ;;
            esac
            record_result "Command: $cmd" "pass" "Available${version_info}"
        else
            record_result "Command: $cmd" "fail" "Not found"
        fi
    done
}

# Validate file permissions
# Usage: validate_file_permissions
validate_file_permissions() {
    print_info "Validating file permissions..."
    
    # Get configured directories
    local create_dirs=$(get_config "deployment.directories.create" "storage storage/logs storage/cache storage/sessions public/uploads")
    
    for dir in $create_dirs; do
        local full_path="$APP_DIR/$dir"
        
        if [ -d "$full_path" ]; then
            # Check ownership
            local expected_owner="www-data"
            if ! user_exists "$expected_owner"; then
                expected_owner=$(whoami)
            fi
            
            local current_owner=$(stat -c '%U' "$full_path" 2>/dev/null || echo "unknown")
            if [ "$current_owner" = "$expected_owner" ] || [ "$current_owner" = "$(whoami)" ]; then
                record_result "Directory: $dir" "pass" "Exists with correct ownership"
            else
                record_result "Directory: $dir" "warn" "Owner: $current_owner (expected: $expected_owner)"
            fi
            
            # Check if writable
            if [ -w "$full_path" ]; then
                record_result "Directory writable: $dir" "pass" "Writable"
            else
                record_result "Directory writable: $dir" "fail" "Not writable"
            fi
        else
            record_result "Directory: $dir" "fail" "Does not exist"
        fi
    done
    
    # Check .env file
    if [ -f "$APP_DIR/.env" ]; then
        local env_perms=$(stat -c '%a' "$APP_DIR/.env" 2>/dev/null)
        if [[ "$env_perms" =~ ^6[04]0$ ]]; then
            record_result "Environment file" "pass" "Secure permissions ($env_perms)"
        else
            record_result "Environment file" "warn" "Permissions: $env_perms (should be 600 or 640)"
        fi
    else
        record_result "Environment file" "fail" "Not found"
    fi
}

# Validate application configuration
# Usage: validate_app_configuration
validate_app_configuration() {
    print_info "Validating application configuration..."
    
    if [ -f "$APP_DIR/.env" ]; then
        # Check for required environment variables
        local required_vars=(
            "APP_KEY"
            "APP_URL"
            "DB_HOST"
            "DB_DATABASE"
            "DB_USERNAME"
            "DB_PASSWORD"
        )
        
        # Read .env file safely
        while IFS='=' read -r key value; do
            # Skip comments and empty lines
            [[ "$key" =~ ^#.*$ ]] && continue
            [[ -z "$key" ]] && continue
            
            # Remove quotes from value
            value=$(echo "$value" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
            
            # Check if this is a required variable
            for var in "${required_vars[@]}"; do
                if [ "$key" = "$var" ]; then
                    if [ -n "$value" ]; then
                        # Don't expose sensitive values
                        if [[ "$var" =~ (PASSWORD|KEY|SECRET) ]]; then
                            record_result "Config: $var" "pass" "Set (hidden)"
                        else
                            record_result "Config: $var" "pass" "Set: $value"
                        fi
                    else
                        record_result "Config: $var" "fail" "Empty value"
                    fi
                fi
            done
        done < "$APP_DIR/.env"
        
        # Check environment-specific settings
        local app_env=$(grep "^APP_ENV=" "$APP_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
        if [ "$app_env" = "production" ]; then
            local app_debug=$(grep "^APP_DEBUG=" "$APP_DIR/.env" | cut -d'=' -f2- | tr -d '"' | tr -d "'")
            if [ "$app_debug" = "false" ] || [ "$app_debug" = "0" ]; then
                record_result "Production settings" "pass" "Debug disabled"
            else
                record_result "Production settings" "warn" "Debug enabled in production"
            fi
        fi
    else
        record_result "Application config" "fail" ".env file not found"
    fi
}

# Validate database connectivity
# Usage: validate_database
validate_database() {
    print_info "Validating database..."
    
    # Load database credentials from .env if available
    if [ -f "$APP_DIR/.env" ]; then
        # Parse .env file safely
        eval $(grep -E '^DB_' "$APP_DIR/.env" | sed 's/^/export /' | sed 's/=\(.*\)$/="\1"/')
    fi
    
    # Use configuration values with .env overrides
    local db_host="${DB_HOST:-$(get_config "deployment.database.host" "localhost")}"
    local db_name="${DB_DATABASE:-$(get_config "deployment.database.name" "yakima_finds")}"
    local db_user="${DB_USERNAME:-$(get_config "deployment.database.user" "yfevents")}"
    local db_pass="${DB_PASSWORD:-$(get_config "deployment.database.password" "")}"
    
    # Test connection
    if mysql -h "$db_host" -u "$db_user" -p"$db_pass" -e "SELECT 1" >/dev/null 2>&1; then
        record_result "Database connection" "pass" "Connected successfully"
        
        # Check if database exists
        if mysql -h "$db_host" -u "$db_user" -p"$db_pass" -e "USE $db_name" 2>/dev/null; then
            record_result "Database" "pass" "$db_name exists"
            
            # Check for key tables
            local key_tables="yfa_auth_users events local_shops communication_channels"
            for table in $key_tables; do
                if mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" \
                    -e "SELECT 1 FROM $table LIMIT 1" >/dev/null 2>&1; then
                    record_result "Table: $table" "pass" "Exists and accessible"
                else
                    # Check if table exists but might be empty
                    if mysql -h "$db_host" -u "$db_user" -p"$db_pass" "$db_name" \
                        -e "DESCRIBE $table" >/dev/null 2>&1; then
                        record_result "Table: $table" "pass" "Exists (empty)"
                    else
                        record_result "Table: $table" "fail" "Not found"
                    fi
                fi
            done
        else
            record_result "Database" "fail" "$db_name not found"
        fi
    else
        record_result "Database connection" "fail" "Could not connect (check credentials)"
    fi
}

# Validate web routes
# Usage: validate_web_routes
validate_web_routes() {
    print_info "Validating web routes..."
    
    # Get configured domain or use localhost
    local domain=$(get_config "deployment.apache.server_name" "localhost")
    local base_url="http://$domain"
    
    # Check if we should use HTTPS
    if [ "$(get_config "deployment.apache.ssl.enabled")" = "true" ]; then
        base_url="https://$domain"
    fi
    
    # Only run if web server is accessible
    if curl -s --connect-timeout 2 "$base_url" >/dev/null 2>&1 || [ "$domain" = "localhost" ]; then
        # Check key routes
        local routes=(
            "/:Homepage"
            "/api/health:Health endpoint"
            "/admin/login:Admin login"
            "/communication:Chat system"
        )
        
        for route_info in "${routes[@]}"; do
            IFS=':' read -r route description <<< "$route_info"
            
            # Use curl to check route (follow redirects, timeout 5s)
            local response=$(curl -s -o /dev/null -w "%{http_code}" -L --max-time 5 \
                --connect-timeout 5 "${base_url}${route}" 2>/dev/null || echo "000")
            
            case "$response" in
                200|201)
                    record_result "Route: $route" "pass" "$description (OK)"
                    ;;
                301|302|303|307|308)
                    record_result "Route: $route" "pass" "$description (Redirect)"
                    ;;
                401|403)
                    record_result "Route: $route" "pass" "$description (Auth required)"
                    ;;
                404)
                    record_result "Route: $route" "fail" "$description (Not Found)"
                    ;;
                500|502|503)
                    record_result "Route: $route" "fail" "$description (Server Error)"
                    ;;
                000)
                    record_result "Route: $route" "fail" "$description (Connection failed)"
                    ;;
                *)
                    record_result "Route: $route" "warn" "$description (HTTP $response)"
                    ;;
            esac
        done
    else
        record_result "Web routes" "warn" "Web server not accessible - skipping route validation"
    fi
}

# Run all pre-deployment validations
# Usage: run_pre_deployment_validation
run_pre_deployment_validation() {
    print_header "Pre-Deployment Validation"
    
    VALIDATION_ERRORS=0
    VALIDATION_WARNINGS=0
    
    validate_config_files
    validate_system_requirements
    validate_php_requirements
    validate_mysql_requirements
    validate_apache_requirements
    
    show_validation_summary
    return $VALIDATION_ERRORS
}

# Run all post-deployment validations
# Usage: run_post_deployment_validation
run_post_deployment_validation() {
    print_header "Post-Deployment Validation"
    
    VALIDATION_ERRORS=0
    VALIDATION_WARNINGS=0
    
    validate_file_permissions
    validate_app_configuration
    validate_database
    validate_web_routes
    
    show_validation_summary
    return $VALIDATION_ERRORS
}

# Display validation summary
# Usage: show_validation_summary
show_validation_summary() {
    echo ""
    echo "Validation Summary"
    echo "=================="
    echo "Total checks: ${#VALIDATION_RESULTS[@]}"
    echo "Passed: $((${#VALIDATION_RESULTS[@]} - VALIDATION_ERRORS - VALIDATION_WARNINGS))"
    echo "Warnings: $VALIDATION_WARNINGS"
    echo "Errors: $VALIDATION_ERRORS"
    echo ""
    
    if [ $VALIDATION_ERRORS -eq 0 ]; then
        if [ $VALIDATION_WARNINGS -eq 0 ]; then
            print_status "All validations passed successfully!"
        else
            print_warning "Validation completed with $VALIDATION_WARNINGS warnings"
        fi
        return 0
    else
        print_error "Validation failed with $VALIDATION_ERRORS errors"
        echo ""
        echo "Failed checks:"
        for key in "${!VALIDATION_RESULTS[@]}"; do
            IFS=':' read -r status message <<< "${VALIDATION_RESULTS[$key]}"
            if [ "$status" = "fail" ]; then
                echo "  - $key: $message"
            fi
        done
        return 1
    fi
}

# Export validation functions
export -f record_result validate_php_requirements validate_mysql_requirements
export -f validate_apache_requirements validate_system_requirements
export -f validate_file_permissions validate_app_configuration
export -f validate_database validate_web_routes
export -f run_pre_deployment_validation run_post_deployment_validation
export -f show_validation_summary