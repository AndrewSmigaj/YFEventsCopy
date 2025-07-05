-- Seed script to create the two global chat rooms for YFEvents Communication System
-- This creates the Support and Selling Tips channels that all admins and sellers can access

-- First, ensure we have at least one admin user to be the creator
-- This assumes user ID 1 exists as an admin (adjust if needed)

-- Create the global Support chat room
INSERT INTO `communication_channels` 
(`name`, `slug`, `description`, `type`, `created_by_user_id`, `event_id`, `shop_id`, `is_archived`, `settings`, `message_count`, `participant_count`, `last_activity_at`, `created_at`, `updated_at`)
VALUES 
('Support Channel', 'support-channel', 'Get help and support from admins and other sellers', 'public', 1, NULL, NULL, FALSE, '{}', 0, 0, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    updated_at = NOW();

-- Create the global Selling Tips chat room  
INSERT INTO `communication_channels` 
(`name`, `slug`, `description`, `type`, `created_by_user_id`, `event_id`, `shop_id`, `is_archived`, `settings`, `message_count`, `participant_count`, `last_activity_at`, `created_at`, `updated_at`)
VALUES 
('Selling Tips', 'selling-tips', 'Share tips and best practices for successful estate sales', 'public', 1, NULL, NULL, FALSE, '{}', 0, 0, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    description = VALUES(description),
    updated_at = NOW();

-- Add welcome messages to both channels
INSERT INTO `communication_messages` 
(`channel_id`, `user_id`, `parent_message_id`, `content`, `content_type`, `is_pinned`, `is_edited`, `is_deleted`, `yfclaim_item_id`, `metadata`, `email_message_id`, `reply_count`, `reaction_count`, `created_at`, `updated_at`, `deleted_at`)
SELECT 
    id,
    1,
    NULL,
    'Welcome to the Support Channel! Feel free to ask questions and get help from the community.',
    'system',
    TRUE,
    FALSE,
    FALSE,
    NULL,
    '{}',
    NULL,
    0,
    0,
    NOW(),
    NOW(),
    NULL
FROM `communication_channels` 
WHERE `slug` = 'support-channel'
AND NOT EXISTS (
    SELECT 1 FROM `communication_messages` 
    WHERE `channel_id` = (SELECT `id` FROM `communication_channels` WHERE `slug` = 'support-channel' LIMIT 1)
    AND `content_type` = 'system'
    AND `is_pinned` = TRUE
);

INSERT INTO `communication_messages` 
(`channel_id`, `user_id`, `parent_message_id`, `content`, `content_type`, `is_pinned`, `is_edited`, `is_deleted`, `yfclaim_item_id`, `metadata`, `email_message_id`, `reply_count`, `reaction_count`, `created_at`, `updated_at`, `deleted_at`)
SELECT 
    id,
    1,
    NULL,
    'Welcome to Selling Tips! Share your experiences and learn from other sellers to make your estate sales more successful.',
    'system',
    TRUE,
    FALSE,
    FALSE,
    NULL,
    '{}',
    NULL,
    0,
    0,
    NOW(),
    NOW(),
    NULL
FROM `communication_channels` 
WHERE `slug` = 'selling-tips'
AND NOT EXISTS (
    SELECT 1 FROM `communication_messages` 
    WHERE `channel_id` = (SELECT `id` FROM `communication_channels` WHERE `slug` = 'selling-tips' LIMIT 1)
    AND `content_type` = 'system'
    AND `is_pinned` = TRUE
);

-- Update message counts for the channels
UPDATE `communication_channels` c
SET `message_count` = (
    SELECT COUNT(*) 
    FROM `communication_messages` m 
    WHERE m.channel_id = c.id AND m.is_deleted = FALSE
),
`last_activity_at` = NOW()
WHERE `slug` IN ('support-channel', 'selling-tips');

-- Output the created channels for verification
SELECT 'Global chat rooms created:' as Status;
SELECT `id`, `name`, `slug`, `type`, `description`, `message_count` 
FROM `communication_channels` 
WHERE `slug` IN ('support-channel', 'selling-tips');