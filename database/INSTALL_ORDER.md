# Database Installation Order

## Overview
Install database schemas in this order to handle dependencies correctly.

## Installation Steps

### 1. Core Tables (Required)
```bash
# Calendar and events system
mysql -u username -p dbname < database/calendar_schema.sql

# Shop system
mysql -u username -p dbname < database/shop_claim_system.sql

# Module support system
mysql -u username -p dbname < database/modules_schema.sql
```

### 2. Communication Systems
```bash
# Choose ONE of these (not both):
# Option A: Fixed version (recommended)
mysql -u username -p dbname < database/communication_schema_fixed.sql

# Option B: Original version
# mysql -u username -p dbname < database/communication_schema.sql

# Chat system
mysql -u username -p dbname < database/yfchat_schema.sql
```

### 3. System Features
```bash
# Batch processing for queues
mysql -u username -p dbname < database/batch_processing_schema.sql

# Intelligent scraper
mysql -u username -p dbname < database/intelligent_scraper_schema.sql
```

### 4. Module Schemas
```bash
# YFAuth - Authentication module
mysql -u username -p dbname < modules/yfauth/database/schema.sql

# YFClaim - Estate sales module  
mysql -u username -p dbname < modules/yfclaim/database/schema.sql

# YFTheme - Theme system
mysql -u username -p dbname < modules/yftheme/database/schema.sql

# YFClassifieds - If using separate from YFClaim
# mysql -u username -p dbname < modules/yfclassifieds/database/schema.sql
```

### 5. Improvements (Optional)
```bash
# Performance optimizations (indexes, etc.)
mysql -u username -p dbname < database/performance_optimization.sql

# Security improvements
mysql -u username -p dbname < database/security_improvements.sql

# Audit logging
mysql -u username -p dbname < database/audit_logging.sql
```

## Notes

- **Foreign Keys**: calendar_schema.sql must be run before tables that reference events
- **Module Tables**: Some modules may share tables (YFClaim and YFClassifieds)
- **User Tables**: Production uses main `users` table, not module-specific versions
- **Improvements**: Can be applied after main schema is working

## Quick Install (All Core)
```bash
#!/bin/bash
DB_NAME="your_database"
DB_USER="your_user"

for schema in calendar_schema.sql shop_claim_system.sql modules_schema.sql communication_schema_fixed.sql yfchat_schema.sql batch_processing_schema.sql intelligent_scraper_schema.sql; do
    echo "Installing $schema..."
    mysql -u $DB_USER -p $DB_NAME < database/$schema
done
```

## Verification
After installation, verify tables exist:
```sql
SHOW TABLES;
-- Should show: events, local_shops, calendar_sources, etc.
```