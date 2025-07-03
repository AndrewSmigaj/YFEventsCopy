-- Remove offer/bid system from YFClaim module
-- Migration script to transition from offer-based to contact-based system

-- Step 1: Add price column to yfc_items table
ALTER TABLE yfc_items 
ADD COLUMN price DECIMAL(10, 2) DEFAULT 0.00 AFTER description;

-- Copy any existing starting_price data to price (for any test data)
UPDATE yfc_items SET price = starting_price WHERE price = 0;

-- Drop offer-related columns
ALTER TABLE yfc_items 
DROP COLUMN winning_offer_id,
DROP COLUMN offer_increment,
DROP COLUMN starting_price;

-- Step 2: Drop foreign key constraints before dropping tables
SET FOREIGN_KEY_CHECKS = 0;

-- Drop offer tables
DROP TABLE IF EXISTS yfc_offer_history;
DROP TABLE IF EXISTS yfc_offers;

SET FOREIGN_KEY_CHECKS = 1;

-- Step 3: Update notifications table to remove offer type
DELETE FROM yfc_notifications WHERE type = 'new_offer';
ALTER TABLE yfc_notifications 
MODIFY COLUMN type ENUM('sale_ending', 'item_claimed', 'system') NOT NULL;