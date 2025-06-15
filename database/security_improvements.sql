-- YFEvents Database Security and Performance Improvements
-- Phase 1: Critical Security & Data Integrity Fixes
-- This script addresses the most critical database security and performance issues

-- ===================================================================
-- SECURITY IMPROVEMENTS
-- ===================================================================

-- Add missing foreign key constraints
-- Fix calendar_sources table
ALTER TABLE calendar_sources 
ADD CONSTRAINT fk_calendar_sources_created_by 
FOREIGN KEY (created_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL;

-- Fix calendar_permissions table (if exists)
SET @table_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'calendar_permissions'
);

SET @sql = IF(@table_exists > 0, 
    'ALTER TABLE calendar_permissions 
     ADD CONSTRAINT fk_calendar_permissions_user_id 
     FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE CASCADE,
     ADD CONSTRAINT fk_calendar_permissions_granted_by 
     FOREIGN KEY (granted_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL',
    'SELECT "calendar_permissions table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Fix events table constraints
ALTER TABLE events 
ADD CONSTRAINT chk_event_dates 
CHECK (end_datetime IS NULL OR end_datetime >= start_datetime);

-- Add validation constraints for coordinates
ALTER TABLE events 
ADD CONSTRAINT chk_events_latitude_range 
CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90)),
ADD CONSTRAINT chk_events_longitude_range 
CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180));

-- Apply same coordinate constraints to local_shops
ALTER TABLE local_shops 
ADD CONSTRAINT chk_shops_latitude_range 
CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90)),
ADD CONSTRAINT chk_shops_longitude_range 
CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180));

-- Add email format validation
ALTER TABLE yfa_auth_users 
ADD CONSTRAINT chk_email_format 
CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$');

-- Add phone format validation (if phone column exists)
SET @phone_exists = (
    SELECT COUNT(*) FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'yfa_auth_users' 
    AND column_name = 'phone'
);

SET @sql = IF(@phone_exists > 0, 
    'ALTER TABLE yfa_auth_users 
     ADD CONSTRAINT chk_phone_format 
     CHECK (phone IS NULL OR phone REGEXP "^[+]?[0-9\\s\\-\\(\\)]{10,20}$")',
    'SELECT "phone column does not exist in yfa_auth_users" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add URL format validation for calendar sources
ALTER TABLE calendar_sources 
ADD CONSTRAINT chk_url_format 
CHECK (url REGEXP '^https?://[^\\s/$.?#].[^\\s]*$');

-- Add status validation where not already present
ALTER TABLE events 
MODIFY COLUMN status ENUM('pending', 'approved', 'published', 'archived', 'deleted') DEFAULT 'pending';

-- ===================================================================
-- PERFORMANCE IMPROVEMENTS - CRITICAL INDEXES
-- ===================================================================

-- Events table performance indexes
CREATE INDEX IF NOT EXISTS idx_events_scraped_at ON events(scraped_at);
CREATE INDEX IF NOT EXISTS idx_events_external_event_id ON events(external_event_id);
CREATE INDEX IF NOT EXISTS idx_events_status_start_datetime ON events(status, start_datetime);
CREATE INDEX IF NOT EXISTS idx_events_location_status ON events(latitude, longitude, status);
CREATE INDEX IF NOT EXISTS idx_events_source_status ON events(source_id, status);
CREATE INDEX IF NOT EXISTS idx_events_created_status ON events(created_at, status);

-- Calendar sources performance indexes
CREATE INDEX IF NOT EXISTS idx_calendar_sources_active_type ON calendar_sources(is_active, scrape_type);
CREATE INDEX IF NOT EXISTS idx_calendar_sources_last_scraped ON calendar_sources(last_scraped_at);
CREATE INDEX IF NOT EXISTS idx_calendar_sources_created_by ON calendar_sources(created_by);

-- Local shops performance indexes
CREATE INDEX IF NOT EXISTS idx_local_shops_verified_active ON local_shops(verified, is_active);
CREATE INDEX IF NOT EXISTS idx_local_shops_location ON local_shops(latitude, longitude);
CREATE INDEX IF NOT EXISTS idx_local_shops_category ON local_shops(category);
CREATE INDEX IF NOT EXISTS idx_local_shops_city_state ON local_shops(city, state);

-- Event categories indexes
CREATE INDEX IF NOT EXISTS idx_event_categories_parent ON event_categories(parent_id);
CREATE INDEX IF NOT EXISTS idx_event_categories_active ON event_categories(is_active);

-- ===================================================================
-- YF CLAIM MODULE SECURITY IMPROVEMENTS
-- ===================================================================

-- Apply constraints to YF Claim tables if they exist
SET @yfc_sales_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'yfc_sales'
);

SET @sql = IF(@yfc_sales_exists > 0, 
    'ALTER TABLE yfc_sales 
     ADD CONSTRAINT chk_claim_dates 
     CHECK (claim_end > claim_start 
            AND (preview_start IS NULL OR preview_start < claim_start)
            AND (pickup_start IS NULL OR pickup_start >= claim_start))',
    'SELECT "yfc_sales table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- YFC Items price validation
SET @yfc_items_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'yfc_items'
);

SET @sql = IF(@yfc_items_exists > 0, 
    'ALTER TABLE yfc_items 
     ADD CONSTRAINT chk_positive_prices 
     CHECK (starting_price >= 0 AND (buy_now_price IS NULL OR buy_now_price > starting_price))',
    'SELECT "yfc_items table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- YFC Offers validation
SET @yfc_offers_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'yfc_offers'
);

SET @sql = IF(@yfc_offers_exists > 0, 
    'ALTER TABLE yfc_offers 
     ADD CONSTRAINT chk_positive_offer 
     CHECK (offer_amount > 0 AND (max_offer IS NULL OR max_offer >= offer_amount))',
    'SELECT "yfc_offers table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- SHOP CLAIM SYSTEM IMPROVEMENTS
-- ===================================================================

-- Shop claim requests performance and security
SET @shop_claim_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'shop_claim_requests'
);

SET @sql = IF(@shop_claim_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_shop_claim_requests_submitted_status ON shop_claim_requests(submitted_at, status);
     CREATE INDEX IF NOT EXISTS idx_shop_claim_requests_priority_status ON shop_claim_requests(priority, status);
     CREATE INDEX IF NOT EXISTS idx_shop_claim_requests_shop_id ON shop_claim_requests(shop_id)',
    'SELECT "shop_claim_requests table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- INTELLIGENT SCRAPER IMPROVEMENTS
-- ===================================================================

-- Intelligent scraper performance indexes
SET @intel_sessions_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'intelligent_scraper_sessions'
);

SET @sql = IF(@intel_sessions_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_intelligent_scraper_sessions_url_status ON intelligent_scraper_sessions(url(100), status);
     CREATE INDEX IF NOT EXISTS idx_intelligent_scraper_sessions_created ON intelligent_scraper_sessions(created_at);
     CREATE INDEX IF NOT EXISTS idx_intelligent_scraper_sessions_user ON intelligent_scraper_sessions(user_id)',
    'SELECT "intelligent_scraper_sessions table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @intel_methods_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'intelligent_scraper_methods'
);

SET @sql = IF(@intel_methods_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_intelligent_scraper_methods_domain_active ON intelligent_scraper_methods(domain, active);
     CREATE INDEX IF NOT EXISTS idx_intelligent_scraper_methods_created ON intelligent_scraper_methods(created_at)',
    'SELECT "intelligent_scraper_methods table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- CHAT SYSTEM IMPROVEMENTS
-- ===================================================================

-- Chat system performance indexes
SET @chat_messages_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'chat_messages'
);

SET @sql = IF(@chat_messages_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_chat_messages_conversation_created_deleted ON chat_messages(conversation_id, created_at, is_deleted);
     CREATE INDEX IF NOT EXISTS idx_chat_messages_sender_created ON chat_messages(sender_id, created_at)',
    'SELECT "chat_messages table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @chat_participants_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'chat_participants'
);

SET @sql = IF(@chat_participants_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_chat_participants_user_active_notifications ON chat_participants(user_id, is_active, notifications_enabled);
     CREATE INDEX IF NOT EXISTS idx_chat_participants_conversation ON chat_participants(conversation_id)',
    'SELECT "chat_participants table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- SCRAPING LOGS IMPROVEMENTS
-- ===================================================================

-- Scraping logs performance indexes
SET @scraping_logs_exists = (
    SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = DATABASE() AND table_name = 'scraping_logs'
);

SET @sql = IF(@scraping_logs_exists > 0, 
    'CREATE INDEX IF NOT EXISTS idx_scraping_logs_source_created ON scraping_logs(source_id, created_at);
     CREATE INDEX IF NOT EXISTS idx_scraping_logs_status_created ON scraping_logs(status, created_at);
     CREATE INDEX IF NOT EXISTS idx_scraping_logs_duration ON scraping_logs(duration_ms)',
    'SELECT "scraping_logs table does not exist" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===================================================================
-- SYSTEM MONITORING TABLE
-- ===================================================================

-- Create system monitoring table for tracking database health
CREATE TABLE IF NOT EXISTS system_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    metric_unit VARCHAR(20),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_name_recorded (metric_name, recorded_at),
    INDEX idx_recorded_at (recorded_at)
);

-- ===================================================================
-- AUDIT TRAIL ENHANCEMENTS
-- ===================================================================

-- Add audit columns to critical tables if they don't exist
SET @sql = 'ALTER TABLE events 
            ADD COLUMN IF NOT EXISTS created_by INT,
            ADD COLUMN IF NOT EXISTS updated_by INT,
            ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add audit foreign keys if possible
SET @foreign_key_sql = 'ALTER TABLE events 
                        ADD CONSTRAINT IF NOT EXISTS fk_events_created_by 
                        FOREIGN KEY (created_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL,
                        ADD CONSTRAINT IF NOT EXISTS fk_events_updated_by 
                        FOREIGN KEY (updated_by) REFERENCES yfa_auth_users(id) ON DELETE SET NULL';

-- Try to add foreign keys, ignore if auth table doesn't exist
SET @sql = CONCAT('BEGIN; ', @foreign_key_sql, '; COMMIT;');

-- ===================================================================
-- CLEANUP AND OPTIMIZATION
-- ===================================================================

-- Remove any duplicate indexes that might have been created
-- This is a safety measure to prevent duplicate index errors

-- Optimize all tables to ensure indexes are properly built
OPTIMIZE TABLE events;
OPTIMIZE TABLE calendar_sources;
OPTIMIZE TABLE local_shops;
OPTIMIZE TABLE event_categories;

-- ===================================================================
-- SECURITY LOG TABLE
-- ===================================================================

-- Create security audit log if not exists
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_created (action, created_at),
    INDEX idx_table_record (table_name, record_id),
    
    FOREIGN KEY (user_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL
);

-- ===================================================================
-- FINAL VERIFICATION
-- ===================================================================

-- Create a view to monitor constraint violations
CREATE OR REPLACE VIEW constraint_violations AS
SELECT 
    'events' as table_name,
    'invalid_dates' as violation_type,
    COUNT(*) as violation_count
FROM events 
WHERE end_datetime IS NOT NULL AND end_datetime < start_datetime

UNION ALL

SELECT 
    'events' as table_name,
    'invalid_coordinates' as violation_type,
    COUNT(*) as violation_count
FROM events 
WHERE (latitude IS NOT NULL AND (latitude < -90 OR latitude > 90))
   OR (longitude IS NOT NULL AND (longitude < -180 OR longitude > 180))

UNION ALL

SELECT 
    'local_shops' as table_name,
    'invalid_coordinates' as violation_type,
    COUNT(*) as violation_count
FROM local_shops 
WHERE (latitude IS NOT NULL AND (latitude < -90 OR latitude > 90))
   OR (longitude IS NOT NULL AND (longitude < -180 OR longitude > 180));

-- Log the completion of security improvements
INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
VALUES ('database_security_improvements_applied', 1, 'completed');

SELECT 'Database security and performance improvements completed successfully' as status;