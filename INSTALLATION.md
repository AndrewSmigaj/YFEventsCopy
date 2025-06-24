# YFEvents Installation Guide

## Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
3. [Communication Module Setup](#communication-module-setup)
4. [Claims Module Setup](#claims-module-setup)
5. [Configuration](#configuration)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

## System Requirements

- PHP 8.1 or higher
- MySQL 5.7 or higher
- Apache 2.4+ with mod_rewrite enabled
- Composer 2.0+
- Required PHP extensions:
  - PDO MySQL
  - JSON
  - Session
  - Fileinfo
  - GD or ImageMagick
  - OpenSSL

## Installation Steps

### 1. Clone Repository

```bash
git clone https://github.com/yourusername/YFEvents.git
cd YFEvents
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Database Setup

#### Create Database
```sql
CREATE DATABASE yakima_finds CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'yfevents'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON yakima_finds.* TO 'yfevents'@'localhost';
FLUSH PRIVILEGES;
```

#### Import Schema
```bash
# Core schema
mysql -u yfevents -p yakima_finds < database/calendar_schema.sql

# Communication module tables
mysql -u yfevents -p yakima_finds < database/communication_schema.sql

# Claims module tables  
mysql -u yfevents -p yakima_finds < modules/yfclaim/database/schema.sql

# Location fields update for picks feature
mysql -u yfevents -p yakima_finds < database/communication_location_update.sql
```

### 4. Environment Configuration

```bash
cp .env.example .env
```

Edit `.env` with your settings:
```env
# Database Configuration
DB_HOST=localhost
DB_NAME=yakima_finds
DB_USER=yfevents
DB_PASS=your_secure_password

# Google Maps API (Required for maps functionality)
GOOGLE_MAPS_API_KEY=your_api_key_here

# Admin Authentication
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=$2y$12$... # Generate with password_hash()

# Email Configuration (for event processing)
ADMIN_EMAIL=admin@yoursite.com
FROM_EMAIL=calendar@yoursite.com

# Environment
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yoursite.com
```

### 5. Directory Permissions

```bash
# Create required directories
mkdir -p www/html/refactor/sessions
mkdir -p cache/geocode
mkdir -p logs
mkdir -p uploads/attachments
mkdir -p uploads/avatars

# Set permissions
chmod 755 cache logs uploads
chmod 777 www/html/refactor/sessions
chmod 755 cron/scrape-events.php
```

### 6. Apache Configuration

Create virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName yoursite.com
    DocumentRoot /path/to/YFEvents/www/html
    
    <Directory /path/to/YFEvents/www/html>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Session directory for refactor
    php_admin_value session.save_path "/path/to/YFEvents/www/html/refactor/sessions"
    
    ErrorLog ${APACHE_LOG_DIR}/yfevents-error.log
    CustomLog ${APACHE_LOG_DIR}/yfevents-access.log combined
</VirtualHost>
```

Enable site and reload Apache:
```bash
a2ensite yfevents
a2enmod rewrite
systemctl reload apache2
```

## Communication Module Setup

### 1. Create Initial Channels

```sql
-- Insert default communication channels
INSERT INTO communication_channels (name, slug, description, type, created_by_user_id) VALUES
('General', 'general', 'General discussion', 'public', 1),
('Announcements', 'announcements', 'Official announcements', 'announcement', 1),
('Events', 'events', 'Event discussions', 'public', 1),
('Shops', 'shops', 'Local business discussions', 'public', 1),
('Picks - Estate & Yard Sales', 'picks', 'Share estate and yard sale locations', 'public', 1);
```

### 2. Configure Picks Feature

The picks feature requires location metadata support:

```sql
-- Verify metadata column exists
DESCRIBE communication_messages metadata;

-- If not, run the location update
mysql -u yfevents -p yakima_finds < database/communication_location_update.sql
```

### 3. Test Communication Module

1. Access: `https://yoursite.com/refactor/communication/`
2. Login with admin credentials
3. Test sending a message
4. Test picks feature at: `https://yoursite.com/refactor/communication/picks.php`

## Claims Module Setup

### 1. Create Test Data (Optional)

```sql
-- Insert test estate sale company
INSERT INTO yfc_sellers (company_name, contact_name, email, phone, status) VALUES
('Premier Estate Sales', 'John Doe', 'john@estatesales.com', '555-0123', 'verified');

-- Insert test sale
INSERT INTO yfc_sales (seller_id, title, description, start_date, end_date, address, city, state, zip_code, status) VALUES
(1, 'Vintage Collection Sale', 'Antiques and collectibles', '2024-01-15', '2024-01-17', '123 Main St', 'Yakima', 'WA', '98901', 'active');
```

### 2. Configure Seller Access

Sellers need to be linked to user accounts:

```sql
-- Link a user to a seller company
UPDATE yfc_sellers SET user_id = 1 WHERE id = 1;
```

### 3. Test Claims Module

1. Public browsing: `https://yoursite.com/refactor/claims`
2. Seller dashboard: `https://yoursite.com/refactor/seller/dashboard`
3. Admin management: `https://yoursite.com/refactor/admin/claims.php`

## Configuration

### 1. Session Configuration

The refactor system uses a custom session directory:

```php
// Automatically configured in index.php
$sessionDir = __DIR__ . '/sessions';
ini_set('session.save_path', $sessionDir);
```

### 2. API Configuration

Create `config/api_keys.php`:

```php
<?php
return [
    'google_maps' => env('GOOGLE_MAPS_API_KEY'),
    'segmind' => env('SEGMIND_API_KEY'), // For AI scraping
];
```

### 3. Service Container

Services are configured in `config/services/`:
- `core.php` - Core services
- `domain.php` - Domain services
- `infrastructure.php` - Infrastructure services

## Testing

### 1. Run System Tests

```bash
# Full test suite
php tests/run_all_tests.php

# Individual modules
php tests/test_core_functionality.php
php tests/test_web_interfaces.php
php tests/test_yfclaim.php
```

### 2. Test Routes

```bash
php scripts/test_all_routes.php
```

### 3. Test Communication API

```bash
# Test authentication
curl -X POST https://yoursite.com/refactor/api/communication/channels \
  -H "Cookie: PHPSESSID=your_session_id"

# Test sending pick
curl -X POST https://yoursite.com/refactor/api/communication/channels/5/messages \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "content": "Estate Sale this Weekend!",
    "location_name": "Big Estate Sale",
    "location_address": "123 Main St, Yakima, WA",
    "location_latitude": 46.6021,
    "location_longitude": -120.5059,
    "event_date": "2024-01-20"
  }'
```

## Troubleshooting

### Session Issues

If you get "Authentication required" errors:

1. Check session directory permissions:
```bash
ls -la www/html/refactor/sessions/
chmod 777 www/html/refactor/sessions/
```

2. Verify session configuration:
```php
<?php
echo "Session save path: " . session_save_path() . "\n";
echo "Is writable: " . (is_writable(session_save_path()) ? 'Yes' : 'No') . "\n";
```

### Database Connection Issues

1. Test connection:
```php
<?php
require 'config/database.php';
echo "Connected successfully\n";
```

2. Check credentials in `.env`

### Map Not Loading

1. Verify Google Maps API key in `.env`
2. Check browser console for errors
3. Ensure API key has Maps JavaScript API enabled

### Picks Not Showing

1. Verify metadata is being saved:
```sql
SELECT id, content, metadata FROM communication_messages 
WHERE channel_id = (SELECT id FROM communication_channels WHERE slug = 'picks');
```

2. Check browser console for JavaScript errors
3. Ensure user is authenticated

### Module Not Found Errors

1. Run composer autoload:
```bash
composer dump-autoload
```

2. Check namespace in file matches PSR-4 structure
3. Verify file permissions

## Security Notes

1. **Never commit** `.env` files or `config/api_keys.php`
2. **Restrict** session directory access in production
3. **Use HTTPS** for all production deployments
4. **Validate** all user inputs
5. **Escape** output in templates
6. **Update** dependencies regularly

## Support

For issues or questions:
1. Check existing issues on GitHub
2. Review logs in `logs/` directory
3. Enable debug mode temporarily in `.env`
4. Create detailed bug reports with:
   - Error messages
   - Steps to reproduce
   - System information
   - Relevant log entries