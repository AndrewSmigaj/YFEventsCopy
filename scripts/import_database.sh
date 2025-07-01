#!/bin/bash
# Database import script with proper error handling

DB_USER="yfevents"
DB_PASS="yfevents_pass"
DB_NAME="yakima_finds"
BASE_DIR="/mnt/d/YFEventsCopy"

# Function to import SQL file with error checking
import_sql() {
    local file=$1
    local description=$2
    
    echo "Importing $description..."
    if [ -f "$BASE_DIR/$file" ]; then
        mysql -u $DB_USER -p$DB_PASS $DB_NAME < "$BASE_DIR/$file" 2>&1
        if [ $? -eq 0 ]; then
            echo "✓ Success: $description"
        else
            echo "✗ Failed: $description"
            exit 1
        fi
    else
        echo "✗ File not found: $file"
        exit 1
    fi
}

# Drop and recreate database
echo "Recreating database..."
mysql -u $DB_USER -p$DB_PASS -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME;" 2>&1

# Import schemas in correct order
cd $BASE_DIR

# Step 1: Import auth module first (creates yfa_auth_users)
import_sql "modules/yfauth/database/schema.sql" "YFAuth authentication tables"

# Step 2: Import base schemas without user dependencies
import_sql "database/calendar_schema.sql" "Calendar and events tables"
import_sql "database/modules_schema.sql" "Module management tables"

# Step 3: Import other modules
import_sql "modules/yfclaim/database/schema.sql" "YFClaim module tables"
# Skipping yfclassifieds - it only adds optional columns to existing tables
import_sql "modules/yftheme/database/schema.sql" "YFTheme module tables"

# Step 4: Import schemas with user dependencies (now updated to use yfa_auth_users)
import_sql "database/yfchat_schema.sql" "YFChat communication tables"
import_sql "database/communication_schema_fixed.sql" "Communication tables"
import_sql "database/shop_claim_system.sql" "Shop claim system tables"

# Step 5: Import audit and optimization schemas
import_sql "database/audit_logging.sql" "Audit logging tables"
import_sql "database/performance_optimization.sql" "Performance optimization"
import_sql "database/security_improvements.sql" "Security improvements"

# Step 6: Import remaining schemas
import_sql "database/batch_processing_schema.sql" "Batch processing tables"
import_sql "database/intelligent_scraper_schema.sql" "Intelligent scraper tables"

# Show results
echo ""
echo "Database import complete!"
echo "Total tables created:"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$DB_NAME';" 2>&1 | grep -v "Warning"

echo ""
echo "Tables by prefix:"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "
SELECT 
    CASE 
        WHEN table_name LIKE 'yfa_%' THEN 'YFAuth'
        WHEN table_name LIKE 'chat_%' THEN 'YFChat'
        WHEN table_name LIKE 'communication_%' THEN 'Communication'
        WHEN table_name LIKE 'yfclaim_%' THEN 'YFClaim'
        WHEN table_name LIKE 'shop_%' THEN 'Shop'
        WHEN table_name LIKE 'events%' OR table_name LIKE 'calendar_%' THEN 'Calendar/Events'
        ELSE 'Other'
    END as module,
    COUNT(*) as table_count
FROM information_schema.tables 
WHERE table_schema = '$DB_NAME'
GROUP BY module
ORDER BY module;" 2>&1 | grep -v "Warning"