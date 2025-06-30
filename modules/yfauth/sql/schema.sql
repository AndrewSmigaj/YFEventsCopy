-- YFAuth Module Database Schema
-- Centralized authentication and authorization system

-- Users table (core authentication)
CREATE TABLE IF NOT EXISTS yfa_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'pending',
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    email_verified_at TIMESTAMP NULL,
    password_reset_token VARCHAR(255),
    password_reset_expires TIMESTAMP NULL,
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    last_login_at TIMESTAMP NULL,
    last_ip VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Roles table
CREATE TABLE IF NOT EXISTS yfa_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permissions table
CREATE TABLE IF NOT EXISTS yfa_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    module VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User roles junction table
CREATE TABLE IF NOT EXISTS yfa_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES yfa_users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES yfa_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES yfa_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Role permissions junction table
CREATE TABLE IF NOT EXISTS yfa_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES yfa_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES yfa_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions table
CREATE TABLE IF NOT EXISTS yfa_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES yfa_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login attempts table (for rate limiting)
CREATE TABLE IF NOT EXISTS yfa_login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255),
    ip_address VARCHAR(45),
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE,
    INDEX idx_email (email),
    INDEX idx_ip (ip_address),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auth tokens table (for API access)
CREATE TABLE IF NOT EXISTS yfa_auth_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(100),
    token VARCHAR(255) UNIQUE NOT NULL,
    abilities JSON,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- OAuth providers table
CREATE TABLE IF NOT EXISTS yfa_oauth_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_id VARCHAR(255) NOT NULL,
    access_token TEXT,
    refresh_token TEXT,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider (provider, provider_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activity log table
CREATE TABLE IF NOT EXISTS yfa_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    properties JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES yfa_users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO yfa_roles (name, display_name, description, is_system) VALUES 
('super_admin', 'Super Administrator', 'Full system access', TRUE),
('admin', 'Administrator', 'Administrative access', TRUE),
('moderator', 'Moderator', 'Content moderation access', TRUE),
('user', 'User', 'Regular user access', TRUE),
('guest', 'Guest', 'Limited guest access', TRUE);

-- Insert default permissions
INSERT INTO yfa_permissions (name, display_name, module, description) VALUES 
-- User management
('users.view', 'View Users', 'users', 'View user listings'),
('users.create', 'Create Users', 'users', 'Create new users'),
('users.edit', 'Edit Users', 'users', 'Edit existing users'),
('users.delete', 'Delete Users', 'users', 'Delete users'),
('users.manage_roles', 'Manage User Roles', 'users', 'Assign and remove user roles'),

-- Role management
('roles.view', 'View Roles', 'roles', 'View role listings'),
('roles.create', 'Create Roles', 'roles', 'Create new roles'),
('roles.edit', 'Edit Roles', 'roles', 'Edit existing roles'),
('roles.delete', 'Delete Roles', 'roles', 'Delete roles'),
('roles.manage_permissions', 'Manage Role Permissions', 'roles', 'Assign and remove role permissions'),

-- Event management
('events.view', 'View Events', 'events', 'View event listings'),
('events.create', 'Create Events', 'events', 'Create new events'),
('events.edit', 'Edit Events', 'events', 'Edit existing events'),
('events.delete', 'Delete Events', 'events', 'Delete events'),
('events.approve', 'Approve Events', 'events', 'Approve pending events'),

-- Shop management
('shops.view', 'View Shops', 'shops', 'View shop listings'),
('shops.create', 'Create Shops', 'shops', 'Create new shops'),
('shops.edit', 'Edit Shops', 'shops', 'Edit existing shops'),
('shops.delete', 'Delete Shops', 'shops', 'Delete shops'),
('shops.approve', 'Approve Shops', 'shops', 'Approve pending shops'),

-- YFClaim management
('yfclaim.admin', 'YFClaim Admin', 'yfclaim', 'Full YFClaim administrative access'),
('yfclaim.seller', 'YFClaim Seller', 'yfclaim', 'Manage own estate sales'),
('yfclaim.buyer', 'YFClaim Buyer', 'yfclaim', 'Make offers on items'),

-- System management
('system.settings', 'System Settings', 'system', 'Manage system settings'),
('system.logs', 'View System Logs', 'system', 'View system logs'),
('system.maintenance', 'System Maintenance', 'system', 'Perform system maintenance');

-- Assign permissions to super_admin role
INSERT INTO yfa_role_permissions (role_id, permission_id)
SELECT 1, id FROM yfa_permissions;

-- Assign basic permissions to admin role
INSERT INTO yfa_role_permissions (role_id, permission_id)
SELECT 2, id FROM yfa_permissions WHERE name NOT LIKE 'system.%';

-- Assign limited permissions to moderator role
INSERT INTO yfa_role_permissions (role_id, permission_id)
SELECT 3, id FROM yfa_permissions WHERE name IN ('events.view', 'events.edit', 'events.approve', 'shops.view', 'shops.edit', 'shops.approve');

-- Assign basic permissions to user role
INSERT INTO yfa_role_permissions (role_id, permission_id)
SELECT 4, id FROM yfa_permissions WHERE name IN ('events.view', 'shops.view', 'yfclaim.buyer');