-- Add auth_user_id column to yfc_sellers table
ALTER TABLE yfc_sellers 
ADD COLUMN auth_user_id INT UNSIGNED NULL AFTER email,
ADD INDEX idx_auth_user_id (auth_user_id),
ADD CONSTRAINT fk_seller_auth_user FOREIGN KEY (auth_user_id) REFERENCES yfa_auth_users(id) ON DELETE SET NULL;

-- Update existing sellers to link with YFAuth users by email
UPDATE yfc_sellers s
INNER JOIN yfa_auth_users u ON s.email = u.email
SET s.auth_user_id = u.id
WHERE s.auth_user_id IS NULL;

-- Make auth_user_id NOT NULL after populating
ALTER TABLE yfc_sellers 
MODIFY COLUMN auth_user_id INT UNSIGNED NOT NULL;

-- Make password_hash nullable since we're using YFAuth now
ALTER TABLE yfc_sellers 
MODIFY COLUMN password_hash VARCHAR(255) NULL;