# Event Time Parsing Solution

## Problem Analysis

The event scraper was setting all events to start at 12:00 AM (00:00:00) because:

1. **Visit Yakima source limitation**: The event listing pages don't include specific time information
2. **Hardcoded default times**: The `parseDateString()` function was using `00:00:00` and `23:59:59` as fallbacks
3. **No intelligent time estimation**: Events were treated as all-day regardless of type

## Solution Implemented

### 1. Intelligent Time Defaults

Added smart defaults based on event titles and types:

| Event Type | Default Start Time | Logic |
|------------|-------------------|-------|
| **Morning Events** | | |
| Farmers Market | 8:00 AM | Markets typically start early |
| Breakfast | 8:00 AM | Standard breakfast time |
| Brunch | 10:00 AM | Late morning start |
| **Afternoon Events** | | |
| Wine/Tasting | 12:00 PM | Midday wine events |
| Lunch | 12:00 PM | Standard lunch time |
| Tours | 10:00 AM | Good touring time |
| **Evening Events** | | |
| Happy Hour | 5:00 PM | Standard happy hour |
| Trivia | 7:00 PM | Popular trivia time |
| Live Music | 7:00 PM | Evening entertainment |
| Comedy Shows | 8:00 PM | Later evening shows |
| Bingo | 7:00 PM | Evening activity |

### 2. Enhanced Parsing Logic

The updated `YakimaValleyEventScraper` now:

1. **Searches for explicit times** in event content using regex patterns:
   - `7:30 PM`, `7 PM` format detection
   - Time ranges like "from 5:00 to 8:00 PM"
   - "Starts at", "Doors open" patterns

2. **Applies intelligent defaults** when no explicit time is found
3. **Calculates appropriate end times** based on event type and duration

### 3. Duration Estimation

End times are calculated based on start times and event types:
- **Morning events**: 2-3 hour duration
- **Afternoon events**: 3-4 hour duration  
- **Evening events**: 2-3 hour duration
- **Markets/Fairs**: 5+ hour duration

## Code Changes

### Modified Files

1. **`src/Scrapers/YakimaValleyEventScraper.php`**:
   - Enhanced `parseDateString()` with time extraction
   - Added `getDefaultTimeForEvent()` function
   - Added `getDefaultEndTime()` function

2. **Created `scripts/fix_event_times.php`**:
   - Updates existing events with default midnight times
   - Applies same intelligent defaults retroactively

## Results

### Before Implementation
```
title: Trivia at Single Hill Brewing Co
start_datetime: 2025-06-11 00:00:00
end_datetime: 2025-06-11 23:59:59
```

### After Implementation
```
title: Trivia at Single Hill Brewing Co  
start_datetime: 2025-06-11 19:00:00
end_datetime: 2025-06-11 22:00:00
```

## Usage

### For New Events
The scraper now automatically applies intelligent times when scraping Visit Yakima events.

### For Existing Events
Run the fix script to update existing events:
```bash
php scripts/fix_event_times.php
```

### Testing
Test the scraper with enhanced logging:
```bash
php cron/scrape-events.php --source-id=1
```

## Future Enhancements

1. **Manual Time Override**: Add admin interface to manually set times for specific events
2. **Learning System**: Track manual corrections to improve automatic detection
3. **External Time Sources**: Integrate with venue websites or social media for time details
4. **User Feedback**: Allow users to report incorrect times

## Configuration

The time defaults can be modified in the `getDefaultTimeForEvent()` function. New event types can be added by updating the `$patterns` array.

## Monitoring

Monitor scraper logs for:
- "Using default time" messages indicate when intelligent defaults are applied
- "Found time pattern" messages show when explicit times are detected
- Check database for events still showing 00:00:00 times

This solution provides much more accurate event times while gracefully handling the limitation of the source data.