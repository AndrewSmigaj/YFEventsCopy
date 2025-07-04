-- ========================================
-- YFClaim Unified Authentication Schema
-- ========================================
-- This script migrates YFClaim to use the unified YFAuth system
-- All users (admins, sellers, buyers) will be in yfa_auth_users

-- Add seller role to existing auth system
INSERT INTO yfa_auth_roles (name, description) 
VALUES ('seller', 'Estate Sale Seller - Can manage estate sales and items')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Add seller-specific permissions
INSERT INTO yfa_auth_permissions (name, description) VALUES
('seller.dashboard', 'Access seller dashboard'),
('seller.sales.create', 'Create new estate sales'),
('seller.sales.edit', 'Edit own estate sales'),
('seller.sales.delete', 'Delete own estate sales'),
('seller.items.manage', 'Manage sale items and images'),
('seller.inquiries.view', 'View and respond to buyer inquiries'),
('seller.reports.view', 'View sales reports and analytics'),
('seller.profile.edit', 'Edit seller profile and company info')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Assign all seller permissions to seller role
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM yfa_auth_roles r
CROSS JOIN yfa_auth_permissions p 
WHERE r.name = 'seller' 
AND p.name LIKE 'seller.%'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Create seller profiles table (extends yfa_auth_users)
CREATE TABLE IF NOT EXISTS yfa_seller_profiles (
    user_id INT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2) DEFAULT 'WA',
    zip VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    website VARCHAR(255),
    description TEXT,
    logo_filename VARCHAR(255),
    business_license VARCHAR(100),
    insurance_info TEXT,
    commission_rate DECIMAL(5,2) DEFAULT 0.00,
    payment_info JSON,
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    INDEX idx_company (company_name),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create temporary mapping table to preserve relationships during migration
CREATE TEMPORARY TABLE IF NOT EXISTS seller_id_mapping (
    old_seller_id INT,
    new_user_id INT,
    PRIMARY KEY (old_seller_id)
);

-- Migrate existing sellers to unified auth (if any exist)
-- This preserves existing data during migration
INSERT INTO yfa_auth_users (username, email, password_hash, created_at)
SELECT 
    LOWER(REPLACE(company_name, ' ', '_')) as username,
    email,
    password_hash,
    created_at
FROM yfc_sellers
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Map old seller IDs to new user IDs
INSERT INTO seller_id_mapping (old_seller_id, new_user_id)
SELECT s.id, u.id
FROM yfc_sellers s
JOIN yfa_auth_users u ON s.email = u.email;

-- Create seller profiles for migrated users
INSERT INTO yfa_seller_profiles (
    user_id, company_name, contact_name, phone, address, 
    city, state, zip, latitude, longitude
)
SELECT 
    u.id,
    s.company_name,
    s.contact_name,
    s.phone,
    s.address,
    s.city,
    s.state,
    s.zip,
    s.latitude,
    s.longitude
FROM yfc_sellers s
JOIN yfa_auth_users u ON s.email = u.email
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- Assign seller role to migrated users
INSERT INTO yfa_auth_user_roles (user_id, role_id)
SELECT u.id, r.id
FROM yfc_sellers s
JOIN yfa_auth_users u ON s.email = u.email
CROSS JOIN yfa_auth_roles r WHERE r.name = 'seller'
ON DUPLICATE KEY UPDATE user_id = VALUES(user_id);

-- Update YFClaim tables to reference yfa_auth_users
-- First, add new column for user_id
ALTER TABLE yfc_sales 
    ADD COLUMN seller_user_id INT AFTER seller_id;

ALTER TABLE yfc_notifications 
    ADD COLUMN seller_user_id INT AFTER seller_id;

-- Update the new columns with mapped user IDs
UPDATE yfc_sales s
JOIN seller_id_mapping m ON s.seller_id = m.old_seller_id
SET s.seller_user_id = m.new_user_id;

UPDATE yfc_notifications n
JOIN seller_id_mapping m ON n.seller_id = m.old_seller_id
SET n.seller_user_id = m.new_user_id;

-- Add foreign key constraints
ALTER TABLE yfc_sales
    ADD CONSTRAINT fk_sales_seller_user 
    FOREIGN KEY (seller_user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE;

ALTER TABLE yfc_notifications
    ADD CONSTRAINT fk_notifications_seller_user 
    FOREIGN KEY (seller_user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE;

-- Create inquiries table for buyer contact forms
CREATE TABLE IF NOT EXISTS yfc_inquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT,
    item_id INT,
    seller_user_id INT NOT NULL,
    buyer_name VARCHAR(255) NOT NULL,
    buyer_email VARCHAR(255) NOT NULL,
    buyer_phone VARCHAR(20),
    subject VARCHAR(255),
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'responded', 'closed') DEFAULT 'new',
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id) ON DELETE SET NULL,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    INDEX idx_seller (seller_user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_email (buyer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Responses to inquiries
CREATE TABLE IF NOT EXISTS yfc_inquiry_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    inquiry_id INT NOT NULL,
    responder_user_id INT NOT NULL,
    message TEXT NOT NULL,
    sent_to_buyer BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (inquiry_id) REFERENCES yfc_inquiries(id) ON DELETE CASCADE,
    FOREIGN KEY (responder_user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    INDEX idx_inquiry (inquiry_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create view for backward compatibility (temporary)
-- This allows existing code to work while we update it
CREATE OR REPLACE VIEW yfc_sellers_view AS
SELECT 
    u.id,
    p.company_name,
    p.contact_name,
    u.email,
    p.phone,
    u.password_hash,
    p.address,
    p.city,
    p.state,
    p.zip,
    p.latitude,
    p.longitude,
    CASE 
        WHEN u.is_active = 1 THEN 'active'
        ELSE 'suspended'
    END as status,
    u.created_at,
    u.updated_at,
    u.last_login_at as last_login
FROM yfa_auth_users u
JOIN yfa_seller_profiles p ON u.id = p.user_id
JOIN yfa_auth_user_roles ur ON u.id = ur.user_id
JOIN yfa_auth_roles r ON ur.role_id = r.id
WHERE r.name = 'seller';

-- Add chat permissions for Communication Hub integration
INSERT INTO yfa_auth_permissions (name, description) VALUES
('chat.access', 'Access chat system'),
('chat.support', 'Access support chat room'),
('chat.marketplace', 'Access marketplace chat room')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Grant chat permissions to seller role
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM yfa_auth_roles r
CROSS JOIN yfa_auth_permissions p 
WHERE r.name = 'seller' 
AND p.name LIKE 'chat.%'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Grant chat permissions to admin role
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT r.id, p.id 
FROM yfa_auth_roles r
CROSS JOIN yfa_auth_permissions p 
WHERE r.name = 'admin' 
AND p.name LIKE 'chat.%'
ON DUPLICATE KEY UPDATE role_id = VALUES(role_id);

-- Output migration summary
SELECT 'Migration Summary:' as '';
SELECT CONCAT('Sellers migrated: ', COUNT(*)) as Result FROM seller_id_mapping;
SELECT CONCAT('Sales updated: ', COUNT(*)) as Result FROM yfc_sales WHERE seller_user_id IS NOT NULL;
SELECT CONCAT('Notifications updated: ', COUNT(*)) as Result FROM yfc_notifications WHERE seller_user_id IS NOT NULL;