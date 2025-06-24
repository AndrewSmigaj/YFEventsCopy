# Communication Module Documentation

## Overview

The YFEvents Communication Module provides a real-time messaging platform with specialized features for community interaction, including the innovative "Picks" feature for sharing estate and yard sale locations.

## Architecture

### Domain Structure
```
Domain/Communication/
├── Entities/
│   ├── Channel.php
│   ├── Message.php
│   ├── Participant.php
│   └── Notification.php
├── Services/
│   ├── ChannelService.php
│   ├── MessageService.php
│   └── NotificationService.php
└── Repositories/
    ├── ChannelRepositoryInterface.php
    ├── MessageRepositoryInterface.php
    └── ParticipantRepositoryInterface.php
```

### Database Schema

#### communication_channels
- `id`: Primary key
- `name`: Channel display name
- `slug`: URL-friendly identifier
- `description`: Channel description
- `type`: enum('public', 'private', 'event', 'vendor', 'announcement')
- `created_by_user_id`: Creator user ID
- `settings`: JSON configuration
- Timestamps and activity tracking

#### communication_messages
- `id`: Primary key
- `channel_id`: Foreign key to channels
- `user_id`: Message author
- `content`: Message text
- `metadata`: JSON field for extended data (locations, mentions, etc.)
- `content_type`: enum('text', 'system', 'announcement')
- Timestamps and soft delete support

## Features

### 1. Channel Types

#### Public Channels
- Open to all authenticated users
- Examples: General, Events, Shops, Picks

#### Private Channels
- Invitation-only access
- User-to-user messaging

#### Event Channels
- Tied to specific events
- Auto-created for event discussions

#### Vendor Channels
- Business-specific communication
- Customer support and updates

#### Announcement Channels
- Admin-only posting
- System-wide notifications

### 2. Picks Feature

The Picks feature is a specialized channel for sharing estate sales, yard sales, and local events with location data.

#### Components

**Backend Support**:
- Metadata storage in messages table
- Location fields: name, address, coordinates, event date/times
- API endpoints with location data handling

**Frontend Interface** (`/communication/picks.php`):
- Dual view: Forum style and Map view
- Google Maps integration
- Address autocomplete with geocoding
- Interactive markers with info windows
- Mobile-responsive design

**Channel Integration**:
- Special rendering in communication hub
- "View on Map" buttons for picks
- Location preview in message display

#### Usage Flow

1. **Creating a Pick**:
   ```javascript
   // Data structure for a pick
   {
     content: "Estate sale this weekend!",
     location_name: "Johnson Estate Sale",
     location_address: "123 Main St, Yakima, WA",
     location_latitude: 46.6021,
     location_longitude: -120.5059,
     event_date: "2024-01-20",
     event_start_time: "08:00",
     event_end_time: "16:00"
   }
   ```

2. **Viewing Picks**:
   - Forum View: List of all picks with details
   - Map View: Interactive map with all locations
   - Click markers for details
   - "Show on Map" buttons for navigation

### 3. Real-time Features

- Message updates without page refresh
- Typing indicators (planned)
- Online user status
- Unread message counts
- Push notifications (PWA)

### 4. Rich Content Support

- @mentions with notifications
- Basic markdown formatting
- File attachments
- Image preview
- Link previews (planned)

## API Endpoints

### Channel Management
```
GET    /api/communication/channels          - List user's channels
POST   /api/communication/channels          - Create new channel
GET    /api/communication/channels/{id}     - Get channel details
PUT    /api/communication/channels/{id}     - Update channel
DELETE /api/communication/channels/{id}     - Delete channel
POST   /api/communication/channels/{id}/join - Join channel
DELETE /api/communication/channels/{id}/leave - Leave channel
```

### Message Operations
```
GET    /api/communication/channels/{channelId}/messages - Get messages
POST   /api/communication/channels/{channelId}/messages - Send message
PUT    /api/communication/messages/{id}                 - Edit message
DELETE /api/communication/messages/{id}                 - Delete message
POST   /api/communication/messages/{id}/pin             - Pin message
DELETE /api/communication/messages/{id}/pin             - Unpin message
```

### Notifications
```
GET    /api/communication/notifications       - Get notifications
PUT    /api/communication/notifications/read  - Mark as read
GET    /api/communication/notifications/count - Get unread count
```

## Configuration

### Environment Variables
```env
# Session configuration
SESSION_SAVE_PATH=/path/to/sessions

# Google Maps (required for picks)
GOOGLE_MAPS_API_KEY=your_api_key
```

### Service Registration
```php
// In config/services/communication.php
$container->singleton(CommunicationService::class, function($c) {
    return new CommunicationService(
        $c->resolve(ChannelService::class),
        $c->resolve(MessageService::class),
        $c->resolve(NotificationService::class)
    );
});
```

## Security Considerations

### Authentication
- All endpoints require authentication
- Session-based authentication with custom storage
- CSRF protection on state-changing operations

### Authorization
- Channel-based permissions
- Message edit/delete limited to authors
- Admin override capabilities

### Data Validation
- Input sanitization for XSS prevention
- SQL injection protection via prepared statements
- File upload restrictions

## Mobile Support

### Progressive Web App
- Service worker for offline support
- Add to home screen capability
- Push notification support
- Responsive design

### Theme Detection
```php
$mobileDetector = new MobileDetector();
$isMobile = $mobileDetector->isMobile();
$theme = $mobileDetector->determineTheme();
```

### Mobile Optimizations
- Touch-friendly interface
- Swipe gestures
- Optimized keyboard handling
- Reduced data usage

## Troubleshooting

### Common Issues

1. **Messages not loading**
   - Check session authentication
   - Verify channel permissions
   - Review browser console errors

2. **Picks not showing on map**
   - Verify Google Maps API key
   - Check location metadata in database
   - Ensure coordinates are valid

3. **Session errors**
   - Check session directory permissions
   - Verify session.save_path configuration
   - Clear browser cookies

### Debug Mode

Enable debug logging:
```php
// In MessageApiController
error_log('Debug: ' . json_encode($data));
```

Check logs:
```bash
tail -f /path/to/logs/error.log
```

## Future Enhancements

### Planned Features
- Direct messaging between users
- Voice/video calls
- Screen sharing
- Message reactions
- Threading/replies
- Search functionality
- Message encryption
- Webhook integrations

### Performance Optimizations
- Message pagination
- Lazy loading
- WebSocket support
- Redis caching
- CDN integration

## Developer Guide

### Adding New Message Types

1. Update Message entity:
```php
class Message {
    // Add new type to MessageType enum
    const TYPE_POLL = 'poll';
}
```

2. Extend metadata handling:
```php
// In MessageService
if ($data['type'] === 'poll') {
    $metadata['poll_options'] = $data['options'];
}
```

3. Update frontend rendering:
```javascript
// In communication.js
if (message.type === 'poll') {
    return this.renderPollMessage(message);
}
```

### Creating Custom Channels

1. Define channel type:
```php
// In Channel entity
const TYPE_CUSTOM = 'custom';
```

2. Implement access control:
```php
// In ChannelService
public function canAccessCustomChannel($channelId, $userId) {
    // Custom logic here
}
```

3. Add UI support:
```javascript
// Channel creation form
<option value="custom">Custom Channel</option>
```