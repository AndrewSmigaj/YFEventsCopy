# YFEvents Refactoring Final TODO List

**Goal**: Clean, reorganize, and refactor to create a single source of truth before completing YFClaim module

## Phase 1: Preparation & Analysis (30 minutes)

### 1.1 Initial Setup
- [ ] Commit current state: `git add . && git commit -m "Pre-refactor checkpoint"`
- [ ] Create working branch: `git checkout -b refactor/unified-structure`
- [ ] Document current working URLs for testing later

### 1.2 Analyze What to Keep/Remove
- [ ] Compare `/src/Models/` vs `/www/html/refactor/src/Models/` - are they identical?
- [ ] Compare `/src/Scrapers/` vs `/www/html/refactor/src/Scrapers/` - which is better?
- [ ] Check if `/www/html/refactor/src/Utils/` has unique functionality
- [ ] List any custom code in `/www/html/` that's not in refactor

## Phase 2: Create Clean Structure (20 minutes)

### 2.1 Root Directory Structure
```bash
# Create the new structure
mkdir -p src/{Domain,Application,Infrastructure,Presentation}
mkdir -p config database docs public resources storage tests
mkdir -p public/{assets/{css,js,images},uploads}
mkdir -p storage/{app,framework/{cache,sessions},logs,uploads}
mkdir -p tests/{Unit,Feature,Integration}
mkdir -p resources/{views,assets}
```

### 2.2 Set Up Git Ignores
- [ ] Create/update `.gitignore`:
```
/vendor/
/node_modules/
/storage/logs/*
/storage/framework/cache/*
/storage/framework/sessions/*
/.env
/.env.backup
*.log
.DS_Store
Thumbs.db
*.cache
*_results.json
*_progress.json
```

## Phase 3: Migrate Clean Architecture (45 minutes)

### 3.1 Move Core Architecture
- [ ] `cp -r www/html/refactor/src/Domain/* src/Domain/`
- [ ] `cp -r www/html/refactor/src/Application/* src/Application/`
- [ ] `cp -r www/html/refactor/src/Infrastructure/* src/Infrastructure/`
- [ ] `cp -r www/html/refactor/src/Presentation/* src/Presentation/`

### 3.2 Handle Legacy Code Decision
- [ ] **IF** `/src/Models/` has unique functionality:
  - [ ] Move to `src/Infrastructure/Legacy/Models/` temporarily
  - [ ] Create migration plan to Domain entities
- [ ] **ELSE**:
  - [ ] Delete `/src/Models/` entirely

### 3.3 Handle Scrapers
- [ ] **IF** scrapers are identical, keep original location
- [ ] **ELSE** move best implementation to `src/Infrastructure/Scrapers/`
- [ ] Remove `/www/html/refactor/src/Scrapers/`

### 3.4 Move Configuration
- [ ] `cp www/html/refactor/config/*.php config/`
- [ ] Remove backup/example files: `rm config/*backup* config/*example*`
- [ ] Merge any unique configs from root `/config/`

### 3.5 Move Entry Points
- [ ] `cp www/html/refactor/index.php public/index.php`
- [ ] `cp -r www/html/refactor/routes .`
- [ ] `cp www/html/refactor/.htaccess public/` (if exists)

## Phase 4: Asset Organization (20 minutes)

### 4.1 Consolidate Public Assets
- [ ] `cp -r www/html/css/* public/assets/css/`
- [ ] `cp -r www/html/js/* public/assets/js/`
- [ ] `cp -r www/html/images/* public/assets/images/` (if exists)
- [ ] `cp -r www/html/refactor/public/* public/` (merge, don't overwrite)

### 4.2 Admin Assets
- [ ] `mkdir -p public/admin/assets`
- [ ] Move admin-specific CSS/JS to `public/admin/assets/`

### 4.3 Module Assets
- [ ] `mkdir -p public/modules`
- [ ] `cp -r modules/*/www/assets/* public/modules/` (organize by module)

## Phase 5: Module Integration (30 minutes)

### 5.1 Remove Duplicate Base Classes
- [ ] Create `src/Domain/Common/BaseModel.php` (best implementation)
- [ ] Create `src/Domain/Common/BaseRepository.php`
- [ ] Delete `modules/yfauth/src/Models/BaseModel.php`
- [ ] Delete `modules/yfclaim/src/Models/BaseModel.php`

### 5.2 Update Module Namespaces
- [ ] Update modules to use core base classes
- [ ] Update modules to use core auth service
- [ ] Ensure PSR-4 compliance

### 5.3 Module Routes
- [ ] Add module routes to main `routes/web.php`:
```php
// YFClaim routes (to be implemented)
$router->group(['prefix' => 'claims'], function($router) {
    // Add routes when controllers are ready
});
```

## Phase 6: Database Consolidation (20 minutes)

### 6.1 Organize Schemas
- [ ] `mkdir -p database/{schemas,migrations,seeds}`
- [ ] `cp database/*.sql database/schemas/`
- [ ] Create combined schema:
```bash
cat database/schemas/calendar_schema.sql > database/schema.sql
echo "\n-- Module Schemas\n" >> database/schema.sql
cat modules/*/database/*.sql >> database/schema.sql
```

### 6.2 Remove Duplicates
- [ ] Check for duplicate table definitions
- [ ] Ensure foreign keys are correct
- [ ] Document any data migration needs

## Phase 7: Clean Up (30 minutes)

### 7.1 Remove Test Files
- [ ] `find . -name "test_*.php" -delete`
- [ ] `find . -name "*_test.php" -not -path "./tests/*" -delete`
- [ ] `find . -name "*Test.php" -not -path "./vendor/*" -not -path "./tests/*" -exec mv {} tests/Feature/ \;`

### 7.2 Remove Duplicate Code
- [ ] `rm -rf www/html/refactor/vendor/`
- [ ] `rm -rf www/html/refactor/src/Models/` (after verification)
- [ ] `rm -rf www/html/refactor/src/Scrapers/` (after verification)
- [ ] `rm -rf www/html/refactor/src/Utils/` (after moving useful code)

### 7.3 Remove Old Structures
- [ ] `rm -rf admin/` (only 3 unused files)
- [ ] `rm -rf www/html/refactor/` (after everything moved)
- [ ] Clean up root: `rm *_progress.json *_results.json *.md.backup`

### 7.4 Documentation Cleanup
- [ ] Move essential docs to `/docs/`:
  - README.md
  - DEPLOYMENT_GUIDE_DIGITALOCEAN.md
  - Architecture docs
- [ ] Create `/docs/archive/` for historical docs
- [ ] Update README.md with new structure

## Phase 8: Configuration Updates (20 minutes)

### 8.1 Update Composer.json
- [ ] Use refactor's composer.json as base (has dependencies)
- [ ] Update paths:
```json
{
    "autoload": {
        "psr-4": {
            "YFEvents\\": "src/",
            "YFEvents\\Modules\\": "modules/"
        }
    }
}
```
- [ ] Run `composer dump-autoload`

### 8.2 Environment Configuration
- [ ] Create comprehensive `.env.example` with all variables
- [ ] Update paths in config files
- [ ] Remove hardcoded values

### 8.3 Update Entry Point
- [ ] Ensure `public/index.php` loads from correct paths
- [ ] Update `.htaccess` for new structure

## Phase 9: Namespace & Path Updates (30 minutes)

### 9.1 Update Namespaces
- [ ] Change `YakimaFinds\` to `YFEvents\` everywhere:
```bash
find src/ -type f -name "*.php" -exec sed -i 's/namespace YakimaFinds/namespace YFEvents/g' {} +
find src/ -type f -name "*.php" -exec sed -i 's/use YakimaFinds/use YFEvents/g' {} +
```

### 9.2 Update Include Paths
- [ ] Search and replace old paths:
```bash
grep -r "www/html/refactor" --include="*.php" .
grep -r "../../../config" --include="*.php" .
# Update any found paths
```

### 9.3 Update Module References
- [ ] Ensure modules reference core services correctly
- [ ] Update any hardcoded module paths

## Phase 10: Testing & Verification (45 minutes)

### 10.1 Basic Functionality Tests
- [ ] `composer install` - verify no errors
- [ ] `php public/index.php` - should not error
- [ ] Check database connection
- [ ] Verify routes load: `php -S localhost:8000 -t public/`

### 10.2 Module Tests
- [ ] Test each module loads correctly
- [ ] Verify module assets accessible
- [ ] Check module database tables exist

### 10.3 Create Health Check
- [ ] Create `public/health.php`:
```php
<?php
require '../vendor/autoload.php';
// Basic health checks
echo json_encode([
    'status' => 'ok',
    'modules' => ['events', 'shops', 'users', 'claims'],
    'database' => 'connected'
]);
```

### 10.4 Documentation Update
- [ ] Update README with:
  - New structure
  - Setup instructions
  - Module information
  - Development workflow

## Phase 11: Final Review (15 minutes)

### 11.1 Code Review
- [ ] No duplicate files remain
- [ ] No hardcoded passwords/keys
- [ ] Consistent namespace usage
- [ ] No broken includes

### 11.2 Git Review
- [ ] `git status` - review all changes
- [ ] Stage files in logical groups
- [ ] Commit with clear messages:
  - "refactor: Migrate to clean architecture"
  - "refactor: Consolidate authentication"
  - "refactor: Organize assets and modules"
  - "refactor: Clean up legacy code"

### 11.3 Final Checklist
- [ ] Single entry point working
- [ ] All modules accessible
- [ ] Database connections work
- [ ] No vendor directories except root
- [ ] Clean folder structure
- [ ] Updated documentation

## Phase 12: Post-Refactor Setup (10 minutes)

### 12.1 Development Environment
- [ ] Copy `.env.example` to `.env`
- [ ] Configure database credentials
- [ ] Set `APP_ENV=development`
- [ ] Set `APP_DEBUG=true`

### 12.2 Prepare for YFClaim Development
- [ ] Document where to add:
  - `ClaimPublicController`
  - `ClaimBuyerController`
  - `ClaimSellerController`
  - Repository implementations
- [ ] Set up controller templates

### 12.3 Update Git
- [ ] Push to repository
- [ ] Create PR if using pull requests
- [ ] Document changes in CHANGELOG.md

## Success Metrics

✅ **Structure**
- Clean root directory
- Single source of truth for all code
- Organized assets
- Clear module boundaries

✅ **Code Quality**
- No duplicate implementations
- Consistent namespaces
- No hardcoded values
- PSR-4 compliance

✅ **Functionality**
- All existing features work
- Database connections active
- Routes accessible
- Modules integrated

✅ **Development Ready**
- Clear where to add YFClaim controllers
- Documentation updated
- Environment configured
- Ready for deployment

---

## Time Estimate: 4-5 hours total

**Note**: After this refactoring, the codebase will be clean and organized, making the YFClaim implementation much easier. The 4-5 hours for YFClaim can then be focused purely on business logic rather than dealing with structural issues.