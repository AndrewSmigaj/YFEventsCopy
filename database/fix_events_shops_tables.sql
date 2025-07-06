-- Fix Events and Shops Tables
-- This script drops the broken tables and recreates them with proper schema
-- Date: 2025-07-05

-- Start transaction for safety
START TRANSACTION;

-- Drop broken tables (they only have id column)
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS local_shops;

-- Create dependency tables first

-- 1. Shop categories (required by local_shops)
CREATE TABLE IF NOT EXISTS shop_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    parent_id INT NULL,
    icon VARCHAR(100),
    sort_order INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug),
    FOREIGN KEY (parent_id) REFERENCES shop_categories(id) ON DELETE SET NULL
);

-- 2. Shop owners (required by local_shops)  
CREATE TABLE IF NOT EXISTS shop_owners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(50),
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_token VARCHAR(255),
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_verification (verification_status)
);

-- 3. Calendar sources (required by events)
CREATE TABLE IF NOT EXISTS calendar_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    scrape_type ENUM('ical', 'html', 'json', 'eventbrite', 'facebook') NOT NULL,
    scrape_config JSON,
    last_scraped TIMESTAMP NULL,
    active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (active),
    INDEX idx_last_scraped (last_scraped)
);

-- 4. Event categories (for the event_category_relations table later)
CREATE TABLE IF NOT EXISTS event_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#3498db',
    icon VARCHAR(100),
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_active (active)
);

-- 5. Main events table with all required columns
CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_datetime TIMESTAMP NOT NULL,
    end_datetime TIMESTAMP NULL,
    location VARCHAR(255),
    address TEXT,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    contact_info JSON,
    external_url VARCHAR(500),
    source_id INT NULL,
    cms_user_id INT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    scraped_at TIMESTAMP NULL,
    external_event_id VARCHAR(255),
    INDEX idx_start_datetime (start_datetime),
    INDEX idx_status (status),
    INDEX idx_location (latitude, longitude),
    INDEX idx_featured (featured),
    FOREIGN KEY (source_id) REFERENCES calendar_sources(id) ON DELETE SET NULL
);

-- 6. Local shops table with all required columns
CREATE TABLE local_shops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    phone VARCHAR(50),
    email VARCHAR(255),
    website VARCHAR(500),
    category_id INT NULL,
    operating_hours JSON,
    payment_methods JSON,
    amenities JSON,
    featured BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    owner_id INT NULL,
    status ENUM('active', 'pending', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_location (latitude, longitude),
    INDEX idx_category (category_id),
    INDEX idx_featured (featured),
    INDEX idx_status (status),
    FOREIGN KEY (category_id) REFERENCES shop_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES shop_owners(id) ON DELETE SET NULL
);

-- Add some sample categories
INSERT INTO event_categories (name, slug, color, icon) VALUES
('Community', 'community', '#3498db', 'fa-users'),
('Arts & Culture', 'arts-culture', '#e74c3c', 'fa-palette'),
('Sports & Recreation', 'sports-recreation', '#2ecc71', 'fa-running'),
('Education', 'education', '#f39c12', 'fa-graduation-cap'),
('Business', 'business', '#9b59b6', 'fa-briefcase')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO shop_categories (name, slug, icon) VALUES
('Restaurants', 'restaurants', 'fa-utensils'),
('Retail', 'retail', 'fa-shopping-bag'),
('Services', 'services', 'fa-concierge-bell'),
('Health & Beauty', 'health-beauty', 'fa-heart'),
('Automotive', 'automotive', 'fa-car')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Commit transaction
COMMIT;

-- Show summary of created tables
SELECT 'Tables created successfully!' as Status;
SHOW TABLES LIKE 'events';
SHOW TABLES LIKE 'local_shops';
SHOW TABLES LIKE '%categories';
SHOW TABLES LIKE '%owners';
SHOW TABLES LIKE 'calendar_sources';