# Database Installation Order

## Overview
Install database schemas in this three-tier order to handle dependencies correctly.

## Three-Tier Architecture

### Tier 1: Core Infrastructure (Required First)
These provide foundational tables that other schemas depend on.

```bash
# Base tables for events, shops, and categories
mysql -u username -p dbname < database/calendar_schema.sql

# Authentication system (many features depend on yfa_auth_users)
mysql -u username -p dbname < modules/yfauth/database/schema.sql
```

### Tier 2: Core Application Features
These depend on infrastructure tables and must be installed second.

```bash
# Shop claiming system (depends on yfa_auth_users)
mysql -u username -p dbname < database/shop_claim_system.sql

# Module support system
mysql -u username -p dbname < database/modules_schema.sql
```

# Communication system (depends on yfa_auth_users)
# This is the main messaging system used by seller dashboard and other features
mysql -u username -p dbname < database/communication_schema_fixed.sql

# Note: yfchat_schema.sql is NOT used - it expects a 'users' table that doesn't exist
# The communication_schema_fixed.sql provides all chat/messaging functionality

# Batch processing for queues
mysql -u username -p dbname < database/batch_processing_schema.sql

# Intelligent scraper
mysql -u username -p dbname < database/intelligent_scraper_schema.sql
```

### Tier 3: Optional Modules
These are truly optional and can be installed as needed.

```bash
# YFClaim - Estate sales module  
mysql -u username -p dbname < modules/yfclaim/database/schema.sql

# YFTheme - Theme system
mysql -u username -p dbname < modules/yftheme/database/schema.sql

# YFChat Subset - Admin-seller chat (simplified)
mysql -u username -p dbname < database/yfchat_subset.sql

# YFClassifieds - If using separate from YFClaim
# mysql -u username -p dbname < modules/yfclassifieds/database/schema.sql
```

### 5. Seed Data
```bash
# Create the two global chat rooms (Support and Selling Tips)
php scripts/seed_chat_rooms.php
```

### 6. Improvements (Optional)
```bash
# Performance optimizations (indexes, etc.)
mysql -u username -p dbname < database/performance_optimization.sql

# Security improvements
mysql -u username -p dbname < database/security_improvements.sql

# Audit logging
mysql -u username -p dbname < database/audit_logging.sql
```

## Important Notes

### Why Three Tiers?
- **Infrastructure**: YFAuth is not truly "optional" - many core features depend on `yfa_auth_users`
- **Dependencies**: shop_claim_system, communication_schema, and others have foreign keys to auth tables
- **Architecture**: This reflects that authentication is foundational infrastructure, not a plugin

### Key Dependencies
- `shop_claim_system.sql` → requires `yfa_auth_users` (admin assignments)
- `communication_schema_fixed.sql` → requires `yfa_auth_users` (messaging)
- `local_shops` → requires `shop_categories` and `shop_owners` (same file)
- Module schemas → may require various core tables

## Quick Install Script
```bash
#!/bin/bash
DB_NAME="your_database"
DB_USER="your_user"

# Tier 1: Infrastructure
echo "Installing infrastructure schemas..."
mysql -u $DB_USER -p $DB_NAME < database/calendar_schema.sql
mysql -u $DB_USER -p $DB_NAME < modules/yfauth/database/schema.sql

# Tier 2: Core Application
echo "Installing core application schemas..."
for schema in shop_claim_system.sql modules_schema.sql communication_schema_fixed.sql batch_processing_schema.sql intelligent_scraper_schema.sql; do
    echo "Installing $schema..."
    mysql -u $DB_USER -p $DB_NAME < database/$schema
done

# Tier 3: Optional Modules (uncomment as needed)
# echo "Installing optional modules..."
# mysql -u $DB_USER -p $DB_NAME < modules/yfclaim/database/schema.sql
```

## Verification
After installation, verify tables exist:
```sql
SHOW TABLES;
-- Should show: events, local_shops, calendar_sources, etc.
```