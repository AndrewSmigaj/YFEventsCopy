-- Yakima Finds Event Calendar Database Schema
-- Integrates with existing CMS database

-- Calendar sources for scraping (no dependencies)
CREATE TABLE calendar_sources (
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

-- Shop owners for business management (no dependencies)
CREATE TABLE shop_owners (
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

-- Shop categories for organization (self-reference ok)
CREATE TABLE shop_categories (
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

-- Event categories for filtering (no dependencies)
CREATE TABLE event_categories (
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

-- Calendar permissions for CMS integration (no dependencies)
CREATE TABLE calendar_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission ENUM('view_pending', 'approve_events', 'manage_sources', 'manage_shops', 'admin') NOT NULL,
    granted_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_permission (permission)
);

-- Events table - main event storage (depends on calendar_sources)
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

-- Local shops/businesses directory (depends on shop_categories, shop_owners)
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

-- Event-category relationships (many-to-many) (depends on events, event_categories)
CREATE TABLE event_category_relations (
    event_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (event_id, category_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES event_categories(id) ON DELETE CASCADE
);

-- Shop images for gallery (depends on local_shops)
CREATE TABLE shop_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shop_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shop_id) REFERENCES local_shops(id) ON DELETE CASCADE,
    INDEX idx_shop (shop_id),
    INDEX idx_primary (is_primary)
);

-- Event images (depends on events)
CREATE TABLE event_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id),
    INDEX idx_primary (is_primary)
);

-- Scraping logs for monitoring (depends on calendar_sources)
CREATE TABLE scraping_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('running', 'success', 'error') DEFAULT 'running',
    events_found INT DEFAULT 0,
    events_added INT DEFAULT 0,
    error_message TEXT,
    FOREIGN KEY (source_id) REFERENCES calendar_sources(id) ON DELETE CASCADE,
    INDEX idx_source (source_id),
    INDEX idx_status (status),
    INDEX idx_started (started_at)
);

-- Insert default categories
INSERT INTO event_categories (name, slug, color, icon) VALUES
('Community Events', 'community', '#e74c3c', 'fas fa-users'),
('Arts & Culture', 'arts-culture', '#9b59b6', 'fas fa-palette'),
('Music & Entertainment', 'music-entertainment', '#f39c12', 'fas fa-music'),
('Food & Dining', 'food-dining', '#27ae60', 'fas fa-utensils'),
('Sports & Recreation', 'sports-recreation', '#3498db', 'fas fa-football-ball'),
('Business & Networking', 'business', '#34495e', 'fas fa-briefcase'),
('Education & Learning', 'education', '#16a085', 'fas fa-graduation-cap'),
('Health & Wellness', 'health-wellness', '#e67e22', 'fas fa-heart');

-- Insert default shop categories
INSERT INTO shop_categories (name, slug, icon, sort_order) VALUES
('Antiques & Collectibles', 'antiques', 'fas fa-chess-rook', 1),
('Restaurants & Dining', 'restaurants', 'fas fa-utensils', 2),
('Retail & Shopping', 'retail', 'fas fa-shopping-bag', 3),
('Services', 'services', 'fas fa-tools', 4),
('Entertainment', 'entertainment', 'fas fa-film', 5),
('Health & Beauty', 'health-beauty', 'fas fa-spa', 6),
('Automotive', 'automotive', 'fas fa-car', 7),
('Professional Services', 'professional', 'fas fa-briefcase', 8);

-- Insert sub-categories for antiques
INSERT INTO shop_categories (name, slug, parent_id, icon, sort_order) VALUES
('Vintage Furniture', 'vintage-furniture', 1, 'fas fa-couch', 1),
('Vintage Clothing', 'vintage-clothing', 1, 'fas fa-tshirt', 2),
('Collectibles', 'collectibles', 1, 'fas fa-gem', 3),
('Books & Records', 'books-records', 1, 'fas fa-book', 4);