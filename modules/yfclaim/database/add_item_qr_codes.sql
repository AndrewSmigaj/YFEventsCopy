-- Add QR code support for individual items
-- This migration adds the qr_code column to yfc_items table
-- to support item-specific QR codes for future functionality

ALTER TABLE yfc_items 
ADD COLUMN qr_code VARCHAR(100) UNIQUE AFTER item_number;

-- Add index for faster QR code lookups
ALTER TABLE yfc_items 
ADD INDEX idx_qr_code (qr_code);