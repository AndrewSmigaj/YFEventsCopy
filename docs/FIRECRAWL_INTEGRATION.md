# Firecrawl Integration Documentation

## Overview

This document provides comprehensive documentation for the Firecrawl integration in YFEvents, including installation, configuration, usage, and removal instructions.

**Integration Date**: 2025-06-04  
**Firecrawl Version**: v1 API  
**Integration Type**: Hybrid (with fallback)

## What is Firecrawl?

Firecrawl is a web scraping and data extraction API that converts websites into "LLM-ready data." It handles modern websites with JavaScript, provides structured data extraction, and offers built-in rate limiting and proxy management.

### Key Benefits for YFEvents:
- **Enhanced Reliability**: Better handling of JavaScript-heavy event websites
- **Structured Extraction**: Automatic event data parsing with schema validation
- **Reduced Maintenance**: Less custom scraper code to maintain
- **Scalability**: Built-in rate limiting and batch processing

## Architecture

### Files Added/Modified

**New Files:**
- `src/Utils/FirecrawlService.php` - Firecrawl API wrapper
- `src/Scrapers/FirecrawlEnhancedScraper.php` - Hybrid scraper implementation
- `scripts/test_firecrawl_integration.php` - Integration testing script
- `docs/FIRECRAWL_INTEGRATION.md` - This documentation

**Modified Files:**
- `src/Scrapers/ScraperFactory.php` - Added 'firecrawl_enhanced' type
- `src/Scrapers/EventScraper.php` - Added scrapeFirecrawlEnhancedSource method
- `config/api_keys.php` - Added FIRECRAWL_API_KEY constant

### Integration Pattern

The integration follows a **hybrid approach**:

1. **Primary**: Try Firecrawl API first for enhanced scraping
2. **Fallback**: Use existing YFEvents scrapers if Firecrawl fails/unavailable
3. **Transparent**: Existing calendar sources continue to work unchanged

## Installation & Configuration

### Step 1: API Key Configuration

The Firecrawl API key is already configured in `config/api_keys.php`:

```php
define('FIRECRAWL_API_KEY', 'YOUR_FIRECRAWL_API_KEY_HERE');
```

### Step 2: Composer Dependencies

No additional composer dependencies are required. The integration uses PHP's built-in HTTP functions.

### Step 3: Test Installation

Run the test script to verify the integration:

```bash
cd /home/robug/YFEvents
php scripts/test_firecrawl_integration.php
```

## Usage

### Creating a Firecrawl-Enhanced Calendar Source

1. **Via Admin Interface**:
   - Navigate to Admin → Calendar → Sources
   - Click "Add New Source"
   - Select scrape type: "Firecrawl Enhanced (with fallback)"
   - Configure as needed

2. **Via Database**:
   ```sql
   INSERT INTO calendar_sources (
       name, url, scrape_type, scrape_config, active
   ) VALUES (
       'Example Events (Firecrawl)',
       'https://example.com/events',
       'firecrawl_enhanced',
       '{"firecrawl_method": "structured", "fallback_type": "html"}',
       1
   );
   ```

### Configuration Options

The `scrape_config` JSON supports these options:

```json
{
    "firecrawl_method": "structured",     // structured, search, basic
    "fallback_type": "html",              // html, yakima_valley, ical, json
    "search_query": "events yakima",      // For search method
    "selectors": {                        // For HTML fallback
        "event_container": ".event-item",
        "title": ".event-title",
        "datetime": ".event-date",
        "location": ".event-location"
    }
}
```

### Firecrawl Methods

1. **Structured** (Recommended):
   - Uses schema-based extraction
   - Most accurate for event data
   - Best for sites with consistent structure

2. **Search**:
   - Searches web for events related to query
   - Good for discovering events across multiple sources
   - Higher API usage

3. **Basic**:
   - Simple markdown conversion
   - Fastest option
   - Requires manual parsing

### Fallback Types

1. **html**: Use CSS selectors for HTML parsing
2. **yakima_valley**: Use specialized Yakima Valley parser
3. **ical**: Parse iCal/ICS calendar feeds
4. **json**: Parse JSON API responses

## API Usage & Costs

### Current Plan
- **API Key**: YOUR_FIRECRAWL_API_KEY_HERE
- **Plan**: Standard (100,000 credits/month)
- **Cost**: ~$83/month

### Credit Usage
- **Scrape**: ~30-50 credits per URL
- **Extract**: ~50-70 credits per URL
- **Search**: ~100+ credits per query
- **Batch**: More efficient for multiple URLs

### Monitoring Usage

Check API usage programmatically:

```php
$firecrawlService = new FirecrawlService();
$usage = $firecrawlService->getUsage();
echo json_encode($usage, JSON_PRETTY_PRINT);
```

## Testing & Troubleshooting

### Running Tests

```bash
# Test basic integration
php scripts/test_firecrawl_integration.php

# Test specific URL
php scripts/test_firecrawl_integration.php https://example.com/events

# Test existing scraper
php test_scraper.php
```

### Common Issues

1. **API Key Not Working**:
   - Verify key in `config/api_keys.php`
   - Check API usage limits
   - Ensure key format: `fc-...`

2. **No Events Found**:
   - Check if source URL is accessible
   - Verify scrape configuration
   - Test fallback method separately

3. **Slow Performance**:
   - Consider using 'basic' method instead of 'structured'
   - Check API rate limits
   - Verify network connectivity

4. **Fallback Not Working**:
   - Check fallback_type configuration
   - Verify selectors for HTML fallback
   - Test original scraper independently

### Debug Mode

Enable debug logging by modifying `FirecrawlService.php`:

```php
// Add to makeRequest method
error_log("Firecrawl Request: " . json_encode($payload));
error_log("Firecrawl Response: " . $response);
```

## Performance Considerations

### Optimization Tips

1. **Use Batch Scraping**: For multiple URLs from same domain
2. **Cache Results**: Store successful responses locally
3. **Smart Fallback**: Configure appropriate fallback methods
4. **Monitor Credits**: Track API usage to avoid overages

### Recommended Configurations

**For News/Blog Sites**:
```json
{
    "firecrawl_method": "basic",
    "fallback_type": "html",
    "selectors": {
        "event_container": "article",
        "title": "h1, h2",
        "datetime": ".date, time",
        "location": ".location"
    }
}
```

**For Calendar Sites**:
```json
{
    "firecrawl_method": "structured",
    "fallback_type": "ical"
}
```

**For Event Directories**:
```json
{
    "firecrawl_method": "search",
    "search_query": "events [location]",
    "fallback_type": "html"
}
```

## Security Considerations

### API Key Protection
- ✅ API key stored in `config/api_keys.php` (gitignored)
- ✅ No API key in frontend/client-side code
- ✅ Environment variable support available

### Data Privacy
- Firecrawl processes publicly available web content only
- No personal data is sent to Firecrawl API
- Event data is cached locally as normal

### Rate Limiting
- Built-in retry mechanism with exponential backoff
- Respects Firecrawl rate limits
- Graceful degradation when limits exceeded

## Removing the Integration

If you need to remove the Firecrawl integration:

### Step 1: Update Calendar Sources
```sql
-- Change all firecrawl_enhanced sources to a different type
UPDATE calendar_sources 
SET scrape_type = 'html' 
WHERE scrape_type = 'firecrawl_enhanced';

-- Or disable them
UPDATE calendar_sources 
SET active = 0 
WHERE scrape_type = 'firecrawl_enhanced';
```

### Step 2: Remove Files
```bash
rm src/Utils/FirecrawlService.php
rm src/Scrapers/FirecrawlEnhancedScraper.php
rm scripts/test_firecrawl_integration.php
rm docs/FIRECRAWL_INTEGRATION.md
```

### Step 3: Revert Code Changes

**ScraperFactory.php**:
- Remove `'firecrawl_enhanced' => 'Firecrawl Enhanced (with fallback)'` from `getAvailableTypes()`
- Remove the `firecrawl_enhanced` case from `create()` method
- Remove the `firecrawl_enhanced` case from `getConfigurationTemplate()`

**EventScraper.php**:
- Remove the `firecrawl_enhanced` case from `scrapeSource()` method
- Remove the `scrapeFirecrawlEnhancedSource()` method

**config/api_keys.php**:
- Remove the `FIRECRAWL_API_KEY` line

### Step 4: Run Composer Autoload
```bash
composer dump-autoload
```

### Step 5: Test
```bash
php test_scraper.php
```

## Migration Guide

### From Existing Scrapers

To migrate an existing calendar source to use Firecrawl:

1. **Note Current Configuration**:
   ```sql
   SELECT name, url, scrape_type, scrape_config 
   FROM calendar_sources 
   WHERE id = [source_id];
   ```

2. **Update to Firecrawl Enhanced**:
   ```sql
   UPDATE calendar_sources SET 
       scrape_type = 'firecrawl_enhanced',
       scrape_config = '{"firecrawl_method": "structured", "fallback_type": "html", "selectors": {...}}'
   WHERE id = [source_id];
   ```

3. **Test the Migration**:
   ```bash
   php cron/scrape-events.php --source-id=[source_id]
   ```

4. **Monitor Results**:
   - Check scraping logs
   - Verify event quality
   - Monitor API usage

### Rollback Plan

If issues arise:

1. **Immediate Rollback**:
   ```sql
   UPDATE calendar_sources SET 
       scrape_type = '[original_type]',
       scrape_config = '[original_config]'
   WHERE scrape_type = 'firecrawl_enhanced';
   ```

2. **Verify Functionality**:
   ```bash
   php test_scraper.php
   ```

## Support & Maintenance

### Monitoring

- **API Usage**: Check monthly credit consumption
- **Error Rates**: Monitor scraping success rates
- **Performance**: Track scraping duration
- **Fallback Usage**: Monitor when fallbacks are triggered

### Updates

- **Firecrawl API**: Monitor for API changes/updates
- **Cost Changes**: Track pricing changes
- **Feature Updates**: New Firecrawl features

### Contact

- **YFEvents**: Internal development team
- **Firecrawl Support**: https://www.firecrawl.dev/support
- **API Documentation**: https://docs.firecrawl.dev/

---

**Last Updated**: 2025-06-04  
**Version**: 1.0.0  
**Integration Status**: ✅ Production Ready