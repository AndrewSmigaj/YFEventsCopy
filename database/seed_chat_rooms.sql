-- Seed script to create the two global chat rooms for YFEvents
-- This creates the Support and Selling Tips channels that all admins and sellers can access

-- First, ensure we have at least one admin user to be the creator
-- This assumes user ID 1 exists as an admin (adjust if needed)

-- Create the global Support chat room
INSERT INTO `chat_conversations` (`type`, `title`, `description`, `created_by`, `is_active`, `created_at`, `updated_at`)
SELECT 'support', 'Support Channel', 'Get help and support from admins and other sellers', 1, TRUE, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `chat_conversations` WHERE `type` = 'support' AND `title` = 'Support Channel'
);

-- Create the global Selling Tips chat room  
INSERT INTO `chat_conversations` (`type`, `title`, `description`, `created_by`, `is_active`, `created_at`, `updated_at`)
SELECT 'tips', 'Selling Tips', 'Share tips and best practices for successful estate sales', 1, TRUE, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `chat_conversations` WHERE `type` = 'tips' AND `title` = 'Selling Tips'
);

-- Add welcome messages to both channels
INSERT INTO `chat_messages` (`conversation_id`, `user_id`, `content`, `is_deleted`, `created_at`)
SELECT 
    (SELECT `id` FROM `chat_conversations` WHERE `type` = 'support' LIMIT 1),
    1,
    'Welcome to the Support Channel! Feel free to ask questions and get help from the community.',
    0,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `chat_messages` 
    WHERE `conversation_id` = (SELECT `id` FROM `chat_conversations` WHERE `type` = 'support' LIMIT 1)
);

INSERT INTO `chat_messages` (`conversation_id`, `user_id`, `content`, `is_deleted`, `created_at`)
SELECT 
    (SELECT `id` FROM `chat_conversations` WHERE `type` = 'tips' LIMIT 1),
    1,
    'Welcome to Selling Tips! Share your experiences and learn from other sellers to make your estate sales more successful.',
    0,
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `chat_messages` 
    WHERE `conversation_id` = (SELECT `id` FROM `chat_conversations` WHERE `type` = 'tips' LIMIT 1)
);

-- Output the created channels for verification
SELECT 'Global chat rooms created:' as Status;
SELECT `id`, `type`, `title`, `description` FROM `chat_conversations` WHERE `type` IN ('support', 'tips');