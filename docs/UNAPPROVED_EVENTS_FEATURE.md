# Unapproved Events Display Feature

## Overview

Added functionality to display unapproved events on the public calendar with clear disclaimers, allowing users to see newly scraped events immediately while maintaining quality control.

## Implementation Details

### 1. Database Settings System

**New Table**: `system_settings`
- Stores system-wide configuration options
- Key-value structure with descriptions
- Supports caching for performance

**Settings Added**:
- `show_unapproved_events`: Toggle to enable/disable feature
- `unapproved_events_disclaimer`: Customizable disclaimer text

### 2. SystemSettings Utility Class

**File**: `src/Utils/SystemSettings.php`

**Key Methods**:
- `get($key, $default)`: Retrieve setting with caching
- `set($key, $value, $description)`: Update setting
- `showUnapprovedEvents()`: Helper for checking if feature is enabled
- `getUnapprovedEventsDisclaimer()`: Get disclaimer text

### 3. Enhanced EventModel

**Updated**: `src/Models/EventModel.php`

**Changes**:
- Added `include_unapproved` filter option
- When enabled, returns both 'approved' and 'pending' events
- Fixed table name references (event_category_mapping)
- Improved GROUP BY query for MySQL compatibility

### 4. API Endpoint Updates

**Updated**: `www/html/ajax/calendar-events.php`

**Features**:
- Checks system setting to include unapproved events
- Adds disclaimer and indicators to pending events
- Event objects include:
  - `is_unapproved`: Boolean flag
  - `disclaimer`: Customized warning text

### 5. Frontend Styling

**Updated**: `www/html/css/calendar.css`

**Styling Features**:
- `.event-unapproved`: Orange border, light background
- `.unapproved-badge`: "Unverified" badge on event titles
- `.event-disclaimer`: Warning box with disclaimer text
- Diagonal stripe pattern overlay for visual distinction

**Updated**: `www/html/js/calendar.js`

**Features**:
- Enhanced `createEventListItem()` to handle unapproved events
- Displays disclaimer text below event meta information
- Adds visual indicators and badges

### 6. Admin Control Panel

**New File**: `www/html/admin/settings.php`

**Features**:
- Toggle to enable/disable unapproved events display
- Editable disclaimer text with live preview
- Visual preview of how unapproved events appear
- Form validation and success feedback

## Usage

### For Administrators

1. **Access Settings**: Visit `/admin/settings.php`
2. **Enable Feature**: Check "Show unapproved events on public calendar"
3. **Customize Disclaimer**: Edit the warning text as needed
4. **Save Settings**: Changes take effect immediately

### For Users

When enabled, unapproved events appear on the public calendar with:
- **Visual Distinction**: Orange border and subtle pattern overlay
- **"Unverified" Badge**: Clear labeling on event titles
- **Disclaimer Text**: Warning about unverified information
- **Same Functionality**: Click to view details, map integration, etc.

## Technical Details

### Event Status Flow

1. **Scraped Events**: Initially created with `status = 'pending'`
2. **Admin Review**: Events can be approved, rejected, or edited
3. **Public Display**: 
   - Default: Only `status = 'approved'` events shown
   - With feature: Both `approved` and `pending` events shown

### API Response Format

```json
{
  "success": true,
  "events": [
    {
      "id": 123,
      "title": "Sample Event",
      "status": "pending",
      "is_unapproved": true,
      "disclaimer": "These events are automatically imported...",
      // ... other event fields
    }
  ]
}
```

### Database Schema

```sql
CREATE TABLE system_settings (
    id int AUTO_INCREMENT PRIMARY KEY,
    setting_key varchar(100) NOT NULL UNIQUE,
    setting_value text,
    description text,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Configuration

### Default Settings

- **Feature Enabled**: Yes (can be disabled via admin panel)
- **Default Disclaimer**: "These events are automatically imported and have not been verified. Details may be incomplete or inaccurate."

### Customization Options

1. **Disclaimer Text**: Fully customizable through admin panel
2. **Visual Styling**: CSS can be modified in `calendar.css`
3. **Badge Text**: Currently "Unverified", can be changed in JavaScript

## Benefits

1. **Immediate Visibility**: Users see new events right after scraping
2. **Quality Control**: Clear warnings about unverified content
3. **Admin Flexibility**: Can enable/disable feature as needed
4. **User Trust**: Transparent about data verification status
5. **Better UX**: More events visible while maintaining accuracy expectations

## Testing Results

- **Current Status**: 24 pending events now visible with disclaimers
- **API Response**: Successfully returns 34 total events (12 approved + 24 pending)
- **Visual Display**: Proper styling and warnings applied
- **Admin Panel**: Settings page functional with live preview

## Files Modified/Created

### New Files
- `src/Utils/SystemSettings.php`
- `www/html/admin/settings.php`
- `test_unapproved_api.php` (testing)

### Modified Files
- `src/Models/EventModel.php`
- `www/html/ajax/calendar-events.php`
- `www/html/css/calendar.css`
- `www/html/js/calendar.js`

### Database Changes
- Added `system_settings` table
- Added `event_images` table (for compatibility)
- Fixed table references in EventModel

## Future Enhancements

1. **Batch Approval**: Allow approving multiple events at once
2. **Auto-Approval**: Rules for automatically approving certain sources
3. **User Feedback**: Allow users to report issues with unverified events
4. **Source Reliability**: Track accuracy of different sources over time