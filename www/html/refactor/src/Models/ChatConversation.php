<?php
namespace YFEvents\Models;

use PDO;

class ChatConversation {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        $sql = "INSERT INTO chat_conversations (type, title, description, created_by, event_id, forum_topic_id, is_private, max_participants) 
                VALUES (:type, :title, :description, :created_by, :event_id, :forum_topic_id, :is_private, :max_participants)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':type' => $data['type'],
            ':title' => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':created_by' => $data['created_by'],
            ':event_id' => $data['event_id'] ?? null,
            ':forum_topic_id' => $data['forum_topic_id'] ?? null,
            ':is_private' => $data['is_private'] ?? false,
            ':max_participants' => $data['max_participants'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function getById($id) {
        $sql = "SELECT c.*, 
                       u.username as creator_username,
                       u.first_name as creator_first_name,
                       u.last_name as creator_last_name,
                       e.title as event_title,
                       ft.title as forum_topic_title
                FROM chat_conversations c
                LEFT JOIN users u ON c.created_by = u.id
                LEFT JOIN events e ON c.event_id = e.id
                LEFT JOIN forum_topics ft ON c.forum_topic_id = ft.id
                WHERE c.id = :id AND c.is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getUserConversations($userId, $limit = 20, $offset = 0) {
        $sql = "SELECT c.*, 
                       u.username as creator_username,
                       u.first_name as creator_first_name,
                       u.last_name as creator_last_name,
                       e.title as event_title,
                       ft.title as forum_topic_title,
                       lm.content as last_message_content,
                       lm.created_at as last_message_time,
                       lmu.username as last_message_username,
                       COUNT(CASE WHEN cn.is_read = FALSE THEN 1 END) as unread_count
                FROM chat_conversations c
                INNER JOIN chat_participants cp ON c.id = cp.conversation_id
                LEFT JOIN users u ON c.created_by = u.id
                LEFT JOIN events e ON c.event_id = e.id
                LEFT JOIN forum_topics ft ON c.forum_topic_id = ft.id
                LEFT JOIN chat_messages lm ON c.last_message_id = lm.id
                LEFT JOIN users lmu ON lm.user_id = lmu.id
                LEFT JOIN chat_notifications cn ON c.id = cn.conversation_id AND cn.user_id = :user_id
                WHERE cp.user_id = :user_id 
                AND cp.is_active = TRUE 
                AND c.is_active = TRUE
                GROUP BY c.id
                ORDER BY c.last_activity DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function findDirectConversation($userId1, $userId2) {
        $sql = "SELECT c.* 
                FROM chat_conversations c
                INNER JOIN chat_participants cp1 ON c.id = cp1.conversation_id
                INNER JOIN chat_participants cp2 ON c.id = cp2.conversation_id
                WHERE c.type = 'direct'
                AND cp1.user_id = :user1 AND cp1.is_active = TRUE
                AND cp2.user_id = :user2 AND cp2.is_active = TRUE
                AND c.is_active = TRUE
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user1' => $userId1, ':user2' => $userId2]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getEventConversation($eventId) {
        $sql = "SELECT * FROM chat_conversations 
                WHERE event_id = :event_id AND type = 'event' AND is_active = TRUE
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':event_id' => $eventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getForumTopicConversation($topicId) {
        $sql = "SELECT * FROM chat_conversations 
                WHERE forum_topic_id = :topic_id AND type = 'forum_topic' AND is_active = TRUE
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':topic_id' => $topicId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updateLastActivity($conversationId) {
        $sql = "UPDATE chat_conversations 
                SET last_activity = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $conversationId]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'description', 'is_private', 'max_participants'])) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE chat_conversations SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $sql = "UPDATE chat_conversations SET is_active = FALSE WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function getParticipantCount($conversationId) {
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE conversation_id = :id AND is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $conversationId]);
        return $stmt->fetchColumn();
    }
    
    public function canUserJoin($conversationId, $userId) {
        $conversation = $this->getById($conversationId);
        if (!$conversation) {
            return false;
        }
        
        // Check if user is already a participant
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE conversation_id = :conv_id AND user_id = :user_id AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':conv_id' => $conversationId, ':user_id' => $userId]);
        
        if ($stmt->fetchColumn() > 0) {
            return false; // Already a participant
        }
        
        // Check max participants limit
        if ($conversation['max_participants']) {
            $currentCount = $this->getParticipantCount($conversationId);
            if ($currentCount >= $conversation['max_participants']) {
                return false;
            }
        }
        
        // Check if conversation is private
        if ($conversation['is_private']) {
            return false; // Private conversations require invitation
        }
        
        return true;
    }
}