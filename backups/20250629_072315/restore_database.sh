#!/bin/bash

# YFEvents Database Restore Script
# Generated: 2025-06-29 07:23:15
# This script restores the YFEvents database from backup

# Database credentials from .env file
DB_HOST="localhost"
DB_NAME="yakima_finds"
DB_USER="yfevents"
DB_PASS="yfevents_pass"

# Backup file path
BACKUP_DIR="/home/robug/YFEvents/backups/20250629_072315"
BACKUP_FILE="$BACKUP_DIR/database_backup.sql"

# Color codes for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}YFEvents Database Restore Script${NC}"
echo -e "${YELLOW}==================================${NC}"
echo ""

# Check if backup file exists
if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}Error: Backup file not found!${NC}"
    echo "Expected location: $BACKUP_FILE"
    exit 1
fi

# Get file info
FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
FILE_DATE=$(stat -c %y "$BACKUP_FILE" | cut -d' ' -f1,2 | cut -d'.' -f1)

echo "Backup file found:"
echo -e "  File: ${YELLOW}$BACKUP_FILE${NC}"
echo -e "  Size: ${YELLOW}$FILE_SIZE${NC}"
echo -e "  Created: ${YELLOW}$FILE_DATE${NC}"
echo ""

# Warning message
echo -e "${RED}WARNING: This will OVERWRITE existing data in the database!${NC}"
echo -e "Database: ${YELLOW}$DB_NAME${NC} on ${YELLOW}$DB_HOST${NC}"
echo ""
read -p "Are you sure you want to restore? (yes/no): " -n 3 -r
echo ""

if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    echo "Restore cancelled."
    exit 1
fi

echo ""
echo "Starting restore process..."

# Perform the restore
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE" 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}Database restored successfully!${NC}"
    
    # Show summary of restored tables
    echo ""
    echo "Tables restored:"
    grep -E "^-- Table structure for table" "$BACKUP_FILE" | sed 's/-- Table structure for table `/  - /g' | sed 's/`//g'
else
    echo -e "${RED}Database restore failed!${NC}"
    echo "Please check your database credentials and permissions."
    exit 1
fi

echo ""
echo "Restore completed at: $(date +"%Y-%m-%d %H:%M:%S")"