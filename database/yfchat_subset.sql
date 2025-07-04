-- YFChat Subset Schema for Admin-Seller Communication
-- Simplified version of yfchat_schema.sql with only essential tables
-- 
-- This schema provides:
-- - Support channel for sellers to get help
-- - Tips/Announcements channel for marketplace advice
-- - Direct messaging capability between admins and sellers
--
-- Integrates with YFAuth for user management

-- Simplified conversations table
CREATE TABLE IF NOT EXISTS `chat_conversations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` ENUM('support', 'tips', 'direct') NOT NULL DEFAULT 'support',
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT(11) NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_message_id` INT(11) DEFAULT NULL,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `yfa_auth_users`(`id`),
    INDEX `idx_conversations_type` (`type`),
    INDEX `idx_conversations_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat participants - tracks who is in each conversation
CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `conversation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `role` ENUM('admin', 'member') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_active` BOOLEAN DEFAULT TRUE,
    `last_read_message_id` INT(11) DEFAULT NULL,
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `yfa_auth_users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_participant` (`conversation_id`, `user_id`),
    INDEX `idx_participants_user` (`user_id`),
    INDEX `idx_participants_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages - stores all messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `conversation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `content` TEXT NOT NULL,
    `is_deleted` BOOLEAN DEFAULT FALSE,
    `deleted_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `yfa_auth_users`(`id`),
    INDEX `idx_messages_conversation` (`conversation_id`),
    INDEX `idx_messages_created` (`created_at`),
    INDEX `idx_messages_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat notifications - tracks unread messages
CREATE TABLE IF NOT EXISTS `chat_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `conversation_id` INT(11) NOT NULL,
    `message_id` INT(11) NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `yfa_auth_users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user_unread` (`user_id`, `is_read`),
    INDEX `idx_notifications_conversation` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default support channels
-- These are the two main channels all sellers can access
INSERT INTO `chat_conversations` (`type`, `title`, `description`, `created_by`) VALUES
('support', 'Seller Support', 'Get help with your estate sales and platform questions', 1),
('tips', 'Selling Tips & Announcements', 'Best practices and updates from the YFClaim team', 1)
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Create a trigger to update conversation last_activity when a message is sent
DELIMITER $$
CREATE TRIGGER update_conversation_activity 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    UPDATE chat_conversations 
    SET last_message_id = NEW.id,
        last_activity = NEW.created_at
    WHERE id = NEW.conversation_id;
    
    -- Update participant last_seen
    UPDATE chat_participants
    SET last_seen = NEW.created_at
    WHERE conversation_id = NEW.conversation_id
    AND user_id = NEW.user_id;
END$$

-- Create a trigger to generate notifications for other participants
CREATE TRIGGER create_message_notifications
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    -- Create notifications for all other active participants
    INSERT INTO chat_notifications (user_id, conversation_id, message_id)
    SELECT p.user_id, NEW.conversation_id, NEW.id
    FROM chat_participants p
    WHERE p.conversation_id = NEW.conversation_id 
    AND p.user_id != NEW.user_id 
    AND p.is_active = TRUE;
END$$

DELIMITER ;

-- Add indexes for common queries
CREATE INDEX idx_messages_conversation_created ON chat_messages(conversation_id, created_at);
CREATE INDEX idx_participants_user_active ON chat_participants(user_id, is_active);
CREATE INDEX idx_notifications_user_conversation ON chat_notifications(user_id, conversation_id, is_read);