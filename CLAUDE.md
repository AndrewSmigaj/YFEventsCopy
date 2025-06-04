# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 📊 Current Development Status (June 2025) - Updated by Testing

### ✅ YFEvents Core - TESTING COMPLETE
- Event calendar with map integration ✅
- Event scraping from multiple sources ✅ (needs URL update for visityakima.com)
- Local business directory with geocoding ✅
- Advanced admin interface ✅ (fixed 500 errors)
- Shop management with proper JSON handling ✅
- Geocoding verification and repair tools ✅
- Map center fixed to Yakima Finds location (111 S. 2nd St) ✅
  - Updated coordinates to precise location: 46.600825, -120.503357
- YFClaim Buyers page 500 error fixed (changed $pdo to $db) ✅
- Event scraper SQL issues fixed ✅
  - Fixed column names: completed_at → end_time, started_at → start_time
  - Fixed status enum: 'error' → 'failed'
  - Fixed GROUP BY clause for MySQL strict mode

### 🚧 YFClaim Module - IN PROGRESS (40% complete)
- **Database Schema**: ✅ Installed (6 tables, sample data)
- **Admin Interface**: ✅ Templates functional, shows stats
- **Model Classes**: 🚧 Structure created, CRUD methods needed
- **Business Logic**: 📅 Planned (offer management, notifications)
- **Public Interface**: 📅 Planned (buyer/seller portals)

### 🎯 Immediate Next Tasks
1. **Find correct Visit Yakima events URL** - Current URL returns 404
2. **Implement YFClaim SellerModel CRUD methods** to make admin interface fully functional:
   - `createSeller()`, `getAllSellers()`, `updateSeller()`, `getSellerById()`
3. **Complete Event Parser Testing Framework** - Build comprehensive test suite for different calendar formats

### 🔗 Quick Links
- **Main Admin**: `http://137.184.245.149/admin/`
- **Advanced Admin**: `http://137.184.245.149/admin/calendar/`
- **YFClaim Admin**: `http://137.184.245.149/modules/yfclaim/www/admin/`
- **YFClaim Progress**: `modules/yfclaim/PROGRESS.md`

## Common Development Commands

### Database Setup and Management
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE yakima_finds;"

# Apply core schema
mysql -u root -p yakima_finds < database/calendar_schema.sql
mysql -u root -p yakima_finds < database/batch_processing_schema.sql
mysql -u root -p yakima_finds < database/intelligent_scraper_schema.sql

# Install YFClaim module (✅ ALREADY DONE)
mysql -u yfevents -p yakima_finds < modules/yfclaim/database/schema.sql

# Apply migrations
php database/apply_migrations.php
```

### Dependency Management
```bash
# Install PHP dependencies
composer install

# Update autoloader after adding new classes
composer dump-autoload
```

### Environment Configuration
```bash
# Copy environment template
cp .env.example .env

# Copy API keys template
cp config/api_keys.example.php config/api_keys.php
```

### Manual Event Scraping
```bash
# Run scraper manually
php cron/scrape-events.php

# Run for specific source ID
php cron/scrape-events.php --source-id=1
```

### Permissions Setup
```bash
# Create required directories
mkdir -p cache/geocode logs

# Set permissions
chmod 755 cache logs
chmod +x cron/scrape-events.php
```

## Architecture Overview

This is a PHP-based event calendar and local business directory system for yakimafinds.com. It follows an MVC-like pattern without using a framework.

### Key Architectural Patterns

1. **PSR-4 Autoloading**: Classes in `src/` use namespace `YakimaFinds\` or `YFEvents\`
2. **Model Pattern**: All models extend `BaseModel` which provides CRUD operations
3. **Direct Database Access**: Uses PDO with prepared statements, no ORM
4. **Template System**: PHP templates in `www/html/templates/` for HTML rendering
5. **AJAX Endpoints**: Located in `www/html/ajax/` for asynchronous operations
6. **Admin System**: Separate admin interface in `www/html/admin/` with session-based auth

### Database Architecture

The system uses MySQL with these core tables:
- `events`: Main event storage with geocoded locations, categories, and source tracking
- `local_shops`: Business directory with full profiles and amenities
- `calendar_sources`: Configuration for various scraper types (iCal, HTML, JSON, Yakima Valley)
- `event_categories`: Hierarchical event categorization
- `scraping_logs`: Monitoring scraper performance

### Scraping System

The scraping system supports multiple source types:
1. **EventScraper**: Base class defining the scraper interface
2. **YakimaValleyEventScraper**: Specialized for yakimavalley.org format
3. **Intelligent Scraper**: LLM-powered scraping using Segmind API for automatic pattern detection

Configuration is stored as JSON in `calendar_sources.configuration` field.

### API Structure

**Public API** (`/www/html/api/`):
- RESTful endpoints for events and shops
- Returns JSON responses
- No authentication required

**Admin API** (`/www/html/admin/api/`):
- Protected by session authentication
- Handles event approval, source management, manual scraping

### Frontend Architecture

- **No Build Process**: Uses vanilla JavaScript, no bundling or transpilation
- **Google Maps Integration**: Heavy use of Maps JavaScript API for interactive features
- **Calendar View**: Custom JavaScript calendar implementation in `js/calendar.js`
- **Mobile Support**: Responsive CSS with touch events for mobile devices

### Session Management

Admin authentication uses PHP sessions with these key files:
- `admin/login.php`: Handles authentication
- `admin/logout.php`: Destroys session
- All admin pages check session at the top of each file

### Geocoding Strategy

1. Checks local cache first (`cache/geocode/`)
2. Falls back to Google Maps Geocoding API or OpenStreetMap Nominatim
3. Caches results to minimize API calls
4. Handles rate limiting and errors gracefully

## Modular Architecture

YFEvents supports optional modules that extend functionality. Modules are self-contained packages that can be installed/uninstalled without affecting the core system.

### Module Management
```bash
# Install a module
php modules/install.php module-name

# Apply modules database schema
mysql -u root -p yakima_finds < database/modules_schema.sql

# List available modules
php modules/install.php
```

### Module Structure
```
modules/
└── module-name/
    ├── module.json      # Module manifest with requirements
    ├── database/        # SQL schemas
    ├── src/             # PHP source (PSR-4: YFEvents\Modules\ModuleName)
    ├── www/             # Public files (admin, api, assets, templates)
    └── README.md        # Module documentation
```

### Current Modules
- **yfclaim**: Facebook-style claim sale platform for estate sales (database ready, models need implementation)

## YFClaim Development Commands

### Database Verification
```bash
# Check YFClaim tables
mysql -u yfevents -p yakima_finds -e "SHOW TABLES LIKE 'yfc_%';"

# Verify data
mysql -u yfevents -p yakima_finds -e "SELECT COUNT(*) FROM yfc_categories;"
```

### Model Development
```bash
# Test model autoloading
php -r "require 'vendor/autoload.php'; require 'config/database.php'; 
use YFEvents\Modules\YFClaim\Models\SellerModel; 
echo class_exists('YFEvents\Modules\YFClaim\Models\SellerModel') ? 'OK' : 'FAIL';"

# Test model instantiation
php -r "require 'vendor/autoload.php'; require 'config/database.php'; 
\$model = new YFEvents\Modules\YFClaim\Models\SellerModel(\$db); 
echo 'SellerModel loaded successfully\n';"
```

### Testing YFClaim Admin
```bash
# Test admin interface functionality
curl -I http://137.184.245.149/modules/yfclaim/www/admin/
```

## Important Notes

- **No Testing Framework**: The project has test files but no formal testing setup
- **No Linting**: No PHP linting configuration exists
- **Direct File Access**: Admin pages are accessed directly, not through a router
- **Environment Variables**: Configuration uses both `.env` and `config/` files
- **Error Handling**: Errors are logged to `logs/` directory
- **No CI/CD**: Deployment is manual, no automated pipelines
- **Module System**: Optional modules extend functionality without modifying core
- **Security**: See `SECURITY.md` for API key and deployment guidelines