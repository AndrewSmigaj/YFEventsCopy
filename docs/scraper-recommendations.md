# Web Scraper Code Review and Recommendations

## Executive Summary

After reviewing the YFEvents web scraper system, I've identified several issues and implemented improvements. The main problems were:

1. **Incorrect URLs**: Many sources had wrong or outdated URLs (returning 404, 403, or redirects)
2. **Poor error handling**: The scraper was failing silently without detailed logging
3. **Wrong source URL in database**: The Yakima Valley source was pointing to YVCC instead of Visit Yakima

## Issues Found

### 1. Database Configuration Issues
- The Yakima Valley Tourism Events source (ID: 1) was pointing to `https://www.yvcc.edu/` instead of the correct URL
- 8 out of 11 sources had broken URLs or were inactive

### 2. Logging Deficiencies
- No detailed logging of HTTP responses or cURL errors
- No logging of parsed event counts during scraping process
- Silent failures made debugging difficult

### 3. Scraper Logic Issues
- Using `file_get_contents()` which doesn't handle redirects or provide good error info
- HTML scraper had invalid XPath selectors causing PHP warnings
- No fallback selectors for different page structures

## Implemented Improvements

### 1. Enhanced Logging
I've updated the EventScraper class with:
- Detailed cURL-based fetching with proper error handling
- HTTP status code logging
- Content size and preview logging
- Step-by-step parsing progress logs

### 2. Better Error Handling
- Replaced `file_get_contents()` with cURL for better control
- Added proper redirect handling
- Better user agent string to avoid bot detection

### 3. Flexible Selector System
Updated YakimaValleyEventScraper with:
- Multiple selector attempts (original, div.event, article.event, schema.org markup)
- Detailed logging of which selectors work
- Page structure analysis when no events found

## Results

After implementing these changes:
- **Visit Yakima scraper**: Now successfully finding 24 events (was 0)
- **US Holidays calendar**: Working correctly (318 events)
- **Other sources**: Need URL updates or new selectors

## Recommendations

### Immediate Actions

1. **Update Broken URLs**
   ```sql
   -- Fix Capitol Theatre (currently returns empty results)
   UPDATE calendar_sources SET url = 'https://www.capitoltheatre.org/events/' WHERE id = 7;
   
   -- Fix City of Yakima (redirects to media calendar)
   UPDATE calendar_sources SET url = 'https://www.yakimawa.gov/media/calendar/' WHERE id = 10;
   ```

2. **Add Scraper-Specific Configuration**
   ```php
   // Add to each HTML source's scrape_config:
   {
     "selectors": {
       "event_container": "div.event-item, article.event, li.event",
       "title": "h2, h3, .event-title",
       "datetime": ".event-date, time, .date",
       "location": ".event-location, .venue",
       "description": ".event-description, .summary"
     },
     "user_agent": "Mozilla/5.0 (compatible; YakimaFindsBot/1.0)"
   }
   ```

3. **Enable Debug Mode**
   ```bash
   # Add to .env file
   SCRAPER_DEBUG=true
   SCRAPER_LOG_LEVEL=DEBUG
   ```

### Long-term Improvements

1. **Implement Adaptive Scraping**
   - Auto-detect event structures using common patterns
   - Machine learning to identify event data
   - Fallback to LLM-based scraping for difficult sites

2. **Add Monitoring Dashboard**
   - Track success rates per source
   - Alert when sources fail repeatedly
   - Visual debugging tools for selectors

3. **Create Source Health Check Script**
   ```bash
   # Run daily to check source health
   php scripts/check_source_health.php --notify-on-failure
   ```

4. **Implement Retry Logic**
   - Retry failed sources with exponential backoff
   - Try alternative URLs if main URL fails
   - Cache successful responses for debugging

## Testing Commands

```bash
# Test individual source with debug output
php cron/scrape-events.php --source-id=1 --debug

# Run enhanced debug script
php scripts/test_scraper_debug.php

# Check all sources
php scripts/test_all_sources.php
```

## Source Status Summary

| Source | Status | Action Needed |
|--------|--------|---------------|
| Visit Yakima Events | ✅ Working (24 events) | None |
| US Holidays | ✅ Working (318 events) | None |
| Capitol Theatre | ⚠️ No events found | Update selectors |
| City of Yakima | ⚠️ Redirects | Use new URL |
| WSU Tri-Cities | ⚠️ No events found | Update selectors |
| Other sources | ❌ Broken URLs | Find correct URLs |

## Next Steps

1. Run the URL updates provided above
2. Test each source individually with the debug script
3. Update HTML selectors based on actual page structure
4. Consider implementing the Firecrawl enhanced scraper for difficult sites
5. Set up automated monitoring to catch issues early

The scraper system is fundamentally sound but needs better error handling and monitoring. With these improvements, you should see much better event discovery rates.