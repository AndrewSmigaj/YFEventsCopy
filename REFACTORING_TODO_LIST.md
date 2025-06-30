# YFEvents Refactoring TODO List

**IMPORTANT CORRECTIONS FOUND:**
- The refactor directory has Models/ and Scrapers/ folders that are legacy duplicates
- There's a vendor/ directory in refactor that should be removed
- 32 test files scattered around need cleanup
- Multiple BaseModel implementations confirmed in modules

## Pre-flight Checklist
- [ ] Ensure all work is committed: `git status`
- [ ] Confirm you're on the refactor/v2-complete-rebuild branch
- [ ] Have database backup ready

## Phase 1: Create New Directory Structure (5 minutes)

### 1.1 Create Core Directories
- [ ] `mkdir -p src/{Domain,Application,Infrastructure,Presentation}`
- [ ] `mkdir -p config database public resources storage tests docs`
- [ ] `mkdir -p public/{admin,api,assets/{css,js,images}}`
- [ ] `mkdir -p storage/{app,framework/{cache,sessions},logs,uploads}`
- [ ] `mkdir -p tests/{Unit,Feature,Integration}`
- [ ] `mkdir -p resources/{views,assets}`
- [ ] `mkdir -p database/{migrations,seeds}`

### 1.2 Create Subdirectories for Clean Architecture
- [ ] `mkdir -p src/Domain/{Auth,Events,Shops,Users,Claims,Common}`
- [ ] `mkdir -p src/Application/{Controllers,Services,Validation}`
- [ ] `mkdir -p src/Infrastructure/{Config,Container,Database,Email,Http,Providers,Repositories,Services}`
- [ ] `mkdir -p src/Presentation/{Api,Http}/Controllers`

## Phase 2: Migrate Clean Architecture (10 minutes)

### 2.1 Move Core Architecture (SKIP Models and Scrapers!)
- [ ] `cp -r www/html/refactor/src/Domain/* src/Domain/`
- [ ] `cp -r www/html/refactor/src/Application/* src/Application/`
- [ ] `cp -r www/html/refactor/src/Infrastructure/* src/Infrastructure/`
- [ ] `cp -r www/html/refactor/src/Presentation/* src/Presentation/`

### 2.2 Move Configuration
- [ ] `cp -r www/html/refactor/config/* config/`
- [ ] Remove backup files: `rm config/backup_*.json config/config_backup.php`

### 2.3 Move Public Assets
- [ ] `cp -r www/html/refactor/public/* public/`
- [ ] `cp -r www/html/css/* public/assets/css/`
- [ ] `cp -r www/html/js/* public/assets/js/`

## Phase 3: Handle Models and Services (15 minutes)

### 3.1 Identify Best Implementation
- [ ] Compare `/src/Models/` vs `/www/html/refactor/src/Models/` (they might be identical)
- [ ] If identical, keep original `/src/Models/` temporarily
- [ ] If refactor has improvements, merge best parts

### 3.2 Scrapers Decision
- [ ] Compare `/src/Scrapers/` vs `/www/html/refactor/src/Scrapers/`
- [ ] Keep best implementation (likely original)
- [ ] Move to `src/Infrastructure/Scrapers/` for clean architecture

## Phase 4: Module Cleanup (10 minutes)

### 4.1 Remove Duplicate BaseModels
- [ ] Keep only one BaseModel in `src/Domain/Common/BaseModel.php`
- [ ] `rm modules/yfauth/src/Models/BaseModel.php`
- [ ] `rm modules/yfclaim/src/Models/BaseModel.php`
- [ ] Update module models to use shared BaseModel

### 4.2 Consolidate Authentication
- [ ] Review all auth implementations:
  - `/src/Utils/Auth.php`
  - `/modules/yfauth/src/Services/AuthService.php`
  - `/modules/yfclaim/src/Services/ClaimAuthService.php`
  - `/www/html/refactor/src/Utils/Auth.php`
- [ ] Create unified auth in `src/Domain/Auth/AuthService.php`
- [ ] Remove duplicate auth files

## Phase 5: Database Consolidation (5 minutes)

### 5.1 Merge SQL Files
- [ ] `cat database/*.sql > database/schema.sql`
- [ ] `cat modules/*/database/*.sql >> database/schema.sql`
- [ ] Create migrations directory: `mkdir -p database/migrations`

## Phase 6: Clean Up (10 minutes)

### 6.1 Remove Test Files
- [ ] `find . -name "test_*.php" -not -path "./vendor/*" -exec rm {} \;`
- [ ] `find . -name "*_test.php" -not -path "./tests/*" -exec rm {} \;`
- [ ] Move any real tests: `find . -name "*Test.php" -not -path "./vendor/*" -not -path "./tests/*" -exec mv {} tests/Feature/ \;`

### 6.2 Remove Duplicate Vendor
- [ ] `rm -rf www/html/refactor/vendor/`

### 6.3 Clean Up JSON/Result Files
- [ ] `rm *_progress.json *_results.json`
- [ ] `rm codebase_verification_*.json`

## Phase 7: Update Core Files (10 minutes)

### 7.1 Update Composer.json
- [ ] Use the refactor's composer.json as base (it has dependencies)
- [ ] Update autoload paths to point to new structure
- [ ] Add module autoloading

### 7.2 Create Entry Point
- [ ] Create `public/index.php` with clean architecture bootstrap
- [ ] Ensure it loads the Infrastructure/Bootstrap.php

### 7.3 Environment File
- [ ] Create comprehensive `.env.example`
- [ ] Include all necessary variables from both systems

## Phase 8: Namespace Updates (15 minutes)

### 8.1 Update Namespaces
- [ ] `find src/ -type f -name "*.php" -exec sed -i 's/YakimaFinds\\/YFEvents\\/g' {} +`
- [ ] Update module namespaces to use core services

### 8.2 Update Include Paths
- [ ] Search for hardcoded paths: `grep -r "www/html/refactor" --include="*.php"`
- [ ] Update all paths to new structure

## Phase 9: Final Cleanup (5 minutes)

### 9.1 Remove Old Directories
- [ ] `rm -rf www/html/refactor/` (everything has been moved)
- [ ] `rm -rf admin/` (only 3 unused files)
- [ ] Consider removing `/www/html/` entirely after verification

### 9.2 Documentation Cleanup
- [ ] Move essential docs to `/docs/`
- [ ] Remove outdated documentation
- [ ] Update README.md with new structure

## Phase 10: Verification (10 minutes)

### 10.1 Composer Install
- [ ] Run `composer install` to verify dependencies
- [ ] Fix any autoloading issues

### 10.2 Basic Testing
- [ ] Create simple health check endpoint
- [ ] Verify database connection
- [ ] Check if core services load

### 10.3 Git Status Review
- [ ] `git status` to see all changes
- [ ] Verify no critical files were lost
- [ ] Commit in logical chunks

## Post-Refactoring Tasks

### Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Configure database credentials
- [ ] Set API keys

### Web Server
- [ ] Update document root to `/public`
- [ ] Update any hardcoded paths in configs

### Testing
- [ ] Run any existing tests
- [ ] Manual testing of core features
- [ ] Check all module functionality

## Critical Warnings

⚠️ **DO NOT** blindly copy Models/ and Scrapers/ from refactor - they appear to be duplicates
⚠️ **VERIFY** auth implementations before deleting - choose the most complete one
⚠️ **CHECK** if any files in www/html/refactor have unique improvements before deleting
⚠️ **KEEP** the YFClaim work that's 70% complete - don't lose progress

## Success Metrics

- [ ] Single entry point working
- [ ] No duplicate files remain
- [ ] All tests in `/tests/`
- [ ] Clean architecture structure
- [ ] Modules use shared services
- [ ] No hardcoded paths
- [ ] Composer autoload working
- [ ] No vendor directories except root

## Time Estimate

- Total time: ~1.5 hours of focused work
- Can be done incrementally
- Commit after each phase for safety

---

**Ready to start?** Work through this list methodically, checking off each item as you complete it.