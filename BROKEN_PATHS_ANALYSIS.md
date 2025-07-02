# Broken Path References Analysis

## Summary
Identified broken path references in YFEvents codebase that need fixing.

## Broken Files by Type

### 1. Scripts with Wrong Paths (2 files)
- `/scripts/browser-automation/database-bridge.php`
  - **Current**: `require_once dirname(__DIR__, 2) . '/www/html/refactor/vendor/autoload.php';`
  - **Issue**: References non-existent '/www/html/refactor/' directory
  - **Should be**: `require_once dirname(__DIR__, 2) . '/vendor/autoload.php';`

### 2. Test Files with Relative Paths (1 file)
- `/tests/Feature/test_email_connection.php`
  - **Current**: `require_once 'vendor/autoload.php';`
  - **Issue**: Uses relative path without proper directory context
  - **Should be**: `require_once __DIR__ . '/../../vendor/autoload.php';`

### 3. Module Files Missing Database Connection (23 files)
These files in the modules directory correctly include vendor/autoload.php and config/database.php but are missing proper database connection initialization:

#### YFClaim Module (10 files)
- `/modules/yfclaim/www/item.php`
- `/modules/yfclaim/www/my-offers.php`
- `/modules/yfclaim/www/sale.php`
- `/modules/yfclaim/www/index.php`
- `/modules/yfclaim/www/debug.php`
- `/modules/yfclaim/www/debug-index.php`
- `/modules/yfclaim/www/simple-index.php`
- `/modules/yfclaim/www/dashboard/index.php`
- `/modules/yfclaim/www/dashboard/create-sale.php`
- `/modules/yfclaim/www/dashboard/manage-items.php`

**Current pattern**:
```php
require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';
```

**Issue**: The paths are correct, but `config/database.php` returns configuration array, not a PDO connection.

#### YFClassifieds Module (5 files)
- `/modules/yfclassifieds/www/index.php`
- `/modules/yfclassifieds/www/item.php`
- `/modules/yfclassifieds/www/admin/create.php`
- `/modules/yfclassifieds/www/admin/items.php`
- `/modules/yfclassifieds/www/admin/simple-index.php`

#### YFTheme Module (8 files)
- `/modules/yftheme/www/index.php`
- `/modules/yftheme/www/admin/index.php`
- `/modules/yftheme/www/admin/theme-editor.php`
- `/modules/yftheme/www/api/index.php`
- `/modules/yftheme/www/api/theme.php`
- `/modules/yftheme/diagnostic.php`
- `/modules/yftheme/install.php`
- `/modules/yftheme/cleanup.php`

## Total Count: 26 Files
- 1 script with wrong refactor path
- 1 test file with improper relative path
- 23 module files with database connection issues
- 1 controller file with possible database connection issue

## Root Causes
1. **Refactoring remnants**: Some files still reference old refactoring attempts
2. **Missing bootstrap**: Module files lack proper initialization
3. **Inconsistent patterns**: Different files use different approaches
4. **Database config vs connection**: config/database.php returns array, not PDO object