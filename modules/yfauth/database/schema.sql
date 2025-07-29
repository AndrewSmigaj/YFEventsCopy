-- YFAuth Module Database Schema
-- Comprehensive authentication and authorization system

-- Users table
CREATE TABLE IF NOT EXISTS yfa_auth_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'locked', 'pending') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(100),
    two_factor_secret VARCHAR(100),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    failed_login_attempts INT DEFAULT 0,
    last_failed_login TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
);

-- Roles table
CREATE TABLE IF NOT EXISTS yfa_auth_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE COMMENT 'System roles cannot be deleted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
);

-- Permissions table
CREATE TABLE IF NOT EXISTS yfa_auth_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    module VARCHAR(50) DEFAULT 'core' COMMENT 'Which module this permission belongs to',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_module (module)
);

-- Role permissions mapping
CREATE TABLE IF NOT EXISTS yfa_auth_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES yfa_auth_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES yfa_auth_permissions(id) ON DELETE CASCADE
);

-- User roles mapping
CREATE TABLE IF NOT EXISTS yfa_auth_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES yfa_auth_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL
);

-- Sessions table for better session management
CREATE TABLE IF NOT EXISTS yfa_auth_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_last_activity (last_activity)
);

-- Password reset tokens
CREATE TABLE IF NOT EXISTS yfa_auth_password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(100) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id)
);

-- Activity log for security
CREATE TABLE IF NOT EXISTS yfa_auth_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Login attempts tracking for brute force protection
CREATE TABLE IF NOT EXISTS yfa_login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    user_agent TEXT,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO yfa_auth_roles (name, display_name, description, is_system) VALUES
('super_admin', 'Super Administrator', 'Full system access', TRUE),
('calendar_admin', 'Calendar Administrator', 'Full access to calendar and events', TRUE),
('calendar_moderator', 'Calendar Moderator', 'Can approve/edit events but not system settings', TRUE),
('shop_owner', 'Shop Owner', 'Can manage own shop listings', TRUE),
('shop_moderator', 'Shop Moderator', 'Can approve/edit all shop listings', TRUE),
('claim_seller', 'Claim Sale Seller', 'Can create and manage claim sales', TRUE),
('registered_user', 'Registered User', 'Basic authenticated user', TRUE);

-- Insert core permissions
INSERT INTO yfa_auth_permissions (name, display_name, description, module) VALUES
-- User management
('users.view', 'View Users', 'View user list and profiles', 'core'),
('users.create', 'Create Users', 'Create new user accounts', 'core'),
('users.edit', 'Edit Users', 'Edit user information', 'core'),
('users.delete', 'Delete Users', 'Delete user accounts', 'core'),
('users.manage_roles', 'Manage User Roles', 'Assign/remove roles from users', 'core'),

-- Role management
('roles.view', 'View Roles', 'View roles and permissions', 'core'),
('roles.create', 'Create Roles', 'Create new roles', 'core'),
('roles.edit', 'Edit Roles', 'Edit role permissions', 'core'),
('roles.delete', 'Delete Roles', 'Delete non-system roles', 'core'),

-- Calendar/Events permissions
('events.view', 'View Events', 'View all events', 'yfevents'),
('events.create', 'Create Events', 'Submit new events', 'yfevents'),
('events.edit_own', 'Edit Own Events', 'Edit events they created', 'yfevents'),
('events.edit_all', 'Edit All Events', 'Edit any event', 'yfevents'),
('events.delete', 'Delete Events', 'Delete events', 'yfevents'),
('events.approve', 'Approve Events', 'Approve pending events', 'yfevents'),
('events.manage_sources', 'Manage Event Sources', 'Configure scraping sources', 'yfevents'),

-- Shop permissions
('shops.view', 'View Shops', 'View all shops', 'yfevents'),
('shops.create', 'Create Shops', 'Create new shop listings', 'yfevents'),
('shops.edit_own', 'Edit Own Shop', 'Edit their own shop', 'yfevents'),
('shops.edit_all', 'Edit All Shops', 'Edit any shop', 'yfevents'),
('shops.delete', 'Delete Shops', 'Delete shop listings', 'yfevents'),
('shops.approve', 'Approve Shops', 'Approve pending shops', 'yfevents'),

-- Claim sale permissions
('claims.view', 'View Claim Sales', 'View all claim sales', 'yfclaim'),
('claims.create', 'Create Claim Sales', 'Create new claim sales', 'yfclaim'),
('claims.edit_own', 'Edit Own Sales', 'Edit their own claim sales', 'yfclaim'),
('claims.edit_all', 'Edit All Sales', 'Edit any claim sale', 'yfclaim'),
('claims.delete', 'Delete Sales', 'Delete claim sales', 'yfclaim'),
('claims.manage_offers', 'Manage Offers', 'Accept/reject offers on items', 'yfclaim'),

-- System permissions
('system.view_logs', 'View System Logs', 'View activity and error logs', 'core'),
('system.manage_settings', 'Manage Settings', 'Change system settings', 'core'),
('system.manage_modules', 'Manage Modules', 'Install/uninstall modules', 'core');

-- Assign permissions to roles
-- Super Admin gets everything
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'super_admin'),
    id
FROM yfa_auth_permissions;

-- Calendar Admin
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'calendar_admin'),
    id
FROM yfa_auth_permissions
WHERE name IN (
    'events.view', 'events.create', 'events.edit_all', 'events.delete', 
    'events.approve', 'events.manage_sources',
    'shops.view', 'shops.create', 'shops.edit_all', 'shops.delete', 'shops.approve'
);

-- Calendar Moderator
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'calendar_moderator'),
    id
FROM yfa_auth_permissions
WHERE name IN (
    'events.view', 'events.create', 'events.edit_all', 'events.approve',
    'shops.view', 'shops.edit_all', 'shops.approve'
);

-- Shop Owner
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'shop_owner'),
    id
FROM yfa_auth_permissions
WHERE name IN ('shops.view', 'shops.create', 'shops.edit_own');

-- Shop Moderator
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'shop_moderator'),
    id
FROM yfa_auth_permissions
WHERE name IN ('shops.view', 'shops.edit_all', 'shops.approve');

-- Claim Seller
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'claim_seller'),
    id
FROM yfa_auth_permissions
WHERE name IN (
    'claims.view', 'claims.create', 'claims.edit_own', 'claims.manage_offers'
);

-- Create default admin user (password: ChangeMe123!)
INSERT INTO yfa_auth_users (email, username, password_hash, first_name, last_name, status, email_verified)
VALUES (
    'admin@yakimafinds.com',
    'admin',
    '$2y$10$YXpqQGPkFSr5lQNlBvZHXOpI4gF7B/I.xX.wHrZkGx8nxfr.cG9Gy',
    'System',
    'Administrator',
    'active',
    TRUE
);

-- Assign super_admin role to default admin
INSERT INTO yfa_auth_user_roles (user_id, role_id)
VALUES (
    (SELECT id FROM yfa_auth_users WHERE username = 'admin'),
    (SELECT id FROM yfa_auth_roles WHERE name = 'super_admin')
);