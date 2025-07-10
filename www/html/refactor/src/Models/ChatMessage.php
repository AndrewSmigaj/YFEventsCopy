<?php
namespace YFEvents\Models;

use PDO;

class ChatMessage {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($data) {
        $sql = "INSERT INTO chat_messages (conversation_id, user_id, parent_message_id, message_type, content, metadata) 
                VALUES (:conversation_id, :user_id, :parent_message_id, :message_type, :content, :metadata)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $data['conversation_id'],
            ':user_id' => $data['user_id'],
            ':parent_message_id' => $data['parent_message_id'] ?? null,
            ':message_type' => $data['message_type'] ?? 'text',
            ':content' => $data['content'],
            ':metadata' => $data['metadata'] ? json_encode($data['metadata']) : null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function getById($id) {
        $sql = "SELECT m.*, 
                       u.username, u.first_name, u.last_name,
                       pm.content as parent_content,
                       pu.username as parent_username
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN chat_messages pm ON m.parent_message_id = pm.id
                LEFT JOIN users pu ON pm.user_id = pu.id
                WHERE m.id = :id AND m.is_deleted = FALSE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getConversationMessages($conversationId, $limit = 50, $offset = 0, $beforeMessageId = null) {
        $sql = "SELECT m.*, 
                       u.username, u.first_name, u.last_name,
                       pm.content as parent_content,
                       pu.username as parent_username,
                       GROUP_CONCAT(DISTINCT CONCAT(mr.emoji, ':', mr.user_id) SEPARATOR '|') as reactions
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN chat_messages pm ON m.parent_message_id = pm.id
                LEFT JOIN users pu ON pm.user_id = pu.id
                LEFT JOIN chat_message_reactions mr ON m.id = mr.message_id
                WHERE m.conversation_id = :conversation_id 
                AND m.is_deleted = FALSE";
        
        if ($beforeMessageId) {
            $sql .= " AND m.id < :before_id";
        }
        
        $sql .= " GROUP BY m.id
                  ORDER BY m.created_at DESC
                  LIMIT :limit OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        if ($beforeMessageId) {
            $stmt->bindValue(':before_id', $beforeMessageId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Process reactions
        foreach ($messages as &$message) {
            $message['reactions_formatted'] = [];
            if ($message['reactions']) {
                $reactions = explode('|', $message['reactions']);
                $reactionCounts = [];
                
                foreach ($reactions as $reaction) {
                    [$emoji, $userId] = explode(':', $reaction);
                    if (!isset($reactionCounts[$emoji])) {
                        $reactionCounts[$emoji] = ['count' => 0, 'users' => []];
                    }
                    $reactionCounts[$emoji]['count']++;
                    $reactionCounts[$emoji]['users'][] = $userId;
                }
                
                $message['reactions_formatted'] = $reactionCounts;
            }
            unset($message['reactions']);
        }
        
        return array_reverse($messages); // Return in chronological order
    }
    
    public function getMessagesSince($conversationId, $sinceId) {
        $sql = "SELECT m.*, 
                       u.username, u.first_name, u.last_name,
                       pm.content as parent_content,
                       pu.username as parent_username
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN chat_messages pm ON m.parent_message_id = pm.id
                LEFT JOIN users pu ON pm.user_id = pu.id
                WHERE m.conversation_id = :conversation_id 
                AND m.id > :since_id
                AND m.is_deleted = FALSE
                ORDER BY m.created_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':since_id' => $sinceId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function searchMessages($conversationId, $query, $limit = 20) {
        $sql = "SELECT m.*, 
                       u.username, u.first_name, u.last_name
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.conversation_id = :conversation_id 
                AND m.content LIKE :query
                AND m.is_deleted = FALSE
                ORDER BY m.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':query', "%$query%", PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $content, $editedBy = null) {
        $sql = "UPDATE chat_messages 
                SET content = :content, 
                    is_edited = TRUE, 
                    edited_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':content' => $content
        ]);
    }
    
    public function delete($id, $deletedBy = null) {
        $sql = "UPDATE chat_messages 
                SET is_deleted = TRUE, 
                    deleted_at = CURRENT_TIMESTAMP,
                    deleted_by = :deleted_by
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':deleted_by' => $deletedBy
        ]);
    }
    
    public function addReaction($messageId, $userId, $emoji) {
        $sql = "INSERT INTO chat_message_reactions (message_id, user_id, emoji) 
                VALUES (:message_id, :user_id, :emoji)
                ON DUPLICATE KEY UPDATE created_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':message_id' => $messageId,
            ':user_id' => $userId,
            ':emoji' => $emoji
        ]);
    }
    
    public function removeReaction($messageId, $userId, $emoji) {
        $sql = "DELETE FROM chat_message_reactions 
                WHERE message_id = :message_id AND user_id = :user_id AND emoji = :emoji";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':message_id' => $messageId,
            ':user_id' => $userId,
            ':emoji' => $emoji
        ]);
    }
    
    public function getMessageReactions($messageId) {
        $sql = "SELECT emoji, COUNT(*) as count,
                       GROUP_CONCAT(u.username) as usernames
                FROM chat_message_reactions mr
                LEFT JOIN users u ON mr.user_id = u.id
                WHERE mr.message_id = :message_id
                GROUP BY emoji
                ORDER BY count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':message_id' => $messageId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getUnreadCount($conversationId, $userId) {
        $sql = "SELECT COUNT(*) 
                FROM chat_messages m
                LEFT JOIN chat_participants p ON m.conversation_id = p.conversation_id AND p.user_id = :user_id
                WHERE m.conversation_id = :conversation_id
                AND m.user_id != :user_id
                AND m.is_deleted = FALSE
                AND (p.last_read_message_id IS NULL OR m.id > p.last_read_message_id)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchColumn();
    }
    
    public function markAsRead($conversationId, $userId, $messageId = null) {
        if ($messageId) {
            $sql = "UPDATE chat_participants 
                    SET last_read_message_id = :message_id
                    WHERE conversation_id = :conversation_id AND user_id = :user_id";
            $params = [
                ':conversation_id' => $conversationId,
                ':user_id' => $userId,
                ':message_id' => $messageId
            ];
        } else {
            // Mark all messages as read up to the latest
            $sql = "UPDATE chat_participants p
                    SET last_read_message_id = (
                        SELECT MAX(id) FROM chat_messages 
                        WHERE conversation_id = :conversation_id
                    )
                    WHERE p.conversation_id = :conversation_id AND p.user_id = :user_id";
            $params = [
                ':conversation_id' => $conversationId,
                ':user_id' => $userId
            ];
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}