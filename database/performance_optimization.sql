-- YFEvents Database Performance Optimization
-- Phase 2: Advanced Performance Improvements
-- This script implements advanced caching, query optimization, and monitoring

-- ===================================================================
-- QUERY OPTIMIZATION VIEWS
-- ===================================================================

-- Optimized view for active events with location data
CREATE OR REPLACE VIEW active_events_with_location AS
SELECT 
    e.id,
    e.title,
    e.description,
    e.start_datetime,
    e.end_datetime,
    e.latitude,
    e.longitude,
    e.address,
    e.city,
    e.state,
    cs.name as source_name,
    cs.scrape_type,
    -- Calculated fields for better performance
    CASE 
        WHEN e.end_datetime IS NULL THEN e.start_datetime
        ELSE e.end_datetime
    END as event_end,
    DATEDIFF(e.start_datetime, NOW()) as days_until_event,
    CASE 
        WHEN e.latitude IS NOT NULL AND e.longitude IS NOT NULL THEN 1
        ELSE 0
    END as has_location
FROM events e
JOIN calendar_sources cs ON e.source_id = cs.id
WHERE e.status = 'published' 
  AND e.start_datetime >= NOW()
  AND cs.is_active = 1;

-- Optimized view for shop listings with categorization
CREATE OR REPLACE VIEW active_shops_with_details AS
SELECT 
    s.id,
    s.name,
    s.description,
    s.address,
    s.city,
    s.state,
    s.latitude,
    s.longitude,
    s.phone,
    s.email,
    s.website,
    s.category,
    s.verified,
    s.rating,
    s.hours,
    -- Calculated fields
    CASE 
        WHEN s.latitude IS NOT NULL AND s.longitude IS NOT NULL THEN 1
        ELSE 0
    END as has_location,
    CASE 
        WHEN s.verified = 1 THEN 'Verified Business'
        ELSE 'Unverified'
    END as verification_status
FROM local_shops s
WHERE s.is_active = 1;

-- ===================================================================
-- MATERIALIZED VIEW SIMULATION WITH TRIGGERS
-- ===================================================================

-- Create table for event statistics cache
CREATE TABLE IF NOT EXISTS event_statistics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(100) UNIQUE NOT NULL,
    total_events INT DEFAULT 0,
    published_events INT DEFAULT 0,
    pending_events INT DEFAULT 0,
    events_this_week INT DEFAULT 0,
    events_this_month INT DEFAULT 0,
    events_with_location INT DEFAULT 0,
    average_events_per_source DECIMAL(10,2) DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_cache_key (cache_key),
    INDEX idx_last_updated (last_updated)
);

-- Insert initial cache entry
INSERT INTO event_statistics_cache (cache_key) VALUES ('global_stats')
ON DUPLICATE KEY UPDATE cache_key = cache_key;

-- Create stored procedure to refresh event statistics
DELIMITER //
CREATE OR REPLACE PROCEDURE RefreshEventStatistics()
BEGIN
    DECLARE total_count, published_count, pending_count, week_count, month_count, location_count INT DEFAULT 0;
    DECLARE avg_per_source DECIMAL(10,2) DEFAULT 0;
    
    -- Calculate statistics
    SELECT COUNT(*) INTO total_count FROM events;
    
    SELECT COUNT(*) INTO published_count 
    FROM events WHERE status = 'published';
    
    SELECT COUNT(*) INTO pending_count 
    FROM events WHERE status = 'pending';
    
    SELECT COUNT(*) INTO week_count 
    FROM events 
    WHERE status = 'published' 
      AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY);
    
    SELECT COUNT(*) INTO month_count 
    FROM events 
    WHERE status = 'published' 
      AND start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY);
    
    SELECT COUNT(*) INTO location_count 
    FROM events 
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL;
    
    SELECT AVG(event_count) INTO avg_per_source
    FROM (
        SELECT COUNT(*) as event_count 
        FROM events 
        GROUP BY source_id
    ) as source_counts;
    
    -- Update cache
    UPDATE event_statistics_cache 
    SET 
        total_events = total_count,
        published_events = published_count,
        pending_events = pending_count,
        events_this_week = week_count,
        events_this_month = month_count,
        events_with_location = location_count,
        average_events_per_source = COALESCE(avg_per_source, 0),
        last_updated = NOW()
    WHERE cache_key = 'global_stats';
END //
DELIMITER ;

-- ===================================================================
-- PERFORMANCE MONITORING TABLES
-- ===================================================================

-- Query performance monitoring
CREATE TABLE IF NOT EXISTS query_performance_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    query_type VARCHAR(100) NOT NULL,
    query_hash VARCHAR(64) NOT NULL, -- MD5 hash of normalized query
    execution_time_ms INT NOT NULL,
    rows_examined INT DEFAULT 0,
    rows_sent INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_query_type_time (query_type, execution_time_ms),
    INDEX idx_created_at (created_at),
    INDEX idx_query_hash (query_hash)
);

-- Slow query analysis view
CREATE OR REPLACE VIEW slow_queries_analysis AS
SELECT 
    query_type,
    COUNT(*) as frequency,
    AVG(execution_time_ms) as avg_time_ms,
    MAX(execution_time_ms) as max_time_ms,
    MIN(execution_time_ms) as min_time_ms,
    AVG(rows_examined) as avg_rows_examined,
    DATE(created_at) as query_date
FROM query_performance_log
WHERE execution_time_ms > 1000 -- Queries taking more than 1 second
GROUP BY query_type, DATE(created_at)
ORDER BY avg_time_ms DESC;

-- ===================================================================
-- CACHING OPTIMIZATION TABLES
-- ===================================================================

-- Application cache management
CREATE TABLE IF NOT EXISTS application_cache (
    cache_key VARCHAR(255) PRIMARY KEY,
    cache_value LONGTEXT NOT NULL,
    cache_tags JSON, -- For tag-based invalidation
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    hit_count INT DEFAULT 0,
    
    INDEX idx_expires (expires_at),
    INDEX idx_created (created_at),
    INDEX idx_tags (cache_tags(255))
);

-- Cache statistics for monitoring
CREATE TABLE IF NOT EXISTS cache_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_namespace VARCHAR(100) NOT NULL,
    total_keys INT DEFAULT 0,
    hit_count INT DEFAULT 0,
    miss_count INT DEFAULT 0,
    eviction_count INT DEFAULT 0,
    memory_usage_bytes BIGINT DEFAULT 0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_namespace_recorded (cache_namespace, recorded_at)
);

-- ===================================================================
-- DATABASE PARTITIONING PREPARATION
-- ===================================================================

-- Prepare events table for partitioning by date
-- Note: This creates a new partitioned table structure

CREATE TABLE IF NOT EXISTS events_partitioned (
    id INT AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_datetime TIMESTAMP NOT NULL,
    end_datetime TIMESTAMP NULL,
    source_id INT NOT NULL,
    external_event_id VARCHAR(255),
    url VARCHAR(500),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(50),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status ENUM('pending', 'approved', 'published', 'archived', 'deleted') DEFAULT 'pending',
    scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    updated_by INT,
    
    PRIMARY KEY (id, start_datetime),
    INDEX idx_source_status (source_id, status),
    INDEX idx_status_start (status, start_datetime),
    INDEX idx_location (latitude, longitude),
    INDEX idx_city_state (city, state),
    INDEX idx_external_id (external_event_id)
) PARTITION BY RANGE (YEAR(start_datetime)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p2026 VALUES LESS THAN (2027),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- ===================================================================
-- SEARCH OPTIMIZATION
-- ===================================================================

-- Full-text search indexes
ALTER TABLE events ADD FULLTEXT(title, description);
ALTER TABLE local_shops ADD FULLTEXT(name, description);

-- Search performance optimization view
CREATE OR REPLACE VIEW search_optimized_events AS
SELECT 
    id,
    title,
    description,
    start_datetime,
    city,
    state,
    status,
    -- Pre-calculated search weights
    CASE 
        WHEN title LIKE '%festival%' OR title LIKE '%fair%' THEN 10
        WHEN title LIKE '%concert%' OR title LIKE '%music%' THEN 8
        WHEN title LIKE '%market%' OR title LIKE '%sale%' THEN 6
        ELSE 1
    END as search_weight,
    -- Location search helper
    CONCAT(COALESCE(city, ''), ' ', COALESCE(state, '')) as location_text
FROM events
WHERE status = 'published' AND start_datetime >= NOW();

-- ===================================================================
-- REPORTING AND ANALYTICS OPTIMIZATION
-- ===================================================================

-- Pre-aggregated reporting tables
CREATE TABLE IF NOT EXISTS daily_event_metrics (
    metric_date DATE PRIMARY KEY,
    total_events INT DEFAULT 0,
    new_events INT DEFAULT 0,
    published_events INT DEFAULT 0,
    events_with_location INT DEFAULT 0,
    unique_sources INT DEFAULT 0,
    unique_cities INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_date (metric_date)
);

CREATE TABLE IF NOT EXISTS source_performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    metric_date DATE NOT NULL,
    events_scraped INT DEFAULT 0,
    events_published INT DEFAULT 0,
    scrape_duration_ms INT DEFAULT 0,
    scrape_success BOOLEAN DEFAULT TRUE,
    error_count INT DEFAULT 0,
    
    UNIQUE KEY unique_source_date (source_id, metric_date),
    INDEX idx_metric_date (metric_date),
    INDEX idx_source_id (source_id),
    FOREIGN KEY (source_id) REFERENCES calendar_sources(id) ON DELETE CASCADE
);

-- ===================================================================
-- AUTOMATED MAINTENANCE PROCEDURES
-- ===================================================================

-- Procedure to clean old logs and optimize tables
DELIMITER //
CREATE OR REPLACE PROCEDURE DatabaseMaintenance()
BEGIN
    -- Clean old query performance logs (keep 30 days)
    DELETE FROM query_performance_log 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Clean expired cache entries
    DELETE FROM application_cache 
    WHERE expires_at < NOW();
    
    -- Clean old login logs (keep 90 days)
    DELETE FROM auth_login_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Clean old security events (keep resolved events for 180 days)
    DELETE FROM auth_security_events 
    WHERE resolved = 1 AND resolved_at < DATE_SUB(NOW(), INTERVAL 180 DAY);
    
    -- Update statistics cache
    CALL RefreshEventStatistics();
    
    -- Log maintenance completion
    INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
    VALUES ('database_maintenance_completed', 1, 'success');
    
END //
DELIMITER ;

-- ===================================================================
-- CONNECTION POOLING OPTIMIZATION
-- ===================================================================

-- Connection monitoring table
CREATE TABLE IF NOT EXISTS connection_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    connection_id BIGINT NOT NULL,
    user_name VARCHAR(100),
    host_name VARCHAR(255),
    database_name VARCHAR(100),
    command VARCHAR(100),
    query_time INT,
    state VARCHAR(100),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_connection_id (connection_id)
);

-- ===================================================================
-- BACKUP AND RECOVERY OPTIMIZATION
-- ===================================================================

-- Backup monitoring table
CREATE TABLE IF NOT EXISTS backup_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('full', 'incremental', 'differential') NOT NULL,
    backup_size_bytes BIGINT,
    duration_seconds INT,
    backup_location VARCHAR(500),
    backup_status ENUM('started', 'completed', 'failed') NOT NULL,
    error_message TEXT,
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP NULL,
    
    INDEX idx_backup_type_date (backup_type, started_at),
    INDEX idx_status (backup_status)
);

-- ===================================================================
-- PERFORMANCE TUNING RECOMMENDATIONS VIEW
-- ===================================================================

CREATE OR REPLACE VIEW performance_recommendations AS
SELECT 
    'Table' as component_type,
    table_name,
    CASE 
        WHEN avg_row_length > 1000 THEN 'Consider splitting large text fields into separate table'
        WHEN table_rows > 1000000 THEN 'Consider partitioning this large table'
        WHEN index_length = 0 THEN 'Add appropriate indexes to improve query performance'
        ELSE 'Performance looks good'
    END as recommendation,
    table_rows,
    avg_row_length,
    index_length
FROM information_schema.tables 
WHERE table_schema = DATABASE()
  AND table_type = 'BASE TABLE'

UNION ALL

SELECT 
    'Cache' as component_type,
    cache_namespace as table_name,
    CASE 
        WHEN hit_count / (hit_count + miss_count) < 0.8 THEN 'Cache hit ratio is low, consider cache optimization'
        WHEN eviction_count > hit_count * 0.1 THEN 'High eviction rate, consider increasing cache size'
        ELSE 'Cache performance is good'
    END as recommendation,
    hit_count,
    miss_count,
    eviction_count
FROM cache_statistics
WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 1 DAY);

-- ===================================================================
-- INITIALIZE PERFORMANCE MONITORING
-- ===================================================================

-- Create initial cache statistics entry
INSERT INTO cache_statistics (cache_namespace, recorded_at) 
VALUES ('global', NOW()) ON DUPLICATE KEY UPDATE cache_namespace = cache_namespace;

-- Create initial daily metrics entry
INSERT INTO daily_event_metrics (metric_date) 
VALUES (CURDATE()) ON DUPLICATE KEY UPDATE metric_date = metric_date;

-- Run initial statistics refresh
CALL RefreshEventStatistics();

-- Log completion
INSERT INTO system_monitoring (metric_name, metric_value, metric_unit) 
VALUES ('performance_optimization_applied', 1, 'completed');

SELECT 'Database performance optimization completed successfully' as status;