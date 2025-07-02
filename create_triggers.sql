-- Create chat triggers with proper delimiters

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

CREATE TRIGGER update_participant_last_seen 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    UPDATE chat_participants 
    SET last_seen = NEW.created_at
    WHERE participant_id = NEW.sender_id 
    AND conversation_id = NEW.conversation_id;
END$$

CREATE TRIGGER create_chat_notification 
AFTER INSERT ON chat_messages
FOR EACH ROW
BEGIN
    DECLARE conversation_type VARCHAR(20);
    DECLARE participant_count INT;
    
    -- Get conversation type
    SELECT type INTO conversation_type 
    FROM chat_conversations 
    WHERE id = NEW.conversation_id;
    
    -- For direct messages, create notification for recipient
    IF conversation_type = 'direct' THEN
        INSERT INTO chat_notifications (user_id, conversation_id, message_id, type, created_at)
        SELECT participant_id, NEW.conversation_id, NEW.id, 'message', NOW()
        FROM chat_participants
        WHERE conversation_id = NEW.conversation_id
        AND participant_id != NEW.sender_id
        AND is_active = 1;
    END IF;
    
    -- Check for @mentions in the message
    IF NEW.content LIKE '%@%' THEN
        -- This is simplified - in production you'd parse @mentions properly
        INSERT INTO chat_notifications (user_id, conversation_id, message_id, type, created_at)
        SELECT DISTINCT cp.participant_id, NEW.conversation_id, NEW.id, 'mention', NOW()
        FROM chat_participants cp
        JOIN yfa_auth_users u ON cp.participant_id = u.id
        WHERE cp.conversation_id = NEW.conversation_id
        AND cp.participant_id != NEW.sender_id
        AND CONCAT('@', u.username) IN (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(NEW.content, '@', -1), ' ', 1)
        );
    END IF;
END$$

DELIMITER ;