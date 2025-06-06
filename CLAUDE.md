# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YFEvents is a comprehensive PHP-based event calendar and local business directory system for yakimafinds.com with a modular architecture featuring:

- **Core System**: Event scraping, calendar interface, shop directory with Google Maps integration
- **YFClaim Module**: Estate sale claim platform (40% complete - models need implementation)
- **YFAuth Module**: Authentication and authorization system

**Technology Stack**: PHP 8.2+, MySQL, vanilla JavaScript, Google Maps API, Composer (PSR-4)

## Current Status (June 2025)

### ✅ YFEvents Core - FULLY FUNCTIONAL
- Event calendar with map integration, multi-source scraping (97.1% success rate)
- Local business directory with geocoding, advanced admin interface
- Shop management with JSON operating hours, geocoding verification tools

### 🚧 YFClaim Module - 40% COMPLETE
- **Database**: ✅ Installed (6 tables, sample data)
- **Admin Interface**: ✅ Templates functional, shows stats  
- **Models**: 🚧 Structure created, CRUD methods needed
- **Public Interface**: 📅 Planned (buyer/seller portals)

### 🎯 Priority Tasks
1. **Implement YFClaim SellerModel CRUD methods**: `createSeller()`, `getAllSellers()`, `updateSeller()`, `getSellerById()`
2. **Find correct Visit Yakima events URL** - Current URL returns 404

### 🔗 Live Access
- **Main Calendar**: `http://137.184.245.149/`
- **Advanced Admin**: `http://137.184.245.149/admin/calendar/`
- **YFClaim Admin**: `http://137.184.245.149/modules/yfclaim/www/admin/`

## Development Commands

### Testing
```bash
# Run complete test suite
php tests/run_all_tests.php

# Run specific test modules
php tests/test_core_functionality.php
php tests/test_web_interfaces.php
php tests/test_yfclaim.php

# Test individual scrapers
php test_scraper.php
php scripts/test_all_sources.php
```

### Database Management
```bash
# Apply core schema
mysql -u root -p yakima_finds < database/calendar_schema.sql

# Install YFClaim module (if needed)
mysql -u yfevents -p yakima_finds < modules/yfclaim/database/schema.sql

# Check YFClaim tables
mysql -u yfevents -p yakima_finds -e "SHOW TABLES LIKE 'yfc_%';"
```

### Dependencies & Setup
```bash
# Install PHP dependencies
composer install

# Update autoloader after adding classes
composer dump-autoload

# Set up environment
cp .env.example .env
mkdir -p cache/geocode logs
chmod 755 cache logs
chmod +x cron/scrape-events.php
```

### Event Scraping
```bash
# Manual scraping
php cron/scrape-events.php

# Test specific source
php cron/scrape-events.php --source-id=1
```

## Architecture

### Core Patterns
- **PSR-4 Autoloading**: `YFEvents\` and `YakimaFinds\` namespaces
- **BaseModel Pattern**: All models extend BaseModel with CRUD operations
- **Direct PDO**: No ORM, prepared statements for security
- **Template System**: PHP templates in `www/html/templates/`
- **Session-based Admin**: Simple authentication in `admin/` directory

### Database Structure
- `events`: Main event storage with geocoding and source tracking
- `local_shops`: Business directory with full profiles and amenities  
- `calendar_sources`: Scraper configurations (iCal, HTML, JSON formats)
- `event_categories`: Hierarchical categorization
- `yfc_*` tables: YFClaim module (sellers, sales, items, offers, buyers)

### Scraping System
- **EventScraper**: Base interface for all scrapers
- **YakimaValleyEventScraper**: Specialized for yakimavalley.org
- **Intelligent Scraper**: LLM-powered using Segmind API
- Configuration stored as JSON in `calendar_sources.configuration`

### API Endpoints
- **Public**: `/api/events`, `/api/shops` - No auth required
- **Admin**: `/admin/api/` - Session-protected management functions
- **AJAX**: `/ajax/` - Frontend interactions

### Frontend
- **Vanilla JavaScript**: No build process, direct file serving
- **Google Maps**: Heavy integration for interactive maps
- **Mobile-first**: Responsive design with touch support

## Module System

Self-contained packages that extend functionality without affecting core system.

### Module Structure
```
modules/module-name/
├── module.json          # Manifest with requirements  
├── database/           # SQL schemas
├── src/               # PHP source (PSR-4: YFEvents\Modules\ModuleName)
└── www/               # Public files (admin, api, templates)
```

### Current Modules
- **yfclaim**: Estate sale claim platform (database ready, models need implementation)
- **yfauth**: Authentication and authorization system

### YFClaim Development
```bash
# Test model autoloading
php -r "require 'vendor/autoload.php'; require 'config/database.php'; 
use YFEvents\Modules\YFClaim\Models\SellerModel; 
echo class_exists('YFEvents\Modules\YFClaim\Models\SellerModel') ? 'OK' : 'FAIL';"

# Test admin interface
curl -I http://137.184.245.149/modules/yfclaim/www/admin/
```

## Key Notes

- **No formal testing framework** - Custom test scripts in `tests/`
- **No build process** - Direct file serving, no bundling/transpilation
- **Direct page access** - Admin pages accessed directly, not through router
- **Manual deployment** - No CI/CD pipelines
- **Environment config** - Uses both `.env` and `config/` files
- **Error logging** - Logs stored in `logs/` directory