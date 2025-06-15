-- Enhanced Authentication Schema with Security Features
-- Based on Gemini's security recommendations

-- Drop existing tables if they exist (for clean install)
DROP TABLE IF EXISTS auth_login_logs;
DROP TABLE IF EXISTS auth_oauth_accounts;
DROP TABLE IF EXISTS auth_user_mfa;
DROP TABLE IF EXISTS auth_user_roles;
DROP TABLE IF EXISTS auth_role_permissions;
DROP TABLE IF EXISTS auth_permissions;
DROP TABLE IF EXISTS auth_roles;
DROP TABLE IF EXISTS auth_password_resets;
DROP TABLE IF EXISTS auth_users;

-- Enhanced user management with security features
CREATE TABLE auth_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- bcrypt/Argon2 hashed
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255) NULL,
    last_login_at TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    must_change_password BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_active (is_active),
    INDEX idx_email_verified (email_verified),
    INDEX idx_locked_until (locked_until)
);

-- Role-based access control
CREATE TABLE auth_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_system_role BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_system (is_system_role)
);

-- Granular permissions system
CREATE TABLE auth_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL, -- e.g., 'event.create', 'claim.manage'
    category VARCHAR(100) NOT NULL, -- e.g., 'event', 'claim', 'admin'
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_category (category)
);

-- Role-permission associations
CREATE TABLE auth_role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NULL, -- User who granted this permission
    
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES auth_permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES auth_users(id) ON DELETE SET NULL
);

-- User-role associations
CREATE TABLE auth_user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL, -- User who assigned this role
    expires_at TIMESTAMP NULL, -- Optional expiration
    
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES auth_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES auth_users(id) ON DELETE SET NULL,
    
    INDEX idx_expires (expires_at)
);

-- Multi-factor authentication
CREATE TABLE auth_user_mfa (
    user_id INT PRIMARY KEY,
    mfa_type ENUM('totp', 'sms', 'email') NOT NULL,
    mfa_secret VARCHAR(500), -- Encrypted storage
    backup_codes JSON, -- Encrypted backup codes
    is_enabled BOOLEAN DEFAULT FALSE,
    enabled_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE
);

-- Security monitoring and audit logs
CREATE TABLE auth_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(255) NULL, -- Store username even if user is deleted
    ip_address VARCHAR(45) NOT NULL, -- IPv6 support
    user_agent VARCHAR(1000),
    login_result ENUM('success', 'failed_password', 'failed_mfa', 'account_locked', 'account_disabled') NOT NULL,
    failure_reason VARCHAR(255) NULL,
    session_id VARCHAR(255) NULL,
    country_code VARCHAR(2) NULL, -- For geolocation tracking
    city VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE SET NULL,
    INDEX idx_user_time (user_id, created_at),
    INDEX idx_ip_time (ip_address, created_at),
    INDEX idx_result (login_result),
    INDEX idx_session (session_id)
);

-- OAuth2 integration
CREATE TABLE auth_oauth_accounts (
    user_id INT NOT NULL,
    provider VARCHAR(50) NOT NULL, -- 'google', 'github', 'facebook'
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255),
    provider_name VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    expires_at TIMESTAMP NULL,
    scope VARCHAR(500), -- OAuth scopes granted
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (user_id, provider),
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_provider_user (provider, provider_user_id)
);

-- Password reset tokens
CREATE TABLE auth_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(1000),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id)
);

-- JWT token blacklist (for logout/invalidation)
CREATE TABLE auth_token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(255) NOT NULL, -- SHA256 hash of JWT
    user_id INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    reason ENUM('logout', 'security', 'expired', 'revoked') DEFAULT 'logout',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id)
);

-- Security events and alerts
CREATE TABLE auth_security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type ENUM('suspicious_login', 'password_changed', 'mfa_enabled', 'mfa_disabled', 'role_changed', 'account_locked', 'multiple_failures') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    description TEXT,
    metadata JSON, -- Additional event data
    ip_address VARCHAR(45),
    user_agent VARCHAR(1000),
    resolved BOOLEAN DEFAULT FALSE,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES auth_users(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_id, event_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (resolved),
    INDEX idx_created (created_at)
);

-- Insert default roles
INSERT INTO auth_roles (name, display_name, description, is_system_role) VALUES
('super_admin', 'Super Administrator', 'Full system access with all permissions', TRUE),
('admin', 'Administrator', 'Administrative access to most features', TRUE),
('editor', 'Editor', 'Can create and edit content', TRUE),
('moderator', 'Moderator', 'Can moderate content and users', TRUE),
('user', 'User', 'Basic user access', TRUE);

-- Temporary MFA codes for SMS/Email
CREATE TABLE auth_mfa_codes (
    user_id INT PRIMARY KEY,
    code VARCHAR(255) NOT NULL, -- Hashed code
    phone_number VARCHAR(20) NULL, -- For SMS codes
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at)
);

-- Remember me tokens for persistent login
CREATE TABLE auth_remember_tokens (
    user_id INT PRIMARY KEY,
    token VARCHAR(255) NOT NULL, -- Hashed token
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES auth_users(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at)
);

-- Insert default permissions
INSERT INTO auth_permissions (name, category, display_name, description) VALUES
-- System permissions
('system.admin', 'system', 'System Administration', 'Full system administration access'),
('system.users', 'system', 'User Management', 'Manage users and roles'),
('system.settings', 'system', 'System Settings', 'Modify system configuration'),

-- Event permissions
('event.view', 'event', 'View Events', 'View event listings'),
('event.create', 'event', 'Create Events', 'Create new events'),
('event.edit', 'event', 'Edit Events', 'Edit existing events'),
('event.delete', 'event', 'Delete Events', 'Delete events'),
('event.approve', 'event', 'Approve Events', 'Approve pending events'),
('event.moderate', 'event', 'Moderate Events', 'Moderate event content'),

-- Claim permissions
('claim.view', 'claim', 'View Claims', 'View estate sale claims'),
('claim.create', 'claim', 'Create Claims', 'Create new claims'),
('claim.edit', 'claim', 'Edit Claims', 'Edit existing claims'),
('claim.delete', 'claim', 'Delete Claims', 'Delete claims'),
('claim.manage', 'claim', 'Manage Claims', 'Full claim management'),

-- Shop permissions
('shop.view', 'shop', 'View Shops', 'View shop listings'),
('shop.create', 'shop', 'Create Shops', 'Create new shops'),
('shop.edit', 'shop', 'Edit Shops', 'Edit existing shops'),
('shop.delete', 'shop', 'Delete Shops', 'Delete shops'),
('shop.claim', 'shop', 'Claim Shops', 'Claim business ownership'),

-- Scraper permissions
('scraper.view', 'scraper', 'View Scrapers', 'View scraper configurations'),
('scraper.edit', 'scraper', 'Edit Scrapers', 'Modify scraper settings'),
('scraper.run', 'scraper', 'Run Scrapers', 'Execute scraping operations');

-- Assign permissions to roles
-- Super Admin gets all permissions
INSERT INTO auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM auth_roles r, auth_permissions p WHERE r.name = 'super_admin';

-- Admin gets most permissions except system admin
INSERT INTO auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM auth_roles r, auth_permissions p 
WHERE r.name = 'admin' AND p.name != 'system.admin';

-- Editor gets content management permissions
INSERT INTO auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM auth_roles r, auth_permissions p 
WHERE r.name = 'editor' AND p.name IN (
    'event.view', 'event.create', 'event.edit',
    'shop.view', 'shop.create', 'shop.edit',
    'claim.view', 'claim.create', 'claim.edit'
);

-- Moderator gets moderation permissions
INSERT INTO auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM auth_roles r, auth_permissions p 
WHERE r.name = 'moderator' AND p.name IN (
    'event.view', 'event.approve', 'event.moderate',
    'shop.view', 'claim.view'
);

-- User gets basic view permissions
INSERT INTO auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM auth_roles r, auth_permissions p 
WHERE r.name = 'user' AND p.name IN (
    'event.view', 'shop.view', 'claim.view'
);