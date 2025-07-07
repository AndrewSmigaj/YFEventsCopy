#!/bin/bash
# YFEvents Database Operations Library
# Handles database connections, migrations, and schema management

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/common.sh"

# Database connection parameters (set by config.sh)
DB_HOST="${DB_HOST:-localhost}"
DB_DATABASE="${DB_DATABASE:-yakima_finds}"
DB_USERNAME="${DB_USERNAME:-yfevents}"
DB_PASSWORD="${DB_PASSWORD:-}"

# Test database connection
# Usage: test_db_connection
test_db_connection() {
    print_info "Testing database connection..."
    
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; then
        print_status "Database connection successful"
        return 0
    else
        print_error "Failed to connect to database"
        return 1
    fi
}

# Create database if it doesn't exist
# Usage: create_database_if_not_exists
create_database_if_not_exists() {
    local db_exists=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW DATABASES LIKE '$DB_DATABASE'" -s --skip-column-names 2>/dev/null)
    
    if [ -z "$db_exists" ]; then
        print_info "Creating database: $DB_DATABASE"
        
        if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
            -e "CREATE DATABASE IF NOT EXISTS $DB_DATABASE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; then
            print_status "Database created successfully"
        else
            print_error "Failed to create database"
            return 1
        fi
    else
        print_info "Database already exists: $DB_DATABASE"
    fi
    
    return 0
}

# Check if table exists
# Usage: table_exists <table_name>
table_exists() {
    local table="$1"
    local exists=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
        -e "SHOW TABLES LIKE '$table'" -s --skip-column-names 2>/dev/null)
    
    [ -n "$exists" ]
}

# Execute SQL file
# Usage: execute_sql_file <file_path> [description]
execute_sql_file() {
    local file="$1"
    local description="${2:-$(basename "$file")}"
    
    if [ ! -f "$file" ]; then
        print_warning "SQL file not found: $file"
        return 1
    fi
    
    print_info "Executing: $description"
    
    # Create temporary file with error handling
    local temp_file=$(mktemp)
    local error_file=$(mktemp)
    
    # Execute SQL file
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$file" 2>"$error_file"; then
        print_status "Successfully executed: $description"
        rm -f "$temp_file" "$error_file"
        return 0
    else
        print_error "Failed to execute: $description"
        
        # Show error details if available
        if [ -s "$error_file" ]; then
            print_error "Error details:"
            cat "$error_file" >&2
        fi
        
        rm -f "$temp_file" "$error_file"
        return 1
    fi
}

# Run database migrations based on configuration
# Usage: run_database_migrations
run_database_migrations() {
    print_header "Running Database Migrations"
    
    # Ensure database exists
    create_database_if_not_exists || return 1
    
    # Test connection
    test_db_connection || return 1
    
    local migration_count=0
    local failed_count=0
    
    # Core schemas
    print_info "Installing core schemas..."
    local core_schemas=(
        "database/calendar_schema.sql:Calendar and events system"
        "database/shop_claim_system.sql:Shop system"
        "database/modules_schema.sql:Module support system"
    )
    
    for schema_info in "${core_schemas[@]}"; do
        IFS=':' read -r schema_file schema_desc <<< "$schema_info"
        
        if [ -f "$APP_DIR/$schema_file" ]; then
            if execute_sql_file "$APP_DIR/$schema_file" "$schema_desc"; then
                ((migration_count++))
            else
                ((failed_count++))
                print_error "Core schema failed - stopping migration"
                return 1
            fi
        fi
    done
    
    # Communication system
    local comm_type=$(get_config "deployment.database.schemas.communication.type" "full")
    if [ "$comm_type" != "none" ]; then
        print_info "Installing communication system ($comm_type)..."
        
        local comm_schema
        case "$comm_type" in
            "full")
                comm_schema="database/communication_schema_fixed.sql"
                ;;
            "subset")
                comm_schema="database/yfchat_subset.sql"
                ;;
        esac
        
        if [ -f "$APP_DIR/$comm_schema" ]; then
            if execute_sql_file "$APP_DIR/$comm_schema" "Communication system"; then
                ((migration_count++))
            else
                ((failed_count++))
            fi
        fi
    fi
    
    # Module schemas
    print_info "Installing module schemas..."
    local modules=$(get_config "deployment.modules.active" "yfauth yfclaim yftheme")
    
    for module in $modules; do
        local module_schema="modules/$module/database/schema.sql"
        
        if [ -f "$APP_DIR/$module_schema" ]; then
            # Check if module tables already exist
            local skip_module=false
            
            case "$module" in
                "yfauth")
                    table_exists "yfa_auth_users" && skip_module=true
                    ;;
                "yfclaim")
                    table_exists "yfc_sellers" && skip_module=true
                    ;;
                "yftheme")
                    table_exists "theme_settings" && skip_module=true
                    ;;
            esac
            
            if [ "$skip_module" = true ]; then
                print_info "Module $module already installed - skipping"
            else
                if execute_sql_file "$APP_DIR/$module_schema" "Module: $module"; then
                    ((migration_count++))
                else
                    ((failed_count++))
                fi
            fi
        fi
    done
    
    # Optional improvements (only in production)
    if [ "$(get_config "environment")" = "production" ]; then
        print_info "Installing optional improvements..."
        
        local improvements=(
            "database/performance_optimization.sql:Performance optimizations"
            "database/security_improvements.sql:Security improvements"
            "database/audit_logging.sql:Audit logging"
        )
        
        for improvement_info in "${improvements[@]}"; do
            IFS=':' read -r improvement_file improvement_desc <<< "$improvement_info"
            
            if [ -f "$APP_DIR/$improvement_file" ]; then
                execute_sql_file "$APP_DIR/$improvement_file" "$improvement_desc"
                # Don't fail on optional improvements
            fi
        done
    fi
    
    # Run version-specific migrations
    run_version_migrations
    
    # Summary
    print_header "Migration Summary"
    print_info "Total migrations run: $migration_count"
    
    if [ $failed_count -gt 0 ]; then
        print_error "Failed migrations: $failed_count"
        return 1
    else
        print_status "All migrations completed successfully"
        return 0
    fi
}

# Run version-specific migrations
# Usage: run_version_migrations
run_version_migrations() {
    local version=$(get_config "deployment.version")
    
    print_info "Running version $version specific migrations..."
    
    # Version 2.3.0 specific migrations
    if [ "$version" = "2.3.0" ]; then
        # Make yfc_sellers.password_hash nullable
        if table_exists "yfc_sellers"; then
            print_info "Updating yfc_sellers table for YFAuth integration..."
            mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
                -e "ALTER TABLE yfc_sellers MODIFY password_hash VARCHAR(255) NULL" 2>/dev/null || true
        fi
        
        # Create global communication channels
        if table_exists "communication_channels"; then
            print_info "Creating global communication channels..."
            mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" <<EOF
INSERT IGNORE INTO communication_channels (name, type, description, created_at)
VALUES 
    ('Support', 'public', 'Get help and support from the community', NOW()),
    ('Selling Tips', 'public', 'Share and learn selling strategies', NOW());
EOF
        fi
    fi
}

# Backup database
# Usage: backup_database [backup_file]
backup_database() {
    local backup_file="${1:-}"
    local backup_dir="${BACKUP_DIR:-/var/backups/yfevents}"
    
    if [ -z "$backup_file" ]; then
        ensure_directory "$backup_dir"
        backup_file="$backup_dir/yfevents_$(date +%Y%m%d_%H%M%S).sql"
    fi
    
    print_info "Backing up database to: $backup_file"
    
    if mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction --routines --triggers \
        "$DB_DATABASE" > "$backup_file" 2>/dev/null; then
        
        # Compress backup
        gzip "$backup_file"
        print_status "Database backed up successfully: ${backup_file}.gz"
        echo "${backup_file}.gz"
    else
        print_error "Database backup failed"
        rm -f "$backup_file"
        return 1
    fi
}

# Restore database from backup
# Usage: restore_database <backup_file>
restore_database() {
    local backup_file="$1"
    
    if [ ! -f "$backup_file" ]; then
        print_error "Backup file not found: $backup_file"
        return 1
    fi
    
    print_warning "This will overwrite the current database!"
    if ! confirm "Continue with restore?"; then
        print_info "Restore cancelled"
        return 1
    fi
    
    print_info "Restoring database from: $backup_file"
    
    # Handle compressed files
    local sql_file="$backup_file"
    if [[ "$backup_file" =~ \.gz$ ]]; then
        sql_file="${backup_file%.gz}"
        gunzip -c "$backup_file" > "$sql_file"
    fi
    
    if mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$sql_file"; then
        print_status "Database restored successfully"
        
        # Clean up uncompressed file if we created it
        if [ "$sql_file" != "$backup_file" ]; then
            rm -f "$sql_file"
        fi
        
        return 0
    else
        print_error "Database restore failed"
        return 1
    fi
}

# Get database statistics
# Usage: show_database_stats
show_database_stats() {
    print_info "Database Statistics:"
    
    # Table count
    local table_count=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE'" \
        -s --skip-column-names 2>/dev/null || echo "0")
    
    echo "  - Total tables: $table_count"
    
    # Database size
    local db_size=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        -e "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' 
            FROM information_schema.tables WHERE table_schema='$DB_DATABASE'" \
        -s --skip-column-names 2>/dev/null || echo "0")
    
    echo "  - Database size: ${db_size}MB"
    
    # Check for key tables
    if table_exists "yfa_auth_users"; then
        local user_count=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
            -e "SELECT COUNT(*) FROM yfa_auth_users" -s --skip-column-names 2>/dev/null || echo "0")
        echo "  - Total users: $user_count"
    fi
    
    if table_exists "events"; then
        local event_count=$(mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
            -e "SELECT COUNT(*) FROM events" -s --skip-column-names 2>/dev/null || echo "0")
        echo "  - Total events: $event_count"
    fi
}