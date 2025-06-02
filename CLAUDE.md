# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### Database Setup and Management
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE yakima_finds;"

# Apply schema
mysql -u root -p yakima_finds < database/calendar_schema.sql
mysql -u root -p yakima_finds < database/batch_processing_schema.sql
mysql -u root -p yakima_finds < database/intelligent_scraper_schema.sql

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
- **yfclaim**: Facebook-style claim sale platform for estate sales (in development)

## Important Notes

- **No Testing Framework**: The project has test files but no formal testing setup
- **No Linting**: No PHP linting configuration exists
- **Direct File Access**: Admin pages are accessed directly, not through a router
- **Environment Variables**: Configuration uses both `.env` and `config/` files
- **Error Handling**: Errors are logged to `logs/` directory
- **No CI/CD**: Deployment is manual, no automated pipelines
- **Module System**: Optional modules extend functionality without modifying core