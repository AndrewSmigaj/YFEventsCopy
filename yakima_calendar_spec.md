# Yakima Finds Event Calendar - Technical Specification

## 1. Project Overview

### 1.1 Purpose
Develop a web-based event calendar system for yakimafinds.com that aggregates local events from multiple sources through automated scraping and user submissions, with administrative oversight for content quality control.

### 1.2 Key Features
- Responsive web calendar (desktop & mobile)
- Automated daily scraping of external event sources
- Admin interface for source management and content moderation
- Public event submission form
- Source attribution for all events
- Pending approval queue system

## 2. System Architecture

### 2.1 Technology Stack - yakimafinds.com Environment

**Server Environment (Confirmed):**
- **Server:** Apache/2.4.61 (Debian)
- **PHP:** 8.2.22 (Excellent - modern version with all latest features)
- **Document Root:** `/var/www/html`
- **Memory Limit:** 2GB (Excellent for processing)
- **Database:** MySQL with PDO support ✅

**Available PHP Extensions:**
- ✅ PDO & PDO_MySQL (Database connectivity)
- ✅ CURL (Web scraping capabilities)
- ✅ JSON (Data processing)
- ✅ XML & DOM (HTML/XML parsing for scraping)
- ✅ OpenSSL (HTTPS requests)
- ✅ ZIP (File compression)
- ✅ MBString (String handling)
- ❌ GD (Image processing - **recommend installing**)

**Integration Approach: Direct CMS Extension**
- **Method:** Extend existing custom CMS in `/var/www/html`
- **Admin Integration:** Use existing `/admin` directory
- **TinyMCE:** Leverage confirmed `/tinymce` installation
- **Database:** Extend current MySQL database with calendar tables
- **Templates:** Use existing `/templates` system
- **AJAX:** Extend existing `/ajax` endpoints

**Recommended Technology Stack:**
- **Backend:** PHP 8.2 (matching existing environment)
- **Database:** MySQL (extend existing database)
- **Frontend:** JavaScript/jQuery (likely already in use)
- **Editor:** Existing TinyMCE installation
- **Scheduling:** PHP cron jobs or system cron
- **File Uploads:** Use existing `/uploads` directory structure

### 2.2 System Components
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Public Web    │    │  Admin Panel    │    │  Scraping       │
│   Interface     │    │                 │    │  Service        │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │   API Server    │
                    └─────────────────┘
                                 │
                    ┌─────────────────┐
                    │   Database      │
                    └─────────────────┘
```

## 3. Database Schema

### 3.1 Core Tables (Extended CMS Integration)

**Integration with Existing CMS:**
If extending the current CMS, the calendar tables would integrate with existing user management and content systems.

**events**
```sql
id (PRIMARY KEY)
title (VARCHAR, NOT NULL)
description (TEXT) -- Can use existing TinyMCE editor
start_datetime (TIMESTAMP, NOT NULL)
end_datetime (TIMESTAMP)
location (VARCHAR)
address (TEXT)
contact_info (JSON)
external_url (VARCHAR)
source_id (FOREIGN KEY to calendar_sources)
cms_user_id (FOREIGN KEY to existing CMS users table)
status (ENUM: 'pending', 'approved', 'rejected')
featured (BOOLEAN DEFAULT false) -- For homepage display
created_at (TIMESTAMP)
updated_at (TIMESTAMP)
scraped_at (TIMESTAMP)
external_event_id (VARCHAR)
```

**calendar_sources** (New table)
```sql
id (PRIMARY KEY)
name (VARCHAR, NOT NULL)
url (VARCHAR, NOT NULL)
scrape_type (ENUM: 'ical', 'html', 'json')
scrape_config (JSON)
last_scraped (TIMESTAMP)
active (BOOLEAN, DEFAULT true)
created_by (FOREIGN KEY to CMS users)
created_at (TIMESTAMP)
```

**calendar_permissions** (New table for CMS integration)
```sql
id (PRIMARY KEY)
user_id (FOREIGN KEY to CMS users)
permission (ENUM: 'view_pending', 'approve_events', 'manage_sources', 'admin')
created_at (TIMESTAMP)
```

**Integration with Existing CMS Tables:**
- Leverage existing user authentication and roles
- Use existing file/media management for event images
- Integrate with existing site navigation and templates
- Utilize current SEO and meta tag systems

## 4. User Interfaces

### 4.1 Public Calendar Interface

**Calendar View Features:**
- Month, week, and day views
- **Map View:** Interactive map showing today's events and local shops
- Event filtering by category/source/location
- Mobile-responsive design with touch-friendly map controls
- Event detail modal with source attribution
- Search functionality with location-based filtering
- Export options (iCal)
- **Location Services:** GPS-based "Events Near Me" functionality

**Map Integration Features:**
- **Today's Events Map:** Shows all events happening today with clickable pins
- **Local Shops Map:** Display partner/local businesses as map pins
- **Combined View:** Toggle between events-only, shops-only, or combined map
- **Cluster Management:** Group nearby pins to prevent overcrowding
- **Custom Pin Icons:** Different icons for events vs shops vs featured locations
- **Distance Calculations:** Show distances from user's location
- **Driving Directions:** Link to Google Maps for navigation

**Event Detail Display:**
- Event title and description
- Date/time information
- **Interactive Map:** Embedded map showing exact event location
- **Nearby Shops:** Show local businesses within walking distance
- Contact information
- Source attribution with link
- "Add to Calendar" functionality
- **Get Directions** button

**Local Shops Integration:**
- **Shop Directory:** Searchable list of local businesses
- **Shop Detail Pages:** Full business information with map
- **Hours of Operation:** Real-time open/closed status
- **Categories:** Filter shops by type (antiques, restaurants, retail, etc.)
- **Featured Shops:** Highlight premium/partner businesses

### 4.2 Public Event Submission Form

**Form Fields:**
- Event title (required)
- Description
- Start date/time (required)
- End date/time
- Location name
- Full address
- Contact email/phone
- Website URL
- Category selection
- Submitter email (optional)

**Form Features:**
- Client-side validation
- Duplicate event detection
- Success/error messaging
- Captcha for spam prevention

### 4.3 CMS-Integrated Admin Interface

**Extend Existing CMS Admin Panel:**
- **Calendar Management Tab:** Add to existing admin navigation
- **User Role Integration:** Leverage existing user roles and permissions
- **Content Editor:** Use existing TinyMCE setup for event descriptions
- **Media Integration:** Use existing file upload and media management
- **SEO Integration:** Leverage existing meta tag and SEO systems

**Admin Dashboard Integration:**
- Calendar widget on main CMS dashboard
- Pending events notification in existing notification system
- Recent activity feed including calendar events
- Site statistics including event engagement metrics

**Event Management Interface:**
- List view with existing CMS table styling
- Bulk actions using existing CMS patterns
- Form validation using existing CMS validation systems
- File uploads through existing media management
- **Location Management:** 
  - Address autocomplete with Google Places API
  - Geocoding for automatic latitude/longitude
  - Map preview when adding/editing events
  - Batch geocoding for imported events

**Local Shops Management:**
- **Comprehensive Shop Directory:** Full business profiles with contact info, hours, images
- **Business Owner Accounts:** Self-service shop management portal
- **Content Approval Workflow:** Admin review of shop owner updates
- **Bulk Import/Export:** CSV tools for managing multiple businesses
- **Business Verification System:** Verify legitimate businesses
- **Claim Management:** Allow owners to claim existing listings
- **Image Management:** Logo, cover photos, and gallery images
- **SEO Optimization:** Custom URLs, meta tags, structured data

**Advanced Shop Features:**
- **Business Categories:** Hierarchical organization (Retail > Antiques > Vintage Furniture)
- **Operating Hours:** Complex schedules including seasonal and holiday hours
- **Payment Methods:** Track accepted payment types
- **Accessibility Features:** Wheelchair access, hearing assistance, etc.
- **Amenities Tracking:** Parking, WiFi, restrooms, etc.
- **Social Media Integration:** Links to Facebook, Instagram, Google Business
- **Review System:** Customer reviews with admin moderation (future feature)
- **Analytics Dashboard:** View engagement metrics for shop owners

**Shop Owner Portal:**
- **Account Management:** Business owner registration and verification
- **Profile Editor:** Update business information, hours, images
- **Event Integration:** Create events associated with their business
- **Promotional Tools:** Special offers tied to calendar events
- **Analytics Access:** View visitor statistics and engagement
- **Multi-User Support:** Managers and employees can have limited access
- **Change Tracking:** All edits logged for admin review

**Map Settings Management:**
- **Google Maps API Key:** Secure key management
- **Default Map Settings:** Center point, zoom level, map style
- **Pin Icon Management:** Upload custom icons for different categories
- **Map Boundaries:** Set viewing area limits for local focus
- **Clustering Settings:** Configure pin grouping behavior

**Source Management:**
- Integrated with existing CMS settings pages
- Form builders using existing CMS form components
- Configuration saved in existing CMS settings system
- Test scraping with existing job/task management

### 4.4 Frontend Integration

**Template Integration:**
- Calendar views using existing site templates
- Consistent navigation with current site structure
- Shared CSS/styling framework
- Mobile responsive using existing responsive patterns

**Page Integration:**
- `/events` - Main calendar page
- `/events/[slug]` - Individual event pages
- `/events/submit` - Public event submission form
- Calendar widget for homepage/sidebar integration

**SEO and Meta Integration:**
- Use existing meta tag system
- Integrate with existing sitemap generation
- Leverage existing structured data implementation
- Social sharing using existing Open Graph setup

## 5. API Endpoints

### 5.1 Public API

```
GET /api/events
- Query params: start_date, end_date, category, limit, offset, lat, lng, radius
- Returns: Paginated list of approved events with location data

GET /api/events/:id
- Returns: Detailed event information with coordinates

POST /api/events/submit
- Body: Event submission data including address
- Returns: Submission confirmation with geocoded coordinates

GET /api/events/today
- Returns: Today's events with location data for map display

GET /api/events/nearby
- Query params: lat, lng, radius (in miles)
- Returns: Events within specified radius of coordinates

GET /api/shops
- Query params: category, featured, active, lat, lng, radius
- Returns: Local shops with location and business information

GET /api/shops/categories
- Returns: Hierarchical list of business categories with icons

GET /api/shops/:id/events
- Returns: Events associated with this shop (hosting or nearby)

GET /api/shops/search
- Query params: q, category, location, radius, amenities
- Returns: Filtered shop results with relevance scoring

GET /api/shops/:slug
- Returns: Shop details by SEO-friendly URL slug

POST /api/shops/claim
- Body: Business claim request with owner contact info
- Returns: Claim submission confirmation

GET /api/shops/reviews/:id
- Query params: limit, offset, rating_filter
- Returns: Customer reviews for shop (when review system implemented)
```

### 5.2 CMS Integration API

**Internal CMS Integration:**
```
GET /api/cms/events
- Integration with existing CMS content API
- Shared authentication with CMS user system
- Consistent response format with other CMS endpoints

POST /api/cms/events
- Create events through existing CMS workflow
- Use existing validation and sanitization
- Integrate with existing user permission system

GET /api/cms/events/nearby
- Body: Location coordinates and radius
- Returns: Events within specified area

GET /api/cms/shops
- Returns: All local shops for admin management
- Authentication: Admin required

POST /api/cms/shops
- Body: New shop data with address
- Returns: Created shop with geocoded coordinates
- Auto-geocodes address to coordinates

PUT /api/cms/shops/:id
- Body: Shop updates
- Returns: Updated shop information

GET /api/cms/shops/:id/claim
- Body: Owner verification information
- Returns: Claim request status
- Allows business owners to claim their listing

POST /api/cms/shops/:id/verify
- Body: Verification documents/information
- Returns: Verification status
- Admin endpoint for business verification

GET /api/cms/shop-analytics/:id
- Query params: date_range, metrics
- Returns: Engagement analytics for shop
- Restricted to shop owners and admins

POST /api/cms/shop-owners
- Body: New shop owner registration
- Returns: Account creation status with email verification

PUT /api/cms/shop-owners/:id/permissions
- Body: Permission updates for shop access
- Returns: Updated permissions
- Admin-only endpoint

GET /api/cms/pending-updates
- Returns: Shop updates awaiting admin approval
- Admin review workflow

PUT /api/cms/updates/:id/approve
- Body: Approval decision and notes
- Returns: Update approval status
```

**Authentication Integration:**
- Use existing CMS session management
- Leverage existing user roles and permissions
- Single sign-on with current admin system
- Existing password reset and user management flows

## 6. Scraping System

### 6.1 Supported Formats
- **iCal/ICS files**: Standard calendar format
- **HTML scraping**: Custom CSS selectors for event data
- **JSON APIs**: Structured data from event platforms
- **Common platforms**: Eventbrite, Facebook Events, Meetup

### 6.2 Address Geocoding Process

**Automatic Location Processing:**
1. **Address Input:** User enters address or location name
2. **Google Places Autocomplete:** Suggest valid addresses during typing
3. **Geocoding API Call:** Convert address to latitude/longitude
4. **Validation:** Verify coordinates are within reasonable bounds
5. **Storage:** Save both address and coordinates in database
6. **Fallback:** Manual coordinate entry if geocoding fails

**Batch Processing:**
- **Scraping Integration:** Automatically geocode addresses from scraped events
- **Import Tools:** Bulk geocoding for CSV imports
- **Existing Data:** Batch process existing events without coordinates
- **Error Handling:** Queue failed geocoding attempts for manual review

### 6.3 Map Display System

**Google Maps Integration:**
- **API Key Management:** Secure key storage and rotation
- **Map Types:** Roadmap, satellite, hybrid, terrain options
- **Responsive Design:** Adaptive sizing for mobile and desktop
- **Performance:** Lazy loading and efficient marker management

**Marker Management:**
- **Clustering:** Group nearby markers to prevent overcrowding
- **Custom Icons:** Different icons for events, shops, featured items
- **Info Windows:** Rich content popups with event/shop details
- **Filtering:** Real-time show/hide based on user preferences

**User Experience:**
- **Current Location:** GPS-based "find me" functionality
- **Directions:** Integration with device navigation apps
- **Search:** Address-based map search and centering
- **Accessibility:** Keyboard navigation and screen reader support
1. **Scheduled Execution**: Daily at configurable time
2. **Source Processing**: Iterate through active sources
3. **Data Extraction**: Parse events based on source type
4. **Address Geocoding**: Automatically geocode event locations
5. **Deduplication**: Compare against existing events (including location proximity)
6. **Queue Population**: Add new events to pending queue
7. **Logging**: Record scraping results and geocoding success/failures

### 6.3 Error Handling
- Retry logic for failed requests
- Graceful degradation for partial failures
- Administrator notifications for persistent issues
- Automatic source deactivation for repeated failures

## 7. Event Deduplication

### 7.1 Matching Criteria
- Title similarity (fuzzy matching)
- Date/time proximity
- **Location proximity** (within 0.1 miles of existing event)
- External event ID tracking

### 7.2 Duplicate Resolution
- Merge complementary information including location data
- Preserve source attribution
- **Coordinate accuracy**: Choose most precise location data
- Admin review for uncertain matches

## 8. Security Considerations

### 8.1 Admin Panel Security
- JWT-based authentication
- Role-based access control
- Session timeout handling
- CSRF protection
- Input validation and sanitization

### 8.2 Scraping Security
- Rate limiting to prevent blocking
- User-agent rotation
- Respect robots.txt
- IP rotation for sensitive sources

### 8.3 Public Interface Security
- SQL injection prevention
- XSS protection
- CAPTCHA for form submissions
- Input validation
- File upload restrictions

## 9. Performance Optimization

### 9.1 Caching Strategy
- Redis caching for frequently accessed events
- CDN for static assets
- Database query optimization
- API response caching

### 9.2 Database Optimization
- Appropriate indexing on date, location, and status fields
- Query optimization for calendar views
- Pagination for large datasets
- Archive old events

## 10. Mobile Responsiveness

### 10.1 Design Considerations
- Touch-friendly interface elements with larger tap targets
- **Mobile-optimized maps** with gesture controls
- Collapsible event details and shop information
- **GPS integration** for location-based features
- Fast loading on mobile connections
- **Offline map caching** for better mobile performance

### 10.2 Progressive Web App Features
- Offline calendar viewing with cached map tiles
- **Geolocation permissions** handling
- Push notifications for nearby events
- App-like installation option
- **Location-based notifications** (events happening nearby)

## 11. Deployment Architecture

### 11.1 Production Environment

**Current Server Configuration:**
- **Server:** Apache/2.4.61 (Debian) - ✅ Production ready
- **PHP:** 8.2.22 - ✅ Latest stable version
- **Memory:** 2GB limit - ✅ Excellent for calendar processing
- **Storage:** Existing `/uploads` directory structure
- **Database:** MySQL with PDO - ✅ Ready for calendar tables

**Integration Deployment:**
- **Location:** Direct integration into existing `/var/www/html`
- **Database:** Extend current MySQL database (no additional costs)
- **File Structure:** Follow existing directory patterns
- **Backup:** Integrate with existing backup procedures
- **SSL:** **Recommend adding HTTPS** (currently HTTP only)

**Required Server Updates:**
1. **Install GD Extension:** For event image processing
   ```bash
   sudo apt-get install php8.2-gd
   sudo systemctl reload apache2
   ```

2. **Optional: Increase Upload Limit** (currently 2MB):
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

3. **SSL Certificate:** Install Let's Encrypt for HTTPS
   ```bash
   sudo certbot --apache -d yakimafinds.com
   ```

**Cron Job Setup** (for daily scraping):
```bash
# Add to crontab for daily scraping at 2 AM
0 2 * * * php /var/www/html/admin/cron/scrape-events.php
```

**Performance Optimizations:**
- **PHP OPcache:** Already available in PHP 8.2
- **Apache mod_rewrite:** For clean calendar URLs
- **Database indexing:** On event dates and locations
- **File compression:** Use existing server compression

### 11.2 Staging Environment
- Mirror of production for testing
- Automated deployment pipeline
- Integration testing suite

## 12. Monitoring and Analytics

### 12.1 System Monitoring
- Application performance monitoring
- Database performance metrics
- Scraping success rates
- Error tracking and alerting

### 12.2 Usage Analytics
- Calendar view statistics (list vs map views)
- **Map interaction metrics** (zoom, pan, marker clicks)
- Popular events and shop locations
- **Geographic usage patterns** (where users are viewing from)
- Source effectiveness metrics
- **Mobile location service usage**
- User engagement with directions and navigation features

## 13. Data Privacy and Compliance

### 13.1 Data Handling
- Privacy policy for user submissions
- GDPR compliance considerations
- Data retention policies
- User consent for analytics

### 13.2 Source Compliance
- Respect source terms of service
- Attribution requirements
- Content licensing considerations

## 14. Future Enhancements

### 14.1 Phase 2 Features
- User accounts for personalized calendars and favorite shops
- Event categories and advanced tagging
- **Location-based push notifications** ("Event starting nearby!")
- Social sharing integration with location context
- Email notifications for events in preferred areas
- **Advanced mapping features** (traffic, street view integration)

### 14.2 Advanced Features
- Machine learning for event categorization and location prediction
- Automatic event image extraction from venues
- **Augmented reality** directions to events
- Weather integration with location-specific forecasts
- Community voting/rating system for events and shops
- **Multi-city expansion** framework
- **Business partnership integration** (shop promotions tied to events)

## 15. Success Metrics

### 15.1 Technical Metrics
- 99.9% uptime target
- <2 second page load times
- 95% scraping success rate
- <5% duplicate event rate

### 15.2 Business Metrics
- Number of unique events per month
- **Map engagement** (time spent on map views, directions requested)
- User engagement (page views, time on site)
- Event submission rate
- **Local business directory usage**
- Source diversity and coverage
- **Mobile location service adoption rate**
- **Shop discovery through event proximity**

## 17. Integration with yakimafinds.com Custom CMS

### 17.1 Current Site Analysis

**Platform Assessment:**
- **Architecture:** Custom PHP application with PSR-4 autoloading
- **Web Root:** `www/html/` (contains admin, templates, css, media, etc.)
- **Application Source:** `yakimafinds/src/` (Models, Routes, PageView, etc.)
- **Dependencies:** Composer-managed with TinyMCE in vendor directory
- **Structure:** Modern MVC architecture with separate concerns

**Key Directories Identified:**
- `www/html/admin/` - Administrative interface
- `www/html/templates/` - Template system
- `yakimafinds/src/Models/` - Data models
- `yakimafinds/src/Routes/` - URL routing
- `yakimafinds/src/PageView/` - View controllers
- `yakimafinds/vendor/tinymce/` - TinyMCE rich text editor

### 17.2 Deep Integration Strategy

**Database Integration:**
- **Location:** Likely configured in `pst/database/` or application config
- **Approach:** Extend existing Models in `yakimafinds/src/Models/`
- **New Models:** EventModel, SourceModel, EventSourceModel
- **Tables:** Add calendar tables to existing database schema

**Admin Interface Integration:**
- **Location:** Extend `www/html/admin/` interface
- **Authentication:** Use existing Authenticators from `yakimafinds/src/Authenticators/`
- **Navigation:** Add calendar management to existing admin navigation
- **Forms:** Use existing form patterns and TinyMCE integration

**Frontend Integration:**
- **Templates:** Add calendar templates to `www/html/templates/`
- **Routes:** Add calendar routes to `yakimafinds/src/Routes/`
- **Views:** Create calendar PageView controllers in `yakimafinds/src/PageView/`
- **Assets:** Add calendar CSS/JS to existing `www/html/css/` and related directories

### 17.3 Recommended File Structure for Calendar

**New Files to Add:**

```
yakimafinds/src/Models/
├── EventModel.php
├── EventSourceModel.php
└── CalendarModel.php

yakimafinds/src/PageView/
├── CalendarPageView.php
├── EventPageView.php
└── AdminCalendarPageView.php

yakimafinds/src/Routes/
├── CalendarRoutes.php
└── AdminCalendarRoutes.php

www/html/templates/
├── calendar/
│   ├── calendar.php
│   ├── event-detail.php
│   ├── event-list.php
│   └── event-submit.php
└── admin/
    ├── calendar-dashboard.php
    ├── event-management.php
    └── source-management.php

www/html/admin/
├── calendar/
│   ├── index.php
│   ├── events.php
│   ├── sources.php
│   └── settings.php

www/html/css/
└── calendar.css

www/html/ajax/
├── calendar-events.php
├── approve-event.php
└── test-source.php
```

**Integration Points:**
- Use existing authentication system from `Authenticators/`
- Extend current admin interface patterns
- Leverage existing TinyMCE setup for event descriptions
- Follow existing routing and MVC patterns
- Use current template system and CSS framework

### 17.4 Development Approach

**Phase 1: Model Integration**
- Create calendar models following existing patterns in `Models/`
- Extend database schema with calendar tables
- Integrate with existing authentication system

**Phase 2: Admin Interface**
- Add calendar management to existing admin in `www/html/admin/`
- Create calendar-specific admin pages
- Integrate with existing admin navigation and permissions

**Phase 3: Frontend Integration**
- Add calendar routes following existing routing patterns
- Create calendar templates using existing template system
- Add calendar views to site navigation

**Phase 4: API and Scraping**
- Create AJAX endpoints in `www/html/ajax/` for calendar functions
- Implement scraping service using existing architectural patterns
- Add calendar widgets for homepage integration

**Phase 0 (Week 1): CMS Analysis and Planning**
- Analyze existing CMS architecture and codebase
- Identify integration points and database schema
- Plan calendar table structure and relationships
- Document existing user roles and permissions
- Set up development environment matching production

**Phase 1 (Weeks 2-5): Core Integration**
- Extend existing database with calendar and shop tables
- Integrate calendar admin into existing CMS admin panel
- Create calendar API endpoints following existing patterns
- Implement user permission integration
- **Set up Google Maps API** and basic map functionality

**Phase 2 (Weeks 6-9): Scraping and Location Services**
- Scraping engine development with address geocoding
- Source management interface
- Event approval workflow
- **Local shops management system**
- **Address autocomplete and geocoding integration**

**Phase 3 (Weeks 10-13): Frontend Integration and Maps**
- Create calendar pages using existing template system
- **Implement interactive maps** with events and shops
- Add calendar widgets to homepage and relevant pages
- **Mobile-optimized map interfaces**
- Integrate with existing site navigation and SEO
- **Location-based filtering and search**
- User acceptance testing with existing CMS users
- Production deployment following existing procedures

**Phase 4 (Ongoing): Enhancement and Optimization**
- **Advanced map features** (clustering, custom icons)
- **Location-based notifications** (future)
- Source maintenance and monitoring
- **Shop directory expansion**
- Performance monitoring and optimization
- **Analytics integration** for map usage tracking
- Source maintenance
- Feature enhancements
- Performance monitoring