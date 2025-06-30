-- YFEvents Comprehensive Audit Logging System
-- Phase 3: Advanced Audit Trail and Data Tracking
-- This script implements comprehensive audit logging across all critical tables

-- ===================================================================
-- AUDIT LOGGING INFRASTRUCTURE
-- ===================================================================

-- Central audit log table with enhanced tracking
CREATE TABLE IF NOT EXISTS comprehensive_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    record_id VARCHAR(100) NOT NULL, -- Support for composite keys
    operation ENUM('INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'ACCESS', 'PERMISSION_CHANGE') NOT NULL,
    user_id INT NULL,
    user_identifier VARCHAR(255) NULL, -- Username or email for tracking
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(255) NULL,
    
    -- Data tracking
    old_values JSON NULL,
    new_values JSON NULL,
    changed_fields JSON NULL, -- Array of field names that changed
    
    -- Context and metadata
    context_data JSON NULL, -- Additional context like admin notes, bulk operation info
    operation_source ENUM('web', 'api', 'cli', 'cron', 'system') DEFAULT 'web',
    request_method VARCHAR(10) NULL, -- GET, POST, PUT, DELETE
    request_path VARCHAR(500) NULL,
    
    -- Risk assessment
    risk_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    requires_review BOOLEAN DEFAULT FALSE,
    
    -- Timing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL, -- When review was completed
    
    -- Indexes for performance
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_time (user_id, created_at),
    INDEX idx_operation_time (operation, created_at),
    INDEX idx_risk_review (risk_level, requires_review),
    INDEX idx_source_time (operation_source, created_at),
    INDEX idx_ip_time (ip_address, created_at),
    
    -- Foreign key if user exists
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL
);

-- Data retention policy table
CREATE TABLE IF NOT EXISTS audit_retention_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    operation_type VARCHAR(50) NOT NULL,
    retention_days INT NOT NULL DEFAULT 365,
    auto_archive BOOLEAN DEFAULT TRUE,
    archive_location VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_table_operation (table_name, operation_type)
);

-- Insert default retention policies
INSERT INTO audit_retention_policy (table_name, operation_type, retention_days, auto_archive) VALUES
('events', 'INSERT', 1095, TRUE), -- 3 years for event creation
('events', 'UPDATE', 730, TRUE),  -- 2 years for event updates
('events', 'DELETE', 2555, TRUE), -- 7 years for event deletion (compliance)
('local_shops', 'INSERT', 1095, TRUE),
('local_shops', 'UPDATE', 730, TRUE),
('local_shops', 'DELETE', 2555, TRUE),
('yfa_auth_users', 'INSERT', 2555, TRUE), -- 7 years for user creation
('yfa_auth_users', 'UPDATE', 1095, TRUE), -- 3 years for user updates
('yfa_auth_users', 'DELETE', 2555, TRUE), -- 7 years for user deletion
('calendar_sources', 'INSERT', 1095, TRUE),
('calendar_sources', 'UPDATE', 730, TRUE),
('calendar_sources', 'DELETE', 1095, TRUE),
('yfc_sales', 'INSERT', 2555, TRUE), -- 7 years for sales records (tax/legal)
('yfc_sales', 'UPDATE', 1095, TRUE),
('yfc_sales', 'DELETE', 2555, TRUE),
('yfc_items', 'INSERT', 1095, TRUE),
('yfc_items', 'UPDATE', 730, TRUE),
('yfc_items', 'DELETE', 1095, TRUE),
('yfc_offers', 'INSERT', 1095, TRUE), -- 3 years for offer tracking
('yfc_offers', 'UPDATE', 730, TRUE),
('yfc_offers', 'DELETE', 1095, TRUE),
('shop_claim_requests', 'INSERT', 1095, TRUE),
('shop_claim_requests', 'UPDATE', 730, TRUE),
('shop_claim_requests', 'DELETE', 1095, TRUE)
ON DUPLICATE KEY UPDATE retention_days = VALUES(retention_days);

-- ===================================================================
-- AUDIT TRIGGER FUNCTIONS
-- ===================================================================

-- Create stored procedure for audit logging
DELIMITER //
CREATE OR REPLACE PROCEDURE LogAuditEvent(
    IN p_table_name VARCHAR(100),
    IN p_record_id VARCHAR(100),
    IN p_operation VARCHAR(20),
    IN p_user_id INT,
    IN p_old_values JSON,
    IN p_new_values JSON,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_session_id VARCHAR(255),
    IN p_context_data JSON
)
BEGIN
    DECLARE v_changed_fields JSON DEFAULT NULL;
    DECLARE v_risk_level VARCHAR(20) DEFAULT 'low';
    DECLARE v_requires_review BOOLEAN DEFAULT FALSE;
    
    -- Calculate changed fields for UPDATE operations
    IF p_operation = 'UPDATE' AND p_old_values IS NOT NULL AND p_new_values IS NOT NULL THEN
        SET v_changed_fields = JSON_ARRAY();
        -- This would need to be implemented based on specific table structures
        -- For now, we'll store the full old/new values
    END IF;
    
    -- Determine risk level based on operation and table
    CASE 
        WHEN p_table_name IN ('yfa_auth_users', 'auth_users') AND p_operation = 'DELETE' THEN
            SET v_risk_level = 'critical';
            SET v_requires_review = TRUE;
        WHEN p_table_name IN ('yfa_auth_users', 'auth_users') AND p_operation = 'UPDATE' THEN
            SET v_risk_level = 'high';
            SET v_requires_review = TRUE;
        WHEN p_table_name IN ('calendar_sources') AND p_operation = 'DELETE' THEN
            SET v_risk_level = 'high';
        WHEN p_table_name IN ('yfc_sales', 'yfc_offers') AND p_operation IN ('UPDATE', 'DELETE') THEN
            SET v_risk_level = 'medium';
        ELSE
            SET v_risk_level = 'low';
    END CASE;
    
    -- Insert audit record
    INSERT INTO comprehensive_audit_log (
        table_name, record_id, operation, user_id, 
        old_values, new_values, changed_fields,
        ip_address, user_agent, session_id, context_data,
        risk_level, requires_review
    ) VALUES (
        p_table_name, p_record_id, p_operation, p_user_id,
        p_old_values, p_new_values, v_changed_fields,
        p_ip_address, p_user_agent, p_session_id, p_context_data,
        v_risk_level, v_requires_review
    );
END //
DELIMITER ;

-- ===================================================================
-- TABLE-SPECIFIC AUDIT TRIGGERS
-- ===================================================================

-- Events table audit triggers
DELIMITER //

-- Events INSERT trigger
CREATE OR REPLACE TRIGGER events_audit_insert
AFTER INSERT ON events
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'events',
        NEW.id,
        'INSERT',
        NEW.created_by,
        NULL,
        JSON_OBJECT(
            'title', NEW.title,
            'description', NEW.description,
            'start_datetime', NEW.start_datetime,
            'end_datetime', NEW.end_datetime,
            'source_id', NEW.source_id,
            'status', NEW.status,
            'city', NEW.city,
            'state', NEW.state
        ),
        NULL, NULL, NULL,
        JSON_OBJECT('scraped_at', NEW.scraped_at)
    );
END //

-- Events UPDATE trigger
CREATE OR REPLACE TRIGGER events_audit_update
AFTER UPDATE ON events
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'events',
        NEW.id,
        'UPDATE',
        NEW.updated_by,
        JSON_OBJECT(
            'title', OLD.title,
            'description', OLD.description,
            'start_datetime', OLD.start_datetime,
            'end_datetime', OLD.end_datetime,
            'status', OLD.status,
            'city', OLD.city,
            'state', OLD.state
        ),
        JSON_OBJECT(
            'title', NEW.title,
            'description', NEW.description,
            'start_datetime', NEW.start_datetime,
            'end_datetime', NEW.end_datetime,
            'status', NEW.status,
            'city', NEW.city,
            'state', NEW.state
        ),
        NULL, NULL, NULL,
        JSON_OBJECT('status_change', OLD.status != NEW.status)
    );
END //

-- Events DELETE trigger
CREATE OR REPLACE TRIGGER events_audit_delete
BEFORE DELETE ON events
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'events',
        OLD.id,
        'DELETE',
        NULL, -- User context would need to be passed separately
        JSON_OBJECT(
            'title', OLD.title,
            'description', OLD.description,
            'start_datetime', OLD.start_datetime,
            'end_datetime', OLD.end_datetime,
            'status', OLD.status,
            'source_id', OLD.source_id
        ),
        NULL,
        NULL, NULL, NULL,
        JSON_OBJECT('deleted_at', NOW())
    );
END //

DELIMITER ;

-- Local Shops audit triggers
DELIMITER //

CREATE OR REPLACE TRIGGER local_shops_audit_insert
AFTER INSERT ON local_shops
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'local_shops',
        NEW.id,
        'INSERT',
        NULL,
        NULL,
        JSON_OBJECT(
            'name', NEW.name,
            'description', NEW.description,
            'address', NEW.address,
            'city', NEW.city,
            'state', NEW.state,
            'verified', NEW.verified,
            'category', NEW.category,
            'is_active', NEW.is_active
        ),
        NULL, NULL, NULL,
        JSON_OBJECT('initial_verification_status', NEW.verified)
    );
END //

CREATE OR REPLACE TRIGGER local_shops_audit_update
AFTER UPDATE ON local_shops
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'local_shops',
        NEW.id,
        'UPDATE',
        NULL,
        JSON_OBJECT(
            'name', OLD.name,
            'verified', OLD.verified,
            'is_active', OLD.is_active,
            'category', OLD.category
        ),
        JSON_OBJECT(
            'name', NEW.name,
            'verified', NEW.verified,
            'is_active', NEW.is_active,
            'category', NEW.category
        ),
        NULL, NULL, NULL,
        JSON_OBJECT(
            'verification_changed', OLD.verified != NEW.verified,
            'status_changed', OLD.is_active != NEW.is_active
        )
    );
END //

CREATE OR REPLACE TRIGGER local_shops_audit_delete
BEFORE DELETE ON local_shops
FOR EACH ROW
BEGIN
    CALL LogAuditEvent(
        'local_shops',
        OLD.id,
        'DELETE',
        NULL,
        JSON_OBJECT(
            'name', OLD.name,
            'verified', OLD.verified,
            'category', OLD.category,
            'is_active', OLD.is_active
        ),
        NULL,
        NULL, NULL, NULL,
        JSON_OBJECT('deletion_reason', 'manual_delete')
    );
END //

DELIMITER ;

-- ===================================================================
-- YF CLAIM MODULE AUDIT TRIGGERS
-- ===================================================================

-- YFC Sales audit triggers (if table exists)
DELIMITER //

-- Check if yfc_sales table exists and create trigger
CREATE OR REPLACE PROCEDURE CreateYFCSalesAuditTriggers()
BEGIN
    DECLARE table_exists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO table_exists 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'yfc_sales';
    
    IF table_exists > 0 THEN
        -- YFC Sales INSERT trigger
        SET @sql = 'CREATE OR REPLACE TRIGGER yfc_sales_audit_insert
        AFTER INSERT ON yfc_sales
        FOR EACH ROW
        BEGIN
            CALL LogAuditEvent(
                "yfc_sales",
                NEW.id,
                "INSERT",
                NEW.seller_id,
                NULL,
                JSON_OBJECT(
                    "title", NEW.title,
                    "claim_start", NEW.claim_start,
                    "claim_end", NEW.claim_end,
                    "status", NEW.status,
                    "total_items", NEW.total_items
                ),
                NULL, NULL, NULL,
                JSON_OBJECT("sale_type", "estate_sale")
            );
        END';
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- YFC Sales UPDATE trigger
        SET @sql = 'CREATE OR REPLACE TRIGGER yfc_sales_audit_update
        AFTER UPDATE ON yfc_sales
        FOR EACH ROW
        BEGIN
            CALL LogAuditEvent(
                "yfc_sales",
                NEW.id,
                "UPDATE",
                NEW.seller_id,
                JSON_OBJECT(
                    "title", OLD.title,
                    "status", OLD.status,
                    "total_items", OLD.total_items
                ),
                JSON_OBJECT(
                    "title", NEW.title,
                    "status", NEW.status,
                    "total_items", NEW.total_items
                ),
                NULL, NULL, NULL,
                JSON_OBJECT("status_changed", OLD.status != NEW.status)
            );
        END';
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END //

-- Call the procedure to create YFC triggers if table exists
CALL CreateYFCSalesAuditTriggers() //

DELIMITER ;

-- ===================================================================
-- AUDIT ANALYSIS AND REPORTING VIEWS
-- ===================================================================

-- High-risk activity monitoring view
CREATE OR REPLACE VIEW high_risk_audit_activity AS
SELECT 
    table_name,
    operation,
    COUNT(*) as occurrence_count,
    COUNT(DISTINCT user_id) as unique_users,
    MIN(created_at) as first_occurrence,
    MAX(created_at) as last_occurrence,
    AVG(CASE WHEN requires_review THEN 1 ELSE 0 END) as review_rate
FROM comprehensive_audit_log
WHERE risk_level IN ('high', 'critical')
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY table_name, operation
ORDER BY occurrence_count DESC;

-- User activity summary view
CREATE OR REPLACE VIEW user_audit_summary AS
SELECT 
    u.id as user_id,
    u.username,
    u.email,
    COUNT(a.id) as total_actions,
    COUNT(CASE WHEN a.operation = 'INSERT' THEN 1 END) as creates,
    COUNT(CASE WHEN a.operation = 'UPDATE' THEN 1 END) as updates,
    COUNT(CASE WHEN a.operation = 'DELETE' THEN 1 END) as deletes,
    COUNT(CASE WHEN a.risk_level = 'high' THEN 1 END) as high_risk_actions,
    MAX(a.created_at) as last_activity,
    COUNT(DISTINCT a.ip_address) as unique_ip_addresses
FROM yfa_auth_users u
LEFT JOIN comprehensive_audit_log a ON u.id = a.user_id
WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) OR a.created_at IS NULL
GROUP BY u.id, u.username, u.email
ORDER BY total_actions DESC;

-- Suspicious activity detection view
CREATE OR REPLACE VIEW suspicious_activity_detection AS
SELECT 
    ip_address,
    COUNT(*) as total_actions,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(CASE WHEN operation = 'DELETE' THEN 1 END) as delete_actions,
    COUNT(CASE WHEN risk_level IN ('high', 'critical') THEN 1 END) as high_risk_actions,
    MIN(created_at) as first_seen,
    MAX(created_at) as last_seen,
    'Multiple users from single IP' as suspicious_pattern
FROM comprehensive_audit_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND ip_address IS NOT NULL
GROUP BY ip_address
HAVING unique_users > 3 OR delete_actions > 10 OR high_risk_actions > 5

UNION ALL

SELECT 
    user_identifier as ip_address,
    COUNT(*) as total_actions,
    COUNT(DISTINCT ip_address) as unique_users,
    COUNT(CASE WHEN operation = 'DELETE' THEN 1 END) as delete_actions,
    COUNT(CASE WHEN risk_level IN ('high', 'critical') THEN 1 END) as high_risk_actions,
    MIN(created_at) as first_seen,
    MAX(created_at) as last_seen,
    'Rapid actions from single user' as suspicious_pattern
FROM comprehensive_audit_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
  AND user_identifier IS NOT NULL
GROUP BY user_identifier
HAVING total_actions > 100;

-- Data integrity monitoring view
CREATE OR REPLACE VIEW data_integrity_audit AS
SELECT 
    table_name,
    DATE(created_at) as audit_date,
    COUNT(CASE WHEN operation = 'INSERT' THEN 1 END) as inserts,
    COUNT(CASE WHEN operation = 'UPDATE' THEN 1 END) as updates,
    COUNT(CASE WHEN operation = 'DELETE' THEN 1 END) as deletes,
    COUNT(*) as total_changes,
    COUNT(CASE WHEN requires_review = 1 THEN 1 END) as pending_reviews
FROM comprehensive_audit_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY table_name, DATE(created_at)
ORDER BY audit_date DESC, total_changes DESC;

-- ===================================================================
-- AUDIT CLEANUP AND MAINTENANCE
-- ===================================================================

-- Procedure for automatic audit log cleanup based on retention policies
DELIMITER //
CREATE OR REPLACE PROCEDURE CleanupAuditLogs()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_table_name VARCHAR(100);
    DECLARE v_operation_type VARCHAR(50);
    DECLARE v_retention_days INT;
    DECLARE v_rows_deleted INT DEFAULT 0;
    DECLARE v_total_deleted INT DEFAULT 0;
    
    -- Cursor for retention policies
    DECLARE retention_cursor CURSOR FOR
        SELECT table_name, operation_type, retention_days
        FROM audit_retention_policy
        WHERE auto_archive = TRUE;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN retention_cursor;
    
    cleanup_loop: LOOP
        FETCH retention_cursor INTO v_table_name, v_operation_type, v_retention_days;
        
        IF done THEN
            LEAVE cleanup_loop;
        END IF;
        
        -- Delete old audit records based on retention policy
        SET @sql = CONCAT(
            'DELETE FROM comprehensive_audit_log ',
            'WHERE table_name = "', v_table_name, '" ',
            'AND operation = "', v_operation_type, '" ',
            'AND created_at < DATE_SUB(NOW(), INTERVAL ', v_retention_days, ' DAY) ',
            'AND processed_at IS NOT NULL' -- Only delete processed/reviewed records
        );
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        GET DIAGNOSTICS v_rows_deleted = ROW_COUNT;
        DEALLOCATE PREPARE stmt;
        
        SET v_total_deleted = v_total_deleted + v_rows_deleted;
        
    END LOOP;
    
    CLOSE retention_cursor;
    
    -- Log cleanup completion
    INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
    VALUES ('audit_cleanup_completed', v_total_deleted, 'records_deleted');
    
END //
DELIMITER ;

-- ===================================================================
-- EMERGENCY AUDIT FEATURES
-- ===================================================================

-- Emergency audit stop procedure (in case of issues)
DELIMITER //
CREATE OR REPLACE PROCEDURE EmergencyDisableAuditTriggers()
BEGIN
    -- Disable all audit triggers
    DROP TRIGGER IF EXISTS events_audit_insert;
    DROP TRIGGER IF EXISTS events_audit_update;
    DROP TRIGGER IF EXISTS events_audit_delete;
    DROP TRIGGER IF EXISTS local_shops_audit_insert;
    DROP TRIGGER IF EXISTS local_shops_audit_update;
    DROP TRIGGER IF EXISTS local_shops_audit_delete;
    DROP TRIGGER IF EXISTS yfc_sales_audit_insert;
    DROP TRIGGER IF EXISTS yfc_sales_audit_update;
    
    -- Log emergency disable
    INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
    VALUES ('audit_triggers_emergency_disabled', 1, 'emergency_action');
    
    SELECT 'All audit triggers have been disabled for emergency maintenance' as status;
END //
DELIMITER ;

-- ===================================================================
-- COMPLIANCE AND EXPORT FEATURES
-- ===================================================================

-- Procedure to export audit data for compliance
DELIMITER //
CREATE OR REPLACE PROCEDURE ExportAuditDataForCompliance(
    IN p_start_date DATE,
    IN p_end_date DATE,
    IN p_table_name VARCHAR(100)
)
BEGIN
    -- Create a comprehensive audit export
    SELECT 
        id,
        table_name,
        record_id,
        operation,
        user_identifier,
        ip_address,
        old_values,
        new_values,
        risk_level,
        created_at,
        'EXPORTED_FOR_COMPLIANCE' as export_status
    FROM comprehensive_audit_log
    WHERE created_at BETWEEN p_start_date AND p_end_date
      AND (p_table_name IS NULL OR table_name = p_table_name)
    ORDER BY created_at ASC;
    
    -- Log export activity
    INSERT INTO comprehensive_audit_log (
        table_name, record_id, operation, 
        context_data, risk_level
    ) VALUES (
        'audit_export', 
        CONCAT(p_start_date, '_to_', p_end_date),
        'ACCESS',
        JSON_OBJECT(
            'export_start_date', p_start_date,
            'export_end_date', p_end_date,
            'table_filter', p_table_name
        ),
        'medium'
    );
END //
DELIMITER ;

-- ===================================================================
-- INITIALIZE AUDIT SYSTEM
-- ===================================================================

-- Test the audit system with a sample entry
INSERT INTO comprehensive_audit_log (
    table_name, record_id, operation, 
    context_data, risk_level
) VALUES (
    'audit_system', 
    'initialization',
    'INSERT',
    JSON_OBJECT('system_status', 'audit_logging_enabled'),
    'low'
);

-- Log successful audit system implementation
INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
VALUES ('comprehensive_audit_system_installed', 1, 'completed');

SELECT 'Comprehensive audit logging system installed successfully' as status;

-- Show summary of created components
SELECT 
    'Tables Created' as component_type,
    COUNT(*) as count
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
  AND table_name IN ('comprehensive_audit_log', 'audit_retention_policy')

UNION ALL

SELECT 
    'Triggers Created' as component_type,
    COUNT(*) as count
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
  AND trigger_name LIKE '%audit%'

UNION ALL

SELECT 
    'Procedures Created' as component_type,
    COUNT(*) as count
FROM information_schema.routines
WHERE routine_schema = DATABASE()
  AND routine_name IN ('LogAuditEvent', 'CleanupAuditLogs', 'EmergencyDisableAuditTriggers', 'ExportAuditDataForCompliance')

UNION ALL

SELECT 
    'Views Created' as component_type,
    COUNT(*) as count
FROM information_schema.views
WHERE table_schema = DATABASE()
  AND table_name IN ('high_risk_audit_activity', 'user_audit_summary', 'suspicious_activity_detection', 'data_integrity_audit');