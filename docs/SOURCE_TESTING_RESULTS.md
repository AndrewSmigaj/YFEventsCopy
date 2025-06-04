# Calendar Source Testing Results

## Test Summary (2025-06-04)

Comprehensive testing was performed on all 11 calendar sources added to the system. Here are the detailed results:

## Working Sources ✅

### 1. Yakima Valley Tourism Events
- **Status**: ✅ Active and working
- **Type**: yakima_valley (custom scraper)
- **URL**: https://www.visityakima.com/yakima-valley-events
- **Results**: 24 events successfully scraped
- **Issue**: Events show as all-day (midnight start) due to website not providing specific times

### 2. US Holidays Calendar
- **Status**: ✅ Active and working
- **Type**: ical
- **URL**: Google Calendar US Holidays feed
- **Results**: 318 holidays found (multi-year calendar)
- **Note**: Events processed but marked as duplicates on subsequent runs

## Sources Needing Configuration 🔧

### 3. Capitol Theatre Yakima
- **Status**: 🔧 Enabled but needs selector updates
- **Type**: html
- **URL**: https://capitoltheatre.org/events/
- **Found**: Proper HTML structure with gallery-item containers
- **Recommended selectors**:
  - Container: `.gallery-item`
  - Title: `h6`
  - Date: `p` (contains date ranges)
  - URL: `a[href*="detail"]`

### 4. City of Yakima Events
- **Status**: 🔧 Enabled but needs selector refinement
- **Type**: html
- **URL**: https://www.yakimawa.gov/calendar/
- **Found**: Uses The Events Calendar WordPress plugin
- **Recommended selectors**:
  - Container: `article.tribe-events-calendar-month__calendar-event`
  - Title: `h3.tribe-events-calendar-month__calendar-event-title a`
  - Time: `.tribe-events-calendar-month__calendar-event-datetime time`

## Problematic Sources ⚠️

### 5. Yakima Herald Calendar
- **Status**: ⚠️ Accessible but complex structure
- **URL**: https://www.yakimaherald.com/calendar/
- **Issue**: 249,764 bytes of content but no clear event selectors found
- **Note**: May require JavaScript or have events embedded in complex layout

### 6. Downtown Yakima Events
- **Status**: ⚠️ URL redirects, content unclear
- **URL**: https://downtownyakima.com/events/
- **Issue**: Redirects and no clear event listings found
- **Note**: May be primarily navigation page

## Broken/Unavailable Sources ❌

### 7. Yakima Convention Center
- **Status**: ❌ Domain for sale
- **Original URL**: https://www.yakimaconventioncenter.com/events
- **Issue**: Domain redirects to HugeDomains sales page

### 8. Yakima Valley Wine Country
- **Status**: ❌ Site unreachable
- **Original URL**: https://www.wineyakimavalley.org/events
- **Issue**: Connection timeouts and 403 errors

### 9. Yakima Valley Sports
- **Status**: ❌ Site doesn't exist
- **Original URL**: https://www.yakimavalleysports.com/calendar
- **Issue**: No response from server

### 10. Yakima Valley Museum
- **Status**: ❌ Redirects to different domain
- **URL**: https://yvmuseum.org/
- **Issue**: 403 Forbidden or site restructured

### 11. Yakima Valley College
- **Status**: ❌ Calendar page not found
- **URL**: https://www.yvcc.edu/calendar.php
- **Issue**: 404 error on calendar page

## Key Findings

### HTML Scraping Challenges
1. **JavaScript Loading**: Many modern sites load events via JavaScript, making them unscrapable with simple HTML parsing
2. **WordPress Events Calendar**: Sites using The Events Calendar plugin have consistent but complex selectors
3. **Dynamic Content**: Some events may be loaded from external APIs or require user interaction

### Successful Patterns
1. **iCal Feeds**: Reliably work and include proper time information
2. **Static HTML**: Simple gallery-style layouts work well (like Capitol Theatre)
3. **WordPress Plugin**: The Events Calendar has predictable structure

### Time Information Issue
- Most local sources don't provide specific event times in listings
- Only iCal feeds and some WordPress calendar plugins include accurate time data
- This results in many events showing as "all day" events

## Recommendations

### Immediate Actions
1. **Focus on iCal sources**: Find more venues offering calendar feeds
2. **Manual configuration**: Update selectors for Capitol Theatre and City of Yakima
3. **Alternative approaches**: Consider Facebook Events API integration

### Medium Term
1. **Enhanced scraping**: Implement detail page fetching for venues with landing pages
2. **API integration**: Explore Eventbrite, Facebook, or Google Events APIs
3. **Community input**: Allow manual event submission through admin panel

### Long Term
1. **JavaScript rendering**: Consider using headless browser for dynamic content
2. **RSS/Feed discovery**: Auto-detect calendar feeds on venue websites
3. **Mobile app integration**: Many venues use mobile apps for event management

## Current Active Sources Summary

| Source | Type | Events | Status |
|--------|------|--------|--------|
| Yakima Valley Tourism | yakima_valley | 24 | ✅ Working |
| US Holidays Calendar | ical | 318 | ✅ Working |
| Capitol Theatre | html | 0 | 🔧 Needs config |
| City of Yakima | html | 0 | 🔧 Needs config |

## Files Created During Testing

- `scripts/add_local_event_sources.php` - Adds sources to database
- `scripts/test_all_sources.php` - Comprehensive source testing
- `scripts/find_correct_urls.php` - URL discovery and verification
- `scripts/update_source_urls.php` - URL corrections
- `scripts/analyze_html_structure.php` - HTML structure analysis
- `scripts/update_working_sources.php` - Configuration updates
- `scripts/show_source_status.php` - Status dashboard

## Next Steps

1. Manually verify and update CSS selectors for promising sources
2. Research additional local venues with proper calendar feeds
3. Consider implementing Facebook Events integration
4. Set up monitoring for source availability changes