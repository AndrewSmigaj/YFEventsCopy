# Table Analysis - YFEvents Database

## Core Tables (From SQL Files)

### Main Application Tables
- `events` - Main events table
- `event_categories` - Event categorization
- `event_images` - Event photos
- `calendar_sources` - External calendar sources
- `local_shops` - Shop listings
- `shop_categories` - Shop categorization
- `shop_owners` - Shop ownership info
- `shop_images` - Shop photos

### Communication/Chat System
- `chat_conversations`
- `chat_messages`
- `chat_participants`
- `chat_attachments`
- `chat_notifications`
- `communication_channels`
- `communication_messages`
- `communication_participants`

### Module Tables

#### YFAuth Module (Multiple versions found!)
- `auth_users` / `yfa_users` / `yfa_auth_users` - User accounts
- `auth_roles` / `yfa_roles` / `yfa_auth_roles` - User roles
- `auth_permissions` / `yfa_permissions` / `yfa_auth_permissions` - Permissions
- `auth_user_roles` / `yfa_user_roles` / `yfa_auth_user_roles` - User-role mapping
- `auth_sessions` / `yfa_sessions` / `yfa_auth_sessions` - Active sessions

#### YFClaim Module
- `yfc_sellers` - Estate sale sellers
- `yfc_buyers` - Buyers
- `yfc_items` - Items for sale
- `yfc_sales` - Sales events
- `yfc_offers` - Offers on items
- `yfc_item_images` - Item photos

#### YFTheme Module
- `theme_variables` - Theme customization
- `theme_presets` - Saved themes
- `theme_history` - Theme change history

#### YFClassifieds Module
- `yfc_items` - Classified items (conflicts with YFClaim!)
- `yfc_categories` - Item categories

### System/Admin Tables
- `modules` - Installed modules
- `module_settings` - Module configuration
- `module_migrations` - Migration tracking
- `intelligent_scraper_*` - Web scraping system
- `audit_log` / `comprehensive_audit_log` - Activity tracking

## 🚨 Critical Issues Found:

### 1. **Table Name Conflicts**
- YFAuth has 3 different prefixes: `auth_`, `yfa_`, `yfa_auth_`
- YFClaim and YFClassifieds both use `yfc_` prefix
- Multiple audit log tables

### 2. **The Refactor Code Expects**:
Based on the repositories:
- `events` table (EventRepository)
- `local_shops` table (ShopRepository)  
- Standard names without module prefixes for core tables

### 3. **Module Integration Confusion**
- Modules have overlapping functionality
- No clear naming convention
- Some tables might not exist in production

## What This Means:

1. **We CANNOT blindly merge schemas** - There are conflicts
2. **Module tables are a mess** - Multiple versions of same functionality
3. **Production might use different names** - We need to verify

## Recommended Actions:

1. **Get from production droplet**:
   ```bash
   mysql -u root -p -e "USE yakima_finds; SHOW TABLES;" > actual_tables.txt
   ```

2. **Compare with our schemas** to see:
   - Which table names are actually used
   - Which modules are actually installed
   - Which version of auth tables exists

3. **Check the refactor config**:
   ```bash
   grep -r "yfa_\|yfc_\|auth_" /path/to/production/refactor/
   ```

Without knowing which exact tables exist in production, we're guessing!