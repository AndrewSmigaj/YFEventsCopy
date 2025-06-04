# Event Time Scraping Issue

## Problem
All scraped events from Visit Yakima are showing as starting at midnight (00:00:00) instead of their actual start times.

## Root Cause
The Visit Yakima events page (https://www.visityakima.com/yakima-valley-events) only displays event dates without specific times in their listing. The scraper correctly identifies this limitation and defaults to:
- Start time: 00:00:00 (beginning of day)
- End time: 23:59:59 (end of day)

## Investigation Results
1. **No time patterns found**: The HTML contains no time information (e.g., "7:00 PM", "19:00")
2. **Date-only format**: Events show only dates like "June 2-4" or "June 5"
3. **Design limitation**: This appears to be how Visit Yakima structures their event listing

## Solutions

### 1. Enhanced Scraping (Recommended)
Modify the scraper to fetch individual event pages for time details:
- The listing page links to detailed event pages
- These detail pages likely contain specific times
- Would require additional HTTP requests per event

### 2. Manual Time Entry
- Keep current behavior for initial scraping
- Use admin panel to manually update times for important events
- Good for high-priority events only

### 3. Alternative Sources
Add sources that include time information:
- Facebook Events API (includes times)
- Eventbrite API (includes times)
- Local venue websites with better structured data
- Google Calendar feeds (iCal format includes times)

### 4. Smart Defaults
Implement intelligent time defaults based on event type:
- Concerts/Shows: 7:00 PM
- Museums/Galleries: 10:00 AM
- Restaurants/Wine Tasting: 12:00 PM
- Classes/Workshops: 6:00 PM

## Current Impact
- All 24 events from Visit Yakima show as all-day events
- User experience: Events appear less specific than they should be
- Map/calendar displays: All events cluster at midnight

## Recommendation
1. **Short term**: Accept the limitation for Visit Yakima source
2. **Medium term**: Add more sources with better time data (like the US Holidays iCal feed)
3. **Long term**: Implement enhanced scraping to fetch detail pages

## Code Location
The default time assignment happens in:
- `src/Scrapers/YakimaValleyEventScraper.php` lines 159-161
- This is working as designed given the available data