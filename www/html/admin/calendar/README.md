# Advanced Admin Dashboard Access

## Access Methods

### 1. Normal Login
- Go to: http://backoffice.yakimafinds.com/admin/login.php
- Username: YakFind
- Password: MapTime
- After login, navigate to: http://backoffice.yakimafinds.com/admin/calendar/

### 2. Direct Links (after login)
- Events Management: http://backoffice.yakimafinds.com/admin/calendar/events.php
- Event Sources: http://backoffice.yakimafinds.com/admin/calendar/sources.php
- Shop Management: http://backoffice.yakimafinds.com/admin/calendar/shops.php

### 3. Temporary Test Access
For testing without login:
1. Go to: http://backoffice.yakimafinds.com/admin/calendar/test-access.php?token=YakFind2025Admin
2. This will grant temporary admin access for your session
3. Then visit the advanced admin dashboard

### 4. Session Test
To check your current session status:
- Visit: http://backoffice.yakimafinds.com/admin/calendar/session-test.php

## Troubleshooting

If links are not working:
1. Make sure you're logged in first
2. Clear your browser cache and cookies
3. Try using the test access method above

## Fixed Issues
- Authentication redirects now use relative paths
- Login page now supports redirect parameter
- All calendar admin pages properly check authentication