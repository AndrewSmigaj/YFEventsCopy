# Deployment Notes - YFEvents Refactoring

## Required Steps After Phase 9 (Namespace Updates)

### 1. Regenerate Composer Autoloader (REQUIRED)
After pulling the namespace changes, you MUST run:
```bash
composer dump-autoload
```

This regenerates the autoloader files to map the new YFEvents namespace to the correct file locations.

### 2. Clear Any Caches
If using opcache or any application caches:
```bash
# Clear opcache if enabled
php -r "opcache_reset();"

# Clear application cache if exists
rm -rf storage/cache/*
```

### 3. Update Environment File
Copy .env.example to .env and configure:
```bash
cp .env.example .env
# Edit .env with your actual values
```

### 4. Test the Application
```bash
# Run a simple test to ensure autoloading works
php public/index.php
```

## Why These Steps Are Required

The namespace migration from YakimaFinds to YFEvents requires:
- Composer autoloader regeneration (maps namespaces to file paths)
- Cache clearing (prevents loading old cached classes)
- Environment configuration (new config system uses .env)

Without these steps, you'll see "Class not found" errors.

## Quick Deployment Script
```bash
#!/bin/bash
# Save as deploy.sh and run after pulling changes

# Regenerate autoloader
composer dump-autoload

# Copy env file if not exists
[ ! -f .env ] && cp .env.example .env

# Clear caches
rm -rf storage/cache/*
rm -rf storage/sessions/*

echo "Deployment preparation complete!"
```