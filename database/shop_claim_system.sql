-- Shop Claim Request System Database Schema
-- Allows users to claim ownership of existing shops or request new shop listings

-- Shop claim requests table
CREATE TABLE IF NOT EXISTS shop_claim_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NULL COMMENT 'Existing shop being claimed (NULL for new shop requests)',
    requester_name VARCHAR(255) NOT NULL,
    requester_email VARCHAR(255) NOT NULL,
    requester_phone VARCHAR(20),
    business_name VARCHAR(255) NOT NULL COMMENT 'Shop name for new requests or claimed name',
    business_address TEXT,
    business_description TEXT,
    business_website VARCHAR(500),
    business_phone VARCHAR(20),
    
    -- Verification information
    claim_type ENUM('existing_shop', 'new_shop') NOT NULL,
    verification_documents JSON COMMENT 'Uploaded document paths and metadata',
    ownership_proof TEXT COMMENT 'Description of how they can prove ownership',
    relationship_to_business ENUM('owner', 'manager', 'employee', 'authorized_rep') NOT NULL,
    
    -- Request status and workflow
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'additional_info_needed') DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    
    -- Admin workflow
    assigned_admin_id INT NULL COMMENT 'Admin reviewing this claim',
    admin_notes TEXT COMMENT 'Internal notes for admin review',
    rejection_reason TEXT COMMENT 'Reason for rejection if applicable',
    
    -- Communication
    applicant_notes TEXT COMMENT 'Additional information from applicant',
    admin_response TEXT COMMENT 'Response from admin to applicant',
    last_contact_attempt TIMESTAMP NULL,
    
    -- Metadata
    ip_address VARCHAR(45) COMMENT 'IP address of submission',
    user_agent TEXT COMMENT 'Browser/device information',
    referrer_url VARCHAR(500) COMMENT 'How they found the claim form',
    
    -- Timestamps
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign keys
    FOREIGN KEY (shop_id) REFERENCES local_shops(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_admin_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL,
    
    -- Indexes
    INDEX idx_status (status),
    INDEX idx_claim_type (claim_type),
    INDEX idx_submitted (submitted_at),
    INDEX idx_email (requester_email),
    INDEX idx_shop (shop_id),
    INDEX idx_assigned_admin (assigned_admin_id)
);

-- Shop claim request attachments
CREATE TABLE IF NOT EXISTS shop_claim_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_request_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100),
    upload_path VARCHAR(500) NOT NULL,
    document_type ENUM('business_license', 'utility_bill', 'lease_agreement', 'tax_document', 'other') NOT NULL,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (claim_request_id) REFERENCES shop_claim_requests(id) ON DELETE CASCADE,
    INDEX idx_claim_request (claim_request_id),
    INDEX idx_document_type (document_type)
);

-- Shop claim request activity log
CREATE TABLE IF NOT EXISTS shop_claim_activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    claim_request_id INT NOT NULL,
    user_id INT NULL COMMENT 'Admin who performed the action',
    action VARCHAR(100) NOT NULL,
    details TEXT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (claim_request_id) REFERENCES shop_claim_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL,
    INDEX idx_claim_request (claim_request_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Shop ownership assignments (when claims are approved)
CREATE TABLE IF NOT EXISTS shop_ownership_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    shop_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'User who was granted ownership',
    claim_request_id INT NULL COMMENT 'Original claim request that led to this assignment',
    assigned_by INT NOT NULL COMMENT 'Admin who approved the assignment',
    ownership_type ENUM('primary_owner', 'manager', 'authorized_rep') DEFAULT 'primary_owner',
    permissions JSON COMMENT 'Specific permissions granted to this user for this shop',
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    revoked_by INT NULL,
    
    FOREIGN KEY (shop_id) REFERENCES local_shops(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
    FOREIGN KEY (claim_request_id) REFERENCES shop_claim_requests(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_by) REFERENCES yfa_auth_users(id),
    FOREIGN KEY (revoked_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_active_primary_owner (shop_id, ownership_type, is_active),
    INDEX idx_shop (shop_id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    INDEX idx_assigned (assigned_at)
);

-- Add shop claim permissions to the auth system
INSERT INTO yfa_auth_permissions (name, display_name, description, module) VALUES
('shop_claims.view', 'View Shop Claims', 'View shop claim requests', 'yfevents'),
('shop_claims.review', 'Review Shop Claims', 'Review and process claim requests', 'yfevents'),
('shop_claims.approve', 'Approve Shop Claims', 'Approve or reject claim requests', 'yfevents'),
('shop_claims.assign_ownership', 'Assign Shop Ownership', 'Grant shop ownership to users', 'yfevents'),
('shop_claims.manage_all', 'Manage All Claims', 'Full access to claim management system', 'yfevents');

-- Add shop ownership permissions
INSERT INTO yfa_auth_permissions (name, display_name, description, module) VALUES
('shop_ownership.view_own', 'View Own Shops', 'View shops they own or manage', 'yfevents'),
('shop_ownership.edit_own', 'Edit Own Shops', 'Edit shops they own or manage', 'yfevents'),
('shop_ownership.manage_events', 'Manage Shop Events', 'Add/edit events for their shops', 'yfevents'),
('shop_ownership.view_analytics', 'View Shop Analytics', 'Access analytics for their shops', 'yfevents');

-- Assign claim permissions to appropriate roles
-- Calendar Admin gets full claim management
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'calendar_admin'),
    id
FROM yfa_auth_permissions
WHERE name LIKE 'shop_claims.%' OR name LIKE 'shop_ownership.%';

-- Shop Moderator gets review and approval permissions
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'shop_moderator'),
    id
FROM yfa_auth_permissions
WHERE name IN ('shop_claims.view', 'shop_claims.review', 'shop_claims.approve');

-- Shop Owner gets ownership permissions
INSERT INTO yfa_auth_role_permissions (role_id, permission_id)
SELECT 
    (SELECT id FROM yfa_auth_roles WHERE name = 'shop_owner'),
    id
FROM yfa_auth_permissions
WHERE name LIKE 'shop_ownership.%';

-- Super Admin gets everything (already covered by existing super admin assignment)

-- Create indexes for better performance
CREATE INDEX idx_shop_claim_email_status ON shop_claim_requests(requester_email, status);
CREATE INDEX idx_shop_claim_type_status ON shop_claim_requests(claim_type, status);
CREATE INDEX idx_ownership_shop_active ON shop_ownership_assignments(shop_id, is_active);