# Legacy File Migration Plan

## Overview
During the refactoring to fix authentication issues, we moved legacy admin and seller files to separate directories to create a clean separation between the old and new systems.

## Changes Made
1. Moved `/public/admin/` → `/public/admin-legacy/`
2. Moved `/public/seller/` → `/public/seller-legacy/`
3. Updated AdminDashboardController to use new paths
4. Router now handles all `/admin/*` and `/seller/*` paths

## Legacy Files Requiring Migration

### Admin Legacy Files (`/public/admin-legacy/`)

#### High Priority (Core Functionality)
- **events.php** - Event management interface → Migrate to AdminEventController
- **shops.php** - Shop management interface → Migrate to AdminShopController  
- **scrapers.php** - Event scraper configuration → Create AdminScraperController
- **users.php** - User management → Create AdminUserController

#### Medium Priority (Configuration)
- **email-config.php** - Email configuration → Create AdminEmailController
- **email-events.php** - Event email notifications → Merge with AdminEmailController
- **settings.php** - System settings → Create AdminSettingsController
- **theme.php** - Theme editor → Create AdminThemeController

#### Low Priority (Specialized Tools)
- **browser-scrapers.php** - Browser automation scrapers → Part of AdminScraperController
- **backup-config.php** - Backup configuration → Part of AdminSettingsController
- **email-upload.php** - Bulk email upload → Part of AdminEmailController
- **modules.php** - Module management → Create AdminModuleController

#### Can Be Removed
- **auth_check.php** - Old auth system (replaced by AuthController)
- **shops-original.php** - Backup file
- **users-original.php** - Backup file
- **scrapers-old.php** - Backup file

### Seller Legacy Files (`/public/seller-legacy/`)
- **login.php** - Static login page → Already routed, needs ClaimsController implementation
- **register.php** - Static registration → Already routed, needs ClaimsController implementation

## Migration Strategy

### Phase 1: Core Admin Functions
1. Create service classes for business logic
2. Move database queries to repository classes
3. Create proper controllers extending BaseController
4. Use template system for views

### Phase 2: Configuration Interfaces
1. Create unified configuration service
2. Migrate settings to database or config files
3. Build modern UI for configuration

### Phase 3: Cleanup
1. Remove legacy files after migration
2. Update all references
3. Remove `/admin-legacy/` and `/seller-legacy/` directories

## Benefits of This Approach
- Clean separation between old and new code
- No Apache PATH_INFO conflicts
- Clear migration path
- System remains functional during migration
- Easy to track progress

## Current Status
- ✅ Authentication works through new system
- ✅ Admin dashboard loads correctly
- ✅ Legacy admin tools accessible at `/admin-legacy/*`
- ⚠️ Seller login/register need implementation in ClaimsController
- 🔄 Legacy files still in use but clearly separated