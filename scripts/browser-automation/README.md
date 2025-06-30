# YFEvents Browser Automation Scraper

A modular, configurable browser automation framework for scraping events from various sources that have anti-bot protection.

## 🚀 Features

- **Configurable**: JSON-based configuration files for different event sources
- **Anti-Detection**: Built-in measures to bypass bot detection
- **Database Integration**: Direct integration with YFEvents refactored system
- **Multiple Outputs**: Save to database and/or CSV files
- **Robust**: Error handling, retry logic, and graceful failures
- **Modular**: Easy to add new event sources

## 📋 Requirements

- Node.js 16+
- PHP 8.1+ (for database bridge)
- YFEvents refactored system
- Web server (Apache/Nginx) for database bridge

## 🛠️ Installation

```bash
# Navigate to the browser automation directory
cd /home/robug/YFEvents/scripts/browser-automation

# Run the setup script
chmod +x setup.sh
./setup.sh
```

## 🎯 Quick Start

### Scrape Eventbrite Events
```bash
npm run eventbrite
```

### Scrape Meetup Events
```bash
npm run meetup
```

### Test Mode (Single Page, Visible Browser)
```bash
npm test
```

### Custom Location and Settings
```bash
node scraper.js --config=eventbrite --location="Seattle, WA" --pages=5 --debug
```

## 📖 Usage Options

### Command Line Arguments

| Option | Description | Default | Example |
|--------|-------------|---------|---------|
| `--config` | Configuration file name | `eventbrite` | `--config=meetup` |
| `--location` | Search location | `Yakima, WA` | `--location="Seattle, WA"` |
| `--pages` | Maximum pages to scrape | `10` | `--pages=5` |
| `--csv-only` | Export to CSV only (skip database) | `false` | `--csv-only` |
| `--headless` | Run browser in headless mode | `true` | `--headless` |
| `--no-headless` | Run with visible browser | `false` | `--no-headless` |
| `--debug` | Enable debug logging | `false` | `--debug` |
| `--output` | Output directory | `./output` | `--output=./data` |
| `--test` | Test mode (1 page, visible) | `false` | `--test` |

### Usage Examples

```bash
# Basic scraping
npm run eventbrite

# Debug mode with visible browser
node scraper.js --config=eventbrite --debug --no-headless

# CSV export only
node scraper.js --config=meetup --csv-only --pages=3

# Custom location
node scraper.js --config=eventbrite --location="Portland, OR"

# Test specific configuration
node scraper.js --config=facebook-events --test
```

## 🔧 Configuration Files

Configuration files are stored in `./configs/` and define how to scrape each event source.

### Available Configurations

| Config | Source | Status | Notes |
|--------|--------|--------|-------|
| `eventbrite.json` | Eventbrite | ✅ Ready | Requires anti-bot measures |
| `meetup.json` | Meetup.com | ✅ Ready | More permissive |
| `facebook-events.json` | Facebook Events | ⚠️ Experimental | High anti-bot protection |

### Creating Custom Configurations

Create a new JSON file in `./configs/` with the following structure:

```json
{
  "sourceName": "Event Source Name",
  "sourceId": "unique-source-id",
  "baseUrl": "https://example.com",
  "searchUrl": "https://example.com/search?location={{location}}",
  
  "waitSelectors": ["selector-to-wait-for"],
  "dismissSelectors": ["popup-close-selectors"],
  
  "selectors": {
    "eventContainer": ".event-item",
    "fields": {
      "title": {
        "selector": "h3",
        "transform": "clean"
      },
      "start_date": {
        "selector": "time",
        "attribute": "datetime",
        "transform": "date"
      }
    }
  },
  
  "pagination": {
    "type": "url",
    "urlTemplate": "https://example.com/search?location={{location}}&page={{page}}"
  }
}
```

## 💾 Database Integration

The scraper integrates with your YFEvents refactored system through a PHP database bridge:

- **Bridge File**: `database-bridge.php`
- **Database Config**: Uses existing `/www/html/refactor/config/database.php`
- **Event Storage**: Saves to existing `events` table
- **Deduplication**: Automatically prevents duplicate events

### Database Bridge API

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `?action=test` | GET | Test database connection |
| `?action=stats` | GET | Get event statistics |
| `?action=save` | POST | Save single event |
| `?action=batch-save` | POST | Save multiple events |

## 📊 Output

### CSV Files
- Location: `./output/`
- Format: `{source}_events_{date}.csv`
- Contains: All scraped event data

### Database
- Integrates with YFEvents refactored system
- Events marked as 'pending' status
- Requires admin approval in YFEvents admin interface

### Logs
- Location: `./output/scraper.log`
- Debug information and error messages

## 🛡️ Anti-Detection Features

- **Random Delays**: Between page loads and interactions
- **Human-like Behavior**: Mouse movements, scrolling, typing
- **Real Browser**: Uses actual Chrome/Chromium via Puppeteer
- **Configurable Headers**: Realistic user agent and headers
- **Cookie Handling**: Accepts cookies and manages sessions

## 🚨 Troubleshooting

### Common Issues

#### Database Connection Failed
```bash
# Check PHP bridge accessibility
curl http://localhost/scripts/browser-automation/database-bridge.php?action=test

# Verify web server configuration
sudo systemctl status apache2  # or nginx
```

#### Eventbrite Returns 405 Errors
- This is normal - Eventbrite has strong anti-bot protection
- Try running with `--no-headless` to see what's happening
- Consider using smaller page counts and longer delays

#### Events Not Found
- Check the configuration selectors for the specific site
- Run in debug mode: `--debug --no-headless`
- Sites frequently change their HTML structure

### Debug Mode

```bash
# Run with full debugging
node scraper.js --config=eventbrite --debug --no-headless --test

# Check saved HTML (in debug mode)
ls -la output/debug_*.html
```

## 📈 Performance Tips

1. **Start Small**: Use `--pages=2` for initial testing
2. **Use Delays**: Don't overwhelm target sites
3. **Monitor Logs**: Watch for rate limiting or blocking
4. **Rotate Sessions**: Clear browser data between runs
5. **Respect robots.txt**: Check site policies

## 🔄 Integration with YFEvents

The scraper is designed to work seamlessly with your YFEvents refactored system:

1. **Events**: Saved to existing `events` table
2. **Admin Review**: Events appear in admin interface for approval
3. **Deduplication**: Prevents duplicate entries
4. **Metadata**: Includes source information and URLs

### Viewing Scraped Events

Visit your admin interface:
- `https://backoffice.yakimafinds.com/refactor/admin/events.php`
- Look for events with 'pending' status
- External ID starts with `browser_eventbrite_` or `browser_meetup_`

## 📝 Configuration Schema

### Field Transforms

| Transform | Purpose | Example |
|-----------|---------|---------|
| `clean` | Remove extra whitespace | `"transform": "clean"` |
| `date` | Parse date string | `"transform": "date"` |
| `url` | Convert relative to absolute URL | `"transform": "url"` |

### Selector Types

| Type | Description | Example |
|------|-------------|---------|
| `selector` | CSS selector for element | `"h3"` |
| `attribute` | Get attribute value | `"href"` |
| `fallbackSelector` | Backup selector | For when primary fails |

## 🚀 Advanced Usage

### Environment Variables

Create `.env` file:
```bash
DEFAULT_LOCATION="Yakima, WA"
HEADLESS=true
DEBUG=false
MAX_PAGES=10
OUTPUT_DIR=./output
```

### Programmatic Usage

```javascript
const { BrowserEventScraper } = require('./scraper.js');

const scraper = new BrowserEventScraper({
    config: 'eventbrite',
    location: 'Seattle, WA',
    maxPages: 5,
    debug: true
});

scraper.run().then(() => {
    console.log('Scraping complete!');
}).catch(err => {
    console.error('Scraping failed:', err);
});
```

## 📞 Support

For issues specific to this browser automation system:

1. Check the logs in `./output/scraper.log`
2. Run in debug mode with visible browser
3. Verify database bridge is accessible
4. Check YFEvents admin interface for scraped events

## 🎯 Roadmap

- [ ] Add more event sources (Facebook Events, local sites)
- [ ] Implement proxy support for IP rotation
- [ ] Add CAPTCHA solving integration
- [ ] Create web-based configuration editor
- [ ] Add event deduplication across sources
- [ ] Implement scheduling/cron integration