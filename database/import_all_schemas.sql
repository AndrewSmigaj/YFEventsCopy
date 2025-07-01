-- Database Import Script with Correct Order
-- Generated on: 2025-06-30 19:28:14

-- Run this script to import all schemas in the correct dependency order

-- Step 1: Create database if not exists
CREATE DATABASE IF NOT EXISTS yakima_finds;
USE yakima_finds;

-- Step 2: Import auth module first (creates yfa_auth_users)
SOURCE modules/yfauth/database/schema.sql;

-- Step 3: Import base schemas without user dependencies
SOURCE database/calendar_schema.sql;
SOURCE database/modules_schema.sql;

-- Step 4: Import other modules
SOURCE modules/yfclaim/database/schema.sql;
SOURCE modules/yfclassifieds/database/schema.sql;
SOURCE modules/yftheme/database/schema.sql;

-- Step 5: Import schemas with user dependencies (now updated to use yfa_auth_users)
SOURCE database/yfchat_schema.sql;
SOURCE database/communication_schema_fixed.sql;
SOURCE database/shop_claim_system.sql;

-- Step 6: Import audit and optimization schemas
SOURCE database/audit_logging.sql;
SOURCE database/performance_optimization.sql;
SOURCE database/security_improvements.sql;

-- Step 7: Import remaining schemas
SOURCE database/batch_processing_schema.sql;
SOURCE database/intelligent_scraper_schema.sql;

-- Step 8: Show results
SELECT 'Import complete!' as Status;
SELECT COUNT(*) as 'Total Tables' FROM information_schema.tables WHERE table_schema = DATABASE();
SHOW TABLES;