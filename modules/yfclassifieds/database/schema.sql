-- YFClassifieds Database Schema
-- Extends and shares tables with YFClaim module

-- Add classifieds-specific columns to shared items table
ALTER TABLE yfc_items 
ADD COLUMN IF NOT EXISTS listing_type ENUM('estate_sale', 'classified') DEFAULT 'estate_sale',
ADD COLUMN IF NOT EXISTS store_location VARCHAR(255) NULL COMMENT 'Physical store location for pickup',
ADD COLUMN IF NOT EXISTS available_until DATE NULL COMMENT 'Date when item is no longer available',
ADD COLUMN IF NOT EXISTS views INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS share_count INT DEFAULT 0,
ADD INDEX idx_listing_type (listing_type),
ADD INDEX idx_available_until (available_until);

-- Create item photos table (supports multiple photos per item)
CREATE TABLE IF NOT EXISTS yfc_item_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    photo_order INT DEFAULT 0,
    caption VARCHAR(255) NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE,
    INDEX idx_item_photos (item_id, photo_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create item locations table (for multiple store locations)
CREATE TABLE IF NOT EXISTS yfc_item_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    location_name VARCHAR(255) NOT NULL,
    address VARCHAR(500) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    phone VARCHAR(20) NULL,
    hours JSON NULL COMMENT 'Store hours in JSON format',
    is_primary BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE,
    INDEX idx_item_location (item_id),
    INDEX idx_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create social sharing tracking table
CREATE TABLE IF NOT EXISTS yfc_item_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    platform ENUM('facebook', 'twitter', 'pinterest', 'email', 'sms', 'whatsapp', 'other') NOT NULL,
    share_url VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE,
    INDEX idx_item_shares (item_id, platform),
    INDEX idx_share_date (shared_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create item categories mapping (many-to-many)
CREATE TABLE IF NOT EXISTS yfc_item_categories (
    item_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (item_id, category_id),
    FOREIGN KEY (item_id) REFERENCES yfc_items(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES yfc_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add classifieds-specific categories if not exists
INSERT IGNORE INTO yfc_categories (name, slug, parent_id, icon) VALUES
('For Sale', 'for-sale', NULL, '🛍️'),
('Electronics', 'electronics', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '📱'),
('Home & Garden', 'home-garden', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '🏡'),
('Clothing & Accessories', 'clothing', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '👔'),
('Sports & Outdoors', 'sports', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '⚽'),
('Toys & Games', 'toys', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '🎮'),
('Books & Media', 'books', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '📚'),
('Vehicles & Parts', 'vehicles', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '🚗'),
('Tools & Equipment', 'tools', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '🔧'),
('Collectibles', 'collectibles', (SELECT id FROM (SELECT id FROM yfc_categories WHERE slug = 'for-sale') AS tmp), '🏺');

-- Create views for classifieds
CREATE OR REPLACE VIEW vw_classified_items AS
SELECT 
    i.*,
    s.business_name as seller_name,
    s.email as seller_email,
    s.phone as seller_phone,
    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as categories,
    (SELECT photo_url FROM yfc_item_photos WHERE item_id = i.id AND is_primary = TRUE LIMIT 1) as primary_photo,
    (SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = i.id) as photo_count,
    (SELECT location_name FROM yfc_item_locations WHERE item_id = i.id AND is_primary = TRUE LIMIT 1) as primary_location
FROM yfc_items i
LEFT JOIN yfc_sellers s ON i.seller_id = s.id
LEFT JOIN yfc_item_categories ic ON i.id = ic.item_id
LEFT JOIN yfc_categories c ON ic.category_id = c.id
WHERE i.listing_type = 'classified'
AND (i.available_until IS NULL OR i.available_until >= CURDATE())
GROUP BY i.id;

-- Create stored procedures for classifieds
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS sp_create_classified_item(
    IN p_seller_id INT,
    IN p_title VARCHAR(255),
    IN p_description TEXT,
    IN p_price DECIMAL(10,2),
    IN p_store_location VARCHAR(255),
    IN p_category_ids JSON,
    IN p_available_days INT
)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_category_id INT;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_count INT;
    
    -- Insert the item
    INSERT INTO yfc_items (
        seller_id, 
        title, 
        description, 
        price, 
        listing_type,
        store_location,
        available_until,
        status
    ) VALUES (
        p_seller_id,
        p_title,
        p_description,
        p_price,
        'classified',
        p_store_location,
        DATE_ADD(CURDATE(), INTERVAL p_available_days DAY),
        'available'
    );
    
    SET v_item_id = LAST_INSERT_ID();
    
    -- Insert categories
    IF p_category_ids IS NOT NULL THEN
        SET v_count = JSON_LENGTH(p_category_ids);
        WHILE v_idx < v_count DO
            SET v_category_id = JSON_EXTRACT(p_category_ids, CONCAT('$[', v_idx, ']'));
            INSERT INTO yfc_item_categories (item_id, category_id) VALUES (v_item_id, v_category_id);
            SET v_idx = v_idx + 1;
        END WHILE;
    END IF;
    
    SELECT v_item_id as item_id;
END//

CREATE PROCEDURE IF NOT EXISTS sp_track_item_view(
    IN p_item_id INT
)
BEGIN
    UPDATE yfc_items 
    SET views = views + 1 
    WHERE id = p_item_id;
END//

CREATE PROCEDURE IF NOT EXISTS sp_track_item_share(
    IN p_item_id INT,
    IN p_platform VARCHAR(20),
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT
)
BEGIN
    INSERT INTO yfc_item_shares (item_id, platform, ip_address, user_agent)
    VALUES (p_item_id, p_platform, p_ip_address, p_user_agent);
    
    UPDATE yfc_items 
    SET share_count = share_count + 1 
    WHERE id = p_item_id;
END//

DELIMITER ;

-- Sample data for testing
INSERT INTO yfc_items (seller_id, title, description, price, listing_type, store_location, available_until, status) VALUES
(1, 'Vintage Record Player', 'Beautiful vintage turntable in working condition. Includes original manual.', 125.00, 'classified', 'Main Store - Downtown', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'available'),
(1, 'Mountain Bike - Like New', 'Trek mountain bike, barely used. 21-speed, aluminum frame.', 350.00, 'classified', 'Main Store - Downtown', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'available'),
(1, 'Antique Bookshelf', 'Solid oak bookshelf from the 1920s. Some wear but structurally sound.', 200.00, 'classified', 'Warehouse - North Location', DATE_ADD(CURDATE(), INTERVAL 21 DAY), 'available');