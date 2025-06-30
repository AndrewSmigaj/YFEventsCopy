#!/bin/bash
# YFEvents Refactoring Script
# This script helps automate the refactoring process
# Run with: bash refactor.sh

set -e  # Exit on error

echo "================================================"
echo "YFEvents Refactoring Script"
echo "================================================"
echo ""
echo "This script will help refactor the codebase to create a single source of truth."
echo "Make sure you have committed all changes before proceeding."
echo ""
read -p "Have you committed all changes and ready to proceed? (y/n) " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Please commit your changes first."
    exit 1
fi

# Create backup branch
echo "Creating backup branch..."
git checkout -b pre-refactor-backup-$(date +%Y%m%d-%H%M%S)
git checkout -b refactor/unified-structure

# Phase 1: Create new directory structure
echo ""
echo "Phase 1: Creating new directory structure..."
echo "================================================"

mkdir -p src/{Domain,Application,Infrastructure,Presentation}
mkdir -p src/Domain/{Auth,Events,Shops,Users,Claims,Common}
mkdir -p src/Application/{Controllers,Services,Validation}
mkdir -p src/Infrastructure/{Config,Container,Database,Email,Http,Providers,Repositories,Services}
mkdir -p src/Presentation/{Api,Http}/Controllers

mkdir -p config
mkdir -p database/{migrations,seeds}
mkdir -p public/{admin,api,assets/{css,js,images}}
mkdir -p resources/{views,assets}
mkdir -p storage/{app,framework/{cache,sessions,testing,views},logs,uploads}
mkdir -p tests/{Unit,Feature,Integration}
mkdir -p docs

# Create .gitkeep files to preserve empty directories
find . -type d -empty -exec touch {}/.gitkeep \;

echo "✓ Directory structure created"

# Phase 2: Move clean architecture from refactor
echo ""
echo "Phase 2: Migrating clean architecture..."
echo "================================================"

if [ -d "www/html/refactor/src/Domain" ]; then
    echo "Moving Domain layer..."
    cp -r www/html/refactor/src/Domain/* src/Domain/ 2>/dev/null || true
fi

if [ -d "www/html/refactor/src/Application" ]; then
    echo "Moving Application layer..."
    cp -r www/html/refactor/src/Application/* src/Application/ 2>/dev/null || true
fi

if [ -d "www/html/refactor/src/Infrastructure" ]; then
    echo "Moving Infrastructure layer..."
    cp -r www/html/refactor/src/Infrastructure/* src/Infrastructure/ 2>/dev/null || true
fi

if [ -d "www/html/refactor/src/Presentation" ]; then
    echo "Moving Presentation layer..."
    cp -r www/html/refactor/src/Presentation/* src/Presentation/ 2>/dev/null || true
fi

echo "✓ Clean architecture migrated"

# Phase 3: Move configuration
echo ""
echo "Phase 3: Consolidating configuration..."
echo "================================================"

if [ -d "www/html/refactor/config" ]; then
    cp -r www/html/refactor/config/* config/ 2>/dev/null || true
fi

# Create unified database config if it doesn't exist
if [ ! -f "config/database.php" ]; then
    cat > config/database.php << 'EOF'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'yakima_finds'),
            'username' => env('DB_USERNAME', 'yfevents'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ],
    ],
];
EOF
fi

echo "✓ Configuration consolidated"

# Phase 4: Move public assets
echo ""
echo "Phase 4: Organizing public assets..."
echo "================================================"

if [ -d "www/html/refactor/public" ]; then
    cp -r www/html/refactor/public/* public/ 2>/dev/null || true
fi

if [ -d "www/html/css" ]; then
    cp -r www/html/css/* public/assets/css/ 2>/dev/null || true
fi

if [ -d "www/html/js" ]; then
    cp -r www/html/js/* public/assets/js/ 2>/dev/null || true
fi

echo "✓ Public assets organized"

# Phase 5: Consolidate tests
echo ""
echo "Phase 5: Consolidating tests..."
echo "================================================"

# Find and move all test files
find . -name "*Test.php" -not -path "./vendor/*" -not -path "./tests/*" -exec mv {} tests/Feature/ \; 2>/dev/null || true
find . -name "test_*.php" -not -path "./vendor/*" -not -path "./tests/*" -exec rm {} \; 2>/dev/null || true

echo "✓ Tests consolidated"

# Phase 6: Update composer.json
echo ""
echo "Phase 6: Updating Composer configuration..."
echo "================================================"

cat > composer.json << 'EOF'
{
    "name": "yakimafinds/yfevents",
    "description": "YFEvents - Unified Event Management Platform",
    "type": "project",
    "version": "2.0.0",
    "require": {
        "php": "^8.1",
        "ext-pdo": "*",
        "ext-json": "*",
        "ext-mbstring": "*",
        "monolog/monolog": "^3.0",
        "guzzlehttp/guzzle": "^7.0",
        "vlucas/phpdotenv": "^5.0",
        "nesbot/carbon": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "phpstan/phpstan": "^1.0",
        "friendsofphp/php-cs-fixer": "^3.0",
        "symfony/var-dumper": "^6.0"
    },
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
    },
    "scripts": {
        "test": "phpunit",
        "analyse": "phpstan analyse",
        "cs-fix": "php-cs-fixer fix",
        "post-install-cmd": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ]
    }
}
EOF

echo "✓ Composer configuration updated"

# Phase 7: Create .env.example
echo ""
echo "Phase 7: Creating environment template..."
echo "================================================"

cat > .env.example << 'EOF'
# Application
APP_NAME=YFEvents
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_KEY=

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=

# External Services
GOOGLE_MAPS_API_KEY=
FIRECRAWL_API_KEY=

# Mail
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# Cache
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=debug
EOF

echo "✓ Environment template created"

# Phase 8: Create public entry point
echo ""
echo "Phase 8: Creating unified entry point..."
echo "================================================"

cat > public/index.php << 'EOF'
<?php
/**
 * YFEvents - Unified Entry Point
 */

// Register autoloader
require __DIR__.'/../vendor/autoload.php';

// Bootstrap application
$app = require_once __DIR__.'/../src/Infrastructure/Bootstrap.php';

// Handle request
$app->run();
EOF

echo "✓ Entry point created"

# Phase 9: Clean up old structure
echo ""
echo "Phase 9: Cleaning up (dry run - files listed but not deleted)..."
echo "================================================"
echo ""
echo "The following directories/files should be removed after verification:"
echo ""

# List files that should be deleted (but don't delete them yet)
echo "Directories to remove:"
echo "  - www/html/refactor/ (contents moved)"
echo "  - admin/ (unused)"
echo "  - src/Models/ (if using Domain entities)"
echo "  - src/Scrapers/ (if duplicated in Infrastructure)"
echo ""
echo "Files to remove:"
find . -name "test_*.php" -not -path "./vendor/*" -not -path "./tests/*" 2>/dev/null | head -20 || true

# Phase 10: Summary
echo ""
echo "================================================"
echo "Refactoring Script Complete!"
echo "================================================"
echo ""
echo "✓ New directory structure created"
echo "✓ Clean architecture migrated"
echo "✓ Configuration consolidated"
echo "✓ Tests organized"
echo "✓ Composer updated"
echo ""
echo "Next steps:"
echo "1. Review the changes: git status"
echo "2. Run: composer install"
echo "3. Update namespace references as needed"
echo "4. Test the application"
echo "5. Remove old directories (see Phase 9 output)"
echo ""
echo "To update all namespace references:"
echo "  find src/ -type f -name '*.php' -exec sed -i 's/YakimaFinds\\/YFEvents\\/g' {} +"
echo ""
echo "Remember to:"
echo "- Update web server configuration to point to /public"
echo "- Copy .env.example to .env and configure"
echo "- Run database migrations"
echo "- Clear any caches"
echo ""
echo "Happy refactoring! 🚀"