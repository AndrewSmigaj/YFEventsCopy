# Refactor Dependency Analysis

## What the Refactor Uses

### 1. Core Architecture
- **Namespace**: `YakimaFinds\` (needs to change to `YFEvents\`)
- **Entry Point**: `/www/html/refactor/index.php`
- **Bootstrap**: Full DI container and service provider pattern
- **Router**: 93 routes defined in `routes/web.php` and `routes/api.php`

### 2. Domain Layer (Business Logic)
The refactor has complete domain implementations for:
- **Admin**: Service and interface
- **Claims**: Full domain model (Buyer, Seller, Item, Offer, Sale)
- **Events**: Event entity and service
- **Shops**: Shop entity and service
- **Users**: User entity
- **Scrapers**: Scraper interfaces

### 3. Infrastructure Dependencies
- **Config**: Loads from `config/` directory
  - app.php
  - database.php
  - email.php
- **Database**: Uses Connection class with PDO
- **Container**: Custom DI container
- **Router**: Custom routing implementation
- **Environment**: Needs .env file for credentials

### 4. Presentation Layer
- **Controllers**: Full set of controllers for all domains
- **API Controllers**: Separate API controllers
- **Admin Pages**: Static PHP files in `admin/`
- **Assets**: CSS/JS files for admin interface

### 5. Legacy Dependencies
The refactor still has these legacy folders:
- `src/Models/` - Identical to root, can be removed
- `src/Scrapers/` - Missing Queue functionality from root
- `src/Utils/` - Has EnvLoader.php that root doesn't have

### 6. Module Integration
The refactor expects modules but doesn't directly include them:
- References to module assets
- Module configuration loading
- Module route registration
- Each module has its own www/assets/

## What to Keep vs Discard

### KEEP from Refactor:
1. **All Clean Architecture layers** (Domain, Application, Infrastructure, Presentation)
2. **Bootstrap and DI system**
3. **Router and route definitions**
4. **Config files** (merge with root configs)
5. **Admin static pages** and their assets
6. **EnvLoader.php** from Utils
7. **Vendor dependencies** (analyze composer.json)

### KEEP from Root:
1. **Scrapers/Queue/** - Advanced queue functionality
2. **Modules/** - All module code and assets
3. **Database schemas** - All SQL files
4. **Composer.json** (merge dependencies)
5. **Any unique configuration**

### DISCARD:
1. **All of /www/html/** except refactor (old production)
2. **Duplicate Models/** (identical in both places)
3. **Duplicate Scrapers/** (except Queue)
4. **Test files** scattered everywhere
5. **Vendor directories** (regenerate from composer)
6. **Backup files** and test results JSON files

## Proposed Organization Structure

```
/YFEvents/
├── src/                           # Clean Architecture
│   ├── Domain/                    # Business logic (from refactor)
│   │   ├── Admin/
│   │   ├── Claims/
│   │   ├── Common/
│   │   ├── Events/
│   │   ├── Shops/
│   │   ├── Users/
│   │   └── Scrapers/
│   ├── Application/               # Use cases (from refactor)
│   │   ├── Bootstrap.php
│   │   ├── Services/
│   │   └── Validation/
│   ├── Infrastructure/            # External interfaces
│   │   ├── Config/
│   │   ├── Container/
│   │   ├── Database/
│   │   ├── Http/
│   │   ├── Repositories/
│   │   ├── Scrapers/
│   │   │   ├── Queue/            # FROM ROOT (important!)
│   │   │   └── [other scrapers]
│   │   ├── Services/
│   │   └── Utils/                # Include EnvLoader.php
│   └── Presentation/              # UI/API layer
│       ├── Api/
│       └── Http/
├── config/                        # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── email.php
│   └── modules.php
├── database/                      # Database files
│   ├── schemas/                   # Organized SQL files
│   ├── migrations/                # Version-controlled changes
│   └── seeds/                     # Test data
├── modules/                       # Feature modules
│   ├── yfauth/
│   ├── yfclaim/
│   ├── yftheme/
│   └── yfclassifieds/
├── public/                        # Web root
│   ├── index.php                  # Single entry point
│   ├── .htaccess                  # URL rewriting
│   ├── admin/                     # Admin interface
│   ├── assets/                    # Public assets
│   │   ├── css/
│   │   ├── js/
│   │   └── images/
│   └── modules/                   # Module assets
├── routes/                        # Route definitions
│   ├── web.php
│   └── api.php
├── storage/                       # Writable directories
│   ├── app/
│   ├── logs/
│   ├── cache/
│   └── uploads/
├── tests/                         # Organized test suite
│   ├── Unit/
│   ├── Feature/
│   └── Integration/
├── .env.example                   # Environment template
├── .gitignore                     # Proper ignores
├── composer.json                  # Merged dependencies
└── README.md                      # Updated documentation
```

## Migration Steps

### Phase 1: Prepare (Current)
1. ✅ Analyze dependencies
2. ✅ Plan structure
3. ✅ Identify what to keep

### Phase 2: Create Structure
1. Create directory skeleton
2. Set up proper .gitignore
3. Create .env.example

### Phase 3: Move Core
1. Copy refactor's Clean Architecture to `/src/`
2. Add Queue functionality from root to Infrastructure/Scrapers/
3. Add EnvLoader.php to Infrastructure/Utils/
4. Preserve vendor composer.json for dependency analysis

### Phase 4: Update Namespaces
1. Change `YakimaFinds\` to `YFEvents\` everywhere
2. Update all imports and use statements
3. Update composer.json autoload PSR-4

### Phase 5: Consolidate
1. Move modules to root level
2. Create unified database schema
3. Merge configuration files
4. Consolidate assets properly

### Phase 6: Clean Up
1. Remove entire `/www/` directory
2. Remove duplicate code
3. Remove scattered test files
4. Clean up backup files

### Phase 7: Verify
1. Test bootstrap process
2. Verify routing works
3. Check module loading
4. Validate database connections

## Critical Considerations

### 1. Database Strategy
- **Current**: Multiple SQL files scattered
- **Proposed**: Organized schemas/ directory with migrations
- **Migration**: Create versioned migration files for safe updates

### 2. Authentication Consolidation
- **Issue**: Multiple auth systems (core + each module)
- **Solution**: Create auth adapter pattern for gradual migration
- **Priority**: Don't break existing module auth immediately

### 3. Module Integration
- **Current**: Modules are independent
- **Proposed**: Modules use core services through adapters
- **Approach**: Gradual migration, not forced integration

### 4. Path Dependencies
- **Problem**: Hardcoded paths throughout codebase
- **Solution**: Use relative paths and constants
- **Implementation**: Define path constants in bootstrap

### 5. Asset Management
- **Current**: Assets scattered in multiple locations
- **Proposed**: Centralized public/assets with module subdirectories
- **Benefit**: Easier to manage and serve efficiently

### 6. Environment Configuration
- **Current**: Mixed .env and PHP configs
- **Proposed**: .env for secrets, PHP for structure
- **Security**: Never commit .env, only .env.example

## Success Criteria

1. **Clean Architecture**: Clear separation between layers
2. **No Duplication**: Single source of truth for all code
3. **Module Independence**: Modules can still function independently
4. **Easy Deployment**: Simple to deploy to Digital Ocean
5. **Maintainable**: Clear structure for future developers
6. **Testable**: Organized test structure with clear coverage

## Risk Mitigation

1. **Backup Everything**: Current state is committed
2. **Incremental Changes**: Each phase is independently testable
3. **Preserve Functionality**: Don't break working features
4. **Document Changes**: Clear documentation of what moved where
5. **Rollback Plan**: Can revert to previous commit if needed

This plan follows SOLID principles, maintains separation of concerns, and creates a maintainable architecture suitable for long-term development.