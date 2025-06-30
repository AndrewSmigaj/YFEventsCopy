# Client-side Browser Scraper for YFEvents

## Overview

This is a browser automation scraper designed to run on your **local computer** instead of the server. This approach has several advantages:

- ✅ **No server dependencies** - Uses your local Chrome installation
- ✅ **More reliable** - Your local browser has better anti-bot evasion
- ✅ **Better performance** - Runs on your machine's resources
- ✅ **Easier debugging** - Can run in non-headless mode to see what's happening

## Setup

### 1. Install Node.js (if not already installed)
Download from: https://nodejs.org/

### 2. Install Dependencies
```bash
cd /path/to/YFEvents/scripts/browser-automation
npm install
```

### 3. Test Installation
```bash
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=1 --debug
```

## Usage

### Basic Scraping
```bash
# Scrape Eventbrite for Yakima events (3 pages)
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=3

# Scrape Meetup events (5 pages)
node client-scraper.js --config=meetup --location="Yakima, WA" --pages=5

# Debug mode (shows browser window)
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=1 --debug
```

### Available Configurations
- `eventbrite` - Major event platform
- `meetup` - Community meetups and events
- `facebook-events` - Facebook public events (experimental)

### Command Options
- `--config=NAME` - Scraper configuration to use
- `--location="City, State"` - Location to search for events
- `--pages=N` - Maximum number of pages to scrape
- `--debug` - Run in visible mode (shows browser window)

## Output

The scraper generates CSV files in the `output/` directory:
```
output/
├── eventbrite-events-2025-06-16T10-30-00.csv
├── meetup-events-2025-06-16T10-35-00.csv
└── ...
```

## Upload to YFEvents

### Manual Upload (Recommended)
1. Run the scraper locally to generate CSV files
2. Go to: `https://backoffice.yakimafinds.com/refactor/admin/browser-scrapers.php`
3. Look for "Upload CSV" section (to be implemented)
4. Upload your CSV file
5. Review imported events in admin

### Automatic Upload (Advanced)
The scraper can also POST data directly to your server:

```bash
# Add server endpoint for automatic upload
node client-scraper.js --config=eventbrite --location="Yakima, WA" --upload
```

## Configuration Files

Each scraper uses a JSON configuration file in `configs/`:

```json
{
  "site_name": "Eventbrite",
  "search_url": "https://www.eventbrite.com/d/online/{location}/",
  "selectors": {
    "event_container": "[data-testid='event-card']",
    "event_link": "a[href*='/e/']",
    "title": "h3[data-testid='event-title']",
    "start_date": "[data-testid='start-date']",
    "location": "[data-testid='event-location']"
  }
}
```

## Troubleshooting

### Chrome Issues
If Chrome fails to launch:
```bash
# Check Chrome installation
google-chrome --version
# or
chromium --version

# Run with additional debugging
node client-scraper.js --config=eventbrite --debug
```

### Permission Issues
```bash
# Make sure script is executable
chmod +x client-scraper.js

# Check Node.js version
node --version  # Should be 14+ 
```

### Network Issues
- Use `--debug` mode to see what the scraper is doing
- Check if the target websites are accessible from your location
- Some sites may require VPN if blocked regionally

## Advanced Features

### Custom Headers
```javascript
// In config file, add:
"headers": {
  "User-Agent": "Custom user agent",
  "Accept-Language": "en-US,en;q=0.9"
}
```

### Proxy Support
```javascript
// Add to config:
"proxy": {
  "server": "http://proxy.example.com:8080",
  "username": "user",
  "password": "pass"
}
```

### Anti-Detection
The scraper includes built-in anti-detection:
- Random delays between requests
- Human-like mouse movements
- Realistic user agents
- Cookie handling

## CSV Format

Generated CSV files contain these columns:
- `title` - Event name
- `description` - Event description
- `start_date` - Event start date
- `start_time` - Event start time
- `end_date` - Event end date (if available)
- `end_time` - Event end time (if available)
- `location` - Event location/venue
- `address` - Full address (if available)
- `organizer` - Event organizer
- `price` - Ticket price information
- `category` - Event category
- `url` - Original event URL
- `source` - Source website name
- `scraped_at` - Timestamp of scraping

## Integration with YFEvents

The CSV format is designed to be compatible with the YFEvents database schema. When uploaded, events will:

1. **Validation**: Check for required fields (title, date)
2. **Deduplication**: Compare against existing events
3. **Geocoding**: Automatically geocode addresses
4. **Categorization**: Auto-categorize based on keywords
5. **Approval Queue**: All imported events require admin approval

## Next Steps

1. **Test locally** with a small scrape (`--pages=1`)
2. **Review CSV output** to ensure data quality
3. **Upload to admin interface** for integration
4. **Set up automated runs** on your schedule
5. **Monitor for changes** in website structure