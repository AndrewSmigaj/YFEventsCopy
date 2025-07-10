# Yakima Finds Event Calendar

A comprehensive event calendar system designed for yakimafinds.com that integrates event scraping, local business directory, interactive maps, and administrative management.

## Features

### Public Interface
- **Responsive Calendar Views**: Month, week, list, and interactive map views
- **Event Discovery**: Search, filter by category, location-based finding
- **Interactive Maps**: Google Maps integration showing events and local shops
- **Mobile Optimized**: Touch-friendly interface with GPS location services
- **Event Submission**: Public form for community event submissions
- **Map Controls**: Toggle event pins, shop pins, and Yakima Finds marker

### Administrative Interface
- **Advanced Admin Dashboard**: Enhanced UI for event, source, and shop management
- **Event Management**: Approve, edit, and manage submitted events with bulk actions
- **Source Management**: Configure and monitor automated scraping sources with testing
- **Local Business Directory**: Manage shops and businesses with images and geocoding
- **Scraper Dashboard**: Manual scraping, view logs, manage sources
- **YFClaim Module**: Facebook-style claim sale platform for estate sales
- **Geocoding Tools**: Verify and fix location coordinates for events and shops
- **Authentication**: Secure admin access with session management

### Automated Features
- **Multi-Source Scraping**: Support for iCal, HTML, JSON, Yakima Valley format
- **Smart Date Parsing**: Handles date ranges like "May 23 - 25"
- **Geocoding**: Automatic address-to-coordinates conversion
- **Duplicate Detection**: Smart filtering to prevent duplicate events
- **Category Mapping**: Automatic event categorization from source data

## Technology Stack

- **Backend**: PHP 8.2+ with PDO
- **Database**: MySQL with spatial indexing
- **Frontend**: Vanilla JavaScript with Google Maps API
- **Styling**: CSS Grid/Flexbox with responsive design
- **Integration**: Designed to extend existing CMS systems

## Installation

### Prerequisites

- PHP 8.2 or higher with extensions: pdo, mysql, json, curl, mbstring
- MySQL 5.7 or higher
- Apache/Nginx web server
- Google Maps API key
- Composer (for dependency management)

### Quick Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/r0bug/yfevents.git
   cd yfevents
   ```

2. **Database Setup**
   ```bash
   mysql -u root -p
   CREATE DATABASE yakima_finds;
   EXIT;
   mysql -u root -p yakima_finds < database/calendar_schema.sql
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database and API credentials
   nano .env
   ```

4. **Install Dependencies**
   ```bash
   composer install
   ```

5. **Set Permissions**
   ```bash
   chmod +x cron/scrape-events.php
   mkdir -p cache/geocode logs
   chmod 755 cache logs
   ```

6. **Configure Web Server**
   
   For Apache, create a virtual host pointing to `/www/html/`:
   ```apache
   <VirtualHost *:80>
       DocumentRoot /path/to/yfevents/www/html
       <Directory /path/to/yfevents/www/html>
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

7. **Configure Cron Job** (Optional)
   ```bash
   # Add to crontab for daily scraping at 2 AM
   0 2 * * * php /path/to/yfevents/cron/scrape-events.php
   ```

## Configuration

### Database Connection

Update `config/database.php` or set environment variables:

```php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'yakima_finds';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
```

### Google Maps API

Set your Google Maps API key in environment variables:

```env
GOOGLE_MAPS_API_KEY=your_api_key_here
```

Required APIs:
- Maps JavaScript API
- Places API
- Geocoding API

### Email Notifications

Configure SMTP settings for admin notifications:

```env
ADMIN_EMAIL=admin@yoursite.com
FROM_EMAIL=calendar@yoursite.com
```

## Usage

### Adding Event Sources

1. Access admin panel at `/admin/calendar/`
2. Go to "Event Sources"
3. Add source with appropriate configuration:

**iCal Source Example:**
```json
{
  "url": "https://example.com/events.ics"
}
```

**HTML Scraping Example:**
```json
{
  "selectors": {
    "event_container": ".event-item",
    "title": ".event-title",
    "datetime": ".event-date",
    "location": ".event-venue",
    "description": ".event-description"
  }
}
```

**JSON API Example:**
```json
{
  "events_path": "data.events",
  "field_mapping": {
    "title": "name",
    "start_datetime": "start_time",
    "location": "venue.name",
    "description": "details"
  }
}
```

### Managing Local Shops

1. Navigate to "Local Shops" in admin
2. Add business with details:
   - Name and description
   - Full address (auto-geocoded)
   - Contact information
   - Operating hours
   - Business category
   - Images and amenities

### API Endpoints

**Public API:**
- `GET /api/events` - List events with filtering
- `GET /api/events/today` - Today's events for map
- `GET /api/events/nearby?lat=46.6&lng=-120.5&radius=10` - Nearby events
- `POST /api/events/submit` - Submit new event
- `GET /api/shops` - Local business directory
- `GET /api/shops/categories` - Business categories

**Admin API:**
- `POST /admin/api/events/{id}/approve` - Approve pending event
- `GET /admin/api/sources/{id}/test` - Test scraping source
- `POST /admin/api/scrape/{sourceId}` - Manual scrape trigger

## Architecture

### Directory Structure

```
YFEvents/
├── database/           # Database schema and migrations
├── src/
│   ├── Models/        # Data models (Event, Shop, Source)
│   ├── Scrapers/      # Event scraping classes
│   ├── Utils/         # Utilities (Geocoding, etc.)
│   └── PageView/      # View controllers
├── www/html/
│   ├── admin/         # Administrative interface
│   ├── templates/     # Frontend templates
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript files
│   └── ajax/         # AJAX endpoints
├── cron/             # Scheduled tasks
└── config/           # Configuration files
```

### Database Schema

Key tables:
- `events` - Main event storage with geocoded locations
- `local_shops` - Business directory with full profiles
- `calendar_sources` - Scraping source configurations
- `event_categories` - Hierarchical categorization
- `scraping_logs` - Monitoring and debugging

### Integration Points

Designed to integrate with existing CMS:
- Uses existing user authentication system
- Leverages current admin interface patterns
- Shares database and file upload systems
- Follows established routing conventions

## Map Features

### Interactive Map Display
- Clustered markers for events and shops
- Custom icons for different types
- Info windows with details and actions
- Mobile-optimized touch controls

### Location Services
- GPS-based "Events Near Me"
- Radius filtering with slider control
- Distance calculations and sorting
- Driving directions integration

### Shop Discovery
- Business categories and filtering
- Hours of operation display
- Contact information and websites
- Integration with events (nearby businesses)

## Performance Optimizations

### Caching Strategy
- Geocoding results cached locally
- Database queries optimized with indexes
- API responses cached when appropriate
- Static assets optimized for CDN

### Database Indexes
```sql
INDEX idx_events_location (latitude, longitude)
INDEX idx_events_datetime (start_datetime)
INDEX idx_events_status (status)
INDEX idx_shops_location (latitude, longitude)
INDEX idx_shops_category (category_id)
```

### Mobile Optimization
- Responsive design with mobile-first approach
- Touch-friendly interface elements
- Optimized map performance on mobile
- Progressive loading for large datasets

## Security Considerations

### Input Validation
- All user inputs sanitized and validated
- SQL injection prevention with prepared statements
- XSS protection with output escaping
- CSRF tokens for admin forms

### API Security
- Rate limiting on public endpoints
- Authentication required for admin functions
- Input validation on all API calls
- Secure session management

### Scraping Security
- Respect robots.txt files
- Implement rate limiting
- User-agent identification
- Error handling for blocked requests

## Monitoring and Logging

### System Health
- Daily scraping success/failure tracking
- Database performance monitoring
- API response time tracking
- Error rate monitoring

### Admin Notifications
- Email alerts for failed scraping
- Weekly summary reports
- Critical error notifications
- New event submission alerts

### Log Files
- Scraping activity logs
- Error and warning logs
- Performance monitoring logs
- User activity logs (admin actions)

## Troubleshooting

### Common Issues

**Scraping Failures:**
1. Check source URL accessibility
2. Verify scraping configuration
3. Review robots.txt compliance
4. Check rate limiting settings

**Geocoding Issues:**
1. Verify Google Maps API key
2. Check API quotas and limits
3. Validate address formats
4. Review error logs for details

**Map Display Problems:**
1. Confirm Google Maps API key
2. Check browser console for errors
3. Verify coordinate validity
4. Test on different devices/browsers

### Debug Mode

Enable debug logging in development:

```php
// In config/debug.php
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
```

### Support

For technical support:
1. Check error logs in `/logs/` directory
2. Review admin dashboard for system status
3. Test individual components via admin interface
4. Consult API documentation for integration issues

## Contributing

### Development Setup

1. Clone repository
2. Install dependencies: `composer install`
3. Set up development database
4. Configure `.env` file
5. Run database migrations
6. Set up local web server

### Code Standards

- Follow PSR-4 autoloading
- Use meaningful variable and function names
- Comment complex logic
- Write unit tests for new features
- Follow existing code style patterns

### Testing

Run test suite:
```bash
./vendor/bin/phpunit tests/
```

Test coverage areas:
- Model CRUD operations
- API endpoint responses
- Scraping functionality
- Geocoding services
- Input validation

## Modules

### YFClaim - Estate Sale Platform
YFClaim is a modular extension that provides Facebook-style claim sales for estate sale companies:

- **Seller Management**: Estate sale companies can register and manage their sales
- **Claim Sales**: Items are posted with starting prices, buyers make offers
- **Offer System**: Price ranges shown to buyers, sellers choose winning offers
- **QR Code Access**: Easy buyer access via QR codes at physical sales
- **Admin Interface**: Complete management dashboard for overseeing all sales

**Status**: Database ready, admin interface functional, models need implementation
**Documentation**: See `modules/yfclaim/README.md` for details

## Current Status

### ✅ Fully Functional
- Event calendar with map integration
- Event scraping from multiple sources
- Local business directory with geocoding
- Advanced admin interface
- Shop management with JSON operating hours
- Geocoding verification and repair tools

### 🚧 In Development
- YFClaim seller/buyer interfaces (admin ready, models need implementation)
- Enhanced notification system
- Advanced reporting and analytics

## Security

⚠️ **Important**: This project includes sensitive configuration files. See `SECURITY.md` for proper setup and deployment guidelines.

## License

This project is proprietary software developed for yakimafinds.com. All rights reserved.

## Changelog

### Version 1.2.0 (Current)
- Fixed advanced admin functionality and 500 errors
- Fixed geocoding namespace issues  
- Added YFClaim module foundation with database schema
- Enhanced security documentation and API key management
- Improved shop management with proper JSON handling

### Version 1.1.0
- Added intelligent AI-powered event scraper
- Enhanced admin interface with better navigation
- Improved error handling and logging

### Version 1.0.0 (Initial Release)
- Complete calendar system with map integration
- Event scraping from multiple source types
- Local business directory
- Mobile-responsive interface
- Administrative dashboard
- Automated geocoding and duplicate detection