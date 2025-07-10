# Source Links Implementation

## Overview

Successfully implemented clickable source links for scraped events, providing users direct access to original event pages for additional details that may not be captured in the scraping process.

## Features Implemented

### 1. **External URL Capture**
- All scraped events now include `external_url` field pointing to original event page
- Visit Yakima events link back to `https://www.visityakima.com/events/[event-id]/`
- URLs are validated and stored for all event sources

### 2. **API Integration**
- Updated `events-simple.php` API to include `external_url` and `source_name`
- Main events API (`calendar-events.php`) already supported external URLs via EventModel
- Both APIs now return complete source attribution data

### 3. **Calendar Interface Enhancement**
- Event details modal displays source attribution with clickable link
- Links open in new tabs (`target="_blank"`) for better user experience
- Clean separation between event description and source attribution
- Proper icon usage (`fas fa-external-link-alt`) for external links

### 4. **Clean Data Structure**
- Event descriptions focus on venue, location, and category information
- Source attribution handled separately by UI components
- No redundant source information in description fields

## User Experience

When users view event details, they see:

```
Event Title
📅 Date and Time
📍 Location
📝 Clean Description (venue, location, categories)
ℹ️ Source: Visit Yakima Tourism [External Link Icon]
```

Clicking the source link takes them to the original event page where they can find:
- Additional event details
- Registration/ticket information  
- Contact information for event organizers
- Photos and media
- Updated information if changes occur

## Technical Implementation

### Database Schema
```sql
-- External URL field already exists
ALTER TABLE events ADD external_url VARCHAR(500);

-- Source relationship via source_id
SELECT e.*, cs.name as source_name, cs.url as source_url
FROM events e
LEFT JOIN calendar_sources cs ON e.source_id = cs.id
```

### Scraper Updates
```php
// YakimaValleyEventScraper.php
$event['external_url'] = $eventUrl;  // Capture individual event URL
$event['description'] = implode("\n", $cleanDescriptionLines);  // Clean format
```

### API Response Format
```json
{
  "title": "Event Title",
  "external_url": "https://www.visityakima.com/events/12345/",
  "source_name": "Yakima Valley Tourism Events",
  "description": "Venue: Example Venue\nLocation: Yakima\nCategories: Music"
}
```

### Frontend Display
```javascript
// calendar.js - Event modal includes source attribution
${eventData.source_name ? `
    <div class="event-source-attribution">
        <i class="fas fa-info-circle"></i> 
        <strong>Source:</strong> 
        <a href="${eventData.external_url}" target="_blank" rel="noopener">
            ${eventData.source_name} <i class="fas fa-external-link-alt"></i>
        </a>
    </div>
` : ''}
```

## Benefits

### For Users
1. **Complete Information Access**: Can view full event details on source websites
2. **Registration/Tickets**: Direct access to ticket purchasing or registration
3. **Current Information**: Source pages may have updates not reflected in scraped data
4. **Contact Details**: Can contact event organizers directly
5. **Trust & Transparency**: Clear attribution to original sources

### For Event Organizers
1. **Traffic Generation**: Direct links drive traffic to their websites
2. **Lead Generation**: Users can sign up for newsletters or mailing lists
3. **Brand Recognition**: Proper attribution maintains source credibility
4. **Updated Information**: Users see current details if organizers make changes

### For Site Administrators
1. **Reduced Support**: Users can find additional details themselves
2. **Legal Compliance**: Proper attribution reduces copyright concerns
3. **Relationship Building**: Shows respect for source websites
4. **Data Quality**: Users can verify information against sources

## Future Enhancements

### 1. **Link Validation**
- Periodic checking of external URLs for broken links
- Automatic notification when source pages become unavailable
- Fallback to source homepage if individual event page is gone

### 2. **Analytics Integration**
- Track click-through rates to source websites
- Identify most valuable event sources
- Monitor user engagement with external links

### 3. **Enhanced Attribution**
- Display source logos where available
- Add "last updated" timestamps for scraped data
- Show data freshness indicators

### 4. **Multi-Source Events**
- Support events that appear on multiple sources
- Display multiple source links when available
- Allow users to choose preferred source

## Maintenance

### Regular Tasks
1. **Monitor Link Health**: Check for broken external URLs
2. **Update Source Information**: Keep calendar_sources table current
3. **Clean Descriptions**: Remove any redundant source info from event descriptions

### Scripts Available
- `scripts/test_external_links.php` - Verify implementation
- `scripts/clean_event_descriptions.php` - Clean redundant source info
- `scripts/fix_event_times.php` - Update event times (related enhancement)

## Testing

```bash
# Test API includes external URLs
curl "http://137.184.245.149/api/events-simple.php?start=2025-06-01&end=2025-06-30" | jq '.events[0].external_url'

# Verify database has external URLs
mysql -e "SELECT title, external_url FROM events WHERE source_id = 1 LIMIT 5;"

# Test calendar interface
# Visit: http://137.184.245.149/calendar.php
# Click on any Visit Yakima event to see source link
```

## Configuration

Source attribution can be customized per source in the `calendar_sources` table:
- `name`: Display name for source attribution
- `url`: Homepage URL (fallback if external_url is missing)
- `active`: Enable/disable source

The implementation automatically handles source attribution for any active scraping source, making it easy to expand to additional event sources in the future.