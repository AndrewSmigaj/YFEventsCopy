# YFEvents Communication Tool Setup Guide

## Overview

The YFEvents Communication Tool is a pure messaging and discussion platform that provides channel-based communication, email integration, and announcements for vendors, event organizers, and staff. It follows the existing YFEvents architecture patterns and integrates seamlessly with the current system.

## Installation Steps

### 1. Database Setup

First, create the communication tables in your database:

```bash
mysql -u yfevents -p yakima_finds < database/communication_schema.sql
```

This will create the following tables:
- `communication_channels` - Discussion channels
- `communication_messages` - Channel messages
- `communication_participants` - Channel membership
- `communication_attachments` - File uploads
- `communication_notifications` - User notifications
- `communication_email_addresses` - Email integration
- `communication_reactions` - Message reactions

### 2. Container Registration

Add the communication services to your dependency injection container. If you have a central container configuration, include the registrations from:

```php
// In your main container configuration
require __DIR__ . '/config/services/communication.php';
```

Or manually register the services in your container setup.

### 3. Route Configuration

Add the API routes to your routing configuration:

```php
// In your main API routes file
require __DIR__ . '/routes/api/communication.php';
```

### 4. File Upload Directory

Create the upload directory for communication attachments:

```bash
mkdir -p www/html/uploads/communication
chmod 755 www/html/uploads/communication
```

### 5. Frontend Access

The communication interface is available at:
- Main Interface: `/communication/`
- API Endpoints: `/api/communication/*`

## Configuration

### Environment Variables

Add these to your `.env` file if needed:

```env
# Communication settings
COMMUNICATION_MAX_FILE_SIZE=10485760  # 10MB
COMMUNICATION_ALLOWED_FILE_TYPES=jpeg,jpg,png,gif,pdf,doc,docx,xls,xlsx
COMMUNICATION_MESSAGE_MAX_LENGTH=5000
```

### Email Integration

To enable email functionality, extend the existing EmailService:

```php
// The CommunicationEmailService extends your existing EmailService
// Configure SMTP settings in your existing email configuration
```

## Usage

### Creating Channels

1. **Public Channels**: Open discussion for all users
2. **Event Channels**: Tied to specific events (requires event_id)
3. **Vendor Channels**: Business-specific discussions (requires shop_id)
4. **Announcement Channels**: One-way broadcast communications

### User Roles

The system uses existing YFEvents user roles:
- **admin**: Full system access, can create announcements
- **editor**: Event organizers with channel management
- **user + shop_id**: Vendors who can create vendor channels
- **user**: General users with basic messaging

### API Authentication

The system uses session-based authentication. Ensure users are logged in before accessing the communication features.

## Features

### Core Functionality
- ✅ Channel-based messaging
- ✅ Message threading
- ✅ File attachments
- ✅ User mentions (@username)
- ✅ Message search
- ✅ Pinned messages
- ✅ Edit/delete messages
- ✅ Unread message counts
- ✅ YFClaim item references

### Email Features (To Be Implemented)
- 📧 Channel email addresses
- 📧 Email to message conversion
- 📧 Daily/weekly digests
- 📧 Notification emails

### Real-time Updates
Currently uses polling (5-second intervals). Can be upgraded to:
- Server-Sent Events (SSE)
- WebSockets
- Long polling

## Integration with YFClaim

The Communication Tool can reference YFClaim items in messages:

```php
// When sending a message with YFClaim reference
{
    "content": "Check out this item for sale",
    "yfclaim_item_id": 123
}
```

This creates a link to the YFClaim item without duplicating marketplace functionality.

## Troubleshooting

### Common Issues

1. **"Not authenticated" error**
   - Ensure user is logged in with valid session
   - Check session configuration

2. **Cannot create channels**
   - Verify user role permissions
   - Check if required fields (event_id, shop_id) are provided for specific channel types

3. **File upload fails**
   - Check upload directory permissions
   - Verify file size and type restrictions

4. **Messages not appearing**
   - Check if user is participant in channel
   - Verify database connections

### Debug Mode

Enable debug logging by adding to your communication services:

```php
// In MessageService or ChannelService
if (getenv('APP_DEBUG') === 'true') {
    error_log('Communication Debug: ' . $message);
}
```

## Security Considerations

1. **Input Validation**: All user input is validated and sanitized
2. **XSS Protection**: HTML is escaped in messages
3. **File Upload Security**: Restricted file types and sizes
4. **Access Control**: Channel-based permissions
5. **SQL Injection Prevention**: Uses prepared statements

## Performance Optimization

1. **Message Pagination**: Default 50 messages per page
2. **Channel Caching**: Consider caching channel lists
3. **Database Indexes**: Optimized for common queries
4. **File Storage**: Consider CDN for attachments

## Future Enhancements

1. **Real-time Updates**: Implement WebSocket support
2. **Rich Text Editor**: Add markdown or WYSIWYG editor
3. **Voice/Video Calls**: WebRTC integration
4. **Mobile App**: Native mobile interfaces
5. **Analytics Dashboard**: Communication metrics

## Support

For issues or questions:
1. Check the error logs in `/logs/`
2. Review the database schema
3. Verify service registrations
4. Check API endpoint responses

---

*Last Updated: December 2024*
*Version: 1.0.0*