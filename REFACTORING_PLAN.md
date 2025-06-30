# YFEvents Refactoring Plan: Creating a Single Source of Truth

**Objective**: Transform the fragmented codebase into a unified, maintainable system  
**Estimated Time**: 2-3 days of focused work  
**Risk Level**: Low (forked repository)

## Current State Analysis

### Major Issues
1. **Two Parallel Systems**: Legacy (`/www/html/`) and Refactor (`/www/html/refactor/`)
2. **5+ Authentication Implementations**: Each module has its own auth system
3. **Duplicate Code**: ~40% of code is duplicated between systems
4. **Mixed Architectures**: Procedural, MVC, and Clean Architecture coexisting
5. **Configuration Chaos**: Multiple config systems and hardcoded values

## Proposed New Structure

```
YFEvents/
├── src/                      # All application code (Clean Architecture)
│   ├── Domain/              # Business logic
│   ├── Application/         # Use cases
│   ├── Infrastructure/      # External interfaces
│   └── Presentation/        # Controllers
├── config/                  # Centralized configuration
├── database/                # Schemas and migrations
├── public/                  # Web root (was www/html)
├── resources/               # Views, assets
├── storage/                 # Logs, cache, uploads
├── tests/                   # All tests
├── modules/                 # Optional modules (refactored)
└── docs/                    # Consolidated documentation
```

## Refactoring Steps

### Phase 1: Preparation (Day 1 Morning)

#### 1.1 Create New Structure
```bash
# Create new directory structure
mkdir -p {src,config,database,public,resources,storage,tests,docs}
mkdir -p storage/{app,framework,logs,uploads}
mkdir -p resources/{views,assets}
mkdir -p tests/{Unit,Feature,Integration}
```

#### 1.2 Backup Current State
```bash
# Create backup branch
git checkout -b pre-refactor-backup
git push origin pre-refactor-backup

# Create working branch
git checkout -b refactor/unified-structure
```

### Phase 2: Core Migration (Day 1)

#### 2.1 Migrate Clean Architecture
```bash
# Move refactor's clean architecture to root
mv www/html/refactor/src/* src/
mv www/html/refactor/config/* config/
mv www/html/refactor/public/* public/

# Remove legacy models that have been replaced
rm -rf src/Models/  # Use Domain entities instead
rm -rf src/Scrapers/  # Use Infrastructure/Scrapers
```

#### 2.2 Consolidate Authentication
Create unified auth service:
```php
// src/Domain/Auth/AuthService.php
namespace YFEvents\Domain\Auth;

class AuthService {
    // Combine best parts of all auth implementations
    // Use JWT for API, sessions for web
    // Single source of truth for authentication
}
```

Remove duplicate auth:
```bash
rm src/Utils/Auth.php
rm modules/yfauth/src/Services/AuthService.php
rm modules/yfclaim/src/Services/ClaimAuthService.php
```

#### 2.3 Unify Configuration
```bash
# Create single config structure
# config/app.php - Application settings
# config/database.php - Database configuration
# config/auth.php - Authentication settings
# config/services.php - External services

# Remove duplicates
rm -rf www/html/refactor/config/
rm -rf modules/*/config/
```

### Phase 3: Module Integration (Day 1 Afternoon)

#### 3.1 Refactor Modules
Convert modules to use shared services:

```
modules/
├── yfauth/      -> Becomes core auth (integrated into src/Domain/Auth)
├── yfclaim/     -> Refactor to use shared BaseModel and AuthService
├── yftheme/     -> Keep as optional module
└── yfclassifieds/ -> Keep as optional module
```

#### 3.2 Database Consolidation
```bash
# Merge all schemas
cat database/*.sql > database/schema.sql
cat modules/*/database/*.sql >> database/schema.sql

# Create migration system
mkdir database/migrations
```

### Phase 4: Cleanup (Day 2 Morning)

#### 4.1 Remove Duplicates
```bash
# Remove entire legacy structure
rm -rf www/html/refactor/  # Everything moved to root
rm -rf admin/  # Unused legacy admin

# Remove duplicate vendor
rm -rf www/html/refactor/vendor/

# Remove test files scattered around
find . -name "test_*.php" -delete
find . -name "*_test.php" -not -path "./tests/*" -delete
```

#### 4.2 Consolidate Documentation
```bash
# Keep only essential docs
mv README.md docs/
mv SECURITY.md docs/
mv DEPLOYMENT_GUIDE_DIGITALOCEAN.md docs/

# Remove outdated docs
rm *_progress.json
rm *_results.json
rm SESSION_PROGRESS.md
```

#### 4.3 Update Entry Points
Create new public/index.php:
```php
<?php
require __DIR__.'/../vendor/autoload.php';

$app = new \YFEvents\Infrastructure\Bootstrap();
$app->run();
```

### Phase 5: Testing & Validation (Day 2 Afternoon)

#### 5.1 Create Test Suite
```bash
# Move existing tests
find . -name "*Test.php" -exec mv {} tests/Feature/ \;

# Create PHPUnit configuration
cp phpunit.xml.dist phpunit.xml
```

#### 5.2 Update Composer
```json
{
    "name": "yakimafinds/yfevents",
    "description": "Unified YFEvents Platform",
    "autoload": {
        "psr-4": {
            "YFEvents\\": "src/",
            "YFEvents\\Modules\\": "modules/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YFEvents\\Tests\\": "tests/"
        }
    }
}
```

### Phase 6: Final Integration (Day 3)

#### 6.1 Update All References
```bash
# Update namespace references
find src/ -type f -name "*.php" -exec sed -i 's/YakimaFinds\\/YFEvents\\/g' {} +

# Update include paths
find . -type f -name "*.php" -exec sed -i 's/www\/html\/refactor\///g' {} +
```

#### 6.2 Environment Configuration
Create single .env.example:
```env
APP_NAME=YFEvents
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=

GOOGLE_MAPS_API_KEY=
```

## Migration Checklist

### Before Starting
- [ ] Full backup created
- [ ] Team notified
- [ ] Deployment freeze in place

### Core Structure
- [ ] Clean architecture moved to root
- [ ] Legacy code removed
- [ ] Modules refactored
- [ ] Authentication unified
- [ ] Configuration consolidated

### Database
- [ ] Schemas merged
- [ ] Migrations created
- [ ] Test data prepared

### Testing
- [ ] PHPUnit configured
- [ ] Core tests passing
- [ ] Module tests passing
- [ ] Integration tests written

### Documentation
- [ ] README updated
- [ ] Architecture docs current
- [ ] API documentation generated
- [ ] Deployment guide updated

### Deployment
- [ ] Composer dependencies updated
- [ ] Web server config updated
- [ ] Environment variables set
- [ ] Health checks passing

## Benefits After Refactoring

1. **Single Source of Truth**: One place for each functionality
2. **Consistent Architecture**: Clean Architecture throughout
3. **Unified Authentication**: One auth system to maintain
4. **Better Testing**: Proper test structure enables TDD
5. **Easier Deployment**: Single entry point, clear structure
6. **Improved Security**: No duplicate vulnerabilities
7. **Performance**: Consistent patterns enable optimization
8. **Maintainability**: Clear boundaries and responsibilities

## Risk Mitigation

1. **Backup Everything**: Multiple backups before starting
2. **Incremental Changes**: Commit after each successful phase
3. **Parallel Testing**: Keep old structure until new one verified
4. **Rollback Plan**: Can revert to pre-refactor-backup branch
5. **Documentation**: Document all changes as you go

## Post-Refactoring Tasks

1. **Update CI/CD**: New paths and structure
2. **Team Training**: Architecture overview session
3. **Performance Baseline**: Benchmark new structure
4. **Security Audit**: Verify no new vulnerabilities
5. **Documentation Sprint**: Update all docs to match

## Directory Mapping (Old → New)

```
/www/html/refactor/src/          → /src/
/www/html/refactor/public/       → /public/
/www/html/refactor/config/       → /config/
/www/html/admin/                 → /public/admin/
/www/html/api/                   → /public/api/
/www/html/css/                   → /resources/assets/css/
/www/html/js/                    → /resources/assets/js/
/modules/                        → /modules/ (refactored)
/database/                       → /database/
/docs/                          → /docs/
```

## Success Criteria

- [ ] All tests pass
- [ ] No duplicate code
- [ ] Single auth system
- [ ] Consistent architecture
- [ ] Clear module boundaries
- [ ] No hardcoded values
- [ ] Deployment successful
- [ ] Performance maintained or improved

---

**Ready to Start?** This refactoring will transform YFEvents into a modern, maintainable system. The key is to be methodical and test at each step.