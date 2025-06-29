#!/bin/bash

# YFEvents Database Backup Script
# Generated: 2025-06-29 07:23:15
# This script exports all YFEvents related tables

# Database credentials from .env file
DB_HOST="localhost"
DB_NAME="yakima_finds"
DB_USER="yfevents"
DB_PASS="yfevents_pass"

# Backup file path
BACKUP_DIR="/home/robug/YFEvents/backups/20250629_072315"
BACKUP_FILE="$BACKUP_DIR/database_backup.sql"
TIMESTAMP=$(date +"%Y-%m-%d %H:%M:%S")

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}YFEvents Database Backup Script${NC}"
echo -e "${YELLOW}================================${NC}"
echo "Starting backup at: $TIMESTAMP"
echo ""

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Start the backup file with header comments
cat > "$BACKUP_FILE" << EOF
-- YFEvents Database Backup
-- Generated: $TIMESTAMP
-- Database: $DB_NAME
-- Host: $DB_HOST
-- 
-- This backup includes all YFEvents related tables:
-- - yfc_* (YFClaim module tables)
-- - auth_* (Authentication tables)
-- - shop_* (Shop related tables)
-- - events (Events table)
-- - users (Users table)
-- - shops (Shops table)
-- ================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: \`$DB_NAME\`
--

EOF

# Function to backup tables with a specific prefix
backup_tables_with_prefix() {
    local prefix=$1
    echo -e "${GREEN}Backing up tables with prefix: ${prefix}*${NC}"
    
    # Get list of tables with this prefix
    tables=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SHOW TABLES LIKE '${prefix}%';" 2>/dev/null)
    
    if [ -z "$tables" ]; then
        echo -e "${YELLOW}No tables found with prefix: ${prefix}*${NC}"
        return
    fi
    
    # Backup each table
    for table in $tables; do
        echo -n "  - Backing up table: $table... "
        
        # Add table comment
        echo "" >> "$BACKUP_FILE"
        echo "-- --------------------------------------------------------" >> "$BACKUP_FILE"
        echo "" >> "$BACKUP_FILE"
        echo "--" >> "$BACKUP_FILE"
        echo "-- Table structure for table \`$table\`" >> "$BACKUP_FILE"
        echo "--" >> "$BACKUP_FILE"
        echo "" >> "$BACKUP_FILE"
        
        # Dump table structure and data
        mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" "$table" \
            --no-create-db \
            --complete-insert \
            --extended-insert \
            --single-transaction \
            --quick \
            --lock-tables=false \
            --add-drop-table \
            2>/dev/null >> "$BACKUP_FILE"
        
        if [ $? -eq 0 ]; then
            echo -e "${GREEN}Done${NC}"
        else
            echo -e "${RED}Failed${NC}"
        fi
    done
}

# Function to backup specific tables
backup_specific_table() {
    local table=$1
    echo -n "  - Backing up table: $table... "
    
    # Check if table exists
    table_exists=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -N -e "SHOW TABLES LIKE '$table';" 2>/dev/null)
    
    if [ -z "$table_exists" ]; then
        echo -e "${YELLOW}Table not found${NC}"
        return
    fi
    
    # Add table comment
    echo "" >> "$BACKUP_FILE"
    echo "-- --------------------------------------------------------" >> "$BACKUP_FILE"
    echo "" >> "$BACKUP_FILE"
    echo "--" >> "$BACKUP_FILE"
    echo "-- Table structure for table \`$table\`" >> "$BACKUP_FILE"
    echo "--" >> "$BACKUP_FILE"
    echo "" >> "$BACKUP_FILE"
    
    # Dump table structure and data
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" "$table" \
        --no-create-db \
        --complete-insert \
        --extended-insert \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --add-drop-table \
        2>/dev/null >> "$BACKUP_FILE"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}Done${NC}"
    else
        echo -e "${RED}Failed${NC}"
    fi
}

# Backup tables with prefixes
echo ""
echo "Backing up YFClaim module tables..."
backup_tables_with_prefix "yfc_"

echo ""
echo "Backing up Authentication tables..."
backup_tables_with_prefix "auth_"

echo ""
echo "Backing up Shop related tables..."
backup_tables_with_prefix "shop_"

echo ""
echo "Backing up Communication tables..."
backup_tables_with_prefix "communication_"

echo ""
echo "Backing up core tables..."
backup_specific_table "events"
backup_specific_table "users"
backup_specific_table "shops"

# Add footer to backup file
cat >> "$BACKUP_FILE" << EOF

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Backup completed at: $(date +"%Y-%m-%d %H:%M:%S")
EOF

# Check if backup file was created and has content
if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
    FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo ""
    echo -e "${GREEN}Backup completed successfully!${NC}"
    echo -e "Backup file: ${YELLOW}$BACKUP_FILE${NC}"
    echo -e "File size: ${YELLOW}$FILE_SIZE${NC}"
    echo ""
    
    # Show summary of backed up tables
    echo "Summary of backed up tables:"
    grep -E "^-- Table structure for table" "$BACKUP_FILE" | sed 's/-- Table structure for table `/  - /g' | sed 's/`//g'
else
    echo ""
    echo -e "${RED}Backup failed! No data was written to the backup file.${NC}"
    exit 1
fi

echo ""
echo "Backup completed at: $(date +"%Y-%m-%d %H:%M:%S")"