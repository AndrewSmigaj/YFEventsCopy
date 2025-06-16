# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

YFEvents is a comprehensive PHP-based event calendar and local business directory system for yakimafinds.com with a modular architecture featuring:

- **Core System**: Event scraping, calendar interface, shop directory with Google Maps integration
- **YFClaim Module**: Estate sale claim platform (40% complete - models need implementation)
- **YFAuth Module**: Authentication and authorization system

**Technology Stack**: PHP 8.2+, MySQL, vanilla JavaScript, Google Maps API, Composer (PSR-4)

## ⚠️ CRITICAL DEPLOYMENT RULES ⚠️

### 🚨 **NEVER REPLACE THE MAIN SYSTEM** 🚨
- **PRODUCTION URL**: `https://backoffice.yakimafinds.com/` - **MUST ALWAYS WORK**
- **DO NOT** deploy experimental/refactored code to the main DocumentRoot (`/home/robug/YFEvents/www/html`)
- **DO NOT** create symbolic links that replace the working system
- **ALWAYS** keep the original working system intact

### 📁 **CORRECT DEVELOPMENT STRUCTURE**
```
YFEvents/
├── www/html/                    # ✅ PRODUCTION (working HTML interface)
│   ├── index.php               # ✅ Main landing page
│   ├── calendar.php            # ✅ Working calendar interface
│   ├── admin/                  # ✅ Working admin panel
│   └── refactor/               # ✅ REFACTORED VERSION (subdirectory)
│       ├── index.php           # 🔬 Modern architecture
│       ├── admin/              # 🔬 Refactored admin
│       └── src/                # 🔬 Domain-driven design
├── .git/                       # ✅ Git repository
│   └── refs/heads/refactor     # ✅ Refactor branch exists
└── YFEvents-refactor/          # ✅ SEPARATE development directory
```

### 🎯 **LIVE SYSTEM ACCESS**
- **Production (Original)**: `https://backoffice.yakimafinds.com/` - ✅ **FULLY FUNCTIONAL HTML**
- **Refactor (Testing)**: `https://backoffice.yakimafinds.com/refactor/` - 🔬 **Modern architecture**
- **Admin Panel**: `https://backoffice.yakimafinds.com/admin/` - ✅ **Working admin interface**

### 🔄 **GIT WORKFLOW**
- **Main branch**: Production-ready original system
- **Refactor branch**: Modern architecture development
- **NEVER** merge refactor to main without explicit approval
- **ALWAYS** work in the refactor subdirectory for experimental features

### 🛡️ **DEPLOYMENT SAFETY CHECKLIST**
Before making ANY changes to production:

1. **✅ Verify current system working**: Test `https://backoffice.yakimafinds.com/`
2. **✅ Check structure**: Ensure `/www/html/refactor/` exists for development
3. **✅ Never use symlinks**: Do not replace DocumentRoot with symbolic links
4. **✅ Test in subdirectory**: Use `/refactor/` for all experimental work
5. **✅ Create backups**: Always backup before ANY changes
6. **✅ Get explicit approval**: Never deploy to production without user consent

### 🚨 **ROLLBACK PROCEDURES**
If production is accidentally broken:

```bash
# Emergency rollback from backup
cp -r /home/robug/YFEvents-original-backup/* /home/robug/YFEvents/www/html/

# Check system is working
curl -I https://backoffice.yakimafinds.com/
```

## 📍 **CRITICAL ROUTING & NAVIGATION DOCUMENTATION**

### **Admin Dashboard - SINGLE SOURCE OF TRUTH**
- **PRIMARY URL**: `/refactor/admin/dashboard` (AdminDashboardController)
- **Legacy URLs**: `/refactor/admin/` and `/refactor/admin/index.php` → **REDIRECT** to dashboard
- **NEVER** create duplicate admin interfaces or dashboards

### **Admin Pages & Controllers**
| Page | URL | File/Controller | Purpose |
|------|-----|----------------|---------|
| Dashboard | `/admin/dashboard` | AdminDashboardController | Central admin hub with all stats |
| Events | `/admin/events.php` | Static PHP file | Event management |
| Shops | `/admin/shops.php` | Static PHP file | Shop/business management |
| Email Events | `/admin/email-events.php` | Static PHP file | Facebook email event processing |
| Email Config | `/admin/email-config.php` | Static PHP file | IMAP/SMTP configuration |
| Theme | `/admin/theme.php` | Static PHP file | Theme, SEO, social media settings |
| Claims | `/admin/claims.php` | Static PHP file | Estate sale management |
| Scrapers | `/admin/scrapers.php` | Static PHP file | Event scraper configuration |
| Users | `/admin/users.php` | Static PHP file | User management |
| Settings | `/admin/settings.php` | Static PHP file | System settings |

### **Public Routes & Controllers**
| Feature | URL Pattern | Controller | Method |
|---------|-------------|------------|--------|
| Home | `/` | HomeController | index |
| Events List | `/events` | EventController | showEventsPage |
| Event Detail | `/events/{id}` | EventController | showEventDetailPage |
| Shops List | `/shops` | ShopController | showShopsPage |
| Shop Detail | `/shops/{id}` | ShopController | showShopDetailsPage |
| Claims | `/claims` | ClaimsController | showClaimsPage |

### **API Routes**
| Endpoint | Pattern | Controller | Purpose |
|----------|---------|------------|---------|
| Events API | `/api/events` | EventController | Search/list events |
| Event Detail API | `/api/events/{id}` | EventController | Get single event |
| Shops API | `/api/shops` | ShopController | List shops |
| Shop Detail API | `/api/shops/{id}` | ShopController | Get single shop |

### **Recent Implementations**
1. **Email Event Processing** (June 2025)
   - EmailEventProcessor service for parsing Facebook event emails
   - Admin interface at `/admin/email-events.php`
   - Configuration at `/admin/email-config.php`
   - Cron job: `scripts/process_event_emails.php`

2. **Theme Editor** (June 2025)
   - ThemeController with full CRUD operations
   - Admin interface at `/admin/theme.php`
   - SEO and social media integration
   - Dynamic CSS generation

3. **Event Detail Pages** (June 2025)
   - Fixed 404 errors on "More Info" links
   - Added `/events/{id}` route
   - Full event detail display with maps

## 🔒 **CRITICAL: API KEY SECURITY GUIDELINES** 🔒

### 🚨 **NEVER COMMIT API KEYS TO VERSION CONTROL**

**Protected Files (Auto-ignored by .gitignore):**
- `config/api_keys.php` - Main API configuration
- `**/config/api_keys.php` - All nested API configs
- `.env` - Environment variables
- `*.key`, `*.pem`, `*.p12` - All certificate files

### 📋 **API Key Security Checklist**

**Before ANY commit:**
1. ✅ **Verify .gitignore** protects API key files
2. ✅ **Search for exposed keys** using detection commands below
3. ✅ **Check git status** - no sensitive files should appear
4. ✅ **Use placeholders** in code - never hardcode actual keys
5. ✅ **Review diff** before committing any config files

### 🛡️ **Safe API Key Practices**

**✅ CORRECT:**
```php
// Use environment variables or config files
$apiKey = $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';
$apiKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
```

**❌ NEVER DO:**
```php
// NEVER hardcode keys in source files
$apiKey = 'AIzaSyD0XAoZjfslv7ikTaply_DfoKp9nx_VXyU';
src="https://maps.googleapis.com/maps/api/js?key=AIzaSy..."
```

### 🚨 **API Key Detection Commands**

**Run before every commit:**
```bash
# Check for exposed API keys
grep -r "AIza\|fc-[a-f0-9]\|sk_\|pk_" . --exclude-dir=.git --exclude-dir=vendor

# Verify gitignore protection
git check-ignore config/api_keys.php .env

# Search for common API key patterns  
grep -r "AIza[A-Za-z0-9_-]{35}" . --exclude-dir=.git
grep -r "fc-[a-f0-9]{32}" . --exclude-dir=.git

# Review staged changes for secrets
git status
git diff --cached
```

### 🚨 **If API Keys Are Accidentally Committed**

**IMMEDIATE ACTIONS:**
1. **REVOKE the exposed keys** immediately from provider dashboards
2. **Generate new keys** with proper restrictions
3. **Update local configuration** with new keys
4. **Do NOT attempt to remove from Git history** (leaves traces)
5. **Document the incident** and update security procedures

### 🔐 **API Key Management**

**Local Development:**
- Store in `.env` files (gitignored)
- Store in `config/api_keys.php` (gitignored)
- Use example files with placeholders

**Production Deployment:**
- Use environment variables
- Use secure configuration management
- Never store in version control
- Restrict keys by domain/IP when possible

## Current Status (June 2025)

### 🎯 **MIGRATION STRATEGY: LEGACY → REFACTOR → PRODUCTION**

**Current Production (Legacy)**: `https://backoffice.yakimafinds.com/` - ✅ Working but will be deprecated
**New Production (Refactor)**: `https://backoffice.yakimafinds.com/refactor/` - 🚀 **MIGRATION IN PROGRESS**

### 🚀 **PRIMARY DEVELOPMENT TARGET: REFACTOR SYSTEM**
- **Architecture**: Modern Domain-Driven Design, PHP 8.1+, dependency injection
- **Location**: `/www/html/refactor/` subdirectory
- **Status**: **WILL BECOME PRODUCTION** when migration complete
- **URL**: `https://backoffice.yakimafinds.com/refactor/`
- **Public Interfaces**: Events ✅, Shops ✅, YFClaim 🚧 (in progress)

### 🔧 Legacy System - TEMPORARY MAINTENANCE MODE
- Event calendar with map integration, multi-source scraping (97.1% success rate)
- Local business directory with geocoding, advanced admin interface
- **Status**: Maintained for stability during migration, **will be deprecated**
- **URL**: `https://backoffice.yakimafinds.com/` - ✅ Working until refactor complete

### 🚧 YFClaim Module Status
**Legacy YFClaim**: `/modules/yfclaim/www/` - ✅ Functional but limited
**Refactor YFClaim**: `/refactor/claims/` - 🚧 **ACTIVE DEVELOPMENT** 
- **Database**: ✅ Installed (6 tables, sample data) 
- **Refactor Integration**: 🚧 Controllers and views being implemented
- **Target**: Full estate sale platform in refactor system

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

## Module Entry Points & User Workflows

### Main Application
- **Landing Page**: `/` - Portal to all modules with system status
- **Event Calendar**: `/calendar.php` - Interactive calendar with maps
- **Event Submission**: `/events/submit/` - Community event submission form
- **Simple Calendar**: `/simple-calendar.php` - Lightweight test interface

### Local Business Directory
- **Browse Shops**: `/calendar.php#shops` - Integrated shop directory
- **Claim Business**: `/claim-shop.php` - Multi-step business claiming process
- **Shop API**: `/api/shops/` - JSON endpoint for shop data

### YFClaim Estate Sales
- **Public Sales**: `/modules/yfclaim/www/` - Browse current estate sales
- **Seller Portal**: `/modules/yfclaim/www/dashboard/` - Estate sale company dashboard
- **Admin Interface**: `/modules/yfclaim/www/admin/` - Full admin management
- **Individual Sales**: `/modules/yfclaim/www/sale.php?id=X` - Specific sale details
- **Buyer Offers**: `/modules/yfclaim/www/my-offers.php` - Buyer dashboard

### Authentication (YFAuth)
- **Enhanced Login**: `/modules/yfauth/www/admin/login.php` - Modern login interface
- **User Registration**: `/ajax/auth/register.php` - User account creation
- **Login API**: `/modules/yfauth/api/login.php` - Authentication endpoint

### Administration
- **Main Admin**: `/admin/` - Central admin dashboard
- **Advanced Admin**: `/admin/calendar/` - Enhanced event management
- **Shop Management**: `/admin/shops.php` - Business directory admin
- **Event Scrapers**: `/admin/scrapers.php` - Scraper configuration
- **AI Scraper**: `/admin/intelligent-scraper.php` - LLM-powered scraping
- **URL Validator**: `/admin/validate-urls.php` - Link testing tool
- **Geocoding Fix**: `/admin/geocode-fix.php` - Location repair tool

### API Endpoints
- **Events API**: `/api/events/` - Event data endpoint
- **Simple Events**: `/api/events-simple.php` - Lightweight event API
- **Calendar AJAX**: `/ajax/calendar-events.php` - Dynamic calendar data

## Registration & Claiming Workflows

### Business Owner Registration
1. **Visit**: `/claim-shop.php`
2. **Choose Option**: Claim existing business or add new business
3. **Provide Details**: Business information, contact details, verification
4. **Verification**: Admin approval process for ownership claims
5. **Access**: Full business profile management

### Estate Sale Company Registration (YFClaim)
1. **Visit**: `/modules/yfclaim/www/dashboard/`
2. **Create Account**: Seller registration with company details
3. **Verification**: Admin approval for estate sale companies
4. **Setup Sales**: Create and manage estate sales
5. **QR Codes**: Generate QR codes for physical sale access

### User Account Registration (YFAuth)
1. **Visit**: `/ajax/auth/register.php` or `/modules/yfauth/www/admin/login.php`
2. **Create Account**: Username, email, password
3. **Role Assignment**: User roles and permissions
4. **Profile Management**: Update account details and preferences

### Event Submission (Community)
1. **Visit**: `/events/submit/`
2. **Event Details**: Title, date, time, location, description
3. **Submission**: Community event for admin approval
4. **Approval**: Admin review and calendar publication

## Key Notes

- **No formal testing framework** - Custom test scripts in `tests/`
- **No build process** - Direct file serving, no bundling/transpilation
- **Landing page portal** - `/` provides access to all modules
- **Manual deployment** - No CI/CD pipelines
- **Environment config** - Uses both `.env` and `config/` files
- **Error logging** - Logs stored in `logs/` directory

## 📋 **DAILY REVIEW CHECKLIST**

### **Before Starting Work**
1. **Read this CLAUDE.md file** - Check for updates and critical rules
2. **Check current location** - Confirm working in `/refactor/` directory
3. **Review recent changes** - Look at git log for context
4. **Test current state** - Verify production is still working

### **Common Issues & Solutions**

#### **Routing Problems**
- **Issue**: "Page shows 404 but route exists"
- **Solution**: Check if controller method expects parameters from $_GET not method args
- **Example**: `showEventDetailPage()` gets ID from `$_GET['id']` not method parameter

#### **Duplicate Interfaces**
- **Issue**: Multiple admin dashboards or pages
- **Solution**: Always use `/admin/dashboard` as the single admin interface
- **Prevention**: Check existing routes before creating new pages

#### **Missing Navigation Links**
- **Issue**: New features not accessible from admin
- **Solution**: Update AdminDashboardController's renderDashboardPage method
- **Location**: `src/Presentation/Http/Controllers/AdminDashboardController.php`

#### **Database Column Mismatches**
- **Issue**: SQL errors about missing columns
- **Solution**: Check actual database schema with `DESCRIBE table_name`
- **Example**: Events table uses `start_datetime` not `start_date`

### **Testing Protocol**
1. **Run route tester**: `php scripts/test_all_routes.php`
2. **Check for errors**: Look for 404s and 500s
3. **Test functionality**: Click through admin sections
4. **Verify data**: Ensure database operations work

### **Documentation Updates**
- **When**: After ANY significant change
- **What**: Update this CLAUDE.md file
- **How**: Add to Recent Implementations section
- **Why**: Prevents repeated mistakes and confusion