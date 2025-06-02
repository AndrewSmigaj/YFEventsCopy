# YFAuth - Authentication & Authorization Module

## Overview

YFAuth is a comprehensive authentication and authorization module for YFEvents that provides:

- User management with secure password hashing
- Role-based access control (RBAC)
- Granular permissions system
- Session management
- Activity logging
- Support for multiple modules (YFEvents, YFClaim, etc.)

## Default Roles

The module comes with pre-configured roles:

1. **Super Administrator** (`super_admin`)
   - Full system access
   - Can manage users, roles, and all modules

2. **Calendar Administrator** (`calendar_admin`)
   - Full access to calendar and events
   - Can manage event sources and shops

3. **Calendar Moderator** (`calendar_moderator`)
   - Can approve/edit events
   - Cannot change system settings

4. **Shop Owner** (`shop_owner`)
   - Can manage their own shop listing
   - View other shops

5. **Shop Moderator** (`shop_moderator`)
   - Can approve/edit all shop listings
   - Cannot delete shops

6. **Claim Sale Seller** (`claim_seller`)
   - Can create and manage claim sales
   - Manage offers on their items

7. **Registered User** (`registered_user`)
   - Basic authenticated user
   - Default role for new users

## Installation

1. **Install the module**:
   ```bash
   php modules/install.php yfauth
   ```

2. **Update existing admin pages** to use the new authentication:
   - Replace hardcoded authentication checks
   - Use permission checks for actions

3. **Default admin account**:
   - Username: `admin`
   - Email: `admin@yakimafinds.com`
   - Password: `ChangeMe123!`
   - **IMPORTANT**: Change this password immediately!

## Usage

### In PHP Pages

```php
// At the top of admin pages
session_start();
require_once 'path/to/AuthService.php';

use YFEvents\Modules\YFAuth\Services\AuthService;

$auth = new AuthService($db);

// Check if logged in
if (!isset($_SESSION['auth_session_id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Validate session
$user = $auth->validateSession($_SESSION['auth_session_id']);
if (!$user) {
    header('Location: /admin/login.php');
    exit;
}

// Check permissions
if (!in_array('events.edit_all', $user['permissions'])) {
    die('Access denied');
}
```

### Creating Users

```php
$auth = new AuthService($db);

$newUser = $auth->createUser([
    'email' => 'user@example.com',
    'username' => 'newuser',
    'password' => 'SecurePassword123!',
    'first_name' => 'John',
    'last_name' => 'Doe',
    'default_role' => 'shop_owner',
    'auto_activate' => true
]);
```

### Checking Permissions

```php
// Check if user has specific permission
if ($auth->hasPermission($userId, 'events.approve')) {
    // Show approve button
}

// Check if user has role
if ($auth->hasRole($userId, 'calendar_admin')) {
    // Show admin features
}
```

## Permissions Reference

### User Management
- `users.view` - View user list
- `users.create` - Create users
- `users.edit` - Edit users
- `users.delete` - Delete users
- `users.manage_roles` - Assign/remove roles

### Events
- `events.view` - View all events
- `events.create` - Create events
- `events.edit_own` - Edit own events
- `events.edit_all` - Edit any event
- `events.delete` - Delete events
- `events.approve` - Approve pending events
- `events.manage_sources` - Configure scraping sources

### Shops
- `shops.view` - View shops
- `shops.create` - Create shops
- `shops.edit_own` - Edit own shop
- `shops.edit_all` - Edit any shop
- `shops.delete` - Delete shops
- `shops.approve` - Approve pending shops

### Claim Sales
- `claims.view` - View claim sales
- `claims.create` - Create claim sales
- `claims.edit_own` - Edit own sales
- `claims.edit_all` - Edit any sale
- `claims.delete` - Delete sales
- `claims.manage_offers` - Accept/reject offers

### System
- `system.view_logs` - View activity logs
- `system.manage_settings` - Change settings
- `system.manage_modules` - Install/uninstall modules

## Security Features

1. **Password Security**
   - Bcrypt hashing with cost factor 10
   - Automatic rehashing when needed
   - Minimum password requirements

2. **Session Management**
   - Secure session tokens
   - Configurable session lifetime
   - IP and user agent tracking

3. **Login Protection**
   - Failed login attempt tracking
   - Account lockout after 5 failed attempts
   - 30-minute lockout period

4. **Activity Logging**
   - All authentication events logged
   - IP address and user agent tracking
   - Audit trail for security

## Migration from Old System

To migrate from the hardcoded authentication:

1. Install YFAuth module
2. Create user accounts for existing admins
3. Update login page to use new system
4. Update all admin pages to check permissions
5. Remove old hardcoded credentials

## API Endpoints

- `POST /modules/yfauth/api/login.php` - User login
- `DELETE /modules/yfauth/api/login.php` - User logout
- `GET /modules/yfauth/api/user.php` - Get current user
- `POST /modules/yfauth/api/users.php` - Create user (admin only)
- `PUT /modules/yfauth/api/users/{id}.php` - Update user
- `POST /modules/yfauth/api/roles.php` - Manage roles

## Configuration

Module settings in `module.json`:
- `session_lifetime` - Session duration in seconds
- `enable_two_factor` - Enable 2FA (future feature)
- `password_min_length` - Minimum password length
- `max_login_attempts` - Failed attempts before lockout

## Future Enhancements

- Two-factor authentication (2FA)
- OAuth integration
- Password reset via email
- Remember me functionality
- API token authentication
- Role hierarchy
- Custom permissions per user