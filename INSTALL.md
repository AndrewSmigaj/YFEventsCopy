# YFEvents Installation Guide

A comprehensive event calendar and local business directory system for Yakima Valley.

## Features
- 📅 Event calendar with month, week, list, and map views
- 🗺️ Interactive Google Maps integration
- 🏪 Local business directory with images
- 🔄 Automated event scraping from multiple sources
- 🔐 Admin panel with authentication
- 📱 Mobile-responsive design

## Required Software

### 1. PHP 8.2+ with extensions:
```bash
sudo apt update
sudo apt install -y php8.3 php8.3-cli php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip
```

### 2. MySQL Server:
```bash
sudo apt install -y mysql-server mysql-client
```

### 3. Web Server (Apache or Nginx):
```bash
# For Apache:
sudo apt install -y apache2 libapache2-mod-php8.3

# For Nginx:
sudo apt install -y nginx php8.3-fpm
```

### 4. Composer (PHP dependency manager):
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## Installation Steps

Once the required software is installed:

1. **Set up the database:**
   ```bash
   mysql -u root -p
   CREATE DATABASE yakima_finds;
   EXIT;
   
   mysql -u root -p yakima_finds < database/calendar_schema.sql
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and Google Maps API key
   nano .env
   ```

3. **Install PHP dependencies:**
   ```bash
   composer install
   ```

4. **Set permissions:**
   ```bash
   chmod 755 cache logs
   chmod +x cron/scrape-events.php
   ```

5. **Configure web server:**
   - Point document root to `/home/robug/YFEvents/www/html/`
   - Enable PHP processing
   - Set up URL rewriting if needed

6. **Set up cron job for daily scraping:**
   ```bash
   crontab -e
   # Add this line:
   0 2 * * * php /home/robug/YFEvents/cron/scrape-events.php
   ```

## Current Status

✅ Project files cloned from GitHub
✅ Directory structure created
✅ Configuration files created:
   - `.env.example` - Environment template
   - `config/database.php` - Database connection
   - `composer.json` - PHP dependencies

❌ Pending installation:
   - PHP 8.2 and extensions
   - MySQL server
   - Web server (Apache/Nginx)
   - Composer
   - Database setup
   - Environment configuration

## Post-Installation Configuration

### 1. Admin Access
- URL: `http://your-domain/admin/`
- Username: `YakFind`
- Password: `MapTime`
- **Important**: Change these credentials after first login!

### 2. Google Maps Configuration
Edit `.env` file and add your Google Maps API key:
```
GOOGLE_MAPS_API_KEY=your_actual_api_key_here
```

Required Google APIs:
- Maps JavaScript API
- Geocoding API
- Places API (optional)

### 3. Event Scraping Setup
1. Access Admin → Manage Scrapers
2. Add scraping sources:
   - **iCal**: For calendar feeds
   - **HTML**: For website scraping
   - **Yakima Valley Events**: Custom parser for local events
   - **JSON**: For API endpoints

### 4. Database Maintenance
```bash
# Backup database
mysqldump -u root -p yakima_finds > yakima_finds_backup.sql

# Clean old events (optional)
mysql -u root -p yakima_finds -e "DELETE FROM events WHERE end_datetime < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
```

## Features Overview

### Map View
- **Red Pin**: Yakima Finds location (111 S 2nd St)
- **Blue Pins**: Events
- **Green Pins**: Local shops
- Toggle layers on/off with map controls

### Admin Panel
- Event management (approve/reject/delete)
- Shop directory management
- Scraper configuration
- View scraping logs
- Manual scrape triggers

### Supported Scraper Types
1. **iCal** - Standard calendar feeds
2. **HTML** - CSS selector-based scraping
3. **Yakima Valley** - Custom format parser
4. **JSON** - REST API endpoints
5. **Eventbrite** - Eventbrite organization events
6. **Facebook** - Facebook page events

## Troubleshooting

### Common Issues

1. **403 Forbidden Error**
   ```bash
   sudo chmod 755 /home/user
   sudo chown -R user:www-data /path/to/yfevents
   ```

2. **Database Connection Failed**
   - Check `.env` credentials
   - Verify MySQL is running: `sudo systemctl status mysql`

3. **Maps Not Loading**
   - Verify Google Maps API key
   - Check browser console for errors
   - Ensure API key has required permissions

4. **Scraping Failures**
   - Check source URL accessibility
   - Verify scraper configuration
   - Check logs in `/logs/` directory

## Security Notes

1. **Change Default Passwords**:
   - Admin panel credentials
   - MySQL root password
   - Application database password

2. **Secure Installation**:
   ```bash
   # Disable directory listing
   echo "Options -Indexes" > /path/to/yfevents/www/html/.htaccess
   
   # Protect sensitive files
   chmod 640 /path/to/yfevents/.env
   ```

3. **Regular Updates**:
   - Keep PHP and MySQL updated
   - Monitor security advisories
   - Regular backups

## Note

This server requires sudo/root access to install the necessary software packages. Please run the installation commands with appropriate privileges.