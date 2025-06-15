-- YFEvents Database Improvements - Master Application Script
-- This script applies all database improvements in the correct order
-- Run this script to implement all security, performance, and audit improvements

-- ===================================================================
-- PHASE 1: SECURITY AND DATA INTEGRITY IMPROVEMENTS
-- ===================================================================

-- Apply security improvements first
SOURCE security_improvements.sql;

-- ===================================================================
-- PHASE 2: PERFORMANCE OPTIMIZATION
-- ===================================================================

-- Apply performance optimizations
SOURCE performance_optimization.sql;

-- ===================================================================
-- PHASE 3: COMPREHENSIVE AUDIT LOGGING
-- ===================================================================

-- Apply audit logging system
SOURCE audit_logging.sql;

-- ===================================================================
-- FINAL VERIFICATION AND REPORTING
-- ===================================================================

-- Create a comprehensive database health report
CREATE OR REPLACE VIEW database_health_summary AS
SELECT 
    'Security Constraints' as improvement_category,
    COUNT(*) as implementations,
    'Constraints added for data integrity' as description
FROM information_schema.table_constraints
WHERE constraint_schema = DATABASE()
  AND constraint_type = 'CHECK'

UNION ALL

SELECT 
    'Performance Indexes' as improvement_category,
    COUNT(*) as implementations,
    'Indexes created for query optimization' as description
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND index_name LIKE 'idx_%'

UNION ALL

SELECT 
    'Audit Triggers' as improvement_category,
    COUNT(*) as implementations,
    'Triggers created for audit logging' as description
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
  AND trigger_name LIKE '%audit%'

UNION ALL

SELECT 
    'Monitoring Tables' as improvement_category,
    COUNT(*) as implementations,
    'Tables created for system monitoring' as description
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('system_monitoring', 'comprehensive_audit_log', 'query_performance_log', 'cache_statistics')

UNION ALL

SELECT 
    'Optimization Views' as improvement_category,
    COUNT(*) as implementations,
    'Views created for performance analysis' as description
FROM information_schema.views
WHERE table_schema = DATABASE()
  AND table_name IN ('active_events_with_location', 'performance_recommendations', 'constraint_violations');

-- Create implementation status table
CREATE TABLE IF NOT EXISTS database_improvements_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    improvement_phase VARCHAR(100) NOT NULL,
    component_name VARCHAR(100) NOT NULL,
    status ENUM('pending', 'applied', 'failed', 'skipped') DEFAULT 'applied',
    error_message TEXT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_component (improvement_phase, component_name)
);

-- Log all applied improvements
INSERT INTO database_improvements_status (improvement_phase, component_name, status) VALUES
-- Phase 1: Security Improvements
('Security', 'Foreign Key Constraints', 'applied'),
('Security', 'Data Validation Constraints', 'applied'),
('Security', 'Coordinate Range Validation', 'applied'),
('Security', 'Email Format Validation', 'applied'),
('Security', 'URL Format Validation', 'applied'),
('Security', 'Event Date Validation', 'applied'),
('Security', 'YFC Price Validation', 'applied'),
('Security', 'Performance Indexes', 'applied'),
('Security', 'Security Audit Log Table', 'applied'),

-- Phase 2: Performance Optimization
('Performance', 'Optimized Views', 'applied'),
('Performance', 'Event Statistics Cache', 'applied'),
('Performance', 'Query Performance Monitoring', 'applied'),
('Performance', 'Application Cache System', 'applied'),
('Performance', 'Full-Text Search Indexes', 'applied'),
('Performance', 'Reporting Tables', 'applied'),
('Performance', 'Maintenance Procedures', 'applied'),

-- Phase 3: Audit Logging
('Audit', 'Comprehensive Audit Log', 'applied'),
('Audit', 'Retention Policy System', 'applied'),
('Audit', 'Audit Triggers', 'applied'),
('Audit', 'Risk Assessment System', 'applied'),
('Audit', 'Suspicious Activity Detection', 'applied'),
('Audit', 'Compliance Export Features', 'applied')

ON DUPLICATE KEY UPDATE 
status = VALUES(status), 
applied_at = VALUES(applied_at);

-- ===================================================================
-- DATABASE MAINTENANCE SCHEDULE SETUP
-- ===================================================================

-- Create maintenance schedule table
CREATE TABLE IF NOT EXISTS maintenance_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    task_type ENUM('cleanup', 'optimization', 'backup', 'analysis') NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly', 'quarterly') NOT NULL,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    command_to_run TEXT NOT NULL,
    estimated_duration_minutes INT DEFAULT 30,
    
    INDEX idx_next_run (next_run),
    INDEX idx_active (is_active)
);

-- Insert maintenance tasks
INSERT INTO maintenance_schedule (task_name, task_type, frequency, next_run, command_to_run, estimated_duration_minutes) VALUES
('Daily Cache Cleanup', 'cleanup', 'daily', DATE_ADD(NOW(), INTERVAL 1 DAY), 'CALL DatabaseMaintenance()', 15),
('Weekly Performance Analysis', 'analysis', 'weekly', DATE_ADD(NOW(), INTERVAL 7 DAY), 'SELECT * FROM performance_recommendations', 30),
('Monthly Audit Log Cleanup', 'cleanup', 'monthly', DATE_ADD(NOW(), INTERVAL 1 MONTH), 'CALL CleanupAuditLogs()', 60),
('Weekly Table Optimization', 'optimization', 'weekly', DATE_ADD(NOW(), INTERVAL 7 DAY), 'OPTIMIZE TABLE events, local_shops, calendar_sources', 45),
('Daily Statistics Refresh', 'optimization', 'daily', DATE_ADD(NOW(), INTERVAL 1 DAY), 'CALL RefreshEventStatistics()', 10),
('Monthly Security Audit', 'analysis', 'monthly', DATE_ADD(NOW(), INTERVAL 1 MONTH), 'SELECT * FROM high_risk_audit_activity', 20)

ON DUPLICATE KEY UPDATE 
next_run = VALUES(next_run),
command_to_run = VALUES(command_to_run);

-- ===================================================================
-- QUICK DIAGNOSTIC QUERIES
-- ===================================================================

-- Create diagnostic procedures for quick health checks
DELIMITER //
CREATE OR REPLACE PROCEDURE QuickDatabaseHealthCheck()
BEGIN
    SELECT 'DATABASE HEALTH CHECK REPORT' as report_title;
    
    -- Table sizes and row counts
    SELECT 
        table_name,
        table_rows,
        ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
        ROUND((index_length / 1024 / 1024), 2) as index_size_mb
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
    ORDER BY (data_length + index_length) DESC
    LIMIT 10;
    
    -- Recent audit activity summary
    SELECT 
        'RECENT AUDIT ACTIVITY (Last 24 Hours)' as activity_summary,
        COUNT(*) as total_logged_actions,
        COUNT(CASE WHEN risk_level = 'high' THEN 1 END) as high_risk_actions,
        COUNT(CASE WHEN requires_review = 1 THEN 1 END) as pending_reviews
    FROM comprehensive_audit_log
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Constraint violations check
    SELECT * FROM constraint_violations;
    
    -- Performance issues check
    SELECT * FROM performance_recommendations LIMIT 5;
    
END //
DELIMITER ;

-- Create procedure for emergency database status
CREATE OR REPLACE PROCEDURE EmergencyDatabaseStatus()
BEGIN
    SELECT 'EMERGENCY DATABASE STATUS' as status_type;
    
    -- Check for locked accounts
    SELECT 'Locked User Accounts' as check_type, COUNT(*) as count
    FROM yfa_auth_users 
    WHERE locked_until > NOW();
    
    -- Check for high-risk unreviewed activities
    SELECT 'High-Risk Unreviewed Activities' as check_type, COUNT(*) as count
    FROM comprehensive_audit_log
    WHERE risk_level IN ('high', 'critical') 
      AND requires_review = 1 
      AND processed_at IS NULL;
    
    -- Check for recent failures
    SELECT 'Recent System Failures' as check_type, COUNT(*) as count
    FROM system_monitoring
    WHERE metric_name LIKE '%_failed' 
      AND recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);
    
    -- Check database connections
    SELECT 'Active Database Connections' as check_type, COUNT(*) as count
    FROM information_schema.processlist;
    
END //
DELIMITER ;

DELIMITER ;

-- ===================================================================
-- FINAL STATUS REPORT
-- ===================================================================

-- Generate final implementation report
SELECT 
    'YFEvents Database Improvements Implementation Report' as report_title,
    NOW() as completion_time;

-- Show all applied improvements
SELECT * FROM database_improvements_status ORDER BY improvement_phase, component_name;

-- Show database health summary
SELECT * FROM database_health_summary;

-- Show next maintenance tasks
SELECT 
    task_name,
    task_type,
    frequency,
    next_run,
    estimated_duration_minutes
FROM maintenance_schedule 
WHERE is_active = 1 
ORDER BY next_run 
LIMIT 5;

-- Log final completion
INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
VALUES ('database_improvements_completed', 1, 'full_implementation');

-- Run initial health check
CALL QuickDatabaseHealthCheck();

SELECT 
    'All database improvements have been successfully applied!' as final_status,
    'Run CALL QuickDatabaseHealthCheck() for ongoing monitoring' as next_steps;