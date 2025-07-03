-- YFClaim Module Database Schema
-- Facebook-style claim sale platform for estate sales

-- Sellers (Estate Sale Companies)
CREATE TABLE IF NOT EXISTS yfc_sellers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password_hash VARCHAR(255) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2) DEFAULT 'WA',
    zip VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- Claim Sales
CREATE TABLE IF NOT EXISTS yfc_sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    state VARCHAR(2) DEFAULT 'WA',
    zip VARCHAR(10),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    preview_start DATETIME,
    preview_end DATETIME,
    claim_start DATETIME NOT NULL,
    claim_end DATETIME NOT NULL,
    pickup_start DATETIME,
    pickup_end DATETIME,
    qr_code VARCHAR(100) UNIQUE,
    access_code VARCHAR(20) UNIQUE,
    status ENUM('draft', 'active', 'closed', 'cancelled') DEFAULT 'draft',
    show_price_ranges BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES yfc_sellers(id) ON DELETE CASCADE,
    INDEX idx_seller (seller_id),
    INDEX idx_status (status),
    INDEX idx_dates (claim_start, claim_end),
    INDEX idx_location (latitude, longitude)
);

-- Claim Items
CREATE TABLE IF NOT EXISTS yfc_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) DEFAULT 0.00,
    buy_now_price DECIMAL(10, 2) NULL,
    category VARCHAR(100),
    condition_rating ENUM('new', 'like-new', 'excellent', 'good', 'fair', 'poor'),
    dimensions VARCHAR(100),
    weight VARCHAR(50),
    item_number VARCHAR(50),
    sort_order INT DEFAULT 0,
    status ENUM('available', 'sold', 'pending', 'cancelled') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id),
    INDEX idx_status (status),
    INDEX idx_category (category)
);

-- Item Images
CREATE TABLE IF NOT EXISTS yfc_item_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(50),
    is_primary BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE,
    INDEX idx_item (item_id)
);

-- Buyers (Temporary Authentication)
CREATE TABLE IF NOT EXISTS yfc_buyers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20),
    auth_method ENUM('sms', 'email') NOT NULL,
    auth_code VARCHAR(6),
    auth_code_expires TIMESTAMP NULL,
    auth_verified BOOLEAN DEFAULT FALSE,
    session_token VARCHAR(100) UNIQUE,
    session_expires TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id),
    INDEX idx_session (session_token),
    INDEX idx_auth (email, phone)
);

-- QR Code Access Log
CREATE TABLE IF NOT EXISTS yfc_access_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    access_method ENUM('qr_code', 'access_code', 'direct_link') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id) ON DELETE CASCADE,
    INDEX idx_sale (sale_id)
);

-- Seller Notifications
CREATE TABLE IF NOT EXISTS yfc_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    seller_id INT NOT NULL,
    sale_id INT,
    type ENUM('sale_ending', 'item_claimed', 'item_inquiry', 'system') NOT NULL,
    title VARCHAR(255),
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES yfc_sellers(id) ON DELETE CASCADE,
    FOREIGN KEY (sale_id) REFERENCES yfc_sales(id) ON DELETE SET NULL,
    INDEX idx_seller (seller_id),
    INDEX idx_read (is_read)
);

-- Claim Categories
CREATE TABLE IF NOT EXISTS yfc_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES yfc_categories(id) ON DELETE SET NULL,
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug)
);

-- Insert default categories
INSERT INTO yfc_categories (name, slug) VALUES
('Furniture', 'furniture'),
('Antiques', 'antiques'),
('Collectibles', 'collectibles'),
('Jewelry', 'jewelry'),
('Art', 'art'),
('Electronics', 'electronics'),
('Household', 'household'),
('Tools', 'tools'),
('Clothing', 'clothing'),
('Books', 'books'),
('Other', 'other');