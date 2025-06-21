-- Add missing columns to users table for communication admin

-- Add is_active column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1 AFTER role;

-- Add last_login_at column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS last_login_at DATETIME DEFAULT NULL AFTER updated_at;

-- Update last_login_at for existing users (set to created_at as default)
UPDATE users 
SET last_login_at = created_at 
WHERE last_login_at IS NULL;