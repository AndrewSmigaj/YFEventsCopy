#!/bin/bash
# YFEvents Deployment Configuration Loader
# Loads and merges deployment configurations

# Color codes (imported from common.sh if available)
if [ -f "$(dirname "$0")/common.sh" ]; then
    source "$(dirname "$0")/common.sh"
else
    RED='\033[0;31m'
    GREEN='\033[0;32m'
    YELLOW='\033[1;33m'
    BLUE='\033[0;34m'
    NC='\033[0m'
fi

# Configuration paths
CONFIG_DIR="${CONFIG_DIR:-/var/www/yfevents/config/deployment}"
MAIN_CONFIG="$CONFIG_DIR/deployment.yaml"
ENV_CONFIG=""
VERSION_CONFIG=""

# Parsed configuration (associative arrays)
declare -A CONFIG
declare -A ENV_CONFIG_DATA
declare -A VERSION_CONFIG_DATA

# Load YAML configuration file
# Usage: load_yaml_config <file_path> <array_name>
load_yaml_config() {
    local file="$1"
    local -n config_array=$2
    
    if [ ! -f "$file" ]; then
        print_error "Configuration file not found: $file"
        return 1
    fi
    
    # Initialize variables for nested YAML parsing
    local line key value prev_key=""
    local -a parent_keys=()
    local current_indent=0
    local prev_indent=0
    
    # Process YAML file line by line
    while IFS= read -r line || [[ -n "$line" ]]; do
        # Skip empty lines and comments
        [[ -z "$line" ]] && continue
        [[ "$line" =~ ^[[:space:]]*# ]] && continue
        
        # Count leading spaces for indentation
        local indent=0
        if [[ "$line" =~ ^([[:space:]]*) ]]; then
            indent=${#BASH_REMATCH[1]}
        fi
        
        # Extract key and value
        if [[ "$line" =~ ^[[:space:]]*([^:]+):[[:space:]]*(.*)$ ]]; then
            key="${BASH_REMATCH[1]}"
            value="${BASH_REMATCH[2]}"
            
            # Trim whitespace from key
            key=$(echo "$key" | xargs)
            
            # Handle indentation changes
            if (( indent < current_indent )); then
                # Moving back up the hierarchy
                local levels_up=$(( (current_indent - indent) / 2 ))
                for ((i=0; i<levels_up && ${#parent_keys[@]} > 0; i++)); do
                    unset 'parent_keys[-1]'
                done
            elif (( indent > current_indent )); then
                # Moving deeper into hierarchy - the previous key becomes a parent
                if [ -n "$prev_key" ]; then
                    parent_keys+=("$prev_key")
                fi
            fi
            current_indent=$indent
            
            # Skip list items (starting with -)
            [[ "$key" =~ ^- ]] && continue
            
            # Build the full key path
            local full_key=""
            if [ ${#parent_keys[@]} -gt 0 ]; then
                full_key=$(IFS=.; echo "${parent_keys[*]}")
                full_key="${full_key}.${key}"
            else
                full_key="$key"
            fi
            
            # Only store if we have a value
            if [ -n "$value" ]; then
                # Remove quotes from value
                value="${value%\"}"
                value="${value#\"}"
                value="${value%\'}"
                value="${value#\'}"
                
                # Handle environment variable substitution
                if [[ "$value" =~ \$\{([^}]+)\} ]]; then
                    local var_expr="${BASH_REMATCH[1]}"
                    if [[ "$var_expr" =~ ([^:-]+)(:-(.+))? ]]; then
                        local var_name="${BASH_REMATCH[1]}"
                        local default_val="${BASH_REMATCH[3]}"
                        
                        if [ -n "${!var_name:-}" ]; then
                            value="${!var_name}"
                        elif [ -n "$default_val" ]; then
                            value="$default_val"
                        else
                            value=""
                        fi
                    fi
                fi
                
                # Store in array
                config_array["$full_key"]="$value"
                print_debug "Loaded config: $full_key = $value"
            fi
            
            # Remember this key for potential parent use
            prev_key="$key"
        fi
    done < "$file"
    
    return 0
}

# Get configuration value
# Usage: get_config <key> [default_value]
get_config() {
    local key="$1"
    local default="${2:-}"
    
    # Check environment-specific config first
    if [[ -v ENV_CONFIG_DATA[$key] ]] && [ -n "${ENV_CONFIG_DATA[$key]}" ]; then
        echo "${ENV_CONFIG_DATA[$key]}"
    # Then check main config
    elif [[ -v CONFIG[$key] ]] && [ -n "${CONFIG[$key]}" ]; then
        echo "${CONFIG[$key]}"
    # Return default
    else
        echo "$default"
    fi
}

# Load all configurations
# Usage: load_all_configs <environment>
load_all_configs() {
    local environment="${1:-production}"
    
    print_info "Loading deployment configurations..."
    
    # Enable debug mode if requested
    if [ "${VERBOSE:-false}" = true ] || [ "${DEBUG:-false}" = true ]; then
        export DEBUG=true
    fi
    
    # Load main configuration
    if load_yaml_config "$MAIN_CONFIG" CONFIG; then
        print_status "Loaded main configuration"
        
        # Show loaded values in debug mode
        if [ "${DEBUG:-false}" = true ]; then
            echo "  Main config keys loaded: ${#CONFIG[@]}"
            echo "  Sample values:"
            echo "    deployment.version = ${CONFIG[deployment.version]:-<not set>}"
            echo "    deployment.repository.url = ${CONFIG[deployment.repository.url]:-<not set>}"
        fi
    else
        print_error "Failed to load main configuration"
        return 1
    fi
    
    # Load environment-specific configuration
    ENV_CONFIG="$CONFIG_DIR/environments/${environment}.yaml"
    if [ -f "$ENV_CONFIG" ]; then
        if load_yaml_config "$ENV_CONFIG" ENV_CONFIG_DATA; then
            print_status "Loaded $environment environment configuration"
        fi
    else
        print_warning "No environment configuration found for: $environment"
    fi
    
    # Load version-specific configuration
    local version=$(get_config "deployment.version" "latest")
    VERSION_CONFIG="$CONFIG_DIR/versions/${version}.yaml"
    if [ -f "$VERSION_CONFIG" ]; then
        if load_yaml_config "$VERSION_CONFIG" VERSION_CONFIG_DATA; then
            print_status "Loaded version $version configuration"
        fi
    else
        # Try latest.yaml as fallback
        VERSION_CONFIG="$CONFIG_DIR/versions/latest.yaml"
        if [ -f "$VERSION_CONFIG" ]; then
            load_yaml_config "$VERSION_CONFIG" VERSION_CONFIG_DATA
        fi
    fi
    
    return 0
}

# Validate required configuration
# Usage: validate_config
validate_config() {
    local errors=0
    
    print_info "Validating configuration..."
    
    # Check required fields
    local required_fields=(
        "deployment.version"
        "deployment.repository.url"
        "deployment.database.name"
        "deployment.database.user"
    )
    
    for field in "${required_fields[@]}"; do
        if [ -z "$(get_config "$field")" ]; then
            print_error "Missing required configuration: $field"
            ((errors++))
        fi
    done
    
    # Check database password (must be provided via environment)
    if [ -z "$DB_PASSWORD" ] && [ -z "$(get_config "deployment.database.password")" ]; then
        print_error "Database password not provided (set DB_PASSWORD environment variable)"
        ((errors++))
    fi
    
    if [ $errors -gt 0 ]; then
        print_error "Configuration validation failed with $errors errors"
        return 1
    fi
    
    print_status "Configuration validated successfully"
    return 0
}

# Export configuration as environment variables
# Usage: export_config_env
export_config_env() {
    # Database configuration
    export DB_HOST=$(get_config "deployment.database.host" "localhost")
    export DB_DATABASE=$(get_config "deployment.database.name" "yakima_finds")
    export DB_USERNAME=$(get_config "deployment.database.user" "yfevents")
    export DB_PASSWORD=$(get_config "deployment.database.password" "$DB_PASSWORD")
    
    # Repository configuration
    export REPO_URL=$(get_config "deployment.repository.url")
    export REPO_BRANCH=$(get_config "deployment.repository.branch" "refactor/unified-structure")
    
    # Application configuration
    export APP_VERSION=$(get_config "deployment.version")
    export APP_ENV=$(get_config "environment" "production")
    export APP_DIR="${APP_DIR:-/var/www/yfevents}"
}

# Display configuration summary
# Usage: show_config_summary
show_config_summary() {
    echo ""
    echo "Configuration Summary:"
    echo "====================="
    echo "Environment: $(get_config "environment" "production")"
    echo "Version: $(get_config "deployment.version")"
    echo "Repository: $(get_config "deployment.repository.url")"
    echo "Branch: $(get_config "deployment.repository.branch")"
    echo "Database: $(get_config "deployment.database.name")"
    echo "App Directory: $APP_DIR"
    echo ""
}