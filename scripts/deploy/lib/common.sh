#!/bin/bash
# YFEvents Deployment Common Functions
# Shared utilities for all deployment scripts

# Color codes for output
export RED='\033[0;31m'
export GREEN='\033[0;32m'
export YELLOW='\033[1;33m'
export BLUE='\033[0;34m'
export CYAN='\033[0;36m'
export MAGENTA='\033[0;35m'
export NC='\033[0m' # No Color

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

print_debug() {
    if [ "${DEBUG:-false}" = "true" ]; then
        echo -e "${CYAN}[D]${NC} $1"
    fi
}

print_success() {
    echo -e "${GREEN}[✓]${NC} ${GREEN}$1${NC}"
}

print_header() {
    local text="$1"
    local width=${2:-60}
    local line=$(printf '%*s' "$width" | tr ' ' '=')
    
    echo ""
    echo "$line"
    echo "$text"
    echo "$line"
    echo ""
}

# Prompt for user input
# Usage: prompt "Enter value: " [default_value]
prompt() {
    local message="$1"
    local default="${2:-}"
    local response
    
    if [ -n "$default" ]; then
        read -p "$message [$default]: " response
        echo "${response:-$default}"
    else
        read -p "$message: " response
        echo "$response"
    fi
}

# Prompt for password (hidden input)
# Usage: prompt_password "Enter password"
prompt_password() {
    local message="$1"
    local password
    
    read -sp "$message: " password
    echo ""
    echo "$password"
}

# Confirm action
# Usage: confirm "Are you sure?" [default_yes]
confirm() {
    local message="$1"
    local default="${2:-n}"
    local response
    
    if [ "$default" = "y" ]; then
        read -p "$message (Y/n): " response
        response="${response:-y}"
    else
        read -p "$message (y/N): " response
        response="${response:-n}"
    fi
    
    [[ "$response" =~ ^[Yy]$ ]]
}

# Check if command exists
# Usage: command_exists <command>
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Execute command with dry-run support
# Usage: dry_run_exec "description" command [args...]
dry_run_exec() {
    local description="$1"
    shift
    
    if [ "${DRY_RUN:-false}" = true ]; then
        print_info "[DRY RUN] Would execute: $description"
        print_debug "Command: $*"
        return 0
    else
        "$@"
    fi
}

# Check if running as root
# Usage: require_root
require_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root or with sudo"
        exit 1
    fi
}

# Check if user exists
# Usage: user_exists <username>
user_exists() {
    id "$1" >/dev/null 2>&1
}

# Create directory if it doesn't exist
# Usage: ensure_directory <path> [permissions] [owner]
ensure_directory() {
    local path="$1"
    local perms="${2:-755}"
    local owner="${3:-}"
    
    if [ ! -d "$path" ]; then
        mkdir -p "$path"
        chmod "$perms" "$path"
        
        if [ -n "$owner" ]; then
            chown "$owner" "$path"
        fi
        
        print_debug "Created directory: $path"
    fi
}

# Backup file or directory
# Usage: backup_item <path>
backup_item() {
    local path="$1"
    local backup_dir="${BACKUP_DIR:-/var/backups/yfevents}"
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_name="$(basename "$path").backup.$timestamp"
    
    ensure_directory "$backup_dir"
    
    if [ -e "$path" ]; then
        cp -r "$path" "$backup_dir/$backup_name"
        print_debug "Backed up $path to $backup_dir/$backup_name"
        echo "$backup_dir/$backup_name"
    fi
}

# Run command as specific user
# Usage: run_as_user <username> <command>
run_as_user() {
    local username="$1"
    shift
    local command="$@"
    
    if [ "$EUID" -eq 0 ]; then
        sudo -u "$username" bash -c "$command"
    else
        bash -c "$command"
    fi
}

# Check system requirements
# Usage: check_requirement <command> <package_name>
check_requirement() {
    local command="$1"
    local package="${2:-$1}"
    
    if command_exists "$command"; then
        print_status "$package is installed"
        return 0
    else
        print_error "$package is not installed"
        return 1
    fi
}

# Get PHP version
# Usage: get_php_version
get_php_version() {
    if command_exists php; then
        php -r "echo PHP_VERSION;"
    else
        echo "0.0.0"
    fi
}

# Compare versions
# Usage: version_compare <version1> <operator> <version2>
# Operators: lt, le, eq, ge, gt
version_compare() {
    local v1="$1"
    local op="$2"
    local v2="$3"
    
    # Convert versions to comparable format
    local v1_normalized=$(echo "$v1" | awk -F. '{ printf("%d%03d%03d", $1, $2, $3) }')
    local v2_normalized=$(echo "$v2" | awk -F. '{ printf("%d%03d%03d", $1, $2, $3) }')
    
    case "$op" in
        "lt") [ "$v1_normalized" -lt "$v2_normalized" ] ;;
        "le") [ "$v1_normalized" -le "$v2_normalized" ] ;;
        "eq") [ "$v1_normalized" -eq "$v2_normalized" ] ;;
        "ge") [ "$v1_normalized" -ge "$v2_normalized" ] ;;
        "gt") [ "$v1_normalized" -gt "$v2_normalized" ] ;;
        *) return 1 ;;
    esac
}

# Wait for service to be ready
# Usage: wait_for_service <service_name> [timeout]
wait_for_service() {
    local service="$1"
    local timeout="${2:-30}"
    local elapsed=0
    
    print_info "Waiting for $service to be ready..."
    
    while ! systemctl is-active --quiet "$service"; do
        sleep 1
        ((elapsed++))
        
        if [ $elapsed -ge $timeout ]; then
            print_error "$service failed to start within $timeout seconds"
            return 1
        fi
    done
    
    print_status "$service is ready"
    return 0
}

# Generate random string
# Usage: generate_random_string [length]
generate_random_string() {
    local length="${1:-32}"
    openssl rand -hex "$length" | cut -c1-"$length"
}

# Calculate file/directory size
# Usage: get_size <path>
get_size() {
    local path="$1"
    
    if [ -e "$path" ]; then
        du -sh "$path" | cut -f1
    else
        echo "0"
    fi
}

# Clean up old backups
# Usage: cleanup_old_backups <directory> <keep_count>
cleanup_old_backups() {
    local dir="$1"
    local keep="${2:-3}"
    
    if [ -d "$dir" ]; then
        # Find and remove old backups, keeping the most recent ones
        find "$dir" -name "*.backup.*" -type f -printf '%T@ %p\n' | \
            sort -nr | \
            tail -n +$((keep + 1)) | \
            cut -d' ' -f2- | \
            xargs -r rm -f
            
        print_debug "Cleaned up old backups in $dir (kept $keep most recent)"
    fi
}

# Trap cleanup function
# Usage: setup_cleanup_trap
cleanup_on_exit() {
    local exit_code=$?
    
    if [ $exit_code -ne 0 ]; then
        print_error "Script failed with exit code: $exit_code"
        
        # Perform any necessary cleanup
        if [ -n "${TEMP_DIR:-}" ] && [ -d "$TEMP_DIR" ]; then
            rm -rf "$TEMP_DIR"
        fi
    fi
}

setup_cleanup_trap() {
    trap cleanup_on_exit EXIT
}

# Export all functions
export -f print_status print_error print_warning print_info print_debug
export -f prompt prompt_password confirm
export -f command_exists require_root user_exists
export -f ensure_directory backup_item run_as_user
export -f check_requirement get_php_version version_compare
export -f wait_for_service generate_random_string get_size
export -f cleanup_old_backups setup_cleanup_trap