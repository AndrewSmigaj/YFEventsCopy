-- YFChat Module Database Schema
-- Real-time messaging system for YFEvents

-- Conversations table - stores chat conversations
CREATE TABLE IF NOT EXISTS `chat_conversations` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `type` ENUM('direct', 'group', 'event', 'forum_topic') NOT NULL DEFAULT 'direct',
    `title` VARCHAR(200) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_by` INT(11) NOT NULL,
    `event_id` INT(11) DEFAULT NULL,
    `forum_topic_id` INT(11) DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `is_private` BOOLEAN DEFAULT FALSE,
    `max_participants` INT DEFAULT NULL,
    `last_message_id` INT(11) DEFAULT NULL,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`forum_topic_id`) REFERENCES `forum_topics`(`id`) ON DELETE CASCADE,
    INDEX `idx_conversations_type` (`type`),
    INDEX `idx_conversations_creator` (`created_by`),
    INDEX `idx_conversations_event` (`event_id`),
    INDEX `idx_conversations_topic` (`forum_topic_id`),
    INDEX `idx_conversations_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat participants - manages who is in each conversation
CREATE TABLE IF NOT EXISTS `chat_participants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `conversation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `role` ENUM('admin', 'moderator', 'member') DEFAULT 'member',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `left_at` TIMESTAMP NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `notifications_enabled` BOOLEAN DEFAULT TRUE,
    `last_read_message_id` INT(11) DEFAULT NULL,
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_participant` (`conversation_id`, `user_id`),
    INDEX `idx_participants_conversation` (`conversation_id`),
    INDEX `idx_participants_user` (`user_id`),
    INDEX `idx_participants_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages - stores all messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `conversation_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `parent_message_id` INT(11) DEFAULT NULL,
    `message_type` ENUM('text', 'image', 'file', 'system', 'emoji') DEFAULT 'text',
    `content` TEXT NOT NULL,
    `metadata` JSON DEFAULT NULL,
    `is_edited` BOOLEAN DEFAULT FALSE,
    `edited_at` TIMESTAMP NULL,
    `is_deleted` BOOLEAN DEFAULT FALSE,
    `deleted_at` TIMESTAMP NULL,
    `deleted_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_message_id`) REFERENCES `chat_messages`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_messages_conversation` (`conversation_id`),
    INDEX `idx_messages_user` (`user_id`),
    INDEX `idx_messages_created` (`created_at`),
    INDEX `idx_messages_parent` (`parent_message_id`),
    INDEX `idx_messages_type` (`message_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message reactions - emoji reactions to messages
CREATE TABLE IF NOT EXISTS `chat_message_reactions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `message_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `emoji` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_reaction` (`message_id`, `user_id`, `emoji`),
    INDEX `idx_reactions_message` (`message_id`),
    INDEX `idx_reactions_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat attachments - files, images uploaded to chat
CREATE TABLE IF NOT EXISTS `chat_attachments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `message_id` INT(11) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(100) NOT NULL,
    `file_size` INT(11) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `thumbnail_path` VARCHAR(500) DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_attachments_message` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat notifications - push notifications for messages
CREATE TABLE IF NOT EXISTS `chat_notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `conversation_id` INT(11) NOT NULL,
    `message_id` INT(11) NOT NULL,
    `notification_type` ENUM('mention', 'direct_message', 'group_message') NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`user_id`),
    INDEX `idx_notifications_conversation` (`conversation_id`),
    INDEX `idx_notifications_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User chat settings and preferences
CREATE TABLE IF NOT EXISTS `chat_user_settings` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `global_notifications` BOOLEAN DEFAULT TRUE,
    `sound_notifications` BOOLEAN DEFAULT TRUE,
    `desktop_notifications` BOOLEAN DEFAULT TRUE,
    `email_notifications` BOOLEAN DEFAULT FALSE,
    `status` ENUM('online', 'away', 'busy', 'invisible') DEFAULT 'online',
    `status_message` VARCHAR(200) DEFAULT NULL,
    `theme` ENUM('light', 'dark', 'auto') DEFAULT 'auto',
    `font_size` ENUM('small', 'medium', 'large') DEFAULT 'medium',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_settings` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User presence tracking for online status
CREATE TABLE IF NOT EXISTS `chat_user_presence` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `status` ENUM('online', 'away', 'offline') DEFAULT 'online',
    `last_seen` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `session_id` VARCHAR(128) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_presence_user` (`user_id`),
    INDEX `idx_presence_status` (`status`),
    INDEX `idx_presence_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat moderation logs
CREATE TABLE IF NOT EXISTS `chat_moderation_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `moderator_id` INT(11) NOT NULL,
    `target_user_id` INT(11) DEFAULT NULL,
    `conversation_id` INT(11) DEFAULT NULL,
    `message_id` INT(11) DEFAULT NULL,
    `action` ENUM('delete_message', 'edit_message', 'kick_user', 'ban_user', 'mute_user', 'warn_user') NOT NULL,
    `reason` TEXT DEFAULT NULL,
    `duration` INT DEFAULT NULL, -- seconds for temporary actions
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`moderator_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages`(`id`) ON DELETE CASCADE,
    INDEX `idx_moderation_moderator` (`moderator_id`),
    INDEX `idx_moderation_target` (`target_user_id`),
    INDEX `idx_moderation_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Triggers to update conversation last_activity
DELIMITER $$
CREATE TRIGGER update_conversation_last_activity 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    UPDATE chat_conversations 
    SET last_message_id = NEW.id,
        last_activity = NEW.created_at
    WHERE id = NEW.conversation_id;
END$$

-- Trigger to update participant last_seen
CREATE TRIGGER update_participant_last_seen 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    UPDATE chat_participants 
    SET last_seen = NEW.created_at
    WHERE conversation_id = NEW.conversation_id 
    AND user_id = NEW.user_id;
END$$

-- Trigger to create notifications for mentions and direct messages
CREATE TRIGGER create_chat_notification 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    DECLARE conversation_type VARCHAR(20);
    
    SELECT type INTO conversation_type 
    FROM chat_conversations 
    WHERE id = NEW.conversation_id;
    
    -- Create notifications for direct messages
    IF conversation_type = 'direct' THEN
        INSERT INTO chat_notifications (user_id, conversation_id, message_id, notification_type)
        SELECT p.user_id, NEW.conversation_id, NEW.id, 'direct_message'
        FROM chat_participants p
        WHERE p.conversation_id = NEW.conversation_id 
        AND p.user_id != NEW.user_id 
        AND p.is_active = TRUE
        AND p.notifications_enabled = TRUE;
    END IF;
    
    -- Create notifications for mentions (check for @username in content)
    IF NEW.content LIKE '%@%' THEN
        INSERT INTO chat_notifications (user_id, conversation_id, message_id, notification_type)
        SELECT u.id, NEW.conversation_id, NEW.id, 'mention'
        FROM users u
        INNER JOIN chat_participants p ON u.id = p.user_id
        WHERE p.conversation_id = NEW.conversation_id 
        AND p.user_id != NEW.user_id 
        AND p.is_active = TRUE
        AND p.notifications_enabled = TRUE
        AND NEW.content LIKE CONCAT('%@', u.username, '%');
    END IF;
END$$
DELIMITER ;

-- Insert default chat settings for existing users
INSERT INTO chat_user_settings (user_id)
SELECT id FROM users 
WHERE id NOT IN (SELECT user_id FROM chat_user_settings);

-- Create indexes for performance optimization
CREATE INDEX idx_chat_messages_conversation_created ON chat_messages(conversation_id, created_at);
CREATE INDEX idx_chat_participants_user_active ON chat_participants(user_id, is_active);
CREATE INDEX idx_chat_notifications_user_unread ON chat_notifications(user_id, is_read);