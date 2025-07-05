# YFEvents Communication System Documentation

## Overview

The YFEvents Communication System provides real-time chat functionality for sellers and administrators. It's built using Clean Architecture principles and integrates seamlessly with the YFClaim module for estate sale management.

## Architecture

### Database Schema

The system uses the following `communication_*` tables:

- **communication_channels**: Chat channels/rooms
- **communication_messages**: Individual messages
- **communication_participants**: Channel membership
- **communication_notifications**: User notifications
- **communication_attachments**: File attachments
- **communication_email_addresses**: Email integration
- **communication_reactions**: Message reactions

### Key Components

1. **Domain Layer** (`src/Domain/Communication/`)
   - Entities: Channel, Message, Participant, Notification
   - Services: ChannelService, MessageService, AnnouncementService

2. **Infrastructure Layer** (`src/Infrastructure/Repositories/Communication/`)
   - ChannelRepository
   - MessageRepository
   - ParticipantRepository
   - NotificationRepository

3. **Application Layer** (`src/Application/Services/Communication/`)
   - CommunicationService: Main orchestration service
   - AdminSellerChatService: Handles seller-specific functionality

4. **Presentation Layer**
   - Web Interface: `/www/html/communication/`
   - API Endpoints: `/www/html/api/communication/`

## Features

### 1. Global Chat Channels

Two default channels are created:
- **Support Channel**: For help and admin support
- **Selling Tips**: For sharing best practices

### 2. Seller Dashboard Integration

The chat system is embedded in the seller dashboard using an iframe approach:
- Navigation item with unread badge
- Embedded chat interface
- Real-time unread count updates

### 3. Real-time Messaging

- Text messages with @mentions support
- System messages for important notifications
- Message history with pagination
- Unread message indicators

### 4. Security

- Authentication required for all endpoints
- Session-based access control
- Seller verification for embedded mode
- CSRF protection

## Setup Instructions

### 1. Database Setup

```bash
# Install communication schema
mysql -u yfevents -pyfevents_pass yakima_finds < database/communication_schema_fixed.sql

# Seed default channels
mysql -u yfevents -pyfevents_pass yakima_finds < database/seed_communication_channels.sql
```

### 2. Configuration

The system uses environment variables from `.env`:
```env
FEATURE_CHAT_ENABLED=true
```

### 3. Testing

Run the test suite:
```bash
php tests/test_chat_system_updated.php
php test_chat_basic.php
```

## Integration Guide

### For Sellers

1. **Automatic Channel Membership**: When sellers log in, they're automatically added to global channels
2. **Dashboard Access**: Click "Messages" tab in seller dashboard
3. **Unread Notifications**: Badge shows unread count, updates every 30 seconds

### For Developers

#### Adding a New Channel

```php
$channelData = [
    'name' => 'New Channel',
    'slug' => 'new-channel',
    'type' => 'public',
    'description' => 'Channel description',
    'created_by_user_id' => $userId
];

$channel = $communicationService->createChannel($channelData);
```

#### Sending a Message

```php
$messageData = [
    'channel_id' => $channelId,
    'user_id' => $userId,
    'content' => 'Message content',
    'content_type' => 'text'
];

$message = $communicationService->sendMessage($messageData);
```

#### Getting Unread Count

```php
$channelsWithUnread = $communicationService->getUserChannelsWithUnread($userId);
$totalUnread = array_sum(array_column($channelsWithUnread, 'unread_count'));
```

## API Endpoints

### GET /api/communication/unread-count
Returns total unread message count for authenticated user.

**Response:**
```json
{
    "success": true,
    "unread": 5,
    "timestamp": 1234567890
}
```

### GET /api/communication/channels
Returns user's channels with details.

### POST /api/communication/messages
Send a new message.

**Request:**
```json
{
    "channel_id": 1,
    "content": "Message text"
}
```

### POST /api/communication/channels/{id}/read
Mark all messages in a channel as read.

## Troubleshooting

### Common Issues

1. **Tables not found**
   - Run the database setup scripts
   - Ensure correct database credentials

2. **Foreign key errors**
   - Ensure yfa_auth_users table exists
   - User must exist before adding to channels

3. **Unread count not updating**
   - Check JavaScript console for errors
   - Verify API endpoint is accessible

4. **Chat not loading in iframe**
   - Check seller authentication
   - Verify embedded=true parameter

### Debug Mode

Enable debug logging:
```php
// In communication interface
if (isset($_GET['debug'])) {
    error_log('Communication Debug: ' . json_encode($_SESSION));
}
```

## Future Enhancements

1. **File Attachments**: Upload images and documents
2. **Private Messaging**: Direct messages between users
3. **Push Notifications**: Real-time browser notifications
4. **Mobile App**: Native mobile support
5. **Video Chat**: WebRTC integration
6. **Message Search**: Full-text search capability
7. **Emoji Reactions**: React to messages
8. **Typing Indicators**: Show when users are typing

## Security Considerations

1. **Input Validation**: All user input is sanitized
2. **SQL Injection**: Using prepared statements
3. **XSS Prevention**: HTML encoding for display
4. **CSRF Protection**: Token validation
5. **Rate Limiting**: Prevent spam/abuse
6. **Access Control**: Role-based permissions

## Performance

1. **Database Indexes**: Optimized queries
2. **Message Pagination**: Load messages in chunks
3. **Caching**: Consider Redis for active data
4. **WebSocket**: For true real-time updates

## Maintenance

### Regular Tasks

1. **Archive Old Messages**: Move to archive tables
2. **Clean Notifications**: Remove read notifications
3. **Monitor Performance**: Check slow queries
4. **Update Dependencies**: Keep libraries current

### Backup

Include these tables in backups:
- communication_channels
- communication_messages
- communication_participants
- communication_notifications

## Support

For issues or questions:
1. Check this documentation
2. Run test scripts
3. Review error logs
4. Contact development team