-- Add missing columns to events table
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS description TEXT,
ADD COLUMN IF NOT EXISTS start_datetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS end_datetime TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS location VARCHAR(255),
ADD COLUMN IF NOT EXISTS address TEXT,
ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL,
ADD COLUMN IF NOT EXISTS contact_info JSON,
ADD COLUMN IF NOT EXISTS external_url VARCHAR(500),
ADD COLUMN IF NOT EXISTS source_id INT NULL,
ADD COLUMN IF NOT EXISTS cms_user_id INT NULL,
ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
ADD COLUMN IF NOT EXISTS featured BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS scraped_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS external_event_id VARCHAR(255);

-- Add indexes
ALTER TABLE events
ADD INDEX IF NOT EXISTS idx_start_datetime (start_datetime),
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_location (latitude, longitude),
ADD INDEX IF NOT EXISTS idx_featured (featured);

-- Drop and recreate local_shops properly
DROP TABLE IF EXISTS local_shops;

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
    INDEX idx_status (status)
);

-- Add test data
INSERT INTO events (title, description, start_datetime, location, address, status) VALUES
('Community Farmers Market', 'Fresh local produce and crafts', NOW() + INTERVAL 3 DAY, 'Downtown Yakima', '3rd Ave & Yakima Ave, Yakima, WA', 'approved'),
('Estate Sale Preview', 'Preview the upcoming estate sale items', NOW() + INTERVAL 1 DAY, 'Heritage Hills', '1234 Heritage Dr, Yakima, WA', 'approved');

INSERT INTO local_shops (name, description, address, status, featured) VALUES
('Valley Coffee Roasters', 'Local artisan coffee shop', '123 Main St, Yakima, WA', 'active', TRUE),
('Yakima Vintage Market', 'Antiques and collectibles', '456 2nd Ave, Yakima, WA', 'active', FALSE);