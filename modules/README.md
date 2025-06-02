# YFEvents Modular Architecture

## Overview

YFEvents supports optional modules that extend its functionality. Each module is self-contained and can be installed/uninstalled without affecting the core system.

## Module Structure

Each module should follow this structure:

```
modules/
└── module-name/
    ├── module.json          # Module manifest
    ├── install.php          # Installation script
    ├── uninstall.php        # Uninstallation script
    ├── database/            # SQL schemas and migrations
    ├── src/                 # PHP source code
    ├── www/                 # Public web files
    │   ├── admin/          # Admin interface additions
    │   ├── api/            # API endpoints
    │   ├── assets/         # CSS, JS, images
    │   └── templates/      # View templates
    ├── config/              # Module configuration
    └── README.md           # Module documentation
```

## Module Manifest (module.json)

Each module must include a `module.json` file:

```json
{
  "name": "module-name",
  "version": "1.0.0",
  "description": "Brief description of the module",
  "author": "Author Name",
  "requires": {
    "yfevents": ">=1.0.0",
    "php": ">=8.2",
    "extensions": ["pdo", "json"]
  },
  "namespace": "YFEvents\\Modules\\ModuleName",
  "hooks": {
    "admin_menu": "addAdminMenuItems",
    "public_routes": "registerPublicRoutes"
  },
  "database": {
    "tables": ["module_table1", "module_table2"],
    "prefix": "yfm_"
  }
}
```

## Installation Process

1. Place module in `modules/` directory
2. Run installation command:
   ```bash
   php modules/install.php module-name
   ```
3. The installer will:
   - Validate module requirements
   - Run database migrations
   - Copy public files to appropriate locations
   - Register module in system

## Module Integration Points

### 1. Database Integration
- Use module-specific table prefix
- Foreign keys to core tables allowed
- Migrations tracked in `module_migrations` table

### 2. Admin Interface
- Add menu items via hooks
- Use existing admin authentication
- Follow YFEvents admin UI patterns

### 3. Public Interface
- Register routes/endpoints
- Use core template system
- Share CSS/JS libraries

### 4. API Integration
- Extend existing API structure
- Use core authentication where needed
- Document endpoints in module README

## Available Core Services

Modules can access these YFEvents core services:
- Database connection (`$db`)
- Geocoding service
- Session management
- Template rendering
- API response helpers

## Module Development Guidelines

1. **Namespace**: Use `YFEvents\Modules\ModuleName`
2. **Database**: Prefix tables with module identifier
3. **Files**: Keep all files within module directory during development
4. **Dependencies**: Declare all requirements in manifest
5. **Documentation**: Include comprehensive README
6. **Uninstall**: Provide clean uninstall process

## Module Registry

Installed modules are tracked in the `modules` database table:
```sql
CREATE TABLE IF NOT EXISTS modules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    version VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive', 'error') DEFAULT 'active',
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    config JSON
);
```