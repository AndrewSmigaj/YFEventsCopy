<?php
namespace YFEvents\Models;

use PDO;

class ChatParticipant {
    private $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function addParticipant($conversationId, $userId, $role = 'member') {
        $sql = "INSERT INTO chat_participants (conversation_id, user_id, role) 
                VALUES (:conversation_id, :user_id, :role)
                ON DUPLICATE KEY UPDATE 
                is_active = TRUE, 
                left_at = NULL,
                joined_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
            ':role' => $role
        ]);
    }
    
    public function removeParticipant($conversationId, $userId) {
        $sql = "UPDATE chat_participants 
                SET is_active = FALSE, left_at = CURRENT_TIMESTAMP
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
    }
    
    public function getParticipants($conversationId, $activeOnly = true) {
        $sql = "SELECT p.*, 
                       u.username, u.first_name, u.last_name, u.email,
                       cus.status as user_status,
                       cup.status as presence_status,
                       cup.last_seen
                FROM chat_participants p
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN chat_user_settings cus ON p.user_id = cus.user_id
                LEFT JOIN chat_user_presence cup ON p.user_id = cup.user_id
                WHERE p.conversation_id = :conversation_id";
        
        if ($activeOnly) {
            $sql .= " AND p.is_active = TRUE";
        }
        
        $sql .= " ORDER BY p.role DESC, p.joined_at ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':conversation_id' => $conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getParticipant($conversationId, $userId) {
        $sql = "SELECT p.*, 
                       u.username, u.first_name, u.last_name, u.email
                FROM chat_participants p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.conversation_id = :conversation_id 
                AND p.user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function isParticipant($conversationId, $userId) {
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE conversation_id = :conversation_id 
                AND user_id = :user_id 
                AND is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function updateRole($conversationId, $userId, $role) {
        $sql = "UPDATE chat_participants 
                SET role = :role 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
            ':role' => $role
        ]);
    }
    
    public function updateNotificationSettings($conversationId, $userId, $enabled) {
        $sql = "UPDATE chat_participants 
                SET notifications_enabled = :enabled 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId,
            ':enabled' => $enabled
        ]);
    }
    
    public function updateLastSeen($conversationId, $userId) {
        $sql = "UPDATE chat_participants 
                SET last_seen = CURRENT_TIMESTAMP 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
    }
    
    public function getParticipantRole($conversationId, $userId) {
        $sql = "SELECT role FROM chat_participants 
                WHERE conversation_id = :conversation_id 
                AND user_id = :user_id 
                AND is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':conversation_id' => $conversationId,
            ':user_id' => $userId
        ]);
        return $stmt->fetchColumn();
    }
    
    public function canModerate($conversationId, $userId) {
        $role = $this->getParticipantRole($conversationId, $userId);
        return in_array($role, ['admin', 'moderator']);
    }
    
    public function getConversationAdmins($conversationId) {
        $sql = "SELECT p.user_id, u.username, u.first_name, u.last_name
                FROM chat_participants p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.conversation_id = :conversation_id 
                AND p.role = 'admin' 
                AND p.is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':conversation_id' => $conversationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function transferOwnership($conversationId, $fromUserId, $toUserId) {
        try {
            $this->pdo->beginTransaction();
            
            // Remove admin role from current owner
            $sql = "UPDATE chat_participants 
                    SET role = 'member' 
                    WHERE conversation_id = :conversation_id AND user_id = :from_user";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':conversation_id' => $conversationId,
                ':from_user' => $fromUserId
            ]);
            
            // Give admin role to new owner
            $sql = "UPDATE chat_participants 
                    SET role = 'admin' 
                    WHERE conversation_id = :conversation_id AND user_id = :to_user";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':conversation_id' => $conversationId,
                ':to_user' => $toUserId
            ]);
            
            // Update conversation creator
            $sql = "UPDATE chat_conversations 
                    SET created_by = :to_user 
                    WHERE id = :conversation_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':conversation_id' => $conversationId,
                ':to_user' => $toUserId
            ]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function getActiveParticipantCount($conversationId) {
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE conversation_id = :conversation_id AND is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':conversation_id' => $conversationId]);
        return $stmt->fetchColumn();
    }
    
    public function getUserConversationCount($userId) {
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE user_id = :user_id AND is_active = TRUE";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchColumn();
    }
}